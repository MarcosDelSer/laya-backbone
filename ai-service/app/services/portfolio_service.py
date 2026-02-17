"""Portfolio service for LAYA AI Service.

Provides business logic for portfolio management including CRUD operations
for portfolio items, observations, milestones, and work samples.
Supports educational documentation with privacy controls.
"""

from datetime import date, datetime
from typing import Optional
from uuid import UUID

from sqlalchemy import and_, cast, delete, func, select, String, update
from sqlalchemy.ext.asyncio import AsyncSession

from app.models.portfolio import (
    Milestone,
    MilestoneCategory,
    MilestoneStatus,
    Observation,
    ObservationType,
    PortfolioItem,
    PortfolioItemType,
    PrivacyLevel,
    WorkSample,
    WorkSampleType,
)
from app.schemas.portfolio import (
    MilestoneCreate,
    MilestoneListResponse,
    MilestoneResponse,
    MilestoneUpdate,
    ObservationCreate,
    ObservationListResponse,
    ObservationResponse,
    ObservationUpdate,
    PortfolioItemCreate,
    PortfolioItemListResponse,
    PortfolioItemResponse,
    PortfolioItemUpdate,
    PortfolioSummary,
    WorkSampleCreate,
    WorkSampleListResponse,
    WorkSampleResponse,
    WorkSampleUpdate,
)


class PortfolioServiceError(Exception):
    """Base exception for portfolio service errors."""

    pass


class PortfolioItemNotFoundError(PortfolioServiceError):
    """Raised when a portfolio item is not found."""

    pass


class ObservationNotFoundError(PortfolioServiceError):
    """Raised when an observation is not found."""

    pass


class MilestoneNotFoundError(PortfolioServiceError):
    """Raised when a milestone is not found."""

    pass


class WorkSampleNotFoundError(PortfolioServiceError):
    """Raised when a work sample is not found."""

    pass


class PortfolioService:
    """Service class for portfolio management business logic.

    Encapsulates business logic for managing educational portfolios including
    media items, observations, milestones, and work samples with privacy controls.

    Attributes:
        db: Async database session for database operations.
    """

    def __init__(self, db: AsyncSession) -> None:
        """Initialize PortfolioService with database session.

        Args:
            db: Async database session for database operations.
        """
        self.db = db

    # =========================================================================
    # Portfolio Item Operations
    # =========================================================================

    async def create_portfolio_item(
        self, data: PortfolioItemCreate
    ) -> PortfolioItem:
        """Create a new portfolio item.

        Args:
            data: Portfolio item creation data.

        Returns:
            The created PortfolioItem record.
        """
        portfolio_item = PortfolioItem(
            child_id=data.child_id,
            item_type=PortfolioItemType(data.item_type.value),
            title=data.title,
            description=data.description,
            media_url=data.media_url,
            thumbnail_url=data.thumbnail_url,
            privacy_level=PrivacyLevel(data.privacy_level.value),
            tags=data.tags,
            captured_at=data.captured_at or datetime.utcnow(),
            captured_by_id=data.captured_by_id,
            is_family_contribution=data.is_family_contribution,
            item_metadata=data.item_metadata,
        )
        self.db.add(portfolio_item)
        await self.db.commit()
        await self.db.refresh(portfolio_item)
        return portfolio_item

    async def get_portfolio_item_by_id(
        self, item_id: UUID
    ) -> Optional[PortfolioItem]:
        """Retrieve a single portfolio item by ID.

        Args:
            item_id: Unique identifier of the portfolio item.

        Returns:
            PortfolioItem if found, None otherwise.
        """
        query = select(PortfolioItem).where(
            cast(PortfolioItem.id, String) == str(item_id)
        )
        result = await self.db.execute(query)
        return result.scalar_one_or_none()

    async def list_portfolio_items(
        self,
        child_id: UUID,
        skip: int = 0,
        limit: int = 100,
        item_type: Optional[str] = None,
        privacy_level: Optional[str] = None,
        include_archived: bool = False,
        tags: Optional[list[str]] = None,
    ) -> tuple[list[PortfolioItem], int]:
        """List portfolio items for a child with optional filtering.

        Args:
            child_id: Unique identifier of the child.
            skip: Number of records to skip.
            limit: Maximum number of records to return.
            item_type: Optional filter by item type.
            privacy_level: Optional filter by privacy level.
            include_archived: Whether to include archived items.
            tags: Optional filter by tags (any match).

        Returns:
            Tuple of (list of portfolio items, total count).
        """
        # Build base query
        query = select(PortfolioItem).where(
            cast(PortfolioItem.child_id, String) == str(child_id)
        )

        if not include_archived:
            query = query.where(PortfolioItem.is_archived == False)

        if item_type:
            try:
                query = query.where(
                    PortfolioItem.item_type == PortfolioItemType(item_type)
                )
            except ValueError:
                # Invalid item_type filter - ignore and continue without filter
                pass

        if privacy_level:
            try:
                query = query.where(
                    PortfolioItem.privacy_level == PrivacyLevel(privacy_level)
                )
            except ValueError:
                # Invalid privacy_level filter - ignore and continue without filter
                pass

        # Note: Tag filtering with ARRAY overlap would need PostgreSQL specific
        # For now, we skip tag filtering in the query for SQLite compatibility

        # Get total count
        count_query = select(func.count()).select_from(query.subquery())
        count_result = await self.db.execute(count_query)
        total = count_result.scalar() or 0

        # Apply pagination and ordering
        query = query.offset(skip).limit(limit).order_by(
            PortfolioItem.captured_at.desc()
        )

        result = await self.db.execute(query)
        items = list(result.scalars().all())

        # Apply tag filtering in memory if needed
        if tags:
            items = [
                item for item in items
                if any(tag in item.tags for tag in tags)
            ]

        return items, total

    async def update_portfolio_item(
        self, item_id: UUID, data: PortfolioItemUpdate
    ) -> PortfolioItem:
        """Update a portfolio item.

        Args:
            item_id: Unique identifier of the portfolio item.
            data: Portfolio item update data.

        Returns:
            The updated PortfolioItem record.

        Raises:
            PortfolioItemNotFoundError: If the portfolio item is not found.
        """
        # Check if exists first
        portfolio_item = await self.get_portfolio_item_by_id(item_id)
        if not portfolio_item:
            raise PortfolioItemNotFoundError(
                f"Portfolio item with ID {item_id} not found"
            )

        # Build update values from provided data
        update_data = data.model_dump(exclude_unset=True)
        if update_data:
            # Convert enum values to their proper types
            if "privacy_level" in update_data and update_data["privacy_level"]:
                update_data["privacy_level"] = PrivacyLevel(
                    update_data["privacy_level"].value
                )

            # Use explicit SQL UPDATE for database compatibility
            stmt = (
                update(PortfolioItem)
                .where(cast(PortfolioItem.id, String) == str(item_id))
                .values(**update_data)
            )
            await self.db.execute(stmt)
            await self.db.commit()

        # Re-fetch and return the updated record
        return await self.get_portfolio_item_by_id(item_id)

    async def delete_portfolio_item(self, item_id: UUID) -> bool:
        """Delete a portfolio item (soft delete by archiving).

        Args:
            item_id: Unique identifier of the portfolio item.

        Returns:
            True if deleted successfully.

        Raises:
            PortfolioItemNotFoundError: If the portfolio item is not found.
        """
        portfolio_item = await self.get_portfolio_item_by_id(item_id)
        if not portfolio_item:
            raise PortfolioItemNotFoundError(
                f"Portfolio item with ID {item_id} not found"
            )

        # Use explicit SQL UPDATE for soft delete (database compatibility)
        stmt = (
            update(PortfolioItem)
            .where(cast(PortfolioItem.id, String) == str(item_id))
            .values(is_archived=True)
        )
        await self.db.execute(stmt)
        await self.db.commit()
        return True

    async def hard_delete_portfolio_item(self, item_id: UUID) -> bool:
        """Permanently delete a portfolio item.

        Args:
            item_id: Unique identifier of the portfolio item.

        Returns:
            True if deleted successfully.

        Raises:
            PortfolioItemNotFoundError: If the portfolio item is not found.
        """
        portfolio_item = await self.get_portfolio_item_by_id(item_id)
        if not portfolio_item:
            raise PortfolioItemNotFoundError(
                f"Portfolio item with ID {item_id} not found"
            )

        await self.db.delete(portfolio_item)
        await self.db.commit()
        return True

    # =========================================================================
    # Observation Operations
    # =========================================================================

    async def create_observation(self, data: ObservationCreate) -> Observation:
        """Create a new observation.

        Args:
            data: Observation creation data.

        Returns:
            The created Observation record.
        """
        observation = Observation(
            child_id=data.child_id,
            observer_id=data.observer_id,
            observation_type=ObservationType(data.observation_type.value),
            title=data.title,
            content=data.content,
            developmental_areas=data.developmental_areas,
            portfolio_item_id=data.portfolio_item_id,
            observation_date=data.observation_date,
            context=data.context,
            is_shared_with_family=data.is_shared_with_family,
        )
        self.db.add(observation)
        await self.db.commit()
        await self.db.refresh(observation)
        return observation

    async def get_observation_by_id(
        self, observation_id: UUID
    ) -> Optional[Observation]:
        """Retrieve a single observation by ID.

        Args:
            observation_id: Unique identifier of the observation.

        Returns:
            Observation if found, None otherwise.
        """
        query = select(Observation).where(
            cast(Observation.id, String) == str(observation_id)
        )
        result = await self.db.execute(query)
        return result.scalar_one_or_none()

    async def list_observations(
        self,
        child_id: UUID,
        skip: int = 0,
        limit: int = 100,
        observation_type: Optional[str] = None,
        observer_id: Optional[UUID] = None,
        date_from: Optional[date] = None,
        date_to: Optional[date] = None,
        include_archived: bool = False,
        shared_with_family_only: bool = False,
    ) -> tuple[list[Observation], int]:
        """List observations for a child with optional filtering.

        Args:
            child_id: Unique identifier of the child.
            skip: Number of records to skip.
            limit: Maximum number of records to return.
            observation_type: Optional filter by observation type.
            observer_id: Optional filter by observer.
            date_from: Optional filter for observations from this date.
            date_to: Optional filter for observations until this date.
            include_archived: Whether to include archived observations.
            shared_with_family_only: Only return observations shared with family.

        Returns:
            Tuple of (list of observations, total count).
        """
        # Build base query
        query = select(Observation).where(
            cast(Observation.child_id, String) == str(child_id)
        )

        if not include_archived:
            query = query.where(Observation.is_archived == False)

        if observation_type:
            query = query.where(
                Observation.observation_type == ObservationType(observation_type)
            )

        if observer_id:
            query = query.where(
                cast(Observation.observer_id, String) == str(observer_id)
            )

        if date_from:
            query = query.where(Observation.observation_date >= date_from)

        if date_to:
            query = query.where(Observation.observation_date <= date_to)

        if shared_with_family_only:
            query = query.where(Observation.is_shared_with_family == True)

        # Get total count
        count_query = select(func.count()).select_from(query.subquery())
        count_result = await self.db.execute(count_query)
        total = count_result.scalar() or 0

        # Apply pagination and ordering
        query = query.offset(skip).limit(limit).order_by(
            Observation.observation_date.desc()
        )

        result = await self.db.execute(query)
        observations = list(result.scalars().all())

        return observations, total

    async def update_observation(
        self, observation_id: UUID, data: ObservationUpdate
    ) -> Observation:
        """Update an observation.

        Args:
            observation_id: Unique identifier of the observation.
            data: Observation update data.

        Returns:
            The updated Observation record.

        Raises:
            ObservationNotFoundError: If the observation is not found.
        """
        observation = await self.get_observation_by_id(observation_id)
        if not observation:
            raise ObservationNotFoundError(
                f"Observation with ID {observation_id} not found"
            )

        # Build update values from provided data
        update_data = data.model_dump(exclude_unset=True)
        if update_data:
            # Use explicit SQL UPDATE for database compatibility
            stmt = (
                update(Observation)
                .where(cast(Observation.id, String) == str(observation_id))
                .values(**update_data)
            )
            await self.db.execute(stmt)
            await self.db.commit()

        # Re-fetch and return the updated record
        return await self.get_observation_by_id(observation_id)

    async def delete_observation(self, observation_id: UUID) -> bool:
        """Delete an observation (soft delete by archiving).

        Args:
            observation_id: Unique identifier of the observation.

        Returns:
            True if deleted successfully.

        Raises:
            ObservationNotFoundError: If the observation is not found.
        """
        observation = await self.get_observation_by_id(observation_id)
        if not observation:
            raise ObservationNotFoundError(
                f"Observation with ID {observation_id} not found"
            )

        # Use explicit SQL UPDATE for soft delete (database compatibility)
        stmt = (
            update(Observation)
            .where(cast(Observation.id, String) == str(observation_id))
            .values(is_archived=True)
        )
        await self.db.execute(stmt)
        await self.db.commit()
        return True

    # =========================================================================
    # Milestone Operations
    # =========================================================================

    async def create_milestone(self, data: MilestoneCreate) -> Milestone:
        """Create a new milestone.

        Args:
            data: Milestone creation data.

        Returns:
            The created Milestone record.
        """
        milestone = Milestone(
            child_id=data.child_id,
            category=MilestoneCategory(data.category.value),
            name=data.name,
            expected_age_months=data.expected_age_months,
            status=MilestoneStatus(data.status.value),
            first_observed_at=data.first_observed_at,
            achieved_at=data.achieved_at,
            observation_id=data.observation_id,
            notes=data.notes,
            is_flagged=data.is_flagged,
        )
        self.db.add(milestone)
        await self.db.commit()
        await self.db.refresh(milestone)
        return milestone

    async def get_milestone_by_id(
        self, milestone_id: UUID
    ) -> Optional[Milestone]:
        """Retrieve a single milestone by ID.

        Args:
            milestone_id: Unique identifier of the milestone.

        Returns:
            Milestone if found, None otherwise.
        """
        query = select(Milestone).where(
            cast(Milestone.id, String) == str(milestone_id)
        )
        result = await self.db.execute(query)
        return result.scalar_one_or_none()

    async def list_milestones(
        self,
        child_id: UUID,
        skip: int = 0,
        limit: int = 100,
        category: Optional[str] = None,
        status: Optional[str] = None,
        is_flagged: Optional[bool] = None,
    ) -> tuple[list[Milestone], int]:
        """List milestones for a child with optional filtering.

        Args:
            child_id: Unique identifier of the child.
            skip: Number of records to skip.
            limit: Maximum number of records to return.
            category: Optional filter by milestone category.
            status: Optional filter by milestone status.
            is_flagged: Optional filter by flagged status.

        Returns:
            Tuple of (list of milestones, total count).
        """
        # Build base query
        query = select(Milestone).where(
            cast(Milestone.child_id, String) == str(child_id)
        )

        if category:
            query = query.where(
                Milestone.category == MilestoneCategory(category)
            )

        if status:
            query = query.where(
                Milestone.status == MilestoneStatus(status)
            )

        if is_flagged is not None:
            query = query.where(Milestone.is_flagged == is_flagged)

        # Get total count
        count_query = select(func.count()).select_from(query.subquery())
        count_result = await self.db.execute(count_query)
        total = count_result.scalar() or 0

        # Apply pagination and ordering
        query = query.offset(skip).limit(limit).order_by(
            Milestone.category,
            Milestone.expected_age_months,
        )

        result = await self.db.execute(query)
        milestones = list(result.scalars().all())

        return milestones, total

    async def update_milestone(
        self, milestone_id: UUID, data: MilestoneUpdate
    ) -> Milestone:
        """Update a milestone.

        Args:
            milestone_id: Unique identifier of the milestone.
            data: Milestone update data.

        Returns:
            The updated Milestone record.

        Raises:
            MilestoneNotFoundError: If the milestone is not found.
        """
        milestone = await self.get_milestone_by_id(milestone_id)
        if not milestone:
            raise MilestoneNotFoundError(
                f"Milestone with ID {milestone_id} not found"
            )

        # Build update values from provided data
        update_data = data.model_dump(exclude_unset=True)
        if update_data:
            # Convert enum values to their proper types
            if "status" in update_data and update_data["status"]:
                update_data["status"] = MilestoneStatus(update_data["status"].value)

            # Use explicit SQL UPDATE for database compatibility
            stmt = (
                update(Milestone)
                .where(cast(Milestone.id, String) == str(milestone_id))
                .values(**update_data)
            )
            await self.db.execute(stmt)
            await self.db.commit()

        # Re-fetch and return the updated record
        return await self.get_milestone_by_id(milestone_id)

    async def delete_milestone(self, milestone_id: UUID) -> bool:
        """Delete a milestone (hard delete).

        Args:
            milestone_id: Unique identifier of the milestone.

        Returns:
            True if deleted successfully.

        Raises:
            MilestoneNotFoundError: If the milestone is not found.
        """
        milestone = await self.get_milestone_by_id(milestone_id)
        if not milestone:
            raise MilestoneNotFoundError(
                f"Milestone with ID {milestone_id} not found"
            )

        # Use explicit SQL DELETE for database compatibility
        stmt = delete(Milestone).where(
            cast(Milestone.id, String) == str(milestone_id)
        )
        await self.db.execute(stmt)
        await self.db.commit()
        return True

    async def get_milestone_progress(
        self, child_id: UUID
    ) -> dict[str, dict[str, int]]:
        """Get milestone progress summary by category.

        Args:
            child_id: Unique identifier of the child.

        Returns:
            Dictionary with category as key and status counts as value.
        """
        milestones, _ = await self.list_milestones(child_id, limit=1000)

        progress: dict[str, dict[str, int]] = {}
        for milestone in milestones:
            category = milestone.category.value
            if category not in progress:
                progress[category] = {
                    "not_started": 0,
                    "emerging": 0,
                    "developing": 0,
                    "achieved": 0,
                    "total": 0,
                }
            progress[category][milestone.status.value] += 1
            progress[category]["total"] += 1

        return progress

    # =========================================================================
    # Work Sample Operations
    # =========================================================================

    async def create_work_sample(self, data: WorkSampleCreate) -> WorkSample:
        """Create a new work sample.

        Args:
            data: Work sample creation data.

        Returns:
            The created WorkSample record.
        """
        work_sample = WorkSample(
            child_id=data.child_id,
            portfolio_item_id=data.portfolio_item_id,
            sample_type=WorkSampleType(data.sample_type.value),
            title=data.title,
            description=data.description,
            learning_objectives=data.learning_objectives,
            educator_notes=data.educator_notes,
            child_reflection=data.child_reflection,
            sample_date=data.sample_date,
            is_shared_with_family=data.is_shared_with_family,
        )
        self.db.add(work_sample)
        await self.db.commit()
        await self.db.refresh(work_sample)
        return work_sample

    async def get_work_sample_by_id(
        self, work_sample_id: UUID
    ) -> Optional[WorkSample]:
        """Retrieve a single work sample by ID.

        Args:
            work_sample_id: Unique identifier of the work sample.

        Returns:
            WorkSample if found, None otherwise.
        """
        query = select(WorkSample).where(
            cast(WorkSample.id, String) == str(work_sample_id)
        )
        result = await self.db.execute(query)
        return result.scalar_one_or_none()

    async def list_work_samples(
        self,
        child_id: UUID,
        skip: int = 0,
        limit: int = 100,
        sample_type: Optional[str] = None,
        portfolio_item_id: Optional[UUID] = None,
        date_from: Optional[date] = None,
        date_to: Optional[date] = None,
        shared_with_family_only: bool = False,
    ) -> tuple[list[WorkSample], int]:
        """List work samples for a child with optional filtering.

        Args:
            child_id: Unique identifier of the child.
            skip: Number of records to skip.
            limit: Maximum number of records to return.
            sample_type: Optional filter by sample type.
            portfolio_item_id: Optional filter by portfolio item.
            date_from: Optional filter for samples from this date.
            date_to: Optional filter for samples until this date.
            shared_with_family_only: Only return samples shared with family.

        Returns:
            Tuple of (list of work samples, total count).
        """
        # Build base query
        query = select(WorkSample).where(
            cast(WorkSample.child_id, String) == str(child_id)
        )

        if sample_type:
            query = query.where(
                WorkSample.sample_type == WorkSampleType(sample_type)
            )

        if portfolio_item_id:
            query = query.where(
                cast(WorkSample.portfolio_item_id, String) == str(portfolio_item_id)
            )

        if date_from:
            query = query.where(WorkSample.sample_date >= date_from)

        if date_to:
            query = query.where(WorkSample.sample_date <= date_to)

        if shared_with_family_only:
            query = query.where(WorkSample.is_shared_with_family == True)

        # Get total count
        count_query = select(func.count()).select_from(query.subquery())
        count_result = await self.db.execute(count_query)
        total = count_result.scalar() or 0

        # Apply pagination and ordering
        query = query.offset(skip).limit(limit).order_by(
            WorkSample.sample_date.desc()
        )

        result = await self.db.execute(query)
        work_samples = list(result.scalars().all())

        return work_samples, total

    async def update_work_sample(
        self, work_sample_id: UUID, data: WorkSampleUpdate
    ) -> WorkSample:
        """Update a work sample.

        Args:
            work_sample_id: Unique identifier of the work sample.
            data: Work sample update data.

        Returns:
            The updated WorkSample record.

        Raises:
            WorkSampleNotFoundError: If the work sample is not found.
        """
        work_sample = await self.get_work_sample_by_id(work_sample_id)
        if not work_sample:
            raise WorkSampleNotFoundError(
                f"Work sample with ID {work_sample_id} not found"
            )

        # Build update values from provided data
        update_data = data.model_dump(exclude_unset=True)
        if update_data:
            # Use explicit SQL UPDATE for database compatibility
            stmt = (
                update(WorkSample)
                .where(cast(WorkSample.id, String) == str(work_sample_id))
                .values(**update_data)
            )
            await self.db.execute(stmt)
            await self.db.commit()

        # Re-fetch and return the updated record
        return await self.get_work_sample_by_id(work_sample_id)

    async def delete_work_sample(self, work_sample_id: UUID) -> bool:
        """Delete a work sample (hard delete).

        Args:
            work_sample_id: Unique identifier of the work sample.

        Returns:
            True if deleted successfully.

        Raises:
            WorkSampleNotFoundError: If the work sample is not found.
        """
        work_sample = await self.get_work_sample_by_id(work_sample_id)
        if not work_sample:
            raise WorkSampleNotFoundError(
                f"Work sample with ID {work_sample_id} not found"
            )

        # Use explicit SQL DELETE for database compatibility
        stmt = delete(WorkSample).where(
            cast(WorkSample.id, String) == str(work_sample_id)
        )
        await self.db.execute(stmt)
        await self.db.commit()
        return True

    # =========================================================================
    # Portfolio Summary Operations
    # =========================================================================

    async def get_portfolio_summary(
        self, child_id: UUID, recent_count: int = 5
    ) -> PortfolioSummary:
        """Get a summary of a child's portfolio.

        Args:
            child_id: Unique identifier of the child.
            recent_count: Number of recent items to include.

        Returns:
            PortfolioSummary with counts and recent items.
        """
        # Get portfolio items
        items, total_items = await self.list_portfolio_items(
            child_id, limit=recent_count
        )

        # Get total items count
        _, total_items_count = await self.list_portfolio_items(
            child_id, limit=1
        )

        # Get observations
        observations, _ = await self.list_observations(
            child_id, limit=recent_count
        )

        # Get total observations count
        _, total_observations_count = await self.list_observations(
            child_id, limit=1
        )

        # Get milestones
        milestones, total_milestones = await self.list_milestones(
            child_id, limit=1000
        )
        milestones_achieved = sum(
            1 for m in milestones if m.status == MilestoneStatus.ACHIEVED
        )

        # Get work samples count
        _, total_work_samples = await self.list_work_samples(
            child_id, limit=1
        )

        # Convert to response schemas
        recent_items = [
            PortfolioItemResponse.model_validate(item) for item in items
        ]
        recent_observations = [
            ObservationResponse.model_validate(obs) for obs in observations
        ]

        return PortfolioSummary(
            child_id=child_id,
            total_items=total_items_count,
            total_observations=total_observations_count,
            total_milestones=total_milestones,
            milestones_achieved=milestones_achieved,
            total_work_samples=total_work_samples,
            recent_items=recent_items,
            recent_observations=recent_observations,
        )
