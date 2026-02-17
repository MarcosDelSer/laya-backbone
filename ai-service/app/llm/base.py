"""Abstract base class for LLM providers in LAYA AI Service.

Defines the interface that all LLM provider implementations must follow.
This allows for consistent usage across different LLM backends while
supporting provider-specific features and optimizations.
"""

from abc import ABC, abstractmethod
from typing import AsyncIterator, Optional

from app.llm.types import LLMConfig, LLMMessage, LLMResponse


class BaseLLMProvider(ABC):
    """Abstract base class for LLM provider implementations.

    All LLM providers (OpenAI, Anthropic, etc.) must inherit from this
    class and implement the required abstract methods. This ensures a
    consistent interface for the LLM client regardless of the underlying
    provider.

    Attributes:
        name: Unique identifier for the provider
        default_model: Default model to use if not specified

    Example:
        class OpenAIProvider(BaseLLMProvider):
            name = "openai"
            default_model = "gpt-4o"

            async def complete(self, messages, config):
                # Implementation
                pass
    """

    name: str = "base"
    default_model: str = "unknown"

    @abstractmethod
    async def complete(
        self,
        messages: list[LLMMessage],
        config: Optional[LLMConfig] = None,
    ) -> LLMResponse:
        """Generate a completion from the LLM.

        Takes a list of messages and returns a completion response.
        The messages should be in conversation order with appropriate
        roles (system, user, assistant).

        Args:
            messages: List of messages forming the conversation
            config: Optional configuration for the completion

        Returns:
            LLMResponse containing the generated content and metadata

        Raises:
            LLMAuthenticationError: If API credentials are invalid
            LLMRateLimitError: If rate limits are exceeded
            LLMTimeoutError: If the request times out
            LLMProviderError: For other provider-specific errors
        """
        pass

    @abstractmethod
    async def complete_stream(
        self,
        messages: list[LLMMessage],
        config: Optional[LLMConfig] = None,
    ) -> AsyncIterator[str]:
        """Generate a streaming completion from the LLM.

        Similar to complete() but yields content chunks as they are
        generated, enabling real-time streaming to clients.

        Args:
            messages: List of messages forming the conversation
            config: Optional configuration for the completion

        Yields:
            String chunks of the generated content

        Raises:
            LLMAuthenticationError: If API credentials are invalid
            LLMRateLimitError: If rate limits are exceeded
            LLMTimeoutError: If the request times out
            LLMProviderError: For other provider-specific errors
        """
        pass

    @abstractmethod
    def is_available(self) -> bool:
        """Check if the provider is available and configured.

        Verifies that required credentials and configuration are
        present for this provider to function.

        Returns:
            True if the provider can be used, False otherwise
        """
        pass

    @abstractmethod
    def get_model_list(self) -> list[str]:
        """Get list of supported models for this provider.

        Returns:
            List of model identifiers supported by this provider
        """
        pass

    def get_default_config(self) -> LLMConfig:
        """Get default configuration for this provider.

        Returns a configuration with sensible defaults for the
        provider. Subclasses can override to customize defaults.

        Returns:
            Default LLMConfig for this provider
        """
        return LLMConfig(model=self.default_model)

    def validate_messages(self, messages: list[LLMMessage]) -> bool:
        """Validate that messages are properly formatted.

        Checks that the message list is not empty and contains
        properly structured messages.

        Args:
            messages: List of messages to validate

        Returns:
            True if messages are valid, False otherwise
        """
        if not messages:
            return False

        for message in messages:
            if not message.content:
                return False

        return True

    def __repr__(self) -> str:
        """Return string representation of the provider.

        Returns:
            String representation including provider name
        """
        return f"<{self.__class__.__name__} name={self.name}>"
