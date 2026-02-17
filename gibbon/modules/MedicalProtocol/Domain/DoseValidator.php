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

namespace Gibbon\Module\MedicalProtocol\Domain;

/**
 * Dose Validator for Quebec FO-0647 Acetaminophen Protocol
 *
 * Implements weight-based dose validation for acetaminophen administration
 * per Quebec childcare regulations (FO-0647). Provides dose calculation,
 * safety validation, and overdose risk detection.
 *
 * Quebec FO-0647 Requirements:
 * - Weight range: 4.3kg - 35kg
 * - Dosing: 10-15 mg/kg per dose
 * - Concentrations: 80mg/mL (drops), 160mg/5mL (suspension), 325mg/500mg (tablets)
 * - Maximum 5 doses per 24 hours
 * - Minimum 4-6 hour interval between doses
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class DoseValidator
{
    // Quebec FO-0647 Constants
    const MIN_WEIGHT_KG = 4.3;
    const MAX_WEIGHT_KG = 35.0;
    const MIN_MG_PER_KG = 10.0;
    const MAX_MG_PER_KG = 15.0;
    const MAX_DAILY_DOSES = 5;
    const MIN_INTERVAL_HOURS = 4;

    // Concentration types
    const CONCENTRATION_INFANT_DROPS = '80mg/mL';
    const CONCENTRATION_CHILDRENS_SUSPENSION = '160mg/5mL';
    const CONCENTRATION_TABLET_325MG = '325mg';
    const CONCENTRATION_TABLET_500MG = '500mg';

    // Age restrictions (in months)
    const MIN_AGE_MONTHS_DROPS = 0;
    const MIN_AGE_MONTHS_SUSPENSION = 3;
    const MIN_AGE_MONTHS_TABLETS = 72; // 6 years

    /**
     * Quebec FO-0647 Dosing Table
     *
     * Based on official Quebec protocol for acetaminophen dosing.
     * Each entry defines weight range and appropriate doses for each concentration.
     *
     * @var array
     */
    private static $dosingTable = [
        [
            'weightMinKg' => 4.3,
            'weightMaxKg' => 5.4,
            'doses' => [
                '80mg/mL' => ['amount' => '0.6 mL', 'mg' => 48],
                '160mg/5mL' => ['amount' => '1.5 mL', 'mg' => 48],
            ]
        ],
        [
            'weightMinKg' => 5.5,
            'weightMaxKg' => 7.9,
            'doses' => [
                '80mg/mL' => ['amount' => '0.8 mL', 'mg' => 64],
                '160mg/5mL' => ['amount' => '2 mL', 'mg' => 64],
            ]
        ],
        [
            'weightMinKg' => 8.0,
            'weightMaxKg' => 10.9,
            'doses' => [
                '80mg/mL' => ['amount' => '1.2 mL', 'mg' => 96],
                '160mg/5mL' => ['amount' => '3 mL', 'mg' => 96],
            ]
        ],
        [
            'weightMinKg' => 11.0,
            'weightMaxKg' => 15.9,
            'doses' => [
                '80mg/mL' => ['amount' => '1.6 mL', 'mg' => 128],
                '160mg/5mL' => ['amount' => '4 mL', 'mg' => 128],
            ]
        ],
        [
            'weightMinKg' => 16.0,
            'weightMaxKg' => 21.9,
            'doses' => [
                '80mg/mL' => ['amount' => '2.4 mL', 'mg' => 192],
                '160mg/5mL' => ['amount' => '6 mL', 'mg' => 192],
                '325mg' => ['amount' => '½ tablet', 'mg' => 162.5],
            ]
        ],
        [
            'weightMinKg' => 22.0,
            'weightMaxKg' => 26.9,
            'doses' => [
                '160mg/5mL' => ['amount' => '8 mL', 'mg' => 256],
                '325mg' => ['amount' => '1 tablet', 'mg' => 325],
            ]
        ],
        [
            'weightMinKg' => 27.0,
            'weightMaxKg' => 31.9,
            'doses' => [
                '160mg/5mL' => ['amount' => '10 mL', 'mg' => 320],
                '325mg' => ['amount' => '1 tablet', 'mg' => 325],
            ]
        ],
        [
            'weightMinKg' => 32.0,
            'weightMaxKg' => 35.0,
            'doses' => [
                '160mg/5mL' => ['amount' => '12 mL', 'mg' => 384],
                '325mg' => ['amount' => '1 tablet', 'mg' => 325],
                '500mg' => ['amount' => '1 tablet', 'mg' => 500],
            ]
        ],
    ];

    /**
     * Validate a proposed dose against Quebec FO-0647 requirements.
     *
     * @param float $weightKg Child's weight in kilograms
     * @param float $doseMg Proposed dose in milligrams
     * @param string $concentration Medication concentration
     * @param int|null $ageMonths Optional age in months for age-based restrictions
     * @return array Validation result with 'valid' boolean and 'errors' array
     */
    public static function validateDose($weightKg, $doseMg, $concentration, $ageMonths = null)
    {
        $errors = [];

        // Validate weight range
        if ($weightKg < self::MIN_WEIGHT_KG) {
            $errors[] = sprintf(
                'Weight %.1fkg is below minimum %.1fkg for acetaminophen protocol',
                $weightKg,
                self::MIN_WEIGHT_KG
            );
        }

        if ($weightKg > self::MAX_WEIGHT_KG) {
            $errors[] = sprintf(
                'Weight %.1fkg exceeds maximum %.1fkg for acetaminophen protocol',
                $weightKg,
                self::MAX_WEIGHT_KG
            );
        }

        // Validate concentration
        if (!self::isValidConcentration($concentration)) {
            $errors[] = sprintf(
                'Invalid concentration "%s". Must be one of: %s',
                $concentration,
                implode(', ', self::getValidConcentrations())
            );
        }

        // Validate age restrictions if age provided
        if ($ageMonths !== null) {
            $ageError = self::validateAgeRestriction($concentration, $ageMonths);
            if ($ageError) {
                $errors[] = $ageError;
            }
        }

        // Calculate recommended dose range
        $minRecommendedMg = $weightKg * self::MIN_MG_PER_KG;
        $maxRecommendedMg = $weightKg * self::MAX_MG_PER_KG;

        // Check if dose is too low
        if ($doseMg < $minRecommendedMg * 0.9) { // Allow 10% tolerance
            $errors[] = sprintf(
                'Dose %.1fmg is too low for weight %.1fkg. Recommended range: %.1f-%.1fmg',
                $doseMg,
                $weightKg,
                $minRecommendedMg,
                $maxRecommendedMg
            );
        }

        // Check for overdose risk
        if ($doseMg > $maxRecommendedMg) {
            $errors[] = sprintf(
                'OVERDOSE RISK: Dose %.1fmg exceeds maximum %.1fmg for weight %.1fkg (%.1f mg/kg)',
                $doseMg,
                $maxRecommendedMg,
                $weightKg,
                $doseMg / $weightKg
            );
        }

        // Validate against dosing table
        $tableValidation = self::validateAgainstDosingTable($weightKg, $doseMg, $concentration);
        if (!$tableValidation['valid']) {
            $errors = array_merge($errors, $tableValidation['errors']);
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => self::generateWarnings($weightKg, $doseMg, $concentration),
            'recommendedRange' => [
                'minMg' => round($minRecommendedMg, 1),
                'maxMg' => round($maxRecommendedMg, 1),
            ],
        ];
    }

    /**
     * Calculate recommended dose for a given weight and concentration.
     *
     * @param float $weightKg Child's weight in kilograms
     * @param string $concentration Medication concentration
     * @return array|null Dose information or null if not found
     */
    public static function getRecommendedDose($weightKg, $concentration)
    {
        if ($weightKg < self::MIN_WEIGHT_KG || $weightKg > self::MAX_WEIGHT_KG) {
            return null;
        }

        foreach (self::$dosingTable as $range) {
            if ($weightKg >= $range['weightMinKg'] && $weightKg <= $range['weightMaxKg']) {
                if (isset($range['doses'][$concentration])) {
                    $dose = $range['doses'][$concentration];
                    return [
                        'weightMinKg' => $range['weightMinKg'],
                        'weightMaxKg' => $range['weightMaxKg'],
                        'concentration' => $concentration,
                        'amount' => $dose['amount'],
                        'mg' => $dose['mg'],
                        'mgPerKg' => round($dose['mg'] / $weightKg, 2),
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Get all available doses for a given weight.
     *
     * @param float $weightKg Child's weight in kilograms
     * @return array Array of dose options for each available concentration
     */
    public static function getAvailableDoses($weightKg)
    {
        $doses = [];

        foreach (self::$dosingTable as $range) {
            if ($weightKg >= $range['weightMinKg'] && $weightKg <= $range['weightMaxKg']) {
                foreach ($range['doses'] as $concentration => $dose) {
                    $doses[] = [
                        'concentration' => $concentration,
                        'amount' => $dose['amount'],
                        'mg' => $dose['mg'],
                        'mgPerKg' => round($dose['mg'] / $weightKg, 2),
                    ];
                }
                break;
            }
        }

        return $doses;
    }

    /**
     * Check if a dose poses an overdose risk.
     *
     * @param float $weightKg Child's weight in kilograms
     * @param float $doseMg Proposed dose in milligrams
     * @return bool True if overdose risk detected
     */
    public static function isOverdoseRisk($weightKg, $doseMg)
    {
        $maxSafeDose = $weightKg * self::MAX_MG_PER_KG;
        return $doseMg > $maxSafeDose;
    }

    /**
     * Calculate mg/kg for a given dose and weight.
     *
     * @param float $weightKg Child's weight in kilograms
     * @param float $doseMg Dose in milligrams
     * @return float Dose in mg/kg
     */
    public static function calculateMgPerKg($weightKg, $doseMg)
    {
        if ($weightKg <= 0) {
            return 0;
        }
        return round($doseMg / $weightKg, 2);
    }

    /**
     * Validate that a concentration is supported.
     *
     * @param string $concentration Concentration to validate
     * @return bool True if valid
     */
    public static function isValidConcentration($concentration)
    {
        return in_array($concentration, self::getValidConcentrations());
    }

    /**
     * Get list of valid concentrations.
     *
     * @return array Array of valid concentration strings
     */
    public static function getValidConcentrations()
    {
        return [
            self::CONCENTRATION_INFANT_DROPS,
            self::CONCENTRATION_CHILDRENS_SUSPENSION,
            self::CONCENTRATION_TABLET_325MG,
            self::CONCENTRATION_TABLET_500MG,
        ];
    }

    /**
     * Validate age restrictions for concentration types.
     *
     * @param string $concentration Medication concentration
     * @param int $ageMonths Child's age in months
     * @return string|null Error message if age restriction violated, null otherwise
     */
    private static function validateAgeRestriction($concentration, $ageMonths)
    {
        $restrictions = [
            self::CONCENTRATION_INFANT_DROPS => self::MIN_AGE_MONTHS_DROPS,
            self::CONCENTRATION_CHILDRENS_SUSPENSION => self::MIN_AGE_MONTHS_SUSPENSION,
            self::CONCENTRATION_TABLET_325MG => self::MIN_AGE_MONTHS_TABLETS,
            self::CONCENTRATION_TABLET_500MG => self::MIN_AGE_MONTHS_TABLETS,
        ];

        if (!isset($restrictions[$concentration])) {
            return null;
        }

        $minAge = $restrictions[$concentration];
        if ($ageMonths < $minAge) {
            $ageYears = floor($minAge / 12);
            $ageMonthsRemainder = $minAge % 12;
            $ageStr = $ageYears > 0 ? $ageYears . ' years' : $ageMonthsRemainder . ' months';

            return sprintf(
                'Child age %d months is below minimum age %s for concentration %s',
                $ageMonths,
                $ageStr,
                $concentration
            );
        }

        return null;
    }

    /**
     * Validate dose against Quebec FO-0647 dosing table.
     *
     * @param float $weightKg Child's weight in kilograms
     * @param float $doseMg Proposed dose in milligrams
     * @param string $concentration Medication concentration
     * @return array Validation result
     */
    private static function validateAgainstDosingTable($weightKg, $doseMg, $concentration)
    {
        $errors = [];
        $tableEntry = null;

        foreach (self::$dosingTable as $range) {
            if ($weightKg >= $range['weightMinKg'] && $weightKg <= $range['weightMaxKg']) {
                $tableEntry = $range;
                break;
            }
        }

        if (!$tableEntry) {
            $errors[] = sprintf(
                'No dosing table entry found for weight %.1fkg',
                $weightKg
            );
            return ['valid' => false, 'errors' => $errors];
        }

        // Check if concentration is available for this weight range
        if (!isset($tableEntry['doses'][$concentration])) {
            $availableConcentrations = array_keys($tableEntry['doses']);
            $errors[] = sprintf(
                'Concentration %s not recommended for weight %.1fkg. Available: %s',
                $concentration,
                $weightKg,
                implode(', ', $availableConcentrations)
            );
        }

        return ['valid' => empty($errors), 'errors' => $errors];
    }

    /**
     * Generate warnings for borderline doses.
     *
     * @param float $weightKg Child's weight in kilograms
     * @param float $doseMg Proposed dose in milligrams
     * @param string $concentration Medication concentration
     * @return array Array of warning messages
     */
    private static function generateWarnings($weightKg, $doseMg, $concentration)
    {
        $warnings = [];
        $mgPerKg = self::calculateMgPerKg($weightKg, $doseMg);

        // Warn if dose is at upper limit
        if ($mgPerKg >= 14.0 && $mgPerKg <= self::MAX_MG_PER_KG) {
            $warnings[] = sprintf(
                'Dose %.1fmg (%.1f mg/kg) is at upper limit. Monitor closely.',
                $doseMg,
                $mgPerKg
            );
        }

        // Warn if dose is at lower limit
        if ($mgPerKg >= self::MIN_MG_PER_KG && $mgPerKg <= 11.0) {
            $warnings[] = sprintf(
                'Dose %.1fmg (%.1f mg/kg) is at lower limit. May be less effective.',
                $doseMg,
                $mgPerKg
            );
        }

        return $warnings;
    }

    /**
     * Get the complete dosing table.
     *
     * @return array Complete Quebec FO-0647 dosing table
     */
    public static function getDosingTable()
    {
        return self::$dosingTable;
    }
}
