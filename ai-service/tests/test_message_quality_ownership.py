"""Unit tests for message quality ownership validation.

Tests that ownership validation correctly enforces access control:
- Admins can access all message quality data
- Teachers can only access their own message quality data
- 403 errors are raised when teachers try to access other teachers' data
"""

from unittest.mock import AsyncMock, MagicMock
from uuid import uuid4

import pytest
from fastapi import HTTPException

from app.services.message_quality_service import MessageQualityService


@pytest.fixture
def mock_db_session() -> AsyncMock:
    """Create a mock async database session."""
    session = AsyncMock()
    session.add = MagicMock()
    session.commit = AsyncMock()
    session.refresh = AsyncMock()
    return session


@pytest.fixture
def message_quality_service(mock_db_session: AsyncMock) -> MessageQualityService:
    """Create a MessageQualityService instance with mock database."""
    return MessageQualityService(mock_db_session)


def test_ownership_validation_admin_can_access_any_resource(message_quality_service):
    """Test that admins can access any user's resources."""
    admin_user = {
        "sub": str(uuid4()),
        "role": "admin",
    }
    resource_owner_id = uuid4()  # Different user

    # Should not raise any exception
    message_quality_service.check_ownership(resource_owner_id, admin_user)


def test_ownership_validation_teacher_can_access_own_resource(message_quality_service):
    """Test that teachers can access their own resources."""
    user_id = uuid4()
    teacher_user = {
        "sub": str(user_id),
        "role": "teacher",
    }

    # Should not raise any exception
    message_quality_service.check_ownership(user_id, teacher_user)


def test_ownership_validation_teacher_cannot_access_other_resource(message_quality_service):
    """Test that teachers cannot access other teachers' resources."""
    teacher_user = {
        "sub": str(uuid4()),
        "role": "teacher",
    }
    other_teacher_id = uuid4()  # Different user

    # Should raise 403 Forbidden
    with pytest.raises(HTTPException) as exc_info:
        message_quality_service.check_ownership(other_teacher_id, teacher_user)

    assert exc_info.value.status_code == 403
    assert "permission" in exc_info.value.detail.lower()
    assert "educators can only access their own messages" in exc_info.value.detail.lower()


def test_ownership_validation_with_user_id_field(message_quality_service):
    """Test that ownership validation works with 'user_id' field (fallback)."""
    user_id = uuid4()
    teacher_user = {
        "user_id": str(user_id),  # Using user_id instead of sub
        "role": "teacher",
    }

    # Should not raise any exception
    message_quality_service.check_ownership(user_id, teacher_user)


def test_ownership_validation_parent_cannot_access_resource(message_quality_service):
    """Test that parents (non-educators) cannot access resources."""
    parent_user = {
        "sub": str(uuid4()),
        "role": "parent",
    }
    resource_owner_id = uuid4()

    # Should raise 403 Forbidden (parent is not admin and doesn't own resource)
    with pytest.raises(HTTPException) as exc_info:
        message_quality_service.check_ownership(resource_owner_id, parent_user)

    assert exc_info.value.status_code == 403


def test_ownership_validation(message_quality_service):
    """Comprehensive test for ownership validation functionality.

    This test verifies that:
    - Admins can access all resources (bypass ownership check)
    - Teachers can access their own resources
    - Teachers cannot access other teachers' resources (403 error)
    - Ownership validation properly uses has_admin_access()
    """
    # Test 1: Admin can access any resource
    admin_user = {
        "sub": str(uuid4()),
        "role": "admin",
    }
    other_user_id = uuid4()
    message_quality_service.check_ownership(other_user_id, admin_user)  # Should not raise

    # Test 2: Teacher can access own resource
    teacher_id = uuid4()
    teacher_user = {
        "sub": str(teacher_id),
        "role": "teacher",
    }
    message_quality_service.check_ownership(teacher_id, teacher_user)  # Should not raise

    # Test 3: Teacher cannot access another teacher's resource
    another_teacher_id = uuid4()
    with pytest.raises(HTTPException) as exc_info:
        message_quality_service.check_ownership(another_teacher_id, teacher_user)

    assert exc_info.value.status_code == 403
    assert "permission" in exc_info.value.detail.lower()
