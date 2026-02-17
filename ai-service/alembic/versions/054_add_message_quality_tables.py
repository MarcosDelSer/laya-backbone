"""Add message quality tables for AI Message Quality Coach.

Revision ID: 054
Revises: 8d7441509331
Create Date: 2026-02-15 15:50:00

Creates the following tables:
- message_analyses: Stores message quality analysis history
- message_templates: Stores pre-approved message templates
- training_examples: Stores before/after examples for educator learning
"""

from typing import Sequence, Union

from alembic import op
import sqlalchemy as sa
from sqlalchemy.dialects import postgresql

# revision identifiers, used by Alembic.
revision: str = "054"
down_revision: Union[str, None] = "8d7441509331"
branch_labels: Union[str, Sequence[str], None] = None
depends_on: Union[str, Sequence[str], None] = None


def upgrade() -> None:
    """Create message quality tables for AI Message Quality Coach."""
    # Create message_analyses table
    op.create_table(
        "message_analyses",
        sa.Column("id", postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column("user_id", postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column("child_id", postgresql.UUID(as_uuid=True), nullable=True),
        sa.Column("message_text", sa.Text(), nullable=False),
        sa.Column("language", sa.String(2), nullable=False, server_default="en"),
        sa.Column("context", sa.String(50), nullable=False, server_default="general_update"),
        sa.Column("quality_score", sa.Integer(), nullable=False, server_default="0"),
        sa.Column("is_acceptable", sa.Boolean(), nullable=False, server_default="false"),
        sa.Column("issues_detected", postgresql.ARRAY(sa.String(50)), nullable=True),
        sa.Column("has_positive_opening", sa.Boolean(), nullable=False, server_default="false"),
        sa.Column("has_factual_basis", sa.Boolean(), nullable=False, server_default="true"),
        sa.Column("has_solution_focus", sa.Boolean(), nullable=False, server_default="false"),
        sa.Column("rewrite_suggested", sa.Boolean(), nullable=False, server_default="false"),
        sa.Column("rewrite_accepted", sa.Boolean(), nullable=True),
        sa.Column("analysis_notes", sa.Text(), nullable=True),
        sa.Column(
            "created_at",
            sa.DateTime(),
            server_default=sa.func.now(),
            nullable=False,
        ),
        sa.Column(
            "updated_at",
            sa.DateTime(),
            server_default=sa.func.now(),
            nullable=True,
        ),
        sa.PrimaryKeyConstraint("id"),
    )
    # Create indexes for message_analyses
    op.create_index("ix_message_analyses_user_id", "message_analyses", ["user_id"], unique=False)
    op.create_index("ix_message_analyses_child_id", "message_analyses", ["child_id"], unique=False)
    op.create_index("ix_message_analyses_user_created", "message_analyses", ["user_id", "created_at"], unique=False)
    op.create_index("ix_message_analyses_quality_score", "message_analyses", ["quality_score"], unique=False)
    op.create_index("ix_message_analyses_language", "message_analyses", ["language"], unique=False)

    # Create message_templates table
    op.create_table(
        "message_templates",
        sa.Column("id", postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column("title", sa.String(200), nullable=False),
        sa.Column("content", sa.Text(), nullable=False),
        sa.Column("category", sa.String(50), nullable=False),
        sa.Column("language", sa.String(2), nullable=False, server_default="en"),
        sa.Column("description", sa.Text(), nullable=True),
        sa.Column("is_system", sa.Boolean(), nullable=False, server_default="false"),
        sa.Column("is_active", sa.Boolean(), nullable=False, server_default="true"),
        sa.Column("usage_count", sa.Integer(), nullable=False, server_default="0"),
        sa.Column("created_by", postgresql.UUID(as_uuid=True), nullable=True),
        sa.Column(
            "created_at",
            sa.DateTime(),
            server_default=sa.func.now(),
            nullable=False,
        ),
        sa.Column(
            "updated_at",
            sa.DateTime(),
            server_default=sa.func.now(),
            nullable=True,
        ),
        sa.PrimaryKeyConstraint("id"),
    )
    # Create indexes for message_templates
    op.create_index("ix_message_templates_category", "message_templates", ["category"], unique=False)
    op.create_index("ix_message_templates_language", "message_templates", ["language"], unique=False)
    op.create_index("ix_message_templates_category_language", "message_templates", ["category", "language"], unique=False)
    op.create_index("ix_message_templates_is_active", "message_templates", ["is_active"], unique=False)

    # Create training_examples table
    op.create_table(
        "training_examples",
        sa.Column("id", postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column("original_message", sa.Text(), nullable=False),
        sa.Column("improved_message", sa.Text(), nullable=False),
        sa.Column("issues_demonstrated", postgresql.ARRAY(sa.String(50)), nullable=False),
        sa.Column("explanation", sa.Text(), nullable=False),
        sa.Column("language", sa.String(2), nullable=False, server_default="en"),
        sa.Column("difficulty_level", sa.String(20), nullable=False, server_default="beginner"),
        sa.Column("is_active", sa.Boolean(), nullable=False, server_default="true"),
        sa.Column("view_count", sa.Integer(), nullable=False, server_default="0"),
        sa.Column("helpfulness_score", sa.Float(), nullable=True),
        sa.Column("created_by", postgresql.UUID(as_uuid=True), nullable=True),
        sa.Column(
            "created_at",
            sa.DateTime(),
            server_default=sa.func.now(),
            nullable=False,
        ),
        sa.Column(
            "updated_at",
            sa.DateTime(),
            server_default=sa.func.now(),
            nullable=True,
        ),
        sa.PrimaryKeyConstraint("id"),
    )
    # Create indexes for training_examples
    op.create_index("ix_training_examples_language", "training_examples", ["language"], unique=False)
    op.create_index("ix_training_examples_difficulty", "training_examples", ["difficulty_level"], unique=False)
    op.create_index("ix_training_examples_language_difficulty", "training_examples", ["language", "difficulty_level"], unique=False)
    op.create_index("ix_training_examples_is_active", "training_examples", ["is_active"], unique=False)


def downgrade() -> None:
    """Drop message quality tables."""
    # Drop training_examples table and indexes
    op.drop_index("ix_training_examples_is_active", table_name="training_examples")
    op.drop_index("ix_training_examples_language_difficulty", table_name="training_examples")
    op.drop_index("ix_training_examples_difficulty", table_name="training_examples")
    op.drop_index("ix_training_examples_language", table_name="training_examples")
    op.drop_table("training_examples")

    # Drop message_templates table and indexes
    op.drop_index("ix_message_templates_is_active", table_name="message_templates")
    op.drop_index("ix_message_templates_category_language", table_name="message_templates")
    op.drop_index("ix_message_templates_language", table_name="message_templates")
    op.drop_index("ix_message_templates_category", table_name="message_templates")
    op.drop_table("message_templates")

    # Drop message_analyses table and indexes
    op.drop_index("ix_message_analyses_language", table_name="message_analyses")
    op.drop_index("ix_message_analyses_quality_score", table_name="message_analyses")
    op.drop_index("ix_message_analyses_user_created", table_name="message_analyses")
    op.drop_index("ix_message_analyses_child_id", table_name="message_analyses")
    op.drop_index("ix_message_analyses_user_id", table_name="message_analyses")
    op.drop_table("message_analyses")
