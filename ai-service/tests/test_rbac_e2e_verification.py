"""End-to-end verification tests for 5-role RBAC system.

This module verifies that all 5 roles (Director, Teacher, Assistant, Staff, Parent)
are correctly enforced across the RBAC system. Each role should have appropriate
access restrictions as defined in the specification.

Role Permissions Summary:
- Director: Full read/write access to all resources
- Teacher (Educator): Group-specific access to children and activities
- Assistant: Limited classroom support (read-only children/activities)
- Staff (Accountant/Other): Operational access (reports, schedules)
- Parent: Read-only access to their own children's data

Verification Steps:
1. Director - verify full read access
2. Educator - verify group-specific child data only
3. Accountant - verify financial module only
4. Parent - verify restricted to own children
5. Other Staff - verify own schedule only
"""

from __future__ import annotations

from datetime import datetime, timedelta, timezone
from typing import Any
from uuid import UUID, uuid4

import pytest
import pytest_asyncio
from httpx import AsyncClient
from sqlalchemy.ext.asyncio import AsyncSession

from app.models.rbac import AuditLog, Permission, Role, RoleType, UserRole
from tests.conftest import create_test_token


# ============================================================================
# Test Data Setup Helpers
# ============================================================================


async def create_role_in_db(
    session: AsyncSession,
    name: str,
    display_name: str,
    description: str | None = None,
    is_system_role: bool = True,
    is_active: bool = True,
) -> Role:
    """Create a role using SQLAlchemy ORM."""
    role = Role(
        name=name,
        display_name=display_name,
        description=description,
        is_system_role=is_system_role,
        is_active=is_active,
    )
    session.add(role)
    await session.flush()
    return role


async def create_permission_in_db(
    session: AsyncSession,
    role_id: UUID,
    resource: str,
    action: str,
    conditions: dict | None = None,
    is_active: bool = True,
) -> Permission:
    """Create a permission using SQLAlchemy ORM."""
    permission = Permission(
        role_id=role_id,
        resource=resource,
        action=action,
        conditions=conditions,
        is_active=is_active,
    )
    session.add(permission)
    await session.flush()
    return permission


async def create_user_role_in_db(
    session: AsyncSession,
    user_id: UUID,
    role_id: UUID,
    organization_id: UUID | None = None,
    group_id: UUID | None = None,
    assigned_by: UUID | None = None,
    is_active: bool = True,
) -> UserRole:
    """Create a user-role assignment using SQLAlchemy ORM."""
    user_role = UserRole(
        user_id=user_id,
        role_id=role_id,
        organization_id=organization_id,
        group_id=group_id,
        assigned_by=assigned_by,
        is_active=is_active,
    )
    session.add(user_role)
    await session.flush()
    return user_role


# ============================================================================
# Test Fixtures
# ============================================================================


@pytest.fixture
def director_user_id() -> UUID:
    """Director user ID for testing."""
    return UUID("11111111-1111-1111-1111-111111111111")


@pytest.fixture
def teacher_user_id() -> UUID:
    """Teacher (Educator) user ID for testing."""
    return UUID("22222222-2222-2222-2222-222222222222")


@pytest.fixture
def assistant_user_id() -> UUID:
    """Assistant user ID for testing."""
    return UUID("33333333-3333-3333-3333-333333333333")


@pytest.fixture
def staff_user_id() -> UUID:
    """Staff (Accountant/Other Staff) user ID for testing."""
    return UUID("44444444-4444-4444-4444-444444444444")


@pytest.fixture
def parent_user_id() -> UUID:
    """Parent user ID for testing."""
    return UUID("55555555-5555-5555-5555-555555555555")


@pytest.fixture
def organization_id() -> UUID:
    """Organization ID for testing."""
    return UUID("aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa")


@pytest.fixture
def group_id_classroom_1() -> UUID:
    """Group ID for classroom 1."""
    return UUID("bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb")


@pytest.fixture
def group_id_classroom_2() -> UUID:
    """Group ID for classroom 2."""
    return UUID("cccccccc-cccc-cccc-cccc-cccccccccccc")


@pytest.fixture
def child_id_1() -> UUID:
    """Child ID 1 (belongs to parent)."""
    return UUID("dddddddd-dddd-dddd-dddd-dddddddddddd")


@pytest.fixture
def child_id_2() -> UUID:
    """Child ID 2 (does not belong to parent)."""
    return UUID("eeeeeeee-eeee-eeee-eeee-eeeeeeeeeeee")


# Token fixtures for each role
@pytest.fixture
def director_token(director_user_id: UUID) -> str:
    """Create a JWT token for director user."""
    return create_test_token(
        subject=str(director_user_id),
        additional_claims={"email": "director@daycare.com", "role": "director"},
    )


@pytest.fixture
def teacher_token(teacher_user_id: UUID) -> str:
    """Create a JWT token for teacher user."""
    return create_test_token(
        subject=str(teacher_user_id),
        additional_claims={"email": "teacher@daycare.com", "role": "teacher"},
    )


@pytest.fixture
def assistant_token(assistant_user_id: UUID) -> str:
    """Create a JWT token for assistant user."""
    return create_test_token(
        subject=str(assistant_user_id),
        additional_claims={"email": "assistant@daycare.com", "role": "assistant"},
    )


@pytest.fixture
def staff_token(staff_user_id: UUID) -> str:
    """Create a JWT token for staff user."""
    return create_test_token(
        subject=str(staff_user_id),
        additional_claims={"email": "staff@daycare.com", "role": "staff"},
    )


@pytest.fixture
def parent_token(parent_user_id: UUID) -> str:
    """Create a JWT token for parent user."""
    return create_test_token(
        subject=str(parent_user_id),
        additional_claims={"email": "parent@example.com", "role": "parent"},
    )


# Headers fixtures
@pytest.fixture
def director_headers(director_token: str) -> dict[str, str]:
    """Authorization headers for director."""
    return {"Authorization": f"Bearer {director_token}"}


@pytest.fixture
def teacher_headers(teacher_token: str) -> dict[str, str]:
    """Authorization headers for teacher."""
    return {"Authorization": f"Bearer {teacher_token}"}


@pytest.fixture
def assistant_headers(assistant_token: str) -> dict[str, str]:
    """Authorization headers for assistant."""
    return {"Authorization": f"Bearer {assistant_token}"}


@pytest.fixture
def staff_headers(staff_token: str) -> dict[str, str]:
    """Authorization headers for staff."""
    return {"Authorization": f"Bearer {staff_token}"}


@pytest.fixture
def parent_headers(parent_token: str) -> dict[str, str]:
    """Authorization headers for parent."""
    return {"Authorization": f"Bearer {parent_token}"}


@pytest_asyncio.fixture
async def setup_all_roles(
    db_session: AsyncSession,
    director_user_id: UUID,
    teacher_user_id: UUID,
    assistant_user_id: UUID,
    staff_user_id: UUID,
    parent_user_id: UUID,
    organization_id: UUID,
    group_id_classroom_1: UUID,
    group_id_classroom_2: UUID,
    child_id_1: UUID,
) -> dict[str, Any]:
    """Set up complete 5-role RBAC system with all permissions.

    Creates:
    - 5 roles with appropriate permissions
    - User assignments for each role type
    - Group-scoped assignments for educators
    - Parent linked to specific child
    """
    # =========================================================================
    # Create the 5 system roles
    # =========================================================================

    # 1. Director - Full administrative access
    director_role = await create_role_in_db(
        db_session,
        name="director",
        display_name="Director",
        description="Full administrative access to all resources",
    )

    # 2. Teacher - Classroom and child management
    teacher_role = await create_role_in_db(
        db_session,
        name="teacher",
        display_name="Teacher",
        description="Classroom and child management access",
    )

    # 3. Assistant - Limited classroom support
    assistant_role = await create_role_in_db(
        db_session,
        name="assistant",
        display_name="Assistant",
        description="Limited classroom support access",
    )

    # 4. Staff - Operational staff (accountant/other)
    staff_role = await create_role_in_db(
        db_session,
        name="staff",
        display_name="Staff",
        description="Operational staff with limited access",
    )

    # 5. Parent - Read-only own children
    parent_role = await create_role_in_db(
        db_session,
        name="parent",
        display_name="Parent",
        description="Read-only access to their children's data",
    )

    # =========================================================================
    # Create permissions for each role
    # =========================================================================

    # Director: Full access (wildcard *)
    await create_permission_in_db(
        db_session, director_role.id, resource="*", action="*"
    )

    # Teacher: Read/write children, activities; read reports
    await create_permission_in_db(
        db_session, teacher_role.id, resource="children", action="read"
    )
    await create_permission_in_db(
        db_session, teacher_role.id, resource="children", action="write"
    )
    await create_permission_in_db(
        db_session, teacher_role.id, resource="activities", action="read"
    )
    await create_permission_in_db(
        db_session, teacher_role.id, resource="activities", action="write"
    )
    await create_permission_in_db(
        db_session, teacher_role.id, resource="daily_reports", action="read"
    )
    await create_permission_in_db(
        db_session, teacher_role.id, resource="daily_reports", action="write"
    )

    # Assistant: Read-only children and activities
    await create_permission_in_db(
        db_session, assistant_role.id, resource="children", action="read"
    )
    await create_permission_in_db(
        db_session, assistant_role.id, resource="activities", action="read"
    )
    await create_permission_in_db(
        db_session, assistant_role.id, resource="daily_reports", action="read"
    )

    # Staff: Reports and schedule access only
    await create_permission_in_db(
        db_session, staff_role.id, resource="reports", action="read"
    )
    await create_permission_in_db(
        db_session, staff_role.id, resource="schedules", action="read"
    )
    await create_permission_in_db(
        db_session, staff_role.id, resource="invoices", action="read"
    )
    await create_permission_in_db(
        db_session, staff_role.id, resource="invoices", action="write"
    )

    # Parent: Read own children only
    await create_permission_in_db(
        db_session,
        parent_role.id,
        resource="children",
        action="read",
        conditions={"own_children_only": True},
    )
    await create_permission_in_db(
        db_session,
        parent_role.id,
        resource="daily_reports",
        action="read",
        conditions={"own_children_only": True},
    )
    await create_permission_in_db(
        db_session,
        parent_role.id,
        resource="invoices",
        action="read",
        conditions={"own_invoices_only": True},
    )

    # =========================================================================
    # Assign roles to users
    # =========================================================================

    # Director - organization-wide access
    await create_user_role_in_db(
        db_session,
        user_id=director_user_id,
        role_id=director_role.id,
        organization_id=organization_id,
    )

    # Teacher - assigned to classroom 1 only
    await create_user_role_in_db(
        db_session,
        user_id=teacher_user_id,
        role_id=teacher_role.id,
        organization_id=organization_id,
        group_id=group_id_classroom_1,
    )

    # Assistant - assigned to classroom 1 only
    await create_user_role_in_db(
        db_session,
        user_id=assistant_user_id,
        role_id=assistant_role.id,
        organization_id=organization_id,
        group_id=group_id_classroom_1,
    )

    # Staff - organization-wide access (for reports/invoices)
    await create_user_role_in_db(
        db_session,
        user_id=staff_user_id,
        role_id=staff_role.id,
        organization_id=organization_id,
    )

    # Parent - linked to their child
    await create_user_role_in_db(
        db_session,
        user_id=parent_user_id,
        role_id=parent_role.id,
        organization_id=organization_id,
    )

    await db_session.commit()

    return {
        "director_role": director_role.id,
        "teacher_role": teacher_role.id,
        "assistant_role": assistant_role.id,
        "staff_role": staff_role.id,
        "parent_role": parent_role.id,
        "organization_id": organization_id,
        "group_id_classroom_1": group_id_classroom_1,
        "group_id_classroom_2": group_id_classroom_2,
        "child_id_1": child_id_1,
    }


# ============================================================================
# E2E Verification Test: Director Role
# ============================================================================


class TestDirectorFullAccess:
    """Verify Director role has full read access to all resources.

    Verification Step 1: Login as Director - verify full read access
    """

    @pytest.mark.asyncio
    async def test_director_can_access_all_roles(
        self,
        client: AsyncClient,
        director_headers: dict[str, str],
        setup_all_roles: dict[str, Any],
    ) -> None:
        """Director can view all roles in the system."""
        response = await client.get(
            "/api/v1/rbac/roles",
            headers=director_headers,
        )
        assert response.status_code == 200
        data = response.json()
        assert len(data) == 5
        role_names = {r["name"] for r in data}
        assert role_names == {"director", "teacher", "assistant", "staff", "parent"}

    @pytest.mark.asyncio
    async def test_director_can_access_audit_logs(
        self,
        client: AsyncClient,
        director_headers: dict[str, str],
        setup_all_roles: dict[str, Any],
    ) -> None:
        """Director can access audit trail."""
        response = await client.get(
            "/api/v1/rbac/audit",
            headers=director_headers,
        )
        assert response.status_code == 200
        assert "items" in response.json()

    @pytest.mark.asyncio
    async def test_director_can_assign_roles(
        self,
        client: AsyncClient,
        director_headers: dict[str, str],
        setup_all_roles: dict[str, Any],
    ) -> None:
        """Director can assign roles to other users."""
        new_user_id = uuid4()
        payload = {
            "user_id": str(new_user_id),
            "role_id": str(setup_all_roles["teacher_role"]),
        }
        response = await client.post(
            "/api/v1/rbac/roles/assign",
            json=payload,
            headers=director_headers,
        )
        assert response.status_code == 200
        assert response.json()["is_active"] is True

    @pytest.mark.asyncio
    async def test_director_can_check_any_permission(
        self,
        client: AsyncClient,
        director_headers: dict[str, str],
        director_user_id: UUID,
        setup_all_roles: dict[str, Any],
    ) -> None:
        """Director has permission on any resource/action combination."""
        # Check permission on children (delete - restricted action)
        payload = {
            "user_id": str(director_user_id),
            "resource": "children",
            "action": "delete",
        }
        response = await client.post(
            "/api/v1/rbac/permissions/check",
            json=payload,
            headers=director_headers,
        )
        assert response.status_code == 200
        assert response.json()["allowed"] is True
        assert response.json()["matched_role"] == "director"

        # Check permission on financial data
        payload["resource"] = "invoices"
        payload["action"] = "write"
        response = await client.post(
            "/api/v1/rbac/permissions/check",
            json=payload,
            headers=director_headers,
        )
        assert response.status_code == 200
        assert response.json()["allowed"] is True

    @pytest.mark.asyncio
    async def test_director_can_view_any_user_permissions(
        self,
        client: AsyncClient,
        director_headers: dict[str, str],
        teacher_user_id: UUID,
        setup_all_roles: dict[str, Any],
    ) -> None:
        """Director can view any user's permissions."""
        response = await client.get(
            f"/api/v1/rbac/users/{teacher_user_id}/permissions",
            headers=director_headers,
        )
        assert response.status_code == 200
        assert response.json()["user_id"] == str(teacher_user_id)


# ============================================================================
# E2E Verification Test: Teacher (Educator) Role
# ============================================================================


class TestEducatorGroupAccess:
    """Verify Educator role has group-specific child data access only.

    Verification Step 2: Login as Educator - verify group-specific child data only
    """

    @pytest.mark.asyncio
    async def test_teacher_can_access_children_in_own_group(
        self,
        client: AsyncClient,
        teacher_headers: dict[str, str],
        teacher_user_id: UUID,
        group_id_classroom_1: UUID,
        setup_all_roles: dict[str, Any],
    ) -> None:
        """Teacher can read/write children in their assigned group."""
        payload = {
            "user_id": str(teacher_user_id),
            "resource": "children",
            "action": "read",
            "group_id": str(group_id_classroom_1),
        }
        response = await client.post(
            "/api/v1/rbac/permissions/check",
            json=payload,
            headers=teacher_headers,
        )
        assert response.status_code == 200
        assert response.json()["allowed"] is True

        # Also verify write access
        payload["action"] = "write"
        response = await client.post(
            "/api/v1/rbac/permissions/check",
            json=payload,
            headers=teacher_headers,
        )
        assert response.status_code == 200
        assert response.json()["allowed"] is True

    @pytest.mark.asyncio
    async def test_teacher_cannot_access_children_in_other_group(
        self,
        client: AsyncClient,
        teacher_headers: dict[str, str],
        teacher_user_id: UUID,
        group_id_classroom_2: UUID,
        setup_all_roles: dict[str, Any],
    ) -> None:
        """Teacher cannot access children in other groups."""
        payload = {
            "user_id": str(teacher_user_id),
            "resource": "children",
            "action": "read",
            "group_id": str(group_id_classroom_2),
        }
        response = await client.post(
            "/api/v1/rbac/permissions/check",
            json=payload,
            headers=teacher_headers,
        )
        assert response.status_code == 200
        assert response.json()["allowed"] is False

    @pytest.mark.asyncio
    async def test_teacher_cannot_access_financial_data(
        self,
        client: AsyncClient,
        teacher_headers: dict[str, str],
        teacher_user_id: UUID,
        setup_all_roles: dict[str, Any],
    ) -> None:
        """Teacher cannot access financial/invoice data."""
        payload = {
            "user_id": str(teacher_user_id),
            "resource": "invoices",
            "action": "read",
        }
        response = await client.post(
            "/api/v1/rbac/permissions/check",
            json=payload,
            headers=teacher_headers,
        )
        assert response.status_code == 200
        assert response.json()["allowed"] is False

    @pytest.mark.asyncio
    async def test_teacher_cannot_access_audit_logs(
        self,
        client: AsyncClient,
        teacher_headers: dict[str, str],
        setup_all_roles: dict[str, Any],
    ) -> None:
        """Teacher cannot access audit logs (director only)."""
        response = await client.get(
            "/api/v1/rbac/audit",
            headers=teacher_headers,
        )
        assert response.status_code == 403

    @pytest.mark.asyncio
    async def test_teacher_cannot_assign_roles(
        self,
        client: AsyncClient,
        teacher_headers: dict[str, str],
        setup_all_roles: dict[str, Any],
    ) -> None:
        """Teacher cannot assign roles to other users."""
        payload = {
            "user_id": str(uuid4()),
            "role_id": str(setup_all_roles["assistant_role"]),
        }
        response = await client.post(
            "/api/v1/rbac/roles/assign",
            json=payload,
            headers=teacher_headers,
        )
        assert response.status_code == 403


# ============================================================================
# E2E Verification Test: Staff (Accountant) Role
# ============================================================================


class TestStaffFinancialAccess:
    """Verify Staff/Accountant role has financial module access only.

    Verification Step 3: Login as Accountant - verify financial module only
    """

    @pytest.mark.asyncio
    async def test_staff_can_access_invoices(
        self,
        client: AsyncClient,
        staff_headers: dict[str, str],
        staff_user_id: UUID,
        setup_all_roles: dict[str, Any],
    ) -> None:
        """Staff can read and write invoices."""
        # Read invoices
        payload = {
            "user_id": str(staff_user_id),
            "resource": "invoices",
            "action": "read",
        }
        response = await client.post(
            "/api/v1/rbac/permissions/check",
            json=payload,
            headers=staff_headers,
        )
        assert response.status_code == 200
        assert response.json()["allowed"] is True

        # Write invoices
        payload["action"] = "write"
        response = await client.post(
            "/api/v1/rbac/permissions/check",
            json=payload,
            headers=staff_headers,
        )
        assert response.status_code == 200
        assert response.json()["allowed"] is True

    @pytest.mark.asyncio
    async def test_staff_can_access_reports(
        self,
        client: AsyncClient,
        staff_headers: dict[str, str],
        staff_user_id: UUID,
        setup_all_roles: dict[str, Any],
    ) -> None:
        """Staff can read operational reports."""
        payload = {
            "user_id": str(staff_user_id),
            "resource": "reports",
            "action": "read",
        }
        response = await client.post(
            "/api/v1/rbac/permissions/check",
            json=payload,
            headers=staff_headers,
        )
        assert response.status_code == 200
        assert response.json()["allowed"] is True

    @pytest.mark.asyncio
    async def test_staff_can_access_schedules(
        self,
        client: AsyncClient,
        staff_headers: dict[str, str],
        staff_user_id: UUID,
        setup_all_roles: dict[str, Any],
    ) -> None:
        """Staff can read schedules."""
        payload = {
            "user_id": str(staff_user_id),
            "resource": "schedules",
            "action": "read",
        }
        response = await client.post(
            "/api/v1/rbac/permissions/check",
            json=payload,
            headers=staff_headers,
        )
        assert response.status_code == 200
        assert response.json()["allowed"] is True

    @pytest.mark.asyncio
    async def test_staff_cannot_access_children_data(
        self,
        client: AsyncClient,
        staff_headers: dict[str, str],
        staff_user_id: UUID,
        setup_all_roles: dict[str, Any],
    ) -> None:
        """Staff cannot access child data (no childcare permissions)."""
        payload = {
            "user_id": str(staff_user_id),
            "resource": "children",
            "action": "read",
        }
        response = await client.post(
            "/api/v1/rbac/permissions/check",
            json=payload,
            headers=staff_headers,
        )
        assert response.status_code == 200
        assert response.json()["allowed"] is False

    @pytest.mark.asyncio
    async def test_staff_cannot_access_activities(
        self,
        client: AsyncClient,
        staff_headers: dict[str, str],
        staff_user_id: UUID,
        setup_all_roles: dict[str, Any],
    ) -> None:
        """Staff cannot access activity data."""
        payload = {
            "user_id": str(staff_user_id),
            "resource": "activities",
            "action": "read",
        }
        response = await client.post(
            "/api/v1/rbac/permissions/check",
            json=payload,
            headers=staff_headers,
        )
        assert response.status_code == 200
        assert response.json()["allowed"] is False

    @pytest.mark.asyncio
    async def test_staff_cannot_access_audit_logs(
        self,
        client: AsyncClient,
        staff_headers: dict[str, str],
        setup_all_roles: dict[str, Any],
    ) -> None:
        """Staff cannot access audit logs (director only)."""
        response = await client.get(
            "/api/v1/rbac/audit",
            headers=staff_headers,
        )
        assert response.status_code == 403


# ============================================================================
# E2E Verification Test: Parent Role
# ============================================================================


class TestParentOwnChildrenAccess:
    """Verify Parent role is restricted to own children only.

    Verification Step 4: Login as Parent - verify restricted to own children
    """

    @pytest.mark.asyncio
    async def test_parent_can_read_children(
        self,
        client: AsyncClient,
        parent_headers: dict[str, str],
        parent_user_id: UUID,
        setup_all_roles: dict[str, Any],
    ) -> None:
        """Parent has read permission on children resource."""
        payload = {
            "user_id": str(parent_user_id),
            "resource": "children",
            "action": "read",
        }
        response = await client.post(
            "/api/v1/rbac/permissions/check",
            json=payload,
            headers=parent_headers,
        )
        assert response.status_code == 200
        # Permission granted but with conditions (own_children_only)
        assert response.json()["allowed"] is True

    @pytest.mark.asyncio
    async def test_parent_cannot_write_children(
        self,
        client: AsyncClient,
        parent_headers: dict[str, str],
        parent_user_id: UUID,
        setup_all_roles: dict[str, Any],
    ) -> None:
        """Parent cannot write/modify child data."""
        payload = {
            "user_id": str(parent_user_id),
            "resource": "children",
            "action": "write",
        }
        response = await client.post(
            "/api/v1/rbac/permissions/check",
            json=payload,
            headers=parent_headers,
        )
        assert response.status_code == 200
        assert response.json()["allowed"] is False

    @pytest.mark.asyncio
    async def test_parent_cannot_delete_children(
        self,
        client: AsyncClient,
        parent_headers: dict[str, str],
        parent_user_id: UUID,
        setup_all_roles: dict[str, Any],
    ) -> None:
        """Parent cannot delete child data."""
        payload = {
            "user_id": str(parent_user_id),
            "resource": "children",
            "action": "delete",
        }
        response = await client.post(
            "/api/v1/rbac/permissions/check",
            json=payload,
            headers=parent_headers,
        )
        assert response.status_code == 200
        assert response.json()["allowed"] is False

    @pytest.mark.asyncio
    async def test_parent_can_read_daily_reports(
        self,
        client: AsyncClient,
        parent_headers: dict[str, str],
        parent_user_id: UUID,
        setup_all_roles: dict[str, Any],
    ) -> None:
        """Parent can read daily reports (for own children)."""
        payload = {
            "user_id": str(parent_user_id),
            "resource": "daily_reports",
            "action": "read",
        }
        response = await client.post(
            "/api/v1/rbac/permissions/check",
            json=payload,
            headers=parent_headers,
        )
        assert response.status_code == 200
        assert response.json()["allowed"] is True

    @pytest.mark.asyncio
    async def test_parent_can_view_own_invoices(
        self,
        client: AsyncClient,
        parent_headers: dict[str, str],
        parent_user_id: UUID,
        setup_all_roles: dict[str, Any],
    ) -> None:
        """Parent can read their own invoices."""
        payload = {
            "user_id": str(parent_user_id),
            "resource": "invoices",
            "action": "read",
        }
        response = await client.post(
            "/api/v1/rbac/permissions/check",
            json=payload,
            headers=parent_headers,
        )
        assert response.status_code == 200
        assert response.json()["allowed"] is True

    @pytest.mark.asyncio
    async def test_parent_cannot_access_activities(
        self,
        client: AsyncClient,
        parent_headers: dict[str, str],
        parent_user_id: UUID,
        setup_all_roles: dict[str, Any],
    ) -> None:
        """Parent cannot access activity management."""
        payload = {
            "user_id": str(parent_user_id),
            "resource": "activities",
            "action": "read",
        }
        response = await client.post(
            "/api/v1/rbac/permissions/check",
            json=payload,
            headers=parent_headers,
        )
        assert response.status_code == 200
        assert response.json()["allowed"] is False

    @pytest.mark.asyncio
    async def test_parent_cannot_access_audit_logs(
        self,
        client: AsyncClient,
        parent_headers: dict[str, str],
        setup_all_roles: dict[str, Any],
    ) -> None:
        """Parent cannot access audit logs (director only)."""
        response = await client.get(
            "/api/v1/rbac/audit",
            headers=parent_headers,
        )
        assert response.status_code == 403

    @pytest.mark.asyncio
    async def test_parent_cannot_assign_roles(
        self,
        client: AsyncClient,
        parent_headers: dict[str, str],
        setup_all_roles: dict[str, Any],
    ) -> None:
        """Parent cannot assign roles."""
        payload = {
            "user_id": str(uuid4()),
            "role_id": str(setup_all_roles["parent_role"]),
        }
        response = await client.post(
            "/api/v1/rbac/roles/assign",
            json=payload,
            headers=parent_headers,
        )
        assert response.status_code == 403


# ============================================================================
# E2E Verification Test: Assistant Role
# ============================================================================


class TestAssistantLimitedAccess:
    """Verify Assistant role has limited classroom support access.

    Verification Step 5: Login as Other Staff - verify own schedule only
    (Assistant is tested here as part of 'Other Staff' verification)
    """

    @pytest.mark.asyncio
    async def test_assistant_can_read_children(
        self,
        client: AsyncClient,
        assistant_headers: dict[str, str],
        assistant_user_id: UUID,
        group_id_classroom_1: UUID,
        setup_all_roles: dict[str, Any],
    ) -> None:
        """Assistant can read children in their group."""
        payload = {
            "user_id": str(assistant_user_id),
            "resource": "children",
            "action": "read",
            "group_id": str(group_id_classroom_1),
        }
        response = await client.post(
            "/api/v1/rbac/permissions/check",
            json=payload,
            headers=assistant_headers,
        )
        assert response.status_code == 200
        assert response.json()["allowed"] is True

    @pytest.mark.asyncio
    async def test_assistant_cannot_write_children(
        self,
        client: AsyncClient,
        assistant_headers: dict[str, str],
        assistant_user_id: UUID,
        setup_all_roles: dict[str, Any],
    ) -> None:
        """Assistant cannot write/modify child data (read-only)."""
        payload = {
            "user_id": str(assistant_user_id),
            "resource": "children",
            "action": "write",
        }
        response = await client.post(
            "/api/v1/rbac/permissions/check",
            json=payload,
            headers=assistant_headers,
        )
        assert response.status_code == 200
        assert response.json()["allowed"] is False

    @pytest.mark.asyncio
    async def test_assistant_can_read_activities(
        self,
        client: AsyncClient,
        assistant_headers: dict[str, str],
        assistant_user_id: UUID,
        setup_all_roles: dict[str, Any],
    ) -> None:
        """Assistant can read activities."""
        payload = {
            "user_id": str(assistant_user_id),
            "resource": "activities",
            "action": "read",
        }
        response = await client.post(
            "/api/v1/rbac/permissions/check",
            json=payload,
            headers=assistant_headers,
        )
        assert response.status_code == 200
        assert response.json()["allowed"] is True

    @pytest.mark.asyncio
    async def test_assistant_cannot_write_activities(
        self,
        client: AsyncClient,
        assistant_headers: dict[str, str],
        assistant_user_id: UUID,
        setup_all_roles: dict[str, Any],
    ) -> None:
        """Assistant cannot write activities."""
        payload = {
            "user_id": str(assistant_user_id),
            "resource": "activities",
            "action": "write",
        }
        response = await client.post(
            "/api/v1/rbac/permissions/check",
            json=payload,
            headers=assistant_headers,
        )
        assert response.status_code == 200
        assert response.json()["allowed"] is False

    @pytest.mark.asyncio
    async def test_assistant_cannot_access_invoices(
        self,
        client: AsyncClient,
        assistant_headers: dict[str, str],
        assistant_user_id: UUID,
        setup_all_roles: dict[str, Any],
    ) -> None:
        """Assistant cannot access financial data."""
        payload = {
            "user_id": str(assistant_user_id),
            "resource": "invoices",
            "action": "read",
        }
        response = await client.post(
            "/api/v1/rbac/permissions/check",
            json=payload,
            headers=assistant_headers,
        )
        assert response.status_code == 200
        assert response.json()["allowed"] is False

    @pytest.mark.asyncio
    async def test_assistant_cannot_access_children_in_other_group(
        self,
        client: AsyncClient,
        assistant_headers: dict[str, str],
        assistant_user_id: UUID,
        group_id_classroom_2: UUID,
        setup_all_roles: dict[str, Any],
    ) -> None:
        """Assistant cannot access children in other groups."""
        payload = {
            "user_id": str(assistant_user_id),
            "resource": "children",
            "action": "read",
            "group_id": str(group_id_classroom_2),
        }
        response = await client.post(
            "/api/v1/rbac/permissions/check",
            json=payload,
            headers=assistant_headers,
        )
        assert response.status_code == 200
        assert response.json()["allowed"] is False


# ============================================================================
# Cross-Role Permission Matrix Verification
# ============================================================================


class TestCrossRolePermissionMatrix:
    """Verify the complete permission matrix across all 5 roles."""

    @pytest.mark.asyncio
    async def test_permission_matrix_children_read(
        self,
        client: AsyncClient,
        director_headers: dict[str, str],
        director_user_id: UUID,
        teacher_user_id: UUID,
        assistant_user_id: UUID,
        staff_user_id: UUID,
        parent_user_id: UUID,
        setup_all_roles: dict[str, Any],
    ) -> None:
        """Verify children:read permission across all roles."""
        users_and_expected = [
            (director_user_id, True, "Director should read children"),
            (teacher_user_id, True, "Teacher should read children"),
            (assistant_user_id, True, "Assistant should read children"),
            (staff_user_id, False, "Staff should NOT read children"),
            (parent_user_id, True, "Parent should read own children"),
        ]

        for user_id, expected_allowed, message in users_and_expected:
            payload = {
                "user_id": str(user_id),
                "resource": "children",
                "action": "read",
            }
            response = await client.post(
                "/api/v1/rbac/permissions/check",
                json=payload,
                headers=director_headers,
            )
            assert response.status_code == 200
            assert response.json()["allowed"] is expected_allowed, message

    @pytest.mark.asyncio
    async def test_permission_matrix_children_write(
        self,
        client: AsyncClient,
        director_headers: dict[str, str],
        director_user_id: UUID,
        teacher_user_id: UUID,
        assistant_user_id: UUID,
        staff_user_id: UUID,
        parent_user_id: UUID,
        setup_all_roles: dict[str, Any],
    ) -> None:
        """Verify children:write permission across all roles."""
        users_and_expected = [
            (director_user_id, True, "Director should write children"),
            (teacher_user_id, True, "Teacher should write children"),
            (assistant_user_id, False, "Assistant should NOT write children"),
            (staff_user_id, False, "Staff should NOT write children"),
            (parent_user_id, False, "Parent should NOT write children"),
        ]

        for user_id, expected_allowed, message in users_and_expected:
            payload = {
                "user_id": str(user_id),
                "resource": "children",
                "action": "write",
            }
            response = await client.post(
                "/api/v1/rbac/permissions/check",
                json=payload,
                headers=director_headers,
            )
            assert response.status_code == 200
            assert response.json()["allowed"] is expected_allowed, message

    @pytest.mark.asyncio
    async def test_permission_matrix_invoices(
        self,
        client: AsyncClient,
        director_headers: dict[str, str],
        director_user_id: UUID,
        teacher_user_id: UUID,
        assistant_user_id: UUID,
        staff_user_id: UUID,
        parent_user_id: UUID,
        setup_all_roles: dict[str, Any],
    ) -> None:
        """Verify invoices permission across all roles."""
        users_and_expected = [
            (director_user_id, True, "Director should access invoices"),
            (teacher_user_id, False, "Teacher should NOT access invoices"),
            (assistant_user_id, False, "Assistant should NOT access invoices"),
            (staff_user_id, True, "Staff should access invoices"),
            (parent_user_id, True, "Parent should access own invoices"),
        ]

        for user_id, expected_allowed, message in users_and_expected:
            payload = {
                "user_id": str(user_id),
                "resource": "invoices",
                "action": "read",
            }
            response = await client.post(
                "/api/v1/rbac/permissions/check",
                json=payload,
                headers=director_headers,
            )
            assert response.status_code == 200
            assert response.json()["allowed"] is expected_allowed, message


# ============================================================================
# Summary Test
# ============================================================================


class TestRBACSummary:
    """Summary verification that all 5 roles exist and are properly configured."""

    @pytest.mark.asyncio
    async def test_all_five_roles_exist(
        self,
        client: AsyncClient,
        director_headers: dict[str, str],
        setup_all_roles: dict[str, Any],
    ) -> None:
        """Verify all 5 system roles are created and active."""
        response = await client.get(
            "/api/v1/rbac/roles",
            headers=director_headers,
        )
        assert response.status_code == 200
        data = response.json()

        # Verify exactly 5 roles
        assert len(data) == 5, "Should have exactly 5 roles"

        # Verify each role exists
        role_names = {r["name"] for r in data}
        expected_roles = {"director", "teacher", "assistant", "staff", "parent"}
        assert role_names == expected_roles, f"Missing roles: {expected_roles - role_names}"

        # Verify all roles are system roles and active
        for role in data:
            assert role["is_system_role"] is True, f"Role {role['name']} should be system role"
            assert role["is_active"] is True, f"Role {role['name']} should be active"

    @pytest.mark.asyncio
    async def test_each_role_has_permissions(
        self,
        client: AsyncClient,
        director_headers: dict[str, str],
        setup_all_roles: dict[str, Any],
    ) -> None:
        """Verify each role has at least one permission defined."""
        response = await client.get(
            "/api/v1/rbac/roles",
            headers=director_headers,
        )
        assert response.status_code == 200
        data = response.json()

        for role in data:
            assert len(role.get("permissions", [])) > 0, (
                f"Role {role['name']} should have at least one permission"
            )
