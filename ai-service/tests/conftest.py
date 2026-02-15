"""Pytest fixtures and test configuration for LAYA AI Service.

Provides reusable fixtures for async testing, database sessions,
test data, and authentication mocking.
"""

from datetime import datetime, timezone
from typing import AsyncGenerator, Generator
from uuid import UUID, uuid4

import pytest
import pytest_asyncio
from httpx import ASGITransport, AsyncClient
from sqlalchemy import StaticPool, event
from sqlalchemy.ext.asyncio import AsyncSession, create_async_engine
from sqlalchemy.orm import sessionmaker

from app.auth import create_token
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


# Test database URL - use SQLite with aiosqlite for testing
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


# Configure pytest-asyncio
pytest_plugins = ("pytest_asyncio",)


@pytest_asyncio.fixture
async def db_session() -> AsyncGenerator[AsyncSession, None]:
    """Fixture for creating a fresh database session for each test.

    Creates all tables before the test and drops them after.
    Each test gets an isolated database state.

    Yields:
        AsyncSession: Async database session for testing.
    """
    # Create all tables
    async with test_engine.begin() as conn:
        await conn.run_sync(Base.metadata.create_all)

    async with TestAsyncSessionLocal() as session:
        try:
            yield session
        finally:
            await session.rollback()

    # Drop all tables after test
    async with test_engine.begin() as conn:
        await conn.run_sync(Base.metadata.drop_all)


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
    return create_token(
        subject="test-user-id",
        expires_delta_seconds=3600,
        additional_claims={
            "email": "test@example.com",
            "role": "teacher",
        },
    )


@pytest.fixture
def auth_headers(auth_token: str) -> dict[str, str]:
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
def sample_activity_data() -> dict:
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


@pytest_asyncio.fixture
async def sample_activity(
    db_session: AsyncSession,
    sample_activity_data: dict,
) -> Activity:
    """Fixture for creating a single sample activity in the database.

    Args:
        db_session: Test database session.
        sample_activity_data: Sample activity field values.

    Returns:
        Activity: Created activity instance.
    """
    activity = Activity(**sample_activity_data)
    db_session.add(activity)
    await db_session.commit()
    await db_session.refresh(activity)
    return activity


@pytest_asyncio.fixture
async def sample_activities(db_session: AsyncSession) -> list[Activity]:
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
        activity = Activity(**data)
        db_session.add(activity)
        activities.append(activity)

    await db_session.commit()
    for activity in activities:
        await db_session.refresh(activity)

    return activities


@pytest_asyncio.fixture
async def sample_participation(
    db_session: AsyncSession,
    sample_activity: Activity,
    sample_child_id: UUID,
) -> ActivityParticipation:
    """Fixture for creating a sample participation record.

    Args:
        db_session: Test database session.
        sample_activity: Sample activity fixture.
        sample_child_id: Sample child UUID fixture.

    Returns:
        ActivityParticipation: Created participation record.
    """
    participation = ActivityParticipation(
        child_id=sample_child_id,
        activity_id=sample_activity.id,
        duration_minutes=15,
        completion_status="completed",
        engagement_score=0.85,
        notes="Child enjoyed the activity",
    )
    db_session.add(participation)
    await db_session.commit()
    await db_session.refresh(participation)
    return participation


@pytest_asyncio.fixture
async def sample_recommendation(
    db_session: AsyncSession,
    sample_activity: Activity,
    sample_child_id: UUID,
) -> ActivityRecommendation:
    """Fixture for creating a sample recommendation record.

    Args:
        db_session: Test database session.
        sample_activity: Sample activity fixture.
        sample_child_id: Sample child UUID fixture.

    Returns:
        ActivityRecommendation: Created recommendation record.
    """
    recommendation = ActivityRecommendation(
        child_id=sample_child_id,
        activity_id=sample_activity.id,
        relevance_score=0.85,
        reasoning="Age-appropriate and matches developmental needs",
        is_dismissed=False,
    )
    db_session.add(recommendation)
    await db_session.commit()
    await db_session.refresh(recommendation)
    return recommendation


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
