#!/usr/bin/env php
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
 * CLI Document Expiration Checker
 *
 * Checks for expiring government documents and queues notifications.
 * Run via cron job: 0 8 * * * php /path/to/gibbon/modules/GovernmentDocuments/cli/checkExpiring.php
 *
 * Options:
 *   --dry-run     Show what would be queued without actually queueing
 *   --verbose     Show detailed output
 *   --intervals=N Comma-separated days before expiry to notify (default: 30,14,7)
 *   --mark-expired  Also mark expired documents
 *
 * @version v1.0.00
 * @since   v1.0.00
 */

// Ensure CLI only
if (PHP_SAPI !== 'cli') {
    die('This script must be run from the command line.');
}

// Parse command line options
$options = getopt('', ['dry-run', 'verbose', 'intervals::', 'mark-expired', 'help']);

if (isset($options['help'])) {
    echo "Government Document Expiration Checker\n";
    echo "======================================\n\n";
    echo "Usage: php checkExpiring.php [options]\n\n";
    echo "Options:\n";
    echo "  --dry-run       Show what would be queued without sending\n";
    echo "  --verbose       Show detailed output\n";
    echo "  --intervals=N   Comma-separated days before expiry (default: 30,14,7)\n";
    echo "  --mark-expired  Also mark expired documents as 'expired'\n";
    echo "  --help          Show this help message\n\n";
    exit(0);
}

$dryRun = isset($options['dry-run']);
$verbose = isset($options['verbose']);
$markExpired = isset($options['mark-expired']);
$intervalsString = isset($options['intervals']) ? $options['intervals'] : '30,14,7';
$intervals = array_map('intval', array_filter(explode(',', $intervalsString)));

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
use Gibbon\Module\GovernmentDocuments\Domain\GovernmentDocumentGateway;
use Gibbon\Module\NotificationEngine\Domain\NotificationGateway;
use Gibbon\Domain\System\SettingGateway;

try {
    $documentGateway = $container->get(GovernmentDocumentGateway::class);
    $notificationGateway = $container->get(NotificationGateway::class);
    $settingGateway = $container->get(SettingGateway::class);
} catch (\Exception $e) {
    output('Error: Failed to initialize services: ' . $e->getMessage(), true);
    exit(1);
}

// Get current school year
$gibbonSchoolYearID = $settingGateway->getSettingByScope('System', 'gibbonSchoolYearID');
if (empty($gibbonSchoolYearID)) {
    output('Error: Could not determine current school year.', true);
    exit(1);
}

output('Government Document Expiration Checker started', true);
output('School Year ID: ' . $gibbonSchoolYearID . ', Dry Run: ' . ($dryRun ? 'Yes' : 'No'));
output('Checking intervals: ' . implode(', ', $intervals) . ' days');

// Track statistics
$stats = [
    'documentsChecked' => 0,
    'notificationsQueued' => 0,
    'documentsMarkedExpired' => 0,
    'skippedAlreadyNotified' => 0,
    'errors' => 0,
];

// Process each notification interval
foreach ($intervals as $daysUntilExpiry) {
    output('');
    output('Checking documents expiring in ' . $daysUntilExpiry . ' days...', true);

    // Query documents expiring within this interval (but not already past)
    $expiringDocuments = $documentGateway->selectExpiringDocuments($gibbonSchoolYearID, $daysUntilExpiry);

    // Filter to only those expiring on exactly this day threshold
    $targetDate = date('Y-m-d', strtotime("+{$daysUntilExpiry} days"));
    $documentsToNotify = [];

    foreach ($expiringDocuments as $doc) {
        // Check if this document expires within the threshold
        // We want to notify at specific intervals: 30, 14, 7 days
        $docDaysRemaining = (int) $doc['daysUntilExpiry'];

        // Only notify if the document expires exactly at or slightly after this interval
        // This prevents duplicate notifications when run daily
        if ($docDaysRemaining <= $daysUntilExpiry && $docDaysRemaining > ($daysUntilExpiry - 1)) {
            // Check if we already sent a notification for this interval
            $notificationKey = 'expiring_' . $daysUntilExpiry . 'd';
            if (!hasRecentNotification($documentGateway, $doc['gibbonGovernmentDocumentID'], $notificationKey)) {
                $documentsToNotify[] = $doc;
            } else {
                $stats['skippedAlreadyNotified']++;
                output('  Skipping document #' . $doc['gibbonGovernmentDocumentID'] . ' - already notified for ' . $daysUntilExpiry . ' day interval');
            }
        }
    }

    output('Found ' . count($documentsToNotify) . ' document(s) requiring notification');

    // Process each document
    foreach ($documentsToNotify as $doc) {
        $stats['documentsChecked']++;

        $documentID = $doc['gibbonGovernmentDocumentID'];
        $personID = $doc['gibbonPersonID'];
        $personName = trim($doc['preferredName'] . ' ' . $doc['surname']);
        $documentType = $doc['documentTypeDisplay'] ?: $doc['documentTypeName'];
        $expiryDate = $doc['expiryDate'];
        $daysRemaining = (int) $doc['daysUntilExpiry'];

        output('  Processing document #' . $documentID . ': ' . $documentType . ' for ' . $personName);
        output('    Expires: ' . $expiryDate . ' (' . $daysRemaining . ' days)');

        // Get family adults to notify (parents/guardians)
        $recipientIDs = getDocumentRecipients($documentGateway, $personID, $doc);

        if (empty($recipientIDs)) {
            output('    Warning: No recipients found for notification');
            $stats['errors']++;
            continue;
        }

        output('    Recipients: ' . count($recipientIDs) . ' person(s)');

        // Build notification content
        $notificationType = 'documentExpiring';
        $title = 'Document Expiring Soon: ' . $documentType;
        $body = buildNotificationBody($personName, $documentType, $expiryDate, $daysRemaining);
        $payloadData = [
            'documentID' => $documentID,
            'documentType' => $documentType,
            'personID' => $personID,
            'personName' => $personName,
            'expiryDate' => $expiryDate,
            'daysRemaining' => $daysRemaining,
            'actionUrl' => '/modules/GovernmentDocuments/governmentDocuments.php',
        ];

        if ($dryRun) {
            output('    [DRY RUN] Would queue notification for ' . count($recipientIDs) . ' recipient(s)');
            output('    Title: ' . $title);
            $stats['notificationsQueued'] += count($recipientIDs);
        } else {
            // Queue notifications
            $queued = $notificationGateway->queueBulkNotification(
                $recipientIDs,
                $notificationType,
                $title,
                $body,
                $payloadData,
                'both' // Send via email and push
            );

            $stats['notificationsQueued'] += $queued;
            output('    Queued ' . $queued . ' notification(s)');

            // Log that we sent notification for this interval
            $notificationKey = 'expiring_' . $daysUntilExpiry . 'd';
            logNotificationSent($documentGateway, $documentID, $notificationKey, $daysRemaining);
        }
    }
}

// Mark expired documents if requested
if ($markExpired) {
    output('');
    output('Checking for expired documents to mark...', true);

    $expiredDocuments = $documentGateway->selectExpiredDocumentsToUpdate();
    output('Found ' . count($expiredDocuments) . ' document(s) to mark as expired');

    foreach ($expiredDocuments as $doc) {
        $documentID = $doc['gibbonGovernmentDocumentID'];
        $documentType = $doc['documentTypeDisplay'];

        output('  Marking document #' . $documentID . ' (' . $documentType . ') as expired');

        if (!$dryRun) {
            $documentGateway->markAsExpired($documentID);
            $stats['documentsMarkedExpired']++;
        } else {
            output('    [DRY RUN] Would mark as expired');
            $stats['documentsMarkedExpired']++;
        }
    }
}

// Summary
output('', true);
output('Processing complete', true);
output('==================', true);
output('Documents checked: ' . $stats['documentsChecked'], true);
output('Notifications queued: ' . $stats['notificationsQueued'], true);
output('Documents marked expired: ' . $stats['documentsMarkedExpired'], true);
output('Skipped (already notified): ' . $stats['skippedAlreadyNotified'], true);
output('Errors: ' . $stats['errors'], true);

// Exit with appropriate code
exit($stats['errors'] > 0 ? 1 : 0);

// =========================================================================
// Helper Functions
// =========================================================================

/**
 * Build notification body text.
 *
 * @param string $personName
 * @param string $documentType
 * @param string $expiryDate
 * @param int $daysRemaining
 * @return string
 */
function buildNotificationBody($personName, $documentType, $expiryDate, $daysRemaining) {
    $formattedDate = date('F j, Y', strtotime($expiryDate));

    if ($daysRemaining <= 7) {
        $urgency = 'URGENT: ';
    } elseif ($daysRemaining <= 14) {
        $urgency = 'Important: ';
    } else {
        $urgency = '';
    }

    return sprintf(
        "%sThe %s document for %s will expire on %s (%d days remaining). Please upload a renewed document to maintain compliance.",
        $urgency,
        $documentType,
        $personName,
        $formattedDate,
        $daysRemaining
    );
}

/**
 * Get recipient person IDs for a document notification.
 * Returns family adults (parents/guardians) for the document owner.
 *
 * @param GovernmentDocumentGateway $gateway
 * @param int $personID Document owner's person ID
 * @param array $doc Document data
 * @return array Array of gibbonPersonID values
 */
function getDocumentRecipients($gateway, $personID, $doc) {
    global $container;

    // Get the database connection
    $pdo = $container->get('db');

    // First, find the family for this person
    $sql = "SELECT DISTINCT fa.gibbonPersonID
            FROM gibbonPerson p
            LEFT JOIN gibbonFamilyChild fc ON p.gibbonPersonID = fc.gibbonPersonID
            LEFT JOIN gibbonFamilyAdult fa ON fc.gibbonFamilyID = fa.gibbonFamilyID
            WHERE p.gibbonPersonID = :personID
            AND fa.gibbonPersonID IS NOT NULL
            AND fa.contactEmail = 'Y'";

    $result = $pdo->select($sql, ['personID' => $personID])->fetchAll();
    $recipientIDs = array_column($result, 'gibbonPersonID');

    // If no family adults found (person might be an adult themselves),
    // notify the person directly if they have an email
    if (empty($recipientIDs)) {
        // Check if the person is a family adult
        $sqlAdult = "SELECT fa.gibbonPersonID
                     FROM gibbonFamilyAdult fa
                     WHERE fa.gibbonPersonID = :personID";

        $adultResult = $pdo->select($sqlAdult, ['personID' => $personID])->fetch();

        if ($adultResult) {
            // This is a parent's document - notify them directly
            $recipientIDs = [$personID];
        } else if (!empty($doc['email'])) {
            // Fallback: notify the document owner if they have email
            $recipientIDs = [$personID];
        }
    }

    return array_unique($recipientIDs);
}

/**
 * Check if a notification was already sent recently for this document and interval.
 *
 * @param GovernmentDocumentGateway $gateway
 * @param int $documentID
 * @param string $notificationKey
 * @return bool
 */
function hasRecentNotification($gateway, $documentID, $notificationKey) {
    global $container;

    $pdo = $container->get('db');

    // Check if we logged a notification for this document within the last 24 hours
    // to prevent duplicate notifications from multiple cron runs
    $sql = "SELECT gibbonGovernmentDocumentLogID
            FROM gibbonGovernmentDocumentLog
            WHERE gibbonGovernmentDocumentID = :documentID
            AND action = 'notification_sent'
            AND details LIKE :notificationKey
            AND timestampCreated > DATE_SUB(NOW(), INTERVAL 24 HOUR)
            LIMIT 1";

    $result = $pdo->select($sql, [
        'documentID' => $documentID,
        'notificationKey' => '%' . $notificationKey . '%',
    ])->fetch();

    return !empty($result);
}

/**
 * Log that a notification was sent for tracking purposes.
 *
 * @param GovernmentDocumentGateway $gateway
 * @param int $documentID
 * @param string $notificationKey
 * @param int $daysRemaining
 * @return void
 */
function logNotificationSent($gateway, $documentID, $notificationKey, $daysRemaining) {
    $details = json_encode([
        'notificationKey' => $notificationKey,
        'daysRemaining' => $daysRemaining,
        'sentAt' => date('Y-m-d H:i:s'),
    ]);

    // Use system user ID (1) for automated actions
    $gateway->insertLog(
        $documentID,
        1, // System user
        'notification_sent',
        null,
        null,
        $details,
        null,
        'CLI/checkExpiring.php'
    );
}
