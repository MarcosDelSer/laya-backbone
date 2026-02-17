"""OpenAI LLM provider implementation for LAYA AI Service.

Provides integration with OpenAI's GPT models including GPT-4o, GPT-4,
and GPT-3.5-turbo. Handles authentication, API calls, streaming,
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
from app.llm.types import LLMConfig, LLMMessage, LLMResponse, LLMUsage

# OpenAI API endpoints
OPENAI_API_BASE = "https://api.openai.com/v1"
OPENAI_CHAT_ENDPOINT = f"{OPENAI_API_BASE}/chat/completions"

# Supported OpenAI models
OPENAI_MODELS = [
    "gpt-4o",
    "gpt-4o-mini",
    "gpt-4-turbo",
    "gpt-4",
    "gpt-3.5-turbo",
]


class OpenAIProvider(BaseLLMProvider):
    """OpenAI LLM provider implementation.

    Provides access to OpenAI's GPT models through the Chat Completions API.
    Supports both synchronous completions and streaming responses.

    Attributes:
        name: Provider identifier ("openai")
        default_model: Default model to use (gpt-4o)
        api_key: OpenAI API key for authentication

    Example:
        provider = OpenAIProvider()
        if provider.is_available():
            response = await provider.complete([
                LLMMessage(role=LLMRole.USER, content="Hello!")
            ])
            print(response.content)
    """

    name: str = "openai"
    default_model: str = "gpt-4o"

    def __init__(self, api_key: Optional[str] = None) -> None:
        """Initialize the OpenAI provider.

        Args:
            api_key: Optional API key override. If not provided,
                     uses the OPENAI_API_KEY from settings.
        """
        self.api_key = api_key or settings.openai_api_key

    async def complete(
        self,
        messages: list[LLMMessage],
        config: Optional[LLMConfig] = None,
    ) -> LLMResponse:
        """Generate a completion using OpenAI's Chat Completions API.

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
            LLMProviderError: For other OpenAI API errors
        """
        if not self.is_available():
            raise LLMAuthenticationError(
                message="OpenAI API key not configured",
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
                    OPENAI_CHAT_ENDPOINT,
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
        """Generate a streaming completion using OpenAI's API.

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
            LLMProviderError: For other OpenAI API errors
        """
        if not self.is_available():
            raise LLMAuthenticationError(
                message="OpenAI API key not configured",
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
                    OPENAI_CHAT_ENDPOINT,
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
                                delta = chunk.get("choices", [{}])[0].get("delta", {})
                                content = delta.get("content", "")
                                if content:
                                    yield content
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
        """Check if the OpenAI provider is available and configured.

        Verifies that an API key is present and appears to be valid
        (non-empty string starting with expected prefix).

        Returns:
            True if the provider can be used, False otherwise
        """
        if not self.api_key:
            return False

        # OpenAI API keys typically start with "sk-"
        # Allow any non-empty string for flexibility (test keys, etc.)
        return len(self.api_key) > 0

    def get_model_list(self) -> list[str]:
        """Get list of supported OpenAI models.

        Returns:
            List of model identifiers supported by this provider
        """
        return OPENAI_MODELS.copy()

    def get_default_config(self) -> LLMConfig:
        """Get default configuration for OpenAI.

        Returns configuration with sensible defaults for OpenAI models.

        Returns:
            Default LLMConfig for OpenAI
        """
        return LLMConfig(
            model=self.default_model,
            temperature=settings.llm_temperature,
            max_tokens=settings.llm_max_tokens,
            timeout=settings.llm_timeout,
        )

    def _get_headers(self) -> dict[str, str]:
        """Build headers for OpenAI API requests.

        Returns:
            Dictionary of HTTP headers including authorization
        """
        return {
            "Authorization": f"Bearer {self.api_key}",
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
        """Build the request payload for OpenAI API.

        Args:
            messages: List of messages to include
            config: Configuration for the request
            stream: Whether to enable streaming

        Returns:
            Dictionary payload for the API request
        """
        payload = {
            "model": config.model,
            "messages": [msg.to_dict() for msg in messages],
            "temperature": config.temperature,
            "max_tokens": config.max_tokens,
            "top_p": config.top_p,
            "frequency_penalty": config.frequency_penalty,
            "presence_penalty": config.presence_penalty,
            "stream": stream,
        }

        if config.stop:
            payload["stop"] = config.stop

        return payload

    def _handle_response(self, response: httpx.Response, model: str) -> LLMResponse:
        """Handle the response from OpenAI API.

        Args:
            response: The HTTP response from OpenAI
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
        choices = data.get("choices", [])
        if not choices:
            raise LLMProviderError(
                message="No choices returned in OpenAI response",
                provider=self.name,
            )

        content = choices[0].get("message", {}).get("content", "")
        finish_reason = choices[0].get("finish_reason")

        # Extract usage information
        usage_data = data.get("usage", {})
        usage = LLMUsage(
            prompt_tokens=usage_data.get("prompt_tokens", 0),
            completion_tokens=usage_data.get("completion_tokens", 0),
            total_tokens=usage_data.get("total_tokens", 0),
        )

        return LLMResponse(
            content=content,
            model=data.get("model", model),
            provider=self.name,
            usage=usage,
            finish_reason=finish_reason,
            created_at=datetime.utcnow(),
            request_id=data.get("id"),
        )

    def _handle_error_response(self, status_code: int, response_text: str) -> None:
        """Handle error responses from OpenAI API.

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
                message="Invalid or expired OpenAI API key",
                provider=self.name,
            )

        if status_code == 429:
            raise LLMRateLimitError(
                message="OpenAI rate limit exceeded. Please retry after a short wait.",
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
                message=f"OpenAI server error: {response_text}",
                provider=self.name,
                status_code=status_code,
            )

        raise LLMProviderError(
            message=f"OpenAI API error ({status_code}): {response_text}",
            provider=self.name,
            status_code=status_code,
        )
