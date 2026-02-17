"""Performance tests for Redis token blacklist service in LAYA AI Service.

Tests TokenBlacklistService performance characteristics to verify:
- Redis blacklist lookup < 10ms
- JWT verification < 5ms
- Total authentication overhead < 15ms per request
"""

import asyncio
import time
from datetime import datetime, timedelta, timezone
from statistics import mean, median
from typing import List
from unittest.mock import AsyncMock, patch
from uuid import uuid4

import pytest
import pytest_asyncio
from redis.asyncio import Redis

from app.auth.blacklist import TokenBlacklistService
from app.auth.jwt import create_token, decode_token, verify_token
from tests.auth.conftest import create_test_token


class PerformanceTimer:
    """Helper class to measure execution time in milliseconds."""

    def __init__(self):
        self.start_time: float = 0
        self.end_time: float = 0
        self.elapsed_ms: float = 0

    def __enter__(self):
        """Start the timer."""
        self.start_time = time.perf_counter()
        return self

    def __exit__(self, exc_type, exc_val, exc_tb):
        """Stop the timer and calculate elapsed time."""
        self.end_time = time.perf_counter()
        self.elapsed_ms = (self.end_time - self.start_time) * 1000


def measure_async_execution(func, *args, **kwargs) -> float:
    """Measure the execution time of an async function in milliseconds.

    Args:
        func: Async function to measure
        *args: Positional arguments for the function
        **kwargs: Keyword arguments for the function

    Returns:
        float: Execution time in milliseconds
    """
    async def _wrapper():
        with PerformanceTimer() as timer:
            await func(*args, **kwargs)
        return timer.elapsed_ms

    return asyncio.run(_wrapper())


class TestBlacklistPerformance:
    """Performance tests for TokenBlacklistService operations."""

    @pytest_asyncio.fixture
    async def redis_client(self):
        """Create a mock Redis client with realistic timing."""
        mock = AsyncMock(spec=Redis)

        # Simulate realistic Redis operation times (1-3ms)
        async def setex_with_delay(*args, **kwargs):
            await asyncio.sleep(0.001)  # 1ms
            return True

        async def get_with_delay(*args, **kwargs):
            await asyncio.sleep(0.001)  # 1ms
            return b"user123:1234567890"

        async def delete_with_delay(*args, **kwargs):
            await asyncio.sleep(0.001)  # 1ms
            return 1

        async def pipeline_with_delay(*args, **kwargs):
            pipe_mock = AsyncMock()
            pipe_mock.get = AsyncMock()
            pipe_mock.ttl = AsyncMock()

            async def execute_with_delay():
                await asyncio.sleep(0.002)  # 2ms
                return [b"user123:1234567890", 3600]

            pipe_mock.execute = execute_with_delay
            return pipe_mock

        mock.setex = setex_with_delay
        mock.get = get_with_delay
        mock.delete = delete_with_delay
        mock.pipeline = pipeline_with_delay

        return mock

    @pytest.fixture
    def service(self, redis_client):
        """Create TokenBlacklistService with mock Redis."""
        return TokenBlacklistService(redis_client=redis_client)

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
    async def test_is_blacklisted_performance_single(self, service, valid_token):
        """Test single blacklist check is under 10ms."""
        with PerformanceTimer() as timer:
            await service.is_blacklisted(valid_token)

        elapsed_ms = timer.elapsed_ms
        assert elapsed_ms < 10, f"Blacklist check took {elapsed_ms:.2f}ms, expected < 10ms"

    @pytest.mark.asyncio
    async def test_is_blacklisted_performance_average(self, service, valid_token):
        """Test average blacklist check time across multiple calls."""
        num_iterations = 100
        times: List[float] = []

        for _ in range(num_iterations):
            with PerformanceTimer() as timer:
                await service.is_blacklisted(valid_token)
            times.append(timer.elapsed_ms)

        avg_time = mean(times)
        median_time = median(times)
        max_time = max(times)

        # Print performance summary for documentation
        print(f"\nBlacklist check performance (n={num_iterations}):")
        print(f"  Average: {avg_time:.2f}ms")
        print(f"  Median:  {median_time:.2f}ms")
        print(f"  Max:     {max_time:.2f}ms")

        # Assert performance requirements
        assert avg_time < 10, f"Average blacklist check {avg_time:.2f}ms exceeds 10ms limit"
        assert median_time < 10, f"Median blacklist check {median_time:.2f}ms exceeds 10ms limit"

    @pytest.mark.asyncio
    async def test_add_to_blacklist_performance(self, service, valid_token, future_expiry):
        """Test adding token to blacklist is under 10ms."""
        user_id = str(uuid4())

        with PerformanceTimer() as timer:
            await service.add_to_blacklist(
                token=valid_token,
                user_id=user_id,
                expires_at=future_expiry,
            )

        elapsed_ms = timer.elapsed_ms
        assert elapsed_ms < 10, f"Blacklist add took {elapsed_ms:.2f}ms, expected < 10ms"

    @pytest.mark.asyncio
    async def test_get_blacklist_info_performance(self, service, valid_token):
        """Test getting blacklist info is under 10ms."""
        with PerformanceTimer() as timer:
            await service.get_blacklist_info(valid_token)

        elapsed_ms = timer.elapsed_ms
        assert elapsed_ms < 10, f"Blacklist info retrieval took {elapsed_ms:.2f}ms, expected < 10ms"

    @pytest.mark.asyncio
    async def test_remove_from_blacklist_performance(self, service, valid_token):
        """Test removing token from blacklist is under 10ms."""
        with PerformanceTimer() as timer:
            await service.remove_from_blacklist(valid_token)

        elapsed_ms = timer.elapsed_ms
        assert elapsed_ms < 10, f"Blacklist remove took {elapsed_ms:.2f}ms, expected < 10ms"

    @pytest.mark.asyncio
    async def test_concurrent_blacklist_checks_performance(self, service):
        """Test performance under concurrent load."""
        num_concurrent = 10
        tokens = [
            create_test_token(subject=str(uuid4()), expires_delta_seconds=3600)
            for _ in range(num_concurrent)
        ]

        with PerformanceTimer() as timer:
            # Execute all checks concurrently
            await asyncio.gather(*[service.is_blacklisted(token) for token in tokens])

        elapsed_ms = timer.elapsed_ms
        avg_per_check = elapsed_ms / num_concurrent

        print(f"\nConcurrent blacklist checks (n={num_concurrent}):")
        print(f"  Total time: {elapsed_ms:.2f}ms")
        print(f"  Avg per check: {avg_per_check:.2f}ms")

        # Concurrent checks should benefit from async operations
        # Average per check should still be under 10ms
        assert avg_per_check < 10, f"Concurrent avg {avg_per_check:.2f}ms exceeds 10ms"


class TestJWTVerificationPerformance:
    """Performance tests for JWT token verification."""

    @pytest.fixture
    def valid_token(self):
        """Create a valid JWT token for testing."""
        return create_token(
            subject=str(uuid4()),
            expires_delta_seconds=3600,
            additional_claims={"role": "teacher", "school_id": str(uuid4())},
        )

    def test_create_token_performance(self):
        """Test JWT token creation is under 5ms."""
        num_iterations = 100
        times: List[float] = []

        for _ in range(num_iterations):
            with PerformanceTimer() as timer:
                create_token(
                    subject=str(uuid4()),
                    expires_delta_seconds=3600,
                    additional_claims={"role": "teacher"},
                )
            times.append(timer.elapsed_ms)

        avg_time = mean(times)
        median_time = median(times)

        print(f"\nJWT creation performance (n={num_iterations}):")
        print(f"  Average: {avg_time:.2f}ms")
        print(f"  Median:  {median_time:.2f}ms")

        assert avg_time < 5, f"Average JWT creation {avg_time:.2f}ms exceeds 5ms limit"

    def test_decode_token_performance(self, valid_token):
        """Test JWT token decoding is under 5ms."""
        num_iterations = 100
        times: List[float] = []

        for _ in range(num_iterations):
            with PerformanceTimer() as timer:
                decode_token(valid_token)
            times.append(timer.elapsed_ms)

        avg_time = mean(times)
        median_time = median(times)
        max_time = max(times)

        print(f"\nJWT decode performance (n={num_iterations}):")
        print(f"  Average: {avg_time:.2f}ms")
        print(f"  Median:  {median_time:.2f}ms")
        print(f"  Max:     {max_time:.2f}ms")

        assert avg_time < 5, f"Average JWT decode {avg_time:.2f}ms exceeds 5ms limit"
        assert median_time < 5, f"Median JWT decode {median_time:.2f}ms exceeds 5ms limit"


class TestCompleteAuthFlowPerformance:
    """Performance tests for complete authentication flow."""

    @pytest_asyncio.fixture
    async def mock_redis(self):
        """Create a mock Redis client with realistic timing."""
        mock = AsyncMock(spec=Redis)

        # Simulate realistic Redis GET operation (1-2ms)
        async def get_with_delay(*args, **kwargs):
            await asyncio.sleep(0.001)  # 1ms
            return None  # Token not blacklisted

        mock.get = get_with_delay
        return mock

    @pytest.fixture
    def blacklist_service(self, mock_redis):
        """Create blacklist service with mock Redis."""
        return TokenBlacklistService(redis_client=mock_redis)

    @pytest.fixture
    def valid_token(self):
        """Create a valid JWT token for testing."""
        return create_token(
            subject=str(uuid4()),
            expires_delta_seconds=3600,
            additional_claims={"role": "teacher"},
        )

    @pytest.mark.asyncio
    async def test_verify_token_performance(self, valid_token, mock_redis):
        """Test complete token verification (decode + blacklist check) is under 15ms."""
        # Mock the database session (not used with Redis blacklist)
        mock_db = AsyncMock()

        num_iterations = 100
        times: List[float] = []

        for _ in range(num_iterations):
            with PerformanceTimer() as timer:
                # verify_token does: decode_token + blacklist check
                await verify_token(valid_token, mock_db)
            times.append(timer.elapsed_ms)

        avg_time = mean(times)
        median_time = median(times)
        max_time = max(times)

        print(f"\nComplete auth verification (n={num_iterations}):")
        print(f"  Average: {avg_time:.2f}ms")
        print(f"  Median:  {median_time:.2f}ms")
        print(f"  Max:     {max_time:.2f}ms")

        # Total auth overhead should be < 15ms (JWT decode < 5ms + blacklist < 10ms)
        assert avg_time < 15, f"Average auth overhead {avg_time:.2f}ms exceeds 15ms limit"
        assert median_time < 15, f"Median auth overhead {median_time:.2f}ms exceeds 15ms limit"

    @pytest.mark.asyncio
    async def test_end_to_end_auth_flow_performance(self, mock_redis):
        """Test complete auth flow: create → decode → blacklist check."""
        num_iterations = 50
        times: List[float] = []

        mock_db = AsyncMock()

        for _ in range(num_iterations):
            with PerformanceTimer() as timer:
                # Step 1: Create token
                token = create_token(
                    subject=str(uuid4()),
                    expires_delta_seconds=3600,
                    additional_claims={"role": "teacher"},
                )

                # Step 2: Verify token (decode + blacklist check)
                await verify_token(token, mock_db)

            times.append(timer.elapsed_ms)

        avg_time = mean(times)
        median_time = median(times)
        max_time = max(times)

        print(f"\nEnd-to-end auth flow (n={num_iterations}):")
        print(f"  Average: {avg_time:.2f}ms")
        print(f"  Median:  {median_time:.2f}ms")
        print(f"  Max:     {max_time:.2f}ms")

        # Complete flow should be < 20ms (create < 5ms + verify < 15ms)
        assert avg_time < 20, f"Average e2e flow {avg_time:.2f}ms exceeds 20ms limit"

    @pytest.mark.asyncio
    async def test_concurrent_auth_requests_performance(self, mock_redis):
        """Test performance under concurrent authentication load."""
        num_concurrent = 20
        mock_db = AsyncMock()

        # Pre-create tokens
        tokens = [
            create_token(
                subject=str(uuid4()),
                expires_delta_seconds=3600,
                additional_claims={"role": "teacher"},
            )
            for _ in range(num_concurrent)
        ]

        with PerformanceTimer() as timer:
            # Execute all verifications concurrently
            await asyncio.gather(*[verify_token(token, mock_db) for token in tokens])

        elapsed_ms = timer.elapsed_ms
        avg_per_request = elapsed_ms / num_concurrent

        print(f"\nConcurrent auth requests (n={num_concurrent}):")
        print(f"  Total time: {elapsed_ms:.2f}ms")
        print(f"  Avg per request: {avg_per_request:.2f}ms")

        # Even under concurrent load, average per request should be < 15ms
        assert avg_per_request < 15, f"Concurrent avg {avg_per_request:.2f}ms exceeds 15ms"


class TestPerformanceSummary:
    """Summary performance report for all authentication operations."""

    @pytest_asyncio.fixture
    async def mock_redis(self):
        """Create a mock Redis client with realistic timing."""
        mock = AsyncMock(spec=Redis)

        async def get_with_delay(*args, **kwargs):
            await asyncio.sleep(0.001)
            return None

        async def setex_with_delay(*args, **kwargs):
            await asyncio.sleep(0.001)
            return True

        mock.get = get_with_delay
        mock.setex = setex_with_delay
        return mock

    @pytest.mark.asyncio
    async def test_performance_requirements_summary(self, mock_redis):
        """Comprehensive performance summary against requirements."""
        print("\n" + "=" * 70)
        print("PERFORMANCE REQUIREMENTS VALIDATION")
        print("=" * 70)

        # Test 1: Blacklist lookup
        service = TokenBlacklistService(redis_client=mock_redis)
        token = create_test_token(subject=str(uuid4()), expires_delta_seconds=3600)

        times_blacklist = []
        for _ in range(100):
            with PerformanceTimer() as timer:
                await service.is_blacklisted(token)
            times_blacklist.append(timer.elapsed_ms)

        avg_blacklist = mean(times_blacklist)
        requirement_blacklist = 10.0

        print(f"\n1. Blacklist Lookup Performance:")
        print(f"   Requirement:  < {requirement_blacklist:.1f}ms")
        print(f"   Actual:       {avg_blacklist:.2f}ms (avg)")
        print(f"   Status:       {'✓ PASS' if avg_blacklist < requirement_blacklist else '✗ FAIL'}")

        # Test 2: JWT verification
        valid_token = create_token(subject=str(uuid4()), expires_delta_seconds=3600)

        times_jwt = []
        for _ in range(100):
            with PerformanceTimer() as timer:
                decode_token(valid_token)
            times_jwt.append(timer.elapsed_ms)

        avg_jwt = mean(times_jwt)
        requirement_jwt = 5.0

        print(f"\n2. JWT Verification Performance:")
        print(f"   Requirement:  < {requirement_jwt:.1f}ms")
        print(f"   Actual:       {avg_jwt:.2f}ms (avg)")
        print(f"   Status:       {'✓ PASS' if avg_jwt < requirement_jwt else '✗ FAIL'}")

        # Test 3: Total auth overhead
        mock_db = AsyncMock()

        times_total = []
        for _ in range(100):
            with PerformanceTimer() as timer:
                await verify_token(valid_token, mock_db)
            times_total.append(timer.elapsed_ms)

        avg_total = mean(times_total)
        requirement_total = 15.0

        print(f"\n3. Total Authentication Overhead:")
        print(f"   Requirement:  < {requirement_total:.1f}ms")
        print(f"   Actual:       {avg_total:.2f}ms (avg)")
        print(f"   Status:       {'✓ PASS' if avg_total < requirement_total else '✗ FAIL'}")

        print("\n" + "=" * 70)
        print("SUMMARY")
        print("=" * 70)

        all_passed = (
            avg_blacklist < requirement_blacklist
            and avg_jwt < requirement_jwt
            and avg_total < requirement_total
        )

        if all_passed:
            print("\n✓ All performance requirements met!")
        else:
            print("\n✗ Some performance requirements not met")

        print(f"\nTotal authentication time budget: {requirement_total:.1f}ms")
        print(f"  - JWT verification:   {avg_jwt:.2f}ms ({avg_jwt/requirement_total*100:.1f}%)")
        print(f"  - Blacklist check:    {avg_blacklist:.2f}ms ({avg_blacklist/requirement_total*100:.1f}%)")
        print(f"  - Other overhead:     {max(0, avg_total - avg_jwt - avg_blacklist):.2f}ms")
        print("=" * 70 + "\n")

        # Assert all requirements met
        assert avg_blacklist < requirement_blacklist, "Blacklist lookup exceeds 10ms"
        assert avg_jwt < requirement_jwt, "JWT verification exceeds 5ms"
        assert avg_total < requirement_total, "Total auth overhead exceeds 15ms"
