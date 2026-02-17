"""MFA SQLAlchemy models for LAYA AI Service.

Defines database models for multi-factor authentication functionality
including MFA settings, backup codes, and IP whitelist management.
"""

from datetime import datetime
from enum import Enum as PyEnum
from typing import Optional
from uuid import UUID, uuid4

from sqlalchemy import (
    Boolean,
    DateTime,
    Enum,
    ForeignKey,
    String,
    Text,
    func,
)
from sqlalchemy.dialects.postgresql import UUID as PGUUID
from sqlalchemy.orm import Mapped, mapped_column, relationship

from app.models.base import Base


class MFAMethod(str, PyEnum):
    """Types of MFA authentication methods.

    Attributes:
        TOTP: Time-based One-Time Password (Google Authenticator, Authy)
        SMS: SMS-based verification (future support)
        EMAIL: Email-based verification (future support)
    """

    TOTP = "totp"
    SMS = "sms"
    EMAIL = "email"


class MFASettings(Base):
    """SQLAlchemy model for user MFA settings.

    Stores MFA configuration and TOTP secrets for admin/director accounts.
    Manages the overall MFA state for a user.

    Attributes:
        id: Unique identifier for the MFA settings
        user_id: Unique identifier of the user (from external auth system)
        is_enabled: Whether MFA is currently enabled for the user
        method: The MFA method being used (default: TOTP)
        secret_key: Encrypted TOTP secret key for generating codes
        recovery_email: Optional recovery email for MFA bypass
        last_verified_at: Timestamp of last successful MFA verification
        failed_attempts: Count of consecutive failed MFA attempts
        locked_until: Timestamp until which MFA is locked after too many failures
        created_at: Timestamp when the record was created
        updated_at: Timestamp when the record was last updated
    """

    __tablename__ = "mfa_settings"

    id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        primary_key=True,
        default=uuid4,
    )
    user_id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        nullable=False,
        unique=True,
        index=True,
    )
    is_enabled: Mapped[bool] = mapped_column(
        Boolean,
        nullable=False,
        default=False,
        index=True,
    )
    method: Mapped[MFAMethod] = mapped_column(
        Enum(MFAMethod, name="mfa_method_enum", create_constraint=True),
        nullable=False,
        default=MFAMethod.TOTP,
    )
    secret_key: Mapped[Optional[str]] = mapped_column(
        String(255),
        nullable=True,
    )
    recovery_email: Mapped[Optional[str]] = mapped_column(
        String(255),
        nullable=True,
    )
    last_verified_at: Mapped[Optional[datetime]] = mapped_column(
        DateTime(timezone=True),
        nullable=True,
    )
    failed_attempts: Mapped[int] = mapped_column(
        nullable=False,
        default=0,
    )
    locked_until: Mapped[Optional[datetime]] = mapped_column(
        DateTime(timezone=True),
        nullable=True,
    )
    created_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True),
        nullable=False,
        server_default=func.now(),
    )
    updated_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True),
        nullable=False,
        server_default=func.now(),
        onupdate=func.now(),
    )

    # Relationships
    backup_codes: Mapped[list["MFABackupCode"]] = relationship(
        "MFABackupCode",
        back_populates="mfa_settings",
        cascade="all, delete-orphan",
    )
    ip_whitelist: Mapped[list["MFAIPWhitelist"]] = relationship(
        "MFAIPWhitelist",
        back_populates="mfa_settings",
        cascade="all, delete-orphan",
    )

    def __repr__(self) -> str:
        """Return string representation of the MFASettings."""
        return (
            f"<MFASettings(id={self.id}, user_id={self.user_id}, "
            f"enabled={self.is_enabled}, method={self.method.value})>"
        )


class MFABackupCode(Base):
    """SQLAlchemy model for MFA backup codes.

    Stores hashed backup codes that can be used for account recovery
    when the primary MFA method is unavailable.

    Attributes:
        id: Unique identifier for the backup code
        mfa_settings_id: Foreign key to the MFA settings
        code_hash: Hashed backup code (never store plaintext)
        is_used: Whether this backup code has been used
        used_at: Timestamp when the code was used (if applicable)
        created_at: Timestamp when the record was created
    """

    __tablename__ = "mfa_backup_codes"

    id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        primary_key=True,
        default=uuid4,
    )
    mfa_settings_id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        ForeignKey("mfa_settings.id", ondelete="CASCADE"),
        nullable=False,
        index=True,
    )
    code_hash: Mapped[str] = mapped_column(
        String(255),
        nullable=False,
    )
    is_used: Mapped[bool] = mapped_column(
        Boolean,
        nullable=False,
        default=False,
        index=True,
    )
    used_at: Mapped[Optional[datetime]] = mapped_column(
        DateTime(timezone=True),
        nullable=True,
    )
    created_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True),
        nullable=False,
        server_default=func.now(),
    )

    # Relationships
    mfa_settings: Mapped["MFASettings"] = relationship(
        "MFASettings",
        back_populates="backup_codes",
    )

    def __repr__(self) -> str:
        """Return string representation of the MFABackupCode."""
        return (
            f"<MFABackupCode(id={self.id}, mfa_settings_id={self.mfa_settings_id}, "
            f"used={self.is_used})>"
        )


class MFAIPWhitelist(Base):
    """SQLAlchemy model for MFA IP whitelist.

    Stores IP addresses that are allowed to bypass MFA verification,
    typically used for trusted office networks or VPNs.

    Attributes:
        id: Unique identifier for the whitelist entry
        mfa_settings_id: Foreign key to the MFA settings
        ip_address: The whitelisted IP address or CIDR range
        description: Optional description of the IP address purpose
        is_active: Whether this whitelist entry is currently active
        created_at: Timestamp when the record was created
        updated_at: Timestamp when the record was last updated
    """

    __tablename__ = "mfa_ip_whitelist"

    id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        primary_key=True,
        default=uuid4,
    )
    mfa_settings_id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        ForeignKey("mfa_settings.id", ondelete="CASCADE"),
        nullable=False,
        index=True,
    )
    ip_address: Mapped[str] = mapped_column(
        String(45),
        nullable=False,
    )
    description: Mapped[Optional[str]] = mapped_column(
        Text,
        nullable=True,
    )
    is_active: Mapped[bool] = mapped_column(
        Boolean,
        nullable=False,
        default=True,
        index=True,
    )
    created_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True),
        nullable=False,
        server_default=func.now(),
    )
    updated_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True),
        nullable=False,
        server_default=func.now(),
        onupdate=func.now(),
    )

    # Relationships
    mfa_settings: Mapped["MFASettings"] = relationship(
        "MFASettings",
        back_populates="ip_whitelist",
    )

    def __repr__(self) -> str:
        """Return string representation of the MFAIPWhitelist."""
        return (
            f"<MFAIPWhitelist(id={self.id}, ip={self.ip_address}, "
            f"active={self.is_active})>"
        )
