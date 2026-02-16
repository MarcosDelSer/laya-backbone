"""Alembic migration environment configuration for LAYA AI Service.

This module configures Alembic for async SQLAlchemy migrations with asyncpg.
"""

import asyncio
from logging.config import fileConfig

from alembic import context
from sqlalchemy import pool
from sqlalchemy.engine import Connection
from sqlalchemy.ext.asyncio import async_engine_from_config

from app.config import settings
from app.models import Base

# Alembic Config object - provides access to values in alembic.ini
config = context.config

# Interpret the config file for Python logging.
# This sets up loggers if not already configured.
if config.config_file_name is not None:
    fileConfig(config.config_file_name)

# Add your model's MetaData object here for 'autogenerate' support
# Import all models to ensure they are registered with Base.metadata
from app.models import (  # noqa: E402, F401
    CoachingRecommendation,
    CoachingSession,
    EvidenceSource,
)
from app.models.activity import (  # noqa: E402, F401
    Activity,
    ActivityRecommendation,
    ActivityParticipation,
)
from app.models.analytics import (  # noqa: E402, F401
    AnalyticsMetric,
    ComplianceCheck,
    EnrollmentForecast,
)
from app.models.storage import (  # noqa: E402, F401
    File,
    FileThumbnail,
    StorageQuota,
)

target_metadata = Base.metadata

# Override sqlalchemy.url with our async database URL from settings
# Convert async URL to sync for Alembic operations that require sync connection
def get_sync_url() -> str:
    """Get synchronous database URL for Alembic offline migrations.

    Converts the async postgresql+asyncpg:// URL to sync postgresql:// URL.

    Returns:
        str: Synchronous PostgreSQL connection URL
    """
    return settings.database_url.replace("+asyncpg", "")


def get_async_url() -> str:
    """Get async database URL for Alembic online migrations.

    Returns:
        str: Async PostgreSQL connection URL with asyncpg driver
    """
    return settings.database_url


def run_migrations_offline() -> None:
    """Run migrations in 'offline' mode.

    This configures the context with just a URL and not an Engine,
    though an Engine is acceptable here as well. By skipping the Engine creation
    we don't even need a DBAPI to be available.

    Calls to context.execute() here emit the given string to the
    script output.
    """
    url = get_sync_url()
    context.configure(
        url=url,
        target_metadata=target_metadata,
        literal_binds=True,
        dialect_opts={"paramstyle": "named"},
    )

    with context.begin_transaction():
        context.run_migrations()


def do_run_migrations(connection: Connection) -> None:
    """Execute migrations against a connection.

    Args:
        connection: SQLAlchemy connection object
    """
    context.configure(connection=connection, target_metadata=target_metadata)

    with context.begin_transaction():
        context.run_migrations()


async def run_async_migrations() -> None:
    """Run migrations in async mode using asyncpg.

    Creates an async engine and runs migrations within an async context.
    """
    configuration = config.get_section(config.config_ini_section, {})
    configuration["sqlalchemy.url"] = get_async_url()

    connectable = async_engine_from_config(
        configuration,
        prefix="sqlalchemy.",
        poolclass=pool.NullPool,
    )

    async with connectable.connect() as connection:
        await connection.run_sync(do_run_migrations)

    await connectable.dispose()


def run_migrations_online() -> None:
    """Run migrations in 'online' mode.

    In this scenario we need to create an Engine and associate a connection
    with the context. This uses async connection handling for asyncpg compatibility.
    """
    asyncio.run(run_async_migrations())


if context.is_offline_mode():
    run_migrations_offline()
else:
    run_migrations_online()
