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
use Gibbon\Module\EnhancedFinance\Service\Releve24Service;
use Gibbon\Module\EnhancedFinance\Domain\InvoiceGateway;
use Gibbon\Module\EnhancedFinance\Domain\PaymentGateway;
use Gibbon\Module\EnhancedFinance\Domain\Releve24Gateway;
use Gibbon\Contracts\Database\Connection;
use Gibbon\Domain\System\SettingGateway;

/**
 * Unit tests for Releve24Service.
 *
 * Tests Quebec RL-24 tax slip generation, SIN validation and formatting,
 * expense calculations, slip number generation, and slip type determination.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class Releve24ServiceTest extends TestCase
{
    /**
     * @var Releve24Service
     */
    protected $service;

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
     * Set up test fixtures.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create mocks
        $this->db = $this->createMock(Connection::class);
        $this->settingGateway = $this->createMock(SettingGateway::class);
        $this->invoiceGateway = $this->createMock(InvoiceGateway::class);
        $this->paymentGateway = $this->createMock(PaymentGateway::class);
        $this->releve24Gateway = $this->createMock(Releve24Gateway::class);

        // Configure default settings
        $this->settingGateway->method('getSettingByScope')
            ->willReturnCallback(function ($scope, $name) {
                $settings = [
                    'providerSIN' => '123456782',
                    'providerName' => 'Test Daycare Inc.',
                    'providerAddress' => '123 Main St, Montreal, QC',
                    'providerNEQ' => '1234567890',
                ];
                return $settings[$name] ?? null;
            });

        // Create service
        $this->service = new Releve24Service(
            $this->db,
            $this->settingGateway,
            $this->invoiceGateway,
            $this->paymentGateway,
            $this->releve24Gateway
        );
    }

    /**
     * Clean up after tests.
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        $this->service = null;
        $this->db = null;
        $this->settingGateway = null;
        $this->invoiceGateway = null;
        $this->paymentGateway = null;
        $this->releve24Gateway = null;
    }

    // =========================================================================
    // SIN FORMATTING TESTS
    // =========================================================================

    /**
     * Test formatting SIN with digits only.
     */
    public function testFormatSINWithDigitsOnly(): void
    {
        $sin = '123456782';
        $formatted = $this->service->formatSIN($sin);

        $this->assertEquals('123-456-782', $formatted);
    }

    /**
     * Test formatting SIN already formatted.
     */
    public function testFormatSINAlreadyFormatted(): void
    {
        $sin = '123-456-782';
        $formatted = $this->service->formatSIN($sin);

        $this->assertEquals('123-456-782', $formatted);
    }

    /**
     * Test formatting SIN with spaces.
     */
    public function testFormatSINWithSpaces(): void
    {
        $sin = '123 456 782';
        $formatted = $this->service->formatSIN($sin);

        $this->assertEquals('123-456-782', $formatted);
    }

    /**
     * Test formatting invalid SIN (too short).
     */
    public function testFormatSINTooShort(): void
    {
        $sin = '12345678';
        $formatted = $this->service->formatSIN($sin);

        $this->assertEquals('', $formatted);
    }

    /**
     * Test formatting invalid SIN (too long).
     */
    public function testFormatSINTooLong(): void
    {
        $sin = '1234567890';
        $formatted = $this->service->formatSIN($sin);

        $this->assertEquals('', $formatted);
    }

    /**
     * Test formatting empty SIN.
     */
    public function testFormatEmptySIN(): void
    {
        $sin = '';
        $formatted = $this->service->formatSIN($sin);

        $this->assertEquals('', $formatted);
    }

    // =========================================================================
    // SIN VALIDATION TESTS (Luhn Algorithm)
    // =========================================================================

    /**
     * Test validating valid SIN using Luhn algorithm.
     */
    public function testValidateSINWithValidSIN(): void
    {
        // Valid test SIN: 046 454 286 (from CRA test SINs)
        $sin = '046454286';
        $isValid = $this->service->validateSIN($sin);

        $this->assertTrue($isValid);
    }

    /**
     * Test validating valid SIN with dashes.
     */
    public function testValidateSINWithValidSINAndDashes(): void
    {
        $sin = '046-454-286';
        $isValid = $this->service->validateSIN($sin);

        $this->assertTrue($isValid);
    }

    /**
     * Test validating invalid SIN (wrong checksum).
     */
    public function testValidateSINWithInvalidChecksum(): void
    {
        $sin = '123456789';
        $isValid = $this->service->validateSIN($sin);

        $this->assertFalse($isValid);
    }

    /**
     * Test validating SIN with too few digits.
     */
    public function testValidateSINWithTooFewDigits(): void
    {
        $sin = '12345678';
        $isValid = $this->service->validateSIN($sin);

        $this->assertFalse($isValid);
    }

    /**
     * Test validating SIN with too many digits.
     */
    public function testValidateSINWithTooManyDigits(): void
    {
        $sin = '1234567890';
        $isValid = $this->service->validateSIN($sin);

        $this->assertFalse($isValid);
    }

    /**
     * Test validating empty SIN.
     */
    public function testValidateEmptySIN(): void
    {
        $sin = '';
        $isValid = $this->service->validateSIN($sin);

        $this->assertFalse($isValid);
    }

    // =========================================================================
    // NAME FORMATTING TESTS
    // =========================================================================

    /**
     * Test formatting name with both surname and first name.
     */
    public function testFormatNameWithBothNames(): void
    {
        $formatted = $this->service->formatName('Smith', 'John');
        $this->assertEquals('Smith, John', $formatted);
    }

    /**
     * Test formatting name with surname only.
     */
    public function testFormatNameWithSurnameOnly(): void
    {
        $formatted = $this->service->formatName('Smith', '');
        $this->assertEquals('Smith', $formatted);
    }

    /**
     * Test formatting name with first name only.
     */
    public function testFormatNameWithFirstNameOnly(): void
    {
        $formatted = $this->service->formatName('', 'John');
        $this->assertEquals('John', $formatted);
    }

    /**
     * Test formatting name with empty values.
     */
    public function testFormatNameWithEmptyValues(): void
    {
        $formatted = $this->service->formatName('', '');
        $this->assertEquals('', $formatted);
    }

    /**
     * Test formatting name with whitespace.
     */
    public function testFormatNameWithWhitespace(): void
    {
        $formatted = $this->service->formatName('  Smith  ', '  John  ');
        $this->assertEquals('Smith, John', $formatted);
    }

    // =========================================================================
    // QUALIFYING EXPENSES CALCULATION TESTS
    // =========================================================================

    /**
     * Test calculating qualifying expenses.
     */
    public function testCalculateQualifyingExpenses(): void
    {
        $totalPaid = 5000.00;
        $nonQualifying = 500.00;
        $qualifying = $this->service->calculateQualifyingExpenses($totalPaid, $nonQualifying);

        $this->assertEquals(4500.00, $qualifying);
    }

    /**
     * Test calculating qualifying expenses when all expenses qualify.
     */
    public function testCalculateQualifyingExpensesAllQualify(): void
    {
        $totalPaid = 5000.00;
        $nonQualifying = 0.00;
        $qualifying = $this->service->calculateQualifyingExpenses($totalPaid, $nonQualifying);

        $this->assertEquals(5000.00, $qualifying);
    }

    /**
     * Test calculating qualifying expenses when all non-qualifying.
     */
    public function testCalculateQualifyingExpensesAllNonQualifying(): void
    {
        $totalPaid = 5000.00;
        $nonQualifying = 5000.00;
        $qualifying = $this->service->calculateQualifyingExpenses($totalPaid, $nonQualifying);

        $this->assertEquals(0.00, $qualifying);
    }

    /**
     * Test qualifying expenses cannot be negative.
     */
    public function testCalculateQualifyingExpensesCannotBeNegative(): void
    {
        $totalPaid = 5000.00;
        $nonQualifying = 6000.00;
        $qualifying = $this->service->calculateQualifyingExpenses($totalPaid, $nonQualifying);

        $this->assertEquals(0.00, $qualifying);
    }

    // =========================================================================
    // TOTAL AMOUNTS PAID CALCULATION TESTS
    // =========================================================================

    /**
     * Test calculating total amounts paid.
     */
    public function testCalculateTotalAmountsPaid(): void
    {
        $paymentData = ['totalPaid' => '3500.00'];

        $this->paymentGateway->expects($this->once())
            ->method('selectTotalPaidByChildAndTaxYear')
            ->with(100, 2025)
            ->willReturn($paymentData);

        $total = $this->service->calculateTotalAmountsPaid(100, 2025);

        $this->assertEquals(3500.00, $total);
    }

    /**
     * Test calculating total amounts paid with no payments.
     */
    public function testCalculateTotalAmountsPaidWithNoPayments(): void
    {
        $paymentData = [];

        $this->paymentGateway->expects($this->once())
            ->method('selectTotalPaidByChildAndTaxYear')
            ->with(100, 2025)
            ->willReturn($paymentData);

        $total = $this->service->calculateTotalAmountsPaid(100, 2025);

        $this->assertEquals(0.00, $total);
    }

    // =========================================================================
    // SLIP TYPE DETERMINATION TESTS
    // =========================================================================

    /**
     * Test determining slip type for original.
     */
    public function testDetermineSlipTypeForOriginal(): void
    {
        $this->releve24Gateway->expects($this->once())
            ->method('hasOriginalReleve24')
            ->with(100, 2025)
            ->willReturn(false);

        $slipType = $this->service->determineSlipType(100, 2025);

        $this->assertEquals(Releve24Service::SLIP_TYPE_ORIGINAL, $slipType);
    }

    /**
     * Test determining slip type for amendment.
     */
    public function testDetermineSlipTypeForAmendment(): void
    {
        $this->releve24Gateway->expects($this->once())
            ->method('hasOriginalReleve24')
            ->with(100, 2025)
            ->willReturn(true);

        $slipType = $this->service->determineSlipType(100, 2025);

        $this->assertEquals(Releve24Service::SLIP_TYPE_AMENDED, $slipType);
    }

    // =========================================================================
    // SLIP TYPE LABEL TESTS
    // =========================================================================

    /**
     * Test getting slip type label for original.
     */
    public function testGetSlipTypeLabelForOriginal(): void
    {
        $label = $this->service->getSlipTypeLabel(Releve24Service::SLIP_TYPE_ORIGINAL);
        $this->assertEquals('Original', $label);
    }

    /**
     * Test getting slip type label for amended.
     */
    public function testGetSlipTypeLabelForAmended(): void
    {
        $label = $this->service->getSlipTypeLabel(Releve24Service::SLIP_TYPE_AMENDED);
        $this->assertEquals('Amended', $label);
    }

    /**
     * Test getting slip type label for cancelled.
     */
    public function testGetSlipTypeLabelForCancelled(): void
    {
        $label = $this->service->getSlipTypeLabel(Releve24Service::SLIP_TYPE_CANCELLED);
        $this->assertEquals('Cancelled', $label);
    }

    /**
     * Test getting slip type label for unknown.
     */
    public function testGetSlipTypeLabelForUnknown(): void
    {
        $label = $this->service->getSlipTypeLabel('X');
        $this->assertEquals('Unknown', $label);
    }

    // =========================================================================
    // SLIP NUMBER GENERATION TESTS
    // =========================================================================

    /**
     * Test generating slip number for first slip.
     */
    public function testGenerateSlipNumberForFirstSlip(): void
    {
        $this->db->expects($this->once())
            ->method('selectOne')
            ->willReturn(['maxNum' => null]);

        $slipNumber = $this->service->generateSlipNumber(2025);

        $this->assertEquals('RL24-2025-000001', $slipNumber);
    }

    /**
     * Test generating slip number for subsequent slip.
     */
    public function testGenerateSlipNumberForSubsequentSlip(): void
    {
        $this->db->expects($this->once())
            ->method('selectOne')
            ->willReturn(['maxNum' => 5]);

        $slipNumber = $this->service->generateSlipNumber(2025);

        $this->assertEquals('RL24-2025-000006', $slipNumber);
    }

    /**
     * Test generating slip number with database error.
     */
    public function testGenerateSlipNumberWithDatabaseError(): void
    {
        $this->db->expects($this->once())
            ->method('selectOne')
            ->willThrowException(new \Exception('Database error'));

        $slipNumber = $this->service->generateSlipNumber(2025);

        $this->assertEquals('RL24-2025-000001', $slipNumber);
    }

    // =========================================================================
    // PROVIDER CONFIGURATION VALIDATION TESTS
    // =========================================================================

    /**
     * Test validating provider configuration with all fields.
     */
    public function testValidateProviderConfigurationWithAllFields(): void
    {
        $missing = $this->service->validateProviderConfiguration();

        $this->assertEmpty($missing);
    }

    /**
     * Test validating provider configuration with missing SIN.
     */
    public function testValidateProviderConfigurationWithMissingSIN(): void
    {
        $this->settingGateway = $this->createMock(SettingGateway::class);
        $this->settingGateway->method('getSettingByScope')
            ->willReturnCallback(function ($scope, $name) {
                if ($name === 'providerSIN') {
                    return null;
                }
                return 'Test Value';
            });

        $this->service = new Releve24Service(
            $this->db,
            $this->settingGateway,
            $this->invoiceGateway,
            $this->paymentGateway,
            $this->releve24Gateway
        );

        $missing = $this->service->validateProviderConfiguration();

        $this->assertContains('Provider SIN', $missing);
    }

    /**
     * Test validating provider configuration with invalid SIN format.
     */
    public function testValidateProviderConfigurationWithInvalidSIN(): void
    {
        $this->settingGateway = $this->createMock(SettingGateway::class);
        $this->settingGateway->method('getSettingByScope')
            ->willReturnCallback(function ($scope, $name) {
                if ($name === 'providerSIN') {
                    return '123456789'; // Invalid checksum
                }
                return 'Test Value';
            });

        $this->service = new Releve24Service(
            $this->db,
            $this->settingGateway,
            $this->invoiceGateway,
            $this->paymentGateway,
            $this->releve24Gateway
        );

        $missing = $this->service->validateProviderConfiguration();

        $this->assertContains('Provider SIN (invalid format)', $missing);
    }

    // =========================================================================
    // CAN AMEND TESTS
    // =========================================================================

    /**
     * Test can amend sent slip.
     */
    public function testCanAmendSentSlip(): void
    {
        $slip = [
            'gibbonEnhancedFinanceReleve24ID' => 1,
            'status' => Releve24Service::STATUS_SENT,
        ];

        $this->releve24Gateway->expects($this->once())
            ->method('selectReleve24ByID')
            ->with(1)
            ->willReturn($slip);

        $canAmend = $this->service->canAmend(1);

        $this->assertTrue($canAmend);
    }

    /**
     * Test can amend filed slip.
     */
    public function testCanAmendFiledSlip(): void
    {
        $slip = [
            'gibbonEnhancedFinanceReleve24ID' => 1,
            'status' => Releve24Service::STATUS_FILED,
        ];

        $this->releve24Gateway->expects($this->once())
            ->method('selectReleve24ByID')
            ->with(1)
            ->willReturn($slip);

        $canAmend = $this->service->canAmend(1);

        $this->assertTrue($canAmend);
    }

    /**
     * Test cannot amend draft slip.
     */
    public function testCannotAmendDraftSlip(): void
    {
        $slip = [
            'gibbonEnhancedFinanceReleve24ID' => 1,
            'status' => Releve24Service::STATUS_DRAFT,
        ];

        $this->releve24Gateway->expects($this->once())
            ->method('selectReleve24ByID')
            ->with(1)
            ->willReturn($slip);

        $canAmend = $this->service->canAmend(1);

        $this->assertFalse($canAmend);
    }

    /**
     * Test cannot amend non-existent slip.
     */
    public function testCannotAmendNonExistentSlip(): void
    {
        $this->releve24Gateway->expects($this->once())
            ->method('selectReleve24ByID')
            ->with(999)
            ->willReturn(null);

        $canAmend = $this->service->canAmend(999);

        $this->assertFalse($canAmend);
    }

    // =========================================================================
    // CAN CANCEL TESTS
    // =========================================================================

    /**
     * Test can cancel draft slip.
     */
    public function testCanCancelDraftSlip(): void
    {
        $slip = [
            'gibbonEnhancedFinanceReleve24ID' => 1,
            'slipType' => Releve24Service::SLIP_TYPE_ORIGINAL,
            'status' => Releve24Service::STATUS_DRAFT,
        ];

        $this->releve24Gateway->expects($this->once())
            ->method('selectReleve24ByID')
            ->with(1)
            ->willReturn($slip);

        $canCancel = $this->service->canCancel(1);

        $this->assertTrue($canCancel);
    }

    /**
     * Test cannot cancel filed slip.
     */
    public function testCannotCancelFiledSlip(): void
    {
        $slip = [
            'gibbonEnhancedFinanceReleve24ID' => 1,
            'slipType' => Releve24Service::SLIP_TYPE_ORIGINAL,
            'status' => Releve24Service::STATUS_FILED,
        ];

        $this->releve24Gateway->expects($this->once())
            ->method('selectReleve24ByID')
            ->with(1)
            ->willReturn($slip);

        $canCancel = $this->service->canCancel(1);

        $this->assertFalse($canCancel);
    }

    /**
     * Test cannot cancel already cancelled slip.
     */
    public function testCannotCancelAlreadyCancelledSlip(): void
    {
        $slip = [
            'gibbonEnhancedFinanceReleve24ID' => 1,
            'slipType' => Releve24Service::SLIP_TYPE_CANCELLED,
            'status' => Releve24Service::STATUS_DRAFT,
        ];

        $this->releve24Gateway->expects($this->once())
            ->method('selectReleve24ByID')
            ->with(1)
            ->willReturn($slip);

        $canCancel = $this->service->canCancel(1);

        $this->assertFalse($canCancel);
    }

    // =========================================================================
    // PROVIDER SIN TESTS
    // =========================================================================

    /**
     * Test getting provider SIN.
     */
    public function testGetProviderSIN(): void
    {
        $sin = $this->service->getProviderSIN();
        $this->assertEquals('123-456-782', $sin);
    }

    /**
     * Test getting provider SIN when not configured.
     */
    public function testGetProviderSINWhenNotConfigured(): void
    {
        $this->settingGateway = $this->createMock(SettingGateway::class);
        $this->settingGateway->method('getSettingByScope')
            ->willReturn(null);

        $this->service = new Releve24Service(
            $this->db,
            $this->settingGateway,
            $this->invoiceGateway,
            $this->paymentGateway,
            $this->releve24Gateway
        );

        $sin = $this->service->getProviderSIN();
        $this->assertEquals('', $sin);
    }

    // =========================================================================
    // NON-QUALIFYING EXPENSE TYPES TESTS
    // =========================================================================

    /**
     * Test non-qualifying expense types constant exists.
     */
    public function testNonQualifyingExpenseTypesConstant(): void
    {
        $this->assertIsArray(Releve24Service::NON_QUALIFYING_EXPENSE_TYPES);
        $this->assertContains('medical', Releve24Service::NON_QUALIFYING_EXPENSE_TYPES);
        $this->assertContains('transportation', Releve24Service::NON_QUALIFYING_EXPENSE_TYPES);
        $this->assertContains('teaching', Releve24Service::NON_QUALIFYING_EXPENSE_TYPES);
        $this->assertContains('fieldtrip', Releve24Service::NON_QUALIFYING_EXPENSE_TYPES);
        $this->assertContains('late_fee', Releve24Service::NON_QUALIFYING_EXPENSE_TYPES);
    }

    // =========================================================================
    // SLIP CONSTANTS TESTS
    // =========================================================================

    /**
     * Test slip type constants.
     */
    public function testSlipTypeConstants(): void
    {
        $this->assertEquals('R', Releve24Service::SLIP_TYPE_ORIGINAL);
        $this->assertEquals('A', Releve24Service::SLIP_TYPE_AMENDED);
        $this->assertEquals('D', Releve24Service::SLIP_TYPE_CANCELLED);
    }

    /**
     * Test status constants.
     */
    public function testStatusConstants(): void
    {
        $this->assertEquals('Draft', Releve24Service::STATUS_DRAFT);
        $this->assertEquals('Generated', Releve24Service::STATUS_GENERATED);
        $this->assertEquals('Sent', Releve24Service::STATUS_SENT);
        $this->assertEquals('Filed', Releve24Service::STATUS_FILED);
        $this->assertEquals('Amended', Releve24Service::STATUS_AMENDED);
    }
}
