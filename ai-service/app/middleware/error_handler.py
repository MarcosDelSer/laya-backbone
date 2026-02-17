"""Exception middleware for centralized error handling with request ID tracking.

This module provides middleware that catches all unhandled exceptions and returns
structured JSON error responses with request IDs for traceability.
"""

import uuid
from typing import Callable

from fastapi import Request, Response, status
from fastapi.responses import JSONResponse
from starlette.middleware.base import BaseHTTPMiddleware

from app.core.context import get_correlation_id, get_request_id
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
        # Determine status code based on exception type
        from fastapi import HTTPException
        from starlette.exceptions import HTTPException as StarletteHTTPException

        if isinstance(exc, (HTTPException, StarletteHTTPException)):
            status_code = exc.status_code
            error_type = "http_error"
            message = exc.detail
            # Log HTTP exceptions at info level (expected errors)
            request_logger.info(
                "HTTP exception",
                status_code=status_code,
                error_type=error_type,
                message=message,
            )
        else:
            # Default to 500 for unhandled exceptions
            status_code = status.HTTP_500_INTERNAL_SERVER_ERROR
            error_type = "internal_error"
            message = "An unexpected error occurred"
            # Log unexpected exceptions at error level with full details
            request_logger.error(
                "Unhandled exception",
                status_code=status_code,
                error_type=error_type,
                exception_type=type(exc).__name__,
                exception_message=str(exc),
                exc_info=True,
            )

        # Get correlation ID from context
        correlation_id = get_correlation_id() or request_id

        # Build structured error response
        error_response = {
            "error": {
                "type": error_type,
                "message": message,
                "request_id": request_id,
                "correlation_id": correlation_id,
            }
        }

        # Add exception details for debugging (only for 500 errors in development)
        # In production, details are logged but not exposed to clients
        import os
        if status_code == status.HTTP_500_INTERNAL_SERVER_ERROR:
            # Only expose details in development mode
            if os.getenv("ENVIRONMENT", "development") == "development":
                error_response["error"]["details"] = str(exc)

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
