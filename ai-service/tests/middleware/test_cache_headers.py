"""Tests for cache headers middleware.

This module tests the CacheHeadersMiddleware to ensure proper cache control
headers are added to different types of responses.
"""

import pytest
from fastapi import FastAPI
from fastapi.responses import JSONResponse, Response
from fastapi.testclient import TestClient

from app.middleware.cache_headers import CacheHeadersMiddleware, create_cache_middleware


@pytest.fixture
def app():
    """Create a test FastAPI application with cache middleware."""
    test_app = FastAPI()

    # Add cache headers middleware
    test_app.add_middleware(CacheHeadersMiddleware)

    # Define test routes
    @test_app.get("/api/data")
    async def get_data():
        """Test API endpoint returning JSON."""
        return JSONResponse({"status": "ok"})

    @test_app.get("/static/image.png")
    async def get_static_image():
        """Test static image endpoint."""
        return Response(
            content=b"fake image data",
            media_type="image/png"
        )

    @test_app.get("/static/style.abc12345.css")
    async def get_versioned_css():
        """Test versioned CSS file."""
        return Response(
            content="body { margin: 0; }",
            media_type="text/css"
        )

    @test_app.get("/static/script.def67890.js")
    async def get_versioned_js():
        """Test versioned JavaScript file."""
        return Response(
            content="console.log('test');",
            media_type="application/javascript"
        )

    @test_app.get("/assets/font.woff2")
    async def get_font():
        """Test font file."""
        return Response(
            content=b"fake font data",
            media_type="font/woff2"
        )

    @test_app.get("/media/video.mp4")
    async def get_video():
        """Test video file."""
        return Response(
            content=b"fake video data",
            media_type="video/mp4"
        )

    @test_app.get("/uploads/document.pdf")
    async def get_pdf():
        """Test PDF document."""
        return Response(
            content=b"fake pdf data",
            media_type="application/pdf"
        )

    @test_app.get("/page.html")
    async def get_html():
        """Test HTML page."""
        return Response(
            content="<html><body>Test</body></html>",
            media_type="text/html"
        )

    @test_app.get("/existing-cache")
    async def get_with_existing_cache():
        """Test endpoint that already has cache headers."""
        return Response(
            content="data",
            headers={"Cache-Control": "max-age=3600"}
        )

    return test_app


def test_api_json_response_no_cache(app):
    """Test that API JSON responses get no-cache headers."""
    client = TestClient(app)
    response = client.get("/api/data")

    assert response.status_code == 200
    assert "cache-control" in response.headers
    assert "no-cache" in response.headers["cache-control"]
    assert "no-store" in response.headers["cache-control"]
    assert "must-revalidate" in response.headers["cache-control"]
    assert "vary" in response.headers
    assert "Accept" in response.headers["vary"]
    assert "Authorization" in response.headers["vary"]


def test_static_image_cache(app):
    """Test that static images get appropriate cache headers."""
    client = TestClient(app)
    response = client.get("/static/image.png")

    assert response.status_code == 200
    assert "cache-control" in response.headers
    assert "public" in response.headers["cache-control"]
    assert "max-age=2592000" in response.headers["cache-control"]  # 1 month
    assert "etag" in response.headers


def test_versioned_css_immutable(app):
    """Test that versioned CSS files get immutable cache headers."""
    client = TestClient(app)
    response = client.get("/static/style.abc12345.css")

    assert response.status_code == 200
    assert "cache-control" in response.headers
    assert "public" in response.headers["cache-control"]
    assert "max-age=31536000" in response.headers["cache-control"]  # 1 year
    assert "immutable" in response.headers["cache-control"]
    assert "etag" in response.headers


def test_versioned_js_immutable(app):
    """Test that versioned JavaScript files get immutable cache headers."""
    client = TestClient(app)
    response = client.get("/static/script.def67890.js")

    assert response.status_code == 200
    assert "cache-control" in response.headers
    assert "public" in response.headers["cache-control"]
    assert "max-age=31536000" in response.headers["cache-control"]  # 1 year
    assert "immutable" in response.headers["cache-control"]
    assert "etag" in response.headers


def test_font_file_cache(app):
    """Test that font files get long-term cache headers."""
    client = TestClient(app)
    response = client.get("/assets/font.woff2")

    assert response.status_code == 200
    assert "cache-control" in response.headers
    assert "public" in response.headers["cache-control"]
    assert "max-age=31536000" in response.headers["cache-control"]  # 1 year
    assert "immutable" in response.headers["cache-control"]
    assert "etag" in response.headers


def test_video_file_cache(app):
    """Test that video files get appropriate cache headers."""
    client = TestClient(app)
    response = client.get("/media/video.mp4")

    assert response.status_code == 200
    assert "cache-control" in response.headers
    assert "public" in response.headers["cache-control"]
    assert "max-age=2592000" in response.headers["cache-control"]  # 1 month
    assert "etag" in response.headers


def test_pdf_document_cache(app):
    """Test that PDF documents get revalidate cache headers."""
    client = TestClient(app)
    response = client.get("/uploads/document.pdf")

    assert response.status_code == 200
    assert "cache-control" in response.headers
    assert "public" in response.headers["cache-control"]
    assert "max-age=86400" in response.headers["cache-control"]  # 1 day
    assert "must-revalidate" in response.headers["cache-control"]
    assert "etag" in response.headers


def test_html_page_no_cache(app):
    """Test that HTML pages get no-cache headers."""
    client = TestClient(app)
    response = client.get("/page.html")

    assert response.status_code == 200
    assert "cache-control" in response.headers
    assert "no-cache" in response.headers["cache-control"]
    assert "must-revalidate" in response.headers["cache-control"]


def test_existing_cache_headers_preserved(app):
    """Test that existing cache headers are not overwritten."""
    client = TestClient(app)
    response = client.get("/existing-cache")

    assert response.status_code == 200
    assert "cache-control" in response.headers
    assert response.headers["cache-control"] == "max-age=3600"


def test_middleware_is_static_path():
    """Test the _is_static_path method."""
    app = FastAPI()
    middleware = CacheHeadersMiddleware(app)

    assert middleware._is_static_path("/static/image.png") is True
    assert middleware._is_static_path("/assets/font.woff2") is True
    assert middleware._is_static_path("/media/video.mp4") is True
    assert middleware._is_static_path("/uploads/file.pdf") is True
    assert middleware._is_static_path("/api/data") is False
    assert middleware._is_static_path("/page") is False


def test_middleware_is_immutable_asset():
    """Test the _is_immutable_asset method."""
    app = FastAPI()
    middleware = CacheHeadersMiddleware(app)

    # Versioned assets (should be immutable)
    assert middleware._is_immutable_asset("/static/main.abc12345.js") is True
    assert middleware._is_immutable_asset("/static/style.def67890.css") is True
    assert middleware._is_immutable_asset("/static/image.a1b2c3d4.png") is True
    assert middleware._is_immutable_asset("/static/font.12345678.woff2") is True

    # Non-versioned assets (should not be immutable)
    assert middleware._is_immutable_asset("/static/main.js") is False
    assert middleware._is_immutable_asset("/static/style.css") is False
    assert middleware._is_immutable_asset("/static/image.png") is False


def test_middleware_get_cache_control():
    """Test the _get_cache_control method."""
    app = FastAPI()
    middleware = CacheHeadersMiddleware(app)

    # Immutable versioned assets
    assert "immutable" in middleware._get_cache_control(
        "/static/main.abc12345.js",
        "application/javascript"
    )

    # Regular images
    cache_control = middleware._get_cache_control("/static/image.png", "image/png")
    assert "public" in cache_control
    assert "max-age=2592000" in cache_control

    # JSON responses
    cache_control = middleware._get_cache_control("/api/data", "application/json")
    assert "no-cache" in cache_control
    assert "no-store" in cache_control

    # HTML pages
    cache_control = middleware._get_cache_control("/page.html", "text/html")
    assert "no-cache" in cache_control
    assert "must-revalidate" in cache_control


def test_create_cache_middleware_factory():
    """Test the create_cache_middleware factory function."""
    custom_static_paths = ["/custom-static", "/custom-assets"]
    custom_rules = {"image/png": "public, max-age=1000"}

    MiddlewareClass = create_cache_middleware(
        static_paths=custom_static_paths,
        cache_rules=custom_rules
    )

    app = FastAPI()
    middleware = MiddlewareClass(app)

    assert "/custom-static" in middleware.static_paths
    assert "/custom-assets" in middleware.static_paths
    assert middleware.cache_rules["image/png"] == "public, max-age=1000"


@pytest.fixture
def custom_app():
    """Create a test app with custom cache configuration."""
    test_app = FastAPI()

    # Create custom middleware with different configuration
    CustomMiddleware = create_cache_middleware(
        static_paths=["/files"],
        cache_rules={"image/png": "public, max-age=1000"}
    )

    test_app.add_middleware(CustomMiddleware)

    @test_app.get("/files/image.png")
    async def get_image():
        return Response(
            content=b"fake image",
            media_type="image/png"
        )

    return test_app


def test_custom_cache_rules(custom_app):
    """Test that custom cache rules are applied correctly."""
    client = TestClient(custom_app)
    response = client.get("/files/image.png")

    assert response.status_code == 200
    assert "cache-control" in response.headers
    assert "max-age=1000" in response.headers["cache-control"]


def test_middleware_initialization_defaults():
    """Test middleware initialization with default values."""
    app = FastAPI()
    middleware = CacheHeadersMiddleware(app)

    assert "/static" in middleware.static_paths
    assert "/assets" in middleware.static_paths
    assert "/media" in middleware.static_paths
    assert "/uploads" in middleware.static_paths
    assert "image/png" in middleware.cache_rules
    assert "application/json" in middleware.cache_rules


def test_middleware_initialization_custom():
    """Test middleware initialization with custom values."""
    app = FastAPI()
    custom_paths = ["/custom"]
    custom_pattern = r"\.[a-f0-9]{16,}\.(js|css)$"
    custom_rules = {"text/plain": "public, max-age=500"}

    middleware = CacheHeadersMiddleware(
        app,
        static_paths=custom_paths,
        immutable_pattern=custom_pattern,
        cache_rules=custom_rules
    )

    assert middleware.static_paths == custom_paths
    assert "text/plain" in middleware.cache_rules
    assert middleware.cache_rules["text/plain"] == "public, max-age=500"
    # Default rules should still be present
    assert "image/png" in middleware.cache_rules
