"""Occupancy service for LAYA AI Service.

Implements business logic for real-time occupancy calculation,
group statistics, and attendance tracking for the director dashboard.
"""

from __future__ import annotations

import logging
from datetime import date, datetime, timedelta
from typing import Optional
from uuid import UUID, uuid4

from sqlalchemy import and_, desc, func, select
from sqlalchemy.exc import SQLAlchemyError
from sqlalchemy.ext.asyncio import AsyncSession

from app.models.occupancy import (
    AttendanceStatus,
    ChildAttendance,
    Group,
    OccupancyRecord,
)
from app.schemas.director_dashboard import (
    AlertPriority,
    AlertType,
    DashboardAlert,
    DirectorDashboardResponse,
    GroupOccupancy,
    OccupancyHistoryPoint,
    OccupancyHistoryResponse,
    OccupancyStatus,
    OccupancySummary,
    AgeGroupType,
)

logger = logging.getLogger(__name__)


# Quebec childcare staff-to-child ratio requirements by age group
QUEBEC_STAFF_RATIOS = {
    AgeGroupType.POUPON: 5,      # 1:5 for infants (0-18 months)
    AgeGroupType.BAMBIN: 8,      # 1:8 for toddlers (18-36 months)
    AgeGroupType.PRESCOLAIRE: 10,  # 1:10 for preschool (3-5 years)
    AgeGroupType.SCOLAIRE: 20,   # 1:20 for school-age (5+)
    AgeGroupType.MIXED: 8,       # 1:8 for mixed groups (conservative)
}

# Occupancy thresholds for status determination
NEAR_CAPACITY_THRESHOLD = 0.80  # 80% occupancy triggers near_capacity
AT_CAPACITY_THRESHOLD = 1.0     # 100% occupancy triggers at_capacity


class OccupancyService:
    """Service for occupancy tracking operations.

    Provides methods for calculating real-time occupancy, generating
    group statistics, and tracking attendance for director dashboards.

    Attributes:
        db: Async database session for data access
    """

    def __init__(self, db: AsyncSession) -> None:
        """Initialize OccupancyService.

        Args:
            db: Async database session
        """
        self.db = db

    async def get_director_dashboard(
        self,
        facility_id: Optional[UUID] = None,
    ) -> DirectorDashboardResponse:
        """Get complete director dashboard with real-time occupancy data.

        Aggregates occupancy summary, group details, and alerts for
        facility directors.

        Args:
            facility_id: Optional facility to filter by

        Returns:
            DirectorDashboardResponse: Complete dashboard data
        """
        now = datetime.utcnow()

        # Get occupancy summary
        summary = await self.get_occupancy_summary(facility_id=facility_id)

        # Get individual group occupancy data
        groups = await self.get_group_occupancies(facility_id=facility_id)

        # Generate alerts based on occupancy data
        alerts = await self._generate_occupancy_alerts(
            summary=summary,
            groups=groups,
        )

        # Calculate alert counts by priority
        alert_counts = self._calculate_alert_counts(alerts)

        return DirectorDashboardResponse(
            summary=summary,
            groups=groups,
            alerts=alerts,
            alert_count_by_priority=alert_counts,
            generated_at=now,
        )

    async def get_occupancy_summary(
        self,
        facility_id: Optional[UUID] = None,
    ) -> OccupancySummary:
        """Get high-level occupancy summary for a facility.

        Calculates aggregate metrics across all active groups.

        Args:
            facility_id: Optional facility to filter by

        Returns:
            OccupancySummary: Aggregated occupancy metrics
        """
        now = datetime.utcnow()
        today = date.today()

        # Fetch active groups
        groups = await self._fetch_active_groups(facility_id=facility_id)

        if not groups:
            # Return empty summary if no groups
            return OccupancySummary(
                facility_id=facility_id,
                total_children=0,
                total_capacity=0,
                overall_occupancy_percentage=0.0,
                groups_at_capacity=0,
                groups_near_capacity=0,
                total_groups=0,
                average_staff_ratio=None,
                snapshot_time=now,
            )

        # Calculate current counts for each group
        total_children = 0
        total_capacity = 0
        total_staff = 0
        groups_at_capacity = 0
        groups_near_capacity = 0

        for group in groups:
            # Get current child count from attendance records
            child_count = await self._get_current_child_count(
                group_id=group.id,
                attendance_date=today,
            )
            total_children += child_count
            total_capacity += group.capacity

            # Determine occupancy status
            if group.capacity > 0:
                occupancy_pct = child_count / group.capacity
                if occupancy_pct >= AT_CAPACITY_THRESHOLD:
                    groups_at_capacity += 1
                elif occupancy_pct >= NEAR_CAPACITY_THRESHOLD:
                    groups_near_capacity += 1

            # Get staff count from latest occupancy record
            staff_count = await self._get_current_staff_count(
                group_id=group.id,
                record_date=today,
            )
            if staff_count is not None:
                total_staff += staff_count

        # Calculate overall occupancy percentage
        overall_pct = 0.0
        if total_capacity > 0:
            overall_pct = (total_children / total_capacity) * 100

        # Calculate average staff ratio
        avg_staff_ratio = None
        if total_staff > 0 and total_children > 0:
            ratio = total_children / total_staff
            avg_staff_ratio = f"1:{ratio:.1f}"

        return OccupancySummary(
            facility_id=facility_id,
            total_children=total_children,
            total_capacity=total_capacity,
            overall_occupancy_percentage=round(overall_pct, 1),
            groups_at_capacity=groups_at_capacity,
            groups_near_capacity=groups_near_capacity,
            total_groups=len(groups),
            average_staff_ratio=avg_staff_ratio,
            snapshot_time=now,
        )

    async def get_group_occupancies(
        self,
        facility_id: Optional[UUID] = None,
    ) -> list[GroupOccupancy]:
        """Get occupancy data for all active groups.

        Returns real-time occupancy status for each group.

        Args:
            facility_id: Optional facility to filter by

        Returns:
            list[GroupOccupancy]: List of group occupancy data
        """
        now = datetime.utcnow()
        today = date.today()

        # Fetch active groups
        groups = await self._fetch_active_groups(facility_id=facility_id)

        group_occupancies: list[GroupOccupancy] = []

        for group in groups:
            # Get current child count
            child_count = await self._get_current_child_count(
                group_id=group.id,
                attendance_date=today,
            )

            # Get staff count
            staff_count = await self._get_current_staff_count(
                group_id=group.id,
                record_date=today,
            )

            # Calculate occupancy percentage
            occupancy_pct = 0.0
            if group.capacity > 0:
                occupancy_pct = (child_count / group.capacity) * 100

            # Determine occupancy status
            status = self._determine_occupancy_status(
                current_count=child_count,
                capacity=group.capacity,
            )

            # Calculate staff ratio
            staff_ratio = None
            if staff_count is not None and staff_count > 0 and child_count > 0:
                ratio = child_count / staff_count
                staff_ratio = f"1:{ratio:.0f}"

            # Parse age group
            try:
                age_group = AgeGroupType(group.age_group)
            except ValueError:
                age_group = AgeGroupType.MIXED

            group_occupancies.append(
                GroupOccupancy(
                    group_id=group.id,
                    group_name=group.name,
                    age_group=age_group,
                    current_count=child_count,
                    capacity=group.capacity,
                    occupancy_percentage=round(occupancy_pct, 1),
                    status=status,
                    staff_count=staff_count,
                    staff_ratio=staff_ratio,
                    room_number=group.room_number,
                    last_updated=now,
                )
            )

        return group_occupancies

    async def get_occupancy_history(
        self,
        facility_id: Optional[UUID] = None,
        days_back: int = 7,
    ) -> OccupancyHistoryResponse:
        """Get historical occupancy data for trend analysis.

        Returns time series data for the specified period.

        Args:
            facility_id: Optional facility to filter by
            days_back: Number of days of history to fetch (1-30)

        Returns:
            OccupancyHistoryResponse: Historical occupancy data
        """
        now = datetime.utcnow()

        # Clamp days_back to valid range
        days_back = max(1, min(30, days_back))

        period_end = now
        period_start = now - timedelta(days=days_back)

        # Fetch historical occupancy records
        data_points = await self._fetch_occupancy_history(
            facility_id=facility_id,
            period_start=period_start,
            period_end=period_end,
        )

        return OccupancyHistoryResponse(
            facility_id=facility_id,
            data_points=data_points,
            period_start=period_start,
            period_end=period_end,
            generated_at=now,
        )

    async def record_attendance(
        self,
        child_id: UUID,
        group_id: UUID,
        facility_id: UUID,
        status: AttendanceStatus = AttendanceStatus.PRESENT,
        check_in_time: Optional[datetime] = None,
        checked_in_by: Optional[UUID] = None,
        notes: Optional[str] = None,
    ) -> ChildAttendance:
        """Record or update a child's attendance.

        Creates a new attendance record or updates existing one for today.

        Args:
            child_id: ID of the child
            group_id: ID of the group
            facility_id: ID of the facility
            status: Attendance status
            check_in_time: Time of check-in (defaults to now)
            checked_in_by: ID of user recording check-in
            notes: Optional attendance notes

        Returns:
            ChildAttendance: Created or updated attendance record
        """
        now = datetime.utcnow()
        today = date.today()

        if check_in_time is None:
            check_in_time = now

        try:
            # Check for existing attendance record today
            existing = await self._get_existing_attendance(
                child_id=child_id,
                group_id=group_id,
                attendance_date=today,
            )

            if existing:
                # Update existing record
                existing.status = status.value
                existing.check_in_time = check_in_time
                existing.checked_in_by = checked_in_by
                existing.notes = notes
                existing.updated_at = now
                await self.db.commit()
                await self.db.refresh(existing)
                return existing

            # Create new attendance record
            attendance = ChildAttendance(
                child_id=child_id,
                group_id=group_id,
                facility_id=facility_id,
                attendance_date=today,
                check_in_time=check_in_time,
                status=status.value,
                checked_in_by=checked_in_by,
                notes=notes,
            )
            self.db.add(attendance)
            await self.db.commit()
            await self.db.refresh(attendance)
            return attendance
        except SQLAlchemyError as e:
            logger.error(f"Database error recording attendance: {e}")
            await self.db.rollback()
            raise

    async def record_checkout(
        self,
        child_id: UUID,
        group_id: UUID,
        check_out_time: Optional[datetime] = None,
        checked_out_by: Optional[UUID] = None,
    ) -> Optional[ChildAttendance]:
        """Record a child's check-out.

        Updates the attendance record with check-out time.

        Args:
            child_id: ID of the child
            group_id: ID of the group
            check_out_time: Time of check-out (defaults to now)
            checked_out_by: ID of user recording check-out

        Returns:
            Optional[ChildAttendance]: Updated attendance record or None
        """
        now = datetime.utcnow()
        today = date.today()

        if check_out_time is None:
            check_out_time = now

        try:
            # Find today's attendance record
            existing = await self._get_existing_attendance(
                child_id=child_id,
                group_id=group_id,
                attendance_date=today,
            )

            if not existing:
                logger.warning(
                    f"No attendance record found for child {child_id} "
                    f"in group {group_id} on {today}"
                )
                return None

            # Update with check-out time
            existing.check_out_time = check_out_time
            existing.checked_out_by = checked_out_by
            existing.updated_at = now

            # Update status if currently present
            if existing.status == AttendanceStatus.PRESENT.value:
                existing.status = AttendanceStatus.EARLY_DEPARTURE.value

            await self.db.commit()
            await self.db.refresh(existing)
            return existing
        except SQLAlchemyError as e:
            logger.error(f"Database error recording checkout: {e}")
            await self.db.rollback()
            raise

    async def create_occupancy_snapshot(
        self,
        facility_id: UUID,
    ) -> list[OccupancyRecord]:
        """Create occupancy snapshot records for all groups.

        Records current occupancy for historical tracking.

        Args:
            facility_id: ID of the facility

        Returns:
            list[OccupancyRecord]: Created occupancy records
        """
        now = datetime.utcnow()
        today = date.today()

        records: list[OccupancyRecord] = []

        try:
            # Fetch active groups for this facility
            groups = await self._fetch_active_groups(facility_id=facility_id)

            for group in groups:
                # Get current child count
                child_count = await self._get_current_child_count(
                    group_id=group.id,
                    attendance_date=today,
                )

                # Get staff count from latest record or default
                staff_count = await self._get_current_staff_count(
                    group_id=group.id,
                    record_date=today,
                )

                # Create snapshot record
                record = OccupancyRecord(
                    group_id=group.id,
                    facility_id=facility_id,
                    record_date=today,
                    record_time=now,
                    current_count=child_count,
                    capacity=group.capacity,
                    staff_count=staff_count,
                )
                self.db.add(record)
                records.append(record)

            await self.db.commit()

            # Refresh all records
            for record in records:
                await self.db.refresh(record)

            return records
        except SQLAlchemyError as e:
            logger.error(f"Database error creating occupancy snapshot: {e}")
            await self.db.rollback()
            raise

    async def _fetch_active_groups(
        self,
        facility_id: Optional[UUID] = None,
    ) -> list[Group]:
        """Fetch active groups from database.

        Args:
            facility_id: Optional facility filter

        Returns:
            list[Group]: List of active groups
        """
        try:
            query = select(Group).where(Group.is_active == True)

            if facility_id is not None:
                query = query.where(Group.facility_id == facility_id)

            query = query.order_by(Group.name)

            result = await self.db.execute(query)
            return list(result.scalars().all())
        except SQLAlchemyError as e:
            logger.warning(f"Database error fetching groups: {e}")
            return []

    async def _get_current_child_count(
        self,
        group_id: UUID,
        attendance_date: date,
    ) -> int:
        """Get the current number of children present in a group.

        Counts attendance records where the child has checked in
        but not yet checked out.

        Args:
            group_id: ID of the group
            attendance_date: Date to check

        Returns:
            int: Number of children currently present
        """
        try:
            query = select(func.count(ChildAttendance.id)).where(
                and_(
                    ChildAttendance.group_id == group_id,
                    ChildAttendance.attendance_date == attendance_date,
                    ChildAttendance.check_in_time.isnot(None),
                    ChildAttendance.check_out_time.is_(None),
                    ChildAttendance.status.in_([
                        AttendanceStatus.PRESENT.value,
                        AttendanceStatus.LATE_ARRIVAL.value,
                    ]),
                )
            )

            result = await self.db.execute(query)
            count = result.scalar()
            return count if count is not None else 0
        except SQLAlchemyError as e:
            logger.warning(f"Database error getting child count: {e}")
            return 0

    async def _get_current_staff_count(
        self,
        group_id: UUID,
        record_date: date,
    ) -> Optional[int]:
        """Get the current staff count for a group.

        Fetches from the most recent occupancy record for today.

        Args:
            group_id: ID of the group
            record_date: Date to check

        Returns:
            Optional[int]: Staff count or None if unavailable
        """
        try:
            query = (
                select(OccupancyRecord.staff_count)
                .where(
                    and_(
                        OccupancyRecord.group_id == group_id,
                        OccupancyRecord.record_date == record_date,
                    )
                )
                .order_by(desc(OccupancyRecord.record_time))
                .limit(1)
            )

            result = await self.db.execute(query)
            staff_count = result.scalar_one_or_none()
            return staff_count
        except SQLAlchemyError as e:
            logger.warning(f"Database error getting staff count: {e}")
            return None

    async def _get_existing_attendance(
        self,
        child_id: UUID,
        group_id: UUID,
        attendance_date: date,
    ) -> Optional[ChildAttendance]:
        """Get existing attendance record for a child on a given date.

        Args:
            child_id: ID of the child
            group_id: ID of the group
            attendance_date: Date to check

        Returns:
            Optional[ChildAttendance]: Existing record or None
        """
        try:
            query = select(ChildAttendance).where(
                and_(
                    ChildAttendance.child_id == child_id,
                    ChildAttendance.group_id == group_id,
                    ChildAttendance.attendance_date == attendance_date,
                )
            )

            result = await self.db.execute(query)
            return result.scalar_one_or_none()
        except SQLAlchemyError as e:
            logger.warning(f"Database error fetching attendance: {e}")
            return None

    async def _fetch_occupancy_history(
        self,
        facility_id: Optional[UUID],
        period_start: datetime,
        period_end: datetime,
    ) -> list[OccupancyHistoryPoint]:
        """Fetch historical occupancy data.

        Aggregates occupancy records into time series points.

        Args:
            facility_id: Optional facility filter
            period_start: Start of period
            period_end: End of period

        Returns:
            list[OccupancyHistoryPoint]: Historical data points
        """
        try:
            query = select(
                OccupancyRecord.record_time,
                func.sum(OccupancyRecord.current_count).label("total_count"),
                func.sum(OccupancyRecord.capacity).label("total_capacity"),
            ).where(
                and_(
                    OccupancyRecord.record_time >= period_start,
                    OccupancyRecord.record_time <= period_end,
                )
            )

            if facility_id is not None:
                query = query.where(OccupancyRecord.facility_id == facility_id)

            query = query.group_by(OccupancyRecord.record_time).order_by(
                OccupancyRecord.record_time
            )

            result = await self.db.execute(query)
            rows = result.all()

            data_points: list[OccupancyHistoryPoint] = []
            for row in rows:
                total_count = row.total_count or 0
                total_capacity = row.total_capacity or 0
                occupancy_pct = 0.0
                if total_capacity > 0:
                    occupancy_pct = (total_count / total_capacity) * 100

                data_points.append(
                    OccupancyHistoryPoint(
                        timestamp=row.record_time,
                        total_count=total_count,
                        capacity=total_capacity,
                        occupancy_percentage=round(occupancy_pct, 1),
                    )
                )

            return data_points
        except SQLAlchemyError as e:
            logger.warning(f"Database error fetching occupancy history: {e}")
            return []

    def _determine_occupancy_status(
        self,
        current_count: int,
        capacity: int,
    ) -> OccupancyStatus:
        """Determine occupancy status based on count and capacity.

        Args:
            current_count: Number of children present
            capacity: Maximum capacity

        Returns:
            OccupancyStatus: Status indicator
        """
        if current_count == 0:
            return OccupancyStatus.EMPTY

        if capacity == 0:
            return OccupancyStatus.OVER_CAPACITY if current_count > 0 else OccupancyStatus.EMPTY

        occupancy_ratio = current_count / capacity

        if occupancy_ratio > AT_CAPACITY_THRESHOLD:
            return OccupancyStatus.OVER_CAPACITY
        elif occupancy_ratio >= AT_CAPACITY_THRESHOLD:
            return OccupancyStatus.AT_CAPACITY
        elif occupancy_ratio >= NEAR_CAPACITY_THRESHOLD:
            return OccupancyStatus.NEAR_CAPACITY
        else:
            return OccupancyStatus.NORMAL

    async def _generate_occupancy_alerts(
        self,
        summary: OccupancySummary,
        groups: list[GroupOccupancy],
    ) -> list[DashboardAlert]:
        """Generate alerts based on occupancy data.

        Creates alerts for capacity issues and staffing concerns.

        Args:
            summary: Facility occupancy summary
            groups: List of group occupancy data

        Returns:
            list[DashboardAlert]: Generated alerts
        """
        now = datetime.utcnow()
        alerts: list[DashboardAlert] = []

        # Check facility-wide occupancy
        if summary.overall_occupancy_percentage >= 100:
            alerts.append(
                DashboardAlert(
                    alert_id=uuid4(),
                    alert_type=AlertType.OCCUPANCY,
                    priority=AlertPriority.CRITICAL,
                    title="Facility at Maximum Capacity",
                    message=(
                        f"Facility is at {summary.overall_occupancy_percentage:.0f}% "
                        "capacity. Cannot accept additional children."
                    ),
                    created_at=now,
                    is_acknowledged=False,
                )
            )
        elif summary.overall_occupancy_percentage >= 90:
            alerts.append(
                DashboardAlert(
                    alert_id=uuid4(),
                    alert_type=AlertType.OCCUPANCY,
                    priority=AlertPriority.HIGH,
                    title="Facility Approaching Capacity",
                    message=(
                        f"Facility is at {summary.overall_occupancy_percentage:.0f}% "
                        "capacity. Plan for limited availability."
                    ),
                    created_at=now,
                    is_acknowledged=False,
                )
            )

        # Check individual groups for capacity and staffing issues
        for group in groups:
            # Over capacity alert
            if group.status == OccupancyStatus.OVER_CAPACITY:
                alerts.append(
                    DashboardAlert(
                        alert_id=uuid4(),
                        alert_type=AlertType.COMPLIANCE,
                        priority=AlertPriority.CRITICAL,
                        title=f"Over Capacity: {group.group_name}",
                        message=(
                            f"Group {group.group_name} has {group.current_count} "
                            f"children but capacity is {group.capacity}. "
                            "Immediate action required."
                        ),
                        group_id=group.group_id,
                        group_name=group.group_name,
                        created_at=now,
                        is_acknowledged=False,
                    )
                )

            # Check staff ratio compliance
            if group.staff_count is not None and group.current_count > 0:
                ratio_compliant = self._check_staff_ratio_compliance(
                    staff_count=group.staff_count,
                    child_count=group.current_count,
                    age_group=group.age_group,
                )
                if not ratio_compliant:
                    required_ratio = QUEBEC_STAFF_RATIOS.get(group.age_group, 8)
                    alerts.append(
                        DashboardAlert(
                            alert_id=uuid4(),
                            alert_type=AlertType.STAFFING,
                            priority=AlertPriority.HIGH,
                            title=f"Staff Ratio Issue: {group.group_name}",
                            message=(
                                f"Group {group.group_name} has {group.staff_count} "
                                f"staff for {group.current_count} children. "
                                f"Quebec regulations require 1:{required_ratio} ratio."
                            ),
                            group_id=group.group_id,
                            group_name=group.group_name,
                            created_at=now,
                            is_acknowledged=False,
                        )
                    )

        # Sort alerts by priority
        priority_order = {
            AlertPriority.CRITICAL: 0,
            AlertPriority.HIGH: 1,
            AlertPriority.MEDIUM: 2,
            AlertPriority.LOW: 3,
        }
        alerts.sort(key=lambda a: priority_order.get(a.priority, 99))

        return alerts

    def _check_staff_ratio_compliance(
        self,
        staff_count: int,
        child_count: int,
        age_group: AgeGroupType,
    ) -> bool:
        """Check if staff-to-child ratio meets Quebec regulations.

        Args:
            staff_count: Number of staff
            child_count: Number of children
            age_group: Age group for ratio requirements

        Returns:
            bool: True if ratio is compliant
        """
        if staff_count == 0:
            return child_count == 0

        if child_count == 0:
            return True

        required_ratio = QUEBEC_STAFF_RATIOS.get(age_group, 8)
        actual_ratio = child_count / staff_count

        return actual_ratio <= required_ratio

    def _calculate_alert_counts(
        self,
        alerts: list[DashboardAlert],
    ) -> dict[str, int]:
        """Calculate count of alerts by priority.

        Args:
            alerts: List of alerts

        Returns:
            dict[str, int]: Alert counts by priority level
        """
        counts = {
            "low": 0,
            "medium": 0,
            "high": 0,
            "critical": 0,
        }

        for alert in alerts:
            priority_key = alert.priority.value
            if priority_key in counts:
                counts[priority_key] += 1

        return counts
