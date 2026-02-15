"""Async database configuration for LAYA AI Service.

Provides async SQLAlchemy engine and session factory for PostgreSQL.
"""

from sqlalchemy.ext.asyncio import AsyncSession, create_async_engine
from sqlalchemy.orm import sessionmaker

from app.config import settings

# Create async engine with asyncpg driver
engine = create_async_engine(
    settings.database_url,
    echo=True,  # Enable SQL logging for development debugging
    pool_pre_ping=True,  # Enable connection health checks
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
