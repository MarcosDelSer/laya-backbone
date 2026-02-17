"""Unit tests for Storage models, service, and API endpoints.

Tests cover:
- File model creation and validation
- FileThumbnail model creation
- StorageQuota model and computed properties
- Storage service upload, download, and delete operations
- Quota management and enforcement
- Secure URL generation and verification
- API endpoint response structure
- Authentication requirements on protected endpoints
- Edge cases: invalid file types, quota exceeded, file not found
"""

from datetime import datetime, timedelta, timezone
from uuid import uuid4

import pytest
from httpx import AsyncClient
from sqlalchemy.ext.asyncio import AsyncSession

from tests.conftest import (
    MockFile,
    MockFileThumbnail,
    MockStorageQuota,
    create_file_in_db,
    create_file_thumbnail_in_db,
    create_storage_quota_in_db,
)


# =============================================================================
# Model Tests
# =============================================================================


class TestFileModel:
    """Tests for the File model (using mock fixtures for SQLite compatibility)."""

    @pytest.mark.asyncio
    async def test_create_file_with_all_fields(
        self,
        sample_file: MockFile,
    ):
        """Test File can be created with all fields."""
        assert sample_file.id is not None
        assert sample_file.owner_id is not None
        assert sample_file.filename is not None
        assert sample_file.original_filename == "test_image.jpg"
        assert sample_file.content_type == "image/jpeg"
        assert sample_file.size_bytes == 1024 * 100  # 100 KB
        assert sample_file.storage_backend == "local"
        assert sample_file.storage_path is not None
        assert sample_file.is_public is False
        assert sample_file.description == "A test image file"
        assert sample_file.created_at is not None
        assert sample_file.updated_at is not None

    @pytest.mark.asyncio
    async def test_file_repr(
        self,
        sample_file: MockFile,
    ):
        """Test File string representation."""
        repr_str = repr(sample_file)
        assert "File" in repr_str
        assert str(sample_file.id) in repr_str
        assert sample_file.original_filename in repr_str

    @pytest.mark.asyncio
    async def test_create_public_file(
        self,
        sample_public_file: MockFile,
    ):
        """Test File can be created with public visibility."""
        assert sample_public_file.is_public is True
        assert sample_public_file.content_type == "application/pdf"
        assert sample_public_file.storage_backend == "s3"

    @pytest.mark.asyncio
    async def test_file_with_thumbnails(
        self,
        sample_file_with_thumbnails: MockFile,
    ):
        """Test File can have associated thumbnails."""
        assert len(sample_file_with_thumbnails.thumbnails) == 3
        sizes = {thumb.size for thumb in sample_file_with_thumbnails.thumbnails}
        assert sizes == {"small", "medium", "large"}


class TestFileThumbnailModel:
    """Tests for the FileThumbnail model."""

    @pytest.mark.asyncio
    async def test_create_thumbnail(
        self,
        db_session: AsyncSession,
        sample_file: MockFile,
    ):
        """Test FileThumbnail can be created."""
        thumbnail = await create_file_thumbnail_in_db(
            db_session,
            file_id=sample_file.id,
            size="medium",
            width=300,
            height=300,
        )

        assert thumbnail.id is not None
        assert thumbnail.file_id == sample_file.id
        assert thumbnail.size == "medium"
        assert thumbnail.width == 300
        assert thumbnail.height == 300
        assert thumbnail.storage_path is not None
        assert thumbnail.created_at is not None

    @pytest.mark.asyncio
    async def test_thumbnail_repr(
        self,
        db_session: AsyncSession,
        sample_file: MockFile,
    ):
        """Test FileThumbnail string representation."""
        thumbnail = await create_file_thumbnail_in_db(
            db_session,
            file_id=sample_file.id,
            size="small",
            width=100,
            height=100,
        )

        repr_str = repr(thumbnail)
        assert "FileThumbnail" in repr_str
        assert str(thumbnail.id) in repr_str
        assert "small" in repr_str
        assert "100x100" in repr_str


class TestStorageQuotaModel:
    """Tests for the StorageQuota model."""

    @pytest.mark.asyncio
    async def test_create_storage_quota(
        self,
        sample_storage_quota: MockStorageQuota,
    ):
        """Test StorageQuota can be created."""
        assert sample_storage_quota.id is not None
        assert sample_storage_quota.owner_id is not None
        assert sample_storage_quota.quota_bytes == 104857600  # 100 MB
        assert sample_storage_quota.used_bytes == 10485760  # 10 MB
        assert sample_storage_quota.file_count == 5
        assert sample_storage_quota.created_at is not None
        assert sample_storage_quota.updated_at is not None

    @pytest.mark.asyncio
    async def test_storage_quota_available_bytes(
        self,
        sample_storage_quota: MockStorageQuota,
    ):
        """Test StorageQuota available_bytes calculation."""
        expected_available = 104857600 - 10485760  # 90 MB
        assert sample_storage_quota.available_bytes == expected_available

    @pytest.mark.asyncio
    async def test_storage_quota_usage_percentage(
        self,
        sample_storage_quota: MockStorageQuota,
    ):
        """Test StorageQuota usage_percentage calculation."""
        expected_percentage = (10485760 / 104857600) * 100  # ~10%
        assert abs(sample_storage_quota.usage_percentage - expected_percentage) < 0.01

    @pytest.mark.asyncio
    async def test_storage_quota_near_limit(
        self,
        sample_storage_quota_near_limit: MockStorageQuota,
    ):
        """Test StorageQuota near limit calculations."""
        assert sample_storage_quota_near_limit.usage_percentage > 90.0
        assert sample_storage_quota_near_limit.available_bytes < 500000  # < 500 KB

    @pytest.mark.asyncio
    async def test_empty_storage_quota(
        self,
        sample_empty_storage_quota: MockStorageQuota,
    ):
        """Test StorageQuota with no files."""
        assert sample_empty_storage_quota.used_bytes == 0
        assert sample_empty_storage_quota.file_count == 0
        assert sample_empty_storage_quota.usage_percentage == 0.0
        assert sample_empty_storage_quota.available_bytes == sample_empty_storage_quota.quota_bytes

    @pytest.mark.asyncio
    async def test_storage_quota_repr(
        self,
        sample_storage_quota: MockStorageQuota,
    ):
        """Test StorageQuota string representation."""
        repr_str = repr(sample_storage_quota)
        assert "StorageQuota" in repr_str
        assert str(sample_storage_quota.id) in repr_str


# =============================================================================
# Service Tests
# =============================================================================


class TestStorageServiceHelpers:
    """Tests for StorageService helper methods."""

    @pytest.mark.asyncio
    async def test_is_image_file_jpeg(
        self,
        db_session: AsyncSession,
    ):
        """Test is_image_file returns True for JPEG."""
        from app.services.storage_service import StorageService

        service = StorageService(db_session)
        assert service.is_image_file("image/jpeg") is True

    @pytest.mark.asyncio
    async def test_is_image_file_png(
        self,
        db_session: AsyncSession,
    ):
        """Test is_image_file returns True for PNG."""
        from app.services.storage_service import StorageService

        service = StorageService(db_session)
        assert service.is_image_file("image/png") is True

    @pytest.mark.asyncio
    async def test_is_image_file_gif(
        self,
        db_session: AsyncSession,
    ):
        """Test is_image_file returns True for GIF."""
        from app.services.storage_service import StorageService

        service = StorageService(db_session)
        assert service.is_image_file("image/gif") is True

    @pytest.mark.asyncio
    async def test_is_image_file_webp(
        self,
        db_session: AsyncSession,
    ):
        """Test is_image_file returns True for WebP."""
        from app.services.storage_service import StorageService

        service = StorageService(db_session)
        assert service.is_image_file("image/webp") is True

    @pytest.mark.asyncio
    async def test_is_image_file_pdf_false(
        self,
        db_session: AsyncSession,
    ):
        """Test is_image_file returns False for PDF."""
        from app.services.storage_service import StorageService

        service = StorageService(db_session)
        assert service.is_image_file("application/pdf") is False

    @pytest.mark.asyncio
    async def test_is_image_file_text_false(
        self,
        db_session: AsyncSession,
    ):
        """Test is_image_file returns False for text."""
        from app.services.storage_service import StorageService

        service = StorageService(db_session)
        assert service.is_image_file("text/plain") is False


class TestStorageServiceQuota:
    """Tests for StorageService quota management."""

    @pytest.mark.asyncio
    async def test_get_or_create_quota_creates_new(
        self,
        db_session: AsyncSession,
    ):
        """Test get_or_create_quota creates a new quota record."""
        from app.services.storage_service import StorageService

        service = StorageService(db_session)
        new_owner_id = uuid4()

        quota = await service.get_or_create_quota(new_owner_id)

        assert quota is not None
        assert quota.owner_id == new_owner_id
        assert quota.used_bytes == 0
        assert quota.file_count == 0

    @pytest.mark.asyncio
    async def test_get_or_create_quota_returns_existing(
        self,
        db_session: AsyncSession,
        sample_storage_quota: MockStorageQuota,
    ):
        """Test get_or_create_quota returns existing quota."""
        from app.services.storage_service import StorageService

        service = StorageService(db_session)

        quota = await service.get_or_create_quota(sample_storage_quota.owner_id)

        assert quota is not None
        assert quota.owner_id == sample_storage_quota.owner_id

    @pytest.mark.asyncio
    async def test_check_quota_returns_true_when_available(
        self,
        db_session: AsyncSession,
        sample_storage_quota: MockStorageQuota,
    ):
        """Test check_quota returns True when space is available."""
        from app.services.storage_service import StorageService

        service = StorageService(db_session)

        # Request less than available
        result = await service.check_quota(
            sample_storage_quota.owner_id,
            required_bytes=1024 * 1024,  # 1 MB
            raise_on_exceeded=False,
        )

        assert result is True

    @pytest.mark.asyncio
    async def test_check_quota_returns_false_when_exceeded(
        self,
        db_session: AsyncSession,
        sample_storage_quota_near_limit: MockStorageQuota,
    ):
        """Test check_quota returns False when quota would be exceeded."""
        from app.services.storage_service import StorageService

        service = StorageService(db_session)

        # Request more than available
        result = await service.check_quota(
            sample_storage_quota_near_limit.owner_id,
            required_bytes=10 * 1024 * 1024,  # 10 MB
            raise_on_exceeded=False,
        )

        assert result is False

    @pytest.mark.asyncio
    async def test_check_quota_raises_when_exceeded(
        self,
        db_session: AsyncSession,
        sample_storage_quota_near_limit: MockStorageQuota,
    ):
        """Test check_quota raises QuotaExceededError when quota exceeded."""
        from app.services.storage_service import QuotaExceededError, StorageService

        service = StorageService(db_session)

        with pytest.raises(QuotaExceededError):
            await service.check_quota(
                sample_storage_quota_near_limit.owner_id,
                required_bytes=10 * 1024 * 1024,  # 10 MB
                raise_on_exceeded=True,
            )

    @pytest.mark.asyncio
    async def test_get_quota_usage_returns_stats(
        self,
        db_session: AsyncSession,
        sample_storage_quota: MockStorageQuota,
    ):
        """Test get_quota_usage returns detailed statistics."""
        from app.services.storage_service import StorageService

        service = StorageService(db_session)

        stats = await service.get_quota_usage(sample_storage_quota.owner_id)

        assert "quota_bytes" in stats
        assert "used_bytes" in stats
        assert "available_bytes" in stats
        assert "usage_percentage" in stats
        assert "file_count" in stats
        assert "can_upload" in stats
        assert stats["can_upload"] is True

    @pytest.mark.asyncio
    async def test_update_quota_changes_limit(
        self,
        db_session: AsyncSession,
    ):
        """Test update_quota changes the quota limit."""
        from app.services.storage_service import StorageService

        service = StorageService(db_session)
        owner_id = uuid4()
        new_quota = 200 * 1024 * 1024  # 200 MB

        # update_quota internally calls get_or_create_quota, so no need to call it first
        updated = await service.update_quota(
            owner_id,
            new_quota_bytes=new_quota,
        )

        assert updated.quota_bytes == new_quota
        assert updated.owner_id == owner_id

    @pytest.mark.asyncio
    async def test_update_quota_rejects_negative(
        self,
        db_session: AsyncSession,
    ):
        """Test update_quota rejects negative quota."""
        from app.services.storage_service import StorageService

        service = StorageService(db_session)
        owner_id = uuid4()

        # ValueError is raised before any database operations
        with pytest.raises(ValueError):
            await service.update_quota(
                owner_id,
                new_quota_bytes=-1,
            )


class TestStorageServiceSignedUrls:
    """Tests for StorageService secure URL methods."""

    @pytest.mark.asyncio
    async def test_verify_local_signed_url_valid(
        self,
        db_session: AsyncSession,
    ):
        """Test verify_local_signed_url returns True for valid signature."""
        import base64
        import hashlib
        import hmac

        from app.config import settings
        from app.services.storage_service import StorageService

        service = StorageService(db_session)
        file_id = uuid4()
        expires_at = datetime.now(timezone.utc) + timedelta(hours=1)
        expires_timestamp = int(expires_at.timestamp())

        # Create valid signature
        message = f"{file_id}:{expires_timestamp}"
        signature = hmac.new(
            settings.jwt_secret_key.encode("utf-8"),
            message.encode("utf-8"),
            hashlib.sha256,
        ).digest()
        signature_b64 = base64.urlsafe_b64encode(signature).decode("utf-8").rstrip("=")

        result = service.verify_local_signed_url(file_id, expires_timestamp, signature_b64)

        assert result is True

    @pytest.mark.asyncio
    async def test_verify_local_signed_url_expired(
        self,
        db_session: AsyncSession,
    ):
        """Test verify_local_signed_url raises SignedUrlExpiredError for expired URL."""
        import base64
        import hashlib
        import hmac

        from app.config import settings
        from app.services.storage_service import SignedUrlExpiredError, StorageService

        service = StorageService(db_session)
        file_id = uuid4()
        # Expired 1 hour ago
        expires_at = datetime.now(timezone.utc) - timedelta(hours=1)
        expires_timestamp = int(expires_at.timestamp())

        # Create valid signature (but expired)
        message = f"{file_id}:{expires_timestamp}"
        signature = hmac.new(
            settings.jwt_secret_key.encode("utf-8"),
            message.encode("utf-8"),
            hashlib.sha256,
        ).digest()
        signature_b64 = base64.urlsafe_b64encode(signature).decode("utf-8").rstrip("=")

        with pytest.raises(SignedUrlExpiredError):
            service.verify_local_signed_url(file_id, expires_timestamp, signature_b64)

    @pytest.mark.asyncio
    async def test_verify_local_signed_url_invalid_signature(
        self,
        db_session: AsyncSession,
    ):
        """Test verify_local_signed_url raises SignedUrlInvalidError for invalid signature."""
        from app.services.storage_service import SignedUrlInvalidError, StorageService

        service = StorageService(db_session)
        file_id = uuid4()
        expires_at = datetime.now(timezone.utc) + timedelta(hours=1)
        expires_timestamp = int(expires_at.timestamp())

        # Invalid signature
        invalid_signature = "invalid_signature_string"

        with pytest.raises(SignedUrlInvalidError):
            service.verify_local_signed_url(file_id, expires_timestamp, invalid_signature)

    @pytest.mark.asyncio
    async def test_generate_secure_url_invalid_expiration(
        self,
        db_session: AsyncSession,
        sample_file: MockFile,
        test_user_id,
    ):
        """Test generate_secure_url rejects invalid expiration time."""
        from app.services.storage_service import StorageService

        service = StorageService(db_session)

        # Too short (< 60 seconds)
        with pytest.raises(ValueError):
            await service.generate_secure_url(
                file_id=sample_file.id,
                owner_id=test_user_id,
                expires_in_seconds=30,
            )

        # Too long (> 86400 seconds / 24 hours)
        with pytest.raises(ValueError):
            await service.generate_secure_url(
                file_id=sample_file.id,
                owner_id=test_user_id,
                expires_in_seconds=100000,
            )


class TestStorageServiceConversion:
    """Tests for StorageService model-to-response conversion methods."""

    @pytest.mark.asyncio
    async def test_file_to_response(
        self,
        db_session: AsyncSession,
        test_user_id,
    ):
        """Test file_to_response converts File model to FileResponse."""
        from app.models.storage import File, StorageBackend
        from app.services.storage_service import StorageService

        service = StorageService(db_session)

        # Create a File model instance
        file = File(
            id=uuid4(),
            owner_id=test_user_id,
            filename="test.jpg",
            original_filename="test_photo.jpg",
            content_type="image/jpeg",
            size_bytes=1024,
            storage_backend=StorageBackend.LOCAL,
            storage_path="/storage/test.jpg",
            checksum="abc123",
            is_public=False,
            description="Test file",
        )
        file.thumbnails = []

        response = service.file_to_response(file)

        assert response.id == file.id
        assert response.owner_id == file.owner_id
        assert response.filename == file.filename
        assert response.original_filename == file.original_filename
        assert response.content_type == file.content_type
        assert response.size_bytes == file.size_bytes
        assert response.is_public == file.is_public
        assert response.description == file.description

    @pytest.mark.asyncio
    async def test_quota_to_response(
        self,
        db_session: AsyncSession,
        test_user_id,
    ):
        """Test quota_to_response converts StorageQuota model to response."""
        from app.models.storage import StorageQuota
        from app.services.storage_service import StorageService

        service = StorageService(db_session)

        # Create a StorageQuota model instance
        quota = StorageQuota(
            id=uuid4(),
            owner_id=test_user_id,
            quota_bytes=100 * 1024 * 1024,
            used_bytes=10 * 1024 * 1024,
            file_count=5,
        )

        response = service.quota_to_response(quota)

        assert response.id == quota.id
        assert response.owner_id == quota.owner_id
        assert response.quota_bytes == quota.quota_bytes
        assert response.used_bytes == quota.used_bytes
        assert response.file_count == quota.file_count
        assert response.available_bytes == quota.available_bytes


# =============================================================================
# API Endpoint Tests
# =============================================================================


class TestUploadEndpoint:
    """Tests for POST /api/v1/storage/upload endpoint."""

    @pytest.mark.asyncio
    async def test_upload_requires_auth(
        self,
        client: AsyncClient,
    ):
        """Test upload endpoint requires authentication."""
        response = await client.post(
            "/api/v1/storage/upload",
            files={"file": ("test.txt", b"test content", "text/plain")},
        )

        assert response.status_code == 401

    @pytest.mark.asyncio
    async def test_upload_missing_file_returns_422(
        self,
        client: AsyncClient,
        auth_headers: dict,
    ):
        """Test upload endpoint returns 422 when no file is provided."""
        response = await client.post(
            "/api/v1/storage/upload",
            headers=auth_headers,
            data={"description": "Test"},
        )

        assert response.status_code == 422


class TestGetQuotaEndpoint:
    """Tests for GET /api/v1/storage/quota endpoint."""

    @pytest.mark.asyncio
    async def test_get_quota_returns_200(
        self,
        client: AsyncClient,
        auth_headers: dict,
    ):
        """Test get quota endpoint returns 200."""
        response = await client.get(
            "/api/v1/storage/quota",
            headers=auth_headers,
        )

        assert response.status_code == 200
        data = response.json()
        assert "quota_bytes" in data
        assert "used_bytes" in data
        assert "available_bytes" in data
        assert "usage_percentage" in data
        assert "file_count" in data

    @pytest.mark.asyncio
    async def test_get_quota_requires_auth(
        self,
        client: AsyncClient,
    ):
        """Test get quota endpoint requires authentication."""
        response = await client.get("/api/v1/storage/quota")

        assert response.status_code == 401


class TestListFilesEndpoint:
    """Tests for GET /api/v1/storage/files endpoint."""

    @pytest.mark.asyncio
    async def test_list_files_returns_200(
        self,
        client: AsyncClient,
        auth_headers: dict,
    ):
        """Test list files endpoint returns 200."""
        response = await client.get(
            "/api/v1/storage/files",
            headers=auth_headers,
        )

        assert response.status_code == 200
        data = response.json()
        assert "items" in data
        assert "total" in data
        assert "skip" in data
        assert "limit" in data
        assert isinstance(data["items"], list)

    @pytest.mark.asyncio
    async def test_list_files_requires_auth(
        self,
        client: AsyncClient,
    ):
        """Test list files endpoint requires authentication."""
        response = await client.get("/api/v1/storage/files")

        assert response.status_code == 401

    @pytest.mark.asyncio
    async def test_list_files_pagination(
        self,
        client: AsyncClient,
        auth_headers: dict,
    ):
        """Test list files endpoint pagination."""
        response = await client.get(
            "/api/v1/storage/files",
            headers=auth_headers,
            params={"skip": 0, "limit": 5},
        )

        assert response.status_code == 200
        data = response.json()
        assert data["skip"] == 0
        assert data["limit"] == 5
        assert len(data["items"]) <= 5

    @pytest.mark.asyncio
    async def test_list_files_content_type_filter(
        self,
        client: AsyncClient,
        auth_headers: dict,
    ):
        """Test list files endpoint content type filter."""
        response = await client.get(
            "/api/v1/storage/files",
            headers=auth_headers,
            params={"content_type": "image/"},
        )

        assert response.status_code == 200
        data = response.json()
        for item in data["items"]:
            assert item["content_type"].startswith("image/")

    @pytest.mark.asyncio
    async def test_list_files_public_filter(
        self,
        client: AsyncClient,
        auth_headers: dict,
    ):
        """Test list files endpoint is_public filter."""
        response = await client.get(
            "/api/v1/storage/files",
            headers=auth_headers,
            params={"is_public": True},
        )

        assert response.status_code == 200
        data = response.json()
        for item in data["items"]:
            assert item["is_public"] is True


class TestGetFileEndpoint:
    """Tests for GET /api/v1/storage/files/{file_id} endpoint."""

    @pytest.mark.asyncio
    async def test_get_file_requires_auth(
        self,
        client: AsyncClient,
    ):
        """Test get file endpoint requires authentication."""
        file_id = uuid4()
        response = await client.get(f"/api/v1/storage/files/{file_id}")

        assert response.status_code == 401

    @pytest.mark.asyncio
    async def test_get_file_not_found(
        self,
        client: AsyncClient,
        auth_headers: dict,
    ):
        """Test get file endpoint returns 404 for non-existent file."""
        non_existent_id = uuid4()
        response = await client.get(
            f"/api/v1/storage/files/{non_existent_id}",
            headers=auth_headers,
        )

        assert response.status_code == 404


class TestDownloadFileEndpoint:
    """Tests for GET /api/v1/storage/files/{file_id}/download endpoint."""

    @pytest.mark.asyncio
    async def test_download_file_requires_auth(
        self,
        client: AsyncClient,
    ):
        """Test download file endpoint requires authentication."""
        file_id = uuid4()
        response = await client.get(f"/api/v1/storage/files/{file_id}/download")

        assert response.status_code == 401

    @pytest.mark.asyncio
    async def test_download_file_not_found(
        self,
        client: AsyncClient,
        auth_headers: dict,
    ):
        """Test download file endpoint returns 404 for non-existent file."""
        non_existent_id = uuid4()
        response = await client.get(
            f"/api/v1/storage/files/{non_existent_id}/download",
            headers=auth_headers,
        )

        assert response.status_code == 404


class TestDeleteFileEndpoint:
    """Tests for DELETE /api/v1/storage/files/{file_id} endpoint."""

    @pytest.mark.asyncio
    async def test_delete_file_requires_auth(
        self,
        client: AsyncClient,
    ):
        """Test delete file endpoint requires authentication."""
        file_id = uuid4()
        response = await client.delete(f"/api/v1/storage/files/{file_id}")

        assert response.status_code == 401

    @pytest.mark.asyncio
    async def test_delete_file_not_found(
        self,
        client: AsyncClient,
        auth_headers: dict,
    ):
        """Test delete file endpoint returns 404 for non-existent file."""
        non_existent_id = uuid4()
        response = await client.delete(
            f"/api/v1/storage/files/{non_existent_id}",
            headers=auth_headers,
        )

        assert response.status_code == 404


class TestSecureUrlEndpoint:
    """Tests for POST /api/v1/storage/files/{file_id}/secure-url endpoint."""

    @pytest.mark.asyncio
    async def test_secure_url_requires_auth(
        self,
        client: AsyncClient,
    ):
        """Test secure URL endpoint requires authentication."""
        file_id = uuid4()
        response = await client.post(f"/api/v1/storage/files/{file_id}/secure-url")

        assert response.status_code == 401

    @pytest.mark.asyncio
    async def test_secure_url_not_found(
        self,
        client: AsyncClient,
        auth_headers: dict,
    ):
        """Test secure URL endpoint returns 404 for non-existent file."""
        non_existent_id = uuid4()
        response = await client.post(
            f"/api/v1/storage/files/{non_existent_id}/secure-url",
            headers=auth_headers,
        )

        assert response.status_code == 404

    @pytest.mark.asyncio
    async def test_secure_url_validates_expiration(
        self,
        client: AsyncClient,
        auth_headers: dict,
    ):
        """Test secure URL endpoint validates expiration parameter."""
        file_id = uuid4()

        # Too short
        response = await client.post(
            f"/api/v1/storage/files/{file_id}/secure-url",
            headers=auth_headers,
            params={"expires_in_seconds": 10},
        )

        assert response.status_code == 422  # Validation error

        # Too long
        response = await client.post(
            f"/api/v1/storage/files/{file_id}/secure-url",
            headers=auth_headers,
            params={"expires_in_seconds": 100000},
        )

        assert response.status_code == 422  # Validation error


# =============================================================================
# Edge Case Tests
# =============================================================================


class TestEdgeCases:
    """Tests for edge cases and error handling."""

    @pytest.mark.asyncio
    async def test_invalid_uuid_format_get_file(
        self,
        client: AsyncClient,
        auth_headers: dict,
    ):
        """Test API handles invalid UUID format gracefully for get file."""
        response = await client.get(
            "/api/v1/storage/files/invalid-uuid",
            headers=auth_headers,
        )

        assert response.status_code == 422  # Validation error

    @pytest.mark.asyncio
    async def test_invalid_uuid_format_delete_file(
        self,
        client: AsyncClient,
        auth_headers: dict,
    ):
        """Test API handles invalid UUID format gracefully for delete file."""
        response = await client.delete(
            "/api/v1/storage/files/invalid-uuid",
            headers=auth_headers,
        )

        assert response.status_code == 422  # Validation error

    @pytest.mark.asyncio
    async def test_invalid_uuid_format_download(
        self,
        client: AsyncClient,
        auth_headers: dict,
    ):
        """Test API handles invalid UUID format gracefully for download."""
        response = await client.get(
            "/api/v1/storage/files/invalid-uuid/download",
            headers=auth_headers,
        )

        assert response.status_code == 422  # Validation error

    @pytest.mark.asyncio
    async def test_negative_skip_rejected(
        self,
        client: AsyncClient,
        auth_headers: dict,
    ):
        """Test negative skip parameter is rejected."""
        response = await client.get(
            "/api/v1/storage/files",
            headers=auth_headers,
            params={"skip": -1},
        )

        assert response.status_code == 422  # Validation error

    @pytest.mark.asyncio
    async def test_zero_limit_rejected(
        self,
        client: AsyncClient,
        auth_headers: dict,
    ):
        """Test zero limit parameter is rejected."""
        response = await client.get(
            "/api/v1/storage/files",
            headers=auth_headers,
            params={"limit": 0},
        )

        assert response.status_code == 422  # Validation error

    @pytest.mark.asyncio
    async def test_limit_over_100_rejected(
        self,
        client: AsyncClient,
        auth_headers: dict,
    ):
        """Test limit parameter over 100 is rejected."""
        response = await client.get(
            "/api/v1/storage/files",
            headers=auth_headers,
            params={"limit": 150},
        )

        assert response.status_code == 422  # Validation error

    @pytest.mark.asyncio
    async def test_storage_quota_zero_division(
        self,
        db_session: AsyncSession,
    ):
        """Test StorageQuota handles zero quota_bytes without division error."""
        owner_id = uuid4()

        # Create a quota with zero bytes (edge case)
        quota = MockStorageQuota(
            id=uuid4(),
            owner_id=owner_id,
            quota_bytes=0,
            used_bytes=0,
            file_count=0,
            created_at=datetime.now(timezone.utc),
            updated_at=datetime.now(timezone.utc),
        )

        # Should not raise ZeroDivisionError
        assert quota.usage_percentage == 0.0
        assert quota.available_bytes == 0


class TestFileMetadataFixtures:
    """Tests for file metadata and fixture integrity."""

    @pytest.mark.asyncio
    async def test_sample_file_data_fixture(
        self,
        sample_file_data: dict,
        test_user_id,
    ):
        """Test sample_file_data fixture has correct structure."""
        assert "owner_id" in sample_file_data
        assert "filename" in sample_file_data
        assert "original_filename" in sample_file_data
        assert "content_type" in sample_file_data
        assert "size_bytes" in sample_file_data
        assert "storage_backend" in sample_file_data
        assert sample_file_data["owner_id"] == test_user_id

    @pytest.mark.asyncio
    async def test_multiple_files_fixture(
        self,
        sample_files: list[MockFile],
        test_user_id,
    ):
        """Test sample_files fixture creates multiple files."""
        assert len(sample_files) == 5

        # All files should belong to the same owner
        for file in sample_files:
            assert file.owner_id == test_user_id

        # Should have variety of content types
        content_types = {file.content_type for file in sample_files}
        assert len(content_types) > 1

    @pytest.mark.asyncio
    async def test_thumbnail_sizes_fixture(
        self,
        sample_thumbnail_sizes: dict,
    ):
        """Test thumbnail_sizes fixture has correct structure."""
        assert "small" in sample_thumbnail_sizes
        assert "medium" in sample_thumbnail_sizes
        assert "large" in sample_thumbnail_sizes

        for size_name, dimensions in sample_thumbnail_sizes.items():
            assert "width" in dimensions
            assert "height" in dimensions
            assert dimensions["width"] > 0
            assert dimensions["height"] > 0

    @pytest.mark.asyncio
    async def test_image_content_types_fixture(
        self,
        image_content_types: list[str],
    ):
        """Test image_content_types fixture contains expected types."""
        assert "image/jpeg" in image_content_types
        assert "image/png" in image_content_types
        assert "image/gif" in image_content_types
        assert "image/webp" in image_content_types

    @pytest.mark.asyncio
    async def test_allowed_content_types_fixture(
        self,
        allowed_content_types: list[str],
    ):
        """Test allowed_content_types fixture contains expected types."""
        assert "image/jpeg" in allowed_content_types
        assert "application/pdf" in allowed_content_types
        assert len(allowed_content_types) >= 4
