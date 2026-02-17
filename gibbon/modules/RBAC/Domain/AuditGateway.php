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

namespace Gibbon\Module\RBAC\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\QueryableGateway;

/**
 * RBAC Audit Gateway
 *
 * Handles audit trail database operations for logging access and modifications.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class AuditGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonRBACAuditLog';
    private static $primaryKey = 'gibbonRBACAuditLogID';

    private static $searchableColumns = ['gibbonRBACAuditLog.resourceType', 'gibbonRBACAuditLog.details'];

    /**
     * Query audit logs with criteria support.
     *
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryAuditLogs(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonRBACAuditLog.gibbonRBACAuditLogID',
                'gibbonRBACAuditLog.gibbonPersonID',
                'gibbonRBACAuditLog.action',
                'gibbonRBACAuditLog.resourceType',
                'gibbonRBACAuditLog.resourceID',
                'gibbonRBACAuditLog.details',
                'gibbonRBACAuditLog.ipAddress',
                'gibbonRBACAuditLog.userAgent',
                'gibbonRBACAuditLog.organizationID',
                'gibbonRBACAuditLog.success',
                'gibbonRBACAuditLog.timestampCreated',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
            ])
            ->innerJoin('gibbonPerson', 'gibbonRBACAuditLog.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->orderBy(['gibbonRBACAuditLog.timestampCreated DESC']);

        $criteria->addFilterRules([
            'person' => function ($query, $gibbonPersonID) {
                return $query
                    ->where('gibbonRBACAuditLog.gibbonPersonID=:gibbonPersonID')
                    ->bindValue('gibbonPersonID', $gibbonPersonID);
            },
            'action' => function ($query, $action) {
                return $query
                    ->where('gibbonRBACAuditLog.action=:action')
                    ->bindValue('action', $action);
            },
            'resourceType' => function ($query, $resourceType) {
                return $query
                    ->where('gibbonRBACAuditLog.resourceType=:resourceType')
                    ->bindValue('resourceType', $resourceType);
            },
            'resourceID' => function ($query, $resourceID) {
                return $query
                    ->where('gibbonRBACAuditLog.resourceID=:resourceID')
                    ->bindValue('resourceID', $resourceID);
            },
            'success' => function ($query, $value) {
                return $query
                    ->where('gibbonRBACAuditLog.success=:success')
                    ->bindValue('success', $value);
            },
            'dateFrom' => function ($query, $date) {
                return $query
                    ->where('DATE(gibbonRBACAuditLog.timestampCreated) >= :dateFrom')
                    ->bindValue('dateFrom', $date);
            },
            'dateTo' => function ($query, $date) {
                return $query
                    ->where('DATE(gibbonRBACAuditLog.timestampCreated) <= :dateTo')
                    ->bindValue('dateTo', $date);
            },
            'organizationID' => function ($query, $organizationID) {
                return $query
                    ->where('gibbonRBACAuditLog.organizationID=:organizationID')
                    ->bindValue('organizationID', $organizationID);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query audit logs for a specific date range.
     *
     * @param QueryCriteria $criteria
     * @param string $dateFrom
     * @param string $dateTo
     * @return DataSet
     */
    public function queryAuditLogsByDateRange(QueryCriteria $criteria, $dateFrom, $dateTo)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonRBACAuditLog.gibbonRBACAuditLogID',
                'gibbonRBACAuditLog.gibbonPersonID',
                'gibbonRBACAuditLog.action',
                'gibbonRBACAuditLog.resourceType',
                'gibbonRBACAuditLog.resourceID',
                'gibbonRBACAuditLog.details',
                'gibbonRBACAuditLog.ipAddress',
                'gibbonRBACAuditLog.success',
                'gibbonRBACAuditLog.timestampCreated',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
            ])
            ->innerJoin('gibbonPerson', 'gibbonRBACAuditLog.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->where('DATE(gibbonRBACAuditLog.timestampCreated) >= :dateFrom')
            ->bindValue('dateFrom', $dateFrom)
            ->where('DATE(gibbonRBACAuditLog.timestampCreated) <= :dateTo')
            ->bindValue('dateTo', $dateTo)
            ->orderBy(['gibbonRBACAuditLog.timestampCreated DESC']);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query audit logs for a specific person.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonPersonID
     * @return DataSet
     */
    public function queryAuditLogsByPerson(QueryCriteria $criteria, $gibbonPersonID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonRBACAuditLog.gibbonRBACAuditLogID',
                'gibbonRBACAuditLog.action',
                'gibbonRBACAuditLog.resourceType',
                'gibbonRBACAuditLog.resourceID',
                'gibbonRBACAuditLog.details',
                'gibbonRBACAuditLog.ipAddress',
                'gibbonRBACAuditLog.success',
                'gibbonRBACAuditLog.timestampCreated',
            ])
            ->where('gibbonRBACAuditLog.gibbonPersonID=:gibbonPersonID')
            ->bindValue('gibbonPersonID', $gibbonPersonID)
            ->orderBy(['gibbonRBACAuditLog.timestampCreated DESC']);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query audit logs for a specific resource.
     *
     * @param QueryCriteria $criteria
     * @param string $resourceType
     * @param int|null $resourceID
     * @return DataSet
     */
    public function queryAuditLogsByResource(QueryCriteria $criteria, $resourceType, $resourceID = null)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonRBACAuditLog.gibbonRBACAuditLogID',
                'gibbonRBACAuditLog.gibbonPersonID',
                'gibbonRBACAuditLog.action',
                'gibbonRBACAuditLog.resourceID',
                'gibbonRBACAuditLog.details',
                'gibbonRBACAuditLog.ipAddress',
                'gibbonRBACAuditLog.success',
                'gibbonRBACAuditLog.timestampCreated',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
            ])
            ->innerJoin('gibbonPerson', 'gibbonRBACAuditLog.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->where('gibbonRBACAuditLog.resourceType=:resourceType')
            ->bindValue('resourceType', $resourceType);

        if ($resourceID !== null) {
            $query->where('gibbonRBACAuditLog.resourceID=:resourceID')
                  ->bindValue('resourceID', $resourceID);
        }

        $query->orderBy(['gibbonRBACAuditLog.timestampCreated DESC']);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Log an action to the audit trail.
     *
     * @param int $gibbonPersonID
     * @param string $action
     * @param string|null $resourceType
     * @param int|null $resourceID
     * @param array|string|null $details
     * @param bool $success
     * @param string|null $ipAddress
     * @param string|null $userAgent
     * @param string|null $organizationID
     * @return int|false
     */
    public function logAction($gibbonPersonID, $action, $resourceType = null, $resourceID = null, $details = null, $success = true, $ipAddress = null, $userAgent = null, $organizationID = null)
    {
        $data = [
            'gibbonPersonID' => $gibbonPersonID,
            'action' => $action,
            'resourceType' => $resourceType,
            'resourceID' => $resourceID,
            'details' => is_array($details) ? json_encode($details) : $details,
            'success' => $success ? 'Y' : 'N',
            'ipAddress' => $ipAddress,
            'userAgent' => $userAgent,
            'organizationID' => $organizationID,
        ];

        return $this->insert($data);
    }

    /**
     * Log a login action.
     *
     * @param int $gibbonPersonID
     * @param string|null $ipAddress
     * @param string|null $userAgent
     * @param bool $success
     * @return int|false
     */
    public function logLogin($gibbonPersonID, $ipAddress = null, $userAgent = null, $success = true)
    {
        return $this->logAction(
            $gibbonPersonID,
            'login',
            null,
            null,
            ['event' => 'User login'],
            $success,
            $ipAddress,
            $userAgent
        );
    }

    /**
     * Log a logout action.
     *
     * @param int $gibbonPersonID
     * @param string|null $ipAddress
     * @return int|false
     */
    public function logLogout($gibbonPersonID, $ipAddress = null)
    {
        return $this->logAction(
            $gibbonPersonID,
            'logout',
            null,
            null,
            ['event' => 'User logout'],
            true,
            $ipAddress
        );
    }

    /**
     * Log an access granted event.
     *
     * @param int $gibbonPersonID
     * @param string $resourceType
     * @param int|null $resourceID
     * @param string|null $ipAddress
     * @return int|false
     */
    public function logAccessGranted($gibbonPersonID, $resourceType, $resourceID = null, $ipAddress = null)
    {
        return $this->logAction(
            $gibbonPersonID,
            'access_granted',
            $resourceType,
            $resourceID,
            ['event' => 'Access granted to resource'],
            true,
            $ipAddress
        );
    }

    /**
     * Log an access denied event.
     *
     * @param int $gibbonPersonID
     * @param string $resourceType
     * @param int|null $resourceID
     * @param string $reason
     * @param string|null $ipAddress
     * @return int|false
     */
    public function logAccessDenied($gibbonPersonID, $resourceType, $resourceID = null, $reason = '', $ipAddress = null)
    {
        return $this->logAction(
            $gibbonPersonID,
            'access_denied',
            $resourceType,
            $resourceID,
            ['event' => 'Access denied', 'reason' => $reason],
            false,
            $ipAddress
        );
    }

    /**
     * Log a data read event.
     *
     * @param int $gibbonPersonID
     * @param string $resourceType
     * @param int $resourceID
     * @param string|null $ipAddress
     * @return int|false
     */
    public function logDataRead($gibbonPersonID, $resourceType, $resourceID, $ipAddress = null)
    {
        return $this->logAction(
            $gibbonPersonID,
            'data_read',
            $resourceType,
            $resourceID,
            ['event' => 'Data read'],
            true,
            $ipAddress
        );
    }

    /**
     * Log a data create event.
     *
     * @param int $gibbonPersonID
     * @param string $resourceType
     * @param int $resourceID
     * @param array|null $data
     * @param string|null $ipAddress
     * @return int|false
     */
    public function logDataCreate($gibbonPersonID, $resourceType, $resourceID, $data = null, $ipAddress = null)
    {
        $details = ['event' => 'Data created'];
        if ($data !== null) {
            $details['data'] = $data;
        }

        return $this->logAction(
            $gibbonPersonID,
            'data_create',
            $resourceType,
            $resourceID,
            $details,
            true,
            $ipAddress
        );
    }

    /**
     * Log a data update event.
     *
     * @param int $gibbonPersonID
     * @param string $resourceType
     * @param int $resourceID
     * @param array|null $changes
     * @param string|null $ipAddress
     * @return int|false
     */
    public function logDataUpdate($gibbonPersonID, $resourceType, $resourceID, $changes = null, $ipAddress = null)
    {
        $details = ['event' => 'Data updated'];
        if ($changes !== null) {
            $details['changes'] = $changes;
        }

        return $this->logAction(
            $gibbonPersonID,
            'data_update',
            $resourceType,
            $resourceID,
            $details,
            true,
            $ipAddress
        );
    }

    /**
     * Log a data delete event.
     *
     * @param int $gibbonPersonID
     * @param string $resourceType
     * @param int $resourceID
     * @param string|null $ipAddress
     * @return int|false
     */
    public function logDataDelete($gibbonPersonID, $resourceType, $resourceID, $ipAddress = null)
    {
        return $this->logAction(
            $gibbonPersonID,
            'data_delete',
            $resourceType,
            $resourceID,
            ['event' => 'Data deleted'],
            true,
            $ipAddress
        );
    }

    /**
     * Log a role assignment event.
     *
     * @param int $gibbonPersonID
     * @param int $targetPersonID
     * @param int $gibbonRBACRoleID
     * @param string $roleName
     * @param string|null $ipAddress
     * @return int|false
     */
    public function logRoleAssigned($gibbonPersonID, $targetPersonID, $gibbonRBACRoleID, $roleName, $ipAddress = null)
    {
        return $this->logAction(
            $gibbonPersonID,
            'role_assigned',
            'user_role',
            $targetPersonID,
            [
                'event' => 'Role assigned',
                'role_id' => $gibbonRBACRoleID,
                'role_name' => $roleName,
                'target_person_id' => $targetPersonID,
            ],
            true,
            $ipAddress
        );
    }

    /**
     * Log a role revocation event.
     *
     * @param int $gibbonPersonID
     * @param int $targetPersonID
     * @param int $gibbonRBACRoleID
     * @param string $roleName
     * @param string|null $ipAddress
     * @return int|false
     */
    public function logRoleRevoked($gibbonPersonID, $targetPersonID, $gibbonRBACRoleID, $roleName, $ipAddress = null)
    {
        return $this->logAction(
            $gibbonPersonID,
            'role_revoked',
            'user_role',
            $targetPersonID,
            [
                'event' => 'Role revoked',
                'role_id' => $gibbonRBACRoleID,
                'role_name' => $roleName,
                'target_person_id' => $targetPersonID,
            ],
            true,
            $ipAddress
        );
    }

    /**
     * Get audit log summary statistics.
     *
     * @param string|null $dateFrom
     * @param string|null $dateTo
     * @return array
     */
    public function getAuditSummary($dateFrom = null, $dateTo = null)
    {
        $data = [];
        $whereClause = '';

        if ($dateFrom !== null && $dateTo !== null) {
            $whereClause = 'WHERE DATE(timestampCreated) >= :dateFrom AND DATE(timestampCreated) <= :dateTo';
            $data['dateFrom'] = $dateFrom;
            $data['dateTo'] = $dateTo;
        }

        $sql = "SELECT
                    action,
                    COUNT(*) as count,
                    SUM(CASE WHEN success='Y' THEN 1 ELSE 0 END) as successful,
                    SUM(CASE WHEN success='N' THEN 1 ELSE 0 END) as failed
                FROM gibbonRBACAuditLog
                {$whereClause}
                GROUP BY action
                ORDER BY count DESC";

        return $this->db()->select($sql, $data)->fetchAll();
    }

    /**
     * Get unauthorized access attempts.
     *
     * @param string|null $dateFrom
     * @param string|null $dateTo
     * @param int $limit
     * @return \Gibbon\Database\Result
     */
    public function selectUnauthorizedAccessAttempts($dateFrom = null, $dateTo = null, $limit = 100)
    {
        $data = ['limit' => $limit];
        $whereClause = "WHERE gibbonRBACAuditLog.action='access_denied'";

        if ($dateFrom !== null) {
            $whereClause .= ' AND DATE(gibbonRBACAuditLog.timestampCreated) >= :dateFrom';
            $data['dateFrom'] = $dateFrom;
        }

        if ($dateTo !== null) {
            $whereClause .= ' AND DATE(gibbonRBACAuditLog.timestampCreated) <= :dateTo';
            $data['dateTo'] = $dateTo;
        }

        $sql = "SELECT
                    gibbonRBACAuditLog.gibbonRBACAuditLogID,
                    gibbonRBACAuditLog.gibbonPersonID,
                    gibbonRBACAuditLog.resourceType,
                    gibbonRBACAuditLog.resourceID,
                    gibbonRBACAuditLog.details,
                    gibbonRBACAuditLog.ipAddress,
                    gibbonRBACAuditLog.timestampCreated,
                    gibbonPerson.preferredName,
                    gibbonPerson.surname
                FROM gibbonRBACAuditLog
                INNER JOIN gibbonPerson ON gibbonRBACAuditLog.gibbonPersonID=gibbonPerson.gibbonPersonID
                {$whereClause}
                ORDER BY gibbonRBACAuditLog.timestampCreated DESC
                LIMIT :limit";

        return $this->db()->select($sql, $data);
    }

    /**
     * Count audit logs by action type for a specific person.
     *
     * @param int $gibbonPersonID
     * @return array
     */
    public function getPersonAuditSummary($gibbonPersonID)
    {
        $data = ['gibbonPersonID' => $gibbonPersonID];
        $sql = "SELECT
                    action,
                    COUNT(*) as count,
                    MAX(timestampCreated) as lastOccurrence
                FROM gibbonRBACAuditLog
                WHERE gibbonPersonID=:gibbonPersonID
                GROUP BY action
                ORDER BY count DESC";

        return $this->db()->select($sql, $data)->fetchAll();
    }

    /**
     * Clean up old audit logs based on retention policy.
     *
     * @param int $retentionDays
     * @return int Number of deleted records
     */
    public function purgeOldLogs($retentionDays)
    {
        $data = ['retentionDays' => $retentionDays];
        $sql = "DELETE FROM gibbonRBACAuditLog
                WHERE timestampCreated < DATE_SUB(NOW(), INTERVAL :retentionDays DAY)";

        return $this->db()->statement($sql, $data);
    }

    /**
     * Get most active users in audit log.
     *
     * @param string|null $dateFrom
     * @param string|null $dateTo
     * @param int $limit
     * @return array
     */
    public function getMostActiveUsers($dateFrom = null, $dateTo = null, $limit = 10)
    {
        $data = ['limit' => $limit];
        $whereClause = '';

        if ($dateFrom !== null && $dateTo !== null) {
            $whereClause = 'WHERE DATE(gibbonRBACAuditLog.timestampCreated) >= :dateFrom AND DATE(gibbonRBACAuditLog.timestampCreated) <= :dateTo';
            $data['dateFrom'] = $dateFrom;
            $data['dateTo'] = $dateTo;
        }

        $sql = "SELECT
                    gibbonPerson.gibbonPersonID,
                    gibbonPerson.preferredName,
                    gibbonPerson.surname,
                    COUNT(*) as actionCount,
                    MAX(gibbonRBACAuditLog.timestampCreated) as lastActivity
                FROM gibbonRBACAuditLog
                INNER JOIN gibbonPerson ON gibbonRBACAuditLog.gibbonPersonID=gibbonPerson.gibbonPersonID
                {$whereClause}
                GROUP BY gibbonPerson.gibbonPersonID
                ORDER BY actionCount DESC
                LIMIT :limit";

        return $this->db()->select($sql, $data)->fetchAll();
    }
}
