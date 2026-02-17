"""add_intervention_plan_tables

Revision ID: b3e5a7c92046
Revises: 8d7441509331
Create Date: 2026-02-16 12:00:00.000000

Creates the following tables for the 8-part intervention plan structure:
- intervention_plans: Main plan with identification/history (Part 1) and versioning
- intervention_strengths: Part 2 - Child's strengths
- intervention_needs: Part 3 - Child's needs with priority
- intervention_goals: Part 4 - SMART goals
- intervention_strategies: Part 5 - Intervention strategies
- intervention_monitoring: Part 6 - Monitoring approaches
- intervention_parent_involvements: Part 7 - Parent involvement activities
- intervention_consultations: Part 8 - Specialist consultations
- intervention_progress: Progress tracking records
- intervention_versions: Version history snapshots
"""
from typing import Sequence, Union

from alembic import op
import sqlalchemy as sa
from sqlalchemy.dialects import postgresql

# revision identifiers, used by Alembic.
revision: str = 'b3e5a7c92046'
down_revision: Union[str, None] = '8d7441509331'
branch_labels: Union[str, Sequence[str], None] = None
depends_on: Union[str, Sequence[str], None] = None


def upgrade() -> None:
    # Create intervention_plans table (Part 1 - Identification & History + Versioning)
    op.create_table(
        'intervention_plans',
        sa.Column('id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('child_id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('created_by', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('title', sa.String(200), nullable=False),
        sa.Column('status', sa.String(20), nullable=False),
        sa.Column('version', sa.Integer(), nullable=False),
        sa.Column('parent_version_id', postgresql.UUID(as_uuid=True), nullable=True),
        # Part 1 - Identification & History
        sa.Column('child_name', sa.String(200), nullable=False),
        sa.Column('date_of_birth', sa.Date(), nullable=True),
        sa.Column('diagnosis', postgresql.ARRAY(sa.String(100)), nullable=True),
        sa.Column('medical_history', sa.Text(), nullable=True),
        sa.Column('educational_history', sa.Text(), nullable=True),
        sa.Column('family_context', sa.Text(), nullable=True),
        # Review and scheduling
        sa.Column('review_schedule', sa.String(20), nullable=False),
        sa.Column('next_review_date', sa.Date(), nullable=True),
        # Parent signature
        sa.Column('parent_signed', sa.Boolean(), nullable=False),
        sa.Column('parent_signature_date', sa.DateTime(), nullable=True),
        sa.Column('parent_signature_data', sa.Text(), nullable=True),
        # Dates
        sa.Column('effective_date', sa.Date(), nullable=True),
        sa.Column('end_date', sa.Date(), nullable=True),
        sa.Column('created_at', sa.DateTime(), nullable=False),
        sa.Column('updated_at', sa.DateTime(), nullable=True),
        sa.PrimaryKeyConstraint('id'),
        sa.ForeignKeyConstraint(['parent_version_id'], ['intervention_plans.id'], ondelete='SET NULL')
    )
    op.create_index('ix_intervention_plans_child_id', 'intervention_plans', ['child_id'], unique=False)
    op.create_index('ix_intervention_plans_created_by', 'intervention_plans', ['created_by'], unique=False)
    op.create_index('ix_intervention_plans_child_status', 'intervention_plans', ['child_id', 'status'], unique=False)
    op.create_index('ix_intervention_plans_review_date', 'intervention_plans', ['next_review_date'], unique=False)
    op.create_index('ix_intervention_plans_created_by_created', 'intervention_plans', ['created_by', 'created_at'], unique=False)

    # Create intervention_strengths table (Part 2)
    op.create_table(
        'intervention_strengths',
        sa.Column('id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('plan_id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('category', sa.String(50), nullable=False),
        sa.Column('description', sa.Text(), nullable=False),
        sa.Column('examples', sa.Text(), nullable=True),
        sa.Column('order', sa.Integer(), nullable=False),
        sa.Column('created_at', sa.DateTime(), nullable=False),
        sa.PrimaryKeyConstraint('id'),
        sa.ForeignKeyConstraint(['plan_id'], ['intervention_plans.id'], ondelete='CASCADE')
    )
    op.create_index('ix_intervention_strengths_plan_id', 'intervention_strengths', ['plan_id'], unique=False)
    op.create_index('ix_intervention_strengths_category', 'intervention_strengths', ['category'], unique=False)

    # Create intervention_needs table (Part 3)
    op.create_table(
        'intervention_needs',
        sa.Column('id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('plan_id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('category', sa.String(50), nullable=False),
        sa.Column('description', sa.Text(), nullable=False),
        sa.Column('priority', sa.String(20), nullable=False),
        sa.Column('baseline', sa.Text(), nullable=True),
        sa.Column('order', sa.Integer(), nullable=False),
        sa.Column('created_at', sa.DateTime(), nullable=False),
        sa.PrimaryKeyConstraint('id'),
        sa.ForeignKeyConstraint(['plan_id'], ['intervention_plans.id'], ondelete='CASCADE')
    )
    op.create_index('ix_intervention_needs_plan_id', 'intervention_needs', ['plan_id'], unique=False)
    op.create_index('ix_intervention_needs_category', 'intervention_needs', ['category'], unique=False)
    op.create_index('ix_intervention_needs_priority', 'intervention_needs', ['priority'], unique=False)

    # Create intervention_goals table (Part 4 - SMART Goals)
    op.create_table(
        'intervention_goals',
        sa.Column('id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('plan_id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('need_id', postgresql.UUID(as_uuid=True), nullable=True),
        sa.Column('title', sa.String(200), nullable=False),
        # SMART components
        sa.Column('description', sa.Text(), nullable=False),
        sa.Column('measurement_criteria', sa.Text(), nullable=False),
        sa.Column('measurement_baseline', sa.String(100), nullable=True),
        sa.Column('measurement_target', sa.String(100), nullable=True),
        sa.Column('achievability_notes', sa.Text(), nullable=True),
        sa.Column('relevance_notes', sa.Text(), nullable=True),
        sa.Column('target_date', sa.Date(), nullable=True),
        # Status and progress
        sa.Column('status', sa.String(20), nullable=False),
        sa.Column('progress_percentage', sa.Float(), nullable=False),
        sa.Column('order', sa.Integer(), nullable=False),
        sa.Column('created_at', sa.DateTime(), nullable=False),
        sa.Column('updated_at', sa.DateTime(), nullable=True),
        sa.PrimaryKeyConstraint('id'),
        sa.ForeignKeyConstraint(['plan_id'], ['intervention_plans.id'], ondelete='CASCADE'),
        sa.ForeignKeyConstraint(['need_id'], ['intervention_needs.id'], ondelete='SET NULL')
    )
    op.create_index('ix_intervention_goals_plan_id', 'intervention_goals', ['plan_id'], unique=False)
    op.create_index('ix_intervention_goals_status', 'intervention_goals', ['status'], unique=False)
    op.create_index('ix_intervention_goals_target_date', 'intervention_goals', ['target_date'], unique=False)

    # Create intervention_strategies table (Part 5)
    op.create_table(
        'intervention_strategies',
        sa.Column('id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('plan_id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('goal_id', postgresql.UUID(as_uuid=True), nullable=True),
        sa.Column('title', sa.String(200), nullable=False),
        sa.Column('description', sa.Text(), nullable=False),
        sa.Column('responsible_party', sa.String(50), nullable=False),
        sa.Column('frequency', sa.String(100), nullable=True),
        sa.Column('materials_needed', sa.Text(), nullable=True),
        sa.Column('accommodations', sa.Text(), nullable=True),
        sa.Column('order', sa.Integer(), nullable=False),
        sa.Column('created_at', sa.DateTime(), nullable=False),
        sa.PrimaryKeyConstraint('id'),
        sa.ForeignKeyConstraint(['plan_id'], ['intervention_plans.id'], ondelete='CASCADE'),
        sa.ForeignKeyConstraint(['goal_id'], ['intervention_goals.id'], ondelete='SET NULL')
    )
    op.create_index('ix_intervention_strategies_plan_id', 'intervention_strategies', ['plan_id'], unique=False)
    op.create_index('ix_intervention_strategies_responsible', 'intervention_strategies', ['responsible_party'], unique=False)

    # Create intervention_monitoring table (Part 6)
    op.create_table(
        'intervention_monitoring',
        sa.Column('id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('plan_id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('goal_id', postgresql.UUID(as_uuid=True), nullable=True),
        sa.Column('method', sa.String(50), nullable=False),
        sa.Column('description', sa.Text(), nullable=False),
        sa.Column('frequency', sa.String(50), nullable=False),
        sa.Column('responsible_party', sa.String(50), nullable=False),
        sa.Column('data_collection_tools', sa.Text(), nullable=True),
        sa.Column('success_indicators', sa.Text(), nullable=True),
        sa.Column('order', sa.Integer(), nullable=False),
        sa.Column('created_at', sa.DateTime(), nullable=False),
        sa.PrimaryKeyConstraint('id'),
        sa.ForeignKeyConstraint(['plan_id'], ['intervention_plans.id'], ondelete='CASCADE'),
        sa.ForeignKeyConstraint(['goal_id'], ['intervention_goals.id'], ondelete='SET NULL')
    )
    op.create_index('ix_intervention_monitoring_plan_id', 'intervention_monitoring', ['plan_id'], unique=False)
    op.create_index('ix_intervention_monitoring_method', 'intervention_monitoring', ['method'], unique=False)

    # Create intervention_parent_involvements table (Part 7)
    op.create_table(
        'intervention_parent_involvements',
        sa.Column('id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('plan_id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('activity_type', sa.String(50), nullable=False),
        sa.Column('title', sa.String(200), nullable=False),
        sa.Column('description', sa.Text(), nullable=False),
        sa.Column('frequency', sa.String(50), nullable=True),
        sa.Column('resources_provided', sa.Text(), nullable=True),
        sa.Column('communication_method', sa.String(100), nullable=True),
        sa.Column('order', sa.Integer(), nullable=False),
        sa.Column('created_at', sa.DateTime(), nullable=False),
        sa.PrimaryKeyConstraint('id'),
        sa.ForeignKeyConstraint(['plan_id'], ['intervention_plans.id'], ondelete='CASCADE')
    )
    op.create_index('ix_intervention_parent_involvements_plan_id', 'intervention_parent_involvements', ['plan_id'], unique=False)
    op.create_index('ix_intervention_parent_involvements_type', 'intervention_parent_involvements', ['activity_type'], unique=False)

    # Create intervention_consultations table (Part 8)
    op.create_table(
        'intervention_consultations',
        sa.Column('id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('plan_id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('specialist_type', sa.String(50), nullable=False),
        sa.Column('specialist_name', sa.String(200), nullable=True),
        sa.Column('organization', sa.String(200), nullable=True),
        sa.Column('purpose', sa.Text(), nullable=False),
        sa.Column('recommendations', sa.Text(), nullable=True),
        sa.Column('consultation_date', sa.Date(), nullable=True),
        sa.Column('next_consultation_date', sa.Date(), nullable=True),
        sa.Column('notes', sa.Text(), nullable=True),
        sa.Column('order', sa.Integer(), nullable=False),
        sa.Column('created_at', sa.DateTime(), nullable=False),
        sa.PrimaryKeyConstraint('id'),
        sa.ForeignKeyConstraint(['plan_id'], ['intervention_plans.id'], ondelete='CASCADE')
    )
    op.create_index('ix_intervention_consultations_plan_id', 'intervention_consultations', ['plan_id'], unique=False)
    op.create_index('ix_intervention_consultations_type', 'intervention_consultations', ['specialist_type'], unique=False)
    op.create_index('ix_intervention_consultations_date', 'intervention_consultations', ['consultation_date'], unique=False)

    # Create intervention_progress table (progress tracking)
    op.create_table(
        'intervention_progress',
        sa.Column('id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('plan_id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('goal_id', postgresql.UUID(as_uuid=True), nullable=True),
        sa.Column('recorded_by', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('record_date', sa.Date(), nullable=False),
        sa.Column('progress_notes', sa.Text(), nullable=False),
        sa.Column('progress_level', sa.String(20), nullable=False),
        sa.Column('measurement_value', sa.String(100), nullable=True),
        sa.Column('barriers', sa.Text(), nullable=True),
        sa.Column('next_steps', sa.Text(), nullable=True),
        sa.Column('attachments', postgresql.JSONB(), nullable=True),
        sa.Column('created_at', sa.DateTime(), nullable=False),
        sa.PrimaryKeyConstraint('id'),
        sa.ForeignKeyConstraint(['plan_id'], ['intervention_plans.id'], ondelete='CASCADE'),
        sa.ForeignKeyConstraint(['goal_id'], ['intervention_goals.id'], ondelete='SET NULL')
    )
    op.create_index('ix_intervention_progress_plan_id', 'intervention_progress', ['plan_id'], unique=False)
    op.create_index('ix_intervention_progress_date', 'intervention_progress', ['record_date'], unique=False)
    op.create_index('ix_intervention_progress_plan_date', 'intervention_progress', ['plan_id', 'record_date'], unique=False)

    # Create intervention_versions table (version history)
    op.create_table(
        'intervention_versions',
        sa.Column('id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('plan_id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('version_number', sa.Integer(), nullable=False),
        sa.Column('created_by', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('change_summary', sa.Text(), nullable=True),
        sa.Column('snapshot_data', postgresql.JSONB(), nullable=True),
        sa.Column('created_at', sa.DateTime(), nullable=False),
        sa.PrimaryKeyConstraint('id'),
        sa.ForeignKeyConstraint(['plan_id'], ['intervention_plans.id'], ondelete='CASCADE')
    )
    op.create_index('ix_intervention_versions_plan_id', 'intervention_versions', ['plan_id'], unique=False)
    op.create_index('ix_intervention_versions_plan_number', 'intervention_versions', ['plan_id', 'version_number'], unique=False)


def downgrade() -> None:
    # Drop tables in reverse order (child tables first due to foreign keys)
    op.drop_index('ix_intervention_versions_plan_number', table_name='intervention_versions')
    op.drop_index('ix_intervention_versions_plan_id', table_name='intervention_versions')
    op.drop_table('intervention_versions')

    op.drop_index('ix_intervention_progress_plan_date', table_name='intervention_progress')
    op.drop_index('ix_intervention_progress_date', table_name='intervention_progress')
    op.drop_index('ix_intervention_progress_plan_id', table_name='intervention_progress')
    op.drop_table('intervention_progress')

    op.drop_index('ix_intervention_consultations_date', table_name='intervention_consultations')
    op.drop_index('ix_intervention_consultations_type', table_name='intervention_consultations')
    op.drop_index('ix_intervention_consultations_plan_id', table_name='intervention_consultations')
    op.drop_table('intervention_consultations')

    op.drop_index('ix_intervention_parent_involvements_type', table_name='intervention_parent_involvements')
    op.drop_index('ix_intervention_parent_involvements_plan_id', table_name='intervention_parent_involvements')
    op.drop_table('intervention_parent_involvements')

    op.drop_index('ix_intervention_monitoring_method', table_name='intervention_monitoring')
    op.drop_index('ix_intervention_monitoring_plan_id', table_name='intervention_monitoring')
    op.drop_table('intervention_monitoring')

    op.drop_index('ix_intervention_strategies_responsible', table_name='intervention_strategies')
    op.drop_index('ix_intervention_strategies_plan_id', table_name='intervention_strategies')
    op.drop_table('intervention_strategies')

    op.drop_index('ix_intervention_goals_target_date', table_name='intervention_goals')
    op.drop_index('ix_intervention_goals_status', table_name='intervention_goals')
    op.drop_index('ix_intervention_goals_plan_id', table_name='intervention_goals')
    op.drop_table('intervention_goals')

    op.drop_index('ix_intervention_needs_priority', table_name='intervention_needs')
    op.drop_index('ix_intervention_needs_category', table_name='intervention_needs')
    op.drop_index('ix_intervention_needs_plan_id', table_name='intervention_needs')
    op.drop_table('intervention_needs')

    op.drop_index('ix_intervention_strengths_category', table_name='intervention_strengths')
    op.drop_index('ix_intervention_strengths_plan_id', table_name='intervention_strengths')
    op.drop_table('intervention_strengths')

    op.drop_index('ix_intervention_plans_created_by_created', table_name='intervention_plans')
    op.drop_index('ix_intervention_plans_review_date', table_name='intervention_plans')
    op.drop_index('ix_intervention_plans_child_status', table_name='intervention_plans')
    op.drop_index('ix_intervention_plans_created_by', table_name='intervention_plans')
    op.drop_index('ix_intervention_plans_child_id', table_name='intervention_plans')
    op.drop_table('intervention_plans')
