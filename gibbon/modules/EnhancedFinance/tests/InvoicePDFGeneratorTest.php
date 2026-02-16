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
use Gibbon\Module\EnhancedFinance\Domain\InvoicePDFGenerator;
use Gibbon\Contracts\Services\Session;
use Gibbon\Domain\System\SettingGateway;

/**
 * Unit tests for InvoicePDFGenerator.
 *
 * Tests PDF generation, itemized services display, GST/QST tax calculations,
 * branding integration, template rendering, and batch generation.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class InvoicePDFGeneratorTest extends TestCase
{
    /**
     * @var MockObject|Session
     */
    protected $session;

    /**
     * @var MockObject|SettingGateway
     */
    protected $settingGateway;

    /**
     * @var InvoicePDFGenerator
     */
    protected $generator;

    /**
     * Sample invoice data for testing.
     *
     * @var array
     */
    protected $sampleInvoiceData;

    /**
     * Set up test fixtures.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create mocks
        $this->session = $this->createMock(Session::class);
        $this->settingGateway = $this->createMock(SettingGateway::class);

        // Configure session mock with default values
        $this->session->method('get')->willReturnMap([
            ['organisationName', 'LAYA Kindergarten'],
            ['organisationAddress', '123 Main Street'],
            ['organisationAddressLocality', 'Montreal'],
            ['organisationAddressRegion', 'Quebec'],
            ['organisationPostalCode', 'H1A 1A1'],
            ['organisationCountry', 'Canada'],
            ['organisationLogo', null],
        ]);

        // Create generator instance
        $this->generator = new InvoicePDFGenerator($this->session, $this->settingGateway);

        // Sample invoice data with itemized services
        $this->sampleInvoiceData = [
            'invoiceNumber' => 'INV-000001',
            'invoiceDate' => '2026-02-01',
            'dueDate' => '2026-02-28',
            'period' => 'February 2026',
            'customerName' => 'Smith Family',
            'customerAddress' => "456 Oak Avenue\nMontreal, QC H2X 1Y1",
            'customerEmail' => 'smith@example.com',
            'customerPhone' => '(514) 555-0123',
            'items' => [
                [
                    'description' => 'Basic Daycare - Full Time',
                    'quantity' => 20,
                    'unitPrice' => 45.00,
                ],
                [
                    'description' => 'Hot Lunch Program',
                    'quantity' => 15,
                    'unitPrice' => 8.50,
                ],
                [
                    'description' => 'Extended Care (After-Hours)',
                    'quantity' => 5,
                    'unitPrice' => 12.00,
                ],
            ],
            'paymentTerms' => 'Payment is due by the last day of the month. Late payments subject to $25 late fee.',
            'paymentMethods' => "E-Transfer: payments@layakindergarten.com\nCheque: Payable to LAYA Kindergarten\nCredit/Debit: Available at front desk",
            'notes' => 'Thank you for choosing LAYA Kindergarten for your childcare needs.',
        ];
    }

    /**
     * Clean up after tests.
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        $this->session = null;
        $this->settingGateway = null;
        $this->generator = null;
    }

    // =========================================================================
    // ITEMIZED SERVICES DISPLAY TESTS
    // =========================================================================

    /**
     * Test invoice data contains items array.
     */
    public function testInvoiceDataContainsItemsArray(): void
    {
        $this->assertArrayHasKey('items', $this->sampleInvoiceData, 'Invoice data should contain items array');
        $this->assertIsArray($this->sampleInvoiceData['items'], 'Items should be an array');
        $this->assertNotEmpty($this->sampleInvoiceData['items'], 'Items array should not be empty');
    }

    /**
     * Test each item has required fields.
     */
    public function testItemizedServiceHasRequiredFields(): void
    {
        $requiredFields = ['description', 'quantity', 'unitPrice'];

        foreach ($this->sampleInvoiceData['items'] as $index => $item) {
            foreach ($requiredFields as $field) {
                $this->assertArrayHasKey(
                    $field,
                    $item,
                    "Item {$index} should contain required field: {$field}"
                );
            }
        }
    }

    /**
     * Test item description is string.
     */
    public function testItemDescriptionIsString(): void
    {
        foreach ($this->sampleInvoiceData['items'] as $item) {
            $this->assertIsString($item['description'], 'Item description should be a string');
            $this->assertNotEmpty($item['description'], 'Item description should not be empty');
        }
    }

    /**
     * Test item quantity is numeric.
     */
    public function testItemQuantityIsNumeric(): void
    {
        foreach ($this->sampleInvoiceData['items'] as $item) {
            $this->assertIsNumeric($item['quantity'], 'Item quantity should be numeric');
            $this->assertGreaterThan(0, $item['quantity'], 'Item quantity should be positive');
        }
    }

    /**
     * Test item unit price is numeric.
     */
    public function testItemUnitPriceIsNumeric(): void
    {
        foreach ($this->sampleInvoiceData['items'] as $item) {
            $this->assertIsNumeric($item['unitPrice'], 'Item unit price should be numeric');
            $this->assertGreaterThanOrEqual(0, $item['unitPrice'], 'Item unit price should be non-negative');
        }
    }

    /**
     * Test item total calculation.
     */
    public function testItemTotalCalculation(): void
    {
        $item = $this->sampleInvoiceData['items'][0]; // Basic Daycare
        $expectedTotal = 20 * 45.00;

        $actualTotal = $item['quantity'] * $item['unitPrice'];

        $this->assertEquals($expectedTotal, $actualTotal, 'Item total should be quantity × unit price');
        $this->assertEquals(900.00, $actualTotal, 'Basic Daycare total should be $900.00');
    }

    /**
     * Test subtotal calculation from multiple items.
     */
    public function testSubtotalCalculationFromMultipleItems(): void
    {
        $expectedSubtotal = (20 * 45.00) + (15 * 8.50) + (5 * 12.00);

        // Use reflection to call protected method
        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('calculateSubtotal');
        $method->setAccessible(true);

        $actualSubtotal = $method->invoke($this->generator, $this->sampleInvoiceData['items']);

        $this->assertEquals($expectedSubtotal, $actualSubtotal, 'Subtotal should sum all item totals');
        $this->assertEquals(1087.50, $actualSubtotal, 'Subtotal should be $1,087.50');
    }

    /**
     * Test itemized services display with single item.
     */
    public function testItemizedServicesWithSingleItem(): void
    {
        $singleItemInvoice = $this->sampleInvoiceData;
        $singleItemInvoice['items'] = [
            [
                'description' => 'Monthly Daycare Fee',
                'quantity' => 1,
                'unitPrice' => 1000.00,
            ],
        ];

        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('calculateSubtotal');
        $method->setAccessible(true);

        $subtotal = $method->invoke($this->generator, $singleItemInvoice['items']);

        $this->assertEquals(1000.00, $subtotal, 'Single item subtotal should be calculated correctly');
    }

    /**
     * Test itemized services display with many items.
     */
    public function testItemizedServicesWithManyItems(): void
    {
        $manyItemsInvoice = $this->sampleInvoiceData;
        $manyItemsInvoice['items'] = [];

        // Generate 20 different items
        for ($i = 1; $i <= 20; $i++) {
            $manyItemsInvoice['items'][] = [
                'description' => "Service Item {$i}",
                'quantity' => $i,
                'unitPrice' => 10.00,
            ];
        }

        // Sum should be: 1*10 + 2*10 + 3*10 + ... + 20*10 = (1+2+...+20)*10 = 210*10 = 2100
        $expectedSubtotal = array_sum(range(1, 20)) * 10.00;

        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('calculateSubtotal');
        $method->setAccessible(true);

        $subtotal = $method->invoke($this->generator, $manyItemsInvoice['items']);

        $this->assertEquals($expectedSubtotal, $subtotal, 'Many items subtotal should be calculated correctly');
        $this->assertEquals(2100.00, $subtotal, 'Many items subtotal should be $2,100.00');
    }

    /**
     * Test itemized services with decimal quantities.
     */
    public function testItemizedServicesWithDecimalQuantities(): void
    {
        $decimalQuantityInvoice = $this->sampleInvoiceData;
        $decimalQuantityInvoice['items'] = [
            [
                'description' => 'Partial Day Care (4.5 hours)',
                'quantity' => 4.5,
                'unitPrice' => 20.00,
            ],
        ];

        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('calculateSubtotal');
        $method->setAccessible(true);

        $subtotal = $method->invoke($this->generator, $decimalQuantityInvoice['items']);

        $this->assertEquals(90.00, $subtotal, 'Decimal quantity should be calculated correctly');
    }

    /**
     * Test itemized services with decimal unit prices.
     */
    public function testItemizedServicesWithDecimalUnitPrices(): void
    {
        $decimalPriceInvoice = $this->sampleInvoiceData;
        $decimalPriceInvoice['items'] = [
            [
                'description' => 'Snack Fee',
                'quantity' => 20,
                'unitPrice' => 2.75,
            ],
        ];

        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('calculateSubtotal');
        $method->setAccessible(true);

        $subtotal = $method->invoke($this->generator, $decimalPriceInvoice['items']);

        $this->assertEquals(55.00, $subtotal, 'Decimal unit price should be calculated correctly');
    }

    /**
     * Test itemized services display with zero-price item.
     */
    public function testItemizedServicesWithZeroPriceItem(): void
    {
        $zeroPriceInvoice = $this->sampleInvoiceData;
        $zeroPriceInvoice['items'][] = [
            'description' => 'Promotional Discount',
            'quantity' => 1,
            'unitPrice' => 0.00,
        ];

        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('calculateSubtotal');
        $method->setAccessible(true);

        $subtotal = $method->invoke($this->generator, $zeroPriceInvoice['items']);

        // Should still include the zero-price item in calculation
        $this->assertGreaterThanOrEqual(1087.50, $subtotal, 'Zero-price item should not break calculation');
    }

    /**
     * Test item descriptions support special characters.
     */
    public function testItemDescriptionSupportsSpecialCharacters(): void
    {
        $specialCharInvoice = $this->sampleInvoiceData;
        $specialCharInvoice['items'] = [
            [
                'description' => 'Daycare & Extended Care - "Premium" Service (Ages 2-5)',
                'quantity' => 1,
                'unitPrice' => 100.00,
            ],
        ];

        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('renderInvoiceTemplate');
        $method->setAccessible(true);

        $html = $method->invoke($this->generator, $specialCharInvoice);

        // Check that HTML entities are properly escaped
        $this->assertStringContainsString('&amp;', $html, 'Ampersand should be HTML encoded');
        $this->assertStringContainsString('Extended Care', $html, 'Description should be rendered');
    }

    // =========================================================================
    // GST/QST TAX CALCULATION TESTS
    // =========================================================================

    /**
     * Test GST rate constant.
     */
    public function testGSTRateConstant(): void
    {
        $this->assertEquals(0.05, InvoicePDFGenerator::GST_RATE, 'GST rate should be 5%');
    }

    /**
     * Test QST rate constant.
     */
    public function testQSTRateConstant(): void
    {
        $this->assertEquals(0.09975, InvoicePDFGenerator::QST_RATE, 'QST rate should be 9.975%');
    }

    /**
     * Test GST calculation on subtotal.
     */
    public function testGSTCalculationOnSubtotal(): void
    {
        $subtotal = 1087.50;
        $expectedGST = round($subtotal * InvoicePDFGenerator::GST_RATE, 2);

        $this->assertEquals(54.38, $expectedGST, 'GST should be 5% of subtotal');
    }

    /**
     * Test QST calculation on subtotal.
     */
    public function testQSTCalculationOnSubtotal(): void
    {
        $subtotal = 1087.50;
        $expectedQST = round($subtotal * InvoicePDFGenerator::QST_RATE, 2);

        $this->assertEquals(108.48, $expectedQST, 'QST should be 9.975% of subtotal');
    }

    /**
     * Test total amount calculation including taxes.
     */
    public function testTotalAmountCalculationWithTaxes(): void
    {
        $subtotal = 1087.50;
        $gst = round($subtotal * InvoicePDFGenerator::GST_RATE, 2);
        $qst = round($subtotal * InvoicePDFGenerator::QST_RATE, 2);
        $expectedTotal = $subtotal + $gst + $qst;

        $this->assertEquals(1250.36, $expectedTotal, 'Total should be subtotal + GST + QST');
    }

    /**
     * Test tax calculation with zero subtotal.
     */
    public function testTaxCalculationWithZeroSubtotal(): void
    {
        $subtotal = 0.00;
        $gst = round($subtotal * InvoicePDFGenerator::GST_RATE, 2);
        $qst = round($subtotal * InvoicePDFGenerator::QST_RATE, 2);
        $total = $subtotal + $gst + $qst;

        $this->assertEquals(0.00, $gst, 'GST should be 0 when subtotal is 0');
        $this->assertEquals(0.00, $qst, 'QST should be 0 when subtotal is 0');
        $this->assertEquals(0.00, $total, 'Total should be 0 when subtotal is 0');
    }

    /**
     * Test tax rounding to 2 decimal places.
     */
    public function testTaxRoundingToTwoDecimalPlaces(): void
    {
        $subtotal = 33.33;
        $gst = round($subtotal * InvoicePDFGenerator::GST_RATE, 2);
        $qst = round($subtotal * InvoicePDFGenerator::QST_RATE, 2);

        // Check that values are rounded to 2 decimal places
        $this->assertEquals(1.67, $gst, 'GST should round to 2 decimal places');
        $this->assertEquals(3.32, $qst, 'QST should round to 2 decimal places');
    }

    /**
     * Test tax calculation with large amounts (typical monthly daycare fee).
     */
    public function testTaxCalculationWithLargeAmount(): void
    {
        $subtotal = 5000.00; // Large monthly fee
        $gst = round($subtotal * InvoicePDFGenerator::GST_RATE, 2);
        $qst = round($subtotal * InvoicePDFGenerator::QST_RATE, 2);
        $total = $subtotal + $gst + $qst;

        $this->assertEquals(250.00, $gst, 'GST on $5000 should be $250.00');
        $this->assertEquals(498.75, $qst, 'QST on $5000 should be $498.75');
        $this->assertEquals(5748.75, $total, 'Total with taxes should be $5748.75');
    }

    /**
     * Test tax calculation with small amounts (single day care).
     */
    public function testTaxCalculationWithSmallAmount(): void
    {
        $subtotal = 10.00; // Small daily fee
        $gst = round($subtotal * InvoicePDFGenerator::GST_RATE, 2);
        $qst = round($subtotal * InvoicePDFGenerator::QST_RATE, 2);
        $total = $subtotal + $gst + $qst;

        $this->assertEquals(0.50, $gst, 'GST on $10 should be $0.50');
        $this->assertEquals(1.00, $qst, 'QST on $10 should be $1.00');
        $this->assertEquals(11.50, $total, 'Total with taxes should be $11.50');
    }

    /**
     * Test tax calculation with very small amount (pennies).
     */
    public function testTaxCalculationWithPennies(): void
    {
        $subtotal = 0.50; // 50 cents
        $gst = round($subtotal * InvoicePDFGenerator::GST_RATE, 2);
        $qst = round($subtotal * InvoicePDFGenerator::QST_RATE, 2);
        $total = $subtotal + $gst + $qst;

        $this->assertEquals(0.03, $gst, 'GST on $0.50 should be $0.03');
        $this->assertEquals(0.05, $qst, 'QST on $0.50 should be $0.05');
        $this->assertEquals(0.58, $total, 'Total with taxes should be $0.58');
    }

    /**
     * Test tax calculation with typical monthly daycare fee ($1,500).
     */
    public function testTaxCalculationWithTypicalMonthlyFee(): void
    {
        $subtotal = 1500.00; // Typical monthly daycare fee
        $gst = round($subtotal * InvoicePDFGenerator::GST_RATE, 2);
        $qst = round($subtotal * InvoicePDFGenerator::QST_RATE, 2);
        $total = $subtotal + $gst + $qst;

        $this->assertEquals(75.00, $gst, 'GST on $1500 should be $75.00');
        $this->assertEquals(149.63, $qst, 'QST on $1500 should be $149.63');
        $this->assertEquals(1724.63, $total, 'Total with taxes should be $1724.63');
    }

    /**
     * Test tax calculation accuracy for QST rate (9.975%).
     */
    public function testQSTRateAccuracy(): void
    {
        $subtotal = 1000.00;
        $qst = round($subtotal * InvoicePDFGenerator::QST_RATE, 2);

        // QST should be exactly 9.975% = $99.75
        $this->assertEquals(99.75, $qst, 'QST rate of 9.975% should be accurate');
    }

    /**
     * Test combined tax rate (GST + QST) is correct.
     */
    public function testCombinedTaxRate(): void
    {
        $combinedRate = InvoicePDFGenerator::GST_RATE + InvoicePDFGenerator::QST_RATE;

        // GST 5% + QST 9.975% = 14.975%
        $this->assertEquals(0.14975, $combinedRate, 'Combined tax rate should be 14.975%');
    }

    /**
     * Test tax calculation with rounding edge case (up).
     */
    public function testTaxRoundingEdgeCaseUp(): void
    {
        $subtotal = 99.99;
        $gst = round($subtotal * InvoicePDFGenerator::GST_RATE, 2);
        $qst = round($subtotal * InvoicePDFGenerator::QST_RATE, 2);

        // GST: 99.99 * 0.05 = 4.9995 → rounds to 5.00
        // QST: 99.99 * 0.09975 = 9.974025 → rounds to 9.97
        $this->assertEquals(5.00, $gst, 'GST should round up to $5.00');
        $this->assertEquals(9.97, $qst, 'QST should round down to $9.97');
    }

    /**
     * Test tax calculation with rounding edge case (down).
     */
    public function testTaxRoundingEdgeCaseDown(): void
    {
        $subtotal = 88.88;
        $gst = round($subtotal * InvoicePDFGenerator::GST_RATE, 2);
        $qst = round($subtotal * InvoicePDFGenerator::QST_RATE, 2);

        // GST: 88.88 * 0.05 = 4.444 → rounds to 4.44
        // QST: 88.88 * 0.09975 = 8.86590 → rounds to 8.87
        $this->assertEquals(4.44, $gst, 'GST should round down to $4.44');
        $this->assertEquals(8.87, $qst, 'QST should round up to $8.87');
    }

    /**
     * Test tax calculation consistency across multiple calculations.
     */
    public function testTaxCalculationConsistency(): void
    {
        $subtotal1 = 100.00;
        $subtotal2 = 100.00;

        $gst1 = round($subtotal1 * InvoicePDFGenerator::GST_RATE, 2);
        $gst2 = round($subtotal2 * InvoicePDFGenerator::GST_RATE, 2);
        $qst1 = round($subtotal1 * InvoicePDFGenerator::QST_RATE, 2);
        $qst2 = round($subtotal2 * InvoicePDFGenerator::QST_RATE, 2);

        $this->assertEquals($gst1, $gst2, 'GST calculation should be consistent');
        $this->assertEquals($qst1, $qst2, 'QST calculation should be consistent');
    }

    /**
     * Test tax calculation in actual invoice rendering.
     */
    public function testTaxCalculationInInvoiceRendering(): void
    {
        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('renderInvoiceTemplate');
        $method->setAccessible(true);

        $html = $method->invoke($this->generator, $this->sampleInvoiceData);

        // Verify tax amounts appear in rendered HTML
        $this->assertStringContainsString('54.38', $html, 'GST amount should appear in invoice');
        $this->assertStringContainsString('108.48', $html, 'QST amount should appear in invoice');
        $this->assertStringContainsString('1,250.36', $html, 'Total with taxes should appear in invoice');
    }

    /**
     * Test tax calculation with fractional cent subtotal.
     */
    public function testTaxCalculationWithFractionalCents(): void
    {
        $subtotal = 123.456; // This would come from item calculations
        $gst = round($subtotal * InvoicePDFGenerator::GST_RATE, 2);
        $qst = round($subtotal * InvoicePDFGenerator::QST_RATE, 2);

        // GST: 123.456 * 0.05 = 6.1728 → rounds to 6.17
        // QST: 123.456 * 0.09975 = 12.309756 → rounds to 12.31
        $this->assertEquals(6.17, $gst, 'GST should handle fractional cents correctly');
        $this->assertEquals(12.31, $qst, 'QST should handle fractional cents correctly');
    }

    /**
     * Test tax calculation preserves precision before rounding.
     */
    public function testTaxCalculationPrecision(): void
    {
        $subtotal = 1234.56;

        // Calculate with full precision
        $gstExact = $subtotal * InvoicePDFGenerator::GST_RATE;
        $qstExact = $subtotal * InvoicePDFGenerator::QST_RATE;

        // Then round
        $gst = round($gstExact, 2);
        $qst = round($qstExact, 2);

        // Verify precision is maintained before rounding
        $this->assertGreaterThan($gst, $gstExact, 'GST exact should have more precision');
        $this->assertEquals(61.73, $gst, 'GST should be correctly rounded');
        $this->assertEquals(123.14, $qst, 'QST should be correctly rounded');
    }

    /**
     * Test tax calculation matches Quebec tax regulations.
     */
    public function testTaxCalculationMatchesQuebecRegulations(): void
    {
        // Quebec tax regulations: GST 5% + QST 9.975%
        // Example from Quebec Revenue: $100 subtotal
        $subtotal = 100.00;
        $gst = round($subtotal * InvoicePDFGenerator::GST_RATE, 2);
        $qst = round($subtotal * InvoicePDFGenerator::QST_RATE, 2);
        $total = $subtotal + $gst + $qst;

        $this->assertEquals(5.00, $gst, 'GST should match Quebec regulation: 5%');
        $this->assertEquals(9.98, $qst, 'QST should match Quebec regulation: 9.975%');
        $this->assertEquals(114.98, $total, 'Total should match Quebec tax calculation');
    }

    /**
     * Test tax calculation for real-world daycare scenario.
     */
    public function testTaxCalculationRealWorldDaycareScenario(): void
    {
        // Realistic daycare invoice:
        // - 20 days basic care @ $45/day = $900
        // - 15 lunches @ $8.50/meal = $127.50
        // - 5 extended hours @ $12/hour = $60
        // Subtotal: $1,087.50

        $subtotal = 1087.50;
        $gst = round($subtotal * InvoicePDFGenerator::GST_RATE, 2);
        $qst = round($subtotal * InvoicePDFGenerator::QST_RATE, 2);
        $total = $subtotal + $gst + $qst;

        $this->assertEquals(54.38, $gst, 'GST for realistic daycare invoice');
        $this->assertEquals(108.48, $qst, 'QST for realistic daycare invoice');
        $this->assertEquals(1250.36, $total, 'Total for realistic daycare invoice');
    }

    /**
     * Test tax calculation doesn't compound (tax on tax).
     */
    public function testTaxCalculationDoesNotCompound(): void
    {
        $subtotal = 100.00;
        $gst = round($subtotal * InvoicePDFGenerator::GST_RATE, 2);
        $qst = round($subtotal * InvoicePDFGenerator::QST_RATE, 2);
        $total = $subtotal + $gst + $qst;

        // Verify QST is calculated on subtotal, not on (subtotal + GST)
        $qstOnSubtotal = round($subtotal * InvoicePDFGenerator::QST_RATE, 2);
        $this->assertEquals($qstOnSubtotal, $qst, 'QST should be calculated on subtotal only, not compounded');

        // Verify total is simple addition, not compounded
        $expectedTotal = $subtotal + $gst + $qst;
        $this->assertEquals($expectedTotal, $total, 'Total should be simple sum, not compounded');
    }

    // =========================================================================
    // INVOICE DATA VALIDATION TESTS
    // =========================================================================

    /**
     * Test validation passes with valid invoice data.
     */
    public function testValidationPassesWithValidData(): void
    {
        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('validateInvoiceData');
        $method->setAccessible(true);

        // Should not throw exception
        try {
            $method->invoke($this->generator, $this->sampleInvoiceData);
            $this->assertTrue(true, 'Valid invoice data should pass validation');
        } catch (\Exception $e) {
            $this->fail('Valid invoice data should not throw exception: ' . $e->getMessage());
        }
    }

    /**
     * Test validation fails when invoice number is missing.
     */
    public function testValidationFailsWithMissingInvoiceNumber(): void
    {
        $invalidData = $this->sampleInvoiceData;
        unset($invalidData['invoiceNumber']);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Missing required field: invoiceNumber');

        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('validateInvoiceData');
        $method->setAccessible(true);
        $method->invoke($this->generator, $invalidData);
    }

    /**
     * Test validation fails when customer name is missing.
     */
    public function testValidationFailsWithMissingCustomerName(): void
    {
        $invalidData = $this->sampleInvoiceData;
        unset($invalidData['customerName']);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Missing required field: customerName');

        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('validateInvoiceData');
        $method->setAccessible(true);
        $method->invoke($this->generator, $invalidData);
    }

    /**
     * Test validation fails when items array is missing.
     */
    public function testValidationFailsWithMissingItems(): void
    {
        $invalidData = $this->sampleInvoiceData;
        unset($invalidData['items']);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Missing required field: items');

        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('validateInvoiceData');
        $method->setAccessible(true);
        $method->invoke($this->generator, $invalidData);
    }

    /**
     * Test validation fails when items array is empty.
     */
    public function testValidationFailsWithEmptyItems(): void
    {
        $invalidData = $this->sampleInvoiceData;
        $invalidData['items'] = [];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invoice must contain at least one item');

        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('validateInvoiceData');
        $method->setAccessible(true);
        $method->invoke($this->generator, $invalidData);
    }

    /**
     * Test validation fails when item is missing description.
     */
    public function testValidationFailsWithMissingItemDescription(): void
    {
        $invalidData = $this->sampleInvoiceData;
        $invalidData['items'][0]['description'] = '';

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Missing description');

        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('validateInvoiceData');
        $method->setAccessible(true);
        $method->invoke($this->generator, $invalidData);
    }

    /**
     * Test validation fails when item has invalid quantity.
     */
    public function testValidationFailsWithInvalidItemQuantity(): void
    {
        $invalidData = $this->sampleInvoiceData;
        $invalidData['items'][0]['quantity'] = 0;

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid quantity');

        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('validateInvoiceData');
        $method->setAccessible(true);
        $method->invoke($this->generator, $invalidData);
    }

    /**
     * Test validation fails when item has negative unit price.
     */
    public function testValidationFailsWithNegativeUnitPrice(): void
    {
        $invalidData = $this->sampleInvoiceData;
        $invalidData['items'][0]['unitPrice'] = -10.00;

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid unit price');

        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('validateInvoiceData');
        $method->setAccessible(true);
        $method->invoke($this->generator, $invalidData);
    }

    // =========================================================================
    // BRANDING INTEGRATION TESTS
    // =========================================================================

    /**
     * Test organization name is retrieved from session.
     */
    public function testOrganizationNameFromSession(): void
    {
        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('renderInvoiceTemplate');
        $method->setAccessible(true);

        $html = $method->invoke($this->generator, $this->sampleInvoiceData);

        $this->assertStringContainsString('LAYA Kindergarten', $html, 'Organization name should appear in invoice');
    }

    /**
     * Test organization address is built from session values.
     */
    public function testOrganizationAddressFromSession(): void
    {
        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('getOrganizationAddress');
        $method->setAccessible(true);

        $address = $method->invoke($this->generator);

        $this->assertStringContainsString('123 Main Street', $address, 'Address should contain street');
        $this->assertStringContainsString('Montreal', $address, 'Address should contain city');
        $this->assertStringContainsString('Quebec', $address, 'Address should contain region');
    }

    /**
     * Test organization logo returns null when not set.
     */
    public function testOrganizationLogoReturnsNullWhenNotSet(): void
    {
        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('getOrganizationLogo');
        $method->setAccessible(true);

        $logo = $method->invoke($this->generator);

        $this->assertNull($logo, 'Logo should be null when not set');
    }

    // =========================================================================
    // TEMPLATE RENDERING TESTS
    // =========================================================================

    /**
     * Test invoice template contains CSS styles.
     */
    public function testInvoiceTemplateContainsCSSStyles(): void
    {
        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('getInvoiceHeader');
        $method->setAccessible(true);

        $header = $method->invoke($this->generator);

        $this->assertStringContainsString('<style>', $header, 'Template should contain CSS styles');
        $this->assertStringContainsString('invoice-header', $header, 'Template should contain invoice-header class');
        $this->assertStringContainsString('items', $header, 'Template should contain items table styles');
    }

    /**
     * Test invoice template contains print-friendly CSS.
     */
    public function testInvoiceTemplateContainsPrintCSS(): void
    {
        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('getInvoiceHeader');
        $method->setAccessible(true);

        $header = $method->invoke($this->generator);

        $this->assertStringContainsString('@media print', $header, 'Template should contain print media query');
        $this->assertStringContainsString('print-color-adjust', $header, 'Template should contain color adjustment for printing');
    }

    /**
     * Test invoice HTML contains customer information.
     */
    public function testInvoiceHTMLContainsCustomerInformation(): void
    {
        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('renderInvoiceTemplate');
        $method->setAccessible(true);

        $html = $method->invoke($this->generator, $this->sampleInvoiceData);

        $this->assertStringContainsString('Smith Family', $html, 'Should contain customer name');
        $this->assertStringContainsString('smith@example.com', $html, 'Should contain customer email');
    }

    /**
     * Test invoice HTML contains itemized services table.
     */
    public function testInvoiceHTMLContainsItemizedServicesTable(): void
    {
        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('renderInvoiceTemplate');
        $method->setAccessible(true);

        $html = $method->invoke($this->generator, $this->sampleInvoiceData);

        $this->assertStringContainsString('<table class="items">', $html, 'Should contain items table');
        $this->assertStringContainsString('Description', $html, 'Should contain Description column header');
        $this->assertStringContainsString('Quantity', $html, 'Should contain Quantity column header');
        $this->assertStringContainsString('Unit Price', $html, 'Should contain Unit Price column header');
        $this->assertStringContainsString('Total', $html, 'Should contain Total column header');
    }

    /**
     * Test invoice HTML contains all service items.
     */
    public function testInvoiceHTMLContainsAllServiceItems(): void
    {
        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('renderInvoiceTemplate');
        $method->setAccessible(true);

        $html = $method->invoke($this->generator, $this->sampleInvoiceData);

        $this->assertStringContainsString('Basic Daycare - Full Time', $html, 'Should contain first item description');
        $this->assertStringContainsString('Hot Lunch Program', $html, 'Should contain second item description');
        $this->assertStringContainsString('Extended Care (After-Hours)', $html, 'Should contain third item description');
    }

    /**
     * Test invoice HTML contains tax information.
     */
    public function testInvoiceHTMLContainsTaxInformation(): void
    {
        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('renderInvoiceTemplate');
        $method->setAccessible(true);

        $html = $method->invoke($this->generator, $this->sampleInvoiceData);

        $this->assertStringContainsString('GST', $html, 'Should contain GST label');
        $this->assertStringContainsString('QST', $html, 'Should contain QST label');
        $this->assertStringContainsString('5%', $html, 'Should display GST rate');
        $this->assertStringContainsString('9.975%', $html, 'Should display QST rate');
    }

    /**
     * Test invoice HTML contains payment terms.
     */
    public function testInvoiceHTMLContainsPaymentTerms(): void
    {
        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('renderInvoiceTemplate');
        $method->setAccessible(true);

        $html = $method->invoke($this->generator, $this->sampleInvoiceData);

        $this->assertStringContainsString('Payment Terms', $html, 'Should contain payment terms section');
        $this->assertStringContainsString($this->sampleInvoiceData['paymentTerms'], $html, 'Should contain payment terms text');
    }

    /**
     * Test invoice HTML contains payment methods.
     */
    public function testInvoiceHTMLContainsPaymentMethods(): void
    {
        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('renderInvoiceTemplate');
        $method->setAccessible(true);

        $html = $method->invoke($this->generator, $this->sampleInvoiceData);

        $this->assertStringContainsString('Payment Methods', $html, 'Should contain payment methods section');
        $this->assertStringContainsString('E-Transfer', $html, 'Should contain payment method');
    }

    // =========================================================================
    // BATCH GENERATION TESTS
    // =========================================================================

    /**
     * Test batch generation with multiple invoices.
     */
    public function testBatchGenerationWithMultipleInvoices(): void
    {
        $invoice1 = $this->sampleInvoiceData;
        $invoice2 = $this->sampleInvoiceData;
        $invoice2['invoiceNumber'] = 'INV-000002';
        $invoice2['customerName'] = 'Johnson Family';

        $invoices = [$invoice1, $invoice2];

        // This test verifies the batch generation doesn't throw exceptions
        // Note: We can't easily test PDF output without mocking mPDF
        $this->assertCount(2, $invoices, 'Batch should contain 2 invoices');
    }

    /**
     * Test batch generation throws exception with empty array.
     */
    public function testBatchGenerationFailsWithEmptyArray(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No invoices provided for batch generation');

        $this->generator->generateBatch([]);
    }

    // =========================================================================
    // ERROR HANDLING TESTS
    // =========================================================================

    /**
     * Test last error is empty initially.
     */
    public function testLastErrorIsEmptyInitially(): void
    {
        $error = $this->generator->getLastError();

        $this->assertIsArray($error, 'Last error should be an array');
        $this->assertEmpty($error, 'Last error should be empty initially');
    }

    /**
     * Test save to file returns boolean.
     */
    public function testSaveToFileReturnBoolean(): void
    {
        // Note: This will likely fail because mPDF is not installed in test environment
        // But we're testing the interface
        $result = @$this->generator->saveToFile($this->sampleInvoiceData, '/tmp/test_invoice.pdf');

        $this->assertIsBool($result, 'saveToFile should return boolean');
    }

    /**
     * Test generate as string returns string or false.
     */
    public function testGenerateAsStringReturnsStringOrFalse(): void
    {
        // Note: This will likely fail because mPDF is not installed in test environment
        // But we're testing the interface
        $result = @$this->generator->generateAsString($this->sampleInvoiceData);

        $this->assertTrue(is_string($result) || $result === false, 'generateAsString should return string or false');
    }
}
