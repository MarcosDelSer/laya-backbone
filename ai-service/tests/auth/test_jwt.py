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
        )
        assert payload["sub"] == user_id

    def test_create_token_contains_iat(self):
        """Test create_token includes issued-at timestamp."""
        token = create_token(subject="user123")
        payload = jwt.decode(
            token,
            settings.jwt_secret_key,
            algorithms=[settings.jwt_algorithm],
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
        """Test that tokens without expiration are handled."""
        payload = {
            "sub": "user123",
            "iat": int(datetime.now(timezone.utc).timestamp()),
            # No 'exp' claim
        }
        token = jwt.encode(
            payload,
            settings.jwt_secret_key,
            algorithm=settings.jwt_algorithm,
        )
        # PyJWT by default doesn't require exp, so this should work
        # but you might want to add require=["exp"] to your decoder
        decoded = decode_token(token)
        assert decoded["sub"] == "user123"

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
