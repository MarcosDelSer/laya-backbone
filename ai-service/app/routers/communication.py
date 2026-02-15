"""FastAPI router for parent communication endpoints.

Provides endpoints for generating personalized daily reports and at-home
activity suggestions for parents. All content is available in English and
French to comply with Quebec bilingual requirements.
"""

from typing import Any
from uuid import UUID

from fastapi import APIRouter, Depends, HTTPException, Query, status
from sqlalchemy.ext.asyncio import AsyncSession

from app.database import get_db
from app.dependencies import get_current_user
from app.schemas.communication import (
    CommunicationPreferenceRequest,
    CommunicationPreferenceResponse,
    GenerateReportRequest,
    HomeActivitiesListResponse,
    Language,
    ParentReportResponse,
    ReportFrequency,
)
from app.services.communication_service import (
    CommunicationService,
    CommunicationServiceError,
    InvalidDateError,
)

router = APIRouter()


@router.post("/generate-report", response_model=ParentReportResponse)
async def generate_report(
    request: GenerateReportRequest,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> ParentReportResponse:
    """Generate a personalized daily report for parents.

    Creates an AI-powered daily summary of a child's activities, mood,
    meals, and milestones. The report is generated in the requested
    language (English or French) for Quebec bilingual compliance.

    Args:
        request: The report generation request containing child_id, date, and language
        db: Async database session (injected)
        current_user: Authenticated user from JWT token (injected)

    Returns:
        ParentReportResponse containing:
        - summary: Main summary of the child's day
        - activities_summary: Summary of activities completed
        - mood_summary: Summary of child's mood throughout the day
        - meals_summary: Summary of meals and eating habits
        - milestones: Notable developmental milestones observed
        - educator_notes: Optional notes from educators

    Raises:
        HTTPException 400: When the date is in the future or invalid
        HTTPException 401: When JWT token is missing or invalid
        HTTPException 500: When an unexpected error occurs
    """
    service = CommunicationService(db)

    try:
        return await service.generate_report(request, current_user)
    except InvalidDateError as e:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=str(e),
        )
    except CommunicationServiceError as e:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Communication service error: {str(e)}",
        )


@router.get("/home-activities/{child_id}", response_model=HomeActivitiesListResponse)
async def get_home_activities(
    child_id: UUID,
    language: Language = Query(
        default=Language.EN,
        description="Language for the activity suggestions (en or fr)",
    ),
    limit: int = Query(
        default=5,
        ge=1,
        le=10,
        description="Maximum number of activity suggestions to return",
    ),
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> HomeActivitiesListResponse:
    """Get suggested home activities for a child.

    Returns personalized at-home activity suggestions based on the child's
    recent daycare activities. Suggestions help parents continue their
    child's developmental activities at home.

    Args:
        child_id: Unique identifier of the child
        language: Language for the activity suggestions (default: English)
        limit: Maximum number of suggestions to return (default: 5, max: 10)
        db: Async database session (injected)
        current_user: Authenticated user from JWT token (injected)

    Returns:
        HomeActivitiesListResponse containing:
        - child_id: The child's identifier
        - activities: List of home activity suggestions with materials and instructions
        - generated_at: Timestamp when suggestions were generated

    Raises:
        HTTPException 401: When JWT token is missing or invalid
        HTTPException 500: When an unexpected error occurs
    """
    service = CommunicationService(db)

    try:
        return await service.get_home_activities(
            child_id=child_id,
            language=language,
            limit=limit,
        )
    except CommunicationServiceError as e:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Communication service error: {str(e)}",
        )


@router.post("/preferences", response_model=CommunicationPreferenceResponse)
async def create_or_update_preferences(
    request: CommunicationPreferenceRequest,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> CommunicationPreferenceResponse:
    """Create or update communication preferences for a parent.

    Sets the parent's preferred language and report frequency for
    communications about their child.

    Args:
        request: The preference request containing parent_id, child_id,
                 preferred_language, and report_frequency
        db: Async database session (injected)
        current_user: Authenticated user from JWT token (injected)

    Returns:
        CommunicationPreferenceResponse containing:
        - parent_id: The parent's identifier
        - child_id: The child's identifier
        - preferred_language: The preferred language for communications
        - report_frequency: How often to generate reports

    Raises:
        HTTPException 401: When JWT token is missing or invalid
        HTTPException 500: When an unexpected error occurs
    """
    service = CommunicationService(db)

    try:
        preference = await service.update_preference(
            parent_id=request.parent_id,
            child_id=request.child_id,
            preferred_language=request.preferred_language,
            report_frequency=request.report_frequency.value,
        )

        return CommunicationPreferenceResponse(
            id=preference.id,
            parent_id=preference.parent_id,
            child_id=preference.child_id,
            preferred_language=Language(preference.preferred_language),
            report_frequency=ReportFrequency(preference.report_frequency),
            created_at=preference.created_at,
            updated_at=preference.updated_at,
        )
    except CommunicationServiceError as e:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Communication service error: {str(e)}",
        )


@router.get("/preferences/{parent_id}", response_model=CommunicationPreferenceResponse)
async def get_preferences(
    parent_id: UUID,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> CommunicationPreferenceResponse:
    """Get communication preferences for a parent.

    Retrieves the parent's communication preferences including
    preferred language and report frequency.

    Args:
        parent_id: Unique identifier of the parent user
        db: Async database session (injected)
        current_user: Authenticated user from JWT token (injected)

    Returns:
        CommunicationPreferenceResponse containing:
        - parent_id: The parent's identifier
        - child_id: The child's identifier
        - preferred_language: The preferred language for communications
        - report_frequency: How often to generate reports

    Raises:
        HTTPException 401: When JWT token is missing or invalid
        HTTPException 404: When preferences are not found for the parent
        HTTPException 500: When an unexpected error occurs
    """
    service = CommunicationService(db)

    try:
        preference = await service.get_preference_by_parent(parent_id=parent_id)

        if preference is None:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail=f"Communication preferences not found for parent {parent_id}",
            )

        return CommunicationPreferenceResponse(
            id=preference.id,
            parent_id=preference.parent_id,
            child_id=preference.child_id,
            preferred_language=Language(preference.preferred_language),
            report_frequency=ReportFrequency(preference.report_frequency),
            created_at=preference.created_at,
            updated_at=preference.updated_at,
        )
    except HTTPException:
        raise
    except CommunicationServiceError as e:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Communication service error: {str(e)}",
        )
