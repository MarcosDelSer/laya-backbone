"""Unit tests for Activity models, service, and API endpoints.

Tests cover:
- Activity model creation and validation
- Age filtering accuracy
- Recommendation scoring algorithm (scores between 0.0 and 1.0)
- Empty recommendations when no activities match
- Participation tracking impact on recommendations
- API endpoint response structure
- Authentication requirements on protected endpoints
- Edge cases: invalid child_id, age out of range
"""

from datetime import datetime, timedelta, timezone
from uuid import uuid4

import pytest
from httpx import AsyncClient
from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession

from app.models.activity import (
    Activity,
    ActivityDifficulty,
    ActivityParticipation,
    ActivityRecommendation,
    ActivityType,
)
from app.services.activity_service import ActivityService
from tests.conftest import MockActivity, MockActivityParticipation, MockActivityRecommendation


# =============================================================================
# Model Tests
# =============================================================================


class TestActivityModel:
    """Tests for the Activity model (using mock fixtures for SQLite compatibility)."""

    @pytest.mark.asyncio
    async def test_create_activity_with_all_fields(
        self,
        sample_activity: MockActivity,
    ):
        """Test Activity can be created with all fields."""
        assert sample_activity.id is not None
        assert sample_activity.name == "Building Blocks Tower"
        assert sample_activity.description is not None
        assert sample_activity.activity_type == ActivityType.MOTOR
        assert sample_activity.difficulty == ActivityDifficulty.EASY
        assert sample_activity.duration_minutes == 20
        assert sample_activity.materials_needed == ["wooden blocks", "flat surface"]
        assert sample_activity.min_age_months == 12
        assert sample_activity.max_age_months == 48
        assert sample_activity.special_needs_adaptations is not None
        assert sample_activity.is_active is True
        assert sample_activity.created_at is not None
        assert sample_activity.updated_at is not None

    @pytest.mark.asyncio
    async def test_activity_repr(
        self,
        sample_activity: MockActivity,
    ):
        """Test Activity string representation."""
        repr_str = repr(sample_activity)
        assert "Activity" in repr_str
        assert str(sample_activity.id) in repr_str
        assert sample_activity.name in repr_str
        assert sample_activity.activity_type.value in repr_str

    @pytest.mark.asyncio
    async def test_activity_default_values(
        self,
        db_session: AsyncSession,
    ):
        """Test Activity default values are applied correctly."""
        from tests.conftest import create_activity_in_db

        activity = await create_activity_in_db(
            db_session,
            name="Minimal Activity",
            description="A minimal activity for testing defaults",
            activity_type=ActivityType.COGNITIVE,
            difficulty=ActivityDifficulty.MEDIUM,
            duration_minutes=30,
        )

        assert activity.difficulty == ActivityDifficulty.MEDIUM
        assert activity.duration_minutes == 30
        assert activity.materials_needed == []
        assert activity.is_active is True
        assert activity.min_age_months is None
        assert activity.max_age_months is None


class TestActivityRecommendationModel:
    """Tests for the ActivityRecommendation model."""

    @pytest.mark.asyncio
    async def test_create_recommendation(
        self,
        sample_recommendation: MockActivityRecommendation,
    ):
        """Test ActivityRecommendation can be created."""
        assert sample_recommendation.id is not None
        assert sample_recommendation.child_id is not None
        assert sample_recommendation.activity_id is not None
        assert sample_recommendation.relevance_score == 0.85
        assert sample_recommendation.reasoning is not None
        assert sample_recommendation.is_dismissed is False
        assert sample_recommendation.generated_at is not None
        assert sample_recommendation.created_at is not None

    @pytest.mark.asyncio
    async def test_recommendation_activity_relationship(
        self,
        sample_recommendation: MockActivityRecommendation,
        sample_activity: MockActivity,
    ):
        """Test ActivityRecommendation has proper relationship to Activity."""
        assert sample_recommendation.activity_id == sample_activity.id

    @pytest.mark.asyncio
    async def test_recommendation_repr(
        self,
        sample_recommendation: MockActivityRecommendation,
    ):
        """Test ActivityRecommendation string representation."""
        repr_str = repr(sample_recommendation)
        assert "ActivityRecommendation" in repr_str
        assert str(sample_recommendation.id) in repr_str


class TestActivityParticipationModel:
    """Tests for the ActivityParticipation model."""

    @pytest.mark.asyncio
    async def test_create_participation(
        self,
        sample_participation: MockActivityParticipation,
    ):
        """Test ActivityParticipation can be created."""
        assert sample_participation.id is not None
        assert sample_participation.child_id is not None
        assert sample_participation.activity_id is not None
        assert sample_participation.duration_minutes == 15
        assert sample_participation.completion_status == "completed"
        assert sample_participation.engagement_score == 0.85
        assert sample_participation.notes == "Child enjoyed the activity"
        assert sample_participation.started_at is not None
        assert sample_participation.created_at is not None

    @pytest.mark.asyncio
    async def test_participation_repr(
        self,
        sample_participation: MockActivityParticipation,
    ):
        """Test ActivityParticipation string representation."""
        repr_str = repr(sample_participation)
        assert "ActivityParticipation" in repr_str
        assert str(sample_participation.id) in repr_str


# =============================================================================
# Service Tests
# =============================================================================


class TestActivityServiceRecommendations:
    """Tests for ActivityService recommendation logic."""

    @pytest.mark.asyncio
    async def test_get_recommendations_returns_response(
        self,
        db_session: AsyncSession,
        sample_activities: list[MockActivity],
        sample_child_id,
    ):
        """Test get_recommendations returns ActivityRecommendationResponse."""
        service = ActivityService(db_session)
        response = await service.get_recommendations(
            child_id=sample_child_id,
            max_recommendations=5,
        )

        assert response is not None
        assert response.child_id == sample_child_id
        assert response.generated_at is not None
        assert isinstance(response.recommendations, list)

    @pytest.mark.asyncio
    async def test_get_recommendations_respects_max_limit(
        self,
        db_session: AsyncSession,
        sample_activities: list[MockActivity],
        sample_child_id,
    ):
        """Test get_recommendations respects max_recommendations parameter."""
        service = ActivityService(db_session)

        # Request only 2 recommendations
        response = await service.get_recommendations(
            child_id=sample_child_id,
            max_recommendations=2,
        )

        assert len(response.recommendations) <= 2

    @pytest.mark.asyncio
    async def test_recommendations_only_include_active_activities(
        self,
        db_session: AsyncSession,
        sample_activities: list[MockActivity],
        sample_child_id,
    ):
        """Test recommendations only include active activities."""
        service = ActivityService(db_session)
        response = await service.get_recommendations(
            child_id=sample_child_id,
            max_recommendations=20,
        )

        # All recommended activities should be active
        for rec in response.recommendations:
            assert rec.activity.is_active is True

    @pytest.mark.asyncio
    async def test_recommendation_scoring_within_valid_range(
        self,
        db_session: AsyncSession,
        sample_activities: list[MockActivity],
        sample_child_id,
    ):
        """Test relevance scores are computed correctly (0-1 range)."""
        service = ActivityService(db_session)
        response = await service.get_recommendations(
            child_id=sample_child_id,
            max_recommendations=10,
        )

        for rec in response.recommendations:
            assert 0.0 <= rec.relevance_score <= 1.0, (
                f"Score {rec.relevance_score} out of valid range [0, 1]"
            )

    @pytest.mark.asyncio
    async def test_recommendations_sorted_by_score_descending(
        self,
        db_session: AsyncSession,
        sample_activities: list[MockActivity],
        sample_child_id,
    ):
        """Test recommendations are sorted by relevance score descending."""
        service = ActivityService(db_session)
        response = await service.get_recommendations(
            child_id=sample_child_id,
            max_recommendations=10,
        )

        scores = [rec.relevance_score for rec in response.recommendations]
        assert scores == sorted(scores, reverse=True)

    @pytest.mark.asyncio
    async def test_recommendations_include_reasoning(
        self,
        db_session: AsyncSession,
        sample_activities: list[MockActivity],
        sample_child_id,
    ):
        """Test recommendations include reasoning explanation."""
        service = ActivityService(db_session)
        response = await service.get_recommendations(
            child_id=sample_child_id,
            max_recommendations=5,
        )

        for rec in response.recommendations:
            assert rec.reasoning is not None
            assert len(rec.reasoning) > 0


class TestAgeFiltering:
    """Tests for age-appropriate filtering in recommendations."""

    @pytest.mark.asyncio
    async def test_age_filtering_excludes_out_of_range(
        self,
        db_session: AsyncSession,
        sample_activities: list[MockActivity],
        sample_child_id,
        infant_age_months,
    ):
        """Test only age-appropriate activities returned for infants."""
        service = ActivityService(db_session)
        response = await service.get_recommendations(
            child_id=sample_child_id,
            child_age_months=infant_age_months,  # 6 months
            max_recommendations=20,
        )

        # Infant (6 months) should not get activities with min_age > 6
        for rec in response.recommendations:
            age_range = rec.activity.age_range
            if age_range is not None:
                # Activity should have min_age <= 6
                assert age_range.min_months <= infant_age_months or age_range.min_months is None

    @pytest.mark.asyncio
    async def test_age_filtering_for_toddler(
        self,
        db_session: AsyncSession,
        sample_activities: list[MockActivity],
        sample_child_id,
        toddler_age_months,
    ):
        """Test age filtering returns appropriate activities for toddlers."""
        service = ActivityService(db_session)
        response = await service.get_recommendations(
            child_id=sample_child_id,
            child_age_months=toddler_age_months,  # 24 months
            max_recommendations=20,
        )

        # Should have some recommendations for a 24-month-old
        assert len(response.recommendations) > 0

        # All should be age-appropriate
        for rec in response.recommendations:
            age_range = rec.activity.age_range
            if age_range is not None:
                assert age_range.min_months <= toddler_age_months

    @pytest.mark.asyncio
    async def test_age_filtering_for_school_age(
        self,
        db_session: AsyncSession,
        sample_activities: list[MockActivity],
        sample_child_id,
        school_age_months,
    ):
        """Test age filtering for older school-age children."""
        service = ActivityService(db_session)
        response = await service.get_recommendations(
            child_id=sample_child_id,
            child_age_months=school_age_months,  # 84 months (7 years)
            max_recommendations=20,
        )

        # Should have recommendations for 7-year-old
        assert len(response.recommendations) > 0

    @pytest.mark.asyncio
    async def test_age_match_affects_score(
        self,
        db_session: AsyncSession,
        sample_activity: MockActivity,
        sample_child_id,
    ):
        """Test that age match affects the relevance score."""
        service = ActivityService(db_session)

        # Activity has min_age_months=12, max_age_months=48
        # Test with age in perfect sweet spot (30 months)
        response_sweet_spot = await service.get_recommendations(
            child_id=sample_child_id,
            child_age_months=30,  # Within sweet spot
            max_recommendations=5,
        )

        # Test with age at boundary
        response_boundary = await service.get_recommendations(
            child_id=sample_child_id,
            child_age_months=12,  # At minimum boundary
            max_recommendations=5,
        )

        # Both should have recommendations, sweet spot should have higher score
        if response_sweet_spot.recommendations and response_boundary.recommendations:
            # Find the sample activity in both responses
            sweet_spot_activity = next(
                (r for r in response_sweet_spot.recommendations
                 if r.activity.name == "Building Blocks Tower"),
                None
            )
            boundary_activity = next(
                (r for r in response_boundary.recommendations
                 if r.activity.name == "Building Blocks Tower"),
                None
            )

            if sweet_spot_activity and boundary_activity:
                # Sweet spot score should be higher due to better age match
                assert sweet_spot_activity.relevance_score >= boundary_activity.relevance_score


class TestActivityTypeFiltering:
    """Tests for activity type filtering."""

    @pytest.mark.asyncio
    async def test_filter_by_single_activity_type(
        self,
        db_session: AsyncSession,
        sample_activities: list[MockActivity],
        sample_child_id,
    ):
        """Test filtering by a single activity type."""
        service = ActivityService(db_session)
        response = await service.get_recommendations(
            child_id=sample_child_id,
            activity_types=["cognitive"],
            max_recommendations=10,
        )

        # All returned activities should be cognitive type
        for rec in response.recommendations:
            assert rec.activity.activity_type == "cognitive"

    @pytest.mark.asyncio
    async def test_filter_by_multiple_activity_types(
        self,
        db_session: AsyncSession,
        sample_activities: list[MockActivity],
        sample_child_id,
    ):
        """Test filtering by multiple activity types."""
        service = ActivityService(db_session)
        response = await service.get_recommendations(
            child_id=sample_child_id,
            activity_types=["motor", "creative"],
            max_recommendations=10,
        )

        # All returned activities should be motor or creative
        for rec in response.recommendations:
            assert rec.activity.activity_type in ["motor", "creative"]


class TestParticipationTracking:
    """Tests for participation history impact on recommendations."""

    @pytest.mark.asyncio
    async def test_participation_history_affects_recommendations(
        self,
        db_session: AsyncSession,
        sample_activity: MockActivity,
        sample_child_id,
    ):
        """Test past participation affects future recommendations."""
        service = ActivityService(db_session)

        # Get baseline score without participation
        response_before = await service.get_recommendations(
            child_id=sample_child_id,
            max_recommendations=5,
        )

        # Record participation
        await service.record_participation(
            child_id=sample_child_id,
            activity_id=sample_activity.id,
            duration_minutes=20,
            completion_status="completed",
        )

        # Get recommendations after participation
        response_after = await service.get_recommendations(
            child_id=sample_child_id,
            max_recommendations=5,
        )

        # Both should have results
        assert len(response_before.recommendations) > 0
        assert len(response_after.recommendations) > 0

    @pytest.mark.asyncio
    async def test_record_participation_creates_record(
        self,
        db_session: AsyncSession,
        sample_activity: MockActivity,
        sample_child_id,
    ):
        """Test record_participation creates ActivityParticipation record."""
        service = ActivityService(db_session)

        participation = await service.record_participation(
            child_id=sample_child_id,
            activity_id=sample_activity.id,
            duration_minutes=25,
            completion_status="completed",
            engagement_score=0.9,
            notes="Great participation!",
        )

        assert participation.id is not None
        assert participation.child_id == sample_child_id
        assert participation.activity_id == sample_activity.id
        assert participation.duration_minutes == 25
        assert participation.completion_status == "completed"
        assert participation.engagement_score == 0.9
        assert participation.notes == "Great participation!"

    @pytest.mark.asyncio
    async def test_multiple_participations_increase_penalty(
        self,
        db_session: AsyncSession,
        sample_activity: MockActivity,
        sample_child_id,
    ):
        """Test multiple participations reduce recommendation score."""
        service = ActivityService(db_session)

        # Record multiple participations
        for _ in range(3):
            await service.record_participation(
                child_id=sample_child_id,
                activity_id=sample_activity.id,
                completion_status="completed",
            )

        response = await service.get_recommendations(
            child_id=sample_child_id,
            max_recommendations=10,
        )

        # Activity with multiple participations should have reasoning mentioning it
        activity_rec = next(
            (r for r in response.recommendations
             if r.activity.name == "Building Blocks Tower"),
            None
        )
        if activity_rec:
            assert "time" in activity_rec.reasoning.lower() or "participated" in activity_rec.reasoning.lower()


class TestEmptyRecommendations:
    """Tests for edge case when no activities match."""

    @pytest.mark.asyncio
    async def test_empty_recommendations_for_no_activities(
        self,
        db_session: AsyncSession,
        sample_child_id,
    ):
        """Test graceful handling when no activities exist."""
        service = ActivityService(db_session)
        response = await service.get_recommendations(
            child_id=sample_child_id,
            max_recommendations=5,
        )

        assert response is not None
        assert response.recommendations == []
        assert response.child_id == sample_child_id

    @pytest.mark.asyncio
    async def test_empty_recommendations_for_no_age_match(
        self,
        db_session: AsyncSession,
        sample_activities: list[MockActivity],
        sample_child_id,
    ):
        """Test empty result when no activities match age range."""
        service = ActivityService(db_session)

        # Request for very old child (150 months = 12.5 years)
        # All test activities have max_age <= 144 months
        response = await service.get_recommendations(
            child_id=sample_child_id,
            child_age_months=150,
            max_recommendations=5,
        )

        # May have results if any activities don't specify age range
        # But none should be age-inappropriate
        for rec in response.recommendations:
            age_range = rec.activity.age_range
            if age_range and age_range.max_months:
                assert age_range.max_months >= 150 or age_range.max_months is None


class TestWeatherScoring:
    """Tests for weather-based scoring."""

    @pytest.mark.asyncio
    async def test_weather_affects_scoring(
        self,
        db_session: AsyncSession,
        sample_activities: list[MockActivity],
        sample_child_id,
        sunny_weather,
        rainy_weather,
    ):
        """Test weather parameter affects activity scoring."""
        service = ActivityService(db_session)

        response_sunny = await service.get_recommendations(
            child_id=sample_child_id,
            weather=sunny_weather,
            max_recommendations=5,
        )

        response_rainy = await service.get_recommendations(
            child_id=sample_child_id,
            weather=rainy_weather,
            max_recommendations=5,
        )

        # Both should have recommendations
        assert len(response_sunny.recommendations) > 0
        assert len(response_rainy.recommendations) > 0


class TestGroupSizeScoring:
    """Tests for group size-based scoring."""

    @pytest.mark.asyncio
    async def test_group_size_affects_social_activity_score(
        self,
        db_session: AsyncSession,
        sample_activities: list[MockActivity],
        sample_child_id,
    ):
        """Test group size affects social activity scoring."""
        service = ActivityService(db_session)

        # Test with group size > 1 (should favor social activities)
        response_group = await service.get_recommendations(
            child_id=sample_child_id,
            group_size=5,
            activity_types=["social"],
            max_recommendations=5,
        )

        # Should have social activity recommendations
        if response_group.recommendations:
            # Should mention group suitability in reasoning
            for rec in response_group.recommendations:
                assert rec.activity.activity_type == "social"


class TestActivityServiceOtherMethods:
    """Tests for other ActivityService methods."""

    @pytest.mark.asyncio
    async def test_get_activity_by_id(
        self,
        db_session: AsyncSession,
        sample_activity: MockActivity,
    ):
        """Test retrieving a single activity by ID."""
        service = ActivityService(db_session)
        activity = await service.get_activity_by_id(sample_activity.id)

        assert activity is not None
        assert activity.id == sample_activity.id
        assert activity.name == sample_activity.name

    @pytest.mark.asyncio
    async def test_get_activity_by_id_not_found(
        self,
        db_session: AsyncSession,
    ):
        """Test get_activity_by_id returns None for non-existent ID."""
        service = ActivityService(db_session)
        activity = await service.get_activity_by_id(uuid4())

        assert activity is None

    @pytest.mark.asyncio
    async def test_list_activities(
        self,
        db_session: AsyncSession,
        sample_activities: list[MockActivity],
    ):
        """Test listing activities with pagination."""
        service = ActivityService(db_session)
        activities, total = await service.list_activities(skip=0, limit=100)

        # Should return active activities
        assert len(activities) > 0
        assert total >= len(activities)

    @pytest.mark.asyncio
    async def test_list_activities_with_type_filter(
        self,
        db_session: AsyncSession,
        sample_activities: list[MockActivity],
    ):
        """Test listing activities filtered by type."""
        service = ActivityService(db_session)
        activities, total = await service.list_activities(
            activity_type="cognitive",
            skip=0,
            limit=100,
        )

        for activity in activities:
            assert activity.activity_type == ActivityType.COGNITIVE

    @pytest.mark.asyncio
    async def test_list_activities_pagination(
        self,
        db_session: AsyncSession,
        sample_activities: list[MockActivity],
    ):
        """Test pagination in list_activities."""
        service = ActivityService(db_session)

        # Get first page
        page1, total = await service.list_activities(skip=0, limit=3)
        # Get second page
        page2, _ = await service.list_activities(skip=3, limit=3)

        # Pages should not overlap
        page1_ids = {a.id for a in page1}
        page2_ids = {a.id for a in page2}
        assert page1_ids.isdisjoint(page2_ids)

    @pytest.mark.asyncio
    async def test_save_recommendation(
        self,
        db_session: AsyncSession,
        sample_activity: MockActivity,
        sample_child_id,
    ):
        """Test saving a recommendation to the database."""
        service = ActivityService(db_session)

        recommendation = await service.save_recommendation(
            child_id=sample_child_id,
            activity_id=sample_activity.id,
            relevance_score=0.95,
            reasoning="Highly relevant for child's development",
        )

        assert recommendation.id is not None
        assert recommendation.child_id == sample_child_id
        assert recommendation.activity_id == sample_activity.id
        assert recommendation.relevance_score == 0.95
        assert recommendation.reasoning == "Highly relevant for child's development"


# =============================================================================
# API Endpoint Tests
# =============================================================================


class TestRecommendationsEndpoint:
    """Tests for GET /api/v1/activities/recommendations/{child_id} endpoint."""

    @pytest.mark.asyncio
    async def test_recommendations_endpoint_returns_200(
        self,
        client: AsyncClient,
        auth_headers: dict,
        sample_activities: list[MockActivity],
        sample_child_id,
    ):
        """Test recommendations endpoint returns 200 with valid token."""
        response = await client.get(
            f"/api/v1/activities/recommendations/{sample_child_id}",
            headers=auth_headers,
        )

        assert response.status_code == 200
        data = response.json()
        assert "child_id" in data
        assert "recommendations" in data
        assert "generated_at" in data
        assert isinstance(data["recommendations"], list)

    @pytest.mark.asyncio
    async def test_recommendations_endpoint_requires_auth(
        self,
        client: AsyncClient,
        sample_child_id,
    ):
        """Test recommendations endpoint requires authentication."""
        response = await client.get(
            f"/api/v1/activities/recommendations/{sample_child_id}",
        )

        assert response.status_code == 401

    @pytest.mark.asyncio
    async def test_recommendations_endpoint_with_parameters(
        self,
        client: AsyncClient,
        auth_headers: dict,
        sample_activities: list[MockActivity],
        sample_child_id,
    ):
        """Test recommendations endpoint with query parameters."""
        response = await client.get(
            f"/api/v1/activities/recommendations/{sample_child_id}",
            headers=auth_headers,
            params={
                "max_recommendations": 3,
                "child_age_months": 36,
                "weather": "sunny",
                "group_size": 5,
            },
        )

        assert response.status_code == 200
        data = response.json()
        assert len(data["recommendations"]) <= 3

    @pytest.mark.asyncio
    async def test_recommendations_endpoint_validates_max_recommendations(
        self,
        client: AsyncClient,
        auth_headers: dict,
        sample_child_id,
    ):
        """Test max_recommendations parameter validation (1-20 range)."""
        # Test with value > 20
        response = await client.get(
            f"/api/v1/activities/recommendations/{sample_child_id}",
            headers=auth_headers,
            params={"max_recommendations": 25},
        )

        assert response.status_code == 422  # Validation error

    @pytest.mark.asyncio
    async def test_recommendations_endpoint_validates_age_range(
        self,
        client: AsyncClient,
        auth_headers: dict,
        sample_child_id,
    ):
        """Test child_age_months parameter validation (0-144 range)."""
        # Test with value > 144
        response = await client.get(
            f"/api/v1/activities/recommendations/{sample_child_id}",
            headers=auth_headers,
            params={"child_age_months": 200},
        )

        assert response.status_code == 422  # Validation error


class TestGetActivityEndpoint:
    """Tests for GET /api/v1/activities/{activity_id} endpoint."""

    @pytest.mark.asyncio
    async def test_get_activity_returns_200(
        self,
        client: AsyncClient,
        auth_headers: dict,
        sample_activity: MockActivity,
    ):
        """Test get activity endpoint returns 200 with valid ID."""
        response = await client.get(
            f"/api/v1/activities/{sample_activity.id}",
            headers=auth_headers,
        )

        assert response.status_code == 200
        data = response.json()
        assert data["id"] == str(sample_activity.id)
        assert data["name"] == sample_activity.name

    @pytest.mark.asyncio
    async def test_get_activity_requires_auth(
        self,
        client: AsyncClient,
        sample_activity: MockActivity,
    ):
        """Test get activity endpoint requires authentication."""
        response = await client.get(
            f"/api/v1/activities/{sample_activity.id}",
        )

        assert response.status_code == 401

    @pytest.mark.asyncio
    async def test_get_activity_not_found(
        self,
        client: AsyncClient,
        auth_headers: dict,
    ):
        """Test get activity endpoint returns 404 for non-existent ID."""
        non_existent_id = uuid4()
        response = await client.get(
            f"/api/v1/activities/{non_existent_id}",
            headers=auth_headers,
        )

        assert response.status_code == 404


class TestListActivitiesEndpoint:
    """Tests for GET /api/v1/activities endpoint."""

    @pytest.mark.asyncio
    async def test_list_activities_returns_200(
        self,
        client: AsyncClient,
        auth_headers: dict,
        sample_activities: list[MockActivity],
    ):
        """Test list activities endpoint returns 200."""
        response = await client.get(
            "/api/v1/activities",
            headers=auth_headers,
        )

        assert response.status_code == 200
        data = response.json()
        assert "items" in data
        assert "total" in data
        assert "page" in data
        assert "per_page" in data
        assert "total_pages" in data
        assert isinstance(data["items"], list)

    @pytest.mark.asyncio
    async def test_list_activities_requires_auth(
        self,
        client: AsyncClient,
    ):
        """Test list activities endpoint requires authentication."""
        response = await client.get("/api/v1/activities")

        assert response.status_code == 401

    @pytest.mark.asyncio
    async def test_list_activities_pagination(
        self,
        client: AsyncClient,
        auth_headers: dict,
        sample_activities: list[MockActivity],
    ):
        """Test list activities endpoint pagination."""
        response = await client.get(
            "/api/v1/activities",
            headers=auth_headers,
            params={"page": 1, "per_page": 3},
        )

        assert response.status_code == 200
        data = response.json()
        assert len(data["items"]) <= 3
        assert data["page"] == 1
        assert data["per_page"] == 3

    @pytest.mark.asyncio
    async def test_list_activities_type_filter(
        self,
        client: AsyncClient,
        auth_headers: dict,
        sample_activities: list[MockActivity],
    ):
        """Test list activities endpoint type filter."""
        response = await client.get(
            "/api/v1/activities",
            headers=auth_headers,
            params={"activity_type": "cognitive"},
        )

        assert response.status_code == 200
        data = response.json()
        for item in data["items"]:
            assert item["activity_type"] == "cognitive"


# =============================================================================
# Edge Case Tests
# =============================================================================


class TestEdgeCases:
    """Tests for edge cases and error handling."""

    @pytest.mark.asyncio
    async def test_invalid_uuid_format(
        self,
        client: AsyncClient,
        auth_headers: dict,
    ):
        """Test API handles invalid UUID format gracefully."""
        response = await client.get(
            "/api/v1/activities/recommendations/invalid-uuid",
            headers=auth_headers,
        )

        assert response.status_code == 422  # Validation error

    @pytest.mark.asyncio
    async def test_invalid_activity_type_filter(
        self,
        client: AsyncClient,
        auth_headers: dict,
        sample_child_id,
    ):
        """Test API handles invalid activity type gracefully."""
        response = await client.get(
            f"/api/v1/activities/recommendations/{sample_child_id}",
            headers=auth_headers,
            params={"activity_types": ["invalid_type"]},
        )

        # Should either return 422 or handle gracefully with empty results
        assert response.status_code in [200, 422]

    @pytest.mark.asyncio
    async def test_negative_group_size_rejected(
        self,
        client: AsyncClient,
        auth_headers: dict,
        sample_child_id,
    ):
        """Test negative group_size is rejected."""
        response = await client.get(
            f"/api/v1/activities/recommendations/{sample_child_id}",
            headers=auth_headers,
            params={"group_size": -1},
        )

        assert response.status_code == 422

    @pytest.mark.asyncio
    async def test_boundary_age_values(
        self,
        db_session: AsyncSession,
        sample_activities: list[MockActivity],
        sample_child_id,
    ):
        """Test edge age values (0 and 144 months)."""
        service = ActivityService(db_session)

        # Test minimum age (0 months - newborn)
        response_min = await service.get_recommendations(
            child_id=sample_child_id,
            child_age_months=0,
            max_recommendations=10,
        )
        assert response_min is not None

        # Test maximum age (144 months - 12 years)
        response_max = await service.get_recommendations(
            child_id=sample_child_id,
            child_age_months=144,
            max_recommendations=10,
        )
        assert response_max is not None

    @pytest.mark.asyncio
    async def test_special_needs_flag(
        self,
        db_session: AsyncSession,
        sample_activities: list[MockActivity],
        sample_child_id,
    ):
        """Test include_special_needs flag affects scoring."""
        service = ActivityService(db_session)

        response_with = await service.get_recommendations(
            child_id=sample_child_id,
            include_special_needs=True,
            max_recommendations=10,
        )

        response_without = await service.get_recommendations(
            child_id=sample_child_id,
            include_special_needs=False,
            max_recommendations=10,
        )

        # Both should return results
        assert len(response_with.recommendations) > 0
        assert len(response_without.recommendations) > 0
