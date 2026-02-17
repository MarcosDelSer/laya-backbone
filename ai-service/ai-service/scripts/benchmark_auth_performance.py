#!/usr/bin/env python3
"""Benchmark script for authentication performance testing.

This script measures the actual performance of JWT verification and Redis
blacklist operations against the requirements:
- Redis blacklist lookup: < 10ms
- JWT verification: < 5ms
- Total auth overhead: < 15ms per request

Usage:
    python scripts/benchmark_auth_performance.py
"""

import asyncio
import sys
import time
from datetime import datetime, timedelta, timezone
from pathlib import Path
from statistics import mean, median
from typing import List

# Add parent directory to path for imports
sys.path.insert(0, str(Path(__file__).parent.parent))

from app.auth.blacklist import TokenBlacklistService
from app.auth.jwt import create_token, decode_token, verify_token
from app.redis_client import get_redis_client


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


def print_header(title: str):
    """Print a formatted section header."""
    print("\n" + "=" * 70)
    print(title)
    print("=" * 70)


def print_metric(name: str, value: float, requirement: float, unit: str = "ms"):
    """Print a formatted performance metric with pass/fail status."""
    status = "✓ PASS" if value < requirement else "✗ FAIL"
    status_color = "\033[92m" if value < requirement else "\033[91m"
    reset_color = "\033[0m"

    print(f"\n{name}:")
    print(f"  Requirement:  < {requirement:.1f}{unit}")
    print(f"  Actual:       {value:.2f}{unit} (avg)")
    print(f"  Status:       {status_color}{status}{reset_color}")


async def benchmark_jwt_operations(num_iterations: int = 100) -> tuple[float, float]:
    """Benchmark JWT token creation and verification.

    Args:
        num_iterations: Number of iterations to run

    Returns:
        tuple: (avg_create_time_ms, avg_decode_time_ms)
    """
    print_header("1. JWT OPERATIONS PERFORMANCE")

    # Benchmark token creation
    create_times: List[float] = []
    for _ in range(num_iterations):
        with PerformanceTimer() as timer:
            token = create_token(
                subject="benchmark-user",
                expires_delta_seconds=3600,
                additional_claims={"role": "teacher", "school_id": "test-school"},
            )
        create_times.append(timer.elapsed_ms)

    avg_create = mean(create_times)
    median_create = median(create_times)
    max_create = max(create_times)

    print(f"\nToken Creation (n={num_iterations}):")
    print(f"  Average: {avg_create:.3f}ms")
    print(f"  Median:  {median_create:.3f}ms")
    print(f"  Max:     {max_create:.3f}ms")

    # Benchmark token decoding
    test_token = create_token(
        subject="benchmark-user",
        expires_delta_seconds=3600,
        additional_claims={"role": "teacher"},
    )

    decode_times: List[float] = []
    for _ in range(num_iterations):
        with PerformanceTimer() as timer:
            payload = decode_token(test_token)
        decode_times.append(timer.elapsed_ms)

    avg_decode = mean(decode_times)
    median_decode = median(decode_times)
    max_decode = max(decode_times)

    print(f"\nToken Decode (n={num_iterations}):")
    print(f"  Average: {avg_decode:.3f}ms")
    print(f"  Median:  {median_decode:.3f}ms")
    print(f"  Max:     {max_decode:.3f}ms")

    return avg_create, avg_decode


async def benchmark_blacklist_operations(num_iterations: int = 100) -> float:
    """Benchmark Redis blacklist operations.

    Args:
        num_iterations: Number of iterations to run

    Returns:
        float: Average blacklist check time in milliseconds
    """
    print_header("2. REDIS BLACKLIST PERFORMANCE")

    try:
        redis = await get_redis_client()
        service = TokenBlacklistService(redis_client=redis)

        # Create test token
        test_token = create_token(
            subject="benchmark-user",
            expires_delta_seconds=3600,
        )

        # Warmup: Run a few iterations to warm up connections
        print("\nWarming up Redis connections...")
        for _ in range(10):
            await service.is_blacklisted(test_token)

        # Benchmark is_blacklisted
        print(f"Running {num_iterations} blacklist checks...")
        check_times: List[float] = []
        for _ in range(num_iterations):
            with PerformanceTimer() as timer:
                await service.is_blacklisted(test_token)
            check_times.append(timer.elapsed_ms)

        avg_check = mean(check_times)
        median_check = median(check_times)
        max_check = max(check_times)
        p95_check = sorted(check_times)[int(num_iterations * 0.95)]
        p99_check = sorted(check_times)[int(num_iterations * 0.99)]

        print(f"\nBlacklist Check (n={num_iterations}):")
        print(f"  Average: {avg_check:.3f}ms")
        print(f"  Median:  {median_check:.3f}ms")
        print(f"  P95:     {p95_check:.3f}ms")
        print(f"  P99:     {p99_check:.3f}ms")
        print(f"  Max:     {max_check:.3f}ms")

        # Benchmark add_to_blacklist
        add_times: List[float] = []
        for i in range(min(50, num_iterations)):
            token = create_token(
                subject=f"user-{i}",
                expires_delta_seconds=3600,
            )
            expires_at = datetime.now(timezone.utc) + timedelta(hours=1)

            with PerformanceTimer() as timer:
                await service.add_to_blacklist(
                    token=token,
                    user_id=f"user-{i}",
                    expires_at=expires_at,
                )
            add_times.append(timer.elapsed_ms)

        avg_add = mean(add_times)
        print(f"\nBlacklist Add (n={len(add_times)}):")
        print(f"  Average: {avg_add:.3f}ms")

        return avg_check

    except Exception as e:
        print(f"\n✗ Error connecting to Redis: {e}")
        print("Make sure Redis is running: redis-server")
        return 999.0  # Return high value to fail the test


async def benchmark_complete_auth_flow(num_iterations: int = 100) -> float:
    """Benchmark complete authentication flow.

    Args:
        num_iterations: Number of iterations to run

    Returns:
        float: Average total auth time in milliseconds
    """
    print_header("3. COMPLETE AUTHENTICATION FLOW")

    try:
        redis = await get_redis_client()

        # Create test token
        test_token = create_token(
            subject="benchmark-user",
            expires_delta_seconds=3600,
            additional_claims={"role": "teacher"},
        )

        # Warmup
        print("\nWarming up...")
        mock_db = None  # verify_token uses Redis, not DB
        for _ in range(10):
            await verify_token(test_token, mock_db)

        # Benchmark complete flow: decode + blacklist check
        print(f"Running {num_iterations} complete auth verifications...")
        auth_times: List[float] = []
        for _ in range(num_iterations):
            with PerformanceTimer() as timer:
                await verify_token(test_token, mock_db)
            auth_times.append(timer.elapsed_ms)

        avg_auth = mean(auth_times)
        median_auth = median(auth_times)
        max_auth = max(auth_times)
        p95_auth = sorted(auth_times)[int(num_iterations * 0.95)]
        p99_auth = sorted(auth_times)[int(num_iterations * 0.99)]

        print(f"\nComplete Auth Flow (n={num_iterations}):")
        print(f"  Average: {avg_auth:.3f}ms")
        print(f"  Median:  {median_auth:.3f}ms")
        print(f"  P95:     {p95_auth:.3f}ms")
        print(f"  P99:     {p99_auth:.3f}ms")
        print(f"  Max:     {max_auth:.3f}ms")

        return avg_auth

    except Exception as e:
        print(f"\n✗ Error in auth flow: {e}")
        return 999.0


async def benchmark_concurrent_load(num_concurrent: int = 20) -> float:
    """Benchmark performance under concurrent load.

    Args:
        num_concurrent: Number of concurrent requests

    Returns:
        float: Average time per request under concurrent load
    """
    print_header("4. CONCURRENT LOAD PERFORMANCE")

    try:
        # Pre-create tokens
        tokens = [
            create_token(
                subject=f"user-{i}",
                expires_delta_seconds=3600,
                additional_claims={"role": "teacher"},
            )
            for i in range(num_concurrent)
        ]

        mock_db = None

        print(f"\nTesting {num_concurrent} concurrent auth requests...")
        with PerformanceTimer() as timer:
            await asyncio.gather(*[verify_token(token, mock_db) for token in tokens])

        total_time = timer.elapsed_ms
        avg_per_request = total_time / num_concurrent

        print(f"\nConcurrent Requests (n={num_concurrent}):")
        print(f"  Total time:     {total_time:.3f}ms")
        print(f"  Avg per request: {avg_per_request:.3f}ms")
        print(f"  Throughput:     {num_concurrent / (total_time / 1000):.0f} req/sec")

        return avg_per_request

    except Exception as e:
        print(f"\n✗ Error in concurrent load test: {e}")
        return 999.0


async def main():
    """Run all performance benchmarks and report results."""
    print("\n" + "=" * 70)
    print("LAYA AI SERVICE - AUTHENTICATION PERFORMANCE BENCHMARK")
    print("=" * 70)
    print("\nThis benchmark validates the following requirements:")
    print("  - Redis blacklist lookup:  < 10ms")
    print("  - JWT verification:        < 5ms")
    print("  - Total auth overhead:     < 15ms per request")

    num_iterations = 100

    # Run benchmarks
    avg_create, avg_decode = await benchmark_jwt_operations(num_iterations)
    avg_blacklist = await benchmark_blacklist_operations(num_iterations)
    avg_auth = await benchmark_complete_auth_flow(num_iterations)
    avg_concurrent = await benchmark_concurrent_load(20)

    # Print summary
    print_header("PERFORMANCE REQUIREMENTS VALIDATION")

    print_metric("1. JWT Verification", avg_decode, 5.0)
    print_metric("2. Redis Blacklist Lookup", avg_blacklist, 10.0)
    print_metric("3. Total Authentication Overhead", avg_auth, 15.0)
    print_metric("4. Concurrent Load (avg per request)", avg_concurrent, 15.0)

    # Overall summary
    print_header("SUMMARY")

    all_passed = (
        avg_decode < 5.0
        and avg_blacklist < 10.0
        and avg_auth < 15.0
        and avg_concurrent < 15.0
    )

    if all_passed:
        print("\n✓ All performance requirements met!")
        print("\nBreakdown of authentication time budget:")
        print(f"  Total budget:         15.0ms")
        print(f"  - JWT verification:   {avg_decode:.2f}ms ({avg_decode/15.0*100:.1f}%)")
        print(f"  - Blacklist check:    {avg_blacklist:.2f}ms ({avg_blacklist/15.0*100:.1f}%)")
        print(f"  - Other overhead:     {max(0, avg_auth - avg_decode - avg_blacklist):.2f}ms")
        exit_code = 0
    else:
        print("\n✗ Some performance requirements not met")
        print("\nPlease investigate:")
        if avg_decode >= 5.0:
            print("  - JWT verification is too slow")
        if avg_blacklist >= 10.0:
            print("  - Redis blacklist lookup is too slow (check Redis latency)")
        if avg_auth >= 15.0:
            print("  - Total auth overhead exceeds budget")
        exit_code = 1

    print("=" * 70 + "\n")

    sys.exit(exit_code)


if __name__ == "__main__":
    asyncio.run(main())
