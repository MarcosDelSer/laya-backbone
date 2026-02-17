"""Unit tests for LLM token tracking and client services.

Tests for TokenTracker cost calculation, usage logging, statistics,
and LLMClient integration with caching, fallback, and provider management.
"""

from __future__ import annotations

from datetime import datetime
from decimal import Decimal
from typing import Any, Optional
from unittest.mock import AsyncMock, MagicMock, patch
from uuid import uuid4

import pytest
import pytest_asyncio

from app.llm.base import BaseLLMProvider
from app.llm.cache import LLMCache
from app.llm.client import (
    CompletionFailedError,
    LLMClient,
    LLMClientError,
    NoProvidersAvailableError,
)
from app.llm.exceptions import (
    LLMError,
    LLMProviderError,
    LLMRateLimitError,
    LLMTimeoutError,
)
from app.llm.fallback import FallbackConfig, FallbackMode, FallbackResult
from app.llm.token_tracker import (
    DEFAULT_PRICING,
    MODEL_PRICING,
    CHARS_PER_TOKEN_ESTIMATE,
    ModelPricing,
    TokenEstimationError,
    TokenTracker,
    TokenTrackerError,
    UsageStatistics,
)
from app.llm.types import LLMConfig, LLMMessage, LLMResponse, LLMRole, LLMUsage


# ============================================================================
# Fixtures
# ============================================================================


@pytest.fixture
def token_tracker() -> TokenTracker:
    """Create a TokenTracker without database for testing."""
    return TokenTracker(db=None)


@pytest.fixture
def sample_messages() -> list[LLMMessage]:
    """Create sample messages for testing."""
    return [
        LLMMessage(role=LLMRole.SYSTEM, content="You are a helpful assistant."),
        LLMMessage(role=LLMRole.USER, content="Hello, how are you?"),
    ]


@pytest.fixture
def sample_response() -> LLMResponse:
    """Create a sample LLM response for testing."""
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
def sample_config() -> LLMConfig:
    """Create sample LLM configuration for testing."""
    return LLMConfig(
        model="gpt-4o",
        temperature=0.7,
        max_tokens=1000,
        timeout=30,
    )


class MockLLMProvider(BaseLLMProvider):
    """Mock LLM provider for testing."""

    def __init__(
        self,
        name: str = "mock",
        available: bool = True,
        response: Optional[LLMResponse] = None,
        error: Optional[Exception] = None,
    ) -> None:
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
        yield "Mock"
        yield " response"

    def is_available(self) -> bool:
        return self._available

    def get_model_list(self) -> list[str]:
        return ["mock-model"]


# ============================================================================
# TokenTracker Tests - Token Estimation
# ============================================================================


class TestTokenEstimation:
    """Test suite for token estimation functionality."""

    def test_estimate_tokens_simple(self, token_tracker: TokenTracker) -> None:
        """Test basic token estimation."""
        text = "Hello, world!"  # 13 characters

        result = token_tracker.estimate_tokens(text)

        # Should be approximately 13 / 4 + 1 = 4 tokens
        assert result > 0
        assert result < 10

    def test_estimate_tokens_empty_string(self, token_tracker: TokenTracker) -> None:
        """Test token estimation for empty string."""
        result = token_tracker.estimate_tokens("")

        assert result == 0

    def test_estimate_tokens_long_text(self, token_tracker: TokenTracker) -> None:
        """Test token estimation for longer text."""
        text = "This is a longer piece of text that should produce more tokens. " * 10

        result = token_tracker.estimate_tokens(text)

        # Should scale with length
        assert result > 50

    def test_estimate_tokens_none_raises_error(
        self, token_tracker: TokenTracker
    ) -> None:
        """Test that None input raises TokenEstimationError."""
        with pytest.raises(TokenEstimationError) as exc_info:
            token_tracker.estimate_tokens(None)  # type: ignore

        assert "Cannot estimate tokens for None" in str(exc_info.value)

    def test_estimate_tokens_non_string_raises_error(
        self, token_tracker: TokenTracker
    ) -> None:
        """Test that non-string input raises TokenEstimationError."""
        with pytest.raises(TokenEstimationError) as exc_info:
            token_tracker.estimate_tokens(123)  # type: ignore

        assert "Expected string" in str(exc_info.value)

    def test_estimate_messages_tokens(
        self, token_tracker: TokenTracker, sample_messages: list[LLMMessage]
    ) -> None:
        """Test token estimation for message list."""
        result = token_tracker.estimate_messages_tokens(sample_messages)

        # Should include content tokens plus overhead
        assert result > 0

    def test_estimate_messages_tokens_empty_list(
        self, token_tracker: TokenTracker
    ) -> None:
        """Test token estimation for empty message list."""
        result = token_tracker.estimate_messages_tokens([])

        assert result == 0

    def test_estimate_messages_tokens_with_name(
        self, token_tracker: TokenTracker
    ) -> None:
        """Test token estimation for messages with name field."""
        messages = [
            LLMMessage(
                role=LLMRole.USER, content="Hello", name="user123"
            ),
        ]

        result = token_tracker.estimate_messages_tokens(messages)

        # Should include name tokens
        assert result > 0


# ============================================================================
# TokenTracker Tests - Model Pricing
# ============================================================================


class TestModelPricing:
    """Test suite for model pricing functionality."""

    def test_get_model_pricing_known_model(
        self, token_tracker: TokenTracker
    ) -> None:
        """Test getting pricing for a known model."""
        pricing = token_tracker.get_model_pricing("gpt-4o")

        assert pricing.input_cost_per_1k == Decimal("0.0025")
        assert pricing.output_cost_per_1k == Decimal("0.01")
        assert pricing.context_window == 128000

    def test_get_model_pricing_unknown_model(
        self, token_tracker: TokenTracker
    ) -> None:
        """Test getting pricing for unknown model returns default."""
        pricing = token_tracker.get_model_pricing("unknown-model-xyz")

        assert pricing == DEFAULT_PRICING

    def test_get_model_pricing_anthropic_model(
        self, token_tracker: TokenTracker
    ) -> None:
        """Test getting pricing for Anthropic models."""
        pricing = token_tracker.get_model_pricing("claude-3-5-sonnet-20241022")

        assert pricing.input_cost_per_1k == Decimal("0.003")
        assert pricing.output_cost_per_1k == Decimal("0.015")

    def test_get_model_pricing_prefix_match(
        self, token_tracker: TokenTracker
    ) -> None:
        """Test pricing lookup with prefix matching for versioned models."""
        # Should match gpt-4o even with a date suffix
        pricing = token_tracker.get_model_pricing("gpt-4o-2024-08-06")

        # Should get gpt-4o pricing
        assert pricing.context_window == 128000

    def test_model_pricing_dataclass(self) -> None:
        """Test ModelPricing dataclass attributes."""
        pricing = ModelPricing(
            input_cost_per_1k=Decimal("0.01"),
            output_cost_per_1k=Decimal("0.02"),
            context_window=16000,
        )

        assert pricing.input_cost_per_1k == Decimal("0.01")
        assert pricing.output_cost_per_1k == Decimal("0.02")
        assert pricing.context_window == 16000


# ============================================================================
# TokenTracker Tests - Cost Calculation
# ============================================================================


class TestCostCalculation:
    """Test suite for cost calculation functionality."""

    def test_calculate_cost_gpt4o(self, token_tracker: TokenTracker) -> None:
        """Test cost calculation for GPT-4o model."""
        cost = token_tracker.calculate_cost(
            prompt_tokens=1000,
            completion_tokens=500,
            model="gpt-4o",
        )

        # Input: 1000 * 0.0025 / 1000 = 0.0025
        # Output: 500 * 0.01 / 1000 = 0.005
        # Total: 0.0075
        assert cost == Decimal("0.0075")

    def test_calculate_cost_gpt35_turbo(self, token_tracker: TokenTracker) -> None:
        """Test cost calculation for GPT-3.5-turbo model."""
        cost = token_tracker.calculate_cost(
            prompt_tokens=2000,
            completion_tokens=1000,
            model="gpt-3.5-turbo",
        )

        # Input: 2000 * 0.0005 / 1000 = 0.001
        # Output: 1000 * 0.0015 / 1000 = 0.0015
        # Total: 0.0025
        assert cost == Decimal("0.0025")

    def test_calculate_cost_claude_sonnet(self, token_tracker: TokenTracker) -> None:
        """Test cost calculation for Claude 3.5 Sonnet."""
        cost = token_tracker.calculate_cost(
            prompt_tokens=1000,
            completion_tokens=500,
            model="claude-3-5-sonnet-20241022",
        )

        # Input: 1000 * 0.003 / 1000 = 0.003
        # Output: 500 * 0.015 / 1000 = 0.0075
        # Total: 0.0105
        assert cost == Decimal("0.0105")

    def test_calculate_cost_unknown_model(self, token_tracker: TokenTracker) -> None:
        """Test cost calculation with unknown model uses default pricing."""
        cost = token_tracker.calculate_cost(
            prompt_tokens=1000,
            completion_tokens=500,
            model="unknown-model",
        )

        # Uses default pricing
        # Input: 1000 * 0.01 / 1000 = 0.01
        # Output: 500 * 0.03 / 1000 = 0.015
        # Total: 0.025
        assert cost == Decimal("0.025")

    def test_calculate_cost_zero_tokens(self, token_tracker: TokenTracker) -> None:
        """Test cost calculation with zero tokens."""
        cost = token_tracker.calculate_cost(
            prompt_tokens=0,
            completion_tokens=0,
            model="gpt-4o",
        )

        assert cost == Decimal("0")

    def test_calculate_response_cost(
        self, token_tracker: TokenTracker, sample_response: LLMResponse
    ) -> None:
        """Test cost calculation from LLMResponse object."""
        cost = token_tracker.calculate_response_cost(sample_response)

        assert isinstance(cost, Decimal)
        assert cost > 0


# ============================================================================
# TokenTracker Tests - Context Window
# ============================================================================


class TestContextWindow:
    """Test suite for context window functionality."""

    def test_get_context_window_gpt4o(self, token_tracker: TokenTracker) -> None:
        """Test getting context window for GPT-4o."""
        window = token_tracker.get_context_window("gpt-4o")

        assert window == 128000

    def test_get_context_window_claude(self, token_tracker: TokenTracker) -> None:
        """Test getting context window for Claude models."""
        window = token_tracker.get_context_window("claude-3-5-sonnet-20241022")

        assert window == 200000

    def test_get_context_window_unknown(self, token_tracker: TokenTracker) -> None:
        """Test getting context window for unknown model."""
        window = token_tracker.get_context_window("unknown-model")

        assert window == DEFAULT_PRICING.context_window

    def test_check_context_limit_within_limit(
        self, token_tracker: TokenTracker, sample_messages: list[LLMMessage]
    ) -> None:
        """Test context limit check when within limit."""
        within_limit, remaining = token_tracker.check_context_limit(
            messages=sample_messages,
            model="gpt-4o",
            max_completion_tokens=4096,
        )

        assert within_limit is True
        assert remaining > 0

    def test_check_context_limit_exceeds_limit(
        self, token_tracker: TokenTracker
    ) -> None:
        """Test context limit check when exceeding limit."""
        # Create a very long message that exceeds GPT-4 (8192 tokens)
        long_content = "x" * 50000  # ~12500 tokens
        messages = [LLMMessage(role=LLMRole.USER, content=long_content)]

        within_limit, remaining = token_tracker.check_context_limit(
            messages=messages,
            model="gpt-4",
            max_completion_tokens=4096,
        )

        assert within_limit is False
        assert remaining < 0


# ============================================================================
# TokenTracker Tests - Utility Methods
# ============================================================================


class TestTokenTrackerUtilities:
    """Test suite for TokenTracker utility methods."""

    def test_format_cost(self, token_tracker: TokenTracker) -> None:
        """Test cost formatting."""
        assert token_tracker.format_cost(Decimal("0.0123")) == "$0.0123"
        assert token_tracker.format_cost(Decimal("1.5")) == "$1.5000"
        assert token_tracker.format_cost(Decimal("0")) == "$0.0000"

    def test_format_tokens_small(self, token_tracker: TokenTracker) -> None:
        """Test token formatting for small numbers."""
        assert token_tracker.format_tokens(100) == "100 tokens"
        assert token_tracker.format_tokens(999) == "999 tokens"

    def test_format_tokens_thousands(self, token_tracker: TokenTracker) -> None:
        """Test token formatting for thousands."""
        assert token_tracker.format_tokens(1000) == "1.0K tokens"
        assert token_tracker.format_tokens(5500) == "5.5K tokens"
        assert token_tracker.format_tokens(10000) == "10.0K tokens"

    def test_format_tokens_millions(self, token_tracker: TokenTracker) -> None:
        """Test token formatting for millions."""
        assert token_tracker.format_tokens(1000000) == "1.0M tokens"
        assert token_tracker.format_tokens(2500000) == "2.5M tokens"

    def test_create_usage_from_response(
        self, token_tracker: TokenTracker, sample_response: LLMResponse
    ) -> None:
        """Test creating LLMUsage from response."""
        usage = token_tracker.create_usage_from_response(sample_response)

        assert usage.prompt_tokens == sample_response.usage.prompt_tokens
        assert usage.completion_tokens == sample_response.usage.completion_tokens
        assert usage.total_tokens == sample_response.usage.total_tokens


# ============================================================================
# TokenTracker Tests - Database Operations (Mocked)
# ============================================================================


class TestTokenTrackerDatabase:
    """Test suite for TokenTracker database operations."""

    @pytest.mark.asyncio
    async def test_log_usage_without_db_raises(
        self, token_tracker: TokenTracker, sample_response: LLMResponse
    ) -> None:
        """Test that log_usage without db raises error."""
        with pytest.raises(TokenTrackerError) as exc_info:
            await token_tracker.log_usage(sample_response)

        assert "Database session required" in str(exc_info.value)

    @pytest.mark.asyncio
    async def test_log_error_without_db_raises(
        self, token_tracker: TokenTracker
    ) -> None:
        """Test that log_error without db raises error."""
        with pytest.raises(TokenTrackerError) as exc_info:
            await token_tracker.log_error(
                provider="openai",
                model="gpt-4o",
                error_message="Test error",
            )

        assert "Database session required" in str(exc_info.value)

    @pytest.mark.asyncio
    async def test_get_usage_statistics_without_db_raises(
        self, token_tracker: TokenTracker
    ) -> None:
        """Test that get_usage_statistics without db raises error."""
        with pytest.raises(TokenTrackerError) as exc_info:
            await token_tracker.get_usage_statistics()

        assert "Database session required" in str(exc_info.value)

    @pytest.mark.asyncio
    async def test_get_daily_usage_without_db_raises(
        self, token_tracker: TokenTracker
    ) -> None:
        """Test that get_daily_usage without db raises error."""
        with pytest.raises(TokenTrackerError) as exc_info:
            await token_tracker.get_daily_usage()

        assert "Database session required" in str(exc_info.value)


# ============================================================================
# UsageStatistics Tests
# ============================================================================


class TestUsageStatistics:
    """Test suite for UsageStatistics dataclass."""

    def test_usage_statistics_defaults(self) -> None:
        """Test UsageStatistics default values."""
        stats = UsageStatistics()

        assert stats.total_requests == 0
        assert stats.successful_requests == 0
        assert stats.failed_requests == 0
        assert stats.total_prompt_tokens == 0
        assert stats.total_completion_tokens == 0
        assert stats.total_tokens == 0
        assert stats.total_cost_usd == Decimal("0")
        assert stats.average_latency_ms is None
        assert stats.cache_hit_rate == 0.0

    def test_usage_statistics_with_values(self) -> None:
        """Test UsageStatistics with custom values."""
        stats = UsageStatistics(
            total_requests=100,
            successful_requests=95,
            failed_requests=5,
            total_prompt_tokens=10000,
            total_completion_tokens=5000,
            total_tokens=15000,
            total_cost_usd=Decimal("1.50"),
            average_latency_ms=250.5,
            cache_hit_rate=15.0,
        )

        assert stats.total_requests == 100
        assert stats.successful_requests == 95
        assert stats.failed_requests == 5
        assert stats.total_cost_usd == Decimal("1.50")
        assert stats.cache_hit_rate == 15.0


# ============================================================================
# LLMClient Tests - Initialization
# ============================================================================


class TestLLMClientInitialization:
    """Test suite for LLMClient initialization."""

    def test_client_default_initialization(self) -> None:
        """Test LLMClient with default configuration."""
        with patch("app.llm.client.LLMProviderFactory") as mock_factory:
            mock_factory_instance = MagicMock()
            mock_factory_instance.available_providers = []
            mock_factory_instance.get_provider.side_effect = LLMProviderError("No provider")
            mock_factory_instance.get_available_provider.return_value = None
            mock_factory.return_value = mock_factory_instance

            client = LLMClient()

            assert client.enable_caching is True
            assert client.enable_tracking is True
            assert client.enable_fallback is True

    def test_client_custom_configuration(self) -> None:
        """Test LLMClient with custom configuration."""
        with patch("app.llm.client.LLMProviderFactory") as mock_factory:
            mock_factory_instance = MagicMock()
            mock_factory_instance.available_providers = []
            mock_factory_instance.get_provider.side_effect = LLMProviderError("No provider")
            mock_factory.return_value = mock_factory_instance

            client = LLMClient(
                enable_caching=False,
                enable_tracking=False,
                enable_fallback=False,
                cache_ttl_seconds=7200,
            )

            assert client.enable_caching is False
            assert client.enable_tracking is False
            assert client.enable_fallback is False

    def test_client_repr(self) -> None:
        """Test LLMClient string representation."""
        with patch("app.llm.client.LLMProviderFactory") as mock_factory:
            mock_factory_instance = MagicMock()
            mock_factory_instance.available_providers = []
            mock_factory_instance.get_provider.side_effect = LLMProviderError("No provider")
            mock_factory_instance.configured_providers = ["openai"]
            mock_factory.return_value = mock_factory_instance

            client = LLMClient()

            repr_str = repr(client)

            assert "LLMClient" in repr_str
            assert "caching=" in repr_str


# ============================================================================
# LLMClient Tests - Completion
# ============================================================================


class TestLLMClientCompletion:
    """Test suite for LLMClient completion functionality."""

    @pytest.mark.asyncio
    async def test_complete_success(
        self, sample_messages: list[LLMMessage], sample_response: LLMResponse
    ) -> None:
        """Test successful completion."""
        mock_provider = MockLLMProvider(
            name="mock", response=sample_response
        )

        with patch("app.llm.client.LLMProviderFactory") as mock_factory:
            mock_factory_instance = MagicMock()
            mock_factory_instance.available_providers = ["mock"]
            mock_factory_instance.get_provider.return_value = mock_provider
            mock_factory_instance.get_available_provider.return_value = mock_provider
            mock_factory.return_value = mock_factory_instance

            client = LLMClient(enable_caching=False, enable_tracking=False)
            response = await client.complete(sample_messages)

            assert response.content == sample_response.content
            assert mock_provider.complete_call_count == 1

    @pytest.mark.asyncio
    async def test_complete_no_providers_raises(
        self, sample_messages: list[LLMMessage]
    ) -> None:
        """Test completion with no available providers."""
        with patch("app.llm.client.LLMProviderFactory") as mock_factory:
            mock_factory_instance = MagicMock()
            mock_factory_instance.available_providers = []
            mock_factory_instance.get_provider.side_effect = LLMProviderError("No provider")
            mock_factory_instance.get_available_provider.return_value = None
            mock_factory.return_value = mock_factory_instance

            client = LLMClient(enable_caching=False, enable_tracking=False)

            with pytest.raises(NoProvidersAvailableError):
                await client.complete(sample_messages)

    @pytest.mark.asyncio
    async def test_complete_with_cache_hit(
        self, sample_messages: list[LLMMessage], sample_response: LLMResponse
    ) -> None:
        """Test completion with cache hit."""
        mock_provider = MockLLMProvider(name="mock", response=sample_response)

        with patch("app.llm.client.LLMProviderFactory") as mock_factory:
            mock_factory_instance = MagicMock()
            mock_factory_instance.available_providers = ["mock"]
            mock_factory_instance.get_provider.return_value = mock_provider
            mock_factory_instance.get_available_provider.return_value = mock_provider
            mock_factory.return_value = mock_factory_instance

            client = LLMClient(enable_caching=True, enable_tracking=False)

            # Mock cache hit
            client.cache = MagicMock()
            client.cache.generate_cache_key = MagicMock(return_value="test-key")
            client.cache.get = AsyncMock(return_value=sample_response)

            response = await client.complete(sample_messages)

            # Provider should not be called on cache hit
            assert mock_provider.complete_call_count == 0
            assert response.content == sample_response.content

    @pytest.mark.asyncio
    async def test_complete_with_cache_miss(
        self, sample_messages: list[LLMMessage], sample_response: LLMResponse
    ) -> None:
        """Test completion with cache miss."""
        mock_provider = MockLLMProvider(name="mock", response=sample_response)

        with patch("app.llm.client.LLMProviderFactory") as mock_factory:
            mock_factory_instance = MagicMock()
            mock_factory_instance.available_providers = ["mock"]
            mock_factory_instance.get_provider.return_value = mock_provider
            mock_factory_instance.get_available_provider.return_value = mock_provider
            mock_factory.return_value = mock_factory_instance

            client = LLMClient(enable_caching=True, enable_tracking=False)

            # Mock cache miss
            client.cache = MagicMock()
            client.cache.generate_cache_key = MagicMock(return_value="test-key")
            client.cache.get = AsyncMock(return_value=None)
            client.cache.set = AsyncMock()

            response = await client.complete(sample_messages)

            # Provider should be called on cache miss
            assert mock_provider.complete_call_count == 1
            # Cache should be populated
            client.cache.set.assert_called_once()

    @pytest.mark.asyncio
    async def test_complete_with_error(
        self, sample_messages: list[LLMMessage]
    ) -> None:
        """Test completion with provider error."""
        mock_provider = MockLLMProvider(
            name="mock",
            error=LLMError("Test error"),
        )

        with patch("app.llm.client.LLMProviderFactory") as mock_factory:
            mock_factory_instance = MagicMock()
            mock_factory_instance.available_providers = ["mock"]
            mock_factory_instance.get_provider.return_value = mock_provider
            mock_factory_instance.get_available_provider.return_value = mock_provider
            mock_factory.return_value = mock_factory_instance

            client = LLMClient(
                enable_caching=False,
                enable_tracking=False,
                enable_fallback=False,
            )

            with pytest.raises(CompletionFailedError) as exc_info:
                await client.complete(sample_messages)

            assert "Completion failed" in str(exc_info.value)


# ============================================================================
# LLMClient Tests - Streaming
# ============================================================================


class TestLLMClientStreaming:
    """Test suite for LLMClient streaming functionality."""

    @pytest.mark.asyncio
    async def test_complete_stream_success(
        self, sample_messages: list[LLMMessage]
    ) -> None:
        """Test successful streaming completion."""
        mock_provider = MockLLMProvider(name="mock")

        with patch("app.llm.client.LLMProviderFactory") as mock_factory:
            mock_factory_instance = MagicMock()
            mock_factory_instance.available_providers = ["mock"]
            mock_factory_instance.get_provider.return_value = mock_provider
            mock_factory_instance.get_available_provider.return_value = mock_provider
            mock_factory.return_value = mock_factory_instance

            client = LLMClient(enable_caching=False, enable_tracking=False)

            chunks = []
            async for chunk in client.complete_stream(sample_messages):
                chunks.append(chunk)

            assert len(chunks) == 2
            assert "Mock" in chunks[0]
            assert "response" in chunks[1]

    @pytest.mark.asyncio
    async def test_complete_stream_no_providers(
        self, sample_messages: list[LLMMessage]
    ) -> None:
        """Test streaming with no available providers."""
        with patch("app.llm.client.LLMProviderFactory") as mock_factory:
            mock_factory_instance = MagicMock()
            mock_factory_instance.available_providers = []
            mock_factory_instance.get_provider.side_effect = LLMProviderError("No provider")
            mock_factory_instance.get_available_provider.return_value = None
            mock_factory.return_value = mock_factory_instance

            client = LLMClient(enable_caching=False, enable_tracking=False)

            with pytest.raises(NoProvidersAvailableError):
                async for _ in client.complete_stream(sample_messages):
                    pass


# ============================================================================
# LLMClient Tests - Fallback
# ============================================================================


class TestLLMClientFallback:
    """Test suite for LLMClient fallback functionality."""

    @pytest.mark.asyncio
    async def test_execute_with_fallback_success(
        self, sample_messages: list[LLMMessage], sample_response: LLMResponse
    ) -> None:
        """Test fallback execution success."""
        mock_provider = MockLLMProvider(name="mock", response=sample_response)

        with patch("app.llm.client.LLMProviderFactory") as mock_factory:
            mock_factory_instance = MagicMock()
            mock_factory_instance.available_providers = ["mock"]
            mock_factory_instance.get_provider.return_value = mock_provider
            mock_factory_instance.get_available_provider.return_value = mock_provider
            mock_factory.return_value = mock_factory_instance

            client = LLMClient(
                enable_caching=False,
                enable_tracking=False,
                enable_fallback=True,
            )

            # Manually configure fallback with multiple providers
            mock_provider2 = MockLLMProvider(
                name="fallback", response=sample_response
            )
            client.fallback.set_providers([mock_provider, mock_provider2])

            result = await client._execute_with_fallback(
                messages=sample_messages,
                config=LLMConfig(),
            )

            assert result.response is not None

    @pytest.mark.asyncio
    async def test_fallback_all_providers_fail(
        self, sample_messages: list[LLMMessage]
    ) -> None:
        """Test fallback when all providers fail."""
        mock_provider1 = MockLLMProvider(
            name="provider1",
            error=LLMRateLimitError("Rate limited"),
        )
        mock_provider2 = MockLLMProvider(
            name="provider2",
            error=LLMTimeoutError("Timeout"),
        )

        with patch("app.llm.client.LLMProviderFactory") as mock_factory:
            mock_factory_instance = MagicMock()
            mock_factory_instance.available_providers = ["provider1", "provider2"]
            mock_factory_instance.get_provider.return_value = mock_provider1
            mock_factory_instance.get_available_provider.return_value = mock_provider1
            mock_factory.return_value = mock_factory_instance

            client = LLMClient(
                enable_caching=False,
                enable_tracking=False,
                enable_fallback=True,
            )

            client.fallback.set_providers([mock_provider1, mock_provider2])

            result = await client._execute_with_fallback(
                messages=sample_messages,
                config=LLMConfig(),
            )

            assert result.all_failed is True
            assert result.response is None


# ============================================================================
# LLMClient Tests - Utility Methods
# ============================================================================


class TestLLMClientUtilities:
    """Test suite for LLMClient utility methods."""

    def test_estimate_tokens(self, sample_messages: list[LLMMessage]) -> None:
        """Test token estimation through client."""
        with patch("app.llm.client.LLMProviderFactory") as mock_factory:
            mock_factory_instance = MagicMock()
            mock_factory_instance.available_providers = []
            mock_factory_instance.get_provider.side_effect = LLMProviderError("No provider")
            mock_factory.return_value = mock_factory_instance

            client = LLMClient()
            result = client.estimate_tokens("Hello, world!")

            assert result > 0

    def test_estimate_messages_tokens(
        self, sample_messages: list[LLMMessage]
    ) -> None:
        """Test message token estimation through client."""
        with patch("app.llm.client.LLMProviderFactory") as mock_factory:
            mock_factory_instance = MagicMock()
            mock_factory_instance.available_providers = []
            mock_factory_instance.get_provider.side_effect = LLMProviderError("No provider")
            mock_factory.return_value = mock_factory_instance

            client = LLMClient()
            result = client.estimate_messages_tokens(sample_messages)

            assert result > 0

    def test_calculate_cost(self) -> None:
        """Test cost calculation through client."""
        with patch("app.llm.client.LLMProviderFactory") as mock_factory:
            mock_factory_instance = MagicMock()
            mock_factory_instance.available_providers = []
            mock_factory_instance.get_provider.side_effect = LLMProviderError("No provider")
            mock_factory.return_value = mock_factory_instance

            client = LLMClient()
            result = client.calculate_cost(
                prompt_tokens=1000,
                completion_tokens=500,
                model="gpt-4o",
            )

            assert isinstance(result, float)
            assert result > 0

    def test_check_context_limit(
        self, sample_messages: list[LLMMessage]
    ) -> None:
        """Test context limit checking through client."""
        with patch("app.llm.client.LLMProviderFactory") as mock_factory:
            mock_factory_instance = MagicMock()
            mock_factory_instance.available_providers = []
            mock_factory_instance.get_provider.side_effect = LLMProviderError("No provider")
            mock_factory.return_value = mock_factory_instance

            with patch("app.llm.client.settings") as mock_settings:
                mock_settings.llm_default_model = "gpt-4o"

                client = LLMClient()
                within_limit, remaining = client.check_context_limit(sample_messages)

                assert within_limit is True
                assert remaining > 0

    @pytest.mark.asyncio
    async def test_get_usage_statistics_without_db(self) -> None:
        """Test that get_usage_statistics raises without db."""
        with patch("app.llm.client.LLMProviderFactory") as mock_factory:
            mock_factory_instance = MagicMock()
            mock_factory_instance.available_providers = []
            mock_factory_instance.get_provider.side_effect = LLMProviderError("No provider")
            mock_factory.return_value = mock_factory_instance

            client = LLMClient()

            with pytest.raises(LLMClientError) as exc_info:
                await client.get_usage_statistics()

            assert "Database session required" in str(exc_info.value)

    @pytest.mark.asyncio
    async def test_get_cache_stats(self) -> None:
        """Test getting cache statistics."""
        with patch("app.llm.client.LLMProviderFactory") as mock_factory:
            mock_factory_instance = MagicMock()
            mock_factory_instance.available_providers = []
            mock_factory_instance.get_provider.side_effect = LLMProviderError("No provider")
            mock_factory.return_value = mock_factory_instance

            client = LLMClient()
            stats = await client.get_cache_stats()

            assert isinstance(stats, dict)
            assert "total_entries" in stats

    @pytest.mark.asyncio
    async def test_invalidate_cache(self) -> None:
        """Test cache invalidation."""
        with patch("app.llm.client.LLMProviderFactory") as mock_factory:
            mock_factory_instance = MagicMock()
            mock_factory_instance.available_providers = []
            mock_factory_instance.get_provider.side_effect = LLMProviderError("No provider")
            mock_factory.return_value = mock_factory_instance

            client = LLMClient()
            removed = await client.invalidate_cache(provider="openai")

            assert isinstance(removed, int)

    @pytest.mark.asyncio
    async def test_cleanup_expired_cache(self) -> None:
        """Test expired cache cleanup."""
        with patch("app.llm.client.LLMProviderFactory") as mock_factory:
            mock_factory_instance = MagicMock()
            mock_factory_instance.available_providers = []
            mock_factory_instance.get_provider.side_effect = LLMProviderError("No provider")
            mock_factory.return_value = mock_factory_instance

            client = LLMClient()
            removed = await client.cleanup_expired_cache()

            assert isinstance(removed, int)


# ============================================================================
# LLMClient Tests - Provider Access
# ============================================================================


class TestLLMClientProviders:
    """Test suite for LLMClient provider access."""

    def test_available_providers_property(self) -> None:
        """Test available_providers property."""
        with patch("app.llm.client.LLMProviderFactory") as mock_factory:
            mock_factory_instance = MagicMock()
            mock_factory_instance.available_providers = ["openai", "anthropic"]
            mock_factory_instance.get_provider.side_effect = LLMProviderError("No provider")
            mock_factory.return_value = mock_factory_instance

            client = LLMClient()

            assert client.available_providers == ["openai", "anthropic"]

    def test_configured_providers_property(self) -> None:
        """Test configured_providers property."""
        with patch("app.llm.client.LLMProviderFactory") as mock_factory:
            mock_factory_instance = MagicMock()
            mock_factory_instance.available_providers = []
            mock_factory_instance.configured_providers = ["openai"]
            mock_factory_instance.get_provider.side_effect = LLMProviderError("No provider")
            mock_factory.return_value = mock_factory_instance

            client = LLMClient()

            assert client.configured_providers == ["openai"]

    def test_get_provider(self) -> None:
        """Test get_provider method."""
        mock_provider = MockLLMProvider(name="openai")

        with patch("app.llm.client.LLMProviderFactory") as mock_factory:
            mock_factory_instance = MagicMock()
            mock_factory_instance.available_providers = ["openai"]
            mock_factory_instance.get_provider.return_value = mock_provider
            mock_factory.return_value = mock_factory_instance

            client = LLMClient()
            provider = client.get_provider("openai")

            assert provider.name == "openai"


# ============================================================================
# LLMClientError Tests
# ============================================================================


class TestLLMClientError:
    """Test suite for LLMClientError exception classes."""

    def test_llm_client_error_attributes(self) -> None:
        """Test LLMClientError has correct attributes."""
        original = ValueError("Original error")
        error = LLMClientError("Test error", original_error=original)

        assert error.message == "Test error"
        assert error.original_error == original
        assert str(error) == "Test error"

    def test_no_providers_available_error(self) -> None:
        """Test NoProvidersAvailableError is subclass of LLMClientError."""
        error = NoProvidersAvailableError("No providers")

        assert isinstance(error, LLMClientError)
        assert error.message == "No providers"

    def test_completion_failed_error(self) -> None:
        """Test CompletionFailedError is subclass of LLMClientError."""
        original = LLMError("LLM failed")
        error = CompletionFailedError("Completion failed", original_error=original)

        assert isinstance(error, LLMClientError)
        assert error.original_error == original
