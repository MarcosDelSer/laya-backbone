"""Fallback strategy service for LLM provider failover in LAYA AI Service.

Provides intelligent failover between LLM providers when the primary
provider fails. Supports configurable retry policies, provider ordering,
and error tracking for reliable LLM completions.
"""

import logging
from dataclasses import dataclass, field
from enum import Enum
from typing import Callable, Optional

from app.llm.base import BaseLLMProvider
from app.llm.exceptions import (
    LLMAuthenticationError,
    LLMError,
    LLMProviderError,
    LLMRateLimitError,
    LLMTimeoutError,
)
from app.llm.types import LLMConfig, LLMMessage, LLMResponse

logger = logging.getLogger(__name__)


class FallbackMode(str, Enum):
    """Fallback mode determining how providers are selected.

    Attributes:
        SEQUENTIAL: Try providers in order until one succeeds
        ROUND_ROBIN: Rotate through providers for load balancing
        PRIORITY: Always try highest priority provider first
    """

    SEQUENTIAL = "sequential"
    ROUND_ROBIN = "round_robin"
    PRIORITY = "priority"


class RetryableError(str, Enum):
    """Error types that should trigger a fallback to another provider.

    Attributes:
        RATE_LIMIT: Rate limit exceeded, try another provider
        TIMEOUT: Request timed out, try another provider
        PROVIDER_ERROR: Generic provider error, try another provider
        ALL: All error types trigger fallback
    """

    RATE_LIMIT = "rate_limit"
    TIMEOUT = "timeout"
    PROVIDER_ERROR = "provider_error"
    ALL = "all"


@dataclass
class FallbackConfig:
    """Configuration for fallback behavior.

    Attributes:
        mode: The fallback mode to use
        max_retries: Maximum number of providers to try before giving up
        retry_on: List of error types that should trigger fallback
        timeout_per_provider: Timeout in seconds for each provider attempt
        log_failures: Whether to log failed attempts
    """

    mode: FallbackMode = FallbackMode.SEQUENTIAL
    max_retries: int = 3
    retry_on: list[RetryableError] = field(
        default_factory=lambda: [RetryableError.ALL]
    )
    timeout_per_provider: int = 60
    log_failures: bool = True


@dataclass
class FallbackAttempt:
    """Record of a single fallback attempt.

    Attributes:
        provider_name: Name of the provider that was tried
        success: Whether the attempt was successful
        error: Error message if the attempt failed
        error_type: Type of error that occurred
        duration_ms: Duration of the attempt in milliseconds
    """

    provider_name: str
    success: bool
    error: Optional[str] = None
    error_type: Optional[str] = None
    duration_ms: Optional[float] = None


@dataclass
class FallbackResult:
    """Result of a fallback execution.

    Attributes:
        response: The LLM response if successful
        successful_provider: Name of the provider that succeeded
        attempts: List of all attempts made
        total_attempts: Total number of attempts made
        all_failed: Whether all attempts failed
    """

    response: Optional[LLMResponse] = None
    successful_provider: Optional[str] = None
    attempts: list[FallbackAttempt] = field(default_factory=list)
    total_attempts: int = 0
    all_failed: bool = False


class FallbackStrategy:
    """Service for executing LLM completions with provider failover.

    Provides a robust way to execute LLM completions that automatically
    falls back to alternative providers when the primary provider fails.
    Supports configurable retry policies, provider ordering, and detailed
    failure tracking.

    Attributes:
        providers: List of providers to use for failover
        config: Fallback configuration settings
        _current_index: Current provider index for round-robin mode
        _on_fallback: Optional callback when fallback occurs

    Example:
        from app.llm import LLMProviderFactory, LLMMessage, LLMRole
        from app.llm.fallback import FallbackStrategy, FallbackConfig

        # Create providers
        factory = LLMProviderFactory()
        providers = [
            factory.get_provider("openai"),
            factory.get_provider("anthropic"),
        ]

        # Create fallback strategy
        strategy = FallbackStrategy(providers=providers)

        # Execute with automatic failover
        messages = [LLMMessage(role=LLMRole.USER, content="Hello!")]
        result = await strategy.execute(messages)

        if result.response:
            print(f"Success with {result.successful_provider}")
        else:
            print(f"All {result.total_attempts} attempts failed")
    """

    def __init__(
        self,
        providers: Optional[list[BaseLLMProvider]] = None,
        config: Optional[FallbackConfig] = None,
        on_fallback: Optional[Callable[[FallbackAttempt], None]] = None,
    ) -> None:
        """Initialize the fallback strategy.

        Args:
            providers: List of providers to use for failover.
                       Order matters for sequential mode.
            config: Optional fallback configuration. Uses defaults if not provided.
            on_fallback: Optional callback invoked when fallback occurs
        """
        self.providers = providers or []
        self.config = config or FallbackConfig()
        self._current_index = 0
        self._on_fallback = on_fallback

    def add_provider(self, provider: BaseLLMProvider) -> None:
        """Add a provider to the fallback chain.

        Args:
            provider: The provider to add

        Raises:
            ValueError: If provider is None
        """
        if provider is None:
            raise ValueError("Provider cannot be None")

        self.providers.append(provider)

    def remove_provider(self, name: str) -> bool:
        """Remove a provider from the fallback chain by name.

        Args:
            name: Name of the provider to remove

        Returns:
            True if provider was removed, False if not found
        """
        for i, provider in enumerate(self.providers):
            if provider.name == name:
                self.providers.pop(i)
                return True
        return False

    def set_providers(self, providers: list[BaseLLMProvider]) -> None:
        """Set the list of providers for the fallback chain.

        Args:
            providers: List of providers to use
        """
        self.providers = providers
        self._current_index = 0

    def _should_retry_on_error(self, error: Exception) -> bool:
        """Determine if the error should trigger a fallback retry.

        Args:
            error: The exception that occurred

        Returns:
            True if fallback should be attempted, False otherwise
        """
        if RetryableError.ALL in self.config.retry_on:
            return isinstance(error, LLMError)

        if isinstance(error, LLMRateLimitError):
            return RetryableError.RATE_LIMIT in self.config.retry_on

        if isinstance(error, LLMTimeoutError):
            return RetryableError.TIMEOUT in self.config.retry_on

        if isinstance(error, LLMProviderError):
            return RetryableError.PROVIDER_ERROR in self.config.retry_on

        # Don't retry on authentication errors by default
        if isinstance(error, LLMAuthenticationError):
            return False

        return False

    def _get_error_type(self, error: Exception) -> str:
        """Get the error type string for an exception.

        Args:
            error: The exception

        Returns:
            String identifier for the error type
        """
        if isinstance(error, LLMRateLimitError):
            return "rate_limit"
        if isinstance(error, LLMTimeoutError):
            return "timeout"
        if isinstance(error, LLMAuthenticationError):
            return "authentication"
        if isinstance(error, LLMProviderError):
            return "provider_error"
        if isinstance(error, LLMError):
            return "llm_error"
        return "unknown"

    def _get_next_provider_index(self) -> int:
        """Get the next provider index based on the fallback mode.

        Returns:
            Index of the next provider to try
        """
        if self.config.mode == FallbackMode.ROUND_ROBIN:
            index = self._current_index
            self._current_index = (self._current_index + 1) % len(self.providers)
            return index

        # For sequential and priority modes, return 0 (first provider)
        return 0

    def _get_ordered_providers(self) -> list[BaseLLMProvider]:
        """Get providers in the order they should be tried.

        Returns:
            List of providers in execution order
        """
        if not self.providers:
            return []

        if self.config.mode == FallbackMode.ROUND_ROBIN:
            start = self._get_next_provider_index()
            return self.providers[start:] + self.providers[:start]

        # Sequential and priority modes use the original order
        return list(self.providers)

    async def execute(
        self,
        messages: list[LLMMessage],
        config: Optional[LLMConfig] = None,
    ) -> FallbackResult:
        """Execute an LLM completion with automatic failover.

        Attempts to complete the request using the configured providers,
        falling back to the next provider if the current one fails with
        a retryable error.

        Args:
            messages: List of messages forming the conversation
            config: Optional LLM configuration for the completion

        Returns:
            FallbackResult containing the response or failure details

        Example:
            messages = [LLMMessage(role=LLMRole.USER, content="Hello!")]
            result = await strategy.execute(messages)

            if result.response:
                print(result.response.content)
            else:
                for attempt in result.attempts:
                    print(f"{attempt.provider_name}: {attempt.error}")
        """
        import time

        result = FallbackResult()
        ordered_providers = self._get_ordered_providers()

        if not ordered_providers:
            result.all_failed = True
            logger.warning("No providers available for fallback execution")
            return result

        # Limit attempts to configured max_retries
        providers_to_try = ordered_providers[: self.config.max_retries]

        for provider in providers_to_try:
            attempt = FallbackAttempt(provider_name=provider.name, success=False)
            start_time = time.time()

            try:
                # Check if provider is available before attempting
                if not provider.is_available():
                    attempt.error = "Provider not available (not configured)"
                    attempt.error_type = "unavailable"
                    result.attempts.append(attempt)
                    result.total_attempts += 1

                    if self.config.log_failures:
                        logger.warning(
                            f"Provider {provider.name} is not available, skipping"
                        )
                    continue

                # Attempt the completion
                response = await provider.complete(messages, config)

                # Success!
                attempt.success = True
                attempt.duration_ms = (time.time() - start_time) * 1000
                result.attempts.append(attempt)
                result.total_attempts += 1
                result.response = response
                result.successful_provider = provider.name

                logger.info(
                    f"Fallback execution succeeded with provider {provider.name}"
                )
                return result

            except Exception as e:
                attempt.duration_ms = (time.time() - start_time) * 1000
                attempt.error = str(e)
                attempt.error_type = self._get_error_type(e)
                result.attempts.append(attempt)
                result.total_attempts += 1

                if self.config.log_failures:
                    logger.warning(
                        f"Provider {provider.name} failed: {e}",
                        exc_info=True,
                    )

                # Invoke callback if configured
                if self._on_fallback:
                    try:
                        self._on_fallback(attempt)
                    except Exception as callback_error:
                        logger.error(
                            f"Fallback callback error: {callback_error}",
                            exc_info=True,
                        )

                # Check if we should retry with next provider
                if not self._should_retry_on_error(e):
                    logger.info(
                        f"Error type {attempt.error_type} not configured for retry"
                    )
                    break

        # All attempts failed
        result.all_failed = True
        logger.error(
            f"All {result.total_attempts} fallback attempts failed. "
            f"Providers tried: {[a.provider_name for a in result.attempts]}"
        )
        return result

    async def execute_with_timeout(
        self,
        messages: list[LLMMessage],
        config: Optional[LLMConfig] = None,
        timeout: Optional[int] = None,
    ) -> FallbackResult:
        """Execute with a total timeout across all providers.

        Unlike the per-provider timeout in execute(), this enforces
        a total timeout for the entire fallback chain.

        Args:
            messages: List of messages forming the conversation
            config: Optional LLM configuration
            timeout: Total timeout in seconds for all attempts

        Returns:
            FallbackResult containing the response or failure details

        Raises:
            asyncio.TimeoutError: If total timeout is exceeded
        """
        import asyncio

        total_timeout = timeout or (
            self.config.timeout_per_provider * len(self.providers)
        )

        return await asyncio.wait_for(
            self.execute(messages, config),
            timeout=total_timeout,
        )

    @property
    def available_providers(self) -> list[str]:
        """Get list of provider names in the fallback chain.

        Returns:
            List of provider names
        """
        return [p.name for p in self.providers]

    @property
    def configured_providers(self) -> list[str]:
        """Get list of providers that are available and configured.

        Returns:
            List of available provider names
        """
        return [p.name for p in self.providers if p.is_available()]

    def __repr__(self) -> str:
        """Return string representation of the fallback strategy.

        Returns:
            String representation including mode and provider count
        """
        provider_names = [p.name for p in self.providers]
        return (
            f"<FallbackStrategy mode={self.config.mode.value} "
            f"providers={provider_names}>"
        )
