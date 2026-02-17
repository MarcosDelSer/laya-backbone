# RL-24 Submission Module - Installation Verification Guide

This document provides a comprehensive checklist for verifying the RL-24 Submission module installation.

## Quick Verification

Run the automated verification script:

```bash
# Via CLI
php modules/RL24Submission/tests/verify_installation.php

# Via Browser
http://your-gibbon-url/modules/RL24Submission/tests/verify_installation.php
```

---

## Manual Verification Checklist

### 1. Database Tables

Verify these tables exist in your Gibbon database:

| Table Name | Description | Required |
|------------|-------------|----------|
| `gibbonRL24Transmission` | Batch transmission tracking | Yes |
| `gibbonRL24Slip` | Individual RL-24 slips | Yes |
| `gibbonRL24Eligibility` | FO-0601 eligibility forms | Yes |
| `gibbonRL24EligibilityDocument` | Supporting documents | Yes |

**SQL Check:**
```sql
SHOW TABLES LIKE 'gibbonRL24%';
```

Expected output: 4 tables

### 2. Module Installation

Verify module is installed and active:

1. Navigate to **Admin > System Admin > Modules**
2. Find **RL-24 Submission** in the list
3. Verify status is **Active**

**SQL Check:**
```sql
SELECT gibbonModuleID, name, description, active
FROM gibbonModule
WHERE name = 'RL-24 Submission';
```

### 3. Module Actions (Menu Items)

Verify all menu actions are registered:

| Action Name | Entry URL | Category |
|-------------|-----------|----------|
| RL-24 Transmissions | rl24_transmissions.php | Tax Forms |
| FO-0601 Eligibility Forms | rl24_eligibility.php | Tax Forms |
| RL-24 Slips | rl24_slips.php | Tax Forms |
| RL-24 Settings | rl24_settings.php | Tax Forms |

**SQL Check:**
```sql
SELECT a.name, a.entryURL, a.category
FROM gibbonAction a
JOIN gibbonModule m ON a.gibbonModuleID = m.gibbonModuleID
WHERE m.name = 'RL-24 Submission';
```

### 4. Module Settings

Verify all settings are registered in `gibbonSetting`:

| Setting Name | Description | Default |
|--------------|-------------|---------|
| preparerNumber | Revenu Quebec preparer ID | (empty) |
| providerName | Childcare provider name | (empty) |
| providerNEQ | Quebec Enterprise Number | (empty) |
| providerAddress | Provider street address | (empty) |
| providerCity | Provider city | (empty) |
| providerPostalCode | Provider postal code | (empty) |
| xmlOutputPath | XML output directory | uploads/rl24/ |
| autoCalculateDays | Auto-calculate days | Y |
| requireSINValidation | Validate SIN format | Y |
| documentRetentionYears | Document retention period | 7 |

**SQL Check:**
```sql
SELECT name, value, description
FROM gibbonSetting
WHERE scope = 'RL-24 Submission';
```

### 5. File Structure

Verify all required files exist:

```
modules/RL24Submission/
├── manifest.php                           # Module manifest
├── CHANGEDB.php                           # Database changes
├── Domain/
│   ├── RL24TransmissionGateway.php       # Transmission gateway
│   ├── RL24SlipGateway.php               # Slip gateway
│   └── RL24EligibilityGateway.php        # Eligibility gateway
├── Xml/
│   ├── RL24XmlSchema.php                 # XML schema constants
│   ├── RL24XmlGenerator.php              # XML generator
│   ├── RL24XmlValidator.php              # XML validator
│   └── RL24SlipBuilder.php               # Slip builder
├── Services/
│   ├── RL24BatchProcessor.php            # Batch processor
│   ├── RL24SummaryCalculator.php         # Summary calculator
│   ├── RL24TransmissionFileNamer.php     # File naming
│   └── RL24PaperSummaryGenerator.php     # Paper summary
├── rl24_eligibility.php                  # Eligibility list
├── rl24_eligibility_add.php              # Add eligibility form
├── rl24_eligibility_addProcess.php       # Add form processor
├── rl24_eligibility_edit.php             # Edit eligibility form
├── rl24_eligibility_editProcess.php      # Edit form processor
├── rl24_eligibility_documents.php        # Document management
├── rl24_transmissions.php                # Transmissions dashboard
├── rl24_transmissions_generate.php       # Generate transmission
├── rl24_transmissions_generateProcess.php# Generate processor
├── rl24_transmissions_view.php           # View transmission
├── rl24_transmissions_download.php       # Download XML/summary
├── rl24_slips.php                        # Slips listing
├── rl24_settings.php                     # Module settings
└── tests/
    ├── GatewayTest.php                   # Gateway unit tests
    ├── XmlGeneratorTest.php              # XML generation tests
    ├── BatchProcessorTest.php            # Batch processing tests
    ├── EligibilityFormTest.php           # Eligibility form tests
    ├── EndToEndTest.php                  # E2E integration tests
    ├── e2e_workflow_test.php             # E2E workflow script
    ├── verify_installation.php           # Installation verification
    ├── E2E_TEST_GUIDE.md                 # Manual E2E guide
    └── INSTALLATION_VERIFICATION.md      # This file
```

### 6. Directory Permissions

Verify the following directories exist and are writable:

```bash
# Check uploads directory
ls -la uploads/rl24/

# If directory doesn't exist, create it
mkdir -p uploads/rl24
chmod 755 uploads/rl24
```

---

## Post-Installation Configuration

After verifying installation, configure the module:

### Required Settings

1. Navigate to **Tax Forms > RL-24 Settings**
2. Configure provider information:
   - Provider Name (required)
   - Provider NEQ (required, 10 digits)
   - Provider Address
   - Provider City
   - Provider Postal Code
3. Configure preparer information:
   - Preparer Number (required, 6 digits)
4. Save settings

### Verify Configuration

Settings are complete when:
- [ ] No warning messages on settings page
- [ ] NEQ is exactly 10 digits
- [ ] Preparer number is exactly 6 digits
- [ ] All required fields are filled

---

## Troubleshooting

### Module Not Visible

1. Check module is active in System Admin > Modules
2. Verify user has permission to access Tax Forms actions
3. Clear Gibbon cache

### Database Tables Missing

1. Re-run database updates via System Admin > Updates
2. Check CHANGEDB.php has executed all versions
3. Manually run SQL from CHANGEDB.php if needed

### XML Generation Fails

1. Verify provider configuration is complete
2. Check uploads/rl24 directory is writable
3. Review PHP error logs for specific errors

### Permission Errors

1. Verify user role has access to RL-24 actions
2. Check action permissions in System Admin > Roles
3. Grant access to required actions

---

## Running Tests

### Unit Tests (PHPUnit)

```bash
cd gibbon
phpunit modules/RL24Submission/tests/
```

### E2E Workflow Test

```bash
# Automated test
php modules/RL24Submission/tests/e2e_workflow_test.php

# Or via browser
http://your-gibbon-url/modules/RL24Submission/tests/e2e_workflow_test.php
```

### Installation Verification

```bash
php modules/RL24Submission/tests/verify_installation.php
```

---

## Sign-Off Template

| Verification Item | Verified By | Date | Status |
|-------------------|-------------|------|--------|
| Database tables created | | | |
| Module active | | | |
| Actions registered | | | |
| Settings configured | | | |
| Files present | | | |
| Directory permissions | | | |
| Unit tests pass | | | |
| E2E workflow test pass | | | |

**Overall Status:** [ ] PASS / [ ] FAIL

**Notes:**

---

*Document Version: 1.0*
*Last Updated: 2026-02-17*
