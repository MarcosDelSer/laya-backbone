"""Message quality domain schemas for LAYA AI Service.

Defines Pydantic schemas for message quality analysis requests and responses.
The message quality system enforces Quebec 'Bonne Message' communication standards
for positive parent-educator communication in daycare settings.
"""

from datetime import datetime
from enum import Enum
from typing import Optional
from uuid import UUID

from pydantic import Field

from app.schemas.base import BaseResponse, BaseSchema, PaginatedResponse


class QualityIssue(str, Enum):
    """Types of quality issues detected in messages.

    Based on Quebec 'Bonne Message' communication standards for
    positive parent-educator communication.

    Attributes:
        ACCUSATORY_YOU: Accusatory 'you' language that blames the parent
        JUDGMENTAL_LABEL: Judgmental labels applied to child or parent
        BLAME_SHAME: Blame or shame patterns in communication
        EXAGGERATION: Exaggerations using words like 'always', 'never'
        ALARMIST: Alarmist or fear-inducing language
        COMPARISON: Inappropriate comparisons to other children
        NEGATIVE_TONE: Overall negative tone without constructive approach
        MISSING_POSITIVE: Missing positive opening or acknowledgment
        MISSING_SOLUTION: Missing solution-oriented closing
        MULTIPLE_OBJECTIVES: Too many topics or objectives in one message
    """

    ACCUSATORY_YOU = "accusatory_you"
    JUDGMENTAL_LABEL = "judgmental_label"
    BLAME_SHAME = "blame_shame"
    EXAGGERATION = "exaggeration"
    ALARMIST = "alarmist"
    COMPARISON = "comparison"
    NEGATIVE_TONE = "negative_tone"
    MISSING_POSITIVE = "missing_positive"
    MISSING_SOLUTION = "missing_solution"
    MULTIPLE_OBJECTIVES = "multiple_objectives"


class IssueSeverity(str, Enum):
    """Severity levels for quality issues.

    Attributes:
        LOW: Minor issue, suggestion for improvement
        MEDIUM: Moderate issue, recommended to address
        HIGH: Significant issue, should be addressed before sending
        CRITICAL: Critical issue, must be addressed before sending
    """

    LOW = "low"
    MEDIUM = "medium"
    HIGH = "high"
    CRITICAL = "critical"


class MessageContext(str, Enum):
    """Context types for messages being analyzed.

    Attributes:
        DAILY_REPORT: Daily activity report to parents
        INCIDENT_REPORT: Report about an incident or concern
        MILESTONE_UPDATE: Update about child's developmental milestone
        GENERAL_UPDATE: General communication with parents
        BEHAVIOR_CONCERN: Communication about behavior concerns
        HEALTH_UPDATE: Health-related communication
    """

    DAILY_REPORT = "daily_report"
    INCIDENT_REPORT = "incident_report"
    MILESTONE_UPDATE = "milestone_update"
    GENERAL_UPDATE = "general_update"
    BEHAVIOR_CONCERN = "behavior_concern"
    HEALTH_UPDATE = "health_update"


class Language(str, Enum):
    """Supported languages for message analysis.

    Quebec compliance requires both English and French support.

    Attributes:
        EN: English language
        FR: French language
    """

    EN = "en"
    FR = "fr"


class TemplateCategory(str, Enum):
    """Categories for message templates.

    Attributes:
        POSITIVE_OPENING: Templates for positive message openings
        FACTUAL_OBSERVATION: Templates for factual observations
        SOLUTION_ORIENTED: Templates for solution-oriented closings
        FULL_MESSAGE: Complete message templates
        BEHAVIOR_CONCERN: Templates for addressing behavior concerns
        MILESTONE_CELEBRATION: Templates for celebrating milestones
    """

    POSITIVE_OPENING = "positive_opening"
    FACTUAL_OBSERVATION = "factual_observation"
    SOLUTION_ORIENTED = "solution_oriented"
    FULL_MESSAGE = "full_message"
    BEHAVIOR_CONCERN = "behavior_concern"
    MILESTONE_CELEBRATION = "milestone_celebration"


# =============================================================================
# Quality Issue Detail Schemas
# =============================================================================


class QualityIssueDetail(BaseSchema):
    """Detailed information about a detected quality issue.

    Attributes:
        issue_type: Type of quality issue detected
        severity: Severity level of the issue
        description: Human-readable description of the issue
        original_text: The problematic text fragment
        position_start: Start position of the issue in the message
        position_end: End position of the issue in the message
        suggestion: Suggestion for how to fix the issue
    """

    issue_type: QualityIssue = Field(
        ...,
        description="Type of quality issue detected",
    )
    severity: IssueSeverity = Field(
        ...,
        description="Severity level of the issue",
    )
    description: str = Field(
        ...,
        min_length=1,
        max_length=500,
        description="Human-readable description of the issue",
    )
    original_text: str = Field(
        ...,
        max_length=500,
        description="The problematic text fragment",
    )
    position_start: int = Field(
        ...,
        ge=0,
        description="Start position of the issue in the message",
    )
    position_end: int = Field(
        ...,
        ge=0,
        description="End position of the issue in the message",
    )
    suggestion: Optional[str] = Field(
        default=None,
        max_length=500,
        description="Suggestion for how to fix the issue",
    )


# =============================================================================
# Rewrite Suggestion Schemas
# =============================================================================


class RewriteSuggestion(BaseSchema):
    """A suggested rewrite for improving message quality.

    Implements 'I' language transformation and sandwich method
    (positive opening, factual concern, solution-oriented closing).

    Attributes:
        original_text: The original text that can be improved
        suggested_text: The suggested improved text
        explanation: Explanation of why this rewrite is better
        uses_i_language: Whether the rewrite uses 'I' language
        has_sandwich_structure: Whether it follows sandwich method
        confidence_score: Confidence score for this suggestion (0-1)
    """

    original_text: str = Field(
        ...,
        min_length=1,
        max_length=2000,
        description="The original text that can be improved",
    )
    suggested_text: str = Field(
        ...,
        min_length=1,
        max_length=2000,
        description="The suggested improved text",
    )
    explanation: str = Field(
        ...,
        min_length=1,
        max_length=500,
        description="Explanation of why this rewrite is better",
    )
    uses_i_language: bool = Field(
        default=False,
        description="Whether the rewrite uses 'I' language",
    )
    has_sandwich_structure: bool = Field(
        default=False,
        description="Whether it follows sandwich method",
    )
    confidence_score: float = Field(
        default=0.0,
        ge=0.0,
        le=1.0,
        description="Confidence score for this suggestion (0-1)",
    )


# =============================================================================
# Request Schemas
# =============================================================================


class MessageAnalysisRequest(BaseSchema):
    """Request schema for analyzing message quality.

    Used to request AI-powered quality analysis of an educator message
    before sending to parents.

    Attributes:
        message_text: The message text to analyze
        language: Language of the message (for bilingual support)
        context: Context type for the message
        child_id: Optional child ID for personalized analysis
        include_rewrites: Whether to include rewrite suggestions
    """

    message_text: str = Field(
        ...,
        min_length=1,
        max_length=5000,
        description="The message text to analyze",
    )
    language: Language = Field(
        default=Language.EN,
        description="Language of the message (for bilingual support)",
    )
    context: MessageContext = Field(
        default=MessageContext.GENERAL_UPDATE,
        description="Context type for the message",
    )
    child_id: Optional[UUID] = Field(
        default=None,
        description="Optional child ID for personalized analysis",
    )
    include_rewrites: bool = Field(
        default=True,
        description="Whether to include rewrite suggestions",
    )


class MessageTemplateRequest(BaseSchema):
    """Request schema for creating a message template.

    Attributes:
        title: Title of the template
        content: Template content with optional placeholders
        category: Category of the template
        language: Language of the template
        description: Optional description of when to use this template
    """

    title: str = Field(
        ...,
        min_length=1,
        max_length=200,
        description="Title of the template",
    )
    content: str = Field(
        ...,
        min_length=1,
        max_length=5000,
        description="Template content with optional placeholders",
    )
    category: TemplateCategory = Field(
        ...,
        description="Category of the template",
    )
    language: Language = Field(
        default=Language.EN,
        description="Language of the template",
    )
    description: Optional[str] = Field(
        default=None,
        max_length=500,
        description="Optional description of when to use this template",
    )


class MessageRewriteRequest(BaseSchema):
    """Request schema for message rewrite suggestions.

    Provides a simplified endpoint focused solely on rewrite suggestions
    using 'I' language and sandwich method.

    Attributes:
        message_text: The message text to rewrite
        language: Language of the message
        child_name: Optional child's name for personalization
    """

    message_text: str = Field(
        ...,
        min_length=1,
        max_length=5000,
        description="The message text to rewrite",
    )
    language: Language = Field(
        default=Language.EN,
        description="Language of the message",
    )
    child_name: Optional[str] = Field(
        default=None,
        max_length=100,
        description="Optional child's name for personalization",
    )


class TrainingExampleRequest(BaseSchema):
    """Request schema for creating a training example.

    Training examples help educators learn to write better messages.

    Attributes:
        original_message: The original message with quality issues
        improved_message: The improved version of the message
        issues_demonstrated: List of quality issues demonstrated
        explanation: Explanation of the improvements made
        language: Language of the example
    """

    original_message: str = Field(
        ...,
        min_length=1,
        max_length=5000,
        description="The original message with quality issues",
    )
    improved_message: str = Field(
        ...,
        min_length=1,
        max_length=5000,
        description="The improved version of the message",
    )
    issues_demonstrated: list[QualityIssue] = Field(
        ...,
        min_length=1,
        description="List of quality issues demonstrated",
    )
    explanation: str = Field(
        ...,
        min_length=1,
        max_length=1000,
        description="Explanation of the improvements made",
    )
    language: Language = Field(
        default=Language.EN,
        description="Language of the example",
    )


# =============================================================================
# Response Schemas
# =============================================================================


class MessageAnalysisResponse(BaseResponse):
    """Response schema for message quality analysis.

    Contains the complete analysis results including quality score,
    detected issues, and rewrite suggestions.

    Attributes:
        message_text: The analyzed message text
        language: Language of the analysis
        quality_score: Overall quality score (0-100)
        is_acceptable: Whether the message meets quality standards
        issues: List of detected quality issues
        rewrite_suggestions: List of suggested rewrites
        has_positive_opening: Whether message has positive opening
        has_factual_basis: Whether message is factual
        has_solution_focus: Whether message is solution-oriented
        analysis_notes: Additional notes from the analysis
    """

    message_text: str = Field(
        ...,
        description="The analyzed message text",
    )
    language: Language = Field(
        ...,
        description="Language of the analysis",
    )
    quality_score: int = Field(
        ...,
        ge=0,
        le=100,
        description="Overall quality score (0-100)",
    )
    is_acceptable: bool = Field(
        ...,
        description="Whether the message meets quality standards",
    )
    issues: list[QualityIssueDetail] = Field(
        default_factory=list,
        description="List of detected quality issues",
    )
    rewrite_suggestions: list[RewriteSuggestion] = Field(
        default_factory=list,
        description="List of suggested rewrites",
    )
    has_positive_opening: bool = Field(
        default=False,
        description="Whether message has positive opening",
    )
    has_factual_basis: bool = Field(
        default=True,
        description="Whether message is factual",
    )
    has_solution_focus: bool = Field(
        default=False,
        description="Whether message is solution-oriented",
    )
    analysis_notes: Optional[str] = Field(
        default=None,
        max_length=1000,
        description="Additional notes from the analysis",
    )


class MessageTemplateResponse(BaseResponse):
    """Response schema for a message template.

    Attributes:
        title: Title of the template
        content: Template content
        category: Category of the template
        language: Language of the template
        description: Description of when to use this template
        is_system: Whether this is a system-provided template
        usage_count: Number of times this template has been used
    """

    title: str = Field(
        ...,
        description="Title of the template",
    )
    content: str = Field(
        ...,
        description="Template content",
    )
    category: TemplateCategory = Field(
        ...,
        description="Category of the template",
    )
    language: Language = Field(
        ...,
        description="Language of the template",
    )
    description: Optional[str] = Field(
        default=None,
        description="Description of when to use this template",
    )
    is_system: bool = Field(
        default=False,
        description="Whether this is a system-provided template",
    )
    usage_count: int = Field(
        default=0,
        ge=0,
        description="Number of times this template has been used",
    )


class TrainingExampleResponse(BaseResponse):
    """Response schema for a training example.

    Attributes:
        original_message: The original message with quality issues
        improved_message: The improved version of the message
        issues_demonstrated: List of quality issues demonstrated
        explanation: Explanation of the improvements made
        language: Language of the example
        difficulty_level: Difficulty level for training purposes
    """

    original_message: str = Field(
        ...,
        description="The original message with quality issues",
    )
    improved_message: str = Field(
        ...,
        description="The improved version of the message",
    )
    issues_demonstrated: list[QualityIssue] = Field(
        ...,
        description="List of quality issues demonstrated",
    )
    explanation: str = Field(
        ...,
        description="Explanation of the improvements made",
    )
    language: Language = Field(
        ...,
        description="Language of the example",
    )
    difficulty_level: str = Field(
        default="beginner",
        description="Difficulty level for training purposes",
    )


class MessageRewriteResponse(BaseResponse):
    """Response schema for message rewrite suggestions.

    Contains a single rewrite suggestion using 'I' language and sandwich method.

    Attributes:
        rewrite: The suggested rewrite with explanation
    """

    rewrite: RewriteSuggestion = Field(
        ...,
        description="The suggested rewrite with explanation",
    )


# =============================================================================
# Paginated Response Schemas
# =============================================================================


class MessageTemplateListResponse(PaginatedResponse):
    """Paginated response for message templates.

    Attributes:
        items: List of message templates
    """

    items: list[MessageTemplateResponse] = Field(
        ...,
        description="List of message templates",
    )


class TrainingExampleListResponse(PaginatedResponse):
    """Paginated response for training examples.

    Attributes:
        items: List of training examples
    """

    items: list[TrainingExampleResponse] = Field(
        ...,
        description="List of training examples",
    )
