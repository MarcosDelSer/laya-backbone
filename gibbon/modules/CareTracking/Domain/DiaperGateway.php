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
 * Care Tracking Diaper Gateway
 *
 * Handles diaper change tracking for children in childcare settings.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class DiaperGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonCareDiaper';
    private static $primaryKey = 'gibbonCareDiaperID';

    private static $searchableColumns = ['gibbonPerson.preferredName', 'gibbonPerson.surname', 'gibbonCareDiaper.notes'];

    /**
     * Query diaper records with criteria support.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonSchoolYearID
     * @return DataSet
     */
    public function queryDiapers(QueryCriteria $criteria, $gibbonSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonCareDiaper.gibbonCareDiaperID',
                'gibbonCareDiaper.gibbonPersonID',
                'gibbonCareDiaper.date',
                'gibbonCareDiaper.time',
                'gibbonCareDiaper.type',
                'gibbonCareDiaper.notes',
                'gibbonCareDiaper.timestampCreated',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
                'recordedBy.preferredName as recordedByName',
                'recordedBy.surname as recordedBySurname',
            ])
            ->innerJoin('gibbonPerson', 'gibbonCareDiaper.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->leftJoin('gibbonPerson as recordedBy', 'gibbonCareDiaper.recordedByID=recordedBy.gibbonPersonID')
            ->where('gibbonCareDiaper.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID);

        $criteria->addFilterRules([
            'date' => function ($query, $date) {
                return $query
                    ->where('gibbonCareDiaper.date=:date')
                    ->bindValue('date', $date);
            },
            'child' => function ($query, $gibbonPersonID) {
                return $query
                    ->where('gibbonCareDiaper.gibbonPersonID=:gibbonPersonID')
                    ->bindValue('gibbonPersonID', $gibbonPersonID);
            },
            'type' => function ($query, $type) {
                return $query
                    ->where('gibbonCareDiaper.type=:type')
                    ->bindValue('type', $type);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query diaper records for a specific date.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonSchoolYearID
     * @param string $date
     * @return DataSet
     */
    public function queryDiapersByDate(QueryCriteria $criteria, $gibbonSchoolYearID, $date)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonCareDiaper.gibbonCareDiaperID',
                'gibbonCareDiaper.gibbonPersonID',
                'gibbonCareDiaper.date',
                'gibbonCareDiaper.time',
                'gibbonCareDiaper.type',
                'gibbonCareDiaper.notes',
                'gibbonCareDiaper.timestampCreated',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
                'gibbonPerson.dob',
            ])
            ->innerJoin('gibbonPerson', 'gibbonCareDiaper.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->where('gibbonCareDiaper.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID)
            ->where('gibbonCareDiaper.date=:date')
            ->bindValue('date', $date);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query diaper history for a specific child.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonPersonID
     * @param int $gibbonSchoolYearID
     * @return DataSet
     */
    public function queryDiapersByPerson(QueryCriteria $criteria, $gibbonPersonID, $gibbonSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonCareDiaper.gibbonCareDiaperID',
                'gibbonCareDiaper.date',
                'gibbonCareDiaper.time',
                'gibbonCareDiaper.type',
                'gibbonCareDiaper.notes',
                'gibbonCareDiaper.timestampCreated',
                'recordedBy.preferredName as recordedByName',
                'recordedBy.surname as recordedBySurname',
            ])
            ->leftJoin('gibbonPerson as recordedBy', 'gibbonCareDiaper.recordedByID=recordedBy.gibbonPersonID')
            ->where('gibbonCareDiaper.gibbonPersonID=:gibbonPersonID')
            ->bindValue('gibbonPersonID', $gibbonPersonID)
            ->where('gibbonCareDiaper.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Get diaper changes for a specific child on a specific date.
     *
     * @param int $gibbonPersonID
     * @param string $date
     * @return \Gibbon\Database\Result
     */
    public function selectDiapersByPersonAndDate($gibbonPersonID, $date)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonCareDiaper.*',
                'recordedBy.preferredName as recordedByName',
                'recordedBy.surname as recordedBySurname',
            ])
            ->leftJoin('gibbonPerson as recordedBy', 'gibbonCareDiaper.recordedByID=recordedBy.gibbonPersonID')
            ->where('gibbonCareDiaper.gibbonPersonID=:gibbonPersonID')
            ->bindValue('gibbonPersonID', $gibbonPersonID)
            ->where('gibbonCareDiaper.date=:date')
            ->bindValue('date', $date)
            ->orderBy(['gibbonCareDiaper.time ASC']);

        return $this->runSelect($query);
    }

    /**
     * Get the most recent diaper change for a child.
     *
     * @param int $gibbonPersonID
     * @param string|null $date
     * @return array|false
     */
    public function getLastDiaperChange($gibbonPersonID, $date = null)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols(['*'])
            ->where('gibbonPersonID=:gibbonPersonID')
            ->bindValue('gibbonPersonID', $gibbonPersonID);

        if ($date !== null) {
            $query->where('date=:date')
                  ->bindValue('date', $date);
        }

        $query->orderBy(['date DESC', 'time DESC'])
              ->limit(1);

        $result = $this->runSelect($query);
        return $result->isNotEmpty() ? $result->fetch() : false;
    }

    /**
     * Get diaper change summary for a specific date.
     *
     * @param int $gibbonSchoolYearID
     * @param string $date
     * @return array
     */
    public function getDiaperSummaryByDate($gibbonSchoolYearID, $date)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID, 'date' => $date];
        $sql = "SELECT
                    COUNT(*) as totalChanges,
                    COUNT(DISTINCT gibbonPersonID) as childrenChanged,
                    SUM(CASE WHEN type='Wet' THEN 1 ELSE 0 END) as wetCount,
                    SUM(CASE WHEN type='Soiled' THEN 1 ELSE 0 END) as soiledCount,
                    SUM(CASE WHEN type='Both' THEN 1 ELSE 0 END) as bothCount,
                    SUM(CASE WHEN type='Dry' THEN 1 ELSE 0 END) as dryCount
                FROM gibbonCareDiaper
                WHERE gibbonSchoolYearID=:gibbonSchoolYearID
                AND date=:date";

        return $this->db()->selectOne($sql, $data) ?: [
            'totalChanges' => 0,
            'childrenChanged' => 0,
            'wetCount' => 0,
            'soiledCount' => 0,
            'bothCount' => 0,
            'dryCount' => 0,
        ];
    }

    /**
     * Get diaper statistics for a child over a date range.
     *
     * @param int $gibbonPersonID
     * @param string $dateStart
     * @param string $dateEnd
     * @return array
     */
    public function getDiaperStatsByPersonAndDateRange($gibbonPersonID, $dateStart, $dateEnd)
    {
        $data = ['gibbonPersonID' => $gibbonPersonID, 'dateStart' => $dateStart, 'dateEnd' => $dateEnd];
        $sql = "SELECT
                    COUNT(*) as totalChanges,
                    COUNT(DISTINCT date) as daysTracked,
                    AVG(changesPerDay.dailyCount) as avgChangesPerDay,
                    SUM(CASE WHEN type='Wet' THEN 1 ELSE 0 END) as wetCount,
                    SUM(CASE WHEN type='Soiled' THEN 1 ELSE 0 END) as soiledCount,
                    SUM(CASE WHEN type='Both' THEN 1 ELSE 0 END) as bothCount,
                    SUM(CASE WHEN type='Dry' THEN 1 ELSE 0 END) as dryCount
                FROM gibbonCareDiaper
                LEFT JOIN (
                    SELECT date, COUNT(*) as dailyCount
                    FROM gibbonCareDiaper
                    WHERE gibbonPersonID=:gibbonPersonID
                    AND date >= :dateStart
                    AND date <= :dateEnd
                    GROUP BY date
                ) as changesPerDay ON gibbonCareDiaper.date=changesPerDay.date
                WHERE gibbonCareDiaper.gibbonPersonID=:gibbonPersonID
                AND gibbonCareDiaper.date >= :dateStart
                AND gibbonCareDiaper.date <= :dateEnd";

        return $this->db()->selectOne($sql, $data) ?: [
            'totalChanges' => 0,
            'daysTracked' => 0,
            'avgChangesPerDay' => 0,
            'wetCount' => 0,
            'soiledCount' => 0,
            'bothCount' => 0,
            'dryCount' => 0,
        ];
    }

    /**
     * Log a diaper change for a child.
     *
     * @param int $gibbonPersonID
     * @param int $gibbonSchoolYearID
     * @param string $date
     * @param string $time
     * @param string $type
     * @param int $recordedByID
     * @param string|null $notes
     * @return int|false
     */
    public function logDiaperChange($gibbonPersonID, $gibbonSchoolYearID, $date, $time, $type, $recordedByID, $notes = null)
    {
        return $this->insert([
            'gibbonPersonID' => $gibbonPersonID,
            'gibbonSchoolYearID' => $gibbonSchoolYearID,
            'date' => $date,
            'time' => $time,
            'type' => $type,
            'recordedByID' => $recordedByID,
            'notes' => $notes,
        ]);
    }

    /**
     * Get children who need a diaper change (haven't been changed in X hours).
     *
     * @param int $gibbonSchoolYearID
     * @param string $date
     * @param int $hoursSinceLastChange
     * @return \Gibbon\Database\Result
     */
    public function selectChildrenNeedingChange($gibbonSchoolYearID, $date, $hoursSinceLastChange = 2)
    {
        $data = [
            'gibbonSchoolYearID' => $gibbonSchoolYearID,
            'date' => $date,
            'hours' => $hoursSinceLastChange,
        ];
        $sql = "SELECT
                    gibbonPerson.gibbonPersonID,
                    gibbonPerson.preferredName,
                    gibbonPerson.surname,
                    gibbonPerson.image_240,
                    lastChange.lastTime,
                    TIMESTAMPDIFF(MINUTE, lastChange.lastDateTime, NOW()) as minutesSinceLastChange
                FROM gibbonCareAttendance
                INNER JOIN gibbonPerson ON gibbonCareAttendance.gibbonPersonID=gibbonPerson.gibbonPersonID
                LEFT JOIN (
                    SELECT gibbonPersonID,
                           MAX(time) as lastTime,
                           MAX(CONCAT(date, ' ', time)) as lastDateTime
                    FROM gibbonCareDiaper
                    WHERE date=:date
                    GROUP BY gibbonPersonID
                ) as lastChange ON gibbonPerson.gibbonPersonID=lastChange.gibbonPersonID
                WHERE gibbonCareAttendance.gibbonSchoolYearID=:gibbonSchoolYearID
                AND gibbonCareAttendance.date=:date
                AND gibbonCareAttendance.checkInTime IS NOT NULL
                AND gibbonCareAttendance.checkOutTime IS NULL
                AND (
                    lastChange.lastDateTime IS NULL
                    OR TIMESTAMPDIFF(HOUR, lastChange.lastDateTime, NOW()) >= :hours
                )
                ORDER BY COALESCE(lastChange.lastDateTime, '1970-01-01') ASC";

        return $this->db()->select($sql, $data);
    }

    /**
     * Get diaper change count for a child on a specific date.
     *
     * @param int $gibbonPersonID
     * @param string $date
     * @return int
     */
    public function countDiaperChangesByPersonAndDate($gibbonPersonID, $date)
    {
        $data = ['gibbonPersonID' => $gibbonPersonID, 'date' => $date];
        $sql = "SELECT COUNT(*) FROM gibbonCareDiaper
                WHERE gibbonPersonID=:gibbonPersonID AND date=:date";

        return (int) $this->db()->selectOne($sql, $data);
    }
}
