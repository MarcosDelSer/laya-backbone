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
 * CSRF Validation Middleware
 *
 * This middleware provides automatic Cross-Site Request Forgery (CSRF) protection
 * for all state-changing HTTP requests (POST, PUT, DELETE, PATCH). It validates
 * CSRF tokens on incoming requests to prevent unauthorized actions on behalf of
 * authenticated users.
 *
 * Features:
 * - Automatic CSRF validation for state-changing requests
 * - Configurable exemption paths for special routes (API endpoints, webhooks)
 * - Comprehensive audit logging with IP tracking and user agent detection
 * - Integration with CsrfTokenManager for token validation
 * - User-friendly error responses with proper HTTP status codes
 * - Security event monitoring and alerting
 *
 * Security Considerations:
 * - Only validates POST, PUT, DELETE, PATCH requests (GET/HEAD/OPTIONS allowed)
 * - Provides exemption mechanism for trusted endpoints (use sparingly)
 * - Logs all validation failures for security monitoring
 * - Returns 403 Forbidden on validation failure
 * - Includes request context (IP, user agent, endpoint) in audit logs
 *
 * Usage:
 * ```php
 * // In index.php or bootstrap file
 * $csrfMiddleware = new CsrfValidationMiddleware($container, [
 *     'enableLogging' => true,
 *     'exemptPaths' => ['/api/webhook', '/api/public']
 * ]);
 * $csrfMiddleware->handle();
 * ```
 *
 * @version v1.0.00
 * @since   v1.0.00
 */

namespace Gibbon\Middleware;

use Gibbon\Security\CsrfTokenManager;

/**
 * CsrfValidationMiddleware class
 *
 * Validates CSRF tokens for all state-changing HTTP requests to prevent
 * Cross-Site Request Forgery attacks.
 */
class CsrfValidationMiddleware
{
    /**
     * HTTP methods that require CSRF validation
     */
    const PROTECTED_METHODS = ['POST', 'PUT', 'DELETE', 'PATCH'];

    /**
     * @var CsrfTokenManager CSRF token manager instance
     */
    private $csrfManager;

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
     * @param CsrfTokenManager|null $csrfManager CSRF token manager instance
     * @param array $config Configuration options
     *   - enableLogging: Enable audit logging (default: true)
     *   - exemptPaths: Array of paths to exempt from CSRF validation (default: [])
     *   - tokenField: Name of the CSRF token field (default: 'csrf_token')
     *   - errorRedirect: URL to redirect on error (default: null)
     */
    public function __construct(?CsrfTokenManager $csrfManager = null, array $config = [])
    {
        $this->csrfManager = $csrfManager ?? new CsrfTokenManager();

        $this->config = array_merge([
            'enableLogging' => true,
            'exemptPaths' => [],
            'tokenField' => 'csrf_token',
            'errorRedirect' => null,
        ], $config);

        $this->enableLogging = $this->config['enableLogging'];
    }

    /**
     * Handle the incoming request
     *
     * Validates CSRF token for state-changing requests. If validation fails,
     * logs the security event and returns a 403 Forbidden response.
     *
     * @return void Exits with error response if validation fails
     */
    public function handle(): void
    {
        $method = $this->getRequestMethod();

        // Skip validation for safe methods (GET, HEAD, OPTIONS)
        if (!in_array($method, self::PROTECTED_METHODS)) {
            return;
        }

        // Get current request path
        $requestPath = $this->getRequestPath();

        // Check if path is exempt from CSRF validation
        if ($this->isExemptPath($requestPath)) {
            $this->log("CSRF validation skipped for exempt path: {$requestPath}");
            return;
        }

        // Get CSRF token from request
        $token = $this->getTokenFromRequest();

        // Validate token
        if (!$this->validateCsrfToken($token)) {
            $this->handleValidationFailure($requestPath, $method);
        }

        // Log successful validation
        $this->log("CSRF validation successful for {$method} {$requestPath}");
    }

    /**
     * Validate CSRF token
     *
     * @param string|null $token Token to validate
     * @return bool True if token is valid, false otherwise
     */
    private function validateCsrfToken(?string $token): bool
    {
        // Token must be present
        if ($token === null || empty($token)) {
            $this->log('CSRF validation failed: No token provided', 'warning');
            return false;
        }

        // Validate using CsrfTokenManager
        try {
            return $this->csrfManager->validateToken($token);
        } catch (\Exception $e) {
            $this->log('CSRF validation failed: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Get CSRF token from request
     *
     * Checks POST data, then falls back to headers for API requests.
     *
     * @return string|null CSRF token or null if not found
     */
    private function getTokenFromRequest(): ?string
    {
        $tokenField = $this->config['tokenField'];

        // Check POST data first
        if (isset($_POST[$tokenField])) {
            return $_POST[$tokenField];
        }

        // Check headers for API requests
        $headerName = 'HTTP_X_CSRF_TOKEN';
        if (isset($_SERVER[$headerName])) {
            return $_SERVER[$headerName];
        }

        return null;
    }

    /**
     * Get the current request method
     *
     * @return string HTTP request method (e.g., 'GET', 'POST')
     */
    private function getRequestMethod(): string
    {
        return $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }

    /**
     * Get the current request path
     *
     * @return string Request path without query string
     */
    private function getRequestPath(): string
    {
        $path = $_SERVER['REQUEST_URI'] ?? '/';

        // Remove query string
        if (($pos = strpos($path, '?')) !== false) {
            $path = substr($path, 0, $pos);
        }

        return $path;
    }

    /**
     * Check if the current path is exempt from CSRF validation
     *
     * @param string $path Request path to check
     * @return bool True if path is exempt, false otherwise
     */
    private function isExemptPath(string $path): bool
    {
        $exemptPaths = $this->config['exemptPaths'];

        foreach ($exemptPaths as $exemptPath) {
            // Support wildcard matching
            if (fnmatch($exemptPath, $path)) {
                return true;
            }

            // Support prefix matching
            if (strpos($path, $exemptPath) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Handle CSRF validation failure
     *
     * Logs the security event and returns an error response to the client.
     *
     * @param string $path Request path
     * @param string $method HTTP method
     * @return void Exits with error response
     */
    private function handleValidationFailure(string $path, string $method): void
    {
        // Log security event with full context
        $this->log(
            "CSRF validation failed for {$method} {$path}",
            'security',
            [
                'ip_address' => $this->getClientIP(),
                'user_agent' => $this->getUserAgent(),
                'session_id' => session_id(),
                'timestamp' => date('Y-m-d H:i:s'),
            ]
        );

        // Return error response
        if ($this->config['errorRedirect']) {
            // Redirect to error page
            header('Location: ' . $this->config['errorRedirect'] . '?error=csrf_validation_failed');
            exit;
        } else {
            // Return 403 Forbidden
            http_response_code(403);
            header('Content-Type: application/json');

            $errorResponse = [
                'error' => 'CSRF Validation Failed',
                'message' => 'The request could not be completed due to a security validation failure. Please refresh the page and try again.',
                'code' => 'CSRF_VALIDATION_FAILED',
            ];

            echo json_encode($errorResponse);
            exit;
        }
    }

    /**
     * Get client IP address
     *
     * Checks various headers to determine the real client IP address,
     * accounting for proxies and load balancers.
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

    /**
     * Get user agent string
     *
     * @return string User agent or 'unknown'
     */
    private function getUserAgent(): string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    }

    /**
     * Get the current endpoint/route
     *
     * @return string Request endpoint
     */
    private function getEndpoint(): string
    {
        return $this->getRequestMethod() . ' ' . $this->getRequestPath();
    }

    /**
     * Log CSRF middleware event
     *
     * Logs security events and validation results for audit purposes.
     *
     * @param string $message Log message
     * @param string $level Log level (info, warning, error, security)
     * @param array $context Additional context data
     * @return void
     */
    private function log(string $message, string $level = 'info', array $context = []): void
    {
        if (!$this->enableLogging) {
            return;
        }

        $contextString = '';
        if (!empty($context)) {
            $contextString = ' - Context: ' . json_encode($context);
        }

        $logMessage = sprintf(
            '[CsrfValidationMiddleware] [%s] %s - Session: %s - IP: %s - User-Agent: %s%s',
            strtoupper($level),
            $message,
            session_id(),
            $this->getClientIP(),
            $this->getUserAgent(),
            $contextString
        );

        error_log($logMessage);

        // For security events, also log to a dedicated security log if available
        if ($level === 'security') {
            $this->logSecurityEvent($message, $context);
        }
    }

    /**
     * Log security event to dedicated security log
     *
     * @param string $message Security event message
     * @param array $context Event context
     * @return void
     */
    private function logSecurityEvent(string $message, array $context): void
    {
        // This could be extended to log to a dedicated security monitoring system
        // For now, we'll just use error_log with a special prefix
        $securityLog = sprintf(
            '[SECURITY] [CSRF] %s - IP: %s - Session: %s - Timestamp: %s',
            $message,
            $context['ip_address'] ?? 'unknown',
            $context['session_id'] ?? 'unknown',
            $context['timestamp'] ?? date('Y-m-d H:i:s')
        );

        error_log($securityLog);
    }
}
