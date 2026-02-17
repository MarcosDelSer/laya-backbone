"""Pagination schemas for LAYA AI Service.

Provides standardized pagination schemas for API endpoints with page-based
pagination, sorting, and filtering capabilities.
"""

from enum import Enum
from typing import Generic, Optional, TypeVar

from pydantic import BaseModel, Field

from app.schemas.base import BaseSchema


class SortOrder(str, Enum):
    """Sort order for list results.

    Attributes:
        ASC: Ascending order (A-Z, 0-9, oldest-newest)
        DESC: Descending order (Z-A, 9-0, newest-oldest)
    """

    ASC = "asc"
    DESC = "desc"


class PaginatedRequest(BaseModel):
    """Request schema for paginated list endpoints.

    Provides standardized pagination parameters with page-based navigation
    and sorting capabilities.

    Attributes:
        page: Page number to retrieve (1-indexed)
        per_page: Number of items per page
        sort_by: Field name to sort by
        sort_order: Sort direction (asc or desc)
    """

    page: int = Field(
        default=1,
        ge=1,
        description="Page number to retrieve (1-indexed)",
    )
    per_page: int = Field(
        default=20,
        ge=1,
        le=100,
        description="Number of items per page (max 100)",
    )
    sort_by: Optional[str] = Field(
        default=None,
        description="Field name to sort by",
    )
    sort_order: SortOrder = Field(
        default=SortOrder.ASC,
        description="Sort direction (asc or desc)",
    )


T = TypeVar("T")


class PaginatedResponse(BaseSchema, Generic[T]):
    """Response schema for paginated list endpoints.

    Provides standardized pagination metadata and items.

    Type Parameters:
        T: Type of items in the response

    Attributes:
        items: List of items for the current page
        total: Total number of items matching the query
        page: Current page number (1-indexed)
        per_page: Number of items per page
        total_pages: Total number of pages
    """

    items: list[T] = Field(
        ...,
        description="List of items for the current page",
    )
    total: int = Field(
        ...,
        ge=0,
        description="Total number of items matching the query",
    )
    page: int = Field(
        ...,
        ge=1,
        description="Current page number (1-indexed)",
    )
    per_page: int = Field(
        ...,
        ge=1,
        description="Number of items per page",
    )
    total_pages: int = Field(
        ...,
        ge=0,
        description="Total number of pages",
    )


class CursorPaginatedRequest(BaseModel):
    """Request schema for cursor-based paginated list endpoints.

    Cursor-based pagination is ideal for real-time feeds and continuously
    updating datasets. Uses opaque cursors instead of page numbers for
    more stable navigation.

    Attributes:
        cursor: Opaque cursor string pointing to a position in the dataset
        limit: Maximum number of items to return
        sort_by: Field name to sort by
        sort_order: Sort direction (asc or desc)
    """

    cursor: Optional[str] = Field(
        default=None,
        description="Opaque cursor string for pagination position",
    )
    limit: int = Field(
        default=20,
        ge=1,
        le=100,
        description="Maximum number of items to return (max 100)",
    )
    sort_by: Optional[str] = Field(
        default=None,
        description="Field name to sort by",
    )
    sort_order: SortOrder = Field(
        default=SortOrder.DESC,
        description="Sort direction (asc or desc)",
    )


class CursorPaginatedResponse(BaseSchema, Generic[T]):
    """Response schema for cursor-based paginated list endpoints.

    Provides cursor-based pagination metadata for real-time feeds.

    Type Parameters:
        T: Type of items in the response

    Attributes:
        items: List of items for the current cursor position
        next_cursor: Cursor for fetching the next page (null if no more items)
        previous_cursor: Cursor for fetching the previous page (null if at start)
        has_more: Whether more items are available after this page
    """

    items: list[T] = Field(
        ...,
        description="List of items for the current cursor position",
    )
    next_cursor: Optional[str] = Field(
        default=None,
        description="Cursor for fetching the next page (null if no more items)",
    )
    previous_cursor: Optional[str] = Field(
        default=None,
        description="Cursor for fetching the previous page (null if at start)",
    )
    has_more: bool = Field(
        ...,
        description="Whether more items are available after this page",
    )
