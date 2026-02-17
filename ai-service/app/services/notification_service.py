"""Service for unauthorized access notifications.

Provides notification functionality for alerting directors and administrators
when unauthorized access attempts occur. Supports multiple notification
channels including in-app notifications, email, and webhooks.
"""

from datetime import datetime
from enum import Enum
from typing import Optional
from uuid import UUID

from sqlalchemy import and_, desc, select
from sqlalchemy.ext.asyncio import AsyncSession

from app.models.rbac import AuditLog, Role, UserRole
from app.schemas.rbac import AuditAction, RoleType


class NotificationChannel(str, Enum):
    """Available notification channels.

    Attributes:
        IN_APP: In-application notification
        EMAIL: Email notification
        WEBHOOK: Webhook notification for external integrations
    """

    IN_APP = "in_app"
    EMAIL = "email"
    WEBHOOK = "webhook"


class NotificationPriority(str, Enum):
    """Notification priority levels.

    Attributes:
        LOW: Low priority notification
        MEDIUM: Medium priority notification
        HIGH: High priority notification
        CRITICAL: Critical priority notification requiring immediate attention
    """

    LOW = "low"
    MEDIUM = "medium"
    HIGH = "high"
    CRITICAL = "critical"


class NotificationServiceError(Exception):
    """Base exception for notification service errors."""

    pass


class NotificationDeliveryError(NotificationServiceError):
    """Raised when notification delivery fails."""

    pass


class NoRecipientsFoundError(NotificationServiceError):
    """Raised when no notification recipients are found."""

    pass


class NotificationService:
    """Service for managing and sending unauthorized access notifications.

    This service handles the creation, delivery, and management of notifications
    related to unauthorized access attempts. It integrates with the audit system
    to detect access denied events and alerts directors accordingly.

    Attributes:
        db: Async database session for database operations
    """

    def __init__(self, db: AsyncSession) -> None:
        """Initialize the notification service.

        Args:
            db: Async database session
        """
        self.db = db
        self._notification_queue: list[dict] = []

    # =========================================================================
    # Core Notification Methods
    # =========================================================================

    async def notify_unauthorized_access(
        self,
        user_id: UUID,
        resource_type: str,
        resource_id: Optional[UUID] = None,
        attempted_action: Optional[str] = None,
        details: Optional[dict] = None,
        ip_address: Optional[str] = None,
        organization_id: Optional[UUID] = None,
    ) -> dict:
        """Send notification to directors about unauthorized access attempt.

        Creates and dispatches a notification to all directors in the organization
        when an unauthorized access attempt is detected.

        Args:
            user_id: ID of the user who attempted unauthorized access
            resource_type: Type of resource that was attempted to be accessed
            resource_id: Optional ID of the specific resource
            attempted_action: Optional action that was attempted
            details: Optional additional details about the attempt
            ip_address: Optional IP address of the request
            organization_id: Optional organization to scope the notification

        Returns:
            Dictionary with notification status and details

        Raises:
            NoRecipientsFoundError: When no directors are found to notify
            NotificationDeliveryError: When notification delivery fails
        """
        # Get all directors to notify
        directors = await self._get_directors(organization_id)

        if not directors:
            raise NoRecipientsFoundError(
                "No directors found to receive unauthorized access notification"
            )

        # Build notification content
        notification = self._build_unauthorized_access_notification(
            user_id=user_id,
            resource_type=resource_type,
            resource_id=resource_id,
            attempted_action=attempted_action,
            details=details,
            ip_address=ip_address,
        )

        # Dispatch to all directors
        results = await self._dispatch_notifications(
            recipients=[d["user_id"] for d in directors],
            notification=notification,
        )

        return {
            "status": "sent",
            "notification_id": notification["id"],
            "recipients_count": len(directors),
            "timestamp": datetime.utcnow().isoformat(),
            "delivery_results": results,
        }

    async def notify_multiple_failed_attempts(
        self,
        user_id: UUID,
        attempt_count: int,
        time_window_minutes: int = 15,
        details: Optional[dict] = None,
        organization_id: Optional[UUID] = None,
    ) -> dict:
        """Send notification about multiple failed access attempts.

        Creates a high-priority notification when a user has multiple
        failed access attempts within a specified time window.

        Args:
            user_id: ID of the user with failed attempts
            attempt_count: Number of failed attempts
            time_window_minutes: Time window for the attempts
            details: Optional additional details
            organization_id: Optional organization scope

        Returns:
            Dictionary with notification status and details

        Raises:
            NoRecipientsFoundError: When no directors are found
            NotificationDeliveryError: When delivery fails
        """
        directors = await self._get_directors(organization_id)

        if not directors:
            raise NoRecipientsFoundError(
                "No directors found for multiple failed attempts notification"
            )

        notification = self._build_multiple_attempts_notification(
            user_id=user_id,
            attempt_count=attempt_count,
            time_window_minutes=time_window_minutes,
            details=details,
        )

        results = await self._dispatch_notifications(
            recipients=[d["user_id"] for d in directors],
            notification=notification,
        )

        return {
            "status": "sent",
            "notification_id": notification["id"],
            "recipients_count": len(directors),
            "priority": NotificationPriority.CRITICAL.value,
            "timestamp": datetime.utcnow().isoformat(),
            "delivery_results": results,
        }

    async def notify_sensitive_data_access(
        self,
        user_id: UUID,
        resource_type: str,
        sensitivity_level: str,
        details: Optional[dict] = None,
        organization_id: Optional[UUID] = None,
    ) -> dict:
        """Send notification about access to sensitive data.

        Creates a notification when a user accesses highly sensitive
        data, even if the access is authorized, for audit purposes.

        Args:
            user_id: ID of the user accessing sensitive data
            resource_type: Type of sensitive resource accessed
            sensitivity_level: Level of data sensitivity
            details: Optional additional details
            organization_id: Optional organization scope

        Returns:
            Dictionary with notification status and details

        Raises:
            NoRecipientsFoundError: When no directors are found
            NotificationDeliveryError: When delivery fails
        """
        directors = await self._get_directors(organization_id)

        if not directors:
            raise NoRecipientsFoundError(
                "No directors found for sensitive data access notification"
            )

        notification = self._build_sensitive_access_notification(
            user_id=user_id,
            resource_type=resource_type,
            sensitivity_level=sensitivity_level,
            details=details,
        )

        results = await self._dispatch_notifications(
            recipients=[d["user_id"] for d in directors],
            notification=notification,
        )

        return {
            "status": "sent",
            "notification_id": notification["id"],
            "recipients_count": len(directors),
            "timestamp": datetime.utcnow().isoformat(),
            "delivery_results": results,
        }

    # =========================================================================
    # Access Attempt Analysis Methods
    # =========================================================================

    async def get_recent_unauthorized_attempts(
        self,
        user_id: Optional[UUID] = None,
        organization_id: Optional[UUID] = None,
        hours: int = 24,
        limit: int = 100,
    ) -> list[dict]:
        """Get recent unauthorized access attempts.

        Queries the audit log for recent access denied events.

        Args:
            user_id: Optional filter by specific user
            organization_id: Optional filter by organization
            hours: Number of hours to look back
            limit: Maximum number of attempts to return

        Returns:
            List of unauthorized access attempt records
        """
        from datetime import timedelta

        cutoff_time = datetime.utcnow() - timedelta(hours=hours)

        query = select(AuditLog).where(
            and_(
                AuditLog.action == AuditAction.ACCESS_DENIED.value,
                AuditLog.created_at >= cutoff_time,
            )
        )

        if user_id:
            query = query.where(AuditLog.user_id == user_id)

        query = query.order_by(desc(AuditLog.created_at)).limit(limit)

        result = await self.db.execute(query)
        audit_logs = result.scalars().all()

        return [
            {
                "id": str(log.id),
                "user_id": str(log.user_id),
                "resource_type": log.resource_type,
                "resource_id": str(log.resource_id) if log.resource_id else None,
                "details": log.details,
                "ip_address": log.ip_address,
                "timestamp": log.created_at.isoformat(),
            }
            for log in audit_logs
        ]

    async def count_failed_attempts(
        self,
        user_id: UUID,
        minutes: int = 15,
    ) -> int:
        """Count failed access attempts for a user within a time window.

        Args:
            user_id: ID of the user to check
            minutes: Time window in minutes

        Returns:
            Number of failed attempts
        """
        from datetime import timedelta
        from sqlalchemy import func

        cutoff_time = datetime.utcnow() - timedelta(minutes=minutes)

        query = (
            select(func.count())
            .select_from(AuditLog)
            .where(
                and_(
                    AuditLog.user_id == user_id,
                    AuditLog.action == AuditAction.ACCESS_DENIED.value,
                    AuditLog.created_at >= cutoff_time,
                )
            )
        )

        result = await self.db.execute(query)
        return result.scalar() or 0

    async def check_and_notify_threshold(
        self,
        user_id: UUID,
        threshold: int = 5,
        time_window_minutes: int = 15,
        organization_id: Optional[UUID] = None,
    ) -> Optional[dict]:
        """Check if failed attempts exceed threshold and notify if so.

        Automatically checks if a user has exceeded the failed attempt
        threshold and sends a notification if so.

        Args:
            user_id: ID of the user to check
            threshold: Number of attempts that trigger notification
            time_window_minutes: Time window for counting attempts
            organization_id: Optional organization scope

        Returns:
            Notification result if threshold exceeded, None otherwise
        """
        attempt_count = await self.count_failed_attempts(
            user_id=user_id,
            minutes=time_window_minutes,
        )

        if attempt_count >= threshold:
            return await self.notify_multiple_failed_attempts(
                user_id=user_id,
                attempt_count=attempt_count,
                time_window_minutes=time_window_minutes,
                details={"threshold": threshold},
                organization_id=organization_id,
            )

        return None

    # =========================================================================
    # Private Helper Methods
    # =========================================================================

    async def _get_directors(
        self,
        organization_id: Optional[UUID] = None,
    ) -> list[dict]:
        """Get all directors to notify.

        Retrieves all users with the director role, optionally filtered
        by organization.

        Args:
            organization_id: Optional organization filter

        Returns:
            List of director user dictionaries
        """
        # Find the director role
        role_query = select(Role).where(Role.name == RoleType.DIRECTOR.value)
        role_result = await self.db.execute(role_query)
        director_role = role_result.scalar_one_or_none()

        if not director_role:
            return []

        # Get all active user-role assignments for directors
        query = select(UserRole).where(
            and_(
                UserRole.role_id == director_role.id,
                UserRole.is_active == True,  # noqa: E712
            )
        )

        if organization_id:
            query = query.where(UserRole.organization_id == organization_id)

        result = await self.db.execute(query)
        user_roles = result.scalars().all()

        return [
            {
                "user_id": str(ur.user_id),
                "organization_id": str(ur.organization_id) if ur.organization_id else None,
            }
            for ur in user_roles
        ]

    def _build_unauthorized_access_notification(
        self,
        user_id: UUID,
        resource_type: str,
        resource_id: Optional[UUID] = None,
        attempted_action: Optional[str] = None,
        details: Optional[dict] = None,
        ip_address: Optional[str] = None,
    ) -> dict:
        """Build notification content for unauthorized access attempt.

        Args:
            user_id: ID of the user who attempted unauthorized access
            resource_type: Type of resource attempted to be accessed
            resource_id: Optional ID of the specific resource
            attempted_action: Optional action that was attempted
            details: Optional additional details
            ip_address: Optional IP address

        Returns:
            Notification dictionary
        """
        import uuid

        notification_id = str(uuid.uuid4())

        title = "Unauthorized Access Attempt Detected"
        message = (
            f"A user (ID: {user_id}) attempted to access a {resource_type} "
            f"resource without proper authorization."
        )

        if attempted_action:
            message += f" Attempted action: {attempted_action}."

        if ip_address:
            message += f" Request IP: {ip_address}."

        return {
            "id": notification_id,
            "type": "unauthorized_access",
            "priority": NotificationPriority.HIGH.value,
            "title": title,
            "message": message,
            "data": {
                "user_id": str(user_id),
                "resource_type": resource_type,
                "resource_id": str(resource_id) if resource_id else None,
                "attempted_action": attempted_action,
                "ip_address": ip_address,
                "details": details,
            },
            "created_at": datetime.utcnow().isoformat(),
            "channels": [NotificationChannel.IN_APP.value],
        }

    def _build_multiple_attempts_notification(
        self,
        user_id: UUID,
        attempt_count: int,
        time_window_minutes: int,
        details: Optional[dict] = None,
    ) -> dict:
        """Build notification for multiple failed access attempts.

        Args:
            user_id: ID of the user with failed attempts
            attempt_count: Number of failed attempts
            time_window_minutes: Time window for the attempts
            details: Optional additional details

        Returns:
            Notification dictionary
        """
        import uuid

        notification_id = str(uuid.uuid4())

        title = "⚠️ Multiple Failed Access Attempts Detected"
        message = (
            f"User (ID: {user_id}) has made {attempt_count} failed access "
            f"attempts in the last {time_window_minutes} minutes. "
            f"This may indicate a potential security concern."
        )

        return {
            "id": notification_id,
            "type": "multiple_failed_attempts",
            "priority": NotificationPriority.CRITICAL.value,
            "title": title,
            "message": message,
            "data": {
                "user_id": str(user_id),
                "attempt_count": attempt_count,
                "time_window_minutes": time_window_minutes,
                "details": details,
            },
            "created_at": datetime.utcnow().isoformat(),
            "channels": [
                NotificationChannel.IN_APP.value,
                NotificationChannel.EMAIL.value,
            ],
        }

    def _build_sensitive_access_notification(
        self,
        user_id: UUID,
        resource_type: str,
        sensitivity_level: str,
        details: Optional[dict] = None,
    ) -> dict:
        """Build notification for sensitive data access.

        Args:
            user_id: ID of the user accessing data
            resource_type: Type of sensitive resource
            sensitivity_level: Level of data sensitivity
            details: Optional additional details

        Returns:
            Notification dictionary
        """
        import uuid

        notification_id = str(uuid.uuid4())

        title = "Sensitive Data Access Alert"
        message = (
            f"User (ID: {user_id}) accessed {resource_type} data "
            f"with sensitivity level: {sensitivity_level}."
        )

        priority = NotificationPriority.MEDIUM.value
        if sensitivity_level in ["high", "critical"]:
            priority = NotificationPriority.HIGH.value

        return {
            "id": notification_id,
            "type": "sensitive_data_access",
            "priority": priority,
            "title": title,
            "message": message,
            "data": {
                "user_id": str(user_id),
                "resource_type": resource_type,
                "sensitivity_level": sensitivity_level,
                "details": details,
            },
            "created_at": datetime.utcnow().isoformat(),
            "channels": [NotificationChannel.IN_APP.value],
        }

    async def _dispatch_notifications(
        self,
        recipients: list[str],
        notification: dict,
    ) -> list[dict]:
        """Dispatch notifications to recipients via configured channels.

        This is a placeholder implementation that would be extended to
        integrate with actual notification infrastructure (push notifications,
        email service, webhook endpoints, etc.).

        Args:
            recipients: List of recipient user IDs
            notification: Notification content dictionary

        Returns:
            List of delivery result dictionaries
        """
        results = []

        for recipient_id in recipients:
            # In production, this would dispatch to actual notification services
            # For now, we simulate successful delivery
            result = {
                "recipient_id": recipient_id,
                "status": "delivered",
                "channels": notification.get("channels", []),
                "delivered_at": datetime.utcnow().isoformat(),
            }
            results.append(result)

            # Add to internal queue for testing/debugging
            self._notification_queue.append({
                "recipient_id": recipient_id,
                "notification": notification,
                "delivered_at": datetime.utcnow(),
            })

        return results

    def get_notification_queue(self) -> list[dict]:
        """Get the internal notification queue.

        Useful for testing and debugging purposes.

        Returns:
            List of queued notifications
        """
        return self._notification_queue.copy()

    def clear_notification_queue(self) -> None:
        """Clear the internal notification queue.

        Useful for testing purposes.
        """
        self._notification_queue.clear()
