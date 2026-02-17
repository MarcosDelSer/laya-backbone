"""Verify that UUID and PGUUID produce identical database schemas.

This script validates that replacing UUID with PGUUID doesn't change
the actual database schema - only the Python type representation.
"""

from sqlalchemy import create_engine, MetaData, Table, Column
from sqlalchemy.dialects.postgresql import UUID as PGUUID
from sqlalchemy import UUID as GenericUUID


def get_column_schema(column):
    """Extract schema definition from a column."""
    return {
        'type': str(column.type.compile()),
        'nullable': column.nullable,
        'primary_key': column.primary_key,
    }


def main():
    """Compare UUID vs PGUUID column schemas."""
    print("=== UUID vs PGUUID Schema Comparison ===\n")

    # Create test tables with UUID and PGUUID
    metadata = MetaData()

    table_with_generic_uuid = Table(
        'test_generic_uuid',
        metadata,
        Column('id', GenericUUID(as_uuid=True), primary_key=True),
    )

    table_with_pguuid = Table(
        'test_pguuid',
        metadata,
        Column('id', PGUUID(as_uuid=True), primary_key=True),
    )

    # Compare column definitions
    generic_col = table_with_generic_uuid.c.id
    pguuid_col = table_with_pguuid.c.id

    print("Generic UUID column:")
    print(f"  Type: {generic_col.type}")
    print(f"  Compiled: {generic_col.type.compile(dialect=None)}")
    print()

    print("PGUUID column:")
    print(f"  Type: {pguuid_col.type}")
    print(f"  Compiled: {pguuid_col.type.compile(dialect=None)}")
    print()

    # Check if they're equivalent for PostgreSQL
    from sqlalchemy.dialects import postgresql
    pg_dialect = postgresql.dialect()

    generic_compiled = generic_col.type.compile(dialect=pg_dialect)
    pguuid_compiled = pguuid_col.type.compile(dialect=pg_dialect)

    print("PostgreSQL dialect compilation:")
    print(f"  Generic UUID: {generic_compiled}")
    print(f"  PGUUID: {pguuid_compiled}")
    print()

    if str(generic_compiled) == str(pguuid_compiled):
        print("✅ RESULT: Both produce IDENTICAL PostgreSQL schema")
        print("   Changing from UUID to PGUUID does NOT require schema migration")
        return 0
    else:
        print("❌ RESULT: Different schemas detected")
        print("   This would require a migration")
        return 1


if __name__ == '__main__':
    exit(main())
