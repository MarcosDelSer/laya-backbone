"""Service for LLM completions with database session support.

Provides a high-level service layer interface for LLM completions that
integrates with the FastAPI dependency injection system. This service wraps
the LLMClient to provide database-backed token tracking, caching, and
usage analytics.
"""

import logging
from datetime import datetime
from decimal import Decimal
from typing import AsyncIterator, Optional
from uuid import UUID

from sqlalchemy.ext.asyncio import AsyncSession

from app.config import settings
from app.llm import (
    CompletionFailedError,
    LLMClient,
    LLMClientError,
    LLMConfig,
    LLMMessage,
    LLMResponse,
    LLMRole,
    NoProvidersAvailableError,
)
from app.llm.cache import LLMCache
from app.llm.fallback import FallbackConfig, FallbackMode, RetryableError
from app.llm.token_tracker import TokenTracker, UsageStatistics
from app.schemas.llm import (
    LLMCompletionRequest,
    LLMCompletionResponse,
    LLMHealthResponse,
    LLMMessageSchema,
    LLMModelInfo,
    LLMModelsListResponse,
    LLMProvider,
    LLMUsageStats,
    LLMUsageSummary,
)

logger = logging.getLogger(__name__)


class LLMServiceError(Exception):
    """Base exception for LLM service errors."""

    def __init__(
        self,
        message: str,
        original_error: Optional[Exception] = None,
    ) -> None:
        """Initialize the service error.

        Args:
            message: Human-readable error message
            original_error: The underlying exception that caused this error
        """
        self.message = message
        self.original_error = original_error
        super().__init__(message)


class ProviderUnavailableError(LLMServiceError):
    """Raised when no LLM providers are available."""

    pass


class CompletionError(LLMServiceError):
    """Raised when a completion request fails."""

    pass


class LLMService:
    """Service for LLM completions with database session support.

    This service provides a high-level interface for LLM completions that
    integrates seamlessly with FastAPI's dependency injection. It wraps the
    LLMClient and provides:

    - Database-backed token usage tracking
    - Response caching with TTL
    - Provider fallback strategies
    - Usage analytics and reporting
    - Health monitoring

    Attributes:
        db: Async database session for database operations
        client: The underlying LLM client instance
        tracker: Token tracker for usage monitoring

    Example:
        # In a FastAPI route
        @router.post("/complete")
        async def complete(
            request: LLMCompletionRequest,
            service: LLMService = Depends(get_llm_service),
        ):
            response = await service.complete(request)
            return response
    """

    def __init__(
        self,
        db: AsyncSession,
        enable_caching: bool = True,
        enable_tracking: bool = True,
        enable_fallback: bool = True,
        cache_ttl_seconds: int = 3600,
    ) -> None:
        """Initialize the LLM service.

        Args:
            db: Async database session for persistence operations
            enable_caching: Whether to enable response caching
            enable_tracking: Whether to enable usage tracking
            enable_fallback: Whether to enable provider fallback
            cache_ttl_seconds: Default TTL for cached responses in seconds
        """
        self.db = db

        # Configure fallback strategy
        fallback_config = FallbackConfig(
            mode=FallbackMode.SEQUENTIAL,
            max_retries=2,
            retry_on=[RetryableError.ALL],
            log_failures=True,
        )

        # Initialize the LLM client with database session
        self.client = LLMClient(
            db=db,
            enable_caching=enable_caching,
            enable_tracking=enable_tracking,
            enable_fallback=enable_fallback,
            cache_ttl_seconds=cache_ttl_seconds,
            fallback_config=fallback_config,
        )

        # Initialize standalone token tracker for additional operations
        self.tracker = TokenTracker(db=db)

        logger.info(
            f"LLMService initialized with caching={enable_caching}, "
            f"tracking={enable_tracking}, fallback={enable_fallback}"
        )

    async def complete(
        self,
        request: LLMCompletionRequest,
        user_id: Optional[UUID] = None,
        session_id: Optional[UUID] = None,
    ) -> LLMCompletionResponse:
        """Generate an LLM completion from a request schema.

        Converts the API request schema to internal types, executes the
        completion, and returns a response schema suitable for API responses.

        Args:
            request: The completion request containing messages and parameters
            user_id: Optional user ID for usage tracking
            session_id: Optional session ID for usage tracking

        Returns:
            LLMCompletionResponse containing the generated content and metadata

        Raises:
            ProviderUnavailableError: If no providers are configured
            CompletionError: If the completion fails
        """
        # Convert request messages to LLM types
        messages = self._convert_messages(request.messages)

        # Build LLM config from request
        config = LLMConfig(
            model=request.model,
            temperature=request.temperature,
            max_tokens=request.max_tokens,
            top_p=request.top_p,
            frequency_penalty=request.frequency_penalty,
            presence_penalty=request.presence_penalty,
            stop=request.stop,
        )

        # Determine provider name
        provider_name = request.provider.value if request.provider else None

        start_time = datetime.utcnow()

        try:
            # Execute completion
            response = await self.client.complete(
                messages=messages,
                config=config,
                provider_name=provider_name,
                use_cache=request.use_cache,
                user_id=user_id,
                session_id=session_id,
            )

            # Calculate latency
            latency_ms = (datetime.utcnow() - start_time).total_seconds() * 1000

            # Convert to response schema
            return self._convert_response(response, latency_ms=latency_ms)

        except NoProvidersAvailableError as e:
            logger.error(f"No LLM providers available: {e}")
            raise ProviderUnavailableError(
                message="No LLM providers are currently available. "
                "Please check your API key configuration.",
                original_error=e,
            )
        except (CompletionFailedError, LLMClientError) as e:
            logger.error(f"LLM completion failed: {e}")
            raise CompletionError(
                message=f"Failed to generate completion: {e}",
                original_error=e,
            )

    async def complete_simple(
        self,
        prompt: str,
        system_prompt: Optional[str] = None,
        provider: Optional[str] = None,
        model: Optional[str] = None,
        temperature: Optional[float] = None,
        max_tokens: Optional[int] = None,
        user_id: Optional[UUID] = None,
        use_cache: bool = True,
    ) -> str:
        """Generate a simple LLM completion from a prompt string.

        Convenience method for simple use cases that don't require the full
        request schema. Automatically constructs the message list.

        Args:
            prompt: The user prompt to complete
            system_prompt: Optional system prompt for context
            provider: Optional provider name (openai, anthropic)
            model: Optional model name
            temperature: Optional temperature (0.0-2.0)
            max_tokens: Optional maximum tokens to generate
            user_id: Optional user ID for tracking
            use_cache: Whether to use response caching

        Returns:
            The generated text content

        Raises:
            ProviderUnavailableError: If no providers are configured
            CompletionError: If the completion fails
        """
        # Build messages
        messages: list[LLMMessage] = []

        if system_prompt:
            messages.append(LLMMessage(role=LLMRole.SYSTEM, content=system_prompt))

        messages.append(LLMMessage(role=LLMRole.USER, content=prompt))

        # Build config
        config = LLMConfig(
            model=model,
            temperature=temperature,
            max_tokens=max_tokens,
        )

        try:
            response = await self.client.complete(
                messages=messages,
                config=config,
                provider_name=provider,
                use_cache=use_cache,
                user_id=user_id,
            )
            return response.content

        except NoProvidersAvailableError as e:
            raise ProviderUnavailableError(
                message="No LLM providers available",
                original_error=e,
            )
        except (CompletionFailedError, LLMClientError) as e:
            raise CompletionError(
                message=f"Completion failed: {e}",
                original_error=e,
            )

    async def complete_stream(
        self,
        request: LLMCompletionRequest,
    ) -> AsyncIterator[str]:
        """Generate a streaming LLM completion.

        Streams content chunks as they are generated. Note that streaming
        completions are not cached.

        Args:
            request: The completion request

        Yields:
            String chunks of the generated content

        Raises:
            ProviderUnavailableError: If no providers are configured
        """
        messages = self._convert_messages(request.messages)
        config = LLMConfig(
            model=request.model,
            temperature=request.temperature,
            max_tokens=request.max_tokens,
            top_p=request.top_p,
        )
        provider_name = request.provider.value if request.provider else None

        try:
            async for chunk in self.client.complete_stream(
                messages=messages,
                config=config,
                provider_name=provider_name,
            ):
                yield chunk

        except NoProvidersAvailableError as e:
            raise ProviderUnavailableError(
                message="No LLM providers available for streaming",
                original_error=e,
            )

    async def get_health(self) -> LLMHealthResponse:
        """Get the health status of the LLM service.

        Returns health information about available providers and
        service components.

        Returns:
            LLMHealthResponse with provider status information
        """
        # Check each provider's availability
        providers: dict[str, bool] = {}

        for provider_name in self.client.available_providers:
            try:
                provider = self.client.get_provider(provider_name)
                providers[provider_name] = provider.is_available()
            except Exception:
                providers[provider_name] = False

        # Determine overall health status
        available_count = sum(1 for v in providers.values() if v)
        total_count = len(providers)

        if available_count == total_count and total_count > 0:
            status = "healthy"
        elif available_count > 0:
            status = "degraded"
        else:
            status = "unhealthy"

        # Check cache availability
        cache_available = self.client.enable_caching

        # Get default provider
        default_provider = settings.llm_default_provider

        return LLMHealthResponse(
            status=status,
            providers=providers,
            default_provider=default_provider,
            cache_available=cache_available,
        )

    async def get_models(self) -> LLMModelsListResponse:
        """Get list of available LLM models.

        Returns information about all available models from configured
        providers.

        Returns:
            LLMModelsListResponse with model information
        """
        models: list[LLMModelInfo] = []

        for provider_name in self.client.configured_providers:
            try:
                provider = self.client.get_provider(provider_name)
                provider_models = provider.get_model_list()

                for model_id in provider_models:
                    # Get pricing info if available
                    pricing = self.tracker.get_model_pricing(model_id)

                    model_info = LLMModelInfo(
                        id=model_id,
                        name=model_id,
                        provider=LLMProvider(provider_name),
                        context_window=pricing.context_window,
                        cost_per_1k_input=float(pricing.input_cost_per_1k),
                        cost_per_1k_output=float(pricing.output_cost_per_1k),
                    )
                    models.append(model_info)

            except Exception as e:
                logger.warning(
                    f"Failed to get models from provider {provider_name}: {e}"
                )

        return LLMModelsListResponse(models=models)

    async def get_usage_summary(
        self,
        user_id: Optional[UUID] = None,
        provider: Optional[str] = None,
        model: Optional[str] = None,
        start_date: Optional[datetime] = None,
        end_date: Optional[datetime] = None,
    ) -> LLMUsageSummary:
        """Get aggregated usage statistics.

        Retrieves usage statistics from the database for the specified
        filters.

        Args:
            user_id: Filter by user ID
            provider: Filter by provider name
            model: Filter by model name
            start_date: Start of date range
            end_date: End of date range

        Returns:
            LLMUsageSummary with aggregated statistics
        """
        try:
            stats = await self.tracker.get_usage_statistics(
                user_id=user_id,
                provider=provider,
                model=model,
                start_date=start_date,
                end_date=end_date,
            )

            return LLMUsageSummary(
                total_requests=stats.total_requests,
                successful_requests=stats.successful_requests,
                failed_requests=stats.failed_requests,
                total_tokens=stats.total_tokens,
                total_prompt_tokens=stats.total_prompt_tokens,
                total_completion_tokens=stats.total_completion_tokens,
                total_cost=float(stats.total_cost_usd),
                average_latency_ms=stats.average_latency_ms,
                period_start=start_date,
                period_end=end_date,
            )

        except Exception as e:
            logger.error(f"Failed to get usage statistics: {e}")
            # Return empty summary on error
            return LLMUsageSummary(
                total_requests=0,
                successful_requests=0,
                failed_requests=0,
                total_tokens=0,
                total_prompt_tokens=0,
                total_completion_tokens=0,
            )

    async def get_cache_stats(self) -> dict:
        """Get cache statistics for monitoring.

        Returns:
            Dictionary with cache statistics including hit rates
        """
        return await self.client.get_cache_stats()

    async def invalidate_cache(
        self,
        cache_key: Optional[str] = None,
        provider: Optional[str] = None,
        model: Optional[str] = None,
    ) -> int:
        """Invalidate cache entries matching criteria.

        Args:
            cache_key: Optional specific cache key
            provider: Optional provider filter
            model: Optional model filter

        Returns:
            Number of entries invalidated
        """
        return await self.client.invalidate_cache(
            cache_key=cache_key,
            provider=provider,
            model=model,
        )

    async def cleanup_expired_cache(self) -> int:
        """Remove expired cache entries.

        Returns:
            Number of entries removed
        """
        return await self.client.cleanup_expired_cache()

    def estimate_tokens(self, text: str) -> int:
        """Estimate the number of tokens in a text string.

        Args:
            text: Text to estimate tokens for

        Returns:
            Estimated token count
        """
        return self.client.estimate_tokens(text)

    def estimate_cost(
        self,
        prompt_tokens: int,
        completion_tokens: int,
        model: str,
    ) -> float:
        """Estimate the cost for a completion request.

        Args:
            prompt_tokens: Number of prompt tokens
            completion_tokens: Number of completion tokens
            model: Model name

        Returns:
            Estimated cost in USD
        """
        return self.client.calculate_cost(prompt_tokens, completion_tokens, model)

    def check_context_limit(
        self,
        messages: list[LLMMessageSchema],
        model: Optional[str] = None,
        max_completion_tokens: int = 4096,
    ) -> tuple[bool, int]:
        """Check if messages fit within the model's context window.

        Args:
            messages: Messages to check
            model: Model to check against
            max_completion_tokens: Reserved tokens for completion

        Returns:
            Tuple of (is_within_limit, remaining_tokens)
        """
        llm_messages = self._convert_messages(messages)
        return self.client.check_context_limit(
            messages=llm_messages,
            model=model,
            max_completion_tokens=max_completion_tokens,
        )

    def _convert_messages(
        self,
        messages: list[LLMMessageSchema],
    ) -> list[LLMMessage]:
        """Convert API message schemas to LLM message types.

        Args:
            messages: List of message schemas from the API

        Returns:
            List of LLMMessage objects for the client
        """
        return [
            LLMMessage(
                role=LLMRole(msg.role.value),
                content=msg.content,
                name=msg.name,
            )
            for msg in messages
        ]

    def _convert_response(
        self,
        response: LLMResponse,
        latency_ms: Optional[float] = None,
        cached: bool = False,
    ) -> LLMCompletionResponse:
        """Convert an LLM response to the API response schema.

        Args:
            response: The LLM response from the client
            latency_ms: Response latency in milliseconds
            cached: Whether the response was served from cache

        Returns:
            LLMCompletionResponse for API serialization
        """
        # Calculate cost
        cost = self.tracker.calculate_cost(
            prompt_tokens=response.usage.prompt_tokens,
            completion_tokens=response.usage.completion_tokens,
            model=response.model,
        )

        return LLMCompletionResponse(
            content=response.content,
            model=response.model,
            provider=LLMProvider(response.provider),
            usage=LLMUsageStats(
                prompt_tokens=response.usage.prompt_tokens,
                completion_tokens=response.usage.completion_tokens,
                total_tokens=response.usage.total_tokens,
                estimated_cost=float(cost),
            ),
            finish_reason=response.finish_reason,
            created_at=datetime.utcnow(),
            request_id=response.request_id,
            cached=cached,
            latency_ms=latency_ms,
        )

    @property
    def available_providers(self) -> list[str]:
        """Get list of registered provider names.

        Returns:
            List of provider names
        """
        return self.client.available_providers

    @property
    def configured_providers(self) -> list[str]:
        """Get list of available and configured providers.

        Returns:
            List of configured provider names
        """
        return self.client.configured_providers

    def __repr__(self) -> str:
        """Return string representation of the service.

        Returns:
            String representation
        """
        return (
            f"<LLMService providers={self.configured_providers} "
            f"caching={self.client.enable_caching}>"
        )
