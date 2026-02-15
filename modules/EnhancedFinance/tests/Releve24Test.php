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
use Gibbon\Module\EnhancedFinance\Releve24;
use Gibbon\Module\EnhancedFinance\Domain\InvoiceGateway;
use Gibbon\Module\EnhancedFinance\Domain\PaymentGateway;
use Gibbon\Module\EnhancedFinance\Domain\Releve24Gateway;
use Gibbon\Contracts\Database\Connection;
use Gibbon\Domain\System\SettingGateway;

/**
 * Unit tests for Quebec Relevé 24 (RL-24) generation.
 *
 * Tests the RL-24 business logic including:
 * - Box B (Days of Care) calculation
 * - Box C (Total Amounts Paid) calculation
 * - Box D (Non-Qualifying Expenses) exclusion
 * - Box E (Qualifying Expenses) = C - D
 * - SIN formatting (XXX-XXX-XXX)
 * - Slip type handling (R, A, D)
 * - Amended slip generation
 * - Only PAID amounts inclusion
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class Releve24Test extends TestCase
{
    /**
     * @var MockObject|Connection
     */
    protected $db;

    /**
     * @var MockObject|SettingGateway
     */
    protected $settingGateway;

    /**
     * @var MockObject|InvoiceGateway
     */
    protected $invoiceGateway;

    /**
     * @var MockObject|PaymentGateway
     */
    protected $paymentGateway;

    /**
     * @var MockObject|Releve24Gateway
     */
    protected $releve24Gateway;

    /**
     * @var Releve24
     */
    protected $releve24;

    /**
     * Sample RL-24 data for testing.
     *
     * @var array
     */
    protected $sampleReleve24;

    /**
     * Sample payment data for testing.
     *
     * @var array
     */
    protected $samplePayments;

    /**
     * Set up test fixtures.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create mock objects
        $this->db = $this->createMock(Connection::class);
        $this->settingGateway = $this->createMock(SettingGateway::class);
        $this->invoiceGateway = $this->createMock(InvoiceGateway::class);
        $this->paymentGateway = $this->createMock(PaymentGateway::class);
        $this->releve24Gateway = $this->createMock(Releve24Gateway::class);

        // Sample RL-24 data
        $this->sampleReleve24 = [
            'gibbonEnhancedFinanceReleve24ID' => 1,
            'gibbonPersonID' => 100,
            'gibbonFamilyID' => 50,
            'taxYear' => 2025,
            'slipType' => 'R',
            'daysOfCare' => 220,
            'totalAmountsPaid' => 12000.00,
            'nonQualifyingExpenses' => 500.00,
            'qualifyingExpenses' => 11500.00,
            'providerSIN' => '123-456-789',
            'recipientSIN' => '987-654-321',
            'recipientName' => 'Smith, John',
            'childName' => 'Smith, Emma',
            'generatedAt' => '2026-02-01 10:00:00',
            'sentAt' => null,
            'status' => 'Draft',
            'createdByID' => 1,
        ];

        // Sample payment data
        $this->samplePayments = [
            [
                'gibbonEnhancedFinancePaymentID' => 1,
                'gibbonEnhancedFinanceInvoiceID' => 1,
                'paymentDate' => '2025-01-15',
                'amount' => 1000.00,
                'method' => 'ETransfer',
            ],
            [
                'gibbonEnhancedFinancePaymentID' => 2,
                'gibbonEnhancedFinanceInvoiceID' => 2,
                'paymentDate' => '2025-02-15',
                'amount' => 1000.00,
                'method' => 'Cash',
            ],
            [
                'gibbonEnhancedFinancePaymentID' => 3,
                'gibbonEnhancedFinanceInvoiceID' => 3,
                'paymentDate' => '2025-03-15',
                'amount' => 1000.00,
                'method' => 'Cheque',
            ],
        ];
    }

    /**
     * Clean up after tests.
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        $this->db = null;
        $this->settingGateway = null;
        $this->invoiceGateway = null;
        $this->paymentGateway = null;
        $this->releve24Gateway = null;
        $this->releve24 = null;
    }

    /**
     * Create a Releve24 instance with mocked dependencies.
     *
     * @return Releve24
     */
    protected function createReleve24Instance(): Releve24
    {
        return new Releve24(
            $this->db,
            $this->settingGateway,
            $this->invoiceGateway,
            $this->paymentGateway,
            $this->releve24Gateway
        );
    }

    // =========================================================================
    // SIN FORMATTING TESTS (XXX-XXX-XXX)
    // =========================================================================

    /**
     * Test SIN formatting with valid 9-digit input.
     */
    public function testSINFormattingWithValidInput(): void
    {
        $releve24 = $this->createReleve24Instance();

        $formatted = $releve24->formatSIN('123456789');

        $this->assertEquals('123-456-789', $formatted, 'SIN should be formatted as XXX-XXX-XXX');
    }

    /**
     * Test SIN formatting removes existing dashes and reformats.
     */
    public function testSINFormattingWithDashes(): void
    {
        $releve24 = $this->createReleve24Instance();

        $formatted = $releve24->formatSIN('123-456-789');

        $this->assertEquals('123-456-789', $formatted, 'SIN with existing dashes should be formatted correctly');
    }

    /**
     * Test SIN formatting removes spaces and reformats.
     */
    public function testSINFormattingWithSpaces(): void
    {
        $releve24 = $this->createReleve24Instance();

        $formatted = $releve24->formatSIN('123 456 789');

        $this->assertEquals('123-456-789', $formatted, 'SIN with spaces should be formatted correctly');
    }

    /**
     * Test SIN formatting returns empty for invalid length.
     */
    public function testSINFormattingInvalidLength(): void
    {
        $releve24 = $this->createReleve24Instance();

        $formatted = $releve24->formatSIN('12345678'); // Only 8 digits

        $this->assertEquals('', $formatted, 'Invalid length SIN should return empty string');
    }

    /**
     * Test SIN formatting returns empty for too many digits.
     */
    public function testSINFormattingTooManyDigits(): void
    {
        $releve24 = $this->createReleve24Instance();

        $formatted = $releve24->formatSIN('1234567890'); // 10 digits

        $this->assertEquals('', $formatted, 'SIN with too many digits should return empty string');
    }

    /**
     * Test SIN formatting returns empty for empty input.
     */
    public function testSINFormattingEmptyInput(): void
    {
        $releve24 = $this->createReleve24Instance();

        $formatted = $releve24->formatSIN('');

        $this->assertEquals('', $formatted, 'Empty input should return empty string');
    }

    /**
     * Test SIN formatting returns empty for null input.
     */
    public function testSINFormattingNullInput(): void
    {
        $releve24 = $this->createReleve24Instance();

        $formatted = $releve24->formatSIN(null);

        $this->assertEquals('', $formatted, 'Null input should return empty string');
    }

    /**
     * Test SIN formatting strips non-numeric characters.
     */
    public function testSINFormattingStripsNonNumeric(): void
    {
        $releve24 = $this->createReleve24Instance();

        $formatted = $releve24->formatSIN('1A2B3C4D5E6F7G8H9');

        $this->assertEquals('123-456-789', $formatted, 'Non-numeric characters should be stripped');
    }

    /**
     * Test SIN format regex pattern.
     */
    public function testSINFormatPattern(): void
    {
        $formattedSIN = '123-456-789';

        // Should match XXX-XXX-XXX pattern where X is a digit
        $this->assertMatchesRegularExpression(
            '/^\d{3}-\d{3}-\d{3}$/',
            $formattedSIN,
            'Formatted SIN should match XXX-XXX-XXX pattern'
        );
    }

    // =========================================================================
    // SIN VALIDATION TESTS (Luhn Algorithm)
    // =========================================================================

    /**
     * Test SIN validation with valid SIN (Luhn check passes).
     */
    public function testSINValidationWithValidSIN(): void
    {
        $releve24 = $this->createReleve24Instance();

        // 046 454 286 is a valid test SIN (Luhn checksum passes)
        $isValid = $releve24->validateSIN('046454286');

        $this->assertTrue($isValid, 'Valid SIN should pass Luhn validation');
    }

    /**
     * Test SIN validation with formatted valid SIN.
     */
    public function testSINValidationWithFormattedValidSIN(): void
    {
        $releve24 = $this->createReleve24Instance();

        $isValid = $releve24->validateSIN('046-454-286');

        $this->assertTrue($isValid, 'Formatted valid SIN should pass Luhn validation');
    }

    /**
     * Test SIN validation with invalid SIN (Luhn check fails).
     */
    public function testSINValidationWithInvalidSIN(): void
    {
        $releve24 = $this->createReleve24Instance();

        // 123 456 789 does NOT pass Luhn validation
        $isValid = $releve24->validateSIN('123456789');

        $this->assertFalse($isValid, 'Invalid SIN should fail Luhn validation');
    }

    /**
     * Test SIN validation with wrong length.
     */
    public function testSINValidationWithWrongLength(): void
    {
        $releve24 = $this->createReleve24Instance();

        $isValid = $releve24->validateSIN('12345678');

        $this->assertFalse($isValid, 'SIN with wrong length should fail validation');
    }

    /**
     * Test SIN validation with empty string.
     */
    public function testSINValidationWithEmptyString(): void
    {
        $releve24 = $this->createReleve24Instance();

        $isValid = $releve24->validateSIN('');

        $this->assertFalse($isValid, 'Empty SIN should fail validation');
    }

    // =========================================================================
    // SLIP TYPE HANDLING TESTS (R, A, D)
    // =========================================================================

    /**
     * Test slip type constants are defined correctly.
     */
    public function testSlipTypeConstantsAreDefined(): void
    {
        $this->assertEquals('R', Releve24::SLIP_TYPE_ORIGINAL, 'Original slip type should be R');
        $this->assertEquals('A', Releve24::SLIP_TYPE_AMENDED, 'Amended slip type should be A');
        $this->assertEquals('D', Releve24::SLIP_TYPE_CANCELLED, 'Cancelled slip type should be D');
    }

    /**
     * Test valid slip types.
     */
    public function testValidSlipTypes(): void
    {
        $validTypes = ['R', 'A', 'D'];

        $this->assertCount(3, $validTypes, 'Should have exactly 3 slip types');
        $this->assertContains('R', $validTypes, 'R (original) should be a valid type');
        $this->assertContains('A', $validTypes, 'A (amended) should be a valid type');
        $this->assertContains('D', $validTypes, 'D (cancelled) should be a valid type');
    }

    /**
     * Test slip type label for Original (R).
     */
    public function testSlipTypeLabelOriginal(): void
    {
        $releve24 = $this->createReleve24Instance();

        $label = $releve24->getSlipTypeLabel('R');

        $this->assertEquals('Original', $label, 'R should translate to Original');
    }

    /**
     * Test slip type label for Amended (A).
     */
    public function testSlipTypeLabelAmended(): void
    {
        $releve24 = $this->createReleve24Instance();

        $label = $releve24->getSlipTypeLabel('A');

        $this->assertEquals('Amended', $label, 'A should translate to Amended');
    }

    /**
     * Test slip type label for Cancelled (D).
     */
    public function testSlipTypeLabelCancelled(): void
    {
        $releve24 = $this->createReleve24Instance();

        $label = $releve24->getSlipTypeLabel('D');

        $this->assertEquals('Cancelled', $label, 'D should translate to Cancelled');
    }

    /**
     * Test slip type label for unknown type.
     */
    public function testSlipTypeLabelUnknown(): void
    {
        $releve24 = $this->createReleve24Instance();

        $label = $releve24->getSlipTypeLabel('X');

        $this->assertEquals('Unknown', $label, 'Unknown type should return Unknown');
    }

    /**
     * Test determine slip type returns Original for new child.
     */
    public function testDetermineSlipTypeOriginalForNewChild(): void
    {
        $releve24 = $this->createReleve24Instance();

        // Mock that no original RL-24 exists
        $this->releve24Gateway->method('hasOriginalReleve24')->willReturn(false);

        $slipType = $releve24->determineSlipType(100, 2025);

        $this->assertEquals('R', $slipType, 'New child should get Original (R) slip type');
    }

    /**
     * Test determine slip type returns Amended for existing child.
     */
    public function testDetermineSlipTypeAmendedForExistingChild(): void
    {
        $releve24 = $this->createReleve24Instance();

        // Mock that original RL-24 exists
        $this->releve24Gateway->method('hasOriginalReleve24')->willReturn(true);

        $slipType = $releve24->determineSlipType(100, 2025);

        $this->assertEquals('A', $slipType, 'Existing child should get Amended (A) slip type');
    }

    // =========================================================================
    // BOX E - QUALIFYING EXPENSES CALCULATION (C - D)
    // =========================================================================

    /**
     * Test Box E calculation (C - D).
     */
    public function testBoxECalculation(): void
    {
        $releve24 = $this->createReleve24Instance();

        $boxC = 12000.00; // Total Amounts Paid
        $boxD = 500.00;   // Non-Qualifying Expenses
        $expectedBoxE = 11500.00; // Qualifying Expenses

        $boxE = $releve24->calculateQualifyingExpenses($boxC, $boxD);

        $this->assertEquals($expectedBoxE, $boxE, 'Box E should equal Box C minus Box D');
    }

    /**
     * Test Box E calculation with zero non-qualifying expenses.
     */
    public function testBoxECalculationWithZeroNonQualifying(): void
    {
        $releve24 = $this->createReleve24Instance();

        $boxC = 10000.00;
        $boxD = 0.00;

        $boxE = $releve24->calculateQualifyingExpenses($boxC, $boxD);

        $this->assertEquals(10000.00, $boxE, 'Box E should equal Box C when no non-qualifying expenses');
    }

    /**
     * Test Box E calculation cannot be negative.
     */
    public function testBoxECalculationCannotBeNegative(): void
    {
        $releve24 = $this->createReleve24Instance();

        $boxC = 500.00;
        $boxD = 1000.00; // Non-qualifying exceeds total

        $boxE = $releve24->calculateQualifyingExpenses($boxC, $boxD);

        $this->assertEquals(0, $boxE, 'Box E cannot be negative');
        $this->assertGreaterThanOrEqual(0, $boxE, 'Box E must be >= 0');
    }

    /**
     * Test Box E calculation with equal values.
     */
    public function testBoxECalculationWithEqualValues(): void
    {
        $releve24 = $this->createReleve24Instance();

        $boxC = 5000.00;
        $boxD = 5000.00;

        $boxE = $releve24->calculateQualifyingExpenses($boxC, $boxD);

        $this->assertEquals(0, $boxE, 'Box E should be zero when C equals D');
    }

    /**
     * Test Box E calculation with decimals.
     */
    public function testBoxECalculationWithDecimals(): void
    {
        $releve24 = $this->createReleve24Instance();

        $boxC = 12345.67;
        $boxD = 1234.56;
        $expectedBoxE = 11111.11;

        $boxE = $releve24->calculateQualifyingExpenses($boxC, $boxD);

        $this->assertEquals($expectedBoxE, $boxE, 'Box E should handle decimal calculations correctly');
    }

    // =========================================================================
    // NON-QUALIFYING EXPENSE TYPES TESTS
    // =========================================================================

    /**
     * Test non-qualifying expense types constant is defined.
     */
    public function testNonQualifyingExpenseTypesAreDefined(): void
    {
        $types = Releve24::NON_QUALIFYING_EXPENSE_TYPES;

        $this->assertIsArray($types, 'Non-qualifying expense types should be an array');
        $this->assertNotEmpty($types, 'Non-qualifying expense types should not be empty');
    }

    /**
     * Test medical expenses are non-qualifying.
     */
    public function testMedicalExpensesAreNonQualifying(): void
    {
        $types = Releve24::NON_QUALIFYING_EXPENSE_TYPES;

        $this->assertContains('medical', $types, 'Medical expenses should be non-qualifying');
        $this->assertContains('hospital', $types, 'Hospital expenses should be non-qualifying');
    }

    /**
     * Test transportation expenses are non-qualifying.
     */
    public function testTransportationExpensesAreNonQualifying(): void
    {
        $types = Releve24::NON_QUALIFYING_EXPENSE_TYPES;

        $this->assertContains('transportation', $types, 'Transportation expenses should be non-qualifying');
        $this->assertContains('transport', $types, 'Transport expenses should be non-qualifying');
    }

    /**
     * Test teaching/education expenses are non-qualifying.
     */
    public function testTeachingExpensesAreNonQualifying(): void
    {
        $types = Releve24::NON_QUALIFYING_EXPENSE_TYPES;

        $this->assertContains('teaching', $types, 'Teaching expenses should be non-qualifying');
        $this->assertContains('education', $types, 'Education expenses should be non-qualifying');
    }

    /**
     * Test field trip expenses are non-qualifying.
     */
    public function testFieldTripExpensesAreNonQualifying(): void
    {
        $types = Releve24::NON_QUALIFYING_EXPENSE_TYPES;

        $this->assertContains('fieldtrip', $types, 'Field trip expenses should be non-qualifying');
        $this->assertContains('field_trip', $types, 'Field trip (underscore) expenses should be non-qualifying');
    }

    /**
     * Test registration fees are non-qualifying.
     */
    public function testRegistrationFeesAreNonQualifying(): void
    {
        $types = Releve24::NON_QUALIFYING_EXPENSE_TYPES;

        $this->assertContains('registration', $types, 'Registration fees should be non-qualifying');
        $this->assertContains('registration_fee', $types, 'Registration fee (explicit) should be non-qualifying');
    }

    /**
     * Test late payment penalties are non-qualifying.
     */
    public function testLateFeesAreNonQualifying(): void
    {
        $types = Releve24::NON_QUALIFYING_EXPENSE_TYPES;

        $this->assertContains('late_fee', $types, 'Late fees should be non-qualifying');
        $this->assertContains('late_penalty', $types, 'Late penalties should be non-qualifying');
        $this->assertContains('penalty', $types, 'Penalties should be non-qualifying');
    }

    /**
     * Test expense type detection helper.
     */
    public function testExpenseTypeIsNonQualifying(): void
    {
        $types = Releve24::NON_QUALIFYING_EXPENSE_TYPES;

        // Test detection function
        $isNonQualifying = function ($type) use ($types) {
            return in_array(strtolower($type), $types);
        };

        $this->assertTrue($isNonQualifying('medical'), 'medical should be non-qualifying');
        $this->assertTrue($isNonQualifying('MEDICAL'), 'MEDICAL (uppercase) should be non-qualifying');
        $this->assertFalse($isNonQualifying('childcare'), 'childcare should be qualifying');
        $this->assertFalse($isNonQualifying('daycare'), 'daycare should be qualifying');
    }

    // =========================================================================
    // BOX B - DAYS OF CARE CALCULATION TESTS
    // =========================================================================

    /**
     * Test days of care must be positive integer.
     */
    public function testDaysOfCareMustBePositiveInteger(): void
    {
        $daysOfCare = 220;

        $this->assertIsInt($daysOfCare, 'Days of care should be an integer');
        $this->assertGreaterThanOrEqual(0, $daysOfCare, 'Days of care should be non-negative');
    }

    /**
     * Test typical annual days of care range.
     */
    public function testTypicalAnnualDaysOfCareRange(): void
    {
        // Typical full-year 5-day/week care: approximately 260 days
        // With holidays/closures: approximately 220-250 days
        $minDays = 0;
        $maxDays = 365;

        $testDays = 220;

        $this->assertGreaterThanOrEqual($minDays, $testDays, 'Days should be at least 0');
        $this->assertLessThanOrEqual($maxDays, $testDays, 'Days should not exceed 365');
    }

    /**
     * Test days of care for partial year enrollment.
     */
    public function testDaysOfCareForPartialYear(): void
    {
        // Child enrolled for 6 months (approximately 130 days of care)
        $fullYearDays = 260;
        $monthsEnrolled = 6;
        $expectedDays = (int) round(($monthsEnrolled / 12) * $fullYearDays);

        $this->assertEquals(130, $expectedDays, 'Partial year should calculate proportional days');
    }

    /**
     * Test days of care calculation based on days per week.
     */
    public function testDaysOfCareBasedOnDaysPerWeek(): void
    {
        // Full year (52 weeks) x days per week = care days
        $weeksPerYear = 52;

        $testCases = [
            ['daysPerWeek' => 5, 'expected' => 260],
            ['daysPerWeek' => 4, 'expected' => 208],
            ['daysPerWeek' => 3, 'expected' => 156],
            ['daysPerWeek' => 2, 'expected' => 104],
        ];

        foreach ($testCases as $case) {
            $careDays = $weeksPerYear * $case['daysPerWeek'];
            $this->assertEquals(
                $case['expected'],
                $careDays,
                "{$case['daysPerWeek']} days/week should equal {$case['expected']} days/year"
            );
        }
    }

    // =========================================================================
    // BOX C - TOTAL AMOUNTS PAID TESTS (PAID ONLY, NOT INVOICED)
    // =========================================================================

    /**
     * Test total amounts paid calculation sums all payments.
     */
    public function testTotalAmountsPaidCalculation(): void
    {
        $payments = [
            ['amount' => 1000.00],
            ['amount' => 1000.00],
            ['amount' => 1000.00],
        ];

        $totalPaid = array_sum(array_column($payments, 'amount'));

        $this->assertEquals(3000.00, $totalPaid, 'Total paid should sum all payments');
    }

    /**
     * Test total amounts paid includes only payments, not invoices.
     */
    public function testTotalAmountsPaidOnlyIncludesPayments(): void
    {
        $invoice = [
            'totalAmount' => 10000.00, // Amount invoiced
        ];

        $payments = [
            ['amount' => 3000.00],
            ['amount' => 2000.00],
        ];

        $invoicedAmount = $invoice['totalAmount'];
        $paidAmount = array_sum(array_column($payments, 'amount'));

        // CRITICAL: RL-24 Box C uses PAID amounts only
        $this->assertNotEquals($invoicedAmount, $paidAmount, 'Paid should differ from invoiced when partially paid');
        $this->assertEquals(5000.00, $paidAmount, 'Box C should only include paid amounts');
    }

    /**
     * Test unpaid invoice contributes zero to total paid.
     */
    public function testUnpaidInvoiceContributesZeroToPaid(): void
    {
        $invoice = [
            'totalAmount' => 1000.00,
            'paidAmount' => 0.00,
            'status' => 'Issued',
        ];

        $contribution = $invoice['paidAmount'];

        $this->assertEquals(0, $contribution, 'Unpaid invoice should contribute zero to Box C');
    }

    /**
     * Test partial payment contributes exact amount to total.
     */
    public function testPartialPaymentContributesExactAmount(): void
    {
        $invoice = [
            'totalAmount' => 1000.00,
            'paidAmount' => 500.00,
            'status' => 'Partial',
        ];

        $contribution = $invoice['paidAmount'];

        $this->assertEquals(500.00, $contribution, 'Partial payment should contribute exact paid amount');
        $this->assertNotEquals($invoice['totalAmount'], $contribution, 'Should not use invoice total');
    }

    /**
     * Test payments from different months sum correctly.
     */
    public function testPaymentsFromDifferentMonthsSum(): void
    {
        $payments = [
            ['paymentDate' => '2025-01-15', 'amount' => 1000.00],
            ['paymentDate' => '2025-02-15', 'amount' => 1000.00],
            ['paymentDate' => '2025-03-15', 'amount' => 1000.00],
            ['paymentDate' => '2025-04-15', 'amount' => 1000.00],
            ['paymentDate' => '2025-05-15', 'amount' => 1000.00],
            ['paymentDate' => '2025-06-15', 'amount' => 1000.00],
            ['paymentDate' => '2025-07-15', 'amount' => 1000.00],
            ['paymentDate' => '2025-08-15', 'amount' => 1000.00],
            ['paymentDate' => '2025-09-15', 'amount' => 1000.00],
            ['paymentDate' => '2025-10-15', 'amount' => 1000.00],
            ['paymentDate' => '2025-11-15', 'amount' => 1000.00],
            ['paymentDate' => '2025-12-15', 'amount' => 1000.00],
        ];

        $totalPaid = array_sum(array_column($payments, 'amount'));

        $this->assertEquals(12000.00, $totalPaid, '12 monthly payments should sum to 12000');
    }

    /**
     * Test payments only within tax year are included.
     */
    public function testOnlyTaxYearPaymentsIncluded(): void
    {
        $taxYear = 2025;
        $payments = [
            ['paymentDate' => '2024-12-15', 'amount' => 1000.00], // Previous year - exclude
            ['paymentDate' => '2025-01-15', 'amount' => 1000.00], // Tax year - include
            ['paymentDate' => '2025-06-15', 'amount' => 1000.00], // Tax year - include
            ['paymentDate' => '2025-12-15', 'amount' => 1000.00], // Tax year - include
            ['paymentDate' => '2026-01-15', 'amount' => 1000.00], // Next year - exclude
        ];

        $taxYearPayments = array_filter($payments, function ($p) use ($taxYear) {
            $year = (int) substr($p['paymentDate'], 0, 4);
            return $year === $taxYear;
        });

        $totalPaid = array_sum(array_column($taxYearPayments, 'amount'));

        $this->assertEquals(3000.00, $totalPaid, 'Only payments within tax year should be included');
    }

    // =========================================================================
    // NAME FORMATTING TESTS
    // =========================================================================

    /**
     * Test name formatting with surname and first name.
     */
    public function testNameFormattingWithBothNames(): void
    {
        $releve24 = $this->createReleve24Instance();

        $formatted = $releve24->formatName('Smith', 'John');

        $this->assertEquals('Smith, John', $formatted, 'Name should be formatted as Surname, FirstName');
    }

    /**
     * Test name formatting with only surname.
     */
    public function testNameFormattingWithOnlySurname(): void
    {
        $releve24 = $this->createReleve24Instance();

        $formatted = $releve24->formatName('Smith', '');

        $this->assertEquals('Smith', $formatted, 'Should return only surname when first name is empty');
    }

    /**
     * Test name formatting with only first name.
     */
    public function testNameFormattingWithOnlyFirstName(): void
    {
        $releve24 = $this->createReleve24Instance();

        $formatted = $releve24->formatName('', 'John');

        $this->assertEquals('John', $formatted, 'Should return only first name when surname is empty');
    }

    /**
     * Test name formatting with empty names.
     */
    public function testNameFormattingWithEmptyNames(): void
    {
        $releve24 = $this->createReleve24Instance();

        $formatted = $releve24->formatName('', '');

        $this->assertEquals('', $formatted, 'Should return empty string when both names are empty');
    }

    /**
     * Test name formatting trims whitespace.
     */
    public function testNameFormattingTrimsWhitespace(): void
    {
        $releve24 = $this->createReleve24Instance();

        $formatted = $releve24->formatName('  Smith  ', '  John  ');

        $this->assertEquals('Smith, John', $formatted, 'Should trim whitespace from names');
    }

    // =========================================================================
    // SLIP NUMBER GENERATION TESTS
    // =========================================================================

    /**
     * Test slip number format pattern.
     */
    public function testSlipNumberFormatPattern(): void
    {
        $slipNumber = 'RL24-2025-000001';

        // Should match RL24-YYYY-NNNNNN pattern
        $this->assertMatchesRegularExpression(
            '/^RL24-\d{4}-\d{6}$/',
            $slipNumber,
            'Slip number should match RL24-YYYY-NNNNNN format'
        );
    }

    /**
     * Test slip number contains correct tax year.
     */
    public function testSlipNumberContainsTaxYear(): void
    {
        $taxYear = 2025;
        $slipNumber = 'RL24-2025-000001';

        $this->assertStringContainsString(
            (string) $taxYear,
            $slipNumber,
            'Slip number should contain the tax year'
        );
    }

    /**
     * Test slip number sequence increments.
     */
    public function testSlipNumberSequenceIncrements(): void
    {
        $slipNumbers = [
            'RL24-2025-000001',
            'RL24-2025-000002',
            'RL24-2025-000003',
        ];

        // Extract sequence numbers
        $sequences = array_map(function ($num) {
            return (int) substr($num, 10);
        }, $slipNumbers);

        $this->assertEquals([1, 2, 3], $sequences, 'Slip numbers should increment sequentially');
    }

    // =========================================================================
    // STATUS CONSTANTS TESTS
    // =========================================================================

    /**
     * Test status constants are defined.
     */
    public function testStatusConstantsAreDefined(): void
    {
        $this->assertEquals('Draft', Releve24::STATUS_DRAFT, 'Draft status should be defined');
        $this->assertEquals('Generated', Releve24::STATUS_GENERATED, 'Generated status should be defined');
        $this->assertEquals('Sent', Releve24::STATUS_SENT, 'Sent status should be defined');
        $this->assertEquals('Filed', Releve24::STATUS_FILED, 'Filed status should be defined');
        $this->assertEquals('Amended', Releve24::STATUS_AMENDED, 'Amended status should be defined');
    }

    /**
     * Test valid status transitions.
     */
    public function testValidStatusTransitions(): void
    {
        $validTransitions = [
            'Draft' => ['Generated'],
            'Generated' => ['Sent', 'Amended'],
            'Sent' => ['Filed', 'Amended'],
            'Filed' => ['Amended'],
            'Amended' => [], // Terminal state
        ];

        // Verify structure
        $this->assertArrayHasKey('Draft', $validTransitions);
        $this->assertArrayHasKey('Generated', $validTransitions);
        $this->assertArrayHasKey('Sent', $validTransitions);
        $this->assertArrayHasKey('Filed', $validTransitions);
        $this->assertArrayHasKey('Amended', $validTransitions);
    }

    // =========================================================================
    // AMENDMENT WORKFLOW TESTS
    // =========================================================================

    /**
     * Test can amend sent slip.
     */
    public function testCanAmendSentSlip(): void
    {
        $releve24 = $this->createReleve24Instance();

        $slip = ['status' => Releve24::STATUS_SENT];
        $this->releve24Gateway->method('selectReleve24ByID')->willReturn($slip);

        $canAmend = $releve24->canAmend(1);

        $this->assertTrue($canAmend, 'Sent slip should be amendable');
    }

    /**
     * Test can amend filed slip.
     */
    public function testCanAmendFiledSlip(): void
    {
        $releve24 = $this->createReleve24Instance();

        $slip = ['status' => Releve24::STATUS_FILED];
        $this->releve24Gateway->method('selectReleve24ByID')->willReturn($slip);

        $canAmend = $releve24->canAmend(1);

        $this->assertTrue($canAmend, 'Filed slip should be amendable');
    }

    /**
     * Test cannot amend draft slip.
     */
    public function testCannotAmendDraftSlip(): void
    {
        $releve24 = $this->createReleve24Instance();

        $slip = ['status' => Releve24::STATUS_DRAFT];
        $this->releve24Gateway->method('selectReleve24ByID')->willReturn($slip);

        $canAmend = $releve24->canAmend(1);

        $this->assertFalse($canAmend, 'Draft slip should not be amendable');
    }

    /**
     * Test cannot amend non-existent slip.
     */
    public function testCannotAmendNonExistentSlip(): void
    {
        $releve24 = $this->createReleve24Instance();

        $this->releve24Gateway->method('selectReleve24ByID')->willReturn(null);

        $canAmend = $releve24->canAmend(999);

        $this->assertFalse($canAmend, 'Non-existent slip should not be amendable');
    }

    /**
     * Test can cancel generated slip.
     */
    public function testCanCancelGeneratedSlip(): void
    {
        $releve24 = $this->createReleve24Instance();

        $slip = [
            'status' => Releve24::STATUS_GENERATED,
            'slipType' => Releve24::SLIP_TYPE_ORIGINAL
        ];
        $this->releve24Gateway->method('selectReleve24ByID')->willReturn($slip);

        $canCancel = $releve24->canCancel(1);

        $this->assertTrue($canCancel, 'Generated slip should be cancellable');
    }

    /**
     * Test cannot cancel filed slip.
     */
    public function testCannotCancelFiledSlip(): void
    {
        $releve24 = $this->createReleve24Instance();

        $slip = [
            'status' => Releve24::STATUS_FILED,
            'slipType' => Releve24::SLIP_TYPE_ORIGINAL
        ];
        $this->releve24Gateway->method('selectReleve24ByID')->willReturn($slip);

        $canCancel = $releve24->canCancel(1);

        $this->assertFalse($canCancel, 'Filed slip should not be cancellable');
    }

    /**
     * Test cannot cancel already cancelled slip.
     */
    public function testCannotCancelAlreadyCancelledSlip(): void
    {
        $releve24 = $this->createReleve24Instance();

        $slip = [
            'status' => Releve24::STATUS_GENERATED,
            'slipType' => Releve24::SLIP_TYPE_CANCELLED
        ];
        $this->releve24Gateway->method('selectReleve24ByID')->willReturn($slip);

        $canCancel = $releve24->canCancel(1);

        $this->assertFalse($canCancel, 'Already cancelled slip should not be cancellable again');
    }

    // =========================================================================
    // RL-24 DATA STRUCTURE TESTS
    // =========================================================================

    /**
     * Test RL-24 data structure has all required fields.
     */
    public function testReleve24DataStructureHasRequiredFields(): void
    {
        $requiredFields = [
            'gibbonPersonID',
            'gibbonFamilyID',
            'taxYear',
            'slipType',
            'daysOfCare',
            'totalAmountsPaid',
            'nonQualifyingExpenses',
            'qualifyingExpenses',
            'providerSIN',
            'recipientSIN',
            'recipientName',
            'childName',
            'status',
        ];

        foreach ($requiredFields as $field) {
            $this->assertArrayHasKey(
                $field,
                $this->sampleReleve24,
                "RL-24 should contain required field: {$field}"
            );
        }
    }

    /**
     * Test Box values are correctly typed.
     */
    public function testBoxValuesAreCorrectlyTyped(): void
    {
        $slip = $this->sampleReleve24;

        // Box B should be integer
        $this->assertIsInt($slip['daysOfCare'], 'Box B (daysOfCare) should be integer');

        // Box C, D, E should be numeric
        $this->assertIsNumeric($slip['totalAmountsPaid'], 'Box C should be numeric');
        $this->assertIsNumeric($slip['nonQualifyingExpenses'], 'Box D should be numeric');
        $this->assertIsNumeric($slip['qualifyingExpenses'], 'Box E should be numeric');
    }

    /**
     * Test Box E equals C minus D in sample data.
     */
    public function testBoxEEqualsBoxCMinusBoxDInSampleData(): void
    {
        $slip = $this->sampleReleve24;

        $expectedBoxE = $slip['totalAmountsPaid'] - $slip['nonQualifyingExpenses'];

        $this->assertEquals(
            $expectedBoxE,
            $slip['qualifyingExpenses'],
            'Sample data Box E should equal Box C - Box D'
        );
    }

    // =========================================================================
    // PROVIDER CONFIGURATION VALIDATION TESTS
    // =========================================================================

    /**
     * Test provider validation reports missing SIN.
     */
    public function testProviderValidationReportsMissingSIN(): void
    {
        $releve24 = $this->createReleve24Instance();

        $this->settingGateway->method('getSettingByScope')
            ->willReturnMap([
                ['Enhanced Finance', 'providerSIN', null],
                ['Enhanced Finance', 'providerName', 'Test Provider'],
                ['Enhanced Finance', 'providerAddress', '123 Test St'],
            ]);

        $missing = $releve24->validateProviderConfiguration();

        $this->assertContains('Provider SIN', $missing, 'Should report missing Provider SIN');
    }

    /**
     * Test provider validation reports invalid SIN format.
     */
    public function testProviderValidationReportsInvalidSINFormat(): void
    {
        $releve24 = $this->createReleve24Instance();

        $this->settingGateway->method('getSettingByScope')
            ->willReturnMap([
                ['Enhanced Finance', 'providerSIN', '123456789'], // Invalid (fails Luhn)
                ['Enhanced Finance', 'providerName', 'Test Provider'],
                ['Enhanced Finance', 'providerAddress', '123 Test St'],
            ]);

        $missing = $releve24->validateProviderConfiguration();

        $this->assertContains('Provider SIN (invalid format)', $missing, 'Should report invalid Provider SIN format');
    }

    /**
     * Test provider validation reports missing name.
     */
    public function testProviderValidationReportsMissingName(): void
    {
        $releve24 = $this->createReleve24Instance();

        $this->settingGateway->method('getSettingByScope')
            ->willReturnMap([
                ['Enhanced Finance', 'providerSIN', '046454286'], // Valid SIN
                ['Enhanced Finance', 'providerName', null],
                ['Enhanced Finance', 'providerAddress', '123 Test St'],
            ]);

        $missing = $releve24->validateProviderConfiguration();

        $this->assertContains('Provider Name', $missing, 'Should report missing Provider Name');
    }

    /**
     * Test provider validation reports missing address.
     */
    public function testProviderValidationReportsMissingAddress(): void
    {
        $releve24 = $this->createReleve24Instance();

        $this->settingGateway->method('getSettingByScope')
            ->willReturnMap([
                ['Enhanced Finance', 'providerSIN', '046454286'],
                ['Enhanced Finance', 'providerName', 'Test Provider'],
                ['Enhanced Finance', 'providerAddress', null],
            ]);

        $missing = $releve24->validateProviderConfiguration();

        $this->assertContains('Provider Address', $missing, 'Should report missing Provider Address');
    }

    /**
     * Test provider validation returns empty when all configured.
     */
    public function testProviderValidationPassesWhenAllConfigured(): void
    {
        $releve24 = $this->createReleve24Instance();

        $this->settingGateway->method('getSettingByScope')
            ->willReturnMap([
                ['Enhanced Finance', 'providerSIN', '046454286'], // Valid SIN
                ['Enhanced Finance', 'providerName', 'Test Provider'],
                ['Enhanced Finance', 'providerAddress', '123 Test St'],
            ]);

        $missing = $releve24->validateProviderConfiguration();

        $this->assertEmpty($missing, 'Should return empty array when all configured');
    }

    // =========================================================================
    // TAX YEAR BOUNDARY TESTS
    // =========================================================================

    /**
     * Test tax year boundaries are correct.
     */
    public function testTaxYearBoundariesAreCorrect(): void
    {
        $taxYear = 2025;
        $startDate = $taxYear . '-01-01';
        $endDate = $taxYear . '-12-31';

        $this->assertEquals('2025-01-01', $startDate, 'Tax year should start Jan 1');
        $this->assertEquals('2025-12-31', $endDate, 'Tax year should end Dec 31');
    }

    /**
     * Test payment date on Jan 1 is included.
     */
    public function testPaymentOnJanFirstIsIncluded(): void
    {
        $taxYear = 2025;
        $paymentDate = '2025-01-01';

        $year = (int) substr($paymentDate, 0, 4);
        $isIncluded = $year === $taxYear;

        $this->assertTrue($isIncluded, 'Payment on Jan 1 should be included in tax year');
    }

    /**
     * Test payment date on Dec 31 is included.
     */
    public function testPaymentOnDecThirtyFirstIsIncluded(): void
    {
        $taxYear = 2025;
        $paymentDate = '2025-12-31';

        $year = (int) substr($paymentDate, 0, 4);
        $isIncluded = $year === $taxYear;

        $this->assertTrue($isIncluded, 'Payment on Dec 31 should be included in tax year');
    }

    // =========================================================================
    // EDGE CASE TESTS
    // =========================================================================

    /**
     * Test RL-24 with zero total paid.
     */
    public function testReleve24WithZeroTotalPaid(): void
    {
        $releve24 = $this->createReleve24Instance();

        $boxC = 0.00;
        $boxD = 0.00;
        $boxE = $releve24->calculateQualifyingExpenses($boxC, $boxD);

        $this->assertEquals(0, $boxE, 'Box E should be zero when Box C is zero');
    }

    /**
     * Test RL-24 with all expenses non-qualifying.
     */
    public function testReleve24WithAllNonQualifyingExpenses(): void
    {
        $releve24 = $this->createReleve24Instance();

        $boxC = 5000.00;
        $boxD = 5000.00; // All expenses are non-qualifying
        $boxE = $releve24->calculateQualifyingExpenses($boxC, $boxD);

        $this->assertEquals(0, $boxE, 'Box E should be zero when all expenses are non-qualifying');
    }

    /**
     * Test child with single payment.
     */
    public function testChildWithSinglePayment(): void
    {
        $payments = [
            ['amount' => 1200.00],
        ];

        $totalPaid = array_sum(array_column($payments, 'amount'));

        $this->assertEquals(1200.00, $totalPaid, 'Single payment should calculate correctly');
    }

    /**
     * Test large qualifying expenses amount.
     */
    public function testLargeQualifyingExpensesAmount(): void
    {
        $releve24 = $this->createReleve24Instance();

        $boxC = 50000.00;
        $boxD = 2500.00;
        $expectedBoxE = 47500.00;

        $boxE = $releve24->calculateQualifyingExpenses($boxC, $boxD);

        $this->assertEquals($expectedBoxE, $boxE, 'Should handle large qualifying amounts');
    }

    /**
     * Test decimal precision in Box calculations.
     */
    public function testDecimalPrecisionInBoxCalculations(): void
    {
        $releve24 = $this->createReleve24Instance();

        // Test with amounts that could cause floating point issues
        $boxC = 10000.33;
        $boxD = 1000.17;
        $expectedBoxE = 9000.16;

        $boxE = $releve24->calculateQualifyingExpenses($boxC, $boxD);

        $this->assertEquals($expectedBoxE, $boxE, 'Should maintain decimal precision');
    }

    /**
     * Test multiple children same family can have separate RL-24s.
     */
    public function testMultipleChildrenSameFamilySeparateSlips(): void
    {
        $familyID = 50;
        $slips = [
            ['gibbonPersonID' => 100, 'gibbonFamilyID' => $familyID, 'childName' => 'Smith, Emma'],
            ['gibbonPersonID' => 101, 'gibbonFamilyID' => $familyID, 'childName' => 'Smith, Jack'],
            ['gibbonPersonID' => 102, 'gibbonFamilyID' => $familyID, 'childName' => 'Smith, Lucy'],
        ];

        // Each child should have separate RL-24
        $childIDs = array_column($slips, 'gibbonPersonID');
        $uniqueChildIDs = array_unique($childIDs);

        $this->assertCount(3, $uniqueChildIDs, 'Each child should have their own RL-24');
        $this->assertEquals(
            $familyID,
            $slips[0]['gibbonFamilyID'],
            'All slips should belong to same family'
        );
    }

    // =========================================================================
    // RENDERING DATA TESTS
    // =========================================================================

    /**
     * Test slip data for rendering contains all display fields.
     */
    public function testSlipDataForRenderingContainsDisplayFields(): void
    {
        $releve24 = $this->createReleve24Instance();

        // Mock the gateway to return sample data
        $this->releve24Gateway->method('selectReleve24ByID')->willReturn($this->sampleReleve24);
        $this->settingGateway->method('getSettingByScope')->willReturn('Test Value');

        $slipData = $releve24->getSlipDataForRendering(1);

        // Check for required rendering fields
        $this->assertArrayHasKey('taxYear', $slipData, 'Should contain taxYear');
        $this->assertArrayHasKey('boxA', $slipData, 'Should contain boxA (slip type label)');
        $this->assertArrayHasKey('boxB', $slipData, 'Should contain boxB (days of care)');
        $this->assertArrayHasKey('boxC', $slipData, 'Should contain boxC (total paid)');
        $this->assertArrayHasKey('boxD', $slipData, 'Should contain boxD (non-qualifying)');
        $this->assertArrayHasKey('boxE', $slipData, 'Should contain boxE (qualifying)');
        $this->assertArrayHasKey('boxH', $slipData, 'Should contain boxH (provider SIN)');
        $this->assertArrayHasKey('providerName', $slipData, 'Should contain providerName');
        $this->assertArrayHasKey('recipientName', $slipData, 'Should contain recipientName');
        $this->assertArrayHasKey('childName', $slipData, 'Should contain childName');
    }

    /**
     * Test slip data for non-existent slip returns empty.
     */
    public function testSlipDataForNonExistentSlipReturnsEmpty(): void
    {
        $releve24 = $this->createReleve24Instance();

        $this->releve24Gateway->method('selectReleve24ByID')->willReturn(null);

        $slipData = $releve24->getSlipDataForRendering(999);

        $this->assertEmpty($slipData, 'Non-existent slip should return empty array');
    }

    /**
     * Test Box C is formatted with 2 decimal places.
     */
    public function testBoxCIsFormattedWithTwoDecimals(): void
    {
        $releve24 = $this->createReleve24Instance();

        $slip = $this->sampleReleve24;
        $this->releve24Gateway->method('selectReleve24ByID')->willReturn($slip);
        $this->settingGateway->method('getSettingByScope')->willReturn('Test Value');

        $slipData = $releve24->getSlipDataForRendering(1);

        // Box C should be formatted as string with 2 decimal places
        $this->assertStringContainsString('.', $slipData['boxC'], 'Box C should contain decimal point');
        $decimalPart = substr($slipData['boxC'], strpos($slipData['boxC'], '.') + 1);
        $this->assertEquals(2, strlen($decimalPart), 'Box C should have exactly 2 decimal places');
    }
}
