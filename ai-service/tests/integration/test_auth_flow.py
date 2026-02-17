"""Integration tests for complete authentication flow in LAYA AI Service.

Tests end-to-end authentication scenarios including:
- Login -> use token -> logout -> token rejected
- Login -> admin revokes token -> token rejected
- Login -> refresh token -> use new token
- Token blacklist integration with Redis
- Authentication middleware flow
"""

from datetime import datetime, timedelta, timezone
from unittest.mock import AsyncMock, MagicMock, patch
from uuid import uuid4

import pytest
import pytest_asyncio
from fastapi import HTTPException

from app.auth.service import AuthService
from app.auth.blacklist import TokenBlacklistService
from app.auth.jwt import decode_token
from app.auth.schemas import (
    LoginRequest,
    LogoutRequest,
    RefreshRequest,
    RevokeTokenRequest,
)
from app.auth.models import User, UserRole
from app.core.security import hash_password

from tests.auth.conftest import (
    create_access_token,
    create_refresh_token,
    TEST_PASSWORD_PLAIN,
)


# ============================================================================
# Test Fixtures
# ============================================================================


@pytest.fixture
def mock_teacher_user():
    """Create a mock teacher user for testing."""
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
def mock_admin_user():
    """Create a mock admin user for testing."""
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
def mock_blacklist_service():
    """Create a mock blacklist service."""
    service = AsyncMock(spec=TokenBlacklistService)
    service.is_blacklisted = AsyncMock(return_value=False)
    service.add_to_blacklist = AsyncMock(return_value=True)
    service.get_blacklist_info = AsyncMock(return_value=None)
    return service


@pytest.fixture
def mock_db_session():
    """Create a mock database session."""
    session = AsyncMock()
    session.commit = AsyncMock()
    session.add = MagicMock()
    return session


# ============================================================================
# Test: Login -> Use Token -> Logout -> Token Rejected
# ============================================================================


class TestLoginLogoutFlow:
    """Integration tests for login -> logout flow."""

    @pytest.mark.asyncio
    async def test_login_logout_flow_success(
        self, mock_teacher_user, mock_db_session, mock_blacklist_service
    ):
        """Test complete login -> logout flow."""
        # Setup mock database to return user
        mock_result = MagicMock()
        mock_result.scalar_one_or_none.return_value = mock_teacher_user
        mock_db_session.execute.return_value = mock_result

        service = AuthService(mock_db_session, blacklist_service=mock_blacklist_service)

        # Step 1: Login
        login_request = LoginRequest(
            email=mock_teacher_user.email,
            password=TEST_PASSWORD_PLAIN,
        )
        login_response = await service.login(login_request)

        assert login_response.access_token is not None
        assert login_response.refresh_token is not None
        assert login_response.token_type == "bearer"
        assert login_response.expires_in == service.ACCESS_TOKEN_EXPIRE_SECONDS

        # Verify tokens are valid
        access_payload = decode_token(login_response.access_token)
        assert access_payload["sub"] == str(mock_teacher_user.id)
        assert access_payload["type"] == "access"

        refresh_payload = decode_token(login_response.refresh_token)
        assert refresh_payload["sub"] == str(mock_teacher_user.id)
        assert refresh_payload["type"] == "refresh"

        # Step 2: Logout
        logout_request = LogoutRequest(
            access_token=login_response.access_token,
            refresh_token=login_response.refresh_token,
        )
        logout_response = await service.logout(logout_request)

        assert logout_response.message == "Successfully logged out"
        assert logout_response.tokens_invalidated == 2
        assert mock_blacklist_service.add_to_blacklist.call_count == 2

        # Step 3: Verify tokens were blacklisted
        blacklist_calls = mock_blacklist_service.add_to_blacklist.call_args_list
        assert len(blacklist_calls) == 2

        # First call should be access token
        access_call = blacklist_calls[0]
        assert access_call[1]["token"] == login_response.access_token
        assert access_call[1]["user_id"] == str(mock_teacher_user.id)

        # Second call should be refresh token
        refresh_call = blacklist_calls[1]
        assert refresh_call[1]["token"] == login_response.refresh_token
        assert refresh_call[1]["user_id"] == str(mock_teacher_user.id)

    @pytest.mark.asyncio
    async def test_logout_only_access_token(
        self, mock_teacher_user, mock_db_session, mock_blacklist_service
    ):
        """Test logout with only access token (no refresh token)."""
        mock_result = MagicMock()
        mock_result.scalar_one_or_none.return_value = mock_teacher_user
        mock_db_session.execute.return_value = mock_result

        service = AuthService(mock_db_session, blacklist_service=mock_blacklist_service)

        # Login
        login_request = LoginRequest(
            email=mock_teacher_user.email,
            password=TEST_PASSWORD_PLAIN,
        )
        login_response = await service.login(login_request)

        # Logout with only access token
        logout_request = LogoutRequest(
            access_token=login_response.access_token,
            refresh_token=None,
        )
        logout_response = await service.logout(logout_request)

        assert logout_response.message == "Successfully logged out"
        assert logout_response.tokens_invalidated == 1
        assert mock_blacklist_service.add_to_blacklist.call_count == 1


# ============================================================================
# Test: Login -> Admin Revokes Token -> Token Rejected
# ============================================================================


class TestAdminTokenRevocation:
    """Integration tests for admin token revocation flow."""

    @pytest.mark.asyncio
    async def test_admin_revoke_user_token(
        self, mock_teacher_user, mock_db_session, mock_blacklist_service
    ):
        """Test admin can revoke user's token."""
        # Setup mock database to return user
        mock_result = MagicMock()
        mock_result.scalar_one_or_none.return_value = mock_teacher_user
        mock_db_session.execute.return_value = mock_result

        service = AuthService(mock_db_session, blacklist_service=mock_blacklist_service)

        # Step 1: User logs in
        login_request = LoginRequest(
            email=mock_teacher_user.email,
            password=TEST_PASSWORD_PLAIN,
        )
        login_response = await service.login(login_request)

        assert login_response.access_token is not None

        # Step 2: Admin revokes token
        revoke_request = RevokeTokenRequest(
            token=login_response.access_token,
            reason="Security incident - suspected compromise",
        )
        revoke_response = await service.revoke_token(revoke_request)

        assert "successfully revoked" in revoke_response.message
        assert revoke_response.token_id == str(mock_teacher_user.id)
        assert revoke_response.revoked_at is not None

        # Step 3: Verify token was blacklisted
        mock_blacklist_service.add_to_blacklist.assert_called_once()
        blacklist_call = mock_blacklist_service.add_to_blacklist.call_args
        assert blacklist_call[1]["token"] == login_response.access_token
        assert blacklist_call[1]["user_id"] == str(mock_teacher_user.id)

    @pytest.mark.asyncio
    async def test_revoke_invalid_token_raises_401(
        self, mock_db_session, mock_blacklist_service
    ):
        """Test revoking invalid token raises 401."""
        service = AuthService(mock_db_session, blacklist_service=mock_blacklist_service)

        revoke_request = RevokeTokenRequest(
            token="invalid.token.string",
            reason="Test revocation",
        )

        with pytest.raises(HTTPException) as exc_info:
            await service.revoke_token(revoke_request)

        assert exc_info.value.status_code == 401

    @pytest.mark.asyncio
    async def test_revoke_expired_token_raises_401(
        self, mock_teacher_user, mock_db_session, mock_blacklist_service
    ):
        """Test revoking expired token raises 401."""
        service = AuthService(mock_db_session, blacklist_service=mock_blacklist_service)

        # Create an expired token
        expired_token = create_access_token(
            user_id=str(mock_teacher_user.id),
            email=mock_teacher_user.email,
            role=mock_teacher_user.role.value,
            expires_delta_seconds=-3600,  # Expired 1 hour ago
        )

        revoke_request = RevokeTokenRequest(
            token=expired_token,
            reason="Test revocation",
        )

        with pytest.raises(HTTPException) as exc_info:
            await service.revoke_token(revoke_request)

        assert exc_info.value.status_code == 401


# ============================================================================
# Test: Login -> Refresh Token -> Use New Token
# ============================================================================


class TestTokenRefreshFlow:
    """Integration tests for token refresh flow."""

    @pytest.mark.asyncio
    async def test_login_refresh_flow(
        self, mock_teacher_user, mock_db_session, mock_blacklist_service
    ):
        """Test login -> refresh flow produces new tokens."""
        import asyncio

        # Setup mock database for login
        mock_login_result = MagicMock()
        mock_login_result.scalar_one_or_none.return_value = mock_teacher_user

        # Setup mock for refresh - blacklist check
        mock_blacklist_result = MagicMock()
        mock_blacklist_result.scalar_one_or_none.return_value = None

        # Setup mock for refresh - user lookup
        mock_user_result = MagicMock()
        mock_user_result.scalar_one_or_none.return_value = mock_teacher_user

        mock_db_session.execute.side_effect = [
            mock_login_result,  # login
            mock_blacklist_result,  # refresh blacklist check
            mock_user_result,  # refresh user lookup
        ]

        service = AuthService(mock_db_session, blacklist_service=mock_blacklist_service)

        # Step 1: Login
        login_request = LoginRequest(
            email=mock_teacher_user.email,
            password=TEST_PASSWORD_PLAIN,
        )
        login_response = await service.login(login_request)

        assert login_response.access_token is not None
        assert login_response.refresh_token is not None

        # Wait to ensure different timestamp (tokens include iat in seconds)
        await asyncio.sleep(1.1)

        # Step 2: Refresh tokens
        refresh_request = RefreshRequest(refresh_token=login_response.refresh_token)
        refresh_response = await service.refresh_tokens(refresh_request)

        assert refresh_response.access_token is not None
        assert refresh_response.refresh_token is not None
        assert refresh_response.token_type == "bearer"

        # Step 3: Verify new tokens are different
        assert refresh_response.access_token != login_response.access_token
        assert refresh_response.refresh_token != login_response.refresh_token

        # Step 4: Verify new tokens are valid
        new_access_payload = decode_token(refresh_response.access_token)
        assert new_access_payload["sub"] == str(mock_teacher_user.id)
        assert new_access_payload["type"] == "access"

        new_refresh_payload = decode_token(refresh_response.refresh_token)
        assert new_refresh_payload["sub"] == str(mock_teacher_user.id)
        assert new_refresh_payload["type"] == "refresh"

    @pytest.mark.asyncio
    async def test_refresh_with_blacklisted_token_raises_401(
        self, mock_teacher_user, mock_db_session, mock_blacklist_service
    ):
        """Test refresh with blacklisted token raises 401."""
        # Mock blacklist to return blacklisted
        mock_blacklist_result = MagicMock()
        mock_blacklist_result.scalar_one_or_none.return_value = MagicMock()
        mock_db_session.execute.return_value = mock_blacklist_result

        service = AuthService(mock_db_session, blacklist_service=mock_blacklist_service)

        refresh_token = create_refresh_token(user_id=str(mock_teacher_user.id))
        refresh_request = RefreshRequest(refresh_token=refresh_token)

        with pytest.raises(HTTPException) as exc_info:
            await service.refresh_tokens(refresh_request)

        assert exc_info.value.status_code == 401
        assert "Token has been revoked" in exc_info.value.detail

    @pytest.mark.asyncio
    async def test_refresh_with_access_token_raises_401(
        self, mock_teacher_user, mock_db_session, mock_blacklist_service
    ):
        """Test refresh with access token (wrong type) raises 401."""
        mock_blacklist_result = MagicMock()
        mock_blacklist_result.scalar_one_or_none.return_value = None
        mock_db_session.execute.return_value = mock_blacklist_result

        service = AuthService(mock_db_session, blacklist_service=mock_blacklist_service)

        # Try to refresh with access token instead of refresh token
        access_token = create_access_token(
            user_id=str(mock_teacher_user.id),
            email=mock_teacher_user.email,
            role=mock_teacher_user.role.value,
        )
        refresh_request = RefreshRequest(refresh_token=access_token)

        with pytest.raises(HTTPException) as exc_info:
            await service.refresh_tokens(refresh_request)

        assert exc_info.value.status_code == 401
        assert "Invalid token type" in exc_info.value.detail


# ============================================================================
# Test: Blacklist Integration
# ============================================================================


class TestBlacklistIntegration:
    """Integration tests for token blacklist functionality."""

    @pytest.mark.asyncio
    async def test_blacklisted_token_rejected(
        self, mock_teacher_user, mock_db_session
    ):
        """Test that blacklisted tokens are properly rejected."""
        # Create a real blacklist service for this test
        mock_redis = AsyncMock()
        mock_redis.get = AsyncMock(return_value=b"user123:2024-01-01T00:00:00Z")
        blacklist_service = TokenBlacklistService(redis_client=mock_redis)

        service = AuthService(mock_db_session, blacklist_service=blacklist_service)

        # Create a token
        token = create_access_token(
            user_id=str(mock_teacher_user.id),
            email=mock_teacher_user.email,
            role=mock_teacher_user.role.value,
        )

        # Blacklist the token
        expires_at = datetime.now(timezone.utc) + timedelta(hours=1)
        await blacklist_service.add_to_blacklist(
            token=token,
            user_id=str(mock_teacher_user.id),
            expires_at=expires_at,
        )

        # Verify token was blacklisted
        is_blacklisted = await blacklist_service.is_blacklisted(token)
        assert is_blacklisted is True

    @pytest.mark.asyncio
    async def test_non_blacklisted_token_accepted(
        self, mock_teacher_user, mock_db_session
    ):
        """Test that non-blacklisted tokens are accepted."""
        # Create a real blacklist service for this test
        mock_redis = AsyncMock()
        mock_redis.get = AsyncMock(return_value=None)  # Not blacklisted
        blacklist_service = TokenBlacklistService(redis_client=mock_redis)

        service = AuthService(mock_db_session, blacklist_service=blacklist_service)

        # Create a token
        token = create_access_token(
            user_id=str(mock_teacher_user.id),
            email=mock_teacher_user.email,
            role=mock_teacher_user.role.value,
        )

        # Verify token is not blacklisted
        is_blacklisted = await blacklist_service.is_blacklisted(token)
        assert is_blacklisted is False

    @pytest.mark.asyncio
    async def test_blacklist_stores_user_id_and_timestamp(
        self, mock_teacher_user, mock_db_session
    ):
        """Test that blacklist stores user_id and timestamp."""
        mock_redis = AsyncMock()
        mock_redis.setex = AsyncMock(return_value=True)
        blacklist_service = TokenBlacklistService(redis_client=mock_redis)

        service = AuthService(mock_db_session, blacklist_service=blacklist_service)

        token = create_access_token(
            user_id=str(mock_teacher_user.id),
            email=mock_teacher_user.email,
            role=mock_teacher_user.role.value,
        )

        expires_at = datetime.now(timezone.utc) + timedelta(hours=1)
        await blacklist_service.add_to_blacklist(
            token=token,
            user_id=str(mock_teacher_user.id),
            expires_at=expires_at,
        )

        # Verify SETEX was called
        mock_redis.setex.assert_called_once()
        call_args = mock_redis.setex.call_args

        # Check key
        key = call_args[0][0]
        assert key == f"blacklist:{token}"

        # Check value includes user_id
        value = call_args[0][2]
        assert value.startswith(str(mock_teacher_user.id))


# ============================================================================
# Test: Complete Integration Scenarios
# ============================================================================


class TestCompleteAuthFlows:
    """Integration tests for complete authentication scenarios."""

    @pytest.mark.asyncio
    async def test_multiple_users_independent_tokens(
        self, mock_teacher_user, mock_admin_user, mock_db_session, mock_blacklist_service
    ):
        """Test multiple users can authenticate independently."""
        service = AuthService(mock_db_session, blacklist_service=mock_blacklist_service)

        # Setup mock to return different users
        teacher_result = MagicMock()
        teacher_result.scalar_one_or_none.return_value = mock_teacher_user

        admin_result = MagicMock()
        admin_result.scalar_one_or_none.return_value = mock_admin_user

        mock_db_session.execute.side_effect = [teacher_result, admin_result]

        # Teacher login
        teacher_login = LoginRequest(
            email=mock_teacher_user.email,
            password=TEST_PASSWORD_PLAIN,
        )
        teacher_response = await service.login(teacher_login)

        # Admin login
        admin_login = LoginRequest(
            email=mock_admin_user.email,
            password=TEST_PASSWORD_PLAIN,
        )
        admin_response = await service.login(admin_login)

        # Verify different tokens
        assert teacher_response.access_token != admin_response.access_token
        assert teacher_response.refresh_token != admin_response.refresh_token

        # Verify tokens contain correct user info
        teacher_payload = decode_token(teacher_response.access_token)
        assert teacher_payload["sub"] == str(mock_teacher_user.id)
        assert teacher_payload["role"] == UserRole.TEACHER.value

        admin_payload = decode_token(admin_response.access_token)
        assert admin_payload["sub"] == str(mock_admin_user.id)
        assert admin_payload["role"] == UserRole.ADMIN.value

    @pytest.mark.asyncio
    async def test_logout_one_user_does_not_affect_other(
        self, mock_teacher_user, mock_admin_user, mock_db_session, mock_blacklist_service
    ):
        """Test logging out one user doesn't affect other user's tokens."""
        service = AuthService(mock_db_session, blacklist_service=mock_blacklist_service)

        # Setup mock to return different users
        teacher_result = MagicMock()
        teacher_result.scalar_one_or_none.return_value = mock_teacher_user

        admin_result = MagicMock()
        admin_result.scalar_one_or_none.return_value = mock_admin_user

        mock_db_session.execute.side_effect = [teacher_result, admin_result]

        # Both users login
        teacher_login = LoginRequest(
            email=mock_teacher_user.email,
            password=TEST_PASSWORD_PLAIN,
        )
        teacher_response = await service.login(teacher_login)

        admin_login = LoginRequest(
            email=mock_admin_user.email,
            password=TEST_PASSWORD_PLAIN,
        )
        admin_response = await service.login(admin_login)

        # Teacher logs out
        teacher_logout = LogoutRequest(
            access_token=teacher_response.access_token,
            refresh_token=teacher_response.refresh_token,
        )
        await service.logout(teacher_logout)

        # Verify teacher's tokens were blacklisted
        blacklist_calls = mock_blacklist_service.add_to_blacklist.call_args_list
        assert len(blacklist_calls) == 2

        # Verify blacklist was called with teacher's tokens
        teacher_tokens = [
            call[1]["token"] for call in blacklist_calls
        ]
        assert teacher_response.access_token in teacher_tokens
        assert teacher_response.refresh_token in teacher_tokens

        # Admin's tokens should not be in blacklist calls
        assert admin_response.access_token not in teacher_tokens
        assert admin_response.refresh_token not in teacher_tokens

    @pytest.mark.asyncio
    async def test_all_user_roles_can_complete_auth_flow(
        self, mock_db_session, mock_blacklist_service
    ):
        """Test all user roles can complete full auth flow."""
        roles = [
            UserRole.ADMIN,
            UserRole.TEACHER,
            UserRole.PARENT,
            UserRole.ACCOUNTANT,
            UserRole.STAFF,
        ]

        password_hash = hash_password(TEST_PASSWORD_PLAIN)

        for role in roles:
            # Create mock user for this role
            user = MagicMock(spec=User)
            user.id = uuid4()
            user.email = f"{role.value}@example.com"
            user.password_hash = password_hash
            user.role = role
            user.is_active = True

            # Setup mock database
            mock_result = MagicMock()
            mock_result.scalar_one_or_none.return_value = user
            mock_db_session.execute.return_value = mock_result

            service = AuthService(mock_db_session, blacklist_service=mock_blacklist_service)

            # Login
            login_request = LoginRequest(
                email=user.email,
                password=TEST_PASSWORD_PLAIN,
            )
            login_response = await service.login(login_request)

            assert login_response.access_token is not None
            assert login_response.refresh_token is not None

            # Verify token contains role
            payload = decode_token(login_response.access_token)
            assert payload["role"] == role.value

            # Logout
            logout_request = LogoutRequest(
                access_token=login_response.access_token,
                refresh_token=None,
            )
            logout_response = await service.logout(logout_request)

            assert logout_response.message == "Successfully logged out"
