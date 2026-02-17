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
 * Enhanced Finance Payment Gateway
 *
 * Provides data access methods for the gibbonEnhancedFinancePayment table.
 * Handles payment recording, queries with pagination, filtering, and history.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class PaymentGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonEnhancedFinancePayment';
    private static $primaryKey = 'gibbonEnhancedFinancePaymentID';

    private static $searchableColumns = ['reference', 'notes'];

    /**
     * Query payments by invoice with pagination and filtering.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonEnhancedFinanceInvoiceID
     * @return DataSet
     */
    public function queryPaymentsByInvoice(QueryCriteria $criteria, $gibbonEnhancedFinanceInvoiceID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonEnhancedFinancePayment.gibbonEnhancedFinancePaymentID',
                'gibbonEnhancedFinancePayment.gibbonEnhancedFinanceInvoiceID',
                'gibbonEnhancedFinancePayment.paymentDate',
                'gibbonEnhancedFinancePayment.amount',
                'gibbonEnhancedFinancePayment.method',
                'gibbonEnhancedFinancePayment.reference',
                'gibbonEnhancedFinancePayment.notes',
                'gibbonEnhancedFinancePayment.recordedByID',
                'gibbonEnhancedFinancePayment.timestampCreated',
                'recordedBy.surname AS recordedBySurname',
                'recordedBy.preferredName AS recordedByPreferredName'
            ])
            ->leftJoin('gibbonPerson AS recordedBy', 'gibbonEnhancedFinancePayment.recordedByID = recordedBy.gibbonPersonID')
            ->where('gibbonEnhancedFinancePayment.gibbonEnhancedFinanceInvoiceID = :gibbonEnhancedFinanceInvoiceID')
            ->bindValue('gibbonEnhancedFinanceInvoiceID', $gibbonEnhancedFinanceInvoiceID);

        $criteria->addFilterRules($this->getFilterRules());

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query payments by school year with pagination and filtering.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonSchoolYearID
     * @return DataSet
     */
    public function queryPaymentsByYear(QueryCriteria $criteria, $gibbonSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonEnhancedFinancePayment.gibbonEnhancedFinancePaymentID',
                'gibbonEnhancedFinancePayment.gibbonEnhancedFinanceInvoiceID',
                'gibbonEnhancedFinancePayment.paymentDate',
                'gibbonEnhancedFinancePayment.amount',
                'gibbonEnhancedFinancePayment.method',
                'gibbonEnhancedFinancePayment.reference',
                'gibbonEnhancedFinancePayment.notes',
                'gibbonEnhancedFinancePayment.recordedByID',
                'gibbonEnhancedFinancePayment.timestampCreated',
                'gibbonEnhancedFinanceInvoice.invoiceNumber',
                'gibbonEnhancedFinanceInvoice.gibbonPersonID',
                'gibbonEnhancedFinanceInvoice.gibbonFamilyID',
                'gibbonPerson.surname AS childSurname',
                'gibbonPerson.preferredName AS childPreferredName',
                'gibbonFamily.name AS familyName',
                'recordedBy.surname AS recordedBySurname',
                'recordedBy.preferredName AS recordedByPreferredName'
            ])
            ->innerJoin('gibbonEnhancedFinanceInvoice', 'gibbonEnhancedFinancePayment.gibbonEnhancedFinanceInvoiceID = gibbonEnhancedFinanceInvoice.gibbonEnhancedFinanceInvoiceID')
            ->leftJoin('gibbonPerson', 'gibbonEnhancedFinanceInvoice.gibbonPersonID = gibbonPerson.gibbonPersonID')
            ->leftJoin('gibbonFamily', 'gibbonEnhancedFinanceInvoice.gibbonFamilyID = gibbonFamily.gibbonFamilyID')
            ->leftJoin('gibbonPerson AS recordedBy', 'gibbonEnhancedFinancePayment.recordedByID = recordedBy.gibbonPersonID')
            ->where('gibbonEnhancedFinanceInvoice.gibbonSchoolYearID = :gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID);

        $criteria->addFilterRules($this->getFilterRules());

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query payments by family with pagination and filtering.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonFamilyID
     * @return DataSet
     */
    public function queryPaymentsByFamily(QueryCriteria $criteria, $gibbonFamilyID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonEnhancedFinancePayment.gibbonEnhancedFinancePaymentID',
                'gibbonEnhancedFinancePayment.gibbonEnhancedFinanceInvoiceID',
                'gibbonEnhancedFinancePayment.paymentDate',
                'gibbonEnhancedFinancePayment.amount',
                'gibbonEnhancedFinancePayment.method',
                'gibbonEnhancedFinancePayment.reference',
                'gibbonEnhancedFinancePayment.notes',
                'gibbonEnhancedFinancePayment.timestampCreated',
                'gibbonEnhancedFinanceInvoice.invoiceNumber',
                'gibbonEnhancedFinanceInvoice.gibbonPersonID',
                'gibbonPerson.surname AS childSurname',
                'gibbonPerson.preferredName AS childPreferredName',
                'gibbonSchoolYear.name AS schoolYearName',
                'recordedBy.surname AS recordedBySurname',
                'recordedBy.preferredName AS recordedByPreferredName'
            ])
            ->innerJoin('gibbonEnhancedFinanceInvoice', 'gibbonEnhancedFinancePayment.gibbonEnhancedFinanceInvoiceID = gibbonEnhancedFinanceInvoice.gibbonEnhancedFinanceInvoiceID')
            ->leftJoin('gibbonPerson', 'gibbonEnhancedFinanceInvoice.gibbonPersonID = gibbonPerson.gibbonPersonID')
            ->leftJoin('gibbonSchoolYear', 'gibbonEnhancedFinanceInvoice.gibbonSchoolYearID = gibbonSchoolYear.gibbonSchoolYearID')
            ->leftJoin('gibbonPerson AS recordedBy', 'gibbonEnhancedFinancePayment.recordedByID = recordedBy.gibbonPersonID')
            ->where('gibbonEnhancedFinanceInvoice.gibbonFamilyID = :gibbonFamilyID')
            ->bindValue('gibbonFamilyID', $gibbonFamilyID);

        $criteria->addFilterRules($this->getFilterRules());

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query payments by child (person) with pagination and filtering.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonPersonID
     * @return DataSet
     */
    public function queryPaymentsByChild(QueryCriteria $criteria, $gibbonPersonID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonEnhancedFinancePayment.gibbonEnhancedFinancePaymentID',
                'gibbonEnhancedFinancePayment.gibbonEnhancedFinanceInvoiceID',
                'gibbonEnhancedFinancePayment.paymentDate',
                'gibbonEnhancedFinancePayment.amount',
                'gibbonEnhancedFinancePayment.method',
                'gibbonEnhancedFinancePayment.reference',
                'gibbonEnhancedFinancePayment.notes',
                'gibbonEnhancedFinancePayment.timestampCreated',
                'gibbonEnhancedFinanceInvoice.invoiceNumber',
                'gibbonSchoolYear.name AS schoolYearName',
                'recordedBy.surname AS recordedBySurname',
                'recordedBy.preferredName AS recordedByPreferredName'
            ])
            ->innerJoin('gibbonEnhancedFinanceInvoice', 'gibbonEnhancedFinancePayment.gibbonEnhancedFinanceInvoiceID = gibbonEnhancedFinanceInvoice.gibbonEnhancedFinanceInvoiceID')
            ->leftJoin('gibbonSchoolYear', 'gibbonEnhancedFinanceInvoice.gibbonSchoolYearID = gibbonSchoolYear.gibbonSchoolYearID')
            ->leftJoin('gibbonPerson AS recordedBy', 'gibbonEnhancedFinancePayment.recordedByID = recordedBy.gibbonPersonID')
            ->where('gibbonEnhancedFinanceInvoice.gibbonPersonID = :gibbonPersonID')
            ->bindValue('gibbonPersonID', $gibbonPersonID);

        $criteria->addFilterRules($this->getFilterRules());

        return $this->runQuery($query, $criteria);
    }

    /**
     * Select payment by ID with full details including invoice and person information.
     *
     * @param int $gibbonEnhancedFinancePaymentID
     * @return array
     */
    public function selectPaymentByID($gibbonEnhancedFinancePaymentID)
    {
        $data = ['gibbonEnhancedFinancePaymentID' => $gibbonEnhancedFinancePaymentID];
        $sql = "SELECT
                gibbonEnhancedFinancePayment.*,
                gibbonEnhancedFinanceInvoice.invoiceNumber,
                gibbonEnhancedFinanceInvoice.gibbonPersonID,
                gibbonEnhancedFinanceInvoice.gibbonFamilyID,
                gibbonEnhancedFinanceInvoice.totalAmount AS invoiceTotalAmount,
                gibbonEnhancedFinanceInvoice.paidAmount AS invoicePaidAmount,
                gibbonPerson.surname AS childSurname,
                gibbonPerson.preferredName AS childPreferredName,
                gibbonFamily.name AS familyName,
                recordedBy.surname AS recordedBySurname,
                recordedBy.preferredName AS recordedByPreferredName
            FROM gibbonEnhancedFinancePayment
            INNER JOIN gibbonEnhancedFinanceInvoice ON gibbonEnhancedFinancePayment.gibbonEnhancedFinanceInvoiceID = gibbonEnhancedFinanceInvoice.gibbonEnhancedFinanceInvoiceID
            LEFT JOIN gibbonPerson ON gibbonEnhancedFinanceInvoice.gibbonPersonID = gibbonPerson.gibbonPersonID
            LEFT JOIN gibbonFamily ON gibbonEnhancedFinanceInvoice.gibbonFamilyID = gibbonFamily.gibbonFamilyID
            LEFT JOIN gibbonPerson AS recordedBy ON gibbonEnhancedFinancePayment.recordedByID = recordedBy.gibbonPersonID
            WHERE gibbonEnhancedFinancePayment.gibbonEnhancedFinancePaymentID = :gibbonEnhancedFinancePaymentID";

        return $this->db()->selectOne($sql, $data);
    }

    /**
     * Select all payments for an invoice.
     *
     * @param int $gibbonEnhancedFinanceInvoiceID
     * @return Result
     */
    public function selectPaymentsByInvoiceID($gibbonEnhancedFinanceInvoiceID)
    {
        $data = ['gibbonEnhancedFinanceInvoiceID' => $gibbonEnhancedFinanceInvoiceID];
        $sql = "SELECT
                gibbonEnhancedFinancePayment.*,
                recordedBy.surname AS recordedBySurname,
                recordedBy.preferredName AS recordedByPreferredName
            FROM gibbonEnhancedFinancePayment
            LEFT JOIN gibbonPerson AS recordedBy ON gibbonEnhancedFinancePayment.recordedByID = recordedBy.gibbonPersonID
            WHERE gibbonEnhancedFinancePayment.gibbonEnhancedFinanceInvoiceID = :gibbonEnhancedFinanceInvoiceID
            ORDER BY gibbonEnhancedFinancePayment.paymentDate DESC, gibbonEnhancedFinancePayment.timestampCreated DESC";

        return $this->db()->select($sql, $data);
    }

    /**
     * Get total paid amount for an invoice.
     *
     * @param int $gibbonEnhancedFinanceInvoiceID
     * @return float
     */
    public function getTotalPaidForInvoice($gibbonEnhancedFinanceInvoiceID)
    {
        $data = ['gibbonEnhancedFinanceInvoiceID' => $gibbonEnhancedFinanceInvoiceID];
        $sql = "SELECT COALESCE(SUM(amount), 0) AS totalPaid
                FROM gibbonEnhancedFinancePayment
                WHERE gibbonEnhancedFinanceInvoiceID = :gibbonEnhancedFinanceInvoiceID";

        $result = $this->db()->selectOne($sql, $data);
        return (float) ($result['totalPaid'] ?? 0);
    }

    /**
     * Select payments for a child within a date range (for RL-24).
     *
     * @param int $gibbonPersonID Child's person ID
     * @param string $startDate Start date (Y-m-d)
     * @param string $endDate End date (Y-m-d)
     * @return Result
     */
    public function selectPaymentsByChildAndDateRange($gibbonPersonID, $startDate, $endDate)
    {
        $data = [
            'gibbonPersonID' => $gibbonPersonID,
            'startDate' => $startDate,
            'endDate' => $endDate
        ];
        $sql = "SELECT
                gibbonEnhancedFinancePayment.*,
                gibbonEnhancedFinanceInvoice.invoiceNumber,
                gibbonEnhancedFinanceInvoice.gibbonPersonID,
                gibbonEnhancedFinanceInvoice.gibbonFamilyID
            FROM gibbonEnhancedFinancePayment
            INNER JOIN gibbonEnhancedFinanceInvoice ON gibbonEnhancedFinancePayment.gibbonEnhancedFinanceInvoiceID = gibbonEnhancedFinanceInvoice.gibbonEnhancedFinanceInvoiceID
            WHERE gibbonEnhancedFinanceInvoice.gibbonPersonID = :gibbonPersonID
            AND gibbonEnhancedFinancePayment.paymentDate BETWEEN :startDate AND :endDate
            ORDER BY gibbonEnhancedFinancePayment.paymentDate ASC";

        return $this->db()->select($sql, $data);
    }

    /**
     * Get total payments for a child in a tax year (for RL-24).
     *
     * @param int $gibbonPersonID Child's person ID
     * @param int $taxYear Tax year (YYYY format)
     * @return array
     */
    public function selectTotalPaidByChildAndTaxYear($gibbonPersonID, $taxYear)
    {
        $data = [
            'gibbonPersonID' => $gibbonPersonID,
            'startDate' => $taxYear . '-01-01',
            'endDate' => $taxYear . '-12-31'
        ];
        $sql = "SELECT
                SUM(p.amount) AS totalPaid,
                COUNT(p.gibbonEnhancedFinancePaymentID) AS paymentCount,
                COUNT(DISTINCT i.gibbonEnhancedFinanceInvoiceID) AS invoiceCount
            FROM gibbonEnhancedFinancePayment p
            INNER JOIN gibbonEnhancedFinanceInvoice i ON p.gibbonEnhancedFinanceInvoiceID = i.gibbonEnhancedFinanceInvoiceID
            WHERE i.gibbonPersonID = :gibbonPersonID
            AND p.paymentDate BETWEEN :startDate AND :endDate";

        return $this->db()->selectOne($sql, $data);
    }

    /**
     * Get payment summary by method for a school year.
     *
     * @param int $gibbonSchoolYearID
     * @return Result
     */
    public function selectPaymentSummaryByMethod($gibbonSchoolYearID)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID];
        $sql = "SELECT
                gibbonEnhancedFinancePayment.method,
                COUNT(*) AS paymentCount,
                SUM(gibbonEnhancedFinancePayment.amount) AS totalAmount
            FROM gibbonEnhancedFinancePayment
            INNER JOIN gibbonEnhancedFinanceInvoice ON gibbonEnhancedFinancePayment.gibbonEnhancedFinanceInvoiceID = gibbonEnhancedFinanceInvoice.gibbonEnhancedFinanceInvoiceID
            WHERE gibbonEnhancedFinanceInvoice.gibbonSchoolYearID = :gibbonSchoolYearID
            GROUP BY gibbonEnhancedFinancePayment.method
            ORDER BY totalAmount DESC";

        return $this->db()->select($sql, $data);
    }

    /**
     * Get payment summary by month for a school year.
     *
     * @param int $gibbonSchoolYearID
     * @return Result
     */
    public function selectPaymentSummaryByMonth($gibbonSchoolYearID)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID];
        $sql = "SELECT
                YEAR(gibbonEnhancedFinancePayment.paymentDate) AS paymentYear,
                MONTH(gibbonEnhancedFinancePayment.paymentDate) AS paymentMonth,
                COUNT(*) AS paymentCount,
                SUM(gibbonEnhancedFinancePayment.amount) AS totalAmount
            FROM gibbonEnhancedFinancePayment
            INNER JOIN gibbonEnhancedFinanceInvoice ON gibbonEnhancedFinancePayment.gibbonEnhancedFinanceInvoiceID = gibbonEnhancedFinanceInvoice.gibbonEnhancedFinanceInvoiceID
            WHERE gibbonEnhancedFinanceInvoice.gibbonSchoolYearID = :gibbonSchoolYearID
            GROUP BY paymentYear, paymentMonth
            ORDER BY paymentYear ASC, paymentMonth ASC";

        return $this->db()->select($sql, $data);
    }

    /**
     * Get recent payments for dashboard display.
     *
     * @param int $gibbonSchoolYearID
     * @param int $limit Number of payments to return
     * @return Result
     */
    public function selectRecentPayments($gibbonSchoolYearID, $limit = 10)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID];
        $sql = "SELECT
                gibbonEnhancedFinancePayment.*,
                gibbonEnhancedFinanceInvoice.invoiceNumber,
                gibbonPerson.surname AS childSurname,
                gibbonPerson.preferredName AS childPreferredName,
                gibbonFamily.name AS familyName
            FROM gibbonEnhancedFinancePayment
            INNER JOIN gibbonEnhancedFinanceInvoice ON gibbonEnhancedFinancePayment.gibbonEnhancedFinanceInvoiceID = gibbonEnhancedFinanceInvoice.gibbonEnhancedFinanceInvoiceID
            LEFT JOIN gibbonPerson ON gibbonEnhancedFinanceInvoice.gibbonPersonID = gibbonPerson.gibbonPersonID
            LEFT JOIN gibbonFamily ON gibbonEnhancedFinanceInvoice.gibbonFamilyID = gibbonFamily.gibbonFamilyID
            WHERE gibbonEnhancedFinanceInvoice.gibbonSchoolYearID = :gibbonSchoolYearID
            ORDER BY gibbonEnhancedFinancePayment.paymentDate DESC, gibbonEnhancedFinancePayment.timestampCreated DESC
            LIMIT " . (int) $limit;

        return $this->db()->select($sql, $data);
    }

    /**
     * Get total payments for a school year.
     *
     * @param int $gibbonSchoolYearID
     * @return array
     */
    public function selectTotalPaymentsByYear($gibbonSchoolYearID)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID];
        $sql = "SELECT
                COUNT(*) AS paymentCount,
                COALESCE(SUM(gibbonEnhancedFinancePayment.amount), 0) AS totalAmount
            FROM gibbonEnhancedFinancePayment
            INNER JOIN gibbonEnhancedFinanceInvoice ON gibbonEnhancedFinancePayment.gibbonEnhancedFinanceInvoiceID = gibbonEnhancedFinanceInvoice.gibbonEnhancedFinanceInvoiceID
            WHERE gibbonEnhancedFinanceInvoice.gibbonSchoolYearID = :gibbonSchoolYearID";

        return $this->db()->selectOne($sql, $data);
    }

    /**
     * Insert a new payment and return the ID.
     *
     * @param array $data Payment data
     * @return int|false
     */
    public function insertPayment(array $data)
    {
        // Ensure required fields are present
        if (empty($data['gibbonEnhancedFinanceInvoiceID']) || empty($data['paymentDate']) || !isset($data['amount'])) {
            return false;
        }

        // Set defaults
        if (empty($data['method'])) {
            $data['method'] = 'Other';
        }

        return $this->insert($data);
    }

    /**
     * Get Year-to-Date (YTD) revenue for a calendar year.
     *
     * @param int $year Calendar year (YYYY format)
     * @return array
     */
    public function selectYTDRevenue($year)
    {
        $data = [
            'startDate' => $year . '-01-01',
            'endDate' => $year . '-12-31'
        ];
        $sql = "SELECT
                COUNT(*) AS paymentCount,
                COALESCE(SUM(gibbonEnhancedFinancePayment.amount), 0) AS totalAmount,
                COUNT(DISTINCT gibbonEnhancedFinanceInvoice.gibbonEnhancedFinanceInvoiceID) AS invoiceCount,
                COUNT(DISTINCT gibbonEnhancedFinanceInvoice.gibbonFamilyID) AS familyCount
            FROM gibbonEnhancedFinancePayment
            INNER JOIN gibbonEnhancedFinanceInvoice ON gibbonEnhancedFinancePayment.gibbonEnhancedFinanceInvoiceID = gibbonEnhancedFinanceInvoice.gibbonEnhancedFinanceInvoiceID
            WHERE gibbonEnhancedFinancePayment.paymentDate BETWEEN :startDate AND :endDate";

        return $this->db()->selectOne($sql, $data);
    }

    /**
     * Get YTD revenue by month for a calendar year.
     *
     * @param int $year Calendar year (YYYY format)
     * @return Result
     */
    public function selectYTDRevenueByMonth($year)
    {
        $data = [
            'startDate' => $year . '-01-01',
            'endDate' => $year . '-12-31'
        ];
        $sql = "SELECT
                MONTH(gibbonEnhancedFinancePayment.paymentDate) AS paymentMonth,
                COUNT(*) AS paymentCount,
                SUM(gibbonEnhancedFinancePayment.amount) AS totalAmount
            FROM gibbonEnhancedFinancePayment
            INNER JOIN gibbonEnhancedFinanceInvoice ON gibbonEnhancedFinancePayment.gibbonEnhancedFinanceInvoiceID = gibbonEnhancedFinanceInvoice.gibbonEnhancedFinanceInvoiceID
            WHERE gibbonEnhancedFinancePayment.paymentDate BETWEEN :startDate AND :endDate
            GROUP BY paymentMonth
            ORDER BY paymentMonth ASC";

        return $this->db()->select($sql, $data);
    }

    /**
     * Get filter rules for payment queries.
     *
     * @return array
     */
    protected function getFilterRules()
    {
        return [
            'method' => function ($query, $method) {
                return $query
                    ->where('gibbonEnhancedFinancePayment.method = :method')
                    ->bindValue('method', $method);
            },

            'invoice' => function ($query, $gibbonEnhancedFinanceInvoiceID) {
                return $query
                    ->where('gibbonEnhancedFinancePayment.gibbonEnhancedFinanceInvoiceID = :filterInvoiceID')
                    ->bindValue('filterInvoiceID', $gibbonEnhancedFinanceInvoiceID);
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
                    ->where('gibbonEnhancedFinancePayment.paymentDate >= :dateFrom')
                    ->bindValue('dateFrom', $dateFrom);
            },

            'dateTo' => function ($query, $dateTo) {
                return $query
                    ->where('gibbonEnhancedFinancePayment.paymentDate <= :dateTo')
                    ->bindValue('dateTo', $dateTo);
            },

            'amountMin' => function ($query, $amountMin) {
                return $query
                    ->where('gibbonEnhancedFinancePayment.amount >= :amountMin')
                    ->bindValue('amountMin', $amountMin);
            },

            'amountMax' => function ($query, $amountMax) {
                return $query
                    ->where('gibbonEnhancedFinancePayment.amount <= :amountMax')
                    ->bindValue('amountMax', $amountMax);
            },

            'month' => function ($query, $month) {
                return $query
                    ->where('MONTH(gibbonEnhancedFinancePayment.paymentDate) = :month')
                    ->bindValue('month', $month);
            },

            'year' => function ($query, $year) {
                return $query
                    ->where('YEAR(gibbonEnhancedFinancePayment.paymentDate) = :year')
                    ->bindValue('year', $year);
            },
        ];
    }
}
