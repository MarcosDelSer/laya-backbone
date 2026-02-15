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
 * Care Tracking Activity Gateway
 *
 * Handles activity participation tracking for children in childcare settings.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class ActivityGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonCareActivity';
    private static $primaryKey = 'gibbonCareActivityID';

    private static $searchableColumns = ['gibbonPerson.preferredName', 'gibbonPerson.surname', 'gibbonCareActivity.activityName', 'gibbonCareActivity.notes'];

    /**
     * Query activity records with criteria support.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonSchoolYearID
     * @return DataSet
     */
    public function queryActivities(QueryCriteria $criteria, $gibbonSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonCareActivity.gibbonCareActivityID',
                'gibbonCareActivity.gibbonPersonID',
                'gibbonCareActivity.date',
                'gibbonCareActivity.activityName',
                'gibbonCareActivity.activityType',
                'gibbonCareActivity.duration',
                'gibbonCareActivity.participation',
                'gibbonCareActivity.aiSuggested',
                'gibbonCareActivity.aiActivityID',
                'gibbonCareActivity.notes',
                'gibbonCareActivity.timestampCreated',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
                'recordedBy.preferredName as recordedByName',
                'recordedBy.surname as recordedBySurname',
            ])
            ->innerJoin('gibbonPerson', 'gibbonCareActivity.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->leftJoin('gibbonPerson as recordedBy', 'gibbonCareActivity.recordedByID=recordedBy.gibbonPersonID')
            ->where('gibbonCareActivity.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID);

        $criteria->addFilterRules([
            'date' => function ($query, $date) {
                return $query
                    ->where('gibbonCareActivity.date=:date')
                    ->bindValue('date', $date);
            },
            'child' => function ($query, $gibbonPersonID) {
                return $query
                    ->where('gibbonCareActivity.gibbonPersonID=:gibbonPersonID')
                    ->bindValue('gibbonPersonID', $gibbonPersonID);
            },
            'activityType' => function ($query, $activityType) {
                return $query
                    ->where('gibbonCareActivity.activityType=:activityType')
                    ->bindValue('activityType', $activityType);
            },
            'participation' => function ($query, $participation) {
                return $query
                    ->where('gibbonCareActivity.participation=:participation')
                    ->bindValue('participation', $participation);
            },
            'aiSuggested' => function ($query, $value) {
                return $query
                    ->where('gibbonCareActivity.aiSuggested=:aiSuggested')
                    ->bindValue('aiSuggested', $value);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query activity records for a specific date.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonSchoolYearID
     * @param string $date
     * @return DataSet
     */
    public function queryActivitiesByDate(QueryCriteria $criteria, $gibbonSchoolYearID, $date)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonCareActivity.gibbonCareActivityID',
                'gibbonCareActivity.gibbonPersonID',
                'gibbonCareActivity.date',
                'gibbonCareActivity.activityName',
                'gibbonCareActivity.activityType',
                'gibbonCareActivity.duration',
                'gibbonCareActivity.participation',
                'gibbonCareActivity.aiSuggested',
                'gibbonCareActivity.notes',
                'gibbonCareActivity.timestampCreated',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
                'gibbonPerson.dob',
            ])
            ->innerJoin('gibbonPerson', 'gibbonCareActivity.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->where('gibbonCareActivity.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID)
            ->where('gibbonCareActivity.date=:date')
            ->bindValue('date', $date);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query activity history for a specific child.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonPersonID
     * @param int $gibbonSchoolYearID
     * @return DataSet
     */
    public function queryActivitiesByPerson(QueryCriteria $criteria, $gibbonPersonID, $gibbonSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonCareActivity.gibbonCareActivityID',
                'gibbonCareActivity.date',
                'gibbonCareActivity.activityName',
                'gibbonCareActivity.activityType',
                'gibbonCareActivity.duration',
                'gibbonCareActivity.participation',
                'gibbonCareActivity.aiSuggested',
                'gibbonCareActivity.aiActivityID',
                'gibbonCareActivity.notes',
                'gibbonCareActivity.timestampCreated',
                'recordedBy.preferredName as recordedByName',
                'recordedBy.surname as recordedBySurname',
            ])
            ->leftJoin('gibbonPerson as recordedBy', 'gibbonCareActivity.recordedByID=recordedBy.gibbonPersonID')
            ->where('gibbonCareActivity.gibbonPersonID=:gibbonPersonID')
            ->bindValue('gibbonPersonID', $gibbonPersonID)
            ->where('gibbonCareActivity.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Get activities for a specific child on a specific date.
     *
     * @param int $gibbonPersonID
     * @param string $date
     * @return \Gibbon\Database\Result
     */
    public function selectActivitiesByPersonAndDate($gibbonPersonID, $date)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonCareActivity.*',
                'recordedBy.preferredName as recordedByName',
                'recordedBy.surname as recordedBySurname',
            ])
            ->leftJoin('gibbonPerson as recordedBy', 'gibbonCareActivity.recordedByID=recordedBy.gibbonPersonID')
            ->where('gibbonCareActivity.gibbonPersonID=:gibbonPersonID')
            ->bindValue('gibbonPersonID', $gibbonPersonID)
            ->where('gibbonCareActivity.date=:date')
            ->bindValue('date', $date)
            ->orderBy(['gibbonCareActivity.timestampCreated ASC']);

        return $this->runSelect($query);
    }

    /**
     * Get activity summary statistics for a specific date.
     *
     * @param int $gibbonSchoolYearID
     * @param string $date
     * @return array
     */
    public function getActivitySummaryByDate($gibbonSchoolYearID, $date)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID, 'date' => $date];
        $sql = "SELECT
                    COUNT(*) as totalActivities,
                    COUNT(DISTINCT gibbonPersonID) as childrenParticipated,
                    COUNT(DISTINCT activityName) as uniqueActivities,
                    SUM(duration) as totalDurationMinutes,
                    AVG(duration) as avgDurationMinutes,
                    SUM(CASE WHEN activityType='Art' THEN 1 ELSE 0 END) as artActivities,
                    SUM(CASE WHEN activityType='Music' THEN 1 ELSE 0 END) as musicActivities,
                    SUM(CASE WHEN activityType='Physical' THEN 1 ELSE 0 END) as physicalActivities,
                    SUM(CASE WHEN activityType='Language' THEN 1 ELSE 0 END) as languageActivities,
                    SUM(CASE WHEN activityType='Math' THEN 1 ELSE 0 END) as mathActivities,
                    SUM(CASE WHEN activityType='Science' THEN 1 ELSE 0 END) as scienceActivities,
                    SUM(CASE WHEN activityType='Social' THEN 1 ELSE 0 END) as socialActivities,
                    SUM(CASE WHEN activityType='Free Play' THEN 1 ELSE 0 END) as freePlayActivities,
                    SUM(CASE WHEN activityType='Outdoor' THEN 1 ELSE 0 END) as outdoorActivities,
                    SUM(CASE WHEN aiSuggested='Y' THEN 1 ELSE 0 END) as aiSuggestedActivities
                FROM gibbonCareActivity
                WHERE gibbonSchoolYearID=:gibbonSchoolYearID
                AND date=:date";

        return $this->db()->selectOne($sql, $data) ?: [
            'totalActivities' => 0,
            'childrenParticipated' => 0,
            'uniqueActivities' => 0,
            'totalDurationMinutes' => 0,
            'avgDurationMinutes' => 0,
            'artActivities' => 0,
            'musicActivities' => 0,
            'physicalActivities' => 0,
            'languageActivities' => 0,
            'mathActivities' => 0,
            'scienceActivities' => 0,
            'socialActivities' => 0,
            'freePlayActivities' => 0,
            'outdoorActivities' => 0,
            'aiSuggestedActivities' => 0,
        ];
    }

    /**
     * Get activity statistics for a child over a date range.
     *
     * @param int $gibbonPersonID
     * @param string $dateStart
     * @param string $dateEnd
     * @return array
     */
    public function getActivityStatsByPersonAndDateRange($gibbonPersonID, $dateStart, $dateEnd)
    {
        $data = ['gibbonPersonID' => $gibbonPersonID, 'dateStart' => $dateStart, 'dateEnd' => $dateEnd];
        $sql = "SELECT
                    COUNT(*) as totalActivities,
                    COUNT(DISTINCT date) as daysWithActivities,
                    SUM(duration) as totalDurationMinutes,
                    AVG(duration) as avgDurationMinutes,
                    SUM(CASE WHEN participation='Leading' THEN 1 ELSE 0 END) as leadingCount,
                    SUM(CASE WHEN participation='Participating' THEN 1 ELSE 0 END) as participatingCount,
                    SUM(CASE WHEN participation='Observing' THEN 1 ELSE 0 END) as observingCount,
                    SUM(CASE WHEN participation='Not Interested' THEN 1 ELSE 0 END) as notInterestedCount,
                    SUM(CASE WHEN aiSuggested='Y' THEN 1 ELSE 0 END) as aiSuggestedCount
                FROM gibbonCareActivity
                WHERE gibbonPersonID=:gibbonPersonID
                AND date >= :dateStart
                AND date <= :dateEnd";

        return $this->db()->selectOne($sql, $data) ?: [
            'totalActivities' => 0,
            'daysWithActivities' => 0,
            'totalDurationMinutes' => 0,
            'avgDurationMinutes' => 0,
            'leadingCount' => 0,
            'participatingCount' => 0,
            'observingCount' => 0,
            'notInterestedCount' => 0,
            'aiSuggestedCount' => 0,
        ];
    }

    /**
     * Get activity type distribution for a child over a date range.
     *
     * @param int $gibbonPersonID
     * @param string $dateStart
     * @param string $dateEnd
     * @return array
     */
    public function getActivityTypeDistribution($gibbonPersonID, $dateStart, $dateEnd)
    {
        $data = ['gibbonPersonID' => $gibbonPersonID, 'dateStart' => $dateStart, 'dateEnd' => $dateEnd];
        $sql = "SELECT
                    activityType,
                    COUNT(*) as count,
                    SUM(duration) as totalDuration,
                    AVG(duration) as avgDuration
                FROM gibbonCareActivity
                WHERE gibbonPersonID=:gibbonPersonID
                AND date >= :dateStart
                AND date <= :dateEnd
                GROUP BY activityType
                ORDER BY count DESC";

        return $this->db()->select($sql, $data)->fetchAll();
    }

    /**
     * Log an activity for a child.
     *
     * @param int $gibbonPersonID
     * @param int $gibbonSchoolYearID
     * @param string $date
     * @param string $activityName
     * @param string $activityType
     * @param int $recordedByID
     * @param int|null $duration
     * @param string|null $participation
     * @param bool $aiSuggested
     * @param int|null $aiActivityID
     * @param string|null $notes
     * @return int|false
     */
    public function logActivity($gibbonPersonID, $gibbonSchoolYearID, $date, $activityName, $activityType, $recordedByID, $duration = null, $participation = null, $aiSuggested = false, $aiActivityID = null, $notes = null)
    {
        $data = [
            'gibbonPersonID' => $gibbonPersonID,
            'gibbonSchoolYearID' => $gibbonSchoolYearID,
            'date' => $date,
            'activityName' => $activityName,
            'activityType' => $activityType,
            'recordedByID' => $recordedByID,
            'aiSuggested' => $aiSuggested ? 'Y' : 'N',
        ];

        if ($duration !== null) {
            $data['duration'] = $duration;
        }

        if ($participation !== null) {
            $data['participation'] = $participation;
        }

        if ($aiActivityID !== null) {
            $data['aiActivityID'] = $aiActivityID;
        }

        if ($notes !== null) {
            $data['notes'] = $notes;
        }

        return $this->insert($data);
    }

    /**
     * Update participation level for an activity.
     *
     * @param int $gibbonCareActivityID
     * @param string $participation
     * @param string|null $notes
     * @return bool
     */
    public function updateParticipation($gibbonCareActivityID, $participation, $notes = null)
    {
        $data = ['participation' => $participation];

        if ($notes !== null) {
            $data['notes'] = $notes;
        }

        return $this->update($gibbonCareActivityID, $data);
    }

    /**
     * Select children who are checked in but haven't participated in activities today.
     *
     * @param int $gibbonSchoolYearID
     * @param string $date
     * @return \Gibbon\Database\Result
     */
    public function selectChildrenWithoutActivities($gibbonSchoolYearID, $date)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID, 'date' => $date];
        $sql = "SELECT gibbonPerson.gibbonPersonID, gibbonPerson.preferredName, gibbonPerson.surname, gibbonPerson.image_240
                FROM gibbonCareAttendance
                INNER JOIN gibbonPerson ON gibbonCareAttendance.gibbonPersonID=gibbonPerson.gibbonPersonID
                WHERE gibbonCareAttendance.gibbonSchoolYearID=:gibbonSchoolYearID
                AND gibbonCareAttendance.date=:date
                AND gibbonCareAttendance.checkInTime IS NOT NULL
                AND gibbonCareAttendance.checkOutTime IS NULL
                AND NOT EXISTS (
                    SELECT 1 FROM gibbonCareActivity
                    WHERE gibbonCareActivity.gibbonPersonID=gibbonPerson.gibbonPersonID
                    AND gibbonCareActivity.date=:date
                )
                ORDER BY gibbonPerson.surname, gibbonPerson.preferredName";

        return $this->db()->select($sql, $data);
    }

    /**
     * Select AI-suggested activities that were used/accepted.
     *
     * @param int $gibbonSchoolYearID
     * @param string $dateStart
     * @param string $dateEnd
     * @return \Gibbon\Database\Result
     */
    public function selectAISuggestedActivities($gibbonSchoolYearID, $dateStart, $dateEnd)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonCareActivity.*',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
                'recordedBy.preferredName as recordedByName',
                'recordedBy.surname as recordedBySurname',
            ])
            ->innerJoin('gibbonPerson', 'gibbonCareActivity.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->leftJoin('gibbonPerson as recordedBy', 'gibbonCareActivity.recordedByID=recordedBy.gibbonPersonID')
            ->where('gibbonCareActivity.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID)
            ->where('gibbonCareActivity.date >= :dateStart')
            ->bindValue('dateStart', $dateStart)
            ->where('gibbonCareActivity.date <= :dateEnd')
            ->bindValue('dateEnd', $dateEnd)
            ->where("gibbonCareActivity.aiSuggested='Y'")
            ->orderBy(['gibbonCareActivity.date DESC', 'gibbonCareActivity.timestampCreated DESC']);

        return $this->runSelect($query);
    }

    /**
     * Get popular activities by participation count.
     *
     * @param int $gibbonSchoolYearID
     * @param string $dateStart
     * @param string $dateEnd
     * @param int $limit
     * @return array
     */
    public function getPopularActivities($gibbonSchoolYearID, $dateStart, $dateEnd, $limit = 10)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID, 'dateStart' => $dateStart, 'dateEnd' => $dateEnd, 'limit' => $limit];
        $sql = "SELECT
                    activityName,
                    activityType,
                    COUNT(*) as timesUsed,
                    COUNT(DISTINCT gibbonPersonID) as childrenParticipated,
                    AVG(duration) as avgDuration,
                    SUM(CASE WHEN participation='Leading' OR participation='Participating' THEN 1 ELSE 0 END) as activeParticipation
                FROM gibbonCareActivity
                WHERE gibbonSchoolYearID=:gibbonSchoolYearID
                AND date >= :dateStart
                AND date <= :dateEnd
                GROUP BY activityName, activityType
                ORDER BY timesUsed DESC
                LIMIT :limit";

        return $this->db()->select($sql, $data)->fetchAll();
    }
}
