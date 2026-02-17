"""Unit tests for Pagination schemas.

Tests cover:
- PaginatedRequest validation with default values
- PaginatedRequest with custom parameters
- PaginatedRequest validation (page >= 1)
- PaginatedRequest validation (per_page between 1-100)
- SortOrder enum values
- PaginatedResponse structure and metadata
- CursorPaginatedRequest validation and defaults
- CursorPaginatedResponse structure and cursors
- Pagination helper functions (encode/decode cursor, build responses)
"""

import pytest
from pydantic import ValidationError

from app.core.pagination import (
    build_cursor_paginated_response,
    build_paginated_response,
    calculate_total_pages,
    decode_cursor,
    encode_cursor,
)
from app.schemas.pagination import (
    CursorPaginatedRequest,
    CursorPaginatedResponse,
    PaginatedRequest,
    PaginatedResponse,
    SortOrder,
)


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


class TestCursorPaginatedRequest:
    """Tests for the CursorPaginatedRequest schema."""

    def test_cursor_paginated_request_defaults(self):
        """Test CursorPaginatedRequest with default values."""
        request = CursorPaginatedRequest()
        assert request.cursor is None
        assert request.limit == 20
        assert request.sort_by is None
        assert request.sort_order == SortOrder.DESC

    def test_cursor_paginated_request_with_cursor(self):
        """Test CursorPaginatedRequest with custom cursor."""
        cursor = "eyJpZCI6ICIxMjMifQ=="
        request = CursorPaginatedRequest(
            cursor=cursor,
            limit=50,
            sort_by="created_at",
            sort_order=SortOrder.ASC,
        )
        assert request.cursor == cursor
        assert request.limit == 50
        assert request.sort_by == "created_at"
        assert request.sort_order == SortOrder.ASC

    def test_cursor_paginated_request_limit_validation(self):
        """Test limit validation (1-100 range)."""
        # Valid boundary values
        request_min = CursorPaginatedRequest(limit=1)
        assert request_min.limit == 1

        request_max = CursorPaginatedRequest(limit=100)
        assert request_max.limit == 100

        # Invalid: limit < 1
        with pytest.raises(ValidationError) as exc_info:
            CursorPaginatedRequest(limit=0)
        assert "limit" in str(exc_info.value)

        # Invalid: limit > 100
        with pytest.raises(ValidationError) as exc_info:
            CursorPaginatedRequest(limit=101)
        assert "limit" in str(exc_info.value)

    def test_cursor_paginated_request_optional_cursor(self):
        """Test that cursor is optional for first page."""
        request = CursorPaginatedRequest(limit=30)
        assert request.cursor is None


class TestCursorPaginatedResponse:
    """Tests for the CursorPaginatedResponse schema."""

    def test_cursor_paginated_response_structure(self):
        """Test CursorPaginatedResponse with cursors and metadata."""
        response = CursorPaginatedResponse[str](
            items=["item1", "item2", "item3"],
            next_cursor="eyJpZCI6ICIzIn0=",
            previous_cursor=None,
            has_more=True,
        )
        assert len(response.items) == 3
        assert response.next_cursor == "eyJpZCI6ICIzIn0="
        assert response.previous_cursor is None
        assert response.has_more is True

    def test_cursor_paginated_response_no_more_items(self):
        """Test CursorPaginatedResponse when no more items available."""
        response = CursorPaginatedResponse[dict](
            items=[{"id": 1}],
            next_cursor=None,
            previous_cursor="eyJpZCI6ICIwIn0=",
            has_more=False,
        )
        assert len(response.items) == 1
        assert response.next_cursor is None
        assert response.previous_cursor == "eyJpZCI6ICIwIn0="
        assert response.has_more is False

    def test_cursor_paginated_response_empty_items(self):
        """Test CursorPaginatedResponse with empty items list."""
        response = CursorPaginatedResponse[dict](
            items=[],
            next_cursor=None,
            previous_cursor=None,
            has_more=False,
        )
        assert response.items == []
        assert response.next_cursor is None
        assert response.previous_cursor is None
        assert response.has_more is False

    def test_cursor_paginated_response_with_complex_items(self):
        """Test CursorPaginatedResponse with complex item types."""
        items = [
            {"id": "123", "name": "Item 1"},
            {"id": "456", "name": "Item 2"},
        ]
        response = CursorPaginatedResponse[dict](
            items=items,
            next_cursor="eyJpZCI6ICI0NTYifQ==",
            previous_cursor=None,
            has_more=True,
        )
        assert len(response.items) == 2
        assert response.items[0]["id"] == "123"
        assert response.items[1]["name"] == "Item 2"


class TestPaginationHelpers:
    """Tests for pagination helper functions."""

    def test_encode_cursor_simple_value(self):
        """Test encoding a simple value into a cursor."""
        cursor = encode_cursor("123")
        assert isinstance(cursor, str)
        assert len(cursor) > 0

    def test_encode_cursor_dict_value(self):
        """Test encoding a dict into a cursor."""
        value = {"id": "123", "timestamp": "2024-01-01"}
        cursor = encode_cursor(value)
        assert isinstance(cursor, str)
        assert len(cursor) > 0

    def test_decode_cursor_simple_value(self):
        """Test decoding a cursor back to simple value."""
        original = "123"
        cursor = encode_cursor(original)
        decoded = decode_cursor(cursor)
        assert decoded == original

    def test_decode_cursor_dict_value(self):
        """Test decoding a cursor back to dict value."""
        original = {"id": "123", "timestamp": "2024-01-01"}
        cursor = encode_cursor(original)
        decoded = decode_cursor(cursor)
        assert decoded == original

    def test_decode_cursor_invalid_format(self):
        """Test decoding an invalid cursor raises ValueError."""
        with pytest.raises(ValueError, match="Invalid cursor format"):
            decode_cursor("invalid-cursor!!!")

    def test_encode_decode_roundtrip(self):
        """Test encoding and decoding preserves data."""
        test_cases = [
            "simple-string",
            123,
            {"id": "abc-123", "page": 5},
            {"nested": {"data": "value"}},
        ]
        for original in test_cases:
            cursor = encode_cursor(original)
            decoded = decode_cursor(cursor)
            assert decoded == original

    def test_calculate_total_pages_exact_division(self):
        """Test calculating total pages with exact division."""
        assert calculate_total_pages(100, 20) == 5
        assert calculate_total_pages(60, 10) == 6

    def test_calculate_total_pages_with_remainder(self):
        """Test calculating total pages with remainder."""
        assert calculate_total_pages(99, 20) == 5
        assert calculate_total_pages(101, 20) == 6
        assert calculate_total_pages(1, 20) == 1

    def test_calculate_total_pages_zero_items(self):
        """Test calculating total pages with zero items."""
        assert calculate_total_pages(0, 20) == 0

    def test_build_paginated_response(self):
        """Test building a complete paginated response."""
        items = [{"id": i} for i in range(20)]
        response = build_paginated_response(items, total=100, page=2, per_page=20)

        assert len(response.items) == 20
        assert response.total == 100
        assert response.page == 2
        assert response.per_page == 20
        assert response.total_pages == 5

    def test_build_paginated_response_empty(self):
        """Test building a paginated response with no items."""
        response = build_paginated_response([], total=0, page=1, per_page=20)

        assert response.items == []
        assert response.total == 0
        assert response.page == 1
        assert response.per_page == 20
        assert response.total_pages == 0

    def test_build_cursor_paginated_response_with_more_items(self):
        """Test building cursor response when more items exist."""
        items = [{"id": f"item-{i}"} for i in range(20)]
        response = build_cursor_paginated_response(items, limit=20, has_more=True)

        assert len(response.items) == 20
        assert response.has_more is True
        assert response.next_cursor is not None
        assert response.previous_cursor is None

    def test_build_cursor_paginated_response_no_more_items(self):
        """Test building cursor response when no more items exist."""
        items = [{"id": "item-1"}, {"id": "item-2"}]
        response = build_cursor_paginated_response(items, limit=20, has_more=False)

        assert len(response.items) == 2
        assert response.has_more is False
        assert response.next_cursor is None

    def test_build_cursor_paginated_response_infer_has_more(self):
        """Test that has_more is inferred from items length."""
        # Exactly limit items - has_more should be True
        items = [{"id": f"item-{i}"} for i in range(20)]
        response = build_cursor_paginated_response(items, limit=20)
        assert response.has_more is True

        # Fewer than limit items - has_more should be False
        items = [{"id": "item-1"}]
        response = build_cursor_paginated_response(items, limit=20)
        assert response.has_more is False

    def test_build_cursor_paginated_response_custom_cursor_field(self):
        """Test building cursor response with custom cursor field."""
        items = [{"created_at": "2024-01-01"}, {"created_at": "2024-01-02"}]
        response = build_cursor_paginated_response(
            items, limit=20, cursor_field="created_at", has_more=True
        )

        assert response.next_cursor is not None
        # Decode to verify it contains the created_at value
        decoded = decode_cursor(response.next_cursor)
        assert decoded == "2024-01-02"

    def test_build_cursor_paginated_response_with_previous_cursor(self):
        """Test building cursor response with previous cursor."""
        items = [{"id": "item-1"}]
        prev_cursor = encode_cursor("item-0")
        response = build_cursor_paginated_response(
            items, limit=20, previous_cursor=prev_cursor, has_more=False
        )

        assert response.previous_cursor == prev_cursor
        assert response.next_cursor is None
