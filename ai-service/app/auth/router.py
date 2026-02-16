"""Authentication router for LAYA AI Service.

Provides API endpoints for user authentication, login, and token management.
"""

from typing import Any

from fastapi import APIRouter, Depends
from sqlalchemy.ext.asyncio import AsyncSession

from app.auth.schemas import LoginRequest, RefreshRequest, TokenResponse, LogoutRequest, LogoutResponse
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


@router.post(
    "/refresh",
    response_model=TokenResponse,
    summary="Refresh access token",
    description="Obtain new access and refresh tokens using a valid refresh token.",
)
async def refresh(
    refresh_request: RefreshRequest,
    db: AsyncSession = Depends(get_db),
) -> TokenResponse:
    """Refresh authentication tokens.

    This endpoint allows clients to obtain new access and refresh tokens using
    a valid refresh token. This is useful when the access token has expired but
    the refresh token is still valid, avoiding the need for the user to log in again.

    Both new access and refresh tokens are issued to implement refresh token rotation,
    which enhances security by reducing the window of opportunity for token theft.

    Args:
        refresh_request: Request containing the refresh token
        db: Async database session (injected)

    Returns:
        TokenResponse containing:
            - access_token: New JWT access token (15min expiry)
            - refresh_token: New JWT refresh token (7day expiry)
            - expires_in: Time in seconds until access token expires
            - token_type: Token type ("bearer")

    Raises:
        HTTPException: 401 Unauthorized if:
            - Refresh token is invalid or expired
            - Refresh token is not of type 'refresh'
            - User associated with token not found
            - User account is inactive

    Example:
        POST /api/v1/auth/refresh
        {
            "refresh_token": "eyJhbGc..."
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
    return await service.refresh_tokens(refresh_request)


@router.post(
    "/logout",
    response_model=LogoutResponse,
    summary="User logout",
    description="Invalidate authentication tokens to log out the user.",
)
async def logout(
    logout_request: LogoutRequest,
    db: AsyncSession = Depends(get_db),
) -> LogoutResponse:
    """Logout user by invalidating their tokens.

    This endpoint invalidates the provided authentication tokens, preventing
    their further use. This effectively logs the user out by adding the tokens
    to a blacklist that is checked during authentication.

    Both the access token and optional refresh token are invalidated. If only
    the access token is provided, it will be blacklisted and the refresh token
    (if it exists) will remain valid until it's used or expires.

    Args:
        logout_request: Request containing tokens to invalidate
        db: Async database session (injected)

    Returns:
        LogoutResponse containing:
            - message: Success confirmation message
            - tokens_invalidated: Number of tokens that were invalidated

    Raises:
        HTTPException: 401 Unauthorized if:
            - Access token is invalid or expired
            - Access token is not of type 'access'
            - Token format is malformed

    Example:
        POST /api/v1/auth/logout
        {
            "access_token": "eyJhbGc...",
            "refresh_token": "eyJhbGc..."
        }

        Response:
        {
            "message": "Successfully logged out",
            "tokens_invalidated": 2
        }
    """
    service = AuthService(db)
    return await service.logout(logout_request)
