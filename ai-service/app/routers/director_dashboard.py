"""Director Dashboard router for LAYA AI Service.

Provides endpoints for real-time director dashboard with occupancy monitoring,
group statistics, and trend analysis for facility oversight.
"""

from __future__ import annotations

import logging
from datetime import datetime, timedelta
from typing import Any, Optional
from uuid import UUID

from fastapi import APIRouter, Depends, Query
from sqlalchemy.exc import SQLAlchemyError
from sqlalchemy.ext.asyncio import AsyncSession

from app.database import AsyncSessionLocal
from app.dependencies import get_current_user
from app.schemas.director_dashboard import (
    AlertPriority,
    AlertType,
    AgeGroupType,
    DashboardAlert,
    DirectorDashboardResponse,
    GroupOccupancy,
    OccupancyHistoryPoint,
    OccupancyHistoryResponse,
    OccupancyStatus,
    OccupancySummary,
)
from app.services.occupancy_service import OccupancyService

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


def get_placeholder_occupancy_summary() -> OccupancySummary:
    """Return placeholder occupancy summary when database is unavailable."""
    now = datetime.utcnow()
    return OccupancySummary(
        facility_id=None,
        total_children=0,
        total_capacity=0,
        overall_occupancy_percentage=0.0,
        groups_at_capacity=0,
        groups_near_capacity=0,
        total_groups=0,
        average_staff_ratio=None,
        snapshot_time=now,
    )


def get_placeholder_groups() -> list[GroupOccupancy]:
    """Return placeholder group occupancy list when database is unavailable."""
    return []


def get_placeholder_alerts() -> list[DashboardAlert]:
    """Return placeholder alerts when database is unavailable."""
    return []


def get_placeholder_dashboard() -> DirectorDashboardResponse:
    """Return placeholder dashboard when database is unavailable."""
    now = datetime.utcnow()
    return DirectorDashboardResponse(
        summary=get_placeholder_occupancy_summary(),
        groups=get_placeholder_groups(),
        alerts=get_placeholder_alerts(),
        alert_count_by_priority={
            "low": 0,
            "medium": 0,
            "high": 0,
            "critical": 0,
        },
        generated_at=now,
    )


def get_placeholder_occupancy_history(days_back: int = 7) -> OccupancyHistoryResponse:
    """Return placeholder occupancy history when database is unavailable.

    Args:
        days_back: Number of days of history requested

    Returns:
        OccupancyHistoryResponse: Empty history response
    """
    now = datetime.utcnow()
    period_end = now
    period_start = now - timedelta(days=days_back)

    return OccupancyHistoryResponse(
        facility_id=None,
        data_points=[],
        period_start=period_start,
        period_end=period_end,
        generated_at=now,
    )


@router.get(
    "/dashboard",
    response_model=DirectorDashboardResponse,
    summary="Get real-time dashboard overview",
    description="Returns aggregated real-time dashboard with occupancy summary, group details, and alerts for facility directors",
)
async def get_dashboard(
    facility_id: Optional[UUID] = Query(
        default=None,
        description="Optional facility ID to filter by",
    ),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> DirectorDashboardResponse:
    """Get complete director dashboard with real-time occupancy data.

    Aggregates occupancy summary, group details, and alerts for
    facility directors.

    Args:
        facility_id: Optional facility to filter by
        current_user: Authenticated user from JWT token

    Returns:
        DirectorDashboardResponse: Complete dashboard data
    """
    db = await get_optional_db()
    if db is None:
        logger.warning("Database unavailable, returning placeholder dashboard")
        return get_placeholder_dashboard()

    try:
        occupancy_service = OccupancyService(db=db)
        return await occupancy_service.get_director_dashboard(facility_id=facility_id)
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
    "/occupancy",
    response_model=list[GroupOccupancy],
    summary="Get current occupancy by group",
    description="Returns real-time occupancy data for all groups in the facility",
)
async def get_occupancy(
    facility_id: Optional[UUID] = Query(
        default=None,
        description="Optional facility ID to filter by",
    ),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> list[GroupOccupancy]:
    """Get current occupancy for all groups.

    Returns real-time occupancy status for each group including
    current count, capacity, and compliance status.

    Args:
        facility_id: Optional facility to filter by
        current_user: Authenticated user from JWT token

    Returns:
        list[GroupOccupancy]: List of group occupancy data
    """
    db = await get_optional_db()
    if db is None:
        logger.warning("Database unavailable, returning placeholder groups")
        return get_placeholder_groups()

    try:
        occupancy_service = OccupancyService(db=db)
        return await occupancy_service.get_group_occupancies(facility_id=facility_id)
    except SQLAlchemyError as e:
        logger.warning(f"Database error in get_occupancy: {e}")
        return get_placeholder_groups()
    except Exception as e:
        logger.warning(f"Unexpected error in get_occupancy: {e}")
        return get_placeholder_groups()
    finally:
        if db:
            await db.close()


@router.get(
    "/occupancy/history",
    response_model=OccupancyHistoryResponse,
    summary="Get occupancy trend data",
    description="Returns historical occupancy data for trend analysis and visualization",
)
async def get_occupancy_history(
    facility_id: Optional[UUID] = Query(
        default=None,
        description="Optional facility ID to filter by",
    ),
    days_back: int = Query(
        default=7,
        ge=1,
        le=30,
        description="Number of days of history to retrieve (max 30)",
    ),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> OccupancyHistoryResponse:
    """Get historical occupancy data for trend analysis.

    Returns time series data for the specified period, useful for
    visualizing occupancy trends over time.

    Args:
        facility_id: Optional facility to filter by
        days_back: Number of days of history (1-30)
        current_user: Authenticated user from JWT token

    Returns:
        OccupancyHistoryResponse: Historical occupancy data
    """
    db = await get_optional_db()
    if db is None:
        logger.warning("Database unavailable, returning placeholder history")
        return get_placeholder_occupancy_history(days_back)

    try:
        occupancy_service = OccupancyService(db=db)
        return await occupancy_service.get_occupancy_history(
            facility_id=facility_id,
            days_back=days_back,
        )
    except SQLAlchemyError as e:
        logger.warning(f"Database error in get_occupancy_history: {e}")
        return get_placeholder_occupancy_history(days_back)
    except Exception as e:
        logger.warning(f"Unexpected error in get_occupancy_history: {e}")
        return get_placeholder_occupancy_history(days_back)
    finally:
        if db:
            await db.close()
