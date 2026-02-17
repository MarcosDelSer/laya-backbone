"""Unit tests for authentication dependencies in LAYA AI Service.

Tests get_current_user() and require_role() functions
from app/auth/dependencies.py.
"""

from datetime import datetime, timedelta, timezone
from unittest.mock import AsyncMock, MagicMock, patch
from uuid import uuid4

import pytest
import pytest_asyncio
from fastapi import HTTPException
from fastapi.security import HTTPAuthorizationCredentials

from app.auth.dependencies import get_current_user, require_role
from app.auth.models import UserRole
from app.auth.jwt import create_token

from tests.auth.conftest import (
    create_access_token,
    create_refresh_token,
    create_test_token,
    create_token_blacklist_in_db,
)


class TestGetCurrentUser:
    """Tests for get_current_user() dependency."""

    @pytest.fixture
    def mock_credentials(self):
        """Create mock HTTP credentials factory."""
        def _create(token: str):
            return HTTPAuthorizationCredentials(scheme="Bearer", credentials=token)
        return _create

    @pytest.mark.asyncio
    async def test_get_current_user_valid_token(self, auth_db_session, teacher_user, mock_credentials):
        """Test get_current_user returns payload for valid token."""
        token = create_access_token(
            user_id=str(teacher_user.id),
            email=teacher_user.email,
            role=teacher_user.role.value,
        )
        credentials = mock_credentials(token)

        payload = await get_current_user(credentials, auth_db_session)

        assert payload["sub"] == str(teacher_user.id)
        assert payload["email"] == teacher_user.email
        assert payload["role"] == teacher_user.role.value
        assert payload["type"] == "access"

    @pytest.mark.asyncio
    async def test_get_current_user_expired_token(self, auth_db_session, teacher_user, mock_credentials):
        """Test get_current_user raises 401 for expired token."""
        token = create_access_token(
            user_id=str(teacher_user.id),
            email=teacher_user.email,
            role=teacher_user.role.value,
            expires_delta_seconds=-3600,  # Expired 1 hour ago
        )
        credentials = mock_credentials(token)

        with pytest.raises(HTTPException) as exc_info:
            await get_current_user(credentials, auth_db_session)

        assert exc_info.value.status_code == 401

    @pytest.mark.asyncio
    async def test_get_current_user_invalid_token(self, auth_db_session, mock_credentials):
        """Test get_current_user raises 401 for invalid token."""
        credentials = mock_credentials("invalid.token.string")

        with pytest.raises(HTTPException) as exc_info:
            await get_current_user(credentials, auth_db_session)

        assert exc_info.value.status_code == 401

    @pytest.mark.asyncio
    async def test_get_current_user_blacklisted_token(self, auth_db_session, teacher_user, mock_credentials):
        """Test get_current_user raises 401 for blacklisted token."""
        token = create_access_token(
            user_id=str(teacher_user.id),
            email=teacher_user.email,
            role=teacher_user.role.value,
        )

        # Blacklist the token
        await create_token_blacklist_in_db(
            auth_db_session,
            token=token,
            user_id=teacher_user.id,
        )

        credentials = mock_credentials(token)

        with pytest.raises(HTTPException) as exc_info:
            await get_current_user(credentials, auth_db_session)

        assert exc_info.value.status_code == 401
        assert "revoked" in exc_info.value.detail

    @pytest.mark.asyncio
    async def test_get_current_user_wrong_signature(self, auth_db_session, mock_credentials):
        """Test get_current_user raises 401 for token with wrong signature."""
        import jwt
        from app.config import settings

        payload = {
            "sub": str(uuid4()),
            "email": "user@example.com",
            "role": "teacher",
            "type": "access",
            "iat": int(datetime.now(timezone.utc).timestamp()),
            "exp": int((datetime.now(timezone.utc) + timedelta(hours=1)).timestamp()),
            "aud": settings.jwt_audience,
            "iss": settings.jwt_issuer,
        }
        token = jwt.encode(payload, "wrong_secret_key", algorithm="HS256")
        credentials = mock_credentials(token)

        with pytest.raises(HTTPException) as exc_info:
            await get_current_user(credentials, auth_db_session)

        assert exc_info.value.status_code == 401

    @pytest.mark.asyncio
    async def test_get_current_user_all_roles(
        self,
        auth_db_session,
        admin_user,
        teacher_user,
        parent_user,
        accountant_user,
        staff_user,
        mock_credentials,
    ):
        """Test get_current_user works for all user roles."""
        users = [admin_user, teacher_user, parent_user, accountant_user, staff_user]

        for user in users:
            token = create_access_token(
                user_id=str(user.id),
                email=user.email,
                role=user.role.value,
            )
            credentials = mock_credentials(token)

            payload = await get_current_user(credentials, auth_db_session)

            assert payload["sub"] == str(user.id)
            assert payload["role"] == user.role.value


class TestRequireRole:
    """Tests for require_role() dependency factory."""

    def test_require_role_returns_callable(self):
        """Test require_role returns a callable dependency."""
        checker = require_role(UserRole.ADMIN)
        assert callable(checker)

    def test_require_role_accepts_multiple_roles(self):
        """Test require_role accepts multiple roles."""
        checker = require_role(UserRole.ADMIN, UserRole.TEACHER, UserRole.ACCOUNTANT)
        assert callable(checker)

    @pytest.mark.asyncio
    async def test_require_role_admin_access_allowed(self):
        """Test admin role is allowed when required."""
        checker = require_role(UserRole.ADMIN)

        # Simulate current_user payload
        current_user = {
            "sub": str(uuid4()),
            "email": "admin@example.com",
            "role": "admin",
            "type": "access",
        }

        result = await checker(current_user)

        assert result == current_user

    @pytest.mark.asyncio
    async def test_require_role_teacher_access_denied(self):
        """Test teacher is denied when only admin is required."""
        checker = require_role(UserRole.ADMIN)

        current_user = {
            "sub": str(uuid4()),
            "email": "teacher@example.com",
            "role": "teacher",
            "type": "access",
        }

        with pytest.raises(HTTPException) as exc_info:
            await checker(current_user)

        assert exc_info.value.status_code == 403
        assert "Access denied" in exc_info.value.detail
        assert "admin" in exc_info.value.detail
        assert "teacher" in exc_info.value.detail

    @pytest.mark.asyncio
    async def test_require_role_multiple_roles_allowed(self):
        """Test user with any of the allowed roles passes."""
        checker = require_role(UserRole.ADMIN, UserRole.ACCOUNTANT)

        # Test accountant is allowed
        current_user = {
            "sub": str(uuid4()),
            "email": "accountant@example.com",
            "role": "accountant",
            "type": "access",
        }

        result = await checker(current_user)
        assert result == current_user

    @pytest.mark.asyncio
    async def test_require_role_missing_role_claim(self):
        """Test raises 403 if role claim is missing."""
        checker = require_role(UserRole.ADMIN)

        current_user = {
            "sub": str(uuid4()),
            "email": "user@example.com",
            # No "role" claim
            "type": "access",
        }

        with pytest.raises(HTTPException) as exc_info:
            await checker(current_user)

        assert exc_info.value.status_code == 403
        assert "role not found" in exc_info.value.detail

    @pytest.mark.asyncio
    async def test_require_role_none_role_value(self):
        """Test raises 403 if role claim is None."""
        checker = require_role(UserRole.ADMIN)

        current_user = {
            "sub": str(uuid4()),
            "email": "user@example.com",
            "role": None,
            "type": "access",
        }

        with pytest.raises(HTTPException) as exc_info:
            await checker(current_user)

        assert exc_info.value.status_code == 403

    @pytest.mark.asyncio
    async def test_require_role_empty_role_value(self):
        """Test raises 403 if role claim is empty string."""
        checker = require_role(UserRole.ADMIN)

        current_user = {
            "sub": str(uuid4()),
            "email": "user@example.com",
            "role": "",
            "type": "access",
        }

        with pytest.raises(HTTPException) as exc_info:
            await checker(current_user)

        assert exc_info.value.status_code == 403

    @pytest.mark.asyncio
    async def test_require_role_all_roles(self):
        """Test each role is allowed when it's in the required list."""
        roles = [
            UserRole.ADMIN,
            UserRole.TEACHER,
            UserRole.PARENT,
            UserRole.ACCOUNTANT,
            UserRole.STAFF,
        ]

        for required_role in roles:
            checker = require_role(required_role)

            current_user = {
                "sub": str(uuid4()),
                "email": f"{required_role.value}@example.com",
                "role": required_role.value,
                "type": "access",
            }

            result = await checker(current_user)
            assert result["role"] == required_role.value

    @pytest.mark.asyncio
    async def test_require_role_wrong_role_shows_message(self):
        """Test error message shows required and actual roles."""
        checker = require_role(UserRole.ADMIN, UserRole.ACCOUNTANT)

        current_user = {
            "sub": str(uuid4()),
            "email": "staff@example.com",
            "role": "staff",
            "type": "access",
        }

        with pytest.raises(HTTPException) as exc_info:
            await checker(current_user)

        detail = exc_info.value.detail
        assert "admin" in detail
        assert "accountant" in detail
        assert "staff" in detail
        assert "Your role:" in detail


class TestRequireRoleIntegration:
    """Integration tests for require_role with get_current_user."""

    @pytest.fixture
    def mock_credentials(self):
        """Create mock HTTP credentials factory."""
        def _create(token: str):
            return HTTPAuthorizationCredentials(scheme="Bearer", credentials=token)
        return _create

    @pytest.mark.asyncio
    async def test_full_auth_flow_admin_allowed(self, auth_db_session, admin_user, mock_credentials):
        """Test full auth flow: token -> get_current_user -> require_role."""
        token = create_access_token(
            user_id=str(admin_user.id),
            email=admin_user.email,
            role=admin_user.role.value,
        )
        credentials = mock_credentials(token)

        # Get current user
        current_user = await get_current_user(credentials, auth_db_session)

        # Check role
        checker = require_role(UserRole.ADMIN)
        result = await checker(current_user)

        assert result["role"] == "admin"

    @pytest.mark.asyncio
    async def test_full_auth_flow_teacher_denied_admin(self, auth_db_session, teacher_user, mock_credentials):
        """Test full auth flow: teacher denied admin-only resource."""
        token = create_access_token(
            user_id=str(teacher_user.id),
            email=teacher_user.email,
            role=teacher_user.role.value,
        )
        credentials = mock_credentials(token)

        # Get current user
        current_user = await get_current_user(credentials, auth_db_session)

        # Check role - should fail
        checker = require_role(UserRole.ADMIN)

        with pytest.raises(HTTPException) as exc_info:
            await checker(current_user)

        assert exc_info.value.status_code == 403

    @pytest.mark.asyncio
    async def test_full_auth_flow_multi_role_access(self, auth_db_session, teacher_user, mock_credentials):
        """Test full auth flow with multiple allowed roles."""
        token = create_access_token(
            user_id=str(teacher_user.id),
            email=teacher_user.email,
            role=teacher_user.role.value,
        )
        credentials = mock_credentials(token)

        # Get current user
        current_user = await get_current_user(credentials, auth_db_session)

        # Check role - teacher should be allowed
        checker = require_role(UserRole.ADMIN, UserRole.TEACHER)
        result = await checker(current_user)

        assert result["role"] == "teacher"

    @pytest.mark.asyncio
    async def test_blacklisted_token_fails_before_role_check(
        self, auth_db_session, admin_user, mock_credentials
    ):
        """Test blacklisted token fails in get_current_user before role check."""
        token = create_access_token(
            user_id=str(admin_user.id),
            email=admin_user.email,
            role=admin_user.role.value,
        )

        # Blacklist the token
        await create_token_blacklist_in_db(
            auth_db_session,
            token=token,
            user_id=admin_user.id,
        )

        credentials = mock_credentials(token)

        # Should fail at get_current_user, not require_role
        with pytest.raises(HTTPException) as exc_info:
            await get_current_user(credentials, auth_db_session)

        assert exc_info.value.status_code == 401
        assert "revoked" in exc_info.value.detail


class TestRequireRoleEdgeCases:
    """Edge case tests for require_role."""

    @pytest.mark.asyncio
    async def test_require_role_preserves_all_claims(self):
        """Test require_role returns all claims from current_user."""
        checker = require_role(UserRole.ADMIN)

        current_user = {
            "sub": str(uuid4()),
            "email": "admin@example.com",
            "role": "admin",
            "type": "access",
            "iat": 1234567890,
            "exp": 1234567890 + 3600,
            "custom_claim": "custom_value",
        }

        result = await checker(current_user)

        # All claims should be preserved
        assert result == current_user
        assert result["custom_claim"] == "custom_value"

    @pytest.mark.asyncio
    async def test_require_role_case_sensitive(self):
        """Test role matching is case sensitive."""
        checker = require_role(UserRole.ADMIN)

        # Role is uppercase "ADMIN" but enum value is "admin"
        current_user = {
            "sub": str(uuid4()),
            "email": "admin@example.com",
            "role": "ADMIN",  # Wrong case
            "type": "access",
        }

        with pytest.raises(HTTPException) as exc_info:
            await checker(current_user)

        assert exc_info.value.status_code == 403

    @pytest.mark.asyncio
    async def test_require_role_unknown_role(self):
        """Test user with unknown role is denied."""
        checker = require_role(UserRole.ADMIN, UserRole.TEACHER)

        current_user = {
            "sub": str(uuid4()),
            "email": "hacker@example.com",
            "role": "superadmin",  # Unknown role
            "type": "access",
        }

        with pytest.raises(HTTPException) as exc_info:
            await checker(current_user)

        assert exc_info.value.status_code == 403

    @pytest.mark.asyncio
    async def test_require_role_single_role_exact_match(self):
        """Test single role requirement with exact match."""
        # Test each role individually
        test_cases = [
            (UserRole.ADMIN, "admin"),
            (UserRole.TEACHER, "teacher"),
            (UserRole.PARENT, "parent"),
            (UserRole.ACCOUNTANT, "accountant"),
            (UserRole.STAFF, "staff"),
        ]

        for role_enum, role_value in test_cases:
            checker = require_role(role_enum)

            current_user = {
                "sub": str(uuid4()),
                "email": f"{role_value}@example.com",
                "role": role_value,
                "type": "access",
            }

            result = await checker(current_user)
            assert result["role"] == role_value


class TestDependencyChaining:
    """Tests for dependency chaining behavior."""

    @pytest.mark.asyncio
    async def test_role_checker_can_be_reused(self):
        """Test that role checker can be called multiple times."""
        checker = require_role(UserRole.ADMIN)

        users = [
            {"sub": str(uuid4()), "email": "admin1@example.com", "role": "admin"},
            {"sub": str(uuid4()), "email": "admin2@example.com", "role": "admin"},
        ]

        for user in users:
            result = await checker(user)
            assert result["role"] == "admin"

    @pytest.mark.asyncio
    async def test_different_checkers_independent(self):
        """Test that different checkers are independent."""
        admin_checker = require_role(UserRole.ADMIN)
        teacher_checker = require_role(UserRole.TEACHER)

        admin_user = {
            "sub": str(uuid4()),
            "email": "admin@example.com",
            "role": "admin",
        }

        teacher_user = {
            "sub": str(uuid4()),
            "email": "teacher@example.com",
            "role": "teacher",
        }

        # Admin passes admin checker
        result = await admin_checker(admin_user)
        assert result["role"] == "admin"

        # Teacher passes teacher checker
        result = await teacher_checker(teacher_user)
        assert result["role"] == "teacher"

        # Admin fails teacher checker
        with pytest.raises(HTTPException):
            await teacher_checker(admin_user)

        # Teacher fails admin checker
        with pytest.raises(HTTPException):
            await admin_checker(teacher_user)


class TestMockedDependencies:
    """Tests using mocked database for isolated dependency testing."""

    @pytest.mark.asyncio
    async def test_get_current_user_calls_verify_token(self):
        """Test get_current_user delegates to verify_token."""
        mock_db = AsyncMock()
        mock_credentials = HTTPAuthorizationCredentials(
            scheme="Bearer",
            credentials=create_token(
                subject="user123",
                additional_claims={"role": "teacher", "email": "test@example.com"},
            ),
        )

        # Mock the blacklist query to return no results
        mock_result = MagicMock()
        mock_result.scalar_one_or_none.return_value = None
        mock_db.execute.return_value = mock_result

        payload = await get_current_user(mock_credentials, mock_db)

        # Verify database was queried for blacklist
        mock_db.execute.assert_called_once()
        assert payload["sub"] == "user123"

    @pytest.mark.asyncio
    async def test_require_role_factory_creates_new_checker(self):
        """Test require_role creates new checker each time."""
        checker1 = require_role(UserRole.ADMIN)
        checker2 = require_role(UserRole.ADMIN)

        # Each call should create a new function
        assert checker1 is not checker2

    @pytest.mark.asyncio
    async def test_require_role_uses_role_values(self):
        """Test require_role uses UserRole enum values correctly."""
        # Create checker with multiple roles
        checker = require_role(UserRole.ADMIN, UserRole.TEACHER, UserRole.ACCOUNTANT)

        # Test each allowed role
        for role_value in ["admin", "teacher", "accountant"]:
            current_user = {
                "sub": str(uuid4()),
                "email": f"{role_value}@example.com",
                "role": role_value,
            }
            result = await checker(current_user)
            assert result["role"] == role_value

        # Test disallowed roles
        for role_value in ["parent", "staff"]:
            current_user = {
                "sub": str(uuid4()),
                "email": f"{role_value}@example.com",
                "role": role_value,
            }
            with pytest.raises(HTTPException):
                await checker(current_user)
