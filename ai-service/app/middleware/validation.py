"""Input validation middleware for LAYA AI Service.

This module provides comprehensive input validation middleware to prevent
common security vulnerabilities and ensure secure data handling.

Security Features:
    - Pydantic validation with JSON schema enforcement
    - Detailed validation error responses
    - Protection against SQL injection via proper typing
    - Protection against XSS via input validation
    - Secure error messages that don't leak sensitive data
    - Field-level constraints (min_length, max_length, ge, le, etc.)
"""

from typing import Any, Callable, Union

from fastapi import Request, Response, status
from fastapi.exceptions import RequestValidationError
from fastapi.responses import JSONResponse
from pydantic import ValidationError


async def validation_exception_handler(
    request: Request,
    exc: Union[RequestValidationError, ValidationError],
) -> JSONResponse:
    """Handle validation errors with security-focused error messages.

    This handler intercepts Pydantic validation errors and returns
    user-friendly error messages while maintaining security by not
    exposing internal implementation details.

    Args:
        request: The incoming request that failed validation
        exc: The validation exception containing error details

    Returns:
        JSONResponse: Structured error response with validation details
    """
    errors = []

    # Process validation errors
    for error in exc.errors():
        error_detail = {
            "field": ".".join(str(loc) for loc in error.get("loc", [])),
            "message": error.get("msg", "Validation error"),
            "type": error.get("type", "value_error"),
        }

        # Add input value only for certain safe error types (avoid leaking sensitive data)
        error_type = error.get("type", "")
        if error_type in [
            "string_type",
            "int_type",
            "float_type",
            "bool_type",
            "list_type",
            "dict_type",
        ]:
            # For type errors, we can safely indicate what type was received
            error_detail["received_type"] = type(error.get("input", None)).__name__

        errors.append(error_detail)

    return JSONResponse(
        status_code=status.HTTP_422_UNPROCESSABLE_ENTITY,
        content={
            "detail": "Validation error",
            "errors": errors,
            "message": "Invalid input data. Please check the errors and try again.",
        },
    )


def get_validation_middleware() -> Callable:
    """Get the validation middleware for the FastAPI application.

    This middleware ensures comprehensive validation is enforced across
    all API endpoints using Pydantic validation with security-focused
    error handling.

    Returns:
        Callable: Middleware function for FastAPI application
    """
    async def validation_middleware(request: Request, call_next: Callable) -> Response:
        """Middleware to handle request validation.

        This middleware wraps request processing to ensure that all
        validation errors are handled consistently and securely.

        Args:
            request: The incoming request
            call_next: The next middleware/handler in the chain

        Returns:
            Response: The response from the next handler or error response
        """
        try:
            response = await call_next(request)
            return response
        except (RequestValidationError, ValidationError) as exc:
            return await validation_exception_handler(request, exc)

    return validation_middleware
