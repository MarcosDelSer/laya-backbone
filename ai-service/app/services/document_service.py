"""Document service for LAYA AI Service.

Provides business logic for document template management, document creation,
and signature workflows. Implements CRUD operations and document lifecycle management.
"""

import json
from typing import Optional
from uuid import UUID

from sqlalchemy import and_, func, or_, select
from sqlalchemy.ext.asyncio import AsyncSession

from app.models.document import (
    Document,
    DocumentStatus,
    DocumentTemplate,
    DocumentType,
    Signature,
)
from app.schemas.document import (
    DocumentCreate,
    DocumentResponse,
    DocumentTemplateCreate,
    DocumentTemplateResponse,
    DocumentTemplateUpdate,
    DocumentUpdate,
    SignatureCreate,
    SignatureResponse,
)


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
        return document

    async def get_document_by_id(self, document_id: UUID) -> Optional[Document]:
        """Retrieve a document by ID.

        Args:
            document_id: Unique identifier of the document.

        Returns:
            Document if found, None otherwise.
        """
        from sqlalchemy import cast, String

        query = select(Document).where(
            cast(Document.id, String) == str(document_id)
        )
        result = await self.db.execute(query)
        return result.scalar_one_or_none()

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
        self, document_id: UUID, update_data: DocumentUpdate
    ) -> Optional[Document]:
        """Update a document.

        Args:
            document_id: ID of the document to update.
            update_data: Fields to update.

        Returns:
            Updated Document if found, None otherwise.
        """
        document = await self.get_document_by_id(document_id)
        if not document:
            return None

        # Update fields if provided
        update_dict = update_data.model_dump(exclude_unset=True)

        for field, value in update_dict.items():
            if field == "status":
                setattr(document, field, DocumentStatus(value))
            elif hasattr(document, field):
                setattr(document, field, value)

        await self.db.commit()
        await self.db.refresh(document)
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

        Automatically updates document status to SIGNED.

        Args:
            signature_data: Signature creation data.

        Returns:
            Created Signature if document exists, None otherwise.
        """
        # Verify document exists
        document = await self.get_document_by_id(signature_data.document_id)
        if not document:
            return None

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
