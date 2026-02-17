"""SQLAlchemy models for Role-Based Access Control (RBAC).

Defines database models for roles, permissions, user-role assignments,
and audit logging. These models support the 5-role RBAC system for
managing access control across the LAYA platform.
"""

from datetime import datetime
from enum import Enum
from typing import Optional
from uuid import uuid4

from sqlalchemy import Boolean, DateTime, ForeignKey, Index, String, Text
from sqlalchemy.dialects.postgresql import JSONB, UUID
from sqlalchemy.orm import Mapped, mapped_column, relationship

from app.models.base import Base


class RoleType(str, Enum):
    """Predefined role types for the RBAC system.

    The 5 roles in the LAYA platform:
    - DIRECTOR: Full administrative access
    - TEACHER: Access to classroom and child management
    - ASSISTANT: Limited classroom support access
    - STAFF: Operational staff with limited access
    - PARENT: Read-only access to their children's data
    """

    DIRECTOR = "director"
    TEACHER = "teacher"
    ASSISTANT = "assistant"
    STAFF = "staff"
    PARENT = "parent"


class Role(Base):
    """Define roles in the RBAC system.

    Roles represent a collection of permissions that can be assigned to users.
    The system includes 5 predefined roles with configurable permissions.

    Attributes:
        id: Unique identifier for the role
        name: Unique name of the role (e.g., 'director', 'teacher')
        display_name: Human-readable name for display
        description: Description of the role's purpose
        is_system_role: Whether this is a predefined system role
        is_active: Whether the role is currently active
        created_at: Timestamp when the role was created
        updated_at: Timestamp when the role was last updated
        permissions: List of permissions assigned to this role
        user_roles: List of user-role assignments
    """

    __tablename__ = "roles"

    id: Mapped[UUID] = mapped_column(
        UUID(as_uuid=True),
        primary_key=True,
        default=uuid4,
    )
    name: Mapped[str] = mapped_column(
        String(50),
        unique=True,
        nullable=False,
        index=True,
    )
    display_name: Mapped[str] = mapped_column(
        String(100),
        nullable=False,
    )
    description: Mapped[Optional[str]] = mapped_column(
        Text,
        nullable=True,
    )
    is_system_role: Mapped[bool] = mapped_column(
        Boolean,
        default=False,
        nullable=False,
    )
    is_active: Mapped[bool] = mapped_column(
        Boolean,
        default=True,
        nullable=False,
    )
    created_at: Mapped[datetime] = mapped_column(
        DateTime,
        default=datetime.utcnow,
        nullable=False,
    )
    updated_at: Mapped[Optional[datetime]] = mapped_column(
        DateTime,
        default=datetime.utcnow,
        onupdate=datetime.utcnow,
        nullable=True,
    )

    # Relationships
    permissions: Mapped[list["Permission"]] = relationship(
        "Permission",
        back_populates="role",
        cascade="all, delete-orphan",
    )
    user_roles: Mapped[list["UserRole"]] = relationship(
        "UserRole",
        back_populates="role",
        cascade="all, delete-orphan",
    )

    # Table-level indexes
    __table_args__ = (
        Index("ix_roles_is_active", "is_active"),
        Index("ix_roles_is_system_role", "is_system_role"),
    )


class Permission(Base):
    """Define individual permissions within the RBAC system.

    Permissions specify what actions a role can perform on which resources.
    Permissions are assigned to roles and checked during authorization.

    Attributes:
        id: Unique identifier for the permission
        role_id: ID of the role this permission belongs to
        resource: The resource this permission applies to (e.g., 'children', 'reports')
        action: The action permitted (e.g., 'read', 'write', 'delete')
        conditions: Optional JSON conditions for fine-grained access control
        is_active: Whether the permission is currently active
        created_at: Timestamp when the permission was created
        role: Reference to the parent role
    """

    __tablename__ = "permissions"

    id: Mapped[UUID] = mapped_column(
        UUID(as_uuid=True),
        primary_key=True,
        default=uuid4,
    )
    role_id: Mapped[UUID] = mapped_column(
        UUID(as_uuid=True),
        ForeignKey("roles.id", ondelete="CASCADE"),
        nullable=False,
        index=True,
    )
    resource: Mapped[str] = mapped_column(
        String(100),
        nullable=False,
    )
    action: Mapped[str] = mapped_column(
        String(50),
        nullable=False,
    )
    conditions: Mapped[Optional[dict]] = mapped_column(
        JSONB,
        nullable=True,
    )
    is_active: Mapped[bool] = mapped_column(
        Boolean,
        default=True,
        nullable=False,
    )
    created_at: Mapped[datetime] = mapped_column(
        DateTime,
        default=datetime.utcnow,
        nullable=False,
    )

    # Relationships
    role: Mapped["Role"] = relationship(
        "Role",
        back_populates="permissions",
    )

    # Table-level indexes for common query patterns
    __table_args__ = (
        Index("ix_permissions_resource_action", "resource", "action"),
        Index("ix_permissions_role_resource", "role_id", "resource"),
    )


class UserRole(Base):
    """Associate users with roles in the RBAC system.

    Supports assigning roles at organization or group level for
    fine-grained access control. Users can have multiple roles.

    Attributes:
        id: Unique identifier for the user-role assignment
        user_id: ID of the user
        role_id: ID of the assigned role
        organization_id: Optional organization scope for the role
        group_id: Optional group scope for the role
        assigned_by: ID of the user who made the assignment
        assigned_at: Timestamp when the role was assigned
        expires_at: Optional expiration timestamp for temporary assignments
        is_active: Whether the assignment is currently active
        role: Reference to the assigned role
    """

    __tablename__ = "user_roles"

    id: Mapped[UUID] = mapped_column(
        UUID(as_uuid=True),
        primary_key=True,
        default=uuid4,
    )
    user_id: Mapped[UUID] = mapped_column(
        UUID(as_uuid=True),
        nullable=False,
        index=True,
    )
    role_id: Mapped[UUID] = mapped_column(
        UUID(as_uuid=True),
        ForeignKey("roles.id", ondelete="CASCADE"),
        nullable=False,
        index=True,
    )
    organization_id: Mapped[Optional[UUID]] = mapped_column(
        UUID(as_uuid=True),
        nullable=True,
        index=True,
    )
    group_id: Mapped[Optional[UUID]] = mapped_column(
        UUID(as_uuid=True),
        nullable=True,
        index=True,
    )
    assigned_by: Mapped[Optional[UUID]] = mapped_column(
        UUID(as_uuid=True),
        nullable=True,
    )
    assigned_at: Mapped[datetime] = mapped_column(
        DateTime,
        default=datetime.utcnow,
        nullable=False,
    )
    expires_at: Mapped[Optional[datetime]] = mapped_column(
        DateTime,
        nullable=True,
    )
    is_active: Mapped[bool] = mapped_column(
        Boolean,
        default=True,
        nullable=False,
    )

    # Relationships
    role: Mapped["Role"] = relationship(
        "Role",
        back_populates="user_roles",
    )

    # Table-level indexes for common query patterns
    __table_args__ = (
        Index("ix_user_roles_user_role", "user_id", "role_id"),
        Index("ix_user_roles_user_org", "user_id", "organization_id"),
        Index("ix_user_roles_user_group", "user_id", "group_id"),
    )


class AuditLog(Base):
    """Track RBAC-related actions for audit and compliance.

    Records all significant RBAC events including role assignments,
    permission changes, and access denials for security auditing.

    Attributes:
        id: Unique identifier for the audit log entry
        user_id: ID of the user who performed the action
        action: The action performed (e.g., 'role_assigned', 'access_denied')
        resource_type: Type of resource affected
        resource_id: ID of the affected resource
        details: JSON details about the action
        ip_address: IP address of the request
        user_agent: User agent string of the request
        created_at: Timestamp when the event occurred
    """

    __tablename__ = "audit_logs"

    id: Mapped[UUID] = mapped_column(
        UUID(as_uuid=True),
        primary_key=True,
        default=uuid4,
    )
    user_id: Mapped[UUID] = mapped_column(
        UUID(as_uuid=True),
        nullable=False,
        index=True,
    )
    action: Mapped[str] = mapped_column(
        String(100),
        nullable=False,
        index=True,
    )
    resource_type: Mapped[str] = mapped_column(
        String(100),
        nullable=False,
    )
    resource_id: Mapped[Optional[UUID]] = mapped_column(
        UUID(as_uuid=True),
        nullable=True,
    )
    details: Mapped[Optional[dict]] = mapped_column(
        JSONB,
        nullable=True,
    )
    ip_address: Mapped[Optional[str]] = mapped_column(
        String(45),
        nullable=True,
    )
    user_agent: Mapped[Optional[str]] = mapped_column(
        String(500),
        nullable=True,
    )
    created_at: Mapped[datetime] = mapped_column(
        DateTime,
        default=datetime.utcnow,
        nullable=False,
        index=True,
    )

    # Table-level indexes for common query patterns
    __table_args__ = (
        Index("ix_audit_logs_user_action", "user_id", "action"),
        Index("ix_audit_logs_resource", "resource_type", "resource_id"),
        Index("ix_audit_logs_created_at_desc", "created_at"),
    )
