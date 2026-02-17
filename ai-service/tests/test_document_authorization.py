"""Unit tests for document service authorization.

Tests for document authorization, ensuring IDOR vulnerabilities are prevented.

Tests cover:
- Document access authorization (creator can access, non-creator cannot)
- Document update authorization (creator can update, non-creator cannot)
- UnauthorizedAccessError exceptions for unauthorized access attempts
"""

from __future__ import annotations

from uuid import UUID, uuid4

import pytest
import pytest_asyncio
from sqlalchemy.ext.asyncio import AsyncSession

from app.models.document import Document
from app.services.document_service import (
    DocumentService,
    UnauthorizedAccessError,
)
from app.schemas.document import DocumentCreate, DocumentUpdate


# =============================================================================
# Fixtures
# =============================================================================


@pytest_asyncio.fixture
async def test_creator_id() -> UUID:
    """Fixture providing a test creator user ID."""
    return uuid4()


@pytest_asyncio.fixture
async def test_other_user_id() -> UUID:
    """Fixture providing a different test user ID."""
    return uuid4()


# =============================================================================
# Document Authorization Tests
# =============================================================================


class TestDocumentAuthorization:
    """Test suite for document authorization functionality."""

    @pytest.mark.asyncio
    async def test_creator_can_access_document(
        self,
        db_session: AsyncSession,
        test_creator_id: UUID,
    ):
        """Test that document creator can access their own document."""
        service = DocumentService(db_session)

        # Create document
        document_data = DocumentCreate(
            type="enrollment",
            title="Test Enrollment Form",
            content_url="https://example.com/doc.pdf",
            created_by=test_creator_id,
        )
        created_document = await service.create_document(document_data)

        # Creator should be able to access
        document = await service.get_document_by_id(
            document_id=created_document.id,
            user_id=test_creator_id,
        )

        assert document is not None
        assert document.id == created_document.id
        assert document.title == "Test Enrollment Form"

    @pytest.mark.asyncio
    async def test_non_creator_cannot_access_document(
        self,
        db_session: AsyncSession,
        test_creator_id: UUID,
        test_other_user_id: UUID,
    ):
        """Test that non-creator cannot access document (raises UnauthorizedAccessError)."""
        service = DocumentService(db_session)

        # Create document
        document_data = DocumentCreate(
            type="enrollment",
            title="Test Enrollment Form",
            content_url="https://example.com/doc.pdf",
            created_by=test_creator_id,
        )
        created_document = await service.create_document(document_data)

        # Non-creator should get UnauthorizedAccessError
        with pytest.raises(UnauthorizedAccessError):
            await service.get_document_by_id(
                document_id=created_document.id,
                user_id=test_other_user_id,
            )

    @pytest.mark.asyncio
    async def test_creator_can_update_document(
        self,
        db_session: AsyncSession,
        test_creator_id: UUID,
    ):
        """Test that document creator can update their own document."""
        service = DocumentService(db_session)

        # Create document
        document_data = DocumentCreate(
            type="enrollment",
            title="Test Enrollment Form",
            content_url="https://example.com/doc.pdf",
            created_by=test_creator_id,
        )
        created_document = await service.create_document(document_data)

        update_data = DocumentUpdate(
            title="Updated Enrollment Form",
        )

        # Creator should be able to update
        updated_document = await service.update_document(
            document_id=created_document.id,
            update_data=update_data,
            user_id=test_creator_id,
        )

        assert updated_document is not None
        assert isinstance(updated_document, Document)
        assert updated_document.title == "Updated Enrollment Form"

    @pytest.mark.asyncio
    async def test_non_creator_cannot_update_document(
        self,
        db_session: AsyncSession,
        test_creator_id: UUID,
        test_other_user_id: UUID,
    ):
        """Test that non-creator cannot update document (raises UnauthorizedAccessError)."""
        service = DocumentService(db_session)

        # Create document
        document_data = DocumentCreate(
            type="enrollment",
            title="Test Enrollment Form",
            content_url="https://example.com/doc.pdf",
            created_by=test_creator_id,
        )
        created_document = await service.create_document(document_data)

        update_data = DocumentUpdate(
            title="Hacked Document Title",
        )

        # Non-creator should get UnauthorizedAccessError
        with pytest.raises(UnauthorizedAccessError):
            await service.update_document(
                document_id=created_document.id,
                update_data=update_data,
                user_id=test_other_user_id,
            )

    @pytest.mark.asyncio
    async def test_authorization_with_nonexistent_document(
        self,
        db_session: AsyncSession,
        test_creator_id: UUID,
    ):
        """Test authorization check with non-existent document returns None."""
        service = DocumentService(db_session)
        non_existent_id = uuid4()

        # Should return None for non-existent document
        document = await service.get_document_by_id(
            document_id=non_existent_id,
            user_id=test_creator_id,
        )

        assert document is None

    @pytest.mark.asyncio
    async def test_multiple_documents_authorization(
        self,
        db_session: AsyncSession,
        test_creator_id: UUID,
        test_other_user_id: UUID,
    ):
        """Test authorization with multiple documents from different creators."""
        service = DocumentService(db_session)

        # Create document for creator
        doc1_data = DocumentCreate(
            type="enrollment",
            title="Creator's Document",
            content_url="https://example.com/doc1.pdf",
            created_by=test_creator_id,
        )
        doc1 = await service.create_document(doc1_data)

        # Create document for other user
        doc2_data = DocumentCreate(
            type="permission",
            title="Other User's Document",
            content_url="https://example.com/doc2.pdf",
            created_by=test_other_user_id,
        )
        doc2 = await service.create_document(doc2_data)

        # Creator can access their own document
        retrieved_doc1 = await service.get_document_by_id(
            document_id=doc1.id,
            user_id=test_creator_id,
        )
        assert retrieved_doc1 is not None
        assert retrieved_doc1.id == doc1.id

        # Creator cannot access other user's document
        with pytest.raises(UnauthorizedAccessError):
            await service.get_document_by_id(
                document_id=doc2.id,
                user_id=test_creator_id,
            )

        # Other user can access their own document
        retrieved_doc2 = await service.get_document_by_id(
            document_id=doc2.id,
            user_id=test_other_user_id,
        )
        assert retrieved_doc2 is not None
        assert retrieved_doc2.id == doc2.id

        # Other user cannot access creator's document
        with pytest.raises(UnauthorizedAccessError):
            await service.get_document_by_id(
                document_id=doc1.id,
                user_id=test_other_user_id,
            )

    @pytest.mark.asyncio
    async def test_user_has_document_access_helper(
        self,
        db_session: AsyncSession,
        test_creator_id: UUID,
        test_other_user_id: UUID,
    ):
        """Test the _user_has_document_access helper method directly."""
        service = DocumentService(db_session)

        # Create document
        document_data = DocumentCreate(
            type="enrollment",
            title="Test Enrollment Form",
            content_url="https://example.com/doc.pdf",
            created_by=test_creator_id,
        )
        created_document = await service.create_document(document_data)

        # Fetch the actual document from DB
        document = await service.get_document_by_id(
            document_id=created_document.id,
            user_id=test_creator_id,
        )

        # Creator should have access
        assert service._user_has_document_access(document, test_creator_id) is True

        # Other user should not have access
        assert service._user_has_document_access(document, test_other_user_id) is False
