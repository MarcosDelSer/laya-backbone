"""FastAPI dependency injection utilities for LAYA AI Service.

Provides reusable dependencies for authentication and database access.
Supports both single-source (AI service only) and multi-source (AI service + Gibbon)
authentication.
"""

from __future__ import annotations

from typing import Any

from fastapi import Depends
from fastapi.security import HTTPAuthorizationCredentials

from app.auth import security, verify_token
from app.middleware.auth import (
    get_current_user_multi_source as _get_current_user_multi_source,
    get_optional_user_multi_source as _get_optional_user_multi_source,
    security_multi_source,
)


async def get_current_user(
    credentials: HTTPAuthorizationCredentials = Depends(security),
) -> dict[str, Any]:
    """Dependency to get the current authenticated user from JWT token.

    This dependency extracts and validates the JWT token from the Authorization
    header and returns the decoded payload containing user information.

    NOTE: This only accepts AI service tokens. For cross-service authentication
    with Gibbon, use get_current_user_multi_source instead.

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

    NOTE: This only accepts AI service tokens. For cross-service authentication
    with Gibbon, use get_optional_user_multi_source instead.

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


async def get_current_user_multi_source(
    credentials: HTTPAuthorizationCredentials = Depends(security_multi_source),
) -> dict[str, Any]:
    """Dependency to get current user from multi-source JWT token.

    This dependency accepts JWT tokens from both AI service and Gibbon,
    making it suitable for endpoints that need to support cross-service
    authentication.

    Args:
        credentials: HTTP Authorization credentials injected by FastAPI

    Returns:
        dict[str, Any]: Decoded token payload containing user information

    Raises:
        HTTPException: 401 Unauthorized if token is missing, invalid, or expired

    Example:
        @app.get("/api/v1/profile")
        async def get_profile(
            current_user: dict = Depends(get_current_user_multi_source)
        ):
            return {
                "user_id": current_user["sub"],
                "source": current_user.get("source", "ai-service")
            }
    """
    return await _get_current_user_multi_source(credentials)


async def get_optional_user_multi_source(
    credentials: HTTPAuthorizationCredentials | None = Depends(security_multi_source),
) -> dict[str, Any] | None:
    """Dependency to optionally get current user from multi-source token.

    Similar to get_current_user_multi_source but returns None if no token
    is provided instead of raising an exception.

    Args:
        credentials: Optional HTTP Authorization credentials

    Returns:
        dict[str, Any] | None: Decoded token payload or None if not authenticated

    Example:
        @app.get("/api/v1/items")
        async def get_items(
            current_user: dict | None = Depends(get_optional_user_multi_source)
        ):
            if current_user:
                return {"items": get_user_items(current_user["sub"])}
            return {"items": get_public_items()}
    """
    return await _get_optional_user_multi_source(credentials)
