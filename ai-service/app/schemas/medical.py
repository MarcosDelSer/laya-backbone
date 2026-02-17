"""Medical domain schemas for LAYA AI Service.

Defines Pydantic schemas for allergy, medication, accommodation plan,
and medical alert request/response validation. These schemas support
comprehensive medical tracking for children in childcare settings.
"""

from datetime import date, datetime
from typing import Optional
from uuid import UUID

from pydantic import Field

from app.models.medical import (
    AccommodationPlanStatus,
    AccommodationPlanType,
    AdministeredBy,
    AlertLevel,
    AlertType,
    AllergenType,
    AllergySeverity,
    MedicationRoute,
    MedicationType,
)
from app.schemas.base import BaseResponse, BaseSchema, PaginatedResponse


# ============================================================================
# Allergy Schemas
# ============================================================================


class AllergyBase(BaseSchema):
    """Base schema for allergy data.

    Contains common fields shared between request and response schemas.

    Attributes:
        child_id: Unique identifier of the child
        allergen_name: Name of the allergen
        allergen_type: Type/category of the allergen
        severity: Severity level of the allergy
        reaction: Description of allergic reaction
        treatment: Recommended treatment/response
        epi_pen_required: Whether an EpiPen is required
        epi_pen_location: Where the EpiPen is stored
        diagnosed_date: Date when allergy was diagnosed
        diagnosed_by: Name of diagnosing doctor/specialist
        notes: Additional notes
    """

    child_id: UUID = Field(
        ...,
        description="Unique identifier of the child",
    )
    allergen_name: str = Field(
        ...,
        min_length=1,
        max_length=100,
        description="Name of the allergen",
    )
    allergen_type: AllergenType = Field(
        default=AllergenType.FOOD,
        description="Type/category of the allergen",
    )
    severity: AllergySeverity = Field(
        default=AllergySeverity.MODERATE,
        description="Severity level of the allergy",
    )
    reaction: Optional[str] = Field(
        default=None,
        max_length=2000,
        description="Description of allergic reaction",
    )
    treatment: Optional[str] = Field(
        default=None,
        max_length=2000,
        description="Recommended treatment/response",
    )
    epi_pen_required: bool = Field(
        default=False,
        description="Whether an EpiPen is required",
    )
    epi_pen_location: Optional[str] = Field(
        default=None,
        max_length=255,
        description="Where the EpiPen is stored",
    )
    diagnosed_date: Optional[date] = Field(
        default=None,
        description="Date when allergy was diagnosed",
    )
    diagnosed_by: Optional[str] = Field(
        default=None,
        max_length=100,
        description="Name of diagnosing doctor/specialist",
    )
    notes: Optional[str] = Field(
        default=None,
        max_length=2000,
        description="Additional notes",
    )


class AllergyRequest(AllergyBase):
    """Request schema for creating or updating an allergy record.

    Inherits all fields from AllergyBase.
    """

    pass


class AllergyResponse(AllergyBase, BaseResponse):
    """Response schema for allergy data.

    Includes all base allergy fields plus ID, timestamps, and status fields.

    Attributes:
        is_verified: Whether the allergy has been verified by staff
        verified_by_id: ID of staff who verified
        verified_date: Date when verified
        is_active: Whether the allergy record is active
        created_by_id: ID of user who created the record
    """

    is_verified: bool = Field(
        default=False,
        description="Whether the allergy has been verified by staff",
    )
    verified_by_id: Optional[UUID] = Field(
        default=None,
        description="ID of staff who verified",
    )
    verified_date: Optional[date] = Field(
        default=None,
        description="Date when verified",
    )
    is_active: bool = Field(
        default=True,
        description="Whether the allergy record is active",
    )
    created_by_id: UUID = Field(
        ...,
        description="ID of user who created the record",
    )


class AllergyListResponse(PaginatedResponse):
    """Paginated list of allergies.

    Attributes:
        items: List of allergies
    """

    items: list[AllergyResponse] = Field(
        ...,
        description="List of allergies",
    )


# ============================================================================
# Medication Schemas
# ============================================================================


class MedicationBase(BaseSchema):
    """Base schema for medication data.

    Contains common fields shared between request and response schemas.

    Attributes:
        child_id: Unique identifier of the child
        medication_name: Name of the medication
        medication_type: Type of medication (prescription, OTC, etc.)
        dosage: Dosage amount and units
        frequency: How often medication is taken
        route: Route of administration
        prescribed_by: Name of prescribing doctor
        prescription_date: Date medication was prescribed
        expiration_date: Medication expiration date
        purpose: Reason for medication
        side_effects: Known side effects to watch for
        storage_location: Where medication is stored at school
        administered_by: Who administers the medication
        notes: Additional notes
    """

    child_id: UUID = Field(
        ...,
        description="Unique identifier of the child",
    )
    medication_name: str = Field(
        ...,
        min_length=1,
        max_length=100,
        description="Name of the medication",
    )
    medication_type: MedicationType = Field(
        default=MedicationType.PRESCRIPTION,
        description="Type of medication (prescription, OTC, etc.)",
    )
    dosage: str = Field(
        ...,
        min_length=1,
        max_length=100,
        description="Dosage amount and units",
    )
    frequency: str = Field(
        ...,
        min_length=1,
        max_length=100,
        description="How often medication is taken",
    )
    route: MedicationRoute = Field(
        default=MedicationRoute.ORAL,
        description="Route of administration",
    )
    prescribed_by: Optional[str] = Field(
        default=None,
        max_length=100,
        description="Name of prescribing doctor",
    )
    prescription_date: Optional[date] = Field(
        default=None,
        description="Date medication was prescribed",
    )
    expiration_date: Optional[date] = Field(
        default=None,
        description="Medication expiration date",
    )
    purpose: Optional[str] = Field(
        default=None,
        max_length=2000,
        description="Reason for medication",
    )
    side_effects: Optional[str] = Field(
        default=None,
        max_length=2000,
        description="Known side effects to watch for",
    )
    storage_location: Optional[str] = Field(
        default=None,
        max_length=255,
        description="Where medication is stored at school",
    )
    administered_by: AdministeredBy = Field(
        default=AdministeredBy.STAFF,
        description="Who administers the medication",
    )
    notes: Optional[str] = Field(
        default=None,
        max_length=2000,
        description="Additional notes",
    )


class MedicationRequest(MedicationBase):
    """Request schema for creating or updating a medication record.

    Inherits all fields from MedicationBase.
    """

    pass


class MedicationResponse(MedicationBase, BaseResponse):
    """Response schema for medication data.

    Includes all base medication fields plus ID, timestamps, and status fields.

    Attributes:
        parent_consent: Whether parent consent has been obtained
        parent_consent_date: Date parent consent was given
        is_verified: Whether the medication has been verified by staff
        verified_by_id: ID of staff who verified
        verified_date: Date when verified
        is_active: Whether the medication record is active
        created_by_id: ID of user who created the record
    """

    parent_consent: bool = Field(
        default=False,
        description="Whether parent consent has been obtained",
    )
    parent_consent_date: Optional[date] = Field(
        default=None,
        description="Date parent consent was given",
    )
    is_verified: bool = Field(
        default=False,
        description="Whether the medication has been verified by staff",
    )
    verified_by_id: Optional[UUID] = Field(
        default=None,
        description="ID of staff who verified",
    )
    verified_date: Optional[date] = Field(
        default=None,
        description="Date when verified",
    )
    is_active: bool = Field(
        default=True,
        description="Whether the medication record is active",
    )
    created_by_id: UUID = Field(
        ...,
        description="ID of user who created the record",
    )


class MedicationListResponse(PaginatedResponse):
    """Paginated list of medications.

    Attributes:
        items: List of medications
    """

    items: list[MedicationResponse] = Field(
        ...,
        description="List of medications",
    )


# ============================================================================
# Accommodation Plan Schemas
# ============================================================================


class AccommodationPlanBase(BaseSchema):
    """Base schema for accommodation plan data.

    Contains common fields shared between request and response schemas.

    Attributes:
        child_id: Unique identifier of the child
        school_year_id: School year the plan applies to
        plan_type: Type of accommodation plan
        plan_name: Name of the plan
        description: Detailed description of the plan
        accommodations: List of required accommodations
        emergency_procedures: Emergency response procedures
        triggers_signs: Warning signs to watch for
        staff_notifications: Staff who need to be notified
        document_path: Path to uploaded plan document
        effective_date: When the plan becomes effective
        expiration_date: When the plan expires
        review_date: Next scheduled review date
        notes: Additional notes
    """

    child_id: UUID = Field(
        ...,
        description="Unique identifier of the child",
    )
    school_year_id: Optional[UUID] = Field(
        default=None,
        description="School year the plan applies to",
    )
    plan_type: AccommodationPlanType = Field(
        default=AccommodationPlanType.HEALTH_PLAN,
        description="Type of accommodation plan",
    )
    plan_name: str = Field(
        ...,
        min_length=1,
        max_length=100,
        description="Name of the plan",
    )
    description: str = Field(
        ...,
        min_length=1,
        max_length=4000,
        description="Detailed description of the plan",
    )
    accommodations: str = Field(
        ...,
        min_length=1,
        max_length=4000,
        description="List of required accommodations",
    )
    emergency_procedures: Optional[str] = Field(
        default=None,
        max_length=4000,
        description="Emergency response procedures",
    )
    triggers_signs: Optional[str] = Field(
        default=None,
        max_length=2000,
        description="Warning signs to watch for",
    )
    staff_notifications: Optional[str] = Field(
        default=None,
        max_length=1000,
        description="Staff who need to be notified",
    )
    document_path: Optional[str] = Field(
        default=None,
        max_length=255,
        description="Path to uploaded plan document",
    )
    effective_date: date = Field(
        ...,
        description="When the plan becomes effective",
    )
    expiration_date: Optional[date] = Field(
        default=None,
        description="When the plan expires",
    )
    review_date: Optional[date] = Field(
        default=None,
        description="Next scheduled review date",
    )
    notes: Optional[str] = Field(
        default=None,
        max_length=2000,
        description="Additional notes",
    )


class AccommodationPlanRequest(AccommodationPlanBase):
    """Request schema for creating or updating an accommodation plan.

    Inherits all fields from AccommodationPlanBase.
    """

    pass


class AccommodationPlanResponse(AccommodationPlanBase, BaseResponse):
    """Response schema for accommodation plan data.

    Includes all base accommodation plan fields plus ID, timestamps,
    and approval status fields.

    Attributes:
        approved_by_id: ID of staff who approved the plan
        approved_date: Date plan was approved
        status: Current status of the plan
        created_by_id: ID of user who created the record
    """

    approved_by_id: Optional[UUID] = Field(
        default=None,
        description="ID of staff who approved the plan",
    )
    approved_date: Optional[date] = Field(
        default=None,
        description="Date plan was approved",
    )
    status: AccommodationPlanStatus = Field(
        default=AccommodationPlanStatus.DRAFT,
        description="Current status of the plan",
    )
    created_by_id: UUID = Field(
        ...,
        description="ID of user who created the record",
    )


class AccommodationPlanListResponse(PaginatedResponse):
    """Paginated list of accommodation plans.

    Attributes:
        items: List of accommodation plans
    """

    items: list[AccommodationPlanResponse] = Field(
        ...,
        description="List of accommodation plans",
    )


# ============================================================================
# Medical Alert Schemas
# ============================================================================


class MedicalAlertBase(BaseSchema):
    """Base schema for medical alert data.

    Contains common fields shared between request and response schemas.

    Attributes:
        child_id: Unique identifier of the child
        alert_type: Type of medical alert
        alert_level: Severity level of the alert
        title: Alert title
        description: Detailed description of the alert
        action_required: What staff should do
        display_on_dashboard: Whether to show on dashboard
        display_on_attendance: Whether to show on attendance
        display_on_reports: Whether to show on reports
        notify_on_check_in: Whether to notify on child check-in
        related_allergy_id: Link to related allergy record
        related_medication_id: Link to related medication record
        related_plan_id: Link to related accommodation plan
        effective_date: When the alert becomes effective
        expiration_date: When the alert expires
    """

    child_id: UUID = Field(
        ...,
        description="Unique identifier of the child",
    )
    alert_type: AlertType = Field(
        ...,
        description="Type of medical alert",
    )
    alert_level: AlertLevel = Field(
        default=AlertLevel.WARNING,
        description="Severity level of the alert",
    )
    title: str = Field(
        ...,
        min_length=1,
        max_length=100,
        description="Alert title",
    )
    description: str = Field(
        ...,
        min_length=1,
        max_length=2000,
        description="Detailed description of the alert",
    )
    action_required: Optional[str] = Field(
        default=None,
        max_length=2000,
        description="What staff should do",
    )
    display_on_dashboard: bool = Field(
        default=True,
        description="Whether to show on dashboard",
    )
    display_on_attendance: bool = Field(
        default=True,
        description="Whether to show on attendance",
    )
    display_on_reports: bool = Field(
        default=False,
        description="Whether to show on reports",
    )
    notify_on_check_in: bool = Field(
        default=False,
        description="Whether to notify on child check-in",
    )
    related_allergy_id: Optional[UUID] = Field(
        default=None,
        description="Link to related allergy record",
    )
    related_medication_id: Optional[UUID] = Field(
        default=None,
        description="Link to related medication record",
    )
    related_plan_id: Optional[UUID] = Field(
        default=None,
        description="Link to related accommodation plan",
    )
    effective_date: Optional[date] = Field(
        default=None,
        description="When the alert becomes effective",
    )
    expiration_date: Optional[date] = Field(
        default=None,
        description="When the alert expires",
    )


class MedicalAlertRequest(MedicalAlertBase):
    """Request schema for creating or updating a medical alert.

    Inherits all fields from MedicalAlertBase.
    """

    pass


class MedicalAlertResponse(MedicalAlertBase, BaseResponse):
    """Response schema for medical alert data.

    Includes all base medical alert fields plus ID, timestamps, and status.

    Attributes:
        is_active: Whether the alert is active
        created_by_id: ID of user who created the record
    """

    is_active: bool = Field(
        default=True,
        description="Whether the alert is active",
    )
    created_by_id: UUID = Field(
        ...,
        description="ID of user who created the record",
    )


class MedicalAlertListResponse(PaginatedResponse):
    """Paginated list of medical alerts.

    Attributes:
        items: List of medical alerts
    """

    items: list[MedicalAlertResponse] = Field(
        ...,
        description="List of medical alerts",
    )


# ============================================================================
# Allergen Detection Schemas
# ============================================================================


class AllergenDetectionRequest(BaseSchema):
    """Request schema for detecting allergens in meal items.

    Used to check if a meal contains allergens that a child is allergic to.

    Attributes:
        child_id: Unique identifier of the child
        meal_items: List of food items in the meal
        include_inactive: Whether to include inactive allergy records
    """

    child_id: UUID = Field(
        ...,
        description="Unique identifier of the child",
    )
    meal_items: list[str] = Field(
        ...,
        min_length=1,
        description="List of food items in the meal",
    )
    include_inactive: bool = Field(
        default=False,
        description="Whether to include inactive allergy records",
    )


class DetectedAllergen(BaseSchema):
    """A single detected allergen with matching allergy details.

    Attributes:
        meal_item: The meal item that contains the allergen
        allergen_name: Name of the matched allergen
        allergy: The child's allergy record for this allergen
        match_confidence: Confidence level of the allergen match (0-1)
    """

    meal_item: str = Field(
        ...,
        description="The meal item that contains the allergen",
    )
    allergen_name: str = Field(
        ...,
        description="Name of the matched allergen",
    )
    allergy: AllergyResponse = Field(
        ...,
        description="The child's allergy record for this allergen",
    )
    match_confidence: float = Field(
        ...,
        ge=0.0,
        le=1.0,
        description="Confidence level of the allergen match (0-1)",
    )


class AllergenDetectionResponse(BaseSchema):
    """Response schema for allergen detection results.

    Contains a list of detected allergens and overall risk assessment.

    Attributes:
        child_id: The child these results are for
        detected_allergens: List of detected allergens with details
        has_allergens: Whether any allergens were detected
        highest_severity: The highest severity level among detected allergens
        requires_immediate_action: Whether immediate action is required
        detected_at: When the detection was performed
    """

    child_id: UUID = Field(
        ...,
        description="The child these results are for",
    )
    detected_allergens: list[DetectedAllergen] = Field(
        ...,
        description="List of detected allergens with details",
    )
    has_allergens: bool = Field(
        ...,
        description="Whether any allergens were detected",
    )
    highest_severity: Optional[AllergySeverity] = Field(
        default=None,
        description="The highest severity level among detected allergens",
    )
    requires_immediate_action: bool = Field(
        default=False,
        description="Whether immediate action is required",
    )
    detected_at: datetime = Field(
        ...,
        description="When the detection was performed",
    )


# ============================================================================
# Child Medical Summary Schemas
# ============================================================================


class ChildMedicalSummaryRequest(BaseSchema):
    """Request schema for getting a child's complete medical summary.

    Attributes:
        child_id: Unique identifier of the child
        include_inactive: Whether to include inactive records
        include_expired_plans: Whether to include expired accommodation plans
    """

    child_id: UUID = Field(
        ...,
        description="Unique identifier of the child",
    )
    include_inactive: bool = Field(
        default=False,
        description="Whether to include inactive records",
    )
    include_expired_plans: bool = Field(
        default=False,
        description="Whether to include expired accommodation plans",
    )


class ChildMedicalSummary(BaseSchema):
    """Complete medical summary for a child.

    Attributes:
        child_id: The child this summary is for
        allergies: List of the child's allergies
        medications: List of the child's medications
        accommodation_plans: List of the child's accommodation plans
        active_alerts: List of active medical alerts
        has_severe_allergies: Whether the child has any severe allergies
        has_epi_pen: Whether the child requires an EpiPen
        has_staff_administered_medications: Whether child has staff-administered meds
        generated_at: When the summary was generated
    """

    child_id: UUID = Field(
        ...,
        description="The child this summary is for",
    )
    allergies: list[AllergyResponse] = Field(
        default_factory=list,
        description="List of the child's allergies",
    )
    medications: list[MedicationResponse] = Field(
        default_factory=list,
        description="List of the child's medications",
    )
    accommodation_plans: list[AccommodationPlanResponse] = Field(
        default_factory=list,
        description="List of the child's accommodation plans",
    )
    active_alerts: list[MedicalAlertResponse] = Field(
        default_factory=list,
        description="List of active medical alerts",
    )
    has_severe_allergies: bool = Field(
        default=False,
        description="Whether the child has any severe allergies",
    )
    has_epi_pen: bool = Field(
        default=False,
        description="Whether the child requires an EpiPen",
    )
    has_staff_administered_medications: bool = Field(
        default=False,
        description="Whether child has staff-administered medications",
    )
    generated_at: datetime = Field(
        ...,
        description="When the summary was generated",
    )
