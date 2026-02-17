"""Service for Role-Based Access Control (RBAC) operations.

Provides permission checking, role assignment, group filtering, and
audit trail functionality for the 5-role RBAC system. Supports
group-level restrictions for educators and parent-specific access.

Integrates with NotificationService to alert directors of unauthorized
access attempts and AuditService for comprehensive audit logging.
"""

import logging
from datetime import datetime
from typing import TYPE_CHECKING, Optional
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

if TYPE_CHECKING:
    from app.services.audit_service import AuditService
    from app.services.notification_service import NotificationService

logger = logging.getLogger(__name__)


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

    Integrates with NotificationService and AuditService to provide:
    - Unauthorized access notifications to directors
    - Comprehensive audit trail for all access checks
    - Threshold-based alerts for repeated failed attempts

    Attributes:
        db: Async database session for database operations
        notification_service: Optional service for sending notifications
        audit_service: Optional service for audit logging
        failed_attempt_threshold: Number of failed attempts before alerting
        failed_attempt_window_minutes: Time window for counting failed attempts
    """

    # Default configuration for unauthorized access detection
    DEFAULT_FAILED_ATTEMPT_THRESHOLD = 5
    DEFAULT_FAILED_ATTEMPT_WINDOW_MINUTES = 15

    def __init__(
        self,
        db: AsyncSession,
        notification_service: Optional["NotificationService"] = None,
        audit_service: Optional["AuditService"] = None,
        failed_attempt_threshold: int = DEFAULT_FAILED_ATTEMPT_THRESHOLD,
        failed_attempt_window_minutes: int = DEFAULT_FAILED_ATTEMPT_WINDOW_MINUTES,
    ) -> None:
        """Initialize the RBAC service.

        Args:
            db: Async database session
            notification_service: Optional notification service for alerts
            audit_service: Optional audit service for logging
            failed_attempt_threshold: Number of failed attempts before alerting
            failed_attempt_window_minutes: Time window for failed attempt counting
        """
        self.db = db
        self._notification_service = notification_service
        self._audit_service = audit_service
        self.failed_attempt_threshold = failed_attempt_threshold
        self.failed_attempt_window_minutes = failed_attempt_window_minutes

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

    async def check_permission_with_audit(
        self,
        request: PermissionCheckRequest,
        ip_address: Optional[str] = None,
        user_agent: Optional[str] = None,
        notify_on_denial: bool = True,
    ) -> PermissionCheckResponse:
        """Check permission and log/notify on unauthorized access.

        This method extends the basic permission check with integrated
        audit logging and notification dispatch for unauthorized access
        attempts.

        Args:
            request: The permission check request
            ip_address: Optional IP address of the request
            user_agent: Optional user agent string
            notify_on_denial: Whether to send notification on denial

        Returns:
            PermissionCheckResponse indicating if the permission is allowed
        """
        result = await self.check_permission(request)

        if result.allowed:
            # Log successful access if audit service is available
            await self._log_access_granted(
                user_id=request.user_id,
                resource=request.resource,
                action=request.action,
                organization_id=request.organization_id,
                group_id=request.group_id,
                ip_address=ip_address,
                user_agent=user_agent,
            )
        else:
            # Handle access denial with logging and notifications
            await self._handle_access_denied(
                user_id=request.user_id,
                resource=request.resource,
                action=request.action,
                reason=result.reason,
                organization_id=request.organization_id,
                group_id=request.group_id,
                ip_address=ip_address,
                user_agent=user_agent,
                notify=notify_on_denial,
            )

        return result

    async def require_permission_with_audit(
        self,
        user_id: UUID,
        resource: str,
        action: str,
        organization_id: Optional[UUID] = None,
        group_id: Optional[UUID] = None,
        ip_address: Optional[str] = None,
        user_agent: Optional[str] = None,
    ) -> None:
        """Require a permission with audit logging, raising on denial.

        This is a convenience method that checks permission and raises
        PermissionDeniedError if access is denied, while also handling
        audit logging and notification dispatch.

        Args:
            user_id: ID of the user to check
            resource: The resource to check access for
            action: The action to check permission for
            organization_id: Optional organization context
            group_id: Optional group context
            ip_address: Optional IP address of the request
            user_agent: Optional user agent string

        Raises:
            PermissionDeniedError: If the user doesn't have the permission
        """
        request = PermissionCheckRequest(
            user_id=user_id,
            resource=resource,
            action=action,
            organization_id=organization_id,
            group_id=group_id,
        )
        result = await self.check_permission_with_audit(
            request=request,
            ip_address=ip_address,
            user_agent=user_agent,
            notify_on_denial=True,
        )

        if not result.allowed:
            raise PermissionDeniedError(
                f"User {user_id} denied access to {resource}:{action}. "
                f"Reason: {result.reason}"
            )

    # =========================================================================
    # Service Configuration Methods
    # =========================================================================

    def set_notification_service(
        self,
        notification_service: "NotificationService",
    ) -> None:
        """Set the notification service for unauthorized access alerts.

        Allows setting the notification service after initialization,
        useful for dependency injection scenarios.

        Args:
            notification_service: The notification service instance
        """
        self._notification_service = notification_service

    def set_audit_service(
        self,
        audit_service: "AuditService",
    ) -> None:
        """Set the audit service for access logging.

        Allows setting the audit service after initialization,
        useful for dependency injection scenarios.

        Args:
            audit_service: The audit service instance
        """
        self._audit_service = audit_service

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

    # =========================================================================
    # Unauthorized Access Detection & Notification Methods
    # =========================================================================

    async def _handle_access_denied(
        self,
        user_id: UUID,
        resource: str,
        action: str,
        reason: Optional[str] = None,
        organization_id: Optional[UUID] = None,
        group_id: Optional[UUID] = None,
        ip_address: Optional[str] = None,
        user_agent: Optional[str] = None,
        notify: bool = True,
    ) -> None:
        """Handle an access denied event with logging and notifications.

        This method:
        1. Logs the access denied event to the audit trail
        2. Checks if failed attempt threshold is exceeded
        3. Dispatches notifications to directors if configured

        Args:
            user_id: ID of the user who was denied
            resource: The resource that was denied
            action: The action that was denied
            reason: The reason for denial
            organization_id: Optional organization context
            group_id: Optional group context
            ip_address: Optional IP address of the request
            user_agent: Optional user agent string
            notify: Whether to dispatch notifications
        """
        details = {
            "resource": resource,
            "action": action,
            "reason": reason or "Permission denied",
        }
        if group_id:
            details["group_id"] = str(group_id)

        # Log the access denied event
        await self._log_access_denied(
            user_id=user_id,
            resource=resource,
            action=action,
            details=details,
            ip_address=ip_address,
            user_agent=user_agent,
        )

        if not notify or not self._notification_service:
            return

        # Check if we should dispatch notification based on attempt
        try:
            # Dispatch immediate notification for this unauthorized access
            await self._notification_service.notify_unauthorized_access(
                user_id=user_id,
                resource_type=resource,
                attempted_action=action,
                details=details,
                ip_address=ip_address,
                organization_id=organization_id,
            )

            # Check if threshold exceeded for multiple failed attempts
            await self._notification_service.check_and_notify_threshold(
                user_id=user_id,
                threshold=self.failed_attempt_threshold,
                time_window_minutes=self.failed_attempt_window_minutes,
                organization_id=organization_id,
            )
        except Exception as e:
            # Log notification errors but don't fail the permission check
            logger.warning(
                f"Failed to dispatch unauthorized access notification: {e}",
                extra={
                    "user_id": str(user_id),
                    "resource": resource,
                    "action": action,
                },
            )

    async def _log_access_granted(
        self,
        user_id: UUID,
        resource: str,
        action: str,
        organization_id: Optional[UUID] = None,
        group_id: Optional[UUID] = None,
        ip_address: Optional[str] = None,
        user_agent: Optional[str] = None,
    ) -> None:
        """Log an access granted event to the audit trail.

        Args:
            user_id: ID of the user who was granted access
            resource: The resource that was accessed
            action: The action that was performed
            organization_id: Optional organization context
            group_id: Optional group context
            ip_address: Optional IP address of the request
            user_agent: Optional user agent string
        """
        if not self._audit_service:
            return

        details = {
            "resource": resource,
            "action": action,
        }
        if organization_id:
            details["organization_id"] = str(organization_id)
        if group_id:
            details["group_id"] = str(group_id)

        try:
            await self._audit_service.log_access_granted(
                user_id=user_id,
                resource_type=resource,
                details=details,
                ip_address=ip_address,
                user_agent=user_agent,
            )
        except Exception as e:
            # Log audit errors but don't fail the operation
            logger.warning(
                f"Failed to log access granted event: {e}",
                extra={
                    "user_id": str(user_id),
                    "resource": resource,
                    "action": action,
                },
            )

    async def _log_access_denied(
        self,
        user_id: UUID,
        resource: str,
        action: str,
        details: Optional[dict] = None,
        ip_address: Optional[str] = None,
        user_agent: Optional[str] = None,
    ) -> None:
        """Log an access denied event to the audit trail.

        Args:
            user_id: ID of the user who was denied
            resource: The resource that was denied
            action: The action that was denied
            details: Optional additional details
            ip_address: Optional IP address of the request
            user_agent: Optional user agent string
        """
        if not self._audit_service:
            return

        try:
            await self._audit_service.log_access_denied(
                user_id=user_id,
                resource_type=resource,
                details=details,
                ip_address=ip_address,
                user_agent=user_agent,
            )
        except Exception as e:
            # Log audit errors but don't fail the operation
            logger.warning(
                f"Failed to log access denied event: {e}",
                extra={
                    "user_id": str(user_id),
                    "resource": resource,
                    "action": action,
                },
            )

    async def detect_suspicious_activity(
        self,
        user_id: UUID,
        organization_id: Optional[UUID] = None,
    ) -> dict:
        """Detect suspicious activity patterns for a user.

        Analyzes recent access patterns to identify potential security
        concerns such as repeated failed attempts or unusual access patterns.

        Args:
            user_id: ID of the user to analyze
            organization_id: Optional organization context

        Returns:
            Dictionary with suspicious activity analysis results
        """
        if not self._notification_service:
            return {
                "user_id": str(user_id),
                "analyzed": False,
                "reason": "Notification service not configured",
            }

        try:
            failed_count = await self._notification_service.count_failed_attempts(
                user_id=user_id,
                minutes=self.failed_attempt_window_minutes,
            )

            recent_attempts = await self._notification_service.get_recent_unauthorized_attempts(
                user_id=user_id,
                hours=24,
                limit=50,
            )

            is_suspicious = failed_count >= self.failed_attempt_threshold
            severity = "low"
            if failed_count >= self.failed_attempt_threshold * 2:
                severity = "critical"
            elif failed_count >= self.failed_attempt_threshold:
                severity = "high"
            elif failed_count >= self.failed_attempt_threshold // 2:
                severity = "medium"

            return {
                "user_id": str(user_id),
                "analyzed": True,
                "is_suspicious": is_suspicious,
                "severity": severity,
                "failed_attempts_recent": failed_count,
                "failed_attempts_24h": len(recent_attempts),
                "threshold": self.failed_attempt_threshold,
                "window_minutes": self.failed_attempt_window_minutes,
                "recent_attempts": recent_attempts[:10],  # Return last 10 attempts
            }
        except Exception as e:
            logger.error(
                f"Failed to detect suspicious activity: {e}",
                extra={"user_id": str(user_id)},
            )
            return {
                "user_id": str(user_id),
                "analyzed": False,
                "error": str(e),
            }

    # =========================================================================
    # Audit Log Methods
    # =========================================================================

    async def get_audit_logs(
        self,
        user_id: Optional[UUID] = None,
        action: Optional[str] = None,
        resource_type: Optional[str] = None,
        start_date: Optional[datetime] = None,
        end_date: Optional[datetime] = None,
        limit: int = 100,
        offset: int = 0,
    ) -> tuple[list[AuditLogResponse], int]:
        """Get audit logs with optional filtering.

        Retrieves audit log entries with filtering by user, action,
        resource type, and date range. Returns paginated results.

        Args:
            user_id: Filter by user ID
            action: Filter by action type
            resource_type: Filter by resource type
            start_date: Filter events after this date
            end_date: Filter events before this date
            limit: Maximum number of results to return
            offset: Number of results to skip

        Returns:
            Tuple of (list of audit log entries, total count)
        """
        from sqlalchemy import desc, func

        # Build the query with filters
        query = select(AuditLog)
        count_query = select(func.count(AuditLog.id))

        # Apply filters
        if user_id:
            query = query.where(AuditLog.user_id == user_id)
            count_query = count_query.where(AuditLog.user_id == user_id)

        if action:
            query = query.where(AuditLog.action == action)
            count_query = count_query.where(AuditLog.action == action)

        if resource_type:
            query = query.where(AuditLog.resource_type == resource_type)
            count_query = count_query.where(AuditLog.resource_type == resource_type)

        if start_date:
            query = query.where(AuditLog.created_at >= start_date)
            count_query = count_query.where(AuditLog.created_at >= start_date)

        if end_date:
            query = query.where(AuditLog.created_at <= end_date)
            count_query = count_query.where(AuditLog.created_at <= end_date)

        # Get total count
        count_result = await self.db.execute(count_query)
        total = count_result.scalar() or 0

        # Apply ordering and pagination
        query = query.order_by(desc(AuditLog.created_at))
        query = query.offset(offset).limit(limit)

        # Execute query
        result = await self.db.execute(query)
        audit_logs = result.scalars().all()

        # Convert to response objects
        responses = [
            AuditLogResponse(
                id=log.id,
                user_id=log.user_id,
                action=log.action,
                resource_type=log.resource_type,
                resource_id=log.resource_id,
                details=log.details,
                ip_address=log.ip_address,
                user_agent=log.user_agent,
                created_at=log.created_at,
            )
            for log in audit_logs
        ]

        return responses, total
