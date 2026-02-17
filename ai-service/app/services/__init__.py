"""Business logic services for LAYA AI Service.

This package contains all service layer implementations that encapsulate
business logic, database operations, and external integrations.

Modules:
    coaching_service: Service for RAG-based special needs coaching guidance
    activity_service: Service for activity intelligence and recommendations
    analytics_service: Service for business intelligence and analytics
    rbac_service: Service for role-based access control
    audit_service: Service for audit logging and tracking
    notification_service: Service for unauthorized access notifications
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
from app.services.rbac_service import (
    RBACService,
    RBACServiceError,
    RoleNotFoundError,
    UserRoleNotFoundError,
    PermissionDeniedError,
    InvalidAssignmentError,
)
from app.services.audit_service import (
    AuditService,
    AuditServiceError,
    AuditLogNotFoundError,
)
from app.services.notification_service import (
    NotificationService,
    NotificationServiceError,
    NotificationDeliveryError,
    NoRecipientsFoundError,
)

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
    # RBAC
    "RBACService",
    "RBACServiceError",
    "RoleNotFoundError",
    "UserRoleNotFoundError",
    "PermissionDeniedError",
    "InvalidAssignmentError",
    # Audit
    "AuditService",
    "AuditServiceError",
    "AuditLogNotFoundError",
    # Notification
    "NotificationService",
    "NotificationServiceError",
    "NotificationDeliveryError",
    "NoRecipientsFoundError",
]
