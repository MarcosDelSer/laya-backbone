"""Exception middleware for centralized error handling with request ID tracking.

This module provides middleware that catches all unhandled exceptions and returns
structured JSON error responses with request IDs for traceability.
"""

import uuid
from typing import Callable

from fastapi import Request, Response, status
from fastapi.responses import JSONResponse
from starlette.middleware.base import BaseHTTPMiddleware

from app.core.logging import bind_request_id, get_logger

logger = get_logger(__name__)


class ErrorHandlerMiddleware(BaseHTTPMiddleware):
    """Middleware for catching and handling all unhandled exceptions.

    This middleware:
    - Extracts or generates a request ID from X-Request-ID header
    - Catches all unhandled exceptions during request processing
    - Returns structured JSON error responses with request ID
    - Logs exceptions with request context for debugging
    """

    async def dispatch(self, request: Request, call_next: Callable) -> Response:
        """Process the request and handle any exceptions.

        Args:
            request: The incoming HTTP request
            call_next: The next middleware or route handler

        Returns:
            Response: The HTTP response, either from the handler or error response
        """
        # Extract or generate request ID
        request_id = request.headers.get("X-Request-ID")
        if not request_id:
            request_id = str(uuid.uuid4())

        # Store request_id in request state for access in route handlers
        request.state.request_id = request_id

        # Bind request ID to logger for this request
        request_logger = bind_request_id(logger, request_id)

        try:
            # Log incoming request
            request_logger.info(
                "Incoming request",
                method=request.method,
                path=request.url.path,
                client=request.client.host if request.client else None,
            )

            # Process the request
            response = await call_next(request)

            # Add request ID to response headers
            response.headers["X-Request-ID"] = request_id

            # Log successful response
            request_logger.info(
                "Request completed",
                status_code=response.status_code,
            )

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
            request_logger: Logger with bound request_id

        Returns:
            JSONResponse: Structured error response with request ID
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

        # Build structured error response
        error_response = {
            "error": {
                "type": error_type,
                "message": message,
                "request_id": request_id,
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
            headers={"X-Request-ID": request_id},
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
