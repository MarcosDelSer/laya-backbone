"""Rate limiting middleware for LAYA AI Service.

This module provides rate limiting using fastapi-limiter to prevent abuse and ensure
fair resource usage across the API. Different rate limits apply to general
endpoints vs authentication-sensitive endpoints.

Rate limits are configurable via environment variables:
- RATE_LIMIT_GENERAL: requests per minute for general endpoints (default: 100)
- RATE_LIMIT_AUTH: requests per minute for auth endpoints (default: 10)
- RATE_LIMIT_STORAGE_URI: Redis backend for rate limiting (default: memory://)

FastAPILimiter is initialized in main.py lifespan context manager.
"""

from fastapi_limiter.depends import RateLimiter

from app.config import settings


def get_auth_rate_limiter() -> RateLimiter:
    """Get rate limiter dependency for authentication endpoints.

    Returns:
        RateLimiter: Configured rate limiter for auth endpoints
            (default: 10 requests per 60 seconds)
    """
    return RateLimiter(times=settings.rate_limit_auth, seconds=60)


def get_general_rate_limiter() -> RateLimiter:
    """Get rate limiter dependency for general endpoints.

    Returns:
        RateLimiter: Configured rate limiter for general endpoints
            (default: 100 requests per 60 seconds)
    """
    return RateLimiter(times=settings.rate_limit_general, seconds=60)


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
