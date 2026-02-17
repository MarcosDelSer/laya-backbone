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
 * QuickBooks Exporter
 *
 * Generates QuickBooks compatible IIF (Intuit Interchange Format) files
 * for invoices and payments. The IIF format is a tab-delimited text format
 * used by QuickBooks Desktop for importing transactions.
 *
 * IIF Format Structure:
 * - Header rows start with "!" to define column names
 * - Transaction rows start with "TRNS" for the main entry
 * - Split rows start with "SPL" for distribution lines
 * - "ENDTRNS" marks the end of a transaction block
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class QuickBooksExporter
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
     * IIF transaction types
     */
    public const IIF_TRNS_TYPE_INVOICE = 'INVOICE';
    public const IIF_TRNS_TYPE_PAYMENT = 'PAYMENT';
    public const IIF_TRNS_TYPE_DEPOSIT = 'DEPOSIT';

    /**
     * Date format for QuickBooks IIF (M/D/YY)
     */
    public const DATE_FORMAT_IIF = 'n/j/y';

    /**
     * Default configuration
     */
    protected $config = [
        'dateFormat' => self::DATE_FORMAT_IIF,
        'delimiter' => "\t",
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
     * Export invoices to QuickBooks IIF format.
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
        $arAccount = $this->settingGateway->getSettingByScope('Enhanced Finance', 'quickbooksAccountsReceivable') ?: 'Accounts Receivable';
        $revenueAccount = $this->settingGateway->getSettingByScope('Enhanced Finance', 'quickbooksRevenueAccount') ?: 'Childcare Revenue';

        // Create export log entry
        $exportLogID = $this->createExportLog(
            'QuickBooks',
            'IIF',
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

            // Generate IIF content
            $iifContent = $this->generateInvoicesIif($invoices, $arAccount, $revenueAccount);

            // Save file
            $fileName = $this->generateFileName('quickbooks_invoices', $gibbonSchoolYearID, $dateFrom, $dateTo);
            $filePath = $this->saveExportFile($fileName, $iifContent);

            // Calculate totals
            $totalAmount = $this->calculateTotalAmount($invoices, 'totalAmount');

            // Update export log with success
            $this->exportGateway->markExportCompleted(
                $exportLogID,
                $filePath,
                strlen($iifContent),
                hash('sha256', $iifContent),
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
                'fileSize' => strlen($iifContent),
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
     * Export payments to QuickBooks IIF format.
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
        $arAccount = $this->settingGateway->getSettingByScope('Enhanced Finance', 'quickbooksAccountsReceivable') ?: 'Accounts Receivable';
        $depositAccount = $this->settingGateway->getSettingByScope('Enhanced Finance', 'quickbooksDepositAccount') ?: 'Undeposited Funds';

        // Create export log entry
        $exportLogID = $this->createExportLog(
            'QuickBooks',
            'IIF',
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

            // Generate IIF content
            $iifContent = $this->generatePaymentsIif($payments, $arAccount, $depositAccount);

            // Save file
            $fileName = $this->generateFileName('quickbooks_payments', $gibbonSchoolYearID, $dateFrom, $dateTo);
            $filePath = $this->saveExportFile($fileName, $iifContent);

            // Calculate totals
            $totalAmount = $this->calculateTotalAmount($payments, 'amount');

            // Update export log with success
            $this->exportGateway->markExportCompleted(
                $exportLogID,
                $filePath,
                strlen($iifContent),
                hash('sha256', $iifContent),
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
                'fileSize' => strlen($iifContent),
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
     * Export combined invoices and payments to QuickBooks IIF format.
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
     * Generate IIF content for invoices.
     *
     * QuickBooks Invoice IIF Format:
     * - TRNS line: Main invoice header with AR debit
     * - SPL line: Revenue credit line
     * - ENDTRNS: Transaction end marker
     *
     * @param array $invoices
     * @param string $arAccount Accounts Receivable account name
     * @param string $revenueAccount Revenue account name
     * @return string
     */
    protected function generateInvoicesIif(array $invoices, $arAccount, $revenueAccount)
    {
        $output = '';
        $delimiter = $this->config['delimiter'];

        // IIF Header for invoices (TRNS section)
        $trnsHeaders = ['!TRNS', 'TRNSID', 'TRNSTYPE', 'DATE', 'ACCNT', 'NAME', 'AMOUNT', 'DOCNUM', 'MEMO', 'CLEAR', 'TOPRINT', 'DUEDATE'];
        $output .= implode($delimiter, $trnsHeaders) . "\r\n";

        // IIF Header for split lines (SPL section)
        $splHeaders = ['!SPL', 'SPLID', 'TRNSTYPE', 'DATE', 'ACCNT', 'NAME', 'AMOUNT', 'DOCNUM', 'MEMO', 'CLEAR', 'QNTY', 'PRICE'];
        $output .= implode($delimiter, $splHeaders) . "\r\n";

        // End transaction marker header
        $output .= "!ENDTRNS\r\n";

        // Data rows for each invoice
        foreach ($invoices as $invoice) {
            $customerName = $this->formatCustomerName($invoice);
            $invoiceDate = $this->formatDate($invoice['invoiceDate']);
            $dueDate = $this->formatDate($invoice['dueDate']);
            $childName = $this->formatName($invoice['childSurname'] ?? '', $invoice['childPreferredName'] ?? '');
            $memo = $this->sanitizeField(sprintf('Childcare Invoice - %s', $childName));

            // TRNS line (debit to Accounts Receivable)
            $trnsRow = [
                'TRNS',
                '',  // TRNSID - auto-generated by QuickBooks
                self::IIF_TRNS_TYPE_INVOICE,
                $invoiceDate,
                $arAccount,
                $customerName,
                $this->formatAmount($invoice['totalAmount']),
                $invoice['invoiceNumber'],
                $memo,
                'N',  // CLEAR - not cleared
                'N',  // TOPRINT
                $dueDate,
            ];
            $output .= implode($delimiter, $trnsRow) . "\r\n";

            // SPL line (credit to Revenue account)
            // If there's tax, we need separate lines for subtotal and tax
            if (!empty($invoice['taxAmount']) && (float)$invoice['taxAmount'] > 0) {
                // Revenue line (subtotal)
                $splRow = [
                    'SPL',
                    '',  // SPLID
                    self::IIF_TRNS_TYPE_INVOICE,
                    $invoiceDate,
                    $revenueAccount,
                    $customerName,
                    '-' . $this->formatAmount($invoice['subtotal']),  // Credit is negative
                    $invoice['invoiceNumber'],
                    $this->sanitizeField('Childcare Services - ' . $childName),
                    'N',
                    '1',  // QNTY
                    $this->formatAmount($invoice['subtotal']),  // PRICE
                ];
                $output .= implode($delimiter, $splRow) . "\r\n";

                // Tax line
                $taxAccount = $this->settingGateway->getSettingByScope('Enhanced Finance', 'quickbooksTaxAccount') ?: 'Sales Tax Payable';
                $splTaxRow = [
                    'SPL',
                    '',
                    self::IIF_TRNS_TYPE_INVOICE,
                    $invoiceDate,
                    $taxAccount,
                    $customerName,
                    '-' . $this->formatAmount($invoice['taxAmount']),
                    $invoice['invoiceNumber'],
                    'Tax',
                    'N',
                    '',
                    '',
                ];
                $output .= implode($delimiter, $splTaxRow) . "\r\n";
            } else {
                // Single revenue line (no tax)
                $splRow = [
                    'SPL',
                    '',
                    self::IIF_TRNS_TYPE_INVOICE,
                    $invoiceDate,
                    $revenueAccount,
                    $customerName,
                    '-' . $this->formatAmount($invoice['totalAmount']),
                    $invoice['invoiceNumber'],
                    $this->sanitizeField('Childcare Services - ' . $childName),
                    'N',
                    '1',
                    $this->formatAmount($invoice['totalAmount']),
                ];
                $output .= implode($delimiter, $splRow) . "\r\n";
            }

            // End transaction marker
            $output .= "ENDTRNS\r\n";
        }

        return $output;
    }

    /**
     * Generate IIF content for payments.
     *
     * QuickBooks Payment IIF Format:
     * - TRNS line: Main payment with deposit to Undeposited Funds
     * - SPL line: Credit to Accounts Receivable
     * - ENDTRNS: Transaction end marker
     *
     * @param array $payments
     * @param string $arAccount Accounts Receivable account name
     * @param string $depositAccount Deposit account name (usually Undeposited Funds)
     * @return string
     */
    protected function generatePaymentsIif(array $payments, $arAccount, $depositAccount)
    {
        $output = '';
        $delimiter = $this->config['delimiter'];

        // IIF Header for payments (TRNS section)
        $trnsHeaders = ['!TRNS', 'TRNSID', 'TRNSTYPE', 'DATE', 'ACCNT', 'NAME', 'AMOUNT', 'DOCNUM', 'MEMO', 'CLEAR', 'PAYMETH'];
        $output .= implode($delimiter, $trnsHeaders) . "\r\n";

        // IIF Header for split lines (SPL section)
        $splHeaders = ['!SPL', 'SPLID', 'TRNSTYPE', 'DATE', 'ACCNT', 'NAME', 'AMOUNT', 'DOCNUM', 'MEMO', 'CLEAR'];
        $output .= implode($delimiter, $splHeaders) . "\r\n";

        // End transaction marker header
        $output .= "!ENDTRNS\r\n";

        // Data rows for each payment
        foreach ($payments as $payment) {
            $customerName = $this->formatCustomerName($payment);
            $paymentDate = $this->formatDate($payment['paymentDate']);
            $childName = $this->formatName($payment['childSurname'] ?? '', $payment['childPreferredName'] ?? '');
            $memo = $this->sanitizeField(sprintf('Payment for Invoice %s - %s', $payment['invoiceNumber'], $childName));
            $paymentMethod = $this->mapPaymentMethod($payment['method']);
            $reference = $this->sanitizeField($payment['reference'] ?? '');

            // TRNS line (debit to Undeposited Funds / Bank)
            $trnsRow = [
                'TRNS',
                '',
                self::IIF_TRNS_TYPE_PAYMENT,
                $paymentDate,
                $depositAccount,
                $customerName,
                $this->formatAmount($payment['amount']),
                $reference ?: 'PMT-' . $payment['gibbonEnhancedFinancePaymentID'],
                $memo,
                'N',
                $paymentMethod,
            ];
            $output .= implode($delimiter, $trnsRow) . "\r\n";

            // SPL line (credit to Accounts Receivable)
            $splRow = [
                'SPL',
                '',
                self::IIF_TRNS_TYPE_PAYMENT,
                $paymentDate,
                $arAccount,
                $customerName,
                '-' . $this->formatAmount($payment['amount']),  // Credit is negative
                $payment['invoiceNumber'],
                $this->sanitizeField('Apply to Invoice ' . $payment['invoiceNumber']),
                'N',
            ];
            $output .= implode($delimiter, $splRow) . "\r\n";

            // End transaction marker
            $output .= "ENDTRNS\r\n";
        }

        return $output;
    }

    /**
     * Format date for QuickBooks IIF (M/D/YY format).
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
     * Format amount for QuickBooks IIF.
     * Uses decimal point, no thousands separator, no currency symbol.
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
     * Format customer name from family name.
     *
     * @param array $record
     * @return string
     */
    protected function formatCustomerName($record)
    {
        $familyName = $record['familyName'] ?? '';
        $familyID = $record['gibbonFamilyID'] ?? '';

        if (empty($familyName)) {
            return 'Family-' . str_pad((string) $familyID, 6, '0', STR_PAD_LEFT);
        }

        return $this->sanitizeField($familyName);
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
     * Map internal payment method to QuickBooks compatible format.
     *
     * @param string $method
     * @return string
     */
    protected function mapPaymentMethod($method)
    {
        $methodMap = [
            'Cash' => 'Cash',
            'Cheque' => 'Check',
            'ETransfer' => 'E-Transfer',
            'CreditCard' => 'Credit Card',
            'DebitCard' => 'Debit Card',
            'Other' => 'Other',
        ];

        return $methodMap[$method] ?? 'Other';
    }

    /**
     * Sanitize field for IIF export.
     * Removes tabs, newlines, and other control characters.
     *
     * @param string $field
     * @return string
     */
    protected function sanitizeField($field)
    {
        if ($field === null) {
            return '';
        }

        // Remove tabs (delimiter) and newlines
        $field = str_replace(["\t", "\r\n", "\r", "\n"], ' ', $field);

        // Remove other control characters
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

        return implode('_', $parts) . '.iif';
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
        $fileName = $this->generateFileName('quickbooks_' . $subType, $gibbonSchoolYearID, $dateFrom, $dateTo);

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
     * Get list of required settings for QuickBooks export.
     *
     * @return array
     */
    public function getRequiredSettings()
    {
        return [
            'quickbooksAccountsReceivable' => $this->settingGateway->getSettingByScope('Enhanced Finance', 'quickbooksAccountsReceivable'),
            'quickbooksRevenueAccount' => $this->settingGateway->getSettingByScope('Enhanced Finance', 'quickbooksRevenueAccount'),
            'quickbooksDepositAccount' => $this->settingGateway->getSettingByScope('Enhanced Finance', 'quickbooksDepositAccount'),
            'quickbooksTaxAccount' => $this->settingGateway->getSettingByScope('Enhanced Finance', 'quickbooksTaxAccount'),
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

        // Only AR and Revenue are required, others have defaults
        $required = ['quickbooksAccountsReceivable', 'quickbooksRevenueAccount'];

        foreach ($required as $name) {
            if (empty($settings[$name])) {
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
                'Transaction Type', 'Date', 'Customer', 'Invoice Number',
                'Subtotal', 'Tax', 'Total', 'Due Date', 'Child Name', 'Status',
            ];

            $previewData = [];
            foreach (array_slice($records, 0, $limit) as $invoice) {
                $previewData[] = [
                    'type' => 'Invoice',
                    'date' => $this->formatDate($invoice['invoiceDate']),
                    'customer' => $this->formatCustomerName($invoice),
                    'invoiceNumber' => $invoice['invoiceNumber'],
                    'subtotal' => $this->formatAmount($invoice['subtotal']),
                    'tax' => $this->formatAmount($invoice['taxAmount']),
                    'total' => $this->formatAmount($invoice['totalAmount']),
                    'dueDate' => $this->formatDate($invoice['dueDate']),
                    'childName' => $this->formatName($invoice['childSurname'] ?? '', $invoice['childPreferredName'] ?? ''),
                    'status' => $invoice['status'],
                ];
            }
        } else {
            $records = $this->fetchPayments($gibbonSchoolYearID, $dateFrom, $dateTo);
            $headers = [
                'Transaction Type', 'Date', 'Customer', 'Invoice Number',
                'Amount', 'Payment Method', 'Reference', 'Child Name',
            ];

            $previewData = [];
            foreach (array_slice($records, 0, $limit) as $payment) {
                $previewData[] = [
                    'type' => 'Payment',
                    'date' => $this->formatDate($payment['paymentDate']),
                    'customer' => $this->formatCustomerName($payment),
                    'invoiceNumber' => $payment['invoiceNumber'],
                    'amount' => $this->formatAmount($payment['amount']),
                    'paymentMethod' => $this->mapPaymentMethod($payment['method']),
                    'reference' => $payment['reference'] ?? '',
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

    /**
     * Generate customer list in IIF format for import into QuickBooks.
     * This creates customer records that match the family names used in invoices.
     *
     * @param int $gibbonSchoolYearID
     * @return string IIF content for customer list
     */
    public function generateCustomerListIif($gibbonSchoolYearID)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID];
        $sql = "SELECT DISTINCT
                f.gibbonFamilyID,
                f.name AS familyName,
                f.nameAddress,
                f.homeAddress,
                f.homeAddressDistrict,
                f.homeAddressCountry
            FROM gibbonEnhancedFinanceInvoice i
            INNER JOIN gibbonFamily f ON i.gibbonFamilyID = f.gibbonFamilyID
            WHERE i.gibbonSchoolYearID = :gibbonSchoolYearID
            ORDER BY f.name ASC";

        try {
            $result = $this->db->select($sql, $data);
            $families = $result ? $result->fetchAll() : [];
        } catch (\Exception $e) {
            $families = [];
        }

        if (empty($families)) {
            return '';
        }

        $output = '';
        $delimiter = $this->config['delimiter'];

        // Customer list header
        $headers = ['!CUST', 'NAME', 'BADDR1', 'BADDR2', 'BADDR3', 'BADDR4', 'BADDR5'];
        $output .= implode($delimiter, $headers) . "\r\n";

        foreach ($families as $family) {
            $row = [
                'CUST',
                $this->sanitizeField($family['familyName']),
                $this->sanitizeField($family['nameAddress'] ?? ''),
                $this->sanitizeField($family['homeAddress'] ?? ''),
                $this->sanitizeField($family['homeAddressDistrict'] ?? ''),
                $this->sanitizeField($family['homeAddressCountry'] ?? ''),
                '',  // Additional address line
            ];
            $output .= implode($delimiter, $row) . "\r\n";
        }

        return $output;
    }
}
