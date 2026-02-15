"""Unit tests for webhook functionality.

Tests for payload parsing, JWT validation, and event processing
for the AISync webhook integration between Gibbon and LAYA AI Service.
"""

from __future__ import annotations

from datetime import datetime, timezone
from typing import Any
from uuid import uuid4

import pytest
import pytest_asyncio
from httpx import AsyncClient

from app.schemas.webhook import (
    WebhookEntityType,
    WebhookEventType,
    WebhookStatus,
)
from tests.conftest import create_test_token


# ============================================================================
# Webhook request fixtures
# ============================================================================


@pytest.fixture
def care_activity_created_payload() -> dict[str, Any]:
    """Create a payload for care activity created event.

    Returns:
        dict: Valid care activity created webhook payload
    """
    return {
        "event_type": WebhookEventType.CARE_ACTIVITY_CREATED.value,
        "entity_type": WebhookEntityType.ACTIVITY.value,
        "entity_id": str(uuid4()),
        "payload": {
            "child_id": str(uuid4()),
            "activity_type": "diaper_change",
            "notes": "Clean and dry",
            "educator_id": str(uuid4()),
        },
        "timestamp": datetime.now(timezone.utc).isoformat(),
        "gibbon_sync_log_id": 12345,
    }


@pytest.fixture
def meal_logged_payload() -> dict[str, Any]:
    """Create a payload for meal logged event.

    Returns:
        dict: Valid meal logged webhook payload
    """
    return {
        "event_type": WebhookEventType.MEAL_LOGGED.value,
        "entity_type": WebhookEntityType.MEAL.value,
        "entity_id": str(uuid4()),
        "payload": {
            "child_id": str(uuid4()),
            "meal_type": "lunch",
            "items": ["pasta", "vegetables", "milk"],
            "amount_consumed": "most",
        },
        "timestamp": datetime.now(timezone.utc).isoformat(),
    }


@pytest.fixture
def nap_logged_payload() -> dict[str, Any]:
    """Create a payload for nap logged event.

    Returns:
        dict: Valid nap logged webhook payload
    """
    return {
        "event_type": WebhookEventType.NAP_LOGGED.value,
        "entity_type": WebhookEntityType.NAP.value,
        "entity_id": str(uuid4()),
        "payload": {
            "child_id": str(uuid4()),
            "start_time": "13:00",
            "end_time": "14:30",
            "duration_minutes": 90,
            "quality": "restful",
        },
    }


@pytest.fixture
def photo_uploaded_payload() -> dict[str, Any]:
    """Create a payload for photo uploaded event.

    Returns:
        dict: Valid photo uploaded webhook payload
    """
    return {
        "event_type": WebhookEventType.PHOTO_UPLOADED.value,
        "entity_type": WebhookEntityType.PHOTO.value,
        "entity_id": str(uuid4()),
        "payload": {
            "child_id": str(uuid4()),
            "photo_url": "https://storage.example.com/photos/abc123.jpg",
            "caption": "Playing with blocks",
            "activity_type": "creative_play",
        },
    }


@pytest.fixture
def attendance_check_in_payload() -> dict[str, Any]:
    """Create a payload for attendance check-in event.

    Returns:
        dict: Valid attendance check-in webhook payload
    """
    return {
        "event_type": WebhookEventType.ATTENDANCE_CHECKED_IN.value,
        "entity_type": WebhookEntityType.ATTENDANCE.value,
        "entity_id": str(uuid4()),
        "payload": {
            "child_id": str(uuid4()),
            "check_in_time": "08:30",
            "dropped_off_by": "parent",
        },
    }


@pytest.fixture
def attendance_check_out_payload() -> dict[str, Any]:
    """Create a payload for attendance check-out event.

    Returns:
        dict: Valid attendance check-out webhook payload
    """
    return {
        "event_type": WebhookEventType.ATTENDANCE_CHECKED_OUT.value,
        "entity_type": WebhookEntityType.ATTENDANCE.value,
        "entity_id": str(uuid4()),
        "payload": {
            "child_id": str(uuid4()),
            "check_out_time": "17:00",
            "picked_up_by": "parent",
        },
    }


@pytest.fixture
def child_profile_updated_payload() -> dict[str, Any]:
    """Create a payload for child profile updated event.

    Returns:
        dict: Valid child profile updated webhook payload
    """
    return {
        "event_type": WebhookEventType.CHILD_PROFILE_UPDATED.value,
        "entity_type": WebhookEntityType.CHILD.value,
        "entity_id": str(uuid4()),
        "payload": {
            "name": "Updated Name",
            "age_months": 36,
            "special_needs": ["autism"],
            "allergies": ["peanuts"],
        },
    }


@pytest.fixture
def custom_event_payload() -> dict[str, Any]:
    """Create a payload for custom event.

    Returns:
        dict: Valid custom webhook payload
    """
    return {
        "event_type": WebhookEventType.CUSTOM.value,
        "entity_type": WebhookEntityType.CUSTOM.value,
        "entity_id": str(uuid4()),
        "payload": {
            "custom_event_type": "milestone_achieved",
            "milestone_name": "First steps",
            "child_id": str(uuid4()),
        },
    }


@pytest.fixture
def minimal_valid_payload() -> dict[str, Any]:
    """Create a minimal valid webhook payload.

    Returns:
        dict: Minimal valid webhook payload with only required fields
    """
    return {
        "event_type": WebhookEventType.CARE_ACTIVITY_CREATED.value,
        "entity_type": WebhookEntityType.ACTIVITY.value,
        "entity_id": "123",
    }


# ============================================================================
# Payload Parsing Tests
# ============================================================================


class TestWebhookPayloadParsing:
    """Tests for webhook payload parsing and validation."""

    @pytest.mark.asyncio
    async def test_valid_care_activity_payload(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
        care_activity_created_payload: dict[str, Any],
    ) -> None:
        """Test that valid care activity payload is parsed correctly."""
        response = await client.post(
            "/api/v1/webhook",
            json=care_activity_created_payload,
            headers=auth_headers,
        )

        assert response.status_code == 200
        data = response.json()
        assert data["status"] == WebhookStatus.PROCESSED.value
        assert data["event_type"] == WebhookEventType.CARE_ACTIVITY_CREATED.value
        assert data["entity_id"] == care_activity_created_payload["entity_id"]
        assert "processing_time_ms" in data
        assert "received_at" in data

    @pytest.mark.asyncio
    async def test_valid_meal_logged_payload(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
        meal_logged_payload: dict[str, Any],
    ) -> None:
        """Test that valid meal logged payload is parsed correctly."""
        response = await client.post(
            "/api/v1/webhook",
            json=meal_logged_payload,
            headers=auth_headers,
        )

        assert response.status_code == 200
        data = response.json()
        assert data["status"] == WebhookStatus.PROCESSED.value
        assert data["event_type"] == WebhookEventType.MEAL_LOGGED.value
        assert "meal" in data["message"].lower()

    @pytest.mark.asyncio
    async def test_valid_nap_logged_payload(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
        nap_logged_payload: dict[str, Any],
    ) -> None:
        """Test that valid nap logged payload is parsed correctly."""
        response = await client.post(
            "/api/v1/webhook",
            json=nap_logged_payload,
            headers=auth_headers,
        )

        assert response.status_code == 200
        data = response.json()
        assert data["status"] == WebhookStatus.PROCESSED.value
        assert data["event_type"] == WebhookEventType.NAP_LOGGED.value
        assert "nap" in data["message"].lower()

    @pytest.mark.asyncio
    async def test_valid_photo_uploaded_payload(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
        photo_uploaded_payload: dict[str, Any],
    ) -> None:
        """Test that valid photo uploaded payload is parsed correctly."""
        response = await client.post(
            "/api/v1/webhook",
            json=photo_uploaded_payload,
            headers=auth_headers,
        )

        assert response.status_code == 200
        data = response.json()
        assert data["status"] == WebhookStatus.PROCESSED.value
        assert data["event_type"] == WebhookEventType.PHOTO_UPLOADED.value
        assert "photo" in data["message"].lower()

    @pytest.mark.asyncio
    async def test_minimal_valid_payload(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
        minimal_valid_payload: dict[str, Any],
    ) -> None:
        """Test that minimal valid payload with only required fields is accepted."""
        response = await client.post(
            "/api/v1/webhook",
            json=minimal_valid_payload,
            headers=auth_headers,
        )

        assert response.status_code == 200
        data = response.json()
        assert data["status"] == WebhookStatus.PROCESSED.value

    @pytest.mark.asyncio
    async def test_missing_event_type(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
    ) -> None:
        """Test that missing event_type returns validation error."""
        payload = {
            "entity_type": WebhookEntityType.ACTIVITY.value,
            "entity_id": "123",
        }

        response = await client.post(
            "/api/v1/webhook",
            json=payload,
            headers=auth_headers,
        )

        assert response.status_code == 422

    @pytest.mark.asyncio
    async def test_missing_entity_type(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
    ) -> None:
        """Test that missing entity_type returns validation error."""
        payload = {
            "event_type": WebhookEventType.CARE_ACTIVITY_CREATED.value,
            "entity_id": "123",
        }

        response = await client.post(
            "/api/v1/webhook",
            json=payload,
            headers=auth_headers,
        )

        assert response.status_code == 422

    @pytest.mark.asyncio
    async def test_missing_entity_id(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
    ) -> None:
        """Test that missing entity_id returns validation error."""
        payload = {
            "event_type": WebhookEventType.CARE_ACTIVITY_CREATED.value,
            "entity_type": WebhookEntityType.ACTIVITY.value,
        }

        response = await client.post(
            "/api/v1/webhook",
            json=payload,
            headers=auth_headers,
        )

        assert response.status_code == 422

    @pytest.mark.asyncio
    async def test_invalid_event_type(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
    ) -> None:
        """Test that invalid event_type returns validation error."""
        payload = {
            "event_type": "invalid_event_type",
            "entity_type": WebhookEntityType.ACTIVITY.value,
            "entity_id": "123",
        }

        response = await client.post(
            "/api/v1/webhook",
            json=payload,
            headers=auth_headers,
        )

        assert response.status_code == 422

    @pytest.mark.asyncio
    async def test_invalid_entity_type(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
    ) -> None:
        """Test that invalid entity_type returns validation error."""
        payload = {
            "event_type": WebhookEventType.CARE_ACTIVITY_CREATED.value,
            "entity_type": "invalid_entity_type",
            "entity_id": "123",
        }

        response = await client.post(
            "/api/v1/webhook",
            json=payload,
            headers=auth_headers,
        )

        assert response.status_code == 422

    @pytest.mark.asyncio
    async def test_empty_entity_id(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
    ) -> None:
        """Test that empty entity_id returns validation error."""
        payload = {
            "event_type": WebhookEventType.CARE_ACTIVITY_CREATED.value,
            "entity_type": WebhookEntityType.ACTIVITY.value,
            "entity_id": "",
        }

        response = await client.post(
            "/api/v1/webhook",
            json=payload,
            headers=auth_headers,
        )

        assert response.status_code == 422

    @pytest.mark.asyncio
    async def test_entity_id_too_long(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
    ) -> None:
        """Test that entity_id exceeding max length returns validation error."""
        payload = {
            "event_type": WebhookEventType.CARE_ACTIVITY_CREATED.value,
            "entity_type": WebhookEntityType.ACTIVITY.value,
            "entity_id": "a" * 101,  # Max is 100
        }

        response = await client.post(
            "/api/v1/webhook",
            json=payload,
            headers=auth_headers,
        )

        assert response.status_code == 422

    @pytest.mark.asyncio
    async def test_empty_payload_dict(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
    ) -> None:
        """Test that empty payload dict is accepted (it's optional)."""
        payload = {
            "event_type": WebhookEventType.CARE_ACTIVITY_CREATED.value,
            "entity_type": WebhookEntityType.ACTIVITY.value,
            "entity_id": "123",
            "payload": {},
        }

        response = await client.post(
            "/api/v1/webhook",
            json=payload,
            headers=auth_headers,
        )

        assert response.status_code == 200

    @pytest.mark.asyncio
    async def test_invalid_gibbon_sync_log_id(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
    ) -> None:
        """Test that gibbon_sync_log_id less than 1 returns validation error."""
        payload = {
            "event_type": WebhookEventType.CARE_ACTIVITY_CREATED.value,
            "entity_type": WebhookEntityType.ACTIVITY.value,
            "entity_id": "123",
            "gibbon_sync_log_id": 0,
        }

        response = await client.post(
            "/api/v1/webhook",
            json=payload,
            headers=auth_headers,
        )

        assert response.status_code == 422


# ============================================================================
# JWT Validation Tests
# ============================================================================


class TestWebhookJWTValidation:
    """Tests for JWT authentication on webhook endpoints."""

    @pytest.mark.asyncio
    async def test_valid_jwt_token(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
        minimal_valid_payload: dict[str, Any],
    ) -> None:
        """Test that valid JWT token allows access."""
        response = await client.post(
            "/api/v1/webhook",
            json=minimal_valid_payload,
            headers=auth_headers,
        )

        assert response.status_code == 200

    @pytest.mark.asyncio
    async def test_missing_jwt_token(
        self,
        client: AsyncClient,
        minimal_valid_payload: dict[str, Any],
    ) -> None:
        """Test that missing JWT token returns 401/403."""
        response = await client.post(
            "/api/v1/webhook",
            json=minimal_valid_payload,
        )

        # FastAPI HTTPBearer returns 403 for missing credentials
        assert response.status_code in [401, 403]

    @pytest.mark.asyncio
    async def test_expired_jwt_token(
        self,
        client: AsyncClient,
        expired_token: str,
        minimal_valid_payload: dict[str, Any],
    ) -> None:
        """Test that expired JWT token returns 401."""
        headers = {"Authorization": f"Bearer {expired_token}"}

        response = await client.post(
            "/api/v1/webhook",
            json=minimal_valid_payload,
            headers=headers,
        )

        assert response.status_code == 401

    @pytest.mark.asyncio
    async def test_malformed_jwt_token(
        self,
        client: AsyncClient,
        minimal_valid_payload: dict[str, Any],
    ) -> None:
        """Test that malformed JWT token returns 401."""
        headers = {"Authorization": "Bearer not.a.valid.jwt.token"}

        response = await client.post(
            "/api/v1/webhook",
            json=minimal_valid_payload,
            headers=headers,
        )

        assert response.status_code == 401

    @pytest.mark.asyncio
    async def test_invalid_bearer_format(
        self,
        client: AsyncClient,
        valid_token: str,
        minimal_valid_payload: dict[str, Any],
    ) -> None:
        """Test that invalid Bearer format returns error."""
        # Missing "Bearer " prefix
        headers = {"Authorization": valid_token}

        response = await client.post(
            "/api/v1/webhook",
            json=minimal_valid_payload,
            headers=headers,
        )

        # Should fail authentication
        assert response.status_code in [401, 403]

    @pytest.mark.asyncio
    async def test_wrong_auth_scheme(
        self,
        client: AsyncClient,
        valid_token: str,
        minimal_valid_payload: dict[str, Any],
    ) -> None:
        """Test that wrong authentication scheme returns error."""
        headers = {"Authorization": f"Basic {valid_token}"}

        response = await client.post(
            "/api/v1/webhook",
            json=minimal_valid_payload,
            headers=headers,
        )

        assert response.status_code in [401, 403]

    @pytest.mark.asyncio
    async def test_empty_bearer_token(
        self,
        client: AsyncClient,
        minimal_valid_payload: dict[str, Any],
    ) -> None:
        """Test that empty bearer token returns error."""
        headers = {"Authorization": "Bearer "}

        response = await client.post(
            "/api/v1/webhook",
            json=minimal_valid_payload,
            headers=headers,
        )

        assert response.status_code in [401, 403]

    @pytest.mark.asyncio
    async def test_jwt_with_additional_claims(
        self,
        client: AsyncClient,
        test_user_payload: dict[str, Any],
        minimal_valid_payload: dict[str, Any],
    ) -> None:
        """Test that JWT with additional claims is accepted."""
        token = create_test_token(
            subject=test_user_payload["sub"],
            additional_claims={
                "email": test_user_payload["email"],
                "role": "system",
                "service": "gibbon_aisync",
            },
        )
        headers = {"Authorization": f"Bearer {token}"}

        response = await client.post(
            "/api/v1/webhook",
            json=minimal_valid_payload,
            headers=headers,
        )

        assert response.status_code == 200


# ============================================================================
# Event Processing Tests
# ============================================================================


class TestWebhookEventProcessing:
    """Tests for webhook event processing logic."""

    @pytest.mark.asyncio
    async def test_care_activity_created_processing(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
        care_activity_created_payload: dict[str, Any],
    ) -> None:
        """Test care activity created event is processed correctly."""
        response = await client.post(
            "/api/v1/webhook",
            json=care_activity_created_payload,
            headers=auth_headers,
        )

        assert response.status_code == 200
        data = response.json()
        assert "creation acknowledged" in data["message"].lower()
        assert care_activity_created_payload["entity_id"] in data["message"]

    @pytest.mark.asyncio
    async def test_care_activity_updated_processing(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
    ) -> None:
        """Test care activity updated event is processed correctly."""
        entity_id = str(uuid4())
        payload = {
            "event_type": WebhookEventType.CARE_ACTIVITY_UPDATED.value,
            "entity_type": WebhookEntityType.ACTIVITY.value,
            "entity_id": entity_id,
            "payload": {"updated_field": "value"},
        }

        response = await client.post(
            "/api/v1/webhook",
            json=payload,
            headers=auth_headers,
        )

        assert response.status_code == 200
        data = response.json()
        assert "update acknowledged" in data["message"].lower()
        assert entity_id in data["message"]

    @pytest.mark.asyncio
    async def test_care_activity_deleted_processing(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
    ) -> None:
        """Test care activity deleted event is processed correctly."""
        entity_id = str(uuid4())
        payload = {
            "event_type": WebhookEventType.CARE_ACTIVITY_DELETED.value,
            "entity_type": WebhookEntityType.ACTIVITY.value,
            "entity_id": entity_id,
            "payload": {},
        }

        response = await client.post(
            "/api/v1/webhook",
            json=payload,
            headers=auth_headers,
        )

        assert response.status_code == 200
        data = response.json()
        assert "deletion acknowledged" in data["message"].lower()
        assert entity_id in data["message"]

    @pytest.mark.asyncio
    async def test_meal_logged_processing(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
        meal_logged_payload: dict[str, Any],
    ) -> None:
        """Test meal logged event is processed correctly."""
        response = await client.post(
            "/api/v1/webhook",
            json=meal_logged_payload,
            headers=auth_headers,
        )

        assert response.status_code == 200
        data = response.json()
        assert "meal" in data["message"].lower()
        assert meal_logged_payload["payload"]["meal_type"] in data["message"]

    @pytest.mark.asyncio
    async def test_nap_logged_processing(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
        nap_logged_payload: dict[str, Any],
    ) -> None:
        """Test nap logged event is processed correctly."""
        response = await client.post(
            "/api/v1/webhook",
            json=nap_logged_payload,
            headers=auth_headers,
        )

        assert response.status_code == 200
        data = response.json()
        assert "nap" in data["message"].lower()
        duration = str(nap_logged_payload["payload"]["duration_minutes"])
        assert duration in data["message"]

    @pytest.mark.asyncio
    async def test_photo_uploaded_processing(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
        photo_uploaded_payload: dict[str, Any],
    ) -> None:
        """Test photo uploaded event is processed correctly."""
        response = await client.post(
            "/api/v1/webhook",
            json=photo_uploaded_payload,
            headers=auth_headers,
        )

        assert response.status_code == 200
        data = response.json()
        assert "photo" in data["message"].lower()

    @pytest.mark.asyncio
    async def test_attendance_check_in_processing(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
        attendance_check_in_payload: dict[str, Any],
    ) -> None:
        """Test attendance check-in event is processed correctly."""
        response = await client.post(
            "/api/v1/webhook",
            json=attendance_check_in_payload,
            headers=auth_headers,
        )

        assert response.status_code == 200
        data = response.json()
        assert "checked in" in data["message"].lower()

    @pytest.mark.asyncio
    async def test_attendance_check_out_processing(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
        attendance_check_out_payload: dict[str, Any],
    ) -> None:
        """Test attendance check-out event is processed correctly."""
        response = await client.post(
            "/api/v1/webhook",
            json=attendance_check_out_payload,
            headers=auth_headers,
        )

        assert response.status_code == 200
        data = response.json()
        assert "checked out" in data["message"].lower()

    @pytest.mark.asyncio
    async def test_child_profile_updated_processing(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
        child_profile_updated_payload: dict[str, Any],
    ) -> None:
        """Test child profile updated event is processed correctly."""
        response = await client.post(
            "/api/v1/webhook",
            json=child_profile_updated_payload,
            headers=auth_headers,
        )

        assert response.status_code == 200
        data = response.json()
        assert "profile" in data["message"].lower()
        assert "update acknowledged" in data["message"].lower()

    @pytest.mark.asyncio
    async def test_custom_event_processing(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
        custom_event_payload: dict[str, Any],
    ) -> None:
        """Test custom event is processed correctly."""
        response = await client.post(
            "/api/v1/webhook",
            json=custom_event_payload,
            headers=auth_headers,
        )

        assert response.status_code == 200
        data = response.json()
        assert "custom event" in data["message"].lower()
        custom_type = custom_event_payload["payload"]["custom_event_type"]
        assert custom_type in data["message"]

    @pytest.mark.asyncio
    async def test_all_event_types_return_processing_time(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
    ) -> None:
        """Test that all event types include processing time in response."""
        for event_type in WebhookEventType:
            entity_type = (
                WebhookEntityType.ACTIVITY
                if "activity" in event_type.value
                else WebhookEntityType.CUSTOM
            )
            payload = {
                "event_type": event_type.value,
                "entity_type": entity_type.value,
                "entity_id": str(uuid4()),
                "payload": {},
            }

            response = await client.post(
                "/api/v1/webhook",
                json=payload,
                headers=auth_headers,
            )

            assert response.status_code == 200, (
                f"Event type {event_type.value} should succeed"
            )
            data = response.json()
            assert "processing_time_ms" in data, (
                f"Event type {event_type.value} should include processing_time_ms"
            )
            assert data["processing_time_ms"] >= 0, (
                f"Processing time should be non-negative for {event_type.value}"
            )


# ============================================================================
# Response Structure Tests
# ============================================================================


class TestWebhookResponseStructure:
    """Tests for webhook response structure and content."""

    @pytest.mark.asyncio
    async def test_response_contains_all_required_fields(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
        minimal_valid_payload: dict[str, Any],
    ) -> None:
        """Test that response contains all required fields."""
        response = await client.post(
            "/api/v1/webhook",
            json=minimal_valid_payload,
            headers=auth_headers,
        )

        assert response.status_code == 200
        data = response.json()

        # Required response fields
        assert "status" in data
        assert "message" in data
        assert "event_type" in data
        assert "entity_id" in data
        assert "received_at" in data

    @pytest.mark.asyncio
    async def test_response_status_is_processed(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
        minimal_valid_payload: dict[str, Any],
    ) -> None:
        """Test that successful response has 'processed' status."""
        response = await client.post(
            "/api/v1/webhook",
            json=minimal_valid_payload,
            headers=auth_headers,
        )

        assert response.status_code == 200
        data = response.json()
        assert data["status"] == WebhookStatus.PROCESSED.value

    @pytest.mark.asyncio
    async def test_response_echoes_event_type_and_entity_id(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
    ) -> None:
        """Test that response echoes back event_type and entity_id."""
        entity_id = "test-entity-12345"
        payload = {
            "event_type": WebhookEventType.MEAL_LOGGED.value,
            "entity_type": WebhookEntityType.MEAL.value,
            "entity_id": entity_id,
        }

        response = await client.post(
            "/api/v1/webhook",
            json=payload,
            headers=auth_headers,
        )

        assert response.status_code == 200
        data = response.json()
        assert data["event_type"] == WebhookEventType.MEAL_LOGGED.value
        assert data["entity_id"] == entity_id

    @pytest.mark.asyncio
    async def test_response_received_at_is_valid_timestamp(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
        minimal_valid_payload: dict[str, Any],
    ) -> None:
        """Test that received_at is a valid ISO timestamp."""
        response = await client.post(
            "/api/v1/webhook",
            json=minimal_valid_payload,
            headers=auth_headers,
        )

        assert response.status_code == 200
        data = response.json()

        # Should be parseable as ISO timestamp
        received_at = datetime.fromisoformat(
            data["received_at"].replace("Z", "+00:00")
        )
        assert received_at is not None

    @pytest.mark.asyncio
    async def test_processing_time_is_reasonable(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
        minimal_valid_payload: dict[str, Any],
    ) -> None:
        """Test that processing time is reasonable (under 1 second for simple requests)."""
        response = await client.post(
            "/api/v1/webhook",
            json=minimal_valid_payload,
            headers=auth_headers,
        )

        assert response.status_code == 200
        data = response.json()
        assert data["processing_time_ms"] < 1000  # Should be under 1 second


# ============================================================================
# Webhook Health Check Tests
# ============================================================================


class TestWebhookHealthCheck:
    """Tests for the webhook health check endpoint."""

    @pytest.mark.asyncio
    async def test_health_check_returns_200(
        self,
        client: AsyncClient,
    ) -> None:
        """Test that health check returns 200 status."""
        response = await client.get("/api/v1/webhook/health")

        assert response.status_code == 200

    @pytest.mark.asyncio
    async def test_health_check_no_auth_required(
        self,
        client: AsyncClient,
    ) -> None:
        """Test that health check does not require authentication."""
        # No auth headers provided
        response = await client.get("/api/v1/webhook/health")

        assert response.status_code == 200

    @pytest.mark.asyncio
    async def test_health_check_returns_healthy_status(
        self,
        client: AsyncClient,
    ) -> None:
        """Test that health check returns healthy status."""
        response = await client.get("/api/v1/webhook/health")

        assert response.status_code == 200
        data = response.json()
        assert data["status"] == "healthy"
        assert data["service"] == "webhook"
        assert "timestamp" in data

    @pytest.mark.asyncio
    async def test_health_check_timestamp_is_valid(
        self,
        client: AsyncClient,
    ) -> None:
        """Test that health check timestamp is a valid ISO format."""
        response = await client.get("/api/v1/webhook/health")

        assert response.status_code == 200
        data = response.json()

        # Should be parseable as ISO timestamp
        timestamp = datetime.fromisoformat(
            data["timestamp"].replace("Z", "+00:00")
        )
        assert timestamp is not None


# ============================================================================
# Edge Case Tests
# ============================================================================


class TestWebhookEdgeCases:
    """Tests for webhook edge cases and boundary conditions."""

    @pytest.mark.asyncio
    async def test_large_payload_is_accepted(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
    ) -> None:
        """Test that reasonably large payloads are accepted."""
        payload = {
            "event_type": WebhookEventType.CUSTOM.value,
            "entity_type": WebhookEntityType.CUSTOM.value,
            "entity_id": str(uuid4()),
            "payload": {
                "large_data": "x" * 10000,  # 10KB of data
                "nested": {
                    "level1": {
                        "level2": {
                            "level3": {"data": ["item"] * 100}
                        }
                    }
                },
            },
        }

        response = await client.post(
            "/api/v1/webhook",
            json=payload,
            headers=auth_headers,
        )

        assert response.status_code == 200

    @pytest.mark.asyncio
    async def test_special_characters_in_entity_id(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
    ) -> None:
        """Test that special characters in entity_id are handled."""
        payload = {
            "event_type": WebhookEventType.CARE_ACTIVITY_CREATED.value,
            "entity_type": WebhookEntityType.ACTIVITY.value,
            "entity_id": "entity-123_abc",
        }

        response = await client.post(
            "/api/v1/webhook",
            json=payload,
            headers=auth_headers,
        )

        assert response.status_code == 200
        data = response.json()
        assert data["entity_id"] == "entity-123_abc"

    @pytest.mark.asyncio
    async def test_unicode_in_payload(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
    ) -> None:
        """Test that unicode characters in payload are handled."""
        payload = {
            "event_type": WebhookEventType.CARE_ACTIVITY_CREATED.value,
            "entity_type": WebhookEntityType.ACTIVITY.value,
            "entity_id": str(uuid4()),
            "payload": {
                "notes": "Child enjoyed activities 🎨 très bien!",
                "child_name": "José García",
            },
        }

        response = await client.post(
            "/api/v1/webhook",
            json=payload,
            headers=auth_headers,
        )

        assert response.status_code == 200

    @pytest.mark.asyncio
    async def test_null_optional_fields(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
    ) -> None:
        """Test that null values for optional fields are accepted."""
        payload = {
            "event_type": WebhookEventType.CARE_ACTIVITY_CREATED.value,
            "entity_type": WebhookEntityType.ACTIVITY.value,
            "entity_id": str(uuid4()),
            "payload": {},
            "timestamp": None,
            "gibbon_sync_log_id": None,
        }

        response = await client.post(
            "/api/v1/webhook",
            json=payload,
            headers=auth_headers,
        )

        assert response.status_code == 200

    @pytest.mark.asyncio
    async def test_entity_id_at_max_length(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
    ) -> None:
        """Test entity_id at exactly max length (100 chars)."""
        payload = {
            "event_type": WebhookEventType.CARE_ACTIVITY_CREATED.value,
            "entity_type": WebhookEntityType.ACTIVITY.value,
            "entity_id": "a" * 100,  # Exactly 100 chars
        }

        response = await client.post(
            "/api/v1/webhook",
            json=payload,
            headers=auth_headers,
        )

        assert response.status_code == 200

    @pytest.mark.asyncio
    async def test_valid_timestamp_format(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
    ) -> None:
        """Test that valid ISO timestamp is accepted."""
        payload = {
            "event_type": WebhookEventType.CARE_ACTIVITY_CREATED.value,
            "entity_type": WebhookEntityType.ACTIVITY.value,
            "entity_id": str(uuid4()),
            "timestamp": "2024-01-15T10:30:00Z",
        }

        response = await client.post(
            "/api/v1/webhook",
            json=payload,
            headers=auth_headers,
        )

        assert response.status_code == 200

    @pytest.mark.asyncio
    async def test_payload_with_various_data_types(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
    ) -> None:
        """Test that payload accepts various JSON data types."""
        payload = {
            "event_type": WebhookEventType.CUSTOM.value,
            "entity_type": WebhookEntityType.CUSTOM.value,
            "entity_id": str(uuid4()),
            "payload": {
                "string_value": "text",
                "int_value": 42,
                "float_value": 3.14159,
                "bool_value": True,
                "null_value": None,
                "array_value": [1, 2, 3],
                "nested_object": {"key": "value"},
            },
        }

        response = await client.post(
            "/api/v1/webhook",
            json=payload,
            headers=auth_headers,
        )

        assert response.status_code == 200
