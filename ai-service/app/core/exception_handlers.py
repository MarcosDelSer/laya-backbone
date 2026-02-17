"""Custom exception handlers for FastAPI application.

This module registers custom exception handlers that convert FastAPI's
default error responses to our standardized format.
"""

from fastapi import FastAPI, Request, status
from fastapi.exceptions import RequestValidationError
from fastapi.responses import JSONResponse
from starlette.exceptions import HTTPException as StarletteHTTPException

from app.core.context import get_correlation_id, get_request_id
from app.core.error_responses import format_validation_errors, get_validation_details
from app.core.errors import ErrorType
from app.core.logging import get_logger

logger = get_logger(__name__)


async def http_exception_handler(request: Request, exc: StarletteHTTPException) -> JSONResponse:
    """Handle HTTPException with standardized error response.

    Args:
        request: The incoming request
        exc: The HTTPException that was raised

    Returns:
        JSONResponse: Standardized error response
    """
    import os

    # Get request and correlation IDs from context
    request_id = get_request_id() or getattr(request.state, "request_id", "unknown")
    correlation_id = get_correlation_id() or getattr(request.state, "correlation_id", request_id)

    # Map HTTP status codes to error types
    status_code = exc.status_code
    if status_code == 401:
        error_type = ErrorType.AUTHENTICATION_ERROR.value
    elif status_code == 403:
        error_type = ErrorType.AUTHORIZATION_ERROR.value
    elif status_code == 404:
        error_type = ErrorType.NOT_FOUND_ERROR.value
    elif status_code == 422:
        error_type = ErrorType.VALIDATION_ERROR.value
    elif status_code == 429:
        error_type = ErrorType.RATE_LIMIT_ERROR.value
    elif status_code >= 500:
        error_type = ErrorType.SERVER_ERROR.value
    else:
        error_type = ErrorType.HTTP_ERROR.value

    message = exc.detail

    # Log the exception
    log_level = "error" if status_code >= 500 else "info"
    logger_func = logger.error if log_level == "error" else logger.info
    logger_func(
        "HTTP exception",
        status_code=status_code,
        error_type=error_type,
        message=message,
        request_id=request_id,
        correlation_id=correlation_id,
    )

    # Build standardized error response
    error_response = {
        "error": {
            "type": error_type,
            "message": message,
            "request_id": request_id,
            "correlation_id": correlation_id,
        }
    }

    # Add details if in development mode and it's a server error
    env = os.getenv("ENVIRONMENT", "development")
    if env == "development" and status_code >= 500:
        error_response["error"]["details"] = str(exc)

    return JSONResponse(
        status_code=status_code,
        content=error_response,
        headers={
            "X-Request-ID": request_id,
            "X-Correlation-ID": correlation_id,
        },
    )


async def validation_exception_handler(
    request: Request, exc: RequestValidationError
) -> JSONResponse:
    """Handle RequestValidationError with standardized error response.

    Args:
        request: The incoming request
        exc: The RequestValidationError that was raised

    Returns:
        JSONResponse: Standardized validation error response
    """
    import os

    # Get request and correlation IDs from context
    request_id = get_request_id() or getattr(request.state, "request_id", "unknown")
    correlation_id = get_correlation_id() or getattr(request.state, "correlation_id", request_id)

    # Format validation errors
    message = format_validation_errors(exc.errors())

    # Log validation error
    logger.info(
        "Validation error",
        status_code=422,
        error_type="validation_error",
        message=message,
        validation_errors_count=len(exc.errors()),
        request_id=request_id,
        correlation_id=correlation_id,
    )

    # Build standardized error response
    error_response = {
        "error": {
            "type": ErrorType.VALIDATION_ERROR.value,
            "message": message,
            "request_id": request_id,
            "correlation_id": correlation_id,
        }
    }

    # Add detailed validation errors in development mode
    env = os.getenv("ENVIRONMENT", "development")
    if env == "development":
        error_response["error"]["details"] = get_validation_details(exc.errors())

    return JSONResponse(
        status_code=status.HTTP_422_UNPROCESSABLE_ENTITY,
        content=error_response,
        headers={
            "X-Request-ID": request_id,
            "X-Correlation-ID": correlation_id,
        },
    )


def register_exception_handlers(app: FastAPI) -> None:
    """Register custom exception handlers to the FastAPI application.

    This ensures all errors are returned in standardized format.

    Args:
        app: The FastAPI application instance
    """
    app.add_exception_handler(StarletteHTTPException, http_exception_handler)
    app.add_exception_handler(RequestValidationError, validation_exception_handler)

    logger.info("Custom exception handlers registered")
