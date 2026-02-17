"""Unit tests for authentication blacklist middleware.

Tests token blacklist functionality in the authentication middleware,
including two-tier blacklist checking (Redis and PostgreSQL), token
validation, and error handling.

Uses mocks for database and Redis operations to isolate middleware logic.
"""

from datetime import datetime, timedelta, timezone
from unittest.mock import AsyncMock, MagicMock, patch
from uuid import uuid4

import pytest
import pytest_asyncio
from fastapi import HTTPException
from fastapi.security import HTTPAuthorizationCredentials

from app.middleware.auth import verify_token_from_any_source
from app.auth.models import UserRole

from tests.auth.conftest import (
    create_access_token,
    create_refresh_token,
    create_test_token,
    create_token_blacklist_in_db,
    MockUser,
)


class TestVerifyTokenFromAnySource:
    """Tests for verify_token_from_any_source middleware function."""

    @pytest.fixture
    def mock_user(self):
        """Create a mock user object."""
        user = MagicMock(spec=MockUser)
        user.id = uuid4()
        user.email = "teacher@example.com"
        user.role = UserRole.TEACHER
        user.is_active = True
        return user

    @pytest.fixture
    def valid_credentials(self, mock_user):
        """Create valid HTTP authorization credentials."""
        token = create_access_token(
            user_id=str(mock_user.id),
            email=mock_user.email,
            role=mock_user.role.value,
        )
        credentials = HTTPAuthorizationCredentials(
            scheme="Bearer",
            credentials=token,
        )
        return credentials

    @pytest.mark.asyncio
    async def test_valid_token_accepted(self, valid_credentials, mock_user):
        """Test valid non-blacklisted token is accepted."""
        mock_db = AsyncMock()

        # Mock blacklist check - token not found
        mock_blacklist_result = MagicMock()
        mock_blacklist_result.scalar_one_or_none.return_value = None
        mock_db.execute.return_value = mock_blacklist_result

        # Mock Redis - token not in Redis blacklist
        mock_redis = AsyncMock()
        mock_redis.get.return_value = None

        payload = await verify_token_from_any_source(
            credentials=valid_credentials,
            db=mock_db,
            redis_client=mock_redis,
            request=None,
        )

        assert payload is not None
        assert payload["sub"] == str(mock_user.id)
        assert payload["email"] == mock_user.email
        assert payload["role"] == mock_user.role.value
        assert payload["type"] == "access"

    @pytest.mark.asyncio
    async def test_blacklisted_token_rejected_postgres(self, valid_credentials):
        """Test blacklisted token in PostgreSQL is rejected."""
        mock_db = AsyncMock()

        # Mock blacklist check - token found in database
        mock_blacklist_entry = MagicMock()
        mock_blacklist_result = MagicMock()
        mock_blacklist_result.scalar_one_or_none.return_value = mock_blacklist_entry
        mock_db.execute.return_value = mock_blacklist_result

        # Mock Redis - token not in Redis (test DB fallback)
        mock_redis = AsyncMock()
        mock_redis.get.return_value = None

        with pytest.raises(HTTPException) as exc_info:
            await verify_token_from_any_source(
                credentials=valid_credentials,
                db=mock_db,
                redis_client=mock_redis,
                request=None,
            )

        assert exc_info.value.status_code == 401
        assert "Token has been revoked" in exc_info.value.detail
        assert exc_info.value.headers == {"WWW-Authenticate": "Bearer"}

    @pytest.mark.asyncio
    async def test_blacklisted_token_rejected_redis(self, valid_credentials):
        """Test blacklisted token in Redis is rejected."""
        mock_db = AsyncMock()

        # Mock Redis - token found in Redis blacklist
        mock_redis = AsyncMock()
        mock_redis.get.return_value = "1"  # Any non-None value indicates blacklisted

        # Database should not be queried if Redis cache hit
        with pytest.raises(HTTPException) as exc_info:
            await verify_token_from_any_source(
                credentials=valid_credentials,
                db=mock_db,
                redis_client=mock_redis,
                request=None,
            )

        assert exc_info.value.status_code == 401
        assert "Token has been revoked" in exc_info.value.detail
        # Verify database was not called (Redis cache hit)
        mock_db.execute.assert_not_called()

    @pytest.mark.asyncio
    async def test_expired_token_rejected(self, mock_user):
        """Test expired token is rejected."""
        # Create expired token
        expired_token = create_access_token(
            user_id=str(mock_user.id),
            email=mock_user.email,
            role=mock_user.role.value,
            expires_delta_seconds=-3600,  # Expired 1 hour ago
        )

        credentials = HTTPAuthorizationCredentials(
            scheme="Bearer",
            credentials=expired_token,
        )

        mock_db = AsyncMock()
        mock_redis = AsyncMock()
        mock_redis.get.return_value = None

        with pytest.raises(HTTPException) as exc_info:
            await verify_token_from_any_source(
                credentials=credentials,
                db=mock_db,
                redis_client=mock_redis,
                request=None,
            )

        assert exc_info.value.status_code == 401
        assert "Token has expired" in exc_info.value.detail

    @pytest.mark.asyncio
    async def test_invalid_token_rejected(self):
        """Test invalid/malformed token is rejected."""
        credentials = HTTPAuthorizationCredentials(
            scheme="Bearer",
            credentials="invalid.token.string",
        )

        mock_db = AsyncMock()
        mock_redis = AsyncMock()

        with pytest.raises(HTTPException) as exc_info:
            await verify_token_from_any_source(
                credentials=credentials,
                db=mock_db,
                redis_client=mock_redis,
                request=None,
            )

        assert exc_info.value.status_code == 401
        assert "Invalid authentication token" in exc_info.value.detail

    @pytest.mark.asyncio
    async def test_token_wrong_signature_rejected(self):
        """Test token signed with wrong secret is rejected."""
        import jwt
        from app.config import settings

        # Create token with wrong secret
        payload = {
            "sub": str(uuid4()),
            "iat": int(datetime.now(timezone.utc).timestamp()),
            "exp": int((datetime.now(timezone.utc) + timedelta(hours=1)).timestamp()),
            "type": "access",
        }
        wrong_token = jwt.encode(payload, "wrong_secret_key", algorithm="HS256")

        credentials = HTTPAuthorizationCredentials(
            scheme="Bearer",
            credentials=wrong_token,
        )

        mock_db = AsyncMock()
        mock_redis = AsyncMock()

        with pytest.raises(HTTPException) as exc_info:
            await verify_token_from_any_source(
                credentials=credentials,
                db=mock_db,
                redis_client=mock_redis,
                request=None,
            )

        assert exc_info.value.status_code == 401

    @pytest.mark.asyncio
    async def test_token_missing_sub_claim_rejected(self):
        """Test token missing required 'sub' claim is rejected."""
        # Create token without sub claim
        token = create_test_token(
            subject="",  # Empty subject
            additional_claims={"type": "access"},
        )

        credentials = HTTPAuthorizationCredentials(
            scheme="Bearer",
            credentials=token,
        )

        mock_db = AsyncMock()
        mock_redis = AsyncMock()
        mock_redis.get.return_value = None

        # Mock database - no blacklist entry
        mock_blacklist_result = MagicMock()
        mock_blacklist_result.scalar_one_or_none.return_value = None
        mock_db.execute.return_value = mock_blacklist_result

        with pytest.raises(HTTPException) as exc_info:
            await verify_token_from_any_source(
                credentials=credentials,
                db=mock_db,
                redis_client=mock_redis,
                request=None,
            )

        assert exc_info.value.status_code == 401
        assert "Token missing required 'sub' claim" in exc_info.value.detail

    @pytest.mark.asyncio
    async def test_redis_unavailable_fallback_to_postgres(self, valid_credentials):
        """Test system falls back to PostgreSQL if Redis is unavailable."""
        mock_db = AsyncMock()

        # Mock database - token not blacklisted
        mock_blacklist_result = MagicMock()
        mock_blacklist_result.scalar_one_or_none.return_value = None
        mock_db.execute.return_value = mock_blacklist_result

        # Mock Redis - raises exception (connection failed)
        mock_redis = AsyncMock()
        mock_redis.get.side_effect = Exception("Redis connection failed")

        # Should succeed despite Redis failure
        payload = await verify_token_from_any_source(
            credentials=valid_credentials,
            db=mock_db,
            redis_client=mock_redis,
            request=None,
        )

        assert payload is not None
        # Verify database was queried (fallback)
        mock_db.execute.assert_called_once()

    @pytest.mark.asyncio
    async def test_redis_none_fallback_to_postgres(self, valid_credentials):
        """Test system falls back to PostgreSQL if Redis client is None."""
        mock_db = AsyncMock()

        # Mock database - token not blacklisted
        mock_blacklist_result = MagicMock()
        mock_blacklist_result.scalar_one_or_none.return_value = None
        mock_db.execute.return_value = mock_blacklist_result

        # No Redis client provided
        payload = await verify_token_from_any_source(
            credentials=valid_credentials,
            db=mock_db,
            redis_client=None,
            request=None,
        )

        assert payload is not None
        # Verify database was queried
        mock_db.execute.assert_called_once()

    @pytest.mark.asyncio
    async def test_refresh_token_type_accepted(self, mock_user):
        """Test refresh token type is accepted (not just access tokens)."""
        refresh_token = create_refresh_token(user_id=str(mock_user.id))

        credentials = HTTPAuthorizationCredentials(
            scheme="Bearer",
            credentials=refresh_token,
        )

        mock_db = AsyncMock()

        # Mock blacklist check - not blacklisted
        mock_blacklist_result = MagicMock()
        mock_blacklist_result.scalar_one_or_none.return_value = None
        mock_db.execute.return_value = mock_blacklist_result

        mock_redis = AsyncMock()
        mock_redis.get.return_value = None

        payload = await verify_token_from_any_source(
            credentials=credentials,
            db=mock_db,
            redis_client=mock_redis,
            request=None,
        )

        assert payload is not None
        assert payload["sub"] == str(mock_user.id)
        assert payload["type"] == "refresh"

    @pytest.mark.asyncio
    async def test_gibbon_token_with_username_accepted(self, mock_user):
        """Test Gibbon-sourced token with username is accepted."""
        # Create Gibbon-style token
        token = create_test_token(
            subject=str(mock_user.id),
            additional_claims={
                "source": "gibbon",
                "username": "testuser",
                "email": mock_user.email,
                "role": mock_user.role.value,
                "session_id": "session_123",
            },
        )

        credentials = HTTPAuthorizationCredentials(
            scheme="Bearer",
            credentials=token,
        )

        mock_db = AsyncMock()

        # Mock blacklist check - not blacklisted
        mock_blacklist_result = MagicMock()
        mock_blacklist_result.scalar_one_or_none.return_value = None
        mock_db.execute.return_value = mock_blacklist_result

        mock_redis = AsyncMock()
        mock_redis.get.return_value = None

        payload = await verify_token_from_any_source(
            credentials=credentials,
            db=mock_db,
            redis_client=mock_redis,
            request=None,
        )

        assert payload is not None
        assert payload["sub"] == str(mock_user.id)
        assert payload["source"] == "gibbon"
        assert payload["username"] == "testuser"

    @pytest.mark.asyncio
    async def test_gibbon_token_missing_username_rejected(self, mock_user):
        """Test Gibbon token missing required username is rejected."""
        # Create Gibbon-style token without username
        token = create_test_token(
            subject=str(mock_user.id),
            additional_claims={
                "source": "gibbon",
                "email": mock_user.email,
                "role": mock_user.role.value,
            },
        )

        credentials = HTTPAuthorizationCredentials(
            scheme="Bearer",
            credentials=token,
        )

        mock_db = AsyncMock()

        # Mock blacklist check - not blacklisted
        mock_blacklist_result = MagicMock()
        mock_blacklist_result.scalar_one_or_none.return_value = None
        mock_db.execute.return_value = mock_blacklist_result

        mock_redis = AsyncMock()
        mock_redis.get.return_value = None

        with pytest.raises(HTTPException) as exc_info:
            await verify_token_from_any_source(
                credentials=credentials,
                db=mock_db,
                redis_client=mock_redis,
                request=None,
            )

        assert exc_info.value.status_code == 401
        assert "Gibbon token missing required 'username' claim" in exc_info.value.detail

    @pytest.mark.asyncio
    async def test_blacklist_check_uses_correct_redis_key_format(self, valid_credentials):
        """Test blacklist check uses correct Redis key format (blacklist:token)."""
        mock_db = AsyncMock()

        # Mock database - not blacklisted
        mock_blacklist_result = MagicMock()
        mock_blacklist_result.scalar_one_or_none.return_value = None
        mock_db.execute.return_value = mock_blacklist_result

        mock_redis = AsyncMock()
        mock_redis.get.return_value = None

        await verify_token_from_any_source(
            credentials=valid_credentials,
            db=mock_db,
            redis_client=mock_redis,
            request=None,
        )

        # Verify Redis was called with correct key format
        mock_redis.get.assert_called_once()
        call_args = mock_redis.get.call_args[0][0]
        assert call_args.startswith("blacklist:")

    @pytest.mark.asyncio
    async def test_ai_service_source_token_accepted(self, mock_user):
        """Test AI service sourced token is accepted."""
        token = create_test_token(
            subject=str(mock_user.id),
            additional_claims={
                "source": "ai-service",
                "email": mock_user.email,
                "role": mock_user.role.value,
                "type": "access",
            },
        )

        credentials = HTTPAuthorizationCredentials(
            scheme="Bearer",
            credentials=token,
        )

        mock_db = AsyncMock()

        # Mock blacklist check - not blacklisted
        mock_blacklist_result = MagicMock()
        mock_blacklist_result.scalar_one_or_none.return_value = None
        mock_db.execute.return_value = mock_blacklist_result

        mock_redis = AsyncMock()
        mock_redis.get.return_value = None

        payload = await verify_token_from_any_source(
            credentials=credentials,
            db=mock_db,
            redis_client=mock_redis,
            request=None,
        )

        assert payload is not None
        assert payload["source"] == "ai-service"


