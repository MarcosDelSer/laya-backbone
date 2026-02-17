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

namespace Gibbon\Module\CareTracking\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\QueryableGateway;

/**
 * Care Tracking Attendance Gateway
 *
 * Handles daily attendance (check-in/check-out) for children in childcare settings.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class AttendanceGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonCareAttendance';
    private static $primaryKey = 'gibbonCareAttendanceID';

    private static $searchableColumns = ['gibbonPerson.preferredName', 'gibbonPerson.surname', 'gibbonCareAttendance.notes'];

    /**
     * Query attendance records with criteria support.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonSchoolYearID
     * @return DataSet
     */
    public function queryAttendance(QueryCriteria $criteria, $gibbonSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonCareAttendance.gibbonCareAttendanceID',
                'gibbonCareAttendance.gibbonPersonID',
                'gibbonCareAttendance.date',
                'gibbonCareAttendance.checkInTime',
                'gibbonCareAttendance.checkOutTime',
                'gibbonCareAttendance.lateArrival',
                'gibbonCareAttendance.earlyDeparture',
                'gibbonCareAttendance.absenceReason',
                'gibbonCareAttendance.pickupPersonName',
                'gibbonCareAttendance.pickupVerified',
                'gibbonCareAttendance.notes',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
                'checkedInBy.preferredName as checkInByName',
                'checkedInBy.surname as checkInBySurname',
                'checkedOutBy.preferredName as checkOutByName',
                'checkedOutBy.surname as checkOutBySurname',
            ])
            ->innerJoin('gibbonPerson', 'gibbonCareAttendance.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->leftJoin('gibbonPerson as checkedInBy', 'gibbonCareAttendance.checkInByID=checkedInBy.gibbonPersonID')
            ->leftJoin('gibbonPerson as checkedOutBy', 'gibbonCareAttendance.checkOutByID=checkedOutBy.gibbonPersonID')
            ->where('gibbonCareAttendance.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID);

        $criteria->addFilterRules([
            'date' => function ($query, $date) {
                return $query
                    ->where('gibbonCareAttendance.date=:date')
                    ->bindValue('date', $date);
            },
            'child' => function ($query, $gibbonPersonID) {
                return $query
                    ->where('gibbonCareAttendance.gibbonPersonID=:gibbonPersonID')
                    ->bindValue('gibbonPersonID', $gibbonPersonID);
            },
            'checkedIn' => function ($query, $value) {
                if ($value == 'Y') {
                    return $query->where('gibbonCareAttendance.checkInTime IS NOT NULL');
                } elseif ($value == 'N') {
                    return $query->where('gibbonCareAttendance.checkInTime IS NULL');
                }
                return $query;
            },
            'checkedOut' => function ($query, $value) {
                if ($value == 'Y') {
                    return $query->where('gibbonCareAttendance.checkOutTime IS NOT NULL');
                } elseif ($value == 'N') {
                    return $query->where('gibbonCareAttendance.checkOutTime IS NULL');
                }
                return $query;
            },
            'lateArrival' => function ($query, $value) {
                return $query
                    ->where('gibbonCareAttendance.lateArrival=:lateArrival')
                    ->bindValue('lateArrival', $value);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query attendance records for a specific date.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonSchoolYearID
     * @param string $date
     * @return DataSet
     */
    public function queryAttendanceByDate(QueryCriteria $criteria, $gibbonSchoolYearID, $date)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonCareAttendance.gibbonCareAttendanceID',
                'gibbonCareAttendance.gibbonPersonID',
                'gibbonCareAttendance.date',
                'gibbonCareAttendance.checkInTime',
                'gibbonCareAttendance.checkOutTime',
                'gibbonCareAttendance.lateArrival',
                'gibbonCareAttendance.earlyDeparture',
                'gibbonCareAttendance.absenceReason',
                'gibbonCareAttendance.pickupPersonName',
                'gibbonCareAttendance.pickupVerified',
                'gibbonCareAttendance.notes',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
                'gibbonPerson.dob',
            ])
            ->innerJoin('gibbonPerson', 'gibbonCareAttendance.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->where('gibbonCareAttendance.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID)
            ->where('gibbonCareAttendance.date=:date')
            ->bindValue('date', $date);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query attendance history for a specific child.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonPersonID
     * @param int $gibbonSchoolYearID
     * @return DataSet
     */
    public function queryAttendanceByPerson(QueryCriteria $criteria, $gibbonPersonID, $gibbonSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonCareAttendance.gibbonCareAttendanceID',
                'gibbonCareAttendance.date',
                'gibbonCareAttendance.checkInTime',
                'gibbonCareAttendance.checkOutTime',
                'gibbonCareAttendance.lateArrival',
                'gibbonCareAttendance.earlyDeparture',
                'gibbonCareAttendance.absenceReason',
                'gibbonCareAttendance.pickupPersonName',
                'gibbonCareAttendance.pickupVerified',
                'gibbonCareAttendance.notes',
                'checkedInBy.preferredName as checkInByName',
                'checkedInBy.surname as checkInBySurname',
                'checkedOutBy.preferredName as checkOutByName',
                'checkedOutBy.surname as checkOutBySurname',
            ])
            ->leftJoin('gibbonPerson as checkedInBy', 'gibbonCareAttendance.checkInByID=checkedInBy.gibbonPersonID')
            ->leftJoin('gibbonPerson as checkedOutBy', 'gibbonCareAttendance.checkOutByID=checkedOutBy.gibbonPersonID')
            ->where('gibbonCareAttendance.gibbonPersonID=:gibbonPersonID')
            ->bindValue('gibbonPersonID', $gibbonPersonID)
            ->where('gibbonCareAttendance.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Get attendance record for a specific child on a specific date.
     *
     * @param int $gibbonPersonID
     * @param string $date
     * @return array
     */
    public function getAttendanceByPersonAndDate($gibbonPersonID, $date)
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
     * Select children who have checked in but not checked out on a specific date.
     *
     * @param int $gibbonSchoolYearID
     * @param string $date
     * @return \Gibbon\Database\Result
     */
    public function selectChildrenCurrentlyCheckedIn($gibbonSchoolYearID, $date)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonCareAttendance.gibbonCareAttendanceID',
                'gibbonCareAttendance.gibbonPersonID',
                'gibbonCareAttendance.checkInTime',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
            ])
            ->innerJoin('gibbonPerson', 'gibbonCareAttendance.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->where('gibbonCareAttendance.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID)
            ->where('gibbonCareAttendance.date=:date')
            ->bindValue('date', $date)
            ->where('gibbonCareAttendance.checkInTime IS NOT NULL')
            ->where('gibbonCareAttendance.checkOutTime IS NULL')
            ->orderBy(['gibbonCareAttendance.checkInTime ASC']);

        return $this->runSelect($query);
    }

    /**
     * Select children who have not been checked in on a specific date.
     *
     * @param int $gibbonSchoolYearID
     * @param string $date
     * @return \Gibbon\Database\Result
     */
    public function selectChildrenNotCheckedIn($gibbonSchoolYearID, $date)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID, 'date' => $date];
        $sql = "SELECT gibbonPerson.gibbonPersonID, gibbonPerson.preferredName, gibbonPerson.surname, gibbonPerson.image_240
                FROM gibbonStudentEnrolment
                INNER JOIN gibbonPerson ON gibbonStudentEnrolment.gibbonPersonID=gibbonPerson.gibbonPersonID
                WHERE gibbonStudentEnrolment.gibbonSchoolYearID=:gibbonSchoolYearID
                AND gibbonPerson.status='Full'
                AND (gibbonPerson.dateStart IS NULL OR gibbonPerson.dateStart <= :date)
                AND (gibbonPerson.dateEnd IS NULL OR gibbonPerson.dateEnd >= :date)
                AND NOT EXISTS (
                    SELECT 1 FROM gibbonCareAttendance
                    WHERE gibbonCareAttendance.gibbonPersonID=gibbonPerson.gibbonPersonID
                    AND gibbonCareAttendance.date=:date
                    AND gibbonCareAttendance.checkInTime IS NOT NULL
                )
                ORDER BY gibbonPerson.surname, gibbonPerson.preferredName";

        return $this->db()->select($sql, $data);
    }

    /**
     * Get daily attendance summary statistics for a specific date.
     *
     * @param int $gibbonSchoolYearID
     * @param string $date
     * @return array
     */
    public function getAttendanceSummaryByDate($gibbonSchoolYearID, $date)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID, 'date' => $date];
        $sql = "SELECT
                    SUM(CASE WHEN checkInTime IS NOT NULL THEN 1 ELSE 0 END) as totalCheckedIn,
                    SUM(CASE WHEN checkOutTime IS NOT NULL THEN 1 ELSE 0 END) as totalCheckedOut,
                    SUM(CASE WHEN checkInTime IS NOT NULL AND checkOutTime IS NULL THEN 1 ELSE 0 END) as currentlyPresent,
                    SUM(CASE WHEN lateArrival='Y' THEN 1 ELSE 0 END) as totalLateArrivals,
                    SUM(CASE WHEN earlyDeparture='Y' THEN 1 ELSE 0 END) as totalEarlyDepartures,
                    SUM(CASE WHEN absenceReason IS NOT NULL AND absenceReason <> '' THEN 1 ELSE 0 END) as totalAbsent
                FROM gibbonCareAttendance
                WHERE gibbonSchoolYearID=:gibbonSchoolYearID
                AND date=:date";

        return $this->db()->selectOne($sql, $data) ?: [
            'totalCheckedIn' => 0,
            'totalCheckedOut' => 0,
            'currentlyPresent' => 0,
            'totalLateArrivals' => 0,
            'totalEarlyDepartures' => 0,
            'totalAbsent' => 0,
        ];
    }

    /**
     * Record check-in for a child.
     *
     * @param int $gibbonPersonID
     * @param int $gibbonSchoolYearID
     * @param string $date
     * @param string $time
     * @param int $checkInByID
     * @param bool $lateArrival
     * @param string|null $notes
     * @return int|false
     */
    public function checkIn($gibbonPersonID, $gibbonSchoolYearID, $date, $time, $checkInByID, $lateArrival = false, $notes = null)
    {
        // Check if record already exists for this person and date
        $existing = $this->getAttendanceByPersonAndDate($gibbonPersonID, $date);

        if (!empty($existing)) {
            // Update existing record
            return $this->update($existing['gibbonCareAttendanceID'], [
                'checkInTime' => $time,
                'checkInByID' => $checkInByID,
                'lateArrival' => $lateArrival ? 'Y' : 'N',
                'notes' => $notes,
            ]) ? $existing['gibbonCareAttendanceID'] : false;
        }

        // Create new attendance record
        return $this->insert([
            'gibbonPersonID' => $gibbonPersonID,
            'gibbonSchoolYearID' => $gibbonSchoolYearID,
            'date' => $date,
            'checkInTime' => $time,
            'checkInByID' => $checkInByID,
            'lateArrival' => $lateArrival ? 'Y' : 'N',
            'notes' => $notes,
        ]);
    }

    /**
     * Record check-out for a child.
     *
     * @param int $gibbonCareAttendanceID
     * @param string $time
     * @param int $checkOutByID
     * @param bool $earlyDeparture
     * @param string|null $pickupPersonName
     * @param int|null $gibbonCareAuthorizedPickupID
     * @param bool $pickupVerified
     * @param string|null $notes
     * @return bool
     */
    public function checkOut($gibbonCareAttendanceID, $time, $checkOutByID, $earlyDeparture = false, $pickupPersonName = null, $gibbonCareAuthorizedPickupID = null, $pickupVerified = false, $notes = null)
    {
        $data = [
            'checkOutTime' => $time,
            'checkOutByID' => $checkOutByID,
            'earlyDeparture' => $earlyDeparture ? 'Y' : 'N',
            'pickupVerified' => $pickupVerified ? 'Y' : 'N',
        ];

        if ($pickupPersonName !== null) {
            $data['pickupPersonName'] = $pickupPersonName;
        }

        if ($gibbonCareAuthorizedPickupID !== null) {
            $data['gibbonCareAuthorizedPickupID'] = $gibbonCareAuthorizedPickupID;
        }

        if ($notes !== null) {
            $data['notes'] = $notes;
        }

        return $this->update($gibbonCareAttendanceID, $data);
    }

    /**
     * Get attendance statistics for a date range.
     *
     * @param int $gibbonPersonID
     * @param string $dateStart
     * @param string $dateEnd
     * @return array
     */
    public function getAttendanceStatsByPersonAndDateRange($gibbonPersonID, $dateStart, $dateEnd)
    {
        $data = ['gibbonPersonID' => $gibbonPersonID, 'dateStart' => $dateStart, 'dateEnd' => $dateEnd];
        $sql = "SELECT
                    COUNT(*) as totalDays,
                    SUM(CASE WHEN checkInTime IS NOT NULL THEN 1 ELSE 0 END) as daysPresent,
                    SUM(CASE WHEN lateArrival='Y' THEN 1 ELSE 0 END) as daysLate,
                    SUM(CASE WHEN earlyDeparture='Y' THEN 1 ELSE 0 END) as daysEarlyDeparture,
                    AVG(TIME_TO_SEC(TIMEDIFF(checkOutTime, checkInTime))/3600) as avgHoursPerDay
                FROM gibbonCareAttendance
                WHERE gibbonPersonID=:gibbonPersonID
                AND date >= :dateStart
                AND date <= :dateEnd";

        return $this->db()->selectOne($sql, $data) ?: [
            'totalDays' => 0,
            'daysPresent' => 0,
            'daysLate' => 0,
            'daysEarlyDeparture' => 0,
            'avgHoursPerDay' => 0,
        ];
    }
}
