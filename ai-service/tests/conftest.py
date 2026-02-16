"""Pytest fixtures and test configuration for LAYA AI Service.

Provides reusable fixtures for async testing, database sessions,
test data, and authentication mocking for coaching, activity, and communication domains.
"""

from __future__ import annotations

from datetime import datetime, timezone
from typing import Any, AsyncGenerator, Dict, List, Optional
from uuid import UUID, uuid4

import jwt
import pytest
import pytest_asyncio
from fastapi import FastAPI
from httpx import ASGITransport, AsyncClient
from sqlalchemy import StaticPool, event, text
from sqlalchemy.ext.asyncio import AsyncSession, create_async_engine
from sqlalchemy.orm import sessionmaker

from app.config import settings
from app.models.base import Base
from app.models.activity import (
    Activity,
    ActivityDifficulty,
    ActivityParticipation,
    ActivityRecommendation,
    ActivityType,
)


# Test database URL using SQLite for isolation
TEST_DATABASE_URL = "sqlite+aiosqlite:///:memory:"


def create_test_token(
    subject: str,
    expires_delta_seconds: int = 3600,
    additional_claims: Optional[dict[str, Any]] = None,
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

# Configure pytest-asyncio
pytest_plugins = ("pytest_asyncio",)


# SQLite-compatible coaching tables (PostgreSQL ARRAY not supported in SQLite)
SQLITE_CREATE_COACHING_TABLES_SQL = """
CREATE TABLE IF NOT EXISTS coaching_sessions (
    id TEXT PRIMARY KEY,
    child_id TEXT NOT NULL,
    user_id TEXT NOT NULL,
    question TEXT NOT NULL,
    context TEXT,
    special_need_types TEXT,
    category VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS coaching_recommendations (
    id TEXT PRIMARY KEY,
    session_id TEXT NOT NULL REFERENCES coaching_sessions(id) ON DELETE CASCADE,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    category VARCHAR(50) NOT NULL,
    priority VARCHAR(20) NOT NULL DEFAULT 'medium',
    relevance_score REAL NOT NULL DEFAULT 0.0,
    target_audience VARCHAR(100) NOT NULL DEFAULT 'educator',
    prerequisites TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS evidence_sources (
    id TEXT PRIMARY KEY,
    recommendation_id TEXT NOT NULL REFERENCES coaching_recommendations(id) ON DELETE CASCADE,
    source_type VARCHAR(50) NOT NULL,
    title VARCHAR(500) NOT NULL,
    authors TEXT,
    publication VARCHAR(200),
    year INTEGER,
    doi VARCHAR(100),
    url VARCHAR(500),
    isbn VARCHAR(20),
    accessed_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_coaching_sessions_child ON coaching_sessions(child_id);
CREATE INDEX IF NOT EXISTS idx_coaching_sessions_user ON coaching_sessions(user_id);
CREATE INDEX IF NOT EXISTS idx_coaching_recommendations_session ON coaching_recommendations(session_id);
CREATE INDEX IF NOT EXISTS idx_coaching_recommendations_category ON coaching_recommendations(category);
CREATE INDEX IF NOT EXISTS idx_evidence_sources_recommendation ON evidence_sources(recommendation_id);
"""

# SQLite-compatible activity tables (PostgreSQL ARRAY not supported in SQLite)
SQLITE_CREATE_ACTIVITY_TABLES_SQL = """
CREATE TABLE IF NOT EXISTS activities (
    id TEXT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    activity_type VARCHAR(20) NOT NULL,
    difficulty VARCHAR(20) NOT NULL DEFAULT 'medium',
    duration_minutes INTEGER NOT NULL DEFAULT 30,
    materials_needed TEXT DEFAULT '[]',
    min_age_months INTEGER,
    max_age_months INTEGER,
    special_needs_adaptations TEXT,
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS activity_recommendations (
    id TEXT PRIMARY KEY,
    child_id TEXT NOT NULL,
    activity_id TEXT NOT NULL REFERENCES activities(id) ON DELETE CASCADE,
    relevance_score REAL NOT NULL,
    reasoning TEXT,
    is_dismissed INTEGER NOT NULL DEFAULT 0,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS activity_participations (
    id TEXT PRIMARY KEY,
    child_id TEXT NOT NULL,
    activity_id TEXT NOT NULL REFERENCES activities(id) ON DELETE CASCADE,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP,
    duration_minutes INTEGER,
    completion_status VARCHAR(20) NOT NULL DEFAULT 'started',
    engagement_score REAL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_activities_name ON activities(name);
CREATE INDEX IF NOT EXISTS idx_activities_type ON activities(activity_type);
CREATE INDEX IF NOT EXISTS idx_activities_active ON activities(is_active);
CREATE INDEX IF NOT EXISTS idx_recommendations_child ON activity_recommendations(child_id);
CREATE INDEX IF NOT EXISTS idx_recommendations_activity ON activity_recommendations(activity_id);
CREATE INDEX IF NOT EXISTS idx_participations_child ON activity_participations(child_id);
CREATE INDEX IF NOT EXISTS idx_participations_activity ON activity_participations(activity_id);
"""


# SQLite-compatible communication tables (PostgreSQL ARRAY not supported in SQLite)
SQLITE_CREATE_COMMUNICATION_TABLES_SQL = """
CREATE TABLE IF NOT EXISTS parent_reports (
    id TEXT PRIMARY KEY,
    child_id TEXT NOT NULL,
    report_date DATE NOT NULL,
    language VARCHAR(2) NOT NULL DEFAULT 'en',
    summary TEXT NOT NULL,
    activities_summary TEXT,
    mood_summary TEXT,
    meals_summary TEXT,
    milestones TEXT,
    educator_notes TEXT,
    generated_by TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS home_activities (
    id TEXT PRIMARY KEY,
    child_id TEXT NOT NULL,
    activity_name VARCHAR(200) NOT NULL,
    activity_description TEXT NOT NULL,
    materials_needed TEXT,
    estimated_duration_minutes INTEGER,
    developmental_area VARCHAR(50),
    language VARCHAR(2) NOT NULL DEFAULT 'en',
    based_on_activity_id TEXT,
    is_completed INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS communication_preferences (
    id TEXT PRIMARY KEY,
    parent_id TEXT NOT NULL UNIQUE,
    child_id TEXT NOT NULL,
    preferred_language VARCHAR(2) NOT NULL DEFAULT 'en',
    report_frequency VARCHAR(20) NOT NULL DEFAULT 'daily',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_parent_reports_child ON parent_reports(child_id);
CREATE INDEX IF NOT EXISTS idx_parent_reports_child_date ON parent_reports(child_id, report_date);
CREATE INDEX IF NOT EXISTS idx_home_activities_child ON home_activities(child_id);
CREATE INDEX IF NOT EXISTS idx_comm_prefs_parent ON communication_preferences(parent_id);
CREATE INDEX IF NOT EXISTS idx_comm_prefs_child ON communication_preferences(child_id);
"""


# SQLite-compatible auth tables
SQLITE_CREATE_AUTH_TABLES_SQL = """
CREATE TABLE IF NOT EXISTS users (
    id TEXT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    role VARCHAR(50) NOT NULL,
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS token_blacklist (
    id TEXT PRIMARY KEY,
    token TEXT NOT NULL UNIQUE,
    blacklisted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id TEXT PRIMARY KEY,
    user_id TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token TEXT NOT NULL UNIQUE,
    expires_at TIMESTAMP NOT NULL,
    is_used INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);
CREATE INDEX IF NOT EXISTS idx_token_blacklist_token ON token_blacklist(token);
CREATE INDEX IF NOT EXISTS idx_password_reset_tokens_token ON password_reset_tokens(token);
CREATE INDEX IF NOT EXISTS idx_password_reset_tokens_user ON password_reset_tokens(user_id);
"""


# SQLite-compatible coaching tables (PostgreSQL ARRAY not supported in SQLite)
SQLITE_CREATE_COACHING_TABLES_SQL = """
CREATE TABLE IF NOT EXISTS coaching_sessions (
    id TEXT PRIMARY KEY,
    child_id TEXT NOT NULL,
    user_id TEXT NOT NULL,
    question TEXT NOT NULL,
    context TEXT,
    special_need_types TEXT,
    category VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS coaching_recommendations (
    id TEXT PRIMARY KEY,
    session_id TEXT NOT NULL REFERENCES coaching_sessions(id) ON DELETE CASCADE,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    category VARCHAR(50) NOT NULL,
    priority VARCHAR(20) NOT NULL DEFAULT 'medium',
    relevance_score REAL NOT NULL DEFAULT 0.0,
    target_audience VARCHAR(100) NOT NULL DEFAULT 'educator',
    prerequisites TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS evidence_sources (
    id TEXT PRIMARY KEY,
    recommendation_id TEXT NOT NULL REFERENCES coaching_recommendations(id) ON DELETE CASCADE,
    source_type VARCHAR(50) NOT NULL,
    title VARCHAR(500) NOT NULL,
    authors TEXT,
    publication VARCHAR(200),
    year INTEGER,
    doi VARCHAR(100),
    url VARCHAR(500),
    isbn VARCHAR(20),
    accessed_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_coaching_sessions_child ON coaching_sessions(child_id);
CREATE INDEX IF NOT EXISTS idx_coaching_sessions_user ON coaching_sessions(user_id);
CREATE INDEX IF NOT EXISTS idx_coaching_recommendations_session ON coaching_recommendations(session_id);
CREATE INDEX IF NOT EXISTS idx_evidence_sources_recommendation ON evidence_sources(recommendation_id);
"""


@pytest.fixture(scope="session")
def event_loop_policy():
    """Use default event loop policy for tests."""
    import asyncio

    return asyncio.DefaultEventLoopPolicy()


@pytest_asyncio.fixture
async def db_session() -> AsyncGenerator[AsyncSession, None]:
    """Create a fresh database session for each test.

    Creates all tables via raw SQL for SQLite compatibility
    (PostgreSQL ARRAY types not supported in SQLite).

    Yields:
        AsyncSession: Async database session for testing.
    """
    # Create coaching tables via raw SQL (SQLite compatibility)
    async with test_engine.begin() as conn:
        for statement in SQLITE_CREATE_COACHING_TABLES_SQL.strip().split(';'):
            statement = statement.strip()
            if statement:
                await conn.execute(text(statement))

    # Create activity tables via raw SQL (SQLite compatibility)
    async with test_engine.begin() as conn:
        for statement in SQLITE_CREATE_ACTIVITY_TABLES_SQL.strip().split(';'):
            statement = statement.strip()
            if statement:
                await conn.execute(text(statement))

    # Create communication tables via raw SQL (SQLite compatibility)
    async with test_engine.begin() as conn:
        for statement in SQLITE_CREATE_COMMUNICATION_TABLES_SQL.strip().split(';'):
            statement = statement.strip()
            if statement:
                await conn.execute(text(statement))

    # Create auth tables via raw SQL (SQLite compatibility)
    async with test_engine.begin() as conn:
        for statement in SQLITE_CREATE_AUTH_TABLES_SQL.strip().split(';'):
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
        # Drop auth tables (foreign keys first)
        await conn.execute(text("DROP TABLE IF EXISTS password_reset_tokens"))
        await conn.execute(text("DROP TABLE IF EXISTS token_blacklist"))
        await conn.execute(text("DROP TABLE IF EXISTS users"))
        # Drop evidence sources (foreign key to recommendations)
        await conn.execute(text("DROP TABLE IF EXISTS evidence_sources"))
        # Drop coaching recommendations (foreign key to sessions)
        await conn.execute(text("DROP TABLE IF EXISTS coaching_recommendations"))
        # Drop coaching sessions
        await conn.execute(text("DROP TABLE IF EXISTS coaching_sessions"))
        # Drop communication tables
        await conn.execute(text("DROP TABLE IF EXISTS communication_preferences"))
        await conn.execute(text("DROP TABLE IF EXISTS home_activities"))
        await conn.execute(text("DROP TABLE IF EXISTS parent_reports"))
        # Drop activity tables
        await conn.execute(text("DROP TABLE IF EXISTS activity_participations"))
        await conn.execute(text("DROP TABLE IF EXISTS activity_recommendations"))
        await conn.execute(text("DROP TABLE IF EXISTS activities"))


@pytest_asyncio.fixture
async def client(db_session: AsyncSession) -> AsyncGenerator[AsyncClient, None]:
    """Create an async HTTP client for testing API endpoints.

    Args:
        db_session: Test database session fixture.

    Yields:
        AsyncClient: HTTP client for making test requests.
    """
    from app.database import get_db
    from app.main import app

    async def override_get_db() -> AsyncGenerator[AsyncSession, None]:
        try:
            yield db_session
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
# Shared authentication fixtures
# ============================================================================


@pytest.fixture
def test_user_id() -> UUID:
    """Generate a consistent test user ID."""
    return UUID("12345678-1234-1234-1234-123456789abc")


@pytest.fixture
def test_child_id() -> UUID:
    """Generate a consistent test child ID."""
    return UUID("87654321-4321-4321-4321-cba987654321")


@pytest.fixture
def test_user_payload(test_user_id: UUID) -> dict[str, Any]:
    """Create a test user token payload."""
    return {
        "sub": str(test_user_id),
        "email": "test@example.com",
        "role": "educator",
    }


@pytest.fixture
def valid_token(test_user_payload: dict[str, Any]) -> str:
    """Create a valid JWT token for testing."""
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
    """Create an expired JWT token for testing."""
    return create_test_token(
        subject=test_user_payload["sub"],
        expires_delta_seconds=-3600,
        additional_claims={
            "email": test_user_payload["email"],
            "role": test_user_payload["role"],
        },
    )


@pytest.fixture
def auth_headers(valid_token: str) -> dict[str, str]:
    """Create authorization headers with valid token."""
    return {"Authorization": f"Bearer {valid_token}"}


@pytest.fixture
def random_uuid() -> UUID:
    """Generate a random UUID for testing."""
    return uuid4()


@pytest.fixture
def current_timestamp() -> datetime:
    """Get the current UTC timestamp."""
    return datetime.now(timezone.utc)


# ============================================================================
# Coaching fixtures
# ============================================================================


@pytest.fixture
def sample_coaching_request(test_child_id: UUID) -> dict[str, Any]:
    """Create a sample coaching guidance request."""
    return {
        "child_id": str(test_child_id),
        "special_need_types": ["autism"],
        "situation_description": "Child has difficulty with transitions between activities",
        "category": "behavior_management",
        "max_recommendations": 3,
    }


@pytest.fixture
def sample_medical_question_request(test_child_id: UUID) -> dict[str, Any]:
    """Create a sample request with medical question."""
    return {
        "child_id": str(test_child_id),
        "special_need_types": ["adhd"],
        "situation_description": "What medication should I give for ADHD symptoms?",
        "max_recommendations": 3,
    }


@pytest.fixture
def sample_evidence_source() -> dict[str, Any]:
    """Create a sample evidence source data."""
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
    """Create sample coaching guidance data."""
    return {
        "title": "Visual Transition Supports",
        "content": "Use visual schedules and countdown timers to support "
        "smooth transitions between activities.",
        "category": "behavior_management",
        "special_need_types": ["autism"],
        "priority": "high",
        "target_audience": "educator",
    }


SAFETY_DISCLAIMER = (
    "This guidance is for informational purposes only and does not "
    "constitute professional medical, therapeutic, or educational advice. "
    "Always consult with qualified professionals for specific recommendations."
)


@pytest.fixture
def safety_disclaimer() -> str:
    """Return the expected safety disclaimer text."""
    return SAFETY_DISCLAIMER


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
    """Return list of medical keywords for detection testing."""
    return MEDICAL_KEYWORDS


# ============================================================================
# Activity fixtures
# ============================================================================


@pytest.fixture
def sample_child_id_activity() -> UUID:
    """Fixture for a sample child UUID used in activity tests."""
    return uuid4()


@pytest.fixture
def sample_child_id(sample_child_id_activity: UUID) -> UUID:
    """Alias for sample_child_id_activity for backward compatibility."""
    return sample_child_id_activity


@pytest.fixture
def sample_activity_data() -> Dict[str, Any]:
    """Fixture for sample activity data for creating test activities."""
    return {
        "name": "Building Blocks Tower",
        "description": "Stack colorful blocks to build a tall tower. Great for motor skills and spatial awareness.",
        "activity_type": ActivityType.MOTOR,
        "difficulty": ActivityDifficulty.EASY,
        "duration_minutes": 20,
        "materials_needed": ["wooden blocks", "flat surface"],
        "min_age_months": 12,
        "max_age_months": 48,
        "special_needs_adaptations": "Use larger blocks for children with fine motor challenges.",
        "is_active": True,
    }


class MockActivity:
    """Mock Activity object for testing without SQLAlchemy ORM overhead."""

    def __init__(self, id, name, description, activity_type, difficulty,
                 duration_minutes, materials_needed, min_age_months,
                 max_age_months, special_needs_adaptations, is_active,
                 created_at, updated_at):
        self.id = id
        self.name = name
        self.description = description
        self.activity_type = activity_type
        self.difficulty = difficulty
        self.duration_minutes = duration_minutes
        self.materials_needed = materials_needed
        self.min_age_months = min_age_months
        self.max_age_months = max_age_months
        self.special_needs_adaptations = special_needs_adaptations
        self.is_active = is_active
        self.created_at = created_at
        self.updated_at = updated_at

    def __repr__(self) -> str:
        return f"<Activity(id={self.id}, name='{self.name}', type={self.activity_type.value})>"


class MockActivityParticipation:
    """Mock ActivityParticipation object for testing."""

    def __init__(self, id, child_id, activity_id, started_at, completed_at,
                 duration_minutes, completion_status, engagement_score,
                 notes, created_at, updated_at):
        self.id = id
        self.child_id = child_id
        self.activity_id = activity_id
        self.started_at = started_at
        self.completed_at = completed_at
        self.duration_minutes = duration_minutes
        self.completion_status = completion_status
        self.engagement_score = engagement_score
        self.notes = notes
        self.created_at = created_at
        self.updated_at = updated_at

    def __repr__(self) -> str:
        return f"<ActivityParticipation(id={self.id}, child_id={self.child_id}, status={self.completion_status})>"


class MockActivityRecommendation:
    """Mock ActivityRecommendation object for testing."""

    def __init__(self, id, child_id, activity_id, relevance_score, reasoning,
                 is_dismissed, generated_at, created_at, updated_at):
        self.id = id
        self.child_id = child_id
        self.activity_id = activity_id
        self.relevance_score = relevance_score
        self.reasoning = reasoning
        self.is_dismissed = is_dismissed
        self.generated_at = generated_at
        self.created_at = created_at
        self.updated_at = updated_at

    def __repr__(self) -> str:
        return f"<ActivityRecommendation(id={self.id}, child_id={self.child_id}, score={self.relevance_score})>"


async def create_activity_in_db(
    session: AsyncSession,
    name: str,
    description: str,
    activity_type: ActivityType,
    difficulty: ActivityDifficulty = ActivityDifficulty.MEDIUM,
    duration_minutes: int = 30,
    materials_needed: Optional[List[str]] = None,
    min_age_months: Optional[int] = None,
    max_age_months: Optional[int] = None,
    special_needs_adaptations: Optional[str] = None,
    is_active: bool = True,
) -> MockActivity:
    """Helper function to create an activity directly in SQLite database."""
    import json

    activity_id = str(uuid4())
    materials_json = json.dumps(materials_needed or [])
    now = datetime.now(timezone.utc)

    await session.execute(
        text("""
            INSERT INTO activities (
                id, name, description, activity_type, difficulty,
                duration_minutes, materials_needed, min_age_months, max_age_months,
                special_needs_adaptations, is_active, created_at, updated_at
            ) VALUES (
                :id, :name, :description, :activity_type, :difficulty,
                :duration_minutes, :materials_needed, :min_age_months, :max_age_months,
                :special_needs_adaptations, :is_active, :created_at, :updated_at
            )
        """),
        {
            "id": activity_id,
            "name": name,
            "description": description,
            "activity_type": activity_type.name,
            "difficulty": difficulty.name,
            "duration_minutes": duration_minutes,
            "materials_needed": materials_json,
            "min_age_months": min_age_months,
            "max_age_months": max_age_months,
            "special_needs_adaptations": special_needs_adaptations,
            "is_active": 1 if is_active else 0,
            "created_at": now.isoformat(),
            "updated_at": now.isoformat(),
        }
    )
    await session.commit()

    return MockActivity(
        id=UUID(activity_id),
        name=name,
        description=description,
        activity_type=activity_type,
        difficulty=difficulty,
        duration_minutes=duration_minutes,
        materials_needed=materials_needed or [],
        min_age_months=min_age_months,
        max_age_months=max_age_months,
        special_needs_adaptations=special_needs_adaptations,
        is_active=is_active,
        created_at=now,
        updated_at=now,
    )


async def create_participation_in_db(
    session: AsyncSession,
    child_id: UUID,
    activity_id: UUID,
    duration_minutes: Optional[int] = None,
    completion_status: str = "started",
    engagement_score: Optional[float] = None,
    notes: Optional[str] = None,
) -> MockActivityParticipation:
    """Helper function to create a participation record in SQLite database."""
    participation_id = str(uuid4())
    now = datetime.now(timezone.utc)

    await session.execute(
        text("""
            INSERT INTO activity_participations (
                id, child_id, activity_id, started_at, duration_minutes,
                completion_status, engagement_score, notes, created_at, updated_at
            ) VALUES (
                :id, :child_id, :activity_id, :started_at, :duration_minutes,
                :completion_status, :engagement_score, :notes, :created_at, :updated_at
            )
        """),
        {
            "id": participation_id,
            "child_id": str(child_id),
            "activity_id": str(activity_id),
            "started_at": now.isoformat(),
            "duration_minutes": duration_minutes,
            "completion_status": completion_status,
            "engagement_score": engagement_score,
            "notes": notes,
            "created_at": now.isoformat(),
            "updated_at": now.isoformat(),
        }
    )
    await session.commit()

    return MockActivityParticipation(
        id=UUID(participation_id),
        child_id=child_id,
        activity_id=activity_id,
        started_at=now,
        completed_at=None,
        duration_minutes=duration_minutes,
        completion_status=completion_status,
        engagement_score=engagement_score,
        notes=notes,
        created_at=now,
        updated_at=now,
    )


async def create_recommendation_in_db(
    session: AsyncSession,
    child_id: UUID,
    activity_id: UUID,
    relevance_score: float,
    reasoning: Optional[str] = None,
    is_dismissed: bool = False,
) -> MockActivityRecommendation:
    """Helper function to create a recommendation record in SQLite database."""
    recommendation_id = str(uuid4())
    now = datetime.now(timezone.utc)

    await session.execute(
        text("""
            INSERT INTO activity_recommendations (
                id, child_id, activity_id, relevance_score, reasoning,
                is_dismissed, generated_at, created_at, updated_at
            ) VALUES (
                :id, :child_id, :activity_id, :relevance_score, :reasoning,
                :is_dismissed, :generated_at, :created_at, :updated_at
            )
        """),
        {
            "id": recommendation_id,
            "child_id": str(child_id),
            "activity_id": str(activity_id),
            "relevance_score": relevance_score,
            "reasoning": reasoning,
            "is_dismissed": 1 if is_dismissed else 0,
            "generated_at": now.isoformat(),
            "created_at": now.isoformat(),
            "updated_at": now.isoformat(),
        }
    )
    await session.commit()

    return MockActivityRecommendation(
        id=UUID(recommendation_id),
        child_id=child_id,
        activity_id=activity_id,
        relevance_score=relevance_score,
        reasoning=reasoning,
        is_dismissed=is_dismissed,
        generated_at=now,
        created_at=now,
        updated_at=now,
    )


@pytest_asyncio.fixture
async def sample_activity(
    db_session: AsyncSession,
    sample_activity_data: Dict[str, Any],
) -> MockActivity:
    """Create a single sample activity in the database."""
    return await create_activity_in_db(db_session, **sample_activity_data)


@pytest_asyncio.fixture
async def sample_activities(db_session: AsyncSession) -> List[MockActivity]:
    """Create multiple sample activities with varied properties."""
    activities_data = [
        {
            "name": "Building Blocks Tower",
            "description": "Stack blocks to build towers. Great for motor skills.",
            "activity_type": ActivityType.MOTOR,
            "difficulty": ActivityDifficulty.EASY,
            "duration_minutes": 20,
            "materials_needed": ["wooden blocks"],
            "min_age_months": 12,
            "max_age_months": 48,
            "special_needs_adaptations": "Use larger blocks for motor challenges.",
            "is_active": True,
        },
        {
            "name": "Story Time Circle",
            "description": "Group reading and discussion of age-appropriate stories.",
            "activity_type": ActivityType.LANGUAGE,
            "difficulty": ActivityDifficulty.EASY,
            "duration_minutes": 30,
            "materials_needed": ["picture books"],
            "min_age_months": 24,
            "max_age_months": 72,
            "special_needs_adaptations": "Use books with larger print and tactile elements.",
            "is_active": True,
        },
        {
            "name": "Finger Painting",
            "description": "Creative expression through finger painting with non-toxic paints.",
            "activity_type": ActivityType.CREATIVE,
            "difficulty": ActivityDifficulty.EASY,
            "duration_minutes": 45,
            "materials_needed": ["finger paints", "paper", "smocks"],
            "min_age_months": 18,
            "max_age_months": 60,
            "special_needs_adaptations": None,
            "is_active": True,
        },
        {
            "name": "Group Dance Party",
            "description": "Moving to music together, following simple dance moves.",
            "activity_type": ActivityType.SOCIAL,
            "difficulty": ActivityDifficulty.MEDIUM,
            "duration_minutes": 25,
            "materials_needed": ["music player", "open space"],
            "min_age_months": 24,
            "max_age_months": 96,
            "special_needs_adaptations": "Provide seated dance options.",
            "is_active": True,
        },
        {
            "name": "Counting Games",
            "description": "Fun games to practice counting and number recognition.",
            "activity_type": ActivityType.COGNITIVE,
            "difficulty": ActivityDifficulty.MEDIUM,
            "duration_minutes": 20,
            "materials_needed": ["counting toys", "number cards"],
            "min_age_months": 36,
            "max_age_months": 72,
            "special_needs_adaptations": "Use manipulatives for tactile learning.",
            "is_active": True,
        },
        {
            "name": "Sensory Bin Exploration",
            "description": "Exploring different textures and materials in sensory bins.",
            "activity_type": ActivityType.SENSORY,
            "difficulty": ActivityDifficulty.EASY,
            "duration_minutes": 30,
            "materials_needed": ["sensory bin", "rice", "beans", "scoops"],
            "min_age_months": 12,
            "max_age_months": 48,
            "special_needs_adaptations": "Avoid small items for children who mouth objects.",
            "is_active": True,
        },
        {
            "name": "Advanced Puzzle Challenge",
            "description": "Complex puzzles for older children to solve.",
            "activity_type": ActivityType.COGNITIVE,
            "difficulty": ActivityDifficulty.HARD,
            "duration_minutes": 40,
            "materials_needed": ["50-100 piece puzzles"],
            "min_age_months": 60,
            "max_age_months": 144,
            "special_needs_adaptations": "Provide puzzles with larger pieces and fewer total pieces.",
            "is_active": True,
        },
        {
            "name": "Inactive Test Activity",
            "description": "This activity is marked as inactive for testing.",
            "activity_type": ActivityType.MOTOR,
            "difficulty": ActivityDifficulty.EASY,
            "duration_minutes": 15,
            "materials_needed": [],
            "min_age_months": 0,
            "max_age_months": 144,
            "special_needs_adaptations": None,
            "is_active": False,
        },
    ]

    activities = []
    for data in activities_data:
        activity = await create_activity_in_db(db_session, **data)
        activities.append(activity)

    return activities


@pytest_asyncio.fixture
async def sample_participation(
    db_session: AsyncSession,
    sample_activity: MockActivity,
    test_child_id: UUID,
) -> MockActivityParticipation:
    """Create a sample participation record."""
    return await create_participation_in_db(
        db_session,
        child_id=test_child_id,
        activity_id=sample_activity.id,
        duration_minutes=15,
        completion_status="completed",
        engagement_score=0.85,
        notes="Child enjoyed the activity",
    )


@pytest_asyncio.fixture
async def sample_recommendation(
    db_session: AsyncSession,
    sample_activity: MockActivity,
    test_child_id: UUID,
) -> MockActivityRecommendation:
    """Create a sample recommendation record."""
    return await create_recommendation_in_db(
        db_session,
        child_id=test_child_id,
        activity_id=sample_activity.id,
        relevance_score=0.85,
        reasoning="Age-appropriate and matches developmental needs",
        is_dismissed=False,
    )


# Age-specific fixtures for testing age filtering

@pytest.fixture
def infant_age_months() -> int:
    """Infant age (6 months)."""
    return 6


@pytest.fixture
def toddler_age_months() -> int:
    """Toddler age (24 months)."""
    return 24


@pytest.fixture
def preschool_age_months() -> int:
    """Preschool age (48 months / 4 years)."""
    return 48


@pytest.fixture
def school_age_months() -> int:
    """School age (84 months / 7 years)."""
    return 84


# Weather fixtures for testing weather-based filtering

@pytest.fixture
def sunny_weather() -> str:
    """Sunny weather condition."""
    return "sunny"


@pytest.fixture
def rainy_weather() -> str:
    """Rainy weather condition."""
    return "rainy"


# ============================================================================
# Communication fixtures
# ============================================================================


class MockParentReport:
    """Mock ParentReport object for testing without SQLAlchemy ORM overhead."""

    def __init__(
        self,
        id,
        child_id,
        report_date,
        language,
        summary,
        activities_summary,
        mood_summary,
        meals_summary,
        milestones,
        educator_notes,
        generated_by,
        created_at,
        updated_at,
    ):
        self.id = id
        self.child_id = child_id
        self.report_date = report_date
        self.language = language
        self.summary = summary
        self.activities_summary = activities_summary
        self.mood_summary = mood_summary
        self.meals_summary = meals_summary
        self.milestones = milestones
        self.educator_notes = educator_notes
        self.generated_by = generated_by
        self.created_at = created_at
        self.updated_at = updated_at

    def __repr__(self) -> str:
        return f"<ParentReport(id={self.id}, child_id={self.child_id}, date={self.report_date})>"


class MockHomeActivity:
    """Mock HomeActivity object for testing without SQLAlchemy ORM overhead."""

    def __init__(
        self,
        id,
        child_id,
        activity_name,
        activity_description,
        materials_needed,
        estimated_duration_minutes,
        developmental_area,
        language,
        based_on_activity_id,
        is_completed,
        created_at,
        updated_at,
    ):
        self.id = id
        self.child_id = child_id
        self.activity_name = activity_name
        self.activity_description = activity_description
        self.materials_needed = materials_needed
        self.estimated_duration_minutes = estimated_duration_minutes
        self.developmental_area = developmental_area
        self.language = language
        self.based_on_activity_id = based_on_activity_id
        self.is_completed = is_completed
        self.created_at = created_at
        self.updated_at = updated_at

    def __repr__(self) -> str:
        return f"<HomeActivity(id={self.id}, name='{self.activity_name}', language={self.language})>"


class MockCommunicationPreference:
    """Mock CommunicationPreference object for testing without SQLAlchemy ORM overhead."""

    def __init__(
        self,
        id,
        parent_id,
        child_id,
        preferred_language,
        report_frequency,
        created_at,
        updated_at,
    ):
        self.id = id
        self.parent_id = parent_id
        self.child_id = child_id
        self.preferred_language = preferred_language
        self.report_frequency = report_frequency
        self.created_at = created_at
        self.updated_at = updated_at

    def __repr__(self) -> str:
        return f"<CommunicationPreference(id={self.id}, parent_id={self.parent_id}, language={self.preferred_language})>"


async def create_parent_report_in_db(
    session: AsyncSession,
    child_id: UUID,
    report_date: datetime,
    generated_by: UUID,
    language: str = "en",
    summary: str = "Today was a great day!",
    activities_summary: Optional[str] = None,
    mood_summary: Optional[str] = None,
    meals_summary: Optional[str] = None,
    milestones: Optional[str] = None,
    educator_notes: Optional[str] = None,
) -> MockParentReport:
    """Helper function to create a parent report directly in SQLite database."""
    report_id = str(uuid4())
    now = datetime.now(timezone.utc)

    await session.execute(
        text("""
            INSERT INTO parent_reports (
                id, child_id, report_date, language, summary, activities_summary,
                mood_summary, meals_summary, milestones, educator_notes,
                generated_by, created_at, updated_at
            ) VALUES (
                :id, :child_id, :report_date, :language, :summary, :activities_summary,
                :mood_summary, :meals_summary, :milestones, :educator_notes,
                :generated_by, :created_at, :updated_at
            )
        """),
        {
            "id": report_id,
            "child_id": str(child_id),
            "report_date": report_date.strftime("%Y-%m-%d") if hasattr(report_date, 'strftime') else str(report_date),
            "language": language,
            "summary": summary,
            "activities_summary": activities_summary,
            "mood_summary": mood_summary,
            "meals_summary": meals_summary,
            "milestones": milestones,
            "educator_notes": educator_notes,
            "generated_by": str(generated_by),
            "created_at": now.isoformat(),
            "updated_at": now.isoformat(),
        }
    )
    await session.commit()

    return MockParentReport(
        id=UUID(report_id),
        child_id=child_id,
        report_date=report_date,
        language=language,
        summary=summary,
        activities_summary=activities_summary,
        mood_summary=mood_summary,
        meals_summary=meals_summary,
        milestones=milestones,
        educator_notes=educator_notes,
        generated_by=generated_by,
        created_at=now,
        updated_at=now,
    )


async def create_home_activity_in_db(
    session: AsyncSession,
    child_id: UUID,
    activity_name: str,
    activity_description: str,
    language: str = "en",
    materials_needed: Optional[List[str]] = None,
    estimated_duration_minutes: Optional[int] = None,
    developmental_area: Optional[str] = None,
    based_on_activity_id: Optional[UUID] = None,
    is_completed: bool = False,
) -> MockHomeActivity:
    """Helper function to create a home activity directly in SQLite database."""
    import json

    activity_id = str(uuid4())
    materials_json = json.dumps(materials_needed or [])
    now = datetime.now(timezone.utc)

    await session.execute(
        text("""
            INSERT INTO home_activities (
                id, child_id, activity_name, activity_description, materials_needed,
                estimated_duration_minutes, developmental_area, language,
                based_on_activity_id, is_completed, created_at, updated_at
            ) VALUES (
                :id, :child_id, :activity_name, :activity_description, :materials_needed,
                :estimated_duration_minutes, :developmental_area, :language,
                :based_on_activity_id, :is_completed, :created_at, :updated_at
            )
        """),
        {
            "id": activity_id,
            "child_id": str(child_id),
            "activity_name": activity_name,
            "activity_description": activity_description,
            "materials_needed": materials_json,
            "estimated_duration_minutes": estimated_duration_minutes,
            "developmental_area": developmental_area,
            "language": language,
            "based_on_activity_id": str(based_on_activity_id) if based_on_activity_id else None,
            "is_completed": 1 if is_completed else 0,
            "created_at": now.isoformat(),
            "updated_at": now.isoformat(),
        }
    )
    await session.commit()

    return MockHomeActivity(
        id=UUID(activity_id),
        child_id=child_id,
        activity_name=activity_name,
        activity_description=activity_description,
        materials_needed=materials_needed or [],
        estimated_duration_minutes=estimated_duration_minutes,
        developmental_area=developmental_area,
        language=language,
        based_on_activity_id=based_on_activity_id,
        is_completed=is_completed,
        created_at=now,
        updated_at=now,
    )


async def create_communication_preference_in_db(
    session: AsyncSession,
    parent_id: UUID,
    child_id: UUID,
    preferred_language: str = "en",
    report_frequency: str = "daily",
) -> MockCommunicationPreference:
    """Helper function to create a communication preference directly in SQLite database."""
    pref_id = str(uuid4())
    now = datetime.now(timezone.utc)

    await session.execute(
        text("""
            INSERT INTO communication_preferences (
                id, parent_id, child_id, preferred_language, report_frequency,
                created_at, updated_at
            ) VALUES (
                :id, :parent_id, :child_id, :preferred_language, :report_frequency,
                :created_at, :updated_at
            )
        """),
        {
            "id": pref_id,
            "parent_id": str(parent_id),
            "child_id": str(child_id),
            "preferred_language": preferred_language,
            "report_frequency": report_frequency,
            "created_at": now.isoformat(),
            "updated_at": now.isoformat(),
        }
    )
    await session.commit()

    return MockCommunicationPreference(
        id=UUID(pref_id),
        parent_id=parent_id,
        child_id=child_id,
        preferred_language=preferred_language,
        report_frequency=report_frequency,
        created_at=now,
        updated_at=now,
    )


@pytest.fixture
def test_parent_id() -> UUID:
    """Generate a consistent test parent ID."""
    return UUID("aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee")


@pytest.fixture
def sample_report_request(test_child_id: UUID) -> Dict[str, Any]:
    """Create a sample report generation request in English."""
    return {
        "child_id": str(test_child_id),
        "report_date": datetime.now(timezone.utc).strftime("%Y-%m-%d"),
        "language": "en",
        "educator_notes": "Great day overall!",
    }


@pytest.fixture
def sample_french_report_request(test_child_id: UUID) -> Dict[str, Any]:
    """Create a sample report generation request in French."""
    return {
        "child_id": str(test_child_id),
        "report_date": datetime.now(timezone.utc).strftime("%Y-%m-%d"),
        "language": "fr",
        "educator_notes": "Une excellente journée!",
    }


@pytest.fixture
def sample_home_activity_data(test_child_id: UUID) -> Dict[str, Any]:
    """Create sample home activity data for testing."""
    return {
        "child_id": test_child_id,
        "activity_name": "Building Blocks at Home",
        "activity_description": "Continue developing motor skills by building towers with blocks at home.",
        "materials_needed": ["wooden blocks", "flat surface"],
        "estimated_duration_minutes": 20,
        "developmental_area": "motor",
        "language": "en",
        "is_completed": False,
    }


@pytest.fixture
def sample_french_home_activity_data(test_child_id: UUID) -> Dict[str, Any]:
    """Create sample home activity data in French for testing."""
    return {
        "child_id": test_child_id,
        "activity_name": "Construction de blocs à la maison",
        "activity_description": "Continuez à développer la motricité fine en construisant des tours avec des blocs à la maison.",
        "materials_needed": ["blocs en bois", "surface plane"],
        "estimated_duration_minutes": 20,
        "developmental_area": "motor",
        "language": "fr",
        "is_completed": False,
    }


@pytest.fixture
def sample_communication_preference_request(
    test_parent_id: UUID,
    test_child_id: UUID,
) -> Dict[str, Any]:
    """Create a sample communication preference request."""
    return {
        "parent_id": str(test_parent_id),
        "child_id": str(test_child_id),
        "preferred_language": "en",
        "report_frequency": "daily",
    }


@pytest.fixture
def sample_french_communication_preference_request(
    test_parent_id: UUID,
    test_child_id: UUID,
) -> Dict[str, Any]:
    """Create a sample French communication preference request."""
    return {
        "parent_id": str(test_parent_id),
        "child_id": str(test_child_id),
        "preferred_language": "fr",
        "report_frequency": "daily",
    }


@pytest_asyncio.fixture
async def sample_parent_report(
    db_session: AsyncSession,
    test_child_id: UUID,
    test_user_id: UUID,
) -> MockParentReport:
    """Create a sample parent report in the database."""
    return await create_parent_report_in_db(
        db_session,
        child_id=test_child_id,
        report_date=datetime.now(timezone.utc),
        generated_by=test_user_id,
        language="en",
        summary="Today was a wonderful day at the daycare! Your child participated in several engaging activities.",
        activities_summary="Participated in building blocks, story time, and outdoor play.",
        mood_summary="Happy and energetic throughout the day.",
        meals_summary="Ate well at both snack time and lunch.",
        milestones="Showed improved fine motor skills while stacking blocks.",
        educator_notes="Great progress this week!",
    )


@pytest_asyncio.fixture
async def sample_french_parent_report(
    db_session: AsyncSession,
    test_child_id: UUID,
    test_user_id: UUID,
) -> MockParentReport:
    """Create a sample French parent report in the database."""
    return await create_parent_report_in_db(
        db_session,
        child_id=test_child_id,
        report_date=datetime.now(timezone.utc),
        generated_by=test_user_id,
        language="fr",
        summary="Aujourd'hui a été une merveilleuse journée à la garderie! Votre enfant a participé à plusieurs activités enrichissantes.",
        activities_summary="A participé aux blocs de construction, à l'heure du conte et aux jeux extérieurs.",
        mood_summary="Joyeux et énergique tout au long de la journée.",
        meals_summary="A bien mangé lors de la collation et du déjeuner.",
        milestones="A montré une amélioration de la motricité fine en empilant des blocs.",
        educator_notes="Excellents progrès cette semaine!",
    )


@pytest_asyncio.fixture
async def sample_home_activity(
    db_session: AsyncSession,
    test_child_id: UUID,
) -> MockHomeActivity:
    """Create a sample home activity in the database."""
    return await create_home_activity_in_db(
        db_session,
        child_id=test_child_id,
        activity_name="Building Blocks at Home",
        activity_description="Continue developing motor skills by building towers with blocks at home. Start with 3-4 blocks and gradually increase.",
        materials_needed=["wooden blocks", "flat surface"],
        estimated_duration_minutes=20,
        developmental_area="motor",
        language="en",
        is_completed=False,
    )


@pytest_asyncio.fixture
async def sample_french_home_activity(
    db_session: AsyncSession,
    test_child_id: UUID,
) -> MockHomeActivity:
    """Create a sample French home activity in the database."""
    return await create_home_activity_in_db(
        db_session,
        child_id=test_child_id,
        activity_name="Construction de blocs à la maison",
        activity_description="Continuez à développer la motricité fine en construisant des tours avec des blocs. Commencez avec 3-4 blocs et augmentez progressivement.",
        materials_needed=["blocs en bois", "surface plane"],
        estimated_duration_minutes=20,
        developmental_area="motor",
        language="fr",
        is_completed=False,
    )


@pytest_asyncio.fixture
async def sample_communication_preference(
    db_session: AsyncSession,
    test_parent_id: UUID,
    test_child_id: UUID,
) -> MockCommunicationPreference:
    """Create a sample communication preference in the database."""
    return await create_communication_preference_in_db(
        db_session,
        parent_id=test_parent_id,
        child_id=test_child_id,
        preferred_language="en",
        report_frequency="daily",
    )


@pytest_asyncio.fixture
async def sample_french_communication_preference(
    db_session: AsyncSession,
    test_parent_id: UUID,
    test_child_id: UUID,
) -> MockCommunicationPreference:
    """Create a sample French communication preference in the database.

    Note: Uses a different parent_id to avoid unique constraint violation
    with sample_communication_preference fixture.
    """
    french_parent_id = UUID("ffffffff-ffff-ffff-ffff-ffffffffffff")
    return await create_communication_preference_in_db(
        db_session,
        parent_id=french_parent_id,
        child_id=test_child_id,
        preferred_language="fr",
        report_frequency="daily",
    )


@pytest_asyncio.fixture
async def sample_home_activities(
    db_session: AsyncSession,
    test_child_id: UUID,
) -> List[MockHomeActivity]:
    """Create multiple sample home activities with varied properties."""
    activities_data = [
        {
            "activity_name": "Building Blocks at Home",
            "activity_description": "Continue motor skills development by building towers.",
            "materials_needed": ["wooden blocks", "flat surface"],
            "estimated_duration_minutes": 20,
            "developmental_area": "motor",
            "language": "en",
        },
        {
            "activity_name": "Story Time Together",
            "activity_description": "Read age-appropriate stories together and discuss the pictures.",
            "materials_needed": ["picture books"],
            "estimated_duration_minutes": 15,
            "developmental_area": "language",
            "language": "en",
        },
        {
            "activity_name": "Sensory Play",
            "activity_description": "Explore different textures with rice or sand in a bin.",
            "materials_needed": ["bin", "rice or sand", "scoops"],
            "estimated_duration_minutes": 25,
            "developmental_area": "sensory",
            "language": "en",
        },
        {
            "activity_name": "Counting Games",
            "activity_description": "Practice counting objects around the house.",
            "materials_needed": ["toys", "snacks for counting"],
            "estimated_duration_minutes": 10,
            "developmental_area": "cognitive",
            "language": "en",
        },
        {
            "activity_name": "Dance Party",
            "activity_description": "Put on music and dance together for fun exercise.",
            "materials_needed": ["music player"],
            "estimated_duration_minutes": 15,
            "developmental_area": "social",
            "language": "en",
        },
    ]

    activities = []
    for data in activities_data:
        activity = await create_home_activity_in_db(
            db_session,
            child_id=test_child_id,
            **data,
        )
        activities.append(activity)

    return activities
