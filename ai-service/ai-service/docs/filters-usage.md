# Filter Parameters Usage Guide

This guide demonstrates how to use the filter parameters implemented for the LAYA AI Service.

## Overview

The filter system provides standardized filtering capabilities for list endpoints including:
- **Date range filtering**: Filter by creation and update timestamps
- **Status filtering**: Filter by active/inactive state or custom status values
- **Type filtering**: Filter by entity types (activity types, categories, etc.)
- **Range filtering**: Filter by numeric ranges (duration, age, etc.)

## Filter Schemas

### DateRangeFilter

Basic date range filtering for any date field:

```python
from app.schemas.filters import DateRangeFilter
from datetime import datetime

# Filter for items within a date range
date_filter = DateRangeFilter(
    start_date=datetime(2024, 1, 1),
    end_date=datetime(2024, 12, 31)
)
```

### StatusFilter

Filter by active state or custom status:

```python
from app.schemas.filters import StatusFilter

# Filter for active items only
status_filter = StatusFilter(is_active=True)

# Filter by specific status value
status_filter = StatusFilter(status="completed")
```

### TypeFilter

Filter by entity types (uses OR logic):

```python
from app.schemas.filters import TypeFilter

# Filter for cognitive or motor activities
type_filter = TypeFilter(types=["cognitive", "motor"])
```

### ActivityFilters

Comprehensive filtering for activities:

```python
from app.schemas.filters import ActivityFilters
from datetime import datetime, timedelta

# Full activity filtering
filters = ActivityFilters(
    created_after=datetime.now() - timedelta(days=30),
    created_before=datetime.now(),
    is_active=True,
    activity_types=["cognitive", "motor"],
    difficulty="medium",
    min_duration_minutes=30,
    max_duration_minutes=60
)
```

### CoachingFilters

Filtering for coaching sessions:

```python
from app.schemas.filters import CoachingFilters
from datetime import datetime, timedelta

# Filter coaching sessions
filters = CoachingFilters(
    created_after=datetime.now() - timedelta(days=7),
    child_id="child-123",
    categories=["behavior", "communication"],
    special_need_types=["autism", "adhd"]
)
```

## Helper Functions

### Applying Filters to SQLAlchemy Queries

```python
from sqlalchemy import select
from app.models.activity import Activity
from app.core.filters import apply_activity_filters
from datetime import datetime, timedelta

# Build base query
query = select(Activity)

# Apply filters
filtered_query = apply_activity_filters(
    query,
    Activity,
    created_after=datetime.now() - timedelta(days=30),
    is_active=True,
    activity_types=["cognitive", "motor"],
    min_duration_minutes=30,
    max_duration_minutes=60
)

# Execute query
results = await session.execute(filtered_query)
activities = results.scalars().all()
```

### Individual Filter Helpers

```python
from app.core.filters import (
    apply_date_range_filter,
    apply_status_filter,
    apply_type_filter,
    apply_range_filter
)

# Apply date range filter
query = apply_date_range_filter(
    query,
    Activity,
    "created_at",
    start_date=datetime(2024, 1, 1),
    end_date=datetime(2024, 12, 31)
)

# Apply status filter
query = apply_status_filter(query, Activity, is_active=True)

# Apply type filter
query = apply_type_filter(
    query,
    Activity,
    "activity_type",
    types=["cognitive", "motor"]
)

# Apply range filter
query = apply_range_filter(
    query,
    Activity,
    "duration_minutes",
    min_value=30,
    max_value=60
)
```

## API Endpoint Integration

### Example: Activity List Endpoint with Filters

```python
from fastapi import APIRouter, Depends, Query
from app.schemas.filters import ActivityFilters
from app.schemas.pagination import PaginatedRequest, PaginatedResponse
from app.schemas.activity import ActivityResponse

router = APIRouter()

@router.get("/activities", response_model=PaginatedResponse[ActivityResponse])
async def list_activities(
    # Pagination parameters
    pagination: PaginatedRequest = Depends(),

    # Filter parameters
    created_after: Optional[datetime] = Query(None),
    created_before: Optional[datetime] = Query(None),
    is_active: Optional[bool] = Query(None),
    activity_types: Optional[list[str]] = Query(None),
    difficulty: Optional[str] = Query(None),
    min_duration_minutes: Optional[int] = Query(None),
    max_duration_minutes: Optional[int] = Query(None),

    db: AsyncSession = Depends(get_db),
):
    # Build filters
    filters = ActivityFilters(
        created_after=created_after,
        created_before=created_before,
        is_active=is_active,
        activity_types=activity_types,
        difficulty=difficulty,
        min_duration_minutes=min_duration_minutes,
        max_duration_minutes=max_duration_minutes,
    )

    # Build query with filters
    query = select(Activity)
    query = apply_activity_filters(query, Activity, **filters.model_dump())

    # Apply pagination
    query = query.limit(pagination.per_page).offset(
        (pagination.page - 1) * pagination.per_page
    )

    # Execute query
    results = await db.execute(query)
    activities = results.scalars().all()

    # Get total count
    count_query = select(func.count()).select_from(Activity)
    count_query = apply_activity_filters(count_query, Activity, **filters.model_dump())
    total = await db.scalar(count_query)

    # Build response
    return build_paginated_response(
        items=activities,
        total=total,
        page=pagination.page,
        per_page=pagination.per_page
    )
```

## Validation

All filter schemas include validation:

```python
# Date range validation
try:
    filter_obj = DateRangeFilter(
        start_date=datetime(2024, 12, 31),
        end_date=datetime(2024, 1, 1)  # Error: before start_date
    )
except ValidationError as e:
    print(e)  # "end_date must not be before start_date"

# Empty list validation
try:
    filter_obj = TypeFilter(types=[])  # Error: empty list
except ValidationError as e:
    print(e)  # "types must not be an empty list"

# Duration range validation
try:
    filters = ActivityFilters(
        min_duration_minutes=60,
        max_duration_minutes=30  # Error: max < min
    )
except ValidationError as e:
    print(e)  # "max_duration_minutes must not be less than min_duration_minutes"
```

## Best Practices

1. **Always validate filters on the API layer**: Use Pydantic schemas for automatic validation
2. **Use helper functions for consistency**: The helper functions ensure filters are applied correctly
3. **Combine filters logically**: All filters use AND logic, except type filters which use OR
4. **Handle None values**: All filter parameters are optional - None values are ignored
5. **Index filtered fields**: Ensure database fields used in filters have appropriate indexes
6. **Document filter options**: Clearly document available filter options in API docs

## Testing

Comprehensive tests are available in `tests/test_filters.py`:

```bash
# Run all filter tests
pytest tests/test_filters.py -v

# Run specific test class
pytest tests/test_filters.py::TestActivityFilters -v

# Run with coverage
pytest tests/test_filters.py --cov=app/schemas/filters --cov=app/core/filters
```

## Performance Considerations

1. **Use indexes**: Ensure filtered fields have database indexes
2. **Limit date ranges**: Very large date ranges can impact performance
3. **Combine with pagination**: Always use filters with pagination for large datasets
4. **Consider caching**: Cache frequently used filter combinations
5. **Monitor query performance**: Use query logging to identify slow filters
