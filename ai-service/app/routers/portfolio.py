"""Portfolio router for LAYA AI Service.

Provides API endpoints for educational portfolio management including
portfolio items, observations, milestones, and work samples.
All endpoints require JWT authentication.
"""

from datetime import date
from typing import Any, Optional
from uuid import UUID

from fastapi import APIRouter, Depends, HTTPException, Query
from sqlalchemy.ext.asyncio import AsyncSession

from app.database import get_db
from app.dependencies import get_current_user
from app.schemas.portfolio import (
    MilestoneCreate,
    MilestoneListResponse,
    MilestoneResponse,
    MilestoneUpdate,
    ObservationCreate,
    ObservationListResponse,
    ObservationResponse,
    ObservationUpdate,
    PortfolioItemCreate,
    PortfolioItemListResponse,
    PortfolioItemResponse,
    PortfolioItemUpdate,
    PortfolioSummary,
    WorkSampleCreate,
    WorkSampleListResponse,
    WorkSampleResponse,
    WorkSampleUpdate,
)
from app.services.portfolio_service import (
    MilestoneNotFoundError,
    ObservationNotFoundError,
    PortfolioItemNotFoundError,
    PortfolioService,
    WorkSampleNotFoundError,
)

router = APIRouter(prefix="/api/v1/portfolio", tags=["portfolio"])


# =============================================================================
# Portfolio Item Endpoints
# =============================================================================


@router.post(
    "/items",
    response_model=PortfolioItemResponse,
    status_code=201,
    summary="Create portfolio item",
    description="Create a new portfolio item (photo, video, document, etc.) for a child.",
)
async def create_portfolio_item(
    data: PortfolioItemCreate,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> PortfolioItemResponse:
    """Create a new portfolio item.

    Args:
        data: Portfolio item creation data.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        PortfolioItemResponse with the created item.

    Raises:
        HTTPException: 401 if not authenticated.
    """
    service = PortfolioService(db)
    item = await service.create_portfolio_item(data)
    return PortfolioItemResponse.model_validate(item)


@router.get(
    "/items/{item_id}",
    response_model=PortfolioItemResponse,
    summary="Get portfolio item by ID",
    description="Retrieve a single portfolio item by its unique identifier.",
)
async def get_portfolio_item(
    item_id: UUID,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> PortfolioItemResponse:
    """Get a single portfolio item by ID.

    Args:
        item_id: Unique identifier of the portfolio item.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        PortfolioItemResponse with item details.

    Raises:
        HTTPException: 404 if item not found.
        HTTPException: 401 if not authenticated.
    """
    service = PortfolioService(db)
    item = await service.get_portfolio_item_by_id(item_id)

    if item is None:
        raise HTTPException(
            status_code=404,
            detail=f"Portfolio item with id {item_id} not found",
        )

    return PortfolioItemResponse.model_validate(item)


@router.get(
    "/children/{child_id}/items",
    response_model=PortfolioItemListResponse,
    summary="List portfolio items for a child",
    description="List all portfolio items for a child with optional filtering and pagination.",
)
async def list_portfolio_items(
    child_id: UUID,
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
    item_type: Optional[str] = Query(
        default=None,
        description="Filter by item type (photo, video, document, audio, other)",
    ),
    privacy_level: Optional[str] = Query(
        default=None,
        description="Filter by privacy level (private, family, shared)",
    ),
    include_archived: bool = Query(
        default=False,
        description="Whether to include archived items",
    ),
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> PortfolioItemListResponse:
    """List portfolio items for a child with optional filtering and pagination.

    Args:
        child_id: Unique identifier of the child.
        skip: Number of records to skip.
        limit: Maximum number of records to return.
        item_type: Optional filter by item type.
        privacy_level: Optional filter by privacy level.
        include_archived: Whether to include archived items.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        PortfolioItemListResponse with paginated list of items.

    Raises:
        HTTPException: 401 if not authenticated.
    """
    service = PortfolioService(db)
    items, total = await service.list_portfolio_items(
        child_id=child_id,
        skip=skip,
        limit=limit,
        item_type=item_type,
        privacy_level=privacy_level,
        include_archived=include_archived,
    )

    response_items = [PortfolioItemResponse.model_validate(item) for item in items]

    return PortfolioItemListResponse(
        items=response_items,
        total=total,
        skip=skip,
        limit=limit,
    )


@router.patch(
    "/items/{item_id}",
    response_model=PortfolioItemResponse,
    summary="Update portfolio item",
    description="Update an existing portfolio item with partial data.",
)
async def update_portfolio_item(
    item_id: UUID,
    data: PortfolioItemUpdate,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> PortfolioItemResponse:
    """Update a portfolio item.

    Args:
        item_id: Unique identifier of the portfolio item.
        data: Portfolio item update data.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        PortfolioItemResponse with updated item.

    Raises:
        HTTPException: 404 if item not found.
        HTTPException: 401 if not authenticated.
    """
    service = PortfolioService(db)
    try:
        item = await service.update_portfolio_item(item_id, data)
        return PortfolioItemResponse.model_validate(item)
    except PortfolioItemNotFoundError:
        raise HTTPException(
            status_code=404,
            detail=f"Portfolio item with id {item_id} not found",
        )


@router.delete(
    "/items/{item_id}",
    status_code=204,
    summary="Delete portfolio item",
    description="Delete a portfolio item (soft delete by archiving).",
)
async def delete_portfolio_item(
    item_id: UUID,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> None:
    """Delete a portfolio item (soft delete).

    Args:
        item_id: Unique identifier of the portfolio item.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Raises:
        HTTPException: 404 if item not found.
        HTTPException: 401 if not authenticated.
    """
    service = PortfolioService(db)
    try:
        await service.delete_portfolio_item(item_id)
    except PortfolioItemNotFoundError:
        raise HTTPException(
            status_code=404,
            detail=f"Portfolio item with id {item_id} not found",
        )


# =============================================================================
# Observation Endpoints
# =============================================================================


@router.post(
    "/observations",
    response_model=ObservationResponse,
    status_code=201,
    summary="Create observation",
    description="Create a new observation note for a child.",
)
async def create_observation(
    data: ObservationCreate,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> ObservationResponse:
    """Create a new observation.

    Args:
        data: Observation creation data.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        ObservationResponse with the created observation.

    Raises:
        HTTPException: 401 if not authenticated.
    """
    service = PortfolioService(db)
    observation = await service.create_observation(data)
    return ObservationResponse.model_validate(observation)


@router.get(
    "/observations/{observation_id}",
    response_model=ObservationResponse,
    summary="Get observation by ID",
    description="Retrieve a single observation by its unique identifier.",
)
async def get_observation(
    observation_id: UUID,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> ObservationResponse:
    """Get a single observation by ID.

    Args:
        observation_id: Unique identifier of the observation.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        ObservationResponse with observation details.

    Raises:
        HTTPException: 404 if observation not found.
        HTTPException: 401 if not authenticated.
    """
    service = PortfolioService(db)
    observation = await service.get_observation_by_id(observation_id)

    if observation is None:
        raise HTTPException(
            status_code=404,
            detail=f"Observation with id {observation_id} not found",
        )

    return ObservationResponse.model_validate(observation)


@router.get(
    "/children/{child_id}/observations",
    response_model=ObservationListResponse,
    summary="List observations for a child",
    description="List all observations for a child with optional filtering and pagination.",
)
async def list_observations(
    child_id: UUID,
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
    observation_type: Optional[str] = Query(
        default=None,
        description="Filter by observation type (anecdotal, running_record, learning_story, checklist, photo_documentation)",
    ),
    observer_id: Optional[UUID] = Query(
        default=None,
        description="Filter by observer ID",
    ),
    date_from: Optional[date] = Query(
        default=None,
        description="Filter observations from this date",
    ),
    date_to: Optional[date] = Query(
        default=None,
        description="Filter observations until this date",
    ),
    include_archived: bool = Query(
        default=False,
        description="Whether to include archived observations",
    ),
    shared_with_family_only: bool = Query(
        default=False,
        description="Only return observations shared with family",
    ),
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> ObservationListResponse:
    """List observations for a child with optional filtering and pagination.

    Args:
        child_id: Unique identifier of the child.
        skip: Number of records to skip.
        limit: Maximum number of records to return.
        observation_type: Optional filter by observation type.
        observer_id: Optional filter by observer.
        date_from: Optional filter for observations from this date.
        date_to: Optional filter for observations until this date.
        include_archived: Whether to include archived observations.
        shared_with_family_only: Only return observations shared with family.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        ObservationListResponse with paginated list of observations.

    Raises:
        HTTPException: 401 if not authenticated.
    """
    service = PortfolioService(db)
    observations, total = await service.list_observations(
        child_id=child_id,
        skip=skip,
        limit=limit,
        observation_type=observation_type,
        observer_id=observer_id,
        date_from=date_from,
        date_to=date_to,
        include_archived=include_archived,
        shared_with_family_only=shared_with_family_only,
    )

    response_items = [
        ObservationResponse.model_validate(obs) for obs in observations
    ]

    return ObservationListResponse(
        items=response_items,
        total=total,
        skip=skip,
        limit=limit,
    )


@router.patch(
    "/observations/{observation_id}",
    response_model=ObservationResponse,
    summary="Update observation",
    description="Update an existing observation with partial data.",
)
async def update_observation(
    observation_id: UUID,
    data: ObservationUpdate,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> ObservationResponse:
    """Update an observation.

    Args:
        observation_id: Unique identifier of the observation.
        data: Observation update data.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        ObservationResponse with updated observation.

    Raises:
        HTTPException: 404 if observation not found.
        HTTPException: 401 if not authenticated.
    """
    service = PortfolioService(db)
    try:
        observation = await service.update_observation(observation_id, data)
        return ObservationResponse.model_validate(observation)
    except ObservationNotFoundError:
        raise HTTPException(
            status_code=404,
            detail=f"Observation with id {observation_id} not found",
        )


@router.delete(
    "/observations/{observation_id}",
    status_code=204,
    summary="Delete observation",
    description="Delete an observation (soft delete by archiving).",
)
async def delete_observation(
    observation_id: UUID,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> None:
    """Delete an observation (soft delete).

    Args:
        observation_id: Unique identifier of the observation.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Raises:
        HTTPException: 404 if observation not found.
        HTTPException: 401 if not authenticated.
    """
    service = PortfolioService(db)
    try:
        await service.delete_observation(observation_id)
    except ObservationNotFoundError:
        raise HTTPException(
            status_code=404,
            detail=f"Observation with id {observation_id} not found",
        )


# =============================================================================
# Milestone Endpoints
# =============================================================================


@router.post(
    "/milestones",
    response_model=MilestoneResponse,
    status_code=201,
    summary="Create milestone",
    description="Create a new developmental milestone for a child.",
)
async def create_milestone(
    data: MilestoneCreate,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> MilestoneResponse:
    """Create a new milestone.

    Args:
        data: Milestone creation data.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        MilestoneResponse with the created milestone.

    Raises:
        HTTPException: 401 if not authenticated.
    """
    service = PortfolioService(db)
    milestone = await service.create_milestone(data)
    return MilestoneResponse.model_validate(milestone)


@router.get(
    "/milestones/{milestone_id}",
    response_model=MilestoneResponse,
    summary="Get milestone by ID",
    description="Retrieve a single milestone by its unique identifier.",
)
async def get_milestone(
    milestone_id: UUID,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> MilestoneResponse:
    """Get a single milestone by ID.

    Args:
        milestone_id: Unique identifier of the milestone.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        MilestoneResponse with milestone details.

    Raises:
        HTTPException: 404 if milestone not found.
        HTTPException: 401 if not authenticated.
    """
    service = PortfolioService(db)
    milestone = await service.get_milestone_by_id(milestone_id)

    if milestone is None:
        raise HTTPException(
            status_code=404,
            detail=f"Milestone with id {milestone_id} not found",
        )

    return MilestoneResponse.model_validate(milestone)


@router.get(
    "/children/{child_id}/milestones",
    response_model=MilestoneListResponse,
    summary="List milestones for a child",
    description="List all milestones for a child with optional filtering and pagination.",
)
async def list_milestones(
    child_id: UUID,
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
    category: Optional[str] = Query(
        default=None,
        description="Filter by milestone category (cognitive, motor_gross, motor_fine, language, social_emotional, self_care)",
    ),
    status: Optional[str] = Query(
        default=None,
        description="Filter by milestone status (not_started, emerging, developing, achieved)",
    ),
    is_flagged: Optional[bool] = Query(
        default=None,
        description="Filter by flagged status",
    ),
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> MilestoneListResponse:
    """List milestones for a child with optional filtering and pagination.

    Args:
        child_id: Unique identifier of the child.
        skip: Number of records to skip.
        limit: Maximum number of records to return.
        category: Optional filter by milestone category.
        status: Optional filter by milestone status.
        is_flagged: Optional filter by flagged status.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        MilestoneListResponse with paginated list of milestones.

    Raises:
        HTTPException: 401 if not authenticated.
    """
    service = PortfolioService(db)
    milestones, total = await service.list_milestones(
        child_id=child_id,
        skip=skip,
        limit=limit,
        category=category,
        status=status,
        is_flagged=is_flagged,
    )

    response_items = [MilestoneResponse.model_validate(m) for m in milestones]

    return MilestoneListResponse(
        items=response_items,
        total=total,
        skip=skip,
        limit=limit,
    )


@router.get(
    "/children/{child_id}/milestones/progress",
    response_model=dict[str, dict[str, int]],
    summary="Get milestone progress summary",
    description="Get a summary of milestone progress by category for a child.",
)
async def get_milestone_progress(
    child_id: UUID,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> dict[str, dict[str, int]]:
    """Get milestone progress summary by category.

    Args:
        child_id: Unique identifier of the child.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        Dictionary with category as key and status counts as value.

    Raises:
        HTTPException: 401 if not authenticated.
    """
    service = PortfolioService(db)
    return await service.get_milestone_progress(child_id)


@router.patch(
    "/milestones/{milestone_id}",
    response_model=MilestoneResponse,
    summary="Update milestone",
    description="Update an existing milestone with partial data.",
)
async def update_milestone(
    milestone_id: UUID,
    data: MilestoneUpdate,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> MilestoneResponse:
    """Update a milestone.

    Args:
        milestone_id: Unique identifier of the milestone.
        data: Milestone update data.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        MilestoneResponse with updated milestone.

    Raises:
        HTTPException: 404 if milestone not found.
        HTTPException: 401 if not authenticated.
    """
    service = PortfolioService(db)
    try:
        milestone = await service.update_milestone(milestone_id, data)
        return MilestoneResponse.model_validate(milestone)
    except MilestoneNotFoundError:
        raise HTTPException(
            status_code=404,
            detail=f"Milestone with id {milestone_id} not found",
        )


@router.delete(
    "/milestones/{milestone_id}",
    status_code=204,
    summary="Delete milestone",
    description="Delete a milestone (hard delete).",
)
async def delete_milestone(
    milestone_id: UUID,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> None:
    """Delete a milestone.

    Args:
        milestone_id: Unique identifier of the milestone.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Raises:
        HTTPException: 404 if milestone not found.
        HTTPException: 401 if not authenticated.
    """
    service = PortfolioService(db)
    try:
        await service.delete_milestone(milestone_id)
    except MilestoneNotFoundError:
        raise HTTPException(
            status_code=404,
            detail=f"Milestone with id {milestone_id} not found",
        )


# =============================================================================
# Work Sample Endpoints
# =============================================================================


@router.post(
    "/work-samples",
    response_model=WorkSampleResponse,
    status_code=201,
    summary="Create work sample",
    description="Create a new work sample documentation for a child.",
)
async def create_work_sample(
    data: WorkSampleCreate,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> WorkSampleResponse:
    """Create a new work sample.

    Args:
        data: Work sample creation data.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        WorkSampleResponse with the created work sample.

    Raises:
        HTTPException: 401 if not authenticated.
    """
    service = PortfolioService(db)
    work_sample = await service.create_work_sample(data)
    return WorkSampleResponse.model_validate(work_sample)


@router.get(
    "/work-samples/{work_sample_id}",
    response_model=WorkSampleResponse,
    summary="Get work sample by ID",
    description="Retrieve a single work sample by its unique identifier.",
)
async def get_work_sample(
    work_sample_id: UUID,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> WorkSampleResponse:
    """Get a single work sample by ID.

    Args:
        work_sample_id: Unique identifier of the work sample.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        WorkSampleResponse with work sample details.

    Raises:
        HTTPException: 404 if work sample not found.
        HTTPException: 401 if not authenticated.
    """
    service = PortfolioService(db)
    work_sample = await service.get_work_sample_by_id(work_sample_id)

    if work_sample is None:
        raise HTTPException(
            status_code=404,
            detail=f"Work sample with id {work_sample_id} not found",
        )

    return WorkSampleResponse.model_validate(work_sample)


@router.get(
    "/children/{child_id}/work-samples",
    response_model=WorkSampleListResponse,
    summary="List work samples for a child",
    description="List all work samples for a child with optional filtering and pagination.",
)
async def list_work_samples(
    child_id: UUID,
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
    sample_type: Optional[str] = Query(
        default=None,
        description="Filter by sample type (artwork, writing, construction, science, music, other)",
    ),
    portfolio_item_id: Optional[UUID] = Query(
        default=None,
        description="Filter by portfolio item ID",
    ),
    date_from: Optional[date] = Query(
        default=None,
        description="Filter work samples from this date",
    ),
    date_to: Optional[date] = Query(
        default=None,
        description="Filter work samples until this date",
    ),
    shared_with_family_only: bool = Query(
        default=False,
        description="Only return work samples shared with family",
    ),
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> WorkSampleListResponse:
    """List work samples for a child with optional filtering and pagination.

    Args:
        child_id: Unique identifier of the child.
        skip: Number of records to skip.
        limit: Maximum number of records to return.
        sample_type: Optional filter by sample type.
        portfolio_item_id: Optional filter by portfolio item.
        date_from: Optional filter for samples from this date.
        date_to: Optional filter for samples until this date.
        shared_with_family_only: Only return samples shared with family.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        WorkSampleListResponse with paginated list of work samples.

    Raises:
        HTTPException: 401 if not authenticated.
    """
    service = PortfolioService(db)
    work_samples, total = await service.list_work_samples(
        child_id=child_id,
        skip=skip,
        limit=limit,
        sample_type=sample_type,
        portfolio_item_id=portfolio_item_id,
        date_from=date_from,
        date_to=date_to,
        shared_with_family_only=shared_with_family_only,
    )

    response_items = [WorkSampleResponse.model_validate(ws) for ws in work_samples]

    return WorkSampleListResponse(
        items=response_items,
        total=total,
        skip=skip,
        limit=limit,
    )


@router.patch(
    "/work-samples/{work_sample_id}",
    response_model=WorkSampleResponse,
    summary="Update work sample",
    description="Update an existing work sample with partial data.",
)
async def update_work_sample(
    work_sample_id: UUID,
    data: WorkSampleUpdate,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> WorkSampleResponse:
    """Update a work sample.

    Args:
        work_sample_id: Unique identifier of the work sample.
        data: Work sample update data.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        WorkSampleResponse with updated work sample.

    Raises:
        HTTPException: 404 if work sample not found.
        HTTPException: 401 if not authenticated.
    """
    service = PortfolioService(db)
    try:
        work_sample = await service.update_work_sample(work_sample_id, data)
        return WorkSampleResponse.model_validate(work_sample)
    except WorkSampleNotFoundError:
        raise HTTPException(
            status_code=404,
            detail=f"Work sample with id {work_sample_id} not found",
        )


@router.delete(
    "/work-samples/{work_sample_id}",
    status_code=204,
    summary="Delete work sample",
    description="Delete a work sample (hard delete).",
)
async def delete_work_sample(
    work_sample_id: UUID,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> None:
    """Delete a work sample.

    Args:
        work_sample_id: Unique identifier of the work sample.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Raises:
        HTTPException: 404 if work sample not found.
        HTTPException: 401 if not authenticated.
    """
    service = PortfolioService(db)
    try:
        await service.delete_work_sample(work_sample_id)
    except WorkSampleNotFoundError:
        raise HTTPException(
            status_code=404,
            detail=f"Work sample with id {work_sample_id} not found",
        )


# =============================================================================
# Portfolio Summary Endpoints
# =============================================================================


@router.get(
    "/children/{child_id}/summary",
    response_model=PortfolioSummary,
    summary="Get portfolio summary for a child",
    description="Get a summary of a child's portfolio including counts and recent items.",
)
async def get_portfolio_summary(
    child_id: UUID,
    recent_count: int = Query(
        default=5,
        ge=1,
        le=20,
        description="Number of recent items to include",
    ),
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> PortfolioSummary:
    """Get a summary of a child's portfolio.

    Args:
        child_id: Unique identifier of the child.
        recent_count: Number of recent items to include.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        PortfolioSummary with counts and recent items.

    Raises:
        HTTPException: 401 if not authenticated.
    """
    service = PortfolioService(db)
    return await service.get_portfolio_summary(child_id, recent_count)
