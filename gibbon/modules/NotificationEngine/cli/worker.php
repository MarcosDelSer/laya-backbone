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
 * Notification Queue Worker (Loop-Based)
 *
 * Continuously processes notification queue in a loop.
 * Designed for Docker/containerized environments as an alternative to cron.
 * Managed via Supervisor or similar process manager.
 *
 * Usage:
 *   php worker.php [--interval=N] [--limit=N] [--verbose]
 *
 * Options:
 *   --interval=N  Sleep interval between runs in seconds (default: 60)
 *   --limit=N     Process maximum N notifications per run (default: 50)
 *   --verbose     Show detailed output
 *
 * @version v1.0.00
 * @since   v1.0.00
 */

// Ensure CLI only
if (PHP_SAPI !== 'cli') {
    die('This script must be run from the command line.');
}

// Parse command line options
$options = getopt('', ['interval::', 'limit::', 'verbose', 'help']);

if (isset($options['help'])) {
    echo "Notification Queue Worker\n";
    echo "=========================\n\n";
    echo "Continuously processes notification queue in a loop.\n\n";
    echo "Usage: php worker.php [options]\n\n";
    echo "Options:\n";
    echo "  --interval=N  Sleep interval between runs in seconds (default: 60)\n";
    echo "  --limit=N     Process maximum N notifications per run (default: 50)\n";
    echo "  --verbose     Show detailed output\n";
    echo "  --help        Show this help message\n\n";
    echo "Example:\n";
    echo "  php worker.php --interval=30 --limit=100 --verbose\n\n";
    exit(0);
}

$interval = isset($options['interval']) ? (int) $options['interval'] : 60;
$limit = isset($options['limit']) ? (int) $options['limit'] : 50;
$verbose = isset($options['verbose']);

// Validate options
if ($interval < 1 || $interval > 3600) {
    echo "Error: Interval must be between 1 and 3600 seconds\n";
    exit(1);
}

if ($limit < 1 || $limit > 1000) {
    echo "Error: Limit must be between 1 and 1000\n";
    exit(1);
}

// Helper function for output
function output($message, $forceOutput = false) {
    global $verbose;
    if ($verbose || $forceOutput) {
        echo '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n";
        flush();
    }
}

// Signal handling for graceful shutdown
$shouldStop = false;

if (function_exists('pcntl_signal')) {
    pcntl_signal(SIGTERM, function() use (&$shouldStop) {
        global $shouldStop;
        output('Received SIGTERM, shutting down gracefully...', true);
        $shouldStop = true;
    });

    pcntl_signal(SIGINT, function() use (&$shouldStop) {
        global $shouldStop;
        output('Received SIGINT, shutting down gracefully...', true);
        $shouldStop = true;
    });

    output('Signal handlers registered', true);
} else {
    output('Warning: pcntl extension not available, signals won\'t be handled', true);
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

output('Notification Queue Worker started', true);
output('Interval: ' . $interval . 's, Limit: ' . $limit . ' per run', true);
output('Press Ctrl+C to stop', true);

// Get configuration
$batchSize = (int) ($settingGateway->getSettingByScope('Notification Engine', 'queueBatchSize') ?: 50);
$maxAttempts = (int) ($settingGateway->getSettingByScope('Notification Engine', 'maxRetryAttempts') ?: 3);

// Use the smaller of limit and batch size
$processLimit = min($limit, $batchSize);

// Statistics
$totalProcessed = 0;
$totalEmailSent = 0;
$totalPushSent = 0;
$totalSkipped = 0;
$totalFailed = 0;
$iterations = 0;
$startTime = time();

// Main worker loop
while (!$shouldStop) {
    $iterationStart = microtime(true);
    $iterations++;

    output('');
    output('=== Iteration #' . $iterations . ' ===', true);

    // Process signals (if available)
    if (function_exists('pcntl_signal_dispatch')) {
        pcntl_signal_dispatch();
    }

    if ($shouldStop) {
        break;
    }

    try {
        // Get queue statistics
        $stats = $notificationGateway->getQueueStatistics();
        output('Queue status - Pending: ' . $stats['pending'] . ', Processing: ' . $stats['processing'] . ', Failed: ' . $stats['failed']);

        // Get pending notifications
        $pendingNotifications = $notificationGateway->selectPendingNotifications($processLimit, $maxAttempts);

        if (empty($pendingNotifications)) {
            output('No pending notifications to process');
        } else {
            output('Found ' . count($pendingNotifications) . ' pending notification(s) to process', true);

            // Process each notification
            $processed = 0;
            $emailSent = 0;
            $pushSent = 0;
            $skipped = 0;
            $failed = 0;

            foreach ($pendingNotifications as $notification) {
                if ($shouldStop) {
                    output('Stop signal received, halting processing', true);
                    break;
                }

                $notificationID = $notification['gibbonNotificationQueueID'];
                $channel = $notification['channel'];
                $type = $notification['type'];

                output('Processing notification #' . $notificationID . ' (' . $type . ', channel: ' . $channel . ')');

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
                        if ($channel === 'email') {
                            $success = true;
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
                        if ($channel === 'push') {
                            $success = true;
                        }
                    } else {
                        output('  Push failed: ' . ($pushResult['error']['message'] ?? 'Unknown error'));
                    }
                }

                // Update notification status
                if ($success) {
                    $notificationGateway->markSent($notificationID);
                    output('  Notification #' . $notificationID . ' marked as sent');
                } else {
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

            // Update totals
            $totalProcessed += $processed;
            $totalEmailSent += $emailSent;
            $totalPushSent += $pushSent;
            $totalSkipped += $skipped;
            $totalFailed += $failed;

            // Iteration summary
            output('Iteration complete - Processed: ' . $processed . ', Emails: ' . $emailSent . ', Push: ' . $pushSent . ', Skipped: ' . $skipped . ', Failed: ' . $failed, true);
        }

        // Handle invalid tokens
        $invalidTokens = $pushDelivery->getInvalidTokens();
        if (!empty($invalidTokens)) {
            output('Invalid FCM tokens deactivated: ' . count($invalidTokens), true);
        }

    } catch (\Exception $e) {
        output('Error during processing: ' . $e->getMessage(), true);
        output('Stack trace: ' . $e->getTraceAsString());
    }

    // Calculate sleep time (maintain consistent interval)
    $iterationTime = microtime(true) - $iterationStart;
    $sleepTime = max(1, $interval - (int) $iterationTime);

    output('Iteration took ' . round($iterationTime, 2) . 's, sleeping for ' . $sleepTime . 's');

    // Sleep with interruptible sleep (check for signals every second)
    $slept = 0;
    while ($slept < $sleepTime && !$shouldStop) {
        sleep(1);
        $slept++;

        // Process signals
        if (function_exists('pcntl_signal_dispatch')) {
            pcntl_signal_dispatch();
        }
    }
}

// Shutdown summary
$uptime = time() - $startTime;
output('', true);
output('Worker shutting down', true);
output('===================', true);
output('Uptime: ' . gmdate('H:i:s', $uptime) . ' (' . $uptime . ' seconds)', true);
output('Iterations: ' . $iterations, true);
output('Total processed: ' . $totalProcessed, true);
output('Total emails sent: ' . $totalEmailSent, true);
output('Total push sent: ' . $totalPushSent, true);
output('Total skipped: ' . $totalSkipped, true);
output('Total failed: ' . $totalFailed, true);
output('Average per iteration: ' . ($iterations > 0 ? round($totalProcessed / $iterations, 2) : 0), true);
output('', true);
output('Goodbye! 👋', true);

exit(0);
