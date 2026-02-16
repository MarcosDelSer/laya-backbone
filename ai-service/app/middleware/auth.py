"""Authentication middleware for multi-source JWT verification.

This module provides middleware to verify JWT tokens from multiple sources:
- AI Service native JWT tokens
- Gibbon session-exchanged JWT tokens

Both token types are verified using the same shared secret and algorithm,
but may have different payload structures. This middleware normalizes
the user data regardless of the token source.
"""

from __future__ import annotations

from typing import Any, Literal, Optional

import jwt
from fastapi import HTTPException, status
from fastapi.security import HTTPAuthorizationCredentials, HTTPBearer

from app.config import settings

# HTTPBearer security scheme for multi-source authentication
security_multi_source = HTTPBearer()


class TokenSource:
    """Token source identifiers."""

    AI_SERVICE = "ai-service"
    GIBBON = "gibbon"


class MultiSourceTokenPayload:
    """Represents a decoded JWT token from any source.

    This class normalizes token payloads from different sources
    (AI service or Gibbon) into a consistent structure.

    Attributes:
        sub: Subject (user identifier)
        exp: Expiration timestamp
        iat: Issued at timestamp
        source: Token source (ai-service or gibbon)
        username: User's username
        email: User's email address
        role: User's role in the system
        name: User's full name
        raw_payload: Original decoded payload
    """

    def __init__(self, payload: dict[str, Any]) -> None:
        """Initialize token payload from decoded JWT.

        Args:
            payload: Decoded JWT payload dictionary
        """
        self.sub: Optional[str] = payload.get("sub")
        self.exp: Optional[int] = payload.get("exp")
        self.iat: Optional[int] = payload.get("iat")
        self.source: str = payload.get("source", TokenSource.AI_SERVICE)
        self.username: Optional[str] = payload.get("username")
        self.email: Optional[str] = payload.get("email")
        self.role: Optional[str] = payload.get("role")
        self.name: Optional[str] = payload.get("name")
        self.raw_payload: dict[str, Any] = payload

        # Store Gibbon-specific fields if present
        if self.source == TokenSource.GIBBON:
            self.gibbon_role_id: Optional[str] = payload.get("gibbon_role_id")
            self.session_id: Optional[str] = payload.get("session_id")

    def to_dict(self) -> dict[str, Any]:
        """Convert to dictionary representation.

        Returns:
            dict[str, Any]: Dictionary containing all token data
        """
        return self.raw_payload


async def verify_token_from_any_source(
    credentials: HTTPAuthorizationCredentials,
) -> dict[str, Any]:
    """Verify and decode a JWT token from any supported source.

    This function verifies JWT tokens from both AI service and Gibbon,
    using the shared secret key. It handles different payload structures
    and normalizes the user data.

    Args:
        credentials: HTTP Authorization credentials containing the Bearer token

    Returns:
        dict[str, Any]: Decoded and normalized token payload

    Raises:
        HTTPException: 401 Unauthorized if token is invalid or expired
    """
    token = credentials.credentials

    try:
        # Decode the token using the shared secret
        payload = jwt.decode(
            token,
            settings.jwt_secret_key,
            algorithms=[settings.jwt_algorithm],
        )

        # Validate required fields
        if not payload.get("sub"):
            raise HTTPException(
                status_code=status.HTTP_401_UNAUTHORIZED,
                detail="Token missing required 'sub' claim",
                headers={"WWW-Authenticate": "Bearer"},
            )

        # Check token source and validate accordingly
        source = payload.get("source", TokenSource.AI_SERVICE)

        if source == TokenSource.GIBBON:
            # Validate Gibbon-specific fields
            if not payload.get("username"):
                raise HTTPException(
                    status_code=status.HTTP_401_UNAUTHORIZED,
                    detail="Gibbon token missing required 'username' claim",
                    headers={"WWW-Authenticate": "Bearer"},
                )
        elif source == TokenSource.AI_SERVICE:
            # AI service tokens are already validated by the existing structure
            pass
        else:
            # Unknown source - this is suspicious but we'll allow it
            # and treat it as an AI service token
            pass

        return payload

    except jwt.ExpiredSignatureError:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Token has expired",
            headers={"WWW-Authenticate": "Bearer"},
        )

    except jwt.InvalidTokenError as e:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail=f"Invalid authentication token: {str(e)}",
            headers={"WWW-Authenticate": "Bearer"},
        )

    except Exception as e:
        # Log unexpected errors but don't expose details to client
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Failed to verify authentication token",
            headers={"WWW-Authenticate": "Bearer"},
        )


async def get_current_user_multi_source(
    credentials: HTTPAuthorizationCredentials = HTTPBearer(),
) -> dict[str, Any]:
    """FastAPI dependency to get current user from multi-source JWT token.

    This dependency can be used in route handlers to require authentication
    from either AI service or Gibbon JWT tokens.

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
    return await verify_token_from_any_source(credentials)


async def get_optional_user_multi_source(
    credentials: HTTPAuthorizationCredentials | None = HTTPBearer(auto_error=False),
) -> dict[str, Any] | None:
    """FastAPI dependency to optionally get current user from multi-source token.

    Similar to get_current_user_multi_source but returns None if no token
    is provided instead of raising an exception. Useful for endpoints that
    behave differently based on authentication status.

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
    if credentials is None:
        return None

    return await verify_token_from_any_source(credentials)


def extract_user_info(payload: dict[str, Any]) -> dict[str, Any]:
    """Extract normalized user information from a token payload.

    This function provides a consistent user info structure regardless
    of whether the token came from AI service or Gibbon.

    Args:
        payload: Decoded JWT payload

    Returns:
        dict[str, Any]: Normalized user information

    Example:
        >>> payload = await verify_token_from_any_source(credentials)
        >>> user_info = extract_user_info(payload)
        >>> print(user_info["user_id"], user_info["email"])
    """
    source = payload.get("source", TokenSource.AI_SERVICE)

    user_info = {
        "user_id": payload.get("sub"),
        "username": payload.get("username"),
        "email": payload.get("email"),
        "role": payload.get("role"),
        "name": payload.get("name"),
        "source": source,
    }

    # Add Gibbon-specific fields if present
    if source == TokenSource.GIBBON:
        user_info["gibbon_role_id"] = payload.get("gibbon_role_id")
        user_info["session_id"] = payload.get("session_id")

    return user_info
