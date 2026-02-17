# Alembic Migration Verification

## Overview

The Alembic migration verification script (`ai-service/scripts/verify_migrations.py`) ensures the integrity and consistency of database migrations for the LAYA AI Service. It performs comprehensive checks to detect migration issues before they cause problems in production.

## Purpose

Database migrations are critical infrastructure. This verification tool helps ensure:

- **Migration Integrity**: All migration files are valid and properly structured
- **Database Consistency**: Database schema matches expected state
- **Reversibility**: Migrations can be rolled back if needed
- **No Drift**: Models and database schema are in sync
- **No Orphaned Files**: No broken or duplicate migrations

## Features

### Basic Verification (Default)

1. **Migration Status Check**
   - Displays current database revision
   - Shows head revision from migration scripts
   - Detects pending migrations that need to be applied
   - Identifies if database is up to date

2. **Migration Files Check**
   - Verifies all migration files exist and are accessible
   - Detects duplicate revision IDs
   - Ensures migration files are properly formatted

3. **Database Tables Check**
   - Confirms database is accessible
   - Verifies alembic_version table exists
   - Lists all tables in database

### Extended Verification

4. **Schema Consistency Check** (`--check-schema`)
   - Compares SQLAlchemy model tables with database tables
   - Detects tables in models but missing from database
   - Detects tables in database not defined in models
   - Helps identify schema drift

5. **Upgrade Test** (`--full`)
   - Verifies pending migrations have upgrade functions
   - Simulates upgrade path validation (dry-run)
   - Ensures migrations can be applied cleanly

6. **Downgrade Test** (`--full`)
   - Verifies all migrations have downgrade functions
   - Ensures migrations are reversible
   - Critical for production rollback scenarios

## Usage

### Basic Verification

Run basic verification to check migration status and file integrity:

```bash
cd ai-service
python scripts/verify_migrations.py
```

**Output:**
```
LAYA AI Service - Alembic Migration Verification
Database: localhost/laya_dev

======================================================================
MIGRATION STATUS CHECK
======================================================================
Current revision: 8d7441509331
Head revision:    001
✓ Database is up to date

======================================================================
MIGRATION FILES CHECK
======================================================================
Found 2 migration(s)
✓ All 2 migration files verified

======================================================================
DATABASE TABLES CHECK
======================================================================
Found 15 table(s) in database:
✓ Database tables verified

======================================================================
VERIFICATION SUMMARY
======================================================================

Checks: 3/3 passed

✓ All verification checks passed!
```

### Full Verification

Run comprehensive verification including upgrade/downgrade tests:

```bash
python scripts/verify_migrations.py --full
```

This adds:
- Upgrade test (validates pending migrations)
- Downgrade test (ensures reversibility)

### Schema Consistency Check

Verify that database schema matches SQLAlchemy models:

```bash
python scripts/verify_migrations.py --check-schema
```

**Use Case:** Detect schema drift or missing migrations

### Verbose Output

Get detailed information about each check:

```bash
python scripts/verify_migrations.py --verbose
```

Shows:
- Individual migration file names
- Table listings
- SQL queries (if applicable)
- Detailed error traces

### Combined Options

Run all verification checks with detailed output:

```bash
python scripts/verify_migrations.py --full --check-schema --verbose
```

## When to Use

### Development Workflow

1. **Before Creating New Migration**
   ```bash
   python scripts/verify_migrations.py --check-schema
   ```
   Ensures current state is consistent before adding new migration

2. **After Creating New Migration**
   ```bash
   python scripts/verify_migrations.py --full
   ```
   Validates the new migration is properly structured

3. **Before Committing Changes**
   ```bash
   python scripts/verify_migrations.py --full --check-schema
   ```
   Comprehensive check before pushing to repository

### CI/CD Integration

Add to your CI pipeline to catch migration issues early:

```yaml
# .github/workflows/test.yml
- name: Verify Migrations
  run: |
    cd ai-service
    python scripts/verify_migrations.py --full --check-schema
```

### Production Deployment

Before deploying to production:

```bash
# Full verification on staging environment
python scripts/verify_migrations.py --full --check-schema --verbose
```

### Troubleshooting

When investigating database issues:

```bash
python scripts/verify_migrations.py --check-schema --verbose
```

Helps identify schema inconsistencies or migration problems

## Understanding Output

### Success Indicators

- `✓` Green checkmark: Check passed
- Green text: Successful operations
- "All verification checks passed!": Everything is good

### Warning Indicators

- `⚠` Yellow warning symbol: Non-critical issue
- Yellow text: Attention needed but not blocking
- Examples:
  - Database behind head (pending migrations)
  - No migrations applied yet
  - Tables in database not in models (might be legacy)

### Error Indicators

- `✗` Red X: Check failed
- Red text: Critical issue requiring attention
- Examples:
  - Duplicate revision IDs
  - Missing migration files
  - Missing upgrade/downgrade functions
  - alembic_version table missing

### Exit Codes

- `0`: All checks passed successfully
- `1`: One or more checks failed or error occurred

## Common Issues and Solutions

### Issue: "Database is behind"

**Symptom:**
```
⚠ Database is behind. 1 pending migration(s):
  - 001: add_analytics_tables
```

**Solution:**
```bash
alembic upgrade head
```

Then re-run verification.

### Issue: "No migrations have been applied"

**Symptom:**
```
⚠ No migrations have been applied to the database
```

**Solution:**
First-time setup - apply all migrations:
```bash
alembic upgrade head
```

### Issue: "Tables in models but not in database"

**Symptom:**
```
⚠ Tables in models but not in database (2):
  - analytics_metrics
  - enrollment_forecasts
```

**Solution:**
You need to create a migration for the new models:
```bash
alembic revision --autogenerate -m "add_analytics_tables"
alembic upgrade head
```

### Issue: "Tables in database but not in models"

**Symptom:**
```
⚠ Tables in database but not in models (1):
  - old_legacy_table
```

**Solution:**
Either:
1. Add model definition if table should be tracked
2. Drop table if it's no longer needed
3. Ignore if it's a legacy/external table

### Issue: "Migration missing upgrade() function"

**Symptom:**
```
✗ Migration 001 missing upgrade() function
```

**Solution:**
Edit migration file and add upgrade function:
```python
def upgrade() -> None:
    # Add upgrade operations
    pass
```

### Issue: "Migration missing downgrade() function"

**Symptom:**
```
✗ 1 migration(s) missing downgrade():
  - 001: add_analytics_tables
```

**Solution:**
Edit migration file and add downgrade function:
```python
def downgrade() -> None:
    # Add reverse operations
    pass
```

## Integration with Development Workflow

### Pre-commit Hook

Add to `.git/hooks/pre-commit`:

```bash
#!/bin/bash
cd ai-service
python scripts/verify_migrations.py --full --check-schema
if [ $? -ne 0 ]; then
    echo "Migration verification failed. Commit aborted."
    exit 1
fi
```

### Makefile Target

Add to `Makefile`:

```makefile
verify-migrations:
    cd ai-service && python scripts/verify_migrations.py --full --check-schema

verify-migrations-verbose:
    cd ai-service && python scripts/verify_migrations.py --full --check-schema --verbose
```

Usage:
```bash
make verify-migrations
```

### Docker Integration

Run verification in Docker container:

```bash
docker-compose exec ai-service python scripts/verify_migrations.py --full
```

## Testing

The verification script includes comprehensive unit tests:

```bash
cd ai-service
pytest tests/test_migration_verification.py -v
```

**Test Coverage:**
- Initialization and configuration
- Migration status checks
- File integrity validation
- Schema consistency checks
- Upgrade/downgrade tests
- Error handling
- Message formatting

## Architecture

### Components

1. **MigrationVerifier Class**: Main verification logic
2. **Alembic Integration**: Uses Alembic API for migration inspection
3. **SQLAlchemy Integration**: Compares models with database schema
4. **Async Database Access**: Uses asyncpg for PostgreSQL

### Key Methods

- `_check_migration_status()`: Verify migration application state
- `_check_migration_files()`: Validate migration file integrity
- `_check_database_tables()`: Ensure database accessibility
- `_check_schema_consistency()`: Compare models with database
- `_test_upgrade()`: Validate upgrade path
- `_test_downgrade()`: Validate downgrade path

### Design Principles

- **Non-destructive**: Never modifies database or migrations
- **Async-first**: Uses async/await for database operations
- **Comprehensive**: Checks multiple aspects of migration health
- **User-friendly**: Clear, colorized output with actionable messages
- **Testable**: Modular design with comprehensive test coverage

## Best Practices

### 1. Run Verification Regularly

```bash
# Daily development check
python scripts/verify_migrations.py

# Before creating new migration
python scripts/verify_migrations.py --check-schema

# Before committing
python scripts/verify_migrations.py --full --check-schema
```

### 2. Never Skip Downgrades

Always implement downgrade functions, even if you think you won't need them:

```python
def upgrade() -> None:
    op.create_table('new_table', ...)

def downgrade() -> None:
    op.drop_table('new_table')  # Don't skip this!
```

### 3. Use Meaningful Messages

Migration messages should be descriptive:

```bash
# Good
alembic revision -m "add_user_email_verification_fields"

# Bad
alembic revision -m "update"
```

### 4. Test Migrations Locally

Before pushing:

```bash
# Test upgrade
alembic upgrade head

# Verify
python scripts/verify_migrations.py --full --check-schema

# Test downgrade
alembic downgrade -1

# Test upgrade again
alembic upgrade head
```

### 5. Keep Migrations Small

Each migration should be focused:
- One logical change per migration
- Easier to review
- Easier to rollback
- Clearer history

## Troubleshooting

### Script Won't Run

**Check Python environment:**
```bash
which python
python --version  # Should be 3.9+
```

**Check dependencies:**
```bash
pip install -r requirements.txt
```

**Check database connection:**
```bash
# Verify database is running
docker-compose ps postgres

# Check connection
psql -h localhost -U laya_user -d laya_dev
```

### False Positives

If verification reports issues that seem incorrect:

1. **Run with --verbose** for detailed output
2. **Check database connection string** in .env
3. **Verify you're in correct environment** (dev/staging/prod)
4. **Refresh migrations** (alembic upgrade head)

## Related Documentation

- [Alembic Documentation](https://alembic.sqlalchemy.org/)
- [SQLAlchemy ORM](https://docs.sqlalchemy.org/en/14/orm/)
- [LAYA Database Models](./ai-service/app/models/)
- [Migration Guide](./docs/migration-guide.md)

## Support

For issues or questions:

1. Check this documentation
2. Review test files for examples
3. Check verbose output for details
4. Review migration files in `ai-service/alembic/versions/`

## Version History

- **v1.0** (2026-02-15): Initial implementation
  - Basic verification checks
  - Full verification mode
  - Schema consistency checks
  - Comprehensive test coverage
