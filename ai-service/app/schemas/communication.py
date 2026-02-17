"""Communication domain schemas for LAYA AI Service.

Defines Pydantic schemas for parent communication requests and responses.
The communication system provides personalized bilingual (English/French)
daily reports and home activity suggestions for parents of children in daycare.
"""

from datetime import date, datetime
from enum import Enum
from typing import Optional
from uuid import UUID

from pydantic import Field

from app.schemas.base import BaseResponse, BaseSchema
from app.schemas.pagination import PaginatedResponse


class Language(str, Enum):
    """Supported languages for parent communication.

    Quebec compliance requires both English and French support
    for all parent-facing communications.

    Attributes:
        EN: English language
        FR: French language
    """

    EN = "en"
    FR = "fr"


class ReportFrequency(str, Enum):
    """Frequency options for parent report generation.

    Attributes:
        DAILY: Reports generated every day
        WEEKLY: Reports generated once per week
    """

    DAILY = "daily"
    WEEKLY = "weekly"


class DevelopmentalArea(str, Enum):
    """Developmental areas for home activities.

    Categorizes activities by the developmental domain they target.

    Attributes:
        COGNITIVE: Cognitive development and learning
        MOTOR: Fine and gross motor skills
        LANGUAGE: Language and communication skills
        SOCIAL: Social and emotional development
        SENSORY: Sensory exploration and processing
        CREATIVE: Creative expression and arts
    """

    COGNITIVE = "cognitive"
    MOTOR = "motor"
    LANGUAGE = "language"
    SOCIAL = "social"
    SENSORY = "sensory"
    CREATIVE = "creative"


# =============================================================================
# Request Schemas
# =============================================================================


class GenerateReportRequest(BaseSchema):
    """Request schema for generating a parent report.

    Used to request an AI-generated daily summary of a child's activities,
    mood, meals, and milestones for a specific date.

    Attributes:
        child_id: Unique identifier of the child
        report_date: Date for the report (YYYY-MM-DD format)
        language: Language for the generated report (defaults to English)
        educator_notes: Optional notes from educators to include
    """

    child_id: UUID = Field(
        ...,
        description="Unique identifier of the child",
    )
    report_date: date = Field(
        ...,
        description="Date for the report (YYYY-MM-DD format)",
    )
    language: Language = Field(
        default=Language.EN,
        description="Language for the generated report",
    )
    educator_notes: Optional[str] = Field(
        default=None,
        max_length=2000,
        description="Optional notes from educators to include in the report",
    )


class HomeActivitiesRequest(BaseSchema):
    """Request schema for getting home activity suggestions.

    Used to request personalized home activity suggestions based on
    the child's recent daycare activities.

    Attributes:
        child_id: Unique identifier of the child
        language: Language for the activity suggestions
        limit: Maximum number of suggestions to return (default: 5, max: 10)
    """

    child_id: UUID = Field(
        ...,
        description="Unique identifier of the child",
    )
    language: Language = Field(
        default=Language.EN,
        description="Language for the activity suggestions",
    )
    limit: int = Field(
        default=5,
        ge=1,
        le=10,
        description="Maximum number of activity suggestions to return",
    )


class CommunicationPreferenceRequest(BaseSchema):
    """Request schema for creating or updating communication preferences.

    Used to set parent preferences for language and report frequency.

    Attributes:
        parent_id: Unique identifier of the parent user
        child_id: Unique identifier of the child
        preferred_language: Preferred language for communications
        report_frequency: How often to generate reports
    """

    parent_id: UUID = Field(
        ...,
        description="Unique identifier of the parent user",
    )
    child_id: UUID = Field(
        ...,
        description="Unique identifier of the child",
    )
    preferred_language: Language = Field(
        default=Language.EN,
        description="Preferred language for communications",
    )
    report_frequency: ReportFrequency = Field(
        default=ReportFrequency.DAILY,
        description="How often to generate reports",
    )


# =============================================================================
# Response Schemas
# =============================================================================


class ParentReportResponse(BaseResponse):
    """Response schema for a parent report.

    Contains the complete AI-generated report with all sections
    for a child's daily activities.

    Attributes:
        child_id: Unique identifier of the child
        report_date: Date the report covers
        language: Language of the report content
        summary: Main summary of the child's day
        activities_summary: Summary of activities completed
        mood_summary: Summary of child's mood throughout the day
        meals_summary: Summary of meals and eating habits
        milestones: Notable developmental milestones observed
        educator_notes: Optional notes from educators
        generated_by: ID of the user who generated the report
    """

    child_id: UUID = Field(
        ...,
        description="Unique identifier of the child",
    )
    report_date: date = Field(
        ...,
        description="Date the report covers",
    )
    language: Language = Field(
        ...,
        description="Language of the report content",
    )
    summary: str = Field(
        ...,
        min_length=1,
        max_length=5000,
        description="Main summary of the child's day",
    )
    activities_summary: Optional[str] = Field(
        default=None,
        max_length=3000,
        description="Summary of activities completed",
    )
    mood_summary: Optional[str] = Field(
        default=None,
        max_length=2000,
        description="Summary of child's mood throughout the day",
    )
    meals_summary: Optional[str] = Field(
        default=None,
        max_length=2000,
        description="Summary of meals and eating habits",
    )
    milestones: Optional[str] = Field(
        default=None,
        max_length=2000,
        description="Notable developmental milestones observed",
    )
    educator_notes: Optional[str] = Field(
        default=None,
        max_length=2000,
        description="Optional notes from educators",
    )
    generated_by: UUID = Field(
        ...,
        description="ID of the user who generated the report",
    )


class HomeActivityBase(BaseSchema):
    """Base schema for home activity data.

    Contains common fields shared between request and response schemas.

    Attributes:
        activity_name: Name of the activity
        activity_description: Detailed description and instructions
        materials_needed: List of materials required for the activity
        estimated_duration_minutes: Estimated time to complete the activity
        developmental_area: Developmental area the activity targets
        language: Language of the activity content
        based_on_activity_id: Optional ID of the daycare activity this is based on
    """

    activity_name: str = Field(
        ...,
        min_length=1,
        max_length=200,
        description="Name of the activity",
    )
    activity_description: str = Field(
        ...,
        min_length=1,
        max_length=3000,
        description="Detailed description and instructions for the activity",
    )
    materials_needed: Optional[list[str]] = Field(
        default=None,
        description="List of materials required for the activity",
    )
    estimated_duration_minutes: Optional[int] = Field(
        default=None,
        ge=1,
        le=180,
        description="Estimated time to complete the activity in minutes",
    )
    developmental_area: Optional[DevelopmentalArea] = Field(
        default=None,
        description="Developmental area the activity targets",
    )
    language: Language = Field(
        default=Language.EN,
        description="Language of the activity content",
    )
    based_on_activity_id: Optional[UUID] = Field(
        default=None,
        description="ID of the daycare activity this suggestion is based on",
    )


class HomeActivityResponse(HomeActivityBase, BaseResponse):
    """Response schema for a home activity suggestion.

    Includes all base home activity fields plus ID, timestamps,
    and child-specific fields.

    Attributes:
        child_id: Unique identifier of the child
        is_completed: Whether the parent marked the activity as completed
        based_on: Optional description of the source daycare activity
    """

    child_id: UUID = Field(
        ...,
        description="Unique identifier of the child",
    )
    is_completed: bool = Field(
        default=False,
        description="Whether the parent marked the activity as completed",
    )
    based_on: Optional[str] = Field(
        default=None,
        max_length=500,
        description="Description of the daycare activity this is based on",
    )


class HomeActivitiesListResponse(BaseSchema):
    """Response schema for a list of home activity suggestions.

    Contains the child ID, list of activities, and generation timestamp.

    Attributes:
        child_id: Unique identifier of the child
        activities: List of home activity suggestions
        generated_at: Timestamp when the suggestions were generated
    """

    child_id: UUID = Field(
        ...,
        description="Unique identifier of the child",
    )
    activities: list[HomeActivityResponse] = Field(
        default_factory=list,
        description="List of home activity suggestions",
    )
    generated_at: datetime = Field(
        ...,
        description="Timestamp when the suggestions were generated",
    )


class CommunicationPreferenceResponse(BaseResponse):
    """Response schema for communication preferences.

    Contains the parent's communication preferences including
    language and report frequency settings.

    Attributes:
        parent_id: Unique identifier of the parent user
        child_id: Unique identifier of the child
        preferred_language: Preferred language for communications
        report_frequency: How often to generate reports
    """

    parent_id: UUID = Field(
        ...,
        description="Unique identifier of the parent user",
    )
    child_id: UUID = Field(
        ...,
        description="Unique identifier of the child",
    )
    preferred_language: Language = Field(
        ...,
        description="Preferred language for communications",
    )
    report_frequency: ReportFrequency = Field(
        ...,
        description="How often to generate reports",
    )


class ParentReportListResponse(PaginatedResponse[ParentReportResponse]):
    """Response schema for a paginated list of parent reports.

    Provides standardized pagination metadata with parent report items.

    Attributes:
        items: List of parent reports (renamed from 'reports')
        total: Total number of reports matching the query
        page: Current page number (1-indexed)
        per_page: Number of items per page
        total_pages: Total number of pages
    """

    pass
