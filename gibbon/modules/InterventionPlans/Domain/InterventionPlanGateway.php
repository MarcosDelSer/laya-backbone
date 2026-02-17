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

namespace Gibbon\Module\InterventionPlans\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\QueryableGateway;

/**
 * Intervention Plan Gateway
 *
 * Handles intervention plan CRUD operations and queries for special needs support.
 * Supports 8-part plan structure with SMART goals, versioning, and progress tracking.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class InterventionPlanGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonInterventionPlan';
    private static $primaryKey = 'gibbonInterventionPlanID';

    private static $searchableColumns = ['gibbonPerson.preferredName', 'gibbonPerson.surname', 'gibbonInterventionPlan.title'];

    /**
     * Query intervention plans with criteria support.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonSchoolYearID
     * @return DataSet
     */
    public function queryInterventionPlans(QueryCriteria $criteria, $gibbonSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonInterventionPlan.gibbonInterventionPlanID',
                'gibbonInterventionPlan.gibbonPersonID',
                'gibbonInterventionPlan.aiServicePlanID',
                'gibbonInterventionPlan.title',
                'gibbonInterventionPlan.status',
                'gibbonInterventionPlan.version',
                'gibbonInterventionPlan.reviewSchedule',
                'gibbonInterventionPlan.nextReviewDate',
                'gibbonInterventionPlan.effectiveDate',
                'gibbonInterventionPlan.endDate',
                'gibbonInterventionPlan.parentSigned',
                'gibbonInterventionPlan.parentSignatureDate',
                'gibbonInterventionPlan.timestampCreated',
                'gibbonInterventionPlan.timestampModified',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
                'gibbonPerson.dob',
                'createdBy.preferredName as createdByName',
                'createdBy.surname as createdBySurname',
                'lastModifiedBy.preferredName as lastModifiedByName',
                'lastModifiedBy.surname as lastModifiedBySurname',
            ])
            ->innerJoin('gibbonPerson', 'gibbonInterventionPlan.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->leftJoin('gibbonPerson as createdBy', 'gibbonInterventionPlan.createdByID=createdBy.gibbonPersonID')
            ->leftJoin('gibbonPerson as lastModifiedBy', 'gibbonInterventionPlan.lastModifiedByID=lastModifiedBy.gibbonPersonID')
            ->where('gibbonInterventionPlan.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID);

        $criteria->addFilterRules([
            'child' => function ($query, $gibbonPersonID) {
                return $query
                    ->where('gibbonInterventionPlan.gibbonPersonID=:gibbonPersonID')
                    ->bindValue('gibbonPersonID', $gibbonPersonID);
            },
            'status' => function ($query, $status) {
                return $query
                    ->where('gibbonInterventionPlan.status=:status')
                    ->bindValue('status', $status);
            },
            'reviewSchedule' => function ($query, $reviewSchedule) {
                return $query
                    ->where('gibbonInterventionPlan.reviewSchedule=:reviewSchedule')
                    ->bindValue('reviewSchedule', $reviewSchedule);
            },
            'parentSigned' => function ($query, $value) {
                return $query
                    ->where('gibbonInterventionPlan.parentSigned=:parentSigned')
                    ->bindValue('parentSigned', $value);
            },
            'createdBy' => function ($query, $createdByID) {
                return $query
                    ->where('gibbonInterventionPlan.createdByID=:createdByID')
                    ->bindValue('createdByID', $createdByID);
            },
            'needsReview' => function ($query, $value) {
                if ($value == 'Y') {
                    return $query
                        ->where("(gibbonInterventionPlan.nextReviewDate IS NOT NULL AND gibbonInterventionPlan.nextReviewDate <= CURRENT_DATE())");
                }
                return $query;
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query intervention plans for a specific child.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonPersonID
     * @param int $gibbonSchoolYearID
     * @return DataSet
     */
    public function queryPlansByPerson(QueryCriteria $criteria, $gibbonPersonID, $gibbonSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonInterventionPlan.gibbonInterventionPlanID',
                'gibbonInterventionPlan.title',
                'gibbonInterventionPlan.status',
                'gibbonInterventionPlan.version',
                'gibbonInterventionPlan.reviewSchedule',
                'gibbonInterventionPlan.nextReviewDate',
                'gibbonInterventionPlan.effectiveDate',
                'gibbonInterventionPlan.endDate',
                'gibbonInterventionPlan.parentSigned',
                'gibbonInterventionPlan.parentSignatureDate',
                'gibbonInterventionPlan.timestampCreated',
                'gibbonInterventionPlan.timestampModified',
                'createdBy.preferredName as createdByName',
                'createdBy.surname as createdBySurname',
            ])
            ->leftJoin('gibbonPerson as createdBy', 'gibbonInterventionPlan.createdByID=createdBy.gibbonPersonID')
            ->where('gibbonInterventionPlan.gibbonPersonID=:gibbonPersonID')
            ->bindValue('gibbonPersonID', $gibbonPersonID)
            ->where('gibbonInterventionPlan.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query plans pending review (due or overdue).
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonSchoolYearID
     * @param int $daysAhead Number of days to look ahead for upcoming reviews
     * @return DataSet
     */
    public function queryPlansPendingReview(QueryCriteria $criteria, $gibbonSchoolYearID, $daysAhead = 14)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonInterventionPlan.gibbonInterventionPlanID',
                'gibbonInterventionPlan.gibbonPersonID',
                'gibbonInterventionPlan.title',
                'gibbonInterventionPlan.status',
                'gibbonInterventionPlan.nextReviewDate',
                'gibbonInterventionPlan.reviewSchedule',
                'gibbonInterventionPlan.version',
                'gibbonInterventionPlan.timestampModified',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
                'createdBy.preferredName as createdByName',
                'createdBy.surname as createdBySurname',
                "CASE WHEN gibbonInterventionPlan.nextReviewDate < CURRENT_DATE() THEN 'Overdue' ELSE 'Upcoming' END as reviewStatus",
                'DATEDIFF(gibbonInterventionPlan.nextReviewDate, CURRENT_DATE()) as daysUntilReview',
            ])
            ->innerJoin('gibbonPerson', 'gibbonInterventionPlan.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->leftJoin('gibbonPerson as createdBy', 'gibbonInterventionPlan.createdByID=createdBy.gibbonPersonID')
            ->where('gibbonInterventionPlan.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID)
            ->where('gibbonInterventionPlan.nextReviewDate IS NOT NULL')
            ->where('gibbonInterventionPlan.nextReviewDate <= DATE_ADD(CURRENT_DATE(), INTERVAL :daysAhead DAY)')
            ->bindValue('daysAhead', $daysAhead)
            ->where("gibbonInterventionPlan.status IN ('Active', 'Under Review')")
            ->orderBy(['gibbonInterventionPlan.nextReviewDate ASC']);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Get a single plan with child details.
     *
     * @param int $gibbonInterventionPlanID
     * @return array|false
     */
    public function getPlanDetails($gibbonInterventionPlanID)
    {
        $data = ['gibbonInterventionPlanID' => $gibbonInterventionPlanID];
        $sql = "SELECT
                    gibbonInterventionPlan.*,
                    gibbonPerson.preferredName,
                    gibbonPerson.surname,
                    gibbonPerson.image_240,
                    gibbonPerson.dob,
                    createdBy.preferredName as createdByName,
                    createdBy.surname as createdBySurname,
                    lastModifiedBy.preferredName as lastModifiedByName,
                    lastModifiedBy.surname as lastModifiedBySurname
                FROM gibbonInterventionPlan
                INNER JOIN gibbonPerson ON gibbonInterventionPlan.gibbonPersonID=gibbonPerson.gibbonPersonID
                LEFT JOIN gibbonPerson as createdBy ON gibbonInterventionPlan.createdByID=createdBy.gibbonPersonID
                LEFT JOIN gibbonPerson as lastModifiedBy ON gibbonInterventionPlan.lastModifiedByID=lastModifiedBy.gibbonPersonID
                WHERE gibbonInterventionPlan.gibbonInterventionPlanID=:gibbonInterventionPlanID";

        return $this->db()->selectOne($sql, $data);
    }

    /**
     * Select strengths for a plan (Part 2).
     *
     * @param int $gibbonInterventionPlanID
     * @return \Gibbon\Database\Result
     */
    public function selectStrengthsByPlan($gibbonInterventionPlanID)
    {
        $query = $this
            ->newSelect()
            ->from('gibbonInterventionStrength')
            ->cols(['*'])
            ->where('gibbonInterventionPlanID=:gibbonInterventionPlanID')
            ->bindValue('gibbonInterventionPlanID', $gibbonInterventionPlanID)
            ->orderBy(['sortOrder ASC', 'timestampCreated ASC']);

        return $this->runSelect($query);
    }

    /**
     * Select needs for a plan (Part 3).
     *
     * @param int $gibbonInterventionPlanID
     * @return \Gibbon\Database\Result
     */
    public function selectNeedsByPlan($gibbonInterventionPlanID)
    {
        $query = $this
            ->newSelect()
            ->from('gibbonInterventionNeed')
            ->cols(['*'])
            ->where('gibbonInterventionPlanID=:gibbonInterventionPlanID')
            ->bindValue('gibbonInterventionPlanID', $gibbonInterventionPlanID)
            ->orderBy(['priority DESC', 'sortOrder ASC', 'timestampCreated ASC']);

        return $this->runSelect($query);
    }

    /**
     * Select SMART goals for a plan (Part 4).
     *
     * @param int $gibbonInterventionPlanID
     * @return \Gibbon\Database\Result
     */
    public function selectGoalsByPlan($gibbonInterventionPlanID)
    {
        $query = $this
            ->newSelect()
            ->from('gibbonInterventionGoal')
            ->cols([
                'gibbonInterventionGoal.*',
                'gibbonInterventionNeed.description as needDescription',
                'gibbonInterventionNeed.category as needCategory',
            ])
            ->leftJoin('gibbonInterventionNeed', 'gibbonInterventionGoal.gibbonInterventionNeedID=gibbonInterventionNeed.gibbonInterventionNeedID')
            ->where('gibbonInterventionGoal.gibbonInterventionPlanID=:gibbonInterventionPlanID')
            ->bindValue('gibbonInterventionPlanID', $gibbonInterventionPlanID)
            ->orderBy(['gibbonInterventionGoal.sortOrder ASC', 'gibbonInterventionGoal.timestampCreated ASC']);

        return $this->runSelect($query);
    }

    /**
     * Select strategies for a plan (Part 5).
     *
     * @param int $gibbonInterventionPlanID
     * @return \Gibbon\Database\Result
     */
    public function selectStrategiesByPlan($gibbonInterventionPlanID)
    {
        $query = $this
            ->newSelect()
            ->from('gibbonInterventionStrategy')
            ->cols([
                'gibbonInterventionStrategy.*',
                'gibbonInterventionGoal.title as goalTitle',
            ])
            ->leftJoin('gibbonInterventionGoal', 'gibbonInterventionStrategy.gibbonInterventionGoalID=gibbonInterventionGoal.gibbonInterventionGoalID')
            ->where('gibbonInterventionStrategy.gibbonInterventionPlanID=:gibbonInterventionPlanID')
            ->bindValue('gibbonInterventionPlanID', $gibbonInterventionPlanID)
            ->orderBy(['gibbonInterventionStrategy.sortOrder ASC', 'gibbonInterventionStrategy.timestampCreated ASC']);

        return $this->runSelect($query);
    }

    /**
     * Select monitoring methods for a plan (Part 6).
     *
     * @param int $gibbonInterventionPlanID
     * @return \Gibbon\Database\Result
     */
    public function selectMonitoringByPlan($gibbonInterventionPlanID)
    {
        $query = $this
            ->newSelect()
            ->from('gibbonInterventionMonitoring')
            ->cols([
                'gibbonInterventionMonitoring.*',
                'gibbonInterventionGoal.title as goalTitle',
            ])
            ->leftJoin('gibbonInterventionGoal', 'gibbonInterventionMonitoring.gibbonInterventionGoalID=gibbonInterventionGoal.gibbonInterventionGoalID')
            ->where('gibbonInterventionMonitoring.gibbonInterventionPlanID=:gibbonInterventionPlanID')
            ->bindValue('gibbonInterventionPlanID', $gibbonInterventionPlanID)
            ->orderBy(['gibbonInterventionMonitoring.sortOrder ASC', 'gibbonInterventionMonitoring.timestampCreated ASC']);

        return $this->runSelect($query);
    }

    /**
     * Select parent involvement activities for a plan (Part 7).
     *
     * @param int $gibbonInterventionPlanID
     * @return \Gibbon\Database\Result
     */
    public function selectParentInvolvementByPlan($gibbonInterventionPlanID)
    {
        $query = $this
            ->newSelect()
            ->from('gibbonInterventionParentInvolvement')
            ->cols(['*'])
            ->where('gibbonInterventionPlanID=:gibbonInterventionPlanID')
            ->bindValue('gibbonInterventionPlanID', $gibbonInterventionPlanID)
            ->orderBy(['sortOrder ASC', 'timestampCreated ASC']);

        return $this->runSelect($query);
    }

    /**
     * Select consultations for a plan (Part 8).
     *
     * @param int $gibbonInterventionPlanID
     * @return \Gibbon\Database\Result
     */
    public function selectConsultationsByPlan($gibbonInterventionPlanID)
    {
        $query = $this
            ->newSelect()
            ->from('gibbonInterventionConsultation')
            ->cols(['*'])
            ->where('gibbonInterventionPlanID=:gibbonInterventionPlanID')
            ->bindValue('gibbonInterventionPlanID', $gibbonInterventionPlanID)
            ->orderBy(['consultationDate DESC', 'sortOrder ASC', 'timestampCreated ASC']);

        return $this->runSelect($query);
    }

    /**
     * Select progress records for a plan.
     *
     * @param int $gibbonInterventionPlanID
     * @return \Gibbon\Database\Result
     */
    public function selectProgressByPlan($gibbonInterventionPlanID)
    {
        $query = $this
            ->newSelect()
            ->from('gibbonInterventionProgress')
            ->cols([
                'gibbonInterventionProgress.*',
                'gibbonInterventionGoal.title as goalTitle',
                'recordedBy.preferredName as recordedByName',
                'recordedBy.surname as recordedBySurname',
            ])
            ->leftJoin('gibbonInterventionGoal', 'gibbonInterventionProgress.gibbonInterventionGoalID=gibbonInterventionGoal.gibbonInterventionGoalID')
            ->leftJoin('gibbonPerson as recordedBy', 'gibbonInterventionProgress.recordedByID=recordedBy.gibbonPersonID')
            ->where('gibbonInterventionProgress.gibbonInterventionPlanID=:gibbonInterventionPlanID')
            ->bindValue('gibbonInterventionPlanID', $gibbonInterventionPlanID)
            ->orderBy(['gibbonInterventionProgress.recordDate DESC', 'gibbonInterventionProgress.timestampCreated DESC']);

        return $this->runSelect($query);
    }

    /**
     * Select progress records for a specific goal.
     *
     * @param int $gibbonInterventionGoalID
     * @return \Gibbon\Database\Result
     */
    public function selectProgressByGoal($gibbonInterventionGoalID)
    {
        $query = $this
            ->newSelect()
            ->from('gibbonInterventionProgress')
            ->cols([
                'gibbonInterventionProgress.*',
                'recordedBy.preferredName as recordedByName',
                'recordedBy.surname as recordedBySurname',
            ])
            ->leftJoin('gibbonPerson as recordedBy', 'gibbonInterventionProgress.recordedByID=recordedBy.gibbonPersonID')
            ->where('gibbonInterventionProgress.gibbonInterventionGoalID=:gibbonInterventionGoalID')
            ->bindValue('gibbonInterventionGoalID', $gibbonInterventionGoalID)
            ->orderBy(['gibbonInterventionProgress.recordDate DESC']);

        return $this->runSelect($query);
    }

    /**
     * Select version history for a plan.
     *
     * @param int $gibbonInterventionPlanID
     * @return \Gibbon\Database\Result
     */
    public function selectVersionsByPlan($gibbonInterventionPlanID)
    {
        $query = $this
            ->newSelect()
            ->from('gibbonInterventionVersion')
            ->cols([
                'gibbonInterventionVersion.*',
                'createdBy.preferredName as createdByName',
                'createdBy.surname as createdBySurname',
            ])
            ->leftJoin('gibbonPerson as createdBy', 'gibbonInterventionVersion.createdByID=createdBy.gibbonPersonID')
            ->where('gibbonInterventionVersion.gibbonInterventionPlanID=:gibbonInterventionPlanID')
            ->bindValue('gibbonInterventionPlanID', $gibbonInterventionPlanID)
            ->orderBy(['gibbonInterventionVersion.versionNumber DESC']);

        return $this->runSelect($query);
    }

    /**
     * Get plan summary statistics for a school year.
     *
     * @param int $gibbonSchoolYearID
     * @return array
     */
    public function getPlanSummaryBySchoolYear($gibbonSchoolYearID)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID];
        $sql = "SELECT
                    COUNT(*) as totalPlans,
                    COUNT(DISTINCT gibbonPersonID) as childrenWithPlans,
                    SUM(CASE WHEN status='Draft' THEN 1 ELSE 0 END) as draftPlans,
                    SUM(CASE WHEN status='Active' THEN 1 ELSE 0 END) as activePlans,
                    SUM(CASE WHEN status='Under Review' THEN 1 ELSE 0 END) as underReviewPlans,
                    SUM(CASE WHEN status='Completed' THEN 1 ELSE 0 END) as completedPlans,
                    SUM(CASE WHEN status='Archived' THEN 1 ELSE 0 END) as archivedPlans,
                    SUM(CASE WHEN parentSigned='Y' THEN 1 ELSE 0 END) as signedPlans,
                    SUM(CASE WHEN nextReviewDate IS NOT NULL AND nextReviewDate < CURRENT_DATE() THEN 1 ELSE 0 END) as overduePlans
                FROM gibbonInterventionPlan
                WHERE gibbonSchoolYearID=:gibbonSchoolYearID";

        return $this->db()->selectOne($sql, $data) ?: [
            'totalPlans' => 0,
            'childrenWithPlans' => 0,
            'draftPlans' => 0,
            'activePlans' => 0,
            'underReviewPlans' => 0,
            'completedPlans' => 0,
            'archivedPlans' => 0,
            'signedPlans' => 0,
            'overduePlans' => 0,
        ];
    }

    /**
     * Get goal progress statistics for a plan.
     *
     * @param int $gibbonInterventionPlanID
     * @return array
     */
    public function getGoalStatsByPlan($gibbonInterventionPlanID)
    {
        $data = ['gibbonInterventionPlanID' => $gibbonInterventionPlanID];
        $sql = "SELECT
                    COUNT(*) as totalGoals,
                    SUM(CASE WHEN status='Not Started' THEN 1 ELSE 0 END) as notStartedGoals,
                    SUM(CASE WHEN status='In Progress' THEN 1 ELSE 0 END) as inProgressGoals,
                    SUM(CASE WHEN status='Achieved' THEN 1 ELSE 0 END) as achievedGoals,
                    SUM(CASE WHEN status='Modified' THEN 1 ELSE 0 END) as modifiedGoals,
                    SUM(CASE WHEN status='Discontinued' THEN 1 ELSE 0 END) as discontinuedGoals,
                    AVG(progressPercentage) as averageProgress
                FROM gibbonInterventionGoal
                WHERE gibbonInterventionPlanID=:gibbonInterventionPlanID";

        return $this->db()->selectOne($sql, $data) ?: [
            'totalGoals' => 0,
            'notStartedGoals' => 0,
            'inProgressGoals' => 0,
            'achievedGoals' => 0,
            'modifiedGoals' => 0,
            'discontinuedGoals' => 0,
            'averageProgress' => 0,
        ];
    }

    /**
     * Insert a strength record.
     *
     * @param array $data
     * @return int|false
     */
    public function insertStrength(array $data)
    {
        $sql = "INSERT INTO gibbonInterventionStrength (gibbonInterventionPlanID, category, description, examples, sortOrder)
                VALUES (:gibbonInterventionPlanID, :category, :description, :examples, :sortOrder)";

        return $this->db()->insert($sql, $data);
    }

    /**
     * Insert a need record.
     *
     * @param array $data
     * @return int|false
     */
    public function insertNeed(array $data)
    {
        $sql = "INSERT INTO gibbonInterventionNeed (gibbonInterventionPlanID, category, description, priority, baseline, sortOrder)
                VALUES (:gibbonInterventionPlanID, :category, :description, :priority, :baseline, :sortOrder)";

        return $this->db()->insert($sql, $data);
    }

    /**
     * Insert a SMART goal record.
     *
     * @param array $data
     * @return int|false
     */
    public function insertGoal(array $data)
    {
        $sql = "INSERT INTO gibbonInterventionGoal (gibbonInterventionPlanID, gibbonInterventionNeedID, title, description,
                measurementCriteria, measurementBaseline, measurementTarget, achievabilityNotes, relevanceNotes,
                targetDate, status, progressPercentage, sortOrder)
                VALUES (:gibbonInterventionPlanID, :gibbonInterventionNeedID, :title, :description,
                :measurementCriteria, :measurementBaseline, :measurementTarget, :achievabilityNotes, :relevanceNotes,
                :targetDate, :status, :progressPercentage, :sortOrder)";

        return $this->db()->insert($sql, $data);
    }

    /**
     * Insert a strategy record.
     *
     * @param array $data
     * @return int|false
     */
    public function insertStrategy(array $data)
    {
        $sql = "INSERT INTO gibbonInterventionStrategy (gibbonInterventionPlanID, gibbonInterventionGoalID, title, description,
                responsibleParty, frequency, materialsNeeded, accommodations, sortOrder)
                VALUES (:gibbonInterventionPlanID, :gibbonInterventionGoalID, :title, :description,
                :responsibleParty, :frequency, :materialsNeeded, :accommodations, :sortOrder)";

        return $this->db()->insert($sql, $data);
    }

    /**
     * Insert a monitoring record.
     *
     * @param array $data
     * @return int|false
     */
    public function insertMonitoring(array $data)
    {
        $sql = "INSERT INTO gibbonInterventionMonitoring (gibbonInterventionPlanID, gibbonInterventionGoalID, method, description,
                frequency, responsibleParty, dataCollectionTools, successIndicators, sortOrder)
                VALUES (:gibbonInterventionPlanID, :gibbonInterventionGoalID, :method, :description,
                :frequency, :responsibleParty, :dataCollectionTools, :successIndicators, :sortOrder)";

        return $this->db()->insert($sql, $data);
    }

    /**
     * Insert a parent involvement record.
     *
     * @param array $data
     * @return int|false
     */
    public function insertParentInvolvement(array $data)
    {
        $sql = "INSERT INTO gibbonInterventionParentInvolvement (gibbonInterventionPlanID, activityType, title, description,
                frequency, resourcesProvided, communicationMethod, sortOrder)
                VALUES (:gibbonInterventionPlanID, :activityType, :title, :description,
                :frequency, :resourcesProvided, :communicationMethod, :sortOrder)";

        return $this->db()->insert($sql, $data);
    }

    /**
     * Insert a consultation record.
     *
     * @param array $data
     * @return int|false
     */
    public function insertConsultation(array $data)
    {
        $sql = "INSERT INTO gibbonInterventionConsultation (gibbonInterventionPlanID, specialistType, specialistName, organization,
                purpose, recommendations, consultationDate, nextConsultationDate, notes, sortOrder)
                VALUES (:gibbonInterventionPlanID, :specialistType, :specialistName, :organization,
                :purpose, :recommendations, :consultationDate, :nextConsultationDate, :notes, :sortOrder)";

        return $this->db()->insert($sql, $data);
    }

    /**
     * Insert a progress record.
     *
     * @param array $data
     * @return int|false
     */
    public function insertProgress(array $data)
    {
        $sql = "INSERT INTO gibbonInterventionProgress (gibbonInterventionPlanID, gibbonInterventionGoalID, recordedByID,
                recordDate, progressNotes, progressLevel, measurementValue, barriers, nextSteps)
                VALUES (:gibbonInterventionPlanID, :gibbonInterventionGoalID, :recordedByID,
                :recordDate, :progressNotes, :progressLevel, :measurementValue, :barriers, :nextSteps)";

        return $this->db()->insert($sql, $data);
    }

    /**
     * Insert a version record.
     *
     * @param array $data
     * @return int|false
     */
    public function insertVersion(array $data)
    {
        $sql = "INSERT INTO gibbonInterventionVersion (gibbonInterventionPlanID, versionNumber, createdByID, changeSummary, snapshotData)
                VALUES (:gibbonInterventionPlanID, :versionNumber, :createdByID, :changeSummary, :snapshotData)";

        return $this->db()->insert($sql, $data);
    }

    /**
     * Update goal progress percentage.
     *
     * @param int $gibbonInterventionGoalID
     * @param float $progressPercentage
     * @param string $status
     * @return bool
     */
    public function updateGoalProgress($gibbonInterventionGoalID, $progressPercentage, $status)
    {
        $data = [
            'gibbonInterventionGoalID' => $gibbonInterventionGoalID,
            'progressPercentage' => $progressPercentage,
            'status' => $status,
        ];
        $sql = "UPDATE gibbonInterventionGoal
                SET progressPercentage=:progressPercentage, status=:status
                WHERE gibbonInterventionGoalID=:gibbonInterventionGoalID";

        return $this->db()->update($sql, $data);
    }

    /**
     * Mark parent signature on a plan.
     *
     * @param int $gibbonInterventionPlanID
     * @return bool
     */
    public function markParentSigned($gibbonInterventionPlanID)
    {
        return $this->update($gibbonInterventionPlanID, [
            'parentSigned' => 'Y',
            'parentSignatureDate' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Update plan status.
     *
     * @param int $gibbonInterventionPlanID
     * @param string $status
     * @param int $lastModifiedByID
     * @return bool
     */
    public function updatePlanStatus($gibbonInterventionPlanID, $status, $lastModifiedByID)
    {
        return $this->update($gibbonInterventionPlanID, [
            'status' => $status,
            'lastModifiedByID' => $lastModifiedByID,
        ]);
    }

    /**
     * Update plan review date.
     *
     * @param int $gibbonInterventionPlanID
     * @param string $nextReviewDate
     * @return bool
     */
    public function updateNextReviewDate($gibbonInterventionPlanID, $nextReviewDate)
    {
        return $this->update($gibbonInterventionPlanID, [
            'nextReviewDate' => $nextReviewDate,
        ]);
    }

    /**
     * Increment plan version number.
     *
     * @param int $gibbonInterventionPlanID
     * @return bool
     */
    public function incrementVersion($gibbonInterventionPlanID)
    {
        $data = ['gibbonInterventionPlanID' => $gibbonInterventionPlanID];
        $sql = "UPDATE gibbonInterventionPlan
                SET version = version + 1
                WHERE gibbonInterventionPlanID=:gibbonInterventionPlanID";

        return $this->db()->update($sql, $data);
    }

    /**
     * Delete all section records for a plan (for plan deletion or rebuild).
     *
     * @param int $gibbonInterventionPlanID
     * @return bool
     */
    public function deletePlanSections($gibbonInterventionPlanID)
    {
        $data = ['gibbonInterventionPlanID' => $gibbonInterventionPlanID];

        // Delete in reverse dependency order
        $tables = [
            'gibbonInterventionProgress',
            'gibbonInterventionConsultation',
            'gibbonInterventionParentInvolvement',
            'gibbonInterventionMonitoring',
            'gibbonInterventionStrategy',
            'gibbonInterventionGoal',
            'gibbonInterventionNeed',
            'gibbonInterventionStrength',
        ];

        foreach ($tables as $table) {
            $sql = "DELETE FROM {$table} WHERE gibbonInterventionPlanID=:gibbonInterventionPlanID";
            $this->db()->delete($sql, $data);
        }

        return true;
    }
}
