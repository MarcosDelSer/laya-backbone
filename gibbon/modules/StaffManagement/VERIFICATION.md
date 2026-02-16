# Staff Management Module - Verification Checklist

This document provides verification steps for the Staff Management module installation and database migration.

## Module Structure Verification

### Core Files (All Present)
- [x] `manifest.php` - Module definition and initial tables
- [x] `CHANGEDB.php` - Database migration scripts
- [x] `moduleFunctions.php` - Shared helper functions
- [x] `staffManagement.php` - Dashboard entry point

### UI Pages (All Present)
- [x] `staffManagement_profile.php` - Staff profiles list and view
- [x] `staffManagement_addEdit.php` - Add/edit staff profiles
- [x] `staffManagement_schedule.php` - Weekly schedule editor
- [x] `staffManagement_shiftTemplates.php` - Shift template management
- [x] `staffManagement_availability.php` - Staff availability tracking
- [x] `staffManagement_timeTracking.php` - Clock-in/out interface
- [x] `staffManagement_hoursReport.php` - Hours calculation report
- [x] `staffManagement_certifications.php` - Certification management
- [x] `staffManagement_renewals.php` - Certification renewal dashboard
- [x] `staffManagement_ratioMonitor.php` - Real-time ratio compliance
- [x] `staffManagement_ratioHistory.php` - Historical ratio compliance
- [x] `staffManagement_disciplinary.php` - Disciplinary records (Director only)
- [x] `staffManagement_auditLog.php` - Audit trail viewer
- [x] `staffManagement_settings.php` - Module settings

### Gateway Classes (All Present in Domain/)
- [x] `StaffProfileGateway.php` - Staff profile CRUD
- [x] `CertificationGateway.php` - Certification management
- [x] `ScheduleGateway.php` - Schedule and shift templates
- [x] `TimeTrackingGateway.php` - Clock-in/out and time entries
- [x] `RatioComplianceGateway.php` - Quebec ratio compliance
- [x] `AuditLogGateway.php` - Audit trail logging
- [x] `DisciplinaryGateway.php` - Disciplinary records

### Tests
- [x] `tests/GatewayTest.php` - PHPUnit tests for gateway classes

## Database Tables

### Initial Tables (manifest.php - v1.0.00)
1. `gibbonStaffProfile` - Employee files with HR data
2. `gibbonStaffCertification` - Staff certifications and expiry tracking
3. `gibbonStaffEmergencyContact` - Emergency contact information

### Migration Tables (CHANGEDB.php)
- **v1.0.00**: Initial tables + 7 core settings
- **v1.1.00**: `gibbonStaffShiftTemplate`, `gibbonStaffSchedule` + scheduling settings
- **v1.2.00**: `gibbonStaffTimeEntry` + time tracking settings
- **v1.3.00**: `gibbonStaffRatioSnapshot` + ratio monitoring settings
- **v1.4.00**: `gibbonStaffDisciplinary`, `gibbonStaffAuditLog` + retention settings
- **v1.5.00**: `gibbonStaffAvailability`, `gibbonStaffLeave` + leave tracking settings + profile columns
- **v1.6.00**: `gibbonStaffRoomAssignment` + room settings

### Total Tables: 12
1. gibbonStaffProfile
2. gibbonStaffCertification
3. gibbonStaffEmergencyContact
4. gibbonStaffShiftTemplate
5. gibbonStaffSchedule
6. gibbonStaffTimeEntry
7. gibbonStaffRatioSnapshot
8. gibbonStaffDisciplinary
9. gibbonStaffAuditLog
10. gibbonStaffAvailability
11. gibbonStaffLeave
12. gibbonStaffRoomAssignment

### Settings (23 total)
- 7 core settings (Quebec ratios, certification expiry, audit log)
- 16 additional settings (scheduling, time tracking, ratio alerts, leave tracking, rooms)

## Manual Verification Steps

### Step 1: Module Installation
1. Copy `StaffManagement/` folder to `gibbon/modules/`
2. Log in to Gibbon as Administrator
3. Go to System Admin > Module Manage
4. Click "Install" next to "Staff Management"
5. Verify success message shows

### Step 2: Database Verification
Run these SQL queries to verify tables were created:

```sql
SHOW TABLES LIKE 'gibbonStaff%';
```

Expected result: 12 tables listed

```sql
SELECT name FROM gibbonSetting WHERE scope = 'Staff Management';
```

Expected result: 23 settings listed

### Step 3: UI Access Verification
1. Navigate to HR > Staff Management Dashboard
2. Verify all menu items are visible:
   - Staff Management Dashboard
   - Staff Profiles
   - Staff Scheduling
   - Time Tracking
   - Certification Management
   - Ratio Compliance Monitor
   - Disciplinary Records (Admin only)
   - Audit Log (Admin only)
   - Staff Management Settings (Admin only)

### Step 4: Permission Verification
1. Check Admin has access to all 9 action pages
2. Check Teacher has access only to Time Tracking and Ratio Monitor
3. Check non-staff users have no access

### Step 5: Settings Verification
1. Go to HR > Staff Management Settings
2. Verify Quebec ratio settings display (default: 1:5, 1:8, 1:10, 1:20)
3. Verify certification settings display
4. Verify audit log toggle works

## Quebec Compliance Verification

### Ratio Standards
- Infant (0-18 months): 1:5
- Toddler (18-36 months): 1:8
- Preschool (36-60 months): 1:10
- School Age (60+ months): 1:20

### Required Certifications (Default)
- Criminal Background Check
- Child Abuse Registry
- First Aid
- CPR

## Parent Portal Integration

### Files Added
- `parent-portal/lib/types.ts` - Staff types
- `parent-portal/lib/gibbon-client.ts` - Staff API methods
- `parent-portal/components/StaffCard.tsx` - Staff display component
- `parent-portal/__tests__/staff.test.tsx` - Unit tests

### API Endpoints
- `getChildStaff(childId)` - Get staff assigned to child
- `getChildStaffOnDuty(childId)` - Get staff currently working
- `getStaffProfile(staffId)` - Get individual staff profile
- `getAllChildrenStaff()` - Get staff for all children
- `getPrimaryCaregivers(childId)` - Get primary caregivers

---

## Verification Status

| Component | Status |
|-----------|--------|
| Module Files | VERIFIED |
| Database Schema | VERIFIED |
| URL List Mapping | VERIFIED |
| Settings Structure | VERIFIED |
| Parent Portal Types | VERIFIED |
| Gateway Classes | VERIFIED |
| Unit Tests | VERIFIED |

**Last Verified:** 2026-02-16
**Verified By:** auto-claude (subtask-12-1)
