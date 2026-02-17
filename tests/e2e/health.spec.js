/**
 * E2E Tests for AISync Health Monitoring Page
 *
 * Tests the health dashboard UI and functionality.
 *
 * Requirements:
 * - Playwright installed
 * - Gibbon instance running at http://localhost:8080
 * - Valid admin credentials
 *
 * Run with:
 * - npx playwright test tests/e2e/health.spec.js
 */

const { test, expect } = require('@playwright/test');

// Environment-configurable constants
const GIBBON_URL = process.env.GIBBON_URL || 'http://localhost:8080';
const ADMIN_USERNAME = process.env.ADMIN_USERNAME || 'admin';
const ADMIN_PASSWORD = process.env.ADMIN_PASSWORD || 'password';

test.describe('AISync Health Monitoring', () => {

    test.beforeEach(async ({ page }) => {
        // Login as admin
        await page.goto(`${GIBBON_URL}/index.php`);
        await page.fill('input[name="username"]', ADMIN_USERNAME);
        await page.fill('input[name="password"]', ADMIN_PASSWORD);
        await page.click('input[type="submit"], button[type="submit"]');

        // Wait for login to complete
        await page.waitForLoadState('networkidle');

        // Navigate to health page using Gibbon's query-based routing
        await page.goto(`${GIBBON_URL}/index.php?q=/modules/AISync/aiSync_health.php`);
        await page.waitForLoadState('networkidle');
    });

    test('displays health metrics', async ({ page }) => {
        // Verify page contains health monitoring content
        const pageContent = await page.content();
        expect(pageContent).toContain('Webhook Health');

        // Verify metrics cards are present - the page has 5 main metrics in a grid
        // Total Syncs, Pending, Successful, Failed, Permanently Failed
        const metricsGrid = page.locator('.grid');
        await expect(metricsGrid.first()).toBeVisible();

        // Verify key metrics text is present
        expect(pageContent).toContain('Total Syncs');
        expect(pageContent).toContain('Pending');
        expect(pageContent).toContain('Successful');
        expect(pageContent).toContain('Failed');

        // Verify status indicator banner is present (shows healthy, warning, or critical)
        const statusBanner = page.locator('.rounded-lg.p-4.mb-6').first();
        await expect(statusBanner).toBeVisible();
    });

    test('date range filtering works', async ({ page }) => {
        // Find the filter form
        const filterForm = page.locator('form#filter');
        await expect(filterForm).toBeVisible();

        // Get date inputs - Gibbon uses its own date picker
        const dateFromInput = page.locator('input[name="dateFrom"]');
        const dateToInput = page.locator('input[name="dateTo"]');

        await expect(dateFromInput).toBeVisible();
        await expect(dateToInput).toBeVisible();

        // Fill in date range
        await dateFromInput.fill('2026-01-01');
        await dateToInput.fill('2026-01-31');

        // Submit the filter form
        await page.click('form#filter input[type="submit"]');
        await page.waitForLoadState('networkidle');

        // Verify the page reloaded with filter parameters in URL
        const currentUrl = page.url();
        expect(currentUrl).toContain('dateFrom');
        expect(currentUrl).toContain('dateTo');

        // Verify metrics section is still visible after filtering
        const pageContent = await page.content();
        expect(pageContent).toContain('Total Syncs');
    });

    test('displays status indicator correctly', async ({ page }) => {
        // The status banner uses Tailwind classes for styling:
        // - healthy: bg-green-50
        // - warning: bg-yellow-50
        // - critical: bg-red-50

        const pageContent = await page.content();

        // Verify the status banner is present with one of the expected statuses
        const hasHealthyStatus = pageContent.includes('bg-green-50') || pageContent.includes('healthy');
        const hasWarningStatus = pageContent.includes('bg-yellow-50') || pageContent.includes('warning');
        const hasCriticalStatus = pageContent.includes('bg-red-50') || pageContent.includes('critical');

        // At least one status indicator should be present
        expect(hasHealthyStatus || hasWarningStatus || hasCriticalStatus).toBeTruthy();

        // Verify status icons are present (checkmark, warning, or X)
        const hasStatusIcon = pageContent.includes('✓') ||
                              pageContent.includes('⚠') ||
                              pageContent.includes('✗');
        expect(hasStatusIcon).toBeTruthy();

        // Verify the status text is displayed
        expect(pageContent).toContain('Webhook Health Status');
    });

});
