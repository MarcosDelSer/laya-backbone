"""LLM response caching service with TTL and invalidation.

Provides intelligent caching for LLM responses to improve performance
and reduce costs. Supports TTL-based expiration, cache invalidation,
and hit count tracking for monitoring cache effectiveness.
"""

import hashlib
import json
from datetime import datetime, timedelta
from typing import Optional
from uuid import UUID

from sqlalchemy import delete, select, update
from sqlalchemy.ext.asyncio import AsyncSession

from app.llm.models import LLMCacheEntry
from app.llm.types import LLMMessage, LLMResponse, LLMUsage


class CacheError(Exception):
    """Base exception for cache-related errors."""

    pass


class CacheKeyError(CacheError):
    """Raised when there's an error generating a cache key."""

    pass


class CacheExpiredError(CacheError):
    """Raised when attempting to access an expired cache entry."""

    pass


# Default TTL values for different cache scenarios
DEFAULT_TTL_SECONDS = 3600  # 1 hour
SHORT_TTL_SECONDS = 300  # 5 minutes
LONG_TTL_SECONDS = 86400  # 24 hours


class LLMCache:
    """Service for caching LLM responses with TTL and invalidation.

    Provides a caching layer for LLM responses to reduce API calls,
    lower costs, and improve response times for repeated queries.
    Supports both in-memory operation (for testing) and database-backed
    persistence.

    Attributes:
        db: Optional async database session for persistence
        default_ttl: Default time-to-live for cache entries in seconds
    """

    def __init__(
        self,
        db: Optional[AsyncSession] = None,
        default_ttl: int = DEFAULT_TTL_SECONDS,
    ) -> None:
        """Initialize the cache service.

        Args:
            db: Optional async database session. If None, uses in-memory cache.
            default_ttl: Default TTL for cache entries in seconds
        """
        self.db = db
        self.default_ttl = default_ttl
        # In-memory cache for when no database is available
        self._memory_cache: dict[str, dict] = {}

    def generate_cache_key(
        self,
        messages: list[LLMMessage],
        provider: str,
        model: str,
        temperature: Optional[float] = None,
        max_tokens: Optional[int] = None,
    ) -> str:
        """Generate a unique cache key for the given request parameters.

        Creates a deterministic hash based on the message content, provider,
        model, and generation parameters to uniquely identify a request.

        Args:
            messages: List of messages in the conversation
            provider: LLM provider name
            model: Model name
            temperature: Optional temperature parameter
            max_tokens: Optional max tokens parameter

        Returns:
            SHA-256 hash string as the cache key

        Raises:
            CacheKeyError: If unable to generate a valid cache key
        """
        try:
            # Build a deterministic representation of the request
            key_parts = {
                "messages": [
                    {"role": msg.role.value, "content": msg.content}
                    for msg in messages
                ],
                "provider": provider,
                "model": model,
            }

            # Include optional parameters only if specified
            if temperature is not None:
                key_parts["temperature"] = temperature
            if max_tokens is not None:
                key_parts["max_tokens"] = max_tokens

            # Create a deterministic JSON string
            key_string = json.dumps(key_parts, sort_keys=True, ensure_ascii=True)

            # Generate SHA-256 hash
            return hashlib.sha256(key_string.encode("utf-8")).hexdigest()

        except (TypeError, ValueError) as e:
            raise CacheKeyError(f"Failed to generate cache key: {e}") from e

    def generate_prompt_hash(self, messages: list[LLMMessage]) -> str:
        """Generate a hash of just the prompt messages for verification.

        Used to verify cache entries match the original prompt without
        including model/provider information.

        Args:
            messages: List of messages in the conversation

        Returns:
            SHA-256 hash string of the prompt content
        """
        prompt_parts = [
            {"role": msg.role.value, "content": msg.content}
            for msg in messages
        ]
        prompt_string = json.dumps(prompt_parts, sort_keys=True, ensure_ascii=True)
        return hashlib.sha256(prompt_string.encode("utf-8")).hexdigest()

    async def get(
        self,
        cache_key: str,
        provider: Optional[str] = None,
        model: Optional[str] = None,
    ) -> Optional[LLMResponse]:
        """Retrieve a cached response by key.

        Looks up a cached response and returns it if found and not expired.
        Automatically updates the hit count and last accessed timestamp.

        Args:
            cache_key: The cache key to look up
            provider: Optional provider filter
            model: Optional model filter

        Returns:
            LLMResponse if found and valid, None otherwise
        """
        if self.db is None:
            return self._get_from_memory(cache_key)

        return await self._get_from_database(cache_key, provider, model)

    def _get_from_memory(self, cache_key: str) -> Optional[LLMResponse]:
        """Retrieve a cached response from in-memory cache.

        Args:
            cache_key: The cache key to look up

        Returns:
            LLMResponse if found and valid, None otherwise
        """
        entry = self._memory_cache.get(cache_key)
        if entry is None:
            return None

        # Check expiration
        if datetime.utcnow() > entry["expires_at"]:
            # Remove expired entry
            del self._memory_cache[cache_key]
            return None

        # Update hit count and last accessed
        entry["hit_count"] += 1
        entry["last_accessed_at"] = datetime.utcnow()

        return self._entry_to_response(entry)

    async def _get_from_database(
        self,
        cache_key: str,
        provider: Optional[str] = None,
        model: Optional[str] = None,
    ) -> Optional[LLMResponse]:
        """Retrieve a cached response from the database.

        Args:
            cache_key: The cache key to look up
            provider: Optional provider filter
            model: Optional model filter

        Returns:
            LLMResponse if found and valid, None otherwise
        """
        # Build query
        query = select(LLMCacheEntry).where(
            LLMCacheEntry.cache_key == cache_key,
            LLMCacheEntry.expires_at > datetime.utcnow(),
        )

        if provider:
            query = query.where(LLMCacheEntry.provider == provider)
        if model:
            query = query.where(LLMCacheEntry.model == model)

        result = await self.db.execute(query)
        entry = result.scalar_one_or_none()

        if entry is None:
            return None

        # Update hit count and last accessed timestamp
        await self.db.execute(
            update(LLMCacheEntry)
            .where(LLMCacheEntry.id == entry.id)
            .values(
                hit_count=LLMCacheEntry.hit_count + 1,
                last_accessed_at=datetime.utcnow(),
            )
        )
        await self.db.commit()

        return self._db_entry_to_response(entry)

    async def set(
        self,
        cache_key: str,
        response: LLMResponse,
        messages: list[LLMMessage],
        ttl_seconds: Optional[int] = None,
    ) -> None:
        """Store a response in the cache.

        Stores the given response with the specified TTL. If a cache entry
        already exists for the key, it will be replaced.

        Args:
            cache_key: The cache key for storage
            response: The LLM response to cache
            messages: Original messages for prompt hash verification
            ttl_seconds: Optional TTL in seconds (uses default if not specified)
        """
        ttl = ttl_seconds if ttl_seconds is not None else self.default_ttl
        expires_at = datetime.utcnow() + timedelta(seconds=ttl)
        prompt_hash = self.generate_prompt_hash(messages)

        if self.db is None:
            self._set_in_memory(cache_key, response, prompt_hash, expires_at)
        else:
            await self._set_in_database(cache_key, response, prompt_hash, expires_at)

    def _set_in_memory(
        self,
        cache_key: str,
        response: LLMResponse,
        prompt_hash: str,
        expires_at: datetime,
    ) -> None:
        """Store a response in the in-memory cache.

        Args:
            cache_key: The cache key for storage
            response: The LLM response to cache
            prompt_hash: Hash of the prompt for verification
            expires_at: Expiration timestamp
        """
        self._memory_cache[cache_key] = {
            "cache_key": cache_key,
            "provider": response.provider,
            "model": response.model,
            "prompt_hash": prompt_hash,
            "response_content": response.content,
            "prompt_tokens": response.usage.prompt_tokens,
            "completion_tokens": response.usage.completion_tokens,
            "hit_count": 0,
            "expires_at": expires_at,
            "created_at": datetime.utcnow(),
            "last_accessed_at": datetime.utcnow(),
        }

    async def _set_in_database(
        self,
        cache_key: str,
        response: LLMResponse,
        prompt_hash: str,
        expires_at: datetime,
    ) -> None:
        """Store a response in the database cache.

        Args:
            cache_key: The cache key for storage
            response: The LLM response to cache
            prompt_hash: Hash of the prompt for verification
            expires_at: Expiration timestamp
        """
        # Check if entry already exists
        result = await self.db.execute(
            select(LLMCacheEntry).where(LLMCacheEntry.cache_key == cache_key)
        )
        existing = result.scalar_one_or_none()

        if existing:
            # Update existing entry
            await self.db.execute(
                update(LLMCacheEntry)
                .where(LLMCacheEntry.id == existing.id)
                .values(
                    provider=response.provider,
                    model=response.model,
                    prompt_hash=prompt_hash,
                    response_content=response.content,
                    prompt_tokens=response.usage.prompt_tokens,
                    completion_tokens=response.usage.completion_tokens,
                    hit_count=0,
                    expires_at=expires_at,
                    last_accessed_at=datetime.utcnow(),
                )
            )
        else:
            # Create new entry
            entry = LLMCacheEntry(
                cache_key=cache_key,
                provider=response.provider,
                model=response.model,
                prompt_hash=prompt_hash,
                response_content=response.content,
                prompt_tokens=response.usage.prompt_tokens,
                completion_tokens=response.usage.completion_tokens,
                hit_count=0,
                expires_at=expires_at,
            )
            self.db.add(entry)

        await self.db.commit()

    async def invalidate(
        self,
        cache_key: Optional[str] = None,
        provider: Optional[str] = None,
        model: Optional[str] = None,
        older_than: Optional[datetime] = None,
    ) -> int:
        """Invalidate cache entries matching the given criteria.

        Removes cache entries that match any of the specified criteria.
        If no criteria are provided, no entries are removed (safety measure).

        Args:
            cache_key: Optional specific cache key to invalidate
            provider: Optional provider to invalidate all entries for
            model: Optional model to invalidate all entries for
            older_than: Optional datetime to invalidate entries created before

        Returns:
            Number of entries invalidated
        """
        if self.db is None:
            return self._invalidate_from_memory(cache_key, provider, model, older_than)

        return await self._invalidate_from_database(
            cache_key, provider, model, older_than
        )

    def _invalidate_from_memory(
        self,
        cache_key: Optional[str] = None,
        provider: Optional[str] = None,
        model: Optional[str] = None,
        older_than: Optional[datetime] = None,
    ) -> int:
        """Invalidate entries from the in-memory cache.

        Args:
            cache_key: Optional specific cache key to invalidate
            provider: Optional provider filter
            model: Optional model filter
            older_than: Optional datetime filter

        Returns:
            Number of entries invalidated
        """
        if cache_key is not None:
            if cache_key in self._memory_cache:
                del self._memory_cache[cache_key]
                return 1
            return 0

        # Safety: require at least one filter for bulk invalidation
        if provider is None and model is None and older_than is None:
            return 0

        keys_to_remove = []
        for key, entry in self._memory_cache.items():
            should_remove = False

            if provider is not None and entry["provider"] == provider:
                should_remove = True
            if model is not None and entry["model"] == model:
                should_remove = True
            if older_than is not None and entry["created_at"] < older_than:
                should_remove = True

            if should_remove:
                keys_to_remove.append(key)

        for key in keys_to_remove:
            del self._memory_cache[key]

        return len(keys_to_remove)

    async def _invalidate_from_database(
        self,
        cache_key: Optional[str] = None,
        provider: Optional[str] = None,
        model: Optional[str] = None,
        older_than: Optional[datetime] = None,
    ) -> int:
        """Invalidate entries from the database cache.

        Args:
            cache_key: Optional specific cache key to invalidate
            provider: Optional provider filter
            model: Optional model filter
            older_than: Optional datetime filter

        Returns:
            Number of entries invalidated
        """
        if cache_key is not None:
            result = await self.db.execute(
                delete(LLMCacheEntry).where(LLMCacheEntry.cache_key == cache_key)
            )
            await self.db.commit()
            return result.rowcount

        # Safety: require at least one filter for bulk invalidation
        if provider is None and model is None and older_than is None:
            return 0

        # Build delete query with filters
        conditions = []
        if provider is not None:
            conditions.append(LLMCacheEntry.provider == provider)
        if model is not None:
            conditions.append(LLMCacheEntry.model == model)
        if older_than is not None:
            conditions.append(LLMCacheEntry.created_at < older_than)

        query = delete(LLMCacheEntry).where(*conditions)
        result = await self.db.execute(query)
        await self.db.commit()

        return result.rowcount

    async def cleanup_expired(self) -> int:
        """Remove all expired cache entries.

        Performs maintenance by removing entries that have passed their
        expiration time. Should be called periodically.

        Returns:
            Number of expired entries removed
        """
        if self.db is None:
            return self._cleanup_expired_memory()

        return await self._cleanup_expired_database()

    def _cleanup_expired_memory(self) -> int:
        """Remove expired entries from in-memory cache.

        Returns:
            Number of expired entries removed
        """
        now = datetime.utcnow()
        keys_to_remove = [
            key
            for key, entry in self._memory_cache.items()
            if entry["expires_at"] <= now
        ]

        for key in keys_to_remove:
            del self._memory_cache[key]

        return len(keys_to_remove)

    async def _cleanup_expired_database(self) -> int:
        """Remove expired entries from the database cache.

        Returns:
            Number of expired entries removed
        """
        result = await self.db.execute(
            delete(LLMCacheEntry).where(LLMCacheEntry.expires_at <= datetime.utcnow())
        )
        await self.db.commit()
        return result.rowcount

    async def get_stats(
        self,
        provider: Optional[str] = None,
        model: Optional[str] = None,
    ) -> dict:
        """Get cache statistics for monitoring.

        Returns statistics about cache usage including total entries,
        hit counts, and storage metrics.

        Args:
            provider: Optional provider filter
            model: Optional model filter

        Returns:
            Dictionary with cache statistics
        """
        if self.db is None:
            return self._get_stats_memory(provider, model)

        return await self._get_stats_database(provider, model)

    def _get_stats_memory(
        self,
        provider: Optional[str] = None,
        model: Optional[str] = None,
    ) -> dict:
        """Get cache statistics from in-memory cache.

        Args:
            provider: Optional provider filter
            model: Optional model filter

        Returns:
            Dictionary with cache statistics
        """
        entries = list(self._memory_cache.values())

        if provider:
            entries = [e for e in entries if e["provider"] == provider]
        if model:
            entries = [e for e in entries if e["model"] == model]

        total_entries = len(entries)
        total_hits = sum(e["hit_count"] for e in entries)
        total_prompt_tokens = sum(e["prompt_tokens"] for e in entries)
        total_completion_tokens = sum(e["completion_tokens"] for e in entries)

        now = datetime.utcnow()
        active_entries = len([e for e in entries if e["expires_at"] > now])
        expired_entries = total_entries - active_entries

        return {
            "total_entries": total_entries,
            "active_entries": active_entries,
            "expired_entries": expired_entries,
            "total_hits": total_hits,
            "total_prompt_tokens": total_prompt_tokens,
            "total_completion_tokens": total_completion_tokens,
            "storage_type": "memory",
        }

    async def _get_stats_database(
        self,
        provider: Optional[str] = None,
        model: Optional[str] = None,
    ) -> dict:
        """Get cache statistics from the database.

        Args:
            provider: Optional provider filter
            model: Optional model filter

        Returns:
            Dictionary with cache statistics
        """
        from sqlalchemy import func

        # Build base query
        base_query = select(
            func.count(LLMCacheEntry.id).label("total_entries"),
            func.sum(LLMCacheEntry.hit_count).label("total_hits"),
            func.sum(LLMCacheEntry.prompt_tokens).label("total_prompt_tokens"),
            func.sum(LLMCacheEntry.completion_tokens).label("total_completion_tokens"),
        )

        if provider:
            base_query = base_query.where(LLMCacheEntry.provider == provider)
        if model:
            base_query = base_query.where(LLMCacheEntry.model == model)

        result = await self.db.execute(base_query)
        row = result.one()

        # Count active vs expired
        active_query = select(func.count(LLMCacheEntry.id)).where(
            LLMCacheEntry.expires_at > datetime.utcnow()
        )
        if provider:
            active_query = active_query.where(LLMCacheEntry.provider == provider)
        if model:
            active_query = active_query.where(LLMCacheEntry.model == model)

        active_result = await self.db.execute(active_query)
        active_count = active_result.scalar() or 0

        total_entries = row.total_entries or 0
        expired_count = total_entries - active_count

        return {
            "total_entries": total_entries,
            "active_entries": active_count,
            "expired_entries": expired_count,
            "total_hits": row.total_hits or 0,
            "total_prompt_tokens": row.total_prompt_tokens or 0,
            "total_completion_tokens": row.total_completion_tokens or 0,
            "storage_type": "database",
        }

    def _entry_to_response(self, entry: dict) -> LLMResponse:
        """Convert an in-memory cache entry to an LLMResponse.

        Args:
            entry: In-memory cache entry dictionary

        Returns:
            LLMResponse reconstructed from cache
        """
        return LLMResponse(
            content=entry["response_content"],
            model=entry["model"],
            provider=entry["provider"],
            usage=LLMUsage(
                prompt_tokens=entry["prompt_tokens"],
                completion_tokens=entry["completion_tokens"],
                total_tokens=entry["prompt_tokens"] + entry["completion_tokens"],
            ),
            finish_reason="cached",
            created_at=entry["created_at"],
        )

    def _db_entry_to_response(self, entry: LLMCacheEntry) -> LLMResponse:
        """Convert a database cache entry to an LLMResponse.

        Args:
            entry: Database cache entry model

        Returns:
            LLMResponse reconstructed from cache
        """
        return LLMResponse(
            content=entry.response_content,
            model=entry.model,
            provider=entry.provider,
            usage=LLMUsage(
                prompt_tokens=entry.prompt_tokens,
                completion_tokens=entry.completion_tokens,
                total_tokens=entry.prompt_tokens + entry.completion_tokens,
            ),
            finish_reason="cached",
            created_at=entry.created_at,
        )

    def clear(self) -> None:
        """Clear all entries from the in-memory cache.

        Note: This only affects the in-memory cache. Use invalidate()
        with appropriate filters for database cache clearing.
        """
        self._memory_cache.clear()

    @property
    def size(self) -> int:
        """Get the number of entries in the in-memory cache.

        Returns:
            Number of entries currently in memory
        """
        return len(self._memory_cache)
