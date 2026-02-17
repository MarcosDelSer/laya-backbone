"""Unit tests for AuthService in LAYA AI Service.

Tests authenticate_user(), login(), logout(), refresh_tokens(),
request_password_reset(), and confirm_password_reset() methods.

Uses mocks for database operations to isolate service logic testing.
The SQLite test database doesn't support PostgreSQL ENUM types used by the User model,
so we use mocks for tests that query the User table via SQLAlchemy ORM.
"""

from datetime import datetime, timedelta, timezone
from unittest.mock import AsyncMock, MagicMock, patch
from uuid import UUID, uuid4

import pytest
import pytest_asyncio
from fastapi import HTTPException

from app.auth.service import AuthService
from app.auth.schemas import (
    LoginRequest,
    LogoutRequest,
    RefreshRequest,
    PasswordResetRequest,
    PasswordResetConfirm,
)
from app.auth.models import UserRole, User
from app.core.security import hash_password, verify_password, hash_token

from tests.auth.conftest import (
    create_token_blacklist_in_db,
    create_password_reset_token_in_db,
    create_access_token,
    create_refresh_token,
    create_test_token,
    MockUser,
    TEST_PASSWORD_PLAIN,
    TEST_PASSWORD_HASH,
)


class TestAuthServiceInit:
    """Tests for AuthService initialization."""

    def test_init_stores_db_session(self):
        """Test AuthService stores the database session."""
        mock_db = MagicMock()
        service = AuthService(mock_db)
        assert service.db == mock_db

    def test_init_has_correct_token_expiration_times(self):
        """Test AuthService has correct token expiration constants."""
        mock_db = MagicMock()
        service = AuthService(mock_db)

        # 15 minutes for access token
        assert service.ACCESS_TOKEN_EXPIRE_SECONDS == 15 * 60
        # 7 days for refresh token
        assert service.REFRESH_TOKEN_EXPIRE_SECONDS == 7 * 24 * 60 * 60
        # 1 hour for password reset token
        assert service.PASSWORD_RESET_TOKEN_EXPIRE_SECONDS == 60 * 60


class TestAuthenticateUser:
    """Tests for AuthService.authenticate_user() method using mocks."""

    @pytest.fixture
    def mock_user(self):
        """Create a mock user object with freshly hashed password."""
        user = MagicMock(spec=User)
        user.id = uuid4()
        user.email = "teacher@example.com"
        # Generate fresh hash for compatibility with current bcrypt version
        user.password_hash = hash_password(TEST_PASSWORD_PLAIN)
        user.first_name = "Test"
        user.last_name = "Teacher"
        user.role = UserRole.TEACHER
        user.is_active = True
        user.created_at = datetime.now(timezone.utc)
        user.updated_at = datetime.now(timezone.utc)
        return user

    @pytest.fixture
    def mock_inactive_user(self):
        """Create a mock inactive user object."""
        user = MagicMock(spec=User)
        user.id = uuid4()
        user.email = "inactive@example.com"
        user.password_hash = hash_password(TEST_PASSWORD_PLAIN)
        user.first_name = "Inactive"
        user.last_name = "User"
        user.role = UserRole.TEACHER
        user.is_active = False
        return user

    @pytest.mark.asyncio
    async def test_authenticate_user_success(self, mock_user):
        """Test successful user authentication."""
        mock_db = AsyncMock()
        mock_result = MagicMock()
        mock_result.scalar_one_or_none.return_value = mock_user
        mock_db.execute.return_value = mock_result

        service = AuthService(mock_db)

        user = await service.authenticate_user(
            email=mock_user.email,
            password=TEST_PASSWORD_PLAIN,
        )

        assert user is not None
        assert user.id == mock_user.id
        assert user.email == mock_user.email

    @pytest.mark.asyncio
    async def test_authenticate_user_wrong_password(self, mock_user):
        """Test authentication fails with wrong password."""
        mock_db = AsyncMock()
        mock_result = MagicMock()
        mock_result.scalar_one_or_none.return_value = mock_user
        mock_db.execute.return_value = mock_result

        service = AuthService(mock_db)

        user = await service.authenticate_user(
            email=mock_user.email,
            password="wrong_password",
        )

        assert user is None

    @pytest.mark.asyncio
    async def test_authenticate_user_nonexistent_email(self):
        """Test authentication fails for non-existent email."""
        mock_db = AsyncMock()
        mock_result = MagicMock()
        mock_result.scalar_one_or_none.return_value = None
        mock_db.execute.return_value = mock_result

        service = AuthService(mock_db)

        user = await service.authenticate_user(
            email="nonexistent@example.com",
            password="any_password",
        )

        assert user is None

    @pytest.mark.asyncio
    async def test_authenticate_user_inactive_user(self, mock_inactive_user):
        """Test authentication fails for inactive user."""
        mock_db = AsyncMock()
        mock_result = MagicMock()
        mock_result.scalar_one_or_none.return_value = mock_inactive_user
        mock_db.execute.return_value = mock_result

        service = AuthService(mock_db)

        user = await service.authenticate_user(
            email=mock_inactive_user.email,
            password=TEST_PASSWORD_PLAIN,
        )

        assert user is None


class TestGetUserById:
    """Tests for AuthService.get_user_by_id() method using mocks."""

    @pytest.fixture
    def mock_user(self):
        """Create a mock user object."""
        user = MagicMock(spec=User)
        user.id = uuid4()
        user.email = "teacher@example.com"
        user.role = UserRole.TEACHER
        return user

    @pytest.mark.asyncio
    async def test_get_user_by_id_found(self, mock_user):
        """Test getting user by valid ID."""
        mock_db = AsyncMock()
        mock_result = MagicMock()
        mock_result.scalar_one_or_none.return_value = mock_user
        mock_db.execute.return_value = mock_result

        service = AuthService(mock_db)

        user = await service.get_user_by_id(mock_user.id)

        assert user is not None
        assert user.id == mock_user.id
        assert user.email == mock_user.email

    @pytest.mark.asyncio
    async def test_get_user_by_id_not_found(self):
        """Test getting user by non-existent ID returns None."""
        mock_db = AsyncMock()
        mock_result = MagicMock()
        mock_result.scalar_one_or_none.return_value = None
        mock_db.execute.return_value = mock_result

        service = AuthService(mock_db)
        random_uuid = uuid4()

        user = await service.get_user_by_id(random_uuid)

        assert user is None


class TestLogin:
    """Tests for AuthService.login() method using mocks."""

    @pytest.fixture
    def mock_user(self):
        """Create a mock user object with freshly hashed password."""
        user = MagicMock(spec=User)
        user.id = uuid4()
        user.email = "teacher@example.com"
        user.password_hash = hash_password(TEST_PASSWORD_PLAIN)
        user.first_name = "Test"
        user.last_name = "Teacher"
        user.role = UserRole.TEACHER
        user.is_active = True
        return user

    @pytest.fixture
    def mock_admin_user(self):
        """Create a mock admin user object with freshly hashed password."""
        user = MagicMock(spec=User)
        user.id = uuid4()
        user.email = "admin@example.com"
        user.password_hash = hash_password(TEST_PASSWORD_PLAIN)
        user.first_name = "Admin"
        user.last_name = "User"
        user.role = UserRole.ADMIN
        user.is_active = True
        return user

    @pytest.fixture
    def mock_inactive_user(self):
        """Create a mock inactive user object."""
        user = MagicMock(spec=User)
        user.id = uuid4()
        user.email = "inactive@example.com"
        user.password_hash = hash_password(TEST_PASSWORD_PLAIN)
        user.role = UserRole.TEACHER
        user.is_active = False
        return user

    @pytest.mark.asyncio
    async def test_login_success(self, mock_user):
        """Test successful login returns token response."""
        mock_db = AsyncMock()
        mock_result = MagicMock()
        mock_result.scalar_one_or_none.return_value = mock_user
        mock_db.execute.return_value = mock_result

        service = AuthService(mock_db)
        login_request = LoginRequest(
            email=mock_user.email,
            password=TEST_PASSWORD_PLAIN,
        )

        response = await service.login(login_request)

        assert response.access_token is not None
        assert response.refresh_token is not None
        assert response.token_type == "bearer"
        assert response.expires_in == service.ACCESS_TOKEN_EXPIRE_SECONDS

    @pytest.mark.asyncio
    async def test_login_wrong_password_raises_401(self, mock_user):
        """Test login with wrong password raises 401."""
        mock_db = AsyncMock()
        mock_result = MagicMock()
        mock_result.scalar_one_or_none.return_value = mock_user
        mock_db.execute.return_value = mock_result

        service = AuthService(mock_db)
        login_request = LoginRequest(
            email=mock_user.email,
            password="wrong_password",
        )

        with pytest.raises(HTTPException) as exc_info:
            await service.login(login_request)

        assert exc_info.value.status_code == 401
        assert "Incorrect email or password" in exc_info.value.detail

    @pytest.mark.asyncio
    async def test_login_nonexistent_user_raises_401(self):
        """Test login with non-existent email raises 401."""
        mock_db = AsyncMock()
        mock_result = MagicMock()
        mock_result.scalar_one_or_none.return_value = None
        mock_db.execute.return_value = mock_result

        service = AuthService(mock_db)
        login_request = LoginRequest(
            email="nonexistent@example.com",
            password="any_password",
        )

        with pytest.raises(HTTPException) as exc_info:
            await service.login(login_request)

        assert exc_info.value.status_code == 401
        assert "Incorrect email or password" in exc_info.value.detail

    @pytest.mark.asyncio
    async def test_login_inactive_user_raises_401(self, mock_inactive_user):
        """Test login for inactive user raises 401."""
        mock_db = AsyncMock()
        mock_result = MagicMock()
        mock_result.scalar_one_or_none.return_value = mock_inactive_user
        mock_db.execute.return_value = mock_result

        service = AuthService(mock_db)
        login_request = LoginRequest(
            email=mock_inactive_user.email,
            password=TEST_PASSWORD_PLAIN,
        )

        with pytest.raises(HTTPException) as exc_info:
            await service.login(login_request)

        assert exc_info.value.status_code == 401

    @pytest.mark.asyncio
    async def test_login_includes_www_authenticate_header(self):
        """Test login failure includes WWW-Authenticate header."""
        mock_db = AsyncMock()
        mock_result = MagicMock()
        mock_result.scalar_one_or_none.return_value = None
        mock_db.execute.return_value = mock_result

        service = AuthService(mock_db)
        login_request = LoginRequest(
            email="invalid@example.com",
            password="password",
        )

        with pytest.raises(HTTPException) as exc_info:
            await service.login(login_request)

        assert exc_info.value.headers == {"WWW-Authenticate": "Bearer"}

    @pytest.mark.asyncio
    async def test_login_access_token_contains_user_info(self, mock_admin_user):
        """Test access token contains correct user claims."""
        from app.auth.jwt import decode_token

        mock_db = AsyncMock()
        mock_result = MagicMock()
        mock_result.scalar_one_or_none.return_value = mock_admin_user
        mock_db.execute.return_value = mock_result

        service = AuthService(mock_db)
        login_request = LoginRequest(
            email=mock_admin_user.email,
            password=TEST_PASSWORD_PLAIN,
        )

        response = await service.login(login_request)
        payload = decode_token(response.access_token)

        assert payload["sub"] == str(mock_admin_user.id)
        assert payload["email"] == mock_admin_user.email
        assert payload["role"] == mock_admin_user.role.value
        assert payload["type"] == "access"

    @pytest.mark.asyncio
    async def test_login_refresh_token_has_correct_type(self, mock_user):
        """Test refresh token has correct type claim."""
        from app.auth.jwt import decode_token

        mock_db = AsyncMock()
        mock_result = MagicMock()
        mock_result.scalar_one_or_none.return_value = mock_user
        mock_db.execute.return_value = mock_result

        service = AuthService(mock_db)
        login_request = LoginRequest(
            email=mock_user.email,
            password=TEST_PASSWORD_PLAIN,
        )

        response = await service.login(login_request)
        payload = decode_token(response.refresh_token)

        assert payload["type"] == "refresh"
        assert payload["sub"] == str(mock_user.id)


class TestIsTokenBlacklisted:
    """Tests for AuthService.is_token_blacklisted() method."""

    @pytest.mark.asyncio
    async def test_is_token_blacklisted_not_in_list(self, auth_db_session):
        """Test returns False for non-blacklisted token."""
        service = AuthService(auth_db_session)

        result = await service.is_token_blacklisted("some_random_token")

        assert result is False

    @pytest.mark.asyncio
    async def test_is_token_blacklisted_in_list(self, auth_db_session, teacher_user):
        """Test returns True for blacklisted token."""
        service = AuthService(auth_db_session)
        token = "blacklisted_token_123"

        await create_token_blacklist_in_db(
            auth_db_session,
            token=token,
            user_id=teacher_user.id,
        )

        result = await service.is_token_blacklisted(token)

        assert result is True


class TestRefreshTokens:
    """Tests for AuthService.refresh_tokens() method using mocks."""

    @pytest.fixture
    def mock_user(self):
        """Create a mock user object."""
        user = MagicMock(spec=User)
        user.id = uuid4()
        user.email = "teacher@example.com"
        user.role = UserRole.TEACHER
        user.is_active = True
        return user

    @pytest.fixture
    def mock_inactive_user(self):
        """Create a mock inactive user object."""
        user = MagicMock(spec=User)
        user.id = uuid4()
        user.email = "inactive@example.com"
        user.role = UserRole.TEACHER
        user.is_active = False
        return user

    @pytest.mark.asyncio
    async def test_refresh_tokens_success(self, mock_user):
        """Test successful token refresh."""
        mock_db = AsyncMock()

        # Mock blacklist check - not blacklisted
        mock_blacklist_result = MagicMock()
        mock_blacklist_result.scalar_one_or_none.return_value = None

        # Mock user lookup
        mock_user_result = MagicMock()
        mock_user_result.scalar_one_or_none.return_value = mock_user

        # Return different results for different queries
        mock_db.execute.side_effect = [mock_blacklist_result, mock_user_result]

        service = AuthService(mock_db)
        refresh_token = create_refresh_token(user_id=str(mock_user.id))
        refresh_request = RefreshRequest(refresh_token=refresh_token)

        response = await service.refresh_tokens(refresh_request)

        assert response.access_token is not None
        assert response.refresh_token is not None
        assert response.token_type == "bearer"
        assert response.expires_in == service.ACCESS_TOKEN_EXPIRE_SECONDS

    @pytest.mark.asyncio
    async def test_refresh_tokens_blacklisted_raises_401(self, mock_user):
        """Test refresh with blacklisted token raises 401."""
        mock_db = AsyncMock()

        # Mock blacklist check - token is blacklisted
        mock_blacklist = MagicMock()
        mock_blacklist_result = MagicMock()
        mock_blacklist_result.scalar_one_or_none.return_value = mock_blacklist
        mock_db.execute.return_value = mock_blacklist_result

        service = AuthService(mock_db)
        refresh_token = create_refresh_token(user_id=str(mock_user.id))
        refresh_request = RefreshRequest(refresh_token=refresh_token)

        with pytest.raises(HTTPException) as exc_info:
            await service.refresh_tokens(refresh_request)

        assert exc_info.value.status_code == 401
        assert "Token has been revoked" in exc_info.value.detail

    @pytest.mark.asyncio
    async def test_refresh_tokens_access_token_type_raises_401(self, mock_user):
        """Test refresh with access token type raises 401."""
        mock_db = AsyncMock()
        mock_blacklist_result = MagicMock()
        mock_blacklist_result.scalar_one_or_none.return_value = None
        mock_db.execute.return_value = mock_blacklist_result

        service = AuthService(mock_db)
        # Create an access token instead of refresh token
        access_token = create_access_token(
            user_id=str(mock_user.id),
            email=mock_user.email,
            role=mock_user.role.value,
        )
        refresh_request = RefreshRequest(refresh_token=access_token)

        with pytest.raises(HTTPException) as exc_info:
            await service.refresh_tokens(refresh_request)

        assert exc_info.value.status_code == 401
        assert "Invalid token type" in exc_info.value.detail

    @pytest.mark.asyncio
    async def test_refresh_tokens_expired_raises_401(self, mock_user):
        """Test refresh with expired token raises 401."""
        mock_db = AsyncMock()

        service = AuthService(mock_db)
        # Create expired refresh token
        expired_token = create_refresh_token(
            user_id=str(mock_user.id),
            expires_delta_seconds=-3600,  # Expired 1 hour ago
        )
        refresh_request = RefreshRequest(refresh_token=expired_token)

        with pytest.raises(HTTPException) as exc_info:
            await service.refresh_tokens(refresh_request)

        assert exc_info.value.status_code == 401

    @pytest.mark.asyncio
    async def test_refresh_tokens_nonexistent_user_raises_401(self):
        """Test refresh for deleted user raises 401."""
        mock_db = AsyncMock()

        # Mock blacklist check - not blacklisted
        mock_blacklist_result = MagicMock()
        mock_blacklist_result.scalar_one_or_none.return_value = None

        # Mock user lookup - not found
        mock_user_result = MagicMock()
        mock_user_result.scalar_one_or_none.return_value = None

        mock_db.execute.side_effect = [mock_blacklist_result, mock_user_result]

        service = AuthService(mock_db)
        fake_user_id = str(uuid4())
        refresh_token = create_refresh_token(user_id=fake_user_id)
        refresh_request = RefreshRequest(refresh_token=refresh_token)

        with pytest.raises(HTTPException) as exc_info:
            await service.refresh_tokens(refresh_request)

        assert exc_info.value.status_code == 401
        assert "User not found" in exc_info.value.detail

    @pytest.mark.asyncio
    async def test_refresh_tokens_inactive_user_raises_401(self, mock_inactive_user):
        """Test refresh for inactive user raises 401."""
        mock_db = AsyncMock()

        # Mock blacklist check - not blacklisted
        mock_blacklist_result = MagicMock()
        mock_blacklist_result.scalar_one_or_none.return_value = None

        # Mock user lookup - inactive user
        mock_user_result = MagicMock()
        mock_user_result.scalar_one_or_none.return_value = mock_inactive_user

        mock_db.execute.side_effect = [mock_blacklist_result, mock_user_result]

        service = AuthService(mock_db)
        refresh_token = create_refresh_token(user_id=str(mock_inactive_user.id))
        refresh_request = RefreshRequest(refresh_token=refresh_token)

        with pytest.raises(HTTPException) as exc_info:
            await service.refresh_tokens(refresh_request)

        assert exc_info.value.status_code == 401
        assert "User account is inactive" in exc_info.value.detail

    @pytest.mark.asyncio
    async def test_refresh_tokens_missing_subject_raises_401(self):
        """Test refresh with token missing subject raises 401."""
        mock_db = AsyncMock()
        mock_blacklist_result = MagicMock()
        mock_blacklist_result.scalar_one_or_none.return_value = None
        mock_db.execute.return_value = mock_blacklist_result

        service = AuthService(mock_db)
        # Create token without subject
        token = create_test_token(
            subject="",  # Empty subject
            additional_claims={"type": "refresh"},
        )
        refresh_request = RefreshRequest(refresh_token=token)

        with pytest.raises(HTTPException) as exc_info:
            await service.refresh_tokens(refresh_request)

        assert exc_info.value.status_code == 401

    @pytest.mark.asyncio
    async def test_refresh_tokens_invalid_uuid_raises_401(self):
        """Test refresh with invalid UUID in subject raises 401."""
        mock_db = AsyncMock()
        mock_blacklist_result = MagicMock()
        mock_blacklist_result.scalar_one_or_none.return_value = None
        mock_db.execute.return_value = mock_blacklist_result

        service = AuthService(mock_db)
        # Create token with invalid UUID
        token = create_test_token(
            subject="not-a-valid-uuid",
            additional_claims={"type": "refresh"},
        )
        refresh_request = RefreshRequest(refresh_token=token)

        with pytest.raises(HTTPException) as exc_info:
            await service.refresh_tokens(refresh_request)

        assert exc_info.value.status_code == 401
        assert "invalid user ID" in exc_info.value.detail


class TestLogout:
    """Tests for AuthService.logout() method using mocks."""

    @pytest.fixture
    def mock_user(self):
        """Create a mock user object."""
        user = MagicMock(spec=User)
        user.id = uuid4()
        user.email = "teacher@example.com"
        user.role = UserRole.TEACHER
        user.is_active = True
        return user

    @pytest.mark.asyncio
    async def test_logout_success_access_token_only(self, mock_user):
        """Test logout blacklists access token."""
        mock_db = AsyncMock()
        mock_blacklist = AsyncMock()
        mock_blacklist.add_to_blacklist = AsyncMock()

        service = AuthService(mock_db, blacklist_service=mock_blacklist)
        access_token = create_access_token(
            user_id=str(mock_user.id),
            email=mock_user.email,
            role=mock_user.role.value,
        )

        logout_request = LogoutRequest(
            access_token=access_token,
            refresh_token=None,
        )

        response = await service.logout(logout_request)

        assert response.message == "Successfully logged out"
        assert response.tokens_invalidated == 1
        mock_blacklist.add_to_blacklist.assert_called_once()

    @pytest.mark.asyncio
    async def test_logout_success_both_tokens(self, mock_user):
        """Test logout blacklists both access and refresh tokens."""
        mock_db = AsyncMock()
        mock_blacklist = AsyncMock()
        mock_blacklist.add_to_blacklist = AsyncMock()

        service = AuthService(mock_db, blacklist_service=mock_blacklist)
        access_token = create_access_token(
            user_id=str(mock_user.id),
            email=mock_user.email,
            role=mock_user.role.value,
        )
        refresh_token = create_refresh_token(user_id=str(mock_user.id))

        logout_request = LogoutRequest(
            access_token=access_token,
            refresh_token=refresh_token,
        )

        response = await service.logout(logout_request)

        assert response.message == "Successfully logged out"
        assert response.tokens_invalidated == 2
        assert mock_blacklist.add_to_blacklist.call_count == 2

    @pytest.mark.asyncio
    async def test_logout_invalid_access_token_raises_401(self):
        """Test logout with invalid access token raises 401."""
        mock_db = AsyncMock()

        service = AuthService(mock_db)

        logout_request = LogoutRequest(
            access_token="invalid.token.string",
            refresh_token=None,
        )

        with pytest.raises(HTTPException) as exc_info:
            await service.logout(logout_request)

        assert exc_info.value.status_code == 401

    @pytest.mark.asyncio
    async def test_logout_refresh_token_type_raises_401(self, mock_user):
        """Test logout with refresh token as access token raises 401."""
        mock_db = AsyncMock()

        service = AuthService(mock_db)
        refresh_token = create_refresh_token(user_id=str(mock_user.id))

        logout_request = LogoutRequest(
            access_token=refresh_token,  # Wrong token type
            refresh_token=None,
        )

        with pytest.raises(HTTPException) as exc_info:
            await service.logout(logout_request)

        assert exc_info.value.status_code == 401
        assert "Invalid token type" in exc_info.value.detail

    @pytest.mark.asyncio
    async def test_logout_ignores_invalid_refresh_token(self, mock_user):
        """Test logout succeeds even if refresh token is invalid."""
        mock_db = AsyncMock()
        mock_blacklist = AsyncMock()
        mock_blacklist.add_to_blacklist = AsyncMock()

        service = AuthService(mock_db, blacklist_service=mock_blacklist)
        access_token = create_access_token(
            user_id=str(mock_user.id),
            email=mock_user.email,
            role=mock_user.role.value,
        )

        logout_request = LogoutRequest(
            access_token=access_token,
            refresh_token="invalid.refresh.token",
        )

        response = await service.logout(logout_request)

        # Should succeed, only access token blacklisted
        assert response.message == "Successfully logged out"
        assert response.tokens_invalidated == 1

    @pytest.mark.asyncio
    async def test_logout_missing_subject_raises_401(self):
        """Test logout with token missing subject raises 401."""
        mock_db = AsyncMock()

        service = AuthService(mock_db)
        # Create token without subject
        token = create_test_token(
            subject="",
            additional_claims={"type": "access"},
        )

        logout_request = LogoutRequest(
            access_token=token,
            refresh_token=None,
        )

        with pytest.raises(HTTPException) as exc_info:
            await service.logout(logout_request)

        assert exc_info.value.status_code == 401


class TestRequestPasswordReset:
    """Tests for AuthService.request_password_reset() method using mocks."""

    @pytest.fixture
    def mock_user(self):
        """Create a mock user object."""
        user = MagicMock(spec=User)
        user.id = uuid4()
        user.email = "teacher@example.com"
        user.is_active = True
        return user

    @pytest.fixture
    def mock_inactive_user(self):
        """Create a mock inactive user object."""
        user = MagicMock(spec=User)
        user.id = uuid4()
        user.email = "inactive@example.com"
        user.is_active = False
        return user

    @pytest.mark.asyncio
    async def test_request_password_reset_existing_user(self, mock_user):
        """Test password reset request for existing user."""
        mock_db = AsyncMock()
        mock_result = MagicMock()
        mock_result.scalar_one_or_none.return_value = mock_user
        mock_db.execute.return_value = mock_result
        mock_db.commit = AsyncMock()

        service = AuthService(mock_db)
        reset_request = PasswordResetRequest(email=mock_user.email)

        response = await service.request_password_reset(reset_request)

        assert "password reset link has been sent" in response.message
        # Email should be masked
        assert "@" in response.email
        assert "***" in response.email
        mock_db.add.assert_called_once()

    @pytest.mark.asyncio
    async def test_request_password_reset_nonexistent_user(self):
        """Test password reset returns success for non-existent email (security)."""
        mock_db = AsyncMock()
        mock_result = MagicMock()
        mock_result.scalar_one_or_none.return_value = None
        mock_db.execute.return_value = mock_result

        service = AuthService(mock_db)
        reset_request = PasswordResetRequest(email="nonexistent@example.com")

        response = await service.request_password_reset(reset_request)

        # Should return same message to prevent email enumeration
        assert "password reset link has been sent" in response.message
        # No token should be added
        mock_db.add.assert_not_called()

    @pytest.mark.asyncio
    async def test_request_password_reset_inactive_user(self, mock_inactive_user):
        """Test password reset returns success for inactive user (security)."""
        mock_db = AsyncMock()
        mock_result = MagicMock()
        mock_result.scalar_one_or_none.return_value = mock_inactive_user
        mock_db.execute.return_value = mock_result

        service = AuthService(mock_db)
        reset_request = PasswordResetRequest(email=mock_inactive_user.email)

        response = await service.request_password_reset(reset_request)

        # Should return same message, but token not created
        assert "password reset link has been sent" in response.message
        mock_db.add.assert_not_called()

    @pytest.mark.asyncio
    async def test_request_password_reset_email_masking(self, mock_user):
        """Test email is properly masked in response."""
        mock_db = AsyncMock()
        mock_result = MagicMock()
        mock_result.scalar_one_or_none.return_value = mock_user
        mock_db.execute.return_value = mock_result
        mock_db.commit = AsyncMock()

        service = AuthService(mock_db)
        reset_request = PasswordResetRequest(email=mock_user.email)

        response = await service.request_password_reset(reset_request)

        # Check masking: first char + *** + @domain
        email_parts = mock_user.email.split("@")
        expected_masked = f"{email_parts[0][0]}***@{email_parts[1]}"
        assert response.email == expected_masked


class TestConfirmPasswordReset:
    """Tests for AuthService.confirm_password_reset() method using mocks."""

    @pytest.fixture
    def mock_user(self):
        """Create a mock user object with freshly hashed password."""
        user = MagicMock(spec=User)
        user.id = uuid4()
        user.email = "teacher@example.com"
        user.is_active = True
        user.password_hash = hash_password(TEST_PASSWORD_PLAIN)
        return user

    @pytest.fixture
    def mock_inactive_user(self):
        """Create a mock inactive user object."""
        user = MagicMock(spec=User)
        user.id = uuid4()
        user.email = "inactive@example.com"
        user.is_active = False
        return user

    @pytest.fixture
    def mock_reset_token(self, mock_user):
        """Create a mock password reset token object."""
        import secrets
        plain_token = secrets.token_urlsafe(32)
        token = MagicMock()
        token.token = hash_token(plain_token)
        token.user_id = mock_user.id
        token.email = mock_user.email
        token.is_used = False
        token.expires_at = datetime.now(timezone.utc) + timedelta(hours=1)
        return plain_token, token

    @pytest.mark.asyncio
    async def test_confirm_password_reset_success(self, mock_user, mock_reset_token):
        """Test successful password reset confirmation."""
        plain_token, reset_token_obj = mock_reset_token
        mock_db = AsyncMock()

        # Mock reset token lookup
        mock_reset_result = MagicMock()
        mock_reset_result.scalar_one_or_none.return_value = reset_token_obj

        # Mock user lookup
        mock_user_result = MagicMock()
        mock_user_result.scalar_one_or_none.return_value = mock_user

        mock_db.execute.side_effect = [mock_reset_result, mock_user_result]
        mock_db.commit = AsyncMock()

        service = AuthService(mock_db)
        new_password = "NewSecurePass123!@#"

        confirm_request = PasswordResetConfirm(
            token=plain_token,
            new_password=new_password,
        )

        response = await service.confirm_password_reset(confirm_request)

        assert "successfully reset" in response.message
        assert reset_token_obj.is_used is True
        mock_db.commit.assert_called_once()

    @pytest.mark.asyncio
    async def test_confirm_password_reset_invalid_token(self):
        """Test password reset with invalid token raises 400."""
        mock_db = AsyncMock()
        mock_result = MagicMock()
        mock_result.scalar_one_or_none.return_value = None
        mock_db.execute.return_value = mock_result

        service = AuthService(mock_db)

        confirm_request = PasswordResetConfirm(
            token="invalid_token_that_does_not_exist",
            new_password="NewPassword123!",
        )

        with pytest.raises(HTTPException) as exc_info:
            await service.confirm_password_reset(confirm_request)

        assert exc_info.value.status_code == 400
        assert "Invalid or expired reset token" in exc_info.value.detail

    @pytest.mark.asyncio
    async def test_confirm_password_reset_already_used(self, mock_user):
        """Test password reset with already-used token raises 400."""
        used_token = MagicMock()
        used_token.is_used = True

        mock_db = AsyncMock()
        mock_result = MagicMock()
        mock_result.scalar_one_or_none.return_value = used_token
        mock_db.execute.return_value = mock_result

        service = AuthService(mock_db)

        confirm_request = PasswordResetConfirm(
            token="any_token",
            new_password="NewPassword123!",
        )

        with pytest.raises(HTTPException) as exc_info:
            await service.confirm_password_reset(confirm_request)

        assert exc_info.value.status_code == 400
        assert "already been used" in exc_info.value.detail

    @pytest.mark.asyncio
    async def test_confirm_password_reset_expired_token(self, mock_user):
        """Test password reset with expired token raises 400."""
        expired_token = MagicMock()
        expired_token.is_used = False
        expired_token.expires_at = datetime.now(timezone.utc) - timedelta(hours=1)

        mock_db = AsyncMock()
        mock_result = MagicMock()
        mock_result.scalar_one_or_none.return_value = expired_token
        mock_db.execute.return_value = mock_result

        service = AuthService(mock_db)

        confirm_request = PasswordResetConfirm(
            token="any_token",
            new_password="NewPassword123!",
        )

        with pytest.raises(HTTPException) as exc_info:
            await service.confirm_password_reset(confirm_request)

        assert exc_info.value.status_code == 400
        assert "expired" in exc_info.value.detail

    @pytest.mark.asyncio
    async def test_confirm_password_reset_user_not_found(self):
        """Test password reset for deleted user raises 400."""
        valid_token = MagicMock()
        valid_token.is_used = False
        valid_token.expires_at = datetime.now(timezone.utc) + timedelta(hours=1)
        valid_token.user_id = uuid4()

        mock_db = AsyncMock()

        # Mock token lookup
        mock_token_result = MagicMock()
        mock_token_result.scalar_one_or_none.return_value = valid_token

        # Mock user lookup - not found
        mock_user_result = MagicMock()
        mock_user_result.scalar_one_or_none.return_value = None

        mock_db.execute.side_effect = [mock_token_result, mock_user_result]

        service = AuthService(mock_db)

        confirm_request = PasswordResetConfirm(
            token="any_token",
            new_password="NewPassword123!",
        )

        with pytest.raises(HTTPException) as exc_info:
            await service.confirm_password_reset(confirm_request)

        assert exc_info.value.status_code == 400
        assert "User not found" in exc_info.value.detail

    @pytest.mark.asyncio
    async def test_confirm_password_reset_inactive_user(self, mock_inactive_user):
        """Test password reset for inactive user raises 400."""
        valid_token = MagicMock()
        valid_token.is_used = False
        valid_token.expires_at = datetime.now(timezone.utc) + timedelta(hours=1)
        valid_token.user_id = mock_inactive_user.id

        mock_db = AsyncMock()

        mock_token_result = MagicMock()
        mock_token_result.scalar_one_or_none.return_value = valid_token

        mock_user_result = MagicMock()
        mock_user_result.scalar_one_or_none.return_value = mock_inactive_user

        mock_db.execute.side_effect = [mock_token_result, mock_user_result]

        service = AuthService(mock_db)

        confirm_request = PasswordResetConfirm(
            token="any_token",
            new_password="NewPassword123!",
        )

        with pytest.raises(HTTPException) as exc_info:
            await service.confirm_password_reset(confirm_request)

        assert exc_info.value.status_code == 400
        assert "inactive" in exc_info.value.detail


class TestAuthServiceIntegration:
    """Integration tests for complete auth flows using mocks."""

    @pytest.fixture
    def mock_user(self):
        """Create a mock user object with freshly hashed password."""
        user = MagicMock(spec=User)
        user.id = uuid4()
        user.email = "teacher@example.com"
        user.password_hash = hash_password(TEST_PASSWORD_PLAIN)
        user.role = UserRole.TEACHER
        user.is_active = True
        return user

    @pytest.mark.asyncio
    async def test_login_logout_flow(self, mock_user):
        """Test complete login -> logout flow."""
        mock_db = AsyncMock()
        mock_result = MagicMock()
        mock_result.scalar_one_or_none.return_value = mock_user
        mock_db.execute.return_value = mock_result
        mock_db.commit = AsyncMock()

        mock_blacklist = AsyncMock()
        mock_blacklist.add_to_blacklist = AsyncMock()

        service = AuthService(mock_db, blacklist_service=mock_blacklist)

        # Login
        login_request = LoginRequest(
            email=mock_user.email,
            password=TEST_PASSWORD_PLAIN,
        )
        login_response = await service.login(login_request)

        assert login_response.access_token is not None
        assert login_response.refresh_token is not None

        # Logout
        logout_request = LogoutRequest(
            access_token=login_response.access_token,
            refresh_token=login_response.refresh_token,
        )
        logout_response = await service.logout(logout_request)

        assert logout_response.tokens_invalidated == 2
        assert mock_blacklist.add_to_blacklist.call_count == 2

    @pytest.mark.asyncio
    async def test_login_refresh_flow(self, mock_user):
        """Test login -> refresh flow produces new tokens."""
        import asyncio

        mock_db = AsyncMock()

        # For login
        mock_login_result = MagicMock()
        mock_login_result.scalar_one_or_none.return_value = mock_user

        # For refresh - blacklist check
        mock_blacklist_result = MagicMock()
        mock_blacklist_result.scalar_one_or_none.return_value = None

        # For refresh - user lookup
        mock_user_result = MagicMock()
        mock_user_result.scalar_one_or_none.return_value = mock_user

        mock_db.execute.side_effect = [
            mock_login_result,  # login
            mock_blacklist_result,  # refresh blacklist check
            mock_user_result,  # refresh user lookup
        ]
        mock_db.commit = AsyncMock()

        service = AuthService(mock_db)

        # Login
        login_request = LoginRequest(
            email=mock_user.email,
            password=TEST_PASSWORD_PLAIN,
        )
        login_response = await service.login(login_request)

        # Wait a bit to ensure different timestamp (tokens include iat in seconds)
        await asyncio.sleep(1.1)

        # Refresh
        refresh_request = RefreshRequest(refresh_token=login_response.refresh_token)
        refresh_response = await service.refresh_tokens(refresh_request)

        # New tokens should be different (due to different iat timestamp)
        assert refresh_response.access_token != login_response.access_token
        assert refresh_response.refresh_token != login_response.refresh_token

    @pytest.mark.asyncio
    async def test_all_user_roles_can_login(self):
        """Test all user roles can successfully log in."""
        roles = [
            UserRole.ADMIN,
            UserRole.TEACHER,
            UserRole.PARENT,
            UserRole.ACCOUNTANT,
            UserRole.STAFF,
        ]

        # Generate hash once for performance
        password_hash = hash_password(TEST_PASSWORD_PLAIN)

        for role in roles:
            mock_user = MagicMock(spec=User)
            mock_user.id = uuid4()
            mock_user.email = f"{role.value}@example.com"
            mock_user.password_hash = password_hash
            mock_user.role = role
            mock_user.is_active = True

            mock_db = AsyncMock()
            mock_result = MagicMock()
            mock_result.scalar_one_or_none.return_value = mock_user
            mock_db.execute.return_value = mock_result

            service = AuthService(mock_db)

            login_request = LoginRequest(
                email=mock_user.email,
                password=TEST_PASSWORD_PLAIN,
            )
            response = await service.login(login_request)

            assert response.access_token is not None
            assert response.refresh_token is not None

            # Verify role is in the token
            from app.auth.jwt import decode_token
            payload = decode_token(response.access_token)
            assert payload["role"] == role.value
