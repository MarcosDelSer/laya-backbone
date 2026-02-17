"""Cache statistics service for LAYA AI Service.

Provides functionality to gather and report cache statistics from Redis.
"""

import logging
from datetime import datetime
from typing import Dict

from app.redis_client import get_redis_client
from app.schemas.cache import CachePrefixStats, CacheStatsResponse

logger = logging.getLogger(__name__)

# Known cache prefixes used in the application
CACHE_PREFIXES = [
    "child_profile",
    "activity_catalog",
    "analytics_dashboard",
    "llm_response",
]


def _format_bytes(bytes_value: int) -> str:
    """Format bytes as human-readable string.

    Args:
        bytes_value: Number of bytes

    Returns:
        str: Human-readable string (e.g., "1.5M", "500K", "1.2G")
    """
    for unit in ["B", "K", "M", "G", "T"]:
        if bytes_value < 1024.0:
            return f"{bytes_value:.1f}{unit}"
        bytes_value /= 1024.0
    return f"{bytes_value:.1f}P"


async def get_cache_statistics() -> CacheStatsResponse:
    """Get comprehensive cache statistics from Redis.

    Gathers statistics including:
    - Total number of keys
    - Memory usage
    - Keys grouped by prefix
    - Server uptime and client connections

    Returns:
        CacheStatsResponse: Comprehensive cache statistics

    Raises:
        Exception: If Redis connection fails or statistics cannot be gathered
    """
    redis = await get_redis_client()

    # Get Redis server info
    info = await redis.info()

    # Get total keys
    total_keys = info.get("db0", {}).get("keys", 0) if isinstance(info.get("db0"), dict) else 0

    # Get memory usage
    memory_used_bytes = info.get("used_memory", 0)
    memory_used_human = _format_bytes(memory_used_bytes)

    # Get uptime and clients
    uptime_seconds = info.get("uptime_in_seconds", 0)
    connected_clients = info.get("connected_clients", 0)

    # Gather statistics by prefix
    by_prefix: Dict[str, CachePrefixStats] = {}

    for prefix in CACHE_PREFIXES:
        # Count keys with this prefix
        key_count = 0
        sample_ttl = None

        # Scan for keys with this prefix
        pattern = f"{prefix}:*"
        keys = []
        async for key in redis.scan_iter(match=pattern, count=100):
            keys.append(key)
            key_count += 1

            # Get sample TTL from first key
            if sample_ttl is None and key:
                try:
                    ttl = await redis.ttl(key)
                    # TTL returns -1 if key has no expiration, -2 if key doesn't exist
                    sample_ttl = ttl if ttl > 0 else None
                except Exception:
                    pass

        by_prefix[prefix] = CachePrefixStats(
            key_count=key_count,
            sample_ttl=sample_ttl,
        )

    return CacheStatsResponse(
        total_keys=total_keys,
        memory_used_bytes=memory_used_bytes,
        memory_used_human=memory_used_human,
        by_prefix=by_prefix,
        uptime_seconds=uptime_seconds,
        connected_clients=connected_clients,
        generated_at=datetime.utcnow(),
    )
