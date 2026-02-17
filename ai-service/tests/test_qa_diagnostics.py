"""Unit tests for QA diagnostics functionality.

Tests for iOS real-device diagnostics ingestion endpoint,
payload validation, and schema enforcement.
"""

from __future__ import annotations

from datetime import datetime, timezone
from typing import Any
from uuid import uuid4

import pytest
import pytest_asyncio
from httpx import AsyncClient

from app.schemas.qa_diagnostics import (
    AppEnvironment,
    BatteryState,
    DiagnosticsStatus,
    LogLevel,
    NetworkErrorType,
)
from tests.conftest import create_test_token


# Alias for async_client -> client fixture used throughout this test module
@pytest_asyncio.fixture
async def async_client(client: AsyncClient) -> AsyncClient:
    """Alias for the client fixture for backward compatibility."""
    return client


# ============================================================================
# QA Diagnostics request fixtures
# ============================================================================


@pytest.fixture
def valid_app_metadata() -> dict[str, Any]:
    """Create valid app metadata.

    Returns:
        dict: Valid app metadata payload
    """
    return {
        "app_name": "TeacherApp",
        "app_version": "2.1.0",
        "build_number": "1234",
        "bundle_id": "com.laya.teacherapp",
        "environment": AppEnvironment.STAGING.value,
    }


@pytest.fixture
def valid_device_metadata() -> dict[str, Any]:
    """Create valid device metadata.

    Returns:
        dict: Valid device metadata payload
    """
    return {
        "device_model": "iPhone 15 Pro",
        "device_identifier": "a1b2c3d4e5f6",
        "ios_version": "17.2.1",
        "locale": "en_US",
        "timezone": "America/New_York",
        "is_simulator": False,
        "available_storage_mb": 45678,
        "total_memory_mb": 6144,
        "battery_level": 0.85,
        "battery_state": BatteryState.UNPLUGGED.value,
    }


@pytest.fixture
def valid_log_entry() -> dict[str, Any]:
    """Create a valid log entry.

    Returns:
        dict: Valid log entry payload
    """
    return {
        "timestamp": datetime.now(timezone.utc).isoformat(),
        "level": LogLevel.ERROR.value,
        "tag": "AttendanceAPI",
        "message": "Failed to fetch attendance: HTTP 500",
        "metadata": {
            "endpoint": "/api/v1/attendance",
            "retry_count": 3,
        },
    }


@pytest.fixture
def valid_network_error() -> dict[str, Any]:
    """Create a valid network error entry.

    Returns:
        dict: Valid network error payload
    """
    return {
        "timestamp": datetime.now(timezone.utc).isoformat(),
        "request_url": "https://api.laya.app/v1/attendance?date=[REDACTED]",
        "request_method": "GET",
        "status_code": 500,
        "error_type": NetworkErrorType.SERVER_ERROR.value,
        "error_message": "Internal Server Error",
        "request_duration_ms": 1234,
        "retry_count": 3,
    }


@pytest.fixture
def minimal_diagnostics_payload(
    valid_app_metadata: dict[str, Any],
    valid_device_metadata: dict[str, Any],
) -> dict[str, Any]:
    """Create minimal valid diagnostics payload (required fields only).

    Returns:
        dict: Minimal valid diagnostics payload
    """
    return {
        "test_run_id": str(uuid4()),
        "app_metadata": valid_app_metadata,
        "device_metadata": valid_device_metadata,
        "timestamp_collected": datetime.now(timezone.utc).isoformat(),
    }


@pytest.fixture
def full_diagnostics_payload(
    valid_app_metadata: dict[str, Any],
    valid_device_metadata: dict[str, Any],
    valid_log_entry: dict[str, Any],
    valid_network_error: dict[str, Any],
) -> dict[str, Any]:
    """Create full diagnostics payload with optional fields.

    Returns:
        dict: Full diagnostics payload with all fields
    """
    return {
        "test_run_id": str(uuid4()),
        "app_metadata": valid_app_metadata,
        "device_metadata": valid_device_metadata,
        "timestamp_collected": datetime.now(timezone.utc).isoformat(),
        "logs": [valid_log_entry],
        "network_errors": [valid_network_error],
        "custom_data": {
            "feature_flags": {"new_attendance_ui": True},
            "screens_visited": ["login", "dashboard", "attendance"],
        },
    }


# ============================================================================
# QA Diagnostics ingestion endpoint tests
# ============================================================================


class TestQADiagnosticsIngestion:
    """Test suite for QA diagnostics ingestion endpoint."""

    @pytest.mark.asyncio
    async def test_ingest_minimal_diagnostics_success(
        self,
        async_client: AsyncClient,
        minimal_diagnostics_payload: dict[str, Any],
    ):
        """Test successful ingestion with minimal payload."""
        token = create_test_token("test-qa-service")
        headers = {"Authorization": f"Bearer {token}"}

        response = await async_client.post(
            "/api/v1/qa/diagnostics",
            json=minimal_diagnostics_payload,
            headers=headers,
        )

        assert response.status_code == 201
        data = response.json()
        assert "diagnostics_id" in data
        assert data["diagnostics_id"].startswith("diag_")
        assert data["test_run_id"] == minimal_diagnostics_payload["test_run_id"]
        assert data["status"] == DiagnosticsStatus.ACCEPTED.value
        assert "received_at" in data

    @pytest.mark.asyncio
    async def test_ingest_full_diagnostics_success(
        self,
        async_client: AsyncClient,
        full_diagnostics_payload: dict[str, Any],
    ):
        """Test successful ingestion with full payload including optional fields."""
        token = create_test_token("test-qa-service")
        headers = {"Authorization": f"Bearer {token}"}

        response = await async_client.post(
            "/api/v1/qa/diagnostics",
            json=full_diagnostics_payload,
            headers=headers,
        )

        assert response.status_code == 201
        data = response.json()
        assert data["diagnostics_id"].startswith("diag_")
        assert data["status"] == DiagnosticsStatus.ACCEPTED.value

    @pytest.mark.asyncio
    async def test_ingest_diagnostics_missing_auth(
        self,
        async_client: AsyncClient,
        minimal_diagnostics_payload: dict[str, Any],
    ):
        """Test ingestion fails without authentication."""
        response = await async_client.post(
            "/api/v1/qa/diagnostics",
            json=minimal_diagnostics_payload,
        )

        assert response.status_code == 401

    @pytest.mark.asyncio
    async def test_ingest_diagnostics_invalid_token(
        self,
        async_client: AsyncClient,
        minimal_diagnostics_payload: dict[str, Any],
    ):
        """Test ingestion fails with invalid token."""
        headers = {"Authorization": "Bearer invalid_token"}

        response = await async_client.post(
            "/api/v1/qa/diagnostics",
            json=minimal_diagnostics_payload,
            headers=headers,
        )

        assert response.status_code == 401


class TestQADiagnosticsValidation:
    """Test suite for QA diagnostics payload validation."""

    @pytest.mark.asyncio
    async def test_missing_test_run_id(
        self,
        async_client: AsyncClient,
        valid_app_metadata: dict[str, Any],
        valid_device_metadata: dict[str, Any],
    ):
        """Test validation fails when test_run_id is missing."""
        token = create_test_token("test-qa-service")
        headers = {"Authorization": f"Bearer {token}"}

        payload = {
            # test_run_id is missing
            "app_metadata": valid_app_metadata,
            "device_metadata": valid_device_metadata,
            "timestamp_collected": datetime.now(timezone.utc).isoformat(),
        }

        response = await async_client.post(
            "/api/v1/qa/diagnostics",
            json=payload,
            headers=headers,
        )

        assert response.status_code == 422

    @pytest.mark.asyncio
    async def test_invalid_test_run_id_format(
        self,
        async_client: AsyncClient,
        valid_app_metadata: dict[str, Any],
        valid_device_metadata: dict[str, Any],
    ):
        """Test validation fails with invalid UUID format."""
        token = create_test_token("test-qa-service")
        headers = {"Authorization": f"Bearer {token}"}

        payload = {
            "test_run_id": "not-a-valid-uuid",
            "app_metadata": valid_app_metadata,
            "device_metadata": valid_device_metadata,
            "timestamp_collected": datetime.now(timezone.utc).isoformat(),
        }

        response = await async_client.post(
            "/api/v1/qa/diagnostics",
            json=payload,
            headers=headers,
        )

        assert response.status_code == 422

    @pytest.mark.asyncio
    async def test_missing_app_metadata(
        self,
        async_client: AsyncClient,
        valid_device_metadata: dict[str, Any],
    ):
        """Test validation fails when app_metadata is missing."""
        token = create_test_token("test-qa-service")
        headers = {"Authorization": f"Bearer {token}"}

        payload = {
            "test_run_id": str(uuid4()),
            # app_metadata is missing
            "device_metadata": valid_device_metadata,
            "timestamp_collected": datetime.now(timezone.utc).isoformat(),
        }

        response = await async_client.post(
            "/api/v1/qa/diagnostics",
            json=payload,
            headers=headers,
        )

        assert response.status_code == 422

    @pytest.mark.asyncio
    async def test_missing_device_metadata(
        self,
        async_client: AsyncClient,
        valid_app_metadata: dict[str, Any],
    ):
        """Test validation fails when device_metadata is missing."""
        token = create_test_token("test-qa-service")
        headers = {"Authorization": f"Bearer {token}"}

        payload = {
            "test_run_id": str(uuid4()),
            "app_metadata": valid_app_metadata,
            # device_metadata is missing
            "timestamp_collected": datetime.now(timezone.utc).isoformat(),
        }

        response = await async_client.post(
            "/api/v1/qa/diagnostics",
            json=payload,
            headers=headers,
        )

        assert response.status_code == 422

    @pytest.mark.asyncio
    async def test_invalid_app_version_format(
        self,
        async_client: AsyncClient,
        valid_device_metadata: dict[str, Any],
    ):
        """Test validation fails with invalid app version format."""
        token = create_test_token("test-qa-service")
        headers = {"Authorization": f"Bearer {token}"}

        payload = {
            "test_run_id": str(uuid4()),
            "app_metadata": {
                "app_name": "TeacherApp",
                "app_version": "invalid-version",  # Invalid format
                "build_number": "1234",
                "bundle_id": "com.laya.teacherapp",
                "environment": "staging",
            },
            "device_metadata": valid_device_metadata,
            "timestamp_collected": datetime.now(timezone.utc).isoformat(),
        }

        response = await async_client.post(
            "/api/v1/qa/diagnostics",
            json=payload,
            headers=headers,
        )

        assert response.status_code == 422

    @pytest.mark.asyncio
    async def test_invalid_ios_version_format(
        self,
        async_client: AsyncClient,
        valid_app_metadata: dict[str, Any],
    ):
        """Test validation fails with invalid iOS version format."""
        token = create_test_token("test-qa-service")
        headers = {"Authorization": f"Bearer {token}"}

        payload = {
            "test_run_id": str(uuid4()),
            "app_metadata": valid_app_metadata,
            "device_metadata": {
                "device_model": "iPhone 15 Pro",
                "device_identifier": "a1b2c3d4",
                "ios_version": "invalid",  # Invalid format
                "locale": "en_US",
                "timezone": "America/New_York",
                "is_simulator": False,
                "available_storage_mb": 45678,
                "total_memory_mb": 6144,
            },
            "timestamp_collected": datetime.now(timezone.utc).isoformat(),
        }

        response = await async_client.post(
            "/api/v1/qa/diagnostics",
            json=payload,
            headers=headers,
        )

        assert response.status_code == 422

    @pytest.mark.asyncio
    async def test_invalid_battery_level(
        self,
        async_client: AsyncClient,
        valid_app_metadata: dict[str, Any],
    ):
        """Test validation fails with battery level out of range."""
        token = create_test_token("test-qa-service")
        headers = {"Authorization": f"Bearer {token}"}

        payload = {
            "test_run_id": str(uuid4()),
            "app_metadata": valid_app_metadata,
            "device_metadata": {
                "device_model": "iPhone 15 Pro",
                "device_identifier": "a1b2c3d4",
                "ios_version": "17.2.1",
                "locale": "en_US",
                "timezone": "America/New_York",
                "is_simulator": False,
                "available_storage_mb": 45678,
                "total_memory_mb": 6144,
                "battery_level": 1.5,  # Out of range (should be 0.0-1.0)
            },
            "timestamp_collected": datetime.now(timezone.utc).isoformat(),
        }

        response = await async_client.post(
            "/api/v1/qa/diagnostics",
            json=payload,
            headers=headers,
        )

        assert response.status_code == 422

    @pytest.mark.asyncio
    async def test_invalid_log_level(
        self,
        async_client: AsyncClient,
        minimal_diagnostics_payload: dict[str, Any],
    ):
        """Test validation fails with invalid log level."""
        token = create_test_token("test-qa-service")
        headers = {"Authorization": f"Bearer {token}"}

        minimal_diagnostics_payload["logs"] = [
            {
                "timestamp": datetime.now(timezone.utc).isoformat(),
                "level": "invalid_level",  # Invalid log level
                "tag": "Test",
                "message": "Test message",
            }
        ]

        response = await async_client.post(
            "/api/v1/qa/diagnostics",
            json=minimal_diagnostics_payload,
            headers=headers,
        )

        assert response.status_code == 422

    @pytest.mark.asyncio
    async def test_invalid_network_error_type(
        self,
        async_client: AsyncClient,
        minimal_diagnostics_payload: dict[str, Any],
    ):
        """Test validation fails with invalid network error type."""
        token = create_test_token("test-qa-service")
        headers = {"Authorization": f"Bearer {token}"}

        minimal_diagnostics_payload["network_errors"] = [
            {
                "timestamp": datetime.now(timezone.utc).isoformat(),
                "request_url": "https://api.example.com/test",
                "request_method": "GET",
                "status_code": 500,
                "error_type": "invalid_type",  # Invalid error type
                "error_message": "Error",
                "request_duration_ms": 100,
                "retry_count": 0,
            }
        ]

        response = await async_client.post(
            "/api/v1/qa/diagnostics",
            json=minimal_diagnostics_payload,
            headers=headers,
        )

        assert response.status_code == 422


class TestQADiagnosticsHealthCheck:
    """Test suite for QA diagnostics health check endpoint."""

    @pytest.mark.asyncio
    async def test_health_check_success(self, async_client: AsyncClient):
        """Test health check endpoint returns healthy status."""
        response = await async_client.get("/api/v1/qa/diagnostics/health")

        assert response.status_code == 200
        data = response.json()
        assert data["status"] == "healthy"
        assert data["service"] == "qa-diagnostics"
        assert "timestamp" in data

    @pytest.mark.asyncio
    async def test_health_check_no_auth_required(self, async_client: AsyncClient):
        """Test health check endpoint does not require authentication."""
        # No Authorization header
        response = await async_client.get("/api/v1/qa/diagnostics/health")

        assert response.status_code == 200


class TestQADiagnosticsRetrieval:
    """Test suite for QA diagnostics retrieval endpoints."""

    @pytest.mark.asyncio
    async def test_get_diagnostics_not_found(self, async_client: AsyncClient):
        """Test retrieving non-existent diagnostics returns 404."""
        token = create_test_token("test-qa-service")
        headers = {"Authorization": f"Bearer {token}"}

        response = await async_client.get(
            "/api/v1/qa/diagnostics/diag_nonexistent",
            headers=headers,
        )

        assert response.status_code == 404
        data = response.json()
        assert data["detail"]["error"] == "not_found"

    @pytest.mark.asyncio
    async def test_get_diagnostics_by_run_empty(self, async_client: AsyncClient):
        """Test retrieving diagnostics by run ID returns empty list."""
        token = create_test_token("test-qa-service")
        headers = {"Authorization": f"Bearer {token}"}
        test_run_id = str(uuid4())

        response = await async_client.get(
            f"/api/v1/qa/diagnostics/run/{test_run_id}",
            headers=headers,
        )

        assert response.status_code == 200
        data = response.json()
        assert data["test_run_id"] == test_run_id
        assert data["diagnostics"] == []
        assert data["count"] == 0

    @pytest.mark.asyncio
    async def test_get_diagnostics_requires_auth(self, async_client: AsyncClient):
        """Test retrieval endpoints require authentication."""
        # No Authorization header
        response = await async_client.get(
            "/api/v1/qa/diagnostics/diag_test123",
        )

        assert response.status_code == 401


class TestQADiagnosticsSchemaValidation:
    """Test suite for schema-level validations."""

    @pytest.mark.asyncio
    async def test_log_message_max_length(
        self,
        async_client: AsyncClient,
        minimal_diagnostics_payload: dict[str, Any],
    ):
        """Test log message length validation (max 1000 chars)."""
        token = create_test_token("test-qa-service")
        headers = {"Authorization": f"Bearer {token}"}

        minimal_diagnostics_payload["logs"] = [
            {
                "timestamp": datetime.now(timezone.utc).isoformat(),
                "level": LogLevel.INFO.value,
                "tag": "Test",
                "message": "x" * 1001,  # Exceeds 1000 char limit
            }
        ]

        response = await async_client.post(
            "/api/v1/qa/diagnostics",
            json=minimal_diagnostics_payload,
            headers=headers,
        )

        assert response.status_code == 422

    @pytest.mark.asyncio
    async def test_status_code_valid_range(
        self,
        async_client: AsyncClient,
        minimal_diagnostics_payload: dict[str, Any],
    ):
        """Test status code validation (100-599)."""
        token = create_test_token("test-qa-service")
        headers = {"Authorization": f"Bearer {token}"}

        minimal_diagnostics_payload["network_errors"] = [
            {
                "timestamp": datetime.now(timezone.utc).isoformat(),
                "request_url": "https://api.example.com/test",
                "request_method": "GET",
                "status_code": 999,  # Out of valid HTTP range
                "error_type": NetworkErrorType.SERVER_ERROR.value,
                "error_message": "Error",
                "request_duration_ms": 100,
                "retry_count": 0,
            }
        ]

        response = await async_client.post(
            "/api/v1/qa/diagnostics",
            json=minimal_diagnostics_payload,
            headers=headers,
        )

        assert response.status_code == 422

    @pytest.mark.asyncio
    async def test_null_status_code_allowed(
        self,
        async_client: AsyncClient,
        minimal_diagnostics_payload: dict[str, Any],
    ):
        """Test null status code is allowed (for no-response errors)."""
        token = create_test_token("test-qa-service")
        headers = {"Authorization": f"Bearer {token}"}

        minimal_diagnostics_payload["network_errors"] = [
            {
                "timestamp": datetime.now(timezone.utc).isoformat(),
                "request_url": "https://api.example.com/test",
                "request_method": "GET",
                "status_code": None,  # Null is allowed
                "error_type": NetworkErrorType.TIMEOUT.value,
                "error_message": "Request timed out",
                "request_duration_ms": 30000,
                "retry_count": 3,
            }
        ]

        response = await async_client.post(
            "/api/v1/qa/diagnostics",
            json=minimal_diagnostics_payload,
            headers=headers,
        )

        assert response.status_code == 201

    @pytest.mark.asyncio
    async def test_empty_logs_array_allowed(
        self,
        async_client: AsyncClient,
        minimal_diagnostics_payload: dict[str, Any],
    ):
        """Test empty logs array is allowed."""
        token = create_test_token("test-qa-service")
        headers = {"Authorization": f"Bearer {token}"}

        minimal_diagnostics_payload["logs"] = []

        response = await async_client.post(
            "/api/v1/qa/diagnostics",
            json=minimal_diagnostics_payload,
            headers=headers,
        )

        assert response.status_code == 201

    @pytest.mark.asyncio
    async def test_custom_data_allowed(
        self,
        async_client: AsyncClient,
        minimal_diagnostics_payload: dict[str, Any],
    ):
        """Test custom_data field accepts arbitrary JSON."""
        token = create_test_token("test-qa-service")
        headers = {"Authorization": f"Bearer {token}"}

        minimal_diagnostics_payload["custom_data"] = {
            "feature_flags": {
                "new_dashboard": True,
                "beta_camera": False,
            },
            "session_duration_seconds": 120,
            "screens_visited": ["login", "dashboard", "attendance"],
            "nested": {
                "deep": {
                    "value": 42,
                }
            },
        }

        response = await async_client.post(
            "/api/v1/qa/diagnostics",
            json=minimal_diagnostics_payload,
            headers=headers,
        )

        assert response.status_code == 201
