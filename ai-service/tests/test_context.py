"""Unit tests for request context management.

Tests for request/correlation ID storage and retrieval using context variables.
"""

from __future__ import annotations

import pytest

from app.core.context import (
    clear_context,
    get_correlation_id,
    get_request_id,
    set_correlation_id,
    set_request_id,
)


@pytest.fixture(autouse=True)
def reset_context() -> None:
    """Reset context before each test.

    This ensures tests don't interfere with each other.
    """
    clear_context()


def test_set_and_get_request_id() -> None:
    """Test setting and getting request ID from context."""
    test_request_id = "12345678-1234-5678-1234-567812345678"

    # Initially should be None
    assert get_request_id() is None

    # Set and retrieve
    set_request_id(test_request_id)
    assert get_request_id() == test_request_id


def test_set_and_get_correlation_id() -> None:
    """Test setting and getting correlation ID from context."""
    test_correlation_id = "87654321-4321-8765-4321-876543218765"

    # Initially should be None
    assert get_correlation_id() is None

    # Set and retrieve
    set_correlation_id(test_correlation_id)
    assert get_correlation_id() == test_correlation_id


def test_clear_context() -> None:
    """Test clearing all context variables."""
    # Set both IDs
    set_request_id("request-123")
    set_correlation_id("correlation-456")

    # Verify they're set
    assert get_request_id() == "request-123"
    assert get_correlation_id() == "correlation-456"

    # Clear context
    clear_context()

    # Should be None now
    assert get_request_id() is None
    assert get_correlation_id() is None


def test_context_independence() -> None:
    """Test that different context values are independent."""
    set_request_id("request-123")
    set_correlation_id("correlation-456")

    # Both should maintain their own values
    assert get_request_id() == "request-123"
    assert get_correlation_id() == "correlation-456"

    # Changing one shouldn't affect the other
    set_request_id("new-request-789")
    assert get_request_id() == "new-request-789"
    assert get_correlation_id() == "correlation-456"


def test_overwrite_values() -> None:
    """Test that setting a new value overwrites the old one."""
    # Set initial values
    set_request_id("old-request")
    set_correlation_id("old-correlation")

    # Verify initial values
    assert get_request_id() == "old-request"
    assert get_correlation_id() == "old-correlation"

    # Set new values
    set_request_id("new-request")
    set_correlation_id("new-correlation")

    # Verify values were overwritten
    assert get_request_id() == "new-request"
    assert get_correlation_id() == "new-correlation"


@pytest.mark.asyncio
async def test_context_in_async_functions() -> None:
    """Test that context variables work properly in async functions."""

    async def set_ids() -> None:
        """Helper function to set IDs in async context."""
        set_request_id("async-request-123")
        set_correlation_id("async-correlation-456")

    async def get_ids() -> tuple[str | None, str | None]:
        """Helper function to get IDs in async context."""
        return get_request_id(), get_correlation_id()

    # Set IDs in async function
    await set_ids()

    # Retrieve IDs in async function
    request_id, correlation_id = await get_ids()

    # Should maintain values across async calls
    assert request_id == "async-request-123"
    assert correlation_id == "async-correlation-456"
