"""Authentication dependency utilities for LAYA AI Service.

Provides role-based access control decorators and dependencies for FastAPI endpoints.
"""

from __future__ import annotations

from typing import Any, Callable

from fastapi import Depends, HTTPException, status
from fastapi.security import HTTPAuthorizationCredentials

from app.auth import verify_token, security
from app.auth.models import UserRole


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
            including 'sub' (user ID), 'email', 'role', and 'type'

    Raises:
        HTTPException: 401 Unauthorized if token is missing, invalid, or expired

    Example:
        @router.get("/profile")
        async def get_profile(current_user: dict = Depends(get_current_user)):
            return {"user_id": current_user["sub"], "email": current_user["email"]}
    """
    return await verify_token(credentials)


def require_role(*allowed_roles: UserRole) -> Callable:
    """Dependency factory for role-based access control.

    Creates a FastAPI dependency that validates the current user has one
    of the specified roles. Use this to protect endpoints that should only
    be accessible to users with specific roles.

    Args:
        *allowed_roles: One or more UserRole values that are allowed access

    Returns:
        Callable: A FastAPI dependency function that checks user roles

    Raises:
        HTTPException: 403 Forbidden if user doesn't have required role
        HTTPException: 401 Unauthorized if token is invalid

    Example:
        # Require admin role
        @router.delete("/users/{user_id}")
        async def delete_user(
            user_id: str,
            current_user: dict = Depends(require_role(UserRole.ADMIN))
        ):
            return {"message": "User deleted"}

        # Allow multiple roles
        @router.get("/reports")
        async def get_reports(
            current_user: dict = Depends(
                require_role(UserRole.ADMIN, UserRole.ACCOUNTANT)
            )
        ):
            return {"reports": [...]}

        # Protect entire router
        router = APIRouter(
            prefix="/admin",
            dependencies=[Depends(require_role(UserRole.ADMIN))]
        )
    """

    async def role_checker(
        current_user: dict[str, Any] = Depends(get_current_user),
    ) -> dict[str, Any]:
        """Check if the current user has one of the required roles.

        Args:
            current_user: Decoded JWT token payload from get_current_user dependency

        Returns:
            dict[str, Any]: The current user payload if authorized

        Raises:
            HTTPException: 403 Forbidden if user doesn't have required role
        """
        # Extract user role from token payload
        user_role = current_user.get("role")

        if not user_role:
            raise HTTPException(
                status_code=status.HTTP_403_FORBIDDEN,
                detail="User role not found in token",
            )

        # Check if user role matches any of the allowed roles
        allowed_role_values = [role.value for role in allowed_roles]

        if user_role not in allowed_role_values:
            roles_str = ", ".join(allowed_role_values)
            raise HTTPException(
                status_code=status.HTTP_403_FORBIDDEN,
                detail=f"Access denied. Required role(s): {roles_str}. Your role: {user_role}",
            )

        return current_user

    return role_checker
