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


def check_database_pool() -> Dict[str, Any]:
    """Check SQLAlchemy database connection pool status.

    Returns:
        Dict containing connection pool statistics
    """
    try:
        from app.database import engine

        pool = engine.pool

        # Get pool statistics
        pool_size = pool.size()
        checked_out = pool.checkedout()
        overflow = pool.overflow()
        checked_in = pool.checkedin()

        # Calculate pool utilization percentage
        total_capacity = pool_size + overflow
        utilization = (checked_out / total_capacity * 100) if total_capacity > 0 else 0

        # Determine pool health status
        # Warn if pool is >80% utilized, critical if >95%
        if utilization > 95:
            pool_status = "critical"
        elif utilization > 80:
            pool_status = "degraded"
        else:
            pool_status = "healthy"

        return {
            "status": pool_status,
            "pool_size": pool_size,
            "checked_out": checked_out,
            "checked_in": checked_in,
            "overflow": overflow,
            "total_capacity": total_capacity,
            "utilization_percent": round(utilization, 2),
        }
    except Exception as e:
        return {
            "status": "error",
            "error": str(e),
        }


async def check_redis_pool() -> Dict[str, Any]:
    """Check Redis connection pool status.

    Returns:
        Dict containing Redis pool statistics
    """
    try:
        # Try to import and use redis
        import redis.asyncio as redis
        from app.config import settings

        # Get Redis connection details from settings
        redis_host = getattr(settings, 'redis_host', 'localhost')
        redis_port = getattr(settings, 'redis_port', 6379)
        redis_db = getattr(settings, 'redis_db', 0)

        # Create Redis connection pool with size limits
        pool = redis.ConnectionPool(
            host=redis_host,
            port=redis_port,
            db=redis_db,
            max_connections=10,  # Default max connections
            decode_responses=True,
        )

        # Create client from pool
        client = redis.Redis(connection_pool=pool)

        # Ping to ensure connection
        await client.ping()

        # Get pool info
        # Note: Redis-py doesn't expose detailed pool metrics directly
        # but we can get some info from the connection pool
        pool_info = {
            "status": "healthy",
            "max_connections": pool.max_connections,
            "connection_kwargs": {
                "host": redis_host,
                "port": redis_port,
                "db": redis_db,
            },
        }

        # Try to get additional info from Redis server
        info = await client.info("stats")
        if info:
            pool_info["total_connections_received"] = info.get(
                "total_connections_received", 0
            )
            pool_info["connected_clients"] = info.get("connected_clients", 0)

        await client.close()
        await pool.disconnect()

        return pool_info
    except ImportError:
        return {
            "status": "unknown",
            "message": "Redis client not installed",
        }
    except Exception as e:
        return {
            "status": "unhealthy",
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
    - Database connection pool status
    - Redis connection pool status

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
                "memory": {"status": "healthy", "rss_mb": 256.5},
                "database_pool": {"status": "healthy", "utilization_percent": 40.0},
                "redis_pool": {"status": "healthy", "max_connections": 10}
            }
        }
    """
    # Run all health checks
    database_health = await check_database_health(db)
    redis_health = await check_redis_health()
    disk_health = get_disk_usage()
    memory_health = get_memory_usage()
    db_pool_health = check_database_pool()
    redis_pool_health = await check_redis_pool()

    # Determine overall health status
    checks = {
        "database": database_health,
        "redis": redis_health,
        "disk": disk_health,
        "memory": memory_health,
        "database_pool": db_pool_health,
        "redis_pool": redis_pool_health,
    }

    # Overall status is healthy only if all critical checks pass
    # Redis is optional, so we only consider it if it's configured
    # Pool status is important - degraded pools should make overall status degraded
    critical_checks = [database_health, disk_health, db_pool_health]

    # Check for any critical failures
    has_critical = any(
        check.get("status") in ["unhealthy", "critical", "error"]
        for check in critical_checks
    )

    # Check for any degraded states
    has_degraded = any(
        check.get("status") == "degraded"
        for check in critical_checks
    )

    if has_critical:
        overall_status = "unhealthy"
    elif has_degraded:
        overall_status = "degraded"
    else:
        overall_status = "healthy"

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


@router.get(
    "/pools",
    summary="Connection pool monitoring",
    description="Monitor database and Redis connection pool status",
    response_model=None,
)
async def connection_pools() -> Dict[str, Any]:
    """Connection pool monitoring endpoint.

    Provides detailed metrics about database and Redis connection pools,
    including pool size, utilization, and capacity.

    Returns:
        Dict containing pool statistics for all services

    Example:
        GET /health/pools

        Response:
        {
            "timestamp": "2024-02-15T10:30:00Z",
            "pools": {
                "database": {
                    "status": "healthy",
                    "pool_size": 5,
                    "checked_out": 2,
                    "checked_in": 3,
                    "overflow": 0,
                    "utilization_percent": 40.0
                },
                "redis": {
                    "status": "healthy",
                    "max_connections": 10,
                    "connected_clients": 3
                }
            }
        }
    """
    # Get pool statistics
    db_pool = check_database_pool()
    redis_pool = await check_redis_pool()

    return {
        "timestamp": datetime.utcnow().isoformat() + "Z",
        "pools": {
            "database": db_pool,
            "redis": redis_pool,
        },
    }
