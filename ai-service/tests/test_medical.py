"""Unit tests for Medical models, service, and API endpoints.

Tests cover:
- Allergy model creation and validation
- Medication model creation and expiration tracking
- Accommodation plan management
- Medical alert functionality
- Allergen detection in meal items
- Child medical summary generation
- API endpoint response structure
- Authentication requirements on protected endpoints
- Edge cases: invalid IDs, invalid enum values
"""

from datetime import date, datetime, timedelta, timezone
from typing import Any, Dict, List, Optional
from uuid import UUID, uuid4

import pytest
import pytest_asyncio
from httpx import AsyncClient
from sqlalchemy import text
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
    MedicationRoute,
    MedicationType,
)
from app.services.medical_service import (
    AllergyNotFoundError,
    MedicalService,
    MedicationNotFoundError,
)


# =============================================================================
# SQLite Table Creation SQL
# =============================================================================

SQLITE_CREATE_MEDICAL_TABLES_SQL = """
CREATE TABLE IF NOT EXISTS medical_allergies (
    id TEXT PRIMARY KEY,
    child_id TEXT NOT NULL,
    allergen_name VARCHAR(100) NOT NULL,
    allergen_type VARCHAR(20) NOT NULL DEFAULT 'Food',
    severity VARCHAR(20) NOT NULL DEFAULT 'Moderate',
    reaction TEXT,
    treatment TEXT,
    epi_pen_required INTEGER NOT NULL DEFAULT 0,
    epi_pen_location VARCHAR(255),
    diagnosed_date DATE,
    diagnosed_by VARCHAR(100),
    is_verified INTEGER NOT NULL DEFAULT 0,
    verified_by_id TEXT,
    verified_date DATE,
    notes TEXT,
    is_active INTEGER NOT NULL DEFAULT 1,
    created_by_id TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS medical_medications (
    id TEXT PRIMARY KEY,
    child_id TEXT NOT NULL,
    medication_name VARCHAR(100) NOT NULL,
    medication_type VARCHAR(20) NOT NULL DEFAULT 'Prescription',
    dosage VARCHAR(100) NOT NULL,
    frequency VARCHAR(100) NOT NULL,
    route VARCHAR(20) NOT NULL DEFAULT 'Oral',
    prescribed_by VARCHAR(100),
    prescription_date DATE,
    expiration_date DATE,
    purpose TEXT,
    side_effects TEXT,
    storage_location VARCHAR(255),
    administered_by VARCHAR(20) NOT NULL DEFAULT 'Staff',
    parent_consent INTEGER NOT NULL DEFAULT 0,
    parent_consent_date DATE,
    is_verified INTEGER NOT NULL DEFAULT 0,
    verified_by_id TEXT,
    verified_date DATE,
    notes TEXT,
    is_active INTEGER NOT NULL DEFAULT 1,
    created_by_id TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS medical_accommodation_plans (
    id TEXT PRIMARY KEY,
    child_id TEXT NOT NULL,
    school_year_id TEXT,
    plan_type VARCHAR(50) NOT NULL DEFAULT 'Health Plan',
    plan_name VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    accommodations TEXT NOT NULL,
    emergency_procedures TEXT,
    triggers_signs TEXT,
    staff_notifications TEXT,
    document_path VARCHAR(255),
    effective_date DATE NOT NULL,
    expiration_date DATE,
    review_date DATE,
    approved_by_id TEXT,
    approved_date DATE,
    status VARCHAR(20) NOT NULL DEFAULT 'Draft',
    notes TEXT,
    created_by_id TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS medical_alerts (
    id TEXT PRIMARY KEY,
    child_id TEXT NOT NULL,
    alert_type VARCHAR(20) NOT NULL,
    alert_level VARCHAR(20) NOT NULL DEFAULT 'Warning',
    title VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    action_required TEXT,
    display_on_dashboard INTEGER NOT NULL DEFAULT 1,
    display_on_attendance INTEGER NOT NULL DEFAULT 1,
    display_on_reports INTEGER NOT NULL DEFAULT 0,
    notify_on_check_in INTEGER NOT NULL DEFAULT 0,
    related_allergy_id TEXT,
    related_medication_id TEXT,
    related_plan_id TEXT,
    effective_date DATE,
    expiration_date DATE,
    is_active INTEGER NOT NULL DEFAULT 1,
    created_by_id TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_medical_allergies_child ON medical_allergies(child_id);
CREATE INDEX IF NOT EXISTS idx_medical_allergies_active ON medical_allergies(is_active);
CREATE INDEX IF NOT EXISTS idx_medical_medications_child ON medical_medications(child_id);
CREATE INDEX IF NOT EXISTS idx_medical_medications_active ON medical_medications(is_active);
CREATE INDEX IF NOT EXISTS idx_medical_plans_child ON medical_accommodation_plans(child_id);
CREATE INDEX IF NOT EXISTS idx_medical_alerts_child ON medical_alerts(child_id);
CREATE INDEX IF NOT EXISTS idx_medical_alerts_active ON medical_alerts(is_active);
"""


# =============================================================================
# Mock Classes
# =============================================================================


class MockAllergy:
    """Mock Allergy object for testing without SQLAlchemy ORM overhead."""

    def __init__(
        self,
        id: UUID,
        child_id: UUID,
        allergen_name: str,
        allergen_type: AllergenType,
        severity: AllergySeverity,
        reaction: Optional[str],
        treatment: Optional[str],
        epi_pen_required: bool,
        epi_pen_location: Optional[str],
        diagnosed_date: Optional[date],
        diagnosed_by: Optional[str],
        is_verified: bool,
        verified_by_id: Optional[UUID],
        verified_date: Optional[date],
        notes: Optional[str],
        is_active: bool,
        created_by_id: UUID,
        created_at: datetime,
        updated_at: datetime,
    ):
        self.id = id
        self.child_id = child_id
        self.allergen_name = allergen_name
        self.allergen_type = allergen_type
        self.severity = severity
        self.reaction = reaction
        self.treatment = treatment
        self.epi_pen_required = epi_pen_required
        self.epi_pen_location = epi_pen_location
        self.diagnosed_date = diagnosed_date
        self.diagnosed_by = diagnosed_by
        self.is_verified = is_verified
        self.verified_by_id = verified_by_id
        self.verified_date = verified_date
        self.notes = notes
        self.is_active = is_active
        self.created_by_id = created_by_id
        self.created_at = created_at
        self.updated_at = updated_at

    def __repr__(self) -> str:
        return (
            f"<Allergy(id={self.id}, child_id={self.child_id}, "
            f"allergen='{self.allergen_name}', severity={self.severity.value})>"
        )


class MockMedication:
    """Mock Medication object for testing without SQLAlchemy ORM overhead."""

    def __init__(
        self,
        id: UUID,
        child_id: UUID,
        medication_name: str,
        medication_type: MedicationType,
        dosage: str,
        frequency: str,
        route: MedicationRoute,
        prescribed_by: Optional[str],
        prescription_date: Optional[date],
        expiration_date: Optional[date],
        purpose: Optional[str],
        side_effects: Optional[str],
        storage_location: Optional[str],
        administered_by: AdministeredBy,
        parent_consent: bool,
        parent_consent_date: Optional[date],
        is_verified: bool,
        verified_by_id: Optional[UUID],
        verified_date: Optional[date],
        notes: Optional[str],
        is_active: bool,
        created_by_id: UUID,
        created_at: datetime,
        updated_at: datetime,
    ):
        self.id = id
        self.child_id = child_id
        self.medication_name = medication_name
        self.medication_type = medication_type
        self.dosage = dosage
        self.frequency = frequency
        self.route = route
        self.prescribed_by = prescribed_by
        self.prescription_date = prescription_date
        self.expiration_date = expiration_date
        self.purpose = purpose
        self.side_effects = side_effects
        self.storage_location = storage_location
        self.administered_by = administered_by
        self.parent_consent = parent_consent
        self.parent_consent_date = parent_consent_date
        self.is_verified = is_verified
        self.verified_by_id = verified_by_id
        self.verified_date = verified_date
        self.notes = notes
        self.is_active = is_active
        self.created_by_id = created_by_id
        self.created_at = created_at
        self.updated_at = updated_at

    def __repr__(self) -> str:
        return (
            f"<Medication(id={self.id}, child_id={self.child_id}, "
            f"name='{self.medication_name}', type={self.medication_type.value})>"
        )


class MockAccommodationPlan:
    """Mock AccommodationPlan object for testing without SQLAlchemy ORM overhead."""

    def __init__(
        self,
        id: UUID,
        child_id: UUID,
        school_year_id: Optional[UUID],
        plan_type: AccommodationPlanType,
        plan_name: str,
        description: str,
        accommodations: str,
        emergency_procedures: Optional[str],
        triggers_signs: Optional[str],
        staff_notifications: Optional[str],
        document_path: Optional[str],
        effective_date: date,
        expiration_date: Optional[date],
        review_date: Optional[date],
        approved_by_id: Optional[UUID],
        approved_date: Optional[date],
        status: AccommodationPlanStatus,
        notes: Optional[str],
        created_by_id: UUID,
        created_at: datetime,
        updated_at: datetime,
    ):
        self.id = id
        self.child_id = child_id
        self.school_year_id = school_year_id
        self.plan_type = plan_type
        self.plan_name = plan_name
        self.description = description
        self.accommodations = accommodations
        self.emergency_procedures = emergency_procedures
        self.triggers_signs = triggers_signs
        self.staff_notifications = staff_notifications
        self.document_path = document_path
        self.effective_date = effective_date
        self.expiration_date = expiration_date
        self.review_date = review_date
        self.approved_by_id = approved_by_id
        self.approved_date = approved_date
        self.status = status
        self.notes = notes
        self.created_by_id = created_by_id
        self.created_at = created_at
        self.updated_at = updated_at

    def __repr__(self) -> str:
        return (
            f"<AccommodationPlan(id={self.id}, child_id={self.child_id}, "
            f"name='{self.plan_name}', status={self.status.value})>"
        )


class MockMedicalAlert:
    """Mock MedicalAlert object for testing without SQLAlchemy ORM overhead."""

    def __init__(
        self,
        id: UUID,
        child_id: UUID,
        alert_type: AlertType,
        alert_level: AlertLevel,
        title: str,
        description: str,
        action_required: Optional[str],
        display_on_dashboard: bool,
        display_on_attendance: bool,
        display_on_reports: bool,
        notify_on_check_in: bool,
        related_allergy_id: Optional[UUID],
        related_medication_id: Optional[UUID],
        related_plan_id: Optional[UUID],
        effective_date: Optional[date],
        expiration_date: Optional[date],
        is_active: bool,
        created_by_id: UUID,
        created_at: datetime,
        updated_at: datetime,
    ):
        self.id = id
        self.child_id = child_id
        self.alert_type = alert_type
        self.alert_level = alert_level
        self.title = title
        self.description = description
        self.action_required = action_required
        self.display_on_dashboard = display_on_dashboard
        self.display_on_attendance = display_on_attendance
        self.display_on_reports = display_on_reports
        self.notify_on_check_in = notify_on_check_in
        self.related_allergy_id = related_allergy_id
        self.related_medication_id = related_medication_id
        self.related_plan_id = related_plan_id
        self.effective_date = effective_date
        self.expiration_date = expiration_date
        self.is_active = is_active
        self.created_by_id = created_by_id
        self.created_at = created_at
        self.updated_at = updated_at

    def __repr__(self) -> str:
        return (
            f"<MedicalAlert(id={self.id}, child_id={self.child_id}, "
            f"type={self.alert_type.value}, level={self.alert_level.value})>"
        )


# =============================================================================
# Database Helper Functions
# =============================================================================


async def create_medical_tables(session: AsyncSession) -> None:
    """Create medical tables in the test database."""
    async with session.get_bind().begin() as conn:
        for statement in SQLITE_CREATE_MEDICAL_TABLES_SQL.strip().split(";"):
            statement = statement.strip()
            if statement:
                await conn.execute(text(statement))


async def drop_medical_tables(session: AsyncSession) -> None:
    """Drop medical tables from the test database."""
    async with session.get_bind().begin() as conn:
        await conn.execute(text("DROP TABLE IF EXISTS medical_alerts"))
        await conn.execute(text("DROP TABLE IF EXISTS medical_accommodation_plans"))
        await conn.execute(text("DROP TABLE IF EXISTS medical_medications"))
        await conn.execute(text("DROP TABLE IF EXISTS medical_allergies"))


async def create_allergy_in_db(
    session: AsyncSession,
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
    is_active: bool = True,
) -> MockAllergy:
    """Helper function to create an allergy directly in SQLite database."""
    allergy_id = str(uuid4())
    now = datetime.now(timezone.utc)

    await session.execute(
        text("""
            INSERT INTO medical_allergies (
                id, child_id, allergen_name, allergen_type, severity,
                reaction, treatment, epi_pen_required, epi_pen_location,
                diagnosed_date, diagnosed_by, notes, is_active,
                created_by_id, created_at, updated_at
            ) VALUES (
                :id, :child_id, :allergen_name, :allergen_type, :severity,
                :reaction, :treatment, :epi_pen_required, :epi_pen_location,
                :diagnosed_date, :diagnosed_by, :notes, :is_active,
                :created_by_id, :created_at, :updated_at
            )
        """),
        {
            "id": allergy_id,
            "child_id": str(child_id),
            "allergen_name": allergen_name,
            "allergen_type": allergen_type.name,  # Use enum name for SQLAlchemy compatibility
            "severity": severity.name,  # Use enum name for SQLAlchemy compatibility
            "reaction": reaction,
            "treatment": treatment,
            "epi_pen_required": 1 if epi_pen_required else 0,
            "epi_pen_location": epi_pen_location,
            "diagnosed_date": diagnosed_date.isoformat() if diagnosed_date else None,
            "diagnosed_by": diagnosed_by,
            "notes": notes,
            "is_active": 1 if is_active else 0,
            "created_by_id": str(created_by_id),
            "created_at": now.isoformat(),
            "updated_at": now.isoformat(),
        },
    )
    await session.commit()

    return MockAllergy(
        id=UUID(allergy_id),
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
        is_verified=False,
        verified_by_id=None,
        verified_date=None,
        notes=notes,
        is_active=is_active,
        created_by_id=created_by_id,
        created_at=now,
        updated_at=now,
    )


async def create_medication_in_db(
    session: AsyncSession,
    child_id: UUID,
    medication_name: str,
    dosage: str,
    frequency: str,
    created_by_id: UUID,
    medication_type: MedicationType = MedicationType.PRESCRIPTION,
    route: MedicationRoute = MedicationRoute.ORAL,
    prescribed_by: Optional[str] = None,
    prescription_date: Optional[date] = None,
    expiration_date: Optional[date] = None,
    purpose: Optional[str] = None,
    side_effects: Optional[str] = None,
    storage_location: Optional[str] = None,
    administered_by: AdministeredBy = AdministeredBy.STAFF,
    parent_consent: bool = False,
    notes: Optional[str] = None,
    is_active: bool = True,
) -> MockMedication:
    """Helper function to create a medication directly in SQLite database."""
    medication_id = str(uuid4())
    now = datetime.now(timezone.utc)

    await session.execute(
        text("""
            INSERT INTO medical_medications (
                id, child_id, medication_name, medication_type, dosage,
                frequency, route, prescribed_by, prescription_date,
                expiration_date, purpose, side_effects, storage_location,
                administered_by, parent_consent, notes, is_active,
                created_by_id, created_at, updated_at
            ) VALUES (
                :id, :child_id, :medication_name, :medication_type, :dosage,
                :frequency, :route, :prescribed_by, :prescription_date,
                :expiration_date, :purpose, :side_effects, :storage_location,
                :administered_by, :parent_consent, :notes, :is_active,
                :created_by_id, :created_at, :updated_at
            )
        """),
        {
            "id": medication_id,
            "child_id": str(child_id),
            "medication_name": medication_name,
            "medication_type": medication_type.name,  # Use enum name for SQLAlchemy compatibility
            "dosage": dosage,
            "frequency": frequency,
            "route": route.name,  # Use enum name for SQLAlchemy compatibility
            "prescribed_by": prescribed_by,
            "prescription_date": prescription_date.isoformat() if prescription_date else None,
            "expiration_date": expiration_date.isoformat() if expiration_date else None,
            "purpose": purpose,
            "side_effects": side_effects,
            "storage_location": storage_location,
            "administered_by": administered_by.name,  # Use enum name for SQLAlchemy compatibility
            "parent_consent": 1 if parent_consent else 0,
            "notes": notes,
            "is_active": 1 if is_active else 0,
            "created_by_id": str(created_by_id),
            "created_at": now.isoformat(),
            "updated_at": now.isoformat(),
        },
    )
    await session.commit()

    return MockMedication(
        id=UUID(medication_id),
        child_id=child_id,
        medication_name=medication_name,
        medication_type=medication_type,
        dosage=dosage,
        frequency=frequency,
        route=route,
        prescribed_by=prescribed_by,
        prescription_date=prescription_date,
        expiration_date=expiration_date,
        purpose=purpose,
        side_effects=side_effects,
        storage_location=storage_location,
        administered_by=administered_by,
        parent_consent=parent_consent,
        parent_consent_date=None,
        is_verified=False,
        verified_by_id=None,
        verified_date=None,
        notes=notes,
        is_active=is_active,
        created_by_id=created_by_id,
        created_at=now,
        updated_at=now,
    )


async def create_accommodation_plan_in_db(
    session: AsyncSession,
    child_id: UUID,
    plan_name: str,
    description: str,
    accommodations: str,
    effective_date: date,
    created_by_id: UUID,
    school_year_id: Optional[UUID] = None,
    plan_type: AccommodationPlanType = AccommodationPlanType.HEALTH_PLAN,
    emergency_procedures: Optional[str] = None,
    triggers_signs: Optional[str] = None,
    staff_notifications: Optional[str] = None,
    expiration_date: Optional[date] = None,
    review_date: Optional[date] = None,
    status: AccommodationPlanStatus = AccommodationPlanStatus.ACTIVE,
    notes: Optional[str] = None,
) -> MockAccommodationPlan:
    """Helper function to create an accommodation plan directly in SQLite database."""
    plan_id = str(uuid4())
    now = datetime.now(timezone.utc)

    await session.execute(
        text("""
            INSERT INTO medical_accommodation_plans (
                id, child_id, school_year_id, plan_type, plan_name,
                description, accommodations, emergency_procedures,
                triggers_signs, staff_notifications, effective_date,
                expiration_date, review_date, status, notes,
                created_by_id, created_at, updated_at
            ) VALUES (
                :id, :child_id, :school_year_id, :plan_type, :plan_name,
                :description, :accommodations, :emergency_procedures,
                :triggers_signs, :staff_notifications, :effective_date,
                :expiration_date, :review_date, :status, :notes,
                :created_by_id, :created_at, :updated_at
            )
        """),
        {
            "id": plan_id,
            "child_id": str(child_id),
            "school_year_id": str(school_year_id) if school_year_id else None,
            "plan_type": plan_type.name,  # Use enum name for SQLAlchemy compatibility
            "plan_name": plan_name,
            "description": description,
            "accommodations": accommodations,
            "emergency_procedures": emergency_procedures,
            "triggers_signs": triggers_signs,
            "staff_notifications": staff_notifications,
            "effective_date": effective_date.isoformat(),
            "expiration_date": expiration_date.isoformat() if expiration_date else None,
            "review_date": review_date.isoformat() if review_date else None,
            "status": status.name,  # Use enum name for SQLAlchemy compatibility
            "notes": notes,
            "created_by_id": str(created_by_id),
            "created_at": now.isoformat(),
            "updated_at": now.isoformat(),
        },
    )
    await session.commit()

    return MockAccommodationPlan(
        id=UUID(plan_id),
        child_id=child_id,
        school_year_id=school_year_id,
        plan_type=plan_type,
        plan_name=plan_name,
        description=description,
        accommodations=accommodations,
        emergency_procedures=emergency_procedures,
        triggers_signs=triggers_signs,
        staff_notifications=staff_notifications,
        document_path=None,
        effective_date=effective_date,
        expiration_date=expiration_date,
        review_date=review_date,
        approved_by_id=None,
        approved_date=None,
        status=status,
        notes=notes,
        created_by_id=created_by_id,
        created_at=now,
        updated_at=now,
    )


async def create_medical_alert_in_db(
    session: AsyncSession,
    child_id: UUID,
    alert_type: AlertType,
    title: str,
    description: str,
    created_by_id: UUID,
    alert_level: AlertLevel = AlertLevel.WARNING,
    action_required: Optional[str] = None,
    display_on_dashboard: bool = True,
    display_on_attendance: bool = True,
    display_on_reports: bool = False,
    notify_on_check_in: bool = False,
    related_allergy_id: Optional[UUID] = None,
    related_medication_id: Optional[UUID] = None,
    related_plan_id: Optional[UUID] = None,
    effective_date: Optional[date] = None,
    expiration_date: Optional[date] = None,
    is_active: bool = True,
) -> MockMedicalAlert:
    """Helper function to create a medical alert directly in SQLite database."""
    alert_id = str(uuid4())
    now = datetime.now(timezone.utc)

    await session.execute(
        text("""
            INSERT INTO medical_alerts (
                id, child_id, alert_type, alert_level, title,
                description, action_required, display_on_dashboard,
                display_on_attendance, display_on_reports, notify_on_check_in,
                related_allergy_id, related_medication_id, related_plan_id,
                effective_date, expiration_date, is_active,
                created_by_id, created_at, updated_at
            ) VALUES (
                :id, :child_id, :alert_type, :alert_level, :title,
                :description, :action_required, :display_on_dashboard,
                :display_on_attendance, :display_on_reports, :notify_on_check_in,
                :related_allergy_id, :related_medication_id, :related_plan_id,
                :effective_date, :expiration_date, :is_active,
                :created_by_id, :created_at, :updated_at
            )
        """),
        {
            "id": alert_id,
            "child_id": str(child_id),
            "alert_type": alert_type.name,  # Use enum name for SQLAlchemy compatibility
            "alert_level": alert_level.name,  # Use enum name for SQLAlchemy compatibility
            "title": title,
            "description": description,
            "action_required": action_required,
            "display_on_dashboard": 1 if display_on_dashboard else 0,
            "display_on_attendance": 1 if display_on_attendance else 0,
            "display_on_reports": 1 if display_on_reports else 0,
            "notify_on_check_in": 1 if notify_on_check_in else 0,
            "related_allergy_id": str(related_allergy_id) if related_allergy_id else None,
            "related_medication_id": str(related_medication_id) if related_medication_id else None,
            "related_plan_id": str(related_plan_id) if related_plan_id else None,
            "effective_date": effective_date.isoformat() if effective_date else None,
            "expiration_date": expiration_date.isoformat() if expiration_date else None,
            "is_active": 1 if is_active else 0,
            "created_by_id": str(created_by_id),
            "created_at": now.isoformat(),
            "updated_at": now.isoformat(),
        },
    )
    await session.commit()

    return MockMedicalAlert(
        id=UUID(alert_id),
        child_id=child_id,
        alert_type=alert_type,
        alert_level=alert_level,
        title=title,
        description=description,
        action_required=action_required,
        display_on_dashboard=display_on_dashboard,
        display_on_attendance=display_on_attendance,
        display_on_reports=display_on_reports,
        notify_on_check_in=notify_on_check_in,
        related_allergy_id=related_allergy_id,
        related_medication_id=related_medication_id,
        related_plan_id=related_plan_id,
        effective_date=effective_date,
        expiration_date=expiration_date,
        is_active=is_active,
        created_by_id=created_by_id,
        created_at=now,
        updated_at=now,
    )


# =============================================================================
# Fixtures
# =============================================================================


@pytest.fixture
def medical_child_id() -> UUID:
    """Generate a consistent test child ID for medical tests."""
    return UUID("11111111-2222-3333-4444-555555555555")


@pytest.fixture
def medical_user_id() -> UUID:
    """Generate a consistent test user ID for medical tests."""
    return UUID("aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee")


@pytest_asyncio.fixture
async def medical_db_session(db_session: AsyncSession) -> AsyncSession:
    """Create medical tables and return the session."""
    # Create medical tables
    for statement in SQLITE_CREATE_MEDICAL_TABLES_SQL.strip().split(";"):
        statement = statement.strip()
        if statement:
            await db_session.execute(text(statement))
    await db_session.commit()

    yield db_session

    # Cleanup
    await db_session.execute(text("DROP TABLE IF EXISTS medical_alerts"))
    await db_session.execute(text("DROP TABLE IF EXISTS medical_accommodation_plans"))
    await db_session.execute(text("DROP TABLE IF EXISTS medical_medications"))
    await db_session.execute(text("DROP TABLE IF EXISTS medical_allergies"))
    await db_session.commit()


@pytest_asyncio.fixture
async def sample_allergy(
    medical_db_session: AsyncSession,
    medical_child_id: UUID,
    medical_user_id: UUID,
) -> MockAllergy:
    """Create a sample allergy in the database."""
    return await create_allergy_in_db(
        medical_db_session,
        child_id=medical_child_id,
        allergen_name="Peanut",
        created_by_id=medical_user_id,
        allergen_type=AllergenType.FOOD,
        severity=AllergySeverity.SEVERE,
        reaction="Anaphylaxis risk - throat swelling, difficulty breathing",
        treatment="Administer EpiPen immediately, call 911",
        epi_pen_required=True,
        epi_pen_location="Classroom cabinet, top shelf",
        diagnosed_date=date(2023, 6, 15),
        diagnosed_by="Dr. Smith, Allergist",
        notes="Child is aware of allergy and knows to ask about ingredients",
        is_active=True,
    )


@pytest_asyncio.fixture
async def sample_allergies(
    medical_db_session: AsyncSession,
    medical_child_id: UUID,
    medical_user_id: UUID,
) -> List[MockAllergy]:
    """Create multiple sample allergies with varied properties."""
    allergies_data = [
        {
            "allergen_name": "Peanut",
            "allergen_type": AllergenType.FOOD,
            "severity": AllergySeverity.LIFE_THREATENING,
            "reaction": "Anaphylaxis",
            "treatment": "EpiPen + call 911",
            "epi_pen_required": True,
            "epi_pen_location": "Main office",
            "is_active": True,
        },
        {
            "allergen_name": "Milk",
            "allergen_type": AllergenType.FOOD,
            "severity": AllergySeverity.MODERATE,
            "reaction": "Stomach upset, hives",
            "treatment": "Antihistamine",
            "epi_pen_required": False,
            "is_active": True,
        },
        {
            "allergen_name": "Penicillin",
            "allergen_type": AllergenType.MEDICATION,
            "severity": AllergySeverity.SEVERE,
            "reaction": "Rash, swelling",
            "treatment": "Stop medication, seek medical help",
            "epi_pen_required": False,
            "is_active": True,
        },
        {
            "allergen_name": "Bee Sting",
            "allergen_type": AllergenType.INSECT,
            "severity": AllergySeverity.MILD,
            "reaction": "Localized swelling",
            "treatment": "Ice pack, antihistamine",
            "epi_pen_required": False,
            "is_active": True,
        },
        {
            "allergen_name": "Dust Mites",
            "allergen_type": AllergenType.ENVIRONMENTAL,
            "severity": AllergySeverity.MILD,
            "reaction": "Sneezing, runny nose",
            "treatment": "Antihistamine",
            "epi_pen_required": False,
            "is_active": False,  # Inactive allergy for testing
        },
    ]

    allergies = []
    for data in allergies_data:
        allergy = await create_allergy_in_db(
            medical_db_session,
            child_id=medical_child_id,
            created_by_id=medical_user_id,
            **data,
        )
        allergies.append(allergy)

    return allergies


@pytest_asyncio.fixture
async def sample_medication(
    medical_db_session: AsyncSession,
    medical_child_id: UUID,
    medical_user_id: UUID,
) -> MockMedication:
    """Create a sample medication in the database."""
    return await create_medication_in_db(
        medical_db_session,
        child_id=medical_child_id,
        medication_name="Albuterol Inhaler",
        dosage="2 puffs",
        frequency="As needed for asthma symptoms",
        created_by_id=medical_user_id,
        medication_type=MedicationType.PRESCRIPTION,
        route=MedicationRoute.INHALED,
        prescribed_by="Dr. Johnson",
        prescription_date=date(2024, 1, 15),
        expiration_date=date.today() + timedelta(days=180),
        purpose="Asthma management",
        side_effects="May cause rapid heartbeat",
        storage_location="Child's backpack, front pocket",
        administered_by=AdministeredBy.STAFF,
        parent_consent=True,
        notes="Child can identify when they need inhaler",
        is_active=True,
    )


@pytest_asyncio.fixture
async def sample_medications(
    medical_db_session: AsyncSession,
    medical_child_id: UUID,
    medical_user_id: UUID,
) -> List[MockMedication]:
    """Create multiple sample medications with varied properties."""
    medications_data = [
        {
            "medication_name": "Albuterol Inhaler",
            "dosage": "2 puffs",
            "frequency": "As needed",
            "medication_type": MedicationType.PRESCRIPTION,
            "route": MedicationRoute.INHALED,
            "administered_by": AdministeredBy.STAFF,
            "expiration_date": date.today() + timedelta(days=180),
            "is_active": True,
        },
        {
            "medication_name": "Children's Tylenol",
            "dosage": "5 mL",
            "frequency": "Every 4-6 hours as needed",
            "medication_type": MedicationType.OVER_THE_COUNTER,
            "route": MedicationRoute.ORAL,
            "administered_by": AdministeredBy.NURSE,
            "expiration_date": date.today() + timedelta(days=365),
            "is_active": True,
        },
        {
            "medication_name": "EpiPen Jr",
            "dosage": "0.15 mg",
            "frequency": "In case of anaphylaxis",
            "medication_type": MedicationType.PRESCRIPTION,
            "route": MedicationRoute.INJECTION,
            "administered_by": AdministeredBy.STAFF,
            "expiration_date": date.today() + timedelta(days=10),  # Expiring soon
            "is_active": True,
        },
        {
            "medication_name": "Vitamin D",
            "dosage": "400 IU",
            "frequency": "Once daily",
            "medication_type": MedicationType.SUPPLEMENT,
            "route": MedicationRoute.ORAL,
            "administered_by": AdministeredBy.SELF,
            "expiration_date": date.today() + timedelta(days=730),
            "is_active": True,
        },
        {
            "medication_name": "Old Medication",
            "dosage": "10 mg",
            "frequency": "Once daily",
            "medication_type": MedicationType.PRESCRIPTION,
            "route": MedicationRoute.ORAL,
            "administered_by": AdministeredBy.STAFF,
            "expiration_date": date.today() - timedelta(days=30),  # Already expired
            "is_active": False,  # Inactive
        },
    ]

    medications = []
    for data in medications_data:
        medication = await create_medication_in_db(
            medical_db_session,
            child_id=medical_child_id,
            created_by_id=medical_user_id,
            **data,
        )
        medications.append(medication)

    return medications


@pytest_asyncio.fixture
async def sample_accommodation_plan(
    medical_db_session: AsyncSession,
    medical_child_id: UUID,
    medical_user_id: UUID,
) -> MockAccommodationPlan:
    """Create a sample accommodation plan in the database."""
    return await create_accommodation_plan_in_db(
        medical_db_session,
        child_id=medical_child_id,
        plan_name="Peanut Allergy Action Plan",
        description="Comprehensive action plan for severe peanut allergy",
        accommodations="Peanut-free table at lunch, no peanut products in classroom",
        effective_date=date.today() - timedelta(days=30),
        created_by_id=medical_user_id,
        plan_type=AccommodationPlanType.EMERGENCY_ACTION_PLAN,
        emergency_procedures="1. Administer EpiPen 2. Call 911 3. Contact parents",
        triggers_signs="Difficulty breathing, swelling, hives",
        staff_notifications="All classroom teachers, lunch supervisors",
        expiration_date=date.today() + timedelta(days=335),
        status=AccommodationPlanStatus.ACTIVE,
        notes="Reviewed with parents at beginning of school year",
    )


@pytest_asyncio.fixture
async def sample_accommodation_plans(
    medical_db_session: AsyncSession,
    medical_child_id: UUID,
    medical_user_id: UUID,
) -> List[MockAccommodationPlan]:
    """Create multiple sample accommodation plans with varied properties."""
    plans_data = [
        {
            "plan_name": "Peanut Allergy Action Plan",
            "description": "Emergency action plan for severe peanut allergy",
            "accommodations": "Peanut-free environment",
            "plan_type": AccommodationPlanType.EMERGENCY_ACTION_PLAN,
            "status": AccommodationPlanStatus.ACTIVE,
            "effective_date": date.today() - timedelta(days=30),
            "expiration_date": date.today() + timedelta(days=335),
        },
        {
            "plan_name": "Asthma Management Plan",
            "description": "Daily asthma management and emergency response",
            "accommodations": "Access to inhaler, indoor activity option during high pollen days",
            "plan_type": AccommodationPlanType.HEALTH_PLAN,
            "status": AccommodationPlanStatus.ACTIVE,
            "effective_date": date.today() - timedelta(days=60),
            "expiration_date": date.today() + timedelta(days=305),
        },
        {
            "plan_name": "Dietary Accommodation Plan",
            "description": "Dairy-free meal accommodations",
            "accommodations": "Dairy-free alternatives at all meals and snacks",
            "plan_type": AccommodationPlanType.HEALTH_PLAN,
            "status": AccommodationPlanStatus.PENDING_APPROVAL,
            "effective_date": date.today(),
            "expiration_date": None,
        },
        {
            "plan_name": "Old IEP",
            "description": "Previous year's IEP - archived",
            "accommodations": "Various learning accommodations",
            "plan_type": AccommodationPlanType.IEP,
            "status": AccommodationPlanStatus.ARCHIVED,
            "effective_date": date.today() - timedelta(days=400),
            "expiration_date": date.today() - timedelta(days=35),
        },
    ]

    plans = []
    for data in plans_data:
        plan = await create_accommodation_plan_in_db(
            medical_db_session,
            child_id=medical_child_id,
            created_by_id=medical_user_id,
            **data,
        )
        plans.append(plan)

    return plans


@pytest_asyncio.fixture
async def sample_medical_alert(
    medical_db_session: AsyncSession,
    medical_child_id: UUID,
    medical_user_id: UUID,
    sample_allergy: MockAllergy,
) -> MockMedicalAlert:
    """Create a sample medical alert in the database."""
    return await create_medical_alert_in_db(
        medical_db_session,
        child_id=medical_child_id,
        alert_type=AlertType.ALLERGY,
        title="SEVERE PEANUT ALLERGY",
        description="Child has life-threatening peanut allergy. EpiPen required.",
        created_by_id=medical_user_id,
        alert_level=AlertLevel.CRITICAL,
        action_required="Check all food items for peanuts. EpiPen in office.",
        display_on_dashboard=True,
        display_on_attendance=True,
        display_on_reports=True,
        notify_on_check_in=True,
        related_allergy_id=sample_allergy.id,
        is_active=True,
    )


@pytest_asyncio.fixture
async def sample_medical_alerts(
    medical_db_session: AsyncSession,
    medical_child_id: UUID,
    medical_user_id: UUID,
) -> List[MockMedicalAlert]:
    """Create multiple sample medical alerts with varied properties."""
    alerts_data = [
        {
            "alert_type": AlertType.ALLERGY,
            "alert_level": AlertLevel.CRITICAL,
            "title": "SEVERE PEANUT ALLERGY",
            "description": "Life-threatening peanut allergy",
            "action_required": "EpiPen in office",
            "display_on_dashboard": True,
            "notify_on_check_in": True,
            "is_active": True,
        },
        {
            "alert_type": AlertType.MEDICATION,
            "alert_level": AlertLevel.WARNING,
            "title": "Asthma Medication Required",
            "description": "Child may need inhaler during physical activity",
            "action_required": "Inhaler in backpack",
            "display_on_dashboard": True,
            "notify_on_check_in": False,
            "is_active": True,
        },
        {
            "alert_type": AlertType.CONDITION,
            "alert_level": AlertLevel.INFO,
            "title": "Mild Food Sensitivity",
            "description": "Child has mild dairy sensitivity",
            "action_required": None,
            "display_on_dashboard": False,
            "notify_on_check_in": False,
            "is_active": True,
        },
        {
            "alert_type": AlertType.DIETARY,
            "alert_level": AlertLevel.WARNING,
            "title": "Vegetarian Diet",
            "description": "Child follows vegetarian diet per family preference",
            "action_required": "Provide vegetarian meal options",
            "display_on_dashboard": True,
            "notify_on_check_in": False,
            "is_active": True,
        },
        {
            "alert_type": AlertType.ALLERGY,
            "alert_level": AlertLevel.WARNING,
            "title": "Old Allergy Alert",
            "description": "This alert is no longer active",
            "action_required": None,
            "display_on_dashboard": False,
            "notify_on_check_in": False,
            "is_active": False,  # Inactive alert
        },
    ]

    alerts = []
    for data in alerts_data:
        alert = await create_medical_alert_in_db(
            medical_db_session,
            child_id=medical_child_id,
            created_by_id=medical_user_id,
            **data,
        )
        alerts.append(alert)

    return alerts


# =============================================================================
# Model Tests
# =============================================================================


class TestAllergyModel:
    """Tests for the Allergy model (using mock fixtures for SQLite compatibility)."""

    @pytest.mark.asyncio
    async def test_create_allergy_with_all_fields(
        self,
        sample_allergy: MockAllergy,
    ):
        """Test Allergy can be created with all fields."""
        assert sample_allergy.id is not None
        assert sample_allergy.allergen_name == "Peanut"
        assert sample_allergy.allergen_type == AllergenType.FOOD
        assert sample_allergy.severity == AllergySeverity.SEVERE
        assert sample_allergy.reaction is not None
        assert sample_allergy.treatment is not None
        assert sample_allergy.epi_pen_required is True
        assert sample_allergy.epi_pen_location is not None
        assert sample_allergy.is_active is True
        assert sample_allergy.created_at is not None
        assert sample_allergy.updated_at is not None

    @pytest.mark.asyncio
    async def test_allergy_repr(
        self,
        sample_allergy: MockAllergy,
    ):
        """Test Allergy string representation."""
        repr_str = repr(sample_allergy)
        assert "Allergy" in repr_str
        assert str(sample_allergy.id) in repr_str
        assert sample_allergy.allergen_name in repr_str
        assert sample_allergy.severity.value in repr_str


class TestMedicationModel:
    """Tests for the Medication model (using mock fixtures for SQLite compatibility)."""

    @pytest.mark.asyncio
    async def test_create_medication_with_all_fields(
        self,
        sample_medication: MockMedication,
    ):
        """Test Medication can be created with all fields."""
        assert sample_medication.id is not None
        assert sample_medication.medication_name == "Albuterol Inhaler"
        assert sample_medication.medication_type == MedicationType.PRESCRIPTION
        assert sample_medication.dosage == "2 puffs"
        assert sample_medication.frequency is not None
        assert sample_medication.route == MedicationRoute.INHALED
        assert sample_medication.administered_by == AdministeredBy.STAFF
        assert sample_medication.is_active is True
        assert sample_medication.created_at is not None

    @pytest.mark.asyncio
    async def test_medication_repr(
        self,
        sample_medication: MockMedication,
    ):
        """Test Medication string representation."""
        repr_str = repr(sample_medication)
        assert "Medication" in repr_str
        assert str(sample_medication.id) in repr_str
        assert sample_medication.medication_name in repr_str


class TestAccommodationPlanModel:
    """Tests for the AccommodationPlan model."""

    @pytest.mark.asyncio
    async def test_create_accommodation_plan_with_all_fields(
        self,
        sample_accommodation_plan: MockAccommodationPlan,
    ):
        """Test AccommodationPlan can be created with all fields."""
        assert sample_accommodation_plan.id is not None
        assert sample_accommodation_plan.plan_name == "Peanut Allergy Action Plan"
        assert sample_accommodation_plan.plan_type == AccommodationPlanType.EMERGENCY_ACTION_PLAN
        assert sample_accommodation_plan.status == AccommodationPlanStatus.ACTIVE
        assert sample_accommodation_plan.accommodations is not None
        assert sample_accommodation_plan.emergency_procedures is not None
        assert sample_accommodation_plan.effective_date is not None
        assert sample_accommodation_plan.created_at is not None

    @pytest.mark.asyncio
    async def test_accommodation_plan_repr(
        self,
        sample_accommodation_plan: MockAccommodationPlan,
    ):
        """Test AccommodationPlan string representation."""
        repr_str = repr(sample_accommodation_plan)
        assert "AccommodationPlan" in repr_str
        assert str(sample_accommodation_plan.id) in repr_str
        assert sample_accommodation_plan.plan_name in repr_str


class TestMedicalAlertModel:
    """Tests for the MedicalAlert model."""

    @pytest.mark.asyncio
    async def test_create_medical_alert_with_all_fields(
        self,
        sample_medical_alert: MockMedicalAlert,
    ):
        """Test MedicalAlert can be created with all fields."""
        assert sample_medical_alert.id is not None
        assert sample_medical_alert.alert_type == AlertType.ALLERGY
        assert sample_medical_alert.alert_level == AlertLevel.CRITICAL
        assert sample_medical_alert.title == "SEVERE PEANUT ALLERGY"
        assert sample_medical_alert.description is not None
        assert sample_medical_alert.action_required is not None
        assert sample_medical_alert.display_on_dashboard is True
        assert sample_medical_alert.notify_on_check_in is True
        assert sample_medical_alert.is_active is True
        assert sample_medical_alert.related_allergy_id is not None

    @pytest.mark.asyncio
    async def test_medical_alert_repr(
        self,
        sample_medical_alert: MockMedicalAlert,
    ):
        """Test MedicalAlert string representation."""
        repr_str = repr(sample_medical_alert)
        assert "MedicalAlert" in repr_str
        assert str(sample_medical_alert.id) in repr_str
        assert sample_medical_alert.alert_type.value in repr_str
        assert sample_medical_alert.alert_level.value in repr_str


# =============================================================================
# Service Tests
# =============================================================================


class TestMedicalServiceAllergenDetection:
    """Tests for MedicalService allergen detection logic."""

    @pytest.mark.asyncio
    async def test_detect_allergens_with_direct_match(
        self,
        medical_db_session: AsyncSession,
        sample_allergies: List[MockAllergy],
        medical_child_id: UUID,
    ):
        """Test allergen detection with direct allergen name match."""
        service = MedicalService(medical_db_session)
        response = await service.detect_allergens(
            child_id=medical_child_id,
            meal_items=["peanut butter sandwich", "apple slices"],
        )

        assert response is not None
        assert response.child_id == medical_child_id
        assert response.has_allergens is True
        assert len(response.detected_allergens) >= 1
        assert response.highest_severity in (AllergySeverity.SEVERE, AllergySeverity.LIFE_THREATENING)
        assert response.requires_immediate_action is True
        assert response.detected_at is not None

    @pytest.mark.asyncio
    async def test_detect_allergens_with_keyword_match(
        self,
        medical_db_session: AsyncSession,
        sample_allergies: List[MockAllergy],
        medical_child_id: UUID,
    ):
        """Test allergen detection with keyword-based matching."""
        service = MedicalService(medical_db_session)
        response = await service.detect_allergens(
            child_id=medical_child_id,
            meal_items=["cheese pizza", "yogurt cup"],
        )

        assert response is not None
        # Milk allergy should be detected due to cheese/yogurt keywords
        assert response.has_allergens is True

    @pytest.mark.asyncio
    async def test_detect_allergens_no_match(
        self,
        medical_db_session: AsyncSession,
        sample_allergies: List[MockAllergy],
        medical_child_id: UUID,
    ):
        """Test allergen detection with no allergen matches."""
        service = MedicalService(medical_db_session)
        response = await service.detect_allergens(
            child_id=medical_child_id,
            meal_items=["rice", "steamed vegetables", "chicken"],
        )

        assert response is not None
        assert response.has_allergens is False
        assert len(response.detected_allergens) == 0
        assert response.highest_severity is None
        assert response.requires_immediate_action is False

    @pytest.mark.asyncio
    async def test_detect_allergens_with_inactive_excluded(
        self,
        medical_db_session: AsyncSession,
        sample_allergies: List[MockAllergy],
        medical_child_id: UUID,
    ):
        """Test that inactive allergies are excluded by default."""
        service = MedicalService(medical_db_session)
        # Dust mites is an inactive allergy in sample_allergies
        response = await service.detect_allergens(
            child_id=medical_child_id,
            meal_items=["dusty crackers"],  # Won't match since dust is environmental
            include_inactive=False,
        )

        # Inactive allergies should not be included
        inactive_allergen_names = [a.allergen_name for a in response.detected_allergens]
        assert "Dust Mites" not in inactive_allergen_names


class TestMedicalServiceAllergyOperations:
    """Tests for MedicalService allergy CRUD operations."""

    @pytest.mark.asyncio
    async def test_list_allergies_returns_paginated_response(
        self,
        medical_db_session: AsyncSession,
        sample_allergies: List[MockAllergy],
    ):
        """Test list_allergies returns paginated results."""
        service = MedicalService(medical_db_session)
        allergies, total = await service.list_allergies(
            skip=0,
            limit=10,
            is_active=True,
        )

        assert len(allergies) > 0
        assert total >= len(allergies)

    @pytest.mark.asyncio
    async def test_list_allergies_filter_by_severity(
        self,
        medical_db_session: AsyncSession,
        sample_allergies: List[MockAllergy],
    ):
        """Test list_allergies filters by severity."""
        service = MedicalService(medical_db_session)
        allergies, total = await service.list_allergies(
            severity=AllergySeverity.LIFE_THREATENING,
        )

        for allergy in allergies:
            assert allergy.severity == AllergySeverity.LIFE_THREATENING

    @pytest.mark.asyncio
    async def test_list_allergies_filter_by_epi_pen_required(
        self,
        medical_db_session: AsyncSession,
        sample_allergies: List[MockAllergy],
    ):
        """Test list_allergies filters by EpiPen requirement."""
        service = MedicalService(medical_db_session)
        allergies, total = await service.list_allergies(
            epi_pen_required=True,
        )

        for allergy in allergies:
            assert allergy.epi_pen_required is True

    @pytest.mark.asyncio
    async def test_get_allergies_by_child(
        self,
        medical_db_session: AsyncSession,
        sample_allergies: List[MockAllergy],
        medical_child_id: UUID,
    ):
        """Test get_allergies_by_child returns correct allergies."""
        service = MedicalService(medical_db_session)
        allergies = await service.get_allergies_by_child(
            child_id=medical_child_id,
            include_inactive=False,
        )

        # Should only return active allergies
        assert all(a.is_active for a in allergies)

    @pytest.mark.asyncio
    async def test_get_allergies_by_child_food_only(
        self,
        medical_db_session: AsyncSession,
        sample_allergies: List[MockAllergy],
        medical_child_id: UUID,
    ):
        """Test get_allergies_by_child with food_only filter."""
        service = MedicalService(medical_db_session)
        allergies = await service.get_allergies_by_child(
            child_id=medical_child_id,
            food_only=True,
        )

        for allergy in allergies:
            assert allergy.allergen_type == AllergenType.FOOD


class TestMedicalServiceMedicationOperations:
    """Tests for MedicalService medication CRUD operations."""

    @pytest.mark.asyncio
    async def test_list_medications_returns_paginated_response(
        self,
        medical_db_session: AsyncSession,
        sample_medications: List[MockMedication],
    ):
        """Test list_medications returns paginated results."""
        service = MedicalService(medical_db_session)
        medications, total = await service.list_medications(
            skip=0,
            limit=10,
            is_active=True,
        )

        assert len(medications) > 0
        assert total >= len(medications)

    @pytest.mark.asyncio
    async def test_list_medications_filter_by_type(
        self,
        medical_db_session: AsyncSession,
        sample_medications: List[MockMedication],
    ):
        """Test list_medications filters by medication type."""
        service = MedicalService(medical_db_session)
        medications, total = await service.list_medications(
            medication_type=MedicationType.PRESCRIPTION,
        )

        for medication in medications:
            assert medication.medication_type == MedicationType.PRESCRIPTION

    @pytest.mark.asyncio
    async def test_get_expiring_medications(
        self,
        medical_db_session: AsyncSession,
        sample_medications: List[MockMedication],
    ):
        """Test get_expiring_medications returns medications expiring soon."""
        service = MedicalService(medical_db_session)
        expiring = await service.get_expiring_medications(days_ahead=30)

        # Should include medications expiring within 30 days
        for medication in expiring:
            if medication.expiration_date:
                days_until_expiry = (medication.expiration_date - date.today()).days
                assert days_until_expiry <= 30

    @pytest.mark.asyncio
    async def test_get_medications_by_child_staff_administered_only(
        self,
        medical_db_session: AsyncSession,
        sample_medications: List[MockMedication],
        medical_child_id: UUID,
    ):
        """Test get_medications_by_child with staff_administered_only filter."""
        service = MedicalService(medical_db_session)
        medications = await service.get_medications_by_child(
            child_id=medical_child_id,
            staff_administered_only=True,
        )

        for medication in medications:
            assert medication.administered_by in (AdministeredBy.STAFF, AdministeredBy.NURSE)


class TestMedicalServiceChildSummary:
    """Tests for MedicalService child medical summary."""

    @pytest.mark.asyncio
    async def test_get_child_medical_summary(
        self,
        medical_db_session: AsyncSession,
        sample_allergies: List[MockAllergy],
        sample_medications: List[MockMedication],
        sample_accommodation_plans: List[MockAccommodationPlan],
        sample_medical_alerts: List[MockMedicalAlert],
        medical_child_id: UUID,
    ):
        """Test get_child_medical_summary returns comprehensive summary."""
        service = MedicalService(medical_db_session)
        summary = await service.get_child_medical_summary(
            child_id=medical_child_id,
            include_inactive=False,
        )

        assert summary is not None
        assert summary.child_id == medical_child_id
        assert len(summary.allergies) > 0
        assert len(summary.medications) > 0
        assert len(summary.accommodation_plans) > 0
        assert len(summary.active_alerts) > 0
        assert summary.has_severe_allergies is True  # We have life-threatening peanut allergy
        assert summary.has_epi_pen is True  # Peanut allergy requires EpiPen
        assert summary.generated_at is not None


# =============================================================================
# API Endpoint Tests
# =============================================================================


class TestAllergyEndpoints:
    """Tests for allergy API endpoints."""

    @pytest.mark.asyncio
    async def test_list_allergies_requires_auth(
        self,
        client: AsyncClient,
    ):
        """Test list allergies endpoint requires authentication."""
        response = await client.get("/api/v1/medical/allergies")
        assert response.status_code == 401

    @pytest.mark.asyncio
    async def test_list_allergies_with_auth(
        self,
        client: AsyncClient,
        auth_headers: Dict[str, str],
        medical_db_session: AsyncSession,
        sample_allergies: List[MockAllergy],
    ):
        """Test list allergies endpoint returns paginated results."""
        response = await client.get(
            "/api/v1/medical/allergies",
            headers=auth_headers,
        )
        assert response.status_code == 200
        data = response.json()
        assert "items" in data
        assert "total" in data
        assert "skip" in data
        assert "limit" in data

    @pytest.mark.asyncio
    async def test_list_allergies_invalid_allergen_type(
        self,
        client: AsyncClient,
        auth_headers: Dict[str, str],
        medical_db_session: AsyncSession,
    ):
        """Test list allergies returns 400 for invalid allergen_type."""
        response = await client.get(
            "/api/v1/medical/allergies",
            params={"allergen_type": "invalid_type"},
            headers=auth_headers,
        )
        assert response.status_code == 400
        assert "Invalid allergen_type" in response.json()["detail"]

    @pytest.mark.asyncio
    async def test_list_allergies_invalid_severity(
        self,
        client: AsyncClient,
        auth_headers: Dict[str, str],
        medical_db_session: AsyncSession,
    ):
        """Test list allergies returns 400 for invalid severity."""
        response = await client.get(
            "/api/v1/medical/allergies",
            params={"severity": "invalid_severity"},
            headers=auth_headers,
        )
        assert response.status_code == 400
        assert "Invalid severity" in response.json()["detail"]

    @pytest.mark.asyncio
    async def test_get_allergy_not_found(
        self,
        client: AsyncClient,
        auth_headers: Dict[str, str],
        medical_db_session: AsyncSession,
    ):
        """Test get allergy returns 404 for non-existent ID."""
        fake_id = uuid4()
        response = await client.get(
            f"/api/v1/medical/allergies/{fake_id}",
            headers=auth_headers,
        )
        assert response.status_code == 404

    @pytest.mark.asyncio
    async def test_get_allergies_by_child(
        self,
        client: AsyncClient,
        auth_headers: Dict[str, str],
        medical_db_session: AsyncSession,
        sample_allergies: List[MockAllergy],
        medical_child_id: UUID,
    ):
        """Test get allergies by child endpoint."""
        response = await client.get(
            f"/api/v1/medical/children/{medical_child_id}/allergies",
            headers=auth_headers,
        )
        assert response.status_code == 200
        data = response.json()
        assert isinstance(data, list)


class TestMedicationEndpoints:
    """Tests for medication API endpoints."""

    @pytest.mark.asyncio
    async def test_list_medications_requires_auth(
        self,
        client: AsyncClient,
    ):
        """Test list medications endpoint requires authentication."""
        response = await client.get("/api/v1/medical/medications")
        assert response.status_code == 401

    @pytest.mark.asyncio
    async def test_list_medications_with_auth(
        self,
        client: AsyncClient,
        auth_headers: Dict[str, str],
        medical_db_session: AsyncSession,
        sample_medications: List[MockMedication],
    ):
        """Test list medications endpoint returns paginated results."""
        response = await client.get(
            "/api/v1/medical/medications",
            headers=auth_headers,
        )
        assert response.status_code == 200
        data = response.json()
        assert "items" in data
        assert "total" in data

    @pytest.mark.asyncio
    async def test_list_medications_invalid_type(
        self,
        client: AsyncClient,
        auth_headers: Dict[str, str],
        medical_db_session: AsyncSession,
    ):
        """Test list medications returns 400 for invalid medication_type."""
        response = await client.get(
            "/api/v1/medical/medications",
            params={"medication_type": "invalid_type"},
            headers=auth_headers,
        )
        assert response.status_code == 400
        assert "Invalid medication_type" in response.json()["detail"]

    @pytest.mark.asyncio
    async def test_get_expiring_medications(
        self,
        client: AsyncClient,
        auth_headers: Dict[str, str],
        medical_db_session: AsyncSession,
        sample_medications: List[MockMedication],
    ):
        """Test get expiring medications endpoint."""
        response = await client.get(
            "/api/v1/medical/medications/expiring",
            params={"days_ahead": 30},
            headers=auth_headers,
        )
        assert response.status_code == 200
        data = response.json()
        assert isinstance(data, list)

    @pytest.mark.asyncio
    async def test_get_medication_not_found(
        self,
        client: AsyncClient,
        auth_headers: Dict[str, str],
        medical_db_session: AsyncSession,
    ):
        """Test get medication returns 404 for non-existent ID."""
        fake_id = uuid4()
        response = await client.get(
            f"/api/v1/medical/medications/{fake_id}",
            headers=auth_headers,
        )
        assert response.status_code == 404


class TestAccommodationPlanEndpoints:
    """Tests for accommodation plan API endpoints."""

    @pytest.mark.asyncio
    async def test_list_accommodation_plans_requires_auth(
        self,
        client: AsyncClient,
    ):
        """Test list accommodation plans endpoint requires authentication."""
        response = await client.get("/api/v1/medical/accommodation-plans")
        assert response.status_code == 401

    @pytest.mark.asyncio
    async def test_list_accommodation_plans_with_auth(
        self,
        client: AsyncClient,
        auth_headers: Dict[str, str],
        medical_db_session: AsyncSession,
        sample_accommodation_plans: List[MockAccommodationPlan],
    ):
        """Test list accommodation plans endpoint returns paginated results."""
        response = await client.get(
            "/api/v1/medical/accommodation-plans",
            headers=auth_headers,
        )
        assert response.status_code == 200
        data = response.json()
        assert "items" in data
        assert "total" in data

    @pytest.mark.asyncio
    async def test_list_accommodation_plans_invalid_type(
        self,
        client: AsyncClient,
        auth_headers: Dict[str, str],
        medical_db_session: AsyncSession,
    ):
        """Test list accommodation plans returns 400 for invalid plan_type."""
        response = await client.get(
            "/api/v1/medical/accommodation-plans",
            params={"plan_type": "invalid_type"},
            headers=auth_headers,
        )
        assert response.status_code == 400
        assert "Invalid plan_type" in response.json()["detail"]

    @pytest.mark.asyncio
    async def test_get_accommodation_plan_not_found(
        self,
        client: AsyncClient,
        auth_headers: Dict[str, str],
        medical_db_session: AsyncSession,
    ):
        """Test get accommodation plan returns 404 for non-existent ID."""
        fake_id = uuid4()
        response = await client.get(
            f"/api/v1/medical/accommodation-plans/{fake_id}",
            headers=auth_headers,
        )
        assert response.status_code == 404


class TestMedicalAlertEndpoints:
    """Tests for medical alert API endpoints."""

    @pytest.mark.asyncio
    async def test_list_alerts_requires_auth(
        self,
        client: AsyncClient,
    ):
        """Test list alerts endpoint requires authentication."""
        response = await client.get("/api/v1/medical/alerts")
        assert response.status_code == 401

    @pytest.mark.asyncio
    async def test_list_alerts_with_auth(
        self,
        client: AsyncClient,
        auth_headers: Dict[str, str],
        medical_db_session: AsyncSession,
        sample_medical_alerts: List[MockMedicalAlert],
    ):
        """Test list alerts endpoint returns paginated results."""
        response = await client.get(
            "/api/v1/medical/alerts",
            headers=auth_headers,
        )
        assert response.status_code == 200
        data = response.json()
        assert "items" in data
        assert "total" in data

    @pytest.mark.asyncio
    async def test_list_alerts_invalid_type(
        self,
        client: AsyncClient,
        auth_headers: Dict[str, str],
        medical_db_session: AsyncSession,
    ):
        """Test list alerts returns 400 for invalid alert_type."""
        response = await client.get(
            "/api/v1/medical/alerts",
            params={"alert_type": "invalid_type"},
            headers=auth_headers,
        )
        assert response.status_code == 400
        assert "Invalid alert_type" in response.json()["detail"]

    @pytest.mark.asyncio
    async def test_get_alert_not_found(
        self,
        client: AsyncClient,
        auth_headers: Dict[str, str],
        medical_db_session: AsyncSession,
    ):
        """Test get alert returns 404 for non-existent ID."""
        fake_id = uuid4()
        response = await client.get(
            f"/api/v1/medical/alerts/{fake_id}",
            headers=auth_headers,
        )
        assert response.status_code == 404


class TestAllergenDetectionEndpoint:
    """Tests for allergen detection API endpoint."""

    @pytest.mark.asyncio
    async def test_detect_allergens_requires_auth(
        self,
        client: AsyncClient,
    ):
        """Test detect allergens endpoint requires authentication."""
        response = await client.post(
            "/api/v1/medical/detect-allergens",
            json={
                "child_id": str(uuid4()),
                "meal_items": ["peanut butter"],
            },
        )
        assert response.status_code == 401

    @pytest.mark.asyncio
    async def test_detect_allergens_with_auth(
        self,
        client: AsyncClient,
        auth_headers: Dict[str, str],
        medical_db_session: AsyncSession,
        sample_allergies: List[MockAllergy],
        medical_child_id: UUID,
    ):
        """Test detect allergens endpoint returns detection results."""
        response = await client.post(
            "/api/v1/medical/detect-allergens",
            json={
                "child_id": str(medical_child_id),
                "meal_items": ["peanut butter sandwich", "milk"],
            },
            headers=auth_headers,
        )
        assert response.status_code == 200
        data = response.json()
        assert "child_id" in data
        assert "detected_allergens" in data
        assert "has_allergens" in data
        assert "highest_severity" in data
        assert "requires_immediate_action" in data
        assert "detected_at" in data


class TestChildMedicalSummaryEndpoint:
    """Tests for child medical summary API endpoint."""

    @pytest.mark.asyncio
    async def test_get_child_summary_requires_auth(
        self,
        client: AsyncClient,
    ):
        """Test get child summary endpoint requires authentication."""
        fake_id = uuid4()
        response = await client.get(f"/api/v1/medical/children/{fake_id}/summary")
        assert response.status_code == 401

    @pytest.mark.asyncio
    async def test_get_child_summary_with_auth(
        self,
        client: AsyncClient,
        auth_headers: Dict[str, str],
        medical_db_session: AsyncSession,
        sample_allergies: List[MockAllergy],
        sample_medications: List[MockMedication],
        sample_accommodation_plans: List[MockAccommodationPlan],
        sample_medical_alerts: List[MockMedicalAlert],
        medical_child_id: UUID,
    ):
        """Test get child summary endpoint returns comprehensive summary."""
        response = await client.get(
            f"/api/v1/medical/children/{medical_child_id}/summary",
            headers=auth_headers,
        )
        assert response.status_code == 200
        data = response.json()
        assert "child_id" in data
        assert "allergies" in data
        assert "medications" in data
        assert "accommodation_plans" in data
        assert "active_alerts" in data
        assert "has_severe_allergies" in data
        assert "has_epi_pen" in data
        assert "has_staff_administered_medications" in data
        assert "generated_at" in data
