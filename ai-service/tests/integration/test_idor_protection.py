"""Integration tests for IDOR (Insecure Direct Object Reference) protection.

Tests verify that users cannot access or modify resources belonging to other users
by manipulating object IDs in API requests. Covers all services fixed for IDOR vulnerabilities:
- Document endpoints
- Messaging endpoints (threads, messages, notification preferences)
- Communication endpoints (child reports, home activities)
- Development profile endpoints
- Intervention plan endpoints
- Storage endpoints
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
# Test Fixtures - Users and Resources
# =============================================================================


@pytest.fixture
def user_a_id():
    """ID for user A (legitimate resource owner)."""
    return uuid4()


@pytest.fixture
def user_b_id():
    """ID for user B (unauthorized user attempting IDOR attack)."""
    return uuid4()


@pytest.fixture
def child_a_id():
    """ID for child belonging to user A."""
    return uuid4()


@pytest.fixture
def child_b_id():
    """ID for child belonging to user B."""
    return uuid4()


# =============================================================================
# Document Service IDOR Tests
# =============================================================================


class TestDocumentIDORProtection:
    """Tests for IDOR protection in document endpoints."""

    @pytest_asyncio.fixture
    async def user_a_document(
        self, db_session: AsyncSession, user_a_id: UUID
    ) -> Document:
        """Create a document owned by user A."""
        document = Document(
            id=uuid4(),
            type=DocumentType.ENROLLMENT,
            title="User A's Private Document",
            content_url="https://example.com/user-a-doc.pdf",
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
        """Create a document template owned by user A."""
        template = DocumentTemplate(
            id=uuid4(),
            name="User A's Template",
            type=DocumentType.ENROLLMENT,
            content="Template content",
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
        """Create a signature request for user A's document."""
        sig_request = SignatureRequest(
            id=uuid4(),
            document_id=user_a_document.id,
            requested_by=user_a_id,
            signer_email="usera@example.com",
            status=SignatureRequestStatus.PENDING,
        )
        db_session.add(sig_request)
        await db_session.commit()
        await db_session.refresh(sig_request)
        return sig_request

    @pytest.mark.asyncio
    async def test_cannot_access_other_users_document(
        self,
        db_session: AsyncSession,
        user_a_document: Document,
        user_b_id: UUID,
    ):
        """Test that user B cannot access user A's document."""
        service = DocumentService(db_session)

        # User B attempts to access user A's document (IDOR attack)
        has_access = service._verify_document_access(
            document=user_a_document,
            user_id=user_b_id,
        )

        assert has_access is False

    @pytest.mark.asyncio
    async def test_cannot_access_other_users_template(
        self,
        db_session: AsyncSession,
        user_a_template: DocumentTemplate,
        user_b_id: UUID,
    ):
        """Test that user B cannot access user A's template."""
        service = DocumentService(db_session)

        # User B attempts to access user A's template (IDOR attack)
        has_access = service._verify_template_access(
            template=user_a_template,
            user_id=user_b_id,
        )

        assert has_access is False

    @pytest.mark.asyncio
    async def test_cannot_access_other_users_signature_request(
        self,
        db_session: AsyncSession,
        user_a_signature_request: SignatureRequest,
        user_b_id: UUID,
    ):
        """Test that user B cannot access user A's signature request."""
        service = DocumentService(db_session)

        # User B attempts to access user A's signature request (IDOR attack)
        has_access = service._verify_signature_request_access(
            request=user_a_signature_request,
            user_id=user_b_id,
        )

        assert has_access is False

    @pytest.mark.asyncio
    async def test_owner_can_access_own_document(
        self,
        db_session: AsyncSession,
        user_a_document: Document,
        user_a_id: UUID,
    ):
        """Test that user A can access their own document (positive test)."""
        service = DocumentService(db_session)

        has_access = service._verify_document_access(
            document=user_a_document,
            user_id=user_a_id,
        )

        assert has_access is True


# =============================================================================
# Messaging Service IDOR Tests
# =============================================================================


class TestMessagingIDORProtection:
    """Tests for IDOR protection in messaging endpoints."""

    @pytest.mark.asyncio
    async def test_cannot_access_other_users_notification_preferences(
        self,
        db_session: AsyncSession,
        user_a_id: UUID,
        user_b_id: UUID,
    ):
        """Test that user B cannot access user A's notification preferences."""
        service = MessagingService(db_session)

        # User B attempts to access user A's preferences (IDOR attack)
        with pytest.raises(MessagingUnauthorizedError) as exc_info:
            service._verify_notification_preference_access(
                target_user_id=user_a_id,
                requesting_user_id=user_b_id,
                user_role="parent",
            )

        assert "notification preferences" in str(exc_info.value).lower()

    @pytest.mark.asyncio
    async def test_user_can_access_own_notification_preferences(
        self,
        db_session: AsyncSession,
        user_a_id: UUID,
    ):
        """Test that user A can access their own notification preferences (positive test)."""
        service = MessagingService(db_session)

        # Should not raise an exception
        service._verify_notification_preference_access(
            target_user_id=user_a_id,
            requesting_user_id=user_a_id,
            user_role="parent",
        )

    @pytest.mark.asyncio
    async def test_admin_can_access_any_notification_preferences(
        self,
        db_session: AsyncSession,
        user_a_id: UUID,
        user_b_id: UUID,
    ):
        """Test that admin can access any user's notification preferences."""
        service = MessagingService(db_session)

        # Admin should be allowed to access user A's preferences
        service._verify_notification_preference_access(
            target_user_id=user_a_id,
            requesting_user_id=user_b_id,
            user_role="admin",
        )


# =============================================================================
# Communication Service IDOR Tests
# =============================================================================


class TestCommunicationIDORProtection:
    """Tests for IDOR protection in communication endpoints."""

    @pytest_asyncio.fixture
    async def setup_child_parent_relationship(
        self,
        db_session: AsyncSession,
        user_a_id: UUID,
        child_a_id: UUID,
    ):
        """Set up child-parent relationship for user A."""
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
        await db_session.commit()

    @pytest.mark.asyncio
    async def test_cannot_access_other_users_child_data(
        self,
        db_session: AsyncSession,
        setup_child_parent_relationship,
        child_a_id: UUID,
        user_b_id: UUID,
    ):
        """Test that user B cannot access user A's child data."""
        service = CommunicationService(db_session)

        # User B attempts to access user A's child (IDOR attack)
        with pytest.raises(CommunicationUnauthorizedError):
            await service._verify_child_access(
                child_id=child_a_id,
                user_id=user_b_id,
                user_role="parent",
            )

    @pytest.mark.asyncio
    async def test_parent_can_access_own_child_data(
        self,
        db_session: AsyncSession,
        setup_child_parent_relationship,
        child_a_id: UUID,
        user_a_id: UUID,
    ):
        """Test that parent can access their own child data (positive test)."""
        service = CommunicationService(db_session)

        # Should not raise an exception
        await service._verify_child_access(
            child_id=child_a_id,
            user_id=user_a_id,
            user_role="parent",
        )

    @pytest.mark.asyncio
    async def test_admin_can_access_any_child_data(
        self,
        db_session: AsyncSession,
        setup_child_parent_relationship,
        child_a_id: UUID,
        user_b_id: UUID,
    ):
        """Test that admin can access any child data."""
        service = CommunicationService(db_session)

        # Admin should be allowed to access any child
        await service._verify_child_access(
            child_id=child_a_id,
            user_id=user_b_id,
            user_role="admin",
        )


# =============================================================================
# Development Profile Service IDOR Tests
# =============================================================================


class TestDevelopmentProfileIDORProtection:
    """Tests for IDOR protection in development profile endpoints."""

    @pytest_asyncio.fixture
    async def setup_profiles(
        self,
        db_session: AsyncSession,
        child_a_id: UUID,
        user_a_id: UUID,
    ):
        """Set up development profile and child for user A."""
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
    async def test_cannot_access_other_users_development_profile(
        self,
        db_session: AsyncSession,
        setup_profiles,
        user_b_id: UUID,
    ):
        """Test that user B cannot access user A's child's development profile."""
        service = DevelopmentProfileService(db_session)
        profile_id = setup_profiles

        # User B attempts to access user A's child's profile (IDOR attack)
        result = await service.get_development_profile(
            profile_id=profile_id,
            user_id=user_b_id,
            user_role="parent",
        )

        # Should return None or raise exception (depending on implementation)
        assert result is None

    @pytest.mark.asyncio
    async def test_parent_can_access_own_child_development_profile(
        self,
        db_session: AsyncSession,
        setup_profiles,
        user_a_id: UUID,
    ):
        """Test that parent can access their own child's development profile (positive test)."""
        service = DevelopmentProfileService(db_session)
        profile_id = setup_profiles

        # Parent should be able to access their child's profile
        result = await service.get_development_profile(
            profile_id=profile_id,
            user_id=user_a_id,
            user_role="parent",
        )

        # Should return profile data
        assert result is not None
        assert result.id == profile_id


# =============================================================================
# Intervention Plan Service IDOR Tests
# =============================================================================


class TestInterventionPlanIDORProtection:
    """Tests for IDOR protection in intervention plan endpoints."""

    @pytest_asyncio.fixture
    async def setup_intervention_plan(
        self,
        db_session: AsyncSession,
        child_a_id: UUID,
        user_a_id: UUID,
    ):
        """Set up intervention plan and child for user A."""
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
    async def test_cannot_access_other_users_intervention_plan(
        self,
        db_session: AsyncSession,
        setup_intervention_plan,
        user_b_id: UUID,
    ):
        """Test that user B cannot access user A's child's intervention plan."""
        service = InterventionPlanService(db_session)
        plan_id = setup_intervention_plan

        # User B attempts to access user A's child's plan (IDOR attack)
        with pytest.raises(InterventionUnauthorizedError):
            await service.get_intervention_plan(
                plan_id=plan_id,
                user_id=user_b_id,
                user_role="parent",
            )

    @pytest.mark.asyncio
    async def test_parent_can_access_own_child_intervention_plan(
        self,
        db_session: AsyncSession,
        setup_intervention_plan,
        user_a_id: UUID,
    ):
        """Test that parent can access their own child's intervention plan (positive test)."""
        service = InterventionPlanService(db_session)
        plan_id = setup_intervention_plan

        # Parent should be able to access their child's plan
        result = await service.get_intervention_plan(
            plan_id=plan_id,
            user_id=user_a_id,
            user_role="parent",
        )

        # Should return plan data
        assert result is not None
        assert result.id == plan_id


# =============================================================================
# Storage Service IDOR Tests
# =============================================================================


class TestStorageIDORProtection:
    """Tests for IDOR protection in storage endpoints."""

    @pytest_asyncio.fixture
    async def user_a_file(
        self,
        db_session: AsyncSession,
        user_a_id: UUID,
    ):
        """Create a file record owned by user A."""
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
                "filename": "user-a-private-file.pdf",
                "file_path": "/uploads/user-a-private-file.pdf",
                "file_size": 1024,
                "mime_type": "application/pdf",
                "uploaded_by": str(user_a_id),
                "is_public": False,
            },
        )
        await db_session.commit()
        return file_id

    @pytest.mark.asyncio
    async def test_cannot_access_other_users_private_file(
        self,
        db_session: AsyncSession,
        user_a_file: UUID,
        user_b_id: UUID,
    ):
        """Test that user B cannot access user A's private file."""
        service = StorageService(db_session)

        # User B attempts to access user A's private file (IDOR attack)
        result = await service._verify_file_access(
            file_id=user_a_file,
            user_id=user_b_id,
        )

        assert result is False

    @pytest.mark.asyncio
    async def test_owner_can_access_own_private_file(
        self,
        db_session: AsyncSession,
        user_a_file: UUID,
        user_a_id: UUID,
    ):
        """Test that owner can access their own private file (positive test)."""
        service = StorageService(db_session)

        # Owner should be able to access their own file
        result = await service._verify_file_access(
            file_id=user_a_file,
            user_id=user_a_id,
        )

        assert result is True

    @pytest.mark.asyncio
    async def test_anyone_can_access_public_file(
        self,
        db_session: AsyncSession,
        user_a_id: UUID,
        user_b_id: UUID,
    ):
        """Test that anyone can access public files."""
        # Create a public file
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
                "filename": "public-file.pdf",
                "file_path": "/uploads/public-file.pdf",
                "file_size": 1024,
                "mime_type": "application/pdf",
                "uploaded_by": str(user_a_id),
                "is_public": True,
            },
        )
        await db_session.commit()

        service = StorageService(db_session)

        # User B should be able to access public file
        result = await service._verify_file_access(
            file_id=file_id,
            user_id=user_b_id,
        )

        assert result is True
