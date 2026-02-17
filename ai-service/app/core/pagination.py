"""Pagination helper functions for LAYA AI Service.

Provides utility functions for encoding/decoding cursors, calculating
pagination metadata, and building paginated responses.
"""

import base64
import json
from typing import Any, Generic, Optional, TypeVar
from uuid import UUID

from app.schemas.pagination import (
    CursorPaginatedResponse,
    PaginatedResponse,
)

T = TypeVar("T")


def encode_cursor(value: Any) -> str:
    """Encode a value into an opaque cursor string.

    Args:
        value: Value to encode (typically an ID, timestamp, or composite key)

    Returns:
        Base64-encoded cursor string

    Examples:
        >>> encode_cursor({"id": "123", "created_at": "2024-01-01"})
        'eyJpZCI6ICIxMjMiLCAiY3JlYXRlZF9hdCI6ICIyMDI0LTAxLTAxIn0='
    """
    # Convert UUID to string for JSON serialization
    if isinstance(value, UUID):
        value = str(value)
    elif isinstance(value, dict):
        value = {k: str(v) if isinstance(v, UUID) else v for k, v in value.items()}

    # Encode to JSON then base64
    json_str = json.dumps(value, sort_keys=True)
    encoded = base64.b64encode(json_str.encode("utf-8"))
    return encoded.decode("utf-8")


def decode_cursor(cursor: str) -> Any:
    """Decode an opaque cursor string back to its original value.

    Args:
        cursor: Base64-encoded cursor string

    Returns:
        Decoded value (dict, string, int, etc.)

    Raises:
        ValueError: If cursor is invalid or cannot be decoded

    Examples:
        >>> decode_cursor('eyJpZCI6ICIxMjMifQ==')
        {'id': '123'}
    """
    try:
        decoded = base64.b64decode(cursor.encode("utf-8"))
        return json.loads(decoded.decode("utf-8"))
    except (ValueError, json.JSONDecodeError) as e:
        raise ValueError(f"Invalid cursor format: {e}") from e


def calculate_total_pages(total: int, per_page: int) -> int:
    """Calculate total number of pages for pagination.

    Args:
        total: Total number of items
        per_page: Number of items per page

    Returns:
        Total number of pages (0 if total is 0)

    Examples:
        >>> calculate_total_pages(100, 20)
        5
        >>> calculate_total_pages(99, 20)
        5
        >>> calculate_total_pages(0, 20)
        0
    """
    if total == 0:
        return 0
    return (total + per_page - 1) // per_page


def build_paginated_response(
    items: list[T],
    total: int,
    page: int,
    per_page: int,
) -> PaginatedResponse[T]:
    """Build a standardized paginated response.

    Args:
        items: List of items for the current page
        total: Total number of items matching the query
        page: Current page number (1-indexed)
        per_page: Number of items per page

    Returns:
        PaginatedResponse with items and metadata

    Examples:
        >>> items = [{"id": 1}, {"id": 2}]
        >>> response = build_paginated_response(items, 100, 1, 20)
        >>> response.total_pages
        5
    """
    total_pages = calculate_total_pages(total, per_page)
    return PaginatedResponse[T](
        items=items,
        total=total,
        page=page,
        per_page=per_page,
        total_pages=total_pages,
    )


def build_cursor_paginated_response(
    items: list[T],
    limit: int,
    cursor_field: str = "id",
    has_more: Optional[bool] = None,
    previous_cursor: Optional[str] = None,
) -> CursorPaginatedResponse[T]:
    """Build a cursor-based paginated response.

    Args:
        items: List of items for the current cursor position
        limit: Maximum number of items requested
        cursor_field: Field name to use for cursor generation (default: "id")
        has_more: Whether more items exist (if None, inferred from items length)
        previous_cursor: Cursor for the previous page (optional)

    Returns:
        CursorPaginatedResponse with items and cursor metadata

    Examples:
        >>> items = [{"id": "1"}, {"id": "2"}]
        >>> response = build_cursor_paginated_response(items, 20)
        >>> response.has_more
        False
    """
    # Infer has_more if not provided
    if has_more is None:
        has_more = len(items) >= limit

    # Generate next_cursor from the last item
    next_cursor = None
    if items and has_more:
        last_item = items[-1]
        if isinstance(last_item, dict):
            cursor_value = last_item.get(cursor_field)
        else:
            cursor_value = getattr(last_item, cursor_field, None)

        if cursor_value is not None:
            next_cursor = encode_cursor(cursor_value)

    return CursorPaginatedResponse[T](
        items=items,
        next_cursor=next_cursor,
        previous_cursor=previous_cursor,
        has_more=has_more,
    )
