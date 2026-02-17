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
use Gibbon\Module\EnhancedFinance\Service\PaymentService;
use Gibbon\Module\EnhancedFinance\Domain\PaymentGateway;
use Gibbon\Module\EnhancedFinance\Domain\InvoiceGateway;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Contracts\Database\Result;

/**
 * Unit tests for PaymentService.
 *
 * Tests payment validation, balance calculations, payment processing,
 * payment methods, and payment summaries.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class PaymentServiceTest extends TestCase
{
    /**
     * @var PaymentService
     */
    protected $service;

    /**
     * @var MockObject|SettingGateway
     */
    protected $settingGateway;

    /**
     * @var MockObject|PaymentGateway
     */
    protected $paymentGateway;

    /**
     * @var MockObject|InvoiceGateway
     */
    protected $invoiceGateway;

    /**
     * Sample invoice data for testing.
     *
     * @var array
     */
    protected $sampleInvoice;

    /**
     * Set up test fixtures.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create mocks
        $this->settingGateway = $this->createMock(SettingGateway::class);
        $this->paymentGateway = $this->createMock(PaymentGateway::class);
        $this->invoiceGateway = $this->createMock(InvoiceGateway::class);

        // Create service
        $this->service = new PaymentService(
            $this->settingGateway,
            $this->paymentGateway,
            $this->invoiceGateway
        );

        // Sample invoice
        $this->sampleInvoice = [
            'gibbonEnhancedFinanceInvoiceID' => 1,
            'totalAmount' => 1149.75,
            'paidAmount' => 0.00,
            'status' => 'Pending',
        ];
    }

    /**
     * Clean up after tests.
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        $this->service = null;
        $this->settingGateway = null;
        $this->paymentGateway = null;
        $this->invoiceGateway = null;
    }

    // =========================================================================
    // PAYMENT METHODS TESTS
    // =========================================================================

    /**
     * Test getting payment methods.
     */
    public function testGetPaymentMethods(): void
    {
        $methods = $this->service->getPaymentMethods();

        $this->assertIsArray($methods);
        $this->assertArrayHasKey('Cash', $methods);
        $this->assertArrayHasKey('Check', $methods);
        $this->assertArrayHasKey('Credit Card', $methods);
        $this->assertArrayHasKey('Debit Card', $methods);
        $this->assertArrayHasKey('Bank Transfer', $methods);
        $this->assertArrayHasKey('Online', $methods);
        $this->assertArrayHasKey('Other', $methods);
    }

    /**
     * Test payment method requires reference.
     */
    public function testRequiresReferenceForCheck(): void
    {
        $this->assertTrue($this->service->requiresReference('Check'));
    }

    /**
     * Test payment method requires reference for bank transfer.
     */
    public function testRequiresReferenceForBankTransfer(): void
    {
        $this->assertTrue($this->service->requiresReference('Bank Transfer'));
    }

    /**
     * Test payment method requires reference for online.
     */
    public function testRequiresReferenceForOnline(): void
    {
        $this->assertTrue($this->service->requiresReference('Online'));
    }

    /**
     * Test cash does not require reference.
     */
    public function testCashDoesNotRequireReference(): void
    {
        $this->assertFalse($this->service->requiresReference('Cash'));
    }

    // =========================================================================
    // AMOUNT VALIDATION TESTS
    // =========================================================================

    /**
     * Test validating positive amount.
     */
    public function testValidateAmountWithPositiveAmount(): void
    {
        $result = $this->service->validateAmount(100.00);

        $this->assertTrue($result['success']);
    }

    /**
     * Test validating zero amount.
     */
    public function testValidateAmountWithZeroAmount(): void
    {
        $result = $this->service->validateAmount(0.00);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertEquals('Payment amount must be greater than zero.', $result['error']);
    }

    /**
     * Test validating negative amount.
     */
    public function testValidateAmountWithNegativeAmount(): void
    {
        $result = $this->service->validateAmount(-50.00);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
    }

    // =========================================================================
    // BALANCE CALCULATION TESTS
    // =========================================================================

    /**
     * Test calculating balance remaining.
     */
    public function testCalculateBalance(): void
    {
        $balance = $this->service->calculateBalance(1149.75, 500.00);
        $this->assertEquals(649.75, $balance);
    }

    /**
     * Test calculating balance with zero paid.
     */
    public function testCalculateBalanceWithZeroPaid(): void
    {
        $balance = $this->service->calculateBalance(1149.75, 0.00);
        $this->assertEquals(1149.75, $balance);
    }

    /**
     * Test calculating balance when fully paid.
     */
    public function testCalculateBalanceWhenFullyPaid(): void
    {
        $balance = $this->service->calculateBalance(1149.75, 1149.75);
        $this->assertEquals(0.00, $balance);
    }

    /**
     * Test checking if payment exceeds balance.
     */
    public function testExceedsBalanceWhenExceeds(): void
    {
        $exceeds = $this->service->exceedsBalance(700.00, 649.75);
        $this->assertTrue($exceeds);
    }

    /**
     * Test checking if payment does not exceed balance.
     */
    public function testExceedsBalanceWhenDoesNotExceed(): void
    {
        $exceeds = $this->service->exceedsBalance(500.00, 649.75);
        $this->assertFalse($exceeds);
    }

    /**
     * Test checking if payment exactly matches balance.
     */
    public function testExceedsBalanceWhenExactMatch(): void
    {
        $exceeds = $this->service->exceedsBalance(649.75, 649.75);
        $this->assertFalse($exceeds);
    }

    /**
     * Test calculating overpayment.
     */
    public function testCalculateOverpayment(): void
    {
        $overpayment = $this->service->calculateOverpayment(700.00, 649.75);
        $this->assertEquals(50.25, $overpayment);
    }

    /**
     * Test calculating overpayment when no overpayment.
     */
    public function testCalculateOverpaymentWhenNoOverpayment(): void
    {
        $overpayment = $this->service->calculateOverpayment(500.00, 649.75);
        $this->assertEquals(0.00, $overpayment);
    }

    // =========================================================================
    // PAYMENT VALIDATION AGAINST INVOICE TESTS
    // =========================================================================

    /**
     * Test validating payment against invoice with valid amount.
     */
    public function testValidatePaymentAgainstInvoiceWithValidAmount(): void
    {
        $result = $this->service->validatePaymentAgainstInvoice(500.00, $this->sampleInvoice);

        $this->assertTrue($result['success']);
        $this->assertEquals(1149.75, $result['balanceRemaining']);
    }

    /**
     * Test validating payment against invoice with exceeding amount.
     */
    public function testValidatePaymentAgainstInvoiceWithExceedingAmount(): void
    {
        $result = $this->service->validatePaymentAgainstInvoice(1200.00, $this->sampleInvoice);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertEquals('Payment amount exceeds invoice balance.', $result['error']);
        $this->assertEquals(1149.75, $result['balanceRemaining']);
    }

    /**
     * Test validating payment for partially paid invoice.
     */
    public function testValidatePaymentAgainstPartiallyPaidInvoice(): void
    {
        $invoice = $this->sampleInvoice;
        $invoice['paidAmount'] = 500.00;

        $result = $this->service->validatePaymentAgainstInvoice(649.75, $invoice);

        $this->assertTrue($result['success']);
        $this->assertEquals(649.75, $result['balanceRemaining']);
    }

    /**
     * Test validating payment exceeding partially paid invoice.
     */
    public function testValidatePaymentExceedingPartiallyPaidInvoice(): void
    {
        $invoice = $this->sampleInvoice;
        $invoice['paidAmount'] = 500.00;

        $result = $this->service->validatePaymentAgainstInvoice(700.00, $invoice);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
    }

    // =========================================================================
    // PAYMENT DATA PREPARATION TESTS
    // =========================================================================

    /**
     * Test preparing payment data.
     */
    public function testPreparePaymentData(): void
    {
        $data = [
            'gibbonEnhancedFinanceInvoiceID' => 1,
            'paymentDate' => '2025-01-20',
            'amount' => 500.00,
            'method' => 'Cash',
            'reference' => '',
            'notes' => 'Test payment',
        ];

        $prepared = $this->service->preparePaymentData($data, 10);

        $this->assertEquals(1, $prepared['gibbonEnhancedFinanceInvoiceID']);
        $this->assertEquals('2025-01-20', $prepared['paymentDate']);
        $this->assertEquals(500.00, $prepared['amount']);
        $this->assertEquals('Cash', $prepared['method']);
        $this->assertEquals('', $prepared['reference']);
        $this->assertEquals('Test payment', $prepared['notes']);
        $this->assertEquals(10, $prepared['recordedByID']);
    }

    /**
     * Test preparing payment data with defaults.
     */
    public function testPreparePaymentDataWithDefaults(): void
    {
        $data = [];
        $prepared = $this->service->preparePaymentData($data, 10);

        $this->assertEquals(date('Y-m-d'), $prepared['paymentDate']);
        $this->assertEquals(0.00, $prepared['amount']);
        $this->assertEquals('Cash', $prepared['method']);
        $this->assertEquals('', $prepared['reference']);
        $this->assertEquals('', $prepared['notes']);
        $this->assertEquals(10, $prepared['recordedByID']);
    }

    // =========================================================================
    // PAYMENT PROCESSING TESTS
    // =========================================================================

    /**
     * Test processing payment successfully.
     */
    public function testProcessPaymentSuccessfully(): void
    {
        $paymentData = [
            'gibbonEnhancedFinanceInvoiceID' => 1,
            'amount' => 500.00,
            'method' => 'Cash',
        ];

        $this->invoiceGateway->expects($this->once())
            ->method('selectInvoiceByID')
            ->with(1)
            ->willReturn($this->sampleInvoice);

        $this->paymentGateway->expects($this->once())
            ->method('insertPayment')
            ->willReturn(100);

        $this->invoiceGateway->expects($this->once())
            ->method('updatePaidAmount')
            ->with(1)
            ->willReturn(true);

        $result = $this->service->processPayment($paymentData);

        $this->assertTrue($result['success']);
        $this->assertEquals(100, $result['paymentID']);
    }

    /**
     * Test processing payment with missing invoice ID.
     */
    public function testProcessPaymentWithMissingInvoiceID(): void
    {
        $paymentData = [
            'amount' => 500.00,
        ];

        $result = $this->service->processPayment($paymentData);

        $this->assertFalse($result['success']);
        $this->assertEquals('Invoice ID is required.', $result['error']);
    }

    /**
     * Test processing payment with invalid amount.
     */
    public function testProcessPaymentWithInvalidAmount(): void
    {
        $paymentData = [
            'gibbonEnhancedFinanceInvoiceID' => 1,
            'amount' => 0.00,
        ];

        $result = $this->service->processPayment($paymentData);

        $this->assertFalse($result['success']);
        $this->assertEquals('Payment amount must be greater than zero.', $result['error']);
    }

    /**
     * Test processing payment with invoice not found.
     */
    public function testProcessPaymentWithInvoiceNotFound(): void
    {
        $paymentData = [
            'gibbonEnhancedFinanceInvoiceID' => 999,
            'amount' => 500.00,
        ];

        $this->invoiceGateway->expects($this->once())
            ->method('selectInvoiceByID')
            ->with(999)
            ->willReturn(null);

        $result = $this->service->processPayment($paymentData);

        $this->assertFalse($result['success']);
        $this->assertEquals('Invoice not found.', $result['error']);
    }

    /**
     * Test processing payment exceeding invoice balance.
     */
    public function testProcessPaymentExceedingBalance(): void
    {
        $paymentData = [
            'gibbonEnhancedFinanceInvoiceID' => 1,
            'amount' => 1200.00,
        ];

        $this->invoiceGateway->expects($this->once())
            ->method('selectInvoiceByID')
            ->with(1)
            ->willReturn($this->sampleInvoice);

        $result = $this->service->processPayment($paymentData);

        $this->assertFalse($result['success']);
        $this->assertEquals('Payment amount exceeds invoice balance.', $result['error']);
    }

    /**
     * Test processing payment with insert failure.
     */
    public function testProcessPaymentWithInsertFailure(): void
    {
        $paymentData = [
            'gibbonEnhancedFinanceInvoiceID' => 1,
            'amount' => 500.00,
        ];

        $this->invoiceGateway->expects($this->once())
            ->method('selectInvoiceByID')
            ->with(1)
            ->willReturn($this->sampleInvoice);

        $this->paymentGateway->expects($this->once())
            ->method('insertPayment')
            ->willReturn(false);

        $result = $this->service->processPayment($paymentData);

        $this->assertFalse($result['success']);
        $this->assertEquals('Failed to insert payment.', $result['error']);
    }

    /**
     * Test processing payment with invoice update failure.
     */
    public function testProcessPaymentWithInvoiceUpdateFailure(): void
    {
        $paymentData = [
            'gibbonEnhancedFinanceInvoiceID' => 1,
            'amount' => 500.00,
        ];

        $this->invoiceGateway->expects($this->once())
            ->method('selectInvoiceByID')
            ->with(1)
            ->willReturn($this->sampleInvoice);

        $this->paymentGateway->expects($this->once())
            ->method('insertPayment')
            ->willReturn(100);

        $this->invoiceGateway->expects($this->once())
            ->method('updatePaidAmount')
            ->with(1)
            ->willReturn(false);

        $result = $this->service->processPayment($paymentData);

        $this->assertFalse($result['success']);
        $this->assertEquals('Payment recorded but failed to update invoice.', $result['error']);
        $this->assertEquals(100, $result['paymentID']);
    }

    // =========================================================================
    // PAYMENT SUMMARY TESTS
    // =========================================================================

    /**
     * Test getting total paid for invoice.
     */
    public function testGetTotalPaidForInvoice(): void
    {
        $this->paymentGateway->expects($this->once())
            ->method('getTotalPaidForInvoice')
            ->with(1)
            ->willReturn(500.00);

        $total = $this->service->getTotalPaidForInvoice(1);
        $this->assertEquals(500.00, $total);
    }

    /**
     * Test getting payment summary for year.
     */
    public function testGetPaymentSummaryForYear(): void
    {
        $summaryData = [
            'totalAmount' => '5000.00',
            'paymentCount' => '25',
        ];

        $this->paymentGateway->expects($this->once())
            ->method('selectTotalPaymentsByYear')
            ->with(2025)
            ->willReturn($summaryData);

        $summary = $this->service->getPaymentSummaryForYear(2025);

        $this->assertEquals(5000.00, $summary['totalAmount']);
        $this->assertEquals(25, $summary['paymentCount']);
    }

    /**
     * Test getting payment summary by method.
     */
    public function testGetPaymentSummaryByMethod(): void
    {
        $mockResult = $this->createMock(Result::class);
        $mockResult->method('fetch')
            ->willReturnOnConsecutiveCalls(
                [
                    'method' => 'Cash',
                    'paymentCount' => '10',
                    'totalAmount' => '1000.00',
                ],
                [
                    'method' => 'Check',
                    'paymentCount' => '5',
                    'totalAmount' => '2500.00',
                ],
                false
            );

        $this->paymentGateway->expects($this->once())
            ->method('selectPaymentSummaryByMethod')
            ->with(2025)
            ->willReturn($mockResult);

        $summary = $this->service->getPaymentSummaryByMethod(2025);

        $this->assertCount(2, $summary);
        $this->assertEquals('Cash', $summary[0]['method']);
        $this->assertEquals(10, $summary[0]['paymentCount']);
        $this->assertEquals(1000.00, $summary[0]['totalAmount']);
    }

    /**
     * Test getting payment summary by month.
     */
    public function testGetPaymentSummaryByMonth(): void
    {
        $mockResult = $this->createMock(Result::class);
        $mockResult->method('fetch')
            ->willReturnOnConsecutiveCalls(
                [
                    'paymentYear' => '2025',
                    'paymentMonth' => '1',
                    'paymentCount' => '5',
                    'totalAmount' => '1000.00',
                ],
                false
            );

        $this->paymentGateway->expects($this->once())
            ->method('selectPaymentSummaryByMonth')
            ->with(2025)
            ->willReturn($mockResult);

        $summary = $this->service->getPaymentSummaryByMonth(2025);

        $this->assertCount(1, $summary);
        $this->assertEquals(2025, $summary[0]['year']);
        $this->assertEquals(1, $summary[0]['month']);
        $this->assertEquals(5, $summary[0]['paymentCount']);
        $this->assertEquals(1000.00, $summary[0]['totalAmount']);
    }

    /**
     * Test getting total paid by child and tax year.
     */
    public function testGetTotalPaidByChildAndTaxYear(): void
    {
        $summaryData = [
            'totalPaid' => '3500.00',
            'paymentCount' => '12',
            'invoiceCount' => '10',
        ];

        $this->paymentGateway->expects($this->once())
            ->method('selectTotalPaidByChildAndTaxYear')
            ->with(100, 2025)
            ->willReturn($summaryData);

        $summary = $this->service->getTotalPaidByChildAndTaxYear(100, 2025);

        $this->assertEquals(3500.00, $summary['totalPaid']);
        $this->assertEquals(12, $summary['paymentCount']);
        $this->assertEquals(10, $summary['invoiceCount']);
    }

    // =========================================================================
    // FORMATTING TESTS
    // =========================================================================

    /**
     * Test formatting payment amount.
     */
    public function testFormatAmount(): void
    {
        $formatted = $this->service->formatAmount(1149.75);
        $this->assertEquals('$1,149.75', $formatted);
    }

    /**
     * Test formatting payment amount with custom currency.
     */
    public function testFormatAmountWithCustomCurrency(): void
    {
        $formatted = $this->service->formatAmount(1149.75, '€');
        $this->assertEquals('€1,149.75', $formatted);
    }

    /**
     * Test formatting small amount.
     */
    public function testFormatSmallAmount(): void
    {
        $formatted = $this->service->formatAmount(5.50);
        $this->assertEquals('$5.50', $formatted);
    }

    // =========================================================================
    // RECENT PAYMENTS TESTS
    // =========================================================================

    /**
     * Test getting recent payments.
     */
    public function testGetRecentPayments(): void
    {
        $mockResult = $this->createMock(Result::class);
        $mockResult->method('fetch')
            ->willReturnOnConsecutiveCalls(
                ['gibbonEnhancedFinancePaymentID' => 1, 'amount' => 500.00],
                ['gibbonEnhancedFinancePaymentID' => 2, 'amount' => 300.00],
                false
            );

        $this->paymentGateway->expects($this->once())
            ->method('selectRecentPayments')
            ->with(2025, 10)
            ->willReturn($mockResult);

        $payments = $this->service->getRecentPayments(2025, 10);

        $this->assertCount(2, $payments);
        $this->assertEquals(1, $payments[0]['gibbonEnhancedFinancePaymentID']);
        $this->assertEquals(500.00, $payments[0]['amount']);
    }
}
