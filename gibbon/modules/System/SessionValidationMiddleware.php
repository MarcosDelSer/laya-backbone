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
 * Session Validation Middleware
 *
 * Provides centralized session validation for Gibbon modules and endpoints.
 * This middleware ensures consistent session checking across the application
 * and provides structured error responses.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */

namespace Gibbon\Module\System;

/**
 * SessionValidationMiddleware class
 *
 * Handles validation of Gibbon PHP sessions with comprehensive error handling
 * and logging capabilities.
 */
class SessionValidationMiddleware
{
    /**
     * Session validation result codes
     */
    const SESSION_VALID = 'valid';
    const SESSION_NOT_STARTED = 'not_started';
    const SESSION_NO_USER = 'no_user';
    const SESSION_INACTIVE = 'inactive';
    const SESSION_EXPIRED = 'expired';
    const SESSION_INVALID_STATUS = 'invalid_status';

    /**
     * Default session timeout in seconds (30 minutes)
     */
    const DEFAULT_TIMEOUT = 1800;

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
     *   - timeout: Session timeout in seconds (default: 1800)
     *   - enableLogging: Enable audit logging (default: true)
     *   - requireStatus: Require specific user status (default: 'Full')
     *   - allowedStatuses: Array of allowed user statuses (default: ['Full'])
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'timeout' => self::DEFAULT_TIMEOUT,
            'enableLogging' => true,
            'requireStatus' => true,
            'allowedStatuses' => ['Full'],
        ], $config);

        $this->enableLogging = $this->config['enableLogging'];
    }

    /**
     * Validate the current session
     *
     * @return array Validation result with status and data
     *   - status: Validation status code
     *   - valid: Boolean indicating if session is valid
     *   - data: Session data if valid, null otherwise
     *   - message: Human-readable message
     */
    public function validate(): array
    {
        // Check if session is started
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return $this->buildResponse(
                self::SESSION_NOT_STARTED,
                false,
                null,
                'No active PHP session found'
            );
        }

        // Check for required session variables
        if (!isset($_SESSION['gibbonPersonID']) || empty($_SESSION['gibbonPersonID'])) {
            return $this->buildResponse(
                self::SESSION_NO_USER,
                false,
                null,
                'No authenticated user in session'
            );
        }

        // Extract session data
        $sessionData = $this->extractSessionData();

        // Validate user status if required
        if ($this->config['requireStatus']) {
            $userStatus = $sessionData['status'] ?? '';
            if (!in_array($userStatus, $this->config['allowedStatuses'], true)) {
                return $this->buildResponse(
                    self::SESSION_INVALID_STATUS,
                    false,
                    null,
                    "User status '{$userStatus}' is not allowed"
                );
            }
        }

        // Check session timeout if last activity is tracked
        if (isset($_SESSION['lastActivityTime'])) {
            $inactiveTime = time() - $_SESSION['lastActivityTime'];
            if ($inactiveTime > $this->config['timeout']) {
                return $this->buildResponse(
                    self::SESSION_EXPIRED,
                    false,
                    null,
                    'Session has expired due to inactivity'
                );
            }
        }

        // Update last activity time
        $_SESSION['lastActivityTime'] = time();

        // Log successful validation if enabled
        if ($this->enableLogging) {
            $this->log(
                'Session validated successfully',
                $sessionData['personID'],
                $sessionData['username']
            );
        }

        // Session is valid
        return $this->buildResponse(
            self::SESSION_VALID,
            true,
            $sessionData,
            'Session is valid'
        );
    }

    /**
     * Validate session and return data or throw exception
     *
     * @return array Session data
     * @throws \RuntimeException If session is invalid
     */
    public function validateOrFail(): array
    {
        $result = $this->validate();

        if (!$result['valid']) {
            throw new \RuntimeException(
                $result['message'],
                $this->getStatusCode($result['status'])
            );
        }

        return $result['data'];
    }

    /**
     * Validate session and send JSON error response if invalid
     *
     * @param int $httpCode HTTP status code for error (default: 401)
     * @return array|null Session data if valid, null if invalid (response sent)
     */
    public function validateOrRespond(int $httpCode = 401): ?array
    {
        $result = $this->validate();

        if (!$result['valid']) {
            $this->sendJsonError($result, $httpCode);
            return null;
        }

        return $result['data'];
    }

    /**
     * Check if a session is valid without updating activity time
     *
     * @return bool True if session is valid
     */
    public function isValid(): bool
    {
        // Temporarily disable activity time updates
        $lastActivity = $_SESSION['lastActivityTime'] ?? null;

        $result = $this->validate();

        // Restore original last activity time
        if ($lastActivity !== null) {
            $_SESSION['lastActivityTime'] = $lastActivity;
        }

        return $result['valid'];
    }

    /**
     * Extract session data into a structured array
     *
     * @return array Session data
     */
    private function extractSessionData(): array
    {
        return [
            'personID' => $_SESSION['gibbonPersonID'] ?? '',
            'username' => $_SESSION['username'] ?? '',
            'email' => $_SESSION['email'] ?? '',
            'gibbonRoleIDPrimary' => $_SESSION['gibbonRoleIDPrimary'] ?? '',
            'surname' => $_SESSION['surname'] ?? '',
            'firstName' => $_SESSION['firstName'] ?? '',
            'preferredName' => $_SESSION['preferredName'] ?? '',
            'status' => $_SESSION['status'] ?? 'Full',
            'sessionID' => session_id(),
            'lastActivity' => $_SESSION['lastActivityTime'] ?? null,
        ];
    }

    /**
     * Build a validation response
     *
     * @param string $status Status code
     * @param bool $valid Whether session is valid
     * @param array|null $data Session data
     * @param string $message Human-readable message
     * @return array Response array
     */
    private function buildResponse(
        string $status,
        bool $valid,
        ?array $data,
        string $message
    ): array {
        return [
            'status' => $status,
            'valid' => $valid,
            'data' => $data,
            'message' => $message,
        ];
    }

    /**
     * Get HTTP status code for validation status
     *
     * @param string $status Validation status
     * @return int HTTP status code
     */
    private function getStatusCode(string $status): int
    {
        $statusCodes = [
            self::SESSION_VALID => 200,
            self::SESSION_NOT_STARTED => 401,
            self::SESSION_NO_USER => 401,
            self::SESSION_INACTIVE => 401,
            self::SESSION_EXPIRED => 401,
            self::SESSION_INVALID_STATUS => 403,
        ];

        return $statusCodes[$status] ?? 401;
    }

    /**
     * Send JSON error response and exit
     *
     * @param array $result Validation result
     * @param int $httpCode HTTP status code
     * @return void
     */
    private function sendJsonError(array $result, int $httpCode): void
    {
        http_response_code($httpCode);
        header('Content-Type: application/json');

        echo json_encode([
            'success' => false,
            'error' => $this->getErrorType($result['status']),
            'message' => $result['message'],
            'code' => $result['status'],
        ]);

        exit;
    }

    /**
     * Get error type from status code
     *
     * @param string $status Status code
     * @return string Error type
     */
    private function getErrorType(string $status): string
    {
        $errorTypes = [
            self::SESSION_NOT_STARTED => 'Session Not Active',
            self::SESSION_NO_USER => 'Unauthorized',
            self::SESSION_INACTIVE => 'Session Inactive',
            self::SESSION_EXPIRED => 'Session Expired',
            self::SESSION_INVALID_STATUS => 'Forbidden',
        ];

        return $errorTypes[$status] ?? 'Authentication Error';
    }

    /**
     * Log validation event
     *
     * @param string $message Log message
     * @param string $personID Person ID
     * @param string $username Username
     * @return void
     */
    private function log(string $message, string $personID = '', string $username = ''): void
    {
        $logMessage = sprintf(
            '[SessionValidation] %s - User: %s (ID: %s) - Session: %s',
            $message,
            $username,
            $personID,
            session_id()
        );

        error_log($logMessage);
    }

    /**
     * Destroy the current session
     *
     * @return void
     */
    public function destroy(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $sessionData = $this->extractSessionData();

            if ($this->enableLogging) {
                $this->log(
                    'Session destroyed',
                    $sessionData['personID'] ?? '',
                    $sessionData['username'] ?? ''
                );
            }

            session_destroy();
        }
    }

    /**
     * Refresh the session ID to prevent fixation attacks
     *
     * @param bool $deleteOldSession Delete the old session file
     * @return bool True on success
     */
    public function regenerateId(bool $deleteOldSession = true): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }

        $oldSessionId = session_id();
        $result = session_regenerate_id($deleteOldSession);

        if ($result && $this->enableLogging) {
            $sessionData = $this->extractSessionData();
            $this->log(
                "Session ID regenerated from {$oldSessionId} to " . session_id(),
                $sessionData['personID'] ?? '',
                $sessionData['username'] ?? ''
            );
        }

        return $result;
    }
}
