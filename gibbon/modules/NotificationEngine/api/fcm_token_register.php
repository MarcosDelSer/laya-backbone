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
 * FCM Token Registration API Endpoint
 *
 * POST /modules/NotificationEngine/api/fcm_token_register.php
 *
 * Registers or updates a Firebase Cloud Messaging (FCM) device token
 * for the authenticated user.
 *
 * Request Body (JSON):
 * {
 *   "deviceToken": "string",     // Required: FCM device token
 *   "deviceType": "ios|android|web",  // Required: Device platform
 *   "deviceName": "string"       // Optional: User-friendly device name
 * }
 *
 * Response (JSON):
 * {
 *   "success": true|false,
 *   "message": "string",
 *   "data": {
 *     "tokenID": int,
 *     "deviceToken": "string",
 *     "deviceType": "string",
 *     "active": "Y"|"N"
 *   }
 * }
 */

use Gibbon\Module\NotificationEngine\Domain\NotificationGateway;

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

// This API endpoint is designed to be called by mobile apps with authentication
// For simplicity and to work standalone, we'll use a basic approach:
// 1. Accept authentication via Bearer token or session
// 2. Extract user ID from the token/session
// 3. Register the FCM token

// For MVP, we'll accept gibbonPersonID in the request body (secured by app-level auth)
// In production, this would come from a validated JWT or session token

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

if (empty($data['deviceType'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Missing required field: deviceType',
    ]);
    exit;
}

if (empty($data['gibbonPersonID'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Missing required field: gibbonPersonID',
    ]);
    exit;
}

// Validate deviceType enum
$validDeviceTypes = ['ios', 'android', 'web'];
if (!in_array($data['deviceType'], $validDeviceTypes)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid deviceType. Must be one of: ' . implode(', ', $validDeviceTypes),
    ]);
    exit;
}

// Validate token format (basic validation)
if (strlen($data['deviceToken']) < 10 || strlen($data['deviceToken']) > 500) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid deviceToken format. Token must be between 10 and 500 characters.',
    ]);
    exit;
}

try {
    // Initialize database connection
    // For standalone API, we need to create a minimal database connection
    require_once __DIR__ . '/../../../config.php';

    // Create PDO connection
    $dsn = "mysql:host={$databaseServer};dbname={$databaseName};charset=utf8mb4";
    $pdo = new PDO($dsn, $databaseUsername, $databasePassword, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    // Extract data from request
    $gibbonPersonID = (int) $data['gibbonPersonID'];
    $deviceToken = trim($data['deviceToken']);
    $deviceType = $data['deviceType'];
    $deviceName = isset($data['deviceName']) ? trim($data['deviceName']) : null;

    // Register or update the token using direct SQL
    $stmt = $pdo->prepare("
        INSERT INTO gibbonFCMToken
        (gibbonPersonID, deviceToken, deviceType, deviceName, active, lastUsedAt)
        VALUES (:gibbonPersonID, :deviceToken, :deviceType, :deviceName, 'Y', NOW())
        ON DUPLICATE KEY UPDATE
            gibbonPersonID = :gibbonPersonID,
            deviceType = :deviceType,
            deviceName = :deviceName,
            active = 'Y',
            lastUsedAt = NOW()
    ");

    $result = $stmt->execute([
        'gibbonPersonID' => $gibbonPersonID,
        'deviceToken' => $deviceToken,
        'deviceType' => $deviceType,
        'deviceName' => $deviceName,
    ]);

    if ($result) {
        // Get the registered token details
        $stmt = $pdo->prepare("SELECT * FROM gibbonFCMToken WHERE deviceToken = :deviceToken");
        $stmt->execute(['deviceToken' => $deviceToken]);
        $tokenDetails = $stmt->fetch();

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Device token registered successfully.',
            'data' => [
                'tokenID' => $tokenDetails['gibbonFCMTokenID'] ?? null,
                'deviceToken' => $deviceToken,
                'deviceType' => $deviceType,
                'deviceName' => $deviceName,
                'active' => $tokenDetails['active'] ?? 'Y',
                'lastUsedAt' => $tokenDetails['lastUsedAt'] ?? null,
            ],
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to register device token. Please try again.',
        ]);
    }
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error: ' . $e->getMessage(),
    ]);
}
