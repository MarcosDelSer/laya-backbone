"""SQLAlchemy models for Business Intelligence Analytics.

Provides database models for analytics metrics, enrollment forecasts,
and Quebec regulatory compliance monitoring.
"""

from datetime import date, datetime
from decimal import Decimal
from enum import Enum
from typing import Any, Optional
from uuid import UUID, uuid4

from sqlalchemy import (
    Date,
    DateTime,
    Index,
    Integer,
    Numeric,
    String,
    func,
)
from sqlalchemy.dialects.postgresql import JSONB, UUID as PG_UUID
from sqlalchemy.orm import DeclarativeBase, Mapped, mapped_column


class Base(DeclarativeBase):
    """Base class for all SQLAlchemy models in LAYA AI Service.

    Uses SQLAlchemy 2.0 declarative style with mapped_column.
    """

    pass


class MetricCategory(str, Enum):
    """Categories for analytics metrics.

    Attributes:
        ENROLLMENT: Enrollment-related metrics
        ATTENDANCE: Attendance tracking metrics
        REVENUE: Financial/revenue metrics
        STAFFING: Staff-related metrics
    """

    ENROLLMENT = "enrollment"
    ATTENDANCE = "attendance"
    REVENUE = "revenue"
    STAFFING = "staffing"


class ComplianceStatus(str, Enum):
    """Status values for compliance checks.

    Attributes:
        COMPLIANT: Fully compliant with regulations
        WARNING: Minor issues requiring attention
        VIOLATION: Non-compliant with regulations
        UNKNOWN: Status cannot be determined
    """

    COMPLIANT = "compliant"
    WARNING = "warning"
    VIOLATION = "violation"
    UNKNOWN = "unknown"


class ComplianceCheckType(str, Enum):
    """Types of compliance checks.

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


class AnalyticsMetric(Base):
    """Model for storing analytics metrics.

    Stores key performance indicators (KPIs) with historical tracking
    for enrollment, attendance, revenue, and staffing metrics.

    Attributes:
        id: Unique identifier for the metric record
        metric_name: Name/identifier of the metric
        metric_value: Numeric value of the metric
        metric_unit: Unit of measurement (e.g., '%', 'count', 'CAD')
        category: Category classification (enrollment, attendance, revenue, staffing)
        period_start: Start of the measurement period
        period_end: End of the measurement period
        facility_id: Optional facility identifier (NULL for aggregate metrics)
        created_at: Timestamp when the record was created
        updated_at: Timestamp when the record was last updated
    """

    __tablename__ = "analytics_metrics"

    id: Mapped[UUID] = mapped_column(
        PG_UUID(as_uuid=True),
        primary_key=True,
        default=uuid4,
    )
    metric_name: Mapped[str] = mapped_column(
        String(100),
        nullable=False,
        index=True,
    )
    metric_value: Mapped[Decimal] = mapped_column(
        Numeric(15, 4),
        nullable=False,
    )
    metric_unit: Mapped[Optional[str]] = mapped_column(
        String(50),
        nullable=True,
    )
    category: Mapped[str] = mapped_column(
        String(50),
        nullable=False,
        index=True,
    )
    period_start: Mapped[datetime] = mapped_column(
        DateTime(timezone=True),
        nullable=False,
    )
    period_end: Mapped[datetime] = mapped_column(
        DateTime(timezone=True),
        nullable=False,
    )
    facility_id: Mapped[Optional[UUID]] = mapped_column(
        PG_UUID(as_uuid=True),
        nullable=True,
        index=True,
    )
    created_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True),
        server_default=func.now(),
        nullable=False,
    )
    updated_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True),
        server_default=func.now(),
        onupdate=func.now(),
        nullable=False,
    )

    # Table-level indexes for common query patterns
    __table_args__ = (
        # Composite index for time-range queries by category
        Index("ix_analytics_metrics_category_period", "category", "period_start", "period_end"),
        # Composite index for facility-specific time queries
        Index("ix_analytics_metrics_facility_period", "facility_id", "period_start"),
        # Composite index for metric name time-series queries
        Index("ix_analytics_metrics_name_period", "metric_name", "period_start"),
    )

    def __repr__(self) -> str:
        """String representation of the AnalyticsMetric."""
        return (
            f"<AnalyticsMetric(id={self.id}, "
            f"metric_name='{self.metric_name}', "
            f"category='{self.category}')>"
        )


class EnrollmentForecast(Base):
    """Model for storing enrollment forecasts.

    Stores time-series enrollment predictions with confidence intervals
    for capacity planning and resource allocation.

    Attributes:
        id: Unique identifier for the forecast record
        forecast_date: Date for which the forecast applies
        predicted_enrollment: Predicted number of enrolled children
        confidence_lower: Lower bound of confidence interval
        confidence_upper: Upper bound of confidence interval
        model_version: Version of the forecasting model used
        facility_id: Optional facility identifier
        created_at: Timestamp when the forecast was generated
    """

    __tablename__ = "enrollment_forecasts"

    id: Mapped[UUID] = mapped_column(
        PG_UUID(as_uuid=True),
        primary_key=True,
        default=uuid4,
    )
    forecast_date: Mapped[date] = mapped_column(
        Date,
        nullable=False,
        index=True,
    )
    predicted_enrollment: Mapped[int] = mapped_column(
        Integer,
        nullable=False,
    )
    confidence_lower: Mapped[Optional[int]] = mapped_column(
        Integer,
        nullable=True,
    )
    confidence_upper: Mapped[Optional[int]] = mapped_column(
        Integer,
        nullable=True,
    )
    model_version: Mapped[str] = mapped_column(
        String(50),
        default="v1",
        nullable=False,
    )
    facility_id: Mapped[Optional[UUID]] = mapped_column(
        PG_UUID(as_uuid=True),
        nullable=True,
        index=True,
    )
    created_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True),
        server_default=func.now(),
        nullable=False,
    )

    # Table-level indexes for common query patterns
    __table_args__ = (
        # Composite index for facility-specific forecasts
        Index("ix_enrollment_forecasts_facility_date", "facility_id", "forecast_date"),
        # Index for model version filtering
        Index("ix_enrollment_forecasts_model_version", "model_version"),
    )

    def __repr__(self) -> str:
        """String representation of the EnrollmentForecast."""
        return (
            f"<EnrollmentForecast(id={self.id}, "
            f"forecast_date={self.forecast_date}, "
            f"predicted_enrollment={self.predicted_enrollment})>"
        )


class ComplianceCheck(Base):
    """Model for storing Quebec regulatory compliance checks.

    Tracks compliance status for staff ratios, certifications,
    facility capacity, and safety inspections as required by
    the Ministère de la Famille.

    Attributes:
        id: Unique identifier for the compliance check
        check_type: Type of compliance check performed
        status: Current compliance status
        details: Additional details stored as JSON
        checked_at: Timestamp when the check was performed
        next_check_due: When the next compliance check is due
        facility_id: Optional facility identifier
        created_at: Timestamp when the record was created
        updated_at: Timestamp when the record was last updated
    """

    __tablename__ = "compliance_checks"

    id: Mapped[UUID] = mapped_column(
        PG_UUID(as_uuid=True),
        primary_key=True,
        default=uuid4,
    )
    check_type: Mapped[str] = mapped_column(
        String(100),
        nullable=False,
        index=True,
    )
    status: Mapped[str] = mapped_column(
        String(20),
        nullable=False,
        index=True,
    )
    details: Mapped[Optional[dict[str, Any]]] = mapped_column(
        JSONB,
        nullable=True,
    )
    checked_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True),
        nullable=False,
    )
    next_check_due: Mapped[Optional[datetime]] = mapped_column(
        DateTime(timezone=True),
        nullable=True,
    )
    facility_id: Mapped[Optional[UUID]] = mapped_column(
        PG_UUID(as_uuid=True),
        nullable=True,
        index=True,
    )
    created_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True),
        server_default=func.now(),
        nullable=False,
    )
    updated_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True),
        server_default=func.now(),
        onupdate=func.now(),
        nullable=False,
    )

    # Table-level indexes for common query patterns
    __table_args__ = (
        # Composite index for facility-specific compliance checks by time
        Index("ix_compliance_checks_facility_checked", "facility_id", "checked_at"),
        # Index for finding upcoming checks
        Index("ix_compliance_checks_next_due", "next_check_due"),
        # Composite index for status filtering by check type
        Index("ix_compliance_checks_type_status", "check_type", "status"),
    )

    def __repr__(self) -> str:
        """String representation of the ComplianceCheck."""
        return (
            f"<ComplianceCheck(id={self.id}, "
            f"check_type='{self.check_type}', "
            f"status='{self.status}')>"
        )
