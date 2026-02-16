"""Unit tests for document API routes.

Tests for document template endpoints, document creation endpoints,
signature endpoints, and authentication/authorization.
"""

from __future__ import annotations

from datetime import datetime, timedelta, timezone
from uuid import UUID, uuid4

import pytest
from httpx import AsyncClient
from sqlalchemy.ext.asyncio import AsyncSession

from app.models.document import DocumentStatus, DocumentType


# ============================================================================
# Template Endpoints Tests
# ============================================================================


@pytest.mark.asyncio
async def test_create_template_success(client: AsyncClient, auth_headers: dict, test_user_id: UUID):
    """Test POST /api/v1/documents/templates - successful creation."""
    response = await client.post(
        "/api/v1/documents/templates",
        json={
            "name": "Standard Enrollment Template",
            "type": "enrollment",
            "description": "Template for enrollment forms",
            "template_content": "<html><body>{{childName}}</body></html>",
            "required_fields": ["childName", "parentName"],
            "created_by": str(test_user_id),
        },
        headers=auth_headers,
    )

    assert response.status_code == 201
    data = response.json()
    assert data["name"] == "Standard Enrollment Template"
    assert data["type"] == "enrollment"
    assert data["version"] == 1
    assert data["is_active"] is True


@pytest.mark.asyncio
async def test_create_template_requires_auth(client: AsyncClient, test_user_id: UUID):
    """Test POST /api/v1/documents/templates - requires authentication."""
    response = await client.post(
        "/api/v1/documents/templates",
        json={
            "name": "Test Template",
            "type": "permission",
            "template_content": "<html>Test</html>",
            "created_by": str(test_user_id),
        },
    )

    assert response.status_code == 401


@pytest.mark.asyncio
async def test_get_template_success(client: AsyncClient, auth_headers: dict, test_user_id: UUID):
    """Test GET /api/v1/documents/templates/{id} - successful retrieval."""
    # Create template first
    create_response = await client.post(
        "/api/v1/documents/templates",
        json={
            "name": "Test Template",
            "type": "medical",
            "template_content": "<html>Medical Form</html>",
            "created_by": str(test_user_id),
        },
        headers=auth_headers,
    )
    template_id = create_response.json()["id"]

    # Get template
    response = await client.get(
        f"/api/v1/documents/templates/{template_id}",
        headers=auth_headers,
    )

    assert response.status_code == 200
    data = response.json()
    assert data["id"] == template_id
    assert data["name"] == "Test Template"


@pytest.mark.asyncio
async def test_get_template_not_found(client: AsyncClient, auth_headers: dict):
    """Test GET /api/v1/documents/templates/{id} - template not found."""
    fake_id = str(uuid4())

    response = await client.get(
        f"/api/v1/documents/templates/{fake_id}",
        headers=auth_headers,
    )

    assert response.status_code == 404


@pytest.mark.asyncio
async def test_list_templates_pagination(client: AsyncClient, auth_headers: dict, test_user_id: UUID):
    """Test GET /api/v1/documents/templates - pagination works."""
    # Create 5 templates
    for i in range(5):
        await client.post(
            "/api/v1/documents/templates",
            json={
                "name": f"Template {i}",
                "type": "enrollment",
                "template_content": f"<html>Template {i}</html>",
                "created_by": str(test_user_id),
            },
            headers=auth_headers,
        )

    # Get first page (limit=2)
    response = await client.get(
        "/api/v1/documents/templates?skip=0&limit=2",
        headers=auth_headers,
    )

    assert response.status_code == 200
    data = response.json()
    assert "templates" in data
    assert len(data["templates"]) <= 2


@pytest.mark.asyncio
async def test_update_template_success(client: AsyncClient, auth_headers: dict, test_user_id: UUID):
    """Test PATCH /api/v1/documents/templates/{id} - successful update."""
    # Create template
    create_response = await client.post(
        "/api/v1/documents/templates",
        json={
            "name": "Original Template",
            "type": "financial",
            "template_content": "<html>v1</html>",
            "created_by": str(test_user_id),
        },
        headers=auth_headers,
    )
    template_id = create_response.json()["id"]

    # Update template
    response = await client.patch(
        f"/api/v1/documents/templates/{template_id}",
        json={
            "name": "Updated Template",
            "template_content": "<html>v2</html>",
        },
        headers=auth_headers,
    )

    assert response.status_code == 200
    data = response.json()
    assert data["name"] == "Updated Template"
    assert data["version"] == 2  # Version should increment


@pytest.mark.asyncio
async def test_delete_template_success(client: AsyncClient, auth_headers: dict, test_user_id: UUID):
    """Test DELETE /api/v1/documents/templates/{id} - successful deletion."""
    # Create template
    create_response = await client.post(
        "/api/v1/documents/templates",
        json={
            "name": "To Delete",
            "type": "policy",
            "template_content": "<html>Delete me</html>",
            "created_by": str(test_user_id),
        },
        headers=auth_headers,
    )
    template_id = create_response.json()["id"]

    # Delete template
    response = await client.delete(
        f"/api/v1/documents/templates/{template_id}",
        headers=auth_headers,
    )

    assert response.status_code == 200
    data = response.json()
    assert data["message"] == "Template deleted successfully"


# ============================================================================
# Document Endpoints Tests
# ============================================================================


@pytest.mark.asyncio
async def test_create_document_success(client: AsyncClient, auth_headers: dict, test_user_id: UUID):
    """Test POST /api/v1/documents - successful document creation."""
    response = await client.post(
        "/api/v1/documents",
        json={
            "type": "enrollment",
            "title": "Enrollment Form 2024",
            "content_url": "https://example.com/enrollment.pdf",
            "created_by": str(test_user_id),
        },
        headers=auth_headers,
    )

    assert response.status_code == 201
    data = response.json()
    assert data["title"] == "Enrollment Form 2024"
    assert data["type"] == "enrollment"
    assert data["status"] == "draft"


@pytest.mark.asyncio
async def test_create_document_from_template_success(
    client: AsyncClient, auth_headers: dict, test_user_id: UUID
):
    """Test POST /api/v1/documents/from-template/{template_id} - successful creation."""
    # Create template first
    template_response = await client.post(
        "/api/v1/documents/templates",
        json={
            "name": "Enrollment Template",
            "type": "enrollment",
            "template_content": "<html>{{childName}}</html>",
            "created_by": str(test_user_id),
        },
        headers=auth_headers,
    )
    template_id = template_response.json()["id"]

    # Create document from template
    response = await client.post(
        f"/api/v1/documents/from-template/{template_id}",
        json={
            "type": "enrollment",
            "title": "Child Enrollment",
            "content_url": "https://example.com/child-enrollment.pdf",
            "created_by": str(test_user_id),
        },
        headers=auth_headers,
    )

    assert response.status_code == 201
    data = response.json()
    assert data["title"] == "Child Enrollment"
    assert data["type"] == "enrollment"


@pytest.mark.asyncio
async def test_get_document_success(client: AsyncClient, auth_headers: dict, test_user_id: UUID):
    """Test GET /api/v1/documents/{id} - successful retrieval."""
    # Create document first
    create_response = await client.post(
        "/api/v1/documents",
        json={
            "type": "permission",
            "title": "Permission Slip",
            "content_url": "https://example.com/permission.pdf",
            "created_by": str(test_user_id),
        },
        headers=auth_headers,
    )
    document_id = create_response.json()["id"]

    # Get document
    response = await client.get(
        f"/api/v1/documents/{document_id}",
        headers=auth_headers,
    )

    assert response.status_code == 200
    data = response.json()
    assert data["id"] == document_id
    assert data["title"] == "Permission Slip"


@pytest.mark.asyncio
async def test_get_document_not_found(client: AsyncClient, auth_headers: dict):
    """Test GET /api/v1/documents/{id} - document not found."""
    fake_id = str(uuid4())

    response = await client.get(
        f"/api/v1/documents/{fake_id}",
        headers=auth_headers,
    )

    assert response.status_code == 404


@pytest.mark.asyncio
async def test_list_documents_with_filters(
    client: AsyncClient, auth_headers: dict, test_user_id: UUID
):
    """Test GET /api/v1/documents - filtering by status."""
    # Create documents with different statuses
    await client.post(
        "/api/v1/documents",
        json={
            "type": "enrollment",
            "title": "Draft Document",
            "content_url": "https://example.com/draft.pdf",
            "created_by": str(test_user_id),
        },
        headers=auth_headers,
    )

    # Get documents filtered by status
    response = await client.get(
        "/api/v1/documents?status=draft",
        headers=auth_headers,
    )

    assert response.status_code == 200
    data = response.json()
    assert "documents" in data
    # All returned documents should have status=draft
    for doc in data["documents"]:
        assert doc["status"] == "draft"


@pytest.mark.asyncio
async def test_update_document_success(client: AsyncClient, auth_headers: dict, test_user_id: UUID):
    """Test PATCH /api/v1/documents/{id} - successful update."""
    # Create document
    create_response = await client.post(
        "/api/v1/documents",
        json={
            "type": "policy",
            "title": "Policy Document",
            "content_url": "https://example.com/policy.pdf",
            "created_by": str(test_user_id),
        },
        headers=auth_headers,
    )
    document_id = create_response.json()["id"]

    # Update document status
    response = await client.patch(
        f"/api/v1/documents/{document_id}",
        json={
            "status": "pending",
            "title": "Updated Policy Document",
        },
        headers=auth_headers,
    )

    assert response.status_code == 200
    data = response.json()
    assert data["status"] == "pending"
    assert data["title"] == "Updated Policy Document"


# ============================================================================
# Signature Endpoints Tests
# ============================================================================


@pytest.mark.asyncio
async def test_create_signature_success(client: AsyncClient, auth_headers: dict, test_user_id: UUID):
    """Test POST /api/v1/documents/{id}/signatures - successful signature creation."""
    # Create document
    create_response = await client.post(
        "/api/v1/documents",
        json={
            "type": "financial",
            "title": "Financial Agreement",
            "content_url": "https://example.com/agreement.pdf",
            "created_by": str(test_user_id),
        },
        headers=auth_headers,
    )
    document_id = create_response.json()["id"]

    # Update to PENDING status
    await client.patch(
        f"/api/v1/documents/{document_id}",
        json={"status": "pending"},
        headers=auth_headers,
    )

    # Create signature
    response = await client.post(
        f"/api/v1/documents/{document_id}/signatures",
        json={
            "document_id": document_id,
            "signer_id": str(test_user_id),
            "signature_image_url": "https://example.com/signatures/sig123.png",
            "ip_address": "192.168.1.100",
            "device_info": "Mozilla/5.0 (iPhone)",
        },
        headers=auth_headers,
    )

    assert response.status_code == 201
    data = response.json()
    assert data["document_id"] == document_id
    assert data["signer_id"] == str(test_user_id)


@pytest.mark.asyncio
async def test_get_signatures_for_document(
    client: AsyncClient, auth_headers: dict, test_user_id: UUID
):
    """Test GET /api/v1/documents/{id}/signatures - retrieve signatures."""
    # Create document
    create_response = await client.post(
        "/api/v1/documents",
        json={
            "type": "permission",
            "title": "Permission Slip",
            "content_url": "https://example.com/permission.pdf",
            "created_by": str(test_user_id),
        },
        headers=auth_headers,
    )
    document_id = create_response.json()["id"]

    # Update to PENDING
    await client.patch(
        f"/api/v1/documents/{document_id}",
        json={"status": "pending"},
        headers=auth_headers,
    )

    # Create signature
    await client.post(
        f"/api/v1/documents/{document_id}/signatures",
        json={
            "document_id": document_id,
            "signer_id": str(test_user_id),
            "signature_image_url": "https://example.com/sig.png",
            "ip_address": "127.0.0.1",
        },
        headers=auth_headers,
    )

    # Get signatures
    response = await client.get(
        f"/api/v1/documents/{document_id}/signatures",
        headers=auth_headers,
    )

    assert response.status_code == 200
    data = response.json()
    assert "signatures" in data
    assert len(data["signatures"]) >= 1


# ============================================================================
# Signature Request Endpoints Tests
# ============================================================================


@pytest.mark.asyncio
async def test_send_signature_request_success(
    client: AsyncClient, auth_headers: dict, test_user_id: UUID
):
    """Test POST /api/v1/documents/signature-requests - send signature request."""
    # Create document
    create_response = await client.post(
        "/api/v1/documents",
        json={
            "type": "enrollment",
            "title": "Enrollment Form",
            "content_url": "https://example.com/enrollment.pdf",
            "created_by": str(test_user_id),
        },
        headers=auth_headers,
    )
    document_id = create_response.json()["id"]

    signer_id = str(uuid4())
    expires_at = (datetime.now(timezone.utc) + timedelta(days=7)).isoformat()

    # Send signature request
    response = await client.post(
        "/api/v1/documents/signature-requests",
        json={
            "document_id": document_id,
            "requester_id": str(test_user_id),
            "signer_id": signer_id,
            "expires_at": expires_at,
            "message": "Please sign this enrollment form",
        },
        headers=auth_headers,
    )

    assert response.status_code == 201
    data = response.json()
    assert data["status"] == "sent"
    assert data["document_id"] == document_id
    assert data["signer_id"] == signer_id


@pytest.mark.asyncio
async def test_mark_signature_request_viewed(
    client: AsyncClient, auth_headers: dict, test_user_id: UUID
):
    """Test PATCH /api/v1/documents/signature-requests/{id}/viewed - mark as viewed."""
    # Create document
    create_response = await client.post(
        "/api/v1/documents",
        json={
            "type": "permission",
            "title": "Permission Form",
            "content_url": "https://example.com/permission.pdf",
            "created_by": str(test_user_id),
        },
        headers=auth_headers,
    )
    document_id = create_response.json()["id"]

    signer_id = str(uuid4())

    # Send signature request
    request_response = await client.post(
        "/api/v1/documents/signature-requests",
        json={
            "document_id": document_id,
            "requester_id": str(test_user_id),
            "signer_id": signer_id,
        },
        headers=auth_headers,
    )
    request_id = request_response.json()["id"]

    # Mark as viewed
    response = await client.patch(
        f"/api/v1/documents/signature-requests/{request_id}/viewed",
        headers=auth_headers,
    )

    assert response.status_code == 200
    data = response.json()
    assert data["status"] == "viewed"
    assert data["viewed_at"] is not None


@pytest.mark.asyncio
async def test_get_signature_requests_for_signer(
    client: AsyncClient, auth_headers: dict, test_user_id: UUID
):
    """Test GET /api/v1/documents/signature-requests - list requests for signer."""
    # Create document
    create_response = await client.post(
        "/api/v1/documents",
        json={
            "type": "financial",
            "title": "Financial Form",
            "content_url": "https://example.com/financial.pdf",
            "created_by": str(test_user_id),
        },
        headers=auth_headers,
    )
    document_id = create_response.json()["id"]

    # Send signature request
    await client.post(
        "/api/v1/documents/signature-requests",
        json={
            "document_id": document_id,
            "requester_id": str(test_user_id),
            "signer_id": str(test_user_id),  # Request to current user
        },
        headers=auth_headers,
    )

    # Get requests for current user
    response = await client.get(
        "/api/v1/documents/signature-requests",
        headers=auth_headers,
    )

    assert response.status_code == 200
    data = response.json()
    assert "signature_requests" in data


# ============================================================================
# Dashboard Endpoint Tests
# ============================================================================


@pytest.mark.asyncio
async def test_get_dashboard_success(client: AsyncClient, auth_headers: dict, test_user_id: UUID):
    """Test GET /api/v1/documents/dashboard - dashboard statistics."""
    # Create some documents
    for i, status in enumerate(["draft", "pending", "signed"]):
        create_response = await client.post(
            "/api/v1/documents",
            json={
                "type": "enrollment",
                "title": f"Document {i}",
                "content_url": f"https://example.com/{status}.pdf",
                "created_by": str(test_user_id),
            },
            headers=auth_headers,
        )

        if status != "draft":
            document_id = create_response.json()["id"]
            await client.patch(
                f"/api/v1/documents/{document_id}",
                json={"status": status},
                headers=auth_headers,
            )

    # Get dashboard
    response = await client.get(
        "/api/v1/documents/dashboard",
        headers=auth_headers,
    )

    assert response.status_code == 200
    data = response.json()
    assert "summary" in data
    assert "total_documents" in data["summary"]
    assert data["summary"]["total_documents"] >= 3


# ============================================================================
# Audit Log Endpoint Tests
# ============================================================================


@pytest.mark.asyncio
async def test_get_audit_logs_for_document(
    client: AsyncClient, auth_headers: dict, test_user_id: UUID
):
    """Test GET /api/v1/documents/{id}/audit-logs - retrieve audit trail."""
    # Create document (should auto-create audit log)
    create_response = await client.post(
        "/api/v1/documents",
        json={
            "type": "medical",
            "title": "Medical Form",
            "content_url": "https://example.com/medical.pdf",
            "created_by": str(test_user_id),
        },
        headers=auth_headers,
    )
    document_id = create_response.json()["id"]

    # Get audit logs
    response = await client.get(
        f"/api/v1/documents/{document_id}/audit-logs",
        headers=auth_headers,
    )

    assert response.status_code == 200
    data = response.json()
    assert "audit_logs" in data
    # Should have at least the DOCUMENT_CREATED audit log
    assert len(data["audit_logs"]) >= 1


# ============================================================================
# Error Handling Tests
# ============================================================================


@pytest.mark.asyncio
async def test_invalid_document_type_returns_422(
    client: AsyncClient, auth_headers: dict, test_user_id: UUID
):
    """Test that invalid document type returns 422 validation error."""
    response = await client.post(
        "/api/v1/documents",
        json={
            "type": "invalid_type",  # Invalid enum value
            "title": "Test Document",
            "content_url": "https://example.com/test.pdf",
            "created_by": str(test_user_id),
        },
        headers=auth_headers,
    )

    assert response.status_code == 422


@pytest.mark.asyncio
async def test_missing_required_fields_returns_422(
    client: AsyncClient, auth_headers: dict
):
    """Test that missing required fields returns 422 validation error."""
    response = await client.post(
        "/api/v1/documents/templates",
        json={
            "name": "Incomplete Template",
            # Missing required fields: type, template_content, created_by
        },
        headers=auth_headers,
    )

    assert response.status_code == 422
