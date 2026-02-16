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
 * FCM Token List API Endpoint
 *
 * GET/POST /modules/NotificationEngine/api/fcm_token_list.php
 *
 * Retrieves all active FCM device tokens for a user.
 *
 * Request Body/Query (JSON):
 * {
 *   "gibbonPersonID": int     // Required: User ID
 * }
 *
 * Response (JSON):
 * {
 *   "success": true|false,
 *   "message": "string",
 *   "data": {
 *     "tokens": [
 *       {
 *         "tokenID": int,
 *         "deviceToken": "string",
 *         "deviceType": "ios|android|web",
 *         "deviceName": "string",
 *         "active": "Y"|"N",
 *         "lastUsedAt": "datetime",
 *         "createdAt": "datetime"
 *       }
 *     ],
 *     "count": int
 *   }
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
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    }
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
    }
    http_response_code(200);
    exit(0);
}

// Allow both GET and POST requests
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'])) {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Use GET or POST.',
    ]);
    exit;
}

// Get data from request (POST body or GET query)
$data = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
} else {
    $data = $_GET;
}

// Validate required fields
if (empty($data['gibbonPersonID'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Missing required field: gibbonPersonID',
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

    $gibbonPersonID = (int) $data['gibbonPersonID'];

    // Get all active tokens for the user
    $stmt = $pdo->prepare("
        SELECT
            gibbonFCMTokenID as tokenID,
            deviceToken,
            deviceType,
            deviceName,
            active,
            lastUsedAt,
            timestampCreated as createdAt
        FROM gibbonFCMToken
        WHERE gibbonPersonID = :gibbonPersonID
        AND active = 'Y'
        ORDER BY lastUsedAt DESC
    ");

    $stmt->execute(['gibbonPersonID' => $gibbonPersonID]);
    $tokens = $stmt->fetchAll();

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Tokens retrieved successfully.',
        'data' => [
            'tokens' => $tokens,
            'count' => count($tokens),
        ],
    ]);
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error: ' . $e->getMessage(),
    ]);
}
