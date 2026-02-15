"""Analytics router for LAYA AI Service.

Provides endpoints for business intelligence dashboard, KPI metrics,
enrollment forecasting, and Quebec regulatory compliance monitoring.
"""

from __future__ import annotations

from typing import Any

from fastapi import APIRouter, Depends, Query
from sqlalchemy.ext.asyncio import AsyncSession

from app.database import get_db
from app.dependencies import get_current_user
from app.schemas.analytics import (
    ComplianceListResponse,
    DashboardResponse,
    ForecastData,
    KPIMetricsListResponse,
)
from app.services.analytics_service import AnalyticsService

router = APIRouter()


async def get_analytics_service(
    db: AsyncSession = Depends(get_db),
) -> AnalyticsService:
    """Dependency to get AnalyticsService instance.

    Args:
        db: Async database session

    Returns:
        AnalyticsService: Service instance for analytics operations
    """
    return AnalyticsService(db=db)


@router.get(
    "/dashboard",
    response_model=DashboardResponse,
    summary="Get dashboard overview",
    description="Returns aggregated dashboard view with all key metrics for directors",
)
async def get_dashboard(
    current_user: dict[str, Any] = Depends(get_current_user),
    analytics_service: AnalyticsService = Depends(get_analytics_service),
) -> DashboardResponse:
    """Get aggregated dashboard with KPIs, enrollment trends, and compliance alerts.

    Args:
        current_user: Authenticated user from JWT token
        analytics_service: Analytics service instance

    Returns:
        DashboardResponse: Combined view of KPIs, forecast summary, and compliance status
    """
    return await analytics_service.get_dashboard(facility_id=None)


@router.get(
    "/kpis",
    response_model=KPIMetricsListResponse,
    summary="Get KPI metrics",
    description="Returns individual KPI values with historical comparison",
)
async def get_kpis(
    current_user: dict[str, Any] = Depends(get_current_user),
    analytics_service: AnalyticsService = Depends(get_analytics_service),
) -> KPIMetricsListResponse:
    """Get detailed KPI metrics with period comparisons.

    Returns enrollment rate, attendance percentage, revenue metrics,
    and staff-to-child ratios.

    Args:
        current_user: Authenticated user from JWT token
        analytics_service: Analytics service instance

    Returns:
        KPIMetricsListResponse: List of KPI metrics with generation timestamp
    """
    return await analytics_service.get_kpis(facility_id=None)


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
    analytics_service: AnalyticsService = Depends(get_analytics_service),
) -> ForecastData:
    """Get enrollment forecast with historical context.

    Args:
        months_ahead: Number of months to forecast (1-12)
        include_historical: Whether to include historical data
        current_user: Authenticated user from JWT token
        analytics_service: Analytics service instance

    Returns:
        ForecastData: Historical and projected enrollment data
    """
    return await analytics_service.get_enrollment_forecast(
        months_ahead=months_ahead,
        include_historical=include_historical,
        facility_id=None,
    )


@router.get(
    "/compliance",
    response_model=ComplianceListResponse,
    summary="Get compliance status",
    description="Returns Quebec childcare regulation compliance status",
)
async def get_compliance(
    current_user: dict[str, Any] = Depends(get_current_user),
    analytics_service: AnalyticsService = Depends(get_analytics_service),
) -> ComplianceListResponse:
    """Get Quebec regulatory compliance status.

    Returns compliance checks for staff-to-child ratios,
    staff certifications, facility capacity, and safety inspections.

    Args:
        current_user: Authenticated user from JWT token
        analytics_service: Analytics service instance

    Returns:
        ComplianceListResponse: List of compliance checks with overall status
    """
    return await analytics_service.get_compliance(facility_id=None)
