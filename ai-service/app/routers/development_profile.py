"""Development Profile router for LAYA AI Service.

Provides API endpoints for Quebec-aligned developmental tracking across 6 domains:
1. Affective Development (emotional expression, self-regulation, attachment, self-confidence)
2. Social Development (peer interactions, turn-taking, empathy, group participation)
3. Language & Communication (receptive/expressive language, speech clarity, emergent literacy)
4. Cognitive Development (problem-solving, memory, attention, classification, number concept)
5. Physical - Gross Motor (balance, coordination, body awareness, outdoor skills)
6. Physical - Fine Motor (hand-eye coordination, pencil grip, manipulation, self-care)

All endpoints require JWT authentication.
"""

from datetime import date
from typing import Any, Optional
from uuid import UUID

from fastapi import APIRouter, Depends, HTTPException, Query, status
from sqlalchemy.ext.asyncio import AsyncSession

from app.database import get_db
from app.dependencies import get_current_user
from app.schemas.development_profile import (
    DevelopmentalDomain,
    DevelopmentProfileListResponse,
    DevelopmentProfileRequest,
    DevelopmentProfileResponse,
    GrowthTrajectoryResponse,
    MonthlySnapshotListResponse,
    MonthlySnapshotRequest,
    MonthlySnapshotResponse,
    MonthlySnapshotUpdateRequest,
    ObservationListResponse,
    ObservationRequest,
    ObservationResponse,
    ObservationUpdateRequest,
    SkillAssessmentListResponse,
    SkillAssessmentRequest,
    SkillAssessmentResponse,
    SkillAssessmentUpdateRequest,
    SkillStatus,
)
from app.services.development_profile_service import DevelopmentProfileService

router = APIRouter(prefix="/api/v1/development-profiles", tags=["development-profiles"])


# =============================================================================
# Development Profile Endpoints
# =============================================================================


@router.post(
    "",
    response_model=DevelopmentProfileResponse,
    status_code=status.HTTP_201_CREATED,
    summary="Create a development profile",
    description="Create a new development profile for a child to track developmental "
    "progress across Quebec's 6 early childhood domains.",
)
async def create_profile(
    request: DevelopmentProfileRequest,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> DevelopmentProfileResponse:
    """Create a new development profile for a child.

    Args:
        request: Development profile creation request data.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        The created development profile response.

    Raises:
        HTTPException: 400 if profile already exists for this child.
        HTTPException: 401 if not authenticated.
    """
    service = DevelopmentProfileService(db)
    try:
        return await service.create_profile(request)
    except ValueError as e:
        raise HTTPException(status_code=400, detail=str(e))


@router.get(
    "",
    response_model=DevelopmentProfileListResponse,
    summary="List development profiles",
    description="List development profiles with optional filtering and pagination.",
)
async def list_profiles(
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
    is_active: Optional[bool] = Query(
        default=True,
        description="Filter by active status",
    ),
    educator_id: Optional[UUID] = Query(
        default=None,
        description="Filter by educator ID",
    ),
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> DevelopmentProfileListResponse:
    """List development profiles with optional filtering and pagination.

    Args:
        skip: Number of records to skip.
        limit: Maximum number of records to return.
        is_active: Optional filter by active status.
        educator_id: Optional filter by educator.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        Paginated list of development profile summaries.

    Raises:
        HTTPException: 401 if not authenticated.
    """
    service = DevelopmentProfileService(db)
    return await service.list_profiles(
        skip=skip,
        limit=limit,
        is_active=is_active,
        educator_id=educator_id,
    )


@router.get(
    "/child/{child_id}",
    response_model=DevelopmentProfileResponse,
    summary="Get profile by child ID",
    description="Retrieve a development profile by child ID with all related data.",
)
async def get_profile_by_child(
    child_id: UUID,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> DevelopmentProfileResponse:
    """Get a development profile by child ID.

    Args:
        child_id: Unique identifier of the child.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        DevelopmentProfileResponse with profile details and related data.

    Raises:
        HTTPException: 404 if profile not found.
        HTTPException: 401 if not authenticated.
    """
    service = DevelopmentProfileService(db)
    profile = await service.get_profile_by_child_id(child_id)

    if profile is None:
        raise HTTPException(
            status_code=404,
            detail=f"Development profile for child {child_id} not found",
        )

    return profile


@router.get(
    "/{profile_id}",
    response_model=DevelopmentProfileResponse,
    summary="Get profile by ID",
    description="Retrieve a development profile by its unique identifier.",
)
async def get_profile(
    profile_id: UUID,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> DevelopmentProfileResponse:
    """Get a development profile by ID.

    Args:
        profile_id: Unique identifier of the profile.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        DevelopmentProfileResponse with profile details and related data.

    Raises:
        HTTPException: 404 if profile not found.
        HTTPException: 401 if not authenticated.
    """
    service = DevelopmentProfileService(db)
    profile = await service.get_profile_by_id(profile_id)

    if profile is None:
        raise HTTPException(
            status_code=404,
            detail=f"Development profile with id {profile_id} not found",
        )

    return profile


@router.put(
    "/{profile_id}",
    response_model=DevelopmentProfileResponse,
    summary="Update profile",
    description="Update an existing development profile.",
)
async def update_profile(
    profile_id: UUID,
    request: DevelopmentProfileRequest,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> DevelopmentProfileResponse:
    """Update an existing development profile.

    Args:
        profile_id: Unique identifier of the profile.
        request: Updated profile data.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        Updated development profile response.

    Raises:
        HTTPException: 404 if profile not found.
        HTTPException: 401 if not authenticated.
    """
    service = DevelopmentProfileService(db)
    profile = await service.update_profile(profile_id, request)

    if profile is None:
        raise HTTPException(
            status_code=404,
            detail=f"Development profile with id {profile_id} not found",
        )

    return profile


@router.delete(
    "/{profile_id}",
    status_code=status.HTTP_204_NO_CONTENT,
    summary="Delete profile",
    description="Delete a development profile and all related data.",
)
async def delete_profile(
    profile_id: UUID,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> None:
    """Delete a development profile.

    Args:
        profile_id: Unique identifier of the profile.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Raises:
        HTTPException: 404 if profile not found.
        HTTPException: 401 if not authenticated.
    """
    service = DevelopmentProfileService(db)
    deleted = await service.delete_profile(profile_id)

    if not deleted:
        raise HTTPException(
            status_code=404,
            detail=f"Development profile with id {profile_id} not found",
        )


# =============================================================================
# Skill Assessment Endpoints
# =============================================================================


@router.post(
    "/{profile_id}/assessments",
    response_model=SkillAssessmentResponse,
    status_code=status.HTTP_201_CREATED,
    summary="Create skill assessment",
    description="Create a new skill assessment for a development profile. "
    "Track skill progress with status: can, learning, not_yet, or na.",
)
async def create_skill_assessment(
    profile_id: UUID,
    request: SkillAssessmentRequest,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> SkillAssessmentResponse:
    """Create a new skill assessment.

    Args:
        profile_id: Unique identifier of the profile (path param for validation).
        request: Skill assessment creation request data.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        The created skill assessment response.

    Raises:
        HTTPException: 400 if profile not found or request validation fails.
        HTTPException: 401 if not authenticated.
    """
    # Ensure profile_id in path matches request body
    if request.profile_id != profile_id:
        raise HTTPException(
            status_code=400,
            detail="Profile ID in path does not match request body",
        )

    service = DevelopmentProfileService(db)
    try:
        return await service.create_skill_assessment(request)
    except ValueError as e:
        raise HTTPException(status_code=400, detail=str(e))


@router.get(
    "/{profile_id}/assessments",
    response_model=SkillAssessmentListResponse,
    summary="List skill assessments",
    description="List skill assessments for a profile with optional filtering.",
)
async def list_skill_assessments(
    profile_id: UUID,
    domain: Optional[DevelopmentalDomain] = Query(
        default=None,
        description="Filter by developmental domain",
    ),
    skill_status: Optional[SkillStatus] = Query(
        default=None,
        alias="status",
        description="Filter by skill status (can, learning, not_yet, na)",
    ),
    skip: int = Query(
        default=0,
        ge=0,
        description="Number of records to skip for pagination",
    ),
    limit: int = Query(
        default=50,
        ge=1,
        le=100,
        description="Maximum number of records to return",
    ),
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> SkillAssessmentListResponse:
    """List skill assessments for a profile.

    Args:
        profile_id: Unique identifier of the profile.
        domain: Optional filter by developmental domain.
        skill_status: Optional filter by skill status.
        skip: Number of records to skip.
        limit: Maximum number of records to return.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        Paginated list of skill assessments.

    Raises:
        HTTPException: 401 if not authenticated.
    """
    service = DevelopmentProfileService(db)
    return await service.list_skill_assessments(
        profile_id=profile_id,
        domain=domain,
        status=skill_status,
        skip=skip,
        limit=limit,
    )


@router.get(
    "/{profile_id}/assessments/{assessment_id}",
    response_model=SkillAssessmentResponse,
    summary="Get skill assessment",
    description="Retrieve a single skill assessment by ID.",
)
async def get_skill_assessment(
    profile_id: UUID,
    assessment_id: UUID,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> SkillAssessmentResponse:
    """Get a skill assessment by ID.

    Args:
        profile_id: Unique identifier of the profile.
        assessment_id: Unique identifier of the assessment.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        SkillAssessmentResponse with assessment details.

    Raises:
        HTTPException: 404 if assessment not found.
        HTTPException: 401 if not authenticated.
    """
    service = DevelopmentProfileService(db)
    assessment = await service.get_skill_assessment_by_id(assessment_id)

    if assessment is None or assessment.profile_id != profile_id:
        raise HTTPException(
            status_code=404,
            detail=f"Skill assessment {assessment_id} not found in profile {profile_id}",
        )

    return assessment


@router.patch(
    "/{profile_id}/assessments/{assessment_id}",
    response_model=SkillAssessmentResponse,
    summary="Update skill assessment",
    description="Update an existing skill assessment with partial data.",
)
async def update_skill_assessment(
    profile_id: UUID,
    assessment_id: UUID,
    request: SkillAssessmentUpdateRequest,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> SkillAssessmentResponse:
    """Update an existing skill assessment.

    Args:
        profile_id: Unique identifier of the profile.
        assessment_id: Unique identifier of the assessment.
        request: Update request with partial data.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        Updated skill assessment response.

    Raises:
        HTTPException: 404 if assessment not found.
        HTTPException: 401 if not authenticated.
    """
    service = DevelopmentProfileService(db)

    # Verify assessment exists and belongs to this profile
    existing = await service.get_skill_assessment_by_id(assessment_id)
    if existing is None or existing.profile_id != profile_id:
        raise HTTPException(
            status_code=404,
            detail=f"Skill assessment {assessment_id} not found in profile {profile_id}",
        )

    assessment = await service.update_skill_assessment(assessment_id, request)
    return assessment


@router.delete(
    "/{profile_id}/assessments/{assessment_id}",
    status_code=status.HTTP_204_NO_CONTENT,
    summary="Delete skill assessment",
    description="Delete a skill assessment.",
)
async def delete_skill_assessment(
    profile_id: UUID,
    assessment_id: UUID,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> None:
    """Delete a skill assessment.

    Args:
        profile_id: Unique identifier of the profile.
        assessment_id: Unique identifier of the assessment.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Raises:
        HTTPException: 404 if assessment not found.
        HTTPException: 401 if not authenticated.
    """
    service = DevelopmentProfileService(db)

    # Verify assessment exists and belongs to this profile
    existing = await service.get_skill_assessment_by_id(assessment_id)
    if existing is None or existing.profile_id != profile_id:
        raise HTTPException(
            status_code=404,
            detail=f"Skill assessment {assessment_id} not found in profile {profile_id}",
        )

    await service.delete_skill_assessment(assessment_id)


# =============================================================================
# Observation Endpoints
# =============================================================================


@router.post(
    "/{profile_id}/observations",
    response_model=ObservationResponse,
    status_code=status.HTTP_201_CREATED,
    summary="Create observation",
    description="Create a new observation documenting observable child behavior "
    "with optional milestone or concern flags.",
)
async def create_observation(
    profile_id: UUID,
    request: ObservationRequest,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> ObservationResponse:
    """Create a new observation.

    Args:
        profile_id: Unique identifier of the profile (path param for validation).
        request: Observation creation request data.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        The created observation response.

    Raises:
        HTTPException: 400 if profile not found or request validation fails.
        HTTPException: 401 if not authenticated.
    """
    # Ensure profile_id in path matches request body
    if request.profile_id != profile_id:
        raise HTTPException(
            status_code=400,
            detail="Profile ID in path does not match request body",
        )

    service = DevelopmentProfileService(db)
    try:
        return await service.create_observation(request)
    except ValueError as e:
        raise HTTPException(status_code=400, detail=str(e))


@router.get(
    "/{profile_id}/observations",
    response_model=ObservationListResponse,
    summary="List observations",
    description="List observations for a profile with optional filtering.",
)
async def list_observations(
    profile_id: UUID,
    domain: Optional[DevelopmentalDomain] = Query(
        default=None,
        description="Filter by developmental domain",
    ),
    is_milestone: Optional[bool] = Query(
        default=None,
        description="Filter for milestones only",
    ),
    is_concern: Optional[bool] = Query(
        default=None,
        description="Filter for concerns only",
    ),
    observer_type: Optional[str] = Query(
        default=None,
        description="Filter by observer type (educator, parent, specialist)",
    ),
    skip: int = Query(
        default=0,
        ge=0,
        description="Number of records to skip for pagination",
    ),
    limit: int = Query(
        default=50,
        ge=1,
        le=100,
        description="Maximum number of records to return",
    ),
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> ObservationListResponse:
    """List observations for a profile.

    Args:
        profile_id: Unique identifier of the profile.
        domain: Optional filter by developmental domain.
        is_milestone: Optional filter for milestones only.
        is_concern: Optional filter for concerns only.
        observer_type: Optional filter by observer type.
        skip: Number of records to skip.
        limit: Maximum number of records to return.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        Paginated list of observations.

    Raises:
        HTTPException: 401 if not authenticated.
    """
    service = DevelopmentProfileService(db)
    return await service.list_observations(
        profile_id=profile_id,
        domain=domain,
        is_milestone=is_milestone,
        is_concern=is_concern,
        observer_type=observer_type,
        skip=skip,
        limit=limit,
    )


@router.get(
    "/{profile_id}/observations/{observation_id}",
    response_model=ObservationResponse,
    summary="Get observation",
    description="Retrieve a single observation by ID.",
)
async def get_observation(
    profile_id: UUID,
    observation_id: UUID,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> ObservationResponse:
    """Get an observation by ID.

    Args:
        profile_id: Unique identifier of the profile.
        observation_id: Unique identifier of the observation.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        ObservationResponse with observation details.

    Raises:
        HTTPException: 404 if observation not found.
        HTTPException: 401 if not authenticated.
    """
    service = DevelopmentProfileService(db)
    observation = await service.get_observation_by_id(observation_id)

    if observation is None or observation.profile_id != profile_id:
        raise HTTPException(
            status_code=404,
            detail=f"Observation {observation_id} not found in profile {profile_id}",
        )

    return observation


@router.patch(
    "/{profile_id}/observations/{observation_id}",
    response_model=ObservationResponse,
    summary="Update observation",
    description="Update an existing observation with partial data.",
)
async def update_observation(
    profile_id: UUID,
    observation_id: UUID,
    request: ObservationUpdateRequest,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> ObservationResponse:
    """Update an existing observation.

    Args:
        profile_id: Unique identifier of the profile.
        observation_id: Unique identifier of the observation.
        request: Update request with partial data.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        Updated observation response.

    Raises:
        HTTPException: 404 if observation not found.
        HTTPException: 401 if not authenticated.
    """
    service = DevelopmentProfileService(db)

    # Verify observation exists and belongs to this profile
    existing = await service.get_observation_by_id(observation_id)
    if existing is None or existing.profile_id != profile_id:
        raise HTTPException(
            status_code=404,
            detail=f"Observation {observation_id} not found in profile {profile_id}",
        )

    observation = await service.update_observation(observation_id, request)
    return observation


@router.delete(
    "/{profile_id}/observations/{observation_id}",
    status_code=status.HTTP_204_NO_CONTENT,
    summary="Delete observation",
    description="Delete an observation.",
)
async def delete_observation(
    profile_id: UUID,
    observation_id: UUID,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> None:
    """Delete an observation.

    Args:
        profile_id: Unique identifier of the profile.
        observation_id: Unique identifier of the observation.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Raises:
        HTTPException: 404 if observation not found.
        HTTPException: 401 if not authenticated.
    """
    service = DevelopmentProfileService(db)

    # Verify observation exists and belongs to this profile
    existing = await service.get_observation_by_id(observation_id)
    if existing is None or existing.profile_id != profile_id:
        raise HTTPException(
            status_code=404,
            detail=f"Observation {observation_id} not found in profile {profile_id}",
        )

    await service.delete_observation(observation_id)


# =============================================================================
# Monthly Snapshot Endpoints
# =============================================================================


@router.post(
    "/{profile_id}/snapshots",
    response_model=MonthlySnapshotResponse,
    status_code=status.HTTP_201_CREATED,
    summary="Create monthly snapshot",
    description="Create a new monthly snapshot summarizing developmental progress "
    "across all 6 Quebec domains.",
)
async def create_monthly_snapshot(
    profile_id: UUID,
    request: MonthlySnapshotRequest,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> MonthlySnapshotResponse:
    """Create a new monthly snapshot.

    Args:
        profile_id: Unique identifier of the profile (path param for validation).
        request: Monthly snapshot creation request data.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        The created monthly snapshot response.

    Raises:
        HTTPException: 400 if profile not found, snapshot exists, or validation fails.
        HTTPException: 401 if not authenticated.
    """
    # Ensure profile_id in path matches request body
    if request.profile_id != profile_id:
        raise HTTPException(
            status_code=400,
            detail="Profile ID in path does not match request body",
        )

    service = DevelopmentProfileService(db)
    try:
        return await service.create_monthly_snapshot(request)
    except ValueError as e:
        raise HTTPException(status_code=400, detail=str(e))


@router.post(
    "/{profile_id}/snapshots/generate",
    response_model=MonthlySnapshotResponse,
    status_code=status.HTTP_201_CREATED,
    summary="Generate monthly snapshot",
    description="Automatically generate a monthly snapshot from current assessments "
    "and observations, aggregating progress across all 6 Quebec domains.",
)
async def generate_monthly_snapshot(
    profile_id: UUID,
    snapshot_month: date = Query(
        ...,
        description="The month to generate the snapshot for (first day of month)",
    ),
    generated_by_id: Optional[UUID] = Query(
        default=None,
        description="UUID of the user generating the snapshot",
    ),
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> MonthlySnapshotResponse:
    """Automatically generate a monthly snapshot.

    Analyzes all skill assessments and observations for the profile to create
    a comprehensive monthly developmental summary across all 6 Quebec domains.

    Args:
        profile_id: Unique identifier of the profile.
        snapshot_month: The month to generate the snapshot for.
        generated_by_id: UUID of the user generating the snapshot.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        The generated monthly snapshot response.

    Raises:
        HTTPException: 400 if profile not found or snapshot already exists.
        HTTPException: 401 if not authenticated.
    """
    service = DevelopmentProfileService(db)
    try:
        return await service.generate_monthly_snapshot(
            profile_id=profile_id,
            snapshot_month=snapshot_month,
            generated_by_id=generated_by_id,
        )
    except ValueError as e:
        raise HTTPException(status_code=400, detail=str(e))


@router.get(
    "/{profile_id}/snapshots",
    response_model=MonthlySnapshotListResponse,
    summary="List monthly snapshots",
    description="List monthly snapshots for a profile with optional date filtering.",
)
async def list_monthly_snapshots(
    profile_id: UUID,
    start_month: Optional[date] = Query(
        default=None,
        description="Filter snapshots from this month onwards",
    ),
    end_month: Optional[date] = Query(
        default=None,
        description="Filter snapshots up to this month",
    ),
    skip: int = Query(
        default=0,
        ge=0,
        description="Number of records to skip for pagination",
    ),
    limit: int = Query(
        default=12,
        ge=1,
        le=100,
        description="Maximum number of records to return",
    ),
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> MonthlySnapshotListResponse:
    """List monthly snapshots for a profile.

    Args:
        profile_id: Unique identifier of the profile.
        start_month: Optional start date filter.
        end_month: Optional end date filter.
        skip: Number of records to skip.
        limit: Maximum number of records to return.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        Paginated list of monthly snapshots.

    Raises:
        HTTPException: 401 if not authenticated.
    """
    service = DevelopmentProfileService(db)
    return await service.list_monthly_snapshots(
        profile_id=profile_id,
        start_month=start_month,
        end_month=end_month,
        skip=skip,
        limit=limit,
    )


@router.get(
    "/{profile_id}/snapshots/{snapshot_id}",
    response_model=MonthlySnapshotResponse,
    summary="Get monthly snapshot",
    description="Retrieve a single monthly snapshot by ID.",
)
async def get_monthly_snapshot(
    profile_id: UUID,
    snapshot_id: UUID,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> MonthlySnapshotResponse:
    """Get a monthly snapshot by ID.

    Args:
        profile_id: Unique identifier of the profile.
        snapshot_id: Unique identifier of the snapshot.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        MonthlySnapshotResponse with snapshot details.

    Raises:
        HTTPException: 404 if snapshot not found.
        HTTPException: 401 if not authenticated.
    """
    service = DevelopmentProfileService(db)
    snapshot = await service.get_monthly_snapshot_by_id(snapshot_id)

    if snapshot is None or snapshot.profile_id != profile_id:
        raise HTTPException(
            status_code=404,
            detail=f"Monthly snapshot {snapshot_id} not found in profile {profile_id}",
        )

    return snapshot


@router.patch(
    "/{profile_id}/snapshots/{snapshot_id}",
    response_model=MonthlySnapshotResponse,
    summary="Update monthly snapshot",
    description="Update an existing monthly snapshot with partial data.",
)
async def update_monthly_snapshot(
    profile_id: UUID,
    snapshot_id: UUID,
    request: MonthlySnapshotUpdateRequest,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> MonthlySnapshotResponse:
    """Update an existing monthly snapshot.

    Args:
        profile_id: Unique identifier of the profile.
        snapshot_id: Unique identifier of the snapshot.
        request: Update request with partial data.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        Updated monthly snapshot response.

    Raises:
        HTTPException: 404 if snapshot not found.
        HTTPException: 401 if not authenticated.
    """
    service = DevelopmentProfileService(db)

    # Verify snapshot exists and belongs to this profile
    existing = await service.get_monthly_snapshot_by_id(snapshot_id)
    if existing is None or existing.profile_id != profile_id:
        raise HTTPException(
            status_code=404,
            detail=f"Monthly snapshot {snapshot_id} not found in profile {profile_id}",
        )

    snapshot = await service.update_monthly_snapshot(snapshot_id, request)
    return snapshot


@router.delete(
    "/{profile_id}/snapshots/{snapshot_id}",
    status_code=status.HTTP_204_NO_CONTENT,
    summary="Delete monthly snapshot",
    description="Delete a monthly snapshot.",
)
async def delete_monthly_snapshot(
    profile_id: UUID,
    snapshot_id: UUID,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> None:
    """Delete a monthly snapshot.

    Args:
        profile_id: Unique identifier of the profile.
        snapshot_id: Unique identifier of the snapshot.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Raises:
        HTTPException: 404 if snapshot not found.
        HTTPException: 401 if not authenticated.
    """
    service = DevelopmentProfileService(db)

    # Verify snapshot exists and belongs to this profile
    existing = await service.get_monthly_snapshot_by_id(snapshot_id)
    if existing is None or existing.profile_id != profile_id:
        raise HTTPException(
            status_code=404,
            detail=f"Monthly snapshot {snapshot_id} not found in profile {profile_id}",
        )

    await service.delete_monthly_snapshot(snapshot_id)


# =============================================================================
# Growth Trajectory Endpoints
# =============================================================================


@router.get(
    "/{profile_id}/trajectory",
    response_model=GrowthTrajectoryResponse,
    summary="Get growth trajectory",
    description="Get growth trajectory data for visualization and analysis. "
    "Returns data points over time with trend analysis and alerts for slow progress.",
)
async def get_growth_trajectory(
    profile_id: UUID,
    start_month: Optional[date] = Query(
        default=None,
        description="Start month for trajectory data",
    ),
    end_month: Optional[date] = Query(
        default=None,
        description="End month for trajectory data",
    ),
    domains: Optional[list[DevelopmentalDomain]] = Query(
        default=None,
        description="Filter by specific developmental domains",
    ),
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> GrowthTrajectoryResponse:
    """Get growth trajectory data for a development profile.

    Retrieves monthly snapshot data points for tracking developmental
    progress over time, with optional filtering by date range and domains.
    Includes trend analysis and alerts for areas needing attention.

    Args:
        profile_id: Unique identifier of the profile.
        start_month: Optional start date for trajectory data.
        end_month: Optional end date for trajectory data.
        domains: Optional list of domains to include.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        GrowthTrajectoryResponse with data points, trend analysis, and alerts.

    Raises:
        HTTPException: 404 if profile not found.
        HTTPException: 401 if not authenticated.
    """
    service = DevelopmentProfileService(db)
    try:
        return await service.get_growth_trajectory(
            profile_id=profile_id,
            start_month=start_month,
            end_month=end_month,
            domains=domains,
        )
    except ValueError as e:
        raise HTTPException(status_code=404, detail=str(e))
