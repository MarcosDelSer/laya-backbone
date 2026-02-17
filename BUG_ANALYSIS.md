# Bug Analysis: Intervention Plan Snapshot (_create_plan_snapshot)

**Date**: 2026-02-17
**Service**: ai-service
**File**: `ai-service/app/services/intervention_plan_service.py`
**Method**: `_create_plan_snapshot` (lines 910-1030)
**Severity**: HIGH - Data Integrity & Regulatory Compliance Issue

---

## Overview

The `_create_plan_snapshot` method is responsible for creating JSON snapshots of intervention plans for version history tracking. Multiple critical bugs have been identified that result in incomplete snapshots, missing critical audit fields, and potential data loss.

---

## Bug #1: Missing `created_by` Field in Snapshot

**Location**: `ai-service/app/services/intervention_plan_service.py`, lines 919-937
**Severity**: HIGH

### Description
The snapshot does not capture the `created_by` field from the intervention plan, which stores the UUID of the user who created the plan.

### Current Code (Line 919-937)
```python
return {
    "id": str(plan.id),
    "child_id": str(plan.child_id),
    "title": plan.title,
    "status": plan.status,
    # ... other fields ...
    # MISSING: "created_by": str(plan.created_by)
}
```

### Impact
- **Audit Trail Broken**: Cannot determine who created the plan when reviewing historical snapshots
- **Regulatory Compliance**: May violate regulatory requirements for maintaining complete audit trails
- **Data Loss**: If the original plan is deleted or modified, there's no record of the original creator in the snapshot
- **Accountability**: Unable to track accountability for plan creation in historical records

### Expected Fix
Add `"created_by": str(plan.created_by)` to the snapshot dictionary after `child_id`.

---

## Bug #2: Missing `parent_version_id` Field in Snapshot

**Location**: `ai-service/app/services/intervention_plan_service.py`, lines 919-937
**Severity**: HIGH

### Description
The snapshot does not capture the `parent_version_id` field, which links to the previous version of the plan for version history tracking.

### Current Code (Line 919-937)
```python
return {
    "id": str(plan.id),
    "child_id": str(plan.child_id),
    # ... other fields ...
    # MISSING: "parent_version_id": str(plan.parent_version_id) if plan.parent_version_id else None
}
```

### Impact
- **Version Chain Broken**: Cannot reconstruct the version lineage from snapshots alone
- **Data Migration Risk**: If database schema changes, snapshots cannot be used to rebuild version history
- **Comparison Impossible**: Difficult to trace which version a snapshot derived from
- **Historical Integrity**: Loss of version ancestry information in archived snapshots

### Expected Fix
Add `"parent_version_id": str(plan.parent_version_id) if plan.parent_version_id else None` to the snapshot dictionary.

---

## Bug #3: Initial Version Has `snapshot_data=None`

**Location**: `ai-service/app/services/intervention_plan_service.py`, line 206
**Severity**: CRITICAL

### Description
When creating a new intervention plan, the initial version (version 1) is created with `snapshot_data=None` instead of capturing the actual plan state.

### Current Code (Line 200-208)
```python
# Create initial version record
version = InterventionVersion(
    plan_id=plan.id,
    version_number=1,
    created_by=user_id,
    change_summary="Initial plan creation",
    snapshot_data=None,  # Will be populated on subsequent versions
)
self.db.add(version)
```

### Impact
- **Critical Data Loss**: Version 1 of every plan has no snapshot data - cannot recover initial state
- **Incomplete History**: The very first version of the plan is lost forever
- **Rollback Impossible**: Cannot revert to the original plan state if needed
- **Audit Failure**: Regulatory audits require complete history including initial state
- **Comparison Failure**: Cannot compare current state to original baseline

### Root Cause
The comment "Will be populated on subsequent versions" indicates this was intentional, but it violates the principle that ALL versions should have complete snapshots.

### Expected Fix
1. After `await self.db.flush()` (line 152), call `await self._create_plan_snapshot(plan)` to generate snapshot
2. Change line 206 from `snapshot_data=None` to `snapshot_data=snapshot` using the generated snapshot
3. Note: Must flush before creating snapshot to ensure all related entities have IDs

---

## Bug #4: No Relationship Loading Validation

**Location**: `ai-service/app/services/intervention_plan_service.py`, lines 938-1029
**Severity**: MEDIUM-HIGH

### Description
The method iterates over plan relationships (`strengths`, `needs`, `goals`, etc.) without validating that these relationships are loaded. If lazy loading is in effect or relationships aren't eagerly loaded, this could cause:
1. Database N+1 query issues
2. Incomplete snapshots (empty arrays)
3. Attribute errors if relationships aren't loaded

### Current Code (Lines 938-946)
```python
"strengths": [
    {
        "id": str(s.id),
        "category": s.category,
        "description": s.description,
        "examples": s.examples,
        "order": s.order,
    }
    for s in plan.strengths  # No validation that strengths are loaded
],
```

### Impact
- **Silent Failures**: Snapshot may appear successful but contain empty arrays
- **Incomplete Data**: Missing related entities in snapshot renders it useless
- **Performance Issues**: Lazy loading could trigger N+1 queries within snapshot creation
- **Unpredictable Behavior**: Depends on how the plan object was loaded (with/without selectinload)

### Observed Behavior
The method `_get_plan_with_relations()` (line 838) properly loads relationships, but:
- `update_plan()` method (line 256) calls `_create_plan_snapshot()` WITHOUT using `_get_plan_with_relations()`
- This means snapshots created during updates may have unloaded relationships

### Expected Fix
Add validation at the start of `_create_plan_snapshot()`:
```python
# Validate relationships are loaded
if not hasattr(plan, '__dict__'):
    raise PlanVersionError("Plan relationships not loaded for snapshot")

# Or provide warning/handling for empty relationships
for rel_name in ['strengths', 'needs', 'goals', 'strategies', 'monitoring', 'parent_involvements', 'consultations']:
    if not hasattr(plan, rel_name):
        logger.warning(f"Relationship {rel_name} not loaded for snapshot of plan {plan.id}")
```

---

## Bug #5: No Snapshot Retrieval Functionality

**Location**: `ai-service/app/services/intervention_plan_service.py` (entire file)
**Severity**: MEDIUM

### Description
There is no method to retrieve a specific version snapshot by version number. The `get_plan_history()` method (line 558) returns all versions, but there's no way to get just one specific version.

### Missing Method
```python
async def get_version(self, plan_id: UUID, version_number: int, user_id: UUID) -> VersionResponse:
    """Get a specific version snapshot."""
    # NOT IMPLEMENTED
```

### Impact
- **Poor API Design**: Clients must fetch all versions and filter client-side
- **Performance Waste**: Unnecessary data transfer for large version histories
- **Developer Experience**: Awkward to use in UI/reports that need specific versions

### Expected Fix
Add `get_version()` method that queries `intervention_versions` table by `plan_id` and `version_number`.

---

## Bug #6: No Snapshot Comparison Functionality

**Location**: `ai-service/app/services/intervention_plan_service.py` (entire file)
**Severity**: MEDIUM

### Description
There is no method to compare two version snapshots to show what changed between versions. This is critical for audit trails and understanding plan evolution.

### Missing Method
```python
async def compare_versions(self, plan_id: UUID, version1: int, version2: int, user_id: UUID) -> dict:
    """Compare two version snapshots and return differences."""
    # NOT IMPLEMENTED
```

### Impact
- **Manual Comparison Required**: Users must manually diff JSON snapshots
- **Audit Difficulty**: Hard to answer "what changed between version 3 and version 5?"
- **Regulatory Gaps**: May not satisfy audit requirements for change tracking
- **User Experience**: Cannot show change history in UI

### Expected Fix
Add `compare_versions()` method that:
1. Retrieves both version snapshots
2. Performs deep comparison of snapshot dictionaries
3. Returns structured diff showing added/removed/changed fields

---

## Summary of Bugs

| Bug # | Description | Severity | Lines | Impact |
|-------|-------------|----------|-------|--------|
| 1 | Missing `created_by` field | HIGH | 919-937 | Audit trail broken, compliance risk |
| 2 | Missing `parent_version_id` field | HIGH | 919-937 | Version chain broken, migration risk |
| 3 | Initial version `snapshot_data=None` | CRITICAL | 206 | Data loss, cannot recover v1 state |
| 4 | No relationship loading validation | MEDIUM-HIGH | 938-1029 | Silent failures, incomplete snapshots |
| 5 | No `get_version()` method | MEDIUM | N/A | Poor API, performance waste |
| 6 | No `compare_versions()` method | MEDIUM | N/A | Manual diff required, audit gaps |

---

## Regulatory & Compliance Implications

**CRITICAL**: These bugs may violate regulatory requirements for special needs intervention plans:

1. **IDEA Compliance** (Individuals with Disabilities Education Act):
   - Requires complete, accurate record-keeping for IEP-like plans
   - Bug #3 means initial plans cannot be recovered - VIOLATION

2. **FERPA Compliance** (Family Educational Rights and Privacy Act):
   - Requires audit trails showing who accessed/modified educational records
   - Bug #1 (missing `created_by`) breaks audit trail - VIOLATION

3. **HIPAA Compliance** (if medical data is involved):
   - Requires complete audit logs for PHI modifications
   - Bugs #1, #2, #3 compromise audit completeness - POTENTIAL VIOLATION

4. **General Data Protection Best Practices**:
   - All bugs undermine data integrity and historical accuracy
   - Risk of legal liability if plan disputes arise

---

## Recommendations

### Immediate Actions (High Priority)
1. **Fix Bug #3** - Populate initial version snapshot to prevent ongoing data loss
2. **Fix Bugs #1 & #2** - Add missing fields to snapshot dictionary
3. **Add Data Migration** - Backfill existing NULL snapshots (best effort)

### Follow-up Actions (Medium Priority)
4. **Fix Bug #4** - Add relationship loading validation or explicit preloading
5. **Add Bug #5** - Implement `get_version()` method
6. **Add Bug #6** - Implement `compare_versions()` method

### Testing Requirements
- Unit tests for complete snapshot field coverage
- Integration tests for version 1 snapshot population
- Test relationship loading in various scenarios
- Test snapshot comparison logic

---

## Affected Data Assessment

**Action Required**: Run audit script to assess how many existing plans have corrupted snapshot data:

```bash
python ai-service/scripts/audit_snapshot_data.py
```

Expected findings:
- All version 1 records will have `snapshot_data=None` (Bug #3)
- All versions missing `created_by` and `parent_version_id` fields (Bugs #1, #2)
- Possible empty relationship arrays if relationships weren't loaded (Bug #4)

---

**Analysis Completed By**: auto-claude
**Next Steps**: Proceed to subtask-1-2 to create audit script and assess affected data
