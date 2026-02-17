# AISync E2E Tests

End-to-end browser tests for the AISync module UI pages, with LLM-guided exploratory testing support.

## Requirements

### Option 1: Playwright (Recommended)

```bash
npm install -D @playwright/test
npx playwright install
```

### Option 2: Cypress

```bash
npm install -D cypress
npx cypress open
```

## Setup

1. **Configure Environment:**

   Set environment variables for your test environment:
   ```bash
   export GIBBON_URL="http://localhost:8080"
   export ADMIN_USERNAME="your-admin-username"
   export ADMIN_PASSWORD="your-admin-password"
   ```

   Or update test constants in each spec file:
   ```javascript
   const GIBBON_URL = 'http://localhost:8080';
   const ADMIN_USERNAME = 'your-admin-username';
   const ADMIN_PASSWORD = 'your-admin-password';
   ```

2. **Start Gibbon:**

   Ensure Gibbon is running and accessible at the configured URL.

3. **Create Test Data:**

   You may need to create test sync logs for some tests to work properly.

## Running Tests

### Playwright

```bash
# Run all E2E tests
npx playwright test tests/e2e/

# Run specific test file
npx playwright test tests/e2e/settings.spec.js

# Run with visible browser
npx playwright test --headed

# Run with UI mode
npx playwright test --ui

# Run with debugging
npx playwright test --debug

# Generate HTML report
npx playwright test --reporter=html
npx playwright show-report
```

### Cypress

```bash
# Open Cypress test runner
npx cypress open

# Run headless
npx cypress run --spec "tests/e2e/**/*.spec.js"
```

## Test Coverage

### Settings Page (`settings.spec.js`)
- Page loads correctly
- Can update AI Service URL
- Validates URL format
- Validates numeric ranges (timeout, retry attempts)

### Health Monitoring (`health.spec.js`)
- Displays health metrics (total, pending, success, failed)
- Date range filtering works
- Status indicator shows correct status (healthy/warning/critical)

### Logs Viewer (`logs.spec.js`)
- Displays sync logs in table
- Filtering works (status, event type, entity type, date range)
- Log details modal opens and displays JSON payload

## LLM Exploratory Testing

### Overview

This test suite includes LLM-guided exploratory testing capabilities for comprehensive frontend quality assurance. The system uses scenario packs to define user journeys and captures evidence for LLM interpretation.

### Scenario Packs

Located in `scenarios/`:

| File | Description | Scenario Count |
|------|-------------|----------------|
| `llm-fe-core.json` | Core user journeys (auth, navigation, settings, health, logs) | 11 |
| `llm-fe-edge-cases.json` | Edge cases, security tests, boundary conditions | 20 |

### Running LLM Exploratory Tests

```bash
# Run tests with full evidence collection
COLLECT_EVIDENCE=true npx playwright test

# Run with trace recording for debugging
npx playwright test --trace=on

# Run specific scenario category (using grep)
npx playwright test --grep "settings"
npx playwright test --grep "health"
npx playwright test --grep "logs"
```

### Using Test Helpers

The `helpers/` directory provides utilities for LLM-guided testing:

#### Network Capture

```typescript
import { createNetworkCapture } from './helpers';

const capture = createNetworkCapture(page);
await capture.start();

// ... perform test actions ...

const summary = capture.getSummary();
console.log(`Total requests: ${summary.totalRequests}`);
console.log(`Failed requests: ${summary.failedRequests}`);
console.log(`Console errors: ${summary.consoleErrors}`);

capture.stop();
```

#### LLM Reporter

```typescript
import { createLlmReporter } from './helpers';

const reporter = createLlmReporter({
  outputDir: './test-results/llm-review',
  reportName: 'qa-review'
});

await reporter.initialize(page);
reporter.startScenario('core-001', 'First-Time Admin Login');

// Capture evidence
await reporter.captureScreenshot(page, 'login-page');

// Record finding
reporter.addFinding({
  title: 'Issue description',
  description: 'Detailed description',
  category: 'ui',
  severity: 'medium',
  tags: ['responsive']
});

reporter.endScenario(true);
await reporter.generateReport('AISync QA Review', 'AISync', baseUrl);
```

### Test Artifacts

After running tests, find artifacts in:

```
test-results/
├── llm-review/
│   ├── llm-qa-report.md         # Markdown QA report
│   ├── llm-qa-report.json       # Machine-readable report
│   └── screenshots/             # Captured screenshots
├── playwright-report/           # HTML test report
│   └── index.html
└── traces/                      # Playwright traces
```

### Reading QA Reports

The markdown report (`llm-qa-report.md`) includes:

1. **Executive Summary** - Pass/fail counts, severity breakdown
2. **Top Findings** - Issues sorted by severity with repro steps
3. **Scenario Results** - Per-scenario status, duration, network stats
4. **Environment Info** - Browser, OS, viewport details

Open the HTML report:
```bash
npx playwright show-report
```

## Best Practices

- **Selectors:** Use data-testid attributes for stable selectors
- **Waits:** Use Playwright's auto-waiting or Cypress's built-in retry logic
- **Cleanup:** Clean up test data after each test
- **Screenshots:** Capture screenshots on failure for debugging
- **Videos:** Enable video recording for CI/CD pipelines
- **Evidence:** Use network capture and reporter helpers for LLM review

## CI/CD Integration

```bash
# Run tests in CI mode (no headed, single worker, retries)
CI=true npx playwright test

# Run with JUnit reporter for CI systems
npx playwright test --reporter=junit
```

Example GitHub Actions workflow:
```yaml
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

## Troubleshooting

**Tests fail immediately:**
- Verify Gibbon is running at configured URL
- Check admin credentials are correct
- Ensure AISync module is installed and enabled

**Tests timeout:**
- Increase timeout values in Playwright/Cypress config
- Check network connectivity
- Verify Gibbon isn't under heavy load
- Use `--timeout=120000` for longer timeouts

**Elements not found:**
- Inspect actual HTML structure in browser
- Update selectors to match Gibbon's generated HTML
- Use browser DevTools to find correct selectors
- Try running with `--headed --debug` for visual debugging

**Network capture shows failures:**
- Check CORS configuration
- Verify API endpoints exist
- Look for rate limiting or auth issues

**Empty reports generated:**
- Ensure `reporter.initialize()` was called
- Verify scenarios were properly started/ended
- Check `generateReport()` was called

## Documentation

For comprehensive LLM QA workflow documentation, see:
- [LLM QA Playwright Guide](../../docs/LLM_QA_PLAYWRIGHT.md)
