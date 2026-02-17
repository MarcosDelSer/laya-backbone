"""SQLAlchemy models for coaching domain.

Defines database models for special needs coaching sessions, recommendations,
and evidence sources. These models support the RAG-based coaching system that
provides personalized, evidence-based guidance for educators and parents.
"""

from datetime import datetime
from typing import TYPE_CHECKING, Optional
from uuid import UUID, uuid4

from sqlalchemy import DateTime, ForeignKey, Index, Integer, String, Text
from sqlalchemy.dialects.postgresql import ARRAY, UUID as PGUUID
from sqlalchemy.orm import Mapped, mapped_column, relationship

from app.models.base import Base

if TYPE_CHECKING:
    pass


class CoachingSession(Base):
    """Track coaching interactions with users.

    Stores the context of each coaching guidance request, including the child's
    special needs, the situation described, and links to generated recommendations.

    Attributes:
        id: Unique identifier for the session
        child_id: ID of the child the coaching is for
        user_id: ID of the user requesting coaching guidance
        question: The coaching question or situation description
        context: Additional context provided for the coaching request
        special_need_types: List of special need types for the child
        category: Optional coaching category filter applied
        created_at: Timestamp when the session was created
        updated_at: Timestamp when the session was last updated
        recommendations: List of recommendations generated for this session
    """

    __tablename__ = "coaching_sessions"

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
    user_id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        nullable=False,
        index=True,
    )
    question: Mapped[str] = mapped_column(
        Text,
        nullable=False,
    )
    context: Mapped[Optional[str]] = mapped_column(
        Text,
        nullable=True,
    )
    special_need_types: Mapped[Optional[list[str]]] = mapped_column(
        ARRAY(String(50)),
        nullable=True,
    )
    category: Mapped[Optional[str]] = mapped_column(
        String(50),
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

    # Relationships
    recommendations: Mapped[list["CoachingRecommendation"]] = relationship(
        "CoachingRecommendation",
        back_populates="session",
        cascade="all, delete-orphan",
    )

    # Table-level indexes for common query patterns
    __table_args__ = (
        Index("ix_coaching_sessions_child_created", "child_id", "created_at"),
        Index("ix_coaching_sessions_user_created", "user_id", "created_at"),
    )


class CoachingRecommendation(Base):
    """Store individual coaching recommendations.

    Each recommendation is part of a coaching session and includes guidance
    content, priority, relevance score, and links to evidence sources that
    support the recommendation.

    Attributes:
        id: Unique identifier for the recommendation
        session_id: ID of the parent coaching session
        title: Title of the recommendation
        content: Detailed recommendation content
        category: Coaching category of this recommendation
        priority: Priority level (low, medium, high, urgent)
        relevance_score: Score indicating relevance to the query (0.0-1.0)
        target_audience: Intended audience (educator, parent, etc.)
        prerequisites: Prerequisites or background needed
        created_at: Timestamp when the recommendation was created
        session: Reference to the parent coaching session
        evidence_sources: List of evidence sources supporting this recommendation
    """

    __tablename__ = "coaching_recommendations"

    id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        primary_key=True,
        default=uuid4,
    )
    session_id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        ForeignKey("coaching_sessions.id", ondelete="CASCADE"),
        nullable=False,
        index=True,
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
    priority: Mapped[str] = mapped_column(
        String(20),
        nullable=False,
        default="medium",
    )
    relevance_score: Mapped[float] = mapped_column(
        nullable=False,
        default=0.0,
    )
    target_audience: Mapped[str] = mapped_column(
        String(100),
        nullable=False,
        default="educator",
    )
    prerequisites: Mapped[Optional[str]] = mapped_column(
        Text,
        nullable=True,
    )
    created_at: Mapped[datetime] = mapped_column(
        DateTime,
        default=datetime.utcnow,
        nullable=False,
    )

    # Relationships
    session: Mapped["CoachingSession"] = relationship(
        "CoachingSession",
        back_populates="recommendations",
    )
    evidence_sources: Mapped[list["EvidenceSource"]] = relationship(
        "EvidenceSource",
        back_populates="recommendation",
        cascade="all, delete-orphan",
    )

    # Table-level indexes
    __table_args__ = (
        Index("ix_coaching_recommendations_category", "category"),
        Index("ix_coaching_recommendations_priority", "priority"),
    )


class EvidenceSource(Base):
    """Store evidence sources for recommendations.

    Each evidence source provides a citation for a coaching recommendation,
    ensuring that all guidance is backed by peer-reviewed or official sources.
    This enforces the no-hallucination requirement of the coaching system.

    Attributes:
        id: Unique identifier for the evidence source
        recommendation_id: ID of the parent recommendation
        source_type: Type of source (peer_reviewed, official_guide, clinical, research_study)
        title: Title of the source document
        authors: Authors of the source (comma-separated or JSON)
        publication: Publication or journal name
        year: Publication year
        doi: Digital Object Identifier
        url: URL to access the source
        isbn: ISBN for book sources
        accessed_at: When the source was last accessed
        created_at: Timestamp when the record was created
        recommendation: Reference to the parent recommendation
    """

    __tablename__ = "evidence_sources"

    id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        primary_key=True,
        default=uuid4,
    )
    recommendation_id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        ForeignKey("coaching_recommendations.id", ondelete="CASCADE"),
        nullable=False,
        index=True,
    )
    source_type: Mapped[str] = mapped_column(
        String(50),
        nullable=False,
    )
    title: Mapped[str] = mapped_column(
        String(500),
        nullable=False,
    )
    authors: Mapped[Optional[str]] = mapped_column(
        Text,
        nullable=True,
    )
    publication: Mapped[Optional[str]] = mapped_column(
        String(200),
        nullable=True,
    )
    year: Mapped[Optional[int]] = mapped_column(
        Integer,
        nullable=True,
    )
    doi: Mapped[Optional[str]] = mapped_column(
        String(100),
        nullable=True,
    )
    url: Mapped[Optional[str]] = mapped_column(
        String(500),
        nullable=True,
    )
    isbn: Mapped[Optional[str]] = mapped_column(
        String(20),
        nullable=True,
    )
    accessed_at: Mapped[Optional[datetime]] = mapped_column(
        DateTime,
        default=datetime.utcnow,
        nullable=True,
    )
    created_at: Mapped[datetime] = mapped_column(
        DateTime,
        default=datetime.utcnow,
        nullable=False,
    )

    # Relationships
    recommendation: Mapped["CoachingRecommendation"] = relationship(
        "CoachingRecommendation",
        back_populates="evidence_sources",
    )

    # Table-level indexes
    __table_args__ = (
        Index("ix_evidence_sources_source_type", "source_type"),
        Index("ix_evidence_sources_year", "year"),
    )
