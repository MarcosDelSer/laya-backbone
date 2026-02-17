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

namespace Gibbon\Module\System\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\QueryableGateway;

/**
 * Auth Token Log Gateway
 *
 * Handles database operations for authentication token exchange audit logs.
 * Provides comprehensive logging and analytics for JWT token generation
 * from Gibbon PHP sessions.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class AuthTokenLogGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonAuthTokenLog';
    private static $primaryKey = 'gibbonAuthTokenLogID';

    private static $searchableColumns = [
        'gibbonAuthTokenLog.username',
        'gibbonAuthTokenLog.ipAddress',
        'gibbonAuthTokenLog.errorMessage'
    ];

    /**
     * Query token exchange logs with criteria support.
     *
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryTokenLogs(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonAuthTokenLog.gibbonAuthTokenLogID',
                'gibbonAuthTokenLog.gibbonPersonID',
                'gibbonAuthTokenLog.username',
                'gibbonAuthTokenLog.sessionID',
                'gibbonAuthTokenLog.tokenStatus',
                'gibbonAuthTokenLog.ipAddress',
                'gibbonAuthTokenLog.userAgent',
                'gibbonAuthTokenLog.gibbonRoleIDPrimary',
                'gibbonAuthTokenLog.aiRole',
                'gibbonAuthTokenLog.errorMessage',
                'gibbonAuthTokenLog.expiresAt',
                'gibbonAuthTokenLog.timestampCreated',
            ]);

        $criteria->addFilterRules([
            'tokenStatus' => function ($query, $status) {
                return $query
                    ->where('gibbonAuthTokenLog.tokenStatus=:tokenStatus')
                    ->bindValue('tokenStatus', $status);
            },
            'gibbonPersonID' => function ($query, $personID) {
                return $query
                    ->where('gibbonAuthTokenLog.gibbonPersonID=:gibbonPersonID')
                    ->bindValue('gibbonPersonID', $personID);
            },
            'username' => function ($query, $username) {
                return $query
                    ->where('gibbonAuthTokenLog.username=:username')
                    ->bindValue('username', $username);
            },
            'ipAddress' => function ($query, $ipAddress) {
                return $query
                    ->where('gibbonAuthTokenLog.ipAddress=:ipAddress')
                    ->bindValue('ipAddress', $ipAddress);
            },
            'dateFrom' => function ($query, $date) {
                return $query
                    ->where('DATE(gibbonAuthTokenLog.timestampCreated) >= :dateFrom')
                    ->bindValue('dateFrom', $date);
            },
            'dateTo' => function ($query, $date) {
                return $query
                    ->where('DATE(gibbonAuthTokenLog.timestampCreated) <= :dateTo')
                    ->bindValue('dateTo', $date);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query token logs by status.
     *
     * @param QueryCriteria $criteria
     * @param string $status Status: success, failed, or expired
     * @return DataSet
     */
    public function queryTokenLogsByStatus(QueryCriteria $criteria, $status)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonAuthTokenLog.*',
            ])
            ->where('gibbonAuthTokenLog.tokenStatus=:status')
            ->bindValue('status', $status)
            ->orderBy(['gibbonAuthTokenLog.timestampCreated DESC']);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query token logs for a specific user.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonPersonID User ID
     * @return DataSet
     */
    public function queryTokenLogsByPerson(QueryCriteria $criteria, $gibbonPersonID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonAuthTokenLog.*',
            ])
            ->where('gibbonAuthTokenLog.gibbonPersonID=:gibbonPersonID')
            ->bindValue('gibbonPersonID', $gibbonPersonID)
            ->orderBy(['gibbonAuthTokenLog.timestampCreated DESC']);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Get token exchange statistics summary.
     *
     * @param string|null $dateFrom Start date filter (Y-m-d format)
     * @param string|null $dateTo End date filter (Y-m-d format)
     * @return array
     */
    public function getTokenStatistics($dateFrom = null, $dateTo = null)
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
                    COUNT(*) as totalExchanges,
                    SUM(CASE WHEN tokenStatus='success' THEN 1 ELSE 0 END) as successfulExchanges,
                    SUM(CASE WHEN tokenStatus='failed' THEN 1 ELSE 0 END) as failedExchanges,
                    SUM(CASE WHEN tokenStatus='expired' THEN 1 ELSE 0 END) as expiredTokens,
                    COUNT(DISTINCT gibbonPersonID) as uniqueUsers,
                    COUNT(DISTINCT ipAddress) as uniqueIPs
                FROM gibbonAuthTokenLog
                WHERE 1=1{$whereClause}";

        return $this->db()->selectOne($sql, $data) ?: [
            'totalExchanges' => 0,
            'successfulExchanges' => 0,
            'failedExchanges' => 0,
            'expiredTokens' => 0,
            'uniqueUsers' => 0,
            'uniqueIPs' => 0,
        ];
    }

    /**
     * Get token exchange statistics by role.
     *
     * @param string|null $dateFrom Start date filter
     * @param string|null $dateTo End date filter
     * @return array
     */
    public function getTokenStatisticsByRole($dateFrom = null, $dateTo = null)
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
                    aiRole,
                    COUNT(*) as count,
                    SUM(CASE WHEN tokenStatus='success' THEN 1 ELSE 0 END) as successCount,
                    SUM(CASE WHEN tokenStatus='failed' THEN 1 ELSE 0 END) as failedCount
                FROM gibbonAuthTokenLog
                WHERE aiRole IS NOT NULL{$whereClause}
                GROUP BY aiRole
                ORDER BY count DESC";

        return $this->db()->select($sql, $data)->fetchAll();
    }

    /**
     * Get suspicious activity (e.g., multiple failed attempts).
     *
     * @param int $failedAttempts Minimum failed attempts threshold
     * @param int $withinMinutes Within how many minutes
     * @return array
     */
    public function getSuspiciousActivity($failedAttempts = 5, $withinMinutes = 30)
    {
        $sql = "SELECT
                    username,
                    ipAddress,
                    COUNT(*) as failedCount,
                    MAX(timestampCreated) as lastAttempt,
                    MIN(timestampCreated) as firstAttempt
                FROM gibbonAuthTokenLog
                WHERE tokenStatus='failed'
                AND timestampCreated >= DATE_SUB(NOW(), INTERVAL :withinMinutes MINUTE)
                GROUP BY username, ipAddress
                HAVING COUNT(*) >= :failedAttempts
                ORDER BY failedCount DESC, lastAttempt DESC";

        return $this->db()->select($sql, [
            'failedAttempts' => $failedAttempts,
            'withinMinutes' => $withinMinutes,
        ])->fetchAll();
    }

    /**
     * Create a token exchange log entry.
     *
     * @param array $data Log data
     * @return int|false The new log ID or false on failure
     */
    public function logTokenExchange(array $data)
    {
        // Required fields
        $logData = [
            'gibbonPersonID' => $data['gibbonPersonID'] ?? null,
            'username' => $data['username'] ?? '',
            'sessionID' => $data['sessionID'] ?? '',
            'tokenStatus' => $data['tokenStatus'] ?? 'success',
        ];

        // Optional fields
        if (isset($data['ipAddress'])) {
            $logData['ipAddress'] = $data['ipAddress'];
        }

        if (isset($data['userAgent'])) {
            $logData['userAgent'] = $data['userAgent'];
        }

        if (isset($data['gibbonRoleIDPrimary'])) {
            $logData['gibbonRoleIDPrimary'] = $data['gibbonRoleIDPrimary'];
        }

        if (isset($data['aiRole'])) {
            $logData['aiRole'] = $data['aiRole'];
        }

        if (isset($data['errorMessage'])) {
            $logData['errorMessage'] = $data['errorMessage'];
        }

        if (isset($data['expiresAt'])) {
            $logData['expiresAt'] = $data['expiresAt'];
        }

        return $this->insert($logData);
    }

    /**
     * Get recent token exchanges for dashboard display.
     *
     * @param int $limit Number of recent records to return
     * @return array
     */
    public function getRecentTokenExchanges($limit = 10)
    {
        $sql = "SELECT
                    gibbonAuthTokenLogID,
                    gibbonPersonID,
                    username,
                    tokenStatus,
                    ipAddress,
                    aiRole,
                    errorMessage,
                    timestampCreated,
                    expiresAt
                FROM gibbonAuthTokenLog
                ORDER BY timestampCreated DESC
                LIMIT :limit";

        return $this->db()->select($sql, ['limit' => $limit])->fetchAll();
    }

    /**
     * Get user's token exchange history.
     *
     * @param int $gibbonPersonID User ID
     * @param int $limit Number of records to return
     * @return array
     */
    public function getUserTokenHistory($gibbonPersonID, $limit = 20)
    {
        $sql = "SELECT
                    gibbonAuthTokenLogID,
                    sessionID,
                    tokenStatus,
                    ipAddress,
                    userAgent,
                    aiRole,
                    errorMessage,
                    timestampCreated,
                    expiresAt
                FROM gibbonAuthTokenLog
                WHERE gibbonPersonID = :gibbonPersonID
                ORDER BY timestampCreated DESC
                LIMIT :limit";

        return $this->db()->select($sql, [
            'gibbonPersonID' => $gibbonPersonID,
            'limit' => $limit,
        ])->fetchAll();
    }

    /**
     * Check if user has recent failed token attempts.
     *
     * @param int $gibbonPersonID User ID
     * @param int $withinMinutes Check within how many minutes
     * @param int $threshold Number of failed attempts threshold
     * @return bool
     */
    public function hasRecentFailedAttempts($gibbonPersonID, $withinMinutes = 15, $threshold = 3)
    {
        $sql = "SELECT COUNT(*) as count
                FROM gibbonAuthTokenLog
                WHERE gibbonPersonID = :gibbonPersonID
                AND tokenStatus = 'failed'
                AND timestampCreated >= DATE_SUB(NOW(), INTERVAL :withinMinutes MINUTE)";

        $result = $this->db()->selectOne($sql, [
            'gibbonPersonID' => $gibbonPersonID,
            'withinMinutes' => $withinMinutes,
        ]);

        return ($result['count'] ?? 0) >= $threshold;
    }

    /**
     * Delete old token logs.
     *
     * @param int $daysOld Number of days after which to delete successful logs
     * @return int Number of deleted records
     */
    public function deleteOldTokenLogs($daysOld = 90)
    {
        $sql = "DELETE FROM gibbonAuthTokenLog
                WHERE tokenStatus = 'success'
                AND timestampCreated < DATE_SUB(NOW(), INTERVAL :daysOld DAY)";

        $this->db()->statement($sql, ['daysOld' => $daysOld]);

        return $this->db()->getConnection()->rowCount();
    }

    /**
     * Get hourly token exchange activity (for charts/analytics).
     *
     * @param string|null $dateFrom Start date
     * @param string|null $dateTo End date
     * @return array
     */
    public function getHourlyActivity($dateFrom = null, $dateTo = null)
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
                    DATE_FORMAT(timestampCreated, '%Y-%m-%d %H:00:00') as hour,
                    COUNT(*) as totalExchanges,
                    SUM(CASE WHEN tokenStatus='success' THEN 1 ELSE 0 END) as successCount,
                    SUM(CASE WHEN tokenStatus='failed' THEN 1 ELSE 0 END) as failedCount
                FROM gibbonAuthTokenLog
                WHERE 1=1{$whereClause}
                GROUP BY hour
                ORDER BY hour ASC";

        return $this->db()->select($sql, $data)->fetchAll();
    }
}
