"""Cache decorator with TTL support for LAYA AI Service.

Provides a simple caching decorator that uses Redis for storage with
configurable TTL (Time To Live) and key prefixes.
"""

import functools
import hashlib
import json
from typing import Any, Callable, Optional

from app.redis_client import get_redis_client


def _generate_cache_key(key_prefix: str, func_name: str, args: tuple, kwargs: dict) -> str:
    """Generate a cache key from function name and arguments.

    Args:
        key_prefix: Prefix for the cache key
        func_name: Name of the cached function
        args: Positional arguments
        kwargs: Keyword arguments

    Returns:
        str: Generated cache key
    """
    # Create a stable representation of args and kwargs
    key_parts = [func_name]

    # Add args to key
    for arg in args:
        # Convert UUID and other objects to strings
        key_parts.append(str(arg))

    # Add sorted kwargs to key (for stability)
    for k in sorted(kwargs.keys()):
        key_parts.append(f"{k}={kwargs[k]}")

    # Create hash of the key parts for a compact key
    key_string = ":".join(key_parts)
    key_hash = hashlib.md5(key_string.encode()).hexdigest()

    return f"{key_prefix}:{func_name}:{key_hash}"


def cache(ttl: int = 300, key_prefix: str = "cache"):
    """Decorator to cache function results in Redis with TTL.

    Args:
        ttl: Time to live in seconds (default: 300 = 5 minutes)
        key_prefix: Prefix for cache keys (default: "cache")

    Returns:
        Decorated function with caching

    Example:
        @cache(ttl=300, key_prefix="activities")
        async def get_activities(child_id: UUID):
            # Expensive operation
            return data
    """
    def decorator(func: Callable) -> Callable:
        @functools.wraps(func)
        async def async_wrapper(*args, **kwargs):
            # Generate cache key
            cache_key = _generate_cache_key(
                key_prefix=key_prefix,
                func_name=func.__name__,
                args=args,
                kwargs=kwargs
            )

            # Get Redis client
            redis = await get_redis_client()

            # Try to get cached value
            try:
                cached_value = await redis.get(cache_key)
                if cached_value is not None:
                    # Cache hit - deserialize and return
                    return json.loads(cached_value)
            except Exception:
                # If cache read fails, continue to execute function
                pass

            # Cache miss - execute function
            result = await func(*args, **kwargs)

            # Store result in cache with TTL
            try:
                serialized_result = json.dumps(result, default=str)
                await redis.setex(cache_key, ttl, serialized_result)
            except Exception:
                # If cache write fails, just return the result without caching
                pass

            return result

        return async_wrapper

    return decorator


async def invalidate_cache(key_prefix: str, pattern: str = "*") -> int:
    """Invalidate cache entries matching a pattern.

    Args:
        key_prefix: Prefix for cache keys
        pattern: Pattern to match (default: "*" for all keys with prefix)

    Returns:
        int: Number of keys deleted

    Example:
        # Invalidate all child profile caches
        await invalidate_cache("child_profile")

        # Invalidate specific child profile
        await invalidate_cache("child_profile", "12345*")
    """
    redis = await get_redis_client()

    # Build full pattern
    full_pattern = f"{key_prefix}:{pattern}"

    # Find matching keys
    try:
        keys = []
        async for key in redis.scan_iter(match=full_pattern):
            keys.append(key)

        # Delete keys if any found
        if keys:
            return await redis.delete(*keys)
        return 0
    except Exception:
        return 0


def invalidate_on_write(*cache_prefixes: str):
    """Decorator to automatically invalidate caches after write operations.

    This decorator wraps write operations (create, update, delete) and
    automatically invalidates specified caches after the operation succeeds.

    Args:
        *cache_prefixes: One or more cache key prefixes to invalidate

    Returns:
        Decorated function with automatic cache invalidation

    Example:
        @invalidate_on_write("child_profile", "analytics_dashboard")
        async def update_child_profile(child_id: UUID, data: dict):
            # Perform update operation
            return updated_profile
            # Caches for "child_profile" and "analytics_dashboard" are invalidated
    """
    def decorator(func: Callable) -> Callable:
        @functools.wraps(func)
        async def async_wrapper(*args, **kwargs):
            # Execute the write operation
            result = await func(*args, **kwargs)

            # Invalidate specified caches after successful write
            try:
                for prefix in cache_prefixes:
                    await invalidate_cache(prefix)
            except Exception:
                # Don't fail the operation if cache invalidation fails
                # This ensures write operations succeed even if Redis is down
                pass

            return result

        return async_wrapper

    return decorator
