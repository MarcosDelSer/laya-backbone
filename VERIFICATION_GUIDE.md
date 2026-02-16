# Session Validation Middleware - Verification Guide

## Manual Verification Steps

### 1. Syntax Verification

Run PHP syntax check on the middleware:
```bash
cd gibbon
php -l modules/System/SessionValidationMiddleware.php
```

Expected output: `No syntax errors detected`

### 2. Run Unit Tests

Execute the PHPUnit test suite:
```bash
cd gibbon
vendor/bin/phpunit tests/unit/Modules/System/SessionValidationMiddlewareTest.php --verbose
```

Expected output:
- All tests should pass
- Coverage should be >80%
- No errors or warnings

### 3. Test Coverage Report

Generate detailed coverage report:
```bash
cd gibbon
vendor/bin/phpunit tests/unit/Modules/System/SessionValidationMiddlewareTest.php --coverage-html coverage/
```

View report: Open `gibbon/coverage/index.html` in a browser

### 4. Integration Test with auth_token.php

Test the middleware integration with the token endpoint:

```bash
# Start the Gibbon container
docker-compose up -d php-fpm nginx mysql

# Test the endpoint (requires valid session cookie)
curl -X POST http://localhost:8080/modules/System/auth_token.php \
  -H "Content-Type: application/json" \
  -H "Cookie: <session_cookie>" \
  -v
```

### 5. Example Integration Test

Test the example integration file:
```bash
cd gibbon
php -l modules/System/examples/auth_token_with_middleware.php
```

### 6. Functional Testing Checklist

- [ ] Valid session returns token successfully
- [ ] Invalid session returns 401 error
- [ ] Expired session returns appropriate error
- [ ] Missing session returns 401 error
- [ ] Invalid user status returns 403 error
- [ ] Session activity time is updated on validation
- [ ] Session regeneration works correctly
- [ ] Logging captures validation events

### 7. Security Verification

- [ ] Session timeout is enforced
- [ ] Invalid statuses are rejected
- [ ] Session data is properly sanitized
- [ ] Error messages don't leak sensitive information
- [ ] Logging doesn't expose passwords or tokens

## Test Scenarios

### Scenario 1: Valid Session
```php
<?php
// Setup: Valid Gibbon session
session_start();
$_SESSION['gibbonPersonID'] = '000001';
$_SESSION['username'] = 'testuser';
$_SESSION['status'] = 'Full';
$_SESSION['lastActivityTime'] = time();

// Test
$validator = new SessionValidationMiddleware(['enableLogging' => false]);
$result = $validator->validate();

// Expected: $result['valid'] === true
```

### Scenario 2: Expired Session
```php
<?php
// Setup: Session with old activity time
session_start();
$_SESSION['gibbonPersonID'] = '000001';
$_SESSION['lastActivityTime'] = time() - 7200; // 2 hours ago

// Test
$validator = new SessionValidationMiddleware(['enableLogging' => false]);
$result = $validator->validate();

// Expected: $result['status'] === 'expired'
```

### Scenario 3: Invalid Status
```php
<?php
// Setup: Session with inactive status
session_start();
$_SESSION['gibbonPersonID'] = '000001';
$_SESSION['status'] = 'Inactive';

// Test
$validator = new SessionValidationMiddleware(['enableLogging' => false]);
$result = $validator->validate();

// Expected: $result['status'] === 'invalid_status'
```

## Files Created

1. **SessionValidationMiddleware.php** - Main middleware class
2. **SessionValidationMiddlewareTest.php** - Comprehensive unit tests
3. **README_SESSION_VALIDATION.md** - Complete documentation
4. **examples/auth_token_with_middleware.php** - Integration example

## Expected Test Results

### Unit Test Summary
```
Tests: 34
Assertions: 80+
Time: < 1 second
Failures: 0
Errors: 0
```

### Test Coverage
```
Classes: 100% (1/1)
Methods: >95%
Lines: >90%
```

## Documentation Verification

Review the following documentation files:
- [ ] README_SESSION_VALIDATION.md is complete
- [ ] Code comments are comprehensive
- [ ] Examples are clear and runnable
- [ ] Configuration options are documented
- [ ] Error codes are documented

## Success Criteria

✅ All PHP syntax checks pass
✅ All unit tests pass (34/34)
✅ Code coverage >80%
✅ Integration example works
✅ Documentation is complete
✅ No security vulnerabilities
✅ Follows Gibbon coding standards
✅ Error handling is comprehensive
