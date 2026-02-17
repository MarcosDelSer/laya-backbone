#!/usr/bin/env python3
"""
Security Penetration Testing Script for JWT Verification
Tests various bypass attacks to verify JWT security fixes
"""

import jwt
from datetime import datetime, timezone, timedelta
import sys
import os

# Add ai-service to path
sys.path.insert(0, os.path.join(os.path.dirname(__file__), 'ai-service'))

from app.auth.jwt import decode_token
from app.config import settings
from fastapi import HTTPException


def print_test_header(test_name):
    """Print formatted test header."""
    print(f"\n{'='*80}")
    print(f"TEST: {test_name}")
    print(f"{'='*80}")


def print_result(attack_type, success, details):
    """Print formatted test result."""
    status = "✓ BLOCKED" if not success else "✗ BYPASSED"
    print(f"\n{status} - {attack_type}")
    print(f"Details: {details}")
    return not success  # Return True if attack was blocked (good)


def test_token_without_exp():
    """Test 1: Attempt to use token without expiration claim."""
    print_test_header("Token Without Expiration Claim (exp)")

    try:
        # Create token without exp claim
        now = datetime.now(timezone.utc)
        payload = {
            "sub": "attacker123",
            "iat": int(now.timestamp()),
            "aud": settings.jwt_audience,
            "iss": settings.jwt_issuer,
            # Deliberately omitting 'exp' claim
        }

        malicious_token = jwt.encode(
            payload,
            settings.jwt_secret_key,
            algorithm=settings.jwt_algorithm,
        )

        # Attempt to decode
        decoded = decode_token(malicious_token)
        return print_result(
            "Token without exp claim",
            True,  # Bypass successful
            f"Token was accepted! Payload: {decoded}"
        )

    except HTTPException as e:
        return print_result(
            "Token without exp claim",
            False,  # Bypass blocked
            f"Rejected with status {e.status_code}: {e.detail}"
        )
    except Exception as e:
        return print_result(
            "Token without exp claim",
            False,
            f"Rejected with error: {str(e)}"
        )


def test_token_without_sub():
    """Test 2: Attempt to use token without subject claim."""
    print_test_header("Token Without Subject Claim (sub)")

    try:
        # Create token without sub claim
        now = datetime.now(timezone.utc)
        exp = int((now + timedelta(hours=1)).timestamp())
        payload = {
            # Deliberately omitting 'sub' claim
            "iat": int(now.timestamp()),
            "exp": exp,
            "aud": settings.jwt_audience,
            "iss": settings.jwt_issuer,
        }

        malicious_token = jwt.encode(
            payload,
            settings.jwt_secret_key,
            algorithm=settings.jwt_algorithm,
        )

        # Attempt to decode
        decoded = decode_token(malicious_token)
        return print_result(
            "Token without sub claim",
            True,  # Bypass successful
            f"Token was accepted! Payload: {decoded}"
        )

    except HTTPException as e:
        return print_result(
            "Token without sub claim",
            False,  # Bypass blocked
            f"Rejected with status {e.status_code}: {e.detail}"
        )
    except Exception as e:
        return print_result(
            "Token without sub claim",
            False,
            f"Rejected with error: {str(e)}"
        )


def test_token_with_wrong_audience():
    """Test 3: Attempt to use token with wrong audience."""
    print_test_header("Token With Wrong Audience")

    try:
        # Create token with wrong audience
        now = datetime.now(timezone.utc)
        exp = int((now + timedelta(hours=1)).timestamp())
        payload = {
            "sub": "attacker123",
            "iat": int(now.timestamp()),
            "exp": exp,
            "aud": "malicious-service",  # Wrong audience
            "iss": settings.jwt_issuer,
        }

        malicious_token = jwt.encode(
            payload,
            settings.jwt_secret_key,
            algorithm=settings.jwt_algorithm,
        )

        # Attempt to decode
        decoded = decode_token(malicious_token)
        return print_result(
            "Token with wrong audience",
            True,  # Bypass successful
            f"Token was accepted! Payload: {decoded}"
        )

    except HTTPException as e:
        return print_result(
            "Token with wrong audience",
            False,  # Bypass blocked
            f"Rejected with status {e.status_code}: {e.detail}"
        )
    except Exception as e:
        return print_result(
            "Token with wrong audience",
            False,
            f"Rejected with error: {str(e)}"
        )


def test_token_with_none_algorithm():
    """Test 4: Attempt to use token with 'none' algorithm."""
    print_test_header("Token With 'none' Algorithm")

    try:
        # Create token with 'none' algorithm
        now = datetime.now(timezone.utc)
        exp = int((now + timedelta(hours=1)).timestamp())
        payload = {
            "sub": "attacker123",
            "iat": int(now.timestamp()),
            "exp": exp,
            "aud": settings.jwt_audience,
            "iss": settings.jwt_issuer,
        }

        # Create token with 'none' algorithm (no signature)
        malicious_token = jwt.encode(
            payload,
            key="",  # No key needed for 'none' algorithm
            algorithm="none",
        )

        # Attempt to decode
        decoded = decode_token(malicious_token)
        return print_result(
            "Token with 'none' algorithm",
            True,  # Bypass successful
            f"Token was accepted! Payload: {decoded}"
        )

    except HTTPException as e:
        return print_result(
            "Token with 'none' algorithm",
            False,  # Bypass blocked
            f"Rejected with status {e.status_code}: {e.detail}"
        )
    except Exception as e:
        return print_result(
            "Token with 'none' algorithm",
            False,
            f"Rejected with error: {str(e)}"
        )


def test_token_tampering():
    """Test 5: Attempt to use tampered token (modified payload)."""
    print_test_header("Token Tampering (Modified Payload)")

    try:
        # Create valid token
        now = datetime.now(timezone.utc)
        exp = int((now + timedelta(hours=1)).timestamp())
        payload = {
            "sub": "user123",
            "iat": int(now.timestamp()),
            "exp": exp,
            "aud": settings.jwt_audience,
            "iss": settings.jwt_issuer,
            "role": "user",
        }

        valid_token = jwt.encode(
            payload,
            settings.jwt_secret_key,
            algorithm=settings.jwt_algorithm,
        )

        # Tamper with the token by modifying the payload
        # Decode without verification, modify, re-encode with wrong key
        tampered_payload = jwt.decode(valid_token, options={"verify_signature": False})
        tampered_payload["role"] = "admin"  # Escalate privileges

        # Re-encode with wrong key (attacker doesn't know the secret)
        tampered_token = jwt.encode(
            tampered_payload,
            "wrong_secret_key",
            algorithm=settings.jwt_algorithm,
        )

        # Attempt to decode
        decoded = decode_token(tampered_token)
        return print_result(
            "Tampered token",
            True,  # Bypass successful
            f"Token was accepted! Payload: {decoded}"
        )

    except HTTPException as e:
        return print_result(
            "Tampered token",
            False,  # Bypass blocked
            f"Rejected with status {e.status_code}: {e.detail}"
        )
    except Exception as e:
        return print_result(
            "Tampered token",
            False,
            f"Rejected with error: {str(e)}"
        )


def test_expired_token():
    """Test 6: Attempt to use expired token."""
    print_test_header("Expired Token")

    try:
        # Create expired token
        now = datetime.now(timezone.utc)
        exp = int((now - timedelta(hours=1)).timestamp())  # Expired 1 hour ago
        payload = {
            "sub": "user123",
            "iat": int((now - timedelta(hours=2)).timestamp()),
            "exp": exp,  # Already expired
            "aud": settings.jwt_audience,
            "iss": settings.jwt_issuer,
        }

        expired_token = jwt.encode(
            payload,
            settings.jwt_secret_key,
            algorithm=settings.jwt_algorithm,
        )

        # Attempt to decode
        decoded = decode_token(expired_token)
        return print_result(
            "Expired token",
            True,  # Bypass successful
            f"Token was accepted! Payload: {decoded}"
        )

    except HTTPException as e:
        return print_result(
            "Expired token",
            False,  # Bypass blocked
            f"Rejected with status {e.status_code}: {e.detail}"
        )
    except Exception as e:
        return print_result(
            "Expired token",
            False,
            f"Rejected with error: {str(e)}"
        )


def test_token_without_iat():
    """Test 7: Attempt to use token without issued-at claim."""
    print_test_header("Token Without Issued-At Claim (iat)")

    try:
        # Create token without iat claim
        now = datetime.now(timezone.utc)
        exp = int((now + timedelta(hours=1)).timestamp())
        payload = {
            "sub": "attacker123",
            # Deliberately omitting 'iat' claim
            "exp": exp,
            "aud": settings.jwt_audience,
            "iss": settings.jwt_issuer,
        }

        malicious_token = jwt.encode(
            payload,
            settings.jwt_secret_key,
            algorithm=settings.jwt_algorithm,
        )

        # Attempt to decode
        decoded = decode_token(malicious_token)
        return print_result(
            "Token without iat claim",
            True,  # Bypass successful
            f"Token was accepted! Payload: {decoded}"
        )

    except HTTPException as e:
        return print_result(
            "Token without iat claim",
            False,  # Bypass blocked
            f"Rejected with status {e.status_code}: {e.detail}"
        )
    except Exception as e:
        return print_result(
            "Token without iat claim",
            False,
            f"Rejected with error: {str(e)}"
        )


def test_token_with_wrong_issuer():
    """Test 8: Attempt to use token with wrong issuer."""
    print_test_header("Token With Wrong Issuer")

    try:
        # Create token with wrong issuer
        now = datetime.now(timezone.utc)
        exp = int((now + timedelta(hours=1)).timestamp())
        payload = {
            "sub": "attacker123",
            "iat": int(now.timestamp()),
            "exp": exp,
            "aud": settings.jwt_audience,
            "iss": "malicious-issuer",  # Wrong issuer
        }

        malicious_token = jwt.encode(
            payload,
            settings.jwt_secret_key,
            algorithm=settings.jwt_algorithm,
        )

        # Attempt to decode
        decoded = decode_token(malicious_token)
        return print_result(
            "Token with wrong issuer",
            True,  # Bypass successful
            f"Token was accepted! Payload: {decoded}"
        )

    except HTTPException as e:
        return print_result(
            "Token with wrong issuer",
            False,  # Bypass blocked
            f"Rejected with status {e.status_code}: {e.detail}"
        )
    except Exception as e:
        return print_result(
            "Token with wrong issuer",
            False,
            f"Rejected with error: {str(e)}"
        )


def test_token_with_wrong_algorithm():
    """Test 9: Attempt to use token with different algorithm."""
    print_test_header("Token With Wrong Algorithm (HS512 instead of HS256)")

    try:
        # Create token with wrong algorithm
        now = datetime.now(timezone.utc)
        exp = int((now + timedelta(hours=1)).timestamp())
        payload = {
            "sub": "attacker123",
            "iat": int(now.timestamp()),
            "exp": exp,
            "aud": settings.jwt_audience,
            "iss": settings.jwt_issuer,
        }

        # Use HS512 instead of HS256
        malicious_token = jwt.encode(
            payload,
            settings.jwt_secret_key,
            algorithm="HS512",
        )

        # Attempt to decode
        decoded = decode_token(malicious_token)
        return print_result(
            "Token with wrong algorithm (HS512)",
            True,  # Bypass successful
            f"Token was accepted! Payload: {decoded}"
        )

    except HTTPException as e:
        return print_result(
            "Token with wrong algorithm",
            False,  # Bypass blocked
            f"Rejected with status {e.status_code}: {e.detail}"
        )
    except Exception as e:
        return print_result(
            "Token with wrong algorithm",
            False,
            f"Rejected with error: {str(e)}"
        )


def main():
    """Run all penetration tests."""
    print("\n" + "="*80)
    print("JWT SECURITY PENETRATION TESTING")
    print("Testing JWT authentication bypass vulnerabilities")
    print("="*80)

    results = []

    # Run all tests
    results.append(("Token without exp claim", test_token_without_exp()))
    results.append(("Token without sub claim", test_token_without_sub()))
    results.append(("Token with wrong audience", test_token_with_wrong_audience()))
    results.append(("Token with 'none' algorithm", test_token_with_none_algorithm()))
    results.append(("Token tampering", test_token_tampering()))
    results.append(("Expired token", test_expired_token()))
    results.append(("Token without iat claim", test_token_without_iat()))
    results.append(("Token with wrong issuer", test_token_with_wrong_issuer()))
    results.append(("Token with wrong algorithm", test_token_with_wrong_algorithm()))

    # Print summary
    print("\n" + "="*80)
    print("SECURITY TEST SUMMARY")
    print("="*80)

    passed = sum(1 for _, result in results if result)
    total = len(results)

    for test_name, result in results:
        status = "✓ PASS" if result else "✗ FAIL"
        print(f"{status} - {test_name}")

    print(f"\nTotal: {passed}/{total} attacks blocked")

    if passed == total:
        print("\n✓ ALL SECURITY TESTS PASSED - No authentication bypass possible")
        return 0
    else:
        print(f"\n✗ SECURITY VULNERABILITY - {total - passed} attack(s) succeeded")
        return 1


if __name__ == "__main__":
    exit(main())
