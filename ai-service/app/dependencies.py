"""FastAPI dependency injection utilities for LAYA AI Service.

Provides reusable dependencies for authentication, database access,
and role-based access control (RBAC).
"""

from __future__ import annotations

from typing import Any, Callable, Sequence
from uuid import UUID

from fastapi import Depends, HTTPException, status
from fastapi.security import HTTPAuthorizationCredentials
from sqlalchemy.ext.asyncio import AsyncSession

from app.auth import security, verify_token
from app.database import get_db
from app.models.rbac import RoleType
from app.services.rbac_service import RBACService


async def get_current_user(
    credentials: HTTPAuthorizationCredentials = Depends(security),
) -> dict[str, Any]:
    """Dependency to get the current authenticated user from JWT token.

    This dependency extracts and validates the JWT token from the Authorization
    header and returns the decoded payload containing user information.

    Args:
        credentials: HTTP Authorization credentials injected by FastAPI

    Returns:
        dict[str, Any]: Decoded token payload containing user information

    Raises:
        HTTPException: 401 Unauthorized if token is missing, invalid, or expired

    Example:
        @app.get("/protected")
        async def protected_route(current_user: dict = Depends(get_current_user)):
            return {"user": current_user["sub"]}
    """
    return await verify_token(credentials)


async def get_optional_user(
    credentials: HTTPAuthorizationCredentials | None = Depends(
        security,
    ),
) -> dict[str, Any] | None:
    """Dependency to optionally get the current user if authenticated.

    Similar to get_current_user but returns None if no token is provided
    instead of raising an exception. Useful for endpoints that behave
    differently based on authentication status.

    Args:
        credentials: Optional HTTP Authorization credentials

    Returns:
        dict[str, Any] | None: Decoded token payload or None if not authenticated

    Example:
        @app.get("/items")
        async def get_items(current_user: dict | None = Depends(get_optional_user)):
            if current_user:
                return {"items": get_user_items(current_user["sub"])}
            return {"items": get_public_items()}
    """
    if credentials is None:
        return None

    return await verify_token(credentials)


def require_role(
    allowed_roles: RoleType | Sequence[RoleType],
) -> Callable[..., Any]:
    """Dependency factory that requires the user to have one of the specified roles.

    Creates a FastAPI dependency that checks if the authenticated user has
    at least one of the allowed roles. Raises 403 Forbidden if the user
    doesn't have the required role.

    Args:
        allowed_roles: A single RoleType or sequence of RoleTypes that are allowed

    Returns:
        Callable: A FastAPI dependency function

    Raises:
        HTTPException: 403 Forbidden if the user doesn't have the required role

    Example:
        @app.get("/admin")
        async def admin_route(
            current_user: dict = Depends(require_role(RoleType.DIRECTOR))
        ):
            return {"message": "Director access granted"}

        @app.get("/staff")
        async def staff_route(
            current_user: dict = Depends(require_role([RoleType.DIRECTOR, RoleType.TEACHER]))
        ):
            return {"message": "Staff access granted"}
    """
    # Normalize to a list of roles
    if isinstance(allowed_roles, RoleType):
        roles_list = [allowed_roles]
    else:
        roles_list = list(allowed_roles)

    async def role_dependency(
        current_user: dict[str, Any] = Depends(get_current_user),
        db: AsyncSession = Depends(get_db),
    ) -> dict[str, Any]:
        """Check if the current user has one of the allowed roles.

        Args:
            current_user: Authenticated user from JWT token
            db: Async database session

        Returns:
            dict[str, Any]: The current user if role check passes

        Raises:
            HTTPException: 403 Forbidden if the user doesn't have the required role
        """
        # Get user ID from token (sub claim contains the user ID)
        user_id_str = current_user.get("sub")
        if not user_id_str:
            raise HTTPException(
                status_code=status.HTTP_403_FORBIDDEN,
                detail="Invalid token: missing user ID",
            )

        try:
            user_id = UUID(user_id_str)
        except (ValueError, TypeError):
            raise HTTPException(
                status_code=status.HTTP_403_FORBIDDEN,
                detail="Invalid token: malformed user ID",
            )

        # Get organization ID from token if present
        organization_id = None
        org_id_str = current_user.get("organization_id")
        if org_id_str:
            try:
                organization_id = UUID(org_id_str)
            except (ValueError, TypeError):
                pass  # Ignore malformed organization ID

        rbac_service = RBACService(db)

        # Check if user has any of the allowed roles
        for role_type in roles_list:
            has_role = await rbac_service._has_role_type(
                user_id=user_id,
                role_type=role_type,
                organization_id=organization_id,
            )
            if has_role:
                return current_user

        # User doesn't have any of the allowed roles
        role_names = ", ".join(r.value for r in roles_list)
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail=f"Access denied: requires one of the following roles: {role_names}",
        )

    return role_dependency


def require_permission(
    resource: str,
    action: str,
) -> Callable[..., Any]:
    """Dependency factory that requires the user to have a specific permission.

    Creates a FastAPI dependency that checks if the authenticated user has
    permission to perform the specified action on the specified resource.
    Raises 403 Forbidden if the user doesn't have the required permission.

    Args:
        resource: The resource to check permission for (e.g., 'children', 'reports')
        action: The action to check permission for (e.g., 'read', 'write', 'delete')

    Returns:
        Callable: A FastAPI dependency function

    Raises:
        HTTPException: 403 Forbidden if the user doesn't have the required permission

    Example:
        @app.get("/children")
        async def get_children(
            current_user: dict = Depends(require_permission("children", "read"))
        ):
            return {"children": [...]}

        @app.post("/reports")
        async def create_report(
            current_user: dict = Depends(require_permission("reports", "write"))
        ):
            return {"message": "Report created"}
    """

    async def permission_dependency(
        current_user: dict[str, Any] = Depends(get_current_user),
        db: AsyncSession = Depends(get_db),
    ) -> dict[str, Any]:
        """Check if the current user has the required permission.

        Args:
            current_user: Authenticated user from JWT token
            db: Async database session

        Returns:
            dict[str, Any]: The current user if permission check passes

        Raises:
            HTTPException: 403 Forbidden if the user doesn't have the required permission
        """
        # Get user ID from token (sub claim contains the user ID)
        user_id_str = current_user.get("sub")
        if not user_id_str:
            raise HTTPException(
                status_code=status.HTTP_403_FORBIDDEN,
                detail="Invalid token: missing user ID",
            )

        try:
            user_id = UUID(user_id_str)
        except (ValueError, TypeError):
            raise HTTPException(
                status_code=status.HTTP_403_FORBIDDEN,
                detail="Invalid token: malformed user ID",
            )

        # Get organization and group IDs from token if present
        organization_id = None
        group_id = None

        org_id_str = current_user.get("organization_id")
        if org_id_str:
            try:
                organization_id = UUID(org_id_str)
            except (ValueError, TypeError):
                pass  # Ignore malformed organization ID

        group_id_str = current_user.get("group_id")
        if group_id_str:
            try:
                group_id = UUID(group_id_str)
            except (ValueError, TypeError):
                pass  # Ignore malformed group ID

        rbac_service = RBACService(db)

        # Check if user has the required permission
        has_perm = await rbac_service.has_permission(
            user_id=user_id,
            resource=resource,
            action=action,
            organization_id=organization_id,
            group_id=group_id,
        )

        if has_perm:
            return current_user

        # User doesn't have the required permission
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail=f"Access denied: requires '{action}' permission on '{resource}'",
        )

    return permission_dependency


def require_group_access(
    group_id_param: str = "group_id",
) -> Callable[..., Any]:
    """Dependency factory that requires the user to have access to a specific group.

    Creates a FastAPI dependency that checks if the authenticated user has
    access to a specific group (extracted from request parameters).
    Directors have access to all groups. Other roles only have access
    to groups they are explicitly assigned to.

    Args:
        group_id_param: Name of the parameter containing the group ID
                       (from path, query, or body)

    Returns:
        Callable: A FastAPI dependency function that checks group access

    Raises:
        HTTPException: 403 Forbidden if the user doesn't have access to the group

    Example:
        @app.get("/groups/{group_id}/children")
        async def get_group_children(
            group_id: UUID,
            current_user: dict = Depends(require_group_access("group_id"))
        ):
            return {"children": [...]}
    """

    async def group_access_dependency(
        current_user: dict[str, Any] = Depends(get_current_user),
        db: AsyncSession = Depends(get_db),
    ) -> dict[str, Any]:
        """Check if the current user has access to the specified group.

        Note: This dependency must be combined with a path/query parameter
        that provides the group_id. The actual group_id extraction happens
        in the endpoint; this dependency just ensures the user has appropriate
        role-based access.

        Args:
            current_user: Authenticated user from JWT token
            db: Async database session

        Returns:
            dict[str, Any]: The current user if group access is allowed

        Raises:
            HTTPException: 403 Forbidden if the user doesn't have group access
        """
        # Get user ID from token
        user_id_str = current_user.get("sub")
        if not user_id_str:
            raise HTTPException(
                status_code=status.HTTP_403_FORBIDDEN,
                detail="Invalid token: missing user ID",
            )

        try:
            user_id = UUID(user_id_str)
        except (ValueError, TypeError):
            raise HTTPException(
                status_code=status.HTTP_403_FORBIDDEN,
                detail="Invalid token: malformed user ID",
            )

        # Get organization ID from token
        organization_id = None
        org_id_str = current_user.get("organization_id")
        if org_id_str:
            try:
                organization_id = UUID(org_id_str)
            except (ValueError, TypeError):
                pass

        rbac_service = RBACService(db)

        # Directors have full access - no need to check specific group
        is_director = await rbac_service.is_director(
            user_id=user_id,
            organization_id=organization_id,
        )

        if is_director:
            return current_user

        # For non-directors, store accessible groups in the current_user dict
        # so the endpoint can use it for filtering
        accessible_groups = await rbac_service.get_accessible_group_ids(
            user_id=user_id,
            organization_id=organization_id,
        )

        # Add accessible groups to current_user for use by the endpoint
        current_user["_accessible_groups"] = accessible_groups

        return current_user

    return group_access_dependency
