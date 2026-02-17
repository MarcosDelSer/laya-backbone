"""Data types for LLM integration in LAYA AI Service.

Defines Pydantic schemas and enums for LLM messages, responses, and
configuration. These types provide a provider-agnostic interface for
interacting with different LLM backends (OpenAI, Anthropic, etc.).
"""

from datetime import datetime
from enum import Enum
from typing import Any, Optional

from pydantic import BaseModel, ConfigDict, Field


class LLMRole(str, Enum):
    """Role of a message in an LLM conversation.

    Attributes:
        SYSTEM: System message providing instructions or context
        USER: Message from the user
        ASSISTANT: Response from the LLM assistant
    """

    SYSTEM = "system"
    USER = "user"
    ASSISTANT = "assistant"


class LLMMessage(BaseModel):
    """A single message in an LLM conversation.

    Represents a message with role and content that can be used
    to build conversation history for LLM completions.

    Attributes:
        role: The role of the message sender (system, user, assistant)
        content: The text content of the message
        name: Optional name identifier for the message sender
    """

    model_config = ConfigDict(
        from_attributes=True,
        str_strip_whitespace=True,
    )

    role: LLMRole = Field(
        ...,
        description="The role of the message sender",
    )
    content: str = Field(
        ...,
        description="The text content of the message",
    )
    name: Optional[str] = Field(
        default=None,
        max_length=64,
        description="Optional name identifier for the message sender",
    )

    def to_dict(self) -> dict[str, Any]:
        """Convert message to a dictionary for API calls.

        Returns:
            Dictionary representation of the message
        """
        result: dict[str, Any] = {
            "role": self.role.value,
            "content": self.content,
        }
        if self.name:
            result["name"] = self.name
        return result


class LLMUsage(BaseModel):
    """Token usage statistics for an LLM completion.

    Tracks the number of tokens used in prompts and completions
    for cost tracking and monitoring purposes.

    Attributes:
        prompt_tokens: Number of tokens in the prompt
        completion_tokens: Number of tokens in the completion
        total_tokens: Total tokens used (prompt + completion)
    """

    model_config = ConfigDict(
        from_attributes=True,
    )

    prompt_tokens: int = Field(
        default=0,
        ge=0,
        description="Number of tokens in the prompt",
    )
    completion_tokens: int = Field(
        default=0,
        ge=0,
        description="Number of tokens in the completion",
    )
    total_tokens: int = Field(
        default=0,
        ge=0,
        description="Total tokens used (prompt + completion)",
    )


class LLMResponse(BaseModel):
    """Response from an LLM completion request.

    Contains the generated content along with metadata about
    the completion including model information and token usage.

    Attributes:
        content: The generated text content
        model: The model used for generation
        provider: The LLM provider (openai, anthropic, etc.)
        usage: Token usage statistics
        finish_reason: Why the generation stopped (stop, length, etc.)
        created_at: Timestamp when the response was generated
        request_id: Optional request identifier from the provider
        raw_response: Optional raw response from the provider for debugging
    """

    model_config = ConfigDict(
        from_attributes=True,
    )

    content: str = Field(
        ...,
        description="The generated text content",
    )
    model: str = Field(
        ...,
        description="The model used for generation",
    )
    provider: str = Field(
        ...,
        description="The LLM provider (openai, anthropic, etc.)",
    )
    usage: LLMUsage = Field(
        default_factory=LLMUsage,
        description="Token usage statistics",
    )
    finish_reason: Optional[str] = Field(
        default=None,
        description="Why the generation stopped (stop, length, etc.)",
    )
    created_at: datetime = Field(
        default_factory=datetime.utcnow,
        description="Timestamp when the response was generated",
    )
    request_id: Optional[str] = Field(
        default=None,
        description="Optional request identifier from the provider",
    )
    raw_response: Optional[dict[str, Any]] = Field(
        default=None,
        description="Optional raw response from the provider for debugging",
    )


class LLMConfig(BaseModel):
    """Configuration options for an LLM completion request.

    Provides fine-grained control over the generation parameters
    that can be passed to any LLM provider.

    Attributes:
        model: The model to use for generation
        temperature: Sampling temperature (0.0-2.0)
        max_tokens: Maximum tokens to generate
        top_p: Nucleus sampling parameter
        frequency_penalty: Frequency penalty for repetition
        presence_penalty: Presence penalty for new topics
        stop: Stop sequences to end generation
        timeout: Request timeout in seconds
    """

    model_config = ConfigDict(
        from_attributes=True,
    )

    model: Optional[str] = Field(
        default=None,
        description="The model to use for generation",
    )
    temperature: float = Field(
        default=0.7,
        ge=0.0,
        le=2.0,
        description="Sampling temperature (0.0-2.0)",
    )
    max_tokens: int = Field(
        default=4096,
        ge=1,
        le=100000,
        description="Maximum tokens to generate",
    )
    top_p: float = Field(
        default=1.0,
        ge=0.0,
        le=1.0,
        description="Nucleus sampling parameter",
    )
    frequency_penalty: float = Field(
        default=0.0,
        ge=-2.0,
        le=2.0,
        description="Frequency penalty for repetition",
    )
    presence_penalty: float = Field(
        default=0.0,
        ge=-2.0,
        le=2.0,
        description="Presence penalty for new topics",
    )
    stop: Optional[list[str]] = Field(
        default=None,
        description="Stop sequences to end generation",
    )
    timeout: int = Field(
        default=60,
        ge=1,
        le=600,
        description="Request timeout in seconds",
    )
