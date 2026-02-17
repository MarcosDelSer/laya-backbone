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
    mock_client = AsyncMock()
    mock_client.ping = AsyncMock()
    mock_client.info = AsyncMock(
        return_value={
            "redis_version": "7.0.0",
            "uptime_in_seconds": 1000,
        }
    )
    mock_client.close = AsyncMock()

    with patch("redis.asyncio.Redis") as mock_redis_class:
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
    mock_client = AsyncMock()
    mock_client.ping.side_effect = Exception("Connection refused")

    with patch("redis.asyncio.Redis") as mock_redis_class:
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

        with patch("app.routers.health.check_redis_pool") as mock_redis_pool:
            mock_redis_pool.return_value = {
                "status": "healthy",
                "max_connections": 10,
            }

            response = await client.get("/api/v1/health")

            assert response.status_code == 200
            data = response.json()

            # Verify timestamp is in ISO format with Z suffix
            assert data["timestamp"].endswith("Z")

            # Verify timestamp can be parsed
            from datetime import datetime

            datetime.fromisoformat(data["timestamp"].replace("Z", "+00:00"))


@pytest.mark.asyncio
async def test_check_database_pool_healthy() -> None:
    """Test database pool check with healthy pool."""
    from app.routers.health import check_database_pool

    # Mock the engine pool
    with patch("app.database.engine") as mock_engine:
        mock_pool = MagicMock()
        mock_pool.size.return_value = 5
        mock_pool.checkedout.return_value = 2
        mock_pool.checkedin.return_value = 3
        mock_pool.overflow.return_value = 0
        mock_engine.pool = mock_pool

        result = check_database_pool()

        assert result["status"] == "healthy"
        assert result["pool_size"] == 5
        assert result["checked_out"] == 2
        assert result["checked_in"] == 3
        assert result["overflow"] == 0
        assert result["total_capacity"] == 5
        assert result["utilization_percent"] == 40.0


@pytest.mark.asyncio
async def test_check_database_pool_degraded() -> None:
    """Test database pool check with degraded pool (>80% utilization)."""
    from app.routers.health import check_database_pool

    # Mock the engine pool with high utilization
    with patch("app.database.engine") as mock_engine:
        mock_pool = MagicMock()
        mock_pool.size.return_value = 5
        mock_pool.checkedout.return_value = 5
        mock_pool.checkedin.return_value = 0
        mock_pool.overflow.return_value = 1
        mock_engine.pool = mock_pool

        result = check_database_pool()

        assert result["status"] == "degraded"
        assert result["pool_size"] == 5
        assert result["checked_out"] == 5
        assert result["overflow"] == 1
        assert result["utilization_percent"] > 80


@pytest.mark.asyncio
async def test_check_database_pool_critical() -> None:
    """Test database pool check with critical pool (>95% utilization)."""
    from app.routers.health import check_database_pool

    # Mock the engine pool with critical utilization
    # 10 checked out / 10 total = 100% utilization
    with patch("app.database.engine") as mock_engine:
        mock_pool = MagicMock()
        mock_pool.size.return_value = 5
        mock_pool.checkedout.return_value = 10
        mock_pool.checkedin.return_value = 0
        mock_pool.overflow.return_value = 5
        mock_engine.pool = mock_pool

        result = check_database_pool()

        assert result["status"] == "critical"
        assert result["utilization_percent"] == 100.0


@pytest.mark.asyncio
async def test_check_database_pool_error() -> None:
    """Test database pool check with error."""
    from app.routers.health import check_database_pool

    # Mock the engine to raise an exception
    with patch("app.database.engine") as mock_engine:
        mock_engine.pool.size.side_effect = Exception("Pool error")

        result = check_database_pool()

        assert result["status"] == "error"
        assert "error" in result


@pytest.mark.asyncio
async def test_check_redis_pool_success() -> None:
    """Test Redis pool check with successful connection."""
    from app.routers.health import check_redis_pool

    # Mock Redis connection pool
    mock_pool = AsyncMock()
    mock_pool.max_connections = 10
    mock_pool.disconnect = AsyncMock()

    mock_client = AsyncMock()
    mock_client.ping = AsyncMock()
    mock_client.info = AsyncMock(
        return_value={
            "total_connections_received": 100,
            "connected_clients": 5,
        }
    )
    mock_client.close = AsyncMock()

    with patch("redis.asyncio.ConnectionPool") as mock_pool_class:
        with patch("redis.asyncio.Redis") as mock_redis_class:
            mock_pool_class.return_value = mock_pool
            mock_redis_class.return_value = mock_client

            result = await check_redis_pool()

            assert result["status"] == "healthy"
            assert result["max_connections"] == 10
            assert result["total_connections_received"] == 100
            assert result["connected_clients"] == 5


@pytest.mark.asyncio
async def test_check_redis_pool_failure() -> None:
    """Test Redis pool check with failed connection."""
    from app.routers.health import check_redis_pool

    # Mock Redis connection pool to raise exception
    mock_client = AsyncMock()
    mock_client.ping.side_effect = Exception("Connection refused")

    with patch("redis.asyncio.ConnectionPool") as mock_pool_class:
        with patch("redis.asyncio.Redis") as mock_redis_class:
            mock_redis_class.return_value = mock_client

            result = await check_redis_pool()

            assert result["status"] == "unhealthy"
            assert "error" in result


@pytest.mark.asyncio
async def test_connection_pools_endpoint(client: AsyncClient) -> None:
    """Test connection pools monitoring endpoint.

    Args:
        client: Async HTTP client fixture
    """
    # Mock pool checks
    with patch("app.routers.health.check_database_pool") as mock_db_pool:
        with patch("app.routers.health.check_redis_pool") as mock_redis_pool:
            mock_db_pool.return_value = {
                "status": "healthy",
                "pool_size": 5,
                "checked_out": 2,
                "checked_in": 3,
                "overflow": 0,
                "utilization_percent": 40.0,
            }

            mock_redis_pool.return_value = {
                "status": "healthy",
                "max_connections": 10,
                "connected_clients": 5,
            }

            response = await client.get("/api/v1/health/pools")

            assert response.status_code == 200
            data = response.json()

            # Verify response structure
            assert "timestamp" in data
            assert "pools" in data

            # Verify timestamp format
            assert data["timestamp"].endswith("Z")

            # Verify pool data
            pools = data["pools"]
            assert "database" in pools
            assert "redis" in pools

            # Verify database pool data
            db_pool = pools["database"]
            assert db_pool["status"] == "healthy"
            assert db_pool["pool_size"] == 5
            assert db_pool["utilization_percent"] == 40.0

            # Verify redis pool data
            redis_pool = pools["redis"]
            assert redis_pool["status"] == "healthy"
            assert redis_pool["max_connections"] == 10


@pytest.mark.asyncio
async def test_health_check_includes_pools(
    client: AsyncClient,
    db_session: AsyncSession,
) -> None:
    """Test that comprehensive health check includes pool metrics.

    Args:
        client: Async HTTP client fixture
        db_session: Database session fixture
    """
    with patch("app.routers.health.check_redis_health") as mock_redis:
        with patch("app.routers.health.check_redis_pool") as mock_redis_pool:
            mock_redis.return_value = {
                "status": "healthy",
                "connected": True,
            }

            mock_redis_pool.return_value = {
                "status": "healthy",
                "max_connections": 10,
            }

            response = await client.get("/api/v1/health")

            assert response.status_code == 200
            data = response.json()

            # Verify pool checks are included
            checks = data["checks"]
            assert "database_pool" in checks
            assert "redis_pool" in checks

            # Verify pool data structure
            assert "status" in checks["database_pool"]
            assert "status" in checks["redis_pool"]


@pytest.mark.asyncio
async def test_health_check_degraded_pool(
    client: AsyncClient,
    db_session: AsyncSession,
) -> None:
    """Test health check with degraded database pool.

    Args:
        client: Async HTTP client fixture
        db_session: Database session fixture
    """
    with patch("app.routers.health.check_database_pool") as mock_db_pool:
        with patch("app.routers.health.check_redis_health") as mock_redis:
            with patch("app.routers.health.check_redis_pool") as mock_redis_pool:
                mock_db_pool.return_value = {
                    "status": "degraded",
                    "utilization_percent": 85.0,
                }

                mock_redis.return_value = {
                    "status": "healthy",
                    "connected": True,
                }

                mock_redis_pool.return_value = {
                    "status": "healthy",
                    "max_connections": 10,
                }

                response = await client.get("/api/v1/health")

                assert response.status_code == 200
                data = response.json()

                # Overall status should be degraded due to pool
                assert data["status"] == "degraded"
                assert data["checks"]["database_pool"]["status"] == "degraded"


@pytest.mark.asyncio
async def test_health_check_critical_pool(
    client: AsyncClient,
    db_session: AsyncSession,
) -> None:
    """Test health check with critical database pool.

    Args:
        client: Async HTTP client fixture
        db_session: Database session fixture
    """
    with patch("app.routers.health.check_database_pool") as mock_db_pool:
        with patch("app.routers.health.check_redis_health") as mock_redis:
            with patch("app.routers.health.check_redis_pool") as mock_redis_pool:
                mock_db_pool.return_value = {
                    "status": "critical",
                    "utilization_percent": 98.0,
                }

                mock_redis.return_value = {
                    "status": "healthy",
                    "connected": True,
                }

                mock_redis_pool.return_value = {
                    "status": "healthy",
                    "max_connections": 10,
                }

                response = await client.get("/api/v1/health")

                assert response.status_code == 200
                data = response.json()

                # Overall status should be unhealthy due to critical pool
                assert data["status"] == "unhealthy"
                assert data["checks"]["database_pool"]["status"] == "critical"
