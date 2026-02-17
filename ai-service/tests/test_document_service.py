"""Unit tests for DocumentService business logic.

Tests for document template management, document creation,
signature workflows, and audit logging functionality.
"""

from __future__ import annotations

from datetime import datetime, timedelta, timezone
from uuid import UUID, uuid4

import pytest
import pytest_asyncio
from sqlalchemy import text
from sqlalchemy.ext.asyncio import AsyncSession

from app.models.document import (
    Document,
    DocumentAuditEventType,
    DocumentStatus,
    DocumentTemplate,
    DocumentType,
    Signature,
    SignatureRequest,
    SignatureRequestStatus,
)
from app.schemas.document import (
    DocumentCreate,
    DocumentTemplateCreate,
    DocumentTemplateUpdate,
    DocumentUpdate,
    SignatureCreate,
    SignatureRequestCreate,
)
from app.services.document_service import DocumentService


# ============================================================================
# Document Template Tests
# ============================================================================


@pytest.mark.asyncio
async def test_create_template(db_session: AsyncSession, test_user_id: UUID):
    """Test creating a document template."""
    service = DocumentService(db_session)

    template_data = DocumentTemplateCreate(
        name="Standard Enrollment Template",
        type="enrollment",
        description="Template for enrollment forms",
        template_content="<html><body>{{childName}}</body></html>",
        required_fields=["childName", "parentName", "dateOfBirth"],
        created_by=test_user_id,
    )

    template = await service.create_template(template_data)

    assert template.id is not None
    assert template.name == "Standard Enrollment Template"
    assert template.type == DocumentType.ENROLLMENT
    assert template.description == "Template for enrollment forms"
    assert template.version == 1
    assert template.is_active is True
    assert template.created_by == test_user_id


@pytest.mark.asyncio
async def test_get_template_by_id(db_session: AsyncSession, test_user_id: UUID):
    """Test retrieving a template by ID."""
    service = DocumentService(db_session)

    # Create template first
    template_data = DocumentTemplateCreate(
        name="Test Template",
        type="permission",
        template_content="<html>Test</html>",
        created_by=test_user_id,
    )
    created = await service.create_template(template_data)

    # Retrieve it
    retrieved = await service.get_template_by_id(created.id)

    assert retrieved is not None
    assert retrieved.id == created.id
    assert retrieved.name == "Test Template"


@pytest.mark.asyncio
async def test_get_template_by_id_not_found(db_session: AsyncSession):
    """Test retrieving non-existent template returns None."""
    service = DocumentService(db_session)

    result = await service.get_template_by_id(uuid4())

    assert result is None


@pytest.mark.asyncio
async def test_list_templates_pagination(db_session: AsyncSession, test_user_id: UUID):
    """Test listing templates with pagination."""
    service = DocumentService(db_session)

    # Create 5 templates
    for i in range(5):
        template_data = DocumentTemplateCreate(
            name=f"Template {i}",
            type="enrollment",
            template_content=f"<html>Template {i}</html>",
            created_by=test_user_id,
        )
        await service.create_template(template_data)

    # Test first page
    first_page = await service.list_templates(skip=0, limit=2)
    assert len(first_page) == 2

    # Test second page
    second_page = await service.list_templates(skip=2, limit=2)
    assert len(second_page) == 2

    # Ensure different templates
    assert first_page[0].id != second_page[0].id


@pytest.mark.asyncio
async def test_update_template_increments_version(
    db_session: AsyncSession, test_user_id: UUID
):
    """Test that updating template increments version number."""
    service = DocumentService(db_session)

    # Create template (version 1)
    template_data = DocumentTemplateCreate(
        name="Original Template",
        type="medical",
        template_content="<html>v1</html>",
        created_by=test_user_id,
    )
    template = await service.create_template(template_data)
    assert template.version == 1

    # Update template
    update_data = DocumentTemplateUpdate(
        template_content="<html>v2 updated</html>"
    )
    updated = await service.update_template(template.id, update_data)

    assert updated is not None
    assert updated.version == 2
    assert updated.template_content == "<html>v2 updated</html>"


@pytest.mark.asyncio
async def test_delete_template(db_session: AsyncSession, test_user_id: UUID):
    """Test soft deleting a template (sets is_active=False)."""
    service = DocumentService(db_session)

    template_data = DocumentTemplateCreate(
        name="To Delete",
        type="financial",
        template_content="<html>Delete me</html>",
        created_by=test_user_id,
    )
    template = await service.create_template(template_data)
    assert template.is_active is True

    # Delete (soft delete)
    result = await service.delete_template(template.id)
    assert result is True

    # Verify it's inactive
    retrieved = await service.get_template_by_id(template.id)
    assert retrieved is not None
    assert retrieved.is_active is False


# ============================================================================
# Document CRUD Tests
# ============================================================================


@pytest.mark.asyncio
async def test_create_document(db_session: AsyncSession, test_user_id: UUID):
    """Test creating a document."""
    service = DocumentService(db_session)

    document_data = DocumentCreate(
        type="enrollment",
        title="Enrollment Form 2024",
        content_url="https://example.com/docs/enrollment-2024.pdf",
        created_by=test_user_id,
    )

    document = await service.create_document(document_data)

    assert document.id is not None
    assert document.type == DocumentType.ENROLLMENT
    assert document.title == "Enrollment Form 2024"
    assert document.status == DocumentStatus.DRAFT
    assert document.created_by == test_user_id


@pytest.mark.asyncio
async def test_create_document_from_template(
    db_session: AsyncSession, test_user_id: UUID
):
    """Test creating document from template."""
    service = DocumentService(db_session)

    # Create template first
    template_data = DocumentTemplateCreate(
        name="Enrollment Template",
        type="enrollment",
        template_content="<html>{{childName}}</html>",
        required_fields=["childName"],
        created_by=test_user_id,
    )
    template = await service.create_template(template_data)

    # Create document from template
    document_data = DocumentCreate(
        type="enrollment",
        title="Child Enrollment",
        content_url="https://example.com/child-enrollment.pdf",
        created_by=test_user_id,
    )

    document = await service.create_document_from_template(
        template_id=template.id,
        document_data=document_data,
        user_id=test_user_id,
    )

    assert document is not None
    assert document.type == DocumentType.ENROLLMENT
    assert document.title == "Child Enrollment"
    assert document.status == DocumentStatus.DRAFT


@pytest.mark.asyncio
async def test_get_document_by_id(db_session: AsyncSession, test_user_id: UUID):
    """Test retrieving a document by ID."""
    service = DocumentService(db_session)

    # Create document
    document_data = DocumentCreate(
        type="permission",
        title="Permission Slip",
        content_url="https://example.com/permission.pdf",
        created_by=test_user_id,
    )
    created = await service.create_document(document_data)

    # Retrieve it
    retrieved = await service.get_document_by_id(created.id)

    assert retrieved is not None
    assert retrieved.id == created.id
    assert retrieved.title == "Permission Slip"


@pytest.mark.asyncio
async def test_list_documents_with_filters(
    db_session: AsyncSession, test_user_id: UUID
):
    """Test listing documents with status filter."""
    service = DocumentService(db_session)

    # Create documents with different statuses
    for status in ["draft", "pending", "signed"]:
        document_data = DocumentCreate(
            type="enrollment",
            title=f"Document {status}",
            content_url=f"https://example.com/{status}.pdf",
            created_by=test_user_id,
        )
        doc = await service.create_document(document_data)

        # Update status for non-draft documents
        if status != "draft":
            update_data = DocumentUpdate(status=status)
            await service.update_document(doc.id, update_data, test_user_id)

    # Filter by status
    draft_docs = await service.list_documents(status=DocumentStatus.DRAFT)
    assert len(draft_docs) >= 1
    assert all(doc.status == DocumentStatus.DRAFT for doc in draft_docs)


@pytest.mark.asyncio
async def test_update_document_status(db_session: AsyncSession, test_user_id: UUID):
    """Test updating document status."""
    service = DocumentService(db_session)

    # Create document (starts as DRAFT)
    document_data = DocumentCreate(
        type="policy",
        title="Policy Document",
        content_url="https://example.com/policy.pdf",
        created_by=test_user_id,
    )
    document = await service.create_document(document_data)
    assert document.status == DocumentStatus.DRAFT

    # Update to PENDING
    update_data = DocumentUpdate(status="pending")
    updated = await service.update_document(document.id, update_data, test_user_id)

    assert updated is not None
    assert updated.status == DocumentStatus.PENDING


# ============================================================================
# Signature Tests
# ============================================================================


@pytest.mark.asyncio
async def test_create_signature_updates_document_status(
    db_session: AsyncSession, test_user_id: UUID
):
    """Test that creating signature updates document to SIGNED status."""
    service = DocumentService(db_session)

    # Create document (PENDING status)
    document_data = DocumentCreate(
        type="financial",
        title="Financial Agreement",
        content_url="https://example.com/agreement.pdf",
        created_by=test_user_id,
    )
    document = await service.create_document(document_data)

    # Update to PENDING first
    update_data = DocumentUpdate(status="pending")
    await service.update_document(document.id, update_data, test_user_id)

    # Create signature
    signature_data = SignatureCreate(
        document_id=document.id,
        signer_id=test_user_id,
        signature_image_url="https://example.com/signatures/sig123.png",
        ip_address="192.168.1.100",
        device_info="Mozilla/5.0 (iPhone)",
    )

    signature = await service.create_signature(signature_data, test_user_id)

    assert signature is not None
    assert signature.document_id == document.id
    assert signature.signer_id == test_user_id

    # Verify document status changed to SIGNED
    updated_doc = await service.get_document_by_id(document.id)
    assert updated_doc.status == DocumentStatus.SIGNED


@pytest.mark.asyncio
async def test_get_signatures_for_document(
    db_session: AsyncSession, test_user_id: UUID
):
    """Test retrieving all signatures for a document."""
    service = DocumentService(db_session)

    # Create document
    document_data = DocumentCreate(
        type="permission",
        title="Multi-Signature Doc",
        content_url="https://example.com/multi.pdf",
        created_by=test_user_id,
    )
    document = await service.create_document(document_data)

    # Update to PENDING
    update_data = DocumentUpdate(status="pending")
    await service.update_document(document.id, update_data, test_user_id)

    # Create 2 signatures
    for i in range(2):
        signature_data = SignatureCreate(
            document_id=document.id,
            signer_id=uuid4(),
            signature_image_url=f"https://example.com/sig{i}.png",
            ip_address=f"192.168.1.{100+i}",
        )
        await service.create_signature(signature_data, test_user_id)

    # Retrieve signatures
    signatures = await service.get_signatures_for_document(document.id)

    assert len(signatures) == 2


# ============================================================================
# Signature Request Workflow Tests
# ============================================================================


@pytest.mark.asyncio
async def test_send_signature_request(db_session: AsyncSession, test_user_id: UUID):
    """Test sending a signature request."""
    service = DocumentService(db_session)

    # Create document (DRAFT)
    document_data = DocumentCreate(
        type="enrollment",
        title="Enrollment Form",
        content_url="https://example.com/enrollment.pdf",
        created_by=test_user_id,
    )
    document = await service.create_document(document_data)

    # Send signature request
    signer_id = uuid4()
    expires_at = datetime.now(timezone.utc) + timedelta(days=7)

    request_data = SignatureRequestCreate(
        document_id=document.id,
        requester_id=test_user_id,
        signer_id=signer_id,
        expires_at=expires_at,
        message="Please sign this enrollment form",
    )

    sig_request = await service.send_signature_request(
        request_data, test_user_id, "127.0.0.1", "Test Agent"
    )

    assert sig_request is not None
    assert sig_request.status == SignatureRequestStatus.SENT
    assert sig_request.document_id == document.id
    assert sig_request.signer_id == signer_id

    # Verify document status changed to PENDING
    updated_doc = await service.get_document_by_id(document.id)
    assert updated_doc.status == DocumentStatus.PENDING


@pytest.mark.asyncio
async def test_mark_request_viewed(db_session: AsyncSession, test_user_id: UUID):
    """Test marking signature request as viewed."""
    service = DocumentService(db_session)

    # Create document and signature request
    document_data = DocumentCreate(
        type="permission",
        title="Permission Form",
        content_url="https://example.com/permission.pdf",
        created_by=test_user_id,
    )
    document = await service.create_document(document_data)

    signer_id = uuid4()
    request_data = SignatureRequestCreate(
        document_id=document.id,
        requester_id=test_user_id,
        signer_id=signer_id,
    )

    sig_request = await service.send_signature_request(
        request_data, test_user_id, "127.0.0.1", "Test Agent"
    )
    assert sig_request.status == SignatureRequestStatus.SENT
    assert sig_request.viewed_at is None

    # Mark as viewed
    updated_request = await service.mark_request_viewed(
        sig_request.id, signer_id, "127.0.0.1", "Test Agent"
    )

    assert updated_request is not None
    assert updated_request.status == SignatureRequestStatus.VIEWED
    assert updated_request.viewed_at is not None


@pytest.mark.asyncio
async def test_complete_signature_request_flow(
    db_session: AsyncSession, test_user_id: UUID
):
    """Test complete signature request workflow."""
    service = DocumentService(db_session)

    # 1. Create document
    document_data = DocumentCreate(
        type="financial",
        title="Financial Form",
        content_url="https://example.com/financial.pdf",
        created_by=test_user_id,
    )
    document = await service.create_document(document_data)

    # 2. Send signature request
    signer_id = uuid4()
    request_data = SignatureRequestCreate(
        document_id=document.id,
        requester_id=test_user_id,
        signer_id=signer_id,
    )
    sig_request = await service.send_signature_request(
        request_data, test_user_id, "127.0.0.1", "Test Agent"
    )
    assert sig_request.status == SignatureRequestStatus.SENT

    # 3. Mark as viewed
    await service.mark_request_viewed(
        sig_request.id, signer_id, "127.0.0.1", "Test Agent"
    )

    # 4. Create signature (should auto-complete request)
    signature_data = SignatureCreate(
        document_id=document.id,
        signer_id=signer_id,
        signature_image_url="https://example.com/sig.png",
        ip_address="127.0.0.1",
    )
    await service.create_signature(signature_data, signer_id)

    # 5. Verify request is completed
    completed_request = await service.get_signature_request_by_id(sig_request.id)
    assert completed_request is not None
    assert completed_request.status == SignatureRequestStatus.COMPLETED
    assert completed_request.completed_at is not None

    # 6. Verify document is signed
    signed_doc = await service.get_document_by_id(document.id)
    assert signed_doc.status == DocumentStatus.SIGNED


# ============================================================================
# Dashboard Tests
# ============================================================================


@pytest.mark.asyncio
async def test_get_signature_dashboard(db_session: AsyncSession, test_user_id: UUID):
    """Test dashboard statistics calculation."""
    service = DocumentService(db_session)

    # Create mix of documents with different statuses
    statuses = ["draft", "pending", "signed", "expired"]
    for status in statuses:
        document_data = DocumentCreate(
            type="enrollment",
            title=f"Document {status}",
            content_url=f"https://example.com/{status}.pdf",
            created_by=test_user_id,
        )
        doc = await service.create_document(document_data)

        if status != "draft":
            update_data = DocumentUpdate(status=status)
            await service.update_document(doc.id, update_data, test_user_id)

    # Get dashboard
    dashboard = await service.get_signature_dashboard(user_id=test_user_id)

    assert dashboard is not None
    assert dashboard.total_documents >= 4
    assert dashboard.pending_signatures >= 0
    assert dashboard.signed_documents >= 1
    assert dashboard.completion_rate is not None


# ============================================================================
# Audit Trail Tests
# ============================================================================


@pytest.mark.asyncio
async def test_create_audit_log(db_session: AsyncSession, test_user_id: UUID):
    """Test creating audit log entry."""
    service = DocumentService(db_session)

    # Create document first
    document_data = DocumentCreate(
        type="medical",
        title="Medical Form",
        content_url="https://example.com/medical.pdf",
        created_by=test_user_id,
    )
    document = await service.create_document(document_data)

    # Create audit log manually
    from app.schemas.document import DocumentAuditLogCreate

    audit_data = DocumentAuditLogCreate(
        event_type=DocumentAuditEventType.DOCUMENT_CREATED,
        document_id=document.id,
        user_id=test_user_id,
        event_data={"documentType": "medical", "title": "Medical Form"},
        ip_address="192.168.1.100",
        user_agent="Mozilla/5.0",
    )

    audit_log = await service.create_audit_log(audit_data)

    assert audit_log is not None
    assert audit_log.event_type == DocumentAuditEventType.DOCUMENT_CREATED
    assert audit_log.document_id == document.id


@pytest.mark.asyncio
async def test_get_audit_logs_for_document(
    db_session: AsyncSession, test_user_id: UUID
):
    """Test retrieving audit trail for a document."""
    service = DocumentService(db_session)

    # Create document (auto-creates audit log)
    document_data = DocumentCreate(
        type="policy",
        title="Policy Document",
        content_url="https://example.com/policy.pdf",
        created_by=test_user_id,
    )
    document = await service.create_document(document_data)

    # Update document (creates another audit log)
    update_data = DocumentUpdate(status="pending")
    await service.update_document(document.id, update_data, test_user_id)

    # Get audit logs
    audit_logs = await service.get_audit_logs_for_document(document.id)

    # Should have at least 1 audit log (DOCUMENT_CREATED)
    # Note: Service layer may auto-create audit logs for create/update operations
    assert len(audit_logs) >= 1


@pytest.mark.asyncio
async def test_signature_creates_audit_log(
    db_session: AsyncSession, test_user_id: UUID
):
    """Test that signature creation generates audit log."""
    service = DocumentService(db_session)

    # Create document
    document_data = DocumentCreate(
        type="enrollment",
        title="Test Document",
        content_url="https://example.com/test.pdf",
        created_by=test_user_id,
    )
    document = await service.create_document(document_data)

    # Update to PENDING
    update_data = DocumentUpdate(status="pending")
    await service.update_document(document.id, update_data, test_user_id)

    # Create signature
    signature_data = SignatureCreate(
        document_id=document.id,
        signer_id=test_user_id,
        signature_image_url="https://example.com/sig.png",
        ip_address="127.0.0.1",
        device_info="Test Device",
    )
    await service.create_signature(signature_data, test_user_id)

    # Check audit logs for SIGNATURE_CREATED event
    audit_logs = await service.get_audit_logs_for_document(document.id)

    # Filter for signature created events
    signature_events = [
        log for log in audit_logs
        if log.event_type == DocumentAuditEventType.SIGNATURE_CREATED
    ]

    assert len(signature_events) >= 1
