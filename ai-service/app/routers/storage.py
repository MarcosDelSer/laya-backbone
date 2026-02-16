"""Storage router for LAYA AI Service.

Provides API endpoints for file upload, download, and storage management.
Supports multipart file upload with local and S3 backend storage.
All endpoints require JWT authentication.
"""

from typing import Any, Optional
from uuid import UUID

from fastapi import (
    APIRouter,
    Depends,
    File,
    Form,
    HTTPException,
    Query,
    Request,
    UploadFile,
)
from sqlalchemy.ext.asyncio import AsyncSession

from app.database import get_db
from app.dependencies import get_current_user
from app.schemas.storage import (
    FileResponse,
    FileUploadResponse,
    StorageQuotaResponse,
)
from app.services.storage_service import (
    FileTooLargeError,
    InvalidFileTypeError,
    QuotaExceededError,
    StorageService,
    StorageServiceError,
    FileNotFoundError as StorageFileNotFoundError,
)

router = APIRouter(prefix="/api/v1/storage", tags=["storage"])


@router.post(
    "/upload",
    response_model=FileUploadResponse,
    summary="Upload a file",
    description="Upload a file using multipart form data. "
    "Supports images, documents, and other allowed file types. "
    "Files are stored based on the configured storage backend (local or S3).",
)
async def upload_file(
    file: UploadFile = File(
        ...,
        description="The file to upload",
    ),
    description: Optional[str] = Form(
        default=None,
        description="Optional description of the file",
    ),
    is_public: bool = Form(
        default=False,
        description="Whether the file should be publicly accessible",
    ),
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> FileUploadResponse:
    """Upload a file using multipart form data.

    This endpoint accepts multipart form data containing a file and optional
    metadata. The file is validated for type and size before being stored.

    Supported file types are configured via the ALLOWED_FILE_TYPES setting
    and typically include:
    - Images: image/jpeg, image/png, image/gif, image/webp
    - Documents: application/pdf

    The file is stored using the configured storage backend (local filesystem
    or S3). For images, thumbnails can be generated via a separate endpoint.

    Args:
        file: The file to upload via multipart form data.
        description: Optional description of the file.
        is_public: Whether the file should be publicly accessible (default False).
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        FileUploadResponse containing the uploaded file details and success message.

    Raises:
        HTTPException: 400 if file type is not allowed or file is too large.
        HTTPException: 401 if not authenticated.
        HTTPException: 413 if file exceeds maximum size.
        HTTPException: 507 if storage quota would be exceeded.

    Example:
        POST /api/v1/storage/upload
        Content-Type: multipart/form-data

        --boundary
        Content-Disposition: form-data; name="file"; filename="photo.jpg"
        Content-Type: image/jpeg

        <binary file data>
        --boundary
        Content-Disposition: form-data; name="description"

        My photo description
        --boundary--
    """
    # Get owner ID from current user
    owner_id = UUID(current_user["sub"])

    # Read file content
    file_content = await file.read()

    # Determine content type
    content_type = file.content_type or "application/octet-stream"

    # Get original filename
    original_filename = file.filename or "unnamed_file"

    # Initialize service and upload
    service = StorageService(db)

    try:
        file_record = await service.upload_file(
            owner_id=owner_id,
            file_content=file_content,
            original_filename=original_filename,
            content_type=content_type,
            description=description,
            is_public=is_public,
        )
    except InvalidFileTypeError as e:
        raise HTTPException(
            status_code=400,
            detail=str(e),
        )
    except FileTooLargeError as e:
        raise HTTPException(
            status_code=413,
            detail=str(e),
        )
    except QuotaExceededError as e:
        raise HTTPException(
            status_code=507,
            detail=str(e),
        )
    except StorageServiceError as e:
        raise HTTPException(
            status_code=500,
            detail=f"Storage error: {str(e)}",
        )

    # Convert to response
    file_response = service.file_to_response(file_record)

    return FileUploadResponse(
        file=file_response,
        message="File uploaded successfully",
    )


@router.get(
    "/quota",
    response_model=StorageQuotaResponse,
    summary="Get storage quota",
    description="Retrieve the current user's storage quota and usage information.",
)
async def get_quota(
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> StorageQuotaResponse:
    """Get the current user's storage quota information.

    Returns detailed information about storage quota including:
    - Maximum allowed storage (quota_bytes)
    - Current usage (used_bytes)
    - Available space (available_bytes)
    - Usage percentage
    - Number of files stored

    Args:
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        StorageQuotaResponse with quota details.

    Raises:
        HTTPException: 401 if not authenticated.
    """
    owner_id = UUID(current_user["sub"])
    service = StorageService(db)

    quota = await service.get_or_create_quota(owner_id)
    return service.quota_to_response(quota)
