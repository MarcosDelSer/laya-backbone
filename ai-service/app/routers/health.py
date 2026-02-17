"""Health check router for LAYA AI Service.

Provides comprehensive health check endpoints for monitoring service health,
including database connectivity, Redis cache, disk space, and memory usage.
Automatically triggers alerts when critical issues are detected.
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
from app.services.alert_manager import AlertSeverity, get_alert_manager

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


async def check_notification_queues() -> Dict[str, Any]:
    """Check notification queue depth and health.

    Monitors Redis-based notification queues for email, push notifications,
    and SMS. Provides depth metrics and health status based on queue size.

    Returns:
        Dict containing notification queue statistics and health status
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

        # Ping to ensure connection
        await client.ping()

        # Define notification queue names
        # These are standard queue names used for different notification types
        queue_names = {
            "email": "laya:notifications:email",
            "push": "laya:notifications:push",
            "sms": "laya:notifications:sms",
        }

        # Get queue depths
        queues = {}
        total_depth = 0

        for queue_type, queue_name in queue_names.items():
            # Get queue length (LLEN for Redis lists)
            depth = await client.llen(queue_name)

            # Determine queue health based on depth
            # Warning thresholds:
            # - email: >1000 messages (warning), >5000 (critical)
            # - push: >500 messages (warning), >2000 (critical)
            # - sms: >100 messages (warning), >500 (critical)
            thresholds = {
                "email": {"warning": 1000, "critical": 5000},
                "push": {"warning": 500, "critical": 2000},
                "sms": {"warning": 100, "critical": 500},
            }

            queue_thresholds = thresholds.get(queue_type, {"warning": 1000, "critical": 5000})

            if depth >= queue_thresholds["critical"]:
                queue_status = "critical"
            elif depth >= queue_thresholds["warning"]:
                queue_status = "degraded"
            else:
                queue_status = "healthy"

            queues[queue_type] = {
                "depth": depth,
                "status": queue_status,
                "queue_name": queue_name,
                "warning_threshold": queue_thresholds["warning"],
                "critical_threshold": queue_thresholds["critical"],
            }
            total_depth += depth

        # Determine overall queue health
        queue_statuses = [q["status"] for q in queues.values()]
        if "critical" in queue_statuses:
            overall_status = "critical"
        elif "degraded" in queue_statuses:
            overall_status = "degraded"
        else:
            overall_status = "healthy"

        await client.close()

        return {
            "status": overall_status,
            "total_depth": total_depth,
            "queues": queues,
            "connected": True,
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
    description="Returns comprehensive health status including database, Redis, disk, memory, and notification queues",
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
    - Notification queue depth and status

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
                "redis_pool": {"status": "healthy", "max_connections": 10},
                "notification_queues": {"status": "healthy", "total_depth": 42}
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
    notification_queue_health = await check_notification_queues()

    # Determine overall health status
    checks = {
        "database": database_health,
        "redis": redis_health,
        "disk": disk_health,
        "memory": memory_health,
        "database_pool": db_pool_health,
        "redis_pool": redis_pool_health,
        "notification_queues": notification_queue_health,
    }

    # Overall status is healthy only if all critical checks pass
    # Redis is optional, so we only consider it if it's configured
    # Pool status is important - degraded pools should make overall status degraded
    # Notification queues are important - critical queues should make overall status degraded
    critical_checks = [database_health, disk_health, db_pool_health]

    # Notification queues contribute to overall health but don't cause complete failure
    queue_check = notification_queue_health

    # Check for any critical failures
    has_critical = any(
        check.get("status") in ["unhealthy", "critical", "error"]
        for check in critical_checks
    )

    # Check for any degraded states (including critical queue status)
    has_degraded = any(
        check.get("status") == "degraded"
        for check in critical_checks
    ) or queue_check.get("status") in ["degraded", "critical"]

    if has_critical:
        overall_status = "unhealthy"
    elif has_degraded:
        overall_status = "degraded"
    else:
        overall_status = "healthy"

    # Trigger alerts for critical or degraded status
    if overall_status in ["unhealthy", "degraded"]:
        await _trigger_health_alert(overall_status, checks)

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

    # Trigger alerts if pool status is critical or degraded
    if db_pool.get("status") in ["critical", "degraded"]:
        await _trigger_pool_alert("database", db_pool)

    return {
        "timestamp": datetime.utcnow().isoformat() + "Z",
        "pools": {
            "database": db_pool,
            "redis": redis_pool,
        },
    }


async def _trigger_health_alert(
    overall_status: str, checks: Dict[str, Any]
) -> None:
    """Trigger health alert when issues are detected.

    Args:
        overall_status: Overall health status (unhealthy/degraded)
        checks: Dictionary of health check results
    """
    try:
        alert_manager = get_alert_manager()

        # Build alert message with details of failed checks
        failed_checks = []
        for check_name, check_data in checks.items():
            check_status = check_data.get("status", "unknown")
            if check_status in ["unhealthy", "critical", "degraded", "error"]:
                failed_checks.append(f"{check_name}: {check_status}")

        if not failed_checks:
            return  # No issues to alert about

        # Determine severity
        severity = (
            AlertSeverity.CRITICAL
            if overall_status == "unhealthy"
            else AlertSeverity.WARNING
        )

        # Send alert
        await alert_manager.send_alert(
            title=f"AI Service Health {overall_status.upper()}",
            message=f"Service health is {overall_status}. Failed checks: {', '.join(failed_checks)}",
            severity=severity,
            context={
                "overall_status": overall_status,
                "failed_checks": failed_checks,
                "checks": checks,
            },
        )

    except Exception as e:
        # Don't let alerting failures affect health checks
        import logging
        logger = logging.getLogger(__name__)
        logger.error("Failed to send health alert: %s", e)


async def _trigger_pool_alert(pool_name: str, pool_data: Dict[str, Any]) -> None:
    """Trigger alert when connection pool is degraded or critical.

    Args:
        pool_name: Name of the pool (database, redis)
        pool_data: Pool statistics dictionary
    """
    try:
        alert_manager = get_alert_manager()

        pool_status = pool_data.get("status", "unknown")
        utilization = pool_data.get("utilization_percent", 0)

        severity = (
            AlertSeverity.CRITICAL
            if pool_status == "critical"
            else AlertSeverity.WARNING
        )

        await alert_manager.send_alert(
            title=f"{pool_name.title()} Connection Pool {pool_status.upper()}",
            message=f"{pool_name} connection pool is {pool_status} with {utilization}% utilization",
            severity=severity,
            context={
                "pool_name": pool_name,
                "pool_status": pool_status,
                "pool_data": pool_data,
            },
        )

    except Exception as e:
        import logging
        logger = logging.getLogger(__name__)
        logger.error("Failed to send pool alert: %s", e)


async def _trigger_queue_alert(queue_stats: Dict[str, Any]) -> None:
    """Trigger alert when notification queues are degraded or critical.

    Args:
        queue_stats: Queue statistics dictionary
    """
    try:
        alert_manager = get_alert_manager()

        overall_status = queue_stats.get("status", "unknown")
        total_depth = queue_stats.get("total_depth", 0)

        if overall_status not in ["degraded", "critical"]:
            return  # No alert needed

        # Find critical/degraded queues
        problem_queues = []
        queues = queue_stats.get("queues", {})
        for queue_type, queue_data in queues.items():
            if queue_data.get("status") in ["degraded", "critical"]:
                problem_queues.append(
                    f"{queue_type}: {queue_data['depth']} messages"
                )

        severity = (
            AlertSeverity.CRITICAL
            if overall_status == "critical"
            else AlertSeverity.WARNING
        )

        await alert_manager.send_alert(
            title=f"Notification Queues {overall_status.upper()}",
            message=f"Notification queues are {overall_status} with {total_depth} total messages. Problem queues: {', '.join(problem_queues)}",
            severity=severity,
            context={
                "overall_status": overall_status,
                "total_depth": total_depth,
                "problem_queues": problem_queues,
                "queue_stats": queue_stats,
            },
        )

    except Exception as e:
        import logging
        logger = logging.getLogger(__name__)
        logger.error("Failed to send queue alert: %s", e)


@router.get(
    "/queues",
    summary="Notification queue monitoring",
    description="Monitor notification queue depth and health status",
    response_model=None,
)
async def notification_queues() -> Dict[str, Any]:
    """Notification queue monitoring endpoint.

    Provides detailed metrics about notification queues including email,
    push notifications, and SMS. Monitors queue depth and provides health
    status based on configurable thresholds.

    Returns:
        Dict containing queue statistics and health status

    Example:
        GET /health/queues

        Response:
        {
            "timestamp": "2024-02-15T10:30:00Z",
            "status": "healthy",
            "total_depth": 42,
            "queues": {
                "email": {
                    "depth": 25,
                    "status": "healthy",
                    "queue_name": "laya:notifications:email",
                    "warning_threshold": 1000,
                    "critical_threshold": 5000
                },
                "push": {
                    "depth": 15,
                    "status": "healthy",
                    "queue_name": "laya:notifications:push",
                    "warning_threshold": 500,
                    "critical_threshold": 2000
                },
                "sms": {
                    "depth": 2,
                    "status": "healthy",
                    "queue_name": "laya:notifications:sms",
                    "warning_threshold": 100,
                    "critical_threshold": 500
                }
            }
        }
    """
    # Get notification queue statistics
    queue_stats = await check_notification_queues()

    # Trigger alerts if queue status is critical or degraded
    if queue_stats.get("status") in ["critical", "degraded"]:
        await _trigger_queue_alert(queue_stats)

    return {
        "timestamp": datetime.utcnow().isoformat() + "Z",
        **queue_stats,
    }


@router.get(
    "/alerts",
    summary="Alert configuration and history",
    description="View alert configuration and recent alert history",
    response_model=None,
)
async def alerts() -> Dict[str, Any]:
    """Alert configuration and history endpoint.

    Provides information about alert configuration status and recent alerts
    that have been sent by the system.

    Returns:
        Dict containing alert configuration and history

    Example:
        GET /health/alerts

        Response:
        {
            "timestamp": "2024-02-15T10:30:00Z",
            "config": {
                "enabled": true,
                "channels": ["email", "slack"],
                "min_severity": "warning"
            },
            "recent_alerts": [
                {
                    "title": "Database Connection Pool CRITICAL",
                    "severity": "critical",
                    "timestamp": "2024-02-15T10:25:00Z"
                }
            ]
        }
    """
    alert_manager = get_alert_manager()

    # Get configuration (sanitize sensitive data)
    config = alert_manager.config
    config_data = {
        "enabled": config.enabled,
        "channels": [c.value for c in config.channels],
        "min_severity": config.min_severity.value,
        "email_configured": bool(config.email_to),
        "webhook_configured": bool(config.webhook_url),
        "slack_configured": bool(config.slack_webhook_url),
    }

    # Get recent alert history
    recent_alerts = alert_manager.get_alert_history(limit=20)

    return {
        "timestamp": datetime.utcnow().isoformat() + "Z",
        "config": config_data,
        "recent_alerts": recent_alerts,
    }


@router.post(
    "/alerts/test",
    summary="Send test alert",
    description="Send a test alert through all configured channels",
    response_model=None,
)
async def test_alert() -> Dict[str, Any]:
    """Send a test alert through all configured channels.

    This endpoint allows testing the alert configuration by sending a
    test alert through all enabled channels.

    Returns:
        Dict containing alert send results

    Example:
        POST /health/alerts/test

        Response:
        {
            "sent": true,
            "timestamp": "2024-02-15T10:30:00Z",
            "channels": {
                "email": {"success": true, "recipients": ["admin@example.com"]},
                "slack": {"success": true, "status_code": 200}
            }
        }
    """
    alert_manager = get_alert_manager()

    result = await alert_manager.send_alert(
        title="Test Alert from LAYA AI Service",
        message="This is a test alert to verify the alert configuration is working correctly.",
        severity=AlertSeverity.INFO,
        context={
            "test": True,
            "timestamp": datetime.utcnow().isoformat() + "Z",
        },
    )

    return result
