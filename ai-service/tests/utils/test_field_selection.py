"""Tests for field selection utilities.

These tests verify that the field selection feature correctly filters
API responses to include only requested fields, reducing payload size
and improving performance.
"""

from typing import Optional
from uuid import UUID, uuid4

import pytest
from pydantic import BaseModel, Field

from app.utils.field_selection import (
    FieldSelector,
    filter_response,
    parse_fields,
    validate_fields,
)


# Test models
class Address(BaseModel):
    """Test model for nested field selection."""

    street: str
    city: str
    country: str
    postal_code: str


class User(BaseModel):
    """Test model for field selection."""

    id: UUID
    name: str
    email: str
    age: int
    address: Optional[Address] = None
    is_active: bool = True


class TestParseFields:
    """Tests for parse_fields function."""

    def test_parse_simple_fields(self):
        """Test parsing simple comma-separated fields."""
        result = parse_fields("id,name,email")
        assert result == {"id", "name", "email"}

    def test_parse_fields_with_whitespace(self):
        """Test parsing fields with extra whitespace."""
        result = parse_fields("id, name , email ")
        assert result == {"id", "name", "email"}

    def test_parse_single_field(self):
        """Test parsing a single field."""
        result = parse_fields("name")
        assert result == {"name"}

    def test_parse_empty_string(self):
        """Test parsing empty string returns None."""
        result = parse_fields("")
        assert result is None

    def test_parse_whitespace_only(self):
        """Test parsing whitespace-only string returns None."""
        result = parse_fields("   ")
        assert result is None

    def test_parse_none(self):
        """Test parsing None returns None."""
        result = parse_fields(None)
        assert result is None

    def test_parse_nested_fields(self):
        """Test parsing nested field notation."""
        result = parse_fields("id,user.name,user.email")
        assert result == {"id", "user.name", "user.email"}

    def test_parse_with_trailing_commas(self):
        """Test parsing with trailing commas."""
        result = parse_fields("id,name,")
        assert result == {"id", "name"}

    def test_parse_duplicate_fields(self):
        """Test that duplicate fields are deduplicated (set behavior)."""
        result = parse_fields("id,name,id,email,name")
        assert result == {"id", "name", "email"}


class TestFilterResponse:
    """Tests for filter_response function."""

    def test_filter_pydantic_model(self):
        """Test filtering a Pydantic model."""
        user = User(
            id=uuid4(),
            name="John Doe",
            email="john@example.com",
            age=30,
        )
        result = filter_response(user, {"id", "name"})

        assert "id" in result
        assert "name" in result
        assert result["name"] == "John Doe"
        assert "email" not in result
        assert "age" not in result

    def test_filter_dict(self):
        """Test filtering a dictionary."""
        data = {
            "id": "123",
            "name": "John",
            "email": "john@example.com",
            "age": 30,
        }
        result = filter_response(data, {"id", "name"})

        assert result == {"id": "123", "name": "John"}

    def test_filter_with_always_include(self):
        """Test filtering with always_include fields."""
        user = User(
            id=uuid4(),
            name="John Doe",
            email="john@example.com",
            age=30,
        )
        result = filter_response(
            user,
            {"name"},
            always_include={"id", "is_active"},
        )

        assert "id" in result
        assert "name" in result
        assert "is_active" in result
        assert "email" not in result

    def test_filter_none_fields_returns_all(self):
        """Test that None fields returns all fields."""
        user = User(
            id=uuid4(),
            name="John Doe",
            email="john@example.com",
            age=30,
        )
        result = filter_response(user, None)

        assert "id" in result
        assert "name" in result
        assert "email" in result
        assert "age" in result
        assert "is_active" in result

    def test_filter_list_of_models(self):
        """Test filtering a list of models."""
        users = [
            User(id=uuid4(), name="John", email="john@example.com", age=30),
            User(id=uuid4(), name="Jane", email="jane@example.com", age=25),
        ]
        result = filter_response(users, {"name", "age"})

        assert len(result) == 2
        assert result[0]["name"] == "John"
        assert result[0]["age"] == 30
        assert "email" not in result[0]
        assert result[1]["name"] == "Jane"
        assert result[1]["age"] == 25

    def test_filter_nested_fields(self):
        """Test filtering with nested field notation."""
        address = Address(
            street="123 Main St",
            city="New York",
            country="USA",
            postal_code="10001",
        )
        user = User(
            id=uuid4(),
            name="John Doe",
            email="john@example.com",
            age=30,
            address=address,
        )
        result = filter_response(user, {"id", "name", "address.city", "address.country"})

        assert "id" in result
        assert "name" in result
        assert "address" in result
        assert "city" in result["address"]
        assert "country" in result["address"]
        # Note: Due to the implementation, filtering nested fields may include parent
        assert result["address"]["city"] == "New York"
        assert result["address"]["country"] == "USA"

    def test_filter_nonexistent_fields(self):
        """Test that filtering for nonexistent fields is safe."""
        user = User(
            id=uuid4(),
            name="John Doe",
            email="john@example.com",
            age=30,
        )
        result = filter_response(user, {"id", "name", "nonexistent_field"})

        # Should only include fields that exist
        assert result == {"id": user.id, "name": "John Doe"}

    def test_filter_empty_fields_set(self):
        """Test filtering with empty fields set."""
        user = User(
            id=uuid4(),
            name="John Doe",
            email="john@example.com",
            age=30,
        )
        result = filter_response(user, set())

        # Empty set should return empty dict
        assert result == {}

    def test_filter_with_always_include_only(self):
        """Test filtering with only always_include fields."""
        user = User(
            id=uuid4(),
            name="John Doe",
            email="john@example.com",
            age=30,
        )
        result = filter_response(user, set(), always_include={"id"})

        # Should only include always_include fields
        assert result == {"id": user.id}


class TestValidateFields:
    """Tests for validate_fields function."""

    def test_validate_all_valid_fields(self):
        """Test validation with all valid fields."""
        requested = {"id", "name", "email"}
        valid = {"id", "name", "email", "age", "is_active"}
        result = validate_fields(requested, valid, raise_on_invalid=False)

        assert result == {"id", "name", "email"}

    def test_validate_with_invalid_fields_no_raise(self):
        """Test validation filters out invalid fields without raising."""
        requested = {"id", "name", "invalid_field"}
        valid = {"id", "name", "email", "age"}
        result = validate_fields(requested, valid, raise_on_invalid=False)

        assert result == {"id", "name"}

    def test_validate_with_invalid_fields_raises(self):
        """Test validation raises on invalid fields when configured."""
        requested = {"id", "name", "invalid_field"}
        valid = {"id", "name", "email", "age"}

        with pytest.raises(ValueError, match="Invalid fields requested: invalid_field"):
            validate_fields(requested, valid, raise_on_invalid=True)

    def test_validate_nested_fields(self):
        """Test validation of nested field notation."""
        requested = {"id", "user.name", "user.email"}
        valid = {"id", "user", "other_field"}
        result = validate_fields(requested, valid, raise_on_invalid=False)

        # Should validate based on top-level field
        assert result == {"id", "user.name", "user.email"}

    def test_validate_nested_invalid_top_level(self):
        """Test validation rejects nested fields with invalid top-level."""
        requested = {"id", "invalid.name", "invalid.email"}
        valid = {"id", "user", "other_field"}
        result = validate_fields(requested, valid, raise_on_invalid=False)

        # Should filter out invalid.* fields
        assert result == {"id"}

    def test_validate_empty_requested(self):
        """Test validation with empty requested set."""
        requested = set()
        valid = {"id", "name", "email"}
        result = validate_fields(requested, valid, raise_on_invalid=False)

        assert result == set()

    def test_validate_multiple_invalid_fields_raises(self):
        """Test validation raises with multiple invalid fields."""
        requested = {"id", "invalid1", "invalid2"}
        valid = {"id", "name", "email"}

        with pytest.raises(ValueError, match="Invalid fields requested:"):
            validate_fields(requested, valid, raise_on_invalid=True)


class TestFieldSelector:
    """Tests for FieldSelector class."""

    def test_field_selector_initialization(self):
        """Test FieldSelector initialization."""
        selector = FieldSelector(fields={"id", "name"})

        assert selector.fields == {"id", "name"}
        assert selector.always_include == {"id"}

    def test_field_selector_custom_always_include(self):
        """Test FieldSelector with custom always_include."""
        selector = FieldSelector(
            fields={"name"},
            always_include={"id", "created_at"},
        )

        assert selector.always_include == {"id", "created_at"}

    def test_filter_fields_with_model(self):
        """Test FieldSelector.filter_fields with a model."""
        selector = FieldSelector(fields={"name", "email"})
        user = User(
            id=uuid4(),
            name="John Doe",
            email="john@example.com",
            age=30,
        )
        result = selector.filter_fields(user)

        # Should include requested fields + always_include (id)
        assert "id" in result
        assert "name" in result
        assert "email" in result
        assert "age" not in result

    def test_filter_fields_without_model_class(self):
        """Test FieldSelector.filter_fields without model_class."""
        selector = FieldSelector(fields={"name", "email"})
        data = {
            "id": "123",
            "name": "John",
            "email": "john@example.com",
            "age": 30,
        }
        result = selector.filter_fields(data)

        assert "id" in result
        assert "name" in result
        assert "email" in result
        assert "age" not in result

    def test_filter_fields_with_validation(self):
        """Test FieldSelector.filter_fields with model_class validation."""
        selector = FieldSelector(fields={"name", "email", "invalid_field"})
        user = User(
            id=uuid4(),
            name="John Doe",
            email="john@example.com",
            age=30,
        )
        result = selector.filter_fields(user, model_class=User)

        # Should filter out invalid_field
        assert "id" in result
        assert "name" in result
        assert "email" in result
        assert "invalid_field" not in result

    def test_is_field_requested_true(self):
        """Test is_field_requested returns True for requested field."""
        selector = FieldSelector(fields={"id", "name"})

        assert selector.is_field_requested("name") is True

    def test_is_field_requested_false(self):
        """Test is_field_requested returns False for non-requested field."""
        selector = FieldSelector(fields={"id", "name"})

        assert selector.is_field_requested("email") is False

    def test_is_field_requested_always_include(self):
        """Test is_field_requested returns True for always_include fields."""
        selector = FieldSelector(fields={"name"}, always_include={"id"})

        assert selector.is_field_requested("id") is True

    def test_is_field_requested_no_filtering(self):
        """Test is_field_requested returns True when no filtering is active."""
        selector = FieldSelector(fields=None)

        assert selector.is_field_requested("any_field") is True

    def test_filter_fields_list(self):
        """Test FieldSelector.filter_fields with a list."""
        selector = FieldSelector(fields={"name"})
        users = [
            User(id=uuid4(), name="John", email="john@example.com", age=30),
            User(id=uuid4(), name="Jane", email="jane@example.com", age=25),
        ]
        result = selector.filter_fields(users)

        assert len(result) == 2
        assert "id" in result[0]
        assert "name" in result[0]
        assert "email" not in result[0]

    def test_filter_fields_no_fields(self):
        """Test FieldSelector with no fields returns all."""
        selector = FieldSelector(fields=None)
        user = User(
            id=uuid4(),
            name="John Doe",
            email="john@example.com",
            age=30,
        )
        result = selector.filter_fields(user)

        assert "id" in result
        assert "name" in result
        assert "email" in result
        assert "age" in result


class TestFieldSelectionIntegration:
    """Integration tests for field selection in realistic scenarios."""

    def test_api_response_optimization(self):
        """Test field selection reduces response size."""
        # Create a user with all fields
        user = User(
            id=uuid4(),
            name="John Doe",
            email="john@example.com",
            age=30,
            address=Address(
                street="123 Main St",
                city="New York",
                country="USA",
                postal_code="10001",
            ),
        )

        # Full response
        full_response = filter_response(user, None)
        assert len(full_response.keys()) == 6  # all fields

        # Optimized response
        optimized_response = filter_response(user, {"id", "name"})
        assert len(optimized_response.keys()) == 2

        # Verify size reduction
        assert len(optimized_response.keys()) < len(full_response.keys())

    def test_list_response_optimization(self):
        """Test field selection on list responses."""
        users = [
            User(id=uuid4(), name=f"User {i}", email=f"user{i}@example.com", age=20 + i)
            for i in range(10)
        ]

        # Request only id and name
        selector = FieldSelector(fields={"name"})
        result = selector.filter_fields(users)

        assert len(result) == 10
        for user_data in result:
            assert "id" in user_data  # always included
            assert "name" in user_data
            assert "email" not in user_data
            assert "age" not in user_data

    def test_backward_compatibility(self):
        """Test that not specifying fields returns full response."""
        user = User(
            id=uuid4(),
            name="John Doe",
            email="john@example.com",
            age=30,
        )

        selector = FieldSelector(fields=None)
        result = selector.filter_fields(user)

        # Should return all fields
        assert "id" in result
        assert "name" in result
        assert "email" in result
        assert "age" in result
        assert "is_active" in result

    def test_security_critical_fields_always_included(self):
        """Test that critical fields like 'id' are always included."""
        user = User(
            id=uuid4(),
            name="John Doe",
            email="john@example.com",
            age=30,
        )

        # Request only name, but id should be included
        selector = FieldSelector(fields={"name"})
        result = selector.filter_fields(user)

        assert "id" in result
        assert "name" in result
        assert "email" not in result
