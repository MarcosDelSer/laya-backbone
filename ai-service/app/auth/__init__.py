"""Authentication module for LAYA AI Service.

This package contains authentication and authorization functionality including:
- User models and schemas
- JWT token generation and validation
- Authentication routes and endpoints
- Password hashing and verification
- Role-based access control
"""

# Import from sibling auth.py module (which exists alongside this auth/ package)
# This is done before other imports to ensure the module is loaded
import sys
from pathlib import Path

# Load the auth.py module file manually to avoid conflict with auth/ package
spec_path = Path(__file__).parent.parent / "auth.py"
if spec_path.exists():
    import importlib.util
    spec = importlib.util.spec_from_file_location("app._auth_utils", spec_path)
    if spec and spec.loader:
        auth_utils = importlib.util.module_from_spec(spec)
        sys.modules["app._auth_utils"] = auth_utils
        spec.loader.exec_module(auth_utils)
        verify_token = auth_utils.verify_token
        security = auth_utils.security
else:
    # Fallback if auth.py doesn't exist
    verify_token = None
    security = None

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

__all__ = [
    "User",
    "UserRole",
    "TokenBlacklist",
    "PasswordResetToken",
    "LoginRequest",
    "RefreshRequest",
    "TokenResponse",
    "LogoutRequest",
    "LogoutResponse",
    "PasswordResetRequest",
    "PasswordResetRequestResponse",
    "PasswordResetConfirm",
    "PasswordResetConfirmResponse",
    "verify_token",
    "security",
]
