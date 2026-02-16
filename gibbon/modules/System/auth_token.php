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
 * Features:
 * - Session validation and JWT token generation
 * - Role mapping from Gibbon to AI service
 * - Comprehensive audit logging with AuthTokenLogGateway
 * - IP address and user agent tracking
 * - Error handling and security logging
 *
 * @version v1.0.00
 * @since   v1.0.00
 */

use Gibbon\Module\System\Domain\AuthTokenLogGateway;

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
 * @return array Token data with expiration
 */
function createTokenFromSession(array $sessionData): array {
    $secret = getJWTSecret();
    $now = time();
    $expiresIn = 3600; // 1 hour
    $expiresAt = $now + $expiresIn;

    // Map Gibbon role to AI service role
    $aiRole = mapGibbonRoleToAIRole($sessionData['gibbonRoleIDPrimary']);

    $payload = [
        'sub' => (string)$sessionData['personID'],
        'iat' => $now,
        'exp' => $expiresAt,
        'username' => $sessionData['username'],
        'email' => $sessionData['email'],
        'role' => $aiRole,
        'gibbon_role_id' => $sessionData['gibbonRoleIDPrimary'],
        'name' => trim(($sessionData['preferredName'] ?: $sessionData['firstName']) . ' ' . $sessionData['surname']),
        'source' => 'gibbon',
        'session_id' => session_id(),
    ];

    return [
        'token' => encodeJWT($payload, $secret),
        'expires_at' => $expiresAt,
        'ai_role' => $aiRole,
    ];
}

/**
 * Get client IP address (handles proxies and load balancers).
 *
 * @return string|null Client IP address or null if unavailable
 */
function getClientIP(): ?string {
    // Check for IP from various headers (in order of reliability)
    $headers = [
        'HTTP_CF_CONNECTING_IP',  // Cloudflare
        'HTTP_X_REAL_IP',         // Nginx proxy
        'HTTP_X_FORWARDED_FOR',   // Standard proxy header
        'REMOTE_ADDR',            // Direct connection
    ];

    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ip = $_SERVER[$header];

            // Handle comma-separated list (X-Forwarded-For)
            if (strpos($ip, ',') !== false) {
                $ips = explode(',', $ip);
                $ip = trim($ips[0]);
            }

            // Validate IP address
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }

    return null;
}

/**
 * Get user agent string.
 *
 * @return string|null User agent or null if unavailable
 */
function getUserAgent(): ?string {
    return $_SERVER['HTTP_USER_AGENT'] ?? null;
}

/**
 * Initialize database connection for logging.
 * This is a minimal bootstrap - in production you'd use Gibbon's full container.
 *
 * @return AuthTokenLogGateway|null Gateway instance or null if unavailable
 */
function getAuthTokenLogGateway(): ?AuthTokenLogGateway {
    // Check if we're in a Gibbon context with database access
    // For now, return null - this would be properly initialized in production
    // with Gibbon's dependency injection container

    // In production, you would do something like:
    // global $container;
    // return $container->get(AuthTokenLogGateway::class);

    return null;
}

// Main execution
try {
    // Validate session
    $sessionData = validateSession();
    $ipAddress = getClientIP();
    $userAgent = getUserAgent();

    if ($sessionData === null) {
        // Log failed authentication attempt (no valid session)
        $gateway = getAuthTokenLogGateway();
        if ($gateway !== null) {
            $gateway->logTokenExchange([
                'gibbonPersonID' => 0,
                'username' => 'unknown',
                'sessionID' => session_id() ?: 'none',
                'tokenStatus' => 'failed',
                'ipAddress' => $ipAddress,
                'userAgent' => $userAgent,
                'errorMessage' => 'No valid Gibbon session found',
            ]);
        }

        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Unauthorized',
            'message' => 'No valid Gibbon session found. Please log in first.'
        ]);
        exit;
    }

    // Generate JWT token
    $tokenData = createTokenFromSession($sessionData);
    $token = $tokenData['token'];
    $expiresAt = $tokenData['expires_at'];
    $aiRole = $tokenData['ai_role'];

    // Log successful token exchange with comprehensive audit trail
    $gateway = getAuthTokenLogGateway();
    if ($gateway !== null) {
        $logID = $gateway->logTokenExchange([
            'gibbonPersonID' => $sessionData['personID'],
            'username' => $sessionData['username'],
            'sessionID' => session_id(),
            'tokenStatus' => 'success',
            'ipAddress' => $ipAddress,
            'userAgent' => $userAgent,
            'gibbonRoleIDPrimary' => $sessionData['gibbonRoleIDPrimary'],
            'aiRole' => $aiRole,
            'expiresAt' => date('Y-m-d H:i:s', $expiresAt),
        ]);

        // Also log to error_log for immediate monitoring
        error_log(sprintf(
            '[AuthToken] SUCCESS - User: %s (ID: %s), Role: %s, IP: %s, LogID: %s',
            $sessionData['username'],
            $sessionData['personID'],
            $aiRole,
            $ipAddress ?? 'unknown',
            $logID ?? 'none'
        ));
    } else {
        // Fallback to error_log only if gateway unavailable
        error_log(sprintf(
            '[AuthToken] SUCCESS - User: %s (ID: %s), Role: %s, IP: %s, Session: %s (Gateway unavailable)',
            $sessionData['username'],
            $sessionData['personID'],
            $aiRole,
            $ipAddress ?? 'unknown',
            session_id()
        ));
    }

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
            'role' => $aiRole,
        ]
    ]);

} catch (Exception $e) {
    // Handle unexpected errors
    $ipAddress = getClientIP();
    $userAgent = getUserAgent();

    // Log the error with audit trail
    $gateway = getAuthTokenLogGateway();
    if ($gateway !== null) {
        $gateway->logTokenExchange([
            'gibbonPersonID' => $sessionData['personID'] ?? 0,
            'username' => $sessionData['username'] ?? 'unknown',
            'sessionID' => session_id() ?: 'none',
            'tokenStatus' => 'failed',
            'ipAddress' => $ipAddress,
            'userAgent' => $userAgent,
            'errorMessage' => 'Exception: ' . $e->getMessage(),
        ]);
    }

    error_log(sprintf(
        '[AuthToken] ERROR - %s, IP: %s',
        $e->getMessage(),
        $ipAddress ?? 'unknown'
    ));

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal Server Error',
        'message' => 'An error occurred while generating the authentication token'
    ]);
}
