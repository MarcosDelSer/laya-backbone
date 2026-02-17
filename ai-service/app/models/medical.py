"""Medical SQLAlchemy models for LAYA AI Service.

Defines database models for allergies, medications, accommodation plans, and medical alerts.
These models support comprehensive medical tracking for children in childcare settings.
"""

from datetime import date, datetime
from enum import Enum as PyEnum
from typing import Optional
from uuid import UUID, uuid4

from sqlalchemy import (
    Boolean,
    Date,
    DateTime,
    Enum,
    ForeignKey,
    Integer,
    String,
    Text,
    func,
)
from sqlalchemy.dialects.postgresql import UUID as PGUUID
from sqlalchemy.orm import Mapped, mapped_column, relationship

from app.models.base import Base


class AllergenType(str, PyEnum):
    """Types of allergens.

    Attributes:
        FOOD: Food allergies (e.g., peanuts, dairy)
        MEDICATION: Medication allergies (e.g., penicillin)
        ENVIRONMENTAL: Environmental allergies (e.g., dust, pollen)
        INSECT: Insect allergies (e.g., bee stings)
        OTHER: Other types of allergies
    """

    FOOD = "Food"
    MEDICATION = "Medication"
    ENVIRONMENTAL = "Environmental"
    INSECT = "Insect"
    OTHER = "Other"


class AllergySeverity(str, PyEnum):
    """Severity levels for allergies.

    Attributes:
        MILD: Mild allergic reactions
        MODERATE: Moderate allergic reactions
        SEVERE: Severe allergic reactions requiring immediate attention
        LIFE_THREATENING: Life-threatening reactions (anaphylaxis risk)
    """

    MILD = "Mild"
    MODERATE = "Moderate"
    SEVERE = "Severe"
    LIFE_THREATENING = "Life-Threatening"


class MedicationType(str, PyEnum):
    """Types of medications.

    Attributes:
        PRESCRIPTION: Prescription medications requiring doctor's order
        OVER_THE_COUNTER: Over-the-counter medications
        SUPPLEMENT: Dietary supplements and vitamins
        OTHER: Other types of medications
    """

    PRESCRIPTION = "Prescription"
    OVER_THE_COUNTER = "Over-the-Counter"
    SUPPLEMENT = "Supplement"
    OTHER = "Other"


class MedicationRoute(str, PyEnum):
    """Routes of medication administration.

    Attributes:
        ORAL: Taken by mouth
        TOPICAL: Applied to skin
        INJECTION: Administered via injection
        INHALED: Inhaled through respiratory system
        OTHER: Other administration routes
    """

    ORAL = "Oral"
    TOPICAL = "Topical"
    INJECTION = "Injection"
    INHALED = "Inhaled"
    OTHER = "Other"


class AdministeredBy(str, PyEnum):
    """Who administers the medication.

    Attributes:
        SELF: Self-administered by child
        STAFF: Administered by childcare staff
        NURSE: Administered by nurse
    """

    SELF = "Self"
    STAFF = "Staff"
    NURSE = "Nurse"


class AccommodationPlanType(str, PyEnum):
    """Types of accommodation plans.

    Attributes:
        PLAN_504: 504 Plan for disability accommodations
        IEP: Individualized Education Program
        HEALTH_PLAN: General health plan
        EMERGENCY_ACTION_PLAN: Emergency action plan
        OTHER: Other types of plans
    """

    PLAN_504 = "504 Plan"
    IEP = "IEP"
    HEALTH_PLAN = "Health Plan"
    EMERGENCY_ACTION_PLAN = "Emergency Action Plan"
    OTHER = "Other"


class AccommodationPlanStatus(str, PyEnum):
    """Status of accommodation plans.

    Attributes:
        DRAFT: Plan is in draft stage
        PENDING_APPROVAL: Plan is awaiting approval
        ACTIVE: Plan is currently active
        EXPIRED: Plan has expired
        ARCHIVED: Plan has been archived
    """

    DRAFT = "Draft"
    PENDING_APPROVAL = "Pending Approval"
    ACTIVE = "Active"
    EXPIRED = "Expired"
    ARCHIVED = "Archived"


class AlertType(str, PyEnum):
    """Types of medical alerts.

    Attributes:
        ALLERGY: Allergy-related alert
        MEDICATION: Medication-related alert
        CONDITION: Medical condition alert
        DIETARY: Dietary restriction alert
        EMERGENCY_CONTACT: Emergency contact information alert
        OTHER: Other types of alerts
    """

    ALLERGY = "Allergy"
    MEDICATION = "Medication"
    CONDITION = "Condition"
    DIETARY = "Dietary"
    EMERGENCY_CONTACT = "Emergency Contact"
    OTHER = "Other"


class AlertLevel(str, PyEnum):
    """Severity levels for medical alerts.

    Attributes:
        INFO: Informational alert
        WARNING: Warning alert requiring attention
        CRITICAL: Critical alert requiring immediate action
    """

    INFO = "Info"
    WARNING = "Warning"
    CRITICAL = "Critical"


class Allergy(Base):
    """SQLAlchemy model for child allergies.

    Represents an allergy record for a child, including allergen details,
    severity level, required treatments, and verification status.

    Attributes:
        id: Unique identifier for the allergy record
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
        is_verified: Whether the allergy has been verified by staff
        verified_by_id: ID of staff who verified
        verified_date: Date when verified
        notes: Additional notes
        is_active: Whether the allergy record is active
        created_by_id: ID of user who created the record
        created_at: Timestamp when the record was created
        updated_at: Timestamp when the record was last updated
    """

    __tablename__ = "medical_allergies"

    id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        primary_key=True,
        default=uuid4,
    )
    child_id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        nullable=False,
        index=True,
    )
    allergen_name: Mapped[str] = mapped_column(
        String(100),
        nullable=False,
        index=True,
    )
    allergen_type: Mapped[AllergenType] = mapped_column(
        Enum(AllergenType, name="allergen_type_enum", create_constraint=True),
        nullable=False,
        default=AllergenType.FOOD,
        index=True,
    )
    severity: Mapped[AllergySeverity] = mapped_column(
        Enum(AllergySeverity, name="allergy_severity_enum", create_constraint=True),
        nullable=False,
        default=AllergySeverity.MODERATE,
        index=True,
    )
    reaction: Mapped[Optional[str]] = mapped_column(
        Text,
        nullable=True,
    )
    treatment: Mapped[Optional[str]] = mapped_column(
        Text,
        nullable=True,
    )
    epi_pen_required: Mapped[bool] = mapped_column(
        Boolean,
        nullable=False,
        default=False,
    )
    epi_pen_location: Mapped[Optional[str]] = mapped_column(
        String(255),
        nullable=True,
    )
    diagnosed_date: Mapped[Optional[date]] = mapped_column(
        Date,
        nullable=True,
    )
    diagnosed_by: Mapped[Optional[str]] = mapped_column(
        String(100),
        nullable=True,
    )
    is_verified: Mapped[bool] = mapped_column(
        Boolean,
        nullable=False,
        default=False,
    )
    verified_by_id: Mapped[Optional[UUID]] = mapped_column(
        PGUUID(as_uuid=True),
        nullable=True,
    )
    verified_date: Mapped[Optional[date]] = mapped_column(
        Date,
        nullable=True,
    )
    notes: Mapped[Optional[str]] = mapped_column(
        Text,
        nullable=True,
    )
    is_active: Mapped[bool] = mapped_column(
        Boolean,
        nullable=False,
        default=True,
        index=True,
    )
    created_by_id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        nullable=False,
    )
    created_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True),
        nullable=False,
        server_default=func.now(),
    )
    updated_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True),
        nullable=False,
        server_default=func.now(),
        onupdate=func.now(),
    )

    # Relationships
    alerts: Mapped[list["MedicalAlert"]] = relationship(
        "MedicalAlert",
        back_populates="related_allergy",
        foreign_keys="MedicalAlert.related_allergy_id",
        cascade="all, delete-orphan",
    )

    def __repr__(self) -> str:
        """Return string representation of the Allergy."""
        return (
            f"<Allergy(id={self.id}, child_id={self.child_id}, "
            f"allergen='{self.allergen_name}', severity={self.severity.value})>"
        )


class Medication(Base):
    """SQLAlchemy model for child medications.

    Represents a medication record for a child, including dosage information,
    administration details, and expiration tracking.

    Attributes:
        id: Unique identifier for the medication record
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
        parent_consent: Whether parent consent has been obtained
        parent_consent_date: Date parent consent was given
        is_verified: Whether the medication has been verified by staff
        verified_by_id: ID of staff who verified
        verified_date: Date when verified
        notes: Additional notes
        is_active: Whether the medication record is active
        created_by_id: ID of user who created the record
        created_at: Timestamp when the record was created
        updated_at: Timestamp when the record was last updated
    """

    __tablename__ = "medical_medications"

    id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        primary_key=True,
        default=uuid4,
    )
    child_id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        nullable=False,
        index=True,
    )
    medication_name: Mapped[str] = mapped_column(
        String(100),
        nullable=False,
        index=True,
    )
    medication_type: Mapped[MedicationType] = mapped_column(
        Enum(MedicationType, name="medication_type_enum", create_constraint=True),
        nullable=False,
        default=MedicationType.PRESCRIPTION,
        index=True,
    )
    dosage: Mapped[str] = mapped_column(
        String(100),
        nullable=False,
    )
    frequency: Mapped[str] = mapped_column(
        String(100),
        nullable=False,
    )
    route: Mapped[MedicationRoute] = mapped_column(
        Enum(MedicationRoute, name="medication_route_enum", create_constraint=True),
        nullable=False,
        default=MedicationRoute.ORAL,
    )
    prescribed_by: Mapped[Optional[str]] = mapped_column(
        String(100),
        nullable=True,
    )
    prescription_date: Mapped[Optional[date]] = mapped_column(
        Date,
        nullable=True,
    )
    expiration_date: Mapped[Optional[date]] = mapped_column(
        Date,
        nullable=True,
        index=True,
    )
    purpose: Mapped[Optional[str]] = mapped_column(
        Text,
        nullable=True,
    )
    side_effects: Mapped[Optional[str]] = mapped_column(
        Text,
        nullable=True,
    )
    storage_location: Mapped[Optional[str]] = mapped_column(
        String(255),
        nullable=True,
    )
    administered_by: Mapped[AdministeredBy] = mapped_column(
        Enum(AdministeredBy, name="administered_by_enum", create_constraint=True),
        nullable=False,
        default=AdministeredBy.STAFF,
    )
    parent_consent: Mapped[bool] = mapped_column(
        Boolean,
        nullable=False,
        default=False,
    )
    parent_consent_date: Mapped[Optional[date]] = mapped_column(
        Date,
        nullable=True,
    )
    is_verified: Mapped[bool] = mapped_column(
        Boolean,
        nullable=False,
        default=False,
    )
    verified_by_id: Mapped[Optional[UUID]] = mapped_column(
        PGUUID(as_uuid=True),
        nullable=True,
    )
    verified_date: Mapped[Optional[date]] = mapped_column(
        Date,
        nullable=True,
    )
    notes: Mapped[Optional[str]] = mapped_column(
        Text,
        nullable=True,
    )
    is_active: Mapped[bool] = mapped_column(
        Boolean,
        nullable=False,
        default=True,
        index=True,
    )
    created_by_id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        nullable=False,
    )
    created_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True),
        nullable=False,
        server_default=func.now(),
    )
    updated_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True),
        nullable=False,
        server_default=func.now(),
        onupdate=func.now(),
    )

    # Relationships
    alerts: Mapped[list["MedicalAlert"]] = relationship(
        "MedicalAlert",
        back_populates="related_medication",
        foreign_keys="MedicalAlert.related_medication_id",
        cascade="all, delete-orphan",
    )

    def __repr__(self) -> str:
        """Return string representation of the Medication."""
        return (
            f"<Medication(id={self.id}, child_id={self.child_id}, "
            f"name='{self.medication_name}', type={self.medication_type.value})>"
        )


class AccommodationPlan(Base):
    """SQLAlchemy model for child accommodation plans.

    Represents an accommodation plan for a child, including dietary
    substitutions, emergency procedures, and staff notification requirements.

    Attributes:
        id: Unique identifier for the plan
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
        approved_by_id: ID of staff who approved the plan
        approved_date: Date plan was approved
        status: Current status of the plan
        notes: Additional notes
        created_by_id: ID of user who created the record
        created_at: Timestamp when the record was created
        updated_at: Timestamp when the record was last updated
    """

    __tablename__ = "medical_accommodation_plans"

    id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        primary_key=True,
        default=uuid4,
    )
    child_id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        nullable=False,
        index=True,
    )
    school_year_id: Mapped[Optional[UUID]] = mapped_column(
        PGUUID(as_uuid=True),
        nullable=True,
        index=True,
    )
    plan_type: Mapped[AccommodationPlanType] = mapped_column(
        Enum(
            AccommodationPlanType,
            name="accommodation_plan_type_enum",
            create_constraint=True,
        ),
        nullable=False,
        default=AccommodationPlanType.HEALTH_PLAN,
        index=True,
    )
    plan_name: Mapped[str] = mapped_column(
        String(100),
        nullable=False,
    )
    description: Mapped[str] = mapped_column(
        Text,
        nullable=False,
    )
    accommodations: Mapped[str] = mapped_column(
        Text,
        nullable=False,
    )
    emergency_procedures: Mapped[Optional[str]] = mapped_column(
        Text,
        nullable=True,
    )
    triggers_signs: Mapped[Optional[str]] = mapped_column(
        Text,
        nullable=True,
    )
    staff_notifications: Mapped[Optional[str]] = mapped_column(
        Text,
        nullable=True,
    )
    document_path: Mapped[Optional[str]] = mapped_column(
        String(255),
        nullable=True,
    )
    effective_date: Mapped[date] = mapped_column(
        Date,
        nullable=False,
        index=True,
    )
    expiration_date: Mapped[Optional[date]] = mapped_column(
        Date,
        nullable=True,
    )
    review_date: Mapped[Optional[date]] = mapped_column(
        Date,
        nullable=True,
    )
    approved_by_id: Mapped[Optional[UUID]] = mapped_column(
        PGUUID(as_uuid=True),
        nullable=True,
    )
    approved_date: Mapped[Optional[date]] = mapped_column(
        Date,
        nullable=True,
    )
    status: Mapped[AccommodationPlanStatus] = mapped_column(
        Enum(
            AccommodationPlanStatus,
            name="accommodation_plan_status_enum",
            create_constraint=True,
        ),
        nullable=False,
        default=AccommodationPlanStatus.DRAFT,
        index=True,
    )
    notes: Mapped[Optional[str]] = mapped_column(
        Text,
        nullable=True,
    )
    created_by_id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        nullable=False,
    )
    created_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True),
        nullable=False,
        server_default=func.now(),
    )
    updated_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True),
        nullable=False,
        server_default=func.now(),
        onupdate=func.now(),
    )

    # Relationships
    alerts: Mapped[list["MedicalAlert"]] = relationship(
        "MedicalAlert",
        back_populates="related_plan",
        foreign_keys="MedicalAlert.related_plan_id",
        cascade="all, delete-orphan",
    )

    def __repr__(self) -> str:
        """Return string representation of the AccommodationPlan."""
        return (
            f"<AccommodationPlan(id={self.id}, child_id={self.child_id}, "
            f"name='{self.plan_name}', status={self.status.value})>"
        )


class MedicalAlert(Base):
    """SQLAlchemy model for medical alerts.

    Represents a medical alert for a child, including display settings,
    notification preferences, and links to related medical records.

    Attributes:
        id: Unique identifier for the alert
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
        is_active: Whether the alert is active
        created_by_id: ID of user who created the record
        created_at: Timestamp when the record was created
        updated_at: Timestamp when the record was last updated
    """

    __tablename__ = "medical_alerts"

    id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        primary_key=True,
        default=uuid4,
    )
    child_id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        nullable=False,
        index=True,
    )
    alert_type: Mapped[AlertType] = mapped_column(
        Enum(AlertType, name="alert_type_enum", create_constraint=True),
        nullable=False,
        index=True,
    )
    alert_level: Mapped[AlertLevel] = mapped_column(
        Enum(AlertLevel, name="alert_level_enum", create_constraint=True),
        nullable=False,
        default=AlertLevel.WARNING,
        index=True,
    )
    title: Mapped[str] = mapped_column(
        String(100),
        nullable=False,
    )
    description: Mapped[str] = mapped_column(
        Text,
        nullable=False,
    )
    action_required: Mapped[Optional[str]] = mapped_column(
        Text,
        nullable=True,
    )
    display_on_dashboard: Mapped[bool] = mapped_column(
        Boolean,
        nullable=False,
        default=True,
        index=True,
    )
    display_on_attendance: Mapped[bool] = mapped_column(
        Boolean,
        nullable=False,
        default=True,
    )
    display_on_reports: Mapped[bool] = mapped_column(
        Boolean,
        nullable=False,
        default=False,
    )
    notify_on_check_in: Mapped[bool] = mapped_column(
        Boolean,
        nullable=False,
        default=False,
    )
    related_allergy_id: Mapped[Optional[UUID]] = mapped_column(
        PGUUID(as_uuid=True),
        ForeignKey("medical_allergies.id", ondelete="SET NULL"),
        nullable=True,
        index=True,
    )
    related_medication_id: Mapped[Optional[UUID]] = mapped_column(
        PGUUID(as_uuid=True),
        ForeignKey("medical_medications.id", ondelete="SET NULL"),
        nullable=True,
        index=True,
    )
    related_plan_id: Mapped[Optional[UUID]] = mapped_column(
        PGUUID(as_uuid=True),
        ForeignKey("medical_accommodation_plans.id", ondelete="SET NULL"),
        nullable=True,
        index=True,
    )
    effective_date: Mapped[Optional[date]] = mapped_column(
        Date,
        nullable=True,
    )
    expiration_date: Mapped[Optional[date]] = mapped_column(
        Date,
        nullable=True,
    )
    is_active: Mapped[bool] = mapped_column(
        Boolean,
        nullable=False,
        default=True,
        index=True,
    )
    created_by_id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        nullable=False,
    )
    created_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True),
        nullable=False,
        server_default=func.now(),
    )
    updated_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True),
        nullable=False,
        server_default=func.now(),
        onupdate=func.now(),
    )

    # Relationships
    related_allergy: Mapped[Optional["Allergy"]] = relationship(
        "Allergy",
        back_populates="alerts",
        foreign_keys=[related_allergy_id],
    )
    related_medication: Mapped[Optional["Medication"]] = relationship(
        "Medication",
        back_populates="alerts",
        foreign_keys=[related_medication_id],
    )
    related_plan: Mapped[Optional["AccommodationPlan"]] = relationship(
        "AccommodationPlan",
        back_populates="alerts",
        foreign_keys=[related_plan_id],
    )

    def __repr__(self) -> str:
        """Return string representation of the MedicalAlert."""
        return (
            f"<MedicalAlert(id={self.id}, child_id={self.child_id}, "
            f"type={self.alert_type.value}, level={self.alert_level.value})>"
        )
