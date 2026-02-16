"""Async database configuration for LAYA AI Service.

Provides async SQLAlchemy engine and session factory for PostgreSQL.
Includes connection pooling optimization and query performance utilities.
"""

from sqlalchemy.ext.asyncio import AsyncSession, create_async_engine
from sqlalchemy.orm import sessionmaker

from app.config import settings

# Create async engine with asyncpg driver and optimized connection pooling
engine = create_async_engine(
    settings.database_url,
    echo=settings.db_echo,  # SQL logging controlled by configuration
    pool_pre_ping=settings.db_pool_pre_ping,  # Enable connection health checks
    pool_size=settings.db_pool_size,  # Number of permanent connections in the pool
    max_overflow=settings.db_max_overflow,  # Maximum overflow connections beyond pool_size
    pool_timeout=settings.db_pool_timeout,  # Timeout for getting a connection from the pool (seconds)
    pool_recycle=settings.db_pool_recycle,  # Recycle connections after 1 hour to prevent stale connections
)

# Session factory for creating async database sessions
AsyncSessionLocal = sessionmaker(
    engine,
    class_=AsyncSession,
    expire_on_commit=False,  # Allow object access after commit
    autocommit=False,
    autoflush=False,
)


async def get_db() -> AsyncSession:
    """Dependency for getting async database sessions.

    Yields:
        AsyncSession: Async database session

    Example:
        @app.get("/items")
        async def get_items(db: AsyncSession = Depends(get_db)):
            result = await db.execute(select(Item))
            return result.scalars().all()
    """
    async with AsyncSessionLocal() as session:
        try:
            yield session
        finally:
            await session.close()


async def explain_query(session: AsyncSession, query: str) -> dict:
    """Execute EXPLAIN ANALYZE on a query for performance analysis.

    Args:
        session: Database session
        query: SQL query to analyze

    Returns:
        dict: Query execution plan with timing information

    Example:
        plan = await explain_query(session, "SELECT * FROM activities WHERE is_active = true")
    """
    from sqlalchemy import text

    result = await session.execute(text(f"EXPLAIN ANALYZE {query}"))
    plan_rows = result.fetchall()
    return {"query": query, "execution_plan": [str(row[0]) for row in plan_rows]}


async def check_pool_status() -> dict:
    """Check the current database connection pool status.

    Returns:
        dict: Connection pool statistics

    Example:
        stats = await check_pool_status()
        print(f"Pool size: {stats['pool_size']}, Checked out: {stats['checked_out']}")
    """
    pool = engine.pool
    return {
        "pool_size": pool.size(),
        "checked_out": pool.checkedout(),
        "overflow": pool.overflow(),
        "total_connections": pool.size() + pool.overflow(),
    }


async def get_pool_health() -> dict:
    """Get comprehensive database connection pool health metrics.

    Returns:
        dict: Detailed pool health information including utilization and recommendations

    Example:
        health = await get_pool_health()
        print(f"Pool utilization: {health['utilization_pct']:.1f}%")
        if health['warnings']:
            for warning in health['warnings']:
                print(f"Warning: {warning}")
    """
    pool = engine.pool
    pool_size = pool.size()
    checked_out = pool.checkedout()
    overflow = pool.overflow()
    total_connections = pool_size + overflow

    # Calculate utilization percentage
    max_connections = settings.db_pool_size + settings.db_max_overflow
    utilization_pct = (total_connections / max_connections * 100) if max_connections > 0 else 0

    # Generate warnings and recommendations
    warnings = []
    recommendations = []

    if utilization_pct > 80:
        warnings.append("Pool utilization is above 80%. Consider increasing pool_size or max_overflow.")
        recommendations.append("Increase db_pool_size or db_max_overflow in configuration")

    if overflow > settings.db_max_overflow * 0.8:
        warnings.append("High overflow usage detected. Pool may be undersized.")
        recommendations.append("Increase db_pool_size to reduce overflow reliance")

    if checked_out == pool_size:
        warnings.append("All permanent connections are checked out. Overflow is being used.")

    return {
        "pool_size": pool_size,
        "checked_out": checked_out,
        "overflow": overflow,
        "total_connections": total_connections,
        "max_connections": max_connections,
        "utilization_pct": utilization_pct,
        "configuration": {
            "pool_size": settings.db_pool_size,
            "max_overflow": settings.db_max_overflow,
            "pool_timeout": settings.db_pool_timeout,
            "pool_recycle": settings.db_pool_recycle,
            "pool_pre_ping": settings.db_pool_pre_ping,
        },
        "warnings": warnings,
        "recommendations": recommendations,
    }


async def get_active_connections(session: AsyncSession) -> dict:
    """Get information about active database connections.

    Args:
        session: Database session

    Returns:
        dict: Active connection statistics from PostgreSQL

    Example:
        conn_info = await get_active_connections(session)
        print(f"Active connections: {conn_info['active_count']}")
    """
    from sqlalchemy import text

    # Query PostgreSQL for active connection information
    query = text("""
        SELECT
            COUNT(*) as total_connections,
            COUNT(*) FILTER (WHERE state = 'active') as active_count,
            COUNT(*) FILTER (WHERE state = 'idle') as idle_count,
            COUNT(*) FILTER (WHERE state = 'idle in transaction') as idle_in_transaction,
            MAX(EXTRACT(EPOCH FROM (NOW() - query_start))) as longest_query_seconds,
            MAX(EXTRACT(EPOCH FROM (NOW() - state_change))) as longest_idle_seconds
        FROM pg_stat_activity
        WHERE datname = current_database()
    """)

    result = await session.execute(query)
    row = result.fetchone()

    return {
        "total_connections": row[0] if row else 0,
        "active_count": row[1] if row else 0,
        "idle_count": row[2] if row else 0,
        "idle_in_transaction": row[3] if row else 0,
        "longest_query_seconds": row[4] if row and row[4] else 0,
        "longest_idle_seconds": row[5] if row and row[5] else 0,
    }


async def optimize_pool_settings(session: AsyncSession) -> dict:
    """Analyze database usage and recommend optimal pool settings.

    Args:
        session: Database session

    Returns:
        dict: Analysis and recommendations for pool configuration

    Example:
        recommendations = await optimize_pool_settings(session)
        print(f"Recommended pool_size: {recommendations['recommended_pool_size']}")
    """
    # Get current active connections
    conn_info = await get_active_connections(session)
    pool_health = await get_pool_health()

    # Analyze and make recommendations
    active_count = conn_info["active_count"]
    total_count = conn_info["total_connections"]

    # Recommend pool size based on typical active connections
    # Add 20% buffer for spikes
    recommended_pool_size = max(5, int(active_count * 1.2))

    # Recommend overflow based on peak usage
    # Should handle 2x the pool size for burst traffic
    recommended_overflow = recommended_pool_size * 2

    analysis = {
        "current_settings": pool_health["configuration"],
        "current_usage": {
            "total_connections": total_count,
            "active_connections": active_count,
            "idle_connections": conn_info["idle_count"],
            "pool_utilization_pct": pool_health["utilization_pct"],
        },
        "recommended_pool_size": recommended_pool_size,
        "recommended_max_overflow": recommended_overflow,
        "recommendations": [],
    }

    # Generate specific recommendations
    if recommended_pool_size > settings.db_pool_size:
        analysis["recommendations"].append(
            f"Increase pool_size from {settings.db_pool_size} to {recommended_pool_size} "
            f"to better match typical load"
        )
    elif recommended_pool_size < settings.db_pool_size * 0.5:
        analysis["recommendations"].append(
            f"Consider reducing pool_size from {settings.db_pool_size} to {recommended_pool_size} "
            f"to save resources"
        )

    if pool_health["utilization_pct"] > 80:
        analysis["recommendations"].append(
            "Pool utilization is high. Increase pool_size or max_overflow to prevent connection timeouts"
        )

    if conn_info["idle_in_transaction"] > 0:
        analysis["recommendations"].append(
            f"Found {conn_info['idle_in_transaction']} idle-in-transaction connections. "
            "Review application code for uncommitted transactions"
        )

    if conn_info["longest_query_seconds"] and conn_info["longest_query_seconds"] > 30:
        analysis["recommendations"].append(
            f"Long-running query detected ({conn_info['longest_query_seconds']:.1f}s). "
            "Review query performance and consider optimization"
        )

    return analysis
