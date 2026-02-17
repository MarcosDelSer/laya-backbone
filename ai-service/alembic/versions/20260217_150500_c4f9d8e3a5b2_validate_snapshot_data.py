"""validate_snapshot_data

Revision ID: c4f9d8e3a5b2
Revises: b3e5a7c92046
Create Date: 2026-02-17 15:05:00.000000

Adds validation constraints to intervention_versions.snapshot_data to ensure
snapshot data integrity and completeness. This migration adds:

1. CHECK constraint to ensure snapshot_data is a valid JSONB object (not empty)
2. CHECK constraint to ensure snapshot_data contains required top-level fields:
   - created_by: ID of user who created the plan
   - parent_version_id: ID of previous version for lineage tracking
   - All 8-part structure sections (strengths, needs, goals, strategies,
     monitoring, parent_involvements, consultations)

Note: This migration does NOT make snapshot_data NOT NULL to allow for
existing records that may need backfilling via the backfill_snapshot_data.py script.
"""
from typing import Sequence, Union

from alembic import op
import sqlalchemy as sa
from sqlalchemy.dialects import postgresql

# revision identifiers, used by Alembic.
revision: str = 'c4f9d8e3a5b2'
down_revision: Union[str, None] = 'b3e5a7c92046'
branch_labels: Union[str, Sequence[str], None] = None
depends_on: Union[str, Sequence[str], None] = None


def upgrade() -> None:
    """Add validation constraints for intervention_versions.snapshot_data"""

    # Add CHECK constraint to ensure snapshot_data, when not NULL, contains required fields
    # This ensures snapshot data integrity without breaking existing NULL records
    op.create_check_constraint(
        'ck_intervention_versions_snapshot_data_valid',
        'intervention_versions',
        """
        snapshot_data IS NULL OR (
            jsonb_typeof(snapshot_data) = 'object' AND
            snapshot_data != '{}'::jsonb AND
            snapshot_data ? 'created_by' AND
            snapshot_data ? 'parent_version_id' AND
            snapshot_data ? 'strengths' AND
            snapshot_data ? 'needs' AND
            snapshot_data ? 'goals' AND
            snapshot_data ? 'strategies' AND
            snapshot_data ? 'monitoring' AND
            snapshot_data ? 'parent_involvements' AND
            snapshot_data ? 'consultations'
        )
        """
    )

    # Add CHECK constraint to ensure arrays in snapshot_data are valid JSON arrays
    op.create_check_constraint(
        'ck_intervention_versions_snapshot_arrays_valid',
        'intervention_versions',
        """
        snapshot_data IS NULL OR (
            jsonb_typeof(snapshot_data->'strengths') = 'array' AND
            jsonb_typeof(snapshot_data->'needs') = 'array' AND
            jsonb_typeof(snapshot_data->'goals') = 'array' AND
            jsonb_typeof(snapshot_data->'strategies') = 'array' AND
            jsonb_typeof(snapshot_data->'monitoring') = 'array' AND
            jsonb_typeof(snapshot_data->'parent_involvements') = 'array' AND
            jsonb_typeof(snapshot_data->'consultations') = 'array'
        )
        """
    )


def downgrade() -> None:
    """Remove snapshot_data validation constraints"""

    # Drop CHECK constraints in reverse order
    op.drop_constraint(
        'ck_intervention_versions_snapshot_arrays_valid',
        'intervention_versions',
        type_='check'
    )
    op.drop_constraint(
        'ck_intervention_versions_snapshot_data_valid',
        'intervention_versions',
        type_='check'
    )
