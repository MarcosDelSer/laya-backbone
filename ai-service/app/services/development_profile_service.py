"""Development Profile service for LAYA AI Service.

Provides business logic for Quebec-aligned developmental tracking across 6 domains:
1. Affective Development (emotional expression, self-regulation, attachment, self-confidence)
2. Social Development (peer interactions, turn-taking, empathy, group participation)
3. Language & Communication (receptive/expressive language, speech clarity, emergent literacy)
4. Cognitive Development (problem-solving, memory, attention, classification, number concept)
5. Physical - Gross Motor (balance, coordination, body awareness, outdoor skills)
6. Physical - Fine Motor (hand-eye coordination, pencil grip, manipulation, self-care)

Implements CRUD operations for profiles, observations, skill assessments,
and automatic monthly snapshot generation.
"""

from datetime import date, datetime, timezone
from typing import Optional
from uuid import UUID

from sqlalchemy import and_, cast, func, select, String
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy.orm import selectinload

from app.models.development_profile import (
    DevelopmentalDomain,
    DevelopmentProfile,
    MonthlySnapshot,
    Observation,
    SkillAssessment,
    SkillStatus,
)
from app.schemas.development_profile import (
    DevelopmentProfileListResponse,
    DevelopmentProfileRequest,
    DevelopmentProfileResponse,
    DevelopmentProfileSummaryResponse,
    DomainSummary,
    GrowthDataPoint,
    GrowthTrajectoryResponse,
    MonthlySnapshotListResponse,
    MonthlySnapshotRequest,
    MonthlySnapshotResponse,
    MonthlySnapshotUpdateRequest,
    ObservationListResponse,
    ObservationRequest,
    ObservationResponse,
    ObservationUpdateRequest,
    OverallProgress,
    SkillAssessmentListResponse,
    SkillAssessmentRequest,
    SkillAssessmentResponse,
    SkillAssessmentUpdateRequest,
)
from app.schemas.development_profile import (
    DevelopmentalDomain as DevelopmentalDomainSchema,
    SkillStatus as SkillStatusSchema,
)


class DevelopmentProfileService:
    """Service class for development profile management and tracking.

    Encapsulates business logic for managing child developmental profiles,
    skill assessments, observations, monthly snapshots, and growth trajectory
    analysis aligned with Quebec early childhood standards.

    Attributes:
        db: Async database session for database operations.
    """

    def __init__(self, db: AsyncSession) -> None:
        """Initialize DevelopmentProfileService with database session.

        Args:
            db: Async database session for database operations.
        """
        self.db = db

    # =========================================================================
    # Development Profile CRUD Operations
    # =========================================================================

    async def create_profile(
        self,
        request: DevelopmentProfileRequest,
    ) -> DevelopmentProfileResponse:
        """Create a new development profile for a child.

        Args:
            request: Development profile creation request data.

        Returns:
            The created development profile response.

        Raises:
            ValueError: If a profile already exists for this child.
        """
        # Check if profile already exists for this child
        existing = await self.get_profile_by_child_id(request.child_id)
        if existing:
            raise ValueError(f"Profile already exists for child {request.child_id}")

        profile = DevelopmentProfile(
            child_id=request.child_id,
            educator_id=request.educator_id,
            birth_date=request.birth_date,
            notes=request.notes,
            is_active=True,
        )
        self.db.add(profile)
        await self.db.commit()
        await self.db.refresh(profile)

        return self._profile_to_response(profile)

    async def get_profile_by_id(
        self,
        profile_id: UUID,
        include_relations: bool = True,
    ) -> Optional[DevelopmentProfileResponse]:
        """Retrieve a development profile by its ID.

        Args:
            profile_id: Unique identifier of the profile.
            include_relations: Whether to include related data (assessments, observations).

        Returns:
            Development profile if found, None otherwise.
        """
        query = select(DevelopmentProfile).where(
            DevelopmentProfile.id == profile_id
        )

        if include_relations:
            query = query.options(
                selectinload(DevelopmentProfile.skill_assessments),
                selectinload(DevelopmentProfile.observations),
                selectinload(DevelopmentProfile.monthly_snapshots),
            )

        result = await self.db.execute(query)
        profile = result.scalar_one_or_none()

        if profile is None:
            return None

        return self._profile_to_response(profile)

    async def get_profile_by_child_id(
        self,
        child_id: UUID,
        include_relations: bool = True,
    ) -> Optional[DevelopmentProfileResponse]:
        """Retrieve a development profile by child ID.

        Args:
            child_id: Unique identifier of the child.
            include_relations: Whether to include related data.

        Returns:
            Development profile if found, None otherwise.
        """
        query = select(DevelopmentProfile).where(
            DevelopmentProfile.child_id == child_id
        )

        if include_relations:
            query = query.options(
                selectinload(DevelopmentProfile.skill_assessments),
                selectinload(DevelopmentProfile.observations),
                selectinload(DevelopmentProfile.monthly_snapshots),
            )

        result = await self.db.execute(query)
        profile = result.scalar_one_or_none()

        if profile is None:
            return None

        return self._profile_to_response(profile)

    async def update_profile(
        self,
        profile_id: UUID,
        request: DevelopmentProfileRequest,
    ) -> Optional[DevelopmentProfileResponse]:
        """Update an existing development profile.

        Args:
            profile_id: Unique identifier of the profile to update.
            request: Updated profile data.

        Returns:
            Updated profile response if found, None otherwise.
        """
        query = select(DevelopmentProfile).where(
            DevelopmentProfile.id == profile_id
        )
        result = await self.db.execute(query)
        profile = result.scalar_one_or_none()

        if profile is None:
            return None

        # Update fields
        profile.educator_id = request.educator_id
        profile.birth_date = request.birth_date
        profile.notes = request.notes

        await self.db.commit()
        await self.db.refresh(profile)

        return self._profile_to_response(profile)

    async def delete_profile(self, profile_id: UUID) -> bool:
        """Delete a development profile.

        Args:
            profile_id: Unique identifier of the profile to delete.

        Returns:
            True if profile was deleted, False if not found.
        """
        query = select(DevelopmentProfile).where(
            DevelopmentProfile.id == profile_id
        )
        result = await self.db.execute(query)
        profile = result.scalar_one_or_none()

        if profile is None:
            return False

        await self.db.delete(profile)
        await self.db.commit()
        return True

    async def list_profiles(
        self,
        skip: int = 0,
        limit: int = 100,
        is_active: Optional[bool] = True,
        educator_id: Optional[UUID] = None,
    ) -> DevelopmentProfileListResponse:
        """List development profiles with optional filtering and pagination.

        Args:
            skip: Number of records to skip.
            limit: Maximum number of records to return.
            is_active: Optional filter by active status.
            educator_id: Optional filter by educator.

        Returns:
            Paginated list of development profile summaries.
        """
        # Build base query
        query = select(DevelopmentProfile)

        if is_active is not None:
            query = query.where(DevelopmentProfile.is_active == is_active)

        if educator_id is not None:
            query = query.where(
                DevelopmentProfile.educator_id == educator_id
            )

        # Get total count
        count_query = select(func.count()).select_from(query.subquery())
        count_result = await self.db.execute(count_query)
        total = count_result.scalar() or 0

        # Apply pagination and load relationships for counts
        query = (
            query.options(
                selectinload(DevelopmentProfile.skill_assessments),
                selectinload(DevelopmentProfile.observations),
                selectinload(DevelopmentProfile.monthly_snapshots),
            )
            .offset(skip)
            .limit(limit)
            .order_by(DevelopmentProfile.created_at.desc())
        )

        result = await self.db.execute(query)
        profiles = list(result.scalars().all())

        items = [self._profile_to_summary_response(p) for p in profiles]

        return DevelopmentProfileListResponse(
            items=items,
            total=total,
            skip=skip,
            limit=limit,
        )

    # =========================================================================
    # Skill Assessment CRUD Operations
    # =========================================================================

    async def create_skill_assessment(
        self,
        request: SkillAssessmentRequest,
    ) -> SkillAssessmentResponse:
        """Create a new skill assessment.

        Args:
            request: Skill assessment creation request data.

        Returns:
            The created skill assessment response.

        Raises:
            ValueError: If the profile does not exist.
        """
        # Verify profile exists
        profile = await self._get_profile_model(request.profile_id)
        if profile is None:
            raise ValueError(f"Profile {request.profile_id} not found")

        # Convert schema enum to model enum
        domain = DevelopmentalDomain(request.domain.value)
        status = SkillStatus(request.status.value)

        assessment = SkillAssessment(
            profile_id=request.profile_id,
            domain=domain,
            skill_name=request.skill_name,
            skill_name_fr=request.skill_name_fr,
            status=status,
            assessed_by_id=request.assessed_by_id,
            evidence=request.evidence,
        )
        self.db.add(assessment)
        await self.db.commit()
        await self.db.refresh(assessment)

        return self._skill_assessment_to_response(assessment)

    async def get_skill_assessment_by_id(
        self,
        assessment_id: UUID,
    ) -> Optional[SkillAssessmentResponse]:
        """Retrieve a skill assessment by ID.

        Args:
            assessment_id: Unique identifier of the assessment.

        Returns:
            Skill assessment if found, None otherwise.
        """
        query = select(SkillAssessment).where(
            SkillAssessment.id == assessment_id
        )
        result = await self.db.execute(query)
        assessment = result.scalar_one_or_none()

        if assessment is None:
            return None

        return self._skill_assessment_to_response(assessment)

    async def update_skill_assessment(
        self,
        assessment_id: UUID,
        request: SkillAssessmentUpdateRequest,
    ) -> Optional[SkillAssessmentResponse]:
        """Update an existing skill assessment.

        Args:
            assessment_id: Unique identifier of the assessment.
            request: Update request with partial data.

        Returns:
            Updated assessment if found, None otherwise.
        """
        query = select(SkillAssessment).where(
            SkillAssessment.id == assessment_id
        )
        result = await self.db.execute(query)
        assessment = result.scalar_one_or_none()

        if assessment is None:
            return None

        # Update fields if provided
        if request.status is not None:
            assessment.status = SkillStatus(request.status.value)
        if request.evidence is not None:
            assessment.evidence = request.evidence
        if request.assessed_by_id is not None:
            assessment.assessed_by_id = request.assessed_by_id

        # Update assessed_at timestamp
        assessment.assessed_at = datetime.now(timezone.utc)

        await self.db.commit()
        await self.db.refresh(assessment)

        return self._skill_assessment_to_response(assessment)

    async def delete_skill_assessment(self, assessment_id: UUID) -> bool:
        """Delete a skill assessment.

        Args:
            assessment_id: Unique identifier of the assessment.

        Returns:
            True if deleted, False if not found.
        """
        query = select(SkillAssessment).where(
            SkillAssessment.id == assessment_id
        )
        result = await self.db.execute(query)
        assessment = result.scalar_one_or_none()

        if assessment is None:
            return False

        await self.db.delete(assessment)
        await self.db.commit()
        return True

    async def list_skill_assessments(
        self,
        profile_id: UUID,
        domain: Optional[DevelopmentalDomainSchema] = None,
        status: Optional[SkillStatusSchema] = None,
        skip: int = 0,
        limit: int = 100,
    ) -> SkillAssessmentListResponse:
        """List skill assessments for a profile with optional filtering.

        Args:
            profile_id: Unique identifier of the profile.
            domain: Optional filter by developmental domain.
            status: Optional filter by skill status.
            skip: Number of records to skip.
            limit: Maximum number of records to return.

        Returns:
            Paginated list of skill assessments.
        """
        # Build base query
        query = select(SkillAssessment).where(
            cast(SkillAssessment.profile_id, String) == str(profile_id)
        )

        if domain is not None:
            query = query.where(
                SkillAssessment.domain == DevelopmentalDomain(domain.value)
            )

        if status is not None:
            query = query.where(SkillAssessment.status == SkillStatus(status.value))

        # Get total count
        count_query = select(func.count()).select_from(query.subquery())
        count_result = await self.db.execute(count_query)
        total = count_result.scalar() or 0

        # Apply pagination
        query = (
            query.offset(skip)
            .limit(limit)
            .order_by(SkillAssessment.domain, SkillAssessment.assessed_at.desc())
        )

        result = await self.db.execute(query)
        assessments = list(result.scalars().all())

        items = [self._skill_assessment_to_response(a) for a in assessments]

        return SkillAssessmentListResponse(
            items=items,
            total=total,
            skip=skip,
            limit=limit,
        )

    # =========================================================================
    # Observation CRUD Operations
    # =========================================================================

    async def create_observation(
        self,
        request: ObservationRequest,
    ) -> ObservationResponse:
        """Create a new observation.

        Args:
            request: Observation creation request data.

        Returns:
            The created observation response.

        Raises:
            ValueError: If the profile does not exist.
        """
        # Verify profile exists
        profile = await self._get_profile_model(request.profile_id)
        if profile is None:
            raise ValueError(f"Profile {request.profile_id} not found")

        # Convert schema enum to model enum
        domain = DevelopmentalDomain(request.domain.value)

        observation = Observation(
            profile_id=request.profile_id,
            domain=domain,
            observed_at=request.observed_at or datetime.now(timezone.utc),
            observer_id=request.observer_id,
            observer_type=request.observer_type.value,
            behavior_description=request.behavior_description,
            context=request.context,
            is_milestone=request.is_milestone,
            is_concern=request.is_concern,
            attachments=request.attachments,
        )
        self.db.add(observation)
        await self.db.commit()
        await self.db.refresh(observation)

        return self._observation_to_response(observation)

    async def get_observation_by_id(
        self,
        observation_id: UUID,
    ) -> Optional[ObservationResponse]:
        """Retrieve an observation by ID.

        Args:
            observation_id: Unique identifier of the observation.

        Returns:
            Observation if found, None otherwise.
        """
        query = select(Observation).where(
            Observation.id == observation_id
        )
        result = await self.db.execute(query)
        observation = result.scalar_one_or_none()

        if observation is None:
            return None

        return self._observation_to_response(observation)

    async def update_observation(
        self,
        observation_id: UUID,
        request: ObservationUpdateRequest,
    ) -> Optional[ObservationResponse]:
        """Update an existing observation.

        Args:
            observation_id: Unique identifier of the observation.
            request: Update request with partial data.

        Returns:
            Updated observation if found, None otherwise.
        """
        query = select(Observation).where(
            Observation.id == observation_id
        )
        result = await self.db.execute(query)
        observation = result.scalar_one_or_none()

        if observation is None:
            return None

        # Update fields if provided
        if request.behavior_description is not None:
            observation.behavior_description = request.behavior_description
        if request.context is not None:
            observation.context = request.context
        if request.is_milestone is not None:
            observation.is_milestone = request.is_milestone
        if request.is_concern is not None:
            observation.is_concern = request.is_concern
        if request.attachments is not None:
            observation.attachments = request.attachments

        await self.db.commit()
        await self.db.refresh(observation)

        return self._observation_to_response(observation)

    async def delete_observation(self, observation_id: UUID) -> bool:
        """Delete an observation.

        Args:
            observation_id: Unique identifier of the observation.

        Returns:
            True if deleted, False if not found.
        """
        query = select(Observation).where(
            Observation.id == observation_id
        )
        result = await self.db.execute(query)
        observation = result.scalar_one_or_none()

        if observation is None:
            return False

        await self.db.delete(observation)
        await self.db.commit()
        return True

    async def list_observations(
        self,
        profile_id: UUID,
        domain: Optional[DevelopmentalDomainSchema] = None,
        is_milestone: Optional[bool] = None,
        is_concern: Optional[bool] = None,
        observer_type: Optional[str] = None,
        skip: int = 0,
        limit: int = 100,
    ) -> ObservationListResponse:
        """List observations for a profile with optional filtering.

        Args:
            profile_id: Unique identifier of the profile.
            domain: Optional filter by developmental domain.
            is_milestone: Optional filter for milestones only.
            is_concern: Optional filter for concerns only.
            observer_type: Optional filter by observer type.
            skip: Number of records to skip.
            limit: Maximum number of records to return.

        Returns:
            Paginated list of observations.
        """
        # Build base query
        query = select(Observation).where(
            cast(Observation.profile_id, String) == str(profile_id)
        )

        if domain is not None:
            query = query.where(
                Observation.domain == DevelopmentalDomain(domain.value)
            )

        if is_milestone is not None:
            query = query.where(Observation.is_milestone == is_milestone)

        if is_concern is not None:
            query = query.where(Observation.is_concern == is_concern)

        if observer_type is not None:
            query = query.where(Observation.observer_type == observer_type)

        # Get total count
        count_query = select(func.count()).select_from(query.subquery())
        count_result = await self.db.execute(count_query)
        total = count_result.scalar() or 0

        # Apply pagination
        query = (
            query.offset(skip).limit(limit).order_by(Observation.observed_at.desc())
        )

        result = await self.db.execute(query)
        observations = list(result.scalars().all())

        items = [self._observation_to_response(o) for o in observations]

        return ObservationListResponse(
            items=items,
            total=total,
            skip=skip,
            limit=limit,
        )

    # =========================================================================
    # Monthly Snapshot Operations
    # =========================================================================

    async def create_monthly_snapshot(
        self,
        request: MonthlySnapshotRequest,
    ) -> MonthlySnapshotResponse:
        """Create a new monthly snapshot.

        Args:
            request: Monthly snapshot creation request data.

        Returns:
            The created monthly snapshot response.

        Raises:
            ValueError: If the profile does not exist or snapshot already exists for this month.
        """
        # Verify profile exists
        profile = await self._get_profile_model(request.profile_id)
        if profile is None:
            raise ValueError(f"Profile {request.profile_id} not found")

        # Check if snapshot already exists for this month
        existing = await self._get_snapshot_for_month(
            request.profile_id, request.snapshot_month
        )
        if existing:
            raise ValueError(
                f"Snapshot already exists for {request.snapshot_month.strftime('%Y-%m')}"
            )

        # Convert strengths and growth_areas to JSON-compatible format
        strengths_json = {"items": request.strengths} if request.strengths else None
        growth_areas_json = (
            {"items": request.growth_areas} if request.growth_areas else None
        )

        snapshot = MonthlySnapshot(
            profile_id=request.profile_id,
            snapshot_month=request.snapshot_month,
            age_months=request.age_months,
            domain_summaries=request.domain_summaries,
            overall_progress=request.overall_progress.value,
            strengths=strengths_json,
            growth_areas=growth_areas_json,
            recommendations=request.recommendations,
            generated_by_id=request.generated_by_id,
            is_parent_shared=False,
        )
        self.db.add(snapshot)
        await self.db.commit()
        await self.db.refresh(snapshot)

        return self._monthly_snapshot_to_response(snapshot)

    async def get_monthly_snapshot_by_id(
        self,
        snapshot_id: UUID,
    ) -> Optional[MonthlySnapshotResponse]:
        """Retrieve a monthly snapshot by ID.

        Args:
            snapshot_id: Unique identifier of the snapshot.

        Returns:
            Monthly snapshot if found, None otherwise.
        """
        query = select(MonthlySnapshot).where(
            cast(MonthlySnapshot.id, String) == str(snapshot_id)
        )
        result = await self.db.execute(query)
        snapshot = result.scalar_one_or_none()

        if snapshot is None:
            return None

        return self._monthly_snapshot_to_response(snapshot)

    async def update_monthly_snapshot(
        self,
        snapshot_id: UUID,
        request: MonthlySnapshotUpdateRequest,
    ) -> Optional[MonthlySnapshotResponse]:
        """Update an existing monthly snapshot.

        Args:
            snapshot_id: Unique identifier of the snapshot.
            request: Update request with partial data.

        Returns:
            Updated snapshot if found, None otherwise.
        """
        query = select(MonthlySnapshot).where(
            cast(MonthlySnapshot.id, String) == str(snapshot_id)
        )
        result = await self.db.execute(query)
        snapshot = result.scalar_one_or_none()

        if snapshot is None:
            return None

        # Update fields if provided
        if request.overall_progress is not None:
            snapshot.overall_progress = request.overall_progress.value
        if request.recommendations is not None:
            snapshot.recommendations = request.recommendations
        if request.strengths is not None:
            snapshot.strengths = {"items": request.strengths}
        if request.growth_areas is not None:
            snapshot.growth_areas = {"items": request.growth_areas}
        if request.is_parent_shared is not None:
            snapshot.is_parent_shared = request.is_parent_shared

        await self.db.commit()
        await self.db.refresh(snapshot)

        return self._monthly_snapshot_to_response(snapshot)

    async def delete_monthly_snapshot(self, snapshot_id: UUID) -> bool:
        """Delete a monthly snapshot.

        Args:
            snapshot_id: Unique identifier of the snapshot.

        Returns:
            True if deleted, False if not found.
        """
        query = select(MonthlySnapshot).where(
            cast(MonthlySnapshot.id, String) == str(snapshot_id)
        )
        result = await self.db.execute(query)
        snapshot = result.scalar_one_or_none()

        if snapshot is None:
            return False

        await self.db.delete(snapshot)
        await self.db.commit()
        return True

    async def list_monthly_snapshots(
        self,
        profile_id: UUID,
        start_month: Optional[date] = None,
        end_month: Optional[date] = None,
        skip: int = 0,
        limit: int = 100,
    ) -> MonthlySnapshotListResponse:
        """List monthly snapshots for a profile with optional date filtering.

        Args:
            profile_id: Unique identifier of the profile.
            start_month: Optional start date filter.
            end_month: Optional end date filter.
            skip: Number of records to skip.
            limit: Maximum number of records to return.

        Returns:
            Paginated list of monthly snapshots.
        """
        # Build base query
        query = select(MonthlySnapshot).where(
            cast(MonthlySnapshot.profile_id, String) == str(profile_id)
        )

        if start_month is not None:
            query = query.where(MonthlySnapshot.snapshot_month >= start_month)

        if end_month is not None:
            query = query.where(MonthlySnapshot.snapshot_month <= end_month)

        # Get total count
        count_query = select(func.count()).select_from(query.subquery())
        count_result = await self.db.execute(count_query)
        total = count_result.scalar() or 0

        # Apply pagination
        query = (
            query.offset(skip)
            .limit(limit)
            .order_by(MonthlySnapshot.snapshot_month.desc())
        )

        result = await self.db.execute(query)
        snapshots = list(result.scalars().all())

        items = [self._monthly_snapshot_to_response(s) for s in snapshots]

        return MonthlySnapshotListResponse(
            items=items,
            total=total,
            skip=skip,
            limit=limit,
        )

    async def generate_monthly_snapshot(
        self,
        profile_id: UUID,
        snapshot_month: date,
        generated_by_id: Optional[UUID] = None,
    ) -> MonthlySnapshotResponse:
        """Automatically generate a monthly snapshot from current assessments and observations.

        Analyzes all skill assessments and observations for the profile to create
        a comprehensive monthly developmental summary across all 6 Quebec domains.

        Args:
            profile_id: Unique identifier of the profile.
            snapshot_month: The month to generate the snapshot for.
            generated_by_id: UUID of the user generating the snapshot.

        Returns:
            The generated monthly snapshot response.

        Raises:
            ValueError: If profile not found or snapshot already exists for this month.
        """
        # Verify profile exists and get it with relations
        profile_response = await self.get_profile_by_id(profile_id, include_relations=True)
        if profile_response is None:
            raise ValueError(f"Profile {profile_id} not found")

        # Check if snapshot already exists for this month
        existing = await self._get_snapshot_for_month(profile_id, snapshot_month)
        if existing:
            raise ValueError(
                f"Snapshot already exists for {snapshot_month.strftime('%Y-%m')}"
            )

        # Get profile model for birth_date calculation
        profile = await self._get_profile_model(profile_id)
        if profile is None:
            raise ValueError(f"Profile {profile_id} not found")

        # Calculate age in months if birth_date is available
        age_months = None
        if profile.birth_date:
            today = snapshot_month
            age_months = (
                (today.year - profile.birth_date.year) * 12
                + (today.month - profile.birth_date.month)
            )

        # Aggregate skill assessments by domain
        domain_summaries = {}
        strengths = []
        growth_areas = []

        for domain in DevelopmentalDomain:
            domain_assessments = [
                a
                for a in profile_response.skill_assessments
                if a.domain.value == domain.value
            ]

            # Count by status
            skills_can = sum(1 for a in domain_assessments if a.status.value == "can")
            skills_learning = sum(
                1 for a in domain_assessments if a.status.value == "learning"
            )
            skills_not_yet = sum(
                1 for a in domain_assessments if a.status.value == "not_yet"
            )
            total_assessed = skills_can + skills_learning + skills_not_yet

            # Calculate progress percentage
            progress_percentage = 0.0
            if total_assessed > 0:
                # Can = 100%, Learning = 50%, Not yet = 0%
                progress_percentage = (
                    (skills_can * 100 + skills_learning * 50) / total_assessed
                )

            # Get key observations for this domain
            domain_observations = [
                o.behavior_description[:100] + "..."
                if len(o.behavior_description) > 100
                else o.behavior_description
                for o in profile_response.observations
                if o.domain.value == domain.value
            ][:3]  # Limit to 3 key observations

            domain_summaries[domain.value] = {
                "domain": domain.value,
                "skills_can": skills_can,
                "skills_learning": skills_learning,
                "skills_not_yet": skills_not_yet,
                "progress_percentage": round(progress_percentage, 1),
                "key_observations": domain_observations,
            }

            # Identify strengths and growth areas
            if progress_percentage >= 70:
                strengths.append(f"{domain.value}: Strong progress")
            elif progress_percentage < 40 and total_assessed > 0:
                growth_areas.append(f"{domain.value}: Needs additional support")

        # Determine overall progress
        all_progress = [
            d["progress_percentage"]
            for d in domain_summaries.values()
            if d["progress_percentage"] > 0
        ]
        avg_progress = sum(all_progress) / len(all_progress) if all_progress else 0

        if avg_progress >= 70:
            overall_progress = OverallProgress.EXCELLING.value
        elif avg_progress >= 40:
            overall_progress = OverallProgress.ON_TRACK.value
        else:
            overall_progress = OverallProgress.NEEDS_SUPPORT.value

        # Create the snapshot
        snapshot = MonthlySnapshot(
            profile_id=profile_id,
            snapshot_month=snapshot_month,
            age_months=age_months,
            domain_summaries=domain_summaries,
            overall_progress=overall_progress,
            strengths={"items": strengths} if strengths else None,
            growth_areas={"items": growth_areas} if growth_areas else None,
            recommendations=None,  # Can be added manually later
            generated_by_id=generated_by_id,
            is_parent_shared=False,
        )
        self.db.add(snapshot)
        await self.db.commit()
        await self.db.refresh(snapshot)

        return self._monthly_snapshot_to_response(snapshot)

    # =========================================================================
    # Growth Trajectory Analysis
    # =========================================================================

    async def get_growth_trajectory(
        self,
        profile_id: UUID,
        start_month: Optional[date] = None,
        end_month: Optional[date] = None,
        domains: Optional[list[DevelopmentalDomainSchema]] = None,
    ) -> GrowthTrajectoryResponse:
        """Get growth trajectory data for visualization and analysis.

        Retrieves monthly snapshot data points for tracking developmental
        progress over time, with optional filtering by date range and domains.

        Args:
            profile_id: Unique identifier of the profile.
            start_month: Optional start date for trajectory data.
            end_month: Optional end date for trajectory data.
            domains: Optional list of domains to include.

        Returns:
            Growth trajectory response with data points and analysis.

        Raises:
            ValueError: If profile not found.
        """
        # Get profile
        profile_response = await self.get_profile_by_id(profile_id, include_relations=False)
        if profile_response is None:
            raise ValueError(f"Profile {profile_id} not found")

        # Query snapshots
        query = select(MonthlySnapshot).where(
            cast(MonthlySnapshot.profile_id, String) == str(profile_id)
        )

        if start_month is not None:
            query = query.where(MonthlySnapshot.snapshot_month >= start_month)

        if end_month is not None:
            query = query.where(MonthlySnapshot.snapshot_month <= end_month)

        query = query.order_by(MonthlySnapshot.snapshot_month.asc())

        result = await self.db.execute(query)
        snapshots = list(result.scalars().all())

        # Convert to data points
        data_points = []
        for snapshot in snapshots:
            domain_scores = {}
            overall_score = 0.0

            if snapshot.domain_summaries:
                for domain_key, summary in snapshot.domain_summaries.items():
                    # Filter by domains if specified
                    if domains is not None:
                        domain_values = [d.value for d in domains]
                        if domain_key not in domain_values:
                            continue

                    progress = summary.get("progress_percentage", 0.0)
                    domain_scores[domain_key] = progress

                # Calculate overall score
                if domain_scores:
                    overall_score = sum(domain_scores.values()) / len(domain_scores)

            data_points.append(
                GrowthDataPoint(
                    month=snapshot.snapshot_month,
                    age_months=snapshot.age_months,
                    domain_scores=domain_scores,
                    overall_score=round(overall_score, 1),
                )
            )

        # Generate alerts based on growth trajectory
        alerts = []
        if len(data_points) >= 2:
            recent = data_points[-1]
            previous = data_points[-2]

            for domain_key, recent_score in recent.domain_scores.items():
                prev_score = previous.domain_scores.get(domain_key, 0)
                # Alert if progress declined significantly
                if prev_score - recent_score > 10:
                    alerts.append(
                        f"{domain_key}: Progress declined from {prev_score:.0f}% to {recent_score:.0f}%"
                    )
                # Alert if consistently low
                elif recent_score < 30:
                    alerts.append(
                        f"{domain_key}: Progress remains below expected level ({recent_score:.0f}%)"
                    )

        return GrowthTrajectoryResponse(
            profile_id=profile_id,
            child_id=profile_response.child_id,
            data_points=data_points,
            trend_analysis=None,  # Can be enhanced with AI analysis
            alerts=alerts,
        )

    # =========================================================================
    # Private Helper Methods
    # =========================================================================

    async def _get_profile_model(
        self, profile_id: UUID
    ) -> Optional[DevelopmentProfile]:
        """Get raw profile model without conversion to response.

        Args:
            profile_id: Unique identifier of the profile.

        Returns:
            DevelopmentProfile model if found, None otherwise.
        """
        query = select(DevelopmentProfile).where(
            DevelopmentProfile.id == profile_id
        )
        result = await self.db.execute(query)
        return result.scalar_one_or_none()

    async def _get_snapshot_for_month(
        self, profile_id: UUID, snapshot_month: date
    ) -> Optional[MonthlySnapshot]:
        """Check if a snapshot exists for a given month.

        Args:
            profile_id: Unique identifier of the profile.
            snapshot_month: The month to check.

        Returns:
            MonthlySnapshot if found, None otherwise.
        """
        query = select(MonthlySnapshot).where(
            and_(
                cast(MonthlySnapshot.profile_id, String) == str(profile_id),
                MonthlySnapshot.snapshot_month == snapshot_month,
            )
        )
        result = await self.db.execute(query)
        return result.scalar_one_or_none()

    def _profile_to_response(
        self, profile: DevelopmentProfile
    ) -> DevelopmentProfileResponse:
        """Convert DevelopmentProfile model to response schema.

        Args:
            profile: The DevelopmentProfile model instance.

        Returns:
            DevelopmentProfileResponse schema instance.
        """
        # Convert related items
        skill_assessments = [
            self._skill_assessment_to_response(a)
            for a in (profile.skill_assessments or [])
        ]
        observations = [
            self._observation_to_response(o) for o in (profile.observations or [])
        ]
        monthly_snapshots = [
            self._monthly_snapshot_to_response(s)
            for s in (profile.monthly_snapshots or [])
        ]

        return DevelopmentProfileResponse(
            id=profile.id,
            child_id=profile.child_id,
            educator_id=profile.educator_id,
            birth_date=profile.birth_date,
            notes=profile.notes,
            is_active=profile.is_active,
            skill_assessments=skill_assessments,
            observations=observations,
            monthly_snapshots=monthly_snapshots,
            created_at=profile.created_at,
            updated_at=profile.updated_at,
        )

    def _profile_to_summary_response(
        self, profile: DevelopmentProfile
    ) -> DevelopmentProfileSummaryResponse:
        """Convert DevelopmentProfile model to summary response schema.

        Args:
            profile: The DevelopmentProfile model instance.

        Returns:
            DevelopmentProfileSummaryResponse schema instance.
        """
        return DevelopmentProfileSummaryResponse(
            id=profile.id,
            child_id=profile.child_id,
            educator_id=profile.educator_id,
            birth_date=profile.birth_date,
            notes=profile.notes,
            is_active=profile.is_active,
            assessment_count=len(profile.skill_assessments or []),
            observation_count=len(profile.observations or []),
            snapshot_count=len(profile.monthly_snapshots or []),
            created_at=profile.created_at,
            updated_at=profile.updated_at,
        )

    def _skill_assessment_to_response(
        self, assessment: SkillAssessment
    ) -> SkillAssessmentResponse:
        """Convert SkillAssessment model to response schema.

        Args:
            assessment: The SkillAssessment model instance.

        Returns:
            SkillAssessmentResponse schema instance.
        """
        return SkillAssessmentResponse(
            id=assessment.id,
            profile_id=assessment.profile_id,
            domain=DevelopmentalDomainSchema(assessment.domain.value),
            skill_name=assessment.skill_name,
            skill_name_fr=assessment.skill_name_fr,
            status=SkillStatusSchema(assessment.status.value),
            assessed_at=assessment.assessed_at,
            assessed_by_id=assessment.assessed_by_id,
            evidence=assessment.evidence,
            created_at=assessment.created_at,
            updated_at=assessment.updated_at,
        )

    def _observation_to_response(
        self, observation: Observation
    ) -> ObservationResponse:
        """Convert Observation model to response schema.

        Args:
            observation: The Observation model instance.

        Returns:
            ObservationResponse schema instance.
        """
        return ObservationResponse(
            id=observation.id,
            profile_id=observation.profile_id,
            domain=DevelopmentalDomainSchema(observation.domain.value),
            observed_at=observation.observed_at,
            observer_id=observation.observer_id,
            observer_type=observation.observer_type,
            behavior_description=observation.behavior_description,
            context=observation.context,
            is_milestone=observation.is_milestone,
            is_concern=observation.is_concern,
            attachments=observation.attachments,
            created_at=observation.created_at,
            updated_at=observation.updated_at,
        )

    def _monthly_snapshot_to_response(
        self, snapshot: MonthlySnapshot
    ) -> MonthlySnapshotResponse:
        """Convert MonthlySnapshot model to response schema.

        Args:
            snapshot: The MonthlySnapshot model instance.

        Returns:
            MonthlySnapshotResponse schema instance.
        """
        # Extract strengths and growth_areas from JSON format
        strengths = None
        if snapshot.strengths and isinstance(snapshot.strengths, dict):
            strengths = snapshot.strengths.get("items")

        growth_areas = None
        if snapshot.growth_areas and isinstance(snapshot.growth_areas, dict):
            growth_areas = snapshot.growth_areas.get("items")

        return MonthlySnapshotResponse(
            id=snapshot.id,
            profile_id=snapshot.profile_id,
            snapshot_month=snapshot.snapshot_month,
            age_months=snapshot.age_months,
            domain_summaries=snapshot.domain_summaries,
            overall_progress=OverallProgress(snapshot.overall_progress),
            strengths=strengths,
            growth_areas=growth_areas,
            recommendations=snapshot.recommendations,
            generated_by_id=snapshot.generated_by_id,
            is_parent_shared=snapshot.is_parent_shared,
            created_at=snapshot.created_at,
            updated_at=snapshot.updated_at,
        )
