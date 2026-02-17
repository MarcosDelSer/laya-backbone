"""SQLAlchemy models for LAYA AI Service.

This package contains all SQLAlchemy ORM model definitions for the AI service
database tables.

Modules:
    base: Base declarative class for all models
    coaching: Models for special needs coaching domain
    activity: Models for activity intelligence domain
    analytics: Models for analytics, forecasting, and compliance
    communication: Models for parent communication domain
    development_profile: Models for Quebec-aligned developmental tracking
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
from app.models.development_profile import (
    DevelopmentProfile,
    DevelopmentalDomain,
    MonthlySnapshot,
    Observation,
    SkillAssessment,
    SkillStatus,
)
from app.models.mfa import (
    MFABackupCode,
    MFAIPWhitelist,
    MFAMethod,
    MFASettings,
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
    # Development Profile models
    "DevelopmentProfile",
    "DevelopmentalDomain",
    "SkillStatus",
    "SkillAssessment",
    "Observation",
    "MonthlySnapshot",
    # MFA models
    "MFASettings",
    "MFABackupCode",
    "MFAIPWhitelist",
    "MFAMethod",
]
