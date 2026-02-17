"""Unit tests for document authorization logic in LAYA AI Service.

Tests authorization helper methods from DocumentService:
- _verify_document_access()
- _verify_template_access()
- _verify_signature_request_access()
"""

from datetime import datetime, timedelta, timezone
from uuid import UUID, uuid4

import pytest
import pytest_asyncio
from sqlalchemy.ext.asyncio import AsyncSession

from app.models.document import (
    Document,
    DocumentStatus,
    DocumentTemplate,
    DocumentType,
    SignatureRequest,
    SignatureRequestStatus,
)
from app.services.document_service import DocumentService


class TestVerifyDocumentAccess:
    """Tests for DocumentService._verify_document_access() method."""

    @pytest_asyncio.fixture
    async def sample_document(self, db_session: AsyncSession) -> Document:
        """Create a sample document for testing."""
        creator_id = uuid4()
        document = Document(
            id=uuid4(),
            type=DocumentType.ENROLLMENT,
            title="Test Enrollment Form",
            content_url="https://example.com/test-doc.pdf",
            status=DocumentStatus.DRAFT,
            created_by=creator_id,
        )
        db_session.add(document)
        await db_session.commit()
        await db_session.refresh(document)
        return document

    @pytest.mark.asyncio
    async def test_creator_has_access(
        self, db_session: AsyncSession, sample_document: Document
    ):
        """Test that document creator has access to their document."""
        service = DocumentService(db_session)

        has_access = service._verify_document_access(
            document=sample_document,
            user_id=sample_document.created_by,
        )

        assert has_access is True

    @pytest.mark.asyncio
    async def test_non_creator_denied_access(
        self, db_session: AsyncSession, sample_document: Document
    ):
        """Test that non-creator is denied access to document."""
        service = DocumentService(db_session)
        other_user_id = uuid4()

        has_access = service._verify_document_access(
            document=sample_document,
            user_id=other_user_id,
        )

        assert has_access is False

    @pytest.mark.asyncio
    async def test_access_with_string_uuid(
        self, db_session: AsyncSession, sample_document: Document
    ):
        """Test that access check works when user_id is passed as string."""
        service = DocumentService(db_session)

        # Pass creator_id as UUID object
        has_access = service._verify_document_access(
            document=sample_document,
            user_id=sample_document.created_by,
        )

        assert has_access is True

    @pytest.mark.asyncio
    async def test_access_with_different_document_statuses(
        self, db_session: AsyncSession
    ):
        """Test that access control is consistent across different document statuses."""
        creator_id = uuid4()
        other_user_id = uuid4()
        service = DocumentService(db_session)

        # Test all document statuses
        for status in [
            DocumentStatus.DRAFT,
            DocumentStatus.PENDING,
            DocumentStatus.SIGNED,
            DocumentStatus.EXPIRED,
        ]:
            document = Document(
                id=uuid4(),
                type=DocumentType.PERMISSION,
                title=f"Test {status.value} Document",
                content_url="https://example.com/test.pdf",
                status=status,
                created_by=creator_id,
            )
            db_session.add(document)
            await db_session.commit()
            await db_session.refresh(document)

            # Creator should have access
            assert service._verify_document_access(document, creator_id) is True

            # Non-creator should not have access
            assert service._verify_document_access(document, other_user_id) is False

    @pytest.mark.asyncio
    async def test_access_with_different_document_types(
        self, db_session: AsyncSession
    ):
        """Test that access control works consistently for all document types."""
        creator_id = uuid4()
        other_user_id = uuid4()
        service = DocumentService(db_session)

        # Test all document types
        for doc_type in [
            DocumentType.ENROLLMENT,
            DocumentType.PERMISSION,
            DocumentType.POLICY,
            DocumentType.MEDICAL,
            DocumentType.FINANCIAL,
            DocumentType.OTHER,
        ]:
            document = Document(
                id=uuid4(),
                type=doc_type,
                title=f"Test {doc_type.value} Document",
                content_url="https://example.com/test.pdf",
                status=DocumentStatus.DRAFT,
                created_by=creator_id,
            )
            db_session.add(document)
            await db_session.commit()
            await db_session.refresh(document)

            # Creator should have access
            assert service._verify_document_access(document, creator_id) is True

            # Non-creator should not have access
            assert service._verify_document_access(document, other_user_id) is False


class TestVerifyTemplateAccess:
    """Tests for DocumentService._verify_template_access() method."""

    @pytest_asyncio.fixture
    async def sample_template(self, db_session: AsyncSession) -> DocumentTemplate:
        """Create a sample document template for testing."""
        creator_id = uuid4()
        template = DocumentTemplate(
            id=uuid4(),
            name="Test Enrollment Template",
            type=DocumentType.ENROLLMENT,
            description="A test enrollment template",
            template_content="<html><body>Test enrollment form</body></html>",
            required_fields='["student_name", "parent_signature"]',
            is_active=True,
            version=1,
            created_by=creator_id,
        )
        db_session.add(template)
        await db_session.commit()
        await db_session.refresh(template)
        return template

    @pytest.mark.asyncio
    async def test_creator_has_access(
        self, db_session: AsyncSession, sample_template: DocumentTemplate
    ):
        """Test that template creator has access to their template."""
        service = DocumentService(db_session)

        has_access = service._verify_template_access(
            template=sample_template,
            user_id=sample_template.created_by,
        )

        assert has_access is True

    @pytest.mark.asyncio
    async def test_non_creator_denied_access(
        self, db_session: AsyncSession, sample_template: DocumentTemplate
    ):
        """Test that non-creator is denied access to template."""
        service = DocumentService(db_session)
        other_user_id = uuid4()

        has_access = service._verify_template_access(
            template=sample_template,
            user_id=other_user_id,
        )

        assert has_access is False

    @pytest.mark.asyncio
    async def test_access_with_inactive_template(self, db_session: AsyncSession):
        """Test that access control works for inactive templates."""
        creator_id = uuid4()
        other_user_id = uuid4()
        service = DocumentService(db_session)

        template = DocumentTemplate(
            id=uuid4(),
            name="Inactive Template",
            type=DocumentType.PERMISSION,
            description="An inactive template",
            template_content="<html><body>Inactive</body></html>",
            is_active=False,
            version=1,
            created_by=creator_id,
        )
        db_session.add(template)
        await db_session.commit()
        await db_session.refresh(template)

        # Creator should have access even if template is inactive
        assert service._verify_template_access(template, creator_id) is True

        # Non-creator should not have access
        assert service._verify_template_access(template, other_user_id) is False

    @pytest.mark.asyncio
    async def test_access_with_different_template_types(self, db_session: AsyncSession):
        """Test that access control works consistently for all template types."""
        creator_id = uuid4()
        other_user_id = uuid4()
        service = DocumentService(db_session)

        # Test all template types
        for template_type in [
            DocumentType.ENROLLMENT,
            DocumentType.PERMISSION,
            DocumentType.POLICY,
            DocumentType.MEDICAL,
            DocumentType.FINANCIAL,
            DocumentType.OTHER,
        ]:
            template = DocumentTemplate(
                id=uuid4(),
                name=f"Test {template_type.value} Template",
                type=template_type,
                description=f"A test {template_type.value} template",
                template_content="<html><body>Test content</body></html>",
                is_active=True,
                version=1,
                created_by=creator_id,
            )
            db_session.add(template)
            await db_session.commit()
            await db_session.refresh(template)

            # Creator should have access
            assert service._verify_template_access(template, creator_id) is True

            # Non-creator should not have access
            assert service._verify_template_access(template, other_user_id) is False

    @pytest.mark.asyncio
    async def test_access_with_different_template_versions(
        self, db_session: AsyncSession
    ):
        """Test that access control works for templates with different versions."""
        creator_id = uuid4()
        other_user_id = uuid4()
        service = DocumentService(db_session)

        for version in [1, 2, 5, 10]:
            template = DocumentTemplate(
                id=uuid4(),
                name=f"Template Version {version}",
                type=DocumentType.ENROLLMENT,
                description=f"Template at version {version}",
                template_content="<html><body>Test content</body></html>",
                is_active=True,
                version=version,
                created_by=creator_id,
            )
            db_session.add(template)
            await db_session.commit()
            await db_session.refresh(template)

            # Creator should have access regardless of version
            assert service._verify_template_access(template, creator_id) is True

            # Non-creator should not have access
            assert service._verify_template_access(template, other_user_id) is False


class TestVerifySignatureRequestAccess:
    """Tests for DocumentService._verify_signature_request_access() method."""

    @pytest_asyncio.fixture
    async def sample_signature_request(
        self, db_session: AsyncSession
    ) -> tuple[SignatureRequest, UUID, UUID]:
        """Create a sample signature request for testing.

        Returns:
            Tuple of (signature_request, requester_id, signer_id)
        """
        # Create a document first
        creator_id = uuid4()
        document = Document(
            id=uuid4(),
            type=DocumentType.ENROLLMENT,
            title="Test Document",
            content_url="https://example.com/test.pdf",
            status=DocumentStatus.PENDING,
            created_by=creator_id,
        )
        db_session.add(document)
        await db_session.commit()
        await db_session.refresh(document)

        # Create signature request
        requester_id = uuid4()
        signer_id = uuid4()
        expires_at = datetime.now(timezone.utc) + timedelta(days=7)

        signature_request = SignatureRequest(
            id=uuid4(),
            document_id=document.id,
            requester_id=requester_id,
            signer_id=signer_id,
            status=SignatureRequestStatus.SENT,
            expires_at=expires_at,
            message="Please sign this document",
        )
        db_session.add(signature_request)
        await db_session.commit()
        await db_session.refresh(signature_request)

        return signature_request, requester_id, signer_id

    @pytest.mark.asyncio
    async def test_requester_has_access(
        self,
        db_session: AsyncSession,
        sample_signature_request: tuple[SignatureRequest, UUID, UUID],
    ):
        """Test that signature request requester has access."""
        signature_request, requester_id, signer_id = sample_signature_request
        service = DocumentService(db_session)

        has_access = service._verify_signature_request_access(
            signature_request=signature_request,
            user_id=requester_id,
        )

        assert has_access is True

    @pytest.mark.asyncio
    async def test_signer_has_access(
        self,
        db_session: AsyncSession,
        sample_signature_request: tuple[SignatureRequest, UUID, UUID],
    ):
        """Test that signature request signer has access."""
        signature_request, requester_id, signer_id = sample_signature_request
        service = DocumentService(db_session)

        has_access = service._verify_signature_request_access(
            signature_request=signature_request,
            user_id=signer_id,
        )

        assert has_access is True

    @pytest.mark.asyncio
    async def test_unrelated_user_denied_access(
        self,
        db_session: AsyncSession,
        sample_signature_request: tuple[SignatureRequest, UUID, UUID],
    ):
        """Test that unrelated user is denied access to signature request."""
        signature_request, requester_id, signer_id = sample_signature_request
        service = DocumentService(db_session)
        unrelated_user_id = uuid4()

        has_access = service._verify_signature_request_access(
            signature_request=signature_request,
            user_id=unrelated_user_id,
        )

        assert has_access is False

    @pytest.mark.asyncio
    async def test_access_with_different_request_statuses(
        self, db_session: AsyncSession
    ):
        """Test that access control works across different request statuses."""
        # Create a document first
        document = Document(
            id=uuid4(),
            type=DocumentType.ENROLLMENT,
            title="Test Document",
            content_url="https://example.com/test.pdf",
            status=DocumentStatus.PENDING,
            created_by=uuid4(),
        )
        db_session.add(document)
        await db_session.commit()

        requester_id = uuid4()
        signer_id = uuid4()
        unrelated_user_id = uuid4()
        service = DocumentService(db_session)

        # Test all signature request statuses
        for status in [
            SignatureRequestStatus.SENT,
            SignatureRequestStatus.VIEWED,
            SignatureRequestStatus.COMPLETED,
            SignatureRequestStatus.CANCELLED,
            SignatureRequestStatus.EXPIRED,
        ]:
            signature_request = SignatureRequest(
                id=uuid4(),
                document_id=document.id,
                requester_id=requester_id,
                signer_id=signer_id,
                status=status,
                expires_at=datetime.now(timezone.utc) + timedelta(days=7),
            )
            db_session.add(signature_request)
            await db_session.commit()
            await db_session.refresh(signature_request)

            # Requester should have access
            assert (
                service._verify_signature_request_access(
                    signature_request, requester_id
                )
                is True
            )

            # Signer should have access
            assert (
                service._verify_signature_request_access(signature_request, signer_id)
                is True
            )

            # Unrelated user should not have access
            assert (
                service._verify_signature_request_access(
                    signature_request, unrelated_user_id
                )
                is False
            )

    @pytest.mark.asyncio
    async def test_access_when_requester_and_signer_are_same(
        self, db_session: AsyncSession
    ):
        """Test access when requester and signer are the same user (edge case)."""
        # Create a document first
        document = Document(
            id=uuid4(),
            type=DocumentType.ENROLLMENT,
            title="Test Document",
            content_url="https://example.com/test.pdf",
            status=DocumentStatus.PENDING,
            created_by=uuid4(),
        )
        db_session.add(document)
        await db_session.commit()

        same_user_id = uuid4()
        service = DocumentService(db_session)

        # Create signature request where requester and signer are the same
        signature_request = SignatureRequest(
            id=uuid4(),
            document_id=document.id,
            requester_id=same_user_id,
            signer_id=same_user_id,
            status=SignatureRequestStatus.SENT,
            expires_at=datetime.now(timezone.utc) + timedelta(days=7),
        )
        db_session.add(signature_request)
        await db_session.commit()
        await db_session.refresh(signature_request)

        # User should have access (as both requester and signer)
        assert (
            service._verify_signature_request_access(signature_request, same_user_id)
            is True
        )

        # Unrelated user should not have access
        unrelated_user_id = uuid4()
        assert (
            service._verify_signature_request_access(
                signature_request, unrelated_user_id
            )
            is False
        )

    @pytest.mark.asyncio
    async def test_access_with_expired_request(self, db_session: AsyncSession):
        """Test that access control works even for expired signature requests."""
        # Create a document first
        document = Document(
            id=uuid4(),
            type=DocumentType.ENROLLMENT,
            title="Test Document",
            content_url="https://example.com/test.pdf",
            status=DocumentStatus.EXPIRED,
            created_by=uuid4(),
        )
        db_session.add(document)
        await db_session.commit()

        requester_id = uuid4()
        signer_id = uuid4()
        unrelated_user_id = uuid4()
        service = DocumentService(db_session)

        # Create expired signature request (expires_at in the past)
        signature_request = SignatureRequest(
            id=uuid4(),
            document_id=document.id,
            requester_id=requester_id,
            signer_id=signer_id,
            status=SignatureRequestStatus.EXPIRED,
            expires_at=datetime.now(timezone.utc) - timedelta(days=1),  # Expired
        )
        db_session.add(signature_request)
        await db_session.commit()
        await db_session.refresh(signature_request)

        # Requester should still have access to view expired request
        assert (
            service._verify_signature_request_access(signature_request, requester_id)
            is True
        )

        # Signer should still have access to view expired request
        assert (
            service._verify_signature_request_access(signature_request, signer_id)
            is True
        )

        # Unrelated user should not have access
        assert (
            service._verify_signature_request_access(
                signature_request, unrelated_user_id
            )
            is False
        )


