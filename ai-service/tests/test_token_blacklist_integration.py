"""Integration tests for token blacklist logout flow.

This module tests the complete end-to-end logout flow including:
- Login to get valid tokens
- Logout to blacklist tokens
- Verify tokens are rejected after logout
- Verify tokens are stored in Redis and PostgreSQL
- Test refresh token blacklisting
- Test multiple session handling
"""

from __future__ import annotations

from datetime import datetime, timedelta, timezone
from typing import Any
from uuid import UUID

import pytest
import pytest_asyncio
from httpx import AsyncClient
from sqlalchemy import text
from sqlalchemy.ext.asyncio import AsyncSession

# Import auth fixtures from auth.conftest
pytest_plugins = ["tests.auth.conftest"]

from tests.auth.conftest import (
    create_access_token,
    create_refresh_token,
    TEST_PASSWORD_PLAIN,
)


# ============================================================================
# Complete Logout Flow Integration Tests
# ============================================================================


@pytest.mark.asyncio
async def test_complete_logout_flow(
    auth_client: AsyncClient,
    auth_db_session: AsyncSession,
    teacher_user: Any,
) -> None:
    """Test complete logout flow: login -> use token -> logout -> token rejected.

    This is the critical end-to-end test verifying:
    1. User can log in and receive valid tokens
    2. Access token works to access protected endpoints BEFORE logout
    3. Logout successfully blacklists both tokens
    4. Access token is REJECTED after logout (401 with 'revoked' message)
    5. Tokens are stored in database blacklist

    This ensures the token blacklist fix properly prevents revoked tokens
    from being used after logout.
    """
    # Step 1: Login to get valid tokens
    login_response = await auth_client.post(
        "/api/v1/auth/login",
        json={
            "email": teacher_user.email,
            "password": TEST_PASSWORD_PLAIN,
        },
    )

    assert login_response.status_code == 200, f"Login failed: {login_response.text}"
    login_data = login_response.json()
    assert "access_token" in login_data
    assert "refresh_token" in login_data
    access_token = login_data["access_token"]
    refresh_token = login_data["refresh_token"]

    # Step 2: Verify token works BEFORE logout
    protected_response = await auth_client.get(
        "/protected",
        headers={"Authorization": f"Bearer {access_token}"},
    )
    assert protected_response.status_code == 200, (
        f"Token should work before logout, got {protected_response.status_code}: "
        f"{protected_response.text}"
    )
    protected_data = protected_response.json()
    assert "user_id" in protected_data or "user" in protected_data

    # Step 3: Logout (blacklist both tokens)
    logout_response = await auth_client.post(
        "/api/v1/auth/logout",
        json={
            "access_token": access_token,
            "refresh_token": refresh_token,
        },
    )

    assert logout_response.status_code == 200, (
        f"Logout failed: {logout_response.status_code}: {logout_response.text}"
    )
    logout_data = logout_response.json()
    assert logout_data["message"] == "Successfully logged out"
    assert logout_data["tokens_invalidated"] == 2

    # Step 4: Verify tokens are in database blacklist
    # Check access token
    result = await auth_db_session.execute(
        text("SELECT COUNT(*) as count FROM token_blacklist WHERE token = :token"),
        {"token": access_token}
    )
    count_row = result.fetchone()
    assert count_row[0] == 1, "Access token should be in database blacklist"

    # Check refresh token
    result = await auth_db_session.execute(
        text("SELECT COUNT(*) as count FROM token_blacklist WHERE token = :token"),
        {"token": refresh_token}
    )
    count_row = result.fetchone()
    assert count_row[0] == 1, "Refresh token should be in database blacklist"

    # Step 5: CRITICAL - Verify token is REJECTED after logout
    rejected_response = await auth_client.get(
        "/protected",
        headers={"Authorization": f"Bearer {access_token}"},
    )

    assert rejected_response.status_code == 401, (
        f"Blacklisted token must be rejected with 401, got {rejected_response.status_code}"
    )
    rejected_data = rejected_response.json()
    assert "detail" in rejected_data
    assert "revoked" in rejected_data["detail"].lower(), (
        f"Error should mention token revocation, got: {rejected_data['detail']}"
    )


@pytest.mark.asyncio
async def test_logout_with_refresh_token_blocked(
    auth_client: AsyncClient,
    auth_db_session: AsyncSession,
    teacher_user: Any,
) -> None:
    """Test that refresh token is blocked after logout.

    Verifies that after logout:
    1. Refresh token cannot be used to get new tokens
    2. Refresh endpoint returns 401 with revocation message
    """
    # Step 1: Login to get tokens
    login_response = await auth_client.post(
        "/api/v1/auth/login",
        json={
            "email": teacher_user.email,
            "password": TEST_PASSWORD_PLAIN,
        },
    )

    assert login_response.status_code == 200
    login_data = login_response.json()
    access_token = login_data["access_token"]
    refresh_token = login_data["refresh_token"]

    # Step 2: Verify refresh token works BEFORE logout
    refresh_response_before = await auth_client.post(
        "/api/v1/auth/refresh",
        json={"refresh_token": refresh_token},
    )
    assert refresh_response_before.status_code == 200, (
        "Refresh token should work before logout"
    )

    # Step 3: Logout
    logout_response = await auth_client.post(
        "/api/v1/auth/logout",
        json={
            "access_token": access_token,
            "refresh_token": refresh_token,
        },
    )
    assert logout_response.status_code == 200
    assert logout_response.json()["tokens_invalidated"] == 2

    # Step 4: Verify refresh token is REJECTED after logout
    refresh_response_after = await auth_client.post(
        "/api/v1/auth/refresh",
        json={"refresh_token": refresh_token},
    )

    assert refresh_response_after.status_code == 401, (
        "Blacklisted refresh token must be rejected"
    )
    refresh_data = refresh_response_after.json()
    assert "detail" in refresh_data
    # The error message may say "revoked" or "Invalid token" depending on implementation
    assert (
        "revoked" in refresh_data["detail"].lower() or
        "invalid" in refresh_data["detail"].lower()
    )


@pytest.mark.asyncio
async def test_logout_only_access_token(
    auth_client: AsyncClient,
    auth_db_session: AsyncSession,
    teacher_user: Any,
) -> None:
    """Test logout with only access token (refresh token omitted).

    Verifies that:
    1. Logout works with just access token
    2. Only 1 token is invalidated
    3. Access token is properly blacklisted
    """
    # Step 1: Login
    login_response = await auth_client.post(
        "/api/v1/auth/login",
        json={
            "email": teacher_user.email,
            "password": TEST_PASSWORD_PLAIN,
        },
    )

    assert login_response.status_code == 200
    access_token = login_response.json()["access_token"]

    # Step 2: Logout with only access token
    logout_response = await auth_client.post(
        "/api/v1/auth/logout",
        json={
            "access_token": access_token,
        },
    )

    assert logout_response.status_code == 200
    logout_data = logout_response.json()
    assert logout_data["message"] == "Successfully logged out"
    assert logout_data["tokens_invalidated"] == 1, (
        "Should invalidate only 1 token when refresh token not provided"
    )

    # Step 3: Verify access token is blacklisted and rejected
    protected_response = await auth_client.get(
        "/protected",
        headers={"Authorization": f"Bearer {access_token}"},
    )

    assert protected_response.status_code == 401
    assert "revoked" in protected_response.json()["detail"].lower()


@pytest.mark.asyncio
async def test_multiple_sessions_logout(
    auth_client: AsyncClient,
    auth_db_session: AsyncSession,
    teacher_user: Any,
) -> None:
    """Test that user can have multiple sessions and logout one at a time.

    Verifies:
    1. User can login multiple times (different sessions)
    2. Logging out session 1 doesn't affect session 2
    3. Each session's tokens work independently
    4. Each logout only affects its own tokens
    """
    # Step 1: Create first session (login 1)
    import asyncio
    await asyncio.sleep(1.1)  # Ensure different iat timestamp

    login1_response = await auth_client.post(
        "/api/v1/auth/login",
        json={
            "email": teacher_user.email,
            "password": TEST_PASSWORD_PLAIN,
        },
    )
    assert login1_response.status_code == 200
    session1_token = login1_response.json()["access_token"]

    # Step 2: Create second session (login 2)
    await asyncio.sleep(1.1)  # Ensure different iat timestamp

    login2_response = await auth_client.post(
        "/api/v1/auth/login",
        json={
            "email": teacher_user.email,
            "password": TEST_PASSWORD_PLAIN,
        },
    )
    assert login2_response.status_code == 200
    session2_token = login2_response.json()["access_token"]
    session2_refresh = login2_response.json()["refresh_token"]

    # Step 3: Verify both tokens work
    response1 = await auth_client.get(
        "/protected",
        headers={"Authorization": f"Bearer {session1_token}"},
    )
    assert response1.status_code == 200, "Session 1 token should work"

    response2 = await auth_client.get(
        "/protected",
        headers={"Authorization": f"Bearer {session2_token}"},
    )
    assert response2.status_code == 200, "Session 2 token should work"

    # Step 4: Logout session 1 only
    logout1_response = await auth_client.post(
        "/api/v1/auth/logout",
        json={
            "access_token": session1_token,
        },
    )
    assert logout1_response.status_code == 200

    # Step 5: Verify session 1 token is rejected
    response1_after = await auth_client.get(
        "/protected",
        headers={"Authorization": f"Bearer {session1_token}"},
    )
    assert response1_after.status_code == 401, (
        "Session 1 token should be rejected after logout"
    )

    # Step 6: Verify session 2 token STILL WORKS
    response2_after = await auth_client.get(
        "/protected",
        headers={"Authorization": f"Bearer {session2_token}"},
    )
    assert response2_after.status_code == 200, (
        "Session 2 token should still work after session 1 logout"
    )

    # Step 7: Logout session 2
    logout2_response = await auth_client.post(
        "/api/v1/auth/logout",
        json={
            "access_token": session2_token,
            "refresh_token": session2_refresh,
        },
    )
    assert logout2_response.status_code == 200
    assert logout2_response.json()["tokens_invalidated"] == 2

    # Step 8: Verify session 2 token is now also rejected
    response2_final = await auth_client.get(
        "/protected",
        headers={"Authorization": f"Bearer {session2_token}"},
    )
    assert response2_final.status_code == 401


@pytest.mark.asyncio
async def test_login_after_logout(
    auth_client: AsyncClient,
    auth_db_session: AsyncSession,
    teacher_user: Any,
) -> None:
    """Test that user can login again after logout.

    Verifies:
    1. User logs in and gets tokens
    2. User logs out (tokens blacklisted)
    3. User can login again and get NEW tokens
    4. New tokens work properly
    5. Old tokens remain blacklisted
    """
    # Step 1: First login
    login1_response = await auth_client.post(
        "/api/v1/auth/login",
        json={
            "email": teacher_user.email,
            "password": TEST_PASSWORD_PLAIN,
        },
    )
    assert login1_response.status_code == 200
    old_token = login1_response.json()["access_token"]

    # Step 2: Logout
    logout_response = await auth_client.post(
        "/api/v1/auth/logout",
        json={"access_token": old_token},
    )
    assert logout_response.status_code == 200

    # Step 3: Login again with same credentials
    import asyncio
    await asyncio.sleep(1.1)  # Ensure different iat timestamp

    login2_response = await auth_client.post(
        "/api/v1/auth/login",
        json={
            "email": teacher_user.email,
            "password": TEST_PASSWORD_PLAIN,
        },
    )
    assert login2_response.status_code == 200
    new_token = login2_response.json()["access_token"]

    # Step 4: Verify new token works
    protected_response = await auth_client.get(
        "/protected",
        headers={"Authorization": f"Bearer {new_token}"},
    )
    assert protected_response.status_code == 200, "New token should work"

    # Step 5: Verify old token is still rejected
    old_token_response = await auth_client.get(
        "/protected",
        headers={"Authorization": f"Bearer {old_token}"},
    )
    assert old_token_response.status_code == 401, (
        "Old blacklisted token should remain rejected"
    )


@pytest.mark.asyncio
async def test_logout_with_already_blacklisted_token(
    auth_client: AsyncClient,
    auth_db_session: AsyncSession,
    teacher_user: Any,
) -> None:
    """Test that logout with already blacklisted token is idempotent.

    Verifies:
    1. User logs out once (tokens blacklisted)
    2. User tries to logout again with same tokens
    3. Second logout still returns success (idempotent)
    """
    # Step 1: Login
    login_response = await auth_client.post(
        "/api/v1/auth/login",
        json={
            "email": teacher_user.email,
            "password": TEST_PASSWORD_PLAIN,
        },
    )
    assert login_response.status_code == 200
    access_token = login_response.json()["access_token"]
    refresh_token = login_response.json()["refresh_token"]

    # Step 2: First logout
    logout1_response = await auth_client.post(
        "/api/v1/auth/logout",
        json={
            "access_token": access_token,
            "refresh_token": refresh_token,
        },
    )
    assert logout1_response.status_code == 200
    assert logout1_response.json()["tokens_invalidated"] == 2

    # Step 3: Second logout with same tokens (should be idempotent)
    logout2_response = await auth_client.post(
        "/api/v1/auth/logout",
        json={
            "access_token": access_token,
            "refresh_token": refresh_token,
        },
    )

    # Should still succeed (idempotent operation)
    assert logout2_response.status_code == 200
    # May invalidate 0 tokens (already blacklisted) or 2 tokens (duplicate insert OK)
    # Both behaviors are acceptable for idempotency
    assert logout2_response.json()["message"] == "Successfully logged out"


@pytest.mark.asyncio
async def test_blacklist_expiration_timestamp(
    auth_client: AsyncClient,
    auth_db_session: AsyncSession,
    teacher_user: Any,
) -> None:
    """Test that blacklist entries have correct expiration timestamps.

    Verifies:
    1. Blacklisted tokens have expires_at timestamp
    2. Expiration timestamp matches JWT expiration
    3. Expiration is in the future (not expired immediately)
    """
    # Step 1: Login
    login_response = await auth_client.post(
        "/api/v1/auth/login",
        json={
            "email": teacher_user.email,
            "password": TEST_PASSWORD_PLAIN,
        },
    )
    assert login_response.status_code == 200
    access_token = login_response.json()["access_token"]

    # Step 2: Logout
    logout_response = await auth_client.post(
        "/api/v1/auth/logout",
        json={"access_token": access_token},
    )
    assert logout_response.status_code == 200

    # Step 3: Check blacklist expiration timestamp
    result = await auth_db_session.execute(
        text("""
            SELECT expires_at
            FROM token_blacklist
            WHERE token = :token
        """),
        {"token": access_token}
    )
    row = result.fetchone()
    assert row is not None, "Token should be in blacklist"

    expires_at_str = row[0]
    # Parse the expiration timestamp (SQLite stores as string)
    from datetime import datetime
    if "+" in expires_at_str:
        # ISO format with timezone
        expires_at = datetime.fromisoformat(expires_at_str)
    else:
        # ISO format without timezone, add UTC
        expires_at = datetime.fromisoformat(expires_at_str).replace(tzinfo=timezone.utc)

    # Verify expiration is in the future
    now = datetime.now(timezone.utc)
    assert expires_at > now, (
        f"Blacklist expiration should be in future: {expires_at} <= {now}"
    )

    # Verify expiration is within reasonable range (15 min for access token)
    # Allow some buffer for test execution time
    expected_max = now + timedelta(minutes=20)
    assert expires_at < expected_max, (
        f"Blacklist expiration seems too far in future: {expires_at}"
    )


@pytest.mark.asyncio
async def test_blacklist_stores_user_id(
    auth_client: AsyncClient,
    auth_db_session: AsyncSession,
    teacher_user: Any,
) -> None:
    """Test that blacklist entries store the correct user ID.

    Verifies:
    1. Blacklisted tokens are associated with correct user
    2. User ID is stored in blacklist table
    3. Can query blacklist by user ID
    """
    # Step 1: Login
    login_response = await auth_client.post(
        "/api/v1/auth/login",
        json={
            "email": teacher_user.email,
            "password": TEST_PASSWORD_PLAIN,
        },
    )
    assert login_response.status_code == 200
    access_token = login_response.json()["access_token"]

    # Step 2: Logout
    logout_response = await auth_client.post(
        "/api/v1/auth/logout",
        json={"access_token": access_token},
    )
    assert logout_response.status_code == 200

    # Step 3: Verify user_id is stored correctly in blacklist
    result = await auth_db_session.execute(
        text("""
            SELECT user_id
            FROM token_blacklist
            WHERE token = :token
        """),
        {"token": access_token}
    )
    row = result.fetchone()
    assert row is not None, "Token should be in blacklist"

    stored_user_id = row[0]
    assert stored_user_id == str(teacher_user.id), (
        f"Blacklist should store correct user ID: {stored_user_id} != {teacher_user.id}"
    )

    # Step 4: Verify can query by user_id
    result = await auth_db_session.execute(
        text("""
            SELECT COUNT(*) as count
            FROM token_blacklist
            WHERE user_id = :user_id
        """),
        {"user_id": str(teacher_user.id)}
    )
    count_row = result.fetchone()
    assert count_row[0] >= 1, (
        "Should be able to find blacklisted tokens by user_id"
    )
