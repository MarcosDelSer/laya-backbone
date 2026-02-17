"""CSRF (Cross-Site Request Forgery) protection for LAYA AI Service.

This module provides CSRF token generation and validation to protect against
CSRF attacks on state-changing operations (POST, PUT, DELETE, PATCH).

CSRF tokens are signed using the application's JWT secret key to ensure
authenticity without requiring server-side state storage.
"""

import secrets
from datetime import datetime, timedelta, timezone
from typing import Callable, List, Optional

from fastapi import HTTPException, Request, Response, status
from jose import JWTError, jwt
from starlette.middleware.base import BaseHTTPMiddleware

from app.config import settings


def generate_csrf_token(duration_minutes: Optional[int] = None) -> str:
    """Generate a cryptographically secure CSRF token.

    The token is a JWT containing:
    - Random nonce for uniqueness
    - Expiration timestamp
    - Token type identifier

    Args:
        duration_minutes: Token validity duration in minutes
                         (default: from settings.csrf_token_expire_minutes)

    Returns:
        str: Signed CSRF token (JWT)
    """
    # Use configured expiration time if not specified
    if duration_minutes is None:
        duration_minutes = settings.csrf_token_expire_minutes

    # Generate a random nonce for uniqueness
    nonce = secrets.token_urlsafe(32)

    # Calculate expiration time
    expires_at = datetime.now(timezone.utc) + timedelta(minutes=duration_minutes)

    # Create token payload
    payload = {
        "nonce": nonce,
        "exp": expires_at.timestamp(),
        "type": "csrf",
    }

    # Sign token using JWT secret
    token = jwt.encode(payload, settings.jwt_secret_key, algorithm=settings.jwt_algorithm)

    return token


def validate_csrf_token(token: str) -> bool:
    """Validate a CSRF token.

    Verifies:
    - Token signature is valid
    - Token has not expired
    - Token type is 'csrf'

    Args:
        token: The CSRF token to validate

    Returns:
        bool: True if token is valid, False otherwise
    """
    try:
        # Decode and verify token
        payload = jwt.decode(
            token,
            settings.jwt_secret_key,
            algorithms=[settings.jwt_algorithm],
        )

        # Verify token type
        if payload.get("type") != "csrf":
            return False

        # JWT library automatically checks expiration
        return True

    except JWTError:
        # Token is invalid (expired, malformed, wrong signature, etc.)
        return False


def get_csrf_exempt_paths() -> List[str]:
    """Get list of paths exempt from CSRF protection.

    These paths are exempt because they:
    - Are read-only (GET, HEAD, OPTIONS)
    - Are used by external webhooks (can't provide CSRF tokens)
    - Are health checks or monitoring endpoints

    Returns:
        List[str]: List of path prefixes exempt from CSRF protection
    """
    return [
        "/",  # Health check
        "/docs",  # API documentation
        "/openapi.json",  # OpenAPI spec
        "/api/v1/webhook",  # Webhook endpoints (external calls)
    ]


class CSRFProtectionMiddleware(BaseHTTPMiddleware):
    """CSRF protection middleware for FastAPI application.

    This middleware validates CSRF tokens on all state-changing requests
    (POST, PUT, DELETE, PATCH) except for exempt paths.

    CSRF tokens must be provided in the X-CSRF-Token header.
    """

    async def dispatch(self, request: Request, call_next: Callable) -> Response:
        """Process request and validate CSRF token if needed.

        Args:
            request: The incoming request
            call_next: The next middleware/handler in the chain

        Returns:
            Response: The response from the handler

        Raises:
            HTTPException: 403 if CSRF token is missing or invalid
        """
        # Get HTTP method
        method = request.method.upper()

        # Only check CSRF for state-changing methods
        state_changing_methods = ["POST", "PUT", "DELETE", "PATCH"]

        if method in state_changing_methods:
            # Check if path is exempt
            path = request.url.path
            exempt_paths = get_csrf_exempt_paths()

            is_exempt = any(path.startswith(exempt_path) for exempt_path in exempt_paths)

            if not is_exempt:
                # Get CSRF token from header
                csrf_token = request.headers.get("X-CSRF-Token")

                if not csrf_token:
                    raise HTTPException(
                        status_code=status.HTTP_403_FORBIDDEN,
                        detail="CSRF token missing. Include X-CSRF-Token header.",
                    )

                # Validate token
                if not validate_csrf_token(csrf_token):
                    raise HTTPException(
                        status_code=status.HTTP_403_FORBIDDEN,
                        detail="CSRF token invalid or expired.",
                    )

        # Process the request
        response = await call_next(request)

        return response


def get_csrf_protection_middleware() -> Callable:
    """Get CSRF protection middleware for the FastAPI application.

    This function is kept for backward compatibility but returns the class.

    Returns:
        type: CSRFProtectionMiddleware class
    """
    return CSRFProtectionMiddleware


def get_csrf_token_from_request(request: Request) -> Optional[str]:
    """Extract CSRF token from request headers.

    Args:
        request: The incoming request

    Returns:
        Optional[str]: CSRF token if present, None otherwise
    """
    return request.headers.get("X-CSRF-Token")
