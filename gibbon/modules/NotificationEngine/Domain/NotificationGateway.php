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

/**
 * NotificationGateway
 *
 * Gateway for notification queue, template, preference, and FCM token operations.
 * Supports multi-channel notifications (email, push) with retry logic.
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
     * Query pending notifications ready for processing.
     *
     * @param int $limit Maximum number to fetch
     * @param int $maxAttempts Maximum retry attempts before giving up
     * @return array
     */
    public function selectPendingNotifications($limit = 50, $maxAttempts = 3)
    {
        $data = [
            'limit' => (int) $limit,
            'maxAttempts' => (int) $maxAttempts,
        ];
        $sql = "SELECT q.*,
                       p.preferredName AS recipientPreferredName,
                       p.surname AS recipientSurname,
                       p.email AS recipientEmail,
                       p.receiveNotificationEmails
                FROM gibbonNotificationQueue q
                INNER JOIN gibbonPerson AS p ON p.gibbonPersonID = q.gibbonPersonID
                WHERE q.status = 'pending'
                AND q.attempts < :maxAttempts
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

        // If we've exhausted retries, mark as permanently failed
        $newStatus = ($notification['attempts'] >= $maxAttempts) ? 'failed' : 'pending';

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
     * @param int $gibbonPersonID
     * @param string $type
     * @return bool
     */
    public function isEmailEnabled($gibbonPersonID, $type)
    {
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
     * @param int $gibbonPersonID
     * @param string $type
     * @return bool
     */
    public function isPushEnabled($gibbonPersonID, $type)
    {
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
}
