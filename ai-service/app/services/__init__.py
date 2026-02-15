"""Business logic services for LAYA AI Service.

This package contains all service layer implementations that encapsulate
business logic, database operations, and external integrations.

Modules:
    coaching_service: Service for RAG-based special needs coaching guidance
"""

from app.services.coaching_service import (
    SAFETY_DISCLAIMER,
    CoachingService,
    CoachingServiceError,
    InvalidChildError,
    NoSourcesFoundError,
)

__all__: list[str] = [
    "CoachingService",
    "CoachingServiceError",
    "InvalidChildError",
    "NoSourcesFoundError",
    "SAFETY_DISCLAIMER",
]
