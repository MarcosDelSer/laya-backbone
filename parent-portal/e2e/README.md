# Medical Protocol E2E Tests

This directory contains end-to-end tests for the Medical Protocol module using Playwright.

## Test Coverage

The `medical-protocol-workflow.spec.ts` file tests the complete workflow for Quebec medical protocols:

### Parent Portal - Authorization Flow
1. **Parent Login** - Verify parent can log into parent-portal
2. **Navigate to Medical Protocols** - Verify navigation to Medical Protocols page
3. **Select Child** - Verify child selection and Acetaminophen protocol visibility
4. **Enter Weight** - Verify weight input (12 kg example)
5. **Dosing Info** - Verify correct dosing info (120-180mg per dose for 12kg)
6. **E-Signature** - Verify authorization signing with e-signature

### Gibbon Admin - Staff Workflow
7. **Staff Login** - Verify staff can log into Gibbon admin
8. **View Authorization** - Verify staff sees child's active authorization
9. **Administer Dose** - Verify staff can administer acetaminophen and log dose
10. **4-Hour Interval** - Verify system prevents re-administration within 4 hours
11. **Compliance Report** - Verify administration appears in compliance report

### Quebec Protocol Compliance
- **FO-0647 (Acetaminophen)**: Dosing follows 10-15 mg/kg guideline
- **FO-0647**: Weight range validation (4.3kg - 35kg)
- **FO-0647**: Three concentration options (80mg/mL, 80mg/5mL, 160mg/5mL)
- **FO-0647**: Maximum 5 daily doses warning
- **FO-0646 (Insect Repellent)**: Age restriction (6 months minimum)

## Running Tests

```bash
# Install Playwright browsers (first time only)
npx playwright install

# Run all E2E tests
npm run test:e2e

# Run with UI mode
npm run test:e2e:ui

# Run headed (visible browser)
npm run test:e2e:headed

# Run specific test file
npx playwright test e2e/medical-protocol-workflow.spec.ts
```

## Test Environment Setup

The tests require:
1. Parent Portal running at `http://localhost:3000`
2. Gibbon admin running at `http://localhost:8080`
3. Test user accounts configured (see test constants in spec file)

## Manual Verification Checklist

For manual QA verification, follow these steps:

### Prerequisites
- [ ] Parent portal is running (`npm run dev`)
- [ ] Gibbon backend is running
- [ ] Test parent account exists
- [ ] Test staff account exists
- [ ] Test child exists with known details

### Parent Portal Verification
- [ ] Login as parent
- [ ] Navigate to Medical Protocols page
- [ ] Select test child
- [ ] View Acetaminophen protocol (FO-0647)
- [ ] Click "Sign Authorization"
- [ ] Enter weight (12 kg)
- [ ] Verify dosing shows "120 - 180 mg"
- [ ] Draw signature on canvas
- [ ] Submit authorization
- [ ] Verify success message

### Gibbon Admin Verification
- [ ] Login as staff
- [ ] Navigate to Medical Protocol module
- [ ] Go to Authorizations page
- [ ] Verify test child's authorization shows as "Active"
- [ ] Go to Administer page
- [ ] Select Acetaminophen protocol
- [ ] Select test child
- [ ] Enter dose (150mg)
- [ ] Enter temperature (38.5°C)
- [ ] Submit administration
- [ ] Verify success message
- [ ] Try to administer again immediately
- [ ] Verify 4-hour interval warning
- [ ] Go to Compliance Report
- [ ] Verify administration appears in report

### Compliance Verification
- [ ] Acetaminophen dosing follows 10-15 mg/kg
- [ ] Weight range is 4.3kg - 35kg
- [ ] Three concentrations available
- [ ] 4-hour interval enforced
- [ ] Max 5 daily doses enforced
- [ ] Insect repellent age restriction (6 months) enforced
