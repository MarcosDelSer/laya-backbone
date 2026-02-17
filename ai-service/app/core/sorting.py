"""Sorting helper functions for LAYA AI Service.

Provides utility functions for applying sorting to SQLAlchemy queries with
validation of sort fields and support for multiple sort directions.
"""

from typing import Optional

from sqlalchemy import Select, asc, desc
from sqlalchemy.orm import DeclarativeBase

from app.schemas.pagination import SortOrder


def apply_sort(
    query: Select,
    model: type[DeclarativeBase],
    sort_by: Optional[str] = None,
    sort_order: SortOrder = SortOrder.ASC,
    allowed_fields: Optional[list[str]] = None,
    default_sort: Optional[str] = None,
) -> Select:
    """Apply sorting to a SQLAlchemy query.

    This function provides a safe way to apply sorting with validation of
    sort fields to prevent SQL injection and invalid field references.

    Args:
        query: SQLAlchemy select query
        model: SQLAlchemy model class
        sort_by: Field name to sort by (must exist on model)
        sort_order: Sort direction (asc or desc)
        allowed_fields: Optional list of allowed field names for sorting.
                       If None, all model fields are allowed.
        default_sort: Default field to sort by if sort_by is None

    Returns:
        Updated query with sorting applied

    Raises:
        ValueError: If sort_by field doesn't exist on model or isn't allowed

    Examples:
        >>> from app.models.activity import Activity
        >>> from sqlalchemy import select
        >>> query = select(Activity)
        >>> # Sort by created_at descending
        >>> query = apply_sort(
        ...     query, Activity,
        ...     sort_by="created_at",
        ...     sort_order=SortOrder.DESC
        ... )
        >>> # Sort with allowed fields restriction
        >>> query = apply_sort(
        ...     query, Activity,
        ...     sort_by="name",
        ...     sort_order=SortOrder.ASC,
        ...     allowed_fields=["name", "created_at", "updated_at"]
        ... )
    """
    # Use default sort if no sort_by provided
    field_name = sort_by or default_sort

    # If still no field to sort by, return query unchanged
    if field_name is None:
        return query

    # Validate field exists on model
    if not hasattr(model, field_name):
        raise ValueError(
            f"Invalid sort field '{field_name}' for {model.__name__}. "
            f"Field does not exist on model."
        )

    # Validate field is allowed if allow list is provided
    if allowed_fields is not None and field_name not in allowed_fields:
        raise ValueError(
            f"Sort field '{field_name}' is not allowed for {model.__name__}. "
            f"Allowed fields: {', '.join(allowed_fields)}"
        )

    # Get the field from the model
    field = getattr(model, field_name)

    # Apply sort direction
    if sort_order == SortOrder.DESC:
        query = query.order_by(desc(field))
    else:
        query = query.order_by(asc(field))

    return query


def apply_multi_sort(
    query: Select,
    model: type[DeclarativeBase],
    sorts: list[tuple[str, SortOrder]],
    allowed_fields: Optional[list[str]] = None,
) -> Select:
    """Apply multiple sort criteria to a SQLAlchemy query.

    This function allows sorting by multiple fields with individual sort
    directions for each field.

    Args:
        query: SQLAlchemy select query
        model: SQLAlchemy model class
        sorts: List of (field_name, sort_order) tuples
        allowed_fields: Optional list of allowed field names for sorting

    Returns:
        Updated query with all sort criteria applied

    Raises:
        ValueError: If any sort field doesn't exist on model or isn't allowed

    Examples:
        >>> from app.models.activity import Activity
        >>> from sqlalchemy import select
        >>> query = select(Activity)
        >>> # Sort by difficulty descending, then name ascending
        >>> query = apply_multi_sort(
        ...     query, Activity,
        ...     sorts=[
        ...         ("difficulty", SortOrder.DESC),
        ...         ("name", SortOrder.ASC)
        ...     ]
        ... )
    """
    if not sorts:
        return query

    for field_name, sort_order in sorts:
        # Validate field exists
        if not hasattr(model, field_name):
            raise ValueError(
                f"Invalid sort field '{field_name}' for {model.__name__}. "
                f"Field does not exist on model."
            )

        # Validate field is allowed
        if allowed_fields is not None and field_name not in allowed_fields:
            raise ValueError(
                f"Sort field '{field_name}' is not allowed for {model.__name__}. "
                f"Allowed fields: {', '.join(allowed_fields)}"
            )

        # Get field and apply sort
        field = getattr(model, field_name)
        if sort_order == SortOrder.DESC:
            query = query.order_by(desc(field))
        else:
            query = query.order_by(asc(field))

    return query


# Common sortable field definitions for different entity types
ACTIVITY_SORTABLE_FIELDS = [
    "name",
    "created_at",
    "updated_at",
    "duration_minutes",
    "difficulty",
    "activity_type",
    "min_age_months",
    "max_age_months",
]

COACHING_SORTABLE_FIELDS = [
    "created_at",
    "category",
    "child_id",
    "user_id",
]

SEARCH_SORTABLE_FIELDS = [
    "relevance",
    "created_at",
    "entity_type",
]
