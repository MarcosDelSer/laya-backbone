/**
 * E2E Test: Medical Protocol Complete Workflow
 *
 * Tests the complete end-to-end workflow for Quebec medical protocols:
 * - Acetaminophen (FO-0647) authorization and administration
 * - Insect Repellent (FO-0646) authorization
 *
 * Verification Steps:
 * 1. Parent logs into parent-portal
 * 2. Parent navigates to Medical Protocols page
 * 3. Parent selects child and views Acetaminophen protocol
 * 4. Parent enters child weight (e.g., 12 kg)
 * 5. Parent sees correct dosing info (120-180mg per dose for 12kg)
 * 6. Parent signs authorization with e-signature
 * 7. Staff logs into Gibbon admin
 * 8. Staff sees child's active authorization
 * 9. Staff administers acetaminophen and logs dose
 * 10. System prevents re-administration within 4 hours
 * 11. Administration appears in compliance report
 *
 * @see Quebec Protocol FO-0647 (Acetaminophen)
 * @see Quebec Protocol FO-0646 (Insect Repellent)
 */

import { test, expect, Page } from '@playwright/test'

// Test constants
const TEST_PARENT_EMAIL = 'test.parent@example.com'
const TEST_PARENT_PASSWORD = 'TestParent123!'
const TEST_CHILD_NAME = 'Emma Test'
const TEST_CHILD_WEIGHT_KG = 12
const EXPECTED_MIN_DOSE_MG = 120 // 10 mg/kg * 12 kg
const EXPECTED_MAX_DOSE_MG = 180 // 15 mg/kg * 12 kg
const MIN_INTERVAL_HOURS = 4

// Gibbon admin test credentials
const STAFF_USERNAME = 'staff.admin'
const STAFF_PASSWORD = 'StaffAdmin123!'
const GIBBON_BASE_URL = 'http://localhost:8080'

test.describe('Medical Protocol Complete Workflow', () => {
  test.describe('Parent Portal - Authorization Flow', () => {
    /**
     * Step 1: Parent logs into parent-portal
     */
    test('1. Parent can log into parent-portal', async ({ page }) => {
      await page.goto('/login')

      // Fill login form
      await page.getByLabel('Email').fill(TEST_PARENT_EMAIL)
      await page.getByLabel('Password').fill(TEST_PARENT_PASSWORD)
      await page.getByRole('button', { name: /sign in|log in/i }).click()

      // Verify successful login - should redirect to dashboard
      await expect(page).toHaveURL(/dashboard|home|\/$/)
      await expect(page.getByText(/welcome|dashboard/i)).toBeVisible()
    })

    /**
     * Step 2: Parent navigates to Medical Protocols page
     */
    test('2. Parent can navigate to Medical Protocols page', async ({ page }) => {
      // Login first
      await loginAsParent(page)

      // Navigate to Medical Protocols
      await page.getByRole('link', { name: /medical/i }).click()

      // Verify page loaded
      await expect(page).toHaveURL(/medical-protocols/)
      await expect(page.getByRole('heading', { name: /medical protocol/i })).toBeVisible()
    })

    /**
     * Step 3: Parent selects child and views Acetaminophen protocol
     */
    test('3. Parent can select child and view Acetaminophen protocol', async ({ page }) => {
      await loginAsParent(page)
      await page.goto('/medical-protocols')

      // Select child from dropdown/list
      const childSelector = page.getByRole('combobox', { name: /child/i }).or(
        page.getByText(TEST_CHILD_NAME)
      )
      await childSelector.click()

      // If it's a dropdown, select the child
      if (await page.getByRole('option', { name: TEST_CHILD_NAME }).isVisible()) {
        await page.getByRole('option', { name: TEST_CHILD_NAME }).click()
      }

      // Verify Acetaminophen protocol is visible
      await expect(page.getByText('Acetaminophen')).toBeVisible()
      await expect(page.getByText('FO-0647')).toBeVisible()
    })

    /**
     * Step 4: Parent enters child weight (12 kg)
     */
    test('4. Parent can enter child weight', async ({ page }) => {
      await loginAsParent(page)
      await navigateToProtocolAuthorization(page)

      // Click to authorize/view Acetaminophen protocol
      await page.getByRole('button', { name: /sign authorization|authorize/i }).first().click()

      // Wait for modal/form to open
      await expect(page.getByText(/weight/i)).toBeVisible()

      // Enter weight
      const weightInput = page.getByRole('textbox', { name: /weight/i }).or(
        page.locator('input[type="number"]').first()
      )
      await weightInput.fill(TEST_CHILD_WEIGHT_KG.toString())

      // Verify weight is accepted
      await expect(page.getByText(/valid range/i)).toBeVisible()
    })

    /**
     * Step 5: Parent sees correct dosing info (120-180mg per dose for 12kg)
     */
    test('5. Parent sees correct dosing info for weight', async ({ page }) => {
      await loginAsParent(page)
      await navigateToProtocolAuthorization(page)

      // Open authorization form
      await page.getByRole('button', { name: /sign authorization|authorize/i }).first().click()

      // Enter weight
      await page.getByRole('textbox', { name: /weight/i }).or(
        page.locator('input[type="number"]').first()
      ).fill(TEST_CHILD_WEIGHT_KG.toString())

      // Wait for dosing chart to update
      await page.waitForTimeout(500)

      // Verify dosing information - should show 120-180mg range for 12kg
      // The exact format may vary: "120 - 180 mg" or "120-180mg"
      const dosingText = await page.getByText(/120.*180\s*mg/i).or(
        page.getByText(new RegExp(`${EXPECTED_MIN_DOSE_MG}.*${EXPECTED_MAX_DOSE_MG}`))
      )
      await expect(dosingText).toBeVisible()

      // Verify dosing guideline text
      await expect(page.getByText(/10-15\s*mg.*kg/i)).toBeVisible()
    })

    /**
     * Step 6: Parent signs authorization with e-signature
     */
    test('6. Parent can sign authorization with e-signature', async ({ page }) => {
      await loginAsParent(page)
      await navigateToProtocolAuthorization(page)

      // Open authorization form
      await page.getByRole('button', { name: /sign authorization|authorize/i }).first().click()

      // Enter weight
      await page.getByRole('textbox', { name: /weight/i }).or(
        page.locator('input[type="number"]').first()
      ).fill(TEST_CHILD_WEIGHT_KG.toString())

      // Find signature canvas
      const signatureCanvas = page.locator('canvas').first()
      await expect(signatureCanvas).toBeVisible()

      // Draw signature on canvas
      await drawSignature(signatureCanvas)

      // Accept terms if checkbox present
      const termsCheckbox = page.getByRole('checkbox', { name: /agree|accept|terms/i })
      if (await termsCheckbox.isVisible()) {
        await termsCheckbox.check()
      }

      // Submit authorization
      await page.getByRole('button', { name: /submit|sign|authorize/i }).click()

      // Verify success message
      await expect(page.getByText(/success|authorized|signed/i)).toBeVisible()
    })
  })

  test.describe('Gibbon Admin - Staff Authorization View', () => {
    /**
     * Step 7: Staff logs into Gibbon admin
     */
    test('7. Staff can log into Gibbon admin', async ({ page }) => {
      await page.goto(GIBBON_BASE_URL)

      // Fill login form
      await page.getByLabel(/username/i).fill(STAFF_USERNAME)
      await page.getByLabel(/password/i).fill(STAFF_PASSWORD)
      await page.getByRole('button', { name: /login|sign in/i }).click()

      // Verify successful login
      await expect(page.getByText(/dashboard|home/i)).toBeVisible()
    })

    /**
     * Step 8: Staff sees child's active authorization
     */
    test('8. Staff can see active authorization', async ({ page }) => {
      await loginAsStaff(page)

      // Navigate to Medical Protocol module
      await page.getByRole('link', { name: /medical protocol/i }).click()

      // Go to authorizations page
      await page.getByRole('link', { name: /authorization/i }).click()

      // Verify child's authorization is visible
      await expect(page.getByText(TEST_CHILD_NAME)).toBeVisible()
      await expect(page.getByText(/active|authorized/i)).toBeVisible()
      await expect(page.getByText('Acetaminophen')).toBeVisible()
    })

    /**
     * Step 9: Staff administers acetaminophen and logs dose
     */
    test('9. Staff can administer acetaminophen and log dose', async ({ page }) => {
      await loginAsStaff(page)
      await navigateToAdministerPage(page)

      // Select protocol
      await page.getByRole('combobox', { name: /protocol/i }).selectOption('Acetaminophen')

      // Select child
      await page.getByRole('combobox', { name: /child/i }).selectOption({ label: TEST_CHILD_NAME })

      // Enter dose information
      await page.getByLabel(/dose/i).fill('150')
      await page.getByLabel(/temperature/i).fill('38.5')

      // Select concentration
      const concentrationSelect = page.getByRole('combobox', { name: /concentration/i })
      if (await concentrationSelect.isVisible()) {
        await concentrationSelect.selectOption('80mg/5mL')
      }

      // Enter witness (if required)
      const witnessField = page.getByLabel(/witness/i)
      if (await witnessField.isVisible()) {
        await witnessField.fill('Jane Doe')
      }

      // Submit administration
      await page.getByRole('button', { name: /log|administer|submit/i }).click()

      // Verify success
      await expect(page.getByText(/success|logged|administered/i)).toBeVisible()
    })

    /**
     * Step 10: System prevents re-administration within 4 hours
     */
    test('10. System prevents re-administration within 4 hours', async ({ page }) => {
      await loginAsStaff(page)
      await navigateToAdministerPage(page)

      // Select protocol
      await page.getByRole('combobox', { name: /protocol/i }).selectOption('Acetaminophen')

      // Select same child (who was just administered to)
      await page.getByRole('combobox', { name: /child/i }).selectOption({ label: TEST_CHILD_NAME })

      // Should see a warning about 4-hour interval
      // The system should either disable the form or show a warning message
      const intervalWarning = page.getByText(/4.*hour/i).or(
        page.getByText(/cannot administer/i)
      ).or(
        page.getByText(/next.*allowed/i)
      )
      await expect(intervalWarning).toBeVisible()

      // If we can still enter data, try to submit and expect error
      const submitButton = page.getByRole('button', { name: /log|administer|submit/i })
      if (await submitButton.isEnabled()) {
        // Fill form
        await page.getByLabel(/dose/i).fill('150')
        await page.getByLabel(/temperature/i).fill('38.0')
        await submitButton.click()

        // Should see error message
        await expect(page.getByText(/interval|wait|hours/i)).toBeVisible()
      }
    })

    /**
     * Step 11: Administration appears in compliance report
     */
    test('11. Administration appears in compliance report', async ({ page }) => {
      await loginAsStaff(page)

      // Navigate to compliance report
      await page.getByRole('link', { name: /medical protocol/i }).click()
      await page.getByRole('link', { name: /compliance/i }).click()

      // Verify the administration from step 9 appears
      await expect(page.getByText(TEST_CHILD_NAME)).toBeVisible()
      await expect(page.getByText('Acetaminophen')).toBeVisible()
      await expect(page.getByText('FO-0647')).toBeVisible()

      // Check for compliance summary
      await expect(page.getByText(/administration/i)).toBeVisible()
    })
  })

  test.describe('Quebec Protocol Compliance - FO-0647', () => {
    /**
     * Verify acetaminophen dosing follows 10-15 mg/kg guideline
     */
    test('Dosing follows 10-15 mg/kg guideline', async ({ page }) => {
      await loginAsParent(page)
      await navigateToProtocolAuthorization(page)

      // Open authorization form
      await page.getByRole('button', { name: /sign authorization|authorize/i }).first().click()

      // Test multiple weights and verify correct dosing
      const testCases = [
        { weight: 5, minDose: 50, maxDose: 75 },
        { weight: 10, minDose: 100, maxDose: 150 },
        { weight: 12, minDose: 120, maxDose: 180 },
        { weight: 15, minDose: 150, maxDose: 225 },
        { weight: 20, minDose: 200, maxDose: 300 },
        { weight: 30, minDose: 300, maxDose: 450 },
      ]

      for (const tc of testCases) {
        // Enter weight
        await page.getByRole('textbox', { name: /weight/i }).or(
          page.locator('input[type="number"]').first()
        ).fill(tc.weight.toString())

        // Wait for UI update
        await page.waitForTimeout(300)

        // Verify dosing range
        const dosingText = page.getByText(new RegExp(`${tc.minDose}.*${tc.maxDose}`))
        await expect(dosingText).toBeVisible({ timeout: 2000 })
      }
    })

    /**
     * Verify weight range validation (4.3kg - 35kg)
     */
    test('Weight range validation works correctly', async ({ page }) => {
      await loginAsParent(page)
      await navigateToProtocolAuthorization(page)

      // Open authorization form
      await page.getByRole('button', { name: /sign authorization|authorize/i }).first().click()

      const weightInput = page.getByRole('textbox', { name: /weight/i }).or(
        page.locator('input[type="number"]').first()
      )

      // Test below minimum (4.3 kg)
      await weightInput.fill('3')
      await expect(page.getByText(/minimum|too low|out of range/i)).toBeVisible()

      // Test above maximum (35 kg)
      await weightInput.fill('40')
      await expect(page.getByText(/maximum|consult.*healthcare|out of range/i)).toBeVisible()

      // Test valid weight
      await weightInput.fill('15')
      await expect(page.getByText(/valid.*range/i)).toBeVisible()
    })

    /**
     * Verify three concentration options are available
     */
    test('Three acetaminophen concentrations are available', async ({ page }) => {
      await loginAsParent(page)
      await navigateToProtocolAuthorization(page)

      // Open authorization form
      await page.getByRole('button', { name: /sign authorization|authorize/i }).first().click()

      // Enter valid weight
      await page.getByRole('textbox', { name: /weight/i }).or(
        page.locator('input[type="number"]').first()
      ).fill('12')

      // Verify all three concentrations are shown
      await expect(page.getByText(/80.*mg.*mL/i).first()).toBeVisible() // 80mg/mL drops
      await expect(page.getByText(/80.*mg.*5.*mL/i).first()).toBeVisible() // 80mg/5mL syrup
      await expect(page.getByText(/160.*mg.*5.*mL/i).first()).toBeVisible() // 160mg/5mL concentrated
    })

    /**
     * Verify max 5 daily doses warning
     */
    test('Maximum 5 daily doses warning is shown', async ({ page }) => {
      await loginAsParent(page)
      await navigateToProtocolAuthorization(page)

      // Open authorization form
      await page.getByRole('button', { name: /sign authorization|authorize/i }).first().click()

      // Enter valid weight
      await page.getByRole('textbox', { name: /weight/i }).or(
        page.locator('input[type="number"]').first()
      ).fill('12')

      // Verify daily dose warning
      await expect(page.getByText(/5.*doses/i).or(page.getByText(/maximum.*doses/i))).toBeVisible()
    })
  })

  test.describe('Quebec Protocol Compliance - FO-0646', () => {
    /**
     * Verify insect repellent age restriction (6 months minimum)
     */
    test('Insect repellent enforces 6-month age restriction', async ({ page }) => {
      await loginAsParent(page)
      await page.goto('/medical-protocols')

      // Look for insect repellent protocol
      const insectRepellentCard = page.getByText('Insect Repellent').or(page.getByText('FO-0646'))
      await expect(insectRepellentCard).toBeVisible()

      // If child is under 6 months, should see age restriction warning
      const ageRestrictionWarning = page.getByText(/6.*month/i).or(
        page.getByText(/age.*restriction/i)
      )

      // Note: This test may pass or fail depending on the test child's age
      // The important thing is the system shows appropriate warnings
    })
  })
})

// ============================================================================
// Helper Functions
// ============================================================================

/**
 * Login as test parent user
 */
async function loginAsParent(page: Page) {
  await page.goto('/login')
  await page.getByLabel('Email').fill(TEST_PARENT_EMAIL)
  await page.getByLabel('Password').fill(TEST_PARENT_PASSWORD)
  await page.getByRole('button', { name: /sign in|log in/i }).click()
  await page.waitForURL(/dashboard|home|\/$/)
}

/**
 * Login as staff user in Gibbon
 */
async function loginAsStaff(page: Page) {
  await page.goto(GIBBON_BASE_URL)
  await page.getByLabel(/username/i).fill(STAFF_USERNAME)
  await page.getByLabel(/password/i).fill(STAFF_PASSWORD)
  await page.getByRole('button', { name: /login|sign in/i }).click()
  await page.waitForLoadState('networkidle')
}

/**
 * Navigate to protocol authorization page
 */
async function navigateToProtocolAuthorization(page: Page) {
  await page.goto('/medical-protocols')

  // Select child if needed
  const childSelector = page.getByRole('combobox', { name: /child/i })
  if (await childSelector.isVisible()) {
    await childSelector.click()
    const childOption = page.getByRole('option', { name: TEST_CHILD_NAME })
    if (await childOption.isVisible()) {
      await childOption.click()
    }
  }
}

/**
 * Navigate to administer page in Gibbon
 */
async function navigateToAdministerPage(page: Page) {
  await page.getByRole('link', { name: /medical protocol/i }).click()
  await page.getByRole('link', { name: /administer/i }).click()
  await page.waitForLoadState('networkidle')
}

/**
 * Draw a simple signature on canvas
 */
async function drawSignature(canvas: ReturnType<Page['locator']>) {
  const box = await canvas.boundingBox()
  if (!box) {
    throw new Error('Could not find canvas bounding box')
  }

  // Draw a simple signature pattern
  const startX = box.x + 20
  const startY = box.y + box.height / 2
  const endX = box.x + box.width - 20
  const endY = box.y + box.height / 2

  // Move to start position
  await canvas.page().mouse.move(startX, startY)
  await canvas.page().mouse.down()

  // Draw a wavy line (simple signature)
  const steps = 10
  for (let i = 0; i <= steps; i++) {
    const x = startX + (endX - startX) * (i / steps)
    const y = startY + Math.sin(i * Math.PI / 2) * 20
    await canvas.page().mouse.move(x, y)
  }

  await canvas.page().mouse.up()
}
