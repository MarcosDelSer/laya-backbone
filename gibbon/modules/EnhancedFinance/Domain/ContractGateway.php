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

namespace Gibbon\Module\EnhancedFinance\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\QueryableGateway;

/**
 * Enhanced Finance Contract Gateway
 *
 * Provides data access methods for the gibbonEnhancedFinanceContract table.
 * Handles contract CRUD operations, queries with pagination, and filtering.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class ContractGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonEnhancedFinanceContract';
    private static $primaryKey = 'gibbonEnhancedFinanceContractID';

    private static $searchableColumns = ['contractNumber', 'terms'];

    /**
     * Query contracts by school year with pagination and filtering.
     *
     * Contracts are filtered by date overlap with the school year:
     * contract start date <= school year last day AND
     * (contract end date is NULL OR contract end date >= school year first day)
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonSchoolYearID
     * @return DataSet
     */
    public function queryContractsByYear(QueryCriteria $criteria, $gibbonSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonEnhancedFinanceContract.gibbonEnhancedFinanceContractID',
                'gibbonEnhancedFinanceContract.gibbonPersonID',
                'gibbonEnhancedFinanceContract.gibbonFamilyID',
                'gibbonEnhancedFinanceContract.contractNumber',
                'gibbonEnhancedFinanceContract.startDate',
                'gibbonEnhancedFinanceContract.endDate',
                'gibbonEnhancedFinanceContract.weeklyRate',
                'gibbonEnhancedFinanceContract.daysPerWeek',
                'gibbonEnhancedFinanceContract.status',
                'gibbonEnhancedFinanceContract.terms',
                'gibbonEnhancedFinanceContract.signedAt',
                'gibbonEnhancedFinanceContract.timestampCreated',
                'gibbonPerson.surname AS childSurname',
                'gibbonPerson.preferredName AS childPreferredName',
                'gibbonFamily.name AS familyName',
                'gibbonSchoolYear.name AS schoolYearName',
                "FIND_IN_SET(gibbonEnhancedFinanceContract.status, 'Active,Suspended,Terminated,Expired') AS defaultSortOrder"
            ])
            ->leftJoin('gibbonPerson', 'gibbonEnhancedFinanceContract.gibbonPersonID = gibbonPerson.gibbonPersonID')
            ->leftJoin('gibbonFamily', 'gibbonEnhancedFinanceContract.gibbonFamilyID = gibbonFamily.gibbonFamilyID')
            ->innerJoin('gibbonSchoolYear', 'gibbonSchoolYear.gibbonSchoolYearID = :gibbonSchoolYearID')
            ->where('gibbonEnhancedFinanceContract.startDate <= gibbonSchoolYear.lastDay')
            ->where('(gibbonEnhancedFinanceContract.endDate IS NULL OR gibbonEnhancedFinanceContract.endDate >= gibbonSchoolYear.firstDay)')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID);

        $criteria->addFilterRules($this->getFilterRules());

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query contracts by family with pagination and filtering.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonFamilyID
     * @return DataSet
     */
    public function queryContractsByFamily(QueryCriteria $criteria, $gibbonFamilyID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonEnhancedFinanceContract.gibbonEnhancedFinanceContractID',
                'gibbonEnhancedFinanceContract.gibbonPersonID',
                'gibbonEnhancedFinanceContract.gibbonFamilyID',
                'gibbonEnhancedFinanceContract.contractNumber',
                'gibbonEnhancedFinanceContract.startDate',
                'gibbonEnhancedFinanceContract.endDate',
                'gibbonEnhancedFinanceContract.weeklyRate',
                'gibbonEnhancedFinanceContract.daysPerWeek',
                'gibbonEnhancedFinanceContract.status',
                'gibbonEnhancedFinanceContract.terms',
                'gibbonEnhancedFinanceContract.signedAt',
                'gibbonPerson.surname AS childSurname',
                'gibbonPerson.preferredName AS childPreferredName',
                'gibbonFamily.name AS familyName'
            ])
            ->leftJoin('gibbonPerson', 'gibbonEnhancedFinanceContract.gibbonPersonID = gibbonPerson.gibbonPersonID')
            ->leftJoin('gibbonFamily', 'gibbonEnhancedFinanceContract.gibbonFamilyID = gibbonFamily.gibbonFamilyID')
            ->where('gibbonEnhancedFinanceContract.gibbonFamilyID = :gibbonFamilyID')
            ->bindValue('gibbonFamilyID', $gibbonFamilyID);

        $criteria->addFilterRules($this->getFilterRules());

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query contracts by child (person) with pagination and filtering.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonPersonID
     * @return DataSet
     */
    public function queryContractsByChild(QueryCriteria $criteria, $gibbonPersonID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonEnhancedFinanceContract.gibbonEnhancedFinanceContractID',
                'gibbonEnhancedFinanceContract.gibbonPersonID',
                'gibbonEnhancedFinanceContract.gibbonFamilyID',
                'gibbonEnhancedFinanceContract.contractNumber',
                'gibbonEnhancedFinanceContract.startDate',
                'gibbonEnhancedFinanceContract.endDate',
                'gibbonEnhancedFinanceContract.weeklyRate',
                'gibbonEnhancedFinanceContract.daysPerWeek',
                'gibbonEnhancedFinanceContract.status',
                'gibbonEnhancedFinanceContract.terms',
                'gibbonEnhancedFinanceContract.signedAt',
                'gibbonFamily.name AS familyName'
            ])
            ->leftJoin('gibbonFamily', 'gibbonEnhancedFinanceContract.gibbonFamilyID = gibbonFamily.gibbonFamilyID')
            ->where('gibbonEnhancedFinanceContract.gibbonPersonID = :gibbonPersonID')
            ->bindValue('gibbonPersonID', $gibbonPersonID);

        $criteria->addFilterRules($this->getFilterRules());

        return $this->runQuery($query, $criteria);
    }

    /**
     * Select contract by ID with full details including child and family information.
     *
     * @param int $gibbonEnhancedFinanceContractID
     * @return array
     */
    public function selectContractByID($gibbonEnhancedFinanceContractID)
    {
        $data = ['gibbonEnhancedFinanceContractID' => $gibbonEnhancedFinanceContractID];
        $sql = "SELECT
                gibbonEnhancedFinanceContract.*,
                gibbonPerson.surname AS childSurname,
                gibbonPerson.preferredName AS childPreferredName,
                gibbonPerson.dob AS childDOB,
                gibbonFamily.name AS familyName,
                createdBy.surname AS createdBySurname,
                createdBy.preferredName AS createdByPreferredName,
                signedBy.surname AS signedBySurname,
                signedBy.preferredName AS signedByPreferredName
            FROM gibbonEnhancedFinanceContract
            LEFT JOIN gibbonPerson ON gibbonEnhancedFinanceContract.gibbonPersonID = gibbonPerson.gibbonPersonID
            LEFT JOIN gibbonFamily ON gibbonEnhancedFinanceContract.gibbonFamilyID = gibbonFamily.gibbonFamilyID
            LEFT JOIN gibbonPerson AS createdBy ON gibbonEnhancedFinanceContract.createdByID = createdBy.gibbonPersonID
            LEFT JOIN gibbonPerson AS signedBy ON gibbonEnhancedFinanceContract.signedByID = signedBy.gibbonPersonID
            WHERE gibbonEnhancedFinanceContract.gibbonEnhancedFinanceContractID = :gibbonEnhancedFinanceContractID";

        return $this->db()->selectOne($sql, $data);
    }

    /**
     * Select active contracts for a family.
     *
     * @param int $gibbonFamilyID
     * @return Result
     */
    public function selectActiveByFamily($gibbonFamilyID)
    {
        $data = ['gibbonFamilyID' => $gibbonFamilyID];
        $sql = "SELECT
                gibbonEnhancedFinanceContract.*,
                gibbonPerson.surname AS childSurname,
                gibbonPerson.preferredName AS childPreferredName
            FROM gibbonEnhancedFinanceContract
            LEFT JOIN gibbonPerson ON gibbonEnhancedFinanceContract.gibbonPersonID = gibbonPerson.gibbonPersonID
            WHERE gibbonEnhancedFinanceContract.gibbonFamilyID = :gibbonFamilyID
            AND gibbonEnhancedFinanceContract.status = 'Active'
            ORDER BY gibbonEnhancedFinanceContract.startDate ASC";

        return $this->db()->select($sql, $data);
    }

    /**
     * Select active contracts for a child (person).
     *
     * @param int $gibbonPersonID
     * @return Result
     */
    public function selectActiveByChild($gibbonPersonID)
    {
        $data = ['gibbonPersonID' => $gibbonPersonID];
        $sql = "SELECT
                gibbonEnhancedFinanceContract.*,
                gibbonFamily.name AS familyName
            FROM gibbonEnhancedFinanceContract
            LEFT JOIN gibbonFamily ON gibbonEnhancedFinanceContract.gibbonFamilyID = gibbonFamily.gibbonFamilyID
            WHERE gibbonEnhancedFinanceContract.gibbonPersonID = :gibbonPersonID
            AND gibbonEnhancedFinanceContract.status = 'Active'
            ORDER BY gibbonEnhancedFinanceContract.startDate ASC";

        return $this->db()->select($sql, $data);
    }

    /**
     * Select contracts expiring within a date range.
     *
     * @param int $gibbonSchoolYearID
     * @param string $startDate
     * @param string $endDate
     * @return Result
     */
    public function selectExpiringSoon($startDate, $endDate)
    {
        $data = [
            'startDate' => $startDate,
            'endDate' => $endDate
        ];
        $sql = "SELECT
                gibbonEnhancedFinanceContract.*,
                gibbonPerson.surname AS childSurname,
                gibbonPerson.preferredName AS childPreferredName,
                gibbonFamily.name AS familyName,
                DATEDIFF(gibbonEnhancedFinanceContract.endDate, CURDATE()) AS daysRemaining
            FROM gibbonEnhancedFinanceContract
            LEFT JOIN gibbonPerson ON gibbonEnhancedFinanceContract.gibbonPersonID = gibbonPerson.gibbonPersonID
            LEFT JOIN gibbonFamily ON gibbonEnhancedFinanceContract.gibbonFamilyID = gibbonFamily.gibbonFamilyID
            WHERE gibbonEnhancedFinanceContract.status = 'Active'
            AND gibbonEnhancedFinanceContract.endDate BETWEEN :startDate AND :endDate
            ORDER BY gibbonEnhancedFinanceContract.endDate ASC";

        return $this->db()->select($sql, $data);
    }

    /**
     * Get contract summary statistics.
     *
     * Returns aggregate statistics including total contracts, active count,
     * suspended count, terminated count, expired count, and contracts needing renewal.
     *
     * @return array
     */
    public function selectContractSummary()
    {
        $data = [
            'today' => date('Y-m-d')
        ];
        $sql = "SELECT
                COUNT(*) AS totalContracts,
                SUM(weeklyRate) AS totalValue,
                SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) AS activeCount,
                SUM(CASE WHEN status = 'Active' THEN weeklyRate ELSE 0 END) AS activeValue,
                SUM(CASE WHEN status = 'Suspended' THEN 1 ELSE 0 END) AS suspendedCount,
                SUM(CASE WHEN status = 'Terminated' THEN 1 ELSE 0 END) AS terminatedCount,
                SUM(CASE WHEN status = 'Expired' THEN 1 ELSE 0 END) AS expiredCount,
                SUM(CASE WHEN status = 'Active' AND endDate < :today THEN 1 ELSE 0 END) AS needsRenewalCount
            FROM gibbonEnhancedFinanceContract";

        return $this->db()->selectOne($sql, $data);
    }

    /**
     * Calculate total contracted amount for a child in a date range.
     *
     * @param int $gibbonPersonID Child's person ID
     * @param string $startDate Start date
     * @param string $endDate End date
     * @return array
     */
    public function selectTotalContractedByChildAndDateRange($gibbonPersonID, $startDate, $endDate)
    {
        $data = [
            'gibbonPersonID' => $gibbonPersonID,
            'startDate' => $startDate,
            'endDate' => $endDate
        ];
        $sql = "SELECT
                SUM(weeklyRate) AS totalContracted,
                COUNT(*) AS contractCount
            FROM gibbonEnhancedFinanceContract
            WHERE gibbonPersonID = :gibbonPersonID
            AND status IN ('Active', 'Expired')
            AND (
                (startDate <= :endDate AND endDate >= :startDate)
                OR (startDate BETWEEN :startDate AND :endDate)
                OR (endDate BETWEEN :startDate AND :endDate)
            )";

        return $this->db()->selectOne($sql, $data);
    }

    /**
     * Update contract status.
     *
     * @param int $gibbonEnhancedFinanceContractID
     * @param string $status
     * @return bool
     */
    public function updateStatus($gibbonEnhancedFinanceContractID, $status)
    {
        return $this->update($gibbonEnhancedFinanceContractID, [
            'status' => $status
        ]);
    }

    /**
     * Expire contracts that have passed their end date.
     *
     * @return int Number of contracts updated
     */
    public function expireOverdueContracts()
    {
        $data = ['today' => date('Y-m-d')];
        $sql = "UPDATE gibbonEnhancedFinanceContract
                SET status = 'Expired'
                WHERE status = 'Active'
                AND endDate < :today";

        return $this->db()->update($sql, $data);
    }

    /**
     * Get filter rules for contract queries.
     *
     * @return array
     */
    protected function getFilterRules()
    {
        return [
            'status' => function ($query, $status) {
                switch ($status) {
                    case 'Expiring':
                        return $query
                            ->where("gibbonEnhancedFinanceContract.status = 'Active'")
                            ->where('gibbonEnhancedFinanceContract.endDate BETWEEN :today AND :thirtyDays')
                            ->bindValue('today', date('Y-m-d'))
                            ->bindValue('thirtyDays', date('Y-m-d', strtotime('+30 days')));

                    default:
                        return $query
                            ->where('gibbonEnhancedFinanceContract.status = :status')
                            ->bindValue('status', $status);
                }
            },

            'family' => function ($query, $gibbonFamilyID) {
                return $query
                    ->where('gibbonEnhancedFinanceContract.gibbonFamilyID = :filterFamilyID')
                    ->bindValue('filterFamilyID', $gibbonFamilyID);
            },

            'child' => function ($query, $gibbonPersonID) {
                return $query
                    ->where('gibbonEnhancedFinanceContract.gibbonPersonID = :filterPersonID')
                    ->bindValue('filterPersonID', $gibbonPersonID);
            },

            'startDateFrom' => function ($query, $startDateFrom) {
                return $query
                    ->where('gibbonEnhancedFinanceContract.startDate >= :startDateFrom')
                    ->bindValue('startDateFrom', $startDateFrom);
            },

            'startDateTo' => function ($query, $startDateTo) {
                return $query
                    ->where('gibbonEnhancedFinanceContract.startDate <= :startDateTo')
                    ->bindValue('startDateTo', $startDateTo);
            },

            'endDateFrom' => function ($query, $endDateFrom) {
                return $query
                    ->where('gibbonEnhancedFinanceContract.endDate >= :endDateFrom')
                    ->bindValue('endDateFrom', $endDateFrom);
            },

            'endDateTo' => function ($query, $endDateTo) {
                return $query
                    ->where('gibbonEnhancedFinanceContract.endDate <= :endDateTo')
                    ->bindValue('endDateTo', $endDateTo);
            },
        ];
    }
}
