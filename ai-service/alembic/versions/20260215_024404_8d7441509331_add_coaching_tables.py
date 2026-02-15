"""add_coaching_tables

Revision ID: 8d7441509331
Revises:
Create Date: 2026-02-15 02:44:04.136594

"""
from typing import Sequence, Union

from alembic import op
import sqlalchemy as sa
from sqlalchemy.dialects import postgresql

# revision identifiers, used by Alembic.
revision: str = '8d7441509331'
down_revision: Union[str, None] = None
branch_labels: Union[str, Sequence[str], None] = None
depends_on: Union[str, Sequence[str], None] = None


def upgrade() -> None:
    # Create coaching_sessions table
    op.create_table(
        'coaching_sessions',
        sa.Column('id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('child_id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('user_id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('question', sa.Text(), nullable=False),
        sa.Column('context', sa.Text(), nullable=True),
        sa.Column('special_need_types', postgresql.ARRAY(sa.String(50)), nullable=True),
        sa.Column('category', sa.String(50), nullable=True),
        sa.Column('created_at', sa.DateTime(), nullable=False),
        sa.Column('updated_at', sa.DateTime(), nullable=True),
        sa.PrimaryKeyConstraint('id')
    )
    op.create_index('ix_coaching_sessions_child_id', 'coaching_sessions', ['child_id'], unique=False)
    op.create_index('ix_coaching_sessions_user_id', 'coaching_sessions', ['user_id'], unique=False)
    op.create_index('ix_coaching_sessions_child_created', 'coaching_sessions', ['child_id', 'created_at'], unique=False)
    op.create_index('ix_coaching_sessions_user_created', 'coaching_sessions', ['user_id', 'created_at'], unique=False)

    # Create coaching_recommendations table
    op.create_table(
        'coaching_recommendations',
        sa.Column('id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('session_id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('title', sa.String(200), nullable=False),
        sa.Column('content', sa.Text(), nullable=False),
        sa.Column('category', sa.String(50), nullable=False),
        sa.Column('priority', sa.String(20), nullable=False),
        sa.Column('relevance_score', sa.Float(), nullable=False),
        sa.Column('target_audience', sa.String(100), nullable=False),
        sa.Column('prerequisites', sa.Text(), nullable=True),
        sa.Column('created_at', sa.DateTime(), nullable=False),
        sa.PrimaryKeyConstraint('id'),
        sa.ForeignKeyConstraint(['session_id'], ['coaching_sessions.id'], ondelete='CASCADE')
    )
    op.create_index('ix_coaching_recommendations_session_id', 'coaching_recommendations', ['session_id'], unique=False)
    op.create_index('ix_coaching_recommendations_category', 'coaching_recommendations', ['category'], unique=False)
    op.create_index('ix_coaching_recommendations_priority', 'coaching_recommendations', ['priority'], unique=False)

    # Create evidence_sources table
    op.create_table(
        'evidence_sources',
        sa.Column('id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('recommendation_id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('source_type', sa.String(50), nullable=False),
        sa.Column('title', sa.String(500), nullable=False),
        sa.Column('authors', sa.Text(), nullable=True),
        sa.Column('publication', sa.String(200), nullable=True),
        sa.Column('year', sa.Integer(), nullable=True),
        sa.Column('doi', sa.String(100), nullable=True),
        sa.Column('url', sa.String(500), nullable=True),
        sa.Column('isbn', sa.String(20), nullable=True),
        sa.Column('accessed_at', sa.DateTime(), nullable=True),
        sa.Column('created_at', sa.DateTime(), nullable=False),
        sa.PrimaryKeyConstraint('id'),
        sa.ForeignKeyConstraint(['recommendation_id'], ['coaching_recommendations.id'], ondelete='CASCADE')
    )
    op.create_index('ix_evidence_sources_recommendation_id', 'evidence_sources', ['recommendation_id'], unique=False)
    op.create_index('ix_evidence_sources_source_type', 'evidence_sources', ['source_type'], unique=False)
    op.create_index('ix_evidence_sources_year', 'evidence_sources', ['year'], unique=False)


def downgrade() -> None:
    # Drop tables in reverse order (child tables first due to foreign keys)
    op.drop_index('ix_evidence_sources_year', table_name='evidence_sources')
    op.drop_index('ix_evidence_sources_source_type', table_name='evidence_sources')
    op.drop_index('ix_evidence_sources_recommendation_id', table_name='evidence_sources')
    op.drop_table('evidence_sources')

    op.drop_index('ix_coaching_recommendations_priority', table_name='coaching_recommendations')
    op.drop_index('ix_coaching_recommendations_category', table_name='coaching_recommendations')
    op.drop_index('ix_coaching_recommendations_session_id', table_name='coaching_recommendations')
    op.drop_table('coaching_recommendations')

    op.drop_index('ix_coaching_sessions_user_created', table_name='coaching_sessions')
    op.drop_index('ix_coaching_sessions_child_created', table_name='coaching_sessions')
    op.drop_index('ix_coaching_sessions_user_id', table_name='coaching_sessions')
    op.drop_index('ix_coaching_sessions_child_id', table_name='coaching_sessions')
    op.drop_table('coaching_sessions')
