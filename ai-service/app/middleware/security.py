"""Security middleware for LAYA AI Service.

This module provides security-related middleware including CORS configuration
with environment-based origin whitelisting for production security and XSS
protection headers to prevent cross-site scripting attacks.
"""

from typing import Callable, Dict, List

from fastapi import Request, Response

from app.config import settings


def get_cors_origins() -> List[str]:
    """Get allowed CORS origins based on environment.

    In production, only specific whitelisted origins are allowed.
    In development, localhost origins are permitted for easier testing.

    Returns:
        List[str]: List of allowed origin URLs
    """
    # Get CORS origins from settings (can be comma-separated in env var)
    if settings.cors_origins:
        # Parse comma-separated origins from environment variable
        origins = [origin.strip() for origin in settings.cors_origins.split(",")]
        return origins

    # Development defaults if no CORS_ORIGINS specified
    return [
        "http://localhost:3000",  # Parent portal
        "http://localhost:8080",  # Gibbon UI
        "http://localhost:8000",  # AI service itself
    ]


def get_xss_protection_headers() -> Dict[str, str]:
    """Get XSS protection headers for HTTP responses.

    These headers provide defense-in-depth protection against XSS attacks:
    - Content-Security-Policy: Restricts resource loading to prevent XSS
    - X-Content-Type-Options: Prevents MIME-type sniffing
    - X-Frame-Options: Prevents clickjacking attacks

    Returns:
        Dict[str, str]: Dictionary of security headers
    """
    return {
        # Content Security Policy - restricts what resources can be loaded
        # default-src 'self': Only allow resources from same origin
        # script-src 'self' 'unsafe-inline': Allow scripts from same origin and inline scripts
        #   (unsafe-inline needed for some frameworks, consider removing in production)
        # style-src 'self' 'unsafe-inline': Allow styles from same origin and inline styles
        # img-src 'self' data: https:; Allow images from same origin, data URIs, and HTTPS
        # font-src 'self' data:; Allow fonts from same origin and data URIs
        # connect-src 'self': Only allow AJAX/fetch to same origin
        # frame-ancestors 'none': Prevent framing (redundant with X-Frame-Options but more flexible)
        "Content-Security-Policy": (
            "default-src 'self'; "
            "script-src 'self' 'unsafe-inline'; "
            "style-src 'self' 'unsafe-inline'; "
            "img-src 'self' data: https:; "
            "font-src 'self' data:; "
            "connect-src 'self'; "
            "frame-ancestors 'none'"
        ),
        # Prevent browsers from MIME-sniffing responses away from declared content-type
        # This prevents browsers from interpreting files as a different MIME type than declared
        "X-Content-Type-Options": "nosniff",
        # Prevent the page from being framed to protect against clickjacking
        "X-Frame-Options": "DENY",
    }


def get_xss_protection_middleware() -> Callable:
    """Get XSS protection middleware for the FastAPI application.

    This middleware adds security headers to all HTTP responses to protect
    against cross-site scripting (XSS) attacks, clickjacking, and MIME-type
    sniffing vulnerabilities.

    Returns:
        Callable: Middleware function for FastAPI application
    """
    async def xss_protection_middleware(request: Request, call_next: Callable) -> Response:
        """Middleware to add XSS protection headers to responses.

        This middleware wraps all responses with security headers that provide
        defense-in-depth protection against various web vulnerabilities.

        Args:
            request: The incoming request
            call_next: The next middleware/handler in the chain

        Returns:
            Response: The response with added security headers
        """
        # Process the request and get the response
        response = await call_next(request)

        # Add XSS protection headers to the response
        headers = get_xss_protection_headers()
        for header_name, header_value in headers.items():
            response.headers[header_name] = header_value

        return response

    return xss_protection_middleware
