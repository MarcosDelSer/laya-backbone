"""Token tracking service for LLM usage monitoring and cost estimation.

Provides comprehensive token counting, usage tracking, and cost estimation
for LLM completions across multiple providers. Supports both estimation
and actual token counting when usage data is available.
"""

from dataclasses import dataclass, field
from datetime import datetime, timedelta
from decimal import Decimal
from typing import Optional
from uuid import UUID

from sqlalchemy import func, select
from sqlalchemy.ext.asyncio import AsyncSession

from app.llm.models import LLMUsageLog
from app.llm.types import LLMMessage, LLMResponse, LLMUsage


# Token estimation constants
# Average characters per token varies by model and language
# English: ~4 chars/token, other languages may differ
CHARS_PER_TOKEN_ESTIMATE = 4.0


@dataclass
class ModelPricing:
    """Pricing information for a specific LLM model.

    Attributes:
        input_cost_per_1k: Cost in USD per 1,000 input tokens
        output_cost_per_1k: Cost in USD per 1,000 output tokens
        context_window: Maximum context window size in tokens
    """

    input_cost_per_1k: Decimal
    output_cost_per_1k: Decimal
    context_window: int = 128000


# Pricing table for supported models (USD per 1,000 tokens)
# Prices as of February 2026 - update as needed
MODEL_PRICING: dict[str, ModelPricing] = {
    # OpenAI Models
    "gpt-4o": ModelPricing(
        input_cost_per_1k=Decimal("0.0025"),
        output_cost_per_1k=Decimal("0.01"),
        context_window=128000,
    ),
    "gpt-4o-mini": ModelPricing(
        input_cost_per_1k=Decimal("0.00015"),
        output_cost_per_1k=Decimal("0.0006"),
        context_window=128000,
    ),
    "gpt-4-turbo": ModelPricing(
        input_cost_per_1k=Decimal("0.01"),
        output_cost_per_1k=Decimal("0.03"),
        context_window=128000,
    ),
    "gpt-4": ModelPricing(
        input_cost_per_1k=Decimal("0.03"),
        output_cost_per_1k=Decimal("0.06"),
        context_window=8192,
    ),
    "gpt-3.5-turbo": ModelPricing(
        input_cost_per_1k=Decimal("0.0005"),
        output_cost_per_1k=Decimal("0.0015"),
        context_window=16385,
    ),
    # Anthropic Models
    "claude-3-5-sonnet-20241022": ModelPricing(
        input_cost_per_1k=Decimal("0.003"),
        output_cost_per_1k=Decimal("0.015"),
        context_window=200000,
    ),
    "claude-3-5-sonnet-latest": ModelPricing(
        input_cost_per_1k=Decimal("0.003"),
        output_cost_per_1k=Decimal("0.015"),
        context_window=200000,
    ),
    "claude-3-opus-20240229": ModelPricing(
        input_cost_per_1k=Decimal("0.015"),
        output_cost_per_1k=Decimal("0.075"),
        context_window=200000,
    ),
    "claude-3-opus-latest": ModelPricing(
        input_cost_per_1k=Decimal("0.015"),
        output_cost_per_1k=Decimal("0.075"),
        context_window=200000,
    ),
    "claude-3-sonnet-20240229": ModelPricing(
        input_cost_per_1k=Decimal("0.003"),
        output_cost_per_1k=Decimal("0.015"),
        context_window=200000,
    ),
    "claude-3-haiku-20240307": ModelPricing(
        input_cost_per_1k=Decimal("0.00025"),
        output_cost_per_1k=Decimal("0.00125"),
        context_window=200000,
    ),
}

# Default pricing for unknown models
DEFAULT_PRICING = ModelPricing(
    input_cost_per_1k=Decimal("0.01"),
    output_cost_per_1k=Decimal("0.03"),
    context_window=8192,
)


@dataclass
class UsageStatistics:
    """Aggregated usage statistics for a time period.

    Attributes:
        total_requests: Total number of LLM requests
        successful_requests: Number of successful requests
        failed_requests: Number of failed requests
        total_prompt_tokens: Total prompt tokens used
        total_completion_tokens: Total completion tokens generated
        total_tokens: Total tokens (prompt + completion)
        total_cost_usd: Total estimated cost in USD
        average_latency_ms: Average response latency in milliseconds
        cache_hit_rate: Percentage of requests served from cache
    """

    total_requests: int = 0
    successful_requests: int = 0
    failed_requests: int = 0
    total_prompt_tokens: int = 0
    total_completion_tokens: int = 0
    total_tokens: int = 0
    total_cost_usd: Decimal = field(default_factory=lambda: Decimal("0"))
    average_latency_ms: Optional[float] = None
    cache_hit_rate: float = 0.0


class TokenTrackerError(Exception):
    """Base exception for token tracking errors."""

    pass


class TokenEstimationError(TokenTrackerError):
    """Raised when token estimation fails."""

    pass


class TokenTracker:
    """Service for tracking LLM token usage and estimating costs.

    This service provides methods for:
    - Estimating token counts from text
    - Calculating costs based on model pricing
    - Logging usage to the database
    - Retrieving usage statistics and analytics

    Attributes:
        db: Optional async database session for persistence
    """

    def __init__(self, db: Optional[AsyncSession] = None) -> None:
        """Initialize the token tracker.

        Args:
            db: Optional async database session for persistence operations
        """
        self.db = db

    def estimate_tokens(self, text: str) -> int:
        """Estimate the number of tokens in a text string.

        Uses a character-based heuristic for estimation. For more accurate
        counting, use tiktoken or provider-specific tokenizers.

        Args:
            text: The text to estimate tokens for

        Returns:
            Estimated number of tokens

        Raises:
            TokenEstimationError: If text is invalid
        """
        if text is None:
            raise TokenEstimationError("Cannot estimate tokens for None value")

        if not isinstance(text, str):
            raise TokenEstimationError(
                f"Expected string, got {type(text).__name__}"
            )

        # Handle empty string
        if not text:
            return 0

        # Character-based estimation
        # Add a small overhead for tokenization artifacts
        char_count = len(text)
        estimated = int(char_count / CHARS_PER_TOKEN_ESTIMATE) + 1

        return estimated

    def estimate_messages_tokens(self, messages: list[LLMMessage]) -> int:
        """Estimate tokens for a list of LLM messages.

        Accounts for message structure overhead in addition to content.

        Args:
            messages: List of LLM messages to estimate

        Returns:
            Estimated total tokens for all messages
        """
        if not messages:
            return 0

        total = 0
        for message in messages:
            # Content tokens
            content_tokens = self.estimate_tokens(message.content)

            # Add overhead for message structure (role, delimiters, etc.)
            # Typically 3-4 tokens per message
            message_overhead = 4

            total += content_tokens + message_overhead

            # Add tokens for name if present
            if message.name:
                total += self.estimate_tokens(message.name) + 1

        # Add tokens for conversation structure (special tokens)
        total += 3

        return total

    def get_model_pricing(self, model: str) -> ModelPricing:
        """Get pricing information for a model.

        Args:
            model: The model name

        Returns:
            ModelPricing for the model, or default if not found
        """
        # Try exact match first
        if model in MODEL_PRICING:
            return MODEL_PRICING[model]

        # Try prefix matching for versioned models
        for model_name, pricing in MODEL_PRICING.items():
            if model.startswith(model_name.split("-20")[0]):
                return pricing

        return DEFAULT_PRICING

    def calculate_cost(
        self,
        prompt_tokens: int,
        completion_tokens: int,
        model: str,
    ) -> Decimal:
        """Calculate the cost for a completion request.

        Args:
            prompt_tokens: Number of tokens in the prompt
            completion_tokens: Number of tokens in the completion
            model: The model used for the completion

        Returns:
            Estimated cost in USD as a Decimal
        """
        pricing = self.get_model_pricing(model)

        # Calculate costs (pricing is per 1,000 tokens)
        input_cost = (Decimal(prompt_tokens) / 1000) * pricing.input_cost_per_1k
        output_cost = (
            Decimal(completion_tokens) / 1000
        ) * pricing.output_cost_per_1k

        return input_cost + output_cost

    def calculate_response_cost(self, response: LLMResponse) -> Decimal:
        """Calculate the cost for an LLM response.

        Args:
            response: The LLM response containing usage information

        Returns:
            Estimated cost in USD
        """
        return self.calculate_cost(
            prompt_tokens=response.usage.prompt_tokens,
            completion_tokens=response.usage.completion_tokens,
            model=response.model,
        )

    def get_context_window(self, model: str) -> int:
        """Get the maximum context window size for a model.

        Args:
            model: The model name

        Returns:
            Maximum number of tokens in the context window
        """
        pricing = self.get_model_pricing(model)
        return pricing.context_window

    def check_context_limit(
        self,
        messages: list[LLMMessage],
        model: str,
        max_completion_tokens: int = 4096,
    ) -> tuple[bool, int]:
        """Check if messages fit within the model's context window.

        Args:
            messages: List of messages to check
            model: The model to check against
            max_completion_tokens: Reserved tokens for the completion

        Returns:
            Tuple of (is_within_limit, remaining_tokens)
        """
        estimated_tokens = self.estimate_messages_tokens(messages)
        context_window = self.get_context_window(model)
        available_tokens = context_window - max_completion_tokens
        remaining = available_tokens - estimated_tokens

        return remaining >= 0, remaining

    async def log_usage(
        self,
        response: LLMResponse,
        user_id: Optional[UUID] = None,
        session_id: Optional[UUID] = None,
        request_type: str = "completion",
        latency_ms: Optional[int] = None,
        cached: bool = False,
    ) -> LLMUsageLog:
        """Log usage information to the database.

        Args:
            response: The LLM response to log
            user_id: Optional user ID
            session_id: Optional session ID
            request_type: Type of request (completion, chat, etc.)
            latency_ms: Response latency in milliseconds
            cached: Whether the response was served from cache

        Returns:
            The created usage log entry

        Raises:
            TokenTrackerError: If database session is not available
        """
        if self.db is None:
            raise TokenTrackerError("Database session required for logging usage")

        cost = self.calculate_response_cost(response)

        usage_log = LLMUsageLog(
            user_id=user_id,
            session_id=session_id,
            provider=response.provider,
            model=response.model,
            prompt_tokens=response.usage.prompt_tokens,
            completion_tokens=response.usage.completion_tokens,
            total_tokens=response.usage.total_tokens,
            cost_usd=float(cost),
            request_type=request_type,
            success=True,
            latency_ms=latency_ms,
            cached=cached,
        )

        self.db.add(usage_log)
        await self.db.flush()

        return usage_log

    async def log_error(
        self,
        provider: str,
        model: str,
        error_message: str,
        user_id: Optional[UUID] = None,
        session_id: Optional[UUID] = None,
        request_type: str = "completion",
        latency_ms: Optional[int] = None,
    ) -> LLMUsageLog:
        """Log a failed LLM request to the database.

        Args:
            provider: The LLM provider
            model: The model used
            error_message: Description of the error
            user_id: Optional user ID
            session_id: Optional session ID
            request_type: Type of request
            latency_ms: Response latency in milliseconds

        Returns:
            The created usage log entry

        Raises:
            TokenTrackerError: If database session is not available
        """
        if self.db is None:
            raise TokenTrackerError("Database session required for logging errors")

        usage_log = LLMUsageLog(
            user_id=user_id,
            session_id=session_id,
            provider=provider,
            model=model,
            prompt_tokens=0,
            completion_tokens=0,
            total_tokens=0,
            cost_usd=0.0,
            request_type=request_type,
            success=False,
            error_message=error_message,
            latency_ms=latency_ms,
            cached=False,
        )

        self.db.add(usage_log)
        await self.db.flush()

        return usage_log

    async def get_usage_statistics(
        self,
        user_id: Optional[UUID] = None,
        provider: Optional[str] = None,
        model: Optional[str] = None,
        start_date: Optional[datetime] = None,
        end_date: Optional[datetime] = None,
    ) -> UsageStatistics:
        """Get aggregated usage statistics.

        Args:
            user_id: Filter by user ID
            provider: Filter by provider
            model: Filter by model
            start_date: Start of date range
            end_date: End of date range

        Returns:
            Aggregated usage statistics

        Raises:
            TokenTrackerError: If database session is not available
        """
        if self.db is None:
            raise TokenTrackerError(
                "Database session required for retrieving statistics"
            )

        # Build query with filters
        query = select(
            func.count(LLMUsageLog.id).label("total_requests"),
            func.sum(
                func.cast(LLMUsageLog.success, Integer)
            ).label("successful_requests"),
            func.sum(LLMUsageLog.prompt_tokens).label("total_prompt_tokens"),
            func.sum(LLMUsageLog.completion_tokens).label("total_completion_tokens"),
            func.sum(LLMUsageLog.total_tokens).label("total_tokens"),
            func.sum(LLMUsageLog.cost_usd).label("total_cost_usd"),
            func.avg(LLMUsageLog.latency_ms).label("average_latency_ms"),
            func.sum(
                func.cast(LLMUsageLog.cached, Integer)
            ).label("cached_requests"),
        )

        if user_id is not None:
            query = query.where(LLMUsageLog.user_id == user_id)
        if provider is not None:
            query = query.where(LLMUsageLog.provider == provider)
        if model is not None:
            query = query.where(LLMUsageLog.model == model)
        if start_date is not None:
            query = query.where(LLMUsageLog.created_at >= start_date)
        if end_date is not None:
            query = query.where(LLMUsageLog.created_at <= end_date)

        result = await self.db.execute(query)
        row = result.one()

        total_requests = row.total_requests or 0
        successful_requests = row.successful_requests or 0
        cached_requests = row.cached_requests or 0

        cache_hit_rate = (
            (cached_requests / total_requests * 100) if total_requests > 0 else 0.0
        )

        return UsageStatistics(
            total_requests=total_requests,
            successful_requests=successful_requests,
            failed_requests=total_requests - successful_requests,
            total_prompt_tokens=row.total_prompt_tokens or 0,
            total_completion_tokens=row.total_completion_tokens or 0,
            total_tokens=row.total_tokens or 0,
            total_cost_usd=Decimal(str(row.total_cost_usd or 0)),
            average_latency_ms=row.average_latency_ms,
            cache_hit_rate=cache_hit_rate,
        )

    async def get_daily_usage(
        self,
        days: int = 30,
        user_id: Optional[UUID] = None,
        provider: Optional[str] = None,
    ) -> list[dict]:
        """Get daily usage breakdown for a time period.

        Args:
            days: Number of days to look back
            user_id: Filter by user ID
            provider: Filter by provider

        Returns:
            List of daily usage dictionaries

        Raises:
            TokenTrackerError: If database session is not available
        """
        if self.db is None:
            raise TokenTrackerError(
                "Database session required for retrieving daily usage"
            )

        start_date = datetime.utcnow() - timedelta(days=days)

        # Build query with date grouping
        query = (
            select(
                func.date_trunc("day", LLMUsageLog.created_at).label("date"),
                func.count(LLMUsageLog.id).label("requests"),
                func.sum(LLMUsageLog.total_tokens).label("tokens"),
                func.sum(LLMUsageLog.cost_usd).label("cost_usd"),
            )
            .where(LLMUsageLog.created_at >= start_date)
            .group_by(func.date_trunc("day", LLMUsageLog.created_at))
            .order_by(func.date_trunc("day", LLMUsageLog.created_at))
        )

        if user_id is not None:
            query = query.where(LLMUsageLog.user_id == user_id)
        if provider is not None:
            query = query.where(LLMUsageLog.provider == provider)

        result = await self.db.execute(query)
        rows = result.all()

        return [
            {
                "date": row.date,
                "requests": row.requests,
                "tokens": row.tokens or 0,
                "cost_usd": Decimal(str(row.cost_usd or 0)),
            }
            for row in rows
        ]

    def create_usage_from_response(self, response: LLMResponse) -> LLMUsage:
        """Create an LLMUsage object from an LLM response.

        Useful for standardizing usage tracking across different providers.

        Args:
            response: The LLM response

        Returns:
            LLMUsage object with token counts
        """
        return LLMUsage(
            prompt_tokens=response.usage.prompt_tokens,
            completion_tokens=response.usage.completion_tokens,
            total_tokens=response.usage.total_tokens,
        )

    def format_cost(self, cost: Decimal) -> str:
        """Format a cost value as a human-readable string.

        Args:
            cost: Cost in USD

        Returns:
            Formatted cost string (e.g., "$0.0123")
        """
        return f"${cost:.4f}"

    def format_tokens(self, tokens: int) -> str:
        """Format token count as a human-readable string.

        Args:
            tokens: Token count

        Returns:
            Formatted token string (e.g., "1.2K tokens")
        """
        if tokens >= 1_000_000:
            return f"{tokens / 1_000_000:.1f}M tokens"
        elif tokens >= 1_000:
            return f"{tokens / 1_000:.1f}K tokens"
        else:
            return f"{tokens} tokens"


# Import Integer type for SQL cast operations
from sqlalchemy import Integer
