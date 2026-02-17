"""Activity router for LAYA AI Service.

Provides API endpoints for activity recommendations and management.
All endpoints require JWT authentication.
"""

from typing import Any, Optional
from uuid import UUID

from fastapi import APIRouter, Depends, HTTPException, Query
from sqlalchemy.ext.asyncio import AsyncSession

from app.database import get_db
from app.dependencies import get_current_user
from app.schemas.activity import (
    ActivityListResponse,
    ActivityRecommendationResponse,
    ActivityResponse,
    ActivityType,
)
from app.services.activity_service import ActivityService
from app.utils.field_selection import FieldSelector, get_field_selector

router = APIRouter(prefix="/api/v1/activities", tags=["activities"])


@router.get(
    "/recommendations/{child_id}",
    response_model=ActivityRecommendationResponse,
    summary="Get personalized activity recommendations",
    description="Generate personalized activity recommendations for a child based on "
    "their profile, preferences, and contextual factors like weather and group size.",
)
async def get_recommendations(
    child_id: UUID,
    max_recommendations: int = Query(
        default=5,
        ge=1,
        le=20,
        description="Maximum number of recommendations to return",
    ),
    child_age_months: Optional[int] = Query(
        default=None,
        ge=0,
        le=144,
        description="Child's age in months for age-appropriate filtering (0-144)",
    ),
    activity_types: Optional[list[str]] = Query(
        default=None,
        description="Filter by specific activity types (cognitive, motor, social, etc.)",
    ),
    weather: Optional[str] = Query(
        default=None,
        description="Current weather condition for indoor/outdoor recommendations",
    ),
    group_size: Optional[int] = Query(
        default=None,
        ge=1,
        description="Current group size for compatibility filtering",
    ),
    include_special_needs: bool = Query(
        default=True,
        description="Whether to include special needs adaptations in scoring",
    ),
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> ActivityRecommendationResponse:
    """Get personalized activity recommendations for a child.

    This endpoint generates personalized activity recommendations based on
    multiple factors including:
    - Child's age (for age-appropriate activities)
    - Activity type preferences
    - Current weather conditions
    - Group size considerations
    - Participation history (to promote variety)
    - Special needs adaptations

    The recommendations are scored using a multi-factor relevance algorithm
    that considers all these factors to provide the most suitable activities.

    Args:
        child_id: Unique identifier of the child to get recommendations for.
        max_recommendations: Maximum number of recommendations to return (1-20).
        child_age_months: Child's age in months for filtering (0-144 months).
        activity_types: Optional filter for specific activity types.
        weather: Current weather condition (sunny, rainy, etc.).
        group_size: Current group size for compatibility.
        include_special_needs: Whether to factor in special needs adaptations.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        ActivityRecommendationResponse containing scored recommendations.

    Raises:
        HTTPException: 401 if not authenticated.

    Example:
        GET /api/v1/activities/recommendations/123e4567-e89b-12d3-a456-426614174000?
            max_recommendations=5&child_age_months=36&weather=sunny
    """
    service = ActivityService(db)
    return await service.get_recommendations(
        child_id=child_id,
        max_recommendations=max_recommendations,
        activity_types=activity_types,
        child_age_months=child_age_months,
        weather=weather,
        group_size=group_size,
        include_special_needs=include_special_needs,
    )


@router.get(
    "/{activity_id}",
    summary="Get activity by ID",
    description="Retrieve a single activity by its unique identifier. "
    "Supports field selection via ?fields= parameter to reduce payload size.",
)
async def get_activity(
    activity_id: UUID,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
    field_selector: FieldSelector = Depends(get_field_selector),
) -> dict:
    """Get a single activity by ID with optional field selection.

    This endpoint supports field selection via the ?fields= query parameter,
    allowing clients to request only the fields they need, reducing response
    payload size and improving performance.

    Args:
        activity_id: Unique identifier of the activity.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).
        field_selector: Field selector for response filtering (injected).

    Returns:
        dict: Activity details (filtered if fields parameter is provided).

    Raises:
        HTTPException: 404 if activity not found.
        HTTPException: 401 if not authenticated.

    Example:
        # Get all fields
        GET /api/v1/activities/123e4567-e89b-12d3-a456-426614174000

        # Get only specific fields
        GET /api/v1/activities/123e4567-e89b-12d3-a456-426614174000?fields=id,name,description
    """
    service = ActivityService(db)
    activity = await service.get_activity_by_id(activity_id)

    if activity is None:
        raise HTTPException(
            status_code=404,
            detail=f"Activity with id {activity_id} not found",
        )

    response = service._activity_to_response(activity)
    return field_selector.filter_fields(response, model_class=ActivityResponse)


@router.get(
    "",
    response_model=ActivityListResponse,
    summary="List activities",
    description="List all activities with optional filtering and pagination.",
)
async def list_activities(
    skip: int = Query(
        default=0,
        ge=0,
        description="Number of records to skip for pagination",
    ),
    limit: int = Query(
        default=20,
        ge=1,
        le=100,
        description="Maximum number of records to return",
    ),
    activity_type: Optional[str] = Query(
        default=None,
        description="Filter by activity type",
    ),
    is_active: Optional[bool] = Query(
        default=True,
        description="Filter by active status",
    ),
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> ActivityListResponse:
    """List activities with optional filtering and pagination.

    Args:
        skip: Number of records to skip.
        limit: Maximum number of records to return.
        activity_type: Optional filter by activity type.
        is_active: Optional filter by active status.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        ActivityListResponse with paginated list of activities.

    Raises:
        HTTPException: 401 if not authenticated.
    """
    service = ActivityService(db)
    activities, total = await service.list_activities(
        skip=skip,
        limit=limit,
        activity_type=activity_type,
        is_active=is_active,
    )

    items = [service._activity_to_response(activity) for activity in activities]

    return ActivityListResponse(
        items=items,
        total=total,
        skip=skip,
        limit=limit,
    )
