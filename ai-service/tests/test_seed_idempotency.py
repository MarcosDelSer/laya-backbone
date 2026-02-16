"""Tests for seed script idempotency.

Tests verify that seed scripts can be run multiple times without creating
duplicate data. This ensures safe re-running of seed scripts in development
environments.

Tests cover:
- Running seed script multiple times creates same number of records
- No duplicate records are created
- Idempotency checks work for all entity types
- Data integrity maintained across multiple runs
"""

import asyncio
from uuid import uuid4

import pytest
from sqlalchemy import select, func
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


# Import seed functions
import sys
from pathlib import Path

# Add scripts directory to path so we can import seed functions
scripts_dir = Path(__file__).parent.parent / "scripts"
sys.path.insert(0, str(scripts_dir))

try:
    from seed import (
        seed_activities,
        seed_activity_participations,
        seed_activity_recommendations,
        seed_coaching_sessions,
        seed_parent_reports,
        seed_home_activities,
        seed_communication_preferences,
        generate_children_and_families,
    )
except ImportError:
    # If import fails, we'll skip these tests
    pytestmark = pytest.mark.skip(reason="Seed module not available")


# =============================================================================
# Helper Functions
# =============================================================================


async def count_records(session: AsyncSession, model) -> int:
    """Count total records for a given model.

    Args:
        session: Database session
        model: SQLAlchemy model class

    Returns:
        int: Total count of records
    """
    result = await session.execute(select(func.count()).select_from(model))
    return result.scalar() or 0


# =============================================================================
# Idempotency Tests
# =============================================================================


class TestSeedIdempotency:
    """Tests for seed script idempotency."""

    @pytest.mark.asyncio
    async def test_seed_activities_idempotent(self, test_session: AsyncSession):
        """Test that seeding activities multiple times doesn't create duplicates."""
        # First run
        activity_ids_1 = await seed_activities(test_session)
        count_1 = await count_records(test_session, Activity)

        # Second run - should not create new activities
        activity_ids_2 = await seed_activities(test_session)
        count_2 = await count_records(test_session, Activity)

        # Verify idempotency
        assert count_1 == count_2, "Activity count should remain the same after second run"
        assert count_1 > 0, "Should have created some activities"

        # Verify we get the same IDs back (order may differ)
        assert set(activity_ids_1) == set(activity_ids_2), "Should return same activity IDs"

    @pytest.mark.asyncio
    async def test_seed_activity_participations_idempotent(self, test_session: AsyncSession):
        """Test that seeding participations multiple times doesn't create duplicates."""
        # Setup: create activities and children
        activity_ids = await seed_activities(test_session)
        child_ids, _, _ = await generate_children_and_families()

        # First run
        await seed_activity_participations(test_session, child_ids, activity_ids)
        count_1 = await count_records(test_session, ActivityParticipation)

        # Second run - should not create new participations
        await seed_activity_participations(test_session, child_ids, activity_ids)
        count_2 = await count_records(test_session, ActivityParticipation)

        # Verify idempotency
        assert count_1 == count_2, "Participation count should remain the same after second run"
        assert count_1 > 0, "Should have created some participations"

    @pytest.mark.asyncio
    async def test_seed_activity_recommendations_idempotent(self, test_session: AsyncSession):
        """Test that seeding recommendations multiple times doesn't create duplicates."""
        # Setup
        activity_ids = await seed_activities(test_session)
        child_ids, _, _ = await generate_children_and_families()

        # First run
        await seed_activity_recommendations(test_session, child_ids, activity_ids)
        count_1 = await count_records(test_session, ActivityRecommendation)

        # Second run - should not create new recommendations
        await seed_activity_recommendations(test_session, child_ids, activity_ids)
        count_2 = await count_records(test_session, ActivityRecommendation)

        # Verify idempotency
        assert count_1 == count_2, "Recommendation count should remain the same after second run"
        assert count_1 > 0, "Should have created some recommendations"

    @pytest.mark.asyncio
    async def test_seed_coaching_sessions_idempotent(self, test_session: AsyncSession):
        """Test that seeding coaching sessions multiple times doesn't create duplicates."""
        # Setup
        child_ids, _, parent_ids = await generate_children_and_families()

        # First run
        session_ids_1 = await seed_coaching_sessions(test_session, child_ids, parent_ids)
        count_1 = await count_records(test_session, CoachingSession)

        # Second run - should not create new sessions
        session_ids_2 = await seed_coaching_sessions(test_session, child_ids, parent_ids)
        count_2 = await count_records(test_session, CoachingSession)

        # Verify idempotency
        assert count_1 == count_2, "Coaching session count should remain the same after second run"
        assert count_1 > 0, "Should have created some coaching sessions"
        assert set(session_ids_1) == set(session_ids_2), "Should return same session IDs"

    @pytest.mark.asyncio
    async def test_seed_parent_reports_idempotent(self, test_session: AsyncSession):
        """Test that seeding parent reports multiple times doesn't create duplicates."""
        # Setup
        child_ids, _, parent_ids = await generate_children_and_families()

        # First run
        await seed_parent_reports(test_session, child_ids, parent_ids)
        count_1 = await count_records(test_session, ParentReport)

        # Second run - should not create new reports
        await seed_parent_reports(test_session, child_ids, parent_ids)
        count_2 = await count_records(test_session, ParentReport)

        # Verify idempotency
        assert count_1 == count_2, "Parent report count should remain the same after second run"
        assert count_1 > 0, "Should have created some parent reports"

    @pytest.mark.asyncio
    async def test_seed_home_activities_idempotent(self, test_session: AsyncSession):
        """Test that seeding home activities multiple times doesn't create duplicates."""
        # Setup
        activity_ids = await seed_activities(test_session)
        child_ids, _, _ = await generate_children_and_families()

        # First run
        await seed_home_activities(test_session, child_ids, activity_ids)
        count_1 = await count_records(test_session, HomeActivity)

        # Second run - should not create new home activities
        await seed_home_activities(test_session, child_ids, activity_ids)
        count_2 = await count_records(test_session, HomeActivity)

        # Verify idempotency
        assert count_1 == count_2, "Home activity count should remain the same after second run"
        assert count_1 > 0, "Should have created some home activities"

    @pytest.mark.asyncio
    async def test_seed_communication_preferences_idempotent(self, test_session: AsyncSession):
        """Test that seeding communication preferences multiple times doesn't create duplicates."""
        # Setup
        child_ids, _, parent_ids = await generate_children_and_families()

        # First run
        await seed_communication_preferences(test_session, child_ids, parent_ids)
        count_1 = await count_records(test_session, CommunicationPreference)

        # Second run - should not create new preferences
        await seed_communication_preferences(test_session, child_ids, parent_ids)
        count_2 = await count_records(test_session, CommunicationPreference)

        # Verify idempotency
        assert count_1 == count_2, "Communication preference count should remain the same after second run"
        assert count_1 > 0, "Should have created some communication preferences"

    @pytest.mark.asyncio
    async def test_full_seed_script_idempotent(self, test_session: AsyncSession):
        """Test that running the complete seed script multiple times is idempotent."""
        # Generate IDs
        child_ids, family_ids, parent_ids = await generate_children_and_families()

        # First complete run
        activity_ids_1 = await seed_activities(test_session)
        await seed_activity_participations(test_session, child_ids, activity_ids_1)
        await seed_activity_recommendations(test_session, child_ids, activity_ids_1)
        await seed_coaching_sessions(test_session, child_ids, parent_ids)
        await seed_parent_reports(test_session, child_ids, parent_ids)
        await seed_home_activities(test_session, child_ids, activity_ids_1)
        await seed_communication_preferences(test_session, child_ids, parent_ids)

        # Count all records after first run
        counts_1 = {
            "activities": await count_records(test_session, Activity),
            "participations": await count_records(test_session, ActivityParticipation),
            "recommendations": await count_records(test_session, ActivityRecommendation),
            "coaching_sessions": await count_records(test_session, CoachingSession),
            "parent_reports": await count_records(test_session, ParentReport),
            "home_activities": await count_records(test_session, HomeActivity),
            "communication_prefs": await count_records(test_session, CommunicationPreference),
        }

        # Second complete run
        activity_ids_2 = await seed_activities(test_session)
        await seed_activity_participations(test_session, child_ids, activity_ids_2)
        await seed_activity_recommendations(test_session, child_ids, activity_ids_2)
        await seed_coaching_sessions(test_session, child_ids, parent_ids)
        await seed_parent_reports(test_session, child_ids, parent_ids)
        await seed_home_activities(test_session, child_ids, activity_ids_2)
        await seed_communication_preferences(test_session, child_ids, parent_ids)

        # Count all records after second run
        counts_2 = {
            "activities": await count_records(test_session, Activity),
            "participations": await count_records(test_session, ActivityParticipation),
            "recommendations": await count_records(test_session, ActivityRecommendation),
            "coaching_sessions": await count_records(test_session, CoachingSession),
            "parent_reports": await count_records(test_session, ParentReport),
            "home_activities": await count_records(test_session, HomeActivity),
            "communication_prefs": await count_records(test_session, CommunicationPreference),
        }

        # Verify all counts remained the same
        for entity_type, count_1 in counts_1.items():
            count_2 = counts_2[entity_type]
            assert count_1 == count_2, (
                f"{entity_type} count changed from {count_1} to {count_2} "
                "after second run - seed script is not idempotent!"
            )
            assert count_1 > 0, f"Should have created some {entity_type}"

    @pytest.mark.asyncio
    async def test_no_duplicate_activities_by_name(self, test_session: AsyncSession):
        """Test that running seed multiple times doesn't create activities with duplicate names."""
        # Run seed twice
        await seed_activities(test_session)
        await seed_activities(test_session)

        # Get all activity names
        result = await test_session.execute(select(Activity.name))
        names = [row[0] for row in result.all()]

        # Check for duplicates
        unique_names = set(names)
        assert len(names) == len(unique_names), (
            f"Found duplicate activity names. "
            f"Total: {len(names)}, Unique: {len(unique_names)}"
        )

    @pytest.mark.asyncio
    async def test_data_integrity_after_multiple_runs(self, test_session: AsyncSession):
        """Test that data integrity is maintained after running seed script multiple times."""
        # Setup
        child_ids, _, parent_ids = await generate_children_and_families()
        activity_ids = await seed_activities(test_session)

        # Run participations seeding 3 times
        for _ in range(3):
            await seed_activity_participations(test_session, child_ids, activity_ids)

        # Verify all participations reference valid activities
        result = await test_session.execute(
            select(ActivityParticipation.activity_id)
        )
        participation_activity_ids = [row[0] for row in result.all()]

        # All participation activity IDs should be in the seeded activity IDs
        for activity_id in participation_activity_ids:
            assert activity_id in activity_ids, (
                f"Found participation referencing non-existent activity {activity_id}"
            )


# =============================================================================
# Edge Case Tests
# =============================================================================


class TestSeedEdgeCases:
    """Tests for edge cases in seed script idempotency."""

    @pytest.mark.asyncio
    async def test_seed_with_empty_database(self, test_session: AsyncSession):
        """Test seeding into empty database works correctly."""
        # Verify database is empty
        count = await count_records(test_session, Activity)
        assert count == 0, "Database should start empty"

        # Run seed
        activity_ids = await seed_activities(test_session)

        # Verify data was created
        count = await count_records(test_session, Activity)
        assert count > 0, "Should have created activities"
        assert len(activity_ids) == count, "Should return all created activity IDs"

    @pytest.mark.asyncio
    async def test_seed_with_partial_data(self, test_session: AsyncSession):
        """Test seeding when some data already exists."""
        # Create some activities manually
        manual_activity = Activity(
            id=uuid4(),
            name="Manual Test Activity",
            description="Manually created for testing",
            activity_type=ActivityType.COGNITIVE,
            difficulty=ActivityDifficulty.EASY,
            duration_minutes=30,
            materials_needed=["test materials"],
            min_age_months=12,
            max_age_months=36,
            is_active=True,
        )
        test_session.add(manual_activity)
        await test_session.commit()

        initial_count = await count_records(test_session, Activity)
        assert initial_count == 1, "Should have one manual activity"

        # Run seed - should add more activities
        activity_ids = await seed_activities(test_session)
        final_count = await count_records(test_session, Activity)

        # Verify seed added activities
        assert final_count > initial_count, "Should have added seeded activities"

        # Verify manual activity still exists
        result = await test_session.execute(
            select(Activity).where(Activity.id == manual_activity.id)
        )
        found_activity = result.scalar_one_or_none()
        assert found_activity is not None, "Manual activity should still exist"
        assert found_activity.name == "Manual Test Activity", "Manual activity should be unchanged"
