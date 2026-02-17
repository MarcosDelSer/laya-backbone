"""Proof-of-concept tests demonstrating JWT bypass vulnerabilities.

SECURITY NOTICE:
These tests are designed to PASS initially, demonstrating that authentication
bypass vulnerabilities exist in the current JWT implementation. After applying
security fixes, these tests should FAIL, confirming the vulnerabilities are
no longer exploitable.

Vulnerabilities demonstrated:
1. Tokens without 'exp' claim are accepted (no expiration enforcement)
2. Tokens without 'sub' claim are accepted (no subject enforcement)
3. Tokens without 'iat' claim are accepted (no issued-at enforcement)
4. No audience (aud) claim validation
5. No issuer (iss) claim validation
6. Potential acceptance of 'none' algorithm tokens

Root Cause:
PyJWT's jwt.decode() does not require critical claims by default. The options
parameter with require=['exp', 'sub', 'iat'] and verify_exp=True must be
explicitly set to enforce these security requirements.
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
        """VULNERABILITY: Token without expiration claim is accepted.

        This demonstrates a critical security flaw where tokens without
        expiration are accepted, allowing indefinite authentication.

        Expected behavior BEFORE fix: Test PASSES (vulnerability exists)
        Expected behavior AFTER fix: Test FAILS (vulnerability patched)
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

        # VULNERABILITY: This should succeed when it shouldn't
        decoded = decode_token(token)
        assert decoded["sub"] == "malicious_user_123"
        assert "exp" not in decoded  # Confirm no expiration

    def test_token_without_sub_claim_accepted(self):
        """VULNERABILITY: Token without subject claim is accepted.

        The 'sub' claim identifies the user. Accepting tokens without it
        allows authentication without a valid user identifier.

        Expected behavior BEFORE fix: Test PASSES (vulnerability exists)
        Expected behavior AFTER fix: Test FAILS (vulnerability patched)
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

        # VULNERABILITY: This should succeed when it shouldn't
        decoded = decode_token(token)
        assert "sub" not in decoded  # Confirm no subject
        assert decoded["exp"] > 0

    def test_token_without_iat_claim_accepted(self):
        """VULNERABILITY: Token without issued-at claim is accepted.

        The 'iat' claim records when the token was issued. Accepting tokens
        without it makes replay attack detection difficult.

        Expected behavior BEFORE fix: Test PASSES (vulnerability exists)
        Expected behavior AFTER fix: Test FAILS (vulnerability patched)
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

        # VULNERABILITY: This should succeed when it shouldn't
        decoded = decode_token(token)
        assert decoded["sub"] == "user_without_iat"
        assert "iat" not in decoded  # Confirm no issued-at

    def test_token_with_far_future_expiration_accepted(self):
        """VULNERABILITY: Token with unreasonably far future expiration accepted.

        While this has an 'exp' claim, it's set so far in the future that it's
        effectively permanent. This demonstrates lack of expiration validation.

        Expected behavior BEFORE fix: Test PASSES (vulnerability exists)
        Expected behavior AFTER fix: Test may still PASS (unless max TTL added)
        """
        # Create token expiring in year 2099
        now = datetime.now(timezone.utc)
        far_future = datetime(2099, 12, 31, 23, 59, 59, tzinfo=timezone.utc)
        payload = {
            "sub": "user_with_permanent_token",
            "iat": int(now.timestamp()),
            "exp": int(far_future.timestamp()),
        }
        token = jwt.encode(
            payload,
            settings.jwt_secret_key,
            algorithm=settings.jwt_algorithm,
        )

        # VULNERABILITY: This succeeds, allowing effectively permanent tokens
        decoded = decode_token(token)
        assert decoded["sub"] == "user_with_permanent_token"
        # Token valid for ~75 years
        assert decoded["exp"] > int((now + timedelta(days=365 * 70)).timestamp())

    def test_token_without_audience_claim_accepted(self):
        """VULNERABILITY: Token without audience claim is accepted.

        The 'aud' claim specifies the intended recipient. Without it,
        tokens meant for other services could be used here.

        Expected behavior BEFORE fix: Test PASSES (vulnerability exists)
        Expected behavior AFTER fix: Test FAILS (aud validation required)
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

        # VULNERABILITY: This should succeed when it shouldn't
        decoded = decode_token(token)
        assert decoded["sub"] == "user_without_audience"
        assert "aud" not in decoded  # Confirm no audience

    def test_token_with_wrong_audience_accepted(self):
        """SECURITY PROTECTION: Token with aud claim is rejected when decoder has no audience.

        PyJWT has a built-in protection: if a token contains an 'aud' claim
        but the decoder doesn't specify an expected audience, it rejects the
        token with InvalidAudienceError. This is actually good security.

        However, the real vulnerability is that tokens WITHOUT 'aud' claim
        are accepted (see test_token_without_audience_claim_accepted).

        Expected behavior BEFORE fix: Test PASSES (PyJWT protection works)
        Expected behavior AFTER fix: Test PASSES (protection remains)
        """
        # Create token with wrong audience
        now = datetime.now(timezone.utc)
        payload = {
            "sub": "user_with_wrong_audience",
            "iat": int(now.timestamp()),
            "exp": int((now + timedelta(hours=1)).timestamp()),
            "aud": "different-service",  # Wrong audience
        }
        token = jwt.encode(
            payload,
            settings.jwt_secret_key,
            algorithm=settings.jwt_algorithm,
        )

        # PyJWT's built-in protection: This IS rejected
        with pytest.raises(HTTPException) as exc_info:
            decode_token(token)
        assert exc_info.value.status_code == 401
        assert "Invalid audience" in exc_info.value.detail

    def test_token_without_issuer_claim_accepted(self):
        """VULNERABILITY: Token without issuer claim is accepted.

        The 'iss' claim identifies who issued the token. Without it,
        tokens from untrusted sources could be accepted.

        Expected behavior BEFORE fix: Test PASSES (vulnerability exists)
        Expected behavior AFTER fix: Test FAILS (iss validation required)
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

        # VULNERABILITY: This should succeed when it shouldn't
        decoded = decode_token(token)
        assert decoded["sub"] == "user_without_issuer"
        assert "iss" not in decoded  # Confirm no issuer

    def test_token_with_wrong_issuer_accepted(self):
        """VULNERABILITY: Token with wrong issuer claim is accepted.

        Even when 'iss' is present, if it's from an untrusted issuer,
        it should be rejected. This demonstrates lack of iss validation.

        Expected behavior BEFORE fix: Test PASSES (vulnerability exists)
        Expected behavior AFTER fix: Test FAILS (iss validation enforced)
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

        # VULNERABILITY: This should be rejected but isn't
        decoded = decode_token(token)
        assert decoded["sub"] == "user_with_wrong_issuer"
        assert decoded["iss"] == "untrusted-issuer"

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
        """VULNERABILITY: Token with only minimal claims bypasses validation.

        This test combines multiple vulnerabilities: a token with just one
        claim can be successfully decoded, demonstrating insufficient
        claim validation.

        Expected behavior BEFORE fix: Test PASSES (vulnerability exists)
        Expected behavior AFTER fix: Test FAILS (all required claims enforced)
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

        # VULNERABILITY: This should be rejected but isn't
        decoded = decode_token(token)
        assert decoded["sub"] == "minimal_claims_user"
        assert "exp" not in decoded
        assert "iat" not in decoded

    def test_authentication_bypass_scenario(self):
        """VULNERABILITY: Complete authentication bypass scenario.

        This demonstrates a real-world attack: an attacker creates a token
        without expiration, claiming to be an admin user, and it's accepted.

        Expected behavior BEFORE fix: Test PASSES (bypass works)
        Expected behavior AFTER fix: Test FAILS (bypass prevented)
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

        # VULNERABILITY: Attacker gains admin access without expiration
        decoded = decode_token(attack_token)
        assert decoded["role"] == "admin"
        assert "exp" not in decoded  # Permanent admin access
        assert decoded["email"] == "attacker@malicious.com"


class TestJWTSecurityGaps:
    """Additional security gap tests for JWT implementation."""

    def test_token_with_all_standard_claims_works(self):
        """CONTROL TEST: Verify tokens with all standard claims work.

        This is a control test to ensure our proof-of-concept tests aren't
        just failing due to other issues. A properly formed token should
        always work.

        Expected behavior: This test should PASS both before and after fix.
        """
        now = datetime.now(timezone.utc)
        payload = {
            "sub": "valid_user_123",
            "iat": int(now.timestamp()),
            "exp": int((now + timedelta(hours=1)).timestamp()),
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

    def test_decode_does_not_enforce_required_claims(self):
        """VULNERABILITY: decode_token doesn't enforce required claims.

        This test verifies that the current decode_token implementation
        does not use the 'require' option in jwt.decode().

        Expected behavior BEFORE fix: Test PASSES (no enforcement)
        Expected behavior AFTER fix: Test FAILS (enforcement added)
        """
        # Token missing multiple required claims
        payload = {"custom_claim": "value"}
        token = jwt.encode(
            payload,
            settings.jwt_secret_key,
            algorithm=settings.jwt_algorithm,
        )

        # VULNERABILITY: This succeeds without any standard claims
        decoded = decode_token(token)
        assert decoded["custom_claim"] == "value"
        assert "sub" not in decoded
        assert "exp" not in decoded
        assert "iat" not in decoded

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
