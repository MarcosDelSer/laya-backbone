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
 * Development Profile Gateway
 *
 * Handles developmental profile data access for Quebec-aligned 6-domain tracking.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class DevelopmentProfileGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonDevelopmentProfile';
    private static $primaryKey = 'gibbonDevelopmentProfileID';

    private static $searchableColumns = ['gibbonPerson.preferredName', 'gibbonPerson.surname', 'gibbonDevelopmentProfile.notes'];

    /**
     * Query development profiles with criteria support.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonSchoolYearID
     * @return DataSet
     */
    public function queryProfiles(QueryCriteria $criteria, $gibbonSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonDevelopmentProfile.gibbonDevelopmentProfileID',
                'gibbonDevelopmentProfile.gibbonPersonID',
                'gibbonDevelopmentProfile.gibbonSchoolYearID',
                'gibbonDevelopmentProfile.educatorID',
                'gibbonDevelopmentProfile.birthDate',
                'gibbonDevelopmentProfile.notes',
                'gibbonDevelopmentProfile.isActive',
                'gibbonDevelopmentProfile.timestampCreated',
                'gibbonDevelopmentProfile.timestampModified',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
                'gibbonPerson.dob',
                'educator.preferredName as educatorName',
                'educator.surname as educatorSurname',
            ])
            ->innerJoin('gibbonPerson', 'gibbonDevelopmentProfile.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->leftJoin('gibbonPerson as educator', 'gibbonDevelopmentProfile.educatorID=educator.gibbonPersonID')
            ->where('gibbonDevelopmentProfile.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID);

        $criteria->addFilterRules([
            'active' => function ($query, $isActive) {
                return $query
                    ->where('gibbonDevelopmentProfile.isActive=:isActive')
                    ->bindValue('isActive', $isActive);
            },
            'child' => function ($query, $gibbonPersonID) {
                return $query
                    ->where('gibbonDevelopmentProfile.gibbonPersonID=:gibbonPersonID')
                    ->bindValue('gibbonPersonID', $gibbonPersonID);
            },
            'educator' => function ($query, $educatorID) {
                return $query
                    ->where('gibbonDevelopmentProfile.educatorID=:educatorID')
                    ->bindValue('educatorID', $educatorID);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query active profiles for a specific school year.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonSchoolYearID
     * @return DataSet
     */
    public function queryActiveProfiles(QueryCriteria $criteria, $gibbonSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonDevelopmentProfile.gibbonDevelopmentProfileID',
                'gibbonDevelopmentProfile.gibbonPersonID',
                'gibbonDevelopmentProfile.educatorID',
                'gibbonDevelopmentProfile.birthDate',
                'gibbonDevelopmentProfile.notes',
                'gibbonDevelopmentProfile.timestampCreated',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
                'gibbonPerson.dob',
                'educator.preferredName as educatorName',
                'educator.surname as educatorSurname',
            ])
            ->innerJoin('gibbonPerson', 'gibbonDevelopmentProfile.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->leftJoin('gibbonPerson as educator', 'gibbonDevelopmentProfile.educatorID=educator.gibbonPersonID')
            ->where('gibbonDevelopmentProfile.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID)
            ->where("gibbonDevelopmentProfile.isActive='Y'");

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query profiles by educator.
     *
     * @param QueryCriteria $criteria
     * @param int $educatorID
     * @param int $gibbonSchoolYearID
     * @return DataSet
     */
    public function queryProfilesByEducator(QueryCriteria $criteria, $educatorID, $gibbonSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonDevelopmentProfile.gibbonDevelopmentProfileID',
                'gibbonDevelopmentProfile.gibbonPersonID',
                'gibbonDevelopmentProfile.birthDate',
                'gibbonDevelopmentProfile.notes',
                'gibbonDevelopmentProfile.isActive',
                'gibbonDevelopmentProfile.timestampCreated',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
                'gibbonPerson.dob',
            ])
            ->innerJoin('gibbonPerson', 'gibbonDevelopmentProfile.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->where('gibbonDevelopmentProfile.educatorID=:educatorID')
            ->bindValue('educatorID', $educatorID)
            ->where('gibbonDevelopmentProfile.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Get a profile by child and school year.
     *
     * @param int $gibbonPersonID
     * @param int $gibbonSchoolYearID
     * @return array|false
     */
    public function getProfileByPersonAndYear($gibbonPersonID, $gibbonSchoolYearID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonDevelopmentProfile.*',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
                'gibbonPerson.dob',
                'educator.preferredName as educatorName',
                'educator.surname as educatorSurname',
            ])
            ->innerJoin('gibbonPerson', 'gibbonDevelopmentProfile.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->leftJoin('gibbonPerson as educator', 'gibbonDevelopmentProfile.educatorID=educator.gibbonPersonID')
            ->where('gibbonDevelopmentProfile.gibbonPersonID=:gibbonPersonID')
            ->bindValue('gibbonPersonID', $gibbonPersonID)
            ->where('gibbonDevelopmentProfile.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID);

        return $this->runSelect($query)->fetch();
    }

    /**
     * Get profile with child details.
     *
     * @param int $gibbonDevelopmentProfileID
     * @return array|false
     */
    public function getProfileWithDetails($gibbonDevelopmentProfileID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonDevelopmentProfile.*',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
                'gibbonPerson.dob',
                'educator.preferredName as educatorName',
                'educator.surname as educatorSurname',
            ])
            ->innerJoin('gibbonPerson', 'gibbonDevelopmentProfile.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->leftJoin('gibbonPerson as educator', 'gibbonDevelopmentProfile.educatorID=educator.gibbonPersonID')
            ->where('gibbonDevelopmentProfile.gibbonDevelopmentProfileID=:gibbonDevelopmentProfileID')
            ->bindValue('gibbonDevelopmentProfileID', $gibbonDevelopmentProfileID);

        return $this->runSelect($query)->fetch();
    }

    /**
     * Get profile summary statistics.
     *
     * @param int $gibbonDevelopmentProfileID
     * @return array
     */
    public function getProfileSummary($gibbonDevelopmentProfileID)
    {
        $data = ['gibbonDevelopmentProfileID' => $gibbonDevelopmentProfileID];
        $sql = "SELECT
                    (SELECT COUNT(*) FROM gibbonSkillAssessment WHERE gibbonDevelopmentProfileID=:gibbonDevelopmentProfileID) as totalSkills,
                    (SELECT COUNT(*) FROM gibbonSkillAssessment WHERE gibbonDevelopmentProfileID=:gibbonDevelopmentProfileID AND status='can') as masteredSkills,
                    (SELECT COUNT(*) FROM gibbonSkillAssessment WHERE gibbonDevelopmentProfileID=:gibbonDevelopmentProfileID AND status='learning') as learningSkills,
                    (SELECT COUNT(*) FROM gibbonSkillAssessment WHERE gibbonDevelopmentProfileID=:gibbonDevelopmentProfileID AND status='not_yet') as notYetSkills,
                    (SELECT COUNT(*) FROM gibbonDevelopmentObservation WHERE gibbonDevelopmentProfileID=:gibbonDevelopmentProfileID) as totalObservations,
                    (SELECT COUNT(*) FROM gibbonDevelopmentObservation WHERE gibbonDevelopmentProfileID=:gibbonDevelopmentProfileID AND isMilestone='Y') as milestoneObservations,
                    (SELECT COUNT(*) FROM gibbonDevelopmentObservation WHERE gibbonDevelopmentProfileID=:gibbonDevelopmentProfileID AND isConcern='Y') as concernObservations,
                    (SELECT COUNT(*) FROM gibbonDevelopmentSnapshot WHERE gibbonDevelopmentProfileID=:gibbonDevelopmentProfileID) as totalSnapshots";

        return $this->db()->selectOne($sql, $data) ?: [
            'totalSkills' => 0,
            'masteredSkills' => 0,
            'learningSkills' => 0,
            'notYetSkills' => 0,
            'totalObservations' => 0,
            'milestoneObservations' => 0,
            'concernObservations' => 0,
            'totalSnapshots' => 0,
        ];
    }

    /**
     * Get domain progress summary for a profile.
     *
     * @param int $gibbonDevelopmentProfileID
     * @return array
     */
    public function getDomainProgress($gibbonDevelopmentProfileID)
    {
        $data = ['gibbonDevelopmentProfileID' => $gibbonDevelopmentProfileID];
        $sql = "SELECT
                    domain,
                    COUNT(*) as totalSkills,
                    SUM(CASE WHEN status='can' THEN 1 ELSE 0 END) as masteredCount,
                    SUM(CASE WHEN status='learning' THEN 1 ELSE 0 END) as learningCount,
                    SUM(CASE WHEN status='not_yet' THEN 1 ELSE 0 END) as notYetCount,
                    SUM(CASE WHEN status='na' THEN 1 ELSE 0 END) as naCount
                FROM gibbonSkillAssessment
                WHERE gibbonDevelopmentProfileID=:gibbonDevelopmentProfileID
                GROUP BY domain
                ORDER BY FIELD(domain, 'affective', 'social', 'language', 'cognitive', 'gross_motor', 'fine_motor')";

        return $this->db()->select($sql, $data)->fetchAll();
    }

    /**
     * Create a development profile for a child.
     *
     * @param int $gibbonPersonID
     * @param int $gibbonSchoolYearID
     * @param int|null $educatorID
     * @param string|null $birthDate
     * @param string|null $notes
     * @return int|false
     */
    public function createProfile($gibbonPersonID, $gibbonSchoolYearID, $educatorID = null, $birthDate = null, $notes = null)
    {
        $data = [
            'gibbonPersonID' => $gibbonPersonID,
            'gibbonSchoolYearID' => $gibbonSchoolYearID,
            'isActive' => 'Y',
        ];

        if ($educatorID !== null) {
            $data['educatorID'] = $educatorID;
        }

        if ($birthDate !== null) {
            $data['birthDate'] = $birthDate;
        }

        if ($notes !== null) {
            $data['notes'] = $notes;
        }

        return $this->insert($data);
    }

    /**
     * Update profile active status.
     *
     * @param int $gibbonDevelopmentProfileID
     * @param bool $isActive
     * @return bool
     */
    public function updateActiveStatus($gibbonDevelopmentProfileID, $isActive)
    {
        return $this->update($gibbonDevelopmentProfileID, ['isActive' => $isActive ? 'Y' : 'N']);
    }

    /**
     * Assign educator to profile.
     *
     * @param int $gibbonDevelopmentProfileID
     * @param int $educatorID
     * @return bool
     */
    public function assignEducator($gibbonDevelopmentProfileID, $educatorID)
    {
        return $this->update($gibbonDevelopmentProfileID, ['educatorID' => $educatorID]);
    }

    /**
     * Select children without development profiles for the school year.
     *
     * @param int $gibbonSchoolYearID
     * @return \Gibbon\Database\Result
     */
    public function selectChildrenWithoutProfiles($gibbonSchoolYearID)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID];
        $sql = "SELECT gibbonPerson.gibbonPersonID, gibbonPerson.preferredName, gibbonPerson.surname, gibbonPerson.image_240, gibbonPerson.dob
                FROM gibbonStudentEnrolment
                INNER JOIN gibbonPerson ON gibbonStudentEnrolment.gibbonPersonID=gibbonPerson.gibbonPersonID
                WHERE gibbonStudentEnrolment.gibbonSchoolYearID=:gibbonSchoolYearID
                AND NOT EXISTS (
                    SELECT 1 FROM gibbonDevelopmentProfile
                    WHERE gibbonDevelopmentProfile.gibbonPersonID=gibbonPerson.gibbonPersonID
                    AND gibbonDevelopmentProfile.gibbonSchoolYearID=:gibbonSchoolYearID
                )
                ORDER BY gibbonPerson.surname, gibbonPerson.preferredName";

        return $this->db()->select($sql, $data);
    }

    /**
     * Get profiles with recent concerns.
     *
     * @param int $gibbonSchoolYearID
     * @param int $days
     * @return \Gibbon\Database\Result
     */
    public function selectProfilesWithRecentConcerns($gibbonSchoolYearID, $days = 30)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID, 'days' => $days];
        $sql = "SELECT DISTINCT
                    gibbonDevelopmentProfile.gibbonDevelopmentProfileID,
                    gibbonDevelopmentProfile.gibbonPersonID,
                    gibbonPerson.preferredName,
                    gibbonPerson.surname,
                    gibbonPerson.image_240,
                    COUNT(gibbonDevelopmentObservation.gibbonDevelopmentObservationID) as concernCount
                FROM gibbonDevelopmentProfile
                INNER JOIN gibbonPerson ON gibbonDevelopmentProfile.gibbonPersonID=gibbonPerson.gibbonPersonID
                INNER JOIN gibbonDevelopmentObservation ON gibbonDevelopmentProfile.gibbonDevelopmentProfileID=gibbonDevelopmentObservation.gibbonDevelopmentProfileID
                WHERE gibbonDevelopmentProfile.gibbonSchoolYearID=:gibbonSchoolYearID
                AND gibbonDevelopmentProfile.isActive='Y'
                AND gibbonDevelopmentObservation.isConcern='Y'
                AND gibbonDevelopmentObservation.observedAt >= DATE_SUB(NOW(), INTERVAL :days DAY)
                GROUP BY gibbonDevelopmentProfile.gibbonDevelopmentProfileID
                ORDER BY concernCount DESC, gibbonPerson.surname, gibbonPerson.preferredName";

        return $this->db()->select($sql, $data);
    }
}
