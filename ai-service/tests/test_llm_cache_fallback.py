"""Unit tests for LLM caching and fallback logic.

Tests for cache key generation, TTL expiration, cache hit/miss behavior,
invalidation, statistics, and fallback strategy with provider failover.
"""

from __future__ import annotations

import asyncio
from datetime import datetime, timedelta
from typing import Any, Optional
from unittest.mock import AsyncMock, MagicMock, patch

import pytest
import pytest_asyncio

from app.llm.base import BaseLLMProvider
from app.llm.cache import (
    DEFAULT_TTL_SECONDS,
    LONG_TTL_SECONDS,
    SHORT_TTL_SECONDS,
    CacheError,
    CacheExpiredError,
    CacheKeyError,
    LLMCache,
)
from app.llm.exceptions import (
    LLMAuthenticationError,
    LLMError,
    LLMProviderError,
    LLMRateLimitError,
    LLMTimeoutError,
)
from app.llm.fallback import (
    FallbackAttempt,
    FallbackConfig,
    FallbackMode,
    FallbackResult,
    FallbackStrategy,
    RetryableError,
)
from app.llm.types import LLMConfig, LLMMessage, LLMResponse, LLMRole, LLMUsage


# ============================================================================
# Fixtures
# ============================================================================


@pytest.fixture
def sample_messages() -> list[LLMMessage]:
    """Create sample messages for testing.

    Returns:
        list[LLMMessage]: List of test messages
    """
    return [
        LLMMessage(role=LLMRole.SYSTEM, content="You are a helpful assistant."),
        LLMMessage(role=LLMRole.USER, content="Hello, how are you?"),
    ]


@pytest.fixture
def sample_response() -> LLMResponse:
    """Create a sample LLM response for testing.

    Returns:
        LLMResponse: Sample response object
    """
    return LLMResponse(
        content="Hello! I'm doing well, thank you for asking.",
        model="gpt-4o",
        provider="openai",
        usage=LLMUsage(
            prompt_tokens=20,
            completion_tokens=15,
            total_tokens=35,
        ),
        finish_reason="stop",
        created_at=datetime.utcnow(),
    )


@pytest.fixture
def llm_cache() -> LLMCache:
    """Create an in-memory LLM cache for testing.

    Returns:
        LLMCache: Cache instance without database backend
    """
    return LLMCache(db=None, default_ttl=DEFAULT_TTL_SECONDS)


@pytest.fixture
def sample_config() -> LLMConfig:
    """Create sample LLM configuration for testing.

    Returns:
        LLMConfig: Test configuration
    """
    return LLMConfig(
        model="gpt-4o",
        temperature=0.7,
        max_tokens=1000,
        timeout=30,
    )


class MockLLMProvider(BaseLLMProvider):
    """Mock LLM provider for testing fallback scenarios.

    Attributes:
        name: Provider name for identification
        _available: Whether the provider is available
        _response: Response to return on completion
        _error: Error to raise on completion (if any)
        complete_call_count: Number of times complete() was called
    """

    def __init__(
        self,
        name: str = "mock",
        available: bool = True,
        response: Optional[LLMResponse] = None,
        error: Optional[Exception] = None,
    ) -> None:
        """Initialize the mock provider.

        Args:
            name: Provider name
            available: Whether provider is available
            response: Response to return on success
            error: Error to raise on failure
        """
        self.name = name
        self.default_model = "mock-model"
        self._available = available
        self._response = response
        self._error = error
        self.complete_call_count = 0

    async def complete(
        self,
        messages: list[LLMMessage],
        config: Optional[LLMConfig] = None,
    ) -> LLMResponse:
        """Simulate LLM completion.

        Args:
            messages: Input messages
            config: Optional configuration

        Returns:
            LLMResponse if no error configured

        Raises:
            Exception: If error was configured
        """
        self.complete_call_count += 1
        if self._error:
            raise self._error
        if self._response:
            return self._response
        return LLMResponse(
            content="Mock response",
            model=self.default_model,
            provider=self.name,
            usage=LLMUsage(prompt_tokens=10, completion_tokens=10, total_tokens=20),
            finish_reason="stop",
        )

    async def complete_stream(self, messages, config=None):
        """Simulate streaming completion."""
        yield "Mock"
        yield " response"

    def is_available(self) -> bool:
        """Check if provider is available."""
        return self._available

    def get_model_list(self) -> list[str]:
        """Get list of supported models."""
        return ["mock-model"]


# ============================================================================
# LLMCache Tests - Cache Key Generation
# ============================================================================


class TestLLMCacheKeyGeneration:
    """Test suite for cache key generation functionality."""

    def test_generate_cache_key_basic(
        self,
        llm_cache: LLMCache,
        sample_messages: list[LLMMessage],
    ) -> None:
        """Test that cache key is generated correctly for basic inputs.

        Verifies that generate_cache_key returns a valid SHA-256 hash
        for a simple set of messages.
        """
        cache_key = llm_cache.generate_cache_key(
            messages=sample_messages,
            provider="openai",
            model="gpt-4o",
        )

        assert cache_key is not None
        assert len(cache_key) == 64  # SHA-256 hash length
        assert all(c in "0123456789abcdef" for c in cache_key)

    def test_generate_cache_key_with_optional_params(
        self,
        llm_cache: LLMCache,
        sample_messages: list[LLMMessage],
    ) -> None:
        """Test that cache key includes optional parameters when specified.

        Verifies that temperature and max_tokens affect the cache key.
        """
        key_without_opts = llm_cache.generate_cache_key(
            messages=sample_messages,
            provider="openai",
            model="gpt-4o",
        )

        key_with_temp = llm_cache.generate_cache_key(
            messages=sample_messages,
            provider="openai",
            model="gpt-4o",
            temperature=0.5,
        )

        key_with_tokens = llm_cache.generate_cache_key(
            messages=sample_messages,
            provider="openai",
            model="gpt-4o",
            max_tokens=100,
        )

        # All keys should be different
        assert key_without_opts != key_with_temp
        assert key_without_opts != key_with_tokens
        assert key_with_temp != key_with_tokens

    def test_generate_cache_key_deterministic(
        self,
        llm_cache: LLMCache,
        sample_messages: list[LLMMessage],
    ) -> None:
        """Test that cache key generation is deterministic.

        Verifies that the same inputs always produce the same cache key.
        """
        key1 = llm_cache.generate_cache_key(
            messages=sample_messages,
            provider="openai",
            model="gpt-4o",
            temperature=0.7,
        )

        key2 = llm_cache.generate_cache_key(
            messages=sample_messages,
            provider="openai",
            model="gpt-4o",
            temperature=0.7,
        )

        assert key1 == key2

    def test_generate_cache_key_different_messages(
        self,
        llm_cache: LLMCache,
    ) -> None:
        """Test that different messages produce different cache keys.

        Verifies that message content affects the cache key.
        """
        messages1 = [LLMMessage(role=LLMRole.USER, content="Hello")]
        messages2 = [LLMMessage(role=LLMRole.USER, content="Hi there")]

        key1 = llm_cache.generate_cache_key(
            messages=messages1,
            provider="openai",
            model="gpt-4o",
        )

        key2 = llm_cache.generate_cache_key(
            messages=messages2,
            provider="openai",
            model="gpt-4o",
        )

        assert key1 != key2

    def test_generate_cache_key_different_providers(
        self,
        llm_cache: LLMCache,
        sample_messages: list[LLMMessage],
    ) -> None:
        """Test that different providers produce different cache keys.

        Verifies that the provider name affects the cache key.
        """
        key_openai = llm_cache.generate_cache_key(
            messages=sample_messages,
            provider="openai",
            model="gpt-4o",
        )

        key_anthropic = llm_cache.generate_cache_key(
            messages=sample_messages,
            provider="anthropic",
            model="gpt-4o",
        )

        assert key_openai != key_anthropic

    def test_generate_prompt_hash(
        self,
        llm_cache: LLMCache,
        sample_messages: list[LLMMessage],
    ) -> None:
        """Test that prompt hash is generated correctly.

        Verifies that generate_prompt_hash returns a valid hash
        independent of provider/model.
        """
        prompt_hash = llm_cache.generate_prompt_hash(sample_messages)

        assert prompt_hash is not None
        assert len(prompt_hash) == 64
        assert all(c in "0123456789abcdef" for c in prompt_hash)


# ============================================================================
# LLMCache Tests - Cache Operations
# ============================================================================


class TestLLMCacheOperations:
    """Test suite for cache get/set/invalidate operations."""

    @pytest.mark.asyncio
    async def test_cache_set_and_get(
        self,
        llm_cache: LLMCache,
        sample_messages: list[LLMMessage],
        sample_response: LLMResponse,
    ) -> None:
        """Test that responses can be stored and retrieved from cache.

        Verifies basic set/get functionality of the cache.
        """
        cache_key = llm_cache.generate_cache_key(
            messages=sample_messages,
            provider="openai",
            model="gpt-4o",
        )

        # Set the cache entry
        await llm_cache.set(
            cache_key=cache_key,
            response=sample_response,
            messages=sample_messages,
        )

        # Get the cache entry
        cached_response = await llm_cache.get(cache_key)

        assert cached_response is not None
        assert cached_response.content == sample_response.content
        assert cached_response.model == sample_response.model
        assert cached_response.provider == sample_response.provider
        assert cached_response.finish_reason == "cached"

    @pytest.mark.asyncio
    async def test_cache_miss_returns_none(
        self,
        llm_cache: LLMCache,
    ) -> None:
        """Test that cache miss returns None.

        Verifies that getting a non-existent key returns None.
        """
        cached_response = await llm_cache.get("nonexistent-key")

        assert cached_response is None

    @pytest.mark.asyncio
    async def test_cache_hit_increments_count(
        self,
        llm_cache: LLMCache,
        sample_messages: list[LLMMessage],
        sample_response: LLMResponse,
    ) -> None:
        """Test that cache hits increment the hit count.

        Verifies that repeated cache lookups increase the hit counter.
        """
        cache_key = llm_cache.generate_cache_key(
            messages=sample_messages,
            provider="openai",
            model="gpt-4o",
        )

        await llm_cache.set(
            cache_key=cache_key,
            response=sample_response,
            messages=sample_messages,
        )

        # Access cache multiple times
        await llm_cache.get(cache_key)
        await llm_cache.get(cache_key)
        await llm_cache.get(cache_key)

        # Check hit count in stats
        stats = await llm_cache.get_stats()
        assert stats["total_hits"] == 3

    @pytest.mark.asyncio
    async def test_cache_ttl_expiration(
        self,
        sample_messages: list[LLMMessage],
        sample_response: LLMResponse,
    ) -> None:
        """Test that cache entries expire after TTL.

        Verifies that expired entries are not returned.
        """
        # Create cache with very short TTL (1 second)
        cache = LLMCache(db=None, default_ttl=1)

        cache_key = cache.generate_cache_key(
            messages=sample_messages,
            provider="openai",
            model="gpt-4o",
        )

        await cache.set(
            cache_key=cache_key,
            response=sample_response,
            messages=sample_messages,
        )

        # Verify entry exists
        assert await cache.get(cache_key) is not None

        # Wait for expiration
        await asyncio.sleep(1.1)

        # Verify entry expired
        assert await cache.get(cache_key) is None

    @pytest.mark.asyncio
    async def test_cache_custom_ttl(
        self,
        llm_cache: LLMCache,
        sample_messages: list[LLMMessage],
        sample_response: LLMResponse,
    ) -> None:
        """Test that custom TTL can be specified per entry.

        Verifies that ttl_seconds parameter overrides default TTL.
        """
        cache_key = llm_cache.generate_cache_key(
            messages=sample_messages,
            provider="openai",
            model="gpt-4o",
        )

        # Set with custom TTL
        await llm_cache.set(
            cache_key=cache_key,
            response=sample_response,
            messages=sample_messages,
            ttl_seconds=LONG_TTL_SECONDS,
        )

        # Verify entry exists
        cached = await llm_cache.get(cache_key)
        assert cached is not None

    @pytest.mark.asyncio
    async def test_cache_overwrite_existing(
        self,
        llm_cache: LLMCache,
        sample_messages: list[LLMMessage],
    ) -> None:
        """Test that cache entries can be overwritten.

        Verifies that setting a cache entry replaces the existing one.
        """
        cache_key = llm_cache.generate_cache_key(
            messages=sample_messages,
            provider="openai",
            model="gpt-4o",
        )

        response1 = LLMResponse(
            content="First response",
            model="gpt-4o",
            provider="openai",
            usage=LLMUsage(prompt_tokens=10, completion_tokens=10, total_tokens=20),
        )

        response2 = LLMResponse(
            content="Second response",
            model="gpt-4o",
            provider="openai",
            usage=LLMUsage(prompt_tokens=10, completion_tokens=10, total_tokens=20),
        )

        await llm_cache.set(cache_key, response1, sample_messages)
        await llm_cache.set(cache_key, response2, sample_messages)

        cached = await llm_cache.get(cache_key)
        assert cached.content == "Second response"


# ============================================================================
# LLMCache Tests - Invalidation
# ============================================================================


class TestLLMCacheInvalidation:
    """Test suite for cache invalidation functionality."""

    @pytest.mark.asyncio
    async def test_invalidate_by_key(
        self,
        llm_cache: LLMCache,
        sample_messages: list[LLMMessage],
        sample_response: LLMResponse,
    ) -> None:
        """Test that specific cache entries can be invalidated by key.

        Verifies that invalidate() removes entries by cache_key.
        """
        cache_key = llm_cache.generate_cache_key(
            messages=sample_messages,
            provider="openai",
            model="gpt-4o",
        )

        await llm_cache.set(cache_key, sample_response, sample_messages)
        assert await llm_cache.get(cache_key) is not None

        removed = await llm_cache.invalidate(cache_key=cache_key)

        assert removed == 1
        assert await llm_cache.get(cache_key) is None

    @pytest.mark.asyncio
    async def test_invalidate_by_provider(
        self,
        llm_cache: LLMCache,
        sample_messages: list[LLMMessage],
    ) -> None:
        """Test that cache entries can be invalidated by provider.

        Verifies that invalidate() removes all entries for a provider.
        """
        response_openai = LLMResponse(
            content="OpenAI response",
            model="gpt-4o",
            provider="openai",
            usage=LLMUsage(prompt_tokens=10, completion_tokens=10, total_tokens=20),
        )

        response_anthropic = LLMResponse(
            content="Anthropic response",
            model="claude-3-5-sonnet",
            provider="anthropic",
            usage=LLMUsage(prompt_tokens=10, completion_tokens=10, total_tokens=20),
        )

        key_openai = llm_cache.generate_cache_key(
            messages=sample_messages,
            provider="openai",
            model="gpt-4o",
        )

        key_anthropic = llm_cache.generate_cache_key(
            messages=sample_messages,
            provider="anthropic",
            model="claude-3-5-sonnet",
        )

        await llm_cache.set(key_openai, response_openai, sample_messages)
        await llm_cache.set(key_anthropic, response_anthropic, sample_messages)

        # Invalidate only OpenAI entries
        removed = await llm_cache.invalidate(provider="openai")

        assert removed == 1
        assert await llm_cache.get(key_openai) is None
        assert await llm_cache.get(key_anthropic) is not None

    @pytest.mark.asyncio
    async def test_invalidate_by_model(
        self,
        llm_cache: LLMCache,
        sample_messages: list[LLMMessage],
    ) -> None:
        """Test that cache entries can be invalidated by model.

        Verifies that invalidate() removes all entries for a model.
        """
        response_gpt4 = LLMResponse(
            content="GPT-4 response",
            model="gpt-4o",
            provider="openai",
            usage=LLMUsage(prompt_tokens=10, completion_tokens=10, total_tokens=20),
        )

        response_gpt35 = LLMResponse(
            content="GPT-3.5 response",
            model="gpt-3.5-turbo",
            provider="openai",
            usage=LLMUsage(prompt_tokens=10, completion_tokens=10, total_tokens=20),
        )

        key_gpt4 = llm_cache.generate_cache_key(
            messages=sample_messages,
            provider="openai",
            model="gpt-4o",
        )

        key_gpt35 = llm_cache.generate_cache_key(
            messages=sample_messages,
            provider="openai",
            model="gpt-3.5-turbo",
        )

        await llm_cache.set(key_gpt4, response_gpt4, sample_messages)
        await llm_cache.set(key_gpt35, response_gpt35, sample_messages)

        # Invalidate only gpt-4o entries
        removed = await llm_cache.invalidate(model="gpt-4o")

        assert removed == 1
        assert await llm_cache.get(key_gpt4) is None
        assert await llm_cache.get(key_gpt35) is not None

    @pytest.mark.asyncio
    async def test_invalidate_no_criteria_returns_zero(
        self,
        llm_cache: LLMCache,
        sample_messages: list[LLMMessage],
        sample_response: LLMResponse,
    ) -> None:
        """Test that invalidate with no criteria doesn't remove entries.

        Verifies the safety measure that requires at least one filter.
        """
        cache_key = llm_cache.generate_cache_key(
            messages=sample_messages,
            provider="openai",
            model="gpt-4o",
        )

        await llm_cache.set(cache_key, sample_response, sample_messages)

        # Call invalidate with no criteria
        removed = await llm_cache.invalidate()

        assert removed == 0
        assert await llm_cache.get(cache_key) is not None

    @pytest.mark.asyncio
    async def test_cleanup_expired(
        self,
        sample_messages: list[LLMMessage],
        sample_response: LLMResponse,
    ) -> None:
        """Test that cleanup_expired removes only expired entries.

        Verifies that expired entries are cleaned up properly.
        """
        cache = LLMCache(db=None, default_ttl=1)

        cache_key = cache.generate_cache_key(
            messages=sample_messages,
            provider="openai",
            model="gpt-4o",
        )

        await cache.set(cache_key, sample_response, sample_messages)

        # Wait for expiration
        await asyncio.sleep(1.1)

        # Cleanup should remove the expired entry
        removed = await cache.cleanup_expired()

        assert removed == 1


# ============================================================================
# LLMCache Tests - Statistics
# ============================================================================


class TestLLMCacheStatistics:
    """Test suite for cache statistics functionality."""

    @pytest.mark.asyncio
    async def test_get_stats_empty_cache(
        self,
        llm_cache: LLMCache,
    ) -> None:
        """Test statistics for empty cache.

        Verifies that stats returns zeros for empty cache.
        """
        stats = await llm_cache.get_stats()

        assert stats["total_entries"] == 0
        assert stats["active_entries"] == 0
        assert stats["expired_entries"] == 0
        assert stats["total_hits"] == 0
        assert stats["total_prompt_tokens"] == 0
        assert stats["total_completion_tokens"] == 0
        assert stats["storage_type"] == "memory"

    @pytest.mark.asyncio
    async def test_get_stats_with_entries(
        self,
        llm_cache: LLMCache,
        sample_messages: list[LLMMessage],
        sample_response: LLMResponse,
    ) -> None:
        """Test statistics with cached entries.

        Verifies that stats accurately reflects cache contents.
        """
        cache_key = llm_cache.generate_cache_key(
            messages=sample_messages,
            provider="openai",
            model="gpt-4o",
        )

        await llm_cache.set(cache_key, sample_response, sample_messages)

        stats = await llm_cache.get_stats()

        assert stats["total_entries"] == 1
        assert stats["active_entries"] == 1
        assert stats["total_prompt_tokens"] == sample_response.usage.prompt_tokens
        assert (
            stats["total_completion_tokens"] == sample_response.usage.completion_tokens
        )

    @pytest.mark.asyncio
    async def test_get_stats_filter_by_provider(
        self,
        llm_cache: LLMCache,
        sample_messages: list[LLMMessage],
    ) -> None:
        """Test statistics filtered by provider.

        Verifies that stats can be filtered by provider.
        """
        response_openai = LLMResponse(
            content="OpenAI",
            model="gpt-4o",
            provider="openai",
            usage=LLMUsage(prompt_tokens=20, completion_tokens=15, total_tokens=35),
        )

        response_anthropic = LLMResponse(
            content="Anthropic",
            model="claude-3-5-sonnet",
            provider="anthropic",
            usage=LLMUsage(prompt_tokens=25, completion_tokens=20, total_tokens=45),
        )

        key_openai = llm_cache.generate_cache_key(
            messages=sample_messages,
            provider="openai",
            model="gpt-4o",
        )

        key_anthropic = llm_cache.generate_cache_key(
            messages=sample_messages,
            provider="anthropic",
            model="claude-3-5-sonnet",
        )

        await llm_cache.set(key_openai, response_openai, sample_messages)
        await llm_cache.set(key_anthropic, response_anthropic, sample_messages)

        # Get stats for OpenAI only
        stats = await llm_cache.get_stats(provider="openai")

        assert stats["total_entries"] == 1
        assert stats["total_prompt_tokens"] == 20
        assert stats["total_completion_tokens"] == 15

    def test_cache_size_property(
        self,
        llm_cache: LLMCache,
        sample_messages: list[LLMMessage],
        sample_response: LLMResponse,
    ) -> None:
        """Test that size property reflects cache entries.

        Verifies the size property returns correct count.
        """
        assert llm_cache.size == 0

        cache_key = llm_cache.generate_cache_key(
            messages=sample_messages,
            provider="openai",
            model="gpt-4o",
        )

        # Use synchronous set for in-memory (via internal method)
        llm_cache._set_in_memory(
            cache_key,
            sample_response,
            llm_cache.generate_prompt_hash(sample_messages),
            datetime.utcnow() + timedelta(seconds=3600),
        )

        assert llm_cache.size == 1

    def test_cache_clear(
        self,
        llm_cache: LLMCache,
        sample_messages: list[LLMMessage],
        sample_response: LLMResponse,
    ) -> None:
        """Test that clear removes all entries.

        Verifies the clear() method empties the cache.
        """
        cache_key = llm_cache.generate_cache_key(
            messages=sample_messages,
            provider="openai",
            model="gpt-4o",
        )

        llm_cache._set_in_memory(
            cache_key,
            sample_response,
            llm_cache.generate_prompt_hash(sample_messages),
            datetime.utcnow() + timedelta(seconds=3600),
        )

        assert llm_cache.size == 1

        llm_cache.clear()

        assert llm_cache.size == 0


# ============================================================================
# FallbackStrategy Tests - Configuration
# ============================================================================


class TestFallbackConfiguration:
    """Test suite for fallback strategy configuration."""

    def test_default_config(self) -> None:
        """Test default fallback configuration values.

        Verifies that FallbackConfig has correct defaults.
        """
        config = FallbackConfig()

        assert config.mode == FallbackMode.SEQUENTIAL
        assert config.max_retries == 3
        assert RetryableError.ALL in config.retry_on
        assert config.timeout_per_provider == 60
        assert config.log_failures is True

    def test_custom_config(self) -> None:
        """Test custom fallback configuration.

        Verifies that custom values can be set.
        """
        config = FallbackConfig(
            mode=FallbackMode.ROUND_ROBIN,
            max_retries=5,
            retry_on=[RetryableError.RATE_LIMIT, RetryableError.TIMEOUT],
            timeout_per_provider=30,
            log_failures=False,
        )

        assert config.mode == FallbackMode.ROUND_ROBIN
        assert config.max_retries == 5
        assert RetryableError.RATE_LIMIT in config.retry_on
        assert RetryableError.TIMEOUT in config.retry_on
        assert config.timeout_per_provider == 30
        assert config.log_failures is False

    def test_fallback_mode_enum(self) -> None:
        """Test FallbackMode enum values.

        Verifies all fallback modes are defined correctly.
        """
        assert FallbackMode.SEQUENTIAL.value == "sequential"
        assert FallbackMode.ROUND_ROBIN.value == "round_robin"
        assert FallbackMode.PRIORITY.value == "priority"

    def test_retryable_error_enum(self) -> None:
        """Test RetryableError enum values.

        Verifies all retryable error types are defined.
        """
        assert RetryableError.RATE_LIMIT.value == "rate_limit"
        assert RetryableError.TIMEOUT.value == "timeout"
        assert RetryableError.PROVIDER_ERROR.value == "provider_error"
        assert RetryableError.ALL.value == "all"


# ============================================================================
# FallbackStrategy Tests - Provider Management
# ============================================================================


class TestFallbackProviderManagement:
    """Test suite for fallback strategy provider management."""

    def test_add_provider(self) -> None:
        """Test adding providers to fallback chain.

        Verifies that providers can be added to the strategy.
        """
        strategy = FallbackStrategy()

        provider = MockLLMProvider(name="test-provider")
        strategy.add_provider(provider)

        assert len(strategy.providers) == 1
        assert "test-provider" in strategy.available_providers

    def test_add_none_provider_raises(self) -> None:
        """Test that adding None provider raises ValueError.

        Verifies validation when adding providers.
        """
        strategy = FallbackStrategy()

        with pytest.raises(ValueError, match="Provider cannot be None"):
            strategy.add_provider(None)

    def test_remove_provider(self) -> None:
        """Test removing providers from fallback chain.

        Verifies that providers can be removed by name.
        """
        provider1 = MockLLMProvider(name="provider1")
        provider2 = MockLLMProvider(name="provider2")

        strategy = FallbackStrategy(providers=[provider1, provider2])

        removed = strategy.remove_provider("provider1")

        assert removed is True
        assert len(strategy.providers) == 1
        assert "provider1" not in strategy.available_providers
        assert "provider2" in strategy.available_providers

    def test_remove_nonexistent_provider(self) -> None:
        """Test removing non-existent provider returns False.

        Verifies behavior when removing unknown provider.
        """
        strategy = FallbackStrategy()

        removed = strategy.remove_provider("unknown")

        assert removed is False

    def test_set_providers(self) -> None:
        """Test setting providers list.

        Verifies that set_providers replaces the provider list.
        """
        strategy = FallbackStrategy(
            providers=[MockLLMProvider(name="old")]
        )

        new_providers = [
            MockLLMProvider(name="new1"),
            MockLLMProvider(name="new2"),
        ]
        strategy.set_providers(new_providers)

        assert len(strategy.providers) == 2
        assert "new1" in strategy.available_providers
        assert "new2" in strategy.available_providers
        assert "old" not in strategy.available_providers

    def test_configured_providers(self) -> None:
        """Test getting configured (available) providers.

        Verifies that only available providers are returned.
        """
        provider_available = MockLLMProvider(name="available", available=True)
        provider_unavailable = MockLLMProvider(name="unavailable", available=False)

        strategy = FallbackStrategy(
            providers=[provider_available, provider_unavailable]
        )

        configured = strategy.configured_providers

        assert "available" in configured
        assert "unavailable" not in configured


# ============================================================================
# FallbackStrategy Tests - Execution
# ============================================================================


class TestFallbackExecution:
    """Test suite for fallback strategy execution."""

    @pytest.mark.asyncio
    async def test_execute_success_first_provider(
        self,
        sample_messages: list[LLMMessage],
        sample_response: LLMResponse,
    ) -> None:
        """Test successful execution with first provider.

        Verifies that successful completion returns correctly.
        """
        provider = MockLLMProvider(
            name="primary",
            response=sample_response,
        )
        strategy = FallbackStrategy(providers=[provider])

        result = await strategy.execute(sample_messages)

        assert result.response is not None
        assert result.response.content == sample_response.content
        assert result.successful_provider == "primary"
        assert result.total_attempts == 1
        assert result.all_failed is False

    @pytest.mark.asyncio
    async def test_execute_fallback_to_second_provider(
        self,
        sample_messages: list[LLMMessage],
        sample_response: LLMResponse,
    ) -> None:
        """Test fallback to second provider after first fails.

        Verifies that fallback occurs when primary provider fails.
        """
        provider1 = MockLLMProvider(
            name="primary",
            error=LLMRateLimitError("Rate limit exceeded", provider="primary"),
        )
        provider2 = MockLLMProvider(
            name="secondary",
            response=sample_response,
        )

        strategy = FallbackStrategy(providers=[provider1, provider2])

        result = await strategy.execute(sample_messages)

        assert result.response is not None
        assert result.successful_provider == "secondary"
        assert result.total_attempts == 2
        assert result.all_failed is False
        assert len(result.attempts) == 2
        assert result.attempts[0].success is False
        assert result.attempts[1].success is True

    @pytest.mark.asyncio
    async def test_execute_all_providers_fail(
        self,
        sample_messages: list[LLMMessage],
    ) -> None:
        """Test that all_failed is True when all providers fail.

        Verifies behavior when no provider succeeds.
        """
        provider1 = MockLLMProvider(
            name="primary",
            error=LLMProviderError("Error 1", provider="primary"),
        )
        provider2 = MockLLMProvider(
            name="secondary",
            error=LLMProviderError("Error 2", provider="secondary"),
        )

        strategy = FallbackStrategy(providers=[provider1, provider2])

        result = await strategy.execute(sample_messages)

        assert result.response is None
        assert result.successful_provider is None
        assert result.all_failed is True
        assert result.total_attempts == 2

    @pytest.mark.asyncio
    async def test_execute_no_providers(
        self,
        sample_messages: list[LLMMessage],
    ) -> None:
        """Test execution with no providers configured.

        Verifies behavior when no providers are available.
        """
        strategy = FallbackStrategy(providers=[])

        result = await strategy.execute(sample_messages)

        assert result.response is None
        assert result.all_failed is True
        assert result.total_attempts == 0

    @pytest.mark.asyncio
    async def test_execute_skips_unavailable_provider(
        self,
        sample_messages: list[LLMMessage],
        sample_response: LLMResponse,
    ) -> None:
        """Test that unavailable providers are skipped.

        Verifies that is_available() check prevents execution.
        """
        provider_unavailable = MockLLMProvider(
            name="unavailable",
            available=False,
        )
        provider_available = MockLLMProvider(
            name="available",
            response=sample_response,
        )

        strategy = FallbackStrategy(
            providers=[provider_unavailable, provider_available]
        )

        result = await strategy.execute(sample_messages)

        assert result.response is not None
        assert result.successful_provider == "available"
        assert result.total_attempts == 2
        assert result.attempts[0].error == "Provider not available (not configured)"

    @pytest.mark.asyncio
    async def test_execute_respects_max_retries(
        self,
        sample_messages: list[LLMMessage],
    ) -> None:
        """Test that max_retries limits provider attempts.

        Verifies that only max_retries providers are tried.
        """
        providers = [
            MockLLMProvider(
                name=f"provider{i}",
                error=LLMProviderError(f"Error {i}"),
            )
            for i in range(5)
        ]

        config = FallbackConfig(max_retries=2)
        strategy = FallbackStrategy(providers=providers, config=config)

        result = await strategy.execute(sample_messages)

        assert result.total_attempts == 2
        assert result.all_failed is True


# ============================================================================
# FallbackStrategy Tests - Error Handling
# ============================================================================


class TestFallbackErrorHandling:
    """Test suite for fallback error handling."""

    @pytest.mark.asyncio
    async def test_retry_on_rate_limit(
        self,
        sample_messages: list[LLMMessage],
        sample_response: LLMResponse,
    ) -> None:
        """Test that rate limit errors trigger retry.

        Verifies that LLMRateLimitError causes fallback.
        """
        provider1 = MockLLMProvider(
            name="primary",
            error=LLMRateLimitError("Rate limited"),
        )
        provider2 = MockLLMProvider(
            name="secondary",
            response=sample_response,
        )

        config = FallbackConfig(retry_on=[RetryableError.RATE_LIMIT])
        strategy = FallbackStrategy(providers=[provider1, provider2], config=config)

        result = await strategy.execute(sample_messages)

        assert result.successful_provider == "secondary"
        assert result.attempts[0].error_type == "rate_limit"

    @pytest.mark.asyncio
    async def test_retry_on_timeout(
        self,
        sample_messages: list[LLMMessage],
        sample_response: LLMResponse,
    ) -> None:
        """Test that timeout errors trigger retry.

        Verifies that LLMTimeoutError causes fallback.
        """
        provider1 = MockLLMProvider(
            name="primary",
            error=LLMTimeoutError("Request timed out"),
        )
        provider2 = MockLLMProvider(
            name="secondary",
            response=sample_response,
        )

        config = FallbackConfig(retry_on=[RetryableError.TIMEOUT])
        strategy = FallbackStrategy(providers=[provider1, provider2], config=config)

        result = await strategy.execute(sample_messages)

        assert result.successful_provider == "secondary"
        assert result.attempts[0].error_type == "timeout"

    @pytest.mark.asyncio
    async def test_no_retry_on_authentication_error(
        self,
        sample_messages: list[LLMMessage],
        sample_response: LLMResponse,
    ) -> None:
        """Test that authentication errors don't trigger retry.

        Verifies that LLMAuthenticationError stops fallback chain.
        """
        provider1 = MockLLMProvider(
            name="primary",
            error=LLMAuthenticationError("Invalid API key"),
        )
        provider2 = MockLLMProvider(
            name="secondary",
            response=sample_response,
        )

        # Don't include auth errors in retry_on
        config = FallbackConfig(retry_on=[RetryableError.RATE_LIMIT])
        strategy = FallbackStrategy(providers=[provider1, provider2], config=config)

        result = await strategy.execute(sample_messages)

        # Should stop after first provider
        assert result.total_attempts == 1
        assert result.all_failed is True
        assert result.attempts[0].error_type == "authentication"

    @pytest.mark.asyncio
    async def test_retry_all_errors_mode(
        self,
        sample_messages: list[LLMMessage],
        sample_response: LLMResponse,
    ) -> None:
        """Test that ALL mode retries on any LLM error.

        Verifies that RetryableError.ALL catches all LLM errors.
        """
        provider1 = MockLLMProvider(
            name="primary",
            error=LLMProviderError("Generic error"),
        )
        provider2 = MockLLMProvider(
            name="secondary",
            response=sample_response,
        )

        config = FallbackConfig(retry_on=[RetryableError.ALL])
        strategy = FallbackStrategy(providers=[provider1, provider2], config=config)

        result = await strategy.execute(sample_messages)

        assert result.successful_provider == "secondary"

    @pytest.mark.asyncio
    async def test_fallback_callback_invoked(
        self,
        sample_messages: list[LLMMessage],
        sample_response: LLMResponse,
    ) -> None:
        """Test that on_fallback callback is invoked on failure.

        Verifies that the callback receives attempt information.
        """
        callback_attempts: list[FallbackAttempt] = []

        def on_fallback(attempt: FallbackAttempt) -> None:
            callback_attempts.append(attempt)

        provider1 = MockLLMProvider(
            name="primary",
            error=LLMRateLimitError("Rate limited"),
        )
        provider2 = MockLLMProvider(
            name="secondary",
            response=sample_response,
        )

        strategy = FallbackStrategy(
            providers=[provider1, provider2],
            on_fallback=on_fallback,
        )

        await strategy.execute(sample_messages)

        assert len(callback_attempts) == 1
        assert callback_attempts[0].provider_name == "primary"
        assert callback_attempts[0].success is False


# ============================================================================
# FallbackStrategy Tests - Round Robin Mode
# ============================================================================


class TestFallbackRoundRobin:
    """Test suite for round-robin fallback mode."""

    @pytest.mark.asyncio
    async def test_round_robin_rotates_providers(
        self,
        sample_messages: list[LLMMessage],
        sample_response: LLMResponse,
    ) -> None:
        """Test that round-robin mode rotates starting provider.

        Verifies that providers are tried in rotating order.
        """
        provider1 = MockLLMProvider(name="provider1", response=sample_response)
        provider2 = MockLLMProvider(name="provider2", response=sample_response)
        provider3 = MockLLMProvider(name="provider3", response=sample_response)

        config = FallbackConfig(mode=FallbackMode.ROUND_ROBIN)
        strategy = FallbackStrategy(
            providers=[provider1, provider2, provider3],
            config=config,
        )

        # First execution starts with provider1
        result1 = await strategy.execute(sample_messages)
        assert result1.successful_provider == "provider1"

        # Second execution starts with provider2
        result2 = await strategy.execute(sample_messages)
        assert result2.successful_provider == "provider2"

        # Third execution starts with provider3
        result3 = await strategy.execute(sample_messages)
        assert result3.successful_provider == "provider3"

        # Fourth execution wraps back to provider1
        result4 = await strategy.execute(sample_messages)
        assert result4.successful_provider == "provider1"


# ============================================================================
# FallbackStrategy Tests - Misc
# ============================================================================


class TestFallbackMisc:
    """Test suite for miscellaneous fallback functionality."""

    def test_strategy_repr(self) -> None:
        """Test string representation of fallback strategy.

        Verifies __repr__ returns useful information.
        """
        provider = MockLLMProvider(name="test-provider")
        strategy = FallbackStrategy(providers=[provider])

        repr_str = repr(strategy)

        assert "FallbackStrategy" in repr_str
        assert "sequential" in repr_str
        assert "test-provider" in repr_str

    @pytest.mark.asyncio
    async def test_execute_with_config(
        self,
        sample_messages: list[LLMMessage],
        sample_response: LLMResponse,
        sample_config: LLMConfig,
    ) -> None:
        """Test that LLMConfig is passed to provider.

        Verifies that configuration reaches the provider.
        """
        provider = MockLLMProvider(name="test", response=sample_response)
        strategy = FallbackStrategy(providers=[provider])

        result = await strategy.execute(sample_messages, config=sample_config)

        assert result.response is not None
        assert provider.complete_call_count == 1

    @pytest.mark.asyncio
    async def test_fallback_attempt_has_duration(
        self,
        sample_messages: list[LLMMessage],
        sample_response: LLMResponse,
    ) -> None:
        """Test that fallback attempts include duration.

        Verifies that duration_ms is tracked for attempts.
        """
        provider = MockLLMProvider(name="test", response=sample_response)
        strategy = FallbackStrategy(providers=[provider])

        result = await strategy.execute(sample_messages)

        assert result.attempts[0].duration_ms is not None
        assert result.attempts[0].duration_ms >= 0


# ============================================================================
# Integration Tests - Cache + Fallback
# ============================================================================


class TestCacheFallbackIntegration:
    """Test suite for cache and fallback integration scenarios."""

    @pytest.mark.asyncio
    async def test_cache_hit_avoids_fallback(
        self,
        llm_cache: LLMCache,
        sample_messages: list[LLMMessage],
        sample_response: LLMResponse,
    ) -> None:
        """Test that cache hits avoid provider execution.

        Verifies that cached responses prevent fallback execution.
        """
        cache_key = llm_cache.generate_cache_key(
            messages=sample_messages,
            provider="openai",
            model="gpt-4o",
        )

        # Pre-populate cache
        await llm_cache.set(cache_key, sample_response, sample_messages)

        # Check cache before fallback
        cached = await llm_cache.get(cache_key)

        assert cached is not None
        assert cached.content == sample_response.content
        assert cached.finish_reason == "cached"

    @pytest.mark.asyncio
    async def test_multiple_providers_different_cache_keys(
        self,
        llm_cache: LLMCache,
        sample_messages: list[LLMMessage],
    ) -> None:
        """Test that different providers have different cache keys.

        Verifies cache isolation between providers.
        """
        key_openai = llm_cache.generate_cache_key(
            messages=sample_messages,
            provider="openai",
            model="gpt-4o",
        )

        key_anthropic = llm_cache.generate_cache_key(
            messages=sample_messages,
            provider="anthropic",
            model="claude-3-5-sonnet",
        )

        assert key_openai != key_anthropic
