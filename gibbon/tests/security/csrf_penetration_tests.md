# CSRF Penetration Testing Documentation

## Overview

This document provides comprehensive manual penetration testing procedures for CSRF (Cross-Site Request Forgery) protection implementation in the Gibbon and Parent Portal applications.

**Security Priority:** HIGH (CVSS 6.5)
**Date:** 2026-02-17
**Tester:** Auto-Claude Security Testing

## Test Categories

### 1. No Token Attacks
### 2. Token Reuse Attacks
### 3. Cross-Origin Attacks
### 4. All Operations Protected
### 5. Security Headers Verification
### 6. Token Tampering Attacks

---

## Prerequisites

Before running penetration tests:

1. **Start Services:**
   ```bash
   docker-compose up gibbon parent-portal
   ```

2. **Verify Services Running:**
   - Gibbon: http://localhost:8080
   - Parent Portal: http://localhost:3000

3. **Tools Required:**
   - `curl` (command-line HTTP client)
   - `jq` (JSON processor, optional)
   - Web browser with Developer Tools
   - HTTP interception proxy (optional, e.g., Burp Suite, OWASP ZAP)

4. **Test Environment:**
   - Clean browser session (no cookies)
   - Valid test user accounts
   - Test database with sample data

---

## TEST CATEGORY 1: No Token Attacks

**Objective:** Verify that requests without CSRF tokens are rejected with 403 Forbidden.

### Test 1.1: Finance Invoice Creation Without Token

**Attack Scenario:** Attacker attempts to create invoice without CSRF token.

```bash
curl -X POST http://localhost:8080/modules/EnhancedFinance/finance_invoice_addProcess.php \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "amount=1000&description=Unauthorized&dueDate=2026-12-31" \
  -v
```

**Expected Result:**
- HTTP 403 Forbidden
- Error message: "CSRF validation failed"
- Request logged in security audit log
- No invoice created in database

**Pass Criteria:** ✓ HTTP 403 or 400 returned, no data modified

---

### Test 1.2: Care Tracking Attendance Without Token

**Attack Scenario:** Attacker attempts to check in child without CSRF token.

```bash
curl -X POST http://localhost:8080/modules/CareTracking/careTracking_attendance.php \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "action=checkin&childID=1&timestamp=2026-02-17T09:00:00" \
  -v
```

**Expected Result:**
- HTTP 403 Forbidden
- No attendance record created

**Pass Criteria:** ✓ Request rejected with 403

---

### Test 1.3: Development Profile Without Token

**Attack Scenario:** Attacker attempts to add observation without CSRF token.

```bash
curl -X POST http://localhost:8080/modules/DevelopmentProfile/developmentProfile_add.php \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "observation=Fake observation&childID=1&category=motor" \
  -v
```

**Expected Result:**
- HTTP 403 Forbidden
- No observation created

**Pass Criteria:** ✓ Request rejected with 403

---

### Test 1.4: Staff Management Without Token

**Attack Scenario:** Attacker attempts to create staff account without CSRF token.

```bash
curl -X POST http://localhost:8080/modules/StaffManagement/staffManagement_addEdit.php \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "firstName=Attacker&lastName=User&email=attacker@evil.com&role=admin" \
  -v
```

**Expected Result:**
- HTTP 403 Forbidden
- No staff account created

**Pass Criteria:** ✓ Request rejected with 403

---

### Test 1.5: Parent Portal Registration Without Token

**Attack Scenario:** Attacker attempts to register without CSRF token.

```bash
curl -X POST http://localhost:3000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{"firstName":"Test","lastName":"User","email":"test@example.com","password":"password123"}' \
  -v
```

**Expected Result:**
- HTTP 403 Forbidden
- JSON response: `{"error": "CSRF validation failed"}`
- No user account created

**Pass Criteria:** ✓ Request rejected with 403

---

## TEST CATEGORY 2: Token Reuse Attacks

**Objective:** Verify that CSRF tokens cannot be reused inappropriately.

### Test 2.1: Extract Valid CSRF Token

**Procedure:**
1. Open browser to http://localhost:8080/modules/EnhancedFinance/finance_invoice_add.php
2. Open Developer Tools → Network tab
3. View page source and locate CSRF token:
   ```html
   <input type="hidden" name="csrf_token" value="abc123...xyz789">
   ```
4. Copy the token value

**Alternative (curl):**
```bash
curl -c /tmp/cookies.txt http://localhost:8080/modules/EnhancedFinance/finance_invoice_add.php \
  | grep -o 'name="csrf_token" value="[^"]*"' \
  | sed 's/.*value="\([^"]*\)".*/\1/'
```

**Expected Result:**
- Token is 64 characters (32 bytes hex-encoded)
- Token is random and unique per session

---

### Test 2.2: First Request with Valid Token

**Procedure:**
```bash
# Use token from Test 2.1
CSRF_TOKEN="<your_token_here>"

curl -X POST http://localhost:8080/modules/EnhancedFinance/finance_invoice_addProcess.php \
  -b /tmp/cookies.txt \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "csrf_token=$CSRF_TOKEN&amount=100&description=Test1" \
  -v
```

**Expected Result:**
- HTTP 200 or 302 (success/redirect)
- Invoice created successfully
- Token still valid in session (if rotateOnUse=false)

---

### Test 2.3: Second Request with Same Token

**Attack Scenario:** Reuse the same token for another request.

```bash
# Reuse the SAME token from Test 2.2
curl -X POST http://localhost:8080/modules/EnhancedFinance/finance_invoice_addProcess.php \
  -b /tmp/cookies.txt \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "csrf_token=$CSRF_TOKEN&amount=200&description=Test2" \
  -v
```

**Expected Result (depends on configuration):**

**If rotateOnUse=true:**
- HTTP 403 Forbidden
- Token has been invalidated after first use
- ✓ PASS: Token reuse prevented

**If rotateOnUse=false (default for multi-tab support):**
- HTTP 200 or 302
- Token remains valid
- ✓ ACCEPTABLE: Token reuse allowed for usability
- Note: Token expires after 2 hours or session ends

**Pass Criteria:** Behavior matches configuration

---

### Test 2.4: Token Reuse Across Sessions

**Attack Scenario:** Attempt to use a token from one session in another session.

**Procedure:**
1. Get token in Session A (browser 1)
2. Copy token
3. Open Session B (incognito/different browser)
4. Submit form with Session A's token

```bash
# Session A
curl -c /tmp/session_a.txt http://localhost:8080/modules/EnhancedFinance/finance_invoice_add.php \
  | grep -o 'csrf_token" value="[^"]*"' | sed 's/.*value="\([^"]*\)".*/\1/'

# Session B (different cookie file)
curl -X POST http://localhost:8080/modules/EnhancedFinance/finance_invoice_addProcess.php \
  -b /tmp/session_b.txt \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "csrf_token=<session_a_token>&amount=100" \
  -v
```

**Expected Result:**
- HTTP 403 Forbidden
- Token validation fails (token not in Session B)
- ✓ PASS: Cross-session token reuse prevented

---

## TEST CATEGORY 3: Cross-Origin Attacks

**Objective:** Verify that requests from different origins are blocked by SameSite cookies.

### Test 3.1: Request with Foreign Origin Header

**Attack Scenario:** Attacker hosts malicious page on evil.com that submits form.

```bash
CSRF_TOKEN="<valid_token>"

curl -X POST http://localhost:8080/modules/EnhancedFinance/finance_invoice_addProcess.php \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -H "Origin: https://evil.com" \
  -H "Referer: https://evil.com/attack.html" \
  -b /tmp/cookies.txt \
  -d "csrf_token=$CSRF_TOKEN&amount=1000" \
  -v
```

**Expected Result:**
- SameSite=Strict cookie prevents cookie from being sent
- HTTP 403 Forbidden (no session cookie)
- ✓ PASS: Cross-origin attack prevented

---

### Test 3.2: Embedded Attack via Iframe

**Attack Scenario:** Attacker embeds Gibbon form in iframe on evil.com.

**Create test HTML file:**
```html
<!-- evil.html -->
<!DOCTYPE html>
<html>
<head><title>Evil Site</title></head>
<body>
  <h1>Win a Prize!</h1>
  <iframe src="http://localhost:8080/modules/EnhancedFinance/finance_invoice_add.php"
          style="display:none"></iframe>
  <script>
    // Attempt to submit form in iframe
    setTimeout(() => {
      const iframe = document.querySelector('iframe');
      iframe.contentWindow.document.forms[0].submit();
    }, 1000);
  </script>
</body>
</html>
```

**Procedure:**
1. Open http://localhost:8080 and log in (establish session)
2. Open evil.html in same browser
3. Check if form submission succeeds

**Expected Result:**
- X-Frame-Options: DENY prevents iframe embedding
- Browser blocks iframe load with error: "Refused to display in a frame"
- ✓ PASS: Iframe attack prevented

---

### Test 3.3: CORS Preflight Attack

**Attack Scenario:** JavaScript from evil.com attempts to make API request.

```bash
curl -X OPTIONS http://localhost:3000/api/auth/register \
  -H "Origin: https://evil.com" \
  -H "Access-Control-Request-Method: POST" \
  -H "Access-Control-Request-Headers: Content-Type" \
  -v
```

**Expected Result:**
- No Access-Control-Allow-Origin header (or not matching evil.com)
- Browser blocks actual POST request
- ✓ PASS: CORS prevents cross-origin API calls

---

## TEST CATEGORY 4: All Operations Protected

**Objective:** Verify comprehensive CSRF protection across all state-changing operations.

### Test 4.1: Verify All POST Endpoints Protected

**Test all implemented endpoints:**

```bash
# Test array of endpoints
declare -a endpoints=(
  "/modules/EnhancedFinance/finance_invoice_addProcess.php"
  "/modules/EnhancedFinance/finance_payment_addProcess.php"
  "/modules/CareTracking/careTracking_attendance.php"
  "/modules/DevelopmentProfile/developmentProfile_add.php"
  "/modules/StaffManagement/staffManagement_addEdit.php"
  "/modules/PhotoManagement/photos_upload.php"
  "/modules/PhotoManagement/photos_deleteProcess.php"
  "/modules/InterventionPlans/interventionPlans_addProcess.php"
)

for endpoint in "${endpoints[@]}"; do
  echo "Testing: $endpoint"

  RESPONSE=$(curl -s -o /dev/null -w "%{http_code}" \
    -X POST "http://localhost:8080$endpoint" \
    -H "Content-Type: application/x-www-form-urlencoded" \
    -d "test=data")

  if [ "$RESPONSE" = "403" ] || [ "$RESPONSE" = "400" ]; then
    echo "  ✓ PASS: Requires CSRF token (HTTP $RESPONSE)"
  else
    echo "  ✗ FAIL: Missing CSRF protection (HTTP $RESPONSE)"
  fi
done
```

**Expected Result:**
- All POST endpoints return 403 without CSRF token
- ✓ PASS: Complete coverage

---

### Test 4.2: Verify PUT/DELETE/PATCH Protection

**Test middleware protects all state-changing methods:**

```bash
# PUT request
curl -X PUT http://localhost:8080/api/test \
  -H "Content-Type: application/json" \
  -d '{"test":"data"}' \
  -v

# DELETE request
curl -X DELETE http://localhost:8080/api/test \
  -v

# PATCH request
curl -X PATCH http://localhost:8080/api/test \
  -H "Content-Type: application/json" \
  -d '{"test":"data"}' \
  -v
```

**Expected Result:**
- HTTP 403 Forbidden (or 404 if endpoint doesn't exist)
- Middleware intercepts all non-GET requests
- ✓ PASS: All HTTP methods protected

---

### Test 4.3: Verify GET Requests Not Protected

**Test that safe methods don't require CSRF tokens:**

```bash
curl -X GET http://localhost:8080/modules/EnhancedFinance/finance_invoice_add.php -v
curl -X HEAD http://localhost:8080/modules/EnhancedFinance/finance_invoice_add.php -v
curl -X OPTIONS http://localhost:8080/modules/EnhancedFinance/finance_invoice_add.php -v
```

**Expected Result:**
- HTTP 200 OK (or appropriate response)
- No CSRF validation for GET/HEAD/OPTIONS
- ✓ PASS: Safe methods not blocked

---

### Test 4.4: Verify Exempt Paths

**Test that webhook/public API paths are exempted:**

```bash
curl -X POST http://localhost:8080/api/webhook/stripe \
  -H "Content-Type: application/json" \
  -d '{"event":"test"}' \
  -v

curl -X POST http://localhost:8080/api/public/healthcheck \
  -v
```

**Expected Result:**
- HTTP 404 (not found) NOT 403 (forbidden)
- Exempt paths bypass CSRF validation
- ✓ PASS: Exemptions configured correctly

---

## TEST CATEGORY 5: Security Headers Verification

**Objective:** Verify all security headers are properly configured.

### Test 5.1: X-Frame-Options Header

```bash
curl -I http://localhost:8080 | grep -i "X-Frame-Options"
```

**Expected:** `X-Frame-Options: DENY`

---

### Test 5.2: X-Content-Type-Options Header

```bash
curl -I http://localhost:8080 | grep -i "X-Content-Type-Options"
```

**Expected:** `X-Content-Type-Options: nosniff`

---

### Test 5.3: X-XSS-Protection Header

```bash
curl -I http://localhost:8080 | grep -i "X-XSS-Protection"
```

**Expected:** `X-XSS-Protection: 1; mode=block`

---

### Test 5.4: Referrer-Policy Header

```bash
curl -I http://localhost:8080 | grep -i "Referrer-Policy"
```

**Expected:** `Referrer-Policy: strict-origin-when-cross-origin`

---

### Test 5.5: SameSite Cookie Attribute

**Browser Test:**
1. Open http://localhost:8080
2. DevTools → Application → Cookies
3. Check session cookie attributes

**Expected:**
- SameSite: Strict (or Lax for parent-portal)
- Secure: Yes (in production)
- HttpOnly: Yes

**curl Test:**
```bash
curl -I -c - http://localhost:8080 | grep -i "Set-Cookie"
```

**Expected:** `Set-Cookie: GIBBON_SESSION=...; SameSite=Strict; HttpOnly; Secure`

---

## TEST CATEGORY 6: Token Tampering Attacks

**Objective:** Verify that tampered/invalid tokens are rejected.

### Test 6.1: Modified Token Attack

**Attack Scenario:** Attacker modifies a few characters of valid token.

```bash
VALID_TOKEN="abc123def456..."  # 64 chars
TAMPERED_TOKEN="abc123def456fffffffffffff..."  # Modified middle section

curl -X POST http://localhost:8080/modules/EnhancedFinance/finance_invoice_addProcess.php \
  -b /tmp/cookies.txt \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "csrf_token=$TAMPERED_TOKEN&amount=1000" \
  -v
```

**Expected Result:**
- HTTP 403 Forbidden
- hash_equals() comparison fails
- ✓ PASS: Tampered token rejected

---

### Test 6.2: Truncated Token Attack

```bash
VALID_TOKEN="abc123def456..."  # 64 chars
TRUNCATED_TOKEN="abc123def456"  # Only 12 chars

curl -X POST http://localhost:8080/modules/EnhancedFinance/finance_invoice_addProcess.php \
  -b /tmp/cookies.txt \
  -d "csrf_token=$TRUNCATED_TOKEN&amount=1000" \
  -v
```

**Expected Result:**
- HTTP 403 Forbidden
- Token length validation fails
- ✓ PASS: Short token rejected

---

### Test 6.3: SQL Injection in Token

**Attack Scenario:** Attacker attempts SQL injection via token field.

```bash
curl -X POST http://localhost:8080/modules/EnhancedFinance/finance_invoice_addProcess.php \
  -b /tmp/cookies.txt \
  -d "csrf_token=' OR '1'='1&amount=1000" \
  -v
```

**Expected Result:**
- HTTP 403 Forbidden
- Token validation fails before any database queries
- ✓ PASS: SQL injection prevented

---

### Test 6.4: XSS in Token

**Attack Scenario:** Attacker attempts XSS via token field.

```bash
curl -X POST http://localhost:8080/modules/EnhancedFinance/finance_invoice_addProcess.php \
  -b /tmp/cookies.txt \
  -d "csrf_token=<script>alert('xss')</script>&amount=1000" \
  -v
```

**Expected Result:**
- HTTP 403 Forbidden
- Token validation fails
- If error message is displayed, it must be HTML-escaped
- ✓ PASS: XSS prevented

---

## Browser-Based Testing

### Manual Test Procedure

**Setup:**
1. Open two browsers (e.g., Chrome and Firefox) or use incognito mode
2. Log into Gibbon in Browser A: http://localhost:8080
3. Open Browser B (not logged in)

**Test Scenario 1: Legitimate User**
1. Browser A: Navigate to Finance → Create Invoice
2. Verify CSRF token in hidden field (View Source)
3. Fill out form and submit
4. Verify success (invoice created)
5. ✓ PASS: Valid token accepted

**Test Scenario 2: Stolen Session Attack**
1. Browser A: Copy session cookie value (DevTools → Application → Cookies)
2. Browser B: Manually set same session cookie value
3. Browser B: Navigate to Finance → Create Invoice
4. Browser B: Submit form
5. ✓ PASS if: Form submission succeeds (session shared)
6. Note: This is expected behavior - session cookies are meant to be shared

**Test Scenario 3: CSRF Attack Simulation**
1. Create attack.html on different domain/port:
```html
<form id="csrf-attack" action="http://localhost:8080/modules/EnhancedFinance/finance_invoice_addProcess.php" method="POST">
  <input type="hidden" name="amount" value="9999">
  <input type="hidden" name="description" value="Hacked">
</form>
<script>document.getElementById('csrf-attack').submit();</script>
```
2. While logged into Gibbon (Browser A), open attack.html
3. ✓ PASS if: Request fails due to missing CSRF token or SameSite cookie blocking

---

## Automated Test Execution

**Run the automated penetration testing script:**

```bash
cd gibbon/tests/security
chmod +x csrf_penetration_tests.sh
./csrf_penetration_tests.sh http://localhost:8080 http://localhost:3000
```

**Review results:**
```bash
cat csrf_test_results_*.log
```

**Expected Output:**
```
================================================================
PENETRATION TESTING RESULTS
================================================================
Total Tests: 30+
Passed: 30+
Failed: 0
================================================================
✓ ALL TESTS PASSED
CSRF protection is properly implemented and secure.
================================================================
```

---

## Acceptance Criteria

For manual penetration testing to be considered complete and successful:

### ✅ Test Category 1: No Token Attacks
- [ ] All POST/PUT/DELETE requests without CSRF tokens are rejected (HTTP 403)
- [ ] Finance invoice creation blocked without token
- [ ] Care tracking blocked without token
- [ ] Profile updates blocked without token
- [ ] Staff management blocked without token
- [ ] Parent portal registration blocked without token

### ✅ Test Category 2: Token Reuse Attacks
- [ ] Token validation uses timing-safe comparison (hash_equals)
- [ ] Tokens expire after 2 hours (configurable)
- [ ] Token rotation works if enabled (rotateOnUse=true)
- [ ] Cross-session token reuse is prevented

### ✅ Test Category 3: Cross-Origin Attacks
- [ ] SameSite=Strict cookies prevent cross-origin form submissions
- [ ] X-Frame-Options: DENY prevents iframe embedding
- [ ] CORS properly configured (no wildcard origins)
- [ ] Requests with foreign Origin headers are blocked

### ✅ Test Category 4: All Operations Protected
- [ ] All state-changing endpoints (POST/PUT/DELETE/PATCH) require CSRF tokens
- [ ] GET/HEAD/OPTIONS requests do not require CSRF tokens
- [ ] Webhook and public API paths are properly exempted
- [ ] Middleware covers all modules and routes

### ✅ Test Category 5: Security Headers
- [ ] X-Frame-Options: DENY header present
- [ ] X-Content-Type-Options: nosniff header present
- [ ] X-XSS-Protection: 1; mode=block header present
- [ ] Referrer-Policy header configured
- [ ] SameSite cookie attribute set (Strict for Gibbon, Lax for Parent Portal)
- [ ] Secure and HttpOnly flags on session cookies

### ✅ Test Category 6: Token Tampering
- [ ] Modified tokens are rejected
- [ ] Truncated tokens are rejected
- [ ] Empty string tokens are rejected
- [ ] SQL injection attempts via token field are blocked
- [ ] XSS attempts via token field are blocked
- [ ] No sensitive data leaked in error messages

---

## Security Audit Log Review

After running penetration tests, review security logs for proper audit trail:

```bash
# Check Gibbon security logs
tail -100 gibbon/logs/security.log

# Look for CSRF validation failures
grep "CSRF validation failed" gibbon/logs/security.log

# Verify IP addresses are logged
grep "IP:" gibbon/logs/security.log
```

**Expected Log Entries:**
```
[2026-02-17 10:30:15] CSRF validation failed - IP: 127.0.0.1, User Agent: curl/7.x, Session: null
[2026-02-17 10:30:16] CSRF validation failed - IP: 127.0.0.1, User Agent: curl/7.x, Session: null
```

---

## Test Results Summary

**Test Date:** _____________
**Tester:** _____________
**Environment:** _____________

| Category | Tests | Passed | Failed | Notes |
|----------|-------|--------|--------|-------|
| 1. No Token Attacks | 5 | | | |
| 2. Token Reuse | 4 | | | |
| 3. Cross-Origin | 3 | | | |
| 4. All Ops Protected | 4 | | | |
| 5. Security Headers | 5 | | | |
| 6. Token Tampering | 4 | | | |
| **TOTAL** | **25+** | | | |

**Overall Status:** ☐ PASS ☐ FAIL

**Sign-off:**

- [ ] All critical tests passed
- [ ] All security headers present
- [ ] All state-changing operations protected
- [ ] CSRF protection properly implemented
- [ ] Ready for production deployment

**Tester Signature:** _____________
**Date:** _____________

---

## Remediation Guidance

If any tests fail, refer to the following remediation steps:

### Failed Token Validation
- Check CsrfTokenManager is properly initialized
- Verify hash_equals() is used for comparison
- Check token is stored in session correctly

### Missing CSRF Protection on Endpoint
- Add CSRF validation in process file
- Add token hidden field to form
- Follow pattern from PhotoManagement module

### Security Headers Missing
- Check gibbon/index.php header initialization
- Verify headers are set before any output
- Check for header() function calls

### SameSite Cookie Issues
- Verify gibbon/config.php $sessionCookieParams
- Check SessionManager initialization
- Test in production (HTTPS) environment

---

## References

- **Implementation Files:**
  - gibbon/src/Security/CsrfTokenManager.php
  - gibbon/middleware/CsrfValidationMiddleware.php
  - gibbon/index.php
  - parent-portal/lib/csrf.ts
  - parent-portal/middleware.ts

- **Test Files:**
  - gibbon/tests/unit/Security/CsrfTokenManagerTest.php
  - gibbon/tests/integration/CsrfProtectionTest.php
  - parent-portal/__tests__/csrf.test.ts

- **Documentation:**
  - OWASP CSRF Prevention Cheat Sheet
  - CWE-352: Cross-Site Request Forgery
  - CVSS Calculator for CSRF (Score: 6.5)

---

**End of Manual Penetration Testing Documentation**
