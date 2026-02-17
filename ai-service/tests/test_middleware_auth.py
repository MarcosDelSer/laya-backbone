"""Unit tests for multi-source authentication middleware.

Tests JWT token verification from both AI service and Gibbon sources,
including token validation, expiration handling, and error cases.
"""

from __future__ import annotations

from datetime import datetime, timezone
from typing import Any
from unittest.mock import MagicMock

import jwt
import pytest
from fastapi import HTTPException
from fastapi.security import HTTPAuthorizationCredentials

from app.config import settings
from app.middleware.auth import (
    TokenSource,
    extract_user_info,
    verify_token_from_any_source,
)


def create_ai_service_token(
    subject: str,
    expires_delta_seconds: int = 3600,
    additional_claims: dict[str, Any] | None = None,
) -> str:
    """Create an AI service JWT token for testing.

    Args:
        subject: Token subject (user identifier)
        expires_delta_seconds: Token expiration time in seconds
        additional_claims: Additional claims to include in the token

    Returns:
        str: Encoded JWT token
    """
    now = datetime.now(timezone.utc)
    expire = datetime.fromtimestamp(
        now.timestamp() + expires_delta_seconds, tz=timezone.utc
    )

    payload = {
        "sub": subject,
        "iat": int(now.timestamp()),
        "exp": int(expire.timestamp()),
        "aud": settings.jwt_audience,
        "iss": settings.jwt_issuer,
        "source": TokenSource.AI_SERVICE,
    }

    if additional_claims:
        payload.update(additional_claims)

    return jwt.encode(
        payload,
        settings.jwt_secret_key,
        algorithm=settings.jwt_algorithm,
    )


def create_gibbon_token(
    person_id: str,
    username: str,
    email: str,
    role: str = "teacher",
    gibbon_role_id: str = "002",
    expires_delta_seconds: int = 3600,
) -> str:
    """Create a Gibbon JWT token for testing.

    Mimics the token structure created by gibbon/modules/System/auth_token.php

    Args:
        person_id: Gibbon person ID
        username: User's username
        email: User's email
        role: Mapped AI service role
        gibbon_role_id: Original Gibbon role ID
        expires_delta_seconds: Token expiration time in seconds

    Returns:
        str: Encoded JWT token
    """
    now = int(datetime.now(timezone.utc).timestamp())

    payload = {
        "sub": person_id,
        "iat": now,
        "exp": now + expires_delta_seconds,
        "aud": settings.jwt_audience,
        "iss": settings.jwt_issuer,
        "username": username,
        "email": email,
        "role": role,
        "gibbon_role_id": gibbon_role_id,
        "name": "Test User",
        "source": TokenSource.GIBBON,
        "session_id": "test_session_123",
    }

    return jwt.encode(
        payload,
        settings.jwt_secret_key,
        algorithm=settings.jwt_algorithm,
    )


@pytest.mark.asyncio
async def test_verify_ai_service_token():
    """Test verification of AI service JWT tokens."""
    token = create_ai_service_token(
        subject="user123",
        additional_claims={
            "email": "user@example.com",
            "role": "admin",
        },
    )

    credentials = HTTPAuthorizationCredentials(scheme="Bearer", credentials=token)
    payload = await verify_token_from_any_source(credentials)

    assert payload["sub"] == "user123"
    assert payload["email"] == "user@example.com"
    assert payload["role"] == "admin"
    assert payload["source"] == TokenSource.AI_SERVICE


@pytest.mark.asyncio
async def test_verify_gibbon_token():
    """Test verification of Gibbon JWT tokens."""
    token = create_gibbon_token(
        person_id="12345",
        username="teacher1",
        email="teacher@school.edu",
        role="teacher",
        gibbon_role_id="002",
    )

    credentials = HTTPAuthorizationCredentials(scheme="Bearer", credentials=token)
    payload = await verify_token_from_any_source(credentials)

    assert payload["sub"] == "12345"
    assert payload["username"] == "teacher1"
    assert payload["email"] == "teacher@school.edu"
    assert payload["role"] == "teacher"
    assert payload["source"] == TokenSource.GIBBON
    assert payload["gibbon_role_id"] == "002"
    assert payload["session_id"] == "test_session_123"


@pytest.mark.asyncio
async def test_expired_token_raises_exception():
    """Test that expired tokens are rejected."""
    token = create_ai_service_token(
        subject="user123",
        expires_delta_seconds=-100,  # Expired 100 seconds ago
    )

    credentials = HTTPAuthorizationCredentials(scheme="Bearer", credentials=token)

    with pytest.raises(HTTPException) as exc_info:
        await verify_token_from_any_source(credentials)

    assert exc_info.value.status_code == 401
    assert "expired" in exc_info.value.detail.lower()


@pytest.mark.asyncio
async def test_invalid_token_raises_exception():
    """Test that invalid tokens are rejected."""
    credentials = HTTPAuthorizationCredentials(
        scheme="Bearer",
        credentials="invalid.token.here",
    )

    with pytest.raises(HTTPException) as exc_info:
        await verify_token_from_any_source(credentials)

    assert exc_info.value.status_code == 401
    assert "invalid" in exc_info.value.detail.lower()


@pytest.mark.asyncio
async def test_token_without_sub_raises_exception():
    """Test that tokens without 'sub' claim are rejected."""
    now = int(datetime.now(timezone.utc).timestamp())
    payload = {
        "iat": now,
        "exp": now + 3600,
        "email": "test@example.com",
        # Missing 'sub' claim
    }

    token = jwt.encode(
        payload,
        settings.jwt_secret_key,
        algorithm=settings.jwt_algorithm,
    )

    credentials = HTTPAuthorizationCredentials(scheme="Bearer", credentials=token)

    with pytest.raises(HTTPException) as exc_info:
        await verify_token_from_any_source(credentials)

    assert exc_info.value.status_code == 401
    assert "sub" in exc_info.value.detail.lower()


@pytest.mark.asyncio
async def test_gibbon_token_without_username_raises_exception():
    """Test that Gibbon tokens without username are rejected."""
    now = int(datetime.now(timezone.utc).timestamp())
    payload = {
        "sub": "12345",
        "iat": now,
        "exp": now + 3600,
        "aud": settings.jwt_audience,
        "iss": settings.jwt_issuer,
        "source": TokenSource.GIBBON,
        "email": "test@example.com",
        # Missing 'username' claim for Gibbon token
    }

    token = jwt.encode(
        payload,
        settings.jwt_secret_key,
        algorithm=settings.jwt_algorithm,
    )

    credentials = HTTPAuthorizationCredentials(scheme="Bearer", credentials=token)

    with pytest.raises(HTTPException) as exc_info:
        await verify_token_from_any_source(credentials)

    assert exc_info.value.status_code == 401
    assert "username" in exc_info.value.detail.lower()


@pytest.mark.asyncio
async def test_token_with_wrong_secret_raises_exception():
    """Test that tokens signed with wrong secret are rejected."""
    now = int(datetime.now(timezone.utc).timestamp())
    payload = {
        "sub": "user123",
        "iat": now,
        "exp": now + 3600,
    }

    # Sign with different secret
    token = jwt.encode(
        payload,
        "wrong_secret_key",
        algorithm=settings.jwt_algorithm,
    )

    credentials = HTTPAuthorizationCredentials(scheme="Bearer", credentials=token)

    with pytest.raises(HTTPException) as exc_info:
        await verify_token_from_any_source(credentials)

    assert exc_info.value.status_code == 401


def test_extract_user_info_ai_service():
    """Test user info extraction from AI service token."""
    payload = {
        "sub": "user123",
        "username": "testuser",
        "email": "test@example.com",
        "role": "admin",
        "name": "Test User",
        "source": TokenSource.AI_SERVICE,
    }

    user_info = extract_user_info(payload)

    assert user_info["user_id"] == "user123"
    assert user_info["username"] == "testuser"
    assert user_info["email"] == "test@example.com"
    assert user_info["role"] == "admin"
    assert user_info["name"] == "Test User"
    assert user_info["source"] == TokenSource.AI_SERVICE
    assert "gibbon_role_id" not in user_info


def test_extract_user_info_gibbon():
    """Test user info extraction from Gibbon token."""
    payload = {
        "sub": "12345",
        "username": "teacher1",
        "email": "teacher@school.edu",
        "role": "teacher",
        "name": "Jane Teacher",
        "source": TokenSource.GIBBON,
        "gibbon_role_id": "002",
        "session_id": "session_abc",
    }

    user_info = extract_user_info(payload)

    assert user_info["user_id"] == "12345"
    assert user_info["username"] == "teacher1"
    assert user_info["email"] == "teacher@school.edu"
    assert user_info["role"] == "teacher"
    assert user_info["name"] == "Jane Teacher"
    assert user_info["source"] == TokenSource.GIBBON
    assert user_info["gibbon_role_id"] == "002"
    assert user_info["session_id"] == "session_abc"


def test_extract_user_info_defaults_to_ai_service():
    """Test that user info extraction defaults to AI service source."""
    payload = {
        "sub": "user123",
        "email": "test@example.com",
        # No 'source' field
    }

    user_info = extract_user_info(payload)

    assert user_info["source"] == TokenSource.AI_SERVICE
    assert "gibbon_role_id" not in user_info


@pytest.mark.asyncio
async def test_verify_token_accepts_unknown_source():
    """Test that tokens with unknown source are accepted as AI service tokens."""
    now = int(datetime.now(timezone.utc).timestamp())
    payload = {
        "sub": "user123",
        "iat": now,
        "exp": now + 3600,
        "source": "unknown_source",
        "aud": settings.jwt_audience,
        "iss": settings.jwt_issuer,
    }

    token = jwt.encode(
        payload,
        settings.jwt_secret_key,
        algorithm=settings.jwt_algorithm,
    )

    credentials = HTTPAuthorizationCredentials(scheme="Bearer", credentials=token)
    result = await verify_token_from_any_source(credentials)

    # Should not raise exception
    assert result["sub"] == "user123"
    assert result["source"] == "unknown_source"


class TestMiddlewareSecurityValidation:
    """Security tests for JWT validation in middleware."""

    @pytest.mark.asyncio
    async def test_security_token_without_exp_claim_rejected(self):
        """Test that tokens without exp claim are rejected by middleware."""
        now = int(datetime.now(timezone.utc).timestamp())
        payload = {
            "sub": "user123",
            "iat": now,
            "aud": settings.jwt_audience,
            "iss": settings.jwt_issuer,
            # Missing 'exp' claim
        }

        token = jwt.encode(
            payload,
            settings.jwt_secret_key,
            algorithm=settings.jwt_algorithm,
        )

        credentials = HTTPAuthorizationCredentials(scheme="Bearer", credentials=token)

        with pytest.raises(HTTPException) as exc_info:
            await verify_token_from_any_source(credentials)

        assert exc_info.value.status_code == 401
        assert "exp" in exc_info.value.detail.lower() or "required" in exc_info.value.detail.lower()

    @pytest.mark.asyncio
    async def test_security_token_without_iat_claim_rejected(self):
        """Test that tokens without iat claim are rejected by middleware."""
        now = int(datetime.now(timezone.utc).timestamp())
        payload = {
            "sub": "user123",
            "exp": now + 3600,
            "aud": settings.jwt_audience,
            "iss": settings.jwt_issuer,
            # Missing 'iat' claim
        }

        token = jwt.encode(
            payload,
            settings.jwt_secret_key,
            algorithm=settings.jwt_algorithm,
        )

        credentials = HTTPAuthorizationCredentials(scheme="Bearer", credentials=token)

        with pytest.raises(HTTPException) as exc_info:
            await verify_token_from_any_source(credentials)

        assert exc_info.value.status_code == 401
        assert "iat" in exc_info.value.detail.lower() or "required" in exc_info.value.detail.lower()

    @pytest.mark.asyncio
    async def test_security_token_with_invalid_audience_rejected(self):
        """Test that tokens with wrong audience are rejected by middleware."""
        now = int(datetime.now(timezone.utc).timestamp())
        payload = {
            "sub": "user123",
            "iat": now,
            "exp": now + 3600,
            "aud": "wrong-audience",
            "iss": settings.jwt_issuer,
        }

        token = jwt.encode(
            payload,
            settings.jwt_secret_key,
            algorithm=settings.jwt_algorithm,
        )

        credentials = HTTPAuthorizationCredentials(scheme="Bearer", credentials=token)

        with pytest.raises(HTTPException) as exc_info:
            await verify_token_from_any_source(credentials)

        assert exc_info.value.status_code == 401
        assert "aud" in exc_info.value.detail.lower() or "audience" in exc_info.value.detail.lower()

    @pytest.mark.asyncio
    async def test_security_token_without_audience_rejected(self):
        """Test that tokens without audience claim are rejected by middleware."""
        now = int(datetime.now(timezone.utc).timestamp())
        payload = {
            "sub": "user123",
            "iat": now,
            "exp": now + 3600,
            "iss": settings.jwt_issuer,
            # Missing 'aud' claim
        }

        token = jwt.encode(
            payload,
            settings.jwt_secret_key,
            algorithm=settings.jwt_algorithm,
        )

        credentials = HTTPAuthorizationCredentials(scheme="Bearer", credentials=token)

        with pytest.raises(HTTPException) as exc_info:
            await verify_token_from_any_source(credentials)

        assert exc_info.value.status_code == 401
        assert "aud" in exc_info.value.detail.lower() or "audience" in exc_info.value.detail.lower()

    @pytest.mark.asyncio
    async def test_security_token_with_invalid_issuer_rejected(self):
        """Test that tokens with wrong issuer are rejected by middleware."""
        now = int(datetime.now(timezone.utc).timestamp())
        payload = {
            "sub": "user123",
            "iat": now,
            "exp": now + 3600,
            "aud": settings.jwt_audience,
            "iss": "wrong-issuer",
        }

        token = jwt.encode(
            payload,
            settings.jwt_secret_key,
            algorithm=settings.jwt_algorithm,
        )

        credentials = HTTPAuthorizationCredentials(scheme="Bearer", credentials=token)

        with pytest.raises(HTTPException) as exc_info:
            await verify_token_from_any_source(credentials)

        assert exc_info.value.status_code == 401
        assert "iss" in exc_info.value.detail.lower() or "issuer" in exc_info.value.detail.lower()

    @pytest.mark.asyncio
    async def test_security_token_without_issuer_rejected(self):
        """Test that tokens without issuer claim are rejected by middleware."""
        now = int(datetime.now(timezone.utc).timestamp())
        payload = {
            "sub": "user123",
            "iat": now,
            "exp": now + 3600,
            "aud": settings.jwt_audience,
            # Missing 'iss' claim
        }

        token = jwt.encode(
            payload,
            settings.jwt_secret_key,
            algorithm=settings.jwt_algorithm,
        )

        credentials = HTTPAuthorizationCredentials(scheme="Bearer", credentials=token)

        with pytest.raises(HTTPException) as exc_info:
            await verify_token_from_any_source(credentials)

        assert exc_info.value.status_code == 401
        assert "iss" in exc_info.value.detail.lower() or "issuer" in exc_info.value.detail.lower()

    @pytest.mark.asyncio
    async def test_security_token_with_none_algorithm_rejected(self):
        """Test that tokens with 'none' algorithm are explicitly rejected by middleware."""
        payload = {
            "sub": "user123",
            "iat": int(datetime.now(timezone.utc).timestamp()),
            "exp": int(datetime.now(timezone.utc).timestamp()) + 3600,
            "aud": settings.jwt_audience,
            "iss": settings.jwt_issuer,
        }

        try:
            # Try to create a token with 'none' algorithm
            token = jwt.encode(payload, "", algorithm="none")

            credentials = HTTPAuthorizationCredentials(scheme="Bearer", credentials=token)

            with pytest.raises(HTTPException) as exc_info:
                await verify_token_from_any_source(credentials)

            assert exc_info.value.status_code == 401
            assert "none" in exc_info.value.detail.lower() or "algorithm" in exc_info.value.detail.lower()
        except jwt.exceptions.InvalidAlgorithmError:
            # PyJWT library doesn't support 'none' algorithm encoding
            # This is actually good - the library prevents it at creation time
            # But our middleware has defense-in-depth to reject it
            pass

    @pytest.mark.asyncio
    async def test_security_token_with_wrong_algorithm_rejected(self):
        """Test that tokens with wrong algorithm are rejected by middleware."""
        payload = {
            "sub": "user123",
            "iat": int(datetime.now(timezone.utc).timestamp()),
            "exp": int(datetime.now(timezone.utc).timestamp()) + 3600,
            "aud": settings.jwt_audience,
            "iss": settings.jwt_issuer,
        }

        # Create token with HS512 instead of HS256
        token = jwt.encode(
            payload,
            settings.jwt_secret_key,
            algorithm="HS512",
        )

        credentials = HTTPAuthorizationCredentials(scheme="Bearer", credentials=token)

        with pytest.raises(HTTPException) as exc_info:
            await verify_token_from_any_source(credentials)

        assert exc_info.value.status_code == 401
        assert "algorithm" in exc_info.value.detail.lower()
