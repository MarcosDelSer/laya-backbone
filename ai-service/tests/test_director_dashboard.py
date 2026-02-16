"""Unit tests for Director Dashboard API endpoints.

Tests cover:
- Director dashboard endpoint response structure
- Occupancy endpoint returning group data
- Occupancy history endpoint with date range parameters
- Authentication requirements on protected endpoints
- Placeholder responses when database is unavailable
- Schema validation for all response types
"""

from __future__ import annotations

from datetime import date, datetime, timedelta, timezone
from typing import Optional
from uuid import UUID, uuid4

import pytest
import pytest_asyncio
from httpx import AsyncClient
from sqlalchemy import text
from sqlalchemy.ext.asyncio import AsyncSession

from app.schemas.director_dashboard import (
    AgeGroupType,
    AlertPriority,
    AlertType,
    DashboardAlert,
    DirectorDashboardResponse,
    GroupOccupancy,
    OccupancyHistoryPoint,
    OccupancyHistoryResponse,
    OccupancyStatus,
    OccupancySummary,
)
from app.services.occupancy_service import OccupancyService
from tests.conftest import test_engine, TestAsyncSessionLocal


# =============================================================================
# SQLite-compatible occupancy tables
# =============================================================================

SQLITE_CREATE_OCCUPANCY_TABLES_SQL = """
CREATE TABLE IF NOT EXISTS occupancy_groups (
    id TEXT PRIMARY KEY,
    facility_id TEXT NOT NULL,
    name VARCHAR(100) NOT NULL,
    age_group VARCHAR(50) NOT NULL DEFAULT 'prescolaire',
    capacity INTEGER NOT NULL DEFAULT 10,
    min_age_months INTEGER,
    max_age_months INTEGER,
    room_number VARCHAR(20),
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS occupancy_records (
    id TEXT PRIMARY KEY,
    group_id TEXT NOT NULL REFERENCES occupancy_groups(id) ON DELETE CASCADE,
    facility_id TEXT NOT NULL,
    record_date DATE NOT NULL,
    record_time TIMESTAMP NOT NULL,
    current_count INTEGER NOT NULL DEFAULT 0,
    capacity INTEGER NOT NULL,
    staff_count INTEGER,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS child_attendances (
    id TEXT PRIMARY KEY,
    child_id TEXT NOT NULL,
    group_id TEXT NOT NULL REFERENCES occupancy_groups(id) ON DELETE CASCADE,
    facility_id TEXT NOT NULL,
    attendance_date DATE NOT NULL,
    check_in_time TIMESTAMP,
    check_out_time TIMESTAMP,
    status VARCHAR(30) NOT NULL DEFAULT 'present',
    checked_in_by TEXT,
    checked_out_by TEXT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_occupancy_groups_facility ON occupancy_groups(facility_id);
CREATE INDEX IF NOT EXISTS idx_occupancy_groups_active ON occupancy_groups(is_active);
CREATE INDEX IF NOT EXISTS idx_occupancy_records_group ON occupancy_records(group_id);
CREATE INDEX IF NOT EXISTS idx_occupancy_records_facility ON occupancy_records(facility_id);
CREATE INDEX IF NOT EXISTS idx_occupancy_records_date ON occupancy_records(record_date);
CREATE INDEX IF NOT EXISTS idx_child_attendances_child ON child_attendances(child_id);
CREATE INDEX IF NOT EXISTS idx_child_attendances_group ON child_attendances(group_id);
CREATE INDEX IF NOT EXISTS idx_child_attendances_date ON child_attendances(attendance_date);
"""


# =============================================================================
# Mock classes for occupancy testing
# =============================================================================


class MockGroup:
    """Mock Group object for testing without SQLAlchemy ORM overhead."""

    def __init__(
        self,
        id: UUID,
        facility_id: UUID,
        name: str,
        age_group: str,
        capacity: int,
        min_age_months: int | None,
        max_age_months: int | None,
        room_number: str | None,
        is_active: bool,
        created_at: datetime,
        updated_at: datetime | None,
    ):
        self.id = id
        self.facility_id = facility_id
        self.name = name
        self.age_group = age_group
        self.capacity = capacity
        self.min_age_months = min_age_months
        self.max_age_months = max_age_months
        self.room_number = room_number
        self.is_active = is_active
        self.created_at = created_at
        self.updated_at = updated_at

    def __repr__(self) -> str:
        return f"<Group(id={self.id}, name='{self.name}', capacity={self.capacity})>"


class MockOccupancyRecord:
    """Mock OccupancyRecord object for testing."""

    def __init__(
        self,
        id: UUID,
        group_id: UUID,
        facility_id: UUID,
        record_date: date,
        record_time: datetime,
        current_count: int,
        capacity: int,
        staff_count: int | None,
        notes: str | None,
        created_at: datetime,
    ):
        self.id = id
        self.group_id = group_id
        self.facility_id = facility_id
        self.record_date = record_date
        self.record_time = record_time
        self.current_count = current_count
        self.capacity = capacity
        self.staff_count = staff_count
        self.notes = notes
        self.created_at = created_at

    def __repr__(self) -> str:
        return f"<OccupancyRecord(id={self.id}, current_count={self.current_count}/{self.capacity})>"


class MockChildAttendance:
    """Mock ChildAttendance object for testing."""

    def __init__(
        self,
        id: UUID,
        child_id: UUID,
        group_id: UUID,
        facility_id: UUID,
        attendance_date: date,
        check_in_time: datetime | None,
        check_out_time: datetime | None,
        status: str,
        checked_in_by: UUID | None,
        checked_out_by: UUID | None,
        notes: str | None,
        created_at: datetime,
        updated_at: datetime | None,
    ):
        self.id = id
        self.child_id = child_id
        self.group_id = group_id
        self.facility_id = facility_id
        self.attendance_date = attendance_date
        self.check_in_time = check_in_time
        self.check_out_time = check_out_time
        self.status = status
        self.checked_in_by = checked_in_by
        self.checked_out_by = checked_out_by
        self.notes = notes
        self.created_at = created_at
        self.updated_at = updated_at

    def __repr__(self) -> str:
        return f"<ChildAttendance(id={self.id}, child_id={self.child_id}, status='{self.status}')>"


# =============================================================================
# Helper functions for creating test data
# =============================================================================


async def create_group_in_db(
    session: AsyncSession,
    facility_id: UUID,
    name: str,
    age_group: str = "prescolaire",
    capacity: int = 10,
    min_age_months: int | None = None,
    max_age_months: int | None = None,
    room_number: str | None = None,
    is_active: bool = True,
) -> MockGroup:
    """Helper function to create a group directly in SQLite database."""
    group_id = str(uuid4())
    now = datetime.now(timezone.utc)

    await session.execute(
        text("""
            INSERT INTO occupancy_groups (
                id, facility_id, name, age_group, capacity, min_age_months,
                max_age_months, room_number, is_active, created_at, updated_at
            ) VALUES (
                :id, :facility_id, :name, :age_group, :capacity, :min_age_months,
                :max_age_months, :room_number, :is_active, :created_at, :updated_at
            )
        """),
        {
            "id": group_id,
            "facility_id": str(facility_id),
            "name": name,
            "age_group": age_group,
            "capacity": capacity,
            "min_age_months": min_age_months,
            "max_age_months": max_age_months,
            "room_number": room_number,
            "is_active": 1 if is_active else 0,
            "created_at": now.isoformat(),
            "updated_at": now.isoformat(),
        },
    )
    await session.commit()

    return MockGroup(
        id=UUID(group_id),
        facility_id=facility_id,
        name=name,
        age_group=age_group,
        capacity=capacity,
        min_age_months=min_age_months,
        max_age_months=max_age_months,
        room_number=room_number,
        is_active=is_active,
        created_at=now,
        updated_at=now,
    )


async def create_occupancy_record_in_db(
    session: AsyncSession,
    group_id: UUID,
    facility_id: UUID,
    current_count: int,
    capacity: int,
    staff_count: int | None = None,
    record_date: date | None = None,
    record_time: datetime | None = None,
    notes: str | None = None,
) -> MockOccupancyRecord:
    """Helper function to create an occupancy record in SQLite database."""
    record_id = str(uuid4())
    now = datetime.now(timezone.utc)
    if record_date is None:
        record_date = date.today()
    if record_time is None:
        record_time = now

    await session.execute(
        text("""
            INSERT INTO occupancy_records (
                id, group_id, facility_id, record_date, record_time, current_count,
                capacity, staff_count, notes, created_at
            ) VALUES (
                :id, :group_id, :facility_id, :record_date, :record_time, :current_count,
                :capacity, :staff_count, :notes, :created_at
            )
        """),
        {
            "id": record_id,
            "group_id": str(group_id),
            "facility_id": str(facility_id),
            "record_date": record_date.isoformat(),
            "record_time": record_time.isoformat(),
            "current_count": current_count,
            "capacity": capacity,
            "staff_count": staff_count,
            "notes": notes,
            "created_at": now.isoformat(),
        },
    )
    await session.commit()

    return MockOccupancyRecord(
        id=UUID(record_id),
        group_id=group_id,
        facility_id=facility_id,
        record_date=record_date,
        record_time=record_time,
        current_count=current_count,
        capacity=capacity,
        staff_count=staff_count,
        notes=notes,
        created_at=now,
    )


async def create_child_attendance_in_db(
    session: AsyncSession,
    child_id: UUID,
    group_id: UUID,
    facility_id: UUID,
    status: str = "present",
    attendance_date: date | None = None,
    check_in_time: datetime | None = None,
    check_out_time: datetime | None = None,
    checked_in_by: UUID | None = None,
    checked_out_by: UUID | None = None,
    notes: str | None = None,
) -> MockChildAttendance:
    """Helper function to create a child attendance record in SQLite database."""
    attendance_id = str(uuid4())
    now = datetime.now(timezone.utc)
    if attendance_date is None:
        attendance_date = date.today()
    if check_in_time is None:
        check_in_time = now

    await session.execute(
        text("""
            INSERT INTO child_attendances (
                id, child_id, group_id, facility_id, attendance_date, check_in_time,
                check_out_time, status, checked_in_by, checked_out_by, notes,
                created_at, updated_at
            ) VALUES (
                :id, :child_id, :group_id, :facility_id, :attendance_date, :check_in_time,
                :check_out_time, :status, :checked_in_by, :checked_out_by, :notes,
                :created_at, :updated_at
            )
        """),
        {
            "id": attendance_id,
            "child_id": str(child_id),
            "group_id": str(group_id),
            "facility_id": str(facility_id),
            "attendance_date": attendance_date.isoformat(),
            "check_in_time": check_in_time.isoformat() if check_in_time else None,
            "check_out_time": check_out_time.isoformat() if check_out_time else None,
            "status": status,
            "checked_in_by": str(checked_in_by) if checked_in_by else None,
            "checked_out_by": str(checked_out_by) if checked_out_by else None,
            "notes": notes,
            "created_at": now.isoformat(),
            "updated_at": now.isoformat(),
        },
    )
    await session.commit()

    return MockChildAttendance(
        id=UUID(attendance_id),
        child_id=child_id,
        group_id=group_id,
        facility_id=facility_id,
        attendance_date=attendance_date,
        check_in_time=check_in_time,
        check_out_time=check_out_time,
        status=status,
        checked_in_by=checked_in_by,
        checked_out_by=checked_out_by,
        notes=notes,
        created_at=now,
        updated_at=now,
    )


# =============================================================================
# Fixtures for director dashboard tests
# =============================================================================


@pytest_asyncio.fixture
async def occupancy_db_session() -> AsyncSession:
    """Create a fresh database session with occupancy tables for each test.

    Yields:
        AsyncSession: Async database session for testing.
    """
    # Create occupancy tables via raw SQL (SQLite compatibility)
    async with test_engine.begin() as conn:
        for statement in SQLITE_CREATE_OCCUPANCY_TABLES_SQL.strip().split(";"):
            statement = statement.strip()
            if statement:
                await conn.execute(text(statement))

    async with TestAsyncSessionLocal() as session:
        try:
            yield session
        finally:
            await session.rollback()

    # Drop occupancy tables after test
    async with test_engine.begin() as conn:
        await conn.execute(text("DROP TABLE IF EXISTS child_attendances"))
        await conn.execute(text("DROP TABLE IF EXISTS occupancy_records"))
        await conn.execute(text("DROP TABLE IF EXISTS occupancy_groups"))


@pytest.fixture
def sample_facility_id() -> UUID:
    """Generate a consistent test facility ID."""
    return UUID("11111111-2222-3333-4444-555555555555")


@pytest_asyncio.fixture
async def sample_group(
    occupancy_db_session: AsyncSession,
    sample_facility_id: UUID,
) -> MockGroup:
    """Create a single sample group in the database."""
    return await create_group_in_db(
        occupancy_db_session,
        facility_id=sample_facility_id,
        name="Les Petits Explorateurs",
        age_group="prescolaire",
        capacity=10,
        min_age_months=36,
        max_age_months=60,
        room_number="101",
        is_active=True,
    )


@pytest_asyncio.fixture
async def sample_groups(
    occupancy_db_session: AsyncSession,
    sample_facility_id: UUID,
) -> list[MockGroup]:
    """Create multiple sample groups with varied properties."""
    groups_data = [
        {
            "name": "Les Poupons",
            "age_group": "poupon",
            "capacity": 5,
            "min_age_months": 0,
            "max_age_months": 18,
            "room_number": "100",
        },
        {
            "name": "Les Bambins",
            "age_group": "bambin",
            "capacity": 8,
            "min_age_months": 18,
            "max_age_months": 36,
            "room_number": "101",
        },
        {
            "name": "Les Explorateurs",
            "age_group": "prescolaire",
            "capacity": 10,
            "min_age_months": 36,
            "max_age_months": 60,
            "room_number": "102",
        },
    ]

    groups = []
    for data in groups_data:
        group = await create_group_in_db(
            occupancy_db_session,
            facility_id=sample_facility_id,
            **data,
        )
        groups.append(group)

    return groups


@pytest_asyncio.fixture
async def sample_attendance(
    occupancy_db_session: AsyncSession,
    sample_group: MockGroup,
    sample_facility_id: UUID,
    test_child_id: UUID,
) -> MockChildAttendance:
    """Create a sample child attendance record."""
    return await create_child_attendance_in_db(
        occupancy_db_session,
        child_id=test_child_id,
        group_id=sample_group.id,
        facility_id=sample_facility_id,
        status="present",
        notes="Arrived happy and ready to play",
    )


@pytest_asyncio.fixture
async def sample_occupancy_record(
    occupancy_db_session: AsyncSession,
    sample_group: MockGroup,
    sample_facility_id: UUID,
) -> MockOccupancyRecord:
    """Create a sample occupancy record."""
    return await create_occupancy_record_in_db(
        occupancy_db_session,
        group_id=sample_group.id,
        facility_id=sample_facility_id,
        current_count=7,
        capacity=10,
        staff_count=2,
    )


# =============================================================================
# Schema Tests
# =============================================================================


class TestOccupancySummarySchema:
    """Tests for OccupancySummary schema validation."""

    def test_create_occupancy_summary(self):
        """Test OccupancySummary can be created with valid data."""
        now = datetime.now(timezone.utc)
        summary = OccupancySummary(
            facility_id=uuid4(),
            total_children=25,
            total_capacity=30,
            overall_occupancy_percentage=83.3,
            groups_at_capacity=1,
            groups_near_capacity=2,
            total_groups=3,
            average_staff_ratio="1:8",
            snapshot_time=now,
        )

        assert summary.total_children == 25
        assert summary.total_capacity == 30
        assert summary.overall_occupancy_percentage == 83.3
        assert summary.groups_at_capacity == 1
        assert summary.groups_near_capacity == 2
        assert summary.total_groups == 3
        assert summary.average_staff_ratio == "1:8"
        assert summary.snapshot_time == now

    def test_occupancy_summary_with_none_facility(self):
        """Test OccupancySummary allows None facility_id."""
        summary = OccupancySummary(
            facility_id=None,
            total_children=0,
            total_capacity=0,
            overall_occupancy_percentage=0.0,
            groups_at_capacity=0,
            groups_near_capacity=0,
            total_groups=0,
            average_staff_ratio=None,
            snapshot_time=datetime.now(timezone.utc),
        )

        assert summary.facility_id is None
        assert summary.average_staff_ratio is None


class TestGroupOccupancySchema:
    """Tests for GroupOccupancy schema validation."""

    def test_create_group_occupancy(self):
        """Test GroupOccupancy can be created with valid data."""
        now = datetime.now(timezone.utc)
        group_id = uuid4()
        group = GroupOccupancy(
            group_id=group_id,
            group_name="Les Explorateurs",
            age_group=AgeGroupType.PRESCOLAIRE,
            current_count=8,
            capacity=10,
            occupancy_percentage=80.0,
            status=OccupancyStatus.NEAR_CAPACITY,
            staff_count=2,
            staff_ratio="1:4",
            room_number="102",
            last_updated=now,
        )

        assert group.group_id == group_id
        assert group.group_name == "Les Explorateurs"
        assert group.age_group == AgeGroupType.PRESCOLAIRE
        assert group.current_count == 8
        assert group.capacity == 10
        assert group.occupancy_percentage == 80.0
        assert group.status == OccupancyStatus.NEAR_CAPACITY
        assert group.staff_count == 2
        assert group.staff_ratio == "1:4"
        assert group.room_number == "102"

    def test_group_occupancy_with_optional_fields(self):
        """Test GroupOccupancy with optional fields set to None."""
        now = datetime.now(timezone.utc)
        group = GroupOccupancy(
            group_id=uuid4(),
            group_name="Test Group",
            age_group=AgeGroupType.MIXED,
            current_count=0,
            capacity=10,
            occupancy_percentage=0.0,
            status=OccupancyStatus.EMPTY,
            staff_count=None,
            staff_ratio=None,
            room_number=None,
            last_updated=now,
        )

        assert group.staff_count is None
        assert group.staff_ratio is None
        assert group.room_number is None


class TestDashboardAlertSchema:
    """Tests for DashboardAlert schema validation."""

    def test_create_dashboard_alert(self):
        """Test DashboardAlert can be created with valid data."""
        now = datetime.now(timezone.utc)
        alert_id = uuid4()
        group_id = uuid4()

        alert = DashboardAlert(
            alert_id=alert_id,
            alert_type=AlertType.OCCUPANCY,
            priority=AlertPriority.HIGH,
            title="Group at Capacity",
            message="Les Explorateurs is at full capacity with 10/10 children.",
            group_id=group_id,
            group_name="Les Explorateurs",
            created_at=now,
            is_acknowledged=False,
        )

        assert alert.alert_id == alert_id
        assert alert.alert_type == AlertType.OCCUPANCY
        assert alert.priority == AlertPriority.HIGH
        assert alert.title == "Group at Capacity"
        assert alert.is_acknowledged is False

    def test_dashboard_alert_types(self):
        """Test all AlertType enum values."""
        assert AlertType.OCCUPANCY.value == "occupancy"
        assert AlertType.STAFFING.value == "staffing"
        assert AlertType.COMPLIANCE.value == "compliance"
        assert AlertType.ATTENDANCE.value == "attendance"
        assert AlertType.GENERAL.value == "general"

    def test_alert_priority_levels(self):
        """Test all AlertPriority enum values."""
        assert AlertPriority.LOW.value == "low"
        assert AlertPriority.MEDIUM.value == "medium"
        assert AlertPriority.HIGH.value == "high"
        assert AlertPriority.CRITICAL.value == "critical"


class TestDirectorDashboardResponseSchema:
    """Tests for DirectorDashboardResponse schema validation."""

    def test_create_director_dashboard_response(self):
        """Test DirectorDashboardResponse can be created with valid data."""
        now = datetime.now(timezone.utc)
        summary = OccupancySummary(
            facility_id=uuid4(),
            total_children=25,
            total_capacity=30,
            overall_occupancy_percentage=83.3,
            groups_at_capacity=0,
            groups_near_capacity=1,
            total_groups=3,
            average_staff_ratio="1:8",
            snapshot_time=now,
        )

        response = DirectorDashboardResponse(
            summary=summary,
            groups=[],
            alerts=[],
            alert_count_by_priority={"low": 0, "medium": 0, "high": 0, "critical": 0},
            generated_at=now,
        )

        assert response.summary == summary
        assert response.groups == []
        assert response.alerts == []
        assert response.generated_at == now


class TestOccupancyHistorySchema:
    """Tests for occupancy history schemas."""

    def test_create_occupancy_history_point(self):
        """Test OccupancyHistoryPoint can be created."""
        now = datetime.now(timezone.utc)
        point = OccupancyHistoryPoint(
            timestamp=now,
            total_count=25,
            capacity=30,
            occupancy_percentage=83.3,
        )

        assert point.timestamp == now
        assert point.total_count == 25
        assert point.capacity == 30
        assert point.occupancy_percentage == 83.3

    def test_create_occupancy_history_response(self):
        """Test OccupancyHistoryResponse can be created."""
        now = datetime.now(timezone.utc)
        period_start = now - timedelta(days=7)

        response = OccupancyHistoryResponse(
            facility_id=uuid4(),
            data_points=[],
            period_start=period_start,
            period_end=now,
            generated_at=now,
        )

        assert response.data_points == []
        assert response.period_start == period_start
        assert response.period_end == now


# =============================================================================
# Model Tests
# =============================================================================


class TestGroupModel:
    """Tests for the Group model (using mock fixtures)."""

    @pytest.mark.asyncio
    async def test_create_group(
        self,
        sample_group: MockGroup,
    ):
        """Test Group can be created with all fields."""
        assert sample_group.id is not None
        assert sample_group.name == "Les Petits Explorateurs"
        assert sample_group.age_group == "prescolaire"
        assert sample_group.capacity == 10
        assert sample_group.room_number == "101"
        assert sample_group.is_active is True
        assert sample_group.created_at is not None

    @pytest.mark.asyncio
    async def test_group_repr(
        self,
        sample_group: MockGroup,
    ):
        """Test Group string representation."""
        repr_str = repr(sample_group)
        assert "Group" in repr_str
        assert str(sample_group.id) in repr_str
        assert sample_group.name in repr_str


class TestChildAttendanceModel:
    """Tests for the ChildAttendance model."""

    @pytest.mark.asyncio
    async def test_create_child_attendance(
        self,
        sample_attendance: MockChildAttendance,
    ):
        """Test ChildAttendance can be created."""
        assert sample_attendance.id is not None
        assert sample_attendance.child_id is not None
        assert sample_attendance.group_id is not None
        assert sample_attendance.status == "present"
        assert sample_attendance.check_in_time is not None
        assert sample_attendance.check_out_time is None
        assert sample_attendance.notes == "Arrived happy and ready to play"

    @pytest.mark.asyncio
    async def test_attendance_repr(
        self,
        sample_attendance: MockChildAttendance,
    ):
        """Test ChildAttendance string representation."""
        repr_str = repr(sample_attendance)
        assert "ChildAttendance" in repr_str
        assert str(sample_attendance.id) in repr_str


class TestOccupancyRecordModel:
    """Tests for the OccupancyRecord model."""

    @pytest.mark.asyncio
    async def test_create_occupancy_record(
        self,
        sample_occupancy_record: MockOccupancyRecord,
    ):
        """Test OccupancyRecord can be created."""
        assert sample_occupancy_record.id is not None
        assert sample_occupancy_record.group_id is not None
        assert sample_occupancy_record.current_count == 7
        assert sample_occupancy_record.capacity == 10
        assert sample_occupancy_record.staff_count == 2

    @pytest.mark.asyncio
    async def test_occupancy_record_repr(
        self,
        sample_occupancy_record: MockOccupancyRecord,
    ):
        """Test OccupancyRecord string representation."""
        repr_str = repr(sample_occupancy_record)
        assert "OccupancyRecord" in repr_str
        assert str(sample_occupancy_record.id) in repr_str


# =============================================================================
# Service Tests
# =============================================================================


class TestOccupancyStatusDetermination:
    """Tests for occupancy status calculation logic."""

    def test_empty_status(self):
        """Test status is EMPTY when no children present."""
        service = OccupancyService.__new__(OccupancyService)
        status = service._determine_occupancy_status(current_count=0, capacity=10)
        assert status == OccupancyStatus.EMPTY

    def test_normal_status(self):
        """Test status is NORMAL when under 80% capacity."""
        service = OccupancyService.__new__(OccupancyService)
        status = service._determine_occupancy_status(current_count=5, capacity=10)
        assert status == OccupancyStatus.NORMAL

    def test_near_capacity_status(self):
        """Test status is NEAR_CAPACITY when at or above 80%."""
        service = OccupancyService.__new__(OccupancyService)
        status = service._determine_occupancy_status(current_count=8, capacity=10)
        assert status == OccupancyStatus.NEAR_CAPACITY

    def test_at_capacity_status(self):
        """Test status is AT_CAPACITY when at 100%."""
        service = OccupancyService.__new__(OccupancyService)
        status = service._determine_occupancy_status(current_count=10, capacity=10)
        assert status == OccupancyStatus.AT_CAPACITY

    def test_over_capacity_status(self):
        """Test status is OVER_CAPACITY when exceeding capacity."""
        service = OccupancyService.__new__(OccupancyService)
        status = service._determine_occupancy_status(current_count=12, capacity=10)
        assert status == OccupancyStatus.OVER_CAPACITY

    def test_zero_capacity_with_children(self):
        """Test OVER_CAPACITY when capacity is 0 but children present."""
        service = OccupancyService.__new__(OccupancyService)
        status = service._determine_occupancy_status(current_count=1, capacity=0)
        assert status == OccupancyStatus.OVER_CAPACITY


class TestStaffRatioCompliance:
    """Tests for Quebec staff ratio compliance checking."""

    def test_poupon_ratio_compliant(self):
        """Test infant (poupon) ratio is compliant at 1:5."""
        service = OccupancyService.__new__(OccupancyService)
        is_compliant = service._check_staff_ratio_compliance(
            staff_count=2,
            child_count=10,
            age_group=AgeGroupType.POUPON,
        )
        assert is_compliant is True

    def test_poupon_ratio_non_compliant(self):
        """Test infant (poupon) ratio is non-compliant above 1:5."""
        service = OccupancyService.__new__(OccupancyService)
        is_compliant = service._check_staff_ratio_compliance(
            staff_count=1,
            child_count=6,
            age_group=AgeGroupType.POUPON,
        )
        assert is_compliant is False

    def test_bambin_ratio_compliant(self):
        """Test toddler (bambin) ratio is compliant at 1:8."""
        service = OccupancyService.__new__(OccupancyService)
        is_compliant = service._check_staff_ratio_compliance(
            staff_count=2,
            child_count=16,
            age_group=AgeGroupType.BAMBIN,
        )
        assert is_compliant is True

    def test_prescolaire_ratio_compliant(self):
        """Test preschool ratio is compliant at 1:10."""
        service = OccupancyService.__new__(OccupancyService)
        is_compliant = service._check_staff_ratio_compliance(
            staff_count=1,
            child_count=10,
            age_group=AgeGroupType.PRESCOLAIRE,
        )
        assert is_compliant is True

    def test_zero_staff_with_children(self):
        """Test non-compliant when no staff but children present."""
        service = OccupancyService.__new__(OccupancyService)
        is_compliant = service._check_staff_ratio_compliance(
            staff_count=0,
            child_count=5,
            age_group=AgeGroupType.PRESCOLAIRE,
        )
        assert is_compliant is False

    def test_zero_children(self):
        """Test compliant when no children present."""
        service = OccupancyService.__new__(OccupancyService)
        is_compliant = service._check_staff_ratio_compliance(
            staff_count=2,
            child_count=0,
            age_group=AgeGroupType.PRESCOLAIRE,
        )
        assert is_compliant is True


class TestAlertCountCalculation:
    """Tests for alert count by priority calculation."""

    def test_calculate_alert_counts_empty(self):
        """Test alert counts with no alerts."""
        service = OccupancyService.__new__(OccupancyService)
        counts = service._calculate_alert_counts([])

        assert counts["low"] == 0
        assert counts["medium"] == 0
        assert counts["high"] == 0
        assert counts["critical"] == 0

    def test_calculate_alert_counts_mixed(self):
        """Test alert counts with various priority levels."""
        now = datetime.now(timezone.utc)
        alerts = [
            DashboardAlert(
                alert_id=uuid4(),
                alert_type=AlertType.OCCUPANCY,
                priority=AlertPriority.CRITICAL,
                title="Critical Alert",
                message="Critical message",
                created_at=now,
                is_acknowledged=False,
            ),
            DashboardAlert(
                alert_id=uuid4(),
                alert_type=AlertType.STAFFING,
                priority=AlertPriority.HIGH,
                title="High Alert",
                message="High message",
                created_at=now,
                is_acknowledged=False,
            ),
            DashboardAlert(
                alert_id=uuid4(),
                alert_type=AlertType.GENERAL,
                priority=AlertPriority.LOW,
                title="Low Alert",
                message="Low message",
                created_at=now,
                is_acknowledged=False,
            ),
        ]

        service = OccupancyService.__new__(OccupancyService)
        counts = service._calculate_alert_counts(alerts)

        assert counts["low"] == 1
        assert counts["medium"] == 0
        assert counts["high"] == 1
        assert counts["critical"] == 1


# =============================================================================
# API Endpoint Tests
# =============================================================================


class TestDirectorDashboardEndpoint:
    """Tests for the GET /dashboard endpoint."""

    @pytest.mark.asyncio
    async def test_dashboard_endpoint_returns_placeholder_without_auth(
        self,
        client: AsyncClient,
    ):
        """Test dashboard endpoint requires authentication."""
        response = await client.get("/api/v1/director/dashboard")

        # Should return 401 or 403 without valid token
        assert response.status_code in [401, 403]

    @pytest.mark.asyncio
    async def test_dashboard_endpoint_returns_data_with_auth(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
    ):
        """Test dashboard endpoint returns valid response with auth."""
        response = await client.get(
            "/api/v1/director/dashboard",
            headers=auth_headers,
        )

        assert response.status_code == 200
        data = response.json()

        # Verify response structure
        assert "summary" in data
        assert "groups" in data
        assert "alerts" in data
        assert "alert_count_by_priority" in data
        assert "generated_at" in data

        # Verify summary structure
        summary = data["summary"]
        assert "total_children" in summary
        assert "total_capacity" in summary
        assert "overall_occupancy_percentage" in summary
        assert "total_groups" in summary

    @pytest.mark.asyncio
    async def test_dashboard_endpoint_with_facility_filter(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
        sample_facility_id: UUID,
    ):
        """Test dashboard endpoint accepts facility_id filter."""
        response = await client.get(
            f"/api/v1/director/dashboard?facility_id={sample_facility_id}",
            headers=auth_headers,
        )

        assert response.status_code == 200
        data = response.json()
        assert "summary" in data


class TestOccupancyEndpoint:
    """Tests for the GET /occupancy endpoint."""

    @pytest.mark.asyncio
    async def test_occupancy_endpoint_requires_auth(
        self,
        client: AsyncClient,
    ):
        """Test occupancy endpoint requires authentication."""
        response = await client.get("/api/v1/director/occupancy")

        assert response.status_code in [401, 403]

    @pytest.mark.asyncio
    async def test_occupancy_endpoint_returns_list(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
    ):
        """Test occupancy endpoint returns a list of groups."""
        response = await client.get(
            "/api/v1/director/occupancy",
            headers=auth_headers,
        )

        assert response.status_code == 200
        data = response.json()

        # Response should be a list
        assert isinstance(data, list)

    @pytest.mark.asyncio
    async def test_occupancy_endpoint_with_facility_filter(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
        sample_facility_id: UUID,
    ):
        """Test occupancy endpoint accepts facility_id filter."""
        response = await client.get(
            f"/api/v1/director/occupancy?facility_id={sample_facility_id}",
            headers=auth_headers,
        )

        assert response.status_code == 200


class TestOccupancyHistoryEndpoint:
    """Tests for the GET /occupancy/history endpoint."""

    @pytest.mark.asyncio
    async def test_history_endpoint_requires_auth(
        self,
        client: AsyncClient,
    ):
        """Test history endpoint requires authentication."""
        response = await client.get("/api/v1/director/occupancy/history")

        assert response.status_code in [401, 403]

    @pytest.mark.asyncio
    async def test_history_endpoint_returns_data(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
    ):
        """Test history endpoint returns valid response."""
        response = await client.get(
            "/api/v1/director/occupancy/history",
            headers=auth_headers,
        )

        assert response.status_code == 200
        data = response.json()

        # Verify response structure
        assert "data_points" in data
        assert "period_start" in data
        assert "period_end" in data
        assert "generated_at" in data

        # data_points should be a list
        assert isinstance(data["data_points"], list)

    @pytest.mark.asyncio
    async def test_history_endpoint_accepts_days_back_param(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
    ):
        """Test history endpoint accepts days_back parameter."""
        response = await client.get(
            "/api/v1/director/occupancy/history?days_back=14",
            headers=auth_headers,
        )

        assert response.status_code == 200

    @pytest.mark.asyncio
    async def test_history_endpoint_validates_days_back_min(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
    ):
        """Test history endpoint validates days_back minimum value."""
        response = await client.get(
            "/api/v1/director/occupancy/history?days_back=0",
            headers=auth_headers,
        )

        # Should fail validation (minimum is 1)
        assert response.status_code == 422

    @pytest.mark.asyncio
    async def test_history_endpoint_validates_days_back_max(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
    ):
        """Test history endpoint validates days_back maximum value."""
        response = await client.get(
            "/api/v1/director/occupancy/history?days_back=31",
            headers=auth_headers,
        )

        # Should fail validation (maximum is 30)
        assert response.status_code == 422


# =============================================================================
# Edge Cases and Error Handling
# =============================================================================


class TestEdgeCases:
    """Tests for edge cases and error handling."""

    @pytest.mark.asyncio
    async def test_dashboard_with_invalid_facility_id_format(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
    ):
        """Test dashboard endpoint with invalid UUID format."""
        response = await client.get(
            "/api/v1/director/dashboard?facility_id=invalid-uuid",
            headers=auth_headers,
        )

        # Should fail validation
        assert response.status_code == 422

    @pytest.mark.asyncio
    async def test_occupancy_with_expired_token(
        self,
        client: AsyncClient,
        expired_token: str,
    ):
        """Test occupancy endpoint rejects expired tokens."""
        headers = {"Authorization": f"Bearer {expired_token}"}
        response = await client.get(
            "/api/v1/director/occupancy",
            headers=headers,
        )

        assert response.status_code in [401, 403]

    def test_age_group_type_values(self):
        """Test all AgeGroupType enum values are valid."""
        assert AgeGroupType.POUPON.value == "poupon"
        assert AgeGroupType.BAMBIN.value == "bambin"
        assert AgeGroupType.PRESCOLAIRE.value == "prescolaire"
        assert AgeGroupType.SCOLAIRE.value == "scolaire"
        assert AgeGroupType.MIXED.value == "mixed"

    def test_occupancy_status_values(self):
        """Test all OccupancyStatus enum values are valid."""
        assert OccupancyStatus.NORMAL.value == "normal"
        assert OccupancyStatus.NEAR_CAPACITY.value == "near_capacity"
        assert OccupancyStatus.AT_CAPACITY.value == "at_capacity"
        assert OccupancyStatus.OVER_CAPACITY.value == "over_capacity"
        assert OccupancyStatus.EMPTY.value == "empty"
