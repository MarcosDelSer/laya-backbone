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
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

/**
 * Sets the database connection information.
 * You can supply an optional $databasePort if your server requires one.
 */
$databaseServer = 'mysql';
$databaseUsername = 'gibbon';
$databasePassword = 'gibbon_password';
$databaseName = 'gibbon';

/**
 * Sets a globally unique id, to allow multiple installs on a single server.
 */
$guid = 'k0dgfjcg8-7bn-i76c-b5u4-bfc8k9mkw2p';

/**
 * Sets system-wide caching factor, used to balance performance and freshness.
 * Value represents number of page loads between cache refresh.
 * Must be positive integer. 1 means no caching.
 */
$caching = 10;

/**
 * Enables the System Admin > Impersonate User action, for testing and development.
 * This option is off by default. Access can be granted by adding usernames to
 * the list below. Allowed users must also have administrator access.
 */
$allowImpersonateUser = [];

/**
 * Session Cookie Security Configuration
 *
 * Configures secure session cookie parameters to protect against CSRF attacks
 * and session hijacking. These settings are used by the SessionManager.
 *
 * SameSite Attribute Options:
 * - 'Strict': Provides maximum CSRF protection. Cookies are only sent in
 *   first-party contexts (recommended for most applications)
 * - 'Lax': Allows cookies for top-level navigation from external sites
 * - 'None': No restrictions (requires Secure=true, not recommended)
 *
 * Security Best Practices:
 * - SameSite=Strict: Prevents CSRF attacks
 * - Secure=true: Requires HTTPS (auto-detected in production)
 * - HttpOnly=true: Prevents JavaScript access to cookies (XSS protection)
 * - Lifetime=0: Session cookie (expires when browser closes)
 */
$sessionCookieParams = [
    'samesite' => 'Strict',  // CSRF protection
    'secure' => true,         // Require HTTPS in production
    'httponly' => true,       // Prevent JavaScript access
    'lifetime' => 0,          // Session cookie (browser close)
    'path' => '/',            // Cookie available for entire domain
    'domain' => '',           // Current domain only
];
