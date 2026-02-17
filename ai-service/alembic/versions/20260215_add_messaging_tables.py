"""add_messaging_tables

Revision ID: add_messaging_tables
Revises: 8d7441509331
Create Date: 2026-02-15

Creates the following tables:
- message_threads: Stores conversation threads between parents and educators
- messages: Stores individual messages within threads
- message_attachments: Stores file attachments for messages
- notification_channels: Stores available notification channels (email, push, sms)
- notification_preferences: Stores parent notification preferences per type/channel
"""
from typing import Sequence, Union

from alembic import op
import sqlalchemy as sa
from sqlalchemy.dialects import postgresql

# revision identifiers, used by Alembic.
revision: str = 'add_messaging_tables'
down_revision: Union[str, None] = '8d7441509331'
branch_labels: Union[str, Sequence[str], None] = None
depends_on: Union[str, Sequence[str], None] = None


def upgrade() -> None:
    # Create message_threads table
    op.create_table(
        'message_threads',
        sa.Column('id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('subject', sa.String(255), nullable=False),
        sa.Column('thread_type', sa.String(50), nullable=False, server_default='daily_log'),
        sa.Column('child_id', postgresql.UUID(as_uuid=True), nullable=True),
        sa.Column('created_by', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('participants', postgresql.JSON(), nullable=True),
        sa.Column('is_active', sa.Boolean(), nullable=False, server_default='true'),
        sa.Column('created_at', sa.DateTime(), nullable=False),
        sa.Column('updated_at', sa.DateTime(), nullable=True),
        sa.PrimaryKeyConstraint('id')
    )
    op.create_index('ix_message_threads_child_id', 'message_threads', ['child_id'], unique=False)
    op.create_index('ix_message_threads_created_by', 'message_threads', ['created_by'], unique=False)
    op.create_index('ix_message_threads_child_type', 'message_threads', ['child_id', 'thread_type'], unique=False)

    # Create messages table
    op.create_table(
        'messages',
        sa.Column('id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('thread_id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('sender_id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('sender_type', sa.String(50), nullable=False),
        sa.Column('content', sa.Text(), nullable=False),
        sa.Column('content_type', sa.String(50), nullable=False, server_default='text'),
        sa.Column('is_read', sa.Boolean(), nullable=False, server_default='false'),
        sa.Column('created_at', sa.DateTime(), nullable=False),
        sa.Column('updated_at', sa.DateTime(), nullable=True),
        sa.PrimaryKeyConstraint('id'),
        sa.ForeignKeyConstraint(['thread_id'], ['message_threads.id'], ondelete='CASCADE')
    )
    op.create_index('ix_messages_thread_id', 'messages', ['thread_id'], unique=False)
    op.create_index('ix_messages_sender_id', 'messages', ['sender_id'], unique=False)
    op.create_index('ix_messages_thread_created', 'messages', ['thread_id', 'created_at'], unique=False)

    # Create message_attachments table
    op.create_table(
        'message_attachments',
        sa.Column('id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('message_id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('file_url', sa.String(500), nullable=False),
        sa.Column('file_type', sa.String(100), nullable=False),
        sa.Column('file_name', sa.String(255), nullable=False),
        sa.Column('file_size', sa.Integer(), nullable=True),
        sa.Column('created_at', sa.DateTime(), nullable=False),
        sa.PrimaryKeyConstraint('id'),
        sa.ForeignKeyConstraint(['message_id'], ['messages.id'], ondelete='CASCADE')
    )
    op.create_index('ix_message_attachments_message_id', 'message_attachments', ['message_id'], unique=False)

    # Create notification_channels table
    op.create_table(
        'notification_channels',
        sa.Column('id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('name', sa.String(50), nullable=False),
        sa.Column('display_name', sa.String(100), nullable=False),
        sa.Column('is_active', sa.Boolean(), nullable=False, server_default='true'),
        sa.Column('created_at', sa.DateTime(), nullable=False),
        sa.Column('updated_at', sa.DateTime(), nullable=True),
        sa.PrimaryKeyConstraint('id'),
        sa.UniqueConstraint('name')
    )

    # Create notification_preferences table
    op.create_table(
        'notification_preferences',
        sa.Column('id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('parent_id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('notification_type', sa.String(50), nullable=False),
        sa.Column('channel', sa.String(50), nullable=False),
        sa.Column('is_enabled', sa.Boolean(), nullable=False, server_default='true'),
        sa.Column('quiet_hours_start', sa.String(5), nullable=True),
        sa.Column('quiet_hours_end', sa.String(5), nullable=True),
        sa.Column('created_at', sa.DateTime(), nullable=False),
        sa.Column('updated_at', sa.DateTime(), nullable=True),
        sa.PrimaryKeyConstraint('id')
    )
    op.create_index('ix_notification_preferences_parent', 'notification_preferences', ['parent_id'], unique=False)
    op.create_index('ix_notification_preferences_parent_type', 'notification_preferences', ['parent_id', 'notification_type'], unique=False)


def downgrade() -> None:
    # Drop tables in reverse order (child tables first due to foreign keys)
    op.drop_index('ix_notification_preferences_parent_type', table_name='notification_preferences')
    op.drop_index('ix_notification_preferences_parent', table_name='notification_preferences')
    op.drop_table('notification_preferences')

    op.drop_table('notification_channels')

    op.drop_index('ix_message_attachments_message_id', table_name='message_attachments')
    op.drop_table('message_attachments')

    op.drop_index('ix_messages_thread_created', table_name='messages')
    op.drop_index('ix_messages_sender_id', table_name='messages')
    op.drop_index('ix_messages_thread_id', table_name='messages')
    op.drop_table('messages')

    op.drop_index('ix_message_threads_child_type', table_name='message_threads')
    op.drop_index('ix_message_threads_created_by', table_name='message_threads')
    op.drop_index('ix_message_threads_child_id', table_name='message_threads')
    op.drop_table('message_threads')
