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
 * Enrollment Parent Gateway
 *
 * Handles parent/guardian information for child enrollment forms.
 * Each enrollment form can have up to 2 parents (Parent 1 and Parent 2).
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class EnrollmentParentGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonChildEnrollmentParent';
    private static $primaryKey = 'gibbonChildEnrollmentParentID';

    private static $searchableColumns = [
        'gibbonChildEnrollmentParent.name',
        'gibbonChildEnrollmentParent.email',
        'gibbonChildEnrollmentParent.employer',
    ];

    /**
     * Query parent records with criteria support.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonChildEnrollmentFormID
     * @return DataSet
     */
    public function queryParentsByForm(QueryCriteria $criteria, $gibbonChildEnrollmentFormID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonChildEnrollmentParent.gibbonChildEnrollmentParentID',
                'gibbonChildEnrollmentParent.gibbonChildEnrollmentFormID',
                'gibbonChildEnrollmentParent.parentNumber',
                'gibbonChildEnrollmentParent.name',
                'gibbonChildEnrollmentParent.relationship',
                'gibbonChildEnrollmentParent.address',
                'gibbonChildEnrollmentParent.city',
                'gibbonChildEnrollmentParent.postalCode',
                'gibbonChildEnrollmentParent.homePhone',
                'gibbonChildEnrollmentParent.cellPhone',
                'gibbonChildEnrollmentParent.workPhone',
                'gibbonChildEnrollmentParent.email',
                'gibbonChildEnrollmentParent.employer',
                'gibbonChildEnrollmentParent.workAddress',
                'gibbonChildEnrollmentParent.workHours',
                'gibbonChildEnrollmentParent.isPrimaryContact',
                'gibbonChildEnrollmentParent.timestampCreated',
                'gibbonChildEnrollmentParent.timestampModified',
            ])
            ->where('gibbonChildEnrollmentParent.gibbonChildEnrollmentFormID=:gibbonChildEnrollmentFormID')
            ->bindValue('gibbonChildEnrollmentFormID', $gibbonChildEnrollmentFormID);

        $criteria->addFilterRules([
            'parentNumber' => function ($query, $parentNumber) {
                return $query
                    ->where('gibbonChildEnrollmentParent.parentNumber=:parentNumber')
                    ->bindValue('parentNumber', $parentNumber);
            },
            'relationship' => function ($query, $relationship) {
                return $query
                    ->where('gibbonChildEnrollmentParent.relationship=:relationship')
                    ->bindValue('relationship', $relationship);
            },
            'isPrimaryContact' => function ($query, $value) {
                return $query
                    ->where('gibbonChildEnrollmentParent.isPrimaryContact=:isPrimaryContact')
                    ->bindValue('isPrimaryContact', $value);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Select all parents for an enrollment form.
     *
     * @param int $gibbonChildEnrollmentFormID
     * @return \Gibbon\Database\Result
     */
    public function selectParentsByForm($gibbonChildEnrollmentFormID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols(['*'])
            ->where('gibbonChildEnrollmentFormID=:gibbonChildEnrollmentFormID')
            ->bindValue('gibbonChildEnrollmentFormID', $gibbonChildEnrollmentFormID)
            ->orderBy(['parentNumber ASC']);

        return $this->runSelect($query);
    }

    /**
     * Get a specific parent by form ID and parent number.
     *
     * @param int $gibbonChildEnrollmentFormID
     * @param string $parentNumber '1' or '2'
     * @return array|false
     */
    public function getParentByFormAndNumber($gibbonChildEnrollmentFormID, $parentNumber)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols(['*'])
            ->where('gibbonChildEnrollmentFormID=:gibbonChildEnrollmentFormID')
            ->bindValue('gibbonChildEnrollmentFormID', $gibbonChildEnrollmentFormID)
            ->where('parentNumber=:parentNumber')
            ->bindValue('parentNumber', $parentNumber);

        $result = $this->runSelect($query);
        return $result->isNotEmpty() ? $result->fetch() : false;
    }

    /**
     * Get the primary contact parent for a form.
     *
     * @param int $gibbonChildEnrollmentFormID
     * @return array|false
     */
    public function getPrimaryContactByForm($gibbonChildEnrollmentFormID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols(['*'])
            ->where('gibbonChildEnrollmentFormID=:gibbonChildEnrollmentFormID')
            ->bindValue('gibbonChildEnrollmentFormID', $gibbonChildEnrollmentFormID)
            ->where('isPrimaryContact=:isPrimaryContact')
            ->bindValue('isPrimaryContact', 'Y');

        $result = $this->runSelect($query);
        return $result->isNotEmpty() ? $result->fetch() : false;
    }

    /**
     * Insert or update a parent record (upsert based on form + parentNumber).
     *
     * @param int $gibbonChildEnrollmentFormID
     * @param string $parentNumber '1' or '2'
     * @param array $data
     * @return int|false Returns the parent ID on success
     */
    public function upsertParent($gibbonChildEnrollmentFormID, $parentNumber, array $data)
    {
        $existing = $this->getParentByFormAndNumber($gibbonChildEnrollmentFormID, $parentNumber);

        $data['gibbonChildEnrollmentFormID'] = $gibbonChildEnrollmentFormID;
        $data['parentNumber'] = $parentNumber;

        if ($existing) {
            // Update existing record
            $success = $this->update($existing['gibbonChildEnrollmentParentID'], $data);
            return $success ? $existing['gibbonChildEnrollmentParentID'] : false;
        }

        // Create new record
        return $this->insert($data);
    }

    /**
     * Delete parent record by form ID and parent number.
     *
     * @param int $gibbonChildEnrollmentFormID
     * @param string $parentNumber '1' or '2'
     * @return bool
     */
    public function deleteParentByFormAndNumber($gibbonChildEnrollmentFormID, $parentNumber)
    {
        $existing = $this->getParentByFormAndNumber($gibbonChildEnrollmentFormID, $parentNumber);

        if ($existing) {
            return $this->delete($existing['gibbonChildEnrollmentParentID']);
        }

        return false;
    }

    /**
     * Delete all parents for an enrollment form.
     *
     * @param int $gibbonChildEnrollmentFormID
     * @return int Number of rows deleted
     */
    public function deleteParentsByForm($gibbonChildEnrollmentFormID)
    {
        $data = ['gibbonChildEnrollmentFormID' => $gibbonChildEnrollmentFormID];
        $sql = "DELETE FROM gibbonChildEnrollmentParent
                WHERE gibbonChildEnrollmentFormID=:gibbonChildEnrollmentFormID";

        return $this->db()->statement($sql, $data);
    }

    /**
     * Set a parent as the primary contact (and unset others on the same form).
     *
     * @param int $gibbonChildEnrollmentFormID
     * @param string $parentNumber '1' or '2'
     * @return bool
     */
    public function setPrimaryContact($gibbonChildEnrollmentFormID, $parentNumber)
    {
        // First, unset all primary contacts for this form
        $data = ['gibbonChildEnrollmentFormID' => $gibbonChildEnrollmentFormID];
        $sql = "UPDATE gibbonChildEnrollmentParent
                SET isPrimaryContact='N'
                WHERE gibbonChildEnrollmentFormID=:gibbonChildEnrollmentFormID";
        $this->db()->statement($sql, $data);

        // Then, set the specified parent as primary
        $existing = $this->getParentByFormAndNumber($gibbonChildEnrollmentFormID, $parentNumber);

        if ($existing) {
            return $this->update($existing['gibbonChildEnrollmentParentID'], ['isPrimaryContact' => 'Y']);
        }

        return false;
    }

    /**
     * Count parents for an enrollment form.
     *
     * @param int $gibbonChildEnrollmentFormID
     * @return int
     */
    public function countParentsByForm($gibbonChildEnrollmentFormID)
    {
        $data = ['gibbonChildEnrollmentFormID' => $gibbonChildEnrollmentFormID];
        $sql = "SELECT COUNT(*) as count FROM gibbonChildEnrollmentParent
                WHERE gibbonChildEnrollmentFormID=:gibbonChildEnrollmentFormID";

        $result = $this->db()->selectOne($sql, $data);
        return $result ? (int) $result['count'] : 0;
    }

    /**
     * Search parents by name or email across all forms.
     *
     * @param QueryCriteria $criteria
     * @param string $searchTerm
     * @return DataSet
     */
    public function queryParentsBySearch(QueryCriteria $criteria, $searchTerm)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonChildEnrollmentParent.gibbonChildEnrollmentParentID',
                'gibbonChildEnrollmentParent.gibbonChildEnrollmentFormID',
                'gibbonChildEnrollmentParent.parentNumber',
                'gibbonChildEnrollmentParent.name',
                'gibbonChildEnrollmentParent.relationship',
                'gibbonChildEnrollmentParent.email',
                'gibbonChildEnrollmentParent.cellPhone',
                'gibbonChildEnrollmentParent.isPrimaryContact',
                'gibbonChildEnrollmentForm.formNumber',
                'gibbonChildEnrollmentForm.childFirstName',
                'gibbonChildEnrollmentForm.childLastName',
                'gibbonChildEnrollmentForm.status as formStatus',
            ])
            ->innerJoin('gibbonChildEnrollmentForm', 'gibbonChildEnrollmentParent.gibbonChildEnrollmentFormID=gibbonChildEnrollmentForm.gibbonChildEnrollmentFormID')
            ->where('(gibbonChildEnrollmentParent.name LIKE :searchTerm OR gibbonChildEnrollmentParent.email LIKE :searchTerm)')
            ->bindValue('searchTerm', '%' . $searchTerm . '%');

        return $this->runQuery($query, $criteria);
    }

    /**
     * Get parent contact info for a form (for emergency contact purposes).
     *
     * @param int $gibbonChildEnrollmentFormID
     * @return array Array of parent contact info
     */
    public function getParentContactInfo($gibbonChildEnrollmentFormID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'name',
                'relationship',
                'homePhone',
                'cellPhone',
                'workPhone',
                'email',
                'isPrimaryContact',
                'parentNumber',
            ])
            ->where('gibbonChildEnrollmentFormID=:gibbonChildEnrollmentFormID')
            ->bindValue('gibbonChildEnrollmentFormID', $gibbonChildEnrollmentFormID)
            ->orderBy(['isPrimaryContact DESC', 'parentNumber ASC']);

        return $this->runSelect($query)->fetchAll();
    }

    /**
     * Validate parent data before insert/update.
     *
     * @param array $data
     * @return array Array of validation errors (empty if valid)
     */
    public function validateParentData(array $data)
    {
        $errors = [];

        // Required fields
        if (empty($data['name'])) {
            $errors[] = 'Parent name is required.';
        }

        if (empty($data['relationship'])) {
            $errors[] = 'Relationship to child is required.';
        }

        // At least one phone number is required
        if (empty($data['homePhone']) && empty($data['cellPhone']) && empty($data['workPhone'])) {
            $errors[] = 'At least one phone number is required.';
        }

        // Validate email format if provided
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format.';
        }

        // Validate parentNumber if provided
        if (!empty($data['parentNumber']) && !in_array($data['parentNumber'], ['1', '2'])) {
            $errors[] = 'Parent number must be 1 or 2.';
        }

        return $errors;
    }
}
