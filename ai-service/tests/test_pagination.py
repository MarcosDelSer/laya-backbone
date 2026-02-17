"""Unit tests for Pagination schemas.

Tests cover:
- PaginatedRequest validation with default values
- PaginatedRequest with custom parameters
- PaginatedRequest validation (page >= 1)
- PaginatedRequest validation (per_page between 1-100)
- SortOrder enum values
- PaginatedResponse structure and metadata
"""

import pytest
from pydantic import ValidationError

from app.schemas.pagination import PaginatedRequest, PaginatedResponse, SortOrder


class TestPaginatedRequest:
    """Tests for the PaginatedRequest schema."""

    def test_paginated_request_defaults(self):
        """Test PaginatedRequest with default values."""
        request = PaginatedRequest()
        assert request.page == 1
        assert request.per_page == 20
        assert request.sort_by is None
        assert request.sort_order == SortOrder.ASC

    def test_paginated_request_with_custom_values(self):
        """Test PaginatedRequest with custom parameters."""
        request = PaginatedRequest(
            page=3,
            per_page=50,
            sort_by="created_at",
            sort_order=SortOrder.DESC,
        )
        assert request.page == 3
        assert request.per_page == 50
        assert request.sort_by == "created_at"
        assert request.sort_order == SortOrder.DESC

    def test_paginated_request_page_must_be_positive(self):
        """Test that page number must be >= 1."""
        with pytest.raises(ValidationError) as exc_info:
            PaginatedRequest(page=0)
        assert "page" in str(exc_info.value)

        with pytest.raises(ValidationError) as exc_info:
            PaginatedRequest(page=-1)
        assert "page" in str(exc_info.value)

    def test_paginated_request_per_page_validation(self):
        """Test per_page validation (1-100 range)."""
        # Valid boundary values
        request_min = PaginatedRequest(per_page=1)
        assert request_min.per_page == 1

        request_max = PaginatedRequest(per_page=100)
        assert request_max.per_page == 100

        # Invalid: per_page < 1
        with pytest.raises(ValidationError) as exc_info:
            PaginatedRequest(per_page=0)
        assert "per_page" in str(exc_info.value)

        # Invalid: per_page > 100
        with pytest.raises(ValidationError) as exc_info:
            PaginatedRequest(per_page=101)
        assert "per_page" in str(exc_info.value)

    def test_paginated_request_sort_order_validation(self):
        """Test sort_order accepts valid enum values."""
        request_asc = PaginatedRequest(sort_order="asc")
        assert request_asc.sort_order == SortOrder.ASC

        request_desc = PaginatedRequest(sort_order="desc")
        assert request_desc.sort_order == SortOrder.DESC

        # Invalid sort order
        with pytest.raises(ValidationError):
            PaginatedRequest(sort_order="invalid")

    def test_paginated_request_optional_sort_by(self):
        """Test that sort_by is optional."""
        request = PaginatedRequest(page=1, per_page=20)
        assert request.sort_by is None


class TestSortOrder:
    """Tests for the SortOrder enum."""

    def test_sort_order_values(self):
        """Test SortOrder enum has expected values."""
        assert SortOrder.ASC.value == "asc"
        assert SortOrder.DESC.value == "desc"

    def test_sort_order_from_string(self):
        """Test SortOrder can be created from string."""
        assert SortOrder("asc") == SortOrder.ASC
        assert SortOrder("desc") == SortOrder.DESC


class TestPaginatedResponse:
    """Tests for the PaginatedResponse schema."""

    def test_paginated_response_structure(self):
        """Test PaginatedResponse with items and metadata."""
        # Simple list of strings for testing
        response = PaginatedResponse[str](
            items=["item1", "item2", "item3"],
            total=50,
            page=2,
            per_page=20,
            total_pages=3,
        )
        assert len(response.items) == 3
        assert response.total == 50
        assert response.page == 2
        assert response.per_page == 20
        assert response.total_pages == 3

    def test_paginated_response_empty_items(self):
        """Test PaginatedResponse with empty items list."""
        response = PaginatedResponse[dict](
            items=[],
            total=0,
            page=1,
            per_page=20,
            total_pages=0,
        )
        assert response.items == []
        assert response.total == 0
        assert response.total_pages == 0

    def test_paginated_response_validation(self):
        """Test PaginatedResponse field validation."""
        # total must be >= 0
        with pytest.raises(ValidationError) as exc_info:
            PaginatedResponse[str](
                items=[],
                total=-1,
                page=1,
                per_page=20,
                total_pages=0,
            )
        assert "total" in str(exc_info.value)

        # page must be >= 1
        with pytest.raises(ValidationError) as exc_info:
            PaginatedResponse[str](
                items=[],
                total=0,
                page=0,
                per_page=20,
                total_pages=0,
            )
        assert "page" in str(exc_info.value)

        # per_page must be >= 1
        with pytest.raises(ValidationError) as exc_info:
            PaginatedResponse[str](
                items=[],
                total=0,
                page=1,
                per_page=0,
                total_pages=0,
            )
        assert "per_page" in str(exc_info.value)

    def test_paginated_response_with_complex_items(self):
        """Test PaginatedResponse with complex item types."""
        # Using dict items
        items = [
            {"id": 1, "name": "Item 1"},
            {"id": 2, "name": "Item 2"},
        ]
        response = PaginatedResponse[dict](
            items=items,
            total=100,
            page=1,
            per_page=2,
            total_pages=50,
        )
        assert len(response.items) == 2
        assert response.items[0]["id"] == 1
        assert response.items[1]["name"] == "Item 2"
