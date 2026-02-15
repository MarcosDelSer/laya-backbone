"""Add analytics tables for Business Intelligence Dashboard.

Revision ID: 001
Revises:
Create Date: 2026-02-15 02:52:39

Creates the following tables:
- analytics_metrics: Stores KPI metrics with historical tracking
- enrollment_forecasts: Stores enrollment predictions with confidence intervals
- compliance_checks: Tracks Quebec regulatory compliance status
"""

from alembic import op
import sqlalchemy as sa
from sqlalchemy.dialects.postgresql import JSONB, UUID


# revision identifiers, used by Alembic.
revision = "001"
down_revision = None
branch_labels = None
depends_on = None


def upgrade() -> None:
    """Create analytics tables for Business Intelligence Dashboard."""
    # Create analytics_metrics table
    op.create_table(
        "analytics_metrics",
        sa.Column("id", UUID(as_uuid=True), primary_key=True),
        sa.Column("metric_name", sa.String(100), nullable=False, index=True),
        sa.Column("metric_value", sa.Numeric(15, 4), nullable=False),
        sa.Column("metric_unit", sa.String(50), nullable=True),
        sa.Column("category", sa.String(50), nullable=False, index=True),
        sa.Column("period_start", sa.DateTime(timezone=True), nullable=False),
        sa.Column("period_end", sa.DateTime(timezone=True), nullable=False),
        sa.Column("facility_id", UUID(as_uuid=True), nullable=True, index=True),
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

    # Create enrollment_forecasts table
    op.create_table(
        "enrollment_forecasts",
        sa.Column("id", UUID(as_uuid=True), primary_key=True),
        sa.Column("forecast_date", sa.Date, nullable=False, index=True),
        sa.Column("predicted_enrollment", sa.Integer, nullable=False),
        sa.Column("confidence_lower", sa.Integer, nullable=True),
        sa.Column("confidence_upper", sa.Integer, nullable=True),
        sa.Column("model_version", sa.String(50), nullable=False, server_default="v1"),
        sa.Column("facility_id", UUID(as_uuid=True), nullable=True, index=True),
        sa.Column(
            "created_at",
            sa.DateTime(timezone=True),
            server_default=sa.func.now(),
            nullable=False,
        ),
    )

    # Create compliance_checks table
    op.create_table(
        "compliance_checks",
        sa.Column("id", UUID(as_uuid=True), primary_key=True),
        sa.Column("check_type", sa.String(100), nullable=False, index=True),
        sa.Column("status", sa.String(20), nullable=False, index=True),
        sa.Column("details", JSONB, nullable=True),
        sa.Column("checked_at", sa.DateTime(timezone=True), nullable=False),
        sa.Column("next_check_due", sa.DateTime(timezone=True), nullable=True),
        sa.Column("facility_id", UUID(as_uuid=True), nullable=True, index=True),
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


def downgrade() -> None:
    """Drop analytics tables."""
    op.drop_table("compliance_checks")
    op.drop_table("enrollment_forecasts")
    op.drop_table("analytics_metrics")
