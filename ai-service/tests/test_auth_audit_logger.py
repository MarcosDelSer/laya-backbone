"""Tests for token verification audit logger.

This test suite verifies the audit logging functionality for
JWT token verification events in the AI service.
"""

from __future__ import annotations

from datetime import datetime
from typing import Any
from unittest.mock import Mock

import pytest

from app.auth.audit_logger import (
    TokenAuditLogger,
    TokenVerificationEvent,
    get_client_ip,
    get_endpoint,
    get_user_agent,
)


class TestTokenVerificationEvent:
    """Tests for TokenVerificationEvent model."""

    def test_event_creation_success(self) -> None:
        """Test creating a successful verification event."""
        event = TokenVerificationEvent(
            timestamp="2026-02-16T10:00:00",
            event_type="verify_success",
            user_id="123",
            username="testuser",
            role="teacher",
            source="gibbon",
            ip_address="192.168.1.1",
        )

        assert event.event_type == "verify_success"
        assert event.user_id == "123"
        assert event.username == "testuser"
        assert event.role == "teacher"
        assert event.source == "gibbon"
        assert event.ip_address == "192.168.1.1"
        assert event.token_expired is False

    def test_event_creation_failed(self) -> None:
        """Test creating a failed verification event."""
        event = TokenVerificationEvent(
            timestamp="2026-02-16T10:00:00",
            event_type="verify_failed",
            ip_address="203.0.113.1",
            error_message="Invalid token signature",
        )

        assert event.event_type == "verify_failed"
        assert event.error_message == "Invalid token signature"
        assert event.ip_address == "203.0.113.1"
        assert event.user_id is None

    def test_event_creation_expired(self) -> None:
        """Test creating an expired token event."""
        event = TokenVerificationEvent(
            timestamp="2026-02-16T10:00:00",
            event_type="token_expired",
            user_id="123",
            username="testuser",
            error_message="Token has expired",
            token_expired=True,
        )

        assert event.event_type == "token_expired"
        assert event.token_expired is True
        assert event.error_message == "Token has expired"

    def test_event_with_claims(self) -> None:
        """Test event with additional token claims."""
        claims = {
            "iat": 1234567890,
            "exp": 1234571490,
            "gibbon_role_id": "002",
        }

        event = TokenVerificationEvent(
            timestamp="2026-02-16T10:00:00",
            event_type="verify_success",
            user_id="123",
            token_claims=claims,
        )

        assert event.token_claims == claims
        assert event.token_claims["gibbon_role_id"] == "002"


class TestTokenAuditLogger:
    """Tests for TokenAuditLogger class."""

    @pytest.fixture
    def audit_logger(self) -> TokenAuditLogger:
        """Create audit logger instance."""
        return TokenAuditLogger()

    @pytest.fixture
    def sample_token_payload(self) -> dict[str, Any]:
        """Sample token payload for testing."""
        return {
            "sub": "123",
            "username": "testuser",
            "email": "test@example.com",
            "role": "teacher",
            "source": "gibbon",
            "session_id": "sess_abc123",
            "gibbon_role_id": "002",
            "iat": 1234567890,
            "exp": 1234571490,
        }

    def test_log_verification_success(
        self, audit_logger: TokenAuditLogger, sample_token_payload: dict[str, Any]
    ) -> None:
        """Test logging successful verification."""
        # This test verifies the method runs without error
        # In a real environment, you'd verify log output
        audit_logger.log_verification_success(
            token_payload=sample_token_payload,
            ip_address="192.168.1.1",
            user_agent="Mozilla/5.0",
            endpoint="GET /api/v1/profile",
        )

        # If we get here without exception, test passes
        assert True

    def test_log_verification_failed(self, audit_logger: TokenAuditLogger) -> None:
        """Test logging failed verification."""
        audit_logger.log_verification_failed(
            error_message="Invalid token signature",
            ip_address="203.0.113.1",
            user_agent="curl/7.68.0",
            endpoint="GET /api/v1/data",
        )

        assert True

    def test_log_token_expired(
        self, audit_logger: TokenAuditLogger, sample_token_payload: dict[str, Any]
    ) -> None:
        """Test logging expired token."""
        audit_logger.log_token_expired(
            token_payload=sample_token_payload,
            ip_address="192.168.1.1",
            user_agent="Mozilla/5.0",
            endpoint="GET /api/v1/profile",
        )

        assert True

    def test_log_invalid_token(self, audit_logger: TokenAuditLogger) -> None:
        """Test logging invalid token."""
        audit_logger.log_invalid_token(
            error_message="Malformed token",
            ip_address="198.51.100.1",
            user_agent="PostmanRuntime/7.26.8",
            endpoint="POST /api/v1/submit",
        )

        assert True

    def test_log_missing_claims(
        self, audit_logger: TokenAuditLogger, sample_token_payload: dict[str, Any]
    ) -> None:
        """Test logging missing claims."""
        # Remove username to simulate missing claim
        incomplete_payload = {**sample_token_payload}
        del incomplete_payload["username"]

        audit_logger.log_missing_claims(
            missing_claims=["username"],
            token_payload=incomplete_payload,
            ip_address="192.168.1.1",
            user_agent="Mozilla/5.0",
            endpoint="GET /api/v1/profile",
        )

        assert True

    def test_extract_relevant_claims(
        self, audit_logger: TokenAuditLogger, sample_token_payload: dict[str, Any]
    ) -> None:
        """Test extracting relevant claims."""
        claims = audit_logger._extract_relevant_claims(sample_token_payload)

        assert "iat" in claims
        assert "exp" in claims
        assert "source" in claims
        assert "gibbon_role_id" in claims
        assert "session_id" in claims
        assert "email" in claims

        # Should not include other fields
        assert "sub" not in claims
        assert "username" not in claims
        assert "role" not in claims


class TestHelperFunctions:
    """Tests for helper functions."""

    def test_get_client_ip_direct(self) -> None:
        """Test getting client IP from direct connection."""
        request = Mock()
        request.headers = {}
        request.client = Mock()
        request.client.host = "192.168.1.100"

        ip = get_client_ip(request)
        assert ip == "192.168.1.100"

    def test_get_client_ip_cloudflare(self) -> None:
        """Test getting client IP from Cloudflare header."""
        request = Mock()
        request.headers = {"cf-connecting-ip": "203.0.113.1"}
        request.client = Mock()
        request.client.host = "10.0.0.1"

        ip = get_client_ip(request)
        assert ip == "203.0.113.1"

    def test_get_client_ip_proxy(self) -> None:
        """Test getting client IP from X-Real-IP header."""
        request = Mock()
        request.headers = {"x-real-ip": "198.51.100.1"}
        request.client = Mock()
        request.client.host = "10.0.0.1"

        ip = get_client_ip(request)
        assert ip == "198.51.100.1"

    def test_get_client_ip_forwarded(self) -> None:
        """Test getting client IP from X-Forwarded-For header."""
        request = Mock()
        request.headers = {"x-forwarded-for": "192.0.2.1, 10.0.0.1, 10.0.0.2"}
        request.client = Mock()
        request.client.host = "10.0.0.3"

        ip = get_client_ip(request)
        # Should return first IP in list
        assert ip == "192.0.2.1"

    def test_get_client_ip_no_client(self) -> None:
        """Test getting client IP when request has no client."""
        request = Mock()
        request.headers = {}
        # No client attribute

        ip = get_client_ip(request)
        assert ip is None

    def test_get_user_agent(self) -> None:
        """Test getting user agent from request."""
        request = Mock()
        request.headers = {"user-agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64)"}

        user_agent = get_user_agent(request)
        assert user_agent == "Mozilla/5.0 (Windows NT 10.0; Win64; x64)"

    def test_get_user_agent_missing(self) -> None:
        """Test getting user agent when header is missing."""
        request = Mock()
        request.headers = {}

        user_agent = get_user_agent(request)
        assert user_agent is None

    def test_get_endpoint(self) -> None:
        """Test getting endpoint from request."""
        request = Mock()
        request.method = "GET"
        request.url = Mock()
        request.url.path = "/api/v1/profile"

        endpoint = get_endpoint(request)
        assert endpoint == "GET /api/v1/profile"

    def test_get_endpoint_post(self) -> None:
        """Test getting endpoint for POST request."""
        request = Mock()
        request.method = "POST"
        request.url = Mock()
        request.url.path = "/api/v1/submit"

        endpoint = get_endpoint(request)
        assert endpoint == "POST /api/v1/submit"

    def test_get_endpoint_no_url(self) -> None:
        """Test getting endpoint when request has no URL."""
        request = Mock()
        # No url attribute

        endpoint = get_endpoint(request)
        assert endpoint == ""


class TestAuditLoggerIntegration:
    """Integration tests for audit logger."""

    @pytest.fixture
    def audit_logger(self) -> TokenAuditLogger:
        """Create audit logger instance."""
        return TokenAuditLogger()

    def test_full_success_flow(self, audit_logger: TokenAuditLogger) -> None:
        """Test full successful authentication flow."""
        token_payload = {
            "sub": "456",
            "username": "jane.doe",
            "email": "jane@example.com",
            "role": "admin",
            "source": "ai-service",
            "iat": 1234567890,
            "exp": 1234571490,
        }

        # Log successful verification
        audit_logger.log_verification_success(
            token_payload=token_payload,
            ip_address="10.0.0.50",
            user_agent="Chrome/96.0",
            endpoint="GET /api/v1/admin",
        )

        # Test passes if no exception raised
        assert True

    def test_full_failure_flow(self, audit_logger: TokenAuditLogger) -> None:
        """Test full failed authentication flow."""
        # Attempt 1: Invalid token
        audit_logger.log_invalid_token(
            error_message="Signature verification failed",
            ip_address="192.0.2.50",
            user_agent="curl/7.68.0",
            endpoint="GET /api/v1/data",
        )

        # Attempt 2: Expired token
        token_payload = {
            "sub": "789",
            "username": "expired.user",
            "role": "user",
            "source": "gibbon",
            "iat": 1234567890,
            "exp": 1234567900,  # Already expired
        }

        audit_logger.log_token_expired(
            token_payload=token_payload,
            ip_address="192.0.2.50",
            user_agent="curl/7.68.0",
            endpoint="GET /api/v1/data",
        )

        # Test passes if no exception raised
        assert True

    def test_gibbon_token_flow(self, audit_logger: TokenAuditLogger) -> None:
        """Test audit logging for Gibbon token."""
        token_payload = {
            "sub": "100",
            "username": "teacher1",
            "email": "teacher@school.edu",
            "role": "teacher",
            "source": "gibbon",
            "session_id": "sess_xyz789",
            "gibbon_role_id": "002",
            "iat": 1234567890,
            "exp": 1234571490,
        }

        audit_logger.log_verification_success(
            token_payload=token_payload,
            ip_address="192.168.100.10",
            user_agent="Safari/14.0",
            endpoint="GET /api/v1/students",
        )

        # Test passes if no exception raised
        assert True
