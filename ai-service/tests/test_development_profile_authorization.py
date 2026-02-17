"""Authorization tests for Development Profile service.

Tests verify that users can only access development profiles, assessments,
observations, and snapshots they are authorized to access (as the assigned educator).

Tests cover:
- Profile access authorization (get, update, delete)
- Skill assessment access authorization (get, update)
- Observation access authorization (get, update)
- Monthly snapshot access authorization (get, update)
- Growth trajectory access authorization
- IDOR vulnerability prevention
"""

from __future__ import annotations

from datetime import date, datetime, timezone
from typing import Any, Dict, List, Optional
from uuid import UUID, uuid4

import pytest
import pytest_asyncio
from sqlalchemy import text
from sqlalchemy.ext.asyncio import AsyncSession

from app.models.development_profile import (
    DevelopmentalDomain,
    SkillStatus,
)
from app.schemas.development_profile import (
    DevelopmentalDomain as DevelopmentalDomainSchema,
    DevelopmentProfileRequest,
    MonthlySnapshotRequest,
    MonthlySnapshotUpdateRequest,
    ObservationRequest,
    ObservationUpdateRequest,
    ObserverType,
    OverallProgress,
    SkillAssessmentRequest,
    SkillAssessmentUpdateRequest,
    SkillStatus as SkillStatusSchema,
)
from app.services.development_profile_service import (
    DevelopmentProfileService,
    UnauthorizedAccessError,
)


# =============================================================================
# SQLite Table Creation for Tests
# =============================================================================


async def ensure_development_profile_tables(session: AsyncSession) -> None:
    """Create development profile tables in SQLite test database."""
    # Create development_profiles table
    await session.execute(
        text("""
            CREATE TABLE IF NOT EXISTS development_profiles (
                id TEXT PRIMARY KEY,
                child_id TEXT NOT NULL,
                educator_id TEXT NOT NULL,
                birth_date TEXT,
                notes TEXT,
                is_active INTEGER DEFAULT 1,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )
        """)
    )

    # Create skill_assessments table
    await session.execute(
        text("""
            CREATE TABLE IF NOT EXISTS skill_assessments (
                id TEXT PRIMARY KEY,
                profile_id TEXT NOT NULL,
                domain TEXT NOT NULL,
                skill_name TEXT NOT NULL,
                skill_name_fr TEXT,
                status TEXT NOT NULL,
                assessed_at TEXT NOT NULL,
                assessed_by_id TEXT,
                evidence TEXT,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                FOREIGN KEY (profile_id) REFERENCES development_profiles(id)
            )
        """)
    )

    # Create observations table
    await session.execute(
        text("""
            CREATE TABLE IF NOT EXISTS observations (
                id TEXT PRIMARY KEY,
                profile_id TEXT NOT NULL,
                domain TEXT NOT NULL,
                observed_at TEXT NOT NULL,
                observer_id TEXT,
                observer_type TEXT NOT NULL,
                behavior_description TEXT NOT NULL,
                context TEXT,
                is_milestone INTEGER DEFAULT 0,
                is_concern INTEGER DEFAULT 0,
                attachments TEXT,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                FOREIGN KEY (profile_id) REFERENCES development_profiles(id)
            )
        """)
    )

    # Create monthly_snapshots table
    await session.execute(
        text("""
            CREATE TABLE IF NOT EXISTS monthly_snapshots (
                id TEXT PRIMARY KEY,
                profile_id TEXT NOT NULL,
                snapshot_month TEXT NOT NULL,
                age_months INTEGER,
                domain_summaries TEXT,
                overall_progress TEXT NOT NULL,
                strengths TEXT,
                growth_areas TEXT,
                recommendations TEXT,
                generated_by_id TEXT,
                is_parent_shared INTEGER DEFAULT 0,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                FOREIGN KEY (profile_id) REFERENCES development_profiles(id)
            )
        """)
    )

    await session.commit()


# =============================================================================
# Helper Functions for Test Data Creation
# =============================================================================


async def create_profile_in_db(
    session: AsyncSession,
    child_id: UUID,
    educator_id: UUID,
    birth_date: Optional[date] = None,
    notes: Optional[str] = None,
    is_active: bool = True,
) -> UUID:
    """Helper function to create a development profile in SQLite database."""
    await ensure_development_profile_tables(session)

    profile_id = uuid4()
    now = datetime.now(timezone.utc)

    await session.execute(
        text("""
            INSERT INTO development_profiles (
                id, child_id, educator_id, birth_date, notes, is_active,
                created_at, updated_at
            ) VALUES (
                :id, :child_id, :educator_id, :birth_date, :notes, :is_active,
                :created_at, :updated_at
            )
        """),
        {
            "id": str(profile_id),
            "child_id": str(child_id),
            "educator_id": str(educator_id),
            "birth_date": birth_date.isoformat() if birth_date else None,
            "notes": notes,
            "is_active": 1 if is_active else 0,
            "created_at": now.isoformat(),
            "updated_at": now.isoformat(),
        },
    )
    await session.commit()

    return profile_id


async def create_skill_assessment_in_db(
    session: AsyncSession,
    profile_id: UUID,
    domain: str = "AFFECTIVE",
    skill_name: str = "Test Skill",
    status: str = "LEARNING",
    assessed_by_id: Optional[UUID] = None,
) -> UUID:
    """Helper function to create a skill assessment in SQLite database."""
    await ensure_development_profile_tables(session)

    assessment_id = uuid4()
    now = datetime.now(timezone.utc)

    await session.execute(
        text("""
            INSERT INTO skill_assessments (
                id, profile_id, domain, skill_name, skill_name_fr, status,
                assessed_at, assessed_by_id, evidence, created_at, updated_at
            ) VALUES (
                :id, :profile_id, :domain, :skill_name, :skill_name_fr, :status,
                :assessed_at, :assessed_by_id, :evidence, :created_at, :updated_at
            )
        """),
        {
            "id": str(assessment_id),
            "profile_id": str(profile_id),
            "domain": domain,
            "skill_name": skill_name,
            "skill_name_fr": None,
            "status": status,
            "assessed_at": now.isoformat(),
            "assessed_by_id": str(assessed_by_id) if assessed_by_id else None,
            "evidence": None,
            "created_at": now.isoformat(),
            "updated_at": now.isoformat(),
        },
    )
    await session.commit()

    return assessment_id


async def create_observation_in_db(
    session: AsyncSession,
    profile_id: UUID,
    domain: str = "SOCIAL",
    behavior_description: str = "Test observation",
    observer_id: Optional[UUID] = None,
    observer_type: str = "EDUCATOR",
) -> UUID:
    """Helper function to create an observation in SQLite database."""
    await ensure_development_profile_tables(session)

    observation_id = uuid4()
    now = datetime.now(timezone.utc)

    await session.execute(
        text("""
            INSERT INTO observations (
                id, profile_id, domain, observed_at, observer_id, observer_type,
                behavior_description, context, is_milestone, is_concern,
                attachments, created_at, updated_at
            ) VALUES (
                :id, :profile_id, :domain, :observed_at, :observer_id, :observer_type,
                :behavior_description, :context, :is_milestone, :is_concern,
                :attachments, :created_at, :updated_at
            )
        """),
        {
            "id": str(observation_id),
            "profile_id": str(profile_id),
            "domain": domain,
            "observed_at": now.isoformat(),
            "observer_id": str(observer_id) if observer_id else None,
            "observer_type": observer_type,
            "behavior_description": behavior_description,
            "context": None,
            "is_milestone": 0,
            "is_concern": 0,
            "attachments": None,
            "created_at": now.isoformat(),
            "updated_at": now.isoformat(),
        },
    )
    await session.commit()

    return observation_id


async def create_monthly_snapshot_in_db(
    session: AsyncSession,
    profile_id: UUID,
    snapshot_month: date,
    overall_progress: str = "on_track",
    generated_by_id: Optional[UUID] = None,
) -> UUID:
    """Helper function to create a monthly snapshot in SQLite database."""
    await ensure_development_profile_tables(session)

    snapshot_id = uuid4()
    now = datetime.now(timezone.utc)

    await session.execute(
        text("""
            INSERT INTO monthly_snapshots (
                id, profile_id, snapshot_month, age_months, domain_summaries,
                overall_progress, strengths, growth_areas, recommendations,
                generated_by_id, is_parent_shared, created_at, updated_at
            ) VALUES (
                :id, :profile_id, :snapshot_month, :age_months, :domain_summaries,
                :overall_progress, :strengths, :growth_areas, :recommendations,
                :generated_by_id, :is_parent_shared, :created_at, :updated_at
            )
        """),
        {
            "id": str(snapshot_id),
            "profile_id": str(profile_id),
            "snapshot_month": snapshot_month.isoformat(),
            "age_months": None,
            "domain_summaries": "{}",
            "overall_progress": overall_progress,
            "strengths": None,
            "growth_areas": None,
            "recommendations": None,
            "generated_by_id": str(generated_by_id) if generated_by_id else None,
            "is_parent_shared": 0,
            "created_at": now.isoformat(),
            "updated_at": now.isoformat(),
        },
    )
    await session.commit()

    return snapshot_id


# =============================================================================
# Fixtures
# =============================================================================


@pytest.fixture
def test_educator_id() -> UUID:
    """Generate a consistent test educator ID."""
    return UUID("aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa")


@pytest.fixture
def test_educator_id_2() -> UUID:
    """Generate a second test educator ID for authorization tests."""
    return UUID("bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb")


@pytest.fixture
def test_child_id_dev() -> UUID:
    """Generate a consistent test child ID for development profile tests."""
    return UUID("cccccccc-cccc-cccc-cccc-cccccccccccc")


@pytest_asyncio.fixture
async def sample_profile(
    db_session: AsyncSession,
    test_educator_id: UUID,
    test_child_id_dev: UUID,
) -> UUID:
    """Create a sample development profile owned by test_educator_id."""
    return await create_profile_in_db(
        db_session,
        child_id=test_child_id_dev,
        educator_id=test_educator_id,
        birth_date=date(2020, 1, 15),
        notes="Test profile",
        is_active=True,
    )


@pytest_asyncio.fixture
async def sample_assessment(
    db_session: AsyncSession,
    sample_profile: UUID,
    test_educator_id: UUID,
) -> UUID:
    """Create a sample skill assessment for the sample profile."""
    return await create_skill_assessment_in_db(
        db_session,
        profile_id=sample_profile,
        domain="AFFECTIVE",
        skill_name="Emotional regulation",
        status="LEARNING",
        assessed_by_id=test_educator_id,
    )


@pytest_asyncio.fixture
async def sample_observation(
    db_session: AsyncSession,
    sample_profile: UUID,
    test_educator_id: UUID,
) -> UUID:
    """Create a sample observation for the sample profile."""
    return await create_observation_in_db(
        db_session,
        profile_id=sample_profile,
        domain="SOCIAL",
        behavior_description="Shared toys with peers during playtime",
        observer_id=test_educator_id,
        observer_type="EDUCATOR",
    )


@pytest_asyncio.fixture
async def sample_snapshot(
    db_session: AsyncSession,
    sample_profile: UUID,
    test_educator_id: UUID,
) -> UUID:
    """Create a sample monthly snapshot for the sample profile."""
    return await create_monthly_snapshot_in_db(
        db_session,
        profile_id=sample_profile,
        snapshot_month=date(2024, 1, 1),
        overall_progress="on_track",
        generated_by_id=test_educator_id,
    )


# =============================================================================
# Authorization Tests - Development Profile
# =============================================================================


class TestDevelopmentProfileAuthorization:
    """Tests for development profile authorization."""

    @pytest.mark.asyncio
    async def test_get_profile_by_id_unauthorized(
        self,
        db_session: AsyncSession,
        sample_profile: UUID,
        test_educator_id_2: UUID,
    ):
        """Test that get_profile_by_id raises UnauthorizedAccessError for non-owner."""
        service = DevelopmentProfileService(db_session)

        with pytest.raises(UnauthorizedAccessError):
            await service.get_profile_by_id(
                profile_id=sample_profile,
                user_id=test_educator_id_2,  # Different educator
            )

    @pytest.mark.asyncio
    async def test_get_profile_by_id_authorized(
        self,
        db_session: AsyncSession,
        sample_profile: UUID,
        test_educator_id: UUID,
    ):
        """Test that get_profile_by_id succeeds for the owner."""
        service = DevelopmentProfileService(db_session)

        profile = await service.get_profile_by_id(
            profile_id=sample_profile,
            user_id=test_educator_id,  # Authorized educator
        )

        assert profile is not None
        assert profile.id == sample_profile

    @pytest.mark.asyncio
    async def test_get_profile_by_child_id_unauthorized(
        self,
        db_session: AsyncSession,
        sample_profile: UUID,
        test_child_id_dev: UUID,
        test_educator_id_2: UUID,
    ):
        """Test that get_profile_by_child_id raises UnauthorizedAccessError for non-owner."""
        service = DevelopmentProfileService(db_session)

        with pytest.raises(UnauthorizedAccessError):
            await service.get_profile_by_child_id(
                child_id=test_child_id_dev,
                user_id=test_educator_id_2,  # Different educator
            )

    @pytest.mark.asyncio
    async def test_get_profile_by_child_id_authorized(
        self,
        db_session: AsyncSession,
        sample_profile: UUID,
        test_child_id_dev: UUID,
        test_educator_id: UUID,
    ):
        """Test that get_profile_by_child_id succeeds for the owner."""
        service = DevelopmentProfileService(db_session)

        profile = await service.get_profile_by_child_id(
            child_id=test_child_id_dev,
            user_id=test_educator_id,  # Authorized educator
        )

        assert profile is not None
        assert profile.child_id == test_child_id_dev

    @pytest.mark.asyncio
    async def test_update_profile_unauthorized(
        self,
        db_session: AsyncSession,
        sample_profile: UUID,
        test_educator_id: UUID,
        test_educator_id_2: UUID,
    ):
        """Test that update_profile raises UnauthorizedAccessError for non-owner."""
        service = DevelopmentProfileService(db_session)

        request = DevelopmentProfileRequest(
            child_id=uuid4(),
            educator_id=test_educator_id,
            birth_date=date(2020, 1, 15),
            notes="Updated notes",
        )

        with pytest.raises(UnauthorizedAccessError):
            await service.update_profile(
                profile_id=sample_profile,
                request=request,
                user_id=test_educator_id_2,  # Different educator
            )

    @pytest.mark.asyncio
    async def test_update_profile_authorized(
        self,
        db_session: AsyncSession,
        sample_profile: UUID,
        test_child_id_dev: UUID,
        test_educator_id: UUID,
    ):
        """Test that update_profile succeeds for the owner."""
        service = DevelopmentProfileService(db_session)

        request = DevelopmentProfileRequest(
            child_id=test_child_id_dev,
            educator_id=test_educator_id,
            birth_date=date(2020, 1, 15),
            notes="Updated notes",
        )

        profile = await service.update_profile(
            profile_id=sample_profile,
            request=request,
            user_id=test_educator_id,  # Authorized educator
        )

        assert profile is not None
        assert profile.notes == "Updated notes"


# =============================================================================
# Authorization Tests - Skill Assessments
# =============================================================================


class TestSkillAssessmentAuthorization:
    """Tests for skill assessment authorization."""

    @pytest.mark.asyncio
    async def test_get_skill_assessment_unauthorized(
        self,
        db_session: AsyncSession,
        sample_assessment: UUID,
        test_educator_id_2: UUID,
    ):
        """Test that get_skill_assessment_by_id raises UnauthorizedAccessError for non-owner."""
        service = DevelopmentProfileService(db_session)

        with pytest.raises(UnauthorizedAccessError):
            await service.get_skill_assessment_by_id(
                assessment_id=sample_assessment,
                user_id=test_educator_id_2,  # Different educator
            )

    @pytest.mark.asyncio
    async def test_get_skill_assessment_authorized(
        self,
        db_session: AsyncSession,
        sample_assessment: UUID,
        test_educator_id: UUID,
    ):
        """Test that get_skill_assessment_by_id succeeds for the owner."""
        service = DevelopmentProfileService(db_session)

        assessment = await service.get_skill_assessment_by_id(
            assessment_id=sample_assessment,
            user_id=test_educator_id,  # Authorized educator
        )

        assert assessment is not None
        assert assessment.id == sample_assessment

    @pytest.mark.asyncio
    async def test_update_skill_assessment_unauthorized(
        self,
        db_session: AsyncSession,
        sample_assessment: UUID,
        test_educator_id_2: UUID,
    ):
        """Test that update_skill_assessment raises UnauthorizedAccessError for non-owner."""
        service = DevelopmentProfileService(db_session)

        request = SkillAssessmentUpdateRequest(
            status=SkillStatusSchema.CAN,
            evidence="Updated evidence",
        )

        with pytest.raises(UnauthorizedAccessError):
            await service.update_skill_assessment(
                assessment_id=sample_assessment,
                request=request,
                user_id=test_educator_id_2,  # Different educator
            )

    @pytest.mark.asyncio
    async def test_update_skill_assessment_authorized(
        self,
        db_session: AsyncSession,
        sample_assessment: UUID,
        test_educator_id: UUID,
    ):
        """Test that update_skill_assessment succeeds for the owner."""
        service = DevelopmentProfileService(db_session)

        request = SkillAssessmentUpdateRequest(
            status=SkillStatusSchema.CAN,
            evidence="Updated evidence",
        )

        assessment = await service.update_skill_assessment(
            assessment_id=sample_assessment,
            request=request,
            user_id=test_educator_id,  # Authorized educator
        )

        assert assessment is not None
        assert assessment.status == SkillStatusSchema.CAN


# =============================================================================
# Authorization Tests - Observations
# =============================================================================


class TestObservationAuthorization:
    """Tests for observation authorization."""

    @pytest.mark.asyncio
    async def test_get_observation_unauthorized(
        self,
        db_session: AsyncSession,
        sample_observation: UUID,
        test_educator_id_2: UUID,
    ):
        """Test that get_observation_by_id raises UnauthorizedAccessError for non-owner."""
        service = DevelopmentProfileService(db_session)

        with pytest.raises(UnauthorizedAccessError):
            await service.get_observation_by_id(
                observation_id=sample_observation,
                user_id=test_educator_id_2,  # Different educator
            )

    @pytest.mark.asyncio
    async def test_get_observation_authorized(
        self,
        db_session: AsyncSession,
        sample_observation: UUID,
        test_educator_id: UUID,
    ):
        """Test that get_observation_by_id succeeds for the owner."""
        service = DevelopmentProfileService(db_session)

        observation = await service.get_observation_by_id(
            observation_id=sample_observation,
            user_id=test_educator_id,  # Authorized educator
        )

        assert observation is not None
        assert observation.id == sample_observation

    @pytest.mark.asyncio
    async def test_update_observation_unauthorized(
        self,
        db_session: AsyncSession,
        sample_observation: UUID,
        test_educator_id_2: UUID,
    ):
        """Test that update_observation raises UnauthorizedAccessError for non-owner."""
        service = DevelopmentProfileService(db_session)

        request = ObservationUpdateRequest(
            behavior_description="Updated description",
            is_milestone=True,
        )

        with pytest.raises(UnauthorizedAccessError):
            await service.update_observation(
                observation_id=sample_observation,
                request=request,
                user_id=test_educator_id_2,  # Different educator
            )

    @pytest.mark.asyncio
    async def test_update_observation_authorized(
        self,
        db_session: AsyncSession,
        sample_observation: UUID,
        test_educator_id: UUID,
    ):
        """Test that update_observation succeeds for the owner."""
        service = DevelopmentProfileService(db_session)

        request = ObservationUpdateRequest(
            behavior_description="Updated description",
            is_milestone=True,
        )

        observation = await service.update_observation(
            observation_id=sample_observation,
            request=request,
            user_id=test_educator_id,  # Authorized educator
        )

        assert observation is not None
        assert observation.behavior_description == "Updated description"
        assert observation.is_milestone is True


# =============================================================================
# Authorization Tests - Monthly Snapshots
# =============================================================================


class TestMonthlySnapshotAuthorization:
    """Tests for monthly snapshot authorization."""

    @pytest.mark.asyncio
    async def test_get_monthly_snapshot_unauthorized(
        self,
        db_session: AsyncSession,
        sample_snapshot: UUID,
        test_educator_id_2: UUID,
    ):
        """Test that get_monthly_snapshot_by_id raises UnauthorizedAccessError for non-owner."""
        service = DevelopmentProfileService(db_session)

        with pytest.raises(UnauthorizedAccessError):
            await service.get_monthly_snapshot_by_id(
                snapshot_id=sample_snapshot,
                user_id=test_educator_id_2,  # Different educator
            )

    @pytest.mark.asyncio
    async def test_get_monthly_snapshot_authorized(
        self,
        db_session: AsyncSession,
        sample_snapshot: UUID,
        test_educator_id: UUID,
    ):
        """Test that get_monthly_snapshot_by_id succeeds for the owner."""
        service = DevelopmentProfileService(db_session)

        snapshot = await service.get_monthly_snapshot_by_id(
            snapshot_id=sample_snapshot,
            user_id=test_educator_id,  # Authorized educator
        )

        assert snapshot is not None
        assert snapshot.id == sample_snapshot

    @pytest.mark.asyncio
    async def test_update_monthly_snapshot_unauthorized(
        self,
        db_session: AsyncSession,
        sample_snapshot: UUID,
        test_educator_id_2: UUID,
    ):
        """Test that update_monthly_snapshot raises UnauthorizedAccessError for non-owner."""
        service = DevelopmentProfileService(db_session)

        request = MonthlySnapshotUpdateRequest(
            overall_progress=OverallProgress.EXCELLING,
            recommendations="Updated recommendations",
        )

        with pytest.raises(UnauthorizedAccessError):
            await service.update_monthly_snapshot(
                snapshot_id=sample_snapshot,
                request=request,
                user_id=test_educator_id_2,  # Different educator
            )

    @pytest.mark.asyncio
    async def test_update_monthly_snapshot_authorized(
        self,
        db_session: AsyncSession,
        sample_snapshot: UUID,
        test_educator_id: UUID,
    ):
        """Test that update_monthly_snapshot succeeds for the owner."""
        service = DevelopmentProfileService(db_session)

        request = MonthlySnapshotUpdateRequest(
            overall_progress=OverallProgress.EXCELLING,
            recommendations="Updated recommendations",
        )

        snapshot = await service.update_monthly_snapshot(
            snapshot_id=sample_snapshot,
            request=request,
            user_id=test_educator_id,  # Authorized educator
        )

        assert snapshot is not None
        assert snapshot.overall_progress == OverallProgress.EXCELLING


# =============================================================================
# Authorization Tests - Growth Trajectory
# =============================================================================


class TestGrowthTrajectoryAuthorization:
    """Tests for growth trajectory authorization."""

    @pytest.mark.asyncio
    async def test_get_growth_trajectory_unauthorized(
        self,
        db_session: AsyncSession,
        sample_profile: UUID,
        test_educator_id_2: UUID,
    ):
        """Test that get_growth_trajectory raises UnauthorizedAccessError for non-owner."""
        service = DevelopmentProfileService(db_session)

        with pytest.raises(UnauthorizedAccessError):
            await service.get_growth_trajectory(
                profile_id=sample_profile,
                user_id=test_educator_id_2,  # Different educator
            )

    @pytest.mark.asyncio
    async def test_get_growth_trajectory_authorized(
        self,
        db_session: AsyncSession,
        sample_profile: UUID,
        sample_snapshot: UUID,
        test_educator_id: UUID,
    ):
        """Test that get_growth_trajectory succeeds for the owner."""
        service = DevelopmentProfileService(db_session)

        trajectory = await service.get_growth_trajectory(
            profile_id=sample_profile,
            user_id=test_educator_id,  # Authorized educator
        )

        assert trajectory is not None
        assert trajectory.profile_id == sample_profile


# =============================================================================
# IDOR Vulnerability Tests
# =============================================================================


class TestIDORPrevention:
    """Tests to verify IDOR vulnerabilities are prevented."""

    @pytest.mark.asyncio
    async def test_cannot_access_another_educators_profile(
        self,
        db_session: AsyncSession,
        test_educator_id_2: UUID,
    ):
        """Test that an educator cannot access another educator's profile by ID manipulation."""
        # Create profile owned by educator 1
        profile_id = await create_profile_in_db(
            db_session,
            child_id=uuid4(),
            educator_id=UUID("aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa"),
            birth_date=date(2020, 1, 1),
        )

        # Try to access with educator 2 (should fail)
        service = DevelopmentProfileService(db_session)

        with pytest.raises(UnauthorizedAccessError):
            await service.get_profile_by_id(
                profile_id=profile_id,
                user_id=test_educator_id_2,
            )

    @pytest.mark.asyncio
    async def test_cannot_update_another_educators_assessment(
        self,
        db_session: AsyncSession,
        test_educator_id_2: UUID,
    ):
        """Test that an educator cannot update another educator's skill assessment."""
        # Create profile and assessment owned by educator 1
        profile_id = await create_profile_in_db(
            db_session,
            child_id=uuid4(),
            educator_id=UUID("aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa"),
            birth_date=date(2020, 1, 1),
        )

        assessment_id = await create_skill_assessment_in_db(
            db_session,
            profile_id=profile_id,
            domain="COGNITIVE",
            skill_name="Problem solving",
            status="CAN",
        )

        # Try to update with educator 2 (should fail)
        service = DevelopmentProfileService(db_session)
        request = SkillAssessmentUpdateRequest(
            status=SkillStatusSchema.NOT_YET,
            evidence="Malicious update",
        )

        with pytest.raises(UnauthorizedAccessError):
            await service.update_skill_assessment(
                assessment_id=assessment_id,
                request=request,
                user_id=test_educator_id_2,
            )

    @pytest.mark.asyncio
    async def test_cannot_access_another_educators_observations(
        self,
        db_session: AsyncSession,
        test_educator_id_2: UUID,
    ):
        """Test that an educator cannot access another educator's observations."""
        # Create profile and observation owned by educator 1
        profile_id = await create_profile_in_db(
            db_session,
            child_id=uuid4(),
            educator_id=UUID("aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa"),
            birth_date=date(2020, 1, 1),
        )

        observation_id = await create_observation_in_db(
            db_session,
            profile_id=profile_id,
            domain="GROSS_MOTOR",
            behavior_description="Running and jumping confidently",
        )

        # Try to access with educator 2 (should fail)
        service = DevelopmentProfileService(db_session)

        with pytest.raises(UnauthorizedAccessError):
            await service.get_observation_by_id(
                observation_id=observation_id,
                user_id=test_educator_id_2,
            )

    @pytest.mark.asyncio
    async def test_cannot_access_another_educators_snapshots(
        self,
        db_session: AsyncSession,
        test_educator_id_2: UUID,
    ):
        """Test that an educator cannot access another educator's monthly snapshots."""
        # Create profile and snapshot owned by educator 1
        profile_id = await create_profile_in_db(
            db_session,
            child_id=uuid4(),
            educator_id=UUID("aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa"),
            birth_date=date(2020, 1, 1),
        )

        snapshot_id = await create_monthly_snapshot_in_db(
            db_session,
            profile_id=profile_id,
            snapshot_month=date(2024, 2, 1),
            overall_progress="excelling",
        )

        # Try to access with educator 2 (should fail)
        service = DevelopmentProfileService(db_session)

        with pytest.raises(UnauthorizedAccessError):
            await service.get_monthly_snapshot_by_id(
                snapshot_id=snapshot_id,
                user_id=test_educator_id_2,
            )
