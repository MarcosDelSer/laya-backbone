"""add_portfolio_tables

Revision ID: 20260216_portfolio
Revises:
Create Date: 2026-02-16

Creates the following tables for the educational portfolio system:
- portfolio_items: Media items (photos, videos, documents) in a child's portfolio
- observations: Educator observations about child development
- milestones: Developmental milestone tracking
- work_samples: Work sample documentation with learning context
"""
from typing import Sequence, Union

from alembic import op
import sqlalchemy as sa
from sqlalchemy.dialects import postgresql

# revision identifiers, used by Alembic.
revision: str = '20260216_portfolio'
down_revision: Union[str, None] = None
branch_labels: Union[str, Sequence[str], None] = None
depends_on: Union[str, Sequence[str], None] = None


def upgrade() -> None:
    # Create portfolio_item_type_enum
    portfolio_item_type_enum = postgresql.ENUM(
        'photo', 'video', 'document', 'audio', 'other',
        name='portfolio_item_type_enum',
        create_type=False
    )
    portfolio_item_type_enum.create(op.get_bind(), checkfirst=True)

    # Create privacy_level_enum
    privacy_level_enum = postgresql.ENUM(
        'private', 'family', 'shared',
        name='privacy_level_enum',
        create_type=False
    )
    privacy_level_enum.create(op.get_bind(), checkfirst=True)

    # Create observation_type_enum
    observation_type_enum = postgresql.ENUM(
        'anecdotal', 'running_record', 'learning_story', 'checklist', 'photo_documentation',
        name='observation_type_enum',
        create_type=False
    )
    observation_type_enum.create(op.get_bind(), checkfirst=True)

    # Create milestone_category_enum
    milestone_category_enum = postgresql.ENUM(
        'cognitive', 'motor_gross', 'motor_fine', 'language', 'social_emotional', 'self_care',
        name='milestone_category_enum',
        create_type=False
    )
    milestone_category_enum.create(op.get_bind(), checkfirst=True)

    # Create milestone_status_enum
    milestone_status_enum = postgresql.ENUM(
        'not_started', 'emerging', 'developing', 'achieved',
        name='milestone_status_enum',
        create_type=False
    )
    milestone_status_enum.create(op.get_bind(), checkfirst=True)

    # Create work_sample_type_enum
    work_sample_type_enum = postgresql.ENUM(
        'artwork', 'writing', 'construction', 'science', 'music', 'other',
        name='work_sample_type_enum',
        create_type=False
    )
    work_sample_type_enum.create(op.get_bind(), checkfirst=True)

    # Create portfolio_items table
    op.create_table(
        'portfolio_items',
        sa.Column('id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('child_id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('item_type', sa.Enum('photo', 'video', 'document', 'audio', 'other', name='portfolio_item_type_enum', create_constraint=True), nullable=False),
        sa.Column('title', sa.String(255), nullable=False),
        sa.Column('description', sa.Text(), nullable=True),
        sa.Column('media_url', sa.String(500), nullable=False),
        sa.Column('thumbnail_url', sa.String(500), nullable=True),
        sa.Column('privacy_level', sa.Enum('private', 'family', 'shared', name='privacy_level_enum', create_constraint=True), nullable=False),
        sa.Column('tags', postgresql.ARRAY(sa.String()), nullable=False, server_default='{}'),
        sa.Column('captured_at', sa.DateTime(timezone=True), nullable=False, server_default=sa.func.now()),
        sa.Column('captured_by_id', postgresql.UUID(as_uuid=True), nullable=True),
        sa.Column('is_family_contribution', sa.Boolean(), nullable=False, server_default='false'),
        sa.Column('item_metadata', postgresql.JSONB(), nullable=True),
        sa.Column('is_archived', sa.Boolean(), nullable=False, server_default='false'),
        sa.Column('created_at', sa.DateTime(timezone=True), nullable=False, server_default=sa.func.now()),
        sa.Column('updated_at', sa.DateTime(timezone=True), nullable=False, server_default=sa.func.now()),
        sa.PrimaryKeyConstraint('id')
    )
    op.create_index('ix_portfolio_items_child_id', 'portfolio_items', ['child_id'], unique=False)
    op.create_index('ix_portfolio_items_item_type', 'portfolio_items', ['item_type'], unique=False)
    op.create_index('ix_portfolio_items_privacy_level', 'portfolio_items', ['privacy_level'], unique=False)
    op.create_index('ix_portfolio_items_is_archived', 'portfolio_items', ['is_archived'], unique=False)
    op.create_index('ix_portfolio_items_child_created', 'portfolio_items', ['child_id', 'created_at'], unique=False)

    # Create observations table
    op.create_table(
        'observations',
        sa.Column('id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('child_id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('observer_id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('observation_type', sa.Enum('anecdotal', 'running_record', 'learning_story', 'checklist', 'photo_documentation', name='observation_type_enum', create_constraint=True), nullable=False),
        sa.Column('title', sa.String(255), nullable=False),
        sa.Column('content', sa.Text(), nullable=False),
        sa.Column('developmental_areas', postgresql.ARRAY(sa.String()), nullable=False, server_default='{}'),
        sa.Column('portfolio_item_id', postgresql.UUID(as_uuid=True), nullable=True),
        sa.Column('observation_date', sa.Date(), nullable=False),
        sa.Column('context', sa.String(255), nullable=True),
        sa.Column('is_shared_with_family', sa.Boolean(), nullable=False, server_default='true'),
        sa.Column('is_archived', sa.Boolean(), nullable=False, server_default='false'),
        sa.Column('created_at', sa.DateTime(timezone=True), nullable=False, server_default=sa.func.now()),
        sa.Column('updated_at', sa.DateTime(timezone=True), nullable=False, server_default=sa.func.now()),
        sa.PrimaryKeyConstraint('id'),
        sa.ForeignKeyConstraint(['portfolio_item_id'], ['portfolio_items.id'], ondelete='SET NULL')
    )
    op.create_index('ix_observations_child_id', 'observations', ['child_id'], unique=False)
    op.create_index('ix_observations_observer_id', 'observations', ['observer_id'], unique=False)
    op.create_index('ix_observations_observation_type', 'observations', ['observation_type'], unique=False)
    op.create_index('ix_observations_portfolio_item_id', 'observations', ['portfolio_item_id'], unique=False)
    op.create_index('ix_observations_is_archived', 'observations', ['is_archived'], unique=False)
    op.create_index('ix_observations_child_created', 'observations', ['child_id', 'created_at'], unique=False)

    # Create milestones table
    op.create_table(
        'milestones',
        sa.Column('id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('child_id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('category', sa.Enum('cognitive', 'motor_gross', 'motor_fine', 'language', 'social_emotional', 'self_care', name='milestone_category_enum', create_constraint=True), nullable=False),
        sa.Column('name', sa.String(255), nullable=False),
        sa.Column('expected_age_months', sa.Integer(), nullable=True),
        sa.Column('status', sa.Enum('not_started', 'emerging', 'developing', 'achieved', name='milestone_status_enum', create_constraint=True), nullable=False),
        sa.Column('first_observed_at', sa.Date(), nullable=True),
        sa.Column('achieved_at', sa.Date(), nullable=True),
        sa.Column('observation_id', postgresql.UUID(as_uuid=True), nullable=True),
        sa.Column('notes', sa.Text(), nullable=True),
        sa.Column('is_flagged', sa.Boolean(), nullable=False, server_default='false'),
        sa.Column('created_at', sa.DateTime(timezone=True), nullable=False, server_default=sa.func.now()),
        sa.Column('updated_at', sa.DateTime(timezone=True), nullable=False, server_default=sa.func.now()),
        sa.PrimaryKeyConstraint('id'),
        sa.ForeignKeyConstraint(['observation_id'], ['observations.id'], ondelete='SET NULL')
    )
    op.create_index('ix_milestones_child_id', 'milestones', ['child_id'], unique=False)
    op.create_index('ix_milestones_category', 'milestones', ['category'], unique=False)
    op.create_index('ix_milestones_status', 'milestones', ['status'], unique=False)
    op.create_index('ix_milestones_observation_id', 'milestones', ['observation_id'], unique=False)
    op.create_index('ix_milestones_is_flagged', 'milestones', ['is_flagged'], unique=False)
    op.create_index('ix_milestones_child_category', 'milestones', ['child_id', 'category'], unique=False)

    # Create work_samples table
    op.create_table(
        'work_samples',
        sa.Column('id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('child_id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('portfolio_item_id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('sample_type', sa.Enum('artwork', 'writing', 'construction', 'science', 'music', 'other', name='work_sample_type_enum', create_constraint=True), nullable=False),
        sa.Column('title', sa.String(255), nullable=False),
        sa.Column('description', sa.Text(), nullable=True),
        sa.Column('learning_objectives', postgresql.ARRAY(sa.String()), nullable=False, server_default='{}'),
        sa.Column('educator_notes', sa.Text(), nullable=True),
        sa.Column('child_reflection', sa.Text(), nullable=True),
        sa.Column('sample_date', sa.Date(), nullable=False),
        sa.Column('is_shared_with_family', sa.Boolean(), nullable=False, server_default='true'),
        sa.Column('created_at', sa.DateTime(timezone=True), nullable=False, server_default=sa.func.now()),
        sa.Column('updated_at', sa.DateTime(timezone=True), nullable=False, server_default=sa.func.now()),
        sa.PrimaryKeyConstraint('id'),
        sa.ForeignKeyConstraint(['portfolio_item_id'], ['portfolio_items.id'], ondelete='CASCADE')
    )
    op.create_index('ix_work_samples_child_id', 'work_samples', ['child_id'], unique=False)
    op.create_index('ix_work_samples_portfolio_item_id', 'work_samples', ['portfolio_item_id'], unique=False)
    op.create_index('ix_work_samples_sample_type', 'work_samples', ['sample_type'], unique=False)
    op.create_index('ix_work_samples_child_created', 'work_samples', ['child_id', 'created_at'], unique=False)


def downgrade() -> None:
    # Drop tables in reverse order (child tables first due to foreign keys)
    op.drop_index('ix_work_samples_child_created', table_name='work_samples')
    op.drop_index('ix_work_samples_sample_type', table_name='work_samples')
    op.drop_index('ix_work_samples_portfolio_item_id', table_name='work_samples')
    op.drop_index('ix_work_samples_child_id', table_name='work_samples')
    op.drop_table('work_samples')

    op.drop_index('ix_milestones_child_category', table_name='milestones')
    op.drop_index('ix_milestones_is_flagged', table_name='milestones')
    op.drop_index('ix_milestones_observation_id', table_name='milestones')
    op.drop_index('ix_milestones_status', table_name='milestones')
    op.drop_index('ix_milestones_category', table_name='milestones')
    op.drop_index('ix_milestones_child_id', table_name='milestones')
    op.drop_table('milestones')

    op.drop_index('ix_observations_child_created', table_name='observations')
    op.drop_index('ix_observations_is_archived', table_name='observations')
    op.drop_index('ix_observations_portfolio_item_id', table_name='observations')
    op.drop_index('ix_observations_observation_type', table_name='observations')
    op.drop_index('ix_observations_observer_id', table_name='observations')
    op.drop_index('ix_observations_child_id', table_name='observations')
    op.drop_table('observations')

    op.drop_index('ix_portfolio_items_child_created', table_name='portfolio_items')
    op.drop_index('ix_portfolio_items_is_archived', table_name='portfolio_items')
    op.drop_index('ix_portfolio_items_privacy_level', table_name='portfolio_items')
    op.drop_index('ix_portfolio_items_item_type', table_name='portfolio_items')
    op.drop_index('ix_portfolio_items_child_id', table_name='portfolio_items')
    op.drop_table('portfolio_items')

    # Drop enums
    op.execute('DROP TYPE IF EXISTS work_sample_type_enum')
    op.execute('DROP TYPE IF EXISTS milestone_status_enum')
    op.execute('DROP TYPE IF EXISTS milestone_category_enum')
    op.execute('DROP TYPE IF EXISTS observation_type_enum')
    op.execute('DROP TYPE IF EXISTS privacy_level_enum')
    op.execute('DROP TYPE IF EXISTS portfolio_item_type_enum')
