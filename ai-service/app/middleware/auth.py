"""Authentication middleware for multi-source JWT verification.

This module provides middleware to verify JWT tokens from multiple sources:
- AI Service native JWT tokens
- Gibbon session-exchanged JWT tokens

Both token types are verified using the same shared secret and algorithm,
but may have different payload structures. This middleware normalizes
the user data regardless of the token source.

Features:
- Multi-source JWT verification
- Comprehensive audit logging
- IP address and user agent tracking
- Security event monitoring
"""

from __future__ import annotations

from typing import Any, Literal, Optional

import jwt
from fastapi import HTTPException, Request, status
from fastapi.security import HTTPAuthorizationCredentials, HTTPBearer

from app.auth.audit_logger import (
    audit_logger,
    get_client_ip,
    get_endpoint,
    get_user_agent,
)
from app.config import settings

# HTTPBearer security scheme for multi-source authentication
security_multi_source = HTTPBearer()
# Optional security scheme that returns None instead of raising when no token provided
security_multi_source_optional = HTTPBearer(auto_error=False)


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
    request: Optional[Request] = None,
) -> dict[str, Any]:
    """Verify and decode a JWT token from any supported source.

    This function verifies JWT tokens from both AI service and Gibbon,
    using the shared secret key. It handles different payload structures
    and normalizes the user data.

    Includes comprehensive audit logging for security monitoring.

    Args:
        credentials: HTTP Authorization credentials containing the Bearer token
        request: Optional FastAPI Request for audit logging context

    Returns:
        dict[str, Any]: Decoded and normalized token payload

    Raises:
        HTTPException: 401 Unauthorized if token is invalid or expired
    """
    token = credentials.credentials

    # Extract request context for audit logging
    ip_address = get_client_ip(request) if request else None
    user_agent = get_user_agent(request) if request else None
    endpoint = get_endpoint(request) if request else None

    try:
        # Decode the token using the shared secret
        payload = jwt.decode(
            token,
            settings.jwt_secret_key,
            algorithms=[settings.jwt_algorithm],
            audience=settings.jwt_audience,
            issuer=settings.jwt_issuer,
            options={
                "require": ["exp", "iat", "sub", "iss", "aud"],
                "verify_signature": True,
                "verify_exp": True,
                "verify_iat": True,
                "verify_aud": True,
                "verify_iss": True,
            },
        )

        # Validate required fields
        if not payload.get("sub"):
            audit_logger.log_missing_claims(
                missing_claims=["sub"],
                token_payload=payload,
                ip_address=ip_address,
                user_agent=user_agent,
                endpoint=endpoint,
            )
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
                audit_logger.log_missing_claims(
                    missing_claims=["username"],
                    token_payload=payload,
                    ip_address=ip_address,
                    user_agent=user_agent,
                    endpoint=endpoint,
                )
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

        # Log successful verification
        audit_logger.log_verification_success(
            token_payload=payload,
            ip_address=ip_address,
            user_agent=user_agent,
            endpoint=endpoint,
        )

        return payload

    except HTTPException:
        # Re-raise HTTPExceptions (validation errors) without modification
        # (already logged above)
        raise

    except jwt.ExpiredSignatureError:
        # Try to decode without verification to get payload for logging
        try:
            expired_payload = jwt.decode(
                token,
                settings.jwt_secret_key,
                algorithms=[settings.jwt_algorithm],
                options={"verify_signature": False, "verify_exp": False},
            )
            audit_logger.log_token_expired(
                token_payload=expired_payload,
                ip_address=ip_address,
                user_agent=user_agent,
                endpoint=endpoint,
            )
        except Exception:
            # If we can't decode at all, just log the failure
            audit_logger.log_verification_failed(
                error_message="Token has expired (could not decode)",
                ip_address=ip_address,
                user_agent=user_agent,
                endpoint=endpoint,
            )

        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Token has expired",
            headers={"WWW-Authenticate": "Bearer"},
        )

    except jwt.InvalidTokenError as e:
        audit_logger.log_invalid_token(
            error_message=str(e),
            ip_address=ip_address,
            user_agent=user_agent,
            endpoint=endpoint,
        )
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail=f"Invalid authentication token: {str(e)}",
            headers={"WWW-Authenticate": "Bearer"},
        )

    except Exception as e:
        # Log unexpected errors but don't expose details to client
        audit_logger.log_verification_failed(
            error_message=f"Unexpected error: {str(e)}",
            ip_address=ip_address,
            user_agent=user_agent,
            endpoint=endpoint,
        )
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Failed to verify authentication token",
            headers={"WWW-Authenticate": "Bearer"},
        )


async def get_current_user_multi_source(
    credentials: HTTPAuthorizationCredentials,
    request: Optional[Request] = None,
) -> dict[str, Any]:
    """FastAPI dependency to get current user from multi-source JWT token.

    This dependency can be used in route handlers to require authentication
    from either AI service or Gibbon JWT tokens.

    Includes audit logging for all verification attempts.

    Args:
        credentials: HTTP Authorization credentials injected by FastAPI
        request: Optional FastAPI Request for audit context

    Returns:
        dict[str, Any]: Decoded token payload containing user information

    Raises:
        HTTPException: 401 Unauthorized if token is missing, invalid, or expired

    Example:
        @app.get("/api/v1/profile")
        async def get_profile(
            current_user: dict = Depends(get_current_user_multi_source),
            request: Request = None
        ):
            return {
                "user_id": current_user["sub"],
                "source": current_user.get("source", "ai-service")
            }
    """
    return await verify_token_from_any_source(credentials, request)


async def get_optional_user_multi_source(
    credentials: HTTPAuthorizationCredentials | None,
    request: Optional[Request] = None,
) -> dict[str, Any] | None:
    """FastAPI dependency to optionally get current user from multi-source token.

    Similar to get_current_user_multi_source but returns None if no token
    is provided instead of raising an exception. Useful for endpoints that
    behave differently based on authentication status.

    Args:
        credentials: Optional HTTP Authorization credentials
        request: Optional FastAPI Request for audit context

    Returns:
        dict[str, Any] | None: Decoded token payload or None if not authenticated

    Example:
        @app.get("/api/v1/items")
        async def get_items(
            current_user: dict | None = Depends(get_optional_user_multi_source),
            request: Request = None
        ):
            if current_user:
                return {"items": get_user_items(current_user["sub"])}
            return {"items": get_public_items()}
    """
    if credentials is None:
        return None

    return await verify_token_from_any_source(credentials, request)


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
