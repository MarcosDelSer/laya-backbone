"""Unit tests for token blacklist operations in LAYA AI Service.

Tests Redis-based token blacklist functionality including adding tokens,
checking blacklist status, TTL management, and error handling.
"""

from __future__ import annotations

from datetime import datetime, timedelta, timezone
from unittest.mock import AsyncMock, MagicMock, patch
from uuid import uuid4

import pytest
import pytest_asyncio
from fastapi import HTTPException
from httpx import AsyncClient
from sqlalchemy.ext.asyncio import AsyncSession

from app.auth.blacklist import TokenBlacklistService
from app.auth.jwt import create_token, decode_token
from app.auth.models import User, UserRole
from app.core.security import hash_password


# ============================================================================
# Fixtures for integration testing
# ============================================================================


@pytest_asyncio.fixture
async def test_user(db_session: AsyncSession) -> User:
    """Create a test user for authentication tests.

    Creates an active teacher user with known credentials for testing login,
    token refresh, and other auth operations.

    Args:
        db_session: Test database session

    Returns:
        User: Created test user with credentials:
            - email: teacher@example.com
            - password: TestPassword123!
            - role: teacher
            - is_active: True
    """
    user = User(
        id=uuid4(),
        email="teacher@example.com",
        password_hash=hash_password("TestPassword123!"),
        first_name="Test",
        last_name="Teacher",
        role=UserRole.TEACHER,
        is_active=True,
    )
    db_session.add(user)
    await db_session.commit()
    await db_session.refresh(user)
    return user


# ============================================================================
# Unit Tests for TokenBlacklistService
# ============================================================================


class TestBlacklistAdd:
    """Tests for adding tokens to the blacklist."""

    @pytest.mark.asyncio
    async def test_blacklist_add(self):
        """Test adding a valid token to the blacklist.

        Verifies that:
        1. Token is successfully added to Redis
        2. JTI is extracted correctly
        3. TTL is calculated and set based on token expiration
        4. Returns True on success
        """
        # Create a valid token with 1 hour expiration
        user_id = str(uuid4())
        token = create_token(
            subject=user_id,
            expires_delta_seconds=3600,
            additional_claims={
                "email": "test@example.com",
                "role": "teacher",
                "type": "access",
            },
        )

        # Mock Redis client
        mock_redis = AsyncMock()
        mock_redis.setex = AsyncMock(return_value=True)

        # Create blacklist service with mocked Redis
        service = TokenBlacklistService(redis_client=mock_redis)

        # Add token to blacklist
        result = await service.add_token_to_blacklist(token)

        # Verify result
        assert result is True

        # Verify Redis setex was called
        mock_redis.setex.assert_called_once()
        call_args = mock_redis.setex.call_args[0]

        # Verify key format is "blacklist:{jti}"
        key = call_args[0]
        assert key.startswith("blacklist:")

        # Verify TTL is positive and reasonable (should be close to 3600 seconds)
        ttl = call_args[1]
        assert isinstance(ttl, int)
        assert 3500 <= ttl <= 3600  # Allow some time for execution

        # Verify value is "1"
        value = call_args[2]
        assert value == "1"

    @pytest.mark.asyncio
    async def test_blacklist_add_with_custom_expires_at(self):
        """Test adding token with custom expiration datetime.

        Verifies that custom expires_at parameter is used instead of
        extracting from token payload.
        """
        user_id = str(uuid4())
        token = create_token(
            subject=user_id,
            expires_delta_seconds=3600,
        )

        # Custom expiration 2 hours from now
        custom_expires = datetime.now(timezone.utc) + timedelta(hours=2)

        mock_redis = AsyncMock()
        mock_redis.setex = AsyncMock(return_value=True)

        service = TokenBlacklistService(redis_client=mock_redis)
        result = await service.add_token_to_blacklist(token, expires_at=custom_expires)

        assert result is True
        mock_redis.setex.assert_called_once()

        # Verify TTL is approximately 2 hours
        call_args = mock_redis.setex.call_args[0]
        ttl = call_args[1]
        assert 7100 <= ttl <= 7200  # ~2 hours in seconds

    @pytest.mark.asyncio
    async def test_blacklist_add_already_expired_token(self):
        """Test adding an already expired token to blacklist.

        Verifies that expired tokens raise an error when decoded
        (they cannot be blacklisted because decode_token validates expiration).
        """
        user_id = str(uuid4())
        # Create token that expired 1 hour ago
        token = create_token(
            subject=user_id,
            expires_delta_seconds=-3600,
        )

        mock_redis = AsyncMock()
        mock_redis.setex = AsyncMock(return_value=True)

        service = TokenBlacklistService(redis_client=mock_redis)

        # Expired tokens raise HTTPException during decode
        with pytest.raises(HTTPException) as exc_info:
            await service.add_token_to_blacklist(token)

        assert exc_info.value.status_code == 401
        assert "Invalid token" in exc_info.value.detail
        # Redis should not be called since token decode fails
        mock_redis.setex.assert_not_called()

    @pytest.mark.asyncio
    async def test_blacklist_add_token_missing_jti(self):
        """Test adding token without JTI claim raises error.

        Verifies that tokens without JTI (older tokens, invalid tokens)
        are rejected with appropriate error.
        """
        # Create token without JTI by using raw JWT encoding
        import jwt
        from app.config import settings

        payload = {
            "sub": str(uuid4()),
            "iat": int(datetime.now(timezone.utc).timestamp()),
            "exp": int((datetime.now(timezone.utc) + timedelta(hours=1)).timestamp()),
            # No "jti" claim
        }
        token = jwt.encode(payload, settings.jwt_secret_key, algorithm=settings.jwt_algorithm)

        mock_redis = AsyncMock()
        service = TokenBlacklistService(redis_client=mock_redis)

        with pytest.raises(HTTPException) as exc_info:
            await service.add_token_to_blacklist(token)

        assert exc_info.value.status_code == 401
        assert "missing JTI" in exc_info.value.detail

    @pytest.mark.asyncio
    async def test_blacklist_add_invalid_token(self):
        """Test adding invalid/malformed token raises error."""
        mock_redis = AsyncMock()
        service = TokenBlacklistService(redis_client=mock_redis)

        with pytest.raises(HTTPException) as exc_info:
            await service.add_token_to_blacklist("invalid.token.here")

        assert exc_info.value.status_code == 401
        assert "Invalid token" in exc_info.value.detail


class TestBlacklistCheck:
    """Tests for checking if tokens are blacklisted."""

    @pytest.mark.asyncio
    async def test_is_token_blacklisted_returns_true(self):
        """Test checking a blacklisted token returns True.

        Verifies that:
        1. JTI is extracted from token
        2. Redis is queried with correct key format
        3. Returns True when token exists in blacklist
        """
        user_id = str(uuid4())
        token = create_token(subject=user_id, expires_delta_seconds=3600)

        # Mock Redis to return 1 (token exists)
        mock_redis = AsyncMock()
        mock_redis.exists = AsyncMock(return_value=1)

        service = TokenBlacklistService(redis_client=mock_redis)
        result = await service.is_token_blacklisted(token)

        assert result is True
        mock_redis.exists.assert_called_once()

        # Verify correct key format
        call_args = mock_redis.exists.call_args[0]
        key = call_args[0]
        assert key.startswith("blacklist:")

    @pytest.mark.asyncio
    async def test_is_token_blacklisted_returns_false(self):
        """Test checking a non-blacklisted token returns False.

        Verifies that tokens not in the blacklist return False.
        """
        user_id = str(uuid4())
        token = create_token(subject=user_id, expires_delta_seconds=3600)

        # Mock Redis to return 0 (token does not exist)
        mock_redis = AsyncMock()
        mock_redis.exists = AsyncMock(return_value=0)

        service = TokenBlacklistService(redis_client=mock_redis)
        result = await service.is_token_blacklisted(token)

        assert result is False
        mock_redis.exists.assert_called_once()

    @pytest.mark.asyncio
    async def test_is_token_blacklisted_missing_jti(self):
        """Test checking token without JTI raises error."""
        import jwt
        from app.config import settings

        payload = {
            "sub": str(uuid4()),
            "iat": int(datetime.now(timezone.utc).timestamp()),
            "exp": int((datetime.now(timezone.utc) + timedelta(hours=1)).timestamp()),
        }
        token = jwt.encode(payload, settings.jwt_secret_key, algorithm=settings.jwt_algorithm)

        mock_redis = AsyncMock()
        service = TokenBlacklistService(redis_client=mock_redis)

        with pytest.raises(HTTPException) as exc_info:
            await service.is_token_blacklisted(token)

        assert exc_info.value.status_code == 401
        assert "missing JTI" in exc_info.value.detail

    @pytest.mark.asyncio
    async def test_is_token_blacklisted_invalid_token(self):
        """Test checking invalid token raises error."""
        mock_redis = AsyncMock()
        service = TokenBlacklistService(redis_client=mock_redis)

        with pytest.raises(HTTPException) as exc_info:
            await service.is_token_blacklisted("invalid.token.here")

        assert exc_info.value.status_code == 401
        assert "Invalid token" in exc_info.value.detail


class TestBlacklistTTL:
    """Tests for TTL (time-to-live) management in blacklist."""

    @pytest.mark.asyncio
    async def test_blacklist_ttl_matches_token_expiration(self):
        """Test that blacklist TTL matches JWT token expiration time.

        Verifies TTL calculation is accurate to prevent:
        - Tokens being blacklisted longer than necessary (memory waste)
        - Tokens expiring from blacklist before JWT expires (security issue)
        """
        user_id = str(uuid4())

        # Test various expiration times
        test_cases = [
            900,    # 15 minutes
            3600,   # 1 hour
            7200,   # 2 hours
            86400,  # 24 hours
        ]

        for expires_seconds in test_cases:
            token = create_token(subject=user_id, expires_delta_seconds=expires_seconds)

            mock_redis = AsyncMock()
            mock_redis.setex = AsyncMock(return_value=True)

            service = TokenBlacklistService(redis_client=mock_redis)
            await service.add_token_to_blacklist(token)

            # Get TTL from setex call
            call_args = mock_redis.setex.call_args[0]
            ttl = call_args[1]

            # TTL should be close to expires_seconds (allow 5 second margin for execution time)
            assert expires_seconds - 5 <= ttl <= expires_seconds, \
                f"TTL {ttl} should match expiration {expires_seconds}"

    @pytest.mark.asyncio
    async def test_blacklist_ttl_prevents_memory_leak(self):
        """Test that short-lived tokens get short TTLs.

        Verifies that tokens about to expire don't stay in Redis
        for long periods.
        """
        user_id = str(uuid4())
        # Token expires in 10 seconds
        token = create_token(subject=user_id, expires_delta_seconds=10)

        mock_redis = AsyncMock()
        mock_redis.setex = AsyncMock(return_value=True)

        service = TokenBlacklistService(redis_client=mock_redis)
        await service.add_token_to_blacklist(token)

        call_args = mock_redis.setex.call_args[0]
        ttl = call_args[1]

        # TTL should be 10 seconds or less
        assert ttl <= 10


class TestBlacklistService:
    """Tests for TokenBlacklistService lifecycle and connection management."""

    @pytest.mark.asyncio
    async def test_service_close(self):
        """Test that service properly closes Redis connection.

        Verifies resource cleanup to prevent connection leaks.
        """
        mock_redis = AsyncMock()
        mock_redis.close = AsyncMock()

        service = TokenBlacklistService(redis_client=mock_redis)
        await service.close()

        mock_redis.close.assert_called_once()

    @pytest.mark.asyncio
    async def test_service_creates_default_redis_client(self):
        """Test that service creates Redis client if not provided.

        Verifies default initialization behavior.
        """
        with patch('app.auth.blacklist.redis.Redis') as mock_redis_class:
            mock_redis_instance = AsyncMock()
            mock_redis_class.return_value = mock_redis_instance

            service = TokenBlacklistService()

            # Verify Redis client was created
            assert service.redis_client is mock_redis_instance
            mock_redis_class.assert_called_once()

    @pytest.mark.asyncio
    async def test_service_uses_provided_redis_client(self):
        """Test that service uses provided Redis client.

        Verifies dependency injection works correctly.
        """
        mock_redis = AsyncMock()
        service = TokenBlacklistService(redis_client=mock_redis)

        assert service.redis_client is mock_redis


class TestBlacklistIntegration:
    """Integration tests for complete blacklist workflows."""

    @pytest.mark.asyncio
    async def test_add_then_check_blacklisted_token(self):
        """Test complete workflow: add token to blacklist, then verify it's blacklisted.

        This simulates the logout flow:
        1. User logs out
        2. Token is added to blacklist
        3. Subsequent requests check blacklist and reject token
        """
        user_id = str(uuid4())
        token = create_token(
            subject=user_id,
            expires_delta_seconds=3600,
            additional_claims={"email": "test@example.com", "role": "teacher"},
        )

        # Mock Redis with state tracking
        blacklist_state = {}

        async def mock_setex(key: str, ttl: int, value: str):
            blacklist_state[key] = value
            return True

        async def mock_exists(key: str):
            return 1 if key in blacklist_state else 0

        mock_redis = AsyncMock()
        mock_redis.setex = AsyncMock(side_effect=mock_setex)
        mock_redis.exists = AsyncMock(side_effect=mock_exists)

        service = TokenBlacklistService(redis_client=mock_redis)

        # Initially not blacklisted
        is_blacklisted = await service.is_token_blacklisted(token)
        assert is_blacklisted is False

        # Add to blacklist
        result = await service.add_token_to_blacklist(token)
        assert result is True

        # Now should be blacklisted
        is_blacklisted = await service.is_token_blacklisted(token)
        assert is_blacklisted is True

    @pytest.mark.asyncio
    async def test_multiple_tokens_independent(self):
        """Test that multiple tokens are tracked independently.

        Verifies that blacklisting one token doesn't affect others.
        """
        user1_id = str(uuid4())
        user2_id = str(uuid4())

        token1 = create_token(subject=user1_id, expires_delta_seconds=3600)
        token2 = create_token(subject=user2_id, expires_delta_seconds=3600)

        # Mock Redis with state tracking
        blacklist_state = {}

        async def mock_setex(key: str, ttl: int, value: str):
            blacklist_state[key] = value
            return True

        async def mock_exists(key: str):
            return 1 if key in blacklist_state else 0

        mock_redis = AsyncMock()
        mock_redis.setex = AsyncMock(side_effect=mock_setex)
        mock_redis.exists = AsyncMock(side_effect=mock_exists)

        service = TokenBlacklistService(redis_client=mock_redis)

        # Blacklist only token1
        await service.add_token_to_blacklist(token1)

        # Verify token1 is blacklisted but token2 is not
        assert await service.is_token_blacklisted(token1) is True
        assert await service.is_token_blacklisted(token2) is False


class TestBlacklistEdgeCases:
    """Tests for edge cases and error conditions."""

    @pytest.mark.asyncio
    async def test_blacklist_token_near_expiration(self):
        """Test token blacklisted near expiration time.

        Verifies that tokens close to expiration get very short TTLs.
        """
        user_id = str(uuid4())
        # Token expires in 5 seconds
        token = create_token(subject=user_id, expires_delta_seconds=5)

        mock_redis = AsyncMock()
        mock_redis.setex = AsyncMock(return_value=True)

        service = TokenBlacklistService(redis_client=mock_redis)
        result = await service.add_token_to_blacklist(token)

        # Should successfully add to blacklist
        assert result is True
        mock_redis.setex.assert_called_once()

        # TTL should be very short (5 seconds or less)
        call_args = mock_redis.setex.call_args[0]
        ttl = call_args[1]
        assert 0 < ttl <= 5

    @pytest.mark.asyncio
    async def test_blacklist_refresh_token(self):
        """Test blacklisting refresh tokens (longer expiration).

        Verifies that refresh tokens with long expiration times
        are handled correctly.
        """
        user_id = str(uuid4())
        # Refresh token expires in 7 days
        token = create_token(
            subject=user_id,
            expires_delta_seconds=604800,  # 7 days
            additional_claims={"type": "refresh"},
        )

        mock_redis = AsyncMock()
        mock_redis.setex = AsyncMock(return_value=True)

        service = TokenBlacklistService(redis_client=mock_redis)
        result = await service.add_token_to_blacklist(token)

        assert result is True
        mock_redis.setex.assert_called_once()

        # Verify TTL is approximately 7 days
        call_args = mock_redis.setex.call_args[0]
        ttl = call_args[1]
        assert 604000 <= ttl <= 604800

    @pytest.mark.asyncio
    async def test_blacklist_handles_redis_errors(self):
        """Test that Redis errors are propagated correctly.

        Verifies error handling when Redis is unavailable.
        """
        user_id = str(uuid4())
        token = create_token(subject=user_id, expires_delta_seconds=3600)

        # Mock Redis to raise an error
        mock_redis = AsyncMock()
        mock_redis.setex = AsyncMock(side_effect=Exception("Redis connection failed"))

        service = TokenBlacklistService(redis_client=mock_redis)

        with pytest.raises(Exception) as exc_info:
            await service.add_token_to_blacklist(token)

        assert "Redis connection failed" in str(exc_info.value)


# ============================================================================
# Integration Tests for Logout Flow
# ============================================================================


@pytest.mark.asyncio
async def test_logout_blacklists_token(
    client: AsyncClient, test_user: User, db_session: AsyncSession
) -> None:
    """Test that logout properly blacklists tokens.

    This integration test verifies the complete logout flow:
    1. User logs in and receives access and refresh tokens
    2. Both tokens work for authentication BEFORE logout
    3. User logs out with both tokens
    4. Tokens are added to the blacklist database
    5. Tokens are rejected when used AFTER logout
    6. Error message indicates token has been revoked

    This ensures that logout provides true session termination and prevents
    token reuse attacks.
    """
    # Step 1: Login to get valid tokens
    login_response = await client.post(
        "/api/v1/auth/login",
        json={
            "email": "teacher@example.com",
            "password": "TestPassword123!",
        },
    )

    assert login_response.status_code == 200, f"Login failed with status {login_response.status_code}: {login_response.text}"
    login_data = login_response.json()
    access_token = login_data["access_token"]
    refresh_token = login_data["refresh_token"]

    # Step 2: Verify access token works BEFORE logout
    protected_response = await client.post(
        "/protected",
        headers={"Authorization": f"Bearer {access_token}"},
    )
    assert protected_response.status_code == 200, "Access token should work before logout"

    # Step 3: Verify refresh token works BEFORE logout
    refresh_response = await client.post(
        "/api/v1/auth/refresh",
        json={"refresh_token": refresh_token},
    )
    assert refresh_response.status_code == 200, "Refresh token should work before logout"

    # Step 4: Logout with both tokens
    logout_response = await client.post(
        "/api/v1/auth/logout",
        json={
            "access_token": access_token,
            "refresh_token": refresh_token,
        },
    )

    assert logout_response.status_code == 200
    logout_data = logout_response.json()

    # Verify logout response structure
    assert "message" in logout_data
    assert "tokens_invalidated" in logout_data
    assert logout_data["message"] == "Successfully logged out"
    assert logout_data["tokens_invalidated"] == 2

    # Step 5: Verify tokens were added to the blacklist database
    from sqlalchemy import text

    # Check access token is in blacklist
    result = await db_session.execute(
        text("SELECT COUNT(*) as count FROM token_blacklist WHERE token = :token"),
        {"token": access_token}
    )
    count_row = result.fetchone()
    assert count_row[0] == 1, "Access token should be in blacklist database"

    # Check refresh token is in blacklist
    result = await db_session.execute(
        text("SELECT COUNT(*) as count FROM token_blacklist WHERE token = :token"),
        {"token": refresh_token}
    )
    count_row = result.fetchone()
    assert count_row[0] == 1, "Refresh token should be in blacklist database"

    # Step 6: Verify access token is REJECTED after logout
    rejected_access_response = await client.get(
        "/protected",
        headers={"Authorization": f"Bearer {access_token}"},
    )
    assert rejected_access_response.status_code == 401, "Blacklisted access token must be rejected with 401"
    rejected_access_data = rejected_access_response.json()
    assert "detail" in rejected_access_data
    assert "revoked" in rejected_access_data["detail"].lower(), \
        f"Error should mention token revocation, got: {rejected_access_data['detail']}"

    # Step 7: Verify refresh token is REJECTED after logout
    rejected_refresh_response = await client.post(
        "/api/v1/auth/refresh",
        json={"refresh_token": refresh_token},
    )
    assert rejected_refresh_response.status_code == 401, "Blacklisted refresh token must be rejected with 401"
    rejected_refresh_data = rejected_refresh_response.json()
    assert "detail" in rejected_refresh_data
    assert "revoked" in rejected_refresh_data["detail"].lower(), \
        f"Error should mention token revocation, got: {rejected_refresh_data['detail']}"
