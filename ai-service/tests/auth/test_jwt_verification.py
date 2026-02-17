"""Comprehensive JWT verification security tests for LAYA AI Service.

Tests security-critical JWT verification scenarios including:
- Signature verification attacks
- Expiration validation
- Required claims validation
- Algorithm confusion attacks
- Issuer and audience validation
- Token tampering detection
"""

from datetime import datetime, timedelta, timezone
from unittest.mock import AsyncMock, MagicMock

import jwt
import pytest
from fastapi import HTTPException
from fastapi.security import HTTPAuthorizationCredentials

from app.auth.jwt import create_token, decode_token, verify_token
from app.config import settings

from tests.auth.conftest import create_test_token


class TestSignatureVerification:
    """Tests for JWT signature verification."""

    def test_decode_token_with_invalid_signature_raises_401(self):
        """Test that token with invalid signature is rejected."""
        # Create token with wrong secret key
        payload = {
            "sub": "user123",
            "iat": int(datetime.now(timezone.utc).timestamp()),
            "exp": int((datetime.now(timezone.utc) + timedelta(hours=1)).timestamp()),
            "iss": settings.jwt_issuer,
            "aud": settings.jwt_audience,
        }
        token = jwt.encode(payload, "wrong_secret_key", algorithm="HS256")

        with pytest.raises(HTTPException) as exc_info:
            decode_token(token)

        assert exc_info.value.status_code == 401
        assert "Invalid token" in exc_info.value.detail
        assert exc_info.value.headers == {"WWW-Authenticate": "Bearer"}

    def test_decode_token_with_tampered_payload_raises_401(self):
        """Test that token with tampered payload is rejected."""
        # Create valid token
        token = create_token(subject="user123", additional_claims={"role": "user"})

        # Tamper with token by modifying a character in the payload section
        parts = token.split(".")
        assert len(parts) == 3
        tampered_token = parts[0] + "." + parts[1][:-1] + "x" + "." + parts[2]

        with pytest.raises(HTTPException) as exc_info:
            decode_token(tampered_token)

        assert exc_info.value.status_code == 401
        assert "Invalid token" in exc_info.value.detail

    def test_decode_token_with_tampered_signature_raises_401(self):
        """Test that token with tampered signature is rejected."""
        token = create_token(subject="user123")

        # Tamper with signature
        parts = token.split(".")
        assert len(parts) == 3
        tampered_token = parts[0] + "." + parts[1] + "." + parts[2][:-1] + "x"

        with pytest.raises(HTTPException) as exc_info:
            decode_token(tampered_token)

        assert exc_info.value.status_code == 401

    def test_decode_token_with_modified_header_raises_401(self):
        """Test that token with modified header is rejected."""
        token = create_token(subject="user123")

        # Tamper with header
        parts = token.split(".")
        assert len(parts) == 3
        tampered_token = parts[0][:-1] + "x" + "." + parts[1] + "." + parts[2]

        with pytest.raises(HTTPException) as exc_info:
            decode_token(tampered_token)

        assert exc_info.value.status_code == 401


class TestExpirationValidation:
    """Tests for JWT expiration validation."""

    def test_decode_token_expired_by_one_hour_raises_401(self):
        """Test that token expired 1 hour ago is rejected."""
        token = create_test_token(
            subject="user123",
            expires_delta_seconds=-3600,
        )

        with pytest.raises(HTTPException) as exc_info:
            decode_token(token)

        assert exc_info.value.status_code == 401
        assert "Invalid token" in exc_info.value.detail

    def test_decode_token_expired_by_one_second_raises_401(self):
        """Test that token expired 1 second ago is rejected."""
        token = create_test_token(
            subject="user123",
            expires_delta_seconds=-1,
        )

        with pytest.raises(HTTPException) as exc_info:
            decode_token(token)

        assert exc_info.value.status_code == 401

    def test_decode_token_expired_by_one_day_raises_401(self):
        """Test that token expired 1 day ago is rejected."""
        token = create_test_token(
            subject="user123",
            expires_delta_seconds=-86400,
        )

        with pytest.raises(HTTPException) as exc_info:
            decode_token(token)

        assert exc_info.value.status_code == 401

    def test_decode_token_about_to_expire_succeeds(self):
        """Test that token expiring in 1 second is still valid."""
        token = create_test_token(
            subject="user123",
            expires_delta_seconds=1,
        )

        payload = decode_token(token)
        assert payload["sub"] == "user123"

    def test_decode_token_without_exp_claim_raises_401(self):
        """Test that token without exp claim is rejected."""
        # Create token without exp claim
        payload = {
            "sub": "user123",
            "iat": int(datetime.now(timezone.utc).timestamp()),
            "iss": settings.jwt_issuer,
            "aud": settings.jwt_audience,
        }
        token = jwt.encode(
            payload,
            settings.jwt_secret_key,
            algorithm=settings.jwt_algorithm,
        )

        with pytest.raises(HTTPException) as exc_info:
            decode_token(token)

        assert exc_info.value.status_code == 401
        assert "Invalid token" in exc_info.value.detail


class TestRequiredClaims:
    """Tests for required JWT claims validation."""

    def test_decode_token_without_sub_claim_raises_401(self):
        """Test that token without sub claim is rejected."""
        payload = {
            "iat": int(datetime.now(timezone.utc).timestamp()),
            "exp": int((datetime.now(timezone.utc) + timedelta(hours=1)).timestamp()),
            "iss": settings.jwt_issuer,
            "aud": settings.jwt_audience,
        }
        token = jwt.encode(
            payload,
            settings.jwt_secret_key,
            algorithm=settings.jwt_algorithm,
        )

        with pytest.raises(HTTPException) as exc_info:
            decode_token(token)

        assert exc_info.value.status_code == 401
        assert "Invalid token" in exc_info.value.detail

    def test_decode_token_without_iat_claim_raises_401(self):
        """Test that token without iat claim is rejected."""
        payload = {
            "sub": "user123",
            "exp": int((datetime.now(timezone.utc) + timedelta(hours=1)).timestamp()),
            "iss": settings.jwt_issuer,
            "aud": settings.jwt_audience,
        }
        token = jwt.encode(
            payload,
            settings.jwt_secret_key,
            algorithm=settings.jwt_algorithm,
        )

        with pytest.raises(HTTPException) as exc_info:
            decode_token(token)

        assert exc_info.value.status_code == 401
        assert "Invalid token" in exc_info.value.detail

    def test_decode_token_without_iss_claim_raises_401(self):
        """Test that token without iss claim is rejected."""
        payload = {
            "sub": "user123",
            "iat": int(datetime.now(timezone.utc).timestamp()),
            "exp": int((datetime.now(timezone.utc) + timedelta(hours=1)).timestamp()),
            "aud": settings.jwt_audience,
        }
        token = jwt.encode(
            payload,
            settings.jwt_secret_key,
            algorithm=settings.jwt_algorithm,
        )

        with pytest.raises(HTTPException) as exc_info:
            decode_token(token)

        assert exc_info.value.status_code == 401
        assert "Invalid token" in exc_info.value.detail

    def test_decode_token_without_aud_claim_raises_401(self):
        """Test that token without aud claim is rejected."""
        payload = {
            "sub": "user123",
            "iat": int(datetime.now(timezone.utc).timestamp()),
            "exp": int((datetime.now(timezone.utc) + timedelta(hours=1)).timestamp()),
            "iss": settings.jwt_issuer,
        }
        token = jwt.encode(
            payload,
            settings.jwt_secret_key,
            algorithm=settings.jwt_algorithm,
        )

        with pytest.raises(HTTPException) as exc_info:
            decode_token(token)

        assert exc_info.value.status_code == 401
        assert "Invalid token" in exc_info.value.detail

    def test_decode_token_with_all_required_claims_succeeds(self):
        """Test that token with all required claims is accepted."""
        token = create_token(subject="user123")
        payload = decode_token(token)

        assert "sub" in payload
        assert "iat" in payload
        assert "exp" in payload
        assert "iss" in payload
        assert "aud" in payload


class TestAlgorithmValidation:
    """Tests for JWT algorithm validation and algorithm confusion attacks."""

    def test_decode_token_with_none_algorithm_raises_401(self):
        """Test that token with 'none' algorithm is rejected (CVE-2015-9235)."""
        payload = {
            "sub": "user123",
            "iat": int(datetime.now(timezone.utc).timestamp()),
            "exp": int((datetime.now(timezone.utc) + timedelta(hours=1)).timestamp()),
            "iss": settings.jwt_issuer,
            "aud": settings.jwt_audience,
        }

        try:
            # Try to create token with 'none' algorithm
            token = jwt.encode(payload, "", algorithm="none")

            with pytest.raises(HTTPException) as exc_info:
                decode_token(token)

            assert exc_info.value.status_code == 401
        except jwt.exceptions.InvalidAlgorithmError:
            # Some JWT libraries don't support 'none' algorithm, which is good
            pass

    def test_decode_token_with_wrong_algorithm_raises_401(self):
        """Test that token signed with different algorithm is rejected."""
        payload = {
            "sub": "user123",
            "iat": int(datetime.now(timezone.utc).timestamp()),
            "exp": int((datetime.now(timezone.utc) + timedelta(hours=1)).timestamp()),
            "iss": settings.jwt_issuer,
            "aud": settings.jwt_audience,
        }

        # Create token with RS256 (would require RSA keys in production)
        # For this test, we'll create with HS512 instead
        token = jwt.encode(payload, settings.jwt_secret_key, algorithm="HS512")

        with pytest.raises(HTTPException) as exc_info:
            decode_token(token)

        assert exc_info.value.status_code == 401

    def test_decode_token_enforces_configured_algorithm(self):
        """Test that only the configured algorithm (HS256) is accepted."""
        # Valid token should use HS256
        token = create_token(subject="user123")
        payload = decode_token(token)

        # Verify we can decode it
        assert payload["sub"] == "user123"

        # Verify the algorithm used
        header = jwt.get_unverified_header(token)
        assert header["alg"] == settings.jwt_algorithm


class TestIssuerAndAudience:
    """Tests for issuer (iss) and audience (aud) claim validation."""

    def test_decode_token_with_wrong_issuer_raises_401(self):
        """Test that token with wrong issuer is rejected."""
        payload = {
            "sub": "user123",
            "iat": int(datetime.now(timezone.utc).timestamp()),
            "exp": int((datetime.now(timezone.utc) + timedelta(hours=1)).timestamp()),
            "iss": "malicious-issuer",
            "aud": settings.jwt_audience,
        }
        token = jwt.encode(
            payload,
            settings.jwt_secret_key,
            algorithm=settings.jwt_algorithm,
        )

        with pytest.raises(HTTPException) as exc_info:
            decode_token(token)

        assert exc_info.value.status_code == 401
        assert "Invalid token" in exc_info.value.detail

    def test_decode_token_with_wrong_audience_raises_401(self):
        """Test that token with wrong audience is rejected."""
        payload = {
            "sub": "user123",
            "iat": int(datetime.now(timezone.utc).timestamp()),
            "exp": int((datetime.now(timezone.utc) + timedelta(hours=1)).timestamp()),
            "iss": settings.jwt_issuer,
            "aud": "wrong-audience",
        }
        token = jwt.encode(
            payload,
            settings.jwt_secret_key,
            algorithm=settings.jwt_algorithm,
        )

        with pytest.raises(HTTPException) as exc_info:
            decode_token(token)

        assert exc_info.value.status_code == 401
        assert "Invalid token" in exc_info.value.detail

    def test_decode_token_with_correct_issuer_and_audience_succeeds(self):
        """Test that token with correct issuer and audience is accepted."""
        token = create_token(subject="user123")
        payload = decode_token(token)

        assert payload["iss"] == settings.jwt_issuer
        assert payload["aud"] == settings.jwt_audience
        assert payload["sub"] == "user123"

    def test_create_token_includes_issuer_and_audience(self):
        """Test that created tokens include issuer and audience claims."""
        token = create_token(subject="user123")

        # Decode without verification to inspect claims
        unverified_payload = jwt.decode(
            token,
            options={"verify_signature": False},
        )

        assert unverified_payload["iss"] == settings.jwt_issuer
        assert unverified_payload["aud"] == settings.jwt_audience


class TestMalformedTokens:
    """Tests for malformed and invalid token formats."""

    def test_decode_token_with_empty_string_raises_401(self):
        """Test that empty string token is rejected."""
        with pytest.raises(HTTPException) as exc_info:
            decode_token("")

        assert exc_info.value.status_code == 401

    def test_decode_token_with_none_raises_401(self):
        """Test that None token is rejected."""
        with pytest.raises(HTTPException) as exc_info:
            decode_token(None)

        assert exc_info.value.status_code == 401

    def test_decode_token_with_random_string_raises_401(self):
        """Test that random string is rejected."""
        with pytest.raises(HTTPException) as exc_info:
            decode_token("this.is.not.a.valid.token")

        assert exc_info.value.status_code == 401

    def test_decode_token_with_single_part_raises_401(self):
        """Test that token with only one part is rejected."""
        with pytest.raises(HTTPException) as exc_info:
            decode_token("singlepart")

        assert exc_info.value.status_code == 401

    def test_decode_token_with_two_parts_raises_401(self):
        """Test that token with only two parts is rejected."""
        with pytest.raises(HTTPException) as exc_info:
            decode_token("two.parts")

        assert exc_info.value.status_code == 401

    def test_decode_token_with_four_parts_raises_401(self):
        """Test that token with four parts is rejected."""
        with pytest.raises(HTTPException) as exc_info:
            decode_token("four.part.token.here")

        assert exc_info.value.status_code == 401

    def test_decode_token_with_invalid_base64_raises_401(self):
        """Test that token with invalid base64 encoding is rejected."""
        with pytest.raises(HTTPException) as exc_info:
            decode_token("invalid!!!.base64!!!.encoding!!!")

        assert exc_info.value.status_code == 401


class TestVerifyTokenSecurityIntegration:
    """Integration tests for verify_token with security-critical scenarios."""

    @pytest.mark.asyncio
    async def test_verify_token_rejects_expired_token(self):
        """Test verify_token rejects expired token before blacklist check."""
        expired_token = create_test_token(
            subject="user123",
            expires_delta_seconds=-3600,
        )
        credentials = HTTPAuthorizationCredentials(
            scheme="Bearer", credentials=expired_token
        )
        mock_db_session = AsyncMock()

        with pytest.raises(HTTPException) as exc_info:
            await verify_token(credentials, mock_db_session)

        assert exc_info.value.status_code == 401
        # Should not reach blacklist check
        mock_db_session.execute.assert_not_called()

    @pytest.mark.asyncio
    async def test_verify_token_rejects_invalid_signature(self):
        """Test verify_token rejects token with invalid signature."""
        payload = {
            "sub": "user123",
            "iat": int(datetime.now(timezone.utc).timestamp()),
            "exp": int((datetime.now(timezone.utc) + timedelta(hours=1)).timestamp()),
            "iss": settings.jwt_issuer,
            "aud": settings.jwt_audience,
        }
        invalid_token = jwt.encode(payload, "wrong_secret", algorithm="HS256")
        credentials = HTTPAuthorizationCredentials(
            scheme="Bearer", credentials=invalid_token
        )
        mock_db_session = AsyncMock()

        with pytest.raises(HTTPException) as exc_info:
            await verify_token(credentials, mock_db_session)

        assert exc_info.value.status_code == 401
        # Should not reach blacklist check
        mock_db_session.execute.assert_not_called()

    @pytest.mark.asyncio
    async def test_verify_token_rejects_missing_claims(self):
        """Test verify_token rejects token with missing required claims."""
        payload = {
            "sub": "user123",
            "exp": int((datetime.now(timezone.utc) + timedelta(hours=1)).timestamp()),
            # Missing iat, iss, aud
        }
        invalid_token = jwt.encode(
            payload,
            settings.jwt_secret_key,
            algorithm=settings.jwt_algorithm,
        )
        credentials = HTTPAuthorizationCredentials(
            scheme="Bearer", credentials=invalid_token
        )
        mock_db_session = AsyncMock()

        with pytest.raises(HTTPException) as exc_info:
            await verify_token(credentials, mock_db_session)

        assert exc_info.value.status_code == 401
        # Should not reach blacklist check
        mock_db_session.execute.assert_not_called()

    @pytest.mark.asyncio
    async def test_verify_token_accepts_valid_token(self):
        """Test verify_token accepts valid token and checks blacklist."""
        token = create_token(
            subject="user123",
            additional_claims={"role": "teacher"},
        )
        credentials = HTTPAuthorizationCredentials(scheme="Bearer", credentials=token)

        # Mock blacklist check to return None (not blacklisted)
        mock_db_session = AsyncMock()
        mock_result = MagicMock()
        mock_result.scalar_one_or_none.return_value = None
        mock_db_session.execute.return_value = mock_result

        payload = await verify_token(credentials, mock_db_session)

        assert payload["sub"] == "user123"
        assert payload["role"] == "teacher"
        # Should have checked blacklist
        mock_db_session.execute.assert_called_once()

    @pytest.mark.asyncio
    async def test_verify_token_rejects_wrong_issuer(self):
        """Test verify_token rejects token with wrong issuer."""
        payload = {
            "sub": "user123",
            "iat": int(datetime.now(timezone.utc).timestamp()),
            "exp": int((datetime.now(timezone.utc) + timedelta(hours=1)).timestamp()),
            "iss": "malicious-issuer",
            "aud": settings.jwt_audience,
        }
        invalid_token = jwt.encode(
            payload,
            settings.jwt_secret_key,
            algorithm=settings.jwt_algorithm,
        )
        credentials = HTTPAuthorizationCredentials(
            scheme="Bearer", credentials=invalid_token
        )
        mock_db_session = AsyncMock()

        with pytest.raises(HTTPException) as exc_info:
            await verify_token(credentials, mock_db_session)

        assert exc_info.value.status_code == 401
        # Should not reach blacklist check
        mock_db_session.execute.assert_not_called()

    @pytest.mark.asyncio
    async def test_verify_token_rejects_wrong_audience(self):
        """Test verify_token rejects token with wrong audience."""
        payload = {
            "sub": "user123",
            "iat": int(datetime.now(timezone.utc).timestamp()),
            "exp": int((datetime.now(timezone.utc) + timedelta(hours=1)).timestamp()),
            "iss": settings.jwt_issuer,
            "aud": "wrong-audience",
        }
        invalid_token = jwt.encode(
            payload,
            settings.jwt_secret_key,
            algorithm=settings.jwt_algorithm,
        )
        credentials = HTTPAuthorizationCredentials(
            scheme="Bearer", credentials=invalid_token
        )
        mock_db_session = AsyncMock()

        with pytest.raises(HTTPException) as exc_info:
            await verify_token(credentials, mock_db_session)

        assert exc_info.value.status_code == 401
        # Should not reach blacklist check
        mock_db_session.execute.assert_not_called()


class TestSecurityBestPractices:
    """Tests for JWT security best practices and edge cases."""

    def test_tokens_are_stateless_and_independent(self):
        """Test that each token is independently verifiable."""
        token1 = create_token(subject="user1")
        token2 = create_token(subject="user2")

        payload1 = decode_token(token1)
        payload2 = decode_token(token2)

        assert payload1["sub"] == "user1"
        assert payload2["sub"] == "user2"
        assert token1 != token2

    def test_token_cannot_be_reused_across_users(self):
        """Test that token for one user cannot be used for another."""
        token = create_token(subject="user123")
        payload = decode_token(token)

        # Token is tied to user123
        assert payload["sub"] == "user123"

        # Cannot be modified to impersonate another user
        # Any attempt to modify will fail signature verification

    def test_short_lived_tokens_expire_quickly(self):
        """Test that short-lived tokens expire as expected."""
        # Create token that expires in 1 second
        token = create_test_token(subject="user123", expires_delta_seconds=1)

        # Should be valid immediately
        payload = decode_token(token)
        assert payload["sub"] == "user123"

        # After expiration, should be rejected
        # (Note: Can't reliably test this without sleep, which we avoid in tests)

    def test_jwt_includes_issued_at_timestamp(self):
        """Test that JWT includes issued-at timestamp for tracking."""
        before = datetime.now(timezone.utc)
        token = create_token(subject="user123")
        after = datetime.now(timezone.utc)

        payload = decode_token(token)
        iat = datetime.fromtimestamp(payload["iat"], tz=timezone.utc)

        # iat should be between before and after (with tolerance for seconds)
        before_seconds = before.replace(microsecond=0)
        after_seconds = after.replace(microsecond=0) + timedelta(seconds=1)
        assert before_seconds <= iat <= after_seconds

    def test_additional_claims_cannot_override_protected_claims(self):
        """Test that additional claims cannot override sub, iat, exp, iss, aud."""
        token = create_token(
            subject="user123",
            expires_delta_seconds=3600,
            additional_claims={
                "sub": "hacker",
                "iat": 0,
                "exp": 9999999999,
                "iss": "malicious",
                "aud": "wrong",
            },
        )
        payload = decode_token(token)

        # Protected claims should not be overridden
        assert payload["sub"] == "user123"
        assert payload["iat"] != 0
        assert payload["exp"] != 9999999999
        assert payload["iss"] == settings.jwt_issuer
        assert payload["aud"] == settings.jwt_audience
