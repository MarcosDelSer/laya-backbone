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
 * Care Tracking Incident Gateway
 *
 * Handles incident reporting and tracking for children in childcare settings.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class IncidentGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonCareIncident';
    private static $primaryKey = 'gibbonCareIncidentID';

    private static $searchableColumns = ['gibbonPerson.preferredName', 'gibbonPerson.surname', 'gibbonCareIncident.description', 'gibbonCareIncident.actionTaken'];

    /**
     * Query incident records with criteria support.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonSchoolYearID
     * @return DataSet
     */
    public function queryIncidents(QueryCriteria $criteria, $gibbonSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonCareIncident.gibbonCareIncidentID',
                'gibbonCareIncident.gibbonPersonID',
                'gibbonCareIncident.date',
                'gibbonCareIncident.time',
                'gibbonCareIncident.type',
                'gibbonCareIncident.severity',
                'gibbonCareIncident.description',
                'gibbonCareIncident.actionTaken',
                'gibbonCareIncident.parentNotified',
                'gibbonCareIncident.parentNotifiedTime',
                'gibbonCareIncident.parentAcknowledged',
                'gibbonCareIncident.parentAcknowledgedTime',
                'gibbonCareIncident.timestampCreated',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
                'recordedBy.preferredName as recordedByName',
                'recordedBy.surname as recordedBySurname',
            ])
            ->innerJoin('gibbonPerson', 'gibbonCareIncident.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->leftJoin('gibbonPerson as recordedBy', 'gibbonCareIncident.recordedByID=recordedBy.gibbonPersonID')
            ->where('gibbonCareIncident.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID);

        $criteria->addFilterRules([
            'date' => function ($query, $date) {
                return $query
                    ->where('gibbonCareIncident.date=:date')
                    ->bindValue('date', $date);
            },
            'child' => function ($query, $gibbonPersonID) {
                return $query
                    ->where('gibbonCareIncident.gibbonPersonID=:gibbonPersonID')
                    ->bindValue('gibbonPersonID', $gibbonPersonID);
            },
            'type' => function ($query, $type) {
                return $query
                    ->where('gibbonCareIncident.type=:type')
                    ->bindValue('type', $type);
            },
            'severity' => function ($query, $severity) {
                return $query
                    ->where('gibbonCareIncident.severity=:severity')
                    ->bindValue('severity', $severity);
            },
            'parentNotified' => function ($query, $value) {
                return $query
                    ->where('gibbonCareIncident.parentNotified=:parentNotified')
                    ->bindValue('parentNotified', $value);
            },
            'parentAcknowledged' => function ($query, $value) {
                return $query
                    ->where('gibbonCareIncident.parentAcknowledged=:parentAcknowledged')
                    ->bindValue('parentAcknowledged', $value);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query incident records for a specific date.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonSchoolYearID
     * @param string $date
     * @return DataSet
     */
    public function queryIncidentsByDate(QueryCriteria $criteria, $gibbonSchoolYearID, $date)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonCareIncident.gibbonCareIncidentID',
                'gibbonCareIncident.gibbonPersonID',
                'gibbonCareIncident.date',
                'gibbonCareIncident.time',
                'gibbonCareIncident.type',
                'gibbonCareIncident.severity',
                'gibbonCareIncident.description',
                'gibbonCareIncident.actionTaken',
                'gibbonCareIncident.parentNotified',
                'gibbonCareIncident.parentAcknowledged',
                'gibbonCareIncident.timestampCreated',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
                'gibbonPerson.dob',
            ])
            ->innerJoin('gibbonPerson', 'gibbonCareIncident.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->where('gibbonCareIncident.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID)
            ->where('gibbonCareIncident.date=:date')
            ->bindValue('date', $date);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query incident history for a specific child.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonPersonID
     * @param int $gibbonSchoolYearID
     * @return DataSet
     */
    public function queryIncidentsByPerson(QueryCriteria $criteria, $gibbonPersonID, $gibbonSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonCareIncident.gibbonCareIncidentID',
                'gibbonCareIncident.date',
                'gibbonCareIncident.time',
                'gibbonCareIncident.type',
                'gibbonCareIncident.severity',
                'gibbonCareIncident.description',
                'gibbonCareIncident.actionTaken',
                'gibbonCareIncident.parentNotified',
                'gibbonCareIncident.parentNotifiedTime',
                'gibbonCareIncident.parentAcknowledged',
                'gibbonCareIncident.parentAcknowledgedTime',
                'gibbonCareIncident.timestampCreated',
                'recordedBy.preferredName as recordedByName',
                'recordedBy.surname as recordedBySurname',
            ])
            ->leftJoin('gibbonPerson as recordedBy', 'gibbonCareIncident.recordedByID=recordedBy.gibbonPersonID')
            ->where('gibbonCareIncident.gibbonPersonID=:gibbonPersonID')
            ->bindValue('gibbonPersonID', $gibbonPersonID)
            ->where('gibbonCareIncident.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Get incidents for a specific child on a specific date.
     *
     * @param int $gibbonPersonID
     * @param string $date
     * @return \Gibbon\Database\Result
     */
    public function selectIncidentsByPersonAndDate($gibbonPersonID, $date)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonCareIncident.*',
                'recordedBy.preferredName as recordedByName',
                'recordedBy.surname as recordedBySurname',
            ])
            ->leftJoin('gibbonPerson as recordedBy', 'gibbonCareIncident.recordedByID=recordedBy.gibbonPersonID')
            ->where('gibbonCareIncident.gibbonPersonID=:gibbonPersonID')
            ->bindValue('gibbonPersonID', $gibbonPersonID)
            ->where('gibbonCareIncident.date=:date')
            ->bindValue('date', $date)
            ->orderBy(['gibbonCareIncident.time ASC']);

        return $this->runSelect($query);
    }

    /**
     * Get incident summary statistics for a specific date.
     *
     * @param int $gibbonSchoolYearID
     * @param string $date
     * @return array
     */
    public function getIncidentSummaryByDate($gibbonSchoolYearID, $date)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID, 'date' => $date];
        $sql = "SELECT
                    COUNT(*) as totalIncidents,
                    COUNT(DISTINCT gibbonPersonID) as childrenInvolved,
                    SUM(CASE WHEN type='Minor Injury' THEN 1 ELSE 0 END) as minorInjuries,
                    SUM(CASE WHEN type='Major Injury' THEN 1 ELSE 0 END) as majorInjuries,
                    SUM(CASE WHEN type='Illness' THEN 1 ELSE 0 END) as illnesses,
                    SUM(CASE WHEN type='Behavioral' THEN 1 ELSE 0 END) as behavioral,
                    SUM(CASE WHEN type='Other' THEN 1 ELSE 0 END) as other,
                    SUM(CASE WHEN severity='Critical' THEN 1 ELSE 0 END) as criticalSeverity,
                    SUM(CASE WHEN severity='High' THEN 1 ELSE 0 END) as highSeverity,
                    SUM(CASE WHEN parentNotified='Y' THEN 1 ELSE 0 END) as parentsNotified,
                    SUM(CASE WHEN parentAcknowledged='Y' THEN 1 ELSE 0 END) as parentsAcknowledged
                FROM gibbonCareIncident
                WHERE gibbonSchoolYearID=:gibbonSchoolYearID
                AND date=:date";

        return $this->db()->selectOne($sql, $data) ?: [
            'totalIncidents' => 0,
            'childrenInvolved' => 0,
            'minorInjuries' => 0,
            'majorInjuries' => 0,
            'illnesses' => 0,
            'behavioral' => 0,
            'other' => 0,
            'criticalSeverity' => 0,
            'highSeverity' => 0,
            'parentsNotified' => 0,
            'parentsAcknowledged' => 0,
        ];
    }

    /**
     * Get incident statistics for a child over a date range.
     *
     * @param int $gibbonPersonID
     * @param string $dateStart
     * @param string $dateEnd
     * @return array
     */
    public function getIncidentStatsByPersonAndDateRange($gibbonPersonID, $dateStart, $dateEnd)
    {
        $data = ['gibbonPersonID' => $gibbonPersonID, 'dateStart' => $dateStart, 'dateEnd' => $dateEnd];
        $sql = "SELECT
                    COUNT(*) as totalIncidents,
                    SUM(CASE WHEN type='Minor Injury' THEN 1 ELSE 0 END) as minorInjuries,
                    SUM(CASE WHEN type='Major Injury' THEN 1 ELSE 0 END) as majorInjuries,
                    SUM(CASE WHEN type='Illness' THEN 1 ELSE 0 END) as illnesses,
                    SUM(CASE WHEN type='Behavioral' THEN 1 ELSE 0 END) as behavioral,
                    SUM(CASE WHEN severity='Critical' OR severity='High' THEN 1 ELSE 0 END) as severeCases
                FROM gibbonCareIncident
                WHERE gibbonPersonID=:gibbonPersonID
                AND date >= :dateStart
                AND date <= :dateEnd";

        return $this->db()->selectOne($sql, $data) ?: [
            'totalIncidents' => 0,
            'minorInjuries' => 0,
            'majorInjuries' => 0,
            'illnesses' => 0,
            'behavioral' => 0,
            'severeCases' => 0,
        ];
    }

    /**
     * Log an incident for a child.
     *
     * @param int $gibbonPersonID
     * @param int $gibbonSchoolYearID
     * @param string $date
     * @param string $time
     * @param string $type
     * @param string $severity
     * @param string $description
     * @param int $recordedByID
     * @param string|null $actionTaken
     * @return int|false
     */
    public function logIncident($gibbonPersonID, $gibbonSchoolYearID, $date, $time, $type, $severity, $description, $recordedByID, $actionTaken = null)
    {
        return $this->insert([
            'gibbonPersonID' => $gibbonPersonID,
            'gibbonSchoolYearID' => $gibbonSchoolYearID,
            'date' => $date,
            'time' => $time,
            'type' => $type,
            'severity' => $severity,
            'description' => $description,
            'actionTaken' => $actionTaken,
            'recordedByID' => $recordedByID,
        ]);
    }

    /**
     * Mark parent as notified for an incident.
     *
     * @param int $gibbonCareIncidentID
     * @return bool
     */
    public function markParentNotified($gibbonCareIncidentID)
    {
        return $this->update($gibbonCareIncidentID, [
            'parentNotified' => 'Y',
            'parentNotifiedTime' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Mark parent acknowledgment for an incident.
     *
     * @param int $gibbonCareIncidentID
     * @return bool
     */
    public function markParentAcknowledged($gibbonCareIncidentID)
    {
        return $this->update($gibbonCareIncidentID, [
            'parentAcknowledged' => 'Y',
            'parentAcknowledgedTime' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Select incidents pending parent notification.
     *
     * @param int $gibbonSchoolYearID
     * @return \Gibbon\Database\Result
     */
    public function selectIncidentsPendingNotification($gibbonSchoolYearID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonCareIncident.*',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
            ])
            ->innerJoin('gibbonPerson', 'gibbonCareIncident.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->where('gibbonCareIncident.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID)
            ->where("gibbonCareIncident.parentNotified='N'")
            ->orderBy(['gibbonCareIncident.severity DESC', 'gibbonCareIncident.date ASC', 'gibbonCareIncident.time ASC']);

        return $this->runSelect($query);
    }

    /**
     * Select incidents pending parent acknowledgment.
     *
     * @param int $gibbonSchoolYearID
     * @return \Gibbon\Database\Result
     */
    public function selectIncidentsPendingAcknowledgment($gibbonSchoolYearID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonCareIncident.*',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
            ])
            ->innerJoin('gibbonPerson', 'gibbonCareIncident.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->where('gibbonCareIncident.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID)
            ->where("gibbonCareIncident.parentNotified='Y'")
            ->where("gibbonCareIncident.parentAcknowledged='N'")
            ->orderBy(['gibbonCareIncident.severity DESC', 'gibbonCareIncident.date ASC']);

        return $this->runSelect($query);
    }

    /**
     * Select critical or high severity incidents for a date range.
     *
     * @param int $gibbonSchoolYearID
     * @param string $dateStart
     * @param string $dateEnd
     * @return \Gibbon\Database\Result
     */
    public function selectSevereIncidents($gibbonSchoolYearID, $dateStart, $dateEnd)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonCareIncident.*',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
                'recordedBy.preferredName as recordedByName',
                'recordedBy.surname as recordedBySurname',
            ])
            ->innerJoin('gibbonPerson', 'gibbonCareIncident.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->leftJoin('gibbonPerson as recordedBy', 'gibbonCareIncident.recordedByID=recordedBy.gibbonPersonID')
            ->where('gibbonCareIncident.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID)
            ->where('gibbonCareIncident.date >= :dateStart')
            ->bindValue('dateStart', $dateStart)
            ->where('gibbonCareIncident.date <= :dateEnd')
            ->bindValue('dateEnd', $dateEnd)
            ->where("(gibbonCareIncident.severity='Critical' OR gibbonCareIncident.severity='High')")
            ->orderBy(['gibbonCareIncident.severity DESC', 'gibbonCareIncident.date DESC', 'gibbonCareIncident.time DESC']);

        return $this->runSelect($query);
    }
}
