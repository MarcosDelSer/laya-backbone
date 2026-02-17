"""Rate limiting middleware for LAYA AI Service.

This module provides rate limiting using slowapi to prevent abuse and ensure
fair resource usage across the API. Different rate limits apply to general
endpoints vs authentication-sensitive endpoints.

Rate limits are configurable via environment variables:
- RATE_LIMIT_GENERAL: requests per minute for general endpoints (default: 100)
- RATE_LIMIT_AUTH: requests per minute for auth endpoints (default: 10)
- RATE_LIMIT_STORAGE_URI: storage backend (default: memory://, production: redis://...)
"""

from slowapi import Limiter
from slowapi.util import get_remote_address

from app.config import settings


def get_rate_limit_key(request) -> str:
    """Get the key for rate limiting based on request path and client.

    For auth endpoints, use a more restrictive limit. For general endpoints,
    use a more permissive limit. The key combines the client IP with the
    endpoint type to apply different limits.

    Args:
        request: The incoming request object

    Returns:
        str: Unique key for rate limiting this request
    """
    # Get client IP address
    client_ip = get_remote_address(request)

    # Identify auth-sensitive endpoints
    auth_paths = [
        "/protected",
        "/api/v1/auth",
        "/api/v1/token",
        "/api/v1/login",
        "/api/v1/register",
    ]

    # Check if this is an auth endpoint
    path = request.url.path
    is_auth = any(path.startswith(auth_path) for auth_path in auth_paths)

    # Return different keys for auth vs general to apply different limits
    return f"auth:{client_ip}" if is_auth else f"general:{client_ip}"


# Create limiter instance with configurable rate limits and storage
# Storage URI from settings: memory:// for dev, redis:// for production
# Default limits from settings: configurable per environment
limiter = Limiter(
    key_func=get_remote_address,
    default_limits=[f"{settings.rate_limit_general} per minute"],
    storage_uri=settings.rate_limit_storage_uri,
)


def get_auth_limit() -> str:
    """Get the rate limit string for authentication endpoints.

    Returns:
        str: Rate limit specification from settings (default: 10 requests per minute)
    """
    return f"{settings.rate_limit_auth} per minute"


def get_general_limit() -> str:
    """Get the rate limit string for general endpoints.

    Returns:
        str: Rate limit specification from settings (default: 100 requests per minute)
    """
    return f"{settings.rate_limit_general} per minute"
