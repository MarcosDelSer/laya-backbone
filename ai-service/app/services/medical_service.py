"""Medical service for LAYA AI Service.

Provides business logic for medical tracking including allergy management,
medication tracking, accommodation plans, and allergen detection in meals.
Implements comprehensive medical safety checks for childcare settings.
"""

from datetime import date, datetime
from typing import Optional
from uuid import UUID

from sqlalchemy import and_, cast, func, or_, select, String
from sqlalchemy.ext.asyncio import AsyncSession

from app.models.medical import (
    AccommodationPlan,
    AccommodationPlanStatus,
    AccommodationPlanType,
    AdministeredBy,
    AlertLevel,
    AlertType,
    Allergy,
    AllergenType,
    AllergySeverity,
    MedicalAlert,
    Medication,
    MedicationType,
)
from app.schemas.medical import (
    AccommodationPlanResponse,
    AllergenDetectionResponse,
    AllergyResponse,
    ChildMedicalSummary,
    DetectedAllergen,
    MedicalAlertResponse,
    MedicationResponse,
)


class MedicalServiceError(Exception):
    """Base exception for medical service errors."""

    pass


class AllergyNotFoundError(MedicalServiceError):
    """Raised when an allergy record is not found."""

    pass


class MedicationNotFoundError(MedicalServiceError):
    """Raised when a medication record is not found."""

    pass


class ChildNotFoundError(MedicalServiceError):
    """Raised when a child is not found or has no medical records."""

    pass


# Common allergen keywords for fuzzy matching
ALLERGEN_KEYWORDS: dict[str, list[str]] = {
    # Peanut-related
    "peanut": ["peanut", "peanuts", "groundnut", "arachis", "satay", "pad thai"],
    # Tree nut-related
    "tree nut": [
        "almond",
        "almonds",
        "cashew",
        "cashews",
        "walnut",
        "walnuts",
        "pecan",
        "pecans",
        "pistachio",
        "pistachios",
        "hazelnut",
        "hazelnuts",
        "macadamia",
        "brazil nut",
        "pine nut",
        "chestnut",
        "praline",
        "marzipan",
        "nougat",
    ],
    # Dairy-related
    "dairy": [
        "milk",
        "cheese",
        "butter",
        "cream",
        "yogurt",
        "yoghurt",
        "lactose",
        "casein",
        "whey",
        "ghee",
        "custard",
        "ice cream",
        "pudding",
        "cottage cheese",
        "sour cream",
        "cream cheese",
    ],
    # Egg-related
    "egg": [
        "egg",
        "eggs",
        "mayonnaise",
        "mayo",
        "meringue",
        "albumin",
        "ovalbumin",
        "hollandaise",
        "aioli",
    ],
    # Wheat/gluten-related
    "wheat": [
        "wheat",
        "bread",
        "flour",
        "pasta",
        "noodles",
        "couscous",
        "bulgur",
        "semolina",
        "durum",
        "farina",
        "panko",
        "breadcrumb",
        "crouton",
    ],
    "gluten": [
        "gluten",
        "wheat",
        "barley",
        "rye",
        "triticale",
        "spelt",
        "kamut",
        "seitan",
    ],
    # Soy-related
    "soy": [
        "soy",
        "soya",
        "tofu",
        "tempeh",
        "edamame",
        "miso",
        "soy sauce",
        "tamari",
        "soybean",
    ],
    # Fish-related
    "fish": [
        "fish",
        "salmon",
        "tuna",
        "cod",
        "tilapia",
        "halibut",
        "trout",
        "sardine",
        "anchovy",
        "anchovies",
        "bass",
        "catfish",
        "haddock",
        "mackerel",
        "perch",
        "snapper",
        "swordfish",
        "fish sauce",
        "worcestershire",
    ],
    # Shellfish-related
    "shellfish": [
        "shrimp",
        "crab",
        "lobster",
        "crawfish",
        "crayfish",
        "prawn",
        "prawns",
        "scallop",
        "scallops",
        "clam",
        "clams",
        "mussel",
        "mussels",
        "oyster",
        "oysters",
        "squid",
        "calamari",
        "octopus",
    ],
    # Sesame-related
    "sesame": [
        "sesame",
        "tahini",
        "hummus",
        "halvah",
        "halva",
        "sesame oil",
        "sesame seeds",
    ],
    # Mustard-related
    "mustard": ["mustard", "dijon", "honey mustard"],
    # Celery-related
    "celery": ["celery", "celeriac", "celery salt", "celery seed"],
    # Lupin-related
    "lupin": ["lupin", "lupine", "lupini"],
    # Mollusc-related
    "mollusc": ["snail", "escargot", "squid", "calamari", "octopus"],
    # Sulphite-related
    "sulphite": ["sulphite", "sulfite", "dried fruit", "wine"],
}


class MedicalService:
    """Service class for medical tracking and allergen detection logic.

    Encapsulates business logic for managing child medical records,
    performing allergen detection in meals, and generating medical
    safety alerts.

    Attributes:
        db: Async database session for database operations.
    """

    def __init__(self, db: AsyncSession) -> None:
        """Initialize MedicalService with database session.

        Args:
            db: Async database session for database operations.
        """
        self.db = db

    # =========================================================================
    # Allergen Detection
    # =========================================================================

    async def detect_allergens(
        self,
        child_id: UUID,
        meal_items: list[str],
        include_inactive: bool = False,
    ) -> AllergenDetectionResponse:
        """Detect allergens in meal items based on child's allergy profile.

        Performs fuzzy matching between meal items and the child's known
        allergies to identify potential allergen exposure risks.

        Args:
            child_id: Unique identifier of the child.
            meal_items: List of food items in the meal.
            include_inactive: Whether to include inactive allergy records.

        Returns:
            AllergenDetectionResponse with detected allergens and risk assessment.
        """
        # Fetch child's allergies
        allergies = await self.get_allergies_by_child(
            child_id=child_id,
            include_inactive=include_inactive,
            food_only=True,
        )

        detected_allergens: list[DetectedAllergen] = []
        severity_levels: list[AllergySeverity] = []

        for meal_item in meal_items:
            meal_item_lower = meal_item.lower().strip()

            for allergy in allergies:
                match_confidence = self._calculate_allergen_match(
                    meal_item=meal_item_lower,
                    allergen_name=allergy.allergen_name,
                )

                if match_confidence > 0:
                    allergy_response = self._allergy_to_response(allergy)
                    detected_allergens.append(
                        DetectedAllergen(
                            meal_item=meal_item,
                            allergen_name=allergy.allergen_name,
                            allergy=allergy_response,
                            match_confidence=match_confidence,
                        )
                    )
                    severity_levels.append(allergy.severity)

        # Determine highest severity
        highest_severity: Optional[AllergySeverity] = None
        if severity_levels:
            severity_order = [
                AllergySeverity.MILD,
                AllergySeverity.MODERATE,
                AllergySeverity.SEVERE,
                AllergySeverity.LIFE_THREATENING,
            ]
            highest_severity = max(
                severity_levels,
                key=lambda s: severity_order.index(s),
            )

        # Determine if immediate action required
        requires_immediate_action = highest_severity in (
            AllergySeverity.SEVERE,
            AllergySeverity.LIFE_THREATENING,
        )

        return AllergenDetectionResponse(
            child_id=child_id,
            detected_allergens=detected_allergens,
            has_allergens=len(detected_allergens) > 0,
            highest_severity=highest_severity,
            requires_immediate_action=requires_immediate_action,
            detected_at=datetime.utcnow(),
        )

    def _calculate_allergen_match(
        self,
        meal_item: str,
        allergen_name: str,
    ) -> float:
        """Calculate match confidence between a meal item and allergen.

        Uses keyword-based fuzzy matching to detect potential allergens.

        Args:
            meal_item: The meal item to check (lowercase).
            allergen_name: The allergen name from the allergy record.

        Returns:
            Match confidence score between 0 and 1.
        """
        allergen_lower = allergen_name.lower().strip()

        # Direct match - highest confidence
        if allergen_lower in meal_item or meal_item in allergen_lower:
            return 1.0

        # Check for known allergen keywords
        for allergen_key, keywords in ALLERGEN_KEYWORDS.items():
            # Check if the allergy record matches this allergen category
            if allergen_lower in allergen_key or allergen_key in allergen_lower:
                # Now check if any keyword matches the meal item
                for keyword in keywords:
                    if keyword in meal_item:
                        return 0.9  # High confidence keyword match

            # Also check individual keywords against the allergen name
            if allergen_lower in keywords:
                for keyword in keywords:
                    if keyword in meal_item:
                        return 0.85

        # Check partial word matches
        allergen_words = allergen_lower.split()
        meal_words = meal_item.split()
        for allergen_word in allergen_words:
            if len(allergen_word) >= 3:
                for meal_word in meal_words:
                    if allergen_word in meal_word or meal_word in allergen_word:
                        return 0.7

        return 0.0

    # =========================================================================
    # Allergy Management
    # =========================================================================

    async def get_allergies_by_child(
        self,
        child_id: UUID,
        include_inactive: bool = False,
        food_only: bool = False,
    ) -> list[Allergy]:
        """Get all allergies for a specific child.

        Args:
            child_id: Unique identifier of the child.
            include_inactive: Whether to include inactive allergy records.
            food_only: Whether to filter to only food allergies.

        Returns:
            List of Allergy records for the child.
        """
        query = select(Allergy).where(
            cast(Allergy.child_id, String) == str(child_id)
        )

        if not include_inactive:
            query = query.where(Allergy.is_active == True)

        if food_only:
            query = query.where(Allergy.allergen_type == AllergenType.FOOD)

        query = query.order_by(Allergy.severity.desc(), Allergy.allergen_name)

        result = await self.db.execute(query)
        return list(result.scalars().all())

    async def get_allergy_by_id(self, allergy_id: UUID) -> Optional[Allergy]:
        """Retrieve a single allergy record by ID.

        Args:
            allergy_id: Unique identifier of the allergy.

        Returns:
            Allergy if found, None otherwise.
        """
        query = select(Allergy).where(
            cast(Allergy.id, String) == str(allergy_id)
        )
        result = await self.db.execute(query)
        return result.scalar_one_or_none()

    async def list_allergies(
        self,
        skip: int = 0,
        limit: int = 100,
        child_id: Optional[UUID] = None,
        allergen_type: Optional[AllergenType] = None,
        severity: Optional[AllergySeverity] = None,
        is_active: Optional[bool] = True,
        epi_pen_required: Optional[bool] = None,
    ) -> tuple[list[Allergy], int]:
        """List allergies with optional filtering and pagination.

        Args:
            skip: Number of records to skip.
            limit: Maximum number of records to return.
            child_id: Optional filter by child ID.
            allergen_type: Optional filter by allergen type.
            severity: Optional filter by severity.
            is_active: Optional filter by active status.
            epi_pen_required: Optional filter by EpiPen requirement.

        Returns:
            Tuple of (list of allergies, total count).
        """
        query = select(Allergy)

        if child_id is not None:
            query = query.where(cast(Allergy.child_id, String) == str(child_id))

        if allergen_type is not None:
            query = query.where(Allergy.allergen_type == allergen_type)

        if severity is not None:
            query = query.where(Allergy.severity == severity)

        if is_active is not None:
            query = query.where(Allergy.is_active == is_active)

        if epi_pen_required is not None:
            query = query.where(Allergy.epi_pen_required == epi_pen_required)

        # Get total count
        count_query = select(func.count()).select_from(query.subquery())
        count_result = await self.db.execute(count_query)
        total = count_result.scalar() or 0

        # Apply pagination and ordering
        query = query.offset(skip).limit(limit).order_by(
            Allergy.severity.desc(),
            Allergy.created_at.desc(),
        )

        result = await self.db.execute(query)
        allergies = list(result.scalars().all())

        return allergies, total

    async def create_allergy(
        self,
        child_id: UUID,
        allergen_name: str,
        created_by_id: UUID,
        allergen_type: AllergenType = AllergenType.FOOD,
        severity: AllergySeverity = AllergySeverity.MODERATE,
        reaction: Optional[str] = None,
        treatment: Optional[str] = None,
        epi_pen_required: bool = False,
        epi_pen_location: Optional[str] = None,
        diagnosed_date: Optional[date] = None,
        diagnosed_by: Optional[str] = None,
        notes: Optional[str] = None,
    ) -> Allergy:
        """Create a new allergy record.

        Args:
            child_id: Unique identifier of the child.
            allergen_name: Name of the allergen.
            created_by_id: ID of user creating the record.
            allergen_type: Type/category of the allergen.
            severity: Severity level of the allergy.
            reaction: Description of allergic reaction.
            treatment: Recommended treatment/response.
            epi_pen_required: Whether an EpiPen is required.
            epi_pen_location: Where the EpiPen is stored.
            diagnosed_date: Date when allergy was diagnosed.
            diagnosed_by: Name of diagnosing doctor/specialist.
            notes: Additional notes.

        Returns:
            The created Allergy record.
        """
        allergy = Allergy(
            child_id=child_id,
            allergen_name=allergen_name,
            allergen_type=allergen_type,
            severity=severity,
            reaction=reaction,
            treatment=treatment,
            epi_pen_required=epi_pen_required,
            epi_pen_location=epi_pen_location,
            diagnosed_date=diagnosed_date,
            diagnosed_by=diagnosed_by,
            notes=notes,
            created_by_id=created_by_id,
        )
        self.db.add(allergy)
        await self.db.commit()
        await self.db.refresh(allergy)
        return allergy

    async def verify_allergy(
        self,
        allergy_id: UUID,
        verified_by_id: UUID,
    ) -> Allergy:
        """Mark an allergy record as verified.

        Args:
            allergy_id: Unique identifier of the allergy.
            verified_by_id: ID of staff verifying the record.

        Returns:
            The updated Allergy record.

        Raises:
            AllergyNotFoundError: If the allergy is not found.
        """
        allergy = await self.get_allergy_by_id(allergy_id)
        if allergy is None:
            raise AllergyNotFoundError(f"Allergy with ID {allergy_id} not found")

        allergy.is_verified = True
        allergy.verified_by_id = verified_by_id
        allergy.verified_date = date.today()

        await self.db.commit()
        await self.db.refresh(allergy)
        return allergy

    async def deactivate_allergy(self, allergy_id: UUID) -> Allergy:
        """Deactivate an allergy record.

        Args:
            allergy_id: Unique identifier of the allergy.

        Returns:
            The updated Allergy record.

        Raises:
            AllergyNotFoundError: If the allergy is not found.
        """
        allergy = await self.get_allergy_by_id(allergy_id)
        if allergy is None:
            raise AllergyNotFoundError(f"Allergy with ID {allergy_id} not found")

        allergy.is_active = False

        await self.db.commit()
        await self.db.refresh(allergy)
        return allergy

    # =========================================================================
    # Medication Management
    # =========================================================================

    async def get_medications_by_child(
        self,
        child_id: UUID,
        include_inactive: bool = False,
        staff_administered_only: bool = False,
    ) -> list[Medication]:
        """Get all medications for a specific child.

        Args:
            child_id: Unique identifier of the child.
            include_inactive: Whether to include inactive medication records.
            staff_administered_only: Whether to filter to staff-administered only.

        Returns:
            List of Medication records for the child.
        """
        query = select(Medication).where(
            cast(Medication.child_id, String) == str(child_id)
        )

        if not include_inactive:
            query = query.where(Medication.is_active == True)

        if staff_administered_only:
            query = query.where(
                Medication.administered_by.in_([AdministeredBy.STAFF, AdministeredBy.NURSE])
            )

        query = query.order_by(Medication.medication_name)

        result = await self.db.execute(query)
        return list(result.scalars().all())

    async def get_medication_by_id(self, medication_id: UUID) -> Optional[Medication]:
        """Retrieve a single medication record by ID.

        Args:
            medication_id: Unique identifier of the medication.

        Returns:
            Medication if found, None otherwise.
        """
        query = select(Medication).where(
            cast(Medication.id, String) == str(medication_id)
        )
        result = await self.db.execute(query)
        return result.scalar_one_or_none()

    async def list_medications(
        self,
        skip: int = 0,
        limit: int = 100,
        child_id: Optional[UUID] = None,
        medication_type: Optional[MedicationType] = None,
        administered_by: Optional[AdministeredBy] = None,
        is_active: Optional[bool] = True,
        expiring_within_days: Optional[int] = None,
    ) -> tuple[list[Medication], int]:
        """List medications with optional filtering and pagination.

        Args:
            skip: Number of records to skip.
            limit: Maximum number of records to return.
            child_id: Optional filter by child ID.
            medication_type: Optional filter by medication type.
            administered_by: Optional filter by administrator.
            is_active: Optional filter by active status.
            expiring_within_days: Optional filter for medications expiring soon.

        Returns:
            Tuple of (list of medications, total count).
        """
        query = select(Medication)

        if child_id is not None:
            query = query.where(cast(Medication.child_id, String) == str(child_id))

        if medication_type is not None:
            query = query.where(Medication.medication_type == medication_type)

        if administered_by is not None:
            query = query.where(Medication.administered_by == administered_by)

        if is_active is not None:
            query = query.where(Medication.is_active == is_active)

        if expiring_within_days is not None:
            expiry_date = date.today()
            from datetime import timedelta
            future_date = expiry_date + timedelta(days=expiring_within_days)
            query = query.where(
                and_(
                    Medication.expiration_date.isnot(None),
                    Medication.expiration_date <= future_date,
                    Medication.expiration_date >= expiry_date,
                )
            )

        # Get total count
        count_query = select(func.count()).select_from(query.subquery())
        count_result = await self.db.execute(count_query)
        total = count_result.scalar() or 0

        # Apply pagination and ordering
        query = query.offset(skip).limit(limit).order_by(
            Medication.expiration_date.asc().nullslast(),
            Medication.created_at.desc(),
        )

        result = await self.db.execute(query)
        medications = list(result.scalars().all())

        return medications, total

    async def create_medication(
        self,
        child_id: UUID,
        medication_name: str,
        dosage: str,
        frequency: str,
        created_by_id: UUID,
        medication_type: MedicationType = MedicationType.PRESCRIPTION,
        **kwargs,
    ) -> Medication:
        """Create a new medication record.

        Args:
            child_id: Unique identifier of the child.
            medication_name: Name of the medication.
            dosage: Dosage amount and units.
            frequency: How often medication is taken.
            created_by_id: ID of user creating the record.
            medication_type: Type of medication.
            **kwargs: Additional medication fields.

        Returns:
            The created Medication record.
        """
        medication = Medication(
            child_id=child_id,
            medication_name=medication_name,
            dosage=dosage,
            frequency=frequency,
            medication_type=medication_type,
            created_by_id=created_by_id,
            **kwargs,
        )
        self.db.add(medication)
        await self.db.commit()
        await self.db.refresh(medication)
        return medication

    async def get_expiring_medications(
        self,
        days_ahead: int = 30,
    ) -> list[Medication]:
        """Get medications that are expiring soon or already expired.

        Args:
            days_ahead: Number of days to look ahead for expiring medications.

        Returns:
            List of medications expiring within the specified timeframe.
        """
        from datetime import timedelta

        today = date.today()
        future_date = today + timedelta(days=days_ahead)

        query = (
            select(Medication)
            .where(
                and_(
                    Medication.is_active == True,
                    Medication.expiration_date.isnot(None),
                    Medication.expiration_date <= future_date,
                )
            )
            .order_by(Medication.expiration_date.asc())
        )

        result = await self.db.execute(query)
        return list(result.scalars().all())

    # =========================================================================
    # Accommodation Plan Management
    # =========================================================================

    async def get_accommodation_plans_by_child(
        self,
        child_id: UUID,
        include_inactive: bool = False,
        include_expired: bool = False,
    ) -> list[AccommodationPlan]:
        """Get all accommodation plans for a specific child.

        Args:
            child_id: Unique identifier of the child.
            include_inactive: Whether to include archived plans.
            include_expired: Whether to include expired plans.

        Returns:
            List of AccommodationPlan records for the child.
        """
        query = select(AccommodationPlan).where(
            cast(AccommodationPlan.child_id, String) == str(child_id)
        )

        if not include_inactive:
            query = query.where(
                AccommodationPlan.status != AccommodationPlanStatus.ARCHIVED
            )

        if not include_expired:
            today = date.today()
            query = query.where(
                or_(
                    AccommodationPlan.expiration_date.is_(None),
                    AccommodationPlan.expiration_date >= today,
                )
            )

        query = query.order_by(AccommodationPlan.effective_date.desc())

        result = await self.db.execute(query)
        return list(result.scalars().all())

    async def get_accommodation_plan_by_id(
        self,
        plan_id: UUID,
    ) -> Optional[AccommodationPlan]:
        """Retrieve a single accommodation plan by ID.

        Args:
            plan_id: Unique identifier of the plan.

        Returns:
            AccommodationPlan if found, None otherwise.
        """
        query = select(AccommodationPlan).where(
            cast(AccommodationPlan.id, String) == str(plan_id)
        )
        result = await self.db.execute(query)
        return result.scalar_one_or_none()

    async def list_accommodation_plans(
        self,
        skip: int = 0,
        limit: int = 100,
        child_id: Optional[UUID] = None,
        plan_type: Optional[AccommodationPlanType] = None,
        status: Optional[AccommodationPlanStatus] = None,
    ) -> tuple[list[AccommodationPlan], int]:
        """List accommodation plans with optional filtering and pagination.

        Args:
            skip: Number of records to skip.
            limit: Maximum number of records to return.
            child_id: Optional filter by child ID.
            plan_type: Optional filter by plan type.
            status: Optional filter by status.

        Returns:
            Tuple of (list of accommodation plans, total count).
        """
        query = select(AccommodationPlan)

        if child_id is not None:
            query = query.where(
                cast(AccommodationPlan.child_id, String) == str(child_id)
            )

        if plan_type is not None:
            query = query.where(AccommodationPlan.plan_type == plan_type)

        if status is not None:
            query = query.where(AccommodationPlan.status == status)

        # Get total count
        count_query = select(func.count()).select_from(query.subquery())
        count_result = await self.db.execute(count_query)
        total = count_result.scalar() or 0

        # Apply pagination and ordering
        query = query.offset(skip).limit(limit).order_by(
            AccommodationPlan.effective_date.desc(),
        )

        result = await self.db.execute(query)
        plans = list(result.scalars().all())

        return plans, total

    # =========================================================================
    # Medical Alert Management
    # =========================================================================

    async def get_alerts_by_child(
        self,
        child_id: UUID,
        include_inactive: bool = False,
        dashboard_only: bool = False,
        check_in_only: bool = False,
    ) -> list[MedicalAlert]:
        """Get all medical alerts for a specific child.

        Args:
            child_id: Unique identifier of the child.
            include_inactive: Whether to include inactive alerts.
            dashboard_only: Whether to filter to dashboard alerts only.
            check_in_only: Whether to filter to check-in alerts only.

        Returns:
            List of MedicalAlert records for the child.
        """
        query = select(MedicalAlert).where(
            cast(MedicalAlert.child_id, String) == str(child_id)
        )

        if not include_inactive:
            query = query.where(MedicalAlert.is_active == True)

        if dashboard_only:
            query = query.where(MedicalAlert.display_on_dashboard == True)

        if check_in_only:
            query = query.where(MedicalAlert.notify_on_check_in == True)

        # Filter by effective/expiration dates
        today = date.today()
        query = query.where(
            or_(
                MedicalAlert.effective_date.is_(None),
                MedicalAlert.effective_date <= today,
            )
        )
        query = query.where(
            or_(
                MedicalAlert.expiration_date.is_(None),
                MedicalAlert.expiration_date >= today,
            )
        )

        query = query.order_by(
            MedicalAlert.alert_level.desc(),
            MedicalAlert.created_at.desc(),
        )

        result = await self.db.execute(query)
        return list(result.scalars().all())

    async def get_alert_by_id(self, alert_id: UUID) -> Optional[MedicalAlert]:
        """Retrieve a single medical alert by ID.

        Args:
            alert_id: Unique identifier of the alert.

        Returns:
            MedicalAlert if found, None otherwise.
        """
        query = select(MedicalAlert).where(
            cast(MedicalAlert.id, String) == str(alert_id)
        )
        result = await self.db.execute(query)
        return result.scalar_one_or_none()

    async def list_alerts(
        self,
        skip: int = 0,
        limit: int = 100,
        child_id: Optional[UUID] = None,
        alert_type: Optional[AlertType] = None,
        alert_level: Optional[AlertLevel] = None,
        is_active: Optional[bool] = True,
    ) -> tuple[list[MedicalAlert], int]:
        """List medical alerts with optional filtering and pagination.

        Args:
            skip: Number of records to skip.
            limit: Maximum number of records to return.
            child_id: Optional filter by child ID.
            alert_type: Optional filter by alert type.
            alert_level: Optional filter by alert level.
            is_active: Optional filter by active status.

        Returns:
            Tuple of (list of alerts, total count).
        """
        query = select(MedicalAlert)

        if child_id is not None:
            query = query.where(
                cast(MedicalAlert.child_id, String) == str(child_id)
            )

        if alert_type is not None:
            query = query.where(MedicalAlert.alert_type == alert_type)

        if alert_level is not None:
            query = query.where(MedicalAlert.alert_level == alert_level)

        if is_active is not None:
            query = query.where(MedicalAlert.is_active == is_active)

        # Get total count
        count_query = select(func.count()).select_from(query.subquery())
        count_result = await self.db.execute(count_query)
        total = count_result.scalar() or 0

        # Apply pagination and ordering
        query = query.offset(skip).limit(limit).order_by(
            MedicalAlert.alert_level.desc(),
            MedicalAlert.created_at.desc(),
        )

        result = await self.db.execute(query)
        alerts = list(result.scalars().all())

        return alerts, total

    async def create_allergen_exposure_alert(
        self,
        child_id: UUID,
        allergy: Allergy,
        meal_item: str,
        created_by_id: UUID,
    ) -> MedicalAlert:
        """Create a medical alert for allergen exposure.

        Args:
            child_id: Unique identifier of the child.
            allergy: The allergy that was triggered.
            meal_item: The meal item that contains the allergen.
            created_by_id: ID of user creating the alert.

        Returns:
            The created MedicalAlert record.
        """
        # Determine alert level based on allergy severity
        alert_level = AlertLevel.WARNING
        if allergy.severity == AllergySeverity.SEVERE:
            alert_level = AlertLevel.WARNING
        elif allergy.severity == AllergySeverity.LIFE_THREATENING:
            alert_level = AlertLevel.CRITICAL

        title = f"Allergen Detected: {allergy.allergen_name}"
        description = (
            f"The meal item '{meal_item}' may contain {allergy.allergen_name}, "
            f"which the child is allergic to. "
            f"Severity: {allergy.severity.value}."
        )

        action_required = None
        if allergy.epi_pen_required:
            action_required = (
                f"EpiPen required. Location: {allergy.epi_pen_location or 'Not specified'}. "
                f"Treatment: {allergy.treatment or 'Follow emergency procedures.'}"
            )
        elif allergy.treatment:
            action_required = allergy.treatment

        alert = MedicalAlert(
            child_id=child_id,
            alert_type=AlertType.ALLERGY,
            alert_level=alert_level,
            title=title,
            description=description,
            action_required=action_required,
            display_on_dashboard=True,
            display_on_attendance=False,
            display_on_reports=True,
            notify_on_check_in=False,
            related_allergy_id=allergy.id,
            created_by_id=created_by_id,
        )

        self.db.add(alert)
        await self.db.commit()
        await self.db.refresh(alert)
        return alert

    # =========================================================================
    # Child Medical Summary
    # =========================================================================

    async def get_child_medical_summary(
        self,
        child_id: UUID,
        include_inactive: bool = False,
        include_expired_plans: bool = False,
    ) -> ChildMedicalSummary:
        """Get a complete medical summary for a child.

        Aggregates all allergies, medications, accommodation plans, and
        active alerts into a comprehensive summary.

        Args:
            child_id: Unique identifier of the child.
            include_inactive: Whether to include inactive records.
            include_expired_plans: Whether to include expired plans.

        Returns:
            ChildMedicalSummary with all medical information.
        """
        # Fetch all medical records
        allergies = await self.get_allergies_by_child(
            child_id=child_id,
            include_inactive=include_inactive,
        )
        medications = await self.get_medications_by_child(
            child_id=child_id,
            include_inactive=include_inactive,
        )
        plans = await self.get_accommodation_plans_by_child(
            child_id=child_id,
            include_inactive=include_inactive,
            include_expired=include_expired_plans,
        )
        alerts = await self.get_alerts_by_child(
            child_id=child_id,
            include_inactive=False,  # Only active alerts
        )

        # Convert to response schemas
        allergy_responses = [self._allergy_to_response(a) for a in allergies]
        medication_responses = [self._medication_to_response(m) for m in medications]
        plan_responses = [self._plan_to_response(p) for p in plans]
        alert_responses = [self._alert_to_response(a) for a in alerts]

        # Calculate summary flags
        has_severe_allergies = any(
            a.severity in (AllergySeverity.SEVERE, AllergySeverity.LIFE_THREATENING)
            for a in allergies
        )
        has_epi_pen = any(a.epi_pen_required for a in allergies)
        has_staff_administered = any(
            m.administered_by in (AdministeredBy.STAFF, AdministeredBy.NURSE)
            for m in medications
        )

        return ChildMedicalSummary(
            child_id=child_id,
            allergies=allergy_responses,
            medications=medication_responses,
            accommodation_plans=plan_responses,
            active_alerts=alert_responses,
            has_severe_allergies=has_severe_allergies,
            has_epi_pen=has_epi_pen,
            has_staff_administered_medications=has_staff_administered,
            generated_at=datetime.utcnow(),
        )

    # =========================================================================
    # Response Conversion Helpers
    # =========================================================================

    def _allergy_to_response(self, allergy: Allergy) -> AllergyResponse:
        """Convert Allergy model to AllergyResponse schema.

        Args:
            allergy: The Allergy model instance.

        Returns:
            AllergyResponse schema instance.
        """
        return AllergyResponse(
            id=allergy.id,
            child_id=allergy.child_id,
            allergen_name=allergy.allergen_name,
            allergen_type=allergy.allergen_type,
            severity=allergy.severity,
            reaction=allergy.reaction,
            treatment=allergy.treatment,
            epi_pen_required=allergy.epi_pen_required,
            epi_pen_location=allergy.epi_pen_location,
            diagnosed_date=allergy.diagnosed_date,
            diagnosed_by=allergy.diagnosed_by,
            notes=allergy.notes,
            is_verified=allergy.is_verified,
            verified_by_id=allergy.verified_by_id,
            verified_date=allergy.verified_date,
            is_active=allergy.is_active,
            created_by_id=allergy.created_by_id,
            created_at=allergy.created_at,
            updated_at=allergy.updated_at,
        )

    def _medication_to_response(self, medication: Medication) -> MedicationResponse:
        """Convert Medication model to MedicationResponse schema.

        Args:
            medication: The Medication model instance.

        Returns:
            MedicationResponse schema instance.
        """
        return MedicationResponse(
            id=medication.id,
            child_id=medication.child_id,
            medication_name=medication.medication_name,
            medication_type=medication.medication_type,
            dosage=medication.dosage,
            frequency=medication.frequency,
            route=medication.route,
            prescribed_by=medication.prescribed_by,
            prescription_date=medication.prescription_date,
            expiration_date=medication.expiration_date,
            purpose=medication.purpose,
            side_effects=medication.side_effects,
            storage_location=medication.storage_location,
            administered_by=medication.administered_by,
            notes=medication.notes,
            parent_consent=medication.parent_consent,
            parent_consent_date=medication.parent_consent_date,
            is_verified=medication.is_verified,
            verified_by_id=medication.verified_by_id,
            verified_date=medication.verified_date,
            is_active=medication.is_active,
            created_by_id=medication.created_by_id,
            created_at=medication.created_at,
            updated_at=medication.updated_at,
        )

    def _plan_to_response(self, plan: AccommodationPlan) -> AccommodationPlanResponse:
        """Convert AccommodationPlan model to AccommodationPlanResponse schema.

        Args:
            plan: The AccommodationPlan model instance.

        Returns:
            AccommodationPlanResponse schema instance.
        """
        return AccommodationPlanResponse(
            id=plan.id,
            child_id=plan.child_id,
            school_year_id=plan.school_year_id,
            plan_type=plan.plan_type,
            plan_name=plan.plan_name,
            description=plan.description,
            accommodations=plan.accommodations,
            emergency_procedures=plan.emergency_procedures,
            triggers_signs=plan.triggers_signs,
            staff_notifications=plan.staff_notifications,
            document_path=plan.document_path,
            effective_date=plan.effective_date,
            expiration_date=plan.expiration_date,
            review_date=plan.review_date,
            notes=plan.notes,
            approved_by_id=plan.approved_by_id,
            approved_date=plan.approved_date,
            status=plan.status,
            created_by_id=plan.created_by_id,
            created_at=plan.created_at,
            updated_at=plan.updated_at,
        )

    def _alert_to_response(self, alert: MedicalAlert) -> MedicalAlertResponse:
        """Convert MedicalAlert model to MedicalAlertResponse schema.

        Args:
            alert: The MedicalAlert model instance.

        Returns:
            MedicalAlertResponse schema instance.
        """
        return MedicalAlertResponse(
            id=alert.id,
            child_id=alert.child_id,
            alert_type=alert.alert_type,
            alert_level=alert.alert_level,
            title=alert.title,
            description=alert.description,
            action_required=alert.action_required,
            display_on_dashboard=alert.display_on_dashboard,
            display_on_attendance=alert.display_on_attendance,
            display_on_reports=alert.display_on_reports,
            notify_on_check_in=alert.notify_on_check_in,
            related_allergy_id=alert.related_allergy_id,
            related_medication_id=alert.related_medication_id,
            related_plan_id=alert.related_plan_id,
            effective_date=alert.effective_date,
            expiration_date=alert.expiration_date,
            is_active=alert.is_active,
            created_by_id=alert.created_by_id,
            created_at=alert.created_at,
            updated_at=alert.updated_at,
        )
