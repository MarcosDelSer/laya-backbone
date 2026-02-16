"""Pytest fixtures for authentication tests in LAYA AI Service.

Provides reusable fixtures for testing JWT tokens, user authentication,
role-based access control, and password reset functionality.
"""

from __future__ import annotations

from datetime import datetime, timedelta, timezone
from typing import Any, AsyncGenerator, Dict, Optional
from uuid import UUID, uuid4

import jwt
import pytest
import pytest_asyncio
from fastapi import FastAPI
from httpx import ASGITransport, AsyncClient
from sqlalchemy import StaticPool, text
from sqlalchemy.ext.asyncio import AsyncSession, create_async_engine
from sqlalchemy.orm import sessionmaker

from app.config import settings
from app.auth.models import UserRole


# Test database URL using SQLite for isolation
TEST_DATABASE_URL = "sqlite+aiosqlite:///:memory:"

# Create async engine for testing with in-memory SQLite
test_engine = create_async_engine(
    TEST_DATABASE_URL,
    echo=False,
    connect_args={"check_same_thread": False},
    poolclass=StaticPool,
)

# Session factory for test database sessions
TestAsyncSessionLocal = sessionmaker(
    test_engine,
    class_=AsyncSession,
    expire_on_commit=False,
    autocommit=False,
    autoflush=False,
)


# SQLite-compatible auth tables (PostgreSQL ARRAY and ENUM not supported)
SQLITE_CREATE_AUTH_TABLES_SQL = """
CREATE TABLE IF NOT EXISTS users (
    id TEXT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    role VARCHAR(20) NOT NULL,
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS token_blacklist (
    id TEXT PRIMARY KEY,
    token VARCHAR(500) NOT NULL UNIQUE,
    user_id TEXT NOT NULL,
    blacklisted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL
);

CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id TEXT PRIMARY KEY,
    token VARCHAR(500) NOT NULL UNIQUE,
    user_id TEXT NOT NULL,
    email VARCHAR(255) NOT NULL,
    is_used INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);
CREATE INDEX IF NOT EXISTS idx_users_role ON users(role);
CREATE INDEX IF NOT EXISTS idx_users_active ON users(is_active);
CREATE INDEX IF NOT EXISTS idx_token_blacklist_token ON token_blacklist(token);
CREATE INDEX IF NOT EXISTS idx_token_blacklist_user ON token_blacklist(user_id);
CREATE INDEX IF NOT EXISTS idx_token_blacklist_expires ON token_blacklist(expires_at);
CREATE INDEX IF NOT EXISTS idx_password_reset_token ON password_reset_tokens(token);
CREATE INDEX IF NOT EXISTS idx_password_reset_user ON password_reset_tokens(user_id);
CREATE INDEX IF NOT EXISTS idx_password_reset_email ON password_reset_tokens(email);
"""


# ============================================================================
# JWT Token Utilities
# ============================================================================


def create_test_token(
    subject: str,
    expires_delta_seconds: int = 3600,
    additional_claims: Optional[Dict[str, Any]] = None,
) -> str:
    """Create a JWT token for testing purposes.

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
    }

    if additional_claims:
        payload.update(additional_claims)

    return jwt.encode(
        payload,
        settings.jwt_secret_key,
        algorithm=settings.jwt_algorithm,
    )


def create_access_token(
    user_id: str,
    email: str,
    role: str,
    expires_delta_seconds: int = 900,
) -> str:
    """Create an access token with standard claims.

    Args:
        user_id: User's unique identifier
        email: User's email address
        role: User's role (admin, teacher, parent, etc.)
        expires_delta_seconds: Token expiration time in seconds (default 15 min)

    Returns:
        str: Encoded JWT access token
    """
    return create_test_token(
        subject=user_id,
        expires_delta_seconds=expires_delta_seconds,
        additional_claims={
            "email": email,
            "role": role,
            "type": "access",
        },
    )


def create_refresh_token(
    user_id: str,
    expires_delta_seconds: int = 604800,
) -> str:
    """Create a refresh token.

    Args:
        user_id: User's unique identifier
        expires_delta_seconds: Token expiration time in seconds (default 7 days)

    Returns:
        str: Encoded JWT refresh token
    """
    return create_test_token(
        subject=user_id,
        expires_delta_seconds=expires_delta_seconds,
        additional_claims={"type": "refresh"},
    )


# ============================================================================
# Mock Objects
# ============================================================================


class MockUser:
    """Mock User object for testing without SQLAlchemy ORM overhead."""

    def __init__(
        self,
        id: UUID,
        email: str,
        password_hash: str,
        first_name: str,
        last_name: str,
        role: UserRole,
        is_active: bool,
        created_at: datetime,
        updated_at: datetime,
    ):
        self.id = id
        self.email = email
        self.password_hash = password_hash
        self.first_name = first_name
        self.last_name = last_name
        self.role = role
        self.is_active = is_active
        self.created_at = created_at
        self.updated_at = updated_at

    def __repr__(self) -> str:
        return f"<User(id={self.id}, email='{self.email}', role={self.role.value})>"


class MockTokenBlacklist:
    """Mock TokenBlacklist object for testing."""

    def __init__(
        self,
        id: UUID,
        token: str,
        user_id: UUID,
        blacklisted_at: datetime,
        expires_at: datetime,
    ):
        self.id = id
        self.token = token
        self.user_id = user_id
        self.blacklisted_at = blacklisted_at
        self.expires_at = expires_at

    def __repr__(self) -> str:
        return f"<TokenBlacklist(id={self.id}, user_id={self.user_id})>"


class MockPasswordResetToken:
    """Mock PasswordResetToken object for testing."""

    def __init__(
        self,
        id: UUID,
        token: str,
        user_id: UUID,
        email: str,
        is_used: bool,
        created_at: datetime,
        expires_at: datetime,
    ):
        self.id = id
        self.token = token
        self.user_id = user_id
        self.email = email
        self.is_used = is_used
        self.created_at = created_at
        self.expires_at = expires_at

    def __repr__(self) -> str:
        return f"<PasswordResetToken(id={self.id}, user_id={self.user_id}, is_used={self.is_used})>"


# ============================================================================
# Database Helper Functions
# ============================================================================


async def create_user_in_db(
    session: AsyncSession,
    email: str,
    password_hash: str,
    first_name: str = "Test",
    last_name: str = "User",
    role: UserRole = UserRole.TEACHER,
    is_active: bool = True,
) -> MockUser:
    """Create a user directly in the SQLite test database.

    Args:
        session: Async database session
        email: User's email address
        password_hash: Bcrypt-hashed password
        first_name: User's first name
        last_name: User's last name
        role: User's role (UserRole enum)
        is_active: Whether the user account is active

    Returns:
        MockUser: Mock user object with the created data
    """
    user_id = str(uuid4())
    now = datetime.now(timezone.utc)

    await session.execute(
        text("""
            INSERT INTO users (
                id, email, password_hash, first_name, last_name,
                role, is_active, created_at, updated_at
            ) VALUES (
                :id, :email, :password_hash, :first_name, :last_name,
                :role, :is_active, :created_at, :updated_at
            )
        """),
        {
            "id": user_id,
            "email": email,
            "password_hash": password_hash,
            "first_name": first_name,
            "last_name": last_name,
            "role": role.value,
            "is_active": 1 if is_active else 0,
            "created_at": now.isoformat(),
            "updated_at": now.isoformat(),
        },
    )
    await session.commit()

    return MockUser(
        id=UUID(user_id),
        email=email,
        password_hash=password_hash,
        first_name=first_name,
        last_name=last_name,
        role=role,
        is_active=is_active,
        created_at=now,
        updated_at=now,
    )


async def create_token_blacklist_in_db(
    session: AsyncSession,
    token: str,
    user_id: UUID,
    expires_at: Optional[datetime] = None,
) -> MockTokenBlacklist:
    """Create a blacklisted token in the SQLite test database.

    Args:
        session: Async database session
        token: The JWT token to blacklist
        user_id: ID of the user who owned the token
        expires_at: When the token expires (default: 1 hour from now)

    Returns:
        MockTokenBlacklist: Mock token blacklist object
    """
    blacklist_id = str(uuid4())
    now = datetime.now(timezone.utc)
    if expires_at is None:
        expires_at = now + timedelta(hours=1)

    await session.execute(
        text("""
            INSERT INTO token_blacklist (
                id, token, user_id, blacklisted_at, expires_at
            ) VALUES (
                :id, :token, :user_id, :blacklisted_at, :expires_at
            )
        """),
        {
            "id": blacklist_id,
            "token": token,
            "user_id": str(user_id),
            "blacklisted_at": now.isoformat(),
            "expires_at": expires_at.isoformat(),
        },
    )
    await session.commit()

    return MockTokenBlacklist(
        id=UUID(blacklist_id),
        token=token,
        user_id=user_id,
        blacklisted_at=now,
        expires_at=expires_at,
    )


async def create_password_reset_token_in_db(
    session: AsyncSession,
    token_hash: str,
    user_id: UUID,
    email: str,
    is_used: bool = False,
    expires_at: Optional[datetime] = None,
) -> MockPasswordResetToken:
    """Create a password reset token in the SQLite test database.

    Args:
        session: Async database session
        token_hash: Hashed reset token (SHA-256)
        user_id: ID of the user requesting reset
        email: Email address of the user
        is_used: Whether the token has been used
        expires_at: When the token expires (default: 1 hour from now)

    Returns:
        MockPasswordResetToken: Mock password reset token object
    """
    reset_id = str(uuid4())
    now = datetime.now(timezone.utc)
    if expires_at is None:
        expires_at = now + timedelta(hours=1)

    await session.execute(
        text("""
            INSERT INTO password_reset_tokens (
                id, token, user_id, email, is_used, created_at, expires_at
            ) VALUES (
                :id, :token, :user_id, :email, :is_used, :created_at, :expires_at
            )
        """),
        {
            "id": reset_id,
            "token": token_hash,
            "user_id": str(user_id),
            "email": email,
            "is_used": 1 if is_used else 0,
            "created_at": now.isoformat(),
            "expires_at": expires_at.isoformat(),
        },
    )
    await session.commit()

    return MockPasswordResetToken(
        id=UUID(reset_id),
        token=token_hash,
        user_id=user_id,
        email=email,
        is_used=is_used,
        created_at=now,
        expires_at=expires_at,
    )


# ============================================================================
# Core Database Fixtures
# ============================================================================


@pytest.fixture(scope="session")
def event_loop_policy():
    """Use default event loop policy for tests."""
    import asyncio

    return asyncio.DefaultEventLoopPolicy()


@pytest_asyncio.fixture
async def auth_db_session() -> AsyncGenerator[AsyncSession, None]:
    """Create a fresh database session with auth tables for each test.

    Creates all auth tables via raw SQL for SQLite compatibility.

    Yields:
        AsyncSession: Async database session for testing.
    """
    # Create auth tables via raw SQL
    async with test_engine.begin() as conn:
        for statement in SQLITE_CREATE_AUTH_TABLES_SQL.strip().split(";"):
            statement = statement.strip()
            if statement:
                await conn.execute(text(statement))

    async with TestAsyncSessionLocal() as session:
        try:
            yield session
        finally:
            await session.rollback()

    # Drop all tables after test
    async with test_engine.begin() as conn:
        await conn.execute(text("DROP TABLE IF EXISTS password_reset_tokens"))
        await conn.execute(text("DROP TABLE IF EXISTS token_blacklist"))
        await conn.execute(text("DROP TABLE IF EXISTS users"))


@pytest_asyncio.fixture
async def auth_client(auth_db_session: AsyncSession) -> AsyncGenerator[AsyncClient, None]:
    """Create an async HTTP client for testing auth endpoints.

    Args:
        auth_db_session: Test database session fixture.

    Yields:
        AsyncClient: HTTP client for making test requests.
    """
    from app.database import get_db
    from app.main import app

    async def override_get_db() -> AsyncGenerator[AsyncSession, None]:
        try:
            yield auth_db_session
        finally:
            pass

    app.dependency_overrides[get_db] = override_get_db

    async with AsyncClient(
        transport=ASGITransport(app=app),
        base_url="http://test",
    ) as ac:
        yield ac

    app.dependency_overrides.clear()


# ============================================================================
# User Fixtures
# ============================================================================


# Default test password (pre-hashed with bcrypt for "Test123!@#")
# This is a valid bcrypt hash that can be verified with the password "Test123!@#"
TEST_PASSWORD_PLAIN = "Test123!@#"
TEST_PASSWORD_HASH = "$2b$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/X4.G5P0FsMLr/fGYC"


@pytest.fixture
def test_password_plain() -> str:
    """Return the plain text test password."""
    return TEST_PASSWORD_PLAIN


@pytest.fixture
def test_password_hash() -> str:
    """Return the bcrypt hash of the test password."""
    return TEST_PASSWORD_HASH


@pytest.fixture
def test_admin_id() -> UUID:
    """Generate a consistent test admin user ID."""
    return UUID("11111111-1111-1111-1111-111111111111")


@pytest.fixture
def test_teacher_id() -> UUID:
    """Generate a consistent test teacher user ID."""
    return UUID("22222222-2222-2222-2222-222222222222")


@pytest.fixture
def test_parent_id() -> UUID:
    """Generate a consistent test parent user ID."""
    return UUID("33333333-3333-3333-3333-333333333333")


@pytest.fixture
def test_accountant_id() -> UUID:
    """Generate a consistent test accountant user ID."""
    return UUID("44444444-4444-4444-4444-444444444444")


@pytest.fixture
def test_staff_id() -> UUID:
    """Generate a consistent test staff user ID."""
    return UUID("55555555-5555-5555-5555-555555555555")


@pytest_asyncio.fixture
async def admin_user(
    auth_db_session: AsyncSession,
    test_password_hash: str,
) -> MockUser:
    """Create an admin user in the test database."""
    return await create_user_in_db(
        auth_db_session,
        email="admin@example.com",
        password_hash=test_password_hash,
        first_name="Admin",
        last_name="User",
        role=UserRole.ADMIN,
        is_active=True,
    )


@pytest_asyncio.fixture
async def teacher_user(
    auth_db_session: AsyncSession,
    test_password_hash: str,
) -> MockUser:
    """Create a teacher user in the test database."""
    return await create_user_in_db(
        auth_db_session,
        email="teacher@example.com",
        password_hash=test_password_hash,
        first_name="Teacher",
        last_name="User",
        role=UserRole.TEACHER,
        is_active=True,
    )


@pytest_asyncio.fixture
async def parent_user(
    auth_db_session: AsyncSession,
    test_password_hash: str,
) -> MockUser:
    """Create a parent user in the test database."""
    return await create_user_in_db(
        auth_db_session,
        email="parent@example.com",
        password_hash=test_password_hash,
        first_name="Parent",
        last_name="User",
        role=UserRole.PARENT,
        is_active=True,
    )


@pytest_asyncio.fixture
async def accountant_user(
    auth_db_session: AsyncSession,
    test_password_hash: str,
) -> MockUser:
    """Create an accountant user in the test database."""
    return await create_user_in_db(
        auth_db_session,
        email="accountant@example.com",
        password_hash=test_password_hash,
        first_name="Accountant",
        last_name="User",
        role=UserRole.ACCOUNTANT,
        is_active=True,
    )


@pytest_asyncio.fixture
async def staff_user(
    auth_db_session: AsyncSession,
    test_password_hash: str,
) -> MockUser:
    """Create a staff user in the test database."""
    return await create_user_in_db(
        auth_db_session,
        email="staff@example.com",
        password_hash=test_password_hash,
        first_name="Staff",
        last_name="User",
        role=UserRole.STAFF,
        is_active=True,
    )


@pytest_asyncio.fixture
async def inactive_user(
    auth_db_session: AsyncSession,
    test_password_hash: str,
) -> MockUser:
    """Create an inactive user in the test database."""
    return await create_user_in_db(
        auth_db_session,
        email="inactive@example.com",
        password_hash=test_password_hash,
        first_name="Inactive",
        last_name="User",
        role=UserRole.TEACHER,
        is_active=False,
    )


# ============================================================================
# Token Fixtures
# ============================================================================


@pytest.fixture
def admin_access_token(admin_user: MockUser) -> str:
    """Create a valid admin access token."""
    return create_access_token(
        user_id=str(admin_user.id),
        email=admin_user.email,
        role=admin_user.role.value,
    )


@pytest.fixture
def teacher_access_token(teacher_user: MockUser) -> str:
    """Create a valid teacher access token."""
    return create_access_token(
        user_id=str(teacher_user.id),
        email=teacher_user.email,
        role=teacher_user.role.value,
    )


@pytest.fixture
def parent_access_token(parent_user: MockUser) -> str:
    """Create a valid parent access token."""
    return create_access_token(
        user_id=str(parent_user.id),
        email=parent_user.email,
        role=parent_user.role.value,
    )


@pytest.fixture
def accountant_access_token(accountant_user: MockUser) -> str:
    """Create a valid accountant access token."""
    return create_access_token(
        user_id=str(accountant_user.id),
        email=accountant_user.email,
        role=accountant_user.role.value,
    )


@pytest.fixture
def admin_refresh_token(admin_user: MockUser) -> str:
    """Create a valid admin refresh token."""
    return create_refresh_token(user_id=str(admin_user.id))


@pytest.fixture
def teacher_refresh_token(teacher_user: MockUser) -> str:
    """Create a valid teacher refresh token."""
    return create_refresh_token(user_id=str(teacher_user.id))


@pytest.fixture
def expired_access_token(teacher_user: MockUser) -> str:
    """Create an expired access token."""
    return create_access_token(
        user_id=str(teacher_user.id),
        email=teacher_user.email,
        role=teacher_user.role.value,
        expires_delta_seconds=-3600,  # Expired 1 hour ago
    )


@pytest.fixture
def expired_refresh_token(teacher_user: MockUser) -> str:
    """Create an expired refresh token."""
    return create_refresh_token(
        user_id=str(teacher_user.id),
        expires_delta_seconds=-3600,  # Expired 1 hour ago
    )


@pytest.fixture
def invalid_token() -> str:
    """Create an invalid/malformed token."""
    return "invalid.token.string"


@pytest.fixture
def token_wrong_signature() -> str:
    """Create a token signed with wrong secret."""
    payload = {
        "sub": str(uuid4()),
        "iat": int(datetime.now(timezone.utc).timestamp()),
        "exp": int((datetime.now(timezone.utc) + timedelta(hours=1)).timestamp()),
        "type": "access",
    }
    return jwt.encode(payload, "wrong_secret_key", algorithm="HS256")


# ============================================================================
# Auth Headers Fixtures
# ============================================================================


@pytest.fixture
def admin_auth_headers(admin_access_token: str) -> Dict[str, str]:
    """Create authorization headers with admin token."""
    return {"Authorization": f"Bearer {admin_access_token}"}


@pytest.fixture
def teacher_auth_headers(teacher_access_token: str) -> Dict[str, str]:
    """Create authorization headers with teacher token."""
    return {"Authorization": f"Bearer {teacher_access_token}"}


@pytest.fixture
def parent_auth_headers(parent_access_token: str) -> Dict[str, str]:
    """Create authorization headers with parent token."""
    return {"Authorization": f"Bearer {parent_access_token}"}


@pytest.fixture
def accountant_auth_headers(accountant_access_token: str) -> Dict[str, str]:
    """Create authorization headers with accountant token."""
    return {"Authorization": f"Bearer {accountant_access_token}"}


@pytest.fixture
def expired_auth_headers(expired_access_token: str) -> Dict[str, str]:
    """Create authorization headers with expired token."""
    return {"Authorization": f"Bearer {expired_access_token}"}


@pytest.fixture
def invalid_auth_headers(invalid_token: str) -> Dict[str, str]:
    """Create authorization headers with invalid token."""
    return {"Authorization": f"Bearer {invalid_token}"}


# ============================================================================
# Blacklist Fixtures
# ============================================================================


@pytest_asyncio.fixture
async def blacklisted_token(
    auth_db_session: AsyncSession,
    teacher_user: MockUser,
    teacher_access_token: str,
) -> MockTokenBlacklist:
    """Create a blacklisted access token."""
    return await create_token_blacklist_in_db(
        auth_db_session,
        token=teacher_access_token,
        user_id=teacher_user.id,
        expires_at=datetime.now(timezone.utc) + timedelta(hours=1),
    )


# ============================================================================
# Password Reset Fixtures
# ============================================================================


@pytest.fixture
def valid_reset_token() -> str:
    """Generate a valid password reset token (plain text)."""
    import secrets
    return secrets.token_urlsafe(32)


@pytest_asyncio.fixture
async def password_reset_token_record(
    auth_db_session: AsyncSession,
    teacher_user: MockUser,
    valid_reset_token: str,
) -> MockPasswordResetToken:
    """Create a valid password reset token in the database."""
    from app.core.security import hash_token

    return await create_password_reset_token_in_db(
        auth_db_session,
        token_hash=hash_token(valid_reset_token),
        user_id=teacher_user.id,
        email=teacher_user.email,
        is_used=False,
        expires_at=datetime.now(timezone.utc) + timedelta(hours=1),
    )


@pytest_asyncio.fixture
async def used_reset_token_record(
    auth_db_session: AsyncSession,
    teacher_user: MockUser,
) -> MockPasswordResetToken:
    """Create an already-used password reset token in the database."""
    from app.core.security import hash_token
    import secrets

    token = secrets.token_urlsafe(32)
    return await create_password_reset_token_in_db(
        auth_db_session,
        token_hash=hash_token(token),
        user_id=teacher_user.id,
        email=teacher_user.email,
        is_used=True,
        expires_at=datetime.now(timezone.utc) + timedelta(hours=1),
    )


@pytest_asyncio.fixture
async def expired_reset_token_record(
    auth_db_session: AsyncSession,
    teacher_user: MockUser,
) -> MockPasswordResetToken:
    """Create an expired password reset token in the database."""
    from app.core.security import hash_token
    import secrets

    token = secrets.token_urlsafe(32)
    return await create_password_reset_token_in_db(
        auth_db_session,
        token_hash=hash_token(token),
        user_id=teacher_user.id,
        email=teacher_user.email,
        is_used=False,
        expires_at=datetime.now(timezone.utc) - timedelta(hours=1),  # Expired
    )


# ============================================================================
# Request Data Fixtures
# ============================================================================


@pytest.fixture
def valid_login_request(teacher_user: MockUser, test_password_plain: str) -> Dict[str, str]:
    """Create a valid login request payload."""
    return {
        "email": teacher_user.email,
        "password": test_password_plain,
    }


@pytest.fixture
def invalid_password_login_request(teacher_user: MockUser) -> Dict[str, str]:
    """Create a login request with invalid password."""
    return {
        "email": teacher_user.email,
        "password": "wrong_password",
    }


@pytest.fixture
def nonexistent_user_login_request() -> Dict[str, str]:
    """Create a login request for non-existent user."""
    return {
        "email": "nonexistent@example.com",
        "password": "any_password",
    }


@pytest.fixture
def valid_refresh_request(teacher_refresh_token: str) -> Dict[str, str]:
    """Create a valid refresh token request payload."""
    return {"refresh_token": teacher_refresh_token}


@pytest.fixture
def valid_logout_request(
    teacher_access_token: str,
    teacher_refresh_token: str,
) -> Dict[str, Any]:
    """Create a valid logout request payload."""
    return {
        "access_token": teacher_access_token,
        "refresh_token": teacher_refresh_token,
    }


@pytest.fixture
def password_reset_request(teacher_user: MockUser) -> Dict[str, str]:
    """Create a password reset request payload."""
    return {"email": teacher_user.email}


@pytest.fixture
def password_reset_confirm_request(valid_reset_token: str) -> Dict[str, str]:
    """Create a password reset confirmation request payload."""
    return {
        "token": valid_reset_token,
        "new_password": "NewSecure123!@#",
    }


# ============================================================================
# Utility Fixtures
# ============================================================================


@pytest.fixture
def random_uuid() -> UUID:
    """Generate a random UUID for testing."""
    return uuid4()


@pytest.fixture
def current_timestamp() -> datetime:
    """Get the current UTC timestamp."""
    return datetime.now(timezone.utc)
