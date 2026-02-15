"""Pytest fixtures and test configuration for LAYA AI Service.

Provides reusable fixtures for async testing, database sessions,
test data, and authentication mocking.
"""

from datetime import datetime, timezone
from typing import AsyncGenerator, Dict, List, Optional, Any
from uuid import UUID, uuid4

import jwt
import pytest
import pytest_asyncio
from httpx import ASGITransport, AsyncClient
from sqlalchemy import StaticPool, String, JSON, event, Column
from sqlalchemy.ext.asyncio import AsyncSession, create_async_engine
from sqlalchemy.orm import sessionmaker
from sqlalchemy.dialects.postgresql import ARRAY

from app.config import settings
from app.database import get_db
from app.main import app
from app.models.activity import (
    Activity,
    ActivityDifficulty,
    ActivityParticipation,
    ActivityRecommendation,
    ActivityType,
    Base,
)


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


# Test database URL - use SQLite with aiosqlite for testing
TEST_DATABASE_URL = "sqlite+aiosqlite:///:memory:"

# Create async engine for testing with in-memory SQLite
test_engine = create_async_engine(
    TEST_DATABASE_URL,
    echo=False,
    connect_args={"check_same_thread": False},
    poolclass=StaticPool,
)


# Override the ARRAY type to use JSON for SQLite compatibility
# This is done at the dialect level before table creation
@event.listens_for(test_engine.sync_engine, "before_cursor_execute", retval=True)
def receive_before_cursor_execute(conn, cursor, statement, parameters, context, executemany):
    """Intercept SQL statements to handle ARRAY type for SQLite."""
    # SQLite doesn't need special handling at this level since we'll create
    # custom tables
    return statement, parameters


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


# Create SQLite-compatible test tables using raw SQL
SQLITE_CREATE_TABLES_SQL = """
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


@pytest_asyncio.fixture
async def db_session() -> AsyncGenerator[AsyncSession, None]:
    """Fixture for creating a fresh database session for each test.

    Creates all tables before the test and drops them after.
    Each test gets an isolated database state.

    Yields:
        AsyncSession: Async database session for testing.
    """
    # Create tables using raw SQL for SQLite compatibility
    async with test_engine.begin() as conn:
        for statement in SQLITE_CREATE_TABLES_SQL.strip().split(';'):
            statement = statement.strip()
            if statement:
                await conn.execute(
                    __import__('sqlalchemy').text(statement)
                )

    async with TestAsyncSessionLocal() as session:
        try:
            yield session
        finally:
            await session.rollback()

    # Drop all tables after test
    async with test_engine.begin() as conn:
        await conn.execute(
            __import__('sqlalchemy').text("DROP TABLE IF EXISTS activity_participations")
        )
        await conn.execute(
            __import__('sqlalchemy').text("DROP TABLE IF EXISTS activity_recommendations")
        )
        await conn.execute(
            __import__('sqlalchemy').text("DROP TABLE IF EXISTS activities")
        )


@pytest_asyncio.fixture
async def client(db_session: AsyncSession) -> AsyncGenerator[AsyncClient, None]:
    """Fixture for creating an async HTTP test client.

    Overrides the database dependency to use the test database session.

    Args:
        db_session: Test database session fixture.

    Yields:
        AsyncClient: Async HTTP client for testing API endpoints.
    """
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


@pytest.fixture
def auth_token() -> str:
    """Fixture for creating a valid JWT token for authenticated requests.

    Returns:
        str: Valid JWT token for testing.
    """
    return create_test_token(
        subject="test-user-id",
        expires_delta_seconds=3600,
        additional_claims={
            "email": "test@example.com",
            "role": "teacher",
        },
    )


@pytest.fixture
def auth_headers(auth_token: str) -> Dict[str, str]:
    """Fixture for creating authorization headers with JWT token.

    Args:
        auth_token: Valid JWT token fixture.

    Returns:
        dict: Headers dict with Authorization Bearer token.
    """
    return {"Authorization": f"Bearer {auth_token}"}


@pytest.fixture
def sample_child_id() -> UUID:
    """Fixture for a sample child UUID used in tests.

    Returns:
        UUID: Sample child identifier.
    """
    return uuid4()


@pytest.fixture
def sample_activity_data() -> Dict[str, Any]:
    """Fixture for sample activity data for creating test activities.

    Returns:
        dict: Dictionary with activity field values.
    """
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

    def __init__(
        self,
        id: UUID,
        name: str,
        description: str,
        activity_type: ActivityType,
        difficulty: ActivityDifficulty,
        duration_minutes: int,
        materials_needed: List[str],
        min_age_months: Optional[int],
        max_age_months: Optional[int],
        special_needs_adaptations: Optional[str],
        is_active: bool,
        created_at: datetime,
        updated_at: datetime,
    ):
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

    def __init__(
        self,
        id: UUID,
        child_id: UUID,
        activity_id: UUID,
        started_at: datetime,
        completed_at: Optional[datetime],
        duration_minutes: Optional[int],
        completion_status: str,
        engagement_score: Optional[float],
        notes: Optional[str],
        created_at: datetime,
        updated_at: datetime,
    ):
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
        return (
            f"<ActivityParticipation(id={self.id}, child_id={self.child_id}, "
            f"activity_id={self.activity_id}, status={self.completion_status})>"
        )


class MockActivityRecommendation:
    """Mock ActivityRecommendation object for testing."""

    def __init__(
        self,
        id: UUID,
        child_id: UUID,
        activity_id: UUID,
        relevance_score: float,
        reasoning: Optional[str],
        is_dismissed: bool,
        generated_at: datetime,
        created_at: datetime,
        updated_at: datetime,
    ):
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
        return (
            f"<ActivityRecommendation(id={self.id}, child_id={self.child_id}, "
            f"activity_id={self.activity_id}, score={self.relevance_score})>"
        )


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
    """Helper function to create an activity directly in SQLite database.

    Returns:
        MockActivity: Created activity mock instance.
    """
    import json
    from sqlalchemy import text

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
            "activity_type": activity_type.value,
            "difficulty": difficulty.value,
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


@pytest_asyncio.fixture
async def sample_activity(
    db_session: AsyncSession,
    sample_activity_data: Dict[str, Any],
) -> MockActivity:
    """Fixture for creating a single sample activity in the database.

    Args:
        db_session: Test database session.
        sample_activity_data: Sample activity field values.

    Returns:
        Activity: Created activity instance.
    """
    return await create_activity_in_db(
        db_session,
        **sample_activity_data,
    )


@pytest_asyncio.fixture
async def sample_activities(db_session: AsyncSession) -> List[MockActivity]:
    """Fixture for creating multiple sample activities with varied properties.

    Creates activities spanning different types, difficulties, and age ranges
    for comprehensive testing of filtering and recommendation logic.

    Args:
        db_session: Test database session.

    Returns:
        list[Activity]: List of created activity instances.
    """
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
    from sqlalchemy import text

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
    from sqlalchemy import text

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
async def sample_participation(
    db_session: AsyncSession,
    sample_activity: MockActivity,
    sample_child_id: UUID,
) -> MockActivityParticipation:
    """Fixture for creating a sample participation record.

    Args:
        db_session: Test database session.
        sample_activity: Sample activity fixture.
        sample_child_id: Sample child UUID fixture.

    Returns:
        ActivityParticipation: Created participation record.
    """
    return await create_participation_in_db(
        db_session,
        child_id=sample_child_id,
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
    sample_child_id: UUID,
) -> MockActivityRecommendation:
    """Fixture for creating a sample recommendation record.

    Args:
        db_session: Test database session.
        sample_activity: Sample activity fixture.
        sample_child_id: Sample child UUID fixture.

    Returns:
        ActivityRecommendation: Created recommendation record.
    """
    return await create_recommendation_in_db(
        db_session,
        child_id=sample_child_id,
        activity_id=sample_activity.id,
        relevance_score=0.85,
        reasoning="Age-appropriate and matches developmental needs",
        is_dismissed=False,
    )


# Age-specific fixtures for testing age filtering


@pytest.fixture
def infant_age_months() -> int:
    """Fixture for infant age (6 months).

    Returns:
        int: Age in months representing an infant.
    """
    return 6


@pytest.fixture
def toddler_age_months() -> int:
    """Fixture for toddler age (24 months).

    Returns:
        int: Age in months representing a toddler.
    """
    return 24


@pytest.fixture
def preschool_age_months() -> int:
    """Fixture for preschool age (48 months / 4 years).

    Returns:
        int: Age in months representing a preschooler.
    """
    return 48


@pytest.fixture
def school_age_months() -> int:
    """Fixture for school age (84 months / 7 years).

    Returns:
        int: Age in months representing a school-age child.
    """
    return 84


# Weather fixtures for testing weather-based filtering


@pytest.fixture
def sunny_weather() -> str:
    """Fixture for sunny weather condition.

    Returns:
        str: Weather string.
    """
    return "sunny"


@pytest.fixture
def rainy_weather() -> str:
    """Fixture for rainy weather condition.

    Returns:
        str: Weather string.
    """
    return "rainy"
