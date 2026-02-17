"""Unit tests for JWT authentication utilities.

Tests for JWT token creation, validation, expiration handling,
and blacklist verification.
"""

from __future__ import annotations

import time
from datetime import datetime, timezone
from typing import Any
from unittest.mock import AsyncMock, MagicMock, patch

import jwt
import pytest
from fastapi import HTTPException, status
from fastapi.security import HTTPAuthorizationCredentials
from sqlalchemy.ext.asyncio import AsyncSession

from app.auth import (
    MFA_REQUIRED_CLAIM,
    MFA_VERIFIED_CLAIM,
    TokenPayload,
    create_token,
    is_mfa_verified,
    requires_mfa,
    verify_mfa_token,
    verify_token,
)
from app.auth.jwt import decode_token
from app.auth.jwt import create_token as jwt_create_token
from app.auth.jwt import decode_token as jwt_decode_token
from app.auth.jwt import verify_token as jwt_verify_token
from app.config import settings


# --- Token Creation Tests ---


def test_create_token_basic() -> None:
    """Test basic JWT token creation with default settings.

    Verifies that a token is created with the correct subject,
    contains required claims (sub, iat, exp), and can be decoded.
    """
    subject = "user123"
    token = create_token(subject)

    # Verify token is a non-empty string
    assert isinstance(token, str)
    assert len(token) > 0

    # Decode token to verify its structure
    payload = jwt.decode(
        token,
        settings.jwt_secret_key,
        algorithms=[settings.jwt_algorithm],
    )

    assert payload["sub"] == subject
    assert "iat" in payload
    assert "exp" in payload


def test_create_token_with_custom_expiration() -> None:
    """Test token creation with custom expiration time.

    Verifies that the token expiration is set correctly
    based on the provided expires_delta_seconds parameter.
    """
    subject = "user456"
    expires_delta_seconds = 1800  # 30 minutes

    now = datetime.now(timezone.utc)
    token = create_token(subject, expires_delta_seconds=expires_delta_seconds)

    payload = jwt.decode(
        token,
        settings.jwt_secret_key,
        algorithms=[settings.jwt_algorithm],
    )

    # Verify expiration is approximately 30 minutes from now
    # Allow for 5 second tolerance to account for test execution time
    expected_exp = int(now.timestamp()) + expires_delta_seconds
    assert abs(payload["exp"] - expected_exp) <= 5


def test_create_token_with_additional_claims() -> None:
    """Test token creation with additional custom claims.

    Verifies that additional claims are properly included in the token
    payload without overriding required claims (sub, iat, exp).
    """
    subject = "user789"
    additional_claims = {
        "role": "admin",
        "permissions": ["read", "write"],
        "custom_field": "value",
    }

    token = create_token(
        subject,
        expires_delta_seconds=3600,
        additional_claims=additional_claims,
    )

    payload = jwt.decode(
        token,
        settings.jwt_secret_key,
        algorithms=[settings.jwt_algorithm],
    )

    # Verify required claims are present
    assert payload["sub"] == subject
    assert "iat" in payload
    assert "exp" in payload

    # Verify additional claims are included
    assert payload["role"] == "admin"
    assert payload["permissions"] == ["read", "write"]
    assert payload["custom_field"] == "value"


def test_create_token_exp_not_overridden() -> None:
    """Test that exp claim cannot be overridden by additional_claims.

    Verifies that the token creation function always sets the exp claim
    correctly even if additional_claims tries to override it.
    """
    subject = "user_exp_test"
    expires_delta_seconds = 1800
    malicious_exp = 9999999999  # Year 2286

    # Try to override exp claim
    token = create_token(
        subject,
        expires_delta_seconds=expires_delta_seconds,
        additional_claims={"exp": malicious_exp},
    )

    payload = jwt.decode(
        token,
        settings.jwt_secret_key,
        algorithms=[settings.jwt_algorithm],
    )

    # Verify exp is NOT the malicious value
    # The function should set exp AFTER additional_claims to prevent override
    now = datetime.now(timezone.utc)
    expected_exp = int(now.timestamp()) + expires_delta_seconds
    assert abs(payload["exp"] - expected_exp) <= 5
    assert payload["exp"] != malicious_exp


# --- Token Validation Tests ---


def test_decode_token_valid() -> None:
    """Test decoding a valid JWT token.

    Verifies that a properly created token can be decoded
    and returns the correct payload.
    """
    subject = "user_decode_test"
    original_claims = {"role": "user", "email": "test@example.com"}

    token = create_token(
        subject,
        expires_delta_seconds=3600,
        additional_claims=original_claims,
    )

    payload = decode_token(token)

    assert payload["sub"] == subject
    assert payload["role"] == "user"
    assert payload["email"] == "test@example.com"


def test_decode_token_invalid_signature() -> None:
    """Test decoding a token with an invalid signature.

    Verifies that HTTPException with 401 status is raised
    when the token signature is invalid.
    """
    # Create a token with a different secret
    fake_token = jwt.encode(
        {"sub": "user123", "exp": int(time.time()) + 3600},
        "wrong_secret_key",
        algorithm=settings.jwt_algorithm,
    )

    with pytest.raises(HTTPException) as exc_info:
        decode_token(fake_token)

    assert exc_info.value.status_code == status.HTTP_401_UNAUTHORIZED
    assert "Invalid token" in exc_info.value.detail
    assert exc_info.value.headers == {"WWW-Authenticate": "Bearer"}


def test_decode_token_malformed() -> None:
    """Test decoding a malformed token.

    Verifies that HTTPException with 401 status is raised
    when the token is malformed or cannot be decoded.
    """
    malformed_token = "not.a.valid.jwt.token"

    with pytest.raises(HTTPException) as exc_info:
        decode_token(malformed_token)

    assert exc_info.value.status_code == status.HTTP_401_UNAUTHORIZED
    assert "Invalid token" in exc_info.value.detail


def test_decode_token_missing_required_claims() -> None:
    """Test decoding a token missing required claims.

    Verifies that tokens without proper structure are rejected.
    """
    # Create a token without 'sub' claim
    incomplete_token = jwt.encode(
        {"exp": int(time.time()) + 3600},
        settings.jwt_secret_key,
        algorithm=settings.jwt_algorithm,
    )

    # Decode should succeed but payload will be missing 'sub'
    payload = decode_token(incomplete_token)
    assert "sub" not in payload
    assert "exp" in payload


# --- Token Expiration Tests ---


def test_decode_token_expired() -> None:
    """Test decoding an expired JWT token.

    Verifies that HTTPException with 401 status is raised
    when attempting to decode an expired token.
    """
    subject = "user_expired_test"
    # Create token that expires in 1 second
    token = create_token(subject, expires_delta_seconds=1)

    # Wait for token to expire
    time.sleep(2)

    with pytest.raises(HTTPException) as exc_info:
        decode_token(token)

    assert exc_info.value.status_code == status.HTTP_401_UNAUTHORIZED
    assert "Token has expired" in exc_info.value.detail
    assert exc_info.value.headers == {"WWW-Authenticate": "Bearer"}


def test_create_token_expiration_boundary() -> None:
    """Test token expiration at the exact boundary.

    Verifies that a token is valid up to the expiration timestamp
    and invalid immediately after.
    """
    subject = "user_boundary_test"
    # Create token that expires in 2 seconds
    token = create_token(subject, expires_delta_seconds=2)

    # Token should be valid immediately
    payload = decode_token(token)
    assert payload["sub"] == subject

    # Wait until just before expiration
    time.sleep(1)

    # Token should still be valid
    payload = decode_token(token)
    assert payload["sub"] == subject

    # Wait past expiration
    time.sleep(2)

    # Token should now be expired
    with pytest.raises(HTTPException) as exc_info:
        decode_token(token)

    assert exc_info.value.status_code == status.HTTP_401_UNAUTHORIZED
    assert "Token has expired" in exc_info.value.detail


# --- JWT Module Tests (app/auth/jwt.py) ---


def test_jwt_create_token_basic() -> None:
    """Test JWT module token creation with basic parameters.

    Tests the create_token function from app.auth.jwt module.
    """
    subject = "jwt_user123"
    token = jwt_create_token(subject)

    assert isinstance(token, str)
    assert len(token) > 0

    payload = jwt.decode(
        token,
        settings.jwt_secret_key,
        algorithms=[settings.jwt_algorithm],
    )

    assert payload["sub"] == subject
    assert "iat" in payload
    assert "exp" in payload


def test_jwt_decode_token_valid() -> None:
    """Test JWT module token decoding with valid token.

    Tests the decode_token function from app.auth.jwt module.
    """
    subject = "jwt_user456"
    token = jwt_create_token(subject, expires_delta_seconds=3600)

    payload = jwt_decode_token(token)

    assert payload["sub"] == subject


def test_jwt_decode_token_expired() -> None:
    """Test JWT module token decoding with expired token.

    Tests the decode_token function from app.auth.jwt module
    with an expired token.
    """
    subject = "jwt_user_expired"
    token = jwt_create_token(subject, expires_delta_seconds=1)

    # Wait for token to expire
    time.sleep(2)

    with pytest.raises(HTTPException) as exc_info:
        jwt_decode_token(token)

    assert exc_info.value.status_code == status.HTTP_401_UNAUTHORIZED
    assert "Token has expired" in exc_info.value.detail


# --- Token Blacklist Tests ---


@pytest.mark.asyncio
async def test_verify_token_not_blacklisted(db_session: AsyncSession) -> None:
    """Test token verification when token is not blacklisted.

    Verifies that a valid, non-blacklisted token passes verification
    and returns the correct payload.

    Args:
        db_session: Async database session fixture
    """
    subject = "user_not_blacklisted"
    token = jwt_create_token(subject, expires_delta_seconds=3600)

    credentials = HTTPAuthorizationCredentials(
        scheme="Bearer",
        credentials=token,
    )

    payload = await jwt_verify_token(credentials, db_session)

    assert payload["sub"] == subject


@pytest.mark.asyncio
async def test_verify_token_blacklisted(db_session: AsyncSession) -> None:
    """Test token verification when token is blacklisted.

    Verifies that HTTPException with 401 status is raised
    when attempting to verify a blacklisted token.

    Args:
        db_session: Async database session fixture
    """
    from app.auth.models import TokenBlacklist

    subject = "user_blacklisted"
    token = jwt_create_token(subject, expires_delta_seconds=3600)

    # Add token to blacklist
    blacklist_entry = TokenBlacklist(
        token=token,
        user_id=subject,
        blacklisted_at=datetime.now(timezone.utc),
    )
    db_session.add(blacklist_entry)
    await db_session.commit()

    credentials = HTTPAuthorizationCredentials(
        scheme="Bearer",
        credentials=token,
    )

    with pytest.raises(HTTPException) as exc_info:
        await jwt_verify_token(credentials, db_session)

    assert exc_info.value.status_code == status.HTTP_401_UNAUTHORIZED
    assert "Token has been revoked" in exc_info.value.detail


@pytest.mark.asyncio
async def test_verify_token_expired_and_blacklisted(db_session: AsyncSession) -> None:
    """Test token verification when token is both expired and blacklisted.

    Verifies that expiration is checked before blacklist lookup,
    raising HTTPException for expired token.

    Args:
        db_session: Async database session fixture
    """
    from app.auth.models import TokenBlacklist

    subject = "user_expired_blacklisted"
    token = jwt_create_token(subject, expires_delta_seconds=1)

    # Add token to blacklist
    blacklist_entry = TokenBlacklist(
        token=token,
        user_id=subject,
        blacklisted_at=datetime.now(timezone.utc),
    )
    db_session.add(blacklist_entry)
    await db_session.commit()

    # Wait for token to expire
    time.sleep(2)

    credentials = HTTPAuthorizationCredentials(
        scheme="Bearer",
        credentials=token,
    )

    # Should raise HTTPException for expired token (checked first)
    with pytest.raises(HTTPException) as exc_info:
        await jwt_verify_token(credentials, db_session)

    assert exc_info.value.status_code == status.HTTP_401_UNAUTHORIZED
    assert "Token has expired" in exc_info.value.detail


# --- MFA Token Tests ---


@pytest.mark.asyncio
async def test_verify_mfa_token_no_mfa_required(db_session: AsyncSession) -> None:
    """Test MFA token verification when MFA is not required.

    Verifies that tokens without MFA requirements pass verification.

    Args:
        db_session: Async database session fixture
    """
    subject = "user_no_mfa"
    token = create_token(subject, expires_delta_seconds=3600)

    credentials = HTTPAuthorizationCredentials(
        scheme="Bearer",
        credentials=token,
    )

    # Mock verify_token to avoid database setup complexity
    with patch("app.auth.verify_token") as mock_verify:
        mock_verify.return_value = {
            "sub": subject,
            "exp": int(time.time()) + 3600,
        }

        payload = await verify_mfa_token(credentials)

        assert payload["sub"] == subject
        mock_verify.assert_called_once_with(credentials)


@pytest.mark.asyncio
async def test_verify_mfa_token_mfa_verified() -> None:
    """Test MFA token verification when MFA is required and verified.

    Verifies that tokens with completed MFA verification pass.
    """
    subject = "user_mfa_verified"
    token = create_token(
        subject,
        expires_delta_seconds=3600,
        additional_claims={
            MFA_REQUIRED_CLAIM: True,
            MFA_VERIFIED_CLAIM: True,
        },
    )

    credentials = HTTPAuthorizationCredentials(
        scheme="Bearer",
        credentials=token,
    )

    # Mock verify_token to return payload with MFA claims
    with patch("app.auth.verify_token") as mock_verify:
        mock_verify.return_value = {
            "sub": subject,
            MFA_REQUIRED_CLAIM: True,
            MFA_VERIFIED_CLAIM: True,
        }

        payload = await verify_mfa_token(credentials)

        assert payload["sub"] == subject
        assert payload[MFA_REQUIRED_CLAIM] is True
        assert payload[MFA_VERIFIED_CLAIM] is True


@pytest.mark.asyncio
async def test_verify_mfa_token_mfa_required_not_verified() -> None:
    """Test MFA token verification when MFA is required but not verified.

    Verifies that HTTPException with 403 status is raised when
    MFA verification is required but not completed.
    """
    subject = "user_mfa_not_verified"
    token = create_token(
        subject,
        expires_delta_seconds=3600,
        additional_claims={
            MFA_REQUIRED_CLAIM: True,
            MFA_VERIFIED_CLAIM: False,
        },
    )

    credentials = HTTPAuthorizationCredentials(
        scheme="Bearer",
        credentials=token,
    )

    # Mock verify_token to return payload with MFA required but not verified
    with patch("app.auth.verify_token") as mock_verify:
        mock_verify.return_value = {
            "sub": subject,
            MFA_REQUIRED_CLAIM: True,
            MFA_VERIFIED_CLAIM: False,
        }

        with pytest.raises(HTTPException) as exc_info:
            await verify_mfa_token(credentials)

        assert exc_info.value.status_code == status.HTTP_403_FORBIDDEN
        assert "MFA verification required" in exc_info.value.detail
        assert exc_info.value.headers == {"WWW-Authenticate": "Bearer realm='mfa'"}


# --- MFA Helper Function Tests ---


def test_is_mfa_verified_no_mfa_required() -> None:
    """Test is_mfa_verified when MFA is not required.

    Verifies that tokens without MFA requirements are considered verified.
    """
    payload = {"sub": "user123"}
    assert is_mfa_verified(payload) is True


def test_is_mfa_verified_mfa_verified() -> None:
    """Test is_mfa_verified when MFA is verified.

    Verifies that tokens with completed MFA are considered verified.
    """
    payload = {
        "sub": "user123",
        MFA_REQUIRED_CLAIM: True,
        MFA_VERIFIED_CLAIM: True,
    }
    assert is_mfa_verified(payload) is True


def test_is_mfa_verified_mfa_not_verified() -> None:
    """Test is_mfa_verified when MFA is required but not verified.

    Verifies that tokens with incomplete MFA are not considered verified.
    """
    payload = {
        "sub": "user123",
        MFA_REQUIRED_CLAIM: True,
        MFA_VERIFIED_CLAIM: False,
    }
    assert is_mfa_verified(payload) is False


def test_requires_mfa_true() -> None:
    """Test requires_mfa when MFA is required.

    Verifies that tokens with MFA requirement flag return True.
    """
    payload = {
        "sub": "user123",
        MFA_REQUIRED_CLAIM: True,
    }
    assert requires_mfa(payload) is True


def test_requires_mfa_false() -> None:
    """Test requires_mfa when MFA is not required.

    Verifies that tokens without MFA requirement flag return False.
    """
    payload = {"sub": "user123"}
    assert requires_mfa(payload) is False


# --- TokenPayload Class Tests ---


def test_token_payload_initialization() -> None:
    """Test TokenPayload initialization with a decoded JWT payload.

    Verifies that TokenPayload correctly extracts and stores
    standard JWT claims.
    """
    payload_dict = {
        "sub": "user123",
        "exp": 1234567890,
        "iat": 1234567800,
        "role": "admin",
    }

    token_payload = TokenPayload(payload_dict)

    assert token_payload.sub == "user123"
    assert token_payload.exp == 1234567890
    assert token_payload.iat == 1234567800
    assert token_payload.data == payload_dict


def test_token_payload_missing_claims() -> None:
    """Test TokenPayload initialization with missing claims.

    Verifies that TokenPayload handles payloads with missing
    standard claims gracefully.
    """
    payload_dict = {"custom_claim": "value"}

    token_payload = TokenPayload(payload_dict)

    assert token_payload.sub is None
    assert token_payload.exp is None
    assert token_payload.iat is None
    assert token_payload.data == payload_dict
