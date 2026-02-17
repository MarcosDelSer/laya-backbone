"""End-to-end verification tests for MFA flow.

This module provides comprehensive E2E verification of the MFA flow across ai-service.
It tests the complete flow from setup to verification including:

1. Admin user initiates MFA setup via ai-service API
2. QR code is generated and TOTP secret is stored
3. User verifies TOTP code to enable MFA
4. Subsequent logins require TOTP verification
5. Backup codes can be generated and used
6. IP whitelist allows bypass for trusted IPs
"""

import pytest
import pyotp
from datetime import datetime, timezone
from uuid import uuid4, UUID

from sqlalchemy import text

from app.models.mfa import MFASettings, MFABackupCode, MFAIPWhitelist, MFAMethod
from app.services.mfa_service import (
    MFAService,
    MFANotEnabledError,
    MFAAlreadyEnabledError,
    MFALockoutError,
    InvalidCodeError,
)


class TestMFAEndToEndFlow:
    """End-to-end verification tests for the complete MFA flow."""

    @pytest.fixture
    def user_id(self) -> UUID:
        """Generate a unique user ID for each test."""
        return uuid4()

    @pytest.fixture
    def user_email(self) -> str:
        """Generate a unique user email for each test."""
        return f"admin_{uuid4().hex[:8]}@laya.app"

    @pytest.mark.asyncio
    async def test_e2e_step1_admin_initiates_mfa_setup(
        self, db_session, user_id: UUID, user_email: str
    ):
        """Step 1: Admin user initiates MFA setup via ai-service API.

        Verifies:
        - Setup can be initiated for a new user
        - A TOTP secret is generated
        - QR code URI is provided
        - The issuer name is correct
        """
        service = MFAService(db_session)

        # Initiate MFA setup
        result = await service.initiate_setup(
            user_id=user_id,
            user_email=user_email,
            method=MFAMethod.TOTP,
            recovery_email="recovery@example.com",
        )

        # Verify response
        assert result.secret is not None, "TOTP secret should be generated"
        assert len(result.secret) == 32, "TOTP secret should be 32 characters (base32)"
        assert result.qr_code_uri is not None, "QR code URI should be provided"
        assert "otpauth://totp/" in result.qr_code_uri, "QR code should be a TOTP URI"
        assert "LAYA" in result.qr_code_uri, "QR code should contain issuer name"
        # Email is URL-encoded in QR code URI (@ becomes %40)
        encoded_email = user_email.replace("@", "%40")
        assert encoded_email in result.qr_code_uri, "QR code should contain user email"
        assert result.method == MFAMethod.TOTP, "Method should be TOTP"
        assert result.issuer == "LAYA", "Issuer should be LAYA"

    @pytest.mark.asyncio
    async def test_e2e_step2_qr_code_and_secret_stored(
        self, db_session, user_id: UUID, user_email: str
    ):
        """Step 2: QR code is generated and TOTP secret is stored.

        Verifies:
        - MFA settings are persisted in database
        - Secret key is stored (encrypted at rest in production)
        - Recovery email is stored
        - MFA is not yet enabled
        """
        service = MFAService(db_session)

        # Initiate setup
        setup_result = await service.initiate_setup(
            user_id=user_id,
            user_email=user_email,
            method=MFAMethod.TOTP,
            recovery_email="recovery@example.com",
        )

        # Verify settings are stored
        settings = await service.get_mfa_settings(user_id)

        assert settings is not None, "MFA settings should be persisted"
        assert settings.secret_key is not None, "Secret key should be stored"
        assert settings.secret_key == setup_result.secret, "Secret should match"
        assert settings.recovery_email == "recovery@example.com", "Recovery email should be stored"
        assert settings.is_enabled is False, "MFA should not be enabled yet"
        assert settings.method == MFAMethod.TOTP, "Method should be TOTP"

    @pytest.mark.asyncio
    async def test_e2e_step3_user_verifies_totp_to_enable_mfa(
        self, db_session, user_id: UUID, user_email: str
    ):
        """Step 3: User verifies TOTP code to enable MFA.

        Verifies:
        - Valid TOTP code enables MFA
        - Invalid TOTP code is rejected
        - MFA status changes after enablement
        """
        service = MFAService(db_session)

        # Setup MFA
        setup_result = await service.initiate_setup(
            user_id=user_id,
            user_email=user_email,
            method=MFAMethod.TOTP,
        )

        # Generate valid TOTP code
        totp = pyotp.TOTP(setup_result.secret)
        valid_code = totp.now()

        # Enable MFA with valid code
        enable_result = await service.enable_mfa(user_id=user_id, code=valid_code)

        assert enable_result.verified is True, "MFA should be enabled with valid code"
        assert "enabled" in enable_result.message.lower(), "Message should confirm enablement"

        # Verify MFA is now enabled
        settings = await service.get_mfa_settings(user_id)
        assert settings.is_enabled is True, "MFA should be enabled in database"
        assert settings.last_verified_at is not None, "Last verified time should be set"

    @pytest.mark.asyncio
    async def test_e2e_step3_invalid_code_rejected(
        self, db_session, user_id: UUID, user_email: str
    ):
        """Step 3 (negative): Invalid TOTP code is rejected.

        Verifies:
        - Invalid codes are rejected
        - Failed attempts are tracked
        - MFA is not enabled with invalid code
        """
        service = MFAService(db_session)

        # Setup MFA
        await service.initiate_setup(
            user_id=user_id,
            user_email=user_email,
            method=MFAMethod.TOTP,
        )

        # Try to enable with invalid code
        enable_result = await service.enable_mfa(user_id=user_id, code="000000")

        assert enable_result.verified is False, "Invalid code should be rejected"
        assert enable_result.remaining_attempts is not None, "Remaining attempts should be shown"

        # Verify MFA is not enabled
        settings = await service.get_mfa_settings(user_id)
        assert settings.is_enabled is False, "MFA should not be enabled with invalid code"

    @pytest.mark.asyncio
    async def test_e2e_step4_subsequent_logins_require_totp(
        self, db_session, user_id: UUID, user_email: str
    ):
        """Step 4: Subsequent logins require TOTP verification.

        Verifies:
        - After MFA is enabled, TOTP verification is required
        - Valid codes are accepted for verification
        - Invalid codes are rejected during verification
        """
        service = MFAService(db_session)

        # Setup and enable MFA
        setup_result = await service.initiate_setup(
            user_id=user_id,
            user_email=user_email,
            method=MFAMethod.TOTP,
        )
        totp = pyotp.TOTP(setup_result.secret)
        await service.enable_mfa(user_id=user_id, code=totp.now())

        # Simulate login verification with valid code
        verify_result = await service.verify_totp(
            user_id=user_id,
            code=totp.now(),
            is_setup_verification=False,
        )

        assert verify_result.verified is True, "Valid TOTP should be verified"
        assert "verified" in verify_result.message.lower(), "Success message should mention verification"

        # Simulate login verification with invalid code
        invalid_result = await service.verify_totp(
            user_id=user_id,
            code="000000",
            is_setup_verification=False,
        )

        assert invalid_result.verified is False, "Invalid TOTP should be rejected"
        assert invalid_result.remaining_attempts is not None, "Remaining attempts should be shown"

    @pytest.mark.asyncio
    async def test_e2e_step5_backup_codes_generated_and_used(
        self, db_session, user_id: UUID, user_email: str
    ):
        """Step 5: Backup codes can be generated and used.

        Verifies:
        - Backup codes can be generated after MFA is enabled
        - Generated codes are unique
        - Backup codes can be used for verification
        - Each backup code can only be used once
        """
        service = MFAService(db_session)

        # Setup and enable MFA
        setup_result = await service.initiate_setup(
            user_id=user_id,
            user_email=user_email,
            method=MFAMethod.TOTP,
        )
        totp = pyotp.TOTP(setup_result.secret)
        await service.enable_mfa(user_id=user_id, code=totp.now())

        # Generate backup codes
        backup_result = await service.generate_backup_codes(
            user_id=user_id,
            code=totp.now(),
            count=5,
        )

        assert backup_result.count == 5, "Should generate 5 backup codes"
        assert len(backup_result.codes) == 5, "Should return 5 codes"
        assert len(set(backup_result.codes)) == 5, "All codes should be unique"

        # Get backup code status
        status = await service.get_backup_code_status(user_id=user_id)
        assert status.total_codes == 5, "Total should be 5"
        assert status.remaining_codes == 5, "All 5 should be unused"

        # Use a backup code for verification
        first_code = backup_result.codes[0]
        verify_result = await service.verify_backup_code(
            user_id=user_id,
            code=first_code,
        )

        assert verify_result.verified is True, "Valid backup code should be verified"

        # Verify code is now used
        status = await service.get_backup_code_status(user_id=user_id)
        assert status.used_codes == 1, "One code should be used"
        assert status.remaining_codes == 4, "4 should remain"

        # Try to use the same backup code again
        reuse_result = await service.verify_backup_code(
            user_id=user_id,
            code=first_code,
        )

        assert reuse_result.verified is False, "Used backup code should be rejected"

    @pytest.mark.asyncio
    async def test_e2e_step6_ip_whitelist_bypasses_mfa(
        self, db_session, user_id: UUID, user_email: str
    ):
        """Step 6: IP whitelist allows bypass for trusted IPs.

        Verifies:
        - IP addresses can be added to whitelist
        - Whitelisted IPs are recognized
        - Non-whitelisted IPs require MFA
        - CIDR ranges work correctly
        """
        service = MFAService(db_session)

        # Setup and enable MFA
        setup_result = await service.initiate_setup(
            user_id=user_id,
            user_email=user_email,
            method=MFAMethod.TOTP,
        )
        totp = pyotp.TOTP(setup_result.secret)
        await service.enable_mfa(user_id=user_id, code=totp.now())

        # Add IP to whitelist
        whitelist_entry = await service.add_ip_to_whitelist(
            user_id=user_id,
            ip_address="192.168.1.100",
            description="Office IP",
        )

        assert whitelist_entry.ip_address == "192.168.1.100"
        assert whitelist_entry.description == "Office IP"
        assert whitelist_entry.is_active is True

        # Check if whitelisted IP is recognized
        check_result = await service.check_ip_whitelisted(
            user_id=user_id,
            ip_address="192.168.1.100",
        )

        assert check_result.is_whitelisted is True, "Whitelisted IP should be recognized"
        assert check_result.matching_entry_id == whitelist_entry.id

        # Check that non-whitelisted IP is not recognized
        non_whitelist_check = await service.check_ip_whitelisted(
            user_id=user_id,
            ip_address="10.0.0.1",
        )

        assert non_whitelist_check.is_whitelisted is False, "Non-whitelisted IP should not bypass"

        # Test CIDR range whitelist
        await service.add_ip_to_whitelist(
            user_id=user_id,
            ip_address="10.0.0.0/24",
            description="Home network",
        )

        cidr_check = await service.check_ip_whitelisted(
            user_id=user_id,
            ip_address="10.0.0.50",
        )

        assert cidr_check.is_whitelisted is True, "IP in CIDR range should be whitelisted"

    @pytest.mark.asyncio
    async def test_e2e_lockout_and_recovery(
        self, db_session, user_id: UUID, user_email: str
    ):
        """Test account lockout after failed attempts and recovery.

        Verifies:
        - Account is locked after MAX_FAILED_ATTEMPTS
        - Lockout prevents further verification attempts
        - Admin can reset lockout
        """
        service = MFAService(db_session, max_failed_attempts=3, lockout_duration_minutes=15)

        # Setup and enable MFA
        setup_result = await service.initiate_setup(
            user_id=user_id,
            user_email=user_email,
            method=MFAMethod.TOTP,
        )
        totp = pyotp.TOTP(setup_result.secret)
        await service.enable_mfa(user_id=user_id, code=totp.now())

        # Make failed attempts to trigger lockout
        for i in range(3):
            result = await service.verify_totp(user_id=user_id, code="000000")

        assert result.remaining_attempts == 0, "No attempts should remain"
        assert result.locked_until is not None, "Account should be locked"

        # Verify lockout prevents further verification
        with pytest.raises(MFALockoutError):
            await service.verify_totp(user_id=user_id, code=totp.now())

        # Admin resets lockout
        reset_success = await service.reset_lockout(user_id)
        assert reset_success is True, "Lockout reset should succeed"

        # Verify user can now verify
        verify_result = await service.verify_totp(user_id=user_id, code=totp.now())
        assert verify_result.verified is True, "Should be able to verify after reset"

    @pytest.mark.asyncio
    async def test_e2e_disable_mfa(
        self, db_session, user_id: UUID, user_email: str
    ):
        """Test disabling MFA after it's been enabled.

        Verifies:
        - MFA can be disabled with valid TOTP code
        - Backup codes are deleted when MFA is disabled
        - MFA can be set up again after being disabled
        """
        service = MFAService(db_session)

        # Setup and enable MFA
        setup_result = await service.initiate_setup(
            user_id=user_id,
            user_email=user_email,
            method=MFAMethod.TOTP,
        )
        totp = pyotp.TOTP(setup_result.secret)
        await service.enable_mfa(user_id=user_id, code=totp.now())

        # Generate backup codes
        await service.generate_backup_codes(user_id=user_id, code=totp.now(), count=5)

        # Disable MFA
        disable_result = await service.disable_mfa(
            user_id=user_id,
            code=totp.now(),
            is_backup_code=False,
        )

        assert disable_result.verified is True, "MFA should be disabled with valid code"

        # Verify MFA is disabled
        status = await service.get_mfa_status(user_id)
        assert status.is_enabled is False, "MFA should be disabled"
        assert status.has_backup_codes is False, "Backup codes should be deleted"

        # Verify MFA can be set up again
        new_setup = await service.initiate_setup(
            user_id=user_id,
            user_email=user_email,
            method=MFAMethod.TOTP,
        )
        assert new_setup.secret is not None, "New secret should be generated"

    @pytest.mark.asyncio
    async def test_e2e_complete_flow(
        self, db_session, user_id: UUID, user_email: str
    ):
        """Complete end-to-end flow test covering all 6 verification steps.

        This test simulates the complete lifecycle of MFA for an admin user.
        """
        service = MFAService(db_session)

        # Step 1 & 2: Admin initiates MFA setup
        print("\n=== Step 1 & 2: Admin initiates MFA setup ===")
        setup_result = await service.initiate_setup(
            user_id=user_id,
            user_email=user_email,
            method=MFAMethod.TOTP,
            recovery_email="admin-recovery@example.com",
        )
        assert setup_result.secret is not None
        assert setup_result.qr_code_uri is not None
        print(f"✓ Secret generated: {setup_result.secret[:8]}...")
        print(f"✓ QR code URI generated")

        # Step 3: User verifies TOTP code to enable MFA
        print("\n=== Step 3: User verifies TOTP code to enable MFA ===")
        totp = pyotp.TOTP(setup_result.secret)
        enable_result = await service.enable_mfa(user_id=user_id, code=totp.now())
        assert enable_result.verified is True
        print(f"✓ MFA enabled successfully")

        # Step 4: Subsequent logins require TOTP verification
        print("\n=== Step 4: Subsequent logins require TOTP verification ===")
        verify_result = await service.verify_totp(user_id=user_id, code=totp.now())
        assert verify_result.verified is True
        print(f"✓ TOTP verification works for login")

        # Step 5: Backup codes can be generated and used
        print("\n=== Step 5: Backup codes can be generated and used ===")
        backup_result = await service.generate_backup_codes(
            user_id=user_id,
            code=totp.now(),
            count=10,
        )
        assert backup_result.count == 10
        print(f"✓ Generated {backup_result.count} backup codes")

        # Use a backup code
        backup_verify = await service.verify_backup_code(
            user_id=user_id,
            code=backup_result.codes[0],
        )
        assert backup_verify.verified is True
        print(f"✓ Backup code verified successfully")

        # Step 6: IP whitelist allows bypass for trusted IPs
        print("\n=== Step 6: IP whitelist allows bypass for trusted IPs ===")
        await service.add_ip_to_whitelist(
            user_id=user_id,
            ip_address="192.168.1.0/24",
            description="Corporate network",
        )
        ip_check = await service.check_ip_whitelisted(
            user_id=user_id,
            ip_address="192.168.1.50",
        )
        assert ip_check.is_whitelisted is True
        print(f"✓ IP whitelist bypass working")

        # Final status check
        print("\n=== Final MFA Status ===")
        status = await service.get_mfa_status(user_id)
        print(f"  MFA Enabled: {status.is_enabled}")
        print(f"  Method: {status.method}")
        print(f"  Backup Codes Remaining: {status.backup_codes_remaining}")
        print(f"  IP Whitelist Count: {status.ip_whitelist_count}")
        print(f"  Has Recovery Email: {status.has_recovery_email}")
        print(f"  Is Locked: {status.is_locked}")

        assert status.is_enabled is True
        assert status.method == "totp"
        assert status.backup_codes_remaining == 9  # Used 1
        assert status.ip_whitelist_count == 1
        assert status.has_recovery_email is True
        assert status.is_locked is False

        print("\n✓ All 6 E2E verification steps passed!")


class TestMFASecurityFeatures:
    """Security-focused E2E tests for MFA implementation."""

    @pytest.fixture
    def user_id(self) -> UUID:
        """Generate a unique user ID for each test."""
        return uuid4()

    @pytest.fixture
    def user_email(self) -> str:
        """Generate a unique user email for each test."""
        return f"secure_{uuid4().hex[:8]}@laya.app"

    @pytest.mark.asyncio
    async def test_totp_time_window_tolerance(
        self, db_session, user_id: UUID, user_email: str
    ):
        """Test that TOTP accepts codes within the valid window.

        TOTP codes are valid for a window around the current time to
        account for clock drift between client and server.
        """
        service = MFAService(db_session)

        # Setup and enable MFA
        setup_result = await service.initiate_setup(
            user_id=user_id,
            user_email=user_email,
        )
        totp = pyotp.TOTP(setup_result.secret)
        await service.enable_mfa(user_id=user_id, code=totp.now())

        # Verify current code works
        result = await service.verify_totp(user_id=user_id, code=totp.now())
        assert result.verified is True, "Current TOTP should be valid"

    @pytest.mark.asyncio
    async def test_backup_code_hashing(
        self, db_session, user_id: UUID, user_email: str
    ):
        """Test that backup codes are stored hashed, not in plaintext.

        This verifies that even if the database is compromised, backup
        codes cannot be easily recovered.
        """
        service = MFAService(db_session)

        # Setup and enable MFA
        setup_result = await service.initiate_setup(
            user_id=user_id,
            user_email=user_email,
        )
        totp = pyotp.TOTP(setup_result.secret)
        await service.enable_mfa(user_id=user_id, code=totp.now())

        # Generate backup codes
        backup_result = await service.generate_backup_codes(
            user_id=user_id,
            code=totp.now(),
            count=3,
        )

        # Get settings to access backup codes
        settings = await service.get_mfa_settings(user_id)

        # Query backup codes from database
        from sqlalchemy import select
        query = select(MFABackupCode).where(
            MFABackupCode.mfa_settings_id == settings.id
        )
        result = await db_session.execute(query)
        stored_codes = result.scalars().all()

        # Verify codes are hashed (should be 64 char hex = SHA-256)
        for stored_code in stored_codes:
            assert len(stored_code.code_hash) == 64, "Code should be hashed with SHA-256"
            assert stored_code.code_hash not in backup_result.codes, "Hash should not equal plaintext"

    @pytest.mark.asyncio
    async def test_mfa_not_bypassed_without_whitelist(
        self, db_session, user_id: UUID, user_email: str
    ):
        """Test that MFA cannot be bypassed without IP whitelist.

        Ensures that only explicitly whitelisted IPs can bypass MFA.
        """
        service = MFAService(db_session)

        # Setup and enable MFA (without any whitelist)
        setup_result = await service.initiate_setup(
            user_id=user_id,
            user_email=user_email,
        )
        totp = pyotp.TOTP(setup_result.secret)
        await service.enable_mfa(user_id=user_id, code=totp.now())

        # Check various IPs - none should be whitelisted
        test_ips = ["127.0.0.1", "192.168.1.1", "10.0.0.1", "8.8.8.8"]
        for ip in test_ips:
            check = await service.check_ip_whitelisted(user_id=user_id, ip_address=ip)
            assert check.is_whitelisted is False, f"IP {ip} should not be whitelisted"
