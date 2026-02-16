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
 * Mark Notification as Read API Endpoint
 *
 * Marks one or more notifications as read for the authenticated user.
 *
 * Endpoint: POST /modules/NotificationEngine/api/notifications_mark_read.php
 *
 * Request Body (JSON):
 *   {
 *     "gibbonPersonID": 123,
 *     "notificationIds": ["1", "2", "3"]  // Single ID or array of IDs
 *   }
 *
 * Response:
 *   {
 *     "success": true,
 *     "markedCount": 3,
 *     "unreadCount": 5
 *   }
 */

// Set headers for CORS and JSON response
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle OPTIONS preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'error' => 'Method Not Allowed',
        'detail' => 'Only POST requests are accepted'
    ]);
    exit;
}

// Get database connection from Gibbon config
$dbConfigPath = dirname(__FILE__, 4) . '/config.php';
if (!file_exists($dbConfigPath)) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Configuration Error',
        'detail' => 'Database configuration not found'
    ]);
    exit;
}

require_once $dbConfigPath;

try {
    // Connect to database
    $pdo = new PDO(
        "mysql:host=$databaseServer;dbname=$databaseName;charset=utf8mb4",
        $databaseUsername,
        $databasePassword,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );

    // Parse JSON request body
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Invalid JSON',
            'detail' => 'Request body must be valid JSON'
        ]);
        exit;
    }

    // Validate required parameters
    $gibbonPersonID = $data['gibbonPersonID'] ?? null;
    if (!$gibbonPersonID || !is_numeric($gibbonPersonID)) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Invalid Request',
            'detail' => 'gibbonPersonID is required and must be a valid integer'
        ]);
        exit;
    }

    $notificationIds = $data['notificationIds'] ?? [];
    if (empty($notificationIds)) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Invalid Request',
            'detail' => 'notificationIds is required and must not be empty'
        ]);
        exit;
    }

    // Ensure notificationIds is an array
    if (!is_array($notificationIds)) {
        $notificationIds = [$notificationIds];
    }

    // Validate all IDs are numeric
    foreach ($notificationIds as $id) {
        if (!is_numeric($id)) {
            http_response_code(400);
            echo json_encode([
                'error' => 'Invalid Request',
                'detail' => 'All notification IDs must be valid integers'
            ]);
            exit;
        }
    }

    // Mark notifications as read
    $placeholders = str_repeat('?,', count($notificationIds) - 1) . '?';
    $sql = "UPDATE gibbonNotificationQueue
            SET readAt = NOW()
            WHERE gibbonNotificationQueueID IN ($placeholders)
              AND gibbonPersonID = ?
              AND readAt IS NULL";

    $stmt = $pdo->prepare($sql);
    $params = array_merge($notificationIds, [$gibbonPersonID]);
    $stmt->execute($params);
    $markedCount = $stmt->rowCount();

    // Get updated unread count
    $unreadSQL = "SELECT COUNT(*) as total FROM gibbonNotificationQueue
                  WHERE gibbonPersonID = ? AND readAt IS NULL";
    $unreadStmt = $pdo->prepare($unreadSQL);
    $unreadStmt->execute([$gibbonPersonID]);
    $unreadCount = (int)$unreadStmt->fetch()['total'];

    // Send successful response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'markedCount' => $markedCount,
        'unreadCount' => $unreadCount
    ], JSON_PRETTY_PRINT);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database Error',
        'detail' => 'Failed to mark notifications as read: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server Error',
        'detail' => $e->getMessage()
    ]);
}
