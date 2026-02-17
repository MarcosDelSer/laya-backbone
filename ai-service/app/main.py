"""FastAPI application entry point for LAYA AI Service."""

import asyncio
import logging
from typing import Any

from fastapi import Depends, FastAPI
from fastapi.middleware.cors import CORSMiddleware

from app.core.cache_warming import warm_all_caches
from app.dependencies import get_current_user
from app.redis_client import close_redis, ping_redis
from app.routers import coaching
from app.routers.activities import router as activities_router
from app.routers.analytics import router as analytics_router
from app.routers.cache import router as cache_router
from app.routers.communication import router as communication_router
from app.routers.webhooks import router as webhooks_router

logger = logging.getLogger(__name__)

app = FastAPI(
    title="LAYA AI Service",
    description="AI-powered features for LAYA platform including activity recommendations, coaching guidance, and analytics",
    version="0.1.0",
)

# Configure CORS middleware for frontend integration
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Register API routers
app.include_router(coaching.router, prefix="/api/v1/coaching", tags=["coaching"])
app.include_router(activities_router)
app.include_router(analytics_router, prefix="/api/v1/analytics", tags=["analytics"])
app.include_router(cache_router, prefix="/api/v1/cache", tags=["cache"])
app.include_router(communication_router, prefix="/api/v1/communication", tags=["communication"])
app.include_router(webhooks_router, prefix="/api/v1/webhook", tags=["webhooks"])


@app.on_event("startup")
async def startup_event() -> None:
    """Application startup event handler.

    Performs initialization tasks:
    - Warms frequently accessed caches
    """
    logger.info("LAYA AI Service starting up...")

    # Warm caches in the background (non-blocking)
    # This ensures the app starts quickly even if cache warming is slow
    asyncio.create_task(warm_all_caches())

    logger.info("LAYA AI Service startup complete")


@app.on_event("shutdown")
async def shutdown_event() -> None:
    """Application shutdown event handler.

    Performs cleanup tasks:
    - Closes Redis connection
    """
    logger.info("LAYA AI Service shutting down...")

    # Close Redis connection
    await close_redis()

    logger.info("LAYA AI Service shutdown complete")


@app.get("/")
async def root_health_check() -> dict:
    """Root endpoint with basic health check.

    Returns:
        dict: Basic service status information
    """
    return {
        "status": "healthy",
        "service": "ai-service",
        "version": "0.1.0",
    }


@app.get("/health")
async def health_check() -> dict:
    """Comprehensive health check endpoint with dependency checks.

    Checks the health of the AI service and its dependencies:
    - Overall service status
    - Redis connectivity and responsiveness

    Returns:
        dict: Service status information including:
            - status: Overall service health ("healthy" or "degraded")
            - service: Service name
            - version: Service version
            - redis: Redis connection status
                - connected: Boolean indicating if Redis is reachable
                - responsive: Boolean indicating if Redis responds to ping

    Note:
        Redis unavailability results in "degraded" status rather than failure,
        as the service can still function without caching.
    """
    # Check Redis health - handle exceptions gracefully
    try:
        redis_healthy = await ping_redis()
    except Exception as e:
        # Log the error but don't fail the health check
        logger.warning(f"Redis health check failed with exception: {e}")
        redis_healthy = False

    # Determine overall status
    # Service is "degraded" if Redis is unavailable, but still functional
    overall_status = "healthy" if redis_healthy else "degraded"

    return {
        "status": overall_status,
        "service": "ai-service",
        "version": "0.1.0",
        "redis": {
            "connected": redis_healthy,
            "responsive": redis_healthy,
        },
    }


@app.get("/protected")
async def protected_endpoint(
    current_user: dict[str, Any] = Depends(get_current_user),
) -> dict:
    """Protected endpoint requiring JWT authentication.

    This endpoint demonstrates JWT authentication middleware.
    Requests without a valid Bearer token will receive a 401 Unauthorized response.

    Args:
        current_user: Decoded JWT payload containing user information

    Returns:
        dict: User information from the JWT token
    """
    return {
        "message": "Access granted",
        "user": current_user.get("sub"),
        "token_data": current_user,
    }
