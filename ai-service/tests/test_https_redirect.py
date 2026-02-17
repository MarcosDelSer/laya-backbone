"""Tests for HTTPS redirect and HSTS middleware."""

from unittest.mock import AsyncMock, MagicMock, patch

import pytest
from fastapi import Request, Response

from app.middleware.security import (
    get_hsts_headers,
    get_hsts_middleware,
    get_https_redirect_middleware,
)


class TestHTTPSRedirectMiddleware:
    """Tests for HTTPS redirect middleware."""

    @pytest.mark.asyncio
    async def test_https_redirect_when_enforce_https_disabled(self):
        """Test that HTTP requests pass through when ENFORCE_HTTPS=false."""
        # Mock settings with HTTPS enforcement disabled
        with patch("app.middleware.security.settings") as mock_settings:
            mock_settings.enforce_https = False

            # Create middleware
            middleware = get_https_redirect_middleware()

            # Create mock request (HTTP)
            mock_request = MagicMock(spec=Request)
            mock_request.url.scheme = "http"
            mock_request.url.replace.return_value = "https://example.com/test"
            mock_request.headers.get.return_value = None

            # Create mock call_next that returns a response
            mock_response = Response(content="OK", status_code=200)
            call_next = AsyncMock(return_value=mock_response)

            # Call middleware
            response = await middleware(mock_request, call_next)

            # Should pass through without redirect
            assert response == mock_response
            assert response.status_code == 200
            call_next.assert_called_once_with(mock_request)

    @pytest.mark.asyncio
    async def test_https_redirect_when_enforce_https_enabled_and_http(self):
        """Test that HTTP requests are redirected to HTTPS when ENFORCE_HTTPS=true."""
        # Mock settings with HTTPS enforcement enabled
        with patch("app.middleware.security.settings") as mock_settings:
            mock_settings.enforce_https = True

            # Create middleware
            middleware = get_https_redirect_middleware()

            # Create mock request (HTTP)
            mock_request = MagicMock(spec=Request)
            mock_request.url.scheme = "http"
            mock_request.url.replace.return_value = "https://example.com/test"
            mock_request.headers.get.return_value = None

            # Create mock call_next
            call_next = AsyncMock()

            # Call middleware
            response = await middleware(mock_request, call_next)

            # Should redirect to HTTPS
            assert response.status_code == 301
            assert response.headers["location"] == "https://example.com/test"
            # Should not call next middleware
            call_next.assert_not_called()

    @pytest.mark.asyncio
    async def test_https_redirect_when_already_https(self):
        """Test that HTTPS requests pass through without redirect."""
        # Mock settings with HTTPS enforcement enabled
        with patch("app.middleware.security.settings") as mock_settings:
            mock_settings.enforce_https = True

            # Create middleware
            middleware = get_https_redirect_middleware()

            # Create mock request (HTTPS)
            mock_request = MagicMock(spec=Request)
            mock_request.url.scheme = "https"
            mock_request.headers.get.return_value = None

            # Create mock call_next that returns a response
            mock_response = Response(content="OK", status_code=200)
            call_next = AsyncMock(return_value=mock_response)

            # Call middleware
            response = await middleware(mock_request, call_next)

            # Should pass through without redirect
            assert response == mock_response
            assert response.status_code == 200
            call_next.assert_called_once_with(mock_request)

    @pytest.mark.asyncio
    async def test_https_redirect_with_x_forwarded_proto_https(self):
        """Test that requests with X-Forwarded-Proto: https pass through."""
        # Mock settings with HTTPS enforcement enabled
        with patch("app.middleware.security.settings") as mock_settings:
            mock_settings.enforce_https = True

            # Create middleware
            middleware = get_https_redirect_middleware()

            # Create mock request (HTTP but proxied with HTTPS)
            mock_request = MagicMock(spec=Request)
            mock_request.url.scheme = "http"
            mock_request.headers.get = MagicMock(side_effect=lambda key: "https" if key == "X-Forwarded-Proto" else None)

            # Create mock call_next that returns a response
            mock_response = Response(content="OK", status_code=200)
            call_next = AsyncMock(return_value=mock_response)

            # Call middleware
            response = await middleware(mock_request, call_next)

            # Should pass through without redirect (proxied with HTTPS)
            assert response == mock_response
            assert response.status_code == 200
            call_next.assert_called_once_with(mock_request)

    @pytest.mark.asyncio
    async def test_https_redirect_with_x_forwarded_ssl_on(self):
        """Test that requests with X-Forwarded-Ssl: on pass through."""
        # Mock settings with HTTPS enforcement enabled
        with patch("app.middleware.security.settings") as mock_settings:
            mock_settings.enforce_https = True

            # Create middleware
            middleware = get_https_redirect_middleware()

            # Create mock request (HTTP but proxied with SSL)
            mock_request = MagicMock(spec=Request)
            mock_request.url.scheme = "http"
            mock_request.headers.get = MagicMock(side_effect=lambda key: "on" if key == "X-Forwarded-Ssl" else None)

            # Create mock call_next that returns a response
            mock_response = Response(content="OK", status_code=200)
            call_next = AsyncMock(return_value=mock_response)

            # Call middleware
            response = await middleware(mock_request, call_next)

            # Should pass through without redirect (proxied with SSL)
            assert response == mock_response
            assert response.status_code == 200
            call_next.assert_called_once_with(mock_request)

    @pytest.mark.asyncio
    async def test_https_redirect_preserves_path_and_query(self):
        """Test that redirect preserves path and query parameters."""
        # Mock settings with HTTPS enforcement enabled
        with patch("app.middleware.security.settings") as mock_settings:
            mock_settings.enforce_https = True

            # Create middleware
            middleware = get_https_redirect_middleware()

            # Create mock request (HTTP with path and query)
            mock_request = MagicMock(spec=Request)
            mock_request.url.scheme = "http"
            mock_request.url.replace.return_value = "https://example.com/api/v1/test?param=value"
            mock_request.headers.get.return_value = None

            # Create mock call_next
            call_next = AsyncMock()

            # Call middleware
            response = await middleware(mock_request, call_next)

            # Should redirect to HTTPS with full path and query
            assert response.status_code == 301
            assert response.headers["location"] == "https://example.com/api/v1/test?param=value"
            call_next.assert_not_called()


class TestHSTSMiddleware:
    """Tests for HSTS middleware."""

    def test_get_hsts_headers(self):
        """Test HSTS headers generation."""
        headers = get_hsts_headers()

        assert "Strict-Transport-Security" in headers
        assert headers["Strict-Transport-Security"] == "max-age=31536000; includeSubDomains"

    @pytest.mark.asyncio
    async def test_hsts_header_added_to_https_responses(self):
        """Test that HSTS header is added to HTTPS responses."""
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
            mock_response = Response(content="OK", status_code=200)
            call_next = AsyncMock(return_value=mock_response)

            # Call middleware
            response = await middleware(mock_request, call_next)

            # Should have HSTS header
            assert "Strict-Transport-Security" in response.headers
            assert response.headers["Strict-Transport-Security"] == "max-age=31536000; includeSubDomains"
            call_next.assert_called_once_with(mock_request)

    @pytest.mark.asyncio
    async def test_hsts_header_not_added_to_http_responses(self):
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
            mock_response = Response(content="OK", status_code=200)
            call_next = AsyncMock(return_value=mock_response)

            # Call middleware
            response = await middleware(mock_request, call_next)

            # Should NOT have HSTS header (HTTP request)
            assert "Strict-Transport-Security" not in response.headers
            call_next.assert_called_once_with(mock_request)

    @pytest.mark.asyncio
    async def test_hsts_header_added_for_proxied_https(self):
        """Test that HSTS header is added for proxied HTTPS requests."""
        # Mock settings with HTTPS enforcement enabled
        with patch("app.middleware.security.settings") as mock_settings:
            mock_settings.enforce_https = True

            # Create middleware
            middleware = get_hsts_middleware()

            # Create mock request (HTTP but proxied with HTTPS)
            mock_request = MagicMock(spec=Request)
            mock_request.url.scheme = "http"
            mock_request.headers.get = MagicMock(side_effect=lambda key: "https" if key == "X-Forwarded-Proto" else None)

            # Create mock response
            mock_response = Response(content="OK", status_code=200)
            call_next = AsyncMock(return_value=mock_response)

            # Call middleware
            response = await middleware(mock_request, call_next)

            # Should have HSTS header (proxied with HTTPS)
            assert "Strict-Transport-Security" in response.headers
            assert response.headers["Strict-Transport-Security"] == "max-age=31536000; includeSubDomains"
            call_next.assert_called_once_with(mock_request)

    @pytest.mark.asyncio
    async def test_hsts_header_not_added_when_enforce_https_disabled(self):
        """Test that HSTS header is NOT added when ENFORCE_HTTPS=false."""
        # Mock settings with HTTPS enforcement disabled
        with patch("app.middleware.security.settings") as mock_settings:
            mock_settings.enforce_https = False

            # Create middleware
            middleware = get_hsts_middleware()

            # Create mock request (HTTPS)
            mock_request = MagicMock(spec=Request)
            mock_request.url.scheme = "https"
            mock_request.headers.get.return_value = None

            # Create mock response
            mock_response = Response(content="OK", status_code=200)
            call_next = AsyncMock(return_value=mock_response)

            # Call middleware
            response = await middleware(mock_request, call_next)

            # Should NOT have HSTS header (enforcement disabled)
            assert "Strict-Transport-Security" not in response.headers
            call_next.assert_called_once_with(mock_request)

    @pytest.mark.asyncio
    async def test_hsts_header_with_x_forwarded_ssl_on(self):
        """Test that HSTS header is added for X-Forwarded-Ssl: on."""
        # Mock settings with HTTPS enforcement enabled
        with patch("app.middleware.security.settings") as mock_settings:
            mock_settings.enforce_https = True

            # Create middleware
            middleware = get_hsts_middleware()

            # Create mock request (HTTP but with X-Forwarded-Ssl: on)
            mock_request = MagicMock(spec=Request)
            mock_request.url.scheme = "http"
            mock_request.headers.get = MagicMock(side_effect=lambda key: "on" if key == "X-Forwarded-Ssl" else None)

            # Create mock response
            mock_response = Response(content="OK", status_code=200)
            call_next = AsyncMock(return_value=mock_response)

            # Call middleware
            response = await middleware(mock_request, call_next)

            # Should have HSTS header
            assert "Strict-Transport-Security" in response.headers
            call_next.assert_called_once_with(mock_request)
