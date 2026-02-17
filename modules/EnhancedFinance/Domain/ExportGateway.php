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

namespace Gibbon\Module\EnhancedFinance\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\QueryableGateway;

/**
 * Enhanced Finance Export Gateway
 *
 * Provides data access methods for the gibbonEnhancedFinanceExportLog table.
 * Handles export log CRUD operations, queries with pagination, filtering,
 * and audit trail functionality for accounting software exports.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class ExportGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonEnhancedFinanceExportLog';
    private static $primaryKey = 'gibbonEnhancedFinanceExportLogID';

    private static $searchableColumns = ['fileName', 'exportType', 'exportFormat'];

    /**
     * Query export logs by school year with pagination and filtering.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonSchoolYearID
     * @return DataSet
     */
    public function queryExportsByYear(QueryCriteria $criteria, $gibbonSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonEnhancedFinanceExportLog.gibbonEnhancedFinanceExportLogID',
                'gibbonEnhancedFinanceExportLog.exportType',
                'gibbonEnhancedFinanceExportLog.exportFormat',
                'gibbonEnhancedFinanceExportLog.gibbonSchoolYearID',
                'gibbonEnhancedFinanceExportLog.dateRangeStart',
                'gibbonEnhancedFinanceExportLog.dateRangeEnd',
                'gibbonEnhancedFinanceExportLog.recordCount',
                'gibbonEnhancedFinanceExportLog.totalAmount',
                'gibbonEnhancedFinanceExportLog.fileName',
                'gibbonEnhancedFinanceExportLog.filePath',
                'gibbonEnhancedFinanceExportLog.fileSize',
                'gibbonEnhancedFinanceExportLog.status',
                'gibbonEnhancedFinanceExportLog.errorMessage',
                'gibbonEnhancedFinanceExportLog.exportedByID',
                'gibbonEnhancedFinanceExportLog.timestampCreated',
                'gibbonPerson.surname AS exportedBySurname',
                'gibbonPerson.preferredName AS exportedByPreferredName',
                'gibbonSchoolYear.name AS schoolYearName'
            ])
            ->leftJoin('gibbonPerson', 'gibbonEnhancedFinanceExportLog.exportedByID = gibbonPerson.gibbonPersonID')
            ->leftJoin('gibbonSchoolYear', 'gibbonEnhancedFinanceExportLog.gibbonSchoolYearID = gibbonSchoolYear.gibbonSchoolYearID')
            ->where('gibbonEnhancedFinanceExportLog.gibbonSchoolYearID = :gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID);

        $criteria->addFilterRules($this->getFilterRules());

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query all export logs with pagination and filtering.
     *
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryAllExports(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonEnhancedFinanceExportLog.gibbonEnhancedFinanceExportLogID',
                'gibbonEnhancedFinanceExportLog.exportType',
                'gibbonEnhancedFinanceExportLog.exportFormat',
                'gibbonEnhancedFinanceExportLog.gibbonSchoolYearID',
                'gibbonEnhancedFinanceExportLog.dateRangeStart',
                'gibbonEnhancedFinanceExportLog.dateRangeEnd',
                'gibbonEnhancedFinanceExportLog.recordCount',
                'gibbonEnhancedFinanceExportLog.totalAmount',
                'gibbonEnhancedFinanceExportLog.fileName',
                'gibbonEnhancedFinanceExportLog.filePath',
                'gibbonEnhancedFinanceExportLog.fileSize',
                'gibbonEnhancedFinanceExportLog.status',
                'gibbonEnhancedFinanceExportLog.errorMessage',
                'gibbonEnhancedFinanceExportLog.exportedByID',
                'gibbonEnhancedFinanceExportLog.timestampCreated',
                'gibbonPerson.surname AS exportedBySurname',
                'gibbonPerson.preferredName AS exportedByPreferredName',
                'gibbonSchoolYear.name AS schoolYearName'
            ])
            ->leftJoin('gibbonPerson', 'gibbonEnhancedFinanceExportLog.exportedByID = gibbonPerson.gibbonPersonID')
            ->leftJoin('gibbonSchoolYear', 'gibbonEnhancedFinanceExportLog.gibbonSchoolYearID = gibbonSchoolYear.gibbonSchoolYearID');

        $criteria->addFilterRules($this->getFilterRules());

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query exports by user (for showing user's export history).
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonPersonID
     * @return DataSet
     */
    public function queryExportsByUser(QueryCriteria $criteria, $gibbonPersonID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonEnhancedFinanceExportLog.gibbonEnhancedFinanceExportLogID',
                'gibbonEnhancedFinanceExportLog.exportType',
                'gibbonEnhancedFinanceExportLog.exportFormat',
                'gibbonEnhancedFinanceExportLog.gibbonSchoolYearID',
                'gibbonEnhancedFinanceExportLog.dateRangeStart',
                'gibbonEnhancedFinanceExportLog.dateRangeEnd',
                'gibbonEnhancedFinanceExportLog.recordCount',
                'gibbonEnhancedFinanceExportLog.totalAmount',
                'gibbonEnhancedFinanceExportLog.fileName',
                'gibbonEnhancedFinanceExportLog.filePath',
                'gibbonEnhancedFinanceExportLog.fileSize',
                'gibbonEnhancedFinanceExportLog.status',
                'gibbonEnhancedFinanceExportLog.timestampCreated',
                'gibbonSchoolYear.name AS schoolYearName'
            ])
            ->leftJoin('gibbonSchoolYear', 'gibbonEnhancedFinanceExportLog.gibbonSchoolYearID = gibbonSchoolYear.gibbonSchoolYearID')
            ->where('gibbonEnhancedFinanceExportLog.exportedByID = :gibbonPersonID')
            ->bindValue('gibbonPersonID', $gibbonPersonID);

        $criteria->addFilterRules($this->getFilterRules());

        return $this->runQuery($query, $criteria);
    }

    /**
     * Select export by ID with full details including exporter information.
     *
     * @param int $gibbonEnhancedFinanceExportLogID
     * @return array
     */
    public function selectExportByID($gibbonEnhancedFinanceExportLogID)
    {
        $data = ['gibbonEnhancedFinanceExportLogID' => $gibbonEnhancedFinanceExportLogID];
        $sql = "SELECT
                gibbonEnhancedFinanceExportLog.*,
                gibbonPerson.surname AS exportedBySurname,
                gibbonPerson.preferredName AS exportedByPreferredName,
                gibbonSchoolYear.name AS schoolYearName
            FROM gibbonEnhancedFinanceExportLog
            LEFT JOIN gibbonPerson ON gibbonEnhancedFinanceExportLog.exportedByID = gibbonPerson.gibbonPersonID
            LEFT JOIN gibbonSchoolYear ON gibbonEnhancedFinanceExportLog.gibbonSchoolYearID = gibbonSchoolYear.gibbonSchoolYearID
            WHERE gibbonEnhancedFinanceExportLog.gibbonEnhancedFinanceExportLogID = :gibbonEnhancedFinanceExportLogID";

        return $this->db()->selectOne($sql, $data);
    }

    /**
     * Select recent exports (last N exports by a user or all users).
     *
     * @param int|null $gibbonPersonID Optional user filter
     * @param int $limit Number of recent exports to return
     * @return Result
     */
    public function selectRecentExports($gibbonPersonID = null, $limit = 10)
    {
        $data = ['limit' => $limit];
        $sql = "SELECT
                gibbonEnhancedFinanceExportLog.*,
                gibbonPerson.surname AS exportedBySurname,
                gibbonPerson.preferredName AS exportedByPreferredName,
                gibbonSchoolYear.name AS schoolYearName
            FROM gibbonEnhancedFinanceExportLog
            LEFT JOIN gibbonPerson ON gibbonEnhancedFinanceExportLog.exportedByID = gibbonPerson.gibbonPersonID
            LEFT JOIN gibbonSchoolYear ON gibbonEnhancedFinanceExportLog.gibbonSchoolYearID = gibbonSchoolYear.gibbonSchoolYearID";

        if ($gibbonPersonID !== null) {
            $sql .= " WHERE gibbonEnhancedFinanceExportLog.exportedByID = :gibbonPersonID";
            $data['gibbonPersonID'] = $gibbonPersonID;
        }

        $sql .= " ORDER BY gibbonEnhancedFinanceExportLog.timestampCreated DESC
                  LIMIT :limit";

        return $this->db()->select($sql, $data);
    }

    /**
     * Select exports by type for a specific school year.
     *
     * @param string $exportType Export type (Sage50, QuickBooks, etc.)
     * @param int|null $gibbonSchoolYearID Optional school year filter
     * @return Result
     */
    public function selectExportsByType($exportType, $gibbonSchoolYearID = null)
    {
        $data = ['exportType' => $exportType];
        $sql = "SELECT
                gibbonEnhancedFinanceExportLog.*,
                gibbonPerson.surname AS exportedBySurname,
                gibbonPerson.preferredName AS exportedByPreferredName
            FROM gibbonEnhancedFinanceExportLog
            LEFT JOIN gibbonPerson ON gibbonEnhancedFinanceExportLog.exportedByID = gibbonPerson.gibbonPersonID
            WHERE gibbonEnhancedFinanceExportLog.exportType = :exportType";

        if ($gibbonSchoolYearID !== null) {
            $sql .= " AND gibbonEnhancedFinanceExportLog.gibbonSchoolYearID = :gibbonSchoolYearID";
            $data['gibbonSchoolYearID'] = $gibbonSchoolYearID;
        }

        $sql .= " ORDER BY gibbonEnhancedFinanceExportLog.timestampCreated DESC";

        return $this->db()->select($sql, $data);
    }

    /**
     * Select completed exports within a date range (for audit purposes).
     *
     * @param string $dateFrom Start date (YYYY-MM-DD)
     * @param string $dateTo End date (YYYY-MM-DD)
     * @return Result
     */
    public function selectExportsByDateRange($dateFrom, $dateTo)
    {
        $data = [
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo
        ];
        $sql = "SELECT
                gibbonEnhancedFinanceExportLog.*,
                gibbonPerson.surname AS exportedBySurname,
                gibbonPerson.preferredName AS exportedByPreferredName,
                gibbonSchoolYear.name AS schoolYearName
            FROM gibbonEnhancedFinanceExportLog
            LEFT JOIN gibbonPerson ON gibbonEnhancedFinanceExportLog.exportedByID = gibbonPerson.gibbonPersonID
            LEFT JOIN gibbonSchoolYear ON gibbonEnhancedFinanceExportLog.gibbonSchoolYearID = gibbonSchoolYear.gibbonSchoolYearID
            WHERE DATE(gibbonEnhancedFinanceExportLog.timestampCreated) BETWEEN :dateFrom AND :dateTo
            ORDER BY gibbonEnhancedFinanceExportLog.timestampCreated DESC";

        return $this->db()->select($sql, $data);
    }

    /**
     * Create a new export log entry (initial pending state).
     *
     * @param array $data Export data
     * @return int|bool New export ID or false on failure
     */
    public function insertExport(array $data)
    {
        $defaults = [
            'status' => 'Pending',
            'recordCount' => 0
        ];

        return $this->insert(array_merge($defaults, $data));
    }

    /**
     * Update export status to Processing.
     *
     * @param int $gibbonEnhancedFinanceExportLogID
     * @return bool
     */
    public function markExportProcessing($gibbonEnhancedFinanceExportLogID)
    {
        return $this->update($gibbonEnhancedFinanceExportLogID, [
            'status' => 'Processing'
        ]);
    }

    /**
     * Mark export as completed with file details.
     *
     * @param int $gibbonEnhancedFinanceExportLogID
     * @param string $filePath Path to the generated file
     * @param int $fileSize File size in bytes
     * @param string $checksum SHA256 checksum
     * @param int $recordCount Number of records exported
     * @param float|null $totalAmount Total monetary value
     * @return bool
     */
    public function markExportCompleted($gibbonEnhancedFinanceExportLogID, $filePath, $fileSize, $checksum, $recordCount, $totalAmount = null)
    {
        $data = [
            'status' => 'Completed',
            'filePath' => $filePath,
            'fileSize' => $fileSize,
            'checksum' => $checksum,
            'recordCount' => $recordCount
        ];

        if ($totalAmount !== null) {
            $data['totalAmount'] = $totalAmount;
        }

        return $this->update($gibbonEnhancedFinanceExportLogID, $data);
    }

    /**
     * Mark export as failed with error message.
     *
     * @param int $gibbonEnhancedFinanceExportLogID
     * @param string $errorMessage Error description
     * @return bool
     */
    public function markExportFailed($gibbonEnhancedFinanceExportLogID, $errorMessage)
    {
        return $this->update($gibbonEnhancedFinanceExportLogID, [
            'status' => 'Failed',
            'errorMessage' => $errorMessage
        ]);
    }

    /**
     * Get export statistics summary.
     *
     * @param int|null $gibbonSchoolYearID Optional school year filter
     * @return array
     */
    public function selectExportStatistics($gibbonSchoolYearID = null)
    {
        $data = [];
        $sql = "SELECT
                COUNT(*) AS totalExports,
                SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) AS completedExports,
                SUM(CASE WHEN status = 'Failed' THEN 1 ELSE 0 END) AS failedExports,
                SUM(CASE WHEN status = 'Processing' THEN 1 ELSE 0 END) AS processingExports,
                SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) AS pendingExports,
                SUM(CASE WHEN status = 'Completed' THEN recordCount ELSE 0 END) AS totalRecordsExported,
                SUM(CASE WHEN status = 'Completed' THEN totalAmount ELSE 0 END) AS totalAmountExported,
                SUM(CASE WHEN status = 'Completed' THEN fileSize ELSE 0 END) AS totalFileSizeBytes
            FROM gibbonEnhancedFinanceExportLog";

        if ($gibbonSchoolYearID !== null) {
            $sql .= " WHERE gibbonSchoolYearID = :gibbonSchoolYearID";
            $data['gibbonSchoolYearID'] = $gibbonSchoolYearID;
        }

        return $this->db()->selectOne($sql, $data);
    }

    /**
     * Get export statistics by type.
     *
     * @param int|null $gibbonSchoolYearID Optional school year filter
     * @return Result
     */
    public function selectExportStatisticsByType($gibbonSchoolYearID = null)
    {
        $data = [];
        $sql = "SELECT
                exportType,
                COUNT(*) AS totalExports,
                SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) AS completedExports,
                SUM(CASE WHEN status = 'Failed' THEN 1 ELSE 0 END) AS failedExports,
                SUM(CASE WHEN status = 'Completed' THEN recordCount ELSE 0 END) AS totalRecordsExported,
                SUM(CASE WHEN status = 'Completed' THEN totalAmount ELSE 0 END) AS totalAmountExported,
                MAX(timestampCreated) AS lastExportDate
            FROM gibbonEnhancedFinanceExportLog";

        if ($gibbonSchoolYearID !== null) {
            $sql .= " WHERE gibbonSchoolYearID = :gibbonSchoolYearID";
            $data['gibbonSchoolYearID'] = $gibbonSchoolYearID;
        }

        $sql .= " GROUP BY exportType
                  ORDER BY totalExports DESC";

        return $this->db()->select($sql, $data);
    }

    /**
     * Get export statistics by month (for trend analysis).
     *
     * @param int|null $gibbonSchoolYearID Optional school year filter
     * @return Result
     */
    public function selectExportStatisticsByMonth($gibbonSchoolYearID = null)
    {
        $data = [];
        $sql = "SELECT
                DATE_FORMAT(timestampCreated, '%Y-%m') AS exportMonth,
                COUNT(*) AS totalExports,
                SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) AS completedExports,
                SUM(CASE WHEN status = 'Completed' THEN recordCount ELSE 0 END) AS totalRecordsExported,
                SUM(CASE WHEN status = 'Completed' THEN totalAmount ELSE 0 END) AS totalAmountExported
            FROM gibbonEnhancedFinanceExportLog";

        if ($gibbonSchoolYearID !== null) {
            $sql .= " WHERE gibbonSchoolYearID = :gibbonSchoolYearID";
            $data['gibbonSchoolYearID'] = $gibbonSchoolYearID;
        }

        $sql .= " GROUP BY DATE_FORMAT(timestampCreated, '%Y-%m')
                  ORDER BY exportMonth DESC
                  LIMIT 12";

        return $this->db()->select($sql, $data);
    }

    /**
     * Select exports older than retention period for cleanup.
     *
     * @param int $retentionDays Number of days to retain exports
     * @return Result
     */
    public function selectExportsForCleanup($retentionDays)
    {
        $data = ['retentionDays' => $retentionDays];
        $sql = "SELECT
                gibbonEnhancedFinanceExportLogID,
                fileName,
                filePath,
                fileSize,
                status,
                timestampCreated
            FROM gibbonEnhancedFinanceExportLog
            WHERE timestampCreated < DATE_SUB(NOW(), INTERVAL :retentionDays DAY)
            ORDER BY timestampCreated ASC";

        return $this->db()->select($sql, $data);
    }

    /**
     * Delete export log entries by IDs (for cleanup).
     *
     * @param array $exportIDs Array of export log IDs to delete
     * @return int Number of deleted records
     */
    public function deleteExports(array $exportIDs)
    {
        if (empty($exportIDs)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($exportIDs), '?'));
        $sql = "DELETE FROM gibbonEnhancedFinanceExportLog
                WHERE gibbonEnhancedFinanceExportLogID IN ($placeholders)";

        return $this->db()->delete($sql, $exportIDs);
    }

    /**
     * Check if a file path exists in completed exports (avoid duplicate downloads).
     *
     * @param string $filePath
     * @return bool
     */
    public function filePathExists($filePath)
    {
        $data = ['filePath' => $filePath];
        $sql = "SELECT COUNT(*) AS count
                FROM gibbonEnhancedFinanceExportLog
                WHERE filePath = :filePath
                AND status = 'Completed'";

        $result = $this->db()->selectOne($sql, $data);

        return ($result['count'] ?? 0) > 0;
    }

    /**
     * Verify file integrity using checksum.
     *
     * @param int $gibbonEnhancedFinanceExportLogID
     * @param string $checksum SHA256 checksum to verify
     * @return bool
     */
    public function verifyChecksum($gibbonEnhancedFinanceExportLogID, $checksum)
    {
        $data = [
            'gibbonEnhancedFinanceExportLogID' => $gibbonEnhancedFinanceExportLogID,
            'checksum' => $checksum
        ];
        $sql = "SELECT COUNT(*) AS count
                FROM gibbonEnhancedFinanceExportLog
                WHERE gibbonEnhancedFinanceExportLogID = :gibbonEnhancedFinanceExportLogID
                AND checksum = :checksum
                AND status = 'Completed'";

        $result = $this->db()->selectOne($sql, $data);

        return ($result['count'] ?? 0) > 0;
    }

    /**
     * Get the last successful export of a specific type.
     *
     * @param string $exportType
     * @param int|null $gibbonSchoolYearID
     * @return array|null
     */
    public function selectLastSuccessfulExport($exportType, $gibbonSchoolYearID = null)
    {
        $data = ['exportType' => $exportType];
        $sql = "SELECT
                gibbonEnhancedFinanceExportLog.*,
                gibbonPerson.surname AS exportedBySurname,
                gibbonPerson.preferredName AS exportedByPreferredName
            FROM gibbonEnhancedFinanceExportLog
            LEFT JOIN gibbonPerson ON gibbonEnhancedFinanceExportLog.exportedByID = gibbonPerson.gibbonPersonID
            WHERE gibbonEnhancedFinanceExportLog.exportType = :exportType
            AND gibbonEnhancedFinanceExportLog.status = 'Completed'";

        if ($gibbonSchoolYearID !== null) {
            $sql .= " AND gibbonEnhancedFinanceExportLog.gibbonSchoolYearID = :gibbonSchoolYearID";
            $data['gibbonSchoolYearID'] = $gibbonSchoolYearID;
        }

        $sql .= " ORDER BY gibbonEnhancedFinanceExportLog.timestampCreated DESC
                  LIMIT 1";

        return $this->db()->selectOne($sql, $data);
    }

    /**
     * Get filter rules for export queries.
     *
     * @return array
     */
    protected function getFilterRules()
    {
        return [
            'status' => function ($query, $status) {
                return $query
                    ->where('gibbonEnhancedFinanceExportLog.status = :status')
                    ->bindValue('status', $status);
            },

            'exportType' => function ($query, $exportType) {
                return $query
                    ->where('gibbonEnhancedFinanceExportLog.exportType = :exportType')
                    ->bindValue('exportType', $exportType);
            },

            'exportFormat' => function ($query, $exportFormat) {
                return $query
                    ->where('gibbonEnhancedFinanceExportLog.exportFormat = :exportFormat')
                    ->bindValue('exportFormat', $exportFormat);
            },

            'exportedBy' => function ($query, $gibbonPersonID) {
                return $query
                    ->where('gibbonEnhancedFinanceExportLog.exportedByID = :filterExportedBy')
                    ->bindValue('filterExportedBy', $gibbonPersonID);
            },

            'dateFrom' => function ($query, $dateFrom) {
                return $query
                    ->where('DATE(gibbonEnhancedFinanceExportLog.timestampCreated) >= :dateFrom')
                    ->bindValue('dateFrom', $dateFrom);
            },

            'dateTo' => function ($query, $dateTo) {
                return $query
                    ->where('DATE(gibbonEnhancedFinanceExportLog.timestampCreated) <= :dateTo')
                    ->bindValue('dateTo', $dateTo);
            },

            'dateRangeStart' => function ($query, $dateRangeStart) {
                return $query
                    ->where('gibbonEnhancedFinanceExportLog.dateRangeStart >= :filterDateRangeStart')
                    ->bindValue('filterDateRangeStart', $dateRangeStart);
            },

            'dateRangeEnd' => function ($query, $dateRangeEnd) {
                return $query
                    ->where('gibbonEnhancedFinanceExportLog.dateRangeEnd <= :filterDateRangeEnd')
                    ->bindValue('filterDateRangeEnd', $dateRangeEnd);
            },

            'completed' => function ($query, $completed) {
                if ($completed === 'Y') {
                    return $query->where("gibbonEnhancedFinanceExportLog.status = 'Completed'");
                } else {
                    return $query->where("gibbonEnhancedFinanceExportLog.status != 'Completed'");
                }
            },

            'failed' => function ($query, $failed) {
                if ($failed === 'Y') {
                    return $query->where("gibbonEnhancedFinanceExportLog.status = 'Failed'");
                }
                return $query;
            },
        ];
    }
}
