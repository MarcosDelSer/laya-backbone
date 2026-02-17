"""Search service for LAYA AI Service.

Provides full-text search functionality across multiple entity types
using PostgreSQL's tsvector and tsquery capabilities.
"""

from typing import Optional
from uuid import UUID

from sqlalchemy import or_, select, func
from sqlalchemy.ext.asyncio import AsyncSession

from app.models.activity import Activity
from app.schemas.activity import ActivityResponse
from app.schemas.search import SearchResult, SearchResultType, SearchType


class SearchService:
    """Service for full-text search across entities.

    Provides methods for searching activities, children, coaching sessions,
    and other entities using PostgreSQL full-text search.

    Attributes:
        db: Async database session
    """

    def __init__(self, db: AsyncSession):
        """Initialize search service.

        Args:
            db: Async database session
        """
        self.db = db

    async def search_activities(
        self,
        query: str,
        skip: int = 0,
        limit: int = 20,
    ) -> tuple[list[SearchResult], int]:
        """Search activities using full-text search.

        Uses PostgreSQL's tsvector and tsquery for efficient full-text
        search across activity names, descriptions, and materials.

        Args:
            query: Search query string
            skip: Number of records to skip
            limit: Maximum number of records to return

        Returns:
            Tuple of (search results, total count)
        """
        # Create search term for PostgreSQL full-text search
        # Use plainto_tsquery for simple search (no operators needed)
        search_term = query.strip()

        # Build query using ILIKE for simple pattern matching
        # In production, this should use tsvector/tsquery for better performance
        stmt = (
            select(Activity)
            .where(
                Activity.is_active == True,  # noqa: E712
            )
            .where(
                or_(
                    Activity.name.ilike(f"%{search_term}%"),
                    Activity.description.ilike(f"%{search_term}%"),
                    Activity.special_needs_adaptations.ilike(f"%{search_term}%"),
                )
            )
            .order_by(Activity.created_at.desc())
            .offset(skip)
            .limit(limit)
        )

        result = await self.db.execute(stmt)
        activities = result.scalars().all()

        # Get total count
        count_stmt = (
            select(func.count())
            .select_from(Activity)
            .where(
                Activity.is_active == True,  # noqa: E712
            )
            .where(
                or_(
                    Activity.name.ilike(f"%{search_term}%"),
                    Activity.description.ilike(f"%{search_term}%"),
                    Activity.special_needs_adaptations.ilike(f"%{search_term}%"),
                )
            )
        )
        count_result = await self.db.execute(count_stmt)
        total = count_result.scalar_one()

        # Convert to search results
        results = []
        for activity in activities:
            # Calculate a simple relevance score based on match location
            relevance = 1.0
            if search_term.lower() in activity.name.lower():
                relevance = 1.0
            elif search_term.lower() in activity.description.lower():
                relevance = 0.8
            else:
                relevance = 0.6

            result = SearchResult(
                type=SearchResultType.ACTIVITY,
                id=activity.id,
                title=activity.name,
                description=activity.description[:200] + "..."
                if len(activity.description) > 200
                else activity.description,
                relevance_score=relevance,
                data=ActivityResponse(
                    id=activity.id,
                    name=activity.name,
                    description=activity.description,
                    activity_type=activity.activity_type,
                    difficulty=activity.difficulty,
                    duration_minutes=activity.duration_minutes,
                    materials_needed=activity.materials_needed,
                    age_range={
                        "min_months": activity.min_age_months,
                        "max_months": activity.max_age_months,
                    }
                    if activity.min_age_months is not None
                    and activity.max_age_months is not None
                    else None,
                    special_needs_adaptations=activity.special_needs_adaptations,
                    is_active=activity.is_active,
                    created_at=activity.created_at,
                    updated_at=activity.updated_at,
                ),
            )
            results.append(result)

        return results, total

    async def search_children(
        self,
        query: str,
        skip: int = 0,
        limit: int = 20,
    ) -> tuple[list[SearchResult], int]:
        """Search children (placeholder for future implementation).

        Args:
            query: Search query string
            skip: Number of records to skip
            limit: Maximum number of records to return

        Returns:
            Tuple of (empty search results, 0 count)

        Note:
            Children data is managed by gibbon-service. This is a placeholder
            for future cross-service search integration.
        """
        # Placeholder - children are managed by gibbon-service
        return [], 0

    async def search_coaching_sessions(
        self,
        query: str,
        skip: int = 0,
        limit: int = 20,
    ) -> tuple[list[SearchResult], int]:
        """Search coaching sessions (placeholder for future implementation).

        Args:
            query: Search query string
            skip: Number of records to skip
            limit: Maximum number of records to return

        Returns:
            Tuple of (empty search results, 0 count)

        Note:
            This is a placeholder for future coaching session search.
        """
        # Placeholder for future implementation
        return [], 0

    async def search(
        self,
        query: str,
        types: list[SearchType],
        skip: int = 0,
        limit: int = 20,
    ) -> tuple[list[SearchResult], int]:
        """Search across multiple entity types.

        Args:
            query: Search query string
            types: Entity types to search in
            skip: Number of records to skip
            limit: Maximum number of records to return

        Returns:
            Tuple of (combined search results, total count)
        """
        all_results: list[SearchResult] = []
        total_count = 0

        # Determine which types to search
        search_all = SearchType.ALL in types
        search_activities = search_all or SearchType.ACTIVITIES in types
        search_children = search_all or SearchType.CHILDREN in types
        search_coaching = search_all or SearchType.COACHING_SESSIONS in types

        # Search activities
        if search_activities:
            activity_results, activity_total = await self.search_activities(
                query, skip=0, limit=limit * 2
            )
            all_results.extend(activity_results)
            total_count += activity_total

        # Search children (placeholder)
        if search_children:
            children_results, children_total = await self.search_children(
                query, skip=0, limit=limit * 2
            )
            all_results.extend(children_results)
            total_count += children_total

        # Search coaching sessions (placeholder)
        if search_coaching:
            coaching_results, coaching_total = await self.search_coaching_sessions(
                query, skip=0, limit=limit * 2
            )
            all_results.extend(coaching_results)
            total_count += coaching_total

        # Sort all results by relevance score
        all_results.sort(key=lambda r: r.relevance_score, reverse=True)

        # Apply pagination to combined results
        paginated_results = all_results[skip : skip + limit]

        return paginated_results, total_count
