"""Integration tests for IDOR (Insecure Direct Object Reference) protection.

Tests for API-level IDOR protection across fixed endpoints to ensure
unauthorized users cannot access resources belonging to other users.

Tests cover:
- Document endpoints (GET/PATCH /api/v1/documents/{document_id})
- 403 Forbidden responses for unauthorized access attempts
- Successful access by resource owners
- Resource isolation between different users

NOTE: Development profile IDOR protection is tested at the service layer
in test_development_profile_authorization.py due to async SQLAlchemy test
infrastructure limitations. The authorization patterns are identical across
all services (documents, development profiles, etc.).
"""

from __future__ import annotations

from uuid import UUID, uuid4

import pytest
from httpx import AsyncClient

from tests.conftest import create_test_token


# =============================================================================
# Fixtures
# =============================================================================


@pytest.fixture
def user_a_id() -> UUID:
    """Fixture providing user A's ID."""
    return uuid4()


@pytest.fixture
def user_b_id() -> UUID:
    """Fixture providing user B's ID."""
    return uuid4()


@pytest.fixture
def user_a_token(user_a_id: UUID) -> str:
    """Create a valid JWT token for user A."""
    return create_test_token(
        subject=str(user_a_id),
        expires_delta_seconds=3600,
        additional_claims={
            "email": "usera@example.com",
            "role": "educator",
        },
    )


@pytest.fixture
def user_b_token(user_b_id: UUID) -> str:
    """Create a valid JWT token for user B."""
    return create_test_token(
        subject=str(user_b_id),
        expires_delta_seconds=3600,
        additional_claims={
            "email": "userb@example.com",
            "role": "educator",
        },
    )


@pytest.fixture
def user_a_headers(user_a_token: str) -> dict[str, str]:
    """Create authorization headers for user A."""
    return {"Authorization": f"Bearer {user_a_token}"}


@pytest.fixture
def user_b_headers(user_b_token: str) -> dict[str, str]:
    """Create authorization headers for user B."""
    return {"Authorization": f"Bearer {user_b_token}"}


# =============================================================================
# Document Endpoints - IDOR Protection Tests
# =============================================================================


class TestDocumentIDORProtection:
    """Test suite for IDOR protection on document endpoints."""

    @pytest.mark.asyncio
    async def test_get_document_by_id_idor_protection(
        self,
        client: AsyncClient,
        user_a_id: UUID,
        user_a_headers: dict[str, str],
        user_b_headers: dict[str, str],
    ):
        """Test that user B cannot access user A's document via GET /api/v1/documents/{id}."""
        # User A creates a document
        create_response = await client.post(
            "/api/v1/documents",
            json={
                "type": "enrollment",
                "title": "User A's Enrollment Form",
                "content_url": "https://example.com/usera-enrollment.pdf",
                "created_by": str(user_a_id),
            },
            headers=user_a_headers,
        )
        assert create_response.status_code == 201
        document_id = create_response.json()["id"]

        # User A can access their own document
        response_a = await client.get(
            f"/api/v1/documents/{document_id}",
            headers=user_a_headers,
        )
        assert response_a.status_code == 200
        assert response_a.json()["id"] == document_id
        assert response_a.json()["title"] == "User A's Enrollment Form"

        # User B attempts to access user A's document - should get 403 Forbidden
        response_b = await client.get(
            f"/api/v1/documents/{document_id}",
            headers=user_b_headers,
        )
        assert response_b.status_code == 403
        # Verify it's an authorization error (various messages possible)
        detail = response_b.json()["detail"].lower()
        assert any(word in detail for word in ["not authorized", "permission", "access denied", "forbidden"])

    @pytest.mark.asyncio
    async def test_update_document_idor_protection(
        self,
        client: AsyncClient,
        user_a_id: UUID,
        user_a_headers: dict[str, str],
        user_b_headers: dict[str, str],
    ):
        """Test that user B cannot update user A's document via PATCH /api/v1/documents/{id}."""
        # User A creates a document
        create_response = await client.post(
            "/api/v1/documents",
            json={
                "type": "permission",
                "title": "User A's Permission Form",
                "content_url": "https://example.com/usera-permission.pdf",
                "created_by": str(user_a_id),
            },
            headers=user_a_headers,
        )
        assert create_response.status_code == 201
        document_id = create_response.json()["id"]

        # User A can update their own document
        update_response_a = await client.patch(
            f"/api/v1/documents/{document_id}",
            json={
                "title": "User A's Updated Permission Form",
                "status": "pending",
            },
            headers=user_a_headers,
        )
        assert update_response_a.status_code == 200
        assert update_response_a.json()["title"] == "User A's Updated Permission Form"

        # User B attempts to update user A's document - should get 403 Forbidden
        update_response_b = await client.patch(
            f"/api/v1/documents/{document_id}",
            json={
                "title": "Malicious Update",
                "status": "signed",
            },
            headers=user_b_headers,
        )
        assert update_response_b.status_code == 403
        # Verify it's an authorization error (various messages possible)
        detail = update_response_b.json()["detail"].lower()
        assert any(word in detail for word in ["not authorized", "permission", "access denied", "forbidden"])

        # Verify document was not modified by user B
        verify_response = await client.get(
            f"/api/v1/documents/{document_id}",
            headers=user_a_headers,
        )
        assert verify_response.status_code == 200
        assert verify_response.json()["title"] == "User A's Updated Permission Form"
        assert verify_response.json()["status"] == "pending"  # Not "signed"

    @pytest.mark.asyncio
    async def test_document_access_isolation_between_users(
        self,
        client: AsyncClient,
        user_a_id: UUID,
        user_b_id: UUID,
        user_a_headers: dict[str, str],
        user_b_headers: dict[str, str],
    ):
        """Test that documents are properly isolated between different users."""
        # User A creates a document
        response_a = await client.post(
            "/api/v1/documents",
            json={
                "type": "financial",
                "title": "User A's Financial Document",
                "content_url": "https://example.com/usera-financial.pdf",
                "created_by": str(user_a_id),
            },
            headers=user_a_headers,
        )
        assert response_a.status_code == 201
        document_a_id = response_a.json()["id"]

        # User B creates their own document
        response_b = await client.post(
            "/api/v1/documents",
            json={
                "type": "financial",
                "title": "User B's Financial Document",
                "content_url": "https://example.com/userb-financial.pdf",
                "created_by": str(user_b_id),
            },
            headers=user_b_headers,
        )
        assert response_b.status_code == 201
        document_b_id = response_b.json()["id"]

        # User A can only access their own document
        a_access_own = await client.get(
            f"/api/v1/documents/{document_a_id}",
            headers=user_a_headers,
        )
        assert a_access_own.status_code == 200
        assert a_access_own.json()["title"] == "User A's Financial Document"

        # User A cannot access user B's document
        a_access_b = await client.get(
            f"/api/v1/documents/{document_b_id}",
            headers=user_a_headers,
        )
        assert a_access_b.status_code == 403

        # User B can only access their own document
        b_access_own = await client.get(
            f"/api/v1/documents/{document_b_id}",
            headers=user_b_headers,
        )
        assert b_access_own.status_code == 200
        assert b_access_own.json()["title"] == "User B's Financial Document"

        # User B cannot access user A's document
        b_access_a = await client.get(
            f"/api/v1/documents/{document_a_id}",
            headers=user_b_headers,
        )
        assert b_access_a.status_code == 403

    @pytest.mark.asyncio
    async def test_cross_resource_idor_protection(
        self,
        client: AsyncClient,
        user_a_id: UUID,
        user_a_headers: dict[str, str],
        user_b_headers: dict[str, str],
    ):
        """Test that manipulating resource IDs is properly blocked across operations."""
        # Create document as user A
        doc_response = await client.post(
            "/api/v1/documents",
            json={
                "type": "enrollment",
                "title": "User A's Document",
                "content_url": "https://example.com/doc.pdf",
                "created_by": str(user_a_id),
            },
            headers=user_a_headers,
        )
        document_id = doc_response.json()["id"]

        # User B cannot access the resource with GET
        doc_access = await client.get(
            f"/api/v1/documents/{document_id}",
            headers=user_b_headers,
        )
        assert doc_access.status_code == 403

        # User B cannot update the resource with PATCH
        doc_update = await client.patch(
            f"/api/v1/documents/{document_id}",
            json={"title": "Malicious update"},
            headers=user_b_headers,
        )
        assert doc_update.status_code == 403
