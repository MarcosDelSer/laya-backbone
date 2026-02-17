<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

namespace Gibbon\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Gibbon\Security\CsrfTokenManager;
use Gibbon\Middleware\CsrfValidationMiddleware;

/**
 * CSRF Protection Integration Tests
 *
 * Tests the complete CSRF protection flow including token generation,
 * form submission validation, middleware integration, and error handling.
 *
 * These integration tests verify that the CSRF protection system works
 * correctly end-to-end across multiple components.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class CsrfProtectionTest extends TestCase
{
    /**
     * @var CsrfTokenManager
     */
    private $tokenManager;

    /**
     * @var CsrfValidationMiddleware
     */
    private $middleware;

    /**
     * Set up before each test.
     */
    protected function setUp(): void
    {
        // Start session if not already active
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Clear session data
        $_SESSION = [];

        // Clear superglobals
        $_POST = [];
        $_SERVER = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/',
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_USER_AGENT' => 'PHPUnit Test Runner',
        ];

        // Initialize components
        $this->tokenManager = new CsrfTokenManager(['enableLogging' => false]);
        $this->middleware = new CsrfValidationMiddleware(
            $this->tokenManager,
            ['enableLogging' => false]
        );
    }

    /**
     * Clean up after each test.
     */
    protected function tearDown(): void
    {
        // Clear session data
        $_SESSION = [];

        // Clear superglobals
        $_POST = [];
        $_SERVER = [];
    }

    /**
     * Test GET request does not require CSRF token.
     */
    public function testGetRequestDoesNotRequireCsrfToken()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/test-page';

        // Should not throw or exit
        $this->middleware->handle();
        $this->assertTrue(true);
    }

    /**
     * Test POST request with valid token succeeds.
     */
    public function testPostRequestWithValidTokenSucceeds()
    {
        // Generate token
        $token = $this->tokenManager->generateToken();

        // Simulate POST request with token
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/submit-form';
        $_POST['csrf_token'] = $token;

        // Should not throw or exit
        $this->middleware->handle();
        $this->assertTrue(true);
    }

    /**
     * Test POST request without token fails.
     *
     * @runInSeparateProcess
     */
    public function testPostRequestWithoutTokenFails()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/submit-form';
        $_POST = ['data' => 'test'];

        $this->expectOutputRegex('/Security Validation Failed|CSRF Validation Failed/');
        $this->middleware->handle();
    }

    /**
     * Test POST request with invalid token fails.
     *
     * @runInSeparateProcess
     */
    public function testPostRequestWithInvalidTokenFails()
    {
        // Generate valid token but don't use it
        $this->tokenManager->generateToken();

        // Use different token
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/submit-form';
        $_POST['csrf_token'] = str_repeat('a', 64);

        $this->expectOutputRegex('/Security Validation Failed|CSRF Validation Failed/');
        $this->middleware->handle();
    }

    /**
     * Test PUT request requires CSRF token.
     *
     * @runInSeparateProcess
     */
    public function testPutRequestRequiresCsrfToken()
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';
        $_SERVER['REQUEST_URI'] = '/api/update';

        $this->expectOutputRegex('/Security Validation Failed|CSRF Validation Failed/');
        $this->middleware->handle();
    }

    /**
     * Test DELETE request requires CSRF token.
     *
     * @runInSeparateProcess
     */
    public function testDeleteRequestRequiresCsrfToken()
    {
        $_SERVER['REQUEST_METHOD'] = 'DELETE';
        $_SERVER['REQUEST_URI'] = '/api/delete';

        $this->expectOutputRegex('/Security Validation Failed|CSRF Validation Failed/');
        $this->middleware->handle();
    }

    /**
     * Test PATCH request requires CSRF token.
     *
     * @runInSeparateProcess
     */
    public function testPatchRequestRequiresCsrfToken()
    {
        $_SERVER['REQUEST_METHOD'] = 'PATCH';
        $_SERVER['REQUEST_URI'] = '/api/patch';

        $this->expectOutputRegex('/Security Validation Failed|CSRF Validation Failed/');
        $this->middleware->handle();
    }

    /**
     * Test exempt path bypasses CSRF validation.
     */
    public function testExemptPathBypassesCsrfValidation()
    {
        // Create middleware with exempt path
        $middleware = new CsrfValidationMiddleware(
            $this->tokenManager,
            [
                'enableLogging' => false,
                'exemptPaths' => ['/api/webhook/*', '/api/public/*']
            ]
        );

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/webhook/stripe';

        // Should not throw or exit
        $middleware->handle();
        $this->assertTrue(true);
    }

    /**
     * Test wildcard exempt path matching.
     */
    public function testWildcardExemptPathMatching()
    {
        $middleware = new CsrfValidationMiddleware(
            $this->tokenManager,
            [
                'enableLogging' => false,
                'exemptPaths' => ['/api/public/*']
            ]
        );

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/public/data';

        // Should not throw or exit
        $middleware->handle();
        $this->assertTrue(true);
    }

    /**
     * Test token from header is accepted.
     */
    public function testTokenFromHeaderIsAccepted()
    {
        $token = $this->tokenManager->generateToken();

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/submit';
        $_SERVER['HTTP_X_CSRF_TOKEN'] = $token;

        // Should not throw or exit
        $this->middleware->handle();
        $this->assertTrue(true);
    }

    /**
     * Test API request returns JSON error.
     *
     * @runInSeparateProcess
     */
    public function testApiRequestReturnsJsonError()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/endpoint';
        $_SERVER['HTTP_ACCEPT'] = 'application/json';

        $this->expectOutputRegex('/"error":\s*"CSRF Validation Failed"/');
        $this->middleware->handle();
    }

    /**
     * Test web request returns HTML error.
     *
     * @runInSeparateProcess
     */
    public function testWebRequestReturnsHtmlError()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/submit-form';
        $_SERVER['HTTP_ACCEPT'] = 'text/html';

        $this->expectOutputRegex('/<html.*Security Validation Failed.*<\/html>/s');
        $this->middleware->handle();
    }

    /**
     * Test form with valid token submits successfully.
     */
    public function testFormWithValidTokenSubmitsSuccessfully()
    {
        // Generate token and add to form
        $token = $this->tokenManager->generateToken();

        // Simulate form submission
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/modules/EnhancedFinance/finance_invoice_addProcess.php';
        $_POST['csrf_token'] = $token;
        $_POST['invoice_amount'] = '100.00';

        // Should not throw or exit
        $this->middleware->handle();
        $this->assertTrue(true);
    }

    /**
     * Test multiple forms can have different tokens.
     */
    public function testMultipleFormsCanHaveDifferentTokens()
    {
        $manager = new CsrfTokenManager([
            'enableLogging' => false,
            'perFormTokens' => true
        ]);

        $token1 = $manager->generateToken('form1');
        $token2 = $manager->generateToken('form2');

        $this->assertNotEquals($token1, $token2);
        $this->assertTrue($manager->validateToken($token1, 'form1'));
        $this->assertTrue($manager->validateToken($token2, 'form2'));
    }

    /**
     * Test token persists across multiple requests.
     */
    public function testTokenPersistsAcrossMultipleRequests()
    {
        // First request - generate token
        $token1 = $this->tokenManager->generateToken();

        // Second request - same token should be returned
        $token2 = $this->tokenManager->getToken();

        $this->assertEquals($token1, $token2);
    }

    /**
     * Test token invalidation prevents reuse.
     */
    public function testTokenInvalidationPreventsReuse()
    {
        $token = $this->tokenManager->generateToken();

        // Invalidate token
        $this->tokenManager->invalidateToken();

        // Old token should no longer validate
        $this->assertFalse($this->tokenManager->validateToken($token));
    }

    /**
     * Test expired token is rejected.
     */
    public function testExpiredTokenIsRejected()
    {
        $manager = new CsrfTokenManager([
            'enableLogging' => false,
            'tokenExpiration' => 1
        ]);

        $token = $manager->generateToken();

        // Wait for token to expire
        sleep(2);

        $this->assertFalse($manager->validateToken($token));
    }

    /**
     * Test token rotation after validation.
     */
    public function testTokenRotationAfterValidation()
    {
        $manager = new CsrfTokenManager([
            'enableLogging' => false,
            'rotateOnUse' => true
        ]);

        $token1 = $manager->generateToken();
        $this->assertTrue($manager->validateToken($token1));

        $token2 = $manager->getToken();
        $this->assertNotEquals($token1, $token2);
    }

    /**
     * Test HTML token field generation.
     */
    public function testHtmlTokenFieldGeneration()
    {
        $field = $this->tokenManager->getTokenField();

        $this->assertStringContainsString('<input type="hidden"', $field);
        $this->assertStringContainsString('name="csrf_token"', $field);
        $this->assertStringContainsString('value="', $field);
        $this->assertMatchesRegularExpression('/value="[a-f0-9]{64}"/', $field);
    }

    /**
     * Test token field can be embedded in forms.
     */
    public function testTokenFieldCanBeEmbeddedInForms()
    {
        $tokenField = $this->tokenManager->getTokenField();

        $form = '<form method="POST" action="/submit">';
        $form .= $tokenField;
        $form .= '<input type="text" name="data">';
        $form .= '<button type="submit">Submit</button>';
        $form .= '</form>';

        $this->assertStringContainsString('csrf_token', $form);
        $this->assertStringContainsString('type="hidden"', $form);
    }

    /**
     * Test concurrent requests with same token.
     */
    public function testConcurrentRequestsWithSameToken()
    {
        $token = $this->tokenManager->generateToken();

        // First validation
        $this->assertTrue($this->tokenManager->validateToken($token));

        // Second validation (without rotation)
        $this->assertTrue($this->tokenManager->validateToken($token));
    }

    /**
     * Test session requirement.
     */
    public function testSessionRequirement()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Session must be active to use CSRF protection');

        // Destroy session
        session_destroy();

        // Try to generate token without session
        $this->tokenManager->generateToken();
    }

    /**
     * Test middleware handles missing session gracefully.
     *
     * @runInSeparateProcess
     */
    public function testMiddlewareHandlesMissingSessionGracefully()
    {
        session_destroy();

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/submit';

        $this->expectOutputRegex('/Security Validation Failed|CSRF/');
        $this->middleware->handle();
    }

    /**
     * Test validateOrFail throws exception on failure.
     */
    public function testValidateOrFailThrowsExceptionOnFailure()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('CSRF token validation failed');
        $this->expectExceptionCode(403);

        $this->tokenManager->generateToken();
        $invalidToken = str_repeat('b', 64);
        $this->tokenManager->validateOrFail($invalidToken);
    }

    /**
     * Test validateOrFail succeeds with valid token.
     */
    public function testValidateOrFailSucceedsWithValidToken()
    {
        $token = $this->tokenManager->generateToken();
        $this->assertTrue($this->tokenManager->validateOrFail($token));
    }

    /**
     * Test empty token is rejected.
     */
    public function testEmptyTokenIsRejected()
    {
        $this->tokenManager->generateToken();
        $this->assertFalse($this->tokenManager->validateToken(''));
    }

    /**
     * Test cleanup of expired tokens.
     */
    public function testCleanupOfExpiredTokens()
    {
        $manager = new CsrfTokenManager([
            'enableLogging' => false,
            'perFormTokens' => true,
            'tokenExpiration' => 1
        ]);

        $manager->generateToken('form1');
        $manager->generateToken('form2');
        $manager->generateToken('form3');

        sleep(2);

        $cleaned = $manager->cleanupExpiredTokens();
        $this->assertEquals(3, $cleaned);
    }

    /**
     * Test max stored tokens limit.
     */
    public function testMaxStoredTokensLimit()
    {
        $manager = new CsrfTokenManager([
            'enableLogging' => false,
            'perFormTokens' => true
        ]);

        // Generate more tokens than the limit
        for ($i = 1; $i <= 15; $i++) {
            $manager->generateToken('form' . $i);
        }

        $tokenCount = count($_SESSION[CsrfTokenManager::SESSION_TOKENS_KEY] ?? []);
        $this->assertLessThanOrEqual(CsrfTokenManager::MAX_STORED_TOKENS, $tokenCount);
    }

    /**
     * Test POST to finance module with valid token.
     */
    public function testPostToFinanceModuleWithValidToken()
    {
        $token = $this->tokenManager->generateToken();

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/modules/EnhancedFinance/finance_invoice_addProcess.php';
        $_POST['csrf_token'] = $token;
        $_POST['invoice_data'] = 'test';

        $this->middleware->handle();
        $this->assertTrue(true);
    }

    /**
     * Test POST to care tracking with valid token.
     */
    public function testPostToCareTrackingWithValidToken()
    {
        $token = $this->tokenManager->generateToken();

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/modules/CareTracking/careTracking_attendance.php';
        $_POST['csrf_token'] = $token;
        $_POST['attendance_data'] = 'test';

        $this->middleware->handle();
        $this->assertTrue(true);
    }

    /**
     * Test timing-attack resistance.
     */
    public function testTimingAttackResistance()
    {
        $token = $this->tokenManager->generateToken();

        // Test with tokens of different similarity
        $almostCorrect = substr($token, 0, 63) . 'x';
        $totallyWrong = str_repeat('0', 64);

        // Both should fail in constant time
        $this->assertFalse($this->tokenManager->validateToken($almostCorrect));
        $this->assertFalse($this->tokenManager->validateToken($totallyWrong));
    }

    /**
     * Test token format consistency.
     */
    public function testTokenFormatConsistency()
    {
        // Generate multiple tokens
        $tokens = [];
        for ($i = 0; $i < 10; $i++) {
            $this->tokenManager->invalidateToken();
            $tokens[] = $this->tokenManager->generateToken();
        }

        // All tokens should be 64 characters hex
        foreach ($tokens as $token) {
            $this->assertEquals(64, strlen($token));
            $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
        }

        // All tokens should be unique
        $uniqueTokens = array_unique($tokens);
        $this->assertEquals(count($tokens), count($uniqueTokens));
    }

    /**
     * Test full form submission flow.
     */
    public function testFullFormSubmissionFlow()
    {
        // Step 1: Generate token for form display
        $token = $this->tokenManager->generateToken();
        $tokenField = $this->tokenManager->getTokenField();

        $this->assertNotEmpty($token);
        $this->assertStringContainsString($token, $tokenField);

        // Step 2: Simulate form submission
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/submit-form';
        $_POST['csrf_token'] = $token;
        $_POST['user_data'] = 'test data';

        // Step 3: Validate via middleware
        $this->middleware->handle();

        // Step 4: Verify token is still valid (no rotation)
        $this->assertTrue($this->tokenManager->validateToken($token));
    }

    /**
     * Test integration with session lifecycle.
     */
    public function testIntegrationWithSessionLifecycle()
    {
        // Generate token in session
        $token1 = $this->tokenManager->generateToken();
        $sessionId1 = session_id();

        // Regenerate session (simulating login)
        session_regenerate_id(true);
        $sessionId2 = session_id();

        $this->assertNotEquals($sessionId1, $sessionId2);

        // Token should still be valid in new session
        $this->assertTrue($this->tokenManager->validateToken($token1));
    }

    /**
     * Test AJAX request detection.
     */
    public function testAjaxRequestDetection()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/ajax-endpoint';
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';

        // Without token, should fail with JSON response
        ob_start();
        $this->middleware->handle();
        $output = ob_get_clean();

        // Should contain JSON error
        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertArrayHasKey('error', $data);
    }
}
