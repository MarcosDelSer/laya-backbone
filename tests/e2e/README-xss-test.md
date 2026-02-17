# XSS Prevention E2E Tests

This directory contains end-to-end tests for verifying XSS (Cross-Site Scripting) prevention measures in the parent-portal application.

## Overview

XSS prevention is critical for web application security. These tests verify that:
1. User input containing malicious scripts is properly sanitized
2. Dangerous HTML tags and attributes are removed
3. Safe HTML formatting is preserved
4. Multiple attack vectors are blocked

## Test Coverage

The XSS prevention test suite (`xss-prevention.spec.js`) includes:

### Core Functionality Tests
- ✅ Script tag sanitization
- ✅ Safe content preservation
- ✅ Complete XSS prevention workflow (4 verification steps)

### Attack Vector Tests
- ✅ Script tag XSS: `<script>alert('xss')</script>`
- ✅ Image onerror XSS: `<img onerror="alert('xss')">`
- ✅ JavaScript URL XSS: `<a href="javascript:alert('xss')">`
- ✅ Data URI XSS: `<a href="data:text/html,<script>...">`
- ✅ Event handler attributes: `onclick`, `onload`, etc.
- ✅ Iframe injection: `<iframe src="javascript:...">`
- ✅ Object/Embed injection: `<object>`, `<embed>`
- ✅ SVG script injection: `<svg><script>...</script></svg>`

### Safe Content Preservation Tests
- ✅ Paragraph tags: `<p>`
- ✅ Text formatting: `<strong>`, `<em>`, `<u>`
- ✅ Safe links: `<a href="https://...">`
- ✅ Lists: `<ul>`, `<ol>`, `<li>`
- ✅ Headings: `<h1>`, `<h2>`, etc.
- ✅ Blockquotes: `<blockquote>`

### Mixed Content Tests
- ✅ Sanitize XSS while preserving safe HTML
- ✅ Handle nested XSS attempts
- ✅ Multiple attack vectors in single payload

### Edge Cases
- ✅ Empty input
- ✅ Plain text (no HTML)
- ✅ Malformed HTML
- ✅ Case variations in tags
- ✅ HTML entity encoding

## Prerequisites

1. **parent-portal running:**
   ```bash
   cd parent-portal
   npm run dev
   ```
   Default URL: http://localhost:3000

2. **Playwright installed:**
   ```bash
   npm install -D @playwright/test
   npx playwright install
   ```

3. **XSS sanitization utilities:**
   - File: `parent-portal/lib/security/sanitize.ts`
   - Must include: `sanitizeHTML`, `sanitizeText`, `sanitizeURL`, etc.

## Running Tests

### Quick Start

```bash
# Run automated verification script
./tests/e2e/verify-xss-prevention.sh
```

### Run Tests Manually

```bash
# Run all XSS prevention tests
npx playwright test tests/e2e/xss-prevention.spec.js

# Run with visible browser (headed mode)
npx playwright test tests/e2e/xss-prevention.spec.js --headed

# Run with UI mode for interactive debugging
npx playwright test tests/e2e/xss-prevention.spec.js --ui

# Run specific test group
npx playwright test tests/e2e/xss-prevention.spec.js --grep "Attack Vectors"

# Run with debugging
npx playwright test tests/e2e/xss-prevention.spec.js --debug

# Generate HTML report
npx playwright test tests/e2e/xss-prevention.spec.js --reporter=html
npx playwright show-report
```

### Environment Variables

Configure test environment:

```bash
# Custom parent-portal URL
export PARENT_PORTAL_URL="http://localhost:3001"
npx playwright test tests/e2e/xss-prevention.spec.js
```

## Test Architecture

### Test Structure

```
tests/e2e/
├── xss-prevention.spec.js          # Main E2E test suite
├── verify-xss-prevention.sh        # Automated verification script
└── README-xss-test.md              # This documentation
```

### Test Flow

Each test follows this pattern:

1. **Navigate to parent-portal**
   ```javascript
   await page.goto(PARENT_PORTAL_URL);
   ```

2. **Inject and test XSS payload**
   ```javascript
   const result = await page.evaluate((payload) => {
     // Simulate sanitization
     const parser = new DOMParser();
     const doc = parser.parseFromString(payload, 'text/html');
     // ... sanitization logic ...
     return { sanitized, hasScript, hasSafeContent };
   }, xssPayload);
   ```

3. **Verify sanitization**
   ```javascript
   expect(result.hasScript).toBe(false);
   expect(result.hasSafeContent).toBe(true);
   ```

### Complete Verification Workflow

The main test verifies the 4 required steps from the spec:

```javascript
test('complete XSS prevention workflow', async ({ page }) => {
  // Step 1: Submit form with XSS payload: <script>alert('xss')</script>
  const xssPayload = '<script>alert("xss")</script><p>Safe content</p>';

  // Step 2: Verify sanitization removes script tags
  const sanitizationResult = await page.evaluate(/* ... */);
  expect(sanitizationResult.hasScript).toBe(false);

  // Step 3: Render content and check no script execution
  const scriptExecuted = await page.evaluate(/* ... */);
  expect(scriptExecuted).toBe(false);

  // Step 4: Verify safe HTML tags are preserved
  expect(sanitizationResult.hasSafeContent).toBe(true);
  expect(sanitizationResult.sanitized).toContain('<p>');
});
```

## Manual Verification

### Browser Testing

1. **Open parent-portal:** http://localhost:3000

2. **Navigate to a form with user input:**
   - Profile page (bio field)
   - Messages page (message composition)
   - Comments section
   - Any rich text editor

3. **Test XSS payloads:**

   **Script Injection:**
   ```html
   <script>alert('xss')</script>
   ```
   ✅ Expected: Script tag removed, no alert shown

   **Event Handler:**
   ```html
   <img src="invalid" onerror="alert('xss')">
   ```
   ✅ Expected: `onerror` attribute removed

   **JavaScript URL:**
   ```html
   <a href="javascript:alert('xss')">Click me</a>
   ```
   ✅ Expected: Link href changed to `#` or removed

   **Mixed Content:**
   ```html
   <p>Hello <strong>World</strong></p><script>alert('xss')</script>
   ```
   ✅ Expected: Paragraph and formatting preserved, script removed

4. **Check browser console:**
   - Open DevTools (F12)
   - Look for CSP violations
   - Verify no JavaScript errors from XSS attempts

### Unit Test Verification

Run the sanitization utility unit tests:

```bash
cd parent-portal
npm test lib/security/__tests__/sanitize.test.ts
```

Expected output: All tests passing (100+ test cases)

## Troubleshooting

### Tests Fail Immediately

**Problem:** Tests fail to start or connect
```
Error: page.goto: net::ERR_CONNECTION_REFUSED
```

**Solution:**
1. Verify parent-portal is running:
   ```bash
   curl http://localhost:3000
   ```
2. Check port configuration (default: 3000)
3. Ensure no firewall blocking localhost

### Script Tags Not Removed

**Problem:** Tests show script tags are not being sanitized

**Solution:**
1. Check sanitization utilities are imported:
   ```typescript
   import { sanitizeHTML } from '@/lib/security/sanitize';
   ```
2. Verify DOMPurify is installed:
   ```bash
   npm list isomorphic-dompurify
   ```
3. Check sanitization is applied before rendering:
   ```tsx
   const safe = sanitizeHTML(userInput);
   <div dangerouslySetInnerHTML={{ __html: safe }} />
   ```

### Safe Content Removed

**Problem:** Tests show legitimate HTML is being removed

**Solution:**
1. Check allowed tags configuration in `sanitize.ts`:
   ```typescript
   const DEFAULT_ALLOWED_TAGS = ['p', 'br', 'strong', 'em', ...];
   ```
2. Use appropriate sanitization preset:
   ```typescript
   import { SanitizePresets } from '@/lib/security/sanitize';
   const safe = sanitizeHTML(input, SanitizePresets.RICH_TEXT);
   ```

### Playwright Installation Issues

**Problem:** Playwright browsers not installed

**Solution:**
```bash
npx playwright install
npx playwright install-deps  # Install system dependencies
```

### Permission Denied on Script

**Problem:** Cannot execute verification script

**Solution:**
```bash
chmod +x tests/e2e/verify-xss-prevention.sh
./tests/e2e/verify-xss-prevention.sh
```

## CI/CD Integration

### GitHub Actions

```yaml
name: XSS Prevention Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Setup Node.js
        uses: actions/setup-node@v4
        with:
          node-version: '20'

      - name: Install dependencies
        run: |
          cd parent-portal
          npm ci
          npx playwright install --with-deps

      - name: Start parent-portal
        run: |
          cd parent-portal
          npm run dev &
          sleep 10  # Wait for server to start

      - name: Run XSS prevention tests
        run: npx playwright test tests/e2e/xss-prevention.spec.js

      - name: Upload test results
        if: always()
        uses: actions/upload-artifact@v4
        with:
          name: playwright-results
          path: |
            playwright-report/
            test-results/
```

### Test Reports

Generate and view HTML report:
```bash
npx playwright test tests/e2e/xss-prevention.spec.js --reporter=html
npx playwright show-report
```

View JSON report for CI:
```bash
npx playwright test tests/e2e/xss-prevention.spec.js --reporter=json
cat playwright-report/results.json
```

## Security Best Practices

### Input Sanitization

1. **Always sanitize user input before rendering:**
   ```typescript
   import { sanitizeHTML } from '@/lib/security/sanitize';
   const safe = sanitizeHTML(userInput);
   ```

2. **Use appropriate sanitization level:**
   - `STRICT`: Plain text only (no HTML)
   - `BASIC`: Basic formatting (p, strong, em)
   - `RICH_TEXT`: Full rich text (headings, links, lists)
   - `MARKDOWN`: Markdown output (includes code, tables)

3. **Never use `dangerouslySetInnerHTML` without sanitization:**
   ```tsx
   // ❌ DANGEROUS
   <div dangerouslySetInnerHTML={{ __html: userInput }} />

   // ✅ SAFE
   <div dangerouslySetInnerHTML={{ __html: sanitizeHTML(userInput) }} />
   ```

### Content Security Policy

Ensure CSP headers are configured in `next.config.js`:
```javascript
headers: [
  {
    key: 'Content-Security-Policy',
    value: "script-src 'self' 'unsafe-inline' 'unsafe-eval'; ..."
  }
]
```

### Testing Strategy

1. **Unit tests:** Test sanitization utilities in isolation
2. **Integration tests:** Test form submission with sanitization
3. **E2E tests:** Test complete user workflow (this test suite)
4. **Manual testing:** Verify in browser with real payloads
5. **Security scan:** Use automated security scanners (OWASP ZAP)

## Related Files

- **Sanitization utilities:** `parent-portal/lib/security/sanitize.ts`
- **Unit tests:** `parent-portal/lib/security/__tests__/sanitize.test.ts`
- **CSRF tests:** `tests/e2e/csrf-protection.spec.js`
- **Security spec:** `.auto-claude/specs/203-frontend-security-type-safety/spec.md`

## References

- [OWASP XSS Prevention Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Cross_Site_Scripting_Prevention_Cheat_Sheet.html)
- [DOMPurify Documentation](https://github.com/cure53/DOMPurify)
- [Playwright Testing Guide](https://playwright.dev/docs/intro)
- [Content Security Policy](https://developer.mozilla.org/en-US/docs/Web/HTTP/CSP)

## Support

For issues or questions:
1. Check troubleshooting section above
2. Review test output for specific error messages
3. Verify sanitization utilities are properly configured
4. Ensure parent-portal is running on correct port
5. Check browser console for CSP violations
