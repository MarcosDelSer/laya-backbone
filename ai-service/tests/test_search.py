"""Tests for search functionality in LAYA AI Service.

Tests full-text search across activities and other entities.
"""

from typing import Any
from uuid import UUID

import pytest
from httpx import AsyncClient
from sqlalchemy.ext.asyncio import AsyncSession

from tests.conftest import MockActivity, create_activity_in_db


@pytest.mark.asyncio
async def test_search_activities_basic(
    client: AsyncClient,
    db_session: AsyncSession,
    auth_headers: dict[str, str],
    sample_activities: list[MockActivity],
) -> None:
    """Test basic activity search with query string."""
    response = await client.get(
        "/api/v1/search",
        params={"q": "blocks", "types": ["activities"]},
        headers=auth_headers,
    )

    assert response.status_code == 200
    data = response.json()

    # Verify response structure
    assert "items" in data
    assert "total" in data
    assert "page" in data
    assert "per_page" in data
    assert "total_pages" in data
    assert "query" in data

    # Verify query is echoed back
    assert data["query"] == "blocks"

    # Verify pagination metadata
    assert data["page"] == 1
    assert data["per_page"] == 20

    # Verify we found activities with "blocks" in name or description
    assert data["total"] > 0
    assert len(data["items"]) > 0

    # Verify result structure
    result = data["items"][0]
    assert "type" in result
    assert "id" in result
    assert "title" in result
    assert "description" in result
    assert "relevance_score" in result
    assert "data" in result

    # Verify type is activity
    assert result["type"] == "activity"


@pytest.mark.asyncio
async def test_search_activities_name_match(
    client: AsyncClient,
    db_session: AsyncSession,
    auth_headers: dict[str, str],
    sample_activities: list[MockActivity],
) -> None:
    """Test search matches activity names."""
    response = await client.get(
        "/api/v1/search",
        params={"q": "Building Blocks Tower", "types": ["activities"]},
        headers=auth_headers,
    )

    assert response.status_code == 200
    data = response.json()

    # Should find the "Building Blocks Tower" activity
    assert data["total"] >= 1
    assert len(data["items"]) >= 1

    # Check that result contains correct activity
    found = False
    for item in data["items"]:
        if "Building Blocks Tower" in item["title"]:
            found = True
            assert item["type"] == "activity"
            assert item["relevance_score"] > 0
            break

    assert found, "Expected 'Building Blocks Tower' in results"


@pytest.mark.asyncio
async def test_search_activities_description_match(
    client: AsyncClient,
    db_session: AsyncSession,
    auth_headers: dict[str, str],
    sample_activities: list[MockActivity],
) -> None:
    """Test search matches activity descriptions."""
    response = await client.get(
        "/api/v1/search",
        params={"q": "motor skills", "types": ["activities"]},
        headers=auth_headers,
    )

    assert response.status_code == 200
    data = response.json()

    # Should find activities with "motor skills" in description
    assert data["total"] > 0


@pytest.mark.asyncio
async def test_search_activities_case_insensitive(
    client: AsyncClient,
    db_session: AsyncSession,
    auth_headers: dict[str, str],
    sample_activities: list[MockActivity],
) -> None:
    """Test search is case-insensitive."""
    # Search with lowercase
    response1 = await client.get(
        "/api/v1/search",
        params={"q": "blocks", "types": ["activities"]},
        headers=auth_headers,
    )

    # Search with uppercase
    response2 = await client.get(
        "/api/v1/search",
        params={"q": "BLOCKS", "types": ["activities"]},
        headers=auth_headers,
    )

    # Search with mixed case
    response3 = await client.get(
        "/api/v1/search",
        params={"q": "BlOcKs", "types": ["activities"]},
        headers=auth_headers,
    )

    assert response1.status_code == 200
    assert response2.status_code == 200
    assert response3.status_code == 200

    data1 = response1.json()
    data2 = response2.json()
    data3 = response3.json()

    # All should return same number of results
    assert data1["total"] == data2["total"]
    assert data1["total"] == data3["total"]


@pytest.mark.asyncio
async def test_search_activities_pagination(
    client: AsyncClient,
    db_session: AsyncSession,
    auth_headers: dict[str, str],
    sample_activities: list[MockActivity],
) -> None:
    """Test search pagination works correctly."""
    # Get first page with 2 items per page
    response1 = await client.get(
        "/api/v1/search",
        params={"q": "activity", "types": ["activities"], "page": 1, "per_page": 2},
        headers=auth_headers,
    )

    # Get second page with 2 items per page
    response2 = await client.get(
        "/api/v1/search",
        params={"q": "activity", "types": ["activities"], "page": 2, "per_page": 2},
        headers=auth_headers,
    )

    assert response1.status_code == 200
    assert response2.status_code == 200

    data1 = response1.json()
    data2 = response2.json()

    # Verify pagination metadata
    assert data1["page"] == 1
    assert data1["per_page"] == 2
    assert data2["page"] == 2
    assert data2["per_page"] == 2

    # Both should have same total
    assert data1["total"] == data2["total"]

    # Total pages should be calculated correctly
    expected_pages = (data1["total"] + 1) // 2
    assert data1["total_pages"] == expected_pages


@pytest.mark.asyncio
async def test_search_activities_empty_results(
    client: AsyncClient,
    db_session: AsyncSession,
    auth_headers: dict[str, str],
    sample_activities: list[MockActivity],
) -> None:
    """Test search with no matching results."""
    response = await client.get(
        "/api/v1/search",
        params={"q": "zzzznonexistentzzz", "types": ["activities"]},
        headers=auth_headers,
    )

    assert response.status_code == 200
    data = response.json()

    # Should return empty results
    assert data["total"] == 0
    assert len(data["items"]) == 0
    assert data["page"] == 1
    assert data["total_pages"] == 0


@pytest.mark.asyncio
async def test_search_activities_excludes_inactive(
    client: AsyncClient,
    db_session: AsyncSession,
    auth_headers: dict[str, str],
    sample_activities: list[MockActivity],
) -> None:
    """Test search excludes inactive activities."""
    # Search for "Inactive Test Activity" which is marked as inactive
    response = await client.get(
        "/api/v1/search",
        params={"q": "Inactive Test Activity", "types": ["activities"]},
        headers=auth_headers,
    )

    assert response.status_code == 200
    data = response.json()

    # Should not find the inactive activity
    assert data["total"] == 0


@pytest.mark.asyncio
async def test_search_all_types(
    client: AsyncClient,
    db_session: AsyncSession,
    auth_headers: dict[str, str],
    sample_activities: list[MockActivity],
) -> None:
    """Test search across all entity types."""
    response = await client.get(
        "/api/v1/search",
        params={"q": "blocks", "types": ["all"]},
        headers=auth_headers,
    )

    assert response.status_code == 200
    data = response.json()

    # Should find activities (children and coaching are placeholders)
    assert data["total"] > 0


@pytest.mark.asyncio
async def test_search_children_placeholder(
    client: AsyncClient,
    db_session: AsyncSession,
    auth_headers: dict[str, str],
) -> None:
    """Test search for children returns empty (placeholder)."""
    response = await client.get(
        "/api/v1/search",
        params={"q": "child", "types": ["children"]},
        headers=auth_headers,
    )

    assert response.status_code == 200
    data = response.json()

    # Children search is a placeholder, should return empty
    assert data["total"] == 0
    assert len(data["items"]) == 0


@pytest.mark.asyncio
async def test_search_coaching_sessions_placeholder(
    client: AsyncClient,
    db_session: AsyncSession,
    auth_headers: dict[str, str],
) -> None:
    """Test search for coaching sessions returns empty (placeholder)."""
    response = await client.get(
        "/api/v1/search",
        params={"q": "coaching", "types": ["coaching_sessions"]},
        headers=auth_headers,
    )

    assert response.status_code == 200
    data = response.json()

    # Coaching session search is a placeholder, should return empty
    assert data["total"] == 0
    assert len(data["items"]) == 0


@pytest.mark.asyncio
async def test_search_requires_auth(
    client: AsyncClient,
    db_session: AsyncSession,
    sample_activities: list[MockActivity],
) -> None:
    """Test search endpoint requires authentication."""
    response = await client.get(
        "/api/v1/search",
        params={"q": "blocks"},
    )

    # Should return 401 Unauthorized
    assert response.status_code == 401


@pytest.mark.asyncio
async def test_search_query_validation_min_length(
    client: AsyncClient,
    auth_headers: dict[str, str],
) -> None:
    """Test search query validation (minimum length)."""
    response = await client.get(
        "/api/v1/search",
        params={"q": ""},  # Empty query
        headers=auth_headers,
    )

    # Should return 422 Unprocessable Entity
    assert response.status_code == 422


@pytest.mark.asyncio
async def test_search_query_validation_max_length(
    client: AsyncClient,
    auth_headers: dict[str, str],
) -> None:
    """Test search query validation (maximum length)."""
    # Query longer than 500 characters
    long_query = "a" * 501

    response = await client.get(
        "/api/v1/search",
        params={"q": long_query},
        headers=auth_headers,
    )

    # Should return 422 Unprocessable Entity
    assert response.status_code == 422


@pytest.mark.asyncio
async def test_search_per_page_validation_max(
    client: AsyncClient,
    auth_headers: dict[str, str],
) -> None:
    """Test per_page validation (maximum 100)."""
    response = await client.get(
        "/api/v1/search",
        params={"q": "test", "per_page": 101},
        headers=auth_headers,
    )

    # Should return 422 Unprocessable Entity
    assert response.status_code == 422


@pytest.mark.asyncio
async def test_search_per_page_validation_min(
    client: AsyncClient,
    auth_headers: dict[str, str],
) -> None:
    """Test per_page validation (minimum 1)."""
    response = await client.get(
        "/api/v1/search",
        params={"q": "test", "per_page": 0},
        headers=auth_headers,
    )

    # Should return 422 Unprocessable Entity
    assert response.status_code == 422


@pytest.mark.asyncio
async def test_search_page_validation_min(
    client: AsyncClient,
    auth_headers: dict[str, str],
) -> None:
    """Test page validation (minimum 1)."""
    response = await client.get(
        "/api/v1/search",
        params={"q": "test", "page": 0},
        headers=auth_headers,
    )

    # Should return 422 Unprocessable Entity
    assert response.status_code == 422


@pytest.mark.asyncio
async def test_search_relevance_score_ordering(
    client: AsyncClient,
    db_session: AsyncSession,
    auth_headers: dict[str, str],
    sample_activities: list[MockActivity],
) -> None:
    """Test search results are ordered by relevance score."""
    response = await client.get(
        "/api/v1/search",
        params={"q": "blocks", "types": ["activities"]},
        headers=auth_headers,
    )

    assert response.status_code == 200
    data = response.json()

    if len(data["items"]) > 1:
        # Verify results are sorted by relevance (descending)
        scores = [item["relevance_score"] for item in data["items"]]
        assert scores == sorted(scores, reverse=True)


@pytest.mark.asyncio
async def test_search_result_data_completeness(
    client: AsyncClient,
    db_session: AsyncSession,
    auth_headers: dict[str, str],
    sample_activities: list[MockActivity],
) -> None:
    """Test search results include complete activity data."""
    response = await client.get(
        "/api/v1/search",
        params={"q": "blocks", "types": ["activities"]},
        headers=auth_headers,
    )

    assert response.status_code == 200
    data = response.json()

    if len(data["items"]) > 0:
        result = data["items"][0]

        # Verify result structure
        assert result["type"] == "activity"
        assert "id" in result
        assert "title" in result
        assert "description" in result
        assert "relevance_score" in result
        assert "data" in result

        # Verify activity data is complete
        activity_data = result["data"]
        assert "id" in activity_data
        assert "name" in activity_data
        assert "description" in activity_data
        assert "activity_type" in activity_data
        assert "difficulty" in activity_data
        assert "duration_minutes" in activity_data
        assert "materials_needed" in activity_data
        assert "is_active" in activity_data
        assert "created_at" in activity_data
        assert "updated_at" in activity_data


@pytest.mark.asyncio
async def test_search_special_characters(
    client: AsyncClient,
    db_session: AsyncSession,
    auth_headers: dict[str, str],
    sample_activities: list[MockActivity],
) -> None:
    """Test search handles special characters correctly."""
    response = await client.get(
        "/api/v1/search",
        params={"q": "story's time", "types": ["activities"]},
        headers=auth_headers,
    )

    # Should not crash, returns 200 even if no results
    assert response.status_code == 200


@pytest.mark.asyncio
async def test_search_default_parameters(
    client: AsyncClient,
    db_session: AsyncSession,
    auth_headers: dict[str, str],
    sample_activities: list[MockActivity],
) -> None:
    """Test search uses default parameters when not specified."""
    response = await client.get(
        "/api/v1/search",
        params={"q": "blocks"},
        headers=auth_headers,
    )

    assert response.status_code == 200
    data = response.json()

    # Verify defaults
    assert data["page"] == 1
    assert data["per_page"] == 20
