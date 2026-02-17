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

namespace Gibbon\Module\RL24Submission\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\QueryableGateway;

/**
 * RL-24 Eligibility Gateway
 *
 * Handles FO-0601 eligibility forms for childcare expense tax credits.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class RL24EligibilityGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonRL24Eligibility';
    private static $primaryKey = 'gibbonRL24EligibilityID';

    private static $searchableColumns = ['gibbonRL24Eligibility.parentFirstName', 'gibbonRL24Eligibility.parentLastName', 'gibbonRL24Eligibility.childFirstName', 'gibbonRL24Eligibility.childLastName', 'gibbonRL24Eligibility.notes'];

    /**
     * Query eligibility forms with criteria support.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonSchoolYearID
     * @return DataSet
     */
    public function queryEligibility(QueryCriteria $criteria, $gibbonSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonRL24Eligibility.gibbonRL24EligibilityID',
                'gibbonRL24Eligibility.gibbonSchoolYearID',
                'gibbonRL24Eligibility.gibbonPersonIDChild',
                'gibbonRL24Eligibility.gibbonPersonIDParent',
                'gibbonRL24Eligibility.formYear',
                'gibbonRL24Eligibility.parentFirstName',
                'gibbonRL24Eligibility.parentLastName',
                'gibbonRL24Eligibility.childFirstName',
                'gibbonRL24Eligibility.childLastName',
                'gibbonRL24Eligibility.childDateOfBirth',
                'gibbonRL24Eligibility.servicePeriodStart',
                'gibbonRL24Eligibility.servicePeriodEnd',
                'gibbonRL24Eligibility.approvalStatus',
                'gibbonRL24Eligibility.approvalDate',
                'gibbonRL24Eligibility.documentsComplete',
                'gibbonRL24Eligibility.signatureConfirmed',
                'gibbonRL24Eligibility.timestampCreated',
                'gibbonRL24Eligibility.timestampModified',
                'child.preferredName as childPreferredName',
                'child.surname as childSurname',
                'child.image_240 as childImage',
                'parent.preferredName as parentPreferredName',
                'parent.surname as parentSurname',
                'approvedBy.preferredName as approvedByName',
                'approvedBy.surname as approvedBySurname',
            ])
            ->leftJoin('gibbonPerson as child', 'gibbonRL24Eligibility.gibbonPersonIDChild=child.gibbonPersonID')
            ->leftJoin('gibbonPerson as parent', 'gibbonRL24Eligibility.gibbonPersonIDParent=parent.gibbonPersonID')
            ->leftJoin('gibbonPerson as approvedBy', 'gibbonRL24Eligibility.approvedByID=approvedBy.gibbonPersonID')
            ->where('gibbonRL24Eligibility.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID);

        $criteria->addFilterRules([
            'approvalStatus' => function ($query, $approvalStatus) {
                return $query
                    ->where('gibbonRL24Eligibility.approvalStatus=:approvalStatus')
                    ->bindValue('approvalStatus', $approvalStatus);
            },
            'formYear' => function ($query, $formYear) {
                return $query
                    ->where('gibbonRL24Eligibility.formYear=:formYear')
                    ->bindValue('formYear', $formYear);
            },
            'child' => function ($query, $gibbonPersonIDChild) {
                return $query
                    ->where('gibbonRL24Eligibility.gibbonPersonIDChild=:gibbonPersonIDChild')
                    ->bindValue('gibbonPersonIDChild', $gibbonPersonIDChild);
            },
            'parent' => function ($query, $gibbonPersonIDParent) {
                return $query
                    ->where('gibbonRL24Eligibility.gibbonPersonIDParent=:gibbonPersonIDParent')
                    ->bindValue('gibbonPersonIDParent', $gibbonPersonIDParent);
            },
            'documentsComplete' => function ($query, $value) {
                return $query
                    ->where('gibbonRL24Eligibility.documentsComplete=:documentsComplete')
                    ->bindValue('documentsComplete', $value);
            },
            'signatureConfirmed' => function ($query, $value) {
                return $query
                    ->where('gibbonRL24Eligibility.signatureConfirmed=:signatureConfirmed')
                    ->bindValue('signatureConfirmed', $value);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query eligibility forms for a specific form year.
     *
     * @param QueryCriteria $criteria
     * @param int $formYear
     * @return DataSet
     */
    public function queryEligibilityByFormYear(QueryCriteria $criteria, $formYear)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonRL24Eligibility.gibbonRL24EligibilityID',
                'gibbonRL24Eligibility.gibbonSchoolYearID',
                'gibbonRL24Eligibility.gibbonPersonIDChild',
                'gibbonRL24Eligibility.gibbonPersonIDParent',
                'gibbonRL24Eligibility.formYear',
                'gibbonRL24Eligibility.parentFirstName',
                'gibbonRL24Eligibility.parentLastName',
                'gibbonRL24Eligibility.childFirstName',
                'gibbonRL24Eligibility.childLastName',
                'gibbonRL24Eligibility.servicePeriodStart',
                'gibbonRL24Eligibility.servicePeriodEnd',
                'gibbonRL24Eligibility.approvalStatus',
                'gibbonRL24Eligibility.documentsComplete',
                'gibbonRL24Eligibility.signatureConfirmed',
                'gibbonRL24Eligibility.timestampCreated',
                'child.preferredName as childPreferredName',
                'child.surname as childSurname',
                'child.image_240 as childImage',
            ])
            ->leftJoin('gibbonPerson as child', 'gibbonRL24Eligibility.gibbonPersonIDChild=child.gibbonPersonID')
            ->where('gibbonRL24Eligibility.formYear=:formYear')
            ->bindValue('formYear', $formYear);

        $criteria->addFilterRules([
            'approvalStatus' => function ($query, $approvalStatus) {
                return $query
                    ->where('gibbonRL24Eligibility.approvalStatus=:approvalStatus')
                    ->bindValue('approvalStatus', $approvalStatus);
            },
            'schoolYear' => function ($query, $gibbonSchoolYearID) {
                return $query
                    ->where('gibbonRL24Eligibility.gibbonSchoolYearID=:gibbonSchoolYearID')
                    ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query eligibility forms for a specific child.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonPersonIDChild
     * @return DataSet
     */
    public function queryEligibilityByChild(QueryCriteria $criteria, $gibbonPersonIDChild)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonRL24Eligibility.gibbonRL24EligibilityID',
                'gibbonRL24Eligibility.formYear',
                'gibbonRL24Eligibility.parentFirstName',
                'gibbonRL24Eligibility.parentLastName',
                'gibbonRL24Eligibility.servicePeriodStart',
                'gibbonRL24Eligibility.servicePeriodEnd',
                'gibbonRL24Eligibility.approvalStatus',
                'gibbonRL24Eligibility.approvalDate',
                'gibbonRL24Eligibility.documentsComplete',
                'gibbonRL24Eligibility.signatureConfirmed',
                'gibbonRL24Eligibility.timestampCreated',
                'parent.preferredName as parentPreferredName',
                'parent.surname as parentSurname',
            ])
            ->leftJoin('gibbonPerson as parent', 'gibbonRL24Eligibility.gibbonPersonIDParent=parent.gibbonPersonID')
            ->where('gibbonRL24Eligibility.gibbonPersonIDChild=:gibbonPersonIDChild')
            ->bindValue('gibbonPersonIDChild', $gibbonPersonIDChild);

        $criteria->addFilterRules([
            'formYear' => function ($query, $formYear) {
                return $query
                    ->where('gibbonRL24Eligibility.formYear=:formYear')
                    ->bindValue('formYear', $formYear);
            },
            'approvalStatus' => function ($query, $approvalStatus) {
                return $query
                    ->where('gibbonRL24Eligibility.approvalStatus=:approvalStatus')
                    ->bindValue('approvalStatus', $approvalStatus);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Select eligibility forms by approval status.
     *
     * @param int $gibbonSchoolYearID
     * @param string $approvalStatus
     * @return \Gibbon\Database\Result
     */
    public function selectEligibilityByStatus($gibbonSchoolYearID, $approvalStatus)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonRL24Eligibility.*',
            ])
            ->where('gibbonRL24Eligibility.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID)
            ->where('gibbonRL24Eligibility.approvalStatus=:approvalStatus')
            ->bindValue('approvalStatus', $approvalStatus)
            ->orderBy(['gibbonRL24Eligibility.childLastName ASC', 'gibbonRL24Eligibility.childFirstName ASC']);

        return $this->runSelect($query);
    }

    /**
     * Select approved eligibility forms for a tax year.
     *
     * @param int $formYear
     * @return \Gibbon\Database\Result
     */
    public function selectApprovedEligibilityByFormYear($formYear)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonRL24Eligibility.*',
            ])
            ->where('gibbonRL24Eligibility.formYear=:formYear')
            ->bindValue('formYear', $formYear)
            ->where("gibbonRL24Eligibility.approvalStatus='Approved'")
            ->orderBy(['gibbonRL24Eligibility.childLastName ASC', 'gibbonRL24Eligibility.childFirstName ASC']);

        return $this->runSelect($query);
    }

    /**
     * Get eligibility form by ID with full details.
     *
     * @param int $gibbonRL24EligibilityID
     * @return array
     */
    public function getEligibilityByID($gibbonRL24EligibilityID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonRL24Eligibility.*',
                'child.preferredName as childPreferredName',
                'child.surname as childSurname',
                'child.image_240 as childImage',
                'child.dob as childDOB',
                'parent.preferredName as parentPreferredName',
                'parent.surname as parentSurname',
                'parent.email as parentEmailFromProfile',
                'parent.phone1 as parentPhoneFromProfile',
                'approvedBy.preferredName as approvedByName',
                'approvedBy.surname as approvedBySurname',
                'createdBy.preferredName as createdByName',
                'createdBy.surname as createdBySurname',
            ])
            ->leftJoin('gibbonPerson as child', 'gibbonRL24Eligibility.gibbonPersonIDChild=child.gibbonPersonID')
            ->leftJoin('gibbonPerson as parent', 'gibbonRL24Eligibility.gibbonPersonIDParent=parent.gibbonPersonID')
            ->leftJoin('gibbonPerson as approvedBy', 'gibbonRL24Eligibility.approvedByID=approvedBy.gibbonPersonID')
            ->leftJoin('gibbonPerson as createdBy', 'gibbonRL24Eligibility.createdByID=createdBy.gibbonPersonID')
            ->where('gibbonRL24Eligibility.gibbonRL24EligibilityID=:gibbonRL24EligibilityID')
            ->bindValue('gibbonRL24EligibilityID', $gibbonRL24EligibilityID);

        $result = $this->runSelect($query);
        return $result->isNotEmpty() ? $result->fetch() : [];
    }

    /**
     * Get eligibility form for a specific child and form year.
     *
     * @param int $gibbonPersonIDChild
     * @param int $formYear
     * @return array
     */
    public function getEligibilityByChildAndYear($gibbonPersonIDChild, $formYear)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols(['*'])
            ->where('gibbonPersonIDChild=:gibbonPersonIDChild')
            ->bindValue('gibbonPersonIDChild', $gibbonPersonIDChild)
            ->where('formYear=:formYear')
            ->bindValue('formYear', $formYear);

        $result = $this->runSelect($query);
        return $result->isNotEmpty() ? $result->fetch() : [];
    }

    /**
     * Create a new eligibility form.
     *
     * @param int $gibbonSchoolYearID
     * @param int $gibbonPersonIDChild
     * @param int|null $gibbonPersonIDParent
     * @param int $createdByID
     * @param array $formData Form data including names, addresses, citizenship, etc.
     * @return int|false
     */
    public function createEligibility($gibbonSchoolYearID, $gibbonPersonIDChild, $gibbonPersonIDParent, $createdByID, $formData = [])
    {
        return $this->insert(array_merge([
            'gibbonSchoolYearID' => $gibbonSchoolYearID,
            'gibbonPersonIDChild' => $gibbonPersonIDChild,
            'gibbonPersonIDParent' => $gibbonPersonIDParent,
            'createdByID' => $createdByID,
            'approvalStatus' => 'Pending',
            'documentsComplete' => 'N',
            'signatureConfirmed' => 'N',
        ], $formData));
    }

    /**
     * Update eligibility approval status.
     *
     * @param int $gibbonRL24EligibilityID
     * @param string $approvalStatus
     * @param int|null $approvedByID
     * @param string|null $approvalNotes
     * @return bool
     */
    public function updateApprovalStatus($gibbonRL24EligibilityID, $approvalStatus, $approvedByID = null, $approvalNotes = null)
    {
        $data = [
            'approvalStatus' => $approvalStatus,
        ];

        if ($approvalStatus === 'Approved' || $approvalStatus === 'Rejected') {
            $data['approvalDate'] = date('Y-m-d');
            $data['approvedByID'] = $approvedByID;
        }

        if ($approvalNotes !== null) {
            $data['approvalNotes'] = $approvalNotes;
        }

        return $this->update($gibbonRL24EligibilityID, $data);
    }

    /**
     * Update documents complete status.
     *
     * @param int $gibbonRL24EligibilityID
     * @param bool $documentsComplete
     * @return bool
     */
    public function updateDocumentsComplete($gibbonRL24EligibilityID, $documentsComplete)
    {
        return $this->update($gibbonRL24EligibilityID, [
            'documentsComplete' => $documentsComplete ? 'Y' : 'N',
        ]);
    }

    /**
     * Update signature confirmation.
     *
     * @param int $gibbonRL24EligibilityID
     * @param bool $signatureConfirmed
     * @param string|null $signatureDate
     * @return bool
     */
    public function updateSignatureStatus($gibbonRL24EligibilityID, $signatureConfirmed, $signatureDate = null)
    {
        return $this->update($gibbonRL24EligibilityID, [
            'signatureConfirmed' => $signatureConfirmed ? 'Y' : 'N',
            'signatureDate' => $signatureDate ?: ($signatureConfirmed ? date('Y-m-d') : null),
        ]);
    }

    /**
     * Update parent information.
     *
     * @param int $gibbonRL24EligibilityID
     * @param array $parentData
     * @return bool
     */
    public function updateParentInfo($gibbonRL24EligibilityID, $parentData)
    {
        $allowedFields = [
            'parentFirstName', 'parentLastName', 'parentSIN',
            'parentAddressLine1', 'parentAddressLine2', 'parentCity',
            'parentProvince', 'parentPostalCode', 'parentPhone', 'parentEmail',
            'citizenshipStatus', 'citizenshipOther', 'residencyStatus',
        ];

        $filteredData = array_intersect_key($parentData, array_flip($allowedFields));
        return $this->update($gibbonRL24EligibilityID, $filteredData);
    }

    /**
     * Update child information.
     *
     * @param int $gibbonRL24EligibilityID
     * @param array $childData
     * @return bool
     */
    public function updateChildInfo($gibbonRL24EligibilityID, $childData)
    {
        $allowedFields = [
            'childFirstName', 'childLastName', 'childDateOfBirth', 'childRelationship',
        ];

        $filteredData = array_intersect_key($childData, array_flip($allowedFields));
        return $this->update($gibbonRL24EligibilityID, $filteredData);
    }

    /**
     * Update service period information.
     *
     * @param int $gibbonRL24EligibilityID
     * @param string $servicePeriodStart
     * @param string $servicePeriodEnd
     * @param string|null $divisionNumber
     * @return bool
     */
    public function updateServicePeriod($gibbonRL24EligibilityID, $servicePeriodStart, $servicePeriodEnd, $divisionNumber = null)
    {
        $data = [
            'servicePeriodStart' => $servicePeriodStart,
            'servicePeriodEnd' => $servicePeriodEnd,
        ];

        if ($divisionNumber !== null) {
            $data['divisionNumber'] = $divisionNumber;
        }

        return $this->update($gibbonRL24EligibilityID, $data);
    }

    /**
     * Get eligibility summary statistics for a school year.
     *
     * @param int $gibbonSchoolYearID
     * @return array
     */
    public function getEligibilitySummaryBySchoolYear($gibbonSchoolYearID)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID];
        $sql = "SELECT
                    COUNT(*) as totalForms,
                    SUM(CASE WHEN approvalStatus='Pending' THEN 1 ELSE 0 END) as pendingCount,
                    SUM(CASE WHEN approvalStatus='Approved' THEN 1 ELSE 0 END) as approvedCount,
                    SUM(CASE WHEN approvalStatus='Rejected' THEN 1 ELSE 0 END) as rejectedCount,
                    SUM(CASE WHEN approvalStatus='Incomplete' THEN 1 ELSE 0 END) as incompleteCount,
                    SUM(CASE WHEN documentsComplete='Y' THEN 1 ELSE 0 END) as documentsCompleteCount,
                    SUM(CASE WHEN signatureConfirmed='Y' THEN 1 ELSE 0 END) as signatureConfirmedCount
                FROM gibbonRL24Eligibility
                WHERE gibbonSchoolYearID=:gibbonSchoolYearID";

        return $this->db()->selectOne($sql, $data) ?: [
            'totalForms' => 0,
            'pendingCount' => 0,
            'approvedCount' => 0,
            'rejectedCount' => 0,
            'incompleteCount' => 0,
            'documentsCompleteCount' => 0,
            'signatureConfirmedCount' => 0,
        ];
    }

    /**
     * Get eligibility summary statistics for a form year.
     *
     * @param int $formYear
     * @return array
     */
    public function getEligibilitySummaryByFormYear($formYear)
    {
        $data = ['formYear' => $formYear];
        $sql = "SELECT
                    COUNT(*) as totalForms,
                    SUM(CASE WHEN approvalStatus='Pending' THEN 1 ELSE 0 END) as pendingCount,
                    SUM(CASE WHEN approvalStatus='Approved' THEN 1 ELSE 0 END) as approvedCount,
                    SUM(CASE WHEN approvalStatus='Rejected' THEN 1 ELSE 0 END) as rejectedCount,
                    SUM(CASE WHEN approvalStatus='Incomplete' THEN 1 ELSE 0 END) as incompleteCount,
                    SUM(CASE WHEN documentsComplete='Y' THEN 1 ELSE 0 END) as documentsCompleteCount,
                    SUM(CASE WHEN signatureConfirmed='Y' THEN 1 ELSE 0 END) as signatureConfirmedCount
                FROM gibbonRL24Eligibility
                WHERE formYear=:formYear";

        return $this->db()->selectOne($sql, $data) ?: [
            'totalForms' => 0,
            'pendingCount' => 0,
            'approvedCount' => 0,
            'rejectedCount' => 0,
            'incompleteCount' => 0,
            'documentsCompleteCount' => 0,
            'signatureConfirmedCount' => 0,
        ];
    }

    /**
     * Check if an eligibility form exists for a child in a specific form year.
     *
     * @param int $gibbonPersonIDChild
     * @param int $formYear
     * @param int|null $excludeEligibilityID Optional ID to exclude (for updates)
     * @return bool
     */
    public function eligibilityExistsForChildAndYear($gibbonPersonIDChild, $formYear, $excludeEligibilityID = null)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols(['gibbonRL24EligibilityID'])
            ->where('gibbonPersonIDChild=:gibbonPersonIDChild')
            ->bindValue('gibbonPersonIDChild', $gibbonPersonIDChild)
            ->where('formYear=:formYear')
            ->bindValue('formYear', $formYear);

        if ($excludeEligibilityID !== null) {
            $query
                ->where('gibbonRL24EligibilityID!=:excludeEligibilityID')
                ->bindValue('excludeEligibilityID', $excludeEligibilityID);
        }

        $result = $this->runSelect($query);
        return $result->isNotEmpty();
    }

    /**
     * Select children who need eligibility forms for a tax year.
     * Returns enrolled children without an existing form for the year.
     *
     * @param int $gibbonSchoolYearID
     * @param int $formYear
     * @return \Gibbon\Database\Result
     */
    public function selectChildrenNeedingEligibility($gibbonSchoolYearID, $formYear)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID, 'formYear' => $formYear];
        $sql = "SELECT gibbonPerson.gibbonPersonID, gibbonPerson.preferredName, gibbonPerson.surname, gibbonPerson.image_240, gibbonPerson.dob
                FROM gibbonStudentEnrolment
                INNER JOIN gibbonPerson ON gibbonStudentEnrolment.gibbonPersonID=gibbonPerson.gibbonPersonID
                WHERE gibbonStudentEnrolment.gibbonSchoolYearID=:gibbonSchoolYearID
                AND gibbonPerson.status='Full'
                AND NOT EXISTS (
                    SELECT 1 FROM gibbonRL24Eligibility
                    WHERE gibbonRL24Eligibility.gibbonPersonIDChild=gibbonPerson.gibbonPersonID
                    AND gibbonRL24Eligibility.formYear=:formYear
                )
                ORDER BY gibbonPerson.surname, gibbonPerson.preferredName";

        return $this->db()->select($sql, $data);
    }

    /**
     * Select children with approved eligibility ready for RL-24 generation.
     *
     * @param int $formYear
     * @return \Gibbon\Database\Result
     */
    public function selectChildrenWithApprovedEligibility($formYear)
    {
        $data = ['formYear' => $formYear];
        $sql = "SELECT
                    gibbonRL24Eligibility.*,
                    child.preferredName as childPreferredName,
                    child.surname as childSurname,
                    child.dob as childDOB,
                    parent.preferredName as parentPreferredName,
                    parent.surname as parentSurname
                FROM gibbonRL24Eligibility
                LEFT JOIN gibbonPerson as child ON gibbonRL24Eligibility.gibbonPersonIDChild=child.gibbonPersonID
                LEFT JOIN gibbonPerson as parent ON gibbonRL24Eligibility.gibbonPersonIDParent=parent.gibbonPersonID
                WHERE gibbonRL24Eligibility.formYear=:formYear
                AND gibbonRL24Eligibility.approvalStatus='Approved'
                ORDER BY gibbonRL24Eligibility.childLastName, gibbonRL24Eligibility.childFirstName";

        return $this->db()->select($sql, $data);
    }

    /**
     * Get count of eligibility forms by status for a school year.
     *
     * @param int $gibbonSchoolYearID
     * @return array
     */
    public function getStatusCounts($gibbonSchoolYearID)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID];
        $sql = "SELECT approvalStatus, COUNT(*) as count
                FROM gibbonRL24Eligibility
                WHERE gibbonSchoolYearID=:gibbonSchoolYearID
                GROUP BY approvalStatus";

        $results = $this->db()->select($sql, $data)->fetchAll();

        $counts = [
            'Pending' => 0,
            'Approved' => 0,
            'Rejected' => 0,
            'Incomplete' => 0,
        ];

        foreach ($results as $row) {
            $counts[$row['approvalStatus']] = (int) $row['count'];
        }

        return $counts;
    }

    /**
     * Delete an eligibility form (only if Pending or Incomplete).
     *
     * @param int $gibbonRL24EligibilityID
     * @return bool
     */
    public function deleteEligibility($gibbonRL24EligibilityID)
    {
        // Only delete if it's pending or incomplete
        $eligibility = $this->getByID($gibbonRL24EligibilityID);
        if (empty($eligibility) || !in_array($eligibility['approvalStatus'], ['Pending', 'Incomplete'])) {
            return false;
        }

        return $this->delete($gibbonRL24EligibilityID);
    }
}
