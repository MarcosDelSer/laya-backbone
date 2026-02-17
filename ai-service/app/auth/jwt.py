"""JWT token utilities for LAYA AI Service authentication.

Provides JWT token creation, validation, and payload management.
"""

from datetime import datetime, timezone
from typing import Any, Optional

import jwt
from jwt.exceptions import InvalidTokenError
from fastapi import HTTPException, status
from fastapi.security import HTTPAuthorizationCredentials, HTTPBearer
from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession

from app.config import get_settings


# Get cached settings instance
settings = get_settings()

# HTTPBearer security scheme for token extraction
security = HTTPBearer()


def create_token(
    subject: str,
    expires_delta_seconds: int = 3600,
    additional_claims: Optional[dict[str, Any]] = None,
) -> str:
    """Create a JWT token.

    Args:
        subject: Token subject (user identifier)
        expires_delta_seconds: Token expiration time in seconds
        additional_claims: Additional claims to include in the token

    Returns:
        str: Encoded JWT token

    Example:
        >>> token = create_token(
        ...     subject="user123",
        ...     expires_delta_seconds=900,
        ...     additional_claims={"role": "admin"}
        ... )
        >>> isinstance(token, str)
        True
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


def decode_token(token: str) -> dict[str, Any]:
    """Decode and validate a JWT token.

    Args:
        token: JWT token to decode and validate

    Returns:
        dict: Token payload containing claims

    Raises:
        HTTPException: 401 Unauthorized if token is invalid or expired

    Example:
        >>> token = create_token("user123", 3600)
        >>> payload = decode_token(token)
        >>> payload["sub"]
        'user123'
    """
    try:
        payload = jwt.decode(
            token,
            settings.jwt_secret_key,
            algorithms=[settings.jwt_algorithm],
        )
        return payload
    except InvalidTokenError as e:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail=f"Invalid token: {str(e)}",
            headers={"WWW-Authenticate": "Bearer"},
        )


async def verify_token(
    credentials: HTTPAuthorizationCredentials,
    db: AsyncSession,
) -> dict[str, Any]:
    """Verify and decode a JWT token, checking against the blacklist.

    This function extracts the token from HTTP credentials, decodes it,
    and verifies it has not been revoked (blacklisted). Use this for
    authenticated endpoints to ensure tokens are valid and not revoked.

    Args:
        credentials: HTTP Authorization credentials containing the Bearer token
        db: Async database session for blacklist lookup

    Returns:
        dict[str, Any]: Decoded token payload containing claims

    Raises:
        HTTPException: 401 Unauthorized if token is invalid, expired, or revoked

    Example:
        @app.get("/protected")
        async def protected_route(
            credentials: HTTPAuthorizationCredentials = Depends(security),
            db: AsyncSession = Depends(get_db),
        ):
            payload = await verify_token(credentials, db)
            return {"user_id": payload["sub"]}
    """
    # Import here to avoid circular dependency
    from app.auth.models import TokenBlacklist

    token = credentials.credentials

    # Decode and validate the token (handles expiration, signature, etc.)
    payload = decode_token(token)

    # Check if token is blacklisted
    stmt = select(TokenBlacklist).where(TokenBlacklist.token == token)
    result = await db.execute(stmt)
    if result.scalar_one_or_none() is not None:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Token has been revoked",
            headers={"WWW-Authenticate": "Bearer"},
        )

    return payload
