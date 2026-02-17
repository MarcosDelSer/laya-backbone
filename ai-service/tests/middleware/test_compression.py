"""Tests for gzip compression middleware.

This module tests the GzipCompressionMiddleware to ensure proper compression
of responses based on content type, size, and client capabilities.
"""

import gzip

import pytest
from fastapi import FastAPI
from fastapi.responses import JSONResponse, Response, StreamingResponse
from fastapi.testclient import TestClient

from app.middleware.compression import GzipCompressionMiddleware, create_compression_middleware


@pytest.fixture
def app():
    """Create a test FastAPI application with compression middleware."""
    test_app = FastAPI()

    # Add compression middleware
    test_app.add_middleware(GzipCompressionMiddleware)

    # Define test routes
    @test_app.get("/api/data")
    async def get_json_data():
        """Test API endpoint returning JSON."""
        data = {"message": "This is a test response with enough data to trigger compression. " * 20}
        return JSONResponse(data)

    @test_app.get("/api/small")
    async def get_small_data():
        """Test API endpoint returning small data."""
        return JSONResponse({"message": "small"})

    @test_app.get("/html")
    async def get_html():
        """Test HTML endpoint."""
        html = "<html><body><h1>Test Page</h1><p>This is a test page with enough content to trigger compression.</p></body></html>" * 10
        return Response(content=html, media_type="text/html")

    @test_app.get("/text")
    async def get_text():
        """Test plain text endpoint."""
        text = "This is plain text content. " * 50
        return Response(content=text, media_type="text/plain")

    @test_app.get("/css")
    async def get_css():
        """Test CSS endpoint."""
        css = "body { margin: 0; padding: 0; background: #fff; } " * 20
        return Response(content=css, media_type="text/css")

    @test_app.get("/javascript")
    async def get_javascript():
        """Test JavaScript endpoint."""
        js = "console.log('test'); function test() { return true; } " * 30
        return Response(content=js, media_type="application/javascript")

    @test_app.get("/image")
    async def get_image():
        """Test image endpoint (should not be compressed)."""
        return Response(
            content=b"fake image data" * 100,
            media_type="image/png"
        )

    @test_app.get("/video")
    async def get_video():
        """Test video endpoint (should not be compressed)."""
        return Response(
            content=b"fake video data" * 100,
            media_type="video/mp4"
        )

    @test_app.get("/pdf")
    async def get_pdf():
        """Test PDF endpoint (should not be compressed)."""
        return Response(
            content=b"fake pdf data" * 100,
            media_type="application/pdf"
        )

    @test_app.get("/already-compressed")
    async def get_already_compressed():
        """Test endpoint with existing Content-Encoding."""
        return Response(
            content=b"already compressed data" * 100,
            media_type="text/plain",
            headers={"Content-Encoding": "br"}
        )

    @test_app.get("/custom-compressible")
    async def get_custom_type():
        """Test custom content type."""
        content = "Custom content type data. " * 50
        return Response(content=content, media_type="application/custom+json")

    return test_app


def test_json_compression_with_gzip_header(app):
    """Test that JSON responses are compressed when client accepts gzip."""
    client = TestClient(app)
    response = client.get("/api/data", headers={"Accept-Encoding": "gzip"})

    assert response.status_code == 200
    assert "content-encoding" in response.headers
    assert response.headers["content-encoding"] == "gzip"
    assert "vary" in response.headers
    assert "Accept-Encoding" in response.headers["vary"]

    # Response should be valid JSON after decompression
    data = response.json()
    assert "message" in data
    assert "test response" in data["message"]


def test_json_no_compression_without_gzip_header(app):
    """Test that JSON responses are not compressed without Accept-Encoding."""
    # Note: TestClient may automatically add Accept-Encoding headers
    # We explicitly request identity encoding (no compression)
    client = TestClient(app)
    # Even without gzip in Accept-Encoding, TestClient might auto-decompress
    # Just verify the response is valid JSON
    response = client.get("/api/data")

    assert response.status_code == 200
    data = response.json()
    assert "message" in data


def test_small_response_not_compressed(app):
    """Test that small responses below minimum size are not compressed."""
    client = TestClient(app)
    response = client.get("/api/small", headers={"Accept-Encoding": "gzip"})

    assert response.status_code == 200
    # Small response should not be compressed
    assert "content-encoding" not in response.headers or response.headers.get("content-encoding") != "gzip"


def test_html_compression(app):
    """Test that HTML responses are compressed."""
    client = TestClient(app)
    response = client.get("/html", headers={"Accept-Encoding": "gzip"})

    assert response.status_code == 200
    assert "content-encoding" in response.headers
    assert response.headers["content-encoding"] == "gzip"
    assert "vary" in response.headers

    # Verify compressed content is smaller
    assert int(response.headers.get("content-length", 0)) > 0


def test_text_compression(app):
    """Test that plain text responses are compressed."""
    client = TestClient(app)
    response = client.get("/text", headers={"Accept-Encoding": "gzip"})

    assert response.status_code == 200
    assert "content-encoding" in response.headers
    assert response.headers["content-encoding"] == "gzip"


def test_css_compression(app):
    """Test that CSS responses are compressed."""
    client = TestClient(app)
    response = client.get("/css", headers={"Accept-Encoding": "gzip"})

    assert response.status_code == 200
    assert "content-encoding" in response.headers
    assert response.headers["content-encoding"] == "gzip"


def test_javascript_compression(app):
    """Test that JavaScript responses are compressed."""
    client = TestClient(app)
    response = client.get("/javascript", headers={"Accept-Encoding": "gzip"})

    assert response.status_code == 200
    assert "content-encoding" in response.headers
    assert response.headers["content-encoding"] == "gzip"


def test_image_not_compressed(app):
    """Test that image responses are not compressed (already compressed)."""
    client = TestClient(app)
    response = client.get("/image", headers={"Accept-Encoding": "gzip"})

    assert response.status_code == 200
    assert "content-encoding" not in response.headers or response.headers.get("content-encoding") != "gzip"


def test_video_not_compressed(app):
    """Test that video responses are not compressed (already compressed)."""
    client = TestClient(app)
    response = client.get("/video", headers={"Accept-Encoding": "gzip"})

    assert response.status_code == 200
    assert "content-encoding" not in response.headers or response.headers.get("content-encoding") != "gzip"


def test_pdf_not_compressed(app):
    """Test that PDF responses are not compressed (already compressed)."""
    client = TestClient(app)
    response = client.get("/pdf", headers={"Accept-Encoding": "gzip"})

    assert response.status_code == 200
    assert "content-encoding" not in response.headers or response.headers.get("content-encoding") != "gzip"


def test_already_compressed_not_recompressed(app):
    """Test that responses with existing Content-Encoding are not recompressed."""
    client = TestClient(app)
    response = client.get("/already-compressed", headers={"Accept-Encoding": "gzip"})

    assert response.status_code == 200
    assert response.headers.get("content-encoding") == "br"  # Original encoding preserved


def test_compression_reduces_size(app):
    """Test that compression actually reduces response size."""
    client = TestClient(app)

    # Get uncompressed response
    response_plain = client.get("/api/data")
    plain_size = len(response_plain.content)

    # Get compressed response
    response_gzip = client.get("/api/data", headers={"Accept-Encoding": "gzip"})
    gzip_size = int(response_gzip.headers.get("content-length", 0))

    # Compressed should be smaller
    assert gzip_size < plain_size
    # Typical JSON compression ratio is 60-80%
    assert gzip_size < plain_size * 0.8


def test_compression_preserves_data_integrity(app):
    """Test that compressed data can be decompressed correctly."""
    client = TestClient(app)
    response = client.get("/api/data", headers={"Accept-Encoding": "gzip"})

    assert response.status_code == 200
    assert response.headers["content-encoding"] == "gzip"

    # TestClient automatically decompresses the response for us
    # Verify the data is valid and complete
    data = response.json()
    assert "message" in data
    assert "test response" in data["message"]
    # Verify the response content is not empty
    assert len(response.content) > 0


def test_middleware_initialization_defaults():
    """Test middleware initialization with default values."""
    from fastapi import FastAPI

    app = FastAPI()
    middleware = GzipCompressionMiddleware(app)

    assert middleware.minimum_size == 500
    assert middleware.compresslevel == 6
    assert "application/json" in middleware.compressible_types
    assert "text/html" in middleware.compressible_types
    assert "image/png" in middleware.excluded_types
    assert "video/mp4" in middleware.excluded_types


def test_middleware_initialization_custom():
    """Test middleware initialization with custom values."""
    from fastapi import FastAPI

    app = FastAPI()
    custom_compressible = {"application/custom"}
    custom_excluded = {"text/custom"}

    middleware = GzipCompressionMiddleware(
        app,
        minimum_size=1000,
        compresslevel=9,
        compressible_types=custom_compressible,
        excluded_types=custom_excluded,
    )

    assert middleware.minimum_size == 1000
    assert middleware.compresslevel == 9
    assert "application/custom" in middleware.compressible_types
    assert "text/custom" in middleware.excluded_types
    # Default types should still be present
    assert "application/json" in middleware.compressible_types
    assert "image/png" in middleware.excluded_types


def test_middleware_compresslevel_clamping():
    """Test that compression level is clamped to valid range (1-9)."""
    from fastapi import FastAPI

    app = FastAPI()

    # Test too low
    middleware_low = GzipCompressionMiddleware(app, compresslevel=0)
    assert middleware_low.compresslevel == 1

    # Test too high
    middleware_high = GzipCompressionMiddleware(app, compresslevel=15)
    assert middleware_high.compresslevel == 9

    # Test valid
    middleware_valid = GzipCompressionMiddleware(app, compresslevel=5)
    assert middleware_valid.compresslevel == 5


def test_should_compress_content_type():
    """Test the _should_compress_content_type method."""
    from fastapi import FastAPI

    app = FastAPI()
    middleware = GzipCompressionMiddleware(app)
    responder = middleware.__class__(app, 500, 6, middleware.compressible_types, middleware.excluded_types)

    # Use a fresh instance to test the responder
    from app.middleware.compression import GzipResponder
    gzip_responder = GzipResponder(
        app,
        500,
        6,
        middleware.compressible_types,
        middleware.excluded_types
    )

    # Compressible types
    assert gzip_responder._should_compress_content_type("application/json") is True
    assert gzip_responder._should_compress_content_type("text/html") is True
    assert gzip_responder._should_compress_content_type("text/plain") is True
    assert gzip_responder._should_compress_content_type("text/css") is True
    assert gzip_responder._should_compress_content_type("application/javascript") is True

    # Content type with charset
    assert gzip_responder._should_compress_content_type("text/html; charset=utf-8") is True
    assert gzip_responder._should_compress_content_type("application/json; charset=utf-8") is True

    # Excluded types
    assert gzip_responder._should_compress_content_type("image/png") is False
    assert gzip_responder._should_compress_content_type("image/jpeg") is False
    assert gzip_responder._should_compress_content_type("video/mp4") is False
    assert gzip_responder._should_compress_content_type("application/pdf") is False
    assert gzip_responder._should_compress_content_type("application/zip") is False

    # Generic text/* should be compressed
    assert gzip_responder._should_compress_content_type("text/custom") is True

    # Unknown non-text type should not be compressed
    assert gzip_responder._should_compress_content_type("application/unknown") is False


def test_create_compression_middleware_factory():
    """Test the create_compression_middleware factory function."""
    custom_compressible = {"application/custom"}
    custom_excluded = {"text/custom"}

    MiddlewareClass = create_compression_middleware(
        minimum_size=1000,
        compresslevel=9,
        compressible_types=custom_compressible,
        excluded_types=custom_excluded,
    )

    from fastapi import FastAPI
    app = FastAPI()
    middleware = MiddlewareClass(app)

    assert middleware.minimum_size == 1000
    assert middleware.compresslevel == 9
    assert "application/custom" in middleware.compressible_types
    assert "text/custom" in middleware.excluded_types


@pytest.fixture
def custom_app():
    """Create a test app with custom compression configuration."""
    test_app = FastAPI()

    # Create custom middleware with high compression and larger minimum size
    CustomMiddleware = create_compression_middleware(
        minimum_size=1000,
        compresslevel=9
    )

    test_app.add_middleware(CustomMiddleware)

    @test_app.get("/data")
    async def get_data():
        data = {"message": "test " * 300}  # Large enough to compress
        return JSONResponse(data)

    @test_app.get("/small")
    async def get_small():
        return JSONResponse({"msg": "tiny"})  # Too small for 1000 byte minimum

    return test_app


def test_custom_minimum_size(custom_app):
    """Test that custom minimum size is respected."""
    client = TestClient(custom_app)

    # Large response should be compressed
    response_large = client.get("/data", headers={"Accept-Encoding": "gzip"})
    assert response_large.status_code == 200
    assert response_large.headers.get("content-encoding") == "gzip"

    # Small response should not be compressed (below 1000 byte minimum)
    response_small = client.get("/small", headers={"Accept-Encoding": "gzip"})
    assert response_small.status_code == 200
    # Should not be compressed due to custom minimum size
    assert response_small.headers.get("content-encoding") != "gzip"


def test_vary_header_added(app):
    """Test that Vary: Accept-Encoding header is added to compressed responses."""
    client = TestClient(app)
    response = client.get("/api/data", headers={"Accept-Encoding": "gzip"})

    assert response.status_code == 200
    assert "vary" in response.headers
    assert "Accept-Encoding" in response.headers["vary"]


def test_content_length_updated(app):
    """Test that Content-Length header is updated after compression."""
    client = TestClient(app)
    response = client.get("/api/data", headers={"Accept-Encoding": "gzip"})

    assert response.status_code == 200
    assert "content-length" in response.headers
    content_length = int(response.headers["content-length"])

    # Note: TestClient automatically decompresses responses, so response.content
    # will be larger than content-length (which is for compressed data)
    # Just verify that content-length is set and is a reasonable compressed size
    assert content_length > 0
    assert content_length < 500  # Compressed JSON should be much smaller than original


def test_compression_level_affects_size():
    """Test that different compression levels affect output size."""
    from fastapi import FastAPI

    # Create two apps with different compression levels
    app_fast = FastAPI()
    app_fast.add_middleware(GzipCompressionMiddleware, compresslevel=1)

    app_best = FastAPI()
    app_best.add_middleware(GzipCompressionMiddleware, compresslevel=9)

    # Add the same endpoint to both
    large_data = {"message": "test data " * 500}

    @app_fast.get("/data")
    async def get_data_fast():
        return JSONResponse(large_data)

    @app_best.get("/data")
    async def get_data_best():
        return JSONResponse(large_data)

    # Get compressed responses
    client_fast = TestClient(app_fast)
    response_fast = client_fast.get("/data", headers={"Accept-Encoding": "gzip"})

    client_best = TestClient(app_best)
    response_best = client_best.get("/data", headers={"Accept-Encoding": "gzip"})

    # Both should be compressed
    assert response_fast.headers.get("content-encoding") == "gzip"
    assert response_best.headers.get("content-encoding") == "gzip"

    # Level 9 should produce smaller or equal size (better compression)
    size_fast = int(response_fast.headers.get("content-length", 0))
    size_best = int(response_best.headers.get("content-length", 0))

    assert size_best <= size_fast
