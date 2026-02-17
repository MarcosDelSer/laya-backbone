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

namespace Gibbon\Module\DevelopmentProfile\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\QueryableGateway;

/**
 * Development Observation Gateway
 *
 * Handles observable behavior documentation for developmental profiles.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class ObservationGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonDevelopmentObservation';
    private static $primaryKey = 'gibbonDevelopmentObservationID';

    private static $searchableColumns = ['gibbonDevelopmentObservation.behaviorDescription', 'gibbonDevelopmentObservation.context', 'gibbonPerson.preferredName', 'gibbonPerson.surname'];

    /**
     * Query observations with criteria support.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonDevelopmentProfileID
     * @return DataSet
     */
    public function queryObservations(QueryCriteria $criteria, $gibbonDevelopmentProfileID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonDevelopmentObservation.gibbonDevelopmentObservationID',
                'gibbonDevelopmentObservation.gibbonDevelopmentProfileID',
                'gibbonDevelopmentObservation.domain',
                'gibbonDevelopmentObservation.observedAt',
                'gibbonDevelopmentObservation.observerID',
                'gibbonDevelopmentObservation.observerType',
                'gibbonDevelopmentObservation.behaviorDescription',
                'gibbonDevelopmentObservation.context',
                'gibbonDevelopmentObservation.isMilestone',
                'gibbonDevelopmentObservation.isConcern',
                'gibbonDevelopmentObservation.attachments',
                'gibbonDevelopmentObservation.timestampCreated',
                'observer.preferredName as observerName',
                'observer.surname as observerSurname',
            ])
            ->leftJoin('gibbonPerson as observer', 'gibbonDevelopmentObservation.observerID=observer.gibbonPersonID')
            ->where('gibbonDevelopmentObservation.gibbonDevelopmentProfileID=:gibbonDevelopmentProfileID')
            ->bindValue('gibbonDevelopmentProfileID', $gibbonDevelopmentProfileID);

        $criteria->addFilterRules([
            'domain' => function ($query, $domain) {
                return $query
                    ->where('gibbonDevelopmentObservation.domain=:domain')
                    ->bindValue('domain', $domain);
            },
            'observerType' => function ($query, $observerType) {
                return $query
                    ->where('gibbonDevelopmentObservation.observerType=:observerType')
                    ->bindValue('observerType', $observerType);
            },
            'isMilestone' => function ($query, $value) {
                return $query
                    ->where('gibbonDevelopmentObservation.isMilestone=:isMilestone')
                    ->bindValue('isMilestone', $value);
            },
            'isConcern' => function ($query, $value) {
                return $query
                    ->where('gibbonDevelopmentObservation.isConcern=:isConcern')
                    ->bindValue('isConcern', $value);
            },
            'dateFrom' => function ($query, $dateFrom) {
                return $query
                    ->where('DATE(gibbonDevelopmentObservation.observedAt) >= :dateFrom')
                    ->bindValue('dateFrom', $dateFrom);
            },
            'dateTo' => function ($query, $dateTo) {
                return $query
                    ->where('DATE(gibbonDevelopmentObservation.observedAt) <= :dateTo')
                    ->bindValue('dateTo', $dateTo);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query observations by domain.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonDevelopmentProfileID
     * @param string $domain
     * @return DataSet
     */
    public function queryObservationsByDomain(QueryCriteria $criteria, $gibbonDevelopmentProfileID, $domain)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonDevelopmentObservation.gibbonDevelopmentObservationID',
                'gibbonDevelopmentObservation.domain',
                'gibbonDevelopmentObservation.observedAt',
                'gibbonDevelopmentObservation.observerType',
                'gibbonDevelopmentObservation.behaviorDescription',
                'gibbonDevelopmentObservation.context',
                'gibbonDevelopmentObservation.isMilestone',
                'gibbonDevelopmentObservation.isConcern',
                'gibbonDevelopmentObservation.timestampCreated',
                'observer.preferredName as observerName',
                'observer.surname as observerSurname',
            ])
            ->leftJoin('gibbonPerson as observer', 'gibbonDevelopmentObservation.observerID=observer.gibbonPersonID')
            ->where('gibbonDevelopmentObservation.gibbonDevelopmentProfileID=:gibbonDevelopmentProfileID')
            ->bindValue('gibbonDevelopmentProfileID', $gibbonDevelopmentProfileID)
            ->where('gibbonDevelopmentObservation.domain=:domain')
            ->bindValue('domain', $domain);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query recent observations for a school year.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonSchoolYearID
     * @param int $days
     * @return DataSet
     */
    public function queryRecentObservations(QueryCriteria $criteria, $gibbonSchoolYearID, $days = 7)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonDevelopmentObservation.gibbonDevelopmentObservationID',
                'gibbonDevelopmentObservation.gibbonDevelopmentProfileID',
                'gibbonDevelopmentObservation.domain',
                'gibbonDevelopmentObservation.observedAt',
                'gibbonDevelopmentObservation.observerType',
                'gibbonDevelopmentObservation.behaviorDescription',
                'gibbonDevelopmentObservation.isMilestone',
                'gibbonDevelopmentObservation.isConcern',
                'gibbonDevelopmentObservation.timestampCreated',
                'child.preferredName',
                'child.surname',
                'child.image_240',
                'observer.preferredName as observerName',
                'observer.surname as observerSurname',
            ])
            ->innerJoin('gibbonDevelopmentProfile', 'gibbonDevelopmentObservation.gibbonDevelopmentProfileID=gibbonDevelopmentProfile.gibbonDevelopmentProfileID')
            ->innerJoin('gibbonPerson as child', 'gibbonDevelopmentProfile.gibbonPersonID=child.gibbonPersonID')
            ->leftJoin('gibbonPerson as observer', 'gibbonDevelopmentObservation.observerID=observer.gibbonPersonID')
            ->where('gibbonDevelopmentProfile.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID)
            ->where('gibbonDevelopmentObservation.observedAt >= DATE_SUB(NOW(), INTERVAL :days DAY)')
            ->bindValue('days', $days);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Get observation with full details.
     *
     * @param int $gibbonDevelopmentObservationID
     * @return array|false
     */
    public function getObservationWithDetails($gibbonDevelopmentObservationID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonDevelopmentObservation.*',
                'observer.preferredName as observerName',
                'observer.surname as observerSurname',
                'child.preferredName',
                'child.surname',
                'child.image_240',
            ])
            ->innerJoin('gibbonDevelopmentProfile', 'gibbonDevelopmentObservation.gibbonDevelopmentProfileID=gibbonDevelopmentProfile.gibbonDevelopmentProfileID')
            ->innerJoin('gibbonPerson as child', 'gibbonDevelopmentProfile.gibbonPersonID=child.gibbonPersonID')
            ->leftJoin('gibbonPerson as observer', 'gibbonDevelopmentObservation.observerID=observer.gibbonPersonID')
            ->where('gibbonDevelopmentObservation.gibbonDevelopmentObservationID=:gibbonDevelopmentObservationID')
            ->bindValue('gibbonDevelopmentObservationID', $gibbonDevelopmentObservationID);

        return $this->runSelect($query)->fetch();
    }

    /**
     * Select observations for a date range.
     *
     * @param int $gibbonDevelopmentProfileID
     * @param string $dateStart
     * @param string $dateEnd
     * @return \Gibbon\Database\Result
     */
    public function selectObservationsByDateRange($gibbonDevelopmentProfileID, $dateStart, $dateEnd)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonDevelopmentObservation.*',
                'observer.preferredName as observerName',
                'observer.surname as observerSurname',
            ])
            ->leftJoin('gibbonPerson as observer', 'gibbonDevelopmentObservation.observerID=observer.gibbonPersonID')
            ->where('gibbonDevelopmentObservation.gibbonDevelopmentProfileID=:gibbonDevelopmentProfileID')
            ->bindValue('gibbonDevelopmentProfileID', $gibbonDevelopmentProfileID)
            ->where('DATE(gibbonDevelopmentObservation.observedAt) >= :dateStart')
            ->bindValue('dateStart', $dateStart)
            ->where('DATE(gibbonDevelopmentObservation.observedAt) <= :dateEnd')
            ->bindValue('dateEnd', $dateEnd)
            ->orderBy(['gibbonDevelopmentObservation.observedAt DESC']);

        return $this->runSelect($query);
    }

    /**
     * Get observation statistics by domain for a profile.
     *
     * @param int $gibbonDevelopmentProfileID
     * @return array
     */
    public function getObservationStatsByDomain($gibbonDevelopmentProfileID)
    {
        $data = ['gibbonDevelopmentProfileID' => $gibbonDevelopmentProfileID];
        $sql = "SELECT
                    domain,
                    COUNT(*) as totalObservations,
                    SUM(CASE WHEN isMilestone='Y' THEN 1 ELSE 0 END) as milestoneCount,
                    SUM(CASE WHEN isConcern='Y' THEN 1 ELSE 0 END) as concernCount,
                    SUM(CASE WHEN observerType='educator' THEN 1 ELSE 0 END) as educatorCount,
                    SUM(CASE WHEN observerType='parent' THEN 1 ELSE 0 END) as parentCount,
                    SUM(CASE WHEN observerType='specialist' THEN 1 ELSE 0 END) as specialistCount,
                    MIN(observedAt) as firstObservation,
                    MAX(observedAt) as lastObservation
                FROM gibbonDevelopmentObservation
                WHERE gibbonDevelopmentProfileID=:gibbonDevelopmentProfileID
                GROUP BY domain
                ORDER BY FIELD(domain, 'affective', 'social', 'language', 'cognitive', 'gross_motor', 'fine_motor')";

        return $this->db()->select($sql, $data)->fetchAll();
    }

    /**
     * Get recent concern observations.
     *
     * @param int $gibbonSchoolYearID
     * @param int $limit
     * @return array
     */
    public function getRecentConcerns($gibbonSchoolYearID, $limit = 10)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID, 'limit' => $limit];
        $sql = "SELECT
                    gibbonDevelopmentObservation.gibbonDevelopmentObservationID,
                    gibbonDevelopmentObservation.domain,
                    gibbonDevelopmentObservation.observedAt,
                    gibbonDevelopmentObservation.behaviorDescription,
                    child.gibbonPersonID,
                    child.preferredName,
                    child.surname,
                    child.image_240,
                    observer.preferredName as observerName,
                    observer.surname as observerSurname
                FROM gibbonDevelopmentObservation
                INNER JOIN gibbonDevelopmentProfile ON gibbonDevelopmentObservation.gibbonDevelopmentProfileID=gibbonDevelopmentProfile.gibbonDevelopmentProfileID
                INNER JOIN gibbonPerson as child ON gibbonDevelopmentProfile.gibbonPersonID=child.gibbonPersonID
                LEFT JOIN gibbonPerson as observer ON gibbonDevelopmentObservation.observerID=observer.gibbonPersonID
                WHERE gibbonDevelopmentProfile.gibbonSchoolYearID=:gibbonSchoolYearID
                AND gibbonDevelopmentObservation.isConcern='Y'
                ORDER BY gibbonDevelopmentObservation.observedAt DESC
                LIMIT :limit";

        return $this->db()->select($sql, $data)->fetchAll();
    }

    /**
     * Get recent milestone observations.
     *
     * @param int $gibbonSchoolYearID
     * @param int $limit
     * @return array
     */
    public function getRecentMilestones($gibbonSchoolYearID, $limit = 10)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID, 'limit' => $limit];
        $sql = "SELECT
                    gibbonDevelopmentObservation.gibbonDevelopmentObservationID,
                    gibbonDevelopmentObservation.domain,
                    gibbonDevelopmentObservation.observedAt,
                    gibbonDevelopmentObservation.behaviorDescription,
                    child.gibbonPersonID,
                    child.preferredName,
                    child.surname,
                    child.image_240,
                    observer.preferredName as observerName,
                    observer.surname as observerSurname
                FROM gibbonDevelopmentObservation
                INNER JOIN gibbonDevelopmentProfile ON gibbonDevelopmentObservation.gibbonDevelopmentProfileID=gibbonDevelopmentProfile.gibbonDevelopmentProfileID
                INNER JOIN gibbonPerson as child ON gibbonDevelopmentProfile.gibbonPersonID=child.gibbonPersonID
                LEFT JOIN gibbonPerson as observer ON gibbonDevelopmentObservation.observerID=observer.gibbonPersonID
                WHERE gibbonDevelopmentProfile.gibbonSchoolYearID=:gibbonSchoolYearID
                AND gibbonDevelopmentObservation.isMilestone='Y'
                ORDER BY gibbonDevelopmentObservation.observedAt DESC
                LIMIT :limit";

        return $this->db()->select($sql, $data)->fetchAll();
    }

    /**
     * Log an observation for a child.
     *
     * @param int $gibbonDevelopmentProfileID
     * @param string $domain
     * @param string $observedAt
     * @param int $observerID
     * @param string $observerType
     * @param string $behaviorDescription
     * @param string|null $context
     * @param bool $isMilestone
     * @param bool $isConcern
     * @param array|null $attachments
     * @return int|false
     */
    public function logObservation($gibbonDevelopmentProfileID, $domain, $observedAt, $observerID, $observerType, $behaviorDescription, $context = null, $isMilestone = false, $isConcern = false, $attachments = null)
    {
        $data = [
            'gibbonDevelopmentProfileID' => $gibbonDevelopmentProfileID,
            'domain' => $domain,
            'observedAt' => $observedAt,
            'observerID' => $observerID,
            'observerType' => $observerType,
            'behaviorDescription' => $behaviorDescription,
            'isMilestone' => $isMilestone ? 'Y' : 'N',
            'isConcern' => $isConcern ? 'Y' : 'N',
        ];

        if ($context !== null) {
            $data['context'] = $context;
        }

        if ($attachments !== null) {
            $data['attachments'] = json_encode($attachments);
        }

        return $this->insert($data);
    }

    /**
     * Update observation milestone/concern flags.
     *
     * @param int $gibbonDevelopmentObservationID
     * @param bool $isMilestone
     * @param bool $isConcern
     * @return bool
     */
    public function updateObservationFlags($gibbonDevelopmentObservationID, $isMilestone, $isConcern)
    {
        return $this->update($gibbonDevelopmentObservationID, [
            'isMilestone' => $isMilestone ? 'Y' : 'N',
            'isConcern' => $isConcern ? 'Y' : 'N',
        ]);
    }

    /**
     * Select observations from parents for a profile.
     *
     * @param int $gibbonDevelopmentProfileID
     * @return \Gibbon\Database\Result
     */
    public function selectParentObservations($gibbonDevelopmentProfileID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonDevelopmentObservation.*',
                'observer.preferredName as observerName',
                'observer.surname as observerSurname',
            ])
            ->leftJoin('gibbonPerson as observer', 'gibbonDevelopmentObservation.observerID=observer.gibbonPersonID')
            ->where('gibbonDevelopmentObservation.gibbonDevelopmentProfileID=:gibbonDevelopmentProfileID')
            ->bindValue('gibbonDevelopmentProfileID', $gibbonDevelopmentProfileID)
            ->where("gibbonDevelopmentObservation.observerType='parent'")
            ->orderBy(['gibbonDevelopmentObservation.observedAt DESC']);

        return $this->runSelect($query);
    }
}
