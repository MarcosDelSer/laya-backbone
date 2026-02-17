"""Unified LLM client service for LAYA AI Service.

Provides a high-level interface for LLM completions that combines caching,
fallback strategies, token tracking, and provider management. This is the
primary interface for services to interact with LLM providers.
"""

import logging
import time
from typing import AsyncIterator, Callable, Optional
from uuid import UUID

from sqlalchemy.ext.asyncio import AsyncSession

from app.config import settings
from app.llm.base import BaseLLMProvider
from app.llm.cache import LLMCache
from app.llm.exceptions import (
    LLMError,
    LLMProviderError,
)
from app.llm.factory import LLMProviderFactory
from app.llm.fallback import (
    FallbackConfig,
    FallbackMode,
    FallbackResult,
    FallbackStrategy,
    RetryableError,
)
from app.llm.token_tracker import TokenTracker, UsageStatistics
from app.llm.types import LLMConfig, LLMMessage, LLMResponse, LLMUsage

logger = logging.getLogger(__name__)


class LLMClientError(Exception):
    """Base exception for LLM client errors."""

    def __init__(
        self,
        message: str,
        original_error: Optional[Exception] = None,
    ) -> None:
        """Initialize the client error.

        Args:
            message: Human-readable error message
            original_error: The underlying exception that caused this error
        """
        self.message = message
        self.original_error = original_error
        super().__init__(message)


class NoProvidersAvailableError(LLMClientError):
    """Raised when no LLM providers are available or configured."""

    pass


class CompletionFailedError(LLMClientError):
    """Raised when all completion attempts fail."""

    pass


class LLMClient:
    """Unified client for LLM completions with caching and fallback support.

    This is the primary interface for interacting with LLM providers in the
    LAYA AI Service. It provides:

    - Automatic provider selection and fallback
    - Response caching with configurable TTL
    - Token usage tracking and cost estimation
    - Configurable retry policies
    - Detailed completion statistics

    Attributes:
        db: Optional async database session for persistence
        factory: Provider factory for creating LLM provider instances
        cache: Cache service for storing responses
        tracker: Token tracker for usage monitoring
        fallback: Fallback strategy for provider failover
        enable_caching: Whether caching is enabled
        enable_tracking: Whether usage tracking is enabled
        enable_fallback: Whether fallback is enabled

    Example:
        from app.llm.client import LLMClient
        from app.llm.types import LLMMessage, LLMRole

        # Create client
        client = LLMClient(db=session)

        # Simple completion
        messages = [LLMMessage(role=LLMRole.USER, content="Hello!")]
        response = await client.complete(messages)
        print(response.content)

        # Completion with custom config
        config = LLMConfig(model="gpt-4o", temperature=0.5)
        response = await client.complete(messages, config=config)

        # Completion with caching disabled
        response = await client.complete(messages, use_cache=False)
    """

    def __init__(
        self,
        db: Optional[AsyncSession] = None,
        factory: Optional[LLMProviderFactory] = None,
        enable_caching: bool = True,
        enable_tracking: bool = True,
        enable_fallback: bool = True,
        cache_ttl_seconds: int = 3600,
        fallback_config: Optional[FallbackConfig] = None,
        on_fallback: Optional[Callable] = None,
    ) -> None:
        """Initialize the LLM client.

        Args:
            db: Optional async database session for persistence operations
            factory: Optional provider factory. Creates default if not provided.
            enable_caching: Whether to enable response caching
            enable_tracking: Whether to enable usage tracking
            enable_fallback: Whether to enable provider fallback
            cache_ttl_seconds: Default TTL for cached responses in seconds
            fallback_config: Optional custom fallback configuration
            on_fallback: Optional callback invoked when fallback occurs
        """
        self.db = db
        self.factory = factory or LLMProviderFactory()
        self.enable_caching = enable_caching
        self.enable_tracking = enable_tracking
        self.enable_fallback = enable_fallback

        # Initialize cache service
        self.cache = LLMCache(db=db, default_ttl=cache_ttl_seconds)

        # Initialize token tracker
        self.tracker = TokenTracker(db=db)

        # Initialize fallback strategy with default config if not provided
        default_fallback_config = fallback_config or FallbackConfig(
            mode=FallbackMode.SEQUENTIAL,
            max_retries=2,
            retry_on=[RetryableError.ALL],
            log_failures=True,
        )
        self.fallback = FallbackStrategy(
            providers=self._get_available_providers(),
            config=default_fallback_config,
            on_fallback=on_fallback,
        )

        logger.info(
            f"LLMClient initialized with caching={enable_caching}, "
            f"tracking={enable_tracking}, fallback={enable_fallback}"
        )

    def _get_available_providers(self) -> list[BaseLLMProvider]:
        """Get list of available providers for fallback.

        Returns providers in order of preference based on configuration.

        Returns:
            List of available provider instances
        """
        providers = []

        # Try to get the default provider first
        try:
            default = self.factory.get_provider()
            if default.is_available():
                providers.append(default)
        except LLMProviderError:
            pass

        # Add other available providers
        for name in self.factory.available_providers:
            try:
                provider = self.factory.get_provider(name)
                if provider.is_available() and provider not in providers:
                    providers.append(provider)
            except LLMProviderError:
                continue

        return providers

    def _get_default_config(self) -> LLMConfig:
        """Get default LLM configuration from settings.

        Returns:
            Default LLMConfig based on application settings
        """
        return LLMConfig(
            model=settings.llm_default_model,
            temperature=settings.llm_temperature,
            max_tokens=settings.llm_max_tokens,
            timeout=settings.llm_timeout,
        )

    def _merge_config(self, config: Optional[LLMConfig]) -> LLMConfig:
        """Merge provided config with defaults.

        Args:
            config: Optional user-provided configuration

        Returns:
            Merged configuration with defaults filled in
        """
        defaults = self._get_default_config()

        if config is None:
            return defaults

        return LLMConfig(
            model=config.model or defaults.model,
            temperature=config.temperature,
            max_tokens=config.max_tokens,
            top_p=config.top_p,
            frequency_penalty=config.frequency_penalty,
            presence_penalty=config.presence_penalty,
            stop=config.stop,
            timeout=config.timeout or defaults.timeout,
        )

    async def complete(
        self,
        messages: list[LLMMessage],
        config: Optional[LLMConfig] = None,
        provider_name: Optional[str] = None,
        use_cache: Optional[bool] = None,
        use_fallback: Optional[bool] = None,
        user_id: Optional[UUID] = None,
        session_id: Optional[UUID] = None,
        cache_ttl: Optional[int] = None,
    ) -> LLMResponse:
        """Generate an LLM completion with full feature support.

        This is the primary method for generating LLM completions. It
        automatically handles caching, fallback, and usage tracking based
        on the client configuration and provided options.

        Args:
            messages: List of messages forming the conversation
            config: Optional LLM configuration for this request
            provider_name: Optional specific provider to use
            use_cache: Override client's caching setting for this request
            use_fallback: Override client's fallback setting for this request
            user_id: Optional user ID for usage tracking
            session_id: Optional session ID for usage tracking
            cache_ttl: Optional custom TTL for caching this response

        Returns:
            LLMResponse containing the generated content and metadata

        Raises:
            NoProvidersAvailableError: If no providers are configured
            CompletionFailedError: If all completion attempts fail

        Example:
            messages = [
                LLMMessage(role=LLMRole.SYSTEM, content="You are helpful."),
                LLMMessage(role=LLMRole.USER, content="What is Python?"),
            ]
            response = await client.complete(messages)
            print(response.content)
        """
        merged_config = self._merge_config(config)
        should_cache = use_cache if use_cache is not None else self.enable_caching
        should_fallback = (
            use_fallback if use_fallback is not None else self.enable_fallback
        )

        # Get provider to determine model
        provider = self._resolve_provider(provider_name)
        if provider is None:
            raise NoProvidersAvailableError(
                "No LLM providers are available. "
                "Please configure at least one provider (OpenAI or Anthropic)."
            )

        model = merged_config.model or provider.default_model
        start_time = time.time()

        # Check cache first if enabled
        if should_cache:
            cached_response = await self._check_cache(
                messages=messages,
                provider=provider.name,
                model=model,
                config=merged_config,
            )
            if cached_response:
                logger.debug(
                    f"Cache hit for completion with provider={provider.name}, "
                    f"model={model}"
                )
                # Track cache hit
                if self.enable_tracking and self.db:
                    latency_ms = int((time.time() - start_time) * 1000)
                    await self.tracker.log_usage(
                        response=cached_response,
                        user_id=user_id,
                        session_id=session_id,
                        latency_ms=latency_ms,
                        cached=True,
                    )
                return cached_response

        # Execute completion
        response: Optional[LLMResponse] = None
        error: Optional[Exception] = None

        try:
            if should_fallback and len(self.fallback.providers) > 1:
                # Use fallback strategy
                result = await self._execute_with_fallback(
                    messages=messages,
                    config=merged_config,
                    provider_name=provider_name,
                )
                if result.response:
                    response = result.response
                elif result.all_failed:
                    error = CompletionFailedError(
                        f"All {result.total_attempts} provider attempts failed. "
                        f"Last error: {result.attempts[-1].error if result.attempts else 'Unknown'}"
                    )
            else:
                # Direct completion with single provider
                response = await provider.complete(messages, merged_config)

        except LLMError as e:
            error = e
            logger.error(f"LLM completion failed: {e}", exc_info=True)

            # Log error if tracking is enabled
            if self.enable_tracking and self.db:
                latency_ms = int((time.time() - start_time) * 1000)
                await self.tracker.log_error(
                    provider=provider.name,
                    model=model,
                    error_message=str(e),
                    user_id=user_id,
                    session_id=session_id,
                    latency_ms=latency_ms,
                )

        if error:
            raise CompletionFailedError(
                message=f"Completion failed: {error}",
                original_error=error,
            )

        if response is None:
            raise CompletionFailedError(
                message="Completion returned no response"
            )

        latency_ms = int((time.time() - start_time) * 1000)

        # Cache the response if enabled
        if should_cache:
            await self._store_in_cache(
                messages=messages,
                response=response,
                ttl_seconds=cache_ttl,
            )

        # Track usage if enabled
        if self.enable_tracking and self.db:
            await self.tracker.log_usage(
                response=response,
                user_id=user_id,
                session_id=session_id,
                latency_ms=latency_ms,
                cached=False,
            )

        logger.debug(
            f"Completion succeeded with provider={response.provider}, "
            f"model={response.model}, tokens={response.usage.total_tokens}, "
            f"latency={latency_ms}ms"
        )

        return response

    async def complete_stream(
        self,
        messages: list[LLMMessage],
        config: Optional[LLMConfig] = None,
        provider_name: Optional[str] = None,
    ) -> AsyncIterator[str]:
        """Generate a streaming LLM completion.

        Streams content chunks as they are generated. Note that streaming
        completions are not cached and do not support fallback (they use
        a single provider).

        Args:
            messages: List of messages forming the conversation
            config: Optional LLM configuration for this request
            provider_name: Optional specific provider to use

        Yields:
            String chunks of the generated content

        Raises:
            NoProvidersAvailableError: If no providers are configured
            LLMError: If the completion fails

        Example:
            async for chunk in client.complete_stream(messages):
                print(chunk, end="", flush=True)
        """
        merged_config = self._merge_config(config)
        provider = self._resolve_provider(provider_name)

        if provider is None:
            raise NoProvidersAvailableError(
                "No LLM providers are available for streaming."
            )

        logger.debug(
            f"Starting streaming completion with provider={provider.name}"
        )

        async for chunk in provider.complete_stream(messages, merged_config):
            yield chunk

    def _resolve_provider(
        self,
        provider_name: Optional[str] = None,
    ) -> Optional[BaseLLMProvider]:
        """Resolve a provider by name or get the default.

        Args:
            provider_name: Optional provider name to resolve

        Returns:
            Provider instance or None if not available
        """
        if provider_name:
            try:
                provider = self.factory.get_provider(provider_name)
                if provider.is_available():
                    return provider
            except LLMProviderError:
                pass
            return None

        # Get first available provider
        return self.factory.get_available_provider()

    async def _check_cache(
        self,
        messages: list[LLMMessage],
        provider: str,
        model: str,
        config: LLMConfig,
    ) -> Optional[LLMResponse]:
        """Check cache for an existing response.

        Args:
            messages: List of messages to check
            provider: Provider name
            model: Model name
            config: LLM configuration

        Returns:
            Cached response if found and valid, None otherwise
        """
        try:
            cache_key = self.cache.generate_cache_key(
                messages=messages,
                provider=provider,
                model=model,
                temperature=config.temperature,
                max_tokens=config.max_tokens,
            )
            return await self.cache.get(cache_key, provider=provider, model=model)
        except Exception as e:
            logger.warning(f"Cache lookup failed: {e}")
            return None

    async def _store_in_cache(
        self,
        messages: list[LLMMessage],
        response: LLMResponse,
        ttl_seconds: Optional[int] = None,
    ) -> None:
        """Store a response in the cache.

        Args:
            messages: Original messages
            response: Response to cache
            ttl_seconds: Optional TTL override
        """
        try:
            cache_key = self.cache.generate_cache_key(
                messages=messages,
                provider=response.provider,
                model=response.model,
            )
            await self.cache.set(
                cache_key=cache_key,
                response=response,
                messages=messages,
                ttl_seconds=ttl_seconds,
            )
            logger.debug(f"Response cached with key={cache_key[:16]}...")
        except Exception as e:
            logger.warning(f"Failed to cache response: {e}")

    async def _execute_with_fallback(
        self,
        messages: list[LLMMessage],
        config: LLMConfig,
        provider_name: Optional[str] = None,
    ) -> FallbackResult:
        """Execute completion with fallback strategy.

        Args:
            messages: List of messages
            config: LLM configuration
            provider_name: Optional preferred provider

        Returns:
            FallbackResult containing response or failure details
        """
        # Update fallback providers if needed
        self.fallback.set_providers(self._get_available_providers())

        # If a specific provider is requested, put it first
        if provider_name:
            try:
                preferred = self.factory.get_provider(provider_name)
                if preferred.is_available():
                    providers = [preferred] + [
                        p for p in self.fallback.providers if p.name != provider_name
                    ]
                    self.fallback.set_providers(providers)
            except LLMProviderError:
                pass

        return await self.fallback.execute(messages, config)

    def estimate_tokens(self, text: str) -> int:
        """Estimate the number of tokens in a text string.

        Args:
            text: Text to estimate tokens for

        Returns:
            Estimated token count
        """
        return self.tracker.estimate_tokens(text)

    def estimate_messages_tokens(self, messages: list[LLMMessage]) -> int:
        """Estimate tokens for a list of messages.

        Args:
            messages: Messages to estimate tokens for

        Returns:
            Estimated total token count
        """
        return self.tracker.estimate_messages_tokens(messages)

    def calculate_cost(
        self,
        prompt_tokens: int,
        completion_tokens: int,
        model: str,
    ) -> float:
        """Calculate the cost for a completion request.

        Args:
            prompt_tokens: Number of prompt tokens
            completion_tokens: Number of completion tokens
            model: Model used

        Returns:
            Estimated cost in USD
        """
        cost = self.tracker.calculate_cost(prompt_tokens, completion_tokens, model)
        return float(cost)

    def check_context_limit(
        self,
        messages: list[LLMMessage],
        model: Optional[str] = None,
        max_completion_tokens: int = 4096,
    ) -> tuple[bool, int]:
        """Check if messages fit within the model's context window.

        Args:
            messages: Messages to check
            model: Model to check against (uses default if not provided)
            max_completion_tokens: Reserved tokens for completion

        Returns:
            Tuple of (is_within_limit, remaining_tokens)
        """
        target_model = model or settings.llm_default_model
        return self.tracker.check_context_limit(
            messages=messages,
            model=target_model,
            max_completion_tokens=max_completion_tokens,
        )

    async def get_usage_statistics(
        self,
        user_id: Optional[UUID] = None,
        provider: Optional[str] = None,
        model: Optional[str] = None,
    ) -> UsageStatistics:
        """Get aggregated usage statistics.

        Args:
            user_id: Optional user ID filter
            provider: Optional provider filter
            model: Optional model filter

        Returns:
            Aggregated usage statistics

        Raises:
            LLMClientError: If database session is not available
        """
        if self.db is None:
            raise LLMClientError(
                "Database session required for retrieving statistics"
            )

        return await self.tracker.get_usage_statistics(
            user_id=user_id,
            provider=provider,
            model=model,
        )

    async def get_cache_stats(self) -> dict:
        """Get cache statistics for monitoring.

        Returns:
            Dictionary with cache statistics
        """
        return await self.cache.get_stats()

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
        return await self.cache.invalidate(
            cache_key=cache_key,
            provider=provider,
            model=model,
        )

    async def cleanup_expired_cache(self) -> int:
        """Remove expired cache entries.

        Returns:
            Number of entries removed
        """
        return await self.cache.cleanup_expired()

    @property
    def available_providers(self) -> list[str]:
        """Get list of registered provider names.

        Returns:
            List of provider names
        """
        return self.factory.available_providers

    @property
    def configured_providers(self) -> list[str]:
        """Get list of providers that are available and configured.

        Returns:
            List of available provider names
        """
        return self.factory.configured_providers

    def get_provider(self, name: Optional[str] = None) -> BaseLLMProvider:
        """Get a specific provider by name.

        Args:
            name: Provider name, or None for default

        Returns:
            Provider instance

        Raises:
            LLMProviderError: If provider is not found
        """
        return self.factory.get_provider(name)

    def __repr__(self) -> str:
        """Return string representation of the client.

        Returns:
            String representation including feature flags
        """
        return (
            f"<LLMClient caching={self.enable_caching} "
            f"tracking={self.enable_tracking} "
            f"fallback={self.enable_fallback} "
            f"providers={self.configured_providers}>"
        )
