"""FastAPI router for Role-Based Access Control (RBAC) management endpoints.

Provides endpoints for managing roles, permissions, user-role assignments,
and audit trail access. All endpoints require authentication and appropriate
role-based permissions.
"""

from datetime import datetime
from typing import Any, Optional
from uuid import UUID

from fastapi import APIRouter, Depends, HTTPException, Query, status
from sqlalchemy.ext.asyncio import AsyncSession

from app.database import get_db
from app.dependencies import get_current_user, require_role
from app.models.rbac import RoleType
from app.schemas.rbac import (
    AuditLogListResponse,
    AuditLogResponse,
    PermissionCheckRequest,
    PermissionCheckResponse,
    PermissionResponse,
    RevokeRoleRequest,
    RoleListResponse,
    RoleResponse,
    UserPermissionsResponse,
    UserRoleAssignment,
    UserRoleListResponse,
    UserRoleResponse,
)
from app.services.rbac_service import (
    InvalidAssignmentError,
    RBACService,
    RBACServiceError,
    RoleNotFoundError,
    UserRoleNotFoundError,
)

router = APIRouter()


@router.get("/roles", response_model=list[RoleResponse])
async def list_roles(
    include_inactive: bool = Query(
        default=False,
        description="Whether to include inactive roles in the response",
    ),
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> list[RoleResponse]:
    """List all available roles in the system.

    Returns all active roles by default. Use include_inactive=true to also
    retrieve deactivated roles.

    Args:
        include_inactive: Whether to include inactive roles
        db: Async database session (injected)
        current_user: Authenticated user from JWT token (injected)

    Returns:
        List of RoleResponse containing all roles with their permissions

    Raises:
        HTTPException 401: When JWT token is missing or invalid
        HTTPException 500: When an unexpected error occurs
    """
    service = RBACService(db)

    try:
        roles = await service.get_roles(include_inactive=include_inactive)
        return roles
    except RBACServiceError as e:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"RBAC service error: {str(e)}",
        )


@router.get("/roles/{role_name}", response_model=RoleResponse)
async def get_role(
    role_name: str,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> RoleResponse:
    """Get a specific role by name.

    Args:
        role_name: The name of the role to retrieve
        db: Async database session (injected)
        current_user: Authenticated user from JWT token (injected)

    Returns:
        RoleResponse containing the role details and permissions

    Raises:
        HTTPException 401: When JWT token is missing or invalid
        HTTPException 404: When the role is not found
        HTTPException 500: When an unexpected error occurs
    """
    service = RBACService(db)

    try:
        role = await service.get_role_by_name(role_name)
        if not role:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail=f"Role '{role_name}' not found",
            )
        return role
    except RBACServiceError as e:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"RBAC service error: {str(e)}",
        )


@router.post("/roles/assign", response_model=UserRoleResponse)
async def assign_role(
    assignment: UserRoleAssignment,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(
        require_role([RoleType.DIRECTOR]),
    ),
) -> UserRoleResponse:
    """Assign a role to a user.

    Creates a new user-role assignment. Can optionally be scoped to a
    specific organization or group. Only users with the Director role
    can assign roles to other users.

    Args:
        assignment: The role assignment details including user_id and role_id
        db: Async database session (injected)
        current_user: Authenticated user with Director role (injected)

    Returns:
        UserRoleResponse containing the created assignment details

    Raises:
        HTTPException 400: When the assignment is invalid (e.g., role already assigned)
        HTTPException 401: When JWT token is missing or invalid
        HTTPException 403: When the user doesn't have Director role
        HTTPException 404: When the specified role is not found
        HTTPException 500: When an unexpected error occurs
    """
    service = RBACService(db)

    # Get the assigner's user ID from the token
    assigner_id: Optional[UUID] = None
    if current_user.get("sub"):
        try:
            assigner_id = UUID(current_user["sub"])
        except (ValueError, TypeError):
            pass

    try:
        result = await service.assign_role(
            assignment=assignment,
            assigned_by=assigner_id,
        )
        return result
    except RoleNotFoundError as e:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=str(e),
        )
    except InvalidAssignmentError as e:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=str(e),
        )
    except RBACServiceError as e:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"RBAC service error: {str(e)}",
        )


@router.post("/roles/revoke", response_model=dict)
async def revoke_role(
    request: RevokeRoleRequest,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(
        require_role([RoleType.DIRECTOR]),
    ),
) -> dict:
    """Revoke a role from a user.

    Deactivates an existing user-role assignment. The assignment record
    is preserved for audit purposes. Only users with the Director role
    can revoke roles from other users.

    Args:
        request: The revoke request containing user_id and role_id
        db: Async database session (injected)
        current_user: Authenticated user with Director role (injected)

    Returns:
        dict with success status and message

    Raises:
        HTTPException 401: When JWT token is missing or invalid
        HTTPException 403: When the user doesn't have Director role
        HTTPException 404: When the user-role assignment is not found
        HTTPException 500: When an unexpected error occurs
    """
    service = RBACService(db)

    try:
        revoked = await service.revoke_role(
            user_id=request.user_id,
            role_id=request.role_id,
            organization_id=request.organization_id,
            group_id=request.group_id,
        )

        if revoked:
            return {
                "success": True,
                "message": "Role revoked successfully",
            }
        else:
            return {
                "success": False,
                "message": "Role was already inactive",
            }
    except UserRoleNotFoundError as e:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=str(e),
        )
    except RBACServiceError as e:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"RBAC service error: {str(e)}",
        )


@router.get("/users/{user_id}/roles", response_model=list[UserRoleResponse])
async def get_user_roles(
    user_id: UUID,
    organization_id: Optional[UUID] = Query(
        default=None,
        description="Filter roles by organization",
    ),
    include_inactive: bool = Query(
        default=False,
        description="Whether to include inactive role assignments",
    ),
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> list[UserRoleResponse]:
    """Get all role assignments for a specific user.

    Returns all active role assignments for the user. Use include_inactive=true
    to also retrieve revoked assignments. Users can view their own roles,
    while Directors can view any user's roles.

    Args:
        user_id: ID of the user to get roles for
        organization_id: Optional filter by organization
        include_inactive: Whether to include inactive assignments
        db: Async database session (injected)
        current_user: Authenticated user from JWT token (injected)

    Returns:
        List of UserRoleResponse containing the user's role assignments

    Raises:
        HTTPException 401: When JWT token is missing or invalid
        HTTPException 403: When trying to view another user's roles without Director role
        HTTPException 500: When an unexpected error occurs
    """
    service = RBACService(db)

    # Check if user is viewing their own roles or is a director
    current_user_id_str = current_user.get("sub")
    is_self = False

    if current_user_id_str:
        try:
            current_user_id = UUID(current_user_id_str)
            is_self = current_user_id == user_id
        except (ValueError, TypeError):
            pass

    # If not viewing own roles, check for Director role
    if not is_self:
        org_id = None
        org_id_str = current_user.get("organization_id")
        if org_id_str:
            try:
                org_id = UUID(org_id_str)
            except (ValueError, TypeError):
                pass

        is_director = False
        if current_user_id_str:
            try:
                current_user_id = UUID(current_user_id_str)
                is_director = await service.is_director(
                    user_id=current_user_id,
                    organization_id=org_id,
                )
            except (ValueError, TypeError):
                pass

        if not is_director:
            raise HTTPException(
                status_code=status.HTTP_403_FORBIDDEN,
                detail="Access denied: can only view your own roles or requires Director role",
            )

    try:
        roles = await service.get_user_roles(
            user_id=user_id,
            organization_id=organization_id,
            include_inactive=include_inactive,
        )
        return roles
    except RBACServiceError as e:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"RBAC service error: {str(e)}",
        )


# =============================================================================
# Permission Endpoints
# =============================================================================


@router.get("/permissions", response_model=list[PermissionResponse])
async def list_permissions(
    role_id: Optional[UUID] = Query(
        default=None,
        description="Filter permissions by role ID",
    ),
    include_inactive: bool = Query(
        default=False,
        description="Whether to include inactive permissions in the response",
    ),
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> list[PermissionResponse]:
    """List all permissions in the system.

    Returns all active permissions by default. Optionally filter by role ID.
    Use include_inactive=true to also retrieve deactivated permissions.

    Args:
        role_id: Optional filter by role ID
        include_inactive: Whether to include inactive permissions
        db: Async database session (injected)
        current_user: Authenticated user from JWT token (injected)

    Returns:
        List of PermissionResponse containing all matching permissions

    Raises:
        HTTPException 401: When JWT token is missing or invalid
        HTTPException 500: When an unexpected error occurs
    """
    service = RBACService(db)

    try:
        # Get all roles with their permissions
        roles = await service.get_roles(include_inactive=include_inactive)

        # Flatten permissions from all roles
        permissions: list[PermissionResponse] = []
        seen_ids: set[UUID] = set()

        for role in roles:
            # Filter by role_id if specified
            if role_id and role.id != role_id:
                continue

            for permission in role.permissions:
                # Skip if already added or if inactive and not including inactive
                if permission.id in seen_ids:
                    continue
                if not include_inactive and not permission.is_active:
                    continue

                seen_ids.add(permission.id)
                permissions.append(permission)

        return permissions
    except RBACServiceError as e:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"RBAC service error: {str(e)}",
        )


@router.post("/permissions/check", response_model=PermissionCheckResponse)
async def check_permission(
    request: PermissionCheckRequest,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> PermissionCheckResponse:
    """Check if a user has a specific permission.

    Evaluates whether the specified user has permission to perform the given
    action on the given resource. Considers the user's roles and any
    group-level restrictions.

    Args:
        request: The permission check request containing user_id, resource, and action
        db: Async database session (injected)
        current_user: Authenticated user from JWT token (injected)

    Returns:
        PermissionCheckResponse indicating if the permission is allowed

    Raises:
        HTTPException 401: When JWT token is missing or invalid
        HTTPException 500: When an unexpected error occurs
    """
    service = RBACService(db)

    try:
        result = await service.check_permission(request)
        return result
    except RBACServiceError as e:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"RBAC service error: {str(e)}",
        )


@router.get("/users/{user_id}/permissions", response_model=UserPermissionsResponse)
async def get_user_permissions(
    user_id: UUID,
    organization_id: Optional[UUID] = Query(
        default=None,
        description="Filter permissions by organization context",
    ),
    group_id: Optional[UUID] = Query(
        default=None,
        description="Filter permissions by group context",
    ),
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> UserPermissionsResponse:
    """Get all permissions for a specific user.

    Retrieves all roles and their associated permissions for the user,
    considering organization and group context. Users can view their own
    permissions, while Directors can view any user's permissions.

    Args:
        user_id: ID of the user to get permissions for
        organization_id: Optional organization context filter
        group_id: Optional group context filter
        db: Async database session (injected)
        current_user: Authenticated user from JWT token (injected)

    Returns:
        UserPermissionsResponse containing all roles and aggregated permissions

    Raises:
        HTTPException 401: When JWT token is missing or invalid
        HTTPException 403: When trying to view another user's permissions without Director role
        HTTPException 500: When an unexpected error occurs
    """
    service = RBACService(db)

    # Check if user is viewing their own permissions or is a director
    current_user_id_str = current_user.get("sub")
    is_self = False

    if current_user_id_str:
        try:
            current_user_id = UUID(current_user_id_str)
            is_self = current_user_id == user_id
        except (ValueError, TypeError):
            pass

    # If not viewing own permissions, check for Director role
    if not is_self:
        org_id = None
        org_id_str = current_user.get("organization_id")
        if org_id_str:
            try:
                org_id = UUID(org_id_str)
            except (ValueError, TypeError):
                pass

        is_director = False
        if current_user_id_str:
            try:
                current_user_id = UUID(current_user_id_str)
                is_director = await service.is_director(
                    user_id=current_user_id,
                    organization_id=org_id,
                )
            except (ValueError, TypeError):
                pass

        if not is_director:
            raise HTTPException(
                status_code=status.HTTP_403_FORBIDDEN,
                detail="Access denied: can only view your own permissions or requires Director role",
            )

    try:
        permissions = await service.get_user_permissions(
            user_id=user_id,
            organization_id=organization_id,
            group_id=group_id,
        )
        return permissions
    except RBACServiceError as e:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"RBAC service error: {str(e)}",
        )


# =============================================================================
# Audit Trail Endpoints
# =============================================================================


@router.get(
    "/audit",
    response_model=AuditLogListResponse,
    summary="Get audit trail",
    description="Returns paginated audit log entries for RBAC events (Director only)",
)
async def get_audit_logs(
    user_id: Optional[UUID] = Query(
        default=None,
        description="Filter by user ID",
    ),
    action: Optional[str] = Query(
        default=None,
        max_length=100,
        description="Filter by action type (e.g., 'role_assigned', 'access_denied')",
    ),
    resource_type: Optional[str] = Query(
        default=None,
        max_length=100,
        description="Filter by resource type",
    ),
    start_date: Optional[datetime] = Query(
        default=None,
        description="Filter events after this date (ISO 8601 format)",
    ),
    end_date: Optional[datetime] = Query(
        default=None,
        description="Filter events before this date (ISO 8601 format)",
    ),
    limit: int = Query(
        default=100,
        ge=1,
        le=1000,
        description="Maximum number of results to return",
    ),
    offset: int = Query(
        default=0,
        ge=0,
        description="Number of results to skip for pagination",
    ),
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(
        require_role([RoleType.DIRECTOR]),
    ),
) -> AuditLogListResponse:
    """Get audit trail for RBAC events.

    Retrieves paginated audit log entries with optional filtering by user,
    action type, resource type, and date range. Only users with the
    Director role can access the audit trail.

    Args:
        user_id: Filter by specific user ID
        action: Filter by action type (e.g., 'role_assigned')
        resource_type: Filter by resource type
        start_date: Filter events after this date
        end_date: Filter events before this date
        limit: Maximum number of results (1-1000)
        offset: Number of results to skip
        db: Async database session (injected)
        current_user: Authenticated user with Director role (injected)

    Returns:
        AuditLogListResponse containing paginated audit log entries

    Raises:
        HTTPException 401: When JWT token is missing or invalid
        HTTPException 403: When the user doesn't have Director role
        HTTPException 500: When an unexpected error occurs
    """
    service = RBACService(db)

    try:
        audit_logs, total = await service.get_audit_logs(
            user_id=user_id,
            action=action,
            resource_type=resource_type,
            start_date=start_date,
            end_date=end_date,
            limit=limit,
            offset=offset,
        )

        return AuditLogListResponse(
            items=audit_logs,
            total=total,
            skip=offset,
            limit=limit,
        )
    except RBACServiceError as e:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"RBAC service error: {str(e)}",
        )
