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
 * Staff Time Tracking Gateway
 *
 * Handles clock-in/out, hours calculation, breaks, and overtime tracking for staff.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class TimeTrackingGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonStaffTimeEntry';
    private static $primaryKey = 'gibbonStaffTimeEntryID';

    private static $searchableColumns = ['gibbonPerson.preferredName', 'gibbonPerson.surname', 'gibbonStaffTimeEntry.notes'];

    /**
     * Query time entries with criteria support.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonSchoolYearID
     * @return DataSet
     */
    public function queryTimeEntries(QueryCriteria $criteria, $gibbonSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonStaffTimeEntry.gibbonStaffTimeEntryID',
                'gibbonStaffTimeEntry.gibbonPersonID',
                'gibbonStaffTimeEntry.gibbonSchoolYearID',
                'gibbonStaffTimeEntry.gibbonStaffScheduleID',
                'gibbonStaffTimeEntry.date',
                'gibbonStaffTimeEntry.clockInTime',
                'gibbonStaffTimeEntry.clockOutTime',
                'gibbonStaffTimeEntry.breakStart',
                'gibbonStaffTimeEntry.breakEnd',
                'gibbonStaffTimeEntry.totalBreakMinutes',
                'gibbonStaffTimeEntry.totalWorkedMinutes',
                'gibbonStaffTimeEntry.overtime',
                'gibbonStaffTimeEntry.overtimeMinutes',
                'gibbonStaffTimeEntry.overtimeApproved',
                'gibbonStaffTimeEntry.clockInMethod',
                'gibbonStaffTimeEntry.clockOutMethod',
                'gibbonStaffTimeEntry.clockInLocation',
                'gibbonStaffTimeEntry.clockOutLocation',
                'gibbonStaffTimeEntry.status',
                'gibbonStaffTimeEntry.notes',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
                'approvedBy.preferredName as approvedByName',
                'approvedBy.surname as approvedBySurname',
                'adjustedBy.preferredName as adjustedByName',
                'adjustedBy.surname as adjustedBySurname',
            ])
            ->innerJoin('gibbonPerson', 'gibbonStaffTimeEntry.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->leftJoin('gibbonPerson as approvedBy', 'gibbonStaffTimeEntry.overtimeApprovedByID=approvedBy.gibbonPersonID')
            ->leftJoin('gibbonPerson as adjustedBy', 'gibbonStaffTimeEntry.adjustedByID=adjustedBy.gibbonPersonID')
            ->where('gibbonStaffTimeEntry.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID);

        $criteria->addFilterRules([
            'date' => function ($query, $date) {
                return $query
                    ->where('gibbonStaffTimeEntry.date=:date')
                    ->bindValue('date', $date);
            },
            'dateFrom' => function ($query, $dateFrom) {
                return $query
                    ->where('gibbonStaffTimeEntry.date>=:dateFrom')
                    ->bindValue('dateFrom', $dateFrom);
            },
            'dateTo' => function ($query, $dateTo) {
                return $query
                    ->where('gibbonStaffTimeEntry.date<=:dateTo')
                    ->bindValue('dateTo', $dateTo);
            },
            'staff' => function ($query, $gibbonPersonID) {
                return $query
                    ->where('gibbonStaffTimeEntry.gibbonPersonID=:gibbonPersonID')
                    ->bindValue('gibbonPersonID', $gibbonPersonID);
            },
            'status' => function ($query, $status) {
                return $query
                    ->where('gibbonStaffTimeEntry.status=:status')
                    ->bindValue('status', $status);
            },
            'clockedIn' => function ($query, $value) {
                if ($value == 'Y') {
                    return $query->where('gibbonStaffTimeEntry.clockInTime IS NOT NULL');
                } elseif ($value == 'N') {
                    return $query->where('gibbonStaffTimeEntry.clockInTime IS NULL');
                }
                return $query;
            },
            'clockedOut' => function ($query, $value) {
                if ($value == 'Y') {
                    return $query->where('gibbonStaffTimeEntry.clockOutTime IS NOT NULL');
                } elseif ($value == 'N') {
                    return $query->where('gibbonStaffTimeEntry.clockOutTime IS NULL');
                }
                return $query;
            },
            'overtime' => function ($query, $value) {
                return $query
                    ->where('gibbonStaffTimeEntry.overtime=:overtime')
                    ->bindValue('overtime', $value);
            },
            'overtimeApproved' => function ($query, $value) {
                return $query
                    ->where('gibbonStaffTimeEntry.overtimeApproved=:overtimeApproved')
                    ->bindValue('overtimeApproved', $value);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query time entries for a specific date.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonSchoolYearID
     * @param string $date
     * @return DataSet
     */
    public function queryTimeEntriesByDate(QueryCriteria $criteria, $gibbonSchoolYearID, $date)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonStaffTimeEntry.gibbonStaffTimeEntryID',
                'gibbonStaffTimeEntry.gibbonPersonID',
                'gibbonStaffTimeEntry.date',
                'gibbonStaffTimeEntry.clockInTime',
                'gibbonStaffTimeEntry.clockOutTime',
                'gibbonStaffTimeEntry.totalBreakMinutes',
                'gibbonStaffTimeEntry.totalWorkedMinutes',
                'gibbonStaffTimeEntry.overtime',
                'gibbonStaffTimeEntry.overtimeMinutes',
                'gibbonStaffTimeEntry.overtimeApproved',
                'gibbonStaffTimeEntry.clockInMethod',
                'gibbonStaffTimeEntry.status',
                'gibbonStaffTimeEntry.notes',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
            ])
            ->innerJoin('gibbonPerson', 'gibbonStaffTimeEntry.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->where('gibbonStaffTimeEntry.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID)
            ->where('gibbonStaffTimeEntry.date=:date')
            ->bindValue('date', $date);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query time entry history for a specific staff member.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonPersonID
     * @param int $gibbonSchoolYearID
     * @return DataSet
     */
    public function queryTimeEntriesByPerson(QueryCriteria $criteria, $gibbonPersonID, $gibbonSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonStaffTimeEntry.gibbonStaffTimeEntryID',
                'gibbonStaffTimeEntry.date',
                'gibbonStaffTimeEntry.clockInTime',
                'gibbonStaffTimeEntry.clockOutTime',
                'gibbonStaffTimeEntry.breakStart',
                'gibbonStaffTimeEntry.breakEnd',
                'gibbonStaffTimeEntry.totalBreakMinutes',
                'gibbonStaffTimeEntry.totalWorkedMinutes',
                'gibbonStaffTimeEntry.overtime',
                'gibbonStaffTimeEntry.overtimeMinutes',
                'gibbonStaffTimeEntry.overtimeApproved',
                'gibbonStaffTimeEntry.clockInMethod',
                'gibbonStaffTimeEntry.clockOutMethod',
                'gibbonStaffTimeEntry.status',
                'gibbonStaffTimeEntry.notes',
                'approvedBy.preferredName as approvedByName',
                'approvedBy.surname as approvedBySurname',
            ])
            ->leftJoin('gibbonPerson as approvedBy', 'gibbonStaffTimeEntry.overtimeApprovedByID=approvedBy.gibbonPersonID')
            ->where('gibbonStaffTimeEntry.gibbonPersonID=:gibbonPersonID')
            ->bindValue('gibbonPersonID', $gibbonPersonID)
            ->where('gibbonStaffTimeEntry.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Get time entry for a specific staff member on a specific date.
     *
     * @param int $gibbonPersonID
     * @param string $date
     * @return array
     */
    public function getTimeEntryByPersonAndDate($gibbonPersonID, $date)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols(['*'])
            ->where('gibbonPersonID=:gibbonPersonID')
            ->bindValue('gibbonPersonID', $gibbonPersonID)
            ->where('date=:date')
            ->bindValue('date', $date);

        $result = $this->runSelect($query);
        return $result->isNotEmpty() ? $result->fetch() : [];
    }

    /**
     * Get active (clocked in but not out) time entry for a staff member.
     *
     * @param int $gibbonPersonID
     * @return array
     */
    public function getActiveTimeEntry($gibbonPersonID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols(['*'])
            ->where('gibbonPersonID=:gibbonPersonID')
            ->bindValue('gibbonPersonID', $gibbonPersonID)
            ->where('clockInTime IS NOT NULL')
            ->where('clockOutTime IS NULL')
            ->where("status='Active'")
            ->orderBy(['clockInTime DESC'])
            ->limit(1);

        $result = $this->runSelect($query);
        return $result->isNotEmpty() ? $result->fetch() : [];
    }

    /**
     * Select staff members currently clocked in.
     *
     * @param int $gibbonSchoolYearID
     * @param string $date
     * @return \Gibbon\Database\Result
     */
    public function selectStaffCurrentlyClockedIn($gibbonSchoolYearID, $date)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonStaffTimeEntry.gibbonStaffTimeEntryID',
                'gibbonStaffTimeEntry.gibbonPersonID',
                'gibbonStaffTimeEntry.clockInTime',
                'gibbonStaffTimeEntry.breakStart',
                'gibbonStaffTimeEntry.breakEnd',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
                'gibbonStaffProfile.position',
                'gibbonStaffProfile.department',
            ])
            ->innerJoin('gibbonPerson', 'gibbonStaffTimeEntry.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->leftJoin('gibbonStaffProfile', 'gibbonStaffTimeEntry.gibbonPersonID=gibbonStaffProfile.gibbonPersonID')
            ->where('gibbonStaffTimeEntry.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID)
            ->where('gibbonStaffTimeEntry.date=:date')
            ->bindValue('date', $date)
            ->where('gibbonStaffTimeEntry.clockInTime IS NOT NULL')
            ->where('gibbonStaffTimeEntry.clockOutTime IS NULL')
            ->where("gibbonStaffTimeEntry.status='Active'")
            ->orderBy(['gibbonStaffTimeEntry.clockInTime ASC']);

        return $this->runSelect($query);
    }

    /**
     * Select staff members on break.
     *
     * @param int $gibbonSchoolYearID
     * @param string $date
     * @return \Gibbon\Database\Result
     */
    public function selectStaffOnBreak($gibbonSchoolYearID, $date)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonStaffTimeEntry.gibbonStaffTimeEntryID',
                'gibbonStaffTimeEntry.gibbonPersonID',
                'gibbonStaffTimeEntry.breakStart',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
            ])
            ->innerJoin('gibbonPerson', 'gibbonStaffTimeEntry.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->where('gibbonStaffTimeEntry.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID)
            ->where('gibbonStaffTimeEntry.date=:date')
            ->bindValue('date', $date)
            ->where('gibbonStaffTimeEntry.breakStart IS NOT NULL')
            ->where('gibbonStaffTimeEntry.breakEnd IS NULL')
            ->where("gibbonStaffTimeEntry.status='Active'")
            ->orderBy(['gibbonStaffTimeEntry.breakStart ASC']);

        return $this->runSelect($query);
    }

    /**
     * Clock in a staff member.
     *
     * @param int $gibbonPersonID
     * @param int $gibbonSchoolYearID
     * @param string $date
     * @param string $clockInTime
     * @param string $clockInMethod
     * @param string|null $clockInLocation
     * @param int|null $gibbonStaffScheduleID
     * @param string|null $notes
     * @return int|false
     */
    public function clockIn($gibbonPersonID, $gibbonSchoolYearID, $date, $clockInTime, $clockInMethod = 'Manual', $clockInLocation = null, $gibbonStaffScheduleID = null, $notes = null)
    {
        // Check if record already exists for this person and date
        $existing = $this->getTimeEntryByPersonAndDate($gibbonPersonID, $date);

        if (!empty($existing)) {
            // Update existing record
            return $this->update($existing['gibbonStaffTimeEntryID'], [
                'clockInTime' => $clockInTime,
                'clockInMethod' => $clockInMethod,
                'clockInLocation' => $clockInLocation,
                'gibbonStaffScheduleID' => $gibbonStaffScheduleID,
                'status' => 'Active',
                'notes' => $notes,
            ]) ? $existing['gibbonStaffTimeEntryID'] : false;
        }

        // Create new time entry record
        return $this->insert([
            'gibbonPersonID' => $gibbonPersonID,
            'gibbonSchoolYearID' => $gibbonSchoolYearID,
            'gibbonStaffScheduleID' => $gibbonStaffScheduleID,
            'date' => $date,
            'clockInTime' => $clockInTime,
            'clockInMethod' => $clockInMethod,
            'clockInLocation' => $clockInLocation,
            'status' => 'Active',
            'notes' => $notes,
        ]);
    }

    /**
     * Clock out a staff member.
     *
     * @param int $gibbonStaffTimeEntryID
     * @param string $clockOutTime
     * @param string $clockOutMethod
     * @param string|null $clockOutLocation
     * @param string|null $notes
     * @return bool
     */
    public function clockOut($gibbonStaffTimeEntryID, $clockOutTime, $clockOutMethod = 'Manual', $clockOutLocation = null, $notes = null)
    {
        // Get the time entry to calculate worked hours
        $timeEntry = $this->getByID($gibbonStaffTimeEntryID);
        if (empty($timeEntry) || empty($timeEntry['clockInTime'])) {
            return false;
        }

        // Calculate total worked minutes
        $clockIn = new \DateTime($timeEntry['clockInTime']);
        $clockOut = new \DateTime($clockOutTime);
        $diff = $clockIn->diff($clockOut);
        $totalMinutes = ($diff->h * 60) + $diff->i + ($diff->days * 24 * 60);

        // Subtract break time
        $breakMinutes = intval($timeEntry['totalBreakMinutes'] ?? 0);
        $workedMinutes = max(0, $totalMinutes - $breakMinutes);

        $data = [
            'clockOutTime' => $clockOutTime,
            'clockOutMethod' => $clockOutMethod,
            'clockOutLocation' => $clockOutLocation,
            'totalWorkedMinutes' => $workedMinutes,
            'status' => 'Completed',
        ];

        if ($notes !== null) {
            $data['notes'] = $notes;
        }

        return $this->update($gibbonStaffTimeEntryID, $data);
    }

    /**
     * Start a break for a staff member.
     *
     * @param int $gibbonStaffTimeEntryID
     * @param string $breakStart
     * @return bool
     */
    public function startBreak($gibbonStaffTimeEntryID, $breakStart)
    {
        return $this->update($gibbonStaffTimeEntryID, [
            'breakStart' => $breakStart,
        ]);
    }

    /**
     * End a break for a staff member.
     *
     * @param int $gibbonStaffTimeEntryID
     * @param string $breakEnd
     * @return bool
     */
    public function endBreak($gibbonStaffTimeEntryID, $breakEnd)
    {
        // Get the time entry to calculate break duration
        $timeEntry = $this->getByID($gibbonStaffTimeEntryID);
        if (empty($timeEntry) || empty($timeEntry['breakStart'])) {
            return false;
        }

        // Calculate break minutes
        $breakStartTime = new \DateTime($timeEntry['breakStart']);
        $breakEndTime = new \DateTime($breakEnd);
        $diff = $breakStartTime->diff($breakEndTime);
        $breakMinutes = ($diff->h * 60) + $diff->i;

        // Add to existing break minutes
        $totalBreakMinutes = intval($timeEntry['totalBreakMinutes'] ?? 0) + $breakMinutes;

        return $this->update($gibbonStaffTimeEntryID, [
            'breakEnd' => $breakEnd,
            'totalBreakMinutes' => $totalBreakMinutes,
        ]);
    }

    /**
     * Mark time entry as having overtime and calculate overtime minutes.
     *
     * @param int $gibbonStaffTimeEntryID
     * @param int $overtimeThresholdMinutes
     * @return bool
     */
    public function calculateOvertime($gibbonStaffTimeEntryID, $overtimeThresholdMinutes = 480)
    {
        $timeEntry = $this->getByID($gibbonStaffTimeEntryID);
        if (empty($timeEntry) || empty($timeEntry['totalWorkedMinutes'])) {
            return false;
        }

        $workedMinutes = intval($timeEntry['totalWorkedMinutes']);
        $overtimeMinutes = max(0, $workedMinutes - $overtimeThresholdMinutes);
        $isOvertime = $overtimeMinutes > 0 ? 'Y' : 'N';

        return $this->update($gibbonStaffTimeEntryID, [
            'overtime' => $isOvertime,
            'overtimeMinutes' => $overtimeMinutes,
            'overtimeApproved' => $isOvertime === 'Y' ? 'Pending' : 'N',
        ]);
    }

    /**
     * Approve overtime for a time entry.
     *
     * @param int $gibbonStaffTimeEntryID
     * @param int $approvedByID
     * @return bool
     */
    public function approveOvertime($gibbonStaffTimeEntryID, $approvedByID)
    {
        return $this->update($gibbonStaffTimeEntryID, [
            'overtimeApproved' => 'Y',
            'overtimeApprovedByID' => $approvedByID,
        ]);
    }

    /**
     * Deny overtime for a time entry.
     *
     * @param int $gibbonStaffTimeEntryID
     * @param int $approvedByID
     * @return bool
     */
    public function denyOvertime($gibbonStaffTimeEntryID, $approvedByID)
    {
        return $this->update($gibbonStaffTimeEntryID, [
            'overtimeApproved' => 'N',
            'overtimeApprovedByID' => $approvedByID,
        ]);
    }

    /**
     * Adjust a time entry with reason.
     *
     * @param int $gibbonStaffTimeEntryID
     * @param array $data
     * @param int $adjustedByID
     * @param string $adjustmentReason
     * @return bool
     */
    public function adjustTimeEntry($gibbonStaffTimeEntryID, $data, $adjustedByID, $adjustmentReason)
    {
        $data['status'] = 'Adjusted';
        $data['adjustedByID'] = $adjustedByID;
        $data['adjustmentReason'] = $adjustmentReason;

        // Recalculate worked minutes if clock times changed
        if (isset($data['clockInTime']) || isset($data['clockOutTime'])) {
            $timeEntry = $this->getByID($gibbonStaffTimeEntryID);
            $clockIn = new \DateTime($data['clockInTime'] ?? $timeEntry['clockInTime']);
            $clockOut = new \DateTime($data['clockOutTime'] ?? $timeEntry['clockOutTime']);

            if ($clockOut > $clockIn) {
                $diff = $clockIn->diff($clockOut);
                $totalMinutes = ($diff->h * 60) + $diff->i + ($diff->days * 24 * 60);
                $breakMinutes = intval($data['totalBreakMinutes'] ?? $timeEntry['totalBreakMinutes'] ?? 0);
                $data['totalWorkedMinutes'] = max(0, $totalMinutes - $breakMinutes);
            }
        }

        return $this->update($gibbonStaffTimeEntryID, $data);
    }

    /**
     * Get daily time tracking summary for a specific date.
     *
     * @param int $gibbonSchoolYearID
     * @param string $date
     * @return array
     */
    public function getDailySummary($gibbonSchoolYearID, $date)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID, 'date' => $date];
        $sql = "SELECT
                    COUNT(*) as totalEntries,
                    SUM(CASE WHEN clockInTime IS NOT NULL THEN 1 ELSE 0 END) as totalClockedIn,
                    SUM(CASE WHEN clockOutTime IS NOT NULL THEN 1 ELSE 0 END) as totalClockedOut,
                    SUM(CASE WHEN clockInTime IS NOT NULL AND clockOutTime IS NULL THEN 1 ELSE 0 END) as currentlyWorking,
                    SUM(CASE WHEN breakStart IS NOT NULL AND breakEnd IS NULL THEN 1 ELSE 0 END) as currentlyOnBreak,
                    SUM(CASE WHEN overtime='Y' THEN 1 ELSE 0 END) as totalWithOvertime,
                    SUM(CASE WHEN overtime='Y' AND overtimeApproved='Pending' THEN 1 ELSE 0 END) as pendingOvertimeApproval,
                    SUM(COALESCE(totalWorkedMinutes, 0)) as totalWorkedMinutes,
                    SUM(COALESCE(overtimeMinutes, 0)) as totalOvertimeMinutes
                FROM gibbonStaffTimeEntry
                WHERE gibbonSchoolYearID=:gibbonSchoolYearID
                AND date=:date";

        return $this->db()->selectOne($sql, $data) ?: [
            'totalEntries' => 0,
            'totalClockedIn' => 0,
            'totalClockedOut' => 0,
            'currentlyWorking' => 0,
            'currentlyOnBreak' => 0,
            'totalWithOvertime' => 0,
            'pendingOvertimeApproval' => 0,
            'totalWorkedMinutes' => 0,
            'totalOvertimeMinutes' => 0,
        ];
    }

    /**
     * Get hours summary for a staff member in a date range.
     *
     * @param int $gibbonPersonID
     * @param string $dateStart
     * @param string $dateEnd
     * @return array
     */
    public function getHoursSummaryByPersonAndDateRange($gibbonPersonID, $dateStart, $dateEnd)
    {
        $data = ['gibbonPersonID' => $gibbonPersonID, 'dateStart' => $dateStart, 'dateEnd' => $dateEnd];
        $sql = "SELECT
                    COUNT(*) as totalDays,
                    SUM(COALESCE(totalWorkedMinutes, 0)) as totalWorkedMinutes,
                    SUM(COALESCE(totalBreakMinutes, 0)) as totalBreakMinutes,
                    SUM(COALESCE(overtimeMinutes, 0)) as totalOvertimeMinutes,
                    SUM(CASE WHEN overtime='Y' THEN 1 ELSE 0 END) as daysWithOvertime,
                    SUM(CASE WHEN overtime='Y' AND overtimeApproved='Y' THEN overtimeMinutes ELSE 0 END) as approvedOvertimeMinutes,
                    AVG(CASE WHEN totalWorkedMinutes > 0 THEN totalWorkedMinutes ELSE NULL END) as avgWorkedMinutesPerDay,
                    MIN(clockInTime) as earliestClockIn,
                    MAX(clockOutTime) as latestClockOut
                FROM gibbonStaffTimeEntry
                WHERE gibbonPersonID=:gibbonPersonID
                AND date >= :dateStart
                AND date <= :dateEnd
                AND status != 'Cancelled'";

        $result = $this->db()->selectOne($sql, $data) ?: [
            'totalDays' => 0,
            'totalWorkedMinutes' => 0,
            'totalBreakMinutes' => 0,
            'totalOvertimeMinutes' => 0,
            'daysWithOvertime' => 0,
            'approvedOvertimeMinutes' => 0,
            'avgWorkedMinutesPerDay' => 0,
            'earliestClockIn' => null,
            'latestClockOut' => null,
        ];

        // Convert minutes to hours for convenience
        $result['totalWorkedHours'] = round($result['totalWorkedMinutes'] / 60, 2);
        $result['totalOvertimeHours'] = round($result['totalOvertimeMinutes'] / 60, 2);
        $result['approvedOvertimeHours'] = round($result['approvedOvertimeMinutes'] / 60, 2);
        $result['avgWorkedHoursPerDay'] = round($result['avgWorkedMinutesPerDay'] / 60, 2);

        return $result;
    }

    /**
     * Get weekly hours summary for all staff.
     *
     * @param int $gibbonSchoolYearID
     * @param string $weekStart
     * @param string $weekEnd
     * @return \Gibbon\Database\Result
     */
    public function selectWeeklyHoursSummary($gibbonSchoolYearID, $weekStart, $weekEnd)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID, 'weekStart' => $weekStart, 'weekEnd' => $weekEnd];
        $sql = "SELECT
                    gibbonStaffTimeEntry.gibbonPersonID,
                    gibbonPerson.preferredName,
                    gibbonPerson.surname,
                    gibbonStaffProfile.position,
                    gibbonStaffProfile.department,
                    COUNT(*) as daysWorked,
                    SUM(COALESCE(totalWorkedMinutes, 0)) as totalWorkedMinutes,
                    SUM(COALESCE(overtimeMinutes, 0)) as totalOvertimeMinutes,
                    SUM(CASE WHEN overtime='Y' AND overtimeApproved='Pending' THEN 1 ELSE 0 END) as pendingOvertimeCount
                FROM gibbonStaffTimeEntry
                INNER JOIN gibbonPerson ON gibbonStaffTimeEntry.gibbonPersonID=gibbonPerson.gibbonPersonID
                LEFT JOIN gibbonStaffProfile ON gibbonStaffTimeEntry.gibbonPersonID=gibbonStaffProfile.gibbonPersonID
                WHERE gibbonStaffTimeEntry.gibbonSchoolYearID=:gibbonSchoolYearID
                AND gibbonStaffTimeEntry.date >= :weekStart
                AND gibbonStaffTimeEntry.date <= :weekEnd
                AND gibbonStaffTimeEntry.status != 'Cancelled'
                GROUP BY gibbonStaffTimeEntry.gibbonPersonID
                ORDER BY gibbonPerson.surname, gibbonPerson.preferredName";

        return $this->db()->select($sql, $data);
    }

    /**
     * Select time entries pending overtime approval.
     *
     * @param int $gibbonSchoolYearID
     * @return \Gibbon\Database\Result
     */
    public function selectPendingOvertimeApproval($gibbonSchoolYearID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonStaffTimeEntry.gibbonStaffTimeEntryID',
                'gibbonStaffTimeEntry.gibbonPersonID',
                'gibbonStaffTimeEntry.date',
                'gibbonStaffTimeEntry.clockInTime',
                'gibbonStaffTimeEntry.clockOutTime',
                'gibbonStaffTimeEntry.totalWorkedMinutes',
                'gibbonStaffTimeEntry.overtimeMinutes',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonStaffProfile.position',
            ])
            ->innerJoin('gibbonPerson', 'gibbonStaffTimeEntry.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->leftJoin('gibbonStaffProfile', 'gibbonStaffTimeEntry.gibbonPersonID=gibbonStaffProfile.gibbonPersonID')
            ->where('gibbonStaffTimeEntry.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID)
            ->where("gibbonStaffTimeEntry.overtime='Y'")
            ->where("gibbonStaffTimeEntry.overtimeApproved='Pending'")
            ->orderBy(['gibbonStaffTimeEntry.date DESC']);

        return $this->runSelect($query);
    }

    /**
     * Select time entries with adjustments.
     *
     * @param int $gibbonSchoolYearID
     * @param string|null $dateFrom
     * @param string|null $dateTo
     * @return \Gibbon\Database\Result
     */
    public function selectAdjustedTimeEntries($gibbonSchoolYearID, $dateFrom = null, $dateTo = null)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonStaffTimeEntry.gibbonStaffTimeEntryID',
                'gibbonStaffTimeEntry.gibbonPersonID',
                'gibbonStaffTimeEntry.date',
                'gibbonStaffTimeEntry.clockInTime',
                'gibbonStaffTimeEntry.clockOutTime',
                'gibbonStaffTimeEntry.adjustmentReason',
                'gibbonStaffTimeEntry.timestampModified',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'adjustedBy.preferredName as adjustedByName',
                'adjustedBy.surname as adjustedBySurname',
            ])
            ->innerJoin('gibbonPerson', 'gibbonStaffTimeEntry.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->leftJoin('gibbonPerson as adjustedBy', 'gibbonStaffTimeEntry.adjustedByID=adjustedBy.gibbonPersonID')
            ->where('gibbonStaffTimeEntry.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID)
            ->where("gibbonStaffTimeEntry.status='Adjusted'")
            ->orderBy(['gibbonStaffTimeEntry.timestampModified DESC']);

        if ($dateFrom !== null) {
            $query->where('gibbonStaffTimeEntry.date>=:dateFrom')
                  ->bindValue('dateFrom', $dateFrom);
        }

        if ($dateTo !== null) {
            $query->where('gibbonStaffTimeEntry.date<=:dateTo')
                  ->bindValue('dateTo', $dateTo);
        }

        return $this->runSelect($query);
    }

    /**
     * Check if staff member is late based on scheduled start time.
     *
     * @param int $gibbonPersonID
     * @param string $date
     * @param string $clockInTime
     * @param int $lateThresholdMinutes
     * @return array
     */
    public function checkIfLate($gibbonPersonID, $date, $clockInTime, $lateThresholdMinutes = 5)
    {
        // Get scheduled start time for this staff member on this date
        $data = ['gibbonPersonID' => $gibbonPersonID, 'date' => $date];
        $sql = "SELECT startTime FROM gibbonStaffSchedule
                WHERE gibbonPersonID=:gibbonPersonID
                AND date=:date
                AND status != 'Cancelled'
                LIMIT 1";

        $schedule = $this->db()->selectOne($sql, $data);

        if (empty($schedule) || empty($schedule['startTime'])) {
            return ['isLate' => false, 'minutesLate' => 0, 'scheduledTime' => null];
        }

        $scheduledStart = new \DateTime($date . ' ' . $schedule['startTime']);
        $actualClockIn = new \DateTime($clockInTime);

        $diff = $scheduledStart->diff($actualClockIn);
        $minutesDiff = ($diff->h * 60) + $diff->i;

        // If clock-in is after scheduled start
        if ($actualClockIn > $scheduledStart && $minutesDiff > $lateThresholdMinutes) {
            return [
                'isLate' => true,
                'minutesLate' => $minutesDiff,
                'scheduledTime' => $schedule['startTime'],
            ];
        }

        return [
            'isLate' => false,
            'minutesLate' => 0,
            'scheduledTime' => $schedule['startTime'],
        ];
    }

    /**
     * Get late arrivals report for a date range.
     *
     * @param int $gibbonSchoolYearID
     * @param string $dateStart
     * @param string $dateEnd
     * @param int $lateThresholdMinutes
     * @return \Gibbon\Database\Result
     */
    public function selectLateArrivals($gibbonSchoolYearID, $dateStart, $dateEnd, $lateThresholdMinutes = 5)
    {
        $data = [
            'gibbonSchoolYearID' => $gibbonSchoolYearID,
            'dateStart' => $dateStart,
            'dateEnd' => $dateEnd,
            'lateThreshold' => $lateThresholdMinutes,
        ];
        $sql = "SELECT
                    t.gibbonStaffTimeEntryID,
                    t.gibbonPersonID,
                    t.date,
                    TIME(t.clockInTime) as clockInTime,
                    s.startTime as scheduledStartTime,
                    TIMESTAMPDIFF(MINUTE, CONCAT(t.date, ' ', s.startTime), t.clockInTime) as minutesLate,
                    p.preferredName,
                    p.surname,
                    sp.position
                FROM gibbonStaffTimeEntry t
                INNER JOIN gibbonPerson p ON t.gibbonPersonID=p.gibbonPersonID
                LEFT JOIN gibbonStaffProfile sp ON t.gibbonPersonID=sp.gibbonPersonID
                INNER JOIN gibbonStaffSchedule s ON t.gibbonPersonID=s.gibbonPersonID AND t.date=s.date
                WHERE t.gibbonSchoolYearID=:gibbonSchoolYearID
                AND t.date >= :dateStart
                AND t.date <= :dateEnd
                AND t.clockInTime IS NOT NULL
                AND s.status != 'Cancelled'
                AND TIMESTAMPDIFF(MINUTE, CONCAT(t.date, ' ', s.startTime), t.clockInTime) > :lateThreshold
                ORDER BY t.date DESC, minutesLate DESC";

        return $this->db()->select($sql, $data);
    }

    /**
     * Cancel a time entry.
     *
     * @param int $gibbonStaffTimeEntryID
     * @param int $cancelledByID
     * @param string $reason
     * @return bool
     */
    public function cancelTimeEntry($gibbonStaffTimeEntryID, $cancelledByID, $reason)
    {
        return $this->update($gibbonStaffTimeEntryID, [
            'status' => 'Cancelled',
            'adjustedByID' => $cancelledByID,
            'adjustmentReason' => 'Cancelled: ' . $reason,
        ]);
    }

    /**
     * Get monthly hours summary for payroll.
     *
     * @param int $gibbonSchoolYearID
     * @param int $month
     * @param int $year
     * @return \Gibbon\Database\Result
     */
    public function selectMonthlyHoursForPayroll($gibbonSchoolYearID, $month, $year)
    {
        $data = [
            'gibbonSchoolYearID' => $gibbonSchoolYearID,
            'monthStart' => sprintf('%04d-%02d-01', $year, $month),
            'monthEnd' => date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $year, $month))),
        ];
        $sql = "SELECT
                    t.gibbonPersonID,
                    p.preferredName,
                    p.surname,
                    sp.position,
                    sp.employmentType,
                    COUNT(*) as daysWorked,
                    SUM(COALESCE(t.totalWorkedMinutes, 0)) as totalWorkedMinutes,
                    SUM(COALESCE(t.totalBreakMinutes, 0)) as totalBreakMinutes,
                    SUM(COALESCE(t.overtimeMinutes, 0)) as totalOvertimeMinutes,
                    SUM(CASE WHEN t.overtime='Y' AND t.overtimeApproved='Y' THEN t.overtimeMinutes ELSE 0 END) as approvedOvertimeMinutes,
                    SUM(CASE WHEN t.overtime='Y' AND t.overtimeApproved='N' THEN t.overtimeMinutes ELSE 0 END) as deniedOvertimeMinutes,
                    SUM(CASE WHEN t.overtime='Y' AND t.overtimeApproved='Pending' THEN t.overtimeMinutes ELSE 0 END) as pendingOvertimeMinutes
                FROM gibbonStaffTimeEntry t
                INNER JOIN gibbonPerson p ON t.gibbonPersonID=p.gibbonPersonID
                LEFT JOIN gibbonStaffProfile sp ON t.gibbonPersonID=sp.gibbonPersonID
                WHERE t.gibbonSchoolYearID=:gibbonSchoolYearID
                AND t.date >= :monthStart
                AND t.date <= :monthEnd
                AND t.status != 'Cancelled'
                GROUP BY t.gibbonPersonID
                ORDER BY p.surname, p.preferredName";

        return $this->db()->select($sql, $data);
    }
}
