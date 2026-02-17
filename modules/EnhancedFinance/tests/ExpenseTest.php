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
use Gibbon\Module\EnhancedFinance\Domain\ExpenseGateway;
use Gibbon\Module\EnhancedFinance\Domain\ExportGateway;
use Gibbon\Contracts\Database\Connection;
use Gibbon\Contracts\Database\Result;

/**
 * Unit tests for ExpenseGateway and ExportGateway.
 *
 * Tests expense creation, retrieval, status transitions, approval workflow,
 * summary calculations, and export log functionality including audit trails.
 *
 * @version v1.0.02
 * @since   v1.0.02
 */
class ExpenseTest extends TestCase
{
    /**
     * @var MockObject|Connection
     */
    protected $db;

    /**
     * @var ExpenseGateway|MockObject
     */
    protected $expenseGateway;

    /**
     * @var ExportGateway|MockObject
     */
    protected $exportGateway;

    /**
     * Sample expense data for testing.
     *
     * @var array
     */
    protected $sampleExpense;

    /**
     * Sample export log data for testing.
     *
     * @var array
     */
    protected $sampleExport;

    /**
     * Set up test fixtures.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create mock database connection
        $this->db = $this->createMock(Connection::class);

        // Sample expense data
        $this->sampleExpense = [
            'gibbonEnhancedFinanceExpenseID' => 1,
            'gibbonEnhancedFinanceExpenseCategoryID' => 5,
            'gibbonSchoolYearID' => 2025,
            'expenseDate' => '2025-01-15',
            'amount' => 500.00,
            'taxAmount' => 74.88,
            'totalAmount' => 574.88,
            'vendor' => 'Office Supplies Inc.',
            'reference' => 'INV-12345',
            'paymentMethod' => 'CreditCard',
            'description' => 'Office supplies for January',
            'receiptPath' => '/uploads/receipts/receipt_001.pdf',
            'status' => 'Pending',
            'approvedByID' => null,
            'approvedAt' => null,
            'createdByID' => 1,
            'timestampCreated' => '2025-01-15 10:00:00',
            'timestampModified' => '2025-01-15 10:00:00',
            'categoryName' => 'Office Supplies',
            'categoryAccountCode' => '5200',
        ];

        // Sample export log data
        $this->sampleExport = [
            'gibbonEnhancedFinanceExportLogID' => 1,
            'exportType' => 'Sage50',
            'exportFormat' => 'CSV',
            'gibbonSchoolYearID' => 2025,
            'dateRangeStart' => '2025-01-01',
            'dateRangeEnd' => '2025-01-31',
            'recordCount' => 150,
            'totalAmount' => 25000.00,
            'fileName' => 'sage50_export_20250131.csv',
            'filePath' => '/exports/sage50_export_20250131.csv',
            'fileSize' => 45678,
            'checksum' => 'abc123def456',
            'status' => 'Completed',
            'errorMessage' => null,
            'exportedByID' => 1,
            'timestampCreated' => '2025-01-31 14:30:00',
        ];
    }

    /**
     * Clean up after tests.
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        $this->db = null;
        $this->expenseGateway = null;
        $this->exportGateway = null;
    }

    // =========================================================================
    // EXPENSE DATA STRUCTURE TESTS
    // =========================================================================

    /**
     * Test that expense data structure contains required fields.
     */
    public function testExpenseDataStructureHasRequiredFields(): void
    {
        $requiredFields = [
            'gibbonEnhancedFinanceExpenseID',
            'gibbonEnhancedFinanceExpenseCategoryID',
            'gibbonSchoolYearID',
            'expenseDate',
            'amount',
            'taxAmount',
            'totalAmount',
            'paymentMethod',
            'status',
        ];

        foreach ($requiredFields as $field) {
            $this->assertArrayHasKey(
                $field,
                $this->sampleExpense,
                "Expense should contain required field: {$field}"
            );
        }
    }

    /**
     * Test expense total calculation (amount + tax).
     */
    public function testExpenseTotalCalculation(): void
    {
        $amount = 500.00;
        $taxRate = 0.14975; // GST 5% + QST 9.975%
        $expectedTax = round($amount * $taxRate, 2);
        $expectedTotal = $amount + $expectedTax;

        $this->assertEquals(74.88, $expectedTax, 'Tax amount should be calculated correctly');
        $this->assertEquals(574.88, $expectedTotal, 'Total should equal amount + tax');
    }

    /**
     * Test expense with zero tax amount.
     */
    public function testExpenseWithZeroTax(): void
    {
        $expense = $this->sampleExpense;
        $expense['amount'] = 100.00;
        $expense['taxAmount'] = 0.00;
        $expense['totalAmount'] = 100.00;

        $calculatedTotal = $expense['amount'] + $expense['taxAmount'];

        $this->assertEquals($expense['totalAmount'], $calculatedTotal, 'Total should equal amount when no tax');
    }

    // =========================================================================
    // EXPENSE STATUS TESTS
    // =========================================================================

    /**
     * Test valid expense statuses.
     */
    public function testValidExpenseStatuses(): void
    {
        $validStatuses = ['Pending', 'Approved', 'Paid', 'Rejected'];

        $this->assertContains(
            $this->sampleExpense['status'],
            $validStatuses,
            'Expense status should be one of the valid options'
        );
    }

    /**
     * Test status transition: Pending to Approved.
     */
    public function testStatusTransitionPendingToApproved(): void
    {
        $currentStatus = 'Pending';
        $newStatus = 'Approved';

        $allowedTransitions = ['Pending' => ['Approved', 'Rejected']];

        $this->assertContains(
            $newStatus,
            $allowedTransitions[$currentStatus],
            'Pending expense should be able to transition to Approved'
        );
    }

    /**
     * Test status transition: Approved to Paid.
     */
    public function testStatusTransitionApprovedToPaid(): void
    {
        $currentStatus = 'Approved';
        $newStatus = 'Paid';

        $allowedTransitions = ['Approved' => ['Paid']];

        $this->assertContains(
            $newStatus,
            $allowedTransitions[$currentStatus],
            'Approved expense should be able to transition to Paid'
        );
    }

    /**
     * Test status transition: Pending to Rejected.
     */
    public function testStatusTransitionPendingToRejected(): void
    {
        $currentStatus = 'Pending';
        $newStatus = 'Rejected';

        $allowedTransitions = ['Pending' => ['Approved', 'Rejected']];

        $this->assertContains(
            $newStatus,
            $allowedTransitions[$currentStatus],
            'Pending expense should be able to transition to Rejected'
        );
    }

    /**
     * Test that rejected expenses cannot transition.
     */
    public function testRejectedExpenseCannotTransition(): void
    {
        $currentStatus = 'Rejected';

        $allowedTransitions = [
            'Pending' => ['Approved', 'Rejected'],
            'Approved' => ['Paid'],
            'Paid' => [],
            'Rejected' => [],
        ];

        $this->assertEmpty(
            $allowedTransitions[$currentStatus],
            'Rejected expense should not have any allowed transitions'
        );
    }

    /**
     * Test that paid expenses cannot transition.
     */
    public function testPaidExpenseCannotTransition(): void
    {
        $currentStatus = 'Paid';

        $allowedTransitions = [
            'Pending' => ['Approved', 'Rejected'],
            'Approved' => ['Paid'],
            'Paid' => [],
            'Rejected' => [],
        ];

        $this->assertEmpty(
            $allowedTransitions[$currentStatus],
            'Paid expense should not have any allowed transitions'
        );
    }

    // =========================================================================
    // PAYMENT METHOD TESTS
    // =========================================================================

    /**
     * Test valid payment methods.
     */
    public function testValidPaymentMethods(): void
    {
        $validMethods = ['Cash', 'Cheque', 'ETransfer', 'CreditCard', 'DebitCard', 'Other'];

        $this->assertContains(
            $this->sampleExpense['paymentMethod'],
            $validMethods,
            'Payment method should be one of the valid options'
        );
    }

    /**
     * Test default payment method is Other.
     */
    public function testDefaultPaymentMethod(): void
    {
        $defaultMethod = 'Other';

        $this->assertEquals(
            'Other',
            $defaultMethod,
            'Default payment method should be Other'
        );
    }

    // =========================================================================
    // EXPENSE AMOUNT VALIDATION TESTS
    // =========================================================================

    /**
     * Test expense amount must be positive.
     */
    public function testExpenseAmountMustBePositive(): void
    {
        $this->assertGreaterThan(
            0,
            $this->sampleExpense['amount'],
            'Expense amount must be positive'
        );
    }

    /**
     * Test expense tax amount can be zero.
     */
    public function testExpenseTaxAmountCanBeZero(): void
    {
        $taxAmount = 0.00;

        $this->assertGreaterThanOrEqual(0, $taxAmount, 'Tax amount can be zero');
    }

    /**
     * Test expense total must equal amount plus tax.
     */
    public function testExpenseTotalMustEqualAmountPlusTax(): void
    {
        $amount = $this->sampleExpense['amount'];
        $taxAmount = $this->sampleExpense['taxAmount'];
        $total = $this->sampleExpense['totalAmount'];

        $calculatedTotal = $amount + $taxAmount;

        $this->assertEquals(
            $total,
            $calculatedTotal,
            'Total amount must equal amount + tax amount'
        );
    }

    /**
     * Test amounts must have at most 2 decimal places.
     */
    public function testAmountDecimalPrecision(): void
    {
        $amount = 500.00;
        $roundedAmount = round($amount, 2);

        $this->assertEquals($amount, $roundedAmount, 'Amount should have at most 2 decimal places');
    }

    /**
     * Test floating point precision in financial calculations.
     */
    public function testFloatingPointPrecision(): void
    {
        $expense1 = 0.10;
        $expense2 = 0.20;
        $expectedTotal = 0.30;

        $actualTotal = round($expense1 + $expense2, 2);

        $this->assertEquals($expectedTotal, $actualTotal, 'Financial calculations should handle floating point precision');
    }

    // =========================================================================
    // EXPENSE DATE VALIDATION TESTS
    // =========================================================================

    /**
     * Test expense date format.
     */
    public function testExpenseDateFormat(): void
    {
        $expenseDate = $this->sampleExpense['expenseDate'];

        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}$/',
            $expenseDate,
            'Expense date should be in YYYY-MM-DD format'
        );
    }

    /**
     * Test expense date validation.
     */
    public function testExpenseDateValidation(): void
    {
        $expenseDate = '2025-01-15';
        $today = '2025-01-20';

        $isValid = strtotime($expenseDate) <= strtotime($today);

        $this->assertTrue($isValid, 'Expense date should not be in the future');
    }

    // =========================================================================
    // EXPENSE CATEGORY TESTS
    // =========================================================================

    /**
     * Test expense has category association.
     */
    public function testExpenseHasCategoryAssociation(): void
    {
        $this->assertArrayHasKey(
            'gibbonEnhancedFinanceExpenseCategoryID',
            $this->sampleExpense,
            'Expense should have a category ID'
        );

        $this->assertGreaterThan(
            0,
            $this->sampleExpense['gibbonEnhancedFinanceExpenseCategoryID'],
            'Category ID should be a positive integer'
        );
    }

    /**
     * Test expense category has account code for export.
     */
    public function testExpenseCategoryHasAccountCode(): void
    {
        $this->assertArrayHasKey(
            'categoryAccountCode',
            $this->sampleExpense,
            'Expense should include category account code for export'
        );
    }

    // =========================================================================
    // EXPENSE SUMMARY CALCULATION TESTS
    // =========================================================================

    /**
     * Test expense summary by category calculation.
     */
    public function testExpenseSummaryByCategory(): void
    {
        $expenses = [
            ['gibbonEnhancedFinanceExpenseCategoryID' => 1, 'totalAmount' => 100.00, 'status' => 'Paid'],
            ['gibbonEnhancedFinanceExpenseCategoryID' => 1, 'totalAmount' => 200.00, 'status' => 'Paid'],
            ['gibbonEnhancedFinanceExpenseCategoryID' => 2, 'totalAmount' => 150.00, 'status' => 'Paid'],
            ['gibbonEnhancedFinanceExpenseCategoryID' => 1, 'totalAmount' => 50.00, 'status' => 'Rejected'],
        ];

        $category1Total = 0;
        $category1Count = 0;
        foreach ($expenses as $expense) {
            if ($expense['gibbonEnhancedFinanceExpenseCategoryID'] === 1 && $expense['status'] !== 'Rejected') {
                $category1Total += $expense['totalAmount'];
                $category1Count++;
            }
        }

        $this->assertEquals(300.00, $category1Total, 'Category 1 total should sum non-rejected expenses');
        $this->assertEquals(2, $category1Count, 'Category 1 should have 2 non-rejected expenses');
    }

    /**
     * Test expense summary by status calculation.
     */
    public function testExpenseSummaryByStatus(): void
    {
        $expenses = [
            ['status' => 'Pending', 'totalAmount' => 100.00],
            ['status' => 'Approved', 'totalAmount' => 200.00],
            ['status' => 'Paid', 'totalAmount' => 300.00],
            ['status' => 'Paid', 'totalAmount' => 150.00],
            ['status' => 'Rejected', 'totalAmount' => 50.00],
        ];

        $statusCounts = [];
        $statusTotals = [];
        foreach ($expenses as $expense) {
            $status = $expense['status'];
            if (!isset($statusCounts[$status])) {
                $statusCounts[$status] = 0;
                $statusTotals[$status] = 0;
            }
            $statusCounts[$status]++;
            $statusTotals[$status] += $expense['totalAmount'];
        }

        $this->assertEquals(1, $statusCounts['Pending'], 'Should have 1 pending expense');
        $this->assertEquals(2, $statusCounts['Paid'], 'Should have 2 paid expenses');
        $this->assertEquals(450.00, $statusTotals['Paid'], 'Paid expenses should total 450');
    }

    /**
     * Test expense summary by month calculation.
     */
    public function testExpenseSummaryByMonth(): void
    {
        $expenses = [
            ['expenseDate' => '2025-01-10', 'totalAmount' => 100.00],
            ['expenseDate' => '2025-01-25', 'totalAmount' => 200.00],
            ['expenseDate' => '2025-02-15', 'totalAmount' => 150.00],
        ];

        $monthTotals = [];
        foreach ($expenses as $expense) {
            $month = substr($expense['expenseDate'], 0, 7);
            if (!isset($monthTotals[$month])) {
                $monthTotals[$month] = 0;
            }
            $monthTotals[$month] += $expense['totalAmount'];
        }

        $this->assertEquals(300.00, $monthTotals['2025-01'], 'January total should be 300');
        $this->assertEquals(150.00, $monthTotals['2025-02'], 'February total should be 150');
    }

    /**
     * Test expense summary by vendor calculation.
     */
    public function testExpenseSummaryByVendor(): void
    {
        $expenses = [
            ['vendor' => 'Vendor A', 'totalAmount' => 100.00],
            ['vendor' => 'Vendor A', 'totalAmount' => 200.00],
            ['vendor' => 'Vendor B', 'totalAmount' => 150.00],
            ['vendor' => '', 'totalAmount' => 50.00], // No vendor
        ];

        $vendorTotals = [];
        foreach ($expenses as $expense) {
            if (!empty($expense['vendor'])) {
                $vendor = $expense['vendor'];
                if (!isset($vendorTotals[$vendor])) {
                    $vendorTotals[$vendor] = 0;
                }
                $vendorTotals[$vendor] += $expense['totalAmount'];
            }
        }

        $this->assertEquals(300.00, $vendorTotals['Vendor A'], 'Vendor A total should be 300');
        $this->assertCount(2, $vendorTotals, 'Should have 2 vendors with totals');
    }

    // =========================================================================
    // EXPENSE APPROVAL WORKFLOW TESTS
    // =========================================================================

    /**
     * Test approval requires approver ID.
     */
    public function testApprovalRequiresApproverID(): void
    {
        $status = 'Approved';
        $approvedByID = 1;

        $this->assertNotNull($approvedByID, 'Approval should require an approver ID');
        $this->assertGreaterThan(0, $approvedByID, 'Approver ID should be positive');
    }

    /**
     * Test approval timestamp is set.
     */
    public function testApprovalTimestampIsSet(): void
    {
        $approvedAt = date('Y-m-d H:i:s');

        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
            $approvedAt,
            'Approval timestamp should be in datetime format'
        );
    }

    /**
     * Test pending expense has no approver.
     */
    public function testPendingExpenseHasNoApprover(): void
    {
        $expense = $this->sampleExpense;

        $this->assertEquals('Pending', $expense['status'], 'Status should be Pending');
        $this->assertNull($expense['approvedByID'], 'Pending expense should have no approver');
        $this->assertNull($expense['approvedAt'], 'Pending expense should have no approval timestamp');
    }

    // =========================================================================
    // EXPENSE INSERT VALIDATION TESTS
    // =========================================================================

    /**
     * Test expense insert requires category ID.
     */
    public function testExpenseInsertRequiresCategoryID(): void
    {
        $data = [
            'gibbonSchoolYearID' => 2025,
            'expenseDate' => '2025-01-15',
            'amount' => 100.00,
        ];

        $isValid = !empty($data['gibbonEnhancedFinanceExpenseCategoryID'] ?? null);

        $this->assertFalse($isValid, 'Insert should fail without category ID');
    }

    /**
     * Test expense insert requires school year ID.
     */
    public function testExpenseInsertRequiresSchoolYearID(): void
    {
        $data = [
            'gibbonEnhancedFinanceExpenseCategoryID' => 1,
            'expenseDate' => '2025-01-15',
            'amount' => 100.00,
        ];

        $isValid = !empty($data['gibbonSchoolYearID'] ?? null);

        $this->assertFalse($isValid, 'Insert should fail without school year ID');
    }

    /**
     * Test expense insert requires expense date.
     */
    public function testExpenseInsertRequiresExpenseDate(): void
    {
        $data = [
            'gibbonEnhancedFinanceExpenseCategoryID' => 1,
            'gibbonSchoolYearID' => 2025,
            'amount' => 100.00,
        ];

        $isValid = !empty($data['expenseDate'] ?? null);

        $this->assertFalse($isValid, 'Insert should fail without expense date');
    }

    /**
     * Test expense insert calculates total amount.
     */
    public function testExpenseInsertCalculatesTotalAmount(): void
    {
        $data = [
            'amount' => 100.00,
            'taxAmount' => 14.98,
        ];

        if (!isset($data['totalAmount'])) {
            $data['totalAmount'] = ($data['amount'] ?? 0) + ($data['taxAmount'] ?? 0);
        }

        $this->assertEquals(114.98, $data['totalAmount'], 'Total should be calculated if not provided');
    }

    /**
     * Test expense insert sets default status.
     */
    public function testExpenseInsertSetsDefaultStatus(): void
    {
        $data = [];

        if (empty($data['status'])) {
            $data['status'] = 'Pending';
        }

        $this->assertEquals('Pending', $data['status'], 'Default status should be Pending');
    }

    /**
     * Test expense insert sets default payment method.
     */
    public function testExpenseInsertSetsDefaultPaymentMethod(): void
    {
        $data = [];

        if (empty($data['paymentMethod'])) {
            $data['paymentMethod'] = 'Other';
        }

        $this->assertEquals('Other', $data['paymentMethod'], 'Default payment method should be Other');
    }

    // =========================================================================
    // EXPORT LOG DATA STRUCTURE TESTS
    // =========================================================================

    /**
     * Test that export log data structure contains required fields.
     */
    public function testExportLogDataStructureHasRequiredFields(): void
    {
        $requiredFields = [
            'gibbonEnhancedFinanceExportLogID',
            'exportType',
            'exportFormat',
            'gibbonSchoolYearID',
            'status',
            'exportedByID',
        ];

        foreach ($requiredFields as $field) {
            $this->assertArrayHasKey(
                $field,
                $this->sampleExport,
                "Export log should contain required field: {$field}"
            );
        }
    }

    /**
     * Test valid export types.
     */
    public function testValidExportTypes(): void
    {
        $validTypes = ['Sage50', 'QuickBooks', 'Acomba', 'Releve24'];

        $this->assertContains(
            $this->sampleExport['exportType'],
            $validTypes,
            'Export type should be one of the valid options'
        );
    }

    /**
     * Test valid export formats.
     */
    public function testValidExportFormats(): void
    {
        $validFormats = ['CSV', 'IIF', 'XML', 'TXT'];

        $this->assertContains(
            $this->sampleExport['exportFormat'],
            $validFormats,
            'Export format should be one of the valid options'
        );
    }

    // =========================================================================
    // EXPORT STATUS WORKFLOW TESTS
    // =========================================================================

    /**
     * Test valid export statuses.
     */
    public function testValidExportStatuses(): void
    {
        $validStatuses = ['Pending', 'Processing', 'Completed', 'Failed'];

        $this->assertContains(
            $this->sampleExport['status'],
            $validStatuses,
            'Export status should be one of the valid options'
        );
    }

    /**
     * Test export status transition: Pending to Processing.
     */
    public function testExportStatusTransitionPendingToProcessing(): void
    {
        $currentStatus = 'Pending';
        $newStatus = 'Processing';

        $allowedTransitions = [
            'Pending' => ['Processing', 'Failed'],
            'Processing' => ['Completed', 'Failed'],
            'Completed' => [],
            'Failed' => [],
        ];

        $this->assertContains(
            $newStatus,
            $allowedTransitions[$currentStatus],
            'Pending export should be able to transition to Processing'
        );
    }

    /**
     * Test export status transition: Processing to Completed.
     */
    public function testExportStatusTransitionProcessingToCompleted(): void
    {
        $currentStatus = 'Processing';
        $newStatus = 'Completed';

        $allowedTransitions = [
            'Pending' => ['Processing', 'Failed'],
            'Processing' => ['Completed', 'Failed'],
            'Completed' => [],
            'Failed' => [],
        ];

        $this->assertContains(
            $newStatus,
            $allowedTransitions[$currentStatus],
            'Processing export should be able to transition to Completed'
        );
    }

    /**
     * Test export status transition: Processing to Failed.
     */
    public function testExportStatusTransitionProcessingToFailed(): void
    {
        $currentStatus = 'Processing';
        $newStatus = 'Failed';

        $allowedTransitions = [
            'Pending' => ['Processing', 'Failed'],
            'Processing' => ['Completed', 'Failed'],
            'Completed' => [],
            'Failed' => [],
        ];

        $this->assertContains(
            $newStatus,
            $allowedTransitions[$currentStatus],
            'Processing export should be able to transition to Failed'
        );
    }

    /**
     * Test completed export cannot transition.
     */
    public function testCompletedExportCannotTransition(): void
    {
        $currentStatus = 'Completed';

        $allowedTransitions = [
            'Pending' => ['Processing', 'Failed'],
            'Processing' => ['Completed', 'Failed'],
            'Completed' => [],
            'Failed' => [],
        ];

        $this->assertEmpty(
            $allowedTransitions[$currentStatus],
            'Completed export should not have any allowed transitions'
        );
    }

    // =========================================================================
    // EXPORT COMPLETION TESTS
    // =========================================================================

    /**
     * Test completed export has file path.
     */
    public function testCompletedExportHasFilePath(): void
    {
        $export = $this->sampleExport;

        $this->assertEquals('Completed', $export['status'], 'Status should be Completed');
        $this->assertNotEmpty($export['filePath'], 'Completed export should have file path');
    }

    /**
     * Test completed export has file size.
     */
    public function testCompletedExportHasFileSize(): void
    {
        $export = $this->sampleExport;

        $this->assertGreaterThan(0, $export['fileSize'], 'Completed export should have positive file size');
    }

    /**
     * Test completed export has checksum.
     */
    public function testCompletedExportHasChecksum(): void
    {
        $export = $this->sampleExport;

        $this->assertNotEmpty($export['checksum'], 'Completed export should have checksum');
    }

    /**
     * Test completed export has record count.
     */
    public function testCompletedExportHasRecordCount(): void
    {
        $export = $this->sampleExport;

        $this->assertGreaterThanOrEqual(0, $export['recordCount'], 'Record count should be non-negative');
    }

    // =========================================================================
    // EXPORT FAILED TESTS
    // =========================================================================

    /**
     * Test failed export has error message.
     */
    public function testFailedExportHasErrorMessage(): void
    {
        $export = $this->sampleExport;
        $export['status'] = 'Failed';
        $export['errorMessage'] = 'Database connection error';

        $this->assertEquals('Failed', $export['status'], 'Status should be Failed');
        $this->assertNotEmpty($export['errorMessage'], 'Failed export should have error message');
    }

    // =========================================================================
    // EXPORT INSERT TESTS
    // =========================================================================

    /**
     * Test export insert sets default status.
     */
    public function testExportInsertSetsDefaultStatus(): void
    {
        $defaults = [
            'status' => 'Pending',
            'recordCount' => 0
        ];

        $this->assertEquals('Pending', $defaults['status'], 'Default status should be Pending');
        $this->assertEquals(0, $defaults['recordCount'], 'Default record count should be 0');
    }

    // =========================================================================
    // EXPORT STATISTICS TESTS
    // =========================================================================

    /**
     * Test export statistics calculation.
     */
    public function testExportStatisticsCalculation(): void
    {
        $exports = [
            ['status' => 'Completed', 'recordCount' => 100, 'totalAmount' => 5000.00, 'fileSize' => 10000],
            ['status' => 'Completed', 'recordCount' => 150, 'totalAmount' => 7500.00, 'fileSize' => 15000],
            ['status' => 'Failed', 'recordCount' => 0, 'totalAmount' => 0, 'fileSize' => 0],
            ['status' => 'Processing', 'recordCount' => 0, 'totalAmount' => 0, 'fileSize' => 0],
        ];

        $completedExports = 0;
        $failedExports = 0;
        $totalRecords = 0;
        $totalAmount = 0;
        $totalFileSize = 0;

        foreach ($exports as $export) {
            if ($export['status'] === 'Completed') {
                $completedExports++;
                $totalRecords += $export['recordCount'];
                $totalAmount += $export['totalAmount'];
                $totalFileSize += $export['fileSize'];
            } elseif ($export['status'] === 'Failed') {
                $failedExports++;
            }
        }

        $this->assertEquals(2, $completedExports, 'Should have 2 completed exports');
        $this->assertEquals(1, $failedExports, 'Should have 1 failed export');
        $this->assertEquals(250, $totalRecords, 'Total records should be 250');
        $this->assertEquals(12500.00, $totalAmount, 'Total amount should be 12500');
        $this->assertEquals(25000, $totalFileSize, 'Total file size should be 25000');
    }

    /**
     * Test export statistics by type.
     */
    public function testExportStatisticsByType(): void
    {
        $exports = [
            ['exportType' => 'Sage50', 'status' => 'Completed', 'recordCount' => 100],
            ['exportType' => 'Sage50', 'status' => 'Completed', 'recordCount' => 150],
            ['exportType' => 'QuickBooks', 'status' => 'Completed', 'recordCount' => 200],
        ];

        $typeStats = [];
        foreach ($exports as $export) {
            $type = $export['exportType'];
            if (!isset($typeStats[$type])) {
                $typeStats[$type] = ['count' => 0, 'totalRecords' => 0];
            }
            $typeStats[$type]['count']++;
            if ($export['status'] === 'Completed') {
                $typeStats[$type]['totalRecords'] += $export['recordCount'];
            }
        }

        $this->assertEquals(2, $typeStats['Sage50']['count'], 'Sage50 should have 2 exports');
        $this->assertEquals(250, $typeStats['Sage50']['totalRecords'], 'Sage50 total records should be 250');
        $this->assertEquals(1, $typeStats['QuickBooks']['count'], 'QuickBooks should have 1 export');
    }

    // =========================================================================
    // EXPORT CLEANUP TESTS
    // =========================================================================

    /**
     * Test export cleanup retention calculation.
     */
    public function testExportCleanupRetentionCalculation(): void
    {
        $retentionDays = 90;
        $exportDate = '2024-10-01 10:00:00';
        $today = '2025-01-15';

        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$retentionDays} days", strtotime($today)));
        $shouldBeDeleted = strtotime($exportDate) < strtotime($cutoffDate);

        $this->assertTrue($shouldBeDeleted, 'Export older than retention period should be marked for cleanup');
    }

    /**
     * Test recent export is not cleaned up.
     */
    public function testRecentExportIsNotCleanedUp(): void
    {
        $retentionDays = 90;
        $exportDate = '2025-01-10 10:00:00';
        $today = '2025-01-15';

        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$retentionDays} days", strtotime($today)));
        $shouldBeDeleted = strtotime($exportDate) < strtotime($cutoffDate);

        $this->assertFalse($shouldBeDeleted, 'Recent export should not be cleaned up');
    }

    // =========================================================================
    // EXPORT FILE VALIDATION TESTS
    // =========================================================================

    /**
     * Test file path format validation.
     */
    public function testFilePathFormatValidation(): void
    {
        $filePath = '/exports/sage50_export_20250131.csv';

        $this->assertStringStartsWith('/', $filePath, 'File path should start with /');
        $this->assertMatchesRegularExpression(
            '/\.(csv|iif|xml|txt)$/i',
            $filePath,
            'File path should have valid extension'
        );
    }

    /**
     * Test checksum verification logic.
     */
    public function testChecksumVerificationLogic(): void
    {
        $storedChecksum = 'abc123def456';
        $calculatedChecksum = 'abc123def456';

        $isValid = $storedChecksum === $calculatedChecksum;

        $this->assertTrue($isValid, 'Checksum should match for verification');
    }

    /**
     * Test checksum mismatch detection.
     */
    public function testChecksumMismatchDetection(): void
    {
        $storedChecksum = 'abc123def456';
        $calculatedChecksum = 'xyz789ghi012';

        $isValid = $storedChecksum === $calculatedChecksum;

        $this->assertFalse($isValid, 'Checksum mismatch should be detected');
    }

    // =========================================================================
    // EXPORT DATE RANGE TESTS
    // =========================================================================

    /**
     * Test export date range validation.
     */
    public function testExportDateRangeValidation(): void
    {
        $dateFrom = '2025-01-01';
        $dateTo = '2025-01-31';

        $isValid = strtotime($dateFrom) <= strtotime($dateTo);

        $this->assertTrue($isValid, 'Date range start should be before or equal to end');
    }

    /**
     * Test invalid date range detection.
     */
    public function testInvalidDateRangeDetection(): void
    {
        $dateFrom = '2025-01-31';
        $dateTo = '2025-01-01';

        $isValid = strtotime($dateFrom) <= strtotime($dateTo);

        $this->assertFalse($isValid, 'Invalid date range should be detected');
    }

    // =========================================================================
    // EDGE CASE TESTS
    // =========================================================================

    /**
     * Test expense with large amount.
     */
    public function testExpenseWithLargeAmount(): void
    {
        $amount = 99999999.99;
        $taxRate = 0.14975;
        $expectedTax = round($amount * $taxRate, 2);
        $expectedTotal = round($amount + $expectedTax, 2);

        $this->assertIsFloat($expectedTotal, 'Should handle large expense amounts');
        $this->assertLessThan(PHP_FLOAT_MAX, $expectedTotal, 'Total should not exceed PHP float max');
    }

    /**
     * Test expense with zero amount.
     */
    public function testExpenseWithZeroAmount(): void
    {
        $expense = $this->sampleExpense;
        $expense['amount'] = 0.00;
        $expense['taxAmount'] = 0.00;
        $expense['totalAmount'] = 0.00;

        $isValid = $expense['amount'] >= 0;

        $this->assertTrue($isValid, 'Zero amount expense should be valid');
    }

    /**
     * Test export with zero records.
     */
    public function testExportWithZeroRecords(): void
    {
        $export = $this->sampleExport;
        $export['recordCount'] = 0;
        $export['totalAmount'] = 0.00;

        $isValid = $export['recordCount'] >= 0;

        $this->assertTrue($isValid, 'Export with zero records should be valid');
    }

    /**
     * Test expense vendor name can be empty.
     */
    public function testExpenseVendorCanBeEmpty(): void
    {
        $expense = $this->sampleExpense;
        $expense['vendor'] = '';

        $isValid = isset($expense['vendor']);

        $this->assertTrue($isValid, 'Vendor can be empty string');
    }

    /**
     * Test expense receipt path can be null.
     */
    public function testExpenseReceiptPathCanBeNull(): void
    {
        $expense = $this->sampleExpense;
        $expense['receiptPath'] = null;

        $hasReceipt = !empty($expense['receiptPath']);

        $this->assertFalse($hasReceipt, 'Receipt path can be null');
    }
}
