"""Add auth tables

Revision ID: 002
Revises:
Create Date: 2026-02-16 00:00:00.000000

"""
from typing import Sequence, Union

from alembic import op
import sqlalchemy as sa
from sqlalchemy.dialects import postgresql

# revision identifiers, used by Alembic.
revision: str = '002'
down_revision: Union[str, None] = None
branch_labels: Union[str, Sequence[str], None] = None
depends_on: Union[str, Sequence[str], None] = None


def upgrade() -> None:
    # Create user_role_enum type
    user_role_enum = postgresql.ENUM(
        'admin',
        'teacher',
        'parent',
        'accountant',
        'staff',
        name='user_role_enum',
        create_type=True
    )
    user_role_enum.create(op.get_bind())

    # Create users table
    op.create_table(
        'users',
        sa.Column('id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('email', sa.String(255), nullable=False),
        sa.Column('password_hash', sa.String(255), nullable=False),
        sa.Column('first_name', sa.String(100), nullable=False),
        sa.Column('last_name', sa.String(100), nullable=False),
        sa.Column('role', user_role_enum, nullable=False),
        sa.Column('is_active', sa.Boolean(), nullable=False, server_default='true'),
        sa.Column('created_at', sa.DateTime(timezone=True), nullable=False, server_default=sa.func.now()),
        sa.Column('updated_at', sa.DateTime(timezone=True), nullable=False, server_default=sa.func.now()),
        sa.PrimaryKeyConstraint('id')
    )
    op.create_index('ix_users_email', 'users', ['email'], unique=True)
    op.create_index('ix_users_role', 'users', ['role'], unique=False)
    op.create_index('ix_users_is_active', 'users', ['is_active'], unique=False)

    # Create token_blacklist table
    op.create_table(
        'token_blacklist',
        sa.Column('id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('token', sa.String(500), nullable=False),
        sa.Column('user_id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('blacklisted_at', sa.DateTime(timezone=True), nullable=False, server_default=sa.func.now()),
        sa.Column('expires_at', sa.DateTime(timezone=True), nullable=False),
        sa.PrimaryKeyConstraint('id')
    )
    op.create_index('ix_token_blacklist_token', 'token_blacklist', ['token'], unique=True)
    op.create_index('ix_token_blacklist_user_id', 'token_blacklist', ['user_id'], unique=False)
    op.create_index('ix_token_blacklist_expires_at', 'token_blacklist', ['expires_at'], unique=False)

    # Create password_reset_tokens table
    op.create_table(
        'password_reset_tokens',
        sa.Column('id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('token', sa.String(500), nullable=False),
        sa.Column('user_id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('email', sa.String(255), nullable=False),
        sa.Column('is_used', sa.Boolean(), nullable=False, server_default='false'),
        sa.Column('created_at', sa.DateTime(timezone=True), nullable=False, server_default=sa.func.now()),
        sa.Column('expires_at', sa.DateTime(timezone=True), nullable=False),
        sa.PrimaryKeyConstraint('id')
    )
    op.create_index('ix_password_reset_tokens_token', 'password_reset_tokens', ['token'], unique=True)
    op.create_index('ix_password_reset_tokens_user_id', 'password_reset_tokens', ['user_id'], unique=False)
    op.create_index('ix_password_reset_tokens_email', 'password_reset_tokens', ['email'], unique=False)
    op.create_index('ix_password_reset_tokens_is_used', 'password_reset_tokens', ['is_used'], unique=False)
    op.create_index('ix_password_reset_tokens_expires_at', 'password_reset_tokens', ['expires_at'], unique=False)


def downgrade() -> None:
    # Drop tables in reverse order
    op.drop_index('ix_password_reset_tokens_expires_at', table_name='password_reset_tokens')
    op.drop_index('ix_password_reset_tokens_is_used', table_name='password_reset_tokens')
    op.drop_index('ix_password_reset_tokens_email', table_name='password_reset_tokens')
    op.drop_index('ix_password_reset_tokens_user_id', table_name='password_reset_tokens')
    op.drop_index('ix_password_reset_tokens_token', table_name='password_reset_tokens')
    op.drop_table('password_reset_tokens')

    op.drop_index('ix_token_blacklist_expires_at', table_name='token_blacklist')
    op.drop_index('ix_token_blacklist_user_id', table_name='token_blacklist')
    op.drop_index('ix_token_blacklist_token', table_name='token_blacklist')
    op.drop_table('token_blacklist')

    op.drop_index('ix_users_is_active', table_name='users')
    op.drop_index('ix_users_role', table_name='users')
    op.drop_index('ix_users_email', table_name='users')
    op.drop_table('users')

    # Drop enum type
    user_role_enum = postgresql.ENUM(name='user_role_enum')
    user_role_enum.drop(op.get_bind())
