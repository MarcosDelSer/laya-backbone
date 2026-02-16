"""Document SQLAlchemy models for LAYA AI Service.

Defines database models for documents and e-signatures.
Documents represent files that require parent signatures.
"""

from datetime import datetime
from enum import Enum as PyEnum
from typing import Optional
from uuid import UUID, uuid4

from sqlalchemy import (
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


class DocumentStatus(str, PyEnum):
    """Status values for documents.

    Attributes:
        DRAFT: Document is in draft state, not yet sent for signature
        PENDING: Document has been sent to parent, awaiting signature
        SIGNED: Document has been signed by parent
        EXPIRED: Document signature request has expired
    """

    DRAFT = "draft"
    PENDING = "pending"
    SIGNED = "signed"
    EXPIRED = "expired"


class DocumentType(str, PyEnum):
    """Types of documents that can be signed.

    Attributes:
        ENROLLMENT: Enrollment forms
        PERMISSION: Permission slips
        POLICY: Policy acknowledgements
        MEDICAL: Medical authorization forms
        FINANCIAL: Financial agreements
        OTHER: Other document types
    """

    ENROLLMENT = "enrollment"
    PERMISSION = "permission"
    POLICY = "policy"
    MEDICAL = "medical"
    FINANCIAL = "financial"
    OTHER = "other"


class Document(Base):
    """SQLAlchemy model for documents requiring signatures.

    Represents a document that can be sent to parents for electronic signature.
    Tracks document metadata, status, and associated file location.

    Attributes:
        id: Unique identifier for the document
        type: Type/category of the document
        title: Human-readable title of the document
        content_url: URL or path to the actual document file
        status: Current status of the document (draft/pending/signed/expired)
        created_by: User ID of the person who created the document
        created_at: Timestamp when the record was created
        updated_at: Timestamp when the record was last updated
    """

    __tablename__ = "documents"

    id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        primary_key=True,
        default=uuid4,
    )
    type: Mapped[DocumentType] = mapped_column(
        Enum(DocumentType, name="document_type_enum", create_constraint=True),
        nullable=False,
        index=True,
    )
    title: Mapped[str] = mapped_column(
        String(255),
        nullable=False,
        index=True,
    )
    content_url: Mapped[str] = mapped_column(
        Text,
        nullable=False,
    )
    status: Mapped[DocumentStatus] = mapped_column(
        Enum(DocumentStatus, name="document_status_enum", create_constraint=True),
        nullable=False,
        default=DocumentStatus.DRAFT,
        index=True,
    )
    created_by: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        nullable=False,
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
    signatures: Mapped[list["Signature"]] = relationship(
        "Signature",
        back_populates="document",
        cascade="all, delete-orphan",
    )

    def __repr__(self) -> str:
        """Return string representation of the Document."""
        return f"<Document(id={self.id}, title='{self.title}', status={self.status.value})>"


class DocumentTemplate(Base):
    """SQLAlchemy model for document templates.

    Represents a reusable template for creating documents that require signatures.
    Templates define standard forms like enrollment, permission slips, medical forms, etc.

    Attributes:
        id: Unique identifier for the template
        name: Human-readable name of the template
        type: Type/category of documents created from this template
        description: Detailed description of the template purpose
        template_content: JSON structure or HTML content of the template
        required_fields: JSON array of field names that must be filled
        is_active: Whether the template is currently available for use
        version: Version number for tracking template updates
        created_by: User ID of the person who created the template
        created_at: Timestamp when the record was created
        updated_at: Timestamp when the record was last updated
    """

    __tablename__ = "document_templates"

    id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        primary_key=True,
        default=uuid4,
    )
    name: Mapped[str] = mapped_column(
        String(255),
        nullable=False,
        index=True,
    )
    type: Mapped[DocumentType] = mapped_column(
        Enum(DocumentType, name="document_type_enum", create_constraint=True),
        nullable=False,
        index=True,
    )
    description: Mapped[Optional[str]] = mapped_column(
        Text,
        nullable=True,
    )
    template_content: Mapped[str] = mapped_column(
        Text,
        nullable=False,
        comment="JSON structure or HTML content of the template",
    )
    required_fields: Mapped[Optional[str]] = mapped_column(
        Text,
        nullable=True,
        comment="JSON array of required field names",
    )
    is_active: Mapped[bool] = mapped_column(
        nullable=False,
        default=True,
        index=True,
    )
    version: Mapped[int] = mapped_column(
        nullable=False,
        default=1,
    )
    created_by: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        nullable=False,
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

    def __repr__(self) -> str:
        """Return string representation of the DocumentTemplate."""
        return f"<DocumentTemplate(id={self.id}, name='{self.name}', type={self.type.value}, version={self.version})>"


class Signature(Base):
    """SQLAlchemy model for document signatures.

    Represents a signature applied to a document by a parent or guardian.
    Tracks signature metadata including the signer, timestamp, device info, and audit trail.

    Attributes:
        id: Unique identifier for the signature
        document_id: ID of the document that was signed
        signer_id: User ID of the person who signed the document
        signature_image_url: URL or path to the stored signature image file
        ip_address: IP address from which the signature was submitted
        timestamp: Date and time when the signature was created
        device_info: Information about the device/browser used for signing
        created_at: Timestamp when the record was created
        updated_at: Timestamp when the record was last updated
    """

    __tablename__ = "signatures"

    id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        primary_key=True,
        default=uuid4,
    )
    document_id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        ForeignKey("documents.id", ondelete="CASCADE"),
        nullable=False,
        index=True,
    )
    signer_id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        nullable=False,
        index=True,
    )
    signature_image_url: Mapped[str] = mapped_column(
        Text,
        nullable=False,
    )
    ip_address: Mapped[str] = mapped_column(
        String(45),  # IPv6 max length is 45 characters
        nullable=False,
    )
    timestamp: Mapped[datetime] = mapped_column(
        DateTime(timezone=True),
        nullable=False,
        server_default=func.now(),
        index=True,
    )
    device_info: Mapped[Optional[str]] = mapped_column(
        Text,
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
    document: Mapped["Document"] = relationship(
        "Document",
        back_populates="signatures",
    )

    def __repr__(self) -> str:
        """Return string representation of the Signature."""
        return f"<Signature(id={self.id}, document_id={self.document_id}, signer_id={self.signer_id})>"


class SignatureRequestStatus(str, PyEnum):
    """Status values for signature requests.

    Attributes:
        SENT: Request has been sent to the signer
        VIEWED: Signer has viewed the document
        COMPLETED: Signer has completed signing
        CANCELLED: Request was cancelled
        EXPIRED: Request has expired
    """

    SENT = "sent"
    VIEWED = "viewed"
    COMPLETED = "completed"
    CANCELLED = "cancelled"
    EXPIRED = "expired"


class SignatureRequest(Base):
    """SQLAlchemy model for signature requests.

    Represents a request sent to a parent/guardian to sign a document.
    Tracks the signature request workflow from creation through completion.

    Attributes:
        id: Unique identifier for the signature request
        document_id: ID of the document requiring signature
        requester_id: User ID of the person requesting the signature
        signer_id: User ID of the person who should sign
        status: Current status of the request (sent/viewed/completed/cancelled/expired)
        sent_at: Timestamp when the request was sent
        viewed_at: Timestamp when the document was viewed by signer
        completed_at: Timestamp when the signature was completed
        expires_at: Timestamp when the request expires
        notification_sent: Whether notification was successfully sent
        notification_method: Method used for notification (email, push, etc.)
        message: Optional message from requester to signer
        created_at: Timestamp when the record was created
        updated_at: Timestamp when the record was last updated
    """

    __tablename__ = "signature_requests"

    id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        primary_key=True,
        default=uuid4,
    )
    document_id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        ForeignKey("documents.id", ondelete="CASCADE"),
        nullable=False,
        index=True,
    )
    requester_id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        nullable=False,
        index=True,
    )
    signer_id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        nullable=False,
        index=True,
    )
    status: Mapped[SignatureRequestStatus] = mapped_column(
        Enum(
            SignatureRequestStatus,
            name="signature_request_status_enum",
            create_constraint=True,
        ),
        nullable=False,
        default=SignatureRequestStatus.SENT,
        index=True,
    )
    sent_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True),
        nullable=False,
        server_default=func.now(),
        index=True,
    )
    viewed_at: Mapped[Optional[datetime]] = mapped_column(
        DateTime(timezone=True),
        nullable=True,
    )
    completed_at: Mapped[Optional[datetime]] = mapped_column(
        DateTime(timezone=True),
        nullable=True,
    )
    expires_at: Mapped[Optional[datetime]] = mapped_column(
        DateTime(timezone=True),
        nullable=True,
        index=True,
    )
    notification_sent: Mapped[bool] = mapped_column(
        nullable=False,
        default=False,
    )
    notification_method: Mapped[Optional[str]] = mapped_column(
        String(50),
        nullable=True,
    )
    message: Mapped[Optional[str]] = mapped_column(
        Text,
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
    document: Mapped["Document"] = relationship(
        "Document",
        foreign_keys=[document_id],
    )

    def __repr__(self) -> str:
        """Return string representation of the SignatureRequest."""
        return f"<SignatureRequest(id={self.id}, document_id={self.document_id}, signer_id={self.signer_id}, status={self.status.value})>"


class DocumentAuditEventType(str, PyEnum):
    """Types of audit events for document and signature workflows.

    Attributes:
        DOCUMENT_CREATED: Document was created
        DOCUMENT_UPDATED: Document metadata was updated
        DOCUMENT_STATUS_CHANGED: Document status was changed
        SIGNATURE_REQUEST_SENT: Signature request was sent to signer
        SIGNATURE_REQUEST_VIEWED: Signer viewed the document
        SIGNATURE_REQUEST_COMPLETED: Signature request was completed
        SIGNATURE_CREATED: Document was signed by a user
        TEMPLATE_USED: Document was created from a template
    """

    DOCUMENT_CREATED = "document_created"
    DOCUMENT_UPDATED = "document_updated"
    DOCUMENT_STATUS_CHANGED = "document_status_changed"
    SIGNATURE_REQUEST_SENT = "signature_request_sent"
    SIGNATURE_REQUEST_VIEWED = "signature_request_viewed"
    SIGNATURE_REQUEST_COMPLETED = "signature_request_completed"
    SIGNATURE_CREATED = "signature_created"
    TEMPLATE_USED = "template_used"


class DocumentAuditLog(Base):
    """SQLAlchemy model for document audit trail logging.

    Tracks all significant events in the document and signature lifecycle
    for compliance, security, and debugging purposes.

    Attributes:
        id: Unique identifier for the audit log entry
        event_type: Type of event that occurred
        document_id: ID of the document related to this event
        user_id: User who triggered the event
        signature_id: Optional ID of the signature (for signature events)
        signature_request_id: Optional ID of the signature request
        event_data: JSON object with additional event-specific data
        ip_address: IP address from which the event was triggered
        user_agent: Browser/device user agent string
        timestamp: When the event occurred
        created_at: Timestamp when the audit record was created
    """

    __tablename__ = "document_audit_logs"

    id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        primary_key=True,
        default=uuid4,
    )
    event_type: Mapped[DocumentAuditEventType] = mapped_column(
        Enum(
            DocumentAuditEventType,
            name="document_audit_event_type_enum",
            create_constraint=True,
        ),
        nullable=False,
        index=True,
    )
    document_id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        ForeignKey("documents.id", ondelete="CASCADE"),
        nullable=False,
        index=True,
    )
    user_id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        nullable=False,
        index=True,
    )
    signature_id: Mapped[Optional[UUID]] = mapped_column(
        PGUUID(as_uuid=True),
        ForeignKey("signatures.id", ondelete="SET NULL"),
        nullable=True,
        index=True,
    )
    signature_request_id: Mapped[Optional[UUID]] = mapped_column(
        PGUUID(as_uuid=True),
        ForeignKey("signature_requests.id", ondelete="SET NULL"),
        nullable=True,
        index=True,
    )
    event_data: Mapped[Optional[str]] = mapped_column(
        Text,
        nullable=True,
        comment="JSON object with event-specific details",
    )
    ip_address: Mapped[Optional[str]] = mapped_column(
        String(45),  # IPv6 max length
        nullable=True,
    )
    user_agent: Mapped[Optional[str]] = mapped_column(
        Text,
        nullable=True,
    )
    timestamp: Mapped[datetime] = mapped_column(
        DateTime(timezone=True),
        nullable=False,
        server_default=func.now(),
        index=True,
    )
    created_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True),
        nullable=False,
        server_default=func.now(),
    )

    # Relationships
    document: Mapped["Document"] = relationship(
        "Document",
        foreign_keys=[document_id],
    )
    signature: Mapped[Optional["Signature"]] = relationship(
        "Signature",
        foreign_keys=[signature_id],
    )
    signature_request: Mapped[Optional["SignatureRequest"]] = relationship(
        "SignatureRequest",
        foreign_keys=[signature_request_id],
    )

    def __repr__(self) -> str:
        """Return string representation of the DocumentAuditLog."""
        return f"<DocumentAuditLog(id={self.id}, event_type={self.event_type.value}, document_id={self.document_id}, user_id={self.user_id})>"
