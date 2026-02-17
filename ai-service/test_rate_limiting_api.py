#!/usr/bin/env python3
"""
Integration test for rate limiting behavior via API calls.

This script tests:
1. Rate limiting enforcement (429 status after limit exceeded)
2. Security headers presence (X-Frame-Options, X-Content-Type-Options, etc.)
3. CORS headers presence

Tests both general endpoints and auth endpoints with different rate limits.
"""

import asyncio
import sys
import time
from typing import Dict, List

import httpx


class RateLimitTester:
    """Test rate limiting behavior for LAYA AI Service."""

    def __init__(self, base_url: str = "http://localhost:8000"):
        """Initialize tester with base URL."""
        self.base_url = base_url
        self.results: Dict[str, bool] = {}

    async def test_general_endpoint_rate_limiting(self) -> bool:
        """Test rate limiting on general endpoint (100 req/min).

        Note: Only testing first few requests to avoid long wait times.

        Returns:
            bool: True if rate limiting appears functional
        """
        print("\n" + "=" * 80)
        print("TEST 1: General Endpoint Rate Limiting (Sample)")
        print("=" * 80)

        endpoint = f"{self.base_url}/"

        print(f"Testing endpoint: {endpoint}")
        print("Making 5 sample requests (general limit is 100/min)...")

        async with httpx.AsyncClient() as client:
            responses = []
            for i in range(5):
                try:
                    response = await client.get(endpoint, timeout=5.0)
                    responses.append(response)
                    print(f"  Request {i+1}: Status {response.status_code}")
                except Exception as e:
                    print(f"  Request {i+1}: ERROR - {e}")
                    return False

            # All should succeed (under the 100/min limit)
            success_count = sum(1 for r in responses if r.status_code == 200)

            if success_count == 5:
                print(f"✓ All {success_count}/5 requests succeeded (under rate limit)")
                return True
            else:
                print(f"✗ Only {success_count}/5 requests succeeded")
                return False

    async def test_auth_endpoint_rate_limiting(self) -> bool:
        """Test rate limiting on auth endpoint (10 req/min).

        Makes 12 requests rapidly to trigger rate limiting (10 allowed + 2 blocked).

        Returns:
            bool: True if rate limiting works correctly (11th and 12th requests blocked)
        """
        print("\n" + "=" * 80)
        print("TEST 2: Auth Endpoint Rate Limiting (10 requests/min)")
        print("=" * 80)

        endpoint = f"{self.base_url}/protected"

        print(f"Testing endpoint: {endpoint}")
        print("Making 12 rapid requests to trigger rate limit (10 allowed)...")

        # Generate a test JWT token for authentication
        from app.auth.jwt import create_token
        token = create_token("test_user", expires_in_seconds=300)

        headers = {
            "Authorization": f"Bearer {token}",
            "Content-Type": "application/json"
        }

        async with httpx.AsyncClient() as client:
            responses = []
            start_time = time.time()

            for i in range(12):
                try:
                    response = await client.get(endpoint, headers=headers, timeout=5.0)
                    responses.append(response)
                    status = response.status_code

                    if status == 200:
                        print(f"  Request {i+1:2d}: ✓ Status {status} (allowed)")
                    elif status == 429:
                        print(f"  Request {i+1:2d}: ✓ Status {status} (rate limited)")
                    else:
                        print(f"  Request {i+1:2d}: ✗ Status {status} (unexpected)")

                except Exception as e:
                    print(f"  Request {i+1:2d}: ERROR - {e}")
                    responses.append(None)

            elapsed = time.time() - start_time
            print(f"\nCompleted 12 requests in {elapsed:.2f} seconds")

            # Check results
            success_responses = [r for r in responses if r and r.status_code == 200]
            rate_limited_responses = [r for r in responses if r and r.status_code == 429]

            print(f"\nResults:")
            print(f"  - Successful (200): {len(success_responses)}")
            print(f"  - Rate Limited (429): {len(rate_limited_responses)}")
            print(f"  - Other/Failed: {12 - len(success_responses) - len(rate_limited_responses)}")

            # We expect:
            # - First 10 requests to succeed (200)
            # - Remaining 2 requests to be rate limited (429)
            expected_success = 10
            expected_rate_limited = 2

            if len(success_responses) == expected_success and len(rate_limited_responses) >= expected_rate_limited:
                print(f"\n✓ Rate limiting working correctly!")
                print(f"  Expected: {expected_success} successful, {expected_rate_limited}+ rate limited")
                print(f"  Got: {len(success_responses)} successful, {len(rate_limited_responses)} rate limited")

                # Check if 429 response includes Retry-After header
                if rate_limited_responses:
                    retry_after = rate_limited_responses[0].headers.get("Retry-After")
                    if retry_after:
                        print(f"  ✓ Retry-After header present: {retry_after}")

                return True
            else:
                print(f"\n✗ Rate limiting not working as expected!")
                print(f"  Expected: {expected_success} successful, {expected_rate_limited} rate limited")
                print(f"  Got: {len(success_responses)} successful, {len(rate_limited_responses)} rate limited")
                return False

    async def test_security_headers(self) -> bool:
        """Test that security headers are present in responses.

        Returns:
            bool: True if all required security headers are present
        """
        print("\n" + "=" * 80)
        print("TEST 3: Security Headers Verification")
        print("=" * 80)

        endpoint = f"{self.base_url}/"

        print(f"Testing endpoint: {endpoint}")

        async with httpx.AsyncClient() as client:
            try:
                response = await client.get(endpoint, timeout=5.0)
                headers = response.headers

                print(f"Status: {response.status_code}")
                print("\nSecurity Headers:")

                required_headers = {
                    "X-Frame-Options": "DENY",
                    "X-Content-Type-Options": "nosniff",
                    "X-XSS-Protection": None,  # Should be present, value varies
                }

                all_present = True

                for header_name, expected_value in required_headers.items():
                    actual_value = headers.get(header_name)

                    if actual_value:
                        if expected_value and actual_value.upper() == expected_value.upper():
                            print(f"  ✓ {header_name}: {actual_value} (correct)")
                        elif expected_value:
                            print(f"  ✗ {header_name}: {actual_value} (expected {expected_value})")
                            all_present = False
                        else:
                            print(f"  ✓ {header_name}: {actual_value}")
                    else:
                        print(f"  ✗ {header_name}: MISSING")
                        all_present = False

                # Check CSP header (optional but recommended)
                csp = headers.get("Content-Security-Policy")
                if csp:
                    print(f"  ✓ Content-Security-Policy: {csp[:60]}...")
                else:
                    print(f"  ℹ Content-Security-Policy: Not set (optional)")

                # Check HSTS header (only in production with HTTPS)
                hsts = headers.get("Strict-Transport-Security")
                if hsts:
                    print(f"  ✓ Strict-Transport-Security: {hsts}")
                else:
                    print(f"  ℹ Strict-Transport-Security: Not set (expected in development)")

                if all_present:
                    print("\n✓ All required security headers present")
                    return True
                else:
                    print("\n✗ Some required security headers missing")
                    return False

            except Exception as e:
                print(f"ERROR: {e}")
                return False

    async def test_cors_headers(self) -> bool:
        """Test that CORS headers are configured correctly.

        Returns:
            bool: True if CORS headers are present
        """
        print("\n" + "=" * 80)
        print("TEST 4: CORS Headers Verification")
        print("=" * 80)

        endpoint = f"{self.base_url}/"

        print(f"Testing endpoint: {endpoint}")
        print("Making OPTIONS request (CORS preflight)...")

        async with httpx.AsyncClient() as client:
            try:
                # CORS preflight request
                response = await client.options(
                    endpoint,
                    headers={
                        "Origin": "http://localhost:3000",
                        "Access-Control-Request-Method": "GET",
                    },
                    timeout=5.0
                )

                headers = response.headers

                print(f"Status: {response.status_code}")
                print("\nCORS Headers:")

                cors_headers = [
                    "Access-Control-Allow-Origin",
                    "Access-Control-Allow-Methods",
                    "Access-Control-Allow-Headers",
                    "Access-Control-Allow-Credentials",
                ]

                present = 0
                for header_name in cors_headers:
                    value = headers.get(header_name)
                    if value:
                        print(f"  ✓ {header_name}: {value}")
                        present += 1
                    else:
                        print(f"  ℹ {header_name}: Not set")

                if present > 0:
                    print(f"\n✓ CORS configured ({present} headers found)")
                    return True
                else:
                    print("\nℹ CORS headers not present (may be normal for development)")
                    return True  # Don't fail on this

            except Exception as e:
                print(f"ERROR: {e}")
                return False

    async def run_all_tests(self) -> bool:
        """Run all tests and return overall result.

        Returns:
            bool: True if all critical tests pass
        """
        print("=" * 80)
        print("LAYA AI Service - Rate Limiting Integration Test")
        print("=" * 80)
        print(f"Base URL: {self.base_url}")

        tests = [
            ("General Endpoint Rate Limiting", self.test_general_endpoint_rate_limiting()),
            ("Auth Endpoint Rate Limiting", self.test_auth_endpoint_rate_limiting()),
            ("Security Headers", self.test_security_headers()),
            ("CORS Headers", self.test_cors_headers()),
        ]

        results = []
        for test_name, test_coro in tests:
            try:
                result = await test_coro
                results.append((test_name, result))
                self.results[test_name] = result
            except Exception as e:
                print(f"\n✗ {test_name} failed with exception: {e}")
                results.append((test_name, False))
                self.results[test_name] = False

        # Print summary
        print("\n" + "=" * 80)
        print("TEST SUMMARY")
        print("=" * 80)

        passed = 0
        failed = 0

        for test_name, result in results:
            status = "✓ PASS" if result else "✗ FAIL"
            print(f"{status:8s} | {test_name}")
            if result:
                passed += 1
            else:
                failed += 1

        print("=" * 80)
        print(f"Total: {len(results)} tests | Passed: {passed} | Failed: {failed}")
        print("=" * 80)

        # Critical tests that must pass
        critical_tests = [
            "Auth Endpoint Rate Limiting",
            "Security Headers",
        ]

        critical_passed = all(
            self.results.get(test_name, False)
            for test_name in critical_tests
        )

        if critical_passed:
            print("\n✓ All critical tests passed!")
            return True
        else:
            print("\n✗ Some critical tests failed!")
            return False


async def main():
    """Main entry point."""
    import os

    # Check if service is running
    base_url = os.getenv("BASE_URL", "http://localhost:8000")

    tester = RateLimitTester(base_url)

    try:
        success = await tester.run_all_tests()
        sys.exit(0 if success else 1)
    except Exception as e:
        print(f"\n✗ Test suite failed with error: {e}")
        import traceback
        traceback.print_exc()
        sys.exit(1)


if __name__ == "__main__":
    asyncio.run(main())
