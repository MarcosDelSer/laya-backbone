<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuiber and the Gibbon community (https://gibbonedu.org/about/)
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
 * FCM Token Unregistration API Endpoint
 *
 * POST /modules/NotificationEngine/api/fcm_token_unregister.php
 *
 * Deactivates a Firebase Cloud Messaging (FCM) device token
 * for the authenticated user.
 *
 * Request Body (JSON):
 * {
 *   "deviceToken": "string"     // Required: FCM device token to deactivate
 * }
 *
 * Response (JSON):
 * {
 *   "success": true|false,
 *   "message": "string"
 * }
 */

// Set JSON content type header
header('Content-Type: application/json');

// Handle CORS for mobile app requests
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');
}

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) {
        header("Access-Control-Allow-Methods: POST, OPTIONS");
    }
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
    }
    http_response_code(200);
    exit(0);
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Use POST.',
    ]);
    exit;
}

// Get request body
$requestBody = file_get_contents('php://input');
$data = json_decode($requestBody, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid JSON in request body.',
    ]);
    exit;
}

// Validate required fields
if (empty($data['deviceToken'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Missing required field: deviceToken',
    ]);
    exit;
}

try {
    // Initialize database connection
    require_once __DIR__ . '/../../../config.php';

    // Create PDO connection
    $dsn = "mysql:host={$databaseServer};dbname={$databaseName};charset=utf8mb4";
    $pdo = new PDO($dsn, $databaseUsername, $databasePassword, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    $deviceToken = trim($data['deviceToken']);

    // Deactivate the token
    $stmt = $pdo->prepare("
        UPDATE gibbonFCMToken
        SET active = 'N'
        WHERE deviceToken = :deviceToken
    ");

    $result = $stmt->execute(['deviceToken' => $deviceToken]);

    if ($result) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Device token deactivated successfully.',
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to deactivate device token. Please try again.',
        ]);
    }
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error: ' . $e->getMessage(),
    ]);
}
