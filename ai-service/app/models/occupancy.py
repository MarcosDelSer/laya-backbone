"""SQLAlchemy models for occupancy tracking domain.

Defines database models for groups/classrooms, occupancy records,
and child attendance tracking. These models support the real-time
director dashboard with occupancy monitoring for Quebec childcare compliance.
"""

from datetime import date, datetime
from enum import Enum
from typing import TYPE_CHECKING, Optional
from uuid import uuid4

from sqlalchemy import Boolean, Date, DateTime, ForeignKey, Index, Integer, String, Text
from sqlalchemy.dialects.postgresql import UUID
from sqlalchemy.orm import Mapped, mapped_column, relationship

from app.models.base import Base

if TYPE_CHECKING:
    pass


class AttendanceStatus(str, Enum):
    """Status values for child attendance.

    Attributes:
        PRESENT: Child is currently present
        ABSENT: Child is absent for the day
        LATE_ARRIVAL: Child arrived late
        EARLY_DEPARTURE: Child left early
        SICK: Child is absent due to illness
        VACATION: Child is on scheduled vacation
    """

    PRESENT = "present"
    ABSENT = "absent"
    LATE_ARRIVAL = "late_arrival"
    EARLY_DEPARTURE = "early_departure"
    SICK = "sick"
    VACATION = "vacation"


class AgeGroupType(str, Enum):
    """Age group classifications for Quebec childcare.

    Based on Quebec Ministère de la Famille regulations for
    staff-to-child ratios and group compositions.

    Attributes:
        POUPON: Infants (0-18 months)
        BAMBIN: Toddlers (18-36 months)
        PRESCOLAIRE: Preschoolers (3-5 years)
        SCOLAIRE: School-age (5+ years)
        MIXED: Mixed age group
    """

    POUPON = "poupon"
    BAMBIN = "bambin"
    PRESCOLAIRE = "prescolaire"
    SCOLAIRE = "scolaire"
    MIXED = "mixed"


class Group(Base):
    """Track childcare groups/classrooms.

    Stores information about each group including capacity limits
    based on Quebec regulatory requirements.

    Attributes:
        id: Unique identifier for the group
        facility_id: ID of the facility this group belongs to
        name: Display name of the group
        age_group: Age classification for the group
        capacity: Maximum number of children allowed
        min_age_months: Minimum age in months for this group
        max_age_months: Maximum age in months for this group
        room_number: Physical room identifier
        is_active: Whether the group is currently active
        created_at: Timestamp when the group was created
        updated_at: Timestamp when the group was last updated
        occupancy_records: List of occupancy records for this group
        child_attendances: List of child attendance records for this group
    """

    __tablename__ = "occupancy_groups"

    id: Mapped[UUID] = mapped_column(
        UUID(as_uuid=True),
        primary_key=True,
        default=uuid4,
    )
    facility_id: Mapped[UUID] = mapped_column(
        UUID(as_uuid=True),
        nullable=False,
        index=True,
    )
    name: Mapped[str] = mapped_column(
        String(100),
        nullable=False,
    )
    age_group: Mapped[str] = mapped_column(
        String(50),
        nullable=False,
        default=AgeGroupType.PRESCOLAIRE.value,
    )
    capacity: Mapped[int] = mapped_column(
        Integer,
        nullable=False,
        default=10,
    )
    min_age_months: Mapped[Optional[int]] = mapped_column(
        Integer,
        nullable=True,
    )
    max_age_months: Mapped[Optional[int]] = mapped_column(
        Integer,
        nullable=True,
    )
    room_number: Mapped[Optional[str]] = mapped_column(
        String(20),
        nullable=True,
    )
    is_active: Mapped[bool] = mapped_column(
        Boolean,
        nullable=False,
        default=True,
    )
    created_at: Mapped[datetime] = mapped_column(
        DateTime,
        default=datetime.utcnow,
        nullable=False,
    )
    updated_at: Mapped[Optional[datetime]] = mapped_column(
        DateTime,
        default=datetime.utcnow,
        onupdate=datetime.utcnow,
        nullable=True,
    )

    # Relationships
    occupancy_records: Mapped[list["OccupancyRecord"]] = relationship(
        "OccupancyRecord",
        back_populates="group",
        cascade="all, delete-orphan",
    )
    child_attendances: Mapped[list["ChildAttendance"]] = relationship(
        "ChildAttendance",
        back_populates="group",
        cascade="all, delete-orphan",
    )

    # Table-level indexes
    __table_args__ = (
        Index("ix_occupancy_groups_facility_active", "facility_id", "is_active"),
        Index("ix_occupancy_groups_age_group", "age_group"),
    )

    def __repr__(self) -> str:
        """String representation of the Group."""
        return (
            f"<Group(id={self.id}, "
            f"name='{self.name}', "
            f"capacity={self.capacity})>"
        )


class OccupancyRecord(Base):
    """Track point-in-time occupancy snapshots.

    Stores occupancy counts at regular intervals for historical
    tracking and compliance reporting.

    Attributes:
        id: Unique identifier for the record
        group_id: ID of the group this record belongs to
        facility_id: ID of the facility for cross-reference
        record_date: Date of the occupancy snapshot
        record_time: Time of the occupancy snapshot
        current_count: Number of children present at this time
        capacity: Group capacity at time of recording
        staff_count: Number of staff present
        notes: Optional notes about the occupancy
        created_at: Timestamp when the record was created
        group: Reference to the parent group
    """

    __tablename__ = "occupancy_records"

    id: Mapped[UUID] = mapped_column(
        UUID(as_uuid=True),
        primary_key=True,
        default=uuid4,
    )
    group_id: Mapped[UUID] = mapped_column(
        UUID(as_uuid=True),
        ForeignKey("occupancy_groups.id", ondelete="CASCADE"),
        nullable=False,
        index=True,
    )
    facility_id: Mapped[UUID] = mapped_column(
        UUID(as_uuid=True),
        nullable=False,
        index=True,
    )
    record_date: Mapped[date] = mapped_column(
        Date,
        nullable=False,
        index=True,
    )
    record_time: Mapped[datetime] = mapped_column(
        DateTime,
        nullable=False,
    )
    current_count: Mapped[int] = mapped_column(
        Integer,
        nullable=False,
        default=0,
    )
    capacity: Mapped[int] = mapped_column(
        Integer,
        nullable=False,
    )
    staff_count: Mapped[Optional[int]] = mapped_column(
        Integer,
        nullable=True,
    )
    notes: Mapped[Optional[str]] = mapped_column(
        Text,
        nullable=True,
    )
    created_at: Mapped[datetime] = mapped_column(
        DateTime,
        default=datetime.utcnow,
        nullable=False,
    )

    # Relationships
    group: Mapped["Group"] = relationship(
        "Group",
        back_populates="occupancy_records",
    )

    # Table-level indexes
    __table_args__ = (
        Index("ix_occupancy_records_facility_date", "facility_id", "record_date"),
        Index("ix_occupancy_records_group_date", "group_id", "record_date"),
    )

    def __repr__(self) -> str:
        """String representation of the OccupancyRecord."""
        return (
            f"<OccupancyRecord(id={self.id}, "
            f"group_id={self.group_id}, "
            f"current_count={self.current_count}/{self.capacity})>"
        )


class ChildAttendance(Base):
    """Track individual child attendance.

    Records check-in and check-out times for each child,
    supporting the real-time occupancy dashboard.

    Attributes:
        id: Unique identifier for the attendance record
        child_id: ID of the child
        group_id: ID of the group the child is in
        facility_id: ID of the facility
        attendance_date: Date of attendance
        check_in_time: Time the child checked in
        check_out_time: Time the child checked out (null if still present)
        status: Current attendance status
        checked_in_by: ID of the user who checked in the child
        checked_out_by: ID of the user who checked out the child
        notes: Optional attendance notes
        created_at: Timestamp when the record was created
        updated_at: Timestamp when the record was last updated
        group: Reference to the group
    """

    __tablename__ = "child_attendances"

    id: Mapped[UUID] = mapped_column(
        UUID(as_uuid=True),
        primary_key=True,
        default=uuid4,
    )
    child_id: Mapped[UUID] = mapped_column(
        UUID(as_uuid=True),
        nullable=False,
        index=True,
    )
    group_id: Mapped[UUID] = mapped_column(
        UUID(as_uuid=True),
        ForeignKey("occupancy_groups.id", ondelete="CASCADE"),
        nullable=False,
        index=True,
    )
    facility_id: Mapped[UUID] = mapped_column(
        UUID(as_uuid=True),
        nullable=False,
        index=True,
    )
    attendance_date: Mapped[date] = mapped_column(
        Date,
        nullable=False,
        index=True,
    )
    check_in_time: Mapped[Optional[datetime]] = mapped_column(
        DateTime,
        nullable=True,
    )
    check_out_time: Mapped[Optional[datetime]] = mapped_column(
        DateTime,
        nullable=True,
    )
    status: Mapped[str] = mapped_column(
        String(30),
        nullable=False,
        default=AttendanceStatus.PRESENT.value,
    )
    checked_in_by: Mapped[Optional[UUID]] = mapped_column(
        UUID(as_uuid=True),
        nullable=True,
    )
    checked_out_by: Mapped[Optional[UUID]] = mapped_column(
        UUID(as_uuid=True),
        nullable=True,
    )
    notes: Mapped[Optional[str]] = mapped_column(
        Text,
        nullable=True,
    )
    created_at: Mapped[datetime] = mapped_column(
        DateTime,
        default=datetime.utcnow,
        nullable=False,
    )
    updated_at: Mapped[Optional[datetime]] = mapped_column(
        DateTime,
        default=datetime.utcnow,
        onupdate=datetime.utcnow,
        nullable=True,
    )

    # Relationships
    group: Mapped["Group"] = relationship(
        "Group",
        back_populates="child_attendances",
    )

    # Table-level indexes
    __table_args__ = (
        Index("ix_child_attendances_child_date", "child_id", "attendance_date"),
        Index("ix_child_attendances_facility_date", "facility_id", "attendance_date"),
        Index("ix_child_attendances_group_date", "group_id", "attendance_date"),
        Index("ix_child_attendances_status", "status"),
    )

    def __repr__(self) -> str:
        """String representation of the ChildAttendance."""
        return (
            f"<ChildAttendance(id={self.id}, "
            f"child_id={self.child_id}, "
            f"status='{self.status}')>"
        )
