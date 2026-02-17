"""Unit tests for correlation ID middleware.

Tests for correlation ID generation, propagation, and distributed tracing.
"""

from __future__ import annotations

from typing import Any
from uuid import UUID

import pytest
from fastapi import FastAPI, Request
from httpx import ASGITransport, AsyncClient

from app.core.context import clear_context, get_correlation_id, get_request_id
from app.middleware.correlation import CorrelationMiddleware


@pytest.fixture(autouse=True)
def reset_context() -> None:
    """Reset context before each test."""
    clear_context()


@pytest.fixture
def test_app() -> FastAPI:
    """Create a test FastAPI app with correlation middleware.

    Returns:
        FastAPI: Test application instance with middleware
    """
    app = FastAPI()

    # Add correlation middleware
    app.add_middleware(CorrelationMiddleware)

    # Test endpoint that returns IDs from both request state and context
    @app.get("/test/ids")
    async def test_ids(request: Request) -> dict[str, Any]:
        """Test endpoint that returns request and correlation IDs."""
        return {
            "request_id_state": request.state.request_id,
            "correlation_id_state": request.state.correlation_id,
            "request_id_context": get_request_id(),
            "correlation_id_context": get_correlation_id(),
        }

    return app


@pytest.mark.asyncio
async def test_request_id_generation(test_app: FastAPI) -> None:
    """Test that request ID is generated when not provided."""
    async with AsyncClient(
        transport=ASGITransport(app=test_app), base_url="http://test"
    ) as client:
        response = await client.get("/test/ids")

        assert response.status_code == 200

        # Check headers
        assert "X-Request-ID" in response.headers
        assert "X-Correlation-ID" in response.headers

        # Verify it's a valid UUID
        request_id = response.headers["X-Request-ID"]
        UUID(request_id)  # Will raise ValueError if not valid

        # Check response data
        data = response.json()
        assert data["request_id_state"] == request_id
        assert data["request_id_context"] == request_id


@pytest.mark.asyncio
async def test_correlation_id_defaults_to_request_id(test_app: FastAPI) -> None:
    """Test that correlation ID defaults to request ID when not provided."""
    async with AsyncClient(
        transport=ASGITransport(app=test_app), base_url="http://test"
    ) as client:
        response = await client.get("/test/ids")

        assert response.status_code == 200

        # When no correlation ID is provided, it should match request ID
        request_id = response.headers["X-Request-ID"]
        correlation_id = response.headers["X-Correlation-ID"]

        assert request_id == correlation_id

        # Check response data
        data = response.json()
        assert data["correlation_id_state"] == request_id
        assert data["correlation_id_context"] == request_id


@pytest.mark.asyncio
async def test_request_id_propagation(test_app: FastAPI) -> None:
    """Test that provided request ID is propagated."""
    test_request_id = "12345678-1234-5678-1234-567812345678"

    async with AsyncClient(
        transport=ASGITransport(app=test_app), base_url="http://test"
    ) as client:
        response = await client.get(
            "/test/ids",
            headers={"X-Request-ID": test_request_id},
        )

        assert response.status_code == 200

        # Should use provided request ID
        assert response.headers["X-Request-ID"] == test_request_id

        # Check response data
        data = response.json()
        assert data["request_id_state"] == test_request_id
        assert data["request_id_context"] == test_request_id


@pytest.mark.asyncio
async def test_correlation_id_propagation(test_app: FastAPI) -> None:
    """Test that provided correlation ID is propagated."""
    test_request_id = "12345678-1234-5678-1234-567812345678"
    test_correlation_id = "87654321-4321-8765-4321-876543218765"

    async with AsyncClient(
        transport=ASGITransport(app=test_app), base_url="http://test"
    ) as client:
        response = await client.get(
            "/test/ids",
            headers={
                "X-Request-ID": test_request_id,
                "X-Correlation-ID": test_correlation_id,
            },
        )

        assert response.status_code == 200

        # Should use provided IDs
        assert response.headers["X-Request-ID"] == test_request_id
        assert response.headers["X-Correlation-ID"] == test_correlation_id

        # Check response data
        data = response.json()
        assert data["request_id_state"] == test_request_id
        assert data["correlation_id_state"] == test_correlation_id
        assert data["request_id_context"] == test_request_id
        assert data["correlation_id_context"] == test_correlation_id


@pytest.mark.asyncio
async def test_correlation_id_without_request_id(test_app: FastAPI) -> None:
    """Test correlation ID when request ID is auto-generated."""
    test_correlation_id = "87654321-4321-8765-4321-876543218765"

    async with AsyncClient(
        transport=ASGITransport(app=test_app), base_url="http://test"
    ) as client:
        response = await client.get(
            "/test/ids",
            headers={"X-Correlation-ID": test_correlation_id},
        )

        assert response.status_code == 200

        # Request ID should be auto-generated
        request_id = response.headers["X-Request-ID"]
        UUID(request_id)  # Verify it's a valid UUID

        # Correlation ID should be the provided one
        assert response.headers["X-Correlation-ID"] == test_correlation_id

        # IDs should be different
        assert request_id != test_correlation_id

        # Check response data
        data = response.json()
        assert data["request_id_state"] == request_id
        assert data["correlation_id_state"] == test_correlation_id
        assert data["request_id_context"] == request_id
        assert data["correlation_id_context"] == test_correlation_id


@pytest.mark.asyncio
async def test_multiple_requests_share_correlation_id(test_app: FastAPI) -> None:
    """Test that multiple requests can share the same correlation ID.

    This simulates a distributed transaction where multiple service calls
    are part of the same logical operation.
    """
    shared_correlation_id = "shared-correlation-12345"

    async with AsyncClient(
        transport=ASGITransport(app=test_app), base_url="http://test"
    ) as client:
        # First request
        response1 = await client.get(
            "/test/ids",
            headers={"X-Correlation-ID": shared_correlation_id},
        )

        # Second request with same correlation ID but different request ID
        response2 = await client.get(
            "/test/ids",
            headers={"X-Correlation-ID": shared_correlation_id},
        )

        # Both responses should have the same correlation ID
        assert response1.headers["X-Correlation-ID"] == shared_correlation_id
        assert response2.headers["X-Correlation-ID"] == shared_correlation_id

        # But different request IDs
        request_id1 = response1.headers["X-Request-ID"]
        request_id2 = response2.headers["X-Request-ID"]
        assert request_id1 != request_id2


@pytest.mark.asyncio
async def test_context_isolation_between_requests(test_app: FastAPI) -> None:
    """Test that context is properly isolated between different requests."""
    async with AsyncClient(
        transport=ASGITransport(app=test_app), base_url="http://test"
    ) as client:
        # First request with specific IDs
        response1 = await client.get(
            "/test/ids",
            headers={
                "X-Request-ID": "request-1",
                "X-Correlation-ID": "correlation-1",
            },
        )

        # Second request with different IDs
        response2 = await client.get(
            "/test/ids",
            headers={
                "X-Request-ID": "request-2",
                "X-Correlation-ID": "correlation-2",
            },
        )

        # Each response should have its own IDs
        data1 = response1.json()
        data2 = response2.json()

        assert data1["request_id_context"] == "request-1"
        assert data1["correlation_id_context"] == "correlation-1"

        assert data2["request_id_context"] == "request-2"
        assert data2["correlation_id_context"] == "correlation-2"


@pytest.mark.asyncio
async def test_ids_in_response_headers(test_app: FastAPI) -> None:
    """Test that both IDs are always included in response headers."""
    async with AsyncClient(
        transport=ASGITransport(app=test_app), base_url="http://test"
    ) as client:
        # Request without any headers
        response = await client.get("/test/ids")

        # Both headers should be present in response
        assert "X-Request-ID" in response.headers
        assert "X-Correlation-ID" in response.headers

        # Both should be valid UUIDs (auto-generated)
        UUID(response.headers["X-Request-ID"])
        UUID(response.headers["X-Correlation-ID"])
