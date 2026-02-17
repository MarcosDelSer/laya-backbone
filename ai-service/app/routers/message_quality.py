"""FastAPI router for message quality analysis endpoints.

Provides endpoints for AI-powered message quality analysis based on Quebec
'Bonne Message' communication standards for positive parent-educator
communication in daycare settings. All analysis results and suggestions
are available in English and French for Quebec bilingual compliance.
"""

from typing import Any, Optional

from fastapi import APIRouter, Depends, HTTPException, Query, Request, status
from sqlalchemy.ext.asyncio import AsyncSession

from app.auth.audit_logger import (
    audit_logger,
    get_client_ip,
    get_endpoint,
    get_user_agent,
)
from app.auth.dependencies import require_role
from app.auth.models import UserRole
from app.database import get_db
from app.schemas.message_quality import (
    Language,
    MessageAnalysisRequest,
    MessageAnalysisResponse,
    MessageQualityHistoryResponse,
    MessageQualitySettingsRequest,
    MessageQualitySettingsResponse,
    MessageRewriteRequest,
    MessageRewriteResponse,
    MessageTemplateListResponse,
    MessageTemplateRequest,
    MessageTemplateResponse,
    QualityIssue,
    TemplateCategory,
    TrainingExampleListResponse,
)
from app.services.message_quality_service import (
    AnalysisError,
    InvalidMessageError,
    InvalidTemplateError,
    MessageQualityService,
    MessageQualityServiceError,
)

router = APIRouter()


@router.post("/analyze", response_model=MessageAnalysisResponse)
async def analyze_message(
    request: MessageAnalysisRequest,
    http_request: Request,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(require_role(UserRole.ADMIN, UserRole.TEACHER)),
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
        http_request: FastAPI Request for audit logging
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
    # Audit logging
    audit_logger.log_message_quality_access(
        action="analyze",
        current_user=current_user,
        ip_address=get_client_ip(http_request),
        user_agent=get_user_agent(http_request),
        endpoint=get_endpoint(http_request),
    )

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


@router.post("/rewrite", response_model=MessageRewriteResponse)
async def rewrite_message(
    request: MessageRewriteRequest,
    http_request: Request,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(require_role(UserRole.ADMIN, UserRole.TEACHER)),
) -> MessageRewriteResponse:
    """Generate rewrite suggestions for a message using 'I' language and sandwich method.

    Provides AI-powered rewrite suggestions that transform problematic messages
    into positive, constructive communication following Quebec 'Bonne Message' standards.
    The rewrite uses two key techniques:
    1. 'I' language transformation - replaces accusatory 'you' statements with 'I' observations
    2. Sandwich method - structures message with positive opening, factual concern, solution closing

    This endpoint first analyzes the message to detect quality issues, then generates
    a rewrite suggestion addressing those issues. The suggestion includes an explanation
    of why the rewrite is better and confidence scores.

    Args:
        request: The message rewrite request containing:
            - message_text: The message to rewrite
            - language: Language of the message (en or fr)
            - child_name: Optional child's name for personalization
        http_request: FastAPI Request for audit logging
        db: Async database session (injected)
        current_user: Authenticated user from JWT token (injected)

    Returns:
        MessageRewriteResponse containing:
        - rewrite: A RewriteSuggestion with:
            - original_text: The original message
            - suggested_text: The improved message using 'I' language and sandwich method
            - explanation: Why this rewrite is better
            - uses_i_language: Whether it uses 'I' language (always true)
            - has_sandwich_structure: Whether it follows sandwich method (always true)
            - confidence_score: Confidence score for the suggestion (0-1)

    Raises:
        HTTPException 400: When the message text is invalid or empty
        HTTPException 401: When JWT token is missing or invalid
        HTTPException 403: When user doesn't have required role (ADMIN or TEACHER)
        HTTPException 500: When an unexpected error occurs during rewriting
    """
    # Audit logging
    audit_logger.log_message_quality_access(
        action="rewrite",
        current_user=current_user,
        ip_address=get_client_ip(http_request),
        user_agent=get_user_agent(http_request),
        endpoint=get_endpoint(http_request),
    )

    service = MessageQualityService(db)

    try:
        # First analyze the message to detect quality issues
        analysis_request = MessageAnalysisRequest(
            message_text=request.message_text,
            language=request.language,
            include_rewrites=False,  # We'll generate our own rewrite
        )
        analysis = await service.analyze_message(analysis_request, current_user)

        # Generate rewrite suggestion based on detected issues
        rewrite = service.suggest_rewrite(
            message_text=request.message_text,
            issues=analysis.issues,
            language=request.language,
            child_name=request.child_name,
        )

        return MessageRewriteResponse(rewrite=rewrite)
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


@router.get("/templates", response_model=MessageTemplateListResponse)
async def get_templates(
    http_request: Request,
    language: Optional[Language] = Query(
        default=None,
        description="Filter templates by language (en or fr)",
    ),
    category: Optional[TemplateCategory] = Query(
        default=None,
        description="Filter templates by category",
    ),
    limit: int = Query(
        default=20,
        ge=1,
        le=100,
        description="Maximum number of templates to return",
    ),
    offset: int = Query(
        default=0,
        ge=0,
        description="Number of templates to skip for pagination",
    ),
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(require_role(UserRole.ADMIN, UserRole.TEACHER)),
) -> MessageTemplateListResponse:
    """Get message templates for positive parent communication.

    Returns a paginated list of message templates that educators can use
    as starting points for positive parent communication. Templates follow
    Quebec 'Bonne Message' standards and are available in both English and
    French for Quebec bilingual compliance.

    Templates can be filtered by language and category to help educators
    find the most relevant templates for their needs.

    Args:
        http_request: FastAPI Request for audit logging
        language: Optional filter by language (en or fr)
        category: Optional filter by template category
        limit: Maximum number of templates to return (default: 20, max: 100)
        offset: Number of templates to skip for pagination (default: 0)
        db: Async database session (injected)
        current_user: Authenticated user from JWT token (injected)

    Returns:
        MessageTemplateListResponse containing:
        - items: List of message templates
        - total: Total number of templates matching filters
        - page: Current page number
        - page_size: Number of items per page
        - has_more: Whether more templates are available

    Raises:
        HTTPException 401: When JWT token is missing or invalid
        HTTPException 500: When an unexpected error occurs
    """
    # Audit logging
    audit_logger.log_message_quality_access(
        action="get_templates",
        current_user=current_user,
        ip_address=get_client_ip(http_request),
        user_agent=get_user_agent(http_request),
        endpoint=get_endpoint(http_request),
    )

    service = MessageQualityService(db)

    try:
        return await service.get_templates(
            language=language,
            category=category,
            limit=limit,
            offset=offset,
        )
    except MessageQualityServiceError as e:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Message quality service error: {str(e)}",
        )


@router.post("/templates", response_model=MessageTemplateResponse, status_code=status.HTTP_201_CREATED)
async def create_template(
    request: MessageTemplateRequest,
    http_request: Request,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(require_role(UserRole.ADMIN, UserRole.TEACHER)),
) -> MessageTemplateResponse:
    """Create a custom message template.

    Creates a new custom message template that educators can use as a
    starting point for positive parent communication. The template should
    follow Quebec 'Bonne Message' standards for positive communication.

    Custom templates are associated with the creating user and are marked
    as non-system templates. They can be used by the creator and, optionally,
    shared with other educators.

    Args:
        request: The template creation request containing:
            - title: Title of the template
            - content: Template content with optional placeholders
            - category: Category of the template
            - language: Language of the template (default: English)
            - description: Optional description of when to use this template
        http_request: FastAPI Request for audit logging
        db: Async database session (injected)
        current_user: Authenticated user from JWT token (injected)

    Returns:
        MessageTemplateResponse containing the created template:
        - id: Unique identifier for the template
        - title: Title of the template
        - content: Template content
        - category: Category of the template
        - language: Language of the template
        - description: Description of when to use this template
        - is_system: Whether this is a system template (always False for custom)
        - usage_count: Number of times the template has been used (starts at 0)

    Raises:
        HTTPException 400: When the template data is invalid
        HTTPException 401: When JWT token is missing or invalid
        HTTPException 500: When an unexpected error occurs
    """
    # Audit logging
    audit_logger.log_message_quality_access(
        action="create_template",
        current_user=current_user,
        ip_address=get_client_ip(http_request),
        user_agent=get_user_agent(http_request),
        endpoint=get_endpoint(http_request),
    )

    service = MessageQualityService(db)

    try:
        return await service.create_template(request, current_user)
    except InvalidTemplateError as e:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=str(e),
        )
    except MessageQualityServiceError as e:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Message quality service error: {str(e)}",
        )


@router.get("/training-examples", response_model=TrainingExampleListResponse)
async def get_training_examples(
    http_request: Request,
    language: Optional[Language] = Query(
        default=None,
        description="Filter training examples by language (en or fr)",
    ),
    issue_type: Optional[QualityIssue] = Query(
        default=None,
        description="Filter training examples by quality issue type demonstrated",
    ),
    difficulty_level: Optional[str] = Query(
        default=None,
        description="Filter training examples by difficulty level (beginner, intermediate, advanced)",
    ),
    limit: int = Query(
        default=20,
        ge=1,
        le=100,
        description="Maximum number of training examples to return",
    ),
    offset: int = Query(
        default=0,
        ge=0,
        description="Number of training examples to skip for pagination",
    ),
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(require_role(UserRole.ADMIN, UserRole.TEACHER)),
) -> TrainingExampleListResponse:
    """Get training examples for educator learning.

    Returns a paginated list of training examples that help educators learn
    to write better messages following Quebec 'Bonne Message' standards.
    Each example shows an original message with quality issues alongside
    an improved version with explanations.

    Training examples can be filtered by language, quality issue type, and
    difficulty level to provide targeted learning experiences.

    Args:
        http_request: FastAPI Request for audit logging
        language: Optional filter by language (en or fr)
        issue_type: Optional filter by quality issue type demonstrated
        difficulty_level: Optional filter by difficulty level
        limit: Maximum number of examples to return (default: 20, max: 100)
        offset: Number of examples to skip for pagination (default: 0)
        db: Async database session (injected)
        current_user: Authenticated user from JWT token (injected)

    Returns:
        TrainingExampleListResponse containing:
        - items: List of training examples
        - total: Total number of examples matching filters
        - page: Current page number
        - page_size: Number of items per page
        - has_more: Whether more examples are available

    Raises:
        HTTPException 401: When JWT token is missing or invalid
        HTTPException 500: When an unexpected error occurs
    """
    # Audit logging
    audit_logger.log_message_quality_access(
        action="get_training_examples",
        current_user=current_user,
        ip_address=get_client_ip(http_request),
        user_agent=get_user_agent(http_request),
        endpoint=get_endpoint(http_request),
    )

    service = MessageQualityService(db)

    try:
        return await service.get_training_examples(
            language=language,
            issue_type=issue_type,
            difficulty_level=difficulty_level,
            limit=limit,
            offset=offset,
        )
    except MessageQualityServiceError as e:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Message quality service error: {str(e)}",
        )


@router.get("/history", response_model=MessageQualityHistoryResponse)
async def get_message_quality_history(
    http_request: Request,
    limit: int = Query(
        default=20,
        ge=1,
        le=100,
        description="Maximum number of history items to return",
    ),
    offset: int = Query(
        default=0,
        ge=0,
        description="Number of history items to skip for pagination",
    ),
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(require_role(UserRole.ADMIN, UserRole.TEACHER)),
) -> MessageQualityHistoryResponse:
    """Get message quality analysis history.

    Returns a paginated list of message quality analysis history records.
    Directors (admins) can see all history across all educators.
    Educators (teachers) can only see their own message quality history.

    This endpoint provides access to past message quality analyses to help
    educators track their progress and directors monitor overall quality trends.

    Args:
        http_request: FastAPI Request for audit logging
        limit: Maximum number of history items to return (default: 20, max: 100)
        offset: Number of history items to skip for pagination (default: 0)
        db: Async database session (injected)
        current_user: Authenticated user from JWT token (injected)

    Returns:
        MessageQualityHistoryResponse containing:
        - items: List of message quality history records
        - total: Total number of history items
        - page: Current page number
        - page_size: Number of items per page
        - has_more: Whether more items are available

    Raises:
        HTTPException 401: When JWT token is missing or invalid
        HTTPException 403: When user doesn't have required role
        HTTPException 500: When an unexpected error occurs
    """
    # Audit logging
    audit_logger.log_message_quality_access(
        action="history",
        current_user=current_user,
        ip_address=get_client_ip(http_request),
        user_agent=get_user_agent(http_request),
        endpoint=get_endpoint(http_request),
    )

    service = MessageQualityService(db)

    try:
        return await service.get_history(
            current_user=current_user,
            limit=limit,
            offset=offset,
        )
    except MessageQualityServiceError as e:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Message quality service error: {str(e)}",
        )


@router.put("/settings", response_model=MessageQualitySettingsResponse)
async def update_message_quality_settings(
    request: MessageQualitySettingsRequest,
    http_request: Request,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(require_role(UserRole.ADMIN)),
) -> MessageQualitySettingsResponse:
    """Update message quality configuration settings.

    Allows directors (admins) to configure quality thresholds, enable/disable
    features, and set notification preferences for the message quality system.
    This endpoint is restricted to directors only.

    Configuration options include:
    - quality_threshold: Minimum score to be considered acceptable
    - enable_auto_suggestions: Auto-show suggestions to educators
    - enable_notifications: Send notifications for low-quality messages
    - notification_threshold: Score below which to send notifications
    - strict_mode: Enforce quality checks before sending messages

    Args:
        request: The settings update request containing:
            - quality_threshold: Optional quality threshold (0-100)
            - enable_auto_suggestions: Optional auto-suggestions flag
            - enable_notifications: Optional notifications flag
            - notification_threshold: Optional notification threshold (0-100)
            - strict_mode: Optional strict mode flag
        http_request: FastAPI Request for audit logging
        db: Async database session (injected)
        current_user: Authenticated admin user from JWT token (injected)

    Returns:
        MessageQualitySettingsResponse containing:
        - quality_threshold: Current quality threshold
        - enable_auto_suggestions: Auto-suggestions status
        - enable_notifications: Notifications status
        - notification_threshold: Current notification threshold
        - strict_mode: Strict mode status
        - updated_at: Timestamp of last update
        - updated_by: ID of user who updated settings

    Raises:
        HTTPException 401: When JWT token is missing or invalid
        HTTPException 403: When user is not an admin/director
        HTTPException 400: When settings data is invalid
        HTTPException 500: When an unexpected error occurs
    """
    # Audit logging
    audit_logger.log_message_quality_access(
        action="update_settings",
        current_user=current_user,
        ip_address=get_client_ip(http_request),
        user_agent=get_user_agent(http_request),
        endpoint=get_endpoint(http_request),
    )

    service = MessageQualityService(db)

    try:
        return await service.update_settings(request, current_user)
    except MessageQualityServiceError as e:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Message quality service error: {str(e)}",
        )