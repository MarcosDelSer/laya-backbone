"""MFA domain schemas for LAYA AI Service.

Defines Pydantic schemas for multi-factor authentication requests and responses.
Supports TOTP-based MFA, backup code management, and IP whitelist functionality
for admin/director account security.
"""

from datetime import datetime
from enum import Enum
from typing import Optional
from uuid import UUID

from pydantic import BaseModel, Field, field_validator
import re

from app.schemas.base import BaseResponse, BaseSchema, PaginatedResponse


class MFAMethod(str, Enum):
    """Types of MFA authentication methods.

    Attributes:
        TOTP: Time-based One-Time Password (Google Authenticator, Authy)
        SMS: SMS-based verification (future support)
        EMAIL: Email-based verification (future support)
    """

    TOTP = "totp"
    SMS = "sms"
    EMAIL = "email"


# =============================================================================
# MFA Setup Schemas
# =============================================================================


class MFASetupRequest(BaseSchema):
    """Request schema for initiating MFA setup.

    Used when a user wants to enable MFA on their account.

    Attributes:
        method: The MFA method to set up (default: TOTP)
        recovery_email: Optional recovery email for emergency access
    """

    method: MFAMethod = Field(
        default=MFAMethod.TOTP,
        description="The MFA method to set up",
    )
    recovery_email: Optional[str] = Field(
        default=None,
        max_length=255,
        description="Optional recovery email for emergency access",
    )

    @field_validator("recovery_email")
    @classmethod
    def validate_email(cls, v: Optional[str]) -> Optional[str]:
        """Validate email format if provided."""
        if v is not None:
            email_pattern = r"^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$"
            if not re.match(email_pattern, v):
                raise ValueError("Invalid email format")
        return v


class MFASetupResponse(BaseSchema):
    """Response schema for MFA setup initiation.

    Returns the information needed for the user to configure their
    authenticator app, including the secret and QR code.

    Attributes:
        secret: The TOTP secret key (base32 encoded)
        qr_code_uri: The otpauth URI for QR code generation
        qr_code_base64: Base64-encoded QR code image (PNG)
        method: The MFA method being set up
        issuer: The issuer name shown in authenticator apps
    """

    secret: str = Field(
        ...,
        description="The TOTP secret key (base32 encoded)",
    )
    qr_code_uri: str = Field(
        ...,
        description="The otpauth URI for QR code generation",
    )
    qr_code_base64: Optional[str] = Field(
        default=None,
        description="Base64-encoded QR code image (PNG)",
    )
    method: MFAMethod = Field(
        ...,
        description="The MFA method being set up",
    )
    issuer: str = Field(
        default="LAYA",
        description="The issuer name shown in authenticator apps",
    )


# =============================================================================
# MFA Verification Schemas
# =============================================================================


class MFAVerifyRequest(BaseSchema):
    """Request schema for verifying an MFA code.

    Used during MFA setup confirmation and login verification.

    Attributes:
        code: The 6-digit TOTP code from authenticator app
        is_setup_verification: Whether this is the initial setup verification
    """

    code: str = Field(
        ...,
        min_length=6,
        max_length=8,
        description="The TOTP code from authenticator app (6-8 digits)",
    )
    is_setup_verification: bool = Field(
        default=False,
        description="Whether this is the initial setup verification",
    )

    @field_validator("code")
    @classmethod
    def validate_code_format(cls, v: str) -> str:
        """Validate that code contains only digits."""
        if not v.isdigit():
            raise ValueError("Code must contain only digits")
        return v


class MFAVerifyResponse(BaseSchema):
    """Response schema for MFA code verification.

    Returns the result of the verification attempt.

    Attributes:
        verified: Whether the code was valid
        message: Human-readable result message
        remaining_attempts: Number of attempts remaining before lockout
        locked_until: Timestamp when lockout expires (if locked)
    """

    verified: bool = Field(
        ...,
        description="Whether the code was valid",
    )
    message: str = Field(
        ...,
        description="Human-readable result message",
    )
    remaining_attempts: Optional[int] = Field(
        default=None,
        ge=0,
        description="Number of attempts remaining before lockout",
    )
    locked_until: Optional[datetime] = Field(
        default=None,
        description="Timestamp when lockout expires (if locked)",
    )


# =============================================================================
# MFA Status Schemas
# =============================================================================


class MFAStatusResponse(BaseSchema):
    """Response schema for MFA status check.

    Returns the current MFA configuration state for a user.

    Attributes:
        user_id: The user's unique identifier
        is_enabled: Whether MFA is currently enabled
        method: The configured MFA method
        has_recovery_email: Whether a recovery email is configured
        has_backup_codes: Whether backup codes have been generated
        backup_codes_remaining: Number of unused backup codes
        ip_whitelist_count: Number of whitelisted IP addresses
        last_verified_at: Timestamp of last successful verification
        is_locked: Whether the account is currently locked
        locked_until: Timestamp when lockout expires (if locked)
    """

    user_id: UUID = Field(
        ...,
        description="The user's unique identifier",
    )
    is_enabled: bool = Field(
        ...,
        description="Whether MFA is currently enabled",
    )
    method: Optional[MFAMethod] = Field(
        default=None,
        description="The configured MFA method",
    )
    has_recovery_email: bool = Field(
        default=False,
        description="Whether a recovery email is configured",
    )
    has_backup_codes: bool = Field(
        default=False,
        description="Whether backup codes have been generated",
    )
    backup_codes_remaining: int = Field(
        default=0,
        ge=0,
        description="Number of unused backup codes",
    )
    ip_whitelist_count: int = Field(
        default=0,
        ge=0,
        description="Number of whitelisted IP addresses",
    )
    last_verified_at: Optional[datetime] = Field(
        default=None,
        description="Timestamp of last successful verification",
    )
    is_locked: bool = Field(
        default=False,
        description="Whether the account is currently locked",
    )
    locked_until: Optional[datetime] = Field(
        default=None,
        description="Timestamp when lockout expires (if locked)",
    )


class MFAEnableRequest(BaseSchema):
    """Request schema for enabling MFA after setup verification.

    Sent after the user has verified their TOTP code during setup.

    Attributes:
        code: The 6-digit TOTP code to confirm setup
    """

    code: str = Field(
        ...,
        min_length=6,
        max_length=8,
        description="The TOTP code to confirm MFA setup",
    )

    @field_validator("code")
    @classmethod
    def validate_code_format(cls, v: str) -> str:
        """Validate that code contains only digits."""
        if not v.isdigit():
            raise ValueError("Code must contain only digits")
        return v


class MFADisableRequest(BaseSchema):
    """Request schema for disabling MFA.

    Requires verification before MFA can be disabled.

    Attributes:
        code: The 6-digit TOTP code or backup code
        is_backup_code: Whether the code is a backup code
    """

    code: str = Field(
        ...,
        min_length=6,
        max_length=12,
        description="The TOTP code or backup code",
    )
    is_backup_code: bool = Field(
        default=False,
        description="Whether the code is a backup code",
    )


# =============================================================================
# Backup Code Schemas
# =============================================================================


class BackupCodesGenerateRequest(BaseSchema):
    """Request schema for generating new backup codes.

    Generates a new set of backup codes, invalidating any existing ones.

    Attributes:
        code: Current TOTP code to verify identity
        count: Number of backup codes to generate (default: 10)
    """

    code: str = Field(
        ...,
        min_length=6,
        max_length=8,
        description="Current TOTP code to verify identity",
    )
    count: int = Field(
        default=10,
        ge=5,
        le=20,
        description="Number of backup codes to generate",
    )

    @field_validator("code")
    @classmethod
    def validate_code_format(cls, v: str) -> str:
        """Validate that code contains only digits."""
        if not v.isdigit():
            raise ValueError("Code must contain only digits")
        return v


class BackupCodesResponse(BaseSchema):
    """Response schema for backup code generation.

    Returns the newly generated backup codes (shown only once).

    Attributes:
        codes: List of newly generated backup codes (plaintext, shown once)
        generated_at: Timestamp when codes were generated
        count: Total number of codes generated
        message: Instructions for the user
    """

    codes: list[str] = Field(
        ...,
        description="List of newly generated backup codes (shown only once)",
    )
    generated_at: datetime = Field(
        ...,
        description="Timestamp when codes were generated",
    )
    count: int = Field(
        ...,
        ge=1,
        description="Total number of codes generated",
    )
    message: str = Field(
        default="Save these codes in a secure location. Each code can only be used once.",
        description="Instructions for the user",
    )


class BackupCodeVerifyRequest(BaseSchema):
    """Request schema for verifying a backup code.

    Used when the user cannot access their authenticator app.

    Attributes:
        code: The backup code to verify
    """

    code: str = Field(
        ...,
        min_length=8,
        max_length=12,
        description="The backup code to verify",
    )


class BackupCodeStatusResponse(BaseSchema):
    """Response schema for backup code status.

    Returns information about remaining backup codes.

    Attributes:
        total_codes: Total number of backup codes generated
        used_codes: Number of backup codes already used
        remaining_codes: Number of backup codes still available
        last_used_at: Timestamp when a code was last used
    """

    total_codes: int = Field(
        ...,
        ge=0,
        description="Total number of backup codes generated",
    )
    used_codes: int = Field(
        ...,
        ge=0,
        description="Number of backup codes already used",
    )
    remaining_codes: int = Field(
        ...,
        ge=0,
        description="Number of backup codes still available",
    )
    last_used_at: Optional[datetime] = Field(
        default=None,
        description="Timestamp when a code was last used",
    )


# =============================================================================
# IP Whitelist Schemas
# =============================================================================


class IPWhitelistEntryBase(BaseSchema):
    """Base schema for IP whitelist entry data.

    Contains common fields shared between request and response schemas.

    Attributes:
        ip_address: The IP address or CIDR range to whitelist
        description: Optional description of the IP address purpose
    """

    ip_address: str = Field(
        ...,
        min_length=7,
        max_length=45,
        description="The IP address or CIDR range to whitelist",
    )
    description: Optional[str] = Field(
        default=None,
        max_length=500,
        description="Optional description of the IP address purpose",
    )

    @field_validator("ip_address")
    @classmethod
    def validate_ip_address(cls, v: str) -> str:
        """Validate IP address or CIDR format."""
        # IPv4 pattern (with optional CIDR)
        ipv4_pattern = r"^(\d{1,3}\.){3}\d{1,3}(\/\d{1,2})?$"
        # IPv6 pattern (simplified, with optional CIDR)
        ipv6_pattern = r"^([0-9a-fA-F:]+)(\/\d{1,3})?$"

        if not (re.match(ipv4_pattern, v) or re.match(ipv6_pattern, v)):
            raise ValueError("Invalid IP address or CIDR format")

        # Additional validation for IPv4 octets
        if re.match(ipv4_pattern, v):
            ip_part = v.split("/")[0]
            octets = ip_part.split(".")
            for octet in octets:
                if int(octet) > 255:
                    raise ValueError("Invalid IPv4 address: octet value exceeds 255")

        return v


class IPWhitelistCreateRequest(IPWhitelistEntryBase):
    """Request schema for adding an IP to the whitelist.

    Inherits IP address and description from base schema.
    """

    pass


class IPWhitelistUpdateRequest(BaseSchema):
    """Request schema for updating an IP whitelist entry.

    Attributes:
        description: Updated description for the entry
        is_active: Whether the entry should be active
    """

    description: Optional[str] = Field(
        default=None,
        max_length=500,
        description="Updated description for the entry",
    )
    is_active: Optional[bool] = Field(
        default=None,
        description="Whether the entry should be active",
    )


class IPWhitelistEntryResponse(IPWhitelistEntryBase, BaseResponse):
    """Response schema for IP whitelist entry data.

    Includes all base fields plus ID, timestamps, and status.

    Attributes:
        is_active: Whether the whitelist entry is currently active
        mfa_settings_id: Reference to the parent MFA settings
    """

    is_active: bool = Field(
        default=True,
        description="Whether the whitelist entry is currently active",
    )
    mfa_settings_id: UUID = Field(
        ...,
        description="Reference to the parent MFA settings",
    )


class IPWhitelistListResponse(PaginatedResponse):
    """Paginated list of IP whitelist entries.

    Attributes:
        items: List of IP whitelist entries
    """

    items: list[IPWhitelistEntryResponse] = Field(
        ...,
        description="List of IP whitelist entries",
    )


class IPWhitelistCheckRequest(BaseSchema):
    """Request schema for checking if an IP is whitelisted.

    Attributes:
        ip_address: The IP address to check
    """

    ip_address: str = Field(
        ...,
        min_length=7,
        max_length=45,
        description="The IP address to check",
    )


class IPWhitelistCheckResponse(BaseSchema):
    """Response schema for IP whitelist check.

    Attributes:
        ip_address: The IP address that was checked
        is_whitelisted: Whether the IP is whitelisted
        matching_entry_id: ID of the matching whitelist entry (if found)
    """

    ip_address: str = Field(
        ...,
        description="The IP address that was checked",
    )
    is_whitelisted: bool = Field(
        ...,
        description="Whether the IP is whitelisted",
    )
    matching_entry_id: Optional[UUID] = Field(
        default=None,
        description="ID of the matching whitelist entry (if found)",
    )


# =============================================================================
# Session Validation Schemas
# =============================================================================


class MFASessionValidateRequest(BaseSchema):
    """Request schema for validating MFA session status.

    Used to check if a session has valid MFA verification.

    Attributes:
        session_token: The session token to validate
        ip_address: The client's IP address for whitelist check
    """

    session_token: str = Field(
        ...,
        min_length=1,
        description="The session token to validate",
    )
    ip_address: Optional[str] = Field(
        default=None,
        description="The client's IP address for whitelist check",
    )


class MFASessionValidateResponse(BaseSchema):
    """Response schema for MFA session validation.

    Returns whether the session has valid MFA verification.

    Attributes:
        is_valid: Whether MFA verification is valid for this session
        requires_mfa: Whether MFA verification is required
        is_ip_whitelisted: Whether the IP bypasses MFA requirement
        session_expires_at: When the MFA session expires
        message: Human-readable status message
    """

    is_valid: bool = Field(
        ...,
        description="Whether MFA verification is valid for this session",
    )
    requires_mfa: bool = Field(
        ...,
        description="Whether MFA verification is required",
    )
    is_ip_whitelisted: bool = Field(
        default=False,
        description="Whether the IP bypasses MFA requirement",
    )
    session_expires_at: Optional[datetime] = Field(
        default=None,
        description="When the MFA session expires",
    )
    message: str = Field(
        ...,
        description="Human-readable status message",
    )
