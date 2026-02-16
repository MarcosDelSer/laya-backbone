# Incident Report System - End-to-End Verification Guide

## Overview

This document provides comprehensive end-to-end verification steps for the Incident Report System (Task 053). The verification covers the complete flow from incident creation in Gibbon to parent acknowledgment in the parent-portal.

## Prerequisites

Before running E2E verification:

1. **Database Setup**: Ensure the database migrations have been applied (CHANGEDB.php v1.5.00 and v1.6.00)
2. **Services Running**:
   - Gibbon CMS running on `http://localhost:8080`
   - Parent Portal running on `http://localhost:3000`
   - Database (MySQL) accessible
3. **Test Data**:
   - At least one child enrolled in the system
   - Parent account linked to the child
   - Staff account with CareTracking permissions

---

## E2E Verification Steps

### Step 1: Create Incident in Gibbon

**URL**: `http://localhost:8080/gibbon/index.php?q=/modules/CareTracking/careTracking_incidents_add.php`

**Actions**:
1. Log in as staff member with CareTracking access
2. Navigate to: Modules > Care Tracking > Incidents > Add New Incident
3. Fill out the incident form:
   - Select a child from the dropdown
   - Set Date and Time
   - Select Incident Type (e.g., "Minor Injury")
   - Select Incident Category (e.g., "Fall")
   - Select Severity Level:
     - `Low` or `Medium` for standard notification
     - `High` or `Critical` for director escalation
   - Select Body Part Affected (if applicable)
   - Enter Description (required)
   - Enter First Aid / Action Taken
   - Set Medical Professional Consulted (Yes/No)
   - Set Follow-up Required (Yes/No)
   - Optionally upload a photo
4. Click "Submit Incident Report"

**Expected Result**:
- Success message: "Incident has been logged successfully"
- Redirect to incidents list page
- New incident visible in the incidents table

**Database Verification**:
```sql
-- Verify incident was created
SELECT * FROM gibbonCareIncident
ORDER BY gibbonCareIncidentID DESC
LIMIT 1;

-- Check for enhanced fields
SELECT gibbonCareIncidentID, gibbonPersonID, type, severity,
       incidentCategory, bodyPart, medicalConsulted, followUpRequired,
       parentNotified, parentNotifiedTime
FROM gibbonCareIncident
ORDER BY gibbonCareIncidentID DESC
LIMIT 1;
```

---

### Step 2: Verify Notification is Queued

**Verification Method**: Database query

**Actions**:
1. After creating the incident, check the notification queue
2. Verify parent notification was created

**Database Verification**:
```sql
-- Check notification was queued for parent
SELECT nq.*, p.preferredName, p.surname
FROM gibbonNotificationQueue nq
INNER JOIN gibbonPerson p ON p.gibbonPersonID = nq.gibbonPersonID
WHERE nq.notificationType = 'incident_parent_notification'
ORDER BY nq.gibbonNotificationQueueID DESC
LIMIT 5;

-- For High/Critical severity, also check director notification
SELECT nq.*, p.preferredName, p.surname
FROM gibbonNotificationQueue nq
INNER JOIN gibbonPerson p ON p.gibbonPersonID = nq.gibbonPersonID
WHERE nq.notificationType = 'incident_director_escalation'
ORDER BY nq.gibbonNotificationQueueID DESC
LIMIT 5;

-- Verify the incident was marked as parent notified
SELECT gibbonCareIncidentID, parentNotified, parentNotifiedTime
FROM gibbonCareIncident
WHERE parentNotified = 'Y'
ORDER BY gibbonCareIncidentID DESC
LIMIT 1;
```

**Expected Result**:
- New record in `gibbonNotificationQueue` with:
  - `notificationType` = 'incident_parent_notification'
  - `status` = 'pending'
  - `gibbonPersonID` = parent's person ID
- Incident record has `parentNotified` = 'Y' and `parentNotifiedTime` is set

---

### Step 3: Verify Incident Appears in Parent Portal

**URL**: `http://localhost:3000/incidents`

**Actions**:
1. Log in to parent-portal as the parent of the child
2. Navigate to Incidents page
3. Look for the newly created incident

**Expected Result**:
- Incident card visible with:
  - Child's name
  - Incident date and time
  - Severity indicator (color-coded bar)
  - Category icon
  - Status badge showing "Pending Acknowledgment"
  - "View Details" and "Acknowledge" buttons

**Visual Verification Checklist**:
- [ ] Incident card displays correct child name
- [ ] Date and time match what was entered
- [ ] Severity color matches level (blue=minor, yellow=moderate, orange=serious, red=severe)
- [ ] Category icon is appropriate
- [ ] Pending incidents banner shows count
- [ ] "Acknowledge" button is visible

---

### Step 4: Acknowledge Incident and Verify Database Update

**URL**: `http://localhost:3000/incidents/<incident-id>`

**Actions**:
1. From the incidents list, click "View Details" on the pending incident
2. Review the incident details:
   - Description
   - Action Taken
   - Follow-up Notes (if any)
   - Photo documentation (if any)
   - Timeline
3. Click "Acknowledge Incident" button
4. In the acknowledgment modal:
   - Review the incident summary
   - Optionally add parent notes
   - Provide signature (draw on signature canvas)
   - Check the acknowledgment checkbox
   - Click "Acknowledge" to submit

**Expected Result**:
- Modal closes
- Incident status changes to "Acknowledged"
- Timeline shows acknowledgment event
- Success toast/notification appears

**Database Verification**:
```sql
-- Verify parentAcknowledged is set to 'Y'
SELECT gibbonCareIncidentID,
       parentNotified, parentNotifiedTime,
       parentAcknowledged, parentAcknowledgedTime,
       parentAcknowledgedNotes
FROM gibbonCareIncident
WHERE gibbonCareIncidentID = <incident_id>;

-- Expected: parentAcknowledged = 'Y', parentAcknowledgedTime is set
```

---

## Automated Test Commands

### PHP Unit Tests
```bash
# Run E2E verification tests
cd gibbon
./vendor/bin/phpunit modules/CareTracking/tests/IncidentE2EVerificationTest.php

# Run all CareTracking tests
./vendor/bin/phpunit modules/CareTracking/tests/
```

### Parent Portal Tests
```bash
# Run component tests
cd parent-portal
npm test -- --run

# Run specific incident tests
npm test -- --run IncidentCard
npm test -- --run IncidentList
npm test -- --run IncidentE2E
```

### TypeScript Compilation Check
```bash
cd parent-portal
npx tsc --noEmit
```

---

## Summary Checklist

| Step | Description | Status |
|------|-------------|--------|
| 1 | Create incident in Gibbon | [ ] |
| 2 | Verify notification queued | [ ] |
| 3 | Incident visible in parent-portal | [ ] |
| 4 | Acknowledge and verify database | [ ] |

---

## Files Involved in E2E Flow

### Gibbon (Backend)
- `gibbon/modules/CareTracking/careTracking_incidents_add.php` - Incident creation form
- `gibbon/modules/CareTracking/Domain/IncidentGateway.php` - Data access layer
- `gibbon/modules/CareTracking/Domain/IncidentNotificationService.php` - Notification handling
- `gibbon/modules/CareTracking/careTracking_incidents_view.php` - View incident details

### Parent Portal (Frontend)
- `parent-portal/app/incidents/page.tsx` - Incidents list page
- `parent-portal/app/incidents/[id]/page.tsx` - Incident detail page
- `parent-portal/components/IncidentCard.tsx` - Incident card component
- `parent-portal/components/IncidentAcknowledge.tsx` - Acknowledgment modal
- `parent-portal/lib/gibbon-client.ts` - API client methods
- `parent-portal/lib/types.ts` - TypeScript interfaces

### Database Tables
- `gibbonCareIncident` - Incident records
- `gibbonNotificationQueue` - Notification queue
- `gibbonCareIncidentPattern` - Pattern detection alerts
- `gibbonCareIncidentEscalation` - Escalation records
