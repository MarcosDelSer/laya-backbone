"""SQLAlchemy models for message quality domain.

Defines database models for message analysis history, quality templates,
and training examples. These models support the AI Message Quality Coach
that enforces Quebec 'Bonne Message' communication standards for positive
parent-educator communication in daycare settings.
"""

from datetime import datetime
from typing import Optional
from uuid import uuid4

from sqlalchemy import Boolean, DateTime, Float, Index, Integer, String, Text
from sqlalchemy.dialects.postgresql import ARRAY, UUID
from sqlalchemy.orm import Mapped, mapped_column

from app.models.base import Base


class MessageAnalysis(Base):
    """Store message quality analysis history.

    Stores the results of AI-powered message quality analysis, including
    detected issues, quality scores, and rewrite suggestions. This enables
    tracking educator improvement over time and auditing message quality.

    Attributes:
        id: Unique identifier for the analysis
        user_id: ID of the user who submitted the message for analysis
        child_id: Optional ID of the child the message is about
        message_text: The original message text that was analyzed
        language: Language of the message (en/fr)
        context: Context type of the message (daily_report, incident_report, etc.)
        quality_score: Overall quality score (0-100)
        is_acceptable: Whether the message meets quality standards
        issues_detected: JSON array of detected quality issues
        has_positive_opening: Whether message has positive opening
        has_factual_basis: Whether message is factual
        has_solution_focus: Whether message is solution-oriented
        rewrite_suggested: Whether a rewrite was suggested
        rewrite_accepted: Whether the suggested rewrite was accepted
        analysis_notes: Additional notes from the analysis
        created_at: Timestamp when the analysis was created
        updated_at: Timestamp when the analysis was last updated
    """

    __tablename__ = "message_analyses"

    id: Mapped[UUID] = mapped_column(
        UUID(as_uuid=True),
        primary_key=True,
        default=uuid4,
    )
    user_id: Mapped[UUID] = mapped_column(
        UUID(as_uuid=True),
        nullable=False,
        index=True,
    )
    child_id: Mapped[Optional[UUID]] = mapped_column(
        UUID(as_uuid=True),
        nullable=True,
        index=True,
    )
    message_text: Mapped[str] = mapped_column(
        Text,
        nullable=False,
    )
    language: Mapped[str] = mapped_column(
        String(2),
        nullable=False,
        default="en",
    )
    context: Mapped[str] = mapped_column(
        String(50),
        nullable=False,
        default="general_update",
    )
    quality_score: Mapped[int] = mapped_column(
        Integer,
        nullable=False,
        default=0,
    )
    is_acceptable: Mapped[bool] = mapped_column(
        Boolean,
        nullable=False,
        default=False,
    )
    issues_detected: Mapped[Optional[list[str]]] = mapped_column(
        ARRAY(String(50)),
        nullable=True,
    )
    has_positive_opening: Mapped[bool] = mapped_column(
        Boolean,
        nullable=False,
        default=False,
    )
    has_factual_basis: Mapped[bool] = mapped_column(
        Boolean,
        nullable=False,
        default=True,
    )
    has_solution_focus: Mapped[bool] = mapped_column(
        Boolean,
        nullable=False,
        default=False,
    )
    rewrite_suggested: Mapped[bool] = mapped_column(
        Boolean,
        nullable=False,
        default=False,
    )
    rewrite_accepted: Mapped[Optional[bool]] = mapped_column(
        Boolean,
        nullable=True,
    )
    analysis_notes: Mapped[Optional[str]] = mapped_column(
        Text,
        nullable=True,
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
        Index("ix_message_analyses_user_created", "user_id", "created_at"),
        Index("ix_message_analyses_quality_score", "quality_score"),
        Index("ix_message_analyses_language", "language"),
    )


class MessageTemplate(Base):
    """Store pre-approved message templates.

    Stores templates that educators can use as starting points for
    positive parent communication. Templates follow Quebec 'Bonne Message'
    standards and are available in both English and French.

    Attributes:
        id: Unique identifier for the template
        title: Title of the template
        content: Template content with optional placeholders
        category: Category of the template (positive_opening, solution_oriented, etc.)
        language: Language of the template (en/fr)
        description: Description of when to use this template
        is_system: Whether this is a system-provided template
        is_active: Whether the template is currently active
        usage_count: Number of times this template has been used
        created_by: ID of the user who created the template (null for system)
        created_at: Timestamp when the template was created
        updated_at: Timestamp when the template was last updated
    """

    __tablename__ = "message_templates"

    id: Mapped[UUID] = mapped_column(
        UUID(as_uuid=True),
        primary_key=True,
        default=uuid4,
    )
    title: Mapped[str] = mapped_column(
        String(200),
        nullable=False,
    )
    content: Mapped[str] = mapped_column(
        Text,
        nullable=False,
    )
    category: Mapped[str] = mapped_column(
        String(50),
        nullable=False,
    )
    language: Mapped[str] = mapped_column(
        String(2),
        nullable=False,
        default="en",
    )
    description: Mapped[Optional[str]] = mapped_column(
        Text,
        nullable=True,
    )
    is_system: Mapped[bool] = mapped_column(
        Boolean,
        nullable=False,
        default=False,
    )
    is_active: Mapped[bool] = mapped_column(
        Boolean,
        nullable=False,
        default=True,
    )
    usage_count: Mapped[int] = mapped_column(
        Integer,
        nullable=False,
        default=0,
    )
    created_by: Mapped[Optional[UUID]] = mapped_column(
        UUID(as_uuid=True),
        nullable=True,
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
        Index("ix_message_templates_category", "category"),
        Index("ix_message_templates_language", "language"),
        Index("ix_message_templates_category_language", "category", "language"),
        Index("ix_message_templates_is_active", "is_active"),
    )


class TrainingExample(Base):
    """Store training examples for educator learning.

    Stores before/after examples that help educators learn to write
    positive messages following Quebec 'Bonne Message' standards.
    Examples demonstrate common issues and their corrections.

    Attributes:
        id: Unique identifier for the training example
        original_message: The original message with quality issues
        improved_message: The improved version of the message
        issues_demonstrated: List of quality issues demonstrated
        explanation: Explanation of the improvements made
        language: Language of the example (en/fr)
        difficulty_level: Difficulty level for training purposes
        is_active: Whether the example is currently active
        view_count: Number of times this example has been viewed
        helpfulness_score: Average helpfulness rating (0-5)
        created_by: ID of the user who created the example (null for system)
        created_at: Timestamp when the example was created
        updated_at: Timestamp when the example was last updated
    """

    __tablename__ = "training_examples"

    id: Mapped[UUID] = mapped_column(
        UUID(as_uuid=True),
        primary_key=True,
        default=uuid4,
    )
    original_message: Mapped[str] = mapped_column(
        Text,
        nullable=False,
    )
    improved_message: Mapped[str] = mapped_column(
        Text,
        nullable=False,
    )
    issues_demonstrated: Mapped[list[str]] = mapped_column(
        ARRAY(String(50)),
        nullable=False,
    )
    explanation: Mapped[str] = mapped_column(
        Text,
        nullable=False,
    )
    language: Mapped[str] = mapped_column(
        String(2),
        nullable=False,
        default="en",
    )
    difficulty_level: Mapped[str] = mapped_column(
        String(20),
        nullable=False,
        default="beginner",
    )
    is_active: Mapped[bool] = mapped_column(
        Boolean,
        nullable=False,
        default=True,
    )
    view_count: Mapped[int] = mapped_column(
        Integer,
        nullable=False,
        default=0,
    )
    helpfulness_score: Mapped[Optional[float]] = mapped_column(
        Float,
        nullable=True,
    )
    created_by: Mapped[Optional[UUID]] = mapped_column(
        UUID(as_uuid=True),
        nullable=True,
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
        Index("ix_training_examples_language", "language"),
        Index("ix_training_examples_difficulty", "difficulty_level"),
        Index("ix_training_examples_language_difficulty", "language", "difficulty_level"),
        Index("ix_training_examples_is_active", "is_active"),
    )
