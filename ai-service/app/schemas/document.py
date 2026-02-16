"""Document domain schemas for LAYA AI Service.

Defines Pydantic schemas for document templates, documents, and signatures.
Used for request validation and response serialization in document e-signature workflows.
"""

from datetime import datetime
from typing import Any, Optional
from uuid import UUID

from pydantic import BaseModel, Field

from app.schemas.base import BaseResponse, BaseSchema, PaginatedResponse


class DocumentTemplateBase(BaseSchema):
    """Base schema for document template data.

    Contains common fields shared between request and response schemas.

    Attributes:
        name: Human-readable name of the template
        type: Type/category of documents created from this template
        description: Detailed description of the template purpose
        template_content: JSON structure or HTML content defining the template
        required_fields: List of field names that must be filled when using template
    """

    name: str = Field(
        ...,
        min_length=1,
        max_length=255,
        description="Name of the template",
    )
    type: str = Field(
        ...,
        description="Type/category of documents (enrollment, permission, etc.)",
    )
    description: Optional[str] = Field(
        default=None,
        max_length=2000,
        description="Detailed description of the template purpose",
    )
    template_content: str = Field(
        ...,
        min_length=1,
        description="JSON structure or HTML content defining the template",
    )
    required_fields: Optional[list[str]] = Field(
        default=None,
        description="List of field names that must be filled",
    )


class DocumentTemplateCreate(DocumentTemplateBase):
    """Request schema for creating a document template.

    Inherits all fields from DocumentTemplateBase.

    Attributes:
        created_by: User ID of the person creating the template
    """

    created_by: UUID = Field(
        ...,
        description="User ID of the person creating the template",
    )


class DocumentTemplateUpdate(BaseSchema):
    """Request schema for updating a document template.

    All fields are optional to support partial updates.

    Attributes:
        name: Updated name of the template
        description: Updated description
        template_content: Updated template content
        required_fields: Updated list of required fields
        is_active: Whether the template is currently available for use
    """

    name: Optional[str] = Field(
        default=None,
        min_length=1,
        max_length=255,
        description="Updated name of the template",
    )
    description: Optional[str] = Field(
        default=None,
        max_length=2000,
        description="Updated description",
    )
    template_content: Optional[str] = Field(
        default=None,
        min_length=1,
        description="Updated template content",
    )
    required_fields: Optional[list[str]] = Field(
        default=None,
        description="Updated list of required fields",
    )
    is_active: Optional[bool] = Field(
        default=None,
        description="Whether the template is currently available",
    )


class DocumentTemplateResponse(DocumentTemplateBase, BaseResponse):
    """Response schema for document template data.

    Includes all base template fields plus ID, timestamps, and metadata.

    Attributes:
        is_active: Whether the template is currently available for use
        version: Version number for tracking template updates
        created_by: User ID of the person who created the template
    """

    is_active: bool = Field(
        default=True,
        description="Whether the template is currently available for use",
    )
    version: int = Field(
        default=1,
        ge=1,
        description="Version number for tracking template updates",
    )
    created_by: UUID = Field(
        ...,
        description="User ID of the person who created the template",
    )


class DocumentTemplateListResponse(PaginatedResponse):
    """Paginated list of document templates.

    Attributes:
        items: List of document templates
    """

    items: list[DocumentTemplateResponse] = Field(
        ...,
        description="List of document templates",
    )


class DocumentBase(BaseSchema):
    """Base schema for document data.

    Contains common fields shared between request and response schemas.

    Attributes:
        type: Type/category of the document
        title: Human-readable title of the document
        content_url: URL or path to the actual document file
    """

    type: str = Field(
        ...,
        description="Type/category of the document",
    )
    title: str = Field(
        ...,
        min_length=1,
        max_length=255,
        description="Human-readable title of the document",
    )
    content_url: str = Field(
        ...,
        min_length=1,
        description="URL or path to the actual document file",
    )


class DocumentCreate(DocumentBase):
    """Request schema for creating a document.

    Inherits all fields from DocumentBase.

    Attributes:
        created_by: User ID of the person creating the document
        template_id: Optional template ID if created from a template
    """

    created_by: UUID = Field(
        ...,
        description="User ID of the person creating the document",
    )
    template_id: Optional[UUID] = Field(
        default=None,
        description="Optional template ID if created from a template",
    )


class DocumentUpdate(BaseSchema):
    """Request schema for updating a document.

    All fields are optional to support partial updates.

    Attributes:
        title: Updated title
        content_url: Updated content URL
        status: Updated status (draft/pending/signed/expired)
    """

    title: Optional[str] = Field(
        default=None,
        min_length=1,
        max_length=255,
        description="Updated title",
    )
    content_url: Optional[str] = Field(
        default=None,
        min_length=1,
        description="Updated content URL",
    )
    status: Optional[str] = Field(
        default=None,
        description="Updated status (draft/pending/signed/expired)",
    )


class DocumentResponse(DocumentBase, BaseResponse):
    """Response schema for document data.

    Includes all base document fields plus ID, timestamps, and status.

    Attributes:
        status: Current status of the document (draft/pending/signed/expired)
        created_by: User ID of the person who created the document
    """

    status: str = Field(
        ...,
        description="Current status of the document",
    )
    created_by: UUID = Field(
        ...,
        description="User ID of the person who created the document",
    )


class DocumentListResponse(PaginatedResponse):
    """Paginated list of documents.

    Attributes:
        items: List of documents
    """

    items: list[DocumentResponse] = Field(
        ...,
        description="List of documents",
    )


class SignatureBase(BaseSchema):
    """Base schema for signature data.

    Contains common fields shared between request and response schemas.

    Attributes:
        signature_image_url: URL or path to the stored signature image file
        ip_address: IP address from which the signature was submitted
        device_info: Information about the device/browser used for signing
    """

    signature_image_url: str = Field(
        ...,
        min_length=1,
        description="URL or path to the stored signature image file",
    )
    ip_address: str = Field(
        ...,
        min_length=1,
        max_length=45,
        description="IP address from which the signature was submitted",
    )
    device_info: Optional[str] = Field(
        default=None,
        description="Information about the device/browser used for signing",
    )


class SignatureCreate(SignatureBase):
    """Request schema for creating a signature.

    Inherits all fields from SignatureBase.

    Attributes:
        document_id: ID of the document being signed
        signer_id: User ID of the person signing the document
    """

    document_id: UUID = Field(
        ...,
        description="ID of the document being signed",
    )
    signer_id: UUID = Field(
        ...,
        description="User ID of the person signing the document",
    )


class SignatureResponse(SignatureBase, BaseResponse):
    """Response schema for signature data.

    Includes all base signature fields plus ID, timestamps, and relationships.

    Attributes:
        document_id: ID of the document that was signed
        signer_id: User ID of the person who signed the document
        timestamp: Date and time when the signature was created
    """

    document_id: UUID = Field(
        ...,
        description="ID of the document that was signed",
    )
    signer_id: UUID = Field(
        ...,
        description="User ID of the person who signed the document",
    )
    timestamp: datetime = Field(
        ...,
        description="Date and time when the signature was created",
    )


class SignatureRequestCreate(BaseSchema):
    """Request schema for creating a signature request.

    Attributes:
        document_id: ID of the document requiring signature
        signer_id: User ID of the person who should sign
        message: Optional message from requester to signer
        expires_in_days: Number of days until request expires (default: 7)
    """

    document_id: UUID = Field(
        ...,
        description="ID of the document requiring signature",
    )
    signer_id: UUID = Field(
        ...,
        description="User ID of the person who should sign",
    )
    message: Optional[str] = Field(
        default=None,
        max_length=1000,
        description="Optional message from requester to signer",
    )
    expires_in_days: int = Field(
        default=7,
        ge=1,
        le=30,
        description="Number of days until request expires",
    )


class SignatureRequestResponse(BaseResponse):
    """Response schema for signature request data.

    Attributes:
        document_id: ID of the document requiring signature
        requester_id: User ID of the person requesting the signature
        signer_id: User ID of the person who should sign
        status: Current status of the request
        sent_at: Timestamp when the request was sent
        viewed_at: Timestamp when the document was viewed by signer
        completed_at: Timestamp when the signature was completed
        expires_at: Timestamp when the request expires
        notification_sent: Whether notification was successfully sent
        notification_method: Method used for notification
        message: Optional message from requester to signer
    """

    document_id: UUID = Field(
        ...,
        description="ID of the document requiring signature",
    )
    requester_id: UUID = Field(
        ...,
        description="User ID of the person requesting the signature",
    )
    signer_id: UUID = Field(
        ...,
        description="User ID of the person who should sign",
    )
    status: str = Field(
        ...,
        description="Current status of the request",
    )
    sent_at: datetime = Field(
        ...,
        description="Timestamp when the request was sent",
    )
    viewed_at: Optional[datetime] = Field(
        default=None,
        description="Timestamp when the document was viewed by signer",
    )
    completed_at: Optional[datetime] = Field(
        default=None,
        description="Timestamp when the signature was completed",
    )
    expires_at: Optional[datetime] = Field(
        default=None,
        description="Timestamp when the request expires",
    )
    notification_sent: bool = Field(
        ...,
        description="Whether notification was successfully sent",
    )
    notification_method: Optional[str] = Field(
        default=None,
        description="Method used for notification",
    )
    message: Optional[str] = Field(
        default=None,
        description="Optional message from requester to signer",
    )


class DocumentStatusCount(BaseSchema):
    """Count of documents by status.

    Attributes:
        status: Document status (draft/pending/signed/expired)
        count: Number of documents with this status
    """

    status: str = Field(
        ...,
        description="Document status",
    )
    count: int = Field(
        ...,
        ge=0,
        description="Number of documents with this status",
    )


class DocumentTypeCount(BaseSchema):
    """Count of documents by type.

    Attributes:
        type: Document type/category
        count: Number of documents of this type
        pending: Number of pending documents of this type
        signed: Number of signed documents of this type
    """

    type: str = Field(
        ...,
        description="Document type/category",
    )
    count: int = Field(
        ...,
        ge=0,
        description="Number of documents of this type",
    )
    pending: int = Field(
        default=0,
        ge=0,
        description="Number of pending documents of this type",
    )
    signed: int = Field(
        default=0,
        ge=0,
        description="Number of signed documents of this type",
    )


class SignatureActivity(BaseSchema):
    """Recent signature activity.

    Attributes:
        document_id: ID of the signed document
        document_title: Title of the signed document
        signer_id: ID of the person who signed
        signed_at: Timestamp of the signature
        document_type: Type of the document
    """

    document_id: UUID = Field(
        ...,
        description="ID of the signed document",
    )
    document_title: str = Field(
        ...,
        description="Title of the signed document",
    )
    signer_id: UUID = Field(
        ...,
        description="ID of the person who signed",
    )
    signed_at: datetime = Field(
        ...,
        description="Timestamp of the signature",
    )
    document_type: str = Field(
        ...,
        description="Type of the document",
    )


class SignatureDashboardSummary(BaseSchema):
    """Summary statistics for signature dashboard.

    Attributes:
        total_documents: Total number of documents
        pending_signatures: Number of documents pending signature
        signed_documents: Number of signed documents
        expired_documents: Number of expired documents
        draft_documents: Number of draft documents
        completion_rate: Percentage of documents that are signed
        documents_this_month: Number of documents created this month
        signatures_this_month: Number of signatures completed this month
    """

    total_documents: int = Field(
        ...,
        ge=0,
        description="Total number of documents",
    )
    pending_signatures: int = Field(
        ...,
        ge=0,
        description="Number of documents pending signature",
    )
    signed_documents: int = Field(
        ...,
        ge=0,
        description="Number of signed documents",
    )
    expired_documents: int = Field(
        default=0,
        ge=0,
        description="Number of expired documents",
    )
    draft_documents: int = Field(
        default=0,
        ge=0,
        description="Number of draft documents",
    )
    completion_rate: float = Field(
        ...,
        ge=0.0,
        le=100.0,
        description="Percentage of documents that are signed",
    )
    documents_this_month: int = Field(
        default=0,
        ge=0,
        description="Number of documents created this month",
    )
    signatures_this_month: int = Field(
        default=0,
        ge=0,
        description="Number of signatures completed this month",
    )


class SignatureDashboardResponse(BaseResponse):
    """Response schema for signature status dashboard.

    Provides aggregated statistics, breakdowns by status and type,
    and recent signature activity.

    Attributes:
        summary: High-level summary statistics
        status_breakdown: Count of documents by status
        type_breakdown: Count of documents by type
        recent_activity: List of recent signatures
        alerts: List of important notifications (e.g., pending documents)
        generated_at: Timestamp when dashboard data was generated
    """

    summary: SignatureDashboardSummary = Field(
        ...,
        description="High-level summary statistics",
    )
    status_breakdown: list[DocumentStatusCount] = Field(
        ...,
        description="Count of documents by status",
    )
    type_breakdown: list[DocumentTypeCount] = Field(
        ...,
        description="Count of documents by type",
    )
    recent_activity: list[SignatureActivity] = Field(
        ...,
        description="List of recent signatures",
    )
    alerts: list[str] = Field(
        default_factory=list,
        description="List of important notifications",
    )
    generated_at: datetime = Field(
        ...,
        description="Timestamp when dashboard data was generated",
    )


# Document Audit Log Schemas


class DocumentAuditLogBase(BaseSchema):
    """Base schema for document audit log data.

    Attributes:
        event_type: Type of event that occurred
        document_id: ID of the document related to this event
        user_id: User who triggered the event
        signature_id: Optional ID of the signature (for signature events)
        signature_request_id: Optional ID of the signature request
        event_data: Additional event-specific data as dictionary
        ip_address: IP address from which the event was triggered
        user_agent: Browser/device user agent string
    """

    event_type: str = Field(
        ...,
        description="Type of audit event",
    )
    document_id: UUID = Field(
        ...,
        description="Document ID related to this event",
    )
    user_id: UUID = Field(
        ...,
        description="User who triggered the event",
    )
    signature_id: Optional[UUID] = Field(
        default=None,
        description="Signature ID (for signature events)",
    )
    signature_request_id: Optional[UUID] = Field(
        default=None,
        description="Signature request ID",
    )
    event_data: Optional[dict[str, Any]] = Field(
        default=None,
        description="Additional event-specific data",
    )
    ip_address: Optional[str] = Field(
        default=None,
        max_length=45,
        description="IP address of the user",
    )
    user_agent: Optional[str] = Field(
        default=None,
        description="Browser/device user agent string",
    )


class DocumentAuditLogCreate(DocumentAuditLogBase):
    """Schema for creating a document audit log entry.

    Used internally by the service layer to create audit log records.
    """

    pass


class DocumentAuditLogResponse(DocumentAuditLogBase, BaseResponse):
    """Schema for document audit log response.

    Includes all audit log data plus timestamps.

    Attributes:
        id: Unique identifier for the audit log entry
        timestamp: When the event occurred
        created_at: Timestamp when the audit record was created
    """

    id: UUID = Field(
        ...,
        description="Unique identifier for the audit log entry",
    )
    timestamp: datetime = Field(
        ...,
        description="When the event occurred",
    )
    created_at: datetime = Field(
        ...,
        description="When the audit record was created",
    )


class DocumentAuditLogListResponse(PaginatedResponse):
    """Paginated response schema for listing document audit logs.

    Attributes:
        items: List of audit log entries
        total: Total number of audit logs matching the query
        skip: Number of records skipped
        limit: Maximum number of records returned
    """

    items: list[DocumentAuditLogResponse] = Field(
        ...,
        description="List of audit log entries",
    )
