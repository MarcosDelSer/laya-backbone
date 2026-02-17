"""add_llm_tables

Revision ID: add_llm_tables
Revises: 8d7441509331
Create Date: 2026-02-16 12:00:00.000000

"""
from typing import Sequence, Union

from alembic import op
import sqlalchemy as sa
from sqlalchemy.dialects import postgresql

# revision identifiers, used by Alembic.
revision: str = 'add_llm_tables'
down_revision: Union[str, None] = '8d7441509331'
branch_labels: Union[str, Sequence[str], None] = None
depends_on: Union[str, Sequence[str], None] = None


def upgrade() -> None:
    # Create llm_usage_logs table for tracking LLM API usage
    op.create_table(
        'llm_usage_logs',
        sa.Column('id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('user_id', postgresql.UUID(as_uuid=True), nullable=True),
        sa.Column('session_id', postgresql.UUID(as_uuid=True), nullable=True),
        sa.Column('provider', sa.String(50), nullable=False),
        sa.Column('model', sa.String(100), nullable=False),
        sa.Column('prompt_tokens', sa.Integer(), nullable=False, server_default='0'),
        sa.Column('completion_tokens', sa.Integer(), nullable=False, server_default='0'),
        sa.Column('total_tokens', sa.Integer(), nullable=False, server_default='0'),
        sa.Column('cost_usd', sa.Float(), nullable=True),
        sa.Column('request_type', sa.String(50), nullable=False, server_default='completion'),
        sa.Column('success', sa.Boolean(), nullable=False, server_default='true'),
        sa.Column('error_message', sa.Text(), nullable=True),
        sa.Column('latency_ms', sa.Integer(), nullable=True),
        sa.Column('cached', sa.Boolean(), nullable=False, server_default='false'),
        sa.Column('created_at', sa.DateTime(), nullable=False),
        sa.PrimaryKeyConstraint('id')
    )
    # Column-level indexes for llm_usage_logs
    op.create_index('ix_llm_usage_logs_user_id', 'llm_usage_logs', ['user_id'], unique=False)
    op.create_index('ix_llm_usage_logs_session_id', 'llm_usage_logs', ['session_id'], unique=False)
    op.create_index('ix_llm_usage_logs_provider', 'llm_usage_logs', ['provider'], unique=False)
    op.create_index('ix_llm_usage_logs_model', 'llm_usage_logs', ['model'], unique=False)
    # Composite indexes for common query patterns
    op.create_index('ix_llm_usage_logs_provider_model', 'llm_usage_logs', ['provider', 'model'], unique=False)
    op.create_index('ix_llm_usage_logs_user_created', 'llm_usage_logs', ['user_id', 'created_at'], unique=False)
    op.create_index('ix_llm_usage_logs_created_at', 'llm_usage_logs', ['created_at'], unique=False)
    op.create_index('ix_llm_usage_logs_success', 'llm_usage_logs', ['success'], unique=False)

    # Create llm_cache_entries table for caching LLM responses
    op.create_table(
        'llm_cache_entries',
        sa.Column('id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('cache_key', sa.String(64), nullable=False),
        sa.Column('provider', sa.String(50), nullable=False),
        sa.Column('model', sa.String(100), nullable=False),
        sa.Column('prompt_hash', sa.String(64), nullable=False),
        sa.Column('response_content', sa.Text(), nullable=False),
        sa.Column('prompt_tokens', sa.Integer(), nullable=False, server_default='0'),
        sa.Column('completion_tokens', sa.Integer(), nullable=False, server_default='0'),
        sa.Column('hit_count', sa.Integer(), nullable=False, server_default='0'),
        sa.Column('expires_at', sa.DateTime(), nullable=False),
        sa.Column('created_at', sa.DateTime(), nullable=False),
        sa.Column('last_accessed_at', sa.DateTime(), nullable=True),
        sa.PrimaryKeyConstraint('id')
    )
    # Column-level indexes for llm_cache_entries
    op.create_index('ix_llm_cache_entries_cache_key', 'llm_cache_entries', ['cache_key'], unique=True)
    op.create_index('ix_llm_cache_entries_provider', 'llm_cache_entries', ['provider'], unique=False)
    op.create_index('ix_llm_cache_entries_model', 'llm_cache_entries', ['model'], unique=False)
    # Composite indexes for common query patterns
    op.create_index('ix_llm_cache_entries_provider_model', 'llm_cache_entries', ['provider', 'model'], unique=False)
    op.create_index('ix_llm_cache_entries_expires_at', 'llm_cache_entries', ['expires_at'], unique=False)
    op.create_index('ix_llm_cache_entries_created_at', 'llm_cache_entries', ['created_at'], unique=False)


def downgrade() -> None:
    # Drop tables in reverse order (cache entries first, then usage logs)
    op.drop_index('ix_llm_cache_entries_created_at', table_name='llm_cache_entries')
    op.drop_index('ix_llm_cache_entries_expires_at', table_name='llm_cache_entries')
    op.drop_index('ix_llm_cache_entries_provider_model', table_name='llm_cache_entries')
    op.drop_index('ix_llm_cache_entries_model', table_name='llm_cache_entries')
    op.drop_index('ix_llm_cache_entries_provider', table_name='llm_cache_entries')
    op.drop_index('ix_llm_cache_entries_cache_key', table_name='llm_cache_entries')
    op.drop_table('llm_cache_entries')

    op.drop_index('ix_llm_usage_logs_success', table_name='llm_usage_logs')
    op.drop_index('ix_llm_usage_logs_created_at', table_name='llm_usage_logs')
    op.drop_index('ix_llm_usage_logs_user_created', table_name='llm_usage_logs')
    op.drop_index('ix_llm_usage_logs_provider_model', table_name='llm_usage_logs')
    op.drop_index('ix_llm_usage_logs_model', table_name='llm_usage_logs')
    op.drop_index('ix_llm_usage_logs_provider', table_name='llm_usage_logs')
    op.drop_index('ix_llm_usage_logs_session_id', table_name='llm_usage_logs')
    op.drop_index('ix_llm_usage_logs_user_id', table_name='llm_usage_logs')
    op.drop_table('llm_usage_logs')
