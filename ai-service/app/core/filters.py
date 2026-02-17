"""Filter helper functions for LAYA AI Service.

Provides utility functions for applying filters to SQLAlchemy queries.
"""

from datetime import datetime
from typing import Any, Optional

from sqlalchemy import Select
from sqlalchemy.orm import DeclarativeBase


def apply_date_range_filter(
    query: Select,
    model: type[DeclarativeBase],
    field_name: str,
    start_date: Optional[datetime] = None,
    end_date: Optional[datetime] = None,
) -> Select:
    """Apply date range filter to a SQLAlchemy query.

    Args:
        query: SQLAlchemy select query
        model: SQLAlchemy model class
        field_name: Name of the date field to filter on
        start_date: Start of the date range (inclusive)
        end_date: End of the date range (inclusive)

    Returns:
        Updated query with date range filter applied

    Examples:
        >>> from app.models.activity import Activity
        >>> query = select(Activity)
        >>> query = apply_date_range_filter(
        ...     query, Activity, "created_at",
        ...     start_date=datetime(2024, 1, 1),
        ...     end_date=datetime(2024, 12, 31)
        ... )
    """
    field = getattr(model, field_name)

    if start_date is not None:
        query = query.where(field >= start_date)
    if end_date is not None:
        query = query.where(field <= end_date)

    return query


def apply_status_filter(
    query: Select,
    model: type[DeclarativeBase],
    is_active: Optional[bool] = None,
    status: Optional[str] = None,
) -> Select:
    """Apply status filter to a SQLAlchemy query.

    Args:
        query: SQLAlchemy select query
        model: SQLAlchemy model class
        is_active: Filter by active/inactive status
        status: Filter by specific status value

    Returns:
        Updated query with status filter applied

    Examples:
        >>> from app.models.activity import Activity
        >>> query = select(Activity)
        >>> query = apply_status_filter(query, Activity, is_active=True)
    """
    if is_active is not None and hasattr(model, "is_active"):
        query = query.where(model.is_active == is_active)

    if status is not None and hasattr(model, "status"):
        query = query.where(model.status == status)

    return query


def apply_type_filter(
    query: Select,
    model: type[DeclarativeBase],
    field_name: str,
    types: Optional[list[str]] = None,
) -> Select:
    """Apply type filter to a SQLAlchemy query.

    Filters items where the specified field matches any of the provided types.

    Args:
        query: SQLAlchemy select query
        model: SQLAlchemy model class
        field_name: Name of the type field to filter on
        types: List of types to filter by (OR logic - matches any)

    Returns:
        Updated query with type filter applied

    Examples:
        >>> from app.models.activity import Activity
        >>> query = select(Activity)
        >>> query = apply_type_filter(
        ...     query, Activity, "activity_type",
        ...     types=["cognitive", "motor"]
        ... )
    """
    if types is not None and len(types) > 0:
        field = getattr(model, field_name)
        query = query.where(field.in_(types))

    return query


def apply_range_filter(
    query: Select,
    model: type[DeclarativeBase],
    field_name: str,
    min_value: Optional[Any] = None,
    max_value: Optional[Any] = None,
) -> Select:
    """Apply numeric range filter to a SQLAlchemy query.

    Args:
        query: SQLAlchemy select query
        model: SQLAlchemy model class
        field_name: Name of the numeric field to filter on
        min_value: Minimum value (inclusive)
        max_value: Maximum value (inclusive)

    Returns:
        Updated query with range filter applied

    Examples:
        >>> from app.models.activity import Activity
        >>> query = select(Activity)
        >>> query = apply_range_filter(
        ...     query, Activity, "duration_minutes",
        ...     min_value=30, max_value=60
        ... )
    """
    field = getattr(model, field_name)

    if min_value is not None:
        query = query.where(field >= min_value)
    if max_value is not None:
        query = query.where(field <= max_value)

    return query


def apply_activity_filters(
    query: Select,
    model: type[DeclarativeBase],
    created_after: Optional[datetime] = None,
    created_before: Optional[datetime] = None,
    updated_after: Optional[datetime] = None,
    updated_before: Optional[datetime] = None,
    is_active: Optional[bool] = None,
    activity_types: Optional[list[str]] = None,
    difficulty: Optional[str] = None,
    min_duration_minutes: Optional[int] = None,
    max_duration_minutes: Optional[int] = None,
) -> Select:
    """Apply activity-specific filters to a SQLAlchemy query.

    This is a convenience function that combines multiple filter applications
    for activity queries.

    Args:
        query: SQLAlchemy select query
        model: SQLAlchemy model class (typically Activity)
        created_after: Filter activities created after this date
        created_before: Filter activities created before this date
        updated_after: Filter activities updated after this date
        updated_before: Filter activities updated before this date
        is_active: Filter by active/inactive status
        activity_types: Filter by activity types
        difficulty: Filter by difficulty level
        min_duration_minutes: Minimum activity duration
        max_duration_minutes: Maximum activity duration

    Returns:
        Updated query with all filters applied

    Examples:
        >>> from app.models.activity import Activity
        >>> from sqlalchemy import select
        >>> query = select(Activity)
        >>> query = apply_activity_filters(
        ...     query, Activity,
        ...     is_active=True,
        ...     activity_types=["cognitive", "motor"],
        ...     min_duration_minutes=30
        ... )
    """
    # Apply date range filters
    query = apply_date_range_filter(
        query, model, "created_at", created_after, created_before
    )
    query = apply_date_range_filter(
        query, model, "updated_at", updated_after, updated_before
    )

    # Apply status filter
    query = apply_status_filter(query, model, is_active=is_active)

    # Apply type filters
    if activity_types is not None:
        query = apply_type_filter(query, model, "activity_type", activity_types)

    if difficulty is not None:
        query = query.where(model.difficulty == difficulty)

    # Apply duration range filter
    query = apply_range_filter(
        query, model, "duration_minutes", min_duration_minutes, max_duration_minutes
    )

    return query


def apply_coaching_filters(
    query: Select,
    model: type[DeclarativeBase],
    created_after: Optional[datetime] = None,
    created_before: Optional[datetime] = None,
    child_id: Optional[str] = None,
    user_id: Optional[str] = None,
    categories: Optional[list[str]] = None,
    special_need_types: Optional[list[str]] = None,
) -> Select:
    """Apply coaching session filters to a SQLAlchemy query.

    This is a convenience function that combines multiple filter applications
    for coaching session queries.

    Args:
        query: SQLAlchemy select query
        model: SQLAlchemy model class (typically CoachingSession)
        created_after: Filter sessions created after this date
        created_before: Filter sessions created before this date
        child_id: Filter by specific child
        user_id: Filter by specific user
        categories: Filter by coaching categories
        special_need_types: Filter by special need types

    Returns:
        Updated query with all filters applied

    Examples:
        >>> from app.models.coaching import CoachingSession
        >>> from sqlalchemy import select
        >>> query = select(CoachingSession)
        >>> query = apply_coaching_filters(
        ...     query, CoachingSession,
        ...     child_id="123",
        ...     categories=["behavior", "communication"]
        ... )
    """
    # Apply date range filter
    query = apply_date_range_filter(
        query, model, "created_at", created_after, created_before
    )

    # Apply entity filters
    if child_id is not None:
        query = query.where(model.child_id == child_id)

    if user_id is not None:
        query = query.where(model.user_id == user_id)

    # Apply category filter
    if categories is not None and len(categories) > 0:
        query = query.where(model.category.in_(categories))

    # Apply special need types filter (array overlap)
    if special_need_types is not None and len(special_need_types) > 0:
        # For PostgreSQL array fields, use overlap operator
        query = query.where(model.special_need_types.overlap(special_need_types))

    return query
