"""Cache statistics schemas for LAYA AI Service.

Provides Pydantic schemas for cache statistics responses.
"""

from datetime import datetime
from typing import Dict, Optional

from pydantic import Field

from app.schemas.base import BaseSchema


class CachePrefixStats(BaseSchema):
    """Statistics for a specific cache prefix.

    Attributes:
        key_count: Number of keys with this prefix
        sample_ttl: Sample TTL (time to live) in seconds for keys with this prefix
    """

    key_count: int = Field(
        ...,
        ge=0,
        description="Number of keys with this prefix",
    )
    sample_ttl: Optional[int] = Field(
        default=None,
        description="Sample TTL in seconds (None if no keys or key has no expiration)",
    )


class CacheStatsResponse(BaseSchema):
    """Cache statistics response.

    Provides comprehensive cache statistics for monitoring and administration.

    Attributes:
        total_keys: Total number of keys in Redis
        memory_used_bytes: Memory used by Redis in bytes
        memory_used_human: Human-readable memory usage
        by_prefix: Statistics grouped by cache key prefix
        uptime_seconds: Redis server uptime in seconds
        connected_clients: Number of connected clients
        generated_at: Timestamp when statistics were generated
    """

    total_keys: int = Field(
        ...,
        ge=0,
        description="Total number of keys in Redis",
    )
    memory_used_bytes: int = Field(
        ...,
        ge=0,
        description="Memory used by Redis in bytes",
    )
    memory_used_human: str = Field(
        ...,
        description="Human-readable memory usage (e.g., '1.5M', '500K')",
    )
    by_prefix: Dict[str, CachePrefixStats] = Field(
        default_factory=dict,
        description="Statistics grouped by cache key prefix",
    )
    uptime_seconds: int = Field(
        ...,
        ge=0,
        description="Redis server uptime in seconds",
    )
    connected_clients: int = Field(
        ...,
        ge=0,
        description="Number of connected clients",
    )
    generated_at: datetime = Field(
        default_factory=datetime.utcnow,
        description="Timestamp when statistics were generated",
    )
