"""Authentication router for LAYA AI Service.

Provides API endpoints for user authentication, login, and token management.
"""

from typing import Any

from fastapi import APIRouter, Depends
from sqlalchemy.ext.asyncio import AsyncSession

from app.auth.schemas import LoginRequest, TokenResponse
from app.auth.service import AuthService
from app.database import get_db

router = APIRouter(prefix="/api/v1/auth", tags=["authentication"])


@router.post(
    "/login",
    response_model=TokenResponse,
    summary="User login",
    description="Authenticate user with email and password, returning JWT tokens.",
)
async def login(
    login_request: LoginRequest,
    db: AsyncSession = Depends(get_db),
) -> TokenResponse:
    """Authenticate user and return JWT tokens.

    This endpoint authenticates a user with their email and password credentials.
    On successful authentication, it returns both an access token (valid for 15 minutes)
    and a refresh token (valid for 7 days).

    The access token should be included in the Authorization header as a Bearer token
    for subsequent API requests. When the access token expires, use the refresh token
    to obtain a new access token via the /auth/refresh endpoint.

    Args:
        login_request: User credentials (email and password)
        db: Async database session (injected)

    Returns:
        TokenResponse containing:
            - access_token: JWT access token for API authentication (15min expiry)
            - refresh_token: JWT refresh token for renewing access (7day expiry)
            - expires_in: Time in seconds until access token expires
            - token_type: Token type ("bearer")

    Raises:
        HTTPException: 401 Unauthorized if:
            - Email not found
            - Password incorrect
            - User account is inactive

    Example:
        POST /api/v1/auth/login
        {
            "email": "teacher@example.com",
            "password": "secure_password"
        }

        Response:
        {
            "access_token": "eyJhbGc...",
            "refresh_token": "eyJhbGc...",
            "expires_in": 900,
            "token_type": "bearer"
        }
    """
    service = AuthService(db)
    return await service.login(login_request)
