"""Search schemas for LAYA AI Service.

Provides schemas for full-text search across multiple entity types.
"""

from enum import Enum
from typing import Any, Optional, Union
from uuid import UUID

from pydantic import BaseModel, Field

from app.schemas.activity import ActivityResponse
from app.schemas.base import BaseSchema
from app.schemas.pagination import PaginatedResponse


class SearchType(str, Enum):
    """Types of entities that can be searched.

    Attributes:
        ACTIVITIES: Search in activities
        CHILDREN: Search in children (placeholder for future implementation)
        COACHING_SESSIONS: Search in coaching sessions
        ALL: Search across all entity types
    """

    ACTIVITIES = "activities"
    CHILDREN = "children"
    COACHING_SESSIONS = "coaching_sessions"
    ALL = "all"


class SearchRequest(BaseModel):
    """Request schema for full-text search.

    Attributes:
        q: Search query string
        types: Entity types to search in
        page: Page number to retrieve (1-indexed)
        per_page: Number of items per page
    """

    q: str = Field(
        ...,
        min_length=1,
        max_length=500,
        description="Search query string",
    )
    types: list[SearchType] = Field(
        default=[SearchType.ALL],
        description="Entity types to search in",
    )
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


class SearchResultType(str, Enum):
    """Type discriminator for search results.

    Attributes:
        ACTIVITY: Activity search result
        CHILD: Child search result
        COACHING_SESSION: Coaching session search result
    """

    ACTIVITY = "activity"
    CHILD = "child"
    COACHING_SESSION = "coaching_session"


class SearchResult(BaseSchema):
    """Single search result with type and relevance.

    Attributes:
        type: Type of entity in this result
        id: Unique identifier of the entity
        title: Display title for the result
        description: Brief description or snippet
        relevance_score: Search relevance score (0-1)
        data: Full entity data (type varies by result type)
    """

    type: SearchResultType = Field(
        ...,
        description="Type of entity in this result",
    )
    id: UUID = Field(
        ...,
        description="Unique identifier of the entity",
    )
    title: str = Field(
        ...,
        max_length=500,
        description="Display title for the result",
    )
    description: str = Field(
        ...,
        max_length=2000,
        description="Brief description or snippet",
    )
    relevance_score: float = Field(
        ...,
        ge=0.0,
        le=1.0,
        description="Search relevance score (0-1)",
    )
    data: Union[ActivityResponse, dict[str, Any]] = Field(
        ...,
        description="Full entity data (type varies by result type)",
    )


class SearchResponse(PaginatedResponse[SearchResult]):
    """Paginated search results response.

    Attributes:
        items: List of search results
        query: The original search query
    """

    query: str = Field(
        ...,
        description="The original search query",
    )
