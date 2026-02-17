"""Cache headers middleware for static asset optimization.

This middleware adds appropriate Cache-Control headers to responses based on
content type and file patterns to optimize browser caching behavior.
"""

import re
from typing import Callable, Dict, Optional, Pattern

from starlette.datastructures import Headers
from starlette.middleware.base import BaseHTTPMiddleware
from starlette.requests import Request
from starlette.responses import Response


class CacheHeadersMiddleware(BaseHTTPMiddleware):
    """Middleware to add cache control headers to static assets.

    This middleware automatically adds appropriate Cache-Control headers
    to responses based on the requested path and content type. It supports:

    - Immutable assets (versioned files with hash): 1 year cache
    - Static assets (images, fonts, media): 1 month cache
    - Client-side code (JS, CSS): 1 week cache with revalidation
    - API responses: no-cache with revalidation
    - HTML pages: no-cache

    Attributes:
        static_paths: List of path prefixes that should be treated as static
        immutable_pattern: Regex pattern for immutable versioned assets
        cache_rules: Dictionary mapping content types to cache control values
    """

    # Default cache control rules by content type
    DEFAULT_CACHE_RULES: Dict[str, str] = {
        # Immutable versioned assets (1 year)
        "immutable": "public, max-age=31536000, immutable",

        # Images (1 month)
        "image/jpeg": "public, max-age=2592000",
        "image/png": "public, max-age=2592000",
        "image/gif": "public, max-age=2592000",
        "image/webp": "public, max-age=2592000",
        "image/svg+xml": "public, max-age=2592000",
        "image/avif": "public, max-age=2592000",

        # Fonts (1 year, fonts rarely change)
        "font/woff": "public, max-age=31536000, immutable",
        "font/woff2": "public, max-age=31536000, immutable",
        "font/ttf": "public, max-age=31536000, immutable",
        "font/otf": "public, max-age=31536000, immutable",
        "application/font-woff": "public, max-age=31536000, immutable",
        "application/font-woff2": "public, max-age=31536000, immutable",

        # JavaScript and CSS (1 week with revalidation)
        "application/javascript": "public, max-age=604800, must-revalidate",
        "text/javascript": "public, max-age=604800, must-revalidate",
        "text/css": "public, max-age=604800, must-revalidate",

        # Media files (1 month)
        "video/mp4": "public, max-age=2592000",
        "video/webm": "public, max-age=2592000",
        "audio/mpeg": "public, max-age=2592000",
        "audio/ogg": "public, max-age=2592000",

        # Documents (1 day)
        "application/pdf": "public, max-age=86400, must-revalidate",

        # HTML (no cache, always revalidate)
        "text/html": "no-cache, must-revalidate",

        # API responses (no cache)
        "application/json": "no-cache, no-store, must-revalidate",
    }

    def __init__(
        self,
        app,
        static_paths: Optional[list[str]] = None,
        immutable_pattern: Optional[str] = None,
        cache_rules: Optional[Dict[str, str]] = None,
    ):
        """Initialize the cache headers middleware.

        Args:
            app: The ASGI application
            static_paths: List of path prefixes for static files (default: ["/static", "/assets", "/media"])
            immutable_pattern: Regex pattern for immutable files (default: files with hashes like .abc123.js)
            cache_rules: Custom cache control rules by content type (merged with defaults)
        """
        super().__init__(app)

        self.static_paths = static_paths or ["/static", "/assets", "/media", "/uploads"]

        # Pattern for versioned/hashed files (e.g., main.abc123.js, style.abc123.css)
        self.immutable_pattern: Pattern = re.compile(
            immutable_pattern or r"\.[a-f0-9]{8,}\.(js|css|jpg|jpeg|png|gif|webp|woff2?|ttf|otf)$"
        )

        # Merge custom cache rules with defaults
        self.cache_rules = {**self.DEFAULT_CACHE_RULES}
        if cache_rules:
            self.cache_rules.update(cache_rules)

    def _is_static_path(self, path: str) -> bool:
        """Check if the request path is for a static asset.

        Args:
            path: The request path

        Returns:
            bool: True if the path matches a static path prefix
        """
        return any(path.startswith(prefix) for prefix in self.static_paths)

    def _is_immutable_asset(self, path: str) -> bool:
        """Check if the asset is immutable (versioned/hashed).

        Args:
            path: The request path

        Returns:
            bool: True if the path matches the immutable pattern
        """
        return bool(self.immutable_pattern.search(path))

    def _get_cache_control(self, path: str, content_type: str) -> str:
        """Determine the appropriate Cache-Control header value.

        Args:
            path: The request path
            content_type: The response content type

        Returns:
            str: The Cache-Control header value
        """
        # Check if it's an immutable versioned asset
        if self._is_static_path(path) and self._is_immutable_asset(path):
            return self.cache_rules["immutable"]

        # Extract the base content type (remove charset, etc.)
        base_content_type = content_type.split(";")[0].strip()

        # Return the cache rule for this content type, or default for API responses
        return self.cache_rules.get(
            base_content_type,
            self.cache_rules.get("application/json")  # Default to no-cache
        )

    async def dispatch(
        self, request: Request, call_next: Callable
    ) -> Response:
        """Process the request and add cache headers to the response.

        Args:
            request: The incoming request
            call_next: The next middleware/handler in the chain

        Returns:
            Response: The response with cache headers added
        """
        # Process the request
        response = await call_next(request)

        # Get the content type from the response
        content_type = response.headers.get("content-type", "")

        # Only add cache headers if not already set
        if "cache-control" not in response.headers:
            cache_control = self._get_cache_control(request.url.path, content_type)
            response.headers["Cache-Control"] = cache_control

            # Add Vary header for JSON responses (may vary by Accept header)
            if "application/json" in content_type:
                response.headers["Vary"] = "Accept, Authorization"

            # Add ETag support for static assets
            if self._is_static_path(request.url.path) and response.status_code == 200:
                # Generate a simple ETag based on path (weak validator)
                # In production, use a proper ETag generator based on file content/hash
                etag_value = hash(request.url.path)
                response.headers.setdefault("ETag", f'W/"{etag_value}"')

        return response


def create_cache_middleware(
    static_paths: Optional[list[str]] = None,
    immutable_pattern: Optional[str] = None,
    cache_rules: Optional[Dict[str, str]] = None,
) -> type[CacheHeadersMiddleware]:
    """Factory function to create a configured cache middleware.

    Args:
        static_paths: List of path prefixes for static files
        immutable_pattern: Regex pattern for immutable files
        cache_rules: Custom cache control rules by content type

    Returns:
        type[CacheHeadersMiddleware]: Configured middleware class
    """
    class ConfiguredCacheMiddleware(CacheHeadersMiddleware):
        def __init__(self, app):
            super().__init__(app, static_paths, immutable_pattern, cache_rules)

    return ConfiguredCacheMiddleware
