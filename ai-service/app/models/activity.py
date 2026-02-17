"""Activity SQLAlchemy models for LAYA AI Service.

Defines database models for activities, recommendations, and participation tracking.
Activities represent educational activities that can be recommended to children.
"""

from datetime import datetime
from enum import Enum as PyEnum
from typing import Optional
from uuid import UUID, uuid4

from sqlalchemy import (
    Boolean,
    DateTime,
    Enum,
    Float,
    ForeignKey,
    Index,
    Integer,
    String,
    Text,
    func,
)
from sqlalchemy.dialects.postgresql import ARRAY, UUID as PGUUID
from sqlalchemy.orm import DeclarativeBase, Mapped, mapped_column, relationship


class Base(DeclarativeBase):
    """Base class for all SQLAlchemy models in LAYA AI Service.

    Provides common configuration for model classes.
    """

    pass


class ActivityType(str, PyEnum):
    """Types of educational activities.

    Attributes:
        COGNITIVE: Activities for cognitive development
        MOTOR: Activities for motor skill development
        SOCIAL: Activities for social skill development
        LANGUAGE: Activities for language development
        CREATIVE: Creative and artistic activities
        SENSORY: Sensory exploration activities
    """

    COGNITIVE = "cognitive"
    MOTOR = "motor"
    SOCIAL = "social"
    LANGUAGE = "language"
    CREATIVE = "creative"
    SENSORY = "sensory"


class ActivityDifficulty(str, PyEnum):
    """Difficulty levels for activities.

    Attributes:
        EASY: Simple activities for beginners
        MEDIUM: Moderate difficulty activities
        HARD: Challenging activities for advanced learners
    """

    EASY = "easy"
    MEDIUM = "medium"
    HARD = "hard"


class Activity(Base):
    """SQLAlchemy model for educational activities.

    Represents an educational activity that can be recommended to children
    based on their developmental needs, interests, and special needs.

    Attributes:
        id: Unique identifier for the activity
        name: Name of the activity
        description: Detailed description of the activity
        activity_type: Type/category of the activity
        difficulty: Difficulty level of the activity
        duration_minutes: Estimated duration in minutes
        materials_needed: List of materials required
        min_age_months: Minimum target age in months
        max_age_months: Maximum target age in months
        special_needs_adaptations: Adaptations for children with special needs
        is_active: Whether the activity is currently active
        created_at: Timestamp when the record was created
        updated_at: Timestamp when the record was last updated
    """

    __tablename__ = "activities"

    id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        primary_key=True,
        default=uuid4,
    )
    name: Mapped[str] = mapped_column(
        String(200),
        nullable=False,
        index=True,
    )
    description: Mapped[str] = mapped_column(
        Text,
        nullable=False,
    )
    activity_type: Mapped[ActivityType] = mapped_column(
        Enum(ActivityType, name="activity_type_enum", create_constraint=True),
        nullable=False,
        index=True,
    )
    difficulty: Mapped[ActivityDifficulty] = mapped_column(
        Enum(ActivityDifficulty, name="activity_difficulty_enum", create_constraint=True),
        nullable=False,
        default=ActivityDifficulty.MEDIUM,
    )
    duration_minutes: Mapped[int] = mapped_column(
        Integer,
        nullable=False,
        default=30,
    )
    materials_needed: Mapped[list[str]] = mapped_column(
        ARRAY(String),
        nullable=False,
        default=list,
    )
    min_age_months: Mapped[Optional[int]] = mapped_column(
        Integer,
        nullable=True,
    )
    max_age_months: Mapped[Optional[int]] = mapped_column(
        Integer,
        nullable=True,
    )
    special_needs_adaptations: Mapped[Optional[str]] = mapped_column(
        Text,
        nullable=True,
    )
    is_active: Mapped[bool] = mapped_column(
        Boolean,
        nullable=False,
        default=True,
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
    recommendations: Mapped[list["ActivityRecommendation"]] = relationship(
        "ActivityRecommendation",
        back_populates="activity",
        cascade="all, delete-orphan",
    )
    participations: Mapped[list["ActivityParticipation"]] = relationship(
        "ActivityParticipation",
        back_populates="activity",
        cascade="all, delete-orphan",
    )

    def __repr__(self) -> str:
        """Return string representation of the Activity."""
        return f"<Activity(id={self.id}, name='{self.name}', type={self.activity_type.value})>"


class ActivityRecommendation(Base):
    """SQLAlchemy model for activity recommendations.

    Stores personalized activity recommendations generated for children
    based on their profiles and developmental needs.

    Attributes:
        id: Unique identifier for the recommendation
        child_id: Unique identifier of the child
        activity_id: Unique identifier of the recommended activity
        relevance_score: Relevance score between 0 and 1
        reasoning: Explanation of why this activity was recommended
        is_dismissed: Whether the recommendation was dismissed by user
        generated_at: When the recommendation was generated
        created_at: Timestamp when the record was created
        updated_at: Timestamp when the record was last updated
    """

    __tablename__ = "activity_recommendations"

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
    activity_id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        ForeignKey("activities.id", ondelete="CASCADE"),
        nullable=False,
        index=True,
    )
    relevance_score: Mapped[float] = mapped_column(
        Float,
        nullable=False,
    )
    reasoning: Mapped[Optional[str]] = mapped_column(
        Text,
        nullable=True,
    )
    is_dismissed: Mapped[bool] = mapped_column(
        Boolean,
        nullable=False,
        default=False,
    )
    generated_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True),
        nullable=False,
        server_default=func.now(),
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
    activity: Mapped["Activity"] = relationship(
        "Activity",
        back_populates="recommendations",
    )

    # Table-level indexes for common query patterns
    __table_args__ = (
        # Composite index for filtering recommendations by child and time
        Index("ix_activity_recommendations_child_generated", "child_id", "generated_at"),
        # Composite index for getting active recommendations per child
        Index("ix_activity_recommendations_child_dismissed", "child_id", "is_dismissed"),
        # Index for time-based queries
        Index("ix_activity_recommendations_generated_at", "generated_at"),
    )

    def __repr__(self) -> str:
        """Return string representation of the ActivityRecommendation."""
        return (
            f"<ActivityRecommendation(id={self.id}, child_id={self.child_id}, "
            f"activity_id={self.activity_id}, score={self.relevance_score})>"
        )


class ActivityParticipation(Base):
    """SQLAlchemy model for tracking child participation in activities.

    Records when children participate in activities, including duration,
    completion status, and engagement metrics.

    Attributes:
        id: Unique identifier for the participation record
        child_id: Unique identifier of the child
        activity_id: Unique identifier of the activity
        started_at: When the child started the activity
        completed_at: When the child completed the activity (if completed)
        duration_minutes: Actual duration of participation in minutes
        completion_status: Status of completion (started, completed, abandoned)
        engagement_score: Engagement level during participation (0-1)
        notes: Additional notes about the participation
        created_at: Timestamp when the record was created
        updated_at: Timestamp when the record was last updated
    """

    __tablename__ = "activity_participations"

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
    activity_id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        ForeignKey("activities.id", ondelete="CASCADE"),
        nullable=False,
        index=True,
    )
    started_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True),
        nullable=False,
        server_default=func.now(),
    )
    completed_at: Mapped[Optional[datetime]] = mapped_column(
        DateTime(timezone=True),
        nullable=True,
    )
    duration_minutes: Mapped[Optional[int]] = mapped_column(
        Integer,
        nullable=True,
    )
    completion_status: Mapped[str] = mapped_column(
        String(20),
        nullable=False,
        default="started",
    )
    engagement_score: Mapped[Optional[float]] = mapped_column(
        Float,
        nullable=True,
    )
    notes: Mapped[Optional[str]] = mapped_column(
        Text,
        nullable=True,
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
    activity: Mapped["Activity"] = relationship(
        "Activity",
        back_populates="participations",
    )

    # Table-level indexes for common query patterns
    __table_args__ = (
        # Composite index for filtering participation by child and time
        Index("ix_activity_participations_child_started", "child_id", "started_at"),
        # Index for time-based queries
        Index("ix_activity_participations_started_at", "started_at"),
        # Index for filtering by completion status
        Index("ix_activity_participations_status", "completion_status"),
    )

    def __repr__(self) -> str:
        """Return string representation of the ActivityParticipation."""
        return (
            f"<ActivityParticipation(id={self.id}, child_id={self.child_id}, "
            f"activity_id={self.activity_id}, status={self.completion_status})>"
        )
