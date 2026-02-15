"""SQLAlchemy models for LAYA AI Service.

This package contains all SQLAlchemy ORM model definitions for the AI service
database tables.

Modules:
    base: Base declarative class for all models
    coaching: Models for special needs coaching domain
"""

from app.models.base import Base
from app.models.coaching import (
    CoachingRecommendation,
    CoachingSession,
    EvidenceSource,
)

__all__ = [
    "Base",
    "CoachingSession",
    "CoachingRecommendation",
    "EvidenceSource",
]
