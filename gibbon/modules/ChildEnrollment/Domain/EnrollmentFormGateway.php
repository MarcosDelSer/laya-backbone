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
 * Child Enrollment Form Gateway
 *
 * Handles CRUD operations for Quebec-compliant child enrollment forms (Fiche d'Inscription).
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class EnrollmentFormGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonChildEnrollmentForm';
    private static $primaryKey = 'gibbonChildEnrollmentFormID';

    private static $searchableColumns = [
        'gibbonChildEnrollmentForm.childFirstName',
        'gibbonChildEnrollmentForm.childLastName',
        'gibbonChildEnrollmentForm.formNumber',
        'gibbonPerson.preferredName',
        'gibbonPerson.surname',
    ];

    /**
     * Query enrollment forms with criteria support.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonSchoolYearID
     * @return DataSet
     */
    public function queryForms(QueryCriteria $criteria, $gibbonSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonChildEnrollmentForm.gibbonChildEnrollmentFormID',
                'gibbonChildEnrollmentForm.gibbonPersonID',
                'gibbonChildEnrollmentForm.gibbonFamilyID',
                'gibbonChildEnrollmentForm.formNumber',
                'gibbonChildEnrollmentForm.status',
                'gibbonChildEnrollmentForm.version',
                'gibbonChildEnrollmentForm.admissionDate',
                'gibbonChildEnrollmentForm.childFirstName',
                'gibbonChildEnrollmentForm.childLastName',
                'gibbonChildEnrollmentForm.childDateOfBirth',
                'gibbonChildEnrollmentForm.timestampCreated',
                'gibbonChildEnrollmentForm.timestampModified',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
                'createdBy.preferredName as createdByName',
                'createdBy.surname as createdBySurname',
            ])
            ->leftJoin('gibbonPerson', 'gibbonChildEnrollmentForm.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->leftJoin('gibbonPerson as createdBy', 'gibbonChildEnrollmentForm.createdByID=createdBy.gibbonPersonID')
            ->where('gibbonChildEnrollmentForm.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID);

        $criteria->addFilterRules([
            'status' => function ($query, $status) {
                return $query
                    ->where('gibbonChildEnrollmentForm.status=:status')
                    ->bindValue('status', $status);
            },
            'child' => function ($query, $gibbonPersonID) {
                return $query
                    ->where('gibbonChildEnrollmentForm.gibbonPersonID=:gibbonPersonID')
                    ->bindValue('gibbonPersonID', $gibbonPersonID);
            },
            'family' => function ($query, $gibbonFamilyID) {
                return $query
                    ->where('gibbonChildEnrollmentForm.gibbonFamilyID=:gibbonFamilyID')
                    ->bindValue('gibbonFamilyID', $gibbonFamilyID);
            },
            'admissionDateFrom' => function ($query, $date) {
                return $query
                    ->where('gibbonChildEnrollmentForm.admissionDate>=:admissionDateFrom')
                    ->bindValue('admissionDateFrom', $date);
            },
            'admissionDateTo' => function ($query, $date) {
                return $query
                    ->where('gibbonChildEnrollmentForm.admissionDate<=:admissionDateTo')
                    ->bindValue('admissionDateTo', $date);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query enrollment forms for a specific child.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonPersonID
     * @param int|null $gibbonSchoolYearID
     * @return DataSet
     */
    public function queryFormsByChild(QueryCriteria $criteria, $gibbonPersonID, $gibbonSchoolYearID = null)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonChildEnrollmentForm.gibbonChildEnrollmentFormID',
                'gibbonChildEnrollmentForm.formNumber',
                'gibbonChildEnrollmentForm.status',
                'gibbonChildEnrollmentForm.version',
                'gibbonChildEnrollmentForm.admissionDate',
                'gibbonChildEnrollmentForm.childFirstName',
                'gibbonChildEnrollmentForm.childLastName',
                'gibbonChildEnrollmentForm.childDateOfBirth',
                'gibbonChildEnrollmentForm.submittedAt',
                'gibbonChildEnrollmentForm.approvedAt',
                'gibbonChildEnrollmentForm.timestampCreated',
                'gibbonChildEnrollmentForm.timestampModified',
                'gibbonSchoolYear.name as schoolYearName',
            ])
            ->leftJoin('gibbonSchoolYear', 'gibbonChildEnrollmentForm.gibbonSchoolYearID=gibbonSchoolYear.gibbonSchoolYearID')
            ->where('gibbonChildEnrollmentForm.gibbonPersonID=:gibbonPersonID')
            ->bindValue('gibbonPersonID', $gibbonPersonID);

        if ($gibbonSchoolYearID !== null) {
            $query
                ->where('gibbonChildEnrollmentForm.gibbonSchoolYearID=:gibbonSchoolYearID')
                ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID);
        }

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query enrollment forms for a specific family.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonFamilyID
     * @param int|null $gibbonSchoolYearID
     * @return DataSet
     */
    public function queryFormsByFamily(QueryCriteria $criteria, $gibbonFamilyID, $gibbonSchoolYearID = null)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonChildEnrollmentForm.gibbonChildEnrollmentFormID',
                'gibbonChildEnrollmentForm.gibbonPersonID',
                'gibbonChildEnrollmentForm.formNumber',
                'gibbonChildEnrollmentForm.status',
                'gibbonChildEnrollmentForm.version',
                'gibbonChildEnrollmentForm.admissionDate',
                'gibbonChildEnrollmentForm.childFirstName',
                'gibbonChildEnrollmentForm.childLastName',
                'gibbonChildEnrollmentForm.childDateOfBirth',
                'gibbonChildEnrollmentForm.submittedAt',
                'gibbonChildEnrollmentForm.approvedAt',
                'gibbonChildEnrollmentForm.timestampCreated',
                'gibbonChildEnrollmentForm.timestampModified',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
                'gibbonSchoolYear.name as schoolYearName',
            ])
            ->leftJoin('gibbonPerson', 'gibbonChildEnrollmentForm.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->leftJoin('gibbonSchoolYear', 'gibbonChildEnrollmentForm.gibbonSchoolYearID=gibbonSchoolYear.gibbonSchoolYearID')
            ->where('gibbonChildEnrollmentForm.gibbonFamilyID=:gibbonFamilyID')
            ->bindValue('gibbonFamilyID', $gibbonFamilyID);

        if ($gibbonSchoolYearID !== null) {
            $query
                ->where('gibbonChildEnrollmentForm.gibbonSchoolYearID=:gibbonSchoolYearID')
                ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID);
        }

        return $this->runQuery($query, $criteria);
    }

    /**
     * Select a form by its ID with basic info.
     *
     * @param int $gibbonChildEnrollmentFormID
     * @return \Gibbon\Database\Result
     */
    public function selectFormByID($gibbonChildEnrollmentFormID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonChildEnrollmentForm.*',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
                'gibbonFamily.name as familyName',
                'gibbonSchoolYear.name as schoolYearName',
                'createdBy.preferredName as createdByName',
                'createdBy.surname as createdBySurname',
                'approvedBy.preferredName as approvedByName',
                'approvedBy.surname as approvedBySurname',
                'rejectedBy.preferredName as rejectedByName',
                'rejectedBy.surname as rejectedBySurname',
            ])
            ->leftJoin('gibbonPerson', 'gibbonChildEnrollmentForm.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->leftJoin('gibbonFamily', 'gibbonChildEnrollmentForm.gibbonFamilyID=gibbonFamily.gibbonFamilyID')
            ->leftJoin('gibbonSchoolYear', 'gibbonChildEnrollmentForm.gibbonSchoolYearID=gibbonSchoolYear.gibbonSchoolYearID')
            ->leftJoin('gibbonPerson as createdBy', 'gibbonChildEnrollmentForm.createdByID=createdBy.gibbonPersonID')
            ->leftJoin('gibbonPerson as approvedBy', 'gibbonChildEnrollmentForm.approvedByID=approvedBy.gibbonPersonID')
            ->leftJoin('gibbonPerson as rejectedBy', 'gibbonChildEnrollmentForm.rejectedByID=rejectedBy.gibbonPersonID')
            ->where('gibbonChildEnrollmentForm.gibbonChildEnrollmentFormID=:gibbonChildEnrollmentFormID')
            ->bindValue('gibbonChildEnrollmentFormID', $gibbonChildEnrollmentFormID);

        return $this->runSelect($query);
    }

    /**
     * Get a form with all related data (parents, pickups, emergency contacts, health, nutrition, attendance, signatures).
     *
     * @param int $gibbonChildEnrollmentFormID
     * @return array|false
     */
    public function getFormWithRelations($gibbonChildEnrollmentFormID)
    {
        // Get the main form data
        $result = $this->selectFormByID($gibbonChildEnrollmentFormID);
        if ($result->isEmpty()) {
            return false;
        }

        $form = $result->fetch();

        // Get parents
        $data = ['gibbonChildEnrollmentFormID' => $gibbonChildEnrollmentFormID];
        $sql = "SELECT * FROM gibbonChildEnrollmentParent
                WHERE gibbonChildEnrollmentFormID=:gibbonChildEnrollmentFormID
                ORDER BY parentNumber ASC";
        $form['parents'] = $this->db()->select($sql, $data)->fetchAll();

        // Get authorized pickups
        $sql = "SELECT * FROM gibbonChildEnrollmentAuthorizedPickup
                WHERE gibbonChildEnrollmentFormID=:gibbonChildEnrollmentFormID
                ORDER BY priority ASC";
        $form['authorizedPickups'] = $this->db()->select($sql, $data)->fetchAll();

        // Get emergency contacts
        $sql = "SELECT * FROM gibbonChildEnrollmentEmergencyContact
                WHERE gibbonChildEnrollmentFormID=:gibbonChildEnrollmentFormID
                ORDER BY priority ASC";
        $form['emergencyContacts'] = $this->db()->select($sql, $data)->fetchAll();

        // Get health info
        $sql = "SELECT * FROM gibbonChildEnrollmentHealth
                WHERE gibbonChildEnrollmentFormID=:gibbonChildEnrollmentFormID";
        $form['health'] = $this->db()->selectOne($sql, $data) ?: null;

        // Get nutrition info
        $sql = "SELECT * FROM gibbonChildEnrollmentNutrition
                WHERE gibbonChildEnrollmentFormID=:gibbonChildEnrollmentFormID";
        $form['nutrition'] = $this->db()->selectOne($sql, $data) ?: null;

        // Get attendance pattern
        $sql = "SELECT * FROM gibbonChildEnrollmentAttendance
                WHERE gibbonChildEnrollmentFormID=:gibbonChildEnrollmentFormID";
        $form['attendance'] = $this->db()->selectOne($sql, $data) ?: null;

        // Get signatures
        $sql = "SELECT * FROM gibbonChildEnrollmentSignature
                WHERE gibbonChildEnrollmentFormID=:gibbonChildEnrollmentFormID
                ORDER BY signatureType ASC";
        $form['signatures'] = $this->db()->select($sql, $data)->fetchAll();

        return $form;
    }

    /**
     * Get forms by status.
     *
     * @param int $gibbonSchoolYearID
     * @param string $status
     * @return \Gibbon\Database\Result
     */
    public function selectFormsByStatus($gibbonSchoolYearID, $status)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonChildEnrollmentForm.*',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
            ])
            ->leftJoin('gibbonPerson', 'gibbonChildEnrollmentForm.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->where('gibbonChildEnrollmentForm.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID)
            ->where('gibbonChildEnrollmentForm.status=:status')
            ->bindValue('status', $status)
            ->orderBy(['gibbonChildEnrollmentForm.timestampCreated DESC']);

        return $this->runSelect($query);
    }

    /**
     * Get the latest form for a child.
     *
     * @param int $gibbonPersonID
     * @return array|false
     */
    public function getLatestFormByChild($gibbonPersonID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols(['*'])
            ->where('gibbonPersonID=:gibbonPersonID')
            ->bindValue('gibbonPersonID', $gibbonPersonID)
            ->orderBy(['timestampCreated DESC'])
            ->limit(1);

        $result = $this->runSelect($query);
        return $result->isNotEmpty() ? $result->fetch() : false;
    }

    /**
     * Check if a form number already exists.
     *
     * @param string $formNumber
     * @param int|null $excludeFormID
     * @return bool
     */
    public function formNumberExists($formNumber, $excludeFormID = null)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols(['gibbonChildEnrollmentFormID'])
            ->where('formNumber=:formNumber')
            ->bindValue('formNumber', $formNumber);

        if ($excludeFormID !== null) {
            $query
                ->where('gibbonChildEnrollmentFormID!=:excludeFormID')
                ->bindValue('excludeFormID', $excludeFormID);
        }

        $result = $this->runSelect($query);
        return $result->isNotEmpty();
    }

    /**
     * Generate the next form number.
     *
     * @param string $prefix
     * @return string
     */
    public function generateFormNumber($prefix = 'ENR-')
    {
        $data = ['prefix' => $prefix . '%'];
        $sql = "SELECT formNumber FROM gibbonChildEnrollmentForm
                WHERE formNumber LIKE :prefix
                ORDER BY formNumber DESC
                LIMIT 1";

        $result = $this->db()->selectOne($sql, $data);

        if ($result && isset($result['formNumber'])) {
            // Extract the numeric part and increment
            $number = (int) preg_replace('/[^0-9]/', '', substr($result['formNumber'], strlen($prefix)));
            $nextNumber = $number + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Create a new enrollment form.
     *
     * @param array $data
     * @return int|false
     */
    public function createForm(array $data)
    {
        // Generate form number if not provided
        if (empty($data['formNumber'])) {
            $data['formNumber'] = $this->generateFormNumber();
        }

        // Ensure required defaults
        $data['status'] = $data['status'] ?? 'Draft';
        $data['version'] = $data['version'] ?? 1;

        return $this->insert($data);
    }

    /**
     * Update an existing enrollment form.
     *
     * @param int $gibbonChildEnrollmentFormID
     * @param array $data
     * @return bool
     */
    public function updateForm($gibbonChildEnrollmentFormID, array $data)
    {
        // Increment version on update
        if (!isset($data['version'])) {
            $currentForm = $this->getByID($gibbonChildEnrollmentFormID);
            if ($currentForm) {
                $data['version'] = ((int) $currentForm['version']) + 1;
            }
        }

        return $this->update($gibbonChildEnrollmentFormID, $data);
    }

    /**
     * Update form status with audit trail.
     *
     * @param int $gibbonChildEnrollmentFormID
     * @param string $status
     * @param int $performedByID
     * @param string|null $reason
     * @return bool
     */
    public function updateFormStatus($gibbonChildEnrollmentFormID, $status, $performedByID, $reason = null)
    {
        $updateData = ['status' => $status];

        switch ($status) {
            case 'Submitted':
                $updateData['submittedAt'] = date('Y-m-d H:i:s');
                break;

            case 'Approved':
                $updateData['approvedAt'] = date('Y-m-d H:i:s');
                $updateData['approvedByID'] = $performedByID;
                break;

            case 'Rejected':
                $updateData['rejectedAt'] = date('Y-m-d H:i:s');
                $updateData['rejectedByID'] = $performedByID;
                $updateData['rejectionReason'] = $reason;
                break;
        }

        return $this->update($gibbonChildEnrollmentFormID, $updateData);
    }

    /**
     * Get form statistics by status for a school year.
     *
     * @param int $gibbonSchoolYearID
     * @return array
     */
    public function getFormStatsBySchoolYear($gibbonSchoolYearID)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID];
        $sql = "SELECT
                    status,
                    COUNT(*) as count
                FROM gibbonChildEnrollmentForm
                WHERE gibbonSchoolYearID=:gibbonSchoolYearID
                GROUP BY status
                ORDER BY FIELD(status, 'Draft', 'Submitted', 'Approved', 'Rejected', 'Expired')";

        $results = $this->db()->select($sql, $data)->fetchAll();

        // Convert to associative array
        $stats = [
            'Draft' => 0,
            'Submitted' => 0,
            'Approved' => 0,
            'Rejected' => 0,
            'Expired' => 0,
            'Total' => 0,
        ];

        foreach ($results as $row) {
            $stats[$row['status']] = (int) $row['count'];
            $stats['Total'] += (int) $row['count'];
        }

        return $stats;
    }

    /**
     * Get expired draft forms that need cleanup.
     *
     * @param int $expiryDays
     * @return \Gibbon\Database\Result
     */
    public function selectExpiredDraftForms($expiryDays = 30)
    {
        $data = ['expiryDays' => $expiryDays];
        $sql = "SELECT * FROM gibbonChildEnrollmentForm
                WHERE status='Draft'
                AND timestampCreated < DATE_SUB(NOW(), INTERVAL :expiryDays DAY)";

        return $this->db()->select($sql, $data);
    }

    /**
     * Mark expired draft forms.
     *
     * @param int $expiryDays
     * @return int Number of forms marked as expired
     */
    public function markExpiredDraftForms($expiryDays = 30)
    {
        $data = ['expiryDays' => $expiryDays];
        $sql = "UPDATE gibbonChildEnrollmentForm
                SET status='Expired'
                WHERE status='Draft'
                AND timestampCreated < DATE_SUB(NOW(), INTERVAL :expiryDays DAY)";

        return $this->db()->statement($sql, $data);
    }
}
