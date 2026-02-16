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
 * CLI AISync Retry Queue Processor
 *
 * Processes failed webhook sync attempts with exponential backoff retry logic.
 * Run via cron job: */5 * * * * php /path/to/gibbon/modules/AISync/cli/retryQueue.php
 *
 * Options:
 *   --limit=N        Process maximum N failed syncs (default: 50)
 *   --dry-run        Show what would be processed without actually retrying
 *   --verbose        Show detailed output
 *   --purge          Purge old completed/failed syncs
 *   --purge-days=N   Days to keep sync logs (default: 30)
 *   --force          Force retry even if within backoff period
 *
 * Retry Strategy:
 *   - Uses exponential backoff: delay = baseDelay * (2 ^ retryCount)
 *   - Default base delay: 30 seconds (configurable)
 *   - Maximum retry attempts: 3 (configurable)
 *   - Only retries syncs that are past their backoff period
 *
 * @version v1.0.00
 * @since   v1.0.00
 */

// Ensure CLI only
if (PHP_SAPI !== 'cli') {
    die('This script must be run from the command line.');
}

// Parse command line options
$options = getopt('', ['limit::', 'dry-run', 'verbose', 'purge', 'purge-days::', 'force', 'help']);

if (isset($options['help'])) {
    echo "AISync Retry Queue Processor\n";
    echo "=============================\n\n";
    echo "Usage: php retryQueue.php [options]\n\n";
    echo "Options:\n";
    echo "  --limit=N        Process maximum N failed syncs (default: 50)\n";
    echo "  --dry-run        Show what would be processed without retrying\n";
    echo "  --verbose        Show detailed output\n";
    echo "  --purge          Purge old completed/failed syncs\n";
    echo "  --purge-days=N   Days to keep sync logs (default: 30)\n";
    echo "  --force          Force retry even if within backoff period\n";
    echo "  --help           Show this help message\n\n";
    echo "Retry Strategy:\n";
    echo "  - Exponential backoff: delay = baseDelay * (2 ^ retryCount)\n";
    echo "  - Default base delay: 30 seconds\n";
    echo "  - Maximum retry attempts: 3\n";
    echo "  - Only retries syncs past their backoff period\n\n";
    echo "Cron Setup:\n";
    echo "  Run every 5 minutes: */5 * * * * php " . __FILE__ . "\n\n";
    exit(0);
}

$dryRun = isset($options['dry-run']);
$verbose = isset($options['verbose']);
$doPurge = isset($options['purge']);
$forceRetry = isset($options['force']);
$limit = isset($options['limit']) ? (int) $options['limit'] : 50;
$purgeDays = isset($options['purge-days']) ? (int) $options['purge-days'] : 30;

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

output('AISync Retry Queue Processor started', true);
output('Limit: ' . $limit . ', Dry Run: ' . ($dryRun ? 'Yes' : 'No') . ', Force: ' . ($forceRetry ? 'Yes' : 'No'));

// Get configuration
$maxRetryAttempts = $aiSyncService->getMaxRetryAttempts();
$baseRetryDelay = $aiSyncService->getRetryDelaySeconds();

output('Max retry attempts: ' . $maxRetryAttempts . ', Base delay: ' . $baseRetryDelay . ' seconds');

// Check if sync is enabled
if (!$aiSyncService->isSyncEnabled()) {
    output('AI Sync is disabled in settings. Exiting.', true);
    exit(0);
}

// Get statistics before processing
try {
    $statsBefore = $aiSyncService->getStatistics();
    output('Sync status - Success: ' . ($statsBefore['byStatus']['success'] ?? 0) .
           ', Failed: ' . ($statsBefore['byStatus']['failed'] ?? 0) .
           ', Pending: ' . ($statsBefore['byStatus']['pending'] ?? 0));
} catch (\Exception $e) {
    output('Warning: Failed to get statistics: ' . $e->getMessage());
}

// Purge old sync logs if requested
if ($doPurge) {
    output('Purging sync logs older than ' . $purgeDays . ' days...', true);

    if (!$dryRun) {
        try {
            $stmt = $pdo->prepare("
                DELETE FROM gibbonAISyncLog
                WHERE timestampCreated < DATE_SUB(NOW(), INTERVAL :days DAY)
                AND status IN ('success', 'failed')
            ");
            $stmt->execute([':days' => $purgeDays]);
            $purged = $stmt->rowCount();
            output('Purged ' . $purged . ' old sync log(s)', true);
        } catch (\PDOException $e) {
            output('Error purging logs: ' . $e->getMessage(), true);
        }
    } else {
        output('[DRY RUN] Would purge old sync logs', true);
    }
}

// Build query to get failed syncs that are eligible for retry
// Eligible means:
// 1. Status is 'failed'
// 2. retryCount < maxRetryAttempts
// 3. Either force is enabled OR past the exponential backoff period
if ($forceRetry) {
    // Force mode: retry all failed syncs regardless of timing
    $query = "
        SELECT *
        FROM gibbonAISyncLog
        WHERE status = 'failed'
        AND retryCount < :maxRetries
        ORDER BY timestampCreated ASC
        LIMIT :limit
    ";
} else {
    // Normal mode: only retry syncs past their backoff period
    // Backoff formula: baseDelay * (2 ^ retryCount) seconds
    $query = "
        SELECT *,
               :baseDelay * POW(2, retryCount) as backoffSeconds,
               TIMESTAMPDIFF(SECOND, timestampProcessed, NOW()) as secondsSinceLastAttempt
        FROM gibbonAISyncLog
        WHERE status = 'failed'
        AND retryCount < :maxRetries
        AND TIMESTAMPDIFF(SECOND, timestampProcessed, NOW()) >= (:baseDelay * POW(2, retryCount))
        ORDER BY timestampCreated ASC
        LIMIT :limit
    ";
}

try {
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':maxRetries', $maxRetryAttempts, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);

    if (!$forceRetry) {
        $stmt->bindValue(':baseDelay', $baseRetryDelay, PDO::PARAM_INT);
    }

    $stmt->execute();
    $failedSyncs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\PDOException $e) {
    output('Error querying failed syncs: ' . $e->getMessage(), true);
    exit(1);
}

if (empty($failedSyncs)) {
    output('No failed syncs eligible for retry', true);
    exit(0);
}

output('Found ' . count($failedSyncs) . ' failed sync(s) eligible for retry', true);

// Process each failed sync
$processed = 0;
$succeeded = 0;
$failed = 0;
$maxExceeded = 0;

foreach ($failedSyncs as $sync) {
    $logID = $sync['gibbonAISyncLogID'];
    $eventType = $sync['eventType'];
    $entityType = $sync['entityType'];
    $entityID = $sync['entityID'];
    $retryCount = $sync['retryCount'];
    $backoffSeconds = isset($sync['backoffSeconds']) ? $sync['backoffSeconds'] : 'N/A';

    output('Processing failed sync #' . $logID . ' (' . $eventType . ', entity: ' . $entityType . ' #' . $entityID . ', retry: ' . $retryCount . '/' . $maxRetryAttempts . ')');

    if (!$forceRetry && isset($sync['secondsSinceLastAttempt'])) {
        output('  Backoff period: ' . $backoffSeconds . 's, Time since last: ' . $sync['secondsSinceLastAttempt'] . 's');
    }

    if ($dryRun) {
        output('[DRY RUN] Would retry sync #' . $logID);
        $processed++;
        continue;
    }

    // Attempt retry using the AISyncService
    try {
        $result = $aiSyncService->retryFailedSync($logID);

        if ($result['success']) {
            output('  Retry succeeded for sync #' . $logID);
            $succeeded++;
        } else {
            $errorCode = $result['error']['code'] ?? 'UNKNOWN';
            $errorMessage = $result['error']['message'] ?? 'Unknown error';

            if ($errorCode === 'MAX_RETRIES_EXCEEDED') {
                output('  Max retries exceeded for sync #' . $logID);
                $maxExceeded++;
            } else {
                output('  Retry failed for sync #' . $logID . ': ' . $errorMessage);
                $failed++;
            }
        }
    } catch (\Exception $e) {
        output('  Exception during retry of sync #' . $logID . ': ' . $e->getMessage());
        $failed++;
    }

    $processed++;
}

// Get statistics after processing
try {
    $statsAfter = $aiSyncService->getStatistics();
} catch (\Exception $e) {
    $statsAfter = ['byStatus' => []];
}

// Summary
output('', true);
output('Processing complete', true);
output('===================', true);
output('Processed: ' . $processed, true);
output('Succeeded: ' . $succeeded, true);
output('Failed: ' . $failed, true);
output('Max retries exceeded: ' . $maxExceeded, true);
output('', true);
output('Sync status after - Success: ' . ($statsAfter['byStatus']['success'] ?? 0) .
       ', Failed: ' . ($statsAfter['byStatus']['failed'] ?? 0) .
       ', Pending: ' . ($statsAfter['byStatus']['pending'] ?? 0), true);

// Exit with appropriate code
exit($failed > 0 ? 1 : 0);
