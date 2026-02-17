"""SQLAlchemy models for intervention plan domain.

Defines database models for special needs intervention plans with 8-part structure,
SMART goals, versioning, and progress tracking. These models support the comprehensive
intervention planning system for children with special needs.

The 8-part structure includes:
1. Identification & History (child info) - stored in InterventionPlan
2. Strengths - InterventionStrength
3. Needs - InterventionNeed
4. SMART Goals - InterventionGoal
5. Strategies - InterventionStrategy
6. Monitoring - InterventionMonitoring
7. Parent Involvement - InterventionParentInvolvement
8. Consultation - InterventionConsultation
"""

from datetime import datetime, date
from typing import TYPE_CHECKING, Optional
from uuid import UUID, uuid4

from sqlalchemy import Boolean, Date, DateTime, Float, ForeignKey, Index, Integer, String, Text
from sqlalchemy.dialects.postgresql import ARRAY, JSONB, UUID as PGUUID
from sqlalchemy.orm import Mapped, mapped_column, relationship

from app.models.base import Base

if TYPE_CHECKING:
    pass


class InterventionPlan(Base):
    """Main intervention plan with 8-part structure and versioning.

    Stores the comprehensive intervention plan for a child with special needs,
    including identification, history, and links to all related components.
    Supports versioning for tracking plan revisions over time.

    Attributes:
        id: Unique identifier for the plan
        child_id: ID of the child the plan is for
        created_by: ID of the user who created the plan
        title: Title of the intervention plan
        status: Plan status (draft, active, under_review, completed, archived)
        version: Current version number (starts at 1)
        parent_version_id: ID of the previous version (for version history)

        Part 1 - Identification & History:
        child_name: Full name of the child
        date_of_birth: Child's date of birth
        diagnosis: Primary diagnosis or special need types
        medical_history: Relevant medical history
        educational_history: Relevant educational background
        family_context: Family situation and context

        Review and scheduling:
        review_schedule: How often the plan should be reviewed (monthly, quarterly, etc.)
        next_review_date: When the next review is due

        Parent signature:
        parent_signed: Whether parent has signed the plan
        parent_signature_date: When parent signed
        parent_signature_data: Signature data (base64 or JSON)

        Timestamps:
        effective_date: When the plan becomes effective
        end_date: When the plan ends (if applicable)
        created_at: Timestamp when the plan was created
        updated_at: Timestamp when the plan was last updated

        Relationships:
        strengths: List of identified strengths (Part 2)
        needs: List of identified needs (Part 3)
        goals: List of SMART goals (Part 4)
        strategies: List of intervention strategies (Part 5)
        monitoring: List of monitoring approaches (Part 6)
        parent_involvements: List of parent involvement activities (Part 7)
        consultations: List of consultations (Part 8)
        progress_records: List of progress tracking records
        versions: List of version history records
    """

    __tablename__ = "intervention_plans"

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
    created_by: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        nullable=False,
        index=True,
    )
    title: Mapped[str] = mapped_column(
        String(200),
        nullable=False,
    )
    status: Mapped[str] = mapped_column(
        String(20),
        nullable=False,
        default="draft",
    )
    version: Mapped[int] = mapped_column(
        Integer,
        nullable=False,
        default=1,
    )
    parent_version_id: Mapped[Optional[UUID]] = mapped_column(
        PGUUID(as_uuid=True),
        ForeignKey("intervention_plans.id", ondelete="SET NULL"),
        nullable=True,
    )

    # Part 1 - Identification & History
    child_name: Mapped[str] = mapped_column(
        String(200),
        nullable=False,
    )
    date_of_birth: Mapped[Optional[date]] = mapped_column(
        Date,
        nullable=True,
    )
    diagnosis: Mapped[Optional[list[str]]] = mapped_column(
        ARRAY(String(100)),
        nullable=True,
    )
    medical_history: Mapped[Optional[str]] = mapped_column(
        Text,
        nullable=True,
    )
    educational_history: Mapped[Optional[str]] = mapped_column(
        Text,
        nullable=True,
    )
    family_context: Mapped[Optional[str]] = mapped_column(
        Text,
        nullable=True,
    )

    # Review and scheduling
    review_schedule: Mapped[str] = mapped_column(
        String(20),
        nullable=False,
        default="quarterly",
    )
    next_review_date: Mapped[Optional[date]] = mapped_column(
        Date,
        nullable=True,
    )

    # Parent signature
    parent_signed: Mapped[bool] = mapped_column(
        Boolean,
        nullable=False,
        default=False,
    )
    parent_signature_date: Mapped[Optional[datetime]] = mapped_column(
        DateTime,
        nullable=True,
    )
    parent_signature_data: Mapped[Optional[str]] = mapped_column(
        Text,
        nullable=True,
    )

    # Dates
    effective_date: Mapped[Optional[date]] = mapped_column(
        Date,
        nullable=True,
    )
    end_date: Mapped[Optional[date]] = mapped_column(
        Date,
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

    # Relationships for 8-part structure
    strengths: Mapped[list["InterventionStrength"]] = relationship(
        "InterventionStrength",
        back_populates="plan",
        cascade="all, delete-orphan",
    )
    needs: Mapped[list["InterventionNeed"]] = relationship(
        "InterventionNeed",
        back_populates="plan",
        cascade="all, delete-orphan",
    )
    goals: Mapped[list["InterventionGoal"]] = relationship(
        "InterventionGoal",
        back_populates="plan",
        cascade="all, delete-orphan",
    )
    strategies: Mapped[list["InterventionStrategy"]] = relationship(
        "InterventionStrategy",
        back_populates="plan",
        cascade="all, delete-orphan",
    )
    monitoring: Mapped[list["InterventionMonitoring"]] = relationship(
        "InterventionMonitoring",
        back_populates="plan",
        cascade="all, delete-orphan",
    )
    parent_involvements: Mapped[list["InterventionParentInvolvement"]] = relationship(
        "InterventionParentInvolvement",
        back_populates="plan",
        cascade="all, delete-orphan",
    )
    consultations: Mapped[list["InterventionConsultation"]] = relationship(
        "InterventionConsultation",
        back_populates="plan",
        cascade="all, delete-orphan",
    )
    progress_records: Mapped[list["InterventionProgress"]] = relationship(
        "InterventionProgress",
        back_populates="plan",
        cascade="all, delete-orphan",
    )
    versions: Mapped[list["InterventionVersion"]] = relationship(
        "InterventionVersion",
        back_populates="plan",
        cascade="all, delete-orphan",
    )

    # Table-level indexes for common query patterns
    __table_args__ = (
        Index("ix_intervention_plans_child_status", "child_id", "status"),
        Index("ix_intervention_plans_review_date", "next_review_date"),
        Index("ix_intervention_plans_created_by_created", "created_by", "created_at"),
    )


class InterventionStrength(Base):
    """Part 2 - Identified strengths of the child.

    Stores the strengths identified for a child as part of the intervention plan,
    which inform the development of goals and strategies.

    Attributes:
        id: Unique identifier for the strength
        plan_id: ID of the parent intervention plan
        category: Category of strength (cognitive, social, physical, emotional, etc.)
        description: Detailed description of the strength
        examples: Examples demonstrating this strength
        order: Display order for organizing strengths
        created_at: Timestamp when the record was created
        plan: Reference to the parent intervention plan
    """

    __tablename__ = "intervention_strengths"

    id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        primary_key=True,
        default=uuid4,
    )
    plan_id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        ForeignKey("intervention_plans.id", ondelete="CASCADE"),
        nullable=False,
        index=True,
    )
    category: Mapped[str] = mapped_column(
        String(50),
        nullable=False,
    )
    description: Mapped[str] = mapped_column(
        Text,
        nullable=False,
    )
    examples: Mapped[Optional[str]] = mapped_column(
        Text,
        nullable=True,
    )
    order: Mapped[int] = mapped_column(
        Integer,
        nullable=False,
        default=0,
    )
    created_at: Mapped[datetime] = mapped_column(
        DateTime,
        default=datetime.utcnow,
        nullable=False,
    )

    # Relationships
    plan: Mapped["InterventionPlan"] = relationship(
        "InterventionPlan",
        back_populates="strengths",
    )

    # Table-level indexes
    __table_args__ = (
        Index("ix_intervention_strengths_category", "category"),
    )


class InterventionNeed(Base):
    """Part 3 - Identified needs of the child.

    Stores the needs identified for a child that the intervention plan
    aims to address through goals and strategies.

    Attributes:
        id: Unique identifier for the need
        plan_id: ID of the parent intervention plan
        category: Category of need (communication, behavior, academic, sensory, etc.)
        description: Detailed description of the need
        priority: Priority level (low, medium, high, critical)
        baseline: Baseline assessment of current ability
        order: Display order for organizing needs
        created_at: Timestamp when the record was created
        plan: Reference to the parent intervention plan
    """

    __tablename__ = "intervention_needs"

    id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        primary_key=True,
        default=uuid4,
    )
    plan_id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        ForeignKey("intervention_plans.id", ondelete="CASCADE"),
        nullable=False,
        index=True,
    )
    category: Mapped[str] = mapped_column(
        String(50),
        nullable=False,
    )
    description: Mapped[str] = mapped_column(
        Text,
        nullable=False,
    )
    priority: Mapped[str] = mapped_column(
        String(20),
        nullable=False,
        default="medium",
    )
    baseline: Mapped[Optional[str]] = mapped_column(
        Text,
        nullable=True,
    )
    order: Mapped[int] = mapped_column(
        Integer,
        nullable=False,
        default=0,
    )
    created_at: Mapped[datetime] = mapped_column(
        DateTime,
        default=datetime.utcnow,
        nullable=False,
    )

    # Relationships
    plan: Mapped["InterventionPlan"] = relationship(
        "InterventionPlan",
        back_populates="needs",
    )

    # Table-level indexes
    __table_args__ = (
        Index("ix_intervention_needs_category", "category"),
        Index("ix_intervention_needs_priority", "priority"),
    )


class InterventionGoal(Base):
    """Part 4 - SMART goals for the intervention plan.

    Stores goals that follow the SMART framework:
    - Specific: Clear and well-defined
    - Measurable: Quantifiable outcomes
    - Achievable: Realistic and attainable
    - Relevant: Aligned with child's needs
    - Time-bound: Has a target date

    Attributes:
        id: Unique identifier for the goal
        plan_id: ID of the parent intervention plan
        need_id: Optional link to the specific need this goal addresses
        title: Short title of the goal
        description: Full description of the goal (Specific)
        measurement_criteria: How progress will be measured (Measurable)
        measurement_baseline: Baseline measurement value
        measurement_target: Target measurement value
        achievability_notes: Notes on why this goal is achievable (Achievable)
        relevance_notes: Notes on why this goal is relevant (Relevant)
        target_date: Target date for achieving the goal (Time-bound)
        status: Goal status (not_started, in_progress, achieved, modified, discontinued)
        progress_percentage: Current progress as percentage (0-100)
        order: Display order for organizing goals
        created_at: Timestamp when the record was created
        updated_at: Timestamp when the record was last updated
        plan: Reference to the parent intervention plan
    """

    __tablename__ = "intervention_goals"

    id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        primary_key=True,
        default=uuid4,
    )
    plan_id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        ForeignKey("intervention_plans.id", ondelete="CASCADE"),
        nullable=False,
        index=True,
    )
    need_id: Mapped[Optional[UUID]] = mapped_column(
        PGUUID(as_uuid=True),
        ForeignKey("intervention_needs.id", ondelete="SET NULL"),
        nullable=True,
    )
    title: Mapped[str] = mapped_column(
        String(200),
        nullable=False,
    )
    # SMART components
    description: Mapped[str] = mapped_column(
        Text,
        nullable=False,
    )
    measurement_criteria: Mapped[str] = mapped_column(
        Text,
        nullable=False,
    )
    measurement_baseline: Mapped[Optional[str]] = mapped_column(
        String(100),
        nullable=True,
    )
    measurement_target: Mapped[Optional[str]] = mapped_column(
        String(100),
        nullable=True,
    )
    achievability_notes: Mapped[Optional[str]] = mapped_column(
        Text,
        nullable=True,
    )
    relevance_notes: Mapped[Optional[str]] = mapped_column(
        Text,
        nullable=True,
    )
    target_date: Mapped[Optional[date]] = mapped_column(
        Date,
        nullable=True,
    )
    # Status and progress
    status: Mapped[str] = mapped_column(
        String(20),
        nullable=False,
        default="not_started",
    )
    progress_percentage: Mapped[float] = mapped_column(
        Float,
        nullable=False,
        default=0.0,
    )
    order: Mapped[int] = mapped_column(
        Integer,
        nullable=False,
        default=0,
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
    plan: Mapped["InterventionPlan"] = relationship(
        "InterventionPlan",
        back_populates="goals",
    )

    # Table-level indexes
    __table_args__ = (
        Index("ix_intervention_goals_status", "status"),
        Index("ix_intervention_goals_target_date", "target_date"),
    )


class InterventionStrategy(Base):
    """Part 5 - Intervention strategies.

    Stores the specific strategies and interventions that will be used
    to help the child achieve their goals.

    Attributes:
        id: Unique identifier for the strategy
        plan_id: ID of the parent intervention plan
        goal_id: Optional link to the specific goal this strategy supports
        title: Title of the strategy
        description: Detailed description of the strategy
        responsible_party: Who is responsible for implementing (educator, parent, therapist)
        frequency: How often the strategy should be applied
        materials_needed: Materials or resources needed
        accommodations: Any accommodations required
        order: Display order for organizing strategies
        created_at: Timestamp when the record was created
        plan: Reference to the parent intervention plan
    """

    __tablename__ = "intervention_strategies"

    id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        primary_key=True,
        default=uuid4,
    )
    plan_id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        ForeignKey("intervention_plans.id", ondelete="CASCADE"),
        nullable=False,
        index=True,
    )
    goal_id: Mapped[Optional[UUID]] = mapped_column(
        PGUUID(as_uuid=True),
        ForeignKey("intervention_goals.id", ondelete="SET NULL"),
        nullable=True,
    )
    title: Mapped[str] = mapped_column(
        String(200),
        nullable=False,
    )
    description: Mapped[str] = mapped_column(
        Text,
        nullable=False,
    )
    responsible_party: Mapped[str] = mapped_column(
        String(50),
        nullable=False,
        default="educator",
    )
    frequency: Mapped[Optional[str]] = mapped_column(
        String(100),
        nullable=True,
    )
    materials_needed: Mapped[Optional[str]] = mapped_column(
        Text,
        nullable=True,
    )
    accommodations: Mapped[Optional[str]] = mapped_column(
        Text,
        nullable=True,
    )
    order: Mapped[int] = mapped_column(
        Integer,
        nullable=False,
        default=0,
    )
    created_at: Mapped[datetime] = mapped_column(
        DateTime,
        default=datetime.utcnow,
        nullable=False,
    )

    # Relationships
    plan: Mapped["InterventionPlan"] = relationship(
        "InterventionPlan",
        back_populates="strategies",
    )

    # Table-level indexes
    __table_args__ = (
        Index("ix_intervention_strategies_responsible", "responsible_party"),
    )


class InterventionMonitoring(Base):
    """Part 6 - Monitoring approaches.

    Stores how progress toward goals will be monitored and documented.

    Attributes:
        id: Unique identifier for the monitoring approach
        plan_id: ID of the parent intervention plan
        goal_id: Optional link to the specific goal being monitored
        method: Method of monitoring (observation, assessment, data_collection, etc.)
        description: Detailed description of the monitoring approach
        frequency: How often monitoring occurs
        responsible_party: Who is responsible for monitoring
        data_collection_tools: Tools or forms used for data collection
        success_indicators: What indicates successful progress
        order: Display order for organizing monitoring approaches
        created_at: Timestamp when the record was created
        plan: Reference to the parent intervention plan
    """

    __tablename__ = "intervention_monitoring"

    id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        primary_key=True,
        default=uuid4,
    )
    plan_id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        ForeignKey("intervention_plans.id", ondelete="CASCADE"),
        nullable=False,
        index=True,
    )
    goal_id: Mapped[Optional[UUID]] = mapped_column(
        PGUUID(as_uuid=True),
        ForeignKey("intervention_goals.id", ondelete="SET NULL"),
        nullable=True,
    )
    method: Mapped[str] = mapped_column(
        String(50),
        nullable=False,
    )
    description: Mapped[str] = mapped_column(
        Text,
        nullable=False,
    )
    frequency: Mapped[str] = mapped_column(
        String(50),
        nullable=False,
        default="weekly",
    )
    responsible_party: Mapped[str] = mapped_column(
        String(50),
        nullable=False,
        default="educator",
    )
    data_collection_tools: Mapped[Optional[str]] = mapped_column(
        Text,
        nullable=True,
    )
    success_indicators: Mapped[Optional[str]] = mapped_column(
        Text,
        nullable=True,
    )
    order: Mapped[int] = mapped_column(
        Integer,
        nullable=False,
        default=0,
    )
    created_at: Mapped[datetime] = mapped_column(
        DateTime,
        default=datetime.utcnow,
        nullable=False,
    )

    # Relationships
    plan: Mapped["InterventionPlan"] = relationship(
        "InterventionPlan",
        back_populates="monitoring",
    )

    # Table-level indexes
    __table_args__ = (
        Index("ix_intervention_monitoring_method", "method"),
    )


class InterventionParentInvolvement(Base):
    """Part 7 - Parent involvement activities.

    Stores how parents will be involved in the intervention plan,
    including activities they can do at home.

    Attributes:
        id: Unique identifier for the parent involvement record
        plan_id: ID of the parent intervention plan
        activity_type: Type of involvement (home_activity, communication, training, etc.)
        title: Title of the involvement activity
        description: Detailed description of what parents will do
        frequency: How often the activity should occur
        resources_provided: Resources or materials provided to parents
        communication_method: How progress will be communicated
        order: Display order for organizing involvement activities
        created_at: Timestamp when the record was created
        plan: Reference to the parent intervention plan
    """

    __tablename__ = "intervention_parent_involvements"

    id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        primary_key=True,
        default=uuid4,
    )
    plan_id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        ForeignKey("intervention_plans.id", ondelete="CASCADE"),
        nullable=False,
        index=True,
    )
    activity_type: Mapped[str] = mapped_column(
        String(50),
        nullable=False,
    )
    title: Mapped[str] = mapped_column(
        String(200),
        nullable=False,
    )
    description: Mapped[str] = mapped_column(
        Text,
        nullable=False,
    )
    frequency: Mapped[Optional[str]] = mapped_column(
        String(50),
        nullable=True,
    )
    resources_provided: Mapped[Optional[str]] = mapped_column(
        Text,
        nullable=True,
    )
    communication_method: Mapped[Optional[str]] = mapped_column(
        String(100),
        nullable=True,
    )
    order: Mapped[int] = mapped_column(
        Integer,
        nullable=False,
        default=0,
    )
    created_at: Mapped[datetime] = mapped_column(
        DateTime,
        default=datetime.utcnow,
        nullable=False,
    )

    # Relationships
    plan: Mapped["InterventionPlan"] = relationship(
        "InterventionPlan",
        back_populates="parent_involvements",
    )

    # Table-level indexes
    __table_args__ = (
        Index("ix_intervention_parent_involvements_type", "activity_type"),
    )


class InterventionConsultation(Base):
    """Part 8 - Consultations with specialists.

    Stores information about consultations with external specialists
    or professionals as part of the intervention plan.

    Attributes:
        id: Unique identifier for the consultation record
        plan_id: ID of the parent intervention plan
        specialist_type: Type of specialist (speech_therapist, occupational_therapist, etc.)
        specialist_name: Name of the specialist
        organization: Organization or practice name
        purpose: Purpose of the consultation
        recommendations: Recommendations from the specialist
        consultation_date: Date of the consultation
        next_consultation_date: Date of next scheduled consultation
        notes: Additional notes from the consultation
        order: Display order for organizing consultations
        created_at: Timestamp when the record was created
        plan: Reference to the parent intervention plan
    """

    __tablename__ = "intervention_consultations"

    id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        primary_key=True,
        default=uuid4,
    )
    plan_id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        ForeignKey("intervention_plans.id", ondelete="CASCADE"),
        nullable=False,
        index=True,
    )
    specialist_type: Mapped[str] = mapped_column(
        String(50),
        nullable=False,
    )
    specialist_name: Mapped[Optional[str]] = mapped_column(
        String(200),
        nullable=True,
    )
    organization: Mapped[Optional[str]] = mapped_column(
        String(200),
        nullable=True,
    )
    purpose: Mapped[str] = mapped_column(
        Text,
        nullable=False,
    )
    recommendations: Mapped[Optional[str]] = mapped_column(
        Text,
        nullable=True,
    )
    consultation_date: Mapped[Optional[date]] = mapped_column(
        Date,
        nullable=True,
    )
    next_consultation_date: Mapped[Optional[date]] = mapped_column(
        Date,
        nullable=True,
    )
    notes: Mapped[Optional[str]] = mapped_column(
        Text,
        nullable=True,
    )
    order: Mapped[int] = mapped_column(
        Integer,
        nullable=False,
        default=0,
    )
    created_at: Mapped[datetime] = mapped_column(
        DateTime,
        default=datetime.utcnow,
        nullable=False,
    )

    # Relationships
    plan: Mapped["InterventionPlan"] = relationship(
        "InterventionPlan",
        back_populates="consultations",
    )

    # Table-level indexes
    __table_args__ = (
        Index("ix_intervention_consultations_type", "specialist_type"),
        Index("ix_intervention_consultations_date", "consultation_date"),
    )


class InterventionProgress(Base):
    """Track progress on intervention plan goals.

    Stores periodic progress updates documenting how the child is
    progressing toward their intervention goals.

    Attributes:
        id: Unique identifier for the progress record
        plan_id: ID of the parent intervention plan
        goal_id: Optional link to the specific goal this progress is for
        recorded_by: ID of the user who recorded the progress
        record_date: Date the progress was recorded
        progress_notes: Detailed progress notes
        progress_level: Progress level (no_progress, minimal, moderate, significant, achieved)
        measurement_value: Quantitative measurement value if applicable
        barriers: Any barriers encountered
        next_steps: Recommended next steps
        attachments: JSON array of attachment references
        created_at: Timestamp when the record was created
        plan: Reference to the parent intervention plan
    """

    __tablename__ = "intervention_progress"

    id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        primary_key=True,
        default=uuid4,
    )
    plan_id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        ForeignKey("intervention_plans.id", ondelete="CASCADE"),
        nullable=False,
        index=True,
    )
    goal_id: Mapped[Optional[UUID]] = mapped_column(
        PGUUID(as_uuid=True),
        ForeignKey("intervention_goals.id", ondelete="SET NULL"),
        nullable=True,
    )
    recorded_by: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        nullable=False,
    )
    record_date: Mapped[date] = mapped_column(
        Date,
        nullable=False,
    )
    progress_notes: Mapped[str] = mapped_column(
        Text,
        nullable=False,
    )
    progress_level: Mapped[str] = mapped_column(
        String(20),
        nullable=False,
        default="minimal",
    )
    measurement_value: Mapped[Optional[str]] = mapped_column(
        String(100),
        nullable=True,
    )
    barriers: Mapped[Optional[str]] = mapped_column(
        Text,
        nullable=True,
    )
    next_steps: Mapped[Optional[str]] = mapped_column(
        Text,
        nullable=True,
    )
    attachments: Mapped[Optional[dict]] = mapped_column(
        JSONB,
        nullable=True,
    )
    created_at: Mapped[datetime] = mapped_column(
        DateTime,
        default=datetime.utcnow,
        nullable=False,
    )

    # Relationships
    plan: Mapped["InterventionPlan"] = relationship(
        "InterventionPlan",
        back_populates="progress_records",
    )

    # Table-level indexes
    __table_args__ = (
        Index("ix_intervention_progress_date", "record_date"),
        Index("ix_intervention_progress_plan_date", "plan_id", "record_date"),
    )


class InterventionVersion(Base):
    """Track version history of intervention plans.

    Stores a snapshot of the plan at each version for audit trail
    and historical reference.

    Attributes:
        id: Unique identifier for the version record
        plan_id: ID of the parent intervention plan
        version_number: Version number
        created_by: ID of the user who created this version
        change_summary: Summary of changes in this version
        snapshot_data: JSON snapshot of the full plan at this version
        created_at: Timestamp when the version was created
        plan: Reference to the parent intervention plan
    """

    __tablename__ = "intervention_versions"

    id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        primary_key=True,
        default=uuid4,
    )
    plan_id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        ForeignKey("intervention_plans.id", ondelete="CASCADE"),
        nullable=False,
        index=True,
    )
    version_number: Mapped[int] = mapped_column(
        Integer,
        nullable=False,
    )
    created_by: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        nullable=False,
    )
    change_summary: Mapped[Optional[str]] = mapped_column(
        Text,
        nullable=True,
    )
    snapshot_data: Mapped[Optional[dict]] = mapped_column(
        JSONB,
        nullable=True,
    )
    created_at: Mapped[datetime] = mapped_column(
        DateTime,
        default=datetime.utcnow,
        nullable=False,
    )

    # Relationships
    plan: Mapped["InterventionPlan"] = relationship(
        "InterventionPlan",
        back_populates="versions",
    )

    # Table-level indexes
    __table_args__ = (
        Index("ix_intervention_versions_plan_number", "plan_id", "version_number"),
    )
