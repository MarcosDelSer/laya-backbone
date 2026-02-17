"""Unit tests for authentication Pydantic schemas.

Tests all auth request/response schemas with validation, required fields, and defaults.
"""

import pytest
from pydantic import ValidationError

from app.auth.schemas import (
    LoginRequest,
    LogoutRequest,
    LogoutResponse,
    PasswordResetConfirm,
    PasswordResetConfirmResponse,
    PasswordResetRequest,
    PasswordResetRequestResponse,
    RefreshRequest,
    TokenResponse,
)


class TestLoginRequest:
    """Tests for LoginRequest schema."""

    def test_valid_login_request(self):
        """Test creating a valid login request."""
        request = LoginRequest(
            email="user@example.com",
            password="SecurePass123!",
        )
        assert request.email == "user@example.com"
        assert request.password == "SecurePass123!"

    def test_login_request_email_required(self):
        """Test email field is required."""
        with pytest.raises(ValidationError) as exc_info:
            LoginRequest(password="SecurePass123!")
        assert "email" in str(exc_info.value)

    def test_login_request_password_required(self):
        """Test password field is required."""
        with pytest.raises(ValidationError) as exc_info:
            LoginRequest(email="user@example.com")
        assert "password" in str(exc_info.value)

    def test_login_request_invalid_email(self):
        """Test invalid email format is rejected."""
        with pytest.raises(ValidationError) as exc_info:
            LoginRequest(
                email="invalid-email",
                password="SecurePass123!",
            )
        assert "email" in str(exc_info.value).lower()

    def test_login_request_password_min_length(self):
        """Test password minimum length validation."""
        with pytest.raises(ValidationError) as exc_info:
            LoginRequest(
                email="user@example.com",
                password="short",  # Less than 8 characters
            )
        errors = exc_info.value.errors()
        assert any("password" in str(e).lower() for e in errors)

    def test_login_request_password_max_length(self):
        """Test password maximum length validation."""
        with pytest.raises(ValidationError) as exc_info:
            LoginRequest(
                email="user@example.com",
                password="x" * 101,  # More than 100 characters
            )
        errors = exc_info.value.errors()
        assert any("password" in str(e).lower() for e in errors)

    def test_login_request_email_normalized(self):
        """Test email is normalized/validated."""
        request = LoginRequest(
            email="USER@EXAMPLE.COM",
            password="SecurePass123!",
        )
        # EmailStr normalizes case
        assert "@" in request.email

    def test_login_request_whitespace_stripped(self):
        """Test whitespace is stripped from email."""
        request = LoginRequest(
            email="  user@example.com  ",
            password="SecurePass123!",
        )
        assert request.email.strip() == request.email


class TestRefreshRequest:
    """Tests for RefreshRequest schema."""

    def test_valid_refresh_request(self):
        """Test creating a valid refresh request."""
        request = RefreshRequest(refresh_token="valid.jwt.token")
        assert request.refresh_token == "valid.jwt.token"

    def test_refresh_request_token_required(self):
        """Test refresh_token field is required."""
        with pytest.raises(ValidationError) as exc_info:
            RefreshRequest()
        assert "refresh_token" in str(exc_info.value)

    def test_refresh_request_token_min_length(self):
        """Test refresh_token minimum length validation."""
        with pytest.raises(ValidationError) as exc_info:
            RefreshRequest(refresh_token="")
        errors = exc_info.value.errors()
        assert len(errors) > 0

    def test_refresh_request_long_token(self):
        """Test refresh_token accepts long tokens."""
        long_token = "a" * 1000
        request = RefreshRequest(refresh_token=long_token)
        assert request.refresh_token == long_token


class TestTokenResponse:
    """Tests for TokenResponse schema."""

    def test_valid_token_response(self):
        """Test creating a valid token response."""
        response = TokenResponse(
            access_token="access.jwt.token",
            refresh_token="refresh.jwt.token",
            expires_in=3600,
        )
        assert response.access_token == "access.jwt.token"
        assert response.refresh_token == "refresh.jwt.token"
        assert response.expires_in == 3600
        assert response.token_type == "bearer"

    def test_token_response_default_token_type(self):
        """Test token_type defaults to 'bearer'."""
        response = TokenResponse(
            access_token="access.jwt.token",
            refresh_token="refresh.jwt.token",
            expires_in=3600,
        )
        assert response.token_type == "bearer"

    def test_token_response_custom_token_type(self):
        """Test custom token_type can be set."""
        response = TokenResponse(
            access_token="access.jwt.token",
            refresh_token="refresh.jwt.token",
            expires_in=3600,
            token_type="Bearer",
        )
        assert response.token_type == "Bearer"

    def test_token_response_access_token_required(self):
        """Test access_token field is required."""
        with pytest.raises(ValidationError) as exc_info:
            TokenResponse(
                refresh_token="refresh.jwt.token",
                expires_in=3600,
            )
        assert "access_token" in str(exc_info.value)

    def test_token_response_refresh_token_required(self):
        """Test refresh_token field is required."""
        with pytest.raises(ValidationError) as exc_info:
            TokenResponse(
                access_token="access.jwt.token",
                expires_in=3600,
            )
        assert "refresh_token" in str(exc_info.value)

    def test_token_response_expires_in_required(self):
        """Test expires_in field is required."""
        with pytest.raises(ValidationError) as exc_info:
            TokenResponse(
                access_token="access.jwt.token",
                refresh_token="refresh.jwt.token",
            )
        assert "expires_in" in str(exc_info.value)

    def test_token_response_expires_in_must_be_positive(self):
        """Test expires_in must be greater than 0."""
        with pytest.raises(ValidationError) as exc_info:
            TokenResponse(
                access_token="access.jwt.token",
                refresh_token="refresh.jwt.token",
                expires_in=0,
            )
        errors = exc_info.value.errors()
        assert len(errors) > 0

    def test_token_response_expires_in_negative(self):
        """Test expires_in rejects negative values."""
        with pytest.raises(ValidationError) as exc_info:
            TokenResponse(
                access_token="access.jwt.token",
                refresh_token="refresh.jwt.token",
                expires_in=-1,
            )
        errors = exc_info.value.errors()
        assert len(errors) > 0


class TestLogoutRequest:
    """Tests for LogoutRequest schema."""

    def test_valid_logout_request_with_both_tokens(self):
        """Test creating a logout request with both tokens."""
        request = LogoutRequest(
            access_token="access.jwt.token",
            refresh_token="refresh.jwt.token",
        )
        assert request.access_token == "access.jwt.token"
        assert request.refresh_token == "refresh.jwt.token"

    def test_valid_logout_request_access_token_only(self):
        """Test creating a logout request with access token only."""
        request = LogoutRequest(access_token="access.jwt.token")
        assert request.access_token == "access.jwt.token"
        assert request.refresh_token is None

    def test_logout_request_access_token_required(self):
        """Test access_token field is required."""
        with pytest.raises(ValidationError) as exc_info:
            LogoutRequest(refresh_token="refresh.jwt.token")
        assert "access_token" in str(exc_info.value)

    def test_logout_request_refresh_token_optional(self):
        """Test refresh_token field is optional."""
        request = LogoutRequest(access_token="access.jwt.token")
        assert request.refresh_token is None

    def test_logout_request_access_token_min_length(self):
        """Test access_token minimum length validation."""
        with pytest.raises(ValidationError) as exc_info:
            LogoutRequest(access_token="")
        errors = exc_info.value.errors()
        assert len(errors) > 0


class TestLogoutResponse:
    """Tests for LogoutResponse schema."""

    def test_valid_logout_response(self):
        """Test creating a valid logout response."""
        response = LogoutResponse(
            message="Logout successful",
            tokens_invalidated=2,
        )
        assert response.message == "Logout successful"
        assert response.tokens_invalidated == 2

    def test_logout_response_message_required(self):
        """Test message field is required."""
        with pytest.raises(ValidationError) as exc_info:
            LogoutResponse(tokens_invalidated=1)
        assert "message" in str(exc_info.value)

    def test_logout_response_tokens_invalidated_required(self):
        """Test tokens_invalidated field is required."""
        with pytest.raises(ValidationError) as exc_info:
            LogoutResponse(message="Logout successful")
        assert "tokens_invalidated" in str(exc_info.value)

    def test_logout_response_tokens_invalidated_must_be_non_negative(self):
        """Test tokens_invalidated must be >= 0."""
        with pytest.raises(ValidationError) as exc_info:
            LogoutResponse(
                message="Logout successful",
                tokens_invalidated=-1,
            )
        errors = exc_info.value.errors()
        assert len(errors) > 0

    def test_logout_response_zero_tokens_invalidated(self):
        """Test tokens_invalidated can be 0."""
        response = LogoutResponse(
            message="No tokens to invalidate",
            tokens_invalidated=0,
        )
        assert response.tokens_invalidated == 0


class TestPasswordResetRequest:
    """Tests for PasswordResetRequest schema."""

    def test_valid_password_reset_request(self):
        """Test creating a valid password reset request."""
        request = PasswordResetRequest(email="user@example.com")
        assert request.email == "user@example.com"

    def test_password_reset_request_email_required(self):
        """Test email field is required."""
        with pytest.raises(ValidationError) as exc_info:
            PasswordResetRequest()
        assert "email" in str(exc_info.value)

    def test_password_reset_request_invalid_email(self):
        """Test invalid email format is rejected."""
        with pytest.raises(ValidationError) as exc_info:
            PasswordResetRequest(email="not-an-email")
        assert "email" in str(exc_info.value).lower()

    def test_password_reset_request_email_whitespace_stripped(self):
        """Test whitespace is stripped from email."""
        request = PasswordResetRequest(email="  user@example.com  ")
        assert request.email.strip() == request.email


class TestPasswordResetRequestResponse:
    """Tests for PasswordResetRequestResponse schema."""

    def test_valid_password_reset_request_response(self):
        """Test creating a valid password reset request response."""
        response = PasswordResetRequestResponse(
            message="Password reset email sent",
            email="u***@example.com",
        )
        assert response.message == "Password reset email sent"
        assert response.email == "u***@example.com"

    def test_password_reset_request_response_message_required(self):
        """Test message field is required."""
        with pytest.raises(ValidationError) as exc_info:
            PasswordResetRequestResponse(email="user@example.com")
        assert "message" in str(exc_info.value)

    def test_password_reset_request_response_email_required(self):
        """Test email field is required."""
        with pytest.raises(ValidationError) as exc_info:
            PasswordResetRequestResponse(message="Password reset email sent")
        assert "email" in str(exc_info.value)


class TestPasswordResetConfirm:
    """Tests for PasswordResetConfirm schema."""

    def test_valid_password_reset_confirm(self):
        """Test creating a valid password reset confirmation."""
        confirm = PasswordResetConfirm(
            token="reset_token_abc123",
            new_password="NewSecurePass123!",
        )
        assert confirm.token == "reset_token_abc123"
        assert confirm.new_password == "NewSecurePass123!"

    def test_password_reset_confirm_token_required(self):
        """Test token field is required."""
        with pytest.raises(ValidationError) as exc_info:
            PasswordResetConfirm(new_password="NewSecurePass123!")
        assert "token" in str(exc_info.value)

    def test_password_reset_confirm_password_required(self):
        """Test new_password field is required."""
        with pytest.raises(ValidationError) as exc_info:
            PasswordResetConfirm(token="reset_token_abc123")
        assert "new_password" in str(exc_info.value)

    def test_password_reset_confirm_token_min_length(self):
        """Test token minimum length validation."""
        with pytest.raises(ValidationError) as exc_info:
            PasswordResetConfirm(
                token="",
                new_password="NewSecurePass123!",
            )
        errors = exc_info.value.errors()
        assert len(errors) > 0

    def test_password_reset_confirm_password_min_length(self):
        """Test new_password minimum length validation."""
        with pytest.raises(ValidationError) as exc_info:
            PasswordResetConfirm(
                token="reset_token_abc123",
                new_password="short",  # Less than 8 characters
            )
        errors = exc_info.value.errors()
        assert len(errors) > 0

    def test_password_reset_confirm_password_max_length(self):
        """Test new_password maximum length validation."""
        with pytest.raises(ValidationError) as exc_info:
            PasswordResetConfirm(
                token="reset_token_abc123",
                new_password="x" * 101,  # More than 100 characters
            )
        errors = exc_info.value.errors()
        assert len(errors) > 0

    def test_password_reset_confirm_password_8_chars(self):
        """Test new_password with exactly 8 characters is valid."""
        confirm = PasswordResetConfirm(
            token="reset_token_abc123",
            new_password="12345678",
        )
        assert len(confirm.new_password) == 8

    def test_password_reset_confirm_password_100_chars(self):
        """Test new_password with exactly 100 characters is valid."""
        confirm = PasswordResetConfirm(
            token="reset_token_abc123",
            new_password="x" * 100,
        )
        assert len(confirm.new_password) == 100


class TestPasswordResetConfirmResponse:
    """Tests for PasswordResetConfirmResponse schema."""

    def test_valid_password_reset_confirm_response(self):
        """Test creating a valid password reset confirmation response."""
        response = PasswordResetConfirmResponse(
            message="Password reset successful",
        )
        assert response.message == "Password reset successful"

    def test_password_reset_confirm_response_message_required(self):
        """Test message field is required."""
        with pytest.raises(ValidationError) as exc_info:
            PasswordResetConfirmResponse()
        assert "message" in str(exc_info.value)


class TestSchemaFromAttributes:
    """Tests for schema from_attributes (ORM mode) functionality."""

    def test_login_request_from_dict(self):
        """Test LoginRequest can be created from dict."""
        data = {"email": "user@example.com", "password": "SecurePass123!"}
        request = LoginRequest(**data)
        assert request.email == "user@example.com"

    def test_token_response_model_dump(self):
        """Test TokenResponse can be serialized to dict."""
        response = TokenResponse(
            access_token="access.jwt.token",
            refresh_token="refresh.jwt.token",
            expires_in=3600,
        )
        data = response.model_dump()
        assert data["access_token"] == "access.jwt.token"
        assert data["refresh_token"] == "refresh.jwt.token"
        assert data["expires_in"] == 3600
        assert data["token_type"] == "bearer"

    def test_logout_response_model_dump_json(self):
        """Test LogoutResponse can be serialized to JSON."""
        response = LogoutResponse(
            message="Logout successful",
            tokens_invalidated=2,
        )
        json_str = response.model_dump_json()
        assert "Logout successful" in json_str
        assert "2" in json_str
