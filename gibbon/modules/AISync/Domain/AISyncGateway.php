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

namespace Gibbon\Module\AISync\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\QueryableGateway;

/**
 * AI Sync Gateway
 *
 * Handles database operations for AI sync logs and webhook tracking.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class AISyncGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonAISyncLog';
    private static $primaryKey = 'gibbonAISyncLogID';

    private static $searchableColumns = ['gibbonAISyncLog.eventType', 'gibbonAISyncLog.entityType', 'gibbonAISyncLog.errorMessage'];

    /**
     * Query sync log records with criteria support.
     *
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function querySyncLogs(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonAISyncLog.gibbonAISyncLogID',
                'gibbonAISyncLog.eventType',
                'gibbonAISyncLog.entityType',
                'gibbonAISyncLog.entityID',
                'gibbonAISyncLog.payload',
                'gibbonAISyncLog.status',
                'gibbonAISyncLog.response',
                'gibbonAISyncLog.retryCount',
                'gibbonAISyncLog.errorMessage',
                'gibbonAISyncLog.timestampCreated',
                'gibbonAISyncLog.timestampProcessed',
            ]);

        $criteria->addFilterRules([
            'status' => function ($query, $status) {
                return $query
                    ->where('gibbonAISyncLog.status=:status')
                    ->bindValue('status', $status);
            },
            'eventType' => function ($query, $eventType) {
                return $query
                    ->where('gibbonAISyncLog.eventType=:eventType')
                    ->bindValue('eventType', $eventType);
            },
            'entityType' => function ($query, $entityType) {
                return $query
                    ->where('gibbonAISyncLog.entityType=:entityType')
                    ->bindValue('entityType', $entityType);
            },
            'dateFrom' => function ($query, $date) {
                return $query
                    ->where('DATE(gibbonAISyncLog.timestampCreated) >= :dateFrom')
                    ->bindValue('dateFrom', $date);
            },
            'dateTo' => function ($query, $date) {
                return $query
                    ->where('DATE(gibbonAISyncLog.timestampCreated) <= :dateTo')
                    ->bindValue('dateTo', $date);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query sync logs by status.
     *
     * @param QueryCriteria $criteria
     * @param string $status Status: pending, success, or failed
     * @return DataSet
     */
    public function querySyncLogsByStatus(QueryCriteria $criteria, $status)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonAISyncLog.gibbonAISyncLogID',
                'gibbonAISyncLog.eventType',
                'gibbonAISyncLog.entityType',
                'gibbonAISyncLog.entityID',
                'gibbonAISyncLog.payload',
                'gibbonAISyncLog.status',
                'gibbonAISyncLog.response',
                'gibbonAISyncLog.retryCount',
                'gibbonAISyncLog.errorMessage',
                'gibbonAISyncLog.timestampCreated',
                'gibbonAISyncLog.timestampProcessed',
            ])
            ->where('gibbonAISyncLog.status=:status')
            ->bindValue('status', $status)
            ->orderBy(['gibbonAISyncLog.timestampCreated DESC']);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query failed sync logs that can be retried.
     *
     * @param QueryCriteria $criteria
     * @param int $maxRetries Maximum retry attempts before excluding
     * @return DataSet
     */
    public function queryRetryableSyncLogs(QueryCriteria $criteria, $maxRetries = 3)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonAISyncLog.gibbonAISyncLogID',
                'gibbonAISyncLog.eventType',
                'gibbonAISyncLog.entityType',
                'gibbonAISyncLog.entityID',
                'gibbonAISyncLog.payload',
                'gibbonAISyncLog.status',
                'gibbonAISyncLog.retryCount',
                'gibbonAISyncLog.errorMessage',
                'gibbonAISyncLog.timestampCreated',
                'gibbonAISyncLog.timestampProcessed',
            ])
            ->where('gibbonAISyncLog.status=:status')
            ->bindValue('status', 'failed')
            ->where('gibbonAISyncLog.retryCount < :maxRetries')
            ->bindValue('maxRetries', $maxRetries)
            ->orderBy(['gibbonAISyncLog.timestampCreated ASC']);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Select sync logs for a specific entity.
     *
     * @param string $entityType Entity type (e.g., 'activity', 'meal')
     * @param int $entityID Entity ID
     * @return \Gibbon\Database\Result
     */
    public function selectSyncLogsByEntity($entityType, $entityID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonAISyncLog.*',
            ])
            ->where('gibbonAISyncLog.entityType=:entityType')
            ->bindValue('entityType', $entityType)
            ->where('gibbonAISyncLog.entityID=:entityID')
            ->bindValue('entityID', $entityID)
            ->orderBy(['gibbonAISyncLog.timestampCreated DESC']);

        return $this->runSelect($query);
    }

    /**
     * Get sync statistics summary.
     *
     * @param string|null $dateFrom Start date filter (Y-m-d format)
     * @param string|null $dateTo End date filter (Y-m-d format)
     * @return array
     */
    public function getSyncStatistics($dateFrom = null, $dateTo = null)
    {
        $data = [];
        $whereClause = '';

        if ($dateFrom) {
            $whereClause .= ' AND DATE(timestampCreated) >= :dateFrom';
            $data['dateFrom'] = $dateFrom;
        }

        if ($dateTo) {
            $whereClause .= ' AND DATE(timestampCreated) <= :dateTo';
            $data['dateTo'] = $dateTo;
        }

        $sql = "SELECT
                    COUNT(*) as totalSyncs,
                    SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pendingSyncs,
                    SUM(CASE WHEN status='success' THEN 1 ELSE 0 END) as successfulSyncs,
                    SUM(CASE WHEN status='failed' THEN 1 ELSE 0 END) as failedSyncs,
                    AVG(retryCount) as avgRetryCount,
                    MAX(retryCount) as maxRetryCount
                FROM gibbonAISyncLog
                WHERE 1=1{$whereClause}";

        return $this->db()->selectOne($sql, $data) ?: [
            'totalSyncs' => 0,
            'pendingSyncs' => 0,
            'successfulSyncs' => 0,
            'failedSyncs' => 0,
            'avgRetryCount' => 0,
            'maxRetryCount' => 0,
        ];
    }

    /**
     * Get sync statistics grouped by event type.
     *
     * @param string|null $dateFrom Start date filter
     * @param string|null $dateTo End date filter
     * @return array
     */
    public function getSyncStatisticsByEventType($dateFrom = null, $dateTo = null)
    {
        $data = [];
        $whereClause = '';

        if ($dateFrom) {
            $whereClause .= ' AND DATE(timestampCreated) >= :dateFrom';
            $data['dateFrom'] = $dateFrom;
        }

        if ($dateTo) {
            $whereClause .= ' AND DATE(timestampCreated) <= :dateTo';
            $data['dateTo'] = $dateTo;
        }

        $sql = "SELECT
                    eventType,
                    COUNT(*) as count,
                    SUM(CASE WHEN status='success' THEN 1 ELSE 0 END) as successCount,
                    SUM(CASE WHEN status='failed' THEN 1 ELSE 0 END) as failedCount
                FROM gibbonAISyncLog
                WHERE 1=1{$whereClause}
                GROUP BY eventType
                ORDER BY count DESC";

        return $this->db()->select($sql, $data)->fetchAll();
    }

    /**
     * Get sync statistics grouped by entity type.
     *
     * @param string|null $dateFrom Start date filter
     * @param string|null $dateTo End date filter
     * @return array
     */
    public function getSyncStatisticsByEntityType($dateFrom = null, $dateTo = null)
    {
        $data = [];
        $whereClause = '';

        if ($dateFrom) {
            $whereClause .= ' AND DATE(timestampCreated) >= :dateFrom';
            $data['dateFrom'] = $dateFrom;
        }

        if ($dateTo) {
            $whereClause .= ' AND DATE(timestampCreated) <= :dateTo';
            $data['dateTo'] = $dateTo;
        }

        $sql = "SELECT
                    entityType,
                    COUNT(*) as count,
                    SUM(CASE WHEN status='success' THEN 1 ELSE 0 END) as successCount,
                    SUM(CASE WHEN status='failed' THEN 1 ELSE 0 END) as failedCount
                FROM gibbonAISyncLog
                WHERE 1=1{$whereClause}
                GROUP BY entityType
                ORDER BY count DESC";

        return $this->db()->select($sql, $data)->fetchAll();
    }

    /**
     * Create a new sync log entry.
     *
     * @param string $eventType Event type (e.g., 'care_activity_created')
     * @param string $entityType Entity type (e.g., 'activity')
     * @param int $entityID Entity ID
     * @param array|null $payload Data payload
     * @return int|false The new log ID or false on failure
     */
    public function createSyncLog($eventType, $entityType, $entityID, $payload = null)
    {
        $data = [
            'eventType' => $eventType,
            'entityType' => $entityType,
            'entityID' => $entityID,
            'status' => 'pending',
        ];

        if ($payload !== null) {
            $data['payload'] = json_encode($payload);
        }

        return $this->insert($data);
    }

    /**
     * Update sync log status.
     *
     * @param int $gibbonAISyncLogID Log entry ID
     * @param string $status New status: pending, success, or failed
     * @param string|null $response Response from AI service
     * @param string|null $errorMessage Error message if failed
     * @return bool
     */
    public function updateSyncLogStatus($gibbonAISyncLogID, $status, $response = null, $errorMessage = null)
    {
        $data = [
            'status' => $status,
            'timestampProcessed' => date('Y-m-d H:i:s'),
        ];

        if ($response !== null) {
            $data['response'] = $response;
        }

        if ($errorMessage !== null) {
            $data['errorMessage'] = $errorMessage;
        }

        return $this->update($gibbonAISyncLogID, $data);
    }

    /**
     * Increment retry count for a sync log entry.
     *
     * @param int $gibbonAISyncLogID Log entry ID
     * @return bool
     */
    public function incrementRetryCount($gibbonAISyncLogID)
    {
        $sql = "UPDATE gibbonAISyncLog
                SET retryCount = retryCount + 1
                WHERE gibbonAISyncLogID = :gibbonAISyncLogID";

        return $this->db()->statement($sql, ['gibbonAISyncLogID' => $gibbonAISyncLogID]);
    }

    /**
     * Delete old sync logs.
     *
     * @param int $daysOld Number of days after which to delete successful logs
     * @return int Number of deleted records
     */
    public function deleteOldSyncLogs($daysOld = 30)
    {
        $sql = "DELETE FROM gibbonAISyncLog
                WHERE status = 'success'
                AND timestampCreated < DATE_SUB(NOW(), INTERVAL :daysOld DAY)";

        $this->db()->statement($sql, ['daysOld' => $daysOld]);

        return $this->db()->getConnection()->rowCount();
    }

    /**
     * Get recent sync activity for dashboard display.
     *
     * @param int $limit Number of recent records to return
     * @return array
     */
    public function getRecentSyncActivity($limit = 10)
    {
        $sql = "SELECT
                    gibbonAISyncLogID,
                    eventType,
                    entityType,
                    entityID,
                    status,
                    retryCount,
                    errorMessage,
                    timestampCreated,
                    timestampProcessed
                FROM gibbonAISyncLog
                ORDER BY timestampCreated DESC
                LIMIT :limit";

        return $this->db()->select($sql, ['limit' => $limit])->fetchAll();
    }

    /**
     * Get webhook health metrics for monitoring and diagnostics.
     * Returns comprehensive health information including success rates,
     * failure counts, retry statistics, and performance metrics.
     *
     * @param string|null $dateFrom Start date filter (Y-m-d format)
     * @param string|null $dateTo End date filter (Y-m-d format)
     * @return array Health metrics
     */
    public function getWebhookHealth($dateFrom = null, $dateTo = null)
    {
        $data = [];
        $whereClause = '';

        if ($dateFrom) {
            $whereClause .= ' AND DATE(timestampCreated) >= :dateFrom';
            $data['dateFrom'] = $dateFrom;
        }

        if ($dateTo) {
            $whereClause .= ' AND DATE(timestampCreated) <= :dateTo';
            $data['dateTo'] = $dateTo;
        }

        // Get overall statistics
        $sql = "SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status='success' THEN 1 ELSE 0 END) as success,
                    SUM(CASE WHEN status='failed' THEN 1 ELSE 0 END) as failed,
                    SUM(CASE WHEN status='failed' AND retryCount >= 3 THEN 1 ELSE 0 END) as permanentlyFailed,
                    AVG(CASE WHEN status='success' THEN retryCount ELSE NULL END) as avgRetriesUntilSuccess,
                    MAX(retryCount) as maxRetryCount,
                    MIN(timestampCreated) as oldestSync,
                    MAX(timestampCreated) as newestSync
                FROM gibbonAISyncLog
                WHERE 1=1{$whereClause}";

        $stats = $this->db()->selectOne($sql, $data);

        // Calculate success rate
        $total = (int)($stats['total'] ?? 0);
        $success = (int)($stats['success'] ?? 0);
        $failed = (int)($stats['failed'] ?? 0);
        $pending = (int)($stats['pending'] ?? 0);

        $successRate = $total > 0 ? round(($success / $total) * 100, 2) : 0;
        $failureRate = $total > 0 ? round(($failed / $total) * 100, 2) : 0;

        // Get pending syncs older than 5 minutes (likely stale)
        $stalePendingSQL = "SELECT COUNT(*) as count
                            FROM gibbonAISyncLog
                            WHERE status='pending'
                            AND timestampCreated < DATE_SUB(NOW(), INTERVAL 5 MINUTE)";
        $stalePending = $this->db()->selectOne($stalePendingSQL);

        // Get recent failures (last hour)
        $recentFailuresSQL = "SELECT COUNT(*) as count
                              FROM gibbonAISyncLog
                              WHERE status='failed'
                              AND timestampCreated >= DATE_SUB(NOW(), INTERVAL 1 HOUR)";
        $recentFailures = $this->db()->selectOne($recentFailuresSQL);

        // Determine overall health status
        $healthStatus = 'healthy';
        $healthIssues = [];

        if ($failureRate > 50) {
            $healthStatus = 'critical';
            $healthIssues[] = "High failure rate: {$failureRate}%";
        } elseif ($failureRate > 25) {
            $healthStatus = 'warning';
            $healthIssues[] = "Elevated failure rate: {$failureRate}%";
        }

        if (($stalePending['count'] ?? 0) > 10) {
            $healthStatus = $healthStatus === 'critical' ? 'critical' : 'warning';
            $healthIssues[] = "Stale pending syncs detected: {$stalePending['count']}";
        }

        if (($recentFailures['count'] ?? 0) > 20) {
            $healthStatus = $healthStatus === 'critical' ? 'critical' : 'warning';
            $healthIssues[] = "High recent failure rate: {$recentFailures['count']} in last hour";
        }

        return [
            'overall' => [
                'status' => $healthStatus,
                'issues' => $healthIssues,
                'total' => $total,
                'pending' => $pending,
                'success' => $success,
                'failed' => $failed,
                'permanentlyFailed' => (int)($stats['permanentlyFailed'] ?? 0),
                'successRate' => $successRate,
                'failureRate' => $failureRate,
            ],
            'performance' => [
                'avgRetriesUntilSuccess' => round((float)($stats['avgRetriesUntilSuccess'] ?? 0), 2),
                'maxRetryCount' => (int)($stats['maxRetryCount'] ?? 0),
                'stalePending' => (int)($stalePending['count'] ?? 0),
                'recentFailures' => (int)($recentFailures['count'] ?? 0),
            ],
            'timeline' => [
                'oldestSync' => $stats['oldestSync'] ?? null,
                'newestSync' => $stats['newestSync'] ?? null,
                'dateFrom' => $dateFrom,
                'dateTo' => $dateTo,
            ],
        ];
    }

    /**
     * Check if an entity has a pending sync.
     *
     * @param string $entityType Entity type
     * @param int $entityID Entity ID
     * @return bool
     */
    public function hasPendingSync($entityType, $entityID)
    {
        $sql = "SELECT COUNT(*) as count
                FROM gibbonAISyncLog
                WHERE entityType = :entityType
                AND entityID = :entityID
                AND status = 'pending'";

        $result = $this->db()->selectOne($sql, [
            'entityType' => $entityType,
            'entityID' => $entityID,
        ]);

        return ($result['count'] ?? 0) > 0;
    }

    /**
     * Get the last sync status for an entity.
     *
     * @param string $entityType Entity type
     * @param int $entityID Entity ID
     * @return array|null
     */
    public function getLastSyncStatus($entityType, $entityID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonAISyncLog.gibbonAISyncLogID',
                'gibbonAISyncLog.eventType',
                'gibbonAISyncLog.status',
                'gibbonAISyncLog.retryCount',
                'gibbonAISyncLog.errorMessage',
                'gibbonAISyncLog.timestampCreated',
                'gibbonAISyncLog.timestampProcessed',
            ])
            ->where('gibbonAISyncLog.entityType=:entityType')
            ->bindValue('entityType', $entityType)
            ->where('gibbonAISyncLog.entityID=:entityID')
            ->bindValue('entityID', $entityID)
            ->orderBy(['gibbonAISyncLog.timestampCreated DESC'])
            ->limit(1);

        return $this->runSelect($query)->fetch();
    }
}
