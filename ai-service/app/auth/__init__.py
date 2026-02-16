"""Authentication module for LAYA AI Service.

This package contains authentication and authorization functionality including:
- User models and schemas
- JWT token generation and validation
- Authentication routes and endpoints
- Password hashing and verification
- Role-based access control
"""

from app.auth.models import User, UserRole, TokenBlacklist
from app.auth.schemas import (
    LoginRequest,
    RefreshRequest,
    TokenResponse,
    LogoutRequest,
    LogoutResponse,
)

__all__ = [
    "User",
    "UserRole",
    "TokenBlacklist",
    "LoginRequest",
    "RefreshRequest",
    "TokenResponse",
    "LogoutRequest",
    "LogoutResponse",
]
