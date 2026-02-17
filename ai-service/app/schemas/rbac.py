"""RBAC domain schemas for LAYA AI Service.

Defines Pydantic schemas for Role-Based Access Control (RBAC) request/response
validation and serialization. The RBAC system provides 5-role access control
with group-level restrictions, audit trails, and permission management.
"""

from datetime import datetime
from enum import Enum
from typing import Any, Optional
from uuid import UUID

from pydantic import BaseModel, Field

from app.schemas.base import BaseResponse, BaseSchema, PaginatedResponse


class RoleType(str, Enum):
    """Predefined role types for the RBAC system.

    Attributes:
        DIRECTOR: Full administrative access
        TEACHER: Access to classroom and child management
        ASSISTANT: Limited classroom support access
        STAFF: Operational staff with limited access
        PARENT: Read-only access to their children's data
    """

    DIRECTOR = "director"
    TEACHER = "teacher"
    ASSISTANT = "assistant"
    STAFF = "staff"
    PARENT = "parent"


class AuditAction(str, Enum):
    """Types of audit log actions.

    Attributes:
        ROLE_ASSIGNED: A role was assigned to a user
        ROLE_REVOKED: A role was revoked from a user
        PERMISSION_GRANTED: A permission was granted
        PERMISSION_REVOKED: A permission was revoked
        ACCESS_GRANTED: Access was granted to a resource
        ACCESS_DENIED: Access was denied to a resource
        LOGIN: User logged in
        LOGOUT: User logged out
        DATA_MODIFIED: Data was modified
        DATA_DELETED: Data was deleted
    """

    ROLE_ASSIGNED = "role_assigned"
    ROLE_REVOKED = "role_revoked"
    PERMISSION_GRANTED = "permission_granted"
    PERMISSION_REVOKED = "permission_revoked"
    ACCESS_GRANTED = "access_granted"
    ACCESS_DENIED = "access_denied"
    LOGIN = "login"
    LOGOUT = "logout"
    DATA_MODIFIED = "data_modified"
    DATA_DELETED = "data_deleted"


class PermissionAction(str, Enum):
    """Types of permission actions.

    Attributes:
        READ: Read access to a resource
        WRITE: Write access to a resource
        DELETE: Delete access to a resource
        MANAGE: Full management access to a resource
    """

    READ = "read"
    WRITE = "write"
    DELETE = "delete"
    MANAGE = "manage"


# =============================================================================
# Permission Schemas
# =============================================================================


class PermissionBase(BaseSchema):
    """Base schema for permission data.

    Contains common fields shared between request and response schemas.

    Attributes:
        resource: The resource this permission applies to
        action: The action permitted on the resource
        conditions: Optional conditions for fine-grained access control
        is_active: Whether the permission is currently active
    """

    resource: str = Field(
        ...,
        min_length=1,
        max_length=100,
        description="The resource this permission applies to (e.g., 'children', 'reports')",
    )
    action: str = Field(
        ...,
        min_length=1,
        max_length=50,
        description="The action permitted (e.g., 'read', 'write', 'delete')",
    )
    conditions: Optional[dict[str, Any]] = Field(
        default=None,
        description="Optional JSON conditions for fine-grained access control",
    )
    is_active: bool = Field(
        default=True,
        description="Whether the permission is currently active",
    )


class PermissionRequest(PermissionBase):
    """Request schema for creating or updating permissions.

    Inherits all fields from PermissionBase.

    Attributes:
        role_id: ID of the role this permission belongs to
    """

    role_id: UUID = Field(
        ...,
        description="ID of the role this permission belongs to",
    )


class PermissionResponse(PermissionBase):
    """Response schema for permission data.

    Includes all base permission fields plus ID and timestamps.

    Attributes:
        id: Unique identifier for the permission
        role_id: ID of the role this permission belongs to
        created_at: Timestamp when the permission was created
    """

    id: UUID = Field(
        ...,
        description="Unique identifier for the permission",
    )
    role_id: UUID = Field(
        ...,
        description="ID of the role this permission belongs to",
    )
    created_at: Optional[datetime] = Field(
        default=None,
        description="Timestamp when the permission was created",
    )


class PermissionListResponse(PaginatedResponse):
    """Paginated list of permissions.

    Attributes:
        items: List of permission responses
    """

    items: list[PermissionResponse] = Field(
        default_factory=list,
        description="List of permissions",
    )


# =============================================================================
# Role Schemas
# =============================================================================


class RoleBase(BaseSchema):
    """Base schema for role data.

    Contains common fields shared between request and response schemas.

    Attributes:
        name: Unique name of the role
        display_name: Human-readable name for display
        description: Description of the role's purpose
        is_system_role: Whether this is a predefined system role
        is_active: Whether the role is currently active
    """

    name: str = Field(
        ...,
        min_length=1,
        max_length=50,
        description="Unique name of the role (e.g., 'director', 'teacher')",
    )
    display_name: str = Field(
        ...,
        min_length=1,
        max_length=100,
        description="Human-readable name for display",
    )
    description: Optional[str] = Field(
        default=None,
        max_length=1000,
        description="Description of the role's purpose",
    )
    is_system_role: bool = Field(
        default=False,
        description="Whether this is a predefined system role",
    )
    is_active: bool = Field(
        default=True,
        description="Whether the role is currently active",
    )


class RoleRequest(RoleBase):
    """Request schema for creating or updating roles.

    Inherits all fields from RoleBase.
    """

    pass


class RoleResponse(RoleBase, BaseResponse):
    """Response schema for role data.

    Includes all base role fields plus ID and timestamps.

    Attributes:
        permissions: List of permissions assigned to this role
    """

    permissions: list[PermissionResponse] = Field(
        default_factory=list,
        description="List of permissions assigned to this role",
    )


class RoleListResponse(PaginatedResponse):
    """Paginated list of roles.

    Attributes:
        items: List of role responses
    """

    items: list[RoleResponse] = Field(
        default_factory=list,
        description="List of roles",
    )


# =============================================================================
# User Role Assignment Schemas
# =============================================================================


class UserRoleAssignmentBase(BaseSchema):
    """Base schema for user role assignment data.

    Contains common fields for assigning roles to users.

    Attributes:
        user_id: ID of the user to assign the role to
        role_id: ID of the role to assign
        organization_id: Optional organization scope for the role
        group_id: Optional group scope for the role
        expires_at: Optional expiration timestamp for temporary assignments
    """

    user_id: UUID = Field(
        ...,
        description="ID of the user to assign the role to",
    )
    role_id: UUID = Field(
        ...,
        description="ID of the role to assign",
    )
    organization_id: Optional[UUID] = Field(
        default=None,
        description="Optional organization scope for the role",
    )
    group_id: Optional[UUID] = Field(
        default=None,
        description="Optional group scope for the role",
    )
    expires_at: Optional[datetime] = Field(
        default=None,
        description="Optional expiration timestamp for temporary assignments",
    )


class UserRoleAssignment(UserRoleAssignmentBase):
    """Request schema for assigning a role to a user.

    Inherits all fields from UserRoleAssignmentBase.
    """

    pass


class UserRoleResponse(UserRoleAssignmentBase):
    """Response schema for user role assignment data.

    Includes all base assignment fields plus ID and timestamps.

    Attributes:
        id: Unique identifier for the user-role assignment
        assigned_by: ID of the user who made the assignment
        assigned_at: Timestamp when the role was assigned
        is_active: Whether the assignment is currently active
        role: The assigned role details
    """

    id: UUID = Field(
        ...,
        description="Unique identifier for the user-role assignment",
    )
    assigned_by: Optional[UUID] = Field(
        default=None,
        description="ID of the user who made the assignment",
    )
    assigned_at: datetime = Field(
        ...,
        description="Timestamp when the role was assigned",
    )
    is_active: bool = Field(
        default=True,
        description="Whether the assignment is currently active",
    )
    role: Optional[RoleResponse] = Field(
        default=None,
        description="The assigned role details",
    )


class UserRoleListResponse(PaginatedResponse):
    """Paginated list of user role assignments.

    Attributes:
        items: List of user role responses
    """

    items: list[UserRoleResponse] = Field(
        default_factory=list,
        description="List of user role assignments",
    )


class RevokeRoleRequest(BaseSchema):
    """Request schema for revoking a role from a user.

    Attributes:
        user_id: ID of the user to revoke the role from
        role_id: ID of the role to revoke
        organization_id: Optional organization scope
        group_id: Optional group scope
    """

    user_id: UUID = Field(
        ...,
        description="ID of the user to revoke the role from",
    )
    role_id: UUID = Field(
        ...,
        description="ID of the role to revoke",
    )
    organization_id: Optional[UUID] = Field(
        default=None,
        description="Optional organization scope",
    )
    group_id: Optional[UUID] = Field(
        default=None,
        description="Optional group scope",
    )


# =============================================================================
# Permission Check Schemas
# =============================================================================


class PermissionCheckRequest(BaseSchema):
    """Request schema for checking user permissions.

    Attributes:
        user_id: ID of the user to check permissions for
        resource: The resource to check access for
        action: The action to check permission for
        organization_id: Optional organization context
        group_id: Optional group context
    """

    user_id: UUID = Field(
        ...,
        description="ID of the user to check permissions for",
    )
    resource: str = Field(
        ...,
        min_length=1,
        max_length=100,
        description="The resource to check access for",
    )
    action: str = Field(
        ...,
        min_length=1,
        max_length=50,
        description="The action to check permission for",
    )
    organization_id: Optional[UUID] = Field(
        default=None,
        description="Optional organization context",
    )
    group_id: Optional[UUID] = Field(
        default=None,
        description="Optional group context",
    )


class PermissionCheckResponse(BaseSchema):
    """Response schema for permission check results.

    Attributes:
        allowed: Whether the permission is allowed
        user_id: ID of the user checked
        resource: The resource checked
        action: The action checked
        matched_role: The role that granted the permission (if allowed)
        reason: Explanation for the permission decision
    """

    allowed: bool = Field(
        ...,
        description="Whether the permission is allowed",
    )
    user_id: UUID = Field(
        ...,
        description="ID of the user checked",
    )
    resource: str = Field(
        ...,
        description="The resource checked",
    )
    action: str = Field(
        ...,
        description="The action checked",
    )
    matched_role: Optional[str] = Field(
        default=None,
        description="The role that granted the permission (if allowed)",
    )
    reason: Optional[str] = Field(
        default=None,
        description="Explanation for the permission decision",
    )


class UserPermissionsResponse(BaseSchema):
    """Response schema for getting all permissions for a user.

    Attributes:
        user_id: ID of the user
        roles: List of roles assigned to the user
        permissions: Aggregated list of all permissions
    """

    user_id: UUID = Field(
        ...,
        description="ID of the user",
    )
    roles: list[RoleResponse] = Field(
        default_factory=list,
        description="List of roles assigned to the user",
    )
    permissions: list[PermissionResponse] = Field(
        default_factory=list,
        description="Aggregated list of all permissions",
    )


# =============================================================================
# Audit Log Schemas
# =============================================================================


class AuditLogBase(BaseSchema):
    """Base schema for audit log data.

    Contains common fields for audit logging.

    Attributes:
        user_id: ID of the user who performed the action
        action: The action performed
        resource_type: Type of resource affected
        resource_id: ID of the affected resource
        details: JSON details about the action
        ip_address: IP address of the request
        user_agent: User agent string of the request
    """

    user_id: UUID = Field(
        ...,
        description="ID of the user who performed the action",
    )
    action: str = Field(
        ...,
        min_length=1,
        max_length=100,
        description="The action performed (e.g., 'role_assigned', 'access_denied')",
    )
    resource_type: str = Field(
        ...,
        min_length=1,
        max_length=100,
        description="Type of resource affected",
    )
    resource_id: Optional[UUID] = Field(
        default=None,
        description="ID of the affected resource",
    )
    details: Optional[dict[str, Any]] = Field(
        default=None,
        description="JSON details about the action",
    )
    ip_address: Optional[str] = Field(
        default=None,
        max_length=45,
        description="IP address of the request",
    )
    user_agent: Optional[str] = Field(
        default=None,
        max_length=500,
        description="User agent string of the request",
    )


class AuditLogRequest(AuditLogBase):
    """Request schema for creating audit log entries.

    Inherits all fields from AuditLogBase.
    """

    pass


class AuditLogResponse(AuditLogBase):
    """Response schema for audit log data.

    Includes all base audit log fields plus ID and timestamp.

    Attributes:
        id: Unique identifier for the audit log entry
        created_at: Timestamp when the event occurred
    """

    id: UUID = Field(
        ...,
        description="Unique identifier for the audit log entry",
    )
    created_at: datetime = Field(
        ...,
        description="Timestamp when the event occurred",
    )


class AuditLogListResponse(PaginatedResponse):
    """Paginated list of audit log entries.

    Attributes:
        items: List of audit log responses
    """

    items: list[AuditLogResponse] = Field(
        default_factory=list,
        description="List of audit log entries",
    )


class AuditLogFilter(BaseSchema):
    """Filter parameters for querying audit logs.

    Attributes:
        user_id: Filter by user ID
        action: Filter by action type
        resource_type: Filter by resource type
        start_date: Filter events after this date
        end_date: Filter events before this date
    """

    user_id: Optional[UUID] = Field(
        default=None,
        description="Filter by user ID",
    )
    action: Optional[str] = Field(
        default=None,
        max_length=100,
        description="Filter by action type",
    )
    resource_type: Optional[str] = Field(
        default=None,
        max_length=100,
        description="Filter by resource type",
    )
    start_date: Optional[datetime] = Field(
        default=None,
        description="Filter events after this date",
    )
    end_date: Optional[datetime] = Field(
        default=None,
        description="Filter events before this date",
    )
