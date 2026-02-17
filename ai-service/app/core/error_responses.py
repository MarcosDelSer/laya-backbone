"""Utility functions for creating standardized error responses.

This module provides helper functions to create error responses that
match the format expected by the parent-portal error handler.
"""

import os
from typing import Any, Optional

from fastapi import status
from fastapi.responses import JSONResponse

from app.core.context import get_correlation_id, get_request_id
from app.core.errors import ErrorResponse, ErrorType
from app.core.logging import get_logger

logger = get_logger(__name__)


def create_error_response(
    error_type: ErrorType,
    message: str,
    status_code: int,
    request_id: Optional[str] = None,
    correlation_id: Optional[str] = None,
    details: Optional[str] = None,
) -> JSONResponse:
    """Create a standardized error response.

    Args:
        error_type: Error type for categorization
        message: Human-readable error message
        status_code: HTTP status code
        request_id: Request ID (auto-retrieved from context if not provided)
        correlation_id: Correlation ID (auto-retrieved from context if not provided)
        details: Additional error details (only included in development mode)

    Returns:
        JSONResponse: Standardized error response with appropriate headers
    """
    # Get IDs from context if not provided
    req_id = request_id or get_request_id() or "unknown"
    corr_id = correlation_id or get_correlation_id() or req_id

    # Only include details in development mode
    env = os.getenv("ENVIRONMENT", "development")
    should_include_details = env == "development" and details is not None

    # Build error response
    error_response = ErrorResponse(
        error={
            "type": error_type.value,
            "message": message,
            "request_id": req_id,
            "correlation_id": corr_id,
            "details": details if should_include_details else None,
        }
    )

    # Log the error
    log_level = "error" if status_code >= 500 else "info"
    logger_func = logger.error if log_level == "error" else logger.info
    logger_func(
        "Error response created",
        error_type=error_type.value,
        message=message,
        status_code=status_code,
        request_id=req_id,
        correlation_id=corr_id,
    )

    return JSONResponse(
        status_code=status_code,
        content=error_response.model_dump(exclude_none=True),
        headers={
            "X-Request-ID": req_id,
            "X-Correlation-ID": corr_id,
        },
    )


# ============================================================================
# Specific Error Response Creators
# ============================================================================


def authentication_error_response(
    message: str = "Authentication failed",
    details: Optional[str] = None,
) -> JSONResponse:
    """Create an authentication error response.

    Args:
        message: Error message
        details: Additional error details

    Returns:
        JSONResponse: 401 authentication error response
    """
    return create_error_response(
        error_type=ErrorType.AUTHENTICATION_ERROR,
        message=message,
        status_code=status.HTTP_401_UNAUTHORIZED,
        details=details,
    )


def authorization_error_response(
    message: str = "You do not have permission to access this resource",
    details: Optional[str] = None,
) -> JSONResponse:
    """Create an authorization error response.

    Args:
        message: Error message
        details: Additional error details

    Returns:
        JSONResponse: 403 authorization error response
    """
    return create_error_response(
        error_type=ErrorType.AUTHORIZATION_ERROR,
        message=message,
        status_code=status.HTTP_403_FORBIDDEN,
        details=details,
    )


def validation_error_response(
    message: str,
    details: Optional[str] = None,
) -> JSONResponse:
    """Create a validation error response.

    Args:
        message: Error message
        details: Additional error details

    Returns:
        JSONResponse: 422 validation error response
    """
    return create_error_response(
        error_type=ErrorType.VALIDATION_ERROR,
        message=message,
        status_code=status.HTTP_422_UNPROCESSABLE_ENTITY,
        details=details,
    )


def not_found_error_response(
    message: str = "Resource not found",
    details: Optional[str] = None,
) -> JSONResponse:
    """Create a not found error response.

    Args:
        message: Error message
        details: Additional error details

    Returns:
        JSONResponse: 404 not found error response
    """
    return create_error_response(
        error_type=ErrorType.NOT_FOUND_ERROR,
        message=message,
        status_code=status.HTTP_404_NOT_FOUND,
        details=details,
    )


def rate_limit_error_response(
    message: str = "Too many requests. Please try again later.",
    details: Optional[str] = None,
) -> JSONResponse:
    """Create a rate limit error response.

    Args:
        message: Error message
        details: Additional error details

    Returns:
        JSONResponse: 429 rate limit error response
    """
    return create_error_response(
        error_type=ErrorType.RATE_LIMIT_ERROR,
        message=message,
        status_code=status.HTTP_429_TOO_MANY_REQUESTS,
        details=details,
    )


def server_error_response(
    message: str = "An unexpected error occurred",
    details: Optional[str] = None,
) -> JSONResponse:
    """Create a server error response.

    Args:
        message: Error message
        details: Additional error details

    Returns:
        JSONResponse: 500 server error response
    """
    return create_error_response(
        error_type=ErrorType.SERVER_ERROR,
        message=message,
        status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
        details=details,
    )


def database_error_response(
    message: str = "Database operation failed",
    details: Optional[str] = None,
) -> JSONResponse:
    """Create a database error response.

    Args:
        message: Error message
        details: Additional error details

    Returns:
        JSONResponse: 500 database error response
    """
    return create_error_response(
        error_type=ErrorType.DATABASE_ERROR,
        message=message,
        status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
        details=details,
    )


# ============================================================================
# Validation Error Formatting
# ============================================================================


def format_validation_errors(errors: list[dict[str, Any]]) -> str:
    """Format Pydantic validation errors into a human-readable message.

    Args:
        errors: List of validation error dicts from Pydantic

    Returns:
        str: Formatted error message

    Example:
        >>> errors = [
        ...     {"loc": ["body", "email"], "msg": "field required"},
        ...     {"loc": ["body", "age"], "msg": "value is not a valid integer"}
        ... ]
        >>> format_validation_errors(errors)
        'Validation failed: email (field required); age (value is not a valid integer)'
    """
    if not errors:
        return "Validation failed"

    error_messages = []
    for error in errors:
        # Get field path (e.g., ["body", "email"] -> "email")
        loc = error.get("loc", [])
        # Skip "body" or "query" prefix
        field_path = ".".join(str(loc_item) for loc_item in loc if loc_item not in ["body", "query", "path"])

        # Get error message
        msg = error.get("msg", "invalid value")

        if field_path:
            error_messages.append(f"{field_path} ({msg})")
        else:
            error_messages.append(msg)

    return f"Validation failed: {'; '.join(error_messages)}"


def get_validation_details(errors: list[dict[str, Any]]) -> str:
    """Get detailed validation error information as JSON string.

    Args:
        errors: List of validation error dicts from Pydantic

    Returns:
        str: JSON string with detailed error information
    """
    import json

    simplified_errors = []
    for error in errors:
        simplified_errors.append({
            "field": ".".join(str(loc) for loc in error.get("loc", []) if loc not in ["body", "query", "path"]),
            "message": error.get("msg", "invalid value"),
            "type": error.get("type", "value_error"),
        })

    return json.dumps(simplified_errors, indent=2)
