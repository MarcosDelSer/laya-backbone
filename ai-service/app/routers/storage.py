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
from fastapi.responses import Response
from sqlalchemy.ext.asyncio import AsyncSession

from app.config import settings
from app.database import get_db
from app.dependencies import get_current_user
from app.schemas.storage import (
    FileDeleteResponse,
    FileListResponse,
    FileResponse,
    FileUploadResponse,
    SecureUrlResponse,
    StorageQuotaResponse,
)
from app.services.storage_service import (
    FileTooLargeError,
    InvalidFileTypeError,
    QuotaExceededError,
    SignedUrlExpiredError,
    SignedUrlInvalidError,
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
    request: Request,
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

    # Calculate maximum allowed file size in bytes
    max_file_size_bytes = settings.max_file_size_mb * 1024 * 1024

    # Check Content-Length header BEFORE buffering the file
    content_length = request.headers.get("content-length")
    if content_length:
        try:
            declared_size = int(content_length)
            # Content-Length is for the entire multipart body, but we can use it
            # as an upper bound check. If the total request body exceeds the max
            # file size, reject early.
            if declared_size > max_file_size_bytes:
                raise HTTPException(
                    status_code=413,
                    detail=f"Request body size ({declared_size} bytes) exceeds "
                    f"maximum allowed file size ({max_file_size_bytes} bytes)",
                )
        except ValueError:
            pass  # Invalid Content-Length header, proceed with streaming check

    # Read file content with size limit enforcement
    file_content = bytearray()
    chunk_size = 64 * 1024  # 64KB chunks
    bytes_read = 0

    while True:
        chunk = await file.read(chunk_size)
        if not chunk:
            break
        bytes_read += len(chunk)
        if bytes_read > max_file_size_bytes:
            raise HTTPException(
                status_code=413,
                detail=f"File size exceeds maximum allowed size "
                f"({max_file_size_bytes} bytes)",
            )
        file_content.extend(chunk)

    file_content = bytes(file_content)

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


@router.get(
    "/files",
    response_model=FileListResponse,
    summary="List files",
    description="List all files owned by the current user with optional filtering and pagination.",
)
async def list_files(
    skip: int = Query(
        default=0,
        ge=0,
        description="Number of records to skip for pagination",
    ),
    limit: int = Query(
        default=20,
        ge=1,
        le=100,
        description="Maximum number of records to return",
    ),
    content_type: Optional[str] = Query(
        default=None,
        description="Filter by content type (e.g., 'image/' for all images, 'image/png' for PNG only)",
    ),
    is_public: Optional[bool] = Query(
        default=None,
        description="Filter by public visibility",
    ),
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> FileListResponse:
    """List files owned by the current user.

    Returns a paginated list of files with optional filtering by content type
    and public visibility. Files are returned in descending order by creation date.

    Args:
        skip: Number of records to skip for pagination.
        limit: Maximum number of records to return (1-100).
        content_type: Optional filter by MIME type. Use 'image/' to filter
            all image types, or 'image/png' for specific type.
        is_public: Optional filter by public visibility.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        FileListResponse with paginated list of files.

    Raises:
        HTTPException: 401 if not authenticated.

    Example:
        GET /api/v1/storage/files?skip=0&limit=10&content_type=image/
    """
    owner_id = UUID(current_user["sub"])
    service = StorageService(db)

    files, total = await service.list_files(
        owner_id=owner_id,
        skip=skip,
        limit=limit,
        content_type=content_type,
        is_public=is_public,
    )

    items = [service.file_to_response(file) for file in files]

    return FileListResponse(
        items=items,
        total=total,
        skip=skip,
        limit=limit,
    )


@router.get(
    "/files/{file_id}",
    response_model=FileResponse,
    summary="Get file information",
    description="Retrieve file metadata by its unique identifier.",
)
async def get_file(
    file_id: UUID,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> FileResponse:
    """Get file information by ID.

    Retrieves metadata for a specific file. The file must be owned by the
    current user or be marked as public.

    Args:
        file_id: Unique identifier of the file.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        FileResponse with file details.

    Raises:
        HTTPException: 401 if not authenticated.
        HTTPException: 404 if file not found or not accessible.
    """
    owner_id = UUID(current_user["sub"])
    service = StorageService(db)

    file_record = await service.get_file_by_id(file_id)

    if file_record is None:
        raise HTTPException(
            status_code=404,
            detail=f"File with id {file_id} not found",
        )

    # Check access permissions
    if not file_record.is_public and file_record.owner_id != owner_id:
        raise HTTPException(
            status_code=404,
            detail=f"File with id {file_id} not found",
        )

    return service.file_to_response(file_record)


@router.get(
    "/files/{file_id}/download",
    summary="Download file",
    description="Download file content by its unique identifier.",
    responses={
        200: {
            "description": "File content",
            "content": {"application/octet-stream": {}},
        },
        404: {"description": "File not found"},
    },
)
async def download_file(
    file_id: UUID,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> Response:
    """Download file content.

    Downloads the actual file content. The file must be owned by the
    current user or be marked as public.

    Args:
        file_id: Unique identifier of the file.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        File content as binary response with appropriate content type.

    Raises:
        HTTPException: 401 if not authenticated.
        HTTPException: 404 if file not found or not accessible.
        HTTPException: 500 if storage error occurs.

    Example:
        GET /api/v1/storage/files/123e4567-e89b-12d3-a456-426614174000/download
    """
    owner_id = UUID(current_user["sub"])
    service = StorageService(db)

    try:
        file_content, file_record = await service.download_file(
            file_id=file_id,
            owner_id=owner_id,
        )
    except StorageFileNotFoundError:
        raise HTTPException(
            status_code=404,
            detail=f"File with id {file_id} not found",
        )
    except StorageServiceError as e:
        raise HTTPException(
            status_code=500,
            detail=f"Storage error: {str(e)}",
        )

    # Return file content with appropriate headers
    return Response(
        content=file_content,
        media_type=file_record.content_type,
        headers={
            "Content-Disposition": f'attachment; filename="{file_record.original_filename}"',
            "Content-Length": str(file_record.size_bytes),
        },
    )


@router.delete(
    "/files/{file_id}",
    response_model=FileDeleteResponse,
    summary="Delete file",
    description="Delete a file by its unique identifier. Only the file owner can delete it.",
)
async def delete_file(
    file_id: UUID,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> FileDeleteResponse:
    """Delete a file.

    Permanently deletes a file from storage and removes its database record.
    Also deletes any associated thumbnails. Only the file owner can delete it.

    Args:
        file_id: Unique identifier of the file to delete.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        FileDeleteResponse with deleted file ID and success message.

    Raises:
        HTTPException: 401 if not authenticated.
        HTTPException: 404 if file not found or not owned by user.
        HTTPException: 500 if storage error occurs.

    Example:
        DELETE /api/v1/storage/files/123e4567-e89b-12d3-a456-426614174000
    """
    owner_id = UUID(current_user["sub"])
    service = StorageService(db)

    try:
        await service.delete_file(
            file_id=file_id,
            owner_id=owner_id,
        )
    except StorageFileNotFoundError:
        raise HTTPException(
            status_code=404,
            detail=f"File with id {file_id} not found",
        )
    except StorageServiceError as e:
        raise HTTPException(
            status_code=500,
            detail=f"Storage error: {str(e)}",
        )

    return FileDeleteResponse(
        file_id=file_id,
        message="File deleted successfully",
    )


@router.post(
    "/files/{file_id}/secure-url",
    response_model=SecureUrlResponse,
    summary="Generate secure URL",
    description="Generate a time-limited secure URL for accessing a file. "
    "The URL will include a cryptographic signature and expiration timestamp.",
)
async def generate_secure_url(
    request: Request,
    file_id: UUID,
    expires_in_seconds: int = Query(
        default=3600,
        ge=60,
        le=86400,
        description="URL expiration time in seconds (1 minute to 24 hours, default 1 hour)",
    ),
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> SecureUrlResponse:
    """Generate a secure, time-limited URL for file access.

    Creates a signed URL that allows temporary access to a file without
    requiring authentication. For S3 backend, uses AWS presigned URLs.
    For local backend, generates HMAC-signed tokens.

    The URL can be shared with others and will automatically expire
    after the specified duration. Public files can have secure URLs
    generated by any authenticated user, while private files require
    the file owner to generate the URL.

    Args:
        file_id: Unique identifier of the file.
        expires_in_seconds: How long the URL should remain valid.
            Must be between 60 (1 minute) and 86400 (24 hours).
            Defaults to 3600 (1 hour).
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        SecureUrlResponse containing the secure URL and expiration timestamp.

    Raises:
        HTTPException: 401 if not authenticated.
        HTTPException: 404 if file not found or not accessible.
        HTTPException: 500 if URL generation fails.

    Example:
        POST /api/v1/storage/files/123e4567-e89b-12d3-a456-426614174000/secure-url?expires_in_seconds=7200
    """
    owner_id = UUID(current_user["sub"])
    service = StorageService(db)

    # Derive base_url from the request context
    # Use X-Forwarded-Proto and X-Forwarded-Host headers if behind a proxy
    forwarded_proto = request.headers.get("x-forwarded-proto")
    forwarded_host = request.headers.get("x-forwarded-host")

    if forwarded_proto and forwarded_host:
        base_url = f"{forwarded_proto}://{forwarded_host}"
    else:
        # Fall back to request URL components
        base_url = f"{request.url.scheme}://{request.url.netloc}"

    try:
        url, expires_at = await service.generate_secure_url(
            file_id=file_id,
            owner_id=owner_id,
            expires_in_seconds=expires_in_seconds,
            base_url=base_url,
        )
    except StorageFileNotFoundError:
        raise HTTPException(
            status_code=404,
            detail=f"File with id {file_id} not found",
        )
    except ValueError as e:
        raise HTTPException(
            status_code=400,
            detail=str(e),
        )
    except StorageServiceError as e:
        raise HTTPException(
            status_code=500,
            detail=f"Failed to generate secure URL: {str(e)}",
        )

    return SecureUrlResponse(
        file_id=file_id,
        url=url,
        expires_at=expires_at,
    )


@router.get(
    "/files/{file_id}/signed-download",
    summary="Download file via signed URL",
    description="Download file content using a signed URL. "
    "This endpoint does not require authentication - the signed URL "
    "serves as authorization. The URL must include valid 'expires' and "
    "'signature' query parameters.",
    responses={
        200: {
            "description": "File content",
            "content": {"application/octet-stream": {}},
        },
        400: {"description": "Invalid or missing signature parameters"},
        401: {"description": "Signature expired"},
        403: {"description": "Invalid signature"},
        404: {"description": "File not found"},
    },
)
async def download_file_signed(
    file_id: UUID,
    expires: int = Query(
        ...,
        description="Unix timestamp when the signed URL expires",
    ),
    signature: str = Query(
        ...,
        description="HMAC signature for URL verification",
    ),
    db: AsyncSession = Depends(get_db),
) -> Response:
    """Download file content using a signed URL.

    This endpoint allows file downloads without JWT authentication.
    Instead, it verifies the request using the signed URL parameters
    (expires timestamp and HMAC signature).

    The signed URL should be generated using the /secure-url endpoint.

    Args:
        file_id: Unique identifier of the file.
        expires: Unix timestamp when the URL expires.
        signature: Base64-encoded HMAC signature.
        db: Async database session (injected).

    Returns:
        File content as binary response with appropriate content type.

    Raises:
        HTTPException: 400 if signature parameters are invalid.
        HTTPException: 401 if the signed URL has expired.
        HTTPException: 403 if the signature is invalid.
        HTTPException: 404 if file not found.
        HTTPException: 500 if storage error occurs.

    Example:
        GET /api/v1/storage/files/123e4567-e89b-12d3-a456-426614174000/signed-download?expires=1699999999&signature=abc123
    """
    service = StorageService(db)

    try:
        file_content, file_record = await service.download_file_with_signed_url(
            file_id=file_id,
            expires_timestamp=expires,
            signature=signature,
        )
    except SignedUrlExpiredError:
        raise HTTPException(
            status_code=401,
            detail="Signed URL has expired",
        )
    except SignedUrlInvalidError as e:
        raise HTTPException(
            status_code=403,
            detail=str(e),
        )
    except StorageFileNotFoundError:
        raise HTTPException(
            status_code=404,
            detail=f"File with id {file_id} not found",
        )
    except StorageServiceError as e:
        raise HTTPException(
            status_code=500,
            detail=f"Storage error: {str(e)}",
        )

    # Return file content with appropriate headers
    return Response(
        content=file_content,
        media_type=file_record.content_type,
        headers={
            "Content-Disposition": f'attachment; filename="{file_record.original_filename}"',
            "Content-Length": str(file_record.size_bytes),
        },
    )
