"""Portfolio domain schemas for LAYA AI Service.

Defines Pydantic schemas for portfolio CRUD operations and responses.
Supports educational portfolio system including media items, observations,
milestones, and work samples with privacy controls.
"""

from datetime import date, datetime
from enum import Enum
from typing import Any, Optional
from uuid import UUID

from pydantic import Field

from app.schemas.base import BaseResponse, BaseSchema, PaginatedResponse


class PortfolioItemType(str, Enum):
    """Types of portfolio items.

    Attributes:
        PHOTO: Photograph of child activity or artwork
        VIDEO: Video recording of child activity
        DOCUMENT: Document such as PDF or scanned work
        AUDIO: Audio recording
        OTHER: Other media type
    """

    PHOTO = "photo"
    VIDEO = "video"
    DOCUMENT = "document"
    AUDIO = "audio"
    OTHER = "other"


class PrivacyLevel(str, Enum):
    """Privacy levels for portfolio items.

    Attributes:
        PRIVATE: Only visible to staff
        FAMILY: Visible to family members
        SHARED: Can be shared with other families (e.g., group photos)
    """

    PRIVATE = "private"
    FAMILY = "family"
    SHARED = "shared"


class MilestoneCategory(str, Enum):
    """Categories of developmental milestones.

    Attributes:
        COGNITIVE: Cognitive development milestones
        MOTOR_GROSS: Gross motor skill milestones
        MOTOR_FINE: Fine motor skill milestones
        LANGUAGE: Language and communication milestones
        SOCIAL_EMOTIONAL: Social and emotional development milestones
        SELF_CARE: Self-care and independence milestones
    """

    COGNITIVE = "cognitive"
    MOTOR_GROSS = "motor_gross"
    MOTOR_FINE = "motor_fine"
    LANGUAGE = "language"
    SOCIAL_EMOTIONAL = "social_emotional"
    SELF_CARE = "self_care"


class MilestoneStatus(str, Enum):
    """Status of milestone achievement.

    Attributes:
        NOT_STARTED: Milestone not yet observed or attempted
        EMERGING: Child is beginning to show signs of this skill
        DEVELOPING: Child is actively developing this skill
        ACHIEVED: Child has achieved this milestone
    """

    NOT_STARTED = "not_started"
    EMERGING = "emerging"
    DEVELOPING = "developing"
    ACHIEVED = "achieved"


class ObservationType(str, Enum):
    """Types of observations.

    Attributes:
        ANECDOTAL: Brief narrative observation
        RUNNING_RECORD: Detailed sequential observation
        LEARNING_STORY: Narrative documenting learning experience
        CHECKLIST: Skill-based checklist observation
        PHOTO_DOCUMENTATION: Photo with observational notes
    """

    ANECDOTAL = "anecdotal"
    RUNNING_RECORD = "running_record"
    LEARNING_STORY = "learning_story"
    CHECKLIST = "checklist"
    PHOTO_DOCUMENTATION = "photo_documentation"


class WorkSampleType(str, Enum):
    """Types of work samples.

    Attributes:
        ARTWORK: Drawings, paintings, crafts
        WRITING: Writing samples, letters, stories
        CONSTRUCTION: Building projects, block constructions
        SCIENCE: Science experiments or observations
        MUSIC: Music or rhythm activities
        OTHER: Other work sample types
    """

    ARTWORK = "artwork"
    WRITING = "writing"
    CONSTRUCTION = "construction"
    SCIENCE = "science"
    MUSIC = "music"
    OTHER = "other"


# =============================================================================
# Portfolio Item Schemas
# =============================================================================


class PortfolioItemBase(BaseSchema):
    """Base schema for portfolio item data.

    Contains common fields shared between request and response schemas.

    Attributes:
        item_type: Type of media (photo, video, document, etc.)
        title: Title or caption for the item
        description: Detailed description or context
        media_url: URL to the media file in storage
        thumbnail_url: URL to thumbnail image (for videos/documents)
        privacy_level: Privacy setting for the item
        tags: List of tags for categorization
        captured_at: When the media was captured/created
        is_family_contribution: Whether this was contributed by family
        item_metadata: Additional metadata as JSON
    """

    item_type: PortfolioItemType = Field(
        ...,
        description="Type of media (photo, video, document, etc.)",
    )
    title: str = Field(
        ...,
        min_length=1,
        max_length=255,
        description="Title or caption for the item",
    )
    description: Optional[str] = Field(
        default=None,
        max_length=5000,
        description="Detailed description or context",
    )
    media_url: str = Field(
        ...,
        min_length=1,
        max_length=500,
        description="URL to the media file in storage",
    )
    thumbnail_url: Optional[str] = Field(
        default=None,
        max_length=500,
        description="URL to thumbnail image (for videos/documents)",
    )
    privacy_level: PrivacyLevel = Field(
        default=PrivacyLevel.FAMILY,
        description="Privacy setting for the item",
    )
    tags: list[str] = Field(
        default_factory=list,
        description="List of tags for categorization",
    )
    captured_at: Optional[datetime] = Field(
        default=None,
        description="When the media was captured/created",
    )
    is_family_contribution: bool = Field(
        default=False,
        description="Whether this was contributed by family",
    )
    item_metadata: Optional[dict[str, Any]] = Field(
        default=None,
        description="Additional metadata as JSON (dimensions, duration, etc.)",
    )


class PortfolioItemCreate(PortfolioItemBase):
    """Request schema for creating a portfolio item.

    Attributes:
        child_id: Unique identifier of the child
        captured_by_id: User ID who captured/uploaded the item
    """

    child_id: UUID = Field(
        ...,
        description="Unique identifier of the child",
    )
    captured_by_id: Optional[UUID] = Field(
        default=None,
        description="User ID who captured/uploaded the item",
    )


class PortfolioItemUpdate(BaseSchema):
    """Request schema for updating a portfolio item.

    All fields are optional for partial updates.
    """

    title: Optional[str] = Field(
        default=None,
        min_length=1,
        max_length=255,
        description="Title or caption for the item",
    )
    description: Optional[str] = Field(
        default=None,
        max_length=5000,
        description="Detailed description or context",
    )
    privacy_level: Optional[PrivacyLevel] = Field(
        default=None,
        description="Privacy setting for the item",
    )
    tags: Optional[list[str]] = Field(
        default=None,
        description="List of tags for categorization",
    )
    is_archived: Optional[bool] = Field(
        default=None,
        description="Whether the item has been archived",
    )


class PortfolioItemResponse(PortfolioItemBase, BaseResponse):
    """Response schema for portfolio item data.

    Includes all base fields plus ID, timestamps, and child_id.

    Attributes:
        child_id: Unique identifier of the child
        captured_by_id: User ID who captured/uploaded the item
        is_archived: Whether the item has been archived
    """

    child_id: UUID = Field(
        ...,
        description="Unique identifier of the child",
    )
    captured_by_id: Optional[UUID] = Field(
        default=None,
        description="User ID who captured/uploaded the item",
    )
    is_archived: bool = Field(
        default=False,
        description="Whether the item has been archived",
    )


class PortfolioItemListResponse(PaginatedResponse):
    """Paginated list of portfolio items.

    Attributes:
        items: List of portfolio items
    """

    items: list[PortfolioItemResponse] = Field(
        ...,
        description="List of portfolio items",
    )


# =============================================================================
# Observation Schemas
# =============================================================================


class ObservationBase(BaseSchema):
    """Base schema for observation data.

    Contains common fields shared between request and response schemas.

    Attributes:
        observation_type: Type of observation
        title: Brief title for the observation
        content: Full observation content/narrative
        developmental_areas: List of developmental areas observed
        observation_date: Date when observation was made
        context: Context/setting of the observation
        is_shared_with_family: Whether observation is visible to family
    """

    observation_type: ObservationType = Field(
        default=ObservationType.ANECDOTAL,
        description="Type of observation (anecdotal, learning story, etc.)",
    )
    title: str = Field(
        ...,
        min_length=1,
        max_length=255,
        description="Brief title for the observation",
    )
    content: str = Field(
        ...,
        min_length=1,
        max_length=10000,
        description="Full observation content/narrative",
    )
    developmental_areas: list[str] = Field(
        default_factory=list,
        description="List of developmental areas observed",
    )
    observation_date: date = Field(
        ...,
        description="Date when observation was made",
    )
    context: Optional[str] = Field(
        default=None,
        max_length=255,
        description="Context/setting of the observation (e.g., 'outdoor play')",
    )
    is_shared_with_family: bool = Field(
        default=True,
        description="Whether observation is visible to family",
    )


class ObservationCreate(ObservationBase):
    """Request schema for creating an observation.

    Attributes:
        child_id: Unique identifier of the child
        observer_id: User ID of the educator who made the observation
        portfolio_item_id: Optional link to associated portfolio media
    """

    child_id: UUID = Field(
        ...,
        description="Unique identifier of the child",
    )
    observer_id: UUID = Field(
        ...,
        description="User ID of the educator who made the observation",
    )
    portfolio_item_id: Optional[UUID] = Field(
        default=None,
        description="Optional link to associated portfolio media",
    )


class ObservationUpdate(BaseSchema):
    """Request schema for updating an observation.

    All fields are optional for partial updates.
    """

    title: Optional[str] = Field(
        default=None,
        min_length=1,
        max_length=255,
        description="Brief title for the observation",
    )
    content: Optional[str] = Field(
        default=None,
        min_length=1,
        max_length=10000,
        description="Full observation content/narrative",
    )
    developmental_areas: Optional[list[str]] = Field(
        default=None,
        description="List of developmental areas observed",
    )
    context: Optional[str] = Field(
        default=None,
        max_length=255,
        description="Context/setting of the observation",
    )
    is_shared_with_family: Optional[bool] = Field(
        default=None,
        description="Whether observation is visible to family",
    )
    is_archived: Optional[bool] = Field(
        default=None,
        description="Whether the observation has been archived",
    )


class ObservationResponse(ObservationBase, BaseResponse):
    """Response schema for observation data.

    Includes all base fields plus ID, timestamps, and relationships.

    Attributes:
        child_id: Unique identifier of the child
        observer_id: User ID of the educator who made the observation
        portfolio_item_id: Optional link to associated portfolio media
        is_archived: Whether the observation has been archived
    """

    child_id: UUID = Field(
        ...,
        description="Unique identifier of the child",
    )
    observer_id: UUID = Field(
        ...,
        description="User ID of the educator who made the observation",
    )
    portfolio_item_id: Optional[UUID] = Field(
        default=None,
        description="Optional link to associated portfolio media",
    )
    is_archived: bool = Field(
        default=False,
        description="Whether the observation has been archived",
    )


class ObservationListResponse(PaginatedResponse):
    """Paginated list of observations.

    Attributes:
        items: List of observations
    """

    items: list[ObservationResponse] = Field(
        ...,
        description="List of observations",
    )


# =============================================================================
# Milestone Schemas
# =============================================================================


class MilestoneBase(BaseSchema):
    """Base schema for milestone data.

    Contains common fields shared between request and response schemas.

    Attributes:
        category: Category of milestone (cognitive, motor, etc.)
        name: Name/description of the milestone
        expected_age_months: Expected age in months for this milestone
        status: Current status of achievement
        notes: Additional notes about progress
        is_flagged: Whether this milestone is flagged for attention
    """

    category: MilestoneCategory = Field(
        ...,
        description="Category of milestone (cognitive, motor, etc.)",
    )
    name: str = Field(
        ...,
        min_length=1,
        max_length=255,
        description="Name/description of the milestone",
    )
    expected_age_months: Optional[int] = Field(
        default=None,
        ge=0,
        le=144,
        description="Expected age in months for this milestone (0-144)",
    )
    status: MilestoneStatus = Field(
        default=MilestoneStatus.NOT_STARTED,
        description="Current status of achievement",
    )
    notes: Optional[str] = Field(
        default=None,
        max_length=5000,
        description="Additional notes about progress",
    )
    is_flagged: bool = Field(
        default=False,
        description="Whether this milestone is flagged for attention",
    )


class MilestoneCreate(MilestoneBase):
    """Request schema for creating a milestone.

    Attributes:
        child_id: Unique identifier of the child
        first_observed_at: Date when milestone was first observed
        achieved_at: Date when milestone was fully achieved
        observation_id: Optional link to observation that documented this
    """

    child_id: UUID = Field(
        ...,
        description="Unique identifier of the child",
    )
    first_observed_at: Optional[date] = Field(
        default=None,
        description="Date when milestone was first observed",
    )
    achieved_at: Optional[date] = Field(
        default=None,
        description="Date when milestone was fully achieved",
    )
    observation_id: Optional[UUID] = Field(
        default=None,
        description="Optional link to observation that documented this",
    )


class MilestoneUpdate(BaseSchema):
    """Request schema for updating a milestone.

    All fields are optional for partial updates.
    """

    status: Optional[MilestoneStatus] = Field(
        default=None,
        description="Current status of achievement",
    )
    first_observed_at: Optional[date] = Field(
        default=None,
        description="Date when milestone was first observed",
    )
    achieved_at: Optional[date] = Field(
        default=None,
        description="Date when milestone was fully achieved",
    )
    notes: Optional[str] = Field(
        default=None,
        max_length=5000,
        description="Additional notes about progress",
    )
    is_flagged: Optional[bool] = Field(
        default=None,
        description="Whether this milestone is flagged for attention",
    )
    observation_id: Optional[UUID] = Field(
        default=None,
        description="Optional link to observation that documented this",
    )


class MilestoneResponse(MilestoneBase, BaseResponse):
    """Response schema for milestone data.

    Includes all base fields plus ID, timestamps, and relationships.

    Attributes:
        child_id: Unique identifier of the child
        first_observed_at: Date when milestone was first observed
        achieved_at: Date when milestone was fully achieved
        observation_id: Optional link to observation that documented this
    """

    child_id: UUID = Field(
        ...,
        description="Unique identifier of the child",
    )
    first_observed_at: Optional[date] = Field(
        default=None,
        description="Date when milestone was first observed",
    )
    achieved_at: Optional[date] = Field(
        default=None,
        description="Date when milestone was fully achieved",
    )
    observation_id: Optional[UUID] = Field(
        default=None,
        description="Optional link to observation that documented this",
    )


class MilestoneListResponse(PaginatedResponse):
    """Paginated list of milestones.

    Attributes:
        items: List of milestones
    """

    items: list[MilestoneResponse] = Field(
        ...,
        description="List of milestones",
    )


# =============================================================================
# Work Sample Schemas
# =============================================================================


class WorkSampleBase(BaseSchema):
    """Base schema for work sample data.

    Contains common fields shared between request and response schemas.

    Attributes:
        sample_type: Type of work sample (artwork, writing, etc.)
        title: Title of the work sample
        description: Description of the work and context
        learning_objectives: List of learning objectives demonstrated
        educator_notes: Educator's assessment notes
        child_reflection: Child's own reflection/comments on their work
        sample_date: Date when the work was created
        is_shared_with_family: Whether visible to family
    """

    sample_type: WorkSampleType = Field(
        ...,
        description="Type of work sample (artwork, writing, etc.)",
    )
    title: str = Field(
        ...,
        min_length=1,
        max_length=255,
        description="Title of the work sample",
    )
    description: Optional[str] = Field(
        default=None,
        max_length=5000,
        description="Description of the work and context",
    )
    learning_objectives: list[str] = Field(
        default_factory=list,
        description="List of learning objectives demonstrated",
    )
    educator_notes: Optional[str] = Field(
        default=None,
        max_length=5000,
        description="Educator's assessment notes",
    )
    child_reflection: Optional[str] = Field(
        default=None,
        max_length=2000,
        description="Child's own reflection/comments on their work",
    )
    sample_date: date = Field(
        ...,
        description="Date when the work was created",
    )
    is_shared_with_family: bool = Field(
        default=True,
        description="Whether visible to family",
    )


class WorkSampleCreate(WorkSampleBase):
    """Request schema for creating a work sample.

    Attributes:
        child_id: Unique identifier of the child
        portfolio_item_id: Link to the portfolio media item
    """

    child_id: UUID = Field(
        ...,
        description="Unique identifier of the child",
    )
    portfolio_item_id: UUID = Field(
        ...,
        description="Link to the portfolio media item",
    )


class WorkSampleUpdate(BaseSchema):
    """Request schema for updating a work sample.

    All fields are optional for partial updates.
    """

    title: Optional[str] = Field(
        default=None,
        min_length=1,
        max_length=255,
        description="Title of the work sample",
    )
    description: Optional[str] = Field(
        default=None,
        max_length=5000,
        description="Description of the work and context",
    )
    learning_objectives: Optional[list[str]] = Field(
        default=None,
        description="List of learning objectives demonstrated",
    )
    educator_notes: Optional[str] = Field(
        default=None,
        max_length=5000,
        description="Educator's assessment notes",
    )
    child_reflection: Optional[str] = Field(
        default=None,
        max_length=2000,
        description="Child's own reflection/comments on their work",
    )
    is_shared_with_family: Optional[bool] = Field(
        default=None,
        description="Whether visible to family",
    )


class WorkSampleResponse(WorkSampleBase, BaseResponse):
    """Response schema for work sample data.

    Includes all base fields plus ID, timestamps, and relationships.

    Attributes:
        child_id: Unique identifier of the child
        portfolio_item_id: Link to the portfolio media item
    """

    child_id: UUID = Field(
        ...,
        description="Unique identifier of the child",
    )
    portfolio_item_id: UUID = Field(
        ...,
        description="Link to the portfolio media item",
    )


class WorkSampleListResponse(PaginatedResponse):
    """Paginated list of work samples.

    Attributes:
        items: List of work samples
    """

    items: list[WorkSampleResponse] = Field(
        ...,
        description="List of work samples",
    )


# =============================================================================
# Portfolio Summary Schemas
# =============================================================================


class PortfolioSummary(BaseSchema):
    """Summary of a child's portfolio contents.

    Attributes:
        child_id: Unique identifier of the child
        total_items: Total number of portfolio items
        total_observations: Total number of observations
        total_milestones: Total number of milestones tracked
        milestones_achieved: Number of milestones achieved
        total_work_samples: Total number of work samples
        recent_items: List of recent portfolio items
        recent_observations: List of recent observations
    """

    child_id: UUID = Field(
        ...,
        description="Unique identifier of the child",
    )
    total_items: int = Field(
        default=0,
        ge=0,
        description="Total number of portfolio items",
    )
    total_observations: int = Field(
        default=0,
        ge=0,
        description="Total number of observations",
    )
    total_milestones: int = Field(
        default=0,
        ge=0,
        description="Total number of milestones tracked",
    )
    milestones_achieved: int = Field(
        default=0,
        ge=0,
        description="Number of milestones achieved",
    )
    total_work_samples: int = Field(
        default=0,
        ge=0,
        description="Total number of work samples",
    )
    recent_items: list[PortfolioItemResponse] = Field(
        default_factory=list,
        description="List of recent portfolio items",
    )
    recent_observations: list[ObservationResponse] = Field(
        default_factory=list,
        description="List of recent observations",
    )
