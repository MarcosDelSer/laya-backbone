"""Exception middleware for centralized error handling with request ID tracking.

This module provides middleware that catches all unhandled exceptions and returns
structured JSON error responses with request IDs for traceability.

The middleware handles:
- RequestValidationError (FastAPI validation errors)
- StandardizedException (custom domain exceptions)
- HTTPException (FastAPI HTTP exceptions)
- Generic exceptions (unexpected errors)

All errors are returned in standardized format compatible with parent-portal error handler.
"""

import uuid
from typing import Callable

from fastapi import Request, Response, status
from fastapi.exceptions import RequestValidationError
from fastapi.responses import JSONResponse
from starlette.middleware.base import BaseHTTPMiddleware

from app.core.context import get_correlation_id, get_request_id
from app.core.error_responses import format_validation_errors, get_validation_details
from app.core.errors import ErrorType, StandardizedException
from app.core.logging import bind_request_id, get_logger

logger = get_logger(__name__)


class ErrorHandlerMiddleware(BaseHTTPMiddleware):
    """Middleware for catching and handling all unhandled exceptions.

    This middleware:
    - Uses request ID and correlation ID from context
    - Catches all unhandled exceptions during request processing
    - Returns structured JSON error responses with request ID and correlation ID
    - Logs exceptions with request context for debugging

    Note: This middleware should be added AFTER CorrelationMiddleware
    to ensure IDs are already set in the context.
    """

    async def dispatch(self, request: Request, call_next: Callable) -> Response:
        """Process the request and handle any exceptions.

        Args:
            request: The incoming HTTP request
            call_next: The next middleware or route handler

        Returns:
            Response: The HTTP response, either from the handler or error response
        """
        # Get request and correlation IDs from context (set by CorrelationMiddleware)
        # Fall back to generating new ones if not set
        request_id = get_request_id()
        if not request_id:
            request_id = request.headers.get("X-Request-ID", str(uuid.uuid4()))
            request.state.request_id = request_id

        correlation_id = get_correlation_id()
        if not correlation_id:
            correlation_id = request.headers.get("X-Correlation-ID", request_id)
            request.state.correlation_id = correlation_id

        # Bind both IDs to logger for this request
        request_logger = logger.bind(
            request_id=request_id,
            correlation_id=correlation_id,
        )

        try:
            # Process the request
            # Note: CorrelationMiddleware handles request/response logging
            # This middleware only catches and handles exceptions
            response = await call_next(request)

            # Add request and correlation IDs to response headers only if not already set
            # (CorrelationMiddleware adds them, but this ensures they're present even without it)
            if "X-Request-ID" not in response.headers:
                response.headers["X-Request-ID"] = request_id
            if "X-Correlation-ID" not in response.headers:
                response.headers["X-Correlation-ID"] = correlation_id

            return response

        except Exception as exc:
            # Log the exception
            request_logger.error(
                "Request failed with exception",
                exception_type=type(exc).__name__,
                exception_message=str(exc),
                exc_info=True,
            )
            # Handle any unhandled exceptions
            return await self._handle_exception(exc, request_id, request_logger)

    async def _handle_exception(
        self, exc: Exception, request_id: str, request_logger
    ) -> JSONResponse:
        """Handle an unhandled exception and return structured error response.

        Args:
            exc: The exception that was raised
            request_id: The request ID for traceability
            request_logger: Logger with bound request_id and correlation_id

        Returns:
            JSONResponse: Structured error response with request ID and correlation ID
        """
        import os
        from fastapi import HTTPException
        from starlette.exceptions import HTTPException as StarletteHTTPException

        # Get correlation ID from context
        correlation_id = get_correlation_id() or request_id

        # Determine if we should include details (development mode only)
        env = os.getenv("ENVIRONMENT", "development")
        should_include_details = env == "development"

        # Handle different exception types with standardized responses

        # 1. RequestValidationError (FastAPI validation errors)
        if isinstance(exc, RequestValidationError):
            status_code = status.HTTP_422_UNPROCESSABLE_ENTITY
            error_type = ErrorType.VALIDATION_ERROR.value
            message = format_validation_errors(exc.errors())
            details = get_validation_details(exc.errors()) if should_include_details else None

            request_logger.info(
                "Validation error",
                status_code=status_code,
                error_type=error_type,
                message=message,
                validation_errors_count=len(exc.errors()),
            )

        # 2. StandardizedException (custom domain exceptions)
        elif isinstance(exc, StandardizedException):
            status_code = exc.status_code
            error_type = exc.error_type.value
            message = exc.message
            details = exc.details if should_include_details else None

            # Log at appropriate level based on status code
            log_level = "error" if status_code >= 500 else "info"
            log_func = request_logger.error if log_level == "error" else request_logger.info
            log_func(
                "Standardized exception",
                status_code=status_code,
                error_type=error_type,
                message=message,
            )

        # 3. HTTPException (FastAPI HTTP exceptions)
        elif isinstance(exc, (HTTPException, StarletteHTTPException)):
            status_code = exc.status_code
            message = exc.detail

            # Map HTTP status codes to error types
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

            details = str(exc) if should_include_details and status_code >= 500 else None

            # Log HTTP exceptions at info level (expected errors)
            request_logger.info(
                "HTTP exception",
                status_code=status_code,
                error_type=error_type,
                message=message,
            )

        # 4. Generic exceptions (unexpected errors)
        else:
            status_code = status.HTTP_500_INTERNAL_SERVER_ERROR
            error_type = ErrorType.INTERNAL_ERROR.value
            message = "An unexpected error occurred"
            details = f"{type(exc).__name__}: {str(exc)}" if should_include_details else None

            # Log unexpected exceptions at error level with full details
            request_logger.error(
                "Unhandled exception",
                status_code=status_code,
                error_type=error_type,
                exception_type=type(exc).__name__,
                exception_message=str(exc),
                exc_info=True,
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

        # Add details if available and in development mode
        if details:
            error_response["error"]["details"] = details

        return JSONResponse(
            status_code=status_code,
            content=error_response,
            headers={
                "X-Request-ID": request_id,
                "X-Correlation-ID": correlation_id,
            },
        )


# Factory function to create middleware instance
def error_handler_middleware(app):
    """Add error handler middleware to the FastAPI application.

    Args:
        app: The FastAPI application instance

    Returns:
        The app with middleware added
    """
    app.add_middleware(ErrorHandlerMiddleware)
    return app
