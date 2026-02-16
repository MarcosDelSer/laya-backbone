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
