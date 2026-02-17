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

namespace Gibbon\Module\NotificationEngine\Service;

use Gibbon\Module\NotificationEngine\Domain\NotificationGateway;
use Gibbon\Domain\System\SettingGateway;

/**
 * DeliveryRulesService
 *
 * Business logic for notification delivery rules including:
 * - Retry logic with exponential backoff
 * - Delivery scheduling and timing
 * - Queue health monitoring
 * - Delivery eligibility determination
 *
 * Extracts delivery rules business logic from NotificationGateway.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class DeliveryRulesService
{
    /**
     * @var NotificationGateway
     */
    protected $notificationGateway;

    /**
     * @var SettingGateway
     */
    protected $settingGateway;

    /**
     * Constructor.
     *
     * @param NotificationGateway $notificationGateway Notification gateway
     * @param SettingGateway $settingGateway Settings gateway
     */
    public function __construct(
        NotificationGateway $notificationGateway,
        SettingGateway $settingGateway
    ) {
        $this->notificationGateway = $notificationGateway;
        $this->settingGateway = $settingGateway;
    }

    // =========================================================================
    // RETRY LOGIC
    // =========================================================================

    /**
     * Calculate retry delay in minutes based on exponential backoff.
     *
     * Formula: retryDelayMinutes * 2^(attempts-1) minutes
     * - Attempt 1: retryDelayMinutes (e.g., 5 minutes)
     * - Attempt 2: retryDelayMinutes * 2 (e.g., 10 minutes)
     * - Attempt 3: retryDelayMinutes * 4 (e.g., 20 minutes)
     *
     * @param int $attemptNumber Current attempt number (1-based)
     * @param int $retryDelayMinutes Base delay in minutes (default from settings)
     * @return int Delay in minutes
     */
    public function calculateRetryDelay($attemptNumber, $retryDelayMinutes = null)
    {
        if ($retryDelayMinutes === null) {
            $retryDelayMinutes = $this->getRetryDelayMinutes();
        }

        if ($attemptNumber <= 0) {
            return 0; // No delay for first attempt
        }

        // Exponential backoff: 2^(attempts-1) * base delay
        return (int) ($retryDelayMinutes * pow(2, $attemptNumber - 1));
    }

    /**
     * Get the next retry timestamp for a notification.
     *
     * @param array $notification Notification data with 'attempts' and 'lastAttemptAt'
     * @param int|null $retryDelayMinutes Base delay in minutes (null to use setting)
     * @return string|null ISO 8601 timestamp when retry should occur, or null if ready now
     */
    public function getNextRetryTime($notification, $retryDelayMinutes = null)
    {
        $attempts = (int) ($notification['attempts'] ?? 0);

        // If never attempted or no delay needed
        if ($attempts === 0 || empty($notification['lastAttemptAt'])) {
            return null; // Ready immediately
        }

        $delayMinutes = $this->calculateRetryDelay($attempts, $retryDelayMinutes);
        $lastAttempt = strtotime($notification['lastAttemptAt']);
        $nextRetry = $lastAttempt + ($delayMinutes * 60);

        return date('Y-m-d H:i:s', $nextRetry);
    }

    /**
     * Check if a notification is ready for retry based on exponential backoff.
     *
     * @param array $notification Notification data
     * @param int|null $retryDelayMinutes Base delay in minutes (null to use setting)
     * @return bool True if ready for retry
     */
    public function isReadyForRetry($notification, $retryDelayMinutes = null)
    {
        $nextRetryTime = $this->getNextRetryTime($notification, $retryDelayMinutes);

        // If null, ready immediately
        if ($nextRetryTime === null) {
            return true;
        }

        // Compare with current time
        return strtotime($nextRetryTime) <= time();
    }

    /**
     * Get detailed retry information for a notification.
     *
     * Returns comprehensive retry details including attempt counts,
     * retry timing, and remaining retries.
     *
     * @param int $gibbonNotificationQueueID Notification ID
     * @param int|null $maxAttempts Maximum retry attempts (null to use setting)
     * @param int|null $retryDelayMinutes Base delay in minutes (null to use setting)
     * @return array|null Retry information or null if not found
     */
    public function getRetryInfo($gibbonNotificationQueueID, $maxAttempts = null, $retryDelayMinutes = null)
    {
        if ($maxAttempts === null) {
            $maxAttempts = $this->getMaxAttempts();
        }

        $notification = $this->notificationGateway->getNotificationByID($gibbonNotificationQueueID);

        if (!$notification) {
            return null;
        }

        $attempts = (int) $notification['attempts'];
        $nextRetryTime = $this->getNextRetryTime($notification, $retryDelayMinutes);
        $isReady = $this->isReadyForRetry($notification, $retryDelayMinutes);
        $hasMoreRetries = $attempts < $maxAttempts;

        return [
            'gibbonNotificationQueueID' => $gibbonNotificationQueueID,
            'currentAttempts' => $attempts,
            'maxAttempts' => $maxAttempts,
            'retriesRemaining' => max(0, $maxAttempts - $attempts),
            'hasMoreRetries' => $hasMoreRetries,
            'lastAttemptAt' => $notification['lastAttemptAt'],
            'nextRetryAt' => $nextRetryTime,
            'isReadyForRetry' => $isReady,
            'status' => $notification['status'],
            'errorMessage' => $notification['errorMessage'],
            'currentDelayMinutes' => $attempts > 0 ? $this->calculateRetryDelay($attempts, $retryDelayMinutes) : 0,
            'nextDelayMinutes' => $hasMoreRetries ? $this->calculateRetryDelay($attempts + 1, $retryDelayMinutes) : null,
        ];
    }

    /**
     * Check if a notification has exhausted its retry attempts.
     *
     * @param array $notification Notification data with 'attempts'
     * @param int|null $maxAttempts Maximum retry attempts (null to use setting)
     * @return bool True if exhausted
     */
    public function hasExhaustedRetries($notification, $maxAttempts = null)
    {
        if ($maxAttempts === null) {
            $maxAttempts = $this->getMaxAttempts();
        }

        $attempts = (int) ($notification['attempts'] ?? 0);
        return $attempts >= $maxAttempts;
    }

    // =========================================================================
    // DELIVERY SCHEDULING
    // =========================================================================

    /**
     * Get pending notifications ready for delivery with retry backoff applied.
     *
     * Only returns notifications that are ready to be sent based on:
     * - Attempt count less than max attempts
     * - Sufficient time has passed since last attempt (exponential backoff)
     *
     * @param int $limit Maximum number to fetch
     * @return array Notifications ready for delivery
     */
    public function getPendingNotificationsForDelivery($limit = 50)
    {
        $maxAttempts = $this->getMaxAttempts();
        $retryDelayMinutes = $this->getRetryDelayMinutes();

        return $this->notificationGateway->selectPendingNotifications(
            $limit,
            $maxAttempts,
            $retryDelayMinutes
        );
    }

    /**
     * Get notifications that are pending retry (waiting for backoff delay).
     *
     * These are pending notifications that aren't ready yet due to exponential backoff.
     *
     * @return array Notifications waiting for retry
     */
    public function getNotificationsPendingRetry()
    {
        $retryDelayMinutes = $this->getRetryDelayMinutes();
        return $this->notificationGateway->selectNotificationsPendingRetry($retryDelayMinutes);
    }

    /**
     * Check if delivery should be attempted for a notification.
     *
     * Considers retry attempts, backoff timing, and notification status.
     *
     * @param array $notification Notification data
     * @return bool True if delivery should be attempted
     */
    public function shouldAttemptDelivery($notification)
    {
        // Only attempt delivery for pending notifications
        if ($notification['status'] !== 'pending') {
            return false;
        }

        // Check if retries are exhausted
        if ($this->hasExhaustedRetries($notification)) {
            return false;
        }

        // Check if enough time has passed for retry
        return $this->isReadyForRetry($notification);
    }

    // =========================================================================
    // QUEUE HEALTH MONITORING
    // =========================================================================

    /**
     * Get retry statistics grouped by attempt number.
     *
     * Returns counts of notifications at each retry attempt level.
     *
     * @return array Retry statistics
     */
    public function getRetryStatistics()
    {
        return $this->notificationGateway->getRetryStatistics();
    }

    /**
     * Get overall retry queue health metrics.
     *
     * Returns comprehensive health status including:
     * - Total notifications in retry
     * - Average attempts
     * - Oldest pending retry
     * - Success/failure rates
     * - Recovery rate from retries
     *
     * @return array Health metrics
     */
    public function getRetryHealthMetrics()
    {
        return $this->notificationGateway->getRetryHealthMetrics();
    }

    /**
     * Get queue statistics (counts by status).
     *
     * @return array Queue statistics with counts for each status
     */
    public function getQueueStatistics()
    {
        return $this->notificationGateway->getQueueStatistics();
    }

    /**
     * Assess overall queue health status.
     *
     * Analyzes queue metrics to determine health: healthy, warning, critical.
     *
     * @return array Health assessment with status and recommendations
     */
    public function assessQueueHealth()
    {
        $stats = $this->getQueueStatistics();
        $retryMetrics = $this->getRetryHealthMetrics();

        $status = 'healthy';
        $recommendations = [];

        // Check pending queue size
        $pendingCount = $stats['pending'] ?? 0;
        if ($pendingCount > 1000) {
            $status = 'critical';
            $recommendations[] = 'Large pending queue detected. Consider increasing processing capacity.';
        } elseif ($pendingCount > 500) {
            $status = ($status === 'healthy') ? 'warning' : $status;
            $recommendations[] = 'Pending queue growing. Monitor processing rate.';
        }

        // Check failure rate
        $failureRate = $retryMetrics['failure_rate'] ?? 0;
        if ($failureRate > 20) {
            $status = 'critical';
            $recommendations[] = 'High failure rate detected. Check email/push configuration.';
        } elseif ($failureRate > 10) {
            $status = ($status === 'healthy') ? 'warning' : $status;
            $recommendations[] = 'Elevated failure rate. Review error logs.';
        }

        // Check retry recovery rate
        $retryRecoveryRate = $retryMetrics['retry_recovery_rate'] ?? 0;
        if ($retryRecoveryRate > 30) {
            $status = ($status === 'healthy') ? 'warning' : $status;
            $recommendations[] = 'Many notifications requiring retries. Check for intermittent issues.';
        }

        return [
            'status' => $status,
            'recommendations' => $recommendations,
            'metrics' => [
                'pending_count' => $pendingCount,
                'failure_rate' => $failureRate,
                'retry_recovery_rate' => $retryRecoveryRate,
            ],
            'timestamp' => date('Y-m-d H:i:s'),
        ];
    }

    // =========================================================================
    // DELIVERY ELIGIBILITY
    // =========================================================================

    /**
     * Check if a notification can be delivered to a recipient.
     *
     * Considers notification preferences and delivery channel.
     *
     * @param int $gibbonPersonID Recipient person ID
     * @param string $type Notification type
     * @param string $channel Delivery channel (email, push, both)
     * @return array Eligibility result with canDeliver flag and reasons
     */
    public function canDeliverToRecipient($gibbonPersonID, $type, $channel = 'both')
    {
        $canDeliver = true;
        $reasons = [];

        // Check channel-specific preferences
        if ($channel === 'email' || $channel === 'both') {
            $emailEnabled = $this->notificationGateway->isEmailEnabled($gibbonPersonID, $type);
            if (!$emailEnabled) {
                $canDeliver = false;
                $reasons[] = 'Email notifications disabled for this type';
            }
        }

        if ($channel === 'push' || $channel === 'both') {
            $pushEnabled = $this->notificationGateway->isPushEnabled($gibbonPersonID, $type);
            if (!$pushEnabled) {
                // If both channels are requested and email is enabled, we can still deliver via email
                if ($channel !== 'both' || !($this->notificationGateway->isEmailEnabled($gibbonPersonID, $type))) {
                    $canDeliver = false;
                    $reasons[] = 'Push notifications disabled for this type';
                }
            }
        }

        return [
            'canDeliver' => $canDeliver,
            'reasons' => $reasons,
            'effectiveChannel' => $this->determineEffectiveChannel($gibbonPersonID, $type, $channel),
        ];
    }

    /**
     * Determine the effective delivery channel based on preferences.
     *
     * If user has disabled one channel, return the other if available.
     *
     * @param int $gibbonPersonID Recipient person ID
     * @param string $type Notification type
     * @param string $requestedChannel Requested channel (email, push, both)
     * @return string Effective channel (email, push, both, or none)
     */
    public function determineEffectiveChannel($gibbonPersonID, $type, $requestedChannel = 'both')
    {
        $emailEnabled = $this->notificationGateway->isEmailEnabled($gibbonPersonID, $type);
        $pushEnabled = $this->notificationGateway->isPushEnabled($gibbonPersonID, $type);

        if ($requestedChannel === 'email') {
            return $emailEnabled ? 'email' : 'none';
        }

        if ($requestedChannel === 'push') {
            return $pushEnabled ? 'push' : 'none';
        }

        // For 'both', determine what's actually available
        if ($emailEnabled && $pushEnabled) {
            return 'both';
        } elseif ($emailEnabled) {
            return 'email';
        } elseif ($pushEnabled) {
            return 'push';
        }

        return 'none';
    }

    // =========================================================================
    // SETTINGS HELPERS
    // =========================================================================

    /**
     * Get maximum retry attempts from settings.
     *
     * @return int Maximum attempts (default: 3)
     */
    protected function getMaxAttempts()
    {
        $maxAttempts = $this->settingGateway->getSettingByScope('Notification Engine', 'maxRetryAttempts');
        return $maxAttempts ? (int) $maxAttempts : 3;
    }

    /**
     * Get retry delay in minutes from settings.
     *
     * @return int Retry delay minutes (default: 5)
     */
    protected function getRetryDelayMinutes()
    {
        $retryDelay = $this->settingGateway->getSettingByScope('Notification Engine', 'retryDelayMinutes');
        return $retryDelay ? (int) $retryDelay : 5;
    }
}
