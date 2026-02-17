"""Message Quality Analytics domain schemas for LAYA AI Service.

Defines Pydantic schemas for message quality analytics, metrics, and
educator performance tracking for directors (admins only).
"""

from datetime import datetime
from decimal import Decimal
from enum import Enum
from typing import Optional
from uuid import UUID

from pydantic import Field

from app.schemas.base import BaseSchema


class IssueType(str, Enum):
    """Types of message quality issues tracked in analytics.

    Attributes:
        ACCUSATORY_LANGUAGE: Accusatory 'you' language detected
        JUDGMENTAL_LABELS: Judgmental labels identified
        BLAME_SHAME: Blame/shame patterns
        EXAGGERATIONS: Exaggerations ('always', 'never')
        ALARMIST_LANGUAGE: Alarmist language
        INAPPROPRIATE_COMPARISONS: Inappropriate comparisons to other children
    """

    ACCUSATORY_LANGUAGE = "accusatory_language"
    JUDGMENTAL_LABELS = "judgmental_labels"
    BLAME_SHAME = "blame_shame"
    EXAGGERATIONS = "exaggerations"
    ALARMIST_LANGUAGE = "alarmist_language"
    INAPPROPRIATE_COMPARISONS = "inappropriate_comparisons"


class IssueDistribution(BaseSchema):
    """Distribution of a specific issue type across all messages.

    Attributes:
        issue_type: Type of quality issue
        count: Number of messages with this issue
        percentage: Percentage of total messages with this issue
    """

    issue_type: IssueType = Field(
        ...,
        description="Type of quality issue",
    )
    count: int = Field(
        ...,
        ge=0,
        description="Number of messages with this issue",
    )
    percentage: float = Field(
        ...,
        ge=0.0,
        le=100.0,
        description="Percentage of total messages with this issue",
    )


class EducatorPerformance(BaseSchema):
    """Performance metrics for an individual educator.

    Attributes:
        educator_id: Unique identifier for the educator
        educator_name: Name of the educator
        total_messages: Total number of messages analyzed
        avg_quality_score: Average quality score across all messages
        messages_needing_improvement: Number of messages below acceptable threshold
        improvement_rate: Percentage improvement over time period
    """

    educator_id: UUID = Field(
        ...,
        description="Unique identifier for the educator",
    )
    educator_name: str = Field(
        ...,
        min_length=1,
        max_length=200,
        description="Name of the educator",
    )
    total_messages: int = Field(
        ...,
        ge=0,
        description="Total number of messages analyzed",
    )
    avg_quality_score: Decimal = Field(
        ...,
        ge=0,
        le=100,
        description="Average quality score across all messages",
    )
    messages_needing_improvement: int = Field(
        ...,
        ge=0,
        description="Number of messages below acceptable threshold",
    )
    improvement_rate: Optional[float] = Field(
        default=None,
        description="Percentage improvement over time period",
    )


class MessageQualityAnalyticsResponse(BaseSchema):
    """Analytics response for message quality metrics.

    Provides comprehensive analytics for directors including overall quality metrics,
    issue distribution, and educator performance statistics.

    Attributes:
        total_messages_analyzed: Total number of messages analyzed in period
        avg_quality_score: Average quality score across all messages
        acceptable_rate: Percentage of messages meeting quality standards
        issue_distribution: Distribution of quality issues by type
        educator_performance: Performance metrics by educator
        period_start: Start of the analytics period
        period_end: End of the analytics period
        generated_at: When the analytics were generated
    """

    total_messages_analyzed: int = Field(
        ...,
        ge=0,
        description="Total number of messages analyzed in period",
    )
    avg_quality_score: Decimal = Field(
        ...,
        ge=0,
        le=100,
        description="Average quality score across all messages",
    )
    acceptable_rate: float = Field(
        ...,
        ge=0.0,
        le=100.0,
        description="Percentage of messages meeting quality standards",
    )
    issue_distribution: list[IssueDistribution] = Field(
        default_factory=list,
        description="Distribution of quality issues by type",
    )
    educator_performance: list[EducatorPerformance] = Field(
        default_factory=list,
        description="Performance metrics by educator",
    )
    period_start: Optional[datetime] = Field(
        default=None,
        description="Start of the analytics period",
    )
    period_end: Optional[datetime] = Field(
        default=None,
        description="End of the analytics period",
    )
    generated_at: datetime = Field(
        ...,
        description="When the analytics were generated",
    )
