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

namespace Gibbon\Module\ExampleModule\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\QueryableGateway;

/**
 * Example Entity Gateway
 *
 * Handles database operations for example entities in the Example Module.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class ExampleEntityGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonExampleEntity';
    private static $primaryKey = 'gibbonExampleEntityID';

    private static $searchableColumns = ['gibbonExampleEntity.title', 'gibbonExampleEntity.description', 'gibbonPerson.preferredName', 'gibbonPerson.surname'];

    /**
     * Query example entities with criteria support.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonSchoolYearID
     * @return DataSet
     */
    public function queryExampleEntities(QueryCriteria $criteria, $gibbonSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonExampleEntity.gibbonExampleEntityID',
                'gibbonExampleEntity.gibbonPersonID',
                'gibbonExampleEntity.gibbonSchoolYearID',
                'gibbonExampleEntity.title',
                'gibbonExampleEntity.description',
                'gibbonExampleEntity.status',
                'gibbonExampleEntity.timestampCreated',
                'gibbonExampleEntity.timestampModified',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
                'createdBy.preferredName as createdByName',
                'createdBy.surname as createdBySurname',
            ])
            ->innerJoin('gibbonPerson', 'gibbonExampleEntity.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->leftJoin('gibbonPerson as createdBy', 'gibbonExampleEntity.createdByID=createdBy.gibbonPersonID')
            ->where('gibbonExampleEntity.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID);

        $criteria->addFilterRules([
            'status' => function ($query, $status) {
                return $query
                    ->where('gibbonExampleEntity.status=:status')
                    ->bindValue('status', $status);
            },
            'person' => function ($query, $gibbonPersonID) {
                return $query
                    ->where('gibbonExampleEntity.gibbonPersonID=:gibbonPersonID')
                    ->bindValue('gibbonPersonID', $gibbonPersonID);
            },
            'title' => function ($query, $title) {
                return $query
                    ->where('gibbonExampleEntity.title LIKE :title')
                    ->bindValue('title', '%'.$title.'%');
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query example entities by status.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonSchoolYearID
     * @param string $status Status: Active, Inactive, or Pending
     * @return DataSet
     */
    public function queryExampleEntitiesByStatus(QueryCriteria $criteria, $gibbonSchoolYearID, $status)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonExampleEntity.gibbonExampleEntityID',
                'gibbonExampleEntity.gibbonPersonID',
                'gibbonExampleEntity.title',
                'gibbonExampleEntity.description',
                'gibbonExampleEntity.status',
                'gibbonExampleEntity.timestampCreated',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
            ])
            ->innerJoin('gibbonPerson', 'gibbonExampleEntity.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->where('gibbonExampleEntity.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID)
            ->where('gibbonExampleEntity.status=:status')
            ->bindValue('status', $status)
            ->orderBy(['gibbonExampleEntity.timestampCreated DESC']);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query example entities for a specific person.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonPersonID
     * @param int $gibbonSchoolYearID
     * @return DataSet
     */
    public function queryExampleEntitiesByPerson(QueryCriteria $criteria, $gibbonPersonID, $gibbonSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonExampleEntity.gibbonExampleEntityID',
                'gibbonExampleEntity.title',
                'gibbonExampleEntity.description',
                'gibbonExampleEntity.status',
                'gibbonExampleEntity.timestampCreated',
                'gibbonExampleEntity.timestampModified',
                'createdBy.preferredName as createdByName',
                'createdBy.surname as createdBySurname',
            ])
            ->leftJoin('gibbonPerson as createdBy', 'gibbonExampleEntity.createdByID=createdBy.gibbonPersonID')
            ->where('gibbonExampleEntity.gibbonPersonID=:gibbonPersonID')
            ->bindValue('gibbonPersonID', $gibbonPersonID)
            ->where('gibbonExampleEntity.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Select a single example entity by ID.
     *
     * @param int $gibbonExampleEntityID
     * @return \Gibbon\Database\Result
     */
    public function selectExampleEntityByID($gibbonExampleEntityID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonExampleEntity.*',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
                'createdBy.preferredName as createdByName',
                'createdBy.surname as createdBySurname',
            ])
            ->innerJoin('gibbonPerson', 'gibbonExampleEntity.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->leftJoin('gibbonPerson as createdBy', 'gibbonExampleEntity.createdByID=createdBy.gibbonPersonID')
            ->where('gibbonExampleEntity.gibbonExampleEntityID=:gibbonExampleEntityID')
            ->bindValue('gibbonExampleEntityID', $gibbonExampleEntityID);

        return $this->runSelect($query);
    }

    /**
     * Select example entities by school year.
     *
     * @param int $gibbonSchoolYearID
     * @return \Gibbon\Database\Result
     */
    public function selectExampleEntitiesBySchoolYear($gibbonSchoolYearID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonExampleEntity.*',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
            ])
            ->innerJoin('gibbonPerson', 'gibbonExampleEntity.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->where('gibbonExampleEntity.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID)
            ->orderBy(['gibbonExampleEntity.timestampCreated DESC']);

        return $this->runSelect($query);
    }

    /**
     * Get statistics summary for example entities.
     *
     * @param int $gibbonSchoolYearID
     * @return array
     */
    public function getStatistics($gibbonSchoolYearID)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID];

        $sql = "SELECT
                    COUNT(*) as totalEntities,
                    SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) as activeCount,
                    SUM(CASE WHEN status = 'Inactive' THEN 1 ELSE 0 END) as inactiveCount,
                    SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pendingCount
                FROM gibbonExampleEntity
                WHERE gibbonSchoolYearID = :gibbonSchoolYearID";

        $result = $this->db()->selectOne($sql, $data);

        return [
            'totalEntities' => $result['totalEntities'] ?? 0,
            'activeCount' => $result['activeCount'] ?? 0,
            'inactiveCount' => $result['inactiveCount'] ?? 0,
            'pendingCount' => $result['pendingCount'] ?? 0,
        ];
    }

    /**
     * Insert a new example entity.
     *
     * @param array $data
     * @return int|bool Returns the new ID on success, false on failure
     */
    public function insertExampleEntity(array $data)
    {
        return $this->db()->insert($this->getTableName(), $data);
    }

    /**
     * Update an existing example entity.
     *
     * @param int $gibbonExampleEntityID
     * @param array $data
     * @return bool Returns true on success, false on failure
     */
    public function updateExampleEntity($gibbonExampleEntityID, array $data)
    {
        $data['gibbonExampleEntityID'] = $gibbonExampleEntityID;
        return $this->db()->update($this->getTableName(), $data, ['gibbonExampleEntityID' => $gibbonExampleEntityID]);
    }

    /**
     * Delete an example entity.
     *
     * @param int $gibbonExampleEntityID
     * @return bool Returns true on success, false on failure
     */
    public function deleteExampleEntity($gibbonExampleEntityID)
    {
        return $this->db()->delete($this->getTableName(), ['gibbonExampleEntityID' => $gibbonExampleEntityID]);
    }

    /**
     * Check if a person has any example entities in a given school year.
     *
     * @param int $gibbonPersonID
     * @param int $gibbonSchoolYearID
     * @return bool
     */
    public function hasExampleEntities($gibbonPersonID, $gibbonSchoolYearID)
    {
        $data = [
            'gibbonPersonID' => $gibbonPersonID,
            'gibbonSchoolYearID' => $gibbonSchoolYearID,
        ];

        $sql = "SELECT COUNT(*) as count
                FROM gibbonExampleEntity
                WHERE gibbonPersonID = :gibbonPersonID
                AND gibbonSchoolYearID = :gibbonSchoolYearID";

        $result = $this->db()->selectOne($sql, $data);

        return ($result['count'] ?? 0) > 0;
    }
}
