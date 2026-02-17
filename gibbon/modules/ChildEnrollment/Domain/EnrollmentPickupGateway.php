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

namespace Gibbon\Module\ChildEnrollment\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\QueryableGateway;

/**
 * Enrollment Pickup Gateway
 *
 * Handles authorized pickup persons for child enrollment forms.
 * Each enrollment form can have multiple authorized pickup persons with priority ordering.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class EnrollmentPickupGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonChildEnrollmentAuthorizedPickup';
    private static $primaryKey = 'gibbonChildEnrollmentAuthorizedPickupID';

    private static $searchableColumns = [
        'gibbonChildEnrollmentAuthorizedPickup.name',
        'gibbonChildEnrollmentAuthorizedPickup.phone',
        'gibbonChildEnrollmentAuthorizedPickup.notes',
    ];

    /**
     * Query authorized pickup records with criteria support.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonChildEnrollmentFormID
     * @return DataSet
     */
    public function queryPickupsByForm(QueryCriteria $criteria, $gibbonChildEnrollmentFormID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonChildEnrollmentAuthorizedPickup.gibbonChildEnrollmentAuthorizedPickupID',
                'gibbonChildEnrollmentAuthorizedPickup.gibbonChildEnrollmentFormID',
                'gibbonChildEnrollmentAuthorizedPickup.name',
                'gibbonChildEnrollmentAuthorizedPickup.relationship',
                'gibbonChildEnrollmentAuthorizedPickup.phone',
                'gibbonChildEnrollmentAuthorizedPickup.photoPath',
                'gibbonChildEnrollmentAuthorizedPickup.priority',
                'gibbonChildEnrollmentAuthorizedPickup.notes',
                'gibbonChildEnrollmentAuthorizedPickup.timestampCreated',
                'gibbonChildEnrollmentAuthorizedPickup.timestampModified',
            ])
            ->where('gibbonChildEnrollmentAuthorizedPickup.gibbonChildEnrollmentFormID=:gibbonChildEnrollmentFormID')
            ->bindValue('gibbonChildEnrollmentFormID', $gibbonChildEnrollmentFormID);

        $criteria->addFilterRules([
            'relationship' => function ($query, $relationship) {
                return $query
                    ->where('gibbonChildEnrollmentAuthorizedPickup.relationship=:relationship')
                    ->bindValue('relationship', $relationship);
            },
            'priority' => function ($query, $priority) {
                return $query
                    ->where('gibbonChildEnrollmentAuthorizedPickup.priority=:priority')
                    ->bindValue('priority', $priority);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Select all authorized pickups for an enrollment form.
     *
     * @param int $gibbonChildEnrollmentFormID
     * @return \Gibbon\Database\Result
     */
    public function selectPickupsByForm($gibbonChildEnrollmentFormID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols(['*'])
            ->where('gibbonChildEnrollmentFormID=:gibbonChildEnrollmentFormID')
            ->bindValue('gibbonChildEnrollmentFormID', $gibbonChildEnrollmentFormID)
            ->orderBy(['priority ASC']);

        return $this->runSelect($query);
    }

    /**
     * Get a specific authorized pickup by ID.
     *
     * @param int $gibbonChildEnrollmentAuthorizedPickupID
     * @return array|false
     */
    public function getPickupByID($gibbonChildEnrollmentAuthorizedPickupID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols(['*'])
            ->where('gibbonChildEnrollmentAuthorizedPickupID=:gibbonChildEnrollmentAuthorizedPickupID')
            ->bindValue('gibbonChildEnrollmentAuthorizedPickupID', $gibbonChildEnrollmentAuthorizedPickupID);

        $result = $this->runSelect($query);
        return $result->isNotEmpty() ? $result->fetch() : false;
    }

    /**
     * Get the highest priority pickup person for a form.
     *
     * @param int $gibbonChildEnrollmentFormID
     * @return array|false
     */
    public function getPrimaryPickupByForm($gibbonChildEnrollmentFormID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols(['*'])
            ->where('gibbonChildEnrollmentFormID=:gibbonChildEnrollmentFormID')
            ->bindValue('gibbonChildEnrollmentFormID', $gibbonChildEnrollmentFormID)
            ->orderBy(['priority ASC'])
            ->limit(1);

        $result = $this->runSelect($query);
        return $result->isNotEmpty() ? $result->fetch() : false;
    }

    /**
     * Add a new authorized pickup person.
     *
     * @param int $gibbonChildEnrollmentFormID
     * @param array $data
     * @return int|false Returns the pickup ID on success
     */
    public function addPickup($gibbonChildEnrollmentFormID, array $data)
    {
        // Set form ID
        $data['gibbonChildEnrollmentFormID'] = $gibbonChildEnrollmentFormID;

        // Auto-assign priority if not provided
        if (empty($data['priority'])) {
            $data['priority'] = $this->getNextPriority($gibbonChildEnrollmentFormID);
        }

        return $this->insert($data);
    }

    /**
     * Update an authorized pickup person.
     *
     * @param int $gibbonChildEnrollmentAuthorizedPickupID
     * @param array $data
     * @return bool
     */
    public function updatePickup($gibbonChildEnrollmentAuthorizedPickupID, array $data)
    {
        return $this->update($gibbonChildEnrollmentAuthorizedPickupID, $data);
    }

    /**
     * Delete an authorized pickup person.
     *
     * @param int $gibbonChildEnrollmentAuthorizedPickupID
     * @return bool
     */
    public function deletePickup($gibbonChildEnrollmentAuthorizedPickupID)
    {
        return $this->delete($gibbonChildEnrollmentAuthorizedPickupID);
    }

    /**
     * Delete all authorized pickups for an enrollment form.
     *
     * @param int $gibbonChildEnrollmentFormID
     * @return int Number of rows deleted
     */
    public function deletePickupsByForm($gibbonChildEnrollmentFormID)
    {
        $data = ['gibbonChildEnrollmentFormID' => $gibbonChildEnrollmentFormID];
        $sql = "DELETE FROM gibbonChildEnrollmentAuthorizedPickup
                WHERE gibbonChildEnrollmentFormID=:gibbonChildEnrollmentFormID";

        return $this->db()->statement($sql, $data);
    }

    /**
     * Get the next available priority number for a form.
     *
     * @param int $gibbonChildEnrollmentFormID
     * @return int
     */
    public function getNextPriority($gibbonChildEnrollmentFormID)
    {
        $data = ['gibbonChildEnrollmentFormID' => $gibbonChildEnrollmentFormID];
        $sql = "SELECT MAX(priority) as maxPriority FROM gibbonChildEnrollmentAuthorizedPickup
                WHERE gibbonChildEnrollmentFormID=:gibbonChildEnrollmentFormID";

        $result = $this->db()->selectOne($sql, $data);
        return $result ? (int) $result['maxPriority'] + 1 : 1;
    }

    /**
     * Count authorized pickups for an enrollment form.
     *
     * @param int $gibbonChildEnrollmentFormID
     * @return int
     */
    public function countPickupsByForm($gibbonChildEnrollmentFormID)
    {
        $data = ['gibbonChildEnrollmentFormID' => $gibbonChildEnrollmentFormID];
        $sql = "SELECT COUNT(*) as count FROM gibbonChildEnrollmentAuthorizedPickup
                WHERE gibbonChildEnrollmentFormID=:gibbonChildEnrollmentFormID";

        $result = $this->db()->selectOne($sql, $data);
        return $result ? (int) $result['count'] : 0;
    }

    /**
     * Reorder priorities for authorized pickups on a form.
     *
     * @param int $gibbonChildEnrollmentFormID
     * @param array $pickupIDs Array of pickup IDs in desired order
     * @return bool
     */
    public function reorderPickups($gibbonChildEnrollmentFormID, array $pickupIDs)
    {
        $priority = 1;
        foreach ($pickupIDs as $pickupID) {
            $data = [
                'gibbonChildEnrollmentAuthorizedPickupID' => $pickupID,
                'gibbonChildEnrollmentFormID' => $gibbonChildEnrollmentFormID,
                'priority' => $priority,
            ];
            $sql = "UPDATE gibbonChildEnrollmentAuthorizedPickup
                    SET priority=:priority
                    WHERE gibbonChildEnrollmentAuthorizedPickupID=:gibbonChildEnrollmentAuthorizedPickupID
                    AND gibbonChildEnrollmentFormID=:gibbonChildEnrollmentFormID";

            $this->db()->statement($sql, $data);
            $priority++;
        }

        return true;
    }

    /**
     * Search authorized pickups by name across all forms.
     *
     * @param QueryCriteria $criteria
     * @param string $searchTerm
     * @return DataSet
     */
    public function queryPickupsBySearch(QueryCriteria $criteria, $searchTerm)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonChildEnrollmentAuthorizedPickup.gibbonChildEnrollmentAuthorizedPickupID',
                'gibbonChildEnrollmentAuthorizedPickup.gibbonChildEnrollmentFormID',
                'gibbonChildEnrollmentAuthorizedPickup.name',
                'gibbonChildEnrollmentAuthorizedPickup.relationship',
                'gibbonChildEnrollmentAuthorizedPickup.phone',
                'gibbonChildEnrollmentAuthorizedPickup.priority',
                'gibbonChildEnrollmentForm.formNumber',
                'gibbonChildEnrollmentForm.childFirstName',
                'gibbonChildEnrollmentForm.childLastName',
                'gibbonChildEnrollmentForm.status as formStatus',
            ])
            ->innerJoin('gibbonChildEnrollmentForm', 'gibbonChildEnrollmentAuthorizedPickup.gibbonChildEnrollmentFormID=gibbonChildEnrollmentForm.gibbonChildEnrollmentFormID')
            ->where('(gibbonChildEnrollmentAuthorizedPickup.name LIKE :searchTerm OR gibbonChildEnrollmentAuthorizedPickup.phone LIKE :searchTerm)')
            ->bindValue('searchTerm', '%' . $searchTerm . '%');

        return $this->runQuery($query, $criteria);
    }

    /**
     * Get pickup contact info for a form (for display purposes).
     *
     * @param int $gibbonChildEnrollmentFormID
     * @return array Array of pickup contact info
     */
    public function getPickupContactInfo($gibbonChildEnrollmentFormID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'name',
                'relationship',
                'phone',
                'photoPath',
                'priority',
                'notes',
            ])
            ->where('gibbonChildEnrollmentFormID=:gibbonChildEnrollmentFormID')
            ->bindValue('gibbonChildEnrollmentFormID', $gibbonChildEnrollmentFormID)
            ->orderBy(['priority ASC']);

        return $this->runSelect($query)->fetchAll();
    }

    /**
     * Check if a pickup person with the same name already exists for a form.
     *
     * @param int $gibbonChildEnrollmentFormID
     * @param string $name
     * @param int|null $excludeID Optionally exclude a specific pickup ID from the check
     * @return bool
     */
    public function pickupNameExists($gibbonChildEnrollmentFormID, $name, $excludeID = null)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols(['gibbonChildEnrollmentAuthorizedPickupID'])
            ->where('gibbonChildEnrollmentFormID=:gibbonChildEnrollmentFormID')
            ->bindValue('gibbonChildEnrollmentFormID', $gibbonChildEnrollmentFormID)
            ->where('name=:name')
            ->bindValue('name', $name);

        if ($excludeID !== null) {
            $query->where('gibbonChildEnrollmentAuthorizedPickupID!=:excludeID')
                  ->bindValue('excludeID', $excludeID);
        }

        $result = $this->runSelect($query);
        return $result->isNotEmpty();
    }

    /**
     * Update the photo path for an authorized pickup.
     *
     * @param int $gibbonChildEnrollmentAuthorizedPickupID
     * @param string $photoPath
     * @return bool
     */
    public function updatePickupPhoto($gibbonChildEnrollmentAuthorizedPickupID, $photoPath)
    {
        return $this->update($gibbonChildEnrollmentAuthorizedPickupID, ['photoPath' => $photoPath]);
    }

    /**
     * Validate pickup data before insert/update.
     *
     * @param array $data
     * @return array Array of validation errors (empty if valid)
     */
    public function validatePickupData(array $data)
    {
        $errors = [];

        // Required fields
        if (empty($data['name'])) {
            $errors[] = 'Pickup person name is required.';
        }

        if (empty($data['relationship'])) {
            $errors[] = 'Relationship to child is required.';
        }

        if (empty($data['phone'])) {
            $errors[] = 'Phone number is required.';
        }

        // Validate priority if provided
        if (isset($data['priority']) && (!is_numeric($data['priority']) || $data['priority'] < 1)) {
            $errors[] = 'Priority must be a positive integer.';
        }

        return $errors;
    }
}
