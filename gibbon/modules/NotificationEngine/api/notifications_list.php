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
 * Notification List API Endpoint
 *
 * Retrieves paginated list of notifications for the authenticated user.
 *
 * Endpoint: GET /modules/NotificationEngine/api/notifications_list.php
 *
 * Query Parameters:
 *   - gibbonPersonID (required): User ID to fetch notifications for
 *   - skip (optional): Number of records to skip (default: 0)
 *   - limit (optional): Maximum number of records to return (default: 20, max: 100)
 *   - status (optional): Filter by status (pending, sent, failed)
 *   - type (optional): Filter by notification type
 *   - unread_only (optional): Only return unread notifications (true/false)
 *
 * Response:
 *   {
 *     "items": [
 *       {
 *         "id": "123",
 *         "type": "checkIn",
 *         "title": "Check-In Confirmed",
 *         "body": "Child has arrived at school",
 *         "data": {...},
 *         "status": "sent",
 *         "read": false,
 *         "createdAt": "2024-01-15T10:30:00Z"
 *       },
 *       ...
 *     ],
 *     "total": 45,
 *     "skip": 0,
 *     "limit": 20,
 *     "unreadCount": 5
 *   }
 */

// Set headers for CORS and JSON response
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle OPTIONS preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'error' => 'Method Not Allowed',
        'detail' => 'Only GET requests are accepted'
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

    // Validate required parameters
    $gibbonPersonID = $_GET['gibbonPersonID'] ?? null;
    if (!$gibbonPersonID || !is_numeric($gibbonPersonID)) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Invalid Request',
            'detail' => 'gibbonPersonID is required and must be a valid integer'
        ]);
        exit;
    }

    // Parse pagination parameters
    $skip = isset($_GET['skip']) && is_numeric($_GET['skip']) ? (int)$_GET['skip'] : 0;
    $limit = isset($_GET['limit']) && is_numeric($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $limit = min($limit, 100); // Cap at 100 records

    // Parse filter parameters
    $status = $_GET['status'] ?? null;
    $type = $_GET['type'] ?? null;
    $unreadOnly = isset($_GET['unread_only']) && $_GET['unread_only'] === 'true';

    // Build WHERE clause
    $whereClauses = ['gibbonPersonID = :gibbonPersonID'];
    $params = [':gibbonPersonID' => $gibbonPersonID];

    if ($status && in_array($status, ['pending', 'processing', 'sent', 'failed'])) {
        $whereClauses[] = 'status = :status';
        $params[':status'] = $status;
    }

    if ($type) {
        $whereClauses[] = 'type = :type';
        $params[':type'] = $type;
    }

    if ($unreadOnly) {
        $whereClauses[] = 'readAt IS NULL';
    }

    $whereSQL = implode(' AND ', $whereClauses);

    // Get total count
    $countSQL = "SELECT COUNT(*) as total FROM gibbonNotificationQueue WHERE $whereSQL";
    $countStmt = $pdo->prepare($countSQL);
    $countStmt->execute($params);
    $totalCount = (int)$countStmt->fetch()['total'];

    // Get unread count
    $unreadSQL = "SELECT COUNT(*) as total FROM gibbonNotificationQueue
                  WHERE gibbonPersonID = :gibbonPersonID AND readAt IS NULL";
    $unreadStmt = $pdo->prepare($unreadSQL);
    $unreadStmt->execute([':gibbonPersonID' => $gibbonPersonID]);
    $unreadCount = (int)$unreadStmt->fetch()['total'];

    // Get notifications with pagination
    $sql = "SELECT
                gibbonNotificationQueueID as id,
                type,
                title,
                body,
                data,
                channel,
                status,
                attempts,
                sentAt,
                readAt,
                timestampCreated as createdAt
            FROM gibbonNotificationQueue
            WHERE $whereSQL
            ORDER BY timestampCreated DESC
            LIMIT :limit OFFSET :skip";

    $stmt = $pdo->prepare($sql);

    // Bind parameters
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':skip', $skip, PDO::PARAM_INT);

    $stmt->execute();
    $notifications = $stmt->fetchAll();

    // Format notifications for response
    $items = array_map(function($notification) {
        return [
            'id' => (string)$notification['id'],
            'type' => $notification['type'],
            'title' => $notification['title'],
            'body' => $notification['body'],
            'data' => $notification['data'] ? json_decode($notification['data'], true) : null,
            'channel' => $notification['channel'],
            'status' => $notification['status'],
            'attempts' => (int)$notification['attempts'],
            'sentAt' => $notification['sentAt'],
            'read' => $notification['readAt'] !== null,
            'readAt' => $notification['readAt'],
            'createdAt' => $notification['createdAt']
        ];
    }, $notifications);

    // Send successful response
    http_response_code(200);
    echo json_encode([
        'items' => $items,
        'total' => $totalCount,
        'skip' => $skip,
        'limit' => $limit,
        'unreadCount' => $unreadCount
    ], JSON_PRETTY_PRINT);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database Error',
        'detail' => 'Failed to retrieve notifications: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server Error',
        'detail' => $e->getMessage()
    ]);
}
