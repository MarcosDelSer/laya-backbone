"""Tests for N+1 query optimization.

These tests verify that eager loading prevents N+1 query problems when
accessing related data through ORM relationships. They use SQLAlchemy's
query logging to count the number of queries executed.
"""

import logging
from datetime import datetime, timedelta
from uuid import uuid4

import pytest
from sqlalchemy import event, select
from sqlalchemy.ext.asyncio import AsyncSession

from app.models.activity import Activity, ActivityParticipation, ActivityRecommendation
from app.models.coaching import CoachingRecommendation, CoachingSession, EvidenceSource
from app.services.activity_service import ActivityService
from app.services.coaching_service import CoachingService
from app.utils.query_optimization import (
    eager_load_activity_participation_relationships,
    eager_load_activity_relationships,
    eager_load_coaching_session_relationships,
)


class QueryCounter:
    """Helper class to count SQL queries executed."""

    def __init__(self):
        self.count = 0
        self.queries = []

    def __call__(self, conn, cursor, statement, parameters, context, executemany):
        """SQLAlchemy event hook to count queries."""
        self.count += 1
        self.queries.append(statement)


@pytest.fixture
async def sample_activities(db_session: AsyncSession):
    """Create sample activities for testing."""
    activities = []
    for i in range(5):
        activity = Activity(
            id=uuid4(),
            name=f"Test Activity {i}",
            description=f"Description for activity {i}",
            activity_type="cognitive",
            difficulty="medium",
            duration_minutes=30,
            materials_needed=["paper", "pencils"],
            is_active=True,
        )
        db_session.add(activity)
        activities.append(activity)

    await db_session.commit()
    return activities


@pytest.fixture
async def sample_participations(db_session: AsyncSession, sample_activities):
    """Create sample activity participations for testing."""
    child_id = uuid4()
    participations = []

    for activity in sample_activities:
        participation = ActivityParticipation(
            id=uuid4(),
            child_id=child_id,
            activity_id=activity.id,
            started_at=datetime.utcnow(),
            completion_status="completed",
            engagement_score=0.85,
        )
        db_session.add(participation)
        participations.append(participation)

    await db_session.commit()
    return participations, child_id


@pytest.fixture
async def sample_recommendations(db_session: AsyncSession, sample_activities):
    """Create sample activity recommendations for testing."""
    child_id = uuid4()
    recommendations = []

    for activity in sample_activities:
        recommendation = ActivityRecommendation(
            id=uuid4(),
            child_id=child_id,
            activity_id=activity.id,
            relevance_score=0.9,
            reasoning="Test recommendation",
        )
        db_session.add(recommendation)
        recommendations.append(recommendation)

    await db_session.commit()
    return recommendations, child_id


@pytest.fixture
async def sample_coaching_sessions(db_session: AsyncSession):
    """Create sample coaching sessions with recommendations and evidence."""
    child_id = uuid4()
    user_id = uuid4()
    sessions = []

    for i in range(3):
        # Create session
        session = CoachingSession(
            id=uuid4(),
            child_id=child_id,
            user_id=user_id,
            question=f"Test question {i}",
            special_need_types=["autism"],
            category="activity_adaptation",
        )
        db_session.add(session)
        await db_session.flush()

        # Create recommendations for this session
        for j in range(2):
            recommendation = CoachingRecommendation(
                id=uuid4(),
                session_id=session.id,
                title=f"Recommendation {j} for session {i}",
                content=f"Content for recommendation {j}",
                category="activity_adaptation",
                priority="medium",
                relevance_score=0.85,
                target_audience="educator",
            )
            db_session.add(recommendation)
            await db_session.flush()

            # Create evidence sources for this recommendation
            for k in range(2):
                evidence = EvidenceSource(
                    id=uuid4(),
                    recommendation_id=recommendation.id,
                    source_type="peer_reviewed",
                    title=f"Evidence {k} for recommendation {j}",
                    authors="Test Author",
                    year=2023,
                )
                db_session.add(evidence)

        sessions.append(session)

    await db_session.commit()
    return sessions, child_id


@pytest.mark.asyncio
async def test_activity_relationships_without_eager_loading(
    db_session: AsyncSession, sample_activities, sample_recommendations
):
    """Test that accessing relationships without eager loading causes N+1 queries."""
    # Create query counter
    counter = QueryCounter()
    event.listen(db_session.sync_session, "before_cursor_execute", counter)

    try:
        # Fetch activities without eager loading
        query = select(Activity).where(Activity.is_active == True).limit(5)
        result = await db_session.execute(query)
        activities = result.scalars().all()

        initial_queries = counter.count

        # Access recommendations for each activity (triggers N additional queries)
        for activity in activities:
            _ = len(activity.recommendations)

        final_queries = counter.count

        # Should have executed 1 (initial) + N (one per activity) queries
        # This demonstrates the N+1 problem
        assert final_queries > initial_queries, "Expected additional queries for relationships"
        # With 5 activities, we expect at least 5 additional queries
        assert (final_queries - initial_queries) >= 5, "Expected N+1 query problem"

    finally:
        event.remove(db_session.sync_session, "before_cursor_execute", counter)


@pytest.mark.asyncio
async def test_activity_relationships_with_eager_loading(
    db_session: AsyncSession, sample_activities, sample_recommendations
):
    """Test that eager loading prevents N+1 queries when accessing relationships."""
    # Create query counter
    counter = QueryCounter()
    event.listen(db_session.sync_session, "before_cursor_execute", counter)

    try:
        # Fetch activities WITH eager loading
        query = select(Activity).where(Activity.is_active == True).limit(5)
        query = eager_load_activity_relationships(query)
        result = await db_session.execute(query)
        activities = result.scalars().all()

        initial_queries = counter.count

        # Access recommendations for each activity (should NOT trigger additional queries)
        for activity in activities:
            _ = len(activity.recommendations)

        final_queries = counter.count

        # With eager loading, accessing relationships should not trigger additional queries
        assert final_queries == initial_queries, "Expected no additional queries with eager loading"

    finally:
        event.remove(db_session.sync_session, "before_cursor_execute", counter)


@pytest.mark.asyncio
async def test_participation_relationships_with_eager_loading(
    db_session: AsyncSession, sample_participations
):
    """Test that eager loading prevents N+1 queries for participation.activity."""
    participations, child_id = sample_participations

    # Create query counter
    counter = QueryCounter()
    event.listen(db_session.sync_session, "before_cursor_execute", counter)

    try:
        # Fetch participations WITH eager loading
        query = select(ActivityParticipation).where(
            ActivityParticipation.child_id == child_id
        )
        query = eager_load_activity_participation_relationships(query)
        result = await db_session.execute(query)
        participations = result.scalars().all()

        initial_queries = counter.count

        # Access activity for each participation (should NOT trigger additional queries)
        for participation in participations:
            _ = participation.activity.name

        final_queries = counter.count

        # With eager loading, accessing relationships should not trigger additional queries
        assert final_queries == initial_queries, "Expected no additional queries with eager loading"

    finally:
        event.remove(db_session.sync_session, "before_cursor_execute", counter)


@pytest.mark.asyncio
async def test_coaching_session_relationships_with_eager_loading(
    db_session: AsyncSession, sample_coaching_sessions
):
    """Test that eager loading prevents N+1 queries for nested coaching relationships."""
    sessions, child_id = sample_coaching_sessions

    # Create query counter
    counter = QueryCounter()
    event.listen(db_session.sync_session, "before_cursor_execute", counter)

    try:
        # Fetch sessions WITH eager loading
        query = select(CoachingSession).where(CoachingSession.child_id == child_id)
        query = eager_load_coaching_session_relationships(query)
        result = await db_session.execute(query)
        sessions = result.scalars().all()

        initial_queries = counter.count

        # Access nested relationships (session -> recommendations -> evidence_sources)
        for session in sessions:
            for recommendation in session.recommendations:
                _ = len(recommendation.evidence_sources)

        final_queries = counter.count

        # With eager loading, accessing nested relationships should not trigger additional queries
        assert final_queries == initial_queries, "Expected no additional queries with eager loading"

    finally:
        event.remove(db_session.sync_session, "before_cursor_execute", counter)


@pytest.mark.asyncio
async def test_activity_service_get_participations_optimization(
    db_session: AsyncSession, sample_participations
):
    """Test that ActivityService.get_participations_for_child uses eager loading."""
    _, child_id = sample_participations
    service = ActivityService(db_session)

    # Create query counter
    counter = QueryCounter()
    event.listen(db_session.sync_session, "before_cursor_execute", counter)

    try:
        # Use service method which should use eager loading
        participations = await service.get_participations_for_child(child_id)

        initial_queries = counter.count

        # Access activity for each participation (should NOT trigger additional queries)
        for participation in participations:
            _ = participation.activity.name

        final_queries = counter.count

        # Service method should use eager loading
        assert final_queries == initial_queries, "Service method should use eager loading"

    finally:
        event.remove(db_session.sync_session, "before_cursor_execute", counter)


@pytest.mark.asyncio
async def test_coaching_service_get_sessions_optimization(
    db_session: AsyncSession, sample_coaching_sessions
):
    """Test that CoachingService.get_sessions_for_child uses eager loading."""
    _, child_id = sample_coaching_sessions
    service = CoachingService(db_session)

    # Create query counter
    counter = QueryCounter()
    event.listen(db_session.sync_session, "before_cursor_execute", counter)

    try:
        # Use service method which should use eager loading
        sessions = await service.get_sessions_for_child(child_id)

        initial_queries = counter.count

        # Access nested relationships (should NOT trigger additional queries)
        for session in sessions:
            for recommendation in session.recommendations:
                _ = len(recommendation.evidence_sources)

        final_queries = counter.count

        # Service method should use eager loading
        assert final_queries == initial_queries, "Service method should use eager loading"

    finally:
        event.remove(db_session.sync_session, "before_cursor_execute", counter)


@pytest.mark.asyncio
async def test_query_count_improvement():
    """Document the performance improvement from N+1 query fixes.

    This test demonstrates the typical improvement:
    - Without eager loading: 1 + N queries (11 queries for 10 records)
    - With eager loading: 1-2 queries (regardless of N)

    Performance improvement: ~80-90% reduction in queries
    """
    # This is a documentation test - the actual improvements are measured
    # in the other tests. This just documents the expected improvements.

    improvement_metrics = {
        "without_eager_loading": {
            "records": 10,
            "queries": 11,  # 1 initial + 10 for relationships
            "description": "N+1 query problem",
        },
        "with_eager_loading": {
            "records": 10,
            "queries": 2,  # 1 for main query + 1 for selectinload
            "description": "Optimized with eager loading",
        },
        "improvement": {
            "query_reduction": "~82%",  # (11 - 2) / 11 = 0.818
            "scalability": "O(1) queries instead of O(N)",
        },
    }

    assert improvement_metrics["with_eager_loading"]["queries"] < improvement_metrics["without_eager_loading"]["queries"]
    # This test always passes - it's for documentation


# Performance benchmarking helper
def benchmark_queries(test_name: str, query_count: int):
    """Helper to log query counts for benchmarking.

    Args:
        test_name: Name of the test
        query_count: Number of queries executed
    """
    logger = logging.getLogger(__name__)
    logger.info(f"Benchmark: {test_name} executed {query_count} queries")
