"""Security verification tests confirming JWT vulnerabilities are FIXED.

SECURITY NOTICE:
These tests verify that JWT authentication bypass vulnerabilities have been
properly remediated. They should now FAIL when run against the vulnerable code
and PASS when run against the fixed code.

Vulnerabilities that WERE present (now FIXED):
1. Tokens without 'exp' claim are accepted (no expiration enforcement) - FIXED
2. Tokens without 'sub' claim are accepted (no subject enforcement) - FIXED
3. Tokens without 'iat' claim are accepted (no issued-at enforcement) - FIXED
4. No audience (aud) claim validation - FIXED
5. No issuer (iss) claim validation - FIXED
6. Potential acceptance of 'none' algorithm tokens - FIXED

Fix Applied:
PyJWT's jwt.decode() now requires critical claims via the options parameter
with require=['exp', 'sub', 'iat'] and verify_exp=True, plus audience and
issuer validation.

Expected Test Results POST-FIX:
- All vulnerability tests should PASS (because they now expect HTTPException)
- All control tests should PASS (properly formed tokens still work)
"""

from datetime import datetime, timedelta, timezone

import jwt
import pytest
from fastapi import HTTPException

from app.auth.jwt import decode_token
from app.config import settings


class TestJWTBypassVulnerabilities:
    """Proof-of-concept tests demonstrating JWT authentication bypass."""

    def test_token_without_exp_claim_accepted(self):
        """FIXED: Token without expiration claim is now REJECTED.

        This verifies that the critical security flaw where tokens without
        expiration were accepted has been fixed.

        Expected behavior BEFORE fix: Token accepted (vulnerability)
        Expected behavior AFTER fix: HTTPException 401 raised (FIXED)
        """
        # Create token without 'exp' claim
        payload = {
            "sub": "malicious_user_123",
            "iat": int(datetime.now(timezone.utc).timestamp()),
            # Deliberately omitting 'exp' claim
        }
        token = jwt.encode(
            payload,
            settings.jwt_secret_key,
            algorithm=settings.jwt_algorithm,
        )

        # FIXED: This is now properly rejected
        with pytest.raises(HTTPException) as exc_info:
            decode_token(token)
        assert exc_info.value.status_code == 401
        assert "Invalid token" in exc_info.value.detail

    def test_token_without_sub_claim_accepted(self):
        """FIXED: Token without subject claim is now REJECTED.

        The 'sub' claim identifies the user. The fix ensures tokens without
        it are rejected, preventing authentication without user identification.

        Expected behavior BEFORE fix: Token accepted (vulnerability)
        Expected behavior AFTER fix: HTTPException 401 raised (FIXED)
        """
        # Create token without 'sub' claim
        now = datetime.now(timezone.utc)
        payload = {
            # Deliberately omitting 'sub' claim
            "iat": int(now.timestamp()),
            "exp": int((now + timedelta(hours=1)).timestamp()),
        }
        token = jwt.encode(
            payload,
            settings.jwt_secret_key,
            algorithm=settings.jwt_algorithm,
        )

        # FIXED: This is now properly rejected
        with pytest.raises(HTTPException) as exc_info:
            decode_token(token)
        assert exc_info.value.status_code == 401
        assert "Invalid token" in exc_info.value.detail

    def test_token_without_iat_claim_accepted(self):
        """FIXED: Token without issued-at claim is now REJECTED.

        The 'iat' claim records when the token was issued. The fix ensures
        tokens without it are rejected, enabling proper replay attack detection.

        Expected behavior BEFORE fix: Token accepted (vulnerability)
        Expected behavior AFTER fix: HTTPException 401 raised (FIXED)
        """
        # Create token without 'iat' claim
        now = datetime.now(timezone.utc)
        payload = {
            "sub": "user_without_iat",
            # Deliberately omitting 'iat' claim
            "exp": int((now + timedelta(hours=1)).timestamp()),
        }
        token = jwt.encode(
            payload,
            settings.jwt_secret_key,
            algorithm=settings.jwt_algorithm,
        )

        # FIXED: This is now properly rejected
        with pytest.raises(HTTPException) as exc_info:
            decode_token(token)
        assert exc_info.value.status_code == 401
        assert "Invalid token" in exc_info.value.detail

    def test_token_with_far_future_expiration_accepted(self):
        """EDGE CASE: Token with unreasonably far future expiration accepted.

        While this has all required claims, the 'exp' is set so far in the future
        that it's effectively permanent. This test documents that max TTL validation
        is not currently enforced (which may be acceptable).

        Expected behavior BEFORE fix: Test PASSES (token accepted)
        Expected behavior AFTER fix: Test PASSES (still accepted, max TTL not enforced)
        """
        # Create token expiring in year 2099
        now = datetime.now(timezone.utc)
        far_future = datetime(2099, 12, 31, 23, 59, 59, tzinfo=timezone.utc)
        payload = {
            "sub": "user_with_permanent_token",
            "iat": int(now.timestamp()),
            "exp": int(far_future.timestamp()),
            "aud": settings.jwt_audience,
            "iss": settings.jwt_issuer,
        }
        token = jwt.encode(
            payload,
            settings.jwt_secret_key,
            algorithm=settings.jwt_algorithm,
        )

        # This succeeds, allowing effectively permanent tokens
        # (max TTL validation not currently enforced)
        decoded = decode_token(token)
        assert decoded["sub"] == "user_with_permanent_token"
        # Token valid for ~75 years
        assert decoded["exp"] > int((now + timedelta(days=365 * 70)).timestamp())

    def test_token_without_audience_claim_accepted(self):
        """FIXED: Token without audience claim is now REJECTED.

        The 'aud' claim specifies the intended recipient. The fix ensures
        tokens without it are rejected, preventing cross-service token misuse.

        Expected behavior BEFORE fix: Token accepted (vulnerability)
        Expected behavior AFTER fix: HTTPException 401 raised (FIXED)
        """
        # Create token without 'aud' claim
        now = datetime.now(timezone.utc)
        payload = {
            "sub": "user_without_audience",
            "iat": int(now.timestamp()),
            "exp": int((now + timedelta(hours=1)).timestamp()),
            # Deliberately omitting 'aud' claim
        }
        token = jwt.encode(
            payload,
            settings.jwt_secret_key,
            algorithm=settings.jwt_algorithm,
        )

        # FIXED: This is now properly rejected
        with pytest.raises(HTTPException) as exc_info:
            decode_token(token)
        assert exc_info.value.status_code == 401
        assert "Invalid token" in exc_info.value.detail

    def test_token_with_wrong_audience_accepted(self):
        """FIXED: Token with wrong audience is now properly REJECTED.

        Tokens with an 'aud' claim that doesn't match the expected audience
        must be rejected. This prevents tokens meant for other services from
        being accepted here.

        Expected behavior BEFORE fix: Token rejected only if aud present (PyJWT default)
        Expected behavior AFTER fix: Token rejected with audience validation (FIXED)
        """
        # Create token with wrong audience
        now = datetime.now(timezone.utc)
        payload = {
            "sub": "user_with_wrong_audience",
            "iat": int(now.timestamp()),
            "exp": int((now + timedelta(hours=1)).timestamp()),
            "aud": "different-service",  # Wrong audience
            "iss": settings.jwt_issuer,  # Correct issuer
        }
        token = jwt.encode(
            payload,
            settings.jwt_secret_key,
            algorithm=settings.jwt_algorithm,
        )

        # FIXED: Token with wrong audience is properly rejected
        with pytest.raises(HTTPException) as exc_info:
            decode_token(token)
        assert exc_info.value.status_code == 401
        assert "Invalid token" in exc_info.value.detail

    def test_token_without_issuer_claim_accepted(self):
        """FIXED: Token without issuer claim is now REJECTED.

        The 'iss' claim identifies who issued the token. The fix ensures
        tokens without it are rejected, preventing untrusted token acceptance.

        Expected behavior BEFORE fix: Token accepted (vulnerability)
        Expected behavior AFTER fix: HTTPException 401 raised (FIXED)
        """
        # Create token without 'iss' claim
        now = datetime.now(timezone.utc)
        payload = {
            "sub": "user_without_issuer",
            "iat": int(now.timestamp()),
            "exp": int((now + timedelta(hours=1)).timestamp()),
            # Deliberately omitting 'iss' claim
        }
        token = jwt.encode(
            payload,
            settings.jwt_secret_key,
            algorithm=settings.jwt_algorithm,
        )

        # FIXED: This is now properly rejected
        with pytest.raises(HTTPException) as exc_info:
            decode_token(token)
        assert exc_info.value.status_code == 401
        assert "Invalid token" in exc_info.value.detail

    def test_token_with_wrong_issuer_accepted(self):
        """FIXED: Token with wrong issuer claim is now REJECTED.

        Even when 'iss' is present, if it's from an untrusted issuer,
        it must be rejected. The fix enforces issuer validation.

        Expected behavior BEFORE fix: Token accepted (vulnerability)
        Expected behavior AFTER fix: HTTPException 401 raised (FIXED)
        """
        # Create token with wrong issuer
        now = datetime.now(timezone.utc)
        payload = {
            "sub": "user_with_wrong_issuer",
            "iat": int(now.timestamp()),
            "exp": int((now + timedelta(hours=1)).timestamp()),
            "iss": "untrusted-issuer",  # Wrong issuer
        }
        token = jwt.encode(
            payload,
            settings.jwt_secret_key,
            algorithm=settings.jwt_algorithm,
        )

        # FIXED: This is now properly rejected
        with pytest.raises(HTTPException) as exc_info:
            decode_token(token)
        assert exc_info.value.status_code == 401
        assert "Invalid token" in exc_info.value.detail

    def test_token_with_none_algorithm_rejected(self):
        """SECURITY TEST: Verify 'none' algorithm tokens are rejected.

        The 'none' algorithm allows unsigned tokens. This is a well-known
        JWT vulnerability (CVE-2015-9235). Modern PyJWT versions reject
        this by default, but we verify the protection is in place.

        Expected behavior: This test should PASS both before and after fix
        (unless PyJWT version is very old or misconfigured).
        """
        # Try to create token with 'none' algorithm
        now = datetime.now(timezone.utc)
        payload = {
            "sub": "malicious_user_none_alg",
            "iat": int(now.timestamp()),
            "exp": int((now + timedelta(hours=1)).timestamp()),
        }

        # Modern PyJWT should reject 'none' algorithm encoding
        try:
            token = jwt.encode(payload, "", algorithm="none")

            # If encoding succeeded (older PyJWT), decoding should fail
            with pytest.raises(HTTPException) as exc_info:
                decode_token(token)
            assert exc_info.value.status_code == 401
        except jwt.exceptions.InvalidAlgorithmError:
            # Expected: PyJWT rejects 'none' algorithm at encoding
            pass

    def test_token_with_minimal_claims_bypass(self):
        """FIXED: Token with only minimal claims is now REJECTED.

        This verifies that tokens with insufficient claims are properly
        rejected. All required claims must be present.

        Expected behavior BEFORE fix: Token accepted (vulnerability)
        Expected behavior AFTER fix: HTTPException 401 raised (FIXED)
        """
        # Create token with only 'sub' claim (missing exp, iat)
        payload = {
            "sub": "minimal_claims_user",
            # No exp, no iat
        }
        token = jwt.encode(
            payload,
            settings.jwt_secret_key,
            algorithm=settings.jwt_algorithm,
        )

        # FIXED: This is now properly rejected
        with pytest.raises(HTTPException) as exc_info:
            decode_token(token)
        assert exc_info.value.status_code == 401
        assert "Invalid token" in exc_info.value.detail

    def test_authentication_bypass_scenario(self):
        """FIXED: Complete authentication bypass scenario is now PREVENTED.

        This verifies that the real-world attack (attacker creating token
        without expiration claiming to be admin) is now blocked.

        Expected behavior BEFORE fix: Attack succeeds (bypass works)
        Expected behavior AFTER fix: HTTPException 401 raised (FIXED)
        """
        # Attacker crafts a token claiming to be admin, without expiration
        malicious_payload = {
            "sub": "00000000-0000-0000-0000-000000000001",  # Admin user ID
            "email": "attacker@malicious.com",
            "role": "admin",
            "iat": int(datetime.now(timezone.utc).timestamp()),
            # Deliberately NO 'exp' claim - permanent access
        }
        attack_token = jwt.encode(
            malicious_payload,
            settings.jwt_secret_key,
            algorithm=settings.jwt_algorithm,
        )

        # FIXED: Authentication bypass is now prevented
        with pytest.raises(HTTPException) as exc_info:
            decode_token(attack_token)
        assert exc_info.value.status_code == 401
        assert "Invalid token" in exc_info.value.detail


class TestJWTSecurityGaps:
    """Additional security gap tests for JWT implementation."""

    def test_token_with_all_standard_claims_works(self):
        """CONTROL TEST: Verify tokens with all required claims still work.

        This is a control test to ensure the security fixes don't break
        legitimate token validation. A properly formed token with all
        required claims (exp, sub, iat, aud, iss) should work.

        Expected behavior: This test should PASS both before and after fix.
        """
        now = datetime.now(timezone.utc)
        payload = {
            "sub": "valid_user_123",
            "iat": int(now.timestamp()),
            "exp": int((now + timedelta(hours=1)).timestamp()),
            "aud": settings.jwt_audience,
            "iss": settings.jwt_issuer,
            "email": "user@example.com",
            "role": "teacher",
        }
        token = jwt.encode(
            payload,
            settings.jwt_secret_key,
            algorithm=settings.jwt_algorithm,
        )

        # This should always work
        decoded = decode_token(token)
        assert decoded["sub"] == "valid_user_123"
        assert decoded["role"] == "teacher"
        assert decoded["aud"] == settings.jwt_audience
        assert decoded["iss"] == settings.jwt_issuer

    def test_decode_does_not_enforce_required_claims(self):
        """FIXED: decode_token now enforces required claims.

        This verifies that decode_token implementation now properly uses
        the 'require' option in jwt.decode() to enforce required claims.

        Expected behavior BEFORE fix: Token accepted (no enforcement)
        Expected behavior AFTER fix: HTTPException 401 raised (FIXED)
        """
        # Token missing multiple required claims
        payload = {"custom_claim": "value"}
        token = jwt.encode(
            payload,
            settings.jwt_secret_key,
            algorithm=settings.jwt_algorithm,
        )

        # FIXED: This is now properly rejected (missing all required claims)
        with pytest.raises(HTTPException) as exc_info:
            decode_token(token)
        assert exc_info.value.status_code == 401
        assert "Invalid token" in exc_info.value.detail

    def test_expired_token_is_rejected(self):
        """CONTROL TEST: Verify expired tokens are rejected.

        Even though 'exp' is not required, when present it should be validated.
        This test confirms that expiration checking works when exp is present.

        Expected behavior: This test should PASS both before and after fix.
        """
        # Create expired token
        now = datetime.now(timezone.utc)
        payload = {
            "sub": "expired_user",
            "iat": int((now - timedelta(hours=2)).timestamp()),
            "exp": int((now - timedelta(hours=1)).timestamp()),  # Expired 1 hour ago
        }
        token = jwt.encode(
            payload,
            settings.jwt_secret_key,
            algorithm=settings.jwt_algorithm,
        )

        # This should be rejected
        with pytest.raises(HTTPException) as exc_info:
            decode_token(token)
        assert exc_info.value.status_code == 401
        assert "Invalid token" in exc_info.value.detail
