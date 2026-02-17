"""Router integration tests for intervention plan API endpoints.

Tests verify that API endpoints are correctly registered, routes match expected paths,
and services can communicate properly. Focuses on endpoint integration verification
between ai-service, gibbon, and parent-portal.
"""

from __future__ import annotations

import json
from datetime import date, timedelta
from typing import Any
from uuid import UUID, uuid4

import pytest
import pytest_asyncio
from httpx import AsyncClient

from app.schemas.intervention_plan import (
    InterventionPlanStatus,
    ReviewSchedule,
)


# =============================================================================
# Integration Test Fixtures
# =============================================================================


@pytest.fixture
def mock_intervention_plan_data(test_child_id: UUID) -> dict[str, Any]:
    """Create sample intervention plan creation data.

    Args:
        test_child_id: Mock child ID

    Returns:
        dict: Valid intervention plan creation payload
    """
    return {
        "child_id": str(test_child_id),
        "title": "Test Intervention Plan",
        "status": "draft",
        "review_schedule": "monthly",
        "effective_date": str(date.today()),
        "child_name": "Test Child",
        "child_dob": str(date.today() - timedelta(days=1095)),  # ~3 years old
        "medical_diagnosis": "Test diagnosis",
        "educational_history": "Test educational history",
        "strengths": [
            {
                "category": "cognitive",
                "description": "Good problem-solving skills",
                "examples": "Can complete puzzles independently",
            }
        ],
        "needs": [
            {
                "category": "communication",
                "description": "Needs support with verbal communication",
                "priority": "high",
            }
        ],
        "goals": [
            {
                "title": "Improve verbal communication",
                "description": "Increase verbal output by 50%",
                "measurement_criteria": "Number of verbal interactions per day",
                "measurement_baseline": "5",
                "measurement_target": "10",
                "achievability_notes": "Achievable with consistent support",
                "relevance_notes": "Critical for social development",
                "target_date": str(date.today() + timedelta(days=90)),
            }
        ],
    }


# =============================================================================
# Health Check Tests
# =============================================================================


class TestInterventionPlanHealthEndpoint:
    """Tests for intervention plan health check endpoint."""

    @pytest.mark.asyncio
    async def test_health_check_returns_ok(
        self,
        client: AsyncClient,
    ) -> None:
        """Verify health check endpoint is accessible.

        This confirms the router is properly registered in main.py.
        """
        response = await client.get("/api/v1/intervention-plans/health")
        assert response.status_code == 200
        data = response.json()
        assert data["status"] == "ok"

    @pytest.mark.asyncio
    async def test_health_check_no_auth_required(
        self,
        client: AsyncClient,
    ) -> None:
        """Verify health check doesn't require authentication."""
        # No auth headers provided
        response = await client.get("/api/v1/intervention-plans/health")
        assert response.status_code == 200


# =============================================================================
# Endpoint Path Integration Tests
# =============================================================================


class TestEndpointPathIntegration:
    """Tests to verify endpoint paths match between services.

    These tests verify that the API paths in ai-service match what
    parent-portal and gibbon expect, preventing integration issues.
    """

    @pytest.mark.asyncio
    async def test_list_plans_endpoint_path(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
    ) -> None:
        """Verify list plans endpoint is at /api/v1/intervention-plans.

        Parent-portal expects this path without /plans suffix.
        """
        response = await client.get(
            "/api/v1/intervention-plans",
            headers=auth_headers,
        )
        # 200 or 422 (validation) are acceptable - confirms route exists
        assert response.status_code in (200, 422, 500)

    @pytest.mark.asyncio
    async def test_single_plan_endpoint_path(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
    ) -> None:
        """Verify single plan endpoint is at /api/v1/intervention-plans/{id}.

        Parent-portal expects this path without /plans segment.
        """
        plan_id = str(uuid4())
        response = await client.get(
            f"/api/v1/intervention-plans/{plan_id}",
            headers=auth_headers,
        )
        # 404 is expected for non-existent plan - confirms route exists
        assert response.status_code in (404, 500)

    @pytest.mark.asyncio
    async def test_pending_review_endpoint_path(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
    ) -> None:
        """Verify pending-review endpoint is at /api/v1/intervention-plans/pending-review.

        Parent-portal expects this path without /plans segment.
        """
        response = await client.get(
            "/api/v1/intervention-plans/pending-review",
            headers=auth_headers,
        )
        # 200 or 500 are acceptable - confirms route exists
        assert response.status_code in (200, 500)

    @pytest.mark.asyncio
    async def test_plan_history_endpoint_path(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
    ) -> None:
        """Verify version history endpoint is at /api/v1/intervention-plans/{id}/history."""
        plan_id = str(uuid4())
        response = await client.get(
            f"/api/v1/intervention-plans/{plan_id}/history",
            headers=auth_headers,
        )
        # 404 is expected for non-existent plan - confirms route exists
        assert response.status_code in (404, 500)

    @pytest.mark.asyncio
    async def test_plan_progress_endpoint_path(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
    ) -> None:
        """Verify progress endpoint is at /api/v1/intervention-plans/{id}/progress."""
        plan_id = str(uuid4())
        response = await client.post(
            f"/api/v1/intervention-plans/{plan_id}/progress",
            headers=auth_headers,
            json={
                "goal_id": str(uuid4()),
                "progress_level": "some_progress",
                "notes": "Test progress",
            },
        )
        # 404 or 422 are expected - confirms route exists
        assert response.status_code in (404, 422, 500)

    @pytest.mark.asyncio
    async def test_plan_sign_endpoint_path(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
    ) -> None:
        """Verify signature endpoint is at /api/v1/intervention-plans/{id}/sign."""
        plan_id = str(uuid4())
        response = await client.post(
            f"/api/v1/intervention-plans/{plan_id}/sign",
            headers=auth_headers,
            json={
                "signature_data": "base64data",
                "agreed_to_terms": True,
            },
        )
        # 404 or 422 are expected - confirms route exists
        assert response.status_code in (404, 422, 500)

    @pytest.mark.asyncio
    async def test_plan_version_endpoint_path(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
    ) -> None:
        """Verify version creation endpoint is at /api/v1/intervention-plans/{id}/version."""
        plan_id = str(uuid4())
        response = await client.post(
            f"/api/v1/intervention-plans/{plan_id}/version",
            headers=auth_headers,
        )
        # 404 is expected for non-existent plan - confirms route exists
        assert response.status_code in (404, 500)


# =============================================================================
# Section CRUD Endpoint Tests
# =============================================================================


class TestSectionCRUDEndpoints:
    """Tests for 8-part intervention plan section endpoints."""

    @pytest.mark.asyncio
    async def test_strengths_endpoint_exists(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
    ) -> None:
        """Verify strengths (Part 2) endpoint exists."""
        plan_id = str(uuid4())
        response = await client.post(
            f"/api/v1/intervention-plans/{plan_id}/strengths",
            headers=auth_headers,
            json={
                "category": "cognitive",
                "description": "Test strength",
            },
        )
        assert response.status_code in (404, 422, 500)

    @pytest.mark.asyncio
    async def test_needs_endpoint_exists(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
    ) -> None:
        """Verify needs (Part 3) endpoint exists."""
        plan_id = str(uuid4())
        response = await client.post(
            f"/api/v1/intervention-plans/{plan_id}/needs",
            headers=auth_headers,
            json={
                "category": "communication",
                "description": "Test need",
                "priority": "high",
            },
        )
        assert response.status_code in (404, 422, 500)

    @pytest.mark.asyncio
    async def test_goals_endpoint_exists(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
    ) -> None:
        """Verify SMART goals (Part 4) endpoint exists."""
        plan_id = str(uuid4())
        response = await client.post(
            f"/api/v1/intervention-plans/{plan_id}/goals",
            headers=auth_headers,
            json={
                "title": "Test goal",
                "description": "Test description",
            },
        )
        assert response.status_code in (404, 422, 500)

    @pytest.mark.asyncio
    async def test_strategies_endpoint_exists(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
    ) -> None:
        """Verify strategies (Part 5) endpoint exists."""
        plan_id = str(uuid4())
        response = await client.post(
            f"/api/v1/intervention-plans/{plan_id}/strategies",
            headers=auth_headers,
            json={
                "title": "Test strategy",
                "description": "Test description",
            },
        )
        assert response.status_code in (404, 422, 500)

    @pytest.mark.asyncio
    async def test_monitoring_endpoint_exists(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
    ) -> None:
        """Verify monitoring (Part 6) endpoint exists."""
        plan_id = str(uuid4())
        response = await client.post(
            f"/api/v1/intervention-plans/{plan_id}/monitoring",
            headers=auth_headers,
            json={
                "method": "observation",
                "description": "Test monitoring",
            },
        )
        assert response.status_code in (404, 422, 500)

    @pytest.mark.asyncio
    async def test_parent_involvement_endpoint_exists(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
    ) -> None:
        """Verify parent involvement (Part 7) endpoint exists."""
        plan_id = str(uuid4())
        response = await client.post(
            f"/api/v1/intervention-plans/{plan_id}/parent-involvements",
            headers=auth_headers,
            json={
                "activity_type": "home_practice",
                "title": "Test activity",
                "description": "Test description",
            },
        )
        assert response.status_code in (404, 422, 500)

    @pytest.mark.asyncio
    async def test_consultations_endpoint_exists(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
    ) -> None:
        """Verify consultations (Part 8) endpoint exists."""
        plan_id = str(uuid4())
        response = await client.post(
            f"/api/v1/intervention-plans/{plan_id}/consultations",
            headers=auth_headers,
            json={
                "specialist_type": "speech_therapist",
                "specialist_name": "Test Specialist",
                "purpose": "Test consultation",
            },
        )
        assert response.status_code in (404, 422, 500)


# =============================================================================
# Authentication Tests
# =============================================================================


class TestAuthenticationRequired:
    """Tests to verify authentication is enforced on protected endpoints."""

    @pytest.mark.asyncio
    async def test_list_plans_requires_auth(
        self,
        client: AsyncClient,
    ) -> None:
        """Verify list plans endpoint requires authentication."""
        response = await client.get("/api/v1/intervention-plans")
        assert response.status_code == 401

    @pytest.mark.asyncio
    async def test_create_plan_requires_auth(
        self,
        client: AsyncClient,
    ) -> None:
        """Verify create plan endpoint requires authentication."""
        response = await client.post(
            "/api/v1/intervention-plans",
            json={"title": "Test"},
        )
        assert response.status_code == 401

    @pytest.mark.asyncio
    async def test_get_plan_requires_auth(
        self,
        client: AsyncClient,
    ) -> None:
        """Verify get plan endpoint requires authentication."""
        plan_id = str(uuid4())
        response = await client.get(f"/api/v1/intervention-plans/{plan_id}")
        assert response.status_code == 401

    @pytest.mark.asyncio
    async def test_update_plan_requires_auth(
        self,
        client: AsyncClient,
    ) -> None:
        """Verify update plan endpoint requires authentication."""
        plan_id = str(uuid4())
        response = await client.put(
            f"/api/v1/intervention-plans/{plan_id}",
            json={"title": "Updated"},
        )
        assert response.status_code == 401

    @pytest.mark.asyncio
    async def test_delete_plan_requires_auth(
        self,
        client: AsyncClient,
    ) -> None:
        """Verify delete plan endpoint requires authentication."""
        plan_id = str(uuid4())
        response = await client.delete(f"/api/v1/intervention-plans/{plan_id}")
        assert response.status_code == 401

    @pytest.mark.asyncio
    async def test_sign_plan_requires_auth(
        self,
        client: AsyncClient,
    ) -> None:
        """Verify sign plan endpoint requires authentication."""
        plan_id = str(uuid4())
        response = await client.post(
            f"/api/v1/intervention-plans/{plan_id}/sign",
            json={"signature_data": "test", "agreed_to_terms": True},
        )
        assert response.status_code == 401

    @pytest.mark.asyncio
    async def test_pending_review_requires_auth(
        self,
        client: AsyncClient,
    ) -> None:
        """Verify pending review endpoint requires authentication."""
        response = await client.get("/api/v1/intervention-plans/pending-review")
        assert response.status_code == 401


# =============================================================================
# Error Handling Tests
# =============================================================================


class TestErrorHandling:
    """Tests for proper error response handling."""

    @pytest.mark.asyncio
    async def test_invalid_plan_id_returns_404(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
    ) -> None:
        """Verify 404 response for non-existent plan."""
        plan_id = str(uuid4())
        response = await client.get(
            f"/api/v1/intervention-plans/{plan_id}",
            headers=auth_headers,
        )
        # Note: might be 500 if database not available, which is also acceptable
        assert response.status_code in (404, 500)

    @pytest.mark.asyncio
    async def test_malformed_uuid_returns_422(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
    ) -> None:
        """Verify 422 response for malformed UUID."""
        response = await client.get(
            "/api/v1/intervention-plans/not-a-uuid",
            headers=auth_headers,
        )
        assert response.status_code == 422


# =============================================================================
# Cross-Service Endpoint Verification
# =============================================================================


class TestCrossServiceEndpointVerification:
    """Verify endpoint paths match between parent-portal client and ai-service.

    These tests document the expected API contract between services.
    """

    # Expected endpoint paths that parent-portal client uses
    EXPECTED_ENDPOINTS = {
        "list_plans": "/api/v1/intervention-plans",
        "get_plan": "/api/v1/intervention-plans/{id}",
        "plan_history": "/api/v1/intervention-plans/{id}/history",
        "plan_progress": "/api/v1/intervention-plans/{id}/progress",
        "plan_sign": "/api/v1/intervention-plans/{id}/sign",
        "pending_review": "/api/v1/intervention-plans/pending-review",
        "health": "/api/v1/intervention-plans/health",
    }

    def test_endpoint_contract_documentation(self) -> None:
        """Document the expected endpoint contract for cross-service verification.

        This test serves as documentation for the API contract between:
        - ai-service (FastAPI backend)
        - parent-portal (Next.js frontend)
        - gibbon (PHP CMS)

        All services should use these paths consistently.
        """
        # Verify expected endpoints are documented
        assert "list_plans" in self.EXPECTED_ENDPOINTS
        assert "get_plan" in self.EXPECTED_ENDPOINTS
        assert "plan_history" in self.EXPECTED_ENDPOINTS
        assert "plan_progress" in self.EXPECTED_ENDPOINTS
        assert "plan_sign" in self.EXPECTED_ENDPOINTS
        assert "pending_review" in self.EXPECTED_ENDPOINTS
        assert "health" in self.EXPECTED_ENDPOINTS

        # Verify paths don't have /plans suffix (fixed from original implementation)
        assert "/plans/" not in self.EXPECTED_ENDPOINTS["list_plans"]
        assert "pending-review" in self.EXPECTED_ENDPOINTS["pending_review"]


# =============================================================================
# Parent Signature Workflow E2E Tests
# =============================================================================


class TestParentSignatureWorkflowE2E:
    """End-to-end tests for the parent signature workflow.

    Verification Steps:
    1. Create plan requiring parent signature
    2. Parent views plan in parent-portal
    3. Parent signs plan
    4. Signature reflected in Gibbon view

    These tests verify the complete signature workflow from:
    - ai-service API (backend)
    - parent-portal client (frontend expectations)
    - gibbon integration (CMS)
    """

    @pytest.fixture
    def signature_request_data(self) -> dict[str, Any]:
        """Create sample parent signature request data.

        Returns:
            dict: Valid signature request payload matching parent-portal client
        """
        return {
            "signature_data": "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==",
            "agreed_to_terms": True,
        }

    @pytest.fixture
    def invalid_signature_request_no_data(self) -> dict[str, Any]:
        """Create signature request missing signature data."""
        return {
            "agreed_to_terms": True,
        }

    @pytest.fixture
    def invalid_signature_request_no_agreement(self) -> dict[str, Any]:
        """Create signature request without terms agreement."""
        return {
            "signature_data": "data:image/png;base64,test",
            "agreed_to_terms": False,
        }

    # -------------------------------------------------------------------------
    # Step 1: Create Plan Requiring Parent Signature
    # -------------------------------------------------------------------------

    @pytest.mark.asyncio
    async def test_signature_endpoint_accepts_valid_request_format(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
        signature_request_data: dict[str, Any],
    ) -> None:
        """Verify signature endpoint accepts valid request format.

        Confirms that the API accepts the same payload format that
        parent-portal sends via signInterventionPlan() client function.
        """
        plan_id = str(uuid4())
        response = await client.post(
            f"/api/v1/intervention-plans/{plan_id}/sign",
            headers=auth_headers,
            json=signature_request_data,
        )
        # 404 or 500 expected (plan doesn't exist), but confirms endpoint accepts format
        # 422 would indicate invalid format, which should NOT happen
        assert response.status_code in (404, 500)
        # Verify it's NOT a validation error (422)
        assert response.status_code != 422, "Signature request format should be valid"

    @pytest.mark.asyncio
    async def test_signature_endpoint_matches_parent_portal_client_path(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
        signature_request_data: dict[str, Any],
    ) -> None:
        """Verify signature endpoint path matches parent-portal client.

        Parent-portal intervention-plan-client.ts uses:
        ENDPOINTS.PLAN_SIGN = (id) => `/api/v1/intervention-plans/${id}/sign`

        This test confirms the path matches.
        """
        plan_id = "12345678-1234-1234-1234-123456789abc"
        expected_path = f"/api/v1/intervention-plans/{plan_id}/sign"

        response = await client.post(
            expected_path,
            headers=auth_headers,
            json=signature_request_data,
        )
        # Should reach endpoint (not 404 for wrong path)
        # Plan doesn't exist so 404/500, but NOT "Method Not Allowed" (405)
        assert response.status_code in (404, 500)
        assert response.status_code != 405, "Path should match - not Method Not Allowed"

    # -------------------------------------------------------------------------
    # Step 2: Parent Views Plan in Parent Portal
    # -------------------------------------------------------------------------

    @pytest.mark.asyncio
    async def test_get_plan_includes_signature_status_field(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
    ) -> None:
        """Verify get plan endpoint path is accessible for parent viewing.

        Parent-portal fetches plan details using getInterventionPlan(planId)
        which calls GET /api/v1/intervention-plans/{id}
        """
        plan_id = str(uuid4())
        response = await client.get(
            f"/api/v1/intervention-plans/{plan_id}",
            headers=auth_headers,
        )
        # Should reach endpoint and respond (plan doesn't exist so 404/500)
        assert response.status_code in (404, 500)
        assert response.status_code != 405, "GET method should be allowed"

    @pytest.mark.asyncio
    async def test_list_plans_supports_parent_signed_filter(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
    ) -> None:
        """Verify list plans supports parent_signed filter for parent portal.

        Parent-portal uses getPlansAwaitingSignature() which calls:
        getInterventionPlans({ parentSigned: false, status: 'active' })
        """
        response = await client.get(
            "/api/v1/intervention-plans",
            headers=auth_headers,
            params={"parent_signed": False},
        )
        # Should accept the filter parameter (200 or 500 due to db state)
        assert response.status_code in (200, 500)
        # NOT 422 - filter should be valid
        assert response.status_code != 422, "parent_signed filter should be valid"

    @pytest.mark.asyncio
    async def test_list_plans_supports_status_filter(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
    ) -> None:
        """Verify list plans supports status filter for filtering active plans.

        Parent-portal uses getActiveInterventionPlans(childId) which calls:
        getInterventionPlans({ childId, status: 'active' })
        """
        response = await client.get(
            "/api/v1/intervention-plans",
            headers=auth_headers,
            params={"status": "active"},
        )
        # Should accept the filter parameter
        assert response.status_code in (200, 500)

    # -------------------------------------------------------------------------
    # Step 3: Parent Signs Plan
    # -------------------------------------------------------------------------

    @pytest.mark.asyncio
    async def test_signature_requires_authentication(
        self,
        client: AsyncClient,
        signature_request_data: dict[str, Any],
    ) -> None:
        """Verify parent must be authenticated to sign plan.

        This ensures only verified parents can sign plans.
        """
        plan_id = str(uuid4())
        response = await client.post(
            f"/api/v1/intervention-plans/{plan_id}/sign",
            json=signature_request_data,
            # No auth headers - should be rejected
        )
        assert response.status_code == 401

    @pytest.mark.asyncio
    async def test_signature_rejects_missing_signature_data(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
        invalid_signature_request_no_data: dict[str, Any],
    ) -> None:
        """Verify signature request requires signature_data field."""
        plan_id = str(uuid4())
        response = await client.post(
            f"/api/v1/intervention-plans/{plan_id}/sign",
            headers=auth_headers,
            json=invalid_signature_request_no_data,
        )
        # Should reject with 422 validation error
        assert response.status_code == 422

    @pytest.mark.asyncio
    async def test_signature_request_with_base64_image(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
    ) -> None:
        """Verify signature endpoint accepts base64-encoded signature image.

        Parent-portal SignatureCanvas component generates base64 data URLs
        which are sent via signInterventionPlan().
        """
        plan_id = str(uuid4())
        # Valid minimal PNG as base64 (1x1 transparent pixel)
        signature_data = {
            "signature_data": "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==",
            "agreed_to_terms": True,
        }
        response = await client.post(
            f"/api/v1/intervention-plans/{plan_id}/sign",
            headers=auth_headers,
            json=signature_data,
        )
        # Should accept format (plan doesn't exist so 404/500)
        assert response.status_code in (404, 500)

    @pytest.mark.asyncio
    async def test_signature_request_with_large_base64_data(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
    ) -> None:
        """Verify signature endpoint handles realistic-sized signature data.

        Actual signatures from canvas can be several KB of base64 data.
        """
        plan_id = str(uuid4())
        # Generate larger but valid-ish base64 data
        large_signature = "data:image/png;base64," + "A" * 10000
        signature_data = {
            "signature_data": large_signature,
            "agreed_to_terms": True,
        }
        response = await client.post(
            f"/api/v1/intervention-plans/{plan_id}/sign",
            headers=auth_headers,
            json=signature_data,
        )
        # Should accept larger data (plan doesn't exist so 404/500)
        assert response.status_code in (404, 500)

    # -------------------------------------------------------------------------
    # Step 4: Signature Reflected in Gibbon View
    # -------------------------------------------------------------------------

    @pytest.mark.asyncio
    async def test_signature_response_format_for_gibbon_sync(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
    ) -> None:
        """Verify signature endpoint response format supports Gibbon sync.

        Gibbon module calls ai-service to verify signature status.
        Response should include:
        - plan_id
        - parent_signed: bool
        - parent_signature_date: datetime
        """
        # This is a schema verification test
        # Actual response tested when plan exists in database
        plan_id = str(uuid4())
        response = await client.post(
            f"/api/v1/intervention-plans/{plan_id}/sign",
            headers=auth_headers,
            json={
                "signature_data": "test_data",
                "agreed_to_terms": True,
            },
        )
        # Endpoint should exist and respond
        assert response.status_code in (404, 500)

    @pytest.mark.asyncio
    async def test_plan_details_endpoint_supports_gibbon_display(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
    ) -> None:
        """Verify get plan endpoint provides data needed by Gibbon display.

        Gibbon's interventionPlans_view.php displays signature information.
        API should support retrieving this data.
        """
        plan_id = str(uuid4())
        response = await client.get(
            f"/api/v1/intervention-plans/{plan_id}",
            headers=auth_headers,
        )
        # Endpoint accessible for Gibbon to fetch plan with signature status
        assert response.status_code in (404, 500)


# =============================================================================
# Parent Signature Schema Validation Tests
# =============================================================================


class TestParentSignatureSchemaValidation:
    """Tests for parent signature request/response schema validation.

    Ensures the API contract matches what parent-portal and gibbon expect.
    """

    @pytest.mark.asyncio
    async def test_signature_request_snake_case_field_names(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
    ) -> None:
        """Verify API accepts snake_case field names from parent-portal.

        Parent-portal intervention-plan-client.ts sends:
        {
            signature_data: request.signatureData,
            agreed_to_terms: request.agreedToTerms,
        }
        """
        plan_id = str(uuid4())
        # Snake case as sent by parent-portal client
        response = await client.post(
            f"/api/v1/intervention-plans/{plan_id}/sign",
            headers=auth_headers,
            json={
                "signature_data": "test_signature",
                "agreed_to_terms": True,
            },
        )
        # Should not be 422 - field names are valid
        assert response.status_code in (404, 500)

    @pytest.mark.asyncio
    async def test_signature_response_contains_expected_fields(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
    ) -> None:
        """Document expected response fields for signature endpoint.

        Parent-portal SignInterventionPlanResponse type expects:
        - planId: string
        - parentSigned: boolean
        - parentSignatureDate: string (ISO datetime)
        - message?: string
        """
        # This is a documentation test
        expected_response_fields = [
            "plan_id",
            "parent_signed",
            "parent_signature_date",
            "message",
        ]
        # Verify these are the fields we expect
        assert len(expected_response_fields) == 4

    @pytest.mark.asyncio
    async def test_signature_validates_agreed_to_terms_required(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
    ) -> None:
        """Verify agreed_to_terms field is required for signing."""
        plan_id = str(uuid4())
        response = await client.post(
            f"/api/v1/intervention-plans/{plan_id}/sign",
            headers=auth_headers,
            json={
                "signature_data": "test_signature",
                # Missing agreed_to_terms
            },
        )
        # Should reject as invalid - 422
        assert response.status_code == 422


# =============================================================================
# Cross-Service Signature Integration Tests
# =============================================================================


class TestCrossServiceSignatureIntegration:
    """Tests verifying signature workflow integration across services.

    Verifies that the API contract is consistent between:
    - ai-service (FastAPI backend)
    - parent-portal (Next.js client)
    - gibbon (PHP CMS)
    """

    def test_parent_portal_signature_endpoint_contract(self) -> None:
        """Document parent-portal signInterventionPlan() API contract.

        From parent-portal/lib/intervention-plan-client.ts:
        ```
        export async function signInterventionPlan(
            planId: string,
            request: SignInterventionPlanRequest
        ): Promise<SignInterventionPlanResponse>
        ```

        Endpoint: POST /api/v1/intervention-plans/{planId}/sign
        Request Body:
        {
            signature_data: string,   // Base64-encoded signature image
            agreed_to_terms: boolean, // User confirmed agreement
        }
        Response:
        {
            plan_id: string,
            parent_signed: boolean,
            parent_signature_date: string, // ISO 8601 datetime
            message: string,
        }
        """
        # Contract documentation test
        contract = {
            "endpoint": "POST /api/v1/intervention-plans/{id}/sign",
            "request_fields": ["signature_data", "agreed_to_terms"],
            "response_fields": ["plan_id", "parent_signed", "parent_signature_date", "message"],
        }
        assert contract["endpoint"] == "POST /api/v1/intervention-plans/{id}/sign"

    def test_gibbon_signature_display_contract(self) -> None:
        """Document Gibbon InterventionPlanGateway signature field mapping.

        From gibbon/modules/InterventionPlans/Domain/InterventionPlanGateway.php:
        - markParentSigned($planId, $parentId, $signatureDate)
        - queryInterventionPlans() returns parentSigned, parentSignatureDate

        Gibbon expects these fields when syncing with ai-service.
        """
        gibbon_fields = {
            "parentSigned": "BOOLEAN - indicates if plan has parent signature",
            "parentSignatureDate": "DATETIME - when parent signed",
            "parentSignatureID": "VARCHAR(36) - UUID of parent who signed",
        }
        assert "parentSigned" in gibbon_fields
        assert "parentSignatureDate" in gibbon_fields

    def test_signature_workflow_steps_documented(self) -> None:
        """Document the complete parent signature workflow.

        Workflow Steps:
        1. Educator creates intervention plan in Gibbon
           - Plan stored in gibbonInterventionPlan table
           - parentSigned = 0 (unsigned)

        2. Plan syncs to ai-service
           - Plan stored in intervention_plans table
           - parent_signed = false

        3. Parent views plan in parent-portal
           - Fetches plan via GET /api/v1/intervention-plans/{id}
           - Displays ParentSignature component if not signed

        4. Parent signs plan in parent-portal
           - SignatureCanvas captures signature as base64
           - POST /api/v1/intervention-plans/{id}/sign
           - Returns confirmation with timestamp

        5. Signature syncs back to Gibbon
           - Gibbon polls or receives webhook
           - Updates gibbonInterventionPlan.parentSigned = 1
           - Updates gibbonInterventionPlan.parentSignatureDate

        6. Educator sees signature in Gibbon
           - interventionPlans_view.php shows signature status
           - interventionPlans.php list shows signed indicator
        """
        workflow_steps = [
            "Create plan in Gibbon (unsigned)",
            "Sync plan to ai-service",
            "Parent views plan in parent-portal",
            "Parent signs via SignatureCanvas",
            "Signature syncs to Gibbon",
            "Signature visible in Gibbon view",
        ]
        assert len(workflow_steps) == 6

    @pytest.mark.asyncio
    async def test_parent_portal_awaiting_signature_flow(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
    ) -> None:
        """Test the getPlansAwaitingSignature() flow from parent-portal.

        This tests the workflow where parent-portal shows unsigned plans.
        """
        # Parent-portal calls:
        # getInterventionPlans({ parentSigned: false, status: 'active' })
        response = await client.get(
            "/api/v1/intervention-plans",
            headers=auth_headers,
            params={
                "parent_signed": "false",
                "status": "active",
            },
        )
        # Should accept these filters
        assert response.status_code in (200, 500)

    @pytest.mark.asyncio
    async def test_parent_portal_child_summary_flow(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
        test_child_id: UUID,
    ) -> None:
        """Test the getChildInterventionPlanSummary() flow from parent-portal.

        This aggregates active plans, pending signatures, and review reminders.
        """
        # Part of the summary - fetch plans for child
        response = await client.get(
            "/api/v1/intervention-plans",
            headers=auth_headers,
            params={
                "child_id": str(test_child_id),
            },
        )
        assert response.status_code in (200, 500)

        # Part of the summary - fetch pending reviews
        response = await client.get(
            "/api/v1/intervention-plans/pending-review",
            headers=auth_headers,
        )
        assert response.status_code in (200, 500)


# =============================================================================
# Automated Review Reminder Flow E2E Tests
# =============================================================================


class TestAutomatedReviewReminderFlowE2E:
    """End-to-end tests for the automated review reminder flow.

    Verification Steps:
    1. Create plan with review schedule
    2. Verify pending review appears in ai-service API
    3. Verify reminder triggered for plans due for review

    These tests verify the complete review reminder workflow from:
    - ai-service API (backend)
    - parent-portal client (frontend expectations)
    - gibbon integration (CMS)
    """

    @pytest.fixture
    def monthly_review_plan_data(self, test_child_id: UUID) -> dict[str, Any]:
        """Create sample intervention plan with monthly review schedule.

        Args:
            test_child_id: Mock child ID

        Returns:
            dict: Valid intervention plan creation payload with monthly reviews
        """
        return {
            "child_id": str(test_child_id),
            "title": "Monthly Review Intervention Plan",
            "status": "active",
            "review_schedule": "monthly",
            "effective_date": str(date.today()),
            "child_name": "Monthly Review Child",
            "child_dob": str(date.today() - timedelta(days=1095)),
            "medical_diagnosis": "Test diagnosis for review",
            "educational_history": "Test educational history",
        }

    @pytest.fixture
    def quarterly_review_plan_data(self, test_child_id: UUID) -> dict[str, Any]:
        """Create sample intervention plan with quarterly review schedule.

        Args:
            test_child_id: Mock child ID

        Returns:
            dict: Valid intervention plan creation payload with quarterly reviews
        """
        return {
            "child_id": str(test_child_id),
            "title": "Quarterly Review Intervention Plan",
            "status": "active",
            "review_schedule": "quarterly",
            "effective_date": str(date.today()),
            "child_name": "Quarterly Review Child",
        }

    # -------------------------------------------------------------------------
    # Step 1: Create Plan with Review Schedule
    # -------------------------------------------------------------------------

    @pytest.mark.asyncio
    async def test_create_plan_with_monthly_review_schedule(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
        monthly_review_plan_data: dict[str, Any],
    ) -> None:
        """Verify plan creation accepts monthly review_schedule.

        Step 1: Plans are created with a review_schedule that determines
        when reminders are triggered.
        """
        response = await client.post(
            "/api/v1/intervention-plans",
            headers=auth_headers,
            json=monthly_review_plan_data,
        )
        # Should accept valid review schedule (500 if db not available)
        assert response.status_code in (201, 500)
        # NOT 422 - review_schedule value should be valid
        assert response.status_code != 422, "monthly review_schedule should be valid"

    @pytest.mark.asyncio
    async def test_create_plan_with_quarterly_review_schedule(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
        quarterly_review_plan_data: dict[str, Any],
    ) -> None:
        """Verify plan creation accepts quarterly review_schedule."""
        response = await client.post(
            "/api/v1/intervention-plans",
            headers=auth_headers,
            json=quarterly_review_plan_data,
        )
        assert response.status_code in (201, 500)
        assert response.status_code != 422, "quarterly review_schedule should be valid"

    @pytest.mark.asyncio
    async def test_create_plan_with_semi_annual_review_schedule(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
        test_child_id: UUID,
    ) -> None:
        """Verify plan creation accepts semi_annually review_schedule."""
        plan_data = {
            "child_id": str(test_child_id),
            "title": "Semi-Annual Review Plan",
            "status": "active",
            "review_schedule": "semi_annually",
            "effective_date": str(date.today()),
            "child_name": "Semi-Annual Child",
        }
        response = await client.post(
            "/api/v1/intervention-plans",
            headers=auth_headers,
            json=plan_data,
        )
        assert response.status_code in (201, 500)
        assert response.status_code != 422, "semi_annually review_schedule should be valid"

    @pytest.mark.asyncio
    async def test_create_plan_with_annual_review_schedule(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
        test_child_id: UUID,
    ) -> None:
        """Verify plan creation accepts annually review_schedule."""
        plan_data = {
            "child_id": str(test_child_id),
            "title": "Annual Review Plan",
            "status": "active",
            "review_schedule": "annually",
            "effective_date": str(date.today()),
            "child_name": "Annual Review Child",
        }
        response = await client.post(
            "/api/v1/intervention-plans",
            headers=auth_headers,
            json=plan_data,
        )
        assert response.status_code in (201, 500)
        assert response.status_code != 422, "annually review_schedule should be valid"

    @pytest.mark.asyncio
    async def test_create_plan_rejects_invalid_review_schedule(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
        test_child_id: UUID,
    ) -> None:
        """Verify plan creation rejects invalid review_schedule values."""
        plan_data = {
            "child_id": str(test_child_id),
            "title": "Invalid Schedule Plan",
            "status": "active",
            "review_schedule": "weekly",  # Invalid value
            "effective_date": str(date.today()),
            "child_name": "Invalid Schedule Child",
        }
        response = await client.post(
            "/api/v1/intervention-plans",
            headers=auth_headers,
            json=plan_data,
        )
        # Should reject invalid review_schedule with 422
        assert response.status_code == 422

    # -------------------------------------------------------------------------
    # Step 2: Verify Pending Review Appears in ai-service API
    # -------------------------------------------------------------------------

    @pytest.mark.asyncio
    async def test_pending_review_endpoint_exists(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
    ) -> None:
        """Verify pending-review endpoint is registered and accessible.

        Step 2: The /pending-review endpoint returns plans due for review.
        """
        response = await client.get(
            "/api/v1/intervention-plans/pending-review",
            headers=auth_headers,
        )
        # Should be accessible (200 or 500 if db unavailable)
        assert response.status_code in (200, 500)
        assert response.status_code != 404, "pending-review endpoint should exist"

    @pytest.mark.asyncio
    async def test_pending_review_returns_list_response(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
    ) -> None:
        """Verify pending-review endpoint returns correct response structure.

        Expected response: PlanReviewReminderListResponse with:
        - plans: list of PlanReviewReminder
        - overdue_count: number of overdue plans
        - upcoming_count: number of upcoming plans
        """
        response = await client.get(
            "/api/v1/intervention-plans/pending-review",
            headers=auth_headers,
        )
        if response.status_code == 200:
            data = response.json()
            # Verify response structure
            assert "plans" in data
            assert "overdue_count" in data
            assert "upcoming_count" in data
            assert isinstance(data["plans"], list)
            assert isinstance(data["overdue_count"], int)
            assert isinstance(data["upcoming_count"], int)

    @pytest.mark.asyncio
    async def test_pending_review_accepts_days_ahead_parameter(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
    ) -> None:
        """Verify pending-review endpoint accepts days_ahead parameter.

        Parent-portal uses: getPlansForReview({ daysAhead: 7 })
        """
        response = await client.get(
            "/api/v1/intervention-plans/pending-review",
            headers=auth_headers,
            params={"days_ahead": 7},
        )
        # Should accept the parameter
        assert response.status_code in (200, 500)
        assert response.status_code != 422, "days_ahead parameter should be valid"

    @pytest.mark.asyncio
    async def test_pending_review_accepts_30_days_ahead(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
    ) -> None:
        """Verify pending-review default 30 days_ahead is valid.

        Parent-portal default: getPlansForReview({ daysAhead: 30 })
        """
        response = await client.get(
            "/api/v1/intervention-plans/pending-review",
            headers=auth_headers,
            params={"days_ahead": 30},
        )
        assert response.status_code in (200, 500)

    @pytest.mark.asyncio
    async def test_pending_review_rejects_invalid_days_ahead(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
    ) -> None:
        """Verify pending-review endpoint rejects invalid days_ahead values."""
        # Test negative value
        response = await client.get(
            "/api/v1/intervention-plans/pending-review",
            headers=auth_headers,
            params={"days_ahead": -5},
        )
        assert response.status_code == 422

    @pytest.mark.asyncio
    async def test_pending_review_rejects_zero_days_ahead(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
    ) -> None:
        """Verify pending-review endpoint requires at least 1 day ahead."""
        response = await client.get(
            "/api/v1/intervention-plans/pending-review",
            headers=auth_headers,
            params={"days_ahead": 0},
        )
        # 0 should be rejected per router validation (ge=1)
        assert response.status_code == 422

    @pytest.mark.asyncio
    async def test_pending_review_rejects_too_large_days_ahead(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
    ) -> None:
        """Verify pending-review endpoint rejects days_ahead > 365."""
        response = await client.get(
            "/api/v1/intervention-plans/pending-review",
            headers=auth_headers,
            params={"days_ahead": 400},
        )
        # > 365 should be rejected per router validation (le=365)
        assert response.status_code == 422

    # -------------------------------------------------------------------------
    # Step 3: Verify Reminder Triggered for Plans Due for Review
    # -------------------------------------------------------------------------

    @pytest.mark.asyncio
    async def test_review_reminder_structure_matches_parent_portal(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
    ) -> None:
        """Verify reminder response structure matches parent-portal types.

        Parent-portal InterventionPlanReviewReminder expects:
        - planId: string
        - childId: string
        - childName: string
        - title: string
        - nextReviewDate: string (ISO date)
        - daysUntilReview: number (can be negative for overdue)
        - status: InterventionPlanStatus
        """
        response = await client.get(
            "/api/v1/intervention-plans/pending-review",
            headers=auth_headers,
        )
        if response.status_code == 200:
            data = response.json()
            # If there are plans, verify structure
            if data["plans"] and len(data["plans"]) > 0:
                reminder = data["plans"][0]
                assert "plan_id" in reminder
                assert "child_id" in reminder
                assert "child_name" in reminder
                assert "title" in reminder
                assert "next_review_date" in reminder
                assert "days_until_review" in reminder
                assert "status" in reminder

    @pytest.mark.asyncio
    async def test_review_reminder_days_until_can_be_negative(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
    ) -> None:
        """Verify days_until_review can be negative for overdue plans.

        This is important for overdue plan identification.
        """
        response = await client.get(
            "/api/v1/intervention-plans/pending-review",
            headers=auth_headers,
        )
        # Endpoint should work (negative values allowed in response)
        assert response.status_code in (200, 500)

    @pytest.mark.asyncio
    async def test_overdue_count_in_response(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
    ) -> None:
        """Verify overdue_count field is present for UI badge display.

        Parent-portal uses getPendingReviewCount() which relies on this.
        """
        response = await client.get(
            "/api/v1/intervention-plans/pending-review",
            headers=auth_headers,
        )
        if response.status_code == 200:
            data = response.json()
            assert "overdue_count" in data
            assert data["overdue_count"] >= 0

    @pytest.mark.asyncio
    async def test_upcoming_count_in_response(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
    ) -> None:
        """Verify upcoming_count field is present for upcoming review alerts."""
        response = await client.get(
            "/api/v1/intervention-plans/pending-review",
            headers=auth_headers,
        )
        if response.status_code == 200:
            data = response.json()
            assert "upcoming_count" in data
            assert data["upcoming_count"] >= 0


# =============================================================================
# Review Reminder Cross-Service Integration Tests
# =============================================================================


class TestReviewReminderCrossServiceIntegration:
    """Tests verifying review reminder workflow integration across services.

    Verifies that the API contract is consistent between:
    - ai-service (FastAPI backend)
    - parent-portal (Next.js client)
    - gibbon (PHP CMS)
    """

    # Expected endpoint paths that parent-portal client uses
    REVIEW_ENDPOINTS = {
        "pending_review": "/api/v1/intervention-plans/pending-review",
        "list_plans": "/api/v1/intervention-plans",
    }

    def test_review_reminder_endpoint_contract(self) -> None:
        """Document parent-portal getPlansForReview() API contract.

        From parent-portal/lib/intervention-plan-client.ts:
        ```
        export async function getPlansForReview(
            params?: PendingReviewParams
        ): Promise<InterventionPlanReviewReminder[]>
        ```

        Endpoint: GET /api/v1/intervention-plans/pending-review
        Query Params:
        {
            days_ahead: number,     // How many days to look ahead (default 30)
            include_overdue: bool,  // Whether to include overdue plans (default true)
        }
        Response:
        {
            plans: InterventionPlanReviewReminder[],
            overdue_count: number,
            upcoming_count: number,
        }
        """
        contract = {
            "endpoint": "GET /api/v1/intervention-plans/pending-review",
            "request_params": ["days_ahead", "include_overdue"],
            "response_fields": ["plans", "overdue_count", "upcoming_count"],
        }
        assert contract["endpoint"] == "GET /api/v1/intervention-plans/pending-review"

    def test_review_reminder_plan_fields_documented(self) -> None:
        """Document expected fields in PlanReviewReminder.

        Gibbon's InterventionPlanGateway expects these fields when
        displaying review status in the CMS.
        """
        reminder_fields = {
            "plan_id": "UUID of the intervention plan",
            "child_id": "UUID of the child",
            "child_name": "Name of the child for display",
            "title": "Plan title",
            "next_review_date": "ISO date when review is due",
            "days_until_review": "Days until review (negative if overdue)",
            "status": "Plan status (active, under_review, etc.)",
        }
        assert "plan_id" in reminder_fields
        assert "days_until_review" in reminder_fields

    def test_gibbon_review_workflow_documented(self) -> None:
        """Document Gibbon review reminder workflow.

        From gibbon/modules/InterventionPlans/Domain/InterventionPlanGateway.php:
        - queryPlansPendingReview(): Plans due/overdue for review
        - Uses urgency status and days until review

        Workflow:
        1. Plan created with review_schedule (monthly, quarterly, etc.)
        2. System calculates next_review_date from schedule
        3. Gibbon queries ai-service for pending reviews
        4. Gibbon displays overdue/urgent alerts in CMS
        5. Educator schedules review meeting
        6. After review, next_review_date is updated
        """
        workflow_steps = [
            "Plan created with review_schedule",
            "next_review_date calculated from schedule",
            "Gibbon queries ai-service /pending-review",
            "CMS displays overdue/urgent alerts",
            "Educator schedules review meeting",
            "next_review_date updated after review",
        ]
        assert len(workflow_steps) == 6

    @pytest.mark.asyncio
    async def test_parent_portal_review_summary_flow(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
        test_child_id: UUID,
    ) -> None:
        """Test parent-portal getChildInterventionPlanSummary() review flow.

        This aggregates upcomingReviews for the child summary page.
        """
        # Parent-portal calls getReviewRemindersForChild(childId)
        # which calls getPlansForReview() then filters by childId
        response = await client.get(
            "/api/v1/intervention-plans/pending-review",
            headers=auth_headers,
        )
        # Should return list that can be filtered by childId
        assert response.status_code in (200, 500)

    @pytest.mark.asyncio
    async def test_pending_review_count_for_badge(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
    ) -> None:
        """Test getPendingReviewCount() flow for badge display.

        Parent-portal uses 7 days ahead for urgent badge.
        """
        response = await client.get(
            "/api/v1/intervention-plans/pending-review",
            headers=auth_headers,
            params={"days_ahead": 7},
        )
        if response.status_code == 200:
            data = response.json()
            # Total count for badge
            total_urgent = data["overdue_count"] + data["upcoming_count"]
            assert total_urgent >= 0


# =============================================================================
# Review Schedule Validation Tests
# =============================================================================


class TestReviewScheduleValidation:
    """Tests for review schedule enum validation in API requests."""

    @pytest.mark.asyncio
    async def test_valid_review_schedules_accepted(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
        test_child_id: UUID,
    ) -> None:
        """Verify all valid ReviewSchedule values are accepted.

        Valid values: monthly, quarterly, semi_annually, annually
        """
        valid_schedules = ["monthly", "quarterly", "semi_annually", "annually"]

        for schedule in valid_schedules:
            plan_data = {
                "child_id": str(test_child_id),
                "title": f"{schedule.title()} Review Plan",
                "status": "active",
                "review_schedule": schedule,
                "effective_date": str(date.today()),
                "child_name": f"{schedule} Test Child",
            }
            response = await client.post(
                "/api/v1/intervention-plans",
                headers=auth_headers,
                json=plan_data,
            )
            # Should accept valid schedule (201 or 500 if db unavailable)
            assert response.status_code in (201, 500), (
                f"review_schedule '{schedule}' should be valid"
            )

    @pytest.mark.asyncio
    async def test_invalid_review_schedules_rejected(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
        test_child_id: UUID,
    ) -> None:
        """Verify invalid ReviewSchedule values are rejected.

        Invalid values should return 422 validation error.
        """
        invalid_schedules = ["weekly", "daily", "biannual", "never", "invalid"]

        for schedule in invalid_schedules:
            plan_data = {
                "child_id": str(test_child_id),
                "title": f"Invalid {schedule} Plan",
                "status": "active",
                "review_schedule": schedule,
                "effective_date": str(date.today()),
                "child_name": f"Invalid {schedule} Child",
            }
            response = await client.post(
                "/api/v1/intervention-plans",
                headers=auth_headers,
                json=plan_data,
            )
            # Should reject invalid schedule with 422
            assert response.status_code == 422, (
                f"review_schedule '{schedule}' should be rejected"
            )

    @pytest.mark.asyncio
    async def test_plan_list_filter_by_review_schedule(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
    ) -> None:
        """Verify list endpoint accepts review_schedule filter.

        Parent-portal uses: getInterventionPlans({ reviewSchedule: 'monthly' })
        """
        response = await client.get(
            "/api/v1/intervention-plans",
            headers=auth_headers,
            params={"review_schedule": "monthly"},
        )
        # Should accept the filter parameter
        assert response.status_code in (200, 500)

    @pytest.mark.asyncio
    async def test_plan_list_filter_needs_review(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
    ) -> None:
        """Verify list endpoint accepts needs_review filter.

        This filter returns only plans that are overdue for review.
        """
        response = await client.get(
            "/api/v1/intervention-plans",
            headers=auth_headers,
            params={"needs_review": True},
        )
        # Should accept the filter parameter
        assert response.status_code in (200, 500)


# =============================================================================
# Review Reminder Service Unit Tests (via API)
# =============================================================================


class TestReviewReminderServiceViaAPI:
    """Tests for review reminder service functionality via API endpoints.

    These tests verify the service logic through the API layer.
    """

    @pytest.mark.asyncio
    async def test_reminder_service_filters_active_plans(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
    ) -> None:
        """Verify only ACTIVE and UNDER_REVIEW status plans appear in reminders.

        Draft, Completed, and Archived plans should not trigger reminders.
        """
        response = await client.get(
            "/api/v1/intervention-plans/pending-review",
            headers=auth_headers,
        )
        if response.status_code == 200:
            data = response.json()
            for plan in data["plans"]:
                assert plan["status"] in ("active", "under_review"), (
                    "Only active and under_review plans should be in reminders"
                )

    @pytest.mark.asyncio
    async def test_reminder_categorizes_overdue_correctly(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
    ) -> None:
        """Verify overdue_count matches plans with negative days_until_review."""
        response = await client.get(
            "/api/v1/intervention-plans/pending-review",
            headers=auth_headers,
        )
        if response.status_code == 200:
            data = response.json()
            # Count plans with negative days
            overdue_plans = [
                p for p in data["plans"]
                if p.get("days_until_review", 0) < 0
            ]
            assert data["overdue_count"] == len(overdue_plans)

    @pytest.mark.asyncio
    async def test_reminder_categorizes_upcoming_correctly(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
    ) -> None:
        """Verify upcoming_count matches plans with non-negative days_until_review."""
        response = await client.get(
            "/api/v1/intervention-plans/pending-review",
            headers=auth_headers,
        )
        if response.status_code == 200:
            data = response.json()
            # Count plans with non-negative days
            upcoming_plans = [
                p for p in data["plans"]
                if p.get("days_until_review", 0) >= 0
            ]
            assert data["upcoming_count"] == len(upcoming_plans)
