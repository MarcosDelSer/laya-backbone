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

namespace Gibbon\Module\CareTracking\Validator;

/**
 * AttendanceValidator
 *
 * Validates attendance-related data for check-in and check-out operations.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class AttendanceValidator
{
    /**
     * Validate check-in data.
     *
     * @param array $data Check-in data
     * @return array Array with 'valid' boolean and 'errors' array
     */
    public function validateCheckIn(array $data)
    {
        $errors = [];

        if (empty($data['gibbonPersonID'])) {
            $errors[] = 'Child ID is required';
        }

        if (empty($data['gibbonSchoolYearID'])) {
            $errors[] = 'School year ID is required';
        }

        if (empty($data['date'])) {
            $errors[] = 'Date is required';
        } elseif (!$this->isValidDate($data['date'])) {
            $errors[] = 'Date must be in Y-m-d format';
        }

        if (empty($data['time'])) {
            $errors[] = 'Time is required';
        } elseif (!$this->isValidTime($data['time'])) {
            $errors[] = 'Time must be in H:i or H:i:s format';
        }

        if (empty($data['checkInByID'])) {
            $errors[] = 'Check-in by ID is required';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Validate check-out data.
     *
     * @param array $data Check-out data
     * @return array Array with 'valid' boolean and 'errors' array
     */
    public function validateCheckOut(array $data)
    {
        $errors = [];

        if (empty($data['gibbonCareAttendanceID'])) {
            $errors[] = 'Attendance record ID is required';
        }

        if (empty($data['time'])) {
            $errors[] = 'Time is required';
        } elseif (!$this->isValidTime($data['time'])) {
            $errors[] = 'Time must be in H:i or H:i:s format';
        }

        if (empty($data['checkOutByID'])) {
            $errors[] = 'Check-out by ID is required';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Validate date format.
     *
     * @param string $date Date string
     * @return bool True if valid
     */
    protected function isValidDate($date)
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }

        $parts = explode('-', $date);
        return checkdate((int)$parts[1], (int)$parts[2], (int)$parts[0]);
    }

    /**
     * Validate time format.
     *
     * @param string $time Time string
     * @return bool True if valid
     */
    protected function isValidTime($time)
    {
        return preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $time);
    }

    /**
     * Validate that check-out time is after check-in time.
     *
     * @param string $checkInTime Check-in time
     * @param string $checkOutTime Check-out time
     * @return bool True if valid
     */
    public function isCheckOutAfterCheckIn($checkInTime, $checkOutTime)
    {
        return strtotime($checkOutTime) > strtotime($checkInTime);
    }

    /**
     * Validate pickup person data.
     *
     * @param array $data Pickup data
     * @return array Array with 'valid' boolean and 'errors' array
     */
    public function validatePickupPerson(array $data)
    {
        $errors = [];

        if (empty($data['pickupPersonName'])) {
            $errors[] = 'Pickup person name is required';
        }

        if (!isset($data['pickupVerified'])) {
            $errors[] = 'Pickup verification status is required';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
}
