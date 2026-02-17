# LLM + Playwright Frontend QA Review

This guide covers the LLM-driven exploratory frontend testing workflow for the AISync module using Playwright.

## Overview

The LLM QA Playwright system combines automated Playwright browser testing with LLM-guided exploratory evaluation to provide comprehensive frontend quality assurance. This approach enables:

- **Automated test execution** - Playwright runs scripted user journeys
- **Evidence collection** - Screenshots, console logs, and network failures are captured
- **LLM interpretation** - Scenario packs guide exploratory evaluation of UI behavior
- **Structured reporting** - Findings are aggregated into markdown QA reports with reproduction steps

## Quick Start

### Prerequisites

1. **Install Playwright**:
   ```bash
   npm install -D @playwright/test
   npx playwright install
   ```

2. **Configure environment variables**:
   ```bash
   export GIBBON_URL="http://localhost:8080"
   export ADMIN_USERNAME="your-admin-username"
   export ADMIN_PASSWORD="your-admin-password"
   ```

3. **Start your Gibbon instance** with the AISync module enabled.

### Run Tests

```bash
# Run all E2E tests
npx playwright test

# Run with visible browser
npx playwright test --headed

# Run specific test file
npx playwright test tests/e2e/settings.spec.js

# Run with debugging
npx playwright test --debug

# Generate HTML report
npx playwright test --reporter=html
```

## Architecture

```
tests/e2e/
├── scenarios/                    # LLM scenario packs
│   ├── llm-fe-core.json         # Core user journeys (11 scenarios)
│   └── llm-fe-edge-cases.json   # Edge cases & security tests (20 scenarios)
├── helpers/                      # Test utilities
│   ├── llmReporter.ts           # Report generation & evidence linking
│   ├── networkCapture.ts        # Network/console error capture
│   └── index.ts                 # Module exports
├── settings.spec.js             # Settings page tests
├── health.spec.js               # Health dashboard tests
├── logs.spec.js                 # Logs viewer tests
└── README.md                    # E2E test documentation
```

## Scenario Packs

### Core Scenarios (`llm-fe-core.json`)

11 scenarios covering critical user journeys:

| ID | Name | Category | Severity |
|----|------|----------|----------|
| core-001 | First-Time Admin Login | authentication | critical |
| core-002 | Navigate to AISync Settings | navigation | high |
| core-003 | Configure AISync Settings | settings | high |
| core-004 | View Health Dashboard | monitoring | high |
| core-005 | Health Dashboard Status Indicator | monitoring | medium |
| core-006 | Health Dashboard Date Filtering | filtering | medium |
| core-007 | View Sync Logs | logs | high |
| core-008 | Filter Logs by Status | filtering | medium |
| core-009 | Filter Logs by Event Type | filtering | medium |
| core-010 | View Log Details Modal | logs | medium |
| core-011 | Cross-Page Navigation Flow | navigation | medium |

### Edge Case Scenarios (`llm-fe-edge-cases.json`)

20 scenarios covering negative tests and security:

| Category | Count | Examples |
|----------|-------|----------|
| Authentication | 2 | Invalid login, empty submission |
| Settings Validation | 6 | Invalid URL, boundary testing, type checks |
| Security | 3 | XSS attempts, SQL injection, unauth access |
| Filtering | 4 | Invalid date ranges, future dates, special chars |
| UX/Stress | 5 | Rapid submission, back button, refresh, multi-tab |

## Using the Helpers

### Network Capture

Capture and analyze network requests during test execution:

```typescript
import { createNetworkCapture } from './helpers';

test('captures network activity', async ({ page }) => {
  const capture = createNetworkCapture(page);
  await capture.start();

  // Perform test actions...
  await page.goto('/modules/AISync/aiSync_settings.php');

  // Get results
  const summary = capture.getSummary();
  const failures = capture.getFailedRequests();
  const errors = capture.getConsoleErrors();

  capture.stop();
});
```

**Key methods:**
- `start()` - Begin capturing network activity
- `stop()` - Stop capturing
- `getSummary()` - Get totals and failure list
- `getFailedRequests()` - Get failed requests with severity
- `getConsoleErrors()` - Get console errors/warnings
- `formatFailuresAsMarkdown()` - Generate markdown report section

### LLM Reporter

Generate structured QA reports with findings and evidence:

```typescript
import { createLlmReporter, type Finding } from './helpers';

test('generates report', async ({ page }) => {
  const reporter = createLlmReporter({
    outputDir: './test-results/llm-review',
    reportName: 'qa-review'
  });

  await reporter.initialize(page);

  // Start a scenario
  reporter.startScenario('core-001', 'First-Time Admin Login');

  // Capture screenshot
  const evidence = await reporter.captureScreenshot(page, 'login-page');

  // Add a finding
  reporter.addFinding({
    title: 'Login button positioning issue',
    description: 'Button is not centered on mobile viewport',
    category: 'ui',
    severity: 'low',
    tags: ['responsive', 'mobile']
  });

  // End scenario
  reporter.endScenario(true, 'Login flow completed');

  // Generate final report
  const report = await reporter.generateReport(
    'AISync QA Review',
    'AISync Module',
    'http://localhost:8080'
  );
});
```

**Finding categories:** `authentication`, `navigation`, `validation`, `ui`, `performance`, `security`, `accessibility`, `data`, `error-handling`, `other`

**Severity levels:** `critical`, `high`, `medium`, `low`, `info`

## Report Artifacts

After running tests, the following artifacts are generated:

### Output Directory Structure

```
test-results/
├── llm-review/
│   ├── llm-qa-report.md         # Markdown QA report
│   ├── llm-qa-report.json       # Machine-readable report
│   └── screenshots/
│       ├── core-001-login-page-*.png
│       ├── core-002-settings-*.png
│       └── ...
├── playwright-report/            # Playwright HTML report
│   └── index.html
└── traces/                       # Playwright traces (on retry)
```

### Reading the QA Report

The markdown report (`llm-qa-report.md`) contains:

1. **Executive Summary**
   - Total scenarios run, passed, failed
   - Findings count by severity
   - Critical and security issue highlights

2. **Top Findings** (sorted by severity)
   - Finding ID and title
   - Category, severity, URL
   - Detailed description
   - Reproduction steps
   - Evidence links
   - LLM analysis notes

3. **Scenario Results**
   - Pass/fail status per scenario
   - Duration and finding count
   - Network summary (requests, failures)
   - Screenshot count

4. **Environment Info**
   - Browser, OS, viewport
   - Execution timestamp

### Example Report Snippet

```markdown
## Executive Summary

| Metric | Value |
|--------|-------|
| Total Scenarios | 11 |
| Passed | 9 |
| Failed | 2 |
| Total Findings | 5 |
| Critical/High Issues | 2 |
| Security Issues | 1 |

## Top Findings

### F-0001: Network Failure: POST /api/sync
**Severity:** HIGH
**Category:** error-handling
**URL:** http://localhost:8080/api/sync

Request failed with status 500: Internal Server Error

**Evidence:**
- [screenshot](screenshots/core-003-error-1234567890.png): Error state after form submit
```

## CI/CD Integration

### GitHub Actions Example

```yaml
name: E2E Tests

on:
  push:
    branches: [main]
  pull_request:
    branches: [main]

jobs:
  e2e:
    runs-on: ubuntu-latest

    services:
      gibbon:
        image: gibbon-edu/gibbon:latest
        ports:
          - 8080:80

    steps:
      - uses: actions/checkout@v4

      - uses: actions/setup-node@v4
        with:
          node-version: 20

      - name: Install dependencies
        run: npm ci

      - name: Install Playwright
        run: npx playwright install --with-deps chromium

      - name: Run E2E tests
        env:
          GIBBON_URL: http://localhost:8080
          ADMIN_USERNAME: admin
          ADMIN_PASSWORD: ${{ secrets.ADMIN_PASSWORD }}
        run: npx playwright test

      - name: Upload test results
        if: always()
        uses: actions/upload-artifact@v4
        with:
          name: test-results
          path: |
            test-results/
            playwright-report/
```

### Local CI Simulation

```bash
# Run tests as they would run in CI
CI=true npx playwright test

# Run with specific workers
npx playwright test --workers=1

# Run with retries
npx playwright test --retries=2
```

## LLM Exploratory Guidance

When running LLM-guided exploratory testing, focus on:

### Visual Review
- Look for visual inconsistencies in the UI
- Check for responsive design issues
- Verify form validation messages are clear
- Note any slow-loading elements

### Error Handling
- Report unexpected console errors or warnings
- Check that error states are handled gracefully
- Verify error messages don't expose sensitive info

### Accessibility
- Verify keyboard navigation (Tab, Enter, Arrow keys)
- Check focus states are visible
- Ensure form labels are associated with inputs

### Security Considerations
- Test input validation for injection attempts
- Verify authentication is required for protected pages
- Check that errors don't leak system information

## Severity Guidelines

| Severity | Description | Examples |
|----------|-------------|----------|
| **Critical** | System unusable, security breach, data loss | Auth bypass, XSS success, page crash |
| **High** | Core functionality broken, major UI issues | Form won't submit, data not saved |
| **Medium** | Minor functional issues, cosmetic problems | Validation gap, poor error message |
| **Low** | Enhancement suggestions, minor inconsistencies | UX improvement, edge case handling |
| **Info** | Observations, notes for documentation | Behavior documentation, suggestions |

## Troubleshooting

### Tests timeout waiting for element

```bash
# Increase timeout
npx playwright test --timeout=120000

# Debug with headed browser
npx playwright test --headed --debug
```

### Authentication failures

1. Verify credentials are correct in environment variables
2. Check Gibbon is running and accessible
3. Ensure AISync module is installed and enabled
4. Check for CSRF token requirements

### Network capture shows unexpected failures

1. Check if requests are blocked by CORS
2. Verify API endpoints are configured correctly
3. Check for rate limiting or firewall rules

### Screenshots not capturing correctly

1. Ensure output directory exists and is writable
2. Check viewport size in playwright.config.js
3. Verify page has finished loading (use `waitForLoadState`)

### Empty report generated

1. Verify `reporter.initialize(page)` was called
2. Check scenarios were started/ended properly
3. Ensure `generateReport()` was called at the end

## Related Documentation

- [Playwright Documentation](https://playwright.dev/docs/intro)
- [tests/e2e/README.md](../tests/e2e/README.md) - E2E test structure
- [scenarios/llm-fe-core.json](../tests/e2e/scenarios/llm-fe-core.json) - Core scenarios
- [scenarios/llm-fe-edge-cases.json](../tests/e2e/scenarios/llm-fe-edge-cases.json) - Edge case scenarios
