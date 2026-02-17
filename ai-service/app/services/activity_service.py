"""Activity service for LAYA AI Service.

Provides business logic for activity recommendations and management.
Implements multi-factor filtering and relevance scoring algorithm.
"""

from datetime import datetime
from typing import Optional
from uuid import UUID

from sqlalchemy import and_, func, or_, select
from sqlalchemy.ext.asyncio import AsyncSession

from app.core.cache import cache, invalidate_cache, invalidate_on_write
from app.models.activity import (
    Activity,
    ActivityParticipation,
    ActivityRecommendation as ActivityRecommendationModel,
    ActivityType,
)
from app.schemas.activity import (
    ActivityRecommendation,
    ActivityRecommendationResponse,
    ActivityResponse,
    AgeRange,
)


class ActivityService:
    """Service class for activity recommendation and management logic.

    Encapsulates business logic for generating personalized activity
    recommendations based on child profile, preferences, and contextual factors.

    Attributes:
        db: Async database session for database operations.
    """

    def __init__(self, db: AsyncSession) -> None:
        """Initialize ActivityService with database session.

        Args:
            db: Async database session for database operations.
        """
        self.db = db

    async def get_recommendations(
        self,
        child_id: UUID,
        max_recommendations: int = 5,
        activity_types: Optional[list[str]] = None,
        child_age_months: Optional[int] = None,
        weather: Optional[str] = None,
        group_size: Optional[int] = None,
        include_special_needs: bool = True,
    ) -> ActivityRecommendationResponse:
        """Generate personalized activity recommendations for a child.

        Implements multi-factor filtering and relevance scoring:
        1. Age-appropriate filtering based on child's age in months
        2. Activity type filtering if specified
        3. Weather/indoor-outdoor preference consideration
        4. Group size compatibility
        5. Participation history weighting (deprioritize recent activities)

        Args:
            child_id: Unique identifier of the child.
            max_recommendations: Maximum number of recommendations to return.
            activity_types: Optional filter for specific activity types.
            child_age_months: Child's age in months for age-appropriate filtering.
            weather: Current weather condition (sunny, rainy, etc.).
            group_size: Current group size for compatibility filtering.
            include_special_needs: Whether to include special needs adaptations.

        Returns:
            ActivityRecommendationResponse with scored recommendations.
        """
        # Build base query for active activities
        query = select(Activity).where(Activity.is_active == True)

        # Apply age filtering if child age provided
        if child_age_months is not None:
            query = query.where(
                and_(
                    or_(
                        Activity.min_age_months.is_(None),
                        Activity.min_age_months <= child_age_months,
                    ),
                    or_(
                        Activity.max_age_months.is_(None),
                        Activity.max_age_months >= child_age_months,
                    ),
                )
            )

        # Apply activity type filtering if specified
        if activity_types:
            type_values = []
            for t in activity_types:
                try:
                    type_values.append(ActivityType(t) if isinstance(t, str) else t)
                except ValueError:
                    # Skip invalid activity types gracefully
                    continue
            if type_values:
                query = query.where(Activity.activity_type.in_(type_values))

        # Execute query and fetch activities
        result = await self.db.execute(query)
        activities = result.scalars().all()

        # Get participation history for the child
        participation_query = (
            select(ActivityParticipation)
            .where(ActivityParticipation.child_id == child_id)
            .order_by(ActivityParticipation.started_at.desc())
        )
        participation_result = await self.db.execute(participation_query)
        participations = participation_result.scalars().all()

        # Build participation lookup for scoring
        participation_counts: dict[UUID, int] = {}
        recent_participations: set[UUID] = set()
        for p in participations:
            activity_id = p.activity_id
            participation_counts[activity_id] = (
                participation_counts.get(activity_id, 0) + 1
            )
            # Mark activities participated in within last 7 days as recent
            if (datetime.utcnow() - p.started_at.replace(tzinfo=None)).days < 7:
                recent_participations.add(activity_id)

        # Score and filter activities
        scored_activities: list[tuple[Activity, float, str]] = []
        for activity in activities:
            score, reasoning = self._calculate_relevance_score(
                activity=activity,
                child_age_months=child_age_months,
                weather=weather,
                group_size=group_size,
                participation_count=participation_counts.get(activity.id, 0),
                is_recent=activity.id in recent_participations,
                include_special_needs=include_special_needs,
            )
            scored_activities.append((activity, score, reasoning))

        # Sort by score descending and limit results
        scored_activities.sort(key=lambda x: x[1], reverse=True)
        top_activities = scored_activities[:max_recommendations]

        # Convert to response schema
        recommendations: list[ActivityRecommendation] = []
        for activity, score, reasoning in top_activities:
            activity_response = self._activity_to_response(activity)
            recommendations.append(
                ActivityRecommendation(
                    activity=activity_response,
                    relevance_score=round(score, 3),
                    reasoning=reasoning,
                )
            )

        return ActivityRecommendationResponse(
            child_id=child_id,
            recommendations=recommendations,
            generated_at=datetime.utcnow(),
        )

    def _calculate_relevance_score(
        self,
        activity: Activity,
        child_age_months: Optional[int],
        weather: Optional[str],
        group_size: Optional[int],
        participation_count: int,
        is_recent: bool,
        include_special_needs: bool,
    ) -> tuple[float, str]:
        """Calculate relevance score for an activity based on multiple factors.

        Scoring factors:
        - Base score: 0.5
        - Age match bonus: +0.2 (if age is in the sweet spot)
        - Weather/indoor-outdoor match: +0.1
        - Group size compatibility: +0.1
        - Special needs adaptations: +0.05 (if requested and available)
        - Novelty bonus: +0.1 (if not participated recently)
        - Participation penalty: -0.05 per previous participation (max -0.2)

        Args:
            activity: The activity to score.
            child_age_months: Child's age in months.
            weather: Current weather condition.
            group_size: Current group size.
            participation_count: Number of times child participated.
            is_recent: Whether child participated recently.
            include_special_needs: Whether special needs are relevant.

        Returns:
            Tuple of (score between 0-1, reasoning string).
        """
        score = 0.5  # Base score
        reasoning_parts: list[str] = []

        # Age appropriateness scoring
        if child_age_months is not None:
            age_score, age_reason = self._score_age_match(activity, child_age_months)
            score += age_score
            if age_reason:
                reasoning_parts.append(age_reason)
        else:
            reasoning_parts.append("Age not specified")

        # Weather/indoor-outdoor consideration
        weather_score, weather_reason = self._score_weather_match(activity, weather)
        score += weather_score
        if weather_reason:
            reasoning_parts.append(weather_reason)

        # Group size compatibility
        group_score, group_reason = self._score_group_size(activity, group_size)
        score += group_score
        if group_reason:
            reasoning_parts.append(group_reason)

        # Special needs adaptations bonus
        if include_special_needs and activity.special_needs_adaptations:
            score += 0.05
            reasoning_parts.append("Has special needs adaptations")

        # Novelty and participation history
        if not is_recent:
            score += 0.1
            reasoning_parts.append("Fresh activity suggestion")
        else:
            reasoning_parts.append("Recently participated")

        # Participation frequency penalty (encourage variety)
        if participation_count > 0:
            penalty = min(participation_count * 0.05, 0.2)
            score -= penalty
            reasoning_parts.append(f"Participated {participation_count} time(s) before")

        # Clamp score to valid range
        score = max(0.0, min(1.0, score))

        reasoning = "; ".join(reasoning_parts) if reasoning_parts else "Standard recommendation"
        return score, reasoning

    def _score_age_match(
        self, activity: Activity, child_age_months: int
    ) -> tuple[float, str]:
        """Score how well an activity matches the child's age.

        Args:
            activity: The activity to score.
            child_age_months: Child's age in months.

        Returns:
            Tuple of (score adjustment, reasoning).
        """
        min_age = activity.min_age_months
        max_age = activity.max_age_months

        if min_age is None and max_age is None:
            return 0.1, "Suitable for all ages"

        # Calculate sweet spot (middle 60% of age range)
        if min_age is not None and max_age is not None:
            range_size = max_age - min_age
            sweet_spot_start = min_age + (range_size * 0.2)
            sweet_spot_end = max_age - (range_size * 0.2)

            if sweet_spot_start <= child_age_months <= sweet_spot_end:
                return 0.2, "Perfect age match"
            elif min_age <= child_age_months <= max_age:
                return 0.1, "Good age match"

        return 0.0, "Age at boundary of range"

    def _score_weather_match(
        self, activity: Activity, weather: Optional[str]
    ) -> tuple[float, str]:
        """Score activity based on weather compatibility.

        Note: The Activity model doesn't have indoor_outdoor field in the
        current implementation, so this is a placeholder for future enhancement.

        Args:
            activity: The activity to score.
            weather: Current weather condition.

        Returns:
            Tuple of (score adjustment, reasoning).
        """
        if weather is None:
            return 0.0, ""

        # Default to both indoor/outdoor suitable
        # Future enhancement: add indoor_outdoor field to Activity model
        weather_lower = weather.lower()
        if weather_lower in ("rainy", "stormy", "cold"):
            return 0.05, "Indoor activity available"
        elif weather_lower in ("sunny", "warm", "clear"):
            return 0.05, "Great for any environment"

        return 0.0, ""

    def _score_group_size(
        self, activity: Activity, group_size: Optional[int]
    ) -> tuple[float, str]:
        """Score activity based on group size compatibility.

        Note: The Activity model doesn't have group size fields in the
        current implementation, so this is a placeholder for future enhancement.

        Args:
            activity: The activity to score.
            group_size: Current group size.

        Returns:
            Tuple of (score adjustment, reasoning).
        """
        if group_size is None:
            return 0.0, ""

        # Default scoring based on activity type
        # Future enhancement: add min_group_size/max_group_size to Activity model
        if activity.activity_type == ActivityType.SOCIAL and group_size > 1:
            return 0.1, "Great for groups"
        elif group_size == 1:
            return 0.05, "Suitable for individual activity"

        return 0.0, ""

    def _activity_to_response(self, activity: Activity) -> ActivityResponse:
        """Convert Activity model to ActivityResponse schema.

        Args:
            activity: The Activity model instance.

        Returns:
            ActivityResponse schema instance.
        """
        age_range = None
        if activity.min_age_months is not None or activity.max_age_months is not None:
            age_range = AgeRange(
                min_months=activity.min_age_months or 0,
                max_months=activity.max_age_months or 144,
            )

        return ActivityResponse(
            id=activity.id,
            name=activity.name,
            description=activity.description,
            activity_type=activity.activity_type.value,
            difficulty=activity.difficulty.value,
            duration_minutes=activity.duration_minutes,
            materials_needed=activity.materials_needed or [],
            age_range=age_range,
            special_needs_adaptations=activity.special_needs_adaptations,
            is_active=activity.is_active,
            created_at=activity.created_at,
            updated_at=activity.updated_at,
        )

    async def get_activity_by_id(self, activity_id: UUID) -> Optional[Activity]:
        """Retrieve a single activity by ID.

        Args:
            activity_id: Unique identifier of the activity.

        Returns:
            Activity if found, None otherwise.
        """
        # Use cast for SQLite compatibility (TEXT storage) while maintaining PostgreSQL compatibility
        from sqlalchemy import cast, String
        query = select(Activity).where(
            cast(Activity.id, String) == str(activity_id)
        )
        result = await self.db.execute(query)
        return result.scalar_one_or_none()

    async def list_activities(
        self,
        skip: int = 0,
        limit: int = 100,
        activity_type: Optional[str] = None,
        is_active: Optional[bool] = True,
    ) -> tuple[list[Activity], int]:
        """List activities with optional filtering and pagination.

        Args:
            skip: Number of records to skip.
            limit: Maximum number of records to return.
            activity_type: Optional filter by activity type.
            is_active: Optional filter by active status.

        Returns:
            Tuple of (list of activities, total count).
        """
        # Build base query
        query = select(Activity)

        if is_active is not None:
            query = query.where(Activity.is_active == is_active)

        if activity_type:
            query = query.where(Activity.activity_type == ActivityType(activity_type))

        # Get total count
        count_query = select(func.count()).select_from(query.subquery())
        count_result = await self.db.execute(count_query)
        total = count_result.scalar() or 0

        # Apply pagination
        query = query.offset(skip).limit(limit).order_by(Activity.created_at.desc())

        result = await self.db.execute(query)
        activities = list(result.scalars().all())

        return activities, total

    @invalidate_on_write("analytics_dashboard")
    async def record_participation(
        self,
        child_id: UUID,
        activity_id: UUID,
        duration_minutes: Optional[int] = None,
        completion_status: str = "started",
        engagement_score: Optional[float] = None,
        notes: Optional[str] = None,
    ) -> ActivityParticipation:
        """Record a child's participation in an activity.

        This method invalidates the analytics dashboard cache since
        participation data affects engagement and activity metrics.

        Args:
            child_id: Unique identifier of the child.
            activity_id: Unique identifier of the activity.
            duration_minutes: Actual duration of participation.
            completion_status: Status of completion (started, completed, abandoned).
            engagement_score: Engagement level (0-1).
            notes: Additional notes about the participation.

        Returns:
            The created ActivityParticipation record.
        """
        participation = ActivityParticipation(
            child_id=child_id,
            activity_id=activity_id,
            duration_minutes=duration_minutes,
            completion_status=completion_status,
            engagement_score=engagement_score,
            notes=notes,
        )
        self.db.add(participation)
        await self.db.commit()
        await self.db.refresh(participation)
        return participation

    @invalidate_on_write("analytics_dashboard")
    async def save_recommendation(
        self,
        child_id: UUID,
        activity_id: UUID,
        relevance_score: float,
        reasoning: Optional[str] = None,
    ) -> ActivityRecommendationModel:
        """Save a generated recommendation to the database.

        This method invalidates the analytics dashboard cache since
        recommendation data may affect analytics calculations.

        Args:
            child_id: Unique identifier of the child.
            activity_id: Unique identifier of the activity.
            relevance_score: Calculated relevance score.
            reasoning: Explanation for the recommendation.

        Returns:
            The created ActivityRecommendation record.
        """
        recommendation = ActivityRecommendationModel(
            child_id=child_id,
            activity_id=activity_id,
            relevance_score=relevance_score,
            reasoning=reasoning,
        )
        self.db.add(recommendation)
        await self.db.commit()
        await self.db.refresh(recommendation)
        return recommendation

    @cache(ttl=3600, key_prefix="activity_catalog")
    async def get_activity_catalog(
        self,
        activity_type: Optional[str] = None,
    ) -> list[dict]:
        """Fetch the complete activity catalog with caching.

        This method fetches all active activities from the database and caches
        them in Redis with a 1-hour (3600 seconds) TTL. Subsequent requests
        within 1 hour will be served from cache.

        The catalog can be optionally filtered by activity type.

        Args:
            activity_type: Optional filter for specific activity type

        Returns:
            list[dict]: List of activity data as dictionaries
        """
        # Build query for all active activities
        query = select(Activity).where(Activity.is_active == True)

        # Apply activity type filter if specified
        if activity_type:
            try:
                type_value = ActivityType(activity_type)
                query = query.where(Activity.activity_type == type_value)
            except ValueError:
                # Invalid activity type, return empty list
                return []

        # Order by name for consistent ordering
        query = query.order_by(Activity.name)

        # Execute query
        result = await self.db.execute(query)
        activities = result.scalars().all()

        # Convert to dictionaries for caching
        catalog = []
        for activity in activities:
            catalog.append({
                "id": str(activity.id),
                "name": activity.name,
                "description": activity.description,
                "activity_type": activity.activity_type.value,
                "difficulty": activity.difficulty.value,
                "duration_minutes": activity.duration_minutes,
                "materials_needed": activity.materials_needed or [],
                "min_age_months": activity.min_age_months,
                "max_age_months": activity.max_age_months,
                "special_needs_adaptations": activity.special_needs_adaptations,
                "is_active": activity.is_active,
                "created_at": activity.created_at.isoformat(),
                "updated_at": activity.updated_at.isoformat(),
            })

        return catalog

    async def get_activity_catalog_validated(
        self,
        activity_type: Optional[str] = None,
    ) -> list[ActivityResponse]:
        """Fetch and validate the activity catalog.

        This method fetches the activity catalog and validates it against
        the ActivityResponse schema.

        Args:
            activity_type: Optional filter for specific activity type

        Returns:
            list[ActivityResponse]: List of validated activity data
        """
        catalog_data = await self.get_activity_catalog(activity_type)

        validated_catalog: list[ActivityResponse] = []
        for activity_data in catalog_data:
            # Convert back from dict to ActivityResponse
            age_range = None
            if activity_data.get("min_age_months") is not None or activity_data.get("max_age_months") is not None:
                age_range = AgeRange(
                    min_months=activity_data.get("min_age_months") or 0,
                    max_months=activity_data.get("max_age_months") or 144,
                )

            validated_catalog.append(ActivityResponse(
                id=UUID(activity_data["id"]),
                name=activity_data["name"],
                description=activity_data["description"],
                activity_type=activity_data["activity_type"],
                difficulty=activity_data["difficulty"],
                duration_minutes=activity_data["duration_minutes"],
                materials_needed=activity_data["materials_needed"],
                age_range=age_range,
                special_needs_adaptations=activity_data.get("special_needs_adaptations"),
                is_active=activity_data["is_active"],
                created_at=datetime.fromisoformat(activity_data["created_at"]),
                updated_at=datetime.fromisoformat(activity_data["updated_at"]),
            ))

        return validated_catalog

    async def invalidate_activity_catalog_cache(
        self,
        activity_type: Optional[str] = None,
    ) -> int:
        """Invalidate cached activity catalog.

        Args:
            activity_type: Specific activity type to invalidate, or None to invalidate all

        Returns:
            int: Number of cache entries deleted
        """
        if activity_type:
            # Invalidate specific activity type catalog cache
            pattern = f"*{activity_type}*"
        else:
            # Invalidate all activity catalog caches
            pattern = "*"

        return await invalidate_cache("activity_catalog", pattern)

    async def refresh_activity_catalog_cache(
        self,
        activity_type: Optional[str] = None,
    ) -> list[dict]:
        """Refresh activity catalog cache by invalidating and refetching.

        Args:
            activity_type: Optional filter for specific activity type

        Returns:
            list[dict]: Fresh activity catalog data
        """
        # Invalidate existing cache
        await self.invalidate_activity_catalog_cache(activity_type)

        # Fetch fresh data (which will be cached)
        return await self.get_activity_catalog(activity_type)
