"""Integration tests for RBAC API endpoints.

Tests for role listing, role assignment/revocation, permission checks,
user permissions retrieval, and audit trail access for the 5-role RBAC
system including Director, Teacher, Assistant, Staff, and Parent roles.
"""

from __future__ import annotations

import json
from datetime import datetime, timezone
from typing import Any
from uuid import UUID, uuid4

import pytest
import pytest_asyncio
from httpx import AsyncClient
from sqlalchemy import text
from sqlalchemy.ext.asyncio import AsyncSession

from app.models.rbac import AuditLog, Permission, Role, UserRole
from tests.conftest import create_test_token


# ============================================================================
# Database Helper Functions using ORM
# ============================================================================


async def create_role_in_db(
    session: AsyncSession,
    name: str,
    display_name: str,
    description: str | None = None,
    is_system_role: bool = True,
    is_active: bool = True,
) -> Role:
    """Create a role using SQLAlchemy ORM.

    Args:
        session: Database session
        name: Role name
        display_name: Human-readable role name
        description: Role description
        is_system_role: Whether this is a system role
        is_active: Whether the role is active

    Returns:
        Role: The created role object
    """
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
    """Create a permission using SQLAlchemy ORM.

    Args:
        session: Database session
        role_id: The role ID this permission belongs to
        resource: Resource this permission applies to
        action: Action permitted
        conditions: Optional conditions for fine-grained access
        is_active: Whether the permission is active

    Returns:
        Permission: The created permission object
    """
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
    """Create a user-role assignment using SQLAlchemy ORM.

    Args:
        session: Database session
        user_id: ID of the user
        role_id: ID of the role
        organization_id: Optional organization scope
        group_id: Optional group scope
        assigned_by: ID of the user who made the assignment
        is_active: Whether the assignment is active

    Returns:
        UserRole: The created user-role object
    """
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


async def create_audit_log_in_db(
    session: AsyncSession,
    user_id: UUID,
    action: str,
    resource_type: str,
    resource_id: UUID | None = None,
    details: dict | None = None,
    ip_address: str | None = None,
    user_agent: str | None = None,
) -> AuditLog:
    """Create an audit log entry using SQLAlchemy ORM.

    Args:
        session: Database session
        user_id: ID of the user who performed the action
        action: The action performed
        resource_type: Type of resource affected
        resource_id: Optional ID of the affected resource
        details: Optional JSON details
        ip_address: Optional IP address
        user_agent: Optional user agent string

    Returns:
        AuditLog: The created audit log object
    """
    audit_log = AuditLog(
        user_id=user_id,
        action=action,
        resource_type=resource_type,
        resource_id=resource_id,
        details=details,
        ip_address=ip_address,
        user_agent=user_agent,
    )
    session.add(audit_log)
    await session.flush()
    return audit_log


# ============================================================================
# Test Fixtures
# ============================================================================


@pytest.fixture
def director_user_id() -> UUID:
    """Generate a consistent director user ID for testing.

    Uses the same ID as test_user_id from conftest.py so that
    auth_headers can be used for director tests.
    """
    return UUID("12345678-1234-1234-1234-123456789abc")


@pytest.fixture
def teacher_user_id() -> UUID:
    """Generate a consistent teacher user ID for testing."""
    return UUID("bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb")


@pytest.fixture
def parent_user_id() -> UUID:
    """Generate a consistent parent user ID for testing."""
    return UUID("cccccccc-cccc-cccc-cccc-cccccccccccc")


@pytest.fixture
def rbac_organization_id() -> UUID:
    """Generate a consistent organization ID for testing."""
    return UUID("dddddddd-dddd-dddd-dddd-dddddddddddd")


@pytest.fixture
def rbac_group_id() -> UUID:
    """Generate a consistent group ID for testing."""
    return UUID("eeeeeeee-eeee-eeee-eeee-eeeeeeeeeeee")


@pytest.fixture
def director_token(director_user_id: UUID) -> str:
    """Create a JWT token for a director user."""
    return create_test_token(
        subject=str(director_user_id),
        additional_claims={"email": "director@example.com", "role": "director"},
    )


@pytest.fixture
def teacher_token(teacher_user_id: UUID) -> str:
    """Create a JWT token for a teacher user."""
    return create_test_token(
        subject=str(teacher_user_id),
        additional_claims={"email": "teacher@example.com", "role": "teacher"},
    )


@pytest.fixture
def parent_token(parent_user_id: UUID) -> str:
    """Create a JWT token for a parent user."""
    return create_test_token(
        subject=str(parent_user_id),
        additional_claims={"email": "parent@example.com", "role": "parent"},
    )


@pytest.fixture
def director_headers(director_token: str) -> dict[str, str]:
    """Create authorization headers for a director user."""
    return {"Authorization": f"Bearer {director_token}"}


@pytest.fixture
def teacher_headers(teacher_token: str) -> dict[str, str]:
    """Create authorization headers for a teacher user."""
    return {"Authorization": f"Bearer {teacher_token}"}


@pytest.fixture
def parent_headers(parent_token: str) -> dict[str, str]:
    """Create authorization headers for a parent user."""
    return {"Authorization": f"Bearer {parent_token}"}


@pytest_asyncio.fixture
async def setup_rbac_data(
    db_session: AsyncSession,
    director_user_id: UUID,
    teacher_user_id: UUID,
    parent_user_id: UUID,
) -> dict[str, Any]:
    """Set up RBAC test data in the database.

    Creates the 5-role system with appropriate permissions and user assignments.

    Returns:
        dict: Contains role IDs and user IDs for director, teacher, assistant, staff, and parent
    """
    # Create the 5 roles
    director_role = await create_role_in_db(
        db_session,
        name="director",
        display_name="Director",
        description="Full administrative access",
    )

    teacher_role = await create_role_in_db(
        db_session,
        name="teacher",
        display_name="Teacher",
        description="Classroom and child management access",
    )

    assistant_role = await create_role_in_db(
        db_session,
        name="assistant",
        display_name="Assistant",
        description="Limited classroom support access",
    )

    staff_role = await create_role_in_db(
        db_session,
        name="staff",
        display_name="Staff",
        description="Operational staff with limited access",
    )

    parent_role = await create_role_in_db(
        db_session,
        name="parent",
        display_name="Parent",
        description="Read-only access to their children's data",
    )

    # Create permissions for director (full access)
    await create_permission_in_db(
        db_session,
        role_id=director_role.id,
        resource="*",
        action="*",
    )

    # Create permissions for teacher
    await create_permission_in_db(
        db_session,
        role_id=teacher_role.id,
        resource="children",
        action="read",
    )
    await create_permission_in_db(
        db_session,
        role_id=teacher_role.id,
        resource="children",
        action="write",
    )
    await create_permission_in_db(
        db_session,
        role_id=teacher_role.id,
        resource="activities",
        action="read",
    )
    await create_permission_in_db(
        db_session,
        role_id=teacher_role.id,
        resource="activities",
        action="write",
    )

    # Create permissions for assistant
    await create_permission_in_db(
        db_session,
        role_id=assistant_role.id,
        resource="children",
        action="read",
    )
    await create_permission_in_db(
        db_session,
        role_id=assistant_role.id,
        resource="activities",
        action="read",
    )

    # Create permissions for staff
    await create_permission_in_db(
        db_session,
        role_id=staff_role.id,
        resource="reports",
        action="read",
    )

    # Create permissions for parent (limited to own children)
    await create_permission_in_db(
        db_session,
        role_id=parent_role.id,
        resource="children",
        action="read",
        conditions={"own_children_only": True},
    )

    # Assign roles to test users
    await create_user_role_in_db(
        db_session,
        user_id=director_user_id,
        role_id=director_role.id,
    )
    await create_user_role_in_db(
        db_session,
        user_id=teacher_user_id,
        role_id=teacher_role.id,
    )
    await create_user_role_in_db(
        db_session,
        user_id=parent_user_id,
        role_id=parent_role.id,
    )

    # Commit all changes
    await db_session.commit()

    return {
        "director_role": director_role.id,
        "teacher_role": teacher_role.id,
        "assistant_role": assistant_role.id,
        "staff_role": staff_role.id,
        "parent_role": parent_role.id,
        "director_user": director_user_id,
        "teacher_user": teacher_user_id,
        "parent_user": parent_user_id,
    }


# ============================================================================
# Role Listing Tests
# ============================================================================


class TestRoleListEndpoint:
    """Tests for the GET /api/v1/rbac/roles endpoint."""

    @pytest.mark.asyncio
    async def test_list_roles_success(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
        setup_rbac_data: dict[str, Any],
    ) -> None:
        """Test that authenticated users can list all active roles."""
        response = await client.get(
            "/api/v1/rbac/roles",
            headers=auth_headers,
        )

        assert response.status_code == 200
        data = response.json()
        assert isinstance(data, list)
        assert len(data) == 5  # 5 roles created in setup

        role_names = {r["name"] for r in data}
        assert "director" in role_names
        assert "teacher" in role_names
        assert "assistant" in role_names
        assert "staff" in role_names
        assert "parent" in role_names

    @pytest.mark.asyncio
    async def test_list_roles_include_inactive(
        self,
        client: AsyncClient,
        db_session: AsyncSession,
        auth_headers: dict[str, str],
        setup_rbac_data: dict[str, Any],
    ) -> None:
        """Test listing roles with inactive roles included."""
        # Create an inactive role
        await create_role_in_db(
            db_session,
            name="inactive_role",
            display_name="Inactive Role",
            is_active=False,
        )
        await db_session.commit()

        # Without include_inactive, should not see inactive role
        response = await client.get(
            "/api/v1/rbac/roles",
            headers=auth_headers,
        )
        assert response.status_code == 200
        role_names = {r["name"] for r in response.json()}
        assert "inactive_role" not in role_names

        # With include_inactive=true, should see inactive role
        response = await client.get(
            "/api/v1/rbac/roles?include_inactive=true",
            headers=auth_headers,
        )
        assert response.status_code == 200
        role_names = {r["name"] for r in response.json()}
        assert "inactive_role" in role_names

    @pytest.mark.asyncio
    async def test_list_roles_no_auth_fails(
        self,
        client: AsyncClient,
        setup_rbac_data: dict[str, Any],
    ) -> None:
        """Test that listing roles without authentication fails."""
        response = await client.get("/api/v1/rbac/roles")

        assert response.status_code in [401, 403]

    @pytest.mark.asyncio
    async def test_list_roles_response_contains_permissions(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
        setup_rbac_data: dict[str, Any],
    ) -> None:
        """Test that role response includes permissions."""
        response = await client.get(
            "/api/v1/rbac/roles",
            headers=auth_headers,
        )

        assert response.status_code == 200
        data = response.json()

        # Find the director role
        director_role = next((r for r in data if r["name"] == "director"), None)
        assert director_role is not None
        assert "permissions" in director_role
        assert len(director_role["permissions"]) > 0


class TestGetRoleEndpoint:
    """Tests for the GET /api/v1/rbac/roles/{role_name} endpoint."""

    @pytest.mark.asyncio
    async def test_get_role_by_name_success(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
        setup_rbac_data: dict[str, Any],
    ) -> None:
        """Test retrieving a role by name."""
        response = await client.get(
            "/api/v1/rbac/roles/director",
            headers=auth_headers,
        )

        assert response.status_code == 200
        data = response.json()
        assert data["name"] == "director"
        assert data["display_name"] == "Director"
        assert data["is_system_role"] is True
        assert data["is_active"] is True
        assert "permissions" in data

    @pytest.mark.asyncio
    async def test_get_role_not_found(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
        setup_rbac_data: dict[str, Any],
    ) -> None:
        """Test retrieving a non-existent role returns 404."""
        response = await client.get(
            "/api/v1/rbac/roles/nonexistent_role",
            headers=auth_headers,
        )

        assert response.status_code == 404
        assert "not found" in response.json()["detail"].lower()

    @pytest.mark.asyncio
    async def test_get_role_no_auth_fails(
        self,
        client: AsyncClient,
        setup_rbac_data: dict[str, Any],
    ) -> None:
        """Test that retrieving a role without authentication fails."""
        response = await client.get("/api/v1/rbac/roles/director")

        assert response.status_code in [401, 403]


# ============================================================================
# Role Assignment Tests
# ============================================================================


class TestRoleAssignmentEndpoint:
    """Tests for the POST /api/v1/rbac/roles/assign endpoint."""

    @pytest.mark.asyncio
    async def test_assign_role_as_director_success(
        self,
        client: AsyncClient,
        director_headers: dict[str, str],
        setup_rbac_data: dict[str, Any],
    ) -> None:
        """Test that directors can assign roles to users."""
        new_user_id = uuid4()
        payload = {
            "user_id": str(new_user_id),
            "role_id": str(setup_rbac_data["teacher_role"]),
        }

        response = await client.post(
            "/api/v1/rbac/roles/assign",
            json=payload,
            headers=director_headers,
        )

        assert response.status_code == 200
        data = response.json()
        assert data["user_id"] == str(new_user_id)
        assert data["role_id"] == str(setup_rbac_data["teacher_role"])
        assert data["is_active"] is True
        assert "role" in data

    @pytest.mark.asyncio
    async def test_assign_role_with_organization_scope(
        self,
        client: AsyncClient,
        director_headers: dict[str, str],
        rbac_organization_id: UUID,
        setup_rbac_data: dict[str, Any],
    ) -> None:
        """Test assigning a role with organization scope."""
        new_user_id = uuid4()
        payload = {
            "user_id": str(new_user_id),
            "role_id": str(setup_rbac_data["assistant_role"]),
            "organization_id": str(rbac_organization_id),
        }

        response = await client.post(
            "/api/v1/rbac/roles/assign",
            json=payload,
            headers=director_headers,
        )

        assert response.status_code == 200
        data = response.json()
        assert data["organization_id"] == str(rbac_organization_id)

    @pytest.mark.asyncio
    async def test_assign_role_with_group_scope(
        self,
        client: AsyncClient,
        director_headers: dict[str, str],
        rbac_group_id: UUID,
        setup_rbac_data: dict[str, Any],
    ) -> None:
        """Test assigning a role with group scope."""
        new_user_id = uuid4()
        payload = {
            "user_id": str(new_user_id),
            "role_id": str(setup_rbac_data["teacher_role"]),
            "group_id": str(rbac_group_id),
        }

        response = await client.post(
            "/api/v1/rbac/roles/assign",
            json=payload,
            headers=director_headers,
        )

        assert response.status_code == 200
        data = response.json()
        assert data["group_id"] == str(rbac_group_id)

    @pytest.mark.asyncio
    async def test_assign_role_as_non_director_fails(
        self,
        client: AsyncClient,
        teacher_headers: dict[str, str],
        setup_rbac_data: dict[str, Any],
    ) -> None:
        """Test that non-directors cannot assign roles."""
        payload = {
            "user_id": str(uuid4()),
            "role_id": str(setup_rbac_data["assistant_role"]),
        }

        response = await client.post(
            "/api/v1/rbac/roles/assign",
            json=payload,
            headers=teacher_headers,
        )

        assert response.status_code == 403

    @pytest.mark.asyncio
    async def test_assign_nonexistent_role_fails(
        self,
        client: AsyncClient,
        director_headers: dict[str, str],
        setup_rbac_data: dict[str, Any],
    ) -> None:
        """Test that assigning a non-existent role fails."""
        payload = {
            "user_id": str(uuid4()),
            "role_id": str(uuid4()),  # Non-existent role ID
        }

        response = await client.post(
            "/api/v1/rbac/roles/assign",
            json=payload,
            headers=director_headers,
        )

        assert response.status_code == 404

    @pytest.mark.asyncio
    async def test_assign_duplicate_role_fails(
        self,
        client: AsyncClient,
        director_headers: dict[str, str],
        teacher_user_id: UUID,
        setup_rbac_data: dict[str, Any],
    ) -> None:
        """Test that assigning a duplicate role fails."""
        # Teacher already has teacher role from setup
        payload = {
            "user_id": str(teacher_user_id),
            "role_id": str(setup_rbac_data["teacher_role"]),
        }

        response = await client.post(
            "/api/v1/rbac/roles/assign",
            json=payload,
            headers=director_headers,
        )

        assert response.status_code == 400
        assert "already has role" in response.json()["detail"].lower()


class TestRoleRevocationEndpoint:
    """Tests for the POST /api/v1/rbac/roles/revoke endpoint."""

    @pytest.mark.asyncio
    async def test_revoke_role_as_director_success(
        self,
        client: AsyncClient,
        director_headers: dict[str, str],
        teacher_user_id: UUID,
        setup_rbac_data: dict[str, Any],
    ) -> None:
        """Test that directors can revoke roles from users."""
        payload = {
            "user_id": str(teacher_user_id),
            "role_id": str(setup_rbac_data["teacher_role"]),
        }

        response = await client.post(
            "/api/v1/rbac/roles/revoke",
            json=payload,
            headers=director_headers,
        )

        assert response.status_code == 200
        data = response.json()
        assert data["success"] is True
        assert "revoked" in data["message"].lower()

    @pytest.mark.asyncio
    async def test_revoke_role_as_non_director_fails(
        self,
        client: AsyncClient,
        teacher_headers: dict[str, str],
        parent_user_id: UUID,
        setup_rbac_data: dict[str, Any],
    ) -> None:
        """Test that non-directors cannot revoke roles."""
        payload = {
            "user_id": str(parent_user_id),
            "role_id": str(setup_rbac_data["parent_role"]),
        }

        response = await client.post(
            "/api/v1/rbac/roles/revoke",
            json=payload,
            headers=teacher_headers,
        )

        assert response.status_code == 403

    @pytest.mark.asyncio
    async def test_revoke_nonexistent_assignment_fails(
        self,
        client: AsyncClient,
        director_headers: dict[str, str],
        setup_rbac_data: dict[str, Any],
    ) -> None:
        """Test that revoking a non-existent assignment fails."""
        payload = {
            "user_id": str(uuid4()),  # User without any roles
            "role_id": str(setup_rbac_data["teacher_role"]),
        }

        response = await client.post(
            "/api/v1/rbac/roles/revoke",
            json=payload,
            headers=director_headers,
        )

        assert response.status_code == 404


# ============================================================================
# User Roles Tests
# ============================================================================


class TestUserRolesEndpoint:
    """Tests for the GET /api/v1/rbac/users/{user_id}/roles endpoint."""

    @pytest.mark.asyncio
    async def test_get_own_roles_success(
        self,
        client: AsyncClient,
        teacher_headers: dict[str, str],
        teacher_user_id: UUID,
        setup_rbac_data: dict[str, Any],
    ) -> None:
        """Test that users can view their own roles."""
        response = await client.get(
            f"/api/v1/rbac/users/{teacher_user_id}/roles",
            headers=teacher_headers,
        )

        assert response.status_code == 200
        data = response.json()
        assert isinstance(data, list)
        assert len(data) == 1
        assert data[0]["role"]["name"] == "teacher"

    @pytest.mark.asyncio
    async def test_director_can_view_other_user_roles(
        self,
        client: AsyncClient,
        director_headers: dict[str, str],
        teacher_user_id: UUID,
        setup_rbac_data: dict[str, Any],
    ) -> None:
        """Test that directors can view other users' roles."""
        response = await client.get(
            f"/api/v1/rbac/users/{teacher_user_id}/roles",
            headers=director_headers,
        )

        assert response.status_code == 200
        data = response.json()
        assert isinstance(data, list)
        assert any(r["role"]["name"] == "teacher" for r in data)

    @pytest.mark.asyncio
    async def test_non_director_cannot_view_other_user_roles(
        self,
        client: AsyncClient,
        teacher_headers: dict[str, str],
        parent_user_id: UUID,
        setup_rbac_data: dict[str, Any],
    ) -> None:
        """Test that non-directors cannot view other users' roles."""
        response = await client.get(
            f"/api/v1/rbac/users/{parent_user_id}/roles",
            headers=teacher_headers,
        )

        assert response.status_code == 403


# ============================================================================
# Permission Listing Tests
# ============================================================================


class TestPermissionListEndpoint:
    """Tests for the GET /api/v1/rbac/permissions endpoint."""

    @pytest.mark.asyncio
    async def test_list_permissions_success(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
        setup_rbac_data: dict[str, Any],
    ) -> None:
        """Test that authenticated users can list all permissions."""
        response = await client.get(
            "/api/v1/rbac/permissions",
            headers=auth_headers,
        )

        assert response.status_code == 200
        data = response.json()
        assert isinstance(data, list)
        assert len(data) > 0

    @pytest.mark.asyncio
    async def test_list_permissions_filter_by_role(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
        setup_rbac_data: dict[str, Any],
    ) -> None:
        """Test filtering permissions by role ID."""
        teacher_role_id = setup_rbac_data["teacher_role"]

        response = await client.get(
            f"/api/v1/rbac/permissions?role_id={teacher_role_id}",
            headers=auth_headers,
        )

        assert response.status_code == 200
        data = response.json()
        assert isinstance(data, list)
        # All returned permissions should belong to teacher role
        for permission in data:
            assert permission["role_id"] == str(teacher_role_id)

    @pytest.mark.asyncio
    async def test_list_permissions_no_auth_fails(
        self,
        client: AsyncClient,
        setup_rbac_data: dict[str, Any],
    ) -> None:
        """Test that listing permissions without authentication fails."""
        response = await client.get("/api/v1/rbac/permissions")

        assert response.status_code in [401, 403]


# ============================================================================
# Permission Check Tests
# ============================================================================


class TestPermissionCheckEndpoint:
    """Tests for the POST /api/v1/rbac/permissions/check endpoint."""

    @pytest.mark.asyncio
    async def test_check_permission_allowed(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
        director_user_id: UUID,
        setup_rbac_data: dict[str, Any],
    ) -> None:
        """Test checking a permission that should be allowed."""
        payload = {
            "user_id": str(director_user_id),
            "resource": "children",
            "action": "delete",
        }

        response = await client.post(
            "/api/v1/rbac/permissions/check",
            json=payload,
            headers=auth_headers,
        )

        assert response.status_code == 200
        data = response.json()
        assert data["allowed"] is True
        assert data["user_id"] == str(director_user_id)
        assert data["resource"] == "children"
        assert data["action"] == "delete"
        assert data["matched_role"] == "director"

    @pytest.mark.asyncio
    async def test_check_permission_denied(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
        teacher_user_id: UUID,
        setup_rbac_data: dict[str, Any],
    ) -> None:
        """Test checking a permission that should be denied."""
        payload = {
            "user_id": str(teacher_user_id),
            "resource": "children",
            "action": "delete",  # Teacher doesn't have delete permission
        }

        response = await client.post(
            "/api/v1/rbac/permissions/check",
            json=payload,
            headers=auth_headers,
        )

        assert response.status_code == 200
        data = response.json()
        assert data["allowed"] is False
        assert data["matched_role"] is None

    @pytest.mark.asyncio
    async def test_check_permission_user_without_roles(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
        setup_rbac_data: dict[str, Any],
    ) -> None:
        """Test checking permission for user without any roles."""
        payload = {
            "user_id": str(uuid4()),  # User without roles
            "resource": "children",
            "action": "read",
        }

        response = await client.post(
            "/api/v1/rbac/permissions/check",
            json=payload,
            headers=auth_headers,
        )

        assert response.status_code == 200
        data = response.json()
        assert data["allowed"] is False
        assert "no active roles" in data["reason"].lower()

    @pytest.mark.asyncio
    async def test_check_permission_with_group_context(
        self,
        client: AsyncClient,
        db_session: AsyncSession,
        auth_headers: dict[str, str],
        rbac_group_id: UUID,
        setup_rbac_data: dict[str, Any],
    ) -> None:
        """Test checking permission with group context."""
        # Create a user with teacher role scoped to a specific group
        user_id = uuid4()
        await create_user_role_in_db(
            db_session,
            user_id=user_id,
            role_id=setup_rbac_data["teacher_role"],
            group_id=rbac_group_id,
        )
        await db_session.commit()

        payload = {
            "user_id": str(user_id),
            "resource": "children",
            "action": "read",
            "group_id": str(rbac_group_id),
        }

        response = await client.post(
            "/api/v1/rbac/permissions/check",
            json=payload,
            headers=auth_headers,
        )

        assert response.status_code == 200
        data = response.json()
        assert data["allowed"] is True


# ============================================================================
# User Permissions Tests
# ============================================================================


class TestUserPermissionsEndpoint:
    """Tests for the GET /api/v1/rbac/users/{user_id}/permissions endpoint."""

    @pytest.mark.asyncio
    async def test_get_own_permissions_success(
        self,
        client: AsyncClient,
        teacher_headers: dict[str, str],
        teacher_user_id: UUID,
        setup_rbac_data: dict[str, Any],
    ) -> None:
        """Test that users can view their own permissions."""
        response = await client.get(
            f"/api/v1/rbac/users/{teacher_user_id}/permissions",
            headers=teacher_headers,
        )

        assert response.status_code == 200
        data = response.json()
        assert data["user_id"] == str(teacher_user_id)
        assert "roles" in data
        assert "permissions" in data
        assert len(data["roles"]) >= 1
        assert len(data["permissions"]) >= 1

    @pytest.mark.asyncio
    async def test_director_can_view_other_user_permissions(
        self,
        client: AsyncClient,
        director_headers: dict[str, str],
        teacher_user_id: UUID,
        setup_rbac_data: dict[str, Any],
    ) -> None:
        """Test that directors can view other users' permissions."""
        response = await client.get(
            f"/api/v1/rbac/users/{teacher_user_id}/permissions",
            headers=director_headers,
        )

        assert response.status_code == 200
        data = response.json()
        assert data["user_id"] == str(teacher_user_id)

    @pytest.mark.asyncio
    async def test_non_director_cannot_view_other_user_permissions(
        self,
        client: AsyncClient,
        teacher_headers: dict[str, str],
        parent_user_id: UUID,
        setup_rbac_data: dict[str, Any],
    ) -> None:
        """Test that non-directors cannot view other users' permissions."""
        response = await client.get(
            f"/api/v1/rbac/users/{parent_user_id}/permissions",
            headers=teacher_headers,
        )

        assert response.status_code == 403


# ============================================================================
# Audit Trail Tests
# ============================================================================


class TestAuditTrailEndpoint:
    """Tests for the GET /api/v1/rbac/audit endpoint."""

    @pytest.mark.asyncio
    async def test_get_audit_logs_as_director(
        self,
        client: AsyncClient,
        db_session: AsyncSession,
        director_headers: dict[str, str],
        director_user_id: UUID,
        setup_rbac_data: dict[str, Any],
    ) -> None:
        """Test that directors can access audit logs."""
        # Create some audit log entries
        await create_audit_log_in_db(
            db_session,
            user_id=director_user_id,
            action="role_assigned",
            resource_type="user_role",
            details={"role": "teacher"},
        )
        await db_session.commit()

        response = await client.get(
            "/api/v1/rbac/audit",
            headers=director_headers,
        )

        assert response.status_code == 200
        data = response.json()
        assert "items" in data
        assert "total" in data
        assert isinstance(data["items"], list)

    @pytest.mark.asyncio
    async def test_get_audit_logs_with_filters(
        self,
        client: AsyncClient,
        db_session: AsyncSession,
        director_headers: dict[str, str],
        director_user_id: UUID,
        teacher_user_id: UUID,
        setup_rbac_data: dict[str, Any],
    ) -> None:
        """Test filtering audit logs."""
        # Create audit entries for different users and actions
        await create_audit_log_in_db(
            db_session,
            user_id=director_user_id,
            action="role_assigned",
            resource_type="user_role",
        )
        await create_audit_log_in_db(
            db_session,
            user_id=teacher_user_id,
            action="access_denied",
            resource_type="children",
        )
        await db_session.commit()

        # Filter by user_id
        response = await client.get(
            f"/api/v1/rbac/audit?user_id={director_user_id}",
            headers=director_headers,
        )
        assert response.status_code == 200
        data = response.json()
        for item in data["items"]:
            assert item["user_id"] == str(director_user_id)

        # Filter by action
        response = await client.get(
            "/api/v1/rbac/audit?action=access_denied",
            headers=director_headers,
        )
        assert response.status_code == 200
        data = response.json()
        for item in data["items"]:
            assert item["action"] == "access_denied"

    @pytest.mark.asyncio
    async def test_get_audit_logs_pagination(
        self,
        client: AsyncClient,
        db_session: AsyncSession,
        director_headers: dict[str, str],
        director_user_id: UUID,
        setup_rbac_data: dict[str, Any],
    ) -> None:
        """Test audit logs pagination."""
        # Create multiple audit entries
        for i in range(15):
            await create_audit_log_in_db(
                db_session,
                user_id=director_user_id,
                action="test_action",
                resource_type="test_resource",
            )
        await db_session.commit()

        # Test with limit
        response = await client.get(
            "/api/v1/rbac/audit?limit=5",
            headers=director_headers,
        )
        assert response.status_code == 200
        data = response.json()
        assert len(data["items"]) <= 5
        assert data["limit"] == 5

        # Test with offset
        response = await client.get(
            "/api/v1/rbac/audit?limit=5&offset=5",
            headers=director_headers,
        )
        assert response.status_code == 200
        data = response.json()
        assert data["skip"] == 5

    @pytest.mark.asyncio
    async def test_get_audit_logs_as_non_director_fails(
        self,
        client: AsyncClient,
        teacher_headers: dict[str, str],
        setup_rbac_data: dict[str, Any],
    ) -> None:
        """Test that non-directors cannot access audit logs."""
        response = await client.get(
            "/api/v1/rbac/audit",
            headers=teacher_headers,
        )

        assert response.status_code == 403

    @pytest.mark.asyncio
    async def test_get_audit_logs_no_auth_fails(
        self,
        client: AsyncClient,
        setup_rbac_data: dict[str, Any],
    ) -> None:
        """Test that accessing audit logs without auth fails."""
        response = await client.get("/api/v1/rbac/audit")

        assert response.status_code in [401, 403]


# ============================================================================
# JWT Validation Tests
# ============================================================================


class TestRBACJWTValidation:
    """Tests for JWT authentication on RBAC endpoints."""

    @pytest.mark.asyncio
    async def test_expired_token_fails(
        self,
        client: AsyncClient,
        expired_token: str,
        setup_rbac_data: dict[str, Any],
    ) -> None:
        """Test that expired JWT token is rejected."""
        headers = {"Authorization": f"Bearer {expired_token}"}

        response = await client.get(
            "/api/v1/rbac/roles",
            headers=headers,
        )

        assert response.status_code == 401

    @pytest.mark.asyncio
    async def test_malformed_token_fails(
        self,
        client: AsyncClient,
        setup_rbac_data: dict[str, Any],
    ) -> None:
        """Test that malformed JWT token is rejected."""
        headers = {"Authorization": "Bearer not.a.valid.jwt.token"}

        response = await client.get(
            "/api/v1/rbac/roles",
            headers=headers,
        )

        assert response.status_code == 401

    @pytest.mark.asyncio
    async def test_missing_bearer_prefix_fails(
        self,
        client: AsyncClient,
        valid_token: str,
        setup_rbac_data: dict[str, Any],
    ) -> None:
        """Test that authorization without Bearer prefix is rejected."""
        headers = {"Authorization": valid_token}

        response = await client.get(
            "/api/v1/rbac/roles",
            headers=headers,
        )

        assert response.status_code in [401, 403]


# ============================================================================
# Edge Cases and Validation Tests
# ============================================================================


class TestRBACValidation:
    """Tests for input validation on RBAC endpoints."""

    @pytest.mark.asyncio
    async def test_assign_role_invalid_uuid_format(
        self,
        client: AsyncClient,
        director_headers: dict[str, str],
        setup_rbac_data: dict[str, Any],
    ) -> None:
        """Test that invalid UUID format returns validation error."""
        payload = {
            "user_id": "not-a-valid-uuid",
            "role_id": str(setup_rbac_data["teacher_role"]),
        }

        response = await client.post(
            "/api/v1/rbac/roles/assign",
            json=payload,
            headers=director_headers,
        )

        assert response.status_code == 422

    @pytest.mark.asyncio
    async def test_permission_check_missing_required_fields(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
        setup_rbac_data: dict[str, Any],
    ) -> None:
        """Test that missing required fields returns validation error."""
        payload = {
            "user_id": str(uuid4()),
            # Missing resource and action
        }

        response = await client.post(
            "/api/v1/rbac/permissions/check",
            json=payload,
            headers=auth_headers,
        )

        assert response.status_code == 422

    @pytest.mark.asyncio
    async def test_permission_check_empty_resource(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
        setup_rbac_data: dict[str, Any],
    ) -> None:
        """Test that empty resource returns validation error."""
        payload = {
            "user_id": str(uuid4()),
            "resource": "",
            "action": "read",
        }

        response = await client.post(
            "/api/v1/rbac/permissions/check",
            json=payload,
            headers=auth_headers,
        )

        assert response.status_code == 422

    @pytest.mark.asyncio
    async def test_audit_log_invalid_limit(
        self,
        client: AsyncClient,
        director_headers: dict[str, str],
        setup_rbac_data: dict[str, Any],
    ) -> None:
        """Test that invalid limit value returns validation error."""
        response = await client.get(
            "/api/v1/rbac/audit?limit=0",  # Limit must be >= 1
            headers=director_headers,
        )

        assert response.status_code == 422

    @pytest.mark.asyncio
    async def test_audit_log_limit_exceeds_max(
        self,
        client: AsyncClient,
        director_headers: dict[str, str],
        setup_rbac_data: dict[str, Any],
    ) -> None:
        """Test that limit exceeding max returns validation error."""
        response = await client.get(
            "/api/v1/rbac/audit?limit=10000",  # Max is 1000
            headers=director_headers,
        )

        assert response.status_code == 422
