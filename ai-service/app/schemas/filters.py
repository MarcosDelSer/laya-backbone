"""Filter schemas for LAYA AI Service.

Provides standardized filter schemas for API endpoints with date range,
status, and type filtering capabilities.
"""

from datetime import datetime
from enum import Enum
from typing import Optional

from pydantic import BaseModel, Field, field_validator


class DateRangeFilter(BaseModel):
    """Date range filter for list endpoints.

    Filters items based on a date field (typically created_at or updated_at)
    within a specified range.

    Attributes:
        start_date: Start of the date range (inclusive)
        end_date: End of the date range (inclusive)
    """

    start_date: Optional[datetime] = Field(
        default=None,
        description="Start of the date range (inclusive)",
    )
    end_date: Optional[datetime] = Field(
        default=None,
        description="End of the date range (inclusive)",
    )

    @field_validator("end_date")
    @classmethod
    def validate_end_date(cls, end_date: Optional[datetime], info) -> Optional[datetime]:
        """Validate that end_date is not before start_date.

        Args:
            end_date: End date to validate
            info: Validation context containing other field values

        Returns:
            Validated end_date

        Raises:
            ValueError: If end_date is before start_date
        """
        if end_date is not None and info.data.get("start_date") is not None:
            start_date = info.data["start_date"]
            if end_date < start_date:
                raise ValueError("end_date must not be before start_date")
        return end_date


class StatusFilter(BaseModel):
    """Status filter for list endpoints.

    Filters items by their status or active state.

    Attributes:
        is_active: Filter by active/inactive status
        status: Filter by specific status value (e.g., 'pending', 'completed')
    """

    is_active: Optional[bool] = Field(
        default=None,
        description="Filter by active/inactive status",
    )
    status: Optional[str] = Field(
        default=None,
        max_length=50,
        description="Filter by specific status value",
    )


class TypeFilter(BaseModel):
    """Type filter for list endpoints.

    Filters items by their type or category.

    Attributes:
        types: List of types to include (OR logic - matches any)
    """

    types: Optional[list[str]] = Field(
        default=None,
        description="List of types to filter by (matches any)",
        max_length=20,
    )

    @field_validator("types")
    @classmethod
    def validate_types_not_empty(cls, types: Optional[list[str]]) -> Optional[list[str]]:
        """Validate that types list is not empty if provided.

        Args:
            types: List of types to validate

        Returns:
            Validated types list

        Raises:
            ValueError: If types is an empty list
        """
        if types is not None and len(types) == 0:
            raise ValueError("types must not be an empty list")
        return types


class ActivityFilters(BaseModel):
    """Combined filters for activity list endpoints.

    Provides all available filter options for activities including
    date range, status, and type filters.

    Attributes:
        created_after: Filter activities created after this date
        created_before: Filter activities created before this date
        updated_after: Filter activities updated after this date
        updated_before: Filter activities updated before this date
        is_active: Filter by active/inactive status
        activity_types: Filter by activity types
        difficulty: Filter by difficulty level
        min_duration_minutes: Minimum activity duration in minutes
        max_duration_minutes: Maximum activity duration in minutes
    """

    # Date range filters
    created_after: Optional[datetime] = Field(
        default=None,
        description="Filter activities created after this date (inclusive)",
    )
    created_before: Optional[datetime] = Field(
        default=None,
        description="Filter activities created before this date (inclusive)",
    )
    updated_after: Optional[datetime] = Field(
        default=None,
        description="Filter activities updated after this date (inclusive)",
    )
    updated_before: Optional[datetime] = Field(
        default=None,
        description="Filter activities updated before this date (inclusive)",
    )

    # Status filter
    is_active: Optional[bool] = Field(
        default=None,
        description="Filter by active/inactive status",
    )

    # Type filters
    activity_types: Optional[list[str]] = Field(
        default=None,
        description="Filter by activity types (matches any)",
        max_length=20,
    )
    difficulty: Optional[str] = Field(
        default=None,
        description="Filter by difficulty level",
    )

    # Duration filters
    min_duration_minutes: Optional[int] = Field(
        default=None,
        ge=0,
        description="Minimum activity duration in minutes",
    )
    max_duration_minutes: Optional[int] = Field(
        default=None,
        ge=0,
        le=1440,  # 24 hours max
        description="Maximum activity duration in minutes",
    )

    @field_validator("created_before")
    @classmethod
    def validate_created_before(
        cls, created_before: Optional[datetime], info
    ) -> Optional[datetime]:
        """Validate that created_before is not before created_after."""
        if created_before is not None and info.data.get("created_after") is not None:
            if created_before < info.data["created_after"]:
                raise ValueError("created_before must not be before created_after")
        return created_before

    @field_validator("updated_before")
    @classmethod
    def validate_updated_before(
        cls, updated_before: Optional[datetime], info
    ) -> Optional[datetime]:
        """Validate that updated_before is not before updated_after."""
        if updated_before is not None and info.data.get("updated_after") is not None:
            if updated_before < info.data["updated_after"]:
                raise ValueError("updated_before must not be before updated_after")
        return updated_before

    @field_validator("max_duration_minutes")
    @classmethod
    def validate_max_duration(cls, max_duration: Optional[int], info) -> Optional[int]:
        """Validate that max_duration is not less than min_duration."""
        if max_duration is not None and info.data.get("min_duration_minutes") is not None:
            if max_duration < info.data["min_duration_minutes"]:
                raise ValueError("max_duration_minutes must not be less than min_duration_minutes")
        return max_duration

    @field_validator("activity_types")
    @classmethod
    def validate_activity_types_not_empty(
        cls, activity_types: Optional[list[str]]
    ) -> Optional[list[str]]:
        """Validate that activity_types is not an empty list."""
        if activity_types is not None and len(activity_types) == 0:
            raise ValueError("activity_types must not be an empty list")
        return activity_types


class CoachingFilters(BaseModel):
    """Combined filters for coaching session list endpoints.

    Provides all available filter options for coaching sessions including
    date range and type filters.

    Attributes:
        created_after: Filter sessions created after this date
        created_before: Filter sessions created before this date
        child_id: Filter by specific child
        user_id: Filter by specific user
        categories: Filter by coaching categories
        special_need_types: Filter by special need types
    """

    # Date range filters
    created_after: Optional[datetime] = Field(
        default=None,
        description="Filter sessions created after this date (inclusive)",
    )
    created_before: Optional[datetime] = Field(
        default=None,
        description="Filter sessions created before this date (inclusive)",
    )

    # Entity filters
    child_id: Optional[str] = Field(
        default=None,
        description="Filter by specific child ID",
    )
    user_id: Optional[str] = Field(
        default=None,
        description="Filter by specific user ID",
    )

    # Type filters
    categories: Optional[list[str]] = Field(
        default=None,
        description="Filter by coaching categories (matches any)",
        max_length=20,
    )
    special_need_types: Optional[list[str]] = Field(
        default=None,
        description="Filter by special need types (matches any)",
        max_length=20,
    )

    @field_validator("created_before")
    @classmethod
    def validate_created_before(
        cls, created_before: Optional[datetime], info
    ) -> Optional[datetime]:
        """Validate that created_before is not before created_after."""
        if created_before is not None and info.data.get("created_after") is not None:
            if created_before < info.data["created_after"]:
                raise ValueError("created_before must not be before created_after")
        return created_before

    @field_validator("categories")
    @classmethod
    def validate_categories_not_empty(
        cls, categories: Optional[list[str]]
    ) -> Optional[list[str]]:
        """Validate that categories is not an empty list."""
        if categories is not None and len(categories) == 0:
            raise ValueError("categories must not be an empty list")
        return categories

    @field_validator("special_need_types")
    @classmethod
    def validate_special_need_types_not_empty(
        cls, special_need_types: Optional[list[str]]
    ) -> Optional[list[str]]:
        """Validate that special_need_types is not an empty list."""
        if special_need_types is not None and len(special_need_types) == 0:
            raise ValueError("special_need_types must not be an empty list")
        return special_need_types
