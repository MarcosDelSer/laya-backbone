"""Gzip compression middleware for API response optimization.

This middleware adds gzip compression to responses to reduce bandwidth usage
and improve response times. It intelligently compresses responses based on
content type, size, and client capabilities.
"""

import gzip
import io
from typing import Callable, Optional, Set

from starlette.datastructures import Headers, MutableHeaders
from starlette.middleware.base import BaseHTTPMiddleware
from starlette.requests import Request
from starlette.responses import Response, StreamingResponse
from starlette.types import ASGIApp, Message, Receive, Scope, Send


class GzipCompressionMiddleware:
    """ASGI middleware for gzip compression of responses.

    This middleware compresses HTTP responses using gzip when the client
    supports it (Accept-Encoding: gzip header). It provides:

    - Automatic compression for text-based content types
    - Configurable minimum response size threshold
    - Configurable compression level (1-9)
    - Skip compression for already-compressed content
    - Skip compression for streaming responses (optional)
    - Proper Content-Encoding and Vary headers

    Attributes:
        app: The ASGI application
        minimum_size: Minimum response size in bytes to compress (default: 500)
        compresslevel: Gzip compression level 1-9 (default: 6, balanced)
        compressible_types: Set of content types that should be compressed
        excluded_types: Set of content types that should never be compressed
    """

    # Default compressible content types (text-based formats)
    DEFAULT_COMPRESSIBLE_TYPES: Set[str] = {
        "text/html",
        "text/css",
        "text/plain",
        "text/xml",
        "text/csv",
        "text/javascript",
        "application/json",
        "application/javascript",
        "application/xml",
        "application/x-javascript",
        "application/xhtml+xml",
        "application/rss+xml",
        "application/atom+xml",
        "application/ld+json",
        "application/geo+json",
        "application/manifest+json",
        "application/vnd.api+json",
    }

    # Content types that should never be compressed (already compressed)
    DEFAULT_EXCLUDED_TYPES: Set[str] = {
        "image/png",
        "image/jpg",
        "image/jpeg",
        "image/gif",
        "image/webp",
        "image/avif",
        "image/bmp",
        "video/mp4",
        "video/mpeg",
        "video/webm",
        "audio/mpeg",
        "audio/ogg",
        "audio/wav",
        "application/zip",
        "application/x-gzip",
        "application/gzip",
        "application/x-bzip2",
        "application/x-7z-compressed",
        "application/x-rar-compressed",
        "application/pdf",
        "application/octet-stream",
        "text/event-stream",  # Server-sent events should not be compressed
    }

    def __init__(
        self,
        app: ASGIApp,
        minimum_size: int = 500,
        compresslevel: int = 6,
        compressible_types: Optional[Set[str]] = None,
        excluded_types: Optional[Set[str]] = None,
    ):
        """Initialize the gzip compression middleware.

        Args:
            app: The ASGI application
            minimum_size: Minimum response size in bytes to compress (default: 500)
                         Responses smaller than this won't be compressed
            compresslevel: Gzip compression level 1-9 (default: 6)
                          1 = fastest/least compression, 9 = slowest/most compression
                          6 provides good balance between speed and compression ratio
            compressible_types: Set of content types to compress (merged with defaults)
            excluded_types: Set of content types to never compress (merged with defaults)
        """
        self.app = app
        self.minimum_size = minimum_size
        self.compresslevel = max(1, min(9, compresslevel))  # Clamp to 1-9

        # Merge custom types with defaults
        self.compressible_types = self.DEFAULT_COMPRESSIBLE_TYPES.copy()
        if compressible_types:
            self.compressible_types.update(compressible_types)

        self.excluded_types = self.DEFAULT_EXCLUDED_TYPES.copy()
        if excluded_types:
            self.excluded_types.update(excluded_types)

    async def __call__(self, scope: Scope, receive: Receive, send: Send) -> None:
        """ASGI application entrypoint.

        Args:
            scope: ASGI scope dictionary
            receive: ASGI receive callable
            send: ASGI send callable
        """
        if scope["type"] != "http":
            await self.app(scope, receive, send)
            return

        headers = Headers(scope=scope)

        # Check if client accepts gzip encoding
        if "gzip" not in headers.get("Accept-Encoding", ""):
            await self.app(scope, receive, send)
            return

        # Use the responder to handle compression
        responder = GzipResponder(
            self.app,
            self.minimum_size,
            self.compresslevel,
            self.compressible_types,
            self.excluded_types,
        )
        await responder(scope, receive, send)


class GzipResponder:
    """Handles gzip compression of HTTP responses.

    This class wraps the ASGI send callable to intercept response messages
    and apply gzip compression when appropriate.
    """

    def __init__(
        self,
        app: ASGIApp,
        minimum_size: int,
        compresslevel: int,
        compressible_types: Set[str],
        excluded_types: Set[str],
    ):
        """Initialize the gzip responder.

        Args:
            app: The ASGI application
            minimum_size: Minimum response size to compress
            compresslevel: Gzip compression level (1-9)
            compressible_types: Set of compressible content types
            excluded_types: Set of excluded content types
        """
        self.app = app
        self.minimum_size = minimum_size
        self.compresslevel = compresslevel
        self.compressible_types = compressible_types
        self.excluded_types = excluded_types

        self.send: Send = None  # type: ignore
        self.initial_message: Message = {}
        self.started = False
        self.gzip_buffer = io.BytesIO()
        self.gzip_file: Optional[gzip.GzipFile] = None
        self.should_compress = True

    async def __call__(self, scope: Scope, receive: Receive, send: Send) -> None:
        """Process the ASGI application with compression.

        Args:
            scope: ASGI scope dictionary
            receive: ASGI receive callable
            send: ASGI send callable
        """
        self.send = send
        await self.app(scope, receive, self.send_with_compression)

    def _should_compress_content_type(self, content_type: str) -> bool:
        """Determine if content type should be compressed.

        Args:
            content_type: The response content type header value

        Returns:
            bool: True if content type should be compressed
        """
        # Extract base content type (remove charset, etc.)
        base_content_type = content_type.split(";")[0].strip().lower()

        # Don't compress excluded types
        if base_content_type in self.excluded_types:
            return False

        # Compress if in compressible types
        if base_content_type in self.compressible_types:
            return True

        # Default to compressing text/* types
        if base_content_type.startswith("text/"):
            return True

        # Default to not compressing other types
        return False

    async def send_with_compression(self, message: Message) -> None:
        """Intercept and potentially compress response messages.

        Args:
            message: ASGI message dictionary
        """
        message_type = message["type"]

        if message_type == "http.response.start":
            # Store the initial message to modify headers later
            self.initial_message = message
            headers = Headers(raw=self.initial_message["headers"])

            # Don't compress if Content-Encoding already set
            if "content-encoding" in headers:
                self.should_compress = False
                return

            # Check if content type should be compressed
            content_type = headers.get("content-type", "")
            if not self._should_compress_content_type(content_type):
                self.should_compress = False
                return

            # Initialize gzip compressor
            self.gzip_file = gzip.GzipFile(
                mode="wb",
                fileobj=self.gzip_buffer,
                compresslevel=self.compresslevel,
            )

        elif message_type == "http.response.body":
            if not self.should_compress:
                # Send without compression
                if not self.started:
                    self.started = True
                    await self.send(self.initial_message)
                await self.send(message)
                return

            body = message.get("body", b"")
            more_body = message.get("more_body", False)

            # Compress the body
            if self.gzip_file:
                self.gzip_file.write(body)

                if not more_body:
                    # Final chunk - close the gzip file
                    self.gzip_file.close()

                compressed_body = self.gzip_buffer.getvalue()

                if not self.started:
                    self.started = True

                    # Check if compression is worthwhile
                    original_size = len(body)
                    compressed_size = len(compressed_body)

                    if original_size < self.minimum_size or compressed_size >= original_size:
                        # Not worth compressing - send original
                        await self.send(self.initial_message)
                        await self.send(message)
                        return

                    # Update headers for compressed response
                    headers = MutableHeaders(raw=self.initial_message["headers"])
                    headers["Content-Encoding"] = "gzip"
                    headers["Content-Length"] = str(compressed_size)
                    headers.add_vary_header("Accept-Encoding")

                    # Send compressed response
                    await self.send(self.initial_message)
                    await self.send({
                        "type": "http.response.body",
                        "body": compressed_body,
                        "more_body": False,
                    })
                else:
                    # Streaming response - send compressed chunk
                    self.gzip_buffer.seek(0)
                    chunk = self.gzip_buffer.read()
                    self.gzip_buffer.seek(0)
                    self.gzip_buffer.truncate()

                    await self.send({
                        "type": "http.response.body",
                        "body": chunk,
                        "more_body": more_body,
                    })


def create_compression_middleware(
    minimum_size: int = 500,
    compresslevel: int = 6,
    compressible_types: Optional[Set[str]] = None,
    excluded_types: Optional[Set[str]] = None,
) -> type[GzipCompressionMiddleware]:
    """Factory function to create a configured compression middleware.

    This factory allows you to create a middleware class with custom configuration
    that can be added to a FastAPI/Starlette application.

    Args:
        minimum_size: Minimum response size in bytes to compress (default: 500)
        compresslevel: Gzip compression level 1-9 (default: 6)
        compressible_types: Set of content types to compress (merged with defaults)
        excluded_types: Set of content types to never compress (merged with defaults)

    Returns:
        type[GzipCompressionMiddleware]: Configured middleware class

    Example:
        ```python
        from fastapi import FastAPI
        from app.middleware.compression import create_compression_middleware

        app = FastAPI()

        # Create custom compression middleware with level 9 compression
        CompressionMiddleware = create_compression_middleware(
            compresslevel=9,
            minimum_size=1000
        )

        app.add_middleware(CompressionMiddleware)
        ```
    """
    class ConfiguredCompressionMiddleware(GzipCompressionMiddleware):
        def __init__(self, app: ASGIApp):
            super().__init__(
                app,
                minimum_size=minimum_size,
                compresslevel=compresslevel,
                compressible_types=compressible_types,
                excluded_types=excluded_types,
            )

    return ConfiguredCompressionMiddleware
