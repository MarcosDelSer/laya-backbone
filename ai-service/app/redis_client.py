"""Async Redis configuration for LAYA AI Service.

Provides async Redis client for caching and session management.
"""

from typing import AsyncGenerator

import redis.asyncio as redis
from redis.asyncio import Redis

from app.config import settings

# Global Redis client instance
_redis_client: Redis | None = None


async def get_redis_client() -> Redis:
    """Get or create the async Redis client.

    Returns:
        Redis: Async Redis client instance

    Raises:
        Exception: If Redis connection fails
    """
    global _redis_client

    if _redis_client is None:
        _redis_client = redis.from_url(
            settings.redis_url,
            encoding="utf-8",
            decode_responses=True,
            max_connections=10,
        )

    return _redis_client


async def get_redis() -> AsyncGenerator[Redis, None]:
    """Dependency for getting async Redis client.

    Yields:
        Redis: Async Redis client

    Example:
        @app.get("/cached-data")
        async def get_cached_data(redis: Redis = Depends(get_redis)):
            value = await redis.get("my_key")
            return {"value": value}
    """
    client = await get_redis_client()
    try:
        yield client
    except Exception:
        # Re-raise any errors during operation
        raise


async def close_redis() -> None:
    """Close the Redis connection.

    Should be called during application shutdown.
    """
    global _redis_client

    if _redis_client is not None:
        await _redis_client.close()
        _redis_client = None


async def ping_redis() -> bool:
    """Check Redis connection health.

    Returns:
        bool: True if Redis is responsive, False otherwise
    """
    try:
        client = await get_redis_client()
        return await client.ping()
    except Exception:
        return False
