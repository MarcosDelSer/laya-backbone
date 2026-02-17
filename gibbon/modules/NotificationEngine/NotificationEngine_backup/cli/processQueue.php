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
 * CLI Notification Queue Processor
 *
 * Processes queued notifications and sends them via email and/or push.
 * Run via cron job: * * * * * php /path/to/gibbon/modules/NotificationEngine/cli/processQueue.php
 *
 * Options:
 *   --limit=N     Process maximum N notifications (default: 50)
 *   --dry-run     Show what would be processed without actually sending
 *   --verbose     Show detailed output
 *   --purge       Purge old sent/failed notifications
 *   --purge-days=N  Days to keep notifications (default: 30)
 *
 * @version v1.0.00
 * @since   v1.0.00
 */

// Ensure CLI only
if (PHP_SAPI !== 'cli') {
    die('This script must be run from the command line.');
}

// Parse command line options
$options = getopt('', ['limit::', 'dry-run', 'verbose', 'purge', 'purge-days::', 'help']);

if (isset($options['help'])) {
    echo "Notification Queue Processor\n";
    echo "============================\n\n";
    echo "Usage: php processQueue.php [options]\n\n";
    echo "Options:\n";
    echo "  --limit=N       Process maximum N notifications (default: 50)\n";
    echo "  --dry-run       Show what would be processed without sending\n";
    echo "  --verbose       Show detailed output\n";
    echo "  --purge         Purge old sent/failed notifications\n";
    echo "  --purge-days=N  Days to keep notifications (default: 30)\n";
    echo "  --help          Show this help message\n\n";
    exit(0);
}

$dryRun = isset($options['dry-run']);
$verbose = isset($options['verbose']);
$doPurge = isset($options['purge']);
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
use Gibbon\Module\NotificationEngine\Domain\NotificationGateway;
use Gibbon\Module\NotificationEngine\Domain\EmailDelivery;
use Gibbon\Module\NotificationEngine\Domain\PushDelivery;
use Gibbon\Domain\System\SettingGateway;

try {
    $notificationGateway = $container->get(NotificationGateway::class);
    $emailDelivery = $container->get(EmailDelivery::class);
    $pushDelivery = $container->get(PushDelivery::class);
    $settingGateway = $container->get(SettingGateway::class);
} catch (\Exception $e) {
    output('Error: Failed to initialize services: ' . $e->getMessage(), true);
    exit(1);
}

output('Notification Queue Processor started', true);
output('Limit: ' . $limit . ', Dry Run: ' . ($dryRun ? 'Yes' : 'No'));

// Get batch size from settings
$batchSize = (int) ($settingGateway->getSettingByScope('Notification Engine', 'queueBatchSize') ?: 50);
$maxAttempts = (int) ($settingGateway->getSettingByScope('Notification Engine', 'maxRetryAttempts') ?: 3);

// Use the smaller of limit and batch size
$processLimit = min($limit, $batchSize);

// Get queue statistics before processing
$statsBefore = $notificationGateway->getQueueStatistics();
output('Queue status - Pending: ' . $statsBefore['pending'] . ', Processing: ' . $statsBefore['processing'] . ', Failed: ' . $statsBefore['failed']);

// Purge old notifications if requested
if ($doPurge) {
    output('Purging notifications older than ' . $purgeDays . ' days...', true);

    if (!$dryRun) {
        $purged = $notificationGateway->purgeOldNotifications($purgeDays);
        output('Purged ' . $purged . ' old notification(s)', true);
    } else {
        output('[DRY RUN] Would purge old notifications', true);
    }
}

// Get pending notifications
$pendingNotifications = $notificationGateway->selectPendingNotifications($processLimit, $maxAttempts);

if (empty($pendingNotifications)) {
    output('No pending notifications to process', true);
    exit(0);
}

output('Found ' . count($pendingNotifications) . ' pending notification(s) to process', true);

// Process each notification
$processed = 0;
$emailSent = 0;
$pushSent = 0;
$skipped = 0;
$failed = 0;

foreach ($pendingNotifications as $notification) {
    $notificationID = $notification['gibbonNotificationQueueID'];
    $channel = $notification['channel'];
    $type = $notification['type'];

    output('Processing notification #' . $notificationID . ' (' . $type . ', channel: ' . $channel . ')');

    if ($dryRun) {
        output('[DRY RUN] Would process notification #' . $notificationID);
        $processed++;
        continue;
    }

    // Mark as processing
    $notificationGateway->markProcessing($notificationID);

    $emailResult = null;
    $pushResult = null;
    $success = false;

    // Build recipient data
    $recipient = [
        'gibbonPersonID' => $notification['gibbonPersonID'],
        'recipientEmail' => $notification['recipientEmail'],
        'recipientPreferredName' => $notification['recipientPreferredName'] ?? '',
        'recipientSurname' => $notification['recipientSurname'] ?? '',
        'receiveNotificationEmails' => $notification['receiveNotificationEmails'] ?? 'Y',
    ];

    // Process email if channel is email or both
    if (in_array($channel, ['email', 'both'])) {
        output('  Sending email to ' . $recipient['recipientEmail']);

        $emailResult = $emailDelivery->send($notification, $recipient);

        if ($emailResult['success']) {
            output('  Email sent successfully');
            $emailSent++;
            $success = true;
        } elseif (!empty($emailResult['skipped'])) {
            output('  Email skipped: ' . ($emailResult['error']['message'] ?? 'User preferences'));
            $skipped++;
            // For 'both' channel, skipped email doesn't mean failure
            if ($channel === 'email') {
                $success = true; // Mark as success since it was intentionally skipped
            }
        } else {
            output('  Email failed: ' . ($emailResult['error']['message'] ?? 'Unknown error'));
        }
    }

    // Process push if channel is push or both
    if (in_array($channel, ['push', 'both'])) {
        output('  Sending push notification');

        $pushResult = $pushDelivery->processQueuedNotification($notificationID);

        if (!empty($pushResult['success'])) {
            output('  Push sent successfully');
            $pushSent++;
            $success = true;
        } elseif (!empty($pushResult['skipped'])) {
            output('  Push skipped: ' . ($pushResult['error']['message'] ?? 'User preferences'));
            $skipped++;
            // For 'both' channel, skipped push doesn't mean failure
            if ($channel === 'push') {
                $success = true; // Mark as success since it was intentionally skipped
            }
        } else {
            output('  Push failed: ' . ($pushResult['error']['message'] ?? 'Unknown error'));
        }
    }

    // Update notification status based on results
    if ($success) {
        $notificationGateway->markSent($notificationID);
        output('  Notification #' . $notificationID . ' marked as sent');
    } else {
        // Combine error messages
        $errorMessages = [];
        if ($emailResult && !$emailResult['success'] && empty($emailResult['skipped'])) {
            $errorMessages[] = 'Email: ' . ($emailResult['error']['message'] ?? 'Failed');
        }
        if ($pushResult && empty($pushResult['success']) && empty($pushResult['skipped'])) {
            $errorMessages[] = 'Push: ' . ($pushResult['error']['message'] ?? 'Failed');
        }
        $errorMessage = implode('; ', $errorMessages);

        $notificationGateway->markFailed($notificationID, $errorMessage, $maxAttempts);
        output('  Notification #' . $notificationID . ' marked as failed');
        $failed++;
    }

    $processed++;
}

// Get queue statistics after processing
$statsAfter = $notificationGateway->getQueueStatistics();

// Summary
output('', true);
output('Processing complete', true);
output('==================', true);
output('Processed: ' . $processed, true);
output('Emails sent: ' . $emailSent, true);
output('Push sent: ' . $pushSent, true);
output('Skipped (user preferences): ' . $skipped, true);
output('Failed: ' . $failed, true);
output('', true);
output('Queue status after - Pending: ' . $statsAfter['pending'] . ', Sent: ' . $statsAfter['sent'] . ', Failed: ' . $statsAfter['failed'], true);

// Handle any invalid tokens found
$invalidTokens = $pushDelivery->getInvalidTokens();
if (!empty($invalidTokens)) {
    output('', true);
    output('Invalid FCM tokens deactivated: ' . count($invalidTokens), true);
    foreach ($invalidTokens as $token) {
        output('  - ' . substr($token, 0, 20) . '...');
    }
}

// Exit with appropriate code
exit($failed > 0 ? 1 : 0);
