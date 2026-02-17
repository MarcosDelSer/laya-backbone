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

use PHPUnit\Framework\TestCase;
use Gibbon\Module\System\Domain\FinanceSettingsStep;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Module\System\Domain\InstallationDetector;

/**
 * FinanceSettingsStepTest
 *
 * Unit tests for the FinanceSettingsStep class.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class FinanceSettingsStepTest extends TestCase
{
    protected $pdo;
    protected $settingGateway;
    protected $installationDetector;
    protected $financeSettingsStep;

    protected function setUp(): void
    {
        // Create in-memory SQLite database for testing
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create gibbonSetting table
        $this->pdo->exec("
            CREATE TABLE gibbonSetting (
                gibbonSettingID INTEGER PRIMARY KEY AUTOINCREMENT,
                scope VARCHAR(50),
                name VARCHAR(50),
                value TEXT,
                nameDisplay VARCHAR(100),
                description TEXT
            )
        ");

        // Create mock SettingGateway
        $this->settingGateway = $this->createMock(SettingGateway::class);

        // Create mock InstallationDetector
        $this->installationDetector = $this->createMock(InstallationDetector::class);

        // Create FinanceSettingsStep instance
        $this->financeSettingsStep = new FinanceSettingsStep(
            $this->settingGateway,
            $this->pdo,
            $this->installationDetector
        );
    }

    protected function tearDown(): void
    {
        $this->pdo = null;
    }

    /**
     * Test validation with valid finance settings
     */
    public function testValidateWithValidSettings()
    {
        $data = [
            'currency' => 'USD',
            'dailyRate' => 50.00,
            'paymentTerms' => 'net30',
        ];

        $errors = $this->financeSettingsStep->validate($data);

        $this->assertEmpty($errors, 'Valid finance settings should have no validation errors');
    }

    /**
     * Test validation with missing currency
     */
    public function testValidateWithMissingCurrency()
    {
        $data = [
            'dailyRate' => 50.00,
            'paymentTerms' => 'net30',
        ];

        $errors = $this->financeSettingsStep->validate($data);

        $this->assertArrayHasKey('currency', $errors);
        $this->assertEquals('Currency is required', $errors['currency']);
    }

    /**
     * Test validation with invalid currency
     */
    public function testValidateWithInvalidCurrency()
    {
        $data = [
            'currency' => 'XXX',
            'dailyRate' => 50.00,
            'paymentTerms' => 'net30',
        ];

        $errors = $this->financeSettingsStep->validate($data);

        $this->assertArrayHasKey('currency', $errors);
        $this->assertStringContainsString('Currency must be one of', $errors['currency']);
    }

    /**
     * Test validation with all supported currencies
     */
    public function testValidateWithAllSupportedCurrencies()
    {
        $currencies = ['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'JPY', 'CNY', 'INR'];

        foreach ($currencies as $currency) {
            $data = [
                'currency' => $currency,
                'dailyRate' => 50.00,
                'paymentTerms' => 'net30',
            ];

            $errors = $this->financeSettingsStep->validate($data);

            $this->assertArrayNotHasKey('currency', $errors, "Currency {$currency} should be valid");
        }
    }

    /**
     * Test validation with missing daily rate
     */
    public function testValidateWithMissingDailyRate()
    {
        $data = [
            'currency' => 'USD',
            'paymentTerms' => 'net30',
        ];

        $errors = $this->financeSettingsStep->validate($data);

        $this->assertArrayHasKey('dailyRate', $errors);
        $this->assertEquals('Daily rate is required', $errors['dailyRate']);
    }

    /**
     * Test validation with empty daily rate
     */
    public function testValidateWithEmptyDailyRate()
    {
        $data = [
            'currency' => 'USD',
            'dailyRate' => '',
            'paymentTerms' => 'net30',
        ];

        $errors = $this->financeSettingsStep->validate($data);

        $this->assertArrayHasKey('dailyRate', $errors);
        $this->assertEquals('Daily rate is required', $errors['dailyRate']);
    }

    /**
     * Test validation with non-numeric daily rate
     */
    public function testValidateWithNonNumericDailyRate()
    {
        $data = [
            'currency' => 'USD',
            'dailyRate' => 'abc',
            'paymentTerms' => 'net30',
        ];

        $errors = $this->financeSettingsStep->validate($data);

        $this->assertArrayHasKey('dailyRate', $errors);
        $this->assertEquals('Daily rate must be a number', $errors['dailyRate']);
    }

    /**
     * Test validation with negative daily rate
     */
    public function testValidateWithNegativeDailyRate()
    {
        $data = [
            'currency' => 'USD',
            'dailyRate' => -10,
            'paymentTerms' => 'net30',
        ];

        $errors = $this->financeSettingsStep->validate($data);

        $this->assertArrayHasKey('dailyRate', $errors);
        $this->assertEquals('Daily rate must be 0 or greater', $errors['dailyRate']);
    }

    /**
     * Test validation with daily rate exceeding maximum
     */
    public function testValidateWithDailyRateExceedingMaximum()
    {
        $data = [
            'currency' => 'USD',
            'dailyRate' => 10000,
            'paymentTerms' => 'net30',
        ];

        $errors = $this->financeSettingsStep->validate($data);

        $this->assertArrayHasKey('dailyRate', $errors);
        $this->assertEquals('Daily rate must not exceed 9999.99', $errors['dailyRate']);
    }

    /**
     * Test validation with zero daily rate
     */
    public function testValidateWithZeroDailyRate()
    {
        $data = [
            'currency' => 'USD',
            'dailyRate' => 0,
            'paymentTerms' => 'net30',
        ];

        $errors = $this->financeSettingsStep->validate($data);

        $this->assertArrayNotHasKey('dailyRate', $errors, 'Zero daily rate should be valid');
    }

    /**
     * Test validation with missing payment terms
     */
    public function testValidateWithMissingPaymentTerms()
    {
        $data = [
            'currency' => 'USD',
            'dailyRate' => 50.00,
        ];

        $errors = $this->financeSettingsStep->validate($data);

        $this->assertArrayHasKey('paymentTerms', $errors);
        $this->assertEquals('Payment terms are required', $errors['paymentTerms']);
    }

    /**
     * Test validation with invalid payment terms
     */
    public function testValidateWithInvalidPaymentTerms()
    {
        $data = [
            'currency' => 'USD',
            'dailyRate' => 50.00,
            'paymentTerms' => 'invalid',
        ];

        $errors = $this->financeSettingsStep->validate($data);

        $this->assertArrayHasKey('paymentTerms', $errors);
        $this->assertStringContainsString('Payment terms must be one of', $errors['paymentTerms']);
    }

    /**
     * Test validation with all valid payment terms
     */
    public function testValidateWithAllValidPaymentTerms()
    {
        $terms = ['immediate', 'net7', 'net15', 'net30', 'net60', 'net90'];

        foreach ($terms as $term) {
            $data = [
                'currency' => 'USD',
                'dailyRate' => 50.00,
                'paymentTerms' => $term,
            ];

            $errors = $this->financeSettingsStep->validate($data);

            $this->assertArrayNotHasKey('paymentTerms', $errors, "Payment term {$term} should be valid");
        }
    }

    /**
     * Test validation with custom payment terms without days
     */
    public function testValidateWithCustomPaymentTermsWithoutDays()
    {
        $data = [
            'currency' => 'USD',
            'dailyRate' => 50.00,
            'paymentTerms' => 'custom',
        ];

        $errors = $this->financeSettingsStep->validate($data);

        $this->assertArrayHasKey('customPaymentDays', $errors);
        $this->assertEquals('Custom payment days are required when payment terms is custom', $errors['customPaymentDays']);
    }

    /**
     * Test validation with custom payment terms with valid days
     */
    public function testValidateWithCustomPaymentTermsWithValidDays()
    {
        $data = [
            'currency' => 'USD',
            'dailyRate' => 50.00,
            'paymentTerms' => 'custom',
            'customPaymentDays' => 45,
        ];

        $errors = $this->financeSettingsStep->validate($data);

        $this->assertArrayNotHasKey('customPaymentDays', $errors);
    }

    /**
     * Test validation with non-numeric custom payment days
     */
    public function testValidateWithNonNumericCustomPaymentDays()
    {
        $data = [
            'currency' => 'USD',
            'dailyRate' => 50.00,
            'paymentTerms' => 'custom',
            'customPaymentDays' => 'abc',
        ];

        $errors = $this->financeSettingsStep->validate($data);

        $this->assertArrayHasKey('customPaymentDays', $errors);
        $this->assertEquals('Custom payment days must be a number', $errors['customPaymentDays']);
    }

    /**
     * Test validation with custom payment days less than 1
     */
    public function testValidateWithCustomPaymentDaysLessThanOne()
    {
        $data = [
            'currency' => 'USD',
            'dailyRate' => 50.00,
            'paymentTerms' => 'custom',
            'customPaymentDays' => 0,
        ];

        $errors = $this->financeSettingsStep->validate($data);

        $this->assertArrayHasKey('customPaymentDays', $errors);
        $this->assertEquals('Custom payment days must be at least 1', $errors['customPaymentDays']);
    }

    /**
     * Test validation with custom payment days exceeding maximum
     */
    public function testValidateWithCustomPaymentDaysExceedingMaximum()
    {
        $data = [
            'currency' => 'USD',
            'dailyRate' => 50.00,
            'paymentTerms' => 'custom',
            'customPaymentDays' => 366,
        ];

        $errors = $this->financeSettingsStep->validate($data);

        $this->assertArrayHasKey('customPaymentDays', $errors);
        $this->assertEquals('Custom payment days must not exceed 365', $errors['customPaymentDays']);
    }

    /**
     * Test validation with tax number too short
     */
    public function testValidateWithTaxNumberTooShort()
    {
        $data = [
            'currency' => 'USD',
            'dailyRate' => 50.00,
            'paymentTerms' => 'net30',
            'taxNumber' => '1234',
        ];

        $errors = $this->financeSettingsStep->validate($data);

        $this->assertArrayHasKey('taxNumber', $errors);
        $this->assertEquals('Tax number must be at least 5 characters', $errors['taxNumber']);
    }

    /**
     * Test validation with tax number too long
     */
    public function testValidateWithTaxNumberTooLong()
    {
        $data = [
            'currency' => 'USD',
            'dailyRate' => 50.00,
            'paymentTerms' => 'net30',
            'taxNumber' => str_repeat('1', 51),
        ];

        $errors = $this->financeSettingsStep->validate($data);

        $this->assertArrayHasKey('taxNumber', $errors);
        $this->assertEquals('Tax number must not exceed 50 characters', $errors['taxNumber']);
    }

    /**
     * Test validation with valid tax number
     */
    public function testValidateWithValidTaxNumber()
    {
        $data = [
            'currency' => 'USD',
            'dailyRate' => 50.00,
            'paymentTerms' => 'net30',
            'taxNumber' => '12-3456789',
        ];

        $errors = $this->financeSettingsStep->validate($data);

        $this->assertArrayNotHasKey('taxNumber', $errors);
    }

    /**
     * Test validation with VAT number too short
     */
    public function testValidateWithVATNumberTooShort()
    {
        $data = [
            'currency' => 'USD',
            'dailyRate' => 50.00,
            'paymentTerms' => 'net30',
            'vatNumber' => '1234',
        ];

        $errors = $this->financeSettingsStep->validate($data);

        $this->assertArrayHasKey('vatNumber', $errors);
        $this->assertEquals('VAT number must be at least 5 characters', $errors['vatNumber']);
    }

    /**
     * Test validation with VAT number too long
     */
    public function testValidateWithVATNumberTooLong()
    {
        $data = [
            'currency' => 'USD',
            'dailyRate' => 50.00,
            'paymentTerms' => 'net30',
            'vatNumber' => str_repeat('1', 51),
        ];

        $errors = $this->financeSettingsStep->validate($data);

        $this->assertArrayHasKey('vatNumber', $errors);
        $this->assertEquals('VAT number must not exceed 50 characters', $errors['vatNumber']);
    }

    /**
     * Test validation with valid VAT number
     */
    public function testValidateWithValidVATNumber()
    {
        $data = [
            'currency' => 'USD',
            'dailyRate' => 50.00,
            'paymentTerms' => 'net30',
            'vatNumber' => 'GB123456789',
        ];

        $errors = $this->financeSettingsStep->validate($data);

        $this->assertArrayNotHasKey('vatNumber', $errors);
    }

    /**
     * Test validation with late fee percentage non-numeric
     */
    public function testValidateWithLateFeePercentageNonNumeric()
    {
        $data = [
            'currency' => 'USD',
            'dailyRate' => 50.00,
            'paymentTerms' => 'net30',
            'lateFeePercentage' => 'abc',
        ];

        $errors = $this->financeSettingsStep->validate($data);

        $this->assertArrayHasKey('lateFeePercentage', $errors);
        $this->assertEquals('Late fee percentage must be a number', $errors['lateFeePercentage']);
    }

    /**
     * Test validation with negative late fee percentage
     */
    public function testValidateWithNegativeLateFeePercentage()
    {
        $data = [
            'currency' => 'USD',
            'dailyRate' => 50.00,
            'paymentTerms' => 'net30',
            'lateFeePercentage' => -5,
        ];

        $errors = $this->financeSettingsStep->validate($data);

        $this->assertArrayHasKey('lateFeePercentage', $errors);
        $this->assertEquals('Late fee percentage must be 0 or greater', $errors['lateFeePercentage']);
    }

    /**
     * Test validation with late fee percentage exceeding maximum
     */
    public function testValidateWithLateFeePercentageExceedingMaximum()
    {
        $data = [
            'currency' => 'USD',
            'dailyRate' => 50.00,
            'paymentTerms' => 'net30',
            'lateFeePercentage' => 101,
        ];

        $errors = $this->financeSettingsStep->validate($data);

        $this->assertArrayHasKey('lateFeePercentage', $errors);
        $this->assertEquals('Late fee percentage must not exceed 100', $errors['lateFeePercentage']);
    }

    /**
     * Test validation with valid late fee percentage
     */
    public function testValidateWithValidLateFeePercentage()
    {
        $data = [
            'currency' => 'USD',
            'dailyRate' => 50.00,
            'paymentTerms' => 'net30',
            'lateFeePercentage' => 5.5,
        ];

        $errors = $this->financeSettingsStep->validate($data);

        $this->assertArrayNotHasKey('lateFeePercentage', $errors);
    }

    /**
     * Test validation with invoice prefix too long
     */
    public function testValidateWithInvoicePrefixTooLong()
    {
        $data = [
            'currency' => 'USD',
            'dailyRate' => 50.00,
            'paymentTerms' => 'net30',
            'invoicePrefix' => str_repeat('A', 21),
        ];

        $errors = $this->financeSettingsStep->validate($data);

        $this->assertArrayHasKey('invoicePrefix', $errors);
        $this->assertEquals('Invoice prefix must not exceed 20 characters', $errors['invoicePrefix']);
    }

    /**
     * Test validation with invalid invoice prefix characters
     */
    public function testValidateWithInvalidInvoicePrefixCharacters()
    {
        $data = [
            'currency' => 'USD',
            'dailyRate' => 50.00,
            'paymentTerms' => 'net30',
            'invoicePrefix' => 'INV_@#$',
        ];

        $errors = $this->financeSettingsStep->validate($data);

        $this->assertArrayHasKey('invoicePrefix', $errors);
        $this->assertEquals('Invoice prefix must contain only letters, numbers, and hyphens', $errors['invoicePrefix']);
    }

    /**
     * Test validation with valid invoice prefix
     */
    public function testValidateWithValidInvoicePrefix()
    {
        $data = [
            'currency' => 'USD',
            'dailyRate' => 50.00,
            'paymentTerms' => 'net30',
            'invoicePrefix' => 'INV-2024',
        ];

        $errors = $this->financeSettingsStep->validate($data);

        $this->assertArrayNotHasKey('invoicePrefix', $errors);
    }

    /**
     * Test validation with invoice start number non-numeric
     */
    public function testValidateWithInvoiceStartNumberNonNumeric()
    {
        $data = [
            'currency' => 'USD',
            'dailyRate' => 50.00,
            'paymentTerms' => 'net30',
            'invoiceStartNumber' => 'abc',
        ];

        $errors = $this->financeSettingsStep->validate($data);

        $this->assertArrayHasKey('invoiceStartNumber', $errors);
        $this->assertEquals('Invoice start number must be a number', $errors['invoiceStartNumber']);
    }

    /**
     * Test validation with invoice start number less than 1
     */
    public function testValidateWithInvoiceStartNumberLessThanOne()
    {
        $data = [
            'currency' => 'USD',
            'dailyRate' => 50.00,
            'paymentTerms' => 'net30',
            'invoiceStartNumber' => 0,
        ];

        $errors = $this->financeSettingsStep->validate($data);

        $this->assertArrayHasKey('invoiceStartNumber', $errors);
        $this->assertEquals('Invoice start number must be at least 1', $errors['invoiceStartNumber']);
    }

    /**
     * Test validation with invoice start number exceeding maximum
     */
    public function testValidateWithInvoiceStartNumberExceedingMaximum()
    {
        $data = [
            'currency' => 'USD',
            'dailyRate' => 50.00,
            'paymentTerms' => 'net30',
            'invoiceStartNumber' => 1000000,
        ];

        $errors = $this->financeSettingsStep->validate($data);

        $this->assertArrayHasKey('invoiceStartNumber', $errors);
        $this->assertEquals('Invoice start number must not exceed 999999', $errors['invoiceStartNumber']);
    }

    /**
     * Test validation with valid invoice start number
     */
    public function testValidateWithValidInvoiceStartNumber()
    {
        $data = [
            'currency' => 'USD',
            'dailyRate' => 50.00,
            'paymentTerms' => 'net30',
            'invoiceStartNumber' => 1000,
        ];

        $errors = $this->financeSettingsStep->validate($data);

        $this->assertArrayNotHasKey('invoiceStartNumber', $errors);
    }

    /**
     * Test validation with tax rate non-numeric
     */
    public function testValidateWithTaxRateNonNumeric()
    {
        $data = [
            'currency' => 'USD',
            'dailyRate' => 50.00,
            'paymentTerms' => 'net30',
            'taxRate' => 'abc',
        ];

        $errors = $this->financeSettingsStep->validate($data);

        $this->assertArrayHasKey('taxRate', $errors);
        $this->assertEquals('Tax rate must be a number', $errors['taxRate']);
    }

    /**
     * Test validation with negative tax rate
     */
    public function testValidateWithNegativeTaxRate()
    {
        $data = [
            'currency' => 'USD',
            'dailyRate' => 50.00,
            'paymentTerms' => 'net30',
            'taxRate' => -5,
        ];

        $errors = $this->financeSettingsStep->validate($data);

        $this->assertArrayHasKey('taxRate', $errors);
        $this->assertEquals('Tax rate must be 0 or greater', $errors['taxRate']);
    }

    /**
     * Test validation with tax rate exceeding maximum
     */
    public function testValidateWithTaxRateExceedingMaximum()
    {
        $data = [
            'currency' => 'USD',
            'dailyRate' => 50.00,
            'paymentTerms' => 'net30',
            'taxRate' => 101,
        ];

        $errors = $this->financeSettingsStep->validate($data);

        $this->assertArrayHasKey('taxRate', $errors);
        $this->assertEquals('Tax rate must not exceed 100', $errors['taxRate']);
    }

    /**
     * Test validation with valid tax rate
     */
    public function testValidateWithValidTaxRate()
    {
        $data = [
            'currency' => 'USD',
            'dailyRate' => 50.00,
            'paymentTerms' => 'net30',
            'taxRate' => 8.5,
        ];

        $errors = $this->financeSettingsStep->validate($data);

        $this->assertArrayNotHasKey('taxRate', $errors);
    }

    /**
     * Test validation with all optional fields
     */
    public function testValidateWithAllOptionalFields()
    {
        $data = [
            'currency' => 'USD',
            'dailyRate' => 75.50,
            'paymentTerms' => 'custom',
            'customPaymentDays' => 45,
            'taxNumber' => '12-3456789',
            'vatNumber' => 'GB123456789',
            'lateFeePercentage' => 5.5,
            'invoicePrefix' => 'INV-2024',
            'invoiceStartNumber' => 1000,
            'taxRate' => 8.5,
        ];

        $errors = $this->financeSettingsStep->validate($data);

        $this->assertEmpty($errors, 'All valid optional fields should pass validation');
    }

    /**
     * Test save with valid data
     */
    public function testSaveWithValidData()
    {
        $data = [
            'currency' => 'USD',
            'dailyRate' => 50.00,
            'paymentTerms' => 'net30',
        ];

        $this->installationDetector->expects($this->once())
            ->method('saveWizardProgress')
            ->with('finance_settings', $data);

        $result = $this->financeSettingsStep->save($data);

        $this->assertTrue($result, 'Save should succeed with valid data');

        // Verify settings were saved
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM gibbonSetting WHERE scope = 'Finance'");
        $count = $stmt->fetchColumn();
        $this->assertGreaterThanOrEqual(3, $count);
    }

    /**
     * Test save with invalid data
     */
    public function testSaveWithInvalidData()
    {
        $data = [
            'currency' => 'XXX',
            'dailyRate' => 50.00,
            'paymentTerms' => 'net30',
        ];

        $this->installationDetector->expects($this->never())
            ->method('saveWizardProgress');

        $result = $this->financeSettingsStep->save($data);

        $this->assertFalse($result, 'Save should fail with invalid data');
    }

    /**
     * Test save with all fields
     */
    public function testSaveWithAllFields()
    {
        $data = [
            'currency' => 'EUR',
            'dailyRate' => 75.50,
            'paymentTerms' => 'custom',
            'customPaymentDays' => 45,
            'taxNumber' => '12-3456789',
            'vatNumber' => 'GB123456789',
            'lateFeePercentage' => 5.5,
            'invoicePrefix' => 'INV-2024',
            'invoiceStartNumber' => 1000,
            'taxRate' => 8.5,
        ];

        $this->installationDetector->expects($this->once())
            ->method('saveWizardProgress');

        $result = $this->financeSettingsStep->save($data);

        $this->assertTrue($result);

        // Verify all settings were saved
        $stmt = $this->pdo->query("
            SELECT name, value FROM gibbonSetting WHERE scope = 'Finance'
        ");
        $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        $this->assertEquals('EUR', $settings['currency']);
        $this->assertEquals('75.5', $settings['dailyRate']);
        $this->assertEquals('custom', $settings['paymentTerms']);
        $this->assertEquals('45', $settings['customPaymentDays']);
        $this->assertEquals('12-3456789', $settings['taxNumber']);
        $this->assertEquals('GB123456789', $settings['vatNumber']);
        $this->assertEquals('5.5', $settings['lateFeePercentage']);
        $this->assertEquals('INV-2024', $settings['invoicePrefix']);
        $this->assertEquals('1000', $settings['invoiceStartNumber']);
        $this->assertEquals('8.5', $settings['taxRate']);
    }

    /**
     * Test save updates existing settings
     */
    public function testSaveUpdatesExistingSettings()
    {
        // Save initial data
        $this->financeSettingsStep->save([
            'currency' => 'USD',
            'dailyRate' => 50.00,
            'paymentTerms' => 'net30',
        ]);

        // Update with new data
        $data = [
            'currency' => 'EUR',
            'dailyRate' => 75.00,
            'paymentTerms' => 'net15',
        ];

        $this->installationDetector->expects($this->atLeastOnce())
            ->method('saveWizardProgress');

        $result = $this->financeSettingsStep->save($data);

        $this->assertTrue($result);

        // Verify settings were updated
        $stmt = $this->pdo->query("
            SELECT name, value FROM gibbonSetting WHERE scope = 'Finance'
        ");
        $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        $this->assertEquals('EUR', $settings['currency']);
        $this->assertEquals('75', $settings['dailyRate']);
        $this->assertEquals('net15', $settings['paymentTerms']);
    }

    /**
     * Test isCompleted returns false when no settings
     */
    public function testIsCompletedReturnsFalseWhenNoSettings()
    {
        $result = $this->financeSettingsStep->isCompleted();

        $this->assertFalse($result);
    }

    /**
     * Test isCompleted returns true when settings exist
     */
    public function testIsCompletedReturnsTrueWhenSettingsExist()
    {
        $data = [
            'currency' => 'USD',
            'dailyRate' => 50.00,
            'paymentTerms' => 'net30',
        ];

        $this->installationDetector->expects($this->once())
            ->method('saveWizardProgress');

        $this->financeSettingsStep->save($data);

        $result = $this->financeSettingsStep->isCompleted();

        $this->assertTrue($result);
    }

    /**
     * Test getFinanceSettings returns saved data
     */
    public function testGetFinanceSettingsReturnsSavedData()
    {
        $data = [
            'currency' => 'GBP',
            'dailyRate' => 60.00,
            'paymentTerms' => 'net30',
            'taxNumber' => '12-3456789',
        ];

        $this->installationDetector->expects($this->once())
            ->method('saveWizardProgress');

        $this->financeSettingsStep->save($data);

        $result = $this->financeSettingsStep->getFinanceSettings();

        $this->assertIsArray($result);
        $this->assertEquals('GBP', $result['currency']);
        $this->assertEquals(60.00, $result['dailyRate']);
        $this->assertEquals('net30', $result['paymentTerms']);
        $this->assertEquals('12-3456789', $result['taxNumber']);
    }

    /**
     * Test getFinanceSettings returns null when no data
     */
    public function testGetFinanceSettingsReturnsNullWhenNoData()
    {
        $result = $this->financeSettingsStep->getFinanceSettings();

        $this->assertNull($result);
    }

    /**
     * Test prepareData returns default settings when no data
     */
    public function testPrepareDataReturnsDefaultSettingsWhenNoData()
    {
        $this->installationDetector->method('getWizardProgress')
            ->willReturn(null);

        $result = $this->financeSettingsStep->prepareData();

        $this->assertIsArray($result);
        $this->assertEquals('USD', $result['currency']);
        $this->assertEquals(0, $result['dailyRate']);
        $this->assertEquals('net30', $result['paymentTerms']);
    }

    /**
     * Test prepareData merges saved and wizard progress data
     */
    public function testPrepareDataMergesSavedAndWizardProgress()
    {
        // Save initial data
        $this->financeSettingsStep->save([
            'currency' => 'USD',
            'dailyRate' => 50.00,
            'paymentTerms' => 'net30',
        ]);

        // Mock wizard progress with different data
        $wizardProgress = [
            'currency' => 'EUR',
            'dailyRate' => 75.00,
        ];

        $this->installationDetector->method('getWizardProgress')
            ->willReturn(['stepData' => $wizardProgress]);

        $result = $this->financeSettingsStep->prepareData();

        // Wizard progress should override saved data
        $this->assertEquals('EUR', $result['currency']);
        $this->assertEquals(75.00, $result['dailyRate']);
    }

    /**
     * Test clear removes all finance settings
     */
    public function testClearRemovesAllFinanceSettings()
    {
        // Save settings
        $this->financeSettingsStep->save([
            'currency' => 'USD',
            'dailyRate' => 50.00,
            'paymentTerms' => 'net30',
        ]);

        $result = $this->financeSettingsStep->clear();

        $this->assertTrue($result);

        // Verify settings were deleted
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM gibbonSetting WHERE scope = 'Finance'");
        $count = $stmt->fetchColumn();
        $this->assertEquals(0, $count);
    }

    /**
     * Test getDefaultSettings returns proper defaults
     */
    public function testGetDefaultSettingsReturnsProperDefaults()
    {
        $result = $this->financeSettingsStep->getDefaultSettings();

        $this->assertIsArray($result);
        $this->assertEquals('USD', $result['currency']);
        $this->assertEquals(0, $result['dailyRate']);
        $this->assertEquals('net30', $result['paymentTerms']);
        $this->assertEquals('INV', $result['invoicePrefix']);
        $this->assertEquals(1, $result['invoiceStartNumber']);
    }

    /**
     * Test getSupportedCurrencies returns currency array
     */
    public function testGetSupportedCurrenciesReturnsCurrencyArray()
    {
        $result = $this->financeSettingsStep->getSupportedCurrencies();

        $this->assertIsArray($result);
        $this->assertContains('USD', $result);
        $this->assertContains('EUR', $result);
        $this->assertContains('GBP', $result);
        $this->assertCount(8, $result);
    }

    /**
     * Test getPaymentTermOptions returns payment term array
     */
    public function testGetPaymentTermOptionsReturnsPaymentTermArray()
    {
        $result = $this->financeSettingsStep->getPaymentTermOptions();

        $this->assertIsArray($result);
        $this->assertContains('immediate', $result);
        $this->assertContains('net30', $result);
        $this->assertContains('custom', $result);
        $this->assertCount(7, $result);
    }

    /**
     * Test getWizardProgress returns null when no progress
     */
    public function testGetWizardProgressReturnsNullWhenNoProgress()
    {
        $this->installationDetector->method('getWizardProgress')
            ->willReturn(null);

        $result = $this->financeSettingsStep->getWizardProgress();

        $this->assertNull($result);
    }

    /**
     * Test getWizardProgress returns stepData when available
     */
    public function testGetWizardProgressReturnsStepDataWhenAvailable()
    {
        $stepData = [
            'currency' => 'EUR',
            'dailyRate' => 75.00,
        ];

        $this->installationDetector->method('getWizardProgress')
            ->willReturn(['stepData' => $stepData]);

        $result = $this->financeSettingsStep->getWizardProgress();

        $this->assertEquals($stepData, $result);
    }

    /**
     * Test validation with edge case values
     */
    public function testValidateWithEdgeCaseValues()
    {
        $data = [
            'currency' => 'USD',
            'dailyRate' => 9999.99,  // Maximum
            'paymentTerms' => 'custom',
            'customPaymentDays' => 365,  // Maximum
            'taxNumber' => '12345',  // Minimum length
            'lateFeePercentage' => 100,  // Maximum
            'invoiceStartNumber' => 999999,  // Maximum
            'taxRate' => 100,  // Maximum
        ];

        $errors = $this->financeSettingsStep->validate($data);

        $this->assertEmpty($errors, 'Edge case values should be valid');
    }

    /**
     * Test validation with multiple errors
     */
    public function testValidateWithMultipleErrors()
    {
        $data = [
            'currency' => 'XXX',  // Invalid
            'dailyRate' => -10,  // Negative
            'paymentTerms' => 'invalid',  // Invalid
            'taxNumber' => '123',  // Too short
            'lateFeePercentage' => 150,  // Too high
        ];

        $errors = $this->financeSettingsStep->validate($data);

        $this->assertArrayHasKey('currency', $errors);
        $this->assertArrayHasKey('dailyRate', $errors);
        $this->assertArrayHasKey('paymentTerms', $errors);
        $this->assertArrayHasKey('taxNumber', $errors);
        $this->assertArrayHasKey('lateFeePercentage', $errors);
    }
}
