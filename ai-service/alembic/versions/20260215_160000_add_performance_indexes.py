"""Add composite indexes for query performance optimization.

Revision ID: 002_performance_indexes
Revises: 001
Create Date: 2026-02-15 16:00:00

Adds composite and single-column indexes to optimize common query patterns:

Activity tables:
- activity_recommendations: (child_id, generated_at), (child_id, is_dismissed), (generated_at)
- activity_participations: (child_id, started_at), (started_at), (completion_status)

Analytics tables:
- analytics_metrics: (category, period_start, period_end), (facility_id, period_start), (metric_name, period_start)
- enrollment_forecasts: (facility_id, forecast_date), (model_version)
- compliance_checks: (facility_id, checked_at), (next_check_due), (check_type, status)

Communication tables:
- parent_reports: (language), (report_date, created_at)
- home_activities: (child_id, is_completed), (developmental_area), (language)

These indexes improve performance for:
- Time-range queries
- Filtering by child and status
- Facility-specific reports
- Language-specific content retrieval
"""

from alembic import op


# revision identifiers, used by Alembic.
revision = "002_performance_indexes"
down_revision = "001"
branch_labels = None
depends_on = None


def upgrade() -> None:
    """Add composite indexes for performance optimization."""

    # Activity recommendations indexes
    op.create_index(
        "ix_activity_recommendations_child_generated",
        "activity_recommendations",
        ["child_id", "generated_at"],
        unique=False,
    )
    op.create_index(
        "ix_activity_recommendations_child_dismissed",
        "activity_recommendations",
        ["child_id", "is_dismissed"],
        unique=False,
    )
    op.create_index(
        "ix_activity_recommendations_generated_at",
        "activity_recommendations",
        ["generated_at"],
        unique=False,
    )

    # Activity participations indexes
    op.create_index(
        "ix_activity_participations_child_started",
        "activity_participations",
        ["child_id", "started_at"],
        unique=False,
    )
    op.create_index(
        "ix_activity_participations_started_at",
        "activity_participations",
        ["started_at"],
        unique=False,
    )
    op.create_index(
        "ix_activity_participations_status",
        "activity_participations",
        ["completion_status"],
        unique=False,
    )

    # Analytics metrics indexes for time-range queries
    op.create_index(
        "ix_analytics_metrics_category_period",
        "analytics_metrics",
        ["category", "period_start", "period_end"],
        unique=False,
    )
    op.create_index(
        "ix_analytics_metrics_facility_period",
        "analytics_metrics",
        ["facility_id", "period_start"],
        unique=False,
    )
    op.create_index(
        "ix_analytics_metrics_name_period",
        "analytics_metrics",
        ["metric_name", "period_start"],
        unique=False,
    )

    # Compliance checks indexes
    op.create_index(
        "ix_compliance_checks_facility_checked",
        "compliance_checks",
        ["facility_id", "checked_at"],
        unique=False,
    )
    op.create_index(
        "ix_compliance_checks_next_due",
        "compliance_checks",
        ["next_check_due"],
        unique=False,
    )
    op.create_index(
        "ix_compliance_checks_type_status",
        "compliance_checks",
        ["check_type", "status"],
        unique=False,
    )

    # Enrollment forecasts indexes
    op.create_index(
        "ix_enrollment_forecasts_facility_date",
        "enrollment_forecasts",
        ["facility_id", "forecast_date"],
        unique=False,
    )
    op.create_index(
        "ix_enrollment_forecasts_model_version",
        "enrollment_forecasts",
        ["model_version"],
        unique=False,
    )

    # Parent reports indexes
    op.create_index(
        "ix_parent_reports_language",
        "parent_reports",
        ["language"],
        unique=False,
    )
    op.create_index(
        "ix_parent_reports_date_created",
        "parent_reports",
        ["report_date", "created_at"],
        unique=False,
    )

    # Home activities indexes
    op.create_index(
        "ix_home_activities_child_completed",
        "home_activities",
        ["child_id", "is_completed"],
        unique=False,
    )
    op.create_index(
        "ix_home_activities_dev_area",
        "home_activities",
        ["developmental_area"],
        unique=False,
    )
    op.create_index(
        "ix_home_activities_language",
        "home_activities",
        ["language"],
        unique=False,
    )


def downgrade() -> None:
    """Remove composite indexes."""
    # Drop home activities indexes
    op.drop_index("ix_home_activities_language", table_name="home_activities")
    op.drop_index("ix_home_activities_dev_area", table_name="home_activities")
    op.drop_index("ix_home_activities_child_completed", table_name="home_activities")

    # Drop parent reports indexes
    op.drop_index("ix_parent_reports_date_created", table_name="parent_reports")
    op.drop_index("ix_parent_reports_language", table_name="parent_reports")

    # Drop enrollment forecasts indexes
    op.drop_index("ix_enrollment_forecasts_model_version", table_name="enrollment_forecasts")
    op.drop_index("ix_enrollment_forecasts_facility_date", table_name="enrollment_forecasts")

    # Drop compliance checks indexes
    op.drop_index("ix_compliance_checks_type_status", table_name="compliance_checks")
    op.drop_index("ix_compliance_checks_next_due", table_name="compliance_checks")
    op.drop_index("ix_compliance_checks_facility_checked", table_name="compliance_checks")

    # Drop analytics metrics indexes
    op.drop_index("ix_analytics_metrics_name_period", table_name="analytics_metrics")
    op.drop_index("ix_analytics_metrics_facility_period", table_name="analytics_metrics")
    op.drop_index("ix_analytics_metrics_category_period", table_name="analytics_metrics")

    # Drop activity participations indexes
    op.drop_index("ix_activity_participations_status", table_name="activity_participations")
    op.drop_index("ix_activity_participations_started_at", table_name="activity_participations")
    op.drop_index("ix_activity_participations_child_started", table_name="activity_participations")

    # Drop activity recommendations indexes
    op.drop_index("ix_activity_recommendations_generated_at", table_name="activity_recommendations")
    op.drop_index("ix_activity_recommendations_child_dismissed", table_name="activity_recommendations")
    op.drop_index("ix_activity_recommendations_child_generated", table_name="activity_recommendations")
