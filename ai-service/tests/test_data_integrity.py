"""Tests for data integrity verification script.

Tests verify that the integrity checker correctly identifies:
- Referential integrity violations
- Data consistency issues
- Business rule violations
- Orphaned records
- Invalid data ranges and formats

Tests cover all entity types and relationships in the AI service database.
"""

import pytest
from datetime import datetime, timedelta
from uuid import uuid4

from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession

from app.models.activity import (
    Activity,
    ActivityDifficulty,
    ActivityParticipation,
    ActivityRecommendation,
    ActivityType,
)
from app.models.coaching import CoachingSession, CoachingRecommendation, EvidenceSource
from app.models.communication import (
    CommunicationPreference,
    HomeActivity,
    ParentReport,
)

# Import integrity checker
import sys
from pathlib import Path

# Add scripts directory to path
scripts_dir = Path(__file__).parent.parent / "scripts"
sys.path.insert(0, str(scripts_dir))

try:
    from check_data_integrity import DataIntegrityChecker, IntegrityCheckResult
except ImportError:
    pytestmark = pytest.mark.skip(reason="Integrity checker module not available")


# =============================================================================
# Test IntegrityCheckResult
# =============================================================================


class TestIntegrityCheckResult:
    """Tests for IntegrityCheckResult class."""

    def test_result_initialization(self):
        """Test result initialization."""
        result = IntegrityCheckResult("Test Check")
        assert result.check_name == "Test Check"
        assert result.passed is True
        assert result.issues == []
        assert result.warnings == []
        assert result.info == []
        assert result.records_checked == 0
        assert result.issues_found == 0

    def test_add_issue(self):
        """Test adding an issue."""
        result = IntegrityCheckResult("Test Check")
        result.add_issue("Test issue")
        assert len(result.issues) == 1
        assert result.issues_found == 1
        assert result.passed is False

    def test_add_warning(self):
        """Test adding a warning."""
        result = IntegrityCheckResult("Test Check")
        result.add_warning("Test warning")
        assert len(result.warnings) == 1
        assert result.passed is True  # Warnings don't fail the check

    def test_add_info(self):
        """Test adding info."""
        result = IntegrityCheckResult("Test Check")
        result.add_info("Test info")
        assert len(result.info) == 1
        assert result.passed is True


# =============================================================================
# Test Activity Integrity
# =============================================================================


class TestActivityIntegrity:
    """Tests for Activity model integrity checks."""

    @pytest.mark.asyncio
    async def test_valid_activity(self, test_session: AsyncSession):
        """Test that valid activities pass integrity checks."""
        # Create a valid activity
        activity = Activity(
            id=uuid4(),
            name="Test Activity",
            description="A test activity",
            activity_type=ActivityType.COGNITIVE,
            difficulty=ActivityDifficulty.MEDIUM,
            duration_minutes=30,
            materials_needed=["Material 1", "Material 2"],
            min_age_months=24,
            max_age_months=48,
            is_active=True,
        )
        test_session.add(activity)
        await test_session.commit()

        # Run integrity check
        checker = DataIntegrityChecker(test_session)
        await checker._check_activity_integrity()

        # Verify no issues
        assert len(checker.results) == 1
        result = checker.results[0]
        assert result.passed is True
        assert result.issues_found == 0

    @pytest.mark.asyncio
    async def test_activity_invalid_age_range(self, test_session: AsyncSession):
        """Test detection of invalid age range (min > max)."""
        activity = Activity(
            id=uuid4(),
            name="Test Activity",
            description="A test activity",
            activity_type=ActivityType.COGNITIVE,
            difficulty=ActivityDifficulty.MEDIUM,
            duration_minutes=30,
            materials_needed=["Material 1"],
            min_age_months=48,  # Invalid: min > max
            max_age_months=24,
            is_active=True,
        )
        test_session.add(activity)
        await test_session.commit()

        # Run integrity check
        checker = DataIntegrityChecker(test_session)
        await checker._check_activity_integrity()

        # Verify issue detected
        result = checker.results[0]
        assert result.passed is False
        assert result.issues_found > 0

    @pytest.mark.asyncio
    async def test_activity_negative_age(self, test_session: AsyncSession):
        """Test detection of negative age values."""
        activity = Activity(
            id=uuid4(),
            name="Test Activity",
            description="A test activity",
            activity_type=ActivityType.COGNITIVE,
            difficulty=ActivityDifficulty.MEDIUM,
            duration_minutes=30,
            materials_needed=["Material 1"],
            min_age_months=-5,  # Invalid: negative
            max_age_months=24,
            is_active=True,
        )
        test_session.add(activity)
        await test_session.commit()

        # Run integrity check
        checker = DataIntegrityChecker(test_session)
        await checker._check_activity_integrity()

        # Verify issue detected
        result = checker.results[0]
        assert result.passed is False
        assert result.issues_found > 0

    @pytest.mark.asyncio
    async def test_activity_invalid_duration(self, test_session: AsyncSession):
        """Test detection of invalid duration."""
        activity = Activity(
            id=uuid4(),
            name="Test Activity",
            description="A test activity",
            activity_type=ActivityType.COGNITIVE,
            difficulty=ActivityDifficulty.MEDIUM,
            duration_minutes=-10,  # Invalid: negative
            materials_needed=["Material 1"],
            min_age_months=24,
            max_age_months=48,
            is_active=True,
        )
        test_session.add(activity)
        await test_session.commit()

        # Run integrity check
        checker = DataIntegrityChecker(test_session)
        await checker._check_activity_integrity()

        # Verify issue detected
        result = checker.results[0]
        assert result.passed is False
        assert result.issues_found > 0

    @pytest.mark.asyncio
    async def test_activity_empty_name(self, test_session: AsyncSession):
        """Test detection of empty name."""
        activity = Activity(
            id=uuid4(),
            name="",  # Invalid: empty
            description="A test activity",
            activity_type=ActivityType.COGNITIVE,
            difficulty=ActivityDifficulty.MEDIUM,
            duration_minutes=30,
            materials_needed=["Material 1"],
            is_active=True,
        )
        test_session.add(activity)
        await test_session.commit()

        # Run integrity check
        checker = DataIntegrityChecker(test_session)
        await checker._check_activity_integrity()

        # Verify issue detected
        result = checker.results[0]
        assert result.passed is False
        assert result.issues_found > 0


# =============================================================================
# Test Activity Participation Integrity
# =============================================================================


class TestActivityParticipationIntegrity:
    """Tests for ActivityParticipation model integrity checks."""

    @pytest.mark.asyncio
    async def test_valid_participation(self, test_session: AsyncSession):
        """Test that valid participations pass integrity checks."""
        # Create activity
        activity = Activity(
            id=uuid4(),
            name="Test Activity",
            description="A test activity",
            activity_type=ActivityType.COGNITIVE,
            difficulty=ActivityDifficulty.MEDIUM,
            duration_minutes=30,
            materials_needed=["Material 1"],
            is_active=True,
        )
        test_session.add(activity)
        await test_session.commit()

        # Create participation
        started = datetime.utcnow() - timedelta(hours=1)
        completed = started + timedelta(minutes=30)
        participation = ActivityParticipation(
            id=uuid4(),
            child_id=uuid4(),
            activity_id=activity.id,
            started_at=started,
            completed_at=completed,
            duration_minutes=30,
            completion_status="completed",
            engagement_score=0.85,
        )
        test_session.add(participation)
        await test_session.commit()

        # Run integrity check
        checker = DataIntegrityChecker(test_session)
        await checker._check_activity_participation_integrity()

        # Verify no issues
        result = checker.results[0]
        assert result.passed is True
        assert result.issues_found == 0

    @pytest.mark.asyncio
    async def test_participation_invalid_dates(self, test_session: AsyncSession):
        """Test detection of completed_at before started_at."""
        # Create activity
        activity = Activity(
            id=uuid4(),
            name="Test Activity",
            description="A test activity",
            activity_type=ActivityType.COGNITIVE,
            difficulty=ActivityDifficulty.MEDIUM,
            duration_minutes=30,
            materials_needed=["Material 1"],
            is_active=True,
        )
        test_session.add(activity)
        await test_session.commit()

        # Create participation with invalid dates
        started = datetime.utcnow()
        completed = started - timedelta(hours=1)  # Invalid: before started
        participation = ActivityParticipation(
            id=uuid4(),
            child_id=uuid4(),
            activity_id=activity.id,
            started_at=started,
            completed_at=completed,
            duration_minutes=30,
            completion_status="completed",
        )
        test_session.add(participation)
        await test_session.commit()

        # Run integrity check
        checker = DataIntegrityChecker(test_session)
        await checker._check_activity_participation_integrity()

        # Verify issue detected
        result = checker.results[0]
        assert result.passed is False
        assert result.issues_found > 0

    @pytest.mark.asyncio
    async def test_participation_invalid_engagement_score(self, test_session: AsyncSession):
        """Test detection of invalid engagement score."""
        # Create activity
        activity = Activity(
            id=uuid4(),
            name="Test Activity",
            description="A test activity",
            activity_type=ActivityType.COGNITIVE,
            difficulty=ActivityDifficulty.MEDIUM,
            duration_minutes=30,
            materials_needed=["Material 1"],
            is_active=True,
        )
        test_session.add(activity)
        await test_session.commit()

        # Create participation with invalid score
        participation = ActivityParticipation(
            id=uuid4(),
            child_id=uuid4(),
            activity_id=activity.id,
            started_at=datetime.utcnow(),
            engagement_score=1.5,  # Invalid: > 1.0
        )
        test_session.add(participation)
        await test_session.commit()

        # Run integrity check
        checker = DataIntegrityChecker(test_session)
        await checker._check_activity_participation_integrity()

        # Verify issue detected
        result = checker.results[0]
        assert result.passed is False
        assert result.issues_found > 0

    @pytest.mark.asyncio
    async def test_participation_status_mismatch(self, test_session: AsyncSession):
        """Test detection of status/completion mismatch."""
        # Create activity
        activity = Activity(
            id=uuid4(),
            name="Test Activity",
            description="A test activity",
            activity_type=ActivityType.COGNITIVE,
            difficulty=ActivityDifficulty.MEDIUM,
            duration_minutes=30,
            materials_needed=["Material 1"],
            is_active=True,
        )
        test_session.add(activity)
        await test_session.commit()

        # Create participation marked completed without timestamp
        participation = ActivityParticipation(
            id=uuid4(),
            child_id=uuid4(),
            activity_id=activity.id,
            started_at=datetime.utcnow(),
            completed_at=None,  # Missing
            completion_status="completed",  # But marked completed
        )
        test_session.add(participation)
        await test_session.commit()

        # Run integrity check
        checker = DataIntegrityChecker(test_session)
        await checker._check_activity_participation_integrity()

        # Verify issue detected
        result = checker.results[0]
        assert result.passed is False
        assert result.issues_found > 0


# =============================================================================
# Test Coaching Session Integrity
# =============================================================================


class TestCoachingSessionIntegrity:
    """Tests for CoachingSession model integrity checks."""

    @pytest.mark.asyncio
    async def test_valid_coaching_session(self, test_session: AsyncSession):
        """Test that valid sessions pass integrity checks."""
        session_obj = CoachingSession(
            id=uuid4(),
            child_id=uuid4(),
            user_id=uuid4(),
            question="How can I help my child?",
            context="Additional context",
            special_need_types=["autism", "adhd"],
            category="behavior",
        )
        test_session.add(session_obj)
        await test_session.commit()

        # Run integrity check
        checker = DataIntegrityChecker(test_session)
        await checker._check_coaching_session_integrity()

        # Verify no issues
        result = checker.results[0]
        assert result.passed is True
        assert result.issues_found == 0

    @pytest.mark.asyncio
    async def test_coaching_session_empty_question(self, test_session: AsyncSession):
        """Test detection of empty question."""
        session_obj = CoachingSession(
            id=uuid4(),
            child_id=uuid4(),
            user_id=uuid4(),
            question="",  # Invalid: empty
        )
        test_session.add(session_obj)
        await test_session.commit()

        # Run integrity check
        checker = DataIntegrityChecker(test_session)
        await checker._check_coaching_session_integrity()

        # Verify issue detected
        result = checker.results[0]
        assert result.passed is False
        assert result.issues_found > 0


# =============================================================================
# Test Coaching Recommendation Integrity
# =============================================================================


class TestCoachingRecommendationIntegrity:
    """Tests for CoachingRecommendation model integrity checks."""

    @pytest.mark.asyncio
    async def test_valid_coaching_recommendation(self, test_session: AsyncSession):
        """Test that valid recommendations pass integrity checks."""
        # Create session
        session_obj = CoachingSession(
            id=uuid4(),
            child_id=uuid4(),
            user_id=uuid4(),
            question="How can I help my child?",
        )
        test_session.add(session_obj)
        await test_session.commit()

        # Create recommendation
        recommendation = CoachingRecommendation(
            id=uuid4(),
            session_id=session_obj.id,
            title="Test Recommendation",
            content="Detailed recommendation content",
            category="behavior",
            priority="medium",
            relevance_score=0.85,
        )
        test_session.add(recommendation)
        await test_session.commit()

        # Run integrity check
        checker = DataIntegrityChecker(test_session)
        await checker._check_coaching_recommendation_integrity()

        # Verify no issues
        result = checker.results[0]
        assert result.passed is True
        assert result.issues_found == 0

    @pytest.mark.asyncio
    async def test_recommendation_invalid_relevance_score(self, test_session: AsyncSession):
        """Test detection of invalid relevance score."""
        # Create session
        session_obj = CoachingSession(
            id=uuid4(),
            child_id=uuid4(),
            user_id=uuid4(),
            question="How can I help my child?",
        )
        test_session.add(session_obj)
        await test_session.commit()

        # Create recommendation with invalid score
        recommendation = CoachingRecommendation(
            id=uuid4(),
            session_id=session_obj.id,
            title="Test Recommendation",
            content="Detailed recommendation content",
            category="behavior",
            priority="medium",
            relevance_score=1.5,  # Invalid: > 1.0
        )
        test_session.add(recommendation)
        await test_session.commit()

        # Run integrity check
        checker = DataIntegrityChecker(test_session)
        await checker._check_coaching_recommendation_integrity()

        # Verify issue detected
        result = checker.results[0]
        assert result.passed is False
        assert result.issues_found > 0

    @pytest.mark.asyncio
    async def test_recommendation_invalid_priority(self, test_session: AsyncSession):
        """Test detection of invalid priority."""
        # Create session
        session_obj = CoachingSession(
            id=uuid4(),
            child_id=uuid4(),
            user_id=uuid4(),
            question="How can I help my child?",
        )
        test_session.add(session_obj)
        await test_session.commit()

        # Create recommendation with invalid priority
        recommendation = CoachingRecommendation(
            id=uuid4(),
            session_id=session_obj.id,
            title="Test Recommendation",
            content="Detailed recommendation content",
            category="behavior",
            priority="super-critical",  # Invalid
            relevance_score=0.85,
        )
        test_session.add(recommendation)
        await test_session.commit()

        # Run integrity check
        checker = DataIntegrityChecker(test_session)
        await checker._check_coaching_recommendation_integrity()

        # Verify issue detected
        result = checker.results[0]
        assert result.passed is False
        assert result.issues_found > 0


# =============================================================================
# Test Orphaned Records Detection
# =============================================================================


class TestOrphanedRecords:
    """Tests for orphaned record detection."""

    @pytest.mark.asyncio
    async def test_detect_orphaned_participation(self, test_session: AsyncSession):
        """Test detection of orphaned activity participation."""
        # Create participation without activity
        participation = ActivityParticipation(
            id=uuid4(),
            child_id=uuid4(),
            activity_id=uuid4(),  # Non-existent activity
            started_at=datetime.utcnow(),
        )
        test_session.add(participation)
        await test_session.commit()

        # Run orphan check
        checker = DataIntegrityChecker(test_session)
        await checker._check_orphaned_records()

        # Verify orphan detected
        result = checker.results[0]
        assert result.passed is False
        assert result.issues_found > 0

    @pytest.mark.asyncio
    async def test_detect_orphaned_evidence_source(self, test_session: AsyncSession):
        """Test detection of orphaned evidence source."""
        # Create evidence source without recommendation
        evidence = EvidenceSource(
            id=uuid4(),
            recommendation_id=uuid4(),  # Non-existent recommendation
            title="Test Evidence",
            source_type="research",
            relevance_score=0.85,
        )
        test_session.add(evidence)
        await test_session.commit()

        # Run orphan check
        checker = DataIntegrityChecker(test_session)
        await checker._check_orphaned_records()

        # Verify orphan detected
        result = checker.results[0]
        assert result.passed is False
        assert result.issues_found > 0

    @pytest.mark.asyncio
    async def test_fix_orphaned_records(self, test_session: AsyncSession):
        """Test automatic fixing of orphaned records."""
        # Create orphaned participation
        participation = ActivityParticipation(
            id=uuid4(),
            child_id=uuid4(),
            activity_id=uuid4(),  # Non-existent
            started_at=datetime.utcnow(),
        )
        test_session.add(participation)
        await test_session.commit()

        # Get count before fix
        stmt = select(ActivityParticipation)
        result = await test_session.execute(stmt)
        count_before = len(result.scalars().all())

        # Run orphan check with fix enabled
        checker = DataIntegrityChecker(test_session, fix_orphans=True)
        await checker._check_orphaned_records()

        # Verify orphan was removed
        result = await test_session.execute(stmt)
        count_after = len(result.scalars().all())
        assert count_after < count_before


# =============================================================================
# Test Parent Report Integrity
# =============================================================================


class TestParentReportIntegrity:
    """Tests for ParentReport model integrity checks."""

    @pytest.mark.asyncio
    async def test_valid_parent_report(self, test_session: AsyncSession):
        """Test that valid reports pass integrity checks."""
        report = ParentReport(
            id=uuid4(),
            child_id=uuid4(),
            parent_id=uuid4(),
            date=datetime.utcnow().date(),
            language="en",
            summary="Test summary",
        )
        test_session.add(report)
        await test_session.commit()

        # Run integrity check
        checker = DataIntegrityChecker(test_session)
        await checker._check_parent_report_integrity()

        # Verify no issues
        result = checker.results[0]
        assert result.passed is True


# =============================================================================
# Test Communication Preference Integrity
# =============================================================================


class TestCommunicationPreferenceIntegrity:
    """Tests for CommunicationPreference model integrity checks."""

    @pytest.mark.asyncio
    async def test_valid_preference(self, test_session: AsyncSession):
        """Test that valid preferences pass integrity checks."""
        pref = CommunicationPreference(
            id=uuid4(),
            parent_id=uuid4(),
            email_enabled=True,
            sms_enabled=False,
            push_enabled=True,
            in_app_enabled=True,
            language="en",
        )
        test_session.add(pref)
        await test_session.commit()

        # Run integrity check
        checker = DataIntegrityChecker(test_session)
        await checker._check_communication_preference_integrity()

        # Verify no issues
        result = checker.results[0]
        assert result.passed is True

    @pytest.mark.asyncio
    async def test_preference_no_channels_enabled(self, test_session: AsyncSession):
        """Test warning when no communication channels are enabled."""
        pref = CommunicationPreference(
            id=uuid4(),
            parent_id=uuid4(),
            email_enabled=False,
            sms_enabled=False,
            push_enabled=False,
            in_app_enabled=False,
            language="en",
        )
        test_session.add(pref)
        await test_session.commit()

        # Run integrity check
        checker = DataIntegrityChecker(test_session)
        await checker._check_communication_preference_integrity()

        # Verify warning issued
        result = checker.results[0]
        assert len(result.warnings) > 0


# =============================================================================
# Test Full Verification
# =============================================================================


class TestFullVerification:
    """Tests for full integrity verification."""

    @pytest.mark.asyncio
    async def test_run_all_checks(self, test_session: AsyncSession):
        """Test running all integrity checks."""
        # Create some valid data
        activity = Activity(
            id=uuid4(),
            name="Test Activity",
            description="A test activity",
            activity_type=ActivityType.COGNITIVE,
            difficulty=ActivityDifficulty.MEDIUM,
            duration_minutes=30,
            materials_needed=["Material 1"],
            is_active=True,
        )
        test_session.add(activity)
        await test_session.commit()

        # Run all checks
        checker = DataIntegrityChecker(test_session)
        success = await checker.run_all_checks(full=False)

        # Verify checks ran
        assert len(checker.results) > 0
        assert success is True

    @pytest.mark.asyncio
    async def test_run_full_checks(self, test_session: AsyncSession):
        """Test running full integrity verification."""
        # Run full checks
        checker = DataIntegrityChecker(test_session)
        success = await checker.run_all_checks(full=True)

        # Verify extended checks ran
        assert len(checker.results) > 0
