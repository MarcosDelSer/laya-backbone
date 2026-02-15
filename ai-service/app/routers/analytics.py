"""Analytics router for LAYA AI Service.

Provides endpoints for business intelligence dashboard, KPI metrics,
enrollment forecasting, and Quebec regulatory compliance monitoring.
"""

from __future__ import annotations

import logging
from datetime import date, datetime, timedelta
from decimal import Decimal
from typing import Any, Optional

from fastapi import APIRouter, Depends, Query
from sqlalchemy.exc import SQLAlchemyError
from sqlalchemy.ext.asyncio import AsyncSession

from app.database import AsyncSessionLocal
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
from app.services.analytics_service import AnalyticsService

router = APIRouter()
logger = logging.getLogger(__name__)


async def get_optional_db() -> Optional[AsyncSession]:
    """Get database session, returning None if connection fails.

    Returns:
        Optional[AsyncSession]: Database session or None
    """
    try:
        session = AsyncSessionLocal()
        return session
    except Exception as e:
        logger.warning(f"Database connection failed: {e}")
        return None


def get_placeholder_kpis() -> KPIMetricsListResponse:
    """Return placeholder KPI metrics when database is unavailable."""
    now = datetime.utcnow()
    period_start = now - timedelta(days=30)
    return KPIMetricsListResponse(
        metrics=[
            KPIMetric(
                metric_name="Enrollment Rate",
                metric_value=Decimal("0.0"),
                metric_unit="%",
                category=MetricCategory.ENROLLMENT,
                period_start=period_start,
                period_end=now,
            ),
            KPIMetric(
                metric_name="Attendance Rate",
                metric_value=Decimal("0.0"),
                metric_unit="%",
                category=MetricCategory.ATTENDANCE,
                period_start=period_start,
                period_end=now,
            ),
            KPIMetric(
                metric_name="Monthly Revenue",
                metric_value=Decimal("0.0"),
                metric_unit="CAD",
                category=MetricCategory.REVENUE,
                period_start=period_start,
                period_end=now,
            ),
            KPIMetric(
                metric_name="Staff-to-Child Ratio",
                metric_value=Decimal("0.0"),
                metric_unit="ratio",
                category=MetricCategory.STAFFING,
                period_start=period_start,
                period_end=now,
            ),
        ],
        generated_at=now,
    )


def get_placeholder_compliance() -> ComplianceListResponse:
    """Return placeholder compliance checks when database is unavailable."""
    now = datetime.utcnow()
    return ComplianceListResponse(
        checks=[
            ComplianceCheckResponse(
                check_type=ComplianceCheckType.STAFF_RATIO,
                status=ComplianceStatus.UNKNOWN,
                details={"message": "Database unavailable - using placeholder data"},
                checked_at=now,
                recommendation="Connect to database for real compliance data",
            ),
            ComplianceCheckResponse(
                check_type=ComplianceCheckType.CERTIFICATION,
                status=ComplianceStatus.UNKNOWN,
                details={"message": "Database unavailable - using placeholder data"},
                checked_at=now,
                recommendation="Connect to database for real compliance data",
            ),
            ComplianceCheckResponse(
                check_type=ComplianceCheckType.CAPACITY,
                status=ComplianceStatus.UNKNOWN,
                details={"message": "Database unavailable - using placeholder data"},
                checked_at=now,
                recommendation="Connect to database for real compliance data",
            ),
            ComplianceCheckResponse(
                check_type=ComplianceCheckType.SAFETY,
                status=ComplianceStatus.UNKNOWN,
                details={"message": "Database unavailable - using placeholder data"},
                checked_at=now,
                recommendation="Connect to database for real compliance data",
            ),
        ],
        overall_status=ComplianceStatus.UNKNOWN,
        generated_at=now,
    )


def get_placeholder_forecast(months_ahead: int = 6) -> ForecastData:
    """Return placeholder forecast when database is unavailable."""
    now = datetime.utcnow()
    today = date.today()
    forecast = [
        ForecastDataPoint(
            forecast_date=today + timedelta(days=i * 30),
            predicted_enrollment=0,
            confidence_lower=None,
            confidence_upper=None,
            is_historical=False,
        )
        for i in range(1, months_ahead + 1)
    ]
    return ForecastData(
        historical=[],
        forecast=forecast,
        model_version="v1",
        generated_at=now,
        confidence_note="Database unavailable - using placeholder data",
    )


def get_placeholder_dashboard() -> DashboardResponse:
    """Return placeholder dashboard when database is unavailable."""
    now = datetime.utcnow()
    kpis = get_placeholder_kpis()
    compliance = get_placeholder_compliance()
    forecast = get_placeholder_forecast(months_ahead=3)

    return DashboardResponse(
        summary=DashboardSummary(
            total_enrolled=0,
            total_capacity=0,
            enrollment_rate=0.0,
            average_attendance=0.0,
            compliance_score=50.0,
        ),
        kpis=kpis.metrics,
        forecast_summary=forecast,
        compliance_summary=compliance,
        alerts=["Database unavailable - displaying placeholder data"],
        generated_at=now,
    )


@router.get(
    "/dashboard",
    response_model=DashboardResponse,
    summary="Get dashboard overview",
    description="Returns aggregated dashboard view with all key metrics for directors",
)
async def get_dashboard(
    current_user: dict[str, Any] = Depends(get_current_user),
) -> DashboardResponse:
    """Get aggregated dashboard with KPIs, enrollment trends, and compliance alerts.

    Args:
        current_user: Authenticated user from JWT token

    Returns:
        DashboardResponse: Combined view of KPIs, forecast summary, and compliance status
    """
    db = await get_optional_db()
    if db is None:
        logger.warning("Database unavailable, returning placeholder dashboard")
        return get_placeholder_dashboard()

    try:
        analytics_service = AnalyticsService(db=db)
        return await analytics_service.get_dashboard(facility_id=None)
    except SQLAlchemyError as e:
        logger.warning(f"Database error in get_dashboard: {e}")
        return get_placeholder_dashboard()
    except Exception as e:
        logger.warning(f"Unexpected error in get_dashboard: {e}")
        return get_placeholder_dashboard()
    finally:
        if db:
            await db.close()


@router.get(
    "/kpis",
    response_model=KPIMetricsListResponse,
    summary="Get KPI metrics",
    description="Returns individual KPI values with historical comparison",
)
async def get_kpis(
    current_user: dict[str, Any] = Depends(get_current_user),
) -> KPIMetricsListResponse:
    """Get detailed KPI metrics with period comparisons.

    Returns enrollment rate, attendance percentage, revenue metrics,
    and staff-to-child ratios.

    Args:
        current_user: Authenticated user from JWT token

    Returns:
        KPIMetricsListResponse: List of KPI metrics with generation timestamp
    """
    db = await get_optional_db()
    if db is None:
        logger.warning("Database unavailable, returning placeholder KPIs")
        return get_placeholder_kpis()

    try:
        analytics_service = AnalyticsService(db=db)
        return await analytics_service.get_kpis(facility_id=None)
    except SQLAlchemyError as e:
        logger.warning(f"Database error in get_kpis: {e}")
        return get_placeholder_kpis()
    except Exception as e:
        logger.warning(f"Unexpected error in get_kpis: {e}")
        return get_placeholder_kpis()
    finally:
        if db:
            await db.close()


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
    current_user: dict[str, Any] = Depends(get_current_user),
) -> ForecastData:
    """Get enrollment forecast with historical context.

    Args:
        months_ahead: Number of months to forecast (1-12)
        include_historical: Whether to include historical data
        current_user: Authenticated user from JWT token

    Returns:
        ForecastData: Historical and projected enrollment data
    """
    db = await get_optional_db()
    if db is None:
        logger.warning("Database unavailable, returning placeholder forecast")
        return get_placeholder_forecast(months_ahead)

    try:
        analytics_service = AnalyticsService(db=db)
        return await analytics_service.get_enrollment_forecast(
            months_ahead=months_ahead,
            include_historical=include_historical,
            facility_id=None,
        )
    except SQLAlchemyError as e:
        logger.warning(f"Database error in get_enrollment_forecast: {e}")
        return get_placeholder_forecast(months_ahead)
    except Exception as e:
        logger.warning(f"Unexpected error in get_enrollment_forecast: {e}")
        return get_placeholder_forecast(months_ahead)
    finally:
        if db:
            await db.close()


@router.get(
    "/compliance",
    response_model=ComplianceListResponse,
    summary="Get compliance status",
    description="Returns Quebec childcare regulation compliance status",
)
async def get_compliance(
    current_user: dict[str, Any] = Depends(get_current_user),
) -> ComplianceListResponse:
    """Get Quebec regulatory compliance status.

    Returns compliance checks for staff-to-child ratios,
    staff certifications, facility capacity, and safety inspections.

    Args:
        current_user: Authenticated user from JWT token

    Returns:
        ComplianceListResponse: List of compliance checks with overall status
    """
    db = await get_optional_db()
    if db is None:
        logger.warning("Database unavailable, returning placeholder compliance")
        return get_placeholder_compliance()

    try:
        analytics_service = AnalyticsService(db=db)
        return await analytics_service.get_compliance(facility_id=None)
    except SQLAlchemyError as e:
        logger.warning(f"Database error in get_compliance: {e}")
        return get_placeholder_compliance()
    except Exception as e:
        logger.warning(f"Unexpected error in get_compliance: {e}")
        return get_placeholder_compliance()
    finally:
        if db:
            await db.close()
