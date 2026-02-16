"""Middleware package for LAYA AI Service.

This package contains middleware components for request/response processing.
"""

from app.middleware.cache_headers import CacheHeadersMiddleware

__all__ = ["CacheHeadersMiddleware"]
