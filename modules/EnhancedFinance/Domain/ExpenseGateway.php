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
 * Enhanced Finance Expense Gateway
 *
 * Provides data access methods for the gibbonEnhancedFinanceExpense table.
 * Handles expense tracking CRUD operations, queries with pagination, filtering, and summaries.
 *
 * @version v1.0.02
 * @since   v1.0.02
 */
class ExpenseGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonEnhancedFinanceExpense';
    private static $primaryKey = 'gibbonEnhancedFinanceExpenseID';

    private static $searchableColumns = ['vendor', 'reference', 'description'];

    /**
     * Query expenses by school year with pagination and filtering.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonSchoolYearID
     * @return DataSet
     */
    public function queryExpensesByYear(QueryCriteria $criteria, $gibbonSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonEnhancedFinanceExpense.gibbonEnhancedFinanceExpenseID',
                'gibbonEnhancedFinanceExpense.gibbonEnhancedFinanceExpenseCategoryID',
                'gibbonEnhancedFinanceExpense.gibbonSchoolYearID',
                'gibbonEnhancedFinanceExpense.expenseDate',
                'gibbonEnhancedFinanceExpense.amount',
                'gibbonEnhancedFinanceExpense.taxAmount',
                'gibbonEnhancedFinanceExpense.totalAmount',
                'gibbonEnhancedFinanceExpense.vendor',
                'gibbonEnhancedFinanceExpense.reference',
                'gibbonEnhancedFinanceExpense.paymentMethod',
                'gibbonEnhancedFinanceExpense.description',
                'gibbonEnhancedFinanceExpense.receiptPath',
                'gibbonEnhancedFinanceExpense.status',
                'gibbonEnhancedFinanceExpense.approvedByID',
                'gibbonEnhancedFinanceExpense.approvedAt',
                'gibbonEnhancedFinanceExpense.createdByID',
                'gibbonEnhancedFinanceExpense.timestampCreated',
                'gibbonEnhancedFinanceExpenseCategory.name AS categoryName',
                'gibbonEnhancedFinanceExpenseCategory.accountCode AS categoryAccountCode',
                'createdBy.surname AS createdBySurname',
                'createdBy.preferredName AS createdByPreferredName',
                'approvedBy.surname AS approvedBySurname',
                'approvedBy.preferredName AS approvedByPreferredName',
                "FIND_IN_SET(gibbonEnhancedFinanceExpense.status, 'Pending,Approved,Paid,Rejected') AS defaultSortOrder"
            ])
            ->innerJoin('gibbonEnhancedFinanceExpenseCategory', 'gibbonEnhancedFinanceExpense.gibbonEnhancedFinanceExpenseCategoryID = gibbonEnhancedFinanceExpenseCategory.gibbonEnhancedFinanceExpenseCategoryID')
            ->leftJoin('gibbonPerson AS createdBy', 'gibbonEnhancedFinanceExpense.createdByID = createdBy.gibbonPersonID')
            ->leftJoin('gibbonPerson AS approvedBy', 'gibbonEnhancedFinanceExpense.approvedByID = approvedBy.gibbonPersonID')
            ->where('gibbonEnhancedFinanceExpense.gibbonSchoolYearID = :gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID);

        $criteria->addFilterRules($this->getFilterRules());

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query expenses by category with pagination and filtering.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonEnhancedFinanceExpenseCategoryID
     * @return DataSet
     */
    public function queryExpensesByCategory(QueryCriteria $criteria, $gibbonEnhancedFinanceExpenseCategoryID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonEnhancedFinanceExpense.gibbonEnhancedFinanceExpenseID',
                'gibbonEnhancedFinanceExpense.gibbonEnhancedFinanceExpenseCategoryID',
                'gibbonEnhancedFinanceExpense.gibbonSchoolYearID',
                'gibbonEnhancedFinanceExpense.expenseDate',
                'gibbonEnhancedFinanceExpense.amount',
                'gibbonEnhancedFinanceExpense.taxAmount',
                'gibbonEnhancedFinanceExpense.totalAmount',
                'gibbonEnhancedFinanceExpense.vendor',
                'gibbonEnhancedFinanceExpense.reference',
                'gibbonEnhancedFinanceExpense.paymentMethod',
                'gibbonEnhancedFinanceExpense.description',
                'gibbonEnhancedFinanceExpense.status',
                'gibbonEnhancedFinanceExpense.timestampCreated',
                'gibbonEnhancedFinanceExpenseCategory.name AS categoryName',
                'gibbonSchoolYear.name AS schoolYearName',
                'createdBy.surname AS createdBySurname',
                'createdBy.preferredName AS createdByPreferredName'
            ])
            ->innerJoin('gibbonEnhancedFinanceExpenseCategory', 'gibbonEnhancedFinanceExpense.gibbonEnhancedFinanceExpenseCategoryID = gibbonEnhancedFinanceExpenseCategory.gibbonEnhancedFinanceExpenseCategoryID')
            ->leftJoin('gibbonSchoolYear', 'gibbonEnhancedFinanceExpense.gibbonSchoolYearID = gibbonSchoolYear.gibbonSchoolYearID')
            ->leftJoin('gibbonPerson AS createdBy', 'gibbonEnhancedFinanceExpense.createdByID = createdBy.gibbonPersonID')
            ->where('gibbonEnhancedFinanceExpense.gibbonEnhancedFinanceExpenseCategoryID = :gibbonEnhancedFinanceExpenseCategoryID')
            ->bindValue('gibbonEnhancedFinanceExpenseCategoryID', $gibbonEnhancedFinanceExpenseCategoryID);

        $criteria->addFilterRules($this->getFilterRules());

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query expenses by vendor with pagination and filtering.
     *
     * @param QueryCriteria $criteria
     * @param string $vendor
     * @return DataSet
     */
    public function queryExpensesByVendor(QueryCriteria $criteria, $vendor)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonEnhancedFinanceExpense.gibbonEnhancedFinanceExpenseID',
                'gibbonEnhancedFinanceExpense.gibbonEnhancedFinanceExpenseCategoryID',
                'gibbonEnhancedFinanceExpense.gibbonSchoolYearID',
                'gibbonEnhancedFinanceExpense.expenseDate',
                'gibbonEnhancedFinanceExpense.amount',
                'gibbonEnhancedFinanceExpense.taxAmount',
                'gibbonEnhancedFinanceExpense.totalAmount',
                'gibbonEnhancedFinanceExpense.vendor',
                'gibbonEnhancedFinanceExpense.reference',
                'gibbonEnhancedFinanceExpense.paymentMethod',
                'gibbonEnhancedFinanceExpense.description',
                'gibbonEnhancedFinanceExpense.status',
                'gibbonEnhancedFinanceExpense.timestampCreated',
                'gibbonEnhancedFinanceExpenseCategory.name AS categoryName',
                'gibbonSchoolYear.name AS schoolYearName'
            ])
            ->innerJoin('gibbonEnhancedFinanceExpenseCategory', 'gibbonEnhancedFinanceExpense.gibbonEnhancedFinanceExpenseCategoryID = gibbonEnhancedFinanceExpenseCategory.gibbonEnhancedFinanceExpenseCategoryID')
            ->leftJoin('gibbonSchoolYear', 'gibbonEnhancedFinanceExpense.gibbonSchoolYearID = gibbonSchoolYear.gibbonSchoolYearID')
            ->where('gibbonEnhancedFinanceExpense.vendor = :vendor')
            ->bindValue('vendor', $vendor);

        $criteria->addFilterRules($this->getFilterRules());

        return $this->runQuery($query, $criteria);
    }

    /**
     * Select expense by ID with full details including category and approver information.
     *
     * @param int $gibbonEnhancedFinanceExpenseID
     * @return array
     */
    public function selectExpenseByID($gibbonEnhancedFinanceExpenseID)
    {
        $data = ['gibbonEnhancedFinanceExpenseID' => $gibbonEnhancedFinanceExpenseID];
        $sql = "SELECT
                gibbonEnhancedFinanceExpense.*,
                gibbonEnhancedFinanceExpenseCategory.name AS categoryName,
                gibbonEnhancedFinanceExpenseCategory.description AS categoryDescription,
                gibbonEnhancedFinanceExpenseCategory.accountCode AS categoryAccountCode,
                gibbonSchoolYear.name AS schoolYearName,
                createdBy.surname AS createdBySurname,
                createdBy.preferredName AS createdByPreferredName,
                approvedBy.surname AS approvedBySurname,
                approvedBy.preferredName AS approvedByPreferredName
            FROM gibbonEnhancedFinanceExpense
            INNER JOIN gibbonEnhancedFinanceExpenseCategory ON gibbonEnhancedFinanceExpense.gibbonEnhancedFinanceExpenseCategoryID = gibbonEnhancedFinanceExpenseCategory.gibbonEnhancedFinanceExpenseCategoryID
            LEFT JOIN gibbonSchoolYear ON gibbonEnhancedFinanceExpense.gibbonSchoolYearID = gibbonSchoolYear.gibbonSchoolYearID
            LEFT JOIN gibbonPerson AS createdBy ON gibbonEnhancedFinanceExpense.createdByID = createdBy.gibbonPersonID
            LEFT JOIN gibbonPerson AS approvedBy ON gibbonEnhancedFinanceExpense.approvedByID = approvedBy.gibbonPersonID
            WHERE gibbonEnhancedFinanceExpense.gibbonEnhancedFinanceExpenseID = :gibbonEnhancedFinanceExpenseID";

        return $this->db()->selectOne($sql, $data);
    }

    /**
     * Select pending expenses awaiting approval for a school year.
     *
     * @param int $gibbonSchoolYearID
     * @return Result
     */
    public function selectPendingExpensesByYear($gibbonSchoolYearID)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID];
        $sql = "SELECT
                gibbonEnhancedFinanceExpense.*,
                gibbonEnhancedFinanceExpenseCategory.name AS categoryName,
                createdBy.surname AS createdBySurname,
                createdBy.preferredName AS createdByPreferredName
            FROM gibbonEnhancedFinanceExpense
            INNER JOIN gibbonEnhancedFinanceExpenseCategory ON gibbonEnhancedFinanceExpense.gibbonEnhancedFinanceExpenseCategoryID = gibbonEnhancedFinanceExpenseCategory.gibbonEnhancedFinanceExpenseCategoryID
            LEFT JOIN gibbonPerson AS createdBy ON gibbonEnhancedFinanceExpense.createdByID = createdBy.gibbonPersonID
            WHERE gibbonEnhancedFinanceExpense.gibbonSchoolYearID = :gibbonSchoolYearID
            AND gibbonEnhancedFinanceExpense.status = 'Pending'
            ORDER BY gibbonEnhancedFinanceExpense.expenseDate ASC";

        return $this->db()->select($sql, $data);
    }

    /**
     * Select expenses by date range for a school year.
     *
     * @param int $gibbonSchoolYearID
     * @param string $startDate Start date (Y-m-d)
     * @param string $endDate End date (Y-m-d)
     * @return Result
     */
    public function selectExpensesByDateRange($gibbonSchoolYearID, $startDate, $endDate)
    {
        $data = [
            'gibbonSchoolYearID' => $gibbonSchoolYearID,
            'startDate' => $startDate,
            'endDate' => $endDate
        ];
        $sql = "SELECT
                gibbonEnhancedFinanceExpense.*,
                gibbonEnhancedFinanceExpenseCategory.name AS categoryName,
                gibbonEnhancedFinanceExpenseCategory.accountCode AS categoryAccountCode
            FROM gibbonEnhancedFinanceExpense
            INNER JOIN gibbonEnhancedFinanceExpenseCategory ON gibbonEnhancedFinanceExpense.gibbonEnhancedFinanceExpenseCategoryID = gibbonEnhancedFinanceExpenseCategory.gibbonEnhancedFinanceExpenseCategoryID
            WHERE gibbonEnhancedFinanceExpense.gibbonSchoolYearID = :gibbonSchoolYearID
            AND gibbonEnhancedFinanceExpense.expenseDate BETWEEN :startDate AND :endDate
            ORDER BY gibbonEnhancedFinanceExpense.expenseDate ASC";

        return $this->db()->select($sql, $data);
    }

    /**
     * Get expense summary by category for a school year.
     *
     * @param int $gibbonSchoolYearID
     * @return Result
     */
    public function selectExpenseSummaryByCategory($gibbonSchoolYearID)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID];
        $sql = "SELECT
                gibbonEnhancedFinanceExpenseCategory.gibbonEnhancedFinanceExpenseCategoryID,
                gibbonEnhancedFinanceExpenseCategory.name AS categoryName,
                gibbonEnhancedFinanceExpenseCategory.accountCode,
                COUNT(gibbonEnhancedFinanceExpense.gibbonEnhancedFinanceExpenseID) AS expenseCount,
                COALESCE(SUM(gibbonEnhancedFinanceExpense.amount), 0) AS totalAmount,
                COALESCE(SUM(gibbonEnhancedFinanceExpense.taxAmount), 0) AS totalTax,
                COALESCE(SUM(gibbonEnhancedFinanceExpense.totalAmount), 0) AS totalWithTax
            FROM gibbonEnhancedFinanceExpenseCategory
            LEFT JOIN gibbonEnhancedFinanceExpense ON gibbonEnhancedFinanceExpenseCategory.gibbonEnhancedFinanceExpenseCategoryID = gibbonEnhancedFinanceExpense.gibbonEnhancedFinanceExpenseCategoryID
                AND gibbonEnhancedFinanceExpense.gibbonSchoolYearID = :gibbonSchoolYearID
                AND gibbonEnhancedFinanceExpense.status != 'Rejected'
            WHERE gibbonEnhancedFinanceExpenseCategory.isActive = 1
            GROUP BY gibbonEnhancedFinanceExpenseCategory.gibbonEnhancedFinanceExpenseCategoryID
            ORDER BY gibbonEnhancedFinanceExpenseCategory.sortOrder ASC";

        return $this->db()->select($sql, $data);
    }

    /**
     * Get expense summary by month for a school year.
     *
     * @param int $gibbonSchoolYearID
     * @return Result
     */
    public function selectExpenseSummaryByMonth($gibbonSchoolYearID)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID];
        $sql = "SELECT
                YEAR(gibbonEnhancedFinanceExpense.expenseDate) AS expenseYear,
                MONTH(gibbonEnhancedFinanceExpense.expenseDate) AS expenseMonth,
                COUNT(*) AS expenseCount,
                COALESCE(SUM(gibbonEnhancedFinanceExpense.amount), 0) AS totalAmount,
                COALESCE(SUM(gibbonEnhancedFinanceExpense.taxAmount), 0) AS totalTax,
                COALESCE(SUM(gibbonEnhancedFinanceExpense.totalAmount), 0) AS totalWithTax
            FROM gibbonEnhancedFinanceExpense
            WHERE gibbonEnhancedFinanceExpense.gibbonSchoolYearID = :gibbonSchoolYearID
            AND gibbonEnhancedFinanceExpense.status != 'Rejected'
            GROUP BY expenseYear, expenseMonth
            ORDER BY expenseYear ASC, expenseMonth ASC";

        return $this->db()->select($sql, $data);
    }

    /**
     * Get expense summary by payment method for a school year.
     *
     * @param int $gibbonSchoolYearID
     * @return Result
     */
    public function selectExpenseSummaryByPaymentMethod($gibbonSchoolYearID)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID];
        $sql = "SELECT
                gibbonEnhancedFinanceExpense.paymentMethod,
                COUNT(*) AS expenseCount,
                COALESCE(SUM(gibbonEnhancedFinanceExpense.totalAmount), 0) AS totalAmount
            FROM gibbonEnhancedFinanceExpense
            WHERE gibbonEnhancedFinanceExpense.gibbonSchoolYearID = :gibbonSchoolYearID
            AND gibbonEnhancedFinanceExpense.status != 'Rejected'
            GROUP BY gibbonEnhancedFinanceExpense.paymentMethod
            ORDER BY totalAmount DESC";

        return $this->db()->select($sql, $data);
    }

    /**
     * Get expense summary by vendor for a school year.
     *
     * @param int $gibbonSchoolYearID
     * @param int $limit Number of top vendors to return
     * @return Result
     */
    public function selectExpenseSummaryByVendor($gibbonSchoolYearID, $limit = 10)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID];
        $sql = "SELECT
                gibbonEnhancedFinanceExpense.vendor,
                COUNT(*) AS expenseCount,
                COALESCE(SUM(gibbonEnhancedFinanceExpense.totalAmount), 0) AS totalAmount
            FROM gibbonEnhancedFinanceExpense
            WHERE gibbonEnhancedFinanceExpense.gibbonSchoolYearID = :gibbonSchoolYearID
            AND gibbonEnhancedFinanceExpense.status != 'Rejected'
            AND gibbonEnhancedFinanceExpense.vendor IS NOT NULL
            AND gibbonEnhancedFinanceExpense.vendor != ''
            GROUP BY gibbonEnhancedFinanceExpense.vendor
            ORDER BY totalAmount DESC
            LIMIT " . (int) $limit;

        return $this->db()->select($sql, $data);
    }

    /**
     * Get total expense statistics for a school year.
     *
     * @param int $gibbonSchoolYearID
     * @return array
     */
    public function selectExpenseTotalsByYear($gibbonSchoolYearID)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID];
        $sql = "SELECT
                COUNT(*) AS totalExpenses,
                COALESCE(SUM(amount), 0) AS totalAmount,
                COALESCE(SUM(taxAmount), 0) AS totalTax,
                COALESCE(SUM(totalAmount), 0) AS totalWithTax,
                SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) AS pendingCount,
                SUM(CASE WHEN status = 'Pending' THEN totalAmount ELSE 0 END) AS pendingAmount,
                SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) AS approvedCount,
                SUM(CASE WHEN status = 'Approved' THEN totalAmount ELSE 0 END) AS approvedAmount,
                SUM(CASE WHEN status = 'Paid' THEN 1 ELSE 0 END) AS paidCount,
                SUM(CASE WHEN status = 'Paid' THEN totalAmount ELSE 0 END) AS paidAmount,
                SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) AS rejectedCount
            FROM gibbonEnhancedFinanceExpense
            WHERE gibbonSchoolYearID = :gibbonSchoolYearID";

        return $this->db()->selectOne($sql, $data);
    }

    /**
     * Get recent expenses for dashboard display.
     *
     * @param int $gibbonSchoolYearID
     * @param int $limit Number of expenses to return
     * @return Result
     */
    public function selectRecentExpenses($gibbonSchoolYearID, $limit = 10)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID];
        $sql = "SELECT
                gibbonEnhancedFinanceExpense.*,
                gibbonEnhancedFinanceExpenseCategory.name AS categoryName,
                createdBy.surname AS createdBySurname,
                createdBy.preferredName AS createdByPreferredName
            FROM gibbonEnhancedFinanceExpense
            INNER JOIN gibbonEnhancedFinanceExpenseCategory ON gibbonEnhancedFinanceExpense.gibbonEnhancedFinanceExpenseCategoryID = gibbonEnhancedFinanceExpenseCategory.gibbonEnhancedFinanceExpenseCategoryID
            LEFT JOIN gibbonPerson AS createdBy ON gibbonEnhancedFinanceExpense.createdByID = createdBy.gibbonPersonID
            WHERE gibbonEnhancedFinanceExpense.gibbonSchoolYearID = :gibbonSchoolYearID
            ORDER BY gibbonEnhancedFinanceExpense.expenseDate DESC, gibbonEnhancedFinanceExpense.timestampCreated DESC
            LIMIT " . (int) $limit;

        return $this->db()->select($sql, $data);
    }

    /**
     * Get distinct vendor names for autocomplete.
     *
     * @param int $gibbonSchoolYearID Optional school year filter
     * @return Result
     */
    public function selectDistinctVendors($gibbonSchoolYearID = null)
    {
        $data = [];
        $sql = "SELECT DISTINCT vendor
            FROM gibbonEnhancedFinanceExpense
            WHERE vendor IS NOT NULL AND vendor != ''";

        if ($gibbonSchoolYearID !== null) {
            $sql .= " AND gibbonSchoolYearID = :gibbonSchoolYearID";
            $data['gibbonSchoolYearID'] = $gibbonSchoolYearID;
        }

        $sql .= " ORDER BY vendor ASC";

        return $this->db()->select($sql, $data);
    }

    /**
     * Insert a new expense and return the ID.
     *
     * @param array $data Expense data
     * @return int|false
     */
    public function insertExpense(array $data)
    {
        // Ensure required fields are present
        if (empty($data['gibbonEnhancedFinanceExpenseCategoryID']) ||
            empty($data['gibbonSchoolYearID']) ||
            empty($data['expenseDate']) ||
            !isset($data['amount'])) {
            return false;
        }

        // Calculate totalAmount if not provided
        if (!isset($data['totalAmount'])) {
            $data['totalAmount'] = ($data['amount'] ?? 0) + ($data['taxAmount'] ?? 0);
        }

        // Set defaults
        if (empty($data['status'])) {
            $data['status'] = 'Pending';
        }
        if (empty($data['paymentMethod'])) {
            $data['paymentMethod'] = 'Other';
        }

        return $this->insert($data);
    }

    /**
     * Update expense status (for approval workflow).
     *
     * @param int $gibbonEnhancedFinanceExpenseID
     * @param string $status New status
     * @param int|null $approvedByID Staff ID who approved (if applicable)
     * @return bool
     */
    public function updateExpenseStatus($gibbonEnhancedFinanceExpenseID, $status, $approvedByID = null)
    {
        $data = ['status' => $status];

        if (in_array($status, ['Approved', 'Rejected']) && $approvedByID !== null) {
            $data['approvedByID'] = $approvedByID;
            $data['approvedAt'] = date('Y-m-d H:i:s');
        }

        return $this->update($gibbonEnhancedFinanceExpenseID, $data);
    }

    /**
     * Get filter rules for expense queries.
     *
     * @return array
     */
    protected function getFilterRules()
    {
        return [
            'status' => function ($query, $status) {
                return $query
                    ->where('gibbonEnhancedFinanceExpense.status = :status')
                    ->bindValue('status', $status);
            },

            'category' => function ($query, $gibbonEnhancedFinanceExpenseCategoryID) {
                return $query
                    ->where('gibbonEnhancedFinanceExpense.gibbonEnhancedFinanceExpenseCategoryID = :filterCategoryID')
                    ->bindValue('filterCategoryID', $gibbonEnhancedFinanceExpenseCategoryID);
            },

            'vendor' => function ($query, $vendor) {
                return $query
                    ->where('gibbonEnhancedFinanceExpense.vendor LIKE :filterVendor')
                    ->bindValue('filterVendor', '%' . $vendor . '%');
            },

            'paymentMethod' => function ($query, $paymentMethod) {
                return $query
                    ->where('gibbonEnhancedFinanceExpense.paymentMethod = :paymentMethod')
                    ->bindValue('paymentMethod', $paymentMethod);
            },

            'dateFrom' => function ($query, $dateFrom) {
                return $query
                    ->where('gibbonEnhancedFinanceExpense.expenseDate >= :dateFrom')
                    ->bindValue('dateFrom', $dateFrom);
            },

            'dateTo' => function ($query, $dateTo) {
                return $query
                    ->where('gibbonEnhancedFinanceExpense.expenseDate <= :dateTo')
                    ->bindValue('dateTo', $dateTo);
            },

            'amountMin' => function ($query, $amountMin) {
                return $query
                    ->where('gibbonEnhancedFinanceExpense.totalAmount >= :amountMin')
                    ->bindValue('amountMin', $amountMin);
            },

            'amountMax' => function ($query, $amountMax) {
                return $query
                    ->where('gibbonEnhancedFinanceExpense.totalAmount <= :amountMax')
                    ->bindValue('amountMax', $amountMax);
            },

            'month' => function ($query, $month) {
                return $query
                    ->where('MONTH(gibbonEnhancedFinanceExpense.expenseDate) = :month')
                    ->bindValue('month', $month);
            },

            'year' => function ($query, $year) {
                return $query
                    ->where('YEAR(gibbonEnhancedFinanceExpense.expenseDate) = :year')
                    ->bindValue('year', $year);
            },

            'hasReceipt' => function ($query, $hasReceipt) {
                if ($hasReceipt === 'Y') {
                    return $query->where("gibbonEnhancedFinanceExpense.receiptPath IS NOT NULL AND gibbonEnhancedFinanceExpense.receiptPath != ''");
                } else {
                    return $query->where("gibbonEnhancedFinanceExpense.receiptPath IS NULL OR gibbonEnhancedFinanceExpense.receiptPath = ''");
                }
            },
        ];
    }
}
