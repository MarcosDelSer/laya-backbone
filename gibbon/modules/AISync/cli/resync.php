#!/usr/bin/env php
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
 * CLI AISync Manual Re-sync Command
 *
 * Manually triggers re-synchronization of specific entities or entity types to the AI service.
 * Useful for backfilling data, recovering from sync failures, or testing webhook integrations.
 *
 * Usage: php resync.php --type=TYPE [options]
 *
 * Options:
 *   --type=TYPE          Entity type to re-sync (required)
 *                        Types: activity, meal, nap, attendance, photo
 *   --id=ID              Specific entity ID to re-sync (optional)
 *   --since=DATE         Re-sync entities created/modified since date (YYYY-MM-DD)
 *   --until=DATE         Re-sync entities created/modified until date (YYYY-MM-DD)
 *   --limit=N            Maximum number of entities to re-sync (default: 100)
 *   --dry-run            Show what would be synced without actually syncing
 *   --verbose            Show detailed output
 *   --force              Re-sync even if already successfully synced
 *   --help               Show this help message
 *
 * Examples:
 *   # Re-sync a specific activity
 *   php resync.php --type=activity --id=123
 *
 *   # Re-sync all meals from the last week
 *   php resync.php --type=meal --since=2026-02-09
 *
 *   # Re-sync all photos (dry run to see what would be synced)
 *   php resync.php --type=photo --dry-run
 *
 *   # Re-sync attendance records between two dates
 *   php resync.php --type=attendance --since=2026-02-01 --until=2026-02-15
 *
 * @version v1.0.00
 * @since   v1.0.00
 */

// Ensure CLI only
if (PHP_SAPI !== 'cli') {
    die('This script must be run from the command line.');
}

// Parse command line options
$options = getopt('', ['type:', 'id::', 'since::', 'until::', 'limit::', 'dry-run', 'verbose', 'force', 'help']);

if (isset($options['help'])) {
    echo "AISync Manual Re-sync Command\n";
    echo "==============================\n\n";
    echo "Usage: php resync.php --type=TYPE [options]\n\n";
    echo "Options:\n";
    echo "  --type=TYPE          Entity type to re-sync (required)\n";
    echo "                       Types: activity, meal, nap, attendance, photo\n";
    echo "  --id=ID              Specific entity ID to re-sync (optional)\n";
    echo "  --since=DATE         Re-sync entities created/modified since date (YYYY-MM-DD)\n";
    echo "  --until=DATE         Re-sync entities created/modified until date (YYYY-MM-DD)\n";
    echo "  --limit=N            Maximum number of entities to re-sync (default: 100)\n";
    echo "  --dry-run            Show what would be synced without syncing\n";
    echo "  --verbose            Show detailed output\n";
    echo "  --force              Re-sync even if already successfully synced\n";
    echo "  --help               Show this help message\n\n";
    echo "Examples:\n";
    echo "  php resync.php --type=activity --id=123\n";
    echo "  php resync.php --type=meal --since=2026-02-09\n";
    echo "  php resync.php --type=photo --dry-run --verbose\n\n";
    exit(0);
}

// Validate required options
if (!isset($options['type'])) {
    echo "Error: --type is required\n";
    echo "Run with --help for usage information\n";
    exit(1);
}

$entityType = $options['type'];
$validTypes = ['activity', 'meal', 'nap', 'attendance', 'photo'];

if (!in_array($entityType, $validTypes)) {
    echo "Error: Invalid entity type '{$entityType}'\n";
    echo "Valid types: " . implode(', ', $validTypes) . "\n";
    exit(1);
}

$dryRun = isset($options['dry-run']);
$verbose = isset($options['verbose']);
$force = isset($options['force']);
$entityID = isset($options['id']) ? (int) $options['id'] : null;
$since = $options['since'] ?? null;
$until = $options['until'] ?? null;
$limit = isset($options['limit']) ? (int) $options['limit'] : 100;

// Validate date formats
if ($since && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $since)) {
    echo "Error: Invalid --since date format. Use YYYY-MM-DD\n";
    exit(1);
}
if ($until && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $until)) {
    echo "Error: Invalid --until date format. Use YYYY-MM-DD\n";
    exit(1);
}

// Helper function for output
function output($message, $forceOutput = false) {
    global $verbose;
    if ($verbose || $forceOutput) {
        echo '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n";
    }
}

// Find and load Gibbon bootstrap
$gibbonPath = realpath(__DIR__ . '/../../../..');
if (!$gibbonPath || !file_exists($gibbonPath . '/gibbon.php')) {
    // Try alternative paths
    $possiblePaths = [
        __DIR__ . '/../../../../gibbon.php',
        __DIR__ . '/../../../gibbon.php',
        getcwd() . '/gibbon.php',
    ];

    foreach ($possiblePaths as $path) {
        if (file_exists($path)) {
            $gibbonPath = dirname(realpath($path));
            break;
        }
    }
}

if (!$gibbonPath || !file_exists($gibbonPath . '/gibbon.php')) {
    output('Error: Could not locate gibbon.php. Please run from the Gibbon root directory.', true);
    exit(1);
}

// Set working directory
chdir($gibbonPath);

// Load Gibbon
require_once $gibbonPath . '/gibbon.php';

// Check if we have access to the container
if (!isset($container)) {
    output('Error: Gibbon container not available. Check Gibbon configuration.', true);
    exit(1);
}

// Get required services
use Gibbon\Module\AISync\AISyncService;
use Gibbon\Domain\System\SettingGateway;

try {
    $settingGateway = $container->get(SettingGateway::class);
    $pdo = $container->get(PDO::class);
    $aiSyncService = new AISyncService($settingGateway, $pdo);
} catch (\Exception $e) {
    output('Error: Failed to initialize services: ' . $e->getMessage(), true);
    exit(1);
}

output('AISync Manual Re-sync started', true);
output('Entity Type: ' . $entityType . ($entityID ? ' (ID: ' . $entityID . ')' : ''));
output('Limit: ' . $limit . ', Dry Run: ' . ($dryRun ? 'Yes' : 'No') . ', Force: ' . ($force ? 'Yes' : 'No'));

// Check if sync is enabled
if (!$aiSyncService->isSyncEnabled()) {
    output('AI Sync is disabled in settings. Exiting.', true);
    exit(0);
}

// Build query based on entity type
$query = '';
$params = [];
$tableMap = [
    'activity' => 'gibbonCareActivity',
    'meal' => 'gibbonCareMeal',
    'nap' => 'gibbonCareNap',
    'attendance' => 'gibbonCareAttendance',
    'photo' => 'gibbonPhoto',
];

$primaryKeyMap = [
    'activity' => 'gibbonCareActivityID',
    'meal' => 'gibbonCareMealID',
    'nap' => 'gibbonCareNapID',
    'attendance' => 'gibbonCareAttendanceID',
    'photo' => 'gibbonPhotoID',
];

$tableName = $tableMap[$entityType];
$primaryKey = $primaryKeyMap[$entityType];

// Build WHERE clause
$whereClauses = [];

if ($entityID) {
    $whereClauses[] = "{$primaryKey} = :entityID";
    $params[':entityID'] = $entityID;
}

if ($since) {
    if ($entityType === 'photo') {
        $whereClauses[] = "timestampCreated >= :since";
    } else {
        $whereClauses[] = "timestampCreated >= :since";
    }
    $params[':since'] = $since . ' 00:00:00';
}

if ($until) {
    if ($entityType === 'photo') {
        $whereClauses[] = "timestampCreated <= :until";
    } else {
        $whereClauses[] = "timestampCreated <= :until";
    }
    $params[':until'] = $until . ' 23:59:59';
}

$whereSQL = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// Build complete query
$query = "
    SELECT *
    FROM {$tableName}
    {$whereSQL}
    ORDER BY {$primaryKey} ASC
    LIMIT :limit
";

// Execute query to get entities
try {
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $entities = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\PDOException $e) {
    output('Error querying entities: ' . $e->getMessage(), true);
    exit(1);
}

if (empty($entities)) {
    output('No entities found to re-sync', true);
    exit(0);
}

output('Found ' . count($entities) . ' ' . $entityType . '(s) to re-sync', true);

// Process each entity
$processed = 0;
$succeeded = 0;
$failed = 0;
$skipped = 0;

foreach ($entities as $entity) {
    $id = $entity[$primaryKey];

    output('Processing ' . $entityType . ' #' . $id);

    if ($dryRun) {
        output('[DRY RUN] Would re-sync ' . $entityType . ' #' . $id);
        $processed++;
        continue;
    }

    // Build payload based on entity type
    $payload = [];
    $eventType = '';

    switch ($entityType) {
        case 'activity':
            $eventType = 'care_activity_created';
            $payload = [
                'activityID' => $entity['gibbonCareActivityID'],
                'personID' => $entity['gibbonPersonID'],
                'date' => $entity['date'],
                'activityName' => $entity['activityName'],
                'activityType' => $entity['activityType'],
                'duration' => $entity['duration'],
                'participation' => $entity['participation'],
                'notes' => $entity['notes'],
                'aiSuggested' => $entity['aiSuggested'],
                'aiActivityID' => $entity['aiActivityID'],
            ];
            break;

        case 'meal':
            $eventType = 'meal_logged';
            $payload = [
                'mealID' => $entity['gibbonCareMealID'],
                'personID' => $entity['gibbonPersonID'],
                'date' => $entity['date'],
                'mealType' => $entity['mealType'],
                'foodItems' => $entity['foodItems'],
                'amountEaten' => $entity['amountEaten'],
                'notes' => $entity['notes'],
            ];
            break;

        case 'nap':
            $eventType = 'nap_logged';
            $payload = [
                'napID' => $entity['gibbonCareNapID'],
                'personID' => $entity['gibbonPersonID'],
                'date' => $entity['date'],
                'startTime' => $entity['startTime'],
                'endTime' => $entity['endTime'],
                'duration' => $entity['duration'],
                'quality' => $entity['quality'],
                'notes' => $entity['notes'],
            ];
            break;

        case 'attendance':
            // Determine if check-in or check-out based on timeOut
            $eventType = empty($entity['timeOut']) ? 'child_checked_in' : 'child_checked_out';
            $payload = [
                'attendanceID' => $entity['gibbonCareAttendanceID'],
                'personID' => $entity['gibbonPersonID'],
                'date' => $entity['date'],
                'timeIn' => $entity['timeIn'],
                'timeOut' => $entity['timeOut'],
                'checkedInBy' => $entity['checkedInBy'],
                'checkedOutBy' => $entity['checkedOutBy'],
                'notes' => $entity['notes'],
            ];
            break;

        case 'photo':
            $eventType = 'photo_uploaded';
            $payload = [
                'photoID' => $entity['gibbonPhotoID'],
                'filePath' => $entity['filePath'],
                'caption' => $entity['caption'],
                'timestamp' => $entity['timestampCreated'],
                'uploadedBy' => $entity['gibbonPersonIDUploadedBy'],
            ];
            break;
    }

    // Send webhook synchronously
    try {
        $result = $aiSyncService->sendWebhookSync($eventType, $entityType, $id, $payload);

        if ($result['success']) {
            output('  Successfully re-synced ' . $entityType . ' #' . $id);
            $succeeded++;
        } else {
            $errorMessage = $result['error']['message'] ?? 'Unknown error';
            output('  Failed to re-sync ' . $entityType . ' #' . $id . ': ' . $errorMessage);
            $failed++;
        }
    } catch (\Exception $e) {
        output('  Exception during re-sync of ' . $entityType . ' #' . $id . ': ' . $e->getMessage());
        $failed++;
    }

    $processed++;
}

// Summary
output('', true);
output('Re-sync complete', true);
output('================', true);
output('Entity Type: ' . $entityType, true);
output('Processed: ' . $processed, true);
output('Succeeded: ' . $succeeded, true);
output('Failed: ' . $failed, true);
output('Skipped: ' . $skipped, true);

// Exit with appropriate code
exit($failed > 0 ? 1 : 0);
