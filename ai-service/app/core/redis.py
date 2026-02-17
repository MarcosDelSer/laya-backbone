"""Async Redis configuration for LAYA AI Service.

Provides async Redis connection pool for token blacklist caching and
other performance-critical operations.
"""

from typing import AsyncGenerator

import redis.asyncio as redis

from app.config import settings

# Create async Redis connection pool
redis_pool = redis.ConnectionPool.from_url(
    settings.redis_url,
    encoding="utf-8",
    decode_responses=True,
    max_connections=10,
)


async def get_redis() -> AsyncGenerator[redis.Redis, None]:
    """Dependency for getting async Redis connections.

    Yields:
        redis.Redis: Async Redis client

    Example:
        @app.get("/cached-data")
        async def get_cached_data(redis_client: redis.Redis = Depends(get_redis)):
            value = await redis_client.get("cache_key")
            return {"value": value}
    """
    client = redis.Redis(connection_pool=redis_pool)
    try:
        yield client
    finally:
        await client.close()


async def check_redis_health() -> dict:
    """Check Redis connection health.

    Returns:
        dict: Redis health status and connection information

    Example:
        health = await check_redis_health()
        print(f"Redis connected: {health['connected']}")
    """
    client = redis.Redis(connection_pool=redis_pool)
    try:
        # Test connection with PING command
        await client.ping()

        # Get server info
        info = await client.info()

        return {
            "connected": True,
            "version": info.get("redis_version", "unknown"),
            "connected_clients": info.get("connected_clients", 0),
            "used_memory": info.get("used_memory_human", "unknown"),
            "uptime_seconds": info.get("uptime_in_seconds", 0),
            "configuration": {
                "host": settings.redis_host,
                "port": settings.redis_port,
                "db": settings.redis_db,
            },
        }
    except redis.ConnectionError as e:
        return {
            "connected": False,
            "error": str(e),
            "configuration": {
                "host": settings.redis_host,
                "port": settings.redis_port,
                "db": settings.redis_db,
            },
        }
    finally:
        await client.close()


async def get_pool_stats() -> dict:
    """Get Redis connection pool statistics.

    Returns:
        dict: Connection pool statistics

    Example:
        stats = await get_pool_stats()
        print(f"Pool size: {stats['max_connections']}")
    """
    return {
        "max_connections": redis_pool.max_connections,
        "connection_class": str(redis_pool.connection_class),
        "connection_kwargs": {
            "host": settings.redis_host,
            "port": settings.redis_port,
            "db": settings.redis_db,
        },
    }
