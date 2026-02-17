<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Gibbon Core) and Gibbon LAYA are trademarks of Gibbon Education Ltd.

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
 * Health Check Endpoint
 *
 * Provides system health status information for monitoring and alerting.
 * Checks MySQL connection, PHP extensions, and disk space.
 * Returns JSON response for easy integration with monitoring systems.
 */

// Disable authentication for health checks
// This endpoint should be accessible without login for monitoring purposes
$_GET['noAuth'] = true;

// Set JSON response headers
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Initialize health check response
$health = [
    'status' => 'healthy',
    'timestamp' => date('c'),
    'checks' => [],
];

/**
 * Check MySQL Database Connection
 */
function checkDatabaseConnection($pdo) {
    try {
        if (!$pdo) {
            return [
                'status' => 'unhealthy',
                'message' => 'Database connection not available',
            ];
        }

        // Test connection with a simple query
        $stmt = $pdo->query('SELECT 1');
        if ($stmt === false) {
            return [
                'status' => 'unhealthy',
                'message' => 'Database query failed',
            ];
        }

        // Get database version
        $versionStmt = $pdo->query('SELECT VERSION() as version');
        $version = $versionStmt ? $versionStmt->fetch(PDO::FETCH_ASSOC)['version'] : 'unknown';

        // Get connection count
        $countStmt = $pdo->query('SHOW STATUS LIKE "Threads_connected"');
        $connections = $countStmt ? $countStmt->fetch(PDO::FETCH_ASSOC)['Value'] : 'unknown';

        return [
            'status' => 'healthy',
            'message' => 'Database connection successful',
            'details' => [
                'version' => $version,
                'connections' => $connections,
            ],
        ];
    } catch (PDOException $e) {
        return [
            'status' => 'unhealthy',
            'message' => 'Database connection error: ' . $e->getMessage(),
        ];
    } catch (Exception $e) {
        return [
            'status' => 'unhealthy',
            'message' => 'Database check error: ' . $e->getMessage(),
        ];
    }
}

/**
 * Check Required PHP Extensions
 */
function checkPHPExtensions() {
    $requiredExtensions = [
        'pdo',
        'pdo_mysql',
        'gd',
        'curl',
        'zip',
        'xml',
        'mbstring',
        'json',
        'openssl',
        'fileinfo',
    ];

    $missingExtensions = [];
    $loadedExtensions = [];

    foreach ($requiredExtensions as $extension) {
        if (extension_loaded($extension)) {
            $loadedExtensions[] = $extension;
        } else {
            $missingExtensions[] = $extension;
        }
    }

    if (empty($missingExtensions)) {
        return [
            'status' => 'healthy',
            'message' => 'All required PHP extensions loaded',
            'details' => [
                'php_version' => phpversion(),
                'loaded_extensions' => $loadedExtensions,
                'total_loaded' => count($loadedExtensions),
            ],
        ];
    } else {
        return [
            'status' => 'unhealthy',
            'message' => 'Missing required PHP extensions: ' . implode(', ', $missingExtensions),
            'details' => [
                'php_version' => phpversion(),
                'missing_extensions' => $missingExtensions,
                'loaded_extensions' => $loadedExtensions,
            ],
        ];
    }
}

/**
 * Check Disk Space
 */
function checkDiskSpace() {
    try {
        // Get absolute path to the Gibbon installation
        $installPath = realpath(__DIR__ . '/../../');

        if (!$installPath) {
            return [
                'status' => 'unhealthy',
                'message' => 'Unable to determine installation path',
            ];
        }

        // Get disk space information
        $totalSpace = disk_total_space($installPath);
        $freeSpace = disk_free_space($installPath);

        if ($totalSpace === false || $freeSpace === false) {
            return [
                'status' => 'unhealthy',
                'message' => 'Unable to retrieve disk space information',
            ];
        }

        $usedSpace = $totalSpace - $freeSpace;
        $usedPercent = ($usedSpace / $totalSpace) * 100;
        $freePercent = 100 - $usedPercent;

        // Warning threshold: 90% used
        // Critical threshold: 95% used
        $status = 'healthy';
        $message = 'Disk space is adequate';

        if ($usedPercent >= 95) {
            $status = 'critical';
            $message = 'Disk space critically low';
        } elseif ($usedPercent >= 90) {
            $status = 'warning';
            $message = 'Disk space running low';
        }

        return [
            'status' => $status,
            'message' => $message,
            'details' => [
                'total_bytes' => $totalSpace,
                'free_bytes' => $freeSpace,
                'used_bytes' => $usedSpace,
                'total_gb' => round($totalSpace / 1024 / 1024 / 1024, 2),
                'free_gb' => round($freeSpace / 1024 / 1024 / 1024, 2),
                'used_gb' => round($usedSpace / 1024 / 1024 / 1024, 2),
                'used_percent' => round($usedPercent, 2),
                'free_percent' => round($freePercent, 2),
            ],
        ];
    } catch (Exception $e) {
        return [
            'status' => 'unhealthy',
            'message' => 'Disk space check error: ' . $e->getMessage(),
        ];
    }
}

/**
 * Check Upload Directory Permissions
 */
function checkUploadDirectory() {
    try {
        $uploadPath = realpath(__DIR__ . '/../../uploads');

        if (!$uploadPath) {
            return [
                'status' => 'warning',
                'message' => 'Upload directory not found',
            ];
        }

        $isReadable = is_readable($uploadPath);
        $isWritable = is_writable($uploadPath);

        if (!$isReadable || !$isWritable) {
            return [
                'status' => 'unhealthy',
                'message' => 'Upload directory permissions incorrect',
                'details' => [
                    'path' => $uploadPath,
                    'readable' => $isReadable,
                    'writable' => $isWritable,
                ],
            ];
        }

        return [
            'status' => 'healthy',
            'message' => 'Upload directory accessible',
            'details' => [
                'path' => $uploadPath,
                'readable' => $isReadable,
                'writable' => $isWritable,
            ],
        ];
    } catch (Exception $e) {
        return [
            'status' => 'unhealthy',
            'message' => 'Upload directory check error: ' . $e->getMessage(),
        ];
    }
}

/**
 * Check Session Availability
 */
function checkSession() {
    try {
        // Check if sessions are enabled
        if (session_status() === PHP_SESSION_DISABLED) {
            return [
                'status' => 'unhealthy',
                'message' => 'PHP sessions are disabled',
            ];
        }

        // Check session save path
        $savePath = session_save_path();
        if (empty($savePath)) {
            return [
                'status' => 'warning',
                'message' => 'Session save path is empty',
                'details' => [
                    'save_path' => $savePath,
                ],
            ];
        }

        // Check if save path is writable
        if (!is_writable($savePath)) {
            return [
                'status' => 'unhealthy',
                'message' => 'Session save path is not writable',
                'details' => [
                    'save_path' => $savePath,
                    'writable' => false,
                ],
            ];
        }

        return [
            'status' => 'healthy',
            'message' => 'Session configuration is valid',
            'details' => [
                'save_path' => $savePath,
                'writable' => true,
            ],
        ];
    } catch (Exception $e) {
        return [
            'status' => 'unhealthy',
            'message' => 'Session check error: ' . $e->getMessage(),
        ];
    }
}

// Perform health checks
try {
    // Database connection check
    $health['checks']['database'] = checkDatabaseConnection($pdo ?? null);

    // PHP extensions check
    $health['checks']['php_extensions'] = checkPHPExtensions();

    // Disk space check
    $health['checks']['disk_space'] = checkDiskSpace();

    // Upload directory check
    $health['checks']['upload_directory'] = checkUploadDirectory();

    // Session check
    $health['checks']['session'] = checkSession();

    // Determine overall status
    // If any check is unhealthy, the overall status is unhealthy
    // If any check is critical, the overall status is critical
    // If any check is warning, the overall status is degraded
    $overallStatus = 'healthy';
    foreach ($health['checks'] as $checkName => $check) {
        if ($check['status'] === 'critical') {
            $overallStatus = 'critical';
            break;
        } elseif ($check['status'] === 'unhealthy') {
            $overallStatus = 'unhealthy';
        } elseif ($check['status'] === 'warning' && $overallStatus === 'healthy') {
            $overallStatus = 'degraded';
        }
    }

    $health['status'] = $overallStatus;

    // Set HTTP status code based on overall health
    if ($overallStatus === 'critical' || $overallStatus === 'unhealthy') {
        http_response_code(503); // Service Unavailable
    } elseif ($overallStatus === 'degraded') {
        http_response_code(200); // OK but with warnings
    } else {
        http_response_code(200); // OK
    }

} catch (Exception $e) {
    // Catch-all error handler
    $health['status'] = 'unhealthy';
    $health['error'] = $e->getMessage();
    http_response_code(500);
}

// Output JSON response
echo json_encode($health, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
exit;
