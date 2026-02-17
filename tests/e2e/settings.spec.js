/**
 * E2E Tests for AISync Settings Page
 *
 * Tests the settings page UI and functionality.
 *
 * Requirements:
 * - Playwright installed
 * - Gibbon instance running at http://localhost:8080
 * - Valid admin credentials
 *
 * Run with:
 * - npx playwright test tests/e2e/settings.spec.js
 */

const { test, expect } = require('@playwright/test');

// Environment-configurable constants
const GIBBON_URL = process.env.GIBBON_URL || 'http://localhost:8080';
const ADMIN_USERNAME = process.env.ADMIN_USERNAME || 'admin';
const ADMIN_PASSWORD = process.env.ADMIN_PASSWORD || 'password';

test.describe('AISync Settings Page', () => {

    test.beforeEach(async ({ page }) => {
        // Login as admin
        await page.goto(`${GIBBON_URL}/index.php`);
        await page.fill('input[name="username"]', ADMIN_USERNAME);
        await page.fill('input[name="password"]', ADMIN_PASSWORD);
        await page.click('input[type="submit"], button[type="submit"]');

        // Wait for login to complete
        await page.waitForLoadState('networkidle');

        // Navigate to AISync settings using Gibbon's query-based routing
        await page.goto(`${GIBBON_URL}/index.php?q=/modules/AISync/aiSync_settings.php`);
        await page.waitForLoadState('networkidle');
    });

    test('can load settings page', async ({ page }) => {
        // Verify page contains AISync Settings breadcrumb or heading
        const pageContent = await page.content();
        expect(pageContent).toContain('AISync');

        // Verify the settings form is present
        const form = page.locator('form#aiSyncSettings');
        await expect(form).toBeVisible();

        // Verify required form fields are present
        await expect(page.locator('input[name="aiServiceURL"]')).toBeVisible();
        await expect(page.locator('input[name="webhookTimeout"]')).toBeVisible();
        await expect(page.locator('select[name="syncEnabled"]')).toBeVisible();
        await expect(page.locator('input[name="maxRetryAttempts"]')).toBeVisible();
        await expect(page.locator('input[name="retryDelaySeconds"]')).toBeVisible();
    });

    test('can update AI Service URL', async ({ page }) => {
        const newURL = 'http://test-ai-service:8000';

        // Fill in the new URL
        await page.fill('input[name="aiServiceURL"]', newURL);

        // Submit the form
        await page.click('form#aiSyncSettings input[type="submit"]');
        await page.waitForLoadState('networkidle');

        // Check for success message or verify the value persisted
        const savedURL = await page.locator('input[name="aiServiceURL"]').inputValue();
        expect(savedURL).toBe(newURL);
    });

    test('validates URL format', async ({ page }) => {
        // Clear and enter invalid URL
        await page.fill('input[name="aiServiceURL"]', 'not-a-valid-url');

        // Submit the form
        await page.click('form#aiSyncSettings input[type="submit"]');
        await page.waitForLoadState('networkidle');

        // Check for validation error - Gibbon shows errors in error divs
        const pageContent = await page.content();
        const hasValidationError = pageContent.includes('valid URL') ||
                                   pageContent.includes('error') ||
                                   pageContent.includes('Error');
        expect(hasValidationError).toBeTruthy();
    });

    test('validates numeric ranges', async ({ page }) => {
        // Test webhookTimeout > 300 (max allowed)
        await page.fill('input[name="webhookTimeout"]', '500');
        await page.click('form#aiSyncSettings input[type="submit"]');
        await page.waitForLoadState('networkidle');

        // Check for validation error
        let pageContent = await page.content();
        let hasValidationError = pageContent.includes('1 and 300') ||
                                 pageContent.includes('error') ||
                                 pageContent.includes('Error');
        expect(hasValidationError).toBeTruthy();

        // Navigate back and test webhookTimeout < 1 (min allowed)
        await page.goto(`${GIBBON_URL}/index.php?q=/modules/AISync/aiSync_settings.php`);
        await page.waitForLoadState('networkidle');
        await page.fill('input[name="webhookTimeout"]', '0');
        await page.click('form#aiSyncSettings input[type="submit"]');
        await page.waitForLoadState('networkidle');

        // Check for validation error
        pageContent = await page.content();
        hasValidationError = pageContent.includes('1 and 300') ||
                             pageContent.includes('error') ||
                             pageContent.includes('Error');
        expect(hasValidationError).toBeTruthy();

        // Navigate back and test maxRetryAttempts > 10 (max allowed)
        await page.goto(`${GIBBON_URL}/index.php?q=/modules/AISync/aiSync_settings.php`);
        await page.waitForLoadState('networkidle');
        await page.fill('input[name="maxRetryAttempts"]', '15');
        await page.click('form#aiSyncSettings input[type="submit"]');
        await page.waitForLoadState('networkidle');

        pageContent = await page.content();
        hasValidationError = pageContent.includes('0 and 10') ||
                             pageContent.includes('error') ||
                             pageContent.includes('Error');
        expect(hasValidationError).toBeTruthy();
    });

});
