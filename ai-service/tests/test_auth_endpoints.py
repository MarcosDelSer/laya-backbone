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


@pytest_asyncio.fixture
async def admin_user(db_session: AsyncSession) -> User:
    """Create an admin test user for role-based access tests.

    Creates an active admin user for testing role-based access control.

    Args:
        db_session: Test database session

    Returns:
        User: Created admin user with credentials:
            - email: admin@example.com
            - password: AdminPassword123!
            - role: admin
            - is_active: True
    """
    user = User(
        id=uuid4(),
        email="admin@example.com",
        password_hash=hash_password("AdminPassword123!"),
        first_name="Admin",
        last_name="User",
        role=UserRole.ADMIN,
        is_active=True,
    )
    db_session.add(user)
    await db_session.commit()
    await db_session.refresh(user)
    return user


@pytest_asyncio.fixture
async def accountant_user(db_session: AsyncSession) -> User:
    """Create an accountant test user for role-based access tests.

    Creates an active accountant user for testing role-based access control.

    Args:
        db_session: Test database session

    Returns:
        User: Created accountant user with credentials:
            - email: accountant@example.com
            - password: AccountantPassword123!
            - role: accountant
            - is_active: True
    """
    user = User(
        id=uuid4(),
        email="accountant@example.com",
        password_hash=hash_password("AccountantPassword123!"),
        first_name="Accountant",
        last_name="User",
        role=UserRole.ACCOUNTANT,
        is_active=True,
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


# ============================================================================
# Logout endpoint tests
# ============================================================================


@pytest.mark.asyncio
async def test_logout_success(client: AsyncClient, test_user: User) -> None:
    """Test successful logout with valid tokens.

    Verifies that logging out with valid access and refresh tokens returns:
    - HTTP 200 status code
    - Success message
    - tokens_invalidated count (should be 2 when both tokens provided)

    The tokens are added to a blacklist and cannot be reused after logout.
    """
    # First login to get valid tokens
    login_response = await client.post(
        "/api/v1/auth/login",
        json={
            "email": "teacher@example.com",
            "password": "TestPassword123!",
        },
    )

    assert login_response.status_code == 200
    login_data = login_response.json()
    access_token = login_data["access_token"]
    refresh_token = login_data["refresh_token"]

    # Logout with both tokens
    response = await client.post(
        "/api/v1/auth/logout",
        json={
            "access_token": access_token,
            "refresh_token": refresh_token,
        },
    )

    assert response.status_code == 200
    data = response.json()

    # Verify response structure
    assert "message" in data
    assert "tokens_invalidated" in data

    # Verify response values
    assert data["message"] == "Successfully logged out"
    assert data["tokens_invalidated"] == 2


@pytest.mark.asyncio
async def test_logout_blacklisted_token_rejected(
    client: AsyncClient, test_user: User, db_session: AsyncSession
) -> None:
    """Test that tokens are properly blacklisted and rejected after logout.

    Verifies the complete blacklist enforcement flow:
    1. User logs in and gets valid tokens
    2. Token works to access protected endpoint BEFORE logout (200)
    3. User logs out, tokens are blacklisted (200, tokens_invalidated: 2)
    4. Token is REJECTED when trying to access protected endpoint AFTER logout (401)
    5. Error message indicates token has been revoked

    This ensures that logged-out tokens cannot be used for any operation,
    providing true logout functionality and preventing token reuse attacks.
    """
    # First login to get valid tokens
    login_response = await client.post(
        "/api/v1/auth/login",
        json={
            "email": "teacher@example.com",
            "password": "TestPassword123!",
        },
    )

    assert login_response.status_code == 200
    login_data = login_response.json()
    access_token = login_data["access_token"]
    refresh_token = login_data["refresh_token"]

    # Verify token works BEFORE logout
    protected_response = await client.get(
        "/protected",
        headers={"Authorization": f"Bearer {access_token}"},
    )
    assert protected_response.status_code == 200, "Token should work before logout"
    protected_data = protected_response.json()
    assert "user_id" in protected_data

    # Logout with both tokens
    logout_response = await client.post(
        "/api/v1/auth/logout",
        json={
            "access_token": access_token,
            "refresh_token": refresh_token,
        },
    )

    assert logout_response.status_code == 200
    logout_data = logout_response.json()
    assert logout_data["tokens_invalidated"] == 2

    # Verify that the tokens were added to the blacklist by checking the database
    from sqlalchemy import text

    # Use raw SQL for SQLite compatibility - check access token
    result = await db_session.execute(
        text("SELECT COUNT(*) as count FROM token_blacklist WHERE token = :token"),
        {"token": access_token}
    )
    count_row = result.fetchone()
    assert count_row[0] == 1, "Access token should be in blacklist"

    # Check refresh token is also blacklisted
    result = await db_session.execute(
        text("SELECT COUNT(*) as count FROM token_blacklist WHERE token = :token"),
        {"token": refresh_token}
    )
    count_row = result.fetchone()
    assert count_row[0] == 1, "Refresh token should be in blacklist"

    # CRITICAL: Verify token is REJECTED after logout (blacklist enforcement)
    rejected_response = await client.get(
        "/protected",
        headers={"Authorization": f"Bearer {access_token}"},
    )
    assert rejected_response.status_code == 401, "Blacklisted token must be rejected with 401"
    rejected_data = rejected_response.json()
    assert "detail" in rejected_data
    assert "revoked" in rejected_data["detail"].lower(), f"Error should mention token revocation, got: {rejected_data['detail']}"


# ============================================================================
# Password reset endpoint tests
# ============================================================================


@pytest.mark.asyncio
async def test_password_reset_request(client: AsyncClient, test_user: User) -> None:
    """Test password reset request endpoint.

    Verifies that requesting a password reset always returns:
    - HTTP 200 status code
    - Success message
    - Masked email address

    Note: For security reasons, this endpoint always returns success
    even if the email doesn't exist (prevents email enumeration attacks).
    """
    response = await client.post(
        "/api/v1/auth/password-reset/request",
        json={
            "email": "teacher@example.com",
        },
    )

    assert response.status_code == 200
    data = response.json()

    # Verify response structure
    assert "message" in data
    assert "email" in data

    # Verify response values
    assert isinstance(data["message"], str)
    assert len(data["message"]) > 0
    assert isinstance(data["email"], str)
    # Email should be masked (e.g., "t***@example.com")
    assert "***" in data["email"]


@pytest.mark.asyncio
async def test_password_reset_request_nonexistent_email(client: AsyncClient) -> None:
    """Test password reset request with non-existent email.

    Verifies that requesting a password reset for a non-existent email
    still returns success (HTTP 200) to prevent email enumeration attacks.
    """
    response = await client.post(
        "/api/v1/auth/password-reset/request",
        json={
            "email": "nonexistent@example.com",
        },
    )

    assert response.status_code == 200
    data = response.json()

    # Should still return success message
    assert "message" in data
    assert "email" in data


@pytest.mark.asyncio
async def test_password_reset_confirm_valid(
    client: AsyncClient, test_user: User, db_session: AsyncSession
) -> None:
    """Test password reset confirmation with valid token.

    Verifies that confirming a password reset with a valid token returns:
    - HTTP 200 status code
    - Success message

    Also verifies that:
    - The user's password is actually changed
    - The user can log in with the new password
    - The reset token is marked as used
    """
    # First, request a password reset
    reset_response = await client.post(
        "/api/v1/auth/password-reset/request",
        json={
            "email": "teacher@example.com",
        },
    )
    assert reset_response.status_code == 200

    # Get the reset token from the database
    from app.auth.models import PasswordResetToken

    stmt = select(PasswordResetToken).where(
        PasswordResetToken.user_id == test_user.id,
        PasswordResetToken.is_used == False,
    )
    result = await db_session.execute(stmt)
    reset_token_record = result.scalar_one_or_none()

    assert reset_token_record is not None, "Reset token should be created"
    reset_token = reset_token_record.token

    # Confirm password reset with new password
    new_password = "NewSecurePassword123!"
    confirm_response = await client.post(
        "/api/v1/auth/password-reset/confirm",
        json={
            "token": reset_token,
            "new_password": new_password,
        },
    )

    assert confirm_response.status_code == 200
    data = confirm_response.json()

    # Verify response structure and values
    assert "message" in data
    assert data["message"] == "Password has been successfully reset"

    # Verify token is marked as used
    await db_session.refresh(reset_token_record)
    assert reset_token_record.is_used is True

    # Verify user can login with new password
    login_response = await client.post(
        "/api/v1/auth/login",
        json={
            "email": "teacher@example.com",
            "password": new_password,
        },
    )
    assert login_response.status_code == 200
    assert "access_token" in login_response.json()


@pytest.mark.asyncio
async def test_password_reset_confirm_invalid_token(client: AsyncClient) -> None:
    """Test password reset confirmation with invalid token.

    Verifies that attempting to confirm a password reset with an invalid
    or non-existent token returns:
    - HTTP 400 Bad Request status code
    - Error message indicating invalid token
    """
    response = await client.post(
        "/api/v1/auth/password-reset/confirm",
        json={
            "token": "invalid_token_that_does_not_exist",
            "new_password": "NewPassword123!",
        },
    )

    assert response.status_code == 400
    data = response.json()

    # Verify error message
    assert "detail" in data
    assert "Invalid or expired reset token" in data["detail"]


@pytest.mark.asyncio
async def test_password_reset_confirm_expired_token(
    client: AsyncClient, test_user: User, db_session: AsyncSession
) -> None:
    """Test password reset confirmation with expired token.

    Verifies that attempting to confirm a password reset with an expired
    token returns:
    - HTTP 400 Bad Request status code
    - Error message indicating token expiration
    """
    from datetime import timedelta
    from app.auth.models import PasswordResetToken
    import secrets

    # Create an expired reset token (expired 1 hour ago)
    # Note: Using naive datetime for SQLite compatibility
    expired_token = PasswordResetToken(
        token=secrets.token_urlsafe(32),
        user_id=test_user.id,
        email=test_user.email,
        is_used=False,
        expires_at=datetime.now() - timedelta(hours=1),
    )
    db_session.add(expired_token)
    await db_session.commit()

    # Attempt to confirm password reset with expired token
    response = await client.post(
        "/api/v1/auth/password-reset/confirm",
        json={
            "token": expired_token.token,
            "new_password": "NewPassword123!",
        },
    )

    assert response.status_code == 400
    data = response.json()

    # Verify error message
    assert "detail" in data
    assert "Reset token has expired" in data["detail"]


@pytest.mark.asyncio
async def test_password_reset_confirm_used_token(
    client: AsyncClient, test_user: User, db_session: AsyncSession
) -> None:
    """Test password reset confirmation with already used token.

    Verifies that attempting to confirm a password reset with a token
    that has already been used returns:
    - HTTP 400 Bad Request status code
    - Error message indicating token was already used

    This prevents token reuse attacks where an attacker could intercept
    a reset token and use it multiple times.
    """
    from datetime import timedelta
    from app.auth.models import PasswordResetToken
    import secrets

    # Create a reset token that's already been used
    # Note: Using naive datetime for SQLite compatibility
    used_token = PasswordResetToken(
        token=secrets.token_urlsafe(32),
        user_id=test_user.id,
        email=test_user.email,
        is_used=True,  # Already used
        expires_at=datetime.now() + timedelta(hours=1),
    )
    db_session.add(used_token)
    await db_session.commit()

    # Attempt to confirm password reset with used token
    response = await client.post(
        "/api/v1/auth/password-reset/confirm",
        json={
            "token": used_token.token,
            "new_password": "NewPassword123!",
        },
    )

    assert response.status_code == 400
    data = response.json()

    # Verify error message
    assert "detail" in data
    assert "Reset token has already been used" in data["detail"]


# ============================================================================
# Password security tests
# ============================================================================


@pytest.mark.asyncio
async def test_password_not_stored_plaintext(
    db_session: AsyncSession,
) -> None:
    """Test that passwords are never stored in plaintext.

    Verifies critical password security requirements:
    1. Passwords are hashed using bcrypt (hash starts with $2b$)
    2. Plain text passwords are never stored in the database
    3. verify_password correctly validates hashed passwords

    This ensures compliance with security best practices and prevents
    credential exposure in case of database breach.
    """
    from app.core.security import verify_password

    # Create a user with a known plain password
    plain_password = "SecureTestPassword123!"
    user = User(
        id=uuid4(),
        email="security_test@example.com",
        password_hash=hash_password(plain_password),
        first_name="Security",
        last_name="Test",
        role=UserRole.TEACHER,
        is_active=True,
    )
    db_session.add(user)
    await db_session.commit()
    await db_session.refresh(user)

    # Verify password is hashed with bcrypt (starts with $2b$)
    assert user.password_hash.startswith("$2b$"), (
        "Password must be hashed with bcrypt (hash should start with $2b$)"
    )

    # Verify plain password is NOT stored in database
    assert user.password_hash != plain_password, (
        "Plain text password must never be stored in database"
    )

    # Verify the hash is significantly different from plain password
    # (bcrypt hashes are typically 60 characters long)
    assert len(user.password_hash) > 50, (
        "Bcrypt hash should be much longer than plain password"
    )

    # Verify verify_password works correctly with correct password
    assert verify_password(plain_password, user.password_hash) is True, (
        "verify_password should return True for correct password"
    )

    # Verify verify_password rejects incorrect password
    assert verify_password("WrongPassword123!", user.password_hash) is False, (
        "verify_password should return False for incorrect password"
    )

    # Verify verify_password rejects empty password
    assert verify_password("", user.password_hash) is False, (
        "verify_password should return False for empty password"
    )


# ============================================================================
# Integration tests - Complete authentication flow
# ============================================================================


@pytest.mark.asyncio
async def test_integration_login_to_protected_endpoint(
    client: AsyncClient, test_user: User
) -> None:
    """Integration test for complete authentication flow.

    This test verifies the entire authentication workflow from login to
    accessing protected endpoints with various token scenarios:

    1. Login with valid credentials → receive tokens
    2. Access protected endpoint with valid Bearer token → 200 OK
    3. Access protected endpoint without token → 401 Unauthorized
    4. Access protected endpoint with invalid token → 401 Unauthorized
    5. Access protected endpoint with expired token → 401 Unauthorized

    This ensures end-to-end JWT authentication works correctly.
    """
    # Step 1: Login to get access token
    login_response = await client.post(
        "/api/v1/auth/login",
        json={
            "email": "teacher@example.com",
            "password": "TestPassword123!",
        },
    )

    assert login_response.status_code == 200
    login_data = login_response.json()
    assert "access_token" in login_data
    access_token = login_data["access_token"]

    # Step 2: Access protected endpoint with valid Bearer token → 200
    protected_response = await client.get(
        "/protected",
        headers={"Authorization": f"Bearer {access_token}"},
    )

    assert protected_response.status_code == 200
    protected_data = protected_response.json()

    # Verify protected endpoint response structure
    assert "message" in protected_data
    assert "user" in protected_data
    assert "token_data" in protected_data

    # Verify response values
    assert protected_data["message"] == "Access granted"
    assert protected_data["user"] == str(test_user.id)

    # Verify token data contains expected fields
    token_data = protected_data["token_data"]
    assert "sub" in token_data
    assert "email" in token_data
    assert "role" in token_data
    assert token_data["sub"] == str(test_user.id)
    assert token_data["email"] == test_user.email
    assert token_data["role"] == test_user.role.value

    # Step 3: Access protected endpoint without token → 401
    no_token_response = await client.get("/protected")

    assert no_token_response.status_code == 401
    no_token_data = no_token_response.json()
    assert "detail" in no_token_data
    assert "Not authenticated" in no_token_data["detail"]

    # Step 4: Access protected endpoint with invalid token → 401
    invalid_token_response = await client.get(
        "/protected",
        headers={"Authorization": "Bearer invalid.token.here"},
    )

    assert invalid_token_response.status_code == 401
    invalid_token_data = invalid_token_response.json()
    assert "detail" in invalid_token_data
    assert "Invalid authentication token" in invalid_token_data["detail"]

    # Step 5: Access protected endpoint with expired token → 401
    # Create an expired access token (expired 1 hour ago)
    expired_token = create_token(
        subject=str(test_user.id),
        expires_delta_seconds=-3600,  # Negative means expired
        additional_claims={
            "email": test_user.email,
            "role": test_user.role.value,
            "type": "access",
        },
    )

    expired_token_response = await client.get(
        "/protected",
        headers={"Authorization": f"Bearer {expired_token}"},
    )

    assert expired_token_response.status_code == 401
    expired_token_data = expired_token_response.json()
    assert "detail" in expired_token_data
    assert "Token has expired" in expired_token_data["detail"]


# ============================================================================
# Role-based access control tests
# ============================================================================


@pytest.mark.asyncio
async def test_admin_endpoint_as_admin(client: AsyncClient, admin_user: User) -> None:
    """Test admin endpoint access with admin role.

    Verifies that a user with the ADMIN role can successfully access
    admin-only endpoints.

    Expected: HTTP 200 status code with success message
    """
    # Login as admin to get access token
    login_response = await client.post(
        "/api/v1/auth/login",
        json={
            "email": "admin@example.com",
            "password": "AdminPassword123!",
        },
    )

    assert login_response.status_code == 200
    login_data = login_response.json()
    access_token = login_data["access_token"]

    # Access admin endpoint with admin token
    response = await client.get(
        "/api/v1/auth/admin/test",
        headers={"Authorization": f"Bearer {access_token}"},
    )

    assert response.status_code == 200
    data = response.json()
    assert "message" in data
    assert data["message"] == "Admin access granted"
    assert "user" in data
    assert data["user"] == "admin@example.com"


@pytest.mark.asyncio
async def test_admin_endpoint_as_teacher(client: AsyncClient, test_user: User) -> None:
    """Test admin endpoint access with teacher role.

    Verifies that a user with the TEACHER role cannot access admin-only
    endpoints and receives a 403 Forbidden response.

    Expected: HTTP 403 status code with error message indicating insufficient permissions
    """
    # Login as teacher to get access token
    login_response = await client.post(
        "/api/v1/auth/login",
        json={
            "email": "teacher@example.com",
            "password": "TestPassword123!",
        },
    )

    assert login_response.status_code == 200
    login_data = login_response.json()
    access_token = login_data["access_token"]

    # Attempt to access admin endpoint with teacher token
    response = await client.get(
        "/api/v1/auth/admin/test",
        headers={"Authorization": f"Bearer {access_token}"},
    )

    assert response.status_code == 403
    data = response.json()
    assert "detail" in data
    assert "Access denied" in data["detail"]
    assert "admin" in data["detail"].lower()


@pytest.mark.asyncio
async def test_financial_endpoint_as_admin(client: AsyncClient, admin_user: User) -> None:
    """Test financial endpoint access with admin role.

    Verifies that a user with the ADMIN role can successfully access
    endpoints that require ADMIN or ACCOUNTANT roles.

    Expected: HTTP 200 status code with success message
    """
    # Login as admin to get access token
    login_response = await client.post(
        "/api/v1/auth/login",
        json={
            "email": "admin@example.com",
            "password": "AdminPassword123!",
        },
    )

    assert login_response.status_code == 200
    login_data = login_response.json()
    access_token = login_data["access_token"]

    # Access financial endpoint with admin token
    response = await client.get(
        "/api/v1/auth/financial/test",
        headers={"Authorization": f"Bearer {access_token}"},
    )

    assert response.status_code == 200
    data = response.json()
    assert "message" in data
    assert data["message"] == "Financial access granted"
    assert "user" in data
    assert data["user"] == "admin@example.com"
    assert "role" in data
    assert data["role"] == "admin"


@pytest.mark.asyncio
async def test_financial_endpoint_as_accountant(
    client: AsyncClient, accountant_user: User
) -> None:
    """Test financial endpoint access with accountant role.

    Verifies that a user with the ACCOUNTANT role can successfully access
    endpoints that require ADMIN or ACCOUNTANT roles.

    Expected: HTTP 200 status code with success message
    """
    # Login as accountant to get access token
    login_response = await client.post(
        "/api/v1/auth/login",
        json={
            "email": "accountant@example.com",
            "password": "AccountantPassword123!",
        },
    )

    assert login_response.status_code == 200
    login_data = login_response.json()
    access_token = login_data["access_token"]

    # Access financial endpoint with accountant token
    response = await client.get(
        "/api/v1/auth/financial/test",
        headers={"Authorization": f"Bearer {access_token}"},
    )

    assert response.status_code == 200
    data = response.json()
    assert "message" in data
    assert data["message"] == "Financial access granted"
    assert "user" in data
    assert data["user"] == "accountant@example.com"
    assert "role" in data
    assert data["role"] == "accountant"


@pytest.mark.asyncio
async def test_financial_endpoint_as_teacher(
    client: AsyncClient, test_user: User
) -> None:
    """Test financial endpoint access with teacher role.

    Verifies that a user with the TEACHER role cannot access endpoints
    that require ADMIN or ACCOUNTANT roles and receives a 403 Forbidden response.

    Expected: HTTP 403 status code with error message indicating insufficient permissions
    """
    # Login as teacher to get access token
    login_response = await client.post(
        "/api/v1/auth/login",
        json={
            "email": "teacher@example.com",
            "password": "TestPassword123!",
        },
    )

    assert login_response.status_code == 200
    login_data = login_response.json()
    access_token = login_data["access_token"]

    # Attempt to access financial endpoint with teacher token
    response = await client.get(
        "/api/v1/auth/financial/test",
        headers={"Authorization": f"Bearer {access_token}"},
    )

    assert response.status_code == 403
    data = response.json()
    assert "detail" in data
    assert "Access denied" in data["detail"]
    assert ("admin" in data["detail"].lower() or "accountant" in data["detail"].lower())
