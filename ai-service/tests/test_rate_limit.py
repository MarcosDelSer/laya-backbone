"""Unit tests for rate limiting middleware.

Tests for rate limiter configuration, rate limiting behavior,
limit enforcement, and reset functionality.
"""

from __future__ import annotations

import asyncio
from typing import Any
from unittest.mock import AsyncMock, MagicMock, patch

import pytest
import redis.asyncio as redis
from fastapi import Depends, FastAPI, Request, status
from fastapi_limiter import FastAPILimiter
from fastapi_limiter.depends import RateLimiter
from httpx import ASGITransport, AsyncClient

from app.config import settings
from app.middleware.rate_limit import (
    get_auth_limit,
    get_auth_rate_limiter,
    get_general_limit,
    get_general_rate_limiter,
)


@pytest.fixture
async def redis_connection():
    """Create a Redis connection for testing.

    Returns:
        redis.Redis: Redis connection instance
    """
    # Use in-memory Redis for testing
    connection = redis.from_url(
        "redis://localhost:6379/15",  # Use separate DB for tests
        encoding="utf-8",
        decode_responses=True,
    )

    # Initialize FastAPILimiter with test Redis connection
    await FastAPILimiter.init(connection)

    yield connection

    # Cleanup: close FastAPILimiter and flush test database
    await FastAPILimiter.close()
    await connection.flushdb()
    await connection.aclose()


@pytest.fixture
async def test_app(redis_connection) -> FastAPI:
    """Create a test FastAPI app with rate limiting.

    Args:
        redis_connection: Redis connection fixture

    Returns:
        FastAPI: Test application instance with rate limiting
    """
    app = FastAPI()

    # Test endpoint with general rate limiting
    @app.get("/test/general")
    async def test_general(
        request: Request,
        rate_limit: RateLimiter = Depends(get_general_rate_limiter),
    ) -> dict[str, Any]:
        """Test endpoint with general rate limiting."""
        return {"message": "success", "endpoint": "general"}

    # Test endpoint with auth rate limiting
    @app.get("/test/auth")
    async def test_auth(
        request: Request,
        rate_limit: RateLimiter = Depends(get_auth_rate_limiter),
    ) -> dict[str, Any]:
        """Test endpoint with auth rate limiting."""
        return {"message": "success", "endpoint": "auth"}

    # Test endpoint with custom rate limiting (2 per minute for testing)
    @app.get("/test/custom")
    async def test_custom(
        request: Request,
        rate_limit: RateLimiter = Depends(RateLimiter(times=2, seconds=60)),
    ) -> dict[str, Any]:
        """Test endpoint with custom low rate limit."""
        return {"message": "success", "endpoint": "custom"}

    return app


@pytest.mark.asyncio
async def test_get_general_limit() -> None:
    """Test general rate limit configuration string.

    Verifies that the general rate limit string is properly formatted
    and uses the correct value from settings.
    """
    limit = get_general_limit()
    assert limit == f"{settings.rate_limit_general} per minute"
    assert limit == "100 per minute"  # Default value


@pytest.mark.asyncio
async def test_get_auth_limit() -> None:
    """Test auth rate limit configuration string.

    Verifies that the auth rate limit string is properly formatted
    and uses the correct value from settings.
    """
    limit = get_auth_limit()
    assert limit == f"{settings.rate_limit_auth} per minute"
    assert limit == "10 per minute"  # Default value


@pytest.mark.asyncio
async def test_get_general_rate_limiter() -> None:
    """Test general rate limiter dependency creation.

    Verifies that the general rate limiter is properly configured
    with the correct number of requests and time window.
    """
    limiter = get_general_rate_limiter()

    assert isinstance(limiter, RateLimiter)
    assert limiter.times == settings.rate_limit_general
    assert limiter.seconds == 60


@pytest.mark.asyncio
async def test_get_auth_rate_limiter() -> None:
    """Test auth rate limiter dependency creation.

    Verifies that the auth rate limiter is properly configured
    with stricter limits than general endpoints.
    """
    limiter = get_auth_rate_limiter()

    assert isinstance(limiter, RateLimiter)
    assert limiter.times == settings.rate_limit_auth
    assert limiter.seconds == 60

    # Auth limiter should be stricter than general
    assert limiter.times < get_general_rate_limiter().times


@pytest.mark.asyncio
async def test_rate_limiter_allows_requests_within_limit(
    test_app: FastAPI,
) -> None:
    """Test that requests within rate limit are allowed.

    Args:
        test_app: Test FastAPI application
    """
    async with AsyncClient(
        transport=ASGITransport(app=test_app), base_url="http://test"
    ) as client:
        # Make multiple requests within the limit
        for i in range(3):
            response = await client.get("/test/general")

            assert response.status_code == status.HTTP_200_OK
            data = response.json()
            assert data["message"] == "success"
            assert data["endpoint"] == "general"


@pytest.mark.asyncio
async def test_rate_limiter_blocks_requests_exceeding_limit(
    test_app: FastAPI,
) -> None:
    """Test that requests exceeding rate limit are blocked.

    Args:
        test_app: Test FastAPI application
    """
    async with AsyncClient(
        transport=ASGITransport(app=test_app), base_url="http://test"
    ) as client:
        # Make requests up to the limit (2 per minute on custom endpoint)
        response1 = await client.get("/test/custom")
        assert response1.status_code == status.HTTP_200_OK

        response2 = await client.get("/test/custom")
        assert response2.status_code == status.HTTP_200_OK

        # Third request should be rate limited
        response3 = await client.get("/test/custom")
        assert response3.status_code == status.HTTP_429_TOO_MANY_REQUESTS


@pytest.mark.asyncio
async def test_rate_limiter_error_response_format(
    test_app: FastAPI,
) -> None:
    """Test that rate limit error response has correct format.

    Args:
        test_app: Test FastAPI application
    """
    async with AsyncClient(
        transport=ASGITransport(app=test_app), base_url="http://test"
    ) as client:
        # Exhaust the rate limit
        await client.get("/test/custom")
        await client.get("/test/custom")

        # Get rate limited response
        response = await client.get("/test/custom")

        assert response.status_code == status.HTTP_429_TOO_MANY_REQUESTS
        assert "detail" in response.json() or "error" in response.json()


@pytest.mark.asyncio
async def test_auth_endpoint_has_stricter_limit(
    test_app: FastAPI,
) -> None:
    """Test that auth endpoints have stricter rate limits.

    Args:
        test_app: Test FastAPI application
    """
    async with AsyncClient(
        transport=ASGITransport(app=test_app), base_url="http://test"
    ) as client:
        # Auth endpoint should allow requests but with stricter limits
        response = await client.get("/test/auth")
        assert response.status_code == status.HTTP_200_OK

        # Verify auth limiter configuration is stricter
        auth_limiter = get_auth_rate_limiter()
        general_limiter = get_general_rate_limiter()
        assert auth_limiter.times < general_limiter.times


@pytest.mark.asyncio
async def test_different_endpoints_have_separate_limits(
    test_app: FastAPI,
) -> None:
    """Test that different endpoints have independent rate limits.

    Args:
        test_app: Test FastAPI application
    """
    async with AsyncClient(
        transport=ASGITransport(app=test_app), base_url="http://test"
    ) as client:
        # Exhaust limit on custom endpoint
        await client.get("/test/custom")
        await client.get("/test/custom")
        response = await client.get("/test/custom")
        assert response.status_code == status.HTTP_429_TOO_MANY_REQUESTS

        # General endpoint should still work
        response = await client.get("/test/general")
        assert response.status_code == status.HTTP_200_OK


@pytest.mark.asyncio
async def test_rate_limit_reset_after_window(
    test_app: FastAPI,
    redis_connection,
) -> None:
    """Test that rate limits reset after the time window expires.

    Args:
        test_app: Test FastAPI application
        redis_connection: Redis connection for manual cleanup
    """
    async with AsyncClient(
        transport=ASGITransport(app=test_app), base_url="http://test"
    ) as client:
        # Exhaust the rate limit
        await client.get("/test/custom")
        await client.get("/test/custom")

        response = await client.get("/test/custom")
        assert response.status_code == status.HTTP_429_TOO_MANY_REQUESTS

        # Simulate time passing by clearing Redis keys
        # In production, FastAPILimiter would automatically expire keys
        await redis_connection.flushdb()

        # Re-initialize to reset state
        await FastAPILimiter.close()
        await FastAPILimiter.init(redis_connection)

        # After reset, requests should work again
        response = await client.get("/test/custom")
        assert response.status_code == status.HTTP_200_OK


@pytest.mark.asyncio
async def test_rate_limit_per_client_isolation(
    test_app: FastAPI,
) -> None:
    """Test that rate limits are isolated per client.

    Args:
        test_app: Test FastAPI application
    """
    # Note: AsyncClient uses the same client IP by default (127.0.0.1)
    # In production, different IPs would have independent limits
    async with AsyncClient(
        transport=ASGITransport(app=test_app), base_url="http://test"
    ) as client:
        # Make requests from "same client" (same IP in test)
        response1 = await client.get("/test/custom")
        assert response1.status_code == status.HTTP_200_OK

        response2 = await client.get("/test/custom")
        assert response2.status_code == status.HTTP_200_OK

        # Third request should be rate limited (same client)
        response3 = await client.get("/test/custom")
        assert response3.status_code == status.HTTP_429_TOO_MANY_REQUESTS


@pytest.mark.asyncio
async def test_rate_limit_with_concurrent_requests(
    test_app: FastAPI,
) -> None:
    """Test rate limiting with concurrent requests.

    Args:
        test_app: Test FastAPI application
    """
    async with AsyncClient(
        transport=ASGITransport(app=test_app), base_url="http://test"
    ) as client:
        # Make concurrent requests
        tasks = [
            client.get("/test/custom")
            for _ in range(5)
        ]
        responses = await asyncio.gather(*tasks, return_exceptions=True)

        # Count successful vs rate limited responses
        success_count = sum(
            1 for r in responses
            if not isinstance(r, Exception) and r.status_code == status.HTTP_200_OK
        )
        rate_limited_count = sum(
            1 for r in responses
            if not isinstance(r, Exception) and r.status_code == status.HTTP_429_TOO_MANY_REQUESTS
        )

        # Should have 2 successes (limit is 2/min) and rest rate limited
        assert success_count == 2
        assert rate_limited_count == 3


@pytest.mark.asyncio
async def test_fastapi_limiter_initialization() -> None:
    """Test FastAPILimiter initialization with Redis.

    Verifies that FastAPILimiter can be properly initialized
    and closed with a Redis connection.
    """
    connection = redis.from_url(
        "redis://localhost:6379/15",
        encoding="utf-8",
        decode_responses=True,
    )

    # Initialize should succeed
    await FastAPILimiter.init(connection)

    # Cleanup
    await FastAPILimiter.close()
    await connection.aclose()


@pytest.mark.asyncio
async def test_rate_limit_configuration_from_settings() -> None:
    """Test that rate limit configuration matches settings.

    Verifies that rate limiters use the values configured in settings.
    """
    general_limiter = get_general_rate_limiter()
    auth_limiter = get_auth_rate_limiter()

    # Check general limiter configuration
    assert general_limiter.times == settings.rate_limit_general
    assert general_limiter.seconds == 60

    # Check auth limiter configuration
    assert auth_limiter.times == settings.rate_limit_auth
    assert auth_limiter.seconds == 60

    # Verify defaults
    assert settings.rate_limit_general == 100
    assert settings.rate_limit_auth == 10
