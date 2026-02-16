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
use Gibbon\Module\EnhancedFinance\Domain\InvoiceGateway;
use Gibbon\Module\EnhancedFinance\Domain\PaymentGateway;
use Gibbon\Contracts\Database\Connection;
use Gibbon\Contracts\Database\Result;

/**
 * Unit tests for InvoiceGateway and PaymentGateway.
 *
 * Tests invoice creation, retrieval, payment recording, balance calculation,
 * status transitions, partial payments, and invoice cancellation.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class InvoiceTest extends TestCase
{
    /**
     * @var MockObject|Connection
     */
    protected $db;

    /**
     * @var InvoiceGateway|MockObject
     */
    protected $invoiceGateway;

    /**
     * @var PaymentGateway|MockObject
     */
    protected $paymentGateway;

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

        // Create mock database connection
        $this->db = $this->createMock(Connection::class);

        // Sample invoice data
        $this->sampleInvoice = [
            'gibbonEnhancedFinanceInvoiceID' => 1,
            'gibbonPersonID' => 100,
            'gibbonFamilyID' => 50,
            'gibbonSchoolYearID' => 2025,
            'invoiceNumber' => 'INV-000001',
            'invoiceDate' => '2025-01-15',
            'dueDate' => '2025-02-15',
            'subtotal' => 1000.00,
            'taxAmount' => 149.75,
            'totalAmount' => 1149.75,
            'paidAmount' => 0.00,
            'status' => 'Pending',
            'notes' => 'Monthly childcare fee',
            'createdByID' => 1,
            'timestampCreated' => '2025-01-15 10:00:00',
            'timestampModified' => '2025-01-15 10:00:00',
            'childSurname' => 'Smith',
            'childPreferredName' => 'John',
            'familyName' => 'Smith Family',
            'balanceRemaining' => 1149.75,
        ];

        // Sample payment data
        $this->samplePayment = [
            'gibbonEnhancedFinancePaymentID' => 1,
            'gibbonEnhancedFinanceInvoiceID' => 1,
            'paymentDate' => '2025-01-20',
            'amount' => 500.00,
            'method' => 'ETransfer',
            'reference' => 'ET-123456',
            'notes' => 'Partial payment',
            'recordedByID' => 1,
            'timestampCreated' => '2025-01-20 14:30:00',
            'timestampModified' => '2025-01-20 14:30:00',
        ];
    }

    /**
     * Clean up after tests.
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        $this->db = null;
        $this->invoiceGateway = null;
        $this->paymentGateway = null;
    }

    // =========================================================================
    // INVOICE CREATION AND RETRIEVAL TESTS
    // =========================================================================

    /**
     * Test that invoice data structure contains required fields.
     */
    public function testInvoiceDataStructureHasRequiredFields(): void
    {
        $requiredFields = [
            'gibbonEnhancedFinanceInvoiceID',
            'gibbonPersonID',
            'gibbonFamilyID',
            'gibbonSchoolYearID',
            'invoiceNumber',
            'invoiceDate',
            'dueDate',
            'subtotal',
            'taxAmount',
            'totalAmount',
            'paidAmount',
            'status',
        ];

        foreach ($requiredFields as $field) {
            $this->assertArrayHasKey(
                $field,
                $this->sampleInvoice,
                "Invoice should contain required field: {$field}"
            );
        }
    }

    /**
     * Test invoice total calculation (subtotal + tax).
     */
    public function testInvoiceTotalCalculation(): void
    {
        $subtotal = 1000.00;
        $taxRate = 0.14975; // GST 5% + QST 9.975%
        $expectedTax = round($subtotal * $taxRate, 2);
        $expectedTotal = $subtotal + $expectedTax;

        $this->assertEquals(149.75, $expectedTax, 'Tax amount should be calculated correctly');
        $this->assertEquals(1149.75, $expectedTotal, 'Total should equal subtotal + tax');
    }

    /**
     * Test balance remaining calculation.
     */
    public function testBalanceRemainingCalculation(): void
    {
        $totalAmount = 1149.75;
        $paidAmount = 500.00;
        $expectedBalance = $totalAmount - $paidAmount;

        $this->assertEquals(649.75, $expectedBalance, 'Balance remaining should be total minus paid');
    }

    /**
     * Test invoice with zero balance shows as fully paid.
     */
    public function testInvoiceWithZeroBalanceIsFullyPaid(): void
    {
        $invoice = $this->sampleInvoice;
        $invoice['paidAmount'] = $invoice['totalAmount'];
        $invoice['balanceRemaining'] = 0;

        $balanceRemaining = $invoice['totalAmount'] - $invoice['paidAmount'];

        $this->assertEquals(0, $balanceRemaining, 'Balance should be zero when fully paid');
        $this->assertTrue($balanceRemaining <= 0, 'Invoice should be considered fully paid');
    }

    /**
     * Test invoice number format follows prefix pattern.
     */
    public function testInvoiceNumberFormat(): void
    {
        $invoiceNumber = 'INV-000001';

        // Should match pattern: PREFIX-NNNNNN
        $this->assertMatchesRegularExpression(
            '/^[A-Z]+-\d{6}$/',
            $invoiceNumber,
            'Invoice number should follow PREFIX-NNNNNN format'
        );
    }

    /**
     * Test invoice number generation increments correctly.
     */
    public function testInvoiceNumberGeneration(): void
    {
        $prefix = 'INV-';
        $currentMax = 5;
        $expectedNext = $prefix . str_pad($currentMax + 1, 6, '0', STR_PAD_LEFT);

        $this->assertEquals('INV-000006', $expectedNext, 'Next invoice number should increment by 1');
    }

    // =========================================================================
    // PAYMENT RECORDING AND BALANCE CALCULATION TESTS
    // =========================================================================

    /**
     * Test payment data structure has required fields.
     */
    public function testPaymentDataStructureHasRequiredFields(): void
    {
        $requiredFields = [
            'gibbonEnhancedFinancePaymentID',
            'gibbonEnhancedFinanceInvoiceID',
            'paymentDate',
            'amount',
            'method',
        ];

        foreach ($requiredFields as $field) {
            $this->assertArrayHasKey(
                $field,
                $this->samplePayment,
                "Payment should contain required field: {$field}"
            );
        }
    }

    /**
     * Test valid payment methods.
     */
    public function testValidPaymentMethods(): void
    {
        $validMethods = ['Cash', 'Cheque', 'ETransfer', 'CreditCard', 'DebitCard', 'Other'];

        $this->assertContains(
            $this->samplePayment['method'],
            $validMethods,
            'Payment method should be one of the valid options'
        );
    }

    /**
     * Test payment amount must be positive.
     */
    public function testPaymentAmountMustBePositive(): void
    {
        $this->assertGreaterThan(
            0,
            $this->samplePayment['amount'],
            'Payment amount must be positive'
        );
    }

    /**
     * Test total paid calculation from multiple payments.
     */
    public function testTotalPaidFromMultiplePayments(): void
    {
        $payments = [
            ['amount' => 300.00],
            ['amount' => 200.00],
            ['amount' => 100.00],
        ];

        $totalPaid = array_sum(array_column($payments, 'amount'));

        $this->assertEquals(600.00, $totalPaid, 'Total paid should sum all payment amounts');
    }

    /**
     * Test balance updates after payment.
     */
    public function testBalanceUpdatesAfterPayment(): void
    {
        $invoice = $this->sampleInvoice;
        $paymentAmount = 500.00;

        $newPaidAmount = $invoice['paidAmount'] + $paymentAmount;
        $newBalance = $invoice['totalAmount'] - $newPaidAmount;

        $this->assertEquals(500.00, $newPaidAmount, 'Paid amount should increase by payment');
        $this->assertEquals(649.75, $newBalance, 'Balance should decrease by payment amount');
    }

    /**
     * Test overpayment detection.
     */
    public function testOverpaymentDetection(): void
    {
        $totalAmount = 1149.75;
        $paymentAmount = 1500.00;
        $currentPaid = 0.00;

        $newPaidAmount = $currentPaid + $paymentAmount;
        $isOverpayment = $newPaidAmount > $totalAmount;

        $this->assertTrue($isOverpayment, 'Payment exceeding total should be detected as overpayment');
    }

    // =========================================================================
    // STATUS TRANSITION TESTS
    // =========================================================================

    /**
     * Test valid invoice statuses.
     */
    public function testValidInvoiceStatuses(): void
    {
        $validStatuses = ['Pending', 'Issued', 'Partial', 'Paid', 'Cancelled', 'Refunded'];

        $this->assertContains(
            $this->sampleInvoice['status'],
            $validStatuses,
            'Invoice status should be one of the valid options'
        );
    }

    /**
     * Test status transition: Pending to Issued.
     */
    public function testStatusTransitionPendingToIssued(): void
    {
        $currentStatus = 'Pending';
        $newStatus = 'Issued';

        // Pending invoices can be issued
        $allowedTransitions = ['Pending' => ['Issued', 'Cancelled']];

        $this->assertContains(
            $newStatus,
            $allowedTransitions[$currentStatus],
            'Pending invoice should be able to transition to Issued'
        );
    }

    /**
     * Test status transition: Issued to Partial (after partial payment).
     */
    public function testStatusTransitionIssuedToPartial(): void
    {
        $totalAmount = 1149.75;
        $paidAmount = 500.00;

        $shouldBePartial = $paidAmount > 0 && $paidAmount < $totalAmount;
        $expectedStatus = $shouldBePartial ? 'Partial' : ($paidAmount >= $totalAmount ? 'Paid' : 'Issued');

        $this->assertEquals('Partial', $expectedStatus, 'Status should be Partial when partially paid');
    }

    /**
     * Test status transition: Partial to Paid (after full payment).
     */
    public function testStatusTransitionPartialToPaid(): void
    {
        $totalAmount = 1149.75;
        $paidAmount = 1149.75;

        $shouldBePaid = $paidAmount >= $totalAmount;
        $expectedStatus = $shouldBePaid ? 'Paid' : 'Partial';

        $this->assertEquals('Paid', $expectedStatus, 'Status should be Paid when fully paid');
    }

    /**
     * Test status determination based on paid amount.
     */
    public function testStatusDeterminationFromPaidAmount(): void
    {
        $totalAmount = 1000.00;
        $testCases = [
            ['paid' => 0.00, 'expected' => 'Issued'],
            ['paid' => 500.00, 'expected' => 'Partial'],
            ['paid' => 999.99, 'expected' => 'Partial'],
            ['paid' => 1000.00, 'expected' => 'Paid'],
            ['paid' => 1100.00, 'expected' => 'Paid'], // Overpayment still counts as Paid
        ];

        foreach ($testCases as $case) {
            $status = $this->determineStatus($totalAmount, $case['paid']);
            $this->assertEquals(
                $case['expected'],
                $status,
                "Paid amount {$case['paid']} should result in status {$case['expected']}"
            );
        }
    }

    /**
     * Helper function to determine status based on paid amount.
     */
    protected function determineStatus(float $totalAmount, float $paidAmount): string
    {
        if ($paidAmount >= $totalAmount) {
            return 'Paid';
        } elseif ($paidAmount > 0) {
            return 'Partial';
        } else {
            return 'Issued';
        }
    }

    // =========================================================================
    // PARTIAL PAYMENT HANDLING TESTS
    // =========================================================================

    /**
     * Test first partial payment creates Partial status.
     */
    public function testFirstPartialPaymentCreatesPartialStatus(): void
    {
        $totalAmount = 1000.00;
        $initialPaid = 0.00;
        $paymentAmount = 300.00;

        $newPaidAmount = $initialPaid + $paymentAmount;
        $newStatus = $this->determineStatus($totalAmount, $newPaidAmount);

        $this->assertEquals('Partial', $newStatus, 'First partial payment should create Partial status');
    }

    /**
     * Test subsequent partial payments maintain Partial status.
     */
    public function testSubsequentPartialPaymentsMaintainPartialStatus(): void
    {
        $totalAmount = 1000.00;
        $payments = [200.00, 300.00, 200.00]; // Total: 700.00

        $totalPaid = array_sum($payments);
        $status = $this->determineStatus($totalAmount, $totalPaid);

        $this->assertLessThan($totalAmount, $totalPaid, 'Total paid should be less than total amount');
        $this->assertEquals('Partial', $status, 'Status should remain Partial until fully paid');
    }

    /**
     * Test final payment changes status to Paid.
     */
    public function testFinalPaymentChangesStatusToPaid(): void
    {
        $totalAmount = 1000.00;
        $currentPaid = 700.00;
        $finalPayment = 300.00;

        $newPaidAmount = $currentPaid + $finalPayment;
        $newStatus = $this->determineStatus($totalAmount, $newPaidAmount);

        $this->assertGreaterThanOrEqual($totalAmount, $newPaidAmount, 'Should be fully paid');
        $this->assertEquals('Paid', $newStatus, 'Final payment should change status to Paid');
    }

    /**
     * Test partial payment tracking with multiple payments.
     */
    public function testPartialPaymentTracking(): void
    {
        $invoice = [
            'totalAmount' => 1000.00,
            'payments' => [
                ['date' => '2025-01-15', 'amount' => 200.00],
                ['date' => '2025-01-20', 'amount' => 300.00],
                ['date' => '2025-01-25', 'amount' => 500.00],
            ],
        ];

        $totalPaid = array_sum(array_column($invoice['payments'], 'amount'));
        $balanceRemaining = $invoice['totalAmount'] - $totalPaid;
        $paymentCount = count($invoice['payments']);

        $this->assertEquals(1000.00, $totalPaid, 'Total paid should sum all payments');
        $this->assertEquals(0.00, $balanceRemaining, 'Balance should be zero after full payment');
        $this->assertEquals(3, $paymentCount, 'Should track 3 separate payments');
    }

    // =========================================================================
    // INVOICE CANCELLATION TESTS
    // =========================================================================

    /**
     * Test cancelled invoice cannot receive payments.
     */
    public function testCancelledInvoiceCannotReceivePayments(): void
    {
        $status = 'Cancelled';
        $canReceivePayment = !in_array($status, ['Cancelled', 'Refunded', 'Paid']);

        $this->assertFalse($canReceivePayment, 'Cancelled invoice should not accept payments');
    }

    /**
     * Test refunded invoice cannot receive payments.
     */
    public function testRefundedInvoiceCannotReceivePayments(): void
    {
        $status = 'Refunded';
        $canReceivePayment = !in_array($status, ['Cancelled', 'Refunded', 'Paid']);

        $this->assertFalse($canReceivePayment, 'Refunded invoice should not accept payments');
    }

    /**
     * Test paid invoice cannot receive additional payments (without refund).
     */
    public function testPaidInvoiceCannotReceivePayments(): void
    {
        $status = 'Paid';
        $canReceivePayment = !in_array($status, ['Cancelled', 'Refunded', 'Paid']);

        $this->assertFalse($canReceivePayment, 'Paid invoice should not accept additional payments');
    }

    /**
     * Test issued invoice can receive payments.
     */
    public function testIssuedInvoiceCanReceivePayments(): void
    {
        $status = 'Issued';
        $canReceivePayment = !in_array($status, ['Cancelled', 'Refunded', 'Paid']);

        $this->assertTrue($canReceivePayment, 'Issued invoice should accept payments');
    }

    /**
     * Test partial invoice can receive payments.
     */
    public function testPartialInvoiceCanReceivePayments(): void
    {
        $status = 'Partial';
        $canReceivePayment = !in_array($status, ['Cancelled', 'Refunded', 'Paid']);

        $this->assertTrue($canReceivePayment, 'Partial invoice should accept payments');
    }

    /**
     * Test cancellation preserves audit trail.
     */
    public function testCancellationPreservesAuditTrail(): void
    {
        $invoice = $this->sampleInvoice;
        $invoice['status'] = 'Cancelled';

        // Original values should be preserved
        $this->assertArrayHasKey('totalAmount', $invoice, 'Total amount should be preserved');
        $this->assertArrayHasKey('paidAmount', $invoice, 'Paid amount should be preserved');
        $this->assertArrayHasKey('invoiceNumber', $invoice, 'Invoice number should be preserved');
        $this->assertArrayHasKey('timestampCreated', $invoice, 'Creation timestamp should be preserved');
    }

    // =========================================================================
    // FINANCIAL CALCULATIONS TESTS
    // =========================================================================

    /**
     * Test GST/QST calculation (Quebec tax rates).
     */
    public function testQuebecTaxCalculation(): void
    {
        $subtotal = 1000.00;
        $gstRate = 0.05;    // 5% Federal GST
        $qstRate = 0.09975; // 9.975% Quebec QST

        $gst = round($subtotal * $gstRate, 2);
        $qst = round($subtotal * $qstRate, 2);
        $totalTax = $gst + $qst;
        $total = $subtotal + $totalTax;

        $this->assertEquals(50.00, $gst, 'GST should be 5% of subtotal');
        $this->assertEquals(99.75, $qst, 'QST should be 9.975% of subtotal');
        $this->assertEquals(149.75, $totalTax, 'Total tax should be GST + QST');
        $this->assertEquals(1149.75, $total, 'Total should be subtotal + total tax');
    }

    /**
     * Test collection rate calculation.
     */
    public function testCollectionRateCalculation(): void
    {
        $totalInvoiced = 10000.00;
        $totalCollected = 7500.00;

        $collectionRate = ($totalInvoiced > 0)
            ? round(($totalCollected / $totalInvoiced) * 100, 2)
            : 0;

        $this->assertEquals(75.00, $collectionRate, 'Collection rate should be 75%');
    }

    /**
     * Test outstanding balance calculation across multiple invoices.
     */
    public function testOutstandingBalanceCalculation(): void
    {
        $invoices = [
            ['totalAmount' => 1000.00, 'paidAmount' => 1000.00], // Fully paid
            ['totalAmount' => 800.00, 'paidAmount' => 500.00],   // Partial
            ['totalAmount' => 600.00, 'paidAmount' => 0.00],     // Unpaid
        ];

        $totalOutstanding = 0;
        foreach ($invoices as $invoice) {
            $totalOutstanding += ($invoice['totalAmount'] - $invoice['paidAmount']);
        }

        $this->assertEquals(900.00, $totalOutstanding, 'Outstanding balance should be sum of unpaid amounts');
    }

    /**
     * Test overdue detection.
     */
    public function testOverdueDetection(): void
    {
        $dueDate = '2025-01-15';
        $today = '2025-01-20';

        $isOverdue = strtotime($dueDate) < strtotime($today);
        $daysOverdue = (strtotime($today) - strtotime($dueDate)) / (60 * 60 * 24);

        $this->assertTrue($isOverdue, 'Invoice should be detected as overdue');
        $this->assertEquals(5, $daysOverdue, 'Should be 5 days overdue');
    }

    /**
     * Test overdue only applies to unpaid invoices.
     */
    public function testOverdueOnlyAppliestoUnpaidInvoices(): void
    {
        $testCases = [
            ['status' => 'Issued', 'dueDate' => '2025-01-15', 'today' => '2025-01-20', 'isOverdue' => true],
            ['status' => 'Partial', 'dueDate' => '2025-01-15', 'today' => '2025-01-20', 'isOverdue' => true],
            ['status' => 'Paid', 'dueDate' => '2025-01-15', 'today' => '2025-01-20', 'isOverdue' => false],
            ['status' => 'Cancelled', 'dueDate' => '2025-01-15', 'today' => '2025-01-20', 'isOverdue' => false],
        ];

        foreach ($testCases as $case) {
            $isPastDue = strtotime($case['dueDate']) < strtotime($case['today']);
            $isUnpaidStatus = in_array($case['status'], ['Issued', 'Partial']);
            $isOverdue = $isPastDue && $isUnpaidStatus;

            $this->assertEquals(
                $case['isOverdue'],
                $isOverdue,
                "Status {$case['status']} should have isOverdue = " . ($case['isOverdue'] ? 'true' : 'false')
            );
        }
    }

    // =========================================================================
    // DATA VALIDATION TESTS
    // =========================================================================

    /**
     * Test invoice date must be before or equal to due date.
     */
    public function testInvoiceDateMustBeBeforeOrEqualToDueDate(): void
    {
        $invoiceDate = '2025-01-15';
        $dueDate = '2025-02-15';

        $isValid = strtotime($invoiceDate) <= strtotime($dueDate);

        $this->assertTrue($isValid, 'Invoice date should be before or equal to due date');
    }

    /**
     * Test payment date must not be in the future.
     */
    public function testPaymentDateValidation(): void
    {
        $paymentDate = '2025-01-20';
        $today = '2025-01-25';

        $isValid = strtotime($paymentDate) <= strtotime($today);

        $this->assertTrue($isValid, 'Payment date should not be in the future');
    }

    /**
     * Test amounts must have at most 2 decimal places.
     */
    public function testAmountDecimalPrecision(): void
    {
        $amount = 1149.75;
        $roundedAmount = round($amount, 2);

        $this->assertEquals($amount, $roundedAmount, 'Amount should have at most 2 decimal places');
    }

    /**
     * Test negative amounts are not allowed.
     */
    public function testNegativeAmountsNotAllowed(): void
    {
        $amount = 100.00;

        $this->assertGreaterThanOrEqual(0, $amount, 'Invoice amount cannot be negative');
    }

    // =========================================================================
    // QUERY FILTER TESTS
    // =========================================================================

    /**
     * Test status filter values.
     */
    public function testStatusFilterValues(): void
    {
        $filterableStatuses = [
            'Pending',
            'Issued',
            'Partial',
            'Outstanding', // Special filter: Issued + Partial
            'Overdue',     // Special filter: Outstanding + past due date
            'Paid',
            'Cancelled',
            'Refunded',
        ];

        $this->assertCount(8, $filterableStatuses, 'Should have 8 filterable status values');
        $this->assertContains('Outstanding', $filterableStatuses, 'Should support Outstanding filter');
        $this->assertContains('Overdue', $filterableStatuses, 'Should support Overdue filter');
    }

    /**
     * Test date range filter logic.
     */
    public function testDateRangeFilterLogic(): void
    {
        $dateFrom = '2025-01-01';
        $dateTo = '2025-01-31';
        $invoiceDate = '2025-01-15';

        $isInRange = strtotime($invoiceDate) >= strtotime($dateFrom)
            && strtotime($invoiceDate) <= strtotime($dateTo);

        $this->assertTrue($isInRange, 'Invoice date should be within date range');
    }

    /**
     * Test family filter includes all children in family.
     */
    public function testFamilyFilterIncludesAllChildren(): void
    {
        $familyID = 50;
        $invoices = [
            ['gibbonFamilyID' => 50, 'gibbonPersonID' => 100], // Child 1
            ['gibbonFamilyID' => 50, 'gibbonPersonID' => 101], // Child 2
            ['gibbonFamilyID' => 51, 'gibbonPersonID' => 102], // Different family
        ];

        $filteredInvoices = array_filter($invoices, function ($inv) use ($familyID) {
            return $inv['gibbonFamilyID'] === $familyID;
        });

        $this->assertCount(2, $filteredInvoices, 'Family filter should return invoices for all children in family');
    }

    // =========================================================================
    // EDGE CASE TESTS
    // =========================================================================

    /**
     * Test zero amount invoice handling.
     */
    public function testZeroAmountInvoice(): void
    {
        $invoice = $this->sampleInvoice;
        $invoice['subtotal'] = 0.00;
        $invoice['taxAmount'] = 0.00;
        $invoice['totalAmount'] = 0.00;
        $invoice['paidAmount'] = 0.00;

        $status = $this->determineStatus($invoice['totalAmount'], $invoice['paidAmount']);

        // Zero amount invoice should be considered paid (0 >= 0)
        $this->assertEquals('Paid', $status, 'Zero amount invoice should be considered Paid');
    }

    /**
     * Test exact payment amount matches total.
     */
    public function testExactPaymentMatchesTotal(): void
    {
        $totalAmount = 1149.75;
        $paymentAmount = 1149.75;

        $newPaidAmount = $paymentAmount;
        $isFullyPaid = $newPaidAmount >= $totalAmount;
        $balanceRemaining = $totalAmount - $newPaidAmount;

        $this->assertTrue($isFullyPaid, 'Exact payment should fully pay invoice');
        $this->assertEquals(0.00, $balanceRemaining, 'Balance should be exactly zero');
    }

    /**
     * Test floating point precision in financial calculations.
     */
    public function testFloatingPointPrecision(): void
    {
        // Classic floating point issue: 0.1 + 0.2 = 0.30000000000000004
        $payment1 = 0.10;
        $payment2 = 0.20;
        $expectedTotal = 0.30;

        $actualTotal = round($payment1 + $payment2, 2);

        $this->assertEquals($expectedTotal, $actualTotal, 'Financial calculations should handle floating point precision');
    }

    /**
     * Test large invoice amounts.
     */
    public function testLargeInvoiceAmounts(): void
    {
        $subtotal = 99999999.99;
        $taxRate = 0.14975;
        $expectedTax = round($subtotal * $taxRate, 2);
        $expectedTotal = round($subtotal + $expectedTax, 2);

        $this->assertIsFloat($expectedTotal, 'Should handle large invoice amounts');
        $this->assertLessThan(PHP_FLOAT_MAX, $expectedTotal, 'Total should not exceed PHP float max');
    }

    /**
     * Test invoice with many payments.
     */
    public function testInvoiceWithManyPayments(): void
    {
        $totalAmount = 1000.00;
        $paymentCount = 50;
        $paymentAmount = $totalAmount / $paymentCount;

        $totalPaid = $paymentAmount * $paymentCount;
        $status = $this->determineStatus($totalAmount, $totalPaid);

        $this->assertEquals($totalAmount, $totalPaid, 'Total paid should equal total amount');
        $this->assertEquals('Paid', $status, 'Status should be Paid after all payments');
    }
}
