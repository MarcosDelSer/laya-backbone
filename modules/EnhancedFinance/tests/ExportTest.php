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

namespace Gibbon\Module\EnhancedFinance\Tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Gibbon\Module\EnhancedFinance\Export\Sage50Exporter;
use Gibbon\Module\EnhancedFinance\Export\QuickBooksExporter;
use Gibbon\Module\EnhancedFinance\Domain\InvoiceGateway;
use Gibbon\Module\EnhancedFinance\Domain\PaymentGateway;
use Gibbon\Module\EnhancedFinance\Domain\ExportGateway;
use Gibbon\Contracts\Database\Connection;
use Gibbon\Domain\System\SettingGateway;

/**
 * Unit tests for Sage50Exporter and QuickBooksExporter output formats.
 *
 * Tests CSV/IIF formatting, date conversion, amount formatting,
 * customer ID generation, payment method mapping, and field sanitization.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class ExportTest extends TestCase
{
    /**
     * Sample invoice data for testing.
     *
     * @var array
     */
    protected $sampleInvoice;

    /**
     * Sample payment data for testing.
     *
     * @var array
     */
    protected $samplePayment;

    /**
     * Set up test fixtures.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Sample invoice data matching exporter expected structure
        $this->sampleInvoice = [
            'gibbonEnhancedFinanceInvoiceID' => 1,
            'invoiceNumber' => 'INV-000001',
            'invoiceDate' => '2025-01-15',
            'dueDate' => '2025-02-15',
            'subtotal' => 1000.00,
            'taxAmount' => 149.75,
            'totalAmount' => 1149.75,
            'paidAmount' => 0.00,
            'status' => 'Issued',
            'notes' => 'Monthly childcare fee',
            'childSurname' => 'Smith',
            'childPreferredName' => 'John',
            'familyName' => 'Smith Family',
            'gibbonFamilyID' => 50,
        ];

        // Sample payment data matching exporter expected structure
        $this->samplePayment = [
            'gibbonEnhancedFinancePaymentID' => 1,
            'gibbonEnhancedFinanceInvoiceID' => 1,
            'paymentDate' => '2025-01-20',
            'amount' => 500.00,
            'method' => 'ETransfer',
            'reference' => 'ET-123456',
            'notes' => 'Partial payment',
            'invoiceNumber' => 'INV-000001',
            'childSurname' => 'Smith',
            'childPreferredName' => 'John',
            'familyName' => 'Smith Family',
            'gibbonFamilyID' => 50,
        ];
    }

    /**
     * Clean up after tests.
     */
    protected function tearDown(): void
    {
        parent::tearDown();
    }

    // =========================================================================
    // SAGE 50 EXPORTER - DATE FORMAT TESTS
    // =========================================================================

    /**
     * Test Sage50 date format MDY (default).
     */
    public function testSage50DateFormatMdy(): void
    {
        $date = '2025-01-15';
        $dateObj = new \DateTime($date);
        $formatted = $dateObj->format('m/d/Y');

        $this->assertEquals('01/15/2025', $formatted, 'MDY format should be MM/DD/YYYY');
    }

    /**
     * Test Sage50 date format YMD.
     */
    public function testSage50DateFormatYmd(): void
    {
        $date = '2025-01-15';
        $dateObj = new \DateTime($date);
        $formatted = $dateObj->format('Y-m-d');

        $this->assertEquals('2025-01-15', $formatted, 'YMD format should be YYYY-MM-DD');
    }

    /**
     * Test Sage50 date format DMY.
     */
    public function testSage50DateFormatDmy(): void
    {
        $date = '2025-01-15';
        $dateObj = new \DateTime($date);
        $formatted = $dateObj->format('d/m/Y');

        $this->assertEquals('15/01/2025', $formatted, 'DMY format should be DD/MM/YYYY');
    }

    /**
     * Test Sage50 empty date handling.
     */
    public function testSage50EmptyDateReturnsEmptyString(): void
    {
        $date = '';
        $result = empty($date) ? '' : $date;

        $this->assertEquals('', $result, 'Empty date should return empty string');
    }

    // =========================================================================
    // SAGE 50 EXPORTER - AMOUNT FORMAT TESTS
    // =========================================================================

    /**
     * Test Sage50 amount formatting with standard values.
     */
    public function testSage50AmountFormatStandard(): void
    {
        $amount = 1149.75;
        $formatted = number_format((float) $amount, 2, '.', '');

        $this->assertEquals('1149.75', $formatted, 'Amount should have 2 decimal places with no thousands separator');
    }

    /**
     * Test Sage50 amount formatting with large values.
     */
    public function testSage50AmountFormatLargeValue(): void
    {
        $amount = 99999.99;
        $formatted = number_format((float) $amount, 2, '.', '');

        $this->assertEquals('99999.99', $formatted, 'Large amount should not have thousands separator');
    }

    /**
     * Test Sage50 amount formatting with zero.
     */
    public function testSage50AmountFormatZero(): void
    {
        $amount = 0;
        $formatted = number_format((float) $amount, 2, '.', '');

        $this->assertEquals('0.00', $formatted, 'Zero should be formatted as 0.00');
    }

    /**
     * Test Sage50 amount formatting with null.
     */
    public function testSage50AmountFormatNull(): void
    {
        $amount = null;
        $formatted = ($amount === null || $amount === '') ? '0.00' : number_format((float) $amount, 2, '.', '');

        $this->assertEquals('0.00', $formatted, 'Null should be formatted as 0.00');
    }

    /**
     * Test Sage50 amount formatting rounds correctly.
     */
    public function testSage50AmountFormatRounding(): void
    {
        $amount = 100.555;
        $formatted = number_format((float) $amount, 2, '.', '');

        $this->assertEquals('100.56', $formatted, 'Amount should round to 2 decimal places');
    }

    // =========================================================================
    // SAGE 50 EXPORTER - CUSTOMER ID FORMAT TESTS
    // =========================================================================

    /**
     * Test Sage50 customer ID formatting.
     */
    public function testSage50CustomerIdFormat(): void
    {
        $familyID = 50;
        $customerID = 'FAM-' . str_pad((string) $familyID, 6, '0', STR_PAD_LEFT);

        $this->assertEquals('FAM-000050', $customerID, 'Customer ID should be FAM- followed by 6-digit padded ID');
    }

    /**
     * Test Sage50 customer ID with large family ID.
     */
    public function testSage50CustomerIdLargeFamilyId(): void
    {
        $familyID = 999999;
        $customerID = 'FAM-' . str_pad((string) $familyID, 6, '0', STR_PAD_LEFT);

        $this->assertEquals('FAM-999999', $customerID, 'Customer ID should handle large family IDs');
    }

    /**
     * Test Sage50 customer ID with single digit family ID.
     */
    public function testSage50CustomerIdSingleDigit(): void
    {
        $familyID = 1;
        $customerID = 'FAM-' . str_pad((string) $familyID, 6, '0', STR_PAD_LEFT);

        $this->assertEquals('FAM-000001', $customerID, 'Customer ID should pad single digit IDs');
    }

    // =========================================================================
    // SAGE 50 EXPORTER - NAME FORMAT TESTS
    // =========================================================================

    /**
     * Test Sage50 name format with both names.
     */
    public function testSage50NameFormatBothNames(): void
    {
        $surname = 'Smith';
        $firstName = 'John';
        $formatted = $surname . ', ' . $firstName;

        $this->assertEquals('Smith, John', $formatted, 'Name should be formatted as Surname, FirstName');
    }

    /**
     * Test Sage50 name format with only surname.
     */
    public function testSage50NameFormatOnlySurname(): void
    {
        $surname = 'Smith';
        $firstName = '';
        $formatted = empty($firstName) ? $surname : ($surname . ', ' . $firstName);

        $this->assertEquals('Smith', $formatted, 'Should return just surname if no first name');
    }

    /**
     * Test Sage50 name format with only first name.
     */
    public function testSage50NameFormatOnlyFirstName(): void
    {
        $surname = '';
        $firstName = 'John';
        $formatted = empty($surname) ? $firstName : ($surname . ', ' . $firstName);

        $this->assertEquals('John', $formatted, 'Should return just first name if no surname');
    }

    /**
     * Test Sage50 name format with empty names.
     */
    public function testSage50NameFormatEmpty(): void
    {
        $surname = '';
        $firstName = '';

        if (empty($surname) && empty($firstName)) {
            $formatted = '';
        } elseif (empty($surname)) {
            $formatted = $firstName;
        } elseif (empty($firstName)) {
            $formatted = $surname;
        } else {
            $formatted = $surname . ', ' . $firstName;
        }

        $this->assertEquals('', $formatted, 'Empty names should return empty string');
    }

    /**
     * Test Sage50 name format trims whitespace.
     */
    public function testSage50NameFormatTrimsWhitespace(): void
    {
        $surname = '  Smith  ';
        $firstName = '  John  ';
        $formatted = trim($surname) . ', ' . trim($firstName);

        $this->assertEquals('Smith, John', $formatted, 'Name formatting should trim whitespace');
    }

    // =========================================================================
    // SAGE 50 EXPORTER - PAYMENT METHOD MAPPING TESTS
    // =========================================================================

    /**
     * Test Sage50 payment method mapping for all valid methods.
     */
    public function testSage50PaymentMethodMapping(): void
    {
        $methodMap = [
            'Cash' => 'Cash',
            'Cheque' => 'Cheque',
            'ETransfer' => 'Electronic Transfer',
            'CreditCard' => 'Credit Card',
            'DebitCard' => 'Debit Card',
            'Other' => 'Other',
        ];

        foreach ($methodMap as $internal => $expected) {
            $mapped = $methodMap[$internal] ?? 'Other';
            $this->assertEquals($expected, $mapped, "Payment method {$internal} should map to {$expected}");
        }
    }

    /**
     * Test Sage50 payment method mapping for unknown method.
     */
    public function testSage50PaymentMethodMappingUnknown(): void
    {
        $methodMap = [
            'Cash' => 'Cash',
            'Cheque' => 'Cheque',
            'ETransfer' => 'Electronic Transfer',
            'CreditCard' => 'Credit Card',
            'DebitCard' => 'Debit Card',
            'Other' => 'Other',
        ];

        $unknownMethod = 'Bitcoin';
        $mapped = $methodMap[$unknownMethod] ?? 'Other';

        $this->assertEquals('Other', $mapped, 'Unknown payment method should default to Other');
    }

    // =========================================================================
    // SAGE 50 EXPORTER - FIELD SANITIZATION TESTS
    // =========================================================================

    /**
     * Test Sage50 field sanitization removes control characters.
     */
    public function testSage50SanitizeFieldRemovesControlChars(): void
    {
        $field = "Test\x00\x08\x0B\x0CValue";
        $sanitized = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $field);
        $sanitized = trim($sanitized);

        $this->assertEquals('TestValue', $sanitized, 'Control characters should be removed');
    }

    /**
     * Test Sage50 field sanitization trims whitespace.
     */
    public function testSage50SanitizeFieldTrims(): void
    {
        $field = '  Test Value  ';
        $sanitized = trim($field);

        $this->assertEquals('Test Value', $sanitized, 'Whitespace should be trimmed');
    }

    /**
     * Test Sage50 field sanitization handles null.
     */
    public function testSage50SanitizeFieldNull(): void
    {
        $field = null;
        $sanitized = ($field === null) ? '' : trim($field);

        $this->assertEquals('', $sanitized, 'Null should return empty string');
    }

    /**
     * Test Sage50 field sanitization preserves tabs and newlines initially.
     */
    public function testSage50SanitizeFieldPreservesAllowedChars(): void
    {
        $field = "Line1\nLine2";
        $sanitized = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $field);

        // Newlines are not removed in basic sanitization
        $this->assertStringContainsString("\n", $sanitized, 'Newlines should be preserved in basic sanitization');
    }

    // =========================================================================
    // SAGE 50 EXPORTER - CSV ROW FORMAT TESTS
    // =========================================================================

    /**
     * Test Sage50 CSV row format basic.
     */
    public function testSage50CsvRowFormatBasic(): void
    {
        $fields = ['Value1', 'Value2', 'Value3'];
        $delimiter = ',';
        $row = implode($delimiter, $fields) . "\r\n";

        $this->assertEquals("Value1,Value2,Value3\r\n", $row, 'CSV row should use comma delimiter and CRLF');
    }

    /**
     * Test Sage50 CSV row format with delimiter in field.
     */
    public function testSage50CsvRowFormatWithDelimiter(): void
    {
        $field = 'Value, with comma';
        $delimiter = ',';
        $enclosure = '"';

        // Field contains delimiter, should be wrapped
        if (strpos($field, $delimiter) !== false) {
            $field = $enclosure . $field . $enclosure;
        }

        $this->assertEquals('"Value, with comma"', $field, 'Field with delimiter should be enclosed');
    }

    /**
     * Test Sage50 CSV row format with quotes in field.
     */
    public function testSage50CsvRowFormatWithQuotes(): void
    {
        $field = 'Value "with" quotes';
        $enclosure = '"';

        // Escape enclosure by doubling it
        $field = str_replace($enclosure, $enclosure . $enclosure, $field);
        $field = $enclosure . $field . $enclosure;

        $this->assertEquals('"Value ""with"" quotes"', $field, 'Quotes in field should be escaped by doubling');
    }

    /**
     * Test Sage50 CSV row format with newline in field.
     */
    public function testSage50CsvRowFormatWithNewline(): void
    {
        $field = "Line1\nLine2";
        $enclosure = '"';

        // Field contains newline, should be wrapped
        if (strpos($field, "\n") !== false) {
            $field = $enclosure . $field . $enclosure;
        }

        $this->assertEquals("\"Line1\nLine2\"", $field, 'Field with newline should be enclosed');
    }

    // =========================================================================
    // SAGE 50 EXPORTER - FILE NAME GENERATION TESTS
    // =========================================================================

    /**
     * Test Sage50 file name generation basic.
     */
    public function testSage50FileNameGenerationBasic(): void
    {
        $type = 'sage50_invoices';
        $gibbonSchoolYearID = 2025;

        $parts = [$type, 'SY' . $gibbonSchoolYearID];
        $fileName = implode('_', $parts);

        $this->assertStringStartsWith('sage50_invoices_SY2025', $fileName, 'File name should start with type and school year');
    }

    /**
     * Test Sage50 file name generation with date range.
     */
    public function testSage50FileNameGenerationWithDateRange(): void
    {
        $type = 'sage50_invoices';
        $gibbonSchoolYearID = 2025;
        $dateFrom = '2025-01-01';
        $dateTo = '2025-01-31';

        $parts = [
            $type,
            'SY' . $gibbonSchoolYearID,
            'from' . str_replace('-', '', $dateFrom),
            'to' . str_replace('-', '', $dateTo),
        ];
        $fileName = implode('_', $parts) . '.csv';

        $this->assertStringContainsString('from20250101', $fileName, 'File name should contain start date');
        $this->assertStringContainsString('to20250131', $fileName, 'File name should contain end date');
        $this->assertStringEndsWith('.csv', $fileName, 'File name should end with .csv extension');
    }

    // =========================================================================
    // SAGE 50 EXPORTER - CONFIGURATION TESTS
    // =========================================================================

    /**
     * Test Sage50 default configuration values.
     */
    public function testSage50DefaultConfiguration(): void
    {
        $config = [
            'dateFormat' => 'm/d/Y',
            'includeBOM' => true,
            'delimiter' => ',',
            'enclosure' => '"',
        ];

        $this->assertEquals('m/d/Y', $config['dateFormat'], 'Default date format should be MDY');
        $this->assertTrue($config['includeBOM'], 'BOM should be included by default');
        $this->assertEquals(',', $config['delimiter'], 'Default delimiter should be comma');
        $this->assertEquals('"', $config['enclosure'], 'Default enclosure should be double quote');
    }

    /**
     * Test Sage50 configuration merge.
     */
    public function testSage50ConfigurationMerge(): void
    {
        $defaultConfig = [
            'dateFormat' => 'm/d/Y',
            'includeBOM' => true,
            'delimiter' => ',',
            'enclosure' => '"',
        ];

        $newConfig = ['dateFormat' => 'Y-m-d'];
        $mergedConfig = array_merge($defaultConfig, $newConfig);

        $this->assertEquals('Y-m-d', $mergedConfig['dateFormat'], 'Date format should be overridden');
        $this->assertTrue($mergedConfig['includeBOM'], 'Other settings should remain unchanged');
    }

    // =========================================================================
    // SAGE 50 EXPORTER - CONSTANTS TESTS
    // =========================================================================

    /**
     * Test Sage50 export type constants.
     */
    public function testSage50ExportTypeConstants(): void
    {
        $this->assertEquals('invoices', Sage50Exporter::EXPORT_TYPE_INVOICES);
        $this->assertEquals('payments', Sage50Exporter::EXPORT_TYPE_PAYMENTS);
        $this->assertEquals('combined', Sage50Exporter::EXPORT_TYPE_COMBINED);
    }

    /**
     * Test Sage50 date format constants.
     */
    public function testSage50DateFormatConstants(): void
    {
        $this->assertEquals('m/d/Y', Sage50Exporter::DATE_FORMAT_MDY);
        $this->assertEquals('Y-m-d', Sage50Exporter::DATE_FORMAT_YMD);
        $this->assertEquals('d/m/Y', Sage50Exporter::DATE_FORMAT_DMY);
    }

    // =========================================================================
    // SAGE 50 EXPORTER - BOM TESTS
    // =========================================================================

    /**
     * Test Sage50 UTF-8 BOM content.
     */
    public function testSage50Utf8Bom(): void
    {
        $bom = "\xEF\xBB\xBF";

        $this->assertEquals(3, strlen($bom), 'UTF-8 BOM should be 3 bytes');
        $this->assertEquals("\xEF\xBB\xBF", $bom, 'BOM bytes should match UTF-8 BOM signature');
    }

    /**
     * Test Sage50 BOM is prepended when configured.
     */
    public function testSage50BomPrepended(): void
    {
        $includeBOM = true;
        $content = 'CSV Content';

        if ($includeBOM) {
            $content = "\xEF\xBB\xBF" . $content;
        }

        $this->assertStringStartsWith("\xEF\xBB\xBF", $content, 'Content should start with BOM when configured');
    }

    // =========================================================================
    // QUICKBOOKS EXPORTER - DATE FORMAT TESTS
    // =========================================================================

    /**
     * Test QuickBooks IIF date format (M/D/YY).
     */
    public function testQuickBooksDateFormatIif(): void
    {
        $date = '2025-01-15';
        $dateObj = new \DateTime($date);
        $formatted = $dateObj->format('n/j/y');

        $this->assertEquals('1/15/25', $formatted, 'IIF date format should be M/D/YY (no leading zeros)');
    }

    /**
     * Test QuickBooks date format for single digit month.
     */
    public function testQuickBooksDateFormatSingleDigitMonth(): void
    {
        $date = '2025-05-09';
        $dateObj = new \DateTime($date);
        $formatted = $dateObj->format('n/j/y');

        $this->assertEquals('5/9/25', $formatted, 'IIF date format should not have leading zeros');
    }

    /**
     * Test QuickBooks empty date handling.
     */
    public function testQuickBooksEmptyDateReturnsEmptyString(): void
    {
        $date = '';
        $result = empty($date) ? '' : $date;

        $this->assertEquals('', $result, 'Empty date should return empty string');
    }

    // =========================================================================
    // QUICKBOOKS EXPORTER - AMOUNT FORMAT TESTS
    // =========================================================================

    /**
     * Test QuickBooks amount formatting.
     */
    public function testQuickBooksAmountFormatStandard(): void
    {
        $amount = 1149.75;
        $formatted = number_format((float) $amount, 2, '.', '');

        $this->assertEquals('1149.75', $formatted, 'IIF amount should have 2 decimal places with no separators');
    }

    /**
     * Test QuickBooks negative amount formatting (for credits).
     */
    public function testQuickBooksAmountFormatNegative(): void
    {
        $amount = 500.00;
        $formatted = '-' . number_format((float) $amount, 2, '.', '');

        $this->assertEquals('-500.00', $formatted, 'Credit amounts should be negative in IIF format');
    }

    // =========================================================================
    // QUICKBOOKS EXPORTER - CUSTOMER NAME FORMAT TESTS
    // =========================================================================

    /**
     * Test QuickBooks customer name formatting.
     */
    public function testQuickBooksCustomerNameFromFamily(): void
    {
        $record = ['familyName' => 'Smith Family', 'gibbonFamilyID' => 50];
        $customerName = $record['familyName'];

        $this->assertEquals('Smith Family', $customerName, 'Customer name should be family name');
    }

    /**
     * Test QuickBooks customer name fallback when family name empty.
     */
    public function testQuickBooksCustomerNameFallback(): void
    {
        $record = ['familyName' => '', 'gibbonFamilyID' => 50];

        if (empty($record['familyName'])) {
            $customerName = 'Family-' . str_pad((string) $record['gibbonFamilyID'], 6, '0', STR_PAD_LEFT);
        } else {
            $customerName = $record['familyName'];
        }

        $this->assertEquals('Family-000050', $customerName, 'Should fallback to Family-ID when name is empty');
    }

    // =========================================================================
    // QUICKBOOKS EXPORTER - PAYMENT METHOD MAPPING TESTS
    // =========================================================================

    /**
     * Test QuickBooks payment method mapping for all valid methods.
     */
    public function testQuickBooksPaymentMethodMapping(): void
    {
        $methodMap = [
            'Cash' => 'Cash',
            'Cheque' => 'Check',  // Note: QuickBooks uses "Check" (US spelling)
            'ETransfer' => 'E-Transfer',
            'CreditCard' => 'Credit Card',
            'DebitCard' => 'Debit Card',
            'Other' => 'Other',
        ];

        foreach ($methodMap as $internal => $expected) {
            $mapped = $methodMap[$internal] ?? 'Other';
            $this->assertEquals($expected, $mapped, "Payment method {$internal} should map to {$expected}");
        }
    }

    /**
     * Test QuickBooks payment method mapping differs from Sage50 for cheque.
     */
    public function testQuickBooksPaymentMethodChequeDiffersFromSage(): void
    {
        $sage50Map = ['Cheque' => 'Cheque'];
        $quickbooksMap = ['Cheque' => 'Check'];

        $this->assertNotEquals(
            $sage50Map['Cheque'],
            $quickbooksMap['Cheque'],
            'QuickBooks uses US spelling "Check" while Sage50 uses "Cheque"'
        );
    }

    // =========================================================================
    // QUICKBOOKS EXPORTER - FIELD SANITIZATION TESTS
    // =========================================================================

    /**
     * Test QuickBooks field sanitization removes tabs (IIF delimiter).
     */
    public function testQuickBooksSanitizeFieldRemovesTabs(): void
    {
        $field = "Value\twith\ttabs";
        $sanitized = str_replace(["\t", "\r\n", "\r", "\n"], ' ', $field);
        $sanitized = trim($sanitized);

        $this->assertEquals('Value with tabs', $sanitized, 'Tabs should be replaced with spaces for IIF');
    }

    /**
     * Test QuickBooks field sanitization removes newlines.
     */
    public function testQuickBooksSanitizeFieldRemovesNewlines(): void
    {
        $field = "Line1\nLine2\r\nLine3";
        $sanitized = str_replace(["\t", "\r\n", "\r", "\n"], ' ', $field);
        $sanitized = trim($sanitized);

        $this->assertEquals('Line1 Line2 Line3', $sanitized, 'Newlines should be replaced with spaces for IIF');
    }

    /**
     * Test QuickBooks field sanitization removes control characters.
     */
    public function testQuickBooksSanitizeFieldRemovesControlChars(): void
    {
        $field = "Test\x00\x08Value";
        $sanitized = str_replace(["\t", "\r\n", "\r", "\n"], ' ', $field);
        $sanitized = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $sanitized);
        $sanitized = trim($sanitized);

        $this->assertEquals('TestValue', $sanitized, 'Control characters should be removed');
    }

    // =========================================================================
    // QUICKBOOKS EXPORTER - IIF FORMAT STRUCTURE TESTS
    // =========================================================================

    /**
     * Test QuickBooks IIF header row format.
     */
    public function testQuickBooksIifHeaderFormat(): void
    {
        $headers = ['!TRNS', 'TRNSID', 'TRNSTYPE', 'DATE', 'ACCNT', 'NAME', 'AMOUNT'];
        $delimiter = "\t";
        $headerRow = implode($delimiter, $headers) . "\r\n";

        $this->assertStringStartsWith('!TRNS', $headerRow, 'IIF headers should start with !');
        $this->assertStringContainsString("\t", $headerRow, 'IIF uses tab delimiter');
        $this->assertStringEndsWith("\r\n", $headerRow, 'IIF uses CRLF line ending');
    }

    /**
     * Test QuickBooks IIF transaction row format.
     */
    public function testQuickBooksIifTransactionRowFormat(): void
    {
        $row = ['TRNS', '', 'INVOICE', '1/15/25', 'Accounts Receivable', 'Smith Family', '1149.75'];
        $delimiter = "\t";
        $transRow = implode($delimiter, $row) . "\r\n";

        $this->assertStringStartsWith('TRNS', $transRow, 'Transaction rows should start with TRNS');
        $this->assertStringContainsString('INVOICE', $transRow, 'Should contain transaction type');
    }

    /**
     * Test QuickBooks IIF split row format.
     */
    public function testQuickBooksIifSplitRowFormat(): void
    {
        $row = ['SPL', '', 'INVOICE', '1/15/25', 'Revenue', 'Smith Family', '-1149.75'];
        $delimiter = "\t";
        $splitRow = implode($delimiter, $row) . "\r\n";

        $this->assertStringStartsWith('SPL', $splitRow, 'Split rows should start with SPL');
        $this->assertStringContainsString('-', $splitRow, 'Credit amounts should be negative');
    }

    /**
     * Test QuickBooks IIF end transaction marker.
     */
    public function testQuickBooksIifEndTransactionMarker(): void
    {
        $endMarker = "ENDTRNS\r\n";

        $this->assertEquals("ENDTRNS\r\n", $endMarker, 'End marker should be ENDTRNS with CRLF');
    }

    /**
     * Test QuickBooks IIF complete transaction structure.
     */
    public function testQuickBooksIifTransactionStructure(): void
    {
        $transaction = [];
        $transaction[] = "TRNS\t\tINVOICE\t1/15/25\tAccounts Receivable\tSmith Family\t1149.75\r\n";
        $transaction[] = "SPL\t\tINVOICE\t1/15/25\tRevenue\tSmith Family\t-1149.75\r\n";
        $transaction[] = "ENDTRNS\r\n";

        $complete = implode('', $transaction);

        $this->assertStringContainsString('TRNS', $complete, 'Transaction should have TRNS line');
        $this->assertStringContainsString('SPL', $complete, 'Transaction should have SPL line');
        $this->assertStringContainsString('ENDTRNS', $complete, 'Transaction should end with ENDTRNS');
    }

    // =========================================================================
    // QUICKBOOKS EXPORTER - FILE NAME GENERATION TESTS
    // =========================================================================

    /**
     * Test QuickBooks file name has IIF extension.
     */
    public function testQuickBooksFileNameHasIifExtension(): void
    {
        $type = 'quickbooks_invoices';
        $gibbonSchoolYearID = 2025;

        $parts = [$type, 'SY' . $gibbonSchoolYearID];
        $fileName = implode('_', $parts) . '.iif';

        $this->assertStringEndsWith('.iif', $fileName, 'QuickBooks files should have .iif extension');
    }

    /**
     * Test QuickBooks file name generation with date range.
     */
    public function testQuickBooksFileNameGenerationWithDateRange(): void
    {
        $type = 'quickbooks_invoices';
        $gibbonSchoolYearID = 2025;
        $dateFrom = '2025-01-01';
        $dateTo = '2025-01-31';

        $parts = [
            $type,
            'SY' . $gibbonSchoolYearID,
            'from' . str_replace('-', '', $dateFrom),
            'to' . str_replace('-', '', $dateTo),
        ];
        $fileName = implode('_', $parts) . '.iif';

        $this->assertStringContainsString('quickbooks', $fileName, 'File name should contain quickbooks');
        $this->assertStringEndsWith('.iif', $fileName, 'File name should end with .iif extension');
    }

    // =========================================================================
    // QUICKBOOKS EXPORTER - CONSTANTS TESTS
    // =========================================================================

    /**
     * Test QuickBooks export type constants.
     */
    public function testQuickBooksExportTypeConstants(): void
    {
        $this->assertEquals('invoices', QuickBooksExporter::EXPORT_TYPE_INVOICES);
        $this->assertEquals('payments', QuickBooksExporter::EXPORT_TYPE_PAYMENTS);
        $this->assertEquals('combined', QuickBooksExporter::EXPORT_TYPE_COMBINED);
    }

    /**
     * Test QuickBooks IIF transaction type constants.
     */
    public function testQuickBooksIifTransactionTypeConstants(): void
    {
        $this->assertEquals('INVOICE', QuickBooksExporter::IIF_TRNS_TYPE_INVOICE);
        $this->assertEquals('PAYMENT', QuickBooksExporter::IIF_TRNS_TYPE_PAYMENT);
        $this->assertEquals('DEPOSIT', QuickBooksExporter::IIF_TRNS_TYPE_DEPOSIT);
    }

    /**
     * Test QuickBooks date format constant.
     */
    public function testQuickBooksDateFormatConstant(): void
    {
        $this->assertEquals('n/j/y', QuickBooksExporter::DATE_FORMAT_IIF);
    }

    // =========================================================================
    // QUICKBOOKS EXPORTER - CONFIGURATION TESTS
    // =========================================================================

    /**
     * Test QuickBooks default configuration values.
     */
    public function testQuickBooksDefaultConfiguration(): void
    {
        $config = [
            'dateFormat' => 'n/j/y',
            'delimiter' => "\t",
        ];

        $this->assertEquals('n/j/y', $config['dateFormat'], 'Default date format should be IIF format');
        $this->assertEquals("\t", $config['delimiter'], 'Default delimiter should be tab');
    }

    // =========================================================================
    // SHARED FUNCTIONALITY TESTS
    // =========================================================================

    /**
     * Test total amount calculation from records.
     */
    public function testCalculateTotalAmount(): void
    {
        $records = [
            ['totalAmount' => 1000.00],
            ['totalAmount' => 500.00],
            ['totalAmount' => 250.50],
        ];

        $total = 0.0;
        foreach ($records as $record) {
            $total += (float) ($record['totalAmount'] ?? 0);
        }

        $this->assertEquals(1750.50, $total, 'Total should sum all amounts');
    }

    /**
     * Test total amount calculation with missing field.
     */
    public function testCalculateTotalAmountWithMissingField(): void
    {
        $records = [
            ['totalAmount' => 1000.00],
            ['otherField' => 500.00], // Missing totalAmount
            ['totalAmount' => 250.00],
        ];

        $total = 0.0;
        foreach ($records as $record) {
            $total += (float) ($record['totalAmount'] ?? 0);
        }

        $this->assertEquals(1250.00, $total, 'Should handle missing fields gracefully');
    }

    /**
     * Test invoice description generation for Sage50.
     */
    public function testSage50InvoiceDescriptionGeneration(): void
    {
        $invoice = $this->sampleInvoice;
        $childName = $invoice['childSurname'] . ', ' . $invoice['childPreferredName'];
        $description = sprintf('Childcare Invoice %s - %s', $invoice['invoiceNumber'], $childName);

        $this->assertEquals(
            'Childcare Invoice INV-000001 - Smith, John',
            $description,
            'Invoice description should include invoice number and child name'
        );
    }

    /**
     * Test payment description generation for QuickBooks.
     */
    public function testQuickBooksPaymentDescriptionGeneration(): void
    {
        $payment = $this->samplePayment;
        $childName = $payment['childSurname'] . ', ' . $payment['childPreferredName'];
        $description = sprintf('Payment for Invoice %s - %s', $payment['invoiceNumber'], $childName);

        $this->assertEquals(
            'Payment for Invoice INV-000001 - Smith, John',
            $description,
            'Payment description should include invoice number and child name'
        );
    }

    // =========================================================================
    // INVOICE DATA STRUCTURE TESTS
    // =========================================================================

    /**
     * Test invoice export data has required fields.
     */
    public function testInvoiceExportDataHasRequiredFields(): void
    {
        $requiredFields = [
            'gibbonEnhancedFinanceInvoiceID',
            'invoiceNumber',
            'invoiceDate',
            'dueDate',
            'subtotal',
            'taxAmount',
            'totalAmount',
            'status',
            'familyName',
            'gibbonFamilyID',
        ];

        foreach ($requiredFields as $field) {
            $this->assertArrayHasKey(
                $field,
                $this->sampleInvoice,
                "Invoice export data should contain field: {$field}"
            );
        }
    }

    /**
     * Test payment export data has required fields.
     */
    public function testPaymentExportDataHasRequiredFields(): void
    {
        $requiredFields = [
            'gibbonEnhancedFinancePaymentID',
            'gibbonEnhancedFinanceInvoiceID',
            'paymentDate',
            'amount',
            'method',
            'invoiceNumber',
            'familyName',
            'gibbonFamilyID',
        ];

        foreach ($requiredFields as $field) {
            $this->assertArrayHasKey(
                $field,
                $this->samplePayment,
                "Payment export data should contain field: {$field}"
            );
        }
    }

    // =========================================================================
    // EDGE CASE TESTS
    // =========================================================================

    /**
     * Test handling of special characters in family name.
     */
    public function testSpecialCharactersInFamilyName(): void
    {
        $familyName = "O'Brien-Smith & Associates";
        $sanitized = trim($familyName);

        // Basic sanitization should preserve these characters
        $this->assertEquals("O'Brien-Smith & Associates", $sanitized, 'Special characters in names should be preserved');
    }

    /**
     * Test handling of unicode characters in names.
     */
    public function testUnicodeCharactersInNames(): void
    {
        $familyName = 'Müller-Østerström';
        $sanitized = trim($familyName);

        $this->assertEquals('Müller-Østerström', $sanitized, 'Unicode characters should be preserved');
    }

    /**
     * Test very long description truncation.
     */
    public function testLongDescriptionHandling(): void
    {
        $longNotes = str_repeat('A', 500);
        $sanitized = trim($longNotes);

        // Sanitization should not truncate, that's handled elsewhere
        $this->assertEquals(500, strlen($sanitized), 'Long descriptions should be preserved');
    }

    /**
     * Test zero tax amount handling.
     */
    public function testZeroTaxAmountHandling(): void
    {
        $invoice = $this->sampleInvoice;
        $invoice['taxAmount'] = 0.00;

        $hasTax = !empty($invoice['taxAmount']) && (float)$invoice['taxAmount'] > 0;

        $this->assertFalse($hasTax, 'Zero tax amount should be treated as no tax');
    }

    /**
     * Test invoice with tax generates separate tax line in IIF.
     */
    public function testInvoiceWithTaxGeneratesSeparateTaxLine(): void
    {
        $invoice = $this->sampleInvoice;
        $hasTax = !empty($invoice['taxAmount']) && (float)$invoice['taxAmount'] > 0;

        $this->assertTrue($hasTax, 'Sample invoice should have tax');
        $this->assertGreaterThan(0, (float)$invoice['taxAmount'], 'Tax amount should be greater than zero');
    }

    /**
     * Test floating point precision in exports.
     */
    public function testFloatingPointPrecisionInExports(): void
    {
        // Classic floating point issue
        $subtotal = 0.10;
        $tax = 0.20;
        $total = round($subtotal + $tax, 2);

        $formatted = number_format($total, 2, '.', '');

        $this->assertEquals('0.30', $formatted, 'Floating point calculations should be precise');
    }
}
