"""add_storage_tables

Revision ID: 002
Revises: 001
Create Date: 2026-02-16 12:32:11

Creates storage tables for file upload and management:
- files: Stores file metadata for uploaded files
- file_thumbnails: Stores thumbnail metadata for image files
- storage_quotas: Tracks storage usage and quota limits per user
"""
from typing import Sequence, Union

from alembic import op
import sqlalchemy as sa
from sqlalchemy.dialects import postgresql

# revision identifiers, used by Alembic.
revision: str = '002'
down_revision: Union[str, None] = '001'
branch_labels: Union[str, Sequence[str], None] = None
depends_on: Union[str, Sequence[str], None] = None


def upgrade() -> None:
    # Create storage_backend_enum type
    storage_backend_enum = postgresql.ENUM(
        'local', 's3',
        name='storage_backend_enum',
        create_type=False
    )
    storage_backend_enum.create(op.get_bind(), checkfirst=True)

    # Create thumbnail_size_enum type
    thumbnail_size_enum = postgresql.ENUM(
        'small', 'medium', 'large',
        name='thumbnail_size_enum',
        create_type=False
    )
    thumbnail_size_enum.create(op.get_bind(), checkfirst=True)

    # Create files table
    op.create_table(
        'files',
        sa.Column('id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('owner_id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('filename', sa.String(255), nullable=False),
        sa.Column('original_filename', sa.String(255), nullable=False),
        sa.Column('content_type', sa.String(100), nullable=False),
        sa.Column('size_bytes', sa.BigInteger(), nullable=False),
        sa.Column(
            'storage_backend',
            sa.Enum('local', 's3', name='storage_backend_enum', create_constraint=True),
            nullable=False
        ),
        sa.Column('storage_path', sa.String(500), nullable=False),
        sa.Column('checksum', sa.String(64), nullable=True),
        sa.Column('is_public', sa.Boolean(), nullable=False, server_default='false'),
        sa.Column('description', sa.Text(), nullable=True),
        sa.Column('created_at', sa.DateTime(timezone=True), nullable=False, server_default=sa.func.now()),
        sa.Column('updated_at', sa.DateTime(timezone=True), nullable=False, server_default=sa.func.now()),
        sa.PrimaryKeyConstraint('id')
    )
    op.create_index('ix_files_owner_id', 'files', ['owner_id'], unique=False)
    op.create_index('ix_files_content_type', 'files', ['content_type'], unique=False)
    op.create_index('ix_files_storage_backend', 'files', ['storage_backend'], unique=False)
    op.create_index('ix_files_is_public', 'files', ['is_public'], unique=False)
    op.create_index('ix_files_created_at', 'files', ['created_at'], unique=False)
    op.create_index('ix_files_owner_created', 'files', ['owner_id', 'created_at'], unique=False)

    # Create file_thumbnails table
    op.create_table(
        'file_thumbnails',
        sa.Column('id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('file_id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column(
            'size',
            sa.Enum('small', 'medium', 'large', name='thumbnail_size_enum', create_constraint=True),
            nullable=False
        ),
        sa.Column('width', sa.Integer(), nullable=False),
        sa.Column('height', sa.Integer(), nullable=False),
        sa.Column('storage_path', sa.String(500), nullable=False),
        sa.Column('created_at', sa.DateTime(timezone=True), nullable=False, server_default=sa.func.now()),
        sa.PrimaryKeyConstraint('id'),
        sa.ForeignKeyConstraint(['file_id'], ['files.id'], ondelete='CASCADE')
    )
    op.create_index('ix_file_thumbnails_file_id', 'file_thumbnails', ['file_id'], unique=False)
    op.create_index('ix_file_thumbnails_file_size', 'file_thumbnails', ['file_id', 'size'], unique=True)

    # Create storage_quotas table
    op.create_table(
        'storage_quotas',
        sa.Column('id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('owner_id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('quota_bytes', sa.BigInteger(), nullable=False, server_default='104857600'),
        sa.Column('used_bytes', sa.BigInteger(), nullable=False, server_default='0'),
        sa.Column('file_count', sa.Integer(), nullable=False, server_default='0'),
        sa.Column('created_at', sa.DateTime(timezone=True), nullable=False, server_default=sa.func.now()),
        sa.Column('updated_at', sa.DateTime(timezone=True), nullable=False, server_default=sa.func.now()),
        sa.PrimaryKeyConstraint('id'),
        sa.UniqueConstraint('owner_id', name='uq_storage_quotas_owner_id')
    )
    op.create_index('ix_storage_quotas_owner_id', 'storage_quotas', ['owner_id'], unique=True)


def downgrade() -> None:
    # Drop tables in reverse order (child tables first due to foreign keys)
    op.drop_index('ix_storage_quotas_owner_id', table_name='storage_quotas')
    op.drop_table('storage_quotas')

    op.drop_index('ix_file_thumbnails_file_size', table_name='file_thumbnails')
    op.drop_index('ix_file_thumbnails_file_id', table_name='file_thumbnails')
    op.drop_table('file_thumbnails')

    op.drop_index('ix_files_owner_created', table_name='files')
    op.drop_index('ix_files_created_at', table_name='files')
    op.drop_index('ix_files_is_public', table_name='files')
    op.drop_index('ix_files_storage_backend', table_name='files')
    op.drop_index('ix_files_content_type', table_name='files')
    op.drop_index('ix_files_owner_id', table_name='files')
    op.drop_table('files')

    # Drop enum types
    op.execute('DROP TYPE IF EXISTS thumbnail_size_enum')
    op.execute('DROP TYPE IF EXISTS storage_backend_enum')
