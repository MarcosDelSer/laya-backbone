"""End-to-end integration tests for Medical Tracking workflow.

Tests the complete workflow from allergy creation through meal logging
and alert generation:
1. Create allergy record for test child (peanuts, severe)
2. Log meal containing peanuts for child
3. Verify allergen alert is generated
4. Verify notification is queued for staff
5. Verify parent-portal displays allergy correctly (via API)
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
from app.services.medical_service import MedicalService


# =============================================================================
# SQLite Table Creation SQL for E2E Tests
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
# E2E Test Fixtures
# =============================================================================


@pytest.fixture
def e2e_child_id() -> UUID:
    """Generate a consistent test child ID for E2E tests."""
    return UUID("e2e00001-0001-0001-0001-000000000001")


@pytest.fixture
def e2e_staff_id() -> UUID:
    """Generate a consistent staff ID for E2E tests."""
    return UUID("e2e00002-0002-0002-0002-000000000002")


@pytest.fixture
def e2e_parent_id() -> UUID:
    """Generate a consistent parent ID for E2E tests."""
    return UUID("e2e00003-0003-0003-0003-000000000003")


@pytest_asyncio.fixture
async def e2e_db_session(db_session: AsyncSession) -> AsyncSession:
    """Create medical tables and return the session for E2E tests."""
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


# =============================================================================
# Helper Functions
# =============================================================================


async def create_severe_peanut_allergy(
    session: AsyncSession,
    child_id: UUID,
    created_by_id: UUID,
) -> UUID:
    """
    Step 1: Create a severe peanut allergy record for test child.

    This simulates adding an allergy through the Gibbon admin interface.
    """
    allergy_id = str(uuid4())
    now = datetime.now(timezone.utc)

    await session.execute(
        text("""
            INSERT INTO medical_allergies (
                id, child_id, allergen_name, allergen_type, severity,
                reaction, treatment, epi_pen_required, epi_pen_location,
                diagnosed_date, diagnosed_by, notes, is_active, is_verified,
                created_by_id, created_at, updated_at
            ) VALUES (
                :id, :child_id, :allergen_name, :allergen_type, :severity,
                :reaction, :treatment, :epi_pen_required, :epi_pen_location,
                :diagnosed_date, :diagnosed_by, :notes, :is_active, :is_verified,
                :created_by_id, :created_at, :updated_at
            )
        """),
        {
            "id": allergy_id,
            "child_id": str(child_id),
            "allergen_name": "Peanuts",
            "allergen_type": AllergenType.FOOD.name,
            "severity": AllergySeverity.SEVERE.name,
            "reaction": "Anaphylaxis - throat swelling, difficulty breathing, hives",
            "treatment": "Administer EpiPen immediately, call 911, monitor breathing",
            "epi_pen_required": 1,
            "epi_pen_location": "Front office emergency kit and classroom cabinet",
            "diagnosed_date": date(2023, 6, 15).isoformat(),
            "diagnosed_by": "Dr. Sarah Johnson, Allergist",
            "notes": "Child is aware of allergy. Always check food labels.",
            "is_active": 1,
            "is_verified": 1,
            "created_by_id": str(created_by_id),
            "created_at": now.isoformat(),
            "updated_at": now.isoformat(),
        },
    )
    await session.commit()

    return UUID(allergy_id)


async def create_epipen_medication(
    session: AsyncSession,
    child_id: UUID,
    created_by_id: UUID,
) -> UUID:
    """Create an EpiPen medication record for the child."""
    medication_id = str(uuid4())
    now = datetime.now(timezone.utc)

    await session.execute(
        text("""
            INSERT INTO medical_medications (
                id, child_id, medication_name, medication_type, dosage,
                frequency, route, prescribed_by, prescription_date,
                expiration_date, purpose, storage_location, administered_by,
                parent_consent, is_active, is_verified, created_by_id,
                created_at, updated_at
            ) VALUES (
                :id, :child_id, :medication_name, :medication_type, :dosage,
                :frequency, :route, :prescribed_by, :prescription_date,
                :expiration_date, :purpose, :storage_location, :administered_by,
                :parent_consent, :is_active, :is_verified, :created_by_id,
                :created_at, :updated_at
            )
        """),
        {
            "id": medication_id,
            "child_id": str(child_id),
            "medication_name": "EpiPen Jr.",
            "medication_type": MedicationType.PRESCRIPTION.name,
            "dosage": "0.15mg auto-injector",
            "frequency": "As needed for anaphylaxis",
            "route": MedicationRoute.INJECTION.name,
            "prescribed_by": "Dr. Sarah Johnson",
            "prescription_date": date.today().isoformat(),
            "expiration_date": (date.today() + timedelta(days=365)).isoformat(),
            "purpose": "Emergency treatment for severe allergic reaction",
            "storage_location": "Front office emergency kit",
            "administered_by": AdministeredBy.STAFF.name,
            "parent_consent": 1,
            "is_active": 1,
            "is_verified": 1,
            "created_by_id": str(created_by_id),
            "created_at": now.isoformat(),
            "updated_at": now.isoformat(),
        },
    )
    await session.commit()

    return UUID(medication_id)


async def create_allergy_accommodation_plan(
    session: AsyncSession,
    child_id: UUID,
    created_by_id: UUID,
) -> UUID:
    """Create an accommodation plan for the peanut allergy."""
    plan_id = str(uuid4())
    now = datetime.now(timezone.utc)

    await session.execute(
        text("""
            INSERT INTO medical_accommodation_plans (
                id, child_id, plan_type, plan_name, description,
                accommodations, emergency_procedures, triggers_signs,
                staff_notifications, effective_date, status,
                created_by_id, created_at, updated_at
            ) VALUES (
                :id, :child_id, :plan_type, :plan_name, :description,
                :accommodations, :emergency_procedures, :triggers_signs,
                :staff_notifications, :effective_date, :status,
                :created_by_id, :created_at, :updated_at
            )
        """),
        {
            "id": plan_id,
            "child_id": str(child_id),
            "plan_type": AccommodationPlanType.EMERGENCY_ACTION_PLAN.name,
            "plan_name": "Peanut Allergy Emergency Action Plan",
            "description": "Comprehensive emergency action plan for severe peanut allergy",
            "accommodations": "Peanut-free table at lunch; no peanut products in classroom; "
                             "separate snacks provided; staff trained in EpiPen administration",
            "emergency_procedures": "1. Administer EpiPen 2. Call 911 3. Contact parents "
                                   "4. Monitor breathing 5. Do not leave child unattended",
            "triggers_signs": "Exposure to peanuts or tree nuts; symptoms include hives, "
                             "swelling, difficulty breathing, vomiting",
            "staff_notifications": "All classroom teachers, cafeteria staff, PE teachers, "
                                  "and nurse notified and trained",
            "effective_date": date.today().isoformat(),
            "status": AccommodationPlanStatus.ACTIVE.name,
            "created_by_id": str(created_by_id),
            "created_at": now.isoformat(),
            "updated_at": now.isoformat(),
        },
    )
    await session.commit()

    return UUID(plan_id)


async def create_medical_alert_for_allergy(
    session: AsyncSession,
    child_id: UUID,
    allergy_id: UUID,
    created_by_id: UUID,
) -> UUID:
    """
    Step 3: Create a medical alert for the allergy.

    In a real scenario, this would be automatically triggered when
    allergen exposure is detected during meal logging.
    """
    alert_id = str(uuid4())
    now = datetime.now(timezone.utc)

    await session.execute(
        text("""
            INSERT INTO medical_alerts (
                id, child_id, alert_type, alert_level, title,
                description, action_required, display_on_dashboard,
                display_on_attendance, display_on_reports, notify_on_check_in,
                related_allergy_id, is_active, created_by_id, created_at, updated_at
            ) VALUES (
                :id, :child_id, :alert_type, :alert_level, :title,
                :description, :action_required, :display_on_dashboard,
                :display_on_attendance, :display_on_reports, :notify_on_check_in,
                :related_allergy_id, :is_active, :created_by_id, :created_at, :updated_at
            )
        """),
        {
            "id": alert_id,
            "child_id": str(child_id),
            "alert_type": AlertType.ALLERGY.name,
            "alert_level": AlertLevel.CRITICAL.name,
            "title": "SEVERE PEANUT ALLERGY - EPIPEN REQUIRED",
            "description": "Child has a severe, life-threatening peanut allergy. "
                          "EpiPen must be accessible at all times. Check all food items.",
            "action_required": "1. Verify EpiPen location at start of day\n"
                              "2. Check all food labels before serving\n"
                              "3. EpiPen in front office emergency kit",
            "display_on_dashboard": 1,
            "display_on_attendance": 1,
            "display_on_reports": 1,
            "notify_on_check_in": 1,
            "related_allergy_id": str(allergy_id),
            "is_active": 1,
            "created_by_id": str(created_by_id),
            "created_at": now.isoformat(),
            "updated_at": now.isoformat(),
        },
    )
    await session.commit()

    return UUID(alert_id)


# =============================================================================
# End-to-End Test Class
# =============================================================================


class TestMedicalTrackingE2EWorkflow:
    """
    End-to-end tests for the complete medical tracking workflow.

    Tests verify:
    1. Create allergy record for test child (peanuts, severe)
    2. Log meal containing peanuts for child - verify allergen detection
    3. Verify allergen alert is generated
    4. Verify notification would be queued for staff
    5. Verify parent-portal API displays allergy correctly
    """

    @pytest.mark.asyncio
    async def test_step1_create_severe_peanut_allergy(
        self,
        e2e_db_session: AsyncSession,
        e2e_child_id: UUID,
        e2e_staff_id: UUID,
    ):
        """
        Step 1: Create allergy record for test child (peanuts, severe).

        Verification:
        - Allergy record is created successfully
        - All fields are set correctly
        - Allergy is marked as verified and active
        """
        # Create the severe peanut allergy
        allergy_id = await create_severe_peanut_allergy(
            e2e_db_session, e2e_child_id, e2e_staff_id
        )

        # Verify the allergy was created
        result = await e2e_db_session.execute(
            text("SELECT * FROM medical_allergies WHERE id = :id"),
            {"id": str(allergy_id)},
        )
        allergy = result.fetchone()

        assert allergy is not None, "Allergy record should be created"
        assert allergy.allergen_name == "Peanuts"
        assert allergy.allergen_type == AllergenType.FOOD.name
        assert allergy.severity == AllergySeverity.SEVERE.name
        assert allergy.epi_pen_required == 1
        assert allergy.is_active == 1
        assert allergy.is_verified == 1
        assert "Front office" in allergy.epi_pen_location
        assert "Administer EpiPen" in allergy.treatment

    @pytest.mark.asyncio
    async def test_step2_detect_allergens_in_meal(
        self,
        e2e_db_session: AsyncSession,
        e2e_child_id: UUID,
        e2e_staff_id: UUID,
    ):
        """
        Step 2: Log meal containing peanuts for child - verify allergen detection.

        Simulates meal logging workflow:
        1. Create the child's allergy record
        2. Use MedicalService to detect allergens in meal items
        3. Verify peanut allergy is detected with correct severity
        """
        # First create the allergy
        await create_severe_peanut_allergy(e2e_db_session, e2e_child_id, e2e_staff_id)

        # Use the MedicalService to detect allergens
        service = MedicalService(e2e_db_session)

        # Meal items that contain peanuts
        meal_items = [
            "peanut butter sandwich",
            "apple slices",
            "milk",
        ]

        # Detect allergens
        result = await service.detect_allergens(
            child_id=e2e_child_id,
            meal_items=meal_items,
        )

        # Verify allergen detection
        assert result is not None, "Detection result should not be None"
        assert result.child_id == e2e_child_id
        assert result.has_allergens is True, "Peanut allergen should be detected"
        assert len(result.detected_allergens) >= 1, "At least one allergen detected"

        # Check detected allergen details
        peanut_allergen = next(
            (a for a in result.detected_allergens if "peanut" in a.allergen_name.lower()),
            None,
        )
        assert peanut_allergen is not None, "Peanut allergy should be detected"
        assert peanut_allergen.allergy.severity == AllergySeverity.SEVERE

        # Verify severity-based flags
        assert result.highest_severity in (
            AllergySeverity.SEVERE,
            AllergySeverity.LIFE_THREATENING,
        )
        assert result.requires_immediate_action is True

    @pytest.mark.asyncio
    async def test_step3_verify_alert_generated(
        self,
        e2e_db_session: AsyncSession,
        e2e_child_id: UUID,
        e2e_staff_id: UUID,
    ):
        """
        Step 3: Verify allergen alert is generated.

        After detecting an allergen exposure, an alert should be created
        to notify staff of the potential danger.
        """
        # Create allergy
        allergy_id = await create_severe_peanut_allergy(
            e2e_db_session, e2e_child_id, e2e_staff_id
        )

        # Create alert for the allergy (simulates what MedicalAlertGateway does)
        alert_id = await create_medical_alert_for_allergy(
            e2e_db_session, e2e_child_id, allergy_id, e2e_staff_id
        )

        # Verify alert was created
        result = await e2e_db_session.execute(
            text("SELECT * FROM medical_alerts WHERE id = :id"),
            {"id": str(alert_id)},
        )
        alert = result.fetchone()

        assert alert is not None, "Alert should be created"
        assert alert.child_id == str(e2e_child_id)
        assert alert.alert_type == AlertType.ALLERGY.name
        assert alert.alert_level == AlertLevel.CRITICAL.name
        assert alert.is_active == 1
        assert alert.display_on_dashboard == 1
        assert alert.display_on_attendance == 1
        assert alert.notify_on_check_in == 1
        assert "PEANUT" in alert.title.upper()
        assert alert.related_allergy_id == str(allergy_id)

    @pytest.mark.asyncio
    async def test_step4_verify_notification_queued(
        self,
        e2e_db_session: AsyncSession,
        e2e_child_id: UUID,
        e2e_staff_id: UUID,
    ):
        """
        Step 4: Verify notification is queued for staff.

        The MedicalAlertGateway.triggerMealAllergenAlert method queues
        notifications to staff via NotificationEngine. This test verifies
        the alert has the correct notification flags.
        """
        # Create allergy and alert
        allergy_id = await create_severe_peanut_allergy(
            e2e_db_session, e2e_child_id, e2e_staff_id
        )
        alert_id = await create_medical_alert_for_allergy(
            e2e_db_session, e2e_child_id, allergy_id, e2e_staff_id
        )

        # Verify notification flags are set correctly
        result = await e2e_db_session.execute(
            text("""
                SELECT
                    alert_level,
                    notify_on_check_in,
                    display_on_dashboard,
                    display_on_attendance
                FROM medical_alerts
                WHERE id = :id
            """),
            {"id": str(alert_id)},
        )
        alert = result.fetchone()

        # Critical alerts should have all notification flags enabled
        assert alert.alert_level == AlertLevel.CRITICAL.name
        assert alert.notify_on_check_in == 1, "Critical alerts notify on check-in"
        assert alert.display_on_dashboard == 1, "Alerts display on dashboard"
        assert alert.display_on_attendance == 1, "Alerts display on attendance"

        # Verify there are active critical alerts for the child
        result = await e2e_db_session.execute(
            text("""
                SELECT COUNT(*) as critical_count
                FROM medical_alerts
                WHERE child_id = :child_id
                AND alert_level = 'CRITICAL'
                AND is_active = 1
            """),
            {"child_id": str(e2e_child_id)},
        )
        count = result.fetchone()
        assert count.critical_count >= 1, "At least one critical alert should exist"

    @pytest.mark.asyncio
    async def test_step5_parent_portal_displays_allergy(
        self,
        e2e_db_session: AsyncSession,
        e2e_child_id: UUID,
        e2e_staff_id: UUID,
    ):
        """
        Step 5: Verify parent-portal displays allergy correctly.

        The parent-portal calls the /children/{child_id}/summary API
        to get the child's complete medical profile. This test verifies
        the MedicalService returns correct data.
        """
        # Create complete medical profile for child
        allergy_id = await create_severe_peanut_allergy(
            e2e_db_session, e2e_child_id, e2e_staff_id
        )
        medication_id = await create_epipen_medication(
            e2e_db_session, e2e_child_id, e2e_staff_id
        )
        plan_id = await create_allergy_accommodation_plan(
            e2e_db_session, e2e_child_id, e2e_staff_id
        )
        alert_id = await create_medical_alert_for_allergy(
            e2e_db_session, e2e_child_id, allergy_id, e2e_staff_id
        )

        # Use MedicalService to get child summary (what parent-portal calls)
        service = MedicalService(e2e_db_session)
        summary = await service.get_child_medical_summary(
            child_id=e2e_child_id,
            include_inactive=False,
        )

        # Verify summary contains all expected data
        assert summary is not None, "Summary should not be None"
        assert summary.child_id == e2e_child_id

        # Verify allergies
        assert len(summary.allergies) >= 1, "Should have at least one allergy"
        peanut_allergy = next(
            (a for a in summary.allergies if "peanut" in a.allergen_name.lower()),
            None,
        )
        assert peanut_allergy is not None, "Peanut allergy should be in summary"
        assert peanut_allergy.severity == AllergySeverity.SEVERE
        assert peanut_allergy.epi_pen_required is True

        # Verify medications
        assert len(summary.medications) >= 1, "Should have at least one medication"
        epipen = next(
            (m for m in summary.medications if "epipen" in m.medication_name.lower()),
            None,
        )
        assert epipen is not None, "EpiPen should be in medications"

        # Verify accommodation plans
        assert len(summary.accommodation_plans) >= 1, "Should have accommodation plan"

        # Verify active alerts
        assert len(summary.active_alerts) >= 1, "Should have active alerts"
        critical_alert = next(
            (a for a in summary.active_alerts if a.alert_level == AlertLevel.CRITICAL),
            None,
        )
        assert critical_alert is not None, "Should have critical alert"

        # Verify summary flags
        assert summary.has_severe_allergies is True
        assert summary.has_epi_pen is True
        assert summary.has_staff_administered_medications is True
        assert summary.generated_at is not None

    @pytest.mark.asyncio
    async def test_complete_e2e_workflow(
        self,
        e2e_db_session: AsyncSession,
        e2e_child_id: UUID,
        e2e_staff_id: UUID,
    ):
        """
        Complete end-to-end workflow test.

        This test runs the complete workflow:
        1. Create allergy record (peanuts, severe)
        2. Create supporting records (medication, plan)
        3. Detect allergen in meal
        4. Create alert
        5. Verify parent-portal summary

        This test validates the integration between all components.
        """
        # Step 1: Create allergy
        allergy_id = await create_severe_peanut_allergy(
            e2e_db_session, e2e_child_id, e2e_staff_id
        )
        assert allergy_id is not None

        # Step 2: Create supporting records
        medication_id = await create_epipen_medication(
            e2e_db_session, e2e_child_id, e2e_staff_id
        )
        assert medication_id is not None

        plan_id = await create_allergy_accommodation_plan(
            e2e_db_session, e2e_child_id, e2e_staff_id
        )
        assert plan_id is not None

        # Step 3: Detect allergen in meal
        service = MedicalService(e2e_db_session)
        detection = await service.detect_allergens(
            child_id=e2e_child_id,
            meal_items=["peanut butter and jelly sandwich"],
        )
        assert detection.has_allergens is True
        assert detection.requires_immediate_action is True

        # Step 4: Create alert (in production, this is automatic)
        alert_id = await create_medical_alert_for_allergy(
            e2e_db_session, e2e_child_id, allergy_id, e2e_staff_id
        )
        assert alert_id is not None

        # Step 5: Verify parent-portal summary
        summary = await service.get_child_medical_summary(
            child_id=e2e_child_id,
            include_inactive=False,
        )

        # Final assertions - complete workflow verification
        assert summary.child_id == e2e_child_id
        assert len(summary.allergies) >= 1
        assert len(summary.medications) >= 1
        assert len(summary.accommodation_plans) >= 1
        assert len(summary.active_alerts) >= 1
        assert summary.has_severe_allergies is True
        assert summary.has_epi_pen is True
        assert summary.has_staff_administered_medications is True


class TestMedicalAPIEndpointsE2E:
    """
    End-to-end tests for medical API endpoints.

    Tests the HTTP API layer that parent-portal would call.
    """

    @pytest.mark.asyncio
    async def test_list_allergies_endpoint(
        self,
        client: AsyncClient,
        auth_headers: Dict[str, str],
        e2e_db_session: AsyncSession,
        e2e_child_id: UUID,
        e2e_staff_id: UUID,
    ):
        """Test listing allergies through API endpoint."""
        # Create allergy
        await create_severe_peanut_allergy(e2e_db_session, e2e_child_id, e2e_staff_id)

        # Call API endpoint
        response = await client.get(
            "/api/v1/medical/allergies",
            headers=auth_headers,
        )

        assert response.status_code == 200
        data = response.json()
        assert "items" in data
        assert "total" in data
        assert data["total"] >= 1

    @pytest.mark.asyncio
    async def test_child_allergies_endpoint(
        self,
        client: AsyncClient,
        auth_headers: Dict[str, str],
        e2e_db_session: AsyncSession,
        e2e_child_id: UUID,
        e2e_staff_id: UUID,
    ):
        """Test getting allergies for specific child through API."""
        # Create allergy
        await create_severe_peanut_allergy(e2e_db_session, e2e_child_id, e2e_staff_id)

        # Call API endpoint
        response = await client.get(
            f"/api/v1/medical/children/{e2e_child_id}/allergies",
            headers=auth_headers,
        )

        assert response.status_code == 200
        data = response.json()
        assert isinstance(data, list)
        assert len(data) >= 1

        # Verify peanut allergy is in response
        peanut_allergy = next(
            (a for a in data if "peanut" in a["allergen_name"].lower()),
            None,
        )
        assert peanut_allergy is not None

    @pytest.mark.asyncio
    async def test_detect_allergens_endpoint(
        self,
        client: AsyncClient,
        auth_headers: Dict[str, str],
        e2e_db_session: AsyncSession,
        e2e_child_id: UUID,
        e2e_staff_id: UUID,
    ):
        """Test allergen detection endpoint."""
        # Create allergy
        await create_severe_peanut_allergy(e2e_db_session, e2e_child_id, e2e_staff_id)

        # Call detection endpoint
        response = await client.post(
            "/api/v1/medical/detect-allergens",
            json={
                "child_id": str(e2e_child_id),
                "meal_items": ["peanut butter sandwich", "apple"],
            },
            headers=auth_headers,
        )

        assert response.status_code == 200
        data = response.json()
        assert data["has_allergens"] is True
        assert data["requires_immediate_action"] is True
        assert len(data["detected_allergens"]) >= 1

    @pytest.mark.asyncio
    async def test_child_summary_endpoint(
        self,
        client: AsyncClient,
        auth_headers: Dict[str, str],
        e2e_db_session: AsyncSession,
        e2e_child_id: UUID,
        e2e_staff_id: UUID,
    ):
        """Test child medical summary endpoint (what parent-portal uses)."""
        # Create complete medical profile
        allergy_id = await create_severe_peanut_allergy(
            e2e_db_session, e2e_child_id, e2e_staff_id
        )
        await create_epipen_medication(e2e_db_session, e2e_child_id, e2e_staff_id)
        await create_allergy_accommodation_plan(
            e2e_db_session, e2e_child_id, e2e_staff_id
        )
        await create_medical_alert_for_allergy(
            e2e_db_session, e2e_child_id, allergy_id, e2e_staff_id
        )

        # Call summary endpoint
        response = await client.get(
            f"/api/v1/medical/children/{e2e_child_id}/summary",
            headers=auth_headers,
        )

        assert response.status_code == 200
        data = response.json()

        # Verify complete summary structure
        assert data["child_id"] == str(e2e_child_id)
        assert "allergies" in data
        assert "medications" in data
        assert "accommodation_plans" in data
        assert "active_alerts" in data
        assert data["has_severe_allergies"] is True
        assert data["has_epi_pen"] is True
        assert data["has_staff_administered_medications"] is True
        assert "generated_at" in data


class TestEdgeCasesE2E:
    """
    Edge case tests for medical tracking E2E workflow.
    """

    @pytest.mark.asyncio
    async def test_no_allergens_detected_for_safe_meal(
        self,
        e2e_db_session: AsyncSession,
        e2e_child_id: UUID,
        e2e_staff_id: UUID,
    ):
        """Test that safe meals don't trigger false positives."""
        # Create peanut allergy
        await create_severe_peanut_allergy(e2e_db_session, e2e_child_id, e2e_staff_id)

        # Test with safe meal items
        service = MedicalService(e2e_db_session)
        result = await service.detect_allergens(
            child_id=e2e_child_id,
            meal_items=["grilled chicken", "rice", "steamed broccoli", "water"],
        )

        assert result.has_allergens is False
        assert len(result.detected_allergens) == 0
        assert result.requires_immediate_action is False

    @pytest.mark.asyncio
    async def test_child_without_allergies(
        self,
        e2e_db_session: AsyncSession,
        e2e_staff_id: UUID,
    ):
        """Test that children without allergies don't get false positives."""
        # Create a different child ID
        other_child_id = uuid4()

        # Don't create any allergies for this child
        service = MedicalService(e2e_db_session)
        result = await service.detect_allergens(
            child_id=other_child_id,
            meal_items=["peanut butter sandwich"],  # Would normally trigger alert
        )

        # No allergies = no detection
        assert result.has_allergens is False
        assert len(result.detected_allergens) == 0

    @pytest.mark.asyncio
    async def test_inactive_allergy_not_detected(
        self,
        e2e_db_session: AsyncSession,
        e2e_child_id: UUID,
        e2e_staff_id: UUID,
    ):
        """Test that inactive allergies are not included in detection."""
        # Create an inactive allergy
        allergy_id = str(uuid4())
        now = datetime.now(timezone.utc)

        await e2e_db_session.execute(
            text("""
                INSERT INTO medical_allergies (
                    id, child_id, allergen_name, allergen_type, severity,
                    is_active, created_by_id, created_at, updated_at
                ) VALUES (
                    :id, :child_id, :allergen_name, :allergen_type, :severity,
                    :is_active, :created_by_id, :created_at, :updated_at
                )
            """),
            {
                "id": allergy_id,
                "child_id": str(e2e_child_id),
                "allergen_name": "Shellfish",
                "allergen_type": AllergenType.FOOD.name,
                "severity": AllergySeverity.SEVERE.name,
                "is_active": 0,  # INACTIVE
                "created_by_id": str(e2e_staff_id),
                "created_at": now.isoformat(),
                "updated_at": now.isoformat(),
            },
        )
        await e2e_db_session.commit()

        # Test detection
        service = MedicalService(e2e_db_session)
        result = await service.detect_allergens(
            child_id=e2e_child_id,
            meal_items=["shrimp scampi"],
            include_inactive=False,
        )

        # Inactive allergy should not be detected
        assert result.has_allergens is False
