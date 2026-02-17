"""Unit tests for Redis token blacklist service in LAYA AI Service.

Tests TokenBlacklistService from app/auth/blacklist.py for token blacklisting,
TTL management, and Redis integration.
"""

from datetime import datetime, timedelta, timezone
from unittest.mock import AsyncMock, MagicMock, patch
from uuid import uuid4

import pytest
import pytest_asyncio
from redis.asyncio import Redis

from app.auth.blacklist import TokenBlacklistService
from tests.auth.conftest import create_test_token


class TestTokenBlacklistServiceInit:
    """Tests for TokenBlacklistService initialization."""

    def test_init_with_redis_client(self):
        """Test TokenBlacklistService can be initialized with Redis client."""
        mock_redis = MagicMock(spec=Redis)
        service = TokenBlacklistService(redis_client=mock_redis)
        assert service._redis_client is mock_redis

    def test_init_without_redis_client(self):
        """Test TokenBlacklistService can be initialized without Redis client."""
        service = TokenBlacklistService()
        assert service._redis_client is None

    @pytest.mark.asyncio
    async def test_get_client_returns_provided_client(self):
        """Test _get_client returns the provided Redis client."""
        mock_redis = MagicMock(spec=Redis)
        service = TokenBlacklistService(redis_client=mock_redis)
        client = await service._get_client()
        assert client is mock_redis

    @pytest.mark.asyncio
    async def test_get_client_fetches_when_none(self):
        """Test _get_client fetches Redis client when not provided."""
        service = TokenBlacklistService()
        with patch("app.auth.blacklist.get_redis_client") as mock_get_redis:
            mock_redis = AsyncMock(spec=Redis)
            mock_get_redis.return_value = mock_redis
            client = await service._get_client()
            assert client is mock_redis
            mock_get_redis.assert_called_once()

    def test_make_key_creates_correct_key(self):
        """Test _make_key creates correct Redis key with prefix."""
        service = TokenBlacklistService()
        token = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.test.signature"
        key = service._make_key(token)
        assert key == f"blacklist:{token}"
        assert key.startswith("blacklist:")


class TestAddToBlacklist:
    """Tests for add_to_blacklist() method."""

    @pytest_asyncio.fixture
    async def mock_redis(self):
        """Create a mock Redis client."""
        mock = AsyncMock(spec=Redis)
        mock.setex = AsyncMock(return_value=True)
        return mock

    @pytest.fixture
    def service(self, mock_redis):
        """Create TokenBlacklistService with mock Redis."""
        return TokenBlacklistService(redis_client=mock_redis)

    @pytest.fixture
    def valid_token(self):
        """Create a valid JWT token for testing."""
        return create_test_token(
            subject=str(uuid4()),
            expires_delta_seconds=3600,
        )

    @pytest.fixture
    def future_expiry(self):
        """Create a future expiration datetime."""
        return datetime.now(timezone.utc) + timedelta(hours=1)

    @pytest.mark.asyncio
    async def test_add_to_blacklist_success(self, service, mock_redis, valid_token, future_expiry):
        """Test add_to_blacklist successfully blacklists a token."""
        user_id = str(uuid4())
        result = await service.add_to_blacklist(
            token=valid_token,
            user_id=user_id,
            expires_at=future_expiry,
        )

        assert result is True
        mock_redis.setex.assert_called_once()

    @pytest.mark.asyncio
    async def test_add_to_blacklist_creates_correct_key(self, service, mock_redis, valid_token, future_expiry):
        """Test add_to_blacklist uses correct Redis key."""
        user_id = str(uuid4())
        await service.add_to_blacklist(
            token=valid_token,
            user_id=user_id,
            expires_at=future_expiry,
        )

        call_args = mock_redis.setex.call_args
        key = call_args[0][0]
        assert key == f"blacklist:{valid_token}"

    @pytest.mark.asyncio
    async def test_add_to_blacklist_calculates_correct_ttl(self, service, mock_redis, valid_token):
        """Test add_to_blacklist calculates correct TTL in seconds."""
        user_id = str(uuid4())
        now = datetime.now(timezone.utc)
        expires_at = now + timedelta(seconds=3600)

        await service.add_to_blacklist(
            token=valid_token,
            user_id=user_id,
            expires_at=expires_at,
        )

        call_args = mock_redis.setex.call_args
        ttl = call_args[0][1]
        # TTL should be approximately 3600 seconds (allow small tolerance)
        assert 3500 < ttl <= 3600

    @pytest.mark.asyncio
    async def test_add_to_blacklist_stores_user_id(self, service, mock_redis, valid_token, future_expiry):
        """Test add_to_blacklist stores user_id in value."""
        user_id = str(uuid4())
        await service.add_to_blacklist(
            token=valid_token,
            user_id=user_id,
            expires_at=future_expiry,
        )

        call_args = mock_redis.setex.call_args
        value = call_args[0][2]
        assert value.startswith(user_id)
        assert ":" in value  # Format is "user_id:timestamp"

    @pytest.mark.asyncio
    async def test_add_to_blacklist_stores_timestamp(self, service, mock_redis, valid_token, future_expiry):
        """Test add_to_blacklist stores timestamp in value."""
        user_id = str(uuid4())
        before = int(datetime.now(timezone.utc).timestamp())

        await service.add_to_blacklist(
            token=valid_token,
            user_id=user_id,
            expires_at=future_expiry,
        )

        after = int(datetime.now(timezone.utc).timestamp())
        call_args = mock_redis.setex.call_args
        value = call_args[0][2]
        parts = value.split(":")
        assert len(parts) == 2
        timestamp = int(parts[1])
        assert before <= timestamp <= after

    @pytest.mark.asyncio
    async def test_add_to_blacklist_naive_datetime_raises_error(self, service, valid_token):
        """Test add_to_blacklist raises ValueError for naive datetime."""
        naive_datetime = datetime.now()  # No timezone
        user_id = str(uuid4())

        with pytest.raises(ValueError) as exc_info:
            await service.add_to_blacklist(
                token=valid_token,
                user_id=user_id,
                expires_at=naive_datetime,
            )

        assert "timezone-aware" in str(exc_info.value)

    @pytest.mark.asyncio
    async def test_add_to_blacklist_expired_token_raises_error(self, service, valid_token):
        """Test add_to_blacklist raises ValueError for already expired token."""
        past_expiry = datetime.now(timezone.utc) - timedelta(hours=1)
        user_id = str(uuid4())

        with pytest.raises(ValueError) as exc_info:
            await service.add_to_blacklist(
                token=valid_token,
                user_id=user_id,
                expires_at=past_expiry,
            )

        assert "already expired" in str(exc_info.value)

    @pytest.mark.asyncio
    async def test_add_to_blacklist_zero_ttl_raises_error(self, service, valid_token):
        """Test add_to_blacklist raises ValueError for zero TTL."""
        now = datetime.now(timezone.utc)
        user_id = str(uuid4())

        with pytest.raises(ValueError) as exc_info:
            await service.add_to_blacklist(
                token=valid_token,
                user_id=user_id,
                expires_at=now,
            )

        assert "already expired" in str(exc_info.value)

    @pytest.mark.asyncio
    async def test_add_to_blacklist_uses_setex_atomic_operation(self, service, mock_redis, valid_token, future_expiry):
        """Test add_to_blacklist uses atomic SETEX operation."""
        user_id = str(uuid4())
        await service.add_to_blacklist(
            token=valid_token,
            user_id=user_id,
            expires_at=future_expiry,
        )

        # Verify SETEX was called (atomic set-with-expiry)
        mock_redis.setex.assert_called_once()
        # Verify it was not called with separate set() and expire() calls
        mock_redis.set.assert_not_called()
        mock_redis.expire.assert_not_called()


class TestIsBlacklisted:
    """Tests for is_blacklisted() method."""

    @pytest_asyncio.fixture
    async def mock_redis(self):
        """Create a mock Redis client."""
        return AsyncMock(spec=Redis)

    @pytest.fixture
    def service(self, mock_redis):
        """Create TokenBlacklistService with mock Redis."""
        return TokenBlacklistService(redis_client=mock_redis)

    @pytest.fixture
    def valid_token(self):
        """Create a valid JWT token for testing."""
        return create_test_token(subject=str(uuid4()))

    @pytest.mark.asyncio
    async def test_is_blacklisted_returns_true_when_token_exists(self, service, mock_redis, valid_token):
        """Test is_blacklisted returns True when token is in blacklist."""
        mock_redis.get.return_value = "user123:1234567890"

        result = await service.is_blacklisted(valid_token)

        assert result is True
        mock_redis.get.assert_called_once()

    @pytest.mark.asyncio
    async def test_is_blacklisted_returns_false_when_token_not_exists(self, service, mock_redis, valid_token):
        """Test is_blacklisted returns False when token is not in blacklist."""
        mock_redis.get.return_value = None

        result = await service.is_blacklisted(valid_token)

        assert result is False
        mock_redis.get.assert_called_once()

    @pytest.mark.asyncio
    async def test_is_blacklisted_uses_correct_key(self, service, mock_redis, valid_token):
        """Test is_blacklisted uses correct Redis key."""
        mock_redis.get.return_value = None

        await service.is_blacklisted(valid_token)

        call_args = mock_redis.get.call_args
        key = call_args[0][0]
        assert key == f"blacklist:{valid_token}"

    @pytest.mark.asyncio
    async def test_is_blacklisted_handles_expired_keys(self, service, mock_redis, valid_token):
        """Test is_blacklisted returns False for expired keys (Redis returns None)."""
        # Redis automatically returns None for expired keys
        mock_redis.get.return_value = None

        result = await service.is_blacklisted(valid_token)

        assert result is False

    @pytest.mark.asyncio
    async def test_is_blacklisted_empty_string_value_treated_as_blacklisted(self, service, mock_redis, valid_token):
        """Test is_blacklisted treats empty string as blacklisted."""
        mock_redis.get.return_value = ""

        result = await service.is_blacklisted(valid_token)

        # Empty string is truthy in terms of existence
        assert result is True


class TestGetBlacklistInfo:
    """Tests for get_blacklist_info() method."""

    @pytest_asyncio.fixture
    async def mock_redis(self):
        """Create a mock Redis client."""
        mock = AsyncMock(spec=Redis)
        mock_pipeline = AsyncMock()
        mock_pipeline.get = MagicMock()
        mock_pipeline.ttl = MagicMock()
        mock_pipeline.execute = AsyncMock()
        mock.pipeline.return_value = mock_pipeline
        return mock

    @pytest.fixture
    def service(self, mock_redis):
        """Create TokenBlacklistService with mock Redis."""
        return TokenBlacklistService(redis_client=mock_redis)

    @pytest.fixture
    def valid_token(self):
        """Create a valid JWT token for testing."""
        return create_test_token(subject=str(uuid4()))

    @pytest.mark.asyncio
    async def test_get_blacklist_info_returns_info_when_token_exists(self, service, mock_redis, valid_token):
        """Test get_blacklist_info returns info for blacklisted token."""
        user_id = str(uuid4())
        timestamp = "1234567890"
        ttl = 3600

        mock_pipeline = mock_redis.pipeline.return_value
        mock_pipeline.execute.return_value = [f"{user_id}:{timestamp}", ttl]

        result = await service.get_blacklist_info(valid_token)

        assert result is not None
        assert result["user_id"] == user_id
        assert result["blacklisted_at"] == timestamp
        assert result["ttl"] == str(ttl)

    @pytest.mark.asyncio
    async def test_get_blacklist_info_returns_none_when_token_not_exists(self, service, mock_redis, valid_token):
        """Test get_blacklist_info returns None for non-blacklisted token."""
        mock_pipeline = mock_redis.pipeline.return_value
        mock_pipeline.execute.return_value = [None, -2]  # -2 means key doesn't exist

        result = await service.get_blacklist_info(valid_token)

        assert result is None

    @pytest.mark.asyncio
    async def test_get_blacklist_info_uses_pipeline(self, service, mock_redis, valid_token):
        """Test get_blacklist_info uses Redis pipeline for efficiency."""
        mock_pipeline = mock_redis.pipeline.return_value
        mock_pipeline.execute.return_value = ["user123:1234567890", 3600]

        await service.get_blacklist_info(valid_token)

        mock_redis.pipeline.assert_called_once()
        mock_pipeline.get.assert_called_once()
        mock_pipeline.ttl.assert_called_once()
        mock_pipeline.execute.assert_called_once()

    @pytest.mark.asyncio
    async def test_get_blacklist_info_parses_value_correctly(self, service, mock_redis, valid_token):
        """Test get_blacklist_info correctly parses stored value."""
        user_id = "user-abc-123"
        timestamp = "9876543210"
        mock_pipeline = mock_redis.pipeline.return_value
        mock_pipeline.execute.return_value = [f"{user_id}:{timestamp}", 1800]

        result = await service.get_blacklist_info(valid_token)

        assert result["user_id"] == user_id
        assert result["blacklisted_at"] == timestamp

    @pytest.mark.asyncio
    async def test_get_blacklist_info_handles_malformed_value(self, service, mock_redis, valid_token):
        """Test get_blacklist_info handles malformed value gracefully."""
        mock_pipeline = mock_redis.pipeline.return_value
        mock_pipeline.execute.return_value = ["no_colon_separator", 3600]

        result = await service.get_blacklist_info(valid_token)

        # Should not crash, returns the value as user_id
        assert result is not None
        assert result["user_id"] == "no_colon_separator"
        assert result["blacklisted_at"] == "unknown"

    @pytest.mark.asyncio
    async def test_get_blacklist_info_handles_empty_value(self, service, mock_redis, valid_token):
        """Test get_blacklist_info handles empty string value."""
        mock_pipeline = mock_redis.pipeline.return_value
        mock_pipeline.execute.return_value = ["", 3600]

        result = await service.get_blacklist_info(valid_token)

        assert result is not None
        assert result["user_id"] == ""
        assert result["blacklisted_at"] == "unknown"

    @pytest.mark.asyncio
    async def test_get_blacklist_info_includes_ttl(self, service, mock_redis, valid_token):
        """Test get_blacklist_info includes remaining TTL."""
        mock_pipeline = mock_redis.pipeline.return_value
        ttl_value = 7200
        mock_pipeline.execute.return_value = ["user123:1234567890", ttl_value]

        result = await service.get_blacklist_info(valid_token)

        assert result["ttl"] == str(ttl_value)


class TestRemoveFromBlacklist:
    """Tests for remove_from_blacklist() method."""

    @pytest_asyncio.fixture
    async def mock_redis(self):
        """Create a mock Redis client."""
        return AsyncMock(spec=Redis)

    @pytest.fixture
    def service(self, mock_redis):
        """Create TokenBlacklistService with mock Redis."""
        return TokenBlacklistService(redis_client=mock_redis)

    @pytest.fixture
    def valid_token(self):
        """Create a valid JWT token for testing."""
        return create_test_token(subject=str(uuid4()))

    @pytest.mark.asyncio
    async def test_remove_from_blacklist_returns_true_when_removed(self, service, mock_redis, valid_token):
        """Test remove_from_blacklist returns True when token was removed."""
        mock_redis.delete.return_value = 1  # 1 key deleted

        result = await service.remove_from_blacklist(valid_token)

        assert result is True
        mock_redis.delete.assert_called_once()

    @pytest.mark.asyncio
    async def test_remove_from_blacklist_returns_false_when_not_found(self, service, mock_redis, valid_token):
        """Test remove_from_blacklist returns False when token wasn't in blacklist."""
        mock_redis.delete.return_value = 0  # 0 keys deleted

        result = await service.remove_from_blacklist(valid_token)

        assert result is False
        mock_redis.delete.assert_called_once()

    @pytest.mark.asyncio
    async def test_remove_from_blacklist_uses_correct_key(self, service, mock_redis, valid_token):
        """Test remove_from_blacklist uses correct Redis key."""
        mock_redis.delete.return_value = 1

        await service.remove_from_blacklist(valid_token)

        call_args = mock_redis.delete.call_args
        key = call_args[0][0]
        assert key == f"blacklist:{valid_token}"

    @pytest.mark.asyncio
    async def test_remove_from_blacklist_multiple_deletions(self, service, mock_redis, valid_token):
        """Test remove_from_blacklist handles multiple key deletions."""
        # Redis delete can return count > 1 if multiple keys matched (rare edge case)
        mock_redis.delete.return_value = 2

        result = await service.remove_from_blacklist(valid_token)

        # Should still return True for any count > 0
        assert result is True


class TestCleanupExpired:
    """Tests for cleanup_expired() method."""

    @pytest_asyncio.fixture
    async def mock_redis(self):
        """Create a mock Redis client."""
        return AsyncMock(spec=Redis)

    @pytest.fixture
    def service(self, mock_redis):
        """Create TokenBlacklistService with mock Redis."""
        return TokenBlacklistService(redis_client=mock_redis)

    @pytest.mark.asyncio
    async def test_cleanup_expired_returns_zero(self, service):
        """Test cleanup_expired returns 0 (Redis handles cleanup automatically)."""
        result = await service.cleanup_expired()
        assert result == 0

    @pytest.mark.asyncio
    async def test_cleanup_expired_no_redis_calls(self, service, mock_redis):
        """Test cleanup_expired doesn't make any Redis calls."""
        await service.cleanup_expired()

        # No Redis operations should be called
        mock_redis.scan.assert_not_called()
        mock_redis.delete.assert_not_called()
        mock_redis.keys.assert_not_called()


class TestTokenBlacklistServiceIntegration:
    """Integration tests combining multiple operations."""

    @pytest_asyncio.fixture
    async def mock_redis(self):
        """Create a mock Redis client with realistic behavior."""
        mock = AsyncMock(spec=Redis)
        # Simulate in-memory storage
        mock._storage = {}

        async def mock_setex(key, ttl, value):
            mock._storage[key] = value
            return True

        async def mock_get(key):
            return mock._storage.get(key)

        async def mock_delete(key):
            if key in mock._storage:
                del mock._storage[key]
                return 1
            return 0

        mock.setex = AsyncMock(side_effect=mock_setex)
        mock.get = AsyncMock(side_effect=mock_get)
        mock.delete = AsyncMock(side_effect=mock_delete)

        return mock

    @pytest.fixture
    def service(self, mock_redis):
        """Create TokenBlacklistService with mock Redis."""
        return TokenBlacklistService(redis_client=mock_redis)

    @pytest.mark.asyncio
    async def test_add_and_check_blacklist(self, service, mock_redis):
        """Test adding token to blacklist and checking it."""
        token = create_test_token(subject=str(uuid4()))
        user_id = str(uuid4())
        expires_at = datetime.now(timezone.utc) + timedelta(hours=1)

        # Add to blacklist
        await service.add_to_blacklist(token, user_id, expires_at)

        # Check if blacklisted
        is_blacklisted = await service.is_blacklisted(token)
        assert is_blacklisted is True

    @pytest.mark.asyncio
    async def test_add_check_and_remove_blacklist(self, service, mock_redis):
        """Test full lifecycle: add, check, remove, check again."""
        token = create_test_token(subject=str(uuid4()))
        user_id = str(uuid4())
        expires_at = datetime.now(timezone.utc) + timedelta(hours=1)

        # Add to blacklist
        await service.add_to_blacklist(token, user_id, expires_at)
        assert await service.is_blacklisted(token) is True

        # Remove from blacklist
        removed = await service.remove_from_blacklist(token)
        assert removed is True

        # Should no longer be blacklisted
        assert await service.is_blacklisted(token) is False

    @pytest.mark.asyncio
    async def test_different_tokens_independent(self, service, mock_redis):
        """Test that different tokens are tracked independently."""
        token1 = create_test_token(subject=str(uuid4()))
        token2 = create_test_token(subject=str(uuid4()))
        user_id = str(uuid4())
        expires_at = datetime.now(timezone.utc) + timedelta(hours=1)

        # Blacklist only token1
        await service.add_to_blacklist(token1, user_id, expires_at)

        # Check both tokens
        assert await service.is_blacklisted(token1) is True
        assert await service.is_blacklisted(token2) is False

    @pytest.mark.asyncio
    async def test_same_token_can_be_blacklisted_twice(self, service, mock_redis):
        """Test that blacklisting same token twice updates the entry."""
        token = create_test_token(subject=str(uuid4()))
        user_id = str(uuid4())
        expires_at = datetime.now(timezone.utc) + timedelta(hours=1)

        # Blacklist token twice
        result1 = await service.add_to_blacklist(token, user_id, expires_at)
        result2 = await service.add_to_blacklist(token, user_id, expires_at)

        assert result1 is True
        assert result2 is True
        assert await service.is_blacklisted(token) is True

    @pytest.mark.asyncio
    async def test_remove_non_blacklisted_token_returns_false(self, service, mock_redis):
        """Test removing a token that was never blacklisted returns False."""
        token = create_test_token(subject=str(uuid4()))

        removed = await service.remove_from_blacklist(token)

        assert removed is False


class TestTokenBlacklistServiceEdgeCases:
    """Tests for edge cases and error scenarios."""

    @pytest_asyncio.fixture
    async def mock_redis(self):
        """Create a mock Redis client."""
        return AsyncMock(spec=Redis)

    @pytest.fixture
    def service(self, mock_redis):
        """Create TokenBlacklistService with mock Redis."""
        return TokenBlacklistService(redis_client=mock_redis)

    @pytest.mark.asyncio
    async def test_very_short_ttl(self, service, mock_redis):
        """Test blacklisting with very short TTL (1 second)."""
        token = create_test_token(subject=str(uuid4()))
        user_id = str(uuid4())
        expires_at = datetime.now(timezone.utc) + timedelta(seconds=1)

        mock_redis.setex = AsyncMock(return_value=True)
        result = await service.add_to_blacklist(token, user_id, expires_at)

        assert result is True
        call_args = mock_redis.setex.call_args
        ttl = call_args[0][1]
        assert ttl == 1

    @pytest.mark.asyncio
    async def test_very_long_ttl(self, service, mock_redis):
        """Test blacklisting with very long TTL (30 days)."""
        token = create_test_token(subject=str(uuid4()))
        user_id = str(uuid4())
        expires_at = datetime.now(timezone.utc) + timedelta(days=30)

        mock_redis.setex = AsyncMock(return_value=True)
        result = await service.add_to_blacklist(token, user_id, expires_at)

        assert result is True
        call_args = mock_redis.setex.call_args
        ttl = call_args[0][1]
        expected_ttl = 30 * 24 * 3600
        assert expected_ttl - 10 < ttl <= expected_ttl

    @pytest.mark.asyncio
    async def test_long_token_string(self, service, mock_redis):
        """Test blacklisting with very long token string."""
        # Create an abnormally long token
        long_token = "x" * 1000
        user_id = str(uuid4())
        expires_at = datetime.now(timezone.utc) + timedelta(hours=1)

        mock_redis.setex = AsyncMock(return_value=True)
        result = await service.add_to_blacklist(long_token, user_id, expires_at)

        assert result is True
        call_args = mock_redis.setex.call_args
        key = call_args[0][0]
        assert key == f"blacklist:{long_token}"

    @pytest.mark.asyncio
    async def test_special_characters_in_token(self, service, mock_redis):
        """Test blacklisting token with special characters."""
        token = "token.with-special_chars!@#$%"
        user_id = str(uuid4())
        expires_at = datetime.now(timezone.utc) + timedelta(hours=1)

        mock_redis.setex = AsyncMock(return_value=True)
        result = await service.add_to_blacklist(token, user_id, expires_at)

        assert result is True

    @pytest.mark.asyncio
    async def test_empty_token_string(self, service, mock_redis):
        """Test blacklisting empty token string."""
        token = ""
        user_id = str(uuid4())
        expires_at = datetime.now(timezone.utc) + timedelta(hours=1)

        mock_redis.setex = AsyncMock(return_value=True)
        mock_redis.get = AsyncMock(return_value=None)

        # Should not raise an error
        result = await service.add_to_blacklist(token, user_id, expires_at)
        assert result is True

        # Can be checked
        is_blacklisted = await service.is_blacklisted(token)
        assert isinstance(is_blacklisted, bool)

    @pytest.mark.asyncio
    async def test_unicode_in_user_id(self, service, mock_redis):
        """Test blacklisting with Unicode characters in user_id."""
        token = create_test_token(subject=str(uuid4()))
        user_id = "user-用户-🔐"
        expires_at = datetime.now(timezone.utc) + timedelta(hours=1)

        mock_redis.setex = AsyncMock(return_value=True)
        result = await service.add_to_blacklist(token, user_id, expires_at)

        assert result is True
        call_args = mock_redis.setex.call_args
        value = call_args[0][2]
        assert user_id in value
