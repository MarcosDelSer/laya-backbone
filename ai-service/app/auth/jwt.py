"""JWT token utilities for LAYA AI Service authentication.

Provides JWT token creation, validation, and payload management.
"""

from datetime import datetime, timezone
from typing import Any, Optional

import jwt
from jwt.exceptions import InvalidTokenError
from fastapi import HTTPException, status

from app.config import settings


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
