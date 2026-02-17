"""Add auth tables (users, token_blacklist, password_reset_tokens).

Revision ID: 002_auth
Revises: None
Create Date: 2026-02-16

Creates the following tables for JWT authentication:
- users: User accounts with role-based access control
- token_blacklist: Revoked JWT tokens
- password_reset_tokens: Password reset tokens
"""

from alembic import op
import sqlalchemy as sa
from sqlalchemy.dialects.postgresql import UUID


# revision identifiers, used by Alembic.
revision = "002_auth"
down_revision = None
branch_labels = None
depends_on = None


def upgrade() -> None:
    """Create auth tables for JWT authentication."""
    # Create user_role_enum type
    user_role_enum = sa.Enum(
        'admin', 'teacher', 'parent', 'accountant', 'staff',
        name='user_role_enum'
    )
    user_role_enum.create(op.get_bind(), checkfirst=True)

    # Create users table
    op.create_table(
        "users",
        sa.Column("id", UUID(as_uuid=True), primary_key=True),
        sa.Column("email", sa.String(255), nullable=False, unique=True, index=True),
        sa.Column("password_hash", sa.String(255), nullable=False),
        sa.Column("first_name", sa.String(100), nullable=False),
        sa.Column("last_name", sa.String(100), nullable=False),
        sa.Column("role", user_role_enum, nullable=False, index=True),
        sa.Column("is_active", sa.Boolean, nullable=False, server_default=sa.text("true"), index=True),
        sa.Column(
            "created_at",
            sa.DateTime(timezone=True),
            server_default=sa.func.now(),
            nullable=False,
        ),
        sa.Column(
            "updated_at",
            sa.DateTime(timezone=True),
            server_default=sa.func.now(),
            nullable=False,
        ),
    )

    # Create token_blacklist table
    op.create_table(
        "token_blacklist",
        sa.Column("id", UUID(as_uuid=True), primary_key=True),
        sa.Column("token", sa.String(500), nullable=False, unique=True, index=True),
        sa.Column("user_id", UUID(as_uuid=True), nullable=False, index=True),
        sa.Column(
            "blacklisted_at",
            sa.DateTime(timezone=True),
            server_default=sa.func.now(),
            nullable=False,
        ),
        sa.Column("expires_at", sa.DateTime(timezone=True), nullable=False, index=True),
    )

    # Create password_reset_tokens table
    op.create_table(
        "password_reset_tokens",
        sa.Column("id", UUID(as_uuid=True), primary_key=True),
        sa.Column("token", sa.String(500), nullable=False, unique=True, index=True),
        sa.Column("user_id", UUID(as_uuid=True), nullable=False, index=True),
        sa.Column("email", sa.String(255), nullable=False, index=True),
        sa.Column("is_used", sa.Boolean, nullable=False, server_default=sa.text("false"), index=True),
        sa.Column(
            "created_at",
            sa.DateTime(timezone=True),
            server_default=sa.func.now(),
            nullable=False,
        ),
        sa.Column("expires_at", sa.DateTime(timezone=True), nullable=False, index=True),
    )


def downgrade() -> None:
    """Drop auth tables."""
    op.drop_table("password_reset_tokens")
    op.drop_table("token_blacklist")
    op.drop_table("users")

    # Drop the enum type
    sa.Enum(name='user_role_enum').drop(op.get_bind(), checkfirst=True)
