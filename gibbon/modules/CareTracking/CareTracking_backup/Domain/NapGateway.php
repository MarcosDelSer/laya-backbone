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
 * Care Tracking Nap Gateway
 *
 * Handles nap/sleep tracking for children in childcare settings.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class NapGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonCareNap';
    private static $primaryKey = 'gibbonCareNapID';

    private static $searchableColumns = ['gibbonPerson.preferredName', 'gibbonPerson.surname', 'gibbonCareNap.notes'];

    /**
     * Query nap records with criteria support.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonSchoolYearID
     * @return DataSet
     */
    public function queryNaps(QueryCriteria $criteria, $gibbonSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonCareNap.gibbonCareNapID',
                'gibbonCareNap.gibbonPersonID',
                'gibbonCareNap.date',
                'gibbonCareNap.startTime',
                'gibbonCareNap.endTime',
                'gibbonCareNap.quality',
                'gibbonCareNap.notes',
                'gibbonCareNap.timestampCreated',
                'TIMESTAMPDIFF(MINUTE, gibbonCareNap.startTime, gibbonCareNap.endTime) as durationMinutes',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
                'recordedBy.preferredName as recordedByName',
                'recordedBy.surname as recordedBySurname',
            ])
            ->innerJoin('gibbonPerson', 'gibbonCareNap.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->leftJoin('gibbonPerson as recordedBy', 'gibbonCareNap.recordedByID=recordedBy.gibbonPersonID')
            ->where('gibbonCareNap.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID);

        $criteria->addFilterRules([
            'date' => function ($query, $date) {
                return $query
                    ->where('gibbonCareNap.date=:date')
                    ->bindValue('date', $date);
            },
            'child' => function ($query, $gibbonPersonID) {
                return $query
                    ->where('gibbonCareNap.gibbonPersonID=:gibbonPersonID')
                    ->bindValue('gibbonPersonID', $gibbonPersonID);
            },
            'quality' => function ($query, $quality) {
                return $query
                    ->where('gibbonCareNap.quality=:quality')
                    ->bindValue('quality', $quality);
            },
            'inProgress' => function ($query, $value) {
                if ($value == 'Y') {
                    return $query->where('gibbonCareNap.endTime IS NULL');
                } elseif ($value == 'N') {
                    return $query->where('gibbonCareNap.endTime IS NOT NULL');
                }
                return $query;
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query nap records for a specific date.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonSchoolYearID
     * @param string $date
     * @return DataSet
     */
    public function queryNapsByDate(QueryCriteria $criteria, $gibbonSchoolYearID, $date)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonCareNap.gibbonCareNapID',
                'gibbonCareNap.gibbonPersonID',
                'gibbonCareNap.date',
                'gibbonCareNap.startTime',
                'gibbonCareNap.endTime',
                'gibbonCareNap.quality',
                'gibbonCareNap.notes',
                'gibbonCareNap.timestampCreated',
                'TIMESTAMPDIFF(MINUTE, gibbonCareNap.startTime, gibbonCareNap.endTime) as durationMinutes',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
                'gibbonPerson.dob',
            ])
            ->innerJoin('gibbonPerson', 'gibbonCareNap.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->where('gibbonCareNap.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID)
            ->where('gibbonCareNap.date=:date')
            ->bindValue('date', $date);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query nap history for a specific child.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonPersonID
     * @param int $gibbonSchoolYearID
     * @return DataSet
     */
    public function queryNapsByPerson(QueryCriteria $criteria, $gibbonPersonID, $gibbonSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonCareNap.gibbonCareNapID',
                'gibbonCareNap.date',
                'gibbonCareNap.startTime',
                'gibbonCareNap.endTime',
                'gibbonCareNap.quality',
                'gibbonCareNap.notes',
                'gibbonCareNap.timestampCreated',
                'TIMESTAMPDIFF(MINUTE, gibbonCareNap.startTime, gibbonCareNap.endTime) as durationMinutes',
                'recordedBy.preferredName as recordedByName',
                'recordedBy.surname as recordedBySurname',
            ])
            ->leftJoin('gibbonPerson as recordedBy', 'gibbonCareNap.recordedByID=recordedBy.gibbonPersonID')
            ->where('gibbonCareNap.gibbonPersonID=:gibbonPersonID')
            ->bindValue('gibbonPersonID', $gibbonPersonID)
            ->where('gibbonCareNap.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Get naps for a specific child on a specific date.
     *
     * @param int $gibbonPersonID
     * @param string $date
     * @return \Gibbon\Database\Result
     */
    public function selectNapsByPersonAndDate($gibbonPersonID, $date)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonCareNap.*',
                'TIMESTAMPDIFF(MINUTE, gibbonCareNap.startTime, gibbonCareNap.endTime) as durationMinutes',
                'recordedBy.preferredName as recordedByName',
                'recordedBy.surname as recordedBySurname',
            ])
            ->leftJoin('gibbonPerson as recordedBy', 'gibbonCareNap.recordedByID=recordedBy.gibbonPersonID')
            ->where('gibbonCareNap.gibbonPersonID=:gibbonPersonID')
            ->bindValue('gibbonPersonID', $gibbonPersonID)
            ->where('gibbonCareNap.date=:date')
            ->bindValue('date', $date)
            ->orderBy(['gibbonCareNap.startTime ASC']);

        return $this->runSelect($query);
    }

    /**
     * Get an active (in-progress) nap for a child on a specific date.
     *
     * @param int $gibbonPersonID
     * @param string $date
     * @return array|false
     */
    public function getActiveNap($gibbonPersonID, $date)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols(['*'])
            ->where('gibbonPersonID=:gibbonPersonID')
            ->bindValue('gibbonPersonID', $gibbonPersonID)
            ->where('date=:date')
            ->bindValue('date', $date)
            ->where('endTime IS NULL')
            ->orderBy(['startTime DESC'])
            ->limit(1);

        $result = $this->runSelect($query);
        return $result->isNotEmpty() ? $result->fetch() : false;
    }

    /**
     * Get nap summary statistics for a specific date.
     *
     * @param int $gibbonSchoolYearID
     * @param string $date
     * @return array
     */
    public function getNapSummaryByDate($gibbonSchoolYearID, $date)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID, 'date' => $date];
        $sql = "SELECT
                    COUNT(*) as totalNaps,
                    COUNT(DISTINCT gibbonPersonID) as childrenNapped,
                    SUM(CASE WHEN endTime IS NULL THEN 1 ELSE 0 END) as currentlySleeping,
                    SUM(CASE WHEN endTime IS NOT NULL THEN 1 ELSE 0 END) as completedNaps,
                    AVG(TIMESTAMPDIFF(MINUTE, startTime, endTime)) as avgDurationMinutes,
                    SUM(CASE WHEN quality='Sound' THEN 1 ELSE 0 END) as soundSleeps,
                    SUM(CASE WHEN quality='Light' THEN 1 ELSE 0 END) as lightSleeps,
                    SUM(CASE WHEN quality='Restless' THEN 1 ELSE 0 END) as restlessSleeps
                FROM gibbonCareNap
                WHERE gibbonSchoolYearID=:gibbonSchoolYearID
                AND date=:date";

        return $this->db()->selectOne($sql, $data) ?: [
            'totalNaps' => 0,
            'childrenNapped' => 0,
            'currentlySleeping' => 0,
            'completedNaps' => 0,
            'avgDurationMinutes' => 0,
            'soundSleeps' => 0,
            'lightSleeps' => 0,
            'restlessSleeps' => 0,
        ];
    }

    /**
     * Get nap statistics for a child over a date range.
     *
     * @param int $gibbonPersonID
     * @param string $dateStart
     * @param string $dateEnd
     * @return array
     */
    public function getNapStatsByPersonAndDateRange($gibbonPersonID, $dateStart, $dateEnd)
    {
        $data = ['gibbonPersonID' => $gibbonPersonID, 'dateStart' => $dateStart, 'dateEnd' => $dateEnd];
        $sql = "SELECT
                    COUNT(*) as totalNaps,
                    COUNT(DISTINCT date) as daysWithNaps,
                    AVG(TIMESTAMPDIFF(MINUTE, startTime, endTime)) as avgDurationMinutes,
                    SUM(CASE WHEN quality='Sound' THEN 1 ELSE 0 END) as soundSleeps,
                    SUM(CASE WHEN quality='Light' THEN 1 ELSE 0 END) as lightSleeps,
                    SUM(CASE WHEN quality='Restless' THEN 1 ELSE 0 END) as restlessSleeps
                FROM gibbonCareNap
                WHERE gibbonPersonID=:gibbonPersonID
                AND date >= :dateStart
                AND date <= :dateEnd
                AND endTime IS NOT NULL";

        return $this->db()->selectOne($sql, $data) ?: [
            'totalNaps' => 0,
            'daysWithNaps' => 0,
            'avgDurationMinutes' => 0,
            'soundSleeps' => 0,
            'lightSleeps' => 0,
            'restlessSleeps' => 0,
        ];
    }

    /**
     * Start a nap for a child.
     *
     * @param int $gibbonPersonID
     * @param int $gibbonSchoolYearID
     * @param string $date
     * @param string $startTime
     * @param int $recordedByID
     * @param string|null $notes
     * @return int|false
     */
    public function startNap($gibbonPersonID, $gibbonSchoolYearID, $date, $startTime, $recordedByID, $notes = null)
    {
        // Check if there's an active nap already
        $activeNap = $this->getActiveNap($gibbonPersonID, $date);
        if ($activeNap) {
            return false; // Can't start a new nap if one is in progress
        }

        return $this->insert([
            'gibbonPersonID' => $gibbonPersonID,
            'gibbonSchoolYearID' => $gibbonSchoolYearID,
            'date' => $date,
            'startTime' => $startTime,
            'recordedByID' => $recordedByID,
            'notes' => $notes,
        ]);
    }

    /**
     * End a nap for a child.
     *
     * @param int $gibbonCareNapID
     * @param string $endTime
     * @param string|null $quality
     * @param string|null $notes
     * @return bool
     */
    public function endNap($gibbonCareNapID, $endTime, $quality = null, $notes = null)
    {
        $data = [
            'endTime' => $endTime,
        ];

        if ($quality !== null) {
            $data['quality'] = $quality;
        }

        if ($notes !== null) {
            $data['notes'] = $notes;
        }

        return $this->update($gibbonCareNapID, $data);
    }

    /**
     * Select children who are currently napping.
     *
     * @param int $gibbonSchoolYearID
     * @param string $date
     * @return \Gibbon\Database\Result
     */
    public function selectChildrenCurrentlyNapping($gibbonSchoolYearID, $date)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonCareNap.gibbonCareNapID',
                'gibbonCareNap.gibbonPersonID',
                'gibbonCareNap.startTime',
                'TIMESTAMPDIFF(MINUTE, gibbonCareNap.startTime, NOW()) as currentDurationMinutes',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
            ])
            ->innerJoin('gibbonPerson', 'gibbonCareNap.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->where('gibbonCareNap.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID)
            ->where('gibbonCareNap.date=:date')
            ->bindValue('date', $date)
            ->where('gibbonCareNap.endTime IS NULL')
            ->orderBy(['gibbonCareNap.startTime ASC']);

        return $this->runSelect($query);
    }

    /**
     * Select children who are checked in but haven't napped today.
     *
     * @param int $gibbonSchoolYearID
     * @param string $date
     * @return \Gibbon\Database\Result
     */
    public function selectChildrenWithoutNap($gibbonSchoolYearID, $date)
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
                    SELECT 1 FROM gibbonCareNap
                    WHERE gibbonCareNap.gibbonPersonID=gibbonPerson.gibbonPersonID
                    AND gibbonCareNap.date=:date
                )
                ORDER BY gibbonPerson.surname, gibbonPerson.preferredName";

        return $this->db()->select($sql, $data);
    }
}
