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
 * CSRF Token Manager
 *
 * Provides Cross-Site Request Forgery (CSRF) protection for Gibbon forms
 * and state-changing operations. This class generates, stores, and validates
 * CSRF tokens to prevent unauthorized actions on behalf of authenticated users.
 *
 * Features:
 * - Secure token generation using cryptographically secure random bytes
 * - Session-based token storage
 * - Timing-attack resistant validation using hash_equals()
 * - Token expiration and rotation
 * - Comprehensive audit logging
 * - Support for per-form tokens and global tokens
 *
 * Security Considerations:
 * - Tokens are 64 characters (32 bytes hex-encoded) for strong entropy
 * - Uses hash_equals() to prevent timing attacks during validation
 * - Tokens expire with the session or after configurable timeout
 * - Supports token rotation after successful use
 *
 * @version v1.0.00
 * @since   v1.0.00
 */

namespace Gibbon\Security;

/**
 * CsrfTokenManager class
 *
 * Manages CSRF token lifecycle including generation, validation, and expiration.
 */
class CsrfTokenManager
{
    /**
     * Session key for storing CSRF tokens
     */
    const SESSION_TOKEN_KEY = 'csrf_token';
    const SESSION_TOKENS_KEY = 'csrf_tokens'; // For per-form tokens
    const SESSION_TOKEN_TIME_KEY = 'csrf_token_time';

    /**
     * Default token expiration time in seconds (2 hours)
     */
    const DEFAULT_TOKEN_EXPIRATION = 7200;

    /**
     * Maximum number of per-form tokens to store
     */
    const MAX_STORED_TOKENS = 10;

    /**
     * @var array Configuration options
     */
    private $config;

    /**
     * @var bool Enable logging
     */
    private $enableLogging;

    /**
     * Constructor
     *
     * @param array $config Configuration options
     *   - tokenExpiration: Token expiration time in seconds (default: 7200)
     *   - enableLogging: Enable audit logging (default: true)
     *   - rotateOnUse: Rotate token after successful validation (default: false)
     *   - perFormTokens: Use per-form tokens instead of global (default: false)
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'tokenExpiration' => self::DEFAULT_TOKEN_EXPIRATION,
            'enableLogging' => true,
            'rotateOnUse' => false,
            'perFormTokens' => false,
        ], $config);

        $this->enableLogging = $this->config['enableLogging'];
    }

    /**
     * Generate a new CSRF token
     *
     * Generates a cryptographically secure random token and stores it
     * in the session. If per-form tokens are enabled, a form identifier
     * can be provided to generate a unique token for that form.
     *
     * @param string|null $formId Optional form identifier for per-form tokens
     * @return string The generated CSRF token
     * @throws \RuntimeException If session is not active or token generation fails
     */
    public function generateToken(?string $formId = null): string
    {
        // Ensure session is active
        $this->ensureSessionActive();

        try {
            // Generate cryptographically secure random token (32 bytes = 64 hex chars)
            $token = bin2hex(random_bytes(32));
        } catch (\Exception $e) {
            $this->log('Failed to generate CSRF token: ' . $e->getMessage(), 'error');
            throw new \RuntimeException('Failed to generate CSRF token', 0, $e);
        }

        $currentTime = time();

        if ($this->config['perFormTokens'] && $formId !== null) {
            // Store per-form token
            $this->storePerFormToken($formId, $token, $currentTime);
            $this->log("Generated CSRF token for form: {$formId}");
        } else {
            // Store global token
            $_SESSION[self::SESSION_TOKEN_KEY] = $token;
            $_SESSION[self::SESSION_TOKEN_TIME_KEY] = $currentTime;
            $this->log('Generated global CSRF token');
        }

        return $token;
    }

    /**
     * Get the current CSRF token
     *
     * Returns the existing token or generates a new one if none exists.
     *
     * @param string|null $formId Optional form identifier for per-form tokens
     * @return string The CSRF token
     */
    public function getToken(?string $formId = null): string
    {
        $this->ensureSessionActive();

        if ($this->config['perFormTokens'] && $formId !== null) {
            // Get per-form token
            $token = $this->getPerFormToken($formId);
            if ($token !== null && !$this->isTokenExpired(self::SESSION_TOKENS_KEY, $formId)) {
                return $token;
            }
        } else {
            // Get global token
            if (isset($_SESSION[self::SESSION_TOKEN_KEY]) && !$this->isTokenExpired()) {
                return $_SESSION[self::SESSION_TOKEN_KEY];
            }
        }

        // Generate new token if none exists or expired
        return $this->generateToken($formId);
    }

    /**
     * Validate a CSRF token
     *
     * Validates the provided token against the stored token using timing-attack
     * resistant comparison. Optionally rotates the token after successful validation.
     *
     * @param string $token The token to validate
     * @param string|null $formId Optional form identifier for per-form tokens
     * @return bool True if token is valid, false otherwise
     */
    public function validateToken(string $token, ?string $formId = null): bool
    {
        $this->ensureSessionActive();

        // Get the stored token
        if ($this->config['perFormTokens'] && $formId !== null) {
            $storedToken = $this->getPerFormToken($formId);
            $isExpired = $this->isTokenExpired(self::SESSION_TOKENS_KEY, $formId);
        } else {
            $storedToken = $_SESSION[self::SESSION_TOKEN_KEY] ?? null;
            $isExpired = $this->isTokenExpired();
        }

        // Validate token exists
        if ($storedToken === null || empty($storedToken)) {
            $this->log('CSRF validation failed: No stored token found', 'warning');
            return false;
        }

        // Check expiration
        if ($isExpired) {
            $this->log('CSRF validation failed: Token expired', 'warning');
            return false;
        }

        // Validate token using timing-attack resistant comparison
        if (!hash_equals($storedToken, $token)) {
            $this->log('CSRF validation failed: Token mismatch', 'warning');
            return false;
        }

        $this->log('CSRF token validated successfully');

        // Rotate token if configured
        if ($this->config['rotateOnUse']) {
            $this->generateToken($formId);
            $this->log('CSRF token rotated after successful validation');
        }

        return true;
    }

    /**
     * Validate token or throw exception
     *
     * @param string $token The token to validate
     * @param string|null $formId Optional form identifier
     * @return bool True if valid
     * @throws \RuntimeException If token is invalid
     */
    public function validateOrFail(string $token, ?string $formId = null): bool
    {
        if (!$this->validateToken($token, $formId)) {
            throw new \RuntimeException('CSRF token validation failed', 403);
        }

        return true;
    }

    /**
     * Invalidate the current token
     *
     * Removes the token from the session, forcing generation of a new token
     * on next request.
     *
     * @param string|null $formId Optional form identifier for per-form tokens
     * @return void
     */
    public function invalidateToken(?string $formId = null): void
    {
        $this->ensureSessionActive();

        if ($this->config['perFormTokens'] && $formId !== null) {
            if (isset($_SESSION[self::SESSION_TOKENS_KEY][$formId])) {
                unset($_SESSION[self::SESSION_TOKENS_KEY][$formId]);
                $this->log("Invalidated CSRF token for form: {$formId}");
            }
        } else {
            unset($_SESSION[self::SESSION_TOKEN_KEY]);
            unset($_SESSION[self::SESSION_TOKEN_TIME_KEY]);
            $this->log('Invalidated global CSRF token');
        }
    }

    /**
     * Clean up expired tokens
     *
     * Removes all expired tokens from the session to prevent memory bloat.
     *
     * @return int Number of tokens cleaned up
     */
    public function cleanupExpiredTokens(): int
    {
        $this->ensureSessionActive();

        if (!isset($_SESSION[self::SESSION_TOKENS_KEY])) {
            return 0;
        }

        $cleaned = 0;
        $currentTime = time();
        $expiration = $this->config['tokenExpiration'];

        foreach ($_SESSION[self::SESSION_TOKENS_KEY] as $formId => $tokenData) {
            $tokenTime = $tokenData['time'] ?? 0;
            if ($currentTime - $tokenTime > $expiration) {
                unset($_SESSION[self::SESSION_TOKENS_KEY][$formId]);
                $cleaned++;
            }
        }

        if ($cleaned > 0) {
            $this->log("Cleaned up {$cleaned} expired CSRF tokens");
        }

        return $cleaned;
    }

    /**
     * Get HTML input field for CSRF token
     *
     * Generates an HTML hidden input field containing the CSRF token
     * for easy inclusion in forms.
     *
     * @param string|null $formId Optional form identifier
     * @return string HTML input field
     */
    public function getTokenField(?string $formId = null): string
    {
        $token = $this->getToken($formId);
        $escapedToken = htmlspecialchars($token, ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="csrf_token" value="' . $escapedToken . '">';
    }

    /**
     * Store a per-form token
     *
     * @param string $formId Form identifier
     * @param string $token Token value
     * @param int $time Timestamp
     * @return void
     */
    private function storePerFormToken(string $formId, string $token, int $time): void
    {
        if (!isset($_SESSION[self::SESSION_TOKENS_KEY])) {
            $_SESSION[self::SESSION_TOKENS_KEY] = [];
        }

        // Clean up old tokens if limit exceeded
        if (count($_SESSION[self::SESSION_TOKENS_KEY]) >= self::MAX_STORED_TOKENS) {
            $this->cleanupExpiredTokens();

            // If still at limit, remove oldest token
            if (count($_SESSION[self::SESSION_TOKENS_KEY]) >= self::MAX_STORED_TOKENS) {
                $oldestFormId = array_key_first($_SESSION[self::SESSION_TOKENS_KEY]);
                unset($_SESSION[self::SESSION_TOKENS_KEY][$oldestFormId]);
            }
        }

        $_SESSION[self::SESSION_TOKENS_KEY][$formId] = [
            'token' => $token,
            'time' => $time,
        ];
    }

    /**
     * Get a per-form token
     *
     * @param string $formId Form identifier
     * @return string|null Token value or null if not found
     */
    private function getPerFormToken(string $formId): ?string
    {
        if (!isset($_SESSION[self::SESSION_TOKENS_KEY][$formId])) {
            return null;
        }

        return $_SESSION[self::SESSION_TOKENS_KEY][$formId]['token'] ?? null;
    }

    /**
     * Check if a token is expired
     *
     * @param string $tokenKey Session key for token storage
     * @param string|null $formId Optional form identifier
     * @return bool True if expired, false otherwise
     */
    private function isTokenExpired(string $tokenKey = self::SESSION_TOKEN_KEY, ?string $formId = null): bool
    {
        $currentTime = time();
        $expiration = $this->config['tokenExpiration'];

        if ($tokenKey === self::SESSION_TOKENS_KEY && $formId !== null) {
            if (!isset($_SESSION[self::SESSION_TOKENS_KEY][$formId])) {
                return true;
            }
            $tokenTime = $_SESSION[self::SESSION_TOKENS_KEY][$formId]['time'] ?? 0;
        } else {
            if (!isset($_SESSION[self::SESSION_TOKEN_TIME_KEY])) {
                return true;
            }
            $tokenTime = $_SESSION[self::SESSION_TOKEN_TIME_KEY];
        }

        return ($currentTime - $tokenTime) > $expiration;
    }

    /**
     * Ensure session is active
     *
     * @return void
     * @throws \RuntimeException If session is not active
     */
    private function ensureSessionActive(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new \RuntimeException('Session must be active to use CSRF protection');
        }
    }

    /**
     * Log CSRF event
     *
     * @param string $message Log message
     * @param string $level Log level (info, warning, error)
     * @return void
     */
    private function log(string $message, string $level = 'info'): void
    {
        if (!$this->enableLogging) {
            return;
        }

        $logMessage = sprintf(
            '[CsrfTokenManager] [%s] %s - Session: %s - IP: %s',
            strtoupper($level),
            $message,
            session_id(),
            $this->getClientIP()
        );

        error_log($logMessage);
    }

    /**
     * Get client IP address
     *
     * @return string Client IP address or 'unknown'
     */
    private function getClientIP(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',  // Proxy/Load Balancer
            'HTTP_X_REAL_IP',        // Nginx
            'REMOTE_ADDR',           // Direct connection
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                // Handle comma-separated list (X-Forwarded-For)
                if (strpos($ip, ',') !== false) {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }
                return $ip;
            }
        }

        return 'unknown';
    }
}
