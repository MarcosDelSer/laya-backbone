"""Document router for LAYA AI Service.

Provides API endpoints for document template management, document creation,
and signature workflows. All endpoints require JWT authentication.
"""

from typing import Any, Optional
from uuid import UUID

from fastapi import APIRouter, Depends, HTTPException, Query, status
from sqlalchemy.ext.asyncio import AsyncSession

from app.database import get_db
from app.dependencies import get_current_user
from app.schemas.document import (
    DocumentAuditLogListResponse,
    DocumentCreate,
    DocumentListResponse,
    DocumentResponse,
    DocumentTemplateCreate,
    DocumentTemplateListResponse,
    DocumentTemplateResponse,
    DocumentTemplateUpdate,
    DocumentUpdate,
    SignatureDashboardResponse,
    SignatureCreate,
    SignatureRequestCreate,
    SignatureRequestResponse,
    SignatureResponse,
)
from app.services.document_service import DocumentService

router = APIRouter(prefix="/api/v1/documents", tags=["documents"])


# Dashboard Endpoint


@router.get(
    "/dashboard",
    response_model=SignatureDashboardResponse,
    summary="Get signature status dashboard",
    description="Returns aggregated dashboard view with signature statistics, "
    "status breakdowns, and recent activity.",
)
async def get_signature_dashboard(
    user_id: Optional[UUID] = Query(
        default=None,
        description="Optional user ID to filter dashboard data",
    ),
    limit_recent: int = Query(
        default=10,
        ge=1,
        le=50,
        description="Number of recent activities to return",
    ),
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> SignatureDashboardResponse:
    """Get signature status dashboard with aggregated statistics.

    Provides comprehensive overview of document signatures including:
    - Summary statistics (total documents, pending, signed, completion rate)
    - Breakdown by document status
    - Breakdown by document type
    - Recent signature activity
    - Important alerts and notifications

    Args:
        user_id: Optional user ID to filter data (defaults to current user if not provided).
        limit_recent: Maximum number of recent activities to return.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        SignatureDashboardResponse with aggregated dashboard data.

    Raises:
        HTTPException: 401 if not authenticated.
    """
    service = DocumentService(db)

    # If no user_id provided, use current user
    if user_id is None:
        user_id = UUID(current_user.get("id") or current_user.get("sub"))

    dashboard_data = await service.get_signature_dashboard(
        user_id=user_id,
        limit_recent=limit_recent,
    )

    # Convert to response schema
    return SignatureDashboardResponse(**dashboard_data)


# Document Template Endpoints


@router.post(
    "/templates",
    response_model=DocumentTemplateResponse,
    status_code=status.HTTP_201_CREATED,
    summary="Create a document template",
    description="Create a new document template for generating standardized documents "
    "like enrollment forms, permission slips, or medical authorization forms.",
)
async def create_template(
    template_data: DocumentTemplateCreate,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> DocumentTemplateResponse:
    """Create a new document template.

    Templates define reusable document structures that can be used to generate
    documents requiring signatures. Examples include enrollment forms, permission
    slips, medical authorization forms, etc.

    Args:
        template_data: Document template creation data.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        DocumentTemplateResponse with the created template.

    Raises:
        HTTPException: 401 if not authenticated.
        HTTPException: 400 if validation fails.
    """
    service = DocumentService(db)
    template = await service.create_template(template_data)
    return service._template_to_response(template)


@router.get(
    "/templates/{template_id}",
    response_model=DocumentTemplateResponse,
    summary="Get template by ID",
    description="Retrieve a single document template by its unique identifier.",
)
async def get_template(
    template_id: UUID,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> DocumentTemplateResponse:
    """Get a document template by ID.

    Args:
        template_id: Unique identifier of the template.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        DocumentTemplateResponse with template details.

    Raises:
        HTTPException: 404 if template not found.
        HTTPException: 401 if not authenticated.
    """
    service = DocumentService(db)
    template = await service.get_template_by_id(template_id)

    if template is None:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=f"Template with id {template_id} not found",
        )

    return service._template_to_response(template)


@router.get(
    "/templates",
    response_model=DocumentTemplateListResponse,
    summary="List document templates",
    description="List all document templates with optional filtering and pagination.",
)
async def list_templates(
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
    template_type: Optional[str] = Query(
        default=None,
        description="Filter by document type (enrollment, permission, etc.)",
    ),
    is_active: Optional[bool] = Query(
        default=True,
        description="Filter by active status",
    ),
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> DocumentTemplateListResponse:
    """List document templates with optional filtering and pagination.

    Args:
        skip: Number of records to skip.
        limit: Maximum number of records to return.
        template_type: Optional filter by document type.
        is_active: Optional filter by active status.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        DocumentTemplateListResponse with paginated list of templates.

    Raises:
        HTTPException: 401 if not authenticated.
    """
    service = DocumentService(db)
    templates, total = await service.list_templates(
        skip=skip,
        limit=limit,
        template_type=template_type,
        is_active=is_active,
    )

    items = [service._template_to_response(template) for template in templates]

    return DocumentTemplateListResponse(
        items=items,
        total=total,
        skip=skip,
        limit=limit,
    )


@router.patch(
    "/templates/{template_id}",
    response_model=DocumentTemplateResponse,
    summary="Update a template",
    description="Update an existing document template. Version is automatically "
    "incremented when template_content is modified.",
)
async def update_template(
    template_id: UUID,
    update_data: DocumentTemplateUpdate,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> DocumentTemplateResponse:
    """Update a document template.

    Args:
        template_id: ID of the template to update.
        update_data: Fields to update.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        DocumentTemplateResponse with updated template.

    Raises:
        HTTPException: 404 if template not found.
        HTTPException: 401 if not authenticated.
    """
    service = DocumentService(db)
    template = await service.update_template(template_id, update_data)

    if template is None:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=f"Template with id {template_id} not found",
        )

    return service._template_to_response(template)


@router.delete(
    "/templates/{template_id}",
    status_code=status.HTTP_204_NO_CONTENT,
    summary="Delete a template",
    description="Soft delete a document template by marking it as inactive. "
    "The template will no longer be available for creating new documents.",
)
async def delete_template(
    template_id: UUID,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> None:
    """Soft delete a document template.

    Args:
        template_id: ID of the template to delete.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Raises:
        HTTPException: 404 if template not found.
        HTTPException: 401 if not authenticated.
    """
    service = DocumentService(db)
    success = await service.delete_template(template_id)

    if not success:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=f"Template with id {template_id} not found",
        )


# Document Endpoints


@router.post(
    "",
    response_model=DocumentResponse,
    status_code=status.HTTP_201_CREATED,
    summary="Create a document",
    description="Create a new document that requires signature. Can be created "
    "standalone or from a template.",
)
async def create_document(
    document_data: DocumentCreate,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> DocumentResponse:
    """Create a new document.

    Args:
        document_data: Document creation data.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        DocumentResponse with the created document.

    Raises:
        HTTPException: 401 if not authenticated.
        HTTPException: 400 if validation fails.
    """
    service = DocumentService(db)

    # If template_id provided, create from template
    if document_data.template_id:
        document = await service.create_document_from_template(
            template_id=document_data.template_id,
            title=document_data.title,
            content_url=document_data.content_url,
            created_by=document_data.created_by,
        )
        if document is None:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail=f"Template with id {document_data.template_id} not found or inactive",
            )
    else:
        document = await service.create_document(document_data)

    return service._document_to_response(document)


@router.get(
    "/{document_id}",
    response_model=DocumentResponse,
    summary="Get document by ID",
    description="Retrieve a single document by its unique identifier.",
)
async def get_document(
    document_id: UUID,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> DocumentResponse:
    """Get a document by ID.

    Args:
        document_id: Unique identifier of the document.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        DocumentResponse with document details.

    Raises:
        HTTPException: 404 if document not found.
        HTTPException: 401 if not authenticated.
    """
    service = DocumentService(db)
    document = await service.get_document_by_id(document_id)

    if document is None:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=f"Document with id {document_id} not found",
        )

    return service._document_to_response(document)


@router.get(
    "",
    response_model=DocumentListResponse,
    summary="List documents",
    description="List all documents with optional filtering and pagination.",
)
async def list_documents(
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
    document_type: Optional[str] = Query(
        default=None,
        description="Filter by document type",
    ),
    status: Optional[str] = Query(
        default=None,
        description="Filter by document status (draft/pending/signed/expired)",
    ),
    created_by: Optional[UUID] = Query(
        default=None,
        description="Filter by creator user ID",
    ),
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> DocumentListResponse:
    """List documents with optional filtering and pagination.

    Args:
        skip: Number of records to skip.
        limit: Maximum number of records to return.
        document_type: Optional filter by document type.
        status: Optional filter by document status.
        created_by: Optional filter by creator user ID.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        DocumentListResponse with paginated list of documents.

    Raises:
        HTTPException: 401 if not authenticated.
    """
    service = DocumentService(db)
    documents, total = await service.list_documents(
        skip=skip,
        limit=limit,
        document_type=document_type,
        status=status,
        created_by=created_by,
    )

    items = [service._document_to_response(document) for document in documents]

    return DocumentListResponse(
        items=items,
        total=total,
        skip=skip,
        limit=limit,
    )


@router.patch(
    "/{document_id}",
    response_model=DocumentResponse,
    summary="Update a document",
    description="Update an existing document. Limited updates allowed after signing.",
)
async def update_document(
    document_id: UUID,
    update_data: DocumentUpdate,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> DocumentResponse:
    """Update a document.

    Args:
        document_id: ID of the document to update.
        update_data: Fields to update.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        DocumentResponse with updated document.

    Raises:
        HTTPException: 404 if document not found.
        HTTPException: 401 if not authenticated.
    """
    service = DocumentService(db)
    document = await service.update_document(document_id, update_data)

    if document is None:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=f"Document with id {document_id} not found",
        )

    return service._document_to_response(document)


# Signature Endpoints


@router.post(
    "/{document_id}/signatures",
    response_model=SignatureResponse,
    status_code=status.HTTP_201_CREATED,
    summary="Sign a document",
    description="Create a signature for a document. Automatically updates document "
    "status to SIGNED and records audit trail.",
)
async def create_signature(
    document_id: UUID,
    signature_data: SignatureCreate,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> SignatureResponse:
    """Create a signature for a document.

    Args:
        document_id: ID of the document to sign.
        signature_data: Signature creation data.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        SignatureResponse with the created signature.

    Raises:
        HTTPException: 404 if document not found.
        HTTPException: 401 if not authenticated.
        HTTPException: 400 if document_id mismatch.
    """
    # Verify document_id matches
    if signature_data.document_id != document_id:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Document ID in path and body must match",
        )

    service = DocumentService(db)
    signature = await service.create_signature(signature_data)

    if signature is None:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=f"Document with id {document_id} not found",
        )

    return service._signature_to_response(signature)


@router.get(
    "/{document_id}/signatures",
    response_model=list[SignatureResponse],
    summary="Get document signatures",
    description="Retrieve all signatures for a specific document.",
)
async def get_document_signatures(
    document_id: UUID,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> list[SignatureResponse]:
    """Get all signatures for a document.

    Args:
        document_id: ID of the document.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        List of SignatureResponse for the document.

    Raises:
        HTTPException: 401 if not authenticated.
    """
    service = DocumentService(db)
    signatures = await service.get_signatures_for_document(document_id)

    return [service._signature_to_response(sig) for sig in signatures]


# Signature Request Workflow Endpoints


@router.post(
    "/signature-requests",
    response_model=SignatureRequestResponse,
    status_code=status.HTTP_201_CREATED,
    summary="Send a signature request",
    description="Create and send a signature request for a document. Updates document "
    "status to PENDING and sends notification to signer.",
)
async def send_signature_request(
    request_data: SignatureRequestCreate,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> SignatureRequestResponse:
    """Send a signature request for a document.

    This initiates the signature request workflow:
    1. Validates document exists and is in DRAFT status
    2. Updates document status to PENDING
    3. Creates a signature request record
    4. Sends notification to signer
    5. Returns the signature request

    Args:
        request_data: Signature request creation data.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        SignatureRequestResponse with the created signature request.

    Raises:
        HTTPException: 404 if document not found.
        HTTPException: 400 if document is not in DRAFT status.
        HTTPException: 401 if not authenticated.
    """
    service = DocumentService(db)

    # Extract requester_id from current_user
    requester_id = UUID(current_user.get("id") or current_user.get("sub"))

    signature_request = await service.send_signature_request(
        request_data=request_data,
        requester_id=requester_id,
    )

    if signature_request is None:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Cannot send signature request. Document not found or not in DRAFT status.",
        )

    return service._signature_request_to_response(signature_request)


@router.get(
    "/signature-requests/{request_id}",
    response_model=SignatureRequestResponse,
    summary="Get signature request by ID",
    description="Retrieve a specific signature request by its unique identifier.",
)
async def get_signature_request(
    request_id: UUID,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> SignatureRequestResponse:
    """Get a signature request by ID.

    Args:
        request_id: Unique identifier of the signature request.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        SignatureRequestResponse with signature request details.

    Raises:
        HTTPException: 404 if signature request not found.
        HTTPException: 401 if not authenticated.
    """
    service = DocumentService(db)
    signature_request = await service.get_signature_request_by_id(request_id)

    if signature_request is None:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=f"Signature request with id {request_id} not found",
        )

    return service._signature_request_to_response(signature_request)


@router.patch(
    "/signature-requests/{request_id}/viewed",
    response_model=SignatureRequestResponse,
    summary="Mark signature request as viewed",
    description="Mark a signature request as viewed by the signer. Updates status to VIEWED.",
)
async def mark_signature_request_viewed(
    request_id: UUID,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> SignatureRequestResponse:
    """Mark a signature request as viewed.

    Updates the signature request status to VIEWED and records the viewed timestamp.
    This is typically called when the signer opens the document for the first time.

    Args:
        request_id: ID of the signature request.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        SignatureRequestResponse with updated signature request.

    Raises:
        HTTPException: 404 if signature request not found.
        HTTPException: 401 if not authenticated.
    """
    service = DocumentService(db)
    signature_request = await service.mark_request_viewed(request_id)

    if signature_request is None:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=f"Signature request with id {request_id} not found",
        )

    return service._signature_request_to_response(signature_request)


@router.get(
    "/signature-requests",
    response_model=list[SignatureRequestResponse],
    summary="List signature requests for current user",
    description="List all signature requests for the authenticated user (as signer). "
    "Supports filtering by status and pagination.",
)
async def list_my_signature_requests(
    status_filter: Optional[str] = Query(
        default=None,
        alias="status",
        description="Filter by request status (sent/viewed/completed/cancelled/expired)",
    ),
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
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> list[SignatureRequestResponse]:
    """List signature requests for the current user.

    Returns all signature requests where the current user is the signer.
    Useful for showing a user their pending signature requests.

    Args:
        status_filter: Optional filter by request status.
        skip: Number of records to skip.
        limit: Maximum number of records to return.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        List of SignatureRequestResponse for the current user.

    Raises:
        HTTPException: 401 if not authenticated.
    """
    service = DocumentService(db)

    # Extract user_id from current_user
    user_id = UUID(current_user.get("id") or current_user.get("sub"))

    requests, total = await service.list_signature_requests_for_signer(
        signer_id=user_id,
        status=status_filter,
        skip=skip,
        limit=limit,
    )

    return [service._signature_request_to_response(req) for req in requests]


@router.get(
    "/{document_id}/signature-request",
    response_model=SignatureRequestResponse,
    summary="Get signature request for document",
    description="Retrieve the most recent signature request for a specific document.",
)
async def get_document_signature_request(
    document_id: UUID,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> SignatureRequestResponse:
    """Get the most recent signature request for a document.

    Args:
        document_id: ID of the document.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        SignatureRequestResponse with the most recent signature request.

    Raises:
        HTTPException: 404 if no signature request found for document.
        HTTPException: 401 if not authenticated.
    """
    service = DocumentService(db)
    signature_request = await service.get_signature_request_by_document(document_id)

    if signature_request is None:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=f"No signature request found for document {document_id}",
        )

    return service._signature_request_to_response(signature_request)


# Audit Log Endpoints


@router.get(
    "/{document_id}/audit-logs",
    response_model=DocumentAuditLogListResponse,
    summary="Get audit logs for a document",
    description="Retrieve the complete audit trail for a specific document. "
    "Includes all events from creation through signatures.",
)
async def get_document_audit_logs(
    document_id: UUID,
    skip: int = Query(
        default=0,
        ge=0,
        description="Number of records to skip for pagination",
    ),
    limit: int = Query(
        default=50,
        ge=1,
        le=200,
        description="Maximum number of records to return",
    ),
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> DocumentAuditLogListResponse:
    """Get all audit logs for a specific document.

    Provides complete audit trail showing all significant events in the
    document lifecycle including creation, updates, signature requests,
    views, and signatures.

    Args:
        document_id: ID of the document.
        skip: Number of records to skip.
        limit: Maximum number of records to return.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        DocumentAuditLogListResponse with paginated audit logs.

    Raises:
        HTTPException: 401 if not authenticated.
    """
    service = DocumentService(db)

    # Verify document exists
    document = await service.get_document_by_id(document_id)
    if document is None:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=f"Document with id {document_id} not found",
        )

    audit_logs, total = await service.get_audit_logs_for_document(
        document_id=document_id,
        skip=skip,
        limit=limit,
    )

    items = [service._audit_log_to_response(log) for log in audit_logs]

    return DocumentAuditLogListResponse(
        items=items,
        total=total,
        skip=skip,
        limit=limit,
    )


@router.get(
    "/audit-logs/user/{user_id}",
    response_model=DocumentAuditLogListResponse,
    summary="Get audit logs for a user",
    description="Retrieve all audit logs for a specific user across all documents.",
)
async def get_user_audit_logs(
    user_id: UUID,
    skip: int = Query(
        default=0,
        ge=0,
        description="Number of records to skip for pagination",
    ),
    limit: int = Query(
        default=50,
        ge=1,
        le=200,
        description="Maximum number of records to return",
    ),
    event_type: Optional[str] = Query(
        default=None,
        description="Filter by event type",
    ),
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> DocumentAuditLogListResponse:
    """Get all audit logs for a specific user.

    Retrieves audit trail for all document and signature events
    performed by a specific user.

    Args:
        user_id: ID of the user.
        skip: Number of records to skip.
        limit: Maximum number of records to return.
        event_type: Optional filter by event type.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        DocumentAuditLogListResponse with paginated audit logs.

    Raises:
        HTTPException: 401 if not authenticated.
    """
    service = DocumentService(db)

    audit_logs, total = await service.get_audit_logs_for_user(
        user_id=user_id,
        skip=skip,
        limit=limit,
        event_type=event_type,
    )

    items = [service._audit_log_to_response(log) for log in audit_logs]

    return DocumentAuditLogListResponse(
        items=items,
        total=total,
        skip=skip,
        limit=limit,
    )
