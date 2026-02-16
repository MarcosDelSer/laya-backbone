# AISync E2E Tests

End-to-end browser tests for the AISync module UI pages.

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

   Update test constants in each spec file:
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

# Run with UI
npx playwright test --ui

# Run with debugging
npx playwright test --debug
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
- ✓ Page loads correctly
- ✓ Can update AI Service URL
- ✓ Validates URL format
- ✓ Validates numeric ranges (timeout, retry attempts)

### Health Monitoring (`health.spec.js`)
- ✓ Displays health metrics (total, pending, success, failed)
- ✓ Date range filtering works
- ✓ Status indicator shows correct status (healthy/warning/critical)

### Logs Viewer (`logs.spec.js`)
- ✓ Displays sync logs in table
- ✓ Filtering works (status, event type, entity type, date range)
- ✓ Log details modal opens and displays JSON payload

## Implementation Status

**Current Status:** Test structure created, implementation pending

All test files are currently marked as `test.skip()` and contain TODO comments indicating what needs to be implemented. To complete E2E testing:

1. Install Playwright or Cypress
2. Update environment constants
3. Implement each test case by removing `test.skip()` and completing TODO sections
4. Run tests to verify UI functionality

## Best Practices

- **Selectors:** Use data-testid attributes for stable selectors
- **Waits:** Use Playwright's auto-waiting or Cypress's built-in retry logic
- **Cleanup:** Clean up test data after each test
- **Screenshots:** Capture screenshots on failure for debugging
- **Videos:** Enable video recording for CI/CD pipelines

## Troubleshooting

**Tests fail immediately:**
- Verify Gibbon is running at configured URL
- Check admin credentials are correct
- Ensure AISync module is installed and enabled

**Tests timeout:**
- Increase timeout values in Playwright/Cypress config
- Check network connectivity
- Verify Gibbon isn't under heavy load

**Elements not found:**
- Inspect actual HTML structure in browser
- Update selectors to match Gibbon's generated HTML
- Use browser DevTools to find correct selectors
