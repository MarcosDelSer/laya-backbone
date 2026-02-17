"""Unit tests for error handler middleware.

Tests for request ID generation/propagation, exception handling,
and structured error responses.
"""

from __future__ import annotations

from typing import Any
from unittest.mock import AsyncMock, patch
from uuid import UUID

import pytest
from fastapi import FastAPI, HTTPException, Request, status
from httpx import ASGITransport, AsyncClient

from app.middleware.correlation import CorrelationMiddleware
from app.middleware.error_handler import ErrorHandlerMiddleware


@pytest.fixture
def test_app() -> FastAPI:
    """Create a test FastAPI app with error handler middleware.

    Returns:
        FastAPI: Test application instance with middleware
    """
    app = FastAPI()

    # Add middleware in correct order (correlation first, then error handler)
    app.add_middleware(CorrelationMiddleware)
    app.add_middleware(ErrorHandlerMiddleware)

    # Test endpoint that succeeds
    @app.get("/test/success")
    async def test_success(request: Request) -> dict[str, Any]:
        """Test endpoint that returns success."""
        return {
            "message": "success",
            "request_id": request.state.request_id,
        }

    # Test endpoint that raises HTTPException
    @app.get("/test/http-error")
    async def test_http_error() -> None:
        """Test endpoint that raises HTTPException."""
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Resource not found",
        )

    # Test endpoint that raises generic exception
    @app.get("/test/generic-error")
    async def test_generic_error() -> None:
        """Test endpoint that raises generic exception."""
        raise ValueError("Something went wrong")

    return app


@pytest.mark.asyncio
async def test_request_id_generation(test_app: FastAPI) -> None:
    """Test that request ID is generated when not provided in headers.

    Args:
        test_app: Test FastAPI application
    """
    async with AsyncClient(
        transport=ASGITransport(app=test_app), base_url="http://test"
    ) as client:
        response = await client.get("/test/success")

        assert response.status_code == status.HTTP_200_OK

        # Check that request ID was generated and returned
        assert "X-Request-ID" in response.headers
        request_id = response.headers["X-Request-ID"]

        # Verify it's a valid UUID
        UUID(request_id)  # Will raise ValueError if not valid

        # Check that request ID is in response body
        data = response.json()
        assert data["request_id"] == request_id


@pytest.mark.asyncio
async def test_request_id_propagation(test_app: FastAPI) -> None:
    """Test that provided request ID is propagated through the request.

    Args:
        test_app: Test FastAPI application
    """
    test_request_id = "12345678-1234-5678-1234-567812345678"

    async with AsyncClient(
        transport=ASGITransport(app=test_app), base_url="http://test"
    ) as client:
        response = await client.get(
            "/test/success",
            headers={"X-Request-ID": test_request_id},
        )

        assert response.status_code == status.HTTP_200_OK

        # Check that the same request ID is returned
        assert response.headers["X-Request-ID"] == test_request_id

        # Check that request ID is in response body
        data = response.json()
        assert data["request_id"] == test_request_id


@pytest.mark.asyncio
async def test_http_exception_handling(test_app: FastAPI) -> None:
    """Test that HTTPExceptions are handled by FastAPI's default handler.

    HTTPExceptions are handled by FastAPI internally before reaching our middleware.
    Our middleware handles unexpected exceptions (like ValueError), not HTTPExceptions.

    Args:
        test_app: Test FastAPI application
    """
    test_request_id = "12345678-1234-5678-1234-567812345678"

    async with AsyncClient(
        transport=ASGITransport(app=test_app), base_url="http://test"
    ) as client:
        response = await client.get(
            "/test/http-error",
            headers={"X-Request-ID": test_request_id},
        )

        assert response.status_code == status.HTTP_404_NOT_FOUND

        # Check request ID and correlation ID in headers (added by CorrelationMiddleware)
        assert response.headers["X-Request-ID"] == test_request_id
        assert response.headers["X-Correlation-ID"] == test_request_id  # Same when not provided separately

        # HTTPException uses FastAPI's default format, not our custom error structure
        data = response.json()
        assert "detail" in data
        assert data["detail"] == "Resource not found"


@pytest.mark.asyncio
async def test_generic_exception_handling(test_app: FastAPI) -> None:
    """Test that generic exceptions are handled with structured error response.

    Args:
        test_app: Test FastAPI application
    """
    test_request_id = "12345678-1234-5678-1234-567812345678"

    async with AsyncClient(
        transport=ASGITransport(app=test_app), base_url="http://test"
    ) as client:
        response = await client.get(
            "/test/generic-error",
            headers={"X-Request-ID": test_request_id},
        )

        assert response.status_code == status.HTTP_500_INTERNAL_SERVER_ERROR

        # Check request ID and correlation ID in headers
        assert response.headers["X-Request-ID"] == test_request_id
        assert response.headers["X-Correlation-ID"] == test_request_id  # Same when not provided separately

        # Check structured error response
        data = response.json()
        assert "error" in data
        assert data["error"]["type"] == "internal_error"
        assert data["error"]["message"] == "An unexpected error occurred"
        assert data["error"]["request_id"] == test_request_id
        assert data["error"]["correlation_id"] == test_request_id  # Same as request_id when not provided

        # Should include details for 500 errors
        assert "details" in data["error"]


@pytest.mark.asyncio
async def test_request_id_in_request_state(test_app: FastAPI) -> None:
    """Test that request ID is accessible in request.state.

    Args:
        test_app: Test FastAPI application
    """
    async with AsyncClient(
        transport=ASGITransport(app=test_app), base_url="http://test"
    ) as client:
        response = await client.get("/test/success")

        assert response.status_code == status.HTTP_200_OK

        # The endpoint returns request_id from request.state
        data = response.json()
        assert "request_id" in data
        assert data["request_id"] == response.headers["X-Request-ID"]


@pytest.mark.asyncio
async def test_multiple_requests_different_ids(test_app: FastAPI) -> None:
    """Test that different requests get different request IDs.

    Args:
        test_app: Test FastAPI application
    """
    async with AsyncClient(
        transport=ASGITransport(app=test_app), base_url="http://test"
    ) as client:
        response1 = await client.get("/test/success")
        response2 = await client.get("/test/success")

        assert response1.status_code == status.HTTP_200_OK
        assert response2.status_code == status.HTTP_200_OK

        # Request IDs should be different
        request_id1 = response1.headers["X-Request-ID"]
        request_id2 = response2.headers["X-Request-ID"]

        assert request_id1 != request_id2
