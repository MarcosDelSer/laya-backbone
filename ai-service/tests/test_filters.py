"""Unit tests for Filter schemas and helpers.

Tests cover:
- DateRangeFilter validation
- StatusFilter validation
- TypeFilter validation
- ActivityFilters validation with all combinations
- CoachingFilters validation
- Filter helper functions for SQLAlchemy queries
"""

from datetime import datetime, timedelta

import pytest
from pydantic import ValidationError
from sqlalchemy import select

from app.core.filters import (
    apply_activity_filters,
    apply_coaching_filters,
    apply_date_range_filter,
    apply_range_filter,
    apply_status_filter,
    apply_type_filter,
)
from app.models.activity import Activity
from app.models.coaching import CoachingSession
from app.schemas.filters import (
    ActivityFilters,
    CoachingFilters,
    DateRangeFilter,
    StatusFilter,
    TypeFilter,
)


class TestDateRangeFilter:
    """Tests for the DateRangeFilter schema."""

    def test_date_range_filter_defaults(self):
        """Test DateRangeFilter with default values."""
        filter_obj = DateRangeFilter()
        assert filter_obj.start_date is None
        assert filter_obj.end_date is None

    def test_date_range_filter_with_dates(self):
        """Test DateRangeFilter with custom dates."""
        start = datetime(2024, 1, 1)
        end = datetime(2024, 12, 31)
        filter_obj = DateRangeFilter(start_date=start, end_date=end)
        assert filter_obj.start_date == start
        assert filter_obj.end_date == end

    def test_date_range_filter_end_before_start_invalid(self):
        """Test that end_date before start_date raises validation error."""
        start = datetime(2024, 12, 31)
        end = datetime(2024, 1, 1)
        with pytest.raises(ValidationError, match="end_date must not be before start_date"):
            DateRangeFilter(start_date=start, end_date=end)

    def test_date_range_filter_only_start(self):
        """Test DateRangeFilter with only start_date."""
        start = datetime(2024, 1, 1)
        filter_obj = DateRangeFilter(start_date=start)
        assert filter_obj.start_date == start
        assert filter_obj.end_date is None

    def test_date_range_filter_only_end(self):
        """Test DateRangeFilter with only end_date."""
        end = datetime(2024, 12, 31)
        filter_obj = DateRangeFilter(end_date=end)
        assert filter_obj.start_date is None
        assert filter_obj.end_date == end


class TestStatusFilter:
    """Tests for the StatusFilter schema."""

    def test_status_filter_defaults(self):
        """Test StatusFilter with default values."""
        filter_obj = StatusFilter()
        assert filter_obj.is_active is None
        assert filter_obj.status is None

    def test_status_filter_is_active_true(self):
        """Test StatusFilter with is_active=True."""
        filter_obj = StatusFilter(is_active=True)
        assert filter_obj.is_active is True
        assert filter_obj.status is None

    def test_status_filter_is_active_false(self):
        """Test StatusFilter with is_active=False."""
        filter_obj = StatusFilter(is_active=False)
        assert filter_obj.is_active is False
        assert filter_obj.status is None

    def test_status_filter_with_status(self):
        """Test StatusFilter with specific status value."""
        filter_obj = StatusFilter(status="pending")
        assert filter_obj.is_active is None
        assert filter_obj.status == "pending"

    def test_status_filter_both_fields(self):
        """Test StatusFilter with both is_active and status."""
        filter_obj = StatusFilter(is_active=True, status="completed")
        assert filter_obj.is_active is True
        assert filter_obj.status == "completed"

    def test_status_filter_status_max_length(self):
        """Test StatusFilter status field max length validation."""
        # Valid: 50 characters or less
        filter_obj = StatusFilter(status="a" * 50)
        assert len(filter_obj.status) == 50

        # Invalid: more than 50 characters
        with pytest.raises(ValidationError):
            StatusFilter(status="a" * 51)


class TestTypeFilter:
    """Tests for the TypeFilter schema."""

    def test_type_filter_defaults(self):
        """Test TypeFilter with default values."""
        filter_obj = TypeFilter()
        assert filter_obj.types is None

    def test_type_filter_single_type(self):
        """Test TypeFilter with a single type."""
        filter_obj = TypeFilter(types=["cognitive"])
        assert filter_obj.types == ["cognitive"]

    def test_type_filter_multiple_types(self):
        """Test TypeFilter with multiple types."""
        types = ["cognitive", "motor", "social"]
        filter_obj = TypeFilter(types=types)
        assert filter_obj.types == types

    def test_type_filter_empty_list_invalid(self):
        """Test that empty types list raises validation error."""
        with pytest.raises(ValidationError, match="types must not be an empty list"):
            TypeFilter(types=[])

    def test_type_filter_max_items(self):
        """Test TypeFilter types field max items validation."""
        # Valid: 20 items or less
        filter_obj = TypeFilter(types=["type"] * 20)
        assert len(filter_obj.types) == 20

        # Invalid: more than 20 items
        with pytest.raises(ValidationError):
            TypeFilter(types=["type"] * 21)


class TestActivityFilters:
    """Tests for the ActivityFilters schema."""

    def test_activity_filters_defaults(self):
        """Test ActivityFilters with default values."""
        filters = ActivityFilters()
        assert filters.created_after is None
        assert filters.created_before is None
        assert filters.updated_after is None
        assert filters.updated_before is None
        assert filters.is_active is None
        assert filters.activity_types is None
        assert filters.difficulty is None
        assert filters.min_duration_minutes is None
        assert filters.max_duration_minutes is None

    def test_activity_filters_with_date_ranges(self):
        """Test ActivityFilters with date range filters."""
        created_after = datetime(2024, 1, 1)
        created_before = datetime(2024, 12, 31)
        filters = ActivityFilters(
            created_after=created_after,
            created_before=created_before,
        )
        assert filters.created_after == created_after
        assert filters.created_before == created_before

    def test_activity_filters_created_before_validation(self):
        """Test that created_before must not be before created_after."""
        with pytest.raises(
            ValidationError, match="created_before must not be before created_after"
        ):
            ActivityFilters(
                created_after=datetime(2024, 12, 31),
                created_before=datetime(2024, 1, 1),
            )

    def test_activity_filters_updated_before_validation(self):
        """Test that updated_before must not be before updated_after."""
        with pytest.raises(
            ValidationError, match="updated_before must not be before updated_after"
        ):
            ActivityFilters(
                updated_after=datetime(2024, 12, 31),
                updated_before=datetime(2024, 1, 1),
            )

    def test_activity_filters_with_status(self):
        """Test ActivityFilters with status filter."""
        filters = ActivityFilters(is_active=True)
        assert filters.is_active is True

    def test_activity_filters_with_types(self):
        """Test ActivityFilters with activity types."""
        types = ["cognitive", "motor"]
        filters = ActivityFilters(activity_types=types)
        assert filters.activity_types == types

    def test_activity_filters_empty_types_invalid(self):
        """Test that empty activity_types list raises validation error."""
        with pytest.raises(ValidationError, match="activity_types must not be an empty list"):
            ActivityFilters(activity_types=[])

    def test_activity_filters_with_difficulty(self):
        """Test ActivityFilters with difficulty filter."""
        filters = ActivityFilters(difficulty="easy")
        assert filters.difficulty == "easy"

    def test_activity_filters_with_duration_range(self):
        """Test ActivityFilters with duration range."""
        filters = ActivityFilters(
            min_duration_minutes=30,
            max_duration_minutes=60,
        )
        assert filters.min_duration_minutes == 30
        assert filters.max_duration_minutes == 60

    def test_activity_filters_duration_validation(self):
        """Test that max_duration must not be less than min_duration."""
        with pytest.raises(
            ValidationError,
            match="max_duration_minutes must not be less than min_duration_minutes",
        ):
            ActivityFilters(
                min_duration_minutes=60,
                max_duration_minutes=30,
            )

    def test_activity_filters_duration_boundaries(self):
        """Test ActivityFilters duration boundary validation."""
        # Valid: min >= 0
        filters = ActivityFilters(min_duration_minutes=0)
        assert filters.min_duration_minutes == 0

        # Invalid: min < 0
        with pytest.raises(ValidationError):
            ActivityFilters(min_duration_minutes=-1)

        # Valid: max <= 1440
        filters = ActivityFilters(max_duration_minutes=1440)
        assert filters.max_duration_minutes == 1440

        # Invalid: max > 1440
        with pytest.raises(ValidationError):
            ActivityFilters(max_duration_minutes=1441)

    def test_activity_filters_all_fields(self):
        """Test ActivityFilters with all fields populated."""
        now = datetime.now()
        filters = ActivityFilters(
            created_after=now - timedelta(days=30),
            created_before=now,
            updated_after=now - timedelta(days=7),
            updated_before=now,
            is_active=True,
            activity_types=["cognitive", "motor"],
            difficulty="medium",
            min_duration_minutes=30,
            max_duration_minutes=60,
        )
        assert filters.created_after is not None
        assert filters.created_before is not None
        assert filters.is_active is True
        assert len(filters.activity_types) == 2
        assert filters.difficulty == "medium"


class TestCoachingFilters:
    """Tests for the CoachingFilters schema."""

    def test_coaching_filters_defaults(self):
        """Test CoachingFilters with default values."""
        filters = CoachingFilters()
        assert filters.created_after is None
        assert filters.created_before is None
        assert filters.child_id is None
        assert filters.user_id is None
        assert filters.categories is None
        assert filters.special_need_types is None

    def test_coaching_filters_with_date_range(self):
        """Test CoachingFilters with date range."""
        created_after = datetime(2024, 1, 1)
        created_before = datetime(2024, 12, 31)
        filters = CoachingFilters(
            created_after=created_after,
            created_before=created_before,
        )
        assert filters.created_after == created_after
        assert filters.created_before == created_before

    def test_coaching_filters_created_before_validation(self):
        """Test that created_before must not be before created_after."""
        with pytest.raises(
            ValidationError, match="created_before must not be before created_after"
        ):
            CoachingFilters(
                created_after=datetime(2024, 12, 31),
                created_before=datetime(2024, 1, 1),
            )

    def test_coaching_filters_with_entity_ids(self):
        """Test CoachingFilters with child and user IDs."""
        filters = CoachingFilters(
            child_id="child-123",
            user_id="user-456",
        )
        assert filters.child_id == "child-123"
        assert filters.user_id == "user-456"

    def test_coaching_filters_with_categories(self):
        """Test CoachingFilters with categories."""
        categories = ["behavior", "communication"]
        filters = CoachingFilters(categories=categories)
        assert filters.categories == categories

    def test_coaching_filters_empty_categories_invalid(self):
        """Test that empty categories list raises validation error."""
        with pytest.raises(ValidationError, match="categories must not be an empty list"):
            CoachingFilters(categories=[])

    def test_coaching_filters_with_special_need_types(self):
        """Test CoachingFilters with special need types."""
        types = ["autism", "adhd"]
        filters = CoachingFilters(special_need_types=types)
        assert filters.special_need_types == types

    def test_coaching_filters_empty_special_need_types_invalid(self):
        """Test that empty special_need_types list raises validation error."""
        with pytest.raises(
            ValidationError, match="special_need_types must not be an empty list"
        ):
            CoachingFilters(special_need_types=[])

    def test_coaching_filters_all_fields(self):
        """Test CoachingFilters with all fields populated."""
        now = datetime.now()
        filters = CoachingFilters(
            created_after=now - timedelta(days=30),
            created_before=now,
            child_id="child-123",
            user_id="user-456",
            categories=["behavior", "communication"],
            special_need_types=["autism", "adhd"],
        )
        assert filters.created_after is not None
        assert filters.created_before is not None
        assert filters.child_id == "child-123"
        assert filters.user_id == "user-456"
        assert len(filters.categories) == 2
        assert len(filters.special_need_types) == 2


class TestFilterHelpers:
    """Tests for filter helper functions."""

    def test_apply_date_range_filter_both_dates(self):
        """Test applying date range filter with both start and end dates."""
        query = select(Activity)
        start = datetime(2024, 1, 1)
        end = datetime(2024, 12, 31)

        filtered_query = apply_date_range_filter(
            query, Activity, "created_at", start, end
        )

        # Verify the query was modified (has where clauses)
        assert filtered_query is not query
        assert str(filtered_query) != str(query)

    def test_apply_date_range_filter_only_start(self):
        """Test applying date range filter with only start date."""
        query = select(Activity)
        start = datetime(2024, 1, 1)

        filtered_query = apply_date_range_filter(
            query, Activity, "created_at", start_date=start
        )

        assert str(filtered_query) != str(query)

    def test_apply_date_range_filter_only_end(self):
        """Test applying date range filter with only end date."""
        query = select(Activity)
        end = datetime(2024, 12, 31)

        filtered_query = apply_date_range_filter(
            query, Activity, "created_at", end_date=end
        )

        assert str(filtered_query) != str(query)

    def test_apply_date_range_filter_no_dates(self):
        """Test that filter returns unchanged query when no dates provided."""
        query = select(Activity)

        filtered_query = apply_date_range_filter(query, Activity, "created_at")

        # Query should be unchanged
        assert str(filtered_query) == str(query)

    def test_apply_status_filter_is_active(self):
        """Test applying status filter with is_active."""
        query = select(Activity)

        filtered_query = apply_status_filter(query, Activity, is_active=True)

        assert str(filtered_query) != str(query)
        assert "is_active" in str(filtered_query).lower()

    def test_apply_status_filter_no_filters(self):
        """Test that status filter returns unchanged query when no filters."""
        query = select(Activity)

        filtered_query = apply_status_filter(query, Activity)

        assert str(filtered_query) == str(query)

    def test_apply_type_filter_single_type(self):
        """Test applying type filter with a single type."""
        query = select(Activity)

        filtered_query = apply_type_filter(
            query, Activity, "activity_type", types=["cognitive"]
        )

        assert str(filtered_query) != str(query)

    def test_apply_type_filter_multiple_types(self):
        """Test applying type filter with multiple types."""
        query = select(Activity)

        filtered_query = apply_type_filter(
            query, Activity, "activity_type", types=["cognitive", "motor"]
        )

        assert str(filtered_query) != str(query)

    def test_apply_type_filter_no_types(self):
        """Test that type filter returns unchanged query when no types."""
        query = select(Activity)

        filtered_query = apply_type_filter(query, Activity, "activity_type")

        assert str(filtered_query) == str(query)

    def test_apply_range_filter_both_values(self):
        """Test applying range filter with both min and max values."""
        query = select(Activity)

        filtered_query = apply_range_filter(
            query, Activity, "duration_minutes", min_value=30, max_value=60
        )

        assert str(filtered_query) != str(query)

    def test_apply_range_filter_only_min(self):
        """Test applying range filter with only min value."""
        query = select(Activity)

        filtered_query = apply_range_filter(
            query, Activity, "duration_minutes", min_value=30
        )

        assert str(filtered_query) != str(query)

    def test_apply_range_filter_only_max(self):
        """Test applying range filter with only max value."""
        query = select(Activity)

        filtered_query = apply_range_filter(
            query, Activity, "duration_minutes", max_value=60
        )

        assert str(filtered_query) != str(query)

    def test_apply_range_filter_no_values(self):
        """Test that range filter returns unchanged query when no values."""
        query = select(Activity)

        filtered_query = apply_range_filter(query, Activity, "duration_minutes")

        assert str(filtered_query) == str(query)

    def test_apply_activity_filters_comprehensive(self):
        """Test applying all activity filters together."""
        query = select(Activity)
        now = datetime.now()

        filtered_query = apply_activity_filters(
            query,
            Activity,
            created_after=now - timedelta(days=30),
            created_before=now,
            is_active=True,
            activity_types=["cognitive", "motor"],
            difficulty="medium",
            min_duration_minutes=30,
            max_duration_minutes=60,
        )

        # Verify query was modified
        assert str(filtered_query) != str(query)

    def test_apply_activity_filters_no_filters(self):
        """Test that apply_activity_filters returns unchanged query when no filters."""
        query = select(Activity)

        filtered_query = apply_activity_filters(query, Activity)

        # Query should be unchanged
        assert str(filtered_query) == str(query)

    def test_apply_coaching_filters_comprehensive(self):
        """Test applying all coaching filters together."""
        query = select(CoachingSession)
        now = datetime.now()

        filtered_query = apply_coaching_filters(
            query,
            CoachingSession,
            created_after=now - timedelta(days=30),
            created_before=now,
            child_id="child-123",
            user_id="user-456",
            categories=["behavior"],
            special_need_types=["autism"],
        )

        # Verify query was modified
        assert str(filtered_query) != str(query)

    def test_apply_coaching_filters_no_filters(self):
        """Test that apply_coaching_filters returns unchanged query when no filters."""
        query = select(CoachingSession)

        filtered_query = apply_coaching_filters(query, CoachingSession)

        # Query should be unchanged
        assert str(filtered_query) == str(query)
