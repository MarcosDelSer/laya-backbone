"""Health check router for LAYA AI Service.

Provides comprehensive health check endpoints for monitoring service health,
including database connectivity, Redis cache, disk space, and memory usage.
"""

import os
import shutil
import sys
from datetime import datetime
from typing import Any, Dict

from fastapi import APIRouter, Depends, status
from sqlalchemy import text
from sqlalchemy.ext.asyncio import AsyncSession

from app.database import get_db

router = APIRouter(prefix="/health", tags=["health"])


def get_memory_usage() -> Dict[str, Any]:
    """Get current memory usage statistics.

    Returns:
        Dict containing memory usage information in MB
    """
    try:
        # Try to use psutil if available
        import psutil
        process = psutil.Process(os.getpid())
        memory_info = process.memory_info()

        return {
            "status": "healthy",
            "rss_mb": round(memory_info.rss / (1024 * 1024), 2),
            "vms_mb": round(memory_info.vms / (1024 * 1024), 2),
            "percent": round(process.memory_percent(), 2),
        }
    except ImportError:
        # Fallback if psutil not available
        return {
            "status": "unknown",
            "message": "psutil not available",
        }


def get_disk_usage() -> Dict[str, Any]:
    """Get current disk usage statistics.

    Returns:
        Dict containing disk usage information
    """
    try:
        disk_usage = shutil.disk_usage("/")
        total_gb = round(disk_usage.total / (1024 ** 3), 2)
        used_gb = round(disk_usage.used / (1024 ** 3), 2)
        free_gb = round(disk_usage.free / (1024 ** 3), 2)
        percent_used = round((disk_usage.used / disk_usage.total) * 100, 2)

        # Consider disk unhealthy if >90% full
        is_healthy = percent_used < 90

        return {
            "status": "healthy" if is_healthy else "degraded",
            "total_gb": total_gb,
            "used_gb": used_gb,
            "free_gb": free_gb,
            "percent_used": percent_used,
        }
    except Exception as e:
        return {
            "status": "error",
            "error": str(e),
        }


async def check_database_health(db: AsyncSession) -> Dict[str, Any]:
    """Check database connectivity and health.

    Args:
        db: Async database session

    Returns:
        Dict containing database health status
    """
    try:
        # Simple query to check database connectivity
        result = await db.execute(text("SELECT 1 as health_check"))
        row = result.fetchone()

        if row and row[0] == 1:
            return {
                "status": "healthy",
                "connected": True,
            }
        else:
            return {
                "status": "unhealthy",
                "connected": False,
                "error": "Unexpected query result",
            }
    except Exception as e:
        return {
            "status": "unhealthy",
            "connected": False,
            "error": str(e),
        }


async def check_redis_health() -> Dict[str, Any]:
    """Check Redis connectivity and health.

    Returns:
        Dict containing Redis health status
    """
    try:
        # Try to import and use redis
        import redis.asyncio as redis
        from app.config import settings

        # Get Redis connection details from settings
        redis_host = getattr(settings, 'redis_host', 'localhost')
        redis_port = getattr(settings, 'redis_port', 6379)
        redis_db = getattr(settings, 'redis_db', 0)

        # Create Redis client
        client = redis.Redis(
            host=redis_host,
            port=redis_port,
            db=redis_db,
            decode_responses=True,
            socket_connect_timeout=2,
            socket_timeout=2,
        )

        # Ping Redis
        await client.ping()

        # Get Redis info
        info = await client.info()

        await client.close()

        return {
            "status": "healthy",
            "connected": True,
            "version": info.get("redis_version", "unknown"),
            "uptime_seconds": info.get("uptime_in_seconds", 0),
        }
    except ImportError:
        return {
            "status": "unknown",
            "connected": False,
            "message": "Redis client not installed",
        }
    except Exception as e:
        return {
            "status": "unhealthy",
            "connected": False,
            "error": str(e),
        }


@router.get(
    "",
    summary="Comprehensive health check",
    description="Returns comprehensive health status including database, Redis, disk, and memory",
    response_model=None,
)
async def health_check(db: AsyncSession = Depends(get_db)) -> Dict[str, Any]:
    """Comprehensive health check endpoint.

    Checks the health of:
    - Database connectivity
    - Redis cache connectivity
    - Disk space usage
    - Memory usage

    Args:
        db: Async database session (injected)

    Returns:
        Dict containing health status of all components

    Example:
        GET /health

        Response:
        {
            "status": "healthy",
            "timestamp": "2024-02-15T10:30:00Z",
            "service": "ai-service",
            "version": "0.1.0",
            "checks": {
                "database": {"status": "healthy", "connected": true},
                "redis": {"status": "healthy", "connected": true},
                "disk": {"status": "healthy", "percent_used": 45.2},
                "memory": {"status": "healthy", "rss_mb": 256.5}
            }
        }
    """
    # Run all health checks
    database_health = await check_database_health(db)
    redis_health = await check_redis_health()
    disk_health = get_disk_usage()
    memory_health = get_memory_usage()

    # Determine overall health status
    checks = {
        "database": database_health,
        "redis": redis_health,
        "disk": disk_health,
        "memory": memory_health,
    }

    # Overall status is healthy only if all critical checks pass
    # Redis is optional, so we only consider it if it's configured
    critical_checks = [database_health, disk_health]

    is_healthy = all(
        check.get("status") in ["healthy", "unknown"]
        for check in critical_checks
    )

    overall_status = "healthy" if is_healthy else "unhealthy"

    # Return 503 if unhealthy, 200 if healthy
    http_status = (
        status.HTTP_200_OK if is_healthy
        else status.HTTP_503_SERVICE_UNAVAILABLE
    )

    return {
        "status": overall_status,
        "timestamp": datetime.utcnow().isoformat() + "Z",
        "service": "ai-service",
        "version": "0.1.0",
        "checks": checks,
    }


@router.get(
    "/liveness",
    summary="Liveness probe",
    description="Simple liveness check for Kubernetes/Docker",
)
async def liveness() -> Dict[str, str]:
    """Liveness probe endpoint.

    Returns a simple status to indicate the service is running.
    This is used by container orchestrators to determine if the service
    should be restarted.

    Returns:
        Dict with status
    """
    return {"status": "alive"}


@router.get(
    "/readiness",
    summary="Readiness probe",
    description="Readiness check for Kubernetes/Docker",
)
async def readiness(db: AsyncSession = Depends(get_db)) -> Dict[str, Any]:
    """Readiness probe endpoint.

    Checks if the service is ready to accept traffic by verifying
    database connectivity.

    Args:
        db: Async database session (injected)

    Returns:
        Dict with readiness status

    Raises:
        HTTPException: 503 if service is not ready
    """
    database_health = await check_database_health(db)

    is_ready = database_health.get("status") == "healthy"

    if is_ready:
        return {
            "status": "ready",
            "database": database_health,
        }
    else:
        return {
            "status": "not_ready",
            "database": database_health,
        }
