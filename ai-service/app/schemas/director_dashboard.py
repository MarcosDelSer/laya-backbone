"""Director Dashboard schemas for LAYA AI Service.

Defines Pydantic schemas for the real-time director dashboard including
occupancy monitoring, group statistics, and facility-wide summary data.
These schemas support Quebec childcare compliance and operational oversight.
"""

from datetime import datetime
from enum import Enum
from typing import Optional
from uuid import UUID

from pydantic import Field

from app.schemas.base import BaseResponse, BaseSchema


class AgeGroupType(str, Enum):
    """Age group classifications for Quebec childcare.

    Based on Quebec Ministère de la Famille regulations for
    staff-to-child ratios and group compositions.

    Attributes:
        POUPON: Infants (0-18 months)
        BAMBIN: Toddlers (18-36 months)
        PRESCOLAIRE: Preschoolers (3-5 years)
        SCOLAIRE: School-age (5+ years)
        MIXED: Mixed age group
    """

    POUPON = "poupon"
    BAMBIN = "bambin"
    PRESCOLAIRE = "prescolaire"
    SCOLAIRE = "scolaire"
    MIXED = "mixed"


class OccupancyStatus(str, Enum):
    """Status levels for occupancy monitoring.

    Attributes:
        NORMAL: Occupancy is within normal range
        NEAR_CAPACITY: Approaching maximum capacity
        AT_CAPACITY: At maximum capacity
        OVER_CAPACITY: Exceeds maximum capacity (compliance issue)
        EMPTY: No children present
    """

    NORMAL = "normal"
    NEAR_CAPACITY = "near_capacity"
    AT_CAPACITY = "at_capacity"
    OVER_CAPACITY = "over_capacity"
    EMPTY = "empty"


class AlertPriority(str, Enum):
    """Priority levels for dashboard alerts.

    Attributes:
        LOW: Informational alert
        MEDIUM: Requires attention
        HIGH: Urgent action needed
        CRITICAL: Immediate action required
    """

    LOW = "low"
    MEDIUM = "medium"
    HIGH = "high"
    CRITICAL = "critical"


class AlertType(str, Enum):
    """Types of alerts on the director dashboard.

    Attributes:
        OCCUPANCY: Related to occupancy thresholds
        STAFFING: Related to staff-to-child ratios
        COMPLIANCE: Regulatory compliance issues
        ATTENDANCE: Attendance-related notifications
        GENERAL: General facility alerts
    """

    OCCUPANCY = "occupancy"
    STAFFING = "staffing"
    COMPLIANCE = "compliance"
    ATTENDANCE = "attendance"
    GENERAL = "general"


class GroupOccupancy(BaseSchema):
    """Occupancy data for a single group/classroom.

    Represents real-time occupancy status for an individual group
    including current count, capacity, and compliance status.

    Attributes:
        group_id: Unique identifier for the group
        group_name: Display name of the group
        age_group: Age classification for the group
        current_count: Number of children currently present
        capacity: Maximum allowed children in the group
        occupancy_percentage: Current occupancy as percentage
        status: Occupancy status indicator
        staff_count: Number of staff currently assigned
        staff_ratio: Current staff-to-child ratio
        room_number: Physical room identifier
        last_updated: When the occupancy was last updated
    """

    group_id: UUID = Field(
        ...,
        description="Unique identifier for the group",
    )
    group_name: str = Field(
        ...,
        min_length=1,
        max_length=100,
        description="Display name of the group",
    )
    age_group: AgeGroupType = Field(
        ...,
        description="Age classification for the group",
    )
    current_count: int = Field(
        ...,
        ge=0,
        description="Number of children currently present",
    )
    capacity: int = Field(
        ...,
        ge=0,
        description="Maximum allowed children in the group",
    )
    occupancy_percentage: float = Field(
        ...,
        ge=0.0,
        le=200.0,
        description="Current occupancy as percentage (can exceed 100% if over capacity)",
    )
    status: OccupancyStatus = Field(
        ...,
        description="Occupancy status indicator",
    )
    staff_count: Optional[int] = Field(
        default=None,
        ge=0,
        description="Number of staff currently assigned",
    )
    staff_ratio: Optional[str] = Field(
        default=None,
        max_length=20,
        description="Current staff-to-child ratio (e.g., '1:5')",
    )
    room_number: Optional[str] = Field(
        default=None,
        max_length=20,
        description="Physical room identifier",
    )
    last_updated: datetime = Field(
        ...,
        description="When the occupancy was last updated",
    )


class GroupOccupancyResponse(GroupOccupancy, BaseResponse):
    """Response schema for group occupancy with ID and timestamps.

    Includes all group occupancy fields plus database record metadata.
    """

    pass


class OccupancySummary(BaseSchema):
    """High-level summary of facility-wide occupancy.

    Provides aggregate occupancy metrics across all groups
    for quick director overview.

    Attributes:
        facility_id: Identifier for the facility
        total_children: Total number of children currently present
        total_capacity: Total facility capacity across all groups
        overall_occupancy_percentage: Facility-wide occupancy percentage
        groups_at_capacity: Number of groups at or over capacity
        groups_near_capacity: Number of groups approaching capacity
        total_groups: Total number of active groups
        average_staff_ratio: Average staff-to-child ratio across facility
        snapshot_time: When this summary was calculated
    """

    facility_id: Optional[UUID] = Field(
        default=None,
        description="Identifier for the facility",
    )
    total_children: int = Field(
        ...,
        ge=0,
        description="Total number of children currently present",
    )
    total_capacity: int = Field(
        ...,
        ge=0,
        description="Total facility capacity across all groups",
    )
    overall_occupancy_percentage: float = Field(
        ...,
        ge=0.0,
        le=200.0,
        description="Facility-wide occupancy percentage",
    )
    groups_at_capacity: int = Field(
        ...,
        ge=0,
        description="Number of groups at or over capacity",
    )
    groups_near_capacity: int = Field(
        ...,
        ge=0,
        description="Number of groups approaching capacity (>80%)",
    )
    total_groups: int = Field(
        ...,
        ge=0,
        description="Total number of active groups",
    )
    average_staff_ratio: Optional[str] = Field(
        default=None,
        max_length=20,
        description="Average staff-to-child ratio across facility",
    )
    snapshot_time: datetime = Field(
        ...,
        description="When this summary was calculated",
    )


class DashboardAlert(BaseSchema):
    """An alert item for the director dashboard.

    Represents an actionable notification requiring director attention.

    Attributes:
        alert_id: Unique identifier for the alert
        alert_type: Category of the alert
        priority: Urgency level of the alert
        title: Brief title for the alert
        message: Detailed alert message
        group_id: Optional associated group ID
        group_name: Optional associated group name
        created_at: When the alert was generated
        is_acknowledged: Whether the alert has been acknowledged
    """

    alert_id: UUID = Field(
        ...,
        description="Unique identifier for the alert",
    )
    alert_type: AlertType = Field(
        ...,
        description="Category of the alert",
    )
    priority: AlertPriority = Field(
        ...,
        description="Urgency level of the alert",
    )
    title: str = Field(
        ...,
        min_length=1,
        max_length=200,
        description="Brief title for the alert",
    )
    message: str = Field(
        ...,
        min_length=1,
        max_length=1000,
        description="Detailed alert message",
    )
    group_id: Optional[UUID] = Field(
        default=None,
        description="Optional associated group ID",
    )
    group_name: Optional[str] = Field(
        default=None,
        max_length=100,
        description="Optional associated group name",
    )
    created_at: datetime = Field(
        ...,
        description="When the alert was generated",
    )
    is_acknowledged: bool = Field(
        default=False,
        description="Whether the alert has been acknowledged",
    )


class OccupancyHistoryPoint(BaseSchema):
    """A single point in occupancy history time series.

    Represents historical occupancy data for trend analysis.

    Attributes:
        timestamp: When this data point was recorded
        total_count: Total children present at this time
        capacity: Total capacity at this time
        occupancy_percentage: Occupancy percentage at this time
    """

    timestamp: datetime = Field(
        ...,
        description="When this data point was recorded",
    )
    total_count: int = Field(
        ...,
        ge=0,
        description="Total children present at this time",
    )
    capacity: int = Field(
        ...,
        ge=0,
        description="Total capacity at this time",
    )
    occupancy_percentage: float = Field(
        ...,
        ge=0.0,
        le=200.0,
        description="Occupancy percentage at this time",
    )


class OccupancyHistoryResponse(BaseSchema):
    """Response schema for occupancy history data.

    Contains time series data for occupancy trend visualization.

    Attributes:
        facility_id: Identifier for the facility
        data_points: List of historical occupancy points
        period_start: Start of the history period
        period_end: End of the history period
        generated_at: When this history was generated
    """

    facility_id: Optional[UUID] = Field(
        default=None,
        description="Identifier for the facility",
    )
    data_points: list[OccupancyHistoryPoint] = Field(
        default_factory=list,
        description="List of historical occupancy points",
    )
    period_start: datetime = Field(
        ...,
        description="Start of the history period",
    )
    period_end: datetime = Field(
        ...,
        description="End of the history period",
    )
    generated_at: datetime = Field(
        ...,
        description="When this history was generated",
    )


class DirectorDashboardResponse(BaseSchema):
    """Complete director dashboard response with all metrics.

    Aggregates occupancy summary, group details, alerts, and
    real-time statistics for the director's operational overview.

    Attributes:
        summary: High-level facility occupancy summary
        groups: List of individual group occupancy data
        alerts: List of active alerts requiring attention
        alert_count_by_priority: Count of alerts by priority level
        generated_at: When the dashboard data was generated
    """

    summary: OccupancySummary = Field(
        ...,
        description="High-level facility occupancy summary",
    )
    groups: list[GroupOccupancy] = Field(
        default_factory=list,
        description="List of individual group occupancy data",
    )
    alerts: list[DashboardAlert] = Field(
        default_factory=list,
        description="List of active alerts requiring attention",
    )
    alert_count_by_priority: dict[str, int] = Field(
        default_factory=dict,
        description="Count of alerts by priority level (low, medium, high, critical)",
    )
    generated_at: datetime = Field(
        ...,
        description="When the dashboard data was generated",
    )
