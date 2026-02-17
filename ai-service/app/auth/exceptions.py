"""Authorization exception classes for the authentication module.

This module defines custom exceptions for authorization-related errors,
including resource access denial and resource not found scenarios.
These exceptions are used throughout the application to enforce security
and prevent unauthorized access to resources.
"""

from __future__ import annotations


# =============================================================================
# Exception Classes
# =============================================================================


class AuthorizationError(Exception):
    """Base exception for authorization errors."""

    pass


class ResourceNotFoundError(AuthorizationError):
    """Raised when the requested resource is not found."""

    pass


class UnauthorizedAccessError(AuthorizationError):
    """Raised when the user does not have permission to access a resource."""

    pass


class ForbiddenError(AuthorizationError):
    """Raised when access to a resource is forbidden."""

    pass


class OwnershipVerificationError(AuthorizationError):
    """Raised when resource ownership verification fails."""

    pass
