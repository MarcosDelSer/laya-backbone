"""Storage SQLAlchemy models for LAYA AI Service.

Defines database models for file storage, thumbnails, and quota management.
Files represent uploaded files stored either locally or in S3.
"""

from datetime import datetime
from enum import Enum as PyEnum
from typing import Optional
from uuid import UUID, uuid4

from sqlalchemy import (
    BigInteger,
    Boolean,
    DateTime,
    Enum,
    ForeignKey,
    Integer,
    String,
    Text,
    func,
)
from sqlalchemy.dialects.postgresql import UUID as PGUUID
from sqlalchemy.orm import Mapped, mapped_column, relationship

from app.models.base import Base


class StorageBackend(str, PyEnum):
    """Storage backend types.

    Attributes:
        LOCAL: Local filesystem storage
        S3: Amazon S3 or S3-compatible storage
    """

    LOCAL = "local"
    S3 = "s3"


class ThumbnailSize(str, PyEnum):
    """Thumbnail size presets.

    Attributes:
        SMALL: Small thumbnail (e.g., 100x100)
        MEDIUM: Medium thumbnail (e.g., 300x300)
        LARGE: Large thumbnail (e.g., 600x600)
    """

    SMALL = "small"
    MEDIUM = "medium"
    LARGE = "large"


class File(Base):
    """SQLAlchemy model for uploaded files.

    Represents a file uploaded to the storage system, with metadata
    about the file and its storage location.

    Attributes:
        id: Unique identifier for the file
        owner_id: UUID of the user who owns the file
        filename: Stored filename (may be sanitized/hashed)
        original_filename: Original filename as uploaded by user
        content_type: MIME type of the file
        size_bytes: File size in bytes
        storage_backend: Storage backend type (local or S3)
        storage_path: Path to the file in the storage backend
        checksum: SHA-256 checksum of the file content
        is_public: Whether the file is publicly accessible
        description: Optional description of the file
        created_at: Timestamp when the file was uploaded
        updated_at: Timestamp when the record was last updated
    """

    __tablename__ = "files"

    id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        primary_key=True,
        default=uuid4,
    )
    owner_id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        nullable=False,
        index=True,
    )
    filename: Mapped[str] = mapped_column(
        String(255),
        nullable=False,
    )
    original_filename: Mapped[str] = mapped_column(
        String(255),
        nullable=False,
    )
    content_type: Mapped[str] = mapped_column(
        String(100),
        nullable=False,
        index=True,
    )
    size_bytes: Mapped[int] = mapped_column(
        BigInteger,
        nullable=False,
    )
    storage_backend: Mapped[StorageBackend] = mapped_column(
        Enum(StorageBackend, name="storage_backend_enum", create_constraint=True),
        nullable=False,
        index=True,
    )
    storage_path: Mapped[str] = mapped_column(
        String(500),
        nullable=False,
    )
    checksum: Mapped[Optional[str]] = mapped_column(
        String(64),
        nullable=True,
    )
    is_public: Mapped[bool] = mapped_column(
        Boolean,
        nullable=False,
        default=False,
        index=True,
    )
    description: Mapped[Optional[str]] = mapped_column(
        Text,
        nullable=True,
    )
    created_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True),
        nullable=False,
        server_default=func.now(),
        index=True,
    )
    updated_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True),
        nullable=False,
        server_default=func.now(),
        onupdate=func.now(),
    )

    # Relationships
    thumbnails: Mapped[list["FileThumbnail"]] = relationship(
        "FileThumbnail",
        back_populates="file",
        cascade="all, delete-orphan",
    )

    def __repr__(self) -> str:
        """Return string representation of the File."""
        return (
            f"<File(id={self.id}, filename='{self.original_filename}', "
            f"size={self.size_bytes}, backend={self.storage_backend.value})>"
        )


class FileThumbnail(Base):
    """SQLAlchemy model for file thumbnails.

    Stores metadata about generated thumbnails for image files.
    Thumbnails are generated in different sizes for various use cases.

    Attributes:
        id: Unique identifier for the thumbnail
        file_id: UUID of the parent file
        size: Thumbnail size preset (small, medium, large)
        width: Thumbnail width in pixels
        height: Thumbnail height in pixels
        storage_path: Path to the thumbnail in the storage backend
        created_at: Timestamp when the thumbnail was created
    """

    __tablename__ = "file_thumbnails"

    id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        primary_key=True,
        default=uuid4,
    )
    file_id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        ForeignKey("files.id", ondelete="CASCADE"),
        nullable=False,
        index=True,
    )
    size: Mapped[ThumbnailSize] = mapped_column(
        Enum(ThumbnailSize, name="thumbnail_size_enum", create_constraint=True),
        nullable=False,
    )
    width: Mapped[int] = mapped_column(
        Integer,
        nullable=False,
    )
    height: Mapped[int] = mapped_column(
        Integer,
        nullable=False,
    )
    storage_path: Mapped[str] = mapped_column(
        String(500),
        nullable=False,
    )
    created_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True),
        nullable=False,
        server_default=func.now(),
    )

    # Relationships
    file: Mapped["File"] = relationship(
        "File",
        back_populates="thumbnails",
    )

    def __repr__(self) -> str:
        """Return string representation of the FileThumbnail."""
        return (
            f"<FileThumbnail(id={self.id}, file_id={self.file_id}, "
            f"size={self.size.value}, {self.width}x{self.height})>"
        )


class StorageQuota(Base):
    """SQLAlchemy model for storage quota tracking.

    Tracks storage usage and quota limits per user. Used to enforce
    storage limits and monitor usage patterns.

    Attributes:
        id: Unique identifier for the quota record
        owner_id: UUID of the user this quota applies to
        quota_bytes: Maximum allowed storage in bytes
        used_bytes: Current storage usage in bytes
        file_count: Number of files stored
        created_at: Timestamp when the quota record was created
        updated_at: Timestamp when the record was last updated
    """

    __tablename__ = "storage_quotas"

    id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        primary_key=True,
        default=uuid4,
    )
    owner_id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        nullable=False,
        unique=True,
        index=True,
    )
    quota_bytes: Mapped[int] = mapped_column(
        BigInteger,
        nullable=False,
        default=104857600,  # 100 MB default quota
    )
    used_bytes: Mapped[int] = mapped_column(
        BigInteger,
        nullable=False,
        default=0,
    )
    file_count: Mapped[int] = mapped_column(
        Integer,
        nullable=False,
        default=0,
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

    def __repr__(self) -> str:
        """Return string representation of the StorageQuota."""
        usage_percent = (self.used_bytes / self.quota_bytes * 100) if self.quota_bytes > 0 else 0
        return (
            f"<StorageQuota(id={self.id}, owner_id={self.owner_id}, "
            f"usage={usage_percent:.1f}%, files={self.file_count})>"
        )

    @property
    def available_bytes(self) -> int:
        """Calculate available storage space in bytes."""
        return max(0, self.quota_bytes - self.used_bytes)

    @property
    def usage_percentage(self) -> float:
        """Calculate storage usage as a percentage."""
        if self.quota_bytes <= 0:
            return 0.0
        return (self.used_bytes / self.quota_bytes) * 100
