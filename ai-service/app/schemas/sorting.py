"""Sort option schemas for LAYA AI Service.

Provides schemas for defining sortable fields and sort options for different
entity types. These schemas are used to validate and document sort capabilities
for list endpoints.
"""

from enum import Enum
from typing import Optional

from pydantic import BaseModel, Field

from app.schemas.pagination import SortOrder


class ActivitySortField(str, Enum):
    """Sortable fields for activity list endpoints.

    Attributes:
        NAME: Sort by activity name alphabetically
        CREATED_AT: Sort by creation timestamp
        UPDATED_AT: Sort by last update timestamp
        DURATION_MINUTES: Sort by activity duration
        DIFFICULTY: Sort by difficulty level
        ACTIVITY_TYPE: Sort by activity type
        MIN_AGE_MONTHS: Sort by minimum age requirement
        MAX_AGE_MONTHS: Sort by maximum age requirement
    """

    NAME = "name"
    CREATED_AT = "created_at"
    UPDATED_AT = "updated_at"
    DURATION_MINUTES = "duration_minutes"
    DIFFICULTY = "difficulty"
    ACTIVITY_TYPE = "activity_type"
    MIN_AGE_MONTHS = "min_age_months"
    MAX_AGE_MONTHS = "max_age_months"


class CoachingSortField(str, Enum):
    """Sortable fields for coaching session list endpoints.

    Attributes:
        CREATED_AT: Sort by creation timestamp
        CATEGORY: Sort by coaching category
        CHILD_ID: Sort by child identifier
        USER_ID: Sort by user identifier
    """

    CREATED_AT = "created_at"
    CATEGORY = "category"
    CHILD_ID = "child_id"
    USER_ID = "user_id"


class SearchSortField(str, Enum):
    """Sortable fields for search results.

    Attributes:
        RELEVANCE: Sort by relevance score (default for search)
        CREATED_AT: Sort by creation timestamp
        ENTITY_TYPE: Sort by entity type
    """

    RELEVANCE = "relevance"
    CREATED_AT = "created_at"
    ENTITY_TYPE = "entity_type"


class SortOptions(BaseModel):
    """Sort options for list endpoints.

    Provides a structured way to specify sort criteria with validation.

    Attributes:
        field: Field name to sort by
        order: Sort direction (asc or desc)
    """

    field: str = Field(
        ...,
        description="Field name to sort by",
        min_length=1,
        max_length=50,
    )
    order: SortOrder = Field(
        default=SortOrder.ASC,
        description="Sort direction (asc or desc)",
    )


class MultiSortOptions(BaseModel):
    """Multiple sort criteria for list endpoints.

    Allows sorting by multiple fields with individual sort directions.
    Sorts are applied in the order specified.

    Attributes:
        sorts: List of sort criteria to apply in order
    """

    sorts: list[SortOptions] = Field(
        ...,
        description="List of sort criteria (applied in order)",
        min_length=1,
        max_length=5,
    )
