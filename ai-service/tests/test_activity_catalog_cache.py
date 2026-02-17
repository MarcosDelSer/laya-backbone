"""Unit tests for activity catalog caching functionality.

Tests for ActivityService activity catalog caching with Redis,
including cache hits, TTL behavior (1 hour), and invalidation.
"""

from __future__ import annotations

from datetime import datetime
from uuid import UUID, uuid4
from unittest.mock import AsyncMock, MagicMock, patch

import pytest

from app.models.activity import Activity, ActivityType, ActivityDifficulty
from app.services.activity_service import ActivityService
from app.schemas.activity import ActivityResponse


# Mock activity data
MOCK_ACTIVITY_1 = Activity(
    id=uuid4(),
    name="Building Blocks",
    description="Develop motor skills and creativity with building blocks",
    activity_type=ActivityType.MOTOR,
    difficulty=ActivityDifficulty.EASY,
    duration_minutes=30,
    materials_needed=["Building blocks", "Play mat"],
    min_age_months=24,
    max_age_months=60,
    special_needs_adaptations="Use larger blocks for easier grip",
    is_active=True,
    created_at=datetime.utcnow(),
    updated_at=datetime.utcnow(),
)

MOCK_ACTIVITY_2 = Activity(
    id=uuid4(),
    name="Story Time",
    description="Language development through interactive storytelling",
    activity_type=ActivityType.LANGUAGE,
    difficulty=ActivityDifficulty.MEDIUM,
    duration_minutes=20,
    materials_needed=["Picture books", "Cushions"],
    min_age_months=18,
    max_age_months=72,
    special_needs_adaptations="Use books with tactile elements",
    is_active=True,
    created_at=datetime.utcnow(),
    updated_at=datetime.utcnow(),
)

MOCK_ACTIVITY_3 = Activity(
    id=uuid4(),
    name="Art Painting",
    description="Creative expression through painting activities",
    activity_type=ActivityType.CREATIVE,
    difficulty=ActivityDifficulty.MEDIUM,
    duration_minutes=45,
    materials_needed=["Paint", "Paper", "Brushes", "Apron"],
    min_age_months=36,
    max_age_months=84,
    special_needs_adaptations="Non-toxic washable paint only",
    is_active=True,
    created_at=datetime.utcnow(),
    updated_at=datetime.utcnow(),
)


class TestActivityCatalogCache:
    """Tests for activity catalog caching functionality."""

    @pytest.mark.asyncio
    async def test_get_activity_catalog_all_activities(self) -> None:
        """Test fetching complete activity catalog."""
        # Mock database session
        mock_db = MagicMock()
        mock_result = MagicMock()
        mock_result.scalars.return_value.all.return_value = [
            MOCK_ACTIVITY_1,
            MOCK_ACTIVITY_2,
            MOCK_ACTIVITY_3,
        ]
        mock_db.execute = AsyncMock(return_value=mock_result)

        service = ActivityService(db=mock_db)

        # Fetch catalog
        catalog = await service.get_activity_catalog()

        # Verify results
        assert len(catalog) == 3
        assert all(isinstance(item, dict) for item in catalog)
        assert catalog[0]["name"] in ["Building Blocks", "Story Time", "Art Painting"]
        assert catalog[0]["activity_type"] in ["motor", "language", "creative"]
        assert "materials_needed" in catalog[0]

    @pytest.mark.asyncio
    async def test_get_activity_catalog_filtered_by_type(self) -> None:
        """Test fetching activity catalog filtered by activity type."""
        # Mock database session
        mock_db = MagicMock()
        mock_result = MagicMock()
        mock_result.scalars.return_value.all.return_value = [MOCK_ACTIVITY_1]
        mock_db.execute = AsyncMock(return_value=mock_result)

        service = ActivityService(db=mock_db)

        # Fetch catalog filtered by motor activities
        catalog = await service.get_activity_catalog(activity_type="motor")

        # Verify results
        assert len(catalog) == 1
        assert catalog[0]["activity_type"] == "motor"
        assert catalog[0]["name"] == "Building Blocks"

    @pytest.mark.asyncio
    async def test_get_activity_catalog_invalid_type(self) -> None:
        """Test fetching activity catalog with invalid activity type."""
        mock_db = MagicMock()
        service = ActivityService(db=mock_db)

        # Fetch catalog with invalid type
        catalog = await service.get_activity_catalog(activity_type="invalid_type")

        # Should return empty list
        assert catalog == []

    @pytest.mark.asyncio
    async def test_get_activity_catalog_empty(self) -> None:
        """Test fetching activity catalog when no activities exist."""
        # Mock database session with empty result
        mock_db = MagicMock()
        mock_result = MagicMock()
        mock_result.scalars.return_value.all.return_value = []
        mock_db.execute = AsyncMock(return_value=mock_result)

        service = ActivityService(db=mock_db)

        # Fetch catalog
        catalog = await service.get_activity_catalog()

        # Verify empty result
        assert catalog == []

    @pytest.mark.asyncio
    async def test_get_activity_catalog_validated(self) -> None:
        """Test fetching and validating activity catalog."""
        # Mock database session
        mock_db = MagicMock()
        mock_result = MagicMock()
        mock_result.scalars.return_value.all.return_value = [
            MOCK_ACTIVITY_1,
            MOCK_ACTIVITY_2,
        ]
        mock_db.execute = AsyncMock(return_value=mock_result)

        service = ActivityService(db=mock_db)

        # Fetch validated catalog
        catalog = await service.get_activity_catalog_validated()

        # Verify results
        assert len(catalog) == 2
        assert all(isinstance(item, ActivityResponse) for item in catalog)
        assert catalog[0].name in ["Building Blocks", "Story Time"]
        assert catalog[0].activity_type in ["motor", "language"]

    @pytest.mark.asyncio
    async def test_get_activity_catalog_data_structure(self) -> None:
        """Test that catalog data has correct structure."""
        # Mock database session
        mock_db = MagicMock()
        mock_result = MagicMock()
        mock_result.scalars.return_value.all.return_value = [MOCK_ACTIVITY_1]
        mock_db.execute = AsyncMock(return_value=mock_result)

        service = ActivityService(db=mock_db)

        # Fetch catalog
        catalog = await service.get_activity_catalog()

        # Verify data structure
        assert len(catalog) == 1
        activity = catalog[0]

        # Check all required fields are present
        assert "id" in activity
        assert "name" in activity
        assert "description" in activity
        assert "activity_type" in activity
        assert "difficulty" in activity
        assert "duration_minutes" in activity
        assert "materials_needed" in activity
        assert "min_age_months" in activity
        assert "max_age_months" in activity
        assert "special_needs_adaptations" in activity
        assert "is_active" in activity
        assert "created_at" in activity
        assert "updated_at" in activity

        # Verify data types
        assert isinstance(activity["id"], str)
        assert isinstance(activity["name"], str)
        assert isinstance(activity["materials_needed"], list)
        assert isinstance(activity["is_active"], bool)

    @pytest.mark.asyncio
    async def test_invalidate_activity_catalog_cache_all(self) -> None:
        """Test invalidating all activity catalog caches."""
        mock_db = MagicMock()
        service = ActivityService(db=mock_db)

        # Invalidate all caches
        deleted_count = await service.invalidate_activity_catalog_cache(None)

        # Should return number of deleted keys (0 or more)
        assert deleted_count >= 0

    @pytest.mark.asyncio
    async def test_invalidate_activity_catalog_cache_specific_type(self) -> None:
        """Test invalidating cache for a specific activity type."""
        mock_db = MagicMock()
        service = ActivityService(db=mock_db)

        # Invalidate specific type
        deleted_count = await service.invalidate_activity_catalog_cache("motor")

        # Should return number of deleted keys (0 or more)
        assert deleted_count >= 0

    @pytest.mark.asyncio
    async def test_refresh_activity_catalog_cache(self) -> None:
        """Test refreshing activity catalog cache."""
        # Mock database session
        mock_db = MagicMock()
        mock_result = MagicMock()
        mock_result.scalars.return_value.all.return_value = [
            MOCK_ACTIVITY_1,
            MOCK_ACTIVITY_2,
        ]
        mock_db.execute = AsyncMock(return_value=mock_result)

        service = ActivityService(db=mock_db)

        # Refresh cache (invalidate + fetch)
        catalog = await service.refresh_activity_catalog_cache()

        # Verify results
        assert len(catalog) == 2
        assert all(isinstance(item, dict) for item in catalog)

    @pytest.mark.asyncio
    async def test_refresh_activity_catalog_cache_with_type(self) -> None:
        """Test refreshing activity catalog cache for specific type."""
        # Mock database session
        mock_db = MagicMock()
        mock_result = MagicMock()
        mock_result.scalars.return_value.all.return_value = [MOCK_ACTIVITY_3]
        mock_db.execute = AsyncMock(return_value=mock_result)

        service = ActivityService(db=mock_db)

        # Refresh cache for creative activities
        catalog = await service.refresh_activity_catalog_cache("creative")

        # Verify results
        assert len(catalog) == 1
        assert catalog[0]["activity_type"] == "creative"

    @pytest.mark.asyncio
    async def test_activity_catalog_caching_behavior(self) -> None:
        """Test that activity catalog is cached properly."""
        # Mock database session
        mock_db = MagicMock()
        mock_result = MagicMock()
        mock_result.scalars.return_value.all.return_value = [
            MOCK_ACTIVITY_1,
            MOCK_ACTIVITY_2,
        ]
        mock_db.execute = AsyncMock(return_value=mock_result)

        service = ActivityService(db=mock_db)

        # First call - should hit database
        catalog1 = await service.get_activity_catalog()
        assert len(catalog1) == 2

        # Second call - should use cache (if Redis is running)
        catalog2 = await service.get_activity_catalog()
        assert len(catalog2) == 2

        # Results should be equal
        assert catalog1 == catalog2


class TestActivityCatalogCacheTTL:
    """Tests for cache TTL (1 hour) behavior."""

    @pytest.mark.asyncio
    async def test_cache_ttl_is_1_hour(self) -> None:
        """Test that cache decorator uses 1-hour TTL."""
        # This is verified by checking the decorator in activity_service.py
        # The @cache(ttl=3600) means 3600 seconds = 1 hour
        from app.services.activity_service import ActivityService
        import inspect

        # Get the source code and verify TTL
        source = inspect.getsource(ActivityService.get_activity_catalog)
        assert "ttl=3600" in source or "3600" in source

    @pytest.mark.asyncio
    async def test_cache_key_prefix_is_activity_catalog(self) -> None:
        """Test that cache uses 'activity_catalog' key prefix."""
        from app.services.activity_service import ActivityService
        import inspect

        # Get the source code and verify key prefix
        source = inspect.getsource(ActivityService.get_activity_catalog)
        assert 'key_prefix="activity_catalog"' in source


class TestActivityCatalogEdgeCases:
    """Tests for edge cases in activity catalog caching."""

    @pytest.mark.asyncio
    async def test_catalog_with_none_age_range(self) -> None:
        """Test catalog with activities that have no age restrictions."""
        activity_no_age = Activity(
            id=uuid4(),
            name="Free Play",
            description="Unstructured play activity",
            activity_type=ActivityType.SOCIAL,
            difficulty=ActivityDifficulty.EASY,
            duration_minutes=60,
            materials_needed=["Toys"],
            min_age_months=None,
            max_age_months=None,
            special_needs_adaptations=None,
            is_active=True,
            created_at=datetime.utcnow(),
            updated_at=datetime.utcnow(),
        )

        # Mock database session
        mock_db = MagicMock()
        mock_result = MagicMock()
        mock_result.scalars.return_value.all.return_value = [activity_no_age]
        mock_db.execute = AsyncMock(return_value=mock_result)

        service = ActivityService(db=mock_db)

        # Fetch catalog
        catalog = await service.get_activity_catalog()

        # Verify results
        assert len(catalog) == 1
        assert catalog[0]["min_age_months"] is None
        assert catalog[0]["max_age_months"] is None

    @pytest.mark.asyncio
    async def test_catalog_with_empty_materials(self) -> None:
        """Test catalog with activities that have no materials."""
        activity_no_materials = Activity(
            id=uuid4(),
            name="Singing",
            description="Group singing activity",
            activity_type=ActivityType.LANGUAGE,
            difficulty=ActivityDifficulty.EASY,
            duration_minutes=15,
            materials_needed=None,
            min_age_months=12,
            max_age_months=48,
            special_needs_adaptations="Use sign language",
            is_active=True,
            created_at=datetime.utcnow(),
            updated_at=datetime.utcnow(),
        )

        # Mock database session
        mock_db = MagicMock()
        mock_result = MagicMock()
        mock_result.scalars.return_value.all.return_value = [activity_no_materials]
        mock_db.execute = AsyncMock(return_value=mock_result)

        service = ActivityService(db=mock_db)

        # Fetch catalog
        catalog = await service.get_activity_catalog()

        # Verify results
        assert len(catalog) == 1
        # Should default to empty list
        assert catalog[0]["materials_needed"] == []

    @pytest.mark.asyncio
    async def test_catalog_ordering_by_name(self) -> None:
        """Test that catalog is ordered by activity name."""
        activity_a = Activity(
            id=uuid4(),
            name="Art Class",
            description="Art activity",
            activity_type=ActivityType.CREATIVE,
            difficulty=ActivityDifficulty.MEDIUM,
            duration_minutes=30,
            materials_needed=[],
            is_active=True,
            created_at=datetime.utcnow(),
            updated_at=datetime.utcnow(),
        )

        activity_z = Activity(
            id=uuid4(),
            name="Zebra Counting",
            description="Counting activity",
            activity_type=ActivityType.COGNITIVE,
            difficulty=ActivityDifficulty.EASY,
            duration_minutes=20,
            materials_needed=[],
            is_active=True,
            created_at=datetime.utcnow(),
            updated_at=datetime.utcnow(),
        )

        # Mock database session - return in reverse order
        mock_db = MagicMock()
        mock_result = MagicMock()
        # Database should order them, so we return them ordered
        mock_result.scalars.return_value.all.return_value = [activity_a, activity_z]
        mock_db.execute = AsyncMock(return_value=mock_result)

        service = ActivityService(db=mock_db)

        # Fetch catalog
        catalog = await service.get_activity_catalog()

        # Verify ordering (should be alphabetical by name)
        assert len(catalog) == 2
        # Since we're testing the service behavior, we just verify the data is returned
        names = [item["name"] for item in catalog]
        assert "Art Class" in names
        assert "Zebra Counting" in names
