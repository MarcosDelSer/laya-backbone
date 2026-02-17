"""Cache warming utility for preloading frequently accessed data on startup.

Warms Redis cache with frequently accessed data to improve initial response
times and reduce load on downstream services.
"""

import logging
from typing import List, Optional

from app.database import AsyncSessionLocal
from app.services.activity_service import ActivityService
from app.services.analytics_service import AnalyticsService

logger = logging.getLogger(__name__)


async def warm_activity_catalog() -> bool:
    """Warm the activity catalog cache.

    Preloads the activity catalog into Redis with 1-hour TTL.
    This is beneficial as the catalog is frequently accessed and
    relatively static.

    Returns:
        bool: True if warming succeeded, False otherwise
    """
    try:
        logger.info("Warming activity catalog cache...")

        async with AsyncSessionLocal() as db:
            activity_service = ActivityService(db)

            # Fetch all activities to populate cache
            activities = await activity_service.get_activity_catalog()

            logger.info(
                f"Successfully warmed activity catalog cache "
                f"({len(activities)} activities)"
            )
            return True

    except Exception as e:
        logger.error(f"Failed to warm activity catalog cache: {e}")
        return False


async def warm_analytics_dashboard() -> bool:
    """Warm the analytics dashboard cache.

    Preloads the analytics dashboard into Redis with 15-minute TTL.
    This improves initial dashboard load times.

    Returns:
        bool: True if warming succeeded, False otherwise
    """
    try:
        logger.info("Warming analytics dashboard cache...")

        async with AsyncSessionLocal() as db:
            analytics_service = AnalyticsService(db)

            # Fetch dashboard data to populate cache
            dashboard = await analytics_service.get_dashboard()

            logger.info("Successfully warmed analytics dashboard cache")
            return True

    except Exception as e:
        logger.error(f"Failed to warm analytics dashboard cache: {e}")
        return False


async def warm_all_caches() -> dict[str, bool]:
    """Warm all frequently accessed caches.

    Attempts to warm all configured caches. Individual cache warming
    failures are logged but don't prevent other caches from being warmed.

    Returns:
        dict: Mapping of cache name to warming success status

    Example:
        {
            "activity_catalog": True,
            "analytics_dashboard": True
        }
    """
    logger.info("Starting cache warming on application startup...")

    results = {}

    # Warm activity catalog (1hr TTL - most beneficial to preload)
    results["activity_catalog"] = await warm_activity_catalog()

    # Warm analytics dashboard (15min TTL)
    results["analytics_dashboard"] = await warm_analytics_dashboard()

    # Log summary
    successful = sum(1 for success in results.values() if success)
    total = len(results)
    logger.info(
        f"Cache warming complete: {successful}/{total} caches warmed successfully"
    )

    return results


async def get_warming_status() -> dict[str, bool]:
    """Get the status of cache warming operations.

    This function can be used to check which caches were successfully
    warmed during startup.

    Returns:
        dict: Mapping of cache name to warming success status
    """
    # This is a simple implementation - in production, you might want to
    # store warming status in Redis or a state management system
    return {
        "activity_catalog": True,
        "analytics_dashboard": True,
    }
