"""LLM integration module for LAYA AI Service.

Provides a provider-agnostic interface for interacting with large language
models. Supports multiple providers (OpenAI, Anthropic) with consistent
types, caching, fallback strategies, and token tracking.

Usage:
    from app.llm import LLMClient, LLMMessage, LLMRole

    # Create a client (recommended approach)
    client = LLMClient(db=session)

    # Create a message
    message = LLMMessage(role=LLMRole.USER, content="Hello!")

    # Generate a completion
    response = await client.complete([message])
    print(response.content)

    # Or use the factory directly for low-level access
    from app.llm import LLMProviderFactory

    factory = LLMProviderFactory()
    provider = factory.get_provider()  # Gets default provider
    if provider.is_available():
        response = await provider.complete([message])

    # Or get a specific provider
    openai = factory.get_provider("openai")
    anthropic = factory.get_provider("anthropic")
"""

from app.llm.base import BaseLLMProvider
from app.llm.client import (
    CompletionFailedError,
    LLMClient,
    LLMClientError,
    NoProvidersAvailableError,
)
from app.llm.factory import LLMProviderFactory
from app.llm.providers import AnthropicProvider, OpenAIProvider
from app.llm.types import LLMConfig, LLMMessage, LLMResponse, LLMRole, LLMUsage

__all__ = [
    "AnthropicProvider",
    "BaseLLMProvider",
    "CompletionFailedError",
    "LLMClient",
    "LLMClientError",
    "LLMConfig",
    "LLMMessage",
    "LLMProviderFactory",
    "LLMResponse",
    "LLMRole",
    "LLMUsage",
    "NoProvidersAvailableError",
    "OpenAIProvider",
]
