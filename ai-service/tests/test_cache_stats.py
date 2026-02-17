"""Unit tests for cache statistics endpoint.

Tests for GET /api/v1/cache/stats endpoint and cache statistics functionality.
"""

from __future__ import annotations

import pytest
from fastapi.testclient import TestClient
from unittest.mock import AsyncMock, patch, MagicMock

from app.main import app
from app.schemas.cache import CacheStatsResponse, CachePrefixStats
from app.services.cache_service import get_cache_statistics, _format_bytes


class TestFormatBytes:
    """Tests for byte formatting utility."""

    def test_format_bytes_small(self) -> None:
        """Test formatting small byte values."""
        assert _format_bytes(0) == "0.0B"
        assert _format_bytes(100) == "100.0B"
        assert _format_bytes(1023) == "1023.0B"

    def test_format_bytes_kilobytes(self) -> None:
        """Test formatting kilobyte values."""
        assert _format_bytes(1024) == "1.0K"
        assert _format_bytes(1536) == "1.5K"
        assert _format_bytes(10240) == "10.0K"

    def test_format_bytes_megabytes(self) -> None:
        """Test formatting megabyte values."""
        assert _format_bytes(1024 * 1024) == "1.0M"
        assert _format_bytes(1024 * 1024 * 2.5) == "2.5M"

    def test_format_bytes_gigabytes(self) -> None:
        """Test formatting gigabyte values."""
        assert _format_bytes(1024 * 1024 * 1024) == "1.0G"
        assert _format_bytes(1024 * 1024 * 1024 * 3) == "3.0G"


@pytest.mark.asyncio
class TestCacheStatisticsService:
    """Tests for cache statistics service."""

    async def test_get_cache_statistics_success(self) -> None:
        """Test getting cache statistics successfully."""
        # Mock Redis client
        mock_redis = AsyncMock()

        # Mock info command
        mock_redis.info.return_value = {
            "db0": {"keys": 42},
            "used_memory": 1048576,  # 1MB
            "uptime_in_seconds": 3600,
            "connected_clients": 5,
        }

        # Mock scan_iter for different prefixes
        async def mock_scan_iter(match=None, count=100):
            """Mock scan iterator that yields keys based on pattern."""
            if match == "child_profile:*":
                for i in range(10):
                    yield f"child_profile:func:{i}"
            elif match == "activity_catalog:*":
                for i in range(5):
                    yield f"activity_catalog:func:{i}"
            elif match == "analytics_dashboard:*":
                for i in range(3):
                    yield f"analytics_dashboard:func:{i}"
            elif match == "llm_response:*":
                for i in range(8):
                    yield f"llm_response:func:{i}"

        mock_redis.scan_iter = mock_scan_iter

        # Mock TTL command
        mock_redis.ttl.return_value = 300

        # Patch get_redis_client
        with patch("app.services.cache_service.get_redis_client", return_value=mock_redis):
            stats = await get_cache_statistics()

        # Verify stats structure
        assert isinstance(stats, CacheStatsResponse)
        assert stats.total_keys == 42
        assert stats.memory_used_bytes == 1048576
        assert stats.memory_used_human == "1.0M"
        assert stats.uptime_seconds == 3600
        assert stats.connected_clients == 5

        # Verify by_prefix stats
        assert "child_profile" in stats.by_prefix
        assert stats.by_prefix["child_profile"].key_count == 10
        assert stats.by_prefix["child_profile"].sample_ttl == 300

        assert "activity_catalog" in stats.by_prefix
        assert stats.by_prefix["activity_catalog"].key_count == 5

        assert "analytics_dashboard" in stats.by_prefix
        assert stats.by_prefix["analytics_dashboard"].key_count == 3

        assert "llm_response" in stats.by_prefix
        assert stats.by_prefix["llm_response"].key_count == 8

    async def test_get_cache_statistics_no_keys(self) -> None:
        """Test getting cache statistics when no keys exist."""
        # Mock Redis client
        mock_redis = AsyncMock()

        # Mock info command with no keys
        mock_redis.info.return_value = {
            "db0": {},
            "used_memory": 1024,
            "uptime_in_seconds": 100,
            "connected_clients": 1,
        }

        # Mock scan_iter with no keys
        async def mock_scan_iter(match=None, count=100):
            """Mock scan iterator that yields no keys."""
            return
            yield  # Make it a generator

        mock_redis.scan_iter = mock_scan_iter

        # Patch get_redis_client
        with patch("app.services.cache_service.get_redis_client", return_value=mock_redis):
            stats = await get_cache_statistics()

        # Verify stats
        assert stats.total_keys == 0
        assert stats.memory_used_bytes == 1024

        # All prefixes should have 0 keys
        for prefix in ["child_profile", "activity_catalog", "analytics_dashboard", "llm_response"]:
            assert stats.by_prefix[prefix].key_count == 0
            assert stats.by_prefix[prefix].sample_ttl is None

    async def test_get_cache_statistics_ttl_no_expiration(self) -> None:
        """Test cache statistics when keys have no expiration."""
        # Mock Redis client
        mock_redis = AsyncMock()

        # Mock info command
        mock_redis.info.return_value = {
            "db0": {"keys": 5},
            "used_memory": 2048,
            "uptime_in_seconds": 200,
            "connected_clients": 2,
        }

        # Mock scan_iter
        async def mock_scan_iter(match=None, count=100):
            """Mock scan iterator."""
            if match == "child_profile:*":
                yield "child_profile:func:1"
            elif match in ["activity_catalog:*", "analytics_dashboard:*", "llm_response:*"]:
                return
                yield

        mock_redis.scan_iter = mock_scan_iter

        # Mock TTL returning -1 (no expiration)
        mock_redis.ttl.return_value = -1

        # Patch get_redis_client
        with patch("app.services.cache_service.get_redis_client", return_value=mock_redis):
            stats = await get_cache_statistics()

        # Verify TTL is None when key has no expiration
        assert stats.by_prefix["child_profile"].key_count == 1
        assert stats.by_prefix["child_profile"].sample_ttl is None


@pytest.mark.asyncio
class TestCacheStatsEndpoint:
    """Tests for cache statistics endpoint."""

    async def test_get_cache_stats_success(self) -> None:
        """Test successful cache stats retrieval."""
        # Create test client
        client = TestClient(app)

        # Mock the cache statistics service
        mock_stats = CacheStatsResponse(
            total_keys=100,
            memory_used_bytes=2097152,
            memory_used_human="2.0M",
            by_prefix={
                "child_profile": CachePrefixStats(key_count=25, sample_ttl=300),
                "activity_catalog": CachePrefixStats(key_count=30, sample_ttl=3600),
                "analytics_dashboard": CachePrefixStats(key_count=20, sample_ttl=900),
                "llm_response": CachePrefixStats(key_count=25, sample_ttl=86400),
            },
            uptime_seconds=7200,
            connected_clients=10,
        )

        # Mock authentication
        mock_token_payload = {"sub": "admin@laya.com", "role": "admin"}

        with patch("app.routers.cache.get_cache_statistics", return_value=mock_stats), \
             patch("app.dependencies.verify_token", return_value=mock_token_payload):
            # Make request with auth token
            response = client.get(
                "/api/v1/cache/stats",
                headers={"Authorization": "Bearer fake-token"}
            )

        # Verify response
        assert response.status_code == 200
        data = response.json()

        assert data["total_keys"] == 100
        assert data["memory_used_bytes"] == 2097152
        assert data["memory_used_human"] == "2.0M"
        assert data["uptime_seconds"] == 7200
        assert data["connected_clients"] == 10

        # Verify by_prefix data
        assert data["by_prefix"]["child_profile"]["key_count"] == 25
        assert data["by_prefix"]["child_profile"]["sample_ttl"] == 300

        assert data["by_prefix"]["activity_catalog"]["key_count"] == 30
        assert data["by_prefix"]["activity_catalog"]["sample_ttl"] == 3600

        assert data["by_prefix"]["analytics_dashboard"]["key_count"] == 20
        assert data["by_prefix"]["analytics_dashboard"]["sample_ttl"] == 900

        assert data["by_prefix"]["llm_response"]["key_count"] == 25
        assert data["by_prefix"]["llm_response"]["sample_ttl"] == 86400

    async def test_get_cache_stats_unauthorized(self) -> None:
        """Test cache stats endpoint without authentication."""
        client = TestClient(app)

        # Make request without auth token
        response = client.get("/api/v1/cache/stats")

        # Should return 401 or 403
        assert response.status_code in [401, 403]

    async def test_get_cache_stats_redis_unavailable(self) -> None:
        """Test cache stats when Redis is unavailable."""
        client = TestClient(app)

        # Mock authentication
        mock_token_payload = {"sub": "admin@laya.com", "role": "admin"}

        # Mock cache statistics to raise exception
        with patch("app.routers.cache.get_cache_statistics", side_effect=Exception("Redis connection failed")), \
             patch("app.dependencies.verify_token", return_value=mock_token_payload):
            response = client.get(
                "/api/v1/cache/stats",
                headers={"Authorization": "Bearer fake-token"}
            )

        # Should return 503 Service Unavailable
        assert response.status_code == 503
        assert "unavailable" in response.json()["detail"].lower()


@pytest.mark.asyncio
class TestCacheStatsIntegration:
    """Integration tests for cache statistics with real Redis (if available)."""

    async def test_cache_stats_with_populated_cache(self) -> None:
        """Test cache statistics after populating cache."""
        # This test requires Redis to be running
        # Skip if Redis is not available
        pytest.skip("Requires Redis - run in integration test environment")

        # TODO: Add integration test that:
        # 1. Clears Redis
        # 2. Populates cache with known data
        # 3. Calls get_cache_statistics()
        # 4. Verifies counts and statistics
        # 5. Cleans up Redis
