"""Business logic services for LAYA AI Service.

This package contains all service layer implementations that encapsulate
business logic, database operations, and external integrations.

Modules:
    coaching_service: Service for RAG-based special needs coaching guidance
    activity_service: Service for activity intelligence and recommendations
    analytics_service: Service for business intelligence and analytics
    occupancy_service: Service for real-time occupancy and attendance tracking
"""

from app.services.coaching_service import (
    SAFETY_DISCLAIMER,
    CoachingService,
    CoachingServiceError,
    InvalidChildError,
    NoSourcesFoundError,
)
from app.services.activity_service import ActivityService
from app.services.analytics_service import AnalyticsService
from app.services.occupancy_service import OccupancyService

__all__: list[str] = [
    # Coaching
    "CoachingService",
    "CoachingServiceError",
    "InvalidChildError",
    "NoSourcesFoundError",
    "SAFETY_DISCLAIMER",
    # Activity
    "ActivityService",
    # Analytics
    "AnalyticsService",
    # Occupancy
    "OccupancyService",
]
