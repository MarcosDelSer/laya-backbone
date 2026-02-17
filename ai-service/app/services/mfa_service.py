"""MFA service for LAYA AI Service.

Provides business logic for multi-factor authentication using TOTP.
Implements TOTP generation/verification, backup codes, IP whitelisting,
and account lockout protection.
"""

import hashlib
import ipaddress
import secrets
from datetime import datetime, timedelta, timezone
from typing import Optional
from uuid import UUID

import pyotp
from sqlalchemy import and_, func, select
from sqlalchemy.ext.asyncio import AsyncSession

from app.models.mfa import (
    MFABackupCode,
    MFAIPWhitelist,
    MFAMethod,
    MFASettings,
)
from app.schemas.mfa import (
    BackupCodeStatusResponse,
    BackupCodesResponse,
    IPWhitelistCheckResponse,
    IPWhitelistEntryResponse,
    MFASetupResponse,
    MFAStatusResponse,
    MFAVerifyResponse,
)


class MFAServiceError(Exception):
    """Base exception for MFA service errors."""

    pass


class MFANotEnabledError(MFAServiceError):
    """Raised when MFA is required but not enabled for the user."""

    pass


class MFAAlreadyEnabledError(MFAServiceError):
    """Raised when trying to set up MFA that is already enabled."""

    pass


class MFALockoutError(MFAServiceError):
    """Raised when the account is locked due to too many failed attempts."""

    def __init__(self, locked_until: datetime) -> None:
        """Initialize with lockout expiration time."""
        self.locked_until = locked_until
        super().__init__(f"Account locked until {locked_until}")


class InvalidCodeError(MFAServiceError):
    """Raised when an invalid TOTP or backup code is provided."""

    pass


class MFAService:
    """Service class for multi-factor authentication logic.

    Encapsulates business logic for TOTP-based MFA including setup,
    verification, backup codes, and IP whitelist management.

    Attributes:
        db: Async database session for database operations.
        issuer: The issuer name shown in authenticator apps.
        max_failed_attempts: Maximum failed attempts before lockout.
        lockout_duration_minutes: Duration of lockout in minutes.
        backup_code_length: Length of generated backup codes.
    """

    # Configuration constants
    DEFAULT_ISSUER = "LAYA"
    MAX_FAILED_ATTEMPTS = 5
    LOCKOUT_DURATION_MINUTES = 15
    BACKUP_CODE_LENGTH = 8
    DEFAULT_BACKUP_CODE_COUNT = 10
    TOTP_VALID_WINDOW = 1  # Accept codes from 1 interval before/after

    def __init__(
        self,
        db: AsyncSession,
        issuer: str = DEFAULT_ISSUER,
        max_failed_attempts: int = MAX_FAILED_ATTEMPTS,
        lockout_duration_minutes: int = LOCKOUT_DURATION_MINUTES,
    ) -> None:
        """Initialize MFAService with database session and configuration.

        Args:
            db: Async database session for database operations.
            issuer: The issuer name shown in authenticator apps.
            max_failed_attempts: Maximum failed attempts before lockout.
            lockout_duration_minutes: Duration of lockout in minutes.
        """
        self.db = db
        self.issuer = issuer
        self.max_failed_attempts = max_failed_attempts
        self.lockout_duration_minutes = lockout_duration_minutes

    async def get_mfa_settings(self, user_id: UUID) -> Optional[MFASettings]:
        """Retrieve MFA settings for a user.

        Args:
            user_id: Unique identifier of the user.

        Returns:
            MFASettings if found, None otherwise.
        """
        query = select(MFASettings).where(MFASettings.user_id == user_id)
        result = await self.db.execute(query)
        return result.scalar_one_or_none()

    async def get_mfa_status(self, user_id: UUID) -> MFAStatusResponse:
        """Get the current MFA status for a user.

        Args:
            user_id: Unique identifier of the user.

        Returns:
            MFAStatusResponse with current MFA configuration state.
        """
        settings = await self.get_mfa_settings(user_id)

        if settings is None:
            return MFAStatusResponse(
                user_id=user_id,
                is_enabled=False,
                method=None,
                has_recovery_email=False,
                has_backup_codes=False,
                backup_codes_remaining=0,
                ip_whitelist_count=0,
                last_verified_at=None,
                is_locked=False,
                locked_until=None,
            )

        # Count unused backup codes
        backup_query = select(func.count()).where(
            and_(
                MFABackupCode.mfa_settings_id == settings.id,
                MFABackupCode.is_used == False,
            )
        )
        backup_result = await self.db.execute(backup_query)
        backup_count = backup_result.scalar() or 0

        # Count active IP whitelist entries
        ip_query = select(func.count()).where(
            and_(
                MFAIPWhitelist.mfa_settings_id == settings.id,
                MFAIPWhitelist.is_active == True,
            )
        )
        ip_result = await self.db.execute(ip_query)
        ip_count = ip_result.scalar() or 0

        # Check if currently locked
        is_locked = self._is_account_locked(settings)

        return MFAStatusResponse(
            user_id=user_id,
            is_enabled=settings.is_enabled,
            method=settings.method.value if settings.is_enabled else None,
            has_recovery_email=settings.recovery_email is not None,
            has_backup_codes=backup_count > 0,
            backup_codes_remaining=backup_count,
            ip_whitelist_count=ip_count,
            last_verified_at=settings.last_verified_at,
            is_locked=is_locked,
            locked_until=settings.locked_until if is_locked else None,
        )

    async def initiate_setup(
        self,
        user_id: UUID,
        user_email: str,
        method: MFAMethod = MFAMethod.TOTP,
        recovery_email: Optional[str] = None,
    ) -> MFASetupResponse:
        """Initiate MFA setup for a user.

        Generates a new TOTP secret and returns the information needed
        for the user to configure their authenticator app.

        Args:
            user_id: Unique identifier of the user.
            user_email: User's email address for the authenticator label.
            method: The MFA method to set up (default: TOTP).
            recovery_email: Optional recovery email for emergency access.

        Returns:
            MFASetupResponse with secret and QR code information.

        Raises:
            MFAAlreadyEnabledError: If MFA is already enabled for the user.
        """
        settings = await self.get_mfa_settings(user_id)

        if settings is not None and settings.is_enabled:
            raise MFAAlreadyEnabledError(
                "MFA is already enabled. Disable it first to set up again."
            )

        # Generate new TOTP secret
        secret = pyotp.random_base32()
        totp = pyotp.TOTP(secret)

        # Generate provisioning URI for QR code
        provisioning_uri = totp.provisioning_uri(
            name=user_email,
            issuer_name=self.issuer,
        )

        # Create or update MFA settings (not enabled yet)
        if settings is None:
            settings = MFASettings(
                user_id=user_id,
                is_enabled=False,
                method=method,
                secret_key=secret,
                recovery_email=recovery_email,
                failed_attempts=0,
            )
            self.db.add(settings)
        else:
            settings.secret_key = secret
            settings.method = method
            if recovery_email is not None:
                settings.recovery_email = recovery_email

        await self.db.commit()

        return MFASetupResponse(
            secret=secret,
            qr_code_uri=provisioning_uri,
            qr_code_base64=None,
            method=method,
            issuer=self.issuer,
        )

    async def verify_totp(
        self,
        user_id: UUID,
        code: str,
        is_setup_verification: bool = False,
    ) -> MFAVerifyResponse:
        """Verify a TOTP code for a user.

        Args:
            user_id: Unique identifier of the user.
            code: The 6-digit TOTP code from authenticator app.
            is_setup_verification: Whether this is the initial setup verification.

        Returns:
            MFAVerifyResponse with verification result.

        Raises:
            MFANotEnabledError: If MFA setup hasn't been initiated.
            MFALockoutError: If account is locked due to too many failures.
        """
        settings = await self.get_mfa_settings(user_id)

        if settings is None or settings.secret_key is None:
            raise MFANotEnabledError("MFA has not been set up for this user.")

        # Check for lockout
        if self._is_account_locked(settings):
            raise MFALockoutError(settings.locked_until)

        # For non-setup verification, MFA must be enabled
        if not is_setup_verification and not settings.is_enabled:
            raise MFANotEnabledError("MFA is not enabled for this user.")

        # Verify the TOTP code
        totp = pyotp.TOTP(settings.secret_key)
        is_valid = totp.verify(code, valid_window=self.TOTP_VALID_WINDOW)

        if is_valid:
            # Reset failed attempts and update last verified
            settings.failed_attempts = 0
            settings.locked_until = None
            settings.last_verified_at = datetime.now(timezone.utc)
            await self.db.commit()

            return MFAVerifyResponse(
                verified=True,
                message="Code verified successfully.",
                remaining_attempts=None,
                locked_until=None,
            )
        else:
            # Increment failed attempts
            settings.failed_attempts += 1
            remaining = self.max_failed_attempts - settings.failed_attempts

            if settings.failed_attempts >= self.max_failed_attempts:
                settings.locked_until = datetime.now(timezone.utc) + timedelta(
                    minutes=self.lockout_duration_minutes
                )
                await self.db.commit()

                return MFAVerifyResponse(
                    verified=False,
                    message="Too many failed attempts. Account temporarily locked.",
                    remaining_attempts=0,
                    locked_until=settings.locked_until,
                )

            await self.db.commit()

            return MFAVerifyResponse(
                verified=False,
                message="Invalid code. Please try again.",
                remaining_attempts=remaining,
                locked_until=None,
            )

    async def enable_mfa(self, user_id: UUID, code: str) -> MFAVerifyResponse:
        """Enable MFA after verifying the setup code.

        Args:
            user_id: Unique identifier of the user.
            code: The 6-digit TOTP code to confirm setup.

        Returns:
            MFAVerifyResponse with result.

        Raises:
            MFANotEnabledError: If MFA setup hasn't been initiated.
            MFAAlreadyEnabledError: If MFA is already enabled.
        """
        settings = await self.get_mfa_settings(user_id)

        if settings is None or settings.secret_key is None:
            raise MFANotEnabledError("MFA has not been set up for this user.")

        if settings.is_enabled:
            raise MFAAlreadyEnabledError("MFA is already enabled for this user.")

        # Verify the code
        result = await self.verify_totp(user_id, code, is_setup_verification=True)

        if result.verified:
            settings.is_enabled = True
            settings.last_verified_at = datetime.now(timezone.utc)
            await self.db.commit()

            return MFAVerifyResponse(
                verified=True,
                message="MFA has been enabled successfully.",
                remaining_attempts=None,
                locked_until=None,
            )

        return result

    async def disable_mfa(
        self,
        user_id: UUID,
        code: str,
        is_backup_code: bool = False,
    ) -> MFAVerifyResponse:
        """Disable MFA for a user after verification.

        Args:
            user_id: Unique identifier of the user.
            code: The TOTP code or backup code for verification.
            is_backup_code: Whether the code is a backup code.

        Returns:
            MFAVerifyResponse with result.

        Raises:
            MFANotEnabledError: If MFA is not enabled.
        """
        settings = await self.get_mfa_settings(user_id)

        if settings is None or not settings.is_enabled:
            raise MFANotEnabledError("MFA is not enabled for this user.")

        # Verify the code
        if is_backup_code:
            result = await self.verify_backup_code(user_id, code)
        else:
            result = await self.verify_totp(user_id, code)

        if result.verified:
            settings.is_enabled = False
            settings.secret_key = None
            settings.failed_attempts = 0
            settings.locked_until = None

            # Delete all backup codes
            await self._delete_all_backup_codes(settings.id)

            await self.db.commit()

            return MFAVerifyResponse(
                verified=True,
                message="MFA has been disabled successfully.",
                remaining_attempts=None,
                locked_until=None,
            )

        return result

    async def generate_backup_codes(
        self,
        user_id: UUID,
        code: str,
        count: int = DEFAULT_BACKUP_CODE_COUNT,
    ) -> BackupCodesResponse:
        """Generate new backup codes for a user.

        Invalidates any existing backup codes before generating new ones.

        Args:
            user_id: Unique identifier of the user.
            code: Current TOTP code to verify identity.
            count: Number of backup codes to generate.

        Returns:
            BackupCodesResponse with newly generated codes.

        Raises:
            MFANotEnabledError: If MFA is not enabled.
            InvalidCodeError: If the provided TOTP code is invalid.
        """
        settings = await self.get_mfa_settings(user_id)

        if settings is None or not settings.is_enabled:
            raise MFANotEnabledError("MFA must be enabled to generate backup codes.")

        # Verify TOTP code first
        result = await self.verify_totp(user_id, code)
        if not result.verified:
            raise InvalidCodeError("Invalid TOTP code. Cannot generate backup codes.")

        # Delete existing backup codes
        await self._delete_all_backup_codes(settings.id)

        # Generate new backup codes
        plaintext_codes: list[str] = []
        for _ in range(count):
            code_plaintext = self._generate_backup_code()
            code_hash = self._hash_backup_code(code_plaintext)

            backup_code = MFABackupCode(
                mfa_settings_id=settings.id,
                code_hash=code_hash,
                is_used=False,
            )
            self.db.add(backup_code)
            plaintext_codes.append(code_plaintext)

        await self.db.commit()

        return BackupCodesResponse(
            codes=plaintext_codes,
            generated_at=datetime.now(timezone.utc),
            count=len(plaintext_codes),
            message="Save these codes in a secure location. Each code can only be used once.",
        )

    async def verify_backup_code(
        self,
        user_id: UUID,
        code: str,
    ) -> MFAVerifyResponse:
        """Verify a backup code for a user.

        Args:
            user_id: Unique identifier of the user.
            code: The backup code to verify.

        Returns:
            MFAVerifyResponse with verification result.

        Raises:
            MFANotEnabledError: If MFA is not enabled.
            MFALockoutError: If account is locked due to too many failures.
        """
        settings = await self.get_mfa_settings(user_id)

        if settings is None or not settings.is_enabled:
            raise MFANotEnabledError("MFA is not enabled for this user.")

        # Check for lockout
        if self._is_account_locked(settings):
            raise MFALockoutError(settings.locked_until)

        # Hash the provided code and look for a match
        code_hash = self._hash_backup_code(code.upper().replace("-", ""))

        query = select(MFABackupCode).where(
            and_(
                MFABackupCode.mfa_settings_id == settings.id,
                MFABackupCode.code_hash == code_hash,
                MFABackupCode.is_used == False,
            )
        )
        result = await self.db.execute(query)
        backup_code = result.scalar_one_or_none()

        if backup_code is not None:
            # Mark backup code as used
            backup_code.is_used = True
            backup_code.used_at = datetime.now(timezone.utc)

            # Reset failed attempts
            settings.failed_attempts = 0
            settings.locked_until = None
            settings.last_verified_at = datetime.now(timezone.utc)

            await self.db.commit()

            return MFAVerifyResponse(
                verified=True,
                message="Backup code verified successfully.",
                remaining_attempts=None,
                locked_until=None,
            )

        # Invalid backup code - increment failed attempts
        settings.failed_attempts += 1
        remaining = self.max_failed_attempts - settings.failed_attempts

        if settings.failed_attempts >= self.max_failed_attempts:
            settings.locked_until = datetime.now(timezone.utc) + timedelta(
                minutes=self.lockout_duration_minutes
            )
            await self.db.commit()

            return MFAVerifyResponse(
                verified=False,
                message="Too many failed attempts. Account temporarily locked.",
                remaining_attempts=0,
                locked_until=settings.locked_until,
            )

        await self.db.commit()

        return MFAVerifyResponse(
            verified=False,
            message="Invalid backup code.",
            remaining_attempts=remaining,
            locked_until=None,
        )

    async def get_backup_code_status(
        self,
        user_id: UUID,
    ) -> BackupCodeStatusResponse:
        """Get backup code status for a user.

        Args:
            user_id: Unique identifier of the user.

        Returns:
            BackupCodeStatusResponse with backup code information.

        Raises:
            MFANotEnabledError: If MFA is not enabled.
        """
        settings = await self.get_mfa_settings(user_id)

        if settings is None or not settings.is_enabled:
            raise MFANotEnabledError("MFA is not enabled for this user.")

        # Count total and used backup codes
        total_query = select(func.count()).where(
            MFABackupCode.mfa_settings_id == settings.id
        )
        total_result = await self.db.execute(total_query)
        total_count = total_result.scalar() or 0

        used_query = select(func.count()).where(
            and_(
                MFABackupCode.mfa_settings_id == settings.id,
                MFABackupCode.is_used == True,
            )
        )
        used_result = await self.db.execute(used_query)
        used_count = used_result.scalar() or 0

        # Get last used timestamp
        last_used_query = (
            select(MFABackupCode.used_at)
            .where(
                and_(
                    MFABackupCode.mfa_settings_id == settings.id,
                    MFABackupCode.is_used == True,
                )
            )
            .order_by(MFABackupCode.used_at.desc())
            .limit(1)
        )
        last_used_result = await self.db.execute(last_used_query)
        last_used_at = last_used_result.scalar_one_or_none()

        return BackupCodeStatusResponse(
            total_codes=total_count,
            used_codes=used_count,
            remaining_codes=total_count - used_count,
            last_used_at=last_used_at,
        )

    async def add_ip_to_whitelist(
        self,
        user_id: UUID,
        ip_address: str,
        description: Optional[str] = None,
    ) -> IPWhitelistEntryResponse:
        """Add an IP address to the user's MFA whitelist.

        Args:
            user_id: Unique identifier of the user.
            ip_address: The IP address or CIDR range to whitelist.
            description: Optional description of the IP address purpose.

        Returns:
            IPWhitelistEntryResponse with the created entry.

        Raises:
            MFANotEnabledError: If MFA is not enabled.
        """
        settings = await self.get_mfa_settings(user_id)

        if settings is None or not settings.is_enabled:
            raise MFANotEnabledError("MFA must be enabled to manage IP whitelist.")

        entry = MFAIPWhitelist(
            mfa_settings_id=settings.id,
            ip_address=ip_address,
            description=description,
            is_active=True,
        )
        self.db.add(entry)
        await self.db.commit()
        await self.db.refresh(entry)

        return IPWhitelistEntryResponse(
            id=entry.id,
            ip_address=entry.ip_address,
            description=entry.description,
            is_active=entry.is_active,
            mfa_settings_id=entry.mfa_settings_id,
            created_at=entry.created_at,
            updated_at=entry.updated_at,
        )

    async def remove_ip_from_whitelist(
        self,
        user_id: UUID,
        entry_id: UUID,
    ) -> bool:
        """Remove an IP address from the user's MFA whitelist.

        Args:
            user_id: Unique identifier of the user.
            entry_id: The ID of the whitelist entry to remove.

        Returns:
            True if the entry was removed, False if not found.

        Raises:
            MFANotEnabledError: If MFA is not enabled.
        """
        settings = await self.get_mfa_settings(user_id)

        if settings is None or not settings.is_enabled:
            raise MFANotEnabledError("MFA must be enabled to manage IP whitelist.")

        query = select(MFAIPWhitelist).where(
            and_(
                MFAIPWhitelist.id == entry_id,
                MFAIPWhitelist.mfa_settings_id == settings.id,
            )
        )
        result = await self.db.execute(query)
        entry = result.scalar_one_or_none()

        if entry is None:
            return False

        await self.db.delete(entry)
        await self.db.commit()
        return True

    async def get_ip_whitelist(
        self,
        user_id: UUID,
    ) -> list[IPWhitelistEntryResponse]:
        """Get all IP whitelist entries for a user.

        Args:
            user_id: Unique identifier of the user.

        Returns:
            List of IPWhitelistEntryResponse objects.

        Raises:
            MFANotEnabledError: If MFA is not enabled.
        """
        settings = await self.get_mfa_settings(user_id)

        if settings is None or not settings.is_enabled:
            raise MFANotEnabledError("MFA must be enabled to view IP whitelist.")

        query = select(MFAIPWhitelist).where(
            MFAIPWhitelist.mfa_settings_id == settings.id
        )
        result = await self.db.execute(query)
        entries = result.scalars().all()

        return [
            IPWhitelistEntryResponse(
                id=entry.id,
                ip_address=entry.ip_address,
                description=entry.description,
                is_active=entry.is_active,
                mfa_settings_id=entry.mfa_settings_id,
                created_at=entry.created_at,
                updated_at=entry.updated_at,
            )
            for entry in entries
        ]

    async def check_ip_whitelisted(
        self,
        user_id: UUID,
        ip_address: str,
    ) -> IPWhitelistCheckResponse:
        """Check if an IP address is whitelisted for a user.

        Args:
            user_id: Unique identifier of the user.
            ip_address: The IP address to check.

        Returns:
            IPWhitelistCheckResponse with the check result.
        """
        settings = await self.get_mfa_settings(user_id)

        if settings is None or not settings.is_enabled:
            return IPWhitelistCheckResponse(
                ip_address=ip_address,
                is_whitelisted=False,
                matching_entry_id=None,
            )

        # Get all active whitelist entries
        query = select(MFAIPWhitelist).where(
            and_(
                MFAIPWhitelist.mfa_settings_id == settings.id,
                MFAIPWhitelist.is_active == True,
            )
        )
        result = await self.db.execute(query)
        entries = result.scalars().all()

        # Check each entry for a match
        for entry in entries:
            if self._ip_matches_entry(ip_address, entry.ip_address):
                return IPWhitelistCheckResponse(
                    ip_address=ip_address,
                    is_whitelisted=True,
                    matching_entry_id=entry.id,
                )

        return IPWhitelistCheckResponse(
            ip_address=ip_address,
            is_whitelisted=False,
            matching_entry_id=None,
        )

    async def reset_lockout(self, user_id: UUID) -> bool:
        """Reset the lockout status for a user (admin function).

        Args:
            user_id: Unique identifier of the user.

        Returns:
            True if lockout was reset, False if user not found.
        """
        settings = await self.get_mfa_settings(user_id)

        if settings is None:
            return False

        settings.failed_attempts = 0
        settings.locked_until = None
        await self.db.commit()
        return True

    def _is_account_locked(self, settings: MFASettings) -> bool:
        """Check if an account is currently locked.

        Args:
            settings: The MFA settings to check.

        Returns:
            True if the account is locked, False otherwise.
        """
        if settings.locked_until is None:
            return False

        now = datetime.now(timezone.utc)
        locked_until = settings.locked_until

        # Ensure timezone awareness for comparison
        if locked_until.tzinfo is None:
            locked_until = locked_until.replace(tzinfo=timezone.utc)

        return now < locked_until

    def _generate_backup_code(self) -> str:
        """Generate a random backup code.

        Returns:
            A random alphanumeric backup code.
        """
        # Generate random bytes and convert to uppercase alphanumeric
        chars = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789"  # Exclude confusing chars
        code = "".join(secrets.choice(chars) for _ in range(self.BACKUP_CODE_LENGTH))
        return code

    def _hash_backup_code(self, code: str) -> str:
        """Hash a backup code for secure storage.

        Args:
            code: The plaintext backup code.

        Returns:
            SHA-256 hash of the code.
        """
        normalized = code.upper().replace("-", "").strip()
        return hashlib.sha256(normalized.encode()).hexdigest()

    async def _delete_all_backup_codes(self, mfa_settings_id: UUID) -> None:
        """Delete all backup codes for an MFA settings entry.

        Args:
            mfa_settings_id: The MFA settings ID.
        """
        query = select(MFABackupCode).where(
            MFABackupCode.mfa_settings_id == mfa_settings_id
        )
        result = await self.db.execute(query)
        codes = result.scalars().all()

        for code in codes:
            await self.db.delete(code)

    def _ip_matches_entry(self, ip_address: str, entry_ip: str) -> bool:
        """Check if an IP address matches a whitelist entry.

        Supports both exact IP matches and CIDR range matching.

        Args:
            ip_address: The IP address to check.
            entry_ip: The whitelist entry (IP or CIDR range).

        Returns:
            True if the IP matches the entry, False otherwise.
        """
        try:
            # Check if entry is a CIDR range
            if "/" in entry_ip:
                network = ipaddress.ip_network(entry_ip, strict=False)
                return ipaddress.ip_address(ip_address) in network
            else:
                return ip_address == entry_ip
        except ValueError:
            # Invalid IP format - no match
            return False
