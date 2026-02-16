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
 * Authentication Token Exchange Endpoint
 *
 * Exchanges a valid Gibbon PHP session for a JWT token that can be used
 * with the AI service and other microservices.
 *
 * This endpoint validates the user's current PHP session and generates
 * a JWT token containing user information and role claims.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */

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
 * Validate that the user has an active Gibbon session
 *
 * @return array|null User session data or null if not authenticated
 */
function validateSession() {
    // Check if user is logged in via Gibbon session
    if (!isset($_SESSION['gibbonPersonID']) || empty($_SESSION['gibbonPersonID'])) {
        return null;
    }

    // Return session data
    return [
        'personID' => $_SESSION['gibbonPersonID'],
        'username' => $_SESSION['username'] ?? '',
        'email' => $_SESSION['email'] ?? '',
        'gibbonRoleIDPrimary' => $_SESSION['gibbonRoleIDPrimary'] ?? '',
        'surname' => $_SESSION['surname'] ?? '',
        'firstName' => $_SESSION['firstName'] ?? '',
        'preferredName' => $_SESSION['preferredName'] ?? '',
        'status' => $_SESSION['status'] ?? 'Full',
    ];
}

/**
 * Simple JWT encoder for HS256 algorithm
 *
 * @param array $payload Token payload
 * @param string $secret Secret key
 * @return string Encoded JWT token
 */
function encodeJWT(array $payload, string $secret): string {
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
function getJWTSecret(): string {
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
function mapGibbonRoleToAIRole(string $gibbonRoleID): string {
    // Role mapping - these IDs should match your Gibbon installation
    // Typical Gibbon role IDs:
    // 001 - Administrator
    // 002 - Teacher
    // 003 - Student
    // 004 - Parent
    // 006 - Support Staff

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
function createTokenFromSession(array $sessionData): string {
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
        'session_id' => session_id(),
    ];

    return encodeJWT($payload, $secret);
}

// Main execution
try {
    // Validate session
    $sessionData = validateSession();

    if ($sessionData === null) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Unauthorized',
            'message' => 'No valid Gibbon session found. Please log in first.'
        ]);
        exit;
    }

    // Generate JWT token
    $token = createTokenFromSession($sessionData);

    // Log token exchange (for audit trail)
    error_log(sprintf(
        'JWT token exchanged for user %s (ID: %s) from session %s',
        $sessionData['username'],
        $sessionData['personID'],
        session_id()
    ));

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
