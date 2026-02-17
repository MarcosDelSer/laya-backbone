"""Intervention plan domain schemas for LAYA AI Service.

Defines Pydantic schemas for intervention plan management with 8-part structure,
SMART goals, versioning, and progress tracking. These schemas support the
comprehensive intervention planning system for children with special needs.

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

from datetime import date, datetime
from enum import Enum
from typing import Any, Optional
from uuid import UUID

from pydantic import BaseModel, Field

from app.schemas.base import BaseResponse, BaseSchema, PaginatedResponse


# =============================================================================
# Enums for Intervention Plan
# =============================================================================


class InterventionPlanStatus(str, Enum):
    """Status of an intervention plan.

    Attributes:
        DRAFT: Plan is being created, not yet finalized
        ACTIVE: Plan is active and being implemented
        UNDER_REVIEW: Plan is under review for updates
        COMPLETED: Plan has been completed
        ARCHIVED: Plan has been archived
    """

    DRAFT = "draft"
    ACTIVE = "active"
    UNDER_REVIEW = "under_review"
    COMPLETED = "completed"
    ARCHIVED = "archived"


class ReviewSchedule(str, Enum):
    """Review schedule frequency for intervention plans.

    Attributes:
        MONTHLY: Review every month
        QUARTERLY: Review every 3 months
        SEMI_ANNUALLY: Review every 6 months
        ANNUALLY: Review every year
    """

    MONTHLY = "monthly"
    QUARTERLY = "quarterly"
    SEMI_ANNUALLY = "semi_annually"
    ANNUALLY = "annually"


class GoalStatus(str, Enum):
    """Status of a SMART goal.

    Attributes:
        NOT_STARTED: Goal has not been started
        IN_PROGRESS: Goal is currently being worked on
        ACHIEVED: Goal has been achieved
        MODIFIED: Goal has been modified from original
        DISCONTINUED: Goal has been discontinued
    """

    NOT_STARTED = "not_started"
    IN_PROGRESS = "in_progress"
    ACHIEVED = "achieved"
    MODIFIED = "modified"
    DISCONTINUED = "discontinued"


class ProgressLevel(str, Enum):
    """Progress level for intervention plan tracking.

    Attributes:
        NO_PROGRESS: No progress made
        MINIMAL: Minimal progress
        MODERATE: Moderate progress
        SIGNIFICANT: Significant progress
        ACHIEVED: Goal/target achieved
    """

    NO_PROGRESS = "no_progress"
    MINIMAL = "minimal"
    MODERATE = "moderate"
    SIGNIFICANT = "significant"
    ACHIEVED = "achieved"


class StrengthCategory(str, Enum):
    """Categories for child's strengths.

    Attributes:
        COGNITIVE: Cognitive strengths
        SOCIAL: Social interaction strengths
        PHYSICAL: Physical abilities
        EMOTIONAL: Emotional regulation strengths
        COMMUNICATION: Communication strengths
        CREATIVE: Creative abilities
        ACADEMIC: Academic strengths
        ADAPTIVE: Adaptive behavior strengths
        OTHER: Other strengths
    """

    COGNITIVE = "cognitive"
    SOCIAL = "social"
    PHYSICAL = "physical"
    EMOTIONAL = "emotional"
    COMMUNICATION = "communication"
    CREATIVE = "creative"
    ACADEMIC = "academic"
    ADAPTIVE = "adaptive"
    OTHER = "other"


class NeedCategory(str, Enum):
    """Categories for child's needs.

    Attributes:
        COMMUNICATION: Communication needs
        BEHAVIOR: Behavioral needs
        ACADEMIC: Academic/learning needs
        SENSORY: Sensory processing needs
        MOTOR: Motor skill needs
        SOCIAL: Social interaction needs
        EMOTIONAL: Emotional regulation needs
        SELF_CARE: Self-care/daily living needs
        COGNITIVE: Cognitive development needs
        OTHER: Other needs
    """

    COMMUNICATION = "communication"
    BEHAVIOR = "behavior"
    ACADEMIC = "academic"
    SENSORY = "sensory"
    MOTOR = "motor"
    SOCIAL = "social"
    EMOTIONAL = "emotional"
    SELF_CARE = "self_care"
    COGNITIVE = "cognitive"
    OTHER = "other"


class NeedPriority(str, Enum):
    """Priority levels for needs.

    Attributes:
        LOW: Low priority need
        MEDIUM: Medium priority need
        HIGH: High priority need
        CRITICAL: Critical/urgent need
    """

    LOW = "low"
    MEDIUM = "medium"
    HIGH = "high"
    CRITICAL = "critical"


class ResponsibleParty(str, Enum):
    """Who is responsible for a strategy or monitoring task.

    Attributes:
        EDUCATOR: Educator/teacher responsible
        PARENT: Parent/caregiver responsible
        THERAPIST: Therapist/specialist responsible
        TEAM: Shared team responsibility
        CHILD: Child (with support) responsible
    """

    EDUCATOR = "educator"
    PARENT = "parent"
    THERAPIST = "therapist"
    TEAM = "team"
    CHILD = "child"


class MonitoringMethod(str, Enum):
    """Methods for monitoring progress.

    Attributes:
        OBSERVATION: Direct observation
        ASSESSMENT: Formal assessment
        DATA_COLLECTION: Data collection/tracking
        INTERVIEW: Interview/discussion
        PORTFOLIO: Portfolio review
        CHECKLIST: Checklist completion
        OTHER: Other methods
    """

    OBSERVATION = "observation"
    ASSESSMENT = "assessment"
    DATA_COLLECTION = "data_collection"
    INTERVIEW = "interview"
    PORTFOLIO = "portfolio"
    CHECKLIST = "checklist"
    OTHER = "other"


class ParentActivityType(str, Enum):
    """Types of parent involvement activities.

    Attributes:
        HOME_ACTIVITY: Activities to do at home
        COMMUNICATION: Regular communication methods
        TRAINING: Parent training/education
        MEETING: Meetings/conferences
        OBSERVATION: Parent observation sessions
        DOCUMENTATION: Documentation tasks
        OTHER: Other involvement types
    """

    HOME_ACTIVITY = "home_activity"
    COMMUNICATION = "communication"
    TRAINING = "training"
    MEETING = "meeting"
    OBSERVATION = "observation"
    DOCUMENTATION = "documentation"
    OTHER = "other"


class SpecialistType(str, Enum):
    """Types of specialists for consultations.

    Attributes:
        SPEECH_THERAPIST: Speech-language pathologist
        OCCUPATIONAL_THERAPIST: Occupational therapist
        PHYSICAL_THERAPIST: Physical therapist
        PSYCHOLOGIST: Psychologist
        BEHAVIORAL_SPECIALIST: Behavioral specialist
        SPECIAL_EDUCATOR: Special education specialist
        SOCIAL_WORKER: Social worker
        PEDIATRICIAN: Pediatrician
        NEUROLOGIST: Neurologist
        PSYCHIATRIST: Psychiatrist
        OTHER: Other specialists
    """

    SPEECH_THERAPIST = "speech_therapist"
    OCCUPATIONAL_THERAPIST = "occupational_therapist"
    PHYSICAL_THERAPIST = "physical_therapist"
    PSYCHOLOGIST = "psychologist"
    BEHAVIORAL_SPECIALIST = "behavioral_specialist"
    SPECIAL_EDUCATOR = "special_educator"
    SOCIAL_WORKER = "social_worker"
    PEDIATRICIAN = "pediatrician"
    NEUROLOGIST = "neurologist"
    PSYCHIATRIST = "psychiatrist"
    OTHER = "other"


# =============================================================================
# Part 2 - Strengths Schemas
# =============================================================================


class StrengthBase(BaseSchema):
    """Base schema for intervention strengths (Part 2).

    Attributes:
        category: Category of strength
        description: Detailed description of the strength
        examples: Examples demonstrating this strength
        order: Display order for organizing strengths
    """

    category: StrengthCategory = Field(
        ...,
        description="Category of strength",
    )
    description: str = Field(
        ...,
        min_length=1,
        max_length=2000,
        description="Detailed description of the strength",
    )
    examples: Optional[str] = Field(
        default=None,
        max_length=2000,
        description="Examples demonstrating this strength",
    )
    order: int = Field(
        default=0,
        ge=0,
        description="Display order for organizing strengths",
    )


class StrengthCreate(StrengthBase):
    """Request schema for creating a strength."""

    pass


class StrengthUpdate(BaseSchema):
    """Request schema for updating a strength."""

    category: Optional[StrengthCategory] = Field(
        default=None,
        description="Category of strength",
    )
    description: Optional[str] = Field(
        default=None,
        min_length=1,
        max_length=2000,
        description="Detailed description of the strength",
    )
    examples: Optional[str] = Field(
        default=None,
        max_length=2000,
        description="Examples demonstrating this strength",
    )
    order: Optional[int] = Field(
        default=None,
        ge=0,
        description="Display order for organizing strengths",
    )


class StrengthResponse(StrengthBase):
    """Response schema for a strength with ID and timestamps."""

    id: UUID = Field(..., description="Unique identifier for the strength")
    plan_id: UUID = Field(..., description="ID of the parent intervention plan")
    created_at: datetime = Field(..., description="Timestamp when the record was created")


# =============================================================================
# Part 3 - Needs Schemas
# =============================================================================


class NeedBase(BaseSchema):
    """Base schema for intervention needs (Part 3).

    Attributes:
        category: Category of need
        description: Detailed description of the need
        priority: Priority level of the need
        baseline: Baseline assessment of current ability
        order: Display order for organizing needs
    """

    category: NeedCategory = Field(
        ...,
        description="Category of need",
    )
    description: str = Field(
        ...,
        min_length=1,
        max_length=2000,
        description="Detailed description of the need",
    )
    priority: NeedPriority = Field(
        default=NeedPriority.MEDIUM,
        description="Priority level of the need",
    )
    baseline: Optional[str] = Field(
        default=None,
        max_length=2000,
        description="Baseline assessment of current ability",
    )
    order: int = Field(
        default=0,
        ge=0,
        description="Display order for organizing needs",
    )


class NeedCreate(NeedBase):
    """Request schema for creating a need."""

    pass


class NeedUpdate(BaseSchema):
    """Request schema for updating a need."""

    category: Optional[NeedCategory] = Field(
        default=None,
        description="Category of need",
    )
    description: Optional[str] = Field(
        default=None,
        min_length=1,
        max_length=2000,
        description="Detailed description of the need",
    )
    priority: Optional[NeedPriority] = Field(
        default=None,
        description="Priority level of the need",
    )
    baseline: Optional[str] = Field(
        default=None,
        max_length=2000,
        description="Baseline assessment of current ability",
    )
    order: Optional[int] = Field(
        default=None,
        ge=0,
        description="Display order for organizing needs",
    )


class NeedResponse(NeedBase):
    """Response schema for a need with ID and timestamps."""

    id: UUID = Field(..., description="Unique identifier for the need")
    plan_id: UUID = Field(..., description="ID of the parent intervention plan")
    created_at: datetime = Field(..., description="Timestamp when the record was created")


# =============================================================================
# Part 4 - SMART Goals Schemas
# =============================================================================


class SMARTGoalBase(BaseSchema):
    """Base schema for SMART goals (Part 4).

    SMART Goals are:
    - Specific: Clear and well-defined
    - Measurable: Quantifiable outcomes
    - Achievable: Realistic and attainable
    - Relevant: Aligned with child's needs
    - Time-bound: Has a target date

    Attributes:
        title: Short title of the goal
        description: Full description of the goal (Specific)
        measurement_criteria: How progress will be measured (Measurable)
        measurement_baseline: Baseline measurement value
        measurement_target: Target measurement value
        achievability_notes: Notes on why this goal is achievable (Achievable)
        relevance_notes: Notes on why this goal is relevant (Relevant)
        target_date: Target date for achieving the goal (Time-bound)
        status: Current status of the goal
        progress_percentage: Current progress as percentage (0-100)
        order: Display order for organizing goals
    """

    title: str = Field(
        ...,
        min_length=1,
        max_length=200,
        description="Short title of the goal",
    )
    description: str = Field(
        ...,
        min_length=1,
        max_length=2000,
        description="Full description of the goal (Specific)",
    )
    measurement_criteria: str = Field(
        ...,
        min_length=1,
        max_length=2000,
        description="How progress will be measured (Measurable)",
    )
    measurement_baseline: Optional[str] = Field(
        default=None,
        max_length=100,
        description="Baseline measurement value",
    )
    measurement_target: Optional[str] = Field(
        default=None,
        max_length=100,
        description="Target measurement value",
    )
    achievability_notes: Optional[str] = Field(
        default=None,
        max_length=2000,
        description="Notes on why this goal is achievable (Achievable)",
    )
    relevance_notes: Optional[str] = Field(
        default=None,
        max_length=2000,
        description="Notes on why this goal is relevant (Relevant)",
    )
    target_date: Optional[date] = Field(
        default=None,
        description="Target date for achieving the goal (Time-bound)",
    )
    status: GoalStatus = Field(
        default=GoalStatus.NOT_STARTED,
        description="Current status of the goal",
    )
    progress_percentage: float = Field(
        default=0.0,
        ge=0.0,
        le=100.0,
        description="Current progress as percentage (0-100)",
    )
    order: int = Field(
        default=0,
        ge=0,
        description="Display order for organizing goals",
    )


class SMARTGoalCreate(SMARTGoalBase):
    """Request schema for creating a SMART goal.

    Attributes:
        need_id: Optional link to the specific need this goal addresses
    """

    need_id: Optional[UUID] = Field(
        default=None,
        description="Optional link to the specific need this goal addresses",
    )


class SMARTGoalUpdate(BaseSchema):
    """Request schema for updating a SMART goal."""

    need_id: Optional[UUID] = Field(
        default=None,
        description="Optional link to the specific need this goal addresses",
    )
    title: Optional[str] = Field(
        default=None,
        min_length=1,
        max_length=200,
        description="Short title of the goal",
    )
    description: Optional[str] = Field(
        default=None,
        min_length=1,
        max_length=2000,
        description="Full description of the goal (Specific)",
    )
    measurement_criteria: Optional[str] = Field(
        default=None,
        min_length=1,
        max_length=2000,
        description="How progress will be measured (Measurable)",
    )
    measurement_baseline: Optional[str] = Field(
        default=None,
        max_length=100,
        description="Baseline measurement value",
    )
    measurement_target: Optional[str] = Field(
        default=None,
        max_length=100,
        description="Target measurement value",
    )
    achievability_notes: Optional[str] = Field(
        default=None,
        max_length=2000,
        description="Notes on why this goal is achievable (Achievable)",
    )
    relevance_notes: Optional[str] = Field(
        default=None,
        max_length=2000,
        description="Notes on why this goal is relevant (Relevant)",
    )
    target_date: Optional[date] = Field(
        default=None,
        description="Target date for achieving the goal (Time-bound)",
    )
    status: Optional[GoalStatus] = Field(
        default=None,
        description="Current status of the goal",
    )
    progress_percentage: Optional[float] = Field(
        default=None,
        ge=0.0,
        le=100.0,
        description="Current progress as percentage (0-100)",
    )
    order: Optional[int] = Field(
        default=None,
        ge=0,
        description="Display order for organizing goals",
    )


class SMARTGoalResponse(SMARTGoalBase):
    """Response schema for a SMART goal with ID and timestamps."""

    id: UUID = Field(..., description="Unique identifier for the goal")
    plan_id: UUID = Field(..., description="ID of the parent intervention plan")
    need_id: Optional[UUID] = Field(
        default=None,
        description="Optional link to the specific need this goal addresses",
    )
    created_at: datetime = Field(..., description="Timestamp when the record was created")
    updated_at: Optional[datetime] = Field(
        default=None,
        description="Timestamp when the record was last updated",
    )


# =============================================================================
# Part 5 - Strategies Schemas
# =============================================================================


class StrategyBase(BaseSchema):
    """Base schema for intervention strategies (Part 5).

    Attributes:
        title: Title of the strategy
        description: Detailed description of the strategy
        responsible_party: Who is responsible for implementing
        frequency: How often the strategy should be applied
        materials_needed: Materials or resources needed
        accommodations: Any accommodations required
        order: Display order for organizing strategies
    """

    title: str = Field(
        ...,
        min_length=1,
        max_length=200,
        description="Title of the strategy",
    )
    description: str = Field(
        ...,
        min_length=1,
        max_length=3000,
        description="Detailed description of the strategy",
    )
    responsible_party: ResponsibleParty = Field(
        default=ResponsibleParty.EDUCATOR,
        description="Who is responsible for implementing",
    )
    frequency: Optional[str] = Field(
        default=None,
        max_length=100,
        description="How often the strategy should be applied",
    )
    materials_needed: Optional[str] = Field(
        default=None,
        max_length=2000,
        description="Materials or resources needed",
    )
    accommodations: Optional[str] = Field(
        default=None,
        max_length=2000,
        description="Any accommodations required",
    )
    order: int = Field(
        default=0,
        ge=0,
        description="Display order for organizing strategies",
    )


class StrategyCreate(StrategyBase):
    """Request schema for creating a strategy.

    Attributes:
        goal_id: Optional link to the specific goal this strategy supports
    """

    goal_id: Optional[UUID] = Field(
        default=None,
        description="Optional link to the specific goal this strategy supports",
    )


class StrategyUpdate(BaseSchema):
    """Request schema for updating a strategy."""

    goal_id: Optional[UUID] = Field(
        default=None,
        description="Optional link to the specific goal this strategy supports",
    )
    title: Optional[str] = Field(
        default=None,
        min_length=1,
        max_length=200,
        description="Title of the strategy",
    )
    description: Optional[str] = Field(
        default=None,
        min_length=1,
        max_length=3000,
        description="Detailed description of the strategy",
    )
    responsible_party: Optional[ResponsibleParty] = Field(
        default=None,
        description="Who is responsible for implementing",
    )
    frequency: Optional[str] = Field(
        default=None,
        max_length=100,
        description="How often the strategy should be applied",
    )
    materials_needed: Optional[str] = Field(
        default=None,
        max_length=2000,
        description="Materials or resources needed",
    )
    accommodations: Optional[str] = Field(
        default=None,
        max_length=2000,
        description="Any accommodations required",
    )
    order: Optional[int] = Field(
        default=None,
        ge=0,
        description="Display order for organizing strategies",
    )


class StrategyResponse(StrategyBase):
    """Response schema for a strategy with ID and timestamps."""

    id: UUID = Field(..., description="Unique identifier for the strategy")
    plan_id: UUID = Field(..., description="ID of the parent intervention plan")
    goal_id: Optional[UUID] = Field(
        default=None,
        description="Optional link to the specific goal this strategy supports",
    )
    created_at: datetime = Field(..., description="Timestamp when the record was created")


# =============================================================================
# Part 6 - Monitoring Schemas
# =============================================================================


class MonitoringBase(BaseSchema):
    """Base schema for monitoring approaches (Part 6).

    Attributes:
        method: Method of monitoring
        description: Detailed description of the monitoring approach
        frequency: How often monitoring occurs
        responsible_party: Who is responsible for monitoring
        data_collection_tools: Tools or forms used for data collection
        success_indicators: What indicates successful progress
        order: Display order for organizing monitoring approaches
    """

    method: MonitoringMethod = Field(
        ...,
        description="Method of monitoring",
    )
    description: str = Field(
        ...,
        min_length=1,
        max_length=2000,
        description="Detailed description of the monitoring approach",
    )
    frequency: str = Field(
        default="weekly",
        max_length=50,
        description="How often monitoring occurs",
    )
    responsible_party: ResponsibleParty = Field(
        default=ResponsibleParty.EDUCATOR,
        description="Who is responsible for monitoring",
    )
    data_collection_tools: Optional[str] = Field(
        default=None,
        max_length=2000,
        description="Tools or forms used for data collection",
    )
    success_indicators: Optional[str] = Field(
        default=None,
        max_length=2000,
        description="What indicates successful progress",
    )
    order: int = Field(
        default=0,
        ge=0,
        description="Display order for organizing monitoring approaches",
    )


class MonitoringCreate(MonitoringBase):
    """Request schema for creating a monitoring approach.

    Attributes:
        goal_id: Optional link to the specific goal being monitored
    """

    goal_id: Optional[UUID] = Field(
        default=None,
        description="Optional link to the specific goal being monitored",
    )


class MonitoringUpdate(BaseSchema):
    """Request schema for updating a monitoring approach."""

    goal_id: Optional[UUID] = Field(
        default=None,
        description="Optional link to the specific goal being monitored",
    )
    method: Optional[MonitoringMethod] = Field(
        default=None,
        description="Method of monitoring",
    )
    description: Optional[str] = Field(
        default=None,
        min_length=1,
        max_length=2000,
        description="Detailed description of the monitoring approach",
    )
    frequency: Optional[str] = Field(
        default=None,
        max_length=50,
        description="How often monitoring occurs",
    )
    responsible_party: Optional[ResponsibleParty] = Field(
        default=None,
        description="Who is responsible for monitoring",
    )
    data_collection_tools: Optional[str] = Field(
        default=None,
        max_length=2000,
        description="Tools or forms used for data collection",
    )
    success_indicators: Optional[str] = Field(
        default=None,
        max_length=2000,
        description="What indicates successful progress",
    )
    order: Optional[int] = Field(
        default=None,
        ge=0,
        description="Display order for organizing monitoring approaches",
    )


class MonitoringResponse(MonitoringBase):
    """Response schema for a monitoring approach with ID and timestamps."""

    id: UUID = Field(..., description="Unique identifier for the monitoring approach")
    plan_id: UUID = Field(..., description="ID of the parent intervention plan")
    goal_id: Optional[UUID] = Field(
        default=None,
        description="Optional link to the specific goal being monitored",
    )
    created_at: datetime = Field(..., description="Timestamp when the record was created")


# =============================================================================
# Part 7 - Parent Involvement Schemas
# =============================================================================


class ParentInvolvementBase(BaseSchema):
    """Base schema for parent involvement activities (Part 7).

    Attributes:
        activity_type: Type of involvement
        title: Title of the involvement activity
        description: Detailed description of what parents will do
        frequency: How often the activity should occur
        resources_provided: Resources or materials provided to parents
        communication_method: How progress will be communicated
        order: Display order for organizing involvement activities
    """

    activity_type: ParentActivityType = Field(
        ...,
        description="Type of involvement",
    )
    title: str = Field(
        ...,
        min_length=1,
        max_length=200,
        description="Title of the involvement activity",
    )
    description: str = Field(
        ...,
        min_length=1,
        max_length=2000,
        description="Detailed description of what parents will do",
    )
    frequency: Optional[str] = Field(
        default=None,
        max_length=50,
        description="How often the activity should occur",
    )
    resources_provided: Optional[str] = Field(
        default=None,
        max_length=2000,
        description="Resources or materials provided to parents",
    )
    communication_method: Optional[str] = Field(
        default=None,
        max_length=100,
        description="How progress will be communicated",
    )
    order: int = Field(
        default=0,
        ge=0,
        description="Display order for organizing involvement activities",
    )


class ParentInvolvementCreate(ParentInvolvementBase):
    """Request schema for creating a parent involvement activity."""

    pass


class ParentInvolvementUpdate(BaseSchema):
    """Request schema for updating a parent involvement activity."""

    activity_type: Optional[ParentActivityType] = Field(
        default=None,
        description="Type of involvement",
    )
    title: Optional[str] = Field(
        default=None,
        min_length=1,
        max_length=200,
        description="Title of the involvement activity",
    )
    description: Optional[str] = Field(
        default=None,
        min_length=1,
        max_length=2000,
        description="Detailed description of what parents will do",
    )
    frequency: Optional[str] = Field(
        default=None,
        max_length=50,
        description="How often the activity should occur",
    )
    resources_provided: Optional[str] = Field(
        default=None,
        max_length=2000,
        description="Resources or materials provided to parents",
    )
    communication_method: Optional[str] = Field(
        default=None,
        max_length=100,
        description="How progress will be communicated",
    )
    order: Optional[int] = Field(
        default=None,
        ge=0,
        description="Display order for organizing involvement activities",
    )


class ParentInvolvementResponse(ParentInvolvementBase):
    """Response schema for a parent involvement activity with ID and timestamps."""

    id: UUID = Field(..., description="Unique identifier for the parent involvement")
    plan_id: UUID = Field(..., description="ID of the parent intervention plan")
    created_at: datetime = Field(..., description="Timestamp when the record was created")


# =============================================================================
# Part 8 - Consultation Schemas
# =============================================================================


class ConsultationBase(BaseSchema):
    """Base schema for consultations with specialists (Part 8).

    Attributes:
        specialist_type: Type of specialist
        specialist_name: Name of the specialist
        organization: Organization or practice name
        purpose: Purpose of the consultation
        recommendations: Recommendations from the specialist
        consultation_date: Date of the consultation
        next_consultation_date: Date of next scheduled consultation
        notes: Additional notes from the consultation
        order: Display order for organizing consultations
    """

    specialist_type: SpecialistType = Field(
        ...,
        description="Type of specialist",
    )
    specialist_name: Optional[str] = Field(
        default=None,
        max_length=200,
        description="Name of the specialist",
    )
    organization: Optional[str] = Field(
        default=None,
        max_length=200,
        description="Organization or practice name",
    )
    purpose: str = Field(
        ...,
        min_length=1,
        max_length=2000,
        description="Purpose of the consultation",
    )
    recommendations: Optional[str] = Field(
        default=None,
        max_length=3000,
        description="Recommendations from the specialist",
    )
    consultation_date: Optional[date] = Field(
        default=None,
        description="Date of the consultation",
    )
    next_consultation_date: Optional[date] = Field(
        default=None,
        description="Date of next scheduled consultation",
    )
    notes: Optional[str] = Field(
        default=None,
        max_length=2000,
        description="Additional notes from the consultation",
    )
    order: int = Field(
        default=0,
        ge=0,
        description="Display order for organizing consultations",
    )


class ConsultationCreate(ConsultationBase):
    """Request schema for creating a consultation."""

    pass


class ConsultationUpdate(BaseSchema):
    """Request schema for updating a consultation."""

    specialist_type: Optional[SpecialistType] = Field(
        default=None,
        description="Type of specialist",
    )
    specialist_name: Optional[str] = Field(
        default=None,
        max_length=200,
        description="Name of the specialist",
    )
    organization: Optional[str] = Field(
        default=None,
        max_length=200,
        description="Organization or practice name",
    )
    purpose: Optional[str] = Field(
        default=None,
        min_length=1,
        max_length=2000,
        description="Purpose of the consultation",
    )
    recommendations: Optional[str] = Field(
        default=None,
        max_length=3000,
        description="Recommendations from the specialist",
    )
    consultation_date: Optional[date] = Field(
        default=None,
        description="Date of the consultation",
    )
    next_consultation_date: Optional[date] = Field(
        default=None,
        description="Date of next scheduled consultation",
    )
    notes: Optional[str] = Field(
        default=None,
        max_length=2000,
        description="Additional notes from the consultation",
    )
    order: Optional[int] = Field(
        default=None,
        ge=0,
        description="Display order for organizing consultations",
    )


class ConsultationResponse(ConsultationBase):
    """Response schema for a consultation with ID and timestamps."""

    id: UUID = Field(..., description="Unique identifier for the consultation")
    plan_id: UUID = Field(..., description="ID of the parent intervention plan")
    created_at: datetime = Field(..., description="Timestamp when the record was created")


# =============================================================================
# Progress Tracking Schemas
# =============================================================================


class ProgressBase(BaseSchema):
    """Base schema for progress tracking records.

    Attributes:
        record_date: Date the progress was recorded
        progress_notes: Detailed progress notes
        progress_level: Progress level
        measurement_value: Quantitative measurement value if applicable
        barriers: Any barriers encountered
        next_steps: Recommended next steps
        attachments: JSON array of attachment references
    """

    record_date: date = Field(
        ...,
        description="Date the progress was recorded",
    )
    progress_notes: str = Field(
        ...,
        min_length=1,
        max_length=5000,
        description="Detailed progress notes",
    )
    progress_level: ProgressLevel = Field(
        default=ProgressLevel.MINIMAL,
        description="Progress level",
    )
    measurement_value: Optional[str] = Field(
        default=None,
        max_length=100,
        description="Quantitative measurement value if applicable",
    )
    barriers: Optional[str] = Field(
        default=None,
        max_length=2000,
        description="Any barriers encountered",
    )
    next_steps: Optional[str] = Field(
        default=None,
        max_length=2000,
        description="Recommended next steps",
    )
    attachments: Optional[dict[str, Any]] = Field(
        default=None,
        description="JSON array of attachment references",
    )


class ProgressCreate(ProgressBase):
    """Request schema for creating a progress record.

    Attributes:
        goal_id: Optional link to the specific goal this progress is for
    """

    goal_id: Optional[UUID] = Field(
        default=None,
        description="Optional link to the specific goal this progress is for",
    )


class ProgressUpdate(BaseSchema):
    """Request schema for updating a progress record."""

    goal_id: Optional[UUID] = Field(
        default=None,
        description="Optional link to the specific goal this progress is for",
    )
    record_date: Optional[date] = Field(
        default=None,
        description="Date the progress was recorded",
    )
    progress_notes: Optional[str] = Field(
        default=None,
        min_length=1,
        max_length=5000,
        description="Detailed progress notes",
    )
    progress_level: Optional[ProgressLevel] = Field(
        default=None,
        description="Progress level",
    )
    measurement_value: Optional[str] = Field(
        default=None,
        max_length=100,
        description="Quantitative measurement value if applicable",
    )
    barriers: Optional[str] = Field(
        default=None,
        max_length=2000,
        description="Any barriers encountered",
    )
    next_steps: Optional[str] = Field(
        default=None,
        max_length=2000,
        description="Recommended next steps",
    )
    attachments: Optional[dict[str, Any]] = Field(
        default=None,
        description="JSON array of attachment references",
    )


class ProgressResponse(ProgressBase):
    """Response schema for a progress record with ID and timestamps."""

    id: UUID = Field(..., description="Unique identifier for the progress record")
    plan_id: UUID = Field(..., description="ID of the parent intervention plan")
    goal_id: Optional[UUID] = Field(
        default=None,
        description="Optional link to the specific goal this progress is for",
    )
    recorded_by: UUID = Field(..., description="ID of the user who recorded the progress")
    created_at: datetime = Field(..., description="Timestamp when the record was created")


# =============================================================================
# Version History Schemas
# =============================================================================


class VersionBase(BaseSchema):
    """Base schema for version history records.

    Attributes:
        version_number: Version number
        change_summary: Summary of changes in this version
        snapshot_data: JSON snapshot of the full plan at this version
    """

    version_number: int = Field(
        ...,
        ge=1,
        description="Version number",
    )
    change_summary: Optional[str] = Field(
        default=None,
        max_length=2000,
        description="Summary of changes in this version",
    )
    snapshot_data: Optional[dict[str, Any]] = Field(
        default=None,
        description="JSON snapshot of the full plan at this version",
    )


class VersionCreate(VersionBase):
    """Request schema for creating a version record."""

    pass


class VersionResponse(VersionBase):
    """Response schema for a version record with ID and timestamps."""

    id: UUID = Field(..., description="Unique identifier for the version record")
    plan_id: UUID = Field(..., description="ID of the parent intervention plan")
    created_by: UUID = Field(..., description="ID of the user who created this version")
    created_at: datetime = Field(..., description="Timestamp when the version was created")


# =============================================================================
# Main Intervention Plan Schemas
# =============================================================================


class InterventionPlanBase(BaseSchema):
    """Base schema for intervention plans.

    Contains Part 1 - Identification & History fields plus plan metadata.

    Attributes:
        title: Title of the intervention plan
        status: Plan status

        Part 1 - Identification & History:
        child_name: Full name of the child
        date_of_birth: Child's date of birth
        diagnosis: Primary diagnosis or special need types
        medical_history: Relevant medical history
        educational_history: Relevant educational background
        family_context: Family situation and context

        Review and scheduling:
        review_schedule: How often the plan should be reviewed
        next_review_date: When the next review is due

        Dates:
        effective_date: When the plan becomes effective
        end_date: When the plan ends (if applicable)
    """

    title: str = Field(
        ...,
        min_length=1,
        max_length=200,
        description="Title of the intervention plan",
    )
    status: InterventionPlanStatus = Field(
        default=InterventionPlanStatus.DRAFT,
        description="Plan status",
    )

    # Part 1 - Identification & History
    child_name: str = Field(
        ...,
        min_length=1,
        max_length=200,
        description="Full name of the child",
    )
    date_of_birth: Optional[date] = Field(
        default=None,
        description="Child's date of birth",
    )
    diagnosis: Optional[list[str]] = Field(
        default=None,
        description="Primary diagnosis or special need types",
    )
    medical_history: Optional[str] = Field(
        default=None,
        max_length=5000,
        description="Relevant medical history",
    )
    educational_history: Optional[str] = Field(
        default=None,
        max_length=5000,
        description="Relevant educational background",
    )
    family_context: Optional[str] = Field(
        default=None,
        max_length=3000,
        description="Family situation and context",
    )

    # Review and scheduling
    review_schedule: ReviewSchedule = Field(
        default=ReviewSchedule.QUARTERLY,
        description="How often the plan should be reviewed",
    )
    next_review_date: Optional[date] = Field(
        default=None,
        description="When the next review is due",
    )

    # Dates
    effective_date: Optional[date] = Field(
        default=None,
        description="When the plan becomes effective",
    )
    end_date: Optional[date] = Field(
        default=None,
        description="When the plan ends (if applicable)",
    )


class InterventionPlanCreate(InterventionPlanBase):
    """Request schema for creating an intervention plan.

    Includes all base fields plus child_id and optional sections
    to create in a single request.

    Attributes:
        child_id: ID of the child the plan is for
        strengths: Optional list of strengths to create (Part 2)
        needs: Optional list of needs to create (Part 3)
        goals: Optional list of SMART goals to create (Part 4)
        strategies: Optional list of strategies to create (Part 5)
        monitoring: Optional list of monitoring approaches to create (Part 6)
        parent_involvements: Optional list of parent involvements to create (Part 7)
        consultations: Optional list of consultations to create (Part 8)
    """

    child_id: UUID = Field(
        ...,
        description="ID of the child the plan is for",
    )

    # Optional nested creation for all 8 parts
    strengths: Optional[list[StrengthCreate]] = Field(
        default=None,
        description="Optional list of strengths to create (Part 2)",
    )
    needs: Optional[list[NeedCreate]] = Field(
        default=None,
        description="Optional list of needs to create (Part 3)",
    )
    goals: Optional[list[SMARTGoalCreate]] = Field(
        default=None,
        description="Optional list of SMART goals to create (Part 4)",
    )
    strategies: Optional[list[StrategyCreate]] = Field(
        default=None,
        description="Optional list of strategies to create (Part 5)",
    )
    monitoring: Optional[list[MonitoringCreate]] = Field(
        default=None,
        description="Optional list of monitoring approaches to create (Part 6)",
    )
    parent_involvements: Optional[list[ParentInvolvementCreate]] = Field(
        default=None,
        description="Optional list of parent involvements to create (Part 7)",
    )
    consultations: Optional[list[ConsultationCreate]] = Field(
        default=None,
        description="Optional list of consultations to create (Part 8)",
    )


class InterventionPlanUpdate(BaseSchema):
    """Request schema for updating an intervention plan.

    All fields are optional - only provided fields will be updated.
    """

    title: Optional[str] = Field(
        default=None,
        min_length=1,
        max_length=200,
        description="Title of the intervention plan",
    )
    status: Optional[InterventionPlanStatus] = Field(
        default=None,
        description="Plan status",
    )

    # Part 1 - Identification & History
    child_name: Optional[str] = Field(
        default=None,
        min_length=1,
        max_length=200,
        description="Full name of the child",
    )
    date_of_birth: Optional[date] = Field(
        default=None,
        description="Child's date of birth",
    )
    diagnosis: Optional[list[str]] = Field(
        default=None,
        description="Primary diagnosis or special need types",
    )
    medical_history: Optional[str] = Field(
        default=None,
        max_length=5000,
        description="Relevant medical history",
    )
    educational_history: Optional[str] = Field(
        default=None,
        max_length=5000,
        description="Relevant educational background",
    )
    family_context: Optional[str] = Field(
        default=None,
        max_length=3000,
        description="Family situation and context",
    )

    # Review and scheduling
    review_schedule: Optional[ReviewSchedule] = Field(
        default=None,
        description="How often the plan should be reviewed",
    )
    next_review_date: Optional[date] = Field(
        default=None,
        description="When the next review is due",
    )

    # Dates
    effective_date: Optional[date] = Field(
        default=None,
        description="When the plan becomes effective",
    )
    end_date: Optional[date] = Field(
        default=None,
        description="When the plan ends (if applicable)",
    )


class InterventionPlanResponse(InterventionPlanBase, BaseResponse):
    """Response schema for an intervention plan with all sections.

    Includes all base fields, ID, timestamps, and all 8 sections.

    Attributes:
        child_id: ID of the child the plan is for
        created_by: ID of the user who created the plan
        version: Current version number
        parent_version_id: ID of the previous version (for version history)
        parent_signed: Whether parent has signed the plan
        parent_signature_date: When parent signed
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

    child_id: UUID = Field(..., description="ID of the child the plan is for")
    created_by: UUID = Field(..., description="ID of the user who created the plan")
    version: int = Field(default=1, ge=1, description="Current version number")
    parent_version_id: Optional[UUID] = Field(
        default=None,
        description="ID of the previous version (for version history)",
    )

    # Parent signature
    parent_signed: bool = Field(
        default=False,
        description="Whether parent has signed the plan",
    )
    parent_signature_date: Optional[datetime] = Field(
        default=None,
        description="When parent signed",
    )

    # All 8 sections
    strengths: list[StrengthResponse] = Field(
        default_factory=list,
        description="List of identified strengths (Part 2)",
    )
    needs: list[NeedResponse] = Field(
        default_factory=list,
        description="List of identified needs (Part 3)",
    )
    goals: list[SMARTGoalResponse] = Field(
        default_factory=list,
        description="List of SMART goals (Part 4)",
    )
    strategies: list[StrategyResponse] = Field(
        default_factory=list,
        description="List of intervention strategies (Part 5)",
    )
    monitoring: list[MonitoringResponse] = Field(
        default_factory=list,
        description="List of monitoring approaches (Part 6)",
    )
    parent_involvements: list[ParentInvolvementResponse] = Field(
        default_factory=list,
        description="List of parent involvement activities (Part 7)",
    )
    consultations: list[ConsultationResponse] = Field(
        default_factory=list,
        description="List of consultations (Part 8)",
    )

    # Progress and version history
    progress_records: list[ProgressResponse] = Field(
        default_factory=list,
        description="List of progress tracking records",
    )
    versions: list[VersionResponse] = Field(
        default_factory=list,
        description="List of version history records",
    )


class InterventionPlanSummary(BaseSchema):
    """Summary schema for intervention plan list views.

    A lighter-weight response for list endpoints that excludes
    detailed sections.

    Attributes:
        id: Unique identifier for the plan
        child_id: ID of the child the plan is for
        child_name: Full name of the child
        title: Title of the intervention plan
        status: Plan status
        version: Current version number
        review_schedule: How often the plan should be reviewed
        next_review_date: When the next review is due
        parent_signed: Whether parent has signed the plan
        goals_count: Number of goals in the plan
        progress_count: Number of progress records
        created_at: Timestamp when the plan was created
        updated_at: Timestamp when the plan was last updated
    """

    id: UUID = Field(..., description="Unique identifier for the plan")
    child_id: UUID = Field(..., description="ID of the child the plan is for")
    child_name: str = Field(..., description="Full name of the child")
    title: str = Field(..., description="Title of the intervention plan")
    status: InterventionPlanStatus = Field(..., description="Plan status")
    version: int = Field(default=1, ge=1, description="Current version number")
    review_schedule: ReviewSchedule = Field(
        ...,
        description="How often the plan should be reviewed",
    )
    next_review_date: Optional[date] = Field(
        default=None,
        description="When the next review is due",
    )
    parent_signed: bool = Field(
        default=False,
        description="Whether parent has signed the plan",
    )
    goals_count: int = Field(
        default=0,
        ge=0,
        description="Number of goals in the plan",
    )
    progress_count: int = Field(
        default=0,
        ge=0,
        description="Number of progress records",
    )
    created_at: datetime = Field(..., description="Timestamp when the plan was created")
    updated_at: Optional[datetime] = Field(
        default=None,
        description="Timestamp when the plan was last updated",
    )


class InterventionPlanListResponse(PaginatedResponse):
    """Paginated list of intervention plan summaries.

    Attributes:
        items: List of intervention plan summaries
    """

    items: list[InterventionPlanSummary] = Field(
        ...,
        description="List of intervention plan summaries",
    )


# =============================================================================
# Parent Signature Schemas
# =============================================================================


class ParentSignatureRequest(BaseSchema):
    """Request schema for parent signature on an intervention plan.

    Attributes:
        signature_data: Base64 encoded signature image or JSON signature data
        agreed_to_terms: Whether parent has agreed to the plan terms
    """

    signature_data: str = Field(
        ...,
        min_length=1,
        description="Base64 encoded signature image or JSON signature data",
    )
    agreed_to_terms: bool = Field(
        default=True,
        description="Whether parent has agreed to the plan terms",
    )


class ParentSignatureResponse(BaseSchema):
    """Response schema for parent signature confirmation.

    Attributes:
        plan_id: ID of the signed intervention plan
        parent_signed: Whether the plan is now signed
        parent_signature_date: When the parent signed
        message: Confirmation message
    """

    plan_id: UUID = Field(..., description="ID of the signed intervention plan")
    parent_signed: bool = Field(default=True, description="Whether the plan is now signed")
    parent_signature_date: datetime = Field(..., description="When the parent signed")
    message: str = Field(
        default="Intervention plan signed successfully",
        description="Confirmation message",
    )


# =============================================================================
# Review Reminder Schemas
# =============================================================================


class PlanReviewReminder(BaseSchema):
    """Schema for plans that are due for review.

    Attributes:
        plan_id: ID of the intervention plan
        child_id: ID of the child
        child_name: Name of the child
        title: Title of the intervention plan
        next_review_date: When the review is due
        days_until_review: Number of days until the review is due (negative if overdue)
        status: Current plan status
    """

    plan_id: UUID = Field(..., description="ID of the intervention plan")
    child_id: UUID = Field(..., description="ID of the child")
    child_name: str = Field(..., description="Name of the child")
    title: str = Field(..., description="Title of the intervention plan")
    next_review_date: date = Field(..., description="When the review is due")
    days_until_review: int = Field(
        ...,
        description="Number of days until the review is due (negative if overdue)",
    )
    status: InterventionPlanStatus = Field(..., description="Current plan status")


class PlanReviewReminderListResponse(BaseSchema):
    """Response schema for plans pending review.

    Attributes:
        plans: List of plans due for review
        overdue_count: Number of plans past their review date
        upcoming_count: Number of plans with upcoming reviews
    """

    plans: list[PlanReviewReminder] = Field(
        ...,
        description="List of plans due for review",
    )
    overdue_count: int = Field(
        default=0,
        ge=0,
        description="Number of plans past their review date",
    )
    upcoming_count: int = Field(
        default=0,
        ge=0,
        description="Number of plans with upcoming reviews",
    )
