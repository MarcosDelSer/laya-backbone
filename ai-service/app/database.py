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
    echo=True,  # Enable SQL logging for development debugging
    pool_pre_ping=True,  # Enable connection health checks
    pool_size=10,  # Number of permanent connections in the pool
    max_overflow=20,  # Maximum overflow connections beyond pool_size
    pool_timeout=30,  # Timeout for getting a connection from the pool (seconds)
    pool_recycle=3600,  # Recycle connections after 1 hour to prevent stale connections
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
