"""FastAPI router for message quality analysis endpoints.

Provides endpoints for AI-powered message quality analysis based on Quebec
'Bonne Message' communication standards for positive parent-educator
communication in daycare settings. All analysis results and suggestions
are available in English and French for Quebec bilingual compliance.
"""

from typing import Any

from fastapi import APIRouter, Depends, HTTPException, status
from sqlalchemy.ext.asyncio import AsyncSession

from app.database import get_db
from app.dependencies import get_current_user
from app.schemas.message_quality import (
    MessageAnalysisRequest,
    MessageAnalysisResponse,
)
from app.services.message_quality_service import (
    AnalysisError,
    InvalidMessageError,
    MessageQualityService,
    MessageQualityServiceError,
)

router = APIRouter()


@router.post("/analyze", response_model=MessageAnalysisResponse)
async def analyze_message(
    request: MessageAnalysisRequest,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> MessageAnalysisResponse:
    """Analyze message quality against Quebec 'Bonne Message' standards.

    Performs AI-powered quality analysis of educator messages before sending
    to parents. Detects problematic language patterns and provides suggestions
    for improvement based on Quebec 'Bonne Message' communication standards.

    Analysis covers:
    - Accusatory 'you' language detection
    - Judgmental labels identification
    - Blame/shame patterns
    - Exaggerations ('always', 'never')
    - Alarmist language
    - Inappropriate comparisons to other children
    - Message structure (positive opening, factual content, solution closing)

    Args:
        request: The message analysis request containing:
            - message_text: The message to analyze
            - language: Language of the message (en or fr)
            - context: Context type for the message
            - child_id: Optional child ID for personalized analysis
            - include_rewrites: Whether to include rewrite suggestions
        db: Async database session (injected)
        current_user: Authenticated user from JWT token (injected)

    Returns:
        MessageAnalysisResponse containing:
        - message_text: The analyzed message text
        - language: Language of the analysis
        - quality_score: Overall quality score (0-100)
        - is_acceptable: Whether the message meets quality standards
        - issues: List of detected quality issues with details
        - rewrite_suggestions: List of suggested rewrites using 'I' language
        - has_positive_opening: Whether message has positive opening
        - has_factual_basis: Whether message is factual
        - has_solution_focus: Whether message is solution-oriented
        - analysis_notes: Additional notes from the analysis

    Raises:
        HTTPException 400: When the message text is invalid or empty
        HTTPException 401: When JWT token is missing or invalid
        HTTPException 500: When an unexpected error occurs during analysis
    """
    service = MessageQualityService(db)

    try:
        return await service.analyze_message(request, current_user)
    except InvalidMessageError as e:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=str(e),
        )
    except AnalysisError as e:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Analysis error: {str(e)}",
        )
    except MessageQualityServiceError as e:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Message quality service error: {str(e)}",
        )
