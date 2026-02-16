"""Portfolio SQLAlchemy models for LAYA AI Service.

Defines database models for educational portfolio system including portfolio items,
observations, milestones, and work samples. Supports documentation of child development
with privacy controls and family contributions.
"""

from datetime import datetime, date
from enum import Enum as PyEnum
from typing import Optional
from uuid import UUID, uuid4

from sqlalchemy import (
    Boolean,
    Date,
    DateTime,
    Enum,
    ForeignKey,
    Integer,
    String,
    Text,
    func,
)
from sqlalchemy.dialects.postgresql import ARRAY, UUID as PGUUID, JSONB
from sqlalchemy.orm import Mapped, mapped_column, relationship

from app.models.base import Base


class PortfolioItemType(str, PyEnum):
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


class PrivacyLevel(str, PyEnum):
    """Privacy levels for portfolio items.

    Attributes:
        PRIVATE: Only visible to staff
        FAMILY: Visible to family members
        SHARED: Can be shared with other families (e.g., group photos)
    """

    PRIVATE = "private"
    FAMILY = "family"
    SHARED = "shared"


class MilestoneCategory(str, PyEnum):
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


class MilestoneStatus(str, PyEnum):
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


class ObservationType(str, PyEnum):
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


class WorkSampleType(str, PyEnum):
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


class PortfolioItem(Base):
    """SQLAlchemy model for portfolio media items.

    Represents a media item (photo, video, document) in a child's portfolio
    with privacy controls and metadata.

    Attributes:
        id: Unique identifier for the portfolio item
        child_id: Unique identifier of the child
        item_type: Type of media (photo, video, document, etc.)
        title: Title or caption for the item
        description: Detailed description or context
        media_url: URL to the media file in storage
        thumbnail_url: URL to thumbnail image (for videos/documents)
        privacy_level: Privacy setting for the item
        tags: List of tags for categorization
        captured_at: When the media was captured/created
        captured_by_id: User ID who captured/uploaded the item
        is_family_contribution: Whether this was contributed by family
        item_metadata: Additional metadata as JSON (dimensions, duration, etc.)
        is_archived: Whether the item has been archived
        created_at: Timestamp when the record was created
        updated_at: Timestamp when the record was last updated
    """

    __tablename__ = "portfolio_items"

    id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        primary_key=True,
        default=uuid4,
    )
    child_id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        nullable=False,
        index=True,
    )
    item_type: Mapped[PortfolioItemType] = mapped_column(
        Enum(PortfolioItemType, name="portfolio_item_type_enum", create_constraint=True),
        nullable=False,
        index=True,
    )
    title: Mapped[str] = mapped_column(
        String(255),
        nullable=False,
    )
    description: Mapped[Optional[str]] = mapped_column(
        Text,
        nullable=True,
    )
    media_url: Mapped[str] = mapped_column(
        String(500),
        nullable=False,
    )
    thumbnail_url: Mapped[Optional[str]] = mapped_column(
        String(500),
        nullable=True,
    )
    privacy_level: Mapped[PrivacyLevel] = mapped_column(
        Enum(PrivacyLevel, name="privacy_level_enum", create_constraint=True),
        nullable=False,
        default=PrivacyLevel.FAMILY,
        index=True,
    )
    tags: Mapped[list[str]] = mapped_column(
        ARRAY(String),
        nullable=False,
        default=list,
    )
    captured_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True),
        nullable=False,
        server_default=func.now(),
    )
    captured_by_id: Mapped[Optional[UUID]] = mapped_column(
        PGUUID(as_uuid=True),
        nullable=True,
    )
    is_family_contribution: Mapped[bool] = mapped_column(
        Boolean,
        nullable=False,
        default=False,
    )
    item_metadata: Mapped[Optional[dict]] = mapped_column(
        JSONB,
        nullable=True,
    )
    is_archived: Mapped[bool] = mapped_column(
        Boolean,
        nullable=False,
        default=False,
        index=True,
    )
    created_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True),
        nullable=False,
        server_default=func.now(),
    )
    updated_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True),
        nullable=False,
        server_default=func.now(),
        onupdate=func.now(),
    )

    # Relationships
    observations: Mapped[list["Observation"]] = relationship(
        "Observation",
        back_populates="portfolio_item",
        cascade="all, delete-orphan",
        foreign_keys="[Observation.portfolio_item_id]",
    )
    work_samples: Mapped[list["WorkSample"]] = relationship(
        "WorkSample",
        back_populates="portfolio_item",
        cascade="all, delete-orphan",
    )

    def __repr__(self) -> str:
        """Return string representation of the PortfolioItem."""
        return (
            f"<PortfolioItem(id={self.id}, child_id={self.child_id}, "
            f"type={self.item_type.value}, title='{self.title}')>"
        )


class Observation(Base):
    """SQLAlchemy model for educator observations.

    Records observations about a child's development, learning, and behavior
    made by educators during daily activities.

    Attributes:
        id: Unique identifier for the observation
        child_id: Unique identifier of the child
        observer_id: User ID of the educator who made the observation
        observation_type: Type of observation (anecdotal, learning story, etc.)
        title: Brief title for the observation
        content: Full observation content/narrative
        developmental_areas: List of developmental areas observed
        portfolio_item_id: Optional link to associated portfolio media
        observation_date: Date when observation was made
        context: Context/setting of the observation (e.g., "outdoor play")
        is_shared_with_family: Whether observation is visible to family
        is_archived: Whether the observation has been archived
        created_at: Timestamp when the record was created
        updated_at: Timestamp when the record was last updated
    """

    __tablename__ = "observations"

    id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        primary_key=True,
        default=uuid4,
    )
    child_id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        nullable=False,
        index=True,
    )
    observer_id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        nullable=False,
        index=True,
    )
    observation_type: Mapped[ObservationType] = mapped_column(
        Enum(ObservationType, name="observation_type_enum", create_constraint=True),
        nullable=False,
        default=ObservationType.ANECDOTAL,
        index=True,
    )
    title: Mapped[str] = mapped_column(
        String(255),
        nullable=False,
    )
    content: Mapped[str] = mapped_column(
        Text,
        nullable=False,
    )
    developmental_areas: Mapped[list[str]] = mapped_column(
        ARRAY(String),
        nullable=False,
        default=list,
    )
    portfolio_item_id: Mapped[Optional[UUID]] = mapped_column(
        PGUUID(as_uuid=True),
        ForeignKey("portfolio_items.id", ondelete="SET NULL"),
        nullable=True,
        index=True,
    )
    observation_date: Mapped[date] = mapped_column(
        Date,
        nullable=False,
    )
    context: Mapped[Optional[str]] = mapped_column(
        String(255),
        nullable=True,
    )
    is_shared_with_family: Mapped[bool] = mapped_column(
        Boolean,
        nullable=False,
        default=True,
    )
    is_archived: Mapped[bool] = mapped_column(
        Boolean,
        nullable=False,
        default=False,
        index=True,
    )
    created_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True),
        nullable=False,
        server_default=func.now(),
    )
    updated_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True),
        nullable=False,
        server_default=func.now(),
        onupdate=func.now(),
    )

    # Relationships
    portfolio_item: Mapped[Optional["PortfolioItem"]] = relationship(
        "PortfolioItem",
        back_populates="observations",
        foreign_keys=[portfolio_item_id],
    )
    milestones: Mapped[list["Milestone"]] = relationship(
        "Milestone",
        back_populates="observation",
        cascade="all, delete-orphan",
    )

    def __repr__(self) -> str:
        """Return string representation of the Observation."""
        return (
            f"<Observation(id={self.id}, child_id={self.child_id}, "
            f"type={self.observation_type.value}, title='{self.title}')>"
        )


class Milestone(Base):
    """SQLAlchemy model for developmental milestones.

    Tracks developmental milestones and their achievement status for children,
    supporting Quebec regulatory requirements for development monitoring.

    Attributes:
        id: Unique identifier for the milestone record
        child_id: Unique identifier of the child
        category: Category of milestone (cognitive, motor, etc.)
        name: Name/description of the milestone
        expected_age_months: Expected age in months for this milestone
        status: Current status of achievement
        first_observed_at: Date when milestone was first observed
        achieved_at: Date when milestone was fully achieved
        observation_id: Optional link to observation that documented this
        notes: Additional notes about progress
        is_flagged: Whether this milestone is flagged for attention
        created_at: Timestamp when the record was created
        updated_at: Timestamp when the record was last updated
    """

    __tablename__ = "milestones"

    id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        primary_key=True,
        default=uuid4,
    )
    child_id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        nullable=False,
        index=True,
    )
    category: Mapped[MilestoneCategory] = mapped_column(
        Enum(MilestoneCategory, name="milestone_category_enum", create_constraint=True),
        nullable=False,
        index=True,
    )
    name: Mapped[str] = mapped_column(
        String(255),
        nullable=False,
    )
    expected_age_months: Mapped[Optional[int]] = mapped_column(
        Integer,
        nullable=True,
    )
    status: Mapped[MilestoneStatus] = mapped_column(
        Enum(MilestoneStatus, name="milestone_status_enum", create_constraint=True),
        nullable=False,
        default=MilestoneStatus.NOT_STARTED,
        index=True,
    )
    first_observed_at: Mapped[Optional[date]] = mapped_column(
        Date,
        nullable=True,
    )
    achieved_at: Mapped[Optional[date]] = mapped_column(
        Date,
        nullable=True,
    )
    observation_id: Mapped[Optional[UUID]] = mapped_column(
        PGUUID(as_uuid=True),
        ForeignKey("observations.id", ondelete="SET NULL"),
        nullable=True,
        index=True,
    )
    notes: Mapped[Optional[str]] = mapped_column(
        Text,
        nullable=True,
    )
    is_flagged: Mapped[bool] = mapped_column(
        Boolean,
        nullable=False,
        default=False,
        index=True,
    )
    created_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True),
        nullable=False,
        server_default=func.now(),
    )
    updated_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True),
        nullable=False,
        server_default=func.now(),
        onupdate=func.now(),
    )

    # Relationships
    observation: Mapped[Optional["Observation"]] = relationship(
        "Observation",
        back_populates="milestones",
    )

    def __repr__(self) -> str:
        """Return string representation of the Milestone."""
        return (
            f"<Milestone(id={self.id}, child_id={self.child_id}, "
            f"category={self.category.value}, name='{self.name}', status={self.status.value})>"
        )


class WorkSample(Base):
    """SQLAlchemy model for work sample documentation.

    Documents work samples (artwork, writing, constructions) with annotations
    and learning context for portfolio assessment.

    Attributes:
        id: Unique identifier for the work sample
        child_id: Unique identifier of the child
        portfolio_item_id: Link to the portfolio media item
        sample_type: Type of work sample (artwork, writing, etc.)
        title: Title of the work sample
        description: Description of the work and context
        learning_objectives: List of learning objectives demonstrated
        educator_notes: Educator's assessment notes
        child_reflection: Child's own reflection/comments on their work
        sample_date: Date when the work was created
        is_shared_with_family: Whether visible to family
        created_at: Timestamp when the record was created
        updated_at: Timestamp when the record was last updated
    """

    __tablename__ = "work_samples"

    id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        primary_key=True,
        default=uuid4,
    )
    child_id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        nullable=False,
        index=True,
    )
    portfolio_item_id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        ForeignKey("portfolio_items.id", ondelete="CASCADE"),
        nullable=False,
        index=True,
    )
    sample_type: Mapped[WorkSampleType] = mapped_column(
        Enum(WorkSampleType, name="work_sample_type_enum", create_constraint=True),
        nullable=False,
        index=True,
    )
    title: Mapped[str] = mapped_column(
        String(255),
        nullable=False,
    )
    description: Mapped[Optional[str]] = mapped_column(
        Text,
        nullable=True,
    )
    learning_objectives: Mapped[list[str]] = mapped_column(
        ARRAY(String),
        nullable=False,
        default=list,
    )
    educator_notes: Mapped[Optional[str]] = mapped_column(
        Text,
        nullable=True,
    )
    child_reflection: Mapped[Optional[str]] = mapped_column(
        Text,
        nullable=True,
    )
    sample_date: Mapped[date] = mapped_column(
        Date,
        nullable=False,
    )
    is_shared_with_family: Mapped[bool] = mapped_column(
        Boolean,
        nullable=False,
        default=True,
    )
    created_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True),
        nullable=False,
        server_default=func.now(),
    )
    updated_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True),
        nullable=False,
        server_default=func.now(),
        onupdate=func.now(),
    )

    # Relationships
    portfolio_item: Mapped["PortfolioItem"] = relationship(
        "PortfolioItem",
        back_populates="work_samples",
    )

    def __repr__(self) -> str:
        """Return string representation of the WorkSample."""
        return (
            f"<WorkSample(id={self.id}, child_id={self.child_id}, "
            f"type={self.sample_type.value}, title='{self.title}')>"
        )
