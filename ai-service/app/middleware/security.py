"""Security middleware for LAYA AI Service.

This module provides security-related middleware including CORS configuration
with environment-based origin whitelisting for production security.
"""

from typing import List

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
