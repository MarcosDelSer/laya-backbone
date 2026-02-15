"""FastAPI router for special needs coaching guidance endpoints.

Provides endpoints for generating personalized, evidence-based coaching
recommendations for educators and parents working with children who
have special needs. All responses include citations from peer-reviewed
or official sources.
"""

from typing import Any

from fastapi import APIRouter, Depends, HTTPException, status
from sqlalchemy.ext.asyncio import AsyncSession

from app.database import get_db
from app.dependencies import get_current_user
from app.schemas.coaching import CoachingGuidanceRequest, CoachingGuidanceResponse
from app.services.coaching_service import (
    CoachingService,
    CoachingServiceError,
    InvalidChildError,
    NoSourcesFoundError,
)

router = APIRouter()


@router.post("/guidance", response_model=CoachingGuidanceResponse)
async def get_coaching_guidance(
    request: CoachingGuidanceRequest,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> CoachingGuidanceResponse:
    """Get personalized coaching guidance with evidence citations.

    Generates evidence-based coaching recommendations based on the child's
    special needs and the situation described. All recommendations include
    mandatory citations from peer-reviewed or official sources.

    Medical questions are detected and redirected to professional referral
    responses instead of providing medical advice.

    Args:
        request: The coaching guidance request containing child info and context
        db: Async database session (injected)
        current_user: Authenticated user from JWT token (injected)

    Returns:
        CoachingGuidanceResponse containing:
        - guidance_items: List of evidence-based recommendations
        - citations: Sources supporting the guidance
        - disclaimer: Safety disclaimer and professional referral notice

    Raises:
        HTTPException 400: When no matching evidence sources are found
        HTTPException 401: When JWT token is missing or invalid
        HTTPException 404: When the child_id is not found
        HTTPException 500: When an unexpected error occurs
    """
    service = CoachingService(db)

    try:
        return await service.generate_guidance(request, current_user)
    except NoSourcesFoundError as e:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=str(e),
        )
    except InvalidChildError:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Child not found",
        )
    except CoachingServiceError as e:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Coaching service error: {str(e)}",
        )
