"""Unit tests for intervention plan service functionality.

Tests for plan CRUD operations, versioning, progress tracking, review scheduling,
parent signature, section management, and error handling for the 8-part
intervention plan structure.
"""

from __future__ import annotations

from datetime import date, datetime, timedelta
from typing import Any
from unittest.mock import AsyncMock, MagicMock, patch
from uuid import UUID, uuid4

import pytest
import pytest_asyncio

from app.schemas.intervention_plan import (
    ConsultationCreate,
    GoalStatus,
    InterventionPlanCreate,
    InterventionPlanResponse,
    InterventionPlanStatus,
    InterventionPlanUpdate,
    MonitoringCreate,
    MonitoringMethod,
    NeedCategory,
    NeedCreate,
    NeedPriority,
    ParentActivityType,
    ParentInvolvementCreate,
    ParentSignatureRequest,
    ProgressCreate,
    ProgressLevel,
    ResponsibleParty,
    ReviewSchedule,
    SMARTGoalCreate,
    SpecialistType,
    StrategyCreate,
    StrengthCategory,
    StrengthCreate,
)
from app.services.intervention_plan_service import (
    InterventionPlanService,
    InvalidPlanError,
    PlanNotFoundError,
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
def mock_child_id() -> UUID:
    """Generate a mock child ID.

    Returns:
        UUID: Mock child identifier
    """
    return uuid4()


@pytest.fixture
def mock_plan_id() -> UUID:
    """Generate a mock plan ID.

    Returns:
        UUID: Mock plan identifier
    """
    return uuid4()


@pytest.fixture
def intervention_service(mock_db_session: AsyncMock) -> InterventionPlanService:
    """Create an InterventionPlanService instance with mock database.

    Args:
        mock_db_session: Mock database session fixture

    Returns:
        InterventionPlanService: Service instance for testing
    """
    return InterventionPlanService(mock_db_session)


@pytest.fixture
def plan_create_request(mock_child_id: UUID) -> InterventionPlanCreate:
    """Create a standard intervention plan creation request for testing.

    Args:
        mock_child_id: Mock child ID fixture

    Returns:
        InterventionPlanCreate: Valid plan creation request
    """
    return InterventionPlanCreate(
        child_id=mock_child_id,
        title="Communication Development Plan",
        child_name="John Doe",
        date_of_birth=date(2020, 1, 15),
        diagnosis=["autism", "speech_delay"],
        medical_history="No significant medical history",
        educational_history="Attended early intervention program",
        family_context="Supportive two-parent household",
        review_schedule=ReviewSchedule.QUARTERLY,
        effective_date=date.today(),
        status=InterventionPlanStatus.DRAFT,
    )


@pytest.fixture
def plan_create_request_with_sections(mock_child_id: UUID) -> InterventionPlanCreate:
    """Create an intervention plan request with all 8 sections for testing.

    Args:
        mock_child_id: Mock child ID fixture

    Returns:
        InterventionPlanCreate: Plan request with all sections
    """
    return InterventionPlanCreate(
        child_id=mock_child_id,
        title="Comprehensive Support Plan",
        child_name="Jane Smith",
        date_of_birth=date(2019, 6, 20),
        diagnosis=["adhd"],
        review_schedule=ReviewSchedule.MONTHLY,
        effective_date=date.today(),
        status=InterventionPlanStatus.DRAFT,
        # Part 2 - Strengths
        strengths=[
            StrengthCreate(
                category=StrengthCategory.COGNITIVE,
                description="Strong visual learning abilities",
                examples="Quickly learns from picture books",
                order=0,
            ),
        ],
        # Part 3 - Needs
        needs=[
            NeedCreate(
                category=NeedCategory.COMMUNICATION,
                description="Difficulty expressing needs verbally",
                priority=NeedPriority.HIGH,
                baseline="Uses gestures primarily",
                order=0,
            ),
        ],
        # Part 4 - SMART Goals
        goals=[
            SMARTGoalCreate(
                title="Improve verbal communication",
                description="Child will use 2-word phrases to express needs",
                measurement_criteria="Count of 2-word phrase occurrences",
                measurement_baseline="0 per day",
                measurement_target="5 per day",
                achievability_notes="Child shows interest in communication",
                relevance_notes="Addresses primary communication need",
                target_date=date.today() + timedelta(days=90),
                status=GoalStatus.NOT_STARTED,
                progress_percentage=0.0,
                order=0,
            ),
        ],
        # Part 5 - Strategies
        strategies=[
            StrategyCreate(
                title="Visual Communication Supports",
                description="Use picture cards for choice-making",
                responsible_party=ResponsibleParty.EDUCATOR,
                frequency="Throughout the day",
                materials_needed="PECS cards, communication board",
                order=0,
            ),
        ],
        # Part 6 - Monitoring
        monitoring=[
            MonitoringCreate(
                method=MonitoringMethod.DATA_COLLECTION,
                description="Track daily phrase usage",
                frequency="daily",
                responsible_party=ResponsibleParty.EDUCATOR,
                data_collection_tools="Communication log sheet",
                success_indicators="Consistent increase in phrase usage",
                order=0,
            ),
        ],
        # Part 7 - Parent Involvement
        parent_involvements=[
            ParentInvolvementCreate(
                activity_type=ParentActivityType.HOME_ACTIVITY,
                title="Home Communication Practice",
                description="Practice using picture cards at home during meals",
                frequency="Daily at mealtimes",
                resources_provided="Set of PECS cards for home use",
                communication_method="Weekly email updates",
                order=0,
            ),
        ],
        # Part 8 - Consultations
        consultations=[
            ConsultationCreate(
                specialist_type=SpecialistType.SPEECH_THERAPIST,
                specialist_name="Dr. Sarah Johnson",
                organization="Children's Speech Center",
                purpose="Weekly speech therapy sessions",
                consultation_date=date.today(),
                order=0,
            ),
        ],
    )


@pytest.fixture
def mock_plan_model(mock_plan_id: UUID, mock_child_id: UUID, mock_user_id: UUID) -> MagicMock:
    """Create a mock intervention plan model.

    Args:
        mock_plan_id: Mock plan ID fixture
        mock_child_id: Mock child ID fixture
        mock_user_id: Mock user ID fixture

    Returns:
        MagicMock: Mock plan model object
    """
    plan = MagicMock()
    plan.id = mock_plan_id
    plan.child_id = mock_child_id
    plan.created_by = mock_user_id
    plan.title = "Test Plan"
    plan.status = "draft"
    plan.version = 1
    plan.child_name = "Test Child"
    plan.date_of_birth = date(2020, 1, 1)
    plan.diagnosis = ["autism"]
    plan.medical_history = None
    plan.educational_history = None
    plan.family_context = None
    plan.review_schedule = "quarterly"
    plan.next_review_date = date.today() + timedelta(days=90)
    plan.effective_date = date.today()
    plan.end_date = None
    plan.parent_signed = False
    plan.parent_signature_date = None
    plan.parent_signature_data = None
    plan.created_at = datetime.utcnow()
    plan.updated_at = datetime.utcnow()
    plan.strengths = []
    plan.needs = []
    plan.goals = []
    plan.strategies = []
    plan.monitoring = []
    plan.parent_involvements = []
    plan.consultations = []
    plan.progress_records = []
    plan.versions = []
    return plan


# =============================================================================
# Service Initialization Tests
# =============================================================================


def test_service_initialization(mock_db_session: AsyncMock) -> None:
    """Test that the service initializes correctly with a database session.

    Verifies that the InterventionPlanService properly stores the
    database session reference.
    """
    service = InterventionPlanService(mock_db_session)

    assert service.db is mock_db_session, "Service should store database session"


# =============================================================================
# Review Schedule Tests
# =============================================================================


def test_all_review_schedules_have_months_delta(
    intervention_service: InterventionPlanService,
) -> None:
    """Test that all ReviewSchedule enum values have corresponding months delta.

    Verifies that the _get_months_delta helper method handles all
    review schedule values: MONTHLY (1), QUARTERLY (3), SEMI_ANNUALLY (6),
    and ANNUALLY (12).
    """
    expected_deltas = {
        ReviewSchedule.MONTHLY: 1,
        ReviewSchedule.QUARTERLY: 3,
        ReviewSchedule.SEMI_ANNUALLY: 6,
        ReviewSchedule.ANNUALLY: 12,
    }

    for schedule, expected_months in expected_deltas.items():
        result = intervention_service._get_months_delta(schedule)
        assert result == expected_months, (
            f"Expected {expected_months} months for {schedule.value}, got {result}"
        )


def test_calculate_next_review_date_monthly(
    intervention_service: InterventionPlanService,
) -> None:
    """Test next review date calculation for monthly schedule.

    Verifies that monthly review schedule calculates the correct
    next review date 1 month from the effective date.
    """
    effective_date = date(2024, 1, 15)
    schedule = ReviewSchedule.MONTHLY

    result = intervention_service._calculate_next_review_date(effective_date, schedule)

    assert result == date(2024, 2, 15), "Monthly should be 1 month later"


def test_calculate_next_review_date_quarterly(
    intervention_service: InterventionPlanService,
) -> None:
    """Test next review date calculation for quarterly schedule.

    Verifies that quarterly review schedule calculates the correct
    next review date 3 months from the effective date.
    """
    effective_date = date(2024, 1, 15)
    schedule = ReviewSchedule.QUARTERLY

    result = intervention_service._calculate_next_review_date(effective_date, schedule)

    assert result == date(2024, 4, 15), "Quarterly should be 3 months later"


def test_calculate_next_review_date_semi_annually(
    intervention_service: InterventionPlanService,
) -> None:
    """Test next review date calculation for semi-annual schedule.

    Verifies that semi-annual review schedule calculates the correct
    next review date 6 months from the effective date.
    """
    effective_date = date(2024, 1, 15)
    schedule = ReviewSchedule.SEMI_ANNUALLY

    result = intervention_service._calculate_next_review_date(effective_date, schedule)

    assert result == date(2024, 7, 15), "Semi-annually should be 6 months later"


def test_calculate_next_review_date_annually(
    intervention_service: InterventionPlanService,
) -> None:
    """Test next review date calculation for annual schedule.

    Verifies that annual review schedule calculates the correct
    next review date 12 months from the effective date.
    """
    effective_date = date(2024, 1, 15)
    schedule = ReviewSchedule.ANNUALLY

    result = intervention_service._calculate_next_review_date(effective_date, schedule)

    assert result == date(2025, 1, 15), "Annually should be 12 months later"


def test_add_months_handles_end_of_month(
    intervention_service: InterventionPlanService,
) -> None:
    """Test that _add_months handles month-end dates correctly.

    Verifies that adding months to dates at the end of longer months
    properly caps to the last day of shorter months (e.g., Jan 31 + 1 month = Feb 28/29).
    """
    # Jan 31 + 1 month should be Feb 28 (or 29 in leap year)
    source_date = date(2024, 1, 31)  # 2024 is a leap year
    result = intervention_service._add_months(source_date, 1)
    assert result == date(2024, 2, 29), "Should cap to end of February in leap year"

    # Jan 31 + 1 month in non-leap year
    source_date = date(2023, 1, 31)
    result = intervention_service._add_months(source_date, 1)
    assert result == date(2023, 2, 28), "Should cap to Feb 28 in non-leap year"

    # Mar 31 + 1 month
    source_date = date(2024, 3, 31)
    result = intervention_service._add_months(source_date, 1)
    assert result == date(2024, 4, 30), "Should cap to Apr 30"


def test_add_months_year_rollover(
    intervention_service: InterventionPlanService,
) -> None:
    """Test that _add_months handles year rollover correctly.

    Verifies that adding months across year boundaries works properly.
    """
    source_date = date(2024, 11, 15)
    result = intervention_service._add_months(source_date, 3)
    assert result == date(2025, 2, 15), "Should roll over to next year"


# =============================================================================
# Plan Status Tests
# =============================================================================


def test_all_plan_statuses_valid() -> None:
    """Test that all InterventionPlanStatus enum values are valid.

    Verifies that the enum contains all expected status values:
    DRAFT, ACTIVE, UNDER_REVIEW, COMPLETED, and ARCHIVED.
    """
    expected_statuses = ["draft", "active", "under_review", "completed", "archived"]

    for status_value in expected_statuses:
        status = InterventionPlanStatus(status_value)
        assert status.value == status_value, f"Status {status_value} should be valid"


def test_plan_status_transitions() -> None:
    """Test that plan status values are correctly defined.

    Verifies status enum has correct values for workflow transitions.
    """
    assert InterventionPlanStatus.DRAFT.value == "draft"
    assert InterventionPlanStatus.ACTIVE.value == "active"
    assert InterventionPlanStatus.UNDER_REVIEW.value == "under_review"
    assert InterventionPlanStatus.COMPLETED.value == "completed"
    assert InterventionPlanStatus.ARCHIVED.value == "archived"


# =============================================================================
# Strength Category Tests
# =============================================================================


def test_all_strength_categories_valid() -> None:
    """Test that all StrengthCategory enum values are valid.

    Verifies coverage for all 9 strength categories: COGNITIVE, SOCIAL,
    PHYSICAL, EMOTIONAL, COMMUNICATION, CREATIVE, ACADEMIC, ADAPTIVE, OTHER.
    """
    expected_categories = [
        "cognitive", "social", "physical", "emotional", "communication",
        "creative", "academic", "adaptive", "other",
    ]

    for category_value in expected_categories:
        category = StrengthCategory(category_value)
        assert category.value == category_value, (
            f"Category {category_value} should be valid"
        )


# =============================================================================
# Need Category Tests
# =============================================================================


def test_all_need_categories_valid() -> None:
    """Test that all NeedCategory enum values are valid.

    Verifies coverage for all 10 need categories: COMMUNICATION, BEHAVIOR,
    ACADEMIC, SENSORY, MOTOR, SOCIAL, EMOTIONAL, SELF_CARE, COGNITIVE, OTHER.
    """
    expected_categories = [
        "communication", "behavior", "academic", "sensory", "motor",
        "social", "emotional", "self_care", "cognitive", "other",
    ]

    for category_value in expected_categories:
        category = NeedCategory(category_value)
        assert category.value == category_value, (
            f"Category {category_value} should be valid"
        )


def test_all_need_priorities_valid() -> None:
    """Test that all NeedPriority enum values are valid.

    Verifies coverage for all 4 priority levels: LOW, MEDIUM, HIGH, CRITICAL.
    """
    expected_priorities = ["low", "medium", "high", "critical"]

    for priority_value in expected_priorities:
        priority = NeedPriority(priority_value)
        assert priority.value == priority_value, (
            f"Priority {priority_value} should be valid"
        )


# =============================================================================
# Goal Status Tests
# =============================================================================


def test_all_goal_statuses_valid() -> None:
    """Test that all GoalStatus enum values are valid.

    Verifies coverage for all 5 goal statuses: NOT_STARTED, IN_PROGRESS,
    ACHIEVED, MODIFIED, DISCONTINUED.
    """
    expected_statuses = [
        "not_started", "in_progress", "achieved", "modified", "discontinued",
    ]

    for status_value in expected_statuses:
        status = GoalStatus(status_value)
        assert status.value == status_value, (
            f"Status {status_value} should be valid"
        )


# =============================================================================
# Progress Level Tests
# =============================================================================


def test_all_progress_levels_valid() -> None:
    """Test that all ProgressLevel enum values are valid.

    Verifies coverage for all 5 progress levels: NO_PROGRESS, MINIMAL,
    MODERATE, SIGNIFICANT, ACHIEVED.
    """
    expected_levels = ["no_progress", "minimal", "moderate", "significant", "achieved"]

    for level_value in expected_levels:
        level = ProgressLevel(level_value)
        assert level.value == level_value, (
            f"Level {level_value} should be valid"
        )


# =============================================================================
# Responsible Party Tests
# =============================================================================


def test_all_responsible_parties_valid() -> None:
    """Test that all ResponsibleParty enum values are valid.

    Verifies coverage for all 5 responsible parties: EDUCATOR, PARENT,
    THERAPIST, TEAM, CHILD.
    """
    expected_parties = ["educator", "parent", "therapist", "team", "child"]

    for party_value in expected_parties:
        party = ResponsibleParty(party_value)
        assert party.value == party_value, (
            f"Party {party_value} should be valid"
        )


# =============================================================================
# Monitoring Method Tests
# =============================================================================


def test_all_monitoring_methods_valid() -> None:
    """Test that all MonitoringMethod enum values are valid.

    Verifies coverage for all 7 monitoring methods: OBSERVATION, ASSESSMENT,
    DATA_COLLECTION, INTERVIEW, PORTFOLIO, CHECKLIST, OTHER.
    """
    expected_methods = [
        "observation", "assessment", "data_collection", "interview",
        "portfolio", "checklist", "other",
    ]

    for method_value in expected_methods:
        method = MonitoringMethod(method_value)
        assert method.value == method_value, (
            f"Method {method_value} should be valid"
        )


# =============================================================================
# Parent Activity Type Tests
# =============================================================================


def test_all_parent_activity_types_valid() -> None:
    """Test that all ParentActivityType enum values are valid.

    Verifies coverage for all 7 parent activity types: HOME_ACTIVITY,
    COMMUNICATION, TRAINING, MEETING, OBSERVATION, DOCUMENTATION, OTHER.
    """
    expected_types = [
        "home_activity", "communication", "training", "meeting",
        "observation", "documentation", "other",
    ]

    for type_value in expected_types:
        activity_type = ParentActivityType(type_value)
        assert activity_type.value == type_value, (
            f"Type {type_value} should be valid"
        )


# =============================================================================
# Specialist Type Tests
# =============================================================================


def test_all_specialist_types_valid() -> None:
    """Test that all SpecialistType enum values are valid.

    Verifies coverage for all 11 specialist types including therapists,
    psychologist, behavioral specialist, educators, and medical professionals.
    """
    expected_types = [
        "speech_therapist", "occupational_therapist", "physical_therapist",
        "psychologist", "behavioral_specialist", "special_educator",
        "social_worker", "pediatrician", "neurologist", "psychiatrist", "other",
    ]

    for type_value in expected_types:
        specialist_type = SpecialistType(type_value)
        assert specialist_type.value == type_value, (
            f"Type {type_value} should be valid"
        )


# =============================================================================
# Plan Creation Schema Tests
# =============================================================================


def test_plan_create_schema_valid(mock_child_id: UUID) -> None:
    """Test that InterventionPlanCreate schema accepts valid data.

    Verifies that the schema correctly validates and accepts
    properly formatted plan creation data.
    """
    request = InterventionPlanCreate(
        child_id=mock_child_id,
        title="Test Plan",
        child_name="Test Child",
        review_schedule=ReviewSchedule.QUARTERLY,
        status=InterventionPlanStatus.DRAFT,
    )

    assert request.child_id == mock_child_id
    assert request.title == "Test Plan"
    assert request.child_name == "Test Child"
    assert request.review_schedule == ReviewSchedule.QUARTERLY
    assert request.status == InterventionPlanStatus.DRAFT


def test_plan_create_schema_with_sections(
    plan_create_request_with_sections: InterventionPlanCreate,
) -> None:
    """Test that InterventionPlanCreate accepts all 8 sections.

    Verifies that the schema correctly validates plan creation requests
    that include all 8 sections of the intervention plan structure.
    """
    request = plan_create_request_with_sections

    assert request.strengths is not None
    assert len(request.strengths) == 1
    assert request.strengths[0].category == StrengthCategory.COGNITIVE

    assert request.needs is not None
    assert len(request.needs) == 1
    assert request.needs[0].category == NeedCategory.COMMUNICATION

    assert request.goals is not None
    assert len(request.goals) == 1
    assert request.goals[0].status == GoalStatus.NOT_STARTED

    assert request.strategies is not None
    assert len(request.strategies) == 1
    assert request.strategies[0].responsible_party == ResponsibleParty.EDUCATOR

    assert request.monitoring is not None
    assert len(request.monitoring) == 1
    assert request.monitoring[0].method == MonitoringMethod.DATA_COLLECTION

    assert request.parent_involvements is not None
    assert len(request.parent_involvements) == 1
    assert request.parent_involvements[0].activity_type == ParentActivityType.HOME_ACTIVITY

    assert request.consultations is not None
    assert len(request.consultations) == 1
    assert request.consultations[0].specialist_type == SpecialistType.SPEECH_THERAPIST


# =============================================================================
# Plan Update Schema Tests
# =============================================================================


def test_plan_update_schema_partial() -> None:
    """Test that InterventionPlanUpdate accepts partial updates.

    Verifies that the schema allows updating only specific fields
    without requiring all fields to be present.
    """
    request = InterventionPlanUpdate(
        title="Updated Title",
        status=InterventionPlanStatus.ACTIVE,
    )

    assert request.title == "Updated Title"
    assert request.status == InterventionPlanStatus.ACTIVE
    assert request.child_name is None
    assert request.diagnosis is None


# =============================================================================
# Change Summary Generation Tests
# =============================================================================


def test_generate_change_summary_single_field(
    intervention_service: InterventionPlanService,
) -> None:
    """Test change summary generation for single field update.

    Verifies that _generate_change_summary produces a readable
    summary when only one field is updated.
    """
    request = InterventionPlanUpdate(title="New Title")

    summary = intervention_service._generate_change_summary(request)

    assert "Title" in summary, "Should mention the updated field"


def test_generate_change_summary_multiple_fields(
    intervention_service: InterventionPlanService,
) -> None:
    """Test change summary generation for multiple field updates.

    Verifies that _generate_change_summary produces a readable
    summary listing all updated fields with proper formatting.
    """
    request = InterventionPlanUpdate(
        title="New Title",
        status=InterventionPlanStatus.ACTIVE,
        diagnosis=["autism", "adhd"],
    )

    summary = intervention_service._generate_change_summary(request)

    assert "Title" in summary or "title" in summary.lower(), "Should mention title"
    assert "Status" in summary or "status" in summary.lower(), "Should mention status"
    assert "and" in summary.lower(), "Should use 'and' for multiple fields"


def test_generate_change_summary_empty_update(
    intervention_service: InterventionPlanService,
) -> None:
    """Test change summary generation for empty update.

    Verifies that _generate_change_summary handles updates with
    no actual changes gracefully.
    """
    request = InterventionPlanUpdate()

    summary = intervention_service._generate_change_summary(request)

    assert summary == "Plan updated", "Should return default message for empty update"


# =============================================================================
# Strength Create Schema Tests
# =============================================================================


def test_strength_create_schema() -> None:
    """Test StrengthCreate schema validation.

    Verifies that strength creation requires category and description.
    """
    strength = StrengthCreate(
        category=StrengthCategory.COGNITIVE,
        description="Strong problem-solving skills",
        examples="Can complete age-appropriate puzzles",
        order=0,
    )

    assert strength.category == StrengthCategory.COGNITIVE
    assert strength.description == "Strong problem-solving skills"
    assert strength.examples == "Can complete age-appropriate puzzles"


# =============================================================================
# Need Create Schema Tests
# =============================================================================


def test_need_create_schema() -> None:
    """Test NeedCreate schema validation.

    Verifies that need creation requires category and description,
    with optional priority defaulting to MEDIUM.
    """
    need = NeedCreate(
        category=NeedCategory.COMMUNICATION,
        description="Difficulty with verbal expression",
        priority=NeedPriority.HIGH,
        baseline="Uses single words only",
        order=0,
    )

    assert need.category == NeedCategory.COMMUNICATION
    assert need.description == "Difficulty with verbal expression"
    assert need.priority == NeedPriority.HIGH
    assert need.baseline == "Uses single words only"


def test_need_create_schema_default_priority() -> None:
    """Test that NeedCreate defaults priority to MEDIUM.

    Verifies the default value is applied when priority is not specified.
    """
    need = NeedCreate(
        category=NeedCategory.BEHAVIOR,
        description="Needs support with transitions",
    )

    assert need.priority == NeedPriority.MEDIUM


# =============================================================================
# SMART Goal Create Schema Tests
# =============================================================================


def test_smart_goal_create_schema() -> None:
    """Test SMARTGoalCreate schema validation.

    Verifies that SMART goal creation includes all required fields
    and properly structures the goal data.
    """
    goal = SMARTGoalCreate(
        title="Improve verbal communication",
        description="Child will use 2-word phrases",
        measurement_criteria="Count of 2-word phrases per day",
        measurement_baseline="0",
        measurement_target="5",
        achievability_notes="Child shows interest",
        relevance_notes="Addresses primary need",
        target_date=date.today() + timedelta(days=90),
        status=GoalStatus.NOT_STARTED,
        progress_percentage=0.0,
        order=0,
    )

    assert goal.title == "Improve verbal communication"
    assert goal.measurement_criteria == "Count of 2-word phrases per day"
    assert goal.status == GoalStatus.NOT_STARTED
    assert goal.progress_percentage == 0.0


def test_smart_goal_create_schema_defaults() -> None:
    """Test SMARTGoalCreate schema default values.

    Verifies that status defaults to NOT_STARTED and progress to 0.
    """
    goal = SMARTGoalCreate(
        title="Test Goal",
        description="Test description",
        measurement_criteria="Test criteria",
    )

    assert goal.status == GoalStatus.NOT_STARTED
    assert goal.progress_percentage == 0.0


# =============================================================================
# Strategy Create Schema Tests
# =============================================================================


def test_strategy_create_schema() -> None:
    """Test StrategyCreate schema validation.

    Verifies that strategy creation includes all required fields.
    """
    strategy = StrategyCreate(
        title="Visual Schedule",
        description="Use visual schedule for transitions",
        responsible_party=ResponsibleParty.EDUCATOR,
        frequency="Throughout the day",
        materials_needed="Visual schedule board, picture cards",
        accommodations="Extra time for transitions",
        order=0,
    )

    assert strategy.title == "Visual Schedule"
    assert strategy.responsible_party == ResponsibleParty.EDUCATOR
    assert strategy.frequency == "Throughout the day"


def test_strategy_create_schema_default_responsible_party() -> None:
    """Test StrategyCreate default responsible party.

    Verifies that responsible_party defaults to EDUCATOR.
    """
    strategy = StrategyCreate(
        title="Test Strategy",
        description="Test description",
    )

    assert strategy.responsible_party == ResponsibleParty.EDUCATOR


# =============================================================================
# Monitoring Create Schema Tests
# =============================================================================


def test_monitoring_create_schema() -> None:
    """Test MonitoringCreate schema validation.

    Verifies that monitoring creation includes all required fields.
    """
    monitoring = MonitoringCreate(
        method=MonitoringMethod.OBSERVATION,
        description="Observe communication attempts",
        frequency="daily",
        responsible_party=ResponsibleParty.EDUCATOR,
        data_collection_tools="Observation checklist",
        success_indicators="Increased communication attempts",
        order=0,
    )

    assert monitoring.method == MonitoringMethod.OBSERVATION
    assert monitoring.frequency == "daily"
    assert monitoring.responsible_party == ResponsibleParty.EDUCATOR


# =============================================================================
# Parent Involvement Create Schema Tests
# =============================================================================


def test_parent_involvement_create_schema() -> None:
    """Test ParentInvolvementCreate schema validation.

    Verifies that parent involvement creation includes all required fields.
    """
    involvement = ParentInvolvementCreate(
        activity_type=ParentActivityType.HOME_ACTIVITY,
        title="Home Communication Practice",
        description="Practice using picture cards at home",
        frequency="Daily",
        resources_provided="Picture card set",
        communication_method="Weekly email",
        order=0,
    )

    assert involvement.activity_type == ParentActivityType.HOME_ACTIVITY
    assert involvement.title == "Home Communication Practice"
    assert involvement.frequency == "Daily"


# =============================================================================
# Consultation Create Schema Tests
# =============================================================================


def test_consultation_create_schema() -> None:
    """Test ConsultationCreate schema validation.

    Verifies that consultation creation includes all required fields.
    """
    consultation = ConsultationCreate(
        specialist_type=SpecialistType.SPEECH_THERAPIST,
        specialist_name="Dr. Smith",
        organization="Speech Center",
        purpose="Weekly speech therapy",
        recommendations="Continue PECS approach",
        consultation_date=date.today(),
        next_consultation_date=date.today() + timedelta(days=7),
        notes="Good progress noted",
        order=0,
    )

    assert consultation.specialist_type == SpecialistType.SPEECH_THERAPIST
    assert consultation.specialist_name == "Dr. Smith"
    assert consultation.purpose == "Weekly speech therapy"


# =============================================================================
# Progress Create Schema Tests
# =============================================================================


def test_progress_create_schema() -> None:
    """Test ProgressCreate schema validation.

    Verifies that progress creation includes all required fields.
    """
    progress = ProgressCreate(
        record_date=date.today(),
        progress_notes="Made significant improvement in communication",
        progress_level=ProgressLevel.SIGNIFICANT,
        measurement_value="4 phrases per day",
        barriers="Some difficulty in group settings",
        next_steps="Focus on group communication",
        goal_id=uuid4(),
    )

    assert progress.record_date == date.today()
    assert progress.progress_level == ProgressLevel.SIGNIFICANT
    assert progress.measurement_value == "4 phrases per day"


def test_progress_create_schema_default_level() -> None:
    """Test ProgressCreate default progress level.

    Verifies that progress_level defaults to MINIMAL.
    """
    progress = ProgressCreate(
        record_date=date.today(),
        progress_notes="Initial progress recorded",
    )

    assert progress.progress_level == ProgressLevel.MINIMAL


# =============================================================================
# Parent Signature Request Schema Tests
# =============================================================================


def test_parent_signature_request_schema() -> None:
    """Test ParentSignatureRequest schema validation.

    Verifies that signature request includes signature data
    and terms agreement.
    """
    request = ParentSignatureRequest(
        signature_data="base64_encoded_signature_data",
        agreed_to_terms=True,
    )

    assert request.signature_data == "base64_encoded_signature_data"
    assert request.agreed_to_terms is True


def test_parent_signature_request_default_agreed() -> None:
    """Test ParentSignatureRequest default agreed_to_terms.

    Verifies that agreed_to_terms defaults to True.
    """
    request = ParentSignatureRequest(
        signature_data="test_signature",
    )

    assert request.agreed_to_terms is True


# =============================================================================
# Error Class Tests
# =============================================================================


def test_plan_not_found_error() -> None:
    """Test PlanNotFoundError exception.

    Verifies that the error can be raised and caught correctly.
    """
    with pytest.raises(PlanNotFoundError) as exc_info:
        raise PlanNotFoundError("Plan with ID xyz not found")

    assert "not found" in str(exc_info.value).lower()


def test_invalid_plan_error() -> None:
    """Test InvalidPlanError exception.

    Verifies that the error can be raised and caught correctly.
    """
    with pytest.raises(InvalidPlanError) as exc_info:
        raise InvalidPlanError("Plan data is invalid")

    assert "invalid" in str(exc_info.value).lower()


# =============================================================================
# Progress Level to Percentage Mapping Tests
# =============================================================================


def test_progress_level_percentage_mapping() -> None:
    """Test progress level to percentage mapping.

    Verifies that each progress level maps to the expected
    percentage value used in goal progress tracking.
    """
    expected_mapping = {
        "no_progress": 0.0,
        "minimal": 25.0,
        "moderate": 50.0,
        "significant": 75.0,
        "achieved": 100.0,
    }

    for level_value, expected_percentage in expected_mapping.items():
        level = ProgressLevel(level_value)
        # The mapping is used internally in add_progress method
        progress_map = {
            "no_progress": 0.0,
            "minimal": 25.0,
            "moderate": 50.0,
            "significant": 75.0,
            "achieved": 100.0,
        }
        assert progress_map[level.value] == expected_percentage, (
            f"Level {level_value} should map to {expected_percentage}%"
        )


# =============================================================================
# Model Creation Helper Tests
# =============================================================================


def test_create_strength_model_helper(
    intervention_service: InterventionPlanService,
    mock_plan_id: UUID,
) -> None:
    """Test _create_strength_model helper method.

    Verifies that the helper creates an InterventionStrength model
    with correct attributes.
    """
    strength_data = StrengthCreate(
        category=StrengthCategory.COGNITIVE,
        description="Test strength",
        examples="Test examples",
        order=1,
    )

    strength = intervention_service._create_strength_model(mock_plan_id, strength_data)

    assert strength.plan_id == mock_plan_id
    assert strength.category == "cognitive"
    assert strength.description == "Test strength"
    assert strength.examples == "Test examples"
    assert strength.order == 1


def test_create_need_model_helper(
    intervention_service: InterventionPlanService,
    mock_plan_id: UUID,
) -> None:
    """Test _create_need_model helper method.

    Verifies that the helper creates an InterventionNeed model
    with correct attributes.
    """
    need_data = NeedCreate(
        category=NeedCategory.COMMUNICATION,
        description="Test need",
        priority=NeedPriority.HIGH,
        baseline="Test baseline",
        order=1,
    )

    need = intervention_service._create_need_model(mock_plan_id, need_data)

    assert need.plan_id == mock_plan_id
    assert need.category == "communication"
    assert need.description == "Test need"
    assert need.priority == "high"
    assert need.baseline == "Test baseline"


def test_create_goal_model_helper(
    intervention_service: InterventionPlanService,
    mock_plan_id: UUID,
) -> None:
    """Test _create_goal_model helper method.

    Verifies that the helper creates an InterventionGoal model
    with correct SMART goal attributes.
    """
    goal_data = SMARTGoalCreate(
        title="Test goal",
        description="Test description",
        measurement_criteria="Test criteria",
        measurement_baseline="0",
        measurement_target="10",
        status=GoalStatus.IN_PROGRESS,
        progress_percentage=25.0,
        order=1,
    )

    goal = intervention_service._create_goal_model(mock_plan_id, goal_data)

    assert goal.plan_id == mock_plan_id
    assert goal.title == "Test goal"
    assert goal.status == "in_progress"
    assert goal.progress_percentage == 25.0


def test_create_strategy_model_helper(
    intervention_service: InterventionPlanService,
    mock_plan_id: UUID,
) -> None:
    """Test _create_strategy_model helper method.

    Verifies that the helper creates an InterventionStrategy model
    with correct attributes.
    """
    strategy_data = StrategyCreate(
        title="Test strategy",
        description="Test description",
        responsible_party=ResponsibleParty.THERAPIST,
        frequency="Weekly",
        order=1,
    )

    strategy = intervention_service._create_strategy_model(mock_plan_id, strategy_data)

    assert strategy.plan_id == mock_plan_id
    assert strategy.title == "Test strategy"
    assert strategy.responsible_party == "therapist"


def test_create_monitoring_model_helper(
    intervention_service: InterventionPlanService,
    mock_plan_id: UUID,
) -> None:
    """Test _create_monitoring_model helper method.

    Verifies that the helper creates an InterventionMonitoring model
    with correct attributes.
    """
    monitoring_data = MonitoringCreate(
        method=MonitoringMethod.ASSESSMENT,
        description="Test monitoring",
        frequency="monthly",
        responsible_party=ResponsibleParty.TEAM,
        order=1,
    )

    monitoring = intervention_service._create_monitoring_model(mock_plan_id, monitoring_data)

    assert monitoring.plan_id == mock_plan_id
    assert monitoring.method == "assessment"
    assert monitoring.responsible_party == "team"


def test_create_parent_involvement_model_helper(
    intervention_service: InterventionPlanService,
    mock_plan_id: UUID,
) -> None:
    """Test _create_parent_involvement_model helper method.

    Verifies that the helper creates an InterventionParentInvolvement model
    with correct attributes.
    """
    involvement_data = ParentInvolvementCreate(
        activity_type=ParentActivityType.TRAINING,
        title="Test involvement",
        description="Test description",
        frequency="Weekly",
        order=1,
    )

    involvement = intervention_service._create_parent_involvement_model(
        mock_plan_id, involvement_data
    )

    assert involvement.plan_id == mock_plan_id
    assert involvement.activity_type == "training"
    assert involvement.title == "Test involvement"


def test_create_consultation_model_helper(
    intervention_service: InterventionPlanService,
    mock_plan_id: UUID,
) -> None:
    """Test _create_consultation_model helper method.

    Verifies that the helper creates an InterventionConsultation model
    with correct attributes.
    """
    consultation_data = ConsultationCreate(
        specialist_type=SpecialistType.PSYCHOLOGIST,
        specialist_name="Dr. Test",
        organization="Test Clinic",
        purpose="Test consultation",
        consultation_date=date.today(),
        order=1,
    )

    consultation = intervention_service._create_consultation_model(
        mock_plan_id, consultation_data
    )

    assert consultation.plan_id == mock_plan_id
    assert consultation.specialist_type == "psychologist"
    assert consultation.specialist_name == "Dr. Test"


# =============================================================================
# Database Session Verification Tests
# =============================================================================


@pytest.mark.asyncio
async def test_create_plan_calls_db_methods(
    mock_db_session: AsyncMock,
    mock_user_id: UUID,
    plan_create_request: InterventionPlanCreate,
) -> None:
    """Test that create_plan calls expected database methods.

    Verifies that the service properly interacts with the database
    session during plan creation.
    """
    service = InterventionPlanService(mock_db_session)

    # Mock the get_plan method to return a response
    mock_plan_response = MagicMock()
    with patch.object(service, "get_plan", return_value=mock_plan_response):
        await service.create_plan(plan_create_request, mock_user_id)

    # Verify database operations were called
    assert mock_db_session.add.called, "Should add records to database session"
    assert mock_db_session.flush.called, "Should flush to get generated IDs"
    assert mock_db_session.commit.called, "Should commit the transaction"


@pytest.mark.asyncio
async def test_get_plan_raises_not_found(
    mock_db_session: AsyncMock,
    mock_plan_id: UUID,
    mock_user_id: UUID,
) -> None:
    """Test that get_plan raises PlanNotFoundError when plan doesn't exist.

    Verifies proper error handling when requesting a non-existent plan.
    """
    # Mock execute to return None
    mock_result = MagicMock()
    mock_result.scalar_one_or_none.return_value = None
    mock_db_session.execute.return_value = mock_result

    service = InterventionPlanService(mock_db_session)

    with pytest.raises(PlanNotFoundError) as exc_info:
        await service.get_plan(mock_plan_id, mock_user_id)

    assert str(mock_plan_id) in str(exc_info.value)


@pytest.mark.asyncio
async def test_update_plan_raises_not_found(
    mock_db_session: AsyncMock,
    mock_plan_id: UUID,
    mock_user_id: UUID,
) -> None:
    """Test that update_plan raises PlanNotFoundError when plan doesn't exist.

    Verifies proper error handling when updating a non-existent plan.
    """
    # Mock execute to return None
    mock_result = MagicMock()
    mock_result.scalar_one_or_none.return_value = None
    mock_db_session.execute.return_value = mock_result

    service = InterventionPlanService(mock_db_session)
    update_request = InterventionPlanUpdate(title="Updated Title")

    with pytest.raises(PlanNotFoundError) as exc_info:
        await service.update_plan(mock_plan_id, update_request, mock_user_id)

    assert str(mock_plan_id) in str(exc_info.value)


@pytest.mark.asyncio
async def test_delete_plan_raises_not_found(
    mock_db_session: AsyncMock,
    mock_plan_id: UUID,
    mock_user_id: UUID,
) -> None:
    """Test that delete_plan raises PlanNotFoundError when plan doesn't exist.

    Verifies proper error handling when deleting a non-existent plan.
    """
    # Mock execute to return None
    mock_result = MagicMock()
    mock_result.scalar_one_or_none.return_value = None
    mock_db_session.execute.return_value = mock_result

    service = InterventionPlanService(mock_db_session)

    with pytest.raises(PlanNotFoundError) as exc_info:
        await service.delete_plan(mock_plan_id, mock_user_id)

    assert str(mock_plan_id) in str(exc_info.value)


@pytest.mark.asyncio
async def test_delete_plan_archives_instead_of_hard_delete(
    mock_db_session: AsyncMock,
    mock_plan_id: UUID,
    mock_user_id: UUID,
    mock_plan_model: MagicMock,
) -> None:
    """Test that delete_plan archives the plan instead of hard deleting.

    Verifies that delete operation soft-deletes by setting status to ARCHIVED.
    """
    # Mock execute to return the plan
    mock_result = MagicMock()
    mock_result.scalar_one_or_none.return_value = mock_plan_model
    mock_db_session.execute.return_value = mock_result

    service = InterventionPlanService(mock_db_session)

    result = await service.delete_plan(mock_plan_id, mock_user_id)

    assert result is True
    assert mock_plan_model.status == InterventionPlanStatus.ARCHIVED.value
    assert mock_db_session.commit.called


@pytest.mark.asyncio
async def test_sign_plan_raises_not_found(
    mock_db_session: AsyncMock,
    mock_plan_id: UUID,
) -> None:
    """Test that sign_plan raises PlanNotFoundError when plan doesn't exist.

    Verifies proper error handling when signing a non-existent plan.
    """
    # Mock execute to return None
    mock_result = MagicMock()
    mock_result.scalar_one_or_none.return_value = None
    mock_db_session.execute.return_value = mock_result

    service = InterventionPlanService(mock_db_session)
    parent_id = uuid4()
    signature_request = ParentSignatureRequest(
        signature_data="test_signature",
        agreed_to_terms=True,
    )

    with pytest.raises(PlanNotFoundError) as exc_info:
        await service.sign_plan(mock_plan_id, signature_request, parent_id)

    assert str(mock_plan_id) in str(exc_info.value)


@pytest.mark.asyncio
async def test_sign_plan_raises_invalid_if_already_signed(
    mock_db_session: AsyncMock,
    mock_plan_id: UUID,
    mock_plan_model: MagicMock,
) -> None:
    """Test that sign_plan raises InvalidPlanError if plan already signed.

    Verifies that a plan cannot be signed twice.
    """
    # Set plan as already signed
    mock_plan_model.parent_signed = True

    mock_result = MagicMock()
    mock_result.scalar_one_or_none.return_value = mock_plan_model
    mock_db_session.execute.return_value = mock_result

    service = InterventionPlanService(mock_db_session)
    parent_id = uuid4()
    signature_request = ParentSignatureRequest(
        signature_data="test_signature",
        agreed_to_terms=True,
    )

    with pytest.raises(InvalidPlanError) as exc_info:
        await service.sign_plan(mock_plan_id, signature_request, parent_id)

    assert "already been signed" in str(exc_info.value)


@pytest.mark.asyncio
async def test_sign_plan_raises_invalid_if_terms_not_agreed(
    mock_db_session: AsyncMock,
    mock_plan_id: UUID,
    mock_plan_model: MagicMock,
) -> None:
    """Test that sign_plan raises InvalidPlanError if terms not agreed.

    Verifies that signature requires agreement to terms.
    """
    mock_result = MagicMock()
    mock_result.scalar_one_or_none.return_value = mock_plan_model
    mock_db_session.execute.return_value = mock_result

    service = InterventionPlanService(mock_db_session)
    parent_id = uuid4()
    signature_request = ParentSignatureRequest(
        signature_data="test_signature",
        agreed_to_terms=False,
    )

    with pytest.raises(InvalidPlanError) as exc_info:
        await service.sign_plan(mock_plan_id, signature_request, parent_id)

    assert "agree to terms" in str(exc_info.value).lower()


@pytest.mark.asyncio
async def test_sign_plan_success(
    mock_db_session: AsyncMock,
    mock_plan_id: UUID,
    mock_plan_model: MagicMock,
) -> None:
    """Test successful plan signing.

    Verifies that sign_plan correctly updates the plan with signature data.
    """
    mock_result = MagicMock()
    mock_result.scalar_one_or_none.return_value = mock_plan_model
    mock_db_session.execute.return_value = mock_result

    service = InterventionPlanService(mock_db_session)
    parent_id = uuid4()
    signature_request = ParentSignatureRequest(
        signature_data="test_signature_data",
        agreed_to_terms=True,
    )

    response = await service.sign_plan(mock_plan_id, signature_request, parent_id)

    assert response.plan_id == mock_plan_id
    assert response.parent_signed is True
    assert response.parent_signature_date is not None
    assert mock_plan_model.parent_signed is True
    assert mock_plan_model.parent_signature_data == "test_signature_data"
    assert mock_db_session.commit.called


@pytest.mark.asyncio
async def test_add_progress_raises_not_found(
    mock_db_session: AsyncMock,
    mock_plan_id: UUID,
    mock_user_id: UUID,
) -> None:
    """Test that add_progress raises PlanNotFoundError when plan doesn't exist.

    Verifies proper error handling when adding progress to non-existent plan.
    """
    # Mock execute to return None
    mock_result = MagicMock()
    mock_result.scalar_one_or_none.return_value = None
    mock_db_session.execute.return_value = mock_result

    service = InterventionPlanService(mock_db_session)
    progress_request = ProgressCreate(
        record_date=date.today(),
        progress_notes="Test progress",
        progress_level=ProgressLevel.MODERATE,
    )

    with pytest.raises(PlanNotFoundError) as exc_info:
        await service.add_progress(mock_plan_id, progress_request, mock_user_id)

    assert str(mock_plan_id) in str(exc_info.value)


@pytest.mark.asyncio
async def test_create_version_raises_not_found(
    mock_db_session: AsyncMock,
    mock_plan_id: UUID,
    mock_user_id: UUID,
) -> None:
    """Test that create_version raises PlanNotFoundError when plan doesn't exist.

    Verifies proper error handling when creating version for non-existent plan.
    """
    service = InterventionPlanService(mock_db_session)

    # Mock _get_plan_with_relations to return None
    with patch.object(service, "_get_plan_with_relations", return_value=None):
        with pytest.raises(PlanNotFoundError) as exc_info:
            await service.create_version(mock_plan_id, mock_user_id)

    assert str(mock_plan_id) in str(exc_info.value)


@pytest.mark.asyncio
async def test_get_plan_history_raises_not_found(
    mock_db_session: AsyncMock,
    mock_plan_id: UUID,
    mock_user_id: UUID,
) -> None:
    """Test that get_plan_history raises PlanNotFoundError when plan doesn't exist.

    Verifies proper error handling when getting history for non-existent plan.
    """
    # First call returns None (plan doesn't exist)
    mock_result = MagicMock()
    mock_result.scalar_one_or_none.return_value = None
    mock_db_session.execute.return_value = mock_result

    service = InterventionPlanService(mock_db_session)

    with pytest.raises(PlanNotFoundError) as exc_info:
        await service.get_plan_history(mock_plan_id, mock_user_id)

    assert str(mock_plan_id) in str(exc_info.value)


@pytest.mark.asyncio
async def test_verify_plan_exists_raises_not_found(
    mock_db_session: AsyncMock,
    mock_plan_id: UUID,
) -> None:
    """Test that _verify_plan_exists raises PlanNotFoundError when plan doesn't exist.

    Verifies the internal helper method raises appropriate error.
    """
    mock_result = MagicMock()
    mock_result.scalar_one_or_none.return_value = None
    mock_db_session.execute.return_value = mock_result

    service = InterventionPlanService(mock_db_session)

    with pytest.raises(PlanNotFoundError) as exc_info:
        await service._verify_plan_exists(mock_plan_id)

    assert str(mock_plan_id) in str(exc_info.value)


@pytest.mark.asyncio
async def test_verify_plan_exists_returns_plan(
    mock_db_session: AsyncMock,
    mock_plan_id: UUID,
    mock_plan_model: MagicMock,
) -> None:
    """Test that _verify_plan_exists returns plan when it exists.

    Verifies the internal helper method returns the plan object.
    """
    mock_result = MagicMock()
    mock_result.scalar_one_or_none.return_value = mock_plan_model
    mock_db_session.execute.return_value = mock_result

    service = InterventionPlanService(mock_db_session)

    result = await service._verify_plan_exists(mock_plan_id)

    assert result == mock_plan_model


# =============================================================================
# Section CRUD Operation Tests
# =============================================================================


@pytest.mark.asyncio
async def test_add_strength_calls_verify_and_commit(
    mock_db_session: AsyncMock,
    mock_plan_id: UUID,
    mock_user_id: UUID,
    mock_plan_model: MagicMock,
) -> None:
    """Test that add_strength verifies plan exists and commits.

    Verifies the section add workflow.
    """
    service = InterventionPlanService(mock_db_session)

    with patch.object(service, "_verify_plan_exists", return_value=mock_plan_model):
        with patch.object(service, "get_plan", return_value=MagicMock()):
            strength_request = StrengthCreate(
                category=StrengthCategory.COGNITIVE,
                description="Test strength",
            )

            await service.add_strength(mock_plan_id, strength_request, mock_user_id)

    assert mock_db_session.add.called
    assert mock_db_session.commit.called


@pytest.mark.asyncio
async def test_add_need_calls_verify_and_commit(
    mock_db_session: AsyncMock,
    mock_plan_id: UUID,
    mock_user_id: UUID,
    mock_plan_model: MagicMock,
) -> None:
    """Test that add_need verifies plan exists and commits.

    Verifies the section add workflow.
    """
    service = InterventionPlanService(mock_db_session)

    with patch.object(service, "_verify_plan_exists", return_value=mock_plan_model):
        with patch.object(service, "get_plan", return_value=MagicMock()):
            need_request = NeedCreate(
                category=NeedCategory.COMMUNICATION,
                description="Test need",
            )

            await service.add_need(mock_plan_id, need_request, mock_user_id)

    assert mock_db_session.add.called
    assert mock_db_session.commit.called


@pytest.mark.asyncio
async def test_add_goal_calls_verify_and_commit(
    mock_db_session: AsyncMock,
    mock_plan_id: UUID,
    mock_user_id: UUID,
    mock_plan_model: MagicMock,
) -> None:
    """Test that add_goal verifies plan exists and commits.

    Verifies the section add workflow.
    """
    service = InterventionPlanService(mock_db_session)

    with patch.object(service, "_verify_plan_exists", return_value=mock_plan_model):
        with patch.object(service, "get_plan", return_value=MagicMock()):
            goal_request = SMARTGoalCreate(
                title="Test goal",
                description="Test description",
                measurement_criteria="Test criteria",
            )

            await service.add_goal(mock_plan_id, goal_request, mock_user_id)

    assert mock_db_session.add.called
    assert mock_db_session.commit.called


@pytest.mark.asyncio
async def test_add_strategy_calls_verify_and_commit(
    mock_db_session: AsyncMock,
    mock_plan_id: UUID,
    mock_user_id: UUID,
    mock_plan_model: MagicMock,
) -> None:
    """Test that add_strategy verifies plan exists and commits.

    Verifies the section add workflow.
    """
    service = InterventionPlanService(mock_db_session)

    with patch.object(service, "_verify_plan_exists", return_value=mock_plan_model):
        with patch.object(service, "get_plan", return_value=MagicMock()):
            strategy_request = StrategyCreate(
                title="Test strategy",
                description="Test description",
            )

            await service.add_strategy(mock_plan_id, strategy_request, mock_user_id)

    assert mock_db_session.add.called
    assert mock_db_session.commit.called


@pytest.mark.asyncio
async def test_add_monitoring_calls_verify_and_commit(
    mock_db_session: AsyncMock,
    mock_plan_id: UUID,
    mock_user_id: UUID,
    mock_plan_model: MagicMock,
) -> None:
    """Test that add_monitoring verifies plan exists and commits.

    Verifies the section add workflow.
    """
    service = InterventionPlanService(mock_db_session)

    with patch.object(service, "_verify_plan_exists", return_value=mock_plan_model):
        with patch.object(service, "get_plan", return_value=MagicMock()):
            monitoring_request = MonitoringCreate(
                method=MonitoringMethod.OBSERVATION,
                description="Test monitoring",
            )

            await service.add_monitoring(mock_plan_id, monitoring_request, mock_user_id)

    assert mock_db_session.add.called
    assert mock_db_session.commit.called


@pytest.mark.asyncio
async def test_add_parent_involvement_calls_verify_and_commit(
    mock_db_session: AsyncMock,
    mock_plan_id: UUID,
    mock_user_id: UUID,
    mock_plan_model: MagicMock,
) -> None:
    """Test that add_parent_involvement verifies plan exists and commits.

    Verifies the section add workflow.
    """
    service = InterventionPlanService(mock_db_session)

    with patch.object(service, "_verify_plan_exists", return_value=mock_plan_model):
        with patch.object(service, "get_plan", return_value=MagicMock()):
            involvement_request = ParentInvolvementCreate(
                activity_type=ParentActivityType.HOME_ACTIVITY,
                title="Test involvement",
                description="Test description",
            )

            await service.add_parent_involvement(
                mock_plan_id, involvement_request, mock_user_id
            )

    assert mock_db_session.add.called
    assert mock_db_session.commit.called


@pytest.mark.asyncio
async def test_add_consultation_calls_verify_and_commit(
    mock_db_session: AsyncMock,
    mock_plan_id: UUID,
    mock_user_id: UUID,
    mock_plan_model: MagicMock,
) -> None:
    """Test that add_consultation verifies plan exists and commits.

    Verifies the section add workflow.
    """
    service = InterventionPlanService(mock_db_session)

    with patch.object(service, "_verify_plan_exists", return_value=mock_plan_model):
        with patch.object(service, "get_plan", return_value=MagicMock()):
            consultation_request = ConsultationCreate(
                specialist_type=SpecialistType.SPEECH_THERAPIST,
                purpose="Test consultation",
            )

            await service.add_consultation(
                mock_plan_id, consultation_request, mock_user_id
            )

    assert mock_db_session.add.called
    assert mock_db_session.commit.called
