"""Development Profile domain schemas for LAYA AI Service.

Defines Pydantic schemas for Quebec-aligned developmental tracking across 6 domains:
1. Affective Development (emotional expression, self-regulation, attachment, self-confidence)
2. Social Development (peer interactions, turn-taking, empathy, group participation)
3. Language & Communication (receptive/expressive language, speech clarity, emergent literacy)
4. Cognitive Development (problem-solving, memory, attention, classification, number concept)
5. Physical - Gross Motor (balance, coordination, body awareness, outdoor skills)
6. Physical - Fine Motor (hand-eye coordination, pencil grip, manipulation, self-care)
"""

from datetime import date, datetime
from enum import Enum
from typing import Any, Optional
from uuid import UUID

from pydantic import BaseModel, Field

from app.schemas.base import BaseResponse, BaseSchema, PaginatedResponse


class DevelopmentalDomain(str, Enum):
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


class SkillStatus(str, Enum):
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


class ObserverType(str, Enum):
    """Types of observers who can document child behavior.

    Attributes:
        EDUCATOR: Daycare educator or teacher
        PARENT: Parent or guardian
        SPECIALIST: Developmental specialist or therapist
    """

    EDUCATOR = "educator"
    PARENT = "parent"
    SPECIALIST = "specialist"


class OverallProgress(str, Enum):
    """Overall developmental progress indicators.

    Attributes:
        ON_TRACK: Development is progressing as expected for age
        NEEDS_SUPPORT: Some areas need additional support
        EXCELLING: Child is exceeding age-appropriate expectations
    """

    ON_TRACK = "on_track"
    NEEDS_SUPPORT = "needs_support"
    EXCELLING = "excelling"


# =============================================================================
# Domain Summary Schema (nested within MonthlySnapshot)
# =============================================================================


class DomainSummary(BaseModel):
    """Summary of developmental progress for a single domain.

    Attributes:
        domain: The developmental domain
        skills_can: Number of skills with 'can' status
        skills_learning: Number of skills with 'learning' status
        skills_not_yet: Number of skills with 'not_yet' status
        progress_percentage: Overall progress percentage for this domain
        key_observations: List of key observations for this domain
    """

    domain: DevelopmentalDomain = Field(
        ...,
        description="The developmental domain",
    )
    skills_can: int = Field(
        default=0,
        ge=0,
        description="Number of skills with 'can' status",
    )
    skills_learning: int = Field(
        default=0,
        ge=0,
        description="Number of skills with 'learning' status",
    )
    skills_not_yet: int = Field(
        default=0,
        ge=0,
        description="Number of skills with 'not_yet' status",
    )
    progress_percentage: float = Field(
        default=0.0,
        ge=0.0,
        le=100.0,
        description="Overall progress percentage for this domain (0-100)",
    )
    key_observations: list[str] = Field(
        default_factory=list,
        description="List of key observations for this domain",
    )


# =============================================================================
# Development Profile Schemas
# =============================================================================


class DevelopmentProfileBase(BaseSchema):
    """Base schema for development profile data.

    Contains common fields shared between request and response schemas.

    Attributes:
        child_id: Unique identifier of the child
        educator_id: Unique identifier of the primary educator
        birth_date: Child's date of birth for age-appropriate expectations
        notes: General notes about the child's development
    """

    child_id: UUID = Field(
        ...,
        description="Unique identifier of the child",
    )
    educator_id: Optional[UUID] = Field(
        default=None,
        description="Unique identifier of the primary educator",
    )
    birth_date: Optional[date] = Field(
        default=None,
        description="Child's date of birth for age-appropriate expectations",
    )
    notes: Optional[str] = Field(
        default=None,
        max_length=5000,
        description="General notes about the child's development",
    )


class DevelopmentProfileRequest(DevelopmentProfileBase):
    """Request schema for creating or updating a development profile.

    Inherits all fields from DevelopmentProfileBase.
    """

    pass


class DevelopmentProfileResponse(DevelopmentProfileBase, BaseResponse):
    """Response schema for development profile data.

    Includes all base profile fields plus ID, timestamps, and relationships.

    Attributes:
        is_active: Whether the profile is currently active
        skill_assessments: List of skill assessments for this profile
        observations: List of observations for this profile
        monthly_snapshots: List of monthly snapshots for this profile
    """

    is_active: bool = Field(
        default=True,
        description="Whether the profile is currently active",
    )
    skill_assessments: list["SkillAssessmentResponse"] = Field(
        default_factory=list,
        description="List of skill assessments for this profile",
    )
    observations: list["ObservationResponse"] = Field(
        default_factory=list,
        description="List of observations for this profile",
    )
    monthly_snapshots: list["MonthlySnapshotResponse"] = Field(
        default_factory=list,
        description="List of monthly snapshots for this profile",
    )


class DevelopmentProfileSummaryResponse(DevelopmentProfileBase, BaseResponse):
    """Summary response schema for development profile data (without nested relations).

    Used for list endpoints where full relationship data is not needed.

    Attributes:
        is_active: Whether the profile is currently active
        assessment_count: Total number of skill assessments
        observation_count: Total number of observations
        snapshot_count: Total number of monthly snapshots
    """

    is_active: bool = Field(
        default=True,
        description="Whether the profile is currently active",
    )
    assessment_count: int = Field(
        default=0,
        ge=0,
        description="Total number of skill assessments",
    )
    observation_count: int = Field(
        default=0,
        ge=0,
        description="Total number of observations",
    )
    snapshot_count: int = Field(
        default=0,
        ge=0,
        description="Total number of monthly snapshots",
    )


class DevelopmentProfileListResponse(PaginatedResponse):
    """Paginated list of development profiles.

    Attributes:
        items: List of development profile summaries
    """

    items: list[DevelopmentProfileSummaryResponse] = Field(
        ...,
        description="List of development profile summaries",
    )


# =============================================================================
# Skill Assessment Schemas
# =============================================================================


class SkillAssessmentBase(BaseSchema):
    """Base schema for skill assessment data.

    Contains common fields shared between request and response schemas.

    Attributes:
        domain: Developmental domain this skill belongs to
        skill_name: Name of the specific skill being assessed
        skill_name_fr: French name of the skill (for bilingual support)
        status: Current assessment status (can/learning/not_yet/na)
        evidence: Observable evidence supporting the assessment
    """

    domain: DevelopmentalDomain = Field(
        ...,
        description="Developmental domain this skill belongs to",
    )
    skill_name: str = Field(
        ...,
        min_length=1,
        max_length=200,
        description="Name of the specific skill being assessed",
    )
    skill_name_fr: Optional[str] = Field(
        default=None,
        max_length=200,
        description="French name of the skill (for bilingual support)",
    )
    status: SkillStatus = Field(
        default=SkillStatus.NOT_YET,
        description="Current assessment status (can/learning/not_yet/na)",
    )
    evidence: Optional[str] = Field(
        default=None,
        max_length=2000,
        description="Observable evidence supporting the assessment",
    )


class SkillAssessmentRequest(SkillAssessmentBase):
    """Request schema for creating or updating a skill assessment.

    Attributes:
        profile_id: Foreign key to the development profile
        assessed_by_id: UUID of the user who made the assessment
    """

    profile_id: UUID = Field(
        ...,
        description="Foreign key to the development profile",
    )
    assessed_by_id: Optional[UUID] = Field(
        default=None,
        description="UUID of the user who made the assessment",
    )


class SkillAssessmentUpdateRequest(BaseSchema):
    """Request schema for updating an existing skill assessment.

    All fields are optional for partial updates.

    Attributes:
        status: Current assessment status (can/learning/not_yet/na)
        evidence: Observable evidence supporting the assessment
        assessed_by_id: UUID of the user who made the assessment
    """

    status: Optional[SkillStatus] = Field(
        default=None,
        description="Current assessment status (can/learning/not_yet/na)",
    )
    evidence: Optional[str] = Field(
        default=None,
        max_length=2000,
        description="Observable evidence supporting the assessment",
    )
    assessed_by_id: Optional[UUID] = Field(
        default=None,
        description="UUID of the user who made the assessment",
    )


class SkillAssessmentResponse(SkillAssessmentBase, BaseResponse):
    """Response schema for skill assessment data.

    Includes all base assessment fields plus ID and timestamps.

    Attributes:
        profile_id: Foreign key to the development profile
        assessed_at: When this skill was last assessed
        assessed_by_id: UUID of the user who made the assessment
    """

    profile_id: UUID = Field(
        ...,
        description="Foreign key to the development profile",
    )
    assessed_at: datetime = Field(
        ...,
        description="When this skill was last assessed",
    )
    assessed_by_id: Optional[UUID] = Field(
        default=None,
        description="UUID of the user who made the assessment",
    )


class SkillAssessmentListResponse(PaginatedResponse):
    """Paginated list of skill assessments.

    Attributes:
        items: List of skill assessments
    """

    items: list[SkillAssessmentResponse] = Field(
        ...,
        description="List of skill assessments",
    )


# =============================================================================
# Observation Schemas
# =============================================================================


class ObservationBase(BaseSchema):
    """Base schema for observation data.

    Contains common fields shared between request and response schemas.

    Attributes:
        domain: Primary developmental domain for this observation
        behavior_description: Detailed description of the observed behavior
        context: Context in which the behavior was observed
        is_milestone: Whether this represents a developmental milestone
        is_concern: Whether this observation raises developmental concerns
    """

    domain: DevelopmentalDomain = Field(
        ...,
        description="Primary developmental domain for this observation",
    )
    behavior_description: str = Field(
        ...,
        min_length=1,
        max_length=5000,
        description="Detailed description of the observed behavior",
    )
    context: Optional[str] = Field(
        default=None,
        max_length=2000,
        description="Context in which the behavior was observed",
    )
    is_milestone: bool = Field(
        default=False,
        description="Whether this represents a developmental milestone",
    )
    is_concern: bool = Field(
        default=False,
        description="Whether this observation raises developmental concerns",
    )


class ObservationRequest(ObservationBase):
    """Request schema for creating an observation.

    Attributes:
        profile_id: Foreign key to the development profile
        observed_at: Date and time when the behavior was observed
        observer_id: UUID of the person who made the observation
        observer_type: Type of observer (educator, parent, specialist)
        attachments: JSON object with attachment references (photos, videos)
    """

    profile_id: UUID = Field(
        ...,
        description="Foreign key to the development profile",
    )
    observed_at: Optional[datetime] = Field(
        default=None,
        description="Date and time when the behavior was observed (defaults to now)",
    )
    observer_id: Optional[UUID] = Field(
        default=None,
        description="UUID of the person who made the observation",
    )
    observer_type: ObserverType = Field(
        default=ObserverType.EDUCATOR,
        description="Type of observer (educator, parent, specialist)",
    )
    attachments: Optional[dict[str, Any]] = Field(
        default=None,
        description="JSON object with attachment references (photos, videos)",
    )


class ObservationUpdateRequest(BaseSchema):
    """Request schema for updating an existing observation.

    All fields are optional for partial updates.

    Attributes:
        behavior_description: Detailed description of the observed behavior
        context: Context in which the behavior was observed
        is_milestone: Whether this represents a developmental milestone
        is_concern: Whether this observation raises developmental concerns
        attachments: JSON object with attachment references (photos, videos)
    """

    behavior_description: Optional[str] = Field(
        default=None,
        max_length=5000,
        description="Detailed description of the observed behavior",
    )
    context: Optional[str] = Field(
        default=None,
        max_length=2000,
        description="Context in which the behavior was observed",
    )
    is_milestone: Optional[bool] = Field(
        default=None,
        description="Whether this represents a developmental milestone",
    )
    is_concern: Optional[bool] = Field(
        default=None,
        description="Whether this observation raises developmental concerns",
    )
    attachments: Optional[dict[str, Any]] = Field(
        default=None,
        description="JSON object with attachment references (photos, videos)",
    )


class ObservationResponse(ObservationBase, BaseResponse):
    """Response schema for observation data.

    Includes all base observation fields plus ID and timestamps.

    Attributes:
        profile_id: Foreign key to the development profile
        observed_at: Date and time when the behavior was observed
        observer_id: UUID of the person who made the observation
        observer_type: Type of observer (educator, parent, specialist)
        attachments: JSON object with attachment references (photos, videos)
    """

    profile_id: UUID = Field(
        ...,
        description="Foreign key to the development profile",
    )
    observed_at: datetime = Field(
        ...,
        description="Date and time when the behavior was observed",
    )
    observer_id: Optional[UUID] = Field(
        default=None,
        description="UUID of the person who made the observation",
    )
    observer_type: str = Field(
        ...,
        description="Type of observer (educator, parent, specialist)",
    )
    attachments: Optional[dict[str, Any]] = Field(
        default=None,
        description="JSON object with attachment references (photos, videos)",
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
# Monthly Snapshot Schemas
# =============================================================================


class MonthlySnapshotBase(BaseSchema):
    """Base schema for monthly snapshot data.

    Contains common fields shared between request and response schemas.

    Attributes:
        snapshot_month: The month this snapshot represents (first day of month)
        age_months: Child's age in months at time of snapshot
        overall_progress: Overall progress indicator (on_track, needs_support, excelling)
        recommendations: Recommendations for next month
    """

    snapshot_month: date = Field(
        ...,
        description="The month this snapshot represents (first day of month)",
    )
    age_months: Optional[int] = Field(
        default=None,
        ge=0,
        le=144,
        description="Child's age in months at time of snapshot (0-144)",
    )
    overall_progress: OverallProgress = Field(
        default=OverallProgress.ON_TRACK,
        description="Overall progress indicator (on_track, needs_support, excelling)",
    )
    recommendations: Optional[str] = Field(
        default=None,
        max_length=5000,
        description="Recommendations for next month",
    )


class MonthlySnapshotRequest(MonthlySnapshotBase):
    """Request schema for creating a monthly snapshot.

    Attributes:
        profile_id: Foreign key to the development profile
        domain_summaries: JSON object with summary per domain
        strengths: List of identified strengths
        growth_areas: List of areas needing growth/support
        generated_by_id: UUID of the user who generated/approved this snapshot
    """

    profile_id: UUID = Field(
        ...,
        description="Foreign key to the development profile",
    )
    domain_summaries: Optional[dict[str, Any]] = Field(
        default=None,
        description="JSON object with summary per domain",
    )
    strengths: Optional[list[str]] = Field(
        default=None,
        description="List of identified strengths",
    )
    growth_areas: Optional[list[str]] = Field(
        default=None,
        description="List of areas needing growth/support",
    )
    generated_by_id: Optional[UUID] = Field(
        default=None,
        description="UUID of the user who generated/approved this snapshot",
    )


class MonthlySnapshotUpdateRequest(BaseSchema):
    """Request schema for updating an existing monthly snapshot.

    All fields are optional for partial updates.

    Attributes:
        overall_progress: Overall progress indicator (on_track, needs_support, excelling)
        recommendations: Recommendations for next month
        strengths: List of identified strengths
        growth_areas: List of areas needing growth/support
        is_parent_shared: Whether this snapshot has been shared with parents
    """

    overall_progress: Optional[OverallProgress] = Field(
        default=None,
        description="Overall progress indicator (on_track, needs_support, excelling)",
    )
    recommendations: Optional[str] = Field(
        default=None,
        max_length=5000,
        description="Recommendations for next month",
    )
    strengths: Optional[list[str]] = Field(
        default=None,
        description="List of identified strengths",
    )
    growth_areas: Optional[list[str]] = Field(
        default=None,
        description="List of areas needing growth/support",
    )
    is_parent_shared: Optional[bool] = Field(
        default=None,
        description="Whether this snapshot has been shared with parents",
    )


class MonthlySnapshotResponse(MonthlySnapshotBase, BaseResponse):
    """Response schema for monthly snapshot data.

    Includes all base snapshot fields plus ID and timestamps.

    Attributes:
        profile_id: Foreign key to the development profile
        domain_summaries: JSON object with summary per domain
        strengths: List of identified strengths
        growth_areas: List of areas needing growth/support
        generated_by_id: UUID of the user who generated/approved this snapshot
        is_parent_shared: Whether this snapshot has been shared with parents
    """

    profile_id: UUID = Field(
        ...,
        description="Foreign key to the development profile",
    )
    domain_summaries: Optional[dict[str, Any]] = Field(
        default=None,
        description="JSON object with summary per domain",
    )
    strengths: Optional[list[str]] = Field(
        default=None,
        description="List of identified strengths",
    )
    growth_areas: Optional[list[str]] = Field(
        default=None,
        description="List of areas needing growth/support",
    )
    generated_by_id: Optional[UUID] = Field(
        default=None,
        description="UUID of the user who generated/approved this snapshot",
    )
    is_parent_shared: bool = Field(
        default=False,
        description="Whether this snapshot has been shared with parents",
    )


class MonthlySnapshotListResponse(PaginatedResponse):
    """Paginated list of monthly snapshots.

    Attributes:
        items: List of monthly snapshots
    """

    items: list[MonthlySnapshotResponse] = Field(
        ...,
        description="List of monthly snapshots",
    )


# =============================================================================
# Growth Trajectory Schemas
# =============================================================================


class GrowthDataPoint(BaseModel):
    """A single data point in the growth trajectory.

    Attributes:
        month: The month for this data point
        age_months: Child's age in months
        domain_scores: Progress scores per domain (0-100)
        overall_score: Overall progress score (0-100)
    """

    month: date = Field(
        ...,
        description="The month for this data point",
    )
    age_months: Optional[int] = Field(
        default=None,
        ge=0,
        le=144,
        description="Child's age in months",
    )
    domain_scores: dict[str, float] = Field(
        default_factory=dict,
        description="Progress scores per domain (0-100)",
    )
    overall_score: float = Field(
        default=0.0,
        ge=0.0,
        le=100.0,
        description="Overall progress score (0-100)",
    )


class GrowthTrajectoryRequest(BaseSchema):
    """Request schema for getting growth trajectory data.

    Attributes:
        profile_id: Unique identifier of the development profile
        start_month: Start month for trajectory data (optional)
        end_month: End month for trajectory data (optional)
        domains: Optional filter for specific domains
    """

    profile_id: UUID = Field(
        ...,
        description="Unique identifier of the development profile",
    )
    start_month: Optional[date] = Field(
        default=None,
        description="Start month for trajectory data (optional)",
    )
    end_month: Optional[date] = Field(
        default=None,
        description="End month for trajectory data (optional)",
    )
    domains: Optional[list[DevelopmentalDomain]] = Field(
        default=None,
        description="Optional filter for specific domains",
    )


class GrowthTrajectoryResponse(BaseSchema):
    """Response schema for growth trajectory data.

    Attributes:
        profile_id: Unique identifier of the development profile
        child_id: Unique identifier of the child
        data_points: List of growth data points over time
        trend_analysis: AI-generated trend analysis and insights
        alerts: List of areas needing attention based on age expectations
    """

    profile_id: UUID = Field(
        ...,
        description="Unique identifier of the development profile",
    )
    child_id: UUID = Field(
        ...,
        description="Unique identifier of the child",
    )
    data_points: list[GrowthDataPoint] = Field(
        default_factory=list,
        description="List of growth data points over time",
    )
    trend_analysis: Optional[str] = Field(
        default=None,
        max_length=5000,
        description="AI-generated trend analysis and insights",
    )
    alerts: list[str] = Field(
        default_factory=list,
        description="List of areas needing attention based on age expectations",
    )


# =============================================================================
# Forward References Resolution
# =============================================================================


# Update forward references for nested models
DevelopmentProfileResponse.model_rebuild()
