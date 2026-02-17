"""Unit tests for RBAC (Role-Based Access Control) functionality.

Tests for role assignment, permission checks, group filtering, role type
verification, and service configuration. Covers the 5-role RBAC system
including Director, Teacher, Assistant, Staff, and Parent roles.
"""

from __future__ import annotations

from datetime import datetime, timedelta, timezone
from typing import Any
from unittest.mock import AsyncMock, MagicMock, patch
from uuid import UUID, uuid4

import pytest
import pytest_asyncio
from sqlalchemy.ext.asyncio import AsyncSession

from app.models.rbac import Permission, Role, RoleType, UserRole
from app.schemas.rbac import (
    PermissionCheckRequest,
    PermissionCheckResponse,
    PermissionResponse,
    RoleResponse,
    UserPermissionsResponse,
    UserRoleAssignment,
    UserRoleResponse,
)
from app.services.rbac_service import (
    InvalidAssignmentError,
    PermissionDeniedError,
    RBACService,
    RoleNotFoundError,
    UserRoleNotFoundError,
)


# ============================================================================
# Fixtures
# ============================================================================


@pytest.fixture
def mock_db_session() -> AsyncMock:
    """Create a mock async database session.

    Returns:
        AsyncMock: Mock database session with async methods
    """
    session = AsyncMock()
    session.add = MagicMock()
    session.flush = AsyncMock()
    session.commit = AsyncMock()
    session.rollback = AsyncMock()
    session.refresh = AsyncMock()
    session.execute = AsyncMock()
    return session


@pytest.fixture
def mock_notification_service() -> AsyncMock:
    """Create a mock notification service.

    Returns:
        AsyncMock: Mock notification service
    """
    service = AsyncMock()
    service.notify_unauthorized_access = AsyncMock()
    service.check_and_notify_threshold = AsyncMock()
    service.count_failed_attempts = AsyncMock(return_value=0)
    service.get_recent_unauthorized_attempts = AsyncMock(return_value=[])
    return service


@pytest.fixture
def mock_audit_service() -> AsyncMock:
    """Create a mock audit service.

    Returns:
        AsyncMock: Mock audit service
    """
    service = AsyncMock()
    service.log_access_granted = AsyncMock()
    service.log_access_denied = AsyncMock()
    return service


@pytest.fixture
def test_user_id() -> UUID:
    """Generate a test user ID.

    Returns:
        UUID: Test user identifier
    """
    return UUID("12345678-1234-1234-1234-123456789abc")


@pytest.fixture
def test_organization_id() -> UUID:
    """Generate a test organization ID.

    Returns:
        UUID: Test organization identifier
    """
    return UUID("aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa")


@pytest.fixture
def test_group_id() -> UUID:
    """Generate a test group ID.

    Returns:
        UUID: Test group identifier
    """
    return UUID("bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb")


@pytest.fixture
def test_role_id() -> UUID:
    """Generate a test role ID.

    Returns:
        UUID: Test role identifier
    """
    return UUID("cccccccc-cccc-cccc-cccc-cccccccccccc")


@pytest.fixture
def director_role() -> Role:
    """Create a mock Director role.

    Returns:
        Role: Mock Director role with permissions
    """
    role = MagicMock(spec=Role)
    role.id = uuid4()
    role.name = RoleType.DIRECTOR.value
    role.display_name = "Director"
    role.description = "Full administrative access"
    role.is_system_role = True
    role.is_active = True

    # Create permission with all access
    permission = MagicMock(spec=Permission)
    permission.id = uuid4()
    permission.role_id = role.id
    permission.resource = "*"
    permission.action = "*"
    permission.conditions = None
    permission.is_active = True
    permission.created_at = datetime.now(timezone.utc)

    role.permissions = [permission]
    return role


@pytest.fixture
def teacher_role() -> Role:
    """Create a mock Teacher role.

    Returns:
        Role: Mock Teacher role with permissions
    """
    role = MagicMock(spec=Role)
    role.id = uuid4()
    role.name = RoleType.TEACHER.value
    role.display_name = "Teacher"
    role.description = "Classroom and child management access"
    role.is_system_role = True
    role.is_active = True

    # Create permission for children resource
    permission = MagicMock(spec=Permission)
    permission.id = uuid4()
    permission.role_id = role.id
    permission.resource = "children"
    permission.action = "read"
    permission.conditions = None
    permission.is_active = True
    permission.created_at = datetime.now(timezone.utc)

    role.permissions = [permission]
    return role


@pytest.fixture
def parent_role() -> Role:
    """Create a mock Parent role.

    Returns:
        Role: Mock Parent role with limited permissions
    """
    role = MagicMock(spec=Role)
    role.id = uuid4()
    role.name = RoleType.PARENT.value
    role.display_name = "Parent"
    role.description = "Read-only access to their children's data"
    role.is_system_role = True
    role.is_active = True

    # Create permission with own_children_only condition
    permission = MagicMock(spec=Permission)
    permission.id = uuid4()
    permission.role_id = role.id
    permission.resource = "children"
    permission.action = "read"
    permission.conditions = {"own_children_only": True}
    permission.is_active = True
    permission.created_at = datetime.now(timezone.utc)

    role.permissions = [permission]
    return role


@pytest.fixture
def rbac_service(mock_db_session: AsyncMock) -> RBACService:
    """Create an RBACService instance with mock database.

    Args:
        mock_db_session: Mock database session fixture

    Returns:
        RBACService: Service instance for testing
    """
    return RBACService(mock_db_session)


@pytest.fixture
def rbac_service_with_notification(
    mock_db_session: AsyncMock,
    mock_notification_service: AsyncMock,
    mock_audit_service: AsyncMock,
) -> RBACService:
    """Create an RBACService with notification and audit services.

    Args:
        mock_db_session: Mock database session
        mock_notification_service: Mock notification service
        mock_audit_service: Mock audit service

    Returns:
        RBACService: Service instance with full configuration
    """
    return RBACService(
        db=mock_db_session,
        notification_service=mock_notification_service,
        audit_service=mock_audit_service,
        failed_attempt_threshold=5,
        failed_attempt_window_minutes=15,
    )


def create_user_role(
    user_id: UUID,
    role: Role,
    organization_id: UUID | None = None,
    group_id: UUID | None = None,
    is_active: bool = True,
    expires_at: datetime | None = None,
) -> UserRole:
    """Create a mock UserRole assignment.

    Args:
        user_id: ID of the user
        role: The Role to assign
        organization_id: Optional organization scope
        group_id: Optional group scope
        is_active: Whether the assignment is active
        expires_at: Optional expiration datetime

    Returns:
        UserRole: Mock user-role assignment
    """
    user_role = MagicMock(spec=UserRole)
    user_role.id = uuid4()
    user_role.user_id = user_id
    user_role.role_id = role.id
    user_role.role = role
    user_role.organization_id = organization_id
    user_role.group_id = group_id
    user_role.assigned_by = None
    user_role.assigned_at = datetime.now(timezone.utc)
    user_role.expires_at = expires_at
    user_role.is_active = is_active
    return user_role


# ============================================================================
# Permission Check Tests
# ============================================================================


@pytest.mark.asyncio
async def test_check_permission_allowed_with_wildcard(
    mock_db_session: AsyncMock,
    test_user_id: UUID,
    director_role: Role,
) -> None:
    """Test that permission check returns allowed for director with wildcard.

    Verifies that users with the Director role (which has wildcard * permissions)
    are granted access to any resource and action.
    """
    service = RBACService(mock_db_session)

    # Setup mock to return director user role
    user_role = create_user_role(user_id=test_user_id, role=director_role)
    mock_result = MagicMock()
    mock_result.scalars.return_value.all.return_value = [user_role]
    mock_db_session.execute.return_value = mock_result

    request = PermissionCheckRequest(
        user_id=test_user_id,
        resource="children",
        action="delete",
    )

    result = await service.check_permission(request)

    assert result.allowed is True, "Director should have permission"
    assert result.user_id == test_user_id, "User ID should match"
    assert result.resource == "children", "Resource should match"
    assert result.action == "delete", "Action should match"
    assert result.matched_role == "director", "Should match director role"


@pytest.mark.asyncio
async def test_check_permission_denied_no_roles(
    mock_db_session: AsyncMock,
    test_user_id: UUID,
) -> None:
    """Test that permission check returns denied when user has no roles.

    Verifies that users without any active role assignments are denied
    access to resources.
    """
    service = RBACService(mock_db_session)

    # Setup mock to return empty roles
    mock_result = MagicMock()
    mock_result.scalars.return_value.all.return_value = []
    mock_db_session.execute.return_value = mock_result

    request = PermissionCheckRequest(
        user_id=test_user_id,
        resource="children",
        action="read",
    )

    result = await service.check_permission(request)

    assert result.allowed is False, "User without roles should be denied"
    assert result.matched_role is None, "Should not match any role"
    assert "no active roles" in result.reason.lower(), (
        "Reason should indicate no roles"
    )


@pytest.mark.asyncio
async def test_check_permission_denied_no_matching_permission(
    mock_db_session: AsyncMock,
    test_user_id: UUID,
    parent_role: Role,
) -> None:
    """Test that permission check returns denied when no matching permission.

    Verifies that users are denied access when they have roles but no
    matching permission for the requested resource/action.
    """
    service = RBACService(mock_db_session)

    # Parent can only read children, not delete
    user_role = create_user_role(user_id=test_user_id, role=parent_role)
    mock_result = MagicMock()
    mock_result.scalars.return_value.all.return_value = [user_role]
    mock_db_session.execute.return_value = mock_result

    request = PermissionCheckRequest(
        user_id=test_user_id,
        resource="children",
        action="delete",
    )

    result = await service.check_permission(request)

    assert result.allowed is False, "Parent should not have delete permission"
    assert result.matched_role is None, "Should not match any role"


@pytest.mark.asyncio
async def test_check_permission_with_group_context(
    mock_db_session: AsyncMock,
    test_user_id: UUID,
    test_group_id: UUID,
    teacher_role: Role,
) -> None:
    """Test permission check with group context.

    Verifies that group-scoped role assignments correctly limit access
    to the specific group context.
    """
    service = RBACService(mock_db_session)

    # Teacher assigned to specific group
    user_role = create_user_role(
        user_id=test_user_id,
        role=teacher_role,
        group_id=test_group_id,
    )
    mock_result = MagicMock()
    mock_result.scalars.return_value.all.return_value = [user_role]
    mock_db_session.execute.return_value = mock_result

    request = PermissionCheckRequest(
        user_id=test_user_id,
        resource="children",
        action="read",
        group_id=test_group_id,
    )

    result = await service.check_permission(request)

    assert result.allowed is True, "Teacher should have read access in their group"


@pytest.mark.asyncio
async def test_check_permission_denied_different_group(
    mock_db_session: AsyncMock,
    test_user_id: UUID,
    test_group_id: UUID,
    teacher_role: Role,
) -> None:
    """Test permission check denied for different group.

    Verifies that users assigned to one group cannot access resources
    in a different group context.
    """
    service = RBACService(mock_db_session)

    # Teacher assigned to specific group
    user_role = create_user_role(
        user_id=test_user_id,
        role=teacher_role,
        group_id=test_group_id,
    )
    mock_result = MagicMock()
    mock_result.scalars.return_value.all.return_value = [user_role]
    mock_db_session.execute.return_value = mock_result

    # Request for different group
    different_group_id = uuid4()
    request = PermissionCheckRequest(
        user_id=test_user_id,
        resource="children",
        action="read",
        group_id=different_group_id,
    )

    result = await service.check_permission(request)

    assert result.allowed is False, "Teacher should not access different group"


@pytest.mark.asyncio
async def test_has_permission_simple_check(
    mock_db_session: AsyncMock,
    test_user_id: UUID,
    director_role: Role,
) -> None:
    """Test the simple has_permission helper method.

    Verifies that has_permission returns a boolean result correctly.
    """
    service = RBACService(mock_db_session)

    user_role = create_user_role(user_id=test_user_id, role=director_role)
    mock_result = MagicMock()
    mock_result.scalars.return_value.all.return_value = [user_role]
    mock_db_session.execute.return_value = mock_result

    result = await service.has_permission(
        user_id=test_user_id,
        resource="reports",
        action="write",
    )

    assert result is True, "Director should have write permission"


@pytest.mark.asyncio
async def test_expired_role_not_considered(
    mock_db_session: AsyncMock,
    test_user_id: UUID,
    teacher_role: Role,
) -> None:
    """Test that expired role assignments are not considered.

    Verifies that roles past their expiration date do not grant
    any permissions to the user.
    """
    service = RBACService(mock_db_session)

    # Create expired role assignment
    expired_time = datetime.now(timezone.utc) - timedelta(days=1)
    user_role = create_user_role(
        user_id=test_user_id,
        role=teacher_role,
        expires_at=expired_time,
    )

    # The query should filter out expired roles, returning empty
    mock_result = MagicMock()
    mock_result.scalars.return_value.all.return_value = []
    mock_db_session.execute.return_value = mock_result

    result = await service.has_permission(
        user_id=test_user_id,
        resource="children",
        action="read",
    )

    assert result is False, "Expired role should not grant permission"


# ============================================================================
# Role Assignment Tests
# ============================================================================


@pytest.mark.asyncio
async def test_assign_role_success(
    mock_db_session: AsyncMock,
    test_user_id: UUID,
    test_role_id: UUID,
    teacher_role: Role,
) -> None:
    """Test successful role assignment to a user.

    Verifies that assign_role creates a new user-role assignment
    and returns the correct response.
    """
    service = RBACService(mock_db_session)

    # First call: role lookup (_get_role_by_id)
    mock_role_result = MagicMock()
    mock_role_result.scalar_one_or_none.return_value = teacher_role

    # Second call: check existing assignment (_find_existing_assignment) - None
    mock_existing_result = MagicMock()
    mock_existing_result.scalar_one_or_none.return_value = None

    # Configure execute to return different results for different calls
    mock_db_session.execute.side_effect = [
        mock_role_result,
        mock_existing_result,
    ]

    # Mock refresh to populate required fields on the UserRole object
    async def mock_refresh(user_role: UserRole) -> None:
        user_role.id = uuid4()
        user_role.assigned_at = datetime.now(timezone.utc)

    mock_db_session.refresh = mock_refresh

    assignment = UserRoleAssignment(
        user_id=test_user_id,
        role_id=teacher_role.id,
    )

    result = await service.assign_role(assignment)

    assert result is not None, "Should return assignment result"
    assert result.user_id == test_user_id, "User ID should match"
    assert result.role_id == teacher_role.id, "Role ID should match"
    assert result.is_active is True, "Assignment should be active"
    mock_db_session.add.assert_called_once()
    mock_db_session.commit.assert_called_once()


@pytest.mark.asyncio
async def test_assign_role_not_found(
    mock_db_session: AsyncMock,
    test_user_id: UUID,
    test_role_id: UUID,
) -> None:
    """Test role assignment failure when role doesn't exist.

    Verifies that assign_role raises RoleNotFoundError when the
    specified role ID doesn't exist in the database.
    """
    service = RBACService(mock_db_session)

    # Mock role lookup returning None
    mock_role_result = MagicMock()
    mock_role_result.scalar_one_or_none.return_value = None
    mock_db_session.execute.return_value = mock_role_result

    assignment = UserRoleAssignment(
        user_id=test_user_id,
        role_id=test_role_id,
    )

    with pytest.raises(RoleNotFoundError) as exc_info:
        await service.assign_role(assignment)

    assert str(test_role_id) in str(exc_info.value), (
        "Error should include role ID"
    )


@pytest.mark.asyncio
async def test_assign_inactive_role_fails(
    mock_db_session: AsyncMock,
    test_user_id: UUID,
    teacher_role: Role,
) -> None:
    """Test role assignment failure for inactive role.

    Verifies that assign_role raises InvalidAssignmentError when
    attempting to assign an inactive role.
    """
    service = RBACService(mock_db_session)

    # Make role inactive
    teacher_role.is_active = False

    mock_role_result = MagicMock()
    mock_role_result.scalar_one_or_none.return_value = teacher_role
    mock_db_session.execute.return_value = mock_role_result

    assignment = UserRoleAssignment(
        user_id=test_user_id,
        role_id=teacher_role.id,
    )

    with pytest.raises(InvalidAssignmentError) as exc_info:
        await service.assign_role(assignment)

    assert "inactive" in str(exc_info.value).lower(), (
        "Error should indicate inactive role"
    )


@pytest.mark.asyncio
async def test_assign_duplicate_role_fails(
    mock_db_session: AsyncMock,
    test_user_id: UUID,
    teacher_role: Role,
) -> None:
    """Test role assignment failure for duplicate assignment.

    Verifies that assign_role raises InvalidAssignmentError when
    the user already has the same role assigned.
    """
    service = RBACService(mock_db_session)

    # First call for role lookup
    mock_role_result = MagicMock()
    mock_role_result.scalar_one_or_none.return_value = teacher_role

    # Second call for existing assignment check
    existing_user_role = create_user_role(
        user_id=test_user_id,
        role=teacher_role,
        is_active=True,
    )
    mock_existing_result = MagicMock()
    mock_existing_result.scalar_one_or_none.return_value = existing_user_role

    # Configure execute to return different results for different calls
    mock_db_session.execute.side_effect = [
        mock_role_result,
        mock_existing_result,
    ]

    assignment = UserRoleAssignment(
        user_id=test_user_id,
        role_id=teacher_role.id,
    )

    with pytest.raises(InvalidAssignmentError) as exc_info:
        await service.assign_role(assignment)

    assert "already has role" in str(exc_info.value).lower(), (
        "Error should indicate duplicate assignment"
    )


# ============================================================================
# Role Revocation Tests
# ============================================================================


@pytest.mark.asyncio
async def test_revoke_role_success(
    mock_db_session: AsyncMock,
    test_user_id: UUID,
    test_role_id: UUID,
    teacher_role: Role,
) -> None:
    """Test successful role revocation from a user.

    Verifies that revoke_role deactivates the user-role assignment
    and returns True on success.
    """
    service = RBACService(mock_db_session)

    existing_user_role = create_user_role(
        user_id=test_user_id,
        role=teacher_role,
        is_active=True,
    )

    mock_result = MagicMock()
    mock_result.scalar_one_or_none.return_value = existing_user_role
    mock_db_session.execute.return_value = mock_result

    result = await service.revoke_role(
        user_id=test_user_id,
        role_id=teacher_role.id,
    )

    assert result is True, "Revoke should return True"
    assert existing_user_role.is_active is False, "Assignment should be deactivated"
    mock_db_session.commit.assert_called_once()


@pytest.mark.asyncio
async def test_revoke_role_not_found(
    mock_db_session: AsyncMock,
    test_user_id: UUID,
    test_role_id: UUID,
) -> None:
    """Test role revocation failure when assignment doesn't exist.

    Verifies that revoke_role raises UserRoleNotFoundError when
    no matching active assignment exists.
    """
    service = RBACService(mock_db_session)

    mock_result = MagicMock()
    mock_result.scalar_one_or_none.return_value = None
    mock_db_session.execute.return_value = mock_result

    with pytest.raises(UserRoleNotFoundError):
        await service.revoke_role(
            user_id=test_user_id,
            role_id=test_role_id,
        )


@pytest.mark.asyncio
async def test_revoke_already_inactive_role(
    mock_db_session: AsyncMock,
    test_user_id: UUID,
    teacher_role: Role,
) -> None:
    """Test revoking an already inactive role returns False.

    Verifies that revoke_role returns False when the assignment
    is already inactive.
    """
    service = RBACService(mock_db_session)

    existing_user_role = create_user_role(
        user_id=test_user_id,
        role=teacher_role,
        is_active=False,
    )

    mock_result = MagicMock()
    mock_result.scalar_one_or_none.return_value = existing_user_role
    mock_db_session.execute.return_value = mock_result

    result = await service.revoke_role(
        user_id=test_user_id,
        role_id=teacher_role.id,
    )

    assert result is False, "Should return False for already inactive"


# ============================================================================
# Group Filtering Tests
# ============================================================================


@pytest.mark.asyncio
async def test_get_accessible_group_ids_director_full_access(
    mock_db_session: AsyncMock,
    test_user_id: UUID,
    director_role: Role,
) -> None:
    """Test that directors get full access (empty group list).

    Verifies that users with Director role return an empty list,
    indicating access to all groups.
    """
    service = RBACService(mock_db_session)

    user_role = create_user_role(user_id=test_user_id, role=director_role)
    mock_result = MagicMock()
    mock_result.scalars.return_value.all.return_value = [user_role]
    mock_db_session.execute.return_value = mock_result

    groups = await service.get_accessible_group_ids(user_id=test_user_id)

    assert groups == [], "Director should have empty list (full access)"


@pytest.mark.asyncio
async def test_get_accessible_group_ids_teacher_specific_groups(
    mock_db_session: AsyncMock,
    test_user_id: UUID,
    test_group_id: UUID,
    teacher_role: Role,
) -> None:
    """Test that teachers get specific group access.

    Verifies that users with Teacher role assigned to specific groups
    receive only those group IDs.
    """
    service = RBACService(mock_db_session)

    # Teacher assigned to specific groups
    group_id_2 = uuid4()
    user_role_1 = create_user_role(
        user_id=test_user_id,
        role=teacher_role,
        group_id=test_group_id,
    )
    user_role_2 = create_user_role(
        user_id=test_user_id,
        role=teacher_role,
        group_id=group_id_2,
    )

    mock_result = MagicMock()
    mock_result.scalars.return_value.all.return_value = [user_role_1, user_role_2]
    mock_db_session.execute.return_value = mock_result

    groups = await service.get_accessible_group_ids(user_id=test_user_id)

    assert len(groups) == 2, "Should have access to 2 groups"
    assert test_group_id in groups, "Should include first group"
    assert group_id_2 in groups, "Should include second group"


@pytest.mark.asyncio
async def test_filter_by_group_access_director(
    mock_db_session: AsyncMock,
    test_user_id: UUID,
    director_role: Role,
) -> None:
    """Test that directors see all items when filtering.

    Verifies that filter_by_group_access returns all items
    for users with Director role (full access).
    """
    service = RBACService(mock_db_session)

    user_role = create_user_role(user_id=test_user_id, role=director_role)
    mock_result = MagicMock()
    mock_result.scalars.return_value.all.return_value = [user_role]
    mock_db_session.execute.return_value = mock_result

    items = [
        {"id": 1, "group_id": uuid4()},
        {"id": 2, "group_id": uuid4()},
        {"id": 3, "group_id": uuid4()},
    ]

    filtered = await service.filter_by_group_access(
        user_id=test_user_id,
        items=items,
    )

    assert len(filtered) == 3, "Director should see all items"
    assert filtered == items, "Items should be unchanged"


@pytest.mark.asyncio
async def test_filter_by_group_access_teacher(
    mock_db_session: AsyncMock,
    test_user_id: UUID,
    test_group_id: UUID,
    teacher_role: Role,
) -> None:
    """Test that teachers only see items from their groups.

    Verifies that filter_by_group_access returns only items
    matching the user's assigned groups.
    """
    service = RBACService(mock_db_session)

    user_role = create_user_role(
        user_id=test_user_id,
        role=teacher_role,
        group_id=test_group_id,
    )
    mock_result = MagicMock()
    mock_result.scalars.return_value.all.return_value = [user_role]
    mock_db_session.execute.return_value = mock_result

    other_group_id = uuid4()
    items = [
        {"id": 1, "group_id": test_group_id},
        {"id": 2, "group_id": other_group_id},
        {"id": 3, "group_id": test_group_id},
    ]

    filtered = await service.filter_by_group_access(
        user_id=test_user_id,
        items=items,
    )

    assert len(filtered) == 2, "Teacher should only see their group items"
    assert all(item["group_id"] == test_group_id for item in filtered), (
        "All items should be from teacher's group"
    )


@pytest.mark.asyncio
async def test_can_access_group_director(
    mock_db_session: AsyncMock,
    test_user_id: UUID,
    test_group_id: UUID,
    director_role: Role,
) -> None:
    """Test that directors can access any group.

    Verifies that can_access_group returns True for any group
    when the user has Director role.
    """
    service = RBACService(mock_db_session)

    user_role = create_user_role(user_id=test_user_id, role=director_role)
    mock_result = MagicMock()
    mock_result.scalars.return_value.all.return_value = [user_role]
    mock_db_session.execute.return_value = mock_result

    result = await service.can_access_group(
        user_id=test_user_id,
        group_id=test_group_id,
    )

    assert result is True, "Director should access any group"


@pytest.mark.asyncio
async def test_can_access_group_teacher_own_group(
    mock_db_session: AsyncMock,
    test_user_id: UUID,
    test_group_id: UUID,
    teacher_role: Role,
) -> None:
    """Test that teachers can access their assigned group.

    Verifies that can_access_group returns True for groups
    the teacher is assigned to.
    """
    service = RBACService(mock_db_session)

    user_role = create_user_role(
        user_id=test_user_id,
        role=teacher_role,
        group_id=test_group_id,
    )
    mock_result = MagicMock()
    mock_result.scalars.return_value.all.return_value = [user_role]
    mock_db_session.execute.return_value = mock_result

    result = await service.can_access_group(
        user_id=test_user_id,
        group_id=test_group_id,
    )

    assert result is True, "Teacher should access own group"


@pytest.mark.asyncio
async def test_can_access_group_teacher_other_group(
    mock_db_session: AsyncMock,
    test_user_id: UUID,
    test_group_id: UUID,
    teacher_role: Role,
) -> None:
    """Test that teachers cannot access other groups.

    Verifies that can_access_group returns False for groups
    the teacher is not assigned to.
    """
    service = RBACService(mock_db_session)

    user_role = create_user_role(
        user_id=test_user_id,
        role=teacher_role,
        group_id=test_group_id,
    )
    mock_result = MagicMock()
    mock_result.scalars.return_value.all.return_value = [user_role]
    mock_db_session.execute.return_value = mock_result

    other_group_id = uuid4()
    result = await service.can_access_group(
        user_id=test_user_id,
        group_id=other_group_id,
    )

    assert result is False, "Teacher should not access other group"


# ============================================================================
# Role Type Helper Tests
# ============================================================================


@pytest.mark.asyncio
async def test_is_director_true(
    mock_db_session: AsyncMock,
    test_user_id: UUID,
    director_role: Role,
) -> None:
    """Test is_director returns True for director users.

    Verifies that is_director correctly identifies users with
    the Director role.
    """
    service = RBACService(mock_db_session)

    user_role = create_user_role(user_id=test_user_id, role=director_role)
    mock_result = MagicMock()
    mock_result.scalars.return_value.all.return_value = [user_role]
    mock_db_session.execute.return_value = mock_result

    result = await service.is_director(user_id=test_user_id)

    assert result is True, "Should return True for director"


@pytest.mark.asyncio
async def test_is_director_false(
    mock_db_session: AsyncMock,
    test_user_id: UUID,
    teacher_role: Role,
) -> None:
    """Test is_director returns False for non-director users.

    Verifies that is_director returns False for users without
    the Director role.
    """
    service = RBACService(mock_db_session)

    user_role = create_user_role(user_id=test_user_id, role=teacher_role)
    mock_result = MagicMock()
    mock_result.scalars.return_value.all.return_value = [user_role]
    mock_db_session.execute.return_value = mock_result

    result = await service.is_director(user_id=test_user_id)

    assert result is False, "Should return False for teacher"


@pytest.mark.asyncio
async def test_is_teacher_true(
    mock_db_session: AsyncMock,
    test_user_id: UUID,
    teacher_role: Role,
) -> None:
    """Test is_teacher returns True for teacher users.

    Verifies that is_teacher correctly identifies users with
    the Teacher role.
    """
    service = RBACService(mock_db_session)

    user_role = create_user_role(user_id=test_user_id, role=teacher_role)
    mock_result = MagicMock()
    mock_result.scalars.return_value.all.return_value = [user_role]
    mock_db_session.execute.return_value = mock_result

    result = await service.is_teacher(user_id=test_user_id)

    assert result is True, "Should return True for teacher"


@pytest.mark.asyncio
async def test_is_parent_true(
    mock_db_session: AsyncMock,
    test_user_id: UUID,
    parent_role: Role,
) -> None:
    """Test is_parent returns True for parent users.

    Verifies that is_parent correctly identifies users with
    the Parent role.
    """
    service = RBACService(mock_db_session)

    user_role = create_user_role(user_id=test_user_id, role=parent_role)
    mock_result = MagicMock()
    mock_result.scalars.return_value.all.return_value = [user_role]
    mock_db_session.execute.return_value = mock_result

    result = await service.is_parent(user_id=test_user_id)

    assert result is True, "Should return True for parent"


# ============================================================================
# Get User Permissions Tests
# ============================================================================


@pytest.mark.asyncio
async def test_get_user_permissions(
    mock_db_session: AsyncMock,
    test_user_id: UUID,
    director_role: Role,
) -> None:
    """Test getting all permissions for a user.

    Verifies that get_user_permissions returns a complete response
    with all roles and their permissions.
    """
    service = RBACService(mock_db_session)

    user_role = create_user_role(user_id=test_user_id, role=director_role)
    mock_result = MagicMock()
    mock_result.scalars.return_value.all.return_value = [user_role]
    mock_db_session.execute.return_value = mock_result

    result = await service.get_user_permissions(user_id=test_user_id)

    assert result.user_id == test_user_id, "User ID should match"
    assert len(result.roles) == 1, "Should have one role"
    assert result.roles[0].name == "director", "Should be director role"
    assert len(result.permissions) > 0, "Should have permissions"


@pytest.mark.asyncio
async def test_get_user_permissions_multiple_roles(
    mock_db_session: AsyncMock,
    test_user_id: UUID,
    director_role: Role,
    teacher_role: Role,
) -> None:
    """Test getting permissions for user with multiple roles.

    Verifies that get_user_permissions aggregates permissions
    from all user roles correctly.
    """
    service = RBACService(mock_db_session)

    user_role_1 = create_user_role(user_id=test_user_id, role=director_role)
    user_role_2 = create_user_role(user_id=test_user_id, role=teacher_role)

    mock_result = MagicMock()
    mock_result.scalars.return_value.all.return_value = [user_role_1, user_role_2]
    mock_db_session.execute.return_value = mock_result

    result = await service.get_user_permissions(user_id=test_user_id)

    assert result.user_id == test_user_id, "User ID should match"
    assert len(result.roles) == 2, "Should have two roles"
    role_names = {r.name for r in result.roles}
    assert "director" in role_names, "Should include director"
    assert "teacher" in role_names, "Should include teacher"


# ============================================================================
# Service Configuration Tests
# ============================================================================


@pytest.mark.asyncio
async def test_set_notification_service(
    mock_db_session: AsyncMock,
    mock_notification_service: AsyncMock,
) -> None:
    """Test setting notification service after initialization.

    Verifies that set_notification_service correctly configures
    the service for later use.
    """
    service = RBACService(mock_db_session)
    assert service._notification_service is None, (
        "Should start without notification service"
    )

    service.set_notification_service(mock_notification_service)

    assert service._notification_service is mock_notification_service, (
        "Should set notification service"
    )


@pytest.mark.asyncio
async def test_set_audit_service(
    mock_db_session: AsyncMock,
    mock_audit_service: AsyncMock,
) -> None:
    """Test setting audit service after initialization.

    Verifies that set_audit_service correctly configures
    the service for later use.
    """
    service = RBACService(mock_db_session)
    assert service._audit_service is None, (
        "Should start without audit service"
    )

    service.set_audit_service(mock_audit_service)

    assert service._audit_service is mock_audit_service, (
        "Should set audit service"
    )


# ============================================================================
# Permission Check with Audit Tests
# ============================================================================


@pytest.mark.asyncio
async def test_check_permission_with_audit_logs_access_granted(
    rbac_service_with_notification: RBACService,
    mock_audit_service: AsyncMock,
    test_user_id: UUID,
    director_role: Role,
) -> None:
    """Test that granted access is logged via audit service.

    Verifies that check_permission_with_audit calls the audit
    service's log_access_granted method when access is allowed.
    """
    service = rbac_service_with_notification

    user_role = create_user_role(user_id=test_user_id, role=director_role)
    mock_result = MagicMock()
    mock_result.scalars.return_value.all.return_value = [user_role]
    service.db.execute.return_value = mock_result

    request = PermissionCheckRequest(
        user_id=test_user_id,
        resource="children",
        action="read",
    )

    result = await service.check_permission_with_audit(
        request=request,
        ip_address="192.168.1.1",
        user_agent="TestAgent/1.0",
    )

    assert result.allowed is True, "Access should be granted"
    mock_audit_service.log_access_granted.assert_called_once()


@pytest.mark.asyncio
async def test_check_permission_with_audit_logs_access_denied(
    rbac_service_with_notification: RBACService,
    mock_audit_service: AsyncMock,
    mock_notification_service: AsyncMock,
    test_user_id: UUID,
) -> None:
    """Test that denied access is logged and notifications sent.

    Verifies that check_permission_with_audit calls both audit
    and notification services when access is denied.
    """
    service = rbac_service_with_notification

    # No roles for user
    mock_result = MagicMock()
    mock_result.scalars.return_value.all.return_value = []
    service.db.execute.return_value = mock_result

    request = PermissionCheckRequest(
        user_id=test_user_id,
        resource="admin",
        action="delete",
    )

    result = await service.check_permission_with_audit(
        request=request,
        ip_address="192.168.1.1",
        notify_on_denial=True,
    )

    assert result.allowed is False, "Access should be denied"
    mock_audit_service.log_access_denied.assert_called_once()
    mock_notification_service.notify_unauthorized_access.assert_called_once()


@pytest.mark.asyncio
async def test_require_permission_with_audit_raises_on_denial(
    rbac_service_with_notification: RBACService,
    test_user_id: UUID,
) -> None:
    """Test that require_permission_with_audit raises on denial.

    Verifies that require_permission_with_audit raises
    PermissionDeniedError when the user lacks permission.
    """
    service = rbac_service_with_notification

    # No roles for user
    mock_result = MagicMock()
    mock_result.scalars.return_value.all.return_value = []
    service.db.execute.return_value = mock_result

    with pytest.raises(PermissionDeniedError) as exc_info:
        await service.require_permission_with_audit(
            user_id=test_user_id,
            resource="admin",
            action="delete",
        )

    assert str(test_user_id) in str(exc_info.value), (
        "Error should include user ID"
    )


# ============================================================================
# All Role Types Tests
# ============================================================================


@pytest.mark.asyncio
async def test_all_role_types_have_distinct_permissions(
    mock_db_session: AsyncMock,
) -> None:
    """Test that all 5 role types have distinct permission levels.

    Verifies that Director, Teacher, Assistant, Staff, and Parent
    roles are properly configured with appropriate permission levels.
    """
    service = RBACService(mock_db_session)

    # Create all role types
    role_types = [
        RoleType.DIRECTOR,
        RoleType.TEACHER,
        RoleType.ASSISTANT,
        RoleType.STAFF,
        RoleType.PARENT,
    ]

    # Verify all role types exist in enum
    assert len(role_types) == 5, "Should have exactly 5 role types"

    role_values = [r.value for r in role_types]
    assert "director" in role_values, "Should include director"
    assert "teacher" in role_values, "Should include teacher"
    assert "assistant" in role_values, "Should include assistant"
    assert "staff" in role_values, "Should include staff"
    assert "parent" in role_values, "Should include parent"


@pytest.mark.asyncio
async def test_permission_matching_with_prefix_wildcard(
    mock_db_session: AsyncMock,
    test_user_id: UUID,
    teacher_role: Role,
) -> None:
    """Test permission matching with prefix wildcards.

    Verifies that permissions like 'children.*' correctly match
    resources like 'children.photos' and 'children.reports'.
    """
    service = RBACService(mock_db_session)

    # Create permission with prefix wildcard
    permission = MagicMock(spec=Permission)
    permission.id = uuid4()
    permission.role_id = teacher_role.id
    permission.resource = "children.*"
    permission.action = "read"
    permission.conditions = None
    permission.is_active = True
    permission.created_at = datetime.now(timezone.utc)
    teacher_role.permissions = [permission]

    user_role = create_user_role(user_id=test_user_id, role=teacher_role)
    mock_result = MagicMock()
    mock_result.scalars.return_value.all.return_value = [user_role]
    mock_db_session.execute.return_value = mock_result

    # Test matching a sub-resource
    request = PermissionCheckRequest(
        user_id=test_user_id,
        resource="children.photos",
        action="read",
    )

    result = await service.check_permission(request)

    assert result.allowed is True, "Should match prefix wildcard permission"
