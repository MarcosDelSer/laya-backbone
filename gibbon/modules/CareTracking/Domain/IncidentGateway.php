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
     * Log a detailed incident with all enhanced fields.
     *
     * @param array $data Incident data containing:
     *   - gibbonPersonID (required): Child's person ID
     *   - gibbonSchoolYearID (required): School year ID
     *   - date (required): Date of incident (Y-m-d)
     *   - time (required): Time of incident (H:i:s)
     *   - type (required): Incident type (Minor Injury, Major Injury, Illness, Behavioral, Other)
     *   - severity (required): Severity level (Low, Medium, High, Critical)
     *   - description (required): Description of the incident
     *   - recordedByID (required): ID of staff member recording the incident
     *   - actionTaken (optional): Action taken in response
     *   - incidentCategory (optional): Category (Bump, Cut, Bite, Fall, Allergic Reaction, Fever, Other)
     *   - bodyPart (optional): Body part affected
     *   - medicalConsulted (optional): Whether medical was consulted (Y/N)
     *   - followUpRequired (optional): Whether follow-up is required (Y/N)
     *   - photoPath (optional): Path to incident photo
     *   - directorNotified (optional): Whether director was notified (Y/N)
     *   - directorNotifiedTime (optional): Timestamp of director notification
     *   - linkedInterventionPlanID (optional): Linked intervention plan ID
     * @return int|false The new incident ID on success, false on failure
     */
    public function logDetailedIncident(array $data)
    {
        // Required fields validation
        $requiredFields = ['gibbonPersonID', 'gibbonSchoolYearID', 'date', 'time', 'type', 'severity', 'description', 'recordedByID'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                return false;
            }
        }

        // Build insert data with required fields
        $insertData = [
            'gibbonPersonID' => $data['gibbonPersonID'],
            'gibbonSchoolYearID' => $data['gibbonSchoolYearID'],
            'date' => $data['date'],
            'time' => $data['time'],
            'type' => $data['type'],
            'severity' => $data['severity'],
            'description' => $data['description'],
            'recordedByID' => $data['recordedByID'],
        ];

        // Add optional fields if provided
        $optionalFields = [
            'actionTaken',
            'incidentCategory',
            'bodyPart',
            'medicalConsulted',
            'followUpRequired',
            'photoPath',
            'directorNotified',
            'directorNotifiedTime',
            'linkedInterventionPlanID',
        ];

        foreach ($optionalFields as $field) {
            if (isset($data[$field]) && $data[$field] !== '') {
                $insertData[$field] = $data[$field];
            }
        }

        return $this->insert($insertData);
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

    /**
     * Get incident count for a specific child within a date range.
     *
     * @param int $gibbonPersonID
     * @param string $dateStart
     * @param string $dateEnd
     * @param string|null $type Optional incident type filter
     * @return int
     */
    public function getIncidentCountByChild($gibbonPersonID, $dateStart, $dateEnd, $type = null)
    {
        $data = [
            'gibbonPersonID' => $gibbonPersonID,
            'dateStart' => $dateStart,
            'dateEnd' => $dateEnd,
        ];

        $sql = "SELECT COUNT(*) as count
                FROM gibbonCareIncident
                WHERE gibbonPersonID=:gibbonPersonID
                AND date >= :dateStart
                AND date <= :dateEnd";

        if ($type !== null) {
            $sql .= " AND type=:type";
            $data['type'] = $type;
        }

        $result = $this->db()->selectOne($sql, $data);
        return (int) ($result['count'] ?? 0);
    }

    /**
     * Detect incident patterns for children within a date range.
     *
     * Returns aggregated incident data by child and type to identify
     * recurring patterns that may need attention.
     *
     * @param int $gibbonSchoolYearID
     * @param string $dateStart
     * @param string $dateEnd
     * @param int $minIncidents Minimum incidents to be considered a pattern (default 3)
     * @return array Array of pattern data with child info, type, and counts
     */
    public function detectPatterns($gibbonSchoolYearID, $dateStart, $dateEnd, $minIncidents = 3)
    {
        $data = [
            'gibbonSchoolYearID' => $gibbonSchoolYearID,
            'dateStart' => $dateStart,
            'dateEnd' => $dateEnd,
            'minIncidents' => $minIncidents,
        ];

        $sql = "SELECT
                    gibbonCareIncident.gibbonPersonID,
                    gibbonPerson.preferredName,
                    gibbonPerson.surname,
                    gibbonPerson.image_240,
                    gibbonCareIncident.type,
                    COUNT(*) as incidentCount,
                    SUM(CASE WHEN severity='Critical' THEN 1 ELSE 0 END) as criticalCount,
                    SUM(CASE WHEN severity='High' THEN 1 ELSE 0 END) as highCount,
                    MIN(gibbonCareIncident.date) as firstIncident,
                    MAX(gibbonCareIncident.date) as lastIncident
                FROM gibbonCareIncident
                INNER JOIN gibbonPerson ON gibbonCareIncident.gibbonPersonID=gibbonPerson.gibbonPersonID
                WHERE gibbonCareIncident.gibbonSchoolYearID=:gibbonSchoolYearID
                AND gibbonCareIncident.date >= :dateStart
                AND gibbonCareIncident.date <= :dateEnd
                GROUP BY gibbonCareIncident.gibbonPersonID, gibbonCareIncident.type
                HAVING COUNT(*) >= :minIncidents
                ORDER BY incidentCount DESC, criticalCount DESC, highCount DESC";

        return $this->db()->select($sql, $data)->fetchAll();
    }

    /**
     * Select children needing review based on incident patterns.
     *
     * Identifies children with concerning incident patterns based on:
     * - High total incident count within the period
     * - Multiple severe (Critical/High) incidents
     * - Recurring incidents of the same type
     *
     * @param int $gibbonSchoolYearID
     * @param string $dateStart
     * @param string $dateEnd
     * @param int $totalThreshold Minimum total incidents to flag (default 5)
     * @param int $severeThreshold Minimum severe incidents to flag (default 2)
     * @return \Gibbon\Database\Result
     */
    public function selectChildrenNeedingReview($gibbonSchoolYearID, $dateStart, $dateEnd, $totalThreshold = 5, $severeThreshold = 2)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonCareIncident.gibbonPersonID',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
                'gibbonPerson.dob',
                'COUNT(*) as totalIncidents',
                "SUM(CASE WHEN gibbonCareIncident.severity='Critical' THEN 1 ELSE 0 END) as criticalCount",
                "SUM(CASE WHEN gibbonCareIncident.severity='High' THEN 1 ELSE 0 END) as highCount",
                "SUM(CASE WHEN gibbonCareIncident.severity IN ('Critical', 'High') THEN 1 ELSE 0 END) as severeCount",
                "SUM(CASE WHEN gibbonCareIncident.type='Minor Injury' THEN 1 ELSE 0 END) as minorInjuryCount",
                "SUM(CASE WHEN gibbonCareIncident.type='Major Injury' THEN 1 ELSE 0 END) as majorInjuryCount",
                "SUM(CASE WHEN gibbonCareIncident.type='Illness' THEN 1 ELSE 0 END) as illnessCount",
                "SUM(CASE WHEN gibbonCareIncident.type='Behavioral' THEN 1 ELSE 0 END) as behavioralCount",
                'MIN(gibbonCareIncident.date) as firstIncidentDate',
                'MAX(gibbonCareIncident.date) as lastIncidentDate',
            ])
            ->innerJoin('gibbonPerson', 'gibbonCareIncident.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->where('gibbonCareIncident.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID)
            ->where('gibbonCareIncident.date >= :dateStart')
            ->bindValue('dateStart', $dateStart)
            ->where('gibbonCareIncident.date <= :dateEnd')
            ->bindValue('dateEnd', $dateEnd)
            ->groupBy(['gibbonCareIncident.gibbonPersonID'])
            ->having("COUNT(*) >= :totalThreshold OR SUM(CASE WHEN gibbonCareIncident.severity IN ('Critical', 'High') THEN 1 ELSE 0 END) >= :severeThreshold")
            ->bindValue('totalThreshold', $totalThreshold)
            ->bindValue('severeThreshold', $severeThreshold)
            ->orderBy(['severeCount DESC', 'totalIncidents DESC']);

        return $this->runSelect($query);
    }
}
