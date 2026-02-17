"""JWT authentication utilities for LAYA AI Service.

Provides token verification and user extraction from JWT tokens.
"""

from __future__ import annotations

from datetime import datetime, timezone
from typing import Any, Optional

import jwt
from fastapi import Depends, HTTPException, status
from fastapi.security import HTTPAuthorizationCredentials, HTTPBearer
from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession

from app.config import settings
from app.database import get_db

# HTTPBearer security scheme for Swagger UI integration
security = HTTPBearer()

# MFA-related constants
MFA_VERIFIED_CLAIM = "mfa_verified"
MFA_REQUIRED_CLAIM = "mfa_required"


class MFARequiredError(Exception):
    """Raised when MFA verification is required but not completed."""

    pass


class TokenPayload:
    """Represents the decoded JWT token payload.

    Attributes:
        sub: Subject (user identifier)
        exp: Expiration timestamp
        iat: Issued at timestamp
        data: Additional token data
    """

    def __init__(self, payload: dict[str, Any]) -> None:
        """Initialize token payload from decoded JWT.

        Args:
            payload: Decoded JWT payload dictionary
        """
        self.sub: Optional[str] = payload.get("sub")
        self.exp: Optional[int] = payload.get("exp")
        self.iat: Optional[int] = payload.get("iat")
        self.data: dict[str, Any] = payload


async def verify_token(
    credentials: HTTPAuthorizationCredentials,
    db: AsyncSession = Depends(get_db),
) -> dict[str, Any]:
    """Verify and decode a JWT token.

    Args:
        credentials: HTTP Authorization credentials containing the Bearer token
        db: Database session for checking token blacklist

    Returns:
        dict[str, Any]: Decoded token payload

    Raises:
        HTTPException: 401 Unauthorized if token is invalid, expired, or revoked
    """
    token = credentials.credentials

    try:
        # First, decode the token to validate its signature and structure
        payload = jwt.decode(
            token,
            settings.jwt_secret_key,
            algorithms=[settings.jwt_algorithm],
        )

        # Check if token is blacklisted (after logout)
        # Import locally to avoid circular dependency
        from app.auth.models import TokenBlacklist

        stmt = select(TokenBlacklist).where(TokenBlacklist.token == token)
        result = await db.execute(stmt)
        if result.scalar_one_or_none() is not None:
            raise HTTPException(
                status_code=status.HTTP_401_UNAUTHORIZED,
                detail="Token has been revoked",
                headers={"WWW-Authenticate": "Bearer"},
            )

        return payload

    except jwt.ExpiredSignatureError:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Token has expired",
            headers={"WWW-Authenticate": "Bearer"},
        )

    except jwt.InvalidTokenError:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Invalid authentication token",
            headers={"WWW-Authenticate": "Bearer"},
        )


async def verify_mfa_token(
    credentials: HTTPAuthorizationCredentials,
) -> dict[str, Any]:
    """Verify JWT token and ensure MFA verification is completed.

    This function first verifies the token is valid, then checks if MFA
    verification is required and completed. Used for endpoints that require
    full MFA-verified authentication.

    Args:
        credentials: HTTP Authorization credentials containing the Bearer token

    Returns:
        dict[str, Any]: Decoded token payload with MFA verification confirmed

    Raises:
        HTTPException: 401 Unauthorized if token is invalid or expired
        HTTPException: 403 Forbidden if MFA is required but not verified
    """
    # First verify the base token
    payload = await verify_token(credentials)

    # Check if MFA is required for this user
    mfa_required = payload.get(MFA_REQUIRED_CLAIM, False)
    mfa_verified = payload.get(MFA_VERIFIED_CLAIM, False)

    if mfa_required and not mfa_verified:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="MFA verification required. Please complete MFA authentication.",
            headers={"WWW-Authenticate": "Bearer realm='mfa'"},
        )

    return payload


def is_mfa_verified(payload: dict[str, Any]) -> bool:
    """Check if the token payload indicates MFA has been verified.

    Args:
        payload: Decoded JWT token payload

    Returns:
        bool: True if MFA is verified or not required, False otherwise
    """
    mfa_required = payload.get(MFA_REQUIRED_CLAIM, False)
    mfa_verified = payload.get(MFA_VERIFIED_CLAIM, False)

    # If MFA is not required, consider it verified
    if not mfa_required:
        return True

    return mfa_verified


def requires_mfa(payload: dict[str, Any]) -> bool:
    """Check if the token payload indicates MFA is required.

    Args:
        payload: Decoded JWT token payload

    Returns:
        bool: True if MFA is required for this user, False otherwise
    """
    return payload.get(MFA_REQUIRED_CLAIM, False)


def create_token(
    subject: str,
    expires_delta_seconds: int = 3600,
    additional_claims: Optional[dict[str, Any]] = None,
) -> str:
    """Create a JWT token for testing purposes.

    Args:
        subject: Token subject (user identifier)
        expires_delta_seconds: Token expiration time in seconds
        additional_claims: Additional claims to include in the token

    Returns:
        str: Encoded JWT token
    """
    now = datetime.now(timezone.utc)
    expire = datetime.fromtimestamp(
        now.timestamp() + expires_delta_seconds, tz=timezone.utc
    )

    payload = {
        "sub": subject,
        "iat": int(now.timestamp()),
        "exp": int(expire.timestamp()),
    }

    if additional_claims:
        payload.update(additional_claims)

    return jwt.encode(
        payload,
        settings.jwt_secret_key,
        algorithm=settings.jwt_algorithm,
    )
