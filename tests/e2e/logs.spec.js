/**
 * E2E Tests for AISync Logs Viewer
 *
 * Tests the logs viewer UI and functionality.
 *
 * Requirements:
 * - Playwright installed
 * - Gibbon instance running at http://localhost:8080
 * - Valid admin credentials
 *
 * Run with:
 * - npx playwright test tests/e2e/logs.spec.js
 */

const { test, expect } = require('@playwright/test');

// Environment-configurable constants
const GIBBON_URL = process.env.GIBBON_URL || 'http://localhost:8080';
const ADMIN_USERNAME = process.env.ADMIN_USERNAME || 'admin';
const ADMIN_PASSWORD = process.env.ADMIN_PASSWORD || 'password';

test.describe('AISync Logs Viewer', () => {

    test.beforeEach(async ({ page }) => {
        // Login as admin
        await page.goto(`${GIBBON_URL}/index.php`);
        await page.fill('input[name="username"]', ADMIN_USERNAME);
        await page.fill('input[name="password"]', ADMIN_PASSWORD);
        await page.click('input[type="submit"], button[type="submit"]');

        // Wait for login to complete
        await page.waitForLoadState('networkidle');

        // Navigate to logs page using Gibbon's query-based routing
        await page.goto(`${GIBBON_URL}/index.php?q=/modules/AISync/aiSync_logs.php`);
        await page.waitForLoadState('networkidle');
    });

    test('displays sync logs', async ({ page }) => {
        // Verify page contains logs viewer content
        const pageContent = await page.content();
        expect(pageContent).toContain('Sync Logs');

        // Verify the DataTable container is present
        const tableContainer = page.locator('table');
        await expect(tableContainer.first()).toBeVisible();

        // Verify key column headers are present
        expect(pageContent).toContain('Timestamp');
        expect(pageContent).toContain('Event Type');
        expect(pageContent).toContain('Status');

        // Verify quick statistics section is present
        expect(pageContent).toContain('Quick Statistics');
    });

    test('filtering works', async ({ page }) => {
        // Verify filter form is present
        const filterForm = page.locator('form#filters');
        await expect(filterForm).toBeVisible();

        // Verify filter controls are present
        await expect(page.locator('select[name="status"]')).toBeVisible();
        await expect(page.locator('select[name="eventType"]')).toBeVisible();
        await expect(page.locator('select[name="entityType"]')).toBeVisible();
        await expect(page.locator('input[name="dateFrom"]')).toBeVisible();
        await expect(page.locator('input[name="dateTo"]')).toBeVisible();

        // Filter by status - select 'success'
        await page.selectOption('select[name="status"]', 'success');

        // Submit filter form
        await page.click('form#filters input[type="submit"]');
        await page.waitForLoadState('networkidle');

        // Verify URL contains the filter parameter
        let currentUrl = page.url();
        expect(currentUrl).toContain('status=success');

        // Navigate back and test event type filter
        await page.goto(`${GIBBON_URL}/index.php?q=/modules/AISync/aiSync_logs.php`);
        await page.waitForLoadState('networkidle');

        // Filter by event type
        await page.selectOption('select[name="eventType"]', 'care_activity_created');
        await page.click('form#filters input[type="submit"]');
        await page.waitForLoadState('networkidle');

        currentUrl = page.url();
        expect(currentUrl).toContain('eventType=care_activity_created');

        // Navigate back and test date range filter
        await page.goto(`${GIBBON_URL}/index.php?q=/modules/AISync/aiSync_logs.php`);
        await page.waitForLoadState('networkidle');

        // Filter by date range
        await page.fill('input[name="dateFrom"]', '2026-01-01');
        await page.fill('input[name="dateTo"]', '2026-01-31');
        await page.click('form#filters input[type="submit"]');
        await page.waitForLoadState('networkidle');

        currentUrl = page.url();
        expect(currentUrl).toContain('dateFrom');
        expect(currentUrl).toContain('dateTo');
    });

    test('log details modal opens', async ({ page }) => {
        // Check if any logs exist by looking for action links
        const viewDetailsLinks = page.locator('a[href*="aiSync_logsDetails"]');
        const linkCount = await viewDetailsLinks.count();

        if (linkCount > 0) {
            // Click the first "View Details" action link
            // This opens in a modal window
            const [popup] = await Promise.all([
                page.waitForEvent('popup'),
                viewDetailsLinks.first().click()
            ]);

            // Wait for popup to load
            await popup.waitForLoadState('networkidle');

            // Verify details page/modal loaded
            const detailsContent = await popup.content();

            // Should contain log details structure
            expect(detailsContent).toContain('gibbonAISyncLogID');

            // Close the popup
            await popup.close();
        } else {
            // No logs available - verify the empty state or table is still visible
            const pageContent = await page.content();
            expect(pageContent).toContain('Sync Logs');

            // The page should still be functional without logs
            const tableContainer = page.locator('table');
            await expect(tableContainer.first()).toBeVisible();
        }
    });

});
