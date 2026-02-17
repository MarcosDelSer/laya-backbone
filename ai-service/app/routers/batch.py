"""Batch operations router for LAYA AI Service.

Provides batch API endpoints that allow clients to perform multiple
operations in a single HTTP request, reducing network round-trips
and improving application performance.
"""

from datetime import datetime
from typing import Any
from uuid import UUID

from fastapi import APIRouter, Depends, HTTPException, status
from sqlalchemy.ext.asyncio import AsyncSession

from app.database import get_db
from app.dependencies import get_current_user
from app.schemas.batch import (
    BatchActivityRecommendationRequest,
    BatchActivityRecommendationResponse,
    BatchGetRequest,
    BatchGetResponse,
    BatchOperationResult,
    BatchOperationStatus,
)
from app.services.activity_service import ActivityService
from app.utils.field_selection import FieldSelector

router = APIRouter(prefix="/api/v1/batch", tags=["batch"])


@router.post(
    "/get",
    response_model=BatchGetResponse,
    summary="Batch GET operation",
    description="Fetch multiple resources by ID in a single request. "
    "Supports activities and other resource types. Returns partial results "
    "if some resources are not found.",
)
async def batch_get(
    request: BatchGetRequest,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> BatchGetResponse:
    """Batch GET operation for fetching multiple resources.

    This endpoint allows clients to fetch multiple resources by ID in a single
    HTTP request, significantly reducing network round-trips compared to making
    individual GET requests for each resource.

    Supported resource types:
    - activities: Fetch multiple activities by ID

    Features:
    - Partial success: Returns results for all requested IDs, marking missing ones as errors
    - Field selection: Supports ?fields= parameter for response optimization
    - Parallel processing: Fetches resources efficiently in batch

    Args:
        request: Batch GET request with resource type and IDs
        db: Database session (injected)
        current_user: Authenticated user (injected)

    Returns:
        BatchGetResponse with results for each requested resource

    Raises:
        HTTPException: 400 if resource type is not supported
        HTTPException: 401 if not authenticated

    Example:
        POST /api/v1/batch/get
        {
            "resource_type": "activities",
            "ids": ["uuid1", "uuid2", "uuid3"],
            "fields": "id,name,description"
        }
    """
    results: list[BatchOperationResult] = []
    total_succeeded = 0
    total_failed = 0

    # Route to appropriate service based on resource type
    if request.resource_type == "activities":
        activity_service = ActivityService(db)

        # Create field selector if fields parameter provided
        field_selector = FieldSelector(requested_fields=request.fields)

        for resource_id in request.ids:
            try:
                activity = await activity_service.get_activity_by_id(resource_id)

                if activity is None:
                    results.append(
                        BatchOperationResult(
                            id=resource_id,
                            status=BatchOperationStatus.ERROR,
                            error=f"Activity with id {resource_id} not found",
                            status_code=404,
                        )
                    )
                    total_failed += 1
                else:
                    # Convert to response and apply field selection
                    activity_response = activity_service._activity_to_response(activity)
                    filtered_data = field_selector.filter_fields(
                        activity_response,
                        model_class=type(activity_response),
                    )

                    results.append(
                        BatchOperationResult(
                            id=resource_id,
                            status=BatchOperationStatus.SUCCESS,
                            data=filtered_data,
                            status_code=200,
                        )
                    )
                    total_succeeded += 1
            except Exception as e:
                results.append(
                    BatchOperationResult(
                        id=resource_id,
                        status=BatchOperationStatus.ERROR,
                        error=str(e),
                        status_code=500,
                    )
                )
                total_failed += 1
    else:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=f"Unsupported resource type: {request.resource_type}. "
            f"Supported types: activities",
        )

    return BatchGetResponse(
        resource_type=request.resource_type,
        results=results,
        total_requested=len(request.ids),
        total_succeeded=total_succeeded,
        total_failed=total_failed,
        processed_at=datetime.utcnow(),
    )


@router.post(
    "/activities/recommendations",
    response_model=BatchActivityRecommendationResponse,
    summary="Batch activity recommendations",
    description="Get personalized activity recommendations for multiple children "
    "in a single request. Reduces round-trips when loading recommendations "
    "for multiple children (e.g., in a classroom view).",
)
async def batch_activity_recommendations(
    request: BatchActivityRecommendationRequest,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> BatchActivityRecommendationResponse:
    """Get batch activity recommendations for multiple children.

    This endpoint allows fetching personalized activity recommendations for
    multiple children in a single request. This is particularly useful for
    classroom views where recommendations need to be displayed for multiple
    children simultaneously.

    Features:
    - Batch processing: Get recommendations for up to 50 children in one request
    - Partial success: Returns results for all children, marking failures as errors
    - Same filtering: Applies same filters (age, weather, etc.) to all children
    - Performance: Significantly faster than individual requests

    Args:
        request: Batch recommendation request with child IDs and filters
        db: Database session (injected)
        current_user: Authenticated user (injected)

    Returns:
        BatchActivityRecommendationResponse with recommendations per child

    Raises:
        HTTPException: 401 if not authenticated

    Example:
        POST /api/v1/batch/activities/recommendations
        {
            "child_ids": ["uuid1", "uuid2"],
            "max_recommendations": 5,
            "weather": "sunny",
            "group_size": 10
        }
    """
    activity_service = ActivityService(db)
    results: list[BatchOperationResult] = []
    total_succeeded = 0
    total_failed = 0

    for child_id in request.child_ids:
        try:
            recommendations = await activity_service.get_recommendations(
                child_id=child_id,
                max_recommendations=request.max_recommendations,
                activity_types=request.activity_types,
                child_age_months=request.child_age_months,
                weather=request.weather,
                group_size=request.group_size,
                include_special_needs=request.include_special_needs,
            )

            results.append(
                BatchOperationResult(
                    id=child_id,
                    status=BatchOperationStatus.SUCCESS,
                    data=recommendations.model_dump(),
                    status_code=200,
                )
            )
            total_succeeded += 1
        except Exception as e:
            results.append(
                BatchOperationResult(
                    id=child_id,
                    status=BatchOperationStatus.ERROR,
                    error=str(e),
                    status_code=500,
                )
            )
            total_failed += 1

    return BatchActivityRecommendationResponse(
        results=results,
        total_requested=len(request.child_ids),
        total_succeeded=total_succeeded,
        total_failed=total_failed,
        processed_at=datetime.utcnow(),
    )
