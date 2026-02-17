"""Unit tests for LLM providers with mocked API responses.

Tests for OpenAI and Anthropic providers including completion, streaming,
availability checks, error handling, and configuration management.
"""

from __future__ import annotations

import json
from datetime import datetime
from typing import Any
from unittest.mock import AsyncMock, MagicMock, patch

import httpx
import pytest
import pytest_asyncio

from app.llm.exceptions import (
    LLMAuthenticationError,
    LLMProviderError,
    LLMRateLimitError,
    LLMTimeoutError,
)
from app.llm.providers.anthropic_provider import (
    ANTHROPIC_MODELS,
    AnthropicProvider,
)
from app.llm.providers.openai_provider import (
    OPENAI_MODELS,
    OpenAIProvider,
)
from app.llm.types import LLMConfig, LLMMessage, LLMResponse, LLMRole, LLMUsage


# ============================================================================
# Fixtures
# ============================================================================


@pytest.fixture
def openai_provider() -> OpenAIProvider:
    """Create an OpenAI provider with a test API key.

    Returns:
        OpenAIProvider: Provider instance for testing
    """
    return OpenAIProvider(api_key="sk-test-key-12345")


@pytest.fixture
def anthropic_provider() -> AnthropicProvider:
    """Create an Anthropic provider with a test API key.

    Returns:
        AnthropicProvider: Provider instance for testing
    """
    return AnthropicProvider(api_key="sk-ant-test-key-12345")


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
def sample_config() -> LLMConfig:
    """Create sample configuration for testing.

    Returns:
        LLMConfig: Test configuration
    """
    return LLMConfig(
        model="gpt-4o",
        temperature=0.7,
        max_tokens=1000,
        timeout=30,
    )


@pytest.fixture
def mock_openai_response() -> dict[str, Any]:
    """Create a mock OpenAI API response.

    Returns:
        dict: Mock OpenAI response data
    """
    return {
        "id": "chatcmpl-123456789",
        "object": "chat.completion",
        "created": 1677652288,
        "model": "gpt-4o",
        "choices": [
            {
                "index": 0,
                "message": {
                    "role": "assistant",
                    "content": "Hello! I'm doing well, thank you for asking.",
                },
                "finish_reason": "stop",
            }
        ],
        "usage": {
            "prompt_tokens": 20,
            "completion_tokens": 15,
            "total_tokens": 35,
        },
    }


@pytest.fixture
def mock_anthropic_response() -> dict[str, Any]:
    """Create a mock Anthropic API response.

    Returns:
        dict: Mock Anthropic response data
    """
    return {
        "id": "msg_01XFDUDYJgAACzvnptvVoYEL",
        "type": "message",
        "role": "assistant",
        "model": "claude-3-5-sonnet-20241022",
        "content": [
            {
                "type": "text",
                "text": "Hello! I'm doing well, thank you for asking.",
            }
        ],
        "stop_reason": "end_turn",
        "usage": {
            "input_tokens": 20,
            "output_tokens": 15,
        },
    }


# ============================================================================
# OpenAI Provider Tests
# ============================================================================


class TestOpenAIProvider:
    """Test suite for OpenAI LLM provider."""

    def test_provider_attributes(self, openai_provider: OpenAIProvider) -> None:
        """Test that provider has correct name and default model.

        Verifies that the OpenAI provider has the expected name
        and default model attributes set correctly.
        """
        assert openai_provider.name == "openai"
        assert openai_provider.default_model == "gpt-4o"

    def test_is_available_with_api_key(self, openai_provider: OpenAIProvider) -> None:
        """Test that provider is available when API key is set.

        Verifies that is_available() returns True when a valid
        API key is configured.
        """
        assert openai_provider.is_available() is True

    def test_is_available_without_api_key(self) -> None:
        """Test that provider is not available without API key.

        Verifies that is_available() returns False when no
        API key is configured.
        """
        provider = OpenAIProvider(api_key=None)
        assert provider.is_available() is False

    def test_is_available_with_empty_api_key(self) -> None:
        """Test that provider is not available with empty API key.

        Verifies that is_available() returns False when an
        empty string is provided as the API key.
        """
        provider = OpenAIProvider(api_key="")
        assert provider.is_available() is False

    def test_get_model_list(self, openai_provider: OpenAIProvider) -> None:
        """Test that provider returns correct model list.

        Verifies that get_model_list() returns the expected
        list of supported OpenAI models.
        """
        models = openai_provider.get_model_list()

        assert isinstance(models, list)
        assert len(models) > 0
        assert "gpt-4o" in models
        assert "gpt-4o-mini" in models
        assert "gpt-4-turbo" in models
        assert models == OPENAI_MODELS

    def test_get_default_config(self, openai_provider: OpenAIProvider) -> None:
        """Test that provider returns valid default configuration.

        Verifies that get_default_config() returns an LLMConfig
        with sensible defaults for OpenAI models.
        """
        config = openai_provider.get_default_config()

        assert isinstance(config, LLMConfig)
        assert config.model == "gpt-4o"
        assert config.temperature >= 0.0
        assert config.max_tokens > 0
        assert config.timeout > 0

    def test_validate_messages_valid(
        self,
        openai_provider: OpenAIProvider,
        sample_messages: list[LLMMessage],
    ) -> None:
        """Test that valid messages pass validation.

        Verifies that validate_messages() returns True for
        properly formatted message lists.
        """
        assert openai_provider.validate_messages(sample_messages) is True

    def test_validate_messages_empty_list(
        self,
        openai_provider: OpenAIProvider,
    ) -> None:
        """Test that empty message list fails validation.

        Verifies that validate_messages() returns False for
        empty message lists.
        """
        assert openai_provider.validate_messages([]) is False

    def test_validate_messages_empty_content(
        self,
        openai_provider: OpenAIProvider,
    ) -> None:
        """Test that messages with empty content fail validation.

        Verifies that validate_messages() returns False when
        any message has empty content.
        """
        messages = [LLMMessage(role=LLMRole.USER, content="")]
        assert openai_provider.validate_messages(messages) is False

    @pytest.mark.asyncio
    async def test_complete_success(
        self,
        openai_provider: OpenAIProvider,
        sample_messages: list[LLMMessage],
        mock_openai_response: dict[str, Any],
    ) -> None:
        """Test successful completion with mocked API response.

        Verifies that the provider correctly processes a successful
        API response and returns a properly formatted LLMResponse.
        """
        mock_response = MagicMock(spec=httpx.Response)
        mock_response.status_code = 200
        mock_response.json.return_value = mock_openai_response

        with patch("httpx.AsyncClient") as mock_client_class:
            mock_client = AsyncMock()
            mock_client.post.return_value = mock_response
            mock_client.__aenter__.return_value = mock_client
            mock_client.__aexit__.return_value = None
            mock_client_class.return_value = mock_client

            response = await openai_provider.complete(sample_messages)

            assert isinstance(response, LLMResponse)
            assert response.content == "Hello! I'm doing well, thank you for asking."
            assert response.model == "gpt-4o"
            assert response.provider == "openai"
            assert response.finish_reason == "stop"
            assert response.usage.prompt_tokens == 20
            assert response.usage.completion_tokens == 15
            assert response.usage.total_tokens == 35
            assert response.request_id == "chatcmpl-123456789"

    @pytest.mark.asyncio
    async def test_complete_with_custom_config(
        self,
        openai_provider: OpenAIProvider,
        sample_messages: list[LLMMessage],
        sample_config: LLMConfig,
        mock_openai_response: dict[str, Any],
    ) -> None:
        """Test completion with custom configuration.

        Verifies that custom configuration is properly applied
        to the API request.
        """
        mock_response = MagicMock(spec=httpx.Response)
        mock_response.status_code = 200
        mock_response.json.return_value = mock_openai_response

        with patch("httpx.AsyncClient") as mock_client_class:
            mock_client = AsyncMock()
            mock_client.post.return_value = mock_response
            mock_client.__aenter__.return_value = mock_client
            mock_client.__aexit__.return_value = None
            mock_client_class.return_value = mock_client

            response = await openai_provider.complete(sample_messages, sample_config)

            assert isinstance(response, LLMResponse)
            # Verify post was called with correct payload structure
            call_args = mock_client.post.call_args
            payload = call_args.kwargs["json"]
            assert payload["temperature"] == 0.7
            assert payload["max_tokens"] == 1000

    @pytest.mark.asyncio
    async def test_complete_authentication_error(
        self,
        openai_provider: OpenAIProvider,
        sample_messages: list[LLMMessage],
    ) -> None:
        """Test that 401 response raises LLMAuthenticationError.

        Verifies that authentication failures from the API
        are properly translated to LLMAuthenticationError.
        """
        mock_response = MagicMock(spec=httpx.Response)
        mock_response.status_code = 401
        mock_response.text = "Invalid API key"

        with patch("httpx.AsyncClient") as mock_client_class:
            mock_client = AsyncMock()
            mock_client.post.return_value = mock_response
            mock_client.__aenter__.return_value = mock_client
            mock_client.__aexit__.return_value = None
            mock_client_class.return_value = mock_client

            with pytest.raises(LLMAuthenticationError) as exc_info:
                await openai_provider.complete(sample_messages)

            assert "openai" in str(exc_info.value).lower()

    @pytest.mark.asyncio
    async def test_complete_rate_limit_error(
        self,
        openai_provider: OpenAIProvider,
        sample_messages: list[LLMMessage],
    ) -> None:
        """Test that 429 response raises LLMRateLimitError.

        Verifies that rate limit errors from the API
        are properly translated to LLMRateLimitError.
        """
        mock_response = MagicMock(spec=httpx.Response)
        mock_response.status_code = 429
        mock_response.text = "Rate limit exceeded"

        with patch("httpx.AsyncClient") as mock_client_class:
            mock_client = AsyncMock()
            mock_client.post.return_value = mock_response
            mock_client.__aenter__.return_value = mock_client
            mock_client.__aexit__.return_value = None
            mock_client_class.return_value = mock_client

            with pytest.raises(LLMRateLimitError) as exc_info:
                await openai_provider.complete(sample_messages)

            assert "rate limit" in str(exc_info.value).lower()

    @pytest.mark.asyncio
    async def test_complete_provider_error_400(
        self,
        openai_provider: OpenAIProvider,
        sample_messages: list[LLMMessage],
    ) -> None:
        """Test that 400 response raises LLMProviderError.

        Verifies that bad request errors from the API
        are properly translated to LLMProviderError.
        """
        mock_response = MagicMock(spec=httpx.Response)
        mock_response.status_code = 400
        mock_response.text = "Bad request: invalid model"

        with patch("httpx.AsyncClient") as mock_client_class:
            mock_client = AsyncMock()
            mock_client.post.return_value = mock_response
            mock_client.__aenter__.return_value = mock_client
            mock_client.__aexit__.return_value = None
            mock_client_class.return_value = mock_client

            with pytest.raises(LLMProviderError) as exc_info:
                await openai_provider.complete(sample_messages)

            assert exc_info.value.status_code == 400

    @pytest.mark.asyncio
    async def test_complete_provider_error_500(
        self,
        openai_provider: OpenAIProvider,
        sample_messages: list[LLMMessage],
    ) -> None:
        """Test that 500 response raises LLMProviderError.

        Verifies that server errors from the API
        are properly translated to LLMProviderError.
        """
        mock_response = MagicMock(spec=httpx.Response)
        mock_response.status_code = 500
        mock_response.text = "Internal server error"

        with patch("httpx.AsyncClient") as mock_client_class:
            mock_client = AsyncMock()
            mock_client.post.return_value = mock_response
            mock_client.__aenter__.return_value = mock_client
            mock_client.__aexit__.return_value = None
            mock_client_class.return_value = mock_client

            with pytest.raises(LLMProviderError) as exc_info:
                await openai_provider.complete(sample_messages)

            assert exc_info.value.status_code == 500

    @pytest.mark.asyncio
    async def test_complete_timeout_error(
        self,
        openai_provider: OpenAIProvider,
        sample_messages: list[LLMMessage],
    ) -> None:
        """Test that timeout raises LLMTimeoutError.

        Verifies that request timeouts are properly
        translated to LLMTimeoutError.
        """
        with patch("httpx.AsyncClient") as mock_client_class:
            mock_client = AsyncMock()
            mock_client.post.side_effect = httpx.TimeoutException("Request timed out")
            mock_client.__aenter__.return_value = mock_client
            mock_client.__aexit__.return_value = None
            mock_client_class.return_value = mock_client

            with pytest.raises(LLMTimeoutError) as exc_info:
                await openai_provider.complete(sample_messages)

            assert "timed out" in str(exc_info.value).lower()

    @pytest.mark.asyncio
    async def test_complete_http_error(
        self,
        openai_provider: OpenAIProvider,
        sample_messages: list[LLMMessage],
    ) -> None:
        """Test that HTTP errors raise LLMProviderError.

        Verifies that generic HTTP errors are properly
        translated to LLMProviderError.
        """
        with patch("httpx.AsyncClient") as mock_client_class:
            mock_client = AsyncMock()
            mock_client.post.side_effect = httpx.HTTPError("Connection failed")
            mock_client.__aenter__.return_value = mock_client
            mock_client.__aexit__.return_value = None
            mock_client_class.return_value = mock_client

            with pytest.raises(LLMProviderError) as exc_info:
                await openai_provider.complete(sample_messages)

            assert "http error" in str(exc_info.value).lower()

    @pytest.mark.asyncio
    async def test_complete_without_api_key(
        self,
        sample_messages: list[LLMMessage],
    ) -> None:
        """Test that completion without API key raises LLMAuthenticationError.

        Verifies that attempting to complete without a configured
        API key raises LLMAuthenticationError immediately.
        """
        provider = OpenAIProvider(api_key=None)

        with pytest.raises(LLMAuthenticationError) as exc_info:
            await provider.complete(sample_messages)

        assert "not configured" in str(exc_info.value).lower()

    @pytest.mark.asyncio
    async def test_complete_with_invalid_messages(
        self,
        openai_provider: OpenAIProvider,
    ) -> None:
        """Test that invalid messages raise LLMProviderError.

        Verifies that passing invalid messages (empty list or
        empty content) raises LLMProviderError.
        """
        with pytest.raises(LLMProviderError) as exc_info:
            await openai_provider.complete([])

        assert "invalid" in str(exc_info.value).lower()

    @pytest.mark.asyncio
    async def test_complete_no_choices_in_response(
        self,
        openai_provider: OpenAIProvider,
        sample_messages: list[LLMMessage],
    ) -> None:
        """Test that response without choices raises LLMProviderError.

        Verifies that an API response missing the choices array
        is properly handled with LLMProviderError.
        """
        mock_response = MagicMock(spec=httpx.Response)
        mock_response.status_code = 200
        mock_response.json.return_value = {
            "id": "chatcmpl-123",
            "model": "gpt-4o",
            "choices": [],  # Empty choices
            "usage": {"prompt_tokens": 10, "completion_tokens": 0, "total_tokens": 10},
        }

        with patch("httpx.AsyncClient") as mock_client_class:
            mock_client = AsyncMock()
            mock_client.post.return_value = mock_response
            mock_client.__aenter__.return_value = mock_client
            mock_client.__aexit__.return_value = None
            mock_client_class.return_value = mock_client

            with pytest.raises(LLMProviderError) as exc_info:
                await openai_provider.complete(sample_messages)

            assert "no choices" in str(exc_info.value).lower()

    def test_repr(self, openai_provider: OpenAIProvider) -> None:
        """Test string representation of provider.

        Verifies that the provider has a useful string representation.
        """
        repr_str = repr(openai_provider)
        assert "OpenAIProvider" in repr_str
        assert "openai" in repr_str


# ============================================================================
# Anthropic Provider Tests
# ============================================================================


class TestAnthropicProvider:
    """Test suite for Anthropic LLM provider."""

    def test_provider_attributes(self, anthropic_provider: AnthropicProvider) -> None:
        """Test that provider has correct name and default model.

        Verifies that the Anthropic provider has the expected name
        and default model attributes set correctly.
        """
        assert anthropic_provider.name == "anthropic"
        assert anthropic_provider.default_model == "claude-3-5-sonnet-20241022"

    def test_is_available_with_api_key(
        self,
        anthropic_provider: AnthropicProvider,
    ) -> None:
        """Test that provider is available when API key is set.

        Verifies that is_available() returns True when a valid
        API key is configured.
        """
        assert anthropic_provider.is_available() is True

    def test_is_available_without_api_key(self) -> None:
        """Test that provider is not available without API key.

        Verifies that is_available() returns False when no
        API key is configured.
        """
        provider = AnthropicProvider(api_key=None)
        assert provider.is_available() is False

    def test_is_available_with_empty_api_key(self) -> None:
        """Test that provider is not available with empty API key.

        Verifies that is_available() returns False when an
        empty string is provided as the API key.
        """
        provider = AnthropicProvider(api_key="")
        assert provider.is_available() is False

    def test_get_model_list(self, anthropic_provider: AnthropicProvider) -> None:
        """Test that provider returns correct model list.

        Verifies that get_model_list() returns the expected
        list of supported Anthropic models.
        """
        models = anthropic_provider.get_model_list()

        assert isinstance(models, list)
        assert len(models) > 0
        assert "claude-3-5-sonnet-20241022" in models
        assert "claude-3-opus-20240229" in models
        assert models == ANTHROPIC_MODELS

    def test_get_default_config(self, anthropic_provider: AnthropicProvider) -> None:
        """Test that provider returns valid default configuration.

        Verifies that get_default_config() returns an LLMConfig
        with sensible defaults for Claude models.
        """
        config = anthropic_provider.get_default_config()

        assert isinstance(config, LLMConfig)
        assert config.model == "claude-3-5-sonnet-20241022"
        assert config.temperature >= 0.0
        assert config.max_tokens > 0
        assert config.timeout > 0

    def test_validate_messages_valid(
        self,
        anthropic_provider: AnthropicProvider,
        sample_messages: list[LLMMessage],
    ) -> None:
        """Test that valid messages pass validation.

        Verifies that validate_messages() returns True for
        properly formatted message lists.
        """
        assert anthropic_provider.validate_messages(sample_messages) is True

    def test_validate_messages_empty_list(
        self,
        anthropic_provider: AnthropicProvider,
    ) -> None:
        """Test that empty message list fails validation.

        Verifies that validate_messages() returns False for
        empty message lists.
        """
        assert anthropic_provider.validate_messages([]) is False

    @pytest.mark.asyncio
    async def test_complete_success(
        self,
        anthropic_provider: AnthropicProvider,
        sample_messages: list[LLMMessage],
        mock_anthropic_response: dict[str, Any],
    ) -> None:
        """Test successful completion with mocked API response.

        Verifies that the provider correctly processes a successful
        API response and returns a properly formatted LLMResponse.
        """
        mock_response = MagicMock(spec=httpx.Response)
        mock_response.status_code = 200
        mock_response.json.return_value = mock_anthropic_response

        with patch("httpx.AsyncClient") as mock_client_class:
            mock_client = AsyncMock()
            mock_client.post.return_value = mock_response
            mock_client.__aenter__.return_value = mock_client
            mock_client.__aexit__.return_value = None
            mock_client_class.return_value = mock_client

            response = await anthropic_provider.complete(sample_messages)

            assert isinstance(response, LLMResponse)
            assert response.content == "Hello! I'm doing well, thank you for asking."
            assert response.model == "claude-3-5-sonnet-20241022"
            assert response.provider == "anthropic"
            assert response.finish_reason == "end_turn"
            assert response.usage.prompt_tokens == 20
            assert response.usage.completion_tokens == 15
            assert response.usage.total_tokens == 35
            assert response.request_id == "msg_01XFDUDYJgAACzvnptvVoYEL"

    @pytest.mark.asyncio
    async def test_complete_with_system_message(
        self,
        anthropic_provider: AnthropicProvider,
        mock_anthropic_response: dict[str, Any],
    ) -> None:
        """Test completion with system message is handled correctly.

        Verifies that system messages are properly separated and
        sent as the 'system' parameter in Anthropic API calls.
        """
        messages = [
            LLMMessage(role=LLMRole.SYSTEM, content="You are a helpful assistant."),
            LLMMessage(role=LLMRole.USER, content="Hello!"),
        ]

        mock_response = MagicMock(spec=httpx.Response)
        mock_response.status_code = 200
        mock_response.json.return_value = mock_anthropic_response

        with patch("httpx.AsyncClient") as mock_client_class:
            mock_client = AsyncMock()
            mock_client.post.return_value = mock_response
            mock_client.__aenter__.return_value = mock_client
            mock_client.__aexit__.return_value = None
            mock_client_class.return_value = mock_client

            await anthropic_provider.complete(messages)

            # Verify post was called with system message separated
            call_args = mock_client.post.call_args
            payload = call_args.kwargs["json"]
            assert "system" in payload
            assert payload["system"] == "You are a helpful assistant."
            # Verify user message is in messages array
            assert len(payload["messages"]) == 1
            assert payload["messages"][0]["role"] == "user"

    @pytest.mark.asyncio
    async def test_complete_authentication_error(
        self,
        anthropic_provider: AnthropicProvider,
        sample_messages: list[LLMMessage],
    ) -> None:
        """Test that 401 response raises LLMAuthenticationError.

        Verifies that authentication failures from the API
        are properly translated to LLMAuthenticationError.
        """
        mock_response = MagicMock(spec=httpx.Response)
        mock_response.status_code = 401
        mock_response.text = "Invalid API key"

        with patch("httpx.AsyncClient") as mock_client_class:
            mock_client = AsyncMock()
            mock_client.post.return_value = mock_response
            mock_client.__aenter__.return_value = mock_client
            mock_client.__aexit__.return_value = None
            mock_client_class.return_value = mock_client

            with pytest.raises(LLMAuthenticationError) as exc_info:
                await anthropic_provider.complete(sample_messages)

            assert "anthropic" in str(exc_info.value).lower()

    @pytest.mark.asyncio
    async def test_complete_rate_limit_error(
        self,
        anthropic_provider: AnthropicProvider,
        sample_messages: list[LLMMessage],
    ) -> None:
        """Test that 429 response raises LLMRateLimitError.

        Verifies that rate limit errors from the API
        are properly translated to LLMRateLimitError.
        """
        mock_response = MagicMock(spec=httpx.Response)
        mock_response.status_code = 429
        mock_response.text = "Rate limit exceeded"

        with patch("httpx.AsyncClient") as mock_client_class:
            mock_client = AsyncMock()
            mock_client.post.return_value = mock_response
            mock_client.__aenter__.return_value = mock_client
            mock_client.__aexit__.return_value = None
            mock_client_class.return_value = mock_client

            with pytest.raises(LLMRateLimitError) as exc_info:
                await anthropic_provider.complete(sample_messages)

            assert "rate limit" in str(exc_info.value).lower()

    @pytest.mark.asyncio
    async def test_complete_provider_error_400(
        self,
        anthropic_provider: AnthropicProvider,
        sample_messages: list[LLMMessage],
    ) -> None:
        """Test that 400 response raises LLMProviderError.

        Verifies that bad request errors from the API
        are properly translated to LLMProviderError.
        """
        mock_response = MagicMock(spec=httpx.Response)
        mock_response.status_code = 400
        mock_response.text = "Bad request: invalid model"

        with patch("httpx.AsyncClient") as mock_client_class:
            mock_client = AsyncMock()
            mock_client.post.return_value = mock_response
            mock_client.__aenter__.return_value = mock_client
            mock_client.__aexit__.return_value = None
            mock_client_class.return_value = mock_client

            with pytest.raises(LLMProviderError) as exc_info:
                await anthropic_provider.complete(sample_messages)

            assert exc_info.value.status_code == 400

    @pytest.mark.asyncio
    async def test_complete_provider_error_500(
        self,
        anthropic_provider: AnthropicProvider,
        sample_messages: list[LLMMessage],
    ) -> None:
        """Test that 500 response raises LLMProviderError.

        Verifies that server errors from the API
        are properly translated to LLMProviderError.
        """
        mock_response = MagicMock(spec=httpx.Response)
        mock_response.status_code = 500
        mock_response.text = "Internal server error"

        with patch("httpx.AsyncClient") as mock_client_class:
            mock_client = AsyncMock()
            mock_client.post.return_value = mock_response
            mock_client.__aenter__.return_value = mock_client
            mock_client.__aexit__.return_value = None
            mock_client_class.return_value = mock_client

            with pytest.raises(LLMProviderError) as exc_info:
                await anthropic_provider.complete(sample_messages)

            assert exc_info.value.status_code == 500

    @pytest.mark.asyncio
    async def test_complete_timeout_error(
        self,
        anthropic_provider: AnthropicProvider,
        sample_messages: list[LLMMessage],
    ) -> None:
        """Test that timeout raises LLMTimeoutError.

        Verifies that request timeouts are properly
        translated to LLMTimeoutError.
        """
        with patch("httpx.AsyncClient") as mock_client_class:
            mock_client = AsyncMock()
            mock_client.post.side_effect = httpx.TimeoutException("Request timed out")
            mock_client.__aenter__.return_value = mock_client
            mock_client.__aexit__.return_value = None
            mock_client_class.return_value = mock_client

            with pytest.raises(LLMTimeoutError) as exc_info:
                await anthropic_provider.complete(sample_messages)

            assert "timed out" in str(exc_info.value).lower()

    @pytest.mark.asyncio
    async def test_complete_http_error(
        self,
        anthropic_provider: AnthropicProvider,
        sample_messages: list[LLMMessage],
    ) -> None:
        """Test that HTTP errors raise LLMProviderError.

        Verifies that generic HTTP errors are properly
        translated to LLMProviderError.
        """
        with patch("httpx.AsyncClient") as mock_client_class:
            mock_client = AsyncMock()
            mock_client.post.side_effect = httpx.HTTPError("Connection failed")
            mock_client.__aenter__.return_value = mock_client
            mock_client.__aexit__.return_value = None
            mock_client_class.return_value = mock_client

            with pytest.raises(LLMProviderError) as exc_info:
                await anthropic_provider.complete(sample_messages)

            assert "http error" in str(exc_info.value).lower()

    @pytest.mark.asyncio
    async def test_complete_without_api_key(
        self,
        sample_messages: list[LLMMessage],
    ) -> None:
        """Test that completion without API key raises LLMAuthenticationError.

        Verifies that attempting to complete without a configured
        API key raises LLMAuthenticationError immediately.
        """
        provider = AnthropicProvider(api_key=None)

        with pytest.raises(LLMAuthenticationError) as exc_info:
            await provider.complete(sample_messages)

        assert "not configured" in str(exc_info.value).lower()

    @pytest.mark.asyncio
    async def test_complete_with_invalid_messages(
        self,
        anthropic_provider: AnthropicProvider,
    ) -> None:
        """Test that invalid messages raise LLMProviderError.

        Verifies that passing invalid messages (empty list or
        empty content) raises LLMProviderError.
        """
        with pytest.raises(LLMProviderError) as exc_info:
            await anthropic_provider.complete([])

        assert "invalid" in str(exc_info.value).lower()

    @pytest.mark.asyncio
    async def test_complete_no_content_in_response(
        self,
        anthropic_provider: AnthropicProvider,
        sample_messages: list[LLMMessage],
    ) -> None:
        """Test that response without content raises LLMProviderError.

        Verifies that an API response missing the content array
        is properly handled with LLMProviderError.
        """
        mock_response = MagicMock(spec=httpx.Response)
        mock_response.status_code = 200
        mock_response.json.return_value = {
            "id": "msg_123",
            "model": "claude-3-5-sonnet-20241022",
            "content": [],  # Empty content
            "usage": {"input_tokens": 10, "output_tokens": 0},
        }

        with patch("httpx.AsyncClient") as mock_client_class:
            mock_client = AsyncMock()
            mock_client.post.return_value = mock_response
            mock_client.__aenter__.return_value = mock_client
            mock_client.__aexit__.return_value = None
            mock_client_class.return_value = mock_client

            with pytest.raises(LLMProviderError) as exc_info:
                await anthropic_provider.complete(sample_messages)

            assert "no content" in str(exc_info.value).lower()

    def test_repr(self, anthropic_provider: AnthropicProvider) -> None:
        """Test string representation of provider.

        Verifies that the provider has a useful string representation.
        """
        repr_str = repr(anthropic_provider)
        assert "AnthropicProvider" in repr_str
        assert "anthropic" in repr_str


# ============================================================================
# Exception Tests
# ============================================================================


class TestLLMExceptions:
    """Test suite for LLM exception classes."""

    def test_llm_authentication_error_format(self) -> None:
        """Test LLMAuthenticationError message formatting.

        Verifies that the error includes provider context
        in the message.
        """
        error = LLMAuthenticationError(
            message="Invalid API key",
            provider="openai",
        )
        assert "[openai]" in str(error)
        assert "Invalid API key" in str(error)

    def test_llm_rate_limit_error_with_retry_after(self) -> None:
        """Test LLMRateLimitError with retry_after attribute.

        Verifies that the retry_after attribute is properly set.
        """
        error = LLMRateLimitError(
            message="Rate limit exceeded",
            provider="anthropic",
            retry_after=30.0,
        )
        assert error.retry_after == 30.0
        assert "[anthropic]" in str(error)

    def test_llm_provider_error_with_status_code(self) -> None:
        """Test LLMProviderError with status_code attribute.

        Verifies that the status_code attribute is properly set.
        """
        error = LLMProviderError(
            message="Server error",
            provider="openai",
            status_code=500,
        )
        assert error.status_code == 500
        assert "[openai]" in str(error)

    def test_llm_timeout_error_with_timeout_seconds(self) -> None:
        """Test LLMTimeoutError with timeout_seconds attribute.

        Verifies that the timeout_seconds attribute is properly set.
        """
        error = LLMTimeoutError(
            message="Request timed out",
            provider="anthropic",
            timeout_seconds=60.0,
        )
        assert error.timeout_seconds == 60.0
        assert "[anthropic]" in str(error)


# ============================================================================
# Types Tests
# ============================================================================


class TestLLMTypes:
    """Test suite for LLM type classes."""

    def test_llm_message_to_dict(self) -> None:
        """Test LLMMessage.to_dict() method.

        Verifies that messages are correctly converted to dictionaries.
        """
        message = LLMMessage(
            role=LLMRole.USER,
            content="Hello, world!",
            name="test_user",
        )
        result = message.to_dict()

        assert result["role"] == "user"
        assert result["content"] == "Hello, world!"
        assert result["name"] == "test_user"

    def test_llm_message_to_dict_without_name(self) -> None:
        """Test LLMMessage.to_dict() without optional name field.

        Verifies that the name field is omitted when not set.
        """
        message = LLMMessage(role=LLMRole.ASSISTANT, content="Response")
        result = message.to_dict()

        assert result["role"] == "assistant"
        assert result["content"] == "Response"
        assert "name" not in result

    def test_llm_usage_defaults(self) -> None:
        """Test LLMUsage default values.

        Verifies that usage defaults to zero tokens.
        """
        usage = LLMUsage()

        assert usage.prompt_tokens == 0
        assert usage.completion_tokens == 0
        assert usage.total_tokens == 0

    def test_llm_config_defaults(self) -> None:
        """Test LLMConfig default values.

        Verifies that configuration has sensible defaults.
        """
        config = LLMConfig()

        assert config.model is None
        assert config.temperature == 0.7
        assert config.max_tokens == 4096
        assert config.top_p == 1.0
        assert config.frequency_penalty == 0.0
        assert config.presence_penalty == 0.0
        assert config.stop is None
        assert config.timeout == 60

    def test_llm_response_creation(self) -> None:
        """Test LLMResponse creation with all fields.

        Verifies that response objects are created correctly.
        """
        usage = LLMUsage(prompt_tokens=10, completion_tokens=20, total_tokens=30)
        response = LLMResponse(
            content="Test response",
            model="gpt-4o",
            provider="openai",
            usage=usage,
            finish_reason="stop",
            request_id="req_123",
        )

        assert response.content == "Test response"
        assert response.model == "gpt-4o"
        assert response.provider == "openai"
        assert response.usage.total_tokens == 30
        assert response.finish_reason == "stop"
        assert response.request_id == "req_123"
        assert response.created_at is not None

    def test_llm_role_values(self) -> None:
        """Test LLMRole enum values.

        Verifies that role enum has the expected values.
        """
        assert LLMRole.SYSTEM.value == "system"
        assert LLMRole.USER.value == "user"
        assert LLMRole.ASSISTANT.value == "assistant"
