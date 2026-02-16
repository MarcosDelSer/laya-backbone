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
 * Enhanced Finance Relevé 24 Gateway
 *
 * Provides data access methods for the gibbonEnhancedFinanceReleve24 table.
 * Handles Quebec RL-24 tax document storage, queries with pagination, and filtering.
 * RL-24 is required by Revenu Québec for childcare expense deductions.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class Releve24Gateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonEnhancedFinanceReleve24';
    private static $primaryKey = 'gibbonEnhancedFinanceReleve24ID';

    private static $searchableColumns = ['recipientName', 'childName', 'recipientSIN'];

    /**
     * Query RL-24 slips by tax year with pagination and filtering.
     *
     * @param QueryCriteria $criteria
     * @param int $taxYear Tax year (YYYY format)
     * @return DataSet
     */
    public function queryReleve24ByYear(QueryCriteria $criteria, $taxYear)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonEnhancedFinanceReleve24.gibbonEnhancedFinanceReleve24ID',
                'gibbonEnhancedFinanceReleve24.gibbonPersonID',
                'gibbonEnhancedFinanceReleve24.gibbonFamilyID',
                'gibbonEnhancedFinanceReleve24.taxYear',
                'gibbonEnhancedFinanceReleve24.slipType',
                'gibbonEnhancedFinanceReleve24.daysOfCare',
                'gibbonEnhancedFinanceReleve24.totalAmountsPaid',
                'gibbonEnhancedFinanceReleve24.nonQualifyingExpenses',
                'gibbonEnhancedFinanceReleve24.qualifyingExpenses',
                'gibbonEnhancedFinanceReleve24.providerSIN',
                'gibbonEnhancedFinanceReleve24.recipientSIN',
                'gibbonEnhancedFinanceReleve24.recipientName',
                'gibbonEnhancedFinanceReleve24.childName',
                'gibbonEnhancedFinanceReleve24.generatedAt',
                'gibbonEnhancedFinanceReleve24.sentAt',
                'gibbonEnhancedFinanceReleve24.status',
                'gibbonEnhancedFinanceReleve24.timestampCreated',
                'gibbonPerson.surname AS childSurname',
                'gibbonPerson.preferredName AS childPreferredName',
                'gibbonFamily.name AS familyName',
                'createdBy.surname AS createdBySurname',
                'createdBy.preferredName AS createdByPreferredName',
                "FIND_IN_SET(gibbonEnhancedFinanceReleve24.status, 'Draft,Generated,Sent,Filed,Amended') AS defaultSortOrder"
            ])
            ->leftJoin('gibbonPerson', 'gibbonEnhancedFinanceReleve24.gibbonPersonID = gibbonPerson.gibbonPersonID')
            ->leftJoin('gibbonFamily', 'gibbonEnhancedFinanceReleve24.gibbonFamilyID = gibbonFamily.gibbonFamilyID')
            ->leftJoin('gibbonPerson AS createdBy', 'gibbonEnhancedFinanceReleve24.createdByID = createdBy.gibbonPersonID')
            ->where('gibbonEnhancedFinanceReleve24.taxYear = :taxYear')
            ->bindValue('taxYear', $taxYear);

        $criteria->addFilterRules($this->getFilterRules());

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query RL-24 slips by child (person) with pagination and filtering.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonPersonID Child's person ID
     * @return DataSet
     */
    public function queryReleve24ByChild(QueryCriteria $criteria, $gibbonPersonID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonEnhancedFinanceReleve24.gibbonEnhancedFinanceReleve24ID',
                'gibbonEnhancedFinanceReleve24.gibbonPersonID',
                'gibbonEnhancedFinanceReleve24.gibbonFamilyID',
                'gibbonEnhancedFinanceReleve24.taxYear',
                'gibbonEnhancedFinanceReleve24.slipType',
                'gibbonEnhancedFinanceReleve24.daysOfCare',
                'gibbonEnhancedFinanceReleve24.totalAmountsPaid',
                'gibbonEnhancedFinanceReleve24.nonQualifyingExpenses',
                'gibbonEnhancedFinanceReleve24.qualifyingExpenses',
                'gibbonEnhancedFinanceReleve24.providerSIN',
                'gibbonEnhancedFinanceReleve24.recipientSIN',
                'gibbonEnhancedFinanceReleve24.recipientName',
                'gibbonEnhancedFinanceReleve24.childName',
                'gibbonEnhancedFinanceReleve24.generatedAt',
                'gibbonEnhancedFinanceReleve24.sentAt',
                'gibbonEnhancedFinanceReleve24.status',
                'gibbonEnhancedFinanceReleve24.timestampCreated',
                'gibbonFamily.name AS familyName'
            ])
            ->leftJoin('gibbonFamily', 'gibbonEnhancedFinanceReleve24.gibbonFamilyID = gibbonFamily.gibbonFamilyID')
            ->where('gibbonEnhancedFinanceReleve24.gibbonPersonID = :gibbonPersonID')
            ->bindValue('gibbonPersonID', $gibbonPersonID);

        $criteria->addFilterRules($this->getFilterRules());

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query RL-24 slips by family with pagination and filtering.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonFamilyID Family ID
     * @return DataSet
     */
    public function queryReleve24ByFamily(QueryCriteria $criteria, $gibbonFamilyID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonEnhancedFinanceReleve24.gibbonEnhancedFinanceReleve24ID',
                'gibbonEnhancedFinanceReleve24.gibbonPersonID',
                'gibbonEnhancedFinanceReleve24.gibbonFamilyID',
                'gibbonEnhancedFinanceReleve24.taxYear',
                'gibbonEnhancedFinanceReleve24.slipType',
                'gibbonEnhancedFinanceReleve24.daysOfCare',
                'gibbonEnhancedFinanceReleve24.totalAmountsPaid',
                'gibbonEnhancedFinanceReleve24.nonQualifyingExpenses',
                'gibbonEnhancedFinanceReleve24.qualifyingExpenses',
                'gibbonEnhancedFinanceReleve24.recipientName',
                'gibbonEnhancedFinanceReleve24.childName',
                'gibbonEnhancedFinanceReleve24.generatedAt',
                'gibbonEnhancedFinanceReleve24.sentAt',
                'gibbonEnhancedFinanceReleve24.status',
                'gibbonEnhancedFinanceReleve24.timestampCreated',
                'gibbonPerson.surname AS childSurname',
                'gibbonPerson.preferredName AS childPreferredName'
            ])
            ->leftJoin('gibbonPerson', 'gibbonEnhancedFinanceReleve24.gibbonPersonID = gibbonPerson.gibbonPersonID')
            ->where('gibbonEnhancedFinanceReleve24.gibbonFamilyID = :gibbonFamilyID')
            ->bindValue('gibbonFamilyID', $gibbonFamilyID);

        $criteria->addFilterRules($this->getFilterRules());

        return $this->runQuery($query, $criteria);
    }

    /**
     * Select RL-24 slip by ID with full details.
     *
     * @param int $gibbonEnhancedFinanceReleve24ID
     * @return array
     */
    public function selectReleve24ByID($gibbonEnhancedFinanceReleve24ID)
    {
        $data = ['gibbonEnhancedFinanceReleve24ID' => $gibbonEnhancedFinanceReleve24ID];
        $sql = "SELECT
                gibbonEnhancedFinanceReleve24.*,
                gibbonPerson.surname AS childSurname,
                gibbonPerson.preferredName AS childPreferredName,
                gibbonPerson.dob AS childDOB,
                gibbonFamily.name AS familyName,
                createdBy.surname AS createdBySurname,
                createdBy.preferredName AS createdByPreferredName
            FROM gibbonEnhancedFinanceReleve24
            LEFT JOIN gibbonPerson ON gibbonEnhancedFinanceReleve24.gibbonPersonID = gibbonPerson.gibbonPersonID
            LEFT JOIN gibbonFamily ON gibbonEnhancedFinanceReleve24.gibbonFamilyID = gibbonFamily.gibbonFamilyID
            LEFT JOIN gibbonPerson AS createdBy ON gibbonEnhancedFinanceReleve24.createdByID = createdBy.gibbonPersonID
            WHERE gibbonEnhancedFinanceReleve24.gibbonEnhancedFinanceReleve24ID = :gibbonEnhancedFinanceReleve24ID";

        return $this->db()->selectOne($sql, $data);
    }

    /**
     * Select existing RL-24 slip for a child and tax year.
     * Used to check if a slip already exists before generation.
     *
     * @param int $gibbonPersonID Child's person ID
     * @param int $taxYear Tax year (YYYY format)
     * @param string $slipType Slip type (R, A, D) - optional
     * @return array|false
     */
    public function selectReleve24ByChildAndYear($gibbonPersonID, $taxYear, $slipType = null)
    {
        $data = [
            'gibbonPersonID' => $gibbonPersonID,
            'taxYear' => $taxYear
        ];
        $sql = "SELECT
                gibbonEnhancedFinanceReleve24.*,
                gibbonFamily.name AS familyName
            FROM gibbonEnhancedFinanceReleve24
            LEFT JOIN gibbonFamily ON gibbonEnhancedFinanceReleve24.gibbonFamilyID = gibbonFamily.gibbonFamilyID
            WHERE gibbonEnhancedFinanceReleve24.gibbonPersonID = :gibbonPersonID
            AND gibbonEnhancedFinanceReleve24.taxYear = :taxYear";

        if ($slipType !== null) {
            $sql .= " AND gibbonEnhancedFinanceReleve24.slipType = :slipType";
            $data['slipType'] = $slipType;
        }

        $sql .= " ORDER BY gibbonEnhancedFinanceReleve24.generatedAt DESC LIMIT 1";

        return $this->db()->selectOne($sql, $data);
    }

    /**
     * Select all RL-24 slips by status.
     *
     * @param int $taxYear Tax year (YYYY format)
     * @param string $status Status to filter by
     * @return Result
     */
    public function selectReleve24ByStatus($taxYear, $status)
    {
        $data = [
            'taxYear' => $taxYear,
            'status' => $status
        ];
        $sql = "SELECT
                gibbonEnhancedFinanceReleve24.*,
                gibbonPerson.surname AS childSurname,
                gibbonPerson.preferredName AS childPreferredName,
                gibbonFamily.name AS familyName
            FROM gibbonEnhancedFinanceReleve24
            LEFT JOIN gibbonPerson ON gibbonEnhancedFinanceReleve24.gibbonPersonID = gibbonPerson.gibbonPersonID
            LEFT JOIN gibbonFamily ON gibbonEnhancedFinanceReleve24.gibbonFamilyID = gibbonFamily.gibbonFamilyID
            WHERE gibbonEnhancedFinanceReleve24.taxYear = :taxYear
            AND gibbonEnhancedFinanceReleve24.status = :status
            ORDER BY gibbonEnhancedFinanceReleve24.recipientName ASC, gibbonEnhancedFinanceReleve24.childName ASC";

        return $this->db()->select($sql, $data);
    }

    /**
     * Select RL-24 slips pending to be sent.
     *
     * @param int $taxYear Tax year (YYYY format)
     * @return Result
     */
    public function selectPendingReleve24($taxYear)
    {
        $data = ['taxYear' => $taxYear];
        $sql = "SELECT
                gibbonEnhancedFinanceReleve24.*,
                gibbonPerson.surname AS childSurname,
                gibbonPerson.preferredName AS childPreferredName,
                gibbonFamily.name AS familyName
            FROM gibbonEnhancedFinanceReleve24
            LEFT JOIN gibbonPerson ON gibbonEnhancedFinanceReleve24.gibbonPersonID = gibbonPerson.gibbonPersonID
            LEFT JOIN gibbonFamily ON gibbonEnhancedFinanceReleve24.gibbonFamilyID = gibbonFamily.gibbonFamilyID
            WHERE gibbonEnhancedFinanceReleve24.taxYear = :taxYear
            AND gibbonEnhancedFinanceReleve24.status = 'Generated'
            AND gibbonEnhancedFinanceReleve24.sentAt IS NULL
            ORDER BY gibbonEnhancedFinanceReleve24.recipientName ASC";

        return $this->db()->select($sql, $data);
    }

    /**
     * Get RL-24 summary statistics for a tax year.
     *
     * @param int $taxYear Tax year (YYYY format)
     * @return array
     */
    public function selectReleve24SummaryByYear($taxYear)
    {
        $data = ['taxYear' => $taxYear];
        $sql = "SELECT
                COUNT(*) AS totalSlips,
                SUM(CASE WHEN slipType = 'R' THEN 1 ELSE 0 END) AS originalSlips,
                SUM(CASE WHEN slipType = 'A' THEN 1 ELSE 0 END) AS amendedSlips,
                SUM(CASE WHEN slipType = 'D' THEN 1 ELSE 0 END) AS cancelledSlips,
                SUM(CASE WHEN status = 'Draft' THEN 1 ELSE 0 END) AS draftCount,
                SUM(CASE WHEN status = 'Generated' THEN 1 ELSE 0 END) AS generatedCount,
                SUM(CASE WHEN status = 'Sent' THEN 1 ELSE 0 END) AS sentCount,
                SUM(CASE WHEN status = 'Filed' THEN 1 ELSE 0 END) AS filedCount,
                SUM(daysOfCare) AS totalDaysOfCare,
                SUM(totalAmountsPaid) AS totalAmountsPaid,
                SUM(qualifyingExpenses) AS totalQualifyingExpenses,
                SUM(nonQualifyingExpenses) AS totalNonQualifyingExpenses
            FROM gibbonEnhancedFinanceReleve24
            WHERE taxYear = :taxYear";

        return $this->db()->selectOne($sql, $data);
    }

    /**
     * Get count of RL-24 slips by status for a tax year.
     *
     * @param int $taxYear Tax year (YYYY format)
     * @return Result
     */
    public function selectReleve24CountByStatus($taxYear)
    {
        $data = ['taxYear' => $taxYear];
        $sql = "SELECT
                status,
                COUNT(*) AS slipCount,
                SUM(qualifyingExpenses) AS totalQualifyingExpenses
            FROM gibbonEnhancedFinanceReleve24
            WHERE taxYear = :taxYear
            GROUP BY status
            ORDER BY FIND_IN_SET(status, 'Draft,Generated,Sent,Filed,Amended')";

        return $this->db()->select($sql, $data);
    }

    /**
     * Get list of tax years that have RL-24 slips.
     *
     * @return Result
     */
    public function selectAvailableTaxYears()
    {
        $sql = "SELECT DISTINCT
                taxYear,
                COUNT(*) AS slipCount
            FROM gibbonEnhancedFinanceReleve24
            GROUP BY taxYear
            ORDER BY taxYear DESC";

        return $this->db()->select($sql);
    }

    /**
     * Check if child has existing original RL-24 for tax year.
     * Used to determine if amendment is needed.
     *
     * @param int $gibbonPersonID Child's person ID
     * @param int $taxYear Tax year (YYYY format)
     * @return bool
     */
    public function hasOriginalReleve24($gibbonPersonID, $taxYear)
    {
        $data = [
            'gibbonPersonID' => $gibbonPersonID,
            'taxYear' => $taxYear
        ];
        $sql = "SELECT COUNT(*) AS cnt
            FROM gibbonEnhancedFinanceReleve24
            WHERE gibbonPersonID = :gibbonPersonID
            AND taxYear = :taxYear
            AND slipType = 'R'
            AND status IN ('Sent', 'Filed')";

        $result = $this->db()->selectOne($sql, $data);
        return ($result['cnt'] ?? 0) > 0;
    }

    /**
     * Update RL-24 slip status to 'Sent' and record sent timestamp.
     *
     * @param int $gibbonEnhancedFinanceReleve24ID
     * @return bool
     */
    public function markAsSent($gibbonEnhancedFinanceReleve24ID)
    {
        return $this->update($gibbonEnhancedFinanceReleve24ID, [
            'status' => 'Sent',
            'sentAt' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Update RL-24 slip status to 'Filed'.
     *
     * @param int $gibbonEnhancedFinanceReleve24ID
     * @return bool
     */
    public function markAsFiled($gibbonEnhancedFinanceReleve24ID)
    {
        return $this->update($gibbonEnhancedFinanceReleve24ID, [
            'status' => 'Filed'
        ]);
    }

    /**
     * Batch update RL-24 slips to 'Sent' status.
     *
     * @param array $releve24IDs Array of RL-24 IDs
     * @return int Number of updated records
     */
    public function batchMarkAsSent(array $releve24IDs)
    {
        if (empty($releve24IDs)) {
            return 0;
        }

        $sentAt = date('Y-m-d H:i:s');
        $count = 0;

        foreach ($releve24IDs as $id) {
            if ($this->markAsSent($id)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Insert a new RL-24 slip and return the ID.
     *
     * @param array $data RL-24 data
     * @return int|false
     */
    public function insertReleve24(array $data)
    {
        // Ensure required fields are present
        if (empty($data['gibbonPersonID']) || empty($data['gibbonFamilyID']) || empty($data['taxYear'])) {
            return false;
        }

        // Set defaults
        if (empty($data['slipType'])) {
            $data['slipType'] = 'R'; // Original
        }
        if (empty($data['status'])) {
            $data['status'] = 'Draft';
        }
        if (empty($data['generatedAt'])) {
            $data['generatedAt'] = date('Y-m-d H:i:s');
        }

        return $this->insert($data);
    }

    /**
     * Get filter rules for RL-24 queries.
     *
     * @return array
     */
    protected function getFilterRules()
    {
        return [
            'slipType' => function ($query, $slipType) {
                return $query
                    ->where('gibbonEnhancedFinanceReleve24.slipType = :slipType')
                    ->bindValue('slipType', $slipType);
            },

            'status' => function ($query, $status) {
                return $query
                    ->where('gibbonEnhancedFinanceReleve24.status = :status')
                    ->bindValue('status', $status);
            },

            'family' => function ($query, $gibbonFamilyID) {
                return $query
                    ->where('gibbonEnhancedFinanceReleve24.gibbonFamilyID = :filterFamilyID')
                    ->bindValue('filterFamilyID', $gibbonFamilyID);
            },

            'child' => function ($query, $gibbonPersonID) {
                return $query
                    ->where('gibbonEnhancedFinanceReleve24.gibbonPersonID = :filterPersonID')
                    ->bindValue('filterPersonID', $gibbonPersonID);
            },

            'taxYear' => function ($query, $taxYear) {
                return $query
                    ->where('gibbonEnhancedFinanceReleve24.taxYear = :filterTaxYear')
                    ->bindValue('filterTaxYear', $taxYear);
            },

            'generated' => function ($query, $value) {
                if ($value === 'Y') {
                    return $query->where('gibbonEnhancedFinanceReleve24.generatedAt IS NOT NULL');
                } else {
                    return $query->where('gibbonEnhancedFinanceReleve24.generatedAt IS NULL');
                }
            },

            'sent' => function ($query, $value) {
                if ($value === 'Y') {
                    return $query->where('gibbonEnhancedFinanceReleve24.sentAt IS NOT NULL');
                } else {
                    return $query->where('gibbonEnhancedFinanceReleve24.sentAt IS NULL');
                }
            },

            'generatedFrom' => function ($query, $date) {
                return $query
                    ->where('DATE(gibbonEnhancedFinanceReleve24.generatedAt) >= :generatedFrom')
                    ->bindValue('generatedFrom', $date);
            },

            'generatedTo' => function ($query, $date) {
                return $query
                    ->where('DATE(gibbonEnhancedFinanceReleve24.generatedAt) <= :generatedTo')
                    ->bindValue('generatedTo', $date);
            },

            'minAmount' => function ($query, $amount) {
                return $query
                    ->where('gibbonEnhancedFinanceReleve24.qualifyingExpenses >= :minAmount')
                    ->bindValue('minAmount', $amount);
            },

            'maxAmount' => function ($query, $amount) {
                return $query
                    ->where('gibbonEnhancedFinanceReleve24.qualifyingExpenses <= :maxAmount')
                    ->bindValue('maxAmount', $amount);
            },
        ];
    }
}
