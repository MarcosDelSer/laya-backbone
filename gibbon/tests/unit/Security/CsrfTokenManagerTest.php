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

namespace Gibbon\Tests\Unit\Security;

use PHPUnit\Framework\TestCase;
use Gibbon\Security\CsrfTokenManager;

/**
 * CSRF Token Manager Tests
 *
 * Tests for the CsrfTokenManager class that handles
 * Cross-Site Request Forgery (CSRF) protection.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class CsrfTokenManagerTest extends TestCase
{
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
    }

    /**
     * Clean up after each test.
     */
    protected function tearDown(): void
    {
        // Clear session data
        $_SESSION = [];
    }

    /**
     * Test token generation creates a valid token.
     */
    public function testTokenGenerationCreatesValidToken()
    {
        $manager = new CsrfTokenManager(['enableLogging' => false]);
        $token = $manager->generateToken();

        // Token should be 64 characters (32 bytes hex-encoded)
        $this->assertEquals(64, strlen($token));

        // Token should be hexadecimal
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
    }

    /**
     * Test token is stored in session.
     */
    public function testTokenIsStoredInSession()
    {
        $manager = new CsrfTokenManager(['enableLogging' => false]);
        $token = $manager->generateToken();

        $this->assertArrayHasKey(CsrfTokenManager::SESSION_TOKEN_KEY, $_SESSION);
        $this->assertEquals($token, $_SESSION[CsrfTokenManager::SESSION_TOKEN_KEY]);
        $this->assertArrayHasKey(CsrfTokenManager::SESSION_TOKEN_TIME_KEY, $_SESSION);
    }

    /**
     * Test getToken returns existing token.
     */
    public function testGetTokenReturnsExistingToken()
    {
        $manager = new CsrfTokenManager(['enableLogging' => false]);
        $token1 = $manager->generateToken();
        $token2 = $manager->getToken();

        $this->assertEquals($token1, $token2);
    }

    /**
     * Test getToken generates new token if none exists.
     */
    public function testGetTokenGeneratesNewTokenIfNoneExists()
    {
        $manager = new CsrfTokenManager(['enableLogging' => false]);
        $token = $manager->getToken();

        $this->assertEquals(64, strlen($token));
        $this->assertArrayHasKey(CsrfTokenManager::SESSION_TOKEN_KEY, $_SESSION);
    }

    /**
     * Test valid token validation succeeds.
     */
    public function testValidTokenValidationSucceeds()
    {
        $manager = new CsrfTokenManager(['enableLogging' => false]);
        $token = $manager->generateToken();

        $this->assertTrue($manager->validateToken($token));
    }

    /**
     * Test invalid token validation fails.
     */
    public function testInvalidTokenValidationFails()
    {
        $manager = new CsrfTokenManager(['enableLogging' => false]);
        $manager->generateToken();

        $invalidToken = str_repeat('a', 64);
        $this->assertFalse($manager->validateToken($invalidToken));
    }

    /**
     * Test validation with no stored token fails.
     */
    public function testValidationWithNoStoredTokenFails()
    {
        $manager = new CsrfTokenManager(['enableLogging' => false]);
        $token = str_repeat('a', 64);

        $this->assertFalse($manager->validateToken($token));
    }

    /**
     * Test validation with empty token fails.
     */
    public function testValidationWithEmptyTokenFails()
    {
        $manager = new CsrfTokenManager(['enableLogging' => false]);
        $manager->generateToken();

        $this->assertFalse($manager->validateToken(''));
    }

    /**
     * Test expired token validation fails.
     */
    public function testExpiredTokenValidationFails()
    {
        // Set very short expiration
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
     * Test token rotation on use.
     */
    public function testTokenRotationOnUse()
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
     * Test token does not rotate when disabled.
     */
    public function testTokenDoesNotRotateWhenDisabled()
    {
        $manager = new CsrfTokenManager([
            'enableLogging' => false,
            'rotateOnUse' => false
        ]);

        $token1 = $manager->generateToken();
        $this->assertTrue($manager->validateToken($token1));

        $token2 = $manager->getToken();
        $this->assertEquals($token1, $token2);
    }

    /**
     * Test token invalidation.
     */
    public function testTokenInvalidation()
    {
        $manager = new CsrfTokenManager(['enableLogging' => false]);
        $token = $manager->generateToken();

        $manager->invalidateToken();

        $this->assertArrayNotHasKey(CsrfTokenManager::SESSION_TOKEN_KEY, $_SESSION);
        $this->assertArrayNotHasKey(CsrfTokenManager::SESSION_TOKEN_TIME_KEY, $_SESSION);
        $this->assertFalse($manager->validateToken($token));
    }

    /**
     * Test per-form token generation.
     */
    public function testPerFormTokenGeneration()
    {
        $manager = new CsrfTokenManager([
            'enableLogging' => false,
            'perFormTokens' => true
        ]);

        $token1 = $manager->generateToken('form1');
        $token2 = $manager->generateToken('form2');

        $this->assertNotEquals($token1, $token2);
        $this->assertArrayHasKey(CsrfTokenManager::SESSION_TOKENS_KEY, $_SESSION);
    }

    /**
     * Test per-form token validation.
     */
    public function testPerFormTokenValidation()
    {
        $manager = new CsrfTokenManager([
            'enableLogging' => false,
            'perFormTokens' => true
        ]);

        $token1 = $manager->generateToken('form1');
        $token2 = $manager->generateToken('form2');

        $this->assertTrue($manager->validateToken($token1, 'form1'));
        $this->assertTrue($manager->validateToken($token2, 'form2'));
        $this->assertFalse($manager->validateToken($token1, 'form2'));
        $this->assertFalse($manager->validateToken($token2, 'form1'));
    }

    /**
     * Test per-form token invalidation.
     */
    public function testPerFormTokenInvalidation()
    {
        $manager = new CsrfTokenManager([
            'enableLogging' => false,
            'perFormTokens' => true
        ]);

        $token1 = $manager->generateToken('form1');
        $token2 = $manager->generateToken('form2');

        $manager->invalidateToken('form1');

        $this->assertFalse($manager->validateToken($token1, 'form1'));
        $this->assertTrue($manager->validateToken($token2, 'form2'));
    }

    /**
     * Test cleanup of expired tokens.
     */
    public function testCleanupExpiredTokens()
    {
        $manager = new CsrfTokenManager([
            'enableLogging' => false,
            'perFormTokens' => true,
            'tokenExpiration' => 1
        ]);

        $manager->generateToken('form1');
        $manager->generateToken('form2');

        // Wait for tokens to expire
        sleep(2);

        $cleaned = $manager->cleanupExpiredTokens();

        $this->assertEquals(2, $cleaned);
    }

    /**
     * Test cleanup returns zero when no expired tokens.
     */
    public function testCleanupReturnsZeroWhenNoExpiredTokens()
    {
        $manager = new CsrfTokenManager([
            'enableLogging' => false,
            'perFormTokens' => true
        ]);

        $manager->generateToken('form1');
        $manager->generateToken('form2');

        $cleaned = $manager->cleanupExpiredTokens();

        $this->assertEquals(0, $cleaned);
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
        for ($i = 1; $i <= CsrfTokenManager::MAX_STORED_TOKENS + 5; $i++) {
            $manager->generateToken('form' . $i);
        }

        $tokenCount = count($_SESSION[CsrfTokenManager::SESSION_TOKENS_KEY] ?? []);

        $this->assertLessThanOrEqual(CsrfTokenManager::MAX_STORED_TOKENS, $tokenCount);
    }

    /**
     * Test HTML token field generation.
     */
    public function testHtmlTokenFieldGeneration()
    {
        $manager = new CsrfTokenManager(['enableLogging' => false]);
        $field = $manager->getTokenField();

        $this->assertStringContainsString('<input type="hidden"', $field);
        $this->assertStringContainsString('name="csrf_token"', $field);
        $this->assertStringContainsString('value="', $field);
    }

    /**
     * Test HTML field escapes token properly.
     */
    public function testHtmlFieldEscapesTokenProperly()
    {
        $manager = new CsrfTokenManager(['enableLogging' => false]);
        $field = $manager->getTokenField();

        // Should not contain unescaped quotes or brackets
        $this->assertStringNotContainsString('<script>', $field);
        $this->assertMatchesRegularExpression('/value="[a-f0-9]{64}"/', $field);
    }

    /**
     * Test validateOrFail succeeds with valid token.
     */
    public function testValidateOrFailSucceedsWithValidToken()
    {
        $manager = new CsrfTokenManager(['enableLogging' => false]);
        $token = $manager->generateToken();

        $this->assertTrue($manager->validateOrFail($token));
    }

    /**
     * Test validateOrFail throws exception with invalid token.
     */
    public function testValidateOrFailThrowsExceptionWithInvalidToken()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('CSRF token validation failed');
        $this->expectExceptionCode(403);

        $manager = new CsrfTokenManager(['enableLogging' => false]);
        $manager->generateToken();

        $invalidToken = str_repeat('a', 64);
        $manager->validateOrFail($invalidToken);
    }

    /**
     * Test session requirement.
     */
    public function testSessionRequirement()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Session must be active to use CSRF protection');

        // Close the session
        session_destroy();

        $manager = new CsrfTokenManager(['enableLogging' => false]);
        $manager->generateToken();
    }

    /**
     * Test default configuration values.
     */
    public function testDefaultConfigurationValues()
    {
        // Test that default values work without errors
        $manager = new CsrfTokenManager();
        $token = $manager->generateToken();

        $this->assertEquals(64, strlen($token));
        $this->assertTrue($manager->validateToken($token));
    }

    /**
     * Test custom token expiration configuration.
     */
    public function testCustomTokenExpirationConfiguration()
    {
        $manager = new CsrfTokenManager([
            'enableLogging' => false,
            'tokenExpiration' => 3600
        ]);

        $token = $manager->generateToken();
        $this->assertTrue($manager->validateToken($token));
    }

    /**
     * Test token format consistency.
     */
    public function testTokenFormatConsistency()
    {
        $manager = new CsrfTokenManager(['enableLogging' => false]);

        // Generate multiple tokens
        $tokens = [];
        for ($i = 0; $i < 10; $i++) {
            $manager->invalidateToken();
            $tokens[] = $manager->generateToken();
        }

        // All tokens should be 64 characters
        foreach ($tokens as $token) {
            $this->assertEquals(64, strlen($token));
            $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
        }

        // All tokens should be unique
        $uniqueTokens = array_unique($tokens);
        $this->assertEquals(count($tokens), count($uniqueTokens));
    }

    /**
     * Test timing-attack resistance.
     */
    public function testTimingAttackResistance()
    {
        $manager = new CsrfTokenManager(['enableLogging' => false]);
        $token = $manager->generateToken();

        // Test with tokens of different similarity
        $almostCorrect = substr($token, 0, 63) . 'x';
        $totallyWrong = str_repeat('0', 64);

        // Both should fail
        $this->assertFalse($manager->validateToken($almostCorrect));
        $this->assertFalse($manager->validateToken($totallyWrong));
    }

    /**
     * Test token data structure.
     */
    public function testTokenDataStructure()
    {
        $manager = new CsrfTokenManager([
            'enableLogging' => false,
            'perFormTokens' => true
        ]);

        $token = $manager->generateToken('testForm');

        $this->assertArrayHasKey(CsrfTokenManager::SESSION_TOKENS_KEY, $_SESSION);
        $this->assertArrayHasKey('testForm', $_SESSION[CsrfTokenManager::SESSION_TOKENS_KEY]);

        $tokenData = $_SESSION[CsrfTokenManager::SESSION_TOKENS_KEY]['testForm'];

        $this->assertArrayHasKey('token', $tokenData);
        $this->assertArrayHasKey('time', $tokenData);
        $this->assertEquals($token, $tokenData['token']);
        $this->assertIsInt($tokenData['time']);
    }

    /**
     * Test configuration merge.
     */
    public function testConfigurationMerge()
    {
        $manager = new CsrfTokenManager([
            'tokenExpiration' => 3600,
            'rotateOnUse' => true
        ]);

        $token = $manager->generateToken();
        $this->assertTrue($manager->validateToken($token));
    }

    /**
     * Test multiple form tokens coexist.
     */
    public function testMultipleFormTokensCoexist()
    {
        $manager = new CsrfTokenManager([
            'enableLogging' => false,
            'perFormTokens' => true
        ]);

        $tokens = [];
        $formIds = ['login', 'register', 'profile', 'settings'];

        foreach ($formIds as $formId) {
            $tokens[$formId] = $manager->generateToken($formId);
        }

        // All tokens should validate correctly for their respective forms
        foreach ($formIds as $formId) {
            $this->assertTrue($manager->validateToken($tokens[$formId], $formId));
        }
    }
}
