"""Authentication module for LAYA AI Service.

This package contains authentication and authorization functionality including:
- User models and schemas
- JWT token generation and validation
- Authentication routes and endpoints
- Password hashing and verification
- Role-based access control
"""

import sys
import importlib

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
from app.auth.jwt import security, verify_token, create_token
from app.auth.dependencies import get_current_user, require_role

# Import MFA-related items from parent auth.py module (not this package)
# We need to import the auth.py file, not this auth/ package
auth_module = importlib.import_module('..auth', 'app.auth')
MFA_REQUIRED_CLAIM = getattr(auth_module, 'MFA_REQUIRED_CLAIM', 'mfa_required')
MFA_VERIFIED_CLAIM = getattr(auth_module, 'MFA_VERIFIED_CLAIM', 'mfa_verified')
TokenPayload = getattr(auth_module, 'TokenPayload', None)
verify_mfa_token = getattr(auth_module, 'verify_mfa_token', None)
is_mfa_verified = getattr(auth_module, 'is_mfa_verified', None)
requires_mfa = getattr(auth_module, 'requires_mfa', None)

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
    "create_token",
    # MFA constants and utilities
    "MFA_REQUIRED_CLAIM",
    "MFA_VERIFIED_CLAIM",
    "TokenPayload",
    "verify_mfa_token",
    "is_mfa_verified",
    "requires_mfa",
    # Dependencies
    "get_current_user",
    "require_role",
]
