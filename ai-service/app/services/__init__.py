"""Business logic services for LAYA AI Service.

This package contains all service layer implementations that encapsulate
business logic, database operations, and external integrations.

Modules:
    coaching_service: Service for RAG-based special needs coaching guidance
    activity_service: Service for activity intelligence and recommendations
    analytics_service: Service for business intelligence and analytics
    mfa_service: Service for multi-factor authentication using TOTP
"""

from app.services.activity_service import ActivityService
from app.services.analytics_service import AnalyticsService
from app.services.coaching_service import (
    SAFETY_DISCLAIMER,
    CoachingService,
    CoachingServiceError,
    InvalidChildError,
    NoSourcesFoundError,
)
from app.services.mfa_service import (
    InvalidCodeError,
    MFAAlreadyEnabledError,
    MFALockoutError,
    MFANotEnabledError,
    MFAService,
    MFAServiceError,
)

__all__: list[str] = [
    # Activity
    "ActivityService",
    # Analytics
    "AnalyticsService",
    # Coaching
    "CoachingService",
    "CoachingServiceError",
    "InvalidChildError",
    "NoSourcesFoundError",
    "SAFETY_DISCLAIMER",
    # MFA
    "MFAService",
    "MFAServiceError",
    "MFANotEnabledError",
    "MFAAlreadyEnabledError",
    "MFALockoutError",
    "InvalidCodeError",
]
