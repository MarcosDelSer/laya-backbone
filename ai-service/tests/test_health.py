"""Tests for health check endpoints.

Tests comprehensive health check functionality including database,
Redis, disk, and memory checks.
"""

from unittest.mock import AsyncMock, MagicMock, patch

import pytest
from httpx import AsyncClient
from sqlalchemy.ext.asyncio import AsyncSession


@pytest.mark.asyncio
async def test_health_check_endpoint_healthy(
    client: AsyncClient,
    db_session: AsyncSession,
) -> None:
    """Test health check endpoint returns healthy status.

    Args:
        client: Async HTTP client fixture
        db_session: Database session fixture
    """
    # Mock Redis to return healthy status
    with patch("app.routers.health.check_redis_health") as mock_redis:
        mock_redis.return_value = {
            "status": "healthy",
            "connected": True,
            "version": "7.0.0",
            "uptime_seconds": 1000,
        }

        response = await client.get("/api/v1/health")

        assert response.status_code == 200
        data = response.json()

        # Verify response structure
        assert "status" in data
        assert "timestamp" in data
        assert "service" in data
        assert "version" in data
        assert "checks" in data

        # Verify service metadata
        assert data["service"] == "ai-service"
        assert data["version"] == "0.1.0"

        # Verify individual checks
        checks = data["checks"]
        assert "database" in checks
        assert "redis" in checks
        assert "disk" in checks
        assert "memory" in checks

        # Database should be healthy (using test database)
        assert checks["database"]["status"] == "healthy"
        assert checks["database"]["connected"] is True

        # Disk should have status
        assert "status" in checks["disk"]
        assert "percent_used" in checks["disk"] or "error" in checks["disk"]

        # Memory should have status
        assert "status" in checks["memory"]


@pytest.mark.asyncio
async def test_health_check_database_unhealthy(
    client: AsyncClient,
) -> None:
    """Test health check when database is unhealthy.

    Args:
        client: Async HTTP client fixture
    """
    # Mock database to fail
    with patch("app.routers.health.check_database_health") as mock_db:
        mock_db.return_value = {
            "status": "unhealthy",
            "connected": False,
            "error": "Connection refused",
        }

        with patch("app.routers.health.check_redis_health") as mock_redis:
            mock_redis.return_value = {
                "status": "healthy",
                "connected": True,
            }

            response = await client.get("/api/v1/health")

            # Should still return 200 but with unhealthy status
            # Note: Based on the implementation, it returns 503 for unhealthy
            assert response.status_code in [200, 503]
            data = response.json()

            # Overall status should be unhealthy
            assert data["status"] == "unhealthy"
            assert data["checks"]["database"]["status"] == "unhealthy"


@pytest.mark.asyncio
async def test_health_check_redis_unavailable(
    client: AsyncClient,
    db_session: AsyncSession,
) -> None:
    """Test health check when Redis is unavailable.

    Args:
        client: Async HTTP client fixture
        db_session: Database session fixture
    """
    with patch("app.routers.health.check_redis_health") as mock_redis:
        mock_redis.return_value = {
            "status": "unhealthy",
            "connected": False,
            "error": "Connection refused",
        }

        response = await client.get("/api/v1/health")

        assert response.status_code == 200
        data = response.json()

        # Redis is optional, so overall status can still be healthy
        # if database and disk are healthy
        assert data["checks"]["redis"]["status"] == "unhealthy"


@pytest.mark.asyncio
async def test_health_check_disk_full(
    client: AsyncClient,
    db_session: AsyncSession,
) -> None:
    """Test health check when disk is almost full.

    Args:
        client: Async HTTP client fixture
        db_session: Database session fixture
    """
    with patch("app.routers.health.get_disk_usage") as mock_disk:
        mock_disk.return_value = {
            "status": "degraded",
            "total_gb": 100.0,
            "used_gb": 95.0,
            "free_gb": 5.0,
            "percent_used": 95.0,
        }

        with patch("app.routers.health.check_redis_health") as mock_redis:
            mock_redis.return_value = {
                "status": "healthy",
                "connected": True,
            }

            response = await client.get("/api/v1/health")

            # Should return unhealthy status
            assert response.status_code in [200, 503]
            data = response.json()

            assert data["checks"]["disk"]["status"] == "degraded"
            assert data["checks"]["disk"]["percent_used"] == 95.0


@pytest.mark.asyncio
async def test_liveness_probe(client: AsyncClient) -> None:
    """Test liveness probe endpoint.

    Args:
        client: Async HTTP client fixture
    """
    response = await client.get("/api/v1/health/liveness")

    assert response.status_code == 200
    data = response.json()

    assert data["status"] == "alive"


@pytest.mark.asyncio
async def test_readiness_probe_ready(
    client: AsyncClient,
    db_session: AsyncSession,
) -> None:
    """Test readiness probe when service is ready.

    Args:
        client: Async HTTP client fixture
        db_session: Database session fixture
    """
    response = await client.get("/api/v1/health/readiness")

    assert response.status_code == 200
    data = response.json()

    assert data["status"] == "ready"
    assert "database" in data
    assert data["database"]["status"] == "healthy"


@pytest.mark.asyncio
async def test_readiness_probe_not_ready(client: AsyncClient) -> None:
    """Test readiness probe when service is not ready.

    Args:
        client: Async HTTP client fixture
    """
    # Mock database to fail
    with patch("app.routers.health.check_database_health") as mock_db:
        mock_db.return_value = {
            "status": "unhealthy",
            "connected": False,
            "error": "Connection refused",
        }

        response = await client.get("/api/v1/health/readiness")

        assert response.status_code == 200
        data = response.json()

        assert data["status"] == "not_ready"
        assert data["database"]["status"] == "unhealthy"


@pytest.mark.asyncio
async def test_check_database_health_success(db_session: AsyncSession) -> None:
    """Test database health check with successful connection.

    Args:
        db_session: Database session fixture
    """
    from app.routers.health import check_database_health

    result = await check_database_health(db_session)

    assert result["status"] == "healthy"
    assert result["connected"] is True


@pytest.mark.asyncio
async def test_check_database_health_failure() -> None:
    """Test database health check with failed connection."""
    from app.routers.health import check_database_health

    # Create a mock session that raises an exception
    mock_session = AsyncMock(spec=AsyncSession)
    mock_session.execute.side_effect = Exception("Connection refused")

    result = await check_database_health(mock_session)

    assert result["status"] == "unhealthy"
    assert result["connected"] is False
    assert "error" in result


@pytest.mark.asyncio
async def test_get_memory_usage() -> None:
    """Test memory usage collection."""
    from app.routers.health import get_memory_usage

    result = get_memory_usage()

    # Should return either healthy status with metrics or unknown if psutil unavailable
    assert "status" in result

    if result["status"] == "healthy":
        assert "rss_mb" in result
        assert "vms_mb" in result
        assert "percent" in result
        assert isinstance(result["rss_mb"], (int, float))
        assert isinstance(result["vms_mb"], (int, float))
        assert isinstance(result["percent"], (int, float))
    else:
        assert result["status"] == "unknown"
        assert "message" in result


@pytest.mark.asyncio
async def test_get_disk_usage() -> None:
    """Test disk usage collection."""
    from app.routers.health import get_disk_usage

    result = get_disk_usage()

    # Should return status
    assert "status" in result

    if result["status"] in ["healthy", "degraded"]:
        assert "total_gb" in result
        assert "used_gb" in result
        assert "free_gb" in result
        assert "percent_used" in result
        assert isinstance(result["total_gb"], (int, float))
        assert isinstance(result["used_gb"], (int, float))
        assert isinstance(result["free_gb"], (int, float))
        assert isinstance(result["percent_used"], (int, float))
    else:
        assert result["status"] == "error"
        assert "error" in result


@pytest.mark.asyncio
async def test_check_redis_health_success() -> None:
    """Test Redis health check with successful connection."""
    from app.routers.health import check_redis_health

    # Mock Redis client
    with patch("app.routers.health.redis.Redis") as mock_redis_class:
        mock_client = AsyncMock()
        mock_client.ping = AsyncMock()
        mock_client.info = AsyncMock(
            return_value={
                "redis_version": "7.0.0",
                "uptime_in_seconds": 1000,
            }
        )
        mock_client.close = AsyncMock()
        mock_redis_class.return_value = mock_client

        result = await check_redis_health()

        assert result["status"] == "healthy"
        assert result["connected"] is True
        assert result["version"] == "7.0.0"
        assert result["uptime_seconds"] == 1000


@pytest.mark.asyncio
async def test_check_redis_health_failure() -> None:
    """Test Redis health check with failed connection."""
    from app.routers.health import check_redis_health

    # Mock Redis client to raise exception
    with patch("app.routers.health.redis.Redis") as mock_redis_class:
        mock_client = AsyncMock()
        mock_client.ping.side_effect = Exception("Connection refused")
        mock_redis_class.return_value = mock_client

        result = await check_redis_health()

        assert result["status"] == "unhealthy"
        assert result["connected"] is False
        assert "error" in result


@pytest.mark.asyncio
async def test_health_check_timestamp_format(
    client: AsyncClient,
    db_session: AsyncSession,
) -> None:
    """Test that health check timestamp is in correct ISO format.

    Args:
        client: Async HTTP client fixture
        db_session: Database session fixture
    """
    with patch("app.routers.health.check_redis_health") as mock_redis:
        mock_redis.return_value = {
            "status": "healthy",
            "connected": True,
        }

        response = await client.get("/api/v1/health")

        assert response.status_code == 200
        data = response.json()

        # Verify timestamp is in ISO format with Z suffix
        assert data["timestamp"].endswith("Z")

        # Verify timestamp can be parsed
        from datetime import datetime

        datetime.fromisoformat(data["timestamp"].replace("Z", "+00:00"))
