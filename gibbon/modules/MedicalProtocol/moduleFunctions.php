<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuiber and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Gibbon Core) and Gibbon LAYA are trademarks of Gibbon Education Ltd.

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

/**
 * Medical Protocol Module Functions
 *
 * Helper functions for the Medical Protocol module including dosing calculations,
 * validation, and display formatting for Quebec-mandated protocols:
 * - Acetaminophen (FO-0647)
 * - Insect Repellent (FO-0646)
 *
 * @version v1.0.00
 * @since   v1.0.00
 */

/**
 * Calculate acetaminophen dose based on weight and concentration.
 * Follows the 10-15 mg/kg guideline per Quebec FO-0647.
 *
 * @param float $weightKg Child's weight in kilograms
 * @param string $concentration Medication concentration (e.g., '80mg/mL', '80mg/5mL', '160mg/5mL')
 * @return array ['doseMinMg' => float, 'doseMaxMg' => float, 'doseRecommendedMg' => float, 'volumeMin' => float, 'volumeMax' => float, 'volumeRecommended' => float, 'unit' => string]
 */
function getAcetaminophenDose($weightKg, $concentration)
{
    // Validate weight
    if (!validateWeight($weightKg)) {
        return [
            'error' => true,
            'message' => __('Weight must be between 2 and 50 kg'),
        ];
    }

    // Calculate mg doses using 10-15 mg/kg guideline
    $doseMinMg = round($weightKg * 10, 2);
    $doseMaxMg = round($weightKg * 15, 2);
    $doseRecommendedMg = round($weightKg * 12.5, 2); // Middle of range

    // Parse concentration to calculate volume
    $concentrationInfo = parseConcentration($concentration);
    if (!$concentrationInfo) {
        return [
            'doseMinMg' => $doseMinMg,
            'doseMaxMg' => $doseMaxMg,
            'doseRecommendedMg' => $doseRecommendedMg,
            'error' => false,
            'volumeMin' => null,
            'volumeMax' => null,
            'volumeRecommended' => null,
            'unit' => 'mL',
        ];
    }

    // Calculate volumes
    $volumeMin = round($doseMinMg / $concentrationInfo['mgPerMl'], 2);
    $volumeMax = round($doseMaxMg / $concentrationInfo['mgPerMl'], 2);
    $volumeRecommended = round($doseRecommendedMg / $concentrationInfo['mgPerMl'], 2);

    return [
        'doseMinMg' => $doseMinMg,
        'doseMaxMg' => $doseMaxMg,
        'doseRecommendedMg' => $doseRecommendedMg,
        'volumeMin' => $volumeMin,
        'volumeMax' => $volumeMax,
        'volumeRecommended' => $volumeRecommended,
        'unit' => $concentrationInfo['unit'],
        'concentration' => $concentration,
        'error' => false,
    ];
}

/**
 * Parse concentration string to extract mg/mL value.
 *
 * @param string $concentration Concentration string (e.g., '80mg/mL', '80mg/5mL', '160mg/5mL')
 * @return array|false ['mgPerMl' => float, 'unit' => string] or false if invalid
 */
function parseConcentration($concentration)
{
    // Supported concentrations per FO-0647:
    // - 80mg/mL (drops)
    // - 80mg/5mL (children's syrup)
    // - 160mg/5mL (concentrated syrup)
    $concentrationMap = [
        '80mg/mL' => ['mgPerMl' => 80.0, 'unit' => 'mL'],
        '80mg/1mL' => ['mgPerMl' => 80.0, 'unit' => 'mL'],
        '80mg/5mL' => ['mgPerMl' => 16.0, 'unit' => 'mL'],
        '160mg/5mL' => ['mgPerMl' => 32.0, 'unit' => 'mL'],
    ];

    // Normalize the concentration string
    $normalized = str_replace(' ', '', strtolower($concentration));
    $normalized = str_replace('mg/ml', 'mg/mL', $normalized);
    $normalized = str_replace('mg/', 'mg/', $normalized);

    foreach ($concentrationMap as $key => $value) {
        if (strtolower($key) === $normalized || strtolower(str_replace(' ', '', $key)) === $normalized) {
            return $value;
        }
    }

    // Try to parse custom format: XYZmg/NmL
    if (preg_match('/(\d+(?:\.\d+)?)\s*mg\s*\/\s*(\d+(?:\.\d+)?)\s*ml/i', $concentration, $matches)) {
        $mg = (float) $matches[1];
        $ml = (float) $matches[2];
        if ($ml > 0) {
            return ['mgPerMl' => $mg / $ml, 'unit' => 'mL'];
        }
    }

    return false;
}

/**
 * Validate weight is within acceptable range.
 *
 * @param float $weightKg Weight in kilograms
 * @param float $minWeight Minimum allowed weight (default 2 kg)
 * @param float $maxWeight Maximum allowed weight (default 50 kg)
 * @return bool True if valid
 */
function validateWeight($weightKg, $minWeight = 2.0, $maxWeight = 50.0)
{
    if (!is_numeric($weightKg)) {
        return false;
    }

    $weight = (float) $weightKg;
    return $weight >= $minWeight && $weight <= $maxWeight;
}

/**
 * Validate weight is within the acetaminophen dosing table range (4.3-35 kg).
 *
 * @param float $weightKg Weight in kilograms
 * @return bool True if within dosing table range
 */
function isWeightInDosingRange($weightKg)
{
    return validateWeight($weightKg, 4.3, 35.0);
}

/**
 * Format dosage for display.
 *
 * @param float $doseMg Dose in milligrams
 * @param float|null $volume Volume in mL (optional)
 * @param string $unit Volume unit (default 'mL')
 * @return string Formatted dosage string
 */
function formatDosageForDisplay($doseMg, $volume = null, $unit = 'mL')
{
    $output = round($doseMg, 1) . ' mg';

    if ($volume !== null) {
        $output .= ' (' . round($volume, 2) . ' ' . $unit . ')';
    }

    return $output;
}

/**
 * Format dosage range for display.
 *
 * @param float $doseMinMg Minimum dose in milligrams
 * @param float $doseMaxMg Maximum dose in milligrams
 * @return string Formatted range string
 */
function formatDosageRangeForDisplay($doseMinMg, $doseMaxMg)
{
    return round($doseMinMg, 1) . ' - ' . round($doseMaxMg, 1) . ' mg';
}

/**
 * Generate HTML table showing dosing information for all concentrations.
 *
 * @param float $weightKg Child's weight in kilograms
 * @param string|null $highlightConcentration Concentration to highlight (optional)
 * @return string HTML table
 */
function getDosingTableHtml($weightKg, $highlightConcentration = null)
{
    $concentrations = ['80mg/mL', '80mg/5mL', '160mg/5mL'];
    $concentrationLabels = [
        '80mg/mL' => __('Drops (80mg/mL)'),
        '80mg/5mL' => __("Children's Syrup (80mg/5mL)"),
        '160mg/5mL' => __('Concentrated Syrup (160mg/5mL)'),
    ];

    $html = '<table class="fullWidth colorOdd" cellspacing="0">';
    $html .= '<thead>';
    $html .= '<tr class="head">';
    $html .= '<th>' . __('Concentration') . '</th>';
    $html .= '<th>' . __('Dose Range (mg)') . '</th>';
    $html .= '<th>' . __('Volume Range') . '</th>';
    $html .= '<th>' . __('Recommended Dose') . '</th>';
    $html .= '</tr>';
    $html .= '</thead>';
    $html .= '<tbody>';

    foreach ($concentrations as $concentration) {
        $dose = getAcetaminophenDose($weightKg, $concentration);

        if (isset($dose['error']) && $dose['error']) {
            continue;
        }

        $rowClass = '';
        if ($highlightConcentration && strtolower($highlightConcentration) === strtolower($concentration)) {
            $rowClass = ' class="current"';
        }

        $html .= '<tr' . $rowClass . '>';
        $html .= '<td>' . htmlspecialchars($concentrationLabels[$concentration] ?? $concentration) . '</td>';
        $html .= '<td>' . formatDosageRangeForDisplay($dose['doseMinMg'], $dose['doseMaxMg']) . '</td>';

        if ($dose['volumeMin'] !== null && $dose['volumeMax'] !== null) {
            $html .= '<td>' . round($dose['volumeMin'], 2) . ' - ' . round($dose['volumeMax'], 2) . ' ' . $dose['unit'] . '</td>';
            $html .= '<td>' . formatDosageForDisplay($dose['doseRecommendedMg'], $dose['volumeRecommended'], $dose['unit']) . '</td>';
        } else {
            $html .= '<td>-</td>';
            $html .= '<td>' . formatDosageForDisplay($dose['doseRecommendedMg']) . '</td>';
        }

        $html .= '</tr>';
    }

    $html .= '</tbody>';
    $html .= '</table>';

    return $html;
}

/**
 * Calculate next allowed administration time based on last administration.
 *
 * @param string $lastAdministrationDateTime Last administration datetime (Y-m-d H:i:s format)
 * @param int $intervalMinutes Minimum interval between doses (default 240 = 4 hours per FO-0647)
 * @return array ['nextAllowedTime' => string, 'canAdministerNow' => bool, 'remainingMinutes' => int]
 */
function calculateNextAdministrationTime($lastAdministrationDateTime, $intervalMinutes = 240)
{
    $lastTime = strtotime($lastAdministrationDateTime);

    if (!$lastTime) {
        return [
            'nextAllowedTime' => null,
            'canAdministerNow' => true,
            'remainingMinutes' => 0,
            'error' => __('Invalid datetime format'),
        ];
    }

    $nextAllowedTime = $lastTime + ($intervalMinutes * 60);
    $now = time();
    $remainingSeconds = $nextAllowedTime - $now;
    $remainingMinutes = (int) ceil($remainingSeconds / 60);

    return [
        'nextAllowedTime' => date('Y-m-d H:i:s', $nextAllowedTime),
        'nextAllowedTimeFormatted' => date('g:i A', $nextAllowedTime),
        'canAdministerNow' => $now >= $nextAllowedTime,
        'remainingMinutes' => max(0, $remainingMinutes),
        'remainingFormatted' => formatRemainingTime($remainingMinutes),
    ];
}

/**
 * Format remaining time for display.
 *
 * @param int $minutes Remaining minutes
 * @return string Formatted time string
 */
function formatRemainingTime($minutes)
{
    if ($minutes <= 0) {
        return __('Now');
    }

    if ($minutes < 60) {
        return sprintf(_n('%d minute', '%d minutes', $minutes), $minutes);
    }

    $hours = (int) floor($minutes / 60);
    $mins = $minutes % 60;

    if ($mins === 0) {
        return sprintf(_n('%d hour', '%d hours', $hours), $hours);
    }

    return sprintf(
        _n('%d hour', '%d hours', $hours) . ' ' . _n('%d minute', '%d minutes', $mins),
        $hours,
        $mins
    );
}

/**
 * Check if insect repellent is allowed for a child based on age.
 * Per FO-0646, insect repellent is prohibited for children under 6 months.
 *
 * @param int $ageMonths Child's age in months
 * @return bool True if allowed
 */
function isInsectRepellentAllowed($ageMonths)
{
    return $ageMonths >= 6;
}

/**
 * Calculate child's age in months from date of birth.
 *
 * @param string $dateOfBirth Date of birth (Y-m-d format)
 * @return int Age in months
 */
function calculateAgeInMonths($dateOfBirth)
{
    $dob = new DateTime($dateOfBirth);
    $now = new DateTime();
    $diff = $now->diff($dob);

    return ($diff->y * 12) + $diff->m;
}

/**
 * Get protocol status label HTML.
 *
 * @param array $authorization Authorization data
 * @return string HTML status label
 */
function getProtocolStatusLabel($authorization)
{
    if (empty($authorization)) {
        return '<span class="tag dull">' . __('Not Authorized') . '</span>';
    }

    if (!empty($authorization['revokedAt'])) {
        return '<span class="tag error">' . __('Revoked') . '</span>';
    }

    if ($authorization['status'] === 'Expired' || (!empty($authorization['expiryDate']) && strtotime($authorization['expiryDate']) < time())) {
        return '<span class="tag warning">' . __('Expired') . '</span>';
    }

    // Check weight expiry (3 months)
    if (!empty($authorization['weightExpiryDate']) && strtotime($authorization['weightExpiryDate']) < time()) {
        return '<span class="tag warning">' . __('Weight Update Needed') . '</span>';
    }

    if ($authorization['status'] === 'Active') {
        return '<span class="tag success">' . __('Active') . '</span>';
    }

    return '<span class="tag dull">' . htmlspecialchars($authorization['status']) . '</span>';
}

/**
 * Get weight status label HTML.
 *
 * @param string $weightExpiryDate Weight expiry date (Y-m-d format)
 * @return string HTML status label
 */
function getWeightStatusLabel($weightExpiryDate)
{
    if (empty($weightExpiryDate)) {
        return '<span class="tag dull">' . __('Not Recorded') . '</span>';
    }

    $expiryTime = strtotime($weightExpiryDate);
    $now = time();
    $daysUntilExpiry = (int) floor(($expiryTime - $now) / (60 * 60 * 24));

    if ($daysUntilExpiry < 0) {
        return '<span class="tag error">' . __('Expired') . '</span>';
    }

    if ($daysUntilExpiry <= 14) {
        return '<span class="tag warning">' . sprintf(__('Expires in %d days'), $daysUntilExpiry) . '</span>';
    }

    return '<span class="tag success">' . __('Valid') . '</span>';
}

/**
 * Format temperature for display.
 *
 * @param float $temperatureC Temperature in Celsius
 * @param string $method Temperature measurement method
 * @return string Formatted temperature
 */
function formatTemperature($temperatureC, $method = null)
{
    if (empty($temperatureC)) {
        return '-';
    }

    $output = number_format($temperatureC, 1) . '°C';

    if ($method) {
        $methodLabels = [
            'oral' => __('Oral'),
            'rectal' => __('Rectal'),
            'axillary' => __('Axillary'),
            'tympanic' => __('Tympanic'),
            'temporal' => __('Temporal'),
        ];
        $methodLabel = $methodLabels[strtolower($method)] ?? $method;
        $output .= ' (' . $methodLabel . ')';
    }

    return $output;
}

/**
 * Check if temperature indicates fever (requires acetaminophen).
 * Per FO-0647, fever thresholds vary by measurement method.
 *
 * @param float $temperatureC Temperature in Celsius
 * @param string $method Temperature measurement method
 * @return bool True if fever
 */
function isFever($temperatureC, $method = 'oral')
{
    // Fever thresholds per Quebec guidelines
    $feverThresholds = [
        'rectal' => 38.0,
        'oral' => 37.5,
        'axillary' => 37.3,
        'tympanic' => 38.0,
        'temporal' => 38.0,
    ];

    $threshold = $feverThresholds[strtolower($method)] ?? 37.5;

    return $temperatureC >= $threshold;
}

/**
 * Get follow-up time for acetaminophen administration.
 * Per FO-0647, temperature should be rechecked 60 minutes after administration.
 *
 * @param string $administrationDateTime Administration datetime
 * @return string Follow-up time (Y-m-d H:i:s format)
 */
function calculateFollowUpTime($administrationDateTime)
{
    $adminTime = strtotime($administrationDateTime);
    $followUpTime = $adminTime + (60 * 60); // 60 minutes

    return date('Y-m-d H:i:s', $followUpTime);
}

/**
 * Convert weight between kg and lbs.
 *
 * @param float $value Weight value
 * @param string $from Source unit ('kg' or 'lbs')
 * @param string $to Target unit ('kg' or 'lbs')
 * @return float Converted weight
 */
function convertWeight($value, $from = 'kg', $to = 'kg')
{
    if ($from === $to) {
        return (float) $value;
    }

    if ($from === 'kg' && $to === 'lbs') {
        return round($value * 2.20462, 2);
    }

    if ($from === 'lbs' && $to === 'kg') {
        return round($value / 2.20462, 2);
    }

    return (float) $value;
}

/**
 * Get max daily doses warning HTML if limit is approaching.
 *
 * @param int $doseCount Current dose count in last 24 hours
 * @param int $maxDailyDoses Maximum daily doses (default 5 per FO-0647)
 * @return string|null HTML warning or null
 */
function getDailyDoseWarning($doseCount, $maxDailyDoses = 5)
{
    if ($doseCount >= $maxDailyDoses) {
        return '<div class="error">' . sprintf(__('Daily limit of %d doses reached. Do not administer more acetaminophen.'), $maxDailyDoses) . '</div>';
    }

    if ($doseCount >= ($maxDailyDoses - 1)) {
        return '<div class="warning">' . sprintf(__('Warning: %d of %d daily doses used. Only 1 dose remaining.'), $doseCount, $maxDailyDoses) . '</div>';
    }

    return null;
}

/**
 * Get DEET percentage warning for insect repellent.
 * Per FO-0646, DEET max is 10% for children.
 *
 * @param float $deetPercentage DEET percentage in product
 * @return string|null HTML warning or null
 */
function getDeetWarning($deetPercentage)
{
    if ($deetPercentage > 10) {
        return '<div class="error">' . sprintf(__('Warning: DEET percentage (%.1f%%) exceeds maximum allowed (10%%) for children per FO-0646.'), $deetPercentage) . '</div>';
    }

    return null;
}

/**
 * Format authorization expiry date for display.
 *
 * @param string $expiryDate Expiry date (Y-m-d format)
 * @return string Formatted expiry info
 */
function formatExpiryDate($expiryDate)
{
    if (empty($expiryDate)) {
        return __('No expiry');
    }

    $expiryTime = strtotime($expiryDate);
    $now = time();
    $daysUntilExpiry = (int) floor(($expiryTime - $now) / (60 * 60 * 24));

    $formattedDate = date('M j, Y', $expiryTime);

    if ($daysUntilExpiry < 0) {
        return '<span class="tag error">' . sprintf(__('Expired %s'), $formattedDate) . '</span>';
    }

    if ($daysUntilExpiry === 0) {
        return '<span class="tag warning">' . __('Expires today') . '</span>';
    }

    if ($daysUntilExpiry <= 14) {
        return '<span class="tag warning">' . sprintf(__('Expires %s (%d days)'), $formattedDate, $daysUntilExpiry) . '</span>';
    }

    return $formattedDate;
}
