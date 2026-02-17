"""Service for intervention plan management.

Provides comprehensive intervention plan management including CRUD operations,
versioning, progress tracking, and review scheduling for special needs support.
The service implements the 8-part intervention plan structure.
"""

import logging
from calendar import monthrange
from datetime import date, datetime, timedelta
from typing import Optional
from uuid import UUID

from sqlalchemy import func, inspect, select
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy.orm import selectinload

from app.models.intervention_plan import (
    InterventionConsultation,
    InterventionGoal,
    InterventionMonitoring,
    InterventionNeed,
    InterventionParentInvolvement,
    InterventionPlan,
    InterventionProgress,
    InterventionStrength,
    InterventionStrategy,
    InterventionVersion,
)
from app.schemas.intervention_plan import (
    ConsultationCreate,
    InterventionPlanCreate,
    InterventionPlanListResponse,
    InterventionPlanResponse,
    InterventionPlanStatus,
    InterventionPlanSummary,
    InterventionPlanUpdate,
    MonitoringCreate,
    NeedCreate,
    ParentInvolvementCreate,
    ParentSignatureRequest,
    ParentSignatureResponse,
    PlanReviewReminder,
    PlanReviewReminderListResponse,
    ProgressCreate,
    ProgressResponse,
    ReviewSchedule,
    SMARTGoalCreate,
    StrategyCreate,
    StrengthCreate,
    VersionCreate,
    VersionResponse,
)


class InterventionPlanServiceError(Exception):
    """Base exception for intervention plan service errors."""

    pass


class PlanNotFoundError(InterventionPlanServiceError):
    """Raised when the intervention plan is not found."""

    pass


class InvalidPlanError(InterventionPlanServiceError):
    """Raised when the plan data is invalid."""

    pass


class UnauthorizedAccessError(InterventionPlanServiceError):
    """Raised when user does not have access to the plan."""

    pass


class PlanVersionError(InterventionPlanServiceError):
    """Raised when there is an error with plan versioning."""

    pass


class InterventionPlanService:
    """Service for managing intervention plans.

    This service provides comprehensive intervention plan management including
    CRUD operations, versioning, progress tracking, and review scheduling.
    Supports the 8-part intervention plan structure for special needs support.

    Attributes:
        db: Async database session for database operations
    """

    def __init__(self, db: AsyncSession) -> None:
        """Initialize the intervention plan service.

        Args:
            db: Async database session
        """
        self.db = db

    async def create_plan(
        self,
        request: InterventionPlanCreate,
        user_id: UUID,
    ) -> InterventionPlanResponse:
        """Create a new intervention plan with optional nested sections.

        Creates a comprehensive intervention plan with all 8 sections if provided.
        Automatically calculates the next review date based on review schedule.

        Args:
            request: The intervention plan creation request
            user_id: ID of the user creating the plan

        Returns:
            InterventionPlanResponse with the created plan and all sections

        Raises:
            InvalidPlanError: When the plan data is invalid
        """
        # Calculate next review date based on schedule
        next_review_date = self._calculate_next_review_date(
            effective_date=request.effective_date or date.today(),
            schedule=request.review_schedule,
        )

        # Create the main plan
        plan = InterventionPlan(
            child_id=request.child_id,
            created_by=user_id,
            title=request.title,
            status=request.status.value,
            version=1,
            # Part 1 - Identification & History
            child_name=request.child_name,
            date_of_birth=request.date_of_birth,
            diagnosis=request.diagnosis,
            medical_history=request.medical_history,
            educational_history=request.educational_history,
            family_context=request.family_context,
            # Review schedule
            review_schedule=request.review_schedule.value,
            next_review_date=request.next_review_date or next_review_date,
            # Dates
            effective_date=request.effective_date,
            end_date=request.end_date,
        )
        self.db.add(plan)
        await self.db.flush()

        # Create Part 2 - Strengths
        if request.strengths:
            for strength_data in request.strengths:
                strength = self._create_strength_model(plan.id, strength_data)
                self.db.add(strength)

        # Create Part 3 - Needs
        if request.needs:
            for need_data in request.needs:
                need = self._create_need_model(plan.id, need_data)
                self.db.add(need)

        # Create Part 4 - SMART Goals
        if request.goals:
            for goal_data in request.goals:
                goal = self._create_goal_model(plan.id, goal_data)
                self.db.add(goal)

        # Create Part 5 - Strategies
        if request.strategies:
            for strategy_data in request.strategies:
                strategy = self._create_strategy_model(plan.id, strategy_data)
                self.db.add(strategy)

        # Create Part 6 - Monitoring
        if request.monitoring:
            for monitoring_data in request.monitoring:
                monitoring = self._create_monitoring_model(plan.id, monitoring_data)
                self.db.add(monitoring)

        # Create Part 7 - Parent Involvement
        if request.parent_involvements:
            for involvement_data in request.parent_involvements:
                involvement = self._create_parent_involvement_model(
                    plan.id, involvement_data
                )
                self.db.add(involvement)

        # Create Part 8 - Consultations
        if request.consultations:
            for consultation_data in request.consultations:
                consultation = self._create_consultation_model(
                    plan.id, consultation_data
                )
                self.db.add(consultation)

        # Flush to ensure all related entities are saved
        await self.db.flush()

        # Reload plan with all relationships for snapshot
        plan = await self._get_plan_with_relations(plan.id)

        # Create snapshot of initial state
        snapshot = await self._create_plan_snapshot(plan)

        # Create initial version record
        version = InterventionVersion(
            plan_id=plan.id,
            version_number=1,
            created_by=user_id,
            change_summary="Initial plan creation",
            snapshot_data=snapshot,
        )
        self.db.add(version)

        await self.db.commit()

        # Refresh and return the full plan
        return await self.get_plan(plan.id, user_id)

    async def get_plan(
        self,
        plan_id: UUID,
        user_id: UUID,
    ) -> InterventionPlanResponse:
        """Get an intervention plan by ID with all sections.

        Args:
            plan_id: ID of the intervention plan
            user_id: ID of the user requesting the plan

        Returns:
            InterventionPlanResponse with all sections loaded

        Raises:
            PlanNotFoundError: When the plan is not found
        """
        # Query with all relationships loaded
        query = (
            select(InterventionPlan)
            .where(InterventionPlan.id == plan_id)
            .options(
                selectinload(InterventionPlan.strengths),
                selectinload(InterventionPlan.needs),
                selectinload(InterventionPlan.goals),
                selectinload(InterventionPlan.strategies),
                selectinload(InterventionPlan.monitoring),
                selectinload(InterventionPlan.parent_involvements),
                selectinload(InterventionPlan.consultations),
                selectinload(InterventionPlan.progress_records),
                selectinload(InterventionPlan.versions),
            )
        )
        result = await self.db.execute(query)
        plan = result.scalar_one_or_none()

        if not plan:
            raise PlanNotFoundError(f"Intervention plan with ID {plan_id} not found")

        return InterventionPlanResponse.model_validate(plan)

    async def update_plan(
        self,
        plan_id: UUID,
        request: InterventionPlanUpdate,
        user_id: UUID,
        create_version: bool = True,
    ) -> InterventionPlanResponse:
        """Update an intervention plan.

        Updates the plan's Part 1 fields and metadata. Optionally creates
        a new version record to track changes.

        Args:
            plan_id: ID of the intervention plan to update
            request: The update request with fields to modify
            user_id: ID of the user making the update
            create_version: Whether to create a version record for this update

        Returns:
            InterventionPlanResponse with updated plan

        Raises:
            PlanNotFoundError: When the plan is not found
        """
        # Get existing plan
        query = select(InterventionPlan).where(InterventionPlan.id == plan_id)
        result = await self.db.execute(query)
        plan = result.scalar_one_or_none()

        if not plan:
            raise PlanNotFoundError(f"Intervention plan with ID {plan_id} not found")

        # Create version snapshot before update if requested
        if create_version:
            snapshot = await self._create_plan_snapshot(plan)
            plan.version += 1

            version = InterventionVersion(
                plan_id=plan.id,
                version_number=plan.version,
                created_by=user_id,
                change_summary=self._generate_change_summary(request),
                snapshot_data=snapshot,
            )
            self.db.add(version)

        # Update fields that are provided
        update_data = request.model_dump(exclude_unset=True)
        for field, value in update_data.items():
            if hasattr(plan, field):
                if field == "status" and value is not None:
                    setattr(plan, field, value.value if hasattr(value, "value") else value)
                elif field == "review_schedule" and value is not None:
                    setattr(plan, field, value.value if hasattr(value, "value") else value)
                else:
                    setattr(plan, field, value)

        # Recalculate next review date if schedule changed
        if request.review_schedule is not None:
            plan.next_review_date = self._calculate_next_review_date(
                effective_date=plan.effective_date or date.today(),
                schedule=request.review_schedule,
            )

        plan.updated_at = datetime.utcnow()

        await self.db.commit()

        return await self.get_plan(plan_id, user_id)

    async def list_plans(
        self,
        user_id: UUID,
        child_id: Optional[UUID] = None,
        status: Optional[InterventionPlanStatus] = None,
        skip: int = 0,
        limit: int = 100,
    ) -> InterventionPlanListResponse:
        """List intervention plans with optional filters.

        Args:
            user_id: ID of the user requesting the list
            child_id: Optional filter by child ID
            status: Optional filter by plan status
            skip: Number of records to skip
            limit: Maximum number of records to return

        Returns:
            InterventionPlanListResponse with paginated plan summaries
        """
        # Build base query
        query = select(InterventionPlan)

        # Apply filters
        if child_id:
            query = query.where(InterventionPlan.child_id == child_id)
        if status:
            query = query.where(InterventionPlan.status == status.value)

        # Get total count
        count_query = select(func.count()).select_from(query.subquery())
        total_result = await self.db.execute(count_query)
        total = total_result.scalar() or 0

        # Add pagination and ordering
        query = (
            query.order_by(InterventionPlan.created_at.desc())
            .offset(skip)
            .limit(limit)
            .options(
                selectinload(InterventionPlan.goals),
                selectinload(InterventionPlan.progress_records),
            )
        )

        result = await self.db.execute(query)
        plans = result.scalars().all()

        # Convert to summaries
        summaries = []
        for plan in plans:
            summary = InterventionPlanSummary(
                id=plan.id,
                child_id=plan.child_id,
                child_name=plan.child_name,
                title=plan.title,
                status=InterventionPlanStatus(plan.status),
                version=plan.version,
                review_schedule=ReviewSchedule(plan.review_schedule),
                next_review_date=plan.next_review_date,
                parent_signed=plan.parent_signed,
                goals_count=len(plan.goals),
                progress_count=len(plan.progress_records),
                created_at=plan.created_at,
                updated_at=plan.updated_at,
            )
            summaries.append(summary)

        return InterventionPlanListResponse(
            items=summaries,
            total=total,
            skip=skip,
            limit=limit,
        )

    async def delete_plan(
        self,
        plan_id: UUID,
        user_id: UUID,
    ) -> bool:
        """Delete an intervention plan.

        Soft-deletes by archiving the plan rather than hard delete.

        Args:
            plan_id: ID of the intervention plan to delete
            user_id: ID of the user performing the delete

        Returns:
            True if the plan was deleted/archived

        Raises:
            PlanNotFoundError: When the plan is not found
        """
        query = select(InterventionPlan).where(InterventionPlan.id == plan_id)
        result = await self.db.execute(query)
        plan = result.scalar_one_or_none()

        if not plan:
            raise PlanNotFoundError(f"Intervention plan with ID {plan_id} not found")

        # Soft delete by archiving
        plan.status = InterventionPlanStatus.ARCHIVED.value
        plan.updated_at = datetime.utcnow()

        await self.db.commit()
        return True

    async def create_version(
        self,
        plan_id: UUID,
        user_id: UUID,
        change_summary: Optional[str] = None,
    ) -> VersionResponse:
        """Create a new version snapshot of the plan.

        Creates a version record capturing the current state of the plan
        for historical reference and audit trail.

        Args:
            plan_id: ID of the intervention plan
            user_id: ID of the user creating the version
            change_summary: Optional description of changes

        Returns:
            VersionResponse with the created version record

        Raises:
            PlanNotFoundError: When the plan is not found
        """
        # Get the plan with all relationships
        plan = await self._get_plan_with_relations(plan_id)
        if not plan:
            raise PlanNotFoundError(f"Intervention plan with ID {plan_id} not found")

        # Create snapshot
        snapshot = await self._create_plan_snapshot(plan)

        # Increment version
        plan.version += 1
        plan.updated_at = datetime.utcnow()

        # Create version record
        version = InterventionVersion(
            plan_id=plan.id,
            version_number=plan.version,
            created_by=user_id,
            change_summary=change_summary or f"Version {plan.version} created",
            snapshot_data=snapshot,
        )
        self.db.add(version)

        await self.db.commit()
        await self.db.refresh(version)

        return VersionResponse.model_validate(version)

    async def add_progress(
        self,
        plan_id: UUID,
        request: ProgressCreate,
        user_id: UUID,
    ) -> ProgressResponse:
        """Add a progress record to the intervention plan.

        Records progress toward intervention goals with detailed notes
        and measurement values.

        Args:
            plan_id: ID of the intervention plan
            request: Progress record data
            user_id: ID of the user recording progress

        Returns:
            ProgressResponse with the created progress record

        Raises:
            PlanNotFoundError: When the plan is not found
        """
        # Verify plan exists
        query = select(InterventionPlan).where(InterventionPlan.id == plan_id)
        result = await self.db.execute(query)
        plan = result.scalar_one_or_none()

        if not plan:
            raise PlanNotFoundError(f"Intervention plan with ID {plan_id} not found")

        # Create progress record
        progress = InterventionProgress(
            plan_id=plan_id,
            goal_id=request.goal_id,
            recorded_by=user_id,
            record_date=request.record_date,
            progress_notes=request.progress_notes,
            progress_level=request.progress_level.value,
            measurement_value=request.measurement_value,
            barriers=request.barriers,
            next_steps=request.next_steps,
            attachments=request.attachments,
        )
        self.db.add(progress)

        # Update goal progress percentage if goal_id provided
        if request.goal_id:
            goal_query = select(InterventionGoal).where(
                InterventionGoal.id == request.goal_id
            )
            goal_result = await self.db.execute(goal_query)
            goal = goal_result.scalar_one_or_none()
            if goal:
                # Update progress based on level
                progress_map = {
                    "no_progress": 0.0,
                    "minimal": 25.0,
                    "moderate": 50.0,
                    "significant": 75.0,
                    "achieved": 100.0,
                }
                goal.progress_percentage = progress_map.get(
                    request.progress_level.value, goal.progress_percentage
                )
                if request.progress_level.value == "achieved":
                    goal.status = "achieved"
                elif goal.status == "not_started":
                    goal.status = "in_progress"
                goal.updated_at = datetime.utcnow()

        await self.db.commit()
        await self.db.refresh(progress)

        return ProgressResponse.model_validate(progress)

    async def get_plan_history(
        self,
        plan_id: UUID,
        user_id: UUID,
    ) -> list[VersionResponse]:
        """Get the version history for an intervention plan.

        Returns all version snapshots in chronological order.

        Args:
            plan_id: ID of the intervention plan
            user_id: ID of the user requesting history

        Returns:
            List of VersionResponse records

        Raises:
            PlanNotFoundError: When the plan is not found
        """
        # Verify plan exists
        query = select(InterventionPlan).where(InterventionPlan.id == plan_id)
        result = await self.db.execute(query)
        plan = result.scalar_one_or_none()

        if not plan:
            raise PlanNotFoundError(f"Intervention plan with ID {plan_id} not found")

        # Get all versions
        version_query = (
            select(InterventionVersion)
            .where(InterventionVersion.plan_id == plan_id)
            .order_by(InterventionVersion.version_number.asc())
        )
        version_result = await self.db.execute(version_query)
        versions = version_result.scalars().all()

        return [VersionResponse.model_validate(v) for v in versions]

    async def get_plans_for_review(
        self,
        user_id: UUID,
        days_ahead: int = 30,
    ) -> PlanReviewReminderListResponse:
        """Get plans that are due or upcoming for review.

        Returns plans where next_review_date is within the specified
        number of days or overdue.

        Args:
            user_id: ID of the user requesting the list
            days_ahead: Number of days ahead to look for upcoming reviews

        Returns:
            PlanReviewReminderListResponse with plans needing review
        """
        today = date.today()
        cutoff_date = today + timedelta(days=days_ahead)

        # Get plans with upcoming or overdue reviews
        query = (
            select(InterventionPlan)
            .where(
                InterventionPlan.next_review_date <= cutoff_date,
                InterventionPlan.status.in_([
                    InterventionPlanStatus.ACTIVE.value,
                    InterventionPlanStatus.UNDER_REVIEW.value,
                ]),
            )
            .order_by(InterventionPlan.next_review_date.asc())
        )

        result = await self.db.execute(query)
        plans = result.scalars().all()

        reminders = []
        overdue_count = 0
        upcoming_count = 0

        for plan in plans:
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

    async def sign_plan(
        self,
        plan_id: UUID,
        request: ParentSignatureRequest,
        parent_id: UUID,
    ) -> ParentSignatureResponse:
        """Record parent signature on an intervention plan.

        Args:
            plan_id: ID of the intervention plan
            request: Parent signature data
            parent_id: ID of the parent signing

        Returns:
            ParentSignatureResponse confirming the signature

        Raises:
            PlanNotFoundError: When the plan is not found
            InvalidPlanError: When the plan cannot be signed
        """
        query = select(InterventionPlan).where(InterventionPlan.id == plan_id)
        result = await self.db.execute(query)
        plan = result.scalar_one_or_none()

        if not plan:
            raise PlanNotFoundError(f"Intervention plan with ID {plan_id} not found")

        if plan.parent_signed:
            raise InvalidPlanError("Plan has already been signed")

        if not request.agreed_to_terms:
            raise InvalidPlanError("Must agree to terms to sign the plan")

        # Record signature
        signature_date = datetime.utcnow()
        plan.parent_signed = True
        plan.parent_signature_date = signature_date
        plan.parent_signature_data = request.signature_data
        plan.updated_at = signature_date

        await self.db.commit()

        return ParentSignatureResponse(
            plan_id=plan_id,
            parent_signed=True,
            parent_signature_date=signature_date,
            message="Intervention plan signed successfully",
        )

    # -------------------------------------------------------------------------
    # Section CRUD Operations
    # -------------------------------------------------------------------------

    async def add_strength(
        self,
        plan_id: UUID,
        request: StrengthCreate,
        user_id: UUID,
    ) -> InterventionPlanResponse:
        """Add a strength to an intervention plan.

        Args:
            plan_id: ID of the intervention plan
            request: Strength data
            user_id: ID of the user making the change

        Returns:
            Updated InterventionPlanResponse
        """
        await self._verify_plan_exists(plan_id)
        strength = self._create_strength_model(plan_id, request)
        self.db.add(strength)
        await self.db.commit()
        return await self.get_plan(plan_id, user_id)

    async def add_need(
        self,
        plan_id: UUID,
        request: NeedCreate,
        user_id: UUID,
    ) -> InterventionPlanResponse:
        """Add a need to an intervention plan."""
        await self._verify_plan_exists(plan_id)
        need = self._create_need_model(plan_id, request)
        self.db.add(need)
        await self.db.commit()
        return await self.get_plan(plan_id, user_id)

    async def add_goal(
        self,
        plan_id: UUID,
        request: SMARTGoalCreate,
        user_id: UUID,
    ) -> InterventionPlanResponse:
        """Add a SMART goal to an intervention plan."""
        await self._verify_plan_exists(plan_id)
        goal = self._create_goal_model(plan_id, request)
        self.db.add(goal)
        await self.db.commit()
        return await self.get_plan(plan_id, user_id)

    async def add_strategy(
        self,
        plan_id: UUID,
        request: StrategyCreate,
        user_id: UUID,
    ) -> InterventionPlanResponse:
        """Add a strategy to an intervention plan."""
        await self._verify_plan_exists(plan_id)
        strategy = self._create_strategy_model(plan_id, request)
        self.db.add(strategy)
        await self.db.commit()
        return await self.get_plan(plan_id, user_id)

    async def add_monitoring(
        self,
        plan_id: UUID,
        request: MonitoringCreate,
        user_id: UUID,
    ) -> InterventionPlanResponse:
        """Add a monitoring approach to an intervention plan."""
        await self._verify_plan_exists(plan_id)
        monitoring = self._create_monitoring_model(plan_id, request)
        self.db.add(monitoring)
        await self.db.commit()
        return await self.get_plan(plan_id, user_id)

    async def add_parent_involvement(
        self,
        plan_id: UUID,
        request: ParentInvolvementCreate,
        user_id: UUID,
    ) -> InterventionPlanResponse:
        """Add a parent involvement activity to an intervention plan."""
        await self._verify_plan_exists(plan_id)
        involvement = self._create_parent_involvement_model(plan_id, request)
        self.db.add(involvement)
        await self.db.commit()
        return await self.get_plan(plan_id, user_id)

    async def add_consultation(
        self,
        plan_id: UUID,
        request: ConsultationCreate,
        user_id: UUID,
    ) -> InterventionPlanResponse:
        """Add a consultation to an intervention plan."""
        await self._verify_plan_exists(plan_id)
        consultation = self._create_consultation_model(plan_id, request)
        self.db.add(consultation)
        await self.db.commit()
        return await self.get_plan(plan_id, user_id)

    # -------------------------------------------------------------------------
    # Private Helper Methods
    # -------------------------------------------------------------------------

    async def _verify_plan_exists(self, plan_id: UUID) -> InterventionPlan:
        """Verify a plan exists and return it.

        Args:
            plan_id: ID of the intervention plan

        Returns:
            InterventionPlan model

        Raises:
            PlanNotFoundError: When the plan is not found
        """
        query = select(InterventionPlan).where(InterventionPlan.id == plan_id)
        result = await self.db.execute(query)
        plan = result.scalar_one_or_none()

        if not plan:
            raise PlanNotFoundError(f"Intervention plan with ID {plan_id} not found")

        return plan

    async def _get_plan_with_relations(self, plan_id: UUID) -> Optional[InterventionPlan]:
        """Get a plan with all relationships loaded."""
        query = (
            select(InterventionPlan)
            .where(InterventionPlan.id == plan_id)
            .options(
                selectinload(InterventionPlan.strengths),
                selectinload(InterventionPlan.needs),
                selectinload(InterventionPlan.goals),
                selectinload(InterventionPlan.strategies),
                selectinload(InterventionPlan.monitoring),
                selectinload(InterventionPlan.parent_involvements),
                selectinload(InterventionPlan.consultations),
                selectinload(InterventionPlan.progress_records),
                selectinload(InterventionPlan.versions),
            )
        )
        result = await self.db.execute(query)
        return result.scalar_one_or_none()

    def _calculate_next_review_date(
        self,
        effective_date: date,
        schedule: ReviewSchedule,
    ) -> date:
        """Calculate the next review date based on schedule.

        Args:
            effective_date: The plan's effective date
            schedule: Review schedule frequency

        Returns:
            Calculated next review date
        """
        return self._add_months(effective_date, self._get_months_delta(schedule))

    def _get_months_delta(self, schedule: ReviewSchedule) -> int:
        """Get the number of months for a review schedule.

        Args:
            schedule: Review schedule frequency

        Returns:
            Number of months
        """
        schedule_map = {
            ReviewSchedule.MONTHLY: 1,
            ReviewSchedule.QUARTERLY: 3,
            ReviewSchedule.SEMI_ANNUALLY: 6,
            ReviewSchedule.ANNUALLY: 12,
        }
        return schedule_map.get(schedule, 3)

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

    async def _create_plan_snapshot(self, plan: InterventionPlan) -> dict:
        """Create a JSON snapshot of the plan for versioning.

        Args:
            plan: The intervention plan to snapshot

        Returns:
            Dictionary representation of the plan
        """
        # Validate that relationships are loaded before snapshot
        logger = logging.getLogger(__name__)
        plan_state = inspect(plan)

        relationships_to_check = [
            "strengths",
            "needs",
            "goals",
            "strategies",
            "monitoring",
            "parent_involvements",
            "consultations",
        ]

        unloaded_relationships = []
        for rel_name in relationships_to_check:
            if rel_name in plan_state.unloaded:
                unloaded_relationships.append(rel_name)

        if unloaded_relationships:
            logger.warning(
                f"Creating plan snapshot with unloaded relationships: {', '.join(unloaded_relationships)}. "
                f"Plan ID: {plan.id}. This may result in incomplete snapshot data."
            )

        # Get the most recent version to track lineage
        parent_version_id = None
        version_query = (
            select(InterventionVersion)
            .where(InterventionVersion.plan_id == plan.id)
            .order_by(InterventionVersion.version_number.desc())
            .limit(1)
        )
        version_result = await self.db.execute(version_query)
        latest_version = version_result.scalar_one_or_none()
        if latest_version:
            parent_version_id = str(latest_version.id)

        return {
            "id": str(plan.id),
            "child_id": str(plan.child_id),
            "created_by": str(plan.created_by) if plan.created_by else None,
            "parent_version_id": parent_version_id,
            "title": plan.title,
            "status": plan.status,
            "version": plan.version,
            "child_name": plan.child_name,
            "date_of_birth": plan.date_of_birth.isoformat() if plan.date_of_birth else None,
            "diagnosis": plan.diagnosis,
            "medical_history": plan.medical_history,
            "educational_history": plan.educational_history,
            "family_context": plan.family_context,
            "review_schedule": plan.review_schedule,
            "next_review_date": plan.next_review_date.isoformat() if plan.next_review_date else None,
            "effective_date": plan.effective_date.isoformat() if plan.effective_date else None,
            "end_date": plan.end_date.isoformat() if plan.end_date else None,
            "parent_signed": plan.parent_signed,
            "created_at": plan.created_at.isoformat() if plan.created_at else None,
            "updated_at": plan.updated_at.isoformat() if plan.updated_at else None,
            "strengths": [
                {
                    "id": str(s.id),
                    "category": s.category,
                    "description": s.description,
                    "examples": s.examples,
                    "order": s.order,
                }
                for s in plan.strengths
            ],
            "needs": [
                {
                    "id": str(n.id),
                    "category": n.category,
                    "description": n.description,
                    "priority": n.priority,
                    "baseline": n.baseline,
                    "order": n.order,
                }
                for n in plan.needs
            ],
            "goals": [
                {
                    "id": str(g.id),
                    "title": g.title,
                    "description": g.description,
                    "measurement_criteria": g.measurement_criteria,
                    "measurement_baseline": g.measurement_baseline,
                    "measurement_target": g.measurement_target,
                    "achievability_notes": g.achievability_notes,
                    "relevance_notes": g.relevance_notes,
                    "target_date": g.target_date.isoformat() if g.target_date else None,
                    "status": g.status,
                    "progress_percentage": g.progress_percentage,
                    "order": g.order,
                }
                for g in plan.goals
            ],
            "strategies": [
                {
                    "id": str(s.id),
                    "title": s.title,
                    "description": s.description,
                    "responsible_party": s.responsible_party,
                    "frequency": s.frequency,
                    "materials_needed": s.materials_needed,
                    "accommodations": s.accommodations,
                    "order": s.order,
                }
                for s in plan.strategies
            ],
            "monitoring": [
                {
                    "id": str(m.id),
                    "method": m.method,
                    "description": m.description,
                    "frequency": m.frequency,
                    "responsible_party": m.responsible_party,
                    "data_collection_tools": m.data_collection_tools,
                    "success_indicators": m.success_indicators,
                    "order": m.order,
                }
                for m in plan.monitoring
            ],
            "parent_involvements": [
                {
                    "id": str(p.id),
                    "activity_type": p.activity_type,
                    "title": p.title,
                    "description": p.description,
                    "frequency": p.frequency,
                    "resources_provided": p.resources_provided,
                    "communication_method": p.communication_method,
                    "order": p.order,
                }
                for p in plan.parent_involvements
            ],
            "consultations": [
                {
                    "id": str(c.id),
                    "specialist_type": c.specialist_type,
                    "specialist_name": c.specialist_name,
                    "organization": c.organization,
                    "purpose": c.purpose,
                    "recommendations": c.recommendations,
                    "consultation_date": c.consultation_date.isoformat() if c.consultation_date else None,
                    "next_consultation_date": c.next_consultation_date.isoformat() if c.next_consultation_date else None,
                    "notes": c.notes,
                    "order": c.order,
                }
                for c in plan.consultations
            ],
        }

    def _generate_change_summary(self, request: InterventionPlanUpdate) -> str:
        """Generate a summary of changes from an update request.

        Args:
            request: The update request

        Returns:
            Human-readable change summary
        """
        changed_fields = []
        update_data = request.model_dump(exclude_unset=True)

        field_names = {
            "title": "Title",
            "status": "Status",
            "child_name": "Child name",
            "date_of_birth": "Date of birth",
            "diagnosis": "Diagnosis",
            "medical_history": "Medical history",
            "educational_history": "Educational history",
            "family_context": "Family context",
            "review_schedule": "Review schedule",
            "next_review_date": "Next review date",
            "effective_date": "Effective date",
            "end_date": "End date",
        }

        for field, value in update_data.items():
            if value is not None and field in field_names:
                changed_fields.append(field_names[field])

        if not changed_fields:
            return "Plan updated"

        if len(changed_fields) == 1:
            return f"Updated {changed_fields[0]}"

        return f"Updated {', '.join(changed_fields[:-1])} and {changed_fields[-1]}"

    def _create_strength_model(
        self, plan_id: UUID, data: StrengthCreate
    ) -> InterventionStrength:
        """Create a strength model from schema data."""
        return InterventionStrength(
            plan_id=plan_id,
            category=data.category.value,
            description=data.description,
            examples=data.examples,
            order=data.order,
        )

    def _create_need_model(self, plan_id: UUID, data: NeedCreate) -> InterventionNeed:
        """Create a need model from schema data."""
        return InterventionNeed(
            plan_id=plan_id,
            category=data.category.value,
            description=data.description,
            priority=data.priority.value,
            baseline=data.baseline,
            order=data.order,
        )

    def _create_goal_model(
        self, plan_id: UUID, data: SMARTGoalCreate
    ) -> InterventionGoal:
        """Create a goal model from schema data."""
        return InterventionGoal(
            plan_id=plan_id,
            need_id=data.need_id,
            title=data.title,
            description=data.description,
            measurement_criteria=data.measurement_criteria,
            measurement_baseline=data.measurement_baseline,
            measurement_target=data.measurement_target,
            achievability_notes=data.achievability_notes,
            relevance_notes=data.relevance_notes,
            target_date=data.target_date,
            status=data.status.value,
            progress_percentage=data.progress_percentage,
            order=data.order,
        )

    def _create_strategy_model(
        self, plan_id: UUID, data: StrategyCreate
    ) -> InterventionStrategy:
        """Create a strategy model from schema data."""
        return InterventionStrategy(
            plan_id=plan_id,
            goal_id=data.goal_id,
            title=data.title,
            description=data.description,
            responsible_party=data.responsible_party.value,
            frequency=data.frequency,
            materials_needed=data.materials_needed,
            accommodations=data.accommodations,
            order=data.order,
        )

    def _create_monitoring_model(
        self, plan_id: UUID, data: MonitoringCreate
    ) -> InterventionMonitoring:
        """Create a monitoring model from schema data."""
        return InterventionMonitoring(
            plan_id=plan_id,
            goal_id=data.goal_id,
            method=data.method.value,
            description=data.description,
            frequency=data.frequency,
            responsible_party=data.responsible_party.value,
            data_collection_tools=data.data_collection_tools,
            success_indicators=data.success_indicators,
            order=data.order,
        )

    def _create_parent_involvement_model(
        self, plan_id: UUID, data: ParentInvolvementCreate
    ) -> InterventionParentInvolvement:
        """Create a parent involvement model from schema data."""
        return InterventionParentInvolvement(
            plan_id=plan_id,
            activity_type=data.activity_type.value,
            title=data.title,
            description=data.description,
            frequency=data.frequency,
            resources_provided=data.resources_provided,
            communication_method=data.communication_method,
            order=data.order,
        )

    def _create_consultation_model(
        self, plan_id: UUID, data: ConsultationCreate
    ) -> InterventionConsultation:
        """Create a consultation model from schema data."""
        return InterventionConsultation(
            plan_id=plan_id,
            specialist_type=data.specialist_type.value,
            specialist_name=data.specialist_name,
            organization=data.organization,
            purpose=data.purpose,
            recommendations=data.recommendations,
            consultation_date=data.consultation_date,
            next_consultation_date=data.next_consultation_date,
            notes=data.notes,
            order=data.order,
        )
