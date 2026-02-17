"""LLM-specific exception classes for error handling.

Provides a hierarchy of exceptions for handling errors that may occur
when interacting with LLM providers. These exceptions enable proper
error handling and user-friendly error messages throughout the service.
"""

from typing import Optional


class LLMError(Exception):
    """Base exception for all LLM-related errors.

    All other LLM exceptions inherit from this class, allowing for
    broad exception handling when needed.

    Attributes:
        message: Human-readable error message
        provider: Name of the LLM provider that raised the error
        original_error: The underlying exception, if any
    """

    def __init__(
        self,
        message: str,
        provider: Optional[str] = None,
        original_error: Optional[Exception] = None,
    ) -> None:
        """Initialize the LLM error.

        Args:
            message: Human-readable error message
            provider: Name of the LLM provider (e.g., "openai", "anthropic")
            original_error: The underlying exception that caused this error
        """
        self.message = message
        self.provider = provider
        self.original_error = original_error
        super().__init__(self._format_message())

    def _format_message(self) -> str:
        """Format the error message with provider context.

        Returns:
            Formatted error message string
        """
        if self.provider:
            return f"[{self.provider}] {self.message}"
        return self.message


class LLMRateLimitError(LLMError):
    """Raised when LLM provider rate limits are exceeded.

    This error indicates that the request was rejected due to
    rate limiting. Callers should implement backoff and retry logic.

    Attributes:
        retry_after: Suggested wait time in seconds before retrying
    """

    def __init__(
        self,
        message: str = "Rate limit exceeded",
        provider: Optional[str] = None,
        original_error: Optional[Exception] = None,
        retry_after: Optional[float] = None,
    ) -> None:
        """Initialize the rate limit error.

        Args:
            message: Human-readable error message
            provider: Name of the LLM provider
            original_error: The underlying exception
            retry_after: Suggested wait time in seconds before retrying
        """
        self.retry_after = retry_after
        super().__init__(message, provider, original_error)


class LLMAuthenticationError(LLMError):
    """Raised when LLM provider authentication fails.

    This error indicates that API credentials are missing, invalid,
    or expired. Check the provider configuration and API keys.
    """

    def __init__(
        self,
        message: str = "Authentication failed",
        provider: Optional[str] = None,
        original_error: Optional[Exception] = None,
    ) -> None:
        """Initialize the authentication error.

        Args:
            message: Human-readable error message
            provider: Name of the LLM provider
            original_error: The underlying exception
        """
        super().__init__(message, provider, original_error)


class LLMProviderError(LLMError):
    """Raised for provider-specific errors not covered by other exceptions.

    This is a catch-all for errors from the LLM provider that don't
    fit into more specific categories like rate limits or authentication.

    Attributes:
        status_code: HTTP status code from the provider, if applicable
    """

    def __init__(
        self,
        message: str = "Provider error occurred",
        provider: Optional[str] = None,
        original_error: Optional[Exception] = None,
        status_code: Optional[int] = None,
    ) -> None:
        """Initialize the provider error.

        Args:
            message: Human-readable error message
            provider: Name of the LLM provider
            original_error: The underlying exception
            status_code: HTTP status code from the provider
        """
        self.status_code = status_code
        super().__init__(message, provider, original_error)


class LLMTimeoutError(LLMError):
    """Raised when an LLM request times out.

    This error indicates that the request took too long to complete.
    Consider increasing timeout settings or retrying with a simpler prompt.

    Attributes:
        timeout_seconds: The timeout duration that was exceeded
    """

    def __init__(
        self,
        message: str = "Request timed out",
        provider: Optional[str] = None,
        original_error: Optional[Exception] = None,
        timeout_seconds: Optional[float] = None,
    ) -> None:
        """Initialize the timeout error.

        Args:
            message: Human-readable error message
            provider: Name of the LLM provider
            original_error: The underlying exception
            timeout_seconds: The timeout duration that was exceeded
        """
        self.timeout_seconds = timeout_seconds
        super().__init__(message, provider, original_error)
