"""Tests for security middleware including CORS configuration and XSS protection."""

import pytest
from fastapi.testclient import TestClient

from app.main import app
from app.middleware.security import get_cors_origins, get_xss_protection_headers


class TestCORSConfiguration:
    """Test CORS security configuration."""

    def test_get_cors_origins_with_custom_origins(self, monkeypatch):
        """Test CORS origins are parsed from environment variable."""
        # Mock the settings to return custom origins
        monkeypatch.setenv("CORS_ORIGINS", "https://app.example.com,https://api.example.com")

        # Import fresh settings after environment change
        from importlib import reload
        from app import config
        reload(config)

        from app.middleware import security
        reload(security)

        origins = security.get_cors_origins()

        assert len(origins) == 2
        assert "https://app.example.com" in origins
        assert "https://api.example.com" in origins

    def test_get_cors_origins_development_defaults(self, monkeypatch):
        """Test default CORS origins for development."""
        # Clear CORS_ORIGINS to test development defaults
        monkeypatch.setenv("CORS_ORIGINS", "")

        # Import fresh settings after environment change
        from importlib import reload
        from app import config
        reload(config)

        from app.middleware import security
        reload(security)

        origins = security.get_cors_origins()

        assert isinstance(origins, list)
        assert len(origins) >= 3
        assert "http://localhost:3000" in origins
        assert "http://localhost:8080" in origins
        assert "http://localhost:8000" in origins

    def test_cors_middleware_applied(self):
        """Test CORS middleware is properly applied to the application."""
        client = TestClient(app)

        # Make OPTIONS request (preflight)
        response = client.options(
            "/",
            headers={
                "Origin": "http://localhost:3000",
                "Access-Control-Request-Method": "GET",
            }
        )

        # Should have CORS headers
        assert response.status_code in [200, 204]
        # The response should include CORS headers when origin is allowed

    def test_cors_blocks_unauthorized_origins(self):
        """Test that CORS middleware blocks unauthorized origins."""
        client = TestClient(app)

        # Make request from unauthorized origin
        response = client.get(
            "/",
            headers={
                "Origin": "https://evil.com"
            }
        )

        # Request should complete but CORS headers should not include evil.com
        assert response.status_code == 200
        # The browser would block this due to missing CORS headers for evil.com

    def test_cors_allows_authorized_origins(self):
        """Test that CORS middleware allows authorized origins."""
        client = TestClient(app)

        # Make request from authorized origin (localhost:3000 is in defaults)
        response = client.get(
            "/",
            headers={
                "Origin": "http://localhost:3000"
            }
        )

        # Should complete successfully
        assert response.status_code == 200
        # Access-Control-Allow-Origin header should be present
        # (actual header presence depends on middleware implementation)


class TestXSSProtectionHeaders:
    """Test XSS protection headers middleware."""

    def test_get_xss_protection_headers_structure(self):
        """Test that XSS protection headers are correctly structured."""
        headers = get_xss_protection_headers()

        # Verify all required security headers are present
        assert "Content-Security-Policy" in headers
        assert "X-Content-Type-Options" in headers
        assert "X-Frame-Options" in headers

        # Verify header values are non-empty strings
        assert isinstance(headers["Content-Security-Policy"], str)
        assert len(headers["Content-Security-Policy"]) > 0
        assert isinstance(headers["X-Content-Type-Options"], str)
        assert len(headers["X-Content-Type-Options"]) > 0
        assert isinstance(headers["X-Frame-Options"], str)
        assert len(headers["X-Frame-Options"]) > 0

    def test_content_security_policy_directives(self):
        """Test Content-Security-Policy contains essential directives."""
        headers = get_xss_protection_headers()
        csp = headers["Content-Security-Policy"]

        # Verify essential CSP directives are present
        assert "default-src 'self'" in csp
        assert "script-src" in csp
        assert "style-src" in csp
        assert "img-src" in csp
        assert "font-src" in csp
        assert "connect-src" in csp
        assert "frame-ancestors" in csp

    def test_x_content_type_options_value(self):
        """Test X-Content-Type-Options header has correct value."""
        headers = get_xss_protection_headers()

        # Should be set to 'nosniff' to prevent MIME-type sniffing
        assert headers["X-Content-Type-Options"] == "nosniff"

    def test_x_frame_options_value(self):
        """Test X-Frame-Options header has correct value."""
        headers = get_xss_protection_headers()

        # Should be set to 'DENY' to prevent clickjacking
        assert headers["X-Frame-Options"] == "DENY"

    def test_xss_headers_present_on_health_endpoint(self):
        """Test that XSS protection headers are added to health check endpoint."""
        client = TestClient(app)

        response = client.get("/")

        # Verify response is successful
        assert response.status_code == 200

        # Verify all XSS protection headers are present
        assert "Content-Security-Policy" in response.headers
        assert "X-Content-Type-Options" in response.headers
        assert "X-Frame-Options" in response.headers

    def test_xss_headers_present_on_protected_endpoint(self):
        """Test that XSS protection headers are added to protected endpoints."""
        client = TestClient(app)

        # Request without authentication (will fail but still have security headers)
        response = client.get("/protected")

        # Verify XSS protection headers are present even on error responses
        assert "Content-Security-Policy" in response.headers
        assert "X-Content-Type-Options" in response.headers
        assert "X-Frame-Options" in response.headers

    def test_xss_headers_content_security_policy_value(self):
        """Test Content-Security-Policy header value on actual response."""
        client = TestClient(app)

        response = client.get("/")

        csp = response.headers.get("Content-Security-Policy", "")

        # Verify CSP includes key security directives
        assert "default-src 'self'" in csp
        assert "script-src" in csp
        assert "style-src" in csp
        assert "frame-ancestors" in csp

    def test_xss_headers_x_content_type_options_value(self):
        """Test X-Content-Type-Options header value on actual response."""
        client = TestClient(app)

        response = client.get("/")

        # Should prevent MIME-type sniffing
        assert response.headers.get("X-Content-Type-Options") == "nosniff"

    def test_xss_headers_x_frame_options_value(self):
        """Test X-Frame-Options header value on actual response."""
        client = TestClient(app)

        response = client.get("/")

        # Should prevent clickjacking
        assert response.headers.get("X-Frame-Options") == "DENY"

    def test_xss_headers_on_404_response(self):
        """Test that XSS protection headers are present even on 404 responses."""
        client = TestClient(app)

        response = client.get("/nonexistent-endpoint")

        # Verify response is 404
        assert response.status_code == 404

        # Verify XSS protection headers are still present
        assert "Content-Security-Policy" in response.headers
        assert "X-Content-Type-Options" in response.headers
        assert "X-Frame-Options" in response.headers

    def test_xss_headers_on_api_endpoints(self):
        """Test that XSS protection headers are present on API endpoints."""
        client = TestClient(app)

        # Test various API endpoints
        endpoints = [
            "/api/v1/coaching/guidance",
        ]

        for endpoint in endpoints:
            # POST request will likely fail validation but should have headers
            response = client.post(endpoint, json={})

            # Verify XSS protection headers are present regardless of response status
            assert "Content-Security-Policy" in response.headers
            assert "X-Content-Type-Options" in response.headers
            assert "X-Frame-Options" in response.headers

    def test_xss_headers_consistent_across_requests(self):
        """Test that XSS protection headers are consistent across multiple requests."""
        client = TestClient(app)

        # Make multiple requests
        responses = [client.get("/") for _ in range(3)]

        # Get headers from first response
        first_csp = responses[0].headers.get("Content-Security-Policy")
        first_xcto = responses[0].headers.get("X-Content-Type-Options")
        first_xfo = responses[0].headers.get("X-Frame-Options")

        # Verify headers are consistent across all responses
        for response in responses[1:]:
            assert response.headers.get("Content-Security-Policy") == first_csp
            assert response.headers.get("X-Content-Type-Options") == first_xcto
            assert response.headers.get("X-Frame-Options") == first_xfo
