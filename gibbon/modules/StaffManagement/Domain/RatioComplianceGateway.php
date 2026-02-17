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
 * Ratio Compliance Gateway
 *
 * Handles Quebec staff-to-child ratio monitoring with real-time calculations.
 * Quebec ratios: 1:5 (Infant/0-18mo), 1:8 (Toddler/18-36mo), 1:10 (Preschool/36-60mo), 1:20 (School Age/60+mo)
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class RatioComplianceGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonStaffRatioSnapshot';
    private static $primaryKey = 'gibbonStaffRatioSnapshotID';

    private static $searchableColumns = ['gibbonStaffRatioSnapshot.roomName', 'gibbonStaffRatioSnapshot.notes'];

    /**
     * Default Quebec staff-to-child ratios by age group.
     * Values represent the number of children per staff member.
     */
    public const QUEBEC_RATIOS = [
        'Infant' => 5,      // 0-18 months
        'Toddler' => 8,     // 18-36 months
        'Preschool' => 10,  // 36-60 months
        'School Age' => 20, // 60+ months
    ];

    // ========================================
    // RATIO SNAPSHOT QUERY METHODS
    // ========================================

    /**
     * Query ratio snapshots with criteria support.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonSchoolYearID
     * @return DataSet
     */
    public function queryRatioSnapshots(QueryCriteria $criteria, $gibbonSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonStaffRatioSnapshot.gibbonStaffRatioSnapshotID',
                'gibbonStaffRatioSnapshot.gibbonSchoolYearID',
                'gibbonStaffRatioSnapshot.snapshotDate',
                'gibbonStaffRatioSnapshot.snapshotTime',
                'gibbonStaffRatioSnapshot.ageGroup',
                'gibbonStaffRatioSnapshot.roomName',
                'gibbonStaffRatioSnapshot.staffCount',
                'gibbonStaffRatioSnapshot.childCount',
                'gibbonStaffRatioSnapshot.requiredRatio',
                'gibbonStaffRatioSnapshot.actualRatio',
                'gibbonStaffRatioSnapshot.isCompliant',
                'gibbonStaffRatioSnapshot.compliancePercent',
                'gibbonStaffRatioSnapshot.alertSent',
                'gibbonStaffRatioSnapshot.alertSentTime',
                'gibbonStaffRatioSnapshot.notes',
                'gibbonStaffRatioSnapshot.isAutomatic',
                'recordedBy.preferredName as recordedByName',
                'recordedBy.surname as recordedBySurname',
            ])
            ->leftJoin('gibbonPerson as recordedBy', 'gibbonStaffRatioSnapshot.recordedByID=recordedBy.gibbonPersonID')
            ->where('gibbonStaffRatioSnapshot.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID);

        $criteria->addFilterRules([
            'date' => function ($query, $date) {
                return $query
                    ->where('gibbonStaffRatioSnapshot.snapshotDate=:date')
                    ->bindValue('date', $date);
            },
            'dateFrom' => function ($query, $dateFrom) {
                return $query
                    ->where('gibbonStaffRatioSnapshot.snapshotDate>=:dateFrom')
                    ->bindValue('dateFrom', $dateFrom);
            },
            'dateTo' => function ($query, $dateTo) {
                return $query
                    ->where('gibbonStaffRatioSnapshot.snapshotDate<=:dateTo')
                    ->bindValue('dateTo', $dateTo);
            },
            'ageGroup' => function ($query, $ageGroup) {
                return $query
                    ->where('gibbonStaffRatioSnapshot.ageGroup=:ageGroup')
                    ->bindValue('ageGroup', $ageGroup);
            },
            'roomName' => function ($query, $roomName) {
                return $query
                    ->where('gibbonStaffRatioSnapshot.roomName=:roomName')
                    ->bindValue('roomName', $roomName);
            },
            'isCompliant' => function ($query, $isCompliant) {
                return $query
                    ->where('gibbonStaffRatioSnapshot.isCompliant=:isCompliant')
                    ->bindValue('isCompliant', $isCompliant);
            },
            'isAutomatic' => function ($query, $isAutomatic) {
                return $query
                    ->where('gibbonStaffRatioSnapshot.isAutomatic=:isAutomatic')
                    ->bindValue('isAutomatic', $isAutomatic);
            },
            'alertSent' => function ($query, $alertSent) {
                return $query
                    ->where('gibbonStaffRatioSnapshot.alertSent=:alertSent')
                    ->bindValue('alertSent', $alertSent);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query ratio snapshots for a specific date.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonSchoolYearID
     * @param string $date
     * @return DataSet
     */
    public function queryRatioSnapshotsByDate(QueryCriteria $criteria, $gibbonSchoolYearID, $date)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonStaffRatioSnapshot.gibbonStaffRatioSnapshotID',
                'gibbonStaffRatioSnapshot.snapshotDate',
                'gibbonStaffRatioSnapshot.snapshotTime',
                'gibbonStaffRatioSnapshot.ageGroup',
                'gibbonStaffRatioSnapshot.roomName',
                'gibbonStaffRatioSnapshot.staffCount',
                'gibbonStaffRatioSnapshot.childCount',
                'gibbonStaffRatioSnapshot.requiredRatio',
                'gibbonStaffRatioSnapshot.actualRatio',
                'gibbonStaffRatioSnapshot.isCompliant',
                'gibbonStaffRatioSnapshot.compliancePercent',
                'gibbonStaffRatioSnapshot.alertSent',
                'gibbonStaffRatioSnapshot.notes',
            ])
            ->where('gibbonStaffRatioSnapshot.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID)
            ->where('gibbonStaffRatioSnapshot.snapshotDate=:date')
            ->bindValue('date', $date)
            ->orderBy(['gibbonStaffRatioSnapshot.snapshotTime DESC']);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query non-compliant ratio snapshots.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonSchoolYearID
     * @param string|null $dateFrom
     * @param string|null $dateTo
     * @return DataSet
     */
    public function queryNonCompliantSnapshots(QueryCriteria $criteria, $gibbonSchoolYearID, $dateFrom = null, $dateTo = null)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonStaffRatioSnapshot.gibbonStaffRatioSnapshotID',
                'gibbonStaffRatioSnapshot.snapshotDate',
                'gibbonStaffRatioSnapshot.snapshotTime',
                'gibbonStaffRatioSnapshot.ageGroup',
                'gibbonStaffRatioSnapshot.roomName',
                'gibbonStaffRatioSnapshot.staffCount',
                'gibbonStaffRatioSnapshot.childCount',
                'gibbonStaffRatioSnapshot.requiredRatio',
                'gibbonStaffRatioSnapshot.actualRatio',
                'gibbonStaffRatioSnapshot.compliancePercent',
                'gibbonStaffRatioSnapshot.alertSent',
                'gibbonStaffRatioSnapshot.notes',
            ])
            ->where('gibbonStaffRatioSnapshot.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID)
            ->where("gibbonStaffRatioSnapshot.isCompliant='N'")
            ->orderBy(['gibbonStaffRatioSnapshot.snapshotDate DESC', 'gibbonStaffRatioSnapshot.snapshotTime DESC']);

        if ($dateFrom !== null) {
            $query->where('gibbonStaffRatioSnapshot.snapshotDate>=:dateFrom')
                  ->bindValue('dateFrom', $dateFrom);
        }

        if ($dateTo !== null) {
            $query->where('gibbonStaffRatioSnapshot.snapshotDate<=:dateTo')
                  ->bindValue('dateTo', $dateTo);
        }

        return $this->runQuery($query, $criteria);
    }

    // ========================================
    // REAL-TIME RATIO CALCULATION METHODS
    // ========================================

    /**
     * Calculate current staff-to-child ratio for an age group.
     *
     * @param int $gibbonSchoolYearID
     * @param string $ageGroup
     * @param string $date
     * @param string $time
     * @param string|null $roomName
     * @return array
     */
    public function calculateCurrentRatio($gibbonSchoolYearID, $ageGroup, $date, $time, $roomName = null)
    {
        // Get staff count for the age group at this time
        $staffCount = $this->getStaffCountForAgeGroup($gibbonSchoolYearID, $ageGroup, $date, $time, $roomName);

        // Get child count for the age group
        $childCount = $this->getChildCountForAgeGroup($gibbonSchoolYearID, $ageGroup, $date, $time, $roomName);

        // Get required ratio for this age group
        $requiredRatio = self::QUEBEC_RATIOS[$ageGroup] ?? 10;

        // Calculate actual ratio
        $actualRatio = $staffCount > 0 ? round($childCount / $staffCount, 2) : ($childCount > 0 ? PHP_FLOAT_MAX : 0);

        // Calculate compliance
        $isCompliant = $staffCount > 0 && $actualRatio <= $requiredRatio;

        // Calculate compliance percentage (100% means exactly at required ratio)
        $compliancePercent = 0;
        if ($staffCount > 0 && $childCount > 0) {
            $maxCapacity = $staffCount * $requiredRatio;
            $compliancePercent = round(($childCount / $maxCapacity) * 100, 2);
        }

        // Calculate staff needed to be compliant
        $staffNeeded = 0;
        if (!$isCompliant && $childCount > 0) {
            $staffNeeded = ceil($childCount / $requiredRatio) - $staffCount;
        }

        // Calculate additional capacity (how many more children can be added)
        $additionalCapacity = 0;
        if ($isCompliant && $staffCount > 0) {
            $maxCapacity = $staffCount * $requiredRatio;
            $additionalCapacity = max(0, $maxCapacity - $childCount);
        }

        return [
            'ageGroup' => $ageGroup,
            'roomName' => $roomName,
            'staffCount' => $staffCount,
            'childCount' => $childCount,
            'requiredRatio' => $requiredRatio,
            'actualRatio' => $actualRatio,
            'isCompliant' => $isCompliant ? 'Y' : 'N',
            'compliancePercent' => $compliancePercent,
            'staffNeeded' => $staffNeeded,
            'additionalCapacity' => $additionalCapacity,
            'calculatedAt' => $date . ' ' . $time,
        ];
    }

    /**
     * Calculate current ratios for all age groups.
     *
     * @param int $gibbonSchoolYearID
     * @param string $date
     * @param string $time
     * @return array
     */
    public function calculateAllCurrentRatios($gibbonSchoolYearID, $date, $time)
    {
        $ratios = [];
        $ageGroups = array_keys(self::QUEBEC_RATIOS);

        foreach ($ageGroups as $ageGroup) {
            $ratios[$ageGroup] = $this->calculateCurrentRatio($gibbonSchoolYearID, $ageGroup, $date, $time);
        }

        return $ratios;
    }

    /**
     * Calculate current ratios by room.
     *
     * @param int $gibbonSchoolYearID
     * @param string $date
     * @param string $time
     * @return array
     */
    public function calculateRatiosByRoom($gibbonSchoolYearID, $date, $time)
    {
        // Get unique rooms from schedules for this date
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID, 'date' => $date, 'time' => $time];
        $sql = "SELECT DISTINCT roomAssignment, ageGroup
                FROM gibbonStaffSchedule
                WHERE gibbonSchoolYearID=:gibbonSchoolYearID
                AND date=:date
                AND startTime <= :time
                AND endTime >= :time
                AND status NOT IN ('Cancelled')
                AND roomAssignment IS NOT NULL
                ORDER BY roomAssignment";

        $rooms = $this->db()->select($sql, $data)->fetchAll();

        $ratios = [];
        foreach ($rooms as $room) {
            $key = $room['roomAssignment'];
            $ratios[$key] = $this->calculateCurrentRatio(
                $gibbonSchoolYearID,
                $room['ageGroup'],
                $date,
                $time,
                $room['roomAssignment']
            );
        }

        return $ratios;
    }

    /**
     * Get count of staff currently working for an age group.
     *
     * @param int $gibbonSchoolYearID
     * @param string $ageGroup
     * @param string $date
     * @param string $time
     * @param string|null $roomName
     * @return int
     */
    public function getStaffCountForAgeGroup($gibbonSchoolYearID, $ageGroup, $date, $time, $roomName = null)
    {
        $data = [
            'gibbonSchoolYearID' => $gibbonSchoolYearID,
            'date' => $date,
            'time' => $time,
            'ageGroup' => $ageGroup,
        ];

        $sql = "SELECT COUNT(DISTINCT t.gibbonPersonID) as staffCount
                FROM gibbonStaffTimeEntry t
                INNER JOIN gibbonStaffSchedule s ON t.gibbonPersonID=s.gibbonPersonID AND t.date=s.date
                WHERE t.gibbonSchoolYearID=:gibbonSchoolYearID
                AND t.date=:date
                AND t.clockInTime IS NOT NULL
                AND t.clockOutTime IS NULL
                AND t.status='Active'
                AND (t.breakStart IS NULL OR t.breakEnd IS NOT NULL)
                AND s.ageGroup=:ageGroup
                AND s.startTime <= :time
                AND s.endTime >= :time
                AND s.status NOT IN ('Cancelled')";

        if ($roomName !== null) {
            $sql .= " AND s.roomAssignment=:roomName";
            $data['roomName'] = $roomName;
        }

        $result = $this->db()->selectOne($sql, $data);
        return intval($result['staffCount'] ?? 0);
    }

    /**
     * Get count of children currently present for an age group.
     *
     * @param int $gibbonSchoolYearID
     * @param string $ageGroup
     * @param string $date
     * @param string $time
     * @param string|null $roomName
     * @return int
     */
    public function getChildCountForAgeGroup($gibbonSchoolYearID, $ageGroup, $date, $time, $roomName = null)
    {
        // Map age groups to age ranges in months
        $ageRanges = [
            'Infant' => ['min' => 0, 'max' => 18],
            'Toddler' => ['min' => 18, 'max' => 36],
            'Preschool' => ['min' => 36, 'max' => 60],
            'School Age' => ['min' => 60, 'max' => 999],
        ];

        $range = $ageRanges[$ageGroup] ?? ['min' => 0, 'max' => 999];

        $data = [
            'gibbonSchoolYearID' => $gibbonSchoolYearID,
            'date' => $date,
            'minMonths' => $range['min'],
            'maxMonths' => $range['max'],
        ];

        $sql = "SELECT COUNT(DISTINCT a.gibbonPersonID) as childCount
                FROM gibbonCareAttendance a
                INNER JOIN gibbonPerson p ON a.gibbonPersonID=p.gibbonPersonID
                WHERE a.gibbonSchoolYearID=:gibbonSchoolYearID
                AND a.date=:date
                AND a.checkInTime IS NOT NULL
                AND a.checkOutTime IS NULL
                AND TIMESTAMPDIFF(MONTH, p.dob, :date) >= :minMonths
                AND TIMESTAMPDIFF(MONTH, p.dob, :date) < :maxMonths";

        // Note: roomName filtering for children would require a room assignment table for children
        // For now, we rely on age-based grouping

        $result = $this->db()->selectOne($sql, $data);
        return intval($result['childCount'] ?? 0);
    }

    // ========================================
    // SNAPSHOT RECORDING METHODS
    // ========================================

    /**
     * Record a ratio snapshot.
     *
     * @param int $gibbonSchoolYearID
     * @param string $ageGroup
     * @param string $date
     * @param string $time
     * @param string|null $roomName
     * @param int|null $recordedByID
     * @param bool $isAutomatic
     * @param string|null $notes
     * @return int|false
     */
    public function recordSnapshot($gibbonSchoolYearID, $ageGroup, $date, $time, $roomName = null, $recordedByID = null, $isAutomatic = true, $notes = null)
    {
        $ratio = $this->calculateCurrentRatio($gibbonSchoolYearID, $ageGroup, $date, $time, $roomName);

        return $this->insert([
            'gibbonSchoolYearID' => $gibbonSchoolYearID,
            'snapshotDate' => $date,
            'snapshotTime' => $time,
            'ageGroup' => $ageGroup,
            'roomName' => $roomName,
            'staffCount' => $ratio['staffCount'],
            'childCount' => $ratio['childCount'],
            'requiredRatio' => $ratio['requiredRatio'],
            'actualRatio' => $ratio['actualRatio'] < PHP_FLOAT_MAX ? $ratio['actualRatio'] : 999.99,
            'isCompliant' => $ratio['isCompliant'],
            'compliancePercent' => $ratio['compliancePercent'],
            'notes' => $notes,
            'recordedByID' => $recordedByID,
            'isAutomatic' => $isAutomatic ? 'Y' : 'N',
        ]);
    }

    /**
     * Record snapshots for all age groups.
     *
     * @param int $gibbonSchoolYearID
     * @param string $date
     * @param string $time
     * @param int|null $recordedByID
     * @param bool $isAutomatic
     * @return array
     */
    public function recordAllSnapshots($gibbonSchoolYearID, $date, $time, $recordedByID = null, $isAutomatic = true)
    {
        $results = [];
        $ageGroups = array_keys(self::QUEBEC_RATIOS);

        foreach ($ageGroups as $ageGroup) {
            $results[$ageGroup] = $this->recordSnapshot(
                $gibbonSchoolYearID,
                $ageGroup,
                $date,
                $time,
                null,
                $recordedByID,
                $isAutomatic
            );
        }

        return $results;
    }

    /**
     * Record snapshots for all rooms.
     *
     * @param int $gibbonSchoolYearID
     * @param string $date
     * @param string $time
     * @param int|null $recordedByID
     * @param bool $isAutomatic
     * @return array
     */
    public function recordSnapshotsByRoom($gibbonSchoolYearID, $date, $time, $recordedByID = null, $isAutomatic = true)
    {
        $rooms = $this->calculateRatiosByRoom($gibbonSchoolYearID, $date, $time);
        $results = [];

        foreach ($rooms as $roomName => $ratio) {
            $results[$roomName] = $this->recordSnapshot(
                $gibbonSchoolYearID,
                $ratio['ageGroup'],
                $date,
                $time,
                $roomName,
                $recordedByID,
                $isAutomatic
            );
        }

        return $results;
    }

    // ========================================
    // ALERT METHODS
    // ========================================

    /**
     * Mark snapshot as having alert sent.
     *
     * @param int $gibbonStaffRatioSnapshotID
     * @return bool
     */
    public function markAlertSent($gibbonStaffRatioSnapshotID)
    {
        return $this->update($gibbonStaffRatioSnapshotID, [
            'alertSent' => 'Y',
            'alertSentTime' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Get non-compliant snapshots that need alerts.
     *
     * @param int $gibbonSchoolYearID
     * @param string $date
     * @return \Gibbon\Database\Result
     */
    public function selectSnapshotsNeedingAlerts($gibbonSchoolYearID, $date)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonStaffRatioSnapshot.gibbonStaffRatioSnapshotID',
                'gibbonStaffRatioSnapshot.snapshotDate',
                'gibbonStaffRatioSnapshot.snapshotTime',
                'gibbonStaffRatioSnapshot.ageGroup',
                'gibbonStaffRatioSnapshot.roomName',
                'gibbonStaffRatioSnapshot.staffCount',
                'gibbonStaffRatioSnapshot.childCount',
                'gibbonStaffRatioSnapshot.requiredRatio',
                'gibbonStaffRatioSnapshot.actualRatio',
                'gibbonStaffRatioSnapshot.compliancePercent',
            ])
            ->where('gibbonStaffRatioSnapshot.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID)
            ->where('gibbonStaffRatioSnapshot.snapshotDate=:date')
            ->bindValue('date', $date)
            ->where("gibbonStaffRatioSnapshot.isCompliant='N'")
            ->where("gibbonStaffRatioSnapshot.alertSent='N'")
            ->orderBy(['gibbonStaffRatioSnapshot.snapshotTime DESC']);

        return $this->runSelect($query);
    }

    /**
     * Get snapshots approaching non-compliance (at warning threshold).
     *
     * @param int $gibbonSchoolYearID
     * @param string $date
     * @param int $warningThreshold
     * @return \Gibbon\Database\Result
     */
    public function selectSnapshotsAtWarningLevel($gibbonSchoolYearID, $date, $warningThreshold = 90)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonStaffRatioSnapshot.gibbonStaffRatioSnapshotID',
                'gibbonStaffRatioSnapshot.snapshotDate',
                'gibbonStaffRatioSnapshot.snapshotTime',
                'gibbonStaffRatioSnapshot.ageGroup',
                'gibbonStaffRatioSnapshot.roomName',
                'gibbonStaffRatioSnapshot.staffCount',
                'gibbonStaffRatioSnapshot.childCount',
                'gibbonStaffRatioSnapshot.requiredRatio',
                'gibbonStaffRatioSnapshot.actualRatio',
                'gibbonStaffRatioSnapshot.compliancePercent',
            ])
            ->where('gibbonStaffRatioSnapshot.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID)
            ->where('gibbonStaffRatioSnapshot.snapshotDate=:date')
            ->bindValue('date', $date)
            ->where("gibbonStaffRatioSnapshot.isCompliant='Y'")
            ->where('gibbonStaffRatioSnapshot.compliancePercent>=:warningThreshold')
            ->bindValue('warningThreshold', $warningThreshold)
            ->orderBy(['gibbonStaffRatioSnapshot.snapshotTime DESC']);

        return $this->runSelect($query);
    }

    // ========================================
    // STATISTICS AND REPORTING METHODS
    // ========================================

    /**
     * Get daily compliance summary.
     *
     * @param int $gibbonSchoolYearID
     * @param string $date
     * @return array
     */
    public function getDailyComplianceSummary($gibbonSchoolYearID, $date)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID, 'date' => $date];
        $sql = "SELECT
                    COUNT(*) as totalSnapshots,
                    SUM(CASE WHEN isCompliant='Y' THEN 1 ELSE 0 END) as compliantSnapshots,
                    SUM(CASE WHEN isCompliant='N' THEN 1 ELSE 0 END) as nonCompliantSnapshots,
                    SUM(CASE WHEN alertSent='Y' THEN 1 ELSE 0 END) as alertsSent,
                    AVG(compliancePercent) as avgCompliancePercent,
                    MIN(compliancePercent) as minCompliancePercent,
                    MAX(compliancePercent) as maxCompliancePercent,
                    AVG(staffCount) as avgStaffCount,
                    AVG(childCount) as avgChildCount,
                    MIN(snapshotTime) as firstSnapshot,
                    MAX(snapshotTime) as lastSnapshot
                FROM gibbonStaffRatioSnapshot
                WHERE gibbonSchoolYearID=:gibbonSchoolYearID
                AND snapshotDate=:date";

        $result = $this->db()->selectOne($sql, $data) ?: [
            'totalSnapshots' => 0,
            'compliantSnapshots' => 0,
            'nonCompliantSnapshots' => 0,
            'alertsSent' => 0,
            'avgCompliancePercent' => 0,
            'minCompliancePercent' => 0,
            'maxCompliancePercent' => 0,
            'avgStaffCount' => 0,
            'avgChildCount' => 0,
            'firstSnapshot' => null,
            'lastSnapshot' => null,
        ];

        // Calculate compliance rate
        $totalSnapshots = intval($result['totalSnapshots']);
        $result['complianceRate'] = $totalSnapshots > 0
            ? round(($result['compliantSnapshots'] / $totalSnapshots) * 100, 2)
            : 100;

        return $result;
    }

    /**
     * Get compliance summary by age group.
     *
     * @param int $gibbonSchoolYearID
     * @param string $dateFrom
     * @param string $dateTo
     * @return \Gibbon\Database\Result
     */
    public function selectComplianceSummaryByAgeGroup($gibbonSchoolYearID, $dateFrom, $dateTo)
    {
        $data = [
            'gibbonSchoolYearID' => $gibbonSchoolYearID,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
        ];
        $sql = "SELECT
                    ageGroup,
                    COUNT(*) as totalSnapshots,
                    SUM(CASE WHEN isCompliant='Y' THEN 1 ELSE 0 END) as compliantSnapshots,
                    SUM(CASE WHEN isCompliant='N' THEN 1 ELSE 0 END) as nonCompliantSnapshots,
                    ROUND((SUM(CASE WHEN isCompliant='Y' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as complianceRate,
                    AVG(compliancePercent) as avgCompliancePercent,
                    AVG(staffCount) as avgStaffCount,
                    AVG(childCount) as avgChildCount,
                    requiredRatio
                FROM gibbonStaffRatioSnapshot
                WHERE gibbonSchoolYearID=:gibbonSchoolYearID
                AND snapshotDate >= :dateFrom
                AND snapshotDate <= :dateTo
                GROUP BY ageGroup, requiredRatio
                ORDER BY ageGroup";

        return $this->db()->select($sql, $data);
    }

    /**
     * Get compliance trend over time.
     *
     * @param int $gibbonSchoolYearID
     * @param string $dateFrom
     * @param string $dateTo
     * @return \Gibbon\Database\Result
     */
    public function selectComplianceTrend($gibbonSchoolYearID, $dateFrom, $dateTo)
    {
        $data = [
            'gibbonSchoolYearID' => $gibbonSchoolYearID,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
        ];
        $sql = "SELECT
                    snapshotDate,
                    COUNT(*) as totalSnapshots,
                    SUM(CASE WHEN isCompliant='Y' THEN 1 ELSE 0 END) as compliantSnapshots,
                    ROUND((SUM(CASE WHEN isCompliant='Y' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as complianceRate,
                    AVG(compliancePercent) as avgCompliancePercent,
                    SUM(staffCount) as totalStaff,
                    SUM(childCount) as totalChildren
                FROM gibbonStaffRatioSnapshot
                WHERE gibbonSchoolYearID=:gibbonSchoolYearID
                AND snapshotDate >= :dateFrom
                AND snapshotDate <= :dateTo
                GROUP BY snapshotDate
                ORDER BY snapshotDate ASC";

        return $this->db()->select($sql, $data);
    }

    /**
     * Get most recent snapshot for each age group.
     *
     * @param int $gibbonSchoolYearID
     * @param string $date
     * @return array
     */
    public function getLatestSnapshotsByAgeGroup($gibbonSchoolYearID, $date)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID, 'date' => $date];
        $sql = "SELECT s1.*
                FROM gibbonStaffRatioSnapshot s1
                INNER JOIN (
                    SELECT ageGroup, MAX(snapshotTime) as latestTime
                    FROM gibbonStaffRatioSnapshot
                    WHERE gibbonSchoolYearID=:gibbonSchoolYearID
                    AND snapshotDate=:date
                    GROUP BY ageGroup
                ) s2 ON s1.ageGroup=s2.ageGroup AND s1.snapshotTime=s2.latestTime
                WHERE s1.gibbonSchoolYearID=:gibbonSchoolYearID
                AND s1.snapshotDate=:date
                ORDER BY s1.ageGroup";

        return $this->db()->select($sql, $data)->fetchAll();
    }

    /**
     * Get peak non-compliance times.
     *
     * @param int $gibbonSchoolYearID
     * @param string $dateFrom
     * @param string $dateTo
     * @return \Gibbon\Database\Result
     */
    public function selectPeakNonComplianceTimes($gibbonSchoolYearID, $dateFrom, $dateTo)
    {
        $data = [
            'gibbonSchoolYearID' => $gibbonSchoolYearID,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
        ];
        $sql = "SELECT
                    HOUR(snapshotTime) as hour,
                    COUNT(*) as totalSnapshots,
                    SUM(CASE WHEN isCompliant='N' THEN 1 ELSE 0 END) as nonCompliantCount,
                    ROUND((SUM(CASE WHEN isCompliant='N' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as nonComplianceRate
                FROM gibbonStaffRatioSnapshot
                WHERE gibbonSchoolYearID=:gibbonSchoolYearID
                AND snapshotDate >= :dateFrom
                AND snapshotDate <= :dateTo
                GROUP BY HOUR(snapshotTime)
                ORDER BY nonComplianceRate DESC, hour ASC";

        return $this->db()->select($sql, $data);
    }

    /**
     * Get staff needed to achieve compliance across all age groups.
     *
     * @param int $gibbonSchoolYearID
     * @param string $date
     * @param string $time
     * @return array
     */
    public function getStaffNeededForCompliance($gibbonSchoolYearID, $date, $time)
    {
        $ratios = $this->calculateAllCurrentRatios($gibbonSchoolYearID, $date, $time);
        $totalStaffNeeded = 0;
        $details = [];

        foreach ($ratios as $ageGroup => $ratio) {
            $details[$ageGroup] = [
                'currentStaff' => $ratio['staffCount'],
                'currentChildren' => $ratio['childCount'],
                'staffNeeded' => $ratio['staffNeeded'],
                'isCompliant' => $ratio['isCompliant'],
            ];
            $totalStaffNeeded += $ratio['staffNeeded'];
        }

        return [
            'totalStaffNeeded' => $totalStaffNeeded,
            'details' => $details,
            'overallCompliant' => $totalStaffNeeded === 0,
            'calculatedAt' => $date . ' ' . $time,
        ];
    }

    /**
     * Get compliance history for a specific room.
     *
     * @param int $gibbonSchoolYearID
     * @param string $roomName
     * @param string $dateFrom
     * @param string $dateTo
     * @return \Gibbon\Database\Result
     */
    public function selectRoomComplianceHistory($gibbonSchoolYearID, $roomName, $dateFrom, $dateTo)
    {
        $data = [
            'gibbonSchoolYearID' => $gibbonSchoolYearID,
            'roomName' => $roomName,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
        ];
        $sql = "SELECT
                    snapshotDate,
                    snapshotTime,
                    ageGroup,
                    staffCount,
                    childCount,
                    requiredRatio,
                    actualRatio,
                    isCompliant,
                    compliancePercent,
                    alertSent
                FROM gibbonStaffRatioSnapshot
                WHERE gibbonSchoolYearID=:gibbonSchoolYearID
                AND roomName=:roomName
                AND snapshotDate >= :dateFrom
                AND snapshotDate <= :dateTo
                ORDER BY snapshotDate DESC, snapshotTime DESC";

        return $this->db()->select($sql, $data);
    }

    /**
     * Get unique room names from snapshots.
     *
     * @param int $gibbonSchoolYearID
     * @return \Gibbon\Database\Result
     */
    public function selectUniqueRooms($gibbonSchoolYearID)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID];
        $sql = "SELECT DISTINCT roomName
                FROM gibbonStaffRatioSnapshot
                WHERE gibbonSchoolYearID=:gibbonSchoolYearID
                AND roomName IS NOT NULL
                ORDER BY roomName";

        return $this->db()->select($sql, $data);
    }

    /**
     * Delete old snapshots for data retention.
     *
     * @param int $daysToKeep
     * @return int Number of records deleted
     */
    public function deleteOldSnapshots($daysToKeep = 365)
    {
        $cutoffDate = date('Y-m-d', strtotime("-{$daysToKeep} days"));

        $data = ['cutoffDate' => $cutoffDate];
        $sql = "DELETE FROM gibbonStaffRatioSnapshot WHERE snapshotDate < :cutoffDate";

        return $this->db()->statement($sql, $data);
    }

    /**
     * Check if a snapshot already exists for given parameters.
     *
     * @param int $gibbonSchoolYearID
     * @param string $ageGroup
     * @param string $date
     * @param string $time
     * @param string|null $roomName
     * @return bool
     */
    public function snapshotExists($gibbonSchoolYearID, $ageGroup, $date, $time, $roomName = null)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols(['gibbonStaffRatioSnapshotID'])
            ->where('gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID)
            ->where('ageGroup=:ageGroup')
            ->bindValue('ageGroup', $ageGroup)
            ->where('snapshotDate=:date')
            ->bindValue('date', $date)
            ->where('snapshotTime=:time')
            ->bindValue('time', $time);

        if ($roomName !== null) {
            $query->where('roomName=:roomName')
                  ->bindValue('roomName', $roomName);
        } else {
            $query->where('roomName IS NULL');
        }

        $result = $this->runSelect($query);
        return $result->isNotEmpty();
    }
}
