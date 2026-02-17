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

namespace Gibbon\Module\ServiceAgreement\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\QueryableGateway;

/**
 * Service Agreement Gateway
 *
 * Handles CRUD operations for Quebec FO-0659 Service Agreements (Entente de Services).
 * Manages service agreements between childcare providers and parents including
 * all 13 articles, annexes, signatures, and payment terms.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class ServiceAgreementGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonServiceAgreement';
    private static $primaryKey = 'gibbonServiceAgreementID';

    private static $searchableColumns = [
        'gibbonServiceAgreement.agreementNumber',
        'gibbonServiceAgreement.childName',
        'gibbonServiceAgreement.parentName',
        'gibbonServiceAgreement.providerName',
        'child.preferredName',
        'child.surname',
        'parent.preferredName',
        'parent.surname',
    ];

    /**
     * Query service agreements with criteria support.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonSchoolYearID
     * @return DataSet
     */
    public function queryServiceAgreements(QueryCriteria $criteria, $gibbonSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonServiceAgreement.gibbonServiceAgreementID',
                'gibbonServiceAgreement.gibbonSchoolYearID',
                'gibbonServiceAgreement.gibbonPersonIDChild',
                'gibbonServiceAgreement.gibbonPersonIDParent',
                'gibbonServiceAgreement.agreementNumber',
                'gibbonServiceAgreement.status',
                'gibbonServiceAgreement.providerName',
                'gibbonServiceAgreement.childName',
                'gibbonServiceAgreement.childDateOfBirth',
                'gibbonServiceAgreement.parentName',
                'gibbonServiceAgreement.effectiveDate',
                'gibbonServiceAgreement.expirationDate',
                'gibbonServiceAgreement.contributionType',
                'gibbonServiceAgreement.dailyReducedContribution',
                'gibbonServiceAgreement.allSignaturesComplete',
                'gibbonServiceAgreement.agreementCompletedDate',
                'gibbonServiceAgreement.timestampCreated',
                'child.preferredName as childPreferredName',
                'child.surname as childSurname',
                'child.image_240 as childImage',
                'parent.preferredName as parentPreferredName',
                'parent.surname as parentSurname',
                'createdBy.preferredName as createdByName',
                'createdBy.surname as createdBySurname',
            ])
            ->innerJoin('gibbonPerson as child', 'gibbonServiceAgreement.gibbonPersonIDChild=child.gibbonPersonID')
            ->innerJoin('gibbonPerson as parent', 'gibbonServiceAgreement.gibbonPersonIDParent=parent.gibbonPersonID')
            ->leftJoin('gibbonPerson as createdBy', 'gibbonServiceAgreement.createdByID=createdBy.gibbonPersonID')
            ->where('gibbonServiceAgreement.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID);

        $criteria->addFilterRules([
            'status' => function ($query, $status) {
                return $query
                    ->where('gibbonServiceAgreement.status=:status')
                    ->bindValue('status', $status);
            },
            'child' => function ($query, $gibbonPersonIDChild) {
                return $query
                    ->where('gibbonServiceAgreement.gibbonPersonIDChild=:gibbonPersonIDChild')
                    ->bindValue('gibbonPersonIDChild', $gibbonPersonIDChild);
            },
            'parent' => function ($query, $gibbonPersonIDParent) {
                return $query
                    ->where('gibbonServiceAgreement.gibbonPersonIDParent=:gibbonPersonIDParent')
                    ->bindValue('gibbonPersonIDParent', $gibbonPersonIDParent);
            },
            'contributionType' => function ($query, $contributionType) {
                return $query
                    ->where('gibbonServiceAgreement.contributionType=:contributionType')
                    ->bindValue('contributionType', $contributionType);
            },
            'effectiveDate' => function ($query, $effectiveDate) {
                return $query
                    ->where('gibbonServiceAgreement.effectiveDate=:effectiveDate')
                    ->bindValue('effectiveDate', $effectiveDate);
            },
            'pendingSignature' => function ($query, $value) {
                if ($value === 'Y') {
                    return $query->where("gibbonServiceAgreement.status='Pending Signature'");
                }
                return $query;
            },
            'active' => function ($query, $value) {
                if ($value === 'Y') {
                    return $query->where("gibbonServiceAgreement.status='Active'");
                }
                return $query;
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query service agreements for a specific child.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonPersonIDChild
     * @param int $gibbonSchoolYearID
     * @return DataSet
     */
    public function queryServiceAgreementsByChild(QueryCriteria $criteria, $gibbonPersonIDChild, $gibbonSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonServiceAgreement.gibbonServiceAgreementID',
                'gibbonServiceAgreement.agreementNumber',
                'gibbonServiceAgreement.status',
                'gibbonServiceAgreement.providerName',
                'gibbonServiceAgreement.effectiveDate',
                'gibbonServiceAgreement.expirationDate',
                'gibbonServiceAgreement.contributionType',
                'gibbonServiceAgreement.dailyReducedContribution',
                'gibbonServiceAgreement.allSignaturesComplete',
                'gibbonServiceAgreement.agreementCompletedDate',
                'gibbonServiceAgreement.timestampCreated',
                'parent.preferredName as parentPreferredName',
                'parent.surname as parentSurname',
            ])
            ->innerJoin('gibbonPerson as parent', 'gibbonServiceAgreement.gibbonPersonIDParent=parent.gibbonPersonID')
            ->where('gibbonServiceAgreement.gibbonPersonIDChild=:gibbonPersonIDChild')
            ->bindValue('gibbonPersonIDChild', $gibbonPersonIDChild)
            ->where('gibbonServiceAgreement.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query service agreements for a specific parent.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonPersonIDParent
     * @param int|null $gibbonSchoolYearID
     * @return DataSet
     */
    public function queryServiceAgreementsByParent(QueryCriteria $criteria, $gibbonPersonIDParent, $gibbonSchoolYearID = null)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonServiceAgreement.gibbonServiceAgreementID',
                'gibbonServiceAgreement.gibbonSchoolYearID',
                'gibbonServiceAgreement.agreementNumber',
                'gibbonServiceAgreement.status',
                'gibbonServiceAgreement.providerName',
                'gibbonServiceAgreement.childName',
                'gibbonServiceAgreement.effectiveDate',
                'gibbonServiceAgreement.expirationDate',
                'gibbonServiceAgreement.contributionType',
                'gibbonServiceAgreement.dailyReducedContribution',
                'gibbonServiceAgreement.allSignaturesComplete',
                'gibbonServiceAgreement.agreementCompletedDate',
                'gibbonServiceAgreement.consumerProtectionAcknowledged',
                'gibbonServiceAgreement.timestampCreated',
                'child.preferredName as childPreferredName',
                'child.surname as childSurname',
                'child.image_240 as childImage',
                'gibbonSchoolYear.name as schoolYearName',
            ])
            ->innerJoin('gibbonPerson as child', 'gibbonServiceAgreement.gibbonPersonIDChild=child.gibbonPersonID')
            ->innerJoin('gibbonSchoolYear', 'gibbonServiceAgreement.gibbonSchoolYearID=gibbonSchoolYear.gibbonSchoolYearID')
            ->where('gibbonServiceAgreement.gibbonPersonIDParent=:gibbonPersonIDParent')
            ->bindValue('gibbonPersonIDParent', $gibbonPersonIDParent);

        if ($gibbonSchoolYearID !== null) {
            $query
                ->where('gibbonServiceAgreement.gibbonSchoolYearID=:gibbonSchoolYearID')
                ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID);
        }

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query pending agreements requiring signature.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonSchoolYearID
     * @return DataSet
     */
    public function queryPendingAgreements(QueryCriteria $criteria, $gibbonSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonServiceAgreement.gibbonServiceAgreementID',
                'gibbonServiceAgreement.agreementNumber',
                'gibbonServiceAgreement.childName',
                'gibbonServiceAgreement.parentName',
                'gibbonServiceAgreement.effectiveDate',
                'gibbonServiceAgreement.timestampCreated',
                'child.preferredName as childPreferredName',
                'child.surname as childSurname',
                'child.image_240 as childImage',
                'parent.preferredName as parentPreferredName',
                'parent.surname as parentSurname',
                'parent.email as parentEmail',
            ])
            ->innerJoin('gibbonPerson as child', 'gibbonServiceAgreement.gibbonPersonIDChild=child.gibbonPersonID')
            ->innerJoin('gibbonPerson as parent', 'gibbonServiceAgreement.gibbonPersonIDParent=parent.gibbonPersonID')
            ->where('gibbonServiceAgreement.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID)
            ->where("gibbonServiceAgreement.status='Pending Signature'")
            ->orderBy(['gibbonServiceAgreement.timestampCreated ASC']);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Get a service agreement with all details.
     *
     * @param int $gibbonServiceAgreementID
     * @return array|false
     */
    public function getAgreementWithDetails($gibbonServiceAgreementID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonServiceAgreement.*',
                'child.preferredName as childPreferredName',
                'child.surname as childSurname',
                'child.image_240 as childImage',
                'child.dob as childDob',
                'parent.preferredName as parentPreferredName',
                'parent.surname as parentSurname',
                'parent.email as parentEmail',
                'parent.phone1 as parentPhone',
                'createdBy.preferredName as createdByName',
                'createdBy.surname as createdBySurname',
                'gibbonSchoolYear.name as schoolYearName',
            ])
            ->innerJoin('gibbonPerson as child', 'gibbonServiceAgreement.gibbonPersonIDChild=child.gibbonPersonID')
            ->innerJoin('gibbonPerson as parent', 'gibbonServiceAgreement.gibbonPersonIDParent=parent.gibbonPersonID')
            ->leftJoin('gibbonPerson as createdBy', 'gibbonServiceAgreement.createdByID=createdBy.gibbonPersonID')
            ->innerJoin('gibbonSchoolYear', 'gibbonServiceAgreement.gibbonSchoolYearID=gibbonSchoolYear.gibbonSchoolYearID')
            ->where('gibbonServiceAgreement.gibbonServiceAgreementID=:gibbonServiceAgreementID')
            ->bindValue('gibbonServiceAgreementID', $gibbonServiceAgreementID);

        $result = $this->runSelect($query);
        return $result->isNotEmpty() ? $result->fetch() : false;
    }

    /**
     * Get the active service agreement for a child.
     *
     * @param int $gibbonPersonIDChild
     * @param int $gibbonSchoolYearID
     * @return array|false
     */
    public function getActiveAgreementByChild($gibbonPersonIDChild, $gibbonSchoolYearID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols(['*'])
            ->where('gibbonPersonIDChild=:gibbonPersonIDChild')
            ->bindValue('gibbonPersonIDChild', $gibbonPersonIDChild)
            ->where('gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID)
            ->where("status='Active'")
            ->orderBy(['effectiveDate DESC'])
            ->limit(1);

        $result = $this->runSelect($query);
        return $result->isNotEmpty() ? $result->fetch() : false;
    }

    /**
     * Get service agreement by agreement number.
     *
     * @param string $agreementNumber
     * @return array|false
     */
    public function getAgreementByNumber($agreementNumber)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols(['*'])
            ->where('agreementNumber=:agreementNumber')
            ->bindValue('agreementNumber', $agreementNumber);

        $result = $this->runSelect($query);
        return $result->isNotEmpty() ? $result->fetch() : false;
    }

    /**
     * Get service agreement summary statistics for a school year.
     *
     * @param int $gibbonSchoolYearID
     * @return array
     */
    public function getAgreementSummaryBySchoolYear($gibbonSchoolYearID)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID];
        $sql = "SELECT
                    status,
                    COUNT(*) as total,
                    SUM(CASE WHEN allSignaturesComplete='Y' THEN 1 ELSE 0 END) as signedCount,
                    SUM(CASE WHEN contributionType='Reduced' THEN 1 ELSE 0 END) as reducedCount,
                    SUM(CASE WHEN contributionType='Full' THEN 1 ELSE 0 END) as fullCount,
                    SUM(CASE WHEN contributionType='Mixed' THEN 1 ELSE 0 END) as mixedCount
                FROM gibbonServiceAgreement
                WHERE gibbonSchoolYearID=:gibbonSchoolYearID
                GROUP BY status
                ORDER BY FIELD(status, 'Draft', 'Pending Signature', 'Active', 'Expired', 'Terminated', 'Cancelled')";

        return $this->db()->select($sql, $data)->fetchAll();
    }

    /**
     * Get agreements expiring within a certain number of days.
     *
     * @param int $gibbonSchoolYearID
     * @param int $daysUntilExpiration
     * @return \Gibbon\Database\Result
     */
    public function selectExpiringAgreements($gibbonSchoolYearID, $daysUntilExpiration = 30)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID, 'days' => $daysUntilExpiration];
        $sql = "SELECT
                    gibbonServiceAgreement.*,
                    child.preferredName as childPreferredName,
                    child.surname as childSurname,
                    parent.preferredName as parentPreferredName,
                    parent.surname as parentSurname,
                    parent.email as parentEmail,
                    DATEDIFF(expirationDate, CURDATE()) as daysRemaining
                FROM gibbonServiceAgreement
                INNER JOIN gibbonPerson as child ON gibbonServiceAgreement.gibbonPersonIDChild=child.gibbonPersonID
                INNER JOIN gibbonPerson as parent ON gibbonServiceAgreement.gibbonPersonIDParent=parent.gibbonPersonID
                WHERE gibbonServiceAgreement.gibbonSchoolYearID=:gibbonSchoolYearID
                AND gibbonServiceAgreement.status='Active'
                AND gibbonServiceAgreement.expirationDate IS NOT NULL
                AND gibbonServiceAgreement.expirationDate BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :days DAY)
                ORDER BY gibbonServiceAgreement.expirationDate ASC";

        return $this->db()->select($sql, $data);
    }

    /**
     * Select children without an active service agreement.
     *
     * @param int $gibbonSchoolYearID
     * @return \Gibbon\Database\Result
     */
    public function selectChildrenWithoutAgreement($gibbonSchoolYearID)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID];
        $sql = "SELECT
                    gibbonPerson.gibbonPersonID,
                    gibbonPerson.preferredName,
                    gibbonPerson.surname,
                    gibbonPerson.image_240,
                    gibbonPerson.dob,
                    gibbonFormGroup.name as formGroupName
                FROM gibbonStudentEnrolment
                INNER JOIN gibbonPerson ON gibbonStudentEnrolment.gibbonPersonID=gibbonPerson.gibbonPersonID
                LEFT JOIN gibbonFormGroup ON gibbonStudentEnrolment.gibbonFormGroupID=gibbonFormGroup.gibbonFormGroupID
                WHERE gibbonStudentEnrolment.gibbonSchoolYearID=:gibbonSchoolYearID
                AND gibbonPerson.status='Full'
                AND NOT EXISTS (
                    SELECT 1 FROM gibbonServiceAgreement
                    WHERE gibbonServiceAgreement.gibbonPersonIDChild=gibbonPerson.gibbonPersonID
                    AND gibbonServiceAgreement.gibbonSchoolYearID=:gibbonSchoolYearID
                    AND gibbonServiceAgreement.status IN ('Active', 'Pending Signature')
                )
                ORDER BY gibbonPerson.surname, gibbonPerson.preferredName";

        return $this->db()->select($sql, $data);
    }

    /**
     * Update agreement status.
     *
     * @param int $gibbonServiceAgreementID
     * @param string $status
     * @return bool
     */
    public function updateStatus($gibbonServiceAgreementID, $status)
    {
        $data = [
            'status' => $status,
        ];

        // If status is Active, also set the completion date
        if ($status === 'Active') {
            $data['agreementCompletedDate'] = date('Y-m-d H:i:s');
            $data['allSignaturesComplete'] = 'Y';
        }

        return $this->update($gibbonServiceAgreementID, $data);
    }

    /**
     * Mark agreement as having all signatures complete.
     *
     * @param int $gibbonServiceAgreementID
     * @return bool
     */
    public function markSignaturesComplete($gibbonServiceAgreementID)
    {
        return $this->update($gibbonServiceAgreementID, [
            'allSignaturesComplete' => 'Y',
            'agreementCompletedDate' => date('Y-m-d H:i:s'),
            'status' => 'Active',
        ]);
    }

    /**
     * Mark Consumer Protection Act as acknowledged.
     *
     * @param int $gibbonServiceAgreementID
     * @return bool
     */
    public function markConsumerProtectionAcknowledged($gibbonServiceAgreementID)
    {
        return $this->update($gibbonServiceAgreementID, [
            'consumerProtectionAcknowledged' => 'Y',
            'consumerProtectionAcknowledgedDate' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Generate a unique agreement number.
     *
     * @param int $gibbonSchoolYearID
     * @return string
     */
    public function generateAgreementNumber($gibbonSchoolYearID)
    {
        // Get current year
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID];
        $sql = "SELECT COUNT(*) + 1 as nextNumber FROM gibbonServiceAgreement WHERE gibbonSchoolYearID=:gibbonSchoolYearID";
        $result = $this->db()->selectOne($sql, $data);

        $nextNumber = $result['nextNumber'] ?? 1;
        $year = date('Y');

        return sprintf('SA-%s-%04d', $year, $nextNumber);
    }

    /**
     * Get payment statistics for an agreement.
     *
     * @param int $gibbonServiceAgreementID
     * @return array
     */
    public function getPaymentStatsByAgreement($gibbonServiceAgreementID)
    {
        $data = ['gibbonServiceAgreementID' => $gibbonServiceAgreementID];
        $sql = "SELECT
                    COUNT(*) as totalPayments,
                    SUM(CASE WHEN status='Paid' THEN 1 ELSE 0 END) as paidCount,
                    SUM(CASE WHEN status='Overdue' THEN 1 ELSE 0 END) as overdueCount,
                    SUM(CASE WHEN status='Scheduled' THEN 1 ELSE 0 END) as scheduledCount,
                    SUM(totalAmount) as totalAmount,
                    SUM(CASE WHEN status='Paid' THEN paidAmount ELSE 0 END) as paidAmount,
                    SUM(CASE WHEN status='Overdue' THEN totalAmount ELSE 0 END) as overdueAmount
                FROM gibbonServiceAgreementPayment
                WHERE gibbonServiceAgreementID=:gibbonServiceAgreementID";

        return $this->db()->selectOne($sql, $data) ?: [
            'totalPayments' => 0,
            'paidCount' => 0,
            'overdueCount' => 0,
            'scheduledCount' => 0,
            'totalAmount' => 0,
            'paidAmount' => 0,
            'overdueAmount' => 0,
        ];
    }

    /**
     * Count agreements by status for dashboard.
     *
     * @param int $gibbonSchoolYearID
     * @return array
     */
    public function countAgreementsByStatus($gibbonSchoolYearID)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID];
        $sql = "SELECT
                    SUM(CASE WHEN status='Draft' THEN 1 ELSE 0 END) as draftCount,
                    SUM(CASE WHEN status='Pending Signature' THEN 1 ELSE 0 END) as pendingCount,
                    SUM(CASE WHEN status='Active' THEN 1 ELSE 0 END) as activeCount,
                    SUM(CASE WHEN status='Expired' THEN 1 ELSE 0 END) as expiredCount,
                    SUM(CASE WHEN status='Terminated' THEN 1 ELSE 0 END) as terminatedCount,
                    SUM(CASE WHEN status='Cancelled' THEN 1 ELSE 0 END) as cancelledCount,
                    COUNT(*) as totalCount
                FROM gibbonServiceAgreement
                WHERE gibbonSchoolYearID=:gibbonSchoolYearID";

        return $this->db()->selectOne($sql, $data) ?: [
            'draftCount' => 0,
            'pendingCount' => 0,
            'activeCount' => 0,
            'expiredCount' => 0,
            'terminatedCount' => 0,
            'cancelledCount' => 0,
            'totalCount' => 0,
        ];
    }

    /**
     * Check if a child already has a pending or active agreement for the school year.
     *
     * @param int $gibbonPersonIDChild
     * @param int $gibbonSchoolYearID
     * @param int|null $excludeAgreementID
     * @return bool
     */
    public function hasExistingAgreement($gibbonPersonIDChild, $gibbonSchoolYearID, $excludeAgreementID = null)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols(['gibbonServiceAgreementID'])
            ->where('gibbonPersonIDChild=:gibbonPersonIDChild')
            ->bindValue('gibbonPersonIDChild', $gibbonPersonIDChild)
            ->where('gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID)
            ->where("status IN ('Active', 'Pending Signature')");

        if ($excludeAgreementID !== null) {
            $query
                ->where('gibbonServiceAgreementID <> :excludeAgreementID')
                ->bindValue('excludeAgreementID', $excludeAgreementID);
        }

        $result = $this->runSelect($query);
        return $result->isNotEmpty();
    }
}
