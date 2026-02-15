# Seed Script Idempotency Implementation

## Overview

This document describes the idempotency implementation for the LAYA seed data scripts. Both the Python (AI service) and PHP (Gibbon) seed scripts are fully idempotent, meaning they can be safely run multiple times without creating duplicate data.

## Implementation Status

✅ **COMPLETE** - Both seed scripts implement idempotency checks before inserting data.

## Python Seed Script (`ai-service/scripts/seed.py`)

### Idempotency Strategy

The Python seed script implements idempotency by checking for existing records before creating new ones. Each seeding function follows this pattern:

```python
async def seed_<entity>(session: AsyncSession, ...) -> list[UUID]:
    # Check if data already exists
    result = await session.execute(select(Entity).limit(1))
    if result.scalar_one_or_none():
        print("✓ <Entity> already exist, skipping...")
        result = await session.execute(select(Entity.id))
        return [row[0] for row in result.all()]

    # Create new data if none exists
    # ...
```

### Idempotent Functions

All seeding functions implement idempotency:

1. **`seed_activities()`** (lines 168-173)
   - Checks if any activities exist before creating new ones
   - Returns existing activity IDs if found

2. **`seed_activity_participations()`** (lines 239-243)
   - Checks if any participation records exist before creating new ones
   - Skips creation if participations already exist

3. **`seed_activity_recommendations()`** (lines 297-301)
   - Checks if any recommendations exist before creating new ones
   - Skips creation if recommendations already exist

4. **`seed_coaching_sessions()`** (lines 346-351)
   - Checks if any coaching sessions exist before creating new ones
   - Returns existing session IDs if found

5. **`seed_parent_reports()`** (lines 425-429)
   - Checks if any parent reports exist before creating new ones
   - Skips creation if reports already exist

6. **`seed_home_activities()`** (lines 489-493)
   - Checks if any home activities exist before creating new ones
   - Skips creation if home activities already exist

7. **`seed_communication_preferences()`** (lines 550-554)
   - Checks if any communication preferences exist before creating new ones
   - Skips creation if preferences already exist

### Testing

Comprehensive idempotency tests are located in `ai-service/tests/test_seed_idempotency.py`:

- **Individual function tests**: Each seeding function is tested for idempotency
- **Full script test**: Verifies running the complete seed script multiple times is safe
- **Duplicate detection**: Ensures no duplicate records are created
- **Data integrity**: Verifies referential integrity is maintained
- **Edge cases**: Tests with empty database and partial data scenarios

Run tests with:
```bash
cd ai-service
pytest tests/test_seed_idempotency.py -v
```

## PHP Seed Script (`gibbon/modules/seed_data.php`)

### Idempotency Strategy

The PHP seed script implements idempotency using a helper function that checks for existing records before insertion:

```php
function recordExists($pdo, $table, $where) {
    $conditions = [];
    $params = [];
    foreach ($where as $key => $value) {
        $conditions[] = "$key = :$key";
        $params[":$key"] = $value;
    }
    $sql = "SELECT COUNT(*) FROM $table WHERE " . implode(' AND ', $conditions);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn() > 0;
}
```

### Idempotent Operations

All data creation operations check for existing records:

1. **Form Groups** (lines 273-293)
   - Checks by `gibbonSchoolYearID` and `nameShort`
   - Skips creation if form group already exists

2. **Staff Members** (lines 299-331)
   - Checks by email address
   - Skips creation if staff member already exists

3. **Families** (lines 347-469)
   - Checks by family name
   - Skips creation if family already exists

4. **Parents** (lines 359-415)
   - Checks by email address
   - Skips creation if parent already exists

5. **Children** (lines 417-469)
   - Checks by email address
   - Skips creation if child already exists

6. **Care Records** (lines 488-625)
   - **Attendance**: Checks by `gibbonPersonID` and `date`
   - **Meals**: Checks by `gibbonPersonID`, `date`, and `mealType`
   - **Naps**: Checks by `gibbonPersonID` and `date`
   - **Diapers**: Checks by `gibbonPersonID`, `date`, and `time`
   - **Incidents**: Checks by `gibbonPersonID` and `date`
   - **Activities**: Checks by `gibbonPersonID`, `date`, and `activityName`

### Testing

A comprehensive verification script is provided at `gibbon/modules/verify_seed_idempotency.php`:

- Runs the seed script multiple times (3 runs)
- Counts records after each run
- Checks for duplicate records
- Verifies counts remain stable between runs 2 and 3
- Reports pass/fail status

Run verification with:
```bash
cd gibbon/modules
php verify_seed_idempotency.php --verbose
```

## Benefits of Idempotency

1. **Safe Re-execution**: Developers can run seed scripts multiple times without fear of creating duplicate data
2. **Development Workflow**: Easy to reset and re-seed during development
3. **Error Recovery**: If seeding fails midway, it can be re-run safely
4. **Consistent State**: Database state is predictable regardless of how many times seeding is run
5. **Testing**: Test data can be reliably recreated

## Usage Examples

### Python Seed Script

```bash
# First run - creates data
python ai-service/scripts/seed.py

# Second run - skips existing data, no duplicates
python ai-service/scripts/seed.py

# Third run - still safe
python ai-service/scripts/seed.py
```

### PHP Seed Script

```bash
# First run - creates data
php gibbon/modules/seed_data.php

# Second run - skips existing data, no duplicates
php gibbon/modules/seed_data.php

# With --reset flag - clears and recreates
php gibbon/modules/seed_data.php --reset
```

## Reset vs Idempotent Runs

### Idempotent Run (Default)
- Checks for existing data
- Skips creation if data exists
- Safe to run multiple times
- Preserves existing data

### Reset Run (PHP only, with `--reset` flag)
- Deletes all seed data first
- Creates fresh data
- Use when you want to start from scratch
- Removes old test data

```bash
# Reset and recreate all data
php gibbon/modules/seed_data.php --reset
```

## Implementation Quality

Both seed scripts follow best practices:

- ✅ Comprehensive idempotency checks
- ✅ Clear logging of skipped operations
- ✅ Transaction-based operations (PHP)
- ✅ Proper error handling
- ✅ Referential integrity maintained
- ✅ No duplicate records created
- ✅ Tested and verified
- ✅ Well-documented

## Verification Results

### Python Seed Script
- All unit tests pass
- No duplicates created across multiple runs
- Data integrity maintained
- Referential constraints satisfied

### PHP Seed Script
- Verification script passes all checks
- No duplicate persons, families, or care records
- Record counts stable across multiple runs
- Transaction safety ensures atomicity

## Conclusion

The seed scripts for the LAYA system are fully idempotent and production-ready. They can be safely run multiple times in development environments without creating duplicate data or corrupting the database state.

## Files Created/Modified

### Created
- `ai-service/tests/test_seed_idempotency.py` - Comprehensive Python tests
- `gibbon/modules/verify_seed_idempotency.php` - PHP verification script
- `SEED_IDEMPOTENCY_IMPLEMENTATION.md` - This documentation

### Existing (Already Idempotent)
- `ai-service/scripts/seed.py` - Python seed script
- `gibbon/modules/seed_data.php` - PHP seed script

## Next Steps

The idempotency implementation is complete. The seed scripts are ready for use in:
1. Development environments
2. Testing environments
3. CI/CD pipelines
4. Pilot daycare onboarding

No further work required on idempotency - implementation is complete and tested.
