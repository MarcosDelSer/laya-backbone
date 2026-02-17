"""Unit tests for security headers middleware.

Tests for XSS protection headers, HSTS headers, and comprehensive
security header verification across different endpoints and scenarios.
"""

from __future__ import annotations

from typing import Any
from unittest.mock import MagicMock, patch

import pytest
from fastapi import FastAPI, Request, status
from httpx import ASGITransport, AsyncClient

from app.middleware.security import (
    get_hsts_middleware,
    get_xss_protection_headers,
    get_xss_protection_middleware,
)


@pytest.fixture
def test_app() -> FastAPI:
    """Create a test FastAPI app with security headers middleware.

    Returns:
        FastAPI: Test application instance with middleware
    """
    app = FastAPI()

    # Add security middleware using the middleware decorator approach
    app.middleware("http")(get_xss_protection_middleware())
    app.middleware("http")(get_hsts_middleware())

    # Test endpoint that succeeds
    @app.get("/test/success")
    async def test_success(request: Request) -> dict[str, Any]:
        """Test endpoint that returns success."""
        return {"message": "success"}

    # Test endpoint for POST requests
    @app.post("/test/create")
    async def test_create(request: Request) -> dict[str, Any]:
        """Test endpoint for POST requests."""
        return {"message": "created"}

    # Test endpoint for error responses (HTTP 400)
    @app.get("/test/error")
    async def test_error() -> None:
        """Test endpoint that raises an HTTP error."""
        from fastapi import HTTPException
        raise HTTPException(status_code=400, detail="Bad request")

    return app


@pytest.mark.asyncio
async def test_xss_protection_headers_present(test_app: FastAPI) -> None:
    """Test that XSS protection headers are present on responses.

    Args:
        test_app: Test FastAPI application
    """
    async with AsyncClient(
        transport=ASGITransport(app=test_app), base_url="http://test"
    ) as client:
        response = await client.get("/test/success")

        assert response.status_code == status.HTTP_200_OK

        # Verify all XSS protection headers are present
        assert "Content-Security-Policy" in response.headers
        assert "X-Content-Type-Options" in response.headers
        assert "X-Frame-Options" in response.headers


@pytest.mark.asyncio
async def test_content_security_policy_header_value(test_app: FastAPI) -> None:
    """Test that Content-Security-Policy header has correct directives.

    Args:
        test_app: Test FastAPI application
    """
    async with AsyncClient(
        transport=ASGITransport(app=test_app), base_url="http://test"
    ) as client:
        response = await client.get("/test/success")

        assert response.status_code == status.HTTP_200_OK

        csp = response.headers.get("Content-Security-Policy", "")

        # Verify essential CSP directives
        assert "default-src 'self'" in csp
        assert "script-src" in csp
        assert "style-src" in csp
        assert "img-src" in csp
        assert "font-src" in csp
        assert "connect-src" in csp
        assert "frame-ancestors" in csp


@pytest.mark.asyncio
async def test_x_content_type_options_header_value(test_app: FastAPI) -> None:
    """Test that X-Content-Type-Options header prevents MIME sniffing.

    Args:
        test_app: Test FastAPI application
    """
    async with AsyncClient(
        transport=ASGITransport(app=test_app), base_url="http://test"
    ) as client:
        response = await client.get("/test/success")

        assert response.status_code == status.HTTP_200_OK

        # Should be set to 'nosniff' to prevent MIME-type sniffing
        assert response.headers.get("X-Content-Type-Options") == "nosniff"


@pytest.mark.asyncio
async def test_x_frame_options_header_value(test_app: FastAPI) -> None:
    """Test that X-Frame-Options header prevents clickjacking.

    Args:
        test_app: Test FastAPI application
    """
    async with AsyncClient(
        transport=ASGITransport(app=test_app), base_url="http://test"
    ) as client:
        response = await client.get("/test/success")

        assert response.status_code == status.HTTP_200_OK

        # Should be set to 'DENY' to prevent clickjacking
        assert response.headers.get("X-Frame-Options") == "DENY"


@pytest.mark.asyncio
async def test_security_headers_on_post_requests(test_app: FastAPI) -> None:
    """Test that security headers are present on POST requests.

    Args:
        test_app: Test FastAPI application
    """
    async with AsyncClient(
        transport=ASGITransport(app=test_app), base_url="http://test"
    ) as client:
        response = await client.post("/test/create", json={"data": "test"})

        assert response.status_code == status.HTTP_200_OK

        # Verify all security headers are present
        assert "Content-Security-Policy" in response.headers
        assert "X-Content-Type-Options" in response.headers
        assert "X-Frame-Options" in response.headers


@pytest.mark.asyncio
async def test_security_headers_on_error_responses(test_app: FastAPI) -> None:
    """Test that security headers are present even on error responses.

    Args:
        test_app: Test FastAPI application
    """
    async with AsyncClient(
        transport=ASGITransport(app=test_app), base_url="http://test"
    ) as client:
        response = await client.get("/test/error")

        # Response should be 400 Bad Request
        assert response.status_code == status.HTTP_400_BAD_REQUEST

        # Verify all security headers are still present on error responses
        assert "Content-Security-Policy" in response.headers
        assert "X-Content-Type-Options" in response.headers
        assert "X-Frame-Options" in response.headers


@pytest.mark.asyncio
async def test_security_headers_consistent_across_requests(test_app: FastAPI) -> None:
    """Test that security headers are consistent across multiple requests.

    Args:
        test_app: Test FastAPI application
    """
    async with AsyncClient(
        transport=ASGITransport(app=test_app), base_url="http://test"
    ) as client:
        # Make multiple requests
        responses = []
        for _ in range(3):
            response = await client.get("/test/success")
            responses.append(response)

        # Get headers from first response
        first_csp = responses[0].headers.get("Content-Security-Policy")
        first_xcto = responses[0].headers.get("X-Content-Type-Options")
        first_xfo = responses[0].headers.get("X-Frame-Options")

        # Verify headers are consistent across all responses
        for response in responses[1:]:
            assert response.headers.get("Content-Security-Policy") == first_csp
            assert response.headers.get("X-Content-Type-Options") == first_xcto
            assert response.headers.get("X-Frame-Options") == first_xfo


@pytest.mark.asyncio
async def test_hsts_header_on_https_request() -> None:
    """Test that HSTS header is added to HTTPS responses when enforcement is enabled."""
    # Mock settings with HTTPS enforcement enabled
    with patch("app.middleware.security.settings") as mock_settings:
        mock_settings.enforce_https = True

        # Create middleware
        middleware = get_hsts_middleware()

        # Create mock request (HTTPS)
        mock_request = MagicMock(spec=Request)
        mock_request.url.scheme = "https"
        mock_request.headers.get.return_value = None

        # Create mock response
        from fastapi import Response

        mock_response = Response(content="OK", status_code=200)

        async def call_next(request: Request) -> Response:
            return mock_response

        # Call middleware
        response = await middleware(mock_request, call_next)

        # Should have HSTS header
        assert "Strict-Transport-Security" in response.headers
        assert response.headers["Strict-Transport-Security"] == "max-age=31536000; includeSubDomains"


@pytest.mark.asyncio
async def test_hsts_header_not_on_http_request() -> None:
    """Test that HSTS header is NOT added to HTTP responses."""
    # Mock settings with HTTPS enforcement enabled
    with patch("app.middleware.security.settings") as mock_settings:
        mock_settings.enforce_https = True

        # Create middleware
        middleware = get_hsts_middleware()

        # Create mock request (HTTP)
        mock_request = MagicMock(spec=Request)
        mock_request.url.scheme = "http"
        mock_request.headers.get.return_value = None

        # Create mock response
        from fastapi import Response

        mock_response = Response(content="OK", status_code=200)

        async def call_next(request: Request) -> Response:
            return mock_response

        # Call middleware
        response = await middleware(mock_request, call_next)

        # Should NOT have HSTS header (HTTP request)
        assert "Strict-Transport-Security" not in response.headers


@pytest.mark.asyncio
async def test_hsts_header_with_x_forwarded_proto() -> None:
    """Test that HSTS header is added when X-Forwarded-Proto indicates HTTPS."""
    # Mock settings with HTTPS enforcement enabled
    with patch("app.middleware.security.settings") as mock_settings:
        mock_settings.enforce_https = True

        # Create middleware
        middleware = get_hsts_middleware()

        # Create mock request (HTTP but proxied with HTTPS)
        mock_request = MagicMock(spec=Request)
        mock_request.url.scheme = "http"
        mock_request.headers.get = MagicMock(
            side_effect=lambda key: "https" if key == "X-Forwarded-Proto" else None
        )

        # Create mock response
        from fastapi import Response

        mock_response = Response(content="OK", status_code=200)

        async def call_next(request: Request) -> Response:
            return mock_response

        # Call middleware
        response = await middleware(mock_request, call_next)

        # Should have HSTS header (proxied with HTTPS)
        assert "Strict-Transport-Security" in response.headers
        assert response.headers["Strict-Transport-Security"] == "max-age=31536000; includeSubDomains"


@pytest.mark.asyncio
async def test_xss_headers_structure() -> None:
    """Test that XSS protection headers helper returns correct structure."""
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

    # Verify specific values
    assert headers["X-Content-Type-Options"] == "nosniff"
    assert headers["X-Frame-Options"] == "DENY"


@pytest.mark.asyncio
async def test_security_headers_on_404_response(test_app: FastAPI) -> None:
    """Test that security headers are present even on 404 responses.

    Args:
        test_app: Test FastAPI application
    """
    async with AsyncClient(
        transport=ASGITransport(app=test_app), base_url="http://test"
    ) as client:
        response = await client.get("/nonexistent")

        # Verify response is 404
        assert response.status_code == status.HTTP_404_NOT_FOUND

        # Verify security headers are still present
        assert "Content-Security-Policy" in response.headers
        assert "X-Content-Type-Options" in response.headers
        assert "X-Frame-Options" in response.headers


@pytest.mark.asyncio
async def test_csp_prevents_inline_execution() -> None:
    """Test that CSP header directives prevent unsafe inline execution."""
    headers = get_xss_protection_headers()
    csp = headers["Content-Security-Policy"]

    # Verify CSP restricts to same origin by default
    assert "default-src 'self'" in csp

    # Verify frame-ancestors prevents clickjacking
    assert "frame-ancestors 'none'" in csp

    # Verify connect-src restricts AJAX/fetch
    assert "connect-src 'self'" in csp


@pytest.mark.asyncio
async def test_security_headers_with_different_status_codes(test_app: FastAPI) -> None:
    """Test that security headers are present on responses with different status codes.

    Args:
        test_app: Test FastAPI application
    """
    async with AsyncClient(
        transport=ASGITransport(app=test_app), base_url="http://test"
    ) as client:
        # Test 200 OK
        response_200 = await client.get("/test/success")
        assert response_200.status_code == status.HTTP_200_OK
        assert "Content-Security-Policy" in response_200.headers
        assert "X-Content-Type-Options" in response_200.headers
        assert "X-Frame-Options" in response_200.headers

        # Test 404 Not Found
        response_404 = await client.get("/nonexistent")
        assert response_404.status_code == status.HTTP_404_NOT_FOUND
        assert "Content-Security-Policy" in response_404.headers
        assert "X-Content-Type-Options" in response_404.headers
        assert "X-Frame-Options" in response_404.headers

        # Test 400 Bad Request
        response_400 = await client.get("/test/error")
        assert response_400.status_code == status.HTTP_400_BAD_REQUEST
        assert "Content-Security-Policy" in response_400.headers
        assert "X-Content-Type-Options" in response_400.headers
        assert "X-Frame-Options" in response_400.headers
