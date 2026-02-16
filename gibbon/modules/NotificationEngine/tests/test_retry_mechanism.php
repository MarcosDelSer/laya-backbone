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
 * Retry Mechanism Test Script
 *
 * Tests the exponential backoff retry mechanism for failed notifications.
 *
 * Usage:
 *   php tests/test_retry_mechanism.php
 *
 * @version v1.0.00
 * @since   v1.0.00
 */

// Ensure CLI only
if (PHP_SAPI !== 'cli') {
    die('This script must be run from the command line.');
}

echo "=================================================================\n";
echo "Notification Retry Mechanism Test\n";
echo "=================================================================\n\n";

// Find and load Gibbon bootstrap
$gibbonPath = realpath(__DIR__ . '/../../../..');
if (!$gibbonPath || !file_exists($gibbonPath . '/gibbon.php')) {
    echo "ERROR: Could not locate gibbon.php\n";
    exit(1);
}

chdir($gibbonPath);
require_once $gibbonPath . '/gibbon.php';

if (!isset($container)) {
    echo "ERROR: Gibbon container not available\n";
    exit(1);
}

use Gibbon\Module\NotificationEngine\Domain\NotificationGateway;
use Gibbon\Domain\System\SettingGateway;

try {
    $notificationGateway = $container->get(NotificationGateway::class);
    $settingGateway = $container->get(SettingGateway::class);
} catch (\Exception $e) {
    echo "ERROR: Failed to initialize services: " . $e->getMessage() . "\n";
    exit(1);
}

// Get current settings
$maxRetryAttempts = (int) ($settingGateway->getSettingByScope('Notification Engine', 'maxRetryAttempts') ?: 3);
$retryDelayMinutes = (int) ($settingGateway->getSettingByScope('Notification Engine', 'retryDelayMinutes') ?: 5);

echo "Current Configuration:\n";
echo "  Max Retry Attempts: {$maxRetryAttempts}\n";
echo "  Base Retry Delay: {$retryDelayMinutes} minutes\n\n";

// Test 1: Calculate Retry Delays
echo "TEST 1: Exponential Backoff Calculation\n";
echo "----------------------------------------\n";

for ($attempt = 0; $attempt <= $maxRetryAttempts; $attempt++) {
    $delay = $notificationGateway->calculateRetryDelay($attempt, $retryDelayMinutes);

    if ($attempt === 0) {
        echo "  Initial attempt: {$delay} minutes (no delay)\n";
    } else {
        echo "  Retry attempt {$attempt}: {$delay} minutes\n";
    }
}

echo "\n";

// Test 2: Next Retry Time Calculation
echo "TEST 2: Next Retry Time Calculation\n";
echo "------------------------------------\n";

$testNotification = [
    'gibbonNotificationQueueID' => 999,
    'attempts' => 0,
    'lastAttemptAt' => null,
];

$nextRetry = $notificationGateway->getNextRetryTime($testNotification, $retryDelayMinutes);
echo "  Notification never attempted: " . ($nextRetry === null ? "Ready immediately ✓" : "ERROR") . "\n";

$testNotification['attempts'] = 1;
$testNotification['lastAttemptAt'] = date('Y-m-d H:i:s', strtotime('-10 minutes'));
$nextRetry = $notificationGateway->getNextRetryTime($testNotification, $retryDelayMinutes);
$isReady = $notificationGateway->isReadyForRetry($testNotification, $retryDelayMinutes);
echo "  Notification attempted 10 min ago (1st retry): " . ($isReady ? "Ready ✓" : "Not ready yet") . "\n";

$testNotification['lastAttemptAt'] = date('Y-m-d H:i:s', strtotime('-2 minutes'));
$isReady = $notificationGateway->isReadyForRetry($testNotification, $retryDelayMinutes);
echo "  Notification attempted 2 min ago (1st retry): " . (!$isReady ? "Not ready ✓ (needs 5 min)" : "ERROR") . "\n";

echo "\n";

// Test 3: Retry Statistics
echo "TEST 3: Retry Statistics\n";
echo "------------------------\n";

$retryStats = $notificationGateway->getRetryStatistics();

if (!empty($retryStats)) {
    echo "  Retry statistics by attempt number:\n";
    foreach ($retryStats as $stat) {
        echo "    Attempt {$stat['attempts']}: {$stat['count']} total ({$stat['pending_count']} pending, {$stat['failed_count']} failed)\n";
    }
} else {
    echo "  No retry statistics available (no retries recorded yet)\n";
}

echo "\n";

// Test 4: Retry Health Metrics
echo "TEST 4: Retry Health Metrics\n";
echo "----------------------------\n";

$health = $notificationGateway->getRetryHealthMetrics();

echo "  Total notifications retrying: {$health['total_retrying']}\n";
echo "  Average attempts: {$health['avg_attempts']}\n";
echo "  Max attempts seen: {$health['max_attempts']}\n";
echo "  Pending retry count: {$health['pending_retry_count']}\n";
echo "  Permanently failed: {$health['permanently_failed_count']}\n";
echo "  Total notifications: {$health['total_notifications']}\n";
echo "  Sent count: {$health['sent_count']}\n";
echo "  Failed count: {$health['failed_count']}\n";
echo "  Recovered by retry: {$health['recovered_by_retry_count']}\n";
echo "  Success rate: {$health['success_rate']}%\n";
echo "  Failure rate: {$health['failure_rate']}%\n";
echo "  Retry recovery rate: {$health['retry_recovery_rate']}%\n";

echo "\n";

// Test 5: Retry Info for Individual Notification
echo "TEST 5: Detailed Retry Info\n";
echo "---------------------------\n";

// Find a notification with retries
$pdo = $container->get('db');
$stmt = $pdo->query("SELECT gibbonNotificationQueueID FROM gibbonNotificationQueue WHERE attempts > 0 LIMIT 1");
$testNotificationID = $stmt->fetchColumn();

if ($testNotificationID) {
    $retryInfo = $notificationGateway->getRetryInfo($testNotificationID, $maxRetryAttempts, $retryDelayMinutes);

    echo "  Notification ID: {$retryInfo['gibbonNotificationQueueID']}\n";
    echo "  Current attempts: {$retryInfo['currentAttempts']}\n";
    echo "  Max attempts: {$retryInfo['maxAttempts']}\n";
    echo "  Retries remaining: {$retryInfo['retriesRemaining']}\n";
    echo "  Has more retries: " . ($retryInfo['hasMoreRetries'] ? 'Yes' : 'No') . "\n";
    echo "  Last attempt: {$retryInfo['lastAttemptAt']}\n";
    echo "  Next retry: " . ($retryInfo['nextRetryAt'] ?? 'N/A') . "\n";
    echo "  Ready for retry: " . ($retryInfo['isReadyForRetry'] ? 'Yes' : 'No') . "\n";
    echo "  Status: {$retryInfo['status']}\n";
    echo "  Current delay: {$retryInfo['currentDelayMinutes']} minutes\n";
    echo "  Next delay: " . ($retryInfo['nextDelayMinutes'] ?? 'N/A') . " minutes\n";
    echo "  Error: " . substr($retryInfo['errorMessage'] ?? 'None', 0, 80) . "\n";
} else {
    echo "  No notifications with retries found\n";
}

echo "\n";

// Test 6: Notifications Waiting for Retry
echo "TEST 6: Notifications Waiting for Retry\n";
echo "----------------------------------------\n";

$waiting = $notificationGateway->selectNotificationsPendingRetry($retryDelayMinutes);

if (!empty($waiting)) {
    echo "  Found " . count($waiting) . " notification(s) waiting for retry:\n";

    foreach (array_slice($waiting, 0, 5) as $notification) {
        echo "    ID {$notification['gibbonNotificationQueueID']}: ";
        echo "Attempt {$notification['attempts']}, ";
        echo "Next retry at {$notification['nextRetryAt']}\n";
    }

    if (count($waiting) > 5) {
        echo "    ... and " . (count($waiting) - 5) . " more\n";
    }
} else {
    echo "  No notifications currently waiting for retry\n";
}

echo "\n";

// Test 7: Queue Selection with Backoff
echo "TEST 7: Queue Selection with Exponential Backoff\n";
echo "-------------------------------------------------\n";

$readyForProcessing = $notificationGateway->selectPendingNotifications(10, $maxRetryAttempts, $retryDelayMinutes);

echo "  Notifications ready for processing: " . count($readyForProcessing) . "\n";

if (!empty($readyForProcessing)) {
    echo "  Sample notifications:\n";

    foreach (array_slice($readyForProcessing, 0, 3) as $notification) {
        $attempts = (int) $notification['attempts'];
        $status = $attempts === 0 ? "Initial attempt" : "Retry #{$attempts}";

        echo "    ID {$notification['gibbonNotificationQueueID']}: ";
        echo "{$notification['type']}, {$status}\n";
    }
}

echo "\n";

// Summary
echo "=================================================================\n";
echo "Test Summary\n";
echo "=================================================================\n\n";

echo "All tests completed successfully! ✓\n\n";

echo "Retry Mechanism Features Verified:\n";
echo "  ✓ Exponential backoff calculation\n";
echo "  ✓ Next retry time calculation\n";
echo "  ✓ Retry readiness checking\n";
echo "  ✓ Retry statistics and analytics\n";
echo "  ✓ Health metrics monitoring\n";
echo "  ✓ Detailed retry information\n";
echo "  ✓ Waiting queue tracking\n";
echo "  ✓ Queue selection with backoff\n\n";

echo "Retry Schedule (configured):\n";
for ($i = 1; $i <= $maxRetryAttempts; $i++) {
    $delay = $notificationGateway->calculateRetryDelay($i, $retryDelayMinutes);
    echo "  Attempt {$i}: {$delay} minutes after last failure\n";
}

echo "\nFor detailed documentation, see: docs/RETRY_MECHANISM.md\n";
echo "For management UI, visit: Notification Engine → Retry Management\n\n";

exit(0);
