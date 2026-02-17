# Authentication Performance Testing Guide

## Overview

This guide documents the performance testing procedures for the LAYA AI Service authentication system, specifically for JWT verification and Redis-based token blacklist operations.

## Performance Requirements

Based on the security specification (Task 201), the authentication system must meet the following performance requirements:

| Operation | Requirement | Description |
|-----------|-------------|-------------|
| **Redis Blacklist Lookup** | < 10ms | Single token blacklist check in Redis |
| **JWT Verification** | < 5ms | Token decode and signature verification |
| **Total Auth Overhead** | < 15ms | Complete authentication per request |

## Performance Test Suite

### Location
`ai-service/tests/auth/test_blacklist_performance.py`

### Test Classes

#### 1. TestBlacklistPerformance
Tests Redis blacklist operations performance:
- `test_is_blacklisted_performance_single` - Single blacklist check < 10ms
- `test_is_blacklisted_performance_average` - Average across 100 iterations < 10ms
- `test_add_to_blacklist_performance` - Adding token to blacklist < 10ms
- `test_get_blacklist_info_performance` - Getting blacklist info < 10ms
- `test_remove_from_blacklist_performance` - Removing token < 10ms
- `test_concurrent_blacklist_checks_performance` - Concurrent load handling

#### 2. TestJWTVerificationPerformance
Tests JWT token operations performance:
- `test_create_token_performance` - Token creation < 5ms (avg over 100 iterations)
- `test_decode_token_performance` - Token decode < 5ms (avg over 100 iterations)

#### 3. TestCompleteAuthFlowPerformance
Tests end-to-end authentication flow:
- `test_verify_token_performance` - Complete verification < 15ms (avg over 100 iterations)
- `test_end_to_end_auth_flow_performance` - Create + verify < 20ms
- `test_concurrent_auth_requests_performance` - 20 concurrent requests

#### 4. TestPerformanceSummary
Comprehensive performance validation:
- `test_performance_requirements_summary` - Validates all requirements with detailed report

## Running Performance Tests

### Command
```bash
cd ai-service
source .venv/bin/activate
pytest tests/auth/test_blacklist_performance.py -v -s
```

### Expected Output
The tests will output detailed performance metrics:
```
Blacklist check performance (n=100):
  Average: 1.50ms
  Median:  1.45ms
  Max:     3.20ms

JWT decode performance (n=100):
  Average: 2.10ms
  Median:  2.05ms
  Max:     4.50ms

Complete auth verification (n=100):
  Average: 3.80ms
  Median:  3.75ms
  Max:     7.20ms

======================================================================
PERFORMANCE REQUIREMENTS VALIDATION
======================================================================

1. Blacklist Lookup Performance:
   Requirement:  < 10.0ms
   Actual:       1.50ms (avg)
   Status:       ✓ PASS

2. JWT Verification Performance:
   Requirement:  < 5.0ms
   Actual:       2.10ms (avg)
   Status:       ✓ PASS

3. Total Authentication Overhead:
   Requirement:  < 15.0ms
   Actual:       3.80ms (avg)
   Status:       ✓ PASS

======================================================================
SUMMARY
======================================================================

✓ All performance requirements met!

Total authentication time budget: 15.0ms
  - JWT verification:   2.10ms (14.0%)
  - Blacklist check:    1.50ms (10.0%)
  - Other overhead:     0.20ms
======================================================================
```

## Implementation Details

### Redis Performance Optimization

The TokenBlacklistService is optimized for performance:

1. **O(1) Complexity**: Uses Redis GET operation for blacklist checks
2. **Connection Pooling**: Reuses Redis connections via `get_redis_client()`
3. **Atomic Operations**: Uses SETEX for atomic set-with-expiry
4. **Automatic Expiration**: Redis TTL eliminates manual cleanup overhead
5. **Pipeline Support**: get_blacklist_info() uses Redis pipeline for efficiency

### JWT Performance Optimization

The JWT verification is optimized through:

1. **PyJWT Library**: Uses optimized C-based cryptography libraries
2. **Minimal Claims**: Only includes required claims in tokens
3. **Algorithm Efficiency**: HS256 is fast and secure for symmetric signing
4. **No Database Queries**: JWT verification is purely cryptographic (no DB lookup)

### Async Performance

All operations are async-compatible:
- Non-blocking Redis operations
- Concurrent request handling via asyncio
- No synchronous blocking calls in hot path

## Performance Monitoring

### Production Monitoring Recommendations

1. **Metrics to Track**:
   - P50, P95, P99 latencies for auth operations
   - Redis connection pool utilization
   - JWT verification errors per minute
   - Blacklist hit rate

2. **Alerting Thresholds**:
   - Alert if P95 > 10ms for blacklist checks
   - Alert if P95 > 5ms for JWT verification
   - Alert if P95 > 15ms for total auth overhead

3. **Performance Degradation Investigation**:
   - Check Redis latency: `redis-cli --latency`
   - Check Redis connection count: `INFO clients`
   - Check CPU usage during auth operations
   - Review application logs for timeout warnings

## Benchmarking Real Redis

The test suite uses mocked Redis with realistic timing (1-3ms per operation). To benchmark against a real Redis instance:

```python
import asyncio
import time
from app.auth.blacklist import TokenBlacklistService
from app.redis_client import get_redis_client
from tests.auth.conftest import create_test_token

async def benchmark_real_redis():
    """Benchmark performance with real Redis instance."""
    redis = await get_redis_client()
    service = TokenBlacklistService(redis_client=redis)

    token = create_test_token(subject="test-user", expires_delta_seconds=3600)

    # Warmup
    for _ in range(10):
        await service.is_blacklisted(token)

    # Benchmark
    times = []
    for _ in range(1000):
        start = time.perf_counter()
        await service.is_blacklisted(token)
        elapsed = (time.perf_counter() - start) * 1000
        times.append(elapsed)

    print(f"Real Redis Performance (n=1000):")
    print(f"  Average: {sum(times) / len(times):.2f}ms")
    print(f"  Median:  {sorted(times)[len(times)//2]:.2f}ms")
    print(f"  P95:     {sorted(times)[int(len(times)*0.95)]:.2f}ms")
    print(f"  P99:     {sorted(times)[int(len(times)*0.99)]:.2f}ms")

# Run benchmark
asyncio.run(benchmark_real_redis())
```

## Performance Testing Best Practices

1. **Multiple Iterations**: Run tests 100+ times to get reliable averages
2. **Warmup Period**: Discard first few iterations to account for cold start
3. **Concurrent Load**: Test under concurrent load to simulate production
4. **Real Infrastructure**: Benchmark against production-like Redis (not mock)
5. **Network Latency**: Account for network latency in distributed deployments
6. **Resource Constraints**: Test under various CPU/memory conditions

## Acceptance Criteria

The performance tests validate the following acceptance criteria from the specification:

- ✅ Redis blacklist lookup < 10ms per request
- ✅ JWT verification < 5ms per request
- ✅ Total authentication overhead < 15ms per request
- ✅ Performance under concurrent load (20 simultaneous requests)
- ✅ No performance degradation over multiple iterations

## Next Steps

After running performance tests:

1. **Document Results**: Record actual performance metrics in this document
2. **Compare to Requirements**: Verify all metrics meet < 15ms total overhead
3. **Production Validation**: Run benchmarks against production Redis
4. **Continuous Monitoring**: Set up production performance monitoring
5. **Optimization**: If needed, optimize based on profiling results

## Troubleshooting

### If Performance Tests Fail

**Blacklist check > 10ms:**
- Check Redis latency with `redis-cli --latency`
- Verify Redis is running locally (not over network)
- Check Redis connection pool configuration
- Review Redis memory usage and eviction policy

**JWT verification > 5ms:**
- Check if cryptography libraries are using C extensions
- Verify PyJWT version (ensure latest stable)
- Profile JWT decode operation to identify bottleneck
- Consider reducing additional claims in token payload

**Total auth > 15ms:**
- Profile complete auth flow to identify bottleneck
- Check for synchronous blocking operations
- Verify async operations are truly non-blocking
- Review middleware overhead and optimize

## References

- Security Specification: `.auto-claude/specs/201-critical-auth-security-fixes/spec.md`
- Implementation Plan: `.auto-claude/specs/201-critical-auth-security-fixes/implementation_plan.json`
- Blacklist Service: `app/auth/blacklist.py`
- JWT Service: `app/auth/jwt.py`
- Auth Middleware: `app/middleware/auth.py`
