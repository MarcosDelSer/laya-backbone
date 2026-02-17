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
 * Gibbon Application Bootstrap
 *
 * This file serves as the main entry point for the Gibbon application.
 * It initializes core services including session management, CSRF protection,
 * and security middleware before routing requests to the appropriate modules.
 *
 * Security Features:
 * - Secure session initialization with SameSite=Strict cookies
 * - Automatic CSRF token generation and validation
 * - Security headers (X-Frame-Options, X-Content-Type-Options)
 * - Request validation and sanitization
 *
 * @version v1.0.00
 * @since   v1.0.00
 */

// Load Composer autoloader
require_once __DIR__ . '/vendor/autoload.php';

// Load configuration
require_once __DIR__ . '/config.php';

use Gibbon\Session\SessionManager;
use Gibbon\Security\CsrfTokenManager;
use Gibbon\Middleware\CsrfValidationMiddleware;

/**
 * Initialize Session Manager
 *
 * Starts the session with secure cookie parameters and automatically
 * generates a CSRF token for the session.
 */
$sessionConfig = [
    'sessionName' => 'GIBBON_SESSION',
    'cookieLifetime' => 0,  // Session cookie (expires when browser closes)
    'cookiePath' => '/',
    'cookieSecure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',  // Require HTTPS in production
    'cookieHttpOnly' => true,  // Prevent JavaScript access to cookies
    'cookieSameSite' => 'Strict',  // CSRF protection via SameSite cookies
    'autoInitCsrf' => true,  // Automatically generate CSRF token
];

$sessionManager = new SessionManager($sessionConfig);
$sessionManager->start();

/**
 * Set Security Headers
 *
 * Add security headers to all responses to protect against common
 * web vulnerabilities.
 */
header('X-Frame-Options: DENY');  // Prevent clickjacking
header('X-Content-Type-Options: nosniff');  // Prevent MIME sniffing
header('X-XSS-Protection: 1; mode=block');  // Enable XSS filter
header('Referrer-Policy: strict-origin-when-cross-origin');  // Control referrer information

/**
 * Initialize CSRF Token Manager
 *
 * Create the CSRF token manager with configuration options.
 */
$csrfConfig = [
    'tokenExpiration' => 7200,  // 2 hours
    'enableLogging' => true,
    'rotateOnUse' => false,  // Don't rotate on every use to avoid breaking multi-tab usage
    'perFormTokens' => false,  // Use global token for simplicity
];

$csrfManager = new CsrfTokenManager($csrfConfig);

/**
 * Register CSRF Validation Middleware
 *
 * The middleware automatically validates CSRF tokens for all state-changing
 * requests (POST, PUT, DELETE, PATCH). Configure exempt paths for API
 * endpoints or webhooks that use alternative authentication methods.
 */
$csrfMiddlewareConfig = [
    'enableLogging' => true,
    'exemptPaths' => [
        '/api/webhook/*',  // Webhooks use signature validation
        '/api/public/*',   // Public API endpoints (read-only)
    ],
    'tokenField' => 'csrf_token',
    'errorRedirect' => null,  // Return JSON error instead of redirecting
];

$csrfMiddleware = new CsrfValidationMiddleware($csrfManager, $csrfMiddlewareConfig);

/**
 * Execute CSRF Middleware
 *
 * Validate CSRF token for state-changing requests. If validation fails,
 * the middleware will terminate the request with a 403 Forbidden response.
 */
$csrfMiddleware->handle();

/**
 * Application Routing
 *
 * At this point, CSRF validation has passed (or was skipped for safe methods).
 * The application can now proceed to route the request to the appropriate
 * module or controller.
 *
 * Note: In a production Gibbon installation, this would include the actual
 * routing logic to determine which module and action to load based on the
 * request parameters (typically the 'q' query parameter).
 */

// Example routing logic (to be implemented based on existing Gibbon architecture):
// $route = $_GET['q'] ?? '/';
// $moduleLoader->loadModule($route);

/**
 * CSRF Token Access
 *
 * The CSRF token is now available in the session and can be accessed by
 * form builders and templates:
 *
 * In forms:
 *   $form->addHiddenValue('csrf_token', $_SESSION['csrf_token']);
 *
 * In templates:
 *   <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
 *
 * The token is automatically validated by the middleware for all POST/PUT/DELETE requests.
 */
