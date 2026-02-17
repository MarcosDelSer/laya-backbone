"""Activity domain schemas for LAYA AI Service.

Defines Pydantic schemas for activity recommendation requests and responses.
Activities represent educational activities that can be recommended to
children based on their developmental needs, interests, and special needs.
"""

from datetime import datetime
from enum import Enum
from typing import Optional
from uuid import UUID

from pydantic import BaseModel, Field

from app.schemas.base import BaseResponse, BaseSchema
from app.schemas.pagination import PaginatedResponse


class ActivityType(str, Enum):
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


class ActivityDifficulty(str, Enum):
    """Difficulty levels for activities.

    Attributes:
        EASY: Simple activities for beginners
        MEDIUM: Moderate difficulty activities
        HARD: Challenging activities for advanced learners
    """

    EASY = "easy"
    MEDIUM = "medium"
    HARD = "hard"


class AgeRange(BaseModel):
    """Age range specification for activity targeting.

    Attributes:
        min_months: Minimum age in months
        max_months: Maximum age in months
    """

    min_months: int = Field(
        ...,
        ge=0,
        le=144,
        description="Minimum age in months (0-144)",
    )
    max_months: int = Field(
        ...,
        ge=0,
        le=144,
        description="Maximum age in months (0-144)",
    )


class ActivityBase(BaseSchema):
    """Base schema for activity data.

    Contains common fields shared between request and response schemas.

    Attributes:
        name: Name of the activity
        description: Detailed description of the activity
        activity_type: Type/category of the activity
        difficulty: Difficulty level of the activity
        duration_minutes: Estimated duration in minutes
        materials_needed: List of materials required
        age_range: Target age range for the activity
        special_needs_adaptations: Adaptations for children with special needs
    """

    name: str = Field(
        ...,
        min_length=1,
        max_length=200,
        description="Name of the activity",
    )
    description: str = Field(
        ...,
        min_length=1,
        max_length=2000,
        description="Detailed description of the activity",
    )
    activity_type: ActivityType = Field(
        ...,
        description="Type/category of the activity",
    )
    difficulty: ActivityDifficulty = Field(
        default=ActivityDifficulty.MEDIUM,
        description="Difficulty level of the activity",
    )
    duration_minutes: int = Field(
        default=30,
        ge=5,
        le=180,
        description="Estimated duration in minutes",
    )
    materials_needed: list[str] = Field(
        default_factory=list,
        description="List of materials required for the activity",
    )
    age_range: Optional[AgeRange] = Field(
        default=None,
        description="Target age range for the activity",
    )
    special_needs_adaptations: Optional[str] = Field(
        default=None,
        max_length=2000,
        description="Adaptations for children with special needs",
    )


class ActivityRequest(ActivityBase):
    """Request schema for creating or updating an activity.

    Inherits all fields from ActivityBase.
    """

    pass


class ActivityResponse(ActivityBase, BaseResponse):
    """Response schema for activity data.

    Includes all base activity fields plus ID and timestamps.

    Attributes:
        is_active: Whether the activity is currently active
    """

    is_active: bool = Field(
        default=True,
        description="Whether the activity is currently active",
    )


class ActivityRecommendationRequest(BaseSchema):
    """Request schema for getting activity recommendations.

    Used to request personalized activity recommendations based on
    child profile and preferences.

    Attributes:
        child_id: Unique identifier of the child
        activity_types: Optional filter for specific activity types
        max_recommendations: Maximum number of recommendations to return
        include_special_needs: Whether to include special needs adaptations
    """

    child_id: UUID = Field(
        ...,
        description="Unique identifier of the child",
    )
    activity_types: Optional[list[ActivityType]] = Field(
        default=None,
        description="Optional filter for specific activity types",
    )
    max_recommendations: int = Field(
        default=5,
        ge=1,
        le=20,
        description="Maximum number of recommendations to return",
    )
    include_special_needs: bool = Field(
        default=True,
        description="Whether to include special needs adaptations",
    )


class ActivityRecommendation(BaseSchema):
    """A single activity recommendation with relevance score.

    Attributes:
        activity: The recommended activity
        relevance_score: How relevant this activity is (0-1)
        reasoning: Explanation of why this activity was recommended
    """

    activity: ActivityResponse = Field(
        ...,
        description="The recommended activity",
    )
    relevance_score: float = Field(
        ...,
        ge=0.0,
        le=1.0,
        description="Relevance score between 0 and 1",
    )
    reasoning: Optional[str] = Field(
        default=None,
        max_length=500,
        description="Explanation of why this activity was recommended",
    )


class ActivityRecommendationResponse(BaseSchema):
    """Response schema for activity recommendations.

    Contains a list of recommended activities with scores.

    Attributes:
        child_id: The child these recommendations are for
        recommendations: List of activity recommendations
        generated_at: When the recommendations were generated
    """

    child_id: UUID = Field(
        ...,
        description="The child these recommendations are for",
    )
    recommendations: list[ActivityRecommendation] = Field(
        ...,
        description="List of activity recommendations",
    )
    generated_at: datetime = Field(
        ...,
        description="When the recommendations were generated",
    )


class ActivityListResponse(PaginatedResponse[ActivityResponse]):
    """Paginated list of activities.

    Provides standardized pagination metadata with activity items.

    Attributes:
        items: List of activities
        total: Total number of activities matching the query
        page: Current page number (1-indexed)
        per_page: Number of items per page
        total_pages: Total number of pages
    """

    pass
