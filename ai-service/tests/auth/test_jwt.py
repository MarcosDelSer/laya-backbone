"""Unit tests for JWT utilities in LAYA AI Service.

Tests create_token(), decode_token(), and verify_token() functions
from app/auth/jwt.py.
"""

from datetime import datetime, timedelta, timezone
from unittest.mock import AsyncMock, MagicMock, patch
from uuid import uuid4

import jwt
import pytest
import pytest_asyncio
from fastapi import HTTPException
from fastapi.security import HTTPAuthorizationCredentials

from app.auth.jwt import create_token, decode_token, verify_token
from app.config import settings

from tests.auth.conftest import (
    create_test_token,
    create_token_blacklist_in_db,
)


class TestCreateToken:
    """Tests for create_token() function."""

    def test_create_token_returns_string(self):
        """Test create_token returns a string."""
        token = create_token(subject="user123")
        assert isinstance(token, str)

    def test_create_token_is_valid_jwt(self):
        """Test create_token returns a valid JWT that can be decoded."""
        token = create_token(subject="user123")
        # Should not raise an exception
        payload = jwt.decode(
            token,
            settings.jwt_secret_key,
            algorithms=[settings.jwt_algorithm],
            audience=settings.jwt_audience,
            issuer=settings.jwt_issuer,
        )
        assert "sub" in payload
        assert payload["sub"] == "user123"

    def test_create_token_contains_subject(self):
        """Test create_token includes subject in payload."""
        user_id = str(uuid4())
        token = create_token(subject=user_id)
        payload = jwt.decode(
            token,
            settings.jwt_secret_key,
            algorithms=[settings.jwt_algorithm],
            audience=settings.jwt_audience,
            issuer=settings.jwt_issuer,
        )
        assert payload["sub"] == user_id

    def test_create_token_contains_iat(self):
        """Test create_token includes issued-at timestamp."""
        token = create_token(subject="user123")
        payload = jwt.decode(
            token,
            settings.jwt_secret_key,
            algorithms=[settings.jwt_algorithm],
            audience=settings.jwt_audience,
            issuer=settings.jwt_issuer,
        )
        assert "iat" in payload
        # iat should be within last minute
        now = datetime.now(timezone.utc)
        iat = datetime.fromtimestamp(payload["iat"], tz=timezone.utc)
        assert abs((now - iat).total_seconds()) < 60

    def test_create_token_contains_exp(self):
        """Test create_token includes expiration timestamp."""
        token = create_token(subject="user123", expires_delta_seconds=3600)
        payload = jwt.decode(
            token,
            settings.jwt_secret_key,
            algorithms=[settings.jwt_algorithm],
            audience=settings.jwt_audience,
            issuer=settings.jwt_issuer,
        )
        assert "exp" in payload
        # exp should be approximately 1 hour from now
        now = datetime.now(timezone.utc)
        exp = datetime.fromtimestamp(payload["exp"], tz=timezone.utc)
        delta = (exp - now).total_seconds()
        assert 3500 < delta < 3700  # Allow small tolerance

    def test_create_token_custom_expiration(self):
        """Test create_token with custom expiration time."""
        # 15 minutes
        token = create_token(subject="user123", expires_delta_seconds=900)
        payload = jwt.decode(
            token,
            settings.jwt_secret_key,
            algorithms=[settings.jwt_algorithm],
            audience=settings.jwt_audience,
            issuer=settings.jwt_issuer,
        )
        now = datetime.now(timezone.utc)
        exp = datetime.fromtimestamp(payload["exp"], tz=timezone.utc)
        delta = (exp - now).total_seconds()
        assert 800 < delta < 1000

    def test_create_token_with_additional_claims(self):
        """Test create_token with additional claims."""
        token = create_token(
            subject="user123",
            additional_claims={
                "role": "admin",
                "email": "admin@example.com",
                "type": "access",
            },
        )
        payload = jwt.decode(
            token,
            settings.jwt_secret_key,
            algorithms=[settings.jwt_algorithm],
            audience=settings.jwt_audience,
            issuer=settings.jwt_issuer,
        )
        assert payload["sub"] == "user123"
        assert payload["role"] == "admin"
        assert payload["email"] == "admin@example.com"
        assert payload["type"] == "access"

    def test_create_token_additional_claims_do_not_override_standard(self):
        """Test additional claims cannot override sub, iat, exp."""
        # Get current timestamp for comparison
        before = datetime.now(timezone.utc)
        token = create_token(
            subject="user123",
            expires_delta_seconds=3600,
            additional_claims={
                "sub": "hacker",
                "iat": 0,
                "exp": 9999999999,
                "iss": "evil.com",
                "aud": "hackers",
            },
        )
        after = datetime.now(timezone.utc)

        payload = jwt.decode(
            token,
            settings.jwt_secret_key,
            algorithms=[settings.jwt_algorithm],
            audience=settings.jwt_audience,
            issuer=settings.jwt_issuer,
        )
        # Standard claims should NOT be overridden by additional_claims
        assert payload["sub"] == "user123"  # Should be original subject
        assert payload["iss"] == settings.jwt_issuer  # Should be original issuer
        assert payload["aud"] == settings.jwt_audience  # Should be original audience

        # Verify iat is current time (not 0 from additional_claims)
        iat = datetime.fromtimestamp(payload["iat"], tz=timezone.utc)
        assert before.replace(microsecond=0) <= iat <= after.replace(microsecond=0) + timedelta(seconds=1)

        # Verify exp is ~1 hour from now (not 9999999999 from additional_claims)
        exp = datetime.fromtimestamp(payload["exp"], tz=timezone.utc)
        delta = (exp - datetime.now(timezone.utc)).total_seconds()
        assert 3500 < delta < 3700  # Allow small tolerance

    def test_create_token_empty_additional_claims(self):
        """Test create_token with empty additional claims dict."""
        token = create_token(subject="user123", additional_claims={})
        payload = jwt.decode(
            token,
            settings.jwt_secret_key,
            algorithms=[settings.jwt_algorithm],
            audience=settings.jwt_audience,
            issuer=settings.jwt_issuer,
        )
        assert payload["sub"] == "user123"
        assert "iat" in payload
        assert "exp" in payload

    def test_create_token_none_additional_claims(self):
        """Test create_token with None additional claims."""
        token = create_token(subject="user123", additional_claims=None)
        payload = jwt.decode(
            token,
            settings.jwt_secret_key,
            algorithms=[settings.jwt_algorithm],
            audience=settings.jwt_audience,
            issuer=settings.jwt_issuer,
        )
        assert payload["sub"] == "user123"

    def test_create_token_different_subjects_different_tokens(self):
        """Test different subjects produce different tokens."""
        token1 = create_token(subject="user1")
        token2 = create_token(subject="user2")
        assert token1 != token2


class TestDecodeToken:
    """Tests for decode_token() function."""

    def test_decode_token_valid_token(self):
        """Test decode_token successfully decodes valid token."""
        token = create_token(subject="user123")
        payload = decode_token(token)
        assert payload["sub"] == "user123"

    def test_decode_token_returns_all_claims(self):
        """Test decode_token returns all claims."""
        token = create_token(
            subject="user123",
            additional_claims={"role": "admin", "email": "test@example.com"},
        )
        payload = decode_token(token)
        assert payload["sub"] == "user123"
        assert payload["role"] == "admin"
        assert payload["email"] == "test@example.com"
        assert "iat" in payload
        assert "exp" in payload

    def test_decode_token_expired_token_raises_401(self):
        """Test decode_token raises 401 for expired token."""
        # Create a token that expired 1 hour ago
        token = create_test_token(
            subject="user123",
            expires_delta_seconds=-3600,
        )
        with pytest.raises(HTTPException) as exc_info:
            decode_token(token)
        assert exc_info.value.status_code == 401
        assert "Invalid token" in exc_info.value.detail

    def test_decode_token_invalid_token_raises_401(self):
        """Test decode_token raises 401 for malformed token."""
        with pytest.raises(HTTPException) as exc_info:
            decode_token("invalid.token.string")
        assert exc_info.value.status_code == 401
        assert "Invalid token" in exc_info.value.detail

    def test_decode_token_wrong_signature_raises_401(self):
        """Test decode_token raises 401 for token with wrong signature."""
        # Create token with different secret
        payload = {
            "sub": "user123",
            "iat": int(datetime.now(timezone.utc).timestamp()),
            "exp": int((datetime.now(timezone.utc) + timedelta(hours=1)).timestamp()),
        }
        token = jwt.encode(payload, "wrong_secret_key", algorithm="HS256")
        with pytest.raises(HTTPException) as exc_info:
            decode_token(token)
        assert exc_info.value.status_code == 401
        assert "Invalid token" in exc_info.value.detail

    def test_decode_token_empty_string_raises_401(self):
        """Test decode_token raises 401 for empty string."""
        with pytest.raises(HTTPException) as exc_info:
            decode_token("")
        assert exc_info.value.status_code == 401

    def test_decode_token_none_raises_401(self):
        """Test decode_token raises 401 for None input."""
        with pytest.raises(HTTPException) as exc_info:
            decode_token(None)
        assert exc_info.value.status_code == 401

    def test_decode_token_www_authenticate_header(self):
        """Test decode_token includes WWW-Authenticate header in error."""
        with pytest.raises(HTTPException) as exc_info:
            decode_token("invalid")
        assert exc_info.value.headers == {"WWW-Authenticate": "Bearer"}

    def test_decode_token_with_test_token(self):
        """Test decode_token works with create_test_token."""
        token = create_test_token(
            subject="test_user",
            additional_claims={"type": "access"},
        )
        payload = decode_token(token)
        assert payload["sub"] == "test_user"
        assert payload["type"] == "access"


class TestVerifyToken:
    """Tests for verify_token() async function with blacklist checking."""

    @pytest_asyncio.fixture
    async def mock_db_session(self):
        """Create a mock database session."""
        mock_session = AsyncMock()
        return mock_session

    @pytest.fixture
    def valid_credentials(self):
        """Create valid HTTP credentials with a valid token."""
        token = create_token(
            subject="user123",
            additional_claims={"role": "teacher", "email": "teacher@example.com"},
        )
        return HTTPAuthorizationCredentials(scheme="Bearer", credentials=token)

    @pytest.fixture
    def expired_credentials(self):
        """Create HTTP credentials with expired token."""
        token = create_test_token(
            subject="user123",
            expires_delta_seconds=-3600,
        )
        return HTTPAuthorizationCredentials(scheme="Bearer", credentials=token)

    @pytest.fixture
    def invalid_credentials(self):
        """Create HTTP credentials with invalid token."""
        return HTTPAuthorizationCredentials(
            scheme="Bearer", credentials="invalid.token.string"
        )

    @pytest.mark.asyncio
    async def test_verify_token_valid_not_blacklisted(self, mock_db_session, valid_credentials):
        """Test verify_token succeeds for valid, non-blacklisted token."""
        # Mock the blacklist query to return no results
        mock_result = MagicMock()
        mock_result.scalar_one_or_none.return_value = None
        mock_db_session.execute.return_value = mock_result

        payload = await verify_token(valid_credentials, mock_db_session)

        assert payload["sub"] == "user123"
        assert payload["role"] == "teacher"
        mock_db_session.execute.assert_called_once()

    @pytest.mark.asyncio
    async def test_verify_token_blacklisted_raises_401(self, mock_db_session, valid_credentials):
        """Test verify_token raises 401 for blacklisted token."""
        # Mock the blacklist query to return a result (token is blacklisted)
        mock_blacklist_entry = MagicMock()
        mock_result = MagicMock()
        mock_result.scalar_one_or_none.return_value = mock_blacklist_entry
        mock_db_session.execute.return_value = mock_result

        with pytest.raises(HTTPException) as exc_info:
            await verify_token(valid_credentials, mock_db_session)

        assert exc_info.value.status_code == 401
        assert exc_info.value.detail == "Token has been revoked"
        assert exc_info.value.headers == {"WWW-Authenticate": "Bearer"}

    @pytest.mark.asyncio
    async def test_verify_token_expired_raises_401(self, mock_db_session, expired_credentials):
        """Test verify_token raises 401 for expired token."""
        with pytest.raises(HTTPException) as exc_info:
            await verify_token(expired_credentials, mock_db_session)

        assert exc_info.value.status_code == 401
        # decode_token is called first, should fail before blacklist check
        mock_db_session.execute.assert_not_called()

    @pytest.mark.asyncio
    async def test_verify_token_invalid_raises_401(self, mock_db_session, invalid_credentials):
        """Test verify_token raises 401 for invalid token."""
        with pytest.raises(HTTPException) as exc_info:
            await verify_token(invalid_credentials, mock_db_session)

        assert exc_info.value.status_code == 401
        # Should fail in decode_token before blacklist check
        mock_db_session.execute.assert_not_called()

    @pytest.mark.asyncio
    async def test_verify_token_extracts_credentials(self, mock_db_session):
        """Test verify_token correctly extracts token from credentials."""
        token = create_token(subject="extracted_user")
        credentials = HTTPAuthorizationCredentials(scheme="Bearer", credentials=token)

        mock_result = MagicMock()
        mock_result.scalar_one_or_none.return_value = None
        mock_db_session.execute.return_value = mock_result

        payload = await verify_token(credentials, mock_db_session)
        assert payload["sub"] == "extracted_user"

    @pytest.mark.asyncio
    async def test_verify_token_checks_blacklist_with_correct_token(self, mock_db_session):
        """Test verify_token queries blacklist with the exact token."""
        token = create_token(subject="user123")
        credentials = HTTPAuthorizationCredentials(scheme="Bearer", credentials=token)

        mock_result = MagicMock()
        mock_result.scalar_one_or_none.return_value = None
        mock_db_session.execute.return_value = mock_result

        await verify_token(credentials, mock_db_session)

        # Verify execute was called (checking the blacklist)
        mock_db_session.execute.assert_called_once()
        # The call should include a select statement
        call_args = mock_db_session.execute.call_args
        assert call_args is not None


class TestVerifyTokenIntegration:
    """Integration tests for verify_token with real database fixtures."""

    @pytest.mark.asyncio
    async def test_verify_token_with_db_not_blacklisted(self, auth_db_session):
        """Test verify_token with real DB session - token not in blacklist."""
        token = create_token(
            subject="user123",
            additional_claims={"role": "teacher"},
        )
        credentials = HTTPAuthorizationCredentials(scheme="Bearer", credentials=token)

        payload = await verify_token(credentials, auth_db_session)

        assert payload["sub"] == "user123"
        assert payload["role"] == "teacher"

    @pytest.mark.asyncio
    async def test_verify_token_with_db_blacklisted(self, auth_db_session, teacher_user):
        """Test verify_token with real DB session - token in blacklist."""
        token = create_token(
            subject=str(teacher_user.id),
            additional_claims={"role": "teacher"},
        )

        # Blacklist the token
        await create_token_blacklist_in_db(
            auth_db_session,
            token=token,
            user_id=teacher_user.id,
        )

        credentials = HTTPAuthorizationCredentials(scheme="Bearer", credentials=token)

        with pytest.raises(HTTPException) as exc_info:
            await verify_token(credentials, auth_db_session)

        assert exc_info.value.status_code == 401
        assert exc_info.value.detail == "Token has been revoked"

    @pytest.mark.asyncio
    async def test_verify_token_different_token_not_affected(self, auth_db_session, teacher_user):
        """Test verify_token allows different tokens when one is blacklisted."""
        # Create two tokens for the same user
        token1 = create_token(
            subject=str(teacher_user.id),
            additional_claims={"role": "teacher", "session": "1"},
        )
        token2 = create_token(
            subject=str(teacher_user.id),
            additional_claims={"role": "teacher", "session": "2"},
        )

        # Blacklist only token1
        await create_token_blacklist_in_db(
            auth_db_session,
            token=token1,
            user_id=teacher_user.id,
        )

        # token1 should be rejected
        credentials1 = HTTPAuthorizationCredentials(scheme="Bearer", credentials=token1)
        with pytest.raises(HTTPException):
            await verify_token(credentials1, auth_db_session)

        # token2 should still work
        credentials2 = HTTPAuthorizationCredentials(scheme="Bearer", credentials=token2)
        payload = await verify_token(credentials2, auth_db_session)
        assert payload["sub"] == str(teacher_user.id)
        assert payload["session"] == "2"


class TestJWTSecurityProperties:
    """Tests for JWT security properties."""

    def test_token_cannot_be_tampered(self):
        """Test that tampering with token payload invalidates it."""
        token = create_token(subject="user123", additional_claims={"role": "user"})

        # Decode without verification to tamper
        parts = token.split(".")
        assert len(parts) == 3

        # Try to decode with original token - should work
        payload = decode_token(token)
        assert payload["role"] == "user"

        # Tamper with the token by modifying a character in payload
        modified_token = parts[0] + "." + parts[1][:-1] + "x" + "." + parts[2]

        # Modified token should fail verification
        with pytest.raises(HTTPException) as exc_info:
            decode_token(modified_token)
        assert exc_info.value.status_code == 401

    def test_token_algorithm_enforced(self):
        """Test that only configured algorithm is accepted."""
        # Create token with different algorithm
        payload = {
            "sub": "user123",
            "iat": int(datetime.now(timezone.utc).timestamp()),
            "exp": int((datetime.now(timezone.utc) + timedelta(hours=1)).timestamp()),
        }
        # Try to use 'none' algorithm (security vulnerability if allowed)
        try:
            token = jwt.encode(payload, "", algorithm="none")
            with pytest.raises(HTTPException):
                decode_token(token)
        except jwt.exceptions.InvalidAlgorithmError:
            # Some JWT libraries don't support 'none' algorithm
            pass

    def test_expiration_is_required(self):
        """Test that tokens without expiration claim are rejected."""
        payload = {
            "sub": "user123",
            "iat": int(datetime.now(timezone.utc).timestamp()),
            "iss": settings.jwt_issuer,
            "aud": settings.jwt_audience,
            # No 'exp' claim
        }
        token = jwt.encode(
            payload,
            settings.jwt_secret_key,
            algorithm=settings.jwt_algorithm,
        )
        # Tokens without exp claim should be rejected
        with pytest.raises(HTTPException) as exc_info:
            decode_token(token)
        assert exc_info.value.status_code == 401
        assert "exp" in exc_info.value.detail.lower()

    def test_token_contains_all_standard_claims(self):
        """Test that created tokens have standard JWT claims."""
        token = create_token(subject="user123")
        payload = decode_token(token)

        # Check standard claims
        assert "sub" in payload  # Subject
        assert "iat" in payload  # Issued At
        assert "exp" in payload  # Expiration

    def test_iat_is_current_time(self):
        """Test that iat claim is set to current time."""
        before = datetime.now(timezone.utc)
        token = create_token(subject="user123")
        after = datetime.now(timezone.utc)

        payload = decode_token(token)
        iat = datetime.fromtimestamp(payload["iat"], tz=timezone.utc)

        # JWT timestamps are in seconds (no microseconds), so we need to truncate
        # the before/after times for comparison
        before_seconds = before.replace(microsecond=0)
        after_seconds = after.replace(microsecond=0) + timedelta(seconds=1)
        assert before_seconds <= iat <= after_seconds

    def test_token_missing_exp_claim_raises_401(self):
        """Test that tokens missing exp claim are rejected."""
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
        # decode_token should raise 401 for missing exp claim
        with pytest.raises(HTTPException) as exc_info:
            decode_token(token)
        assert exc_info.value.status_code == 401
        assert "exp" in exc_info.value.detail.lower()

    def test_token_missing_iat_claim_raises_401(self):
        """Test that tokens missing iat claim are rejected."""
        # Create token without iat claim
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
        # decode_token should raise 401 for missing iat claim
        with pytest.raises(HTTPException) as exc_info:
            decode_token(token)
        assert exc_info.value.status_code == 401
        assert "iat" in exc_info.value.detail.lower()

    def test_token_missing_sub_claim_raises_401(self):
        """Test that tokens missing sub claim are rejected."""
        # Create token without sub claim
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
        # decode_token should raise 401 for missing sub claim
        with pytest.raises(HTTPException) as exc_info:
            decode_token(token)
        assert exc_info.value.status_code == 401
        assert "sub" in exc_info.value.detail.lower()

    def test_create_token_includes_issuer(self):
        """Test that created tokens include issuer claim."""
        token = create_token(subject="user123")
        payload = jwt.decode(
            token,
            settings.jwt_secret_key,
            algorithms=[settings.jwt_algorithm],
            audience=settings.jwt_audience,
            issuer=settings.jwt_issuer,
        )
        assert "iss" in payload
        assert payload["iss"] == settings.jwt_issuer

    def test_create_token_includes_audience(self):
        """Test that created tokens include audience claim."""
        token = create_token(subject="user123")
        payload = jwt.decode(
            token,
            settings.jwt_secret_key,
            algorithms=[settings.jwt_algorithm],
            audience=settings.jwt_audience,
            issuer=settings.jwt_issuer,
        )
        assert "aud" in payload
        assert payload["aud"] == settings.jwt_audience

    def test_decode_token_validates_issuer(self):
        """Test that decode_token rejects tokens with wrong issuer."""
        # Create token with wrong issuer
        payload = {
            "sub": "user123",
            "iat": int(datetime.now(timezone.utc).timestamp()),
            "exp": int((datetime.now(timezone.utc) + timedelta(hours=1)).timestamp()),
            "iss": "evil-issuer",
            "aud": settings.jwt_audience,
        }
        token = jwt.encode(
            payload,
            settings.jwt_secret_key,
            algorithm=settings.jwt_algorithm,
        )
        # decode_token should raise 401 for wrong issuer
        with pytest.raises(HTTPException) as exc_info:
            decode_token(token)
        assert exc_info.value.status_code == 401
        assert "issuer" in exc_info.value.detail.lower()

    def test_decode_token_validates_audience(self):
        """Test that decode_token rejects tokens with wrong audience."""
        # Create token with wrong audience
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
        # decode_token should raise 401 for wrong audience
        with pytest.raises(HTTPException) as exc_info:
            decode_token(token)
        assert exc_info.value.status_code == 401
        assert "audience" in exc_info.value.detail.lower()

    def test_token_missing_issuer_claim_raises_401(self):
        """Test that tokens missing issuer claim are rejected."""
        # Create token without issuer claim
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
        # decode_token should raise 401 for missing issuer claim
        with pytest.raises(HTTPException) as exc_info:
            decode_token(token)
        assert exc_info.value.status_code == 401
        assert "iss" in exc_info.value.detail.lower()

    def test_token_missing_audience_claim_raises_401(self):
        """Test that tokens missing audience claim are rejected."""
        # Create token without audience claim
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
        # decode_token should raise 401 for missing audience claim
        with pytest.raises(HTTPException) as exc_info:
            decode_token(token)
        assert exc_info.value.status_code == 401
        assert "aud" in exc_info.value.detail.lower()

    def test_issuer_validation_prevents_token_reuse_across_services(self):
        """Test that issuer validation prevents tokens from other services being accepted."""
        # Simulate token from a different service with same secret
        other_service_token = jwt.encode(
            {
                "sub": "user123",
                "iat": int(datetime.now(timezone.utc).timestamp()),
                "exp": int((datetime.now(timezone.utc) + timedelta(hours=1)).timestamp()),
                "iss": "other-service",
                "aud": settings.jwt_audience,
            },
            settings.jwt_secret_key,  # Same secret but different issuer
            algorithm=settings.jwt_algorithm,
        )
        # Should be rejected due to wrong issuer
        with pytest.raises(HTTPException) as exc_info:
            decode_token(other_service_token)
        assert exc_info.value.status_code == 401

    def test_audience_validation_prevents_token_misuse(self):
        """Test that audience validation prevents tokens intended for other audiences."""
        # Create token intended for different audience
        wrong_audience_token = jwt.encode(
            {
                "sub": "user123",
                "iat": int(datetime.now(timezone.utc).timestamp()),
                "exp": int((datetime.now(timezone.utc) + timedelta(hours=1)).timestamp()),
                "iss": settings.jwt_issuer,
                "aud": "different-api",
            },
            settings.jwt_secret_key,
            algorithm=settings.jwt_algorithm,
        )
        # Should be rejected due to wrong audience
        with pytest.raises(HTTPException) as exc_info:
            decode_token(wrong_audience_token)
        assert exc_info.value.status_code == 401

    def test_issuer_and_audience_both_must_be_valid(self):
        """Test that both issuer and audience must be valid together."""
        # Valid issuer, wrong audience
        token1 = jwt.encode(
            {
                "sub": "user123",
                "iat": int(datetime.now(timezone.utc).timestamp()),
                "exp": int((datetime.now(timezone.utc) + timedelta(hours=1)).timestamp()),
                "iss": settings.jwt_issuer,
                "aud": "wrong-audience",
            },
            settings.jwt_secret_key,
            algorithm=settings.jwt_algorithm,
        )
        with pytest.raises(HTTPException):
            decode_token(token1)

        # Wrong issuer, valid audience
        token2 = jwt.encode(
            {
                "sub": "user123",
                "iat": int(datetime.now(timezone.utc).timestamp()),
                "exp": int((datetime.now(timezone.utc) + timedelta(hours=1)).timestamp()),
                "iss": "wrong-issuer",
                "aud": settings.jwt_audience,
            },
            settings.jwt_secret_key,
            algorithm=settings.jwt_algorithm,
        )
        with pytest.raises(HTTPException):
            decode_token(token2)

        # Both correct - should work
        token3 = create_token(subject="user123")
        payload = decode_token(token3)
        assert payload["sub"] == "user123"


class TestSignatureVerificationSecurity:
    """Tests ensuring signature verification cannot be bypassed."""

    def test_token_signed_with_wrong_secret_rejected(self):
        """Test that tokens signed with incorrect secret key are rejected."""
        # Create token with wrong secret
        payload = {
            "sub": "user123",
            "iat": int(datetime.now(timezone.utc).timestamp()),
            "exp": int((datetime.now(timezone.utc) + timedelta(hours=1)).timestamp()),
            "iss": settings.jwt_issuer,
            "aud": settings.jwt_audience,
        }
        wrong_secret_token = jwt.encode(
            payload,
            "completely_wrong_secret_key_12345",
            algorithm=settings.jwt_algorithm,
        )
        # Should be rejected due to signature mismatch
        with pytest.raises(HTTPException) as exc_info:
            decode_token(wrong_secret_token)
        assert exc_info.value.status_code == 401
        assert "Invalid token" in exc_info.value.detail

    def test_token_with_modified_signature_rejected(self):
        """Test that tokens with modified signatures are rejected."""
        # Create valid token
        token = create_token(subject="user123")
        parts = token.split(".")

        # Modify the signature part
        modified_signature = parts[2][:-5] + "XXXXX"
        tampered_token = f"{parts[0]}.{parts[1]}.{modified_signature}"

        # Should be rejected due to invalid signature
        with pytest.raises(HTTPException) as exc_info:
            decode_token(tampered_token)
        assert exc_info.value.status_code == 401

    def test_token_with_signature_from_different_token_rejected(self):
        """Test that mixing payload from one token with signature from another is rejected."""
        # Create two different tokens
        token1 = create_token(subject="user1")
        token2 = create_token(subject="user2")

        # Split both tokens
        parts1 = token1.split(".")
        parts2 = token2.split(".")

        # Create hybrid token: payload from token1, signature from token2
        hybrid_token = f"{parts1[0]}.{parts1[1]}.{parts2[2]}"

        # Should be rejected due to signature mismatch
        with pytest.raises(HTTPException) as exc_info:
            decode_token(hybrid_token)
        assert exc_info.value.status_code == 401

    def test_unsigned_token_with_none_algorithm_rejected(self):
        """Test that unsigned tokens using 'none' algorithm are rejected."""
        # Create payload without signature
        payload = {
            "sub": "user123",
            "iat": int(datetime.now(timezone.utc).timestamp()),
            "exp": int((datetime.now(timezone.utc) + timedelta(hours=1)).timestamp()),
            "iss": settings.jwt_issuer,
            "aud": settings.jwt_audience,
        }

        try:
            # Try to create token with 'none' algorithm (no signature)
            unsigned_token = jwt.encode(payload, "", algorithm="none")

            # Should be rejected
            with pytest.raises(HTTPException) as exc_info:
                decode_token(unsigned_token)
            assert exc_info.value.status_code == 401
        except jwt.exceptions.InvalidAlgorithmError:
            # Some JWT libraries don't allow 'none' algorithm - that's good!
            pass

    def test_token_with_modified_payload_rejected(self):
        """Test that tokens with modified payload but valid structure are rejected."""
        # Create valid token
        token = create_token(subject="regular_user", additional_claims={"role": "user"})
        parts = token.split(".")

        # Decode payload to modify it
        import base64
        import json

        # Decode the payload
        padded_payload = parts[1] + "=" * (4 - len(parts[1]) % 4)
        payload_bytes = base64.urlsafe_b64decode(padded_payload)
        payload = json.loads(payload_bytes)

        # Modify the payload (try to escalate privileges)
        payload["role"] = "admin"
        payload["sub"] = "admin_user"

        # Re-encode modified payload
        modified_payload = base64.urlsafe_b64encode(
            json.dumps(payload).encode()
        ).decode().rstrip("=")

        # Create token with modified payload but original signature
        tampered_token = f"{parts[0]}.{modified_payload}.{parts[2]}"

        # Should be rejected due to signature mismatch
        with pytest.raises(HTTPException) as exc_info:
            decode_token(tampered_token)
        assert exc_info.value.status_code == 401

    def test_token_signature_verification_not_optional(self):
        """Test that signature verification cannot be bypassed by any means."""
        # Create a valid token
        valid_token = create_token(subject="user123")

        # Verify it works first
        payload = decode_token(valid_token)
        assert payload["sub"] == "user123"

        # Now try various bypass attempts
        parts = valid_token.split(".")

        # Attempt 1: Empty signature
        no_sig_token = f"{parts[0]}.{parts[1]}."
        with pytest.raises(HTTPException):
            decode_token(no_sig_token)

        # Attempt 2: Garbage signature
        garbage_sig_token = f"{parts[0]}.{parts[1]}.invalidgarbage"
        with pytest.raises(HTTPException):
            decode_token(garbage_sig_token)

    def test_signature_verified_before_claims_processed(self):
        """Test that signature is verified before processing any claims."""
        # Create token with wrong signature but valid-looking payload
        payload = {
            "sub": "admin",
            "iat": int(datetime.now(timezone.utc).timestamp()),
            "exp": int((datetime.now(timezone.utc) + timedelta(hours=1)).timestamp()),
            "iss": settings.jwt_issuer,
            "aud": settings.jwt_audience,
            "role": "superadmin",
        }
        wrong_sig_token = jwt.encode(
            payload,
            "wrong_secret",
            algorithm=settings.jwt_algorithm,
        )

        # Even though payload looks valid, should fail due to signature
        with pytest.raises(HTTPException) as exc_info:
            decode_token(wrong_sig_token)
        assert exc_info.value.status_code == 401

    def test_algorithm_switching_attack_prevented(self):
        """Test that algorithm switching attacks are prevented."""
        # This tests against attacks where an attacker tries to change
        # the algorithm (e.g., from RS256 to HS256) to bypass signature verification

        payload = {
            "sub": "user123",
            "iat": int(datetime.now(timezone.utc).timestamp()),
            "exp": int((datetime.now(timezone.utc) + timedelta(hours=1)).timestamp()),
            "iss": settings.jwt_issuer,
            "aud": settings.jwt_audience,
        }

        # Try to create token with different algorithm
        # If we're using HS256, try HS512; if RS256, try HS256, etc.
        alternative_algorithms = ["HS512", "HS384", "RS256"]

        for alt_alg in alternative_algorithms:
            if alt_alg != settings.jwt_algorithm:
                try:
                    # Create token with alternative algorithm
                    alt_token = jwt.encode(
                        payload,
                        settings.jwt_secret_key,
                        algorithm=alt_alg,
                    )

                    # Should be rejected due to algorithm mismatch
                    with pytest.raises(HTTPException) as exc_info:
                        decode_token(alt_token)
                    assert exc_info.value.status_code == 401
                except (jwt.exceptions.InvalidAlgorithmError, ValueError, NotImplementedError):
                    # Some algorithms may not be supported - that's fine
                    pass

    @pytest.mark.asyncio
    async def test_verify_token_checks_signature_before_blacklist(self, mock_db_session):
        """Test that signature verification happens before blacklist check."""
        # Create token with wrong signature
        payload = {
            "sub": "user123",
            "iat": int(datetime.now(timezone.utc).timestamp()),
            "exp": int((datetime.now(timezone.utc) + timedelta(hours=1)).timestamp()),
            "iss": settings.jwt_issuer,
            "aud": settings.jwt_audience,
        }
        invalid_sig_token = jwt.encode(
            payload,
            "wrong_secret",
            algorithm=settings.jwt_algorithm,
        )
        credentials = HTTPAuthorizationCredentials(
            scheme="Bearer", credentials=invalid_sig_token
        )

        # Should fail before reaching blacklist check
        with pytest.raises(HTTPException) as exc_info:
            await verify_token(credentials, mock_db_session)

        assert exc_info.value.status_code == 401
        # Database should not be queried since signature check fails first
        mock_db_session.execute.assert_not_called()

    @pytest.fixture
    def mock_db_session(self):
        """Create a mock database session."""
        return AsyncMock()
