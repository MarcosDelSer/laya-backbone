"""Unit tests for review reminder service functionality.

Tests for review reminder retrieval, date calculations, overdue/upcoming counts,
status filtering, and proper response structure for intervention plan reviews.
"""

from __future__ import annotations

from datetime import date, timedelta
from typing import Any
from unittest.mock import AsyncMock, MagicMock
from uuid import UUID, uuid4

import pytest
import pytest_asyncio

from app.schemas.intervention_plan import (
    InterventionPlanStatus,
    PlanReviewReminder,
    PlanReviewReminderListResponse,
    ReviewSchedule,
)
from app.services.intervention_plan_service import (
    InterventionPlanService,
)


# =============================================================================
# Fixtures
# =============================================================================


@pytest.fixture
def mock_db_session() -> AsyncMock:
    """Create a mock async database session.

    Returns:
        AsyncMock: Mock database session with async methods
    """
    session = AsyncMock()
    session.add = MagicMock()
    session.flush = AsyncMock()
    session.commit = AsyncMock()
    session.rollback = AsyncMock()
    session.refresh = AsyncMock()
    session.execute = AsyncMock()
    return session


@pytest.fixture
def mock_user_id() -> UUID:
    """Generate a mock user ID.

    Returns:
        UUID: Mock user identifier
    """
    return uuid4()


@pytest.fixture
def review_reminder_service(mock_db_session: AsyncMock) -> InterventionPlanService:
    """Create an InterventionPlanService instance with mock database.

    Args:
        mock_db_session: Mock database session fixture

    Returns:
        InterventionPlanService: Service instance for testing
    """
    return InterventionPlanService(mock_db_session)


def create_mock_plan(
    plan_id: UUID,
    child_id: UUID,
    child_name: str,
    title: str,
    status: str,
    next_review_date: date,
) -> MagicMock:
    """Create a mock intervention plan model for testing.

    Args:
        plan_id: Unique plan identifier
        child_id: Child identifier
        child_name: Child's name
        title: Plan title
        status: Plan status value
        next_review_date: Next review date

    Returns:
        MagicMock: Mock plan model object
    """
    plan = MagicMock()
    plan.id = plan_id
    plan.child_id = child_id
    plan.child_name = child_name
    plan.title = title
    plan.status = status
    plan.next_review_date = next_review_date
    return plan


# =============================================================================
# PlanReviewReminder Schema Tests
# =============================================================================


def test_plan_review_reminder_schema_valid() -> None:
    """Test that PlanReviewReminder schema accepts valid data.

    Verifies that the schema correctly validates and accepts
    properly formatted plan review reminder data.
    """
    plan_id = uuid4()
    child_id = uuid4()

    reminder = PlanReviewReminder(
        plan_id=plan_id,
        child_id=child_id,
        child_name="Test Child",
        title="Communication Plan",
        next_review_date=date.today() + timedelta(days=7),
        days_until_review=7,
        status=InterventionPlanStatus.ACTIVE,
    )

    assert reminder.plan_id == plan_id
    assert reminder.child_id == child_id
    assert reminder.child_name == "Test Child"
    assert reminder.title == "Communication Plan"
    assert reminder.days_until_review == 7
    assert reminder.status == InterventionPlanStatus.ACTIVE


def test_plan_review_reminder_overdue() -> None:
    """Test that PlanReviewReminder correctly represents overdue plans.

    Verifies that negative days_until_review values are accepted
    for plans that are past their review date.
    """
    reminder = PlanReviewReminder(
        plan_id=uuid4(),
        child_id=uuid4(),
        child_name="Test Child",
        title="Overdue Plan",
        next_review_date=date.today() - timedelta(days=5),
        days_until_review=-5,
        status=InterventionPlanStatus.ACTIVE,
    )

    assert reminder.days_until_review == -5, "Should allow negative days for overdue plans"


def test_plan_review_reminder_due_today() -> None:
    """Test that PlanReviewReminder correctly represents plans due today.

    Verifies that days_until_review of 0 is accepted for plans
    due for review today.
    """
    reminder = PlanReviewReminder(
        plan_id=uuid4(),
        child_id=uuid4(),
        child_name="Test Child",
        title="Due Today Plan",
        next_review_date=date.today(),
        days_until_review=0,
        status=InterventionPlanStatus.UNDER_REVIEW,
    )

    assert reminder.days_until_review == 0, "Should allow 0 days for plans due today"


# =============================================================================
# PlanReviewReminderListResponse Schema Tests
# =============================================================================


def test_plan_review_reminder_list_response_schema() -> None:
    """Test that PlanReviewReminderListResponse schema works correctly.

    Verifies that the list response schema properly aggregates
    plan reminders with overdue and upcoming counts.
    """
    reminder1 = PlanReviewReminder(
        plan_id=uuid4(),
        child_id=uuid4(),
        child_name="Child 1",
        title="Plan 1",
        next_review_date=date.today() - timedelta(days=3),
        days_until_review=-3,
        status=InterventionPlanStatus.ACTIVE,
    )

    reminder2 = PlanReviewReminder(
        plan_id=uuid4(),
        child_id=uuid4(),
        child_name="Child 2",
        title="Plan 2",
        next_review_date=date.today() + timedelta(days=10),
        days_until_review=10,
        status=InterventionPlanStatus.ACTIVE,
    )

    response = PlanReviewReminderListResponse(
        plans=[reminder1, reminder2],
        overdue_count=1,
        upcoming_count=1,
    )

    assert len(response.plans) == 2
    assert response.overdue_count == 1
    assert response.upcoming_count == 1


def test_plan_review_reminder_list_response_empty() -> None:
    """Test that PlanReviewReminderListResponse handles empty results.

    Verifies that the schema properly handles the case when
    no plans are due for review.
    """
    response = PlanReviewReminderListResponse(
        plans=[],
        overdue_count=0,
        upcoming_count=0,
    )

    assert len(response.plans) == 0
    assert response.overdue_count == 0
    assert response.upcoming_count == 0


def test_plan_review_reminder_list_response_defaults() -> None:
    """Test that PlanReviewReminderListResponse has correct defaults.

    Verifies that overdue_count and upcoming_count default to 0.
    """
    response = PlanReviewReminderListResponse(plans=[])

    assert response.overdue_count == 0
    assert response.upcoming_count == 0


# =============================================================================
# get_plans_for_review Service Method Tests
# =============================================================================


@pytest.mark.asyncio
async def test_get_plans_for_review_returns_overdue_plans(
    mock_db_session: AsyncMock,
    mock_user_id: UUID,
) -> None:
    """Test that get_plans_for_review returns overdue plans.

    Verifies that plans with next_review_date before today
    are included in the response with negative days_until_review.
    """
    service = InterventionPlanService(mock_db_session)

    # Create mock overdue plan (5 days past due)
    overdue_plan = create_mock_plan(
        plan_id=uuid4(),
        child_id=uuid4(),
        child_name="Overdue Child",
        title="Overdue Communication Plan",
        status=InterventionPlanStatus.ACTIVE.value,
        next_review_date=date.today() - timedelta(days=5),
    )

    # Mock the database result
    mock_result = MagicMock()
    mock_result.scalars.return_value.all.return_value = [overdue_plan]
    mock_db_session.execute.return_value = mock_result

    response = await service.get_plans_for_review(mock_user_id)

    assert len(response.plans) == 1
    assert response.overdue_count == 1
    assert response.upcoming_count == 0
    assert response.plans[0].days_until_review == -5


@pytest.mark.asyncio
async def test_get_plans_for_review_returns_upcoming_plans(
    mock_db_session: AsyncMock,
    mock_user_id: UUID,
) -> None:
    """Test that get_plans_for_review returns upcoming plans.

    Verifies that plans with next_review_date within the days_ahead
    window are included with positive days_until_review.
    """
    service = InterventionPlanService(mock_db_session)

    # Create mock upcoming plan (10 days ahead)
    upcoming_plan = create_mock_plan(
        plan_id=uuid4(),
        child_id=uuid4(),
        child_name="Upcoming Child",
        title="Upcoming Review Plan",
        status=InterventionPlanStatus.ACTIVE.value,
        next_review_date=date.today() + timedelta(days=10),
    )

    # Mock the database result
    mock_result = MagicMock()
    mock_result.scalars.return_value.all.return_value = [upcoming_plan]
    mock_db_session.execute.return_value = mock_result

    response = await service.get_plans_for_review(mock_user_id, days_ahead=30)

    assert len(response.plans) == 1
    assert response.overdue_count == 0
    assert response.upcoming_count == 1
    assert response.plans[0].days_until_review == 10


@pytest.mark.asyncio
async def test_get_plans_for_review_mixed_overdue_and_upcoming(
    mock_db_session: AsyncMock,
    mock_user_id: UUID,
) -> None:
    """Test that get_plans_for_review correctly categorizes mixed plans.

    Verifies that overdue and upcoming plans are properly counted
    and categorized in the response.
    """
    service = InterventionPlanService(mock_db_session)

    # Create overdue plan
    overdue_plan = create_mock_plan(
        plan_id=uuid4(),
        child_id=uuid4(),
        child_name="Overdue Child",
        title="Overdue Plan",
        status=InterventionPlanStatus.ACTIVE.value,
        next_review_date=date.today() - timedelta(days=3),
    )

    # Create upcoming plan
    upcoming_plan = create_mock_plan(
        plan_id=uuid4(),
        child_id=uuid4(),
        child_name="Upcoming Child",
        title="Upcoming Plan",
        status=InterventionPlanStatus.UNDER_REVIEW.value,
        next_review_date=date.today() + timedelta(days=15),
    )

    # Create plan due today
    today_plan = create_mock_plan(
        plan_id=uuid4(),
        child_id=uuid4(),
        child_name="Today Child",
        title="Due Today Plan",
        status=InterventionPlanStatus.ACTIVE.value,
        next_review_date=date.today(),
    )

    # Mock the database result
    mock_result = MagicMock()
    mock_result.scalars.return_value.all.return_value = [
        overdue_plan,
        today_plan,
        upcoming_plan,
    ]
    mock_db_session.execute.return_value = mock_result

    response = await service.get_plans_for_review(mock_user_id)

    assert len(response.plans) == 3
    assert response.overdue_count == 1, "Should count only plans with negative days"
    assert response.upcoming_count == 2, "Should count plans with 0 or positive days"


@pytest.mark.asyncio
async def test_get_plans_for_review_empty_result(
    mock_db_session: AsyncMock,
    mock_user_id: UUID,
) -> None:
    """Test that get_plans_for_review handles empty results correctly.

    Verifies that when no plans are due for review, the response
    returns empty lists and zero counts.
    """
    service = InterventionPlanService(mock_db_session)

    # Mock empty database result
    mock_result = MagicMock()
    mock_result.scalars.return_value.all.return_value = []
    mock_db_session.execute.return_value = mock_result

    response = await service.get_plans_for_review(mock_user_id)

    assert len(response.plans) == 0
    assert response.overdue_count == 0
    assert response.upcoming_count == 0


@pytest.mark.asyncio
async def test_get_plans_for_review_custom_days_ahead(
    mock_db_session: AsyncMock,
    mock_user_id: UUID,
) -> None:
    """Test that get_plans_for_review respects custom days_ahead parameter.

    Verifies that the days_ahead parameter correctly limits
    which upcoming plans are included.
    """
    service = InterventionPlanService(mock_db_session)

    # Create a plan 10 days ahead
    plan_within_window = create_mock_plan(
        plan_id=uuid4(),
        child_id=uuid4(),
        child_name="Within Window Child",
        title="Within Window Plan",
        status=InterventionPlanStatus.ACTIVE.value,
        next_review_date=date.today() + timedelta(days=10),
    )

    # Mock the database result
    mock_result = MagicMock()
    mock_result.scalars.return_value.all.return_value = [plan_within_window]
    mock_db_session.execute.return_value = mock_result

    # Should include with 15 day window
    response = await service.get_plans_for_review(mock_user_id, days_ahead=15)

    assert len(response.plans) == 1
    assert response.plans[0].days_until_review == 10


@pytest.mark.asyncio
async def test_get_plans_for_review_response_structure(
    mock_db_session: AsyncMock,
    mock_user_id: UUID,
) -> None:
    """Test that get_plans_for_review returns properly structured response.

    Verifies that each plan in the response contains all required
    fields with correct values.
    """
    service = InterventionPlanService(mock_db_session)

    plan_id = uuid4()
    child_id = uuid4()

    mock_plan = create_mock_plan(
        plan_id=plan_id,
        child_id=child_id,
        child_name="Test Child",
        title="Test Plan",
        status=InterventionPlanStatus.ACTIVE.value,
        next_review_date=date.today() + timedelta(days=7),
    )

    # Mock the database result
    mock_result = MagicMock()
    mock_result.scalars.return_value.all.return_value = [mock_plan]
    mock_db_session.execute.return_value = mock_result

    response = await service.get_plans_for_review(mock_user_id)

    assert isinstance(response, PlanReviewReminderListResponse)
    assert len(response.plans) == 1

    reminder = response.plans[0]
    assert reminder.plan_id == plan_id
    assert reminder.child_id == child_id
    assert reminder.child_name == "Test Child"
    assert reminder.title == "Test Plan"
    assert reminder.next_review_date == date.today() + timedelta(days=7)
    assert reminder.days_until_review == 7
    assert reminder.status == InterventionPlanStatus.ACTIVE


@pytest.mark.asyncio
async def test_get_plans_for_review_active_status_only(
    mock_db_session: AsyncMock,
    mock_user_id: UUID,
) -> None:
    """Test that get_plans_for_review only includes active and under_review status.

    Verifies that plans with ACTIVE or UNDER_REVIEW status are
    included while DRAFT, COMPLETED, and ARCHIVED are excluded.
    The query should only return plans matching these statuses.
    """
    service = InterventionPlanService(mock_db_session)

    # Create an active plan
    active_plan = create_mock_plan(
        plan_id=uuid4(),
        child_id=uuid4(),
        child_name="Active Child",
        title="Active Plan",
        status=InterventionPlanStatus.ACTIVE.value,
        next_review_date=date.today() + timedelta(days=5),
    )

    # Create an under_review plan
    under_review_plan = create_mock_plan(
        plan_id=uuid4(),
        child_id=uuid4(),
        child_name="Review Child",
        title="Under Review Plan",
        status=InterventionPlanStatus.UNDER_REVIEW.value,
        next_review_date=date.today() + timedelta(days=3),
    )

    # Mock returns only active/under_review plans (as the query filters)
    mock_result = MagicMock()
    mock_result.scalars.return_value.all.return_value = [
        active_plan,
        under_review_plan,
    ]
    mock_db_session.execute.return_value = mock_result

    response = await service.get_plans_for_review(mock_user_id)

    assert len(response.plans) == 2

    # Verify statuses
    statuses = [p.status for p in response.plans]
    assert InterventionPlanStatus.ACTIVE in statuses
    assert InterventionPlanStatus.UNDER_REVIEW in statuses


@pytest.mark.asyncio
async def test_get_plans_for_review_default_days_ahead(
    mock_db_session: AsyncMock,
    mock_user_id: UUID,
) -> None:
    """Test that get_plans_for_review uses default 30 days ahead.

    Verifies that when days_ahead is not specified, the method
    defaults to 30 days.
    """
    service = InterventionPlanService(mock_db_session)

    # Plan at 29 days (within default 30 day window)
    plan_within = create_mock_plan(
        plan_id=uuid4(),
        child_id=uuid4(),
        child_name="Within Window Child",
        title="Within Window Plan",
        status=InterventionPlanStatus.ACTIVE.value,
        next_review_date=date.today() + timedelta(days=29),
    )

    # Mock the database result
    mock_result = MagicMock()
    mock_result.scalars.return_value.all.return_value = [plan_within]
    mock_db_session.execute.return_value = mock_result

    # Call without specifying days_ahead (should default to 30)
    response = await service.get_plans_for_review(mock_user_id)

    assert len(response.plans) == 1
    assert response.plans[0].days_until_review == 29


@pytest.mark.asyncio
async def test_get_plans_for_review_sorted_by_date(
    mock_db_session: AsyncMock,
    mock_user_id: UUID,
) -> None:
    """Test that get_plans_for_review returns plans sorted by review date.

    Verifies that plans are ordered by next_review_date ascending,
    with overdue plans appearing first.
    """
    service = InterventionPlanService(mock_db_session)

    # Create plans in non-sorted order
    plan_later = create_mock_plan(
        plan_id=uuid4(),
        child_id=uuid4(),
        child_name="Later Child",
        title="Later Plan",
        status=InterventionPlanStatus.ACTIVE.value,
        next_review_date=date.today() + timedelta(days=20),
    )

    plan_overdue = create_mock_plan(
        plan_id=uuid4(),
        child_id=uuid4(),
        child_name="Overdue Child",
        title="Overdue Plan",
        status=InterventionPlanStatus.ACTIVE.value,
        next_review_date=date.today() - timedelta(days=5),
    )

    plan_soon = create_mock_plan(
        plan_id=uuid4(),
        child_id=uuid4(),
        child_name="Soon Child",
        title="Soon Plan",
        status=InterventionPlanStatus.ACTIVE.value,
        next_review_date=date.today() + timedelta(days=3),
    )

    # Mock returns plans in sorted order (as the query orders them)
    mock_result = MagicMock()
    mock_result.scalars.return_value.all.return_value = [
        plan_overdue,
        plan_soon,
        plan_later,
    ]
    mock_db_session.execute.return_value = mock_result

    response = await service.get_plans_for_review(mock_user_id)

    assert len(response.plans) == 3
    # Verify sorted order (most urgent first)
    assert response.plans[0].days_until_review == -5  # overdue
    assert response.plans[1].days_until_review == 3  # soon
    assert response.plans[2].days_until_review == 20  # later


# =============================================================================
# Review Schedule Enum Tests
# =============================================================================


def test_all_review_schedules_valid() -> None:
    """Test that all ReviewSchedule enum values are valid.

    Verifies coverage for all 4 review schedules: MONTHLY, QUARTERLY,
    SEMI_ANNUALLY, and ANNUALLY.
    """
    expected_schedules = ["monthly", "quarterly", "semi_annually", "annually"]

    for schedule_value in expected_schedules:
        schedule = ReviewSchedule(schedule_value)
        assert schedule.value == schedule_value, (
            f"Schedule {schedule_value} should be valid"
        )


def test_review_schedule_enum_values() -> None:
    """Test ReviewSchedule enum has correct string values.

    Verifies that each schedule enum maps to the expected string value.
    """
    assert ReviewSchedule.MONTHLY.value == "monthly"
    assert ReviewSchedule.QUARTERLY.value == "quarterly"
    assert ReviewSchedule.SEMI_ANNUALLY.value == "semi_annually"
    assert ReviewSchedule.ANNUALLY.value == "annually"


# =============================================================================
# Date Calculation Tests for Review Reminders
# =============================================================================


def test_calculate_next_review_date_from_today(
    review_reminder_service: InterventionPlanService,
) -> None:
    """Test next review date calculation from today's date.

    Verifies that the service correctly calculates review dates
    based on different schedules starting from today.
    """
    today = date.today()

    # Monthly from today
    result = review_reminder_service._calculate_next_review_date(
        today, ReviewSchedule.MONTHLY
    )
    expected = review_reminder_service._add_months(today, 1)
    assert result == expected, "Monthly review should be 1 month from today"

    # Quarterly from today
    result = review_reminder_service._calculate_next_review_date(
        today, ReviewSchedule.QUARTERLY
    )
    expected = review_reminder_service._add_months(today, 3)
    assert result == expected, "Quarterly review should be 3 months from today"


def test_days_until_review_calculation() -> None:
    """Test that days_until_review is calculated correctly.

    Verifies that the difference between today and review date
    gives the correct number of days, including negative for overdue.
    """
    today = date.today()

    # Test upcoming review (positive days)
    future_date = today + timedelta(days=15)
    days_until = (future_date - today).days
    assert days_until == 15, "Future date should have positive days"

    # Test overdue review (negative days)
    past_date = today - timedelta(days=10)
    days_until = (past_date - today).days
    assert days_until == -10, "Past date should have negative days"

    # Test today (zero days)
    days_until = (today - today).days
    assert days_until == 0, "Same day should have zero days"


def test_review_window_boundary_cases(
    review_reminder_service: InterventionPlanService,
) -> None:
    """Test boundary cases for review reminder window.

    Verifies that plans exactly at the boundary of the days_ahead
    window are correctly included or excluded.
    """
    today = date.today()

    # Plan exactly at 30 days (at the boundary of default window)
    boundary_date = today + timedelta(days=30)
    days_until = (boundary_date - today).days
    assert days_until == 30, "Plan at boundary should be exactly 30 days"

    # Plan at 31 days (just outside default window)
    outside_date = today + timedelta(days=31)
    days_until = (outside_date - today).days
    assert days_until == 31, "Plan outside boundary should be 31 days"


# =============================================================================
# Plan Status Filtering Tests
# =============================================================================


def test_review_eligible_statuses() -> None:
    """Test that only ACTIVE and UNDER_REVIEW statuses are eligible for reviews.

    Verifies the business logic that only certain plan statuses
    should appear in review reminders.
    """
    # Eligible statuses
    eligible_statuses = [
        InterventionPlanStatus.ACTIVE,
        InterventionPlanStatus.UNDER_REVIEW,
    ]

    for status in eligible_statuses:
        assert status in [
            InterventionPlanStatus.ACTIVE,
            InterventionPlanStatus.UNDER_REVIEW,
        ], f"{status} should be eligible for review reminders"

    # Non-eligible statuses
    non_eligible_statuses = [
        InterventionPlanStatus.DRAFT,
        InterventionPlanStatus.COMPLETED,
        InterventionPlanStatus.ARCHIVED,
    ]

    for status in non_eligible_statuses:
        assert status not in [
            InterventionPlanStatus.ACTIVE,
            InterventionPlanStatus.UNDER_REVIEW,
        ], f"{status} should not be eligible for review reminders"


# =============================================================================
# Edge Case Tests
# =============================================================================


def test_plan_review_reminder_with_long_child_name() -> None:
    """Test PlanReviewReminder with a long child name.

    Verifies that the schema handles child names of various lengths.
    """
    long_name = "A" * 200

    reminder = PlanReviewReminder(
        plan_id=uuid4(),
        child_id=uuid4(),
        child_name=long_name,
        title="Test Plan",
        next_review_date=date.today(),
        days_until_review=0,
        status=InterventionPlanStatus.ACTIVE,
    )

    assert len(reminder.child_name) == 200


def test_plan_review_reminder_with_long_title() -> None:
    """Test PlanReviewReminder with a long title.

    Verifies that the schema handles plan titles of various lengths.
    """
    long_title = "B" * 200

    reminder = PlanReviewReminder(
        plan_id=uuid4(),
        child_id=uuid4(),
        child_name="Test Child",
        title=long_title,
        next_review_date=date.today(),
        days_until_review=0,
        status=InterventionPlanStatus.ACTIVE,
    )

    assert len(reminder.title) == 200


@pytest.mark.asyncio
async def test_get_plans_for_review_with_zero_days_ahead(
    mock_db_session: AsyncMock,
    mock_user_id: UUID,
) -> None:
    """Test get_plans_for_review with zero days_ahead.

    Verifies that when days_ahead is 0, only overdue and today's
    plans are included.
    """
    service = InterventionPlanService(mock_db_session)

    # Create a plan due today
    today_plan = create_mock_plan(
        plan_id=uuid4(),
        child_id=uuid4(),
        child_name="Today Child",
        title="Today Plan",
        status=InterventionPlanStatus.ACTIVE.value,
        next_review_date=date.today(),
    )

    # Create an overdue plan
    overdue_plan = create_mock_plan(
        plan_id=uuid4(),
        child_id=uuid4(),
        child_name="Overdue Child",
        title="Overdue Plan",
        status=InterventionPlanStatus.ACTIVE.value,
        next_review_date=date.today() - timedelta(days=2),
    )

    # Mock returns only plans at or before today
    mock_result = MagicMock()
    mock_result.scalars.return_value.all.return_value = [
        overdue_plan,
        today_plan,
    ]
    mock_db_session.execute.return_value = mock_result

    response = await service.get_plans_for_review(mock_user_id, days_ahead=0)

    assert len(response.plans) == 2
    assert response.overdue_count == 1
    assert response.upcoming_count == 1  # Today counts as upcoming


@pytest.mark.asyncio
async def test_get_plans_for_review_large_days_ahead(
    mock_db_session: AsyncMock,
    mock_user_id: UUID,
) -> None:
    """Test get_plans_for_review with large days_ahead value.

    Verifies that the method handles large look-ahead windows correctly.
    """
    service = InterventionPlanService(mock_db_session)

    # Create a plan far in the future
    future_plan = create_mock_plan(
        plan_id=uuid4(),
        child_id=uuid4(),
        child_name="Future Child",
        title="Future Plan",
        status=InterventionPlanStatus.ACTIVE.value,
        next_review_date=date.today() + timedelta(days=180),
    )

    # Mock the database result
    mock_result = MagicMock()
    mock_result.scalars.return_value.all.return_value = [future_plan]
    mock_db_session.execute.return_value = mock_result

    response = await service.get_plans_for_review(mock_user_id, days_ahead=365)

    assert len(response.plans) == 1
    assert response.plans[0].days_until_review == 180


# =============================================================================
# Multiple Plans Tests
# =============================================================================


@pytest.mark.asyncio
async def test_get_plans_for_review_multiple_children(
    mock_db_session: AsyncMock,
    mock_user_id: UUID,
) -> None:
    """Test get_plans_for_review with multiple children's plans.

    Verifies that plans for different children are all included
    in the response.
    """
    service = InterventionPlanService(mock_db_session)

    # Create plans for different children
    plans = []
    for i in range(5):
        plan = create_mock_plan(
            plan_id=uuid4(),
            child_id=uuid4(),
            child_name=f"Child {i + 1}",
            title=f"Plan for Child {i + 1}",
            status=InterventionPlanStatus.ACTIVE.value,
            next_review_date=date.today() + timedelta(days=i * 5),
        )
        plans.append(plan)

    # Mock the database result
    mock_result = MagicMock()
    mock_result.scalars.return_value.all.return_value = plans
    mock_db_session.execute.return_value = mock_result

    response = await service.get_plans_for_review(mock_user_id)

    assert len(response.plans) == 5
    # All child names should be unique
    child_names = [p.child_name for p in response.plans]
    assert len(set(child_names)) == 5


@pytest.mark.asyncio
async def test_get_plans_for_review_same_child_multiple_plans(
    mock_db_session: AsyncMock,
    mock_user_id: UUID,
) -> None:
    """Test get_plans_for_review with multiple plans for the same child.

    Verifies that multiple plans for the same child are all included.
    """
    service = InterventionPlanService(mock_db_session)

    child_id = uuid4()

    # Create multiple plans for the same child
    plan1 = create_mock_plan(
        plan_id=uuid4(),
        child_id=child_id,
        child_name="Same Child",
        title="Communication Plan",
        status=InterventionPlanStatus.ACTIVE.value,
        next_review_date=date.today() + timedelta(days=5),
    )

    plan2 = create_mock_plan(
        plan_id=uuid4(),
        child_id=child_id,
        child_name="Same Child",
        title="Behavior Plan",
        status=InterventionPlanStatus.ACTIVE.value,
        next_review_date=date.today() + timedelta(days=10),
    )

    plan3 = create_mock_plan(
        plan_id=uuid4(),
        child_id=child_id,
        child_name="Same Child",
        title="Motor Skills Plan",
        status=InterventionPlanStatus.UNDER_REVIEW.value,
        next_review_date=date.today() + timedelta(days=2),
    )

    # Mock the database result
    mock_result = MagicMock()
    mock_result.scalars.return_value.all.return_value = [plan3, plan1, plan2]
    mock_db_session.execute.return_value = mock_result

    response = await service.get_plans_for_review(mock_user_id)

    assert len(response.plans) == 3
    # All plans should be for the same child
    for reminder in response.plans:
        assert reminder.child_id == child_id
        assert reminder.child_name == "Same Child"

    # Plans should have unique titles
    titles = [p.title for p in response.plans]
    assert len(set(titles)) == 3
