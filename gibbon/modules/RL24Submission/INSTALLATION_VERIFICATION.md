# RL-24 Submission Module - Installation Verification Checklist

This document provides a comprehensive checklist for verifying the RL-24 Submission module installation in Gibbon.

## Prerequisites

Before running verification:
- [ ] Gibbon is installed and running
- [ ] You have administrator access
- [ ] Database backups have been made (recommended)

---

## 1. Module Installation

### Via Gibbon Admin Panel

1. Navigate to **Admin > System Admin > Modules**
2. Click **Upload Module** or **Install Module**
3. Select the `RL24Submission` module folder
4. Click **Install**

### Verification Steps

- [ ] Module appears in the module list
- [ ] Module status shows as "Active"
- [ ] Version displays as "1.0.00"
- [ ] Author shows as "LAYA"
- [ ] Category shows as "Finance"

---

## 2. Database Tables Verification

After installation, verify these tables exist in your database:

### Table: `gibbonRL24Transmission`
- [ ] Table exists
- [ ] Contains columns: `gibbonRL24TransmissionID`, `gibbonSchoolYearID`, `taxYear`, `sequenceNumber`, `fileName`, `status`
- [ ] Has unique key on `taxYear` + `sequenceNumber`
- [ ] Status ENUM includes: Draft, Generated, Validated, Submitted, Accepted, Rejected, Cancelled

### Table: `gibbonRL24Slip`
- [ ] Table exists
- [ ] Contains columns: `gibbonRL24SlipID`, `gibbonRL24TransmissionID`, `gibbonPersonIDChild`, `slipNumber`
- [ ] Amount columns present: `case11Amount`, `case12Amount`, `case13Amount`, `case14Amount`
- [ ] Foreign key to transmission table

### Table: `gibbonRL24Eligibility`
- [ ] Table exists
- [ ] Contains columns: `gibbonRL24EligibilityID`, `gibbonSchoolYearID`, `gibbonPersonIDChild`, `formYear`
- [ ] Has unique key on `gibbonPersonIDChild` + `formYear`
- [ ] Approval status ENUM includes: Pending, Approved, Rejected, Incomplete

### Table: `gibbonRL24EligibilityDocument`
- [ ] Table exists
- [ ] Contains columns: `gibbonRL24EligibilityDocumentID`, `gibbonRL24EligibilityID`, `documentType`, `filePath`
- [ ] Document type ENUM includes: ProofOfCitizenship, ProofOfResidency, BirthCertificate, SINDocument, ProofOfGuardianship, Other
- [ ] Foreign key to eligibility table

### SQL Verification Commands

```sql
-- Check tables exist
SHOW TABLES LIKE 'gibbonRL24%';

-- Verify transmission table structure
DESCRIBE gibbonRL24Transmission;

-- Verify slip table structure
DESCRIBE gibbonRL24Slip;

-- Verify eligibility table structure
DESCRIBE gibbonRL24Eligibility;

-- Verify document table structure
DESCRIBE gibbonRL24EligibilityDocument;
```

---

## 3. Module Settings Verification

Navigate to **Admin > System Admin > System Settings** and search for "RL-24" settings:

### Required Settings
- [ ] `preparerNumber` - Revenu Quebec preparer identification number
- [ ] `providerName` - Childcare provider official name
- [ ] `providerNEQ` - Quebec Enterprise Number (NEQ)
- [ ] `providerAddress` - Official street address
- [ ] `providerCity` - City location
- [ ] `providerPostalCode` - Postal code in Canadian format

### Optional Settings (with defaults)
- [ ] `xmlOutputPath` - Default: `uploads/rl24/`
- [ ] `autoCalculateDays` - Default: `Y`
- [ ] `requireSINValidation` - Default: `Y`
- [ ] `documentRetentionYears` - Default: `7`

### SQL Verification

```sql
SELECT name, value, description
FROM gibbonSetting
WHERE scope = 'RL-24 Submission'
ORDER BY name;
```

---

## 4. Menu Actions Verification

After installation, verify these menu items appear under the module:

### Expected Menu Items

| Action Name | Entry URL | Category | Admin Access |
|-------------|-----------|----------|--------------|
| RL-24 Transmissions | `rl24_transmissions.php` | Tax Forms | Yes |
| FO-0601 Eligibility Forms | `rl24_eligibility.php` | Tax Forms | Yes |
| RL-24 Slips | `rl24_slips.php` | Tax Forms | Yes |
| RL-24 Settings | `rl24_settings.php` | Tax Forms | Yes |

### Manual Verification

1. Log in as administrator
2. Navigate to the module in the main menu
3. Verify each menu item:
   - [ ] **RL-24 Transmissions** - Dashboard with transmission list appears
   - [ ] **FO-0601 Eligibility Forms** - Eligibility form list appears
   - [ ] **RL-24 Slips** - Individual slips listing appears
   - [ ] **RL-24 Settings** - Settings configuration page appears

### SQL Verification

```sql
SELECT a.name, a.entryURL, a.category, a.menuShow
FROM gibbonAction a
JOIN gibbonModule m ON a.gibbonModuleID = m.gibbonModuleID
WHERE m.name = 'RL-24 Submission'
ORDER BY a.name;
```

---

## 5. Permission Verification

Check that permissions are correctly set:

### Default Permissions

| Role | RL-24 Transmissions | Eligibility Forms | Slips | Settings |
|------|---------------------|-------------------|-------|----------|
| Administrator | Yes | Yes | Yes | Yes |
| Teacher | No | No | No | No |
| Student | No | No | No | No |
| Parent | No | No | No | No |
| Support | No | No | No | No |

### Verification Steps

1. Navigate to **Admin > User Admin > Manage Permissions**
2. Select the "RL-24 Submission" module
3. Verify:
   - [ ] All actions visible to Administrator role
   - [ ] No actions visible to non-admin roles (unless configured)

---

## 6. Page Load Verification

Test each page loads without errors:

### RL-24 Transmissions
- [ ] `/modules/RL24Submission/rl24_transmissions.php` - Dashboard loads
- [ ] Statistics cards display (even if showing zeros)
- [ ] Filter form renders correctly
- [ ] "Generate New Transmission" button visible

### FO-0601 Eligibility Forms
- [ ] `/modules/RL24Submission/rl24_eligibility.php` - List page loads
- [ ] "Add Eligibility Form" button visible
- [ ] Filter by tax year works
- [ ] Filter by approval status works

### RL-24 Slips
- [ ] `/modules/RL24Submission/rl24_slips.php` - Slips list loads
- [ ] Summary statistics display
- [ ] Filter form renders correctly

### RL-24 Settings
- [ ] `/modules/RL24Submission/rl24_settings.php` - Settings page loads
- [ ] Provider Information section visible
- [ ] Preparer Information section visible
- [ ] Technical Settings section visible
- [ ] Save button functional

---

## 7. Functional Verification (Optional First-Run Test)

### Test Data Entry

1. **Configure Settings**
   - [ ] Enter provider name
   - [ ] Enter provider NEQ (10 digits)
   - [ ] Enter preparer number (6 digits)
   - [ ] Save settings

2. **Create Test Eligibility Form**
   - [ ] Navigate to FO-0601 Eligibility Forms
   - [ ] Click "Add Eligibility Form"
   - [ ] Select a test child
   - [ ] Enter parent information
   - [ ] Set service period dates
   - [ ] Save form
   - [ ] Verify form appears in list

3. **Approve Eligibility Form**
   - [ ] Edit the test form
   - [ ] Change approval status to "Approved"
   - [ ] Save changes
   - [ ] Verify status updated in list

4. **Generate Test Transmission**
   - [ ] Navigate to RL-24 Transmissions
   - [ ] Click "Generate New Transmission"
   - [ ] Select tax year
   - [ ] Verify preview shows approved eligibility forms
   - [ ] Confirm generation
   - [ ] Verify transmission appears in list
   - [ ] Verify XML file created

---

## 8. Automated Verification Script

An automated verification script is provided at:
```
/modules/RL24Submission/tests/verify_installation.php
```

### Running the Script

**Via Browser:**
```
http://your-gibbon-url/modules/RL24Submission/tests/verify_installation.php
```

**Via CLI:**
```bash
cd /path/to/gibbon
php modules/RL24Submission/tests/verify_installation.php
```

The script will check:
- Database tables and columns
- Module settings
- Module actions
- File existence
- Directory permissions

---

## 9. Troubleshooting

### Module Not Appearing in Menu
1. Check module is installed and active
2. Verify permissions for your role
3. Clear Gibbon cache
4. Log out and log back in

### Database Tables Missing
1. Check CHANGEDB.php was executed
2. Run module version update from admin panel
3. Manually run SQL from CHANGEDB.php

### Settings Not Showing
1. Verify gibbonSetting entries were created
2. Run INSERT statements from CHANGEDB.php manually if needed

### Permission Errors
1. Check action permissions in Admin > Manage Permissions
2. Verify user role has access to module actions

---

## Sign-Off

| Verification Area | Status | Verified By | Date |
|-------------------|--------|-------------|------|
| Module Installation | ☐ | | |
| Database Tables | ☐ | | |
| Module Settings | ☐ | | |
| Menu Actions | ☐ | | |
| Permissions | ☐ | | |
| Page Load | ☐ | | |
| Functional Test | ☐ | | |

**Overall Status:** ☐ Pass / ☐ Fail

**Notes:**
_________________________________________________________________________

_________________________________________________________________________

_________________________________________________________________________
