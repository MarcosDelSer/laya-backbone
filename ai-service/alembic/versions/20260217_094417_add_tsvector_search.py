"""add_tsvector_search

Revision ID: add_tsvector_search
Revises: 001
Create Date: 2026-02-17 09:44:17.000000

Adds PostgreSQL tsvector full-text search capabilities to the activities table
including search_vector column, GIN index, and automatic update trigger.
"""
from typing import Sequence, Union

from alembic import op
import sqlalchemy as sa
from sqlalchemy.dialects import postgresql

# revision identifiers, used by Alembic.
revision: str = 'add_tsvector_search'
down_revision: Union[str, None] = '001'
branch_labels: Union[str, Sequence[str], None] = None
depends_on: Union[str, Sequence[str], None] = None


def upgrade() -> None:
    """Add tsvector search capabilities to activities table."""

    # Add search_vector column using tsvector type
    op.add_column(
        'activities',
        sa.Column(
            'search_vector',
            postgresql.TSVECTOR,
            nullable=True,
        )
    )

    # Create GIN index on search_vector for fast full-text search
    op.create_index(
        'ix_activities_search_vector',
        'activities',
        ['search_vector'],
        unique=False,
        postgresql_using='gin',
    )

    # Create a trigger function to automatically update search_vector
    # This function combines name, description, and special_needs_adaptations
    # with different weights (A=1.0, B=0.4, C=0.2, D=0.1)
    op.execute("""
        CREATE OR REPLACE FUNCTION activities_search_vector_update() RETURNS trigger AS $$
        BEGIN
            NEW.search_vector :=
                setweight(to_tsvector('english', COALESCE(NEW.name, '')), 'A') ||
                setweight(to_tsvector('english', COALESCE(NEW.description, '')), 'B') ||
                setweight(to_tsvector('english', COALESCE(NEW.special_needs_adaptations, '')), 'C');
            RETURN NEW;
        END;
        $$ LANGUAGE plpgsql;
    """)

    # Create trigger to call the function on INSERT or UPDATE
    op.execute("""
        CREATE TRIGGER activities_search_vector_trigger
        BEFORE INSERT OR UPDATE ON activities
        FOR EACH ROW
        EXECUTE FUNCTION activities_search_vector_update();
    """)

    # Update existing rows to populate search_vector
    op.execute("""
        UPDATE activities
        SET search_vector =
            setweight(to_tsvector('english', COALESCE(name, '')), 'A') ||
            setweight(to_tsvector('english', COALESCE(description, '')), 'B') ||
            setweight(to_tsvector('english', COALESCE(special_needs_adaptations, '')), 'C');
    """)


def downgrade() -> None:
    """Remove tsvector search capabilities from activities table."""

    # Drop trigger first
    op.execute("DROP TRIGGER IF EXISTS activities_search_vector_trigger ON activities;")

    # Drop trigger function
    op.execute("DROP FUNCTION IF EXISTS activities_search_vector_update();")

    # Drop index
    op.drop_index('ix_activities_search_vector', table_name='activities')

    # Drop column
    op.drop_column('activities', 'search_vector')
