"""Error types, custom exceptions, and error response schemas.

This module provides standardized error handling that aligns with the
parent-portal errorHandler expectations.
"""

from enum import Enum
from typing import Any, Optional

from pydantic import BaseModel, Field


class ErrorType(str, Enum):
    """Standard error types aligned with parent-portal error categories.

    These types match the ErrorCategory enum in parent-portal/lib/api/errorHandler.ts
    """

    # Authentication and Authorization
    AUTHENTICATION_ERROR = "authentication_error"
    AUTHORIZATION_ERROR = "authorization_error"

    # Client Errors
    VALIDATION_ERROR = "validation_error"
    NOT_FOUND_ERROR = "not_found_error"
    RATE_LIMIT_ERROR = "rate_limit_error"

    # Server Errors
    SERVER_ERROR = "server_error"
    DATABASE_ERROR = "database_error"

    # Network and Communication Errors
    NETWORK_ERROR = "network_error"
    TIMEOUT_ERROR = "timeout_error"

    # Generic Errors
    HTTP_ERROR = "http_error"
    INTERNAL_ERROR = "internal_error"
    UNKNOWN_ERROR = "unknown_error"


class ErrorDetail(BaseModel):
    """Detailed error information in standardized format.

    This schema matches the StandardErrorResponse.error format expected
    by parent-portal/lib/api/errorHandler.ts
    """

    type: str = Field(
        ...,
        description="Error type for categorization",
        examples=["validation_error", "authentication_error"],
    )
    message: str = Field(
        ...,
        description="Human-readable error message",
        examples=["Invalid input data"],
    )
    request_id: str = Field(
        ...,
        description="Request ID for traceability",
        examples=["550e8400-e29b-41d4-a716-446655440000"],
    )
    correlation_id: str = Field(
        ...,
        description="Correlation ID for distributed tracing",
        examples=["550e8400-e29b-41d4-a716-446655440000"],
    )
    details: Optional[str] = Field(
        default=None,
        description="Additional error details (only in development mode)",
        examples=["Field 'email' is required"],
    )


class ErrorResponse(BaseModel):
    """Standardized error response format.

    This schema matches the StandardErrorResponse interface expected
    by parent-portal/lib/api/errorHandler.ts:

    interface StandardErrorResponse {
      error: {
        type: string;
        message: string;
        request_id: string;
        correlation_id: string;
        details?: string;
      };
    }
    """

    error: ErrorDetail = Field(
        ...,
        description="Error details",
    )

    class Config:
        """Pydantic model configuration."""
        json_schema_extra = {
            "example": {
                "error": {
                    "type": "validation_error",
                    "message": "Invalid input data",
                    "request_id": "550e8400-e29b-41d4-a716-446655440000",
                    "correlation_id": "550e8400-e29b-41d4-a716-446655440000",
                    "details": "Field 'email' must be a valid email address",
                }
            }
        }


class ValidationErrorDetail(BaseModel):
    """Detailed validation error information."""

    field: str = Field(
        ...,
        description="Field name that failed validation",
        examples=["email"],
    )
    message: str = Field(
        ...,
        description="Validation error message",
        examples=["field required"],
    )
    type: str = Field(
        ...,
        description="Validation error type",
        examples=["value_error.missing"],
    )


# ============================================================================
# Custom Exception Classes
# ============================================================================


class StandardizedException(Exception):
    """Base exception class for standardized error responses.

    All custom exceptions should inherit from this class to ensure
    they are properly handled by the error middleware.
    """

    def __init__(
        self,
        message: str,
        error_type: ErrorType = ErrorType.INTERNAL_ERROR,
        status_code: int = 500,
        details: Optional[str] = None,
    ):
        """Initialize standardized exception.

        Args:
            message: Human-readable error message
            error_type: Error type for categorization
            status_code: HTTP status code
            details: Additional error details
        """
        super().__init__(message)
        self.message = message
        self.error_type = error_type
        self.status_code = status_code
        self.details = details


class AuthenticationError(StandardizedException):
    """Exception for authentication failures."""

    def __init__(self, message: str = "Authentication failed", details: Optional[str] = None):
        """Initialize authentication error.

        Args:
            message: Error message
            details: Additional error details
        """
        super().__init__(
            message=message,
            error_type=ErrorType.AUTHENTICATION_ERROR,
            status_code=401,
            details=details,
        )


class AuthorizationError(StandardizedException):
    """Exception for authorization failures."""

    def __init__(
        self,
        message: str = "You do not have permission to access this resource",
        details: Optional[str] = None,
    ):
        """Initialize authorization error.

        Args:
            message: Error message
            details: Additional error details
        """
        super().__init__(
            message=message,
            error_type=ErrorType.AUTHORIZATION_ERROR,
            status_code=403,
            details=details,
        )


class ValidationError(StandardizedException):
    """Exception for validation failures."""

    def __init__(self, message: str, details: Optional[str] = None):
        """Initialize validation error.

        Args:
            message: Error message
            details: Additional error details
        """
        super().__init__(
            message=message,
            error_type=ErrorType.VALIDATION_ERROR,
            status_code=422,
            details=details,
        )


class NotFoundError(StandardizedException):
    """Exception for resource not found errors."""

    def __init__(self, message: str = "Resource not found", details: Optional[str] = None):
        """Initialize not found error.

        Args:
            message: Error message
            details: Additional error details
        """
        super().__init__(
            message=message,
            error_type=ErrorType.NOT_FOUND_ERROR,
            status_code=404,
            details=details,
        )


class RateLimitError(StandardizedException):
    """Exception for rate limiting errors."""

    def __init__(
        self,
        message: str = "Too many requests. Please try again later.",
        details: Optional[str] = None,
    ):
        """Initialize rate limit error.

        Args:
            message: Error message
            details: Additional error details
        """
        super().__init__(
            message=message,
            error_type=ErrorType.RATE_LIMIT_ERROR,
            status_code=429,
            details=details,
        )


class DatabaseError(StandardizedException):
    """Exception for database-related errors."""

    def __init__(
        self,
        message: str = "Database operation failed",
        details: Optional[str] = None,
    ):
        """Initialize database error.

        Args:
            message: Error message
            details: Additional error details
        """
        super().__init__(
            message=message,
            error_type=ErrorType.DATABASE_ERROR,
            status_code=500,
            details=details,
        )
