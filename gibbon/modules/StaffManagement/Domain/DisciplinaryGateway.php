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
 * Staff Disciplinary Gateway
 *
 * Handles disciplinary record management with director-only access.
 * Supports tracking of warnings, suspensions, performance plans, and related follow-ups.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class DisciplinaryGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonStaffDisciplinary';
    private static $primaryKey = 'gibbonStaffDisciplinaryID';

    private static $searchableColumns = ['gibbonPerson.preferredName', 'gibbonPerson.surname', 'gibbonStaffDisciplinary.description', 'gibbonStaffDisciplinary.actionTaken', 'gibbonStaffDisciplinary.witnessNames'];

    /**
     * Query disciplinary records with criteria support.
     *
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryDisciplinaryRecords(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonStaffDisciplinary.gibbonStaffDisciplinaryID',
                'gibbonStaffDisciplinary.gibbonPersonID',
                'gibbonStaffDisciplinary.incidentDate',
                'gibbonStaffDisciplinary.incidentTime',
                'gibbonStaffDisciplinary.type',
                'gibbonStaffDisciplinary.severity',
                'gibbonStaffDisciplinary.category',
                'gibbonStaffDisciplinary.description',
                'gibbonStaffDisciplinary.actionTaken',
                'gibbonStaffDisciplinary.employeeResponse',
                'gibbonStaffDisciplinary.followUpRequired',
                'gibbonStaffDisciplinary.followUpDate',
                'gibbonStaffDisciplinary.followUpCompleted',
                'gibbonStaffDisciplinary.followUpNotes',
                'gibbonStaffDisciplinary.witnessNames',
                'gibbonStaffDisciplinary.documentPath',
                'gibbonStaffDisciplinary.confidential',
                'gibbonStaffDisciplinary.status',
                'gibbonStaffDisciplinary.resolutionDate',
                'gibbonStaffDisciplinary.resolutionNotes',
                'gibbonStaffDisciplinary.recordedByID',
                'gibbonStaffDisciplinary.timestampCreated',
                'gibbonStaffDisciplinary.timestampModified',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
                'recordedBy.preferredName as recordedByName',
                'recordedBy.surname as recordedBySurname',
            ])
            ->innerJoin('gibbonPerson', 'gibbonStaffDisciplinary.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->leftJoin('gibbonPerson as recordedBy', 'gibbonStaffDisciplinary.recordedByID=recordedBy.gibbonPersonID');

        $criteria->addFilterRules([
            'type' => function ($query, $type) {
                return $query
                    ->where('gibbonStaffDisciplinary.type=:type')
                    ->bindValue('type', $type);
            },
            'severity' => function ($query, $severity) {
                return $query
                    ->where('gibbonStaffDisciplinary.severity=:severity')
                    ->bindValue('severity', $severity);
            },
            'category' => function ($query, $category) {
                return $query
                    ->where('gibbonStaffDisciplinary.category=:category')
                    ->bindValue('category', $category);
            },
            'status' => function ($query, $status) {
                return $query
                    ->where('gibbonStaffDisciplinary.status=:status')
                    ->bindValue('status', $status);
            },
            'staff' => function ($query, $gibbonPersonID) {
                return $query
                    ->where('gibbonStaffDisciplinary.gibbonPersonID=:gibbonPersonID')
                    ->bindValue('gibbonPersonID', $gibbonPersonID);
            },
            'confidential' => function ($query, $confidential) {
                return $query
                    ->where('gibbonStaffDisciplinary.confidential=:confidential')
                    ->bindValue('confidential', $confidential);
            },
            'dateFrom' => function ($query, $dateFrom) {
                return $query
                    ->where('gibbonStaffDisciplinary.incidentDate>=:dateFrom')
                    ->bindValue('dateFrom', $dateFrom);
            },
            'dateTo' => function ($query, $dateTo) {
                return $query
                    ->where('gibbonStaffDisciplinary.incidentDate<=:dateTo')
                    ->bindValue('dateTo', $dateTo);
            },
            'followUpRequired' => function ($query, $value) {
                return $query
                    ->where('gibbonStaffDisciplinary.followUpRequired=:followUpRequired')
                    ->bindValue('followUpRequired', $value);
            },
            'followUpCompleted' => function ($query, $value) {
                return $query
                    ->where('gibbonStaffDisciplinary.followUpCompleted=:followUpCompleted')
                    ->bindValue('followUpCompleted', $value);
            },
            'recordedBy' => function ($query, $recordedByID) {
                return $query
                    ->where('gibbonStaffDisciplinary.recordedByID=:recordedByID')
                    ->bindValue('recordedByID', $recordedByID);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query disciplinary records for a specific staff member.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonPersonID
     * @return DataSet
     */
    public function queryDisciplinaryRecordsByPerson(QueryCriteria $criteria, $gibbonPersonID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonStaffDisciplinary.gibbonStaffDisciplinaryID',
                'gibbonStaffDisciplinary.incidentDate',
                'gibbonStaffDisciplinary.incidentTime',
                'gibbonStaffDisciplinary.type',
                'gibbonStaffDisciplinary.severity',
                'gibbonStaffDisciplinary.category',
                'gibbonStaffDisciplinary.description',
                'gibbonStaffDisciplinary.actionTaken',
                'gibbonStaffDisciplinary.employeeResponse',
                'gibbonStaffDisciplinary.followUpRequired',
                'gibbonStaffDisciplinary.followUpDate',
                'gibbonStaffDisciplinary.followUpCompleted',
                'gibbonStaffDisciplinary.status',
                'gibbonStaffDisciplinary.resolutionDate',
                'gibbonStaffDisciplinary.timestampCreated',
                'recordedBy.preferredName as recordedByName',
                'recordedBy.surname as recordedBySurname',
            ])
            ->leftJoin('gibbonPerson as recordedBy', 'gibbonStaffDisciplinary.recordedByID=recordedBy.gibbonPersonID')
            ->where('gibbonStaffDisciplinary.gibbonPersonID=:gibbonPersonID')
            ->bindValue('gibbonPersonID', $gibbonPersonID);

        $criteria->addFilterRules([
            'type' => function ($query, $type) {
                return $query
                    ->where('gibbonStaffDisciplinary.type=:type')
                    ->bindValue('type', $type);
            },
            'status' => function ($query, $status) {
                return $query
                    ->where('gibbonStaffDisciplinary.status=:status')
                    ->bindValue('status', $status);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query disciplinary records requiring follow-up.
     *
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryPendingFollowUps(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonStaffDisciplinary.gibbonStaffDisciplinaryID',
                'gibbonStaffDisciplinary.gibbonPersonID',
                'gibbonStaffDisciplinary.incidentDate',
                'gibbonStaffDisciplinary.type',
                'gibbonStaffDisciplinary.severity',
                'gibbonStaffDisciplinary.category',
                'gibbonStaffDisciplinary.followUpDate',
                'gibbonStaffDisciplinary.status',
                'gibbonStaffDisciplinary.timestampCreated',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
                'recordedBy.preferredName as recordedByName',
                'recordedBy.surname as recordedBySurname',
            ])
            ->innerJoin('gibbonPerson', 'gibbonStaffDisciplinary.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->leftJoin('gibbonPerson as recordedBy', 'gibbonStaffDisciplinary.recordedByID=recordedBy.gibbonPersonID')
            ->where("gibbonStaffDisciplinary.followUpRequired='Y'")
            ->where("gibbonStaffDisciplinary.followUpCompleted='N'");

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query open disciplinary records.
     *
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryOpenRecords(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonStaffDisciplinary.gibbonStaffDisciplinaryID',
                'gibbonStaffDisciplinary.gibbonPersonID',
                'gibbonStaffDisciplinary.incidentDate',
                'gibbonStaffDisciplinary.type',
                'gibbonStaffDisciplinary.severity',
                'gibbonStaffDisciplinary.category',
                'gibbonStaffDisciplinary.status',
                'gibbonStaffDisciplinary.followUpRequired',
                'gibbonStaffDisciplinary.followUpDate',
                'gibbonStaffDisciplinary.timestampCreated',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
            ])
            ->innerJoin('gibbonPerson', 'gibbonStaffDisciplinary.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->where("gibbonStaffDisciplinary.status IN ('Open', 'Under Review')");

        return $this->runQuery($query, $criteria);
    }

    /**
     * Get a disciplinary record by ID.
     *
     * @param int $gibbonStaffDisciplinaryID
     * @return array|false
     */
    public function getDisciplinaryRecordByID($gibbonStaffDisciplinaryID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonStaffDisciplinary.*',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
                'gibbonPerson.email',
                'recordedBy.preferredName as recordedByName',
                'recordedBy.surname as recordedBySurname',
            ])
            ->innerJoin('gibbonPerson', 'gibbonStaffDisciplinary.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->leftJoin('gibbonPerson as recordedBy', 'gibbonStaffDisciplinary.recordedByID=recordedBy.gibbonPersonID')
            ->where('gibbonStaffDisciplinary.gibbonStaffDisciplinaryID=:gibbonStaffDisciplinaryID')
            ->bindValue('gibbonStaffDisciplinaryID', $gibbonStaffDisciplinaryID);

        return $this->runSelect($query)->fetch();
    }

    /**
     * Select disciplinary records for a staff member.
     *
     * @param int $gibbonPersonID
     * @return \Gibbon\Database\Result
     */
    public function selectDisciplinaryRecordsByPerson($gibbonPersonID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonStaffDisciplinary.*',
                'recordedBy.preferredName as recordedByName',
                'recordedBy.surname as recordedBySurname',
            ])
            ->leftJoin('gibbonPerson as recordedBy', 'gibbonStaffDisciplinary.recordedByID=recordedBy.gibbonPersonID')
            ->where('gibbonStaffDisciplinary.gibbonPersonID=:gibbonPersonID')
            ->bindValue('gibbonPersonID', $gibbonPersonID)
            ->orderBy(['gibbonStaffDisciplinary.incidentDate DESC', 'gibbonStaffDisciplinary.incidentTime DESC']);

        return $this->runSelect($query);
    }

    /**
     * Select follow-ups due within a date range.
     *
     * @param string $dateFrom
     * @param string $dateTo
     * @return \Gibbon\Database\Result
     */
    public function selectFollowUpsDue($dateFrom, $dateTo)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonStaffDisciplinary.gibbonStaffDisciplinaryID',
                'gibbonStaffDisciplinary.gibbonPersonID',
                'gibbonStaffDisciplinary.incidentDate',
                'gibbonStaffDisciplinary.type',
                'gibbonStaffDisciplinary.severity',
                'gibbonStaffDisciplinary.followUpDate',
                'gibbonStaffDisciplinary.status',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
            ])
            ->innerJoin('gibbonPerson', 'gibbonStaffDisciplinary.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->where("gibbonStaffDisciplinary.followUpRequired='Y'")
            ->where("gibbonStaffDisciplinary.followUpCompleted='N'")
            ->where('gibbonStaffDisciplinary.followUpDate>=:dateFrom')
            ->bindValue('dateFrom', $dateFrom)
            ->where('gibbonStaffDisciplinary.followUpDate<=:dateTo')
            ->bindValue('dateTo', $dateTo)
            ->orderBy(['gibbonStaffDisciplinary.followUpDate ASC']);

        return $this->runSelect($query);
    }

    /**
     * Select overdue follow-ups.
     *
     * @return \Gibbon\Database\Result
     */
    public function selectOverdueFollowUps()
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonStaffDisciplinary.gibbonStaffDisciplinaryID',
                'gibbonStaffDisciplinary.gibbonPersonID',
                'gibbonStaffDisciplinary.incidentDate',
                'gibbonStaffDisciplinary.type',
                'gibbonStaffDisciplinary.severity',
                'gibbonStaffDisciplinary.followUpDate',
                'gibbonStaffDisciplinary.status',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
                "DATEDIFF(CURDATE(), gibbonStaffDisciplinary.followUpDate) as daysOverdue",
            ])
            ->innerJoin('gibbonPerson', 'gibbonStaffDisciplinary.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->where("gibbonStaffDisciplinary.followUpRequired='Y'")
            ->where("gibbonStaffDisciplinary.followUpCompleted='N'")
            ->where('gibbonStaffDisciplinary.followUpDate < CURDATE()')
            ->orderBy(['gibbonStaffDisciplinary.followUpDate ASC']);

        return $this->runSelect($query);
    }

    /**
     * Log a disciplinary incident.
     *
     * @param int $gibbonPersonID
     * @param string $incidentDate
     * @param string|null $incidentTime
     * @param string $type
     * @param string $severity
     * @param string $category
     * @param string $description
     * @param int $recordedByID
     * @param array $additionalData Optional additional fields
     * @return int|false
     */
    public function logDisciplinaryIncident($gibbonPersonID, $incidentDate, $incidentTime, $type, $severity, $category, $description, $recordedByID, $additionalData = [])
    {
        $data = array_merge([
            'gibbonPersonID' => $gibbonPersonID,
            'incidentDate' => $incidentDate,
            'incidentTime' => $incidentTime,
            'type' => $type,
            'severity' => $severity,
            'category' => $category,
            'description' => $description,
            'recordedByID' => $recordedByID,
        ], $additionalData);

        return $this->insert($data);
    }

    /**
     * Update the status of a disciplinary record.
     *
     * @param int $gibbonStaffDisciplinaryID
     * @param string $status
     * @param string|null $resolutionDate
     * @param string|null $resolutionNotes
     * @return bool
     */
    public function updateStatus($gibbonStaffDisciplinaryID, $status, $resolutionDate = null, $resolutionNotes = null)
    {
        $data = ['status' => $status];

        if ($resolutionDate !== null) {
            $data['resolutionDate'] = $resolutionDate;
        }

        if ($resolutionNotes !== null) {
            $data['resolutionNotes'] = $resolutionNotes;
        }

        return $this->update($gibbonStaffDisciplinaryID, $data);
    }

    /**
     * Mark a follow-up as completed.
     *
     * @param int $gibbonStaffDisciplinaryID
     * @param string $followUpNotes
     * @return bool
     */
    public function completeFollowUp($gibbonStaffDisciplinaryID, $followUpNotes = null)
    {
        $data = [
            'followUpCompleted' => 'Y',
        ];

        if ($followUpNotes !== null) {
            $data['followUpNotes'] = $followUpNotes;
        }

        return $this->update($gibbonStaffDisciplinaryID, $data);
    }

    /**
     * Add employee response to a disciplinary record.
     *
     * @param int $gibbonStaffDisciplinaryID
     * @param string $employeeResponse
     * @return bool
     */
    public function addEmployeeResponse($gibbonStaffDisciplinaryID, $employeeResponse)
    {
        return $this->update($gibbonStaffDisciplinaryID, [
            'employeeResponse' => $employeeResponse,
        ]);
    }

    /**
     * Get disciplinary summary statistics.
     *
     * @param string|null $dateFrom
     * @param string|null $dateTo
     * @return array
     */
    public function getDisciplinarySummary($dateFrom = null, $dateTo = null)
    {
        $conditions = '';
        $data = [];

        if ($dateFrom !== null) {
            $conditions .= ' AND incidentDate>=:dateFrom';
            $data['dateFrom'] = $dateFrom;
        }

        if ($dateTo !== null) {
            $conditions .= ' AND incidentDate<=:dateTo';
            $data['dateTo'] = $dateTo;
        }

        $sql = "SELECT
                    COUNT(*) as totalRecords,
                    COUNT(DISTINCT gibbonPersonID) as staffInvolved,
                    SUM(CASE WHEN type='Verbal Warning' THEN 1 ELSE 0 END) as verbalWarnings,
                    SUM(CASE WHEN type='Written Warning' THEN 1 ELSE 0 END) as writtenWarnings,
                    SUM(CASE WHEN type='Suspension' THEN 1 ELSE 0 END) as suspensions,
                    SUM(CASE WHEN type='Probation' THEN 1 ELSE 0 END) as probations,
                    SUM(CASE WHEN type='Performance Improvement Plan' THEN 1 ELSE 0 END) as performancePlans,
                    SUM(CASE WHEN type='Termination' THEN 1 ELSE 0 END) as terminations,
                    SUM(CASE WHEN severity='Critical' THEN 1 ELSE 0 END) as criticalSeverity,
                    SUM(CASE WHEN severity='Serious' THEN 1 ELSE 0 END) as seriousSeverity,
                    SUM(CASE WHEN status='Open' THEN 1 ELSE 0 END) as openRecords,
                    SUM(CASE WHEN status='Under Review' THEN 1 ELSE 0 END) as underReview,
                    SUM(CASE WHEN status='Resolved' THEN 1 ELSE 0 END) as resolved,
                    SUM(CASE WHEN followUpRequired='Y' AND followUpCompleted='N' THEN 1 ELSE 0 END) as pendingFollowUps
                FROM {$this->getTableName()}
                WHERE 1=1 {$conditions}";

        return $this->db()->selectOne($sql, $data) ?: [
            'totalRecords' => 0,
            'staffInvolved' => 0,
            'verbalWarnings' => 0,
            'writtenWarnings' => 0,
            'suspensions' => 0,
            'probations' => 0,
            'performancePlans' => 0,
            'terminations' => 0,
            'criticalSeverity' => 0,
            'seriousSeverity' => 0,
            'openRecords' => 0,
            'underReview' => 0,
            'resolved' => 0,
            'pendingFollowUps' => 0,
        ];
    }

    /**
     * Get disciplinary summary for a specific staff member.
     *
     * @param int $gibbonPersonID
     * @return array
     */
    public function getDisciplinarySummaryByPerson($gibbonPersonID)
    {
        $data = ['gibbonPersonID' => $gibbonPersonID];
        $sql = "SELECT
                    COUNT(*) as totalRecords,
                    SUM(CASE WHEN type='Verbal Warning' THEN 1 ELSE 0 END) as verbalWarnings,
                    SUM(CASE WHEN type='Written Warning' THEN 1 ELSE 0 END) as writtenWarnings,
                    SUM(CASE WHEN type='Suspension' THEN 1 ELSE 0 END) as suspensions,
                    SUM(CASE WHEN status='Open' OR status='Under Review' THEN 1 ELSE 0 END) as activeRecords,
                    SUM(CASE WHEN status='Resolved' THEN 1 ELSE 0 END) as resolved,
                    MIN(incidentDate) as firstIncident,
                    MAX(incidentDate) as lastIncident
                FROM {$this->getTableName()}
                WHERE gibbonPersonID=:gibbonPersonID";

        return $this->db()->selectOne($sql, $data) ?: [
            'totalRecords' => 0,
            'verbalWarnings' => 0,
            'writtenWarnings' => 0,
            'suspensions' => 0,
            'activeRecords' => 0,
            'resolved' => 0,
            'firstIncident' => null,
            'lastIncident' => null,
        ];
    }

    /**
     * Get summary of disciplinary records by category.
     *
     * @param string|null $dateFrom
     * @param string|null $dateTo
     * @return \Gibbon\Database\Result
     */
    public function selectSummaryByCategory($dateFrom = null, $dateTo = null)
    {
        $conditions = '';
        $data = [];

        if ($dateFrom !== null) {
            $conditions .= ' AND incidentDate>=:dateFrom';
            $data['dateFrom'] = $dateFrom;
        }

        if ($dateTo !== null) {
            $conditions .= ' AND incidentDate<=:dateTo';
            $data['dateTo'] = $dateTo;
        }

        $sql = "SELECT
                    category,
                    COUNT(*) as totalRecords,
                    COUNT(DISTINCT gibbonPersonID) as staffInvolved,
                    SUM(CASE WHEN status='Open' OR status='Under Review' THEN 1 ELSE 0 END) as activeRecords
                FROM {$this->getTableName()}
                WHERE 1=1 {$conditions}
                GROUP BY category
                ORDER BY totalRecords DESC";

        return $this->db()->select($sql, $data);
    }

    /**
     * Get summary of disciplinary records by type.
     *
     * @param string|null $dateFrom
     * @param string|null $dateTo
     * @return \Gibbon\Database\Result
     */
    public function selectSummaryByType($dateFrom = null, $dateTo = null)
    {
        $conditions = '';
        $data = [];

        if ($dateFrom !== null) {
            $conditions .= ' AND incidentDate>=:dateFrom';
            $data['dateFrom'] = $dateFrom;
        }

        if ($dateTo !== null) {
            $conditions .= ' AND incidentDate<=:dateTo';
            $data['dateTo'] = $dateTo;
        }

        $sql = "SELECT
                    type,
                    COUNT(*) as totalRecords,
                    COUNT(DISTINCT gibbonPersonID) as staffInvolved,
                    SUM(CASE WHEN status='Open' OR status='Under Review' THEN 1 ELSE 0 END) as activeRecords
                FROM {$this->getTableName()}
                WHERE 1=1 {$conditions}
                GROUP BY type
                ORDER BY totalRecords DESC";

        return $this->db()->select($sql, $data);
    }

    /**
     * Get staff members with multiple disciplinary records.
     *
     * @param int $threshold Minimum number of records
     * @param string|null $dateFrom
     * @param string|null $dateTo
     * @return \Gibbon\Database\Result
     */
    public function selectStaffWithMultipleRecords($threshold = 2, $dateFrom = null, $dateTo = null)
    {
        $conditions = '';
        $data = ['threshold' => $threshold];

        if ($dateFrom !== null) {
            $conditions .= ' AND d.incidentDate>=:dateFrom';
            $data['dateFrom'] = $dateFrom;
        }

        if ($dateTo !== null) {
            $conditions .= ' AND d.incidentDate<=:dateTo';
            $data['dateTo'] = $dateTo;
        }

        $sql = "SELECT
                    d.gibbonPersonID,
                    p.preferredName,
                    p.surname,
                    p.image_240,
                    COUNT(*) as totalRecords,
                    SUM(CASE WHEN d.status='Open' OR d.status='Under Review' THEN 1 ELSE 0 END) as activeRecords,
                    MAX(d.incidentDate) as lastIncident
                FROM {$this->getTableName()} d
                INNER JOIN gibbonPerson p ON d.gibbonPersonID=p.gibbonPersonID
                WHERE 1=1 {$conditions}
                GROUP BY d.gibbonPersonID
                HAVING totalRecords >= :threshold
                ORDER BY totalRecords DESC";

        return $this->db()->select($sql, $data);
    }

    /**
     * Get disciplinary trend over time (monthly).
     *
     * @param int $months Number of months to look back
     * @return \Gibbon\Database\Result
     */
    public function selectMonthlyTrend($months = 12)
    {
        $data = ['months' => $months];
        $sql = "SELECT
                    DATE_FORMAT(incidentDate, '%Y-%m') as month,
                    COUNT(*) as totalRecords,
                    COUNT(DISTINCT gibbonPersonID) as staffInvolved,
                    SUM(CASE WHEN severity='Critical' OR severity='Serious' THEN 1 ELSE 0 END) as severeRecords
                FROM {$this->getTableName()}
                WHERE incidentDate >= DATE_SUB(CURDATE(), INTERVAL :months MONTH)
                GROUP BY DATE_FORMAT(incidentDate, '%Y-%m')
                ORDER BY month ASC";

        return $this->db()->select($sql, $data);
    }

    /**
     * Check if a staff member has active disciplinary records.
     *
     * @param int $gibbonPersonID
     * @return bool
     */
    public function hasActiveRecords($gibbonPersonID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols(['gibbonStaffDisciplinaryID'])
            ->where('gibbonPersonID=:gibbonPersonID')
            ->bindValue('gibbonPersonID', $gibbonPersonID)
            ->where("status IN ('Open', 'Under Review')")
            ->limit(1);

        $result = $this->runSelect($query);
        return $result->isNotEmpty();
    }

    /**
     * Count total disciplinary records for a staff member.
     *
     * @param int $gibbonPersonID
     * @return int
     */
    public function countRecordsByPerson($gibbonPersonID)
    {
        $data = ['gibbonPersonID' => $gibbonPersonID];
        $sql = "SELECT COUNT(*) as count FROM {$this->getTableName()}
                WHERE gibbonPersonID=:gibbonPersonID";

        $result = $this->db()->selectOne($sql, $data);
        return intval($result['count'] ?? 0);
    }

    /**
     * Delete old archived records based on retention policy.
     *
     * @param int $retentionYears Number of years to retain records
     * @return int Number of rows deleted
     */
    public function deleteOldArchivedRecords($retentionYears = 7)
    {
        $data = ['cutoffDate' => date('Y-m-d', strtotime("-{$retentionYears} years"))];
        $sql = "DELETE FROM {$this->getTableName()}
                WHERE status='Archived'
                AND incidentDate < :cutoffDate";

        return $this->db()->delete($sql, $data);
    }

    /**
     * Archive resolved records older than specified period.
     *
     * @param int $archiveAfterDays Days after resolution to archive
     * @return int Number of rows updated
     */
    public function archiveOldResolvedRecords($archiveAfterDays = 365)
    {
        $data = ['cutoffDate' => date('Y-m-d', strtotime("-{$archiveAfterDays} days"))];
        $sql = "UPDATE {$this->getTableName()}
                SET status='Archived'
                WHERE status='Resolved'
                AND resolutionDate IS NOT NULL
                AND resolutionDate < :cutoffDate";

        return $this->db()->statement($sql, $data);
    }
}
