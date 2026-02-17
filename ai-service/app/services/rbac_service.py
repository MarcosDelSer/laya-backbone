"""Service for Role-Based Access Control (RBAC) operations.

Provides permission checking, role assignment, group filtering, and
audit trail functionality for the 5-role RBAC system. Supports
group-level restrictions for educators and parent-specific access.
"""

from datetime import datetime
from typing import Optional
from uuid import UUID

from sqlalchemy import and_, select
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy.orm import selectinload

from app.models.rbac import (
    AuditLog,
    Permission,
    Role,
    RoleType,
    UserRole,
)
from app.schemas.rbac import (
    AuditAction,
    AuditLogResponse,
    PermissionCheckRequest,
    PermissionCheckResponse,
    PermissionResponse,
    RoleResponse,
    UserPermissionsResponse,
    UserRoleAssignment,
    UserRoleResponse,
)


class RBACServiceError(Exception):
    """Base exception for RBAC service errors."""

    pass


class RoleNotFoundError(RBACServiceError):
    """Raised when a role is not found."""

    pass


class UserRoleNotFoundError(RBACServiceError):
    """Raised when a user-role assignment is not found."""

    pass


class PermissionDeniedError(RBACServiceError):
    """Raised when permission is denied for an action."""

    pass


class InvalidAssignmentError(RBACServiceError):
    """Raised when a role assignment is invalid."""

    pass


class RBACService:
    """Service for managing role-based access control.

    This service provides methods for checking permissions, assigning roles,
    filtering data by group, and managing the RBAC system. It supports the
    5-role system (Director, Teacher, Assistant, Staff, Parent) with
    group-level restrictions.

    Attributes:
        db: Async database session for database operations
    """

    def __init__(self, db: AsyncSession) -> None:
        """Initialize the RBAC service.

        Args:
            db: Async database session
        """
        self.db = db

    # =========================================================================
    # Permission Checking Methods
    # =========================================================================

    async def check_permission(
        self,
        request: PermissionCheckRequest,
    ) -> PermissionCheckResponse:
        """Check if a user has a specific permission.

        Evaluates whether the user has permission to perform the specified
        action on the specified resource, considering their roles and
        any group-level restrictions.

        Args:
            request: The permission check request containing user, resource, and action

        Returns:
            PermissionCheckResponse indicating if the permission is allowed
        """
        # Get user's active roles
        user_roles = await self._get_user_roles(
            user_id=request.user_id,
            organization_id=request.organization_id,
            group_id=request.group_id,
        )

        if not user_roles:
            return PermissionCheckResponse(
                allowed=False,
                user_id=request.user_id,
                resource=request.resource,
                action=request.action,
                matched_role=None,
                reason="No active roles assigned to user",
            )

        # Check each role for the required permission
        for user_role in user_roles:
            role = user_role.role
            if not role or not role.is_active:
                continue

            # Check if the role has the required permission
            for permission in role.permissions:
                if not permission.is_active:
                    continue

                if self._permission_matches(
                    permission=permission,
                    resource=request.resource,
                    action=request.action,
                    group_id=request.group_id,
                    user_role=user_role,
                ):
                    return PermissionCheckResponse(
                        allowed=True,
                        user_id=request.user_id,
                        resource=request.resource,
                        action=request.action,
                        matched_role=role.name,
                        reason=f"Permission granted by role '{role.display_name}'",
                    )

        return PermissionCheckResponse(
            allowed=False,
            user_id=request.user_id,
            resource=request.resource,
            action=request.action,
            matched_role=None,
            reason="No matching permission found in user's roles",
        )

    async def has_permission(
        self,
        user_id: UUID,
        resource: str,
        action: str,
        organization_id: Optional[UUID] = None,
        group_id: Optional[UUID] = None,
    ) -> bool:
        """Check if a user has a specific permission (simple boolean check).

        Convenience method for checking permissions without the full response.

        Args:
            user_id: ID of the user to check
            resource: The resource to check access for
            action: The action to check permission for
            organization_id: Optional organization context
            group_id: Optional group context

        Returns:
            True if the user has the permission, False otherwise
        """
        request = PermissionCheckRequest(
            user_id=user_id,
            resource=resource,
            action=action,
            organization_id=organization_id,
            group_id=group_id,
        )
        result = await self.check_permission(request)
        return result.allowed

    async def get_user_permissions(
        self,
        user_id: UUID,
        organization_id: Optional[UUID] = None,
        group_id: Optional[UUID] = None,
    ) -> UserPermissionsResponse:
        """Get all permissions for a user.

        Retrieves all roles and their associated permissions for the user,
        considering organization and group context.

        Args:
            user_id: ID of the user
            organization_id: Optional organization context
            group_id: Optional group context

        Returns:
            UserPermissionsResponse with all roles and permissions
        """
        user_roles = await self._get_user_roles(
            user_id=user_id,
            organization_id=organization_id,
            group_id=group_id,
        )

        roles: list[RoleResponse] = []
        all_permissions: list[PermissionResponse] = []
        seen_permission_ids: set[UUID] = set()

        for user_role in user_roles:
            role = user_role.role
            if not role or not role.is_active:
                continue

            # Build role response with permissions
            role_permissions = [
                PermissionResponse(
                    id=p.id,
                    role_id=p.role_id,
                    resource=p.resource,
                    action=p.action,
                    conditions=p.conditions,
                    is_active=p.is_active,
                    created_at=p.created_at,
                )
                for p in role.permissions
                if p.is_active
            ]

            roles.append(
                RoleResponse(
                    id=role.id,
                    name=role.name,
                    display_name=role.display_name,
                    description=role.description,
                    is_system_role=role.is_system_role,
                    is_active=role.is_active,
                    permissions=role_permissions,
                )
            )

            # Aggregate unique permissions
            for permission in role_permissions:
                if permission.id not in seen_permission_ids:
                    seen_permission_ids.add(permission.id)
                    all_permissions.append(permission)

        return UserPermissionsResponse(
            user_id=user_id,
            roles=roles,
            permissions=all_permissions,
        )

    # =========================================================================
    # Role Management Methods
    # =========================================================================

    async def get_roles(
        self,
        include_inactive: bool = False,
    ) -> list[RoleResponse]:
        """Get all roles in the system.

        Args:
            include_inactive: Whether to include inactive roles

        Returns:
            List of all roles
        """
        query = select(Role).options(selectinload(Role.permissions))

        if not include_inactive:
            query = query.where(Role.is_active == True)

        result = await self.db.execute(query)
        roles = result.scalars().all()

        return [
            RoleResponse(
                id=role.id,
                name=role.name,
                display_name=role.display_name,
                description=role.description,
                is_system_role=role.is_system_role,
                is_active=role.is_active,
                permissions=[
                    PermissionResponse(
                        id=p.id,
                        role_id=p.role_id,
                        resource=p.resource,
                        action=p.action,
                        conditions=p.conditions,
                        is_active=p.is_active,
                        created_at=p.created_at,
                    )
                    for p in role.permissions
                    if p.is_active or include_inactive
                ],
            )
            for role in roles
        ]

    async def get_role_by_name(self, name: str) -> Optional[RoleResponse]:
        """Get a role by its name.

        Args:
            name: The role name to look up

        Returns:
            The role if found, None otherwise
        """
        query = (
            select(Role)
            .options(selectinload(Role.permissions))
            .where(Role.name == name)
        )
        result = await self.db.execute(query)
        role = result.scalar_one_or_none()

        if not role:
            return None

        return RoleResponse(
            id=role.id,
            name=role.name,
            display_name=role.display_name,
            description=role.description,
            is_system_role=role.is_system_role,
            is_active=role.is_active,
            permissions=[
                PermissionResponse(
                    id=p.id,
                    role_id=p.role_id,
                    resource=p.resource,
                    action=p.action,
                    conditions=p.conditions,
                    is_active=p.is_active,
                    created_at=p.created_at,
                )
                for p in role.permissions
                if p.is_active
            ],
        )

    async def get_role_by_type(self, role_type: RoleType) -> Optional[RoleResponse]:
        """Get a role by its type enum.

        Args:
            role_type: The RoleType enum value

        Returns:
            The role if found, None otherwise
        """
        return await self.get_role_by_name(role_type.value)

    # =========================================================================
    # User Role Assignment Methods
    # =========================================================================

    async def assign_role(
        self,
        assignment: UserRoleAssignment,
        assigned_by: Optional[UUID] = None,
    ) -> UserRoleResponse:
        """Assign a role to a user.

        Creates a new user-role assignment, optionally scoped to an
        organization or group.

        Args:
            assignment: The role assignment details
            assigned_by: ID of the user making the assignment

        Returns:
            The created user role assignment

        Raises:
            RoleNotFoundError: If the specified role doesn't exist
            InvalidAssignmentError: If the assignment is invalid
        """
        # Verify the role exists and is active
        role = await self._get_role_by_id(assignment.role_id)
        if not role:
            raise RoleNotFoundError(f"Role with ID {assignment.role_id} not found")

        if not role.is_active:
            raise InvalidAssignmentError(
                f"Cannot assign inactive role '{role.name}'"
            )

        # Check if assignment already exists
        existing = await self._find_existing_assignment(
            user_id=assignment.user_id,
            role_id=assignment.role_id,
            organization_id=assignment.organization_id,
            group_id=assignment.group_id,
        )

        if existing:
            if existing.is_active:
                raise InvalidAssignmentError(
                    f"User already has role '{role.name}' assigned"
                )
            # Reactivate existing assignment
            existing.is_active = True
            existing.assigned_by = assigned_by
            existing.assigned_at = datetime.utcnow()
            existing.expires_at = assignment.expires_at
            await self.db.commit()
            return self._build_user_role_response(existing, role)

        # Create new assignment
        user_role = UserRole(
            user_id=assignment.user_id,
            role_id=assignment.role_id,
            organization_id=assignment.organization_id,
            group_id=assignment.group_id,
            assigned_by=assigned_by,
            expires_at=assignment.expires_at,
            is_active=True,
        )
        self.db.add(user_role)
        await self.db.commit()
        await self.db.refresh(user_role)

        return self._build_user_role_response(user_role, role)

    async def revoke_role(
        self,
        user_id: UUID,
        role_id: UUID,
        organization_id: Optional[UUID] = None,
        group_id: Optional[UUID] = None,
    ) -> bool:
        """Revoke a role from a user.

        Deactivates the user-role assignment rather than deleting it,
        preserving audit history.

        Args:
            user_id: ID of the user
            role_id: ID of the role to revoke
            organization_id: Optional organization scope
            group_id: Optional group scope

        Returns:
            True if the role was revoked, False if not found

        Raises:
            UserRoleNotFoundError: If the assignment is not found
        """
        assignment = await self._find_existing_assignment(
            user_id=user_id,
            role_id=role_id,
            organization_id=organization_id,
            group_id=group_id,
        )

        if not assignment:
            raise UserRoleNotFoundError(
                f"No active role assignment found for user {user_id}"
            )

        if not assignment.is_active:
            return False

        assignment.is_active = False
        await self.db.commit()
        return True

    async def get_user_roles(
        self,
        user_id: UUID,
        organization_id: Optional[UUID] = None,
        include_inactive: bool = False,
    ) -> list[UserRoleResponse]:
        """Get all role assignments for a user.

        Args:
            user_id: ID of the user
            organization_id: Optional organization filter
            include_inactive: Whether to include inactive assignments

        Returns:
            List of user role assignments
        """
        query = (
            select(UserRole)
            .options(selectinload(UserRole.role).selectinload(Role.permissions))
            .where(UserRole.user_id == user_id)
        )

        if organization_id:
            query = query.where(UserRole.organization_id == organization_id)

        if not include_inactive:
            query = query.where(UserRole.is_active == True)

        result = await self.db.execute(query)
        user_roles = result.scalars().all()

        return [
            self._build_user_role_response(ur, ur.role)
            for ur in user_roles
            if ur.role
        ]

    # =========================================================================
    # Group Filtering Methods
    # =========================================================================

    async def get_accessible_group_ids(
        self,
        user_id: UUID,
        organization_id: Optional[UUID] = None,
    ) -> list[UUID]:
        """Get the list of group IDs a user has access to.

        For directors, returns an empty list (indicating access to all groups).
        For other roles, returns the specific group IDs they're assigned to.

        Args:
            user_id: ID of the user
            organization_id: Optional organization context

        Returns:
            List of accessible group IDs, or empty list for full access
        """
        user_roles = await self._get_user_roles(
            user_id=user_id,
            organization_id=organization_id,
        )

        # Check if user has director role (full access)
        for user_role in user_roles:
            if user_role.role and user_role.role.name == RoleType.DIRECTOR.value:
                return []  # Empty list indicates full access

        # Collect all assigned group IDs
        group_ids: set[UUID] = set()
        for user_role in user_roles:
            if user_role.group_id:
                group_ids.add(user_role.group_id)

        return list(group_ids)

    async def filter_by_group_access(
        self,
        user_id: UUID,
        items: list[dict],
        group_id_field: str = "group_id",
        organization_id: Optional[UUID] = None,
    ) -> list[dict]:
        """Filter a list of items based on user's group access.

        Directors see all items. Other roles only see items matching
        their assigned groups.

        Args:
            user_id: ID of the user
            items: List of dictionaries to filter
            group_id_field: Name of the group ID field in items
            organization_id: Optional organization context

        Returns:
            Filtered list of items the user can access
        """
        accessible_groups = await self.get_accessible_group_ids(
            user_id=user_id,
            organization_id=organization_id,
        )

        # Empty list means full access (director)
        if not accessible_groups:
            return items

        # Filter items by accessible groups
        return [
            item
            for item in items
            if item.get(group_id_field) in accessible_groups
        ]

    async def can_access_group(
        self,
        user_id: UUID,
        group_id: UUID,
        organization_id: Optional[UUID] = None,
    ) -> bool:
        """Check if a user can access a specific group.

        Args:
            user_id: ID of the user
            group_id: ID of the group to check
            organization_id: Optional organization context

        Returns:
            True if the user can access the group
        """
        accessible_groups = await self.get_accessible_group_ids(
            user_id=user_id,
            organization_id=organization_id,
        )

        # Empty list means full access (director)
        if not accessible_groups:
            return True

        return group_id in accessible_groups

    # =========================================================================
    # Role Type Helper Methods
    # =========================================================================

    async def is_director(
        self,
        user_id: UUID,
        organization_id: Optional[UUID] = None,
    ) -> bool:
        """Check if a user has the Director role.

        Args:
            user_id: ID of the user
            organization_id: Optional organization context

        Returns:
            True if the user is a Director
        """
        return await self._has_role_type(
            user_id=user_id,
            role_type=RoleType.DIRECTOR,
            organization_id=organization_id,
        )

    async def is_teacher(
        self,
        user_id: UUID,
        organization_id: Optional[UUID] = None,
    ) -> bool:
        """Check if a user has the Teacher role.

        Args:
            user_id: ID of the user
            organization_id: Optional organization context

        Returns:
            True if the user is a Teacher
        """
        return await self._has_role_type(
            user_id=user_id,
            role_type=RoleType.TEACHER,
            organization_id=organization_id,
        )

    async def is_parent(
        self,
        user_id: UUID,
        organization_id: Optional[UUID] = None,
    ) -> bool:
        """Check if a user has the Parent role.

        Args:
            user_id: ID of the user
            organization_id: Optional organization context

        Returns:
            True if the user is a Parent
        """
        return await self._has_role_type(
            user_id=user_id,
            role_type=RoleType.PARENT,
            organization_id=organization_id,
        )

    # =========================================================================
    # Private Helper Methods
    # =========================================================================

    async def _get_user_roles(
        self,
        user_id: UUID,
        organization_id: Optional[UUID] = None,
        group_id: Optional[UUID] = None,
    ) -> list[UserRole]:
        """Get active user role assignments with roles and permissions loaded.

        Args:
            user_id: ID of the user
            organization_id: Optional organization filter
            group_id: Optional group filter

        Returns:
            List of UserRole objects with relationships loaded
        """
        query = (
            select(UserRole)
            .options(selectinload(UserRole.role).selectinload(Role.permissions))
            .where(
                and_(
                    UserRole.user_id == user_id,
                    UserRole.is_active == True,
                )
            )
        )

        if organization_id:
            query = query.where(
                (UserRole.organization_id == organization_id)
                | (UserRole.organization_id.is_(None))
            )

        if group_id:
            query = query.where(
                (UserRole.group_id == group_id) | (UserRole.group_id.is_(None))
            )

        # Filter out expired assignments
        query = query.where(
            (UserRole.expires_at.is_(None))
            | (UserRole.expires_at > datetime.utcnow())
        )

        result = await self.db.execute(query)
        return list(result.scalars().all())

    async def _get_role_by_id(self, role_id: UUID) -> Optional[Role]:
        """Get a role by ID.

        Args:
            role_id: ID of the role

        Returns:
            The Role if found, None otherwise
        """
        query = (
            select(Role)
            .options(selectinload(Role.permissions))
            .where(Role.id == role_id)
        )
        result = await self.db.execute(query)
        return result.scalar_one_or_none()

    async def _find_existing_assignment(
        self,
        user_id: UUID,
        role_id: UUID,
        organization_id: Optional[UUID] = None,
        group_id: Optional[UUID] = None,
    ) -> Optional[UserRole]:
        """Find an existing user-role assignment.

        Args:
            user_id: ID of the user
            role_id: ID of the role
            organization_id: Optional organization scope
            group_id: Optional group scope

        Returns:
            The UserRole if found, None otherwise
        """
        conditions = [
            UserRole.user_id == user_id,
            UserRole.role_id == role_id,
        ]

        if organization_id:
            conditions.append(UserRole.organization_id == organization_id)
        else:
            conditions.append(UserRole.organization_id.is_(None))

        if group_id:
            conditions.append(UserRole.group_id == group_id)
        else:
            conditions.append(UserRole.group_id.is_(None))

        query = select(UserRole).where(and_(*conditions))
        result = await self.db.execute(query)
        return result.scalar_one_or_none()

    async def _has_role_type(
        self,
        user_id: UUID,
        role_type: RoleType,
        organization_id: Optional[UUID] = None,
    ) -> bool:
        """Check if a user has a specific role type.

        Args:
            user_id: ID of the user
            role_type: The role type to check
            organization_id: Optional organization context

        Returns:
            True if the user has the role type
        """
        user_roles = await self._get_user_roles(
            user_id=user_id,
            organization_id=organization_id,
        )

        for user_role in user_roles:
            if user_role.role and user_role.role.name == role_type.value:
                return True

        return False

    def _permission_matches(
        self,
        permission: Permission,
        resource: str,
        action: str,
        group_id: Optional[UUID],
        user_role: UserRole,
    ) -> bool:
        """Check if a permission matches the requested resource and action.

        Handles wildcard permissions ('*') and group-level restrictions.

        Args:
            permission: The permission to check
            resource: The requested resource
            action: The requested action
            group_id: The group context (if any)
            user_role: The user's role assignment

        Returns:
            True if the permission matches
        """
        # Check resource match (supports wildcards)
        if permission.resource != "*" and permission.resource != resource:
            # Check for prefix wildcard (e.g., "children.*" matches "children.photos")
            if not (
                permission.resource.endswith(".*")
                and resource.startswith(permission.resource[:-2])
            ):
                return False

        # Check action match (supports wildcards)
        if permission.action != "*" and permission.action != action:
            return False

        # Check group-level restrictions
        if group_id and user_role.group_id:
            # User has group-specific assignment, must match the requested group
            if user_role.group_id != group_id:
                return False

        # Check permission conditions
        if permission.conditions:
            # Evaluate conditions (e.g., {"own_children_only": true})
            if not self._evaluate_conditions(permission.conditions, group_id):
                return False

        return True

    def _evaluate_conditions(
        self,
        conditions: dict,
        group_id: Optional[UUID],
    ) -> bool:
        """Evaluate permission conditions.

        Args:
            conditions: The conditions dictionary
            group_id: The group context

        Returns:
            True if conditions are satisfied
        """
        # Group restriction condition
        if conditions.get("group_restricted") and not group_id:
            return False

        # Additional condition types can be added here
        return True

    def _build_user_role_response(
        self,
        user_role: UserRole,
        role: Role,
    ) -> UserRoleResponse:
        """Build a UserRoleResponse from database objects.

        Args:
            user_role: The UserRole database object
            role: The Role database object

        Returns:
            UserRoleResponse schema object
        """
        role_response = RoleResponse(
            id=role.id,
            name=role.name,
            display_name=role.display_name,
            description=role.description,
            is_system_role=role.is_system_role,
            is_active=role.is_active,
            permissions=[
                PermissionResponse(
                    id=p.id,
                    role_id=p.role_id,
                    resource=p.resource,
                    action=p.action,
                    conditions=p.conditions,
                    is_active=p.is_active,
                    created_at=p.created_at,
                )
                for p in role.permissions
                if p.is_active
            ],
        )

        return UserRoleResponse(
            id=user_role.id,
            user_id=user_role.user_id,
            role_id=user_role.role_id,
            organization_id=user_role.organization_id,
            group_id=user_role.group_id,
            assigned_by=user_role.assigned_by,
            assigned_at=user_role.assigned_at,
            expires_at=user_role.expires_at,
            is_active=user_role.is_active,
            role=role_response,
        )
