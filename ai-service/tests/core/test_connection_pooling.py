"""Tests for database connection pooling tuning and monitoring."""

import pytest
from unittest.mock import AsyncMock, MagicMock, patch
from sqlalchemy.ext.asyncio import AsyncSession

from app.database import (
    check_pool_status,
    get_pool_health,
    get_active_connections,
    optimize_pool_settings,
)
from app.config import settings


@pytest.fixture
def mock_session():
    """Create a mock async database session."""
    session = AsyncMock(spec=AsyncSession)
    return session


@pytest.fixture
def mock_pool():
    """Create a mock connection pool."""
    pool = MagicMock()
    pool.size.return_value = 8
    pool.checkedout.return_value = 5
    pool.overflow.return_value = 3
    return pool


@pytest.mark.asyncio
async def test_check_pool_status(mock_pool):
    """Test checking basic pool status."""
    with patch("app.database.engine") as mock_engine:
        mock_engine.pool = mock_pool

        status = await check_pool_status()

        assert status["pool_size"] == 8
        assert status["checked_out"] == 5
        assert status["overflow"] == 3
        assert status["total_connections"] == 11  # 8 + 3


@pytest.mark.asyncio
async def test_get_pool_health_normal_utilization(mock_pool):
    """Test pool health check with normal utilization."""
    # Configure pool to show 50% utilization
    mock_pool.size.return_value = 5
    mock_pool.checkedout.return_value = 3
    mock_pool.overflow.return_value = 0

    with patch("app.database.engine") as mock_engine:
        mock_engine.pool = mock_pool

        health = await get_pool_health()

        assert health["pool_size"] == 5
        assert health["checked_out"] == 3
        assert health["overflow"] == 0
        assert health["total_connections"] == 5
        assert health["utilization_pct"] < 80  # Should be healthy
        assert len(health["warnings"]) == 0  # No warnings for normal usage


@pytest.mark.asyncio
async def test_get_pool_health_high_utilization(mock_pool):
    """Test pool health check with high utilization."""
    # Configure pool to show 85% utilization
    # With default settings: pool_size=10, max_overflow=20, total=30
    # Using 25 connections = 83% utilization
    mock_pool.size.return_value = 10
    mock_pool.checkedout.return_value = 10
    mock_pool.overflow.return_value = 15

    with patch("app.database.engine") as mock_engine:
        mock_engine.pool = mock_pool

        health = await get_pool_health()

        assert health["total_connections"] == 25
        assert health["utilization_pct"] > 80
        assert len(health["warnings"]) > 0
        assert any("80%" in warning for warning in health["warnings"])
        assert len(health["recommendations"]) > 0


@pytest.mark.asyncio
async def test_get_pool_health_high_overflow(mock_pool):
    """Test pool health check with high overflow usage."""
    # Configure pool to show high overflow usage
    mock_pool.size.return_value = 10
    mock_pool.checkedout.return_value = 10
    mock_pool.overflow.return_value = 18  # 90% of max_overflow=20

    with patch("app.database.engine") as mock_engine:
        mock_engine.pool = mock_pool

        health = await get_pool_health()

        assert health["overflow"] == 18
        # Should warn about high overflow usage
        assert any("overflow" in warning.lower() for warning in health["warnings"])


@pytest.mark.asyncio
async def test_get_pool_health_all_connections_checked_out(mock_pool):
    """Test pool health when all permanent connections are checked out."""
    mock_pool.size.return_value = 10
    mock_pool.checkedout.return_value = 10  # All checked out
    mock_pool.overflow.return_value = 5

    with patch("app.database.engine") as mock_engine:
        mock_engine.pool = mock_pool

        health = await get_pool_health()

        assert health["checked_out"] == health["pool_size"]
        # Should warn about all connections checked out
        assert any("permanent connections" in warning.lower() for warning in health["warnings"])


@pytest.mark.asyncio
async def test_get_pool_health_configuration_values(mock_pool):
    """Test that pool health includes configuration values."""
    mock_pool.size.return_value = 5
    mock_pool.checkedout.return_value = 2
    mock_pool.overflow.return_value = 0

    with patch("app.database.engine") as mock_engine:
        mock_engine.pool = mock_pool

        health = await get_pool_health()

        config = health["configuration"]
        assert config["pool_size"] == settings.db_pool_size
        assert config["max_overflow"] == settings.db_max_overflow
        assert config["pool_timeout"] == settings.db_pool_timeout
        assert config["pool_recycle"] == settings.db_pool_recycle
        assert config["pool_pre_ping"] == settings.db_pool_pre_ping


@pytest.mark.asyncio
async def test_get_active_connections(mock_session):
    """Test getting active connection information from PostgreSQL."""
    # Mock the database response
    mock_result = MagicMock()
    mock_result.fetchone.return_value = (15, 5, 8, 2, 45.5, 120.0)
    mock_session.execute.return_value = mock_result

    conn_info = await get_active_connections(mock_session)

    assert conn_info["total_connections"] == 15
    assert conn_info["active_count"] == 5
    assert conn_info["idle_count"] == 8
    assert conn_info["idle_in_transaction"] == 2
    assert conn_info["longest_query_seconds"] == 45.5
    assert conn_info["longest_idle_seconds"] == 120.0


@pytest.mark.asyncio
async def test_get_active_connections_empty_result(mock_session):
    """Test getting active connections when no connections exist."""
    mock_result = MagicMock()
    mock_result.fetchone.return_value = None
    mock_session.execute.return_value = mock_result

    conn_info = await get_active_connections(mock_session)

    assert conn_info["total_connections"] == 0
    assert conn_info["active_count"] == 0
    assert conn_info["idle_count"] == 0


@pytest.mark.asyncio
async def test_optimize_pool_settings_low_usage(mock_session, mock_pool):
    """Test pool optimization recommendations for low usage."""
    # Mock low usage scenario
    mock_result = MagicMock()
    mock_result.fetchone.return_value = (5, 2, 3, 0, None, 10.0)
    mock_session.execute.return_value = mock_result

    mock_pool.size.return_value = 10
    mock_pool.checkedout.return_value = 2
    mock_pool.overflow.return_value = 0

    with patch("app.database.engine") as mock_engine:
        mock_engine.pool = mock_pool

        recommendations = await optimize_pool_settings(mock_session)

        assert "current_settings" in recommendations
        assert "current_usage" in recommendations
        assert "recommended_pool_size" in recommendations
        assert "recommended_max_overflow" in recommendations
        assert recommendations["current_usage"]["active_connections"] == 2
        # With 2 active connections, recommended = max(5, 2 * 1.2) = 5
        assert recommendations["recommended_pool_size"] >= 5


@pytest.mark.asyncio
async def test_optimize_pool_settings_high_usage(mock_session, mock_pool):
    """Test pool optimization recommendations for high usage."""
    # Mock high usage scenario
    mock_result = MagicMock()
    mock_result.fetchone.return_value = (25, 20, 5, 0, 15.0, 5.0)
    mock_session.execute.return_value = mock_result

    mock_pool.size.return_value = 10
    mock_pool.checkedout.return_value = 10
    mock_pool.overflow.return_value = 15

    with patch("app.database.engine") as mock_engine:
        mock_engine.pool = mock_pool

        recommendations = await optimize_pool_settings(mock_session)

        # With 20 active connections, recommended = 20 * 1.2 = 24
        assert recommendations["recommended_pool_size"] >= 20
        # Should recommend increasing pool size
        assert any("Increase pool_size" in rec for rec in recommendations["recommendations"])
        # Should warn about high utilization (25/30 = 83%)
        assert any("utilization" in rec.lower() for rec in recommendations["recommendations"])


@pytest.mark.asyncio
async def test_optimize_pool_settings_idle_in_transaction(mock_session, mock_pool):
    """Test pool optimization detects idle-in-transaction connections."""
    # Mock scenario with idle-in-transaction connections
    mock_result = MagicMock()
    mock_result.fetchone.return_value = (10, 5, 3, 2, 5.0, 10.0)
    mock_session.execute.return_value = mock_result

    mock_pool.size.return_value = 10
    mock_pool.checkedout.return_value = 5
    mock_pool.overflow.return_value = 0

    with patch("app.database.engine") as mock_engine:
        mock_engine.pool = mock_pool

        recommendations = await optimize_pool_settings(mock_session)

        # Should warn about idle-in-transaction connections
        assert any("idle-in-transaction" in rec for rec in recommendations["recommendations"])


@pytest.mark.asyncio
async def test_optimize_pool_settings_long_running_query(mock_session, mock_pool):
    """Test pool optimization detects long-running queries."""
    # Mock scenario with long-running query
    mock_result = MagicMock()
    mock_result.fetchone.return_value = (10, 5, 5, 0, 65.0, 10.0)  # 65 second query
    mock_session.execute.return_value = mock_result

    mock_pool.size.return_value = 10
    mock_pool.checkedout.return_value = 5
    mock_pool.overflow.return_value = 0

    with patch("app.database.engine") as mock_engine:
        mock_engine.pool = mock_pool

        recommendations = await optimize_pool_settings(mock_session)

        # Should warn about long-running query
        assert any("Long-running query" in rec for rec in recommendations["recommendations"])


@pytest.mark.asyncio
async def test_optimize_pool_settings_oversized_pool(mock_session, mock_pool):
    """Test pool optimization suggests reducing oversized pool."""
    # Mock low usage with large pool
    mock_result = MagicMock()
    mock_result.fetchone.return_value = (5, 2, 3, 0, 1.0, 5.0)
    mock_session.execute.return_value = mock_result

    # Large pool for low usage
    mock_pool.size.return_value = 20
    mock_pool.checkedout.return_value = 2
    mock_pool.overflow.return_value = 0

    with patch("app.database.engine") as mock_engine, \
         patch("app.database.settings") as mock_settings:
        mock_engine.pool = mock_pool
        mock_settings.db_pool_size = 20
        mock_settings.db_max_overflow = 40

        recommendations = await optimize_pool_settings(mock_session)

        # Should suggest reducing pool size
        # With 2 active, recommended = max(5, 2.4) = 5
        # 5 < 20 * 0.5 = 10, so should recommend reduction
        assert recommendations["recommended_pool_size"] < 20


@pytest.mark.asyncio
async def test_pool_health_zero_max_connections():
    """Test pool health handles edge case of zero max connections."""
    mock_pool = MagicMock()
    mock_pool.size.return_value = 0
    mock_pool.checkedout.return_value = 0
    mock_pool.overflow.return_value = 0

    with patch("app.database.engine") as mock_engine, \
         patch("app.database.settings") as mock_settings:
        mock_engine.pool = mock_pool
        mock_settings.db_pool_size = 0
        mock_settings.db_max_overflow = 0

        health = await get_pool_health()

        # Should handle division by zero gracefully
        assert health["utilization_pct"] == 0
        assert health["max_connections"] == 0


@pytest.mark.asyncio
async def test_pool_recommendations_format():
    """Test that pool health recommendations are properly formatted."""
    mock_pool = MagicMock()
    mock_pool.size.return_value = 10
    mock_pool.checkedout.return_value = 10
    mock_pool.overflow.return_value = 18

    with patch("app.database.engine") as mock_engine:
        mock_engine.pool = mock_pool

        health = await get_pool_health()

        # All warnings should be strings
        assert all(isinstance(w, str) for w in health["warnings"])
        # All recommendations should be strings
        assert all(isinstance(r, str) for r in health["recommendations"])
        # Warnings should have actionable messages
        for warning in health["warnings"]:
            assert len(warning) > 10  # Should have meaningful content
