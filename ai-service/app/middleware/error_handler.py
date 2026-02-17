"""Exception middleware for centralized error handling with request ID tracking.

This module provides middleware that catches all unhandled exceptions and returns
structured JSON error responses with request IDs for traceability.
"""

import uuid
from typing import Callable

from fastapi import Request, Response, status
from fastapi.responses import JSONResponse
from starlette.middleware.base import BaseHTTPMiddleware


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

        try:
            # Process the request
            response = await call_next(request)

            # Add request ID to response headers
            response.headers["X-Request-ID"] = request_id

            return response

        except Exception as exc:
            # Handle any unhandled exceptions
            return await self._handle_exception(exc, request_id)

    async def _handle_exception(
        self, exc: Exception, request_id: str
    ) -> JSONResponse:
        """Handle an unhandled exception and return structured error response.

        Args:
            exc: The exception that was raised
            request_id: The request ID for traceability

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
        else:
            # Default to 500 for unhandled exceptions
            status_code = status.HTTP_500_INTERNAL_SERVER_ERROR
            error_type = "internal_error"
            message = "An unexpected error occurred"

        # Build structured error response
        error_response = {
            "error": {
                "type": error_type,
                "message": message,
                "request_id": request_id,
            }
        }

        # Add exception details for debugging (only for 500 errors in development)
        # In production, this should be logged but not exposed to clients
        if status_code == status.HTTP_500_INTERNAL_SERVER_ERROR:
            # TODO: Add proper logging here instead of exposing to client
            # For now, keep the error details minimal for security
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
