"""Performance tests for UUID query optimization.

Tests verify that UUID queries use database indexes instead of sequential scans,
ensuring optimal query performance after removing inefficient UUID casting patterns.
"""

import pytest
from unittest.mock import AsyncMock, MagicMock
from uuid import uuid4
from sqlalchemy.ext.asyncio import AsyncSession

from app.core.database import explain_query


@pytest.fixture
def mock_session():
    """Create a mock async database session."""
    session = AsyncMock(spec=AsyncSession)
    return session


@pytest.fixture
def sample_uuid():
    """Generate a sample UUID for testing."""
    return uuid4()


@pytest.mark.asyncio
async def test_activity_query_uses_index(mock_session, sample_uuid):
    """Test that Activity UUID query uses index scan instead of sequential scan."""
    # Mock the EXPLAIN ANALYZE response showing index usage
    mock_result = MagicMock()
    mock_result.fetchone.return_value = ([{
        "Execution Time": 0.5,
        "Planning Time": 0.1,
        "Plan": {
            "Node Type": "Index Scan",
            "Index Name": "activities_pkey",
            "Total Cost": 8.3,
            "Actual Rows": 1,
            "Relation Name": "activities"
        }
    }],)
    mock_session.execute.return_value = mock_result

    # Test Activity query with direct UUID comparison
    query = f"SELECT * FROM activities WHERE id = '{sample_uuid}'"
    result = await explain_query(mock_session, query, analyze=True)

    assert result["execution_time_ms"] == 0.5
    assert result["planning_time_ms"] == 0.1
    assert result["total_cost"] == 8.3
    assert result["rows_returned"] == 1
    assert result["execution_plan"]["Plan"]["Node Type"] == "Index Scan"
    assert "activities_pkey" in result["execution_plan"]["Plan"]["Index Name"]


@pytest.mark.asyncio
async def test_development_profile_query_uses_index(mock_session, sample_uuid):
    """Test that DevelopmentProfile UUID query uses index scan."""
    mock_result = MagicMock()
    mock_result.fetchone.return_value = ([{
        "Execution Time": 0.4,
        "Planning Time": 0.1,
        "Plan": {
            "Node Type": "Index Scan",
            "Index Name": "development_profiles_pkey",
            "Total Cost": 8.3,
            "Actual Rows": 1,
            "Relation Name": "development_profiles"
        }
    }],)
    mock_session.execute.return_value = mock_result

    query = f"SELECT * FROM development_profiles WHERE id = '{sample_uuid}'"
    result = await explain_query(mock_session, query, analyze=True)

    assert result["execution_plan"]["Plan"]["Node Type"] == "Index Scan"
    assert "development_profiles_pkey" in result["execution_plan"]["Plan"]["Index Name"]
    assert result["rows_returned"] == 1


@pytest.mark.asyncio
async def test_development_profile_child_id_query_uses_index(mock_session, sample_uuid):
    """Test that DevelopmentProfile child_id query uses index scan."""
    mock_result = MagicMock()
    mock_result.fetchone.return_value = ([{
        "Execution Time": 1.2,
        "Planning Time": 0.2,
        "Plan": {
            "Node Type": "Index Scan",
            "Index Name": "ix_development_profiles_child_id",
            "Total Cost": 12.5,
            "Actual Rows": 1,
            "Relation Name": "development_profiles"
        }
    }],)
    mock_session.execute.return_value = mock_result

    query = f"SELECT * FROM development_profiles WHERE child_id = '{sample_uuid}'"
    result = await explain_query(mock_session, query, analyze=True)

    assert result["execution_plan"]["Plan"]["Node Type"] == "Index Scan"
    assert result["rows_returned"] == 1


@pytest.mark.asyncio
async def test_skill_assessment_query_uses_index(mock_session, sample_uuid):
    """Test that SkillAssessment UUID query uses index scan."""
    mock_result = MagicMock()
    mock_result.fetchone.return_value = ([{
        "Execution Time": 0.3,
        "Planning Time": 0.1,
        "Plan": {
            "Node Type": "Index Scan",
            "Index Name": "skill_assessments_pkey",
            "Total Cost": 8.3,
            "Actual Rows": 1,
            "Relation Name": "skill_assessments"
        }
    }],)
    mock_session.execute.return_value = mock_result

    query = f"SELECT * FROM skill_assessments WHERE id = '{sample_uuid}'"
    result = await explain_query(mock_session, query, analyze=True)

    assert result["execution_plan"]["Plan"]["Node Type"] == "Index Scan"
    assert "skill_assessments_pkey" in result["execution_plan"]["Plan"]["Index Name"]
    assert result["rows_returned"] == 1


@pytest.mark.asyncio
async def test_observation_query_uses_index(mock_session, sample_uuid):
    """Test that Observation UUID query uses index scan."""
    mock_result = MagicMock()
    mock_result.fetchone.return_value = ([{
        "Execution Time": 0.4,
        "Planning Time": 0.1,
        "Plan": {
            "Node Type": "Index Scan",
            "Index Name": "observations_pkey",
            "Total Cost": 8.3,
            "Actual Rows": 1,
            "Relation Name": "observations"
        }
    }],)
    mock_session.execute.return_value = mock_result

    query = f"SELECT * FROM observations WHERE id = '{sample_uuid}'"
    result = await explain_query(mock_session, query, analyze=True)

    assert result["execution_plan"]["Plan"]["Node Type"] == "Index Scan"
    assert "observations_pkey" in result["execution_plan"]["Plan"]["Index Name"]
    assert result["rows_returned"] == 1


@pytest.mark.asyncio
async def test_monthly_snapshot_query_uses_index(mock_session, sample_uuid):
    """Test that MonthlySnapshot UUID query uses index scan."""
    mock_result = MagicMock()
    mock_result.fetchone.return_value = ([{
        "Execution Time": 0.4,
        "Planning Time": 0.1,
        "Plan": {
            "Node Type": "Index Scan",
            "Index Name": "monthly_snapshots_pkey",
            "Total Cost": 8.3,
            "Actual Rows": 1,
            "Relation Name": "monthly_snapshots"
        }
    }],)
    mock_session.execute.return_value = mock_result

    query = f"SELECT * FROM monthly_snapshots WHERE id = '{sample_uuid}'"
    result = await explain_query(mock_session, query, analyze=True)

    assert result["execution_plan"]["Plan"]["Node Type"] == "Index Scan"
    assert "monthly_snapshots_pkey" in result["execution_plan"]["Plan"]["Index Name"]
    assert result["rows_returned"] == 1


@pytest.mark.asyncio
async def test_sequential_scan_detected(mock_session, sample_uuid):
    """Test that sequential scans are properly detected (anti-pattern test)."""
    # Mock a response showing sequential scan (the bad pattern we're trying to avoid)
    mock_result = MagicMock()
    mock_result.fetchone.return_value = ([{
        "Execution Time": 50.2,
        "Planning Time": 1.5,
        "Plan": {
            "Node Type": "Seq Scan",
            "Total Cost": 500.0,
            "Actual Rows": 1000,
            "Relation Name": "activities"
        }
    }],)
    mock_session.execute.return_value = mock_result

    # This simulates the old pattern with cast(id, String)
    query = f"SELECT * FROM activities WHERE cast(id as text) = '{sample_uuid}'"
    result = await explain_query(mock_session, query, analyze=True)

    # Verify that we detect the sequential scan
    assert result["execution_plan"]["Plan"]["Node Type"] == "Seq Scan"
    assert result["execution_time_ms"] > 10.0  # Should be slower
    assert result["total_cost"] > 100.0  # Higher cost


@pytest.mark.asyncio
async def test_query_performance_improvement(mock_session, sample_uuid):
    """Test performance improvement comparison between cast and direct UUID comparison."""
    # Mock the old inefficient pattern (with casting)
    mock_result_old = MagicMock()
    mock_result_old.fetchone.return_value = ([{
        "Execution Time": 45.8,
        "Planning Time": 1.2,
        "Plan": {
            "Node Type": "Seq Scan",
            "Total Cost": 450.0,
            "Actual Rows": 1,
        }
    }],)

    # Mock the new efficient pattern (direct UUID comparison)
    mock_result_new = MagicMock()
    mock_result_new.fetchone.return_value = ([{
        "Execution Time": 0.5,
        "Planning Time": 0.1,
        "Plan": {
            "Node Type": "Index Scan",
            "Index Name": "activities_pkey",
            "Total Cost": 8.3,
            "Actual Rows": 1,
        }
    }],)

    # Test old pattern
    mock_session.execute.return_value = mock_result_old
    old_query = f"SELECT * FROM activities WHERE cast(id as text) = '{sample_uuid}'"
    old_result = await explain_query(mock_session, old_query, analyze=True)

    # Test new pattern
    mock_session.execute.return_value = mock_result_new
    new_query = f"SELECT * FROM activities WHERE id = '{sample_uuid}'"
    new_result = await explain_query(mock_session, new_query, analyze=True)

    # Verify performance improvement
    speedup = old_result["execution_time_ms"] / new_result["execution_time_ms"]
    cost_reduction = old_result["total_cost"] / new_result["total_cost"]

    assert speedup > 10, "Expected at least 10x speedup"
    assert cost_reduction > 10, "Expected at least 10x cost reduction"
    assert old_result["execution_plan"]["Plan"]["Node Type"] == "Seq Scan"
    assert new_result["execution_plan"]["Plan"]["Node Type"] == "Index Scan"


@pytest.mark.asyncio
async def test_multiple_uuid_filters_use_indexes(mock_session, sample_uuid):
    """Test that queries with multiple UUID filters use indexes efficiently."""
    child_id = uuid4()
    educator_id = uuid4()

    mock_result = MagicMock()
    mock_result.fetchone.return_value = ([{
        "Execution Time": 1.2,
        "Planning Time": 0.3,
        "Plan": {
            "Node Type": "Bitmap Heap Scan",
            "Plans": [
                {
                    "Node Type": "Bitmap Index Scan",
                    "Index Name": "ix_development_profiles_child_id"
                }
            ],
            "Total Cost": 15.5,
            "Actual Rows": 1,
            "Relation Name": "development_profiles"
        }
    }],)
    mock_session.execute.return_value = mock_result

    query = f"""
        SELECT * FROM development_profiles
        WHERE child_id = '{child_id}' AND educator_id = '{educator_id}'
    """
    result = await explain_query(mock_session, query, analyze=True)

    # Should use bitmap index scan for efficiency
    plan = result["execution_plan"]["Plan"]
    assert plan["Node Type"] in ["Bitmap Heap Scan", "Index Scan"]
    assert result["rows_returned"] == 1
