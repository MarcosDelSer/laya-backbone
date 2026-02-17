"""Tests for standardized error responses.

Tests error response creation, formatting, and standardization
functionality.
"""

from __future__ import annotations

import json
from typing import Any
from unittest.mock import patch

import pytest
from fastapi import status
from fastapi.responses import JSONResponse

from app.core.error_responses import (
    authentication_error_response,
    authorization_error_response,
    create_error_response,
    database_error_response,
    format_validation_errors,
    get_validation_details,
    not_found_error_response,
    rate_limit_error_response,
    server_error_response,
    validation_error_response,
)
from app.core.errors import (
    AuthenticationError,
    AuthorizationError,
    DatabaseError,
    ErrorType,
    NotFoundError,
    RateLimitError,
    StandardizedException,
    ValidationError,
)


# ============================================================================
# Error Response Creation Tests
# ============================================================================


def test_create_error_response_basic() -> None:
    """Test basic error response creation."""
    with patch("app.core.error_responses.get_request_id", return_value="test-request-id"):
        with patch("app.core.error_responses.get_correlation_id", return_value="test-correlation-id"):
            response = create_error_response(
                error_type=ErrorType.VALIDATION_ERROR,
                message="Invalid input",
                status_code=status.HTTP_422_UNPROCESSABLE_ENTITY,
            )

    assert isinstance(response, JSONResponse)
    assert response.status_code == status.HTTP_422_UNPROCESSABLE_ENTITY
    assert response.headers["X-Request-ID"] == "test-request-id"
    assert response.headers["X-Correlation-ID"] == "test-correlation-id"

    body = json.loads(response.body)
    assert "error" in body
    assert body["error"]["type"] == "validation_error"
    assert body["error"]["message"] == "Invalid input"
    assert body["error"]["request_id"] == "test-request-id"
    assert body["error"]["correlation_id"] == "test-correlation-id"


def test_create_error_response_with_details_development() -> None:
    """Test error response with details in development mode."""
    with patch("app.core.error_responses.get_request_id", return_value="test-request-id"):
        with patch("app.core.error_responses.get_correlation_id", return_value="test-correlation-id"):
            with patch.dict("os.environ", {"ENVIRONMENT": "development"}):
                response = create_error_response(
                    error_type=ErrorType.SERVER_ERROR,
                    message="Server error",
                    status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
                    details="Detailed error information",
                )

    body = json.loads(response.body)
    assert body["error"]["details"] == "Detailed error information"


def test_create_error_response_no_details_production() -> None:
    """Test that details are excluded in production mode."""
    with patch("app.core.error_responses.get_request_id", return_value="test-request-id"):
        with patch("app.core.error_responses.get_correlation_id", return_value="test-correlation-id"):
            with patch.dict("os.environ", {"ENVIRONMENT": "production"}):
                response = create_error_response(
                    error_type=ErrorType.SERVER_ERROR,
                    message="Server error",
                    status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
                    details="Detailed error information",
                )

    body = json.loads(response.body)
    assert "details" not in body["error"]


def test_create_error_response_custom_ids() -> None:
    """Test error response with custom request and correlation IDs."""
    response = create_error_response(
        error_type=ErrorType.NOT_FOUND_ERROR,
        message="Not found",
        status_code=status.HTTP_404_NOT_FOUND,
        request_id="custom-request-id",
        correlation_id="custom-correlation-id",
    )

    assert response.headers["X-Request-ID"] == "custom-request-id"
    assert response.headers["X-Correlation-ID"] == "custom-correlation-id"

    body = json.loads(response.body)
    assert body["error"]["request_id"] == "custom-request-id"
    assert body["error"]["correlation_id"] == "custom-correlation-id"


# ============================================================================
# Specific Error Response Tests
# ============================================================================


def test_authentication_error_response() -> None:
    """Test authentication error response."""
    with patch("app.core.error_responses.get_request_id", return_value="test-id"):
        response = authentication_error_response("Invalid credentials")

    assert response.status_code == status.HTTP_401_UNAUTHORIZED
    body = json.loads(response.body)
    assert body["error"]["type"] == "authentication_error"
    assert body["error"]["message"] == "Invalid credentials"


def test_authorization_error_response() -> None:
    """Test authorization error response."""
    with patch("app.core.error_responses.get_request_id", return_value="test-id"):
        response = authorization_error_response("Access denied")

    assert response.status_code == status.HTTP_403_FORBIDDEN
    body = json.loads(response.body)
    assert body["error"]["type"] == "authorization_error"
    assert body["error"]["message"] == "Access denied"


def test_validation_error_response() -> None:
    """Test validation error response."""
    with patch("app.core.error_responses.get_request_id", return_value="test-id"):
        response = validation_error_response("Invalid email format")

    assert response.status_code == status.HTTP_422_UNPROCESSABLE_ENTITY
    body = json.loads(response.body)
    assert body["error"]["type"] == "validation_error"
    assert body["error"]["message"] == "Invalid email format"


def test_not_found_error_response() -> None:
    """Test not found error response."""
    with patch("app.core.error_responses.get_request_id", return_value="test-id"):
        response = not_found_error_response("User not found")

    assert response.status_code == status.HTTP_404_NOT_FOUND
    body = json.loads(response.body)
    assert body["error"]["type"] == "not_found_error"
    assert body["error"]["message"] == "User not found"


def test_rate_limit_error_response() -> None:
    """Test rate limit error response."""
    with patch("app.core.error_responses.get_request_id", return_value="test-id"):
        response = rate_limit_error_response()

    assert response.status_code == status.HTTP_429_TOO_MANY_REQUESTS
    body = json.loads(response.body)
    assert body["error"]["type"] == "rate_limit_error"
    assert "too many requests" in body["error"]["message"].lower()


def test_server_error_response() -> None:
    """Test server error response."""
    with patch("app.core.error_responses.get_request_id", return_value="test-id"):
        response = server_error_response()

    assert response.status_code == status.HTTP_500_INTERNAL_SERVER_ERROR
    body = json.loads(response.body)
    assert body["error"]["type"] == "server_error"
    assert "unexpected error" in body["error"]["message"].lower()


def test_database_error_response() -> None:
    """Test database error response."""
    with patch("app.core.error_responses.get_request_id", return_value="test-id"):
        response = database_error_response("Connection timeout")

    assert response.status_code == status.HTTP_500_INTERNAL_SERVER_ERROR
    body = json.loads(response.body)
    assert body["error"]["type"] == "database_error"
    assert body["error"]["message"] == "Connection timeout"


# ============================================================================
# Validation Error Formatting Tests
# ============================================================================


def test_format_validation_errors_single_error() -> None:
    """Test formatting single validation error."""
    errors = [
        {"loc": ["body", "email"], "msg": "field required", "type": "value_error.missing"}
    ]

    result = format_validation_errors(errors)
    assert "email" in result
    assert "field required" in result


def test_format_validation_errors_multiple_errors() -> None:
    """Test formatting multiple validation errors."""
    errors = [
        {"loc": ["body", "email"], "msg": "field required", "type": "value_error.missing"},
        {"loc": ["body", "age"], "msg": "value is not a valid integer", "type": "type_error.integer"},
    ]

    result = format_validation_errors(errors)
    assert "email" in result
    assert "field required" in result
    assert "age" in result
    assert "value is not a valid integer" in result


def test_format_validation_errors_nested_field() -> None:
    """Test formatting validation error for nested field."""
    errors = [
        {"loc": ["body", "address", "zip_code"], "msg": "invalid format", "type": "value_error"}
    ]

    result = format_validation_errors(errors)
    assert "address.zip_code" in result
    assert "invalid format" in result


def test_format_validation_errors_empty_list() -> None:
    """Test formatting empty validation errors list."""
    result = format_validation_errors([])
    assert result == "Validation failed"


def test_get_validation_details() -> None:
    """Test getting detailed validation error information."""
    errors = [
        {"loc": ["body", "email"], "msg": "field required", "type": "value_error.missing"},
        {"loc": ["body", "age"], "msg": "value is not a valid integer", "type": "type_error.integer"},
    ]

    result = get_validation_details(errors)
    details = json.loads(result)

    assert len(details) == 2
    assert details[0]["field"] == "email"
    assert details[0]["message"] == "field required"
    assert details[0]["type"] == "value_error.missing"
    assert details[1]["field"] == "age"
    assert details[1]["message"] == "value is not a valid integer"
    assert details[1]["type"] == "type_error.integer"


# ============================================================================
# Custom Exception Tests
# ============================================================================


def test_standardized_exception() -> None:
    """Test StandardizedException base class."""
    exc = StandardizedException(
        message="Test error",
        error_type=ErrorType.VALIDATION_ERROR,
        status_code=422,
        details="Test details",
    )

    assert str(exc) == "Test error"
    assert exc.message == "Test error"
    assert exc.error_type == ErrorType.VALIDATION_ERROR
    assert exc.status_code == 422
    assert exc.details == "Test details"


def test_authentication_error_exception() -> None:
    """Test AuthenticationError exception."""
    exc = AuthenticationError("Invalid token")

    assert exc.message == "Invalid token"
    assert exc.error_type == ErrorType.AUTHENTICATION_ERROR
    assert exc.status_code == 401


def test_authorization_error_exception() -> None:
    """Test AuthorizationError exception."""
    exc = AuthorizationError("Access denied")

    assert exc.message == "Access denied"
    assert exc.error_type == ErrorType.AUTHORIZATION_ERROR
    assert exc.status_code == 403


def test_validation_error_exception() -> None:
    """Test ValidationError exception."""
    exc = ValidationError("Invalid email")

    assert exc.message == "Invalid email"
    assert exc.error_type == ErrorType.VALIDATION_ERROR
    assert exc.status_code == 422


def test_not_found_error_exception() -> None:
    """Test NotFoundError exception."""
    exc = NotFoundError("User not found")

    assert exc.message == "User not found"
    assert exc.error_type == ErrorType.NOT_FOUND_ERROR
    assert exc.status_code == 404


def test_rate_limit_error_exception() -> None:
    """Test RateLimitError exception."""
    exc = RateLimitError()

    assert "too many requests" in exc.message.lower()
    assert exc.error_type == ErrorType.RATE_LIMIT_ERROR
    assert exc.status_code == 429


def test_database_error_exception() -> None:
    """Test DatabaseError exception."""
    exc = DatabaseError("Connection failed")

    assert exc.message == "Connection failed"
    assert exc.error_type == ErrorType.DATABASE_ERROR
    assert exc.status_code == 500


def test_exception_with_details() -> None:
    """Test exception with details."""
    exc = AuthenticationError("Token expired", details="Expired at 2024-01-01T00:00:00Z")

    assert exc.details == "Expired at 2024-01-01T00:00:00Z"


# ============================================================================
# Error Type Enum Tests
# ============================================================================


def test_error_type_values() -> None:
    """Test ErrorType enum values match expected format."""
    assert ErrorType.AUTHENTICATION_ERROR.value == "authentication_error"
    assert ErrorType.AUTHORIZATION_ERROR.value == "authorization_error"
    assert ErrorType.VALIDATION_ERROR.value == "validation_error"
    assert ErrorType.NOT_FOUND_ERROR.value == "not_found_error"
    assert ErrorType.RATE_LIMIT_ERROR.value == "rate_limit_error"
    assert ErrorType.SERVER_ERROR.value == "server_error"
    assert ErrorType.DATABASE_ERROR.value == "database_error"
    assert ErrorType.NETWORK_ERROR.value == "network_error"
    assert ErrorType.TIMEOUT_ERROR.value == "timeout_error"
    assert ErrorType.HTTP_ERROR.value == "http_error"
    assert ErrorType.INTERNAL_ERROR.value == "internal_error"
    assert ErrorType.UNKNOWN_ERROR.value == "unknown_error"
