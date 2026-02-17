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

/**
 * Authentication Token Exchange Endpoint - Enhanced with Middleware
 *
 * This is an example of how to integrate SessionValidationMiddleware
 * with the auth_token.php endpoint for improved code organization
 * and reusability.
 *
 * @version v2.0.00
 * @since   v2.0.00
 */

require_once __DIR__ . '/../SessionValidationMiddleware.php';

use Gibbon\Module\System\SessionValidationMiddleware;

// Ensure this is accessed via POST only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Method Not Allowed',
        'message' => 'This endpoint only accepts POST requests'
    ]);
    exit;
}

// Set response content type
header('Content-Type: application/json');

// Start or resume session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Simple JWT encoder for HS256 algorithm
 *
 * @param array $payload Token payload
 * @param string $secret Secret key
 * @return string Encoded JWT token
 */
function encodeJWT(array $payload, string $secret): string
{
    $header = [
        'typ' => 'JWT',
        'alg' => 'HS256'
    ];

    // Base64Url encode header
    $headerEncoded = rtrim(strtr(base64_encode(json_encode($header)), '+/', '-_'), '=');

    // Base64Url encode payload
    $payloadEncoded = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');

    // Create signature
    $signature = hash_hmac('sha256', "$headerEncoded.$payloadEncoded", $secret, true);
    $signatureEncoded = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

    // Return complete JWT
    return "$headerEncoded.$payloadEncoded.$signatureEncoded";
}

/**
 * Get JWT secret from environment or configuration
 *
 * @return string JWT secret key
 */
function getJWTSecret(): string
{
    // Try environment variable first
    $secret = getenv('JWT_SECRET_KEY');
    if ($secret !== false && !empty($secret)) {
        return $secret;
    }

    // Default secret (should match ai-service config)
    // In production, this should be loaded from a secure config file
    return 'your_jwt_secret_key_change_in_production';
}

/**
 * Map Gibbon role ID to AI service role
 *
 * @param string $gibbonRoleID Gibbon role ID
 * @return string AI service role name
 */
function mapGibbonRoleToAIRole(string $gibbonRoleID): string
{
    $roleMapping = [
        '001' => 'admin',
        '002' => 'teacher',
        '003' => 'student',
        '004' => 'parent',
        '006' => 'staff',
    ];

    return $roleMapping[$gibbonRoleID] ?? 'user';
}

/**
 * Create JWT token from session data
 *
 * @param array $sessionData Validated session data
 * @return string JWT token
 */
function createTokenFromSession(array $sessionData): string
{
    $secret = getJWTSecret();
    $now = time();
    $expiresIn = 3600; // 1 hour

    // Map Gibbon role to AI service role
    $aiRole = mapGibbonRoleToAIRole($sessionData['gibbonRoleIDPrimary']);

    $payload = [
        'sub' => (string)$sessionData['personID'],
        'iat' => $now,
        'exp' => $now + $expiresIn,
        'username' => $sessionData['username'],
        'email' => $sessionData['email'],
        'role' => $aiRole,
        'gibbon_role_id' => $sessionData['gibbonRoleIDPrimary'],
        'name' => trim(($sessionData['preferredName'] ?: $sessionData['firstName']) . ' ' . $sessionData['surname']),
        'source' => 'gibbon',
        'session_id' => $sessionData['sessionID'],
    ];

    return encodeJWT($payload, $secret);
}

// Main execution
try {
    // Create middleware with custom configuration
    $sessionValidator = new SessionValidationMiddleware([
        'timeout' => 1800,  // 30 minutes
        'enableLogging' => true,
        'requireStatus' => true,
        'allowedStatuses' => ['Full', 'Expected']
    ]);

    // Validate session using middleware
    $sessionData = $sessionValidator->validateOrRespond(401);

    // If validation failed, response was already sent
    if ($sessionData === null) {
        exit;
    }

    // Generate JWT token
    $token = createTokenFromSession($sessionData);

    // Return success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'token' => $token,
        'expires_in' => 3600,
        'token_type' => 'Bearer',
        'user' => [
            'id' => $sessionData['personID'],
            'username' => $sessionData['username'],
            'email' => $sessionData['email'],
            'name' => trim(($sessionData['preferredName'] ?: $sessionData['firstName']) . ' ' . $sessionData['surname']),
            'role' => mapGibbonRoleToAIRole($sessionData['gibbonRoleIDPrimary']),
        ]
    ]);

} catch (Exception $e) {
    // Handle unexpected errors
    error_log('JWT token exchange error: ' . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal Server Error',
        'message' => 'An error occurred while generating the authentication token'
    ]);
}
