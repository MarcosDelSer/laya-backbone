"""Service for automated review reminder notifications.

Provides automated review reminder functionality for intervention plans,
including calculating review dates, generating reminder notifications,
and tracking reminder delivery status.
"""

from calendar import monthrange
from datetime import date, datetime, timedelta
from enum import Enum
from typing import Optional
from uuid import UUID

from pydantic import BaseModel, Field
from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession

from app.models.intervention_plan import InterventionPlan
from app.schemas.intervention_plan import (
    InterventionPlanStatus,
    PlanReviewReminder,
    PlanReviewReminderListResponse,
    ReviewSchedule,
)


class ReminderUrgency(str, Enum):
    """Urgency level for review reminders.

    Attributes:
        OVERDUE: Review is past due date
        URGENT: Review is due within 7 days
        UPCOMING: Review is due within 30 days
        SCHEDULED: Review is scheduled but not yet urgent
    """

    OVERDUE = "overdue"
    URGENT = "urgent"
    UPCOMING = "upcoming"
    SCHEDULED = "scheduled"


class RecipientType(str, Enum):
    """Types of reminder recipients.

    Attributes:
        EDUCATOR: Educator/teacher who manages the plan
        PARENT: Parent/caregiver of the child
        TEAM: Entire care team
        CREATOR: Plan creator
    """

    EDUCATOR = "educator"
    PARENT = "parent"
    TEAM = "team"
    CREATOR = "creator"


class ReminderNotification(BaseModel):
    """Schema for a review reminder notification.

    Attributes:
        plan_id: ID of the intervention plan
        child_id: ID of the child
        child_name: Name of the child
        plan_title: Title of the intervention plan
        next_review_date: When the review is due
        days_until_review: Days until review (negative if overdue)
        urgency: Urgency level of the reminder
        recipient_type: Type of recipient for this notification
        subject: Subject line for notification
        message: Full notification message
        action_url: URL to take action on the plan
    """

    plan_id: UUID = Field(..., description="ID of the intervention plan")
    child_id: UUID = Field(..., description="ID of the child")
    child_name: str = Field(..., description="Name of the child")
    plan_title: str = Field(..., description="Title of the intervention plan")
    next_review_date: date = Field(..., description="When the review is due")
    days_until_review: int = Field(
        ...,
        description="Days until review (negative if overdue)",
    )
    urgency: ReminderUrgency = Field(..., description="Urgency level of the reminder")
    recipient_type: RecipientType = Field(
        ...,
        description="Type of recipient for this notification",
    )
    subject: str = Field(..., description="Subject line for notification")
    message: str = Field(..., description="Full notification message")
    action_url: Optional[str] = Field(
        default=None,
        description="URL to take action on the plan",
    )


class ReminderBatch(BaseModel):
    """Schema for a batch of reminder notifications.

    Attributes:
        notifications: List of reminder notifications
        overdue_count: Number of overdue plans
        urgent_count: Number of urgent plans (due within 7 days)
        upcoming_count: Number of upcoming plans (due within 30 days)
        generated_at: When the batch was generated
    """

    notifications: list[ReminderNotification] = Field(
        ...,
        description="List of reminder notifications",
    )
    overdue_count: int = Field(
        default=0,
        ge=0,
        description="Number of overdue plans",
    )
    urgent_count: int = Field(
        default=0,
        ge=0,
        description="Number of urgent plans (due within 7 days)",
    )
    upcoming_count: int = Field(
        default=0,
        ge=0,
        description="Number of upcoming plans (due within 30 days)",
    )
    generated_at: datetime = Field(
        default_factory=datetime.utcnow,
        description="When the batch was generated",
    )


class ReviewReminderServiceError(Exception):
    """Base exception for review reminder service errors."""

    pass


class NoPlansFoundError(ReviewReminderServiceError):
    """Raised when no plans are found for reminder generation."""

    pass


class InvalidScheduleError(ReviewReminderServiceError):
    """Raised when an invalid review schedule is provided."""

    pass


class ReviewReminderService:
    """Service for generating and managing review reminder notifications.

    This service provides automated review reminder functionality for
    intervention plans, including:
    - Calculating next review dates based on schedule
    - Generating reminder notifications with appropriate urgency
    - Filtering reminders by recipient type
    - Tracking overdue and upcoming reviews

    Attributes:
        db: Async database session for database operations
    """

    # Message templates for different urgency levels
    SUBJECT_TEMPLATES = {
        ReminderUrgency.OVERDUE: "OVERDUE: Review required for {child_name}'s intervention plan",
        ReminderUrgency.URGENT: "URGENT: Review due in {days} days for {child_name}'s plan",
        ReminderUrgency.UPCOMING: "Reminder: Review scheduled for {child_name}'s intervention plan",
        ReminderUrgency.SCHEDULED: "Scheduled: Upcoming review for {child_name}'s plan",
    }

    MESSAGE_TEMPLATES = {
        ReminderUrgency.OVERDUE: (
            "The intervention plan '{plan_title}' for {child_name} was due for review "
            "on {review_date}. This review is now {days_overdue} day(s) overdue.\n\n"
            "Please schedule a review meeting as soon as possible to assess progress "
            "toward the plan's goals and make any necessary adjustments.\n\n"
            "Key areas to review:\n"
            "- Progress on SMART goals\n"
            "- Effectiveness of current strategies\n"
            "- Any barriers encountered\n"
            "- Parent involvement activities\n"
            "- Need for specialist consultations"
        ),
        ReminderUrgency.URGENT: (
            "The intervention plan '{plan_title}' for {child_name} is due for review "
            "in {days} day(s) on {review_date}.\n\n"
            "Please begin preparing for the review by gathering:\n"
            "- Recent progress notes and observations\n"
            "- Data on goal achievement\n"
            "- Feedback from all team members\n"
            "- Any concerns or suggested modifications"
        ),
        ReminderUrgency.UPCOMING: (
            "This is a reminder that the intervention plan '{plan_title}' for "
            "{child_name} is scheduled for review on {review_date} ({days} days away).\n\n"
            "Consider starting to collect progress information and scheduling "
            "time with team members to discuss the plan's effectiveness."
        ),
        ReminderUrgency.SCHEDULED: (
            "The intervention plan '{plan_title}' for {child_name} has a scheduled "
            "review on {review_date}.\n\n"
            "This is an advance notice to help you plan ahead for the review."
        ),
    }

    def __init__(self, db: AsyncSession) -> None:
        """Initialize the review reminder service.

        Args:
            db: Async database session
        """
        self.db = db

    async def get_plans_for_review(
        self,
        days_ahead: int = 30,
        include_overdue: bool = True,
    ) -> PlanReviewReminderListResponse:
        """Get plans that are due or upcoming for review.

        Returns plans where next_review_date is within the specified
        number of days or overdue.

        Args:
            days_ahead: Number of days ahead to look for upcoming reviews
            include_overdue: Whether to include overdue plans

        Returns:
            PlanReviewReminderListResponse with plans needing review
        """
        today = date.today()
        cutoff_date = today + timedelta(days=days_ahead)

        # Build query for active/under_review plans with upcoming reviews
        query = (
            select(InterventionPlan)
            .where(
                InterventionPlan.status.in_([
                    InterventionPlanStatus.ACTIVE.value,
                    InterventionPlanStatus.UNDER_REVIEW.value,
                ]),
            )
            .order_by(InterventionPlan.next_review_date.asc())
        )

        # Filter by review date
        if include_overdue:
            query = query.where(InterventionPlan.next_review_date <= cutoff_date)
        else:
            query = query.where(
                InterventionPlan.next_review_date <= cutoff_date,
                InterventionPlan.next_review_date >= today,
            )

        result = await self.db.execute(query)
        plans = result.scalars().all()

        reminders = []
        overdue_count = 0
        upcoming_count = 0

        for plan in plans:
            if plan.next_review_date is None:
                continue

            days_until = (plan.next_review_date - today).days
            if days_until < 0:
                overdue_count += 1
            else:
                upcoming_count += 1

            reminder = PlanReviewReminder(
                plan_id=plan.id,
                child_id=plan.child_id,
                child_name=plan.child_name,
                title=plan.title,
                next_review_date=plan.next_review_date,
                days_until_review=days_until,
                status=InterventionPlanStatus(plan.status),
            )
            reminders.append(reminder)

        return PlanReviewReminderListResponse(
            plans=reminders,
            overdue_count=overdue_count,
            upcoming_count=upcoming_count,
        )

    async def generate_reminder_notifications(
        self,
        days_ahead: int = 30,
        recipient_type: RecipientType = RecipientType.EDUCATOR,
        urgency_filter: Optional[list[ReminderUrgency]] = None,
        base_action_url: Optional[str] = None,
    ) -> ReminderBatch:
        """Generate reminder notifications for plans needing review.

        Creates formatted notification messages for plans that are
        overdue or upcoming for review.

        Args:
            days_ahead: Number of days ahead to look for upcoming reviews
            recipient_type: Type of recipient for the notifications
            urgency_filter: Optional list of urgency levels to include
            base_action_url: Base URL for action links (e.g., plan view page)

        Returns:
            ReminderBatch with generated notifications and counts
        """
        today = date.today()
        cutoff_date = today + timedelta(days=days_ahead)

        # Get plans needing review
        query = (
            select(InterventionPlan)
            .where(
                InterventionPlan.status.in_([
                    InterventionPlanStatus.ACTIVE.value,
                    InterventionPlanStatus.UNDER_REVIEW.value,
                ]),
                InterventionPlan.next_review_date <= cutoff_date,
            )
            .order_by(InterventionPlan.next_review_date.asc())
        )

        result = await self.db.execute(query)
        plans = result.scalars().all()

        notifications = []
        overdue_count = 0
        urgent_count = 0
        upcoming_count = 0

        for plan in plans:
            if plan.next_review_date is None:
                continue

            days_until = (plan.next_review_date - today).days
            urgency = self._determine_urgency(days_until)

            # Apply urgency filter if provided
            if urgency_filter and urgency not in urgency_filter:
                continue

            # Update counts
            if urgency == ReminderUrgency.OVERDUE:
                overdue_count += 1
            elif urgency == ReminderUrgency.URGENT:
                urgent_count += 1
            else:
                upcoming_count += 1

            # Generate notification
            notification = self._create_notification(
                plan=plan,
                days_until=days_until,
                urgency=urgency,
                recipient_type=recipient_type,
                base_action_url=base_action_url,
            )
            notifications.append(notification)

        return ReminderBatch(
            notifications=notifications,
            overdue_count=overdue_count,
            urgent_count=urgent_count,
            upcoming_count=upcoming_count,
            generated_at=datetime.utcnow(),
        )

    async def get_overdue_plans(self) -> list[PlanReviewReminder]:
        """Get all plans that are overdue for review.

        Returns:
            List of PlanReviewReminder for overdue plans
        """
        today = date.today()

        query = (
            select(InterventionPlan)
            .where(
                InterventionPlan.status.in_([
                    InterventionPlanStatus.ACTIVE.value,
                    InterventionPlanStatus.UNDER_REVIEW.value,
                ]),
                InterventionPlan.next_review_date < today,
            )
            .order_by(InterventionPlan.next_review_date.asc())
        )

        result = await self.db.execute(query)
        plans = result.scalars().all()

        reminders = []
        for plan in plans:
            if plan.next_review_date is None:
                continue

            days_until = (plan.next_review_date - today).days
            reminder = PlanReviewReminder(
                plan_id=plan.id,
                child_id=plan.child_id,
                child_name=plan.child_name,
                title=plan.title,
                next_review_date=plan.next_review_date,
                days_until_review=days_until,
                status=InterventionPlanStatus(plan.status),
            )
            reminders.append(reminder)

        return reminders

    async def get_urgent_plans(self, days: int = 7) -> list[PlanReviewReminder]:
        """Get plans that are urgently due for review within specified days.

        Args:
            days: Number of days to consider urgent (default 7)

        Returns:
            List of PlanReviewReminder for urgent plans
        """
        today = date.today()
        cutoff_date = today + timedelta(days=days)

        query = (
            select(InterventionPlan)
            .where(
                InterventionPlan.status.in_([
                    InterventionPlanStatus.ACTIVE.value,
                    InterventionPlanStatus.UNDER_REVIEW.value,
                ]),
                InterventionPlan.next_review_date >= today,
                InterventionPlan.next_review_date <= cutoff_date,
            )
            .order_by(InterventionPlan.next_review_date.asc())
        )

        result = await self.db.execute(query)
        plans = result.scalars().all()

        reminders = []
        for plan in plans:
            if plan.next_review_date is None:
                continue

            days_until = (plan.next_review_date - today).days
            reminder = PlanReviewReminder(
                plan_id=plan.id,
                child_id=plan.child_id,
                child_name=plan.child_name,
                title=plan.title,
                next_review_date=plan.next_review_date,
                days_until_review=days_until,
                status=InterventionPlanStatus(plan.status),
            )
            reminders.append(reminder)

        return reminders

    def calculate_next_review_date(
        self,
        from_date: date,
        schedule: ReviewSchedule,
    ) -> date:
        """Calculate the next review date based on schedule.

        Args:
            from_date: The date to calculate from
            schedule: Review schedule frequency

        Returns:
            Calculated next review date

        Raises:
            InvalidScheduleError: When an invalid schedule is provided
        """
        months_delta = self._get_months_delta(schedule)
        if months_delta is None:
            raise InvalidScheduleError(f"Invalid review schedule: {schedule}")

        return self._add_months(from_date, months_delta)

    def calculate_review_dates_for_year(
        self,
        start_date: date,
        schedule: ReviewSchedule,
    ) -> list[date]:
        """Calculate all review dates for a year from start date.

        Args:
            start_date: The starting date for the plan
            schedule: Review schedule frequency

        Returns:
            List of review dates for the year
        """
        dates = []
        current_date = start_date

        months_delta = self._get_months_delta(schedule)
        if months_delta is None:
            return dates

        end_date = self._add_months(start_date, 12)

        while current_date < end_date:
            next_date = self._add_months(current_date, months_delta)
            if next_date <= end_date:
                dates.append(next_date)
            current_date = next_date

        return dates

    def get_reminder_summary(
        self,
        plans: list[PlanReviewReminder],
    ) -> dict[str, int]:
        """Get a summary of reminder counts by urgency.

        Args:
            plans: List of plan review reminders

        Returns:
            Dictionary with counts by urgency level
        """
        summary = {
            "overdue": 0,
            "urgent": 0,
            "upcoming": 0,
            "total": len(plans),
        }

        for plan in plans:
            urgency = self._determine_urgency(plan.days_until_review)
            if urgency == ReminderUrgency.OVERDUE:
                summary["overdue"] += 1
            elif urgency == ReminderUrgency.URGENT:
                summary["urgent"] += 1
            else:
                summary["upcoming"] += 1

        return summary

    # -------------------------------------------------------------------------
    # Private Helper Methods
    # -------------------------------------------------------------------------

    def _determine_urgency(self, days_until_review: int) -> ReminderUrgency:
        """Determine the urgency level based on days until review.

        Args:
            days_until_review: Number of days until the review is due

        Returns:
            ReminderUrgency level
        """
        if days_until_review < 0:
            return ReminderUrgency.OVERDUE
        elif days_until_review <= 7:
            return ReminderUrgency.URGENT
        elif days_until_review <= 30:
            return ReminderUrgency.UPCOMING
        else:
            return ReminderUrgency.SCHEDULED

    def _create_notification(
        self,
        plan: InterventionPlan,
        days_until: int,
        urgency: ReminderUrgency,
        recipient_type: RecipientType,
        base_action_url: Optional[str] = None,
    ) -> ReminderNotification:
        """Create a notification from a plan.

        Args:
            plan: The intervention plan
            days_until: Days until the review is due
            urgency: Urgency level of the reminder
            recipient_type: Type of recipient
            base_action_url: Optional base URL for action links

        Returns:
            ReminderNotification with formatted content
        """
        # Generate subject
        subject = self._format_subject(
            urgency=urgency,
            child_name=plan.child_name,
            days=abs(days_until),
        )

        # Generate message
        message = self._format_message(
            urgency=urgency,
            child_name=plan.child_name,
            plan_title=plan.title,
            review_date=plan.next_review_date,
            days=abs(days_until),
        )

        # Generate action URL if base provided
        action_url = None
        if base_action_url:
            action_url = f"{base_action_url}/intervention-plans/{plan.id}"

        return ReminderNotification(
            plan_id=plan.id,
            child_id=plan.child_id,
            child_name=plan.child_name,
            plan_title=plan.title,
            next_review_date=plan.next_review_date,
            days_until_review=days_until,
            urgency=urgency,
            recipient_type=recipient_type,
            subject=subject,
            message=message,
            action_url=action_url,
        )

    def _format_subject(
        self,
        urgency: ReminderUrgency,
        child_name: str,
        days: int,
    ) -> str:
        """Format the notification subject line.

        Args:
            urgency: Urgency level
            child_name: Name of the child
            days: Days until/past due

        Returns:
            Formatted subject line
        """
        template = self.SUBJECT_TEMPLATES.get(
            urgency,
            self.SUBJECT_TEMPLATES[ReminderUrgency.UPCOMING],
        )
        return template.format(child_name=child_name, days=days)

    def _format_message(
        self,
        urgency: ReminderUrgency,
        child_name: str,
        plan_title: str,
        review_date: date,
        days: int,
    ) -> str:
        """Format the notification message body.

        Args:
            urgency: Urgency level
            child_name: Name of the child
            plan_title: Title of the intervention plan
            review_date: Date of the scheduled review
            days: Days until/past due

        Returns:
            Formatted message body
        """
        template = self.MESSAGE_TEMPLATES.get(
            urgency,
            self.MESSAGE_TEMPLATES[ReminderUrgency.UPCOMING],
        )
        return template.format(
            child_name=child_name,
            plan_title=plan_title,
            review_date=review_date.strftime("%B %d, %Y"),
            days=days,
            days_overdue=days,
        )

    def _get_months_delta(self, schedule: ReviewSchedule) -> Optional[int]:
        """Get the number of months for a review schedule.

        Args:
            schedule: Review schedule frequency

        Returns:
            Number of months, or None if invalid schedule
        """
        schedule_map = {
            ReviewSchedule.MONTHLY: 1,
            ReviewSchedule.QUARTERLY: 3,
            ReviewSchedule.SEMI_ANNUALLY: 6,
            ReviewSchedule.ANNUALLY: 12,
        }
        return schedule_map.get(schedule)

    def _add_months(self, source_date: date, months: int) -> date:
        """Add a number of months to a date.

        Handles edge cases where the target month doesn't have enough days
        by capping to the last day of the target month.

        Args:
            source_date: The starting date
            months: Number of months to add

        Returns:
            The resulting date
        """
        month = source_date.month - 1 + months
        year = source_date.year + month // 12
        month = month % 12 + 1
        day = min(source_date.day, monthrange(year, month)[1])
        return date(year, month, day)
