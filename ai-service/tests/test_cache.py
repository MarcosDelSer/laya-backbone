"""Unit tests for cache decorator functionality.

Tests for cache decorator with TTL, key generation, and invalidation.
"""

from __future__ import annotations

import asyncio
from uuid import UUID, uuid4

import pytest
from redis.asyncio import Redis

from app.core.cache import cache, invalidate_cache, _generate_cache_key
from app.redis_client import get_redis_client, close_redis


class TestCacheKeyGeneration:
    """Tests for cache key generation."""

    def test_generate_cache_key_basic(self) -> None:
        """Test basic cache key generation."""
        key = _generate_cache_key(
            key_prefix="test",
            func_name="my_func",
            args=(),
            kwargs={}
        )

        assert key.startswith("test:my_func:")
        assert len(key) > len("test:my_func:")

    def test_generate_cache_key_with_args(self) -> None:
        """Test cache key generation with positional arguments."""
        key1 = _generate_cache_key(
            key_prefix="test",
            func_name="my_func",
            args=(1, 2, 3),
            kwargs={}
        )
        key2 = _generate_cache_key(
            key_prefix="test",
            func_name="my_func",
            args=(1, 2, 3),
            kwargs={}
        )
        key3 = _generate_cache_key(
            key_prefix="test",
            func_name="my_func",
            args=(1, 2, 4),  # Different args
            kwargs={}
        )

        # Same args should produce same key
        assert key1 == key2
        # Different args should produce different key
        assert key1 != key3

    def test_generate_cache_key_with_kwargs(self) -> None:
        """Test cache key generation with keyword arguments."""
        key1 = _generate_cache_key(
            key_prefix="test",
            func_name="my_func",
            args=(),
            kwargs={"a": 1, "b": 2}
        )
        key2 = _generate_cache_key(
            key_prefix="test",
            func_name="my_func",
            args=(),
            kwargs={"b": 2, "a": 1}  # Different order
        )

        # Same kwargs in different order should produce same key
        assert key1 == key2

    def test_generate_cache_key_with_uuid(self) -> None:
        """Test cache key generation with UUID arguments."""
        test_uuid = uuid4()

        key1 = _generate_cache_key(
            key_prefix="test",
            func_name="my_func",
            args=(test_uuid,),
            kwargs={}
        )
        key2 = _generate_cache_key(
            key_prefix="test",
            func_name="my_func",
            args=(test_uuid,),
            kwargs={}
        )

        # Same UUID should produce same key
        assert key1 == key2


class TestCacheDecorator:
    """Tests for cache decorator functionality."""

    @pytest.mark.asyncio
    async def test_cache_basic_functionality(self) -> None:
        """Test basic cache functionality."""
        call_count = 0

        @cache(ttl=60, key_prefix="test")
        async def expensive_function(value: int) -> int:
            nonlocal call_count
            call_count += 1
            return value * 2

        # First call - should execute function
        result1 = await expensive_function(5)
        assert result1 == 10
        assert call_count == 1

        # Second call with same args - should use cache
        result2 = await expensive_function(5)
        assert result2 == 10
        assert call_count == 1  # Not incremented

        # Call with different args - should execute function
        result3 = await expensive_function(10)
        assert result3 == 20
        assert call_count == 2

    @pytest.mark.asyncio
    async def test_cache_with_dict_return(self) -> None:
        """Test caching with dictionary return value."""
        @cache(ttl=60, key_prefix="test_dict")
        async def get_data(key: str) -> dict:
            return {"key": key, "value": "test_data", "nested": {"a": 1}}

        # First call
        result1 = await get_data("test")
        assert result1 == {"key": "test", "value": "test_data", "nested": {"a": 1}}

        # Second call - from cache
        result2 = await get_data("test")
        assert result2 == result1

    @pytest.mark.asyncio
    async def test_cache_with_list_return(self) -> None:
        """Test caching with list return value."""
        @cache(ttl=60, key_prefix="test_list")
        async def get_items(count: int) -> list:
            return [{"id": i, "name": f"Item {i}"} for i in range(count)]

        # First call
        result1 = await get_items(3)
        assert len(result1) == 3
        assert result1[0] == {"id": 0, "name": "Item 0"}

        # Second call - from cache
        result2 = await get_items(3)
        assert result2 == result1

    @pytest.mark.asyncio
    async def test_cache_ttl_expiration(self) -> None:
        """Test that cache expires after TTL."""
        call_count = 0

        @cache(ttl=1, key_prefix="test_ttl")  # 1 second TTL
        async def short_lived_cache(value: int) -> int:
            nonlocal call_count
            call_count += 1
            return value * 3

        # First call
        result1 = await short_lived_cache(7)
        assert result1 == 21
        assert call_count == 1

        # Immediate second call - should use cache
        result2 = await short_lived_cache(7)
        assert result2 == 21
        assert call_count == 1

        # Wait for cache to expire
        await asyncio.sleep(1.5)

        # Third call after expiration - should execute function
        result3 = await short_lived_cache(7)
        assert result3 == 21
        assert call_count == 2

    @pytest.mark.asyncio
    async def test_cache_with_different_key_prefixes(self) -> None:
        """Test that different key prefixes create separate caches."""
        call_count_a = 0
        call_count_b = 0

        @cache(ttl=60, key_prefix="prefix_a")
        async def func_a(value: int) -> int:
            nonlocal call_count_a
            call_count_a += 1
            return value

        @cache(ttl=60, key_prefix="prefix_b")
        async def func_b(value: int) -> int:
            nonlocal call_count_b
            call_count_b += 1
            return value * 2

        # Call both functions with same args
        await func_a(5)
        await func_b(5)

        assert call_count_a == 1
        assert call_count_b == 1

        # Second calls - both should use their own caches
        await func_a(5)
        await func_b(5)

        assert call_count_a == 1
        assert call_count_b == 1

    @pytest.mark.asyncio
    async def test_cache_preserves_function_metadata(self) -> None:
        """Test that decorator preserves function metadata."""
        @cache(ttl=60, key_prefix="test_meta")
        async def documented_function(x: int) -> int:
            """This is a test function."""
            return x

        assert documented_function.__name__ == "documented_function"
        assert documented_function.__doc__ == "This is a test function."


class TestCacheInvalidation:
    """Tests for cache invalidation."""

    @pytest.mark.asyncio
    async def test_invalidate_cache_basic(self) -> None:
        """Test basic cache invalidation."""
        call_count = 0

        @cache(ttl=300, key_prefix="test_invalidate")
        async def cached_func(value: int) -> int:
            nonlocal call_count
            call_count += 1
            return value * 4

        # Create cached value
        result1 = await cached_func(3)
        assert result1 == 12
        assert call_count == 1

        # Verify cache hit
        result2 = await cached_func(3)
        assert result2 == 12
        assert call_count == 1

        # Invalidate cache
        deleted_count = await invalidate_cache("test_invalidate")
        assert deleted_count >= 1

        # Next call should execute function again
        result3 = await cached_func(3)
        assert result3 == 12
        assert call_count == 2

    @pytest.mark.asyncio
    async def test_invalidate_cache_with_pattern(self) -> None:
        """Test cache invalidation with pattern matching."""
        @cache(ttl=300, key_prefix="user_data")
        async def get_user_data(user_id: int) -> dict:
            return {"user_id": user_id, "data": "value"}

        # Create multiple cached entries
        await get_user_data(1)
        await get_user_data(2)
        await get_user_data(3)

        # Invalidate all user_data cache
        deleted_count = await invalidate_cache("user_data", "*")
        assert deleted_count >= 3

    @pytest.mark.asyncio
    async def test_invalidate_nonexistent_cache(self) -> None:
        """Test invalidating cache that doesn't exist."""
        deleted_count = await invalidate_cache("nonexistent_prefix")
        assert deleted_count == 0


class TestCacheWithUUID:
    """Tests for caching with UUID arguments."""

    @pytest.mark.asyncio
    async def test_cache_with_uuid_argument(self) -> None:
        """Test caching with UUID as argument."""
        call_count = 0
        test_uuid = uuid4()

        @cache(ttl=60, key_prefix="test_uuid")
        async def get_by_uuid(item_id: UUID) -> str:
            nonlocal call_count
            call_count += 1
            return f"Item {item_id}"

        # First call
        result1 = await get_by_uuid(test_uuid)
        assert result1 == f"Item {test_uuid}"
        assert call_count == 1

        # Second call with same UUID - should use cache
        result2 = await get_by_uuid(test_uuid)
        assert result2 == result1
        assert call_count == 1

        # Call with different UUID - should execute function
        result3 = await get_by_uuid(uuid4())
        assert call_count == 2


class TestCacheErrorHandling:
    """Tests for cache error handling."""

    @pytest.mark.asyncio
    async def test_cache_continues_on_redis_error(self) -> None:
        """Test that function executes even if Redis is unavailable."""
        # Close Redis to simulate unavailability
        await close_redis()

        @cache(ttl=60, key_prefix="test_error")
        async def resilient_function(value: int) -> int:
            return value * 5

        # Should execute function and return result despite Redis issues
        result = await resilient_function(4)
        assert result == 20

        # Reconnect Redis for other tests
        await get_redis_client()
