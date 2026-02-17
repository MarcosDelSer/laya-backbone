"""Unit tests for HTTP client with trace propagation.

Tests for automatic request/correlation ID propagation to downstream services.
"""

from __future__ import annotations

from unittest.mock import AsyncMock, patch

import pytest

from app.core.context import (
    clear_context,
    set_correlation_id,
    set_request_id,
)
from app.core.http_client import (
    TracedAsyncClient,
    get_trace_headers,
    make_traced_request,
)


@pytest.fixture(autouse=True)
def reset_context() -> None:
    """Reset context before each test."""
    clear_context()


def test_get_trace_headers_empty() -> None:
    """Test get_trace_headers when no IDs are set in context."""
    headers = get_trace_headers()

    # Should return empty dict when no context is set
    assert headers == {}


def test_get_trace_headers_with_request_id() -> None:
    """Test get_trace_headers with only request ID set."""
    test_request_id = "12345678-1234-5678-1234-567812345678"
    set_request_id(test_request_id)

    headers = get_trace_headers()

    assert "X-Request-ID" in headers
    assert headers["X-Request-ID"] == test_request_id
    assert "X-Correlation-ID" not in headers


def test_get_trace_headers_with_correlation_id() -> None:
    """Test get_trace_headers with only correlation ID set."""
    test_correlation_id = "87654321-4321-8765-4321-876543218765"
    set_correlation_id(test_correlation_id)

    headers = get_trace_headers()

    assert "X-Correlation-ID" in headers
    assert headers["X-Correlation-ID"] == test_correlation_id
    assert "X-Request-ID" not in headers


def test_get_trace_headers_with_both_ids() -> None:
    """Test get_trace_headers with both IDs set."""
    test_request_id = "12345678-1234-5678-1234-567812345678"
    test_correlation_id = "87654321-4321-8765-4321-876543218765"

    set_request_id(test_request_id)
    set_correlation_id(test_correlation_id)

    headers = get_trace_headers()

    assert headers["X-Request-ID"] == test_request_id
    assert headers["X-Correlation-ID"] == test_correlation_id


@pytest.mark.asyncio
async def test_traced_client_adds_headers() -> None:
    """Test that TracedAsyncClient automatically adds trace headers."""
    test_request_id = "12345678-1234-5678-1234-567812345678"
    test_correlation_id = "87654321-4321-8765-4321-876543218765"

    set_request_id(test_request_id)
    set_correlation_id(test_correlation_id)

    # Mock the parent request method
    with patch(
        "httpx.AsyncClient.request",
        new_callable=AsyncMock,
    ) as mock_request:
        # Configure mock to return a response
        mock_response = AsyncMock()
        mock_response.status_code = 200
        mock_request.return_value = mock_response

        async with TracedAsyncClient() as client:
            await client.get("http://example.com/api")

        # Verify that request was called with trace headers
        mock_request.assert_called_once()
        call_args = mock_request.call_args

        # Check that headers were included
        headers = call_args.kwargs.get("headers", {})
        assert headers["X-Request-ID"] == test_request_id
        assert headers["X-Correlation-ID"] == test_correlation_id


@pytest.mark.asyncio
async def test_traced_client_merges_headers() -> None:
    """Test that TracedAsyncClient merges trace headers with provided headers."""
    test_request_id = "12345678-1234-5678-1234-567812345678"
    test_correlation_id = "87654321-4321-8765-4321-876543218765"

    set_request_id(test_request_id)
    set_correlation_id(test_correlation_id)

    with patch(
        "httpx.AsyncClient.request",
        new_callable=AsyncMock,
    ) as mock_request:
        mock_response = AsyncMock()
        mock_response.status_code = 200
        mock_request.return_value = mock_response

        async with TracedAsyncClient() as client:
            # Provide custom headers
            custom_headers = {
                "Authorization": "Bearer token123",
                "Content-Type": "application/json",
            }
            await client.get("http://example.com/api", headers=custom_headers)

        # Verify headers were merged
        call_args = mock_request.call_args
        headers = call_args.kwargs.get("headers", {})

        # Should have both trace headers and custom headers
        assert headers["X-Request-ID"] == test_request_id
        assert headers["X-Correlation-ID"] == test_correlation_id
        assert headers["Authorization"] == "Bearer token123"
        assert headers["Content-Type"] == "application/json"


@pytest.mark.asyncio
async def test_traced_client_custom_headers_override() -> None:
    """Test that custom headers can override trace headers if needed."""
    test_request_id = "12345678-1234-5678-1234-567812345678"
    set_request_id(test_request_id)

    with patch(
        "httpx.AsyncClient.request",
        new_callable=AsyncMock,
    ) as mock_request:
        mock_response = AsyncMock()
        mock_response.status_code = 200
        mock_request.return_value = mock_response

        async with TracedAsyncClient() as client:
            # Provide custom request ID that should override context
            custom_headers = {"X-Request-ID": "custom-override-id"}
            await client.get("http://example.com/api", headers=custom_headers)

        # Custom header should take precedence
        call_args = mock_request.call_args
        headers = call_args.kwargs.get("headers", {})
        assert headers["X-Request-ID"] == "custom-override-id"


@pytest.mark.asyncio
async def test_traced_client_without_context() -> None:
    """Test TracedAsyncClient when no context is set."""
    # Don't set any context IDs

    with patch(
        "httpx.AsyncClient.request",
        new_callable=AsyncMock,
    ) as mock_request:
        mock_response = AsyncMock()
        mock_response.status_code = 200
        mock_request.return_value = mock_response

        async with TracedAsyncClient() as client:
            await client.get("http://example.com/api")

        # Should still make the request, just without trace headers
        call_args = mock_request.call_args
        headers = call_args.kwargs.get("headers", {})

        # Headers should be empty or not contain trace headers
        assert "X-Request-ID" not in headers
        assert "X-Correlation-ID" not in headers


@pytest.mark.asyncio
async def test_traced_client_post_request() -> None:
    """Test TracedAsyncClient with POST request."""
    test_request_id = "12345678-1234-5678-1234-567812345678"
    set_request_id(test_request_id)

    with patch(
        "httpx.AsyncClient.request",
        new_callable=AsyncMock,
    ) as mock_request:
        mock_response = AsyncMock()
        mock_response.status_code = 201
        mock_request.return_value = mock_response

        async with TracedAsyncClient() as client:
            await client.post(
                "http://example.com/api",
                json={"data": "test"},
            )

        # Verify method and headers
        call_args = mock_request.call_args
        assert call_args.args[0] == "POST"

        headers = call_args.kwargs.get("headers", {})
        assert headers["X-Request-ID"] == test_request_id


@pytest.mark.asyncio
async def test_make_traced_request() -> None:
    """Test convenience function make_traced_request."""
    test_request_id = "12345678-1234-5678-1234-567812345678"
    test_correlation_id = "87654321-4321-8765-4321-876543218765"

    set_request_id(test_request_id)
    set_correlation_id(test_correlation_id)

    with patch(
        "httpx.AsyncClient.request",
        new_callable=AsyncMock,
    ) as mock_request:
        mock_response = AsyncMock()
        mock_response.status_code = 200
        mock_request.return_value = mock_response

        response = await make_traced_request(
            "GET",
            "http://example.com/api",
        )

        # Verify response is returned
        assert response.status_code == 200

        # Verify trace headers were added
        call_args = mock_request.call_args
        headers = call_args.kwargs.get("headers", {})
        assert headers["X-Request-ID"] == test_request_id
        assert headers["X-Correlation-ID"] == test_correlation_id


@pytest.mark.asyncio
async def test_make_traced_request_with_params() -> None:
    """Test make_traced_request with additional parameters."""
    test_request_id = "12345678-1234-5678-1234-567812345678"
    set_request_id(test_request_id)

    with patch(
        "httpx.AsyncClient.request",
        new_callable=AsyncMock,
    ) as mock_request:
        mock_response = AsyncMock()
        mock_response.status_code = 200
        mock_request.return_value = mock_response

        await make_traced_request(
            "POST",
            "http://example.com/api",
            json={"key": "value"},
            timeout=30.0,
        )

        # Verify parameters were passed through
        call_args = mock_request.call_args
        assert call_args.kwargs.get("json") == {"key": "value"}
        assert call_args.kwargs.get("timeout") == 30.0


@pytest.mark.asyncio
async def test_traced_client_different_http_methods() -> None:
    """Test TracedAsyncClient with different HTTP methods."""
    test_request_id = "12345678-1234-5678-1234-567812345678"
    set_request_id(test_request_id)

    methods = ["GET", "POST", "PUT", "PATCH", "DELETE"]

    for method in methods:
        with patch(
            "httpx.AsyncClient.request",
            new_callable=AsyncMock,
        ) as mock_request:
            mock_response = AsyncMock()
            mock_response.status_code = 200
            mock_request.return_value = mock_response

            async with TracedAsyncClient() as client:
                await client.request(method, "http://example.com/api")

            # Verify method was passed correctly
            call_args = mock_request.call_args
            assert call_args.args[0] == method

            # Verify trace headers were added
            headers = call_args.kwargs.get("headers", {})
            assert headers["X-Request-ID"] == test_request_id
