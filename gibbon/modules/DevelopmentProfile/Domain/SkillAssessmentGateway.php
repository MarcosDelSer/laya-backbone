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
 * Skill Assessment Gateway
 *
 * Handles skill assessment data access for Quebec developmental domains.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class SkillAssessmentGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonSkillAssessment';
    private static $primaryKey = 'gibbonSkillAssessmentID';

    private static $searchableColumns = ['gibbonSkillAssessment.skillName', 'gibbonSkillAssessment.skillNameFR', 'gibbonSkillAssessment.evidence'];

    /**
     * Query skill assessments with criteria support.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonDevelopmentProfileID
     * @return DataSet
     */
    public function queryAssessments(QueryCriteria $criteria, $gibbonDevelopmentProfileID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonSkillAssessment.gibbonSkillAssessmentID',
                'gibbonSkillAssessment.gibbonDevelopmentProfileID',
                'gibbonSkillAssessment.domain',
                'gibbonSkillAssessment.skillName',
                'gibbonSkillAssessment.skillNameFR',
                'gibbonSkillAssessment.status',
                'gibbonSkillAssessment.assessedAt',
                'gibbonSkillAssessment.assessedByID',
                'gibbonSkillAssessment.evidence',
                'gibbonSkillAssessment.timestampCreated',
                'gibbonSkillAssessment.timestampModified',
                'assessedBy.preferredName as assessedByName',
                'assessedBy.surname as assessedBySurname',
            ])
            ->leftJoin('gibbonPerson as assessedBy', 'gibbonSkillAssessment.assessedByID=assessedBy.gibbonPersonID')
            ->where('gibbonSkillAssessment.gibbonDevelopmentProfileID=:gibbonDevelopmentProfileID')
            ->bindValue('gibbonDevelopmentProfileID', $gibbonDevelopmentProfileID);

        $criteria->addFilterRules([
            'domain' => function ($query, $domain) {
                return $query
                    ->where('gibbonSkillAssessment.domain=:domain')
                    ->bindValue('domain', $domain);
            },
            'status' => function ($query, $status) {
                return $query
                    ->where('gibbonSkillAssessment.status=:status')
                    ->bindValue('status', $status);
            },
            'dateFrom' => function ($query, $dateFrom) {
                return $query
                    ->where('DATE(gibbonSkillAssessment.assessedAt) >= :dateFrom')
                    ->bindValue('dateFrom', $dateFrom);
            },
            'dateTo' => function ($query, $dateTo) {
                return $query
                    ->where('DATE(gibbonSkillAssessment.assessedAt) <= :dateTo')
                    ->bindValue('dateTo', $dateTo);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query assessments by domain.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonDevelopmentProfileID
     * @param string $domain
     * @return DataSet
     */
    public function queryAssessmentsByDomain(QueryCriteria $criteria, $gibbonDevelopmentProfileID, $domain)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonSkillAssessment.gibbonSkillAssessmentID',
                'gibbonSkillAssessment.skillName',
                'gibbonSkillAssessment.skillNameFR',
                'gibbonSkillAssessment.status',
                'gibbonSkillAssessment.assessedAt',
                'gibbonSkillAssessment.evidence',
                'gibbonSkillAssessment.timestampModified',
                'assessedBy.preferredName as assessedByName',
                'assessedBy.surname as assessedBySurname',
            ])
            ->leftJoin('gibbonPerson as assessedBy', 'gibbonSkillAssessment.assessedByID=assessedBy.gibbonPersonID')
            ->where('gibbonSkillAssessment.gibbonDevelopmentProfileID=:gibbonDevelopmentProfileID')
            ->bindValue('gibbonDevelopmentProfileID', $gibbonDevelopmentProfileID)
            ->where('gibbonSkillAssessment.domain=:domain')
            ->bindValue('domain', $domain);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query assessments by status.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonDevelopmentProfileID
     * @param string $status
     * @return DataSet
     */
    public function queryAssessmentsByStatus(QueryCriteria $criteria, $gibbonDevelopmentProfileID, $status)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonSkillAssessment.gibbonSkillAssessmentID',
                'gibbonSkillAssessment.domain',
                'gibbonSkillAssessment.skillName',
                'gibbonSkillAssessment.skillNameFR',
                'gibbonSkillAssessment.status',
                'gibbonSkillAssessment.assessedAt',
                'gibbonSkillAssessment.evidence',
                'gibbonSkillAssessment.timestampModified',
                'assessedBy.preferredName as assessedByName',
                'assessedBy.surname as assessedBySurname',
            ])
            ->leftJoin('gibbonPerson as assessedBy', 'gibbonSkillAssessment.assessedByID=assessedBy.gibbonPersonID')
            ->where('gibbonSkillAssessment.gibbonDevelopmentProfileID=:gibbonDevelopmentProfileID')
            ->bindValue('gibbonDevelopmentProfileID', $gibbonDevelopmentProfileID)
            ->where('gibbonSkillAssessment.status=:status')
            ->bindValue('status', $status);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Get assessment with full details.
     *
     * @param int $gibbonSkillAssessmentID
     * @return array|false
     */
    public function getAssessmentWithDetails($gibbonSkillAssessmentID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonSkillAssessment.*',
                'assessedBy.preferredName as assessedByName',
                'assessedBy.surname as assessedBySurname',
                'child.preferredName',
                'child.surname',
                'child.image_240',
            ])
            ->innerJoin('gibbonDevelopmentProfile', 'gibbonSkillAssessment.gibbonDevelopmentProfileID=gibbonDevelopmentProfile.gibbonDevelopmentProfileID')
            ->innerJoin('gibbonPerson as child', 'gibbonDevelopmentProfile.gibbonPersonID=child.gibbonPersonID')
            ->leftJoin('gibbonPerson as assessedBy', 'gibbonSkillAssessment.assessedByID=assessedBy.gibbonPersonID')
            ->where('gibbonSkillAssessment.gibbonSkillAssessmentID=:gibbonSkillAssessmentID')
            ->bindValue('gibbonSkillAssessmentID', $gibbonSkillAssessmentID);

        return $this->runSelect($query)->fetch();
    }

    /**
     * Select assessments for a date range.
     *
     * @param int $gibbonDevelopmentProfileID
     * @param string $dateStart
     * @param string $dateEnd
     * @return \Gibbon\Database\Result
     */
    public function selectAssessmentsByDateRange($gibbonDevelopmentProfileID, $dateStart, $dateEnd)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonSkillAssessment.*',
                'assessedBy.preferredName as assessedByName',
                'assessedBy.surname as assessedBySurname',
            ])
            ->leftJoin('gibbonPerson as assessedBy', 'gibbonSkillAssessment.assessedByID=assessedBy.gibbonPersonID')
            ->where('gibbonSkillAssessment.gibbonDevelopmentProfileID=:gibbonDevelopmentProfileID')
            ->bindValue('gibbonDevelopmentProfileID', $gibbonDevelopmentProfileID)
            ->where('DATE(gibbonSkillAssessment.assessedAt) >= :dateStart')
            ->bindValue('dateStart', $dateStart)
            ->where('DATE(gibbonSkillAssessment.assessedAt) <= :dateEnd')
            ->bindValue('dateEnd', $dateEnd)
            ->orderBy(['gibbonSkillAssessment.assessedAt DESC']);

        return $this->runSelect($query);
    }

    /**
     * Get skill status summary by domain for a profile.
     *
     * @param int $gibbonDevelopmentProfileID
     * @return array
     */
    public function getStatusSummaryByDomain($gibbonDevelopmentProfileID)
    {
        $data = ['gibbonDevelopmentProfileID' => $gibbonDevelopmentProfileID];
        $sql = "SELECT
                    domain,
                    COUNT(*) as totalSkills,
                    SUM(CASE WHEN status='can' THEN 1 ELSE 0 END) as canCount,
                    SUM(CASE WHEN status='learning' THEN 1 ELSE 0 END) as learningCount,
                    SUM(CASE WHEN status='not_yet' THEN 1 ELSE 0 END) as notYetCount,
                    SUM(CASE WHEN status='na' THEN 1 ELSE 0 END) as naCount,
                    MIN(assessedAt) as firstAssessment,
                    MAX(assessedAt) as lastAssessment
                FROM gibbonSkillAssessment
                WHERE gibbonDevelopmentProfileID=:gibbonDevelopmentProfileID
                GROUP BY domain
                ORDER BY FIELD(domain, 'affective', 'social', 'language', 'cognitive', 'gross_motor', 'fine_motor')";

        return $this->db()->select($sql, $data)->fetchAll();
    }

    /**
     * Get skill progress over time for a specific domain.
     *
     * @param int $gibbonDevelopmentProfileID
     * @param string $domain
     * @return array
     */
    public function getSkillProgressByDomain($gibbonDevelopmentProfileID, $domain)
    {
        $data = ['gibbonDevelopmentProfileID' => $gibbonDevelopmentProfileID, 'domain' => $domain];
        $sql = "SELECT
                    DATE_FORMAT(assessedAt, '%Y-%m') as month,
                    COUNT(*) as assessmentsCount,
                    SUM(CASE WHEN status='can' THEN 1 ELSE 0 END) as canCount,
                    SUM(CASE WHEN status='learning' THEN 1 ELSE 0 END) as learningCount,
                    SUM(CASE WHEN status='not_yet' THEN 1 ELSE 0 END) as notYetCount
                FROM gibbonSkillAssessment
                WHERE gibbonDevelopmentProfileID=:gibbonDevelopmentProfileID
                AND domain=:domain
                GROUP BY DATE_FORMAT(assessedAt, '%Y-%m')
                ORDER BY month ASC";

        return $this->db()->select($sql, $data)->fetchAll();
    }

    /**
     * Get overall skill progress statistics.
     *
     * @param int $gibbonDevelopmentProfileID
     * @return array
     */
    public function getOverallProgress($gibbonDevelopmentProfileID)
    {
        $data = ['gibbonDevelopmentProfileID' => $gibbonDevelopmentProfileID];
        $sql = "SELECT
                    COUNT(*) as totalSkills,
                    SUM(CASE WHEN status='can' THEN 1 ELSE 0 END) as masteredSkills,
                    SUM(CASE WHEN status='learning' THEN 1 ELSE 0 END) as learningSkills,
                    SUM(CASE WHEN status='not_yet' THEN 1 ELSE 0 END) as notYetSkills,
                    SUM(CASE WHEN status='na' THEN 1 ELSE 0 END) as naSkills,
                    ROUND(SUM(CASE WHEN status='can' THEN 1 ELSE 0 END) * 100.0 / NULLIF(SUM(CASE WHEN status!='na' THEN 1 ELSE 0 END), 0), 1) as masteredPercent,
                    COUNT(DISTINCT domain) as domainsAssessed
                FROM gibbonSkillAssessment
                WHERE gibbonDevelopmentProfileID=:gibbonDevelopmentProfileID";

        return $this->db()->selectOne($sql, $data) ?: [
            'totalSkills' => 0,
            'masteredSkills' => 0,
            'learningSkills' => 0,
            'notYetSkills' => 0,
            'naSkills' => 0,
            'masteredPercent' => 0,
            'domainsAssessed' => 0,
        ];
    }

    /**
     * Get recently mastered skills.
     *
     * @param int $gibbonDevelopmentProfileID
     * @param int $limit
     * @return array
     */
    public function getRecentlyMasteredSkills($gibbonDevelopmentProfileID, $limit = 5)
    {
        $data = ['gibbonDevelopmentProfileID' => $gibbonDevelopmentProfileID, 'limit' => $limit];
        $sql = "SELECT
                    gibbonSkillAssessment.gibbonSkillAssessmentID,
                    gibbonSkillAssessment.domain,
                    gibbonSkillAssessment.skillName,
                    gibbonSkillAssessment.skillNameFR,
                    gibbonSkillAssessment.assessedAt,
                    assessedBy.preferredName as assessedByName,
                    assessedBy.surname as assessedBySurname
                FROM gibbonSkillAssessment
                LEFT JOIN gibbonPerson as assessedBy ON gibbonSkillAssessment.assessedByID=assessedBy.gibbonPersonID
                WHERE gibbonSkillAssessment.gibbonDevelopmentProfileID=:gibbonDevelopmentProfileID
                AND gibbonSkillAssessment.status='can'
                ORDER BY gibbonSkillAssessment.timestampModified DESC
                LIMIT :limit";

        return $this->db()->select($sql, $data)->fetchAll();
    }

    /**
     * Get skills currently in learning status.
     *
     * @param int $gibbonDevelopmentProfileID
     * @return array
     */
    public function getCurrentlyLearningSkills($gibbonDevelopmentProfileID)
    {
        $data = ['gibbonDevelopmentProfileID' => $gibbonDevelopmentProfileID];
        $sql = "SELECT
                    gibbonSkillAssessment.gibbonSkillAssessmentID,
                    gibbonSkillAssessment.domain,
                    gibbonSkillAssessment.skillName,
                    gibbonSkillAssessment.skillNameFR,
                    gibbonSkillAssessment.assessedAt,
                    gibbonSkillAssessment.evidence,
                    assessedBy.preferredName as assessedByName,
                    assessedBy.surname as assessedBySurname
                FROM gibbonSkillAssessment
                LEFT JOIN gibbonPerson as assessedBy ON gibbonSkillAssessment.assessedByID=assessedBy.gibbonPersonID
                WHERE gibbonSkillAssessment.gibbonDevelopmentProfileID=:gibbonDevelopmentProfileID
                AND gibbonSkillAssessment.status='learning'
                ORDER BY gibbonSkillAssessment.domain, gibbonSkillAssessment.skillName";

        return $this->db()->select($sql, $data)->fetchAll();
    }

    /**
     * Record a skill assessment.
     *
     * @param int $gibbonDevelopmentProfileID
     * @param string $domain
     * @param string $skillName
     * @param string $status
     * @param string $assessedAt
     * @param int $assessedByID
     * @param string|null $skillNameFR
     * @param string|null $evidence
     * @return int|false
     */
    public function recordAssessment($gibbonDevelopmentProfileID, $domain, $skillName, $status, $assessedAt, $assessedByID, $skillNameFR = null, $evidence = null)
    {
        $data = [
            'gibbonDevelopmentProfileID' => $gibbonDevelopmentProfileID,
            'domain' => $domain,
            'skillName' => $skillName,
            'status' => $status,
            'assessedAt' => $assessedAt,
            'assessedByID' => $assessedByID,
        ];

        if ($skillNameFR !== null) {
            $data['skillNameFR'] = $skillNameFR;
        }

        if ($evidence !== null) {
            $data['evidence'] = $evidence;
        }

        return $this->insert($data);
    }

    /**
     * Update skill status.
     *
     * @param int $gibbonSkillAssessmentID
     * @param string $status
     * @param int $assessedByID
     * @param string|null $evidence
     * @return bool
     */
    public function updateSkillStatus($gibbonSkillAssessmentID, $status, $assessedByID, $evidence = null)
    {
        $data = [
            'status' => $status,
            'assessedByID' => $assessedByID,
            'assessedAt' => date('Y-m-d H:i:s'),
        ];

        if ($evidence !== null) {
            $data['evidence'] = $evidence;
        }

        return $this->update($gibbonSkillAssessmentID, $data);
    }

    /**
     * Check if a skill exists for a profile.
     *
     * @param int $gibbonDevelopmentProfileID
     * @param string $domain
     * @param string $skillName
     * @return array|false
     */
    public function getExistingSkill($gibbonDevelopmentProfileID, $domain, $skillName)
    {
        $data = ['gibbonDevelopmentProfileID' => $gibbonDevelopmentProfileID, 'domain' => $domain, 'skillName' => $skillName];
        $sql = "SELECT * FROM gibbonSkillAssessment
                WHERE gibbonDevelopmentProfileID=:gibbonDevelopmentProfileID
                AND domain=:domain
                AND skillName=:skillName";

        return $this->db()->selectOne($sql, $data);
    }

    /**
     * Get skills needing attention (not_yet for extended period).
     *
     * @param int $gibbonDevelopmentProfileID
     * @param int $days
     * @return array
     */
    public function getSkillsNeedingAttention($gibbonDevelopmentProfileID, $days = 90)
    {
        $data = ['gibbonDevelopmentProfileID' => $gibbonDevelopmentProfileID, 'days' => $days];
        $sql = "SELECT
                    gibbonSkillAssessment.gibbonSkillAssessmentID,
                    gibbonSkillAssessment.domain,
                    gibbonSkillAssessment.skillName,
                    gibbonSkillAssessment.skillNameFR,
                    gibbonSkillAssessment.assessedAt,
                    DATEDIFF(NOW(), gibbonSkillAssessment.assessedAt) as daysSinceAssessment
                FROM gibbonSkillAssessment
                WHERE gibbonSkillAssessment.gibbonDevelopmentProfileID=:gibbonDevelopmentProfileID
                AND gibbonSkillAssessment.status='not_yet'
                AND gibbonSkillAssessment.assessedAt <= DATE_SUB(NOW(), INTERVAL :days DAY)
                ORDER BY gibbonSkillAssessment.assessedAt ASC";

        return $this->db()->select($sql, $data)->fetchAll();
    }
}
