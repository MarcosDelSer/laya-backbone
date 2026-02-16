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
 * Enhanced Finance Invoice Gateway
 *
 * Provides data access methods for the gibbonEnhancedFinanceInvoice table.
 * Handles invoice CRUD operations, queries with pagination, and filtering.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class InvoiceGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonEnhancedFinanceInvoice';
    private static $primaryKey = 'gibbonEnhancedFinanceInvoiceID';

    private static $searchableColumns = ['invoiceNumber', 'notes'];

    /**
     * Query invoices by school year with pagination and filtering.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonSchoolYearID
     * @return DataSet
     */
    public function queryInvoicesByYear(QueryCriteria $criteria, $gibbonSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonEnhancedFinanceInvoice.gibbonEnhancedFinanceInvoiceID',
                'gibbonEnhancedFinanceInvoice.gibbonPersonID',
                'gibbonEnhancedFinanceInvoice.gibbonFamilyID',
                'gibbonEnhancedFinanceInvoice.gibbonSchoolYearID',
                'gibbonEnhancedFinanceInvoice.invoiceNumber',
                'gibbonEnhancedFinanceInvoice.invoiceDate',
                'gibbonEnhancedFinanceInvoice.dueDate',
                'gibbonEnhancedFinanceInvoice.subtotal',
                'gibbonEnhancedFinanceInvoice.taxAmount',
                'gibbonEnhancedFinanceInvoice.totalAmount',
                'gibbonEnhancedFinanceInvoice.paidAmount',
                'gibbonEnhancedFinanceInvoice.status',
                'gibbonEnhancedFinanceInvoice.notes',
                'gibbonEnhancedFinanceInvoice.createdByID',
                'gibbonEnhancedFinanceInvoice.timestampCreated',
                'gibbonPerson.surname AS childSurname',
                'gibbonPerson.preferredName AS childPreferredName',
                'gibbonFamily.name AS familyName',
                "(gibbonEnhancedFinanceInvoice.totalAmount - gibbonEnhancedFinanceInvoice.paidAmount) AS balanceRemaining",
                "FIND_IN_SET(gibbonEnhancedFinanceInvoice.status, 'Pending,Issued,Partial,Paid,Cancelled,Refunded') AS defaultSortOrder"
            ])
            ->leftJoin('gibbonPerson', 'gibbonEnhancedFinanceInvoice.gibbonPersonID = gibbonPerson.gibbonPersonID')
            ->leftJoin('gibbonFamily', 'gibbonEnhancedFinanceInvoice.gibbonFamilyID = gibbonFamily.gibbonFamilyID')
            ->where('gibbonEnhancedFinanceInvoice.gibbonSchoolYearID = :gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID);

        $criteria->addFilterRules($this->getFilterRules());

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query invoices by family with pagination and filtering.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonFamilyID
     * @return DataSet
     */
    public function queryInvoicesByFamily(QueryCriteria $criteria, $gibbonFamilyID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonEnhancedFinanceInvoice.gibbonEnhancedFinanceInvoiceID',
                'gibbonEnhancedFinanceInvoice.gibbonPersonID',
                'gibbonEnhancedFinanceInvoice.gibbonFamilyID',
                'gibbonEnhancedFinanceInvoice.gibbonSchoolYearID',
                'gibbonEnhancedFinanceInvoice.invoiceNumber',
                'gibbonEnhancedFinanceInvoice.invoiceDate',
                'gibbonEnhancedFinanceInvoice.dueDate',
                'gibbonEnhancedFinanceInvoice.subtotal',
                'gibbonEnhancedFinanceInvoice.taxAmount',
                'gibbonEnhancedFinanceInvoice.totalAmount',
                'gibbonEnhancedFinanceInvoice.paidAmount',
                'gibbonEnhancedFinanceInvoice.status',
                'gibbonEnhancedFinanceInvoice.notes',
                'gibbonPerson.surname AS childSurname',
                'gibbonPerson.preferredName AS childPreferredName',
                'gibbonFamily.name AS familyName',
                'gibbonSchoolYear.name AS schoolYearName',
                "(gibbonEnhancedFinanceInvoice.totalAmount - gibbonEnhancedFinanceInvoice.paidAmount) AS balanceRemaining"
            ])
            ->leftJoin('gibbonPerson', 'gibbonEnhancedFinanceInvoice.gibbonPersonID = gibbonPerson.gibbonPersonID')
            ->leftJoin('gibbonFamily', 'gibbonEnhancedFinanceInvoice.gibbonFamilyID = gibbonFamily.gibbonFamilyID')
            ->leftJoin('gibbonSchoolYear', 'gibbonEnhancedFinanceInvoice.gibbonSchoolYearID = gibbonSchoolYear.gibbonSchoolYearID')
            ->where('gibbonEnhancedFinanceInvoice.gibbonFamilyID = :gibbonFamilyID')
            ->bindValue('gibbonFamilyID', $gibbonFamilyID);

        $criteria->addFilterRules($this->getFilterRules());

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query invoices by child (person) with pagination and filtering.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonPersonID
     * @return DataSet
     */
    public function queryInvoicesByChild(QueryCriteria $criteria, $gibbonPersonID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonEnhancedFinanceInvoice.gibbonEnhancedFinanceInvoiceID',
                'gibbonEnhancedFinanceInvoice.gibbonPersonID',
                'gibbonEnhancedFinanceInvoice.gibbonFamilyID',
                'gibbonEnhancedFinanceInvoice.gibbonSchoolYearID',
                'gibbonEnhancedFinanceInvoice.invoiceNumber',
                'gibbonEnhancedFinanceInvoice.invoiceDate',
                'gibbonEnhancedFinanceInvoice.dueDate',
                'gibbonEnhancedFinanceInvoice.subtotal',
                'gibbonEnhancedFinanceInvoice.taxAmount',
                'gibbonEnhancedFinanceInvoice.totalAmount',
                'gibbonEnhancedFinanceInvoice.paidAmount',
                'gibbonEnhancedFinanceInvoice.status',
                'gibbonEnhancedFinanceInvoice.notes',
                'gibbonFamily.name AS familyName',
                'gibbonSchoolYear.name AS schoolYearName',
                "(gibbonEnhancedFinanceInvoice.totalAmount - gibbonEnhancedFinanceInvoice.paidAmount) AS balanceRemaining"
            ])
            ->leftJoin('gibbonFamily', 'gibbonEnhancedFinanceInvoice.gibbonFamilyID = gibbonFamily.gibbonFamilyID')
            ->leftJoin('gibbonSchoolYear', 'gibbonEnhancedFinanceInvoice.gibbonSchoolYearID = gibbonSchoolYear.gibbonSchoolYearID')
            ->where('gibbonEnhancedFinanceInvoice.gibbonPersonID = :gibbonPersonID')
            ->bindValue('gibbonPersonID', $gibbonPersonID);

        $criteria->addFilterRules($this->getFilterRules());

        return $this->runQuery($query, $criteria);
    }

    /**
     * Select invoice by ID with full details including child and family information.
     *
     * @param int $gibbonEnhancedFinanceInvoiceID
     * @return array
     */
    public function selectInvoiceByID($gibbonEnhancedFinanceInvoiceID)
    {
        $data = ['gibbonEnhancedFinanceInvoiceID' => $gibbonEnhancedFinanceInvoiceID];
        $sql = "SELECT
                gibbonEnhancedFinanceInvoice.*,
                gibbonPerson.surname AS childSurname,
                gibbonPerson.preferredName AS childPreferredName,
                gibbonPerson.dob AS childDOB,
                gibbonFamily.name AS familyName,
                gibbonSchoolYear.name AS schoolYearName,
                createdBy.surname AS createdBySurname,
                createdBy.preferredName AS createdByPreferredName,
                (gibbonEnhancedFinanceInvoice.totalAmount - gibbonEnhancedFinanceInvoice.paidAmount) AS balanceRemaining
            FROM gibbonEnhancedFinanceInvoice
            LEFT JOIN gibbonPerson ON gibbonEnhancedFinanceInvoice.gibbonPersonID = gibbonPerson.gibbonPersonID
            LEFT JOIN gibbonFamily ON gibbonEnhancedFinanceInvoice.gibbonFamilyID = gibbonFamily.gibbonFamilyID
            LEFT JOIN gibbonSchoolYear ON gibbonEnhancedFinanceInvoice.gibbonSchoolYearID = gibbonSchoolYear.gibbonSchoolYearID
            LEFT JOIN gibbonPerson AS createdBy ON gibbonEnhancedFinanceInvoice.createdByID = createdBy.gibbonPersonID
            WHERE gibbonEnhancedFinanceInvoice.gibbonEnhancedFinanceInvoiceID = :gibbonEnhancedFinanceInvoiceID";

        return $this->db()->selectOne($sql, $data);
    }

    /**
     * Select outstanding invoices (not fully paid) for a family.
     *
     * @param int $gibbonFamilyID
     * @return Result
     */
    public function selectOutstandingByFamily($gibbonFamilyID)
    {
        $data = ['gibbonFamilyID' => $gibbonFamilyID];
        $sql = "SELECT
                gibbonEnhancedFinanceInvoice.*,
                gibbonPerson.surname AS childSurname,
                gibbonPerson.preferredName AS childPreferredName,
                (gibbonEnhancedFinanceInvoice.totalAmount - gibbonEnhancedFinanceInvoice.paidAmount) AS balanceRemaining
            FROM gibbonEnhancedFinanceInvoice
            LEFT JOIN gibbonPerson ON gibbonEnhancedFinanceInvoice.gibbonPersonID = gibbonPerson.gibbonPersonID
            WHERE gibbonEnhancedFinanceInvoice.gibbonFamilyID = :gibbonFamilyID
            AND gibbonEnhancedFinanceInvoice.status IN ('Issued', 'Partial')
            ORDER BY gibbonEnhancedFinanceInvoice.dueDate ASC";

        return $this->db()->select($sql, $data);
    }

    /**
     * Select overdue invoices for a school year.
     *
     * @param int $gibbonSchoolYearID
     * @return Result
     */
    public function selectOverdueByYear($gibbonSchoolYearID)
    {
        $data = [
            'gibbonSchoolYearID' => $gibbonSchoolYearID,
            'today' => date('Y-m-d')
        ];
        $sql = "SELECT
                gibbonEnhancedFinanceInvoice.*,
                gibbonPerson.surname AS childSurname,
                gibbonPerson.preferredName AS childPreferredName,
                gibbonFamily.name AS familyName,
                (gibbonEnhancedFinanceInvoice.totalAmount - gibbonEnhancedFinanceInvoice.paidAmount) AS balanceRemaining,
                DATEDIFF(:today, gibbonEnhancedFinanceInvoice.dueDate) AS daysOverdue
            FROM gibbonEnhancedFinanceInvoice
            LEFT JOIN gibbonPerson ON gibbonEnhancedFinanceInvoice.gibbonPersonID = gibbonPerson.gibbonPersonID
            LEFT JOIN gibbonFamily ON gibbonEnhancedFinanceInvoice.gibbonFamilyID = gibbonFamily.gibbonFamilyID
            WHERE gibbonEnhancedFinanceInvoice.gibbonSchoolYearID = :gibbonSchoolYearID
            AND gibbonEnhancedFinanceInvoice.status IN ('Issued', 'Partial')
            AND gibbonEnhancedFinanceInvoice.dueDate < :today
            ORDER BY gibbonEnhancedFinanceInvoice.dueDate ASC";

        return $this->db()->select($sql, $data);
    }

    /**
     * Get financial summary for a school year.
     *
     * @param int $gibbonSchoolYearID
     * @return array
     */
    public function selectFinancialSummaryByYear($gibbonSchoolYearID)
    {
        $data = [
            'gibbonSchoolYearID' => $gibbonSchoolYearID,
            'today' => date('Y-m-d')
        ];
        $sql = "SELECT
                COUNT(*) AS totalInvoices,
                SUM(totalAmount) AS totalInvoiced,
                SUM(paidAmount) AS totalPaid,
                SUM(totalAmount - paidAmount) AS totalOutstanding,
                SUM(CASE WHEN status = 'Paid' THEN 1 ELSE 0 END) AS paidCount,
                SUM(CASE WHEN status IN ('Issued', 'Partial') THEN 1 ELSE 0 END) AS outstandingCount,
                SUM(CASE WHEN status IN ('Issued', 'Partial') AND dueDate < :today THEN 1 ELSE 0 END) AS overdueCount,
                SUM(CASE WHEN status IN ('Issued', 'Partial') AND dueDate < :today THEN (totalAmount - paidAmount) ELSE 0 END) AS overdueAmount
            FROM gibbonEnhancedFinanceInvoice
            WHERE gibbonSchoolYearID = :gibbonSchoolYearID
            AND status NOT IN ('Cancelled', 'Refunded')";

        return $this->db()->selectOne($sql, $data);
    }

    /**
     * Get total paid amount for a child in a tax year (for RL-24).
     *
     * @param int $gibbonPersonID Child's person ID
     * @param int $taxYear Tax year (YYYY format)
     * @return array
     */
    public function selectTotalPaidByChildAndYear($gibbonPersonID, $taxYear)
    {
        $data = [
            'gibbonPersonID' => $gibbonPersonID,
            'startDate' => $taxYear . '-01-01',
            'endDate' => $taxYear . '-12-31'
        ];
        $sql = "SELECT
                SUM(p.amount) AS totalPaid,
                COUNT(DISTINCT i.gibbonEnhancedFinanceInvoiceID) AS invoiceCount
            FROM gibbonEnhancedFinancePayment p
            INNER JOIN gibbonEnhancedFinanceInvoice i ON p.gibbonEnhancedFinanceInvoiceID = i.gibbonEnhancedFinanceInvoiceID
            WHERE i.gibbonPersonID = :gibbonPersonID
            AND p.paymentDate BETWEEN :startDate AND :endDate";

        return $this->db()->selectOne($sql, $data);
    }

    /**
     * Update invoice paid amount from payments table.
     *
     * @param int $gibbonEnhancedFinanceInvoiceID
     * @return bool
     */
    public function updatePaidAmount($gibbonEnhancedFinanceInvoiceID)
    {
        $data = ['gibbonEnhancedFinanceInvoiceID' => $gibbonEnhancedFinanceInvoiceID];

        // Get total payments for this invoice
        $sql = "SELECT COALESCE(SUM(amount), 0) AS totalPaid
                FROM gibbonEnhancedFinancePayment
                WHERE gibbonEnhancedFinanceInvoiceID = :gibbonEnhancedFinanceInvoiceID";

        $result = $this->db()->selectOne($sql, $data);
        $totalPaid = $result['totalPaid'] ?? 0;

        // Get invoice total
        $invoice = $this->getByID($gibbonEnhancedFinanceInvoiceID, ['totalAmount', 'status']);
        if (empty($invoice)) {
            return false;
        }

        $totalAmount = $invoice['totalAmount'];

        // Determine new status
        $newStatus = 'Issued';
        if ($totalPaid >= $totalAmount) {
            $newStatus = 'Paid';
        } elseif ($totalPaid > 0) {
            $newStatus = 'Partial';
        }

        // Only update if status was not Cancelled or Refunded
        if (in_array($invoice['status'], ['Cancelled', 'Refunded'])) {
            return false;
        }

        return $this->update($gibbonEnhancedFinanceInvoiceID, [
            'paidAmount' => $totalPaid,
            'status' => $newStatus
        ]);
    }

    /**
     * Generate next invoice number.
     *
     * @param string $prefix Invoice number prefix
     * @param int $gibbonSchoolYearID
     * @return string
     */
    public function generateInvoiceNumber($prefix, $gibbonSchoolYearID)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID];
        $sql = "SELECT MAX(CAST(SUBSTRING(invoiceNumber, LENGTH(:prefix) + 1) AS UNSIGNED)) AS maxNum
                FROM gibbonEnhancedFinanceInvoice
                WHERE gibbonSchoolYearID = :gibbonSchoolYearID
                AND invoiceNumber LIKE CONCAT(:prefix, '%')";
        $data['prefix'] = $prefix;

        $result = $this->db()->selectOne($sql, $data);
        $nextNum = ($result['maxNum'] ?? 0) + 1;

        return $prefix . str_pad($nextNum, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Select outstanding balances grouped by family for a school year.
     *
     * @param int $gibbonSchoolYearID
     * @param int $limit Maximum number of families to return
     * @return Result
     */
    public function selectOutstandingByFamily($gibbonSchoolYearID, $limit = 10)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID];
        $sql = "SELECT
                gibbonFamily.gibbonFamilyID,
                gibbonFamily.name AS familyName,
                COUNT(gibbonEnhancedFinanceInvoice.gibbonEnhancedFinanceInvoiceID) AS invoiceCount,
                SUM(gibbonEnhancedFinanceInvoice.totalAmount - gibbonEnhancedFinanceInvoice.paidAmount) AS totalOutstanding,
                MIN(gibbonEnhancedFinanceInvoice.dueDate) AS oldestDueDate,
                MAX(CASE WHEN gibbonEnhancedFinanceInvoice.dueDate < CURDATE() THEN 1 ELSE 0 END) AS hasOverdue,
                SUM(CASE WHEN gibbonEnhancedFinanceInvoice.dueDate < CURDATE() THEN (gibbonEnhancedFinanceInvoice.totalAmount - gibbonEnhancedFinanceInvoice.paidAmount) ELSE 0 END) AS overdueAmount
            FROM gibbonEnhancedFinanceInvoice
            INNER JOIN gibbonFamily ON gibbonEnhancedFinanceInvoice.gibbonFamilyID = gibbonFamily.gibbonFamilyID
            WHERE gibbonEnhancedFinanceInvoice.gibbonSchoolYearID = :gibbonSchoolYearID
            AND gibbonEnhancedFinanceInvoice.status IN ('Issued', 'Partial')
            GROUP BY gibbonFamily.gibbonFamilyID, gibbonFamily.name
            HAVING totalOutstanding > 0
            ORDER BY totalOutstanding DESC
            LIMIT " . (int) $limit;

        return $this->db()->select($sql, $data);
    }

    /**
     * Get summary of outstanding balances by family for a school year.
     *
     * @param int $gibbonSchoolYearID
     * @return array
     */
    public function selectOutstandingByFamilySummary($gibbonSchoolYearID)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID];
        $sql = "SELECT
                COUNT(DISTINCT gibbonEnhancedFinanceInvoice.gibbonFamilyID) AS familyCount,
                COUNT(gibbonEnhancedFinanceInvoice.gibbonEnhancedFinanceInvoiceID) AS invoiceCount,
                SUM(gibbonEnhancedFinanceInvoice.totalAmount - gibbonEnhancedFinanceInvoice.paidAmount) AS totalOutstanding
            FROM gibbonEnhancedFinanceInvoice
            WHERE gibbonEnhancedFinanceInvoice.gibbonSchoolYearID = :gibbonSchoolYearID
            AND gibbonEnhancedFinanceInvoice.status IN ('Issued', 'Partial')
            AND (gibbonEnhancedFinanceInvoice.totalAmount - gibbonEnhancedFinanceInvoice.paidAmount) > 0";

        return $this->db()->selectOne($sql, $data);
    }

    /**
     * Get filter rules for invoice queries.
     *
     * @return array
     */
    protected function getFilterRules()
    {
        return [
            'status' => function ($query, $status) {
                switch ($status) {
                    case 'Overdue':
                        return $query
                            ->where("gibbonEnhancedFinanceInvoice.status IN ('Issued', 'Partial')")
                            ->where('gibbonEnhancedFinanceInvoice.dueDate < :today')
                            ->bindValue('today', date('Y-m-d'));

                    case 'Outstanding':
                        return $query
                            ->where("gibbonEnhancedFinanceInvoice.status IN ('Issued', 'Partial')");

                    default:
                        return $query
                            ->where('gibbonEnhancedFinanceInvoice.status = :status')
                            ->bindValue('status', $status);
                }
            },

            'family' => function ($query, $gibbonFamilyID) {
                return $query
                    ->where('gibbonEnhancedFinanceInvoice.gibbonFamilyID = :filterFamilyID')
                    ->bindValue('filterFamilyID', $gibbonFamilyID);
            },

            'child' => function ($query, $gibbonPersonID) {
                return $query
                    ->where('gibbonEnhancedFinanceInvoice.gibbonPersonID = :filterPersonID')
                    ->bindValue('filterPersonID', $gibbonPersonID);
            },

            'dateFrom' => function ($query, $dateFrom) {
                return $query
                    ->where('gibbonEnhancedFinanceInvoice.invoiceDate >= :dateFrom')
                    ->bindValue('dateFrom', $dateFrom);
            },

            'dateTo' => function ($query, $dateTo) {
                return $query
                    ->where('gibbonEnhancedFinanceInvoice.invoiceDate <= :dateTo')
                    ->bindValue('dateTo', $dateTo);
            },

            'dueDateFrom' => function ($query, $dueDateFrom) {
                return $query
                    ->where('gibbonEnhancedFinanceInvoice.dueDate >= :dueDateFrom')
                    ->bindValue('dueDateFrom', $dueDateFrom);
            },

            'dueDateTo' => function ($query, $dueDateTo) {
                return $query
                    ->where('gibbonEnhancedFinanceInvoice.dueDate <= :dueDateTo')
                    ->bindValue('dueDateTo', $dueDateTo);
            },

            'month' => function ($query, $month) {
                return $query
                    ->where('MONTH(gibbonEnhancedFinanceInvoice.invoiceDate) = :month')
                    ->bindValue('month', $month);
            },
        ];
    }
}
