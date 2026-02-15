"""Pytest fixtures for LAYA AI Service tests.

Provides reusable test fixtures for database sessions, HTTP clients,
authentication tokens, and sample test data.
"""

from __future__ import annotations

from datetime import datetime, timezone
from typing import Any, AsyncGenerator, Optional
from uuid import UUID, uuid4

import jwt
import pytest
import pytest_asyncio
from fastapi import FastAPI
from httpx import ASGITransport, AsyncClient
from sqlalchemy.ext.asyncio import AsyncSession, create_async_engine
from sqlalchemy.orm import sessionmaker

from app.config import settings
from app.models.base import Base


# Test database URL using SQLite for isolation
TEST_DATABASE_URL = "sqlite+aiosqlite:///:memory:"


def create_test_token(
    subject: str,
    expires_delta_seconds: int = 3600,
    additional_claims: Optional[dict[str, Any]] = None,
) -> str:
    """Create a JWT token for testing purposes.

    This is a local implementation to avoid importing from app.auth
    which may have Python version compatibility issues.

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


@pytest.fixture(scope="session")
def event_loop_policy():
    """Use default event loop policy for tests."""
    import asyncio

    return asyncio.DefaultEventLoopPolicy()


@pytest_asyncio.fixture(scope="function")
async def test_engine():
    """Create a test database engine with in-memory SQLite.

    Yields:
        AsyncEngine: Async SQLAlchemy engine for testing
    """
    engine = create_async_engine(
        TEST_DATABASE_URL,
        echo=False,
        future=True,
    )

    async with engine.begin() as conn:
        await conn.run_sync(Base.metadata.create_all)

    yield engine

    async with engine.begin() as conn:
        await conn.run_sync(Base.metadata.drop_all)

    await engine.dispose()


@pytest_asyncio.fixture(scope="function")
async def db_session(test_engine) -> AsyncGenerator[AsyncSession, None]:
    """Create a test database session.

    Args:
        test_engine: The test database engine fixture

    Yields:
        AsyncSession: Async database session for testing
    """
    async_session_factory = sessionmaker(
        test_engine,
        class_=AsyncSession,
        expire_on_commit=False,
        autocommit=False,
        autoflush=False,
    )

    async with async_session_factory() as session:
        yield session
        await session.rollback()


@pytest.fixture
def test_user_id() -> UUID:
    """Generate a consistent test user ID.

    Returns:
        UUID: Test user identifier
    """
    return UUID("12345678-1234-1234-1234-123456789abc")


@pytest.fixture
def test_child_id() -> UUID:
    """Generate a consistent test child ID.

    Returns:
        UUID: Test child identifier
    """
    return UUID("87654321-4321-4321-4321-cba987654321")


@pytest.fixture
def test_user_payload(test_user_id: UUID) -> dict[str, Any]:
    """Create a test user token payload.

    Args:
        test_user_id: The test user ID fixture

    Returns:
        dict: JWT token payload for test user
    """
    return {
        "sub": str(test_user_id),
        "email": "test@example.com",
        "role": "educator",
    }


@pytest.fixture
def valid_token(test_user_payload: dict[str, Any]) -> str:
    """Create a valid JWT token for testing.

    Args:
        test_user_payload: The test user payload fixture

    Returns:
        str: Valid JWT token
    """
    return create_test_token(
        subject=test_user_payload["sub"],
        expires_delta_seconds=3600,
        additional_claims={
            "email": test_user_payload["email"],
            "role": test_user_payload["role"],
        },
    )


@pytest.fixture
def expired_token(test_user_payload: dict[str, Any]) -> str:
    """Create an expired JWT token for testing.

    Args:
        test_user_payload: The test user payload fixture

    Returns:
        str: Expired JWT token
    """
    return create_test_token(
        subject=test_user_payload["sub"],
        expires_delta_seconds=-3600,  # Already expired
        additional_claims={
            "email": test_user_payload["email"],
            "role": test_user_payload["role"],
        },
    )


@pytest.fixture
def auth_headers(valid_token: str) -> dict[str, str]:
    """Create authorization headers with valid token.

    Args:
        valid_token: The valid JWT token fixture

    Returns:
        dict: HTTP headers with Authorization Bearer token
    """
    return {"Authorization": f"Bearer {valid_token}"}


@pytest_asyncio.fixture
async def test_app(db_session: AsyncSession) -> FastAPI:
    """Create a test FastAPI application instance.

    Overrides the database dependency to use the test session.

    Args:
        db_session: The test database session fixture

    Returns:
        FastAPI: Configured test application
    """
    # Import here to avoid circular imports and Python version issues
    from app.database import get_db
    from app.main import app

    async def override_get_db():
        yield db_session

    app.dependency_overrides[get_db] = override_get_db

    yield app

    app.dependency_overrides.clear()


@pytest_asyncio.fixture
async def client(test_app: FastAPI) -> AsyncGenerator[AsyncClient, None]:
    """Create an async HTTP client for testing API endpoints.

    Args:
        test_app: The test FastAPI application fixture

    Yields:
        AsyncClient: HTTP client for making test requests
    """
    transport = ASGITransport(app=test_app)
    async with AsyncClient(
        transport=transport,
        base_url="http://test",
    ) as ac:
        yield ac


@pytest.fixture
def sample_coaching_request(test_child_id: UUID) -> dict[str, Any]:
    """Create a sample coaching guidance request.

    Args:
        test_child_id: The test child ID fixture

    Returns:
        dict: Sample request payload for coaching guidance endpoint
    """
    return {
        "child_id": str(test_child_id),
        "special_need_types": ["autism"],
        "situation_description": "Child has difficulty with transitions between activities",
        "category": "behavior_management",
        "max_recommendations": 3,
    }


@pytest.fixture
def sample_medical_question_request(test_child_id: UUID) -> dict[str, Any]:
    """Create a sample request with medical question.

    Args:
        test_child_id: The test child ID fixture

    Returns:
        dict: Sample request with medical question
    """
    return {
        "child_id": str(test_child_id),
        "special_need_types": ["adhd"],
        "situation_description": "What medication should I give for ADHD symptoms?",
        "max_recommendations": 3,
    }


@pytest.fixture
def sample_evidence_source() -> dict[str, Any]:
    """Create a sample evidence source data.

    Returns:
        dict: Sample evidence source for citations
    """
    return {
        "title": "Evidence-Based Practices for Children with Autism",
        "authors": "Wong, C., et al.",
        "publication_year": 2015,
        "source_type": "peer_reviewed",
        "url": "https://doi.org/10.1177/1362361315577525",
        "doi": "10.1177/1362361315577525",
    }


@pytest.fixture
def sample_coaching_guidance() -> dict[str, Any]:
    """Create sample coaching guidance data.

    Returns:
        dict: Sample coaching guidance response
    """
    return {
        "title": "Visual Transition Supports",
        "content": "Use visual schedules and countdown timers to support "
        "smooth transitions between activities.",
        "category": "behavior_management",
        "special_need_types": ["autism"],
        "priority": "high",
        "target_audience": "educator",
    }


@pytest.fixture
def random_uuid() -> UUID:
    """Generate a random UUID for testing.

    Returns:
        UUID: Random UUID
    """
    return uuid4()


@pytest.fixture
def current_timestamp() -> datetime:
    """Get the current UTC timestamp.

    Returns:
        datetime: Current UTC datetime
    """
    return datetime.now(timezone.utc)


# Safety disclaimer constant for tests
SAFETY_DISCLAIMER = (
    "This guidance is for informational purposes only and does not "
    "constitute professional medical, therapeutic, or educational advice. "
    "Always consult with qualified professionals for specific recommendations."
)


@pytest.fixture
def safety_disclaimer() -> str:
    """Return the expected safety disclaimer text.

    Returns:
        str: Safety disclaimer text
    """
    return SAFETY_DISCLAIMER


# Medical keywords for detection testing
MEDICAL_KEYWORDS = [
    "medication",
    "prescribe",
    "dosage",
    "drug",
    "diagnosis",
    "treatment",
    "therapy",
    "medical",
]


@pytest.fixture
def medical_keywords() -> list[str]:
    """Return list of medical keywords for detection testing.

    Returns:
        list[str]: Medical keywords
    """
    return MEDICAL_KEYWORDS
