"""Analytics domain schemas for LAYA AI Service.

Defines Pydantic schemas for business intelligence dashboard, KPI metrics,
enrollment forecasting, and Quebec regulatory compliance monitoring.
These schemas support data-driven decision-making for childcare facility
management.
"""

from datetime import date, datetime
from decimal import Decimal
from enum import Enum
from typing import Any, Optional
from uuid import UUID

from pydantic import Field

from app.schemas.base import BaseResponse, BaseSchema


class MetricCategory(str, Enum):
    """Categories of analytics metrics.

    Attributes:
        ENROLLMENT: Enrollment-related metrics
        ATTENDANCE: Attendance tracking metrics
        REVENUE: Financial and revenue metrics
        STAFFING: Staff-to-child ratio metrics
    """

    ENROLLMENT = "enrollment"
    ATTENDANCE = "attendance"
    REVENUE = "revenue"
    STAFFING = "staffing"


class ComplianceStatus(str, Enum):
    """Status levels for compliance checks.

    Attributes:
        COMPLIANT: Fully compliant with regulations
        WARNING: Minor issues requiring attention
        VIOLATION: Serious compliance violation
        UNKNOWN: Status cannot be determined
    """

    COMPLIANT = "compliant"
    WARNING = "warning"
    VIOLATION = "violation"
    UNKNOWN = "unknown"


class ComplianceCheckType(str, Enum):
    """Types of compliance checks performed.

    Attributes:
        STAFF_RATIO: Staff-to-child ratio compliance
        CERTIFICATION: Staff certification requirements
        CAPACITY: Facility capacity limits
        SAFETY: Safety inspection status
    """

    STAFF_RATIO = "staff_ratio"
    CERTIFICATION = "certification"
    CAPACITY = "capacity"
    SAFETY = "safety"


class KPIMetric(BaseSchema):
    """A single KPI metric with value and metadata.

    Represents a key performance indicator with its current value,
    historical comparison, and category classification.

    Attributes:
        metric_name: Name of the KPI metric
        metric_value: Current value of the metric
        metric_unit: Unit of measurement (e.g., %, count, CAD)
        category: Category this metric belongs to
        period_start: Start of the measurement period
        period_end: End of the measurement period
        previous_value: Value from the previous period for comparison
        change_percentage: Percentage change from previous period
        facility_id: Optional facility identifier for facility-specific metrics
    """

    metric_name: str = Field(
        ...,
        min_length=1,
        max_length=100,
        description="Name of the KPI metric",
    )
    metric_value: Decimal = Field(
        ...,
        description="Current value of the metric",
    )
    metric_unit: Optional[str] = Field(
        default=None,
        max_length=50,
        description="Unit of measurement (e.g., %, count, CAD)",
    )
    category: MetricCategory = Field(
        ...,
        description="Category this metric belongs to",
    )
    period_start: datetime = Field(
        ...,
        description="Start of the measurement period",
    )
    period_end: datetime = Field(
        ...,
        description="End of the measurement period",
    )
    previous_value: Optional[Decimal] = Field(
        default=None,
        description="Value from the previous period for comparison",
    )
    change_percentage: Optional[float] = Field(
        default=None,
        ge=-100.0,
        description="Percentage change from previous period",
    )
    facility_id: Optional[UUID] = Field(
        default=None,
        description="Optional facility identifier for facility-specific metrics",
    )


class KPIMetricResponse(KPIMetric, BaseResponse):
    """Response schema for a KPI metric with ID and timestamps.

    Includes all KPI metric fields plus database record metadata.
    """

    pass


class KPIMetricsListResponse(BaseSchema):
    """Response schema for a list of KPI metrics.

    Attributes:
        metrics: List of KPI metrics
        generated_at: When the metrics were calculated
    """

    metrics: list[KPIMetric] = Field(
        ...,
        description="List of KPI metrics",
    )
    generated_at: datetime = Field(
        ...,
        description="When the metrics were calculated",
    )


class ForecastDataPoint(BaseSchema):
    """A single data point in the forecast time series.

    Attributes:
        forecast_date: Date for this forecast point
        predicted_enrollment: Predicted enrollment count
        confidence_lower: Lower bound of confidence interval
        confidence_upper: Upper bound of confidence interval
        is_historical: Whether this is historical data (not a prediction)
    """

    forecast_date: date = Field(
        ...,
        description="Date for this forecast point",
    )
    predicted_enrollment: int = Field(
        ...,
        ge=0,
        description="Predicted enrollment count",
    )
    confidence_lower: Optional[int] = Field(
        default=None,
        ge=0,
        description="Lower bound of confidence interval",
    )
    confidence_upper: Optional[int] = Field(
        default=None,
        ge=0,
        description="Upper bound of confidence interval",
    )
    is_historical: bool = Field(
        default=False,
        description="Whether this is historical data (not a prediction)",
    )


class ForecastData(BaseSchema):
    """Enrollment forecast data with historical context.

    Contains both historical enrollment data and future predictions
    with confidence intervals.

    Attributes:
        facility_id: Optional facility identifier
        historical: Historical enrollment data points
        forecast: Predicted future enrollment data points
        model_version: Version of the forecasting model used
        generated_at: When the forecast was generated
        confidence_note: Note about forecast confidence
    """

    facility_id: Optional[UUID] = Field(
        default=None,
        description="Optional facility identifier",
    )
    historical: list[ForecastDataPoint] = Field(
        default_factory=list,
        description="Historical enrollment data points",
    )
    forecast: list[ForecastDataPoint] = Field(
        default_factory=list,
        description="Predicted future enrollment data points",
    )
    model_version: str = Field(
        default="v1",
        max_length=50,
        description="Version of the forecasting model used",
    )
    generated_at: datetime = Field(
        ...,
        description="When the forecast was generated",
    )
    confidence_note: Optional[str] = Field(
        default=None,
        max_length=500,
        description="Note about forecast confidence (e.g., limited historical data)",
    )


class ComplianceCheckResponse(BaseSchema):
    """Response schema for a compliance check result.

    Represents the status of a single compliance area with details
    and recommendations.

    Attributes:
        check_type: Type of compliance check performed
        status: Current compliance status
        details: Detailed information about the compliance check
        checked_at: When the check was performed
        next_check_due: When the next check is due
        facility_id: Optional facility identifier
        recommendation: Recommended action if not compliant
    """

    check_type: ComplianceCheckType = Field(
        ...,
        description="Type of compliance check performed",
    )
    status: ComplianceStatus = Field(
        ...,
        description="Current compliance status",
    )
    details: Optional[dict[str, Any]] = Field(
        default=None,
        description="Detailed information about the compliance check",
    )
    checked_at: datetime = Field(
        ...,
        description="When the check was performed",
    )
    next_check_due: Optional[datetime] = Field(
        default=None,
        description="When the next check is due",
    )
    facility_id: Optional[UUID] = Field(
        default=None,
        description="Optional facility identifier",
    )
    recommendation: Optional[str] = Field(
        default=None,
        max_length=1000,
        description="Recommended action if not compliant",
    )


class ComplianceCheckWithID(ComplianceCheckResponse, BaseResponse):
    """Compliance check response with database ID and timestamps.

    Includes all compliance check fields plus database record metadata.
    """

    pass


class ComplianceListResponse(BaseSchema):
    """Response schema for a list of compliance checks.

    Attributes:
        checks: List of compliance check results
        overall_status: Overall compliance status across all checks
        generated_at: When the compliance report was generated
    """

    checks: list[ComplianceCheckResponse] = Field(
        ...,
        description="List of compliance check results",
    )
    overall_status: ComplianceStatus = Field(
        ...,
        description="Overall compliance status across all checks",
    )
    generated_at: datetime = Field(
        ...,
        description="When the compliance report was generated",
    )


class DashboardSummary(BaseSchema):
    """Summary metrics for the dashboard overview.

    Attributes:
        total_enrolled: Total number of enrolled children
        total_capacity: Total facility capacity
        enrollment_rate: Enrollment rate as percentage
        average_attendance: Average daily attendance percentage
        compliance_score: Overall compliance score (0-100)
    """

    total_enrolled: int = Field(
        ...,
        ge=0,
        description="Total number of enrolled children",
    )
    total_capacity: int = Field(
        ...,
        ge=0,
        description="Total facility capacity",
    )
    enrollment_rate: float = Field(
        ...,
        ge=0.0,
        le=100.0,
        description="Enrollment rate as percentage",
    )
    average_attendance: float = Field(
        ...,
        ge=0.0,
        le=100.0,
        description="Average daily attendance percentage",
    )
    compliance_score: float = Field(
        ...,
        ge=0.0,
        le=100.0,
        description="Overall compliance score (0-100)",
    )


class DashboardResponse(BaseSchema):
    """Aggregated dashboard response with all key metrics.

    Provides a comprehensive view for facility directors including
    KPIs, enrollment forecast summary, and compliance alerts.

    Attributes:
        summary: High-level summary metrics
        kpis: List of key performance indicators
        forecast_summary: Summary of enrollment forecast
        compliance_summary: Summary of compliance status
        alerts: List of items requiring attention
        generated_at: When the dashboard data was generated
    """

    summary: DashboardSummary = Field(
        ...,
        description="High-level summary metrics",
    )
    kpis: list[KPIMetric] = Field(
        ...,
        description="List of key performance indicators",
    )
    forecast_summary: ForecastData = Field(
        ...,
        description="Summary of enrollment forecast",
    )
    compliance_summary: ComplianceListResponse = Field(
        ...,
        description="Summary of compliance status",
    )
    alerts: list[str] = Field(
        default_factory=list,
        description="List of items requiring attention",
    )
    generated_at: datetime = Field(
        ...,
        description="When the dashboard data was generated",
    )
