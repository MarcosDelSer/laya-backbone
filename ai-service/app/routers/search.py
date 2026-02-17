"""Search router for LAYA AI Service.

Provides API endpoints for full-text search across entities.
All endpoints require JWT authentication.
"""

from typing import Any

from fastapi import APIRouter, Depends, Query
from sqlalchemy.ext.asyncio import AsyncSession

from app.core.pagination import build_paginated_response
from app.database import get_db
from app.dependencies import get_current_user
from app.schemas.search import SearchResponse, SearchType
from app.services.search_service import SearchService

router = APIRouter(prefix="/api/v1/search", tags=["search"])


@router.get(
    "",
    response_model=SearchResponse,
    summary="Full-text search",
    description="Search across multiple entity types (activities, children, coaching sessions) "
    "using full-text search. Returns paginated results sorted by relevance.",
)
async def search(
    q: str = Query(
        ...,
        min_length=1,
        max_length=500,
        description="Search query string",
    ),
    types: list[SearchType] = Query(
        default=[SearchType.ALL],
        description="Entity types to search in (activities, children, coaching_sessions, or all)",
    ),
    page: int = Query(
        default=1,
        ge=1,
        description="Page number to retrieve (1-indexed)",
    ),
    per_page: int = Query(
        default=20,
        ge=1,
        le=100,
        description="Number of items per page (max 100)",
    ),
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> SearchResponse:
    """Search across multiple entity types using full-text search.

    This endpoint provides full-text search across activities, children,
    coaching sessions, and other entities. Results are sorted by relevance
    score and returned with pagination.

    Search uses PostgreSQL's full-text search capabilities for efficient
    and accurate results.

    Args:
        q: Search query string (1-500 characters)
        types: Entity types to search in (defaults to all)
        page: Page number to retrieve (1-indexed)
        per_page: Number of items per page (max 100)
        db: Async database session (injected)
        current_user: Authenticated user information (injected)

    Returns:
        SearchResponse with paginated results and metadata

    Raises:
        HTTPException: 401 if not authenticated
        HTTPException: 422 if validation fails

    Example:
        GET /api/v1/search?q=creative&types=activities&page=1&per_page=20
    """
    service = SearchService(db)

    # Calculate skip for pagination
    skip = (page - 1) * per_page

    # Perform search
    results, total = await service.search(
        query=q,
        types=types,
        skip=skip,
        limit=per_page,
    )

    # Build paginated response
    paginated = build_paginated_response(
        items=results,
        total=total,
        page=page,
        per_page=per_page,
    )

    # Add query to response
    return SearchResponse(
        items=paginated.items,
        total=paginated.total,
        page=paginated.page,
        per_page=paginated.per_page,
        total_pages=paginated.total_pages,
        query=q,
    )
