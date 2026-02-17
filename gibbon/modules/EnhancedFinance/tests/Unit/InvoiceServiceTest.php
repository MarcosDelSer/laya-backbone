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

namespace Gibbon\Module\EnhancedFinance\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Gibbon\Module\EnhancedFinance\Service\InvoiceService;
use Gibbon\Module\EnhancedFinance\Domain\InvoiceGateway;
use Gibbon\Module\EnhancedFinance\Validator\InvoiceValidator;
use Gibbon\Domain\System\SettingGateway;

/**
 * Unit tests for InvoiceService.
 *
 * Tests tax calculations, invoice number generation, due date calculation,
 * balance calculations, status determination, and overdue checks.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class InvoiceServiceTest extends TestCase
{
    /**
     * @var InvoiceService
     */
    protected $service;

    /**
     * @var MockObject|SettingGateway
     */
    protected $settingGateway;

    /**
     * @var MockObject|InvoiceGateway
     */
    protected $invoiceGateway;

    /**
     * @var MockObject|InvoiceValidator
     */
    protected $validator;

    /**
     * Set up test fixtures.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create mocks
        $this->settingGateway = $this->createMock(SettingGateway::class);
        $this->invoiceGateway = $this->createMock(InvoiceGateway::class);
        $this->validator = $this->createMock(InvoiceValidator::class);

        // Configure default settings
        $this->settingGateway->method('getSettingByScope')
            ->willReturnCallback(function ($scope, $name) {
                $settings = [
                    'gstRate' => '0.05',
                    'qstRate' => '0.09975',
                    'invoicePrefix' => 'INV-',
                    'defaultPaymentTermsDays' => '30',
                ];
                return $settings[$name] ?? null;
            });

        // Create service
        $this->service = new InvoiceService(
            $this->settingGateway,
            $this->invoiceGateway,
            $this->validator
        );
    }

    /**
     * Clean up after tests.
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        $this->service = null;
        $this->settingGateway = null;
        $this->invoiceGateway = null;
        $this->validator = null;
    }

    // =========================================================================
    // TAX RATE TESTS
    // =========================================================================

    /**
     * Test getting tax rates from settings.
     */
    public function testGetTaxRatesReturnsCorrectRates(): void
    {
        $rates = $this->service->getTaxRates();

        $this->assertArrayHasKey('gst', $rates);
        $this->assertArrayHasKey('qst', $rates);
        $this->assertArrayHasKey('combined', $rates);

        $this->assertEquals(0.05, $rates['gst']);
        $this->assertEquals(0.09975, $rates['qst']);
        $this->assertEquals(0.14975, $rates['combined']);
    }

    /**
     * Test getting GST rate.
     */
    public function testGetGSTRate(): void
    {
        $gstRate = $this->service->getGSTRate();
        $this->assertEquals(0.05, $gstRate);
    }

    /**
     * Test getting QST rate.
     */
    public function testGetQSTRate(): void
    {
        $qstRate = $this->service->getQSTRate();
        $this->assertEquals(0.09975, $qstRate);
    }

    /**
     * Test getting combined tax rate.
     */
    public function testGetCombinedTaxRate(): void
    {
        $combinedRate = $this->service->getCombinedTaxRate();
        $this->assertEquals(0.14975, $combinedRate);
    }

    /**
     * Test tax rates are cached after first call.
     */
    public function testTaxRatesAreCached(): void
    {
        // First call
        $rates1 = $this->service->getTaxRates();

        // Second call should return same instance without calling settings again
        $rates2 = $this->service->getTaxRates();

        $this->assertSame($rates1, $rates2);
    }

    // =========================================================================
    // TAX CALCULATION TESTS
    // =========================================================================

    /**
     * Test calculating tax amount.
     */
    public function testCalculateTax(): void
    {
        $subtotal = 1000.00;
        $tax = $this->service->calculateTax($subtotal);

        $this->assertEquals(149.75, $tax);
    }

    /**
     * Test calculating GST amount.
     */
    public function testCalculateGST(): void
    {
        $subtotal = 1000.00;
        $gst = $this->service->calculateGST($subtotal);

        $this->assertEquals(50.00, $gst);
    }

    /**
     * Test calculating QST amount.
     */
    public function testCalculateQST(): void
    {
        $subtotal = 1000.00;
        $qst = $this->service->calculateQST($subtotal);

        $this->assertEquals(99.75, $qst);
    }

    /**
     * Test calculating total with tax.
     */
    public function testCalculateTotal(): void
    {
        $subtotal = 1000.00;
        $total = $this->service->calculateTotal($subtotal);

        $this->assertEquals(1149.75, $total);
    }

    /**
     * Test tax calculation without rounding.
     */
    public function testCalculateTaxWithoutRounding(): void
    {
        $subtotal = 100.00;
        $tax = $this->service->calculateTax($subtotal, false);

        // Precise calculation without rounding
        $this->assertEquals(14.975, $tax);
    }

    /**
     * Test calculating detailed invoice amounts.
     */
    public function testCalculateInvoiceAmounts(): void
    {
        $subtotal = 1000.00;
        $amounts = $this->service->calculateInvoiceAmounts($subtotal);

        $this->assertArrayHasKey('subtotal', $amounts);
        $this->assertArrayHasKey('gst', $amounts);
        $this->assertArrayHasKey('qst', $amounts);
        $this->assertArrayHasKey('taxAmount', $amounts);
        $this->assertArrayHasKey('totalAmount', $amounts);

        $this->assertEquals(1000.00, $amounts['subtotal']);
        $this->assertEquals(50.00, $amounts['gst']);
        $this->assertEquals(99.75, $amounts['qst']);
        $this->assertEquals(149.75, $amounts['taxAmount']);
        $this->assertEquals(1149.75, $amounts['totalAmount']);
    }

    // =========================================================================
    // INVOICE NUMBER GENERATION TESTS
    // =========================================================================

    /**
     * Test generating invoice number.
     */
    public function testGenerateInvoiceNumber(): void
    {
        $gibbonSchoolYearID = 2025;
        $expectedNumber = 'INV-000001';

        $this->invoiceGateway->expects($this->once())
            ->method('generateInvoiceNumber')
            ->with('INV-', $gibbonSchoolYearID)
            ->willReturn($expectedNumber);

        $invoiceNumber = $this->service->generateInvoiceNumber($gibbonSchoolYearID);

        $this->assertEquals($expectedNumber, $invoiceNumber);
    }

    // =========================================================================
    // DUE DATE CALCULATION TESTS
    // =========================================================================

    /**
     * Test calculating due date with default payment terms.
     */
    public function testCalculateDueDateWithDefaultTerms(): void
    {
        $this->validator->method('isValidDate')->willReturn(true);

        $invoiceDate = '2025-01-15';
        $dueDate = $this->service->calculateDueDate($invoiceDate);

        $this->assertEquals('2025-02-14', $dueDate);
    }

    /**
     * Test calculating due date with custom payment terms.
     */
    public function testCalculateDueDateWithCustomTerms(): void
    {
        $this->validator->method('isValidDate')->willReturn(true);

        $invoiceDate = '2025-01-15';
        $paymentTerms = 15;
        $dueDate = $this->service->calculateDueDate($invoiceDate, $paymentTerms);

        $this->assertEquals('2025-01-30', $dueDate);
    }

    /**
     * Test calculating due date with invalid invoice date.
     */
    public function testCalculateDueDateWithInvalidDate(): void
    {
        $this->validator->method('isValidDate')->willReturn(false);

        $invalidDate = 'invalid-date';
        $dueDate = $this->service->calculateDueDate($invalidDate);

        // Should fallback to today + payment terms
        $expectedDate = date('Y-m-d', strtotime('+30 days'));
        $this->assertEquals($expectedDate, $dueDate);
    }

    /**
     * Test getting default payment terms.
     */
    public function testGetDefaultPaymentTerms(): void
    {
        $terms = $this->service->getDefaultPaymentTerms();
        $this->assertEquals(30, $terms);
    }

    // =========================================================================
    // BALANCE CALCULATION TESTS
    // =========================================================================

    /**
     * Test calculating balance remaining.
     */
    public function testCalculateBalance(): void
    {
        $totalAmount = 1149.75;
        $paidAmount = 500.00;
        $balance = $this->service->calculateBalance($totalAmount, $paidAmount);

        $this->assertEquals(649.75, $balance);
    }

    /**
     * Test calculating balance with zero paid.
     */
    public function testCalculateBalanceWithZeroPaid(): void
    {
        $totalAmount = 1149.75;
        $paidAmount = 0.00;
        $balance = $this->service->calculateBalance($totalAmount, $paidAmount);

        $this->assertEquals(1149.75, $balance);
    }

    /**
     * Test calculating balance when fully paid.
     */
    public function testCalculateBalanceWhenFullyPaid(): void
    {
        $totalAmount = 1149.75;
        $paidAmount = 1149.75;
        $balance = $this->service->calculateBalance($totalAmount, $paidAmount);

        $this->assertEquals(0.00, $balance);
    }

    // =========================================================================
    // STATUS DETERMINATION TESTS
    // =========================================================================

    /**
     * Test determining status for unpaid invoice.
     */
    public function testDetermineStatusForUnpaidInvoice(): void
    {
        $status = $this->service->determineStatus(1149.75, 0.00);
        $this->assertEquals('Pending', $status);
    }

    /**
     * Test determining status for partially paid invoice.
     */
    public function testDetermineStatusForPartiallyPaidInvoice(): void
    {
        $status = $this->service->determineStatus(1149.75, 500.00);
        $this->assertEquals('Partial', $status);
    }

    /**
     * Test determining status for fully paid invoice.
     */
    public function testDetermineStatusForFullyPaidInvoice(): void
    {
        $status = $this->service->determineStatus(1149.75, 1149.75);
        $this->assertEquals('Paid', $status);
    }

    /**
     * Test determining status preserves Issued status.
     */
    public function testDetermineStatusPreservesIssuedStatus(): void
    {
        $status = $this->service->determineStatus(1149.75, 0.00, 'Issued');
        $this->assertEquals('Issued', $status);
    }

    /**
     * Test determining status preserves Cancelled status.
     */
    public function testDetermineStatusPreservesCancelledStatus(): void
    {
        $status = $this->service->determineStatus(1149.75, 500.00, 'Cancelled');
        $this->assertEquals('Cancelled', $status);
    }

    /**
     * Test determining status preserves Refunded status.
     */
    public function testDetermineStatusPreservesRefundedStatus(): void
    {
        $status = $this->service->determineStatus(1149.75, 500.00, 'Refunded');
        $this->assertEquals('Refunded', $status);
    }

    /**
     * Test determining status when overpaid.
     */
    public function testDetermineStatusWhenOverpaid(): void
    {
        $status = $this->service->determineStatus(1149.75, 1200.00);
        $this->assertEquals('Paid', $status);
    }

    // =========================================================================
    // OVERDUE CHECK TESTS
    // =========================================================================

    /**
     * Test checking if invoice is overdue.
     */
    public function testIsOverdueWhenOverdue(): void
    {
        $this->validator->method('isValidDate')->willReturn(true);

        $dueDate = '2025-01-01';
        $compareDate = '2025-01-15';
        $isOverdue = $this->service->isOverdue($dueDate, 'Issued', $compareDate);

        $this->assertTrue($isOverdue);
    }

    /**
     * Test checking if invoice is not overdue.
     */
    public function testIsOverdueWhenNotOverdue(): void
    {
        $this->validator->method('isValidDate')->willReturn(true);

        $dueDate = '2025-02-01';
        $compareDate = '2025-01-15';
        $isOverdue = $this->service->isOverdue($dueDate, 'Issued', $compareDate);

        $this->assertFalse($isOverdue);
    }

    /**
     * Test paid invoices are never overdue.
     */
    public function testIsOverdueForPaidInvoice(): void
    {
        $this->validator->method('isValidDate')->willReturn(true);

        $dueDate = '2025-01-01';
        $compareDate = '2025-01-15';
        $isOverdue = $this->service->isOverdue($dueDate, 'Paid', $compareDate);

        $this->assertFalse($isOverdue);
    }

    /**
     * Test pending invoices are not overdue.
     */
    public function testIsOverdueForPendingInvoice(): void
    {
        $this->validator->method('isValidDate')->willReturn(true);

        $dueDate = '2025-01-01';
        $compareDate = '2025-01-15';
        $isOverdue = $this->service->isOverdue($dueDate, 'Pending', $compareDate);

        $this->assertFalse($isOverdue);
    }

    /**
     * Test partial invoices can be overdue.
     */
    public function testIsOverdueForPartialInvoice(): void
    {
        $this->validator->method('isValidDate')->willReturn(true);

        $dueDate = '2025-01-01';
        $compareDate = '2025-01-15';
        $isOverdue = $this->service->isOverdue($dueDate, 'Partial', $compareDate);

        $this->assertTrue($isOverdue);
    }

    // =========================================================================
    // INVOICE AGE TESTS
    // =========================================================================

    /**
     * Test getting invoice age in days.
     */
    public function testGetInvoiceAge(): void
    {
        $this->validator->method('isValidDate')->willReturn(true);

        $invoiceDate = '2025-01-01';
        $compareDate = '2025-01-15';
        $age = $this->service->getInvoiceAge($invoiceDate, $compareDate);

        $this->assertEquals(14, $age);
    }

    /**
     * Test getting invoice age with invalid date.
     */
    public function testGetInvoiceAgeWithInvalidDate(): void
    {
        $this->validator->method('isValidDate')->willReturn(false);

        $age = $this->service->getInvoiceAge('invalid', '2025-01-15');

        $this->assertEquals(0, $age);
    }

    /**
     * Test getting days overdue.
     */
    public function testGetDaysOverdue(): void
    {
        $this->validator->method('isValidDate')->willReturn(true);

        $dueDate = '2025-01-01';
        $compareDate = '2025-01-15';
        $daysOverdue = $this->service->getDaysOverdue($dueDate, $compareDate);

        $this->assertEquals(14, $daysOverdue);
    }

    /**
     * Test getting days overdue when not overdue.
     */
    public function testGetDaysOverdueWhenNotOverdue(): void
    {
        $this->validator->method('isValidDate')->willReturn(true);

        $dueDate = '2025-02-01';
        $compareDate = '2025-01-15';
        $daysOverdue = $this->service->getDaysOverdue($dueDate, $compareDate);

        $this->assertEquals(0, $daysOverdue);
    }

    // =========================================================================
    // INVOICE DATA PREPARATION TESTS
    // =========================================================================

    /**
     * Test preparing invoice data.
     */
    public function testPrepareInvoiceData(): void
    {
        $this->validator->method('isValidDate')->willReturn(true);
        $this->invoiceGateway->method('generateInvoiceNumber')
            ->willReturn('INV-000001');

        $data = [
            'gibbonPersonID' => 100,
            'gibbonFamilyID' => 50,
            'gibbonSchoolYearID' => 2025,
            'subtotal' => 1000.00,
            'invoiceDate' => '2025-01-15',
            'notes' => 'Test invoice',
        ];

        $prepared = $this->service->prepareInvoiceData($data);

        $this->assertEquals('INV-000001', $prepared['invoiceNumber']);
        $this->assertEquals('2025-01-15', $prepared['invoiceDate']);
        $this->assertEquals('2025-02-14', $prepared['dueDate']);
        $this->assertEquals(1000.00, $prepared['subtotal']);
        $this->assertEquals(149.75, $prepared['taxAmount']);
        $this->assertEquals(1149.75, $prepared['totalAmount']);
        $this->assertEquals(0.00, $prepared['paidAmount']);
        $this->assertEquals('Pending', $prepared['status']);
    }

    /**
     * Test preparing invoice data with custom due date.
     */
    public function testPrepareInvoiceDataWithCustomDueDate(): void
    {
        $this->validator->method('isValidDate')->willReturn(true);

        $data = [
            'subtotal' => 1000.00,
            'invoiceDate' => '2025-01-15',
            'dueDate' => '2025-01-20',
        ];

        $prepared = $this->service->prepareInvoiceData($data);

        $this->assertEquals('2025-01-20', $prepared['dueDate']);
    }

    /**
     * Test validate invoice delegates to validator.
     */
    public function testValidateInvoice(): void
    {
        $invoiceData = ['subtotal' => 1000.00];
        $expectedResult = ['success' => true, 'errors' => []];

        $this->validator->expects($this->once())
            ->method('validate')
            ->with($invoiceData)
            ->willReturn($expectedResult);

        $result = $this->service->validateInvoice($invoiceData);

        $this->assertEquals($expectedResult, $result);
    }
}
