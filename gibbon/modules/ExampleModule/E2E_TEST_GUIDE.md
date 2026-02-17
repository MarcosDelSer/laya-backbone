# ExampleModule - End-to-End Test Guide

This guide provides comprehensive step-by-step instructions for manually testing the complete ExampleModule functionality, including role-based permissions, CRUD operations, database integrity, and error checking.

## Test Objectives

By following this guide, you will verify:
1. ✅ Module installation and activation
2. ✅ Role-based permission enforcement (Admin, Teacher, Student, Parent)
3. ✅ All CRUD operations (Create, Read, Update, Delete)
4. ✅ Database records creation and integrity
5. ✅ Form validation and error handling
6. ✅ No PHP errors or warnings in logs
7. ✅ Security measures (SQL injection prevention, XSS protection)

---

## Prerequisites

Before beginning the test:

- [ ] Gibbon is installed and running (http://localhost:8080)
- [ ] ExampleModule is installed and active (v1.0.00)
- [ ] You have access to accounts with different roles:
  - [ ] System Administrator account
  - [ ] Teacher account
  - [ ] Student account
  - [ ] Parent account
- [ ] MySQL database is accessible for verification
- [ ] PHP error logs are accessible
- [ ] Module database migrations have been applied

**Start Services:**
```bash
docker-compose up -d gibbon mysql
# or
docker compose up -d gibbon mysql
```

---

## Test Environment Setup

### 1. Verify Module Installation

**Login as System Administrator**

1. Go to: **System Admin > Manage Modules**
   - URL: `http://localhost:8080/index.php?q=/modules/System/module_manage.php`
2. Find **Example Module** in the list
3. Verify:
   - [ ] Status: Active
   - [ ] Version: 1.0.00
   - [ ] Type: Additional
   - [ ] Category: Other

### 2. Verify Module Settings

1. Go to: **Example Module > Settings**
   - URL: `http://localhost:8080/index.php?q=/modules/Example Module/exampleModule_settings.php`
2. Verify default settings:
   - [ ] Enable Feature: Yes (enabled)
   - [ ] Maximum Items: 50

### 3. Verify Database Schema

**Via MySQL CLI:**
```bash
# Access MySQL container
docker-compose exec mysql mysql -u gibbon_user -pchangeme gibbon

# Check table exists
SHOW TABLES LIKE 'gibbonExampleEntity';

# Verify table structure
DESCRIBE gibbonExampleEntity;

# Check settings
SELECT * FROM gibbonSetting WHERE scope='Example Module';

# Expected settings:
# | name          | value |
# |---------------|-------|
# | enableFeature | Y     |
# | maxItems      | 50    |
```

**Expected Table Structure:**
```
+------------------------+------------------+------+-----+-------------------+
| Field                  | Type             | Null | Key | Default           |
+------------------------+------------------+------+-----+-------------------+
| gibbonExampleEntityID  | int(10) unsigned | NO   | PRI | NULL              |
| gibbonPersonID         | int(10) unsigned | NO   | MUL | NULL              |
| gibbonSchoolYearID     | int(3) unsigned  | NO   | MUL | NULL              |
| title                  | varchar(100)     | NO   |     | NULL              |
| description            | text             | YES  |     | NULL              |
| status                 | enum(...)        | NO   | MUL | pending           |
| createdByID            | int(10) unsigned | YES  |     | NULL              |
| timestampCreated       | timestamp        | NO   |     | CURRENT_TIMESTAMP |
| timestampModified      | timestamp        | YES  |     | NULL              |
+------------------------+------------------+------+-----+-------------------+
```

**Verification Checklist:**
- [ ] Table `gibbonExampleEntity` exists
- [ ] Table uses utf8mb4_unicode_ci collation
- [ ] All columns match expected structure
- [ ] Primary key is set correctly
- [ ] Indexes exist on gibbonPersonID, gibbonSchoolYearID, status
- [ ] Module settings exist in gibbonSetting table

---

## Phase 1: Administrator Role Tests

**Login as System Administrator**

### Test 1.1: Access Dashboard (Read Permission)

**Action:** Navigate to module dashboard
- URL: `http://localhost:8080/index.php?q=/modules/Example Module/exampleModule.php`

**Expected Results:**
- [ ] Page loads without errors
- [ ] Breadcrumb shows: Home > Example Module > Dashboard
- [ ] Statistics panel displays:
  - Total Items: 0
  - Active Items: 0
  - Pending Items: 0
  - Inactive Items: 0
- [ ] Recent items section shows "No records found" message
- [ ] Quick actions visible:
  - "Add New Item" button
  - "Manage Items" link
  - "View All" link

**Verification:**
```bash
# Check PHP error log for any warnings/errors
docker-compose exec gibbon tail -f /var/log/php/error.log
# Should show no errors related to exampleModule.php
```

### Test 1.2: Create New Item (CRUD - Create)

**Action:** Create a new example entity

1. Click **"Add New Item"** button or navigate to:
   - URL: `http://localhost:8080/index.php?q=/modules/Example Module/exampleModule_manage_add.php`

2. Verify form displays correctly:
   - [ ] Title field (text input, required)
   - [ ] Description field (textarea)
   - [ ] Status dropdown (Active, Pending, Inactive)
   - [ ] Person selector (select user)
   - [ ] Submit button

3. **Test Case 1.2a: Validation - Empty Form**
   - Click Submit without filling any fields
   - **Expected:** Form validation prevents submission
   - **Expected:** Error message: "Title is required" or similar

4. **Test Case 1.2b: Validation - Title Too Long**
   - Enter title with 101+ characters
   - Click Submit
   - **Expected:** Validation error about max length (100 chars)

5. **Test Case 1.2c: Successful Creation**
   - Fill in:
     - Title: "Test Item 1 - Admin Created"
     - Description: "This is a test item created by administrator for E2E testing"
     - Status: Active
     - Person: Select any user (or leave as current user)
   - Click Submit
   - **Expected Results:**
     - [ ] Redirect to manage page
     - [ ] Success message: "Your request was completed successfully"
     - [ ] New item appears in the list

**Database Verification:**
```sql
-- Check record was created
SELECT gibbonExampleEntityID, title, status, timestampCreated
FROM gibbonExampleEntity
WHERE title = 'Test Item 1 - Admin Created';

-- Expected: 1 row returned with current timestamp
```

**Verification Checklist:**
- [ ] Form loads correctly
- [ ] Required field validation works
- [ ] Max length validation works
- [ ] Record created in database
- [ ] Timestamps set correctly (timestampCreated)
- [ ] No PHP errors in logs

### Test 1.3: View Items List (CRUD - Read)

**Action:** View all items in manage page

1. Navigate to: **Example Module > Manage Items**
   - URL: `http://localhost:8080/index.php?q=/modules/Example Module/exampleModule_manage.php`

2. Verify list displays:
   - [ ] DataTable with columns: Title, Person, Status, Modified, Actions
   - [ ] Filter options: Status, Person
   - [ ] Pagination controls
   - [ ] "Test Item 1 - Admin Created" appears in list

3. **Test Case 1.3a: Status Filter**
   - Select "Active" from status filter
   - Click Apply/Filter
   - **Expected:** Only active items shown

4. **Test Case 1.3b: Person Filter**
   - Select a specific person
   - **Expected:** Only items for that person shown

5. **Test Case 1.3c: Sorting**
   - Click column headers (Title, Status, Modified)
   - **Expected:** Table sorts by that column

**Verification Checklist:**
- [ ] List displays correctly
- [ ] Filters work as expected
- [ ] Sorting works on all sortable columns
- [ ] Action buttons visible (Edit, Delete)
- [ ] Status badges color-coded correctly
- [ ] No PHP errors in logs

### Test 1.4: Update Item (CRUD - Update)

**Action:** Edit existing item

1. In manage page, click **Edit** icon for "Test Item 1"
   - URL: `http://localhost:8080/index.php?q=/modules/Example Module/exampleModule_manage_edit.php&gibbonExampleEntityID=1`

2. Verify form pre-populated:
   - [ ] Title shows: "Test Item 1 - Admin Created"
   - [ ] Description shows previous value
   - [ ] Status shows: Active
   - [ ] Person shows selected user

3. **Test Case 1.4a: Successful Update**
   - Change title to: "Test Item 1 - Admin Updated"
   - Change description to: "Updated description for E2E testing"
   - Change status to: Pending
   - Click Submit
   - **Expected Results:**
     - [ ] Redirect to manage page
     - [ ] Success message displayed
     - [ ] Changes reflected in list

**Database Verification:**
```sql
-- Check record was updated
SELECT title, status, timestampModified
FROM gibbonExampleEntity
WHERE gibbonExampleEntityID = 1;

-- Expected: Updated values with new timestampModified
```

**Verification Checklist:**
- [ ] Form loads with existing values (loadAllValuesFrom works)
- [ ] Update successful
- [ ] timestampModified updated in database
- [ ] Changes reflected in list immediately
- [ ] No PHP errors in logs

### Test 1.5: Delete Item (CRUD - Delete)

**Action:** Delete item with confirmation

1. In manage page, click **Delete** icon for "Test Item 1"
   - URL: `http://localhost:8080/index.php?q=/modules/Example Module/exampleModule_manage_delete.php&gibbonExampleEntityID=1`

2. Verify confirmation page:
   - [ ] Warning message about deletion
   - [ ] Item details shown
   - [ ] "Delete" and "Cancel" buttons

3. **Test Case 1.5a: Cancel Deletion**
   - Click Cancel
   - **Expected:** Return to manage page, item still exists

4. **Test Case 1.5b: Confirm Deletion**
   - Click Delete icon again
   - Click Confirm/Delete button
   - **Expected Results:**
     - [ ] Redirect to manage page
     - [ ] Success message: "Record deleted"
     - [ ] Item no longer appears in list

**Database Verification:**
```sql
-- Check record was deleted
SELECT COUNT(*) FROM gibbonExampleEntity WHERE gibbonExampleEntityID = 1;

-- Expected: 0 rows
```

**Verification Checklist:**
- [ ] Confirmation page displays
- [ ] Cancel works correctly
- [ ] Delete removes record from database
- [ ] No orphaned records
- [ ] No PHP errors in logs

### Test 1.6: View-Only Page (Public Access)

**Action:** Access read-only view page

1. Navigate to: **Example Module > View**
   - URL: `http://localhost:8080/index.php?q=/modules/Example Module/exampleModule_view.php`

2. Verify:
   - [ ] Page loads without errors
   - [ ] Items displayed in read-only format
   - [ ] No Edit/Delete buttons visible
   - [ ] Status shown with color-coded badges
   - [ ] Timestamps displayed in readable format

**Verification Checklist:**
- [ ] View page accessible
- [ ] Read-only (no modification actions)
- [ ] Proper date formatting
- [ ] No PHP errors in logs

### Test 1.7: Settings Management

**Action:** Modify module settings

1. Navigate to: **Example Module > Settings**
   - URL: `http://localhost:8080/index.php?q=/modules/Example Module/exampleModule_settings.php`

2. Verify form displays:
   - [ ] Enable Feature: Yes/No toggle
   - [ ] Maximum Items: Number input

3. **Test Case 1.7a: Update Settings**
   - Change "Maximum Items" to 100
   - Click Submit
   - **Expected Results:**
     - [ ] Success message
     - [ ] Setting persisted in database

**Database Verification:**
```sql
-- Check settings updated
SELECT name, value FROM gibbonSetting
WHERE scope = 'Example Module' AND name = 'maxItems';

-- Expected: value = '100'
```

**Verification Checklist:**
- [ ] Settings form loads
- [ ] Settings update successfully
- [ ] Changes reflected immediately
- [ ] No PHP errors in logs

---

## Phase 2: Role-Based Permission Tests

### Test 2.1: Teacher Role Permissions

**Logout and Login as Teacher**

**Test Case 2.1a: Dashboard Access (Allowed)**
- Navigate to: `http://localhost:8080/index.php?q=/modules/Example Module/exampleModule.php`
- **Expected:** ✅ Access granted, page loads

**Test Case 2.1b: Manage Items (Denied)**
- Navigate to: `http://localhost:8080/index.php?q=/modules/Example Module/exampleModule_manage.php`
- **Expected:** ❌ Access denied message or redirect

**Test Case 2.1c: View Page (Allowed)**
- Navigate to: `http://localhost:8080/index.php?q=/modules/Example Module/exampleModule_view.php`
- **Expected:** ✅ Access granted, read-only view

**Test Case 2.1d: Settings (Denied)**
- Navigate to: `http://localhost:8080/index.php?q=/modules/Example Module/exampleModule_settings.php`
- **Expected:** ❌ Access denied

**Verification Checklist:**
- [ ] Dashboard accessible to Teacher
- [ ] Manage actions not accessible
- [ ] View page accessible (read-only)
- [ ] Settings not accessible
- [ ] Proper error messages shown
- [ ] No PHP errors in logs

### Test 2.2: Student Role Permissions

**Logout and Login as Student**

**Test Case 2.2a: Dashboard Access (Denied)**
- Navigate to: `http://localhost:8080/index.php?q=/modules/Example Module/exampleModule.php`
- **Expected:** ❌ Access denied or module not in menu

**Test Case 2.2b: Manage Items (Denied)**
- Navigate to: `http://localhost:8080/index.php?q=/modules/Example Module/exampleModule_manage.php`
- **Expected:** ❌ Access denied

**Test Case 2.2c: View Page (Allowed)**
- Navigate to: `http://localhost:8080/index.php?q=/modules/Example Module/exampleModule_view.php`
- **Expected:** ✅ Access granted, read-only view

**Verification Checklist:**
- [ ] Dashboard not accessible
- [ ] Manage actions not accessible
- [ ] View page accessible (read-only)
- [ ] Proper error messages shown
- [ ] No PHP errors in logs

### Test 2.3: Parent Role Permissions

**Logout and Login as Parent**

**Test Case 2.3a: All Module Pages (Denied)**
- Navigate to all module URLs
- **Expected:** ❌ Access denied for all actions

**Test Case 2.3b: View Page (Allowed)**
- Navigate to: `http://localhost:8080/index.php?q=/modules/Example Module/exampleModule_view.php`
- **Expected:** ✅ Access granted, read-only view

**Verification Checklist:**
- [ ] No access to management features
- [ ] View page accessible (read-only)
- [ ] Proper error messages shown
- [ ] No PHP errors in logs

### Test 2.4: Permission Matrix Verification

**Login as System Administrator**

1. Navigate to: **System Admin > Manage Permissions**
   - URL: `http://localhost:8080/index.php?q=/modules/System/permissions_manage.php`

2. Search for "Example Module" actions

3. Verify default permissions:

| Action | Admin | Teacher | Student | Parent | Support |
|--------|-------|---------|---------|--------|---------|
| Dashboard | ✅ Y | ✅ Y | ❌ N | ❌ N | ❌ N |
| Manage Items | ✅ Y | ❌ N | ❌ N | ❌ N | ❌ N |
| View | ✅ Y | ✅ Y | ✅ Y | ✅ Y | ❌ N |
| Settings | ✅ Y | ❌ N | ❌ N | ❌ N | ❌ N |

**Verification Checklist:**
- [ ] All 4 actions registered in permission system
- [ ] Default permissions match manifest.php
- [ ] Permissions can be modified by admin
- [ ] Changes take effect immediately

---

## Phase 3: Security Verification

### Test 3.1: SQL Injection Prevention

**Test Case 3.1a: Malicious Input in Forms**

1. Navigate to Add form
2. Try entering SQL injection payloads in Title field:
   ```
   ' OR '1'='1
   '; DROP TABLE gibbonExampleEntity; --
   1' UNION SELECT * FROM gibbonPerson --
   ```
3. Submit form
4. **Expected Results:**
   - [ ] Input treated as literal string
   - [ ] No SQL errors
   - [ ] Data stored safely with special characters escaped
   - [ ] Database tables intact

**Database Verification:**
```sql
-- Verify data stored correctly
SELECT title FROM gibbonExampleEntity ORDER BY gibbonExampleEntityID DESC LIMIT 1;

-- Expected: Literal string stored, no SQL executed
```

**Test Case 3.1b: URL Parameter Tampering**

1. Manually edit URL with SQL injection:
   ```
   http://localhost:8080/index.php?q=/modules/Example Module/exampleModule_manage_edit.php&gibbonExampleEntityID=1' OR '1'='1
   ```
2. **Expected:** Error or no data loaded, no SQL executed

**Verification Checklist:**
- [ ] All user input sanitized
- [ ] Parameterized queries prevent injection
- [ ] No SQL errors in logs
- [ ] Database integrity maintained

### Test 3.2: XSS (Cross-Site Scripting) Prevention

**Test Case 3.2a: JavaScript in Form Fields**

1. Create new item with XSS payload in title:
   ```html
   <script>alert('XSS')</script>
   <img src=x onerror=alert('XSS')>
   ```
2. Submit form
3. View item in list
4. **Expected Results:**
   - [ ] Script tags displayed as text (not executed)
   - [ ] No JavaScript alerts
   - [ ] HTML properly escaped: `&lt;script&gt;`

**Test Case 3.2b: XSS in Description Field**

1. Add item with HTML/JavaScript in description
2. View item
3. **Expected:** All HTML escaped, displayed as text

**Verification Checklist:**
- [ ] All output uses htmlspecialchars() or Format:: methods
- [ ] No JavaScript execution from user input
- [ ] HTML tags displayed as text
- [ ] No XSS vulnerabilities

### Test 3.3: Permission Bypass Attempts

**Test Case 3.3a: Direct URL Access**

1. Login as Student
2. Try accessing admin-only URLs directly:
   ```
   http://localhost:8080/index.php?q=/modules/Example Module/exampleModule_manage.php
   http://localhost:8080/index.php?q=/modules/Example Module/exampleModule_settings.php
   ```
3. **Expected:** Access denied, redirect to error page

**Test Case 3.3b: Cookie/Session Tampering**

1. Login as Teacher
2. Use browser dev tools to modify session cookie
3. Try accessing admin-only pages
4. **Expected:** Access denied or session invalidated

**Verification Checklist:**
- [ ] isActionAccessible() blocks unauthorized access
- [ ] All action pages have permission checks
- [ ] Permission checks use hard-coded addresses
- [ ] No bypass possible through URL manipulation

### Test 3.4: Code Security Audit

**Manual Code Review:**

1. **Check Permission Addresses:**
   ```bash
   # Verify all permission checks use hard-coded strings
   grep -n "isActionAccessible" ./gibbon/modules/ExampleModule/*.php

   # Expected: All addresses are literal strings, no variables
   # BAD: isActionAccessible($guid, $connection2, $dynamicPath)
   # GOOD: isActionAccessible($guid, $connection2, '/modules/Example Module/exampleModule.php')
   ```

2. **Check Parameterized Queries:**
   ```bash
   # Verify Gateway uses bindValue
   grep -n "bindValue\|->insert\|->update\|->delete" ./gibbon/modules/ExampleModule/src/Domain/*.php

   # Expected: All database operations use parameterized queries
   ```

3. **Check GPL License Headers:**
   ```bash
   # Verify all PHP files have license
   head -20 ./gibbon/modules/ExampleModule/*.php | grep -i "GNU General Public License"

   # Expected: All files have GPL-3.0 header
   ```

**Verification Checklist:**
- [ ] All permission checks use hard-coded addresses
- [ ] All queries parameterized (no string concatenation)
- [ ] GPL-3.0 license in all PHP files
- [ ] No direct $_GET/$_POST usage without validation
- [ ] No eval(), exec(), or shell_exec() usage

---

## Phase 4: Data Integrity Tests

### Test 4.1: Create Multiple Test Records

**Action:** Create test dataset

Create the following items:

1. **Item 1:**
   - Title: "Active Item - Math Resources"
   - Status: Active
   - Person: Teacher user

2. **Item 2:**
   - Title: "Pending Item - Science Lab"
   - Status: Pending
   - Person: Admin user

3. **Item 3:**
   - Title: "Inactive Item - Old Curriculum"
   - Status: Inactive
   - Person: Teacher user

4. **Item 4:**
   - Title: "Active Item - Student Projects"
   - Status: Active
   - Person: Student user

**Database Verification:**
```sql
-- Verify all records created
SELECT gibbonExampleEntityID, title, status, gibbonPersonID, timestampCreated
FROM gibbonExampleEntity
ORDER BY gibbonExampleEntityID;

-- Expected: 4 rows with correct values
```

### Test 4.2: Statistics Accuracy

**Action:** Verify dashboard statistics

1. Navigate to dashboard
2. Check statistics panel:
   - [ ] Total Items: 4
   - [ ] Active Items: 2
   - [ ] Pending Items: 1
   - [ ] Inactive Items: 1

**Database Cross-Check:**
```sql
-- Verify counts match
SELECT
  COUNT(*) as total,
  SUM(CASE WHEN status='Active' THEN 1 ELSE 0 END) as active,
  SUM(CASE WHEN status='Pending' THEN 1 ELSE 0 END) as pending,
  SUM(CASE WHEN status='Inactive' THEN 1 ELSE 0 END) as inactive
FROM gibbonExampleEntity;

-- Expected: Matches dashboard display
```

**Verification Checklist:**
- [ ] Statistics accurate
- [ ] Counts update in real-time
- [ ] No caching issues

### Test 4.3: Foreign Key Integrity

**Test Case 4.3a: Valid Person Reference**
```sql
-- Verify all person IDs are valid
SELECT e.gibbonExampleEntityID, e.title, e.gibbonPersonID, p.gibbonPersonID
FROM gibbonExampleEntity e
LEFT JOIN gibbonPerson p ON e.gibbonPersonID = p.gibbonPersonID;

-- Expected: All joins successful, no NULL gibbonPersonID
```

**Test Case 4.3b: Valid School Year Reference**
```sql
-- Verify all school year IDs are valid
SELECT e.gibbonExampleEntityID, e.title, e.gibbonSchoolYearID, sy.gibbonSchoolYearID
FROM gibbonExampleEntity e
LEFT JOIN gibbonSchoolYear sy ON e.gibbonSchoolYearID = sy.gibbonSchoolYearID;

-- Expected: All joins successful
```

**Verification Checklist:**
- [ ] All foreign keys reference valid records
- [ ] No orphaned records
- [ ] Referential integrity maintained

### Test 4.4: Timestamp Accuracy

**Test Case 4.4a: Creation Timestamps**
```sql
-- Check creation timestamps
SELECT title, timestampCreated,
       TIMESTAMPDIFF(MINUTE, timestampCreated, NOW()) as minutes_ago
FROM gibbonExampleEntity
ORDER BY timestampCreated DESC;

-- Expected: Recent timestamps (< 10 minutes for test records)
```

**Test Case 4.4b: Modification Timestamps**

1. Edit an item
2. Check database:
   ```sql
   SELECT title, timestampCreated, timestampModified
   FROM gibbonExampleEntity
   WHERE gibbonExampleEntityID = 1;

   -- Expected: timestampModified > timestampCreated
   ```

**Verification Checklist:**
- [ ] timestampCreated set on insertion
- [ ] timestampModified updated on changes
- [ ] Timestamps use server time (not client)
- [ ] Timestamps in correct timezone

---

## Phase 5: Error Handling & Logs

### Test 5.1: PHP Error Log Check

**Action:** Review PHP error logs for any issues

```bash
# Check for PHP errors during testing
docker-compose exec gibbon tail -100 /var/log/php/error.log | grep -i "example\|error\|warning\|notice"

# Or check Apache error log
docker-compose logs gibbon | grep -i "example\|error"

# Expected: No PHP errors, warnings, or notices related to ExampleModule
```

**Verification Checklist:**
- [ ] No Fatal Errors
- [ ] No Warnings
- [ ] No Notices
- [ ] No Deprecated function usage
- [ ] No "Undefined index" errors

### Test 5.2: Database Query Log

**Action:** Check slow or problematic queries

```sql
-- Enable query logging (if not already enabled)
SET GLOBAL general_log = 'ON';
SET GLOBAL general_log_file = '/var/log/mysql/query.log';

-- Check recent queries
SELECT * FROM mysql.general_log
WHERE argument LIKE '%gibbonExampleEntity%'
ORDER BY event_time DESC
LIMIT 20;

-- Expected: All queries properly parameterized (no literal user input)
```

**Verification Checklist:**
- [ ] All queries use placeholders (?)
- [ ] No slow queries (< 100ms)
- [ ] No duplicate queries (N+1 problem)
- [ ] Proper indexes used

### Test 5.3: Browser Console Errors

**Action:** Check browser JavaScript console

1. Open browser developer tools (F12)
2. Navigate to each module page
3. Check Console tab

**Expected Results:**
- [ ] No JavaScript errors
- [ ] No 404 errors for resources
- [ ] No CORS errors
- [ ] No CSP violations

### Test 5.4: Edge Cases & Error Scenarios

**Test Case 5.4a: Non-Existent Record**
- URL: `http://localhost:8080/index.php?q=/modules/Example Module/exampleModule_manage_edit.php&gibbonExampleEntityID=99999`
- **Expected:** Error message "Record not found" or redirect

**Test Case 5.4b: Missing Parameters**
- URL: `http://localhost:8080/index.php?q=/modules/Example Module/exampleModule_manage_edit.php`
- **Expected:** Error message or redirect to list

**Test Case 5.4c: Invalid Status Value**
- Manually craft POST with invalid status
- **Expected:** Validation error or default value used

**Verification Checklist:**
- [ ] Graceful error handling
- [ ] User-friendly error messages
- [ ] No sensitive data exposed in errors
- [ ] Proper error logging

---

## Phase 6: Comprehensive Final Verification

### Test 6.1: Complete CRUD Workflow

**Execute complete workflow:**

1. ✅ Create 3 new items
2. ✅ View items in list (sorting, filtering)
3. ✅ Edit 2 items
4. ✅ Delete 1 item
5. ✅ Verify statistics update correctly
6. ✅ Check database consistency

### Test 6.2: All Roles Tested

**Confirm testing completed for:**
- [ ] ✅ Administrator role (full access)
- [ ] ✅ Teacher role (limited access)
- [ ] ✅ Student role (view only)
- [ ] ✅ Parent role (view only)

### Test 6.3: Security Checklist

- [ ] ✅ SQL injection prevention verified
- [ ] ✅ XSS prevention verified
- [ ] ✅ Permission enforcement verified
- [ ] ✅ No unauthorized access possible

### Test 6.4: Database Integrity

```sql
-- Final database verification
-- 1. Check table exists and structure correct
DESCRIBE gibbonExampleEntity;

-- 2. Check collation
SELECT TABLE_COLLATION
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'gibbon' AND TABLE_NAME = 'gibbonExampleEntity';
-- Expected: utf8mb4_unicode_ci

-- 3. Verify all test records
SELECT COUNT(*) FROM gibbonExampleEntity;
-- Expected: > 0 (test records exist)

-- 4. Check for orphaned records
SELECT e.gibbonExampleEntityID
FROM gibbonExampleEntity e
LEFT JOIN gibbonPerson p ON e.gibbonPersonID = p.gibbonPersonID
WHERE p.gibbonPersonID IS NULL;
-- Expected: 0 rows (no orphans)

-- 5. Verify settings
SELECT * FROM gibbonSetting WHERE scope = 'Example Module';
-- Expected: 2 rows (enableFeature, maxItems)
```

### Test 6.5: Error Log Final Check

```bash
# Final error log review
docker-compose exec gibbon tail -200 /var/log/php/error.log

# Check for any errors during entire test session
# Expected: Clean log with no errors related to ExampleModule
```

---

## Test Results Summary

### Overall Verification Checklist

**Module Installation:**
- [ ] Module installed successfully
- [ ] Version 1.0.00
- [ ] All 4 actions registered
- [ ] Database migrations applied

**CRUD Operations:**
- [ ] Create: ✅ Works correctly
- [ ] Read: ✅ Works correctly
- [ ] Update: ✅ Works correctly
- [ ] Delete: ✅ Works correctly

**Permissions:**
- [ ] Admin: Full access ✅
- [ ] Teacher: Dashboard + View ✅
- [ ] Student: View only ✅
- [ ] Parent: View only ✅
- [ ] Permission enforcement working ✅

**Security:**
- [ ] SQL injection: Protected ✅
- [ ] XSS: Protected ✅
- [ ] Permission bypass: Blocked ✅
- [ ] Hard-coded addresses: ✅
- [ ] Parameterized queries: ✅

**Database:**
- [ ] Schema correct ✅
- [ ] Collation: utf8mb4_unicode_ci ✅
- [ ] Foreign keys valid ✅
- [ ] Timestamps accurate ✅
- [ ] No orphaned records ✅

**Error Handling:**
- [ ] No PHP errors ✅
- [ ] No JavaScript errors ✅
- [ ] Graceful error messages ✅
- [ ] Clean error logs ✅

**Data Integrity:**
- [ ] Statistics accurate ✅
- [ ] Filters working ✅
- [ ] Sorting working ✅
- [ ] Forms validated ✅

---

## Known Limitations & Notes

1. **Module is a Template:** ExampleModule is designed as a development template, not a production module.

2. **Reference Module:** Follows patterns from CareTracking module (Gibbon v30.0.01).

3. **Testing Environment:** Tests assume Docker-based Gibbon installation on localhost:8080.

4. **User Accounts:** Tests require pre-existing user accounts with different roles.

5. **Database Access:** Some verification steps require direct MySQL access.

---

## Troubleshooting

### Issue: Module doesn't appear in menu

**Solution:**
- Check module is Active in System Admin > Manage Modules
- Verify user role has permission to access module
- Check gibbonAction table for module actions

### Issue: Permission denied errors

**Solution:**
- Verify role-based permissions in System Admin > Manage Permissions
- Check isActionAccessible() returns true for current user
- Ensure action is registered in manifest.php

### Issue: Database errors

**Solution:**
- Verify CHANGEDB.php was executed successfully
- Check table collation is utf8mb4_unicode_ci
- Verify foreign key constraints are valid

### Issue: Form validation not working

**Solution:**
- Check browser JavaScript console for errors
- Verify Gibbon Form API is properly initialized
- Check server-side validation in *Process.php files

---

## Test Sign-Off

**Tester Name:** _______________________
**Date:** _______________________
**Gibbon Version:** _______________________
**Module Version:** 1.0.00

**Overall Result:** ☐ PASS ☐ FAIL ☐ PASS WITH ISSUES

**Issues Found:**
```
[List any issues, bugs, or concerns discovered during testing]
```

**Additional Notes:**
```
[Any additional observations or recommendations]
```

---

## Next Steps

After successful E2E testing:

1. ✅ Mark subtask-5-2 as completed
2. ✅ Update QA sign-off in implementation_plan.json
3. ✅ Commit all test results and documentation
4. ✅ Tag module as v1.0.00 release-ready
5. ✅ Create deployment documentation (if deploying to production)

**Congratulations!** ExampleModule has been thoroughly tested and verified. 🎉
