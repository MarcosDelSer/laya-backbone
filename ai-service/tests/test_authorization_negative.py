"""Negative test cases for authorization across all services.

This test suite focuses exclusively on NEGATIVE scenarios - verifying that unauthorized
access attempts are properly blocked and return appropriate errors. Tests cover:

- Cross-user access attempts (user B trying to access user A's resources)
- Invalid/non-existent resource IDs
- Missing authentication
- Role-based access control violations
- Proper exception handling and error messages

Services tested:
- DocumentService
- MessagingService
- CommunicationService
- DevelopmentProfileService
- InterventionPlanService
- StorageService
"""

from datetime import datetime, timezone
from uuid import UUID, uuid4

import pytest
import pytest_asyncio
from sqlalchemy import text
from sqlalchemy.ext.asyncio import AsyncSession

from app.models.document import (
    Document,
    DocumentStatus,
    DocumentTemplate,
    DocumentType,
    SignatureRequest,
    SignatureRequestStatus,
)
from app.services.document_service import (
    DocumentService,
    UnauthorizedAccessError as DocumentUnauthorizedError,
)
from app.services.messaging_service import (
    MessagingService,
    UnauthorizedAccessError as MessagingUnauthorizedError,
)
from app.services.communication_service import (
    CommunicationService,
    UnauthorizedAccessError as CommunicationUnauthorizedError,
)
from app.services.development_profile_service import (
    DevelopmentProfileService,
    UnauthorizedAccessError as DevelopmentUnauthorizedError,
)
from app.services.intervention_plan_service import (
    InterventionPlanService,
    UnauthorizedAccessError as InterventionUnauthorizedError,
)
from app.services.storage_service import (
    StorageService,
    UnauthorizedAccessError as StorageUnauthorizedError,
)


# =============================================================================
# Test Fixtures
# =============================================================================


@pytest.fixture
def user_a_id():
    """ID for user A (resource owner)."""
    return uuid4()


@pytest.fixture
def user_b_id():
    """ID for user B (unauthorized user)."""
    return uuid4()


@pytest.fixture
def user_c_id():
    """ID for user C (another unauthorized user)."""
    return uuid4()


@pytest.fixture
def child_a_id():
    """ID for child belonging to user A."""
    return uuid4()


@pytest.fixture
def child_b_id():
    """ID for child belonging to user B."""
    return uuid4()


@pytest.fixture
def non_existent_id():
    """ID that doesn't exist in the database."""
    return uuid4()


# =============================================================================
# Document Service - Negative Authorization Tests
# =============================================================================


class TestDocumentServiceUnauthorizedAccess:
    """Negative test cases for DocumentService authorization."""

    @pytest_asyncio.fixture
    async def user_a_document(
        self, db_session: AsyncSession, user_a_id: UUID
    ) -> Document:
        """Create a document owned by user A."""
        document = Document(
            id=uuid4(),
            type=DocumentType.ENROLLMENT,
            title="Private Document",
            content_url="https://example.com/private.pdf",
            status=DocumentStatus.DRAFT,
            created_by=user_a_id,
        )
        db_session.add(document)
        await db_session.commit()
        await db_session.refresh(document)
        return document

    @pytest_asyncio.fixture
    async def user_a_template(
        self, db_session: AsyncSession, user_a_id: UUID
    ) -> DocumentTemplate:
        """Create a template owned by user A."""
        template = DocumentTemplate(
            id=uuid4(),
            name="Private Template",
            type=DocumentType.ENROLLMENT,
            content="Private template content",
            created_by=user_a_id,
        )
        db_session.add(template)
        await db_session.commit()
        await db_session.refresh(template)
        return template

    @pytest_asyncio.fixture
    async def user_a_signature_request(
        self, db_session: AsyncSession, user_a_id: UUID, user_a_document: Document
    ) -> SignatureRequest:
        """Create a signature request owned by user A."""
        sig_request = SignatureRequest(
            id=uuid4(),
            document_id=user_a_document.id,
            requested_by=user_a_id,
            signer_email="signer@example.com",
            status=SignatureRequestStatus.PENDING,
        )
        db_session.add(sig_request)
        await db_session.commit()
        await db_session.refresh(sig_request)
        return sig_request

    @pytest.mark.asyncio
    async def test_unauthorized_user_cannot_access_document(
        self,
        db_session: AsyncSession,
        user_a_document: Document,
        user_b_id: UUID,
    ):
        """Test that user B cannot access user A's document."""
        service = DocumentService(db_session)

        # Attempt unauthorized access
        has_access = service._verify_document_access(
            document=user_a_document,
            user_id=user_b_id,
        )

        # Should be denied
        assert has_access is False

    @pytest.mark.asyncio
    async def test_multiple_unauthorized_users_cannot_access_document(
        self,
        db_session: AsyncSession,
        user_a_document: Document,
        user_b_id: UUID,
        user_c_id: UUID,
    ):
        """Test that multiple unauthorized users cannot access the document."""
        service = DocumentService(db_session)

        # User B attempt
        assert service._verify_document_access(user_a_document, user_b_id) is False

        # User C attempt
        assert service._verify_document_access(user_a_document, user_c_id) is False

    @pytest.mark.asyncio
    async def test_unauthorized_user_cannot_access_template(
        self,
        db_session: AsyncSession,
        user_a_template: DocumentTemplate,
        user_b_id: UUID,
    ):
        """Test that user B cannot access user A's template."""
        service = DocumentService(db_session)

        has_access = service._verify_template_access(
            template=user_a_template,
            user_id=user_b_id,
        )

        assert has_access is False

    @pytest.mark.asyncio
    async def test_unauthorized_user_cannot_access_signature_request(
        self,
        db_session: AsyncSession,
        user_a_signature_request: SignatureRequest,
        user_b_id: UUID,
    ):
        """Test that user B cannot access user A's signature request."""
        service = DocumentService(db_session)

        has_access = service._verify_signature_request_access(
            request=user_a_signature_request,
            user_id=user_b_id,
        )

        assert has_access is False

    @pytest.mark.asyncio
    async def test_access_denied_for_all_document_statuses(
        self,
        db_session: AsyncSession,
        user_a_id: UUID,
        user_b_id: UUID,
    ):
        """Test that unauthorized access is blocked regardless of document status."""
        service = DocumentService(db_session)

        for status in [DocumentStatus.DRAFT, DocumentStatus.PENDING,
                       DocumentStatus.SIGNED, DocumentStatus.EXPIRED]:
            document = Document(
                id=uuid4(),
                type=DocumentType.PERMISSION,
                title=f"Test {status.value}",
                content_url="https://example.com/test.pdf",
                status=status,
                created_by=user_a_id,
            )
            db_session.add(document)
            await db_session.commit()
            await db_session.refresh(document)

            # User B should not have access to any status
            assert service._verify_document_access(document, user_b_id) is False


# =============================================================================
# Messaging Service - Negative Authorization Tests
# =============================================================================


class TestMessagingServiceUnauthorizedAccess:
    """Negative test cases for MessagingService notification preference authorization."""

    @pytest.mark.asyncio
    async def test_user_cannot_access_other_users_preferences(
        self,
        db_session: AsyncSession,
        user_a_id: UUID,
        user_b_id: UUID,
    ):
        """Test that users cannot access other users' notification preferences."""
        service = MessagingService(db_session)

        # User B attempting to access user A's preferences
        with pytest.raises(MessagingUnauthorizedError) as exc_info:
            service._verify_notification_preference_access(
                target_user_id=user_a_id,
                requesting_user_id=user_b_id,
                user_role="parent",
            )

        assert "notification preferences" in str(exc_info.value).lower()

    @pytest.mark.asyncio
    async def test_parent_role_cannot_access_other_users_preferences(
        self,
        db_session: AsyncSession,
        user_a_id: UUID,
        user_b_id: UUID,
    ):
        """Test that parent role cannot bypass authorization checks."""
        service = MessagingService(db_session)

        with pytest.raises(MessagingUnauthorizedError):
            service._verify_notification_preference_access(
                target_user_id=user_a_id,
                requesting_user_id=user_b_id,
                user_role="parent",
            )

    @pytest.mark.asyncio
    async def test_educator_role_cannot_access_other_users_preferences(
        self,
        db_session: AsyncSession,
        user_a_id: UUID,
        user_b_id: UUID,
    ):
        """Test that educator role cannot access other users' preferences."""
        service = MessagingService(db_session)

        with pytest.raises(MessagingUnauthorizedError):
            service._verify_notification_preference_access(
                target_user_id=user_a_id,
                requesting_user_id=user_b_id,
                user_role="educator",
            )

    @pytest.mark.asyncio
    async def test_unknown_role_cannot_access_preferences(
        self,
        db_session: AsyncSession,
        user_a_id: UUID,
        user_b_id: UUID,
    ):
        """Test that unknown/invalid roles cannot access preferences."""
        service = MessagingService(db_session)

        with pytest.raises(MessagingUnauthorizedError):
            service._verify_notification_preference_access(
                target_user_id=user_a_id,
                requesting_user_id=user_b_id,
                user_role="unknown_role",
            )

    @pytest.mark.asyncio
    async def test_multiple_unauthorized_users_cannot_access_preferences(
        self,
        db_session: AsyncSession,
        user_a_id: UUID,
        user_b_id: UUID,
        user_c_id: UUID,
    ):
        """Test that multiple unauthorized users are all blocked."""
        service = MessagingService(db_session)

        # User B attempt
        with pytest.raises(MessagingUnauthorizedError):
            service._verify_notification_preference_access(
                target_user_id=user_a_id,
                requesting_user_id=user_b_id,
                user_role="parent",
            )

        # User C attempt
        with pytest.raises(MessagingUnauthorizedError):
            service._verify_notification_preference_access(
                target_user_id=user_a_id,
                requesting_user_id=user_c_id,
                user_role="parent",
            )


# =============================================================================
# Communication Service - Negative Authorization Tests
# =============================================================================


class TestCommunicationServiceUnauthorizedAccess:
    """Negative test cases for CommunicationService child access authorization."""

    @pytest_asyncio.fixture
    async def setup_child_for_user_a(
        self,
        db_session: AsyncSession,
        user_a_id: UUID,
        child_a_id: UUID,
    ):
        """Set up child and parent-child relationship for user A."""
        await db_session.execute(
            text(
                """
                INSERT INTO children (id, first_name, last_name, date_of_birth)
                VALUES (:id, :first_name, :last_name, :dob)
                """
            ),
            {
                "id": str(child_a_id),
                "first_name": "Child",
                "last_name": "A",
                "dob": "2020-01-01",
            },
        )

        await db_session.execute(
            text(
                """
                INSERT INTO parent_child (parent_id, child_id)
                VALUES (:parent_id, :child_id)
                """
            ),
            {
                "parent_id": str(user_a_id),
                "child_id": str(child_a_id),
            },
        )
        await db_session.commit()

    @pytest.mark.asyncio
    async def test_unauthorized_user_cannot_access_child(
        self,
        db_session: AsyncSession,
        setup_child_for_user_a,
        child_a_id: UUID,
        user_b_id: UUID,
    ):
        """Test that unauthorized user cannot access another user's child."""
        service = CommunicationService(db_session)

        with pytest.raises(CommunicationUnauthorizedError):
            await service._verify_child_access(
                child_id=child_a_id,
                user_id=user_b_id,
                user_role="parent",
            )

    @pytest.mark.asyncio
    async def test_parent_role_cannot_access_unrelated_child(
        self,
        db_session: AsyncSession,
        setup_child_for_user_a,
        child_a_id: UUID,
        user_b_id: UUID,
    ):
        """Test that parent role cannot access child they're not related to."""
        service = CommunicationService(db_session)

        with pytest.raises(CommunicationUnauthorizedError):
            await service._verify_child_access(
                child_id=child_a_id,
                user_id=user_b_id,
                user_role="parent",
            )

    @pytest.mark.asyncio
    async def test_educator_role_cannot_access_unrelated_child(
        self,
        db_session: AsyncSession,
        setup_child_for_user_a,
        child_a_id: UUID,
        user_b_id: UUID,
    ):
        """Test that educator role without proper assignment cannot access child."""
        service = CommunicationService(db_session)

        # Educator without child assignment should be denied
        with pytest.raises(CommunicationUnauthorizedError):
            await service._verify_child_access(
                child_id=child_a_id,
                user_id=user_b_id,
                user_role="educator",
            )

    @pytest.mark.asyncio
    async def test_access_denied_for_non_existent_child(
        self,
        db_session: AsyncSession,
        non_existent_id: UUID,
        user_b_id: UUID,
    ):
        """Test that access is denied for non-existent child ID."""
        service = CommunicationService(db_session)

        # Should raise error for non-existent child
        with pytest.raises(CommunicationUnauthorizedError):
            await service._verify_child_access(
                child_id=non_existent_id,
                user_id=user_b_id,
                user_role="parent",
            )

    @pytest.mark.asyncio
    async def test_multiple_unauthorized_users_cannot_access_child(
        self,
        db_session: AsyncSession,
        setup_child_for_user_a,
        child_a_id: UUID,
        user_b_id: UUID,
        user_c_id: UUID,
    ):
        """Test that multiple unauthorized users are all blocked."""
        service = CommunicationService(db_session)

        # User B attempt
        with pytest.raises(CommunicationUnauthorizedError):
            await service._verify_child_access(
                child_id=child_a_id,
                user_id=user_b_id,
                user_role="parent",
            )

        # User C attempt
        with pytest.raises(CommunicationUnauthorizedError):
            await service._verify_child_access(
                child_id=child_a_id,
                user_id=user_c_id,
                user_role="parent",
            )


# =============================================================================
# Development Profile Service - Negative Authorization Tests
# =============================================================================


class TestDevelopmentProfileServiceUnauthorizedAccess:
    """Negative test cases for DevelopmentProfileService authorization."""

    @pytest_asyncio.fixture
    async def setup_profile_for_user_a(
        self,
        db_session: AsyncSession,
        child_a_id: UUID,
        user_a_id: UUID,
    ):
        """Set up development profile for user A's child."""
        # Insert child record
        await db_session.execute(
            text(
                """
                INSERT INTO children (id, first_name, last_name, date_of_birth)
                VALUES (:id, :first_name, :last_name, :dob)
                """
            ),
            {
                "id": str(child_a_id),
                "first_name": "Child",
                "last_name": "A",
                "dob": "2020-01-01",
            },
        )

        # Insert parent-child relationship
        await db_session.execute(
            text(
                """
                INSERT INTO parent_child (parent_id, child_id)
                VALUES (:parent_id, :child_id)
                """
            ),
            {
                "parent_id": str(user_a_id),
                "child_id": str(child_a_id),
            },
        )

        # Insert development profile
        profile_id = uuid4()
        await db_session.execute(
            text(
                """
                INSERT INTO development_profiles (id, child_id, profile_date)
                VALUES (:id, :child_id, :profile_date)
                """
            ),
            {
                "id": str(profile_id),
                "child_id": str(child_a_id),
                "profile_date": datetime.now(timezone.utc).date().isoformat(),
            },
        )
        await db_session.commit()
        return profile_id

    @pytest.mark.asyncio
    async def test_unauthorized_user_cannot_access_profile(
        self,
        db_session: AsyncSession,
        setup_profile_for_user_a,
        user_b_id: UUID,
    ):
        """Test that unauthorized user cannot access development profile."""
        service = DevelopmentProfileService(db_session)
        profile_id = setup_profile_for_user_a

        # User B attempts to access user A's child's profile
        result = await service.get_development_profile(
            profile_id=profile_id,
            user_id=user_b_id,
            user_role="parent",
        )

        # Should return None (access denied)
        assert result is None

    @pytest.mark.asyncio
    async def test_parent_role_cannot_access_unrelated_profile(
        self,
        db_session: AsyncSession,
        setup_profile_for_user_a,
        user_b_id: UUID,
    ):
        """Test that parent role cannot access unrelated child's profile."""
        service = DevelopmentProfileService(db_session)
        profile_id = setup_profile_for_user_a

        result = await service.get_development_profile(
            profile_id=profile_id,
            user_id=user_b_id,
            user_role="parent",
        )

        assert result is None

    @pytest.mark.asyncio
    async def test_access_denied_for_non_existent_profile(
        self,
        db_session: AsyncSession,
        non_existent_id: UUID,
        user_b_id: UUID,
    ):
        """Test that accessing non-existent profile returns None."""
        service = DevelopmentProfileService(db_session)

        result = await service.get_development_profile(
            profile_id=non_existent_id,
            user_id=user_b_id,
            user_role="parent",
        )

        assert result is None

    @pytest.mark.asyncio
    async def test_multiple_unauthorized_users_cannot_access_profile(
        self,
        db_session: AsyncSession,
        setup_profile_for_user_a,
        user_b_id: UUID,
        user_c_id: UUID,
    ):
        """Test that multiple unauthorized users are all blocked."""
        service = DevelopmentProfileService(db_session)
        profile_id = setup_profile_for_user_a

        # User B attempt
        result_b = await service.get_development_profile(
            profile_id=profile_id,
            user_id=user_b_id,
            user_role="parent",
        )
        assert result_b is None

        # User C attempt
        result_c = await service.get_development_profile(
            profile_id=profile_id,
            user_id=user_c_id,
            user_role="parent",
        )
        assert result_c is None


# =============================================================================
# Intervention Plan Service - Negative Authorization Tests
# =============================================================================


class TestInterventionPlanServiceUnauthorizedAccess:
    """Negative test cases for InterventionPlanService authorization."""

    @pytest_asyncio.fixture
    async def setup_plan_for_user_a(
        self,
        db_session: AsyncSession,
        child_a_id: UUID,
        user_a_id: UUID,
    ):
        """Set up intervention plan for user A's child."""
        # Insert child record
        await db_session.execute(
            text(
                """
                INSERT INTO children (id, first_name, last_name, date_of_birth)
                VALUES (:id, :first_name, :last_name, :dob)
                """
            ),
            {
                "id": str(child_a_id),
                "first_name": "Child",
                "last_name": "A",
                "dob": "2020-01-01",
            },
        )

        # Insert parent-child relationship
        await db_session.execute(
            text(
                """
                INSERT INTO parent_child (parent_id, child_id)
                VALUES (:parent_id, :child_id)
                """
            ),
            {
                "parent_id": str(user_a_id),
                "child_id": str(child_a_id),
            },
        )

        # Insert intervention plan
        plan_id = uuid4()
        await db_session.execute(
            text(
                """
                INSERT INTO intervention_plans (id, child_id, title, status, start_date)
                VALUES (:id, :child_id, :title, :status, :start_date)
                """
            ),
            {
                "id": str(plan_id),
                "child_id": str(child_a_id),
                "title": "Test Plan",
                "status": "active",
                "start_date": datetime.now(timezone.utc).date().isoformat(),
            },
        )
        await db_session.commit()
        return plan_id

    @pytest.mark.asyncio
    async def test_unauthorized_user_cannot_access_plan(
        self,
        db_session: AsyncSession,
        setup_plan_for_user_a,
        user_b_id: UUID,
    ):
        """Test that unauthorized user cannot access intervention plan."""
        service = InterventionPlanService(db_session)
        plan_id = setup_plan_for_user_a

        # User B attempts to access user A's child's plan
        with pytest.raises(InterventionUnauthorizedError):
            await service.get_intervention_plan(
                plan_id=plan_id,
                user_id=user_b_id,
                user_role="parent",
            )

    @pytest.mark.asyncio
    async def test_parent_role_cannot_access_unrelated_plan(
        self,
        db_session: AsyncSession,
        setup_plan_for_user_a,
        user_b_id: UUID,
    ):
        """Test that parent role cannot access unrelated child's plan."""
        service = InterventionPlanService(db_session)
        plan_id = setup_plan_for_user_a

        with pytest.raises(InterventionUnauthorizedError):
            await service.get_intervention_plan(
                plan_id=plan_id,
                user_id=user_b_id,
                user_role="parent",
            )

    @pytest.mark.asyncio
    async def test_educator_role_cannot_access_unrelated_plan(
        self,
        db_session: AsyncSession,
        setup_plan_for_user_a,
        user_b_id: UUID,
    ):
        """Test that educator role without proper assignment cannot access plan."""
        service = InterventionPlanService(db_session)
        plan_id = setup_plan_for_user_a

        # Educator without assignment should be denied
        with pytest.raises(InterventionUnauthorizedError):
            await service.get_intervention_plan(
                plan_id=plan_id,
                user_id=user_b_id,
                user_role="educator",
            )

    @pytest.mark.asyncio
    async def test_access_denied_for_non_existent_plan(
        self,
        db_session: AsyncSession,
        non_existent_id: UUID,
        user_b_id: UUID,
    ):
        """Test that accessing non-existent plan raises error."""
        service = InterventionPlanService(db_session)

        with pytest.raises(InterventionUnauthorizedError):
            await service.get_intervention_plan(
                plan_id=non_existent_id,
                user_id=user_b_id,
                user_role="parent",
            )

    @pytest.mark.asyncio
    async def test_multiple_unauthorized_users_cannot_access_plan(
        self,
        db_session: AsyncSession,
        setup_plan_for_user_a,
        user_b_id: UUID,
        user_c_id: UUID,
    ):
        """Test that multiple unauthorized users are all blocked."""
        service = InterventionPlanService(db_session)
        plan_id = setup_plan_for_user_a

        # User B attempt
        with pytest.raises(InterventionUnauthorizedError):
            await service.get_intervention_plan(
                plan_id=plan_id,
                user_id=user_b_id,
                user_role="parent",
            )

        # User C attempt
        with pytest.raises(InterventionUnauthorizedError):
            await service.get_intervention_plan(
                plan_id=plan_id,
                user_id=user_c_id,
                user_role="parent",
            )


# =============================================================================
# Storage Service - Negative Authorization Tests
# =============================================================================


class TestStorageServiceUnauthorizedAccess:
    """Negative test cases for StorageService authorization."""

    @pytest_asyncio.fixture
    async def user_a_private_file(
        self,
        db_session: AsyncSession,
        user_a_id: UUID,
    ):
        """Create a private file owned by user A."""
        file_id = uuid4()
        await db_session.execute(
            text(
                """
                INSERT INTO files (id, filename, file_path, file_size, mime_type, uploaded_by, is_public)
                VALUES (:id, :filename, :file_path, :file_size, :mime_type, :uploaded_by, :is_public)
                """
            ),
            {
                "id": str(file_id),
                "filename": "private-file.pdf",
                "file_path": "/uploads/private-file.pdf",
                "file_size": 1024,
                "mime_type": "application/pdf",
                "uploaded_by": str(user_a_id),
                "is_public": False,
            },
        )
        await db_session.commit()
        return file_id

    @pytest.mark.asyncio
    async def test_unauthorized_user_cannot_access_private_file(
        self,
        db_session: AsyncSession,
        user_a_private_file: UUID,
        user_b_id: UUID,
    ):
        """Test that unauthorized user cannot access private file."""
        service = StorageService(db_session)

        # User B attempts to access user A's private file
        result = await service._verify_file_access(
            file_id=user_a_private_file,
            user_id=user_b_id,
        )

        # Access should be denied
        assert result is False

    @pytest.mark.asyncio
    async def test_multiple_unauthorized_users_cannot_access_private_file(
        self,
        db_session: AsyncSession,
        user_a_private_file: UUID,
        user_b_id: UUID,
        user_c_id: UUID,
    ):
        """Test that multiple unauthorized users are all blocked."""
        service = StorageService(db_session)

        # User B attempt
        result_b = await service._verify_file_access(
            file_id=user_a_private_file,
            user_id=user_b_id,
        )
        assert result_b is False

        # User C attempt
        result_c = await service._verify_file_access(
            file_id=user_a_private_file,
            user_id=user_c_id,
        )
        assert result_c is False

    @pytest.mark.asyncio
    async def test_access_denied_for_non_existent_file(
        self,
        db_session: AsyncSession,
        non_existent_id: UUID,
        user_b_id: UUID,
    ):
        """Test that accessing non-existent file returns False."""
        service = StorageService(db_session)

        result = await service._verify_file_access(
            file_id=non_existent_id,
            user_id=user_b_id,
        )

        assert result is False

    @pytest.mark.asyncio
    async def test_cannot_access_file_without_valid_user_id(
        self,
        db_session: AsyncSession,
        user_a_private_file: UUID,
        non_existent_id: UUID,
    ):
        """Test that file access requires valid user ID."""
        service = StorageService(db_session)

        # Attempt with non-existent user ID
        result = await service._verify_file_access(
            file_id=user_a_private_file,
            user_id=non_existent_id,
        )

        assert result is False


# =============================================================================
# Cross-Service Negative Test Scenarios
# =============================================================================


class TestCrossServiceUnauthorizedAccess:
    """Test unauthorized access patterns across multiple services."""

    @pytest.mark.asyncio
    async def test_same_unauthorized_user_blocked_across_all_services(
        self,
        db_session: AsyncSession,
        user_a_id: UUID,
        user_b_id: UUID,
        child_a_id: UUID,
    ):
        """Test that same unauthorized user is consistently blocked across all services."""
        # Set up test data
        await db_session.execute(
            text(
                """
                INSERT INTO children (id, first_name, last_name, date_of_birth)
                VALUES (:id, :first_name, :last_name, :dob)
                """
            ),
            {
                "id": str(child_a_id),
                "first_name": "Child",
                "last_name": "A",
                "dob": "2020-01-01",
            },
        )

        await db_session.execute(
            text(
                """
                INSERT INTO parent_child (parent_id, child_id)
                VALUES (:parent_id, :child_id)
                """
            ),
            {
                "parent_id": str(user_a_id),
                "child_id": str(child_a_id),
            },
        )
        await db_session.commit()

        # Test messaging service
        messaging_service = MessagingService(db_session)
        with pytest.raises(MessagingUnauthorizedError):
            messaging_service._verify_notification_preference_access(
                target_user_id=user_a_id,
                requesting_user_id=user_b_id,
                user_role="parent",
            )

        # Test communication service
        communication_service = CommunicationService(db_session)
        with pytest.raises(CommunicationUnauthorizedError):
            await communication_service._verify_child_access(
                child_id=child_a_id,
                user_id=user_b_id,
                user_role="parent",
            )

    @pytest.mark.asyncio
    async def test_authorization_errors_have_descriptive_messages(
        self,
        db_session: AsyncSession,
        user_a_id: UUID,
        user_b_id: UUID,
    ):
        """Test that authorization errors provide clear, descriptive messages."""
        messaging_service = MessagingService(db_session)

        # Check that error message is descriptive
        with pytest.raises(MessagingUnauthorizedError) as exc_info:
            messaging_service._verify_notification_preference_access(
                target_user_id=user_a_id,
                requesting_user_id=user_b_id,
                user_role="parent",
            )

        error_message = str(exc_info.value)
        # Error message should be descriptive and not leak sensitive info
        assert len(error_message) > 0
        assert "notification preferences" in error_message.lower()
