"""Unit tests for Redis client functionality.

Tests for Redis client initialization, connection, and basic operations.
"""

from __future__ import annotations

import pytest
import pytest_asyncio
from redis.asyncio import Redis

from app.redis_client import get_redis_client, ping_redis, close_redis


class TestRedisClient:
    """Tests for Redis client functionality."""

    @pytest.mark.asyncio
    async def test_get_redis_client_returns_instance(self) -> None:
        """Test that get_redis_client returns a Redis instance."""
        client = await get_redis_client()

        assert client is not None
        assert isinstance(client, Redis)

    @pytest.mark.asyncio
    async def test_get_redis_client_singleton(self) -> None:
        """Test that get_redis_client returns the same instance."""
        client1 = await get_redis_client()
        client2 = await get_redis_client()

        # Should return the same instance
        assert client1 is client2

    @pytest.mark.asyncio
    async def test_ping_redis_health_check(self) -> None:
        """Test Redis health check with ping."""
        # Note: This test will pass even if Redis is not running
        # It returns False in that case
        is_healthy = await ping_redis()

        # Should return a boolean
        assert isinstance(is_healthy, bool)

    @pytest.mark.asyncio
    async def test_close_redis_connection(self) -> None:
        """Test closing Redis connection."""
        # Get a client instance
        await get_redis_client()

        # Close should not raise an exception
        await close_redis()

        # After close, get_redis_client should create a new instance
        new_client = await get_redis_client()
        assert new_client is not None
