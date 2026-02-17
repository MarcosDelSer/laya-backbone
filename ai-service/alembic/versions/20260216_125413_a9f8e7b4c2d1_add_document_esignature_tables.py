"""add_document_esignature_tables

Revision ID: a9f8e7b4c2d1
Revises: 001
Create Date: 2026-02-16 12:54:13.000000

"""
from typing import Sequence, Union

from alembic import op
import sqlalchemy as sa
from sqlalchemy.dialects import postgresql

# revision identifiers, used by Alembic.
revision: str = 'a9f8e7b4c2d1'
down_revision: Union[str, None] = '001'
branch_labels: Union[str, Sequence[str], None] = None
depends_on: Union[str, Sequence[str], None] = None


def upgrade() -> None:
    # Create enums first (before tables that use them)

    # DocumentType enum - used by documents and document_templates
    document_type_enum = postgresql.ENUM(
        'enrollment', 'permission', 'policy', 'medical', 'financial', 'other',
        name='document_type_enum',
        create_type=False
    )
    document_type_enum.create(op.get_bind(), checkfirst=True)

    # DocumentStatus enum - used by documents
    document_status_enum = postgresql.ENUM(
        'draft', 'pending', 'signed', 'expired',
        name='document_status_enum',
        create_type=False
    )
    document_status_enum.create(op.get_bind(), checkfirst=True)

    # SignatureRequestStatus enum - used by signature_requests
    signature_request_status_enum = postgresql.ENUM(
        'sent', 'viewed', 'completed', 'cancelled', 'expired',
        name='signature_request_status_enum',
        create_type=False
    )
    signature_request_status_enum.create(op.get_bind(), checkfirst=True)

    # DocumentAuditEventType enum - used by document_audit_logs
    document_audit_event_type_enum = postgresql.ENUM(
        'document_created', 'document_updated', 'document_status_changed',
        'signature_request_sent', 'signature_request_viewed', 'signature_request_completed',
        'signature_created', 'template_used',
        name='document_audit_event_type_enum',
        create_type=False
    )
    document_audit_event_type_enum.create(op.get_bind(), checkfirst=True)

    # Create documents table
    op.create_table(
        'documents',
        sa.Column('id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('type', postgresql.ENUM(
            'enrollment', 'permission', 'policy', 'medical', 'financial', 'other',
            name='document_type_enum', create_type=False
        ), nullable=False),
        sa.Column('title', sa.String(255), nullable=False),
        sa.Column('content_url', sa.Text(), nullable=False),
        sa.Column('status', postgresql.ENUM(
            'draft', 'pending', 'signed', 'expired',
            name='document_status_enum', create_type=False
        ), nullable=False),
        sa.Column('created_by', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('created_at', sa.DateTime(timezone=True), server_default=sa.text('now()'), nullable=False),
        sa.Column('updated_at', sa.DateTime(timezone=True), server_default=sa.text('now()'), nullable=False),
        sa.PrimaryKeyConstraint('id')
    )
    op.create_index('ix_documents_type', 'documents', ['type'], unique=False)
    op.create_index('ix_documents_title', 'documents', ['title'], unique=False)
    op.create_index('ix_documents_status', 'documents', ['status'], unique=False)
    op.create_index('ix_documents_created_by', 'documents', ['created_by'], unique=False)

    # Create document_templates table
    op.create_table(
        'document_templates',
        sa.Column('id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('name', sa.String(255), nullable=False),
        sa.Column('type', postgresql.ENUM(
            'enrollment', 'permission', 'policy', 'medical', 'financial', 'other',
            name='document_type_enum', create_type=False
        ), nullable=False),
        sa.Column('description', sa.Text(), nullable=True),
        sa.Column('template_content', sa.Text(), nullable=False, comment='JSON structure or HTML content of the template'),
        sa.Column('required_fields', sa.Text(), nullable=True, comment='JSON array of required field names'),
        sa.Column('is_active', sa.Boolean(), nullable=False, server_default=sa.text('true')),
        sa.Column('version', sa.Integer(), nullable=False, server_default=sa.text('1')),
        sa.Column('created_by', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('created_at', sa.DateTime(timezone=True), server_default=sa.text('now()'), nullable=False),
        sa.Column('updated_at', sa.DateTime(timezone=True), server_default=sa.text('now()'), nullable=False),
        sa.PrimaryKeyConstraint('id')
    )
    op.create_index('ix_document_templates_name', 'document_templates', ['name'], unique=False)
    op.create_index('ix_document_templates_type', 'document_templates', ['type'], unique=False)
    op.create_index('ix_document_templates_is_active', 'document_templates', ['is_active'], unique=False)
    op.create_index('ix_document_templates_created_by', 'document_templates', ['created_by'], unique=False)

    # Create signatures table (depends on documents)
    op.create_table(
        'signatures',
        sa.Column('id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('document_id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('signer_id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('signature_image_url', sa.Text(), nullable=False),
        sa.Column('ip_address', sa.String(45), nullable=False),
        sa.Column('timestamp', sa.DateTime(timezone=True), server_default=sa.text('now()'), nullable=False),
        sa.Column('device_info', sa.Text(), nullable=True),
        sa.Column('created_at', sa.DateTime(timezone=True), server_default=sa.text('now()'), nullable=False),
        sa.Column('updated_at', sa.DateTime(timezone=True), server_default=sa.text('now()'), nullable=False),
        sa.PrimaryKeyConstraint('id'),
        sa.ForeignKeyConstraint(['document_id'], ['documents.id'], ondelete='CASCADE')
    )
    op.create_index('ix_signatures_document_id', 'signatures', ['document_id'], unique=False)
    op.create_index('ix_signatures_signer_id', 'signatures', ['signer_id'], unique=False)
    op.create_index('ix_signatures_timestamp', 'signatures', ['timestamp'], unique=False)

    # Create signature_requests table (depends on documents)
    op.create_table(
        'signature_requests',
        sa.Column('id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('document_id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('requester_id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('signer_id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('status', postgresql.ENUM(
            'sent', 'viewed', 'completed', 'cancelled', 'expired',
            name='signature_request_status_enum', create_type=False
        ), nullable=False),
        sa.Column('sent_at', sa.DateTime(timezone=True), server_default=sa.text('now()'), nullable=False),
        sa.Column('viewed_at', sa.DateTime(timezone=True), nullable=True),
        sa.Column('completed_at', sa.DateTime(timezone=True), nullable=True),
        sa.Column('expires_at', sa.DateTime(timezone=True), nullable=True),
        sa.Column('notification_sent', sa.Boolean(), nullable=False, server_default=sa.text('false')),
        sa.Column('notification_method', sa.String(50), nullable=True),
        sa.Column('message', sa.Text(), nullable=True),
        sa.Column('created_at', sa.DateTime(timezone=True), server_default=sa.text('now()'), nullable=False),
        sa.Column('updated_at', sa.DateTime(timezone=True), server_default=sa.text('now()'), nullable=False),
        sa.PrimaryKeyConstraint('id'),
        sa.ForeignKeyConstraint(['document_id'], ['documents.id'], ondelete='CASCADE')
    )
    op.create_index('ix_signature_requests_document_id', 'signature_requests', ['document_id'], unique=False)
    op.create_index('ix_signature_requests_requester_id', 'signature_requests', ['requester_id'], unique=False)
    op.create_index('ix_signature_requests_signer_id', 'signature_requests', ['signer_id'], unique=False)
    op.create_index('ix_signature_requests_status', 'signature_requests', ['status'], unique=False)
    op.create_index('ix_signature_requests_sent_at', 'signature_requests', ['sent_at'], unique=False)
    op.create_index('ix_signature_requests_expires_at', 'signature_requests', ['expires_at'], unique=False)

    # Create document_audit_logs table (depends on documents, signatures, signature_requests)
    op.create_table(
        'document_audit_logs',
        sa.Column('id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('event_type', postgresql.ENUM(
            'document_created', 'document_updated', 'document_status_changed',
            'signature_request_sent', 'signature_request_viewed', 'signature_request_completed',
            'signature_created', 'template_used',
            name='document_audit_event_type_enum', create_type=False
        ), nullable=False),
        sa.Column('document_id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('user_id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('signature_id', postgresql.UUID(as_uuid=True), nullable=True),
        sa.Column('signature_request_id', postgresql.UUID(as_uuid=True), nullable=True),
        sa.Column('event_data', sa.Text(), nullable=True, comment='JSON object with event-specific details'),
        sa.Column('ip_address', sa.String(45), nullable=True),
        sa.Column('user_agent', sa.Text(), nullable=True),
        sa.Column('timestamp', sa.DateTime(timezone=True), server_default=sa.text('now()'), nullable=False),
        sa.Column('created_at', sa.DateTime(timezone=True), server_default=sa.text('now()'), nullable=False),
        sa.PrimaryKeyConstraint('id'),
        sa.ForeignKeyConstraint(['document_id'], ['documents.id'], ondelete='CASCADE'),
        sa.ForeignKeyConstraint(['signature_id'], ['signatures.id'], ondelete='SET NULL'),
        sa.ForeignKeyConstraint(['signature_request_id'], ['signature_requests.id'], ondelete='SET NULL')
    )
    op.create_index('ix_document_audit_logs_event_type', 'document_audit_logs', ['event_type'], unique=False)
    op.create_index('ix_document_audit_logs_document_id', 'document_audit_logs', ['document_id'], unique=False)
    op.create_index('ix_document_audit_logs_user_id', 'document_audit_logs', ['user_id'], unique=False)
    op.create_index('ix_document_audit_logs_signature_id', 'document_audit_logs', ['signature_id'], unique=False)
    op.create_index('ix_document_audit_logs_signature_request_id', 'document_audit_logs', ['signature_request_id'], unique=False)
    op.create_index('ix_document_audit_logs_timestamp', 'document_audit_logs', ['timestamp'], unique=False)


def downgrade() -> None:
    # Drop tables in reverse order (child tables first due to foreign keys)
    op.drop_index('ix_document_audit_logs_timestamp', table_name='document_audit_logs')
    op.drop_index('ix_document_audit_logs_signature_request_id', table_name='document_audit_logs')
    op.drop_index('ix_document_audit_logs_signature_id', table_name='document_audit_logs')
    op.drop_index('ix_document_audit_logs_user_id', table_name='document_audit_logs')
    op.drop_index('ix_document_audit_logs_document_id', table_name='document_audit_logs')
    op.drop_index('ix_document_audit_logs_event_type', table_name='document_audit_logs')
    op.drop_table('document_audit_logs')

    op.drop_index('ix_signature_requests_expires_at', table_name='signature_requests')
    op.drop_index('ix_signature_requests_sent_at', table_name='signature_requests')
    op.drop_index('ix_signature_requests_status', table_name='signature_requests')
    op.drop_index('ix_signature_requests_signer_id', table_name='signature_requests')
    op.drop_index('ix_signature_requests_requester_id', table_name='signature_requests')
    op.drop_index('ix_signature_requests_document_id', table_name='signature_requests')
    op.drop_table('signature_requests')

    op.drop_index('ix_signatures_timestamp', table_name='signatures')
    op.drop_index('ix_signatures_signer_id', table_name='signatures')
    op.drop_index('ix_signatures_document_id', table_name='signatures')
    op.drop_table('signatures')

    op.drop_index('ix_document_templates_created_by', table_name='document_templates')
    op.drop_index('ix_document_templates_is_active', table_name='document_templates')
    op.drop_index('ix_document_templates_type', table_name='document_templates')
    op.drop_index('ix_document_templates_name', table_name='document_templates')
    op.drop_table('document_templates')

    op.drop_index('ix_documents_created_by', table_name='documents')
    op.drop_index('ix_documents_status', table_name='documents')
    op.drop_index('ix_documents_title', table_name='documents')
    op.drop_index('ix_documents_type', table_name='documents')
    op.drop_table('documents')

    # Drop enums last (after all tables that use them)
    document_audit_event_type_enum = postgresql.ENUM(name='document_audit_event_type_enum')
    document_audit_event_type_enum.drop(op.get_bind(), checkfirst=True)

    signature_request_status_enum = postgresql.ENUM(name='signature_request_status_enum')
    signature_request_status_enum.drop(op.get_bind(), checkfirst=True)

    document_status_enum = postgresql.ENUM(name='document_status_enum')
    document_status_enum.drop(op.get_bind(), checkfirst=True)

    document_type_enum = postgresql.ENUM(name='document_type_enum')
    document_type_enum.drop(op.get_bind(), checkfirst=True)
