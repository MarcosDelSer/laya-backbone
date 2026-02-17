"""Development Profile SQLAlchemy models for LAYA AI Service.

Defines database models for Quebec-aligned developmental tracking across 6 domains:
1. Affective Development (emotional expression, self-regulation, attachment, self-confidence)
2. Social Development (peer interactions, turn-taking, empathy, group participation)
3. Language & Communication (receptive/expressive language, speech clarity, emergent literacy)
4. Cognitive Development (problem-solving, memory, attention, classification, number concept)
5. Physical - Gross Motor (balance, coordination, body awareness, outdoor skills)
6. Physical - Fine Motor (hand-eye coordination, pencil grip, manipulation, self-care)
"""

from datetime import date, datetime
from enum import Enum as PyEnum
from typing import Optional
from uuid import UUID, uuid4

from sqlalchemy import (
    Boolean,
    Date,
    DateTime,
    Enum,
    Float,
    ForeignKey,
    Integer,
    String,
    Text,
    func,
)
from sqlalchemy.dialects.postgresql import JSONB, UUID as PGUUID
from sqlalchemy.orm import Mapped, mapped_column, relationship

from app.models.base import Base


class DevelopmentalDomain(str, PyEnum):
    """Quebec-aligned developmental domains for early childhood education.

    Attributes:
        AFFECTIVE: Emotional expression, self-regulation, attachment, self-confidence
        SOCIAL: Peer interactions, turn-taking, empathy, group participation
        LANGUAGE: Receptive/expressive language, speech clarity, emergent literacy
        COGNITIVE: Problem-solving, memory, attention, classification, number concept
        GROSS_MOTOR: Balance, coordination, body awareness, outdoor skills
        FINE_MOTOR: Hand-eye coordination, pencil grip, manipulation, self-care
    """

    AFFECTIVE = "affective"
    SOCIAL = "social"
    LANGUAGE = "language"
    COGNITIVE = "cognitive"
    GROSS_MOTOR = "gross_motor"
    FINE_MOTOR = "fine_motor"


class SkillStatus(str, PyEnum):
    """Status levels for skill assessment tracking.

    Attributes:
        CAN: Child can perform the skill independently
        LEARNING: Child is learning/developing the skill
        NOT_YET: Child has not yet started developing the skill
        NA: Not applicable (age-inappropriate or not assessed)
    """

    CAN = "can"
    LEARNING = "learning"
    NOT_YET = "not_yet"
    NA = "na"


class DevelopmentProfile(Base):
    """SQLAlchemy model for child development profiles.

    Represents a comprehensive developmental profile for a child,
    tracking progress across all 6 Quebec developmental domains.

    Attributes:
        id: Unique identifier for the profile
        child_id: Unique identifier of the child
        educator_id: Unique identifier of the primary educator
        birth_date: Child's date of birth for age-appropriate expectations
        notes: General notes about the child's development
        is_active: Whether the profile is currently active
        created_at: Timestamp when the profile was created
        updated_at: Timestamp when the profile was last updated
    """

    __tablename__ = "development_profiles"

    id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        primary_key=True,
        default=uuid4,
    )
    child_id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        nullable=False,
        unique=True,
        index=True,
    )
    educator_id: Mapped[Optional[UUID]] = mapped_column(
        PGUUID(as_uuid=True),
        nullable=True,
        index=True,
    )
    birth_date: Mapped[Optional[date]] = mapped_column(
        Date,
        nullable=True,
    )
    notes: Mapped[Optional[str]] = mapped_column(
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
    skill_assessments: Mapped[list["SkillAssessment"]] = relationship(
        "SkillAssessment",
        back_populates="profile",
        cascade="all, delete-orphan",
    )
    observations: Mapped[list["Observation"]] = relationship(
        "Observation",
        back_populates="profile",
        cascade="all, delete-orphan",
    )
    monthly_snapshots: Mapped[list["MonthlySnapshot"]] = relationship(
        "MonthlySnapshot",
        back_populates="profile",
        cascade="all, delete-orphan",
    )

    def __repr__(self) -> str:
        """Return string representation of the DevelopmentProfile."""
        return f"<DevelopmentProfile(id={self.id}, child_id={self.child_id})>"


class SkillAssessment(Base):
    """SQLAlchemy model for skill assessment tracking.

    Records the assessment status of individual skills within developmental domains.

    Attributes:
        id: Unique identifier for the assessment
        profile_id: Foreign key to the development profile
        domain: Developmental domain this skill belongs to
        skill_name: Name of the specific skill being assessed
        skill_name_fr: French name of the skill (for bilingual support)
        status: Current assessment status (can/learning/not_yet/na)
        assessed_at: When this skill was last assessed
        assessed_by_id: UUID of the user who made the assessment
        evidence: Observable evidence supporting the assessment
        created_at: Timestamp when the assessment was created
        updated_at: Timestamp when the assessment was last updated
    """

    __tablename__ = "skill_assessments"

    id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        primary_key=True,
        default=uuid4,
    )
    profile_id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        ForeignKey("development_profiles.id", ondelete="CASCADE"),
        nullable=False,
        index=True,
    )
    domain: Mapped[DevelopmentalDomain] = mapped_column(
        Enum(DevelopmentalDomain, name="developmental_domain_enum", create_constraint=True),
        nullable=False,
        index=True,
    )
    skill_name: Mapped[str] = mapped_column(
        String(200),
        nullable=False,
    )
    skill_name_fr: Mapped[Optional[str]] = mapped_column(
        String(200),
        nullable=True,
    )
    status: Mapped[SkillStatus] = mapped_column(
        Enum(SkillStatus, name="skill_status_enum", create_constraint=True),
        nullable=False,
        default=SkillStatus.NOT_YET,
        index=True,
    )
    assessed_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True),
        nullable=False,
        server_default=func.now(),
    )
    assessed_by_id: Mapped[Optional[UUID]] = mapped_column(
        PGUUID(as_uuid=True),
        nullable=True,
    )
    evidence: Mapped[Optional[str]] = mapped_column(
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
    profile: Mapped["DevelopmentProfile"] = relationship(
        "DevelopmentProfile",
        back_populates="skill_assessments",
    )

    def __repr__(self) -> str:
        """Return string representation of the SkillAssessment."""
        return (
            f"<SkillAssessment(id={self.id}, domain={self.domain.value}, "
            f"skill='{self.skill_name}', status={self.status.value})>"
        )


class Observation(Base):
    """SQLAlchemy model for observable behavior documentation.

    Records observations of child behaviors with dates and evidence,
    supporting developmental tracking across domains.

    Attributes:
        id: Unique identifier for the observation
        profile_id: Foreign key to the development profile
        domain: Primary developmental domain for this observation
        observed_at: Date and time when the behavior was observed
        observer_id: UUID of the person who made the observation
        observer_type: Type of observer (educator, parent, specialist)
        behavior_description: Detailed description of the observed behavior
        context: Context in which the behavior was observed
        is_milestone: Whether this represents a developmental milestone
        is_concern: Whether this observation raises developmental concerns
        attachments: JSON array of attachment references (photos, videos)
        created_at: Timestamp when the observation was created
        updated_at: Timestamp when the observation was last updated
    """

    __tablename__ = "observations"

    id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        primary_key=True,
        default=uuid4,
    )
    profile_id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        ForeignKey("development_profiles.id", ondelete="CASCADE"),
        nullable=False,
        index=True,
    )
    domain: Mapped[DevelopmentalDomain] = mapped_column(
        Enum(DevelopmentalDomain, name="developmental_domain_enum", create_constraint=True),
        nullable=False,
        index=True,
    )
    observed_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True),
        nullable=False,
        server_default=func.now(),
    )
    observer_id: Mapped[Optional[UUID]] = mapped_column(
        PGUUID(as_uuid=True),
        nullable=True,
        index=True,
    )
    observer_type: Mapped[str] = mapped_column(
        String(50),
        nullable=False,
        default="educator",
    )
    behavior_description: Mapped[str] = mapped_column(
        Text,
        nullable=False,
    )
    context: Mapped[Optional[str]] = mapped_column(
        Text,
        nullable=True,
    )
    is_milestone: Mapped[bool] = mapped_column(
        Boolean,
        nullable=False,
        default=False,
    )
    is_concern: Mapped[bool] = mapped_column(
        Boolean,
        nullable=False,
        default=False,
        index=True,
    )
    attachments: Mapped[Optional[dict]] = mapped_column(
        JSONB,
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
    profile: Mapped["DevelopmentProfile"] = relationship(
        "DevelopmentProfile",
        back_populates="observations",
    )

    def __repr__(self) -> str:
        """Return string representation of the Observation."""
        return (
            f"<Observation(id={self.id}, domain={self.domain.value}, "
            f"observed_at={self.observed_at})>"
        )


class MonthlySnapshot(Base):
    """SQLAlchemy model for monthly developmental snapshots.

    Captures a monthly summary of developmental progress across all domains,
    supporting growth trajectory analysis and reporting.

    Attributes:
        id: Unique identifier for the snapshot
        profile_id: Foreign key to the development profile
        snapshot_month: The month this snapshot represents (first day of month)
        age_months: Child's age in months at time of snapshot
        domain_summaries: JSON object with summary per domain
        overall_progress: Overall progress indicator (on_track, needs_support, excelling)
        strengths: List of identified strengths
        growth_areas: List of areas needing growth/support
        recommendations: Recommendations for next month
        generated_by_id: UUID of the user who generated/approved this snapshot
        is_parent_shared: Whether this snapshot has been shared with parents
        created_at: Timestamp when the snapshot was created
        updated_at: Timestamp when the snapshot was last updated
    """

    __tablename__ = "monthly_snapshots"

    id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        primary_key=True,
        default=uuid4,
    )
    profile_id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        ForeignKey("development_profiles.id", ondelete="CASCADE"),
        nullable=False,
        index=True,
    )
    snapshot_month: Mapped[date] = mapped_column(
        Date,
        nullable=False,
        index=True,
    )
    age_months: Mapped[Optional[int]] = mapped_column(
        Integer,
        nullable=True,
    )
    domain_summaries: Mapped[Optional[dict]] = mapped_column(
        JSONB,
        nullable=True,
    )
    overall_progress: Mapped[str] = mapped_column(
        String(50),
        nullable=False,
        default="on_track",
    )
    strengths: Mapped[Optional[dict]] = mapped_column(
        JSONB,
        nullable=True,
    )
    growth_areas: Mapped[Optional[dict]] = mapped_column(
        JSONB,
        nullable=True,
    )
    recommendations: Mapped[Optional[str]] = mapped_column(
        Text,
        nullable=True,
    )
    generated_by_id: Mapped[Optional[UUID]] = mapped_column(
        PGUUID(as_uuid=True),
        nullable=True,
    )
    is_parent_shared: Mapped[bool] = mapped_column(
        Boolean,
        nullable=False,
        default=False,
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
    profile: Mapped["DevelopmentProfile"] = relationship(
        "DevelopmentProfile",
        back_populates="monthly_snapshots",
    )

    def __repr__(self) -> str:
        """Return string representation of the MonthlySnapshot."""
        return (
            f"<MonthlySnapshot(id={self.id}, profile_id={self.profile_id}, "
            f"month={self.snapshot_month}, progress={self.overall_progress})>"
        )
