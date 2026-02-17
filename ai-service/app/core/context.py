"""Request context management for correlation and request ID tracking.

This module provides thread-safe context storage for request and correlation IDs
that can be accessed throughout the request lifecycle and propagated to
downstream services for distributed tracing.
"""

from contextvars import ContextVar
from typing import Optional

# Context variables for request/correlation tracking
# These are thread-safe and work properly with async/await
_request_id_var: ContextVar[Optional[str]] = ContextVar("request_id", default=None)
_correlation_id_var: ContextVar[Optional[str]] = ContextVar(
    "correlation_id", default=None
)


def set_request_id(request_id: str) -> None:
    """Set the request ID for the current request context.

    Args:
        request_id: The unique request ID to set
    """
    _request_id_var.set(request_id)


def get_request_id() -> Optional[str]:
    """Get the request ID from the current request context.

    Returns:
        Optional[str]: The request ID if available, None otherwise
    """
    return _request_id_var.get()


def set_correlation_id(correlation_id: str) -> None:
    """Set the correlation ID for the current request context.

    The correlation ID is used to track a logical transaction across
    multiple services and requests.

    Args:
        correlation_id: The correlation ID to set
    """
    _correlation_id_var.set(correlation_id)


def get_correlation_id() -> Optional[str]:
    """Get the correlation ID from the current request context.

    Returns:
        Optional[str]: The correlation ID if available, None otherwise
    """
    return _correlation_id_var.get()


def clear_context() -> None:
    """Clear all context variables.

    This is mainly useful for testing to ensure clean state between tests.
    """
    _request_id_var.set(None)
    _correlation_id_var.set(None)
