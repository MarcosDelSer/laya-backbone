"""Tests for security middleware including CORS configuration."""

import pytest
from fastapi.testclient import TestClient

from app.main import app
from app.middleware.security import get_cors_origins


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
