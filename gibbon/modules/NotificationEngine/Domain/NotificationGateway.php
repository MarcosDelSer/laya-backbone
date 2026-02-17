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

namespace Gibbon\Module\NotificationEngine\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\QueryableGateway;
use Gibbon\Module\NotificationEngine\Service\DeliveryRulesService;
use Gibbon\Module\NotificationEngine\Service\PreferenceService;

/**
 * NotificationGateway
 *
 * Gateway for notification queue, template, preference, and FCM token operations.
 * Provides data access layer for notifications.
 *
 * Business logic has been moved to service classes:
 * - DeliveryRulesService: Retry logic, delivery scheduling, queue health
 * - PreferenceService: Notification preference management
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class NotificationGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonNotificationQueue';
    private static $primaryKey = 'gibbonNotificationQueueID';
    private static $searchableColumns = ['gibbonNotificationQueue.title', 'gibbonNotificationQueue.body'];

    /**
     * @var DeliveryRulesService|null
     */
    private $deliveryRulesService;

    /**
     * @var PreferenceService|null
     */
    private $preferenceService;

    /**
     * Set the delivery rules service.
     *
     * @param DeliveryRulesService $service
     * @return void
     */
    public function setDeliveryRulesService(DeliveryRulesService $service)
    {
        $this->deliveryRulesService = $service;
    }

    /**
     * Set the preference service.
     *
     * @param PreferenceService $service
     * @return void
     */
    public function setPreferenceService(PreferenceService $service)
    {
        $this->preferenceService = $service;
    }

    // =========================================================================
    // QUEUE OPERATIONS
    // =========================================================================

    /**
     * Query notification queue with pagination support.
     *
     * @param QueryCriteria $criteria
     * @param string|null $status Filter by status (pending, processing, sent, failed)
     * @return \Gibbon\Domain\DataSet
     */
    public function queryQueue(QueryCriteria $criteria, $status = null)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonNotificationQueue.gibbonNotificationQueueID',
                'gibbonNotificationQueue.gibbonPersonID',
                'gibbonNotificationQueue.type',
                'gibbonNotificationQueue.title',
                'gibbonNotificationQueue.body',
                'gibbonNotificationQueue.channel',
                'gibbonNotificationQueue.status',
                'gibbonNotificationQueue.attempts',
                'gibbonNotificationQueue.lastAttemptAt',
                'gibbonNotificationQueue.sentAt',
                'gibbonNotificationQueue.errorMessage',
                'gibbonNotificationQueue.timestampCreated',
                'person.preferredName AS recipientPreferredName',
                'person.surname AS recipientSurname',
                'person.email AS recipientEmail',
            ])
            ->leftJoin('gibbonPerson AS person', 'person.gibbonPersonID = gibbonNotificationQueue.gibbonPersonID');

        if ($status !== null) {
            $query->where('gibbonNotificationQueue.status = :status')
                  ->bindValue('status', $status);
        }

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query pending notifications ready for processing with exponential backoff.
     *
     * Only returns notifications that are ready to be retried based on:
     * - Attempt count less than max attempts
     * - Sufficient time has passed since last attempt (exponential backoff)
     *
     * @param int $limit Maximum number to fetch
     * @param int $maxAttempts Maximum retry attempts before giving up
     * @param int $retryDelayMinutes Base delay in minutes (exponential backoff applied)
     * @return array
     */
    public function selectPendingNotifications($limit = 50, $maxAttempts = 3, $retryDelayMinutes = 5)
    {
        $data = [
            'limit' => (int) $limit,
            'maxAttempts' => (int) $maxAttempts,
        ];

        // Build SQL with exponential backoff logic
        // For first attempt (attempts = 0): no delay needed
        // For retries (attempts > 0): require delay based on 2^(attempts-1) * retryDelayMinutes
        $sql = "SELECT q.*,
                       p.preferredName AS recipientPreferredName,
                       p.surname AS recipientSurname,
                       p.email AS recipientEmail,
                       p.receiveNotificationEmails
                FROM gibbonNotificationQueue q
                INNER JOIN gibbonPerson AS p ON p.gibbonPersonID = q.gibbonPersonID
                WHERE q.status = 'pending'
                AND q.attempts < :maxAttempts
                AND (
                    -- First attempt: no delay
                    q.attempts = 0
                    OR
                    -- Retry with exponential backoff
                    -- Delay = retryDelayMinutes * 2^(attempts-1) minutes
                    -- Attempt 1: " . $retryDelayMinutes . " min
                    -- Attempt 2: " . ($retryDelayMinutes * 2) . " min
                    -- Attempt 3: " . ($retryDelayMinutes * 4) . " min
                    q.lastAttemptAt <= DATE_SUB(NOW(), INTERVAL (
                        " . $retryDelayMinutes . " * POW(2, q.attempts - 1)
                    ) MINUTE)
                )
                ORDER BY q.timestampCreated ASC
                LIMIT :limit";

        return $this->db()->select($sql, $data)->fetchAll();
    }

    /**
     * Get a single notification by ID.
     *
     * @param int $gibbonNotificationQueueID
     * @return array|false
     */
    public function getNotificationByID($gibbonNotificationQueueID)
    {
        $data = ['gibbonNotificationQueueID' => $gibbonNotificationQueueID];
        $sql = "SELECT q.*,
                       p.preferredName AS recipientPreferredName,
                       p.surname AS recipientSurname,
                       p.email AS recipientEmail
                FROM gibbonNotificationQueue q
                LEFT JOIN gibbonPerson AS p ON p.gibbonPersonID = q.gibbonPersonID
                WHERE q.gibbonNotificationQueueID = :gibbonNotificationQueueID";

        return $this->db()->selectOne($sql, $data);
    }

    /**
     * Insert a new notification to the queue.
     *
     * @param array $data Notification data
     * @return int|false The new notification ID or false on failure
     */
    public function insertNotification(array $data)
    {
        $fields = [
            'gibbonPersonID',
            'type',
            'title',
            'body',
            'data',
            'channel',
            'status',
        ];

        $insertData = array_intersect_key($data, array_flip($fields));

        // Ensure JSON data is encoded properly
        if (isset($insertData['data']) && is_array($insertData['data'])) {
            $insertData['data'] = json_encode($insertData['data']);
        }

        // Default status to pending
        if (!isset($insertData['status'])) {
            $insertData['status'] = 'pending';
        }

        return $this->insert($insertData);
    }

    /**
     * Queue a notification for multiple recipients.
     *
     * @param array $recipientIDs Array of gibbonPersonID values
     * @param string $type Notification type
     * @param string $title Notification title
     * @param string $body Notification body
     * @param array $payloadData Additional data payload
     * @param string $channel Channel (email, push, both)
     * @return int Number of notifications queued
     */
    public function queueBulkNotification(array $recipientIDs, $type, $title, $body, $payloadData = [], $channel = 'both')
    {
        $count = 0;
        foreach ($recipientIDs as $gibbonPersonID) {
            $result = $this->insertNotification([
                'gibbonPersonID' => $gibbonPersonID,
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'data' => $payloadData,
                'channel' => $channel,
            ]);
            if ($result !== false) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Mark notification as processing.
     *
     * @param int $gibbonNotificationQueueID
     * @return bool
     */
    public function markProcessing($gibbonNotificationQueueID)
    {
        $data = ['gibbonNotificationQueueID' => $gibbonNotificationQueueID];
        $sql = "UPDATE gibbonNotificationQueue
                SET status = 'processing',
                    attempts = attempts + 1,
                    lastAttemptAt = NOW()
                WHERE gibbonNotificationQueueID = :gibbonNotificationQueueID";

        return $this->db()->statement($sql, $data);
    }

    /**
     * Mark notification as sent.
     *
     * @param int $gibbonNotificationQueueID
     * @return bool
     */
    public function markSent($gibbonNotificationQueueID)
    {
        $data = ['gibbonNotificationQueueID' => $gibbonNotificationQueueID];
        $sql = "UPDATE gibbonNotificationQueue
                SET status = 'sent',
                    sentAt = NOW(),
                    errorMessage = NULL
                WHERE gibbonNotificationQueueID = :gibbonNotificationQueueID";

        return $this->db()->statement($sql, $data);
    }

    /**
     * Mark notification as failed with error message.
     *
     * Uses DeliveryRulesService to determine if retries are exhausted.
     *
     * @param int $gibbonNotificationQueueID
     * @param string $errorMessage
     * @param int $maxAttempts Maximum retry attempts
     * @return bool
     */
    public function markFailed($gibbonNotificationQueueID, $errorMessage, $maxAttempts = 3)
    {
        // First, get the current attempt count
        $notification = $this->getNotificationByID($gibbonNotificationQueueID);
        if (!$notification) {
            return false;
        }

        // Determine if retries are exhausted (use service if available)
        $retriesExhausted = false;
        if ($this->deliveryRulesService) {
            $retriesExhausted = $this->deliveryRulesService->hasExhaustedRetries($notification, $maxAttempts);
        } else {
            // Fallback logic for backward compatibility
            $retriesExhausted = ($notification['attempts'] >= $maxAttempts);
        }

        // If we've exhausted retries, mark as permanently failed
        $newStatus = $retriesExhausted ? 'failed' : 'pending';

        $data = [
            'gibbonNotificationQueueID' => $gibbonNotificationQueueID,
            'errorMessage' => $errorMessage,
            'status' => $newStatus,
        ];
        $sql = "UPDATE gibbonNotificationQueue
                SET status = :status,
                    errorMessage = :errorMessage
                WHERE gibbonNotificationQueueID = :gibbonNotificationQueueID";

        return $this->db()->statement($sql, $data);
    }

    /**
     * Get queue statistics.
     *
     * @return array
     */
    public function getQueueStatistics()
    {
        $sql = "SELECT status, COUNT(*) as count
                FROM gibbonNotificationQueue
                GROUP BY status";

        $results = $this->db()->select($sql)->fetchAll();

        $stats = [
            'pending' => 0,
            'processing' => 0,
            'sent' => 0,
            'failed' => 0,
            'total' => 0,
        ];

        foreach ($results as $row) {
            $stats[$row['status']] = (int) $row['count'];
            $stats['total'] += (int) $row['count'];
        }

        return $stats;
    }

    /**
     * Purge old sent/failed notifications.
     *
     * @param int $daysOld Days to keep notifications
     * @return int Number of records purged
     */
    public function purgeOldNotifications($daysOld = 30)
    {
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$daysOld} days"));

        $data = ['cutoffDate' => $cutoffDate];
        $sql = "DELETE FROM gibbonNotificationQueue
                WHERE status IN ('sent', 'failed')
                AND timestampCreated < :cutoffDate";

        return $this->db()->delete($sql, $data);
    }

    // =========================================================================
    // TEMPLATE OPERATIONS
    // =========================================================================

    /**
     * Query notification templates with pagination support.
     *
     * @param QueryCriteria $criteria
     * @return \Gibbon\Domain\DataSet
     */
    public function queryTemplates(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->from('gibbonNotificationTemplate')
            ->cols([
                'gibbonNotificationTemplateID',
                'type',
                'nameDisplay',
                'subjectTemplate',
                'bodyTemplate',
                'pushTitle',
                'pushBody',
                'active',
                'timestampCreated',
                'timestampModified',
            ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Get all active templates.
     *
     * @return array
     */
    public function selectActiveTemplates()
    {
        $sql = "SELECT * FROM gibbonNotificationTemplate
                WHERE active = 'Y'
                ORDER BY nameDisplay";

        return $this->db()->select($sql)->fetchAll();
    }

    /**
     * Get template by type.
     *
     * @param string $type Template type
     * @return array|false
     */
    public function getTemplateByType($type)
    {
        $data = ['type' => $type];
        $sql = "SELECT * FROM gibbonNotificationTemplate
                WHERE type = :type";

        return $this->db()->selectOne($sql, $data);
    }

    /**
     * Get template by ID.
     *
     * @param int $gibbonNotificationTemplateID
     * @return array|false
     */
    public function getTemplateByID($gibbonNotificationTemplateID)
    {
        $data = ['gibbonNotificationTemplateID' => $gibbonNotificationTemplateID];
        $sql = "SELECT * FROM gibbonNotificationTemplate
                WHERE gibbonNotificationTemplateID = :gibbonNotificationTemplateID";

        return $this->db()->selectOne($sql, $data);
    }

    /**
     * Render a template with placeholder values.
     *
     * @param string $template Template string with {{placeholders}}
     * @param array $values Key-value pairs for replacement
     * @return string Rendered template
     */
    public function renderTemplate($template, array $values)
    {
        foreach ($values as $key => $value) {
            $template = str_replace('{{' . $key . '}}', $value, $template);
        }
        return $template;
    }

    /**
     * Insert a new template.
     *
     * @param array $data Template data
     * @return int|false
     */
    public function insertTemplate(array $data)
    {
        $fields = [
            'type',
            'nameDisplay',
            'subjectTemplate',
            'bodyTemplate',
            'pushTitle',
            'pushBody',
            'active',
        ];

        $insertData = array_intersect_key($data, array_flip($fields));

        $sql = "INSERT INTO gibbonNotificationTemplate
                (type, nameDisplay, subjectTemplate, bodyTemplate, pushTitle, pushBody, active)
                VALUES (:type, :nameDisplay, :subjectTemplate, :bodyTemplate, :pushTitle, :pushBody, :active)";

        $this->db()->statement($sql, $insertData);
        return $this->db()->getConnection()->lastInsertID();
    }

    /**
     * Update a template.
     *
     * @param int $gibbonNotificationTemplateID
     * @param array $data Updated data
     * @return bool
     */
    public function updateTemplate($gibbonNotificationTemplateID, array $data)
    {
        $allowedFields = ['nameDisplay', 'subjectTemplate', 'bodyTemplate', 'pushTitle', 'pushBody', 'active'];
        $updateData = array_intersect_key($data, array_flip($allowedFields));
        $updateData['gibbonNotificationTemplateID'] = $gibbonNotificationTemplateID;

        $sets = [];
        foreach ($allowedFields as $field) {
            if (isset($updateData[$field])) {
                $sets[] = "$field = :$field";
            }
        }

        if (empty($sets)) {
            return false;
        }

        $sql = "UPDATE gibbonNotificationTemplate
                SET " . implode(', ', $sets) . "
                WHERE gibbonNotificationTemplateID = :gibbonNotificationTemplateID";

        return $this->db()->statement($sql, $updateData);
    }

    // =========================================================================
    // PREFERENCE OPERATIONS
    // =========================================================================

    /**
     * Get user notification preferences.
     *
     * @param int $gibbonPersonID
     * @return array
     */
    public function selectPreferencesByPerson($gibbonPersonID)
    {
        $data = ['gibbonPersonID' => $gibbonPersonID];
        $sql = "SELECT np.*, nt.nameDisplay AS typeDisplay
                FROM gibbonNotificationPreference np
                LEFT JOIN gibbonNotificationTemplate nt ON nt.type = np.type
                WHERE np.gibbonPersonID = :gibbonPersonID
                ORDER BY np.type";

        return $this->db()->select($sql, $data)->fetchAll();
    }

    /**
     * Get preference for a specific person and notification type.
     *
     * @param int $gibbonPersonID
     * @param string $type
     * @return array|false
     */
    public function getPreference($gibbonPersonID, $type)
    {
        $data = [
            'gibbonPersonID' => $gibbonPersonID,
            'type' => $type,
        ];
        $sql = "SELECT * FROM gibbonNotificationPreference
                WHERE gibbonPersonID = :gibbonPersonID
                AND type = :type";

        return $this->db()->selectOne($sql, $data);
    }

    /**
     * Check if a user has email notifications enabled for a type.
     *
     * @deprecated Use PreferenceService::isEmailEnabled() instead
     * @param int $gibbonPersonID
     * @param string $type
     * @return bool
     */
    public function isEmailEnabled($gibbonPersonID, $type)
    {
        // Delegate to service if available, otherwise fallback to inline logic
        if ($this->preferenceService) {
            return $this->preferenceService->isEmailEnabled($gibbonPersonID, $type);
        }

        // Fallback logic for backward compatibility
        $preference = $this->getPreference($gibbonPersonID, $type);

        // Default to enabled if no preference exists
        if (!$preference) {
            return true;
        }

        return $preference['emailEnabled'] === 'Y';
    }

    /**
     * Check if a user has push notifications enabled for a type.
     *
     * @deprecated Use PreferenceService::isPushEnabled() instead
     * @param int $gibbonPersonID
     * @param string $type
     * @return bool
     */
    public function isPushEnabled($gibbonPersonID, $type)
    {
        // Delegate to service if available, otherwise fallback to inline logic
        if ($this->preferenceService) {
            return $this->preferenceService->isPushEnabled($gibbonPersonID, $type);
        }

        // Fallback logic for backward compatibility
        $preference = $this->getPreference($gibbonPersonID, $type);

        // Default to enabled if no preference exists
        if (!$preference) {
            return true;
        }

        return $preference['pushEnabled'] === 'Y';
    }

    /**
     * Set user notification preference (insert or update).
     *
     * @param int $gibbonPersonID
     * @param string $type
     * @param string $emailEnabled Y/N
     * @param string $pushEnabled Y/N
     * @return bool
     */
    public function setPreference($gibbonPersonID, $type, $emailEnabled = 'Y', $pushEnabled = 'Y')
    {
        $data = [
            'gibbonPersonID' => $gibbonPersonID,
            'type' => $type,
            'emailEnabled' => $emailEnabled,
            'pushEnabled' => $pushEnabled,
        ];
        $sql = "INSERT INTO gibbonNotificationPreference
                (gibbonPersonID, type, emailEnabled, pushEnabled)
                VALUES (:gibbonPersonID, :type, :emailEnabled, :pushEnabled)
                ON DUPLICATE KEY UPDATE
                    emailEnabled = :emailEnabled,
                    pushEnabled = :pushEnabled";

        return $this->db()->statement($sql, $data);
    }

    /**
     * Delete a user preference.
     *
     * @param int $gibbonNotificationPreferenceID
     * @return bool
     */
    public function deletePreference($gibbonNotificationPreferenceID)
    {
        $data = ['gibbonNotificationPreferenceID' => $gibbonNotificationPreferenceID];
        $sql = "DELETE FROM gibbonNotificationPreference
                WHERE gibbonNotificationPreferenceID = :gibbonNotificationPreferenceID";

        return $this->db()->delete($sql, $data) > 0;
    }

    // =========================================================================
    // FCM TOKEN OPERATIONS
    // =========================================================================

    /**
     * Get all active FCM tokens for a user.
     *
     * @param int $gibbonPersonID
     * @return array
     */
    public function selectActiveTokensByPerson($gibbonPersonID)
    {
        $data = ['gibbonPersonID' => $gibbonPersonID];
        $sql = "SELECT * FROM gibbonFCMToken
                WHERE gibbonPersonID = :gibbonPersonID
                AND active = 'Y'
                ORDER BY lastUsedAt DESC";

        return $this->db()->select($sql, $data)->fetchAll();
    }

    /**
     * Get FCM token by device token string.
     *
     * @param string $deviceToken
     * @return array|false
     */
    public function getTokenByDeviceToken($deviceToken)
    {
        $data = ['deviceToken' => $deviceToken];
        $sql = "SELECT * FROM gibbonFCMToken
                WHERE deviceToken = :deviceToken";

        return $this->db()->selectOne($sql, $data);
    }

    /**
     * Register or update an FCM device token.
     *
     * @param int $gibbonPersonID
     * @param string $deviceToken
     * @param string $deviceType ios, android, or web
     * @param string|null $deviceName User-friendly device name
     * @return bool
     */
    public function registerToken($gibbonPersonID, $deviceToken, $deviceType, $deviceName = null)
    {
        $data = [
            'gibbonPersonID' => $gibbonPersonID,
            'deviceToken' => $deviceToken,
            'deviceType' => $deviceType,
            'deviceName' => $deviceName,
        ];
        $sql = "INSERT INTO gibbonFCMToken
                (gibbonPersonID, deviceToken, deviceType, deviceName, active, lastUsedAt)
                VALUES (:gibbonPersonID, :deviceToken, :deviceType, :deviceName, 'Y', NOW())
                ON DUPLICATE KEY UPDATE
                    gibbonPersonID = :gibbonPersonID,
                    deviceType = :deviceType,
                    deviceName = :deviceName,
                    active = 'Y',
                    lastUsedAt = NOW()";

        return $this->db()->statement($sql, $data);
    }

    /**
     * Deactivate an FCM token (e.g., when it expires or is invalid).
     *
     * @param string $deviceToken
     * @return bool
     */
    public function deactivateToken($deviceToken)
    {
        $data = ['deviceToken' => $deviceToken];
        $sql = "UPDATE gibbonFCMToken
                SET active = 'N'
                WHERE deviceToken = :deviceToken";

        return $this->db()->statement($sql, $data);
    }

    /**
     * Delete an FCM token.
     *
     * @param int $gibbonFCMTokenID
     * @return bool
     */
    public function deleteToken($gibbonFCMTokenID)
    {
        $data = ['gibbonFCMTokenID' => $gibbonFCMTokenID];
        $sql = "DELETE FROM gibbonFCMToken
                WHERE gibbonFCMTokenID = :gibbonFCMTokenID";

        return $this->db()->delete($sql, $data) > 0;
    }

    /**
     * Update last used timestamp for a token.
     *
     * @param string $deviceToken
     * @return bool
     */
    public function updateTokenLastUsed($deviceToken)
    {
        $data = ['deviceToken' => $deviceToken];
        $sql = "UPDATE gibbonFCMToken
                SET lastUsedAt = NOW()
                WHERE deviceToken = :deviceToken";

        return $this->db()->statement($sql, $data);
    }

    /**
     * Get device statistics for a user.
     *
     * @param int $gibbonPersonID
     * @return array
     */
    public function getDeviceStatistics($gibbonPersonID)
    {
        $data = ['gibbonPersonID' => $gibbonPersonID];
        $sql = "SELECT deviceType, COUNT(*) as count, MAX(lastUsedAt) as lastUsed
                FROM gibbonFCMToken
                WHERE gibbonPersonID = :gibbonPersonID
                AND active = 'Y'
                GROUP BY deviceType";

        return $this->db()->select($sql, $data)->fetchAll();
    }

    /**
     * Cleanup stale tokens (not used for specified days).
     *
     * @param int $daysStale Days without use before cleanup
     * @return int Number of tokens cleaned up
     */
    public function cleanupStaleTokens($daysStale = 90)
    {
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$daysStale} days"));

        $data = ['cutoffDate' => $cutoffDate];
        $sql = "DELETE FROM gibbonFCMToken
                WHERE lastUsedAt < :cutoffDate
                OR (lastUsedAt IS NULL AND timestampCreated < :cutoffDate)";

        return $this->db()->delete($sql, $data);
    }

    // =========================================================================
    // RETRY MECHANISM HELPERS
    // =========================================================================

    /**
     * Calculate next retry time for a notification based on exponential backoff.
     *
     * Formula: retryDelayMinutes * 2^(attempts-1) minutes
     * - Attempt 1: retryDelayMinutes (e.g., 5 minutes)
     * - Attempt 2: retryDelayMinutes * 2 (e.g., 10 minutes)
     * - Attempt 3: retryDelayMinutes * 4 (e.g., 20 minutes)
     *
     * @deprecated Use DeliveryRulesService::calculateRetryDelay() instead
     * @param int $attemptNumber Current attempt number (1-based)
     * @param int $retryDelayMinutes Base delay in minutes
     * @return int Delay in minutes
     */
    public function calculateRetryDelay($attemptNumber, $retryDelayMinutes = 5)
    {
        // Delegate to service if available, otherwise fallback to inline logic
        if ($this->deliveryRulesService) {
            return $this->deliveryRulesService->calculateRetryDelay($attemptNumber, $retryDelayMinutes);
        }

        // Fallback logic for backward compatibility
        if ($attemptNumber <= 0) {
            return 0; // No delay for first attempt
        }

        // Exponential backoff: 2^(attempts-1) * base delay
        return (int) ($retryDelayMinutes * pow(2, $attemptNumber - 1));
    }

    /**
     * Get the next retry timestamp for a notification.
     *
     * @deprecated Use DeliveryRulesService::getNextRetryTime() instead
     * @param array $notification Notification data with 'attempts' and 'lastAttemptAt'
     * @param int $retryDelayMinutes Base delay in minutes
     * @return string|null ISO 8601 timestamp when retry should occur, or null if ready now
     */
    public function getNextRetryTime($notification, $retryDelayMinutes = 5)
    {
        // Delegate to service if available, otherwise fallback to inline logic
        if ($this->deliveryRulesService) {
            return $this->deliveryRulesService->getNextRetryTime($notification, $retryDelayMinutes);
        }

        // Fallback logic for backward compatibility
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
     * Check if a notification is ready for retry.
     *
     * @deprecated Use DeliveryRulesService::isReadyForRetry() instead
     * @param array $notification Notification data
     * @param int $retryDelayMinutes Base delay in minutes
     * @return bool True if ready for retry
     */
    public function isReadyForRetry($notification, $retryDelayMinutes = 5)
    {
        // Delegate to service if available, otherwise fallback to inline logic
        if ($this->deliveryRulesService) {
            return $this->deliveryRulesService->isReadyForRetry($notification, $retryDelayMinutes);
        }

        // Fallback logic for backward compatibility
        $nextRetryTime = $this->getNextRetryTime($notification, $retryDelayMinutes);

        // If null, ready immediately
        if ($nextRetryTime === null) {
            return true;
        }

        // Compare with current time
        return strtotime($nextRetryTime) <= time();
    }

    /**
     * Get retry statistics for failed notifications.
     *
     * Returns counts grouped by attempt number.
     *
     * @return array
     */
    public function getRetryStatistics()
    {
        $sql = "SELECT
                    attempts,
                    COUNT(*) as count,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count
                FROM gibbonNotificationQueue
                WHERE attempts > 0
                GROUP BY attempts
                ORDER BY attempts ASC";

        return $this->db()->select($sql)->fetchAll();
    }

    /**
     * Get notifications pending retry (waiting for backoff delay).
     *
     * These are pending notifications that aren't ready yet due to exponential backoff.
     *
     * @param int $retryDelayMinutes Base delay in minutes
     * @return array
     */
    public function selectNotificationsPendingRetry($retryDelayMinutes = 5)
    {
        $sql = "SELECT q.*,
                       p.preferredName AS recipientPreferredName,
                       p.surname AS recipientSurname,
                       p.email AS recipientEmail,
                       TIMESTAMPADD(MINUTE,
                           " . $retryDelayMinutes . " * POW(2, q.attempts - 1),
                           q.lastAttemptAt
                       ) as nextRetryAt
                FROM gibbonNotificationQueue q
                INNER JOIN gibbonPerson AS p ON p.gibbonPersonID = q.gibbonPersonID
                WHERE q.status = 'pending'
                AND q.attempts > 0
                AND q.lastAttemptAt > DATE_SUB(NOW(), INTERVAL (
                    " . $retryDelayMinutes . " * POW(2, q.attempts - 1)
                ) MINUTE)
                ORDER BY nextRetryAt ASC";

        return $this->db()->select($sql)->fetchAll();
    }

    /**
     * Get detailed retry information for a notification.
     *
     * @deprecated Use DeliveryRulesService::getRetryInfo() instead
     * @param int $gibbonNotificationQueueID
     * @param int $maxAttempts Maximum retry attempts
     * @param int $retryDelayMinutes Base delay in minutes
     * @return array|null Retry information or null if not found
     */
    public function getRetryInfo($gibbonNotificationQueueID, $maxAttempts = 3, $retryDelayMinutes = 5)
    {
        // Delegate to service if available, otherwise fallback to inline logic
        if ($this->deliveryRulesService) {
            return $this->deliveryRulesService->getRetryInfo($gibbonNotificationQueueID, $maxAttempts, $retryDelayMinutes);
        }

        // Fallback logic for backward compatibility
        $notification = $this->getNotificationByID($gibbonNotificationQueueID);

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
     * Get retry queue health metrics.
     *
     * Returns overall health status of the retry mechanism including:
     * - Total notifications in retry
     * - Average attempts
     * - Oldest pending retry
     * - Success/failure rates
     *
     * @return array
     */
    public function getRetryHealthMetrics()
    {
        // Get retry queue stats
        $sql = "SELECT
                    COUNT(*) as total_retrying,
                    AVG(attempts) as avg_attempts,
                    MAX(attempts) as max_attempts,
                    MIN(lastAttemptAt) as oldest_retry,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_retry_count,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as permanently_failed_count
                FROM gibbonNotificationQueue
                WHERE attempts > 0";

        $retryStats = $this->db()->selectOne($sql);

        // Get overall success/failure rates
        $sql = "SELECT
                    COUNT(*) as total_notifications,
                    SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent_count,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count,
                    SUM(CASE WHEN status = 'sent' AND attempts > 0 THEN 1 ELSE 0 END) as recovered_by_retry_count
                FROM gibbonNotificationQueue";

        $overallStats = $this->db()->selectOne($sql);

        // Calculate rates
        $totalNotifications = (int) $overallStats['total_notifications'];
        $sentCount = (int) $overallStats['sent_count'];
        $failedCount = (int) $overallStats['failed_count'];
        $recoveredByRetry = (int) $overallStats['recovered_by_retry_count'];

        $successRate = $totalNotifications > 0 ? ($sentCount / $totalNotifications) * 100 : 0;
        $failureRate = $totalNotifications > 0 ? ($failedCount / $totalNotifications) * 100 : 0;
        $retryRecoveryRate = $sentCount > 0 ? ($recoveredByRetry / $sentCount) * 100 : 0;

        return [
            'total_retrying' => (int) $retryStats['total_retrying'],
            'avg_attempts' => round((float) $retryStats['avg_attempts'], 2),
            'max_attempts' => (int) $retryStats['max_attempts'],
            'oldest_retry' => $retryStats['oldest_retry'],
            'pending_retry_count' => (int) $retryStats['pending_retry_count'],
            'permanently_failed_count' => (int) $retryStats['permanently_failed_count'],
            'total_notifications' => $totalNotifications,
            'sent_count' => $sentCount,
            'failed_count' => $failedCount,
            'recovered_by_retry_count' => $recoveredByRetry,
            'success_rate' => round($successRate, 2),
            'failure_rate' => round($failureRate, 2),
            'retry_recovery_rate' => round($retryRecoveryRate, 2),
        ];
    }
}
