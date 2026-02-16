"""LLM integration module for LAYA AI Service.

Provides a provider-agnostic interface for interacting with large language
models. Supports multiple providers (OpenAI, Anthropic) with consistent
types, caching, fallback strategies, and token tracking.

Usage:
    from app.llm import BaseLLMProvider, LLMMessage, LLMResponse, LLMRole

    # Create a message
    message = LLMMessage(role=LLMRole.USER, content="Hello!")

    # Provider implementations will be available after they are created
    # from app.llm.providers import OpenAIProvider
"""

from app.llm.base import BaseLLMProvider
from app.llm.types import LLMConfig, LLMMessage, LLMResponse, LLMRole, LLMUsage

__all__ = [
    "BaseLLMProvider",
    "LLMConfig",
    "LLMMessage",
    "LLMResponse",
    "LLMRole",
    "LLMUsage",
]
