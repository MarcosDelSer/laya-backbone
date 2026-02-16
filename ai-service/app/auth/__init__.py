"""Authentication module for LAYA AI Service.

This package contains authentication and authorization functionality including:
- User models and schemas
- JWT token generation and validation
- Authentication routes and endpoints
- Password hashing and verification
- Role-based access control
"""

from app.auth.models import User, UserRole, TokenBlacklist, PasswordResetToken
from app.auth.schemas import (
    LoginRequest,
    RefreshRequest,
    TokenResponse,
    LogoutRequest,
    LogoutResponse,
    PasswordResetRequest,
    PasswordResetRequestResponse,
    PasswordResetConfirm,
    PasswordResetConfirmResponse,
)
from app.auth.jwt import security, verify_token
from app.auth.dependencies import get_current_user, require_role

__all__ = [
    # Models
    "User",
    "UserRole",
    "TokenBlacklist",
    "PasswordResetToken",
    # Schemas
    "LoginRequest",
    "RefreshRequest",
    "TokenResponse",
    "LogoutRequest",
    "LogoutResponse",
    "PasswordResetRequest",
    "PasswordResetRequestResponse",
    "PasswordResetConfirm",
    "PasswordResetConfirmResponse",
    # JWT utilities
    "security",
    "verify_token",
    # Dependencies
    "get_current_user",
    "require_role",
]
