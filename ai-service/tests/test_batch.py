"""Tests for batch operation endpoints.

These tests verify that batch endpoints correctly process multiple
requests in a single HTTP call, reducing network round-trips and
improving application performance.
"""

from datetime import datetime
from typing import Any
from uuid import UUID, uuid4

import pytest
from fastapi import status
from sqlalchemy.ext.asyncio import AsyncSession

from app.models.activity import Activity, ActivityDifficulty, ActivityType
from app.schemas.batch import (
    BatchActivityRecommendationRequest,
    BatchGetRequest,
    BatchOperationStatus,
)


@pytest.fixture
async def sample_activities(db: AsyncSession) -> list[Activity]:
    """Create sample activities for testing.

    Args:
        db: Database session

    Returns:
        List of created Activity objects
    """
    activities = [
        Activity(
            name="Color Sorting",
            description="Sort colored blocks by color",
            activity_type=ActivityType.COGNITIVE,
            difficulty=ActivityDifficulty.EASY,
            duration_minutes=15,
            materials_needed=["colored blocks"],
            min_age_months=24,
            max_age_months=48,
            is_active=True,
        ),
        Activity(
            name="Story Time",
            description="Read interactive stories",
            activity_type=ActivityType.LANGUAGE,
            difficulty=ActivityDifficulty.EASY,
            duration_minutes=20,
            materials_needed=["picture books"],
            min_age_months=12,
            max_age_months=60,
            is_active=True,
        ),
        Activity(
            name="Building Blocks",
            description="Build towers and structures",
            activity_type=ActivityType.MOTOR,
            difficulty=ActivityDifficulty.MEDIUM,
            duration_minutes=25,
            materials_needed=["building blocks"],
            min_age_months=18,
            max_age_months=72,
            is_active=True,
        ),
    ]

    for activity in activities:
        db.add(activity)

    await db.commit()

    for activity in activities:
        await db.refresh(activity)

    return activities


class TestBatchGet:
    """Tests for batch GET endpoint."""

    @pytest.mark.asyncio
    async def test_batch_get_activities_success(
        self,
        client: Any,
        authenticated_headers: dict[str, str],
        sample_activities: list[Activity],
    ):
        """Test successful batch GET of activities."""
        activity_ids = [str(a.id) for a in sample_activities]

        response = await client.post(
            "/api/v1/batch/get",
            json={
                "resource_type": "activities",
                "ids": activity_ids,
            },
            headers=authenticated_headers,
        )

        assert response.status_code == status.HTTP_200_OK
        data = response.json()

        assert data["resource_type"] == "activities"
        assert data["total_requested"] == 3
        assert data["total_succeeded"] == 3
        assert data["total_failed"] == 0
        assert len(data["results"]) == 3

        # Verify all results are successful
        for result in data["results"]:
            assert result["status"] == BatchOperationStatus.SUCCESS.value
            assert result["status_code"] == 200
            assert result["data"] is not None
            assert "id" in result["data"]
            assert "name" in result["data"]

    @pytest.mark.asyncio
    async def test_batch_get_with_field_selection(
        self,
        client: Any,
        authenticated_headers: dict[str, str],
        sample_activities: list[Activity],
    ):
        """Test batch GET with field selection."""
        activity_ids = [str(sample_activities[0].id)]

        response = await client.post(
            "/api/v1/batch/get",
            json={
                "resource_type": "activities",
                "ids": activity_ids,
                "fields": "id,name",
            },
            headers=authenticated_headers,
        )

        assert response.status_code == status.HTTP_200_OK
        data = response.json()

        assert data["total_succeeded"] == 1
        result = data["results"][0]
        assert result["status"] == BatchOperationStatus.SUCCESS.value

        # Verify only requested fields are present (plus always-included id)
        result_data = result["data"]
        assert "id" in result_data
        assert "name" in result_data
        # description should not be present as it wasn't requested
        assert "description" not in result_data or result_data.get("description") is None

    @pytest.mark.asyncio
    async def test_batch_get_with_missing_resources(
        self,
        client: Any,
        authenticated_headers: dict[str, str],
        sample_activities: list[Activity],
    ):
        """Test batch GET with some missing resources."""
        valid_id = str(sample_activities[0].id)
        missing_id1 = str(uuid4())
        missing_id2 = str(uuid4())

        response = await client.post(
            "/api/v1/batch/get",
            json={
                "resource_type": "activities",
                "ids": [valid_id, missing_id1, missing_id2],
            },
            headers=authenticated_headers,
        )

        assert response.status_code == status.HTTP_200_OK
        data = response.json()

        assert data["total_requested"] == 3
        assert data["total_succeeded"] == 1
        assert data["total_failed"] == 2

        # Find the successful and failed results
        success_results = [r for r in data["results"] if r["status"] == "success"]
        error_results = [r for r in data["results"] if r["status"] == "error"]

        assert len(success_results) == 1
        assert len(error_results) == 2

        # Verify error results have proper error messages
        for error_result in error_results:
            assert error_result["status_code"] == 404
            assert "not found" in error_result["error"]

    @pytest.mark.asyncio
    async def test_batch_get_all_missing(
        self,
        client: Any,
        authenticated_headers: dict[str, str],
    ):
        """Test batch GET with all missing resources."""
        missing_ids = [str(uuid4()), str(uuid4())]

        response = await client.post(
            "/api/v1/batch/get",
            json={
                "resource_type": "activities",
                "ids": missing_ids,
            },
            headers=authenticated_headers,
        )

        assert response.status_code == status.HTTP_200_OK
        data = response.json()

        assert data["total_requested"] == 2
        assert data["total_succeeded"] == 0
        assert data["total_failed"] == 2

        # All results should be errors
        for result in data["results"]:
            assert result["status"] == BatchOperationStatus.ERROR.value
            assert result["status_code"] == 404

    @pytest.mark.asyncio
    async def test_batch_get_unsupported_resource_type(
        self,
        client: Any,
        authenticated_headers: dict[str, str],
    ):
        """Test batch GET with unsupported resource type."""
        response = await client.post(
            "/api/v1/batch/get",
            json={
                "resource_type": "unsupported_type",
                "ids": [str(uuid4())],
            },
            headers=authenticated_headers,
        )

        assert response.status_code == status.HTTP_400_BAD_REQUEST
        data = response.json()
        assert "Unsupported resource type" in data["detail"]

    @pytest.mark.asyncio
    async def test_batch_get_empty_ids(
        self,
        client: Any,
        authenticated_headers: dict[str, str],
    ):
        """Test batch GET with empty IDs list."""
        response = await client.post(
            "/api/v1/batch/get",
            json={
                "resource_type": "activities",
                "ids": [],
            },
            headers=authenticated_headers,
        )

        # Should fail validation
        assert response.status_code == status.HTTP_422_UNPROCESSABLE_ENTITY

    @pytest.mark.asyncio
    async def test_batch_get_too_many_ids(
        self,
        client: Any,
        authenticated_headers: dict[str, str],
    ):
        """Test batch GET with too many IDs (over limit)."""
        # Create 101 IDs (over the 100 limit)
        ids = [str(uuid4()) for _ in range(101)]

        response = await client.post(
            "/api/v1/batch/get",
            json={
                "resource_type": "activities",
                "ids": ids,
            },
            headers=authenticated_headers,
        )

        # Should fail validation
        assert response.status_code == status.HTTP_422_UNPROCESSABLE_ENTITY

    @pytest.mark.asyncio
    async def test_batch_get_unauthenticated(
        self,
        client: Any,
        sample_activities: list[Activity],
    ):
        """Test batch GET without authentication."""
        activity_ids = [str(a.id) for a in sample_activities]

        response = await client.post(
            "/api/v1/batch/get",
            json={
                "resource_type": "activities",
                "ids": activity_ids,
            },
        )

        assert response.status_code == status.HTTP_401_UNAUTHORIZED


class TestBatchActivityRecommendations:
    """Tests for batch activity recommendations endpoint."""

    @pytest.mark.asyncio
    async def test_batch_recommendations_success(
        self,
        client: Any,
        authenticated_headers: dict[str, str],
        sample_activities: list[Activity],
    ):
        """Test successful batch activity recommendations."""
        child_ids = [str(uuid4()), str(uuid4())]

        response = await client.post(
            "/api/v1/batch/activities/recommendations",
            json={
                "child_ids": child_ids,
                "max_recommendations": 5,
            },
            headers=authenticated_headers,
        )

        assert response.status_code == status.HTTP_200_OK
        data = response.json()

        assert data["total_requested"] == 2
        assert data["total_succeeded"] == 2
        assert data["total_failed"] == 0
        assert len(data["results"]) == 2

        # Verify all results are successful
        for result in data["results"]:
            assert result["status"] == BatchOperationStatus.SUCCESS.value
            assert result["status_code"] == 200
            assert result["data"] is not None
            assert "child_id" in result["data"]
            assert "recommendations" in result["data"]

    @pytest.mark.asyncio
    async def test_batch_recommendations_with_filters(
        self,
        client: Any,
        authenticated_headers: dict[str, str],
        sample_activities: list[Activity],
    ):
        """Test batch recommendations with filters."""
        child_ids = [str(uuid4())]

        response = await client.post(
            "/api/v1/batch/activities/recommendations",
            json={
                "child_ids": child_ids,
                "max_recommendations": 3,
                "activity_types": ["cognitive", "language"],
                "child_age_months": 36,
                "weather": "sunny",
                "group_size": 10,
                "include_special_needs": True,
            },
            headers=authenticated_headers,
        )

        assert response.status_code == status.HTTP_200_OK
        data = response.json()

        assert data["total_succeeded"] == 1
        result = data["results"][0]
        assert result["status"] == BatchOperationStatus.SUCCESS.value

        recommendations_data = result["data"]
        assert "recommendations" in recommendations_data
        # Should have at most 3 recommendations as specified
        assert len(recommendations_data["recommendations"]) <= 3

    @pytest.mark.asyncio
    async def test_batch_recommendations_multiple_children(
        self,
        client: Any,
        authenticated_headers: dict[str, str],
        sample_activities: list[Activity],
    ):
        """Test batch recommendations for multiple children."""
        # Test with 5 children
        child_ids = [str(uuid4()) for _ in range(5)]

        response = await client.post(
            "/api/v1/batch/activities/recommendations",
            json={
                "child_ids": child_ids,
                "max_recommendations": 5,
            },
            headers=authenticated_headers,
        )

        assert response.status_code == status.HTTP_200_OK
        data = response.json()

        assert data["total_requested"] == 5
        assert data["total_succeeded"] == 5
        assert data["total_failed"] == 0
        assert len(data["results"]) == 5

        # Verify each child has their own recommendations
        child_ids_set = set(child_ids)
        result_child_ids = {r["data"]["child_id"] for r in data["results"]}
        assert result_child_ids == child_ids_set

    @pytest.mark.asyncio
    async def test_batch_recommendations_empty_children(
        self,
        client: Any,
        authenticated_headers: dict[str, str],
    ):
        """Test batch recommendations with empty children list."""
        response = await client.post(
            "/api/v1/batch/activities/recommendations",
            json={
                "child_ids": [],
            },
            headers=authenticated_headers,
        )

        # Should fail validation
        assert response.status_code == status.HTTP_422_UNPROCESSABLE_ENTITY

    @pytest.mark.asyncio
    async def test_batch_recommendations_too_many_children(
        self,
        client: Any,
        authenticated_headers: dict[str, str],
    ):
        """Test batch recommendations with too many children (over limit)."""
        # Create 51 child IDs (over the 50 limit)
        child_ids = [str(uuid4()) for _ in range(51)]

        response = await client.post(
            "/api/v1/batch/activities/recommendations",
            json={
                "child_ids": child_ids,
            },
            headers=authenticated_headers,
        )

        # Should fail validation
        assert response.status_code == status.HTTP_422_UNPROCESSABLE_ENTITY

    @pytest.mark.asyncio
    async def test_batch_recommendations_unauthenticated(
        self,
        client: Any,
    ):
        """Test batch recommendations without authentication."""
        child_ids = [str(uuid4())]

        response = await client.post(
            "/api/v1/batch/activities/recommendations",
            json={
                "child_ids": child_ids,
            },
        )

        assert response.status_code == status.HTTP_401_UNAUTHORIZED


class TestBatchSchemas:
    """Tests for batch operation schemas."""

    def test_batch_get_request_validation(self):
        """Test BatchGetRequest validation."""
        # Valid request
        valid_request = BatchGetRequest(
            resource_type="activities",
            ids=[uuid4() for _ in range(5)],
            fields="id,name",
        )
        assert valid_request.resource_type == "activities"
        assert len(valid_request.ids) == 5
        assert valid_request.fields == "id,name"

        # Test with None fields (optional)
        request_no_fields = BatchGetRequest(
            resource_type="activities",
            ids=[uuid4()],
        )
        assert request_no_fields.fields is None

    def test_batch_activity_recommendation_request_validation(self):
        """Test BatchActivityRecommendationRequest validation."""
        # Valid request with all fields
        valid_request = BatchActivityRecommendationRequest(
            child_ids=[uuid4() for _ in range(3)],
            max_recommendations=10,
            activity_types=["cognitive", "motor"],
            child_age_months=36,
            weather="sunny",
            group_size=15,
            include_special_needs=True,
        )
        assert len(valid_request.child_ids) == 3
        assert valid_request.max_recommendations == 10
        assert valid_request.activity_types == ["cognitive", "motor"]

        # Test with defaults
        request_defaults = BatchActivityRecommendationRequest(
            child_ids=[uuid4()],
        )
        assert request_defaults.max_recommendations == 5
        assert request_defaults.include_special_needs is True

    def test_batch_operation_result_structure(self):
        """Test BatchOperationResult structure."""
        from app.schemas.batch import BatchOperationResult

        # Success result
        success_result = BatchOperationResult(
            id=uuid4(),
            status=BatchOperationStatus.SUCCESS,
            data={"name": "Test Activity"},
            status_code=200,
        )
        assert success_result.status == BatchOperationStatus.SUCCESS
        assert success_result.error is None
        assert success_result.data is not None

        # Error result
        error_result = BatchOperationResult(
            id=uuid4(),
            status=BatchOperationStatus.ERROR,
            error="Resource not found",
            status_code=404,
        )
        assert error_result.status == BatchOperationStatus.ERROR
        assert error_result.error == "Resource not found"
        assert error_result.data is None
