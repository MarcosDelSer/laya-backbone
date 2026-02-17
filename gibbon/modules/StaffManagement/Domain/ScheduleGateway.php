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
 * Schedule Gateway
 *
 * Handles weekly schedules, shift templates, and availability tracking for staff management.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class ScheduleGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonStaffSchedule';
    private static $primaryKey = 'gibbonStaffScheduleID';

    private static $searchableColumns = ['gibbonPerson.preferredName', 'gibbonPerson.surname', 'gibbonStaffSchedule.roomAssignment', 'gibbonStaffSchedule.notes'];

    // ========================================
    // SCHEDULE QUERY METHODS
    // ========================================

    /**
     * Query schedules with criteria support.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonSchoolYearID
     * @return DataSet
     */
    public function querySchedules(QueryCriteria $criteria, $gibbonSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonStaffSchedule.gibbonStaffScheduleID',
                'gibbonStaffSchedule.gibbonPersonID',
                'gibbonStaffSchedule.gibbonStaffShiftTemplateID',
                'gibbonStaffSchedule.date',
                'gibbonStaffSchedule.startTime',
                'gibbonStaffSchedule.endTime',
                'gibbonStaffSchedule.breakDuration',
                'gibbonStaffSchedule.roomAssignment',
                'gibbonStaffSchedule.ageGroup',
                'gibbonStaffSchedule.status',
                'gibbonStaffSchedule.notes',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
                'gibbonStaffShiftTemplate.name as shiftTemplateName',
                'gibbonStaffShiftTemplate.color as shiftTemplateColor',
            ])
            ->innerJoin('gibbonPerson', 'gibbonStaffSchedule.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->leftJoin('gibbonStaffShiftTemplate', 'gibbonStaffSchedule.gibbonStaffShiftTemplateID=gibbonStaffShiftTemplate.gibbonStaffShiftTemplateID')
            ->where('gibbonStaffSchedule.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID);

        $criteria->addFilterRules([
            'date' => function ($query, $date) {
                return $query
                    ->where('gibbonStaffSchedule.date=:date')
                    ->bindValue('date', $date);
            },
            'dateFrom' => function ($query, $dateFrom) {
                return $query
                    ->where('gibbonStaffSchedule.date>=:dateFrom')
                    ->bindValue('dateFrom', $dateFrom);
            },
            'dateTo' => function ($query, $dateTo) {
                return $query
                    ->where('gibbonStaffSchedule.date<=:dateTo')
                    ->bindValue('dateTo', $dateTo);
            },
            'staff' => function ($query, $gibbonPersonID) {
                return $query
                    ->where('gibbonStaffSchedule.gibbonPersonID=:gibbonPersonID')
                    ->bindValue('gibbonPersonID', $gibbonPersonID);
            },
            'status' => function ($query, $status) {
                return $query
                    ->where('gibbonStaffSchedule.status=:status')
                    ->bindValue('status', $status);
            },
            'ageGroup' => function ($query, $ageGroup) {
                return $query
                    ->where('gibbonStaffSchedule.ageGroup=:ageGroup')
                    ->bindValue('ageGroup', $ageGroup);
            },
            'roomAssignment' => function ($query, $roomAssignment) {
                return $query
                    ->where('gibbonStaffSchedule.roomAssignment=:roomAssignment')
                    ->bindValue('roomAssignment', $roomAssignment);
            },
            'shiftTemplate' => function ($query, $gibbonStaffShiftTemplateID) {
                return $query
                    ->where('gibbonStaffSchedule.gibbonStaffShiftTemplateID=:gibbonStaffShiftTemplateID')
                    ->bindValue('gibbonStaffShiftTemplateID', $gibbonStaffShiftTemplateID);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query schedules for a specific date range (week view).
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonSchoolYearID
     * @param string $dateFrom
     * @param string $dateTo
     * @return DataSet
     */
    public function querySchedulesByDateRange(QueryCriteria $criteria, $gibbonSchoolYearID, $dateFrom, $dateTo)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonStaffSchedule.gibbonStaffScheduleID',
                'gibbonStaffSchedule.gibbonPersonID',
                'gibbonStaffSchedule.gibbonStaffShiftTemplateID',
                'gibbonStaffSchedule.date',
                'gibbonStaffSchedule.startTime',
                'gibbonStaffSchedule.endTime',
                'gibbonStaffSchedule.breakDuration',
                'gibbonStaffSchedule.roomAssignment',
                'gibbonStaffSchedule.ageGroup',
                'gibbonStaffSchedule.status',
                'gibbonStaffSchedule.notes',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
                'gibbonStaffShiftTemplate.name as shiftTemplateName',
                'gibbonStaffShiftTemplate.color as shiftTemplateColor',
            ])
            ->innerJoin('gibbonPerson', 'gibbonStaffSchedule.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->leftJoin('gibbonStaffShiftTemplate', 'gibbonStaffSchedule.gibbonStaffShiftTemplateID=gibbonStaffShiftTemplate.gibbonStaffShiftTemplateID')
            ->where('gibbonStaffSchedule.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID)
            ->where('gibbonStaffSchedule.date>=:dateFrom')
            ->bindValue('dateFrom', $dateFrom)
            ->where('gibbonStaffSchedule.date<=:dateTo')
            ->bindValue('dateTo', $dateTo)
            ->orderBy(['gibbonStaffSchedule.date ASC', 'gibbonStaffSchedule.startTime ASC']);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query schedules for a specific staff member.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonPersonID
     * @param int $gibbonSchoolYearID
     * @return DataSet
     */
    public function querySchedulesByPerson(QueryCriteria $criteria, $gibbonPersonID, $gibbonSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonStaffSchedule.gibbonStaffScheduleID',
                'gibbonStaffSchedule.date',
                'gibbonStaffSchedule.startTime',
                'gibbonStaffSchedule.endTime',
                'gibbonStaffSchedule.breakDuration',
                'gibbonStaffSchedule.roomAssignment',
                'gibbonStaffSchedule.ageGroup',
                'gibbonStaffSchedule.status',
                'gibbonStaffSchedule.notes',
                'gibbonStaffShiftTemplate.name as shiftTemplateName',
                'gibbonStaffShiftTemplate.color as shiftTemplateColor',
            ])
            ->leftJoin('gibbonStaffShiftTemplate', 'gibbonStaffSchedule.gibbonStaffShiftTemplateID=gibbonStaffShiftTemplate.gibbonStaffShiftTemplateID')
            ->where('gibbonStaffSchedule.gibbonPersonID=:gibbonPersonID')
            ->bindValue('gibbonPersonID', $gibbonPersonID)
            ->where('gibbonStaffSchedule.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID)
            ->orderBy(['gibbonStaffSchedule.date ASC', 'gibbonStaffSchedule.startTime ASC']);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Get schedule entry by ID.
     *
     * @param int $gibbonStaffScheduleID
     * @return array
     */
    public function getScheduleByID($gibbonStaffScheduleID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols(['*'])
            ->where('gibbonStaffScheduleID=:gibbonStaffScheduleID')
            ->bindValue('gibbonStaffScheduleID', $gibbonStaffScheduleID);

        $result = $this->runSelect($query);
        return $result->isNotEmpty() ? $result->fetch() : [];
    }

    /**
     * Get schedule for a specific staff member on a specific date.
     *
     * @param int $gibbonPersonID
     * @param string $date
     * @return array
     */
    public function getScheduleByPersonAndDate($gibbonPersonID, $date)
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
     * Select staff scheduled for a specific date and time.
     *
     * @param int $gibbonSchoolYearID
     * @param string $date
     * @param string $time
     * @return \Gibbon\Database\Result
     */
    public function selectStaffScheduledAtTime($gibbonSchoolYearID, $date, $time)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonStaffSchedule.gibbonStaffScheduleID',
                'gibbonStaffSchedule.gibbonPersonID',
                'gibbonStaffSchedule.startTime',
                'gibbonStaffSchedule.endTime',
                'gibbonStaffSchedule.roomAssignment',
                'gibbonStaffSchedule.ageGroup',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
            ])
            ->innerJoin('gibbonPerson', 'gibbonStaffSchedule.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->where('gibbonStaffSchedule.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID)
            ->where('gibbonStaffSchedule.date=:date')
            ->bindValue('date', $date)
            ->where('gibbonStaffSchedule.startTime<=:time')
            ->bindValue('time', $time)
            ->where('gibbonStaffSchedule.endTime>:time2')
            ->bindValue('time2', $time)
            ->where("gibbonStaffSchedule.status IN ('Scheduled', 'Confirmed')")
            ->orderBy(['gibbonPerson.surname ASC', 'gibbonPerson.preferredName ASC']);

        return $this->runSelect($query);
    }

    /**
     * Select staff scheduled for a specific date by age group.
     *
     * @param int $gibbonSchoolYearID
     * @param string $date
     * @param string $ageGroup
     * @return \Gibbon\Database\Result
     */
    public function selectStaffScheduledByAgeGroup($gibbonSchoolYearID, $date, $ageGroup)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonStaffSchedule.gibbonStaffScheduleID',
                'gibbonStaffSchedule.gibbonPersonID',
                'gibbonStaffSchedule.startTime',
                'gibbonStaffSchedule.endTime',
                'gibbonStaffSchedule.roomAssignment',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
            ])
            ->innerJoin('gibbonPerson', 'gibbonStaffSchedule.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->where('gibbonStaffSchedule.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID)
            ->where('gibbonStaffSchedule.date=:date')
            ->bindValue('date', $date)
            ->where('gibbonStaffSchedule.ageGroup=:ageGroup')
            ->bindValue('ageGroup', $ageGroup)
            ->where("gibbonStaffSchedule.status IN ('Scheduled', 'Confirmed')")
            ->orderBy(['gibbonStaffSchedule.startTime ASC']);

        return $this->runSelect($query);
    }

    /**
     * Get scheduled hours summary for a staff member within a date range.
     *
     * @param int $gibbonPersonID
     * @param string $dateFrom
     * @param string $dateTo
     * @return array
     */
    public function getScheduledHoursSummary($gibbonPersonID, $dateFrom, $dateTo)
    {
        $data = ['gibbonPersonID' => $gibbonPersonID, 'dateFrom' => $dateFrom, 'dateTo' => $dateTo];
        $sql = "SELECT
                    COUNT(*) as totalShifts,
                    SUM(TIME_TO_SEC(TIMEDIFF(endTime, startTime))/60 - breakDuration) as totalMinutes,
                    SUM(breakDuration) as totalBreakMinutes,
                    COUNT(DISTINCT date) as totalDays
                FROM gibbonStaffSchedule
                WHERE gibbonPersonID=:gibbonPersonID
                AND date >= :dateFrom
                AND date <= :dateTo
                AND status IN ('Scheduled', 'Confirmed', 'Completed')";

        return $this->db()->selectOne($sql, $data) ?: [
            'totalShifts' => 0,
            'totalMinutes' => 0,
            'totalBreakMinutes' => 0,
            'totalDays' => 0,
        ];
    }

    /**
     * Get daily schedule summary for a specific date.
     *
     * @param int $gibbonSchoolYearID
     * @param string $date
     * @return array
     */
    public function getDailyScheduleSummary($gibbonSchoolYearID, $date)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID, 'date' => $date];
        $sql = "SELECT
                    COUNT(*) as totalScheduled,
                    COUNT(DISTINCT gibbonPersonID) as uniqueStaff,
                    SUM(CASE WHEN status='Confirmed' THEN 1 ELSE 0 END) as confirmed,
                    SUM(CASE WHEN status='Cancelled' THEN 1 ELSE 0 END) as cancelled,
                    SUM(CASE WHEN status='No Show' THEN 1 ELSE 0 END) as noShow,
                    MIN(startTime) as earliestStart,
                    MAX(endTime) as latestEnd
                FROM gibbonStaffSchedule
                WHERE gibbonSchoolYearID=:gibbonSchoolYearID
                AND date=:date";

        return $this->db()->selectOne($sql, $data) ?: [
            'totalScheduled' => 0,
            'uniqueStaff' => 0,
            'confirmed' => 0,
            'cancelled' => 0,
            'noShow' => 0,
            'earliestStart' => null,
            'latestEnd' => null,
        ];
    }

    /**
     * Check for scheduling conflicts for a staff member.
     *
     * @param int $gibbonPersonID
     * @param string $date
     * @param string $startTime
     * @param string $endTime
     * @param int|null $excludeScheduleID
     * @return array
     */
    public function findSchedulingConflicts($gibbonPersonID, $date, $startTime, $endTime, $excludeScheduleID = null)
    {
        $data = [
            'gibbonPersonID' => $gibbonPersonID,
            'date' => $date,
            'startTime' => $startTime,
            'endTime' => $endTime,
        ];
        $sql = "SELECT gibbonStaffScheduleID, startTime, endTime, roomAssignment
                FROM gibbonStaffSchedule
                WHERE gibbonPersonID=:gibbonPersonID
                AND date=:date
                AND status NOT IN ('Cancelled')
                AND (
                    (startTime < :endTime AND endTime > :startTime)
                )";

        if ($excludeScheduleID !== null) {
            $sql .= " AND gibbonStaffScheduleID != :excludeScheduleID";
            $data['excludeScheduleID'] = $excludeScheduleID;
        }

        return $this->db()->select($sql, $data)->fetchAll();
    }

    /**
     * Copy schedules from one week to another.
     *
     * @param int $gibbonSchoolYearID
     * @param string $sourceWeekStart
     * @param string $targetWeekStart
     * @param int $createdByID
     * @return int Number of schedules copied
     */
    public function copyWeekSchedule($gibbonSchoolYearID, $sourceWeekStart, $targetWeekStart, $createdByID)
    {
        $data = [
            'gibbonSchoolYearID' => $gibbonSchoolYearID,
            'sourceWeekStart' => $sourceWeekStart,
            'sourceWeekEnd' => date('Y-m-d', strtotime($sourceWeekStart . ' +6 days')),
            'daysDiff' => (strtotime($targetWeekStart) - strtotime($sourceWeekStart)) / 86400,
            'createdByID' => $createdByID,
        ];

        $sql = "INSERT INTO gibbonStaffSchedule (gibbonPersonID, gibbonSchoolYearID, gibbonStaffShiftTemplateID, date, startTime, endTime, breakDuration, roomAssignment, ageGroup, status, notes, createdByID)
                SELECT gibbonPersonID, gibbonSchoolYearID, gibbonStaffShiftTemplateID, DATE_ADD(date, INTERVAL :daysDiff DAY), startTime, endTime, breakDuration, roomAssignment, ageGroup, 'Scheduled', notes, :createdByID
                FROM gibbonStaffSchedule
                WHERE gibbonSchoolYearID=:gibbonSchoolYearID
                AND date >= :sourceWeekStart
                AND date <= :sourceWeekEnd
                AND status NOT IN ('Cancelled')";

        return $this->db()->executeQuery($sql, $data);
    }

    // ========================================
    // SHIFT TEMPLATE METHODS
    // ========================================

    /**
     * Query shift templates with criteria support.
     *
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryShiftTemplates(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->from('gibbonStaffShiftTemplate')
            ->cols([
                'gibbonStaffShiftTemplate.gibbonStaffShiftTemplateID',
                'gibbonStaffShiftTemplate.name',
                'gibbonStaffShiftTemplate.description',
                'gibbonStaffShiftTemplate.startTime',
                'gibbonStaffShiftTemplate.endTime',
                'gibbonStaffShiftTemplate.breakDuration',
                'gibbonStaffShiftTemplate.color',
                'gibbonStaffShiftTemplate.active',
                'gibbonPerson.preferredName as createdByName',
                'gibbonPerson.surname as createdBySurname',
            ])
            ->leftJoin('gibbonPerson', 'gibbonStaffShiftTemplate.createdByID=gibbonPerson.gibbonPersonID');

        $criteria->addFilterRules([
            'active' => function ($query, $active) {
                return $query
                    ->where('gibbonStaffShiftTemplate.active=:active')
                    ->bindValue('active', $active);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Select active shift templates for dropdown lists.
     *
     * @return \Gibbon\Database\Result
     */
    public function selectActiveShiftTemplates()
    {
        $query = $this
            ->newSelect()
            ->from('gibbonStaffShiftTemplate')
            ->cols([
                'gibbonStaffShiftTemplateID',
                'name',
                'startTime',
                'endTime',
                'breakDuration',
                'color',
            ])
            ->where("active='Y'")
            ->orderBy(['name ASC']);

        return $this->runSelect($query);
    }

    /**
     * Get shift template by ID.
     *
     * @param int $gibbonStaffShiftTemplateID
     * @return array
     */
    public function getShiftTemplateByID($gibbonStaffShiftTemplateID)
    {
        $query = $this
            ->newSelect()
            ->from('gibbonStaffShiftTemplate')
            ->cols(['*'])
            ->where('gibbonStaffShiftTemplateID=:gibbonStaffShiftTemplateID')
            ->bindValue('gibbonStaffShiftTemplateID', $gibbonStaffShiftTemplateID);

        $result = $this->runSelect($query);
        return $result->isNotEmpty() ? $result->fetch() : [];
    }

    /**
     * Insert a new shift template.
     *
     * @param array $data
     * @return int|false
     */
    public function insertShiftTemplate(array $data)
    {
        $query = $this
            ->newInsert()
            ->into('gibbonStaffShiftTemplate')
            ->cols($data);

        return $this->runInsert($query);
    }

    /**
     * Update a shift template.
     *
     * @param int $gibbonStaffShiftTemplateID
     * @param array $data
     * @return bool
     */
    public function updateShiftTemplate($gibbonStaffShiftTemplateID, array $data)
    {
        $query = $this
            ->newUpdate()
            ->table('gibbonStaffShiftTemplate')
            ->cols($data)
            ->where('gibbonStaffShiftTemplateID=:gibbonStaffShiftTemplateID')
            ->bindValue('gibbonStaffShiftTemplateID', $gibbonStaffShiftTemplateID);

        return $this->runUpdate($query);
    }

    /**
     * Delete a shift template.
     *
     * @param int $gibbonStaffShiftTemplateID
     * @return bool
     */
    public function deleteShiftTemplate($gibbonStaffShiftTemplateID)
    {
        $query = $this
            ->newDelete()
            ->from('gibbonStaffShiftTemplate')
            ->where('gibbonStaffShiftTemplateID=:gibbonStaffShiftTemplateID')
            ->bindValue('gibbonStaffShiftTemplateID', $gibbonStaffShiftTemplateID);

        return $this->runDelete($query);
    }

    // ========================================
    // AVAILABILITY METHODS
    // ========================================

    /**
     * Query staff availability with criteria support.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonPersonID
     * @return DataSet
     */
    public function queryAvailabilityByPerson(QueryCriteria $criteria, $gibbonPersonID)
    {
        $query = $this
            ->newQuery()
            ->from('gibbonStaffAvailability')
            ->cols([
                'gibbonStaffAvailability.gibbonStaffAvailabilityID',
                'gibbonStaffAvailability.gibbonPersonID',
                'gibbonStaffAvailability.dayOfWeek',
                'gibbonStaffAvailability.availableFrom',
                'gibbonStaffAvailability.availableTo',
                'gibbonStaffAvailability.isAvailable',
                'gibbonStaffAvailability.preferredHours',
                'gibbonStaffAvailability.maxHours',
                'gibbonStaffAvailability.notes',
                'gibbonStaffAvailability.effectiveFrom',
                'gibbonStaffAvailability.effectiveTo',
            ])
            ->where('gibbonStaffAvailability.gibbonPersonID=:gibbonPersonID')
            ->bindValue('gibbonPersonID', $gibbonPersonID);

        $criteria->addFilterRules([
            'dayOfWeek' => function ($query, $dayOfWeek) {
                return $query
                    ->where('gibbonStaffAvailability.dayOfWeek=:dayOfWeek')
                    ->bindValue('dayOfWeek', $dayOfWeek);
            },
            'isAvailable' => function ($query, $isAvailable) {
                return $query
                    ->where('gibbonStaffAvailability.isAvailable=:isAvailable')
                    ->bindValue('isAvailable', $isAvailable);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Get current availability for a staff member.
     *
     * @param int $gibbonPersonID
     * @param string|null $effectiveDate
     * @return array
     */
    public function getCurrentAvailability($gibbonPersonID, $effectiveDate = null)
    {
        $effectiveDate = $effectiveDate ?: date('Y-m-d');
        $data = ['gibbonPersonID' => $gibbonPersonID, 'effectiveDate' => $effectiveDate];
        $sql = "SELECT *
                FROM gibbonStaffAvailability
                WHERE gibbonPersonID=:gibbonPersonID
                AND (effectiveFrom IS NULL OR effectiveFrom <= :effectiveDate)
                AND (effectiveTo IS NULL OR effectiveTo >= :effectiveDate)
                ORDER BY FIELD(dayOfWeek, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')";

        return $this->db()->select($sql, $data)->fetchAll();
    }

    /**
     * Get availability for a specific day of the week.
     *
     * @param int $gibbonPersonID
     * @param string $dayOfWeek
     * @param string|null $effectiveDate
     * @return array
     */
    public function getAvailabilityByDay($gibbonPersonID, $dayOfWeek, $effectiveDate = null)
    {
        $effectiveDate = $effectiveDate ?: date('Y-m-d');
        $data = ['gibbonPersonID' => $gibbonPersonID, 'dayOfWeek' => $dayOfWeek, 'effectiveDate' => $effectiveDate];
        $sql = "SELECT *
                FROM gibbonStaffAvailability
                WHERE gibbonPersonID=:gibbonPersonID
                AND dayOfWeek=:dayOfWeek
                AND (effectiveFrom IS NULL OR effectiveFrom <= :effectiveDate)
                AND (effectiveTo IS NULL OR effectiveTo >= :effectiveDate)";

        return $this->db()->selectOne($sql, $data) ?: [];
    }

    /**
     * Check if a staff member is available at a specific time.
     *
     * @param int $gibbonPersonID
     * @param string $date
     * @param string $startTime
     * @param string $endTime
     * @return bool
     */
    public function isStaffAvailable($gibbonPersonID, $date, $startTime, $endTime)
    {
        $dayOfWeek = date('l', strtotime($date));
        $availability = $this->getAvailabilityByDay($gibbonPersonID, $dayOfWeek, $date);

        if (empty($availability)) {
            return true; // No availability record means available by default
        }

        if ($availability['isAvailable'] !== 'Y') {
            return false;
        }

        // Check time range if specified
        if (!empty($availability['availableFrom']) && !empty($availability['availableTo'])) {
            return $startTime >= $availability['availableFrom'] && $endTime <= $availability['availableTo'];
        }

        return true;
    }

    /**
     * Select staff available on a specific day and time.
     *
     * @param string $dayOfWeek
     * @param string $startTime
     * @param string $endTime
     * @param string|null $effectiveDate
     * @return \Gibbon\Database\Result
     */
    public function selectAvailableStaff($dayOfWeek, $startTime, $endTime, $effectiveDate = null)
    {
        $effectiveDate = $effectiveDate ?: date('Y-m-d');
        $data = [
            'dayOfWeek' => $dayOfWeek,
            'startTime' => $startTime,
            'endTime' => $endTime,
            'effectiveDate' => $effectiveDate,
        ];
        $sql = "SELECT gibbonStaffAvailability.*, gibbonPerson.preferredName, gibbonPerson.surname, gibbonPerson.image_240
                FROM gibbonStaffAvailability
                INNER JOIN gibbonPerson ON gibbonStaffAvailability.gibbonPersonID=gibbonPerson.gibbonPersonID
                WHERE gibbonStaffAvailability.dayOfWeek=:dayOfWeek
                AND gibbonStaffAvailability.isAvailable='Y'
                AND (gibbonStaffAvailability.effectiveFrom IS NULL OR gibbonStaffAvailability.effectiveFrom <= :effectiveDate)
                AND (gibbonStaffAvailability.effectiveTo IS NULL OR gibbonStaffAvailability.effectiveTo >= :effectiveDate)
                AND (
                    (gibbonStaffAvailability.availableFrom IS NULL AND gibbonStaffAvailability.availableTo IS NULL)
                    OR (gibbonStaffAvailability.availableFrom <= :startTime AND gibbonStaffAvailability.availableTo >= :endTime)
                )
                ORDER BY gibbonPerson.surname, gibbonPerson.preferredName";

        return $this->db()->select($sql, $data);
    }

    /**
     * Insert or update availability for a staff member.
     *
     * @param int $gibbonPersonID
     * @param string $dayOfWeek
     * @param array $data
     * @return int|false
     */
    public function upsertAvailability($gibbonPersonID, $dayOfWeek, array $data)
    {
        $data['gibbonPersonID'] = $gibbonPersonID;
        $data['dayOfWeek'] = $dayOfWeek;

        $existing = $this->getAvailabilityByDay($gibbonPersonID, $dayOfWeek, $data['effectiveFrom'] ?? null);

        if (!empty($existing)) {
            return $this->updateAvailability($existing['gibbonStaffAvailabilityID'], $data);
        }

        return $this->insertAvailability($data);
    }

    /**
     * Insert a new availability record.
     *
     * @param array $data
     * @return int|false
     */
    public function insertAvailability(array $data)
    {
        $query = $this
            ->newInsert()
            ->into('gibbonStaffAvailability')
            ->cols($data);

        return $this->runInsert($query);
    }

    /**
     * Update an availability record.
     *
     * @param int $gibbonStaffAvailabilityID
     * @param array $data
     * @return bool
     */
    public function updateAvailability($gibbonStaffAvailabilityID, array $data)
    {
        $query = $this
            ->newUpdate()
            ->table('gibbonStaffAvailability')
            ->cols($data)
            ->where('gibbonStaffAvailabilityID=:gibbonStaffAvailabilityID')
            ->bindValue('gibbonStaffAvailabilityID', $gibbonStaffAvailabilityID);

        return $this->runUpdate($query);
    }

    /**
     * Delete an availability record.
     *
     * @param int $gibbonStaffAvailabilityID
     * @return bool
     */
    public function deleteAvailability($gibbonStaffAvailabilityID)
    {
        $query = $this
            ->newDelete()
            ->from('gibbonStaffAvailability')
            ->where('gibbonStaffAvailabilityID=:gibbonStaffAvailabilityID')
            ->bindValue('gibbonStaffAvailabilityID', $gibbonStaffAvailabilityID);

        return $this->runDelete($query);
    }

    // ========================================
    // LEAVE TRACKING METHODS
    // ========================================

    /**
     * Query leave requests with criteria support.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonSchoolYearID
     * @return DataSet
     */
    public function queryLeaveRequests(QueryCriteria $criteria, $gibbonSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->from('gibbonStaffLeave')
            ->cols([
                'gibbonStaffLeave.gibbonStaffLeaveID',
                'gibbonStaffLeave.gibbonPersonID',
                'gibbonStaffLeave.leaveType',
                'gibbonStaffLeave.startDate',
                'gibbonStaffLeave.endDate',
                'gibbonStaffLeave.startTime',
                'gibbonStaffLeave.endTime',
                'gibbonStaffLeave.isPartialDay',
                'gibbonStaffLeave.totalDays',
                'gibbonStaffLeave.reason',
                'gibbonStaffLeave.status',
                'gibbonStaffLeave.approvedDate',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
                'approver.preferredName as approvedByName',
                'approver.surname as approvedBySurname',
            ])
            ->innerJoin('gibbonPerson', 'gibbonStaffLeave.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->leftJoin('gibbonPerson as approver', 'gibbonStaffLeave.approvedByID=approver.gibbonPersonID')
            ->where('gibbonStaffLeave.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID);

        $criteria->addFilterRules([
            'staff' => function ($query, $gibbonPersonID) {
                return $query
                    ->where('gibbonStaffLeave.gibbonPersonID=:gibbonPersonID')
                    ->bindValue('gibbonPersonID', $gibbonPersonID);
            },
            'leaveType' => function ($query, $leaveType) {
                return $query
                    ->where('gibbonStaffLeave.leaveType=:leaveType')
                    ->bindValue('leaveType', $leaveType);
            },
            'status' => function ($query, $status) {
                return $query
                    ->where('gibbonStaffLeave.status=:status')
                    ->bindValue('status', $status);
            },
            'dateFrom' => function ($query, $dateFrom) {
                return $query
                    ->where('gibbonStaffLeave.startDate>=:dateFrom')
                    ->bindValue('dateFrom', $dateFrom);
            },
            'dateTo' => function ($query, $dateTo) {
                return $query
                    ->where('gibbonStaffLeave.endDate<=:dateTo')
                    ->bindValue('dateTo', $dateTo);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Get leave request by ID.
     *
     * @param int $gibbonStaffLeaveID
     * @return array
     */
    public function getLeaveByID($gibbonStaffLeaveID)
    {
        $query = $this
            ->newSelect()
            ->from('gibbonStaffLeave')
            ->cols(['*'])
            ->where('gibbonStaffLeaveID=:gibbonStaffLeaveID')
            ->bindValue('gibbonStaffLeaveID', $gibbonStaffLeaveID);

        $result = $this->runSelect($query);
        return $result->isNotEmpty() ? $result->fetch() : [];
    }

    /**
     * Check if staff has leave on a specific date.
     *
     * @param int $gibbonPersonID
     * @param string $date
     * @return array|null
     */
    public function getLeaveByDate($gibbonPersonID, $date)
    {
        $data = ['gibbonPersonID' => $gibbonPersonID, 'date' => $date];
        $sql = "SELECT *
                FROM gibbonStaffLeave
                WHERE gibbonPersonID=:gibbonPersonID
                AND startDate <= :date
                AND endDate >= :date
                AND status='Approved'";

        return $this->db()->selectOne($sql, $data);
    }

    /**
     * Select staff on leave for a specific date.
     *
     * @param string $date
     * @return \Gibbon\Database\Result
     */
    public function selectStaffOnLeave($date)
    {
        $data = ['date' => $date];
        $sql = "SELECT gibbonStaffLeave.*, gibbonPerson.preferredName, gibbonPerson.surname, gibbonPerson.image_240
                FROM gibbonStaffLeave
                INNER JOIN gibbonPerson ON gibbonStaffLeave.gibbonPersonID=gibbonPerson.gibbonPersonID
                WHERE gibbonStaffLeave.startDate <= :date
                AND gibbonStaffLeave.endDate >= :date
                AND gibbonStaffLeave.status='Approved'
                ORDER BY gibbonPerson.surname, gibbonPerson.preferredName";

        return $this->db()->select($sql, $data);
    }

    /**
     * Get leave balance summary for a staff member.
     *
     * @param int $gibbonPersonID
     * @param int $gibbonSchoolYearID
     * @return array
     */
    public function getLeaveBalanceSummary($gibbonPersonID, $gibbonSchoolYearID)
    {
        $data = ['gibbonPersonID' => $gibbonPersonID, 'gibbonSchoolYearID' => $gibbonSchoolYearID];
        $sql = "SELECT
                    leaveType,
                    SUM(totalDays) as usedDays,
                    COUNT(*) as requestCount
                FROM gibbonStaffLeave
                WHERE gibbonPersonID=:gibbonPersonID
                AND gibbonSchoolYearID=:gibbonSchoolYearID
                AND status='Approved'
                GROUP BY leaveType";

        return $this->db()->select($sql, $data)->fetchAll();
    }

    /**
     * Insert a new leave request.
     *
     * @param array $data
     * @return int|false
     */
    public function insertLeave(array $data)
    {
        $query = $this
            ->newInsert()
            ->into('gibbonStaffLeave')
            ->cols($data);

        return $this->runInsert($query);
    }

    /**
     * Update a leave request.
     *
     * @param int $gibbonStaffLeaveID
     * @param array $data
     * @return bool
     */
    public function updateLeave($gibbonStaffLeaveID, array $data)
    {
        $query = $this
            ->newUpdate()
            ->table('gibbonStaffLeave')
            ->cols($data)
            ->where('gibbonStaffLeaveID=:gibbonStaffLeaveID')
            ->bindValue('gibbonStaffLeaveID', $gibbonStaffLeaveID);

        return $this->runUpdate($query);
    }

    /**
     * Approve a leave request.
     *
     * @param int $gibbonStaffLeaveID
     * @param int $approvedByID
     * @return bool
     */
    public function approveLeave($gibbonStaffLeaveID, $approvedByID)
    {
        return $this->updateLeave($gibbonStaffLeaveID, [
            'status' => 'Approved',
            'approvedByID' => $approvedByID,
            'approvedDate' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Deny a leave request.
     *
     * @param int $gibbonStaffLeaveID
     * @param int $approvedByID
     * @param string $denialReason
     * @return bool
     */
    public function denyLeave($gibbonStaffLeaveID, $approvedByID, $denialReason)
    {
        return $this->updateLeave($gibbonStaffLeaveID, [
            'status' => 'Denied',
            'approvedByID' => $approvedByID,
            'approvedDate' => date('Y-m-d H:i:s'),
            'denialReason' => $denialReason,
        ]);
    }

    /**
     * Cancel a leave request.
     *
     * @param int $gibbonStaffLeaveID
     * @return bool
     */
    public function cancelLeave($gibbonStaffLeaveID)
    {
        return $this->updateLeave($gibbonStaffLeaveID, [
            'status' => 'Cancelled',
        ]);
    }
}
