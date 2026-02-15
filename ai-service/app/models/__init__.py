"""SQLAlchemy models for LAYA AI Service.

This package contains all SQLAlchemy ORM model definitions for the AI service
database tables.

Modules:
    base: Base declarative class for all models
    coaching: Models for special needs coaching domain
    activity: Models for activity intelligence domain
    analytics: Models for analytics, forecasting, and compliance
    communication: Models for parent communication domain
"""

from app.models.base import Base
from app.models.coaching import (
    CoachingRecommendation,
    CoachingSession,
    EvidenceSource,
)
from app.models.activity import (
    Activity,
    ActivityDifficulty,
    ActivityParticipation,
    ActivityRecommendation,
    ActivityType,
)
from app.models.analytics import (
    AnalyticsMetric,
    ComplianceCheck,
    ComplianceCheckType,
    ComplianceStatus,
    EnrollmentForecast,
    MetricCategory,
)
from app.models.communication import (
    CommunicationPreference,
    HomeActivity,
    ParentReport,
)

__all__ = [
    "Base",
    # Coaching models
    "CoachingSession",
    "CoachingRecommendation",
    "EvidenceSource",
    # Activity models
    "Activity",
    "ActivityType",
    "ActivityDifficulty",
    "ActivityRecommendation",
    "ActivityParticipation",
    # Analytics models
    "AnalyticsMetric",
    "EnrollmentForecast",
    "ComplianceCheck",
    "MetricCategory",
    "ComplianceStatus",
    "ComplianceCheckType",
    # Communication models
    "ParentReport",
    "HomeActivity",
    "CommunicationPreference",
]
