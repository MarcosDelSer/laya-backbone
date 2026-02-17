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

namespace Gibbon\Module\StaffManagement\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\QueryableGateway;

/**
 * Staff Audit Log Gateway
 *
 * Handles tracking of all modifications to staff records with user attribution.
 * Supports INSERT, UPDATE, and DELETE operations with field-level change tracking.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class AuditLogGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonStaffAuditLog';
    private static $primaryKey = 'gibbonStaffAuditLogID';

    private static $searchableColumns = ['gibbonStaffAuditLog.tableName', 'gibbonStaffAuditLog.fieldName', 'gibbonStaffAuditLog.oldValue', 'gibbonStaffAuditLog.newValue'];

    /**
     * Query audit log entries with criteria support.
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
                'gibbonStaffAuditLog.gibbonStaffAuditLogID',
                'gibbonStaffAuditLog.tableName',
                'gibbonStaffAuditLog.recordID',
                'gibbonStaffAuditLog.action',
                'gibbonStaffAuditLog.fieldName',
                'gibbonStaffAuditLog.oldValue',
                'gibbonStaffAuditLog.newValue',
                'gibbonStaffAuditLog.gibbonPersonID',
                'gibbonStaffAuditLog.ipAddress',
                'gibbonStaffAuditLog.userAgent',
                'gibbonStaffAuditLog.sessionID',
                'gibbonStaffAuditLog.timestampCreated',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.username',
            ])
            ->leftJoin('gibbonPerson', 'gibbonStaffAuditLog.gibbonPersonID=gibbonPerson.gibbonPersonID');

        $criteria->addFilterRules([
            'tableName' => function ($query, $tableName) {
                return $query
                    ->where('gibbonStaffAuditLog.tableName=:tableName')
                    ->bindValue('tableName', $tableName);
            },
            'recordID' => function ($query, $recordID) {
                return $query
                    ->where('gibbonStaffAuditLog.recordID=:recordID')
                    ->bindValue('recordID', $recordID);
            },
            'action' => function ($query, $action) {
                return $query
                    ->where('gibbonStaffAuditLog.action=:action')
                    ->bindValue('action', $action);
            },
            'person' => function ($query, $gibbonPersonID) {
                return $query
                    ->where('gibbonStaffAuditLog.gibbonPersonID=:gibbonPersonID')
                    ->bindValue('gibbonPersonID', $gibbonPersonID);
            },
            'dateFrom' => function ($query, $dateFrom) {
                return $query
                    ->where('DATE(gibbonStaffAuditLog.timestampCreated)>=:dateFrom')
                    ->bindValue('dateFrom', $dateFrom);
            },
            'dateTo' => function ($query, $dateTo) {
                return $query
                    ->where('DATE(gibbonStaffAuditLog.timestampCreated)<=:dateTo')
                    ->bindValue('dateTo', $dateTo);
            },
            'fieldName' => function ($query, $fieldName) {
                return $query
                    ->where('gibbonStaffAuditLog.fieldName=:fieldName')
                    ->bindValue('fieldName', $fieldName);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query audit logs for a specific table.
     *
     * @param QueryCriteria $criteria
     * @param string $tableName
     * @return DataSet
     */
    public function queryAuditLogsByTable(QueryCriteria $criteria, $tableName)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonStaffAuditLog.gibbonStaffAuditLogID',
                'gibbonStaffAuditLog.recordID',
                'gibbonStaffAuditLog.action',
                'gibbonStaffAuditLog.fieldName',
                'gibbonStaffAuditLog.oldValue',
                'gibbonStaffAuditLog.newValue',
                'gibbonStaffAuditLog.gibbonPersonID',
                'gibbonStaffAuditLog.ipAddress',
                'gibbonStaffAuditLog.timestampCreated',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
            ])
            ->leftJoin('gibbonPerson', 'gibbonStaffAuditLog.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->where('gibbonStaffAuditLog.tableName=:tableName')
            ->bindValue('tableName', $tableName);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query audit logs for a specific record.
     *
     * @param QueryCriteria $criteria
     * @param string $tableName
     * @param int $recordID
     * @return DataSet
     */
    public function queryAuditLogsByRecord(QueryCriteria $criteria, $tableName, $recordID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonStaffAuditLog.gibbonStaffAuditLogID',
                'gibbonStaffAuditLog.action',
                'gibbonStaffAuditLog.fieldName',
                'gibbonStaffAuditLog.oldValue',
                'gibbonStaffAuditLog.newValue',
                'gibbonStaffAuditLog.gibbonPersonID',
                'gibbonStaffAuditLog.ipAddress',
                'gibbonStaffAuditLog.timestampCreated',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
            ])
            ->leftJoin('gibbonPerson', 'gibbonStaffAuditLog.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->where('gibbonStaffAuditLog.tableName=:tableName')
            ->bindValue('tableName', $tableName)
            ->where('gibbonStaffAuditLog.recordID=:recordID')
            ->bindValue('recordID', $recordID);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query audit logs made by a specific user.
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
                'gibbonStaffAuditLog.gibbonStaffAuditLogID',
                'gibbonStaffAuditLog.tableName',
                'gibbonStaffAuditLog.recordID',
                'gibbonStaffAuditLog.action',
                'gibbonStaffAuditLog.fieldName',
                'gibbonStaffAuditLog.oldValue',
                'gibbonStaffAuditLog.newValue',
                'gibbonStaffAuditLog.ipAddress',
                'gibbonStaffAuditLog.timestampCreated',
            ])
            ->where('gibbonStaffAuditLog.gibbonPersonID=:gibbonPersonID')
            ->bindValue('gibbonPersonID', $gibbonPersonID);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Log a single field change.
     *
     * @param string $tableName
     * @param int $recordID
     * @param string $action
     * @param string|null $fieldName
     * @param mixed $oldValue
     * @param mixed $newValue
     * @param int $gibbonPersonID
     * @param string|null $ipAddress
     * @param string|null $userAgent
     * @param string|null $sessionID
     * @return int|false
     */
    public function logChange($tableName, $recordID, $action, $fieldName, $oldValue, $newValue, $gibbonPersonID, $ipAddress = null, $userAgent = null, $sessionID = null)
    {
        return $this->insert([
            'tableName' => $tableName,
            'recordID' => $recordID,
            'action' => $action,
            'fieldName' => $fieldName,
            'oldValue' => is_array($oldValue) || is_object($oldValue) ? json_encode($oldValue) : $oldValue,
            'newValue' => is_array($newValue) || is_object($newValue) ? json_encode($newValue) : $newValue,
            'gibbonPersonID' => $gibbonPersonID,
            'ipAddress' => $ipAddress,
            'userAgent' => $userAgent ? substr($userAgent, 0, 500) : null,
            'sessionID' => $sessionID,
        ]);
    }

    /**
     * Log an INSERT operation.
     *
     * @param string $tableName
     * @param int $recordID
     * @param array $data The inserted data
     * @param int $gibbonPersonID
     * @param string|null $ipAddress
     * @param string|null $userAgent
     * @param string|null $sessionID
     * @return bool
     */
    public function logInsert($tableName, $recordID, $data, $gibbonPersonID, $ipAddress = null, $userAgent = null, $sessionID = null)
    {
        // Log each field that was inserted
        foreach ($data as $fieldName => $value) {
            $this->logChange(
                $tableName,
                $recordID,
                'INSERT',
                $fieldName,
                null,
                $value,
                $gibbonPersonID,
                $ipAddress,
                $userAgent,
                $sessionID
            );
        }

        return true;
    }

    /**
     * Log an UPDATE operation with before/after comparison.
     *
     * @param string $tableName
     * @param int $recordID
     * @param array $oldData The data before update
     * @param array $newData The data after update
     * @param int $gibbonPersonID
     * @param string|null $ipAddress
     * @param string|null $userAgent
     * @param string|null $sessionID
     * @return bool
     */
    public function logUpdate($tableName, $recordID, $oldData, $newData, $gibbonPersonID, $ipAddress = null, $userAgent = null, $sessionID = null)
    {
        // Log only fields that changed
        foreach ($newData as $fieldName => $newValue) {
            $oldValue = $oldData[$fieldName] ?? null;

            // Skip if values are the same
            if ($oldValue === $newValue) {
                continue;
            }

            // Skip timestamp fields that auto-update
            if (in_array($fieldName, ['timestampModified', 'timestampCreated'])) {
                continue;
            }

            $this->logChange(
                $tableName,
                $recordID,
                'UPDATE',
                $fieldName,
                $oldValue,
                $newValue,
                $gibbonPersonID,
                $ipAddress,
                $userAgent,
                $sessionID
            );
        }

        return true;
    }

    /**
     * Log a DELETE operation.
     *
     * @param string $tableName
     * @param int $recordID
     * @param array $data The deleted data (for record keeping)
     * @param int $gibbonPersonID
     * @param string|null $ipAddress
     * @param string|null $userAgent
     * @param string|null $sessionID
     * @return bool
     */
    public function logDelete($tableName, $recordID, $data, $gibbonPersonID, $ipAddress = null, $userAgent = null, $sessionID = null)
    {
        // Log each field that was deleted
        foreach ($data as $fieldName => $value) {
            $this->logChange(
                $tableName,
                $recordID,
                'DELETE',
                $fieldName,
                $value,
                null,
                $gibbonPersonID,
                $ipAddress,
                $userAgent,
                $sessionID
            );
        }

        return true;
    }

    /**
     * Get the complete change history for a specific record.
     *
     * @param string $tableName
     * @param int $recordID
     * @return \Gibbon\Database\Result
     */
    public function getChangeHistory($tableName, $recordID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonStaffAuditLog.gibbonStaffAuditLogID',
                'gibbonStaffAuditLog.action',
                'gibbonStaffAuditLog.fieldName',
                'gibbonStaffAuditLog.oldValue',
                'gibbonStaffAuditLog.newValue',
                'gibbonStaffAuditLog.gibbonPersonID',
                'gibbonStaffAuditLog.ipAddress',
                'gibbonStaffAuditLog.timestampCreated',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
            ])
            ->leftJoin('gibbonPerson', 'gibbonStaffAuditLog.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->where('gibbonStaffAuditLog.tableName=:tableName')
            ->bindValue('tableName', $tableName)
            ->where('gibbonStaffAuditLog.recordID=:recordID')
            ->bindValue('recordID', $recordID)
            ->orderBy(['gibbonStaffAuditLog.timestampCreated DESC']);

        return $this->runSelect($query);
    }

    /**
     * Select recent changes across all tables.
     *
     * @param int $limit
     * @return \Gibbon\Database\Result
     */
    public function selectRecentChanges($limit = 50)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonStaffAuditLog.gibbonStaffAuditLogID',
                'gibbonStaffAuditLog.tableName',
                'gibbonStaffAuditLog.recordID',
                'gibbonStaffAuditLog.action',
                'gibbonStaffAuditLog.fieldName',
                'gibbonStaffAuditLog.oldValue',
                'gibbonStaffAuditLog.newValue',
                'gibbonStaffAuditLog.gibbonPersonID',
                'gibbonStaffAuditLog.ipAddress',
                'gibbonStaffAuditLog.timestampCreated',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
            ])
            ->leftJoin('gibbonPerson', 'gibbonStaffAuditLog.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->orderBy(['gibbonStaffAuditLog.timestampCreated DESC'])
            ->limit($limit);

        return $this->runSelect($query);
    }

    /**
     * Select changes within a specific date range.
     *
     * @param string $dateStart
     * @param string $dateEnd
     * @param string|null $tableName Optional table filter
     * @return \Gibbon\Database\Result
     */
    public function selectChangesByDateRange($dateStart, $dateEnd, $tableName = null)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonStaffAuditLog.gibbonStaffAuditLogID',
                'gibbonStaffAuditLog.tableName',
                'gibbonStaffAuditLog.recordID',
                'gibbonStaffAuditLog.action',
                'gibbonStaffAuditLog.fieldName',
                'gibbonStaffAuditLog.oldValue',
                'gibbonStaffAuditLog.newValue',
                'gibbonStaffAuditLog.gibbonPersonID',
                'gibbonStaffAuditLog.ipAddress',
                'gibbonStaffAuditLog.timestampCreated',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
            ])
            ->leftJoin('gibbonPerson', 'gibbonStaffAuditLog.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->where('DATE(gibbonStaffAuditLog.timestampCreated)>=:dateStart')
            ->bindValue('dateStart', $dateStart)
            ->where('DATE(gibbonStaffAuditLog.timestampCreated)<=:dateEnd')
            ->bindValue('dateEnd', $dateEnd)
            ->orderBy(['gibbonStaffAuditLog.timestampCreated DESC']);

        if ($tableName !== null) {
            $query->where('gibbonStaffAuditLog.tableName=:tableName')
                  ->bindValue('tableName', $tableName);
        }

        return $this->runSelect($query);
    }

    /**
     * Delete audit log entries older than specified retention days.
     *
     * @param int $retentionDays
     * @return int Number of rows deleted
     */
    public function cleanupOldLogs($retentionDays = 365)
    {
        $data = ['cutoffDate' => date('Y-m-d H:i:s', strtotime("-{$retentionDays} days"))];
        $sql = "DELETE FROM {$this->getTableName()} WHERE timestampCreated < :cutoffDate";

        return $this->db()->delete($sql, $data);
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
        $conditions = '';
        $data = [];

        if ($dateFrom !== null) {
            $conditions .= ' AND DATE(timestampCreated)>=:dateFrom';
            $data['dateFrom'] = $dateFrom;
        }

        if ($dateTo !== null) {
            $conditions .= ' AND DATE(timestampCreated)<=:dateTo';
            $data['dateTo'] = $dateTo;
        }

        $sql = "SELECT
                    COUNT(*) as totalChanges,
                    SUM(CASE WHEN action='INSERT' THEN 1 ELSE 0 END) as totalInserts,
                    SUM(CASE WHEN action='UPDATE' THEN 1 ELSE 0 END) as totalUpdates,
                    SUM(CASE WHEN action='DELETE' THEN 1 ELSE 0 END) as totalDeletes,
                    COUNT(DISTINCT tableName) as tablesAffected,
                    COUNT(DISTINCT recordID) as recordsAffected,
                    COUNT(DISTINCT gibbonPersonID) as uniqueUsers
                FROM {$this->getTableName()}
                WHERE 1=1 {$conditions}";

        return $this->db()->selectOne($sql, $data) ?: [
            'totalChanges' => 0,
            'totalInserts' => 0,
            'totalUpdates' => 0,
            'totalDeletes' => 0,
            'tablesAffected' => 0,
            'recordsAffected' => 0,
            'uniqueUsers' => 0,
        ];
    }

    /**
     * Get summary of changes by table.
     *
     * @param string|null $dateFrom
     * @param string|null $dateTo
     * @return \Gibbon\Database\Result
     */
    public function selectChangesSummaryByTable($dateFrom = null, $dateTo = null)
    {
        $conditions = '';
        $data = [];

        if ($dateFrom !== null) {
            $conditions .= ' AND DATE(timestampCreated)>=:dateFrom';
            $data['dateFrom'] = $dateFrom;
        }

        if ($dateTo !== null) {
            $conditions .= ' AND DATE(timestampCreated)<=:dateTo';
            $data['dateTo'] = $dateTo;
        }

        $sql = "SELECT
                    tableName,
                    COUNT(*) as totalChanges,
                    SUM(CASE WHEN action='INSERT' THEN 1 ELSE 0 END) as inserts,
                    SUM(CASE WHEN action='UPDATE' THEN 1 ELSE 0 END) as updates,
                    SUM(CASE WHEN action='DELETE' THEN 1 ELSE 0 END) as deletes,
                    COUNT(DISTINCT recordID) as recordsAffected,
                    COUNT(DISTINCT gibbonPersonID) as uniqueUsers,
                    MAX(timestampCreated) as lastChange
                FROM {$this->getTableName()}
                WHERE 1=1 {$conditions}
                GROUP BY tableName
                ORDER BY totalChanges DESC";

        return $this->db()->select($sql, $data);
    }

    /**
     * Get summary of changes by user.
     *
     * @param string|null $dateFrom
     * @param string|null $dateTo
     * @return \Gibbon\Database\Result
     */
    public function selectChangesSummaryByUser($dateFrom = null, $dateTo = null)
    {
        $conditions = '';
        $data = [];

        if ($dateFrom !== null) {
            $conditions .= ' AND DATE(a.timestampCreated)>=:dateFrom';
            $data['dateFrom'] = $dateFrom;
        }

        if ($dateTo !== null) {
            $conditions .= ' AND DATE(a.timestampCreated)<=:dateTo';
            $data['dateTo'] = $dateTo;
        }

        $sql = "SELECT
                    a.gibbonPersonID,
                    p.preferredName,
                    p.surname,
                    p.username,
                    COUNT(*) as totalChanges,
                    SUM(CASE WHEN a.action='INSERT' THEN 1 ELSE 0 END) as inserts,
                    SUM(CASE WHEN a.action='UPDATE' THEN 1 ELSE 0 END) as updates,
                    SUM(CASE WHEN a.action='DELETE' THEN 1 ELSE 0 END) as deletes,
                    COUNT(DISTINCT a.tableName) as tablesAffected,
                    MAX(a.timestampCreated) as lastChange
                FROM {$this->getTableName()} a
                LEFT JOIN gibbonPerson p ON a.gibbonPersonID=p.gibbonPersonID
                WHERE 1=1 {$conditions}
                GROUP BY a.gibbonPersonID
                ORDER BY totalChanges DESC";

        return $this->db()->select($sql, $data);
    }

    /**
     * Get the most recently modified records for a specific table.
     *
     * @param string $tableName
     * @param int $limit
     * @return \Gibbon\Database\Result
     */
    public function selectRecentlyModifiedRecords($tableName, $limit = 20)
    {
        $data = ['tableName' => $tableName];
        $sql = "SELECT
                    recordID,
                    MAX(timestampCreated) as lastModified,
                    GROUP_CONCAT(DISTINCT action ORDER BY timestampCreated DESC) as actions,
                    COUNT(*) as changeCount
                FROM {$this->getTableName()}
                WHERE tableName=:tableName
                GROUP BY recordID
                ORDER BY lastModified DESC
                LIMIT {$limit}";

        return $this->db()->select($sql, $data);
    }

    /**
     * Check if a specific record has any audit history.
     *
     * @param string $tableName
     * @param int $recordID
     * @return bool
     */
    public function hasAuditHistory($tableName, $recordID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols(['gibbonStaffAuditLogID'])
            ->where('tableName=:tableName')
            ->bindValue('tableName', $tableName)
            ->where('recordID=:recordID')
            ->bindValue('recordID', $recordID)
            ->limit(1);

        $result = $this->runSelect($query);
        return $result->isNotEmpty();
    }

    /**
     * Get field change history for a specific field on a record.
     *
     * @param string $tableName
     * @param int $recordID
     * @param string $fieldName
     * @return \Gibbon\Database\Result
     */
    public function getFieldChangeHistory($tableName, $recordID, $fieldName)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonStaffAuditLog.gibbonStaffAuditLogID',
                'gibbonStaffAuditLog.oldValue',
                'gibbonStaffAuditLog.newValue',
                'gibbonStaffAuditLog.gibbonPersonID',
                'gibbonStaffAuditLog.timestampCreated',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
            ])
            ->leftJoin('gibbonPerson', 'gibbonStaffAuditLog.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->where('gibbonStaffAuditLog.tableName=:tableName')
            ->bindValue('tableName', $tableName)
            ->where('gibbonStaffAuditLog.recordID=:recordID')
            ->bindValue('recordID', $recordID)
            ->where('gibbonStaffAuditLog.fieldName=:fieldName')
            ->bindValue('fieldName', $fieldName)
            ->orderBy(['gibbonStaffAuditLog.timestampCreated DESC']);

        return $this->runSelect($query);
    }

    /**
     * Get changes made during a specific session.
     *
     * @param string $sessionID
     * @return \Gibbon\Database\Result
     */
    public function selectChangesBySession($sessionID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonStaffAuditLog.gibbonStaffAuditLogID',
                'gibbonStaffAuditLog.tableName',
                'gibbonStaffAuditLog.recordID',
                'gibbonStaffAuditLog.action',
                'gibbonStaffAuditLog.fieldName',
                'gibbonStaffAuditLog.oldValue',
                'gibbonStaffAuditLog.newValue',
                'gibbonStaffAuditLog.timestampCreated',
            ])
            ->where('gibbonStaffAuditLog.sessionID=:sessionID')
            ->bindValue('sessionID', $sessionID)
            ->orderBy(['gibbonStaffAuditLog.timestampCreated ASC']);

        return $this->runSelect($query);
    }

    /**
     * Count total changes for a specific record.
     *
     * @param string $tableName
     * @param int $recordID
     * @return int
     */
    public function countChangesForRecord($tableName, $recordID)
    {
        $data = ['tableName' => $tableName, 'recordID' => $recordID];
        $sql = "SELECT COUNT(*) as count FROM {$this->getTableName()}
                WHERE tableName=:tableName AND recordID=:recordID";

        $result = $this->db()->selectOne($sql, $data);
        return intval($result['count'] ?? 0);
    }
}
