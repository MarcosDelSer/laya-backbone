<?php
/**
 * Unit tests for SessionValidationMiddleware
 *
 * Tests session validation logic, error handling, and configuration options.
 */

namespace Gibbon\Tests\Unit\Modules\System;

use PHPUnit\Framework\TestCase;

// Mock the session functions if needed
if (!function_exists('session_status')) {
    function session_status() {
        return $_SESSION['_mock_status'] ?? PHP_SESSION_NONE;
    }
}

if (!function_exists('session_id')) {
    function session_id($id = null) {
        if ($id !== null) {
            $_SESSION['_mock_session_id'] = $id;
            return $id;
        }
        return $_SESSION['_mock_session_id'] ?? 'test_session_id';
    }
}

require_once __DIR__ . '/../../../../modules/System/SessionValidationMiddleware.php';

use Gibbon\Module\System\SessionValidationMiddleware;

class SessionValidationMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Clear session before each test
        $_SESSION = [];

        // Suppress error_log output during tests
        ini_set('error_log', '/dev/null');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $_SESSION = [];
    }

    /**
     * Set up a mock active session
     */
    private function setActiveSession(): void
    {
        $_SESSION['_mock_status'] = PHP_SESSION_ACTIVE;
        $_SESSION['_mock_session_id'] = 'test_session_' . uniqid();
    }

    /**
     * Set up a complete valid session with user data
     */
    private function setValidSession(): void
    {
        $this->setActiveSession();
        $_SESSION['gibbonPersonID'] = '000123';
        $_SESSION['username'] = 'testuser';
        $_SESSION['email'] = 'test@example.com';
        $_SESSION['gibbonRoleIDPrimary'] = '002';
        $_SESSION['surname'] = 'User';
        $_SESSION['firstName'] = 'Test';
        $_SESSION['preferredName'] = 'Tester';
        $_SESSION['status'] = 'Full';
        $_SESSION['lastActivityTime'] = time();
    }

    public function testConstructorWithDefaultConfig(): void
    {
        $middleware = new SessionValidationMiddleware();
        $this->assertInstanceOf(SessionValidationMiddleware::class, $middleware);
    }

    public function testConstructorWithCustomConfig(): void
    {
        $config = [
            'timeout' => 3600,
            'enableLogging' => false,
            'requireStatus' => false,
        ];

        $middleware = new SessionValidationMiddleware($config);
        $this->assertInstanceOf(SessionValidationMiddleware::class, $middleware);
    }

    public function testValidateWithNoActiveSession(): void
    {
        $_SESSION['_mock_status'] = PHP_SESSION_NONE;

        $middleware = new SessionValidationMiddleware(['enableLogging' => false]);
        $result = $middleware->validate();

        $this->assertFalse($result['valid']);
        $this->assertEquals(SessionValidationMiddleware::SESSION_NOT_STARTED, $result['status']);
        $this->assertNull($result['data']);
        $this->assertStringContainsString('No active PHP session', $result['message']);
    }

    public function testValidateWithNoUser(): void
    {
        $this->setActiveSession();
        // Don't set gibbonPersonID

        $middleware = new SessionValidationMiddleware(['enableLogging' => false]);
        $result = $middleware->validate();

        $this->assertFalse($result['valid']);
        $this->assertEquals(SessionValidationMiddleware::SESSION_NO_USER, $result['status']);
        $this->assertNull($result['data']);
        $this->assertStringContainsString('No authenticated user', $result['message']);
    }

    public function testValidateWithEmptyPersonID(): void
    {
        $this->setActiveSession();
        $_SESSION['gibbonPersonID'] = '';

        $middleware = new SessionValidationMiddleware(['enableLogging' => false]);
        $result = $middleware->validate();

        $this->assertFalse($result['valid']);
        $this->assertEquals(SessionValidationMiddleware::SESSION_NO_USER, $result['status']);
    }

    public function testValidateWithInvalidStatus(): void
    {
        $this->setValidSession();
        $_SESSION['status'] = 'Inactive';

        $middleware = new SessionValidationMiddleware(['enableLogging' => false]);
        $result = $middleware->validate();

        $this->assertFalse($result['valid']);
        $this->assertEquals(SessionValidationMiddleware::SESSION_INVALID_STATUS, $result['status']);
        $this->assertStringContainsString('not allowed', $result['message']);
    }

    public function testValidateWithMultipleAllowedStatuses(): void
    {
        $this->setValidSession();
        $_SESSION['status'] = 'Expected';

        $config = [
            'enableLogging' => false,
            'allowedStatuses' => ['Full', 'Expected'],
        ];

        $middleware = new SessionValidationMiddleware($config);
        $result = $middleware->validate();

        $this->assertTrue($result['valid']);
        $this->assertEquals(SessionValidationMiddleware::SESSION_VALID, $result['status']);
    }

    public function testValidateWithoutStatusRequirement(): void
    {
        $this->setValidSession();
        $_SESSION['status'] = 'AnyStatus';

        $config = [
            'enableLogging' => false,
            'requireStatus' => false,
        ];

        $middleware = new SessionValidationMiddleware($config);
        $result = $middleware->validate();

        $this->assertTrue($result['valid']);
    }

    public function testValidateWithExpiredSession(): void
    {
        $this->setValidSession();
        // Set last activity to 2 hours ago (default timeout is 30 minutes)
        $_SESSION['lastActivityTime'] = time() - 7200;

        $middleware = new SessionValidationMiddleware(['enableLogging' => false]);
        $result = $middleware->validate();

        $this->assertFalse($result['valid']);
        $this->assertEquals(SessionValidationMiddleware::SESSION_EXPIRED, $result['status']);
        $this->assertStringContainsString('expired', $result['message']);
    }

    public function testValidateWithCustomTimeout(): void
    {
        $this->setValidSession();
        // Set last activity to 10 seconds ago
        $_SESSION['lastActivityTime'] = time() - 10;

        $config = [
            'enableLogging' => false,
            'timeout' => 5, // 5 second timeout
        ];

        $middleware = new SessionValidationMiddleware($config);
        $result = $middleware->validate();

        $this->assertFalse($result['valid']);
        $this->assertEquals(SessionValidationMiddleware::SESSION_EXPIRED, $result['status']);
    }

    public function testValidateWithValidSession(): void
    {
        $this->setValidSession();

        $middleware = new SessionValidationMiddleware(['enableLogging' => false]);
        $result = $middleware->validate();

        $this->assertTrue($result['valid']);
        $this->assertEquals(SessionValidationMiddleware::SESSION_VALID, $result['status']);
        $this->assertIsArray($result['data']);
        $this->assertEquals('000123', $result['data']['personID']);
        $this->assertEquals('testuser', $result['data']['username']);
        $this->assertEquals('test@example.com', $result['data']['email']);
    }

    public function testValidateUpdatesLastActivityTime(): void
    {
        $this->setValidSession();
        $oldTime = time() - 60;
        $_SESSION['lastActivityTime'] = $oldTime;

        $middleware = new SessionValidationMiddleware(['enableLogging' => false]);
        $result = $middleware->validate();

        $this->assertTrue($result['valid']);
        $this->assertGreaterThan($oldTime, $_SESSION['lastActivityTime']);
    }

    public function testValidateReturnsCompleteSessionData(): void
    {
        $this->setValidSession();

        $middleware = new SessionValidationMiddleware(['enableLogging' => false]);
        $result = $middleware->validate();

        $this->assertArrayHasKey('personID', $result['data']);
        $this->assertArrayHasKey('username', $result['data']);
        $this->assertArrayHasKey('email', $result['data']);
        $this->assertArrayHasKey('gibbonRoleIDPrimary', $result['data']);
        $this->assertArrayHasKey('surname', $result['data']);
        $this->assertArrayHasKey('firstName', $result['data']);
        $this->assertArrayHasKey('preferredName', $result['data']);
        $this->assertArrayHasKey('status', $result['data']);
        $this->assertArrayHasKey('sessionID', $result['data']);
    }

    public function testValidateOrFailWithValidSession(): void
    {
        $this->setValidSession();

        $middleware = new SessionValidationMiddleware(['enableLogging' => false]);
        $data = $middleware->validateOrFail();

        $this->assertIsArray($data);
        $this->assertEquals('000123', $data['personID']);
    }

    public function testValidateOrFailWithInvalidSessionThrowsException(): void
    {
        $this->setActiveSession();
        // No user set

        $middleware = new SessionValidationMiddleware(['enableLogging' => false]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No authenticated user');

        $middleware->validateOrFail();
    }

    public function testIsValidWithValidSession(): void
    {
        $this->setValidSession();

        $middleware = new SessionValidationMiddleware(['enableLogging' => false]);
        $isValid = $middleware->isValid();

        $this->assertTrue($isValid);
    }

    public function testIsValidWithInvalidSession(): void
    {
        $this->setActiveSession();

        $middleware = new SessionValidationMiddleware(['enableLogging' => false]);
        $isValid = $middleware->isValid();

        $this->assertFalse($isValid);
    }

    public function testIsValidDoesNotUpdateActivityTime(): void
    {
        $this->setValidSession();
        $originalTime = time() - 60;
        $_SESSION['lastActivityTime'] = $originalTime;

        $middleware = new SessionValidationMiddleware(['enableLogging' => false]);

        // Wait a moment to ensure time would change if it were updated
        sleep(1);

        $middleware->isValid();

        // Activity time should not have changed
        $this->assertEquals($originalTime, $_SESSION['lastActivityTime']);
    }

    public function testRegenerateIdWithActiveSession(): void
    {
        $this->setValidSession();
        $oldSessionId = session_id();

        $middleware = new SessionValidationMiddleware(['enableLogging' => false]);
        $result = $middleware->regenerateId();

        // In test environment, we can't actually regenerate, but method should not error
        $this->assertIsBool($result);
    }

    public function testRegenerateIdWithNoSession(): void
    {
        $_SESSION['_mock_status'] = PHP_SESSION_NONE;

        $middleware = new SessionValidationMiddleware(['enableLogging' => false]);
        $result = $middleware->regenerateId();

        $this->assertFalse($result);
    }

    public function testDestroySession(): void
    {
        $this->setValidSession();

        $middleware = new SessionValidationMiddleware(['enableLogging' => false]);
        $middleware->destroy();

        // In test environment, we can verify the method runs without error
        $this->assertTrue(true);
    }

    public function testDestroySessionWithNoActiveSession(): void
    {
        $_SESSION['_mock_status'] = PHP_SESSION_NONE;

        $middleware = new SessionValidationMiddleware(['enableLogging' => false]);
        $middleware->destroy();

        // Should not throw an error
        $this->assertTrue(true);
    }

    public function testValidationWithLoggingEnabled(): void
    {
        $this->setValidSession();

        $middleware = new SessionValidationMiddleware(['enableLogging' => true]);
        $result = $middleware->validate();

        $this->assertTrue($result['valid']);
        // Logging should not affect the result
    }

    public function testSessionDataWithMissingOptionalFields(): void
    {
        $this->setActiveSession();
        $_SESSION['gibbonPersonID'] = '000456';
        $_SESSION['status'] = 'Full';
        // Don't set optional fields like email, preferredName, etc.

        $middleware = new SessionValidationMiddleware(['enableLogging' => false]);
        $result = $middleware->validate();

        $this->assertTrue($result['valid']);
        $this->assertEquals('000456', $result['data']['personID']);
        $this->assertEquals('', $result['data']['email']);
        $this->assertEquals('', $result['data']['username']);
    }

    public function testSessionValidationConstants(): void
    {
        $this->assertEquals('valid', SessionValidationMiddleware::SESSION_VALID);
        $this->assertEquals('not_started', SessionValidationMiddleware::SESSION_NOT_STARTED);
        $this->assertEquals('no_user', SessionValidationMiddleware::SESSION_NO_USER);
        $this->assertEquals('inactive', SessionValidationMiddleware::SESSION_INACTIVE);
        $this->assertEquals('expired', SessionValidationMiddleware::SESSION_EXPIRED);
        $this->assertEquals('invalid_status', SessionValidationMiddleware::SESSION_INVALID_STATUS);
    }
}
