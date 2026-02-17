# Intervention Plan Snapshot Behavior

## Overview

Intervention plan snapshots provide **automatic versioning and audit trails** for intervention plans. Every time a plan is created or modified, the system captures a complete snapshot of the plan's state, enabling:

- **Version history tracking** - Access any previous version of a plan
- **Change comparison** - See what changed between versions
- **Regulatory compliance** - Maintain complete audit trails for IEP/504 plans
- **Data integrity** - Prevent accidental data loss during updates

## Snapshot Creation Timing

Snapshots are created automatically at two key moments:

### 1. Plan Creation (Version 1)

When a new intervention plan is created via `POST /api/v1/intervention-plans`:

```python
# Example: Creating a plan
plan = await intervention_plan_service.create_plan(request, user_id)
# ✓ Version 1 snapshot created automatically with complete plan state
```

**What happens:**
1. Plan is created with all initial data (Part 1 fields, strengths, needs, etc.)
2. All relationships are loaded into memory
3. Complete snapshot is created via `_create_plan_snapshot()`
4. Version 1 record is stored in `intervention_versions` table
5. Snapshot includes:
   - Initial plan metadata
   - All 8-part structure data (strengths, needs, goals, etc.)
   - `created_by` field (user who created the plan)
   - `parent_version_id` set to `null` (no previous version)

### 2. Plan Updates (Version 2+)

When a plan is updated via `PATCH /api/v1/intervention-plans/{id}`:

```python
# Example: Updating a plan
plan = await intervention_plan_service.update_plan(
    plan_id,
    request,
    user_id,
    create_version=True  # ← Controls snapshot creation
)
# ✓ New version snapshot created BEFORE changes are applied
```

**What happens:**
1. Plan is loaded with all relationships using `selectinload()`
2. **BEFORE applying changes**, snapshot of current state is created
3. Version number is incremented
4. Changes are applied to the plan
5. New version record is stored with the pre-update snapshot
6. Automatic change summary is generated from modified fields

**Important:** The snapshot captures the **BEFORE state**, not the after state. This enables accurate comparison of what changed.

### When Snapshots Are NOT Created

Snapshots are **not** created for:
- Adding/updating individual sections (strengths, needs, goals, etc.) via section-specific endpoints
- Signing a plan (parent signature endpoint)
- Reading/retrieving plan data (GET requests)
- Progress record updates

To create a snapshot when updating, use the main `update_plan` method with `create_version=True`.

## Fields Captured in Snapshots

Each snapshot is a complete JSON representation of the plan stored in the `snapshot_data` JSONB column.

### Required Top-Level Fields

Every snapshot **must** include these fields (enforced by database constraints):

```json
{
  "id": "plan-uuid",
  "child_id": "child-uuid",
  "created_by": "user-uuid",              // ← User who created this version
  "parent_version_id": "previous-version-uuid",  // ← Links to previous version
  "title": "Plan title",
  "status": "draft|active|completed",
  "version": 2,
  "child_name": "Student Name"
}
```

### Part 1: Child Identification & History

```json
{
  "date_of_birth": "2015-05-10",
  "diagnosis": "Autism Spectrum Disorder",
  "medical_history": "...",
  "educational_history": "...",
  "family_context": "...",
  "review_schedule": "quarterly",
  "next_review_date": "2026-05-01",
  "effective_date": "2026-02-01",
  "end_date": "2027-02-01"
}
```

### Part 2: Strengths

```json
{
  "strengths": [
    {
      "id": "strength-uuid",
      "category": "academic|social|behavioral|communication",
      "description": "...",
      "examples": "...",
      "order": 1
    }
  ]
}
```

### Part 3: Needs

```json
{
  "needs": [
    {
      "id": "need-uuid",
      "category": "academic|social|behavioral|communication",
      "description": "...",
      "priority": "high|medium|low",
      "baseline": "Current performance level",
      "order": 1
    }
  ]
}
```

### Part 4: SMART Goals

```json
{
  "goals": [
    {
      "id": "goal-uuid",
      "need_id": "need-uuid",
      "title": "...",
      "description": "...",
      "measurement_criteria": "How progress is measured",
      "measurement_baseline": "Starting point",
      "measurement_target": "Target achievement level",
      "achievability_notes": "...",
      "relevance_notes": "...",
      "target_date": "2026-06-01",
      "status": "in_progress",
      "progress_percentage": 25,
      "order": 1
    }
  ]
}
```

### Part 5: Strategies

```json
{
  "strategies": [
    {
      "id": "strategy-uuid",
      "goal_id": "goal-uuid",
      "title": "...",
      "description": "...",
      "responsible_party": "Teacher, Aide, Parent",
      "frequency": "Daily, 3x per week",
      "materials_needed": "...",
      "accommodations": "...",
      "order": 1
    }
  ]
}
```

### Part 6: Monitoring

```json
{
  "monitoring": [
    {
      "id": "monitoring-uuid",
      "goal_id": "goal-uuid",
      "method": "observation|assessment|data_collection",
      "description": "...",
      "frequency": "Weekly",
      "responsible_party": "...",
      "data_collection_tools": "...",
      "success_indicators": "...",
      "order": 1
    }
  ]
}
```

### Part 7: Parent Involvement

```json
{
  "parent_involvements": [
    {
      "id": "involvement-uuid",
      "activity_type": "communication|activity|resource",
      "title": "...",
      "description": "...",
      "frequency": "Weekly",
      "resources_provided": "...",
      "communication_method": "Email, Phone, In-person",
      "order": 1
    }
  ]
}
```

### Part 8: Consultations

```json
{
  "consultations": [
    {
      "id": "consultation-uuid",
      "specialist_type": "speech_therapist|occupational_therapist|psychologist",
      "specialist_name": "Dr. Smith",
      "organization": "County Special Services",
      "purpose": "...",
      "recommendations": "...",
      "consultation_date": "2026-01-15",
      "next_consultation_date": "2026-04-15",
      "notes": "...",
      "order": 1
    }
  ]
}
```

### Metadata & Signatures

```json
{
  "parent_signed": true,
  "parent_signature_date": "2026-02-10T14:30:00Z",
  "parent_signature_data": "...",
  "created_at": "2026-02-01T10:00:00Z",
  "updated_at": "2026-02-10T14:30:00Z"
}
```

## Version Lineage Tracking

Each snapshot includes `parent_version_id` to create a version chain:

```
Version 1 (Feb 1)  →  Version 2 (Feb 10)  →  Version 3 (Mar 1)
parent_version_id: null    parent_version_id: v1-uuid    parent_version_id: v2-uuid
```

This enables:
- Complete version history traversal
- Identifying when specific changes were introduced
- Building audit trails for regulatory compliance

## Limitations and Considerations

### 1. Relationship Loading Requirement

**CRITICAL:** Snapshots require all relationships to be loaded before creation.

✅ **Correct:**
```python
# Load plan with all relationships
plan = await intervention_plan_service._get_plan_with_relations(plan_id)
snapshot = await intervention_plan_service._create_plan_snapshot(plan)
```

❌ **Incorrect:**
```python
# Only load plan metadata (relationships unloaded)
plan = await db.execute(select(InterventionPlan).where(...))
snapshot = await intervention_plan_service._create_plan_snapshot(plan)
# ⚠️ Results in empty arrays for strengths, needs, goals, etc.
```

**Validation:** The `_create_plan_snapshot` method validates that relationships are loaded and logs warnings if any are unloaded:

```
WARNING: Creating plan snapshot with unloaded relationships: strengths, needs.
Plan ID: abc123. This may result in incomplete snapshot data.
```

### 2. Snapshot Reflects Current State (for backfills)

When backfilling missing snapshots (see Data Migration section), the snapshot is reconstructed from the **current plan state**, not the historical state at the time the version was created.

**Impact:** Backfilled version 1 snapshots may include data that was added later if the plan has been modified.

**Mitigation:** Backfilled snapshots include all required fields and 8-part structure, ensuring database constraints are met and future versioning works correctly.

### 3. Data Size Considerations

Snapshots store **complete plan data** in JSONB format. Large plans with many sections may result in:
- Larger `intervention_versions` table size
- Slower snapshot creation (typically <100ms)
- Increased database storage usage

**Best Practice:** This is acceptable trade-off for data integrity and audit capabilities. JSONB is efficiently indexed and compressed by PostgreSQL.

### 4. Concurrent Updates

If two users update the same plan simultaneously:
- Each will create a new version snapshot
- Version numbers auto-increment atomically
- Latest update wins (last write)
- Both versions are preserved in history

No data is lost, but the second user's changes will be based on a potentially stale snapshot.

### 5. UUID and DateTime Serialization

All UUIDs and datetimes are converted to strings in snapshots:
- **UUIDs** → `str(uuid)` format: `"a1b2c3d4-..."`
- **Dates** → ISO format: `"2026-02-17T10:00:00Z"`

When comparing versions, string comparisons are used.

## Comparison API

The snapshot system provides a powerful comparison API for analyzing changes between versions.

### Get Specific Version

Retrieve a specific version snapshot:

```python
GET /api/v1/intervention-plans/{plan_id}/versions/{version_number}
```

**Response:**
```json
{
  "id": "version-uuid",
  "plan_id": "plan-uuid",
  "version_number": 2,
  "created_by": "user-uuid",
  "change_summary": "Updated goals and strategies",
  "snapshot_data": { /* complete snapshot */ },
  "created_at": "2026-02-10T14:30:00Z"
}
```

### Compare Two Versions

Compare changes between two versions:

```python
GET /api/v1/intervention-plans/{plan_id}/versions/compare?version1=1&version2=2
```

**Response:**
```json
{
  "plan_id": "plan-uuid",
  "version1": 1,
  "version2": 2,
  "version1_date": "2026-02-01T10:00:00Z",
  "version2_date": "2026-02-10T14:30:00Z",
  "changes": {
    "status": {
      "old": "draft",
      "new": "active",
      "changed": true
    },
    "goals": {
      "added": [
        {
          "id": "goal-2-uuid",
          "title": "Improve reading comprehension"
        }
      ],
      "removed": [],
      "modified": [
        {
          "id": "goal-1-uuid",
          "field": "progress_percentage",
          "old": 0,
          "new": 25
        }
      ]
    }
  }
}
```

### Change Detection Logic

The comparison algorithm detects:

**1. Simple Field Changes:**
```python
# Compares main plan fields
if snapshot1["status"] != snapshot2["status"]:
    changes["status"] = {
        "old": snapshot1["status"],
        "new": snapshot2["status"],
        "changed": True
    }
```

**2. Array Changes (sections):**
- **Added items:** Items in version2 not in version1 (by ID)
- **Removed items:** Items in version1 not in version2 (by ID)
- **Modified items:** Items with same ID but different field values

**Example:**
```json
{
  "strengths": {
    "added": [{"id": "str-3", "description": "..."}],
    "removed": [{"id": "str-1", "description": "..."}],
    "modified": [
      {
        "id": "str-2",
        "changes": {
          "description": {
            "old": "Shows leadership",
            "new": "Demonstrates strong leadership in group settings"
          }
        }
      }
    ]
  }
}
```

## Data Migration Notes

The snapshot system includes tools for migrating existing data.

### Migration Background

Early versions of the intervention plan system had bugs:
1. **Version 1 snapshots had `snapshot_data = null`**
2. **Missing `created_by` and `parent_version_id` fields** in snapshots
3. **No relationship loading validation** (caused incomplete snapshots)

### Backfill Script

The `backfill_snapshot_data.py` script repairs existing snapshot data.

**Location:** `ai-service/scripts/backfill_snapshot_data.py`

### Usage

**1. Preview changes (dry-run):**
```bash
python scripts/backfill_snapshot_data.py --dry-run --verbose
```

**2. Preview limited number:**
```bash
python scripts/backfill_snapshot_data.py --dry-run --limit 10
```

**3. Backfill specific plan:**
```bash
python scripts/backfill_snapshot_data.py --plan-id <plan-uuid>
```

**4. Backfill all (LIVE MODE):**
```bash
python scripts/backfill_snapshot_data.py
# ⚠️ Warning: 3-second countdown before execution
```

### What the Script Does

**For versions with `snapshot_data = null`:**
1. Loads plan with all relationships
2. Reconstructs complete snapshot from current plan state
3. Adds all required fields including `created_by` and `parent_version_id`
4. Includes all 8-part structure sections
5. Updates `snapshot_data` column

**For versions with existing snapshots missing fields:**
1. Loads existing snapshot
2. Adds missing `created_by` field (from plan.created_by)
3. Adds missing `parent_version_id` (by querying previous version)
4. Preserves all existing snapshot data (doesn't overwrite)
5. Updates only the missing fields

### Example Output

```
==================================================================================
Intervention Plan Snapshot Backfill - DRY RUN
==================================================================================

Running in DRY RUN mode - no data will be modified

Checking 147 version records...

✓ Version 1 of plan abc123: Already complete
⚠ Would backfill version 1 of plan def456: NULL snapshot_data
⚠ Would backfill version 2 of plan def456: missing fields: created_by, parent_version_id
✓ Version 1 of plan ghi789: Already complete

==================================================================================
Backfill Report

Overall Statistics:
  Total versions processed: 147
  Versions backfilled (NULL data): 23
  Versions updated (missing fields): 31
  Versions skipped (already complete): 93
  Errors encountered: 0

Next Steps:
  • Run without --dry-run to apply backfill changes

==================================================================================
Preview: 54 versions would be backfilled/updated
```

### Database Constraints

After migration, database constraints validate snapshot data:

**Constraint 1: Required Fields**
```sql
CHECK (
  snapshot_data IS NULL OR (
    jsonb_typeof(snapshot_data) = 'object' AND
    snapshot_data != '{}'::jsonb AND
    snapshot_data ? 'created_by' AND
    snapshot_data ? 'parent_version_id' AND
    snapshot_data ? 'strengths' AND
    snapshot_data ? 'needs' AND
    -- ... all 8-part structure fields
  )
)
```

**Constraint 2: Valid Arrays**
```sql
CHECK (
  snapshot_data IS NULL OR (
    jsonb_typeof(snapshot_data->'strengths') = 'array' AND
    jsonb_typeof(snapshot_data->'needs') = 'array' AND
    -- ... all section arrays
  )
)
```

These constraints prevent future bugs by enforcing snapshot data integrity at the database level.

## Best Practices

### 1. Always Load Relationships Before Snapshot

```python
# ✅ Good
plan = await self._get_plan_with_relations(plan_id)
snapshot = await self._create_plan_snapshot(plan)

# ❌ Bad
plan = await db.get(InterventionPlan, plan_id)
snapshot = await self._create_plan_snapshot(plan)  # Missing relationships!
```

### 2. Create Versions for Significant Changes

```python
# Significant change - create version
await service.update_plan(
    plan_id,
    request,
    user_id,
    create_version=True  # ← Enable versioning
)

# Minor change - skip versioning
await service.update_plan(
    plan_id,
    request,
    user_id,
    create_version=False
)
```

### 3. Use Comparison API for Audit Trails

When displaying change history to users:

```python
# Get all versions
versions = plan.versions  # Ordered by version_number

# Compare adjacent versions to build timeline
for i in range(len(versions) - 1):
    changes = await service.compare_versions(
        plan_id,
        versions[i].version_number,
        versions[i + 1].version_number
    )
    # Display changes in UI
```

### 4. Monitor Snapshot Creation Performance

Log slow snapshot creation:

```python
import time

start = time.time()
snapshot = await self._create_plan_snapshot(plan)
duration = time.time() - start

if duration > 0.1:  # 100ms threshold
    logger.warning(
        f"Slow snapshot creation: {duration:.2f}s for plan {plan.id}"
    )
```

### 5. Run Backfill After Database Restore

If you restore from a database backup that predates the snapshot fixes:

```bash
# 1. Check what needs backfilling
python scripts/backfill_snapshot_data.py --dry-run --verbose

# 2. Run backfill
python scripts/backfill_snapshot_data.py

# 3. Verify constraints pass
psql -c "SELECT COUNT(*) FROM intervention_versions WHERE snapshot_data IS NULL;"
# Should return 0
```

## Troubleshooting

### Issue: Incomplete Snapshot Data

**Symptom:** Snapshot has empty arrays for some sections

**Cause:** Relationships were not loaded before snapshot creation

**Solution:**
```python
# Ensure relationships are loaded
plan = await self._get_plan_with_relations(plan_id)
```

**Validation:** Check logs for warnings:
```
WARNING: Creating plan snapshot with unloaded relationships: strengths, needs
```

### Issue: Constraint Violation on Insert

**Symptom:** Error when creating version:
```
ERROR: new row for relation "intervention_versions" violates check constraint
"ck_intervention_versions_snapshot_data_valid"
```

**Cause:** Snapshot missing required fields (`created_by`, `parent_version_id`, or sections)

**Solution:** Verify `_create_plan_snapshot` includes all required fields (see code at lines 1229-1330)

### Issue: Backfill Script Fails

**Symptom:** Backfill reports errors for specific plans

**Possible causes:**
1. Plan was deleted (version references non-existent plan)
2. Database connection timeout
3. Large plan exceeds memory limits

**Solution:**
```bash
# Backfill with verbose logging to identify issue
python scripts/backfill_snapshot_data.py --dry-run --verbose

# Skip problematic plans
python scripts/backfill_snapshot_data.py --limit 100

# Backfill one plan at a time
python scripts/backfill_snapshot_data.py --plan-id <uuid>
```

### Issue: Version Number Gaps

**Symptom:** Plan has versions 1, 2, 5 (missing 3, 4)

**Cause:** Versions 3 and 4 were deleted or transaction rolled back

**Impact:** No impact on functionality. Version numbers don't need to be sequential.

**Note:** `parent_version_id` maintains the correct lineage chain regardless of gaps.

## Implementation Reference

**Core Methods:**
- `_create_plan_snapshot()` - Lines 1139-1330 in `intervention_plan_service.py`
- `get_version()` - Lines 647-691
- `compare_versions()` - Lines 693-796

**Database Schema:**
- `InterventionVersion` model - Lines 944-1006 in `models/intervention_plan.py`

**Migration Files:**
- Validation constraints: `20260217_150500_c4f9d8e3a5b2_validate_snapshot_data.py`

**Scripts:**
- Backfill tool: `scripts/backfill_snapshot_data.py`

## Summary

✅ **Snapshots are created automatically** on plan creation and updates
✅ **Complete plan state is captured** including all 8-part structure sections
✅ **Version lineage is tracked** via `parent_version_id` links
✅ **Comparison API** enables detailed change analysis
✅ **Database constraints** enforce data integrity
✅ **Migration tools** repair existing data

For questions or issues, refer to the implementation code or contact the development team.
