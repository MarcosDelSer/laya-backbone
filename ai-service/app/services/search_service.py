"""Search service for LAYA AI Service.

Provides full-text search functionality across multiple entity types
using PostgreSQL's tsvector and tsquery capabilities.
Falls back to ILIKE for databases that don't support tsvector (e.g., SQLite for testing).
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
    and other entities using PostgreSQL full-text search with automatic
    fallback to ILIKE for SQLite testing.

    Attributes:
        db: Async database session
    """

    def __init__(self, db: AsyncSession):
        """Initialize search service.

        Args:
            db: Async database session
        """
        self.db = db

    def _is_postgresql(self) -> bool:
        """Check if the database is PostgreSQL.

        Returns:
            bool: True if using PostgreSQL, False otherwise
        """
        # Get the database dialect name
        dialect_name = self.db.bind.dialect.name if self.db.bind else ""
        return dialect_name == "postgresql"

    async def search_activities(
        self,
        query: str,
        skip: int = 0,
        limit: int = 20,
    ) -> tuple[list[SearchResult], int]:
        """Search activities using full-text search.

        Uses PostgreSQL's tsvector and tsquery for efficient full-text
        search across activity names, descriptions, and special needs adaptations.
        Results are ranked by relevance using ts_rank.

        Falls back to ILIKE for databases that don't support tsvector (e.g., SQLite).

        Args:
            query: Search query string
            skip: Number of records to skip
            limit: Maximum number of records to return

        Returns:
            Tuple of (search results, total count)
        """
        search_term = query.strip()

        # Check if we're using PostgreSQL with tsvector support
        if self._is_postgresql():
            # Use PostgreSQL tsvector full-text search
            return await self._search_activities_pg(search_term, skip, limit)
        else:
            # Fallback to ILIKE for SQLite/other databases
            return await self._search_activities_fallback(search_term, skip, limit)

    async def _search_activities_pg(
        self,
        search_term: str,
        skip: int = 0,
        limit: int = 20,
    ) -> tuple[list[SearchResult], int]:
        """Search activities using PostgreSQL tsvector.

        Args:
            search_term: Search query string
            skip: Number of records to skip
            limit: Maximum number of records to return

        Returns:
            Tuple of (search results, total count)
        """
        # Create tsquery from search term
        # plainto_tsquery automatically handles stop words, stemming, and special chars
        tsquery = func.plainto_tsquery('english', search_term)

        # Calculate relevance score using ts_rank
        # ts_rank scores how well the document matches the query
        relevance = func.ts_rank(Activity.search_vector, tsquery).label('relevance')

        # Build query using tsvector full-text search
        stmt = (
            select(Activity, relevance)
            .where(
                Activity.is_active == True,  # noqa: E712
            )
            .where(
                Activity.search_vector.op('@@')(tsquery)
            )
            .order_by(relevance.desc())
            .offset(skip)
            .limit(limit)
        )

        result = await self.db.execute(stmt)
        rows = result.all()

        # Get total count
        count_stmt = (
            select(func.count())
            .select_from(Activity)
            .where(
                Activity.is_active == True,  # noqa: E712
            )
            .where(
                Activity.search_vector.op('@@')(tsquery)
            )
        )
        count_result = await self.db.execute(count_stmt)
        total = count_result.scalar_one()

        # Convert to search results
        results = []
        for activity, relevance_score in rows:
            # Normalize relevance score to 0-1 range
            # ts_rank typically returns values between 0 and 1, but can be higher
            # Clamp to max of 1.0
            normalized_score = min(relevance_score, 1.0)

            results.append(self._activity_to_search_result(activity, normalized_score))

        return results, total

    async def _search_activities_fallback(
        self,
        search_term: str,
        skip: int = 0,
        limit: int = 20,
    ) -> tuple[list[SearchResult], int]:
        """Search activities using ILIKE fallback for SQLite.

        Args:
            search_term: Search query string
            skip: Number of records to skip
            limit: Maximum number of records to return

        Returns:
            Tuple of (search results, total count)
        """
        # Build query using ILIKE for simple pattern matching
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

            results.append(self._activity_to_search_result(activity, relevance))

        return results, total

    def _activity_to_search_result(
        self,
        activity: Activity,
        relevance_score: float,
    ) -> SearchResult:
        """Convert an Activity to a SearchResult.

        Args:
            activity: Activity model instance
            relevance_score: Relevance score for this result

        Returns:
            SearchResult instance
        """
        return SearchResult(
            type=SearchResultType.ACTIVITY,
            id=activity.id,
            title=activity.name,
            description=activity.description[:200] + "..."
            if len(activity.description) > 200
            else activity.description,
            relevance_score=relevance_score,
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
