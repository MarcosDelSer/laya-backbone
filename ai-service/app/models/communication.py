"""SQLAlchemy models for parent communication domain.

Defines database models for parent reports, home activities, and communication
preferences. These models support the Intelligent Parent Communication system
that generates personalized bilingual reports and home activity suggestions.
"""

from datetime import datetime
from typing import Optional
from uuid import uuid4

from sqlalchemy import Boolean, Date, DateTime, ForeignKey, Index, Integer, String, Text
from sqlalchemy.dialects.postgresql import UUID
from sqlalchemy.orm import Mapped, mapped_column

from app.models.base import Base


class ParentReport(Base):
    """Store generated parent reports.

    Stores AI-generated daily summaries of a child's activities, mood, meals,
    and milestones observed during the day. Reports are available in both
    English and French for Quebec bilingual compliance.

    Attributes:
        id: Unique identifier for the report
        child_id: ID of the child the report is about
        report_date: Date the report covers
        language: Language of the report (en/fr)
        summary: Main summary of the child's day
        activities_summary: Summary of activities completed
        mood_summary: Summary of child's mood throughout the day
        meals_summary: Summary of meals and eating habits
        milestones: Notable developmental milestones observed
        educator_notes: Optional notes from educators
        generated_by: ID of the user who generated the report
        created_at: Timestamp when the report was created
        updated_at: Timestamp when the report was last updated
    """

    __tablename__ = "parent_reports"

    id: Mapped[UUID] = mapped_column(
        UUID(as_uuid=True),
        primary_key=True,
        default=uuid4,
    )
    child_id: Mapped[UUID] = mapped_column(
        UUID(as_uuid=True),
        nullable=False,
        index=True,
    )
    report_date: Mapped[datetime] = mapped_column(
        Date,
        nullable=False,
    )
    language: Mapped[str] = mapped_column(
        String(2),
        nullable=False,
        default="en",
    )
    summary: Mapped[str] = mapped_column(
        Text,
        nullable=False,
    )
    activities_summary: Mapped[Optional[str]] = mapped_column(
        Text,
        nullable=True,
    )
    mood_summary: Mapped[Optional[str]] = mapped_column(
        Text,
        nullable=True,
    )
    meals_summary: Mapped[Optional[str]] = mapped_column(
        Text,
        nullable=True,
    )
    milestones: Mapped[Optional[str]] = mapped_column(
        Text,
        nullable=True,
    )
    educator_notes: Mapped[Optional[str]] = mapped_column(
        Text,
        nullable=True,
    )
    generated_by: Mapped[UUID] = mapped_column(
        UUID(as_uuid=True),
        nullable=False,
    )
    created_at: Mapped[datetime] = mapped_column(
        DateTime,
        default=datetime.utcnow,
        nullable=False,
    )
    updated_at: Mapped[Optional[datetime]] = mapped_column(
        DateTime,
        default=datetime.utcnow,
        onupdate=datetime.utcnow,
        nullable=True,
    )

    # Table-level indexes for common query patterns
    __table_args__ = (
        # Composite index for child + date queries (already exists)
        Index("ix_parent_reports_child_date", "child_id", "report_date"),
        # Index for language-specific queries
        Index("ix_parent_reports_language", "language"),
        # Composite index for filtering reports by date range
        Index("ix_parent_reports_date_created", "report_date", "created_at"),
    )


class HomeActivity(Base):
    """Store home activity suggestions for parents.

    Each home activity is a personalized suggestion for parents to continue
    their child's developmental activities at home. Activities are based on
    daycare activities and are available in both English and French.

    Attributes:
        id: Unique identifier for the home activity
        child_id: ID of the child the activity is suggested for
        activity_name: Name of the activity
        activity_description: Detailed description and instructions
        materials_needed: List of materials required for the activity
        estimated_duration_minutes: Estimated time to complete the activity
        developmental_area: Developmental area the activity targets
        language: Language of the activity content (en/fr)
        based_on_activity_id: Optional ID of the daycare activity this is based on
        is_completed: Whether the parent marked the activity as completed
        created_at: Timestamp when the activity was created
        updated_at: Timestamp when the activity was last updated
    """

    __tablename__ = "home_activities"

    id: Mapped[UUID] = mapped_column(
        UUID(as_uuid=True),
        primary_key=True,
        default=uuid4,
    )
    child_id: Mapped[UUID] = mapped_column(
        UUID(as_uuid=True),
        nullable=False,
        index=True,
    )
    activity_name: Mapped[str] = mapped_column(
        String(200),
        nullable=False,
    )
    activity_description: Mapped[str] = mapped_column(
        Text,
        nullable=False,
    )
    materials_needed: Mapped[Optional[str]] = mapped_column(
        Text,
        nullable=True,
    )
    estimated_duration_minutes: Mapped[Optional[int]] = mapped_column(
        Integer,
        nullable=True,
    )
    developmental_area: Mapped[Optional[str]] = mapped_column(
        String(50),
        nullable=True,
    )
    language: Mapped[str] = mapped_column(
        String(2),
        nullable=False,
        default="en",
    )
    based_on_activity_id: Mapped[Optional[UUID]] = mapped_column(
        UUID(as_uuid=True),
        nullable=True,
    )
    is_completed: Mapped[bool] = mapped_column(
        Boolean,
        nullable=False,
        default=False,
    )
    created_at: Mapped[datetime] = mapped_column(
        DateTime,
        default=datetime.utcnow,
        nullable=False,
    )
    updated_at: Mapped[Optional[datetime]] = mapped_column(
        DateTime,
        default=datetime.utcnow,
        onupdate=datetime.utcnow,
        nullable=True,
    )

    # Table-level indexes for common query patterns
    __table_args__ = (
        # Index for child-specific activities
        Index("ix_home_activities_child", "child_id"),
        # Composite index for filtering by child and completion status
        Index("ix_home_activities_child_completed", "child_id", "is_completed"),
        # Index for developmental area filtering
        Index("ix_home_activities_dev_area", "developmental_area"),
        # Composite index for language-specific queries
        Index("ix_home_activities_language", "language"),
    )


class CommunicationPreference(Base):
    """Store parent communication preferences.

    Stores parent preferences for language and report frequency to personalize
    the communication experience. This ensures Quebec bilingual compliance by
    defaulting to the parent's preferred language.

    Attributes:
        id: Unique identifier for the preference record
        parent_id: ID of the parent user (unique constraint)
        child_id: ID of the child the preferences apply to
        preferred_language: Preferred language for communications (en/fr)
        report_frequency: How often to generate reports (daily/weekly)
        created_at: Timestamp when the preference was created
        updated_at: Timestamp when the preference was last updated
    """

    __tablename__ = "communication_preferences"

    id: Mapped[UUID] = mapped_column(
        UUID(as_uuid=True),
        primary_key=True,
        default=uuid4,
    )
    parent_id: Mapped[UUID] = mapped_column(
        UUID(as_uuid=True),
        nullable=False,
        unique=True,
    )
    child_id: Mapped[UUID] = mapped_column(
        UUID(as_uuid=True),
        nullable=False,
    )
    preferred_language: Mapped[str] = mapped_column(
        String(2),
        nullable=False,
        default="en",
    )
    report_frequency: Mapped[str] = mapped_column(
        String(20),
        nullable=False,
        default="daily",
    )
    created_at: Mapped[datetime] = mapped_column(
        DateTime,
        default=datetime.utcnow,
        nullable=False,
    )
    updated_at: Mapped[Optional[datetime]] = mapped_column(
        DateTime,
        default=datetime.utcnow,
        onupdate=datetime.utcnow,
        nullable=True,
    )

    # Table-level indexes for common query patterns
    __table_args__ = (
        Index("ix_comm_prefs_parent", "parent_id"),
        Index("ix_comm_prefs_child", "child_id"),
    )
