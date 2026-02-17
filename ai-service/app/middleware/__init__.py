"""Middleware package for LAYA AI Service."""

from app.middleware.security import get_cors_origins

__all__ = ["get_cors_origins"]
