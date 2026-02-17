# CSRF Protection End-to-End Test

## Overview

This test suite verifies the complete CSRF (Cross-Site Request Forgery) protection flow between the frontend and ai-service backend.

## Test Coverage

### Main Workflow Tests
1. **Fetch CSRF Token** - Verifies token can be fetched from `/api/v1/csrf-token`
2. **Accept Valid Token** - Verifies POST requests with valid CSRF token are accepted
3. **Reject Missing Token** - Verifies POST requests without CSRF token are rejected (403)
4. **Reject Invalid Token** - Verifies POST requests with invalid token are rejected (403)
5. **Reject Expired Token** - Verifies POST requests with expired token are rejected (403)
6. **Complete Workflow** - Comprehensive test covering all verification steps

### Edge Case Tests
- Empty CSRF token
- Whitespace-only CSRF token
- Malformed JWT tokens
- Token reusability within expiration time

### Integration Tests
- CSRF token refresh scenario
- Multiple sequential requests with same token

## Prerequisites

### 1. Install Dependencies

```bash
# Install Playwright (if not already installed)
npm install -g playwright
npm install -g @playwright/test

# Or install locally
npm install --save-dev @playwright/test
```

### 2. Start ai-service

The ai-service backend must be running on `http://localhost:8000`.

```bash
# Navigate to ai-service directory
cd ai-service

# Ensure .env file is configured with required variables:
# - JWT_SECRET_KEY
# - JWT_ALGORITHM
# - CSRF_TOKEN_EXPIRE_MINUTES
# - CORS_ORIGINS (must include test origin)

# Activate virtual environment
source .venv/bin/activate  # or .venv\Scripts\activate on Windows

# Install dependencies (if needed)
pip install -r requirements.txt

# Start the service
uvicorn app.main:app --reload --port 8000

# Or use the start script if available
python -m app.main
```

Verify ai-service is running:
```bash
curl http://localhost:8000/api/v1/csrf-token
```

Expected response:
```json
{
  "csrf_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "expires_in_minutes": 60
}
```

### 3. Verify CSRF Middleware is Enabled

Ensure `CSRFProtectionMiddleware` is enabled in `ai-service/app/main.py`:

```python
from app.security.csrf import CSRFProtectionMiddleware

# Add middleware to app
app.add_middleware(CSRFProtectionMiddleware)
```

## Running the Tests

### Run All CSRF Tests

```bash
# From repository root
npx playwright test tests/e2e/csrf-protection.spec.js

# Or with more verbose output
npx playwright test tests/e2e/csrf-protection.spec.js --reporter=list
```

### Run Specific Test

```bash
# Run only the complete workflow test
npx playwright test tests/e2e/csrf-protection.spec.js -g "complete CSRF protection workflow"

# Run only edge case tests
npx playwright test tests/e2e/csrf-protection.spec.js -g "Edge Cases"
```

### Run in Headed Mode (with browser visible)

```bash
npx playwright test tests/e2e/csrf-protection.spec.js --headed
```

### Run with Debug Mode

```bash
npx playwright test tests/e2e/csrf-protection.spec.js --debug
```

## Environment Variables

You can customize the test configuration using environment variables:

```bash
# Use different ai-service URL
AI_SERVICE_URL=http://localhost:9000 npx playwright test tests/e2e/csrf-protection.spec.js
```

## Expected Test Results

All tests should pass when:
- ✅ ai-service is running on the configured URL
- ✅ CSRF protection middleware is enabled
- ✅ CSRF token endpoint returns valid tokens
- ✅ Test endpoint validates CSRF tokens correctly
- ✅ Invalid/missing tokens are rejected with 403 status

Example output:
```
Running 10 tests using 1 worker

  ✓ CSRF Protection Flow › should fetch CSRF token successfully (123ms)
  ✓ CSRF Protection Flow › should accept POST request with valid CSRF token (456ms)
  ✓ CSRF Protection Flow › should reject POST request without CSRF token (78ms)
  ✓ CSRF Protection Flow › should reject POST request with invalid CSRF token (89ms)
  ✓ CSRF Protection Flow › should reject POST request with expired CSRF token (91ms)
  ✓ CSRF Protection Flow › complete CSRF protection workflow (234ms)
  ✓ CSRF Protection - Edge Cases › should reject POST with empty CSRF token (67ms)
  ✓ CSRF Protection - Edge Cases › should reject POST with whitespace-only CSRF token (71ms)
  ✓ CSRF Protection - Edge Cases › should reject POST with malformed JWT CSRF token (345ms)
  ✓ CSRF Protection - Edge Cases › CSRF token should be reusable within expiration time (189ms)

  10 passed (1.8s)
```

## Troubleshooting

### ai-service Connection Refused

**Problem:** Tests fail with "ECONNREFUSED" error

**Solution:**
- Verify ai-service is running: `curl http://localhost:8000/`
- Check the port matches the configured URL
- Ensure no firewall is blocking connections

### All Tests Return 403

**Problem:** Even valid tokens return 403

**Solution:**
- Verify CSRF middleware is enabled in ai-service
- Check JWT_SECRET_KEY is configured in ai-service .env
- Ensure test endpoint `/api/v1/test-csrf` exists

### Tests Timeout

**Problem:** Tests hang or timeout

**Solution:**
- Check ai-service logs for errors
- Verify network connectivity
- Increase timeout in playwright.config.js
- Run with `--timeout 90000` flag

### Token Validation Fails

**Problem:** Valid tokens are rejected as invalid

**Solution:**
- Verify JWT_SECRET_KEY matches between token generation and validation
- Check JWT_ALGORITHM is configured correctly (default: HS256)
- Ensure system clock is synchronized (JWT uses timestamps)

## Integration with CI/CD

To run these tests in a CI/CD pipeline:

1. **Start ai-service** before running tests:
   ```yaml
   - name: Start ai-service
     run: |
       cd ai-service
       python -m venv .venv
       source .venv/bin/activate
       pip install -r requirements.txt
       uvicorn app.main:app --port 8000 &
       sleep 5  # Wait for service to start
   ```

2. **Run tests**:
   ```yaml
   - name: Run CSRF E2E Tests
     run: npx playwright test tests/e2e/csrf-protection.spec.js
   ```

3. **Stop services** after tests:
   ```yaml
   - name: Stop services
     run: pkill -f uvicorn
   ```

## Manual Verification (Alternative)

If you prefer manual testing without Playwright:

### 1. Fetch CSRF Token
```bash
curl -X GET http://localhost:8000/api/v1/csrf-token
```

### 2. Submit POST with Token
```bash
# Replace YOUR_TOKEN with the token from step 1
curl -X POST http://localhost:8000/api/v1/test-csrf \
  -H "Content-Type: application/json" \
  -H "X-CSRF-Token: YOUR_TOKEN" \
  -d '{"test": "data"}'
```

Expected: 200 OK with "CSRF validation passed"

### 3. Submit POST without Token
```bash
curl -X POST http://localhost:8000/api/v1/test-csrf \
  -H "Content-Type: application/json" \
  -d '{"test": "data"}'
```

Expected: 403 Forbidden with "CSRF token missing"

## Security Verification Checklist

After running tests, verify:

- [ ] CSRF tokens are required for all POST/PUT/DELETE/PATCH requests
- [ ] Missing CSRF token results in 403 Forbidden
- [ ] Invalid CSRF token results in 403 Forbidden
- [ ] Expired CSRF token results in 403 Forbidden
- [ ] Valid CSRF token allows request to proceed
- [ ] CSRF tokens can be reused within expiration window
- [ ] Each CSRF token fetch generates a unique token
- [ ] CSRF token validation doesn't leak sensitive information in error messages

## Related Files

- Test implementation: `tests/e2e/csrf-protection.spec.js`
- Backend CSRF implementation: `ai-service/app/security/csrf.py`
- Frontend CSRF utilities: `parent-portal/lib/security/csrf.ts`
- CSRF token endpoint: `ai-service/app/main.py` (`/api/v1/csrf-token`)
- Test endpoint: `ai-service/app/main.py` (`/api/v1/test-csrf`)

## Success Criteria

This test suite fulfills **subtask-5-1** requirements:

✅ Fetch CSRF token via GET /api/v1/csrf-token
✅ Submit POST request with CSRF token in X-CSRF-Token header
✅ Verify backend validates successfully
✅ Submit POST without CSRF token
✅ Verify backend rejects with 403

All verification steps from the implementation plan are covered.
