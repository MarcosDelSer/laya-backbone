"""LLM integration module for LAYA AI Service.

Provides a provider-agnostic interface for interacting with large language
models. Supports multiple providers (OpenAI, Anthropic) with consistent
types, caching, fallback strategies, and token tracking.

Usage:
    from app.llm import OpenAIProvider, LLMMessage, LLMResponse, LLMRole

    # Create a message
    message = LLMMessage(role=LLMRole.USER, content="Hello!")

    # Use a provider
    provider = OpenAIProvider()
    if provider.is_available():
        response = await provider.complete([message])
"""

from app.llm.base import BaseLLMProvider
from app.llm.providers import OpenAIProvider
from app.llm.types import LLMConfig, LLMMessage, LLMResponse, LLMRole, LLMUsage

__all__ = [
    "BaseLLMProvider",
    "LLMConfig",
    "LLMMessage",
    "LLMResponse",
    "LLMRole",
    "LLMUsage",
    "OpenAIProvider",
]
