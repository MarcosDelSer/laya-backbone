"""Add RBAC tables for Role-Based Access Control.

Revision ID: 002
Revises: None
Create Date: 2026-02-15 16:43:43

Creates the following tables:
- roles: Defines roles in the RBAC system (5 predefined roles)
- permissions: Individual permissions assigned to roles
- user_roles: Associates users with roles at organization/group level
- audit_logs: Tracks RBAC-related actions for audit and compliance
"""

from typing import Sequence, Union
from uuid import uuid4

from alembic import op
import sqlalchemy as sa
from sqlalchemy.dialects.postgresql import JSONB, UUID


# revision identifiers, used by Alembic.
revision: str = "002"
down_revision: Union[str, None] = None
branch_labels: Union[str, Sequence[str], None] = None
depends_on: Union[str, Sequence[str], None] = None


# Predefined role UUIDs for seed data (consistent across environments)
ROLE_DIRECTOR_ID = "11111111-1111-1111-1111-111111111111"
ROLE_TEACHER_ID = "22222222-2222-2222-2222-222222222222"
ROLE_ASSISTANT_ID = "33333333-3333-3333-3333-333333333333"
ROLE_STAFF_ID = "44444444-4444-4444-4444-444444444444"
ROLE_PARENT_ID = "55555555-5555-5555-5555-555555555555"


def upgrade() -> None:
    """Create RBAC tables and seed default roles."""
    # Create roles table
    op.create_table(
        "roles",
        sa.Column("id", UUID(as_uuid=True), nullable=False),
        sa.Column("name", sa.String(50), nullable=False),
        sa.Column("display_name", sa.String(100), nullable=False),
        sa.Column("description", sa.Text(), nullable=True),
        sa.Column("is_system_role", sa.Boolean(), nullable=False, server_default="false"),
        sa.Column("is_active", sa.Boolean(), nullable=False, server_default="true"),
        sa.Column(
            "created_at",
            sa.DateTime(),
            server_default=sa.func.now(),
            nullable=False,
        ),
        sa.Column("updated_at", sa.DateTime(), nullable=True),
        sa.PrimaryKeyConstraint("id"),
    )
    op.create_index("ix_roles_name", "roles", ["name"], unique=True)
    op.create_index("ix_roles_is_active", "roles", ["is_active"], unique=False)
    op.create_index("ix_roles_is_system_role", "roles", ["is_system_role"], unique=False)

    # Create permissions table
    op.create_table(
        "permissions",
        sa.Column("id", UUID(as_uuid=True), nullable=False),
        sa.Column("role_id", UUID(as_uuid=True), nullable=False),
        sa.Column("resource", sa.String(100), nullable=False),
        sa.Column("action", sa.String(50), nullable=False),
        sa.Column("conditions", JSONB(), nullable=True),
        sa.Column("is_active", sa.Boolean(), nullable=False, server_default="true"),
        sa.Column(
            "created_at",
            sa.DateTime(),
            server_default=sa.func.now(),
            nullable=False,
        ),
        sa.PrimaryKeyConstraint("id"),
        sa.ForeignKeyConstraint(["role_id"], ["roles.id"], ondelete="CASCADE"),
    )
    op.create_index("ix_permissions_role_id", "permissions", ["role_id"], unique=False)
    op.create_index(
        "ix_permissions_resource_action",
        "permissions",
        ["resource", "action"],
        unique=False,
    )
    op.create_index(
        "ix_permissions_role_resource",
        "permissions",
        ["role_id", "resource"],
        unique=False,
    )

    # Create user_roles table
    op.create_table(
        "user_roles",
        sa.Column("id", UUID(as_uuid=True), nullable=False),
        sa.Column("user_id", UUID(as_uuid=True), nullable=False),
        sa.Column("role_id", UUID(as_uuid=True), nullable=False),
        sa.Column("organization_id", UUID(as_uuid=True), nullable=True),
        sa.Column("group_id", UUID(as_uuid=True), nullable=True),
        sa.Column("assigned_by", UUID(as_uuid=True), nullable=True),
        sa.Column(
            "assigned_at",
            sa.DateTime(),
            server_default=sa.func.now(),
            nullable=False,
        ),
        sa.Column("expires_at", sa.DateTime(), nullable=True),
        sa.Column("is_active", sa.Boolean(), nullable=False, server_default="true"),
        sa.PrimaryKeyConstraint("id"),
        sa.ForeignKeyConstraint(["role_id"], ["roles.id"], ondelete="CASCADE"),
    )
    op.create_index("ix_user_roles_user_id", "user_roles", ["user_id"], unique=False)
    op.create_index("ix_user_roles_role_id", "user_roles", ["role_id"], unique=False)
    op.create_index("ix_user_roles_organization_id", "user_roles", ["organization_id"], unique=False)
    op.create_index("ix_user_roles_group_id", "user_roles", ["group_id"], unique=False)
    op.create_index(
        "ix_user_roles_user_role",
        "user_roles",
        ["user_id", "role_id"],
        unique=False,
    )
    op.create_index(
        "ix_user_roles_user_org",
        "user_roles",
        ["user_id", "organization_id"],
        unique=False,
    )
    op.create_index(
        "ix_user_roles_user_group",
        "user_roles",
        ["user_id", "group_id"],
        unique=False,
    )

    # Create audit_logs table
    op.create_table(
        "audit_logs",
        sa.Column("id", UUID(as_uuid=True), nullable=False),
        sa.Column("user_id", UUID(as_uuid=True), nullable=False),
        sa.Column("action", sa.String(100), nullable=False),
        sa.Column("resource_type", sa.String(100), nullable=False),
        sa.Column("resource_id", UUID(as_uuid=True), nullable=True),
        sa.Column("details", JSONB(), nullable=True),
        sa.Column("ip_address", sa.String(45), nullable=True),
        sa.Column("user_agent", sa.String(500), nullable=True),
        sa.Column(
            "created_at",
            sa.DateTime(),
            server_default=sa.func.now(),
            nullable=False,
        ),
        sa.PrimaryKeyConstraint("id"),
    )
    op.create_index("ix_audit_logs_user_id", "audit_logs", ["user_id"], unique=False)
    op.create_index("ix_audit_logs_action", "audit_logs", ["action"], unique=False)
    op.create_index("ix_audit_logs_created_at", "audit_logs", ["created_at"], unique=False)
    op.create_index(
        "ix_audit_logs_user_action",
        "audit_logs",
        ["user_id", "action"],
        unique=False,
    )
    op.create_index(
        "ix_audit_logs_resource",
        "audit_logs",
        ["resource_type", "resource_id"],
        unique=False,
    )
    op.create_index(
        "ix_audit_logs_created_at_desc",
        "audit_logs",
        ["created_at"],
        unique=False,
    )

    # Seed default roles
    roles_table = sa.table(
        "roles",
        sa.column("id", UUID(as_uuid=True)),
        sa.column("name", sa.String),
        sa.column("display_name", sa.String),
        sa.column("description", sa.Text),
        sa.column("is_system_role", sa.Boolean),
        sa.column("is_active", sa.Boolean),
    )

    op.bulk_insert(
        roles_table,
        [
            {
                "id": ROLE_DIRECTOR_ID,
                "name": "director",
                "display_name": "Director",
                "description": "Full administrative access to all features and data",
                "is_system_role": True,
                "is_active": True,
            },
            {
                "id": ROLE_TEACHER_ID,
                "name": "teacher",
                "display_name": "Teacher",
                "description": "Access to classroom and child management features",
                "is_system_role": True,
                "is_active": True,
            },
            {
                "id": ROLE_ASSISTANT_ID,
                "name": "assistant",
                "display_name": "Assistant",
                "description": "Limited classroom support access",
                "is_system_role": True,
                "is_active": True,
            },
            {
                "id": ROLE_STAFF_ID,
                "name": "staff",
                "display_name": "Staff",
                "description": "Operational staff with limited access",
                "is_system_role": True,
                "is_active": True,
            },
            {
                "id": ROLE_PARENT_ID,
                "name": "parent",
                "display_name": "Parent",
                "description": "Read-only access to their children's data",
                "is_system_role": True,
                "is_active": True,
            },
        ],
    )

    # Seed default permissions for each role
    permissions_table = sa.table(
        "permissions",
        sa.column("id", UUID(as_uuid=True)),
        sa.column("role_id", UUID(as_uuid=True)),
        sa.column("resource", sa.String),
        sa.column("action", sa.String),
        sa.column("is_active", sa.Boolean),
    )

    # Director permissions - full access
    director_permissions = [
        {"resource": "*", "action": "*"},  # Full access to everything
    ]

    # Teacher permissions - classroom and child management
    teacher_permissions = [
        {"resource": "children", "action": "read"},
        {"resource": "children", "action": "write"},
        {"resource": "classrooms", "action": "read"},
        {"resource": "classrooms", "action": "write"},
        {"resource": "activities", "action": "read"},
        {"resource": "activities", "action": "write"},
        {"resource": "attendance", "action": "read"},
        {"resource": "attendance", "action": "write"},
        {"resource": "reports", "action": "read"},
        {"resource": "coaching", "action": "read"},
    ]

    # Assistant permissions - limited classroom support
    assistant_permissions = [
        {"resource": "children", "action": "read"},
        {"resource": "classrooms", "action": "read"},
        {"resource": "activities", "action": "read"},
        {"resource": "attendance", "action": "read"},
        {"resource": "attendance", "action": "write"},
    ]

    # Staff permissions - operational access
    staff_permissions = [
        {"resource": "schedule", "action": "read"},
        {"resource": "facilities", "action": "read"},
    ]

    # Parent permissions - read-only access to their children
    parent_permissions = [
        {"resource": "children", "action": "read"},
        {"resource": "activities", "action": "read"},
        {"resource": "reports", "action": "read"},
        {"resource": "photos", "action": "read"},
    ]

    all_permissions = []

    for perm in director_permissions:
        all_permissions.append({
            "id": str(uuid4()),
            "role_id": ROLE_DIRECTOR_ID,
            "resource": perm["resource"],
            "action": perm["action"],
            "is_active": True,
        })

    for perm in teacher_permissions:
        all_permissions.append({
            "id": str(uuid4()),
            "role_id": ROLE_TEACHER_ID,
            "resource": perm["resource"],
            "action": perm["action"],
            "is_active": True,
        })

    for perm in assistant_permissions:
        all_permissions.append({
            "id": str(uuid4()),
            "role_id": ROLE_ASSISTANT_ID,
            "resource": perm["resource"],
            "action": perm["action"],
            "is_active": True,
        })

    for perm in staff_permissions:
        all_permissions.append({
            "id": str(uuid4()),
            "role_id": ROLE_STAFF_ID,
            "resource": perm["resource"],
            "action": perm["action"],
            "is_active": True,
        })

    for perm in parent_permissions:
        all_permissions.append({
            "id": str(uuid4()),
            "role_id": ROLE_PARENT_ID,
            "resource": perm["resource"],
            "action": perm["action"],
            "is_active": True,
        })

    op.bulk_insert(permissions_table, all_permissions)


def downgrade() -> None:
    """Drop RBAC tables in reverse order (child tables first due to foreign keys)."""
    # Drop indexes and tables for audit_logs
    op.drop_index("ix_audit_logs_created_at_desc", table_name="audit_logs")
    op.drop_index("ix_audit_logs_resource", table_name="audit_logs")
    op.drop_index("ix_audit_logs_user_action", table_name="audit_logs")
    op.drop_index("ix_audit_logs_created_at", table_name="audit_logs")
    op.drop_index("ix_audit_logs_action", table_name="audit_logs")
    op.drop_index("ix_audit_logs_user_id", table_name="audit_logs")
    op.drop_table("audit_logs")

    # Drop indexes and tables for user_roles
    op.drop_index("ix_user_roles_user_group", table_name="user_roles")
    op.drop_index("ix_user_roles_user_org", table_name="user_roles")
    op.drop_index("ix_user_roles_user_role", table_name="user_roles")
    op.drop_index("ix_user_roles_group_id", table_name="user_roles")
    op.drop_index("ix_user_roles_organization_id", table_name="user_roles")
    op.drop_index("ix_user_roles_role_id", table_name="user_roles")
    op.drop_index("ix_user_roles_user_id", table_name="user_roles")
    op.drop_table("user_roles")

    # Drop indexes and tables for permissions
    op.drop_index("ix_permissions_role_resource", table_name="permissions")
    op.drop_index("ix_permissions_resource_action", table_name="permissions")
    op.drop_index("ix_permissions_role_id", table_name="permissions")
    op.drop_table("permissions")

    # Drop indexes and tables for roles
    op.drop_index("ix_roles_is_system_role", table_name="roles")
    op.drop_index("ix_roles_is_active", table_name="roles")
    op.drop_index("ix_roles_name", table_name="roles")
    op.drop_table("roles")
