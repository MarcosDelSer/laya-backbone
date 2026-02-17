"""Cache statistics router for LAYA AI Service.

Provides endpoints for cache monitoring and administration.
"""

import logging
from typing import Any

from fastapi import APIRouter, Depends, HTTPException, status

from app.dependencies import get_current_user
from app.schemas.cache import CacheStatsResponse
from app.services.cache_service import get_cache_statistics

router = APIRouter()
logger = logging.getLogger(__name__)


@router.get(
    "/stats",
    response_model=CacheStatsResponse,
    summary="Get cache statistics",
    description="Returns comprehensive cache statistics for monitoring and administration (admin only)",
)
async def get_cache_stats(
    current_user: dict[str, Any] = Depends(get_current_user),
) -> CacheStatsResponse:
    """Get comprehensive cache statistics.

    Returns statistics including:
    - Total number of cached keys
    - Memory usage by Redis
    - Keys grouped by prefix (child_profile, activity_catalog, analytics_dashboard, llm_response)
    - Sample TTL values
    - Server uptime and connected clients

    This endpoint is intended for administrators to monitor cache performance
    and health.

    Args:
        current_user: Authenticated user from JWT token

    Returns:
        CacheStatsResponse: Comprehensive cache statistics

    Raises:
        HTTPException: 503 Service Unavailable if Redis is not available
    """
    try:
        return await get_cache_statistics()
    except Exception as e:
        logger.error(f"Failed to get cache statistics: {e}")
        raise HTTPException(
            status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
            detail="Cache statistics unavailable - Redis connection failed",
        ) from e
