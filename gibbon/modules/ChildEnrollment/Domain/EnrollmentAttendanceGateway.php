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

namespace Gibbon\Module\ChildEnrollment\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\QueryableGateway;

/**
 * Enrollment Attendance Gateway
 *
 * Handles weekly attendance pattern information for child enrollment forms.
 * Each enrollment form has one attendance record (one-to-one relationship).
 * This stores the expected/planned attendance schedule, not actual daily attendance.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class EnrollmentAttendanceGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonChildEnrollmentAttendance';
    private static $primaryKey = 'gibbonChildEnrollmentAttendanceID';

    private static $searchableColumns = [
        'gibbonChildEnrollmentAttendance.notes',
    ];

    /**
     * Day/period combinations for the weekly schedule.
     */
    private static $dayPeriods = [
        'mondayAm', 'mondayPm',
        'tuesdayAm', 'tuesdayPm',
        'wednesdayAm', 'wednesdayPm',
        'thursdayAm', 'thursdayPm',
        'fridayAm', 'fridayPm',
        'saturdayAm', 'saturdayPm',
        'sundayAm', 'sundayPm',
    ];

    /**
     * Query attendance pattern records with criteria support.
     *
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryAttendancePatterns(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonChildEnrollmentAttendance.gibbonChildEnrollmentAttendanceID',
                'gibbonChildEnrollmentAttendance.gibbonChildEnrollmentFormID',
                'gibbonChildEnrollmentAttendance.mondayAm',
                'gibbonChildEnrollmentAttendance.mondayPm',
                'gibbonChildEnrollmentAttendance.tuesdayAm',
                'gibbonChildEnrollmentAttendance.tuesdayPm',
                'gibbonChildEnrollmentAttendance.wednesdayAm',
                'gibbonChildEnrollmentAttendance.wednesdayPm',
                'gibbonChildEnrollmentAttendance.thursdayAm',
                'gibbonChildEnrollmentAttendance.thursdayPm',
                'gibbonChildEnrollmentAttendance.fridayAm',
                'gibbonChildEnrollmentAttendance.fridayPm',
                'gibbonChildEnrollmentAttendance.saturdayAm',
                'gibbonChildEnrollmentAttendance.saturdayPm',
                'gibbonChildEnrollmentAttendance.sundayAm',
                'gibbonChildEnrollmentAttendance.sundayPm',
                'gibbonChildEnrollmentAttendance.expectedHoursPerWeek',
                'gibbonChildEnrollmentAttendance.expectedArrivalTime',
                'gibbonChildEnrollmentAttendance.expectedDepartureTime',
                'gibbonChildEnrollmentAttendance.notes',
                'gibbonChildEnrollmentAttendance.timestampCreated',
                'gibbonChildEnrollmentAttendance.timestampModified',
                'gibbonChildEnrollmentForm.formNumber',
                'gibbonChildEnrollmentForm.childFirstName',
                'gibbonChildEnrollmentForm.childLastName',
                'gibbonChildEnrollmentForm.status as formStatus',
            ])
            ->innerJoin('gibbonChildEnrollmentForm', 'gibbonChildEnrollmentAttendance.gibbonChildEnrollmentFormID=gibbonChildEnrollmentForm.gibbonChildEnrollmentFormID');

        $criteria->addFilterRules([
            'hasWeekendAttendance' => function ($query, $value) {
                if ($value === 'Y') {
                    return $query
                        ->where('(gibbonChildEnrollmentAttendance.saturdayAm=\'Y\' OR gibbonChildEnrollmentAttendance.saturdayPm=\'Y\' OR gibbonChildEnrollmentAttendance.sundayAm=\'Y\' OR gibbonChildEnrollmentAttendance.sundayPm=\'Y\')');
                }
                return $query
                    ->where('(gibbonChildEnrollmentAttendance.saturdayAm=\'N\' AND gibbonChildEnrollmentAttendance.saturdayPm=\'N\' AND gibbonChildEnrollmentAttendance.sundayAm=\'N\' AND gibbonChildEnrollmentAttendance.sundayPm=\'N\')');
            },
            'isFullTime' => function ($query, $value) {
                // Full-time: 5 days, both AM and PM
                if ($value === 'Y') {
                    return $query
                        ->where('gibbonChildEnrollmentAttendance.mondayAm=\'Y\' AND gibbonChildEnrollmentAttendance.mondayPm=\'Y\'')
                        ->where('gibbonChildEnrollmentAttendance.tuesdayAm=\'Y\' AND gibbonChildEnrollmentAttendance.tuesdayPm=\'Y\'')
                        ->where('gibbonChildEnrollmentAttendance.wednesdayAm=\'Y\' AND gibbonChildEnrollmentAttendance.wednesdayPm=\'Y\'')
                        ->where('gibbonChildEnrollmentAttendance.thursdayAm=\'Y\' AND gibbonChildEnrollmentAttendance.thursdayPm=\'Y\'')
                        ->where('gibbonChildEnrollmentAttendance.fridayAm=\'Y\' AND gibbonChildEnrollmentAttendance.fridayPm=\'Y\'');
                }
                return $query;
            },
            'day' => function ($query, $day) {
                $dayLower = strtolower($day);
                return $query
                    ->where("(gibbonChildEnrollmentAttendance.{$dayLower}Am='Y' OR gibbonChildEnrollmentAttendance.{$dayLower}Pm='Y')");
            },
            'minHoursPerWeek' => function ($query, $hours) {
                return $query
                    ->where('gibbonChildEnrollmentAttendance.expectedHoursPerWeek >= :minHours')
                    ->bindValue('minHours', $hours);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Get attendance pattern for an enrollment form.
     *
     * @param int $gibbonChildEnrollmentFormID
     * @return array|false
     */
    public function getAttendanceByForm($gibbonChildEnrollmentFormID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols(['*'])
            ->where('gibbonChildEnrollmentFormID=:gibbonChildEnrollmentFormID')
            ->bindValue('gibbonChildEnrollmentFormID', $gibbonChildEnrollmentFormID);

        $result = $this->runSelect($query);
        return $result->isNotEmpty() ? $result->fetch() : false;
    }

    /**
     * Get a specific attendance pattern record by ID.
     *
     * @param int $gibbonChildEnrollmentAttendanceID
     * @return array|false
     */
    public function getAttendanceByID($gibbonChildEnrollmentAttendanceID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols(['*'])
            ->where('gibbonChildEnrollmentAttendanceID=:gibbonChildEnrollmentAttendanceID')
            ->bindValue('gibbonChildEnrollmentAttendanceID', $gibbonChildEnrollmentAttendanceID);

        $result = $this->runSelect($query);
        return $result->isNotEmpty() ? $result->fetch() : false;
    }

    /**
     * Check if an attendance pattern record exists for an enrollment form.
     *
     * @param int $gibbonChildEnrollmentFormID
     * @return bool
     */
    public function attendanceRecordExists($gibbonChildEnrollmentFormID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols(['gibbonChildEnrollmentAttendanceID'])
            ->where('gibbonChildEnrollmentFormID=:gibbonChildEnrollmentFormID')
            ->bindValue('gibbonChildEnrollmentFormID', $gibbonChildEnrollmentFormID);

        $result = $this->runSelect($query);
        return $result->isNotEmpty();
    }

    /**
     * Insert or update attendance pattern for an enrollment form.
     *
     * @param int $gibbonChildEnrollmentFormID
     * @param array $data
     * @return int|false Returns the attendance record ID on success
     */
    public function saveAttendance($gibbonChildEnrollmentFormID, array $data)
    {
        $existing = $this->getAttendanceByForm($gibbonChildEnrollmentFormID);

        if ($existing) {
            // Update existing record
            return $this->update($existing['gibbonChildEnrollmentAttendanceID'], $data)
                ? $existing['gibbonChildEnrollmentAttendanceID']
                : false;
        }

        // Create new record
        $data['gibbonChildEnrollmentFormID'] = $gibbonChildEnrollmentFormID;
        return $this->insert($data);
    }

    /**
     * Update attendance pattern information.
     *
     * @param int $gibbonChildEnrollmentAttendanceID
     * @param array $data
     * @return bool
     */
    public function updateAttendance($gibbonChildEnrollmentAttendanceID, array $data)
    {
        return $this->update($gibbonChildEnrollmentAttendanceID, $data);
    }

    /**
     * Delete attendance pattern information for an enrollment form.
     *
     * @param int $gibbonChildEnrollmentFormID
     * @return bool
     */
    public function deleteAttendanceByForm($gibbonChildEnrollmentFormID)
    {
        $data = ['gibbonChildEnrollmentFormID' => $gibbonChildEnrollmentFormID];
        $sql = "DELETE FROM gibbonChildEnrollmentAttendance
                WHERE gibbonChildEnrollmentFormID=:gibbonChildEnrollmentFormID";

        return $this->db()->statement($sql, $data) !== false;
    }

    /**
     * Get the weekly schedule as a structured array.
     *
     * @param int $gibbonChildEnrollmentFormID
     * @return array|null
     */
    public function getWeeklySchedule($gibbonChildEnrollmentFormID)
    {
        $attendance = $this->getAttendanceByForm($gibbonChildEnrollmentFormID);
        if (!$attendance) {
            return null;
        }

        return [
            'monday' => [
                'am' => $attendance['mondayAm'] === 'Y',
                'pm' => $attendance['mondayPm'] === 'Y',
            ],
            'tuesday' => [
                'am' => $attendance['tuesdayAm'] === 'Y',
                'pm' => $attendance['tuesdayPm'] === 'Y',
            ],
            'wednesday' => [
                'am' => $attendance['wednesdayAm'] === 'Y',
                'pm' => $attendance['wednesdayPm'] === 'Y',
            ],
            'thursday' => [
                'am' => $attendance['thursdayAm'] === 'Y',
                'pm' => $attendance['thursdayPm'] === 'Y',
            ],
            'friday' => [
                'am' => $attendance['fridayAm'] === 'Y',
                'pm' => $attendance['fridayPm'] === 'Y',
            ],
            'saturday' => [
                'am' => $attendance['saturdayAm'] === 'Y',
                'pm' => $attendance['saturdayPm'] === 'Y',
            ],
            'sunday' => [
                'am' => $attendance['sundayAm'] === 'Y',
                'pm' => $attendance['sundayPm'] === 'Y',
            ],
        ];
    }

    /**
     * Get expected times for an enrollment form.
     *
     * @param int $gibbonChildEnrollmentFormID
     * @return array|null
     */
    public function getExpectedTimes($gibbonChildEnrollmentFormID)
    {
        $attendance = $this->getAttendanceByForm($gibbonChildEnrollmentFormID);
        if (!$attendance) {
            return null;
        }

        return [
            'arrivalTime' => $attendance['expectedArrivalTime'],
            'departureTime' => $attendance['expectedDepartureTime'],
            'hoursPerWeek' => $attendance['expectedHoursPerWeek'],
        ];
    }

    /**
     * Count the number of scheduled periods (AM/PM sessions) per week.
     *
     * @param int $gibbonChildEnrollmentFormID
     * @return int
     */
    public function countScheduledPeriods($gibbonChildEnrollmentFormID)
    {
        $attendance = $this->getAttendanceByForm($gibbonChildEnrollmentFormID);
        if (!$attendance) {
            return 0;
        }

        $count = 0;
        foreach (self::$dayPeriods as $period) {
            if ($attendance[$period] === 'Y') {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Count the number of scheduled days per week.
     *
     * @param int $gibbonChildEnrollmentFormID
     * @return int
     */
    public function countScheduledDays($gibbonChildEnrollmentFormID)
    {
        $attendance = $this->getAttendanceByForm($gibbonChildEnrollmentFormID);
        if (!$attendance) {
            return 0;
        }

        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $count = 0;

        foreach ($days as $day) {
            if ($attendance[$day . 'Am'] === 'Y' || $attendance[$day . 'Pm'] === 'Y') {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Check if child is scheduled for a specific day.
     *
     * @param int $gibbonChildEnrollmentFormID
     * @param string $dayName (monday, tuesday, etc.)
     * @return bool
     */
    public function isScheduledForDay($gibbonChildEnrollmentFormID, $dayName)
    {
        $attendance = $this->getAttendanceByForm($gibbonChildEnrollmentFormID);
        if (!$attendance) {
            return false;
        }

        $dayLower = strtolower($dayName);
        return $attendance[$dayLower . 'Am'] === 'Y' || $attendance[$dayLower . 'Pm'] === 'Y';
    }

    /**
     * Check if child is scheduled for a specific period (AM or PM).
     *
     * @param int $gibbonChildEnrollmentFormID
     * @param string $dayName (monday, tuesday, etc.)
     * @param string $period (am or pm)
     * @return bool
     */
    public function isScheduledForPeriod($gibbonChildEnrollmentFormID, $dayName, $period)
    {
        $attendance = $this->getAttendanceByForm($gibbonChildEnrollmentFormID);
        if (!$attendance) {
            return false;
        }

        $field = strtolower($dayName) . ucfirst(strtolower($period));
        return isset($attendance[$field]) && $attendance[$field] === 'Y';
    }

    /**
     * Check if child has weekend attendance.
     *
     * @param int $gibbonChildEnrollmentFormID
     * @return bool
     */
    public function hasWeekendAttendance($gibbonChildEnrollmentFormID)
    {
        $attendance = $this->getAttendanceByForm($gibbonChildEnrollmentFormID);
        if (!$attendance) {
            return false;
        }

        return $attendance['saturdayAm'] === 'Y' ||
               $attendance['saturdayPm'] === 'Y' ||
               $attendance['sundayAm'] === 'Y' ||
               $attendance['sundayPm'] === 'Y';
    }

    /**
     * Check if child is enrolled full-time (5 full days, Monday-Friday).
     *
     * @param int $gibbonChildEnrollmentFormID
     * @return bool
     */
    public function isFullTime($gibbonChildEnrollmentFormID)
    {
        $attendance = $this->getAttendanceByForm($gibbonChildEnrollmentFormID);
        if (!$attendance) {
            return false;
        }

        $weekdays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
        foreach ($weekdays as $day) {
            if ($attendance[$day . 'Am'] !== 'Y' || $attendance[$day . 'Pm'] !== 'Y') {
                return false;
            }
        }

        return true;
    }

    /**
     * Query children scheduled for a specific day.
     *
     * @param QueryCriteria $criteria
     * @param string $dayName
     * @return DataSet
     */
    public function queryChildrenByDay(QueryCriteria $criteria, $dayName)
    {
        $dayLower = strtolower($dayName);

        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonChildEnrollmentAttendance.gibbonChildEnrollmentAttendanceID',
                'gibbonChildEnrollmentAttendance.gibbonChildEnrollmentFormID',
                "gibbonChildEnrollmentAttendance.{$dayLower}Am",
                "gibbonChildEnrollmentAttendance.{$dayLower}Pm",
                'gibbonChildEnrollmentAttendance.expectedArrivalTime',
                'gibbonChildEnrollmentAttendance.expectedDepartureTime',
                'gibbonChildEnrollmentForm.formNumber',
                'gibbonChildEnrollmentForm.childFirstName',
                'gibbonChildEnrollmentForm.childLastName',
                'gibbonChildEnrollmentForm.status as formStatus',
            ])
            ->innerJoin('gibbonChildEnrollmentForm', 'gibbonChildEnrollmentAttendance.gibbonChildEnrollmentFormID=gibbonChildEnrollmentForm.gibbonChildEnrollmentFormID')
            ->where("(gibbonChildEnrollmentAttendance.{$dayLower}Am='Y' OR gibbonChildEnrollmentAttendance.{$dayLower}Pm='Y')")
            ->where('gibbonChildEnrollmentForm.status IN (\'Submitted\', \'Approved\')');

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query full-time children.
     *
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryFullTimeChildren(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonChildEnrollmentAttendance.gibbonChildEnrollmentAttendanceID',
                'gibbonChildEnrollmentAttendance.gibbonChildEnrollmentFormID',
                'gibbonChildEnrollmentAttendance.expectedHoursPerWeek',
                'gibbonChildEnrollmentAttendance.expectedArrivalTime',
                'gibbonChildEnrollmentAttendance.expectedDepartureTime',
                'gibbonChildEnrollmentForm.formNumber',
                'gibbonChildEnrollmentForm.childFirstName',
                'gibbonChildEnrollmentForm.childLastName',
                'gibbonChildEnrollmentForm.status as formStatus',
            ])
            ->innerJoin('gibbonChildEnrollmentForm', 'gibbonChildEnrollmentAttendance.gibbonChildEnrollmentFormID=gibbonChildEnrollmentForm.gibbonChildEnrollmentFormID')
            ->where('gibbonChildEnrollmentAttendance.mondayAm=\'Y\' AND gibbonChildEnrollmentAttendance.mondayPm=\'Y\'')
            ->where('gibbonChildEnrollmentAttendance.tuesdayAm=\'Y\' AND gibbonChildEnrollmentAttendance.tuesdayPm=\'Y\'')
            ->where('gibbonChildEnrollmentAttendance.wednesdayAm=\'Y\' AND gibbonChildEnrollmentAttendance.wednesdayPm=\'Y\'')
            ->where('gibbonChildEnrollmentAttendance.thursdayAm=\'Y\' AND gibbonChildEnrollmentAttendance.thursdayPm=\'Y\'')
            ->where('gibbonChildEnrollmentAttendance.fridayAm=\'Y\' AND gibbonChildEnrollmentAttendance.fridayPm=\'Y\'')
            ->where('gibbonChildEnrollmentForm.status IN (\'Submitted\', \'Approved\')');

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query children with weekend attendance.
     *
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryChildrenWithWeekendAttendance(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonChildEnrollmentAttendance.gibbonChildEnrollmentAttendanceID',
                'gibbonChildEnrollmentAttendance.gibbonChildEnrollmentFormID',
                'gibbonChildEnrollmentAttendance.saturdayAm',
                'gibbonChildEnrollmentAttendance.saturdayPm',
                'gibbonChildEnrollmentAttendance.sundayAm',
                'gibbonChildEnrollmentAttendance.sundayPm',
                'gibbonChildEnrollmentForm.formNumber',
                'gibbonChildEnrollmentForm.childFirstName',
                'gibbonChildEnrollmentForm.childLastName',
                'gibbonChildEnrollmentForm.status as formStatus',
            ])
            ->innerJoin('gibbonChildEnrollmentForm', 'gibbonChildEnrollmentAttendance.gibbonChildEnrollmentFormID=gibbonChildEnrollmentForm.gibbonChildEnrollmentFormID')
            ->where('(gibbonChildEnrollmentAttendance.saturdayAm=\'Y\' OR gibbonChildEnrollmentAttendance.saturdayPm=\'Y\' OR gibbonChildEnrollmentAttendance.sundayAm=\'Y\' OR gibbonChildEnrollmentAttendance.sundayPm=\'Y\')')
            ->where('gibbonChildEnrollmentForm.status IN (\'Submitted\', \'Approved\')');

        return $this->runQuery($query, $criteria);
    }

    /**
     * Get attendance pattern info for display purposes (formatted).
     *
     * @param int $gibbonChildEnrollmentFormID
     * @return array|null Formatted attendance information
     */
    public function getAttendanceInfo($gibbonChildEnrollmentFormID)
    {
        $attendance = $this->getAttendanceByForm($gibbonChildEnrollmentFormID);
        if (!$attendance) {
            return null;
        }

        return [
            'schedule' => $this->getWeeklySchedule($gibbonChildEnrollmentFormID),
            'times' => $this->getExpectedTimes($gibbonChildEnrollmentFormID),
            'scheduledDays' => $this->countScheduledDays($gibbonChildEnrollmentFormID),
            'scheduledPeriods' => $this->countScheduledPeriods($gibbonChildEnrollmentFormID),
            'isFullTime' => $this->isFullTime($gibbonChildEnrollmentFormID),
            'hasWeekendAttendance' => $this->hasWeekendAttendance($gibbonChildEnrollmentFormID),
            'notes' => $attendance['notes'],
        ];
    }

    /**
     * Validate attendance pattern data before insert/update.
     *
     * @param array $data
     * @return array Array of validation errors (empty if valid)
     */
    public function validateAttendanceData(array $data)
    {
        $errors = [];

        // Validate at least one day/period is selected
        $hasAnyPeriod = false;
        foreach (self::$dayPeriods as $period) {
            if (isset($data[$period]) && $data[$period] === 'Y') {
                $hasAnyPeriod = true;
                break;
            }
        }

        if (!$hasAnyPeriod) {
            $errors[] = 'At least one attendance period must be selected.';
        }

        // Validate expected hours per week
        if (isset($data['expectedHoursPerWeek'])) {
            $hours = (float) $data['expectedHoursPerWeek'];
            if ($hours < 0) {
                $errors[] = 'Expected hours per week cannot be negative.';
            }
            if ($hours > 168) { // More than hours in a week
                $errors[] = 'Expected hours per week cannot exceed 168.';
            }
        }

        // Validate time format (HH:MM:SS)
        $timeFields = ['expectedArrivalTime', 'expectedDepartureTime'];
        foreach ($timeFields as $field) {
            if (isset($data[$field]) && !empty($data[$field])) {
                if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/', $data[$field])) {
                    $errors[] = ucfirst(str_replace('expected', 'Expected ', str_replace('Time', ' time', $field))) . ' must be in HH:MM format.';
                }
            }
        }

        // Validate departure is after arrival if both are set
        if (!empty($data['expectedArrivalTime']) && !empty($data['expectedDepartureTime'])) {
            if ($data['expectedArrivalTime'] >= $data['expectedDepartureTime']) {
                $errors[] = 'Expected departure time must be after arrival time.';
            }
        }

        // Validate notes length
        if (isset($data['notes']) && strlen($data['notes']) > 65535) {
            $errors[] = 'Notes exceed maximum length.';
        }

        return $errors;
    }

    /**
     * Get attendance summary statistics for reporting.
     *
     * @return array
     */
    public function getAttendanceStatistics()
    {
        $sql = "SELECT
                    COUNT(*) as totalRecords,
                    SUM(CASE WHEN mondayAm='Y' AND mondayPm='Y'
                        AND tuesdayAm='Y' AND tuesdayPm='Y'
                        AND wednesdayAm='Y' AND wednesdayPm='Y'
                        AND thursdayAm='Y' AND thursdayPm='Y'
                        AND fridayAm='Y' AND fridayPm='Y' THEN 1 ELSE 0 END) as fullTimeCount,
                    SUM(CASE WHEN saturdayAm='Y' OR saturdayPm='Y'
                        OR sundayAm='Y' OR sundayPm='Y' THEN 1 ELSE 0 END) as weekendAttendanceCount,
                    AVG(expectedHoursPerWeek) as avgHoursPerWeek
                FROM gibbonChildEnrollmentAttendance a
                INNER JOIN gibbonChildEnrollmentForm f
                    ON a.gibbonChildEnrollmentFormID = f.gibbonChildEnrollmentFormID
                WHERE f.status IN ('Submitted', 'Approved')";

        return $this->db()->selectOne($sql) ?: [
            'totalRecords' => 0,
            'fullTimeCount' => 0,
            'weekendAttendanceCount' => 0,
            'avgHoursPerWeek' => 0,
        ];
    }

    /**
     * Get a daily enrollment count summary (how many children per day).
     *
     * @return array
     */
    public function getDailyEnrollmentSummary()
    {
        $sql = "SELECT
                    SUM(CASE WHEN mondayAm='Y' OR mondayPm='Y' THEN 1 ELSE 0 END) as monday,
                    SUM(CASE WHEN tuesdayAm='Y' OR tuesdayPm='Y' THEN 1 ELSE 0 END) as tuesday,
                    SUM(CASE WHEN wednesdayAm='Y' OR wednesdayPm='Y' THEN 1 ELSE 0 END) as wednesday,
                    SUM(CASE WHEN thursdayAm='Y' OR thursdayPm='Y' THEN 1 ELSE 0 END) as thursday,
                    SUM(CASE WHEN fridayAm='Y' OR fridayPm='Y' THEN 1 ELSE 0 END) as friday,
                    SUM(CASE WHEN saturdayAm='Y' OR saturdayPm='Y' THEN 1 ELSE 0 END) as saturday,
                    SUM(CASE WHEN sundayAm='Y' OR sundayPm='Y' THEN 1 ELSE 0 END) as sunday
                FROM gibbonChildEnrollmentAttendance a
                INNER JOIN gibbonChildEnrollmentForm f
                    ON a.gibbonChildEnrollmentFormID = f.gibbonChildEnrollmentFormID
                WHERE f.status IN ('Submitted', 'Approved')";

        return $this->db()->selectOne($sql) ?: [
            'monday' => 0,
            'tuesday' => 0,
            'wednesday' => 0,
            'thursday' => 0,
            'friday' => 0,
            'saturday' => 0,
            'sunday' => 0,
        ];
    }

    /**
     * Get a period-by-period enrollment count (AM/PM for each day).
     *
     * @return array
     */
    public function getPeriodEnrollmentSummary()
    {
        $sql = "SELECT
                    SUM(CASE WHEN mondayAm='Y' THEN 1 ELSE 0 END) as mondayAm,
                    SUM(CASE WHEN mondayPm='Y' THEN 1 ELSE 0 END) as mondayPm,
                    SUM(CASE WHEN tuesdayAm='Y' THEN 1 ELSE 0 END) as tuesdayAm,
                    SUM(CASE WHEN tuesdayPm='Y' THEN 1 ELSE 0 END) as tuesdayPm,
                    SUM(CASE WHEN wednesdayAm='Y' THEN 1 ELSE 0 END) as wednesdayAm,
                    SUM(CASE WHEN wednesdayPm='Y' THEN 1 ELSE 0 END) as wednesdayPm,
                    SUM(CASE WHEN thursdayAm='Y' THEN 1 ELSE 0 END) as thursdayAm,
                    SUM(CASE WHEN thursdayPm='Y' THEN 1 ELSE 0 END) as thursdayPm,
                    SUM(CASE WHEN fridayAm='Y' THEN 1 ELSE 0 END) as fridayAm,
                    SUM(CASE WHEN fridayPm='Y' THEN 1 ELSE 0 END) as fridayPm,
                    SUM(CASE WHEN saturdayAm='Y' THEN 1 ELSE 0 END) as saturdayAm,
                    SUM(CASE WHEN saturdayPm='Y' THEN 1 ELSE 0 END) as saturdayPm,
                    SUM(CASE WHEN sundayAm='Y' THEN 1 ELSE 0 END) as sundayAm,
                    SUM(CASE WHEN sundayPm='Y' THEN 1 ELSE 0 END) as sundayPm
                FROM gibbonChildEnrollmentAttendance a
                INNER JOIN gibbonChildEnrollmentForm f
                    ON a.gibbonChildEnrollmentFormID = f.gibbonChildEnrollmentFormID
                WHERE f.status IN ('Submitted', 'Approved')";

        $result = $this->db()->selectOne($sql);
        if (!$result) {
            $result = [];
            foreach (self::$dayPeriods as $period) {
                $result[$period] = 0;
            }
        }

        return $result;
    }

    /**
     * Get a human-readable summary of the weekly schedule.
     *
     * @param int $gibbonChildEnrollmentFormID
     * @return string
     */
    public function getScheduleSummaryText($gibbonChildEnrollmentFormID)
    {
        $attendance = $this->getAttendanceByForm($gibbonChildEnrollmentFormID);
        if (!$attendance) {
            return 'No schedule defined';
        }

        $days = [];
        $dayNames = [
            'monday' => 'Mon',
            'tuesday' => 'Tue',
            'wednesday' => 'Wed',
            'thursday' => 'Thu',
            'friday' => 'Fri',
            'saturday' => 'Sat',
            'sunday' => 'Sun',
        ];

        foreach ($dayNames as $day => $abbrev) {
            $hasAm = $attendance[$day . 'Am'] === 'Y';
            $hasPm = $attendance[$day . 'Pm'] === 'Y';

            if ($hasAm && $hasPm) {
                $days[] = $abbrev . ' (Full)';
            } elseif ($hasAm) {
                $days[] = $abbrev . ' (AM)';
            } elseif ($hasPm) {
                $days[] = $abbrev . ' (PM)';
            }
        }

        if (empty($days)) {
            return 'No days scheduled';
        }

        return implode(', ', $days);
    }
}
