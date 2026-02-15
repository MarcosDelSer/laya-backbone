"""Coaching domain schemas for LAYA AI Service.

Defines Pydantic schemas for special needs coaching guidance requests
and responses. The coaching system provides personalized guidance for
educators and parents working with children who have special needs.
"""

from datetime import datetime
from enum import Enum
from typing import Optional
from uuid import UUID

from pydantic import BaseModel, Field

from app.schemas.base import BaseResponse, BaseSchema, PaginatedResponse


class SpecialNeedType(str, Enum):
    """Types of special needs supported by the coaching system.

    Attributes:
        AUTISM: Autism Spectrum Disorder
        ADHD: Attention Deficit Hyperactivity Disorder
        DYSLEXIA: Dyslexia and reading difficulties
        SPEECH_DELAY: Speech and language delays
        MOTOR_DELAY: Motor skill development delays
        SENSORY_PROCESSING: Sensory processing disorders
        BEHAVIORAL: Behavioral challenges
        COGNITIVE_DELAY: Cognitive developmental delays
        VISUAL_IMPAIRMENT: Visual impairments
        HEARING_IMPAIRMENT: Hearing impairments
        OTHER: Other special needs not listed
    """

    AUTISM = "autism"
    ADHD = "adhd"
    DYSLEXIA = "dyslexia"
    SPEECH_DELAY = "speech_delay"
    MOTOR_DELAY = "motor_delay"
    SENSORY_PROCESSING = "sensory_processing"
    BEHAVIORAL = "behavioral"
    COGNITIVE_DELAY = "cognitive_delay"
    VISUAL_IMPAIRMENT = "visual_impairment"
    HEARING_IMPAIRMENT = "hearing_impairment"
    OTHER = "other"


class CoachingCategory(str, Enum):
    """Categories of coaching guidance.

    Attributes:
        ACTIVITY_ADAPTATION: Adapting activities for special needs
        COMMUNICATION: Communication strategies
        BEHAVIOR_MANAGEMENT: Behavior management techniques
        SENSORY_SUPPORT: Sensory support strategies
        MOTOR_SUPPORT: Motor skill support
        SOCIAL_SKILLS: Social skills development
        PARENT_GUIDANCE: Guidance for parents
        EDUCATOR_TRAINING: Training for educators
    """

    ACTIVITY_ADAPTATION = "activity_adaptation"
    COMMUNICATION = "communication"
    BEHAVIOR_MANAGEMENT = "behavior_management"
    SENSORY_SUPPORT = "sensory_support"
    MOTOR_SUPPORT = "motor_support"
    SOCIAL_SKILLS = "social_skills"
    PARENT_GUIDANCE = "parent_guidance"
    EDUCATOR_TRAINING = "educator_training"


class CoachingPriority(str, Enum):
    """Priority levels for coaching guidance.

    Attributes:
        LOW: Low priority guidance
        MEDIUM: Medium priority guidance
        HIGH: High priority guidance
        URGENT: Urgent guidance requiring immediate attention
    """

    LOW = "low"
    MEDIUM = "medium"
    HIGH = "high"
    URGENT = "urgent"


class CoachingBase(BaseSchema):
    """Base schema for coaching guidance data.

    Contains common fields shared between request and response schemas.

    Attributes:
        title: Title of the coaching guidance
        content: Detailed coaching content and instructions
        category: Category of the coaching guidance
        special_need_types: Types of special needs this guidance addresses
        priority: Priority level of the guidance
        target_audience: Intended audience (educator, parent, etc.)
        prerequisites: Prerequisites or background knowledge needed
    """

    title: str = Field(
        ...,
        min_length=1,
        max_length=200,
        description="Title of the coaching guidance",
    )
    content: str = Field(
        ...,
        min_length=1,
        max_length=5000,
        description="Detailed coaching content and instructions",
    )
    category: CoachingCategory = Field(
        ...,
        description="Category of the coaching guidance",
    )
    special_need_types: list[SpecialNeedType] = Field(
        default_factory=list,
        description="Types of special needs this guidance addresses",
    )
    priority: CoachingPriority = Field(
        default=CoachingPriority.MEDIUM,
        description="Priority level of the guidance",
    )
    target_audience: str = Field(
        default="educator",
        max_length=100,
        description="Intended audience (educator, parent, etc.)",
    )
    prerequisites: Optional[str] = Field(
        default=None,
        max_length=1000,
        description="Prerequisites or background knowledge needed",
    )


class CoachingRequest(CoachingBase):
    """Request schema for creating or updating coaching guidance.

    Inherits all fields from CoachingBase.
    """

    pass


class CoachingResponse(CoachingBase, BaseResponse):
    """Response schema for coaching guidance data.

    Includes all base coaching fields plus ID and timestamps.

    Attributes:
        is_published: Whether the guidance is published and visible
        view_count: Number of times the guidance has been viewed
    """

    is_published: bool = Field(
        default=True,
        description="Whether the guidance is published and visible",
    )
    view_count: int = Field(
        default=0,
        ge=0,
        description="Number of times the guidance has been viewed",
    )


class CoachingGuidanceRequest(BaseSchema):
    """Request schema for getting personalized coaching guidance.

    Used to request coaching guidance based on child profile and situation.

    Attributes:
        child_id: Unique identifier of the child
        special_need_types: Special needs of the child
        situation_description: Description of the current situation or challenge
        category: Optional filter for specific coaching category
        max_recommendations: Maximum number of guidance items to return
    """

    child_id: UUID = Field(
        ...,
        description="Unique identifier of the child",
    )
    special_need_types: list[SpecialNeedType] = Field(
        ...,
        min_length=1,
        description="Special needs of the child",
    )
    situation_description: Optional[str] = Field(
        default=None,
        max_length=2000,
        description="Description of the current situation or challenge",
    )
    category: Optional[CoachingCategory] = Field(
        default=None,
        description="Optional filter for specific coaching category",
    )
    max_recommendations: int = Field(
        default=5,
        ge=1,
        le=20,
        description="Maximum number of guidance items to return",
    )


class EvidenceSourceSchema(BaseSchema):
    """Schema for evidence source citations.

    Represents a source of evidence supporting coaching guidance,
    such as research papers, clinical guidelines, or expert resources.

    Attributes:
        title: Title of the evidence source
        authors: Authors of the source (if applicable)
        publication_year: Year the source was published
        source_type: Type of source (research, guideline, expert, etc.)
        url: Optional URL to access the source
        doi: Optional DOI identifier for academic sources
    """

    title: str = Field(
        ...,
        min_length=1,
        max_length=500,
        description="Title of the evidence source",
    )
    authors: Optional[str] = Field(
        default=None,
        max_length=500,
        description="Authors of the source (if applicable)",
    )
    publication_year: Optional[int] = Field(
        default=None,
        ge=1900,
        le=2100,
        description="Year the source was published",
    )
    source_type: str = Field(
        default="research",
        max_length=100,
        description="Type of source (research, guideline, expert, etc.)",
    )
    url: Optional[str] = Field(
        default=None,
        max_length=1000,
        description="Optional URL to access the source",
    )
    doi: Optional[str] = Field(
        default=None,
        max_length=100,
        description="Optional DOI identifier for academic sources",
    )


class CoachingGuidance(BaseSchema):
    """A single coaching guidance item with relevance information.

    Attributes:
        coaching: The coaching guidance
        relevance_score: How relevant this guidance is (0-1)
        applicability_notes: Notes on how to apply this guidance
    """

    coaching: CoachingResponse = Field(
        ...,
        description="The coaching guidance",
    )
    relevance_score: float = Field(
        ...,
        ge=0.0,
        le=1.0,
        description="Relevance score between 0 and 1",
    )
    applicability_notes: Optional[str] = Field(
        default=None,
        max_length=500,
        description="Notes on how to apply this guidance",
    )


class CoachingGuidanceResponse(BaseSchema):
    """Response schema for coaching guidance recommendations.

    Contains a list of recommended coaching guidance items with
    evidence-based citations and appropriate disclaimers.

    Attributes:
        child_id: The child this guidance is for
        guidance_items: List of coaching guidance items
        generated_at: When the guidance was generated
        citations: List of evidence sources supporting the guidance
        disclaimer: Important disclaimer about the guidance limitations
    """

    child_id: UUID = Field(
        ...,
        description="The child this guidance is for",
    )
    guidance_items: list[CoachingGuidance] = Field(
        ...,
        description="List of coaching guidance items",
    )
    generated_at: datetime = Field(
        ...,
        description="When the guidance was generated",
    )
    citations: list[EvidenceSourceSchema] = Field(
        default_factory=list,
        description="List of evidence sources supporting the guidance",
    )
    disclaimer: Optional[str] = Field(
        default="This guidance is for informational purposes only and does not "
        "constitute professional medical, therapeutic, or educational advice. "
        "Always consult with qualified professionals for specific recommendations.",
        max_length=2000,
        description="Important disclaimer about the guidance limitations",
    )


class CoachingListResponse(PaginatedResponse):
    """Paginated list of coaching guidance.

    Attributes:
        items: List of coaching guidance items
    """

    items: list[CoachingResponse] = Field(
        ...,
        description="List of coaching guidance items",
    )
