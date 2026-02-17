"""FastAPI dependency injection utilities for LAYA AI Service.

Provides reusable dependencies for authentication, database access, and services.
"""

from __future__ import annotations

from typing import Any

from fastapi import Depends
from fastapi.security import HTTPAuthorizationCredentials
from sqlalchemy.ext.asyncio import AsyncSession

from app.auth import security, verify_token
from app.database import get_db
from app.services.llm_service import LLMService


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


async def get_llm_service(
    db: AsyncSession = Depends(get_db),
) -> LLMService:
    """Dependency to get the LLM service instance.

    This dependency creates an LLMService instance with the provided database
    session for token tracking, caching, and usage analytics.

    Args:
        db: Async database session injected by FastAPI

    Returns:
        LLMService: Configured LLM service instance with caching, tracking,
            and fallback capabilities enabled

    Example:
        @app.post("/completions")
        async def create_completion(
            request: LLMCompletionRequest,
            service: LLMService = Depends(get_llm_service),
        ):
            return await service.complete(request)
    """
    return LLMService(db)
