"""JWT token utilities for LAYA AI Service authentication.

Provides JWT token creation, validation, and payload management.
"""

from datetime import datetime, timezone
from typing import Any, Optional

import jwt
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
