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
 * DeliveryLogGateway
 *
 * Gateway for notification delivery log operations.
 * Provides detailed tracking of each delivery attempt for analytics and debugging.
 *
 * @version v1.2.00
 * @since   v1.2.00
 */
class DeliveryLogGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonNotificationDeliveryLog';
    private static $primaryKey = 'gibbonNotificationDeliveryLogID';
    private static $searchableColumns = ['gibbonNotificationDeliveryLog.errorMessage'];

    // =========================================================================
    // LOG OPERATIONS
    // =========================================================================

    /**
     * Log a delivery attempt.
     *
     * @param array $data Log entry data
     *   - gibbonNotificationQueueID (required): Notification ID
     *   - channel (required): 'email' or 'push'
     *   - status (required): 'success', 'failed', or 'skipped'
     *   - recipientIdentifier (optional): Email address or FCM token
     *   - attemptNumber (optional): Attempt number (default: 1)
     *   - errorCode (optional): Error code
     *   - errorMessage (optional): Error message
     *   - responseData (optional): Provider response as array
     *   - deliveryTimeMs (optional): Delivery time in milliseconds
     * @return int|false The new log entry ID or false on failure
     */
    public function logDelivery(array $data)
    {
        $fields = [
            'gibbonNotificationQueueID',
            'channel',
            'status',
            'recipientIdentifier',
            'attemptNumber',
            'errorCode',
            'errorMessage',
            'responseData',
            'deliveryTimeMs',
        ];

        // Convert responseData array to JSON if provided
        if (isset($data['responseData']) && is_array($data['responseData'])) {
            $data['responseData'] = json_encode($data['responseData']);
        }

        // Set defaults
        $data['attemptNumber'] = $data['attemptNumber'] ?? 1;

        return $this->insertAndUpdate($data, $fields);
    }

    /**
     * Query delivery logs with pagination and filtering.
     *
     * @param QueryCriteria $criteria Query criteria
     * @param int|null $gibbonNotificationQueueID Filter by notification ID
     * @param string|null $channel Filter by channel ('email', 'push')
     * @param string|null $status Filter by status ('success', 'failed', 'skipped')
     * @return \Gibbon\Domain\DataSet
     */
    public function queryDeliveryLogs(
        QueryCriteria $criteria,
        $gibbonNotificationQueueID = null,
        $channel = null,
        $status = null
    ) {
        $query = $this
            ->newQuery()
            ->from($this->getTableName() . ' AS log')
            ->cols([
                'log.gibbonNotificationDeliveryLogID',
                'log.gibbonNotificationQueueID',
                'log.channel',
                'log.status',
                'log.recipientIdentifier',
                'log.attemptNumber',
                'log.errorCode',
                'log.errorMessage',
                'log.responseData',
                'log.deliveryTimeMs',
                'log.timestampCreated',
                'queue.type AS notificationType',
                'queue.title AS notificationTitle',
                'queue.gibbonPersonID',
                'person.preferredName AS recipientPreferredName',
                'person.surname AS recipientSurname',
                'person.email AS recipientEmail',
            ])
            ->innerJoin('gibbonNotificationQueue AS queue', 'queue.gibbonNotificationQueueID = log.gibbonNotificationQueueID')
            ->leftJoin('gibbonPerson AS person', 'person.gibbonPersonID = queue.gibbonPersonID');

        if ($gibbonNotificationQueueID !== null) {
            $query->where('log.gibbonNotificationQueueID = :notificationID')
                  ->bindValue('notificationID', $gibbonNotificationQueueID);
        }

        if ($channel !== null) {
            $query->where('log.channel = :channel')
                  ->bindValue('channel', $channel);
        }

        if ($status !== null) {
            $query->where('log.status = :status')
                  ->bindValue('status', $status);
        }

        return $this->runQuery($query, $criteria);
    }

    /**
     * Get delivery logs for a specific notification.
     *
     * @param int $gibbonNotificationQueueID Notification ID
     * @return array Array of log entries
     */
    public function getLogsByNotificationID($gibbonNotificationQueueID)
    {
        $data = ['gibbonNotificationQueueID' => $gibbonNotificationQueueID];
        $sql = "SELECT *
                FROM gibbonNotificationDeliveryLog
                WHERE gibbonNotificationQueueID = :gibbonNotificationQueueID
                ORDER BY timestampCreated ASC";

        return $this->db()->select($sql, $data)->fetchAll();
    }

    /**
     * Get a single delivery log entry by ID.
     *
     * @param int $gibbonNotificationDeliveryLogID Log entry ID
     * @return array|false Log entry or false if not found
     */
    public function getByID($gibbonNotificationDeliveryLogID)
    {
        return $this->selectBy(['gibbonNotificationDeliveryLogID' => $gibbonNotificationDeliveryLogID]);
    }

    // =========================================================================
    // ANALYTICS
    // =========================================================================

    /**
     * Get delivery statistics for a date range.
     *
     * @param string|null $startDate Start date (Y-m-d format)
     * @param string|null $endDate End date (Y-m-d format)
     * @return array Statistics array with counts by channel and status
     */
    public function getDeliveryStatistics($startDate = null, $endDate = null)
    {
        $data = [];
        $sql = "SELECT
                    channel,
                    status,
                    COUNT(*) as count,
                    AVG(deliveryTimeMs) as avgDeliveryTimeMs,
                    MIN(deliveryTimeMs) as minDeliveryTimeMs,
                    MAX(deliveryTimeMs) as maxDeliveryTimeMs
                FROM gibbonNotificationDeliveryLog
                WHERE 1=1";

        if ($startDate !== null) {
            $sql .= " AND DATE(timestampCreated) >= :startDate";
            $data['startDate'] = $startDate;
        }

        if ($endDate !== null) {
            $sql .= " AND DATE(timestampCreated) <= :endDate";
            $data['endDate'] = $endDate;
        }

        $sql .= " GROUP BY channel, status
                  ORDER BY channel, status";

        return $this->db()->select($sql, $data)->fetchAll();
    }

    /**
     * Get delivery success rate by channel.
     *
     * @param string|null $startDate Start date (Y-m-d format)
     * @param string|null $endDate End date (Y-m-d format)
     * @return array Success rates by channel
     */
    public function getSuccessRates($startDate = null, $endDate = null)
    {
        $data = [];
        $sql = "SELECT
                    channel,
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                    SUM(CASE WHEN status = 'skipped' THEN 1 ELSE 0 END) as skipped,
                    ROUND(100.0 * SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) / COUNT(*), 2) as successRate
                FROM gibbonNotificationDeliveryLog
                WHERE 1=1";

        if ($startDate !== null) {
            $sql .= " AND DATE(timestampCreated) >= :startDate";
            $data['startDate'] = $startDate;
        }

        if ($endDate !== null) {
            $sql .= " AND DATE(timestampCreated) <= :endDate";
            $data['endDate'] = $endDate;
        }

        $sql .= " GROUP BY channel
                  ORDER BY channel";

        return $this->db()->select($sql, $data)->fetchAll();
    }

    /**
     * Get most common error codes.
     *
     * @param int $limit Maximum number of error codes to return
     * @param string|null $channel Filter by channel ('email', 'push')
     * @return array Array of error codes with counts
     */
    public function getTopErrors($limit = 10, $channel = null)
    {
        $data = ['limit' => $limit];
        $sql = "SELECT
                    errorCode,
                    channel,
                    COUNT(*) as count,
                    MAX(timestampCreated) as lastOccurrence
                FROM gibbonNotificationDeliveryLog
                WHERE status = 'failed'
                AND errorCode IS NOT NULL";

        if ($channel !== null) {
            $sql .= " AND channel = :channel";
            $data['channel'] = $channel;
        }

        $sql .= " GROUP BY errorCode, channel
                  ORDER BY count DESC
                  LIMIT :limit";

        return $this->db()->select($sql, $data)->fetchAll();
    }

    /**
     * Get delivery timeline (hourly breakdown).
     *
     * @param string|null $startDate Start date (Y-m-d format)
     * @param string|null $endDate End date (Y-m-d format)
     * @return array Timeline data with hourly counts
     */
    public function getDeliveryTimeline($startDate = null, $endDate = null)
    {
        $data = [];
        $sql = "SELECT
                    DATE_FORMAT(timestampCreated, '%Y-%m-%d %H:00:00') as hourBucket,
                    channel,
                    status,
                    COUNT(*) as count
                FROM gibbonNotificationDeliveryLog
                WHERE 1=1";

        if ($startDate !== null) {
            $sql .= " AND DATE(timestampCreated) >= :startDate";
            $data['startDate'] = $startDate;
        }

        if ($endDate !== null) {
            $sql .= " AND DATE(timestampCreated) <= :endDate";
            $data['endDate'] = $endDate;
        }

        $sql .= " GROUP BY hourBucket, channel, status
                  ORDER BY hourBucket ASC";

        return $this->db()->select($sql, $data)->fetchAll();
    }

    /**
     * Get average delivery times by channel.
     *
     * @param string|null $startDate Start date (Y-m-d format)
     * @param string|null $endDate End date (Y-m-d format)
     * @return array Average delivery times by channel
     */
    public function getAverageDeliveryTimes($startDate = null, $endDate = null)
    {
        $data = [];
        $sql = "SELECT
                    channel,
                    AVG(deliveryTimeMs) as avgMs,
                    MIN(deliveryTimeMs) as minMs,
                    MAX(deliveryTimeMs) as maxMs,
                    COUNT(*) as sampleSize
                FROM gibbonNotificationDeliveryLog
                WHERE deliveryTimeMs IS NOT NULL
                AND status = 'success'";

        if ($startDate !== null) {
            $sql .= " AND DATE(timestampCreated) >= :startDate";
            $data['startDate'] = $startDate;
        }

        if ($endDate !== null) {
            $sql .= " AND DATE(timestampCreated) <= :endDate";
            $data['endDate'] = $endDate;
        }

        $sql .= " GROUP BY channel
                  ORDER BY channel";

        return $this->db()->select($sql, $data)->fetchAll();
    }

    /**
     * Purge old delivery logs.
     *
     * @param int $days Number of days to keep
     * @return int Number of logs purged
     */
    public function purgeOldLogs($days = 90)
    {
        $data = ['days' => $days];
        $sql = "DELETE FROM gibbonNotificationDeliveryLog
                WHERE timestampCreated < DATE_SUB(NOW(), INTERVAL :days DAY)";

        $this->db()->delete($sql, $data);
        return $this->db()->getConnection()->rowCount();
    }

    /**
     * Get failed delivery logs that may need attention.
     *
     * @param int $limit Maximum number of failures to return
     * @return array Array of recent failures
     */
    public function getRecentFailures($limit = 20)
    {
        $data = ['limit' => $limit];
        $sql = "SELECT log.*,
                       queue.type AS notificationType,
                       queue.title AS notificationTitle,
                       queue.gibbonPersonID,
                       person.preferredName AS recipientPreferredName,
                       person.surname AS recipientSurname
                FROM gibbonNotificationDeliveryLog log
                INNER JOIN gibbonNotificationQueue queue ON queue.gibbonNotificationQueueID = log.gibbonNotificationQueueID
                LEFT JOIN gibbonPerson person ON person.gibbonPersonID = queue.gibbonPersonID
                WHERE log.status = 'failed'
                ORDER BY log.timestampCreated DESC
                LIMIT :limit";

        return $this->db()->select($sql, $data)->fetchAll();
    }
}
