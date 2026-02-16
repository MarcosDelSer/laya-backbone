"""Unit tests for document models.

Tests for Document, DocumentTemplate, Signature, SignatureRequest,
and DocumentAuditLog models including relationships, constraints, and validators.
"""

from __future__ import annotations

from datetime import datetime, timezone
from uuid import UUID, uuid4

import pytest
import pytest_asyncio
from sqlalchemy import select
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


@pytest.mark.asyncio
async def test_create_document(db_session: AsyncSession):
    """Test creating a document with all required fields."""
    user_id = uuid4()

    document = Document(
        id=uuid4(),
        type=DocumentType.ENROLLMENT,
        title="Enrollment Form 2024",
        content_url="https://example.com/forms/enrollment-2024.pdf",
        status=DocumentStatus.DRAFT,
        created_by=user_id,
        created_at=datetime.now(timezone.utc),
        updated_at=datetime.now(timezone.utc),
    )

    db_session.add(document)
    await db_session.commit()
    await db_session.refresh(document)

    assert document.id is not None
    assert document.type == DocumentType.ENROLLMENT
    assert document.title == "Enrollment Form 2024"
    assert document.status == DocumentStatus.DRAFT
    assert document.created_by == user_id


@pytest.mark.asyncio
async def test_document_status_transitions(db_session: AsyncSession):
    """Test document status transitions from draft to signed."""
    document = Document(
        id=uuid4(),
        type=DocumentType.PERMISSION,
        title="Permission Slip",
        content_url="https://example.com/permission.pdf",
        status=DocumentStatus.DRAFT,
        created_by=uuid4(),
        created_at=datetime.now(timezone.utc),
        updated_at=datetime.now(timezone.utc),
    )

    db_session.add(document)
    await db_session.commit()

    # Transition to PENDING
    document.status = DocumentStatus.PENDING
    await db_session.commit()
    await db_session.refresh(document)
    assert document.status == DocumentStatus.PENDING

    # Transition to SIGNED
    document.status = DocumentStatus.SIGNED
    await db_session.commit()
    await db_session.refresh(document)
    assert document.status == DocumentStatus.SIGNED


@pytest.mark.asyncio
async def test_create_document_template(db_session: AsyncSession):
    """Test creating a document template."""
    user_id = uuid4()

    template = DocumentTemplate(
        id=uuid4(),
        name="Standard Enrollment Template",
        type=DocumentType.ENROLLMENT,
        description="Template for enrollment forms",
        template_content='<html><body>Enrollment Form</body></html>',
        required_fields='["parentName", "childName", "dateOfBirth"]',
        is_active=True,
        version=1,
        created_by=user_id,
        created_at=datetime.now(timezone.utc),
        updated_at=datetime.now(timezone.utc),
    )

    db_session.add(template)
    await db_session.commit()
    await db_session.refresh(template)

    assert template.id is not None
    assert template.name == "Standard Enrollment Template"
    assert template.is_active is True
    assert template.version == 1


@pytest.mark.asyncio
async def test_template_version_increment(db_session: AsyncSession):
    """Test incrementing template version on update."""
    template = DocumentTemplate(
        id=uuid4(),
        name="Medical Form Template",
        type=DocumentType.MEDICAL,
        template_content='<html>v1</html>',
        is_active=True,
        version=1,
        created_by=uuid4(),
        created_at=datetime.now(timezone.utc),
        updated_at=datetime.now(timezone.utc),
    )

    db_session.add(template)
    await db_session.commit()

    # Update template content and increment version
    template.template_content = '<html>v2</html>'
    template.version = 2
    await db_session.commit()
    await db_session.refresh(template)

    assert template.version == 2
    assert template.template_content == '<html>v2</html>'


@pytest.mark.asyncio
async def test_create_signature(db_session: AsyncSession):
    """Test creating a signature for a document."""
    document = Document(
        id=uuid4(),
        type=DocumentType.PERMISSION,
        title="Test Document",
        content_url="https://example.com/doc.pdf",
        status=DocumentStatus.PENDING,
        created_by=uuid4(),
        created_at=datetime.now(timezone.utc),
        updated_at=datetime.now(timezone.utc),
    )
    db_session.add(document)
    await db_session.commit()

    signer_id = uuid4()
    signature = Signature(
        id=uuid4(),
        document_id=document.id,
        signer_id=signer_id,
        signature_image_url="https://example.com/signatures/sig123.png",
        ip_address="192.168.1.1",
        timestamp=datetime.now(timezone.utc),
        device_info="Mozilla/5.0 (iPhone; CPU iPhone OS 14_0)",
        created_at=datetime.now(timezone.utc),
        updated_at=datetime.now(timezone.utc),
    )

    db_session.add(signature)
    await db_session.commit()
    await db_session.refresh(signature)

    assert signature.id is not None
    assert signature.document_id == document.id
    assert signature.signer_id == signer_id
    assert signature.ip_address == "192.168.1.1"


@pytest.mark.asyncio
async def test_signature_cascade_delete(db_session: AsyncSession):
    """Test that deleting a document cascades to signatures."""
    document = Document(
        id=uuid4(),
        type=DocumentType.POLICY,
        title="Test Document",
        content_url="https://example.com/doc.pdf",
        status=DocumentStatus.PENDING,
        created_by=uuid4(),
        created_at=datetime.now(timezone.utc),
        updated_at=datetime.now(timezone.utc),
    )
    db_session.add(document)
    await db_session.commit()

    signature = Signature(
        id=uuid4(),
        document_id=document.id,
        signer_id=uuid4(),
        signature_image_url="https://example.com/sig.png",
        ip_address="10.0.0.1",
        timestamp=datetime.now(timezone.utc),
        created_at=datetime.now(timezone.utc),
        updated_at=datetime.now(timezone.utc),
    )
    db_session.add(signature)
    await db_session.commit()

    signature_id = signature.id

    # Delete document
    await db_session.delete(document)
    await db_session.commit()

    # Verify signature is also deleted (cascade)
    result = await db_session.execute(
        select(Signature).where(Signature.id == signature_id)
    )
    deleted_signature = result.scalar_one_or_none()
    assert deleted_signature is None


@pytest.mark.asyncio
async def test_create_signature_request(db_session: AsyncSession):
    """Test creating a signature request."""
    document = Document(
        id=uuid4(),
        type=DocumentType.FINANCIAL,
        title="Financial Agreement",
        content_url="https://example.com/agreement.pdf",
        status=DocumentStatus.DRAFT,
        created_by=uuid4(),
        created_at=datetime.now(timezone.utc),
        updated_at=datetime.now(timezone.utc),
    )
    db_session.add(document)
    await db_session.commit()

    requester_id = uuid4()
    signer_id = uuid4()

    request = SignatureRequest(
        id=uuid4(),
        document_id=document.id,
        requester_id=requester_id,
        signer_id=signer_id,
        status=SignatureRequestStatus.SENT,
        sent_at=datetime.now(timezone.utc),
        notification_sent=True,
        notification_method="email",
        message="Please sign this document",
        created_at=datetime.now(timezone.utc),
        updated_at=datetime.now(timezone.utc),
    )

    db_session.add(request)
    await db_session.commit()
    await db_session.refresh(request)

    assert request.id is not None
    assert request.document_id == document.id
    assert request.status == SignatureRequestStatus.SENT
    assert request.notification_sent is True


@pytest.mark.asyncio
async def test_signature_request_status_flow(db_session: AsyncSession):
    """Test signature request status transitions."""
    document = Document(
        id=uuid4(),
        type=DocumentType.ENROLLMENT,
        title="Enrollment Form",
        content_url="https://example.com/form.pdf",
        status=DocumentStatus.PENDING,
        created_by=uuid4(),
        created_at=datetime.now(timezone.utc),
        updated_at=datetime.now(timezone.utc),
    )
    db_session.add(document)
    await db_session.commit()

    request = SignatureRequest(
        id=uuid4(),
        document_id=document.id,
        requester_id=uuid4(),
        signer_id=uuid4(),
        status=SignatureRequestStatus.SENT,
        sent_at=datetime.now(timezone.utc),
        notification_sent=True,
        created_at=datetime.now(timezone.utc),
        updated_at=datetime.now(timezone.utc),
    )
    db_session.add(request)
    await db_session.commit()

    # Transition to VIEWED
    request.status = SignatureRequestStatus.VIEWED
    request.viewed_at = datetime.now(timezone.utc)
    await db_session.commit()
    await db_session.refresh(request)
    assert request.status == SignatureRequestStatus.VIEWED
    assert request.viewed_at is not None

    # Transition to COMPLETED
    request.status = SignatureRequestStatus.COMPLETED
    request.completed_at = datetime.now(timezone.utc)
    await db_session.commit()
    await db_session.refresh(request)
    assert request.status == SignatureRequestStatus.COMPLETED
    assert request.completed_at is not None


@pytest.mark.asyncio
async def test_create_audit_log(db_session: AsyncSession):
    """Test creating a document audit log entry."""
    document = Document(
        id=uuid4(),
        type=DocumentType.PERMISSION,
        title="Permission Slip",
        content_url="https://example.com/permission.pdf",
        status=DocumentStatus.DRAFT,
        created_by=uuid4(),
        created_at=datetime.now(timezone.utc),
        updated_at=datetime.now(timezone.utc),
    )
    db_session.add(document)
    await db_session.commit()

    user_id = uuid4()

    audit_log = DocumentAuditLog(
        id=uuid4(),
        event_type=DocumentAuditEventType.DOCUMENT_CREATED,
        document_id=document.id,
        user_id=user_id,
        event_data='{"documentType": "permission", "title": "Permission Slip"}',
        ip_address="192.168.1.100",
        user_agent="Mozilla/5.0",
        timestamp=datetime.now(timezone.utc),
        created_at=datetime.now(timezone.utc),
    )

    db_session.add(audit_log)
    await db_session.commit()
    await db_session.refresh(audit_log)

    assert audit_log.id is not None
    assert audit_log.event_type == DocumentAuditEventType.DOCUMENT_CREATED
    assert audit_log.document_id == document.id
    assert audit_log.user_id == user_id


@pytest.mark.asyncio
async def test_audit_log_all_event_types(db_session: AsyncSession):
    """Test creating audit logs for all event types."""
    document = Document(
        id=uuid4(),
        type=DocumentType.MEDICAL,
        title="Medical Form",
        content_url="https://example.com/medical.pdf",
        status=DocumentStatus.DRAFT,
        created_by=uuid4(),
        created_at=datetime.now(timezone.utc),
        updated_at=datetime.now(timezone.utc),
    )
    db_session.add(document)
    await db_session.commit()

    user_id = uuid4()

    # Test each event type
    event_types = [
        DocumentAuditEventType.DOCUMENT_CREATED,
        DocumentAuditEventType.DOCUMENT_UPDATED,
        DocumentAuditEventType.DOCUMENT_STATUS_CHANGED,
        DocumentAuditEventType.SIGNATURE_REQUEST_SENT,
        DocumentAuditEventType.SIGNATURE_REQUEST_VIEWED,
        DocumentAuditEventType.SIGNATURE_REQUEST_COMPLETED,
        DocumentAuditEventType.SIGNATURE_CREATED,
        DocumentAuditEventType.TEMPLATE_USED,
    ]

    for event_type in event_types:
        audit_log = DocumentAuditLog(
            id=uuid4(),
            event_type=event_type,
            document_id=document.id,
            user_id=user_id,
            event_data=f'{{"event": "{event_type.value}"}}',
            timestamp=datetime.now(timezone.utc),
            created_at=datetime.now(timezone.utc),
        )
        db_session.add(audit_log)

    await db_session.commit()

    # Verify all logs were created
    result = await db_session.execute(
        select(DocumentAuditLog).where(DocumentAuditLog.document_id == document.id)
    )
    logs = result.scalars().all()
    assert len(logs) == len(event_types)


@pytest.mark.asyncio
async def test_audit_log_with_foreign_keys(db_session: AsyncSession):
    """Test audit log with signature and signature_request foreign keys."""
    # Create document
    document = Document(
        id=uuid4(),
        type=DocumentType.ENROLLMENT,
        title="Enrollment Form",
        content_url="https://example.com/enrollment.pdf",
        status=DocumentStatus.PENDING,
        created_by=uuid4(),
        created_at=datetime.now(timezone.utc),
        updated_at=datetime.now(timezone.utc),
    )
    db_session.add(document)
    await db_session.commit()

    # Create signature
    signature = Signature(
        id=uuid4(),
        document_id=document.id,
        signer_id=uuid4(),
        signature_image_url="https://example.com/sig.png",
        ip_address="10.0.0.1",
        timestamp=datetime.now(timezone.utc),
        created_at=datetime.now(timezone.utc),
        updated_at=datetime.now(timezone.utc),
    )
    db_session.add(signature)
    await db_session.commit()

    # Create signature request
    sig_request = SignatureRequest(
        id=uuid4(),
        document_id=document.id,
        requester_id=uuid4(),
        signer_id=uuid4(),
        status=SignatureRequestStatus.SENT,
        sent_at=datetime.now(timezone.utc),
        notification_sent=True,
        created_at=datetime.now(timezone.utc),
        updated_at=datetime.now(timezone.utc),
    )
    db_session.add(sig_request)
    await db_session.commit()

    # Create audit log with all foreign keys
    audit_log = DocumentAuditLog(
        id=uuid4(),
        event_type=DocumentAuditEventType.SIGNATURE_CREATED,
        document_id=document.id,
        user_id=uuid4(),
        signature_id=signature.id,
        signature_request_id=sig_request.id,
        event_data='{"action": "signature_created"}',
        timestamp=datetime.now(timezone.utc),
        created_at=datetime.now(timezone.utc),
    )

    db_session.add(audit_log)
    await db_session.commit()
    await db_session.refresh(audit_log)

    assert audit_log.document_id == document.id
    assert audit_log.signature_id == signature.id
    assert audit_log.signature_request_id == sig_request.id
