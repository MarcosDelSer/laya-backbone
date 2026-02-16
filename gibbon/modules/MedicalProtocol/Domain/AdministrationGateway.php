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

namespace Gibbon\Module\MedicalProtocol\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\QueryableGateway;

/**
 * Medical Protocol Administration Gateway
 *
 * Handles logging and querying of medication/repellent administrations
 * for Quebec-mandated protocols (Acetaminophen FO-0647, Insect Repellent FO-0646).
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class AdministrationGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonMedicalProtocolAdministration';
    private static $primaryKey = 'gibbonMedicalProtocolAdministrationID';

    private static $searchableColumns = ['gibbonPerson.preferredName', 'gibbonPerson.surname', 'gibbonMedicalProtocolAdministration.reason', 'gibbonMedicalProtocolAdministration.observations'];

    /**
     * Query administration records with criteria support.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonSchoolYearID
     * @return DataSet
     */
    public function queryAdministrations(QueryCriteria $criteria, $gibbonSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonMedicalProtocolAdministration.gibbonMedicalProtocolAdministrationID',
                'gibbonMedicalProtocolAdministration.gibbonMedicalProtocolAuthorizationID',
                'gibbonMedicalProtocolAdministration.gibbonPersonID',
                'gibbonMedicalProtocolAdministration.date',
                'gibbonMedicalProtocolAdministration.time',
                'gibbonMedicalProtocolAdministration.doseGiven',
                'gibbonMedicalProtocolAdministration.doseMg',
                'gibbonMedicalProtocolAdministration.concentration',
                'gibbonMedicalProtocolAdministration.weightAtTimeKg',
                'gibbonMedicalProtocolAdministration.temperatureC',
                'gibbonMedicalProtocolAdministration.temperatureMethod',
                'gibbonMedicalProtocolAdministration.reason',
                'gibbonMedicalProtocolAdministration.observations',
                'gibbonMedicalProtocolAdministration.followUpTime',
                'gibbonMedicalProtocolAdministration.followUpCompleted',
                'gibbonMedicalProtocolAdministration.parentNotified',
                'gibbonMedicalProtocolAdministration.parentAcknowledged',
                'gibbonMedicalProtocolAdministration.timestampCreated',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
                'gibbonMedicalProtocol.name as protocolName',
                'gibbonMedicalProtocol.formCode',
                'gibbonMedicalProtocol.type as protocolType',
                'administeredBy.preferredName as administeredByName',
                'administeredBy.surname as administeredBySurname',
                'witnessedBy.preferredName as witnessedByName',
                'witnessedBy.surname as witnessedBySurname',
            ])
            ->innerJoin('gibbonPerson', 'gibbonMedicalProtocolAdministration.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->innerJoin('gibbonMedicalProtocolAuthorization', 'gibbonMedicalProtocolAdministration.gibbonMedicalProtocolAuthorizationID=gibbonMedicalProtocolAuthorization.gibbonMedicalProtocolAuthorizationID')
            ->innerJoin('gibbonMedicalProtocol', 'gibbonMedicalProtocolAuthorization.gibbonMedicalProtocolID=gibbonMedicalProtocol.gibbonMedicalProtocolID')
            ->leftJoin('gibbonPerson as administeredBy', 'gibbonMedicalProtocolAdministration.administeredByID=administeredBy.gibbonPersonID')
            ->leftJoin('gibbonPerson as witnessedBy', 'gibbonMedicalProtocolAdministration.witnessedByID=witnessedBy.gibbonPersonID')
            ->where('gibbonMedicalProtocolAdministration.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID);

        $criteria->addFilterRules([
            'date' => function ($query, $date) {
                return $query
                    ->where('gibbonMedicalProtocolAdministration.date=:date')
                    ->bindValue('date', $date);
            },
            'child' => function ($query, $gibbonPersonID) {
                return $query
                    ->where('gibbonMedicalProtocolAdministration.gibbonPersonID=:gibbonPersonID')
                    ->bindValue('gibbonPersonID', $gibbonPersonID);
            },
            'protocol' => function ($query, $gibbonMedicalProtocolID) {
                return $query
                    ->where('gibbonMedicalProtocolAuthorization.gibbonMedicalProtocolID=:gibbonMedicalProtocolID')
                    ->bindValue('gibbonMedicalProtocolID', $gibbonMedicalProtocolID);
            },
            'formCode' => function ($query, $formCode) {
                return $query
                    ->where('gibbonMedicalProtocol.formCode=:formCode')
                    ->bindValue('formCode', $formCode);
            },
            'protocolType' => function ($query, $type) {
                return $query
                    ->where('gibbonMedicalProtocol.type=:protocolType')
                    ->bindValue('protocolType', $type);
            },
            'administeredBy' => function ($query, $administeredByID) {
                return $query
                    ->where('gibbonMedicalProtocolAdministration.administeredByID=:administeredByID')
                    ->bindValue('administeredByID', $administeredByID);
            },
            'followUpCompleted' => function ($query, $value) {
                return $query
                    ->where('gibbonMedicalProtocolAdministration.followUpCompleted=:followUpCompleted')
                    ->bindValue('followUpCompleted', $value);
            },
            'parentNotified' => function ($query, $value) {
                return $query
                    ->where('gibbonMedicalProtocolAdministration.parentNotified=:parentNotified')
                    ->bindValue('parentNotified', $value);
            },
            'parentAcknowledged' => function ($query, $value) {
                return $query
                    ->where('gibbonMedicalProtocolAdministration.parentAcknowledged=:parentAcknowledged')
                    ->bindValue('parentAcknowledged', $value);
            },
            'dateFrom' => function ($query, $dateFrom) {
                return $query
                    ->where('gibbonMedicalProtocolAdministration.date >= :dateFrom')
                    ->bindValue('dateFrom', $dateFrom);
            },
            'dateTo' => function ($query, $dateTo) {
                return $query
                    ->where('gibbonMedicalProtocolAdministration.date <= :dateTo')
                    ->bindValue('dateTo', $dateTo);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query administration records for a specific date.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonSchoolYearID
     * @param string $date
     * @return DataSet
     */
    public function queryAdministrationsByDate(QueryCriteria $criteria, $gibbonSchoolYearID, $date)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonMedicalProtocolAdministration.gibbonMedicalProtocolAdministrationID',
                'gibbonMedicalProtocolAdministration.gibbonPersonID',
                'gibbonMedicalProtocolAdministration.date',
                'gibbonMedicalProtocolAdministration.time',
                'gibbonMedicalProtocolAdministration.doseGiven',
                'gibbonMedicalProtocolAdministration.doseMg',
                'gibbonMedicalProtocolAdministration.concentration',
                'gibbonMedicalProtocolAdministration.temperatureC',
                'gibbonMedicalProtocolAdministration.temperatureMethod',
                'gibbonMedicalProtocolAdministration.followUpTime',
                'gibbonMedicalProtocolAdministration.followUpCompleted',
                'gibbonMedicalProtocolAdministration.parentNotified',
                'gibbonMedicalProtocolAdministration.timestampCreated',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
                'gibbonPerson.dob',
                'gibbonMedicalProtocol.name as protocolName',
                'gibbonMedicalProtocol.formCode',
                'administeredBy.preferredName as administeredByName',
                'administeredBy.surname as administeredBySurname',
            ])
            ->innerJoin('gibbonPerson', 'gibbonMedicalProtocolAdministration.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->innerJoin('gibbonMedicalProtocolAuthorization', 'gibbonMedicalProtocolAdministration.gibbonMedicalProtocolAuthorizationID=gibbonMedicalProtocolAuthorization.gibbonMedicalProtocolAuthorizationID')
            ->innerJoin('gibbonMedicalProtocol', 'gibbonMedicalProtocolAuthorization.gibbonMedicalProtocolID=gibbonMedicalProtocol.gibbonMedicalProtocolID')
            ->leftJoin('gibbonPerson as administeredBy', 'gibbonMedicalProtocolAdministration.administeredByID=administeredBy.gibbonPersonID')
            ->where('gibbonMedicalProtocolAdministration.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID)
            ->where('gibbonMedicalProtocolAdministration.date=:date')
            ->bindValue('date', $date);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query administration history for a specific child.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonPersonID
     * @param int $gibbonSchoolYearID
     * @return DataSet
     */
    public function queryAdministrationsByPerson(QueryCriteria $criteria, $gibbonPersonID, $gibbonSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonMedicalProtocolAdministration.gibbonMedicalProtocolAdministrationID',
                'gibbonMedicalProtocolAdministration.date',
                'gibbonMedicalProtocolAdministration.time',
                'gibbonMedicalProtocolAdministration.doseGiven',
                'gibbonMedicalProtocolAdministration.doseMg',
                'gibbonMedicalProtocolAdministration.concentration',
                'gibbonMedicalProtocolAdministration.weightAtTimeKg',
                'gibbonMedicalProtocolAdministration.temperatureC',
                'gibbonMedicalProtocolAdministration.temperatureMethod',
                'gibbonMedicalProtocolAdministration.reason',
                'gibbonMedicalProtocolAdministration.observations',
                'gibbonMedicalProtocolAdministration.followUpTime',
                'gibbonMedicalProtocolAdministration.followUpCompleted',
                'gibbonMedicalProtocolAdministration.followUpNotes',
                'gibbonMedicalProtocolAdministration.parentNotified',
                'gibbonMedicalProtocolAdministration.parentNotifiedTime',
                'gibbonMedicalProtocolAdministration.parentAcknowledged',
                'gibbonMedicalProtocolAdministration.parentAcknowledgedTime',
                'gibbonMedicalProtocolAdministration.timestampCreated',
                'gibbonMedicalProtocol.name as protocolName',
                'gibbonMedicalProtocol.formCode',
                'gibbonMedicalProtocol.type as protocolType',
                'administeredBy.preferredName as administeredByName',
                'administeredBy.surname as administeredBySurname',
                'witnessedBy.preferredName as witnessedByName',
                'witnessedBy.surname as witnessedBySurname',
            ])
            ->innerJoin('gibbonMedicalProtocolAuthorization', 'gibbonMedicalProtocolAdministration.gibbonMedicalProtocolAuthorizationID=gibbonMedicalProtocolAuthorization.gibbonMedicalProtocolAuthorizationID')
            ->innerJoin('gibbonMedicalProtocol', 'gibbonMedicalProtocolAuthorization.gibbonMedicalProtocolID=gibbonMedicalProtocol.gibbonMedicalProtocolID')
            ->leftJoin('gibbonPerson as administeredBy', 'gibbonMedicalProtocolAdministration.administeredByID=administeredBy.gibbonPersonID')
            ->leftJoin('gibbonPerson as witnessedBy', 'gibbonMedicalProtocolAdministration.witnessedByID=witnessedBy.gibbonPersonID')
            ->where('gibbonMedicalProtocolAdministration.gibbonPersonID=:gibbonPersonID')
            ->bindValue('gibbonPersonID', $gibbonPersonID)
            ->where('gibbonMedicalProtocolAdministration.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Get administrations for a specific child on a specific date.
     *
     * @param int $gibbonPersonID
     * @param string $date
     * @return \Gibbon\Database\Result
     */
    public function selectAdministrationsByPersonAndDate($gibbonPersonID, $date)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonMedicalProtocolAdministration.*',
                'gibbonMedicalProtocol.name as protocolName',
                'gibbonMedicalProtocol.formCode',
                'gibbonMedicalProtocol.type as protocolType',
                'gibbonMedicalProtocol.intervalMinutes',
                'administeredBy.preferredName as administeredByName',
                'administeredBy.surname as administeredBySurname',
            ])
            ->innerJoin('gibbonMedicalProtocolAuthorization', 'gibbonMedicalProtocolAdministration.gibbonMedicalProtocolAuthorizationID=gibbonMedicalProtocolAuthorization.gibbonMedicalProtocolAuthorizationID')
            ->innerJoin('gibbonMedicalProtocol', 'gibbonMedicalProtocolAuthorization.gibbonMedicalProtocolID=gibbonMedicalProtocol.gibbonMedicalProtocolID')
            ->leftJoin('gibbonPerson as administeredBy', 'gibbonMedicalProtocolAdministration.administeredByID=administeredBy.gibbonPersonID')
            ->where('gibbonMedicalProtocolAdministration.gibbonPersonID=:gibbonPersonID')
            ->bindValue('gibbonPersonID', $gibbonPersonID)
            ->where('gibbonMedicalProtocolAdministration.date=:date')
            ->bindValue('date', $date)
            ->orderBy(['gibbonMedicalProtocolAdministration.time ASC']);

        return $this->runSelect($query);
    }

    /**
     * Get the last administration for a child and protocol.
     *
     * @param int $gibbonPersonID
     * @param int $gibbonMedicalProtocolID
     * @return array|false
     */
    public function getLastAdministration($gibbonPersonID, $gibbonMedicalProtocolID)
    {
        $data = [
            'gibbonPersonID' => $gibbonPersonID,
            'gibbonMedicalProtocolID' => $gibbonMedicalProtocolID,
        ];
        $sql = "SELECT
                    gibbonMedicalProtocolAdministration.*,
                    gibbonMedicalProtocol.name as protocolName,
                    gibbonMedicalProtocol.formCode,
                    gibbonMedicalProtocol.intervalMinutes,
                    CONCAT(gibbonMedicalProtocolAdministration.date, ' ', gibbonMedicalProtocolAdministration.time) as administrationDateTime
                FROM gibbonMedicalProtocolAdministration
                INNER JOIN gibbonMedicalProtocolAuthorization
                    ON gibbonMedicalProtocolAdministration.gibbonMedicalProtocolAuthorizationID=gibbonMedicalProtocolAuthorization.gibbonMedicalProtocolAuthorizationID
                INNER JOIN gibbonMedicalProtocol
                    ON gibbonMedicalProtocolAuthorization.gibbonMedicalProtocolID=gibbonMedicalProtocol.gibbonMedicalProtocolID
                WHERE gibbonMedicalProtocolAdministration.gibbonPersonID=:gibbonPersonID
                AND gibbonMedicalProtocolAuthorization.gibbonMedicalProtocolID=:gibbonMedicalProtocolID
                ORDER BY gibbonMedicalProtocolAdministration.date DESC, gibbonMedicalProtocolAdministration.time DESC
                LIMIT 1";

        return $this->db()->selectOne($sql, $data);
    }

    /**
     * Check if a protocol can be administered to a child (respects interval requirements).
     * Returns true if administration is allowed, or an array with error info if not.
     *
     * @param int $gibbonPersonID
     * @param int $gibbonMedicalProtocolID
     * @param int|null $intervalMinutes Override interval (uses protocol default if null)
     * @return bool|array True if can administer, or array with 'canAdminister', 'reason', 'nextAllowedTime'
     */
    public function canAdminister($gibbonPersonID, $gibbonMedicalProtocolID, $intervalMinutes = null)
    {
        $lastAdmin = $this->getLastAdministration($gibbonPersonID, $gibbonMedicalProtocolID);

        if (!$lastAdmin) {
            return true;
        }

        // Use provided interval or protocol's default interval
        $interval = $intervalMinutes ?? $lastAdmin['intervalMinutes'];

        // If no interval requirement, allow administration
        if (empty($interval)) {
            return true;
        }

        $lastDateTime = new \DateTime($lastAdmin['administrationDateTime']);
        $now = new \DateTime();
        $nextAllowedTime = clone $lastDateTime;
        $nextAllowedTime->modify("+{$interval} minutes");

        if ($now >= $nextAllowedTime) {
            return true;
        }

        $remainingMinutes = ceil(($nextAllowedTime->getTimestamp() - $now->getTimestamp()) / 60);

        return [
            'canAdminister' => false,
            'reason' => 'Minimum interval between doses has not elapsed',
            'lastAdministration' => $lastAdmin,
            'intervalMinutes' => $interval,
            'nextAllowedTime' => $nextAllowedTime->format('Y-m-d H:i:s'),
            'remainingMinutes' => $remainingMinutes,
        ];
    }

    /**
     * Log a protocol administration.
     *
     * @param array $data Administration data
     * @return int|false The new administration ID or false on failure
     */
    public function logAdministration(array $data)
    {
        $requiredFields = [
            'gibbonMedicalProtocolAuthorizationID',
            'gibbonPersonID',
            'gibbonSchoolYearID',
            'date',
            'time',
            'administeredByID',
            'doseGiven',
            'weightAtTimeKg',
        ];

        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                return false;
            }
        }

        return $this->insert([
            'gibbonMedicalProtocolAuthorizationID' => $data['gibbonMedicalProtocolAuthorizationID'],
            'gibbonPersonID' => $data['gibbonPersonID'],
            'gibbonSchoolYearID' => $data['gibbonSchoolYearID'],
            'date' => $data['date'],
            'time' => $data['time'],
            'administeredByID' => $data['administeredByID'],
            'witnessedByID' => $data['witnessedByID'] ?? null,
            'doseGiven' => $data['doseGiven'],
            'doseMg' => $data['doseMg'] ?? null,
            'concentration' => $data['concentration'] ?? null,
            'weightAtTimeKg' => $data['weightAtTimeKg'],
            'temperatureC' => $data['temperatureC'] ?? null,
            'temperatureMethod' => $data['temperatureMethod'] ?? null,
            'reason' => $data['reason'] ?? null,
            'observations' => $data['observations'] ?? null,
            'followUpTime' => $data['followUpTime'] ?? null,
            'followUpCompleted' => $data['followUpCompleted'] ?? 'N',
            'followUpNotes' => $data['followUpNotes'] ?? null,
            'parentNotified' => $data['parentNotified'] ?? 'N',
            'parentNotifiedTime' => $data['parentNotifiedTime'] ?? null,
        ]);
    }

    /**
     * Get administration history for a child with optional date range.
     *
     * @param int $gibbonPersonID
     * @param string|null $dateStart
     * @param string|null $dateEnd
     * @return \Gibbon\Database\Result
     */
    public function getAdministrationHistory($gibbonPersonID, $dateStart = null, $dateEnd = null)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonMedicalProtocolAdministration.*',
                'gibbonMedicalProtocol.name as protocolName',
                'gibbonMedicalProtocol.formCode',
                'gibbonMedicalProtocol.type as protocolType',
                'administeredBy.preferredName as administeredByName',
                'administeredBy.surname as administeredBySurname',
                'witnessedBy.preferredName as witnessedByName',
                'witnessedBy.surname as witnessedBySurname',
            ])
            ->innerJoin('gibbonMedicalProtocolAuthorization', 'gibbonMedicalProtocolAdministration.gibbonMedicalProtocolAuthorizationID=gibbonMedicalProtocolAuthorization.gibbonMedicalProtocolAuthorizationID')
            ->innerJoin('gibbonMedicalProtocol', 'gibbonMedicalProtocolAuthorization.gibbonMedicalProtocolID=gibbonMedicalProtocol.gibbonMedicalProtocolID')
            ->leftJoin('gibbonPerson as administeredBy', 'gibbonMedicalProtocolAdministration.administeredByID=administeredBy.gibbonPersonID')
            ->leftJoin('gibbonPerson as witnessedBy', 'gibbonMedicalProtocolAdministration.witnessedByID=witnessedBy.gibbonPersonID')
            ->where('gibbonMedicalProtocolAdministration.gibbonPersonID=:gibbonPersonID')
            ->bindValue('gibbonPersonID', $gibbonPersonID);

        if ($dateStart) {
            $query->where('gibbonMedicalProtocolAdministration.date >= :dateStart')
                  ->bindValue('dateStart', $dateStart);
        }

        if ($dateEnd) {
            $query->where('gibbonMedicalProtocolAdministration.date <= :dateEnd')
                  ->bindValue('dateEnd', $dateEnd);
        }

        $query->orderBy(['gibbonMedicalProtocolAdministration.date DESC', 'gibbonMedicalProtocolAdministration.time DESC']);

        return $this->runSelect($query);
    }

    /**
     * Get administration summary statistics for a specific date.
     *
     * @param int $gibbonSchoolYearID
     * @param string $date
     * @return array
     */
    public function getAdministrationSummaryByDate($gibbonSchoolYearID, $date)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID, 'date' => $date];
        $sql = "SELECT
                    COUNT(*) as totalAdministrations,
                    COUNT(DISTINCT gibbonMedicalProtocolAdministration.gibbonPersonID) as childrenCount,
                    SUM(CASE WHEN gibbonMedicalProtocol.formCode='FO-0647' THEN 1 ELSE 0 END) as acetaminophenCount,
                    SUM(CASE WHEN gibbonMedicalProtocol.formCode='FO-0646' THEN 1 ELSE 0 END) as insectRepellentCount,
                    SUM(CASE WHEN gibbonMedicalProtocolAdministration.followUpCompleted='Y' THEN 1 ELSE 0 END) as followUpsCompleted,
                    SUM(CASE WHEN gibbonMedicalProtocolAdministration.followUpTime IS NOT NULL AND gibbonMedicalProtocolAdministration.followUpCompleted='N' THEN 1 ELSE 0 END) as followUpsPending,
                    SUM(CASE WHEN gibbonMedicalProtocolAdministration.parentNotified='Y' THEN 1 ELSE 0 END) as parentsNotified,
                    SUM(CASE WHEN gibbonMedicalProtocolAdministration.parentAcknowledged='Y' THEN 1 ELSE 0 END) as parentsAcknowledged
                FROM gibbonMedicalProtocolAdministration
                INNER JOIN gibbonMedicalProtocolAuthorization
                    ON gibbonMedicalProtocolAdministration.gibbonMedicalProtocolAuthorizationID=gibbonMedicalProtocolAuthorization.gibbonMedicalProtocolAuthorizationID
                INNER JOIN gibbonMedicalProtocol
                    ON gibbonMedicalProtocolAuthorization.gibbonMedicalProtocolID=gibbonMedicalProtocol.gibbonMedicalProtocolID
                WHERE gibbonMedicalProtocolAdministration.gibbonSchoolYearID=:gibbonSchoolYearID
                AND gibbonMedicalProtocolAdministration.date=:date";

        return $this->db()->selectOne($sql, $data) ?: [
            'totalAdministrations' => 0,
            'childrenCount' => 0,
            'acetaminophenCount' => 0,
            'insectRepellentCount' => 0,
            'followUpsCompleted' => 0,
            'followUpsPending' => 0,
            'parentsNotified' => 0,
            'parentsAcknowledged' => 0,
        ];
    }

    /**
     * Generate compliance report for Quebec protocols.
     *
     * @param int $gibbonSchoolYearID
     * @param string $dateStart
     * @param string $dateEnd
     * @param int|null $gibbonMedicalProtocolID Filter by protocol (optional)
     * @return array
     */
    public function generateComplianceReport($gibbonSchoolYearID, $dateStart, $dateEnd, $gibbonMedicalProtocolID = null)
    {
        $data = [
            'gibbonSchoolYearID' => $gibbonSchoolYearID,
            'dateStart' => $dateStart,
            'dateEnd' => $dateEnd,
        ];

        $protocolFilter = '';
        if ($gibbonMedicalProtocolID) {
            $protocolFilter = 'AND gibbonMedicalProtocol.gibbonMedicalProtocolID=:gibbonMedicalProtocolID';
            $data['gibbonMedicalProtocolID'] = $gibbonMedicalProtocolID;
        }

        // Summary statistics
        $sqlSummary = "SELECT
                    gibbonMedicalProtocol.name as protocolName,
                    gibbonMedicalProtocol.formCode,
                    COUNT(*) as totalAdministrations,
                    COUNT(DISTINCT gibbonMedicalProtocolAdministration.gibbonPersonID) as uniqueChildren,
                    COUNT(DISTINCT gibbonMedicalProtocolAdministration.administeredByID) as uniqueStaff,
                    AVG(gibbonMedicalProtocolAdministration.doseMg) as avgDoseMg,
                    MIN(gibbonMedicalProtocolAdministration.doseMg) as minDoseMg,
                    MAX(gibbonMedicalProtocolAdministration.doseMg) as maxDoseMg,
                    SUM(CASE WHEN gibbonMedicalProtocolAdministration.followUpCompleted='Y' THEN 1 ELSE 0 END) as followUpsCompleted,
                    SUM(CASE WHEN gibbonMedicalProtocolAdministration.followUpTime IS NOT NULL THEN 1 ELSE 0 END) as followUpsRequired,
                    SUM(CASE WHEN gibbonMedicalProtocolAdministration.parentNotified='Y' THEN 1 ELSE 0 END) as parentsNotified,
                    SUM(CASE WHEN gibbonMedicalProtocolAdministration.parentAcknowledged='Y' THEN 1 ELSE 0 END) as parentsAcknowledged
                FROM gibbonMedicalProtocolAdministration
                INNER JOIN gibbonMedicalProtocolAuthorization
                    ON gibbonMedicalProtocolAdministration.gibbonMedicalProtocolAuthorizationID=gibbonMedicalProtocolAuthorization.gibbonMedicalProtocolAuthorizationID
                INNER JOIN gibbonMedicalProtocol
                    ON gibbonMedicalProtocolAuthorization.gibbonMedicalProtocolID=gibbonMedicalProtocol.gibbonMedicalProtocolID
                WHERE gibbonMedicalProtocolAdministration.gibbonSchoolYearID=:gibbonSchoolYearID
                AND gibbonMedicalProtocolAdministration.date >= :dateStart
                AND gibbonMedicalProtocolAdministration.date <= :dateEnd
                {$protocolFilter}
                GROUP BY gibbonMedicalProtocol.gibbonMedicalProtocolID
                ORDER BY gibbonMedicalProtocol.name";

        $summaryByProtocol = $this->db()->select($sql = $sqlSummary, $data)->fetchAll();

        // Daily breakdown
        $sqlDaily = "SELECT
                    gibbonMedicalProtocolAdministration.date,
                    gibbonMedicalProtocol.formCode,
                    COUNT(*) as administrations,
                    COUNT(DISTINCT gibbonMedicalProtocolAdministration.gibbonPersonID) as children
                FROM gibbonMedicalProtocolAdministration
                INNER JOIN gibbonMedicalProtocolAuthorization
                    ON gibbonMedicalProtocolAdministration.gibbonMedicalProtocolAuthorizationID=gibbonMedicalProtocolAuthorization.gibbonMedicalProtocolAuthorizationID
                INNER JOIN gibbonMedicalProtocol
                    ON gibbonMedicalProtocolAuthorization.gibbonMedicalProtocolID=gibbonMedicalProtocol.gibbonMedicalProtocolID
                WHERE gibbonMedicalProtocolAdministration.gibbonSchoolYearID=:gibbonSchoolYearID
                AND gibbonMedicalProtocolAdministration.date >= :dateStart
                AND gibbonMedicalProtocolAdministration.date <= :dateEnd
                {$protocolFilter}
                GROUP BY gibbonMedicalProtocolAdministration.date, gibbonMedicalProtocol.formCode
                ORDER BY gibbonMedicalProtocolAdministration.date DESC";

        $dailyBreakdown = $this->db()->select($sqlDaily, $data)->fetchAll();

        // Compliance checks - interval violations (administrations within minimum interval)
        $sqlViolations = "SELECT
                    a1.gibbonMedicalProtocolAdministrationID,
                    a1.gibbonPersonID,
                    a1.date,
                    a1.time,
                    gibbonPerson.preferredName,
                    gibbonPerson.surname,
                    gibbonMedicalProtocol.name as protocolName,
                    gibbonMedicalProtocol.formCode,
                    gibbonMedicalProtocol.intervalMinutes,
                    TIMESTAMPDIFF(MINUTE,
                        CONCAT(a2.date, ' ', a2.time),
                        CONCAT(a1.date, ' ', a1.time)
                    ) as minutesSincePrevious
                FROM gibbonMedicalProtocolAdministration a1
                INNER JOIN gibbonMedicalProtocolAdministration a2
                    ON a1.gibbonPersonID=a2.gibbonPersonID
                    AND a1.gibbonMedicalProtocolAuthorizationID=a2.gibbonMedicalProtocolAuthorizationID
                    AND a1.gibbonMedicalProtocolAdministrationID > a2.gibbonMedicalProtocolAdministrationID
                INNER JOIN gibbonMedicalProtocolAuthorization
                    ON a1.gibbonMedicalProtocolAuthorizationID=gibbonMedicalProtocolAuthorization.gibbonMedicalProtocolAuthorizationID
                INNER JOIN gibbonMedicalProtocol
                    ON gibbonMedicalProtocolAuthorization.gibbonMedicalProtocolID=gibbonMedicalProtocol.gibbonMedicalProtocolID
                INNER JOIN gibbonPerson
                    ON a1.gibbonPersonID=gibbonPerson.gibbonPersonID
                WHERE a1.gibbonSchoolYearID=:gibbonSchoolYearID
                AND a1.date >= :dateStart
                AND a1.date <= :dateEnd
                AND gibbonMedicalProtocol.intervalMinutes IS NOT NULL
                AND TIMESTAMPDIFF(MINUTE, CONCAT(a2.date, ' ', a2.time), CONCAT(a1.date, ' ', a1.time)) < gibbonMedicalProtocol.intervalMinutes
                {$protocolFilter}
                ORDER BY a1.date DESC, a1.time DESC";

        $intervalViolations = $this->db()->select($sqlViolations, $data)->fetchAll();

        return [
            'period' => [
                'start' => $dateStart,
                'end' => $dateEnd,
            ],
            'summaryByProtocol' => $summaryByProtocol,
            'dailyBreakdown' => $dailyBreakdown,
            'complianceIssues' => [
                'intervalViolations' => $intervalViolations,
                'violationCount' => count($intervalViolations),
            ],
            'generatedAt' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Mark follow-up as completed.
     *
     * @param int $gibbonMedicalProtocolAdministrationID
     * @param string|null $notes
     * @return bool
     */
    public function markFollowUpCompleted($gibbonMedicalProtocolAdministrationID, $notes = null)
    {
        $data = ['followUpCompleted' => 'Y'];
        if ($notes) {
            $data['followUpNotes'] = $notes;
        }
        return $this->update($gibbonMedicalProtocolAdministrationID, $data);
    }

    /**
     * Mark parent as notified for an administration.
     *
     * @param int $gibbonMedicalProtocolAdministrationID
     * @return bool
     */
    public function markParentNotified($gibbonMedicalProtocolAdministrationID)
    {
        return $this->update($gibbonMedicalProtocolAdministrationID, [
            'parentNotified' => 'Y',
            'parentNotifiedTime' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Mark parent acknowledgment for an administration.
     *
     * @param int $gibbonMedicalProtocolAdministrationID
     * @return bool
     */
    public function markParentAcknowledged($gibbonMedicalProtocolAdministrationID)
    {
        return $this->update($gibbonMedicalProtocolAdministrationID, [
            'parentAcknowledged' => 'Y',
            'parentAcknowledgedTime' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Select administrations pending follow-up.
     *
     * @param int $gibbonSchoolYearID
     * @param string|null $date Filter to specific date (defaults to today)
     * @return \Gibbon\Database\Result
     */
    public function selectAdministrationsPendingFollowUp($gibbonSchoolYearID, $date = null)
    {
        $date = $date ?? date('Y-m-d');

        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonMedicalProtocolAdministration.*',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
                'gibbonMedicalProtocol.name as protocolName',
                'gibbonMedicalProtocol.formCode',
                'administeredBy.preferredName as administeredByName',
                'administeredBy.surname as administeredBySurname',
            ])
            ->innerJoin('gibbonPerson', 'gibbonMedicalProtocolAdministration.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->innerJoin('gibbonMedicalProtocolAuthorization', 'gibbonMedicalProtocolAdministration.gibbonMedicalProtocolAuthorizationID=gibbonMedicalProtocolAuthorization.gibbonMedicalProtocolAuthorizationID')
            ->innerJoin('gibbonMedicalProtocol', 'gibbonMedicalProtocolAuthorization.gibbonMedicalProtocolID=gibbonMedicalProtocol.gibbonMedicalProtocolID')
            ->leftJoin('gibbonPerson as administeredBy', 'gibbonMedicalProtocolAdministration.administeredByID=administeredBy.gibbonPersonID')
            ->where('gibbonMedicalProtocolAdministration.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID)
            ->where('gibbonMedicalProtocolAdministration.date=:date')
            ->bindValue('date', $date)
            ->where('gibbonMedicalProtocolAdministration.followUpTime IS NOT NULL')
            ->where("gibbonMedicalProtocolAdministration.followUpCompleted='N'")
            ->orderBy(['gibbonMedicalProtocolAdministration.followUpTime ASC']);

        return $this->runSelect($query);
    }

    /**
     * Select administrations pending parent notification.
     *
     * @param int $gibbonSchoolYearID
     * @return \Gibbon\Database\Result
     */
    public function selectAdministrationsPendingNotification($gibbonSchoolYearID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonMedicalProtocolAdministration.*',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonMedicalProtocol.name as protocolName',
                'gibbonMedicalProtocol.formCode',
            ])
            ->innerJoin('gibbonPerson', 'gibbonMedicalProtocolAdministration.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->innerJoin('gibbonMedicalProtocolAuthorization', 'gibbonMedicalProtocolAdministration.gibbonMedicalProtocolAuthorizationID=gibbonMedicalProtocolAuthorization.gibbonMedicalProtocolAuthorizationID')
            ->innerJoin('gibbonMedicalProtocol', 'gibbonMedicalProtocolAuthorization.gibbonMedicalProtocolID=gibbonMedicalProtocol.gibbonMedicalProtocolID')
            ->where('gibbonMedicalProtocolAdministration.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID)
            ->where("gibbonMedicalProtocolAdministration.parentNotified='N'")
            ->orderBy(['gibbonMedicalProtocolAdministration.date DESC', 'gibbonMedicalProtocolAdministration.time DESC']);

        return $this->runSelect($query);
    }

    /**
     * Get count of administrations in last 24 hours for a child and protocol.
     * Used to enforce maxDailyDoses limit.
     *
     * @param int $gibbonPersonID
     * @param int $gibbonMedicalProtocolID
     * @return int
     */
    public function getAdministrationCountLast24Hours($gibbonPersonID, $gibbonMedicalProtocolID)
    {
        $data = [
            'gibbonPersonID' => $gibbonPersonID,
            'gibbonMedicalProtocolID' => $gibbonMedicalProtocolID,
        ];
        $sql = "SELECT COUNT(*) as count
                FROM gibbonMedicalProtocolAdministration
                INNER JOIN gibbonMedicalProtocolAuthorization
                    ON gibbonMedicalProtocolAdministration.gibbonMedicalProtocolAuthorizationID=gibbonMedicalProtocolAuthorization.gibbonMedicalProtocolAuthorizationID
                WHERE gibbonMedicalProtocolAdministration.gibbonPersonID=:gibbonPersonID
                AND gibbonMedicalProtocolAuthorization.gibbonMedicalProtocolID=:gibbonMedicalProtocolID
                AND CONCAT(gibbonMedicalProtocolAdministration.date, ' ', gibbonMedicalProtocolAdministration.time) >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";

        $result = $this->db()->selectOne($sql, $data);
        return (int)($result['count'] ?? 0);
    }

    /**
     * Check if daily dose limit has been reached.
     *
     * @param int $gibbonPersonID
     * @param int $gibbonMedicalProtocolID
     * @param int $maxDailyDoses
     * @return bool True if limit reached
     */
    public function isDailyLimitReached($gibbonPersonID, $gibbonMedicalProtocolID, $maxDailyDoses)
    {
        if (empty($maxDailyDoses)) {
            return false;
        }

        $count = $this->getAdministrationCountLast24Hours($gibbonPersonID, $gibbonMedicalProtocolID);
        return $count >= $maxDailyDoses;
    }
}
