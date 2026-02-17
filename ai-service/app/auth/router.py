"""Authentication router for LAYA AI Service.

Provides API endpoints for user authentication, login, and token management.
"""

from typing import Any

from fastapi import APIRouter, Depends
from sqlalchemy.ext.asyncio import AsyncSession

from app.auth.schemas import (
    LoginRequest,
    RefreshRequest,
    TokenResponse,
    LogoutRequest,
    LogoutResponse,
    PasswordResetRequest,
    PasswordResetRequestResponse,
    PasswordResetConfirm,
    PasswordResetConfirmResponse,
    RevokeTokenRequest,
    RevokeTokenResponse,
)
from app.auth.service import AuthService
from app.auth.dependencies import get_current_user, require_role
from app.auth.models import UserRole
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


@router.post(
    "/password-reset/request",
    response_model=PasswordResetRequestResponse,
    summary="Request password reset",
    description="Request a password reset token to be sent to the user's email.",
)
async def request_password_reset(
    reset_request: PasswordResetRequest,
    db: AsyncSession = Depends(get_db),
) -> PasswordResetRequestResponse:
    """Request a password reset token.

    This endpoint initiates the password reset process by generating a secure
    reset token. In production, this token would be sent to the user's email
    address along with instructions on how to reset their password.

    For security purposes, this endpoint always returns a success message
    regardless of whether the email exists in the system. This prevents
    attackers from using this endpoint to enumerate valid email addresses.

    The reset token is valid for 1 hour from the time of generation.

    Args:
        reset_request: Request containing the user's email address
        db: Async database session (injected)

    Returns:
        PasswordResetRequestResponse containing:
            - message: Confirmation message about the reset request
            - email: Masked email address (for privacy)

    Example:
        POST /api/v1/auth/password-reset/request
        {
            "email": "user@example.com"
        }

        Response:
        {
            "message": "If the email exists in our system, a password reset link has been sent",
            "email": "u***@example.com"
        }
    """
    service = AuthService(db)
    return await service.request_password_reset(reset_request)


@router.post(
    "/password-reset/confirm",
    response_model=PasswordResetConfirmResponse,
    summary="Confirm password reset",
    description="Complete the password reset process using a valid reset token.",
)
async def confirm_password_reset(
    confirm_request: PasswordResetConfirm,
    db: AsyncSession = Depends(get_db),
) -> PasswordResetConfirmResponse:
    """Confirm password reset and set new password.

    This endpoint completes the password reset process by validating the
    reset token and updating the user's password. The reset token must be
    valid, not expired, and not previously used.

    After successful password reset, the user can immediately log in with
    their new password. All existing authentication tokens remain valid
    until they expire or are explicitly invalidated through logout.

    Args:
        confirm_request: Request containing reset token and new password
        db: Async database session (injected)

    Returns:
        PasswordResetConfirmResponse containing:
            - message: Confirmation of successful password reset

    Raises:
        HTTPException: 400 Bad Request if:
            - Reset token is invalid or not found
            - Reset token has expired (>1 hour old)
            - Reset token has already been used
            - Associated user account is inactive

    Example:
        POST /api/v1/auth/password-reset/confirm
        {
            "token": "secure_reset_token_here",
            "new_password": "new_secure_password"
        }

        Response:
        {
            "message": "Password has been successfully reset"
        }
    """
    service = AuthService(db)
    return await service.confirm_password_reset(confirm_request)


@router.get(
    "/me",
    summary="Get current user info",
    description="Get information about the currently authenticated user.",
)
async def get_me(
    current_user: dict[str, Any] = Depends(get_current_user),
) -> dict[str, Any]:
    """Get current authenticated user information.

    This endpoint returns information about the currently authenticated user
    based on their JWT token. Any authenticated user can access this endpoint.

    Args:
        current_user: Current user from JWT token (injected)

    Returns:
        dict containing user information from the token

    Example:
        GET /api/v1/auth/me
        Authorization: Bearer <access_token>

        Response:
        {
            "sub": "user-uuid-here",
            "email": "user@example.com",
            "role": "teacher"
        }
    """
    return {
        "sub": current_user.get("sub"),
        "email": current_user.get("email"),
        "role": current_user.get("role"),
    }


@router.get(
    "/admin/test",
    summary="Test admin-only endpoint",
    description="Example endpoint that requires admin role.",
)
async def admin_only_test(
    current_user: dict[str, Any] = Depends(require_role(UserRole.ADMIN)),
) -> dict[str, str]:
    """Test endpoint requiring admin role.

    This is an example endpoint demonstrating role-based access control.
    Only users with the ADMIN role can access this endpoint.

    Args:
        current_user: Current user from JWT token (injected, must be admin)

    Returns:
        dict with success message

    Raises:
        HTTPException: 403 Forbidden if user is not an admin

    Example:
        GET /api/v1/auth/admin/test
        Authorization: Bearer <admin_access_token>

        Response:
        {
            "message": "Admin access granted",
            "user": "user@example.com"
        }
    """
    return {
        "message": "Admin access granted",
        "user": current_user.get("email", "unknown"),
    }


@router.get(
    "/financial/test",
    summary="Test financial staff endpoint",
    description="Example endpoint for admin or accountant roles.",
)
async def financial_staff_test(
    current_user: dict[str, Any] = Depends(
        require_role(UserRole.ADMIN, UserRole.ACCOUNTANT)
    ),
) -> dict[str, str]:
    """Test endpoint requiring admin or accountant role.

    This is an example endpoint demonstrating multi-role access control.
    Only users with ADMIN or ACCOUNTANT roles can access this endpoint.

    Args:
        current_user: Current user from JWT token (injected, must be admin or accountant)

    Returns:
        dict with success message

    Raises:
        HTTPException: 403 Forbidden if user doesn't have required role

    Example:
        GET /api/v1/auth/financial/test
        Authorization: Bearer <admin_or_accountant_access_token>

        Response:
        {
            "message": "Financial access granted",
            "user": "accountant@example.com",
            "role": "accountant"
        }
    """
    return {
        "message": "Financial access granted",
        "user": current_user.get("email", "unknown"),
        "role": current_user.get("role", "unknown"),
    }


@router.post(
    "/admin/revoke-token",
    response_model=RevokeTokenResponse,
    summary="Revoke a token (Admin only)",
    description="Revoke any user's authentication token, immediately invalidating it.",
)
async def revoke_token(
    revoke_request: RevokeTokenRequest,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(require_role(UserRole.ADMIN)),
) -> RevokeTokenResponse:
    """Revoke a user's authentication token.

    This endpoint allows administrators to forcibly revoke any user's token,
    immediately invalidating it and preventing further use. This is useful for:
    - Responding to security incidents
    - Revoking tokens for compromised accounts
    - Force-logging out specific users
    - Compliance with access revocation requirements

    The token is added to a Redis blacklist with TTL matching its expiration time.
    Once revoked, the token cannot be used for any authenticated operations.

    Only users with the ADMIN role can access this endpoint.

    Args:
        revoke_request: Request containing token to revoke and reason
        db: Async database session (injected)
        current_user: Current admin user from JWT token (injected)

    Returns:
        RevokeTokenResponse containing:
            - message: Confirmation message
            - token_id: Identifier of the revoked token (first 10 chars)
            - revoked_at: ISO 8601 timestamp when token was revoked

    Raises:
        HTTPException: 401 Unauthorized if:
            - Token to revoke is invalid or malformed
            - Token format is incorrect
        HTTPException: 403 Forbidden if:
            - User is not an admin

    Example:
        POST /api/v1/auth/admin/revoke-token
        Authorization: Bearer <admin_access_token>
        {
            "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
            "reason": "Security incident - account compromised"
        }

        Response:
        {
            "message": "Token has been revoked",
            "token_id": "eyJhbGciOi...",
            "revoked_at": "2026-02-17T16:30:00.000000+00:00"
        }

    Security:
        - Requires admin role for access
        - Reason is logged for audit trail
        - Token becomes invalid immediately
        - Automatic expiration via Redis TTL (< 5ms operation)
    """
    service = AuthService(db)
    return await service.revoke_token(revoke_request)
