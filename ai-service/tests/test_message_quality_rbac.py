"""Unit tests for message quality RBAC (Role-Based Access Control).

Tests that message quality endpoints correctly enforce role-based access:
- Admins can access all endpoints
- Teachers can access analysis, rewrite, templates, history
- Parents, Staff, Accountants are denied access to message quality features
- Analytics and settings are admin-only
"""

from uuid import uuid4

import pytest
from fastapi import HTTPException

from app.auth.dependencies import require_role
from app.auth.models import UserRole


# ============================================================================
# Message Quality Analyze/Rewrite/Templates/History RBAC Tests
# (Admin + Teacher allowed)
# ============================================================================


class TestEducatorEndpointsRBAC:
    """Tests for endpoints that allow Admin and Teacher roles."""

    @pytest.mark.asyncio
    async def test_admin_allowed(self):
        """Test that admin can access educator endpoints."""
        checker = require_role(UserRole.ADMIN, UserRole.TEACHER)

        admin_user = {
            "sub": str(uuid4()),
            "email": "admin@example.com",
            "role": "admin",
            "type": "access",
        }

        result = await checker(admin_user)
        assert result == admin_user

    @pytest.mark.asyncio
    async def test_teacher_allowed(self):
        """Test that teacher can access educator endpoints."""
        checker = require_role(UserRole.ADMIN, UserRole.TEACHER)

        teacher_user = {
            "sub": str(uuid4()),
            "email": "teacher@example.com",
            "role": "teacher",
            "type": "access",
        }

        result = await checker(teacher_user)
        assert result == teacher_user

    @pytest.mark.asyncio
    async def test_parent_denied(self):
        """Test that parent is denied access to educator endpoints."""
        checker = require_role(UserRole.ADMIN, UserRole.TEACHER)

        parent_user = {
            "sub": str(uuid4()),
            "email": "parent@example.com",
            "role": "parent",
            "type": "access",
        }

        with pytest.raises(HTTPException) as exc_info:
            await checker(parent_user)

        assert exc_info.value.status_code == 403
        assert "Access denied" in exc_info.value.detail

    @pytest.mark.asyncio
    async def test_staff_denied(self):
        """Test that staff is denied access to educator endpoints."""
        checker = require_role(UserRole.ADMIN, UserRole.TEACHER)

        staff_user = {
            "sub": str(uuid4()),
            "email": "staff@example.com",
            "role": "staff",
            "type": "access",
        }

        with pytest.raises(HTTPException) as exc_info:
            await checker(staff_user)

        assert exc_info.value.status_code == 403
        assert "Access denied" in exc_info.value.detail

    @pytest.mark.asyncio
    async def test_accountant_denied(self):
        """Test that accountant is denied access to educator endpoints."""
        checker = require_role(UserRole.ADMIN, UserRole.TEACHER)

        accountant_user = {
            "sub": str(uuid4()),
            "email": "accountant@example.com",
            "role": "accountant",
            "type": "access",
        }

        with pytest.raises(HTTPException) as exc_info:
            await checker(accountant_user)

        assert exc_info.value.status_code == 403
        assert "Access denied" in exc_info.value.detail


# ============================================================================
# Message Quality Analytics/Settings RBAC Tests (Admin only)
# ============================================================================


class TestAdminOnlyEndpointsRBAC:
    """Tests for endpoints that only allow Admin role."""

    @pytest.mark.asyncio
    async def test_admin_allowed(self):
        """Test that admin can access admin-only endpoints."""
        checker = require_role(UserRole.ADMIN)

        admin_user = {
            "sub": str(uuid4()),
            "email": "admin@example.com",
            "role": "admin",
            "type": "access",
        }

        result = await checker(admin_user)
        assert result == admin_user

    @pytest.mark.asyncio
    async def test_teacher_denied(self):
        """Test that teacher is denied access to admin-only endpoints."""
        checker = require_role(UserRole.ADMIN)

        teacher_user = {
            "sub": str(uuid4()),
            "email": "teacher@example.com",
            "role": "teacher",
            "type": "access",
        }

        with pytest.raises(HTTPException) as exc_info:
            await checker(teacher_user)

        assert exc_info.value.status_code == 403
        assert "Access denied" in exc_info.value.detail
        assert "admin" in exc_info.value.detail.lower()

    @pytest.mark.asyncio
    async def test_parent_denied(self):
        """Test that parent is denied access to admin-only endpoints."""
        checker = require_role(UserRole.ADMIN)

        parent_user = {
            "sub": str(uuid4()),
            "email": "parent@example.com",
            "role": "parent",
            "type": "access",
        }

        with pytest.raises(HTTPException) as exc_info:
            await checker(parent_user)

        assert exc_info.value.status_code == 403
        assert "Access denied" in exc_info.value.detail

    @pytest.mark.asyncio
    async def test_staff_denied(self):
        """Test that staff is denied access to admin-only endpoints."""
        checker = require_role(UserRole.ADMIN)

        staff_user = {
            "sub": str(uuid4()),
            "email": "staff@example.com",
            "role": "staff",
            "type": "access",
        }

        with pytest.raises(HTTPException) as exc_info:
            await checker(staff_user)

        assert exc_info.value.status_code == 403
        assert "Access denied" in exc_info.value.detail

    @pytest.mark.asyncio
    async def test_accountant_denied(self):
        """Test that accountant is denied access to admin-only endpoints."""
        checker = require_role(UserRole.ADMIN)

        accountant_user = {
            "sub": str(uuid4()),
            "email": "accountant@example.com",
            "role": "accountant",
            "type": "access",
        }

        with pytest.raises(HTTPException) as exc_info:
            await checker(accountant_user)

        assert exc_info.value.status_code == 403
        assert "Access denied" in exc_info.value.detail


# ============================================================================
# Role Access Matrix Verification Tests
# ============================================================================


class TestMessageQualityRoleAccessMatrix:
    """Comprehensive tests verifying the complete role access matrix."""

    @pytest.mark.asyncio
    async def test_analyze_endpoint_access_matrix(self):
        """Test access matrix for POST /analyze endpoint (Admin + Teacher)."""
        checker = require_role(UserRole.ADMIN, UserRole.TEACHER)

        # Admin should have access
        admin_user = {"sub": str(uuid4()), "email": "admin@example.com", "role": "admin"}
        result = await checker(admin_user)
        assert result["role"] == "admin"

        # Teacher should have access
        teacher_user = {"sub": str(uuid4()), "email": "teacher@example.com", "role": "teacher"}
        result = await checker(teacher_user)
        assert result["role"] == "teacher"

        # All others should be denied
        denied_roles = ["parent", "staff", "accountant"]
        for role in denied_roles:
            user = {"sub": str(uuid4()), "email": f"{role}@example.com", "role": role}
            with pytest.raises(HTTPException) as exc_info:
                await checker(user)
            assert exc_info.value.status_code == 403

    @pytest.mark.asyncio
    async def test_rewrite_endpoint_access_matrix(self):
        """Test access matrix for POST /rewrite endpoint (Admin + Teacher)."""
        checker = require_role(UserRole.ADMIN, UserRole.TEACHER)

        # Admin should have access
        admin_user = {"sub": str(uuid4()), "email": "admin@example.com", "role": "admin"}
        result = await checker(admin_user)
        assert result["role"] == "admin"

        # Teacher should have access
        teacher_user = {"sub": str(uuid4()), "email": "teacher@example.com", "role": "teacher"}
        result = await checker(teacher_user)
        assert result["role"] == "teacher"

        # All others should be denied
        denied_roles = ["parent", "staff", "accountant"]
        for role in denied_roles:
            user = {"sub": str(uuid4()), "email": f"{role}@example.com", "role": role}
            with pytest.raises(HTTPException) as exc_info:
                await checker(user)
            assert exc_info.value.status_code == 403

    @pytest.mark.asyncio
    async def test_history_endpoint_access_matrix(self):
        """Test access matrix for GET /history endpoint (Admin + Teacher)."""
        checker = require_role(UserRole.ADMIN, UserRole.TEACHER)

        # Admin should have access
        admin_user = {"sub": str(uuid4()), "email": "admin@example.com", "role": "admin"}
        result = await checker(admin_user)
        assert result["role"] == "admin"

        # Teacher should have access
        teacher_user = {"sub": str(uuid4()), "email": "teacher@example.com", "role": "teacher"}
        result = await checker(teacher_user)
        assert result["role"] == "teacher"

        # All others should be denied
        denied_roles = ["parent", "staff", "accountant"]
        for role in denied_roles:
            user = {"sub": str(uuid4()), "email": f"{role}@example.com", "role": role}
            with pytest.raises(HTTPException) as exc_info:
                await checker(user)
            assert exc_info.value.status_code == 403

    @pytest.mark.asyncio
    async def test_templates_endpoint_access_matrix(self):
        """Test access matrix for GET /templates endpoint (Admin + Teacher)."""
        checker = require_role(UserRole.ADMIN, UserRole.TEACHER)

        # Admin should have access
        admin_user = {"sub": str(uuid4()), "email": "admin@example.com", "role": "admin"}
        result = await checker(admin_user)
        assert result["role"] == "admin"

        # Teacher should have access
        teacher_user = {"sub": str(uuid4()), "email": "teacher@example.com", "role": "teacher"}
        result = await checker(teacher_user)
        assert result["role"] == "teacher"

        # All others should be denied
        denied_roles = ["parent", "staff", "accountant"]
        for role in denied_roles:
            user = {"sub": str(uuid4()), "email": f"{role}@example.com", "role": role}
            with pytest.raises(HTTPException) as exc_info:
                await checker(user)
            assert exc_info.value.status_code == 403

    @pytest.mark.asyncio
    async def test_analytics_endpoint_access_matrix(self):
        """Test access matrix for GET /analytics endpoint (Admin only)."""
        checker = require_role(UserRole.ADMIN)

        # Only admin should have access
        admin_user = {"sub": str(uuid4()), "email": "admin@example.com", "role": "admin"}
        result = await checker(admin_user)
        assert result["role"] == "admin"

        # All others should be denied
        denied_roles = ["teacher", "parent", "staff", "accountant"]
        for role in denied_roles:
            user = {"sub": str(uuid4()), "email": f"{role}@example.com", "role": role}
            with pytest.raises(HTTPException) as exc_info:
                await checker(user)
            assert exc_info.value.status_code == 403

    @pytest.mark.asyncio
    async def test_settings_endpoint_access_matrix(self):
        """Test access matrix for PUT /settings endpoint (Admin only)."""
        checker = require_role(UserRole.ADMIN)

        # Only admin should have access
        admin_user = {"sub": str(uuid4()), "email": "admin@example.com", "role": "admin"}
        result = await checker(admin_user)
        assert result["role"] == "admin"

        # All others should be denied
        denied_roles = ["teacher", "parent", "staff", "accountant"]
        for role in denied_roles:
            user = {"sub": str(uuid4()), "email": f"{role}@example.com", "role": role}
            with pytest.raises(HTTPException) as exc_info:
                await checker(user)
            assert exc_info.value.status_code == 403


# ============================================================================
# Edge Cases and Security Tests
# ============================================================================


class TestMessageQualityRBACEdgeCases:
    """Edge case and security tests for message quality RBAC."""

    @pytest.mark.asyncio
    async def test_missing_role_claim_denied(self):
        """Test that user with missing role claim is denied."""
        checker = require_role(UserRole.ADMIN, UserRole.TEACHER)

        user_no_role = {
            "sub": str(uuid4()),
            "email": "user@example.com",
            # No "role" claim
            "type": "access",
        }

        with pytest.raises(HTTPException) as exc_info:
            await checker(user_no_role)

        assert exc_info.value.status_code == 403
        assert "role not found" in exc_info.value.detail

    @pytest.mark.asyncio
    async def test_empty_role_denied(self):
        """Test that user with empty role is denied."""
        checker = require_role(UserRole.ADMIN, UserRole.TEACHER)

        user_empty_role = {
            "sub": str(uuid4()),
            "email": "user@example.com",
            "role": "",
            "type": "access",
        }

        with pytest.raises(HTTPException) as exc_info:
            await checker(user_empty_role)

        assert exc_info.value.status_code == 403

    @pytest.mark.asyncio
    async def test_invalid_role_denied(self):
        """Test that user with invalid/unknown role is denied."""
        checker = require_role(UserRole.ADMIN, UserRole.TEACHER)

        user_invalid_role = {
            "sub": str(uuid4()),
            "email": "hacker@example.com",
            "role": "superadmin",  # Invalid role
            "type": "access",
        }

        with pytest.raises(HTTPException) as exc_info:
            await checker(user_invalid_role)

        assert exc_info.value.status_code == 403

    @pytest.mark.asyncio
    async def test_case_sensitive_role_matching(self):
        """Test that role matching is case sensitive."""
        checker = require_role(UserRole.ADMIN)

        user_wrong_case = {
            "sub": str(uuid4()),
            "email": "admin@example.com",
            "role": "ADMIN",  # Wrong case
            "type": "access",
        }

        with pytest.raises(HTTPException) as exc_info:
            await checker(user_wrong_case)

        assert exc_info.value.status_code == 403
