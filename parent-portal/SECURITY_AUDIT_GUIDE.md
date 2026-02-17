# Security Vulnerability Scan and Audit Guide

**Task:** 203 - Frontend Security and Type Safety Implementation
**Subtask:** 5-4 - Security vulnerability scan and audit
**Date:** 2026-02-18

## Overview

This document provides comprehensive security audit procedures for all frontend services. The audit covers:

1. **NPM Dependency Vulnerabilities** - Zero high/critical vulnerabilities required
2. **ESLint Security Rules** - Zero violations required
3. **XSS Payload Testing** - All common payloads must be sanitized
4. **TypeScript Strict Mode** - All services must compile without errors
5. **Security Headers** - CSP and other security headers must be configured
6. **Secure Storage** - Mobile apps must use secure keychain/keystore
7. **CSRF Protection** - CSRF utilities must be properly implemented

---

## 1. NPM Dependency Vulnerability Scan

### Services to Check
- parent-portal
- parent-app
- teacher-app
- desktop-app

### Verification Commands

```bash
# Parent Portal
cd parent-portal
npm audit
npm audit --json | jq '.metadata.vulnerabilities'

# Parent App
cd ../parent-app
npm audit
npm audit --json | jq '.metadata.vulnerabilities'

# Teacher App
cd ../teacher-app
npm audit
npm audit --json | jq '.metadata.vulnerabilities'

# Desktop App
cd ../desktop-app
npm audit
npm audit --json | jq '.metadata.vulnerabilities'
```

### Success Criteria
- ✅ **Zero critical vulnerabilities** in all services
- ✅ **Zero high vulnerabilities** in all services
- ⚠️  Moderate/Low vulnerabilities are acceptable if they don't affect security features

### How to Fix Vulnerabilities

```bash
# Update vulnerable dependencies
npm audit fix

# For breaking changes, use force
npm audit fix --force

# If automatic fix not available, manually update package.json
npm update <package-name>
```

---

## 2. ESLint Security Rule Violations

### Security Rules Implemented

All services have the following ESLint security rules:

```javascript
"rules": {
  // Code injection prevention
  "no-eval": "error",
  "no-implied-eval": "error",
  "no-new-func": "error",

  // XSS attack prevention
  "no-script-url": "error",
  "react/no-danger": "warn",
  "react/no-danger-with-children": "error",

  // Prototype pollution prevention
  "no-proto": "error",

  // Unsafe API prevention
  "no-caller": "error",
  "no-extend-native": "error",

  // Scope confusion prevention
  "no-with": "error"
}
```

### Verification Commands

```bash
# Parent Portal
cd parent-portal
npm run lint -- --max-warnings 0

# Parent App
cd ../parent-app
npm run lint -- --max-warnings 0

# Teacher App
cd ../teacher-app
npm run lint -- --max-warnings 0

# Desktop App (if ESLint configured)
cd ../desktop-app
npm run lint -- --max-warnings 0
```

### Success Criteria
- ✅ **Zero errors** related to security rules
- ⚠️  Non-security warnings are acceptable but should be addressed

### Common Violations and Fixes

#### 1. `no-eval` Violation
```javascript
// ❌ Bad
eval('console.log("hello")');
new Function('return 1 + 1')();

// ✅ Good - Use safer alternatives
JSON.parse('{"key": "value"}');
```

#### 2. `react/no-danger` Violation
```javascript
// ❌ Bad
<div dangerouslySetInnerHTML={{ __html: userContent }} />

// ✅ Good - Use sanitization
import { sanitizeHTML } from '@/lib/security/sanitize';
<div dangerouslySetInnerHTML={{ __html: sanitizeHTML(userContent) }} />
```

#### 3. `no-script-url` Violation
```javascript
// ❌ Bad
<a href="javascript:void(0)">Click</a>

// ✅ Good
<button onClick={handleClick}>Click</button>
```

---

## 3. XSS Payload Testing

### Common XSS Attack Vectors

The following payloads must be sanitized by our security utilities:

```javascript
// Script tag injection
"<script>alert('xss')</script>"

// Image onerror
"<img src=x onerror=alert('xss')>"

// SVG onload
"<svg onload=alert('xss')>"

// JavaScript protocol
"javascript:alert('xss')"

// Data URI with script
"data:text/html,<script>alert('xss')</script>"

// iFrame injection
"<iframe src='javascript:alert(\"xss\")'>"

// Event handler injection
"<body onload=alert('xss')>"
"<input onfocus=alert('xss') autofocus>"

// DOM clobbering
"<form name='document'><input name='cookie'></form>"

// HTML entity encoding bypass
"&lt;script&gt;alert('xss')&lt;/script&gt;"
```

### Verification Script

Create a file `test-xss-sanitization.js`:

```javascript
const { JSDOM } = require('jsdom');
const DOMPurify = require('isomorphic-dompurify');

const payloads = [
    "<script>alert('xss')</script>",
    "<img src=x onerror=alert('xss')>",
    "<svg onload=alert('xss')>",
    "<iframe src='javascript:alert(\"xss\")'>",
    "javascript:alert('xss')",
    "<body onload=alert('xss')>",
    "<input onfocus=alert('xss') autofocus>",
    "<marquee onstart=alert('xss')>",
    "<details open ontoggle=alert('xss')>",
    "<a href='data:text/html,<script>alert(\"xss\")</script>'>click</a>"
];

console.log("Testing XSS Payload Sanitization\n");

let allPassed = true;
let passCount = 0;
let failCount = 0;

payloads.forEach((payload, index) => {
    // Test with strict sanitization (no tags allowed)
    const sanitized = DOMPurify.sanitize(payload, { ALLOWED_TAGS: [] });

    // Check if dangerous content is removed
    const isClean = !sanitized.includes('script') &&
                    !sanitized.includes('onerror') &&
                    !sanitized.includes('onload') &&
                    !sanitized.includes('javascript:') &&
                    !sanitized.includes('onfocus') &&
                    !sanitized.includes('onstart') &&
                    !sanitized.includes('ontoggle');

    if (!isClean) {
        console.log(`❌ FAIL: Payload ${index + 1} not properly sanitized`);
        console.log(`   Input:  ${payload}`);
        console.log(`   Output: ${sanitized}`);
        failCount++;
        allPassed = false;
    } else {
        console.log(`✅ PASS: Payload ${index + 1} sanitized`);
        passCount++;
    }
});

console.log(`\nResults: ${passCount} passed, ${failCount} failed`);
process.exit(allPassed ? 0 : 1);
```

Run the test:

```bash
cd parent-portal
node ../test-xss-sanitization.js
```

### Manual Browser Testing

1. Start parent-portal: `cd parent-portal && npm run dev`
2. Open browser to http://localhost:3000
3. Find a form that accepts HTML content (e.g., bio field in profile)
4. Test each payload from the list above
5. Verify:
   - ✅ Script tags are removed or escaped
   - ✅ Event handlers are removed
   - ✅ JavaScript: URLs are blocked
   - ✅ No alert boxes appear
   - ✅ No console errors from script execution

### Success Criteria
- ✅ **All XSS payloads** are sanitized or blocked
- ✅ **No script execution** occurs when payloads are rendered
- ✅ **Safe HTML tags** (like `<p>`, `<strong>`, `<em>`) are preserved when appropriate

---

## 4. TypeScript Strict Mode Verification

### Configuration Requirements

Each service must have TypeScript strict mode enabled in `tsconfig.json`:

```json
{
  "compilerOptions": {
    "strict": true,
    "noImplicitAny": true,
    "strictNullChecks": true,
    "strictFunctionTypes": true,
    "strictBindCallApply": true,
    "strictPropertyInitialization": true,
    "noImplicitThis": true,
    "alwaysStrict": true,
    "noUncheckedIndexedAccess": true
  }
}
```

### Verification Commands

```bash
# Parent Portal
cd parent-portal
npx tsc --noEmit
echo "Exit code: $?"

# Parent App
cd ../parent-app
npx tsc --noEmit
echo "Exit code: $?"

# Teacher App
cd ../teacher-app
npx tsc --noEmit
echo "Exit code: $?"

# Desktop App
cd ../desktop-app
npx tsc --noEmit
echo "Exit code: $?"
```

### Success Criteria
- ✅ **Exit code 0** (no errors) for all services
- ✅ **All strict mode options enabled** in tsconfig.json
- ⚠️  Warnings are acceptable, but errors must be fixed

### Common TypeScript Errors and Fixes

#### 1. Implicit Any
```typescript
// ❌ Bad
function process(data) {
  return data.value;
}

// ✅ Good
function process(data: { value: string }): string {
  return data.value;
}
```

#### 2. Null/Undefined Handling
```typescript
// ❌ Bad
const user = getUser();
console.log(user.name); // Error: Object is possibly 'null'

// ✅ Good
const user = getUser();
if (user) {
  console.log(user.name);
}
```

#### 3. Unchecked Index Access
```typescript
// ❌ Bad
const items = ['a', 'b', 'c'];
const item = items[5]; // Type: string (but could be undefined!)

// ✅ Good
const items = ['a', 'b', 'c'];
const item = items[5]; // Type: string | undefined
if (item) {
  console.log(item.toUpperCase());
}
```

---

## 5. Security Headers Configuration

### Parent Portal (next.config.js)

Verify Content Security Policy headers are configured:

```bash
cd parent-portal
grep -A 20 "Content-Security-Policy" next.config.js
```

**Expected configuration:**

```javascript
async headers() {
  return [
    {
      source: '/:path*',
      headers: [
        {
          key: 'Content-Security-Policy',
          value: "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self' data:; connect-src 'self' http://localhost:* ws://localhost:*; frame-ancestors 'none'; base-uri 'self'; form-action 'self';"
        },
        {
          key: 'X-Frame-Options',
          value: 'DENY'
        },
        {
          key: 'X-Content-Type-Options',
          value: 'nosniff'
        }
      ]
    }
  ];
}
```

### Desktop App (src/main.ts)

Verify CSP headers in BrowserWindow configuration:

```bash
cd desktop-app
grep -A 10 "Content-Security-Policy" src/main.ts
```

**Expected configuration:**

```typescript
webPreferences: {
  preload: path.join(__dirname, 'preload.js'),
  contextIsolation: true,
  nodeIntegration: false,
  // CSP configured in session
}

// In ready event:
session.defaultSession.webRequest.onHeadersReceived((details, callback) => {
  callback({
    responseHeaders: {
      ...details.responseHeaders,
      'Content-Security-Policy': ["default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:;"]
    }
  });
});
```

### Browser Verification

1. Start parent-portal: `cd parent-portal && npm run dev`
2. Open browser DevTools (F12)
3. Navigate to Network tab
4. Reload page
5. Click on the main document request
6. Check Response Headers:
   - ✅ `Content-Security-Policy` header present
   - ✅ `X-Frame-Options: DENY` present
   - ✅ `X-Content-Type-Options: nosniff` present
7. Check Console tab:
   - ✅ No CSP violation warnings

### Success Criteria
- ✅ **CSP headers configured** in all web services
- ✅ **No unsafe-inline or unsafe-eval** in production CSP (development exceptions allowed)
- ✅ **X-Frame-Options and X-Content-Type-Options** configured
- ✅ **No CSP violations** in browser console

---

## 6. Secure Storage Implementation

### Parent App

Verify secure storage implementation:

```bash
cd parent-app
cat src/utils/secureStorage.ts | head -n 50
grep "react-native-keychain" package.json
```

**Expected:**
- ✅ File exists: `src/utils/secureStorage.ts`
- ✅ Uses `react-native-keychain` library
- ✅ Implements `setItem`, `getItem`, `removeItem`, `clear` functions
- ✅ Supports biometric authentication options

### Teacher App

Verify secure storage implementation:

```bash
cd teacher-app
cat src/utils/secureStorage.ts | head -n 50
grep "react-native-keychain" package.json
```

**Expected:**
- ✅ File exists: `src/utils/secureStorage.ts`
- ✅ Uses `react-native-keychain` library
- ✅ Implements storage API matching parent-app pattern

### Device Verification (iOS)

1. Build and run app on iOS device/simulator
2. Login to app (stores token)
3. Connect Xcode → Window → Devices and Simulators
4. Select device → Show installed apps → Your app → Download Container
5. Extract and check `Library/Keychains/` - tokens should be there
6. Check UserDefaults - **tokens should NOT be there**

### Device Verification (Android)

1. Build and run app on Android device/emulator
2. Login to app (stores token)
3. Connect adb: `adb shell`
4. Run: `run-as <package-name>`
5. Check shared_prefs - **tokens should NOT be there**
6. Android Keystore is system-level, not directly inspectable (good!)

### Success Criteria
- ✅ **Secure storage utilities implemented** in both mobile apps
- ✅ **react-native-keychain dependency** installed
- ✅ **Tokens stored in Keychain (iOS) or Keystore (Android)**
- ❌ **Tokens NOT in AsyncStorage/UserDefaults/SharedPreferences**

---

## 7. CSRF Protection Implementation

### Parent Portal

Verify CSRF utilities:

```bash
cd parent-portal
cat lib/security/csrf.ts | head -n 50
```

**Expected functions:**
- ✅ `fetchCSRFToken()` - Fetches CSRF token from backend
- ✅ `getCSRFToken()` - Retrieves cached token
- ✅ `validateCSRFToken()` - Validates token expiration
- ✅ `clearCSRFToken()` - Clears cached token
- ✅ `refreshCSRFToken()` - Refreshes expired token
- ✅ `fetchWithCSRF()` - Wrapper that auto-includes CSRF token

### API Client Integration

Verify CSRF integration in API client:

```bash
cd parent-portal
grep -n "csrf" lib/api/client.ts
```

**Expected:**
- ✅ CSRF token automatically included in POST/PUT/PATCH/DELETE requests
- ✅ CSRF token in `X-CSRF-Token` header
- ✅ CSRF token exemption for public endpoints

### E2E Testing

Run CSRF protection tests:

```bash
cd parent-portal
npx playwright test tests/e2e/csrf-protection.spec.js
```

**Expected test results:**
- ✅ Can fetch CSRF token from backend
- ✅ POST with CSRF token succeeds
- ✅ POST without CSRF token fails with 403
- ✅ Invalid CSRF token fails with 403
- ✅ Expired CSRF token auto-refreshes

### Success Criteria
- ✅ **CSRF utilities implemented** in parent-portal
- ✅ **CSRF tokens automatically included** in state-changing requests
- ✅ **Backend validates CSRF tokens** and rejects invalid ones
- ✅ **All E2E tests pass**

---

## 8. Summary Checklist

Use this checklist to verify all security requirements:

### NPM Audit
- [ ] parent-portal: Zero high/critical vulnerabilities
- [ ] parent-app: Zero high/critical vulnerabilities
- [ ] teacher-app: Zero high/critical vulnerabilities
- [ ] desktop-app: Zero high/critical vulnerabilities

### ESLint Security Rules
- [ ] parent-portal: Zero security rule violations
- [ ] parent-app: Zero security rule violations
- [ ] teacher-app: Zero security rule violations
- [ ] desktop-app: Zero security rule violations (if applicable)

### XSS Protection
- [ ] All 10+ common XSS payloads sanitized
- [ ] Browser testing shows no script execution
- [ ] Sanitization utilities tested and passing
- [ ] E2E XSS prevention tests passing

### TypeScript Strict Mode
- [ ] parent-portal: Compiles with zero errors
- [ ] parent-app: Compiles with zero errors
- [ ] teacher-app: Compiles with zero errors
- [ ] desktop-app: Compiles with zero errors

### Security Headers
- [ ] parent-portal: CSP configured in next.config.js
- [ ] parent-portal: X-Frame-Options configured
- [ ] desktop-app: CSP configured in main.ts
- [ ] No CSP violations in browser console

### Secure Storage
- [ ] parent-app: secureStorage.ts implemented
- [ ] parent-app: react-native-keychain installed
- [ ] teacher-app: secureStorage.ts implemented
- [ ] teacher-app: react-native-keychain installed
- [ ] Device testing confirms keychain/keystore usage

### CSRF Protection
- [ ] parent-portal: csrf.ts utilities implemented
- [ ] API client auto-includes CSRF tokens
- [ ] Backend validates CSRF tokens
- [ ] E2E CSRF tests passing

---

## 9. Automated Audit Script

Run the comprehensive audit from the repository root:

```bash
./run-security-audit.sh
```

This script will:
1. Run npm audit on all services
2. Run ESLint on all services
3. Test XSS payload sanitization
4. Verify TypeScript compilation
5. Check security headers configuration
6. Verify secure storage implementation
7. Verify CSRF implementation
8. Generate a detailed report: `security-audit-report.txt`

---

## 10. Remediation

If any checks fail, follow these steps:

### High/Critical NPM Vulnerabilities
1. Run `npm audit fix` in the affected service
2. If automatic fix fails, manually update vulnerable packages
3. Re-run `npm audit` to verify
4. Test the service to ensure no breaking changes

### ESLint Violations
1. Review the specific rule violation
2. Refactor code to use safe alternatives (see section 2)
3. If a violation is unavoidable, add inline comment with justification
4. Re-run `npm run lint` to verify

### XSS Vulnerabilities
1. Identify where unsanitized content is rendered
2. Import sanitization utilities: `import { sanitizeHTML } from '@/lib/security/sanitize'`
3. Wrap all user content: `sanitizeHTML(userContent)`
4. Re-test with XSS payloads

### TypeScript Errors
1. Review error messages from `npx tsc --noEmit`
2. Add proper type annotations
3. Handle null/undefined cases
4. Re-run TypeScript compiler

### Missing Security Headers
1. Update next.config.js or main.ts with CSP configuration
2. Test in browser DevTools
3. Fix any CSP violations

### Insecure Storage
1. Replace AsyncStorage with react-native-keychain
2. Update all token storage/retrieval code
3. Test on device to verify keychain usage

### Missing CSRF Protection
1. Ensure csrf.ts utilities are implemented
2. Update API client to include CSRF tokens
3. Test with E2E tests

---

## Completion Criteria

This subtask is complete when:

✅ **All services have zero high/critical npm vulnerabilities**
✅ **All services have zero ESLint security violations**
✅ **All common XSS payloads are properly sanitized**
✅ **All services compile with TypeScript strict mode**
✅ **Security headers are configured and verified**
✅ **Mobile apps use secure keychain/keystore**
✅ **CSRF protection is implemented and tested**
✅ **Automated audit script runs successfully**
✅ **Security audit report shows all checks passing**

---

**Generated:** 2026-02-18
**Task:** 203-frontend-security-type-safety
**Subtask:** 5-4
