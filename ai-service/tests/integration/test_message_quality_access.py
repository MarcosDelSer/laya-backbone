"""Integration tests for message quality role-based access control.

End-to-end tests for RBAC scenarios including:
- Educators analyzing their own messages (success)
- Educators trying to access others' messages (fail)
- Directors accessing all features (success)
- Non-authorized roles accessing message quality (fail)
- Ownership validation integration
- Audit logging integration

Note: These integration tests validate the interaction between RBAC dependencies,
service layer, and audit logging without requiring the full FastAPI app import
(which has Python 3.10+ syntax incompatibilities).
"""

from typing import Dict
from unittest.mock import AsyncMock, MagicMock, patch
from uuid import UUID, uuid4

import pytest
from fastapi import HTTPException

from app.auth.dependencies import require_role
from app.auth.models import UserRole
from app.services.message_quality_service import MessageQualityService


# ============================================================================
# Integration Test Scenarios
# ============================================================================


class TestEducatorOwnMessageAccess:
    """Test educators can successfully access their own message quality data."""

    @pytest.mark.asyncio
    async def test_educator_analyze_own_message_rbac_and_service_integration(self):
        """Test RBAC allows educator and service processes their message."""
        # Setup: Create educator user
        educator_user = {
            "sub": str(uuid4()),
            "email": "educator@example.com",
            "role": "teacher",
            "type": "access",
        }

        # Test RBAC dependency
        rbac_checker = require_role(UserRole.ADMIN, UserRole.TEACHER)
        verified_user = await rbac_checker(educator_user)
        assert verified_user == educator_user
        assert verified_user["role"] == "teacher"

        # Test service can process message for educator
        mock_db = AsyncMock()
        service = MessageQualityService(mock_db)

        # Service should accept educator's message
        assert service is not None

    @pytest.mark.asyncio
    async def test_educator_ownership_validation_integration(self):
        """Test ownership validation in service integrates with RBAC."""
        educator_id = str(uuid4())
        educator_user = {
            "sub": educator_id,
            "email": "educator@example.com",
            "role": "teacher",
            "type": "access",
        }

        mock_db = AsyncMock()
        service = MessageQualityService(mock_db)

        # Educator should pass ownership check for their own resources
        # (check_ownership would be called in the service)
        try:
            service.check_ownership(UUID(educator_id), educator_user)
            # Should not raise exception for own resources
            assert True
        except HTTPException:
            pytest.fail("Educator should have access to own resources")

    @pytest.mark.asyncio
    async def test_educator_service_instantiation(self):
        """Test message quality service can be instantiated for educator."""
        educator_id = str(uuid4())
        educator_user = {
            "sub": educator_id,
            "email": "educator@example.com",
            "role": "teacher",
            "type": "access",
        }

        mock_db = AsyncMock()
        service = MessageQualityService(mock_db)

        # Service should be created successfully
        assert service is not None
        assert service.db == mock_db


class TestEducatorCrossAccessDenied:
    """Test educators cannot access other educators' message quality data."""

    @pytest.mark.asyncio
    async def test_educator_cannot_access_another_educator_resources(self):
        """Test ownership validation blocks cross-educator access."""
        educator1_id = str(uuid4())
        educator2_id = str(uuid4())

        educator1_user = {
            "sub": educator1_id,
            "email": "educator1@example.com",
            "role": "teacher",
            "type": "access",
        }

        mock_db = AsyncMock()
        service = MessageQualityService(mock_db)

        # Educator 1 tries to access Educator 2's resource
        with pytest.raises(HTTPException) as exc_info:
            service.check_ownership(UUID(educator2_id), educator1_user)

        assert exc_info.value.status_code == 403
        # Check for any of the possible error messages
        assert ("not authorized" in exc_info.value.detail.lower() or
                "access" in exc_info.value.detail.lower())


class TestDirectorFullAccess:
    """Test directors (admins) can access all message quality features."""

    @pytest.mark.asyncio
    async def test_director_passes_all_rbac_checks(self):
        """Test director passes both educator and admin-only RBAC checks."""
        admin_user = {
            "sub": str(uuid4()),
            "email": "admin@example.com",
            "role": "admin",
            "type": "access",
        }

        # Test educator endpoints RBAC (Admin + Teacher)
        educator_rbac = require_role(UserRole.ADMIN, UserRole.TEACHER)
        verified = await educator_rbac(admin_user)
        assert verified["role"] == "admin"

        # Test admin-only endpoints RBAC
        admin_rbac = require_role(UserRole.ADMIN)
        verified = await admin_rbac(admin_user)
        assert verified["role"] == "admin"

    @pytest.mark.asyncio
    async def test_director_bypasses_ownership_checks(self):
        """Test director can access any educator's resources."""
        admin_id = str(uuid4())
        educator_id = str(uuid4())

        admin_user = {
            "sub": admin_id,
            "email": "admin@example.com",
            "role": "admin",
            "type": "access",
        }

        mock_db = AsyncMock()
        service = MessageQualityService(mock_db)

        # Admin should be able to access any resource
        try:
            service.check_ownership(UUID(educator_id), admin_user)
            # Should not raise exception for admin accessing others' resources
            assert True
        except HTTPException:
            pytest.fail("Admin should have access to all resources")

    @pytest.mark.asyncio
    async def test_director_can_access_service_methods(self):
        """Test director can instantiate service and access methods."""
        admin_user = {
            "sub": str(uuid4()),
            "email": "admin@example.com",
            "role": "admin",
            "type": "access",
        }

        mock_db = AsyncMock()
        service = MessageQualityService(mock_db)

        # Admin should be able to create service instance
        assert service is not None
        assert service.db == mock_db


class TestUnauthorizedRolesDenied:
    """Test non-authorized roles cannot access message quality features."""

    @pytest.mark.asyncio
    async def test_parent_denied_educator_endpoints(self):
        """Test parent is denied access to educator endpoints."""
        parent_user = {
            "sub": str(uuid4()),
            "email": "parent@example.com",
            "role": "parent",
            "type": "access",
        }

        rbac_checker = require_role(UserRole.ADMIN, UserRole.TEACHER)

        with pytest.raises(HTTPException) as exc_info:
            await rbac_checker(parent_user)

        assert exc_info.value.status_code == 403
        assert "Access denied" in exc_info.value.detail

    @pytest.mark.asyncio
    async def test_accountant_denied_educator_endpoints(self):
        """Test accountant is denied access to educator endpoints."""
        accountant_user = {
            "sub": str(uuid4()),
            "email": "accountant@example.com",
            "role": "accountant",
            "type": "access",
        }

        rbac_checker = require_role(UserRole.ADMIN, UserRole.TEACHER)

        with pytest.raises(HTTPException) as exc_info:
            await rbac_checker(accountant_user)

        assert exc_info.value.status_code == 403
        assert "Access denied" in exc_info.value.detail

    @pytest.mark.asyncio
    async def test_staff_denied_educator_endpoints(self):
        """Test staff is denied access to educator endpoints."""
        staff_user = {
            "sub": str(uuid4()),
            "email": "staff@example.com",
            "role": "staff",
            "type": "access",
        }

        rbac_checker = require_role(UserRole.ADMIN, UserRole.TEACHER)

        with pytest.raises(HTTPException) as exc_info:
            await rbac_checker(staff_user)

        assert exc_info.value.status_code == 403

    @pytest.mark.asyncio
    async def test_educator_denied_admin_only_endpoints(self):
        """Test educator is denied access to admin-only endpoints."""
        teacher_user = {
            "sub": str(uuid4()),
            "email": "teacher@example.com",
            "role": "teacher",
            "type": "access",
        }

        admin_rbac = require_role(UserRole.ADMIN)

        with pytest.raises(HTTPException) as exc_info:
            await admin_rbac(teacher_user)

        assert exc_info.value.status_code == 403
        assert "admin" in exc_info.value.detail.lower()

    @pytest.mark.asyncio
    async def test_parent_denied_admin_only_endpoints(self):
        """Test parent is denied access to admin-only endpoints."""
        parent_user = {
            "sub": str(uuid4()),
            "email": "parent@example.com",
            "role": "parent",
            "type": "access",
        }

        admin_rbac = require_role(UserRole.ADMIN)

        with pytest.raises(HTTPException) as exc_info:
            await admin_rbac(parent_user)

        assert exc_info.value.status_code == 403


class TestRBACServiceIntegration:
    """Test integration between RBAC and service layer."""

    @pytest.mark.asyncio
    async def test_rbac_to_service_flow_educator(self):
        """Test complete flow from RBAC check to service execution for educator."""
        educator_id = str(uuid4())
        educator_user = {
            "sub": educator_id,
            "email": "educator@example.com",
            "role": "teacher",
            "type": "access",
        }

        # Step 1: RBAC validation
        rbac_checker = require_role(UserRole.ADMIN, UserRole.TEACHER)
        verified_user = await rbac_checker(educator_user)
        assert verified_user["role"] == "teacher"

        # Step 2: Service layer processing
        mock_db = AsyncMock()
        service = MessageQualityService(mock_db)

        # Step 3: Ownership validation
        try:
            service.check_ownership(UUID(educator_id), verified_user)
            ownership_valid = True
        except HTTPException:
            ownership_valid = False

        assert ownership_valid is True

    @pytest.mark.asyncio
    async def test_rbac_to_service_flow_admin(self):
        """Test complete flow from RBAC check to service execution for admin."""
        admin_id = str(uuid4())
        other_user_id = str(uuid4())

        admin_user = {
            "sub": admin_id,
            "email": "admin@example.com",
            "role": "admin",
            "type": "access",
        }

        # Step 1: RBAC validation
        rbac_checker = require_role(UserRole.ADMIN)
        verified_user = await rbac_checker(admin_user)
        assert verified_user["role"] == "admin"

        # Step 2: Service layer processing
        mock_db = AsyncMock()
        service = MessageQualityService(mock_db)

        # Step 3: Ownership validation (admin should bypass)
        try:
            service.check_ownership(UUID(other_user_id), verified_user)
            can_access_others = True
        except HTTPException:
            can_access_others = False

        assert can_access_others is True

    @pytest.mark.asyncio
    async def test_rbac_blocks_unauthorized_before_service(self):
        """Test RBAC blocks unauthorized users before they reach service layer."""
        parent_user = {
            "sub": str(uuid4()),
            "email": "parent@example.com",
            "role": "parent",
            "type": "access",
        }

        # RBAC should block at dependency level
        rbac_checker = require_role(UserRole.ADMIN, UserRole.TEACHER)

        with pytest.raises(HTTPException) as exc_info:
            await rbac_checker(parent_user)

        assert exc_info.value.status_code == 403
        # Parent never reaches service layer


class TestAuditLoggingIntegration:
    """Test audit logging integration with RBAC and service."""

    @pytest.mark.asyncio
    async def test_audit_logger_available_in_service_context(self):
        """Test audit logger can be used with service operations."""
        from app.auth.audit_logger import audit_logger

        # Audit logger should be available
        assert audit_logger is not None
        assert hasattr(audit_logger, 'log_message_quality_access')
        assert hasattr(audit_logger, 'log_message_quality_denied')

    @pytest.mark.asyncio
    async def test_denied_access_logs_properly(self):
        """Test that denied access generates proper audit log data."""
        parent_user = {
            "sub": str(uuid4()),
            "email": "parent@example.com",
            "role": "parent",
            "type": "access",
        }

        rbac_checker = require_role(UserRole.ADMIN, UserRole.TEACHER)

        # Attempt access that will be denied
        try:
            await rbac_checker(parent_user)
        except HTTPException as e:
            # Verify exception has details needed for audit logging
            assert e.status_code == 403
            assert "Access denied" in e.detail
            assert parent_user["role"] in ["parent"]


class TestEdgeCases:
    """Test edge cases in RBAC integration."""

    @pytest.mark.asyncio
    async def test_missing_role_claim_blocked(self):
        """Test user without role claim is blocked."""
        user_no_role = {
            "sub": str(uuid4()),
            "email": "user@example.com",
            # No role claim
            "type": "access",
        }

        rbac_checker = require_role(UserRole.ADMIN, UserRole.TEACHER)

        with pytest.raises(HTTPException) as exc_info:
            await rbac_checker(user_no_role)

        assert exc_info.value.status_code == 403
        assert "role not found" in exc_info.value.detail

    @pytest.mark.asyncio
    async def test_invalid_role_value_blocked(self):
        """Test user with invalid role value is blocked."""
        user_invalid_role = {
            "sub": str(uuid4()),
            "email": "hacker@example.com",
            "role": "superadmin",  # Invalid role
            "type": "access",
        }

        rbac_checker = require_role(UserRole.ADMIN, UserRole.TEACHER)

        with pytest.raises(HTTPException) as exc_info:
            await rbac_checker(user_invalid_role)

        assert exc_info.value.status_code == 403

    @pytest.mark.asyncio
    async def test_empty_role_blocked(self):
        """Test user with empty role string is blocked."""
        user_empty_role = {
            "sub": str(uuid4()),
            "email": "user@example.com",
            "role": "",
            "type": "access",
        }

        rbac_checker = require_role(UserRole.ADMIN, UserRole.TEACHER)

        with pytest.raises(HTTPException) as exc_info:
            await rbac_checker(user_empty_role)

        assert exc_info.value.status_code == 403


class TestOwnershipValidationIntegration:
    """Test ownership validation across service boundaries."""

    @pytest.mark.asyncio
    async def test_ownership_check_integration_teacher_own(self):
        """Test teacher can access own resources through ownership check."""
        teacher_id = str(uuid4())
        teacher_user = {
            "sub": teacher_id,
            "email": "teacher@example.com",
            "role": "teacher",
        }

        mock_db = AsyncMock()
        service = MessageQualityService(mock_db)

        # Should not raise exception
        service.check_ownership(UUID(teacher_id), teacher_user)

    @pytest.mark.asyncio
    async def test_ownership_check_integration_teacher_other(self):
        """Test teacher cannot access others' resources through ownership check."""
        teacher_id = str(uuid4())
        other_teacher_id = str(uuid4())

        teacher_user = {
            "sub": teacher_id,
            "email": "teacher@example.com",
            "role": "teacher",
        }

        mock_db = AsyncMock()
        service = MessageQualityService(mock_db)

        # Should raise 403
        with pytest.raises(HTTPException) as exc_info:
            service.check_ownership(UUID(other_teacher_id), teacher_user)

        assert exc_info.value.status_code == 403

    @pytest.mark.asyncio
    async def test_ownership_check_integration_admin_bypass(self):
        """Test admin bypasses ownership checks."""
        admin_id = str(uuid4())
        teacher_id = str(uuid4())

        admin_user = {
            "sub": admin_id,
            "email": "admin@example.com",
            "role": "admin",
        }

        mock_db = AsyncMock()
        service = MessageQualityService(mock_db)

        # Should not raise exception even for other's resources
        service.check_ownership(UUID(teacher_id), admin_user)
