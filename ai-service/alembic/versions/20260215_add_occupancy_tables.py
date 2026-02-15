"""Add occupancy tables for Real-Time Director Dashboard.

Revision ID: 002
Revises: 001
Create Date: 2026-02-15 15:50:00

Creates the following tables:
- occupancy_groups: Stores childcare groups/classrooms with capacity limits
- occupancy_records: Stores point-in-time occupancy snapshots
- child_attendances: Tracks individual child attendance with check-in/check-out times
"""

from alembic import op
import sqlalchemy as sa
from sqlalchemy.dialects.postgresql import UUID


# revision identifiers, used by Alembic.
revision = "002"
down_revision = "001"
branch_labels = None
depends_on = None


def upgrade() -> None:
    """Create occupancy tables for Real-Time Director Dashboard."""
    # Create occupancy_groups table
    op.create_table(
        "occupancy_groups",
        sa.Column("id", UUID(as_uuid=True), primary_key=True),
        sa.Column("facility_id", UUID(as_uuid=True), nullable=False, index=True),
        sa.Column("name", sa.String(100), nullable=False),
        sa.Column(
            "age_group",
            sa.String(50),
            nullable=False,
            server_default="prescolaire",
        ),
        sa.Column("capacity", sa.Integer, nullable=False, server_default="10"),
        sa.Column("min_age_months", sa.Integer, nullable=True),
        sa.Column("max_age_months", sa.Integer, nullable=True),
        sa.Column("room_number", sa.String(20), nullable=True),
        sa.Column("is_active", sa.Boolean, nullable=False, server_default="true"),
        sa.Column(
            "created_at",
            sa.DateTime,
            server_default=sa.func.now(),
            nullable=False,
        ),
        sa.Column(
            "updated_at",
            sa.DateTime,
            server_default=sa.func.now(),
            nullable=True,
        ),
    )

    # Create composite indexes for occupancy_groups
    op.create_index(
        "ix_occupancy_groups_facility_active",
        "occupancy_groups",
        ["facility_id", "is_active"],
    )
    op.create_index(
        "ix_occupancy_groups_age_group",
        "occupancy_groups",
        ["age_group"],
    )

    # Create occupancy_records table
    op.create_table(
        "occupancy_records",
        sa.Column("id", UUID(as_uuid=True), primary_key=True),
        sa.Column(
            "group_id",
            UUID(as_uuid=True),
            sa.ForeignKey("occupancy_groups.id", ondelete="CASCADE"),
            nullable=False,
            index=True,
        ),
        sa.Column("facility_id", UUID(as_uuid=True), nullable=False, index=True),
        sa.Column("record_date", sa.Date, nullable=False, index=True),
        sa.Column("record_time", sa.DateTime, nullable=False),
        sa.Column("current_count", sa.Integer, nullable=False, server_default="0"),
        sa.Column("capacity", sa.Integer, nullable=False),
        sa.Column("staff_count", sa.Integer, nullable=True),
        sa.Column("notes", sa.Text, nullable=True),
        sa.Column(
            "created_at",
            sa.DateTime,
            server_default=sa.func.now(),
            nullable=False,
        ),
    )

    # Create composite indexes for occupancy_records
    op.create_index(
        "ix_occupancy_records_facility_date",
        "occupancy_records",
        ["facility_id", "record_date"],
    )
    op.create_index(
        "ix_occupancy_records_group_date",
        "occupancy_records",
        ["group_id", "record_date"],
    )

    # Create child_attendances table
    op.create_table(
        "child_attendances",
        sa.Column("id", UUID(as_uuid=True), primary_key=True),
        sa.Column("child_id", UUID(as_uuid=True), nullable=False, index=True),
        sa.Column(
            "group_id",
            UUID(as_uuid=True),
            sa.ForeignKey("occupancy_groups.id", ondelete="CASCADE"),
            nullable=False,
            index=True,
        ),
        sa.Column("facility_id", UUID(as_uuid=True), nullable=False, index=True),
        sa.Column("attendance_date", sa.Date, nullable=False, index=True),
        sa.Column("check_in_time", sa.DateTime, nullable=True),
        sa.Column("check_out_time", sa.DateTime, nullable=True),
        sa.Column(
            "status",
            sa.String(30),
            nullable=False,
            server_default="present",
        ),
        sa.Column("checked_in_by", UUID(as_uuid=True), nullable=True),
        sa.Column("checked_out_by", UUID(as_uuid=True), nullable=True),
        sa.Column("notes", sa.Text, nullable=True),
        sa.Column(
            "created_at",
            sa.DateTime,
            server_default=sa.func.now(),
            nullable=False,
        ),
        sa.Column(
            "updated_at",
            sa.DateTime,
            server_default=sa.func.now(),
            nullable=True,
        ),
    )

    # Create composite indexes for child_attendances
    op.create_index(
        "ix_child_attendances_child_date",
        "child_attendances",
        ["child_id", "attendance_date"],
    )
    op.create_index(
        "ix_child_attendances_facility_date",
        "child_attendances",
        ["facility_id", "attendance_date"],
    )
    op.create_index(
        "ix_child_attendances_group_date",
        "child_attendances",
        ["group_id", "attendance_date"],
    )
    op.create_index(
        "ix_child_attendances_status",
        "child_attendances",
        ["status"],
    )


def downgrade() -> None:
    """Drop occupancy tables."""
    # Drop indexes first
    op.drop_index("ix_child_attendances_status", table_name="child_attendances")
    op.drop_index("ix_child_attendances_group_date", table_name="child_attendances")
    op.drop_index("ix_child_attendances_facility_date", table_name="child_attendances")
    op.drop_index("ix_child_attendances_child_date", table_name="child_attendances")

    op.drop_index("ix_occupancy_records_group_date", table_name="occupancy_records")
    op.drop_index("ix_occupancy_records_facility_date", table_name="occupancy_records")

    op.drop_index("ix_occupancy_groups_age_group", table_name="occupancy_groups")
    op.drop_index("ix_occupancy_groups_facility_active", table_name="occupancy_groups")

    # Drop tables in reverse dependency order
    op.drop_table("child_attendances")
    op.drop_table("occupancy_records")
    op.drop_table("occupancy_groups")
