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
 * Development Snapshot Gateway
 *
 * Handles monthly developmental snapshot data access for developmental profiles.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class SnapshotGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonDevelopmentSnapshot';
    private static $primaryKey = 'gibbonDevelopmentSnapshotID';

    private static $searchableColumns = ['gibbonDevelopmentSnapshot.recommendations'];

    /**
     * Query snapshots with criteria support.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonDevelopmentProfileID
     * @return DataSet
     */
    public function querySnapshots(QueryCriteria $criteria, $gibbonDevelopmentProfileID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonDevelopmentSnapshot.gibbonDevelopmentSnapshotID',
                'gibbonDevelopmentSnapshot.gibbonDevelopmentProfileID',
                'gibbonDevelopmentSnapshot.snapshotMonth',
                'gibbonDevelopmentSnapshot.ageMonths',
                'gibbonDevelopmentSnapshot.domainSummaries',
                'gibbonDevelopmentSnapshot.overallProgress',
                'gibbonDevelopmentSnapshot.strengths',
                'gibbonDevelopmentSnapshot.growthAreas',
                'gibbonDevelopmentSnapshot.recommendations',
                'gibbonDevelopmentSnapshot.generatedByID',
                'gibbonDevelopmentSnapshot.isParentShared',
                'gibbonDevelopmentSnapshot.timestampCreated',
                'gibbonDevelopmentSnapshot.timestampModified',
                'generatedBy.preferredName as generatedByName',
                'generatedBy.surname as generatedBySurname',
            ])
            ->leftJoin('gibbonPerson as generatedBy', 'gibbonDevelopmentSnapshot.generatedByID=generatedBy.gibbonPersonID')
            ->where('gibbonDevelopmentSnapshot.gibbonDevelopmentProfileID=:gibbonDevelopmentProfileID')
            ->bindValue('gibbonDevelopmentProfileID', $gibbonDevelopmentProfileID);

        $criteria->addFilterRules([
            'overallProgress' => function ($query, $progress) {
                return $query
                    ->where('gibbonDevelopmentSnapshot.overallProgress=:overallProgress')
                    ->bindValue('overallProgress', $progress);
            },
            'isParentShared' => function ($query, $value) {
                return $query
                    ->where('gibbonDevelopmentSnapshot.isParentShared=:isParentShared')
                    ->bindValue('isParentShared', $value);
            },
            'monthFrom' => function ($query, $monthFrom) {
                return $query
                    ->where('gibbonDevelopmentSnapshot.snapshotMonth >= :monthFrom')
                    ->bindValue('monthFrom', $monthFrom);
            },
            'monthTo' => function ($query, $monthTo) {
                return $query
                    ->where('gibbonDevelopmentSnapshot.snapshotMonth <= :monthTo')
                    ->bindValue('monthTo', $monthTo);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query snapshots for a school year.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonSchoolYearID
     * @return DataSet
     */
    public function querySnapshotsBySchoolYear(QueryCriteria $criteria, $gibbonSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonDevelopmentSnapshot.gibbonDevelopmentSnapshotID',
                'gibbonDevelopmentSnapshot.gibbonDevelopmentProfileID',
                'gibbonDevelopmentSnapshot.snapshotMonth',
                'gibbonDevelopmentSnapshot.ageMonths',
                'gibbonDevelopmentSnapshot.overallProgress',
                'gibbonDevelopmentSnapshot.isParentShared',
                'gibbonDevelopmentSnapshot.timestampCreated',
                'child.preferredName',
                'child.surname',
                'child.image_240',
                'generatedBy.preferredName as generatedByName',
                'generatedBy.surname as generatedBySurname',
            ])
            ->innerJoin('gibbonDevelopmentProfile', 'gibbonDevelopmentSnapshot.gibbonDevelopmentProfileID=gibbonDevelopmentProfile.gibbonDevelopmentProfileID')
            ->innerJoin('gibbonPerson as child', 'gibbonDevelopmentProfile.gibbonPersonID=child.gibbonPersonID')
            ->leftJoin('gibbonPerson as generatedBy', 'gibbonDevelopmentSnapshot.generatedByID=generatedBy.gibbonPersonID')
            ->where('gibbonDevelopmentProfile.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query recent snapshots for a school year.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonSchoolYearID
     * @param int $limit
     * @return DataSet
     */
    public function queryRecentSnapshots(QueryCriteria $criteria, $gibbonSchoolYearID, $limit = 10)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonDevelopmentSnapshot.gibbonDevelopmentSnapshotID',
                'gibbonDevelopmentSnapshot.gibbonDevelopmentProfileID',
                'gibbonDevelopmentSnapshot.snapshotMonth',
                'gibbonDevelopmentSnapshot.ageMonths',
                'gibbonDevelopmentSnapshot.overallProgress',
                'gibbonDevelopmentSnapshot.isParentShared',
                'gibbonDevelopmentSnapshot.timestampCreated',
                'child.preferredName',
                'child.surname',
                'child.image_240',
            ])
            ->innerJoin('gibbonDevelopmentProfile', 'gibbonDevelopmentSnapshot.gibbonDevelopmentProfileID=gibbonDevelopmentProfile.gibbonDevelopmentProfileID')
            ->innerJoin('gibbonPerson as child', 'gibbonDevelopmentProfile.gibbonPersonID=child.gibbonPersonID')
            ->where('gibbonDevelopmentProfile.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID)
            ->orderBy(['gibbonDevelopmentSnapshot.timestampCreated DESC'])
            ->limit($limit);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Get snapshot with full details.
     *
     * @param int $gibbonDevelopmentSnapshotID
     * @return array|false
     */
    public function getSnapshotWithDetails($gibbonDevelopmentSnapshotID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonDevelopmentSnapshot.*',
                'generatedBy.preferredName as generatedByName',
                'generatedBy.surname as generatedBySurname',
                'child.preferredName',
                'child.surname',
                'child.image_240',
                'child.dob',
            ])
            ->innerJoin('gibbonDevelopmentProfile', 'gibbonDevelopmentSnapshot.gibbonDevelopmentProfileID=gibbonDevelopmentProfile.gibbonDevelopmentProfileID')
            ->innerJoin('gibbonPerson as child', 'gibbonDevelopmentProfile.gibbonPersonID=child.gibbonPersonID')
            ->leftJoin('gibbonPerson as generatedBy', 'gibbonDevelopmentSnapshot.generatedByID=generatedBy.gibbonPersonID')
            ->where('gibbonDevelopmentSnapshot.gibbonDevelopmentSnapshotID=:gibbonDevelopmentSnapshotID')
            ->bindValue('gibbonDevelopmentSnapshotID', $gibbonDevelopmentSnapshotID);

        return $this->runSelect($query)->fetch();
    }

    /**
     * Get snapshot for a specific month.
     *
     * @param int $gibbonDevelopmentProfileID
     * @param string $snapshotMonth
     * @return array|false
     */
    public function getSnapshotByMonth($gibbonDevelopmentProfileID, $snapshotMonth)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonDevelopmentSnapshot.*',
                'generatedBy.preferredName as generatedByName',
                'generatedBy.surname as generatedBySurname',
            ])
            ->leftJoin('gibbonPerson as generatedBy', 'gibbonDevelopmentSnapshot.generatedByID=generatedBy.gibbonPersonID')
            ->where('gibbonDevelopmentSnapshot.gibbonDevelopmentProfileID=:gibbonDevelopmentProfileID')
            ->bindValue('gibbonDevelopmentProfileID', $gibbonDevelopmentProfileID)
            ->where('gibbonDevelopmentSnapshot.snapshotMonth=:snapshotMonth')
            ->bindValue('snapshotMonth', $snapshotMonth);

        return $this->runSelect($query)->fetch();
    }

    /**
     * Get the latest snapshot for a profile.
     *
     * @param int $gibbonDevelopmentProfileID
     * @return array|false
     */
    public function getLatestSnapshot($gibbonDevelopmentProfileID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonDevelopmentSnapshot.*',
                'generatedBy.preferredName as generatedByName',
                'generatedBy.surname as generatedBySurname',
            ])
            ->leftJoin('gibbonPerson as generatedBy', 'gibbonDevelopmentSnapshot.generatedByID=generatedBy.gibbonPersonID')
            ->where('gibbonDevelopmentSnapshot.gibbonDevelopmentProfileID=:gibbonDevelopmentProfileID')
            ->bindValue('gibbonDevelopmentProfileID', $gibbonDevelopmentProfileID)
            ->orderBy(['gibbonDevelopmentSnapshot.snapshotMonth DESC'])
            ->limit(1);

        return $this->runSelect($query)->fetch();
    }

    /**
     * Select snapshots shared with parents for a profile.
     *
     * @param int $gibbonDevelopmentProfileID
     * @return \Gibbon\Database\Result
     */
    public function selectParentSharedSnapshots($gibbonDevelopmentProfileID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonDevelopmentSnapshot.*',
                'generatedBy.preferredName as generatedByName',
                'generatedBy.surname as generatedBySurname',
            ])
            ->leftJoin('gibbonPerson as generatedBy', 'gibbonDevelopmentSnapshot.generatedByID=generatedBy.gibbonPersonID')
            ->where('gibbonDevelopmentSnapshot.gibbonDevelopmentProfileID=:gibbonDevelopmentProfileID')
            ->bindValue('gibbonDevelopmentProfileID', $gibbonDevelopmentProfileID)
            ->where("gibbonDevelopmentSnapshot.isParentShared='Y'")
            ->orderBy(['gibbonDevelopmentSnapshot.snapshotMonth DESC']);

        return $this->runSelect($query);
    }

    /**
     * Get overall progress distribution for a school year.
     *
     * @param int $gibbonSchoolYearID
     * @param string $snapshotMonth
     * @return array
     */
    public function getProgressDistribution($gibbonSchoolYearID, $snapshotMonth)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID, 'snapshotMonth' => $snapshotMonth];
        $sql = "SELECT
                    gibbonDevelopmentSnapshot.overallProgress,
                    COUNT(*) as count
                FROM gibbonDevelopmentSnapshot
                INNER JOIN gibbonDevelopmentProfile ON gibbonDevelopmentSnapshot.gibbonDevelopmentProfileID=gibbonDevelopmentProfile.gibbonDevelopmentProfileID
                WHERE gibbonDevelopmentProfile.gibbonSchoolYearID=:gibbonSchoolYearID
                AND gibbonDevelopmentSnapshot.snapshotMonth=:snapshotMonth
                GROUP BY gibbonDevelopmentSnapshot.overallProgress";

        return $this->db()->select($sql, $data)->fetchAll();
    }

    /**
     * Get growth trajectory data for a profile.
     *
     * @param int $gibbonDevelopmentProfileID
     * @return array
     */
    public function getGrowthTrajectory($gibbonDevelopmentProfileID)
    {
        $data = ['gibbonDevelopmentProfileID' => $gibbonDevelopmentProfileID];
        $sql = "SELECT
                    snapshotMonth,
                    ageMonths,
                    domainSummaries,
                    overallProgress
                FROM gibbonDevelopmentSnapshot
                WHERE gibbonDevelopmentProfileID=:gibbonDevelopmentProfileID
                ORDER BY snapshotMonth ASC";

        return $this->db()->select($sql, $data)->fetchAll();
    }

    /**
     * Get progress trend for a specific domain over time.
     *
     * @param int $gibbonDevelopmentProfileID
     * @param string $domain
     * @return array
     */
    public function getDomainProgressTrend($gibbonDevelopmentProfileID, $domain)
    {
        $data = ['gibbonDevelopmentProfileID' => $gibbonDevelopmentProfileID];
        $sql = "SELECT
                    snapshotMonth,
                    ageMonths,
                    JSON_UNQUOTE(JSON_EXTRACT(domainSummaries, CONCAT('$.', :domain))) as domainData
                FROM gibbonDevelopmentSnapshot
                WHERE gibbonDevelopmentProfileID=:gibbonDevelopmentProfileID
                AND domainSummaries IS NOT NULL
                ORDER BY snapshotMonth ASC";

        $data['domain'] = $domain;
        return $this->db()->select($sql, $data)->fetchAll();
    }

    /**
     * Create a monthly snapshot.
     *
     * @param int $gibbonDevelopmentProfileID
     * @param string $snapshotMonth
     * @param int|null $ageMonths
     * @param array|null $domainSummaries
     * @param string $overallProgress
     * @param array|null $strengths
     * @param array|null $growthAreas
     * @param string|null $recommendations
     * @param int|null $generatedByID
     * @return int|false
     */
    public function createSnapshot($gibbonDevelopmentProfileID, $snapshotMonth, $ageMonths = null, $domainSummaries = null, $overallProgress = 'on_track', $strengths = null, $growthAreas = null, $recommendations = null, $generatedByID = null)
    {
        $data = [
            'gibbonDevelopmentProfileID' => $gibbonDevelopmentProfileID,
            'snapshotMonth' => $snapshotMonth,
            'overallProgress' => $overallProgress,
            'isParentShared' => 'N',
        ];

        if ($ageMonths !== null) {
            $data['ageMonths'] = $ageMonths;
        }

        if ($domainSummaries !== null) {
            $data['domainSummaries'] = json_encode($domainSummaries);
        }

        if ($strengths !== null) {
            $data['strengths'] = json_encode($strengths);
        }

        if ($growthAreas !== null) {
            $data['growthAreas'] = json_encode($growthAreas);
        }

        if ($recommendations !== null) {
            $data['recommendations'] = $recommendations;
        }

        if ($generatedByID !== null) {
            $data['generatedByID'] = $generatedByID;
        }

        return $this->insert($data);
    }

    /**
     * Update snapshot content.
     *
     * @param int $gibbonDevelopmentSnapshotID
     * @param array|null $domainSummaries
     * @param string|null $overallProgress
     * @param array|null $strengths
     * @param array|null $growthAreas
     * @param string|null $recommendations
     * @return bool
     */
    public function updateSnapshotContent($gibbonDevelopmentSnapshotID, $domainSummaries = null, $overallProgress = null, $strengths = null, $growthAreas = null, $recommendations = null)
    {
        $data = [];

        if ($domainSummaries !== null) {
            $data['domainSummaries'] = json_encode($domainSummaries);
        }

        if ($overallProgress !== null) {
            $data['overallProgress'] = $overallProgress;
        }

        if ($strengths !== null) {
            $data['strengths'] = json_encode($strengths);
        }

        if ($growthAreas !== null) {
            $data['growthAreas'] = json_encode($growthAreas);
        }

        if ($recommendations !== null) {
            $data['recommendations'] = $recommendations;
        }

        if (empty($data)) {
            return true;
        }

        return $this->update($gibbonDevelopmentSnapshotID, $data);
    }

    /**
     * Share snapshot with parents.
     *
     * @param int $gibbonDevelopmentSnapshotID
     * @param bool $isShared
     * @return bool
     */
    public function shareWithParents($gibbonDevelopmentSnapshotID, $isShared = true)
    {
        return $this->update($gibbonDevelopmentSnapshotID, ['isParentShared' => $isShared ? 'Y' : 'N']);
    }

    /**
     * Check if snapshot exists for a month.
     *
     * @param int $gibbonDevelopmentProfileID
     * @param string $snapshotMonth
     * @return bool
     */
    public function snapshotExists($gibbonDevelopmentProfileID, $snapshotMonth)
    {
        $data = ['gibbonDevelopmentProfileID' => $gibbonDevelopmentProfileID, 'snapshotMonth' => $snapshotMonth];
        $sql = "SELECT gibbonDevelopmentSnapshotID FROM gibbonDevelopmentSnapshot
                WHERE gibbonDevelopmentProfileID=:gibbonDevelopmentProfileID
                AND snapshotMonth=:snapshotMonth";

        return !empty($this->db()->selectOne($sql, $data));
    }

    /**
     * Get profiles needing monthly snapshots.
     *
     * @param int $gibbonSchoolYearID
     * @param string $snapshotMonth
     * @return array
     */
    public function getProfilesNeedingSnapshots($gibbonSchoolYearID, $snapshotMonth)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID, 'snapshotMonth' => $snapshotMonth];
        $sql = "SELECT
                    gibbonDevelopmentProfile.gibbonDevelopmentProfileID,
                    gibbonDevelopmentProfile.gibbonPersonID,
                    child.preferredName,
                    child.surname,
                    child.dob
                FROM gibbonDevelopmentProfile
                INNER JOIN gibbonPerson as child ON gibbonDevelopmentProfile.gibbonPersonID=child.gibbonPersonID
                WHERE gibbonDevelopmentProfile.gibbonSchoolYearID=:gibbonSchoolYearID
                AND gibbonDevelopmentProfile.isActive='Y'
                AND NOT EXISTS (
                    SELECT 1 FROM gibbonDevelopmentSnapshot
                    WHERE gibbonDevelopmentSnapshot.gibbonDevelopmentProfileID=gibbonDevelopmentProfile.gibbonDevelopmentProfileID
                    AND gibbonDevelopmentSnapshot.snapshotMonth=:snapshotMonth
                )
                ORDER BY child.surname, child.preferredName";

        return $this->db()->select($sql, $data)->fetchAll();
    }

    /**
     * Get snapshots needing support attention.
     *
     * @param int $gibbonSchoolYearID
     * @return array
     */
    public function getSnapshotsNeedingSupport($gibbonSchoolYearID)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID];
        $sql = "SELECT
                    gibbonDevelopmentSnapshot.gibbonDevelopmentSnapshotID,
                    gibbonDevelopmentSnapshot.snapshotMonth,
                    gibbonDevelopmentSnapshot.overallProgress,
                    gibbonDevelopmentSnapshot.growthAreas,
                    gibbonDevelopmentProfile.gibbonDevelopmentProfileID,
                    child.gibbonPersonID,
                    child.preferredName,
                    child.surname,
                    child.image_240
                FROM gibbonDevelopmentSnapshot
                INNER JOIN gibbonDevelopmentProfile ON gibbonDevelopmentSnapshot.gibbonDevelopmentProfileID=gibbonDevelopmentProfile.gibbonDevelopmentProfileID
                INNER JOIN gibbonPerson as child ON gibbonDevelopmentProfile.gibbonPersonID=child.gibbonPersonID
                WHERE gibbonDevelopmentProfile.gibbonSchoolYearID=:gibbonSchoolYearID
                AND gibbonDevelopmentSnapshot.overallProgress='needs_support'
                ORDER BY gibbonDevelopmentSnapshot.snapshotMonth DESC, child.surname, child.preferredName";

        return $this->db()->select($sql, $data)->fetchAll();
    }
}
