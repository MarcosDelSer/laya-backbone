"""Unit tests for Redis health check functionality.

Tests for health check endpoints with Redis connectivity verification.
"""

from __future__ import annotations

from unittest.mock import AsyncMock, patch

import pytest
import pytest_asyncio
from httpx import ASGITransport, AsyncClient

from app.main import app


@pytest_asyncio.fixture
async def async_client() -> AsyncClient:
    """Create an async HTTP client for testing.

    Returns:
        AsyncClient: Async HTTP client for API testing
    """
    async with AsyncClient(
        transport=ASGITransport(app=app), base_url="http://test"
    ) as client:
        yield client


class TestRootHealthCheck:
    """Tests for root endpoint ("/") health check."""

    @pytest.mark.asyncio
    async def test_root_endpoint_returns_healthy_status(
        self, async_client: AsyncClient
    ) -> None:
        """Test that root endpoint returns healthy status."""
        response = await async_client.get("/")

        assert response.status_code == 200
        data = response.json()
        assert data["status"] == "healthy"
        assert data["service"] == "ai-service"
        assert data["version"] == "0.1.0"
        # Root endpoint doesn't include Redis check
        assert "redis" not in data

    @pytest.mark.asyncio
    async def test_root_endpoint_always_returns_healthy(
        self, async_client: AsyncClient
    ) -> None:
        """Test that root endpoint returns healthy even if Redis is down."""
        # Mock Redis ping to return False (Redis unavailable)
        with patch("app.main.ping_redis", new_callable=AsyncMock) as mock_ping:
            mock_ping.return_value = False

            response = await async_client.get("/")

            assert response.status_code == 200
            data = response.json()
            # Root endpoint should still be healthy
            assert data["status"] == "healthy"
            # Root endpoint doesn't check Redis
            assert "redis" not in data


class TestHealthCheckEndpoint:
    """Tests for comprehensive health check endpoint ("/health")."""

    @pytest.mark.asyncio
    async def test_health_endpoint_when_redis_available(
        self, async_client: AsyncClient
    ) -> None:
        """Test health endpoint when Redis is available and responsive."""
        # Mock Redis ping to return True (Redis available)
        with patch("app.main.ping_redis", new_callable=AsyncMock) as mock_ping:
            mock_ping.return_value = True

            response = await async_client.get("/health")

            assert response.status_code == 200
            data = response.json()
            assert data["status"] == "healthy"
            assert data["service"] == "ai-service"
            assert data["version"] == "0.1.0"
            assert "redis" in data
            assert data["redis"]["connected"] is True
            assert data["redis"]["responsive"] is True

            # Verify ping_redis was called
            mock_ping.assert_called_once()

    @pytest.mark.asyncio
    async def test_health_endpoint_when_redis_unavailable(
        self, async_client: AsyncClient
    ) -> None:
        """Test health endpoint when Redis is unavailable."""
        # Mock Redis ping to return False (Redis unavailable)
        with patch("app.main.ping_redis", new_callable=AsyncMock) as mock_ping:
            mock_ping.return_value = False

            response = await async_client.get("/health")

            assert response.status_code == 200
            data = response.json()
            # Status should be degraded when Redis is unavailable
            assert data["status"] == "degraded"
            assert data["service"] == "ai-service"
            assert data["version"] == "0.1.0"
            assert "redis" in data
            assert data["redis"]["connected"] is False
            assert data["redis"]["responsive"] is False

            # Verify ping_redis was called
            mock_ping.assert_called_once()

    @pytest.mark.asyncio
    async def test_health_endpoint_response_structure(
        self, async_client: AsyncClient
    ) -> None:
        """Test that health endpoint returns correct response structure."""
        with patch("app.main.ping_redis", new_callable=AsyncMock) as mock_ping:
            mock_ping.return_value = True

            response = await async_client.get("/health")

            assert response.status_code == 200
            data = response.json()

            # Verify all required fields are present
            assert "status" in data
            assert "service" in data
            assert "version" in data
            assert "redis" in data

            # Verify Redis sub-structure
            redis_data = data["redis"]
            assert "connected" in redis_data
            assert "responsive" in redis_data
            assert isinstance(redis_data["connected"], bool)
            assert isinstance(redis_data["responsive"], bool)

    @pytest.mark.asyncio
    async def test_health_endpoint_handles_redis_exception(
        self, async_client: AsyncClient
    ) -> None:
        """Test that health endpoint handles Redis exceptions gracefully."""
        # Mock Redis ping to raise an exception
        with patch("app.main.ping_redis", new_callable=AsyncMock) as mock_ping:
            mock_ping.side_effect = Exception("Redis connection error")

            # Health endpoint should handle exception and return degraded status
            response = await async_client.get("/health")

            # Should still return 200 (service is running, just degraded)
            assert response.status_code == 200
            data = response.json()
            # Status should be degraded when Redis check fails
            assert data["status"] == "degraded"
            assert data["redis"]["connected"] is False
            assert data["redis"]["responsive"] is False

    @pytest.mark.asyncio
    async def test_health_endpoint_redis_status_consistency(
        self, async_client: AsyncClient
    ) -> None:
        """Test that Redis connected and responsive status are consistent."""
        # Test when Redis is healthy
        with patch("app.main.ping_redis", new_callable=AsyncMock) as mock_ping:
            mock_ping.return_value = True

            response = await async_client.get("/health")
            data = response.json()

            # Both should be True
            assert data["redis"]["connected"] == data["redis"]["responsive"]

        # Test when Redis is unhealthy
        with patch("app.main.ping_redis", new_callable=AsyncMock) as mock_ping:
            mock_ping.return_value = False

            response = await async_client.get("/health")
            data = response.json()

            # Both should be False
            assert data["redis"]["connected"] == data["redis"]["responsive"]

    @pytest.mark.asyncio
    async def test_health_endpoint_multiple_calls(
        self, async_client: AsyncClient
    ) -> None:
        """Test that health endpoint can be called multiple times."""
        with patch("app.main.ping_redis", new_callable=AsyncMock) as mock_ping:
            mock_ping.return_value = True

            # Make multiple health check requests
            for _ in range(3):
                response = await async_client.get("/health")
                assert response.status_code == 200
                data = response.json()
                assert data["status"] == "healthy"
                assert data["redis"]["connected"] is True

            # Verify ping_redis was called 3 times
            assert mock_ping.call_count == 3


class TestHealthCheckStatusLogic:
    """Tests for health check status determination logic."""

    @pytest.mark.asyncio
    async def test_healthy_status_when_all_dependencies_ok(
        self, async_client: AsyncClient
    ) -> None:
        """Test that status is 'healthy' when all dependencies are OK."""
        with patch("app.main.ping_redis", new_callable=AsyncMock) as mock_ping:
            mock_ping.return_value = True

            response = await async_client.get("/health")
            data = response.json()

            assert data["status"] == "healthy"

    @pytest.mark.asyncio
    async def test_degraded_status_when_redis_down(
        self, async_client: AsyncClient
    ) -> None:
        """Test that status is 'degraded' when Redis is down."""
        with patch("app.main.ping_redis", new_callable=AsyncMock) as mock_ping:
            mock_ping.return_value = False

            response = await async_client.get("/health")
            data = response.json()

            assert data["status"] == "degraded"

    @pytest.mark.asyncio
    async def test_health_check_non_blocking(
        self, async_client: AsyncClient
    ) -> None:
        """Test that health check doesn't block on slow Redis responses."""
        # Mock Redis ping with a small delay to simulate slow response
        async def slow_ping():
            import asyncio

            await asyncio.sleep(0.1)  # 100ms delay
            return True

        with patch("app.main.ping_redis", new_callable=AsyncMock) as mock_ping:
            mock_ping.side_effect = slow_ping

            response = await async_client.get("/health")

            # Should still complete successfully
            assert response.status_code == 200
            data = response.json()
            assert data["status"] == "healthy"


class TestHealthCheckIntegration:
    """Integration tests for health check functionality."""

    @pytest.mark.asyncio
    async def test_health_endpoints_coexist(
        self, async_client: AsyncClient
    ) -> None:
        """Test that both root and /health endpoints work together."""
        with patch("app.main.ping_redis", new_callable=AsyncMock) as mock_ping:
            mock_ping.return_value = True

            # Call root endpoint
            root_response = await async_client.get("/")
            assert root_response.status_code == 200

            # Call health endpoint
            health_response = await async_client.get("/health")
            assert health_response.status_code == 200

            # Both should return valid data
            root_data = root_response.json()
            health_data = health_response.json()

            assert root_data["service"] == health_data["service"]
            assert root_data["version"] == health_data["version"]

    @pytest.mark.asyncio
    async def test_health_endpoint_includes_service_metadata(
        self, async_client: AsyncClient
    ) -> None:
        """Test that health endpoint includes service metadata."""
        with patch("app.main.ping_redis", new_callable=AsyncMock) as mock_ping:
            mock_ping.return_value = True

            response = await async_client.get("/health")
            data = response.json()

            # Verify service metadata is present
            assert data["service"] == "ai-service"
            assert data["version"] == "0.1.0"
            assert isinstance(data["service"], str)
            assert isinstance(data["version"], str)
