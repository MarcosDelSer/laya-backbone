"""Tests for activities endpoint pagination metadata.

Tests verify that all list endpoints return standardized response metadata
(total, page, per_page, total_pages) as specified in task 039-3-4.
"""

from typing import Any

import pytest
from httpx import AsyncClient
from sqlalchemy.ext.asyncio import AsyncSession

from tests.conftest import MockActivity, create_activity_in_db


@pytest.mark.asyncio
async def test_list_activities_response_metadata(
    client: AsyncClient,
    db_session: AsyncSession,
    auth_headers: dict[str, str],
    sample_activities: list[MockActivity],
) -> None:
    """Test list activities endpoint returns standardized pagination metadata."""
    response = await client.get(
        "/api/v1/activities",
        params={"page": 1, "per_page": 10},
        headers=auth_headers,
    )

    assert response.status_code == 200
    data = response.json()

    # Verify all required metadata fields are present
    assert "items" in data
    assert "total" in data
    assert "page" in data
    assert "per_page" in data
    assert "total_pages" in data

    # Verify metadata values
    assert isinstance(data["items"], list)
    assert isinstance(data["total"], int)
    assert data["total"] >= 0
    assert data["page"] == 1
    assert data["per_page"] == 10
    assert isinstance(data["total_pages"], int)
    assert data["total_pages"] >= 0


@pytest.mark.asyncio
async def test_list_activities_default_pagination(
    client: AsyncClient,
    db_session: AsyncSession,
    auth_headers: dict[str, str],
    sample_activities: list[MockActivity],
) -> None:
    """Test list activities uses default pagination values."""
    response = await client.get(
        "/api/v1/activities",
        headers=auth_headers,
    )

    assert response.status_code == 200
    data = response.json()

    # Verify defaults
    assert data["page"] == 1
    assert data["per_page"] == 20


@pytest.mark.asyncio
async def test_list_activities_page_navigation(
    client: AsyncClient,
    db_session: AsyncSession,
    auth_headers: dict[str, str],
    sample_activities: list[MockActivity],
) -> None:
    """Test pagination works correctly across pages."""
    # Get first page with 2 items per page
    response1 = await client.get(
        "/api/v1/activities",
        params={"page": 1, "per_page": 2, "is_active": True},
        headers=auth_headers,
    )

    # Get second page with 2 items per page
    response2 = await client.get(
        "/api/v1/activities",
        params={"page": 2, "per_page": 2, "is_active": True},
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
    if data1["total"] > 0:
        expected_pages = (data1["total"] + 1) // 2
        assert data1["total_pages"] == expected_pages
        assert data2["total_pages"] == expected_pages


@pytest.mark.asyncio
async def test_list_activities_total_pages_calculation(
    client: AsyncClient,
    db_session: AsyncSession,
    auth_headers: dict[str, str],
    sample_activities: list[MockActivity],
) -> None:
    """Test total_pages is calculated correctly."""
    response = await client.get(
        "/api/v1/activities",
        params={"page": 1, "per_page": 10, "is_active": True},
        headers=auth_headers,
    )

    assert response.status_code == 200
    data = response.json()

    total = data["total"]
    per_page = data["per_page"]
    total_pages = data["total_pages"]

    # Verify total_pages calculation
    if total == 0:
        assert total_pages == 0
    else:
        expected_pages = (total + per_page - 1) // per_page
        assert total_pages == expected_pages


@pytest.mark.asyncio
async def test_list_activities_empty_page(
    client: AsyncClient,
    db_session: AsyncSession,
    auth_headers: dict[str, str],
    sample_activities: list[MockActivity],
) -> None:
    """Test requesting a page beyond total_pages returns empty items."""
    # First get the total pages
    response1 = await client.get(
        "/api/v1/activities",
        params={"page": 1, "per_page": 10, "is_active": True},
        headers=auth_headers,
    )

    assert response1.status_code == 200
    data1 = response1.json()
    total_pages = data1["total_pages"]

    # Request a page beyond the total
    if total_pages > 0:
        beyond_page = total_pages + 5
        response2 = await client.get(
            "/api/v1/activities",
            params={"page": beyond_page, "per_page": 10, "is_active": True},
            headers=auth_headers,
        )

        assert response2.status_code == 200
        data2 = response2.json()

        # Should return empty items but still have metadata
        assert len(data2["items"]) == 0
        assert data2["page"] == beyond_page
        assert data2["total"] == data1["total"]
        assert data2["total_pages"] == total_pages


@pytest.mark.asyncio
async def test_list_activities_per_page_validation(
    client: AsyncClient,
    auth_headers: dict[str, str],
) -> None:
    """Test per_page validation (max 100)."""
    response = await client.get(
        "/api/v1/activities",
        params={"page": 1, "per_page": 101},
        headers=auth_headers,
    )

    # Should return 422 Unprocessable Entity
    assert response.status_code == 422


@pytest.mark.asyncio
async def test_list_activities_per_page_minimum(
    client: AsyncClient,
    auth_headers: dict[str, str],
) -> None:
    """Test per_page validation (minimum 1)."""
    response = await client.get(
        "/api/v1/activities",
        params={"page": 1, "per_page": 0},
        headers=auth_headers,
    )

    # Should return 422 Unprocessable Entity
    assert response.status_code == 422


@pytest.mark.asyncio
async def test_list_activities_page_validation(
    client: AsyncClient,
    auth_headers: dict[str, str],
) -> None:
    """Test page validation (minimum 1)."""
    response = await client.get(
        "/api/v1/activities",
        params={"page": 0, "per_page": 20},
        headers=auth_headers,
    )

    # Should return 422 Unprocessable Entity
    assert response.status_code == 422


@pytest.mark.asyncio
async def test_list_activities_with_filters_metadata(
    client: AsyncClient,
    db_session: AsyncSession,
    auth_headers: dict[str, str],
    sample_activities: list[MockActivity],
) -> None:
    """Test pagination metadata is correct when filters are applied."""
    response = await client.get(
        "/api/v1/activities",
        params={
            "page": 1,
            "per_page": 10,
            "activity_type": "cognitive",
            "is_active": True,
        },
        headers=auth_headers,
    )

    assert response.status_code == 200
    data = response.json()

    # Verify metadata is present even with filters
    assert "total" in data
    assert "page" in data
    assert "per_page" in data
    assert "total_pages" in data

    # Total should reflect filtered results
    assert data["total"] >= 0


@pytest.mark.asyncio
async def test_list_activities_consistency_across_requests(
    client: AsyncClient,
    db_session: AsyncSession,
    auth_headers: dict[str, str],
    sample_activities: list[MockActivity],
) -> None:
    """Test pagination metadata is consistent across multiple requests."""
    # Make multiple requests with same params
    responses = []
    for _ in range(3):
        response = await client.get(
            "/api/v1/activities",
            params={"page": 1, "per_page": 20, "is_active": True},
            headers=auth_headers,
        )
        assert response.status_code == 200
        responses.append(response.json())

    # Verify all responses have same metadata
    for i in range(1, len(responses)):
        assert responses[i]["total"] == responses[0]["total"]
        assert responses[i]["page"] == responses[0]["page"]
        assert responses[i]["per_page"] == responses[0]["per_page"]
        assert responses[i]["total_pages"] == responses[0]["total_pages"]
