# LAYA Seed Data CLI Commands

This document describes the Makefile commands for seeding development databases with sample data.

## Overview

The LAYA backbone provides convenient `make` commands to populate development databases with realistic sample data for both the AI service (Python/PostgreSQL) and Gibbon (PHP/MySQL) components.

## Quick Start

```bash
# Seed both databases with sample data
make seed

# Reset and seed both databases
make seed-reset

# Show all available commands
make help
```

## Available Commands

### `make help`

Displays help information about all available seed commands.

**Example:**
```bash
make help
```

**Output:**
```
LAYA Development Seed Data Commands
====================================

Available targets:
  make seed              - Seed both AI service and Gibbon databases
  make seed-reset        - Reset and seed both databases
  ...
```

---

### `make seed`

Seeds both the AI service and Gibbon databases with sample data. This command is idempotent - it's safe to run multiple times without creating duplicate data.

**What it does:**
1. Seeds AI service database (PostgreSQL)
2. Seeds Gibbon database (MySQL)

**What it creates:**

**AI Service:**
- 20 children across 3 age groups (0-2, 2-4, 4-6 years)
- 15 families with parent information
- 55 activities across 6 types (cognitive, motor, social, language, creative, sensory)
- 100 activity participation records
- 10 coaching sessions with recommendations
- Bilingual parent reports (English & French)
- Home activity suggestions
- Communication preferences

**Gibbon:**
- 3 form groups (age groups: 0-2, 2-4, 4-6)
- 5 staff members with roles
- 15 families with parent info
- 20 children with enrollments
- ~1000+ care records:
  - Attendance (check-in/check-out)
  - Meals (breakfast, lunch, snacks)
  - Naps (duration and quality)
  - Diaper changes (for younger children)
  - Incidents (minor injuries, illness, behavioral)
  - Activities (art, music, physical, language, math, science, social)

**Example:**
```bash
make seed
```

**Prerequisites:**
- Database servers running (PostgreSQL and MySQL)
- AI service configured with correct database credentials
- Gibbon installed with at least one "Current" school year
- CareTracking module tables installed in Gibbon

**When to use:**
- Initial setup of development environment
- Refreshing data after manual testing
- Onboarding new developers

---

### `make seed-reset`

Resets both databases to a clean state and then seeds them with fresh sample data. **Warning:** This will delete all existing data!

**What it does:**
1. Resets AI service database (downgrades to base, upgrades to head)
2. Resets Gibbon database (deletes all seed data)
3. Seeds both databases with fresh data

**Example:**
```bash
make seed-reset
```

**⚠️ Warning:** This command will:
- Delete ALL data from AI service database
- Delete ALL seed data from Gibbon database
- Recreate fresh sample data

**When to use:**
- Starting fresh after data corruption
- Testing migration scripts
- Cleaning up after extensive manual testing
- Ensuring a pristine development environment

---

### `make seed-ai`

Seeds only the AI service database with sample data. Useful when you only need to refresh AI service data without touching Gibbon.

**What it does:**
- Runs `python ai-service/scripts/seed.py`
- Creates sample data in PostgreSQL database

**Example:**
```bash
make seed-ai
```

**When to use:**
- Testing AI service features in isolation
- Refreshing only AI service data
- After modifying AI service models

---

### `make seed-ai-reset`

Resets only the AI service database and seeds it with fresh data. Uses Alembic migrations to ensure schema is up-to-date.

**What it does:**
1. Downgrades database to base (removes all tables)
2. Upgrades database to head (applies all migrations)
3. Seeds database with sample data

**Example:**
```bash
make seed-ai-reset
```

**⚠️ Warning:** This will delete ALL AI service data!

**When to use:**
- Testing Alembic migrations
- Recovering from schema corruption
- Starting fresh with AI service development

---

### `make seed-gibbon`

Seeds only the Gibbon database with sample data. Useful when you only need to refresh Gibbon data without touching AI service.

**What it does:**
- Runs `php gibbon/modules/seed_data.php`
- Creates sample data in MySQL database

**Example:**
```bash
make seed-gibbon
```

**When to use:**
- Testing Gibbon module features in isolation
- Refreshing only Gibbon data
- After modifying CareTracking module

---

### `make seed-gibbon-reset`

Resets only the Gibbon database and seeds it with fresh data.

**What it does:**
1. Deletes all existing seed data (staff, families, students, care records)
2. Seeds database with fresh sample data

**Example:**
```bash
make seed-gibbon-reset
```

**⚠️ Warning:** This will delete all Gibbon seed data!

**When to use:**
- Starting fresh with Gibbon development
- Recovering from data corruption
- Testing clean install scenarios

---

## Workflows

### Daily Development

```bash
# Start your day with fresh data
make seed-reset

# Make changes to code...

# Refresh only what you need
make seed-ai        # If working on AI service
make seed-gibbon    # If working on Gibbon
```

### After Schema Changes

```bash
# After adding new AI service models/migrations
make seed-ai-reset

# After modifying Gibbon tables
make seed-gibbon-reset
```

### Onboarding New Developers

```bash
# First time setup
git clone <repo>
# ... install dependencies ...
make seed           # Populate databases with sample data
```

### Testing Data Integrity

```bash
# Test idempotency - should not create duplicates
make seed
make seed           # Run again - should skip existing data
make seed           # Run again - still should skip
```

---

## Verification

After running seed commands, you can verify the data:

### AI Service

```bash
# Access API docs
open http://localhost:8000/docs

# Or use curl
curl http://localhost:8000/api/v1/activities
curl http://localhost:8000/api/v1/children
```

### Gibbon

```bash
# Access Gibbon UI
open http://localhost:8080

# Navigate to:
# - People > Manage Families
# - Students > Student Enrolment
# - Care Tracking > Dashboard
```

---

## Troubleshooting

### Error: "alembic: command not found"

**Solution:**
```bash
cd ai-service
pip install -r requirements.txt
```

### Error: "No current school year found"

**Solution:**
1. Access Gibbon UI: http://localhost:8080
2. Navigate to: School Admin > Manage School Years
3. Ensure at least one school year has status 'Current'

### Error: "Database connection failed"

**Solution:**
```bash
# Check if database services are running
docker-compose ps

# Start database services if needed
docker-compose up -d postgres mysql
```

### Error: "Permission denied"

**Solution:**
```bash
# Make PHP script executable
chmod +x gibbon/modules/seed_data.php
```

### Idempotency Issues

If seed data is being duplicated:
```bash
# Reset and start fresh
make seed-reset
```

---

## Technical Details

### AI Service Seed Script

- **Location:** `ai-service/scripts/seed.py`
- **Language:** Python 3.11
- **Database:** PostgreSQL
- **ORM:** SQLAlchemy (async)
- **Idempotency:** Checks for existing records before inserting
- **Execution time:** ~2-5 seconds

### Gibbon Seed Script

- **Location:** `gibbon/modules/seed_data.php`
- **Language:** PHP 8.3
- **Database:** MySQL/MariaDB
- **Idempotency:** Uses `recordExists()` helper function
- **Transaction safety:** Wrapped in database transaction
- **Execution time:** ~2-5 seconds (~1000+ records)

### Makefile

- **Location:** Root directory (`./Makefile`)
- **PHONY targets:** All targets are declared as `.PHONY` to avoid conflicts with files
- **Error handling:** Commands fail fast on error (default Make behavior)
- **Clean output:** Uses `@` prefix to suppress command echo

---

## Integration with CI/CD

These commands can be used in automated testing:

```bash
# In your test script
make seed-reset     # Start with clean data
./run_tests.sh      # Run your tests
make seed-reset     # Clean up after
```

---

## Related Documentation

- **Seed Script Details:**
  - [AI Service Seed Script](ai-service/scripts/seed.py)
  - [Gibbon Seed Script](gibbon/modules/SEED_DATA_README.md)

- **Idempotency Implementation:**
  - [SEED_IDEMPOTENCY_IMPLEMENTATION.md](SEED_IDEMPOTENCY_IMPLEMENTATION.md)

- **Migration Verification:**
  - [ALEMBIC_MIGRATION_VERIFICATION.md](ALEMBIC_MIGRATION_VERIFICATION.md)

---

## Support

For issues or questions:
1. Check this documentation
2. Run `make help` for quick reference
3. Review individual script documentation
4. Check database connectivity and prerequisites
5. Try `make seed-reset` to start fresh

---

## Examples

### Example: Full Development Environment Setup

```bash
# Clone repository
git clone <repo-url>
cd laya-backbone

# Start services
docker-compose up -d

# Install dependencies
cd ai-service && pip install -r requirements.txt && cd ..

# Seed databases
make seed

# Verify
open http://localhost:8000/docs
open http://localhost:8080
```

### Example: Testing Migrations

```bash
# Reset AI service database
make seed-ai-reset

# Verify migration scripts work
cd ai-service
python scripts/verify_migrations.py --full

# Verify data integrity
pytest tests/test_seed_idempotency.py
```

### Example: Quick Data Refresh

```bash
# Refresh only AI service data
make seed-ai

# Or refresh only Gibbon data
make seed-gibbon
```

---

**Last Updated:** 2026-02-15
**Version:** 1.0.0
