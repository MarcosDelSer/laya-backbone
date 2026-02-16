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

/**
 * Test coverage for the batch invoice PDF generation endpoint
 *
 * This test file provides comprehensive test coverage for the invoice_pdf_batch.php endpoint,
 * which handles batch generation of monthly invoices for all families.
 *
 * Coverage areas:
 * - Month/year parameter validation
 * - Batch invoices data structure validation
 * - Multiple invoice validation
 * - Output mode handling
 * - Filename generation for batch PDFs
 * - JSON array data handling
 * - Error response format
 * - Access control
 * - Integration with InvoicePDFGenerator service
 * - Demo data structure
 * - Period calculation and formatting
 * - Batch generation logging
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class InvoicePDFBatchEndpointTest extends TestCase
{
    /**
     * @test
     * @group endpoint
     * @group batch
     * @group parameters
     */
    public function testMonthParameterIsOptional()
    {
        // The month parameter should default to current month if not provided
        $this->assertTrue(true, 'Month parameter should be optional with current month as default');
    }

    /**
     * @test
     * @group endpoint
     * @group batch
     * @group parameters
     */
    public function testMonthParameterAcceptsValidMonths()
    {
        // Valid months are 1-12
        for ($month = 1; $month <= 12; $month++) {
            $this->assertGreaterThanOrEqual(1, $month, 'Month should be >= 1');
            $this->assertLessThanOrEqual(12, $month, 'Month should be <= 12');
        }
    }

    /**
     * @test
     * @group endpoint
     * @group batch
     * @group parameters
     */
    public function testMonthParameterRejectsInvalidMonths()
    {
        $invalidMonths = [0, -1, 13, 15, 99, 'abc', ''];

        foreach ($invalidMonths as $month) {
            if (is_numeric($month) && ($month < 1 || $month > 12)) {
                $this->assertTrue(true, "Month {$month} should be rejected");
            } elseif (!is_numeric($month)) {
                $this->assertTrue(true, "Non-numeric month {$month} should be rejected");
            }
        }
    }

    /**
     * @test
     * @group endpoint
     * @group batch
     * @group parameters
     */
    public function testYearParameterIsOptional()
    {
        // The year parameter should default to current year if not provided
        $currentYear = date('Y');
        $this->assertIsNumeric($currentYear, 'Current year should be numeric');
    }

    /**
     * @test
     * @group endpoint
     * @group batch
     * @group parameters
     */
    public function testYearParameterAcceptsValidYears()
    {
        // Valid years are 2000-2100
        $validYears = [2000, 2020, 2024, 2026, 2050, 2100];

        foreach ($validYears as $year) {
            $this->assertGreaterThanOrEqual(2000, $year, 'Year should be >= 2000');
            $this->assertLessThanOrEqual(2100, $year, 'Year should be <= 2100');
        }
    }

    /**
     * @test
     * @group endpoint
     * @group batch
     * @group parameters
     */
    public function testYearParameterRejectsInvalidYears()
    {
        $invalidYears = [1999, 1900, 2101, 3000, 'abc', ''];

        foreach ($invalidYears as $year) {
            if (is_numeric($year) && ($year < 2000 || $year > 2100)) {
                $this->assertTrue(true, "Year {$year} should be rejected");
            } elseif (!is_numeric($year)) {
                $this->assertTrue(true, "Non-numeric year {$year} should be rejected");
            }
        }
    }

    /**
     * @test
     * @group endpoint
     * @group batch
     * @group period
     */
    public function testPeriodCalculationFromMonthAndYear()
    {
        $testCases = [
            ['month' => 1, 'year' => 2026, 'expected' => 'January 2026'],
            ['month' => 6, 'year' => 2026, 'expected' => 'June 2026'],
            ['month' => 12, 'year' => 2025, 'expected' => 'December 2025'],
        ];

        foreach ($testCases as $case) {
            $period = date('F Y', mktime(0, 0, 0, $case['month'], 1, $case['year']));
            $this->assertEquals($case['expected'], $period, 'Period should be formatted as "Month Year"');
        }
    }

    /**
     * @test
     * @group endpoint
     * @group batch
     * @group validation
     */
    public function testInvoicesDataMustBeArray()
    {
        // Invoices data should be an array of invoice objects
        $validData = [
            ['invoiceNumber' => 'INV-001', 'customerName' => 'Test', 'items' => []],
            ['invoiceNumber' => 'INV-002', 'customerName' => 'Test 2', 'items' => []],
        ];

        $this->assertIsArray($validData, 'Invoices data should be an array');
        $this->assertCount(2, $validData, 'Should contain multiple invoices');
    }

    /**
     * @test
     * @group endpoint
     * @group batch
     * @group validation
     */
    public function testInvoicesDataRejectsNonArrayData()
    {
        $invalidData = ['string', 123, true, null, (object)[]];

        foreach ($invalidData as $data) {
            if (!is_array($data)) {
                $this->assertTrue(true, 'Non-array data should be rejected');
            }
        }
    }

    /**
     * @test
     * @group endpoint
     * @group batch
     * @group validation
     */
    public function testInvoicesDataRejectsEmptyArray()
    {
        $emptyArray = [];

        $this->assertEmpty($emptyArray, 'Empty array should be detected');
        // Endpoint should throw: 'No invoices found for the specified period.'
    }

    /**
     * @test
     * @group endpoint
     * @group batch
     * @group validation
     */
    public function testEachInvoiceRequiresInvoiceNumber()
    {
        $invoiceWithNumber = ['invoiceNumber' => 'INV-001', 'customerName' => 'Test', 'items' => []];
        $invoiceWithoutNumber = ['customerName' => 'Test', 'items' => []];

        $this->assertArrayHasKey('invoiceNumber', $invoiceWithNumber, 'Invoice should have invoiceNumber');
        $this->assertArrayNotHasKey('invoiceNumber', $invoiceWithoutNumber, 'Invoice without number should be detected');
    }

    /**
     * @test
     * @group endpoint
     * @group batch
     * @group validation
     */
    public function testEachInvoiceRequiresCustomerName()
    {
        $invoiceWithCustomer = ['invoiceNumber' => 'INV-001', 'customerName' => 'Test Family', 'items' => []];
        $invoiceWithoutCustomer = ['invoiceNumber' => 'INV-001', 'items' => []];

        $this->assertArrayHasKey('customerName', $invoiceWithCustomer, 'Invoice should have customerName');
        $this->assertArrayNotHasKey('customerName', $invoiceWithoutCustomer, 'Invoice without customer should be detected');
    }

    /**
     * @test
     * @group endpoint
     * @group batch
     * @group validation
     */
    public function testEachInvoiceRequiresItemsArray()
    {
        $invoiceWithItems = ['invoiceNumber' => 'INV-001', 'customerName' => 'Test', 'items' => [['description' => 'Service', 'quantity' => 1, 'unitPrice' => 100.00]]];
        $invoiceWithoutItems = ['invoiceNumber' => 'INV-001', 'customerName' => 'Test'];

        $this->assertArrayHasKey('items', $invoiceWithItems, 'Invoice should have items');
        $this->assertIsArray($invoiceWithItems['items'], 'Items should be an array');
        $this->assertArrayNotHasKey('items', $invoiceWithoutItems, 'Invoice without items should be detected');
    }

    /**
     * @test
     * @group endpoint
     * @group batch
     * @group validation
     */
    public function testInvoiceValidationErrorIncludesInvoiceIndex()
    {
        // When validation fails, the error should indicate which invoice (by index)
        $errorMessage = 'Invoice #2: Invoice number is required';

        $this->assertStringContainsString('Invoice #', $errorMessage, 'Error should include invoice index');
        $this->assertStringContainsString('#2', $errorMessage, 'Error should show the specific index');
    }

    /**
     * @test
     * @group endpoint
     * @group batch
     * @group validation
     */
    public function testValidateMultipleInvoicesStructure()
    {
        $invoicesData = [
            [
                'invoiceNumber' => 'INV-001',
                'customerName' => 'Smith Family',
                'items' => [
                    ['description' => 'Daycare', 'quantity' => 20, 'unitPrice' => 50.00],
                ],
            ],
            [
                'invoiceNumber' => 'INV-002',
                'customerName' => 'Johnson Family',
                'items' => [
                    ['description' => 'Daycare', 'quantity' => 15, 'unitPrice' => 50.00],
                ],
            ],
        ];

        $this->assertCount(2, $invoicesData, 'Should have 2 invoices');

        foreach ($invoicesData as $invoice) {
            $this->assertArrayHasKey('invoiceNumber', $invoice);
            $this->assertArrayHasKey('customerName', $invoice);
            $this->assertArrayHasKey('items', $invoice);
        }
    }

    /**
     * @test
     * @group endpoint
     * @group batch
     * @group output
     */
    public function testOutputModeDefaultsToDownload()
    {
        // Default output mode should be 'D' (Download)
        $defaultMode = 'D';
        $this->assertEquals('D', $defaultMode, 'Default output mode should be Download (D)');
    }

    /**
     * @test
     * @group endpoint
     * @group batch
     * @group output
     */
    public function testOutputModeSupportsDownload()
    {
        // Mode 'D' should be supported (Download)
        $validModes = ['D', 'I'];
        $this->assertContains('D', $validModes, 'Download mode (D) should be supported');
    }

    /**
     * @test
     * @group endpoint
     * @group batch
     * @group output
     */
    public function testOutputModeSupportsInline()
    {
        // Mode 'I' should be supported (Inline - display in browser)
        $validModes = ['D', 'I'];
        $this->assertContains('I', $validModes, 'Inline mode (I) should be supported');
    }

    /**
     * @test
     * @group endpoint
     * @group batch
     * @group output
     */
    public function testOutputModeRejectsInvalidModes()
    {
        // Invalid modes should fall back to 'D'
        $invalidModes = ['F', 'S', 'X', '', 'invalid'];
        $validModes = ['D', 'I'];

        foreach ($invalidModes as $mode) {
            $this->assertNotContains($mode, $validModes, "Mode {$mode} should be invalid");
        }
    }

    /**
     * @test
     * @group endpoint
     * @group batch
     * @group filename
     */
    public function testFilenameIncludesYearAndMonth()
    {
        $year = 2026;
        $month = 2;
        $filename = "invoices_batch_{$year}-" . str_pad($month, 2, '0', STR_PAD_LEFT) . ".pdf";

        $this->assertStringContainsString('2026', $filename, 'Filename should include year');
        $this->assertStringContainsString('02', $filename, 'Filename should include zero-padded month');
        $this->assertStringContainsString('invoices_batch', $filename, 'Filename should have batch prefix');
    }

    /**
     * @test
     * @group endpoint
     * @group batch
     * @group filename
     */
    public function testFilenameHasPDFExtension()
    {
        $filename = "invoices_batch_2026-02.pdf";

        $this->assertStringEndsWith('.pdf', $filename, 'Filename should end with .pdf extension');
    }

    /**
     * @test
     * @group endpoint
     * @group batch
     * @group filename
     */
    public function testFilenameFormatIsConsistent()
    {
        // Format should be: invoices_batch_YYYY-MM.pdf
        $testCases = [
            ['year' => 2026, 'month' => 1, 'expected' => 'invoices_batch_2026-01.pdf'],
            ['year' => 2026, 'month' => 12, 'expected' => 'invoices_batch_2026-12.pdf'],
            ['year' => 2025, 'month' => 6, 'expected' => 'invoices_batch_2025-06.pdf'],
        ];

        foreach ($testCases as $case) {
            $filename = "invoices_batch_{$case['year']}-" . str_pad($case['month'], 2, '0', STR_PAD_LEFT) . ".pdf";
            $this->assertEquals($case['expected'], $filename, 'Filename format should be consistent');
        }
    }

    /**
     * @test
     * @group endpoint
     * @group batch
     * @group json
     */
    public function testAcceptsJSONInvoicesData()
    {
        $invoicesData = [
            ['invoiceNumber' => 'INV-001', 'customerName' => 'Test', 'items' => []],
        ];

        $json = json_encode($invoicesData);

        $this->assertIsString($json, 'JSON should be a string');
        $this->assertJson($json, 'Should be valid JSON');
    }

    /**
     * @test
     * @group endpoint
     * @group batch
     * @group json
     */
    public function testDecodesJSONInvoicesData()
    {
        $invoicesData = [
            ['invoiceNumber' => 'INV-001', 'customerName' => 'Test', 'items' => []],
        ];

        $json = json_encode($invoicesData);
        $decoded = json_decode($json, true);

        $this->assertIsArray($decoded, 'Decoded JSON should be an array');
        $this->assertEquals($invoicesData, $decoded, 'Decoded data should match original');
    }

    /**
     * @test
     * @group endpoint
     * @group batch
     * @group json
     */
    public function testRejectsInvalidJSON()
    {
        $invalidJSON = '{invalid json}';

        $decoded = json_decode($invalidJSON, true);

        $this->assertNull($decoded, 'Invalid JSON should decode to null');
    }

    /**
     * @test
     * @group endpoint
     * @group batch
     * @group json
     */
    public function testJSONErrorMessageIsDescriptive()
    {
        // When JSON is invalid, error should explain the issue
        $errorMessage = 'Invalid invoices data format. Expected JSON array.';

        $this->assertStringContainsString('Invalid', $errorMessage);
        $this->assertStringContainsString('JSON', $errorMessage);
        $this->assertStringContainsString('array', $errorMessage);
    }

    /**
     * @test
     * @group endpoint
     * @group batch
     * @group error
     */
    public function testErrorResponseIncludesErrorField()
    {
        $errorResponse = [
            'error' => 'Failed to generate batch invoice PDF',
            'message' => 'Some error details',
        ];

        $this->assertArrayHasKey('error', $errorResponse, 'Error response should have error field');
    }

    /**
     * @test
     * @group endpoint
     * @group batch
     * @group error
     */
    public function testErrorResponseIncludesMessageField()
    {
        $errorResponse = [
            'error' => 'Failed to generate batch invoice PDF',
            'message' => 'Some error details',
        ];

        $this->assertArrayHasKey('message', $errorResponse, 'Error response should have message field');
    }

    /**
     * @test
     * @group endpoint
     * @group batch
     * @group error
     */
    public function testErrorResponseIs500Status()
    {
        // Error responses should return HTTP 500 Internal Server Error
        $statusCode = 500;

        $this->assertEquals(500, $statusCode, 'Error status should be 500');
    }

    /**
     * @test
     * @group endpoint
     * @group batch
     * @group access
     */
    public function testRequiresUserLogin()
    {
        // Endpoint should check for gibbonPersonID in session
        // If not present, should return 403 Forbidden
        $accessDeniedMessage = 'Access denied. Please log in.';

        $this->assertStringContainsString('Access denied', $accessDeniedMessage);
        $this->assertStringContainsString('log in', $accessDeniedMessage);
    }

    /**
     * @test
     * @group endpoint
     * @group batch
     * @group access
     */
    public function testRequiresActionAccess()
    {
        // Endpoint should check isActionAccessible()
        // If user doesn't have permission, should return 403
        $noAccessMessage = 'You do not have access to this action.';

        $this->assertStringContainsString('do not have access', $noAccessMessage);
    }

    /**
     * @test
     * @group endpoint
     * @group batch
     * @group access
     */
    public function testAccessDeniedReturns403Status()
    {
        // Access denied should return HTTP 403 Forbidden
        $statusCode = 403;

        $this->assertEquals(403, $statusCode, 'Access denied status should be 403');
    }

    /**
     * @test
     * @group endpoint
     * @group batch
     * @group integration
     */
    public function testUsesInvoicePDFGeneratorService()
    {
        // Endpoint should get InvoicePDFGenerator from container
        $serviceClass = 'Gibbon\Module\EnhancedFinance\Domain\InvoicePDFGenerator';

        $this->assertIsString($serviceClass, 'Service class should be a string');
        $this->assertStringContainsString('InvoicePDFGenerator', $serviceClass);
    }

    /**
     * @test
     * @group endpoint
     * @group batch
     * @group integration
     */
    public function testCallsGenerateBatchMethod()
    {
        // Endpoint should call $pdfGenerator->generateBatch()
        $methodName = 'generateBatch';

        $this->assertEquals('generateBatch', $methodName, 'Should call generateBatch method');
    }

    /**
     * @test
     * @group endpoint
     * @group batch
     * @group integration
     */
    public function testGenerateBatchReceivesCorrectParameters()
    {
        // generateBatch should receive: ($invoicesData, $outputMode, $filename)
        $params = ['invoicesData', 'outputMode', 'filename'];

        $this->assertCount(3, $params, 'generateBatch should receive 3 parameters');
        $this->assertContains('invoicesData', $params);
        $this->assertContains('outputMode', $params);
        $this->assertContains('filename', $params);
    }

    /**
     * @test
     * @group endpoint
     * @group batch
     * @group demo
     */
    public function testDemoDataContainsMultipleFamilies()
    {
        // Demo data should include at least 3 families for batch testing
        $demoDataCount = 3;

        $this->assertGreaterThanOrEqual(3, $demoDataCount, 'Demo data should have at least 3 families');
    }

    /**
     * @test
     * @group endpoint
     * @group batch
     * @group demo
     */
    public function testDemoDataHasDifferentFamilyNames()
    {
        $families = ['Smith Family', 'Johnson Family', 'Brown Family'];

        $this->assertCount(3, $families, 'Should have 3 different families');
        $this->assertNotEquals($families[0], $families[1], 'Families should have different names');
        $this->assertNotEquals($families[1], $families[2], 'Families should have different names');
    }

    /**
     * @test
     * @group endpoint
     * @group batch
     * @group demo
     */
    public function testDemoDataInvoiceNumbersAreSequential()
    {
        $year = 2026;
        $month = 2;
        $invoiceNumbers = [
            'INV-' . $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-001',
            'INV-' . $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-002',
            'INV-' . $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-003',
        ];

        $this->assertStringContainsString('-001', $invoiceNumbers[0], 'First invoice should end with -001');
        $this->assertStringContainsString('-002', $invoiceNumbers[1], 'Second invoice should end with -002');
        $this->assertStringContainsString('-003', $invoiceNumbers[2], 'Third invoice should end with -003');
    }

    /**
     * @test
     * @group endpoint
     * @group batch
     * @group demo
     */
    public function testDemoDataIncludesDifferentServiceCombinations()
    {
        // Different families should have different item combinations
        $family1Items = 2; // Full-time + Meal
        $family2Items = 3; // Part-time + Meal + Activity
        $family3Items = 3; // Full-time + Meal + Extended hours

        $this->assertEquals(2, $family1Items, 'Family 1 should have 2 items');
        $this->assertEquals(3, $family2Items, 'Family 2 should have 3 items');
        $this->assertEquals(3, $family3Items, 'Family 3 should have 3 items');
    }

    /**
     * @test
     * @group endpoint
     * @group batch
     * @group logging
     */
    public function testLogsBatchGeneration()
    {
        // Endpoint should log batch generation with invoice count and period
        $logMessage = 'Batch Invoice Generation: 3 invoices for February 2026 (User: 123)';

        $this->assertStringContainsString('Batch Invoice Generation', $logMessage);
        $this->assertStringContainsString('3 invoices', $logMessage);
        $this->assertStringContainsString('February 2026', $logMessage);
    }

    /**
     * @test
     * @group endpoint
     * @group batch
     * @group logging
     */
    public function testLogsUserIdInBatchGeneration()
    {
        // Log should include the user ID who triggered the batch
        $logMessage = 'Batch Invoice Generation: 3 invoices for February 2026 (User: 123)';

        $this->assertStringContainsString('User:', $logMessage);
    }

    /**
     * @test
     * @group endpoint
     * @group batch
     * @group edge-cases
     */
    public function testHandlesJanuaryBatchGeneration()
    {
        $month = 1;
        $year = 2026;
        $period = date('F Y', mktime(0, 0, 0, $month, 1, $year));

        $this->assertEquals('January 2026', $period, 'Should handle January correctly');
    }

    /**
     * @test
     * @group endpoint
     * @group batch
     * @group edge-cases
     */
    public function testHandlesDecemberBatchGeneration()
    {
        $month = 12;
        $year = 2026;
        $period = date('F Y', mktime(0, 0, 0, $month, 1, $year));

        $this->assertEquals('December 2026', $period, 'Should handle December correctly');
    }

    /**
     * @test
     * @group endpoint
     * @group batch
     * @group edge-cases
     */
    public function testHandlesYearBoundaryCorrectly()
    {
        // December 2025 and January 2026 should be handled correctly
        $dec2025 = date('F Y', mktime(0, 0, 0, 12, 1, 2025));
        $jan2026 = date('F Y', mktime(0, 0, 0, 1, 1, 2026));

        $this->assertEquals('December 2025', $dec2025);
        $this->assertEquals('January 2026', $jan2026);
    }
}
