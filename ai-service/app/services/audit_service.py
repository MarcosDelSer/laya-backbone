"""Service for audit logging of all access and modifications.

Provides comprehensive audit trail functionality for tracking actor, action,
and resource information across the LAYA platform. Supports filtering,
querying, and retention management for compliance requirements.
"""

from datetime import datetime
from typing import Optional
from uuid import UUID

from sqlalchemy import and_, desc, select
from sqlalchemy.ext.asyncio import AsyncSession

from app.models.rbac import AuditLog
from app.schemas.rbac import (
    AuditAction,
    AuditLogFilter,
    AuditLogRequest,
    AuditLogResponse,
)


class AuditServiceError(Exception):
    """Base exception for audit service errors."""

    pass


class AuditLogNotFoundError(AuditServiceError):
    """Raised when an audit log entry is not found."""

    pass


class AuditService:
    """Service for managing audit logs.

    This service provides methods for logging all access and modifications
    with actor/action/resource tracking. It supports querying, filtering,
    and compliance-related audit trail requirements.

    Attributes:
        db: Async database session for database operations
    """

    def __init__(self, db: AsyncSession) -> None:
        """Initialize the audit service.

        Args:
            db: Async database session
        """
        self.db = db

    # =========================================================================
    # Audit Logging Methods
    # =========================================================================

    async def log(
        self,
        user_id: UUID,
        action: str,
        resource_type: str,
        resource_id: Optional[UUID] = None,
        details: Optional[dict] = None,
        ip_address: Optional[str] = None,
        user_agent: Optional[str] = None,
    ) -> AuditLogResponse:
        """Log an audit event.

        Creates an audit log entry for tracking access and modifications.

        Args:
            user_id: ID of the user who performed the action
            action: The action performed (e.g., 'role_assigned', 'data_modified')
            resource_type: Type of resource affected (e.g., 'user', 'child', 'report')
            resource_id: Optional ID of the affected resource
            details: Optional JSON details about the action
            ip_address: Optional IP address of the request
            user_agent: Optional user agent string of the request

        Returns:
            AuditLogResponse with the created audit log entry
        """
        audit_log = AuditLog(
            user_id=user_id,
            action=action,
            resource_type=resource_type,
            resource_id=resource_id,
            details=details,
            ip_address=ip_address,
            user_agent=user_agent,
        )
        self.db.add(audit_log)
        await self.db.commit()
        await self.db.refresh(audit_log)

        return self._build_audit_log_response(audit_log)

    async def log_access_granted(
        self,
        user_id: UUID,
        resource_type: str,
        resource_id: Optional[UUID] = None,
        details: Optional[dict] = None,
        ip_address: Optional[str] = None,
        user_agent: Optional[str] = None,
    ) -> AuditLogResponse:
        """Log an access granted event.

        Args:
            user_id: ID of the user who was granted access
            resource_type: Type of resource accessed
            resource_id: Optional ID of the resource
            details: Optional additional details
            ip_address: Optional IP address
            user_agent: Optional user agent

        Returns:
            AuditLogResponse with the created audit log entry
        """
        return await self.log(
            user_id=user_id,
            action=AuditAction.ACCESS_GRANTED.value,
            resource_type=resource_type,
            resource_id=resource_id,
            details=details,
            ip_address=ip_address,
            user_agent=user_agent,
        )

    async def log_access_denied(
        self,
        user_id: UUID,
        resource_type: str,
        resource_id: Optional[UUID] = None,
        details: Optional[dict] = None,
        ip_address: Optional[str] = None,
        user_agent: Optional[str] = None,
    ) -> AuditLogResponse:
        """Log an access denied event.

        Args:
            user_id: ID of the user who was denied access
            resource_type: Type of resource denied
            resource_id: Optional ID of the resource
            details: Optional additional details
            ip_address: Optional IP address
            user_agent: Optional user agent

        Returns:
            AuditLogResponse with the created audit log entry
        """
        return await self.log(
            user_id=user_id,
            action=AuditAction.ACCESS_DENIED.value,
            resource_type=resource_type,
            resource_id=resource_id,
            details=details,
            ip_address=ip_address,
            user_agent=user_agent,
        )

    async def log_data_modified(
        self,
        user_id: UUID,
        resource_type: str,
        resource_id: Optional[UUID] = None,
        details: Optional[dict] = None,
        ip_address: Optional[str] = None,
        user_agent: Optional[str] = None,
    ) -> AuditLogResponse:
        """Log a data modification event.

        Args:
            user_id: ID of the user who modified the data
            resource_type: Type of resource modified
            resource_id: Optional ID of the resource
            details: Optional additional details (e.g., changed fields)
            ip_address: Optional IP address
            user_agent: Optional user agent

        Returns:
            AuditLogResponse with the created audit log entry
        """
        return await self.log(
            user_id=user_id,
            action=AuditAction.DATA_MODIFIED.value,
            resource_type=resource_type,
            resource_id=resource_id,
            details=details,
            ip_address=ip_address,
            user_agent=user_agent,
        )

    async def log_data_deleted(
        self,
        user_id: UUID,
        resource_type: str,
        resource_id: Optional[UUID] = None,
        details: Optional[dict] = None,
        ip_address: Optional[str] = None,
        user_agent: Optional[str] = None,
    ) -> AuditLogResponse:
        """Log a data deletion event.

        Args:
            user_id: ID of the user who deleted the data
            resource_type: Type of resource deleted
            resource_id: Optional ID of the resource
            details: Optional additional details
            ip_address: Optional IP address
            user_agent: Optional user agent

        Returns:
            AuditLogResponse with the created audit log entry
        """
        return await self.log(
            user_id=user_id,
            action=AuditAction.DATA_DELETED.value,
            resource_type=resource_type,
            resource_id=resource_id,
            details=details,
            ip_address=ip_address,
            user_agent=user_agent,
        )

    async def log_role_assigned(
        self,
        user_id: UUID,
        target_user_id: UUID,
        role_name: str,
        details: Optional[dict] = None,
        ip_address: Optional[str] = None,
        user_agent: Optional[str] = None,
    ) -> AuditLogResponse:
        """Log a role assignment event.

        Args:
            user_id: ID of the user who assigned the role
            target_user_id: ID of the user who received the role
            role_name: Name of the role assigned
            details: Optional additional details
            ip_address: Optional IP address
            user_agent: Optional user agent

        Returns:
            AuditLogResponse with the created audit log entry
        """
        log_details = {"target_user_id": str(target_user_id), "role_name": role_name}
        if details:
            log_details.update(details)

        return await self.log(
            user_id=user_id,
            action=AuditAction.ROLE_ASSIGNED.value,
            resource_type="user_role",
            resource_id=target_user_id,
            details=log_details,
            ip_address=ip_address,
            user_agent=user_agent,
        )

    async def log_role_revoked(
        self,
        user_id: UUID,
        target_user_id: UUID,
        role_name: str,
        details: Optional[dict] = None,
        ip_address: Optional[str] = None,
        user_agent: Optional[str] = None,
    ) -> AuditLogResponse:
        """Log a role revocation event.

        Args:
            user_id: ID of the user who revoked the role
            target_user_id: ID of the user who lost the role
            role_name: Name of the role revoked
            details: Optional additional details
            ip_address: Optional IP address
            user_agent: Optional user agent

        Returns:
            AuditLogResponse with the created audit log entry
        """
        log_details = {"target_user_id": str(target_user_id), "role_name": role_name}
        if details:
            log_details.update(details)

        return await self.log(
            user_id=user_id,
            action=AuditAction.ROLE_REVOKED.value,
            resource_type="user_role",
            resource_id=target_user_id,
            details=log_details,
            ip_address=ip_address,
            user_agent=user_agent,
        )

    async def log_login(
        self,
        user_id: UUID,
        details: Optional[dict] = None,
        ip_address: Optional[str] = None,
        user_agent: Optional[str] = None,
    ) -> AuditLogResponse:
        """Log a user login event.

        Args:
            user_id: ID of the user who logged in
            details: Optional additional details
            ip_address: Optional IP address
            user_agent: Optional user agent

        Returns:
            AuditLogResponse with the created audit log entry
        """
        return await self.log(
            user_id=user_id,
            action=AuditAction.LOGIN.value,
            resource_type="session",
            resource_id=None,
            details=details,
            ip_address=ip_address,
            user_agent=user_agent,
        )

    async def log_logout(
        self,
        user_id: UUID,
        details: Optional[dict] = None,
        ip_address: Optional[str] = None,
        user_agent: Optional[str] = None,
    ) -> AuditLogResponse:
        """Log a user logout event.

        Args:
            user_id: ID of the user who logged out
            details: Optional additional details
            ip_address: Optional IP address
            user_agent: Optional user agent

        Returns:
            AuditLogResponse with the created audit log entry
        """
        return await self.log(
            user_id=user_id,
            action=AuditAction.LOGOUT.value,
            resource_type="session",
            resource_id=None,
            details=details,
            ip_address=ip_address,
            user_agent=user_agent,
        )

    # =========================================================================
    # Query Methods
    # =========================================================================

    async def get_audit_logs(
        self,
        filter_params: Optional[AuditLogFilter] = None,
        limit: int = 100,
        offset: int = 0,
    ) -> list[AuditLogResponse]:
        """Get audit logs with optional filtering.

        Args:
            filter_params: Optional filter parameters
            limit: Maximum number of entries to return
            offset: Number of entries to skip

        Returns:
            List of audit log entries matching the filter
        """
        query = select(AuditLog)

        if filter_params:
            conditions = []

            if filter_params.user_id:
                conditions.append(AuditLog.user_id == filter_params.user_id)

            if filter_params.action:
                conditions.append(AuditLog.action == filter_params.action)

            if filter_params.resource_type:
                conditions.append(AuditLog.resource_type == filter_params.resource_type)

            if filter_params.start_date:
                conditions.append(AuditLog.created_at >= filter_params.start_date)

            if filter_params.end_date:
                conditions.append(AuditLog.created_at <= filter_params.end_date)

            if conditions:
                query = query.where(and_(*conditions))

        query = query.order_by(desc(AuditLog.created_at)).limit(limit).offset(offset)

        result = await self.db.execute(query)
        audit_logs = result.scalars().all()

        return [self._build_audit_log_response(log) for log in audit_logs]

    async def get_audit_log_by_id(self, log_id: UUID) -> AuditLogResponse:
        """Get a single audit log entry by ID.

        Args:
            log_id: ID of the audit log entry

        Returns:
            The audit log entry

        Raises:
            AuditLogNotFoundError: If the audit log entry is not found
        """
        query = select(AuditLog).where(AuditLog.id == log_id)
        result = await self.db.execute(query)
        audit_log = result.scalar_one_or_none()

        if not audit_log:
            raise AuditLogNotFoundError(f"Audit log with ID {log_id} not found")

        return self._build_audit_log_response(audit_log)

    async def get_user_audit_history(
        self,
        user_id: UUID,
        limit: int = 100,
        offset: int = 0,
    ) -> list[AuditLogResponse]:
        """Get all audit log entries for a specific user.

        Args:
            user_id: ID of the user
            limit: Maximum number of entries to return
            offset: Number of entries to skip

        Returns:
            List of audit log entries for the user
        """
        filter_params = AuditLogFilter(user_id=user_id)
        return await self.get_audit_logs(
            filter_params=filter_params,
            limit=limit,
            offset=offset,
        )

    async def get_resource_audit_history(
        self,
        resource_type: str,
        resource_id: Optional[UUID] = None,
        limit: int = 100,
        offset: int = 0,
    ) -> list[AuditLogResponse]:
        """Get all audit log entries for a specific resource.

        Args:
            resource_type: Type of the resource
            resource_id: Optional ID of the resource
            limit: Maximum number of entries to return
            offset: Number of entries to skip

        Returns:
            List of audit log entries for the resource
        """
        query = select(AuditLog).where(AuditLog.resource_type == resource_type)

        if resource_id:
            query = query.where(AuditLog.resource_id == resource_id)

        query = query.order_by(desc(AuditLog.created_at)).limit(limit).offset(offset)

        result = await self.db.execute(query)
        audit_logs = result.scalars().all()

        return [self._build_audit_log_response(log) for log in audit_logs]

    async def count_audit_logs(
        self,
        filter_params: Optional[AuditLogFilter] = None,
    ) -> int:
        """Count audit logs matching the filter.

        Args:
            filter_params: Optional filter parameters

        Returns:
            Count of matching audit log entries
        """
        from sqlalchemy import func

        query = select(func.count()).select_from(AuditLog)

        if filter_params:
            conditions = []

            if filter_params.user_id:
                conditions.append(AuditLog.user_id == filter_params.user_id)

            if filter_params.action:
                conditions.append(AuditLog.action == filter_params.action)

            if filter_params.resource_type:
                conditions.append(AuditLog.resource_type == filter_params.resource_type)

            if filter_params.start_date:
                conditions.append(AuditLog.created_at >= filter_params.start_date)

            if filter_params.end_date:
                conditions.append(AuditLog.created_at <= filter_params.end_date)

            if conditions:
                query = query.where(and_(*conditions))

        result = await self.db.execute(query)
        return result.scalar() or 0

    # =========================================================================
    # Private Helper Methods
    # =========================================================================

    def _build_audit_log_response(self, audit_log: AuditLog) -> AuditLogResponse:
        """Build an AuditLogResponse from a database object.

        Args:
            audit_log: The AuditLog database object

        Returns:
            AuditLogResponse schema object
        """
        return AuditLogResponse(
            id=audit_log.id,
            user_id=audit_log.user_id,
            action=audit_log.action,
            resource_type=audit_log.resource_type,
            resource_id=audit_log.resource_id,
            details=audit_log.details,
            ip_address=audit_log.ip_address,
            user_agent=audit_log.user_agent,
            created_at=audit_log.created_at,
        )
