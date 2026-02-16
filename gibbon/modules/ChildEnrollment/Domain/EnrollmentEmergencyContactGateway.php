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
 * Enrollment Emergency Contact Gateway
 *
 * Handles emergency contacts for child enrollment forms.
 * Each enrollment form can have multiple emergency contacts with priority ordering.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class EnrollmentEmergencyContactGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonChildEnrollmentEmergencyContact';
    private static $primaryKey = 'gibbonChildEnrollmentEmergencyContactID';

    private static $searchableColumns = [
        'gibbonChildEnrollmentEmergencyContact.name',
        'gibbonChildEnrollmentEmergencyContact.phone',
        'gibbonChildEnrollmentEmergencyContact.notes',
    ];

    /**
     * Query emergency contact records with criteria support.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonChildEnrollmentFormID
     * @return DataSet
     */
    public function queryContactsByForm(QueryCriteria $criteria, $gibbonChildEnrollmentFormID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonChildEnrollmentEmergencyContact.gibbonChildEnrollmentEmergencyContactID',
                'gibbonChildEnrollmentEmergencyContact.gibbonChildEnrollmentFormID',
                'gibbonChildEnrollmentEmergencyContact.name',
                'gibbonChildEnrollmentEmergencyContact.relationship',
                'gibbonChildEnrollmentEmergencyContact.phone',
                'gibbonChildEnrollmentEmergencyContact.alternatePhone',
                'gibbonChildEnrollmentEmergencyContact.priority',
                'gibbonChildEnrollmentEmergencyContact.notes',
                'gibbonChildEnrollmentEmergencyContact.timestampCreated',
                'gibbonChildEnrollmentEmergencyContact.timestampModified',
            ])
            ->where('gibbonChildEnrollmentEmergencyContact.gibbonChildEnrollmentFormID=:gibbonChildEnrollmentFormID')
            ->bindValue('gibbonChildEnrollmentFormID', $gibbonChildEnrollmentFormID);

        $criteria->addFilterRules([
            'relationship' => function ($query, $relationship) {
                return $query
                    ->where('gibbonChildEnrollmentEmergencyContact.relationship=:relationship')
                    ->bindValue('relationship', $relationship);
            },
            'priority' => function ($query, $priority) {
                return $query
                    ->where('gibbonChildEnrollmentEmergencyContact.priority=:priority')
                    ->bindValue('priority', $priority);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Select all emergency contacts for an enrollment form.
     *
     * @param int $gibbonChildEnrollmentFormID
     * @return \Gibbon\Database\Result
     */
    public function selectContactsByForm($gibbonChildEnrollmentFormID)
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
     * Get a specific emergency contact by ID.
     *
     * @param int $gibbonChildEnrollmentEmergencyContactID
     * @return array|false
     */
    public function getContactByID($gibbonChildEnrollmentEmergencyContactID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols(['*'])
            ->where('gibbonChildEnrollmentEmergencyContactID=:gibbonChildEnrollmentEmergencyContactID')
            ->bindValue('gibbonChildEnrollmentEmergencyContactID', $gibbonChildEnrollmentEmergencyContactID);

        $result = $this->runSelect($query);
        return $result->isNotEmpty() ? $result->fetch() : false;
    }

    /**
     * Get the highest priority emergency contact for a form.
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
            ->orderBy(['priority ASC'])
            ->limit(1);

        $result = $this->runSelect($query);
        return $result->isNotEmpty() ? $result->fetch() : false;
    }

    /**
     * Add a new emergency contact.
     *
     * @param int $gibbonChildEnrollmentFormID
     * @param array $data
     * @return int|false Returns the contact ID on success
     */
    public function addContact($gibbonChildEnrollmentFormID, array $data)
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
     * Update an emergency contact.
     *
     * @param int $gibbonChildEnrollmentEmergencyContactID
     * @param array $data
     * @return bool
     */
    public function updateContact($gibbonChildEnrollmentEmergencyContactID, array $data)
    {
        return $this->update($gibbonChildEnrollmentEmergencyContactID, $data);
    }

    /**
     * Delete an emergency contact.
     *
     * @param int $gibbonChildEnrollmentEmergencyContactID
     * @return bool
     */
    public function deleteContact($gibbonChildEnrollmentEmergencyContactID)
    {
        return $this->delete($gibbonChildEnrollmentEmergencyContactID);
    }

    /**
     * Delete all emergency contacts for an enrollment form.
     *
     * @param int $gibbonChildEnrollmentFormID
     * @return int Number of rows deleted
     */
    public function deleteContactsByForm($gibbonChildEnrollmentFormID)
    {
        $data = ['gibbonChildEnrollmentFormID' => $gibbonChildEnrollmentFormID];
        $sql = "DELETE FROM gibbonChildEnrollmentEmergencyContact
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
        $sql = "SELECT MAX(priority) as maxPriority FROM gibbonChildEnrollmentEmergencyContact
                WHERE gibbonChildEnrollmentFormID=:gibbonChildEnrollmentFormID";

        $result = $this->db()->selectOne($sql, $data);
        return $result ? (int) $result['maxPriority'] + 1 : 1;
    }

    /**
     * Count emergency contacts for an enrollment form.
     *
     * @param int $gibbonChildEnrollmentFormID
     * @return int
     */
    public function countContactsByForm($gibbonChildEnrollmentFormID)
    {
        $data = ['gibbonChildEnrollmentFormID' => $gibbonChildEnrollmentFormID];
        $sql = "SELECT COUNT(*) as count FROM gibbonChildEnrollmentEmergencyContact
                WHERE gibbonChildEnrollmentFormID=:gibbonChildEnrollmentFormID";

        $result = $this->db()->selectOne($sql, $data);
        return $result ? (int) $result['count'] : 0;
    }

    /**
     * Reorder priorities for emergency contacts on a form.
     *
     * @param int $gibbonChildEnrollmentFormID
     * @param array $contactIDs Array of contact IDs in desired order
     * @return bool
     */
    public function reorderContacts($gibbonChildEnrollmentFormID, array $contactIDs)
    {
        $priority = 1;
        foreach ($contactIDs as $contactID) {
            $data = [
                'gibbonChildEnrollmentEmergencyContactID' => $contactID,
                'gibbonChildEnrollmentFormID' => $gibbonChildEnrollmentFormID,
                'priority' => $priority,
            ];
            $sql = "UPDATE gibbonChildEnrollmentEmergencyContact
                    SET priority=:priority
                    WHERE gibbonChildEnrollmentEmergencyContactID=:gibbonChildEnrollmentEmergencyContactID
                    AND gibbonChildEnrollmentFormID=:gibbonChildEnrollmentFormID";

            $this->db()->statement($sql, $data);
            $priority++;
        }

        return true;
    }

    /**
     * Search emergency contacts by name across all forms.
     *
     * @param QueryCriteria $criteria
     * @param string $searchTerm
     * @return DataSet
     */
    public function queryContactsBySearch(QueryCriteria $criteria, $searchTerm)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonChildEnrollmentEmergencyContact.gibbonChildEnrollmentEmergencyContactID',
                'gibbonChildEnrollmentEmergencyContact.gibbonChildEnrollmentFormID',
                'gibbonChildEnrollmentEmergencyContact.name',
                'gibbonChildEnrollmentEmergencyContact.relationship',
                'gibbonChildEnrollmentEmergencyContact.phone',
                'gibbonChildEnrollmentEmergencyContact.alternatePhone',
                'gibbonChildEnrollmentEmergencyContact.priority',
                'gibbonChildEnrollmentForm.formNumber',
                'gibbonChildEnrollmentForm.childFirstName',
                'gibbonChildEnrollmentForm.childLastName',
                'gibbonChildEnrollmentForm.status as formStatus',
            ])
            ->innerJoin('gibbonChildEnrollmentForm', 'gibbonChildEnrollmentEmergencyContact.gibbonChildEnrollmentFormID=gibbonChildEnrollmentForm.gibbonChildEnrollmentFormID')
            ->where('(gibbonChildEnrollmentEmergencyContact.name LIKE :searchTerm OR gibbonChildEnrollmentEmergencyContact.phone LIKE :searchTerm)')
            ->bindValue('searchTerm', '%' . $searchTerm . '%');

        return $this->runQuery($query, $criteria);
    }

    /**
     * Get emergency contact info for a form (for display purposes).
     *
     * @param int $gibbonChildEnrollmentFormID
     * @return array Array of emergency contact info
     */
    public function getContactInfo($gibbonChildEnrollmentFormID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'name',
                'relationship',
                'phone',
                'alternatePhone',
                'priority',
                'notes',
            ])
            ->where('gibbonChildEnrollmentFormID=:gibbonChildEnrollmentFormID')
            ->bindValue('gibbonChildEnrollmentFormID', $gibbonChildEnrollmentFormID)
            ->orderBy(['priority ASC']);

        return $this->runSelect($query)->fetchAll();
    }

    /**
     * Check if an emergency contact with the same name already exists for a form.
     *
     * @param int $gibbonChildEnrollmentFormID
     * @param string $name
     * @param int|null $excludeID Optionally exclude a specific contact ID from the check
     * @return bool
     */
    public function contactNameExists($gibbonChildEnrollmentFormID, $name, $excludeID = null)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols(['gibbonChildEnrollmentEmergencyContactID'])
            ->where('gibbonChildEnrollmentFormID=:gibbonChildEnrollmentFormID')
            ->bindValue('gibbonChildEnrollmentFormID', $gibbonChildEnrollmentFormID)
            ->where('name=:name')
            ->bindValue('name', $name);

        if ($excludeID !== null) {
            $query->where('gibbonChildEnrollmentEmergencyContactID!=:excludeID')
                  ->bindValue('excludeID', $excludeID);
        }

        $result = $this->runSelect($query);
        return $result->isNotEmpty();
    }

    /**
     * Validate emergency contact data before insert/update.
     *
     * @param array $data
     * @return array Array of validation errors (empty if valid)
     */
    public function validateContactData(array $data)
    {
        $errors = [];

        // Required fields
        if (empty($data['name'])) {
            $errors[] = 'Emergency contact name is required.';
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

    /**
     * Check if minimum number of emergency contacts requirement is met.
     *
     * @param int $gibbonChildEnrollmentFormID
     * @param int $minRequired Minimum number of contacts required
     * @return bool
     */
    public function meetsMinimumRequirement($gibbonChildEnrollmentFormID, $minRequired = 2)
    {
        $count = $this->countContactsByForm($gibbonChildEnrollmentFormID);
        return $count >= $minRequired;
    }

    /**
     * Get all phone numbers for emergency contacts of a form.
     *
     * @param int $gibbonChildEnrollmentFormID
     * @return array Array of phone numbers with names
     */
    public function getAllPhoneNumbers($gibbonChildEnrollmentFormID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'name',
                'relationship',
                'phone',
                'alternatePhone',
                'priority',
            ])
            ->where('gibbonChildEnrollmentFormID=:gibbonChildEnrollmentFormID')
            ->bindValue('gibbonChildEnrollmentFormID', $gibbonChildEnrollmentFormID)
            ->orderBy(['priority ASC']);

        $result = $this->runSelect($query)->fetchAll();

        $phoneNumbers = [];
        foreach ($result as $contact) {
            $phoneNumbers[] = [
                'name' => $contact['name'],
                'relationship' => $contact['relationship'],
                'phone' => $contact['phone'],
                'priority' => $contact['priority'],
            ];
            if (!empty($contact['alternatePhone'])) {
                $phoneNumbers[] = [
                    'name' => $contact['name'],
                    'relationship' => $contact['relationship'],
                    'phone' => $contact['alternatePhone'],
                    'priority' => $contact['priority'],
                    'isAlternate' => true,
                ];
            }
        }

        return $phoneNumbers;
    }
}
