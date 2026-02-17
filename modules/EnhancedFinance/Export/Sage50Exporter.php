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

use Gibbon\Module\EnhancedFinance\Domain\InvoiceGateway;
use Gibbon\Module\EnhancedFinance\Domain\PaymentGateway;
use Gibbon\Module\EnhancedFinance\Domain\ExportGateway;
use Gibbon\Contracts\Database\Connection;
use Gibbon\Domain\System\SettingGateway;

/**
 * Sage 50 Exporter
 *
 * Generates Sage 50 compatible CSV import files for invoices and payments.
 * Supports both Sales Invoices and Receipts/Payments export formats.
 *
 * Sage 50 CSV Import Format Requirements:
 * - UTF-8 encoding with BOM for special characters
 * - Comma-separated values
 * - Date format: MM/DD/YYYY or YYYY-MM-DD (configurable)
 * - Amount format: No currency symbols, decimal point separator
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class Sage50Exporter
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
     * @var InvoiceGateway
     */
    protected $invoiceGateway;

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
    public const EXPORT_TYPE_INVOICES = 'invoices';
    public const EXPORT_TYPE_PAYMENTS = 'payments';
    public const EXPORT_TYPE_COMBINED = 'combined';

    /**
     * Date format options for Sage 50
     */
    public const DATE_FORMAT_MDY = 'm/d/Y';
    public const DATE_FORMAT_YMD = 'Y-m-d';
    public const DATE_FORMAT_DMY = 'd/m/Y';

    /**
     * Default configuration
     */
    protected $config = [
        'dateFormat' => self::DATE_FORMAT_MDY,
        'includeBOM' => true,
        'delimiter' => ',',
        'enclosure' => '"',
    ];

    /**
     * Constructor.
     *
     * @param Connection $db
     * @param SettingGateway $settingGateway
     * @param InvoiceGateway $invoiceGateway
     * @param PaymentGateway $paymentGateway
     * @param ExportGateway $exportGateway
     */
    public function __construct(
        Connection $db,
        SettingGateway $settingGateway,
        InvoiceGateway $invoiceGateway,
        PaymentGateway $paymentGateway,
        ExportGateway $exportGateway
    ) {
        $this->db = $db;
        $this->settingGateway = $settingGateway;
        $this->invoiceGateway = $invoiceGateway;
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
     * Export invoices to Sage 50 CSV format.
     *
     * @param int $gibbonSchoolYearID School year to export
     * @param string|null $dateFrom Start date filter (Y-m-d)
     * @param string|null $dateTo End date filter (Y-m-d)
     * @param int $exportedByID Staff ID performing the export
     * @return array Export result with file path, record count, etc.
     * @throws \Exception If export fails
     */
    public function exportInvoices($gibbonSchoolYearID, $dateFrom = null, $dateTo = null, $exportedByID = 0)
    {
        // Get account settings
        $arAccount = $this->settingGateway->getSettingByScope('Enhanced Finance', 'sage50AccountsReceivable') ?: '1200';
        $revenueAccount = $this->settingGateway->getSettingByScope('Enhanced Finance', 'sage50RevenueAccount') ?: '4100';

        // Create export log entry
        $exportLogID = $this->createExportLog(
            'Sage50',
            'CSV',
            $gibbonSchoolYearID,
            $dateFrom,
            $dateTo,
            $exportedByID,
            'invoices'
        );

        try {
            // Mark as processing
            $this->exportGateway->markExportProcessing($exportLogID);

            // Fetch invoice data
            $invoices = $this->fetchInvoices($gibbonSchoolYearID, $dateFrom, $dateTo);

            if (empty($invoices)) {
                throw new \Exception('No invoices found for the specified criteria');
            }

            // Generate CSV content
            $csvContent = $this->generateInvoicesCsv($invoices, $arAccount, $revenueAccount);

            // Save file
            $fileName = $this->generateFileName('sage50_invoices', $gibbonSchoolYearID, $dateFrom, $dateTo);
            $filePath = $this->saveExportFile($fileName, $csvContent);

            // Calculate totals
            $totalAmount = $this->calculateTotalAmount($invoices, 'totalAmount');

            // Update export log with success
            $this->exportGateway->markExportCompleted(
                $exportLogID,
                $filePath,
                strlen($csvContent),
                hash('sha256', $csvContent),
                count($invoices),
                $totalAmount
            );

            return [
                'success' => true,
                'exportLogID' => $exportLogID,
                'fileName' => $fileName,
                'filePath' => $filePath,
                'recordCount' => count($invoices),
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
     * Export payments to Sage 50 CSV format.
     *
     * @param int $gibbonSchoolYearID School year to export
     * @param string|null $dateFrom Start date filter (Y-m-d)
     * @param string|null $dateTo End date filter (Y-m-d)
     * @param int $exportedByID Staff ID performing the export
     * @return array Export result with file path, record count, etc.
     * @throws \Exception If export fails
     */
    public function exportPayments($gibbonSchoolYearID, $dateFrom = null, $dateTo = null, $exportedByID = 0)
    {
        // Get account settings
        $arAccount = $this->settingGateway->getSettingByScope('Enhanced Finance', 'sage50AccountsReceivable') ?: '1200';

        // Create export log entry
        $exportLogID = $this->createExportLog(
            'Sage50',
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
            $payments = $this->fetchPayments($gibbonSchoolYearID, $dateFrom, $dateTo);

            if (empty($payments)) {
                throw new \Exception('No payments found for the specified criteria');
            }

            // Generate CSV content
            $csvContent = $this->generatePaymentsCsv($payments, $arAccount);

            // Save file
            $fileName = $this->generateFileName('sage50_payments', $gibbonSchoolYearID, $dateFrom, $dateTo);
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
     * Export combined invoices and payments to Sage 50 CSV format.
     *
     * @param int $gibbonSchoolYearID School year to export
     * @param string|null $dateFrom Start date filter (Y-m-d)
     * @param string|null $dateTo End date filter (Y-m-d)
     * @param int $exportedByID Staff ID performing the export
     * @return array Export results for both invoices and payments
     */
    public function exportCombined($gibbonSchoolYearID, $dateFrom = null, $dateTo = null, $exportedByID = 0)
    {
        $invoiceResult = $this->exportInvoices($gibbonSchoolYearID, $dateFrom, $dateTo, $exportedByID);
        $paymentResult = $this->exportPayments($gibbonSchoolYearID, $dateFrom, $dateTo, $exportedByID);

        return [
            'invoices' => $invoiceResult,
            'payments' => $paymentResult,
            'success' => $invoiceResult['success'] && $paymentResult['success'],
        ];
    }

    /**
     * Fetch invoices for export.
     *
     * @param int $gibbonSchoolYearID
     * @param string|null $dateFrom
     * @param string|null $dateTo
     * @return array
     */
    protected function fetchInvoices($gibbonSchoolYearID, $dateFrom = null, $dateTo = null)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID];
        $sql = "SELECT
                i.gibbonEnhancedFinanceInvoiceID,
                i.invoiceNumber,
                i.invoiceDate,
                i.dueDate,
                i.subtotal,
                i.taxAmount,
                i.totalAmount,
                i.paidAmount,
                i.status,
                i.notes,
                p.surname AS childSurname,
                p.preferredName AS childPreferredName,
                f.name AS familyName,
                f.gibbonFamilyID
            FROM gibbonEnhancedFinanceInvoice i
            LEFT JOIN gibbonPerson p ON i.gibbonPersonID = p.gibbonPersonID
            LEFT JOIN gibbonFamily f ON i.gibbonFamilyID = f.gibbonFamilyID
            WHERE i.gibbonSchoolYearID = :gibbonSchoolYearID
            AND i.status NOT IN ('Cancelled')";

        if ($dateFrom !== null) {
            $sql .= " AND i.invoiceDate >= :dateFrom";
            $data['dateFrom'] = $dateFrom;
        }

        if ($dateTo !== null) {
            $sql .= " AND i.invoiceDate <= :dateTo";
            $data['dateTo'] = $dateTo;
        }

        $sql .= " ORDER BY i.invoiceDate ASC, i.invoiceNumber ASC";

        try {
            $result = $this->db->select($sql, $data);
            return $result ? $result->fetchAll() : [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Fetch payments for export.
     *
     * @param int $gibbonSchoolYearID
     * @param string|null $dateFrom
     * @param string|null $dateTo
     * @return array
     */
    protected function fetchPayments($gibbonSchoolYearID, $dateFrom = null, $dateTo = null)
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
                i.invoiceNumber,
                p.surname AS childSurname,
                p.preferredName AS childPreferredName,
                f.name AS familyName,
                f.gibbonFamilyID
            FROM gibbonEnhancedFinancePayment pay
            INNER JOIN gibbonEnhancedFinanceInvoice i ON pay.gibbonEnhancedFinanceInvoiceID = i.gibbonEnhancedFinanceInvoiceID
            LEFT JOIN gibbonPerson p ON i.gibbonPersonID = p.gibbonPersonID
            LEFT JOIN gibbonFamily f ON i.gibbonFamilyID = f.gibbonFamilyID
            WHERE i.gibbonSchoolYearID = :gibbonSchoolYearID";

        if ($dateFrom !== null) {
            $sql .= " AND pay.paymentDate >= :dateFrom";
            $data['dateFrom'] = $dateFrom;
        }

        if ($dateTo !== null) {
            $sql .= " AND pay.paymentDate <= :dateTo";
            $data['dateTo'] = $dateTo;
        }

        $sql .= " ORDER BY pay.paymentDate ASC, pay.gibbonEnhancedFinancePaymentID ASC";

        try {
            $result = $this->db->select($sql, $data);
            return $result ? $result->fetchAll() : [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Generate CSV content for invoices.
     *
     * Sage 50 Sales Invoice Import Format:
     * - Customer ID, Invoice Number, Date, Due Date, Account, Amount, Tax Code, Description
     *
     * @param array $invoices
     * @param string $arAccount Accounts Receivable account code
     * @param string $revenueAccount Revenue account code
     * @return string
     */
    protected function generateInvoicesCsv(array $invoices, $arAccount, $revenueAccount)
    {
        $output = '';

        // Add BOM for UTF-8 if configured
        if ($this->config['includeBOM']) {
            $output .= "\xEF\xBB\xBF";
        }

        // CSV header row
        $headers = [
            'Customer ID',
            'Customer Name',
            'Invoice Number',
            'Invoice Date',
            'Due Date',
            'Debit Account',
            'Credit Account',
            'Subtotal',
            'Tax Amount',
            'Total Amount',
            'Description',
            'Child Name',
            'Status',
        ];

        $output .= $this->formatCsvRow($headers);

        // Data rows
        foreach ($invoices as $invoice) {
            $customerID = $this->formatCustomerID($invoice['gibbonFamilyID']);
            $customerName = $this->sanitizeField($invoice['familyName'] ?? 'Unknown Family');
            $childName = $this->formatName($invoice['childSurname'] ?? '', $invoice['childPreferredName'] ?? '');
            $description = sprintf(
                'Childcare Invoice %s - %s',
                $invoice['invoiceNumber'],
                $childName
            );

            $row = [
                $customerID,
                $customerName,
                $invoice['invoiceNumber'],
                $this->formatDate($invoice['invoiceDate']),
                $this->formatDate($invoice['dueDate']),
                $arAccount,  // Debit: Accounts Receivable
                $revenueAccount,  // Credit: Revenue
                $this->formatAmount($invoice['subtotal']),
                $this->formatAmount($invoice['taxAmount']),
                $this->formatAmount($invoice['totalAmount']),
                $this->sanitizeField($description),
                $this->sanitizeField($childName),
                $invoice['status'],
            ];

            $output .= $this->formatCsvRow($row);
        }

        return $output;
    }

    /**
     * Generate CSV content for payments.
     *
     * Sage 50 Receipt/Payment Import Format:
     * - Customer ID, Invoice Number, Payment Date, Amount, Payment Method, Reference
     *
     * @param array $payments
     * @param string $arAccount Accounts Receivable account code
     * @return string
     */
    protected function generatePaymentsCsv(array $payments, $arAccount)
    {
        $output = '';

        // Add BOM for UTF-8 if configured
        if ($this->config['includeBOM']) {
            $output .= "\xEF\xBB\xBF";
        }

        // CSV header row
        $headers = [
            'Customer ID',
            'Customer Name',
            'Invoice Number',
            'Payment Date',
            'Amount',
            'Payment Method',
            'Reference',
            'Credit Account',
            'Description',
            'Child Name',
        ];

        $output .= $this->formatCsvRow($headers);

        // Data rows
        foreach ($payments as $payment) {
            $customerID = $this->formatCustomerID($payment['gibbonFamilyID']);
            $customerName = $this->sanitizeField($payment['familyName'] ?? 'Unknown Family');
            $childName = $this->formatName($payment['childSurname'] ?? '', $payment['childPreferredName'] ?? '');
            $description = sprintf(
                'Payment for Invoice %s - %s',
                $payment['invoiceNumber'],
                $childName
            );

            // Map internal payment method to Sage 50 compatible format
            $paymentMethod = $this->mapPaymentMethod($payment['method']);

            $row = [
                $customerID,
                $customerName,
                $payment['invoiceNumber'],
                $this->formatDate($payment['paymentDate']),
                $this->formatAmount($payment['amount']),
                $paymentMethod,
                $this->sanitizeField($payment['reference'] ?? ''),
                $arAccount,  // Credit: Accounts Receivable (reduce balance)
                $this->sanitizeField($description),
                $this->sanitizeField($childName),
            ];

            $output .= $this->formatCsvRow($row);
        }

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
     * Format date for Sage 50.
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
     * Format amount for Sage 50.
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
     * Format customer ID from family ID.
     *
     * @param int $gibbonFamilyID
     * @return string
     */
    protected function formatCustomerID($gibbonFamilyID)
    {
        return 'FAM-' . str_pad((string) $gibbonFamilyID, 6, '0', STR_PAD_LEFT);
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
     * Map internal payment method to Sage 50 compatible format.
     *
     * @param string $method
     * @return string
     */
    protected function mapPaymentMethod($method)
    {
        $methodMap = [
            'Cash' => 'Cash',
            'Cheque' => 'Cheque',
            'ETransfer' => 'Electronic Transfer',
            'CreditCard' => 'Credit Card',
            'DebitCard' => 'Debit Card',
            'Other' => 'Other',
        ];

        return $methodMap[$method] ?? 'Other';
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
     * @param string $type Export type (invoices, payments)
     * @param int $gibbonSchoolYearID
     * @param string|null $dateFrom
     * @param string|null $dateTo
     * @return string
     */
    protected function generateFileName($type, $gibbonSchoolYearID, $dateFrom = null, $dateTo = null)
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
        $fileName = $this->generateFileName('sage50_' . $subType, $gibbonSchoolYearID, $dateFrom, $dateTo);

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
     * Get list of required settings for Sage 50 export.
     *
     * @return array
     */
    public function getRequiredSettings()
    {
        return [
            'sage50AccountsReceivable' => $this->settingGateway->getSettingByScope('Enhanced Finance', 'sage50AccountsReceivable'),
            'sage50RevenueAccount' => $this->settingGateway->getSettingByScope('Enhanced Finance', 'sage50RevenueAccount'),
        ];
    }

    /**
     * Validate required settings are configured.
     *
     * @return array List of missing settings
     */
    public function validateSettings()
    {
        $missing = [];
        $settings = $this->getRequiredSettings();

        foreach ($settings as $name => $value) {
            if (empty($value)) {
                $missing[] = $name;
            }
        }

        return $missing;
    }

    /**
     * Get export preview (first N records without saving).
     *
     * @param string $type Export type (invoices or payments)
     * @param int $gibbonSchoolYearID
     * @param string|null $dateFrom
     * @param string|null $dateTo
     * @param int $limit Number of records to preview
     * @return array Preview data with headers and sample rows
     */
    public function getPreview($type, $gibbonSchoolYearID, $dateFrom = null, $dateTo = null, $limit = 10)
    {
        if ($type === self::EXPORT_TYPE_INVOICES) {
            $records = $this->fetchInvoices($gibbonSchoolYearID, $dateFrom, $dateTo);
            $headers = [
                'Customer ID', 'Customer Name', 'Invoice Number', 'Invoice Date',
                'Due Date', 'Debit Account', 'Credit Account', 'Subtotal',
                'Tax Amount', 'Total Amount', 'Description', 'Child Name', 'Status',
            ];
        } else {
            $records = $this->fetchPayments($gibbonSchoolYearID, $dateFrom, $dateTo);
            $headers = [
                'Customer ID', 'Customer Name', 'Invoice Number', 'Payment Date',
                'Amount', 'Payment Method', 'Reference', 'Credit Account',
                'Description', 'Child Name',
            ];
        }

        $preview = array_slice($records, 0, $limit);

        return [
            'headers' => $headers,
            'totalRecords' => count($records),
            'previewRecords' => $preview,
            'previewCount' => count($preview),
        ];
    }
}
