"""Tests for database performance optimization utilities."""

import pytest
from unittest.mock import AsyncMock, MagicMock, patch
from sqlalchemy.ext.asyncio import AsyncSession

from app.core.database import (
    explain_query,
    find_slow_queries,
    find_missing_indexes,
    get_table_index_usage,
    analyze_query_plan,
    get_cache_hit_ratio,
    get_database_size_stats,
)


@pytest.fixture
def mock_session():
    """Create a mock async database session."""
    session = AsyncMock(spec=AsyncSession)
    return session


@pytest.mark.asyncio
async def test_explain_query_with_analyze(mock_session):
    """Test explain_query with ANALYZE option."""
    # Mock the database response
    mock_result = MagicMock()
    mock_result.fetchone.return_value = ([{
        "Execution Time": 5.2,
        "Planning Time": 0.8,
        "Plan": {
            "Total Cost": 100.5,
            "Actual Rows": 42
        }
    }],)
    mock_session.execute.return_value = mock_result

    result = await explain_query(
        mock_session,
        "SELECT * FROM activities WHERE is_active = true",
        analyze=True
    )

    assert result["execution_time_ms"] == 5.2
    assert result["planning_time_ms"] == 0.8
    assert result["total_cost"] == 100.5
    assert result["rows_returned"] == 42
    assert "query" in result
    assert "execution_plan" in result


@pytest.mark.asyncio
async def test_explain_query_without_analyze(mock_session):
    """Test explain_query without ANALYZE option."""
    mock_result = MagicMock()
    mock_result.fetchone.return_value = ([{"Plan": {"Total Cost": 50.0}}],)
    mock_session.execute.return_value = mock_result

    result = await explain_query(
        mock_session,
        "SELECT * FROM activities",
        analyze=False
    )

    assert "query" in result
    assert "execution_plan" in result


@pytest.mark.asyncio
async def test_find_slow_queries(mock_session):
    """Test finding slow queries from pg_stat_statements."""
    mock_result = MagicMock()
    mock_result.__iter__.return_value = iter([
        ("SELECT * FROM activities WHERE name LIKE '%test%'", 100, 5000.0, 50.0, 150.0, 10.0, 1000),
        ("SELECT COUNT(*) FROM activity_participations", 50, 3000.0, 60.0, 100.0, 5.0, 1),
    ])
    mock_session.execute.return_value = mock_result

    slow_queries = await find_slow_queries(mock_session, min_duration_ms=40.0, limit=20)

    assert len(slow_queries) == 2
    assert slow_queries[0]["mean_time_ms"] == 50.0
    assert slow_queries[0]["calls"] == 100
    assert slow_queries[1]["mean_time_ms"] == 60.0


@pytest.mark.asyncio
async def test_find_missing_indexes(mock_session):
    """Test identifying tables that might need indexes."""
    mock_result = MagicMock()
    mock_result.__iter__.return_value = iter([
        ("public", "activities", 500, 50000, 100, 100.0),
        ("public", "activity_participations", 300, 30000, 50, 100.0),
    ])
    mock_session.execute.return_value = mock_result

    tables = await find_missing_indexes(mock_session, min_scans=100)

    assert len(tables) == 2
    assert tables[0]["table_name"] == "activities"
    assert tables[0]["seq_scans"] == 500
    assert tables[0]["seq_tuples_read"] == 50000


@pytest.mark.asyncio
async def test_get_table_index_usage(mock_session):
    """Test getting index usage statistics."""
    mock_result = MagicMock()
    mock_result.__iter__.return_value = iter([
        ("activities", "activities_pkey", 1000, 100, 90.91),
        ("activities", "ix_activities_name", 500, 100, 83.33),
    ])
    mock_session.execute.return_value = mock_result

    stats = await get_table_index_usage(mock_session, schema="public")

    assert len(stats) == 2
    assert stats[0]["table_name"] == "activities"
    assert stats[0]["index_name"] == "activities_pkey"
    assert stats[0]["index_usage_pct"] == 90.91


@pytest.mark.asyncio
async def test_analyze_query_plan(mock_session):
    """Test query plan analysis with suggestions."""
    # Mock the explain_query response
    mock_result = MagicMock()
    mock_result.fetchone.return_value = ([{
        "Execution Time": 150.0,
        "Planning Time": 2.0,
        "Plan": {
            "Total Cost": 500.0,
            "Actual Rows": 1500,
            "Node Type": "Seq Scan"
        }
    }],)
    mock_session.execute.return_value = mock_result

    analysis = await analyze_query_plan(
        mock_session,
        "SELECT * FROM activities WHERE name LIKE '%test%'"
    )

    assert analysis["execution_time_ms"] == 150.0
    assert analysis["rows_returned"] == 1500
    assert len(analysis["suggestions"]) > 0
    # Should suggest adding an index for sequential scan
    assert any("index" in s.lower() for s in analysis["suggestions"])
    # Should suggest pagination for large result set
    assert any("pagination" in s.lower() for s in analysis["suggestions"])


@pytest.mark.asyncio
async def test_get_cache_hit_ratio(mock_session):
    """Test getting cache hit ratio statistics."""
    # Mock buffer cache query
    mock_buffer_result = MagicMock()
    mock_buffer_result.fetchone.return_value = (1000, 9000, 0.90)

    # Mock index cache query
    mock_index_result = MagicMock()
    mock_index_result.fetchone.return_value = (500, 4500, 0.90)

    mock_session.execute.side_effect = [mock_buffer_result, mock_index_result]

    cache_stats = await get_cache_hit_ratio(mock_session)

    assert cache_stats["buffer_cache_hit_ratio"] == 0.90
    assert cache_stats["index_cache_hit_ratio"] == 0.90


@pytest.mark.asyncio
async def test_get_database_size_stats(mock_session):
    """Test getting database size statistics."""
    # Mock database size query
    mock_db_size_result = MagicMock()
    mock_db_size_result.fetchone.return_value = (100 * 1024 * 1024,)  # 100 MB

    # Mock table size query
    mock_table_size_result = MagicMock()
    mock_table_size_result.__iter__.return_value = iter([
        ("activities", 10 * 1024 * 1024),  # 10 MB
        ("activity_participations", 5 * 1024 * 1024),  # 5 MB
    ])

    mock_session.execute.side_effect = [mock_db_size_result, mock_table_size_result]

    stats = await get_database_size_stats(mock_session)

    assert stats["total_size_mb"] == 100.0
    assert len(stats["largest_tables"]) == 2
    assert stats["largest_tables"][0]["table_name"] == "activities"
    assert stats["largest_tables"][0]["size_mb"] == 10.0


@pytest.mark.asyncio
async def test_analyze_query_plan_with_joins(mock_session):
    """Test query plan analysis for queries with joins."""
    mock_result = MagicMock()
    mock_result.fetchone.return_value = ([{
        "Execution Time": 80.0,
        "Planning Time": 1.5,
        "Plan": {
            "Total Cost": 300.0,
            "Actual Rows": 500,
            "Node Type": "Hash Join"
        }
    }],)
    mock_session.execute.return_value = mock_result

    analysis = await analyze_query_plan(
        mock_session,
        "SELECT a.* FROM activities a JOIN activity_participations p ON a.id = p.activity_id"
    )

    assert analysis["execution_time_ms"] == 80.0
    # Should suggest ensuring foreign keys are indexed for joins
    assert any("join" in s.lower() for s in analysis["suggestions"])


@pytest.mark.asyncio
async def test_cache_hit_ratio_with_no_data(mock_session):
    """Test cache hit ratio when there's no data."""
    mock_buffer_result = MagicMock()
    mock_buffer_result.fetchone.return_value = (0, 0, None)

    mock_index_result = MagicMock()
    mock_index_result.fetchone.return_value = (0, 0, None)

    mock_session.execute.side_effect = [mock_buffer_result, mock_index_result]

    cache_stats = await get_cache_hit_ratio(mock_session)

    assert cache_stats["buffer_cache_hit_ratio"] == 0.0
    assert cache_stats["index_cache_hit_ratio"] == 0.0
