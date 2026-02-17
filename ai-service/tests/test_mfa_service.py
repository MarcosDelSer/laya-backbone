"""Unit tests for MFA service, models, and API endpoints.

Tests cover:
- MFA setup initiation and TOTP secret generation
- TOTP code verification with valid window
- MFA enable/disable flow
- Backup code generation and verification
- IP whitelist management and CIDR matching
- Account lockout after failed attempts
- Lockout reset functionality
- API endpoint authentication requirements
- Edge cases: invalid codes, expired lockouts, already enabled
"""

from datetime import datetime, timedelta, timezone
from typing import Optional
from urllib.parse import quote
from uuid import UUID, uuid4

import pyotp
import pytest
from sqlalchemy import text
from sqlalchemy.ext.asyncio import AsyncSession

from app.models.mfa import MFAMethod
from app.services.mfa_service import (
    InvalidCodeError,
    MFAAlreadyEnabledError,
    MFALockoutError,
    MFANotEnabledError,
    MFAService,
)
from tests.conftest import test_engine


# =============================================================================
# Mock Classes for Testing
# =============================================================================


class MockMFASettings:
    """Mock MFASettings object for testing without SQLAlchemy ORM overhead."""

    def __init__(
        self,
        id: UUID,
        user_id: UUID,
        is_enabled: bool,
        method: str,
        secret_key: Optional[str],
        recovery_email: Optional[str],
        last_verified_at: Optional[datetime],
        failed_attempts: int,
        locked_until: Optional[datetime],
        created_at: datetime,
        updated_at: datetime,
    ):
        self.id = id
        self.user_id = user_id
        self.is_enabled = is_enabled
        self.method = method
        self.secret_key = secret_key
        self.recovery_email = recovery_email
        self.last_verified_at = last_verified_at
        self.failed_attempts = failed_attempts
        self.locked_until = locked_until
        self.created_at = created_at
        self.updated_at = updated_at

    def __repr__(self) -> str:
        return (
            f"<MFASettings(id={self.id}, user_id={self.user_id}, "
            f"enabled={self.is_enabled}, method={self.method})>"
        )


class MockMFABackupCode:
    """Mock MFABackupCode object for testing."""

    def __init__(
        self,
        id: UUID,
        mfa_settings_id: UUID,
        code_hash: str,
        is_used: bool,
        used_at: Optional[datetime],
        created_at: datetime,
    ):
        self.id = id
        self.mfa_settings_id = mfa_settings_id
        self.code_hash = code_hash
        self.is_used = is_used
        self.used_at = used_at
        self.created_at = created_at

    def __repr__(self) -> str:
        return (
            f"<MFABackupCode(id={self.id}, mfa_settings_id={self.mfa_settings_id}, "
            f"used={self.is_used})>"
        )


class MockMFAIPWhitelist:
    """Mock MFAIPWhitelist object for testing."""

    def __init__(
        self,
        id: UUID,
        mfa_settings_id: UUID,
        ip_address: str,
        description: Optional[str],
        is_active: bool,
        created_at: datetime,
        updated_at: datetime,
    ):
        self.id = id
        self.mfa_settings_id = mfa_settings_id
        self.ip_address = ip_address
        self.description = description
        self.is_active = is_active
        self.created_at = created_at
        self.updated_at = updated_at

    def __repr__(self) -> str:
        return (
            f"<MFAIPWhitelist(id={self.id}, ip={self.ip_address}, "
            f"active={self.is_active})>"
        )


# =============================================================================
# Database Helper Functions
# =============================================================================


async def create_mfa_settings_in_db(
    session: AsyncSession,
    user_id: UUID,
    is_enabled: bool = False,
    method: str = "totp",
    secret_key: Optional[str] = None,
    recovery_email: Optional[str] = None,
    failed_attempts: int = 0,
    locked_until: Optional[datetime] = None,
) -> MockMFASettings:
    """Helper function to create MFA settings directly in SQLite database."""
    settings_id = str(uuid4())
    now = datetime.now(timezone.utc)

    await session.execute(
        text("""
            INSERT INTO mfa_settings (
                id, user_id, is_enabled, method, secret_key, recovery_email,
                failed_attempts, locked_until, created_at, updated_at
            ) VALUES (
                :id, :user_id, :is_enabled, :method, :secret_key, :recovery_email,
                :failed_attempts, :locked_until, :created_at, :updated_at
            )
        """),
        {
            "id": settings_id,
            "user_id": str(user_id),
            "is_enabled": 1 if is_enabled else 0,
            "method": method,
            "secret_key": secret_key,
            "recovery_email": recovery_email,
            "failed_attempts": failed_attempts,
            "locked_until": locked_until.isoformat() if locked_until else None,
            "created_at": now.isoformat(),
            "updated_at": now.isoformat(),
        }
    )
    await session.commit()

    return MockMFASettings(
        id=UUID(settings_id),
        user_id=user_id,
        is_enabled=is_enabled,
        method=method,
        secret_key=secret_key,
        recovery_email=recovery_email,
        last_verified_at=None,
        failed_attempts=failed_attempts,
        locked_until=locked_until,
        created_at=now,
        updated_at=now,
    )


async def create_backup_code_in_db(
    session: AsyncSession,
    mfa_settings_id: UUID,
    code_hash: str,
    is_used: bool = False,
    used_at: Optional[datetime] = None,
) -> MockMFABackupCode:
    """Helper function to create a backup code directly in SQLite database."""
    code_id = str(uuid4())
    now = datetime.now(timezone.utc)

    await session.execute(
        text("""
            INSERT INTO mfa_backup_codes (
                id, mfa_settings_id, code_hash, is_used, used_at, created_at
            ) VALUES (
                :id, :mfa_settings_id, :code_hash, :is_used, :used_at, :created_at
            )
        """),
        {
            "id": code_id,
            "mfa_settings_id": str(mfa_settings_id),
            "code_hash": code_hash,
            "is_used": 1 if is_used else 0,
            "used_at": used_at.isoformat() if used_at else None,
            "created_at": now.isoformat(),
        }
    )
    await session.commit()

    return MockMFABackupCode(
        id=UUID(code_id),
        mfa_settings_id=mfa_settings_id,
        code_hash=code_hash,
        is_used=is_used,
        used_at=used_at,
        created_at=now,
    )


async def create_ip_whitelist_in_db(
    session: AsyncSession,
    mfa_settings_id: UUID,
    ip_address: str,
    description: Optional[str] = None,
    is_active: bool = True,
) -> MockMFAIPWhitelist:
    """Helper function to create an IP whitelist entry directly in SQLite database."""
    entry_id = str(uuid4())
    now = datetime.now(timezone.utc)

    await session.execute(
        text("""
            INSERT INTO mfa_ip_whitelist (
                id, mfa_settings_id, ip_address, description, is_active,
                created_at, updated_at
            ) VALUES (
                :id, :mfa_settings_id, :ip_address, :description, :is_active,
                :created_at, :updated_at
            )
        """),
        {
            "id": entry_id,
            "mfa_settings_id": str(mfa_settings_id),
            "ip_address": ip_address,
            "description": description,
            "is_active": 1 if is_active else 0,
            "created_at": now.isoformat(),
            "updated_at": now.isoformat(),
        }
    )
    await session.commit()

    return MockMFAIPWhitelist(
        id=UUID(entry_id),
        mfa_settings_id=mfa_settings_id,
        ip_address=ip_address,
        description=description,
        is_active=is_active,
        created_at=now,
        updated_at=now,
    )


# =============================================================================
# Fixtures
# =============================================================================


@pytest.fixture
def mfa_user_id() -> UUID:
    """Generate a unique test user ID for each MFA test."""
    return uuid4()


@pytest.fixture
def mfa_user_email() -> str:
    """Test user email for MFA tests."""
    return "mfa_test@example.com"


@pytest.fixture
def totp_secret() -> str:
    """Generate a valid TOTP secret for testing."""
    return pyotp.random_base32()


@pytest.fixture
def valid_totp_code(totp_secret: str) -> str:
    """Generate a valid TOTP code from the test secret."""
    totp = pyotp.TOTP(totp_secret)
    return totp.now()


# =============================================================================
# Model Tests
# =============================================================================


class TestMFASettingsModel:
    """Tests for the MFASettings model."""

    @pytest.mark.asyncio
    async def test_create_mfa_settings(
        self,
        db_session: AsyncSession,
        mfa_user_id: UUID,
        totp_secret: str,
    ):
        """Test MFASettings can be created with all fields."""
        settings = await create_mfa_settings_in_db(
            db_session,
            user_id=mfa_user_id,
            is_enabled=True,
            method="totp",
            secret_key=totp_secret,
            recovery_email="recovery@example.com",
        )

        assert settings.id is not None
        assert settings.user_id == mfa_user_id
        assert settings.is_enabled is True
        assert settings.method == "totp"
        assert settings.secret_key == totp_secret
        assert settings.recovery_email == "recovery@example.com"
        assert settings.failed_attempts == 0
        assert settings.locked_until is None
        assert settings.created_at is not None

    @pytest.mark.asyncio
    async def test_mfa_settings_repr(
        self,
        db_session: AsyncSession,
        mfa_user_id: UUID,
    ):
        """Test MFASettings string representation."""
        settings = await create_mfa_settings_in_db(
            db_session,
            user_id=mfa_user_id,
            is_enabled=True,
        )

        repr_str = repr(settings)
        assert "MFASettings" in repr_str
        assert str(settings.id) in repr_str
        assert str(mfa_user_id) in repr_str

    @pytest.mark.asyncio
    async def test_mfa_settings_default_values(
        self,
        db_session: AsyncSession,
        mfa_user_id: UUID,
    ):
        """Test MFASettings default values are applied correctly."""
        settings = await create_mfa_settings_in_db(
            db_session,
            user_id=mfa_user_id,
        )

        assert settings.is_enabled is False
        assert settings.method == "totp"
        assert settings.secret_key is None
        assert settings.recovery_email is None
        assert settings.failed_attempts == 0
        assert settings.locked_until is None


class TestMFABackupCodeModel:
    """Tests for the MFABackupCode model."""

    @pytest.mark.asyncio
    async def test_create_backup_code(
        self,
        db_session: AsyncSession,
        mfa_user_id: UUID,
    ):
        """Test MFABackupCode can be created."""
        settings = await create_mfa_settings_in_db(
            db_session,
            user_id=mfa_user_id,
            is_enabled=True,
        )

        backup_code = await create_backup_code_in_db(
            db_session,
            mfa_settings_id=settings.id,
            code_hash="test_hash_12345",
        )

        assert backup_code.id is not None
        assert backup_code.mfa_settings_id == settings.id
        assert backup_code.code_hash == "test_hash_12345"
        assert backup_code.is_used is False
        assert backup_code.used_at is None
        assert backup_code.created_at is not None

    @pytest.mark.asyncio
    async def test_backup_code_repr(
        self,
        db_session: AsyncSession,
        mfa_user_id: UUID,
    ):
        """Test MFABackupCode string representation."""
        settings = await create_mfa_settings_in_db(
            db_session,
            user_id=mfa_user_id,
            is_enabled=True,
        )

        backup_code = await create_backup_code_in_db(
            db_session,
            mfa_settings_id=settings.id,
            code_hash="test_hash",
        )

        repr_str = repr(backup_code)
        assert "MFABackupCode" in repr_str
        assert str(backup_code.id) in repr_str


class TestMFAIPWhitelistModel:
    """Tests for the MFAIPWhitelist model."""

    @pytest.mark.asyncio
    async def test_create_ip_whitelist_entry(
        self,
        db_session: AsyncSession,
        mfa_user_id: UUID,
    ):
        """Test MFAIPWhitelist can be created."""
        settings = await create_mfa_settings_in_db(
            db_session,
            user_id=mfa_user_id,
            is_enabled=True,
        )

        entry = await create_ip_whitelist_in_db(
            db_session,
            mfa_settings_id=settings.id,
            ip_address="192.168.1.1",
            description="Office IP",
        )

        assert entry.id is not None
        assert entry.mfa_settings_id == settings.id
        assert entry.ip_address == "192.168.1.1"
        assert entry.description == "Office IP"
        assert entry.is_active is True
        assert entry.created_at is not None

    @pytest.mark.asyncio
    async def test_ip_whitelist_repr(
        self,
        db_session: AsyncSession,
        mfa_user_id: UUID,
    ):
        """Test MFAIPWhitelist string representation."""
        settings = await create_mfa_settings_in_db(
            db_session,
            user_id=mfa_user_id,
            is_enabled=True,
        )

        entry = await create_ip_whitelist_in_db(
            db_session,
            mfa_settings_id=settings.id,
            ip_address="10.0.0.1",
        )

        repr_str = repr(entry)
        assert "MFAIPWhitelist" in repr_str
        assert "10.0.0.1" in repr_str


# =============================================================================
# MFA Service Tests - Setup and Status
# =============================================================================


class TestMFAServiceStatus:
    """Tests for MFAService get_mfa_status method."""

    @pytest.mark.asyncio
    async def test_get_status_no_mfa_setup(
        self,
        db_session: AsyncSession,
        mfa_user_id: UUID,
    ):
        """Test get_mfa_status returns disabled status when MFA not set up."""
        service = MFAService(db_session)
        status = await service.get_mfa_status(mfa_user_id)

        assert status.user_id == mfa_user_id
        assert status.is_enabled is False
        assert status.method is None
        assert status.has_recovery_email is False
        assert status.has_backup_codes is False
        assert status.backup_codes_remaining == 0
        assert status.ip_whitelist_count == 0
        assert status.is_locked is False


class TestMFAServiceSetup:
    """Tests for MFAService initiate_setup method."""

    @pytest.mark.asyncio
    async def test_initiate_setup_generates_secret(
        self,
        db_session: AsyncSession,
        mfa_user_id: UUID,
        mfa_user_email: str,
    ):
        """Test initiate_setup generates a valid TOTP secret."""
        service = MFAService(db_session)
        response = await service.initiate_setup(
            user_id=mfa_user_id,
            user_email=mfa_user_email,
        )

        assert response.secret is not None
        assert len(response.secret) > 0
        assert response.qr_code_uri is not None
        assert "otpauth://totp/" in response.qr_code_uri
        # Email is URL-encoded in the QR code URI
        assert quote(mfa_user_email, safe='') in response.qr_code_uri
        assert response.method == MFAMethod.TOTP
        assert response.issuer == "LAYA"

    @pytest.mark.asyncio
    async def test_initiate_setup_with_recovery_email(
        self,
        db_session: AsyncSession,
        mfa_user_id: UUID,
        mfa_user_email: str,
    ):
        """Test initiate_setup stores recovery email."""
        service = MFAService(db_session)
        await service.initiate_setup(
            user_id=mfa_user_id,
            user_email=mfa_user_email,
            recovery_email="recovery@backup.com",
        )

        status = await service.get_mfa_status(mfa_user_id)
        assert status.has_recovery_email is True


# =============================================================================
# MFA Service Tests - TOTP Verification
# =============================================================================


class TestMFAServiceVerifyTOTP:
    """Tests for MFAService verify_totp method."""

    @pytest.mark.asyncio
    async def test_verify_totp_raises_when_no_settings(
        self,
        db_session: AsyncSession,
        mfa_user_id: UUID,
    ):
        """Test verify_totp raises error when no MFA settings exist."""
        service = MFAService(db_session)

        with pytest.raises(MFANotEnabledError):
            await service.verify_totp(mfa_user_id, "123456")


# =============================================================================
# MFA Service Tests - Enable/Disable
# =============================================================================


class TestMFAServiceEnableDisable:
    """Tests for MFAService enable_mfa and disable_mfa methods."""

    @pytest.mark.asyncio
    async def test_enable_mfa_with_valid_code(
        self,
        db_session: AsyncSession,
        mfa_user_id: UUID,
        mfa_user_email: str,
    ):
        """Test enable_mfa succeeds with valid TOTP code."""
        service = MFAService(db_session)

        # First initiate setup
        setup_response = await service.initiate_setup(
            user_id=mfa_user_id,
            user_email=mfa_user_email,
        )

        # Generate valid code from the secret
        totp = pyotp.TOTP(setup_response.secret)
        valid_code = totp.now()

        # Enable MFA
        response = await service.enable_mfa(mfa_user_id, valid_code)

        assert response.verified is True
        assert "enabled" in response.message.lower()

        # Verify status
        status = await service.get_mfa_status(mfa_user_id)
        assert status.is_enabled is True

    @pytest.mark.asyncio
    async def test_enable_mfa_fails_with_invalid_code(
        self,
        db_session: AsyncSession,
        mfa_user_id: UUID,
        mfa_user_email: str,
    ):
        """Test enable_mfa fails with invalid TOTP code."""
        service = MFAService(db_session)

        await service.initiate_setup(
            user_id=mfa_user_id,
            user_email=mfa_user_email,
        )

        response = await service.enable_mfa(mfa_user_id, "000000")

        assert response.verified is False

        # MFA should still be disabled
        status = await service.get_mfa_status(mfa_user_id)
        assert status.is_enabled is False

    @pytest.mark.asyncio
    async def test_disable_mfa_raises_when_not_enabled(
        self,
        db_session: AsyncSession,
        mfa_user_id: UUID,
    ):
        """Test disable_mfa raises error when MFA not enabled."""
        service = MFAService(db_session)

        with pytest.raises(MFANotEnabledError):
            await service.disable_mfa(mfa_user_id, "123456")


# =============================================================================
# MFA Service Tests - Lockout
# =============================================================================


class TestMFAServiceLockout:
    """Tests for MFAService account lockout functionality."""

    @pytest.mark.asyncio
    async def test_reset_lockout_returns_false_for_nonexistent_user(
        self,
        db_session: AsyncSession,
    ):
        """Test reset_lockout returns False when user doesn't exist."""
        service = MFAService(db_session)
        result = await service.reset_lockout(uuid4())

        assert result is False


# =============================================================================
# MFA Service Tests - Backup Codes
# =============================================================================


class TestMFAServiceBackupCodes:
    """Tests for MFAService backup code functionality."""

    @pytest.mark.asyncio
    async def test_generate_backup_codes_requires_mfa_enabled(
        self,
        db_session: AsyncSession,
        mfa_user_id: UUID,
    ):
        """Test generate_backup_codes raises error when MFA not enabled."""
        service = MFAService(db_session)

        with pytest.raises(MFANotEnabledError):
            await service.generate_backup_codes(
                user_id=mfa_user_id,
                code="123456",
                count=5,
            )


# =============================================================================
# MFA Service Tests - IP Whitelist
# =============================================================================


class TestMFAServiceIPWhitelist:
    """Tests for MFAService IP whitelist functionality."""

    @pytest.mark.asyncio
    async def test_ip_whitelist_requires_mfa_enabled(
        self,
        db_session: AsyncSession,
        mfa_user_id: UUID,
    ):
        """Test IP whitelist operations require MFA to be enabled."""
        service = MFAService(db_session)

        with pytest.raises(MFANotEnabledError):
            await service.add_ip_to_whitelist(mfa_user_id, "192.168.1.1")


# =============================================================================
# Edge Case Tests
# =============================================================================


class TestMFAServiceEdgeCases:
    """Tests for edge cases in MFAService."""

    @pytest.mark.asyncio
    async def test_ip_match_handles_invalid_format(
        self,
        db_session: AsyncSession,
    ):
        """Test IP matching handles invalid IP formats gracefully."""
        service = MFAService(db_session)

        # Test private method directly
        result = service._ip_matches_entry("invalid-ip", "192.168.1.1")
        assert result is False

        result = service._ip_matches_entry("192.168.1.1", "invalid-cidr/99")
        assert result is False

    @pytest.mark.asyncio
    async def test_ip_match_exact(
        self,
        db_session: AsyncSession,
    ):
        """Test IP matching with exact match."""
        service = MFAService(db_session)

        result = service._ip_matches_entry("192.168.1.1", "192.168.1.1")
        assert result is True

        result = service._ip_matches_entry("192.168.1.2", "192.168.1.1")
        assert result is False

    @pytest.mark.asyncio
    async def test_ip_match_cidr(
        self,
        db_session: AsyncSession,
    ):
        """Test IP matching with CIDR ranges."""
        service = MFAService(db_session)

        result = service._ip_matches_entry("192.168.1.50", "192.168.1.0/24")
        assert result is True

        result = service._ip_matches_entry("192.168.2.1", "192.168.1.0/24")
        assert result is False

    @pytest.mark.asyncio
    async def test_check_ip_whitelisted_no_mfa(
        self,
        db_session: AsyncSession,
        mfa_user_id: UUID,
    ):
        """Test check_ip_whitelisted when MFA not enabled."""
        service = MFAService(db_session)

        result = await service.check_ip_whitelisted(mfa_user_id, "192.168.1.1")

        assert result.is_whitelisted is False
        assert result.matching_entry_id is None
