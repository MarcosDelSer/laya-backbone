"""Analytics router for LAYA AI Service.

Provides endpoints for business intelligence dashboard, KPI metrics,
enrollment forecasting, and Quebec regulatory compliance monitoring.
"""

from __future__ import annotations

from datetime import datetime
from typing import Any, Optional

from fastapi import APIRouter, Depends, Query
from sqlalchemy.ext.asyncio import AsyncSession

from app.database import get_db
from app.dependencies import get_current_user
from app.schemas.analytics import (
    ComplianceCheckResponse,
    ComplianceCheckType,
    ComplianceListResponse,
    ComplianceStatus,
    DashboardResponse,
    DashboardSummary,
    ForecastData,
    ForecastDataPoint,
    KPIMetric,
    KPIMetricsListResponse,
    MetricCategory,
)

router = APIRouter()


@router.get(
    "/dashboard",
    response_model=DashboardResponse,
    summary="Get dashboard overview",
    description="Returns aggregated dashboard view with all key metrics for directors",
)
async def get_dashboard(
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> DashboardResponse:
    """Get aggregated dashboard with KPIs, enrollment trends, and compliance alerts.

    Args:
        db: Async database session
        current_user: Authenticated user from JWT token

    Returns:
        DashboardResponse: Combined view of KPIs, forecast summary, and compliance status
    """
    now = datetime.utcnow()

    # Build summary metrics
    summary = DashboardSummary(
        total_enrolled=0,
        total_capacity=0,
        enrollment_rate=0.0,
        average_attendance=0.0,
        compliance_score=100.0,
    )

    # Build KPIs list
    kpis = _build_placeholder_kpis(now)

    # Build forecast summary
    forecast_summary = ForecastData(
        historical=[],
        forecast=[],
        model_version="v1",
        generated_at=now,
        confidence_note="No historical data available for forecasting",
    )

    # Build compliance summary
    compliance_summary = ComplianceListResponse(
        checks=_build_placeholder_compliance_checks(now),
        overall_status=ComplianceStatus.UNKNOWN,
        generated_at=now,
    )

    return DashboardResponse(
        summary=summary,
        kpis=kpis,
        forecast_summary=forecast_summary,
        compliance_summary=compliance_summary,
        alerts=["No data available - please configure data sources"],
        generated_at=now,
    )


@router.get(
    "/kpis",
    response_model=KPIMetricsListResponse,
    summary="Get KPI metrics",
    description="Returns individual KPI values with historical comparison",
)
async def get_kpis(
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> KPIMetricsListResponse:
    """Get detailed KPI metrics with period comparisons.

    Returns enrollment rate, attendance percentage, revenue metrics,
    and staff-to-child ratios.

    Args:
        db: Async database session
        current_user: Authenticated user from JWT token

    Returns:
        KPIMetricsListResponse: List of KPI metrics with generation timestamp
    """
    now = datetime.utcnow()
    kpis = _build_placeholder_kpis(now)

    return KPIMetricsListResponse(
        metrics=kpis,
        generated_at=now,
    )


@router.get(
    "/enrollment-forecast",
    response_model=ForecastData,
    summary="Get enrollment forecast",
    description="Returns time-series enrollment predictions based on historical data",
)
async def get_enrollment_forecast(
    months_ahead: int = Query(
        default=6,
        ge=1,
        le=12,
        description="Number of months to forecast ahead (max 12)",
    ),
    include_historical: bool = Query(
        default=True,
        description="Include historical enrollment data in response",
    ),
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> ForecastData:
    """Get enrollment forecast with historical context.

    Args:
        months_ahead: Number of months to forecast (1-12)
        include_historical: Whether to include historical data
        db: Async database session
        current_user: Authenticated user from JWT token

    Returns:
        ForecastData: Historical and projected enrollment data
    """
    now = datetime.utcnow()

    historical: list[ForecastDataPoint] = []
    forecast: list[ForecastDataPoint] = []
    confidence_note: Optional[str] = None

    # Placeholder: No historical data available yet
    if include_historical:
        confidence_note = (
            "Insufficient historical data (< 6 months). "
            "Forecast confidence is limited."
        )

    return ForecastData(
        facility_id=None,
        historical=historical,
        forecast=forecast,
        model_version="v1",
        generated_at=now,
        confidence_note=confidence_note,
    )


@router.get(
    "/compliance",
    response_model=ComplianceListResponse,
    summary="Get compliance status",
    description="Returns Quebec childcare regulation compliance status",
)
async def get_compliance(
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> ComplianceListResponse:
    """Get Quebec regulatory compliance status.

    Returns compliance checks for staff-to-child ratios,
    staff certifications, facility capacity, and safety inspections.

    Args:
        db: Async database session
        current_user: Authenticated user from JWT token

    Returns:
        ComplianceListResponse: List of compliance checks with overall status
    """
    now = datetime.utcnow()
    checks = _build_placeholder_compliance_checks(now)

    # Determine overall status based on individual checks
    statuses = [check.status for check in checks]
    if ComplianceStatus.VIOLATION in statuses:
        overall_status = ComplianceStatus.VIOLATION
    elif ComplianceStatus.WARNING in statuses:
        overall_status = ComplianceStatus.WARNING
    elif all(s == ComplianceStatus.COMPLIANT for s in statuses):
        overall_status = ComplianceStatus.COMPLIANT
    else:
        overall_status = ComplianceStatus.UNKNOWN

    return ComplianceListResponse(
        checks=checks,
        overall_status=overall_status,
        generated_at=now,
    )


def _build_placeholder_kpis(now: datetime) -> list[KPIMetric]:
    """Build placeholder KPI metrics.

    Args:
        now: Current timestamp for period bounds

    Returns:
        list[KPIMetric]: List of placeholder KPI metrics
    """
    from decimal import Decimal

    return [
        KPIMetric(
            metric_name="Enrollment Rate",
            metric_value=Decimal("0.0"),
            metric_unit="%",
            category=MetricCategory.ENROLLMENT,
            period_start=now,
            period_end=now,
            previous_value=None,
            change_percentage=None,
        ),
        KPIMetric(
            metric_name="Attendance Rate",
            metric_value=Decimal("0.0"),
            metric_unit="%",
            category=MetricCategory.ATTENDANCE,
            period_start=now,
            period_end=now,
            previous_value=None,
            change_percentage=None,
        ),
        KPIMetric(
            metric_name="Monthly Revenue",
            metric_value=Decimal("0.0"),
            metric_unit="CAD",
            category=MetricCategory.REVENUE,
            period_start=now,
            period_end=now,
            previous_value=None,
            change_percentage=None,
        ),
        KPIMetric(
            metric_name="Staff-to-Child Ratio",
            metric_value=Decimal("0.0"),
            metric_unit="ratio",
            category=MetricCategory.STAFFING,
            period_start=now,
            period_end=now,
            previous_value=None,
            change_percentage=None,
        ),
    ]


def _build_placeholder_compliance_checks(
    now: datetime,
) -> list[ComplianceCheckResponse]:
    """Build placeholder compliance checks for all check types.

    Args:
        now: Current timestamp for checked_at

    Returns:
        list[ComplianceCheckResponse]: List of placeholder compliance checks
    """
    return [
        ComplianceCheckResponse(
            check_type=ComplianceCheckType.STAFF_RATIO,
            status=ComplianceStatus.UNKNOWN,
            details={"message": "No staff ratio data available"},
            checked_at=now,
            next_check_due=None,
            facility_id=None,
            recommendation="Configure staff and enrollment data to enable ratio monitoring",
        ),
        ComplianceCheckResponse(
            check_type=ComplianceCheckType.CERTIFICATION,
            status=ComplianceStatus.UNKNOWN,
            details={"message": "No certification data available"},
            checked_at=now,
            next_check_due=None,
            facility_id=None,
            recommendation="Upload staff certification records",
        ),
        ComplianceCheckResponse(
            check_type=ComplianceCheckType.CAPACITY,
            status=ComplianceStatus.UNKNOWN,
            details={"message": "No capacity data available"},
            checked_at=now,
            next_check_due=None,
            facility_id=None,
            recommendation="Set facility capacity limits",
        ),
        ComplianceCheckResponse(
            check_type=ComplianceCheckType.SAFETY,
            status=ComplianceStatus.UNKNOWN,
            details={"message": "No safety inspection data available"},
            checked_at=now,
            next_check_due=None,
            facility_id=None,
            recommendation="Record safety inspection results",
        ),
    ]
