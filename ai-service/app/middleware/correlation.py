"""Correlation ID middleware for distributed tracing.

This middleware handles correlation ID propagation across service boundaries,
enabling end-to-end request tracking in a microservices architecture.
"""

import uuid
from typing import Callable

from fastapi import Request, Response
from starlette.middleware.base import BaseHTTPMiddleware

from app.core.context import (
    get_correlation_id,
    get_request_id,
    set_correlation_id,
    set_request_id,
)
from app.core.logging import get_logger

logger = get_logger(__name__)


class CorrelationMiddleware(BaseHTTPMiddleware):
    """Middleware for handling request and correlation ID propagation.

    This middleware:
    - Extracts or generates X-Request-ID header for the current request
    - Extracts or generates X-Correlation-ID header for distributed tracing
    - Stores both IDs in context variables for access throughout the request
    - Adds both IDs to response headers for client visibility
    - Logs both IDs for correlation in distributed systems

    Request ID: Unique to each HTTP request
    Correlation ID: Shared across multiple service calls in a transaction
    """

    async def dispatch(self, request: Request, call_next: Callable) -> Response:
        """Process the request and handle ID propagation.

        Args:
            request: The incoming HTTP request
            call_next: The next middleware or route handler

        Returns:
            Response: The HTTP response with correlation headers
        """
        # Extract or generate request ID (unique per request)
        request_id = request.headers.get("X-Request-ID")
        if not request_id:
            request_id = str(uuid.uuid4())

        # Extract or generate correlation ID (shared across related requests)
        # If a correlation ID is provided, use it (request is part of existing flow)
        # Otherwise, use the request ID as the correlation ID (start of new flow)
        correlation_id = request.headers.get("X-Correlation-ID")
        if not correlation_id:
            correlation_id = request_id

        # Store in request state for backward compatibility
        request.state.request_id = request_id
        request.state.correlation_id = correlation_id

        # Store in context variables for global access
        set_request_id(request_id)
        set_correlation_id(correlation_id)

        # Bind to logger for this request
        request_logger = logger.bind(
            request_id=request_id,
            correlation_id=correlation_id,
        )

        # Log incoming request with both IDs
        request_logger.info(
            "Incoming request",
            method=request.method,
            path=request.url.path,
            client=request.client.host if request.client else None,
        )

        # Process the request
        response = await call_next(request)

        # Add both IDs to response headers
        response.headers["X-Request-ID"] = request_id
        response.headers["X-Correlation-ID"] = correlation_id

        # Log response
        request_logger.info(
            "Request completed",
            status_code=response.status_code,
        )

        return response


def correlation_middleware(app):
    """Add correlation middleware to the FastAPI application.

    Args:
        app: The FastAPI application instance

    Returns:
        The app with middleware added
    """
    app.add_middleware(CorrelationMiddleware)
    return app
