<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Gibbon Core) and Gibbon LAYA are trademarks of Gibbon Education Ltd.

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

namespace Gibbon\Module\CareTracking\Service;

use Gibbon\Module\CareTracking\Domain\AttendanceGateway;
use Gibbon\Module\CareTracking\Validator\AttendanceValidator;
use Gibbon\Domain\QueryCriteria;

/**
 * AttendanceService
 *
 * Service layer for attendance tracking business logic.
 * Provides a clean API for attendance operations by wrapping AttendanceGateway.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class AttendanceService
{
    /**
     * @var AttendanceGateway
     */
    protected $attendanceGateway;

    /**
     * @var AttendanceValidator
     */
    protected $validator;

    /**
     * Constructor.
     *
     * @param AttendanceGateway $attendanceGateway Attendance gateway
     * @param AttendanceValidator $validator Attendance validator
     */
    public function __construct(AttendanceGateway $attendanceGateway, AttendanceValidator $validator)
    {
        $this->attendanceGateway = $attendanceGateway;
        $this->validator = $validator;
    }

    /**
     * Query attendance records with criteria support.
     *
     * @param QueryCriteria $criteria Query criteria
     * @param int $gibbonSchoolYearID School year ID
     * @return \Gibbon\Domain\DataSet
     */
    public function queryAttendance(QueryCriteria $criteria, $gibbonSchoolYearID)
    {
        return $this->attendanceGateway->queryAttendance($criteria, $gibbonSchoolYearID);
    }

    /**
     * Query attendance records for a specific date.
     *
     * @param QueryCriteria $criteria Query criteria
     * @param int $gibbonSchoolYearID School year ID
     * @param string $date Date in Y-m-d format
     * @return \Gibbon\Domain\DataSet
     */
    public function queryAttendanceByDate(QueryCriteria $criteria, $gibbonSchoolYearID, $date)
    {
        return $this->attendanceGateway->queryAttendanceByDate($criteria, $gibbonSchoolYearID, $date);
    }

    /**
     * Query attendance history for a specific child.
     *
     * @param QueryCriteria $criteria Query criteria
     * @param int $gibbonPersonID Child person ID
     * @param int $gibbonSchoolYearID School year ID
     * @return \Gibbon\Domain\DataSet
     */
    public function queryAttendanceByPerson(QueryCriteria $criteria, $gibbonPersonID, $gibbonSchoolYearID)
    {
        return $this->attendanceGateway->queryAttendanceByPerson($criteria, $gibbonPersonID, $gibbonSchoolYearID);
    }

    /**
     * Get attendance record for a specific child on a specific date.
     *
     * @param int $gibbonPersonID Child person ID
     * @param string $date Date in Y-m-d format
     * @return array Attendance record or empty array if not found
     */
    public function getAttendanceByPersonAndDate($gibbonPersonID, $date)
    {
        return $this->attendanceGateway->getAttendanceByPersonAndDate($gibbonPersonID, $date);
    }

    /**
     * Get children who have checked in but not checked out on a specific date.
     *
     * @param int $gibbonSchoolYearID School year ID
     * @param string $date Date in Y-m-d format
     * @return \Gibbon\Database\Result
     */
    public function getChildrenCurrentlyCheckedIn($gibbonSchoolYearID, $date)
    {
        return $this->attendanceGateway->selectChildrenCurrentlyCheckedIn($gibbonSchoolYearID, $date);
    }

    /**
     * Get children who have not been checked in on a specific date.
     *
     * @param int $gibbonSchoolYearID School year ID
     * @param string $date Date in Y-m-d format
     * @return \Gibbon\Database\Result
     */
    public function getChildrenNotCheckedIn($gibbonSchoolYearID, $date)
    {
        return $this->attendanceGateway->selectChildrenNotCheckedIn($gibbonSchoolYearID, $date);
    }

    /**
     * Get daily attendance summary statistics for a specific date.
     *
     * @param int $gibbonSchoolYearID School year ID
     * @param string $date Date in Y-m-d format
     * @return array Summary statistics
     */
    public function getAttendanceSummaryByDate($gibbonSchoolYearID, $date)
    {
        return $this->attendanceGateway->getAttendanceSummaryByDate($gibbonSchoolYearID, $date);
    }

    /**
     * Get attendance statistics for a child over a date range.
     *
     * @param int $gibbonPersonID Child person ID
     * @param string $dateStart Start date in Y-m-d format
     * @param string $dateEnd End date in Y-m-d format
     * @return array Statistics
     */
    public function getAttendanceStatsByPersonAndDateRange($gibbonPersonID, $dateStart, $dateEnd)
    {
        return $this->attendanceGateway->getAttendanceStatsByPersonAndDateRange($gibbonPersonID, $dateStart, $dateEnd);
    }

    /**
     * Record check-in for a child.
     *
     * @param int $gibbonPersonID Child person ID
     * @param int $gibbonSchoolYearID School year ID
     * @param string $date Date in Y-m-d format
     * @param string $time Time in H:i:s format
     * @param int $checkInByID ID of person checking in
     * @param bool $lateArrival Whether arrival is late
     * @param string|null $notes Optional notes
     * @return array Result with success status and data
     */
    public function checkIn($gibbonPersonID, $gibbonSchoolYearID, $date, $time, $checkInByID, $lateArrival = false, $notes = null)
    {
        $validation = $this->validator->validateCheckIn([
            'gibbonPersonID' => $gibbonPersonID,
            'gibbonSchoolYearID' => $gibbonSchoolYearID,
            'date' => $date,
            'time' => $time,
            'checkInByID' => $checkInByID,
        ]);

        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors'],
            ];
        }

        $result = $this->attendanceGateway->checkIn($gibbonPersonID, $gibbonSchoolYearID, $date, $time, $checkInByID, $lateArrival, $notes);

        return [
            'success' => $result !== false,
            'id' => $result,
            'errors' => $result === false ? ['Failed to record check-in'] : [],
        ];
    }

    /**
     * Record check-out for a child.
     *
     * @param int $gibbonCareAttendanceID Attendance record ID
     * @param string $time Time in H:i:s format
     * @param int $checkOutByID ID of person checking out
     * @param bool $earlyDeparture Whether departure is early
     * @param string|null $pickupPersonName Name of pickup person
     * @param int|null $gibbonCareAuthorizedPickupID Authorized pickup person ID
     * @param bool $pickupVerified Whether pickup was verified
     * @param string|null $notes Optional notes
     * @return array Result with success status
     */
    public function checkOut($gibbonCareAttendanceID, $time, $checkOutByID, $earlyDeparture = false, $pickupPersonName = null, $gibbonCareAuthorizedPickupID = null, $pickupVerified = false, $notes = null)
    {
        $validation = $this->validator->validateCheckOut([
            'gibbonCareAttendanceID' => $gibbonCareAttendanceID,
            'time' => $time,
            'checkOutByID' => $checkOutByID,
        ]);

        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors'],
            ];
        }

        $result = $this->attendanceGateway->checkOut($gibbonCareAttendanceID, $time, $checkOutByID, $earlyDeparture, $pickupPersonName, $gibbonCareAuthorizedPickupID, $pickupVerified, $notes);

        return [
            'success' => $result,
            'errors' => !$result ? ['Failed to record check-out'] : [],
        ];
    }

    /**
     * Check if a child is currently checked in.
     *
     * @param int $gibbonPersonID Child person ID
     * @param string $date Date in Y-m-d format
     * @return bool True if checked in
     */
    public function isCheckedIn($gibbonPersonID, $date)
    {
        $attendance = $this->getAttendanceByPersonAndDate($gibbonPersonID, $date);

        if (empty($attendance)) {
            return false;
        }

        return !empty($attendance['checkInTime']) && empty($attendance['checkOutTime']);
    }

    /**
     * Calculate hours between check-in and check-out.
     *
     * @param array $attendance Attendance record with checkInTime and checkOutTime
     * @return float|null Hours or null if incomplete
     */
    public function calculateHours(array $attendance)
    {
        if (empty($attendance['checkInTime']) || empty($attendance['checkOutTime'])) {
            return null;
        }

        $checkIn = new \DateTime($attendance['checkInTime']);
        $checkOut = new \DateTime($attendance['checkOutTime']);
        $interval = $checkIn->diff($checkOut);

        return $interval->h + ($interval->i / 60) + ($interval->s / 3600);
    }
}
