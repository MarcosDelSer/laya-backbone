"""Add development profile tables for Quebec-aligned developmental tracking.

Revision ID: 002
Revises: 001
Create Date: 2026-02-16 12:00:00

Creates the following tables:
- development_profiles: Child development profiles with Quebec 6-domain tracking
- skill_assessments: Individual skill assessment records per domain
- observations: Observable behavior documentation with evidence
- monthly_snapshots: Monthly developmental progress summaries

Also creates the following enums:
- developmental_domain_enum: affective, social, language, cognitive, gross_motor, fine_motor
- skill_status_enum: can, learning, not_yet, na
"""

from alembic import op
import sqlalchemy as sa
from sqlalchemy.dialects.postgresql import JSONB, UUID


# revision identifiers, used by Alembic.
revision = "002"
down_revision = "001"
branch_labels = None
depends_on = None


def upgrade() -> None:
    """Create development profile tables for Quebec-aligned developmental tracking."""
    # Create developmental_domain_enum type
    developmental_domain_enum = sa.Enum(
        "affective",
        "social",
        "language",
        "cognitive",
        "gross_motor",
        "fine_motor",
        name="developmental_domain_enum",
    )
    developmental_domain_enum.create(op.get_bind(), checkfirst=True)

    # Create skill_status_enum type
    skill_status_enum = sa.Enum(
        "can",
        "learning",
        "not_yet",
        "na",
        name="skill_status_enum",
    )
    skill_status_enum.create(op.get_bind(), checkfirst=True)

    # Create development_profiles table
    op.create_table(
        "development_profiles",
        sa.Column("id", UUID(as_uuid=True), primary_key=True),
        sa.Column("child_id", UUID(as_uuid=True), nullable=False, unique=True, index=True),
        sa.Column("educator_id", UUID(as_uuid=True), nullable=True, index=True),
        sa.Column("birth_date", sa.Date, nullable=True),
        sa.Column("notes", sa.Text, nullable=True),
        sa.Column("is_active", sa.Boolean, nullable=False, server_default=sa.text("true"), index=True),
        sa.Column(
            "created_at",
            sa.DateTime(timezone=True),
            server_default=sa.func.now(),
            nullable=False,
        ),
        sa.Column(
            "updated_at",
            sa.DateTime(timezone=True),
            server_default=sa.func.now(),
            nullable=False,
        ),
    )

    # Create skill_assessments table
    op.create_table(
        "skill_assessments",
        sa.Column("id", UUID(as_uuid=True), primary_key=True),
        sa.Column("profile_id", UUID(as_uuid=True), nullable=False, index=True),
        sa.Column(
            "domain",
            developmental_domain_enum,
            nullable=False,
            index=True,
        ),
        sa.Column("skill_name", sa.String(200), nullable=False),
        sa.Column("skill_name_fr", sa.String(200), nullable=True),
        sa.Column(
            "status",
            skill_status_enum,
            nullable=False,
            server_default="not_yet",
            index=True,
        ),
        sa.Column(
            "assessed_at",
            sa.DateTime(timezone=True),
            server_default=sa.func.now(),
            nullable=False,
        ),
        sa.Column("assessed_by_id", UUID(as_uuid=True), nullable=True),
        sa.Column("evidence", sa.Text, nullable=True),
        sa.Column(
            "created_at",
            sa.DateTime(timezone=True),
            server_default=sa.func.now(),
            nullable=False,
        ),
        sa.Column(
            "updated_at",
            sa.DateTime(timezone=True),
            server_default=sa.func.now(),
            nullable=False,
        ),
        sa.ForeignKeyConstraint(
            ["profile_id"],
            ["development_profiles.id"],
            ondelete="CASCADE",
        ),
    )

    # Create observations table
    op.create_table(
        "observations",
        sa.Column("id", UUID(as_uuid=True), primary_key=True),
        sa.Column("profile_id", UUID(as_uuid=True), nullable=False, index=True),
        sa.Column(
            "domain",
            developmental_domain_enum,
            nullable=False,
            index=True,
        ),
        sa.Column(
            "observed_at",
            sa.DateTime(timezone=True),
            server_default=sa.func.now(),
            nullable=False,
        ),
        sa.Column("observer_id", UUID(as_uuid=True), nullable=True, index=True),
        sa.Column("observer_type", sa.String(50), nullable=False, server_default="educator"),
        sa.Column("behavior_description", sa.Text, nullable=False),
        sa.Column("context", sa.Text, nullable=True),
        sa.Column("is_milestone", sa.Boolean, nullable=False, server_default=sa.text("false")),
        sa.Column("is_concern", sa.Boolean, nullable=False, server_default=sa.text("false"), index=True),
        sa.Column("attachments", JSONB, nullable=True),
        sa.Column(
            "created_at",
            sa.DateTime(timezone=True),
            server_default=sa.func.now(),
            nullable=False,
        ),
        sa.Column(
            "updated_at",
            sa.DateTime(timezone=True),
            server_default=sa.func.now(),
            nullable=False,
        ),
        sa.ForeignKeyConstraint(
            ["profile_id"],
            ["development_profiles.id"],
            ondelete="CASCADE",
        ),
    )

    # Create monthly_snapshots table
    op.create_table(
        "monthly_snapshots",
        sa.Column("id", UUID(as_uuid=True), primary_key=True),
        sa.Column("profile_id", UUID(as_uuid=True), nullable=False, index=True),
        sa.Column("snapshot_month", sa.Date, nullable=False, index=True),
        sa.Column("age_months", sa.Integer, nullable=True),
        sa.Column("domain_summaries", JSONB, nullable=True),
        sa.Column("overall_progress", sa.String(50), nullable=False, server_default="on_track"),
        sa.Column("strengths", JSONB, nullable=True),
        sa.Column("growth_areas", JSONB, nullable=True),
        sa.Column("recommendations", sa.Text, nullable=True),
        sa.Column("generated_by_id", UUID(as_uuid=True), nullable=True),
        sa.Column("is_parent_shared", sa.Boolean, nullable=False, server_default=sa.text("false")),
        sa.Column(
            "created_at",
            sa.DateTime(timezone=True),
            server_default=sa.func.now(),
            nullable=False,
        ),
        sa.Column(
            "updated_at",
            sa.DateTime(timezone=True),
            server_default=sa.func.now(),
            nullable=False,
        ),
        sa.ForeignKeyConstraint(
            ["profile_id"],
            ["development_profiles.id"],
            ondelete="CASCADE",
        ),
    )


def downgrade() -> None:
    """Drop development profile tables and enum types."""
    op.drop_table("monthly_snapshots")
    op.drop_table("observations")
    op.drop_table("skill_assessments")
    op.drop_table("development_profiles")

    # Drop enum types
    sa.Enum(name="skill_status_enum").drop(op.get_bind(), checkfirst=True)
    sa.Enum(name="developmental_domain_enum").drop(op.get_bind(), checkfirst=True)
