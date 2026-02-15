"""SQLAlchemy models for LAYA AI Service.

This package contains all SQLAlchemy ORM model definitions for the AI service
database tables.

Modules:
    base: Base declarative class for all models
    coaching: Models for special needs coaching domain
    analytics: Models for analytics, forecasting, and compliance
"""

from app.models.base import Base
from app.models.coaching import (
    CoachingRecommendation,
    CoachingSession,
    EvidenceSource,
)
from app.models.analytics import (
    AnalyticsMetric,
    ComplianceCheck,
    ComplianceCheckType,
    ComplianceStatus,
    EnrollmentForecast,
    MetricCategory,
)

__all__ = [
    "Base",
    # Coaching models
    "CoachingSession",
    "CoachingRecommendation",
    "EvidenceSource",
    # Analytics models
    "AnalyticsMetric",
    "EnrollmentForecast",
    "ComplianceCheck",
    # Analytics enums
    "MetricCategory",
    "ComplianceStatus",
    "ComplianceCheckType",
]
