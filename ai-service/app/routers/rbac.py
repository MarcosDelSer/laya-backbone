"""FastAPI router for Role-Based Access Control (RBAC) management endpoints.

Provides endpoints for managing roles, permissions, user-role assignments,
and audit trail access. All endpoints require authentication and appropriate
role-based permissions.
"""

from typing import Any, Optional
from uuid import UUID

from fastapi import APIRouter, Depends, HTTPException, Query, status
from sqlalchemy.ext.asyncio import AsyncSession

from app.database import get_db
from app.dependencies import get_current_user, require_role
from app.models.rbac import RoleType
from app.schemas.rbac import (
    RevokeRoleRequest,
    RoleListResponse,
    RoleResponse,
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
