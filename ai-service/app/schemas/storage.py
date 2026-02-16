"""Storage domain schemas for LAYA AI Service.

Defines Pydantic schemas for file upload, storage management, and quota tracking.
Files represent uploaded files stored either locally or in S3.
"""

from datetime import datetime
from enum import Enum
from typing import Optional
from uuid import UUID

from pydantic import Field

from app.schemas.base import BaseResponse, BaseSchema, PaginatedResponse


class StorageBackend(str, Enum):
    """Storage backend types.

    Attributes:
        LOCAL: Local filesystem storage
        S3: Amazon S3 or S3-compatible storage
    """

    LOCAL = "local"
    S3 = "s3"


class ThumbnailSize(str, Enum):
    """Thumbnail size presets.

    Attributes:
        SMALL: Small thumbnail (e.g., 100x100)
        MEDIUM: Medium thumbnail (e.g., 300x300)
        LARGE: Large thumbnail (e.g., 600x600)
    """

    SMALL = "small"
    MEDIUM = "medium"
    LARGE = "large"


class ThumbnailResponse(BaseSchema):
    """Response schema for file thumbnail data.

    Attributes:
        id: Unique identifier for the thumbnail
        file_id: UUID of the parent file
        size: Thumbnail size preset
        width: Thumbnail width in pixels
        height: Thumbnail height in pixels
        url: URL to access the thumbnail
        created_at: Timestamp when the thumbnail was created
    """

    id: UUID = Field(
        ...,
        description="Unique identifier for the thumbnail",
    )
    file_id: UUID = Field(
        ...,
        description="UUID of the parent file",
    )
    size: ThumbnailSize = Field(
        ...,
        description="Thumbnail size preset",
    )
    width: int = Field(
        ...,
        ge=1,
        description="Thumbnail width in pixels",
    )
    height: int = Field(
        ...,
        ge=1,
        description="Thumbnail height in pixels",
    )
    url: Optional[str] = Field(
        default=None,
        description="URL to access the thumbnail",
    )
    created_at: Optional[datetime] = Field(
        default=None,
        description="Timestamp when the thumbnail was created",
    )


class FileBase(BaseSchema):
    """Base schema for file data.

    Contains common fields shared between request and response schemas.

    Attributes:
        original_filename: Original filename as uploaded by user
        content_type: MIME type of the file
        description: Optional description of the file
        is_public: Whether the file is publicly accessible
    """

    original_filename: str = Field(
        ...,
        min_length=1,
        max_length=255,
        description="Original filename as uploaded by user",
    )
    content_type: str = Field(
        ...,
        min_length=1,
        max_length=100,
        description="MIME type of the file",
    )
    description: Optional[str] = Field(
        default=None,
        description="Optional description of the file",
    )
    is_public: bool = Field(
        default=False,
        description="Whether the file is publicly accessible",
    )


class FileUploadRequest(BaseSchema):
    """Request schema for file upload metadata.

    Used to provide additional metadata when uploading a file.
    The actual file content is handled via multipart form data.

    Attributes:
        description: Optional description of the file
        is_public: Whether the file should be publicly accessible
    """

    description: Optional[str] = Field(
        default=None,
        description="Optional description of the file",
    )
    is_public: bool = Field(
        default=False,
        description="Whether the file should be publicly accessible",
    )


class FileUpdateRequest(BaseSchema):
    """Request schema for updating file metadata.

    All fields are optional; only provided fields will be updated.

    Attributes:
        description: New description for the file
        is_public: New public access setting
    """

    description: Optional[str] = Field(
        default=None,
        description="New description for the file",
    )
    is_public: Optional[bool] = Field(
        default=None,
        description="New public access setting",
    )


class FileResponse(FileBase, BaseResponse):
    """Response schema for file data.

    Includes all base file fields plus storage details and timestamps.

    Attributes:
        owner_id: UUID of the user who owns the file
        filename: Stored filename (may be sanitized/hashed)
        size_bytes: File size in bytes
        storage_backend: Storage backend type (local or S3)
        checksum: SHA-256 checksum of the file content
        thumbnails: List of available thumbnails (for images)
        download_url: URL to download the file
    """

    owner_id: UUID = Field(
        ...,
        description="UUID of the user who owns the file",
    )
    filename: str = Field(
        ...,
        min_length=1,
        max_length=255,
        description="Stored filename (may be sanitized/hashed)",
    )
    size_bytes: int = Field(
        ...,
        ge=0,
        description="File size in bytes",
    )
    storage_backend: StorageBackend = Field(
        ...,
        description="Storage backend type (local or S3)",
    )
    checksum: Optional[str] = Field(
        default=None,
        max_length=64,
        description="SHA-256 checksum of the file content",
    )
    thumbnails: list[ThumbnailResponse] = Field(
        default_factory=list,
        description="List of available thumbnails (for images)",
    )
    download_url: Optional[str] = Field(
        default=None,
        description="URL to download the file",
    )


class FileUploadResponse(BaseSchema):
    """Response schema for file upload operation.

    Returned after a successful file upload.

    Attributes:
        file: The uploaded file details
        message: Success message
    """

    file: FileResponse = Field(
        ...,
        description="The uploaded file details",
    )
    message: str = Field(
        default="File uploaded successfully",
        description="Success message",
    )


class FileListResponse(PaginatedResponse):
    """Paginated list of files.

    Attributes:
        items: List of files
    """

    items: list[FileResponse] = Field(
        ...,
        description="List of files",
    )


class StorageQuotaResponse(BaseSchema):
    """Response schema for storage quota information.

    Provides details about a user's storage quota and usage.

    Attributes:
        id: Unique identifier for the quota record
        owner_id: UUID of the user this quota applies to
        quota_bytes: Maximum allowed storage in bytes
        used_bytes: Current storage usage in bytes
        available_bytes: Available storage space in bytes
        usage_percentage: Storage usage as a percentage
        file_count: Number of files stored
        created_at: Timestamp when the quota record was created
        updated_at: Timestamp when the record was last updated
    """

    id: UUID = Field(
        ...,
        description="Unique identifier for the quota record",
    )
    owner_id: UUID = Field(
        ...,
        description="UUID of the user this quota applies to",
    )
    quota_bytes: int = Field(
        ...,
        ge=0,
        description="Maximum allowed storage in bytes",
    )
    used_bytes: int = Field(
        ...,
        ge=0,
        description="Current storage usage in bytes",
    )
    available_bytes: int = Field(
        ...,
        ge=0,
        description="Available storage space in bytes",
    )
    usage_percentage: float = Field(
        ...,
        ge=0.0,
        le=100.0,
        description="Storage usage as a percentage",
    )
    file_count: int = Field(
        ...,
        ge=0,
        description="Number of files stored",
    )
    created_at: Optional[datetime] = Field(
        default=None,
        description="Timestamp when the quota record was created",
    )
    updated_at: Optional[datetime] = Field(
        default=None,
        description="Timestamp when the record was last updated",
    )


class SecureUrlRequest(BaseSchema):
    """Request schema for generating a secure URL.

    Attributes:
        file_id: UUID of the file to generate URL for
        expires_in_seconds: URL expiration time in seconds
    """

    file_id: UUID = Field(
        ...,
        description="UUID of the file to generate URL for",
    )
    expires_in_seconds: int = Field(
        default=3600,
        ge=60,
        le=86400,
        description="URL expiration time in seconds (1 minute to 24 hours)",
    )


class SecureUrlResponse(BaseSchema):
    """Response schema for secure URL generation.

    Attributes:
        file_id: UUID of the file
        url: The secure URL to access the file
        expires_at: Timestamp when the URL expires
    """

    file_id: UUID = Field(
        ...,
        description="UUID of the file",
    )
    url: str = Field(
        ...,
        description="The secure URL to access the file",
    )
    expires_at: datetime = Field(
        ...,
        description="Timestamp when the URL expires",
    )


class FileDeleteResponse(BaseSchema):
    """Response schema for file deletion.

    Attributes:
        file_id: UUID of the deleted file
        message: Success message
    """

    file_id: UUID = Field(
        ...,
        description="UUID of the deleted file",
    )
    message: str = Field(
        default="File deleted successfully",
        description="Success message",
    )
