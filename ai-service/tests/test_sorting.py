"""Unit tests for Sort helper functions and schemas.

Tests cover:
- SortOptions schema validation
- MultiSortOptions schema validation
- Sort field enums (Activity, Coaching, Search)
- apply_sort function with various scenarios
- apply_multi_sort function
- Sort field validation and security
- Integration with existing models
"""

import pytest
from pydantic import ValidationError
from sqlalchemy import select

from app.core.sorting import (
    ACTIVITY_SORTABLE_FIELDS,
    COACHING_SORTABLE_FIELDS,
    SEARCH_SORTABLE_FIELDS,
    apply_multi_sort,
    apply_sort,
)
from app.models.activity import Activity
from app.schemas.pagination import SortOrder
from app.schemas.sorting import (
    ActivitySortField,
    CoachingSortField,
    MultiSortOptions,
    SearchSortField,
    SortOptions,
)


class TestSortOptions:
    """Tests for the SortOptions schema."""

    def test_sort_options_basic(self):
        """Test SortOptions with basic values."""
        sort = SortOptions(field="name", order=SortOrder.ASC)
        assert sort.field == "name"
        assert sort.order == SortOrder.ASC

    def test_sort_options_default_order(self):
        """Test SortOptions defaults to ASC order."""
        sort = SortOptions(field="created_at")
        assert sort.field == "created_at"
        assert sort.order == SortOrder.ASC

    def test_sort_options_desc_order(self):
        """Test SortOptions with DESC order."""
        sort = SortOptions(field="updated_at", order=SortOrder.DESC)
        assert sort.order == SortOrder.DESC

    def test_sort_options_field_required(self):
        """Test that field is required."""
        with pytest.raises(ValidationError) as exc_info:
            SortOptions()
        assert "field" in str(exc_info.value)

    def test_sort_options_field_validation(self):
        """Test field length validation."""
        # Valid field
        sort = SortOptions(field="a")
        assert sort.field == "a"

        # Field too long
        with pytest.raises(ValidationError) as exc_info:
            SortOptions(field="a" * 51)
        assert "field" in str(exc_info.value)

        # Empty field
        with pytest.raises(ValidationError) as exc_info:
            SortOptions(field="")
        assert "field" in str(exc_info.value)

    def test_sort_options_invalid_order(self):
        """Test invalid sort order is rejected."""
        with pytest.raises(ValidationError):
            SortOptions(field="name", order="invalid")


class TestMultiSortOptions:
    """Tests for the MultiSortOptions schema."""

    def test_multi_sort_options_single_sort(self):
        """Test MultiSortOptions with single sort."""
        multi = MultiSortOptions(
            sorts=[SortOptions(field="name", order=SortOrder.ASC)]
        )
        assert len(multi.sorts) == 1
        assert multi.sorts[0].field == "name"

    def test_multi_sort_options_multiple_sorts(self):
        """Test MultiSortOptions with multiple sorts."""
        multi = MultiSortOptions(
            sorts=[
                SortOptions(field="difficulty", order=SortOrder.DESC),
                SortOptions(field="name", order=SortOrder.ASC),
                SortOptions(field="created_at", order=SortOrder.DESC),
            ]
        )
        assert len(multi.sorts) == 3
        assert multi.sorts[0].field == "difficulty"
        assert multi.sorts[1].field == "name"
        assert multi.sorts[2].field == "created_at"

    def test_multi_sort_options_max_length(self):
        """Test MultiSortOptions enforces max length of 5."""
        # Valid: 5 sorts
        multi = MultiSortOptions(
            sorts=[SortOptions(field=f"field{i}") for i in range(5)]
        )
        assert len(multi.sorts) == 5

        # Invalid: 6 sorts
        with pytest.raises(ValidationError) as exc_info:
            MultiSortOptions(
                sorts=[SortOptions(field=f"field{i}") for i in range(6)]
            )
        assert "sorts" in str(exc_info.value)

    def test_multi_sort_options_min_length(self):
        """Test MultiSortOptions requires at least one sort."""
        with pytest.raises(ValidationError) as exc_info:
            MultiSortOptions(sorts=[])
        assert "sorts" in str(exc_info.value)


class TestSortFieldEnums:
    """Tests for sort field enum definitions."""

    def test_activity_sort_field_values(self):
        """Test ActivitySortField has expected values."""
        assert ActivitySortField.NAME.value == "name"
        assert ActivitySortField.CREATED_AT.value == "created_at"
        assert ActivitySortField.UPDATED_AT.value == "updated_at"
        assert ActivitySortField.DURATION_MINUTES.value == "duration_minutes"
        assert ActivitySortField.DIFFICULTY.value == "difficulty"
        assert ActivitySortField.ACTIVITY_TYPE.value == "activity_type"
        assert ActivitySortField.MIN_AGE_MONTHS.value == "min_age_months"
        assert ActivitySortField.MAX_AGE_MONTHS.value == "max_age_months"

    def test_activity_sort_field_count(self):
        """Test ActivitySortField has all expected fields."""
        assert len(ActivitySortField) == 8

    def test_coaching_sort_field_values(self):
        """Test CoachingSortField has expected values."""
        assert CoachingSortField.CREATED_AT.value == "created_at"
        assert CoachingSortField.CATEGORY.value == "category"
        assert CoachingSortField.CHILD_ID.value == "child_id"
        assert CoachingSortField.USER_ID.value == "user_id"

    def test_coaching_sort_field_count(self):
        """Test CoachingSortField has all expected fields."""
        assert len(CoachingSortField) == 4

    def test_search_sort_field_values(self):
        """Test SearchSortField has expected values."""
        assert SearchSortField.RELEVANCE.value == "relevance"
        assert SearchSortField.CREATED_AT.value == "created_at"
        assert SearchSortField.ENTITY_TYPE.value == "entity_type"

    def test_search_sort_field_count(self):
        """Test SearchSortField has all expected fields."""
        assert len(SearchSortField) == 3


class TestApplySort:
    """Tests for the apply_sort helper function."""

    def test_apply_sort_ascending(self):
        """Test applying ascending sort."""
        query = select(Activity)
        sorted_query = apply_sort(
            query, Activity, sort_by="name", sort_order=SortOrder.ASC
        )

        # Verify query has order_by clause
        assert sorted_query is not query
        assert str(sorted_query).lower().count("order by") == 1
        assert "asc" in str(sorted_query).lower()

    def test_apply_sort_descending(self):
        """Test applying descending sort."""
        query = select(Activity)
        sorted_query = apply_sort(
            query, Activity, sort_by="created_at", sort_order=SortOrder.DESC
        )

        # Verify query has order_by clause with DESC
        assert str(sorted_query).lower().count("order by") == 1
        assert "desc" in str(sorted_query).lower()

    def test_apply_sort_no_sort_by(self):
        """Test apply_sort with no sort_by returns unchanged query."""
        query = select(Activity)
        result_query = apply_sort(query, Activity, sort_by=None)

        # Query should be unchanged (no order by)
        assert "order by" not in str(result_query).lower()

    def test_apply_sort_with_default(self):
        """Test apply_sort uses default_sort when sort_by is None."""
        query = select(Activity)
        sorted_query = apply_sort(
            query, Activity, sort_by=None, default_sort="created_at"
        )

        # Should have applied default sort
        assert "order by" in str(sorted_query).lower()
        assert "created_at" in str(sorted_query).lower()

    def test_apply_sort_invalid_field(self):
        """Test apply_sort raises ValueError for invalid field."""
        query = select(Activity)
        with pytest.raises(ValueError, match="Invalid sort field"):
            apply_sort(query, Activity, sort_by="nonexistent_field")

    def test_apply_sort_with_allowed_fields(self):
        """Test apply_sort with allowed_fields restriction."""
        query = select(Activity)
        allowed = ["name", "created_at"]

        # Valid field - should work
        sorted_query = apply_sort(
            query, Activity, sort_by="name", allowed_fields=allowed
        )
        assert "order by" in str(sorted_query).lower()

        # Invalid field - should fail
        with pytest.raises(ValueError, match="not allowed"):
            apply_sort(
                query, Activity, sort_by="duration_minutes", allowed_fields=allowed
            )

    def test_apply_sort_multiple_fields(self):
        """Test sorting by different fields."""
        query = select(Activity)

        # Test each sortable field
        for field in ACTIVITY_SORTABLE_FIELDS:
            sorted_query = apply_sort(query, Activity, sort_by=field)
            assert "order by" in str(sorted_query).lower()
            assert field in str(sorted_query).lower()

    def test_apply_sort_with_existing_filters(self):
        """Test apply_sort works with queries that have filters."""
        query = select(Activity).where(Activity.is_active == True)
        sorted_query = apply_sort(
            query, Activity, sort_by="name", sort_order=SortOrder.ASC
        )

        # Should have both WHERE and ORDER BY
        query_str = str(sorted_query).lower()
        assert "where" in query_str
        assert "order by" in query_str


class TestApplyMultiSort:
    """Tests for the apply_multi_sort helper function."""

    def test_apply_multi_sort_two_fields(self):
        """Test applying multiple sort criteria."""
        query = select(Activity)
        sorts = [
            ("difficulty", SortOrder.DESC),
            ("name", SortOrder.ASC),
        ]
        sorted_query = apply_multi_sort(query, Activity, sorts)

        # Should have both sort fields in order by
        query_str = str(sorted_query).lower()
        assert "order by" in query_str
        assert "difficulty" in query_str
        assert "name" in query_str

    def test_apply_multi_sort_three_fields(self):
        """Test applying three sort criteria."""
        query = select(Activity)
        sorts = [
            ("activity_type", SortOrder.ASC),
            ("difficulty", SortOrder.DESC),
            ("name", SortOrder.ASC),
        ]
        sorted_query = apply_multi_sort(query, Activity, sorts)

        query_str = str(sorted_query).lower()
        assert "order by" in query_str
        # All three fields should be present
        assert "activity_type" in query_str
        assert "difficulty" in query_str
        assert "name" in query_str

    def test_apply_multi_sort_empty_list(self):
        """Test apply_multi_sort with empty list returns unchanged query."""
        query = select(Activity)
        sorted_query = apply_multi_sort(query, Activity, [])

        # Should be unchanged
        assert "order by" not in str(sorted_query).lower()

    def test_apply_multi_sort_invalid_field(self):
        """Test apply_multi_sort raises ValueError for invalid field."""
        query = select(Activity)
        sorts = [("invalid_field", SortOrder.ASC)]

        with pytest.raises(ValueError, match="Invalid sort field"):
            apply_multi_sort(query, Activity, sorts)

    def test_apply_multi_sort_with_allowed_fields(self):
        """Test apply_multi_sort with allowed_fields restriction."""
        query = select(Activity)
        allowed = ["name", "created_at", "difficulty"]

        # All valid fields - should work
        sorts = [
            ("difficulty", SortOrder.DESC),
            ("name", SortOrder.ASC),
        ]
        sorted_query = apply_multi_sort(query, Activity, sorts, allowed_fields=allowed)
        assert "order by" in str(sorted_query).lower()

        # One invalid field - should fail
        sorts = [
            ("name", SortOrder.ASC),
            ("duration_minutes", SortOrder.DESC),  # Not allowed
        ]
        with pytest.raises(ValueError, match="not allowed"):
            apply_multi_sort(query, Activity, sorts, allowed_fields=allowed)

    def test_apply_multi_sort_mixed_directions(self):
        """Test multi-sort with mixed ASC/DESC directions."""
        query = select(Activity)
        sorts = [
            ("created_at", SortOrder.DESC),
            ("name", SortOrder.ASC),
            ("duration_minutes", SortOrder.DESC),
        ]
        sorted_query = apply_multi_sort(query, Activity, sorts)

        query_str = str(sorted_query).lower()
        assert "order by" in query_str
        # All fields should be present
        assert "created_at" in query_str
        assert "name" in query_str
        assert "duration_minutes" in query_str


class TestSortableFieldConstants:
    """Tests for sortable field constant definitions."""

    def test_activity_sortable_fields_content(self):
        """Test ACTIVITY_SORTABLE_FIELDS contains expected fields."""
        expected = [
            "name",
            "created_at",
            "updated_at",
            "duration_minutes",
            "difficulty",
            "activity_type",
            "min_age_months",
            "max_age_months",
        ]
        assert ACTIVITY_SORTABLE_FIELDS == expected

    def test_activity_sortable_fields_match_enum(self):
        """Test ACTIVITY_SORTABLE_FIELDS matches ActivitySortField enum."""
        enum_values = [field.value for field in ActivitySortField]
        assert set(ACTIVITY_SORTABLE_FIELDS) == set(enum_values)

    def test_coaching_sortable_fields_content(self):
        """Test COACHING_SORTABLE_FIELDS contains expected fields."""
        expected = ["created_at", "category", "child_id", "user_id"]
        assert COACHING_SORTABLE_FIELDS == expected

    def test_coaching_sortable_fields_match_enum(self):
        """Test COACHING_SORTABLE_FIELDS matches CoachingSortField enum."""
        enum_values = [field.value for field in CoachingSortField]
        assert set(COACHING_SORTABLE_FIELDS) == set(enum_values)

    def test_search_sortable_fields_content(self):
        """Test SEARCH_SORTABLE_FIELDS contains expected fields."""
        expected = ["relevance", "created_at", "entity_type"]
        assert SEARCH_SORTABLE_FIELDS == expected

    def test_search_sortable_fields_match_enum(self):
        """Test SEARCH_SORTABLE_FIELDS matches SearchSortField enum."""
        enum_values = [field.value for field in SearchSortField]
        assert set(SEARCH_SORTABLE_FIELDS) == set(enum_values)


class TestSortSecurity:
    """Tests for sort security and validation."""

    def test_sort_prevents_sql_injection(self):
        """Test that sort field validation prevents SQL injection."""
        query = select(Activity)

        # Attempt SQL injection through field name
        malicious_fields = [
            "name; DROP TABLE activities;--",
            "name' OR '1'='1",
            "name); DELETE FROM activities; --",
            "../../../etc/passwd",
        ]

        for malicious_field in malicious_fields:
            with pytest.raises(ValueError, match="Invalid sort field"):
                apply_sort(query, Activity, sort_by=malicious_field)

    def test_sort_only_allows_model_attributes(self):
        """Test that sort only allows actual model attributes."""
        query = select(Activity)

        # Try to sort by non-existent attributes
        # Note: Some Python internals like __dict__ exist as attributes but
        # are not valid SQLAlchemy columns
        invalid_fields = [
            "password",  # Non-existent field
            "secret_data",  # Non-existent field
            "nonexistent_column",  # Non-existent field
        ]

        for field in invalid_fields:
            with pytest.raises(ValueError, match="Invalid sort field"):
                apply_sort(query, Activity, sort_by=field)

    def test_allowed_fields_whitelist_security(self):
        """Test that allowed_fields provides whitelist security."""
        query = select(Activity)

        # Create a strict whitelist
        allowed = ["name", "created_at"]

        # Only allowed fields should work
        for field in allowed:
            sorted_query = apply_sort(query, Activity, sort_by=field, allowed_fields=allowed)
            assert "order by" in str(sorted_query).lower()

        # Disallowed but valid model fields should fail
        disallowed = ["duration_minutes", "difficulty", "updated_at"]
        for field in disallowed:
            with pytest.raises(ValueError, match="not allowed"):
                apply_sort(query, Activity, sort_by=field, allowed_fields=allowed)
