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

use Gibbon\Module\CareTracking\Domain\IncidentGateway;
use Gibbon\Domain\QueryCriteria;

/**
 * IncidentService
 *
 * Service layer for incident tracking business logic.
 * Provides a clean API for incident operations by wrapping IncidentGateway.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class IncidentService
{
    /**
     * @var IncidentGateway
     */
    protected $incidentGateway;

    /**
     * Constructor.
     *
     * @param IncidentGateway $incidentGateway Incident gateway
     */
    public function __construct(IncidentGateway $incidentGateway)
    {
        $this->incidentGateway = $incidentGateway;
    }

    /**
     * Query incident records with criteria support.
     *
     * @param QueryCriteria $criteria Query criteria
     * @param int $gibbonSchoolYearID School year ID
     * @return \Gibbon\Domain\DataSet
     */
    public function queryIncidents(QueryCriteria $criteria, $gibbonSchoolYearID)
    {
        return $this->incidentGateway->queryIncidents($criteria, $gibbonSchoolYearID);
    }

    /**
     * Query incident records for a specific date.
     *
     * @param QueryCriteria $criteria Query criteria
     * @param int $gibbonSchoolYearID School year ID
     * @param string $date Date in Y-m-d format
     * @return \Gibbon\Domain\DataSet
     */
    public function queryIncidentsByDate(QueryCriteria $criteria, $gibbonSchoolYearID, $date)
    {
        return $this->incidentGateway->queryIncidentsByDate($criteria, $gibbonSchoolYearID, $date);
    }

    /**
     * Query incident history for a specific child.
     *
     * @param QueryCriteria $criteria Query criteria
     * @param int $gibbonPersonID Child person ID
     * @param int $gibbonSchoolYearID School year ID
     * @return \Gibbon\Domain\DataSet
     */
    public function queryIncidentsByPerson(QueryCriteria $criteria, $gibbonPersonID, $gibbonSchoolYearID)
    {
        return $this->incidentGateway->queryIncidentsByPerson($criteria, $gibbonPersonID, $gibbonSchoolYearID);
    }

    /**
     * Get incidents for a specific child on a specific date.
     *
     * @param int $gibbonPersonID Child person ID
     * @param string $date Date in Y-m-d format
     * @return \Gibbon\Database\Result
     */
    public function getIncidentsByPersonAndDate($gibbonPersonID, $date)
    {
        return $this->incidentGateway->selectIncidentsByPersonAndDate($gibbonPersonID, $date);
    }

    /**
     * Get incident summary statistics for a specific date.
     *
     * @param int $gibbonSchoolYearID School year ID
     * @param string $date Date in Y-m-d format
     * @return array Summary statistics
     */
    public function getIncidentSummaryByDate($gibbonSchoolYearID, $date)
    {
        return $this->incidentGateway->getIncidentSummaryByDate($gibbonSchoolYearID, $date);
    }

    /**
     * Get incident statistics for a child over a date range.
     *
     * @param int $gibbonPersonID Child person ID
     * @param string $dateStart Start date in Y-m-d format
     * @param string $dateEnd End date in Y-m-d format
     * @return array Statistics
     */
    public function getIncidentStatsByPersonAndDateRange($gibbonPersonID, $dateStart, $dateEnd)
    {
        return $this->incidentGateway->getIncidentStatsByPersonAndDateRange($gibbonPersonID, $dateStart, $dateEnd);
    }

    /**
     * Log an incident for a child.
     *
     * @param int $gibbonPersonID Child person ID
     * @param int $gibbonSchoolYearID School year ID
     * @param string $date Date in Y-m-d format
     * @param string $time Time in H:i:s format
     * @param string $type Incident type
     * @param string $severity Severity level
     * @param string $description Incident description
     * @param int $recordedByID ID of person recording
     * @param string|null $actionTaken Action taken
     * @return int|false Incident ID or false on failure
     */
    public function logIncident($gibbonPersonID, $gibbonSchoolYearID, $date, $time, $type, $severity, $description, $recordedByID, $actionTaken = null)
    {
        return $this->incidentGateway->logIncident($gibbonPersonID, $gibbonSchoolYearID, $date, $time, $type, $severity, $description, $recordedByID, $actionTaken);
    }

    /**
     * Mark parent as notified for an incident.
     *
     * @param int $gibbonCareIncidentID Incident ID
     * @return bool Success status
     */
    public function markParentNotified($gibbonCareIncidentID)
    {
        return $this->incidentGateway->markParentNotified($gibbonCareIncidentID);
    }

    /**
     * Mark parent acknowledgment for an incident.
     *
     * @param int $gibbonCareIncidentID Incident ID
     * @return bool Success status
     */
    public function markParentAcknowledged($gibbonCareIncidentID)
    {
        return $this->incidentGateway->markParentAcknowledged($gibbonCareIncidentID);
    }

    /**
     * Get incidents pending parent notification.
     *
     * @param int $gibbonSchoolYearID School year ID
     * @return \Gibbon\Database\Result
     */
    public function getIncidentsPendingNotification($gibbonSchoolYearID)
    {
        return $this->incidentGateway->selectIncidentsPendingNotification($gibbonSchoolYearID);
    }

    /**
     * Get incidents pending parent acknowledgment.
     *
     * @param int $gibbonSchoolYearID School year ID
     * @return \Gibbon\Database\Result
     */
    public function getIncidentsPendingAcknowledgment($gibbonSchoolYearID)
    {
        return $this->incidentGateway->selectIncidentsPendingAcknowledgment($gibbonSchoolYearID);
    }

    /**
     * Get critical or high severity incidents for a date range.
     *
     * @param int $gibbonSchoolYearID School year ID
     * @param string $dateStart Start date in Y-m-d format
     * @param string $dateEnd End date in Y-m-d format
     * @return \Gibbon\Database\Result
     */
    public function getSevereIncidents($gibbonSchoolYearID, $dateStart, $dateEnd)
    {
        return $this->incidentGateway->selectSevereIncidents($gibbonSchoolYearID, $dateStart, $dateEnd);
    }

    /**
     * Validate incident data before logging.
     *
     * @param array $data Incident data
     * @return array Array with 'valid' boolean and 'errors' array
     */
    public function validateIncidentData(array $data)
    {
        $errors = [];

        if (empty($data['gibbonPersonID'])) {
            $errors[] = 'Child ID is required';
        }

        if (empty($data['date'])) {
            $errors[] = 'Date is required';
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['date'])) {
            $errors[] = 'Date must be in Y-m-d format';
        }

        if (empty($data['time'])) {
            $errors[] = 'Time is required';
        } elseif (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $data['time'])) {
            $errors[] = 'Time must be in H:i or H:i:s format';
        }

        $validTypes = ['Minor Injury', 'Major Injury', 'Illness', 'Behavioral', 'Other'];
        if (empty($data['type']) || !in_array($data['type'], $validTypes)) {
            $errors[] = 'Valid incident type is required';
        }

        $validSeverities = ['Low', 'Medium', 'High', 'Critical'];
        if (empty($data['severity']) || !in_array($data['severity'], $validSeverities)) {
            $errors[] = 'Valid severity level is required';
        }

        if (empty($data['description'])) {
            $errors[] = 'Description is required';
        }

        if (empty($data['recordedByID'])) {
            $errors[] = 'Recorded by ID is required';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Determine if an incident requires parent notification based on severity.
     *
     * @param string $severity Severity level
     * @return bool True if notification is required
     */
    public function requiresParentNotification($severity)
    {
        return in_array($severity, ['High', 'Critical']);
    }

    /**
     * Determine if an incident is severe (High or Critical).
     *
     * @param string $severity Severity level
     * @return bool True if severe
     */
    public function isSevere($severity)
    {
        return in_array($severity, ['High', 'Critical']);
    }
}
