# ExampleModule Installation & Verification Guide

## Overview
This guide provides step-by-step instructions to install ExampleModule in Gibbon and verify that all components are working correctly.

## Prerequisites

1. **Gibbon Services Running:**
   ```bash
   # Start Gibbon and MySQL services
   docker-compose up -d gibbon mysql

   # Or use docker compose (newer syntax)
   docker compose up -d gibbon mysql
   ```

2. **Verify Services:**
   - Gibbon should be accessible at: http://localhost:8080
   - MySQL should be running on port 3306

3. **Admin Access:**
   - You need System Admin privileges to install modules

---

## Installation Steps

### Step 1: Access Module Management

1. Open browser to: **http://localhost:8080**
2. Log in with System Admin credentials
3. Navigate to: **System Admin > Manage Modules**
   - Direct URL: `http://localhost:8080/index.php?q=/modules/System/module_manage.php`

### Step 2: Install ExampleModule

1. In the module list, find **Example Module** (should appear with status "Not Installed" or similar)
2. Click the **Install** button/link
3. Gibbon will automatically:
   - Read `manifest.php`
   - Execute `CHANGEDB.php` migrations
   - Create database tables
   - Insert module settings
   - Register module actions
4. Confirm successful installation message

**Expected Result:**
- ✅ Module status changes to "Installed" or "Active"
- ✅ No error messages displayed
- ✅ Installation completes in < 5 seconds

### Step 3: Verify Module Appears in List

After installation, verify the module details:

**Module Information:**
- **Name:** Example Module
- **Description:** Template module demonstrating Gibbon module development patterns
- **Version:** 1.0.00
- **Type:** Additional
- **Category:** Other
- **Author:** LAYA
- **URL:** https://laya.ca

---

## Verification Checklist

### ✅ 1. Module Installation Verification

**Test:** Module appears in module list
- [ ] Navigate to: System Admin > Manage Modules
- [ ] Find "Example Module" in the list
- [ ] Status shows as "Active" or "Installed"
- [ ] Version shows as "1.0.00"

### ✅ 2. Database Tables Verification

**Test:** Tables created successfully

**Option A - Via Gibbon (Recommended):**
1. System Admin > Database Admin (if available)
2. Look for table: `gibbonExampleEntity`

**Option B - Via MySQL CLI:**
```bash
# Access MySQL container
docker-compose exec mysql mysql -u gibbon_user -pchangeme gibbon

# Or with docker compose
docker compose exec mysql mysql -u gibbon_user -pchangeme gibbon

# Check tables
SHOW TABLES LIKE 'gibbonExample%';

# Expected output:
# +---------------------------------+
# | Tables_in_gibbon (gibbonExample%)|
# +---------------------------------+
# | gibbonExampleEntity             |
# +---------------------------------+

# Verify table structure
DESCRIBE gibbonExampleEntity;

# Expected columns:
# - gibbonExampleEntityID (PK, INT UNSIGNED AUTO_INCREMENT)
# - gibbonPersonID (INT UNSIGNED)
# - gibbonSchoolYearID (INT UNSIGNED)
# - title (VARCHAR 100)
# - description (TEXT)
# - status (ENUM: Active, Inactive, Pending)
# - createdByID (INT UNSIGNED)
# - timestampCreated (TIMESTAMP)
# - timestampModified (TIMESTAMP)

# Verify collation
SHOW TABLE STATUS LIKE 'gibbonExampleEntity';
# Expected: Collation = utf8mb4_unicode_ci
```

**Option C - Via Verification Script:**
```bash
# Run the included verification script
./gibbon/modules/ExampleModule/verify-schema.sh
```

### ✅ 3. Module Settings Verification

**Test:** Settings registered successfully

**Via Gibbon Interface:**
1. Navigate to: System Admin > Settings
2. Search for scope: "Example Module"
3. Verify settings exist:
   - [ ] **Enable Feature** - Default value: "Y"
   - [ ] **Maximum Items** - Default value: "50"

**Via MySQL CLI:**
```sql
SELECT * FROM gibbonSetting WHERE scope = 'Example Module';

-- Expected output (2 rows):
-- scope           | name          | nameDisplay      | value
-- ----------------+---------------+------------------+-------
-- Example Module  | enableFeature | Enable Feature   | Y
-- Example Module  | maxItems      | Maximum Items    | 50
```

### ✅ 4. Actions/Permissions Verification

**Test:** Module actions appear in permission matrix

1. Navigate to: **System Admin > Manage Permissions**
   - URL: `http://localhost:8080/index.php?q=/modules/System/permission_manage.php`
2. Look for **Example Module** actions in the list
3. Verify the following 4 actions exist:

#### Action 1: Example Dashboard
- [ ] **Name:** Example Dashboard
- [ ] **Category:** Example
- [ ] **Description:** View example module dashboard
- [ ] **Entry URL:** exampleModule.php
- [ ] **Default Permissions:**
  - Admin: ✓ Yes
  - Teacher: ✓ Yes
  - Student: ✗ No
  - Parent: ✗ No
  - Support: ✓ Yes

#### Action 2: Manage Example Items
- [ ] **Name:** Manage Example Items
- [ ] **Category:** Example
- [ ] **Description:** Create, edit, and delete example items
- [ ] **Entry URL:** exampleModule_manage.php
- [ ] **Default Permissions:**
  - Admin: ✓ Yes
  - Teacher: ✓ Yes
  - Student: ✗ No
  - Parent: ✗ No
  - Support: ✗ No

#### Action 3: View Example Items
- [ ] **Name:** View Example Items
- [ ] **Category:** Example
- [ ] **Description:** View example items (read-only access)
- [ ] **Entry URL:** exampleModule_view.php
- [ ] **Default Permissions:**
  - Admin: ✓ Yes
  - Teacher: ✓ Yes
  - Student: ✓ Yes
  - Parent: ✓ Yes
  - Support: ✓ Yes

#### Action 4: Example Settings
- [ ] **Name:** Example Settings
- [ ] **Category:** Example
- [ ] **Description:** Configure example module settings
- [ ] **Entry URL:** exampleModule_settings.php
- [ ] **Default Permissions:**
  - Admin: ✓ Yes
  - Teacher: ✗ No
  - Student: ✗ No
  - Parent: ✗ No
  - Support: ✗ No

**Via MySQL CLI:**
```sql
-- Check actions registered
SELECT name, category, description, URLList
FROM gibbonAction
WHERE gibbonModuleID = (
    SELECT gibbonModuleID FROM gibbonModule WHERE name = 'Example Module'
);

-- Should return 4 rows with the actions listed above
```

### ✅ 5. Menu Integration Verification

**Test:** Module appears in navigation menu

1. Look in the main Gibbon navigation menu
2. Under the appropriate category, verify "Example Module" menu items appear
3. Menu items should be visible based on user role:
   - **Admin users** should see all 4 menu items
   - **Teacher users** should see 3 menu items (Dashboard, Manage, View)
   - **Student/Parent users** should see 1 menu item (View only)

### ✅ 6. Page Access Verification

**Test:** Module pages load without errors

**As Admin user:**
- [ ] Access: http://localhost:8080/index.php?q=/modules/ExampleModule/exampleModule.php
  - Expected: Dashboard page loads, shows statistics (may show 0 items initially)
  - No PHP errors displayed
  - Page has proper breadcrumb navigation

- [ ] Access: http://localhost:8080/index.php?q=/modules/ExampleModule/exampleModule_manage.php
  - Expected: Management page loads, shows empty list with "Add" button
  - DataTable renders correctly
  - Filter controls visible

- [ ] Access: http://localhost:8080/index.php?q=/modules/ExampleModule/exampleModule_view.php
  - Expected: View page loads (read-only version)
  - No edit/delete buttons visible
  - Appropriate messaging for empty state

- [ ] Access: http://localhost:8080/index.php?q=/modules/ExampleModule/exampleModule_settings.php
  - Expected: Settings page loads
  - Shows 2 settings (enableFeature, maxItems)
  - Form renders correctly with current values

---

## Troubleshooting

### Issue: Module Not Appearing in List

**Possible Causes:**
1. Module directory not in correct location
2. manifest.php has syntax errors
3. Gibbon cache needs clearing

**Solutions:**
```bash
# Verify module directory exists
ls -la ./gibbon/modules/ExampleModule/

# Expected files:
# - manifest.php
# - CHANGEDB.php
# - version.php
# - exampleModule.php (and other action files)
# - src/Domain/ExampleEntityGateway.php

# Check PHP syntax
php -l ./gibbon/modules/ExampleModule/manifest.php
# Should output: "No syntax errors detected"

# Clear Gibbon cache (if applicable)
# Navigate to: System Admin > System Check
# Look for cache clearing options
```

### Issue: Installation Errors

**Error: "Table already exists"**
- Solution: Table was created previously. Safe to ignore if using `CREATE TABLE IF NOT EXISTS`

**Error: "SQL syntax error"**
- Check CHANGEDB.php has proper `;end` separators
- Verify no extra semicolons within SQL statements
- Run: `grep -c ";end" ./gibbon/modules/ExampleModule/CHANGEDB.php` (should show 3)

**Error: "Permission denied"**
- Verify MySQL user has CREATE TABLE privileges
- Check database connection settings

### Issue: Actions Not Appearing

**Possible Causes:**
1. manifest.php $actionRows not properly formatted
2. Module ID not generated correctly
3. Permission cache needs refresh

**Solutions:**
1. Verify manifest.php syntax:
   ```bash
   php -l ./gibbon/modules/ExampleModule/manifest.php
   ```

2. Check gibbonModule table:
   ```sql
   SELECT * FROM gibbonModule WHERE name = 'Example Module';
   ```
   Should return 1 row with gibbonModuleID

3. Log out and log back in to refresh permissions

### Issue: Pages Show "Access Denied"

**Possible Causes:**
1. User role doesn't have permission
2. Permissions not configured correctly

**Solutions:**
1. Verify user role has permission:
   - System Admin > Manage Permissions
   - Check the specific action for the user's role

2. As System Admin, manually grant permission:
   - Find the action in permission matrix
   - Check the box for appropriate roles
   - Save changes

---

## Security Verification

After installation, verify security measures:

### ✅ 1. Permission Checks
- [ ] Try accessing pages without proper permissions (should show access denied)
- [ ] Verify isActionAccessible() is enforced on all pages

### ✅ 2. SQL Injection Protection
- [ ] All database queries use Gateway pattern
- [ ] No direct variable interpolation in SQL
- [ ] Parameterized queries via bindValue()

### ✅ 3. XSS Protection
- [ ] User input properly escaped in forms
- [ ] Output uses Format:: methods or htmlspecialchars()
- [ ] No unescaped echo of $_GET/$_POST

---

## Expected Database State After Installation

```sql
-- Module registered
SELECT * FROM gibbonModule WHERE name = 'Example Module';
-- 1 row: active='Y', version='1.0.00'

-- Actions registered
SELECT COUNT(*) FROM gibbonAction
WHERE gibbonModuleID = (SELECT gibbonModuleID FROM gibbonModule WHERE name = 'Example Module');
-- Result: 4 actions

-- Settings registered
SELECT COUNT(*) FROM gibbonSetting WHERE scope = 'Example Module';
-- Result: 2 settings

-- Table created
SELECT COUNT(*) FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'gibbon' AND TABLE_NAME = 'gibbonExampleEntity';
-- Result: 1 table
```

---

## Post-Installation Testing

### Functional Test: Create Example Item

1. Login as Admin
2. Navigate to: Example Module > Manage Example Items
3. Click "Add" button
4. Fill form:
   - Title: "Test Item"
   - Person: Select a person
   - Status: Active
   - Description: "Test description"
5. Submit form
6. Verify:
   - Success message appears
   - Redirected to manage page
   - Item appears in list
   - Item has correct details

### Functional Test: Edit Example Item

1. From manage page, click "Edit" on test item
2. Change title to "Updated Test Item"
3. Submit form
4. Verify:
   - Success message appears
   - Changes reflected in list
   - timestampModified updated

### Functional Test: Delete Example Item

1. From manage page, click "Delete" on test item
2. Confirm deletion
3. Verify:
   - Success message appears
   - Item removed from list
   - Record deleted from database

---

## Success Criteria Summary

Installation is successful when:

- ✅ Module appears in module list with Active status
- ✅ Database table `gibbonExampleEntity` created with correct schema
- ✅ 2 module settings registered in gibbonSetting
- ✅ 4 actions appear in permission matrix with correct default permissions
- ✅ All module pages load without PHP errors
- ✅ Menu items appear for appropriate user roles
- ✅ Security checks pass (permissions, SQL injection, XSS)
- ✅ CRUD operations work correctly
- ✅ No errors in PHP logs or browser console

---

## Quick Verification Script

For automated verification (after installation), run:

```bash
# Navigate to module directory
cd ./gibbon/modules/ExampleModule/

# Run verification script
./verify-schema.sh

# Expected output:
# ✓ MySQL connection successful
# ✓ Table gibbonExampleEntity exists
# ✓ Table structure correct (9 columns)
# ✓ Collation is utf8mb4_unicode_ci
# ✓ Module settings registered (2 settings)
#
# Verification: PASSED
```

---

## Support & Documentation

- **Module Documentation:** See README.md in module directory
- **Action Pages Overview:** See ACTION_PAGES_OVERVIEW.md
- **Migration Guide:** See MIGRATION_GUIDE.md
- **Form API Verification:** See FORM_API_VERIFICATION.md
- **Gibbon Documentation:** https://docs.gibbonedu.org/
- **Gibbon Developer Guide:** https://github.com/GibbonEdu/core

---

**Last Updated:** 2026-02-17
**Module Version:** 1.0.00
**Gibbon Compatibility:** v30.0.01+
