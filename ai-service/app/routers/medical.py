"""Medical router for LAYA AI Service.

Provides API endpoints for medical tracking including allergies, medications,
accommodation plans, medical alerts, and allergen detection.
All endpoints require JWT authentication.
"""

from typing import Any, Optional
from uuid import UUID

from fastapi import APIRouter, Depends, HTTPException, Query
from sqlalchemy.ext.asyncio import AsyncSession

from app.database import get_db
from app.dependencies import get_current_user
from app.models.medical import (
    AccommodationPlanStatus,
    AccommodationPlanType,
    AdministeredBy,
    AlertLevel,
    AlertType,
    AllergenType,
    AllergySeverity,
    MedicationType,
)
from app.schemas.medical import (
    AccommodationPlanListResponse,
    AccommodationPlanResponse,
    AllergenDetectionRequest,
    AllergenDetectionResponse,
    AllergyListResponse,
    AllergyRequest,
    AllergyResponse,
    ChildMedicalSummary,
    MedicalAlertListResponse,
    MedicalAlertResponse,
    MedicationListResponse,
    MedicationResponse,
)
from app.services.medical_service import (
    AllergyNotFoundError,
    MedicalService,
    MedicationNotFoundError,
)

router = APIRouter(prefix="/api/v1/medical", tags=["medical"])


# =============================================================================
# Allergy Endpoints
# =============================================================================


@router.get(
    "/allergies",
    response_model=AllergyListResponse,
    summary="List allergies",
    description="List all allergies with optional filtering and pagination.",
)
async def list_allergies(
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
    child_id: Optional[UUID] = Query(
        default=None,
        description="Filter by child ID",
    ),
    allergen_type: Optional[str] = Query(
        default=None,
        description="Filter by allergen type (food, medication, environmental, insect, other)",
    ),
    severity: Optional[str] = Query(
        default=None,
        description="Filter by severity (mild, moderate, severe, life_threatening)",
    ),
    is_active: Optional[bool] = Query(
        default=True,
        description="Filter by active status",
    ),
    epi_pen_required: Optional[bool] = Query(
        default=None,
        description="Filter by EpiPen requirement",
    ),
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> AllergyListResponse:
    """List allergies with optional filtering and pagination.

    Args:
        skip: Number of records to skip.
        limit: Maximum number of records to return.
        child_id: Optional filter by child ID.
        allergen_type: Optional filter by allergen type.
        severity: Optional filter by severity level.
        is_active: Optional filter by active status.
        epi_pen_required: Optional filter by EpiPen requirement.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        AllergyListResponse with paginated list of allergies.

    Raises:
        HTTPException: 401 if not authenticated.
    """
    service = MedicalService(db)

    # Convert string filters to enums if provided
    allergen_type_enum = None
    if allergen_type:
        try:
            allergen_type_enum = AllergenType(allergen_type)
        except ValueError:
            raise HTTPException(
                status_code=400,
                detail=f"Invalid allergen_type: {allergen_type}",
            )

    severity_enum = None
    if severity:
        try:
            severity_enum = AllergySeverity(severity)
        except ValueError:
            raise HTTPException(
                status_code=400,
                detail=f"Invalid severity: {severity}",
            )

    allergies, total = await service.list_allergies(
        skip=skip,
        limit=limit,
        child_id=child_id,
        allergen_type=allergen_type_enum,
        severity=severity_enum,
        is_active=is_active,
        epi_pen_required=epi_pen_required,
    )

    items = [service._allergy_to_response(allergy) for allergy in allergies]

    return AllergyListResponse(
        items=items,
        total=total,
        skip=skip,
        limit=limit,
    )


@router.get(
    "/allergies/{allergy_id}",
    response_model=AllergyResponse,
    summary="Get allergy by ID",
    description="Retrieve a single allergy record by its unique identifier.",
)
async def get_allergy(
    allergy_id: UUID,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> AllergyResponse:
    """Get a single allergy record by ID.

    Args:
        allergy_id: Unique identifier of the allergy.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        AllergyResponse with allergy details.

    Raises:
        HTTPException: 404 if allergy not found.
        HTTPException: 401 if not authenticated.
    """
    service = MedicalService(db)
    allergy = await service.get_allergy_by_id(allergy_id)

    if allergy is None:
        raise HTTPException(
            status_code=404,
            detail=f"Allergy with id {allergy_id} not found",
        )

    return service._allergy_to_response(allergy)


@router.get(
    "/children/{child_id}/allergies",
    response_model=list[AllergyResponse],
    summary="Get allergies by child",
    description="Retrieve all allergies for a specific child.",
)
async def get_allergies_by_child(
    child_id: UUID,
    include_inactive: bool = Query(
        default=False,
        description="Whether to include inactive allergy records",
    ),
    food_only: bool = Query(
        default=False,
        description="Whether to filter to food allergies only",
    ),
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> list[AllergyResponse]:
    """Get all allergies for a specific child.

    Args:
        child_id: Unique identifier of the child.
        include_inactive: Whether to include inactive allergy records.
        food_only: Whether to filter to only food allergies.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        List of AllergyResponse objects for the child.

    Raises:
        HTTPException: 401 if not authenticated.
    """
    service = MedicalService(db)
    allergies = await service.get_allergies_by_child(
        child_id=child_id,
        include_inactive=include_inactive,
        food_only=food_only,
    )

    return [service._allergy_to_response(allergy) for allergy in allergies]


@router.post(
    "/allergies",
    response_model=AllergyResponse,
    status_code=201,
    summary="Create allergy",
    description="Create a new allergy record for a child.",
)
async def create_allergy(
    request: AllergyRequest,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> AllergyResponse:
    """Create a new allergy record.

    Args:
        request: The allergy data to create.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        AllergyResponse with the created allergy details.

    Raises:
        HTTPException: 401 if not authenticated.
    """
    service = MedicalService(db)

    # Get the user ID from the JWT token
    created_by_id = UUID(current_user.get("sub", str(UUID(int=0))))

    allergy = await service.create_allergy(
        child_id=request.child_id,
        allergen_name=request.allergen_name,
        created_by_id=created_by_id,
        allergen_type=request.allergen_type,
        severity=request.severity,
        reaction=request.reaction,
        treatment=request.treatment,
        epi_pen_required=request.epi_pen_required,
        epi_pen_location=request.epi_pen_location,
        diagnosed_date=request.diagnosed_date,
        diagnosed_by=request.diagnosed_by,
        notes=request.notes,
    )

    return service._allergy_to_response(allergy)


@router.post(
    "/allergies/{allergy_id}/verify",
    response_model=AllergyResponse,
    summary="Verify allergy",
    description="Mark an allergy record as verified by staff.",
)
async def verify_allergy(
    allergy_id: UUID,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> AllergyResponse:
    """Mark an allergy record as verified.

    Args:
        allergy_id: Unique identifier of the allergy to verify.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        AllergyResponse with the updated allergy details.

    Raises:
        HTTPException: 404 if allergy not found.
        HTTPException: 401 if not authenticated.
    """
    service = MedicalService(db)
    verified_by_id = UUID(current_user.get("sub", str(UUID(int=0))))

    try:
        allergy = await service.verify_allergy(
            allergy_id=allergy_id,
            verified_by_id=verified_by_id,
        )
        return service._allergy_to_response(allergy)
    except AllergyNotFoundError:
        raise HTTPException(
            status_code=404,
            detail=f"Allergy with id {allergy_id} not found",
        )


@router.post(
    "/allergies/{allergy_id}/deactivate",
    response_model=AllergyResponse,
    summary="Deactivate allergy",
    description="Deactivate an allergy record.",
)
async def deactivate_allergy(
    allergy_id: UUID,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> AllergyResponse:
    """Deactivate an allergy record.

    Args:
        allergy_id: Unique identifier of the allergy to deactivate.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        AllergyResponse with the updated allergy details.

    Raises:
        HTTPException: 404 if allergy not found.
        HTTPException: 401 if not authenticated.
    """
    service = MedicalService(db)

    try:
        allergy = await service.deactivate_allergy(allergy_id)
        return service._allergy_to_response(allergy)
    except AllergyNotFoundError:
        raise HTTPException(
            status_code=404,
            detail=f"Allergy with id {allergy_id} not found",
        )


# =============================================================================
# Medication Endpoints
# =============================================================================


@router.get(
    "/medications",
    response_model=MedicationListResponse,
    summary="List medications",
    description="List all medications with optional filtering and pagination.",
)
async def list_medications(
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
    child_id: Optional[UUID] = Query(
        default=None,
        description="Filter by child ID",
    ),
    medication_type: Optional[str] = Query(
        default=None,
        description="Filter by medication type (prescription, otc, supplement, emergency)",
    ),
    administered_by: Optional[str] = Query(
        default=None,
        description="Filter by administrator (staff, nurse, self, parent)",
    ),
    is_active: Optional[bool] = Query(
        default=True,
        description="Filter by active status",
    ),
    expiring_within_days: Optional[int] = Query(
        default=None,
        ge=1,
        description="Filter medications expiring within N days",
    ),
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> MedicationListResponse:
    """List medications with optional filtering and pagination.

    Args:
        skip: Number of records to skip.
        limit: Maximum number of records to return.
        child_id: Optional filter by child ID.
        medication_type: Optional filter by medication type.
        administered_by: Optional filter by administrator.
        is_active: Optional filter by active status.
        expiring_within_days: Optional filter for medications expiring soon.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        MedicationListResponse with paginated list of medications.

    Raises:
        HTTPException: 401 if not authenticated.
    """
    service = MedicalService(db)

    # Convert string filters to enums if provided
    medication_type_enum = None
    if medication_type:
        try:
            medication_type_enum = MedicationType(medication_type)
        except ValueError:
            raise HTTPException(
                status_code=400,
                detail=f"Invalid medication_type: {medication_type}",
            )

    administered_by_enum = None
    if administered_by:
        try:
            administered_by_enum = AdministeredBy(administered_by)
        except ValueError:
            raise HTTPException(
                status_code=400,
                detail=f"Invalid administered_by: {administered_by}",
            )

    medications, total = await service.list_medications(
        skip=skip,
        limit=limit,
        child_id=child_id,
        medication_type=medication_type_enum,
        administered_by=administered_by_enum,
        is_active=is_active,
        expiring_within_days=expiring_within_days,
    )

    items = [service._medication_to_response(med) for med in medications]

    return MedicationListResponse(
        items=items,
        total=total,
        skip=skip,
        limit=limit,
    )


@router.get(
    "/medications/expiring",
    response_model=list[MedicationResponse],
    summary="Get expiring medications",
    description="Get medications that are expiring soon or already expired.",
)
async def get_expiring_medications(
    days_ahead: int = Query(
        default=30,
        ge=1,
        le=365,
        description="Number of days to look ahead for expiring medications",
    ),
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> list[MedicationResponse]:
    """Get medications that are expiring soon or already expired.

    Args:
        days_ahead: Number of days to look ahead for expiring medications.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        List of MedicationResponse objects for expiring medications.

    Raises:
        HTTPException: 401 if not authenticated.
    """
    service = MedicalService(db)
    medications = await service.get_expiring_medications(days_ahead=days_ahead)

    return [service._medication_to_response(med) for med in medications]


@router.get(
    "/medications/{medication_id}",
    response_model=MedicationResponse,
    summary="Get medication by ID",
    description="Retrieve a single medication record by its unique identifier.",
)
async def get_medication(
    medication_id: UUID,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> MedicationResponse:
    """Get a single medication record by ID.

    Args:
        medication_id: Unique identifier of the medication.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        MedicationResponse with medication details.

    Raises:
        HTTPException: 404 if medication not found.
        HTTPException: 401 if not authenticated.
    """
    service = MedicalService(db)
    medication = await service.get_medication_by_id(medication_id)

    if medication is None:
        raise HTTPException(
            status_code=404,
            detail=f"Medication with id {medication_id} not found",
        )

    return service._medication_to_response(medication)


@router.get(
    "/children/{child_id}/medications",
    response_model=list[MedicationResponse],
    summary="Get medications by child",
    description="Retrieve all medications for a specific child.",
)
async def get_medications_by_child(
    child_id: UUID,
    include_inactive: bool = Query(
        default=False,
        description="Whether to include inactive medication records",
    ),
    staff_administered_only: bool = Query(
        default=False,
        description="Whether to filter to staff-administered medications only",
    ),
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> list[MedicationResponse]:
    """Get all medications for a specific child.

    Args:
        child_id: Unique identifier of the child.
        include_inactive: Whether to include inactive medication records.
        staff_administered_only: Whether to filter to staff-administered only.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        List of MedicationResponse objects for the child.

    Raises:
        HTTPException: 401 if not authenticated.
    """
    service = MedicalService(db)
    medications = await service.get_medications_by_child(
        child_id=child_id,
        include_inactive=include_inactive,
        staff_administered_only=staff_administered_only,
    )

    return [service._medication_to_response(med) for med in medications]


# =============================================================================
# Accommodation Plan Endpoints
# =============================================================================


@router.get(
    "/accommodation-plans",
    response_model=AccommodationPlanListResponse,
    summary="List accommodation plans",
    description="List all accommodation plans with optional filtering and pagination.",
)
async def list_accommodation_plans(
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
    child_id: Optional[UUID] = Query(
        default=None,
        description="Filter by child ID",
    ),
    plan_type: Optional[str] = Query(
        default=None,
        description="Filter by plan type (health_plan, emergency_action_plan, dietary_plan, etc.)",
    ),
    status: Optional[str] = Query(
        default=None,
        description="Filter by status (draft, pending_approval, approved, active, archived)",
    ),
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> AccommodationPlanListResponse:
    """List accommodation plans with optional filtering and pagination.

    Args:
        skip: Number of records to skip.
        limit: Maximum number of records to return.
        child_id: Optional filter by child ID.
        plan_type: Optional filter by plan type.
        status: Optional filter by status.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        AccommodationPlanListResponse with paginated list of plans.

    Raises:
        HTTPException: 401 if not authenticated.
    """
    service = MedicalService(db)

    # Convert string filters to enums if provided
    plan_type_enum = None
    if plan_type:
        try:
            plan_type_enum = AccommodationPlanType(plan_type)
        except ValueError:
            raise HTTPException(
                status_code=400,
                detail=f"Invalid plan_type: {plan_type}",
            )

    status_enum = None
    if status:
        try:
            status_enum = AccommodationPlanStatus(status)
        except ValueError:
            raise HTTPException(
                status_code=400,
                detail=f"Invalid status: {status}",
            )

    plans, total = await service.list_accommodation_plans(
        skip=skip,
        limit=limit,
        child_id=child_id,
        plan_type=plan_type_enum,
        status=status_enum,
    )

    items = [service._plan_to_response(plan) for plan in plans]

    return AccommodationPlanListResponse(
        items=items,
        total=total,
        skip=skip,
        limit=limit,
    )


@router.get(
    "/accommodation-plans/{plan_id}",
    response_model=AccommodationPlanResponse,
    summary="Get accommodation plan by ID",
    description="Retrieve a single accommodation plan by its unique identifier.",
)
async def get_accommodation_plan(
    plan_id: UUID,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> AccommodationPlanResponse:
    """Get a single accommodation plan by ID.

    Args:
        plan_id: Unique identifier of the plan.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        AccommodationPlanResponse with plan details.

    Raises:
        HTTPException: 404 if plan not found.
        HTTPException: 401 if not authenticated.
    """
    service = MedicalService(db)
    plan = await service.get_accommodation_plan_by_id(plan_id)

    if plan is None:
        raise HTTPException(
            status_code=404,
            detail=f"Accommodation plan with id {plan_id} not found",
        )

    return service._plan_to_response(plan)


@router.get(
    "/children/{child_id}/accommodation-plans",
    response_model=list[AccommodationPlanResponse],
    summary="Get accommodation plans by child",
    description="Retrieve all accommodation plans for a specific child.",
)
async def get_accommodation_plans_by_child(
    child_id: UUID,
    include_inactive: bool = Query(
        default=False,
        description="Whether to include archived plans",
    ),
    include_expired: bool = Query(
        default=False,
        description="Whether to include expired plans",
    ),
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> list[AccommodationPlanResponse]:
    """Get all accommodation plans for a specific child.

    Args:
        child_id: Unique identifier of the child.
        include_inactive: Whether to include archived plans.
        include_expired: Whether to include expired plans.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        List of AccommodationPlanResponse objects for the child.

    Raises:
        HTTPException: 401 if not authenticated.
    """
    service = MedicalService(db)
    plans = await service.get_accommodation_plans_by_child(
        child_id=child_id,
        include_inactive=include_inactive,
        include_expired=include_expired,
    )

    return [service._plan_to_response(plan) for plan in plans]


# =============================================================================
# Medical Alert Endpoints
# =============================================================================


@router.get(
    "/alerts",
    response_model=MedicalAlertListResponse,
    summary="List medical alerts",
    description="List all medical alerts with optional filtering and pagination.",
)
async def list_alerts(
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
    child_id: Optional[UUID] = Query(
        default=None,
        description="Filter by child ID",
    ),
    alert_type: Optional[str] = Query(
        default=None,
        description="Filter by alert type (allergy, medication, accommodation, general)",
    ),
    alert_level: Optional[str] = Query(
        default=None,
        description="Filter by alert level (info, warning, critical)",
    ),
    is_active: Optional[bool] = Query(
        default=True,
        description="Filter by active status",
    ),
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> MedicalAlertListResponse:
    """List medical alerts with optional filtering and pagination.

    Args:
        skip: Number of records to skip.
        limit: Maximum number of records to return.
        child_id: Optional filter by child ID.
        alert_type: Optional filter by alert type.
        alert_level: Optional filter by alert level.
        is_active: Optional filter by active status.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        MedicalAlertListResponse with paginated list of alerts.

    Raises:
        HTTPException: 401 if not authenticated.
    """
    service = MedicalService(db)

    # Convert string filters to enums if provided
    alert_type_enum = None
    if alert_type:
        try:
            alert_type_enum = AlertType(alert_type)
        except ValueError:
            raise HTTPException(
                status_code=400,
                detail=f"Invalid alert_type: {alert_type}",
            )

    alert_level_enum = None
    if alert_level:
        try:
            alert_level_enum = AlertLevel(alert_level)
        except ValueError:
            raise HTTPException(
                status_code=400,
                detail=f"Invalid alert_level: {alert_level}",
            )

    alerts, total = await service.list_alerts(
        skip=skip,
        limit=limit,
        child_id=child_id,
        alert_type=alert_type_enum,
        alert_level=alert_level_enum,
        is_active=is_active,
    )

    items = [service._alert_to_response(alert) for alert in alerts]

    return MedicalAlertListResponse(
        items=items,
        total=total,
        skip=skip,
        limit=limit,
    )


@router.get(
    "/alerts/{alert_id}",
    response_model=MedicalAlertResponse,
    summary="Get medical alert by ID",
    description="Retrieve a single medical alert by its unique identifier.",
)
async def get_alert(
    alert_id: UUID,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> MedicalAlertResponse:
    """Get a single medical alert by ID.

    Args:
        alert_id: Unique identifier of the alert.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        MedicalAlertResponse with alert details.

    Raises:
        HTTPException: 404 if alert not found.
        HTTPException: 401 if not authenticated.
    """
    service = MedicalService(db)
    alert = await service.get_alert_by_id(alert_id)

    if alert is None:
        raise HTTPException(
            status_code=404,
            detail=f"Medical alert with id {alert_id} not found",
        )

    return service._alert_to_response(alert)


@router.get(
    "/children/{child_id}/alerts",
    response_model=list[MedicalAlertResponse],
    summary="Get medical alerts by child",
    description="Retrieve all medical alerts for a specific child.",
)
async def get_alerts_by_child(
    child_id: UUID,
    include_inactive: bool = Query(
        default=False,
        description="Whether to include inactive alerts",
    ),
    dashboard_only: bool = Query(
        default=False,
        description="Whether to filter to dashboard alerts only",
    ),
    check_in_only: bool = Query(
        default=False,
        description="Whether to filter to check-in alerts only",
    ),
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> list[MedicalAlertResponse]:
    """Get all medical alerts for a specific child.

    Args:
        child_id: Unique identifier of the child.
        include_inactive: Whether to include inactive alerts.
        dashboard_only: Whether to filter to dashboard alerts only.
        check_in_only: Whether to filter to check-in alerts only.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        List of MedicalAlertResponse objects for the child.

    Raises:
        HTTPException: 401 if not authenticated.
    """
    service = MedicalService(db)
    alerts = await service.get_alerts_by_child(
        child_id=child_id,
        include_inactive=include_inactive,
        dashboard_only=dashboard_only,
        check_in_only=check_in_only,
    )

    return [service._alert_to_response(alert) for alert in alerts]


# =============================================================================
# Allergen Detection Endpoints
# =============================================================================


@router.post(
    "/detect-allergens",
    response_model=AllergenDetectionResponse,
    summary="Detect allergens in meal items",
    description="Check if a meal contains allergens that a child is allergic to. "
    "Uses fuzzy matching to detect potential allergen exposure risks.",
)
async def detect_allergens(
    request: AllergenDetectionRequest,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> AllergenDetectionResponse:
    """Detect allergens in meal items for a child.

    This endpoint performs fuzzy matching between meal items and the child's
    known allergies to identify potential allergen exposure risks. It returns
    detailed information about detected allergens including severity levels
    and whether immediate action is required.

    Args:
        request: The allergen detection request containing child ID and meal items.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        AllergenDetectionResponse with detected allergens and risk assessment.

    Raises:
        HTTPException: 401 if not authenticated.

    Example:
        POST /api/v1/medical/detect-allergens
        {
            "child_id": "123e4567-e89b-12d3-a456-426614174000",
            "meal_items": ["peanut butter sandwich", "apple slices", "milk"]
        }
    """
    service = MedicalService(db)

    return await service.detect_allergens(
        child_id=request.child_id,
        meal_items=request.meal_items,
        include_inactive=request.include_inactive,
    )


# =============================================================================
# Child Medical Summary Endpoints
# =============================================================================


@router.get(
    "/children/{child_id}/summary",
    response_model=ChildMedicalSummary,
    summary="Get child medical summary",
    description="Get a complete medical summary for a child including all "
    "allergies, medications, accommodation plans, and active alerts.",
)
async def get_child_medical_summary(
    child_id: UUID,
    include_inactive: bool = Query(
        default=False,
        description="Whether to include inactive records",
    ),
    include_expired_plans: bool = Query(
        default=False,
        description="Whether to include expired accommodation plans",
    ),
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> ChildMedicalSummary:
    """Get a complete medical summary for a child.

    This endpoint aggregates all medical information for a child including:
    - All allergies with severity levels and EpiPen requirements
    - All medications with administration details
    - All accommodation plans with approval status
    - All active medical alerts

    It also provides summary flags indicating:
    - Whether the child has any severe allergies
    - Whether the child requires an EpiPen
    - Whether the child has staff-administered medications

    Args:
        child_id: Unique identifier of the child.
        include_inactive: Whether to include inactive records.
        include_expired_plans: Whether to include expired accommodation plans.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        ChildMedicalSummary with all medical information for the child.

    Raises:
        HTTPException: 401 if not authenticated.

    Example:
        GET /api/v1/medical/children/123e4567-e89b-12d3-a456-426614174000/summary
    """
    service = MedicalService(db)

    return await service.get_child_medical_summary(
        child_id=child_id,
        include_inactive=include_inactive,
        include_expired_plans=include_expired_plans,
    )
