<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright (c) 2010, Gibbon Foundation
Gibbon(tm), Gibbon Education Ltd. (Hong Kong)

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

namespace Gibbon\Module\MedicalProtocol\Tests;

use PHPUnit\Framework\TestCase;
use Gibbon\Module\MedicalProtocol\Domain\DoseValidator;

/**
 * Unit tests for DoseValidator.
 *
 * These tests verify that the DoseValidator properly implements Quebec FO-0647
 * acetaminophen dosing protocol requirements, including weight-based dose calculation,
 * safety validation, overdose risk detection, and age-based restrictions.
 *
 * @covers \Gibbon\Module\MedicalProtocol\Domain\DoseValidator
 */
class DoseValidatorTest extends TestCase
{
    // =========================================================================
    // CONSTANT VALIDATION TESTS
    // =========================================================================

    /**
     * @test
     */
    public function hasCorrectWeightConstants(): void
    {
        $this->assertEquals(4.3, DoseValidator::MIN_WEIGHT_KG);
        $this->assertEquals(35.0, DoseValidator::MAX_WEIGHT_KG);
    }

    /**
     * @test
     */
    public function hasCorrectDosingConstants(): void
    {
        $this->assertEquals(10.0, DoseValidator::MIN_MG_PER_KG);
        $this->assertEquals(15.0, DoseValidator::MAX_MG_PER_KG);
        $this->assertEquals(5, DoseValidator::MAX_DAILY_DOSES);
        $this->assertEquals(4, DoseValidator::MIN_INTERVAL_HOURS);
    }

    /**
     * @test
     */
    public function hasCorrectConcentrationConstants(): void
    {
        $this->assertEquals('80mg/mL', DoseValidator::CONCENTRATION_INFANT_DROPS);
        $this->assertEquals('160mg/5mL', DoseValidator::CONCENTRATION_CHILDRENS_SUSPENSION);
        $this->assertEquals('325mg', DoseValidator::CONCENTRATION_TABLET_325MG);
        $this->assertEquals('500mg', DoseValidator::CONCENTRATION_TABLET_500MG);
    }

    /**
     * @test
     */
    public function hasCorrectAgeRestrictionConstants(): void
    {
        $this->assertEquals(0, DoseValidator::MIN_AGE_MONTHS_DROPS);
        $this->assertEquals(3, DoseValidator::MIN_AGE_MONTHS_SUSPENSION);
        $this->assertEquals(72, DoseValidator::MIN_AGE_MONTHS_TABLETS);
    }

    // =========================================================================
    // CONCENTRATION VALIDATION TESTS
    // =========================================================================

    /**
     * @test
     */
    public function getValidConcentrationsReturnsCorrectList(): void
    {
        $concentrations = DoseValidator::getValidConcentrations();

        $this->assertIsArray($concentrations);
        $this->assertCount(4, $concentrations);
        $this->assertContains('80mg/mL', $concentrations);
        $this->assertContains('160mg/5mL', $concentrations);
        $this->assertContains('325mg', $concentrations);
        $this->assertContains('500mg', $concentrations);
    }

    /**
     * @test
     * @dataProvider validConcentrationProvider
     */
    public function isValidConcentrationReturnsTrueForValidConcentrations(string $concentration): void
    {
        $this->assertTrue(
            DoseValidator::isValidConcentration($concentration),
            sprintf('Concentration %s should be valid', $concentration)
        );
    }

    public static function validConcentrationProvider(): array
    {
        return [
            'Infant drops' => ['80mg/mL'],
            'Childrens suspension' => ['160mg/5mL'],
            'Tablet 325mg' => ['325mg'],
            'Tablet 500mg' => ['500mg'],
        ];
    }

    /**
     * @test
     * @dataProvider invalidConcentrationProvider
     */
    public function isValidConcentrationReturnsFalseForInvalidConcentrations(string $concentration): void
    {
        $this->assertFalse(
            DoseValidator::isValidConcentration($concentration),
            sprintf('Concentration %s should be invalid', $concentration)
        );
    }

    public static function invalidConcentrationProvider(): array
    {
        return [
            'Empty string' => [''],
            'Wrong format' => ['100mg/mL'],
            'Random string' => ['invalid'],
            'Numeric' => ['123'],
        ];
    }

    // =========================================================================
    // DOSE CALCULATION TESTS
    // =========================================================================

    /**
     * @test
     * @dataProvider mgPerKgCalculationProvider
     */
    public function calculateMgPerKgReturnsCorrectValue(float $weightKg, float $doseMg, float $expected): void
    {
        $result = DoseValidator::calculateMgPerKg($weightKg, $doseMg);
        $this->assertEquals($expected, $result);
    }

    public static function mgPerKgCalculationProvider(): array
    {
        return [
            'Standard dose' => [10.0, 120.0, 12.0],
            'Upper limit' => [10.0, 150.0, 15.0],
            'Lower limit' => [10.0, 100.0, 10.0],
            'Fractional result' => [8.5, 110.0, 12.94],
            'Zero weight' => [0.0, 100.0, 0.0],
        ];
    }

    // =========================================================================
    // OVERDOSE RISK TESTS
    // =========================================================================

    /**
     * @test
     * @dataProvider overdoseRiskProvider
     */
    public function isOverdoseRiskDetectsOverdoseCorrectly(float $weightKg, float $doseMg, bool $expected): void
    {
        $result = DoseValidator::isOverdoseRisk($weightKg, $doseMg);
        $this->assertEquals(
            $expected,
            $result,
            sprintf(
                'Weight %.1fkg with dose %.1fmg should %s be overdose risk',
                $weightKg,
                $doseMg,
                $expected ? '' : 'NOT'
            )
        );
    }

    public static function overdoseRiskProvider(): array
    {
        return [
            'Safe dose at midpoint' => [10.0, 125.0, false],
            'Safe dose at maximum' => [10.0, 150.0, false],
            'Overdose by 1mg' => [10.0, 151.0, true],
            'Significant overdose' => [10.0, 200.0, true],
            'Low weight safe dose' => [5.0, 75.0, false],
            'Low weight overdose' => [5.0, 76.0, true],
            'High weight safe dose' => [30.0, 450.0, false],
            'High weight overdose' => [30.0, 451.0, true],
        ];
    }

    // =========================================================================
    // RECOMMENDED DOSE TESTS
    // =========================================================================

    /**
     * @test
     */
    public function getRecommendedDoseReturnsNullForWeightBelowMinimum(): void
    {
        $result = DoseValidator::getRecommendedDose(4.0, '80mg/mL');
        $this->assertNull($result);
    }

    /**
     * @test
     */
    public function getRecommendedDoseReturnsNullForWeightAboveMaximum(): void
    {
        $result = DoseValidator::getRecommendedDose(40.0, '160mg/5mL');
        $this->assertNull($result);
    }

    /**
     * @test
     */
    public function getRecommendedDoseReturnsNullForUnavailableConcentration(): void
    {
        // Infant drops not available for higher weights
        $result = DoseValidator::getRecommendedDose(25.0, '80mg/mL');
        $this->assertNull($result);
    }

    /**
     * @test
     * @dataProvider recommendedDoseProvider
     */
    public function getRecommendedDoseReturnsCorrectDoseForValidInputs(
        float $weightKg,
        string $concentration,
        string $expectedAmount,
        float $expectedMg
    ): void {
        $result = DoseValidator::getRecommendedDose($weightKg, $concentration);

        $this->assertIsArray($result);
        $this->assertEquals($concentration, $result['concentration']);
        $this->assertEquals($expectedAmount, $result['amount']);
        $this->assertEquals($expectedMg, $result['mg']);
        $this->assertArrayHasKey('weightMinKg', $result);
        $this->assertArrayHasKey('weightMaxKg', $result);
        $this->assertArrayHasKey('mgPerKg', $result);
    }

    public static function recommendedDoseProvider(): array
    {
        return [
            'Infant 4.3kg drops' => [4.3, '80mg/mL', '0.6 mL', 48.0],
            'Infant 5.0kg drops' => [5.0, '80mg/mL', '0.6 mL', 48.0],
            'Toddler 8kg drops' => [8.0, '80mg/mL', '1.2 mL', 96.0],
            'Toddler 8kg suspension' => [8.0, '160mg/5mL', '3 mL', 96.0],
            'Child 20kg suspension' => [20.0, '160mg/5mL', '6 mL', 192.0],
            'Child 20kg tablet' => [20.0, '325mg', '½ tablet', 162.5],
            'Older child 32kg suspension' => [32.0, '160mg/5mL', '12 mL', 384.0],
            'Older child 32kg tablet 325mg' => [32.0, '325mg', '1 tablet', 325.0],
            'Older child 32kg tablet 500mg' => [32.0, '500mg', '1 tablet', 500.0],
        ];
    }

    // =========================================================================
    // AVAILABLE DOSES TESTS
    // =========================================================================

    /**
     * @test
     */
    public function getAvailableDosesReturnsEmptyArrayForOutOfRangeWeight(): void
    {
        $result = DoseValidator::getAvailableDoses(3.0);
        $this->assertIsArray($result);
        $this->assertEmpty($result);

        $result = DoseValidator::getAvailableDoses(40.0);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * @test
     */
    public function getAvailableDosesReturnsMultipleOptionsForInfantWeight(): void
    {
        $result = DoseValidator::getAvailableDoses(5.0);

        $this->assertIsArray($result);
        $this->assertCount(2, $result); // Drops and suspension available

        // Check structure
        $this->assertArrayHasKey('concentration', $result[0]);
        $this->assertArrayHasKey('amount', $result[0]);
        $this->assertArrayHasKey('mg', $result[0]);
        $this->assertArrayHasKey('mgPerKg', $result[0]);
    }

    /**
     * @test
     */
    public function getAvailableDosesReturnsThreeOptionsForHigherWeight(): void
    {
        $result = DoseValidator::getAvailableDoses(32.0);

        $this->assertIsArray($result);
        $this->assertCount(3, $result); // Suspension, 325mg tablet, and 500mg tablet

        $concentrations = array_column($result, 'concentration');
        $this->assertContains('160mg/5mL', $concentrations);
        $this->assertContains('325mg', $concentrations);
        $this->assertContains('500mg', $concentrations);
    }

    // =========================================================================
    // DOSE VALIDATION TESTS
    // =========================================================================

    /**
     * @test
     */
    public function validateDoseRejectsWeightBelowMinimum(): void
    {
        $result = DoseValidator::validateDose(4.0, 48.0, '80mg/mL');

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('below minimum', $result['errors'][0]);
    }

    /**
     * @test
     */
    public function validateDoseRejectsWeightAboveMaximum(): void
    {
        $result = DoseValidator::validateDose(40.0, 400.0, '160mg/5mL');

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('exceeds maximum', $result['errors'][0]);
    }

    /**
     * @test
     */
    public function validateDoseRejectsInvalidConcentration(): void
    {
        $result = DoseValidator::validateDose(10.0, 120.0, 'invalid');

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('Invalid concentration', $result['errors'][0]);
    }

    /**
     * @test
     */
    public function validateDoseRejectsUnavailableConcentrationForWeightRange(): void
    {
        // 80mg/mL drops not available for weight 25kg
        $result = DoseValidator::validateDose(25.0, 256.0, '80mg/mL');

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('not recommended for weight', $result['errors'][0]);
    }

    /**
     * @test
     */
    public function validateDoseRejectsDoseBelowMinimum(): void
    {
        // 10kg child needs minimum ~90mg (10kg * 10mg/kg * 0.9 tolerance)
        $result = DoseValidator::validateDose(10.0, 80.0, '160mg/5mL');

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('too low', $result['errors'][0]);
    }

    /**
     * @test
     */
    public function validateDoseRejectsOverdoseRisk(): void
    {
        // 10kg child maximum safe dose is 150mg (10kg * 15mg/kg)
        $result = DoseValidator::validateDose(10.0, 160.0, '160mg/5mL');

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('OVERDOSE RISK', $result['errors'][0]);
    }

    /**
     * @test
     */
    public function validateDoseRejectsViolationOfAgeRestrictions(): void
    {
        // 2-month-old cannot receive suspension (minimum 3 months)
        $result = DoseValidator::validateDose(5.0, 48.0, '160mg/5mL', 2);

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('below minimum age', $result['errors'][0]);
    }

    /**
     * @test
     */
    public function validateDoseRejectsTabletsForYoungChildren(): void
    {
        // 5-year-old (60 months) cannot receive tablets (minimum 6 years = 72 months)
        $result = DoseValidator::validateDose(20.0, 162.5, '325mg', 60);

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('below minimum age', $result['errors'][0]);
    }

    /**
     * @test
     */
    public function validateDoseAcceptsValidDose(): void
    {
        $result = DoseValidator::validateDose(10.0, 120.0, '160mg/5mL');

        $this->assertTrue($result['valid'], 'Valid dose should be accepted');
        $this->assertEmpty($result['errors']);
        $this->assertArrayHasKey('recommendedRange', $result);
        $this->assertArrayHasKey('warnings', $result);
    }

    /**
     * @test
     */
    public function validateDoseReturnsRecommendedRange(): void
    {
        $result = DoseValidator::validateDose(10.0, 120.0, '160mg/5mL');

        $this->assertArrayHasKey('recommendedRange', $result);
        $this->assertArrayHasKey('minMg', $result['recommendedRange']);
        $this->assertArrayHasKey('maxMg', $result['recommendedRange']);
        $this->assertEquals(100.0, $result['recommendedRange']['minMg']); // 10kg * 10mg/kg
        $this->assertEquals(150.0, $result['recommendedRange']['maxMg']); // 10kg * 15mg/kg
    }

    /**
     * @test
     */
    public function validateDoseGeneratesWarningForUpperLimitDose(): void
    {
        // 10kg child with 140mg dose (14 mg/kg - at upper limit)
        $result = DoseValidator::validateDose(10.0, 140.0, '160mg/5mL');

        $this->assertTrue($result['valid']);
        $this->assertNotEmpty($result['warnings']);
        $this->assertStringContainsString('upper limit', $result['warnings'][0]);
    }

    /**
     * @test
     */
    public function validateDoseGeneratesWarningForLowerLimitDose(): void
    {
        // 10kg child with 105mg dose (10.5 mg/kg - at lower limit)
        $result = DoseValidator::validateDose(10.0, 105.0, '160mg/5mL');

        $this->assertTrue($result['valid']);
        $this->assertNotEmpty($result['warnings']);
        $this->assertStringContainsString('lower limit', $result['warnings'][0]);
    }

    /**
     * @test
     */
    public function validateDoseAcceptsValidAgeForConcentration(): void
    {
        // 6-month-old can receive suspension
        $result = DoseValidator::validateDose(8.0, 96.0, '160mg/5mL', 6);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    /**
     * @test
     */
    public function validateDoseAcceptsTabletsForOlderChildren(): void
    {
        // 7-year-old (84 months) can receive tablets
        $result = DoseValidator::validateDose(25.0, 325.0, '325mg', 84);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    // =========================================================================
    // DOSING TABLE TESTS
    // =========================================================================

    /**
     * @test
     */
    public function getDosingTableReturnsCompleteTable(): void
    {
        $table = DoseValidator::getDosingTable();

        $this->assertIsArray($table);
        $this->assertCount(8, $table, 'Should have 8 weight ranges');

        // Verify structure of first entry
        $this->assertArrayHasKey('weightMinKg', $table[0]);
        $this->assertArrayHasKey('weightMaxKg', $table[0]);
        $this->assertArrayHasKey('doses', $table[0]);
        $this->assertIsArray($table[0]['doses']);
    }

    /**
     * @test
     */
    public function dosingTableCoversCompleteWeightRange(): void
    {
        $table = DoseValidator::getDosingTable();

        // First entry should start at minimum weight
        $this->assertEquals(
            DoseValidator::MIN_WEIGHT_KG,
            $table[0]['weightMinKg'],
            'Table should start at minimum weight'
        );

        // Last entry should end at maximum weight
        $lastIndex = count($table) - 1;
        $this->assertEquals(
            DoseValidator::MAX_WEIGHT_KG,
            $table[$lastIndex]['weightMaxKg'],
            'Table should end at maximum weight'
        );
    }

    /**
     * @test
     */
    public function dosingTableHasContiguousWeightRanges(): void
    {
        $table = DoseValidator::getDosingTable();

        for ($i = 0; $i < count($table) - 1; $i++) {
            $currentMax = $table[$i]['weightMaxKg'];
            $nextMin = $table[$i + 1]['weightMinKg'];

            // Next range should start within 0.1kg of where previous ended
            $this->assertLessThanOrEqual(
                0.1,
                abs($nextMin - $currentMax - 0.1),
                sprintf(
                    'Weight ranges should be contiguous between entries %d and %d',
                    $i,
                    $i + 1
                )
            );
        }
    }

    /**
     * @test
     */
    public function dosingTableEntriesHaveValidDosing(): void
    {
        $table = DoseValidator::getDosingTable();

        foreach ($table as $index => $entry) {
            $this->assertArrayHasKey('doses', $entry, "Entry $index should have doses");
            $this->assertNotEmpty($entry['doses'], "Entry $index should have at least one dose");

            foreach ($entry['doses'] as $concentration => $dose) {
                $this->assertArrayHasKey(
                    'amount',
                    $dose,
                    "Entry $index concentration $concentration should have amount"
                );
                $this->assertArrayHasKey(
                    'mg',
                    $dose,
                    "Entry $index concentration $concentration should have mg"
                );
                $this->assertGreaterThan(
                    0,
                    $dose['mg'],
                    "Entry $index concentration $concentration should have positive mg value"
                );
            }
        }
    }

    // =========================================================================
    // INTEGRATION TESTS
    // =========================================================================

    /**
     * @test
     */
    public function completeWorkflowForInfantDosing(): void
    {
        $weightKg = 5.0;
        $ageMonths = 1;

        // Get available doses
        $availableDoses = DoseValidator::getAvailableDoses($weightKg);
        $this->assertNotEmpty($availableDoses);

        // Get recommended dose for drops
        $recommended = DoseValidator::getRecommendedDose($weightKg, '80mg/mL');
        $this->assertNotNull($recommended);
        $this->assertEquals(48.0, $recommended['mg']);

        // Validate the recommended dose
        $validation = DoseValidator::validateDose($weightKg, 48.0, '80mg/mL', $ageMonths);
        $this->assertTrue($validation['valid']);
        $this->assertEmpty($validation['errors']);
    }

    /**
     * @test
     */
    public function completeWorkflowForChildDosing(): void
    {
        $weightKg = 20.0;
        $ageMonths = 48; // 4 years old

        // Get available doses
        $availableDoses = DoseValidator::getAvailableDoses($weightKg);
        $this->assertCount(2, $availableDoses); // Suspension and 325mg tablet

        // Get recommended dose for suspension
        $recommended = DoseValidator::getRecommendedDose($weightKg, '160mg/5mL');
        $this->assertNotNull($recommended);
        $this->assertEquals(192.0, $recommended['mg']);

        // Validate the recommended dose
        $validation = DoseValidator::validateDose($weightKg, 192.0, '160mg/5mL', $ageMonths);
        $this->assertTrue($validation['valid']);
        $this->assertEmpty($validation['errors']);

        // Verify overdose detection works
        $this->assertFalse(DoseValidator::isOverdoseRisk($weightKg, 192.0));
        $this->assertTrue(DoseValidator::isOverdoseRisk($weightKg, 350.0));
    }
}
