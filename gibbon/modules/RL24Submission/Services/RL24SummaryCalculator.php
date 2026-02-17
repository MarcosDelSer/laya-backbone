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

namespace Gibbon\Module\RL24Submission\Services;

use Gibbon\Module\RL24Submission\Domain\RL24TransmissionGateway;
use Gibbon\Module\RL24Submission\Domain\RL24SlipGateway;
use Gibbon\Module\RL24Submission\Domain\RL24EligibilityGateway;
use Gibbon\Module\RL24Submission\Xml\RL24XmlSchema;

/**
 * RL-24 Summary Calculator
 *
 * Service for calculating summary totals for RL-24 tax slip submissions.
 * Computes:
 * - Total number of slips
 * - Total days of childcare (Box 10)
 * - Total childcare expenses paid (Box 11)
 * - Total eligible childcare expenses (Box 12)
 * - Total government contributions (Box 13)
 * - Total net eligible expenses (Box 14)
 * - Participant count (unique children)
 *
 * Supports calculation from both existing slips and eligibility form previews.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class RL24SummaryCalculator
{
    /**
     * @var RL24TransmissionGateway Transmission gateway
     */
    protected $transmissionGateway;

    /**
     * @var RL24SlipGateway Slip gateway
     */
    protected $slipGateway;

    /**
     * @var RL24EligibilityGateway Eligibility gateway
     */
    protected $eligibilityGateway;

    /**
     * @var array Validation errors
     */
    protected $errors = [];

    /**
     * @var array Validation warnings
     */
    protected $warnings = [];

    /**
     * Constructor
     *
     * @param RL24TransmissionGateway $transmissionGateway
     * @param RL24SlipGateway $slipGateway
     * @param RL24EligibilityGateway $eligibilityGateway
     */
    public function __construct(
        RL24TransmissionGateway $transmissionGateway,
        RL24SlipGateway $slipGateway,
        RL24EligibilityGateway $eligibilityGateway
    ) {
        $this->transmissionGateway = $transmissionGateway;
        $this->slipGateway = $slipGateway;
        $this->eligibilityGateway = $eligibilityGateway;
    }

    /**
     * Calculate summary totals for a transmission.
     *
     * @param int $gibbonRL24TransmissionID
     * @param bool $includeDraftOnly Only include draft slips (before finalization)
     * @return array Summary totals array
     */
    public function calculateTransmissionSummary(int $gibbonRL24TransmissionID, bool $includeDraftOnly = false): array
    {
        $this->resetState();

        // Get all slips for this transmission
        $slips = $this->slipGateway->selectSlipsByTransmission($gibbonRL24TransmissionID)->fetchAll();

        if (empty($slips)) {
            $this->warnings[] = 'No slips found for transmission';
            return $this->buildEmptySummary();
        }

        // Filter by status if needed
        if ($includeDraftOnly) {
            $slips = array_filter($slips, function ($slip) {
                return $slip['status'] === 'Draft';
            });
        }

        return $this->calculateSummaryFromSlips($slips);
    }

    /**
     * Calculate summary totals from an array of slip data.
     *
     * @param array $slips Array of slip records
     * @return array Summary totals array
     */
    public function calculateSummaryFromSlips(array $slips): array
    {
        $this->resetState();

        $summary = $this->buildEmptySummary();

        // Track unique children for participant count
        $uniqueChildren = [];

        foreach ($slips as $slip) {
            // Skip cancelled slips in the summary count unless explicitly needed
            if (isset($slip['caseACode']) && $slip['caseACode'] === RL24XmlSchema::CODE_CANCELLED) {
                $summary['cancelledSlips']++;
                continue;
            }

            // Skip amended (replaced) slips - only count the amendment
            if (isset($slip['status']) && $slip['status'] === 'Amended') {
                $summary['amendedSlips']++;
                continue;
            }

            $summary['totalSlips']++;

            // Sum amounts
            $summary['totalDays'] += (int) ($slip['totalDays'] ?? 0);
            $summary['totalCase10'] += (int) ($slip['totalDays'] ?? 0); // Box 10 = days
            $summary['totalCase11'] += (float) ($slip['case11Amount'] ?? 0);
            $summary['totalCase12'] += (float) ($slip['case12Amount'] ?? 0);
            $summary['totalCase13'] += (float) ($slip['case13Amount'] ?? 0);
            $summary['totalCase14'] += (float) ($slip['case14Amount'] ?? 0);

            // Track unique children
            if (!empty($slip['gibbonPersonIDChild'])) {
                $uniqueChildren[$slip['gibbonPersonIDChild']] = true;
            }

            // Track slip types
            if (isset($slip['caseACode'])) {
                switch ($slip['caseACode']) {
                    case RL24XmlSchema::CODE_ORIGINAL:
                        $summary['originalSlips']++;
                        break;
                    case RL24XmlSchema::CODE_AMENDED:
                        $summary['amendmentSlips']++;
                        break;
                }
            }
        }

        $summary['participantCount'] = count($uniqueChildren);

        // Format amounts to 2 decimal places
        $summary['totalCase11'] = round($summary['totalCase11'], RL24XmlSchema::AMOUNT_DECIMALS);
        $summary['totalCase12'] = round($summary['totalCase12'], RL24XmlSchema::AMOUNT_DECIMALS);
        $summary['totalCase13'] = round($summary['totalCase13'], RL24XmlSchema::AMOUNT_DECIMALS);
        $summary['totalCase14'] = round($summary['totalCase14'], RL24XmlSchema::AMOUNT_DECIMALS);

        // Validate Box 14 = Box 12 - Box 13
        $this->validateCase14Calculation($summary);

        return $summary;
    }

    /**
     * Calculate preview summary from approved eligibility forms.
     *
     * This calculates estimated totals before slip generation.
     *
     * @param int $taxYear Tax year (form year)
     * @return array Summary preview array
     */
    public function calculatePreviewSummary(int $taxYear): array
    {
        $this->resetState();

        // Get approved eligibility forms
        $eligibilityForms = $this->eligibilityGateway->selectApprovedEligibilityByFormYear($taxYear)->fetchAll();

        if (empty($eligibilityForms)) {
            $this->warnings[] = 'No approved eligibility forms found for tax year ' . $taxYear;
            return $this->buildEmptySummary();
        }

        $summary = $this->buildEmptySummary();
        $uniqueChildren = [];

        foreach ($eligibilityForms as $form) {
            $summary['totalSlips']++;

            // Calculate days from service period
            $totalDays = $this->calculateServiceDays(
                $form['servicePeriodStart'] ?? ($taxYear . '-01-01'),
                $form['servicePeriodEnd'] ?? ($taxYear . '-12-31')
            );

            $summary['totalDays'] += $totalDays;
            $summary['totalCase10'] += $totalDays;

            // Note: Box 11-14 amounts are typically 0 in preview as they require billing data
            // These would be populated during actual slip generation from billing records

            // Track unique children
            if (!empty($form['gibbonPersonIDChild'])) {
                $uniqueChildren[$form['gibbonPersonIDChild']] = true;
            }

            $summary['originalSlips']++;
        }

        $summary['participantCount'] = count($uniqueChildren);
        $summary['isPreview'] = true;

        return $summary;
    }

    /**
     * Calculate summary totals for multiple transmissions (year-to-date).
     *
     * @param int $taxYear
     * @return array Combined summary totals
     */
    public function calculateYearToDateSummary(int $taxYear): array
    {
        $this->resetState();

        // Get all transmissions for the tax year
        $transmissions = $this->transmissionGateway->selectTransmissionsByTaxYear($taxYear)->fetchAll();

        if (empty($transmissions)) {
            $this->warnings[] = 'No transmissions found for tax year ' . $taxYear;
            return $this->buildEmptySummary();
        }

        $allSlips = [];
        foreach ($transmissions as $transmission) {
            // Only include finalized transmissions
            if (!in_array($transmission['status'], ['Generated', 'Validated', 'Submitted', 'Accepted'])) {
                continue;
            }

            $slips = $this->slipGateway->selectSlipsByTransmission($transmission['gibbonRL24TransmissionID'])->fetchAll();
            $allSlips = array_merge($allSlips, $slips);
        }

        $summary = $this->calculateSummaryFromSlips($allSlips);
        $summary['transmissionCount'] = count($transmissions);
        $summary['taxYear'] = $taxYear;

        return $summary;
    }

    /**
     * Calculate and update transmission summary totals in the database.
     *
     * @param int $gibbonRL24TransmissionID
     * @return array Updated summary totals
     */
    public function updateTransmissionSummary(int $gibbonRL24TransmissionID): array
    {
        $summary = $this->calculateTransmissionSummary($gibbonRL24TransmissionID);

        if ($summary['totalSlips'] > 0) {
            $this->transmissionGateway->updateSummaryTotals(
                $gibbonRL24TransmissionID,
                $summary['totalSlips'],
                $summary['totalCase11'],
                $summary['totalCase12'],
                $summary['totalDays']
            );
        }

        return $summary;
    }

    /**
     * Validate summary totals against stored transmission values.
     *
     * @param int $gibbonRL24TransmissionID
     * @return array Validation result with discrepancies
     */
    public function validateTransmissionSummary(int $gibbonRL24TransmissionID): array
    {
        $this->resetState();

        // Get stored transmission summary
        $transmission = $this->transmissionGateway->getTransmissionByID($gibbonRL24TransmissionID);
        if (empty($transmission)) {
            $this->errors[] = 'Transmission not found';
            return ['valid' => false, 'errors' => $this->errors];
        }

        // Calculate actual summary from slips
        $calculatedSummary = $this->calculateTransmissionSummary($gibbonRL24TransmissionID);

        $discrepancies = [];

        // Compare total slips
        if ((int) $transmission['totalSlips'] !== $calculatedSummary['totalSlips']) {
            $discrepancies['totalSlips'] = [
                'stored' => (int) $transmission['totalSlips'],
                'calculated' => $calculatedSummary['totalSlips'],
            ];
        }

        // Compare total days
        if ((int) $transmission['totalDays'] !== $calculatedSummary['totalDays']) {
            $discrepancies['totalDays'] = [
                'stored' => (int) $transmission['totalDays'],
                'calculated' => $calculatedSummary['totalDays'],
            ];
        }

        // Compare Box 11 totals
        if (abs((float) $transmission['totalCase11'] - $calculatedSummary['totalCase11']) > 0.01) {
            $discrepancies['totalCase11'] = [
                'stored' => (float) $transmission['totalCase11'],
                'calculated' => $calculatedSummary['totalCase11'],
            ];
        }

        // Compare Box 12 totals
        if (abs((float) $transmission['totalCase12'] - $calculatedSummary['totalCase12']) > 0.01) {
            $discrepancies['totalCase12'] = [
                'stored' => (float) $transmission['totalCase12'],
                'calculated' => $calculatedSummary['totalCase12'],
            ];
        }

        $isValid = empty($discrepancies);

        if (!$isValid) {
            $this->warnings[] = 'Summary totals do not match calculated values';
        }

        return [
            'valid' => $isValid,
            'storedSummary' => [
                'totalSlips' => (int) $transmission['totalSlips'],
                'totalDays' => (int) $transmission['totalDays'],
                'totalCase11' => (float) $transmission['totalCase11'],
                'totalCase12' => (float) $transmission['totalCase12'],
            ],
            'calculatedSummary' => $calculatedSummary,
            'discrepancies' => $discrepancies,
            'warnings' => $this->warnings,
        ];
    }

    /**
     * Calculate summary breakdown by slip type (Original, Amended, Cancelled).
     *
     * @param int $gibbonRL24TransmissionID
     * @return array Summary broken down by slip type
     */
    public function calculateSummaryBySlipType(int $gibbonRL24TransmissionID): array
    {
        $this->resetState();

        $slips = $this->slipGateway->selectSlipsByTransmission($gibbonRL24TransmissionID)->fetchAll();

        $breakdown = [
            RL24XmlSchema::CODE_ORIGINAL => $this->buildEmptySummary(),
            RL24XmlSchema::CODE_AMENDED => $this->buildEmptySummary(),
            RL24XmlSchema::CODE_CANCELLED => $this->buildEmptySummary(),
        ];

        foreach ($slips as $slip) {
            $code = $slip['caseACode'] ?? RL24XmlSchema::CODE_ORIGINAL;

            if (!isset($breakdown[$code])) {
                continue;
            }

            $breakdown[$code]['totalSlips']++;
            $breakdown[$code]['totalDays'] += (int) ($slip['totalDays'] ?? 0);
            $breakdown[$code]['totalCase10'] += (int) ($slip['totalDays'] ?? 0);
            $breakdown[$code]['totalCase11'] += (float) ($slip['case11Amount'] ?? 0);
            $breakdown[$code]['totalCase12'] += (float) ($slip['case12Amount'] ?? 0);
            $breakdown[$code]['totalCase13'] += (float) ($slip['case13Amount'] ?? 0);
            $breakdown[$code]['totalCase14'] += (float) ($slip['case14Amount'] ?? 0);
        }

        // Round amounts
        foreach ($breakdown as $code => &$summary) {
            $summary['totalCase11'] = round($summary['totalCase11'], RL24XmlSchema::AMOUNT_DECIMALS);
            $summary['totalCase12'] = round($summary['totalCase12'], RL24XmlSchema::AMOUNT_DECIMALS);
            $summary['totalCase13'] = round($summary['totalCase13'], RL24XmlSchema::AMOUNT_DECIMALS);
            $summary['totalCase14'] = round($summary['totalCase14'], RL24XmlSchema::AMOUNT_DECIMALS);
        }

        return [
            'original' => $breakdown[RL24XmlSchema::CODE_ORIGINAL],
            'amended' => $breakdown[RL24XmlSchema::CODE_AMENDED],
            'cancelled' => $breakdown[RL24XmlSchema::CODE_CANCELLED],
            'combined' => $this->calculateTransmissionSummary($gibbonRL24TransmissionID),
        ];
    }

    /**
     * Calculate summary for XML Sommaire element.
     *
     * Returns summary formatted for XML generation.
     *
     * @param int $gibbonRL24TransmissionID
     * @return array Summary formatted for XML
     */
    public function calculateXmlSummary(int $gibbonRL24TransmissionID): array
    {
        $summary = $this->calculateTransmissionSummary($gibbonRL24TransmissionID);

        return [
            RL24XmlSchema::ELEMENT_TOTAL_SLIPS => $summary['totalSlips'],
            RL24XmlSchema::ELEMENT_TOTAL_DAYS => $summary['totalDays'],
            RL24XmlSchema::ELEMENT_TOTAL_BOX_11 => RL24XmlSchema::formatAmount($summary['totalCase11']),
            RL24XmlSchema::ELEMENT_TOTAL_BOX_12 => RL24XmlSchema::formatAmount($summary['totalCase12']),
            RL24XmlSchema::ELEMENT_TOTAL_BOX_13 => RL24XmlSchema::formatAmount($summary['totalCase13']),
            RL24XmlSchema::ELEMENT_TOTAL_BOX_14 => RL24XmlSchema::formatAmount($summary['totalCase14']),
        ];
    }

    /**
     * Recalculate Box 14 amounts for all slips in a transmission.
     *
     * Box 14 = Box 12 - Box 13 (Net eligible expenses).
     *
     * @param int $gibbonRL24TransmissionID
     * @return array Result with count of updated slips
     */
    public function recalculateCase14Amounts(int $gibbonRL24TransmissionID): array
    {
        $this->resetState();

        $slips = $this->slipGateway->selectSlipsByTransmission($gibbonRL24TransmissionID)->fetchAll();

        $updated = 0;
        $skipped = 0;

        foreach ($slips as $slip) {
            // Skip non-draft slips
            if ($slip['status'] !== 'Draft') {
                $skipped++;
                continue;
            }

            $case12 = (float) ($slip['case12Amount'] ?? 0);
            $case13 = (float) ($slip['case13Amount'] ?? 0);
            $calculatedCase14 = max(0, $case12 - $case13);
            $currentCase14 = (float) ($slip['case14Amount'] ?? 0);

            // Only update if different
            if (abs($calculatedCase14 - $currentCase14) > 0.01) {
                $this->slipGateway->updateSlipAmounts(
                    $slip['gibbonRL24SlipID'],
                    $slip['totalDays'],
                    $slip['case11Amount'],
                    $slip['case12Amount'],
                    $slip['case13Amount'],
                    round($calculatedCase14, RL24XmlSchema::AMOUNT_DECIMALS)
                );
                $updated++;
            }
        }

        return [
            'success' => true,
            'totalSlips' => count($slips),
            'updated' => $updated,
            'skipped' => $skipped,
        ];
    }

    /**
     * Calculate number of service days between two dates.
     *
     * @param string $startDate Start date (Y-m-d format)
     * @param string $endDate End date (Y-m-d format)
     * @return int Number of days (inclusive)
     */
    public function calculateServiceDays(string $startDate, string $endDate): int
    {
        try {
            $start = new \DateTime($startDate);
            $end = new \DateTime($endDate);

            $interval = $start->diff($end);

            // Add 1 to include both start and end dates
            return max(0, $interval->days + 1);
        } catch (\Exception $e) {
            $this->errors[] = 'Invalid date format: ' . $e->getMessage();
            return 0;
        }
    }

    /**
     * Calculate average amounts per slip.
     *
     * @param array $summary Summary totals array
     * @return array Average amounts per slip
     */
    public function calculateAverages(array $summary): array
    {
        $totalSlips = $summary['totalSlips'] ?? 0;

        if ($totalSlips === 0) {
            return [
                'avgDays' => 0,
                'avgCase11' => 0.00,
                'avgCase12' => 0.00,
                'avgCase13' => 0.00,
                'avgCase14' => 0.00,
            ];
        }

        return [
            'avgDays' => round($summary['totalDays'] / $totalSlips, 1),
            'avgCase11' => round($summary['totalCase11'] / $totalSlips, RL24XmlSchema::AMOUNT_DECIMALS),
            'avgCase12' => round($summary['totalCase12'] / $totalSlips, RL24XmlSchema::AMOUNT_DECIMALS),
            'avgCase13' => round($summary['totalCase13'] / $totalSlips, RL24XmlSchema::AMOUNT_DECIMALS),
            'avgCase14' => round($summary['totalCase14'] / $totalSlips, RL24XmlSchema::AMOUNT_DECIMALS),
        ];
    }

    /**
     * Validate that Box 14 = Box 12 - Box 13 in the summary.
     *
     * @param array $summary Summary totals
     * @return bool True if valid
     */
    protected function validateCase14Calculation(array &$summary): bool
    {
        $expectedCase14 = $summary['totalCase12'] - $summary['totalCase13'];
        $expectedCase14 = max(0, round($expectedCase14, RL24XmlSchema::AMOUNT_DECIMALS));

        if (abs($summary['totalCase14'] - $expectedCase14) > 0.01) {
            $this->warnings[] = sprintf(
                'Box 14 total (%.2f) does not match Box 12 - Box 13 (%.2f - %.2f = %.2f)',
                $summary['totalCase14'],
                $summary['totalCase12'],
                $summary['totalCase13'],
                $expectedCase14
            );
            $summary['case14Discrepancy'] = $summary['totalCase14'] - $expectedCase14;
            return false;
        }

        return true;
    }

    /**
     * Build an empty summary array with default values.
     *
     * @return array Empty summary structure
     */
    protected function buildEmptySummary(): array
    {
        return [
            'totalSlips' => 0,
            'totalDays' => 0,
            'totalCase10' => 0, // Box 10 = Number of days
            'totalCase11' => 0.00, // Box 11 = Childcare expenses paid
            'totalCase12' => 0.00, // Box 12 = Eligible childcare expenses
            'totalCase13' => 0.00, // Box 13 = Government contributions
            'totalCase14' => 0.00, // Box 14 = Net eligible (Box 12 - Box 13)
            'participantCount' => 0, // Unique children count
            'originalSlips' => 0, // Original slip count
            'amendmentSlips' => 0, // Amendment slip count
            'amendedSlips' => 0, // Slips that were amended (replaced)
            'cancelledSlips' => 0, // Cancelled slip count
            'isPreview' => false, // Whether this is a preview calculation
        ];
    }

    /**
     * Reset validation state.
     */
    protected function resetState(): void
    {
        $this->errors = [];
        $this->warnings = [];
    }

    /**
     * Get validation errors.
     *
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get validation warnings.
     *
     * @return array
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    /**
     * Check if there are any errors.
     *
     * @return bool
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Check if there are any warnings.
     *
     * @return bool
     */
    public function hasWarnings(): bool
    {
        return !empty($this->warnings);
    }
}
