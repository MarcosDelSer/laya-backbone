"""add_mfa_tables

Revision ID: 20260215_mfa001
Revises: 8d7441509331
Create Date: 2026-02-15 16:00:00.000000

Creates the following tables:
- mfa_settings: Stores user MFA configuration and TOTP secrets
- mfa_backup_codes: Stores hashed backup codes for account recovery
- mfa_ip_whitelist: Stores IP addresses allowed to bypass MFA
"""
from typing import Sequence, Union

from alembic import op
import sqlalchemy as sa
from sqlalchemy.dialects import postgresql

# revision identifiers, used by Alembic.
revision: str = '20260215_mfa001'
down_revision: Union[str, None] = '8d7441509331'
branch_labels: Union[str, Sequence[str], None] = None
depends_on: Union[str, Sequence[str], None] = None


def upgrade() -> None:
    # Create MFA method enum type
    mfa_method_enum = postgresql.ENUM(
        'totp', 'sms', 'email',
        name='mfa_method_enum',
        create_type=True
    )
    mfa_method_enum.create(op.get_bind(), checkfirst=True)

    # Create mfa_settings table
    op.create_table(
        'mfa_settings',
        sa.Column('id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('user_id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('is_enabled', sa.Boolean(), nullable=False, server_default='false'),
        sa.Column('method', mfa_method_enum, nullable=False, server_default='totp'),
        sa.Column('secret_key', sa.String(255), nullable=True),
        sa.Column('recovery_email', sa.String(255), nullable=True),
        sa.Column('last_verified_at', sa.DateTime(timezone=True), nullable=True),
        sa.Column('failed_attempts', sa.Integer(), nullable=False, server_default='0'),
        sa.Column('locked_until', sa.DateTime(timezone=True), nullable=True),
        sa.Column('created_at', sa.DateTime(timezone=True), nullable=False, server_default=sa.func.now()),
        sa.Column('updated_at', sa.DateTime(timezone=True), nullable=False, server_default=sa.func.now()),
        sa.PrimaryKeyConstraint('id'),
        sa.UniqueConstraint('user_id', name='uq_mfa_settings_user_id')
    )
    op.create_index('ix_mfa_settings_user_id', 'mfa_settings', ['user_id'], unique=True)
    op.create_index('ix_mfa_settings_is_enabled', 'mfa_settings', ['is_enabled'], unique=False)

    # Create mfa_backup_codes table
    op.create_table(
        'mfa_backup_codes',
        sa.Column('id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('mfa_settings_id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('code_hash', sa.String(255), nullable=False),
        sa.Column('is_used', sa.Boolean(), nullable=False, server_default='false'),
        sa.Column('used_at', sa.DateTime(timezone=True), nullable=True),
        sa.Column('created_at', sa.DateTime(timezone=True), nullable=False, server_default=sa.func.now()),
        sa.PrimaryKeyConstraint('id'),
        sa.ForeignKeyConstraint(['mfa_settings_id'], ['mfa_settings.id'], ondelete='CASCADE')
    )
    op.create_index('ix_mfa_backup_codes_mfa_settings_id', 'mfa_backup_codes', ['mfa_settings_id'], unique=False)
    op.create_index('ix_mfa_backup_codes_is_used', 'mfa_backup_codes', ['is_used'], unique=False)

    # Create mfa_ip_whitelist table
    op.create_table(
        'mfa_ip_whitelist',
        sa.Column('id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('mfa_settings_id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('ip_address', sa.String(45), nullable=False),
        sa.Column('description', sa.Text(), nullable=True),
        sa.Column('is_active', sa.Boolean(), nullable=False, server_default='true'),
        sa.Column('created_at', sa.DateTime(timezone=True), nullable=False, server_default=sa.func.now()),
        sa.Column('updated_at', sa.DateTime(timezone=True), nullable=False, server_default=sa.func.now()),
        sa.PrimaryKeyConstraint('id'),
        sa.ForeignKeyConstraint(['mfa_settings_id'], ['mfa_settings.id'], ondelete='CASCADE')
    )
    op.create_index('ix_mfa_ip_whitelist_mfa_settings_id', 'mfa_ip_whitelist', ['mfa_settings_id'], unique=False)
    op.create_index('ix_mfa_ip_whitelist_is_active', 'mfa_ip_whitelist', ['is_active'], unique=False)


def downgrade() -> None:
    # Drop tables in reverse order (child tables first due to foreign keys)
    op.drop_index('ix_mfa_ip_whitelist_is_active', table_name='mfa_ip_whitelist')
    op.drop_index('ix_mfa_ip_whitelist_mfa_settings_id', table_name='mfa_ip_whitelist')
    op.drop_table('mfa_ip_whitelist')

    op.drop_index('ix_mfa_backup_codes_is_used', table_name='mfa_backup_codes')
    op.drop_index('ix_mfa_backup_codes_mfa_settings_id', table_name='mfa_backup_codes')
    op.drop_table('mfa_backup_codes')

    op.drop_index('ix_mfa_settings_is_enabled', table_name='mfa_settings')
    op.drop_index('ix_mfa_settings_user_id', table_name='mfa_settings')
    op.drop_constraint('uq_mfa_settings_user_id', 'mfa_settings', type_='unique')
    op.drop_table('mfa_settings')

    # Drop the enum type
    mfa_method_enum = postgresql.ENUM(name='mfa_method_enum')
    mfa_method_enum.drop(op.get_bind(), checkfirst=True)
