"""Unit tests for error handler middleware.

Tests for request ID generation/propagation, exception handling,
and structured error responses with standardization.
"""

from __future__ import annotations

from typing import Any
from unittest.mock import AsyncMock, patch
from uuid import UUID

import pytest
from fastapi import FastAPI, HTTPException, Request, status
from fastapi.exceptions import RequestValidationError
from httpx import ASGITransport, AsyncClient
from pydantic import BaseModel, Field

from app.core.errors import (
    AuthenticationError,
    NotFoundError,
    StandardizedException,
    ValidationError,
)
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


# ============================================================================
# Error Response Standardization Tests
# ============================================================================


@pytest.fixture
def standardized_test_app() -> FastAPI:
    """Create a test FastAPI app with standardized error handling.

    Returns:
        FastAPI: Test application with middleware and test endpoints
    """
    from app.core.exception_handlers import register_exception_handlers

    app = FastAPI()

    # Register custom exception handlers
    register_exception_handlers(app)

    # Add middleware
    app.add_middleware(CorrelationMiddleware)
    app.add_middleware(ErrorHandlerMiddleware)

    # Test model for validation
    class TestModel(BaseModel):
        email: str = Field(..., description="Email address")
        age: int = Field(..., ge=0, le=150, description="Age")

    # Endpoint that raises StandardizedException
    @app.get("/test/custom-error")
    async def test_custom_error() -> None:
        """Test endpoint that raises custom exception."""
        raise NotFoundError("Resource not found", details="ID: 123")

    # Endpoint that raises ValidationError
    @app.get("/test/validation-error")
    async def test_validation_error() -> None:
        """Test endpoint that raises validation error."""
        raise ValidationError("Invalid data", details="Email is required")

    # Endpoint that raises AuthenticationError
    @app.get("/test/auth-error")
    async def test_auth_error() -> None:
        """Test endpoint that raises authentication error."""
        raise AuthenticationError("Invalid token")

    # Endpoint with Pydantic validation
    @app.post("/test/validate")
    async def test_validate(data: TestModel) -> dict[str, Any]:
        """Test endpoint with Pydantic validation."""
        return {"email": data.email, "age": data.age}

    # Endpoint that raises HTTPException with different status codes
    @app.get("/test/http-401")
    async def test_http_401() -> None:
        """Test endpoint that raises 401 HTTPException."""
        raise HTTPException(status_code=401, detail="Unauthorized")

    @app.get("/test/http-403")
    async def test_http_403() -> None:
        """Test endpoint that raises 403 HTTPException."""
        raise HTTPException(status_code=403, detail="Forbidden")

    @app.get("/test/http-404")
    async def test_http_404() -> None:
        """Test endpoint that raises 404 HTTPException."""
        raise HTTPException(status_code=404, detail="Not found")

    @app.get("/test/http-429")
    async def test_http_429() -> None:
        """Test endpoint that raises 429 HTTPException."""
        raise HTTPException(status_code=429, detail="Too many requests")

    return app


@pytest.mark.asyncio
async def test_standardized_exception_handling(standardized_test_app: FastAPI) -> None:
    """Test handling of StandardizedException with proper error type."""
    async with AsyncClient(
        transport=ASGITransport(app=standardized_test_app), base_url="http://test"
    ) as client:
        response = await client.get("/test/custom-error")

    assert response.status_code == status.HTTP_404_NOT_FOUND

    data = response.json()
    assert "error" in data
    assert data["error"]["type"] == "not_found_error"
    assert data["error"]["message"] == "Resource not found"
    assert "request_id" in data["error"]
    assert "correlation_id" in data["error"]


@pytest.mark.asyncio
async def test_validation_exception_handling(standardized_test_app: FastAPI) -> None:
    """Test handling of ValidationError exception."""
    async with AsyncClient(
        transport=ASGITransport(app=standardized_test_app), base_url="http://test"
    ) as client:
        response = await client.get("/test/validation-error")

    assert response.status_code == status.HTTP_422_UNPROCESSABLE_ENTITY

    data = response.json()
    assert data["error"]["type"] == "validation_error"
    assert data["error"]["message"] == "Invalid data"


@pytest.mark.asyncio
async def test_authentication_exception_handling(standardized_test_app: FastAPI) -> None:
    """Test handling of AuthenticationError exception."""
    async with AsyncClient(
        transport=ASGITransport(app=standardized_test_app), base_url="http://test"
    ) as client:
        response = await client.get("/test/auth-error")

    assert response.status_code == status.HTTP_401_UNAUTHORIZED

    data = response.json()
    assert data["error"]["type"] == "authentication_error"
    assert data["error"]["message"] == "Invalid token"


@pytest.mark.asyncio
async def test_pydantic_validation_error_handling(standardized_test_app: FastAPI) -> None:
    """Test handling of Pydantic RequestValidationError."""
    async with AsyncClient(
        transport=ASGITransport(app=standardized_test_app), base_url="http://test"
    ) as client:
        # Send invalid data (missing required fields)
        response = await client.post("/test/validate", json={})

    assert response.status_code == status.HTTP_422_UNPROCESSABLE_ENTITY

    data = response.json()
    assert "error" in data
    assert data["error"]["type"] == "validation_error"
    assert "validation failed" in data["error"]["message"].lower()
    # Check that it mentions field validation error
    assert "field required" in data["error"]["message"].lower() or "data" in data["error"]["message"]


@pytest.mark.asyncio
async def test_http_401_mapped_to_authentication_error(
    standardized_test_app: FastAPI,
) -> None:
    """Test that 401 HTTPException is mapped to authentication_error type."""
    async with AsyncClient(
        transport=ASGITransport(app=standardized_test_app), base_url="http://test"
    ) as client:
        response = await client.get("/test/http-401")

    assert response.status_code == status.HTTP_401_UNAUTHORIZED

    data = response.json()
    assert data["error"]["type"] == "authentication_error"


@pytest.mark.asyncio
async def test_http_403_mapped_to_authorization_error(
    standardized_test_app: FastAPI,
) -> None:
    """Test that 403 HTTPException is mapped to authorization_error type."""
    async with AsyncClient(
        transport=ASGITransport(app=standardized_test_app), base_url="http://test"
    ) as client:
        response = await client.get("/test/http-403")

    assert response.status_code == status.HTTP_403_FORBIDDEN

    data = response.json()
    assert data["error"]["type"] == "authorization_error"


@pytest.mark.asyncio
async def test_http_404_mapped_to_not_found_error(
    standardized_test_app: FastAPI,
) -> None:
    """Test that 404 HTTPException is mapped to not_found_error type."""
    async with AsyncClient(
        transport=ASGITransport(app=standardized_test_app), base_url="http://test"
    ) as client:
        response = await client.get("/test/http-404")

    assert response.status_code == status.HTTP_404_NOT_FOUND

    data = response.json()
    assert data["error"]["type"] == "not_found_error"


@pytest.mark.asyncio
async def test_http_429_mapped_to_rate_limit_error(
    standardized_test_app: FastAPI,
) -> None:
    """Test that 429 HTTPException is mapped to rate_limit_error type."""
    async with AsyncClient(
        transport=ASGITransport(app=standardized_test_app), base_url="http://test"
    ) as client:
        response = await client.get("/test/http-429")

    assert response.status_code == status.HTTP_429_TOO_MANY_REQUESTS

    data = response.json()
    assert data["error"]["type"] == "rate_limit_error"


@pytest.mark.asyncio
async def test_error_details_in_development_mode(standardized_test_app: FastAPI) -> None:
    """Test that error details are included in development mode."""
    with patch.dict("os.environ", {"ENVIRONMENT": "development"}):
        async with AsyncClient(
            transport=ASGITransport(app=standardized_test_app), base_url="http://test"
        ) as client:
            response = await client.get("/test/custom-error")

    data = response.json()
    assert "details" in data["error"]
    assert data["error"]["details"] == "ID: 123"


@pytest.mark.asyncio
async def test_error_details_excluded_in_production_mode(
    standardized_test_app: FastAPI,
) -> None:
    """Test that error details are excluded in production mode."""
    with patch.dict("os.environ", {"ENVIRONMENT": "production"}):
        async with AsyncClient(
            transport=ASGITransport(app=standardized_test_app), base_url="http://test"
        ) as client:
            response = await client.get("/test/custom-error")

    data = response.json()
    # Details should not be present in production
    assert "details" not in data["error"] or data["error"].get("details") is None
