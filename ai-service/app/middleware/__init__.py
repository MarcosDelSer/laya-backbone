"""Middleware package for LAYA AI Service."""

from app.middleware.error_handler import error_handler_middleware

__all__ = ["error_handler_middleware"]
