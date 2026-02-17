"""Rate limiting middleware for LAYA AI Service.

This module provides rate limiting using slowapi to prevent abuse and ensure
fair resource usage across the API. Different rate limits apply to general
endpoints vs authentication-sensitive endpoints.

Rate Limits:
- General endpoints: 100 requests per minute
- Auth endpoints: 10 requests per minute
"""

from slowapi import Limiter
from slowapi.util import get_remote_address


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


# Create limiter instance with default rate limit
# The actual limits are applied per-endpoint in the route definitions
limiter = Limiter(
    key_func=get_remote_address,
    default_limits=["100 per minute"],  # General default
    storage_uri="memory://",  # In-memory storage for development
)


def get_auth_limit() -> str:
    """Get the rate limit string for authentication endpoints.

    Returns:
        str: Rate limit specification (10 requests per minute)
    """
    return "10 per minute"


def get_general_limit() -> str:
    """Get the rate limit string for general endpoints.

    Returns:
        str: Rate limit specification (100 requests per minute)
    """
    return "100 per minute"
