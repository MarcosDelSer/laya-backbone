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
