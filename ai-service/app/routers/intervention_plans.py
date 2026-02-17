"""FastAPI router for intervention plan management endpoints.

Provides comprehensive intervention plan management including CRUD operations,
versioning, progress tracking, and review scheduling. Implements the 8-part
intervention plan structure for children with special needs.

The 8-part structure includes:
1. Identification & History
2. Strengths
3. Needs
4. SMART Goals
5. Strategies
6. Monitoring
7. Parent Involvement
8. Consultations
"""

from typing import Any, Optional
from uuid import UUID

from fastapi import APIRouter, Depends, HTTPException, Query, status
from sqlalchemy.ext.asyncio import AsyncSession

from app.database import get_db
from app.dependencies import get_current_user
from app.schemas.intervention_plan import (
    ConsultationCreate,
    InterventionPlanCreate,
    InterventionPlanListResponse,
    InterventionPlanResponse,
    InterventionPlanStatus,
    InterventionPlanUpdate,
    MonitoringCreate,
    NeedCreate,
    ParentInvolvementCreate,
    ParentSignatureRequest,
    ParentSignatureResponse,
    PlanReviewReminderListResponse,
    ProgressCreate,
    ProgressResponse,
    SMARTGoalCreate,
    StrategyCreate,
    StrengthCreate,
    VersionCreate,
    VersionResponse,
)
from app.services.intervention_plan_service import (
    InterventionPlanService,
    InterventionPlanServiceError,
    InvalidPlanError,
    PlanNotFoundError,
    PlanVersionError,
    UnauthorizedAccessError,
)

router = APIRouter()


# =============================================================================
# Health Check
# =============================================================================


@router.get("/health")
async def health_check() -> dict[str, str]:
    """Health check endpoint for intervention plans router.

    Returns:
        Dict with status "ok"
    """
    return {"status": "ok"}


# =============================================================================
# Plan CRUD Operations
# =============================================================================


@router.post("", response_model=InterventionPlanResponse, status_code=status.HTTP_201_CREATED)
async def create_intervention_plan(
    request: InterventionPlanCreate,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> InterventionPlanResponse:
    """Create a new intervention plan with optional sections.

    Creates a comprehensive intervention plan with all 8 sections if provided.
    Automatically calculates the next review date based on review schedule.

    Args:
        request: The intervention plan creation request with all sections
        db: Async database session (injected)
        current_user: Authenticated user from JWT token (injected)

    Returns:
        InterventionPlanResponse with the created plan and all sections

    Raises:
        HTTPException 400: When the plan data is invalid
        HTTPException 401: When JWT token is missing or invalid
        HTTPException 500: When an unexpected error occurs
    """
    service = InterventionPlanService(db)

    try:
        user_id = UUID(current_user["sub"])
        return await service.create_plan(request, user_id)
    except InvalidPlanError as e:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=str(e),
        )
    except InterventionPlanServiceError as e:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Intervention plan service error: {str(e)}",
        )


@router.get("", response_model=InterventionPlanListResponse)
async def list_intervention_plans(
    child_id: Optional[UUID] = Query(default=None, description="Filter by child ID"),
    plan_status: Optional[InterventionPlanStatus] = Query(
        default=None, alias="status", description="Filter by plan status"
    ),
    skip: int = Query(default=0, ge=0, description="Number of records to skip"),
    limit: int = Query(default=100, ge=1, le=500, description="Maximum records to return"),
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> InterventionPlanListResponse:
    """List intervention plans with optional filters.

    Returns a paginated list of intervention plan summaries with support
    for filtering by child ID and plan status.

    Args:
        child_id: Optional filter by child ID
        plan_status: Optional filter by plan status
        skip: Number of records to skip for pagination
        limit: Maximum number of records to return
        db: Async database session (injected)
        current_user: Authenticated user from JWT token (injected)

    Returns:
        InterventionPlanListResponse with paginated plan summaries

    Raises:
        HTTPException 401: When JWT token is missing or invalid
        HTTPException 500: When an unexpected error occurs
    """
    service = InterventionPlanService(db)

    try:
        user_id = UUID(current_user["sub"])
        return await service.list_plans(
            user_id=user_id,
            child_id=child_id,
            status=plan_status,
            skip=skip,
            limit=limit,
        )
    except InterventionPlanServiceError as e:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Intervention plan service error: {str(e)}",
        )


@router.get("/pending-review", response_model=PlanReviewReminderListResponse)
async def get_plans_pending_review(
    days_ahead: int = Query(
        default=30, ge=1, le=365, description="Days ahead to look for upcoming reviews"
    ),
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> PlanReviewReminderListResponse:
    """Get plans that are due or upcoming for review.

    Returns plans where next_review_date is within the specified number
    of days or overdue. Useful for generating review reminders.

    Args:
        days_ahead: Number of days ahead to look for upcoming reviews
        db: Async database session (injected)
        current_user: Authenticated user from JWT token (injected)

    Returns:
        PlanReviewReminderListResponse with plans needing review

    Raises:
        HTTPException 401: When JWT token is missing or invalid
        HTTPException 500: When an unexpected error occurs
    """
    service = InterventionPlanService(db)

    try:
        user_id = UUID(current_user["sub"])
        return await service.get_plans_for_review(user_id, days_ahead)
    except InterventionPlanServiceError as e:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Intervention plan service error: {str(e)}",
        )


@router.get("/{plan_id}", response_model=InterventionPlanResponse)
async def get_intervention_plan(
    plan_id: UUID,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> InterventionPlanResponse:
    """Get an intervention plan by ID with all sections.

    Returns the complete intervention plan including all 8 sections,
    progress records, and version history.

    Args:
        plan_id: ID of the intervention plan
        db: Async database session (injected)
        current_user: Authenticated user from JWT token (injected)

    Returns:
        InterventionPlanResponse with all sections loaded

    Raises:
        HTTPException 401: When JWT token is missing or invalid
        HTTPException 404: When the plan is not found
        HTTPException 500: When an unexpected error occurs
    """
    service = InterventionPlanService(db)

    try:
        user_id = UUID(current_user["sub"])
        return await service.get_plan(plan_id, user_id)
    except PlanNotFoundError:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Intervention plan not found",
        )
    except UnauthorizedAccessError:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="You do not have access to this intervention plan",
        )
    except InterventionPlanServiceError as e:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Intervention plan service error: {str(e)}",
        )


@router.put("/{plan_id}", response_model=InterventionPlanResponse)
async def update_intervention_plan(
    plan_id: UUID,
    request: InterventionPlanUpdate,
    create_version: bool = Query(
        default=True, description="Whether to create a version record for this update"
    ),
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> InterventionPlanResponse:
    """Update an intervention plan.

    Updates the plan's Part 1 fields and metadata. Optionally creates
    a new version record to track changes (enabled by default).

    Args:
        plan_id: ID of the intervention plan to update
        request: The update request with fields to modify
        create_version: Whether to create a version record for this update
        db: Async database session (injected)
        current_user: Authenticated user from JWT token (injected)

    Returns:
        InterventionPlanResponse with updated plan

    Raises:
        HTTPException 400: When the update data is invalid
        HTTPException 401: When JWT token is missing or invalid
        HTTPException 404: When the plan is not found
        HTTPException 500: When an unexpected error occurs
    """
    service = InterventionPlanService(db)

    try:
        user_id = UUID(current_user["sub"])
        return await service.update_plan(plan_id, request, user_id, create_version)
    except PlanNotFoundError:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Intervention plan not found",
        )
    except UnauthorizedAccessError:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="You do not have access to this intervention plan",
        )
    except InvalidPlanError as e:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=str(e),
        )
    except InterventionPlanServiceError as e:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Intervention plan service error: {str(e)}",
        )


@router.delete("/{plan_id}", status_code=status.HTTP_204_NO_CONTENT)
async def delete_intervention_plan(
    plan_id: UUID,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> None:
    """Delete (archive) an intervention plan.

    Soft-deletes the plan by setting its status to ARCHIVED rather than
    performing a hard delete, preserving the data for audit purposes.

    Args:
        plan_id: ID of the intervention plan to delete
        db: Async database session (injected)
        current_user: Authenticated user from JWT token (injected)

    Raises:
        HTTPException 401: When JWT token is missing or invalid
        HTTPException 404: When the plan is not found
        HTTPException 500: When an unexpected error occurs
    """
    service = InterventionPlanService(db)

    try:
        user_id = UUID(current_user["sub"])
        await service.delete_plan(plan_id, user_id)
    except PlanNotFoundError:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Intervention plan not found",
        )
    except UnauthorizedAccessError:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="You do not have access to this intervention plan",
        )
    except InterventionPlanServiceError as e:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Intervention plan service error: {str(e)}",
        )


# =============================================================================
# Versioning Operations
# =============================================================================


@router.post("/{plan_id}/version", response_model=VersionResponse, status_code=status.HTTP_201_CREATED)
async def create_plan_version(
    plan_id: UUID,
    change_summary: Optional[str] = Query(
        default=None, max_length=2000, description="Summary of changes in this version"
    ),
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> VersionResponse:
    """Create a new version snapshot of the plan.

    Creates a version record capturing the current state of the plan
    for historical reference and audit trail.

    Args:
        plan_id: ID of the intervention plan
        change_summary: Optional description of changes
        db: Async database session (injected)
        current_user: Authenticated user from JWT token (injected)

    Returns:
        VersionResponse with the created version record

    Raises:
        HTTPException 401: When JWT token is missing or invalid
        HTTPException 404: When the plan is not found
        HTTPException 500: When an unexpected error occurs
    """
    service = InterventionPlanService(db)

    try:
        user_id = UUID(current_user["sub"])
        return await service.create_version(plan_id, user_id, change_summary)
    except PlanNotFoundError:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Intervention plan not found",
        )
    except UnauthorizedAccessError:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="You do not have access to this intervention plan",
        )
    except PlanVersionError as e:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=str(e),
        )
    except InterventionPlanServiceError as e:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Intervention plan service error: {str(e)}",
        )


@router.get("/{plan_id}/history", response_model=list[VersionResponse])
async def get_plan_history(
    plan_id: UUID,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> list[VersionResponse]:
    """Get the version history for an intervention plan.

    Returns all version snapshots in chronological order.

    Args:
        plan_id: ID of the intervention plan
        db: Async database session (injected)
        current_user: Authenticated user from JWT token (injected)

    Returns:
        List of VersionResponse records

    Raises:
        HTTPException 401: When JWT token is missing or invalid
        HTTPException 404: When the plan is not found
        HTTPException 500: When an unexpected error occurs
    """
    service = InterventionPlanService(db)

    try:
        user_id = UUID(current_user["sub"])
        return await service.get_plan_history(plan_id, user_id)
    except PlanNotFoundError:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Intervention plan not found",
        )
    except UnauthorizedAccessError:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="You do not have access to this intervention plan",
        )
    except InterventionPlanServiceError as e:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Intervention plan service error: {str(e)}",
        )


# =============================================================================
# Progress Tracking
# =============================================================================


@router.post("/{plan_id}/progress", response_model=ProgressResponse, status_code=status.HTTP_201_CREATED)
async def add_plan_progress(
    plan_id: UUID,
    request: ProgressCreate,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> ProgressResponse:
    """Add a progress record to the intervention plan.

    Records progress toward intervention goals with detailed notes
    and measurement values. Automatically updates goal status and
    progress percentage.

    Args:
        plan_id: ID of the intervention plan
        request: Progress record data
        db: Async database session (injected)
        current_user: Authenticated user from JWT token (injected)

    Returns:
        ProgressResponse with the created progress record

    Raises:
        HTTPException 400: When the progress data is invalid
        HTTPException 401: When JWT token is missing or invalid
        HTTPException 404: When the plan is not found
        HTTPException 500: When an unexpected error occurs
    """
    service = InterventionPlanService(db)

    try:
        user_id = UUID(current_user["sub"])
        return await service.add_progress(plan_id, request, user_id)
    except PlanNotFoundError:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Intervention plan not found",
        )
    except UnauthorizedAccessError:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="You do not have access to this intervention plan",
        )
    except InvalidPlanError as e:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=str(e),
        )
    except InterventionPlanServiceError as e:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Intervention plan service error: {str(e)}",
        )


# =============================================================================
# Parent Signature
# =============================================================================


@router.post("/{plan_id}/sign", response_model=ParentSignatureResponse)
async def sign_intervention_plan(
    plan_id: UUID,
    request: ParentSignatureRequest,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> ParentSignatureResponse:
    """Record parent signature on an intervention plan.

    Records the parent's digital signature and acknowledgment of
    the intervention plan. Once signed, the signature cannot be
    removed without creating a new plan version.

    Args:
        plan_id: ID of the intervention plan
        request: Parent signature data including signature and agreement
        db: Async database session (injected)
        current_user: Authenticated user from JWT token (injected)

    Returns:
        ParentSignatureResponse confirming the signature

    Raises:
        HTTPException 400: When the plan cannot be signed (already signed, etc.)
        HTTPException 401: When JWT token is missing or invalid
        HTTPException 404: When the plan is not found
        HTTPException 500: When an unexpected error occurs
    """
    service = InterventionPlanService(db)

    try:
        parent_id = UUID(current_user["sub"])
        return await service.sign_plan(plan_id, request, parent_id)
    except PlanNotFoundError:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Intervention plan not found",
        )
    except UnauthorizedAccessError:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="You do not have access to this intervention plan",
        )
    except InvalidPlanError as e:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=str(e),
        )
    except InterventionPlanServiceError as e:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Intervention plan service error: {str(e)}",
        )


# =============================================================================
# Section CRUD Operations
# =============================================================================


@router.post("/{plan_id}/strengths", response_model=InterventionPlanResponse, status_code=status.HTTP_201_CREATED)
async def add_strength(
    plan_id: UUID,
    request: StrengthCreate,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> InterventionPlanResponse:
    """Add a strength to an intervention plan (Part 2).

    Args:
        plan_id: ID of the intervention plan
        request: Strength data
        db: Async database session (injected)
        current_user: Authenticated user from JWT token (injected)

    Returns:
        Updated InterventionPlanResponse

    Raises:
        HTTPException 401: When JWT token is missing or invalid
        HTTPException 404: When the plan is not found
        HTTPException 500: When an unexpected error occurs
    """
    service = InterventionPlanService(db)

    try:
        user_id = UUID(current_user["sub"])
        return await service.add_strength(plan_id, request, user_id)
    except PlanNotFoundError:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Intervention plan not found",
        )
    except UnauthorizedAccessError:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="You do not have access to this intervention plan",
        )
    except InterventionPlanServiceError as e:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Intervention plan service error: {str(e)}",
        )


@router.post("/{plan_id}/needs", response_model=InterventionPlanResponse, status_code=status.HTTP_201_CREATED)
async def add_need(
    plan_id: UUID,
    request: NeedCreate,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> InterventionPlanResponse:
    """Add a need to an intervention plan (Part 3).

    Args:
        plan_id: ID of the intervention plan
        request: Need data
        db: Async database session (injected)
        current_user: Authenticated user from JWT token (injected)

    Returns:
        Updated InterventionPlanResponse

    Raises:
        HTTPException 401: When JWT token is missing or invalid
        HTTPException 404: When the plan is not found
        HTTPException 500: When an unexpected error occurs
    """
    service = InterventionPlanService(db)

    try:
        user_id = UUID(current_user["sub"])
        return await service.add_need(plan_id, request, user_id)
    except PlanNotFoundError:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Intervention plan not found",
        )
    except UnauthorizedAccessError:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="You do not have access to this intervention plan",
        )
    except InterventionPlanServiceError as e:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Intervention plan service error: {str(e)}",
        )


@router.post("/{plan_id}/goals", response_model=InterventionPlanResponse, status_code=status.HTTP_201_CREATED)
async def add_goal(
    plan_id: UUID,
    request: SMARTGoalCreate,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> InterventionPlanResponse:
    """Add a SMART goal to an intervention plan (Part 4).

    SMART Goals are:
    - Specific: Clear and well-defined
    - Measurable: Quantifiable outcomes
    - Achievable: Realistic and attainable
    - Relevant: Aligned with child's needs
    - Time-bound: Has a target date

    Args:
        plan_id: ID of the intervention plan
        request: SMART goal data
        db: Async database session (injected)
        current_user: Authenticated user from JWT token (injected)

    Returns:
        Updated InterventionPlanResponse

    Raises:
        HTTPException 401: When JWT token is missing or invalid
        HTTPException 404: When the plan is not found
        HTTPException 500: When an unexpected error occurs
    """
    service = InterventionPlanService(db)

    try:
        user_id = UUID(current_user["sub"])
        return await service.add_goal(plan_id, request, user_id)
    except PlanNotFoundError:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Intervention plan not found",
        )
    except UnauthorizedAccessError:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="You do not have access to this intervention plan",
        )
    except InterventionPlanServiceError as e:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Intervention plan service error: {str(e)}",
        )


@router.post("/{plan_id}/strategies", response_model=InterventionPlanResponse, status_code=status.HTTP_201_CREATED)
async def add_strategy(
    plan_id: UUID,
    request: StrategyCreate,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> InterventionPlanResponse:
    """Add a strategy to an intervention plan (Part 5).

    Args:
        plan_id: ID of the intervention plan
        request: Strategy data
        db: Async database session (injected)
        current_user: Authenticated user from JWT token (injected)

    Returns:
        Updated InterventionPlanResponse

    Raises:
        HTTPException 401: When JWT token is missing or invalid
        HTTPException 404: When the plan is not found
        HTTPException 500: When an unexpected error occurs
    """
    service = InterventionPlanService(db)

    try:
        user_id = UUID(current_user["sub"])
        return await service.add_strategy(plan_id, request, user_id)
    except PlanNotFoundError:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Intervention plan not found",
        )
    except UnauthorizedAccessError:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="You do not have access to this intervention plan",
        )
    except InterventionPlanServiceError as e:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Intervention plan service error: {str(e)}",
        )


@router.post("/{plan_id}/monitoring", response_model=InterventionPlanResponse, status_code=status.HTTP_201_CREATED)
async def add_monitoring(
    plan_id: UUID,
    request: MonitoringCreate,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> InterventionPlanResponse:
    """Add a monitoring approach to an intervention plan (Part 6).

    Args:
        plan_id: ID of the intervention plan
        request: Monitoring data
        db: Async database session (injected)
        current_user: Authenticated user from JWT token (injected)

    Returns:
        Updated InterventionPlanResponse

    Raises:
        HTTPException 401: When JWT token is missing or invalid
        HTTPException 404: When the plan is not found
        HTTPException 500: When an unexpected error occurs
    """
    service = InterventionPlanService(db)

    try:
        user_id = UUID(current_user["sub"])
        return await service.add_monitoring(plan_id, request, user_id)
    except PlanNotFoundError:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Intervention plan not found",
        )
    except UnauthorizedAccessError:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="You do not have access to this intervention plan",
        )
    except InterventionPlanServiceError as e:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Intervention plan service error: {str(e)}",
        )


@router.post("/{plan_id}/parent-involvements", response_model=InterventionPlanResponse, status_code=status.HTTP_201_CREATED)
async def add_parent_involvement(
    plan_id: UUID,
    request: ParentInvolvementCreate,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> InterventionPlanResponse:
    """Add a parent involvement activity to an intervention plan (Part 7).

    Args:
        plan_id: ID of the intervention plan
        request: Parent involvement data
        db: Async database session (injected)
        current_user: Authenticated user from JWT token (injected)

    Returns:
        Updated InterventionPlanResponse

    Raises:
        HTTPException 401: When JWT token is missing or invalid
        HTTPException 404: When the plan is not found
        HTTPException 500: When an unexpected error occurs
    """
    service = InterventionPlanService(db)

    try:
        user_id = UUID(current_user["sub"])
        return await service.add_parent_involvement(plan_id, request, user_id)
    except PlanNotFoundError:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Intervention plan not found",
        )
    except UnauthorizedAccessError:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="You do not have access to this intervention plan",
        )
    except InterventionPlanServiceError as e:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Intervention plan service error: {str(e)}",
        )


@router.post("/{plan_id}/consultations", response_model=InterventionPlanResponse, status_code=status.HTTP_201_CREATED)
async def add_consultation(
    plan_id: UUID,
    request: ConsultationCreate,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> InterventionPlanResponse:
    """Add a consultation to an intervention plan (Part 8).

    Args:
        plan_id: ID of the intervention plan
        request: Consultation data
        db: Async database session (injected)
        current_user: Authenticated user from JWT token (injected)

    Returns:
        Updated InterventionPlanResponse

    Raises:
        HTTPException 401: When JWT token is missing or invalid
        HTTPException 404: When the plan is not found
        HTTPException 500: When an unexpected error occurs
    """
    service = InterventionPlanService(db)

    try:
        user_id = UUID(current_user["sub"])
        return await service.add_consultation(plan_id, request, user_id)
    except PlanNotFoundError:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Intervention plan not found",
        )
    except UnauthorizedAccessError:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="You do not have access to this intervention plan",
        )
    except InterventionPlanServiceError as e:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Intervention plan service error: {str(e)}",
        )
