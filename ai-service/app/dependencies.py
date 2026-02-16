"""FastAPI dependency injection utilities for LAYA AI Service.

Provides reusable dependencies for authentication and database access.
"""

from __future__ import annotations

from typing import Any

from fastapi import Depends, HTTPException, status
from fastapi.security import HTTPAuthorizationCredentials

from app.auth import (
    is_mfa_verified,
    requires_mfa,
    security,
    verify_mfa_token,
    verify_token,
)


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


async def get_mfa_verified_user(
    credentials: HTTPAuthorizationCredentials = Depends(security),
) -> dict[str, Any]:
    """Dependency to get the current user with MFA verification required.

    This dependency extends get_current_user by additionally verifying that
    MFA authentication has been completed for users who have MFA enabled.
    Use this for sensitive endpoints that require full MFA-verified access.

    The verification flow checks:
    1. Token is valid and not expired
    2. If user has MFA required (mfa_required claim), MFA must be verified
       (mfa_verified claim must be True)

    Args:
        credentials: HTTP Authorization credentials injected by FastAPI

    Returns:
        dict[str, Any]: Decoded token payload with MFA verification confirmed

    Raises:
        HTTPException: 401 Unauthorized if token is missing, invalid, or expired
        HTTPException: 403 Forbidden if MFA is required but not verified

    Example:
        @app.get("/sensitive-data")
        async def get_sensitive_data(
            current_user: dict = Depends(get_mfa_verified_user)
        ):
            # This endpoint requires MFA verification
            return {"sensitive": "data"}

        @app.post("/transfer-funds")
        async def transfer_funds(
            amount: float,
            current_user: dict = Depends(get_mfa_verified_user)
        ):
            # Financial operations require MFA
            return {"transferred": amount}
    """
    return await verify_mfa_token(credentials)


async def get_mfa_status_user(
    credentials: HTTPAuthorizationCredentials = Depends(security),
) -> tuple[dict[str, Any], bool, bool]:
    """Dependency to get user info along with MFA status flags.

    Returns the authenticated user's token payload along with flags
    indicating whether MFA is required and whether it's been verified.
    Useful for endpoints that need to know MFA status but don't want
    to block access.

    Args:
        credentials: HTTP Authorization credentials injected by FastAPI

    Returns:
        tuple containing:
            - dict[str, Any]: Decoded token payload
            - bool: Whether MFA is required for this user
            - bool: Whether MFA has been verified in this session

    Raises:
        HTTPException: 401 Unauthorized if token is missing, invalid, or expired

    Example:
        @app.get("/profile")
        async def get_profile(
            user_info: tuple = Depends(get_mfa_status_user)
        ):
            user, mfa_required, mfa_verified = user_info
            return {
                "user": user["sub"],
                "mfa_required": mfa_required,
                "mfa_verified": mfa_verified,
            }
    """
    payload = await verify_token(credentials)
    mfa_required = requires_mfa(payload)
    mfa_verified = is_mfa_verified(payload)
    return payload, mfa_required, mfa_verified
