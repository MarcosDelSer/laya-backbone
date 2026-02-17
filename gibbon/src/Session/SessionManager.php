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
 * Session Manager
 *
 * Manages PHP session lifecycle including initialization, configuration,
 * and CSRF token setup. This class provides centralized session management
 * for the Gibbon platform with built-in security features.
 *
 * Features:
 * - Secure session initialization with proper cookie parameters
 * - Automatic CSRF token generation and initialization
 * - Session regeneration for security
 * - Session data management with get/set/has/remove methods
 * - Integration with CsrfTokenManager for CSRF protection
 * - Session validation and security checks
 *
 * Security Considerations:
 * - Uses secure, httponly, and SameSite cookie attributes
 * - Automatically generates CSRF tokens on session start
 * - Supports session regeneration to prevent fixation attacks
 * - Validates session state before operations
 *
 * @version v1.0.00
 * @since   v1.0.00
 */

namespace Gibbon\Session;

use Gibbon\Security\CsrfTokenManager;

/**
 * SessionManager class
 *
 * Manages session lifecycle and provides secure session operations.
 */
class SessionManager
{
    /**
     * @var CsrfTokenManager CSRF token manager instance
     */
    private $csrfTokenManager;

    /**
     * @var array Session configuration
     */
    private $config;

    /**
     * @var bool Whether session has been started
     */
    private $sessionStarted = false;

    /**
     * Constructor
     *
     * @param CsrfTokenManager|null $csrfTokenManager Optional CSRF token manager
     * @param array $config Session configuration options
     *   - sessionName: Custom session name (default: 'GIBBON_SESSION')
     *   - cookieLifetime: Cookie lifetime in seconds (default: 0 = until browser closes)
     *   - cookiePath: Cookie path (default: '/')
     *   - cookieDomain: Cookie domain (default: '')
     *   - cookieSecure: Require HTTPS (default: true)
     *   - cookieHttpOnly: HTTP only cookies (default: true)
     *   - cookieSameSite: SameSite attribute (default: 'Strict')
     *   - autoInitCsrf: Automatically initialize CSRF token (default: true)
     */
    public function __construct(?CsrfTokenManager $csrfTokenManager = null, array $config = [])
    {
        $this->csrfTokenManager = $csrfTokenManager ?? new CsrfTokenManager();

        $this->config = array_merge([
            'sessionName' => 'GIBBON_SESSION',
            'cookieLifetime' => 0,
            'cookiePath' => '/',
            'cookieDomain' => '',
            'cookieSecure' => true,
            'cookieHttpOnly' => true,
            'cookieSameSite' => 'Strict',
            'autoInitCsrf' => true,
        ], $config);
    }

    /**
     * Start the session
     *
     * Initializes the PHP session with secure cookie parameters and
     * automatically generates a CSRF token if configured.
     *
     * @return bool True if session started successfully, false if already started
     * @throws \RuntimeException If session start fails
     */
    public function start(): bool
    {
        // Check if session is already active
        if (session_status() === PHP_SESSION_ACTIVE) {
            $this->sessionStarted = true;
            return false;
        }

        // Set session name
        session_name($this->config['sessionName']);

        // Configure session cookie parameters
        session_set_cookie_params([
            'lifetime' => $this->config['cookieLifetime'],
            'path' => $this->config['cookiePath'],
            'domain' => $this->config['cookieDomain'],
            'secure' => $this->config['cookieSecure'],
            'httponly' => $this->config['cookieHttpOnly'],
            'samesite' => $this->config['cookieSameSite'],
        ]);

        // Start the session
        if (!session_start()) {
            throw new \RuntimeException('Failed to start session');
        }

        $this->sessionStarted = true;

        // Initialize CSRF token if enabled
        if ($this->config['autoInitCsrf']) {
            $this->initializeCsrfToken();
        }

        return true;
    }

    /**
     * Initialize CSRF token
     *
     * Generates a new CSRF token if one doesn't exist in the session.
     * This method is automatically called on session start if autoInitCsrf
     * is enabled in the configuration.
     *
     * @return string The CSRF token
     */
    public function initializeCsrfToken(): string
    {
        $this->ensureSessionActive();

        // Check if token already exists
        if (isset($_SESSION['csrf_token']) && !empty($_SESSION['csrf_token'])) {
            return $_SESSION['csrf_token'];
        }

        // Generate new token
        $token = $this->csrfTokenManager->generateToken();

        // Store token in session
        $_SESSION['csrf_token'] = $token;

        return $token;
    }

    /**
     * Get CSRF token
     *
     * Returns the current CSRF token or generates a new one if none exists.
     *
     * @param string|null $formId Optional form identifier for per-form tokens
     * @return string The CSRF token
     */
    public function getCsrfToken(?string $formId = null): string
    {
        $this->ensureSessionActive();
        return $this->csrfTokenManager->getToken($formId);
    }

    /**
     * Validate CSRF token
     *
     * Validates a CSRF token against the stored token.
     *
     * @param string $token The token to validate
     * @param string|null $formId Optional form identifier
     * @return bool True if valid, false otherwise
     */
    public function validateCsrfToken(string $token, ?string $formId = null): bool
    {
        $this->ensureSessionActive();
        return $this->csrfTokenManager->validateToken($token, $formId);
    }

    /**
     * Regenerate session ID
     *
     * Regenerates the session ID to prevent session fixation attacks.
     * The CSRF token is preserved during regeneration.
     *
     * @param bool $deleteOldSession Whether to delete the old session file
     * @return bool True on success
     */
    public function regenerate(bool $deleteOldSession = true): bool
    {
        $this->ensureSessionActive();

        return session_regenerate_id($deleteOldSession);
    }

    /**
     * Get a value from the session
     *
     * @param string $key The session key
     * @param mixed $default Default value if key doesn't exist
     * @return mixed The session value or default
     */
    public function get(string $key, $default = null)
    {
        $this->ensureSessionActive();

        return $_SESSION[$key] ?? $default;
    }

    /**
     * Set a value in the session
     *
     * @param string $key The session key
     * @param mixed $value The value to store
     * @return void
     */
    public function set(string $key, $value): void
    {
        $this->ensureSessionActive();

        $_SESSION[$key] = $value;
    }

    /**
     * Check if a key exists in the session
     *
     * @param string $key The session key
     * @return bool True if key exists, false otherwise
     */
    public function has(string $key): bool
    {
        $this->ensureSessionActive();

        return isset($_SESSION[$key]);
    }

    /**
     * Remove a value from the session
     *
     * @param string $key The session key to remove
     * @return void
     */
    public function remove(string $key): void
    {
        $this->ensureSessionActive();

        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
        }
    }

    /**
     * Clear all session data
     *
     * Removes all session variables but keeps the session active.
     *
     * @return void
     */
    public function clear(): void
    {
        $this->ensureSessionActive();

        $_SESSION = [];
    }

    /**
     * Destroy the session
     *
     * Completely destroys the session including cookies and session file.
     *
     * @return bool True on success
     */
    public function destroy(): bool
    {
        $this->ensureSessionActive();

        // Clear session data
        $_SESSION = [];

        // Delete session cookie
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        // Destroy session file
        $result = session_destroy();
        $this->sessionStarted = false;

        return $result;
    }

    /**
     * Get the session ID
     *
     * @return string The current session ID
     */
    public function getId(): string
    {
        return session_id();
    }

    /**
     * Check if session is active
     *
     * @return bool True if session is active, false otherwise
     */
    public function isActive(): bool
    {
        return session_status() === PHP_SESSION_ACTIVE;
    }

    /**
     * Get all session data
     *
     * @return array All session data
     */
    public function all(): array
    {
        $this->ensureSessionActive();

        return $_SESSION;
    }

    /**
     * Ensure session is active
     *
     * @return void
     * @throws \RuntimeException If session is not active
     */
    private function ensureSessionActive(): void
    {
        if (!$this->isActive()) {
            throw new \RuntimeException('Session is not active. Call start() first.');
        }
    }

    /**
     * Get the CSRF token manager
     *
     * @return CsrfTokenManager
     */
    public function getCsrfTokenManager(): CsrfTokenManager
    {
        return $this->csrfTokenManager;
    }
}
