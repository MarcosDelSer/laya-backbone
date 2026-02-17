"""Document service for LAYA AI Service.

Provides business logic for document template management, document creation,
and signature workflows. Implements CRUD operations and document lifecycle management.
"""

import json
import logging
from datetime import datetime, timedelta, timezone
from typing import Optional
from uuid import UUID

from sqlalchemy import and_, case, cast, func, or_, select, String
from sqlalchemy.ext.asyncio import AsyncSession

from app.models.document import (
    Document,
    DocumentAuditEventType,
    DocumentAuditLog,
    DocumentStatus,
    DocumentTemplate,
    DocumentType,
    Signature,
    SignatureRequest,
    SignatureRequestStatus,
)
from app.schemas.document import (
    DocumentAuditLogCreate,
    DocumentAuditLogResponse,
    DocumentCreate,
    DocumentResponse,
    DocumentTemplateCreate,
    DocumentTemplateResponse,
    DocumentTemplateUpdate,
    DocumentUpdate,
    SignatureCreate,
    SignatureRequestCreate,
    SignatureRequestResponse,
    SignatureResponse,
)

logger = logging.getLogger(__name__)


# =============================================================================
# Exception Classes
# =============================================================================


class DocumentServiceError(Exception):
    """Base exception for document service errors."""

    pass


class UnauthorizedAccessError(DocumentServiceError):
    """Raised when the user does not have permission to access a resource."""

    pass


# =============================================================================
# Document Service
# =============================================================================


class DocumentService:
    """Service class for document and template management logic.

    Encapsulates business logic for managing document templates, creating
    documents from templates, and handling signature workflows.

    Attributes:
        db: Async database session for database operations.
    """

    def __init__(self, db: AsyncSession) -> None:
        """Initialize DocumentService with database session.

        Args:
            db: Async database session for database operations.
        """
        self.db = db

    # Document Template Methods

    async def create_template(
        self, template_data: DocumentTemplateCreate
    ) -> DocumentTemplate:
        """Create a new document template.

        Args:
            template_data: Document template creation data.

        Returns:
            The created DocumentTemplate instance.
        """
        # Serialize required_fields to JSON if provided
        required_fields_json = None
        if template_data.required_fields:
            required_fields_json = json.dumps(template_data.required_fields)

        template = DocumentTemplate(
            name=template_data.name,
            type=DocumentType(template_data.type),
            description=template_data.description,
            template_content=template_data.template_content,
            required_fields=required_fields_json,
            created_by=template_data.created_by,
        )

        self.db.add(template)
        await self.db.commit()
        await self.db.refresh(template)
        return template

    async def get_template_by_id(
        self, template_id: UUID
    ) -> Optional[DocumentTemplate]:
        """Retrieve a document template by ID.

        Args:
            template_id: Unique identifier of the template.

        Returns:
            DocumentTemplate if found, None otherwise.
        """
        from sqlalchemy import cast, String

        query = select(DocumentTemplate).where(
            cast(DocumentTemplate.id, String) == str(template_id)
        )
        result = await self.db.execute(query)
        return result.scalar_one_or_none()

    async def list_templates(
        self,
        skip: int = 0,
        limit: int = 100,
        template_type: Optional[str] = None,
        is_active: Optional[bool] = True,
    ) -> tuple[list[DocumentTemplate], int]:
        """List document templates with optional filtering and pagination.

        Args:
            skip: Number of records to skip.
            limit: Maximum number of records to return.
            template_type: Optional filter by document type.
            is_active: Optional filter by active status.

        Returns:
            Tuple of (list of templates, total count).
        """
        query = select(DocumentTemplate)

        if is_active is not None:
            query = query.where(DocumentTemplate.is_active == is_active)

        if template_type:
            query = query.where(
                DocumentTemplate.type == DocumentType(template_type)
            )

        # Get total count
        count_query = select(func.count()).select_from(query.subquery())
        count_result = await self.db.execute(count_query)
        total = count_result.scalar() or 0

        # Apply pagination
        query = (
            query.offset(skip)
            .limit(limit)
            .order_by(DocumentTemplate.created_at.desc())
        )

        result = await self.db.execute(query)
        templates = list(result.scalars().all())

        return templates, total

    async def update_template(
        self, template_id: UUID, update_data: DocumentTemplateUpdate
    ) -> Optional[DocumentTemplate]:
        """Update a document template.

        Args:
            template_id: ID of the template to update.
            update_data: Fields to update.

        Returns:
            Updated DocumentTemplate if found, None otherwise.
        """
        template = await self.get_template_by_id(template_id)
        if not template:
            return None

        # Update fields if provided
        update_dict = update_data.model_dump(exclude_unset=True)

        for field, value in update_dict.items():
            if field == "required_fields" and value is not None:
                # Serialize required_fields to JSON
                setattr(template, field, json.dumps(value))
            elif hasattr(template, field):
                setattr(template, field, value)

        # Increment version on content update
        if "template_content" in update_dict:
            template.version += 1

        await self.db.commit()
        await self.db.refresh(template)
        return template

    async def delete_template(self, template_id: UUID) -> bool:
        """Soft delete a document template by marking it inactive.

        Args:
            template_id: ID of the template to delete.

        Returns:
            True if template was found and deleted, False otherwise.
        """
        template = await self.get_template_by_id(template_id)
        if not template:
            return False

        template.is_active = False
        await self.db.commit()
        return True

    def _template_to_response(
        self, template: DocumentTemplate
    ) -> DocumentTemplateResponse:
        """Convert DocumentTemplate model to DocumentTemplateResponse schema.

        Args:
            template: The DocumentTemplate model instance.

        Returns:
            DocumentTemplateResponse schema instance.
        """
        # Deserialize required_fields from JSON
        required_fields = None
        if template.required_fields:
            try:
                required_fields = json.loads(template.required_fields)
            except json.JSONDecodeError:
                required_fields = []

        return DocumentTemplateResponse(
            id=template.id,
            name=template.name,
            type=template.type.value,
            description=template.description,
            template_content=template.template_content,
            required_fields=required_fields,
            is_active=template.is_active,
            version=template.version,
            created_by=template.created_by,
            created_at=template.created_at,
            updated_at=template.updated_at,
        )

    # Document Methods

    async def create_document(self, document_data: DocumentCreate) -> Document:
        """Create a new document.

        Args:
            document_data: Document creation data.

        Returns:
            The created Document instance.
        """
        document = Document(
            type=DocumentType(document_data.type),
            title=document_data.title,
            content_url=document_data.content_url,
            created_by=document_data.created_by,
        )

        self.db.add(document)
        await self.db.commit()
        await self.db.refresh(document)

        # Create audit log entry
        await self.create_audit_log(
            event_type=DocumentAuditEventType.DOCUMENT_CREATED,
            document_id=document.id,
            user_id=document.created_by,
            event_data={
                "document_type": document.type.value,
                "title": document.title,
                "status": document.status.value,
            },
        )

        return document

    async def create_document_from_template(
        self,
        template_id: UUID,
        title: str,
        content_url: str,
        created_by: UUID,
    ) -> Optional[Document]:
        """Create a new document from a template.

        Args:
            template_id: ID of the template to use.
            title: Title for the new document.
            content_url: URL where the generated document content is stored.
            created_by: User ID creating the document.

        Returns:
            Created Document if template exists, None otherwise.
        """
        template = await self.get_template_by_id(template_id)
        if not template or not template.is_active:
            return None

        document = Document(
            type=template.type,
            title=title,
            content_url=content_url,
            created_by=created_by,
        )

        self.db.add(document)
        await self.db.commit()
        await self.db.refresh(document)

        # Create audit log entry
        await self.create_audit_log(
            event_type=DocumentAuditEventType.TEMPLATE_USED,
            document_id=document.id,
            user_id=created_by,
            event_data={
                "template_id": str(template_id),
                "template_name": template.name,
                "document_type": document.type.value,
                "title": title,
            },
        )

        return document

    async def get_document_by_id(
        self, document_id: UUID, user_id: UUID
    ) -> Optional[Document]:
        """Retrieve a document by ID.

        Args:
            document_id: Unique identifier of the document.
            user_id: ID of the user requesting the document.

        Returns:
            Document if found, None otherwise.

        Raises:
            UnauthorizedAccessError: When the user doesn't have access.
        """
        from sqlalchemy import cast, String

        query = select(Document).where(
            cast(Document.id, String) == str(document_id)
        )
        result = await self.db.execute(query)
        document = result.scalar_one_or_none()

        if not document:
            return None

        # Verify user has access to the document
        if not self._user_has_document_access(document, user_id):
            raise UnauthorizedAccessError(
                "User does not have permission to access this document"
            )

        return document

    async def list_documents(
        self,
        skip: int = 0,
        limit: int = 100,
        document_type: Optional[str] = None,
        status: Optional[str] = None,
        created_by: Optional[UUID] = None,
    ) -> tuple[list[Document], int]:
        """List documents with optional filtering and pagination.

        Args:
            skip: Number of records to skip.
            limit: Maximum number of records to return.
            document_type: Optional filter by document type.
            status: Optional filter by document status.
            created_by: Optional filter by creator user ID.

        Returns:
            Tuple of (list of documents, total count).
        """
        query = select(Document)

        if document_type:
            query = query.where(Document.type == DocumentType(document_type))

        if status:
            query = query.where(Document.status == DocumentStatus(status))

        if created_by:
            from sqlalchemy import cast, String

            query = query.where(
                cast(Document.created_by, String) == str(created_by)
            )

        # Get total count
        count_query = select(func.count()).select_from(query.subquery())
        count_result = await self.db.execute(count_query)
        total = count_result.scalar() or 0

        # Apply pagination
        query = query.offset(skip).limit(limit).order_by(Document.created_at.desc())

        result = await self.db.execute(query)
        documents = list(result.scalars().all())

        return documents, total

    async def update_document(
        self, document_id: UUID, update_data: DocumentUpdate, user_id: UUID
    ) -> Optional[Document | str]:
        """Update a document.

        Signed documents are immutable and cannot be updated.

        Args:
            document_id: ID of the document to update.
            update_data: Fields to update.
            user_id: ID of the user updating the document.

        Returns:
            Updated Document if found and updatable.
            None if document not found.
            "immutable" string if document is signed and cannot be modified.

        Raises:
            UnauthorizedAccessError: When the user doesn't have access.
        """
        document = await self.get_document_by_id(document_id, user_id)
        if not document:
            return None

        # Enforce immutability: signed documents cannot be modified
        if document.status == DocumentStatus.SIGNED:
            logger.warning(
                f"Attempted to update signed document {document_id}. "
                "Signed documents are immutable."
            )
            return "immutable"

        # Track old values for audit logging
        old_status = document.status
        update_dict = update_data.model_dump(exclude_unset=True)

        for field, value in update_dict.items():
            if field == "status":
                setattr(document, field, DocumentStatus(value))
            elif hasattr(document, field):
                setattr(document, field, value)

        await self.db.commit()
        await self.db.refresh(document)

        # Create audit log entry
        event_type = DocumentAuditEventType.DOCUMENT_UPDATED
        event_data = {"updated_fields": list(update_dict.keys())}

        # If status changed, log it specifically
        if "status" in update_dict and old_status != document.status:
            event_type = DocumentAuditEventType.DOCUMENT_STATUS_CHANGED
            event_data["old_status"] = old_status.value
            event_data["new_status"] = document.status.value

        await self.create_audit_log(
            event_type=event_type,
            document_id=document.id,
            user_id=document.created_by,
            event_data=event_data,
        )

        return document

    def _document_to_response(self, document: Document) -> DocumentResponse:
        """Convert Document model to DocumentResponse schema.

        Args:
            document: The Document model instance.

        Returns:
            DocumentResponse schema instance.
        """
        return DocumentResponse(
            id=document.id,
            type=document.type.value,
            title=document.title,
            content_url=document.content_url,
            status=document.status.value,
            created_by=document.created_by,
            created_at=document.created_at,
            updated_at=document.updated_at,
        )

    # Signature Methods

    async def create_signature(
        self, signature_data: SignatureCreate
    ) -> Optional[Signature]:
        """Create a signature for a document.

        Automatically updates document status to SIGNED and completes any
        pending signature request for this document.

        Args:
            signature_data: Signature creation data.

        Returns:
            Created Signature if document exists, None otherwise.
        """
        # Verify document exists
        document = await self.get_document_by_id(signature_data.document_id)
        if not document:
            return None

        # Track old status for audit
        old_status = document.status

        # Create signature
        signature = Signature(
            document_id=signature_data.document_id,
            signer_id=signature_data.signer_id,
            signature_image_url=signature_data.signature_image_url,
            ip_address=signature_data.ip_address,
            device_info=signature_data.device_info,
        )

        self.db.add(signature)

        # Update document status to SIGNED
        document.status = DocumentStatus.SIGNED

        await self.db.commit()
        await self.db.refresh(signature)

        # Create audit log entry for signature
        await self.create_audit_log(
            event_type=DocumentAuditEventType.SIGNATURE_CREATED,
            document_id=signature.document_id,
            user_id=signature.signer_id,
            signature_id=signature.id,
            event_data={
                "old_status": old_status.value,
                "new_status": DocumentStatus.SIGNED.value,
                "document_title": document.title,
            },
            ip_address=signature.ip_address,
            user_agent=signature.device_info,
        )

        # Complete any pending signature request for this document
        signature_request = await self.get_signature_request_by_document(
            signature_data.document_id
        )
        if signature_request and signature_request.status in (
            SignatureRequestStatus.SENT,
            SignatureRequestStatus.VIEWED,
        ):
            await self.complete_signature_request(
                request_id=signature_request.id,
                signature=signature,
            )

        return signature

    async def get_signatures_for_document(
        self, document_id: UUID
    ) -> list[Signature]:
        """Get all signatures for a document.

        Args:
            document_id: ID of the document.

        Returns:
            List of Signature instances for the document.
        """
        from sqlalchemy import cast, String

        query = select(Signature).where(
            cast(Signature.document_id, String) == str(document_id)
        )
        result = await self.db.execute(query)
        return list(result.scalars().all())

    def _signature_to_response(self, signature: Signature) -> SignatureResponse:
        """Convert Signature model to SignatureResponse schema.

        Args:
            signature: The Signature model instance.

        Returns:
            SignatureResponse schema instance.
        """
        return SignatureResponse(
            id=signature.id,
            document_id=signature.document_id,
            signer_id=signature.signer_id,
            signature_image_url=signature.signature_image_url,
            ip_address=signature.ip_address,
            device_info=signature.device_info,
            timestamp=signature.timestamp,
            created_at=signature.created_at,
            updated_at=signature.updated_at,
        )

    # Signature Request Workflow Methods

    async def send_signature_request(
        self,
        request_data: SignatureRequestCreate,
        requester_id: UUID,
    ) -> Optional[SignatureRequest]:
        """Send a signature request for a document.

        This initiates the signature request workflow:
        1. Validates document exists and is in DRAFT status
        2. Updates document status to PENDING
        3. Creates a signature request record
        4. Sends notification to signer (placeholder for integration)
        5. Returns the signature request

        Args:
            request_data: Signature request creation data.
            requester_id: User ID of the person requesting the signature.

        Returns:
            Created SignatureRequest if successful, None if document not found.
        """
        # Verify document exists and is in DRAFT status
        document = await self.get_document_by_id(request_data.document_id)
        if not document:
            logger.warning(
                f"Cannot send signature request: Document {request_data.document_id} not found"
            )
            return None

        if document.status != DocumentStatus.DRAFT:
            logger.warning(
                f"Cannot send signature request: Document {request_data.document_id} "
                f"is in {document.status.value} status, expected DRAFT"
            )
            return None

        # Calculate expiration date
        expires_at = datetime.now(timezone.utc) + timedelta(
            days=request_data.expires_in_days
        )

        # Create signature request
        signature_request = SignatureRequest(
            document_id=request_data.document_id,
            requester_id=requester_id,
            signer_id=request_data.signer_id,
            status=SignatureRequestStatus.SENT,
            expires_at=expires_at,
            message=request_data.message,
        )

        # Send notification (placeholder for integration with notification service)
        notification_success = await self._send_signature_notification(
            document=document,
            signer_id=request_data.signer_id,
            requester_id=requester_id,
            message=request_data.message,
        )

        signature_request.notification_sent = notification_success
        signature_request.notification_method = "email" if notification_success else None

        # Update document status to PENDING
        document.status = DocumentStatus.PENDING

        self.db.add(signature_request)
        await self.db.commit()
        await self.db.refresh(signature_request)

        logger.info(
            f"Signature request {signature_request.id} created for document "
            f"{document.id} by requester {requester_id} to signer {request_data.signer_id}"
        )

        # Create audit log entry
        await self.create_audit_log(
            event_type=DocumentAuditEventType.SIGNATURE_REQUEST_SENT,
            document_id=signature_request.document_id,
            user_id=requester_id,
            signature_request_id=signature_request.id,
            event_data={
                "signer_id": str(request_data.signer_id),
                "expires_at": signature_request.expires_at.isoformat() if signature_request.expires_at else None,
                "notification_sent": notification_success,
                "message": request_data.message,
            },
        )

        return signature_request

    async def mark_request_viewed(
        self, request_id: UUID
    ) -> Optional[SignatureRequest]:
        """Mark a signature request as viewed.

        Updates the signature request status and records the viewed timestamp.

        Args:
            request_id: ID of the signature request.

        Returns:
            Updated SignatureRequest if found, None otherwise.
        """
        query = select(SignatureRequest).where(
            cast(SignatureRequest.id, String) == str(request_id)
        )
        result = await self.db.execute(query)
        signature_request = result.scalar_one_or_none()

        if not signature_request:
            return None

        # Only update if not already viewed
        if signature_request.status == SignatureRequestStatus.SENT:
            signature_request.status = SignatureRequestStatus.VIEWED
            signature_request.viewed_at = datetime.now(timezone.utc)

            await self.db.commit()
            await self.db.refresh(signature_request)

            logger.info(f"Signature request {request_id} marked as viewed")

            # Create audit log entry
            await self.create_audit_log(
                event_type=DocumentAuditEventType.SIGNATURE_REQUEST_VIEWED,
                document_id=signature_request.document_id,
                user_id=signature_request.signer_id,
                signature_request_id=signature_request.id,
                event_data={
                    "requester_id": str(signature_request.requester_id),
                    "viewed_at": signature_request.viewed_at.isoformat(),
                },
            )

        return signature_request

    async def complete_signature_request(
        self,
        request_id: UUID,
        signature: Signature,
    ) -> Optional[SignatureRequest]:
        """Complete a signature request after signature is created.

        Updates the signature request status to completed and records completion timestamp.
        This is called automatically after a signature is created.

        Args:
            request_id: ID of the signature request.
            signature: The created Signature instance.

        Returns:
            Updated SignatureRequest if found, None otherwise.
        """
        query = select(SignatureRequest).where(
            cast(SignatureRequest.id, String) == str(request_id)
        )
        result = await self.db.execute(query)
        signature_request = result.scalar_one_or_none()

        if not signature_request:
            return None

        signature_request.status = SignatureRequestStatus.COMPLETED
        signature_request.completed_at = datetime.now(timezone.utc)

        await self.db.commit()
        await self.db.refresh(signature_request)

        # Send completion notification (placeholder)
        await self._send_completion_notification(
            signature_request=signature_request,
            signature=signature,
        )

        logger.info(f"Signature request {request_id} marked as completed")

        # Create audit log entry
        await self.create_audit_log(
            event_type=DocumentAuditEventType.SIGNATURE_REQUEST_COMPLETED,
            document_id=signature_request.document_id,
            user_id=signature_request.signer_id,
            signature_id=signature.id,
            signature_request_id=signature_request.id,
            event_data={
                "requester_id": str(signature_request.requester_id),
                "completed_at": signature_request.completed_at.isoformat(),
            },
        )

        return signature_request

    async def get_signature_request_by_id(
        self, request_id: UUID
    ) -> Optional[SignatureRequest]:
        """Retrieve a signature request by ID.

        Args:
            request_id: Unique identifier of the signature request.

        Returns:
            SignatureRequest if found, None otherwise.
        """
        query = select(SignatureRequest).where(
            cast(SignatureRequest.id, String) == str(request_id)
        )
        result = await self.db.execute(query)
        return result.scalar_one_or_none()

    async def get_signature_request_by_document(
        self, document_id: UUID
    ) -> Optional[SignatureRequest]:
        """Retrieve the most recent signature request for a document.

        Args:
            document_id: ID of the document.

        Returns:
            Most recent SignatureRequest if found, None otherwise.
        """
        query = (
            select(SignatureRequest)
            .where(cast(SignatureRequest.document_id, String) == str(document_id))
            .order_by(SignatureRequest.created_at.desc())
            .limit(1)
        )
        result = await self.db.execute(query)
        return result.scalar_one_or_none()

    async def list_signature_requests_for_signer(
        self,
        signer_id: UUID,
        status: Optional[str] = None,
        skip: int = 0,
        limit: int = 100,
    ) -> tuple[list[SignatureRequest], int]:
        """List signature requests for a specific signer.

        Args:
            signer_id: User ID of the signer.
            status: Optional filter by request status.
            skip: Number of records to skip.
            limit: Maximum number of records to return.

        Returns:
            Tuple of (list of signature requests, total count).
        """
        query = select(SignatureRequest).where(
            cast(SignatureRequest.signer_id, String) == str(signer_id)
        )

        if status:
            query = query.where(
                SignatureRequest.status == SignatureRequestStatus(status)
            )

        # Get total count
        count_query = select(func.count()).select_from(query.subquery())
        count_result = await self.db.execute(count_query)
        total = count_result.scalar() or 0

        # Apply pagination
        query = (
            query.offset(skip)
            .limit(limit)
            .order_by(SignatureRequest.created_at.desc())
        )

        result = await self.db.execute(query)
        requests = list(result.scalars().all())

        return requests, total

    async def _send_signature_notification(
        self,
        document: Document,
        signer_id: UUID,
        requester_id: UUID,
        message: Optional[str],
    ) -> bool:
        """Send notification to signer about signature request.

        This is a placeholder for integration with notification service (task 032).
        Currently logs the notification event.

        Args:
            document: The document requiring signature.
            signer_id: User ID of the person who should sign.
            requester_id: User ID of the person requesting the signature.
            message: Optional message from requester.

        Returns:
            True if notification sent successfully, False otherwise.
        """
        # TODO: Integrate with notification service (task 032)
        logger.info(
            f"[NOTIFICATION PLACEHOLDER] Signature request for document {document.id} "
            f"sent to signer {signer_id} by requester {requester_id}. "
            f"Document: {document.title}, Message: {message}"
        )

        # For now, always return True (placeholder)
        # In production, this would call the notification service
        return True

    async def _send_completion_notification(
        self,
        signature_request: SignatureRequest,
        signature: Signature,
    ) -> bool:
        """Send notification about completed signature.

        This is a placeholder for integration with notification service (task 032).
        Currently logs the notification event.

        Args:
            signature_request: The completed signature request.
            signature: The created signature.

        Returns:
            True if notification sent successfully, False otherwise.
        """
        # TODO: Integrate with notification service (task 032)
        logger.info(
            f"[NOTIFICATION PLACEHOLDER] Signature request {signature_request.id} "
            f"completed. Document {signature_request.document_id} signed by "
            f"{signature.signer_id}. Notifying requester {signature_request.requester_id}."
        )

        # For now, always return True (placeholder)
        # In production, this would call the notification service
        return True

    def _signature_request_to_response(
        self, signature_request: SignatureRequest
    ) -> SignatureRequestResponse:
        """Convert SignatureRequest model to SignatureRequestResponse schema.

        Args:
            signature_request: The SignatureRequest model instance.

        Returns:
            SignatureRequestResponse schema instance.
        """
        return SignatureRequestResponse(
            id=signature_request.id,
            document_id=signature_request.document_id,
            requester_id=signature_request.requester_id,
            signer_id=signature_request.signer_id,
            status=signature_request.status.value,
            sent_at=signature_request.sent_at,
            viewed_at=signature_request.viewed_at,
            completed_at=signature_request.completed_at,
            expires_at=signature_request.expires_at,
            notification_sent=signature_request.notification_sent,
            notification_method=signature_request.notification_method,
            message=signature_request.message,
            created_at=signature_request.created_at,
            updated_at=signature_request.updated_at,
        )

    async def get_signature_dashboard(
        self,
        user_id: Optional[UUID] = None,
        limit_recent: int = 10,
    ) -> dict:
        """Get aggregated signature status dashboard data.

        Provides comprehensive statistics about documents and signatures including:
        - Summary statistics (total, pending, signed, etc.)
        - Breakdown by document status
        - Breakdown by document type
        - Recent signature activity

        Args:
            user_id: Optional user ID to filter by (for user-specific dashboard)
            limit_recent: Maximum number of recent activities to return

        Returns:
            Dictionary with dashboard data including summary, breakdowns, and recent activity
        """
        now = datetime.now(timezone.utc)
        first_of_month = now.replace(day=1, hour=0, minute=0, second=0, microsecond=0)

        # Base query for filtering by user if provided
        base_filter = []
        if user_id:
            base_filter.append(Document.created_by == user_id)

        # Get total counts by status
        status_query = (
            select(
                Document.status,
                func.count(Document.id).label("count"),
            )
            .group_by(Document.status)
        )
        if base_filter:
            status_query = status_query.where(and_(*base_filter))

        status_result = await self.db.execute(status_query)
        status_counts = {row.status.value: row.count for row in status_result}

        # Calculate summary statistics
        total_documents = sum(status_counts.values())
        pending_signatures = status_counts.get("pending", 0)
        signed_documents = status_counts.get("signed", 0)
        expired_documents = status_counts.get("expired", 0)
        draft_documents = status_counts.get("draft", 0)

        # Calculate completion rate
        completable_docs = total_documents - draft_documents
        completion_rate = (
            (signed_documents / completable_docs * 100.0)
            if completable_docs > 0
            else 0.0
        )

        # Get documents created this month
        month_query = select(func.count(Document.id)).where(
            Document.created_at >= first_of_month
        )
        if base_filter:
            month_query = month_query.where(and_(*base_filter))
        month_result = await self.db.execute(month_query)
        documents_this_month = month_result.scalar() or 0

        # Get signatures created this month
        sig_month_query = select(func.count(Signature.id)).where(
            Signature.created_at >= first_of_month
        )
        if user_id:
            sig_month_query = sig_month_query.where(Signature.signer_id == user_id)
        sig_month_result = await self.db.execute(sig_month_query)
        signatures_this_month = sig_month_result.scalar() or 0

        # Get breakdown by document type with status counts
        # Use case() for correct SQL conditional aggregation
        type_query = (
            select(
                Document.type,
                func.count(Document.id).label("total"),
                func.sum(
                    case((Document.status == DocumentStatus.PENDING, 1), else_=0)
                ).label("pending"),
                func.sum(
                    case((Document.status == DocumentStatus.SIGNED, 1), else_=0)
                ).label("signed"),
            )
            .group_by(Document.type)
        )
        if base_filter:
            type_query = type_query.where(and_(*base_filter))

        type_result = await self.db.execute(type_query)
        type_breakdown = [
            {
                "type": row.type.value,
                "count": row.total,
                "pending": row.pending or 0,
                "signed": row.signed or 0,
            }
            for row in type_result
        ]

        # Get recent signature activity
        activity_query = (
            select(Signature, Document)
            .join(Document, Signature.document_id == Document.id)
            .order_by(Signature.created_at.desc())
            .limit(limit_recent)
        )
        if user_id:
            activity_query = activity_query.where(Signature.signer_id == user_id)

        activity_result = await self.db.execute(activity_query)
        recent_activity = [
            {
                "document_id": str(signature.document_id),
                "document_title": document.title,
                "signer_id": str(signature.signer_id),
                "signed_at": signature.created_at.isoformat(),
                "document_type": document.type.value,
            }
            for signature, document in activity_result
        ]

        # Generate alerts
        alerts = []
        if pending_signatures > 0:
            alerts.append(
                f"You have {pending_signatures} document{'s' if pending_signatures != 1 else ''} "
                f"awaiting signature"
            )
        if expired_documents > 0:
            alerts.append(
                f"{expired_documents} document{'s' if expired_documents != 1 else ''} "
                f"ha{'ve' if expired_documents != 1 else 's'} expired"
            )
        if completion_rate < 50 and completable_docs > 5:
            alerts.append(
                f"Signature completion rate is {completion_rate:.1f}%. "
                f"Consider following up on pending documents."
            )

        # Build status breakdown
        status_breakdown = [
            {"status": status, "count": count}
            for status, count in status_counts.items()
        ]

        return {
            "summary": {
                "total_documents": total_documents,
                "pending_signatures": pending_signatures,
                "signed_documents": signed_documents,
                "expired_documents": expired_documents,
                "draft_documents": draft_documents,
                "completion_rate": round(completion_rate, 2),
                "documents_this_month": documents_this_month,
                "signatures_this_month": signatures_this_month,
            },
            "status_breakdown": status_breakdown,
            "type_breakdown": type_breakdown,
            "recent_activity": recent_activity,
            "alerts": alerts,
            "generated_at": now.isoformat(),
        }

    # Audit Trail Methods

    async def create_audit_log(
        self,
        event_type: DocumentAuditEventType,
        document_id: UUID,
        user_id: UUID,
        signature_id: Optional[UUID] = None,
        signature_request_id: Optional[UUID] = None,
        event_data: Optional[dict] = None,
        ip_address: Optional[str] = None,
        user_agent: Optional[str] = None,
    ) -> DocumentAuditLog:
        """Create an audit log entry for a document or signature event.

        This method records all significant events in the document lifecycle
        for compliance, security, and debugging purposes.

        Args:
            event_type: Type of event that occurred.
            document_id: ID of the document related to this event.
            user_id: User who triggered the event.
            signature_id: Optional ID of the signature (for signature events).
            signature_request_id: Optional ID of the signature request.
            event_data: Optional dictionary with additional event-specific data.
            ip_address: IP address from which the event was triggered.
            user_agent: Browser/device user agent string.

        Returns:
            Created DocumentAuditLog instance.
        """
        # Serialize event_data to JSON if provided
        event_data_json = None
        if event_data:
            event_data_json = json.dumps(event_data)

        audit_log = DocumentAuditLog(
            event_type=event_type,
            document_id=document_id,
            user_id=user_id,
            signature_id=signature_id,
            signature_request_id=signature_request_id,
            event_data=event_data_json,
            ip_address=ip_address,
            user_agent=user_agent,
        )

        self.db.add(audit_log)
        await self.db.commit()
        await self.db.refresh(audit_log)

        logger.info(
            f"Audit log created: {event_type.value} for document {document_id} "
            f"by user {user_id}"
        )

        return audit_log

    async def get_audit_logs_for_document(
        self,
        document_id: UUID,
        skip: int = 0,
        limit: int = 100,
    ) -> tuple[list[DocumentAuditLog], int]:
        """Retrieve all audit logs for a specific document.

        Args:
            document_id: ID of the document.
            skip: Number of records to skip for pagination.
            limit: Maximum number of records to return.

        Returns:
            Tuple of (list of audit logs, total count).
        """
        query = select(DocumentAuditLog).where(
            cast(DocumentAuditLog.document_id, String) == str(document_id)
        )

        # Get total count
        count_query = select(func.count()).select_from(query.subquery())
        count_result = await self.db.execute(count_query)
        total = count_result.scalar() or 0

        # Apply pagination and ordering
        query = (
            query.offset(skip)
            .limit(limit)
            .order_by(DocumentAuditLog.timestamp.desc())
        )

        result = await self.db.execute(query)
        audit_logs = list(result.scalars().all())

        return audit_logs, total

    async def get_audit_logs_for_user(
        self,
        user_id: UUID,
        skip: int = 0,
        limit: int = 100,
        event_type: Optional[str] = None,
    ) -> tuple[list[DocumentAuditLog], int]:
        """Retrieve all audit logs for a specific user.

        Args:
            user_id: ID of the user.
            skip: Number of records to skip for pagination.
            limit: Maximum number of records to return.
            event_type: Optional filter by event type.

        Returns:
            Tuple of (list of audit logs, total count).
        """
        query = select(DocumentAuditLog).where(
            cast(DocumentAuditLog.user_id, String) == str(user_id)
        )

        if event_type:
            query = query.where(
                DocumentAuditLog.event_type == DocumentAuditEventType(event_type)
            )

        # Get total count
        count_query = select(func.count()).select_from(query.subquery())
        count_result = await self.db.execute(count_query)
        total = count_result.scalar() or 0

        # Apply pagination and ordering
        query = (
            query.offset(skip)
            .limit(limit)
            .order_by(DocumentAuditLog.timestamp.desc())
        )

        result = await self.db.execute(query)
        audit_logs = list(result.scalars().all())

        return audit_logs, total

    def _audit_log_to_response(
        self, audit_log: DocumentAuditLog
    ) -> DocumentAuditLogResponse:
        """Convert DocumentAuditLog model to DocumentAuditLogResponse schema.

        Args:
            audit_log: The DocumentAuditLog model instance.

        Returns:
            DocumentAuditLogResponse schema instance.
        """
        # Deserialize event_data from JSON
        event_data = None
        if audit_log.event_data:
            try:
                event_data = json.loads(audit_log.event_data)
            except json.JSONDecodeError:
                event_data = {}

        return DocumentAuditLogResponse(
            id=audit_log.id,
            event_type=audit_log.event_type.value,
            document_id=audit_log.document_id,
            user_id=audit_log.user_id,
            signature_id=audit_log.signature_id,
            signature_request_id=audit_log.signature_request_id,
            event_data=event_data,
            ip_address=audit_log.ip_address,
            user_agent=audit_log.user_agent,
            timestamp=audit_log.timestamp,
            created_at=audit_log.created_at,
        )

    def _user_has_document_access(
        self,
        document: Document,
        user_id: UUID,
    ) -> bool:
        """Check if a user has access to a document.

        User has access if they are the creator.

        Args:
            document: The document to check access for
            user_id: ID of the user to check

        Returns:
            True if user has access, False otherwise
        """
        # Creator always has access
        if str(document.created_by) == str(user_id):
            return True

        return False
