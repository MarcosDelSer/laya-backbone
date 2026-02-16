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

namespace Gibbon\Module\EnhancedFinance\Export;

use Gibbon\Module\EnhancedFinance\Domain\PaymentGateway;
use Gibbon\Module\EnhancedFinance\Domain\ExportGateway;
use Gibbon\Contracts\Database\Connection;
use Gibbon\Domain\System\SettingGateway;

/**
 * Bank Reconciliation Exporter
 *
 * Generates bank reconciliation CSV files for matching payments against
 * bank statements. The export includes payment dates, amounts, references,
 * and descriptions formatted for easy reconciliation.
 *
 * Bank Reconciliation CSV Format:
 * - UTF-8 encoding with BOM for special characters
 * - Comma-separated values
 * - Date format configurable (default: YYYY-MM-DD for sorting)
 * - Amounts with 2 decimal places, positive for deposits
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class BankReconciliationExporter
{
    /**
     * @var Connection
     */
    protected $db;

    /**
     * @var SettingGateway
     */
    protected $settingGateway;

    /**
     * @var PaymentGateway
     */
    protected $paymentGateway;

    /**
     * @var ExportGateway
     */
    protected $exportGateway;

    /**
     * Export types
     */
    public const EXPORT_TYPE_PAYMENTS = 'payments';
    public const EXPORT_TYPE_SUMMARY = 'summary';

    /**
     * Date format options
     */
    public const DATE_FORMAT_YMD = 'Y-m-d';
    public const DATE_FORMAT_MDY = 'm/d/Y';
    public const DATE_FORMAT_DMY = 'd/m/Y';

    /**
     * Default configuration
     */
    protected $config = [
        'dateFormat' => self::DATE_FORMAT_YMD,
        'includeBOM' => true,
        'delimiter' => ',',
        'enclosure' => '"',
        'includeReconciled' => true,
        'groupByMethod' => false,
    ];

    /**
     * Constructor.
     *
     * @param Connection $db
     * @param SettingGateway $settingGateway
     * @param PaymentGateway $paymentGateway
     * @param ExportGateway $exportGateway
     */
    public function __construct(
        Connection $db,
        SettingGateway $settingGateway,
        PaymentGateway $paymentGateway,
        ExportGateway $exportGateway
    ) {
        $this->db = $db;
        $this->settingGateway = $settingGateway;
        $this->paymentGateway = $paymentGateway;
        $this->exportGateway = $exportGateway;
    }

    /**
     * Configure export settings.
     *
     * @param array $config Configuration options
     * @return self
     */
    public function configure(array $config): self
    {
        $this->config = array_merge($this->config, $config);
        return $this;
    }

    /**
     * Export payments for bank reconciliation in CSV format.
     *
     * @param int $gibbonSchoolYearID School year to export
     * @param string|null $dateFrom Start date filter (Y-m-d)
     * @param string|null $dateTo End date filter (Y-m-d)
     * @param int $exportedByID Staff ID performing the export
     * @param string|null $paymentMethod Optional filter by payment method
     * @return array Export result with file path, record count, etc.
     * @throws \Exception If export fails
     */
    public function exportPayments($gibbonSchoolYearID, $dateFrom = null, $dateTo = null, $exportedByID = 0, $paymentMethod = null)
    {
        // Create export log entry
        $exportLogID = $this->createExportLog(
            'BankReconciliation',
            'CSV',
            $gibbonSchoolYearID,
            $dateFrom,
            $dateTo,
            $exportedByID,
            'payments'
        );

        try {
            // Mark as processing
            $this->exportGateway->markExportProcessing($exportLogID);

            // Fetch payment data
            $payments = $this->fetchPayments($gibbonSchoolYearID, $dateFrom, $dateTo, $paymentMethod);

            if (empty($payments)) {
                throw new \Exception('No payments found for the specified criteria');
            }

            // Generate CSV content
            $csvContent = $this->generateReconciliationCsv($payments);

            // Save file
            $fileName = $this->generateFileName('bank_reconciliation', $gibbonSchoolYearID, $dateFrom, $dateTo, $paymentMethod);
            $filePath = $this->saveExportFile($fileName, $csvContent);

            // Calculate totals
            $totalAmount = $this->calculateTotalAmount($payments, 'amount');

            // Update export log with success
            $this->exportGateway->markExportCompleted(
                $exportLogID,
                $filePath,
                strlen($csvContent),
                hash('sha256', $csvContent),
                count($payments),
                $totalAmount
            );

            return [
                'success' => true,
                'exportLogID' => $exportLogID,
                'fileName' => $fileName,
                'filePath' => $filePath,
                'recordCount' => count($payments),
                'totalAmount' => $totalAmount,
                'fileSize' => strlen($csvContent),
            ];

        } catch (\Exception $e) {
            // Update export log with failure
            $this->exportGateway->markExportFailed($exportLogID, $e->getMessage());

            return [
                'success' => false,
                'exportLogID' => $exportLogID,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Export payment summary by method for bank reconciliation.
     *
     * @param int $gibbonSchoolYearID School year to export
     * @param string|null $dateFrom Start date filter (Y-m-d)
     * @param string|null $dateTo End date filter (Y-m-d)
     * @param int $exportedByID Staff ID performing the export
     * @return array Export result with file path, record count, etc.
     * @throws \Exception If export fails
     */
    public function exportSummary($gibbonSchoolYearID, $dateFrom = null, $dateTo = null, $exportedByID = 0)
    {
        // Create export log entry
        $exportLogID = $this->createExportLog(
            'BankReconciliation',
            'CSV',
            $gibbonSchoolYearID,
            $dateFrom,
            $dateTo,
            $exportedByID,
            'summary'
        );

        try {
            // Mark as processing
            $this->exportGateway->markExportProcessing($exportLogID);

            // Fetch summary data
            $summary = $this->fetchPaymentSummary($gibbonSchoolYearID, $dateFrom, $dateTo);

            if (empty($summary)) {
                throw new \Exception('No payment data found for the specified criteria');
            }

            // Generate CSV content
            $csvContent = $this->generateSummaryCsv($summary, $dateFrom, $dateTo);

            // Save file
            $fileName = $this->generateFileName('bank_reconciliation_summary', $gibbonSchoolYearID, $dateFrom, $dateTo);
            $filePath = $this->saveExportFile($fileName, $csvContent);

            // Calculate totals
            $totalAmount = 0.0;
            foreach ($summary as $row) {
                $totalAmount += (float) ($row['totalAmount'] ?? 0);
            }

            // Update export log with success
            $this->exportGateway->markExportCompleted(
                $exportLogID,
                $filePath,
                strlen($csvContent),
                hash('sha256', $csvContent),
                count($summary),
                $totalAmount
            );

            return [
                'success' => true,
                'exportLogID' => $exportLogID,
                'fileName' => $fileName,
                'filePath' => $filePath,
                'recordCount' => count($summary),
                'totalAmount' => $totalAmount,
                'fileSize' => strlen($csvContent),
            ];

        } catch (\Exception $e) {
            // Update export log with failure
            $this->exportGateway->markExportFailed($exportLogID, $e->getMessage());

            return [
                'success' => false,
                'exportLogID' => $exportLogID,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Export payments grouped by date for daily reconciliation.
     *
     * @param int $gibbonSchoolYearID School year to export
     * @param string|null $dateFrom Start date filter (Y-m-d)
     * @param string|null $dateTo End date filter (Y-m-d)
     * @param int $exportedByID Staff ID performing the export
     * @return array Export result with file path, record count, etc.
     * @throws \Exception If export fails
     */
    public function exportDailyTotals($gibbonSchoolYearID, $dateFrom = null, $dateTo = null, $exportedByID = 0)
    {
        // Create export log entry
        $exportLogID = $this->createExportLog(
            'BankReconciliation',
            'CSV',
            $gibbonSchoolYearID,
            $dateFrom,
            $dateTo,
            $exportedByID,
            'daily'
        );

        try {
            // Mark as processing
            $this->exportGateway->markExportProcessing($exportLogID);

            // Fetch daily totals
            $dailyTotals = $this->fetchDailyTotals($gibbonSchoolYearID, $dateFrom, $dateTo);

            if (empty($dailyTotals)) {
                throw new \Exception('No payment data found for the specified criteria');
            }

            // Generate CSV content
            $csvContent = $this->generateDailyTotalsCsv($dailyTotals);

            // Save file
            $fileName = $this->generateFileName('bank_reconciliation_daily', $gibbonSchoolYearID, $dateFrom, $dateTo);
            $filePath = $this->saveExportFile($fileName, $csvContent);

            // Calculate totals
            $totalAmount = 0.0;
            foreach ($dailyTotals as $row) {
                $totalAmount += (float) ($row['totalAmount'] ?? 0);
            }

            // Update export log with success
            $this->exportGateway->markExportCompleted(
                $exportLogID,
                $filePath,
                strlen($csvContent),
                hash('sha256', $csvContent),
                count($dailyTotals),
                $totalAmount
            );

            return [
                'success' => true,
                'exportLogID' => $exportLogID,
                'fileName' => $fileName,
                'filePath' => $filePath,
                'recordCount' => count($dailyTotals),
                'totalAmount' => $totalAmount,
                'fileSize' => strlen($csvContent),
            ];

        } catch (\Exception $e) {
            // Update export log with failure
            $this->exportGateway->markExportFailed($exportLogID, $e->getMessage());

            return [
                'success' => false,
                'exportLogID' => $exportLogID,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Fetch payments for reconciliation export.
     *
     * @param int $gibbonSchoolYearID
     * @param string|null $dateFrom
     * @param string|null $dateTo
     * @param string|null $paymentMethod
     * @return array
     */
    protected function fetchPayments($gibbonSchoolYearID, $dateFrom = null, $dateTo = null, $paymentMethod = null)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID];
        $sql = "SELECT
                pay.gibbonEnhancedFinancePaymentID,
                pay.gibbonEnhancedFinanceInvoiceID,
                pay.paymentDate,
                pay.amount,
                pay.method,
                pay.reference,
                pay.notes,
                pay.timestampCreated,
                i.invoiceNumber,
                p.surname AS childSurname,
                p.preferredName AS childPreferredName,
                f.name AS familyName,
                f.gibbonFamilyID,
                recordedBy.surname AS recordedBySurname,
                recordedBy.preferredName AS recordedByPreferredName
            FROM gibbonEnhancedFinancePayment pay
            INNER JOIN gibbonEnhancedFinanceInvoice i ON pay.gibbonEnhancedFinanceInvoiceID = i.gibbonEnhancedFinanceInvoiceID
            LEFT JOIN gibbonPerson p ON i.gibbonPersonID = p.gibbonPersonID
            LEFT JOIN gibbonFamily f ON i.gibbonFamilyID = f.gibbonFamilyID
            LEFT JOIN gibbonPerson recordedBy ON pay.recordedByID = recordedBy.gibbonPersonID
            WHERE i.gibbonSchoolYearID = :gibbonSchoolYearID";

        if ($dateFrom !== null) {
            $sql .= " AND pay.paymentDate >= :dateFrom";
            $data['dateFrom'] = $dateFrom;
        }

        if ($dateTo !== null) {
            $sql .= " AND pay.paymentDate <= :dateTo";
            $data['dateTo'] = $dateTo;
        }

        if ($paymentMethod !== null && $paymentMethod !== '') {
            $sql .= " AND pay.method = :paymentMethod";
            $data['paymentMethod'] = $paymentMethod;
        }

        $sql .= " ORDER BY pay.paymentDate ASC, pay.timestampCreated ASC";

        try {
            $result = $this->db->select($sql, $data);
            return $result ? $result->fetchAll() : [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Fetch payment summary by method.
     *
     * @param int $gibbonSchoolYearID
     * @param string|null $dateFrom
     * @param string|null $dateTo
     * @return array
     */
    protected function fetchPaymentSummary($gibbonSchoolYearID, $dateFrom = null, $dateTo = null)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID];
        $sql = "SELECT
                pay.method,
                COUNT(*) AS paymentCount,
                SUM(pay.amount) AS totalAmount,
                MIN(pay.paymentDate) AS firstDate,
                MAX(pay.paymentDate) AS lastDate
            FROM gibbonEnhancedFinancePayment pay
            INNER JOIN gibbonEnhancedFinanceInvoice i ON pay.gibbonEnhancedFinanceInvoiceID = i.gibbonEnhancedFinanceInvoiceID
            WHERE i.gibbonSchoolYearID = :gibbonSchoolYearID";

        if ($dateFrom !== null) {
            $sql .= " AND pay.paymentDate >= :dateFrom";
            $data['dateFrom'] = $dateFrom;
        }

        if ($dateTo !== null) {
            $sql .= " AND pay.paymentDate <= :dateTo";
            $data['dateTo'] = $dateTo;
        }

        $sql .= " GROUP BY pay.method ORDER BY totalAmount DESC";

        try {
            $result = $this->db->select($sql, $data);
            return $result ? $result->fetchAll() : [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Fetch daily payment totals.
     *
     * @param int $gibbonSchoolYearID
     * @param string|null $dateFrom
     * @param string|null $dateTo
     * @return array
     */
    protected function fetchDailyTotals($gibbonSchoolYearID, $dateFrom = null, $dateTo = null)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID];
        $sql = "SELECT
                pay.paymentDate,
                pay.method,
                COUNT(*) AS paymentCount,
                SUM(pay.amount) AS totalAmount
            FROM gibbonEnhancedFinancePayment pay
            INNER JOIN gibbonEnhancedFinanceInvoice i ON pay.gibbonEnhancedFinanceInvoiceID = i.gibbonEnhancedFinanceInvoiceID
            WHERE i.gibbonSchoolYearID = :gibbonSchoolYearID";

        if ($dateFrom !== null) {
            $sql .= " AND pay.paymentDate >= :dateFrom";
            $data['dateFrom'] = $dateFrom;
        }

        if ($dateTo !== null) {
            $sql .= " AND pay.paymentDate <= :dateTo";
            $data['dateTo'] = $dateTo;
        }

        $sql .= " GROUP BY pay.paymentDate, pay.method ORDER BY pay.paymentDate ASC, pay.method ASC";

        try {
            $result = $this->db->select($sql, $data);
            return $result ? $result->fetchAll() : [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Generate CSV content for bank reconciliation.
     *
     * @param array $payments
     * @return string
     */
    protected function generateReconciliationCsv(array $payments)
    {
        $output = '';

        // Add BOM for UTF-8 if configured
        if ($this->config['includeBOM']) {
            $output .= "\xEF\xBB\xBF";
        }

        // CSV header row
        $headers = [
            'Date',
            'Reference',
            'Amount',
            'Payment Method',
            'Invoice Number',
            'Family Name',
            'Child Name',
            'Description',
            'Recorded By',
            'Timestamp',
            'Payment ID',
        ];

        $output .= $this->formatCsvRow($headers);

        // Data rows
        foreach ($payments as $payment) {
            $childName = $this->formatName($payment['childSurname'] ?? '', $payment['childPreferredName'] ?? '');
            $recordedBy = $this->formatName($payment['recordedBySurname'] ?? '', $payment['recordedByPreferredName'] ?? '');

            $description = sprintf(
                'Payment for Invoice %s - %s',
                $payment['invoiceNumber'],
                $childName
            );

            $row = [
                $this->formatDate($payment['paymentDate']),
                $this->sanitizeField($payment['reference'] ?? ''),
                $this->formatAmount($payment['amount']),
                $this->mapPaymentMethod($payment['method']),
                $payment['invoiceNumber'],
                $this->sanitizeField($payment['familyName'] ?? 'Unknown Family'),
                $this->sanitizeField($childName),
                $this->sanitizeField($description),
                $this->sanitizeField($recordedBy),
                $this->formatTimestamp($payment['timestampCreated']),
                'PAY-' . str_pad((string) $payment['gibbonEnhancedFinancePaymentID'], 8, '0', STR_PAD_LEFT),
            ];

            $output .= $this->formatCsvRow($row);
        }

        return $output;
    }

    /**
     * Generate CSV content for payment summary.
     *
     * @param array $summary
     * @param string|null $dateFrom
     * @param string|null $dateTo
     * @return string
     */
    protected function generateSummaryCsv(array $summary, $dateFrom = null, $dateTo = null)
    {
        $output = '';

        // Add BOM for UTF-8 if configured
        if ($this->config['includeBOM']) {
            $output .= "\xEF\xBB\xBF";
        }

        // Add report header
        $output .= $this->formatCsvRow(['Bank Reconciliation Summary Report']);
        $output .= $this->formatCsvRow(['Generated', date('Y-m-d H:i:s')]);

        if ($dateFrom || $dateTo) {
            $dateRange = sprintf(
                'Date Range: %s to %s',
                $dateFrom ?: 'Start',
                $dateTo ?: 'End'
            );
            $output .= $this->formatCsvRow([$dateRange]);
        }

        $output .= $this->formatCsvRow(['']); // Empty row

        // CSV header row
        $headers = [
            'Payment Method',
            'Payment Count',
            'Total Amount',
            'First Payment Date',
            'Last Payment Date',
        ];

        $output .= $this->formatCsvRow($headers);

        // Data rows
        $grandTotal = 0.0;
        $grandCount = 0;

        foreach ($summary as $row) {
            $dataRow = [
                $this->mapPaymentMethod($row['method']),
                $row['paymentCount'],
                $this->formatAmount($row['totalAmount']),
                $this->formatDate($row['firstDate']),
                $this->formatDate($row['lastDate']),
            ];

            $output .= $this->formatCsvRow($dataRow);

            $grandTotal += (float) $row['totalAmount'];
            $grandCount += (int) $row['paymentCount'];
        }

        // Add grand total row
        $output .= $this->formatCsvRow(['']); // Empty row
        $output .= $this->formatCsvRow([
            'GRAND TOTAL',
            $grandCount,
            $this->formatAmount($grandTotal),
            '',
            '',
        ]);

        return $output;
    }

    /**
     * Generate CSV content for daily totals.
     *
     * @param array $dailyTotals
     * @return string
     */
    protected function generateDailyTotalsCsv(array $dailyTotals)
    {
        $output = '';

        // Add BOM for UTF-8 if configured
        if ($this->config['includeBOM']) {
            $output .= "\xEF\xBB\xBF";
        }

        // CSV header row
        $headers = [
            'Date',
            'Payment Method',
            'Payment Count',
            'Total Amount',
        ];

        $output .= $this->formatCsvRow($headers);

        // Data rows
        $grandTotal = 0.0;
        $grandCount = 0;

        foreach ($dailyTotals as $row) {
            $dataRow = [
                $this->formatDate($row['paymentDate']),
                $this->mapPaymentMethod($row['method']),
                $row['paymentCount'],
                $this->formatAmount($row['totalAmount']),
            ];

            $output .= $this->formatCsvRow($dataRow);

            $grandTotal += (float) $row['totalAmount'];
            $grandCount += (int) $row['paymentCount'];
        }

        // Add grand total row
        $output .= $this->formatCsvRow(['']); // Empty row
        $output .= $this->formatCsvRow([
            'GRAND TOTAL',
            '',
            $grandCount,
            $this->formatAmount($grandTotal),
        ]);

        return $output;
    }

    /**
     * Format a CSV row.
     *
     * @param array $fields
     * @return string
     */
    protected function formatCsvRow(array $fields)
    {
        $delimiter = $this->config['delimiter'];
        $enclosure = $this->config['enclosure'];

        $escapedFields = array_map(function ($field) use ($delimiter, $enclosure) {
            $field = (string) $field;

            // If field contains delimiter, enclosure, or newline, wrap in enclosure
            if (strpos($field, $delimiter) !== false ||
                strpos($field, $enclosure) !== false ||
                strpos($field, "\n") !== false ||
                strpos($field, "\r") !== false) {
                // Escape enclosure by doubling it
                $field = str_replace($enclosure, $enclosure . $enclosure, $field);
                return $enclosure . $field . $enclosure;
            }
            return $field;
        }, $fields);

        return implode($delimiter, $escapedFields) . "\r\n";
    }

    /**
     * Format date for export.
     *
     * @param string $date Date in Y-m-d format
     * @return string
     */
    protected function formatDate($date)
    {
        if (empty($date)) {
            return '';
        }

        try {
            $dateObj = new \DateTime($date);
            return $dateObj->format($this->config['dateFormat']);
        } catch (\Exception $e) {
            return $date;
        }
    }

    /**
     * Format timestamp for export.
     *
     * @param string $timestamp
     * @return string
     */
    protected function formatTimestamp($timestamp)
    {
        if (empty($timestamp)) {
            return '';
        }

        try {
            $dateObj = new \DateTime($timestamp);
            return $dateObj->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return $timestamp;
        }
    }

    /**
     * Format amount for export.
     *
     * @param mixed $amount
     * @return string
     */
    protected function formatAmount($amount)
    {
        if ($amount === null || $amount === '') {
            return '0.00';
        }

        return number_format((float) $amount, 2, '.', '');
    }

    /**
     * Format name (Surname, FirstName).
     *
     * @param string $surname
     * @param string $firstName
     * @return string
     */
    protected function formatName($surname, $firstName)
    {
        $surname = trim($surname);
        $firstName = trim($firstName);

        if (empty($surname) && empty($firstName)) {
            return '';
        }

        if (empty($surname)) {
            return $firstName;
        }

        if (empty($firstName)) {
            return $surname;
        }

        return $surname . ', ' . $firstName;
    }

    /**
     * Map internal payment method to display format.
     *
     * @param string $method
     * @return string
     */
    protected function mapPaymentMethod($method)
    {
        $methodMap = [
            'Cash' => 'Cash',
            'Cheque' => 'Cheque',
            'ETransfer' => 'E-Transfer',
            'CreditCard' => 'Credit Card',
            'DebitCard' => 'Debit Card',
            'Other' => 'Other',
        ];

        return $methodMap[$method] ?? $method;
    }

    /**
     * Sanitize field for CSV export.
     *
     * @param string $field
     * @return string
     */
    protected function sanitizeField($field)
    {
        if ($field === null) {
            return '';
        }

        // Remove control characters except tab, newline, carriage return
        $field = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $field);

        // Trim whitespace
        return trim($field);
    }

    /**
     * Generate export file name.
     *
     * @param string $type Export type
     * @param int $gibbonSchoolYearID
     * @param string|null $dateFrom
     * @param string|null $dateTo
     * @param string|null $paymentMethod
     * @return string
     */
    protected function generateFileName($type, $gibbonSchoolYearID, $dateFrom = null, $dateTo = null, $paymentMethod = null)
    {
        $parts = [
            $type,
            'SY' . $gibbonSchoolYearID,
        ];

        if ($dateFrom) {
            $parts[] = 'from' . str_replace('-', '', $dateFrom);
        }

        if ($dateTo) {
            $parts[] = 'to' . str_replace('-', '', $dateTo);
        }

        if ($paymentMethod) {
            $parts[] = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $paymentMethod));
        }

        $parts[] = date('Ymd_His');

        return implode('_', $parts) . '.csv';
    }

    /**
     * Save export file to the uploads directory.
     *
     * @param string $fileName
     * @param string $content
     * @return string Full file path
     * @throws \Exception If file cannot be saved
     */
    protected function saveExportFile($fileName, $content)
    {
        // Get absolute path from Gibbon configuration
        $absolutePath = $this->getExportDirectory();

        // Create export directory if it doesn't exist
        if (!is_dir($absolutePath)) {
            if (!mkdir($absolutePath, 0755, true)) {
                throw new \Exception('Failed to create export directory: ' . $absolutePath);
            }
        }

        $filePath = $absolutePath . '/' . $fileName;

        // Save file
        $result = file_put_contents($filePath, $content);

        if ($result === false) {
            throw new \Exception('Failed to write export file: ' . $filePath);
        }

        return $filePath;
    }

    /**
     * Get the export directory path.
     *
     * @return string
     */
    protected function getExportDirectory()
    {
        // Use Gibbon's uploads directory structure
        global $session;

        $absolutePath = $session->get('absolutePath') ?? '';
        $uploadsPath = $absolutePath . '/uploads';

        // Create module-specific export directory
        $exportPath = $uploadsPath . '/EnhancedFinance/exports/' . date('Y/m');

        return $exportPath;
    }

    /**
     * Create export log entry.
     *
     * @param string $exportType
     * @param string $exportFormat
     * @param int $gibbonSchoolYearID
     * @param string|null $dateFrom
     * @param string|null $dateTo
     * @param int $exportedByID
     * @param string $subType
     * @return int Export log ID
     */
    protected function createExportLog($exportType, $exportFormat, $gibbonSchoolYearID, $dateFrom, $dateTo, $exportedByID, $subType)
    {
        $fileName = $this->generateFileName('bank_reconciliation_' . $subType, $gibbonSchoolYearID, $dateFrom, $dateTo);

        return $this->exportGateway->insertExport([
            'exportType' => $exportType,
            'exportFormat' => $exportFormat,
            'gibbonSchoolYearID' => $gibbonSchoolYearID,
            'dateRangeStart' => $dateFrom,
            'dateRangeEnd' => $dateTo,
            'fileName' => $fileName,
            'filePath' => '',
            'exportedByID' => $exportedByID,
            'status' => 'Pending',
        ]);
    }

    /**
     * Calculate total amount from records.
     *
     * @param array $records
     * @param string $field
     * @return float
     */
    protected function calculateTotalAmount(array $records, $field)
    {
        $total = 0.0;
        foreach ($records as $record) {
            $total += (float) ($record[$field] ?? 0);
        }
        return $total;
    }

    /**
     * Get available payment methods for filtering.
     *
     * @return array
     */
    public function getPaymentMethods()
    {
        return [
            'Cash' => 'Cash',
            'Cheque' => 'Cheque',
            'ETransfer' => 'E-Transfer',
            'CreditCard' => 'Credit Card',
            'DebitCard' => 'Debit Card',
            'Other' => 'Other',
        ];
    }

    /**
     * Get export preview (first N records without saving).
     *
     * @param string $type Export type (payments, summary, daily)
     * @param int $gibbonSchoolYearID
     * @param string|null $dateFrom
     * @param string|null $dateTo
     * @param string|null $paymentMethod
     * @param int $limit Number of records to preview
     * @return array Preview data with headers and sample rows
     */
    public function getPreview($type, $gibbonSchoolYearID, $dateFrom = null, $dateTo = null, $paymentMethod = null, $limit = 10)
    {
        if ($type === self::EXPORT_TYPE_SUMMARY) {
            $records = $this->fetchPaymentSummary($gibbonSchoolYearID, $dateFrom, $dateTo);
            $headers = ['Payment Method', 'Payment Count', 'Total Amount', 'First Date', 'Last Date'];

            $previewData = [];
            foreach (array_slice($records, 0, $limit) as $row) {
                $previewData[] = [
                    'method' => $this->mapPaymentMethod($row['method']),
                    'paymentCount' => $row['paymentCount'],
                    'totalAmount' => $this->formatAmount($row['totalAmount']),
                    'firstDate' => $this->formatDate($row['firstDate']),
                    'lastDate' => $this->formatDate($row['lastDate']),
                ];
            }
        } else {
            $records = $this->fetchPayments($gibbonSchoolYearID, $dateFrom, $dateTo, $paymentMethod);
            $headers = ['Date', 'Reference', 'Amount', 'Method', 'Invoice', 'Family', 'Child'];

            $previewData = [];
            foreach (array_slice($records, 0, $limit) as $payment) {
                $previewData[] = [
                    'date' => $this->formatDate($payment['paymentDate']),
                    'reference' => $payment['reference'] ?? '',
                    'amount' => $this->formatAmount($payment['amount']),
                    'method' => $this->mapPaymentMethod($payment['method']),
                    'invoiceNumber' => $payment['invoiceNumber'],
                    'familyName' => $payment['familyName'] ?? '',
                    'childName' => $this->formatName($payment['childSurname'] ?? '', $payment['childPreferredName'] ?? ''),
                ];
            }
        }

        return [
            'headers' => $headers,
            'totalRecords' => count($records),
            'previewRecords' => $previewData,
            'previewCount' => count($previewData),
        ];
    }
}
