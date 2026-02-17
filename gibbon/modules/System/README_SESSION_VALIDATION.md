# Session Validation Middleware

## Overview

The `SessionValidationMiddleware` provides centralized, reusable session validation for Gibbon modules and endpoints. It ensures consistent session checking across the application with comprehensive error handling and security features.

## Features

- ✅ **Centralized Validation**: Single source of truth for session validation logic
- ✅ **Flexible Configuration**: Customizable timeout, logging, and status requirements
- ✅ **Multiple Validation Modes**: Validate with responses, exceptions, or simple boolean checks
- ✅ **Session Timeout**: Automatic detection of inactive sessions
- ✅ **Status Validation**: Ensure users have appropriate status (Full, Expected, etc.)
- ✅ **Audit Logging**: Optional logging of validation events
- ✅ **Security Features**: Session regeneration and destruction methods
- ✅ **Error Handling**: Structured error responses with appropriate HTTP codes

## Installation

The middleware is located at:
```
gibbon/modules/System/SessionValidationMiddleware.php
```

## Basic Usage

### Simple Validation

```php
<?php
require_once __DIR__ . '/SessionValidationMiddleware.php';

use Gibbon\Module\System\SessionValidationMiddleware;

// Create middleware instance
$sessionValidator = new SessionValidationMiddleware();

// Validate session
$result = $sessionValidator->validate();

if ($result['valid']) {
    $userData = $result['data'];
    echo "Welcome, {$userData['username']}!";
} else {
    echo "Error: {$result['message']}";
}
```

### Validate or Respond

Automatically send JSON error response if session is invalid:

```php
<?php
use Gibbon\Module\System\SessionValidationMiddleware;

$sessionValidator = new SessionValidationMiddleware();

// Validate and send error response if invalid
$userData = $sessionValidator->validateOrRespond(401);

if ($userData === null) {
    // Response was sent, execution stopped
    exit;
}

// Session is valid, continue processing
processAuthenticatedRequest($userData);
```

### Validate or Fail

Throw exception if session is invalid:

```php
<?php
use Gibbon\Module\System\SessionValidationMiddleware;

$sessionValidator = new SessionValidationMiddleware();

try {
    // Validate and throw exception if invalid
    $userData = $sessionValidator->validateOrFail();

    // Session is valid
    processAuthenticatedRequest($userData);

} catch (RuntimeException $e) {
    // Handle invalid session
    http_response_code(401);
    echo json_encode([
        'error' => 'Unauthorized',
        'message' => $e->getMessage()
    ]);
}
```

### Simple Boolean Check

```php
<?php
use Gibbon\Module\System\SessionValidationMiddleware;

$sessionValidator = new SessionValidationMiddleware();

// Simple true/false check (doesn't update activity time)
if ($sessionValidator->isValid()) {
    echo "Session is active";
} else {
    echo "Please log in";
}
```

## Configuration Options

```php
<?php
$config = [
    // Session timeout in seconds (default: 1800 = 30 minutes)
    'timeout' => 3600,

    // Enable audit logging (default: true)
    'enableLogging' => true,

    // Require user status validation (default: true)
    'requireStatus' => true,

    // Allowed user statuses (default: ['Full'])
    'allowedStatuses' => ['Full', 'Expected'],
];

$sessionValidator = new SessionValidationMiddleware($config);
```

### Configuration Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `timeout` | int | 1800 | Session timeout in seconds |
| `enableLogging` | bool | true | Enable audit logging to error_log |
| `requireStatus` | bool | true | Validate user status field |
| `allowedStatuses` | array | ['Full'] | Array of allowed user status values |

## Validation Response Structure

The `validate()` method returns an array with the following structure:

```php
[
    'status' => 'valid',           // Validation status code
    'valid' => true,               // Boolean: is session valid?
    'data' => [                    // Session data (null if invalid)
        'personID' => '000123',
        'username' => 'testuser',
        'email' => 'test@example.com',
        'gibbonRoleIDPrimary' => '002',
        'surname' => 'User',
        'firstName' => 'Test',
        'preferredName' => 'Tester',
        'status' => 'Full',
        'sessionID' => 'abc123...',
        'lastActivity' => 1234567890
    ],
    'message' => 'Session is valid' // Human-readable message
]
```

## Status Codes

| Code | Constant | Description | HTTP Code |
|------|----------|-------------|-----------|
| `valid` | `SESSION_VALID` | Session is valid | 200 |
| `not_started` | `SESSION_NOT_STARTED` | No active PHP session | 401 |
| `no_user` | `SESSION_NO_USER` | No authenticated user | 401 |
| `inactive` | `SESSION_INACTIVE` | Session inactive | 401 |
| `expired` | `SESSION_EXPIRED` | Session timeout exceeded | 401 |
| `invalid_status` | `SESSION_INVALID_STATUS` | User status not allowed | 403 |

## Integration with Existing Endpoints

### Refactoring auth_token.php

The existing `auth_token.php` endpoint can be refactored to use this middleware:

```php
<?php
// auth_token.php
require_once __DIR__ . '/SessionValidationMiddleware.php';

use Gibbon\Module\System\SessionValidationMiddleware;

// Ensure POST only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

header('Content-Type: application/json');

// Start session if needed
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Validate session using middleware
$sessionValidator = new SessionValidationMiddleware();
$sessionData = $sessionValidator->validateOrRespond();

if ($sessionData === null) {
    // Response already sent
    exit;
}

// Generate JWT token
$token = createTokenFromSession($sessionData);

// Return success response
echo json_encode([
    'success' => true,
    'token' => $token,
    'expires_in' => 3600,
    'user' => [
        'id' => $sessionData['personID'],
        'username' => $sessionData['username'],
        'email' => $sessionData['email']
    ]
]);
```

## Security Features

### Session Regeneration

Prevent session fixation attacks by regenerating session IDs:

```php
<?php
$sessionValidator = new SessionValidationMiddleware();

// Regenerate session ID after successful login
$sessionValidator->regenerateId(true);
```

### Session Destruction

Properly destroy sessions on logout:

```php
<?php
$sessionValidator = new SessionValidationMiddleware();

// Destroy session (logs the event if logging is enabled)
$sessionValidator->destroy();
```

## Testing

Comprehensive unit tests are provided in:
```
gibbon/tests/unit/Modules/System/SessionValidationMiddlewareTest.php
```

Run tests with PHPUnit:
```bash
cd gibbon
vendor/bin/phpunit tests/unit/Modules/System/SessionValidationMiddlewareTest.php
```

### Test Coverage

The test suite covers:
- ✅ No active session
- ✅ No authenticated user
- ✅ Invalid user status
- ✅ Session timeout/expiration
- ✅ Valid session validation
- ✅ Activity time updates
- ✅ Configuration options
- ✅ Multiple validation modes
- ✅ Session regeneration
- ✅ Session destruction
- ✅ Edge cases and error handling

## Error Responses

When using `validateOrRespond()`, invalid sessions receive a JSON error response:

```json
{
    "success": false,
    "error": "Session Expired",
    "message": "Session has expired due to inactivity",
    "code": "expired"
}
```

## Logging

When logging is enabled (default), validation events are logged to PHP's error log:

```
[SessionValidation] Session validated successfully - User: testuser (ID: 000123) - Session: abc123...
[SessionValidation] Session destroyed - User: testuser (ID: 000123) - Session: abc123...
[SessionValidation] Session ID regenerated from abc123... to def456... - User: testuser (ID: 000123)
```

## Best Practices

1. **Use `validateOrRespond()` for API endpoints**: Automatically handles errors with proper HTTP codes
2. **Use `validateOrFail()` for internal logic**: Clean exception handling for business logic
3. **Use `isValid()` for conditional checks**: When you don't need the full session data
4. **Enable logging in production**: Helps with security auditing and debugging
5. **Set appropriate timeouts**: Balance security with user experience
6. **Regenerate session IDs**: After login, privilege escalation, or sensitive operations

## Examples

### API Endpoint Protection

```php
<?php
// api_endpoint.php
require_once __DIR__ . '/SessionValidationMiddleware.php';

use Gibbon\Module\System\SessionValidationMiddleware;

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$sessionValidator = new SessionValidationMiddleware([
    'timeout' => 3600,  // 1 hour for API calls
    'allowedStatuses' => ['Full', 'Expected']
]);

$userData = $sessionValidator->validateOrRespond(401);

if ($userData === null) {
    exit;
}

// Process authenticated API request
$response = processApiRequest($userData);
echo json_encode($response);
```

### Page Access Control

```php
<?php
// protected_page.php
require_once __DIR__ . '/SessionValidationMiddleware.php';

use Gibbon\Module\System\SessionValidationMiddleware;

session_start();

$sessionValidator = new SessionValidationMiddleware();

if (!$sessionValidator->isValid()) {
    header('Location: /login.php');
    exit;
}

// Render protected page
?>
<!DOCTYPE html>
<html>
<head><title>Protected Page</title></head>
<body>
    <h1>Welcome!</h1>
</body>
</html>
```

### Admin-Only Access

```php
<?php
// admin_page.php
require_once __DIR__ . '/SessionValidationMiddleware.php';

use Gibbon\Module\System\SessionValidationMiddleware;

session_start();

$sessionValidator = new SessionValidationMiddleware([
    'allowedStatuses' => ['Full']
]);

try {
    $userData = $sessionValidator->validateOrFail();

    // Check if user is admin
    if ($userData['gibbonRoleIDPrimary'] !== '001') {
        http_response_code(403);
        die('Access denied: Admin privileges required');
    }

    // Render admin page

} catch (RuntimeException $e) {
    header('Location: /login.php');
    exit;
}
```

## Version History

### v1.0.0 (2026-02-16)
- Initial release
- Core validation functionality
- Multiple validation modes
- Configuration options
- Security features
- Comprehensive test suite
- Documentation

## License

This code is part of the Gibbon project and is licensed under the GNU General Public License v3.0.
