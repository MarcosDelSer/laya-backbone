"""Storage service for LAYA AI Service.

Provides business logic for file upload, storage, and management.
Implements local filesystem and S3 backend support with thumbnail generation.
"""

import asyncio
import hashlib
import io
import os
import shutil
from concurrent.futures import ThreadPoolExecutor
from datetime import datetime
from functools import partial
from pathlib import Path
from typing import Optional
from uuid import UUID, uuid4

import boto3
from botocore.config import Config as BotoConfig
from botocore.exceptions import ClientError
from PIL import Image
from sqlalchemy import cast, func, select, String
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy.orm import selectinload

from app.config import settings


# Thumbnail size presets in pixels
THUMBNAIL_SIZE_PIXELS = {
    "small": 64,
    "medium": 128,
    "large": 256,
}

# Image MIME types that support thumbnail generation
IMAGE_MIME_TYPES = frozenset({
    "image/jpeg",
    "image/png",
    "image/gif",
    "image/webp",
})

# Thread pool for running sync image processing operations
_image_executor = ThreadPoolExecutor(max_workers=2, thread_name_prefix="image_")
from app.models.storage import (
    File,
    FileThumbnail,
    StorageBackend,
    StorageQuota,
    ThumbnailSize,
)
from app.schemas.storage import (
    FileResponse,
    FileListResponse,
    StorageQuotaResponse,
    ThumbnailResponse,
)


class StorageServiceError(Exception):
    """Base exception for storage service errors."""

    pass


class QuotaExceededError(StorageServiceError):
    """Raised when storage quota would be exceeded."""

    pass


class FileNotFoundError(StorageServiceError):
    """Raised when a file is not found."""

    pass


class InvalidFileTypeError(StorageServiceError):
    """Raised when file type is not allowed."""

    pass


class FileTooLargeError(StorageServiceError):
    """Raised when file exceeds maximum size."""

    pass


class S3StorageError(StorageServiceError):
    """Raised when S3 storage operation fails."""

    pass


# Thread pool for running sync S3 operations
_s3_executor = ThreadPoolExecutor(max_workers=4, thread_name_prefix="s3_")


class StorageService:
    """Service class for file storage and management logic.

    Encapsulates business logic for uploading, downloading, and managing files
    with support for local filesystem and S3 storage backends.

    Attributes:
        db: Async database session for database operations.
    """

    def __init__(self, db: AsyncSession) -> None:
        """Initialize StorageService with database session.

        Args:
            db: Async database session for database operations.
        """
        self.db = db
        self._local_storage_path = Path(settings.local_storage_path)
        self._max_file_size_bytes = settings.max_file_size_mb * 1024 * 1024
        self._allowed_file_types = set(settings.allowed_file_types.split(","))
        self._storage_backend = StorageBackend(settings.storage_backend)
        self._default_quota_bytes = settings.storage_quota_mb * 1024 * 1024

        # Initialize S3 client if using S3 backend
        self._s3_client = None
        self._s3_bucket_name = settings.s3_bucket_name
        if self._storage_backend == StorageBackend.S3:
            self._init_s3_client()

    def _init_s3_client(self) -> None:
        """Initialize the S3 client with configured credentials.

        Creates a boto3 S3 client using settings from the application config.
        Supports custom endpoints for S3-compatible services like MinIO.

        Raises:
            S3StorageError: If S3 configuration is missing or invalid.
        """
        if not self._s3_bucket_name:
            raise S3StorageError(
                "S3 bucket name is required when using S3 storage backend. "
                "Set the S3_BUCKET_NAME environment variable."
            )

        # Build boto3 config
        boto_config = BotoConfig(
            signature_version="s3v4",
            retries={"max_attempts": 3, "mode": "standard"},
        )

        # Build client arguments
        client_kwargs = {
            "service_name": "s3",
            "region_name": settings.s3_region,
            "config": boto_config,
        }

        # Add credentials if provided
        if settings.s3_access_key_id and settings.s3_secret_access_key:
            client_kwargs["aws_access_key_id"] = settings.s3_access_key_id
            client_kwargs["aws_secret_access_key"] = settings.s3_secret_access_key

        # Add custom endpoint if provided (for S3-compatible services)
        if settings.s3_endpoint_url:
            client_kwargs["endpoint_url"] = settings.s3_endpoint_url

        self._s3_client = boto3.client(**client_kwargs)

    async def upload_file(
        self,
        owner_id: UUID,
        file_content: bytes,
        original_filename: str,
        content_type: str,
        description: Optional[str] = None,
        is_public: bool = False,
    ) -> File:
        """Upload a file to storage.

        Validates file type and size, checks quota, stores the file,
        and creates the database record.

        Args:
            owner_id: UUID of the user uploading the file.
            file_content: The raw file content as bytes.
            original_filename: Original filename from the upload.
            content_type: MIME type of the file.
            description: Optional description of the file.
            is_public: Whether the file should be publicly accessible.

        Returns:
            The created File record.

        Raises:
            InvalidFileTypeError: If the file type is not allowed.
            FileTooLargeError: If the file exceeds the maximum size.
            QuotaExceededError: If the upload would exceed the user's quota.
        """
        # Validate file type
        if content_type not in self._allowed_file_types:
            raise InvalidFileTypeError(
                f"File type '{content_type}' is not allowed. "
                f"Allowed types: {', '.join(sorted(self._allowed_file_types))}"
            )

        # Validate file size
        file_size = len(file_content)
        if file_size > self._max_file_size_bytes:
            raise FileTooLargeError(
                f"File size ({file_size} bytes) exceeds maximum "
                f"allowed size ({self._max_file_size_bytes} bytes)"
            )

        # Check quota
        quota = await self.get_or_create_quota(owner_id)
        if quota.used_bytes + file_size > quota.quota_bytes:
            raise QuotaExceededError(
                f"Upload would exceed storage quota. "
                f"Available: {quota.available_bytes} bytes, "
                f"Required: {file_size} bytes"
            )

        # Generate unique filename and calculate checksum
        file_id = uuid4()
        file_extension = self._get_file_extension(original_filename)
        stored_filename = f"{file_id}{file_extension}"
        checksum = self._calculate_checksum(file_content)

        # Store file based on backend
        if self._storage_backend == StorageBackend.LOCAL:
            storage_path = await self._upload_to_local(
                file_content, stored_filename, owner_id
            )
        else:
            storage_path = await self._upload_to_s3(
                file_content, stored_filename, owner_id, content_type
            )

        # Create file record
        file_record = File(
            id=file_id,
            owner_id=owner_id,
            filename=stored_filename,
            original_filename=original_filename,
            content_type=content_type,
            size_bytes=file_size,
            storage_backend=self._storage_backend,
            storage_path=storage_path,
            checksum=checksum,
            is_public=is_public,
            description=description,
        )
        self.db.add(file_record)

        # Update quota
        quota.used_bytes += file_size
        quota.file_count += 1

        await self.db.commit()
        await self.db.refresh(file_record)

        return file_record

    async def download_file(
        self,
        file_id: UUID,
        owner_id: Optional[UUID] = None,
    ) -> tuple[bytes, File]:
        """Download a file from storage.

        Retrieves the file content and metadata from storage.

        Args:
            file_id: UUID of the file to download.
            owner_id: Optional owner ID for access control.
                     If provided, only returns if owner matches or file is public.

        Returns:
            Tuple of (file_content, file_record).

        Raises:
            FileNotFoundError: If the file does not exist or is not accessible.
        """
        file_record = await self.get_file_by_id(file_id)

        if file_record is None:
            raise FileNotFoundError(f"File with ID {file_id} not found")

        # Check access permissions
        if owner_id is not None and not file_record.is_public:
            if file_record.owner_id != owner_id:
                raise FileNotFoundError(f"File with ID {file_id} not found")

        # Retrieve file content based on backend
        if file_record.storage_backend == StorageBackend.LOCAL:
            file_content = await self._download_from_local(file_record.storage_path)
        else:
            file_content = await self._download_from_s3(file_record.storage_path)

        return file_content, file_record

    async def delete_file(
        self,
        file_id: UUID,
        owner_id: UUID,
    ) -> bool:
        """Delete a file from storage.

        Removes the file from storage and deletes the database record.

        Args:
            file_id: UUID of the file to delete.
            owner_id: UUID of the owner requesting deletion.

        Returns:
            True if the file was deleted successfully.

        Raises:
            FileNotFoundError: If the file does not exist or is not owned by user.
        """
        file_record = await self.get_file_by_id(file_id)

        if file_record is None:
            raise FileNotFoundError(f"File with ID {file_id} not found")

        if file_record.owner_id != owner_id:
            raise FileNotFoundError(f"File with ID {file_id} not found")

        file_size = file_record.size_bytes

        # Delete file from storage
        if file_record.storage_backend == StorageBackend.LOCAL:
            await self._delete_from_local(file_record.storage_path)
            # Delete thumbnails if they exist
            for thumbnail in file_record.thumbnails:
                await self._delete_from_local(thumbnail.storage_path)
        else:
            await self._delete_from_s3(file_record.storage_path)
            # Delete thumbnails if they exist
            for thumbnail in file_record.thumbnails:
                await self._delete_from_s3(thumbnail.storage_path)

        # Delete database record (thumbnails deleted via cascade)
        await self.db.delete(file_record)

        # Update quota
        quota = await self.get_quota(owner_id)
        if quota:
            quota.used_bytes = max(0, quota.used_bytes - file_size)
            quota.file_count = max(0, quota.file_count - 1)

        await self.db.commit()

        return True

    async def list_files(
        self,
        owner_id: UUID,
        skip: int = 0,
        limit: int = 100,
        content_type: Optional[str] = None,
        is_public: Optional[bool] = None,
    ) -> tuple[list[File], int]:
        """List files owned by a user with optional filtering.

        Args:
            owner_id: UUID of the file owner.
            skip: Number of records to skip for pagination.
            limit: Maximum number of records to return.
            content_type: Optional filter by MIME type prefix (e.g., 'image/').
            is_public: Optional filter by public visibility.

        Returns:
            Tuple of (list of File records, total count).
        """
        # Build base query
        query = (
            select(File)
            .where(cast(File.owner_id, String) == str(owner_id))
            .options(selectinload(File.thumbnails))
        )

        # Apply filters
        if content_type is not None:
            if content_type.endswith("/"):
                # Filter by MIME type prefix (e.g., 'image/')
                query = query.where(File.content_type.startswith(content_type))
            else:
                query = query.where(File.content_type == content_type)

        if is_public is not None:
            query = query.where(File.is_public == is_public)

        # Get total count
        count_query = select(func.count()).select_from(query.subquery())
        count_result = await self.db.execute(count_query)
        total = count_result.scalar() or 0

        # Apply pagination and ordering
        query = query.offset(skip).limit(limit).order_by(File.created_at.desc())

        result = await self.db.execute(query)
        files = list(result.scalars().all())

        return files, total

    async def get_file_by_id(self, file_id: UUID) -> Optional[File]:
        """Retrieve a single file by ID.

        Args:
            file_id: Unique identifier of the file.

        Returns:
            File if found, None otherwise.
        """
        query = (
            select(File)
            .where(cast(File.id, String) == str(file_id))
            .options(selectinload(File.thumbnails))
        )
        result = await self.db.execute(query)
        return result.scalar_one_or_none()

    async def get_quota(self, owner_id: UUID) -> Optional[StorageQuota]:
        """Get storage quota for a user.

        Args:
            owner_id: UUID of the user.

        Returns:
            StorageQuota if found, None otherwise.
        """
        query = select(StorageQuota).where(
            cast(StorageQuota.owner_id, String) == str(owner_id)
        )
        result = await self.db.execute(query)
        return result.scalar_one_or_none()

    async def get_or_create_quota(self, owner_id: UUID) -> StorageQuota:
        """Get or create storage quota for a user.

        If no quota record exists, creates one with default settings.

        Args:
            owner_id: UUID of the user.

        Returns:
            StorageQuota record.
        """
        quota = await self.get_quota(owner_id)

        if quota is None:
            quota = StorageQuota(
                owner_id=owner_id,
                quota_bytes=self._default_quota_bytes,
                used_bytes=0,
                file_count=0,
            )
            self.db.add(quota)
            await self.db.commit()
            await self.db.refresh(quota)

        return quota

    async def update_file_metadata(
        self,
        file_id: UUID,
        owner_id: UUID,
        description: Optional[str] = None,
        is_public: Optional[bool] = None,
    ) -> File:
        """Update file metadata.

        Args:
            file_id: UUID of the file to update.
            owner_id: UUID of the owner.
            description: New description (if provided).
            is_public: New public visibility setting (if provided).

        Returns:
            Updated File record.

        Raises:
            FileNotFoundError: If the file does not exist or is not owned by user.
        """
        file_record = await self.get_file_by_id(file_id)

        if file_record is None:
            raise FileNotFoundError(f"File with ID {file_id} not found")

        if file_record.owner_id != owner_id:
            raise FileNotFoundError(f"File with ID {file_id} not found")

        if description is not None:
            file_record.description = description

        if is_public is not None:
            file_record.is_public = is_public

        await self.db.commit()
        await self.db.refresh(file_record)

        return file_record

    async def generate_thumbnail(
        self,
        file_id: UUID,
        size: ThumbnailSize,
        owner_id: Optional[UUID] = None,
    ) -> FileThumbnail:
        """Generate a thumbnail for an image file.

        Creates a resized version of the image and stores it alongside
        the original file. Thumbnails maintain aspect ratio.

        Args:
            file_id: UUID of the file to generate thumbnail for.
            size: Thumbnail size preset (small, medium, large).
            owner_id: Optional owner ID for access control.

        Returns:
            The created FileThumbnail record.

        Raises:
            FileNotFoundError: If the file does not exist or is not accessible.
            InvalidFileTypeError: If the file is not an image.
            StorageServiceError: If thumbnail generation fails.
        """
        # Get the file record
        file_record = await self.get_file_by_id(file_id)

        if file_record is None:
            raise FileNotFoundError(f"File with ID {file_id} not found")

        # Check access permissions
        if owner_id is not None and not file_record.is_public:
            if file_record.owner_id != owner_id:
                raise FileNotFoundError(f"File with ID {file_id} not found")

        # Verify file is an image
        if file_record.content_type not in IMAGE_MIME_TYPES:
            raise InvalidFileTypeError(
                f"Cannot generate thumbnail for non-image file type: "
                f"{file_record.content_type}"
            )

        # Check if thumbnail already exists for this size
        for thumb in file_record.thumbnails:
            if thumb.size == size:
                return thumb

        # Get thumbnail dimensions
        target_size = THUMBNAIL_SIZE_PIXELS.get(size.value, 128)

        # Download the original file
        if file_record.storage_backend == StorageBackend.LOCAL:
            file_content = await self._download_from_local(file_record.storage_path)
        else:
            file_content = await self._download_from_s3(file_record.storage_path)

        # Generate thumbnail in thread pool (PIL is synchronous)
        loop = asyncio.get_event_loop()
        thumbnail_content, width, height = await loop.run_in_executor(
            _image_executor,
            partial(
                self._create_thumbnail_sync,
                file_content,
                target_size,
                file_record.content_type,
            ),
        )

        # Generate thumbnail filename and path
        base_filename = file_record.filename.rsplit(".", 1)[0]
        thumb_extension = self._get_thumbnail_extension(file_record.content_type)
        thumb_filename = f"{base_filename}_thumb_{size.value}{thumb_extension}"

        # Store thumbnail
        if self._storage_backend == StorageBackend.LOCAL:
            thumb_storage_path = await self._upload_to_local(
                thumbnail_content, thumb_filename, file_record.owner_id
            )
        else:
            thumb_storage_path = await self._upload_to_s3(
                thumbnail_content,
                thumb_filename,
                file_record.owner_id,
                file_record.content_type,
            )

        # Create thumbnail record
        thumbnail = FileThumbnail(
            file_id=file_id,
            size=size,
            width=width,
            height=height,
            storage_path=thumb_storage_path,
        )
        self.db.add(thumbnail)
        await self.db.commit()
        await self.db.refresh(thumbnail)

        return thumbnail

    async def generate_all_thumbnails(
        self,
        file_id: UUID,
        owner_id: Optional[UUID] = None,
    ) -> list[FileThumbnail]:
        """Generate all thumbnail sizes for an image file.

        Convenience method to generate small, medium, and large thumbnails
        for a single image file.

        Args:
            file_id: UUID of the file to generate thumbnails for.
            owner_id: Optional owner ID for access control.

        Returns:
            List of created FileThumbnail records.

        Raises:
            FileNotFoundError: If the file does not exist or is not accessible.
            InvalidFileTypeError: If the file is not an image.
        """
        thumbnails = []
        for size in ThumbnailSize:
            thumb = await self.generate_thumbnail(file_id, size, owner_id)
            thumbnails.append(thumb)
        return thumbnails

    def is_image_file(self, content_type: str) -> bool:
        """Check if a content type is a supported image type.

        Args:
            content_type: MIME type to check.

        Returns:
            True if the content type supports thumbnail generation.
        """
        return content_type in IMAGE_MIME_TYPES

    def _create_thumbnail_sync(
        self,
        image_content: bytes,
        max_size: int,
        content_type: str,
    ) -> tuple[bytes, int, int]:
        """Create a thumbnail from image content (synchronous).

        This method runs in a thread pool to avoid blocking the event loop.

        Args:
            image_content: Original image as bytes.
            max_size: Maximum dimension (width or height) for thumbnail.
            content_type: MIME type of the original image.

        Returns:
            Tuple of (thumbnail_bytes, width, height).

        Raises:
            StorageServiceError: If image processing fails.
        """
        try:
            # Open image from bytes
            with Image.open(io.BytesIO(image_content)) as img:
                # Convert RGBA to RGB for JPEG output if needed
                if img.mode in ("RGBA", "P") and content_type == "image/jpeg":
                    img = img.convert("RGB")

                # Calculate new dimensions maintaining aspect ratio
                original_width, original_height = img.size
                ratio = min(max_size / original_width, max_size / original_height)
                new_width = int(original_width * ratio)
                new_height = int(original_height * ratio)

                # Resize using high-quality resampling
                thumbnail = img.resize((new_width, new_height), Image.Resampling.LANCZOS)

                # Save to bytes
                output = io.BytesIO()
                format_map = {
                    "image/jpeg": "JPEG",
                    "image/png": "PNG",
                    "image/gif": "GIF",
                    "image/webp": "WEBP",
                }
                img_format = format_map.get(content_type, "PNG")

                # Set quality for lossy formats
                save_kwargs = {}
                if img_format in ("JPEG", "WEBP"):
                    save_kwargs["quality"] = 85
                    save_kwargs["optimize"] = True
                elif img_format == "PNG":
                    save_kwargs["optimize"] = True

                thumbnail.save(output, format=img_format, **save_kwargs)
                thumbnail_bytes = output.getvalue()

                return thumbnail_bytes, new_width, new_height

        except Exception as e:
            raise StorageServiceError(
                f"Failed to generate thumbnail: {str(e)}"
            ) from e

    def _get_thumbnail_extension(self, content_type: str) -> str:
        """Get file extension for a thumbnail based on content type.

        Args:
            content_type: MIME type of the image.

        Returns:
            File extension including the dot (e.g., '.jpg').
        """
        extension_map = {
            "image/jpeg": ".jpg",
            "image/png": ".png",
            "image/gif": ".gif",
            "image/webp": ".webp",
        }
        return extension_map.get(content_type, ".png")

    def file_to_response(
        self,
        file: File,
        include_download_url: bool = False,
        download_url: Optional[str] = None,
    ) -> FileResponse:
        """Convert File model to FileResponse schema.

        Args:
            file: The File model instance.
            include_download_url: Whether to include download URL.
            download_url: Pre-generated download URL (optional).

        Returns:
            FileResponse schema instance.
        """
        thumbnails = [
            ThumbnailResponse(
                id=thumb.id,
                file_id=thumb.file_id,
                size=thumb.size,
                width=thumb.width,
                height=thumb.height,
                created_at=thumb.created_at,
            )
            for thumb in file.thumbnails
        ]

        return FileResponse(
            id=file.id,
            owner_id=file.owner_id,
            filename=file.filename,
            original_filename=file.original_filename,
            content_type=file.content_type,
            size_bytes=file.size_bytes,
            storage_backend=file.storage_backend,
            checksum=file.checksum,
            description=file.description,
            is_public=file.is_public,
            thumbnails=thumbnails,
            download_url=download_url if include_download_url else None,
            created_at=file.created_at,
            updated_at=file.updated_at,
        )

    def quota_to_response(self, quota: StorageQuota) -> StorageQuotaResponse:
        """Convert StorageQuota model to StorageQuotaResponse schema.

        Args:
            quota: The StorageQuota model instance.

        Returns:
            StorageQuotaResponse schema instance.
        """
        return StorageQuotaResponse(
            id=quota.id,
            owner_id=quota.owner_id,
            quota_bytes=quota.quota_bytes,
            used_bytes=quota.used_bytes,
            available_bytes=quota.available_bytes,
            usage_percentage=round(quota.usage_percentage, 2),
            file_count=quota.file_count,
            created_at=quota.created_at,
            updated_at=quota.updated_at,
        )

    # Private helper methods

    def _get_file_extension(self, filename: str) -> str:
        """Extract file extension from filename.

        Args:
            filename: The original filename.

        Returns:
            File extension including the dot (e.g., '.jpg'), or empty string.
        """
        if "." in filename:
            return "." + filename.rsplit(".", 1)[1].lower()
        return ""

    def _calculate_checksum(self, content: bytes) -> str:
        """Calculate SHA-256 checksum of file content.

        Args:
            content: The file content as bytes.

        Returns:
            Hexadecimal SHA-256 hash string.
        """
        return hashlib.sha256(content).hexdigest()

    async def _upload_to_local(
        self,
        content: bytes,
        filename: str,
        owner_id: UUID,
    ) -> str:
        """Upload file to local filesystem storage.

        Creates owner-specific subdirectory and stores the file.

        Args:
            content: File content as bytes.
            filename: Stored filename.
            owner_id: UUID of the owner for directory organization.

        Returns:
            Relative storage path for the file.
        """
        # Create owner-specific directory
        owner_dir = self._local_storage_path / str(owner_id)
        owner_dir.mkdir(parents=True, exist_ok=True)

        # Write file
        file_path = owner_dir / filename
        file_path.write_bytes(content)

        # Return relative path
        return str(Path(str(owner_id)) / filename)

    async def _download_from_local(self, storage_path: str) -> bytes:
        """Download file from local filesystem storage.

        Args:
            storage_path: Relative path to the file.

        Returns:
            File content as bytes.

        Raises:
            FileNotFoundError: If the file does not exist on disk.
        """
        file_path = self._local_storage_path / storage_path

        if not file_path.exists():
            raise FileNotFoundError(f"File not found at path: {storage_path}")

        return file_path.read_bytes()

    async def _delete_from_local(self, storage_path: str) -> bool:
        """Delete file from local filesystem storage.

        Args:
            storage_path: Relative path to the file.

        Returns:
            True if deletion was successful, False if file didn't exist.
        """
        file_path = self._local_storage_path / storage_path

        if file_path.exists():
            file_path.unlink()
            return True

        return False

    def _get_local_file_path(self, storage_path: str) -> Path:
        """Get absolute path to a locally stored file.

        Args:
            storage_path: Relative storage path.

        Returns:
            Absolute Path object.
        """
        return self._local_storage_path / storage_path

    # S3 storage methods

    async def _upload_to_s3(
        self,
        content: bytes,
        filename: str,
        owner_id: UUID,
        content_type: str,
    ) -> str:
        """Upload file to S3 storage.

        Creates an owner-prefixed key structure and stores the file in S3.

        Args:
            content: File content as bytes.
            filename: Stored filename.
            owner_id: UUID of the owner for key organization.
            content_type: MIME type of the file.

        Returns:
            S3 key path for the file.

        Raises:
            S3StorageError: If the upload fails.
        """
        if self._s3_client is None:
            raise S3StorageError("S3 client not initialized")

        # Create S3 key with owner prefix
        s3_key = f"{owner_id}/{filename}"

        # Run S3 upload in thread pool
        loop = asyncio.get_event_loop()
        try:
            await loop.run_in_executor(
                _s3_executor,
                partial(
                    self._s3_client.put_object,
                    Bucket=self._s3_bucket_name,
                    Key=s3_key,
                    Body=content,
                    ContentType=content_type,
                ),
            )
        except ClientError as e:
            error_code = e.response.get("Error", {}).get("Code", "Unknown")
            raise S3StorageError(
                f"Failed to upload file to S3: {error_code} - {str(e)}"
            ) from e

        return s3_key

    async def _download_from_s3(self, storage_path: str) -> bytes:
        """Download file from S3 storage.

        Args:
            storage_path: S3 key of the file.

        Returns:
            File content as bytes.

        Raises:
            FileNotFoundError: If the file does not exist in S3.
            S3StorageError: If the download fails.
        """
        if self._s3_client is None:
            raise S3StorageError("S3 client not initialized")

        loop = asyncio.get_event_loop()
        try:
            response = await loop.run_in_executor(
                _s3_executor,
                partial(
                    self._s3_client.get_object,
                    Bucket=self._s3_bucket_name,
                    Key=storage_path,
                ),
            )
            # Read the body content
            body = response["Body"]
            content = await loop.run_in_executor(
                _s3_executor,
                body.read,
            )
            return content
        except ClientError as e:
            error_code = e.response.get("Error", {}).get("Code", "Unknown")
            if error_code in ("NoSuchKey", "404"):
                raise FileNotFoundError(
                    f"File not found in S3: {storage_path}"
                ) from e
            raise S3StorageError(
                f"Failed to download file from S3: {error_code} - {str(e)}"
            ) from e

    async def _delete_from_s3(self, storage_path: str) -> bool:
        """Delete file from S3 storage.

        Args:
            storage_path: S3 key of the file.

        Returns:
            True if deletion was successful.

        Raises:
            S3StorageError: If the deletion fails.
        """
        if self._s3_client is None:
            raise S3StorageError("S3 client not initialized")

        loop = asyncio.get_event_loop()
        try:
            await loop.run_in_executor(
                _s3_executor,
                partial(
                    self._s3_client.delete_object,
                    Bucket=self._s3_bucket_name,
                    Key=storage_path,
                ),
            )
            return True
        except ClientError as e:
            error_code = e.response.get("Error", {}).get("Code", "Unknown")
            raise S3StorageError(
                f"Failed to delete file from S3: {error_code} - {str(e)}"
            ) from e

    def _get_s3_key(self, owner_id: UUID, filename: str) -> str:
        """Generate S3 key for a file.

        Args:
            owner_id: UUID of the owner.
            filename: Stored filename.

        Returns:
            S3 key string.
        """
        return f"{owner_id}/{filename}"
