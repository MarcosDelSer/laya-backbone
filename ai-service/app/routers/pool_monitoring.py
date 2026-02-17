"""Database connection pool monitoring endpoints.

Provides health check and monitoring endpoints for database connection pool.
These endpoints are useful for:
- Production monitoring and alerting
- Performance tuning and optimization
- Debugging connection issues
"""

from typing import Any, Dict

from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy.ext.asyncio import AsyncSession

from app.database import (
    check_pool_status,
    get_active_connections,
    get_pool_health,
    optimize_pool_settings,
    get_db,
)

router = APIRouter(
    prefix="/pool",
    tags=["monitoring"],
)


@router.get("/status", response_model=Dict[str, Any])
async def pool_status() -> Dict[str, Any]:
    """Get current database connection pool status.

    Returns basic pool statistics including:
    - Current pool size
    - Checked out connections
    - Overflow connections in use
    - Total active connections

    This is a lightweight endpoint suitable for frequent polling.

    Returns:
        dict: Pool status information

    Example:
        GET /pool/status
        {
            "pool_size": 10,
            "checked_out": 5,
            "overflow": 2,
            "total_connections": 12
        }
    """
    return await check_pool_status()


@router.get("/health", response_model=Dict[str, Any])
async def pool_health() -> Dict[str, Any]:
    """Get comprehensive database connection pool health metrics.

    Returns detailed health information including:
    - Pool utilization percentage
    - Configuration settings
    - Warnings about potential issues
    - Recommendations for optimization

    This endpoint provides actionable insights for pool tuning.

    Returns:
        dict: Comprehensive pool health information with warnings and recommendations

    Example:
        GET /pool/health
        {
            "pool_size": 10,
            "utilization_pct": 85.5,
            "warnings": ["Pool utilization is above 80%..."],
            "recommendations": ["Increase pool_size..."],
            "configuration": {...}
        }
    """
    health = await get_pool_health()

    # Return 503 if pool is critically unhealthy
    if health["utilization_pct"] > 95:
        raise HTTPException(
            status_code=503,
            detail={
                "error": "Database pool critically overloaded",
                "utilization": health["utilization_pct"],
                "warnings": health["warnings"],
            }
        )

    return health


@router.get("/connections", response_model=Dict[str, Any])
async def active_connections(db: AsyncSession = Depends(get_db)) -> Dict[str, Any]:
    """Get information about active database connections.

    Returns PostgreSQL connection statistics including:
    - Total connections to the database
    - Active query count
    - Idle connection count
    - Idle-in-transaction connections (potential issues)
    - Longest running query time

    Requires database access to query pg_stat_activity.

    Args:
        db: Database session (injected)

    Returns:
        dict: Active connection statistics from PostgreSQL

    Example:
        GET /pool/connections
        {
            "total_connections": 15,
            "active_count": 5,
            "idle_count": 8,
            "idle_in_transaction": 2,
            "longest_query_seconds": 45.5
        }
    """
    conn_info = await get_active_connections(db)

    # Warn if there are idle-in-transaction connections
    if conn_info["idle_in_transaction"] > 5:
        raise HTTPException(
            status_code=503,
            detail={
                "error": "Too many idle-in-transaction connections",
                "count": conn_info["idle_in_transaction"],
                "message": "Check application code for uncommitted transactions"
            }
        )

    return conn_info


@router.get("/optimize", response_model=Dict[str, Any])
async def optimization_recommendations(
    db: AsyncSession = Depends(get_db)
) -> Dict[str, Any]:
    """Get pool optimization recommendations based on current usage.

    Analyzes current pool usage and database connection patterns to provide
    data-driven recommendations for optimal pool configuration.

    Recommendations include:
    - Suggested pool_size based on typical load
    - Suggested max_overflow for burst traffic
    - Specific configuration changes to improve performance
    - Warnings about potential issues

    Args:
        db: Database session (injected)

    Returns:
        dict: Analysis and optimization recommendations

    Example:
        GET /pool/optimize
        {
            "current_settings": {
                "pool_size": 10,
                "max_overflow": 20
            },
            "recommended_pool_size": 15,
            "recommended_max_overflow": 30,
            "recommendations": [
                "Increase pool_size from 10 to 15 to better match typical load",
                "Pool utilization is high. Increase pool_size..."
            ]
        }
    """
    return await optimize_pool_settings(db)


@router.get("/healthcheck", response_model=Dict[str, str])
async def pool_healthcheck() -> Dict[str, str]:
    """Simple health check endpoint for load balancers.

    This endpoint provides a simple OK/ERROR response suitable for
    load balancer health checks. It checks if the pool is operational
    without making database queries.

    Returns:
        dict: Simple status response

    Raises:
        HTTPException: 503 if pool is critically overloaded

    Example:
        GET /pool/healthcheck
        {"status": "ok"}
    """
    health = await get_pool_health()

    # Return 503 if pool is critically unhealthy
    if health["utilization_pct"] > 95:
        raise HTTPException(
            status_code=503,
            detail="Pool critically overloaded"
        )

    return {"status": "ok"}
