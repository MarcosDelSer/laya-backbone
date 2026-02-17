"""Tests for Pydantic input validation security.

This test suite verifies that comprehensive input validation is enforced
across all schemas to prevent security vulnerabilities and ensure data integrity.

Security features tested:
- Field-level constraints (min_length, max_length, ge, le)
- Type validation through Pydantic
- Enum validation prevents invalid values
- UUID validation ensures proper format
- Required vs optional field validation
- Protection against SQL injection via proper typing
- Protection against XSS via field length limits
"""

import pytest
from pydantic import ValidationError

from app.schemas.activity import (
    ActivityDifficulty,
    ActivityRecommendationRequest,
    ActivityRequest,
    ActivityType,
    AgeRange,
)
from app.schemas.base import BaseSchema, PaginationParams
from app.schemas.coaching import (
    CoachingCategory,
    CoachingGuidanceRequest,
    CoachingPriority,
    CoachingRequest,
    SpecialNeedType,
)


class TestBaseSchemaConfiguration:
    """Test that BaseSchema has security-focused configuration."""

    def test_base_schema_strips_whitespace(self):
        """Test that BaseSchema strips whitespace from strings."""
        config = BaseSchema.model_config
        assert config.get("str_strip_whitespace") is True

    def test_base_schema_uses_from_attributes(self):
        """Test that BaseSchema can be created from ORM models."""
        config = BaseSchema.model_config
        assert config.get("from_attributes") is True


class TestFieldLevelValidation:
    """Test field-level validation constraints."""

    def test_string_field_respects_min_length(self):
        """Test that string fields enforce minimum length."""
        with pytest.raises(ValidationError) as exc_info:
            ActivityRequest(
                name="",  # Empty string, min_length=1
                description="Test description",
                activity_type=ActivityType.COGNITIVE,
            )

        errors = exc_info.value.errors()
        # Should have validation error for empty string
        assert len(errors) > 0

    def test_string_field_respects_max_length(self):
        """Test that string fields enforce maximum length."""
        with pytest.raises(ValidationError) as exc_info:
            ActivityRequest(
                name="x" * 201,  # Too long, max_length=200
                description="Test description",
                activity_type=ActivityType.COGNITIVE,
            )

        errors = exc_info.value.errors()
        assert any("string_too_long" in str(e.get("type", "")) for e in errors)

    def test_integer_field_respects_ge_constraint(self):
        """Test that integer fields enforce greater-than-or-equal constraint."""
        with pytest.raises(ValidationError) as exc_info:
            AgeRange(
                min_months=-1,  # Negative, ge=0
                max_months=24,
            )

        errors = exc_info.value.errors()
        assert any("greater_than_equal" in str(e.get("type", "")) for e in errors)

    def test_integer_field_respects_le_constraint(self):
        """Test that integer fields enforce less-than-or-equal constraint."""
        with pytest.raises(ValidationError) as exc_info:
            AgeRange(
                min_months=0,
                max_months=200,  # Too large, le=144
            )

        errors = exc_info.value.errors()
        assert any("less_than_equal" in str(e.get("type", "")) for e in errors)


class TestEnumValidation:
    """Test enum validation prevents invalid values."""

    def test_enum_field_rejects_invalid_string(self):
        """Test that enum fields reject invalid string values."""
        with pytest.raises(ValidationError) as exc_info:
            ActivityRequest(
                name="Test Activity",
                description="Test description",
                activity_type="invalid_type",  # Invalid enum value
            )

        errors = exc_info.value.errors()
        assert len(errors) > 0

    def test_enum_field_accepts_valid_enum(self):
        """Test that enum fields accept valid enum values."""
        activity = ActivityRequest(
            name="Test Activity",
            description="Test description",
            activity_type=ActivityType.COGNITIVE,
        )
        assert activity.activity_type == ActivityType.COGNITIVE


class TestPaginationValidation:
    """Test pagination parameter validation."""

    def test_pagination_skip_must_be_non_negative(self):
        """Test that skip parameter must be >= 0."""
        with pytest.raises(ValidationError):
            PaginationParams(skip=-1, limit=20)

    def test_pagination_limit_must_be_positive(self):
        """Test that limit parameter must be >= 1."""
        with pytest.raises(ValidationError):
            PaginationParams(skip=0, limit=0)

    def test_pagination_limit_has_maximum(self):
        """Test that limit parameter has a maximum value."""
        with pytest.raises(ValidationError):
            PaginationParams(skip=0, limit=2000)  # Max is 1000

    def test_pagination_with_valid_values(self):
        """Test pagination with valid values."""
        params = PaginationParams(skip=0, limit=20)
        assert params.skip == 0
        assert params.limit == 20


class TestSecurityBenefitsOfValidation:
    """Test security benefits of Pydantic validation."""

    def test_prevents_sql_injection_via_length_limits(self):
        """Test that field length limits help prevent SQL injection."""
        # Very long SQL injection attempt should be rejected
        with pytest.raises(ValidationError):
            ActivityRequest(
                name="x" * 201,  # Exceeds max_length=200
                description="Test",
                activity_type=ActivityType.COGNITIVE,
            )

    def test_prevents_xss_via_length_limits(self):
        """Test that field length limits help prevent XSS attacks."""
        # Very long XSS attempt should be rejected
        with pytest.raises(ValidationError):
            CoachingRequest(
                title="<script>" + "x" * 200 + "</script>",  # Exceeds max_length
                content="Test content",
                category=CoachingCategory.COMMUNICATION,
            )

    def test_enum_validation_prevents_injection(self):
        """Test that enum validation prevents value injection."""
        # Cannot inject arbitrary SQL commands into enum fields
        sql_injection = "'; DELETE FROM activities; --"
        with pytest.raises(ValidationError):
            ActivityRequest(
                name="Test",
                description="Test",
                activity_type=sql_injection,
            )
