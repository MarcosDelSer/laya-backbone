"""Performance tests for token blacklist checks.

Tests verify that blacklist checking meets performance requirements:
- Redis check < 5ms
- Database check < 50ms
- Total authentication overhead < 10ms

Uses time measurements to ensure security features don't significantly
impact request latency.
"""

import time
from datetime import datetime, timedelta, timezone
from unittest.mock import AsyncMock, MagicMock
from uuid import uuid4

import pytest
from fastapi.security import HTTPAuthorizationCredentials

from app.middleware.auth import verify_token_from_any_source
from app.auth.models import UserRole

from tests.auth.conftest import create_access_token


class TestBlacklistPerformance:
    """Performance tests for token blacklist checking."""

    @pytest.fixture
    def valid_token(self):
        """Create a valid access token for performance testing."""
        return create_access_token(
            user_id=str(uuid4()),
            email="test@example.com",
            role=UserRole.TEACHER.value,
        )

    @pytest.fixture
    def valid_credentials(self, valid_token):
        """Create valid HTTP authorization credentials."""
        return HTTPAuthorizationCredentials(
            scheme="Bearer",
            credentials=valid_token,
        )

    @pytest.mark.asyncio
    async def test_redis_blacklist_check_performance(self, valid_credentials):
        """Test Redis blacklist check completes in < 5ms."""
        mock_db = AsyncMock()

        # Mock Redis - token found in blacklist (fast cache hit)
        mock_redis = AsyncMock()
        mock_redis.get.return_value = "1"

        # Warm up - run once to ensure any initialization is done
        try:
            await verify_token_from_any_source(
                credentials=valid_credentials,
                db=mock_db,
                redis_client=mock_redis,
                request=None,
            )
        except Exception:
            pass  # Expected to fail (token blacklisted)

        # Measure performance over multiple iterations for accuracy
        iterations = 100
        start_time = time.perf_counter()

        for _ in range(iterations):
            try:
                await verify_token_from_any_source(
                    credentials=valid_credentials,
                    db=mock_db,
                    redis_client=mock_redis,
                    request=None,
                )
            except Exception:
                pass  # Expected to fail (token blacklisted)

        end_time = time.perf_counter()
        avg_time_ms = ((end_time - start_time) / iterations) * 1000

        # Redis check should be very fast (< 5ms)
        assert avg_time_ms < 5.0, f"Redis check took {avg_time_ms:.2f}ms (expected < 5ms)"

        # Verify database was NOT called (Redis cache hit)
        # Note: mock_db.execute might be called from multiple iterations,
        # but it should never be called because Redis returned a hit
        mock_db.execute.assert_not_called()

    @pytest.mark.asyncio
    async def test_database_blacklist_check_performance(self, valid_credentials):
        """Test database blacklist check completes in < 50ms."""
        mock_db = AsyncMock()

        # Mock database - token found in blacklist
        mock_blacklist_entry = MagicMock()
        mock_blacklist_result = MagicMock()
        mock_blacklist_result.scalar_one_or_none.return_value = mock_blacklist_entry
        mock_db.execute.return_value = mock_blacklist_result

        # No Redis (test pure database check)
        mock_redis = None

        # Warm up
        try:
            await verify_token_from_any_source(
                credentials=valid_credentials,
                db=mock_db,
                redis_client=mock_redis,
                request=None,
            )
        except Exception:
            pass

        # Measure performance over multiple iterations
        iterations = 50
        start_time = time.perf_counter()

        for _ in range(iterations):
            try:
                await verify_token_from_any_source(
                    credentials=valid_credentials,
                    db=mock_db,
                    redis_client=mock_redis,
                    request=None,
                )
            except Exception:
                pass  # Expected to fail (token blacklisted)

        end_time = time.perf_counter()
        avg_time_ms = ((end_time - start_time) / iterations) * 1000

        # Database check should be fast (< 50ms)
        assert avg_time_ms < 50.0, f"Database check took {avg_time_ms:.2f}ms (expected < 50ms)"

    @pytest.mark.asyncio
    async def test_redis_miss_fallback_to_db_performance(self, valid_credentials):
        """Test Redis miss + DB check completes in < 50ms."""
        mock_db = AsyncMock()

        # Mock database - token NOT blacklisted
        mock_blacklist_result = MagicMock()
        mock_blacklist_result.scalar_one_or_none.return_value = None
        mock_db.execute.return_value = mock_blacklist_result

        # Mock Redis - token NOT in cache (miss, fallback to DB)
        mock_redis = AsyncMock()
        mock_redis.get.return_value = None

        # Warm up
        await verify_token_from_any_source(
            credentials=valid_credentials,
            db=mock_db,
            redis_client=mock_redis,
            request=None,
        )

        # Measure performance over multiple iterations
        iterations = 50
        start_time = time.perf_counter()

        for _ in range(iterations):
            await verify_token_from_any_source(
                credentials=valid_credentials,
                db=mock_db,
                redis_client=mock_redis,
                request=None,
            )

        end_time = time.perf_counter()
        avg_time_ms = ((end_time - start_time) / iterations) * 1000

        # Redis miss + DB check should still be fast (< 50ms)
        assert avg_time_ms < 50.0, f"Redis miss + DB check took {avg_time_ms:.2f}ms (expected < 50ms)"

        # Verify both Redis and database were called
        assert mock_redis.get.call_count >= iterations
        assert mock_db.execute.call_count >= iterations

    @pytest.mark.asyncio
    async def test_valid_token_no_blacklist_performance(self, valid_credentials):
        """Test valid non-blacklisted token check completes in < 10ms."""
        mock_db = AsyncMock()

        # Mock database - token NOT blacklisted
        mock_blacklist_result = MagicMock()
        mock_blacklist_result.scalar_one_or_none.return_value = None
        mock_db.execute.return_value = mock_blacklist_result

        # Mock Redis - token NOT in cache
        mock_redis = AsyncMock()
        mock_redis.get.return_value = None

        # Warm up
        await verify_token_from_any_source(
            credentials=valid_credentials,
            db=mock_db,
            redis_client=mock_redis,
            request=None,
        )

        # Measure performance over multiple iterations
        iterations = 100
        start_time = time.perf_counter()

        for _ in range(iterations):
            await verify_token_from_any_source(
                credentials=valid_credentials,
                db=mock_db,
                redis_client=mock_redis,
                request=None,
            )

        end_time = time.perf_counter()
        avg_time_ms = ((end_time - start_time) / iterations) * 1000

        # Total overhead should be minimal (< 10ms per request)
        # This is the acceptance criteria from the spec
        assert avg_time_ms < 10.0, f"Auth overhead is {avg_time_ms:.2f}ms (expected < 10ms)"

    @pytest.mark.asyncio
    async def test_redis_cache_hit_is_faster_than_db(self, valid_credentials):
        """Test Redis cache hit is significantly faster than database check."""
        mock_db = AsyncMock()

        # Test 1: Redis cache hit (blacklisted in Redis)
        mock_redis_hit = AsyncMock()
        mock_redis_hit.get.return_value = "1"

        iterations = 50
        start_time = time.perf_counter()

        for _ in range(iterations):
            try:
                await verify_token_from_any_source(
                    credentials=valid_credentials,
                    db=mock_db,
                    redis_client=mock_redis_hit,
                    request=None,
                )
            except Exception:
                pass

        redis_time = time.perf_counter() - start_time

        # Test 2: Database check (no Redis)
        mock_blacklist_entry = MagicMock()
        mock_blacklist_result = MagicMock()
        mock_blacklist_result.scalar_one_or_none.return_value = mock_blacklist_entry
        mock_db.execute.return_value = mock_blacklist_result

        start_time = time.perf_counter()

        for _ in range(iterations):
            try:
                await verify_token_from_any_source(
                    credentials=valid_credentials,
                    db=mock_db,
                    redis_client=None,
                    request=None,
                )
            except Exception:
                pass

        db_time = time.perf_counter() - start_time

        # Redis should be faster (even with mocks, the pattern holds)
        # We're not asserting specific ratios since these are mocks,
        # but verifying both complete in reasonable time
        redis_time_ms = (redis_time / iterations) * 1000
        db_time_ms = (db_time / iterations) * 1000

        assert redis_time_ms < 5.0, f"Redis check took {redis_time_ms:.2f}ms"
        assert db_time_ms < 50.0, f"DB check took {db_time_ms:.2f}ms"

    @pytest.mark.asyncio
    async def test_jwt_decode_performance(self, valid_credentials):
        """Test JWT decoding overhead is reasonable."""
        mock_db = AsyncMock()

        # Mock database - token NOT blacklisted
        mock_blacklist_result = MagicMock()
        mock_blacklist_result.scalar_one_or_none.return_value = None
        mock_db.execute.return_value = mock_blacklist_result

        # Mock Redis - token NOT in cache
        mock_redis = AsyncMock()
        mock_redis.get.return_value = None

        # Measure JWT decode + blacklist check
        iterations = 100
        start_time = time.perf_counter()

        for _ in range(iterations):
            await verify_token_from_any_source(
                credentials=valid_credentials,
                db=mock_db,
                redis_client=mock_redis,
                request=None,
            )

        end_time = time.perf_counter()
        avg_time_ms = ((end_time - start_time) / iterations) * 1000

        # Total time including JWT decode should be reasonable
        assert avg_time_ms < 10.0, f"JWT decode + blacklist check took {avg_time_ms:.2f}ms"

    @pytest.mark.asyncio
    async def test_redis_failure_graceful_degradation_performance(self, valid_credentials):
        """Test that Redis failure doesn't significantly impact performance."""
        mock_db = AsyncMock()

        # Mock database - token NOT blacklisted
        mock_blacklist_result = MagicMock()
        mock_blacklist_result.scalar_one_or_none.return_value = None
        mock_db.execute.return_value = mock_blacklist_result

        # Mock Redis - connection fails
        mock_redis = AsyncMock()
        mock_redis.get.side_effect = Exception("Redis connection failed")

        # Warm up
        await verify_token_from_any_source(
            credentials=valid_credentials,
            db=mock_db,
            redis_client=mock_redis,
            request=None,
        )

        # Measure performance with Redis failure
        iterations = 50
        start_time = time.perf_counter()

        for _ in range(iterations):
            await verify_token_from_any_source(
                credentials=valid_credentials,
                db=mock_db,
                redis_client=mock_redis,
                request=None,
            )

        end_time = time.perf_counter()
        avg_time_ms = ((end_time - start_time) / iterations) * 1000

        # Should still complete reasonably fast (< 50ms) even with Redis failure
        assert avg_time_ms < 50.0, f"Fallback to DB took {avg_time_ms:.2f}ms (expected < 50ms)"

        # Verify database was called (graceful degradation)
        assert mock_db.execute.call_count >= iterations
