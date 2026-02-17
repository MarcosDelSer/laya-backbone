"""LLM domain schemas for LAYA AI Service.

Defines Pydantic schemas for LLM completion requests, responses, and
usage statistics. These schemas are used for API request/response
validation and serialization for the LLM integration endpoints.
"""

from datetime import datetime
from enum import Enum
from typing import Any, Optional
from uuid import UUID

from pydantic import BaseModel, Field

from app.schemas.base import BaseResponse, BaseSchema, PaginatedResponse


class LLMProvider(str, Enum):
    """Supported LLM providers.

    Attributes:
        OPENAI: OpenAI GPT models
        ANTHROPIC: Anthropic Claude models
    """

    OPENAI = "openai"
    ANTHROPIC = "anthropic"


class LLMMessageRole(str, Enum):
    """Role of a message in an LLM conversation.

    Attributes:
        SYSTEM: System message providing instructions or context
        USER: Message from the user
        ASSISTANT: Response from the LLM assistant
    """

    SYSTEM = "system"
    USER = "user"
    ASSISTANT = "assistant"


class LLMMessageSchema(BaseSchema):
    """Schema for a single message in an LLM conversation.

    Represents a message with role and content for building
    conversation history in completion requests.

    Attributes:
        role: The role of the message sender (system, user, assistant)
        content: The text content of the message
        name: Optional name identifier for the message sender
    """

    role: LLMMessageRole = Field(
        ...,
        description="The role of the message sender",
    )
    content: str = Field(
        ...,
        min_length=1,
        max_length=100000,
        description="The text content of the message",
    )
    name: Optional[str] = Field(
        default=None,
        max_length=64,
        description="Optional name identifier for the message sender",
    )


class LLMCompletionRequest(BaseSchema):
    """Request schema for LLM text completion.

    Used to request LLM completions with customizable parameters
    for model selection, temperature, and other generation options.

    Attributes:
        messages: List of messages for the conversation
        provider: LLM provider to use (openai, anthropic)
        model: Specific model to use (e.g., gpt-4, claude-3-sonnet)
        temperature: Sampling temperature (0.0-2.0)
        max_tokens: Maximum tokens to generate
        top_p: Nucleus sampling parameter
        frequency_penalty: Frequency penalty for repetition
        presence_penalty: Presence penalty for new topics
        stop: Stop sequences to end generation
        stream: Whether to stream the response
        use_cache: Whether to use response caching
    """

    messages: list[LLMMessageSchema] = Field(
        ...,
        min_length=1,
        max_length=100,
        description="List of messages for the conversation",
    )
    provider: Optional[LLMProvider] = Field(
        default=None,
        description="LLM provider to use (defaults to configured default)",
    )
    model: Optional[str] = Field(
        default=None,
        max_length=100,
        description="Specific model to use (e.g., gpt-4, claude-3-sonnet)",
    )
    temperature: Optional[float] = Field(
        default=None,
        ge=0.0,
        le=2.0,
        description="Sampling temperature (0.0-2.0)",
    )
    max_tokens: Optional[int] = Field(
        default=None,
        ge=1,
        le=100000,
        description="Maximum tokens to generate",
    )
    top_p: Optional[float] = Field(
        default=None,
        ge=0.0,
        le=1.0,
        description="Nucleus sampling parameter",
    )
    frequency_penalty: Optional[float] = Field(
        default=None,
        ge=-2.0,
        le=2.0,
        description="Frequency penalty for repetition",
    )
    presence_penalty: Optional[float] = Field(
        default=None,
        ge=-2.0,
        le=2.0,
        description="Presence penalty for new topics",
    )
    stop: Optional[list[str]] = Field(
        default=None,
        max_length=4,
        description="Stop sequences to end generation",
    )
    stream: bool = Field(
        default=False,
        description="Whether to stream the response",
    )
    use_cache: bool = Field(
        default=True,
        description="Whether to use response caching",
    )


class LLMUsageStats(BaseSchema):
    """Token usage statistics for an LLM completion.

    Tracks token counts and estimated costs for monitoring
    and billing purposes.

    Attributes:
        prompt_tokens: Number of tokens in the prompt
        completion_tokens: Number of tokens in the completion
        total_tokens: Total tokens used (prompt + completion)
        estimated_cost: Estimated cost in USD
    """

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
    estimated_cost: Optional[float] = Field(
        default=None,
        ge=0.0,
        description="Estimated cost in USD",
    )


class LLMCompletionResponse(BaseSchema):
    """Response schema for LLM text completion.

    Contains the generated content along with metadata about
    the completion including model information and token usage.

    Attributes:
        content: The generated text content
        model: The model used for generation
        provider: The LLM provider used
        usage: Token usage statistics
        finish_reason: Why the generation stopped (stop, length, etc.)
        created_at: Timestamp when the response was generated
        request_id: Optional request identifier from the provider
        cached: Whether the response was served from cache
        latency_ms: Response latency in milliseconds
    """

    content: str = Field(
        ...,
        description="The generated text content",
    )
    model: str = Field(
        ...,
        description="The model used for generation",
    )
    provider: LLMProvider = Field(
        ...,
        description="The LLM provider used",
    )
    usage: LLMUsageStats = Field(
        default_factory=LLMUsageStats,
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
    cached: bool = Field(
        default=False,
        description="Whether the response was served from cache",
    )
    latency_ms: Optional[float] = Field(
        default=None,
        ge=0.0,
        description="Response latency in milliseconds",
    )


class LLMUsageLogResponse(BaseResponse):
    """Response schema for LLM usage log entry.

    Contains details about a single LLM API usage record
    for tracking and analytics purposes.

    Attributes:
        provider: The LLM provider used
        model: The model used
        prompt_tokens: Number of prompt tokens
        completion_tokens: Number of completion tokens
        total_tokens: Total tokens used
        estimated_cost: Estimated cost in USD
        latency_ms: Response latency in milliseconds
        success: Whether the request was successful
        error_message: Error message if request failed
    """

    provider: LLMProvider = Field(
        ...,
        description="The LLM provider used",
    )
    model: str = Field(
        ...,
        description="The model used",
    )
    prompt_tokens: int = Field(
        default=0,
        ge=0,
        description="Number of prompt tokens",
    )
    completion_tokens: int = Field(
        default=0,
        ge=0,
        description="Number of completion tokens",
    )
    total_tokens: int = Field(
        default=0,
        ge=0,
        description="Total tokens used",
    )
    estimated_cost: Optional[float] = Field(
        default=None,
        ge=0.0,
        description="Estimated cost in USD",
    )
    latency_ms: Optional[float] = Field(
        default=None,
        ge=0.0,
        description="Response latency in milliseconds",
    )
    success: bool = Field(
        default=True,
        description="Whether the request was successful",
    )
    error_message: Optional[str] = Field(
        default=None,
        description="Error message if request failed",
    )


class LLMUsageListResponse(PaginatedResponse):
    """Paginated response for LLM usage logs.

    Attributes:
        items: List of usage log entries
    """

    items: list[LLMUsageLogResponse] = Field(
        default_factory=list,
        description="List of usage log entries",
    )


class LLMUsageSummary(BaseSchema):
    """Summary statistics for LLM usage over a time period.

    Provides aggregated usage statistics for analytics dashboards.

    Attributes:
        total_requests: Total number of LLM requests
        successful_requests: Number of successful requests
        failed_requests: Number of failed requests
        total_tokens: Total tokens used
        total_prompt_tokens: Total prompt tokens
        total_completion_tokens: Total completion tokens
        total_cost: Total estimated cost in USD
        average_latency_ms: Average response latency
        by_provider: Usage breakdown by provider
        by_model: Usage breakdown by model
        period_start: Start of the summary period
        period_end: End of the summary period
    """

    total_requests: int = Field(
        default=0,
        ge=0,
        description="Total number of LLM requests",
    )
    successful_requests: int = Field(
        default=0,
        ge=0,
        description="Number of successful requests",
    )
    failed_requests: int = Field(
        default=0,
        ge=0,
        description="Number of failed requests",
    )
    total_tokens: int = Field(
        default=0,
        ge=0,
        description="Total tokens used",
    )
    total_prompt_tokens: int = Field(
        default=0,
        ge=0,
        description="Total prompt tokens",
    )
    total_completion_tokens: int = Field(
        default=0,
        ge=0,
        description="Total completion tokens",
    )
    total_cost: Optional[float] = Field(
        default=None,
        ge=0.0,
        description="Total estimated cost in USD",
    )
    average_latency_ms: Optional[float] = Field(
        default=None,
        ge=0.0,
        description="Average response latency in milliseconds",
    )
    by_provider: Optional[dict[str, int]] = Field(
        default=None,
        description="Usage breakdown by provider",
    )
    by_model: Optional[dict[str, int]] = Field(
        default=None,
        description="Usage breakdown by model",
    )
    period_start: Optional[datetime] = Field(
        default=None,
        description="Start of the summary period",
    )
    period_end: Optional[datetime] = Field(
        default=None,
        description="End of the summary period",
    )


class LLMHealthResponse(BaseSchema):
    """Response schema for LLM service health check.

    Provides status information about available LLM providers.

    Attributes:
        status: Overall health status (healthy, degraded, unhealthy)
        providers: Health status of each provider
        default_provider: The default provider configured
        cache_available: Whether caching is available
    """

    status: str = Field(
        ...,
        description="Overall health status (healthy, degraded, unhealthy)",
    )
    providers: dict[str, bool] = Field(
        default_factory=dict,
        description="Health status of each provider",
    )
    default_provider: Optional[str] = Field(
        default=None,
        description="The default provider configured",
    )
    cache_available: bool = Field(
        default=False,
        description="Whether caching is available",
    )


class LLMModelInfo(BaseSchema):
    """Information about an available LLM model.

    Attributes:
        id: Model identifier
        name: Display name for the model
        provider: Provider that offers this model
        context_window: Maximum context window size
        max_output_tokens: Maximum output tokens
        cost_per_1k_input: Cost per 1K input tokens in USD
        cost_per_1k_output: Cost per 1K output tokens in USD
    """

    id: str = Field(
        ...,
        description="Model identifier",
    )
    name: str = Field(
        ...,
        description="Display name for the model",
    )
    provider: LLMProvider = Field(
        ...,
        description="Provider that offers this model",
    )
    context_window: Optional[int] = Field(
        default=None,
        ge=1,
        description="Maximum context window size",
    )
    max_output_tokens: Optional[int] = Field(
        default=None,
        ge=1,
        description="Maximum output tokens",
    )
    cost_per_1k_input: Optional[float] = Field(
        default=None,
        ge=0.0,
        description="Cost per 1K input tokens in USD",
    )
    cost_per_1k_output: Optional[float] = Field(
        default=None,
        ge=0.0,
        description="Cost per 1K output tokens in USD",
    )


class LLMModelsListResponse(BaseSchema):
    """Response schema for listing available LLM models.

    Attributes:
        models: List of available models
    """

    models: list[LLMModelInfo] = Field(
        default_factory=list,
        description="List of available models",
    )
