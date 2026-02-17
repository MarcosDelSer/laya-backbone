"""API integration tests for MFA endpoints.

Tests cover:
- MFA status endpoint (GET /api/v1/mfa/status)
- MFA setup endpoint (POST /api/v1/mfa/setup)
- MFA verification endpoint (POST /api/v1/mfa/verify)
- MFA enable endpoint (POST /api/v1/mfa/enable)
- MFA disable endpoint (POST /api/v1/mfa/disable)
- Backup code generation (POST /api/v1/mfa/backup-codes)
- Backup code verification (POST /api/v1/mfa/backup-codes/verify)
- Backup code status (GET /api/v1/mfa/backup-codes/status)
- IP whitelist management (GET, POST, DELETE /api/v1/mfa/ip-whitelist)
- IP whitelist check (POST /api/v1/mfa/ip-whitelist/check)
- Session validation (POST /api/v1/mfa/validate)
- Admin lockout reset (POST /api/v1/mfa/admin/reset-lockout)
- Authentication requirements on protected endpoints
- Error handling and edge cases
"""

from datetime import datetime, timedelta, timezone
from uuid import uuid4

import pyotp
import pytest
from httpx import AsyncClient
from sqlalchemy.ext.asyncio import AsyncSession

from tests.conftest import (
    MockMFABackupCode,
    MockMFAIPWhitelist,
    MockMFASettings,
    create_mfa_settings_in_db,
)


# =============================================================================
# MFA Status Endpoint Tests
# =============================================================================


class TestMFAStatusEndpoint:
    """Tests for GET /api/v1/mfa/status endpoint."""

    @pytest.mark.asyncio
    async def test_mfa_status_returns_200_when_not_configured(
        self,
        client: AsyncClient,
        auth_headers: dict,
    ):
        """Test status endpoint returns 200 with default values when MFA not set up."""
        response = await client.get(
            "/api/v1/mfa/status",
            headers=auth_headers,
        )

        assert response.status_code == 200
        data = response.json()
        assert "user_id" in data
        assert data["is_enabled"] is False
        assert data["method"] is None
        assert data["has_backup_codes"] is False
        assert data["backup_codes_remaining"] == 0
        assert data["ip_whitelist_count"] == 0
        assert data["is_locked"] is False

    @pytest.mark.asyncio
    async def test_mfa_status_returns_enabled_state(
        self,
        client: AsyncClient,
        auth_headers: dict,
        mfa_settings_enabled: MockMFASettings,
    ):
        """Test status endpoint returns enabled state."""
        response = await client.get(
            "/api/v1/mfa/status",
            headers=auth_headers,
        )

        assert response.status_code == 200
        data = response.json()
        assert data["is_enabled"] is True
        assert data["method"] == "totp"

    @pytest.mark.asyncio
    async def test_mfa_status_requires_auth(
        self,
        client: AsyncClient,
    ):
        """Test status endpoint requires authentication."""
        response = await client.get("/api/v1/mfa/status")

        assert response.status_code == 401

    @pytest.mark.asyncio
    async def test_mfa_status_shows_backup_codes_remaining(
        self,
        client: AsyncClient,
        auth_headers: dict,
        mfa_with_backup_codes: tuple[MockMFASettings, list[MockMFABackupCode]],
    ):
        """Test status endpoint shows correct backup code counts."""
        response = await client.get(
            "/api/v1/mfa/status",
            headers=auth_headers,
        )

        assert response.status_code == 200
        data = response.json()
        assert data["has_backup_codes"] is True
        assert data["backup_codes_remaining"] == 4  # 5 total, 1 used

    @pytest.mark.asyncio
    async def test_mfa_status_shows_ip_whitelist_count(
        self,
        client: AsyncClient,
        auth_headers: dict,
        mfa_with_ip_whitelist: tuple[MockMFASettings, list[MockMFAIPWhitelist]],
    ):
        """Test status endpoint shows correct IP whitelist count."""
        response = await client.get(
            "/api/v1/mfa/status",
            headers=auth_headers,
        )

        assert response.status_code == 200
        data = response.json()
        assert data["ip_whitelist_count"] == 2  # Only active entries

    @pytest.mark.asyncio
    async def test_mfa_status_shows_locked_state(
        self,
        client: AsyncClient,
        auth_headers: dict,
        mfa_settings_locked: MockMFASettings,
    ):
        """Test status endpoint shows locked state."""
        response = await client.get(
            "/api/v1/mfa/status",
            headers=auth_headers,
        )

        assert response.status_code == 200
        data = response.json()
        assert data["is_locked"] is True
        assert data["locked_until"] is not None


# =============================================================================
# MFA Setup Endpoint Tests
# =============================================================================


class TestMFASetupEndpoint:
    """Tests for POST /api/v1/mfa/setup endpoint."""

    @pytest.mark.asyncio
    async def test_mfa_setup_returns_200_with_secret(
        self,
        client: AsyncClient,
        auth_headers: dict,
    ):
        """Test setup endpoint returns secret and QR code URI."""
        response = await client.post(
            "/api/v1/mfa/setup",
            headers=auth_headers,
            json={"method": "totp"},
        )

        assert response.status_code == 200
        data = response.json()
        assert "secret" in data
        assert len(data["secret"]) >= 16
        assert "qr_code_uri" in data
        assert "otpauth://" in data["qr_code_uri"]
        assert data["method"] == "totp"
        assert data["issuer"] == "LAYA"

    @pytest.mark.asyncio
    async def test_mfa_setup_with_recovery_email(
        self,
        client: AsyncClient,
        auth_headers: dict,
    ):
        """Test setup endpoint accepts recovery email."""
        response = await client.post(
            "/api/v1/mfa/setup",
            headers=auth_headers,
            json={
                "method": "totp",
                "recovery_email": "recovery@example.com",
            },
        )

        assert response.status_code == 200

    @pytest.mark.asyncio
    async def test_mfa_setup_requires_auth(
        self,
        client: AsyncClient,
    ):
        """Test setup endpoint requires authentication."""
        response = await client.post(
            "/api/v1/mfa/setup",
            json={"method": "totp"},
        )

        assert response.status_code == 401

    @pytest.mark.asyncio
    async def test_mfa_setup_fails_if_already_enabled(
        self,
        client: AsyncClient,
        auth_headers: dict,
        mfa_settings_enabled: MockMFASettings,
    ):
        """Test setup endpoint returns 400 if MFA already enabled."""
        response = await client.post(
            "/api/v1/mfa/setup",
            headers=auth_headers,
            json={"method": "totp"},
        )

        assert response.status_code == 400
        assert "already enabled" in response.json()["detail"].lower()

    @pytest.mark.asyncio
    async def test_mfa_setup_validates_recovery_email_format(
        self,
        client: AsyncClient,
        auth_headers: dict,
    ):
        """Test setup endpoint validates recovery email format."""
        response = await client.post(
            "/api/v1/mfa/setup",
            headers=auth_headers,
            json={
                "method": "totp",
                "recovery_email": "invalid-email",
            },
        )

        assert response.status_code == 422


# =============================================================================
# MFA Verify Endpoint Tests
# =============================================================================


class TestMFAVerifyEndpoint:
    """Tests for POST /api/v1/mfa/verify endpoint."""

    @pytest.mark.asyncio
    async def test_mfa_verify_returns_success_with_valid_code(
        self,
        client: AsyncClient,
        auth_headers: dict,
        mfa_settings_enabled: MockMFASettings,
        mfa_test_secret: str,
    ):
        """Test verify endpoint returns success with valid TOTP code."""
        totp = pyotp.TOTP(mfa_test_secret)
        valid_code = totp.now()

        response = await client.post(
            "/api/v1/mfa/verify",
            headers=auth_headers,
            json={"code": valid_code},
        )

        assert response.status_code == 200
        data = response.json()
        assert data["verified"] is True
        assert "successfully" in data["message"].lower()

    @pytest.mark.asyncio
    async def test_mfa_verify_returns_failure_with_invalid_code(
        self,
        client: AsyncClient,
        auth_headers: dict,
        mfa_settings_enabled: MockMFASettings,
    ):
        """Test verify endpoint returns failure with invalid code."""
        response = await client.post(
            "/api/v1/mfa/verify",
            headers=auth_headers,
            json={"code": "000000"},
        )

        assert response.status_code == 200
        data = response.json()
        assert data["verified"] is False
        assert "remaining_attempts" in data

    @pytest.mark.asyncio
    async def test_mfa_verify_requires_auth(
        self,
        client: AsyncClient,
    ):
        """Test verify endpoint requires authentication."""
        response = await client.post(
            "/api/v1/mfa/verify",
            json={"code": "123456"},
        )

        assert response.status_code == 401

    @pytest.mark.asyncio
    async def test_mfa_verify_fails_if_not_enabled(
        self,
        client: AsyncClient,
        auth_headers: dict,
    ):
        """Test verify endpoint returns 400 if MFA not enabled."""
        response = await client.post(
            "/api/v1/mfa/verify",
            headers=auth_headers,
            json={"code": "123456"},
        )

        assert response.status_code == 400

    @pytest.mark.asyncio
    async def test_mfa_verify_validates_code_format(
        self,
        client: AsyncClient,
        auth_headers: dict,
        mfa_settings_enabled: MockMFASettings,
    ):
        """Test verify endpoint validates code format (digits only)."""
        response = await client.post(
            "/api/v1/mfa/verify",
            headers=auth_headers,
            json={"code": "abc123"},
        )

        assert response.status_code == 422

    @pytest.mark.asyncio
    async def test_mfa_verify_returns_423_when_locked(
        self,
        client: AsyncClient,
        auth_headers: dict,
        mfa_settings_locked: MockMFASettings,
    ):
        """Test verify endpoint returns 423 when account is locked."""
        response = await client.post(
            "/api/v1/mfa/verify",
            headers=auth_headers,
            json={"code": "123456"},
        )

        assert response.status_code == 423
        assert "locked" in response.json()["detail"].lower()


# =============================================================================
# MFA Enable Endpoint Tests
# =============================================================================


class TestMFAEnableEndpoint:
    """Tests for POST /api/v1/mfa/enable endpoint."""

    @pytest.mark.asyncio
    async def test_mfa_enable_with_valid_code(
        self,
        client: AsyncClient,
        auth_headers: dict,
        mfa_settings_pending_setup: MockMFASettings,
        mfa_test_secret: str,
    ):
        """Test enable endpoint succeeds with valid TOTP code."""
        totp = pyotp.TOTP(mfa_test_secret)
        valid_code = totp.now()

        response = await client.post(
            "/api/v1/mfa/enable",
            headers=auth_headers,
            json={"code": valid_code},
        )

        assert response.status_code == 200
        data = response.json()
        assert data["verified"] is True
        assert "enabled" in data["message"].lower()

    @pytest.mark.asyncio
    async def test_mfa_enable_requires_auth(
        self,
        client: AsyncClient,
    ):
        """Test enable endpoint requires authentication."""
        response = await client.post(
            "/api/v1/mfa/enable",
            json={"code": "123456"},
        )

        assert response.status_code == 401

    @pytest.mark.asyncio
    async def test_mfa_enable_fails_if_not_setup(
        self,
        client: AsyncClient,
        auth_headers: dict,
    ):
        """Test enable endpoint returns 400 if setup not initiated."""
        response = await client.post(
            "/api/v1/mfa/enable",
            headers=auth_headers,
            json={"code": "123456"},
        )

        assert response.status_code == 400

    @pytest.mark.asyncio
    async def test_mfa_enable_fails_if_already_enabled(
        self,
        client: AsyncClient,
        auth_headers: dict,
        mfa_settings_enabled: MockMFASettings,
        mfa_test_secret: str,
    ):
        """Test enable endpoint returns 400 if already enabled."""
        totp = pyotp.TOTP(mfa_test_secret)
        valid_code = totp.now()

        response = await client.post(
            "/api/v1/mfa/enable",
            headers=auth_headers,
            json={"code": valid_code},
        )

        assert response.status_code == 400


# =============================================================================
# MFA Disable Endpoint Tests
# =============================================================================


class TestMFADisableEndpoint:
    """Tests for POST /api/v1/mfa/disable endpoint."""

    @pytest.mark.asyncio
    async def test_mfa_disable_with_valid_totp_code(
        self,
        client: AsyncClient,
        auth_headers: dict,
        mfa_settings_enabled: MockMFASettings,
        mfa_test_secret: str,
    ):
        """Test disable endpoint succeeds with valid TOTP code."""
        totp = pyotp.TOTP(mfa_test_secret)
        valid_code = totp.now()

        response = await client.post(
            "/api/v1/mfa/disable",
            headers=auth_headers,
            json={"code": valid_code, "is_backup_code": False},
        )

        assert response.status_code == 200
        data = response.json()
        assert data["verified"] is True
        assert "disabled" in data["message"].lower()

    @pytest.mark.asyncio
    async def test_mfa_disable_requires_auth(
        self,
        client: AsyncClient,
    ):
        """Test disable endpoint requires authentication."""
        response = await client.post(
            "/api/v1/mfa/disable",
            json={"code": "123456"},
        )

        assert response.status_code == 401

    @pytest.mark.asyncio
    async def test_mfa_disable_fails_if_not_enabled(
        self,
        client: AsyncClient,
        auth_headers: dict,
    ):
        """Test disable endpoint returns 400 if MFA not enabled."""
        response = await client.post(
            "/api/v1/mfa/disable",
            headers=auth_headers,
            json={"code": "123456"},
        )

        assert response.status_code == 400

    @pytest.mark.asyncio
    async def test_mfa_disable_returns_423_when_locked(
        self,
        client: AsyncClient,
        auth_headers: dict,
        mfa_settings_locked: MockMFASettings,
    ):
        """Test disable endpoint returns 423 when account is locked."""
        response = await client.post(
            "/api/v1/mfa/disable",
            headers=auth_headers,
            json={"code": "123456"},
        )

        assert response.status_code == 423


# =============================================================================
# Backup Code Generation Endpoint Tests
# =============================================================================


class TestBackupCodeGenerateEndpoint:
    """Tests for POST /api/v1/mfa/backup-codes endpoint."""

    @pytest.mark.asyncio
    async def test_backup_codes_generated_successfully(
        self,
        client: AsyncClient,
        auth_headers: dict,
        mfa_settings_enabled: MockMFASettings,
        mfa_test_secret: str,
    ):
        """Test backup codes are generated with valid TOTP."""
        totp = pyotp.TOTP(mfa_test_secret)
        valid_code = totp.now()

        response = await client.post(
            "/api/v1/mfa/backup-codes",
            headers=auth_headers,
            json={"code": valid_code, "count": 10},
        )

        assert response.status_code == 200
        data = response.json()
        assert "codes" in data
        assert len(data["codes"]) == 10
        assert data["count"] == 10
        assert "generated_at" in data
        assert "message" in data

    @pytest.mark.asyncio
    async def test_backup_codes_custom_count(
        self,
        client: AsyncClient,
        auth_headers: dict,
        mfa_settings_enabled: MockMFASettings,
        mfa_test_secret: str,
    ):
        """Test backup codes can have custom count."""
        totp = pyotp.TOTP(mfa_test_secret)
        valid_code = totp.now()

        response = await client.post(
            "/api/v1/mfa/backup-codes",
            headers=auth_headers,
            json={"code": valid_code, "count": 5},
        )

        assert response.status_code == 200
        data = response.json()
        assert len(data["codes"]) == 5

    @pytest.mark.asyncio
    async def test_backup_codes_requires_auth(
        self,
        client: AsyncClient,
    ):
        """Test backup codes endpoint requires authentication."""
        response = await client.post(
            "/api/v1/mfa/backup-codes",
            json={"code": "123456", "count": 10},
        )

        assert response.status_code == 401

    @pytest.mark.asyncio
    async def test_backup_codes_fails_if_mfa_not_enabled(
        self,
        client: AsyncClient,
        auth_headers: dict,
    ):
        """Test backup codes returns 400 if MFA not enabled."""
        response = await client.post(
            "/api/v1/mfa/backup-codes",
            headers=auth_headers,
            json={"code": "123456", "count": 10},
        )

        assert response.status_code == 400

    @pytest.mark.asyncio
    async def test_backup_codes_fails_with_invalid_totp(
        self,
        client: AsyncClient,
        auth_headers: dict,
        mfa_settings_enabled: MockMFASettings,
    ):
        """Test backup codes fails with invalid TOTP code."""
        response = await client.post(
            "/api/v1/mfa/backup-codes",
            headers=auth_headers,
            json={"code": "000000", "count": 10},
        )

        assert response.status_code == 400

    @pytest.mark.asyncio
    async def test_backup_codes_validates_count_range(
        self,
        client: AsyncClient,
        auth_headers: dict,
        mfa_settings_enabled: MockMFASettings,
        mfa_test_secret: str,
    ):
        """Test backup codes validates count is between 5 and 20."""
        totp = pyotp.TOTP(mfa_test_secret)
        valid_code = totp.now()

        # Test count too low
        response = await client.post(
            "/api/v1/mfa/backup-codes",
            headers=auth_headers,
            json={"code": valid_code, "count": 3},
        )
        assert response.status_code == 422

        # Test count too high
        response = await client.post(
            "/api/v1/mfa/backup-codes",
            headers=auth_headers,
            json={"code": valid_code, "count": 25},
        )
        assert response.status_code == 422


# =============================================================================
# Backup Code Status Endpoint Tests
# =============================================================================


class TestBackupCodeStatusEndpoint:
    """Tests for GET /api/v1/mfa/backup-codes/status endpoint."""

    @pytest.mark.asyncio
    async def test_backup_code_status_returns_counts(
        self,
        client: AsyncClient,
        auth_headers: dict,
        mfa_with_backup_codes: tuple[MockMFASettings, list[MockMFABackupCode]],
    ):
        """Test backup code status returns correct counts."""
        response = await client.get(
            "/api/v1/mfa/backup-codes/status",
            headers=auth_headers,
        )

        assert response.status_code == 200
        data = response.json()
        assert data["total_codes"] == 5
        assert data["used_codes"] == 1
        assert data["remaining_codes"] == 4

    @pytest.mark.asyncio
    async def test_backup_code_status_requires_auth(
        self,
        client: AsyncClient,
    ):
        """Test backup code status requires authentication."""
        response = await client.get("/api/v1/mfa/backup-codes/status")

        assert response.status_code == 401

    @pytest.mark.asyncio
    async def test_backup_code_status_fails_if_mfa_not_enabled(
        self,
        client: AsyncClient,
        auth_headers: dict,
    ):
        """Test backup code status returns 400 if MFA not enabled."""
        response = await client.get(
            "/api/v1/mfa/backup-codes/status",
            headers=auth_headers,
        )

        assert response.status_code == 400


# =============================================================================
# Backup Code Verify Endpoint Tests
# =============================================================================


class TestBackupCodeVerifyEndpoint:
    """Tests for POST /api/v1/mfa/backup-codes/verify endpoint."""

    @pytest.mark.asyncio
    async def test_backup_code_verify_requires_auth(
        self,
        client: AsyncClient,
    ):
        """Test backup code verify requires authentication."""
        response = await client.post(
            "/api/v1/mfa/backup-codes/verify",
            json={"code": "TESTCODE1"},
        )

        assert response.status_code == 401

    @pytest.mark.asyncio
    async def test_backup_code_verify_fails_if_mfa_not_enabled(
        self,
        client: AsyncClient,
        auth_headers: dict,
    ):
        """Test backup code verify returns 400 if MFA not enabled."""
        response = await client.post(
            "/api/v1/mfa/backup-codes/verify",
            headers=auth_headers,
            json={"code": "TESTCODE1"},
        )

        assert response.status_code == 400


# =============================================================================
# IP Whitelist Endpoints Tests
# =============================================================================


class TestIPWhitelistListEndpoint:
    """Tests for GET /api/v1/mfa/ip-whitelist endpoint."""

    @pytest.mark.asyncio
    async def test_ip_whitelist_list_returns_entries(
        self,
        client: AsyncClient,
        auth_headers: dict,
        mfa_with_ip_whitelist: tuple[MockMFASettings, list[MockMFAIPWhitelist]],
    ):
        """Test IP whitelist list returns all entries."""
        response = await client.get(
            "/api/v1/mfa/ip-whitelist",
            headers=auth_headers,
        )

        assert response.status_code == 200
        data = response.json()
        assert "items" in data
        assert len(data["items"]) == 3  # All entries including inactive
        assert "total" in data

    @pytest.mark.asyncio
    async def test_ip_whitelist_list_requires_auth(
        self,
        client: AsyncClient,
    ):
        """Test IP whitelist list requires authentication."""
        response = await client.get("/api/v1/mfa/ip-whitelist")

        assert response.status_code == 401

    @pytest.mark.asyncio
    async def test_ip_whitelist_list_fails_if_mfa_not_enabled(
        self,
        client: AsyncClient,
        auth_headers: dict,
    ):
        """Test IP whitelist list returns 400 if MFA not enabled."""
        response = await client.get(
            "/api/v1/mfa/ip-whitelist",
            headers=auth_headers,
        )

        assert response.status_code == 400


class TestIPWhitelistCreateEndpoint:
    """Tests for POST /api/v1/mfa/ip-whitelist endpoint."""

    @pytest.mark.asyncio
    async def test_ip_whitelist_add_ipv4_address(
        self,
        client: AsyncClient,
        auth_headers: dict,
        mfa_settings_enabled: MockMFASettings,
    ):
        """Test adding IPv4 address to whitelist."""
        response = await client.post(
            "/api/v1/mfa/ip-whitelist",
            headers=auth_headers,
            json={
                "ip_address": "192.168.1.100",
                "description": "Home office",
            },
        )

        assert response.status_code == 200
        data = response.json()
        assert data["ip_address"] == "192.168.1.100"
        assert data["description"] == "Home office"
        assert data["is_active"] is True

    @pytest.mark.asyncio
    async def test_ip_whitelist_add_cidr_range(
        self,
        client: AsyncClient,
        auth_headers: dict,
        mfa_settings_enabled: MockMFASettings,
    ):
        """Test adding CIDR range to whitelist."""
        response = await client.post(
            "/api/v1/mfa/ip-whitelist",
            headers=auth_headers,
            json={
                "ip_address": "10.0.0.0/24",
                "description": "Corporate VPN",
            },
        )

        assert response.status_code == 200
        data = response.json()
        assert data["ip_address"] == "10.0.0.0/24"

    @pytest.mark.asyncio
    async def test_ip_whitelist_add_requires_auth(
        self,
        client: AsyncClient,
    ):
        """Test adding to whitelist requires authentication."""
        response = await client.post(
            "/api/v1/mfa/ip-whitelist",
            json={"ip_address": "192.168.1.100"},
        )

        assert response.status_code == 401

    @pytest.mark.asyncio
    async def test_ip_whitelist_add_fails_if_mfa_not_enabled(
        self,
        client: AsyncClient,
        auth_headers: dict,
    ):
        """Test adding to whitelist returns 400 if MFA not enabled."""
        response = await client.post(
            "/api/v1/mfa/ip-whitelist",
            headers=auth_headers,
            json={"ip_address": "192.168.1.100"},
        )

        assert response.status_code == 400

    @pytest.mark.asyncio
    async def test_ip_whitelist_validates_ip_format(
        self,
        client: AsyncClient,
        auth_headers: dict,
        mfa_settings_enabled: MockMFASettings,
    ):
        """Test IP whitelist validates IP address format."""
        response = await client.post(
            "/api/v1/mfa/ip-whitelist",
            headers=auth_headers,
            json={"ip_address": "invalid-ip"},
        )

        assert response.status_code == 422


class TestIPWhitelistDeleteEndpoint:
    """Tests for DELETE /api/v1/mfa/ip-whitelist/{entry_id} endpoint."""

    @pytest.mark.asyncio
    async def test_ip_whitelist_remove_entry(
        self,
        client: AsyncClient,
        auth_headers: dict,
        mfa_with_ip_whitelist: tuple[MockMFASettings, list[MockMFAIPWhitelist]],
    ):
        """Test removing entry from whitelist."""
        _, entries = mfa_with_ip_whitelist
        entry_id = entries[0].id

        response = await client.delete(
            f"/api/v1/mfa/ip-whitelist/{entry_id}",
            headers=auth_headers,
        )

        assert response.status_code == 200
        data = response.json()
        assert "removed" in data["message"].lower()

    @pytest.mark.asyncio
    async def test_ip_whitelist_remove_requires_auth(
        self,
        client: AsyncClient,
    ):
        """Test removing from whitelist requires authentication."""
        entry_id = uuid4()
        response = await client.delete(f"/api/v1/mfa/ip-whitelist/{entry_id}")

        assert response.status_code == 401

    @pytest.mark.asyncio
    async def test_ip_whitelist_remove_not_found(
        self,
        client: AsyncClient,
        auth_headers: dict,
        mfa_settings_enabled: MockMFASettings,
    ):
        """Test removing non-existent entry returns 404."""
        non_existent_id = uuid4()
        response = await client.delete(
            f"/api/v1/mfa/ip-whitelist/{non_existent_id}",
            headers=auth_headers,
        )

        assert response.status_code == 404


class TestIPWhitelistCheckEndpoint:
    """Tests for POST /api/v1/mfa/ip-whitelist/check endpoint."""

    @pytest.mark.asyncio
    async def test_ip_whitelist_check_returns_whitelisted(
        self,
        client: AsyncClient,
        auth_headers: dict,
        mfa_with_ip_whitelist: tuple[MockMFASettings, list[MockMFAIPWhitelist]],
    ):
        """Test IP whitelist check returns true for whitelisted IP."""
        response = await client.post(
            "/api/v1/mfa/ip-whitelist/check",
            headers=auth_headers,
            json={"ip_address": "192.168.1.100"},
        )

        assert response.status_code == 200
        data = response.json()
        assert data["ip_address"] == "192.168.1.100"
        assert data["is_whitelisted"] is True
        assert data["matching_entry_id"] is not None

    @pytest.mark.asyncio
    async def test_ip_whitelist_check_returns_not_whitelisted(
        self,
        client: AsyncClient,
        auth_headers: dict,
        mfa_with_ip_whitelist: tuple[MockMFASettings, list[MockMFAIPWhitelist]],
    ):
        """Test IP whitelist check returns false for non-whitelisted IP."""
        response = await client.post(
            "/api/v1/mfa/ip-whitelist/check",
            headers=auth_headers,
            json={"ip_address": "8.8.8.8"},
        )

        assert response.status_code == 200
        data = response.json()
        assert data["is_whitelisted"] is False
        assert data["matching_entry_id"] is None

    @pytest.mark.asyncio
    async def test_ip_whitelist_check_requires_auth(
        self,
        client: AsyncClient,
    ):
        """Test IP whitelist check requires authentication."""
        response = await client.post(
            "/api/v1/mfa/ip-whitelist/check",
            json={"ip_address": "192.168.1.100"},
        )

        assert response.status_code == 401


# =============================================================================
# MFA Session Validation Endpoint Tests
# =============================================================================


class TestMFAValidateEndpoint:
    """Tests for POST /api/v1/mfa/validate endpoint."""

    @pytest.mark.asyncio
    async def test_mfa_validate_with_valid_code(
        self,
        client: AsyncClient,
        auth_headers: dict,
        mfa_settings_enabled: MockMFASettings,
        mfa_test_secret: str,
    ):
        """Test validate endpoint succeeds with valid code."""
        totp = pyotp.TOTP(mfa_test_secret)
        valid_code = totp.now()

        response = await client.post(
            "/api/v1/mfa/validate",
            headers=auth_headers,
            json={"code": valid_code},
        )

        assert response.status_code == 200
        data = response.json()
        assert data["verified"] is True

    @pytest.mark.asyncio
    async def test_mfa_validate_requires_auth(
        self,
        client: AsyncClient,
    ):
        """Test validate endpoint requires authentication."""
        response = await client.post(
            "/api/v1/mfa/validate",
            json={"code": "123456"},
        )

        assert response.status_code == 401

    @pytest.mark.asyncio
    async def test_mfa_validate_fails_if_not_enabled(
        self,
        client: AsyncClient,
        auth_headers: dict,
    ):
        """Test validate endpoint returns 400 if MFA not enabled."""
        response = await client.post(
            "/api/v1/mfa/validate",
            headers=auth_headers,
            json={"code": "123456"},
        )

        assert response.status_code == 400


# =============================================================================
# Admin Lockout Reset Endpoint Tests
# =============================================================================


class TestAdminResetLockoutEndpoint:
    """Tests for POST /api/v1/mfa/admin/reset-lockout/{target_user_id} endpoint."""

    @pytest.mark.asyncio
    async def test_admin_reset_lockout_succeeds(
        self,
        client: AsyncClient,
        admin_auth_headers: dict,
        mfa_settings_locked: MockMFASettings,
        test_user_id,
    ):
        """Test admin can reset lockout for a user."""
        response = await client.post(
            f"/api/v1/mfa/admin/reset-lockout/{test_user_id}",
            headers=admin_auth_headers,
        )

        assert response.status_code == 200
        data = response.json()
        assert "reset" in data["message"].lower()

    @pytest.mark.asyncio
    async def test_admin_reset_lockout_requires_auth(
        self,
        client: AsyncClient,
    ):
        """Test admin reset lockout requires authentication."""
        target_id = uuid4()
        response = await client.post(f"/api/v1/mfa/admin/reset-lockout/{target_id}")

        assert response.status_code == 401

    @pytest.mark.asyncio
    async def test_admin_reset_lockout_requires_admin_role(
        self,
        client: AsyncClient,
        auth_headers: dict,  # Regular user, not admin
    ):
        """Test admin reset lockout returns 403 for non-admin users."""
        target_id = uuid4()
        response = await client.post(
            f"/api/v1/mfa/admin/reset-lockout/{target_id}",
            headers=auth_headers,
        )

        assert response.status_code == 403
        assert "admin" in response.json()["detail"].lower()

    @pytest.mark.asyncio
    async def test_admin_reset_lockout_not_found(
        self,
        client: AsyncClient,
        admin_auth_headers: dict,
    ):
        """Test admin reset lockout returns 404 for non-existent user."""
        non_existent_id = uuid4()
        response = await client.post(
            f"/api/v1/mfa/admin/reset-lockout/{non_existent_id}",
            headers=admin_auth_headers,
        )

        assert response.status_code == 404


# =============================================================================
# Edge Case Tests
# =============================================================================


class TestMFAEdgeCases:
    """Tests for edge cases and error handling."""

    @pytest.mark.asyncio
    async def test_invalid_uuid_format_in_path(
        self,
        client: AsyncClient,
        auth_headers: dict,
    ):
        """Test API handles invalid UUID format gracefully."""
        response = await client.delete(
            "/api/v1/mfa/ip-whitelist/invalid-uuid",
            headers=auth_headers,
        )

        assert response.status_code == 422

    @pytest.mark.asyncio
    async def test_code_length_validation(
        self,
        client: AsyncClient,
        auth_headers: dict,
        mfa_settings_enabled: MockMFASettings,
    ):
        """Test code length validation is enforced."""
        # Too short
        response = await client.post(
            "/api/v1/mfa/verify",
            headers=auth_headers,
            json={"code": "12345"},  # 5 digits
        )
        assert response.status_code == 422

    @pytest.mark.asyncio
    async def test_expired_token_rejected(
        self,
        client: AsyncClient,
        expired_token: str,
    ):
        """Test expired tokens are rejected."""
        response = await client.get(
            "/api/v1/mfa/status",
            headers={"Authorization": f"Bearer {expired_token}"},
        )

        assert response.status_code == 401

    @pytest.mark.asyncio
    async def test_invalid_token_rejected(
        self,
        client: AsyncClient,
    ):
        """Test invalid tokens are rejected."""
        response = await client.get(
            "/api/v1/mfa/status",
            headers={"Authorization": "Bearer invalid_token_string"},
        )

        assert response.status_code == 401

    @pytest.mark.asyncio
    async def test_ip_whitelist_check_handles_cidr_matching(
        self,
        client: AsyncClient,
        auth_headers: dict,
        mfa_with_ip_whitelist: tuple[MockMFASettings, list[MockMFAIPWhitelist]],
    ):
        """Test IP whitelist check handles CIDR range matching."""
        # The fixture includes 10.0.0.0/24
        response = await client.post(
            "/api/v1/mfa/ip-whitelist/check",
            headers=auth_headers,
            json={"ip_address": "10.0.0.50"},
        )

        assert response.status_code == 200
        data = response.json()
        assert data["is_whitelisted"] is True
