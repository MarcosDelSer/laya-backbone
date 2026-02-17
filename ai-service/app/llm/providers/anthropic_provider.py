"""Anthropic Claude LLM provider implementation for LAYA AI Service.

Provides integration with Anthropic's Claude models including Claude 3.5 Sonnet,
Claude 3 Opus, and Claude 3 Haiku. Handles authentication, API calls, streaming,
and error mapping to the provider-agnostic interface.
"""

from datetime import datetime
from typing import AsyncIterator, Optional

import httpx

from app.config import settings
from app.llm.base import BaseLLMProvider
from app.llm.exceptions import (
    LLMAuthenticationError,
    LLMProviderError,
    LLMRateLimitError,
    LLMTimeoutError,
)
from app.llm.types import LLMConfig, LLMMessage, LLMResponse, LLMRole, LLMUsage

# Anthropic API endpoints
ANTHROPIC_API_BASE = "https://api.anthropic.com/v1"
ANTHROPIC_MESSAGES_ENDPOINT = f"{ANTHROPIC_API_BASE}/messages"

# Anthropic API version
ANTHROPIC_API_VERSION = "2023-06-01"

# Supported Anthropic models
ANTHROPIC_MODELS = [
    "claude-3-5-sonnet-20241022",
    "claude-3-5-haiku-20241022",
    "claude-3-opus-20240229",
    "claude-3-sonnet-20240229",
    "claude-3-haiku-20240307",
]


class AnthropicProvider(BaseLLMProvider):
    """Anthropic Claude LLM provider implementation.

    Provides access to Anthropic's Claude models through the Messages API.
    Supports both synchronous completions and streaming responses.

    Attributes:
        name: Provider identifier ("anthropic")
        default_model: Default model to use (claude-3-5-sonnet-20241022)
        api_key: Anthropic API key for authentication

    Example:
        provider = AnthropicProvider()
        if provider.is_available():
            response = await provider.complete([
                LLMMessage(role=LLMRole.USER, content="Hello!")
            ])
            print(response.content)
    """

    name: str = "anthropic"
    default_model: str = "claude-3-5-sonnet-20241022"

    def __init__(self, api_key: Optional[str] = None) -> None:
        """Initialize the Anthropic provider.

        Args:
            api_key: Optional API key override. If not provided,
                     uses the ANTHROPIC_API_KEY from settings.
        """
        self.api_key = api_key or settings.anthropic_api_key

    async def complete(
        self,
        messages: list[LLMMessage],
        config: Optional[LLMConfig] = None,
    ) -> LLMResponse:
        """Generate a completion using Anthropic's Messages API.

        Takes a list of messages and returns a completion response.
        The messages should be in conversation order with appropriate
        roles (system, user, assistant).

        Args:
            messages: List of messages forming the conversation
            config: Optional configuration for the completion

        Returns:
            LLMResponse containing the generated content and metadata

        Raises:
            LLMAuthenticationError: If API key is invalid or missing
            LLMRateLimitError: If rate limits are exceeded
            LLMTimeoutError: If the request times out
            LLMProviderError: For other Anthropic API errors
        """
        if not self.is_available():
            raise LLMAuthenticationError(
                message="Anthropic API key not configured",
                provider=self.name,
            )

        if not self.validate_messages(messages):
            raise LLMProviderError(
                message="Invalid or empty messages provided",
                provider=self.name,
            )

        effective_config = self._merge_config(config)
        payload = self._build_request_payload(messages, effective_config)

        try:
            async with httpx.AsyncClient(timeout=effective_config.timeout) as client:
                response = await client.post(
                    ANTHROPIC_MESSAGES_ENDPOINT,
                    headers=self._get_headers(),
                    json=payload,
                )

                return self._handle_response(response, effective_config.model)

        except httpx.TimeoutException as e:
            raise LLMTimeoutError(
                message=f"Request timed out after {effective_config.timeout} seconds",
                provider=self.name,
                original_error=e,
                timeout_seconds=effective_config.timeout,
            )
        except httpx.HTTPError as e:
            raise LLMProviderError(
                message=f"HTTP error during API call: {str(e)}",
                provider=self.name,
                original_error=e,
            )

    async def complete_stream(
        self,
        messages: list[LLMMessage],
        config: Optional[LLMConfig] = None,
    ) -> AsyncIterator[str]:
        """Generate a streaming completion using Anthropic's API.

        Similar to complete() but yields content chunks as they are
        generated, enabling real-time streaming to clients.

        Args:
            messages: List of messages forming the conversation
            config: Optional configuration for the completion

        Yields:
            String chunks of the generated content

        Raises:
            LLMAuthenticationError: If API key is invalid or missing
            LLMRateLimitError: If rate limits are exceeded
            LLMTimeoutError: If the request times out
            LLMProviderError: For other Anthropic API errors
        """
        if not self.is_available():
            raise LLMAuthenticationError(
                message="Anthropic API key not configured",
                provider=self.name,
            )

        if not self.validate_messages(messages):
            raise LLMProviderError(
                message="Invalid or empty messages provided",
                provider=self.name,
            )

        effective_config = self._merge_config(config)
        payload = self._build_request_payload(messages, effective_config, stream=True)

        try:
            async with httpx.AsyncClient(timeout=effective_config.timeout) as client:
                async with client.stream(
                    "POST",
                    ANTHROPIC_MESSAGES_ENDPOINT,
                    headers=self._get_headers(),
                    json=payload,
                ) as response:
                    if response.status_code != 200:
                        error_body = await response.aread()
                        self._handle_error_response(
                            response.status_code,
                            error_body.decode("utf-8"),
                        )

                    async for line in response.aiter_lines():
                        if line.startswith("data: "):
                            data = line[6:]  # Remove "data: " prefix
                            if data == "[DONE]":
                                break
                            try:
                                import json

                                chunk = json.loads(data)
                                event_type = chunk.get("type", "")

                                # Handle content_block_delta events
                                if event_type == "content_block_delta":
                                    delta = chunk.get("delta", {})
                                    if delta.get("type") == "text_delta":
                                        text = delta.get("text", "")
                                        if text:
                                            yield text
                            except (json.JSONDecodeError, IndexError, KeyError):
                                # Skip malformed chunks
                                continue

        except httpx.TimeoutException as e:
            raise LLMTimeoutError(
                message=f"Stream timed out after {effective_config.timeout} seconds",
                provider=self.name,
                original_error=e,
                timeout_seconds=effective_config.timeout,
            )
        except httpx.HTTPError as e:
            raise LLMProviderError(
                message=f"HTTP error during streaming: {str(e)}",
                provider=self.name,
                original_error=e,
            )

    def is_available(self) -> bool:
        """Check if the Anthropic provider is available and configured.

        Verifies that an API key is present and appears to be valid
        (non-empty string).

        Returns:
            True if the provider can be used, False otherwise
        """
        if not self.api_key:
            return False

        # Allow any non-empty string for flexibility (test keys, etc.)
        return len(self.api_key) > 0

    def get_model_list(self) -> list[str]:
        """Get list of supported Anthropic models.

        Returns:
            List of model identifiers supported by this provider
        """
        return ANTHROPIC_MODELS.copy()

    def get_default_config(self) -> LLMConfig:
        """Get default configuration for Anthropic.

        Returns configuration with sensible defaults for Claude models.

        Returns:
            Default LLMConfig for Anthropic
        """
        return LLMConfig(
            model=self.default_model,
            temperature=settings.llm_temperature,
            max_tokens=settings.llm_max_tokens,
            timeout=settings.llm_timeout,
        )

    def _get_headers(self) -> dict[str, str]:
        """Build headers for Anthropic API requests.

        Returns:
            Dictionary of HTTP headers including authentication
        """
        return {
            "x-api-key": self.api_key,
            "anthropic-version": ANTHROPIC_API_VERSION,
            "Content-Type": "application/json",
        }

    def _merge_config(self, config: Optional[LLMConfig]) -> LLMConfig:
        """Merge provided config with defaults.

        Args:
            config: Optional configuration from the caller

        Returns:
            Merged configuration with defaults filled in
        """
        default = self.get_default_config()

        if config is None:
            return default

        return LLMConfig(
            model=config.model or default.model,
            temperature=config.temperature,
            max_tokens=config.max_tokens,
            top_p=config.top_p,
            frequency_penalty=config.frequency_penalty,
            presence_penalty=config.presence_penalty,
            stop=config.stop,
            timeout=config.timeout,
        )

    def _build_request_payload(
        self,
        messages: list[LLMMessage],
        config: LLMConfig,
        stream: bool = False,
    ) -> dict:
        """Build the request payload for Anthropic API.

        Anthropic's API handles system messages differently - they must be
        passed as a separate 'system' parameter rather than in the messages
        array.

        Args:
            messages: List of messages to include
            config: Configuration for the request
            stream: Whether to enable streaming

        Returns:
            Dictionary payload for the API request
        """
        # Separate system messages from conversation messages
        system_content = ""
        conversation_messages = []

        for msg in messages:
            if msg.role == LLMRole.SYSTEM:
                # Concatenate multiple system messages if present
                if system_content:
                    system_content += "\n\n"
                system_content += msg.content
            else:
                # Convert to Anthropic's message format
                conversation_messages.append({
                    "role": msg.role.value,
                    "content": msg.content,
                })

        payload = {
            "model": config.model,
            "messages": conversation_messages,
            "max_tokens": config.max_tokens,
            "temperature": config.temperature,
            "top_p": config.top_p,
            "stream": stream,
        }

        # Add system message if present
        if system_content:
            payload["system"] = system_content

        # Add stop sequences if specified
        if config.stop:
            payload["stop_sequences"] = config.stop

        return payload

    def _handle_response(self, response: httpx.Response, model: str) -> LLMResponse:
        """Handle the response from Anthropic API.

        Args:
            response: The HTTP response from Anthropic
            model: The model that was used

        Returns:
            LLMResponse with parsed content and metadata

        Raises:
            LLMAuthenticationError: For 401 errors
            LLMRateLimitError: For 429 errors
            LLMProviderError: For other error status codes
        """
        if response.status_code != 200:
            self._handle_error_response(response.status_code, response.text)

        data = response.json()

        # Extract content from the response
        # Anthropic returns content as an array of content blocks
        content_blocks = data.get("content", [])
        if not content_blocks:
            raise LLMProviderError(
                message="No content returned in Anthropic response",
                provider=self.name,
            )

        # Concatenate all text content blocks
        content = ""
        for block in content_blocks:
            if block.get("type") == "text":
                content += block.get("text", "")

        stop_reason = data.get("stop_reason")

        # Extract usage information
        usage_data = data.get("usage", {})
        usage = LLMUsage(
            prompt_tokens=usage_data.get("input_tokens", 0),
            completion_tokens=usage_data.get("output_tokens", 0),
            total_tokens=(
                usage_data.get("input_tokens", 0)
                + usage_data.get("output_tokens", 0)
            ),
        )

        return LLMResponse(
            content=content,
            model=data.get("model", model),
            provider=self.name,
            usage=usage,
            finish_reason=stop_reason,
            created_at=datetime.utcnow(),
            request_id=data.get("id"),
        )

    def _handle_error_response(self, status_code: int, response_text: str) -> None:
        """Handle error responses from Anthropic API.

        Args:
            status_code: HTTP status code
            response_text: Response body text

        Raises:
            LLMAuthenticationError: For 401 errors
            LLMRateLimitError: For 429 errors
            LLMProviderError: For other error status codes
        """
        if status_code == 401:
            raise LLMAuthenticationError(
                message="Invalid or expired Anthropic API key",
                provider=self.name,
            )

        if status_code == 429:
            raise LLMRateLimitError(
                message="Anthropic rate limit exceeded. Please retry after a short wait.",
                provider=self.name,
            )

        if status_code == 400:
            raise LLMProviderError(
                message=f"Bad request: {response_text}",
                provider=self.name,
                status_code=status_code,
            )

        if status_code >= 500:
            raise LLMProviderError(
                message=f"Anthropic server error: {response_text}",
                provider=self.name,
                status_code=status_code,
            )

        raise LLMProviderError(
            message=f"Anthropic API error ({status_code}): {response_text}",
            provider=self.name,
            status_code=status_code,
        )
