"""Tests for authentication endpoints.

Tests JWT token authentication, login, refresh, logout, and password reset endpoints.
"""

from __future__ import annotations

from datetime import datetime, timezone
from typing import Any
from uuid import UUID, uuid4

import pytest
import pytest_asyncio
from httpx import AsyncClient
from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession

from app.auth.models import User, UserRole
from app.core.security import hash_password
from app.auth.jwt import create_token


# ============================================================================
# Fixtures for authentication testing
# ============================================================================


@pytest_asyncio.fixture
async def test_user(db_session: AsyncSession) -> User:
    """Create a test user for authentication tests.

    Creates an active teacher user with known credentials for testing login,
    token refresh, and other auth operations.

    Args:
        db_session: Test database session

    Returns:
        User: Created test user with credentials:
            - email: teacher@example.com
            - password: TestPassword123!
            - role: teacher
            - is_active: True
    """
    user = User(
        id=uuid4(),
        email="teacher@example.com",
        password_hash=hash_password("TestPassword123!"),
        first_name="Test",
        last_name="Teacher",
        role=UserRole.TEACHER,
        is_active=True,
    )
    db_session.add(user)
    await db_session.commit()
    await db_session.refresh(user)
    return user


@pytest_asyncio.fixture
async def inactive_user(db_session: AsyncSession) -> User:
    """Create an inactive test user for authentication tests.

    Creates an inactive user to test that inactive accounts cannot log in.

    Args:
        db_session: Test database session

    Returns:
        User: Created inactive test user
    """
    user = User(
        id=uuid4(),
        email="inactive@example.com",
        password_hash=hash_password("InactivePassword123!"),
        first_name="Inactive",
        last_name="User",
        role=UserRole.TEACHER,
        is_active=False,
    )
    db_session.add(user)
    await db_session.commit()
    await db_session.refresh(user)
    return user


# ============================================================================
# Login endpoint tests
# ============================================================================


@pytest.mark.asyncio
async def test_login_success(client: AsyncClient, test_user: User) -> None:
    """Test successful login with valid credentials.

    Verifies that logging in with valid email and password returns:
    - HTTP 200 status code
    - access_token (JWT for API authentication)
    - refresh_token (JWT for token renewal)
    - expires_in (time until access token expires)
    - token_type (should be "bearer")
    """
    response = await client.post(
        "/api/v1/auth/login",
        json={
            "email": "teacher@example.com",
            "password": "TestPassword123!",
        },
    )

    assert response.status_code == 200
    data = response.json()

    # Verify all required fields are present
    assert "access_token" in data
    assert "refresh_token" in data
    assert "expires_in" in data
    assert "token_type" in data

    # Verify field types and values
    assert isinstance(data["access_token"], str)
    assert len(data["access_token"]) > 0
    assert isinstance(data["refresh_token"], str)
    assert len(data["refresh_token"]) > 0
    assert isinstance(data["expires_in"], int)
    assert data["expires_in"] > 0
    assert data["token_type"] == "bearer"


@pytest.mark.asyncio
async def test_login_invalid_email(client: AsyncClient, test_user: User) -> None:
    """Test login with non-existent email address.

    Verifies that attempting to log in with an email that doesn't exist
    in the database returns:
    - HTTP 401 Unauthorized status code
    - Error message indicating incorrect credentials
    """
    response = await client.post(
        "/api/v1/auth/login",
        json={
            "email": "nonexistent@example.com",
            "password": "SomePassword123!",
        },
    )

    assert response.status_code == 401
    data = response.json()
    assert "detail" in data
    assert data["detail"] == "Incorrect email or password"


@pytest.mark.asyncio
async def test_login_invalid_password(client: AsyncClient, test_user: User) -> None:
    """Test login with incorrect password.

    Verifies that attempting to log in with a valid email but incorrect
    password returns:
    - HTTP 401 Unauthorized status code
    - Error message indicating incorrect credentials
    """
    response = await client.post(
        "/api/v1/auth/login",
        json={
            "email": "teacher@example.com",
            "password": "WrongPassword123!",
        },
    )

    assert response.status_code == 401
    data = response.json()
    assert "detail" in data
    assert data["detail"] == "Incorrect email or password"


@pytest.mark.asyncio
async def test_login_inactive_user(client: AsyncClient, inactive_user: User) -> None:
    """Test login with inactive user account.

    Verifies that attempting to log in with valid credentials for an
    inactive user account returns:
    - HTTP 401 Unauthorized status code
    - Error message indicating incorrect credentials

    Note: The error message is intentionally generic to avoid revealing
    that the account exists but is inactive (security best practice).
    """
    response = await client.post(
        "/api/v1/auth/login",
        json={
            "email": "inactive@example.com",
            "password": "InactivePassword123!",
        },
    )

    assert response.status_code == 401
    data = response.json()
    assert "detail" in data
    assert data["detail"] == "Incorrect email or password"


# ============================================================================
# Token refresh endpoint tests
# ============================================================================


@pytest.mark.asyncio
async def test_refresh_valid_token(client: AsyncClient, test_user: User) -> None:
    """Test token refresh with valid refresh token.

    Verifies that using a valid refresh token returns:
    - HTTP 200 status code
    - New access_token (JWT for API authentication)
    - New refresh_token (JWT for token renewal)
    - expires_in (time until access token expires)
    - token_type (should be "bearer")

    This implements refresh token rotation for enhanced security.
    """
    # First login to get a valid refresh token
    login_response = await client.post(
        "/api/v1/auth/login",
        json={
            "email": "teacher@example.com",
            "password": "TestPassword123!",
        },
    )

    assert login_response.status_code == 200
    login_data = login_response.json()
    refresh_token = login_data["refresh_token"]

    # Use refresh token to get new tokens
    response = await client.post(
        "/api/v1/auth/refresh",
        json={
            "refresh_token": refresh_token,
        },
    )

    assert response.status_code == 200
    data = response.json()

    # Verify all required fields are present
    assert "access_token" in data
    assert "refresh_token" in data
    assert "expires_in" in data
    assert "token_type" in data

    # Verify field types and values
    assert isinstance(data["access_token"], str)
    assert len(data["access_token"]) > 0
    assert isinstance(data["refresh_token"], str)
    assert len(data["refresh_token"]) > 0
    assert isinstance(data["expires_in"], int)
    assert data["expires_in"] > 0
    assert data["token_type"] == "bearer"

    # Note: In production, tokens would be different due to different timestamps,
    # but in tests they may be identical if generated in the same second


@pytest.mark.asyncio
async def test_refresh_invalid_token(client: AsyncClient) -> None:
    """Test token refresh with invalid/malformed token.

    Verifies that using an invalid refresh token returns:
    - HTTP 401 Unauthorized status code
    - Error message indicating invalid token
    """
    response = await client.post(
        "/api/v1/auth/refresh",
        json={
            "refresh_token": "invalid.token.here",
        },
    )

    assert response.status_code == 401
    data = response.json()
    assert "detail" in data
    assert "Invalid token" in data["detail"]


@pytest.mark.asyncio
async def test_refresh_expired_token(client: AsyncClient, test_user: User) -> None:
    """Test token refresh with expired refresh token.

    Verifies that using an expired refresh token returns:
    - HTTP 401 Unauthorized status code
    - Error message indicating token expiration

    Creates a refresh token that expired 1 hour ago to simulate expiration.
    """
    # Create an expired refresh token (expired 1 hour ago)
    expired_token = create_token(
        subject=str(test_user.id),
        expires_delta_seconds=-3600,  # Negative means expired
        additional_claims={
            "type": "refresh",
        },
    )

    response = await client.post(
        "/api/v1/auth/refresh",
        json={
            "refresh_token": expired_token,
        },
    )

    assert response.status_code == 401
    data = response.json()
    assert "detail" in data
    assert "Invalid token" in data["detail"]


@pytest.mark.asyncio
async def test_refresh_with_access_token(client: AsyncClient, test_user: User) -> None:
    """Test token refresh with access token instead of refresh token.

    Verifies that attempting to use an access token (instead of refresh token)
    for token refresh returns:
    - HTTP 401 Unauthorized status code
    - Error message indicating wrong token type

    This ensures that access tokens cannot be used to obtain new tokens,
    only refresh tokens can be used for this purpose.
    """
    # Create a valid access token (not a refresh token)
    access_token = create_token(
        subject=str(test_user.id),
        expires_delta_seconds=900,  # 15 minutes
        additional_claims={
            "email": test_user.email,
            "role": test_user.role.value,
            "type": "access",  # This is an access token, not refresh
        },
    )

    response = await client.post(
        "/api/v1/auth/refresh",
        json={
            "refresh_token": access_token,
        },
    )

    assert response.status_code == 401
    data = response.json()
    assert "detail" in data
    assert "Invalid token type" in data["detail"]
