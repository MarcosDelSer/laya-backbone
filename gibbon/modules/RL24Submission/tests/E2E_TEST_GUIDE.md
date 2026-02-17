# RL-24 Submission Module - End-to-End Test Guide

This guide provides comprehensive step-by-step instructions for manually testing the complete RL-24 submission workflow from eligibility form creation through XML file generation and download.

## Test Objectives

By following this guide, you will verify:
1. FO-0601 eligibility form creation and validation
2. Batch transmission generation
3. XML file format (AAPPPPPPSSS.xml)
4. XML schema validation
5. Paper summary form generation
6. Summary calculations accuracy

---

## Prerequisites

Before beginning the test:

- [ ] Gibbon is installed and running
- [ ] RL-24 Submission module is installed and active
- [ ] You have administrator access
- [ ] Module settings are configured (see Settings Configuration below)
- [ ] At least one school year exists in the system
- [ ] At least one student record exists for testing

---

## 1. Settings Configuration

### Navigate to RL-24 Settings

1. Go to **Admin > System Admin > Modules**
2. Find **RL-24 Submission** module
3. Click **Settings** or navigate to: `/index.php?q=/modules/RL24Submission/rl24_settings.php`

### Configure Required Settings

Fill in the following provider information:

| Setting | Test Value | Notes |
|---------|------------|-------|
| Provider Name | `Centre de la petite enfance ABC` | Official organization name |
| Provider NEQ | `1234567890` | 10-digit Quebec Enterprise Number |
| Provider Address | `5678 rue Laval` | Street address |
| Provider City | `Montreal` | City name |
| Provider Postal Code | `H2X 3T5` | Canadian postal code format |
| Preparer Number | `123456` | 6-digit Revenu Quebec preparer ID |
| XML Output Path | `uploads/rl24/` | Directory for generated XML files |

### Verification Checklist

- [ ] All required fields are filled
- [ ] NEQ is exactly 10 digits
- [ ] Preparer number is exactly 6 digits
- [ ] Postal code is valid Canadian format
- [ ] No configuration warnings displayed

---

## 2. Create FO-0601 Eligibility Form

### Navigate to Eligibility Forms

1. Go to **Tax Forms > FO-0601 Eligibility Forms**
2. Or navigate to: `/index.php?q=/modules/RL24Submission/rl24_eligibility.php`
3. Click **Add New Eligibility Form**

### Fill in Child Information

| Field | Test Value | Validation |
|-------|------------|------------|
| School Year | Current school year | Required |
| Child | Select existing student | Required |
| Child First Name | `Marie` | Auto-filled or editable |
| Child Last Name | `Tremblay` | Auto-filled or editable |
| Date of Birth | `2020-03-15` | YYYY-MM-DD format |
| Relationship | `Parent` | Dropdown selection |

### Fill in Parent/Guardian Information

| Field | Test Value | Validation |
|-------|------------|------------|
| Parent First Name | `Jean` | Required |
| Parent Last Name | `Tremblay` | Required |
| Parent SIN | `123456782` | 9 digits, passes Luhn check |

### Fill in Address Information

| Field | Test Value | Validation |
|-------|------------|------------|
| Address Line 1 | `1234 rue Principale` | Required |
| Address Line 2 | (leave empty) | Optional |
| City | `Montreal` | Required |
| Province | `QC` | Must be Quebec |
| Postal Code | `H3A 1B2` | Canadian format |

### Fill in Citizenship/Residency

| Field | Test Value | Validation |
|-------|------------|------------|
| Citizenship Status | `Citizen` | Dropdown |
| Quebec Resident | `Yes` | Required for eligibility |
| Residency Start Date | `2020-01-01` | YYYY-MM-DD |

### Fill in Service Period

| Field | Test Value | Validation |
|-------|------------|------------|
| Form Year | `2025` | Tax year |
| Service Period Start | `2025-01-06` | Within form year |
| Service Period End | `2025-06-30` | After start date |
| Total Days | `125` | Positive number |

### Submit Form

1. Click **Submit** or **Save**
2. Verify no validation errors
3. Note the eligibility form ID for tracking

### Verification Checklist

- [ ] Form saved successfully
- [ ] All required fields accepted
- [ ] SIN format validated
- [ ] Postal code format validated
- [ ] Dates within appropriate range
- [ ] Form appears in eligibility list

---

## 3. Approve Eligibility Form

### Edit the Form

1. Return to eligibility forms list
2. Click **Edit** on the newly created form
3. Or navigate to: `/index.php?q=/modules/RL24Submission/rl24_eligibility_edit.php&gibbonRL24EligibilityID=[ID]`

### Update Provider Administration Section

| Field | Test Value | Notes |
|-------|------------|-------|
| Division Number | `001` | Optional |
| Approval Status | `Approved` | Required for batch |
| Approval Notes | `Test approval for E2E testing` | Optional |
| Documents Complete | `Yes` | Checkbox |
| Signature Confirmed | `Yes` | Checkbox |

### Submit Changes

1. Click **Update** or **Save**
2. Verify status changed to Approved
3. Verify approval date/time recorded

### Verification Checklist

- [ ] Status shows as "Approved"
- [ ] Approval timestamp recorded
- [ ] Form eligible for batch generation

---

## 4. Upload Supporting Documents (Optional)

### Navigate to Documents

1. Click **Documents** on the eligibility form
2. Or navigate to: `/index.php?q=/modules/RL24Submission/rl24_eligibility_documents.php&gibbonRL24EligibilityID=[ID]`

### Upload Test Documents

| Document Type | Test File | Format |
|---------------|-----------|--------|
| Proof of Citizenship | `test_citizenship.pdf` | PDF, JPG, or PNG |
| Proof of Residency | `test_residency.pdf` | Max 10MB |

### Verify Documents

1. Mark document as **Verified**
2. Check "Documents Complete" updates automatically

### Verification Checklist

- [ ] Document uploaded successfully
- [ ] File stored in correct location
- [ ] Document type recorded
- [ ] Verification workflow functional

---

## 5. Generate Batch Transmission

### Navigate to Transmissions

1. Go to **Tax Forms > RL-24 Transmissions**
2. Or navigate to: `/index.php?q=/modules/RL24Submission/rl24_transmissions.php`
3. Click **Generate New Transmission**

### Select Tax Year and Review

1. Select tax year `2025`
2. Click **Preview** or **Load Preview**
3. Review the summary:
   - Number of approved eligibility forms
   - Number of slips to generate
   - Expected filename format

### Expected Preview Data

| Item | Expected Value |
|------|----------------|
| Tax Year | 2025 |
| Approved Forms | 1 (or more) |
| New Slips to Generate | 1 (or more) |
| Expected Filename | `25123456001.xml` |
| Next Sequence Number | 001 |

### Confirm and Generate

1. Check the confirmation checkbox
2. Optionally add batch notes
3. Click **Generate Batch**
4. Wait for processing to complete

### Verification Checklist

- [ ] Preview shows correct statistics
- [ ] Expected filename follows AAPPPPPPSSS.xml format
- [ ] Batch generates without errors
- [ ] Redirected to transmission view page
- [ ] Transmission status shows "Generated" or "Validated"

---

## 6. Verify XML File Format

### View Transmission Details

1. Click on the generated transmission
2. Or navigate to: `/index.php?q=/modules/RL24Submission/rl24_transmissions_view.php&gibbonRL24TransmissionID=[ID]`

### Verify File Information

| Item | Expected |
|------|----------|
| File Name | `25123456001.xml` (AAPPPPPPSSS format) |
| Status | Generated or Validated |
| Total Slips | Matches expected count |
| Validation Status | Valid (no errors) |

### Download XML File

1. Click **Download XML** button
2. Save the file to your local system
3. Verify filename matches expected format

### Verify XML Content

Open the downloaded XML file and verify:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<Transmission xmlns="http://www.revenquebec.gouv.qc.ca/rl24">
    <Entete>
        <!-- Header with preparer info, tax year, sequence -->
    </Entete>
    <Groupe>
        <!-- Provider information -->
        <Emetteur>
            <NEQ>1234567890</NEQ>
            <!-- ... -->
        </Emetteur>
        <!-- Individual RL-24 slips -->
        <RL24>
            <!-- Slip identification, recipient, child, amounts -->
        </RL24>
        <!-- Summary totals -->
        <Sommaire>
            <!-- Total slips, total amounts by box -->
        </Sommaire>
    </Groupe>
</Transmission>
```

### Verification Checklist

- [ ] File downloads successfully
- [ ] Filename is 11 characters + .xml (14 total)
- [ ] Format: AAPPPPPPSSS.xml
- [ ] XML is well-formed (opens in browser/editor)
- [ ] Contains Transmission root element
- [ ] Contains Entete (header) section
- [ ] Contains Groupe with Emetteur and RL24 elements
- [ ] Contains Sommaire (summary) section

---

## 7. Validate XML Against Schema

### Automatic Validation

The system automatically validates XML during generation. Check:

1. **Transmission View Page**: Look for validation status
2. **Validation Errors**: If any, they should be displayed

### Manual Validation (Optional)

For thorough testing, validate against the official Revenu Quebec schema:

1. Obtain the official RL-24 XSD schema file
2. Use an XML validator tool:
   ```bash
   xmllint --schema rl24.xsd 25123456001.xml
   ```
3. Or use an online XML validator

### Validation Points

| Element | Validation Rule |
|---------|-----------------|
| Tax Year (AA) | 2-digit year (00-99) |
| Preparer Number (PPPPPP) | 6 digits |
| Sequence Number (SSS) | 3 digits (001-999) |
| SIN | 9 digits, valid Luhn checksum |
| NEQ | 10 digits |
| Amounts | Positive decimals, 2 decimal places |
| Dates | YYYY-MM-DD format |

### Verification Checklist

- [ ] XML passes well-formedness check
- [ ] All required elements present
- [ ] Data types match expected format
- [ ] No schema validation errors
- [ ] Business rules satisfied (Box 14 = Box 12 - Box 13)

---

## 8. Download Paper Summary Form

### Access Summary Download

1. On the transmission view page
2. Click **Download Summary** or **Paper Summary**
3. Or navigate to: `/index.php?q=/modules/RL24Submission/rl24_transmissions_download.php&gibbonRL24TransmissionID=[ID]&type=summary`

### Verify Summary Form (RL-24 Sommaire)

The paper summary should contain:

| Field (French Label) | Expected Value |
|---------------------|----------------|
| Annee | 2025 |
| No. de sequence | 001 |
| NEQ de l'emetteur | 1234 567 890 |
| Nombre de releves | 1 |
| Total Case 10 | 125 (total days) |
| Total Case 11 | [Eligible fees total] |
| Total Case 12 | [Fees paid total] |
| Total Case 13 | [Fees reimbursed total] |
| Total Case 14 | [Eligible amount total] |

### Download Slip Listing (Optional)

1. Click **Download Slips** or **Slip Listing**
2. Verify each slip shows:
   - Recipient (parent) name and SIN (masked)
   - Child name and DOB
   - Service period
   - Box amounts (10-14)

### Verification Checklist

- [ ] Summary downloads/displays correctly
- [ ] French labels used (RL-24 Sommaire)
- [ ] NEQ formatted as XXXX XXX XXX
- [ ] All totals calculated and displayed
- [ ] Slip count matches expected
- [ ] Print-friendly formatting

---

## 9. Verify Summary Calculations

### Compare Totals

Using the test data from Step 2:

| Box | Single Slip Value | Total (1 slip) |
|-----|-------------------|----------------|
| Box 10 (Days) | 125 | 125 |
| Box 11 | [Calculated] | [Sum] |
| Box 12 | [Calculated] | [Sum] |
| Box 13 | [Calculated] | [Sum] |
| Box 14 | [Calculated] | [Sum] |

### Verify Business Rules

1. **Box 14 Formula**: For each slip, verify:
   ```
   Box 14 = Box 12 - Box 13
   ```

2. **Totals**: For summary, verify:
   ```
   Total Box 14 = Sum of all individual Box 14 values
   Total Box 14 = Total Box 12 - Total Box 13
   ```

### SQL Verification (Optional)

```sql
-- Verify slip totals match summary
SELECT
    gibbonRL24TransmissionID,
    COUNT(*) as slip_count,
    SUM(case10TotalDays) as total_days,
    SUM(case11Amount) as total_case11,
    SUM(case12Amount) as total_case12,
    SUM(case13Amount) as total_case13,
    SUM(case14Amount) as total_case14
FROM gibbonRL24Slip
WHERE gibbonRL24TransmissionID = [ID]
GROUP BY gibbonRL24TransmissionID;

-- Verify Box 14 = Box 12 - Box 13 for each slip
SELECT
    gibbonRL24SlipID,
    case12Amount,
    case13Amount,
    case14Amount,
    (case12Amount - case13Amount) as calculated_case14,
    CASE WHEN case14Amount = (case12Amount - case13Amount)
         THEN 'PASS' ELSE 'FAIL' END as validation
FROM gibbonRL24Slip
WHERE gibbonRL24TransmissionID = [ID];
```

### Verification Checklist

- [ ] Individual slip calculations correct
- [ ] Box 14 = Box 12 - Box 13 for each slip
- [ ] Summary totals match sum of slips
- [ ] Slip count matches number of slips
- [ ] Total days matches sum of individual days
- [ ] No calculation discrepancies

---

## 10. Additional Test Scenarios

### Multiple Children Batch

Repeat steps 2-9 with multiple eligibility forms:
1. Create 5 eligibility forms for different children
2. Approve all forms
3. Generate single batch transmission
4. Verify all 5 slips in transmission
5. Verify summary totals are sums of all slips

### Amendment Workflow

1. Generate original transmission
2. Create amended slip for existing child
3. Generate new transmission with type "A" (Amended)
4. Verify original slip reference included

### Cancellation Workflow

1. Generate original transmission
2. Create cancelled slip for existing child
3. Generate new transmission with type "D" (Cancelled)
4. Verify original slip reference included

---

## Test Sign-Off

| Test Area | Tester | Date | Status |
|-----------|--------|------|--------|
| Settings Configuration | | | |
| Eligibility Form Creation | | | |
| Form Approval Workflow | | | |
| Document Upload (Optional) | | | |
| Batch Generation | | | |
| XML File Format | | | |
| XML Schema Validation | | | |
| Paper Summary Download | | | |
| Summary Calculations | | | |
| Multiple Children Batch | | | |

### Overall Result

- [ ] **PASS** - All tests completed successfully
- [ ] **FAIL** - Issues found (document below)

### Issues Found

| Issue # | Description | Severity | Status |
|---------|-------------|----------|--------|
| | | | |

### Notes

_Space for additional observations or notes during testing_

---

## Troubleshooting

### Common Issues

1. **Settings incomplete error**
   - Verify all required provider settings are filled
   - Check NEQ is exactly 10 digits
   - Check preparer number is exactly 6 digits

2. **No eligible forms found**
   - Verify forms are in "Approved" status
   - Check form year matches tax year selected
   - Verify documents complete if required

3. **XML validation errors**
   - Check SIN passes Luhn validation
   - Verify all required fields populated
   - Check date formats (YYYY-MM-DD)

4. **Summary calculation discrepancy**
   - Verify Box 14 = Box 12 - Box 13
   - Check for rounding issues
   - Verify correct slips included in batch

### Support Resources

- Module Documentation: `INSTALLATION_VERIFICATION.md`
- PHPUnit Tests: `gibbon/modules/RL24Submission/tests/`
- Verification Script: `verify_installation.php`

---

*Document Version: 1.0*
*Last Updated: 2026-02-17*
*RL-24 Submission Module End-to-End Test Guide*
