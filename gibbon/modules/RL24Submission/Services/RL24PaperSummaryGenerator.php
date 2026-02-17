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
use Gibbon\Domain\System\SettingGateway;

/**
 * RL-24 Paper Summary Generator
 *
 * Service for generating paper summary form auto-fill data for RL-24 transmissions.
 * This data can be used to:
 * - Auto-fill the paper RL-24 Summary form for manual submission
 * - Generate PDF summary reports
 * - Export summary data for printing
 *
 * The paper summary form includes:
 * - Provider (issuer) identification information
 * - Summary totals (slip count, Box 10-14 totals)
 * - Preparer certification information
 * - Date and signature fields
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class RL24PaperSummaryGenerator
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
     * @var SettingGateway Setting gateway for module configuration
     */
    protected $settingGateway;

    /**
     * @var array Processing errors
     */
    protected $errors = [];

    /**
     * @var array Processing warnings
     */
    protected $warnings = [];

    /**
     * Constructor
     *
     * @param RL24TransmissionGateway $transmissionGateway
     * @param RL24SlipGateway $slipGateway
     * @param RL24EligibilityGateway $eligibilityGateway
     * @param SettingGateway $settingGateway
     */
    public function __construct(
        RL24TransmissionGateway $transmissionGateway,
        RL24SlipGateway $slipGateway,
        RL24EligibilityGateway $eligibilityGateway,
        SettingGateway $settingGateway
    ) {
        $this->transmissionGateway = $transmissionGateway;
        $this->slipGateway = $slipGateway;
        $this->eligibilityGateway = $eligibilityGateway;
        $this->settingGateway = $settingGateway;
    }

    /**
     * Generate paper summary form data for a transmission.
     *
     * @param int $gibbonRL24TransmissionID Transmission ID
     * @return array Paper summary form data array
     */
    public function generateSummaryData(int $gibbonRL24TransmissionID): array
    {
        $this->resetState();

        // Get transmission record
        $transmission = $this->transmissionGateway->getTransmissionByID($gibbonRL24TransmissionID);

        if (empty($transmission)) {
            $this->errors[] = 'Transmission not found';
            return $this->buildEmptyResult();
        }

        // Get provider information
        $providerInfo = $this->getProviderInfo();

        // Get slip summary
        $slipSummary = $this->calculateSlipSummary($gibbonRL24TransmissionID);

        // Get preparer information
        $preparerInfo = $this->getPreparerInfo();

        // Build the paper summary data
        return $this->buildSummaryData($transmission, $providerInfo, $slipSummary, $preparerInfo);
    }

    /**
     * Generate paper summary form data from eligibility forms (preview mode).
     *
     * @param int $taxYear Tax year
     * @return array Paper summary form preview data
     */
    public function generatePreviewData(int $taxYear): array
    {
        $this->resetState();

        // Get provider information
        $providerInfo = $this->getProviderInfo();

        // Validate provider info
        if (!$this->validateProviderInfo($providerInfo)) {
            return $this->buildEmptyResult();
        }

        // Get eligibility summary
        $eligibilitySummary = $this->calculateEligibilitySummary($taxYear);

        // Get preparer information
        $preparerInfo = $this->getPreparerInfo();

        // Build preview data
        return $this->buildPreviewData($taxYear, $providerInfo, $eligibilitySummary, $preparerInfo);
    }

    /**
     * Generate print-ready summary for a transmission.
     *
     * Includes all data needed to fill out the paper RL-24 Summary form.
     *
     * @param int $gibbonRL24TransmissionID Transmission ID
     * @return array Print-ready summary data with field labels
     */
    public function generatePrintSummary(int $gibbonRL24TransmissionID): array
    {
        $summaryData = $this->generateSummaryData($gibbonRL24TransmissionID);

        if (!$summaryData['success']) {
            return $summaryData;
        }

        // Add field labels and formatting for print output
        return [
            'success' => true,
            'title' => $this->formatPrintTitle($summaryData),
            'sections' => [
                'identification' => $this->formatIdentificationSection($summaryData),
                'provider' => $this->formatProviderSection($summaryData),
                'summary' => $this->formatSummarySection($summaryData),
                'preparer' => $this->formatPreparerSection($summaryData),
                'certification' => $this->formatCertificationSection($summaryData),
            ],
            'metadata' => [
                'generatedAt' => date('Y-m-d H:i:s'),
                'transmissionID' => $gibbonRL24TransmissionID,
                'formType' => 'RL-24 Sommaire',
            ],
        ];
    }

    /**
     * Generate slip listing for paper copies.
     *
     * @param int $gibbonRL24TransmissionID Transmission ID
     * @return array Slip listing data for paper copies
     */
    public function generateSlipListing(int $gibbonRL24TransmissionID): array
    {
        $this->resetState();

        // Get transmission record
        $transmission = $this->transmissionGateway->getTransmissionByID($gibbonRL24TransmissionID);

        if (empty($transmission)) {
            $this->errors[] = 'Transmission not found';
            return $this->buildEmptyResult();
        }

        // Get all slips for this transmission
        $slips = $this->slipGateway->selectSlipsByTransmission($gibbonRL24TransmissionID)->fetchAll();

        if (empty($slips)) {
            $this->warnings[] = 'No slips found for transmission';
        }

        // Format slips for paper output
        $formattedSlips = [];
        foreach ($slips as $slip) {
            $formattedSlips[] = $this->formatSlipForPaper($slip);
        }

        return [
            'success' => true,
            'transmission' => [
                'id' => $gibbonRL24TransmissionID,
                'taxYear' => $transmission['taxYear'],
                'sequenceNumber' => $transmission['sequenceNumber'],
                'filename' => $transmission['xmlFilename'] ?? null,
            ],
            'slipCount' => count($formattedSlips),
            'slips' => $formattedSlips,
            'warnings' => $this->warnings,
        ];
    }

    /**
     * Calculate slip summary totals for a transmission.
     *
     * @param int $gibbonRL24TransmissionID Transmission ID
     * @return array Summary totals array
     */
    protected function calculateSlipSummary(int $gibbonRL24TransmissionID): array
    {
        $slips = $this->slipGateway->selectSlipsByTransmission($gibbonRL24TransmissionID)->fetchAll();

        return $this->calculateSummaryFromSlips($slips);
    }

    /**
     * Calculate summary from an array of slips.
     *
     * @param array $slips Array of slip records
     * @return array Summary totals
     */
    protected function calculateSummaryFromSlips(array $slips): array
    {
        $summary = [
            'totalSlips' => 0,
            'originalSlips' => 0,
            'amendedSlips' => 0,
            'cancelledSlips' => 0,
            'totalDays' => 0,
            'totalCase10' => 0,
            'totalCase11' => 0.00,
            'totalCase12' => 0.00,
            'totalCase13' => 0.00,
            'totalCase14' => 0.00,
            'participantCount' => 0,
        ];

        $uniqueChildren = [];

        foreach ($slips as $slip) {
            // Count by slip type
            $slipCode = $slip['caseACode'] ?? RL24XmlSchema::CODE_ORIGINAL;
            switch ($slipCode) {
                case RL24XmlSchema::CODE_ORIGINAL:
                    $summary['originalSlips']++;
                    break;
                case RL24XmlSchema::CODE_AMENDED:
                    $summary['amendedSlips']++;
                    break;
                case RL24XmlSchema::CODE_CANCELLED:
                    $summary['cancelledSlips']++;
                    continue 2; // Skip cancelled slips in totals
            }

            $summary['totalSlips']++;

            // Sum amounts
            $summary['totalDays'] += (int) ($slip['totalDays'] ?? 0);
            $summary['totalCase10'] += (int) ($slip['totalDays'] ?? 0);
            $summary['totalCase11'] += (float) ($slip['case11Amount'] ?? 0);
            $summary['totalCase12'] += (float) ($slip['case12Amount'] ?? 0);
            $summary['totalCase13'] += (float) ($slip['case13Amount'] ?? 0);
            $summary['totalCase14'] += (float) ($slip['case14Amount'] ?? 0);

            // Track unique children
            if (!empty($slip['gibbonPersonIDChild'])) {
                $uniqueChildren[$slip['gibbonPersonIDChild']] = true;
            }
        }

        $summary['participantCount'] = count($uniqueChildren);

        // Round monetary amounts
        $summary['totalCase11'] = round($summary['totalCase11'], RL24XmlSchema::AMOUNT_DECIMALS);
        $summary['totalCase12'] = round($summary['totalCase12'], RL24XmlSchema::AMOUNT_DECIMALS);
        $summary['totalCase13'] = round($summary['totalCase13'], RL24XmlSchema::AMOUNT_DECIMALS);
        $summary['totalCase14'] = round($summary['totalCase14'], RL24XmlSchema::AMOUNT_DECIMALS);

        return $summary;
    }

    /**
     * Calculate eligibility summary for preview mode.
     *
     * @param int $taxYear Tax year
     * @return array Eligibility summary
     */
    protected function calculateEligibilitySummary(int $taxYear): array
    {
        $eligibilityForms = $this->eligibilityGateway->selectApprovedEligibilityByFormYear($taxYear)->fetchAll();

        if (empty($eligibilityForms)) {
            $this->warnings[] = 'No approved eligibility forms found for tax year ' . $taxYear;
            return [
                'totalSlips' => 0,
                'totalDays' => 0,
                'totalCase10' => 0,
                'totalCase11' => 0.00,
                'totalCase12' => 0.00,
                'totalCase13' => 0.00,
                'totalCase14' => 0.00,
                'participantCount' => 0,
                'isPreview' => true,
            ];
        }

        $summary = [
            'totalSlips' => count($eligibilityForms),
            'totalDays' => 0,
            'totalCase10' => 0,
            'totalCase11' => 0.00,
            'totalCase12' => 0.00,
            'totalCase13' => 0.00,
            'totalCase14' => 0.00,
            'participantCount' => 0,
            'isPreview' => true,
        ];

        $uniqueChildren = [];

        foreach ($eligibilityForms as $form) {
            // Calculate service days
            $totalDays = $this->calculateServiceDays(
                $form['servicePeriodStart'] ?? ($taxYear . '-01-01'),
                $form['servicePeriodEnd'] ?? ($taxYear . '-12-31')
            );

            $summary['totalDays'] += $totalDays;
            $summary['totalCase10'] += $totalDays;

            // Track unique children
            if (!empty($form['gibbonPersonIDChild'])) {
                $uniqueChildren[$form['gibbonPersonIDChild']] = true;
            }
        }

        $summary['participantCount'] = count($uniqueChildren);

        return $summary;
    }

    /**
     * Calculate number of service days between two dates.
     *
     * @param string $startDate Start date (Y-m-d format)
     * @param string $endDate End date (Y-m-d format)
     * @return int Number of days (inclusive)
     */
    protected function calculateServiceDays(string $startDate, string $endDate): int
    {
        try {
            $start = new \DateTime($startDate);
            $end = new \DateTime($endDate);

            $interval = $start->diff($end);

            return max(0, $interval->days + 1);
        } catch (\Exception $e) {
            $this->warnings[] = 'Invalid date format: ' . $e->getMessage();
            return 0;
        }
    }

    /**
     * Get provider information from settings.
     *
     * @return array Provider info array
     */
    protected function getProviderInfo(): array
    {
        return [
            'providerName' => $this->settingGateway->getSettingByScope('RL24 Submission', 'providerName') ?? '',
            'providerNEQ' => $this->settingGateway->getSettingByScope('RL24 Submission', 'providerNEQ') ?? '',
            'providerAddress' => $this->settingGateway->getSettingByScope('RL24 Submission', 'providerAddress') ?? '',
            'providerCity' => $this->settingGateway->getSettingByScope('RL24 Submission', 'providerCity') ?? '',
            'providerProvince' => $this->settingGateway->getSettingByScope('RL24 Submission', 'providerProvince') ?? RL24XmlSchema::PROVINCE_QUEBEC,
            'providerPostalCode' => $this->settingGateway->getSettingByScope('RL24 Submission', 'providerPostalCode') ?? '',
            'providerPhone' => $this->settingGateway->getSettingByScope('RL24 Submission', 'providerPhone') ?? '',
        ];
    }

    /**
     * Get preparer information from settings.
     *
     * @return array Preparer info array
     */
    protected function getPreparerInfo(): array
    {
        return [
            'preparerNumber' => $this->settingGateway->getSettingByScope('RL24 Submission', 'preparerNumber') ?? '',
            'preparerName' => $this->settingGateway->getSettingByScope('RL24 Submission', 'preparerName') ?? '',
            'preparerAddress' => $this->settingGateway->getSettingByScope('RL24 Submission', 'preparerAddress') ?? '',
            'preparerCity' => $this->settingGateway->getSettingByScope('RL24 Submission', 'preparerCity') ?? '',
            'preparerProvince' => $this->settingGateway->getSettingByScope('RL24 Submission', 'preparerProvince') ?? RL24XmlSchema::PROVINCE_QUEBEC,
            'preparerPostalCode' => $this->settingGateway->getSettingByScope('RL24 Submission', 'preparerPostalCode') ?? '',
            'preparerPhone' => $this->settingGateway->getSettingByScope('RL24 Submission', 'preparerPhone') ?? '',
        ];
    }

    /**
     * Validate provider information is complete.
     *
     * @param array $providerInfo Provider information
     * @return bool True if valid
     */
    protected function validateProviderInfo(array $providerInfo): bool
    {
        $required = ['providerName', 'providerNEQ'];

        foreach ($required as $field) {
            if (empty($providerInfo[$field])) {
                $this->errors[] = "Provider configuration missing: $field";
                return false;
            }
        }

        // Validate NEQ format
        if (!RL24XmlSchema::isValidNEQ($providerInfo['providerNEQ'])) {
            $this->errors[] = 'Invalid provider NEQ format. Must be 10 digits.';
            return false;
        }

        return true;
    }

    /**
     * Build the paper summary data structure.
     *
     * @param array $transmission Transmission record
     * @param array $providerInfo Provider information
     * @param array $slipSummary Slip summary totals
     * @param array $preparerInfo Preparer information
     * @return array Complete summary data
     */
    protected function buildSummaryData(array $transmission, array $providerInfo, array $slipSummary, array $preparerInfo): array
    {
        return [
            'success' => true,
            'transmission' => [
                'id' => $transmission['gibbonRL24TransmissionID'],
                'taxYear' => $transmission['taxYear'],
                'sequenceNumber' => $transmission['sequenceNumber'],
                'status' => $transmission['status'],
                'xmlFilename' => $transmission['xmlFilename'] ?? null,
                'generatedDate' => $transmission['createdAt'] ?? null,
            ],
            'provider' => [
                'name' => $providerInfo['providerName'],
                'neq' => $this->formatNEQ($providerInfo['providerNEQ']),
                'neqRaw' => $providerInfo['providerNEQ'],
                'address' => $providerInfo['providerAddress'],
                'city' => $providerInfo['providerCity'],
                'province' => $providerInfo['providerProvince'],
                'postalCode' => $this->formatPostalCode($providerInfo['providerPostalCode']),
                'phone' => $providerInfo['providerPhone'],
                'fullAddress' => $this->formatFullAddress($providerInfo),
            ],
            'summary' => [
                'totalSlips' => $slipSummary['totalSlips'],
                'originalSlips' => $slipSummary['originalSlips'] ?? $slipSummary['totalSlips'],
                'amendedSlips' => $slipSummary['amendedSlips'] ?? 0,
                'cancelledSlips' => $slipSummary['cancelledSlips'] ?? 0,
                'totalDays' => $slipSummary['totalDays'],
                'case10' => $slipSummary['totalCase10'],
                'case11' => $slipSummary['totalCase11'],
                'case12' => $slipSummary['totalCase12'],
                'case13' => $slipSummary['totalCase13'],
                'case14' => $slipSummary['totalCase14'],
                'case10Formatted' => number_format($slipSummary['totalCase10']),
                'case11Formatted' => $this->formatCurrency($slipSummary['totalCase11']),
                'case12Formatted' => $this->formatCurrency($slipSummary['totalCase12']),
                'case13Formatted' => $this->formatCurrency($slipSummary['totalCase13']),
                'case14Formatted' => $this->formatCurrency($slipSummary['totalCase14']),
                'participantCount' => $slipSummary['participantCount'],
            ],
            'preparer' => [
                'number' => $this->formatPreparerNumber($preparerInfo['preparerNumber']),
                'numberRaw' => $preparerInfo['preparerNumber'],
                'name' => $preparerInfo['preparerName'],
                'address' => $preparerInfo['preparerAddress'],
                'city' => $preparerInfo['preparerCity'],
                'province' => $preparerInfo['preparerProvince'],
                'postalCode' => $this->formatPostalCode($preparerInfo['preparerPostalCode']),
                'phone' => $preparerInfo['preparerPhone'],
            ],
            'formFields' => $this->buildFormFields($transmission, $providerInfo, $slipSummary, $preparerInfo),
            'errors' => $this->errors,
            'warnings' => $this->warnings,
        ];
    }

    /**
     * Build preview data for tax year.
     *
     * @param int $taxYear Tax year
     * @param array $providerInfo Provider information
     * @param array $eligibilitySummary Eligibility summary
     * @param array $preparerInfo Preparer information
     * @return array Preview data
     */
    protected function buildPreviewData(int $taxYear, array $providerInfo, array $eligibilitySummary, array $preparerInfo): array
    {
        return [
            'success' => true,
            'isPreview' => true,
            'taxYear' => $taxYear,
            'provider' => [
                'name' => $providerInfo['providerName'],
                'neq' => $this->formatNEQ($providerInfo['providerNEQ']),
                'address' => $providerInfo['providerAddress'],
                'city' => $providerInfo['providerCity'],
                'province' => $providerInfo['providerProvince'],
                'postalCode' => $this->formatPostalCode($providerInfo['providerPostalCode']),
            ],
            'summary' => [
                'estimatedSlips' => $eligibilitySummary['totalSlips'],
                'estimatedDays' => $eligibilitySummary['totalDays'],
                'case10' => $eligibilitySummary['totalCase10'],
                'case11' => $eligibilitySummary['totalCase11'],
                'case12' => $eligibilitySummary['totalCase12'],
                'case13' => $eligibilitySummary['totalCase13'],
                'case14' => $eligibilitySummary['totalCase14'],
                'participantCount' => $eligibilitySummary['participantCount'],
                'note' => 'Box 11-14 amounts will be calculated during batch generation from billing data.',
            ],
            'preparer' => [
                'number' => $this->formatPreparerNumber($preparerInfo['preparerNumber']),
                'name' => $preparerInfo['preparerName'],
            ],
            'errors' => $this->errors,
            'warnings' => $this->warnings,
        ];
    }

    /**
     * Build form fields for direct form auto-fill.
     *
     * Maps data to specific paper form field identifiers.
     *
     * @param array $transmission Transmission record
     * @param array $providerInfo Provider information
     * @param array $slipSummary Slip summary
     * @param array $preparerInfo Preparer information
     * @return array Form field mapping
     */
    protected function buildFormFields(array $transmission, array $providerInfo, array $slipSummary, array $preparerInfo): array
    {
        return [
            // Identification section
            'annee' => $transmission['taxYear'],
            'noSequence' => str_pad($transmission['sequenceNumber'], 3, '0', STR_PAD_LEFT),
            'noTransmetteur' => $this->formatPreparerNumber($preparerInfo['preparerNumber']),

            // Provider/Issuer section
            'neqEmetteur' => $providerInfo['providerNEQ'],
            'nomEmetteur' => $providerInfo['providerName'],
            'adresseEmetteur' => $providerInfo['providerAddress'],
            'villeEmetteur' => $providerInfo['providerCity'],
            'provinceEmetteur' => $providerInfo['providerProvince'],
            'codePostalEmetteur' => $providerInfo['providerPostalCode'],
            'telephoneEmetteur' => $providerInfo['providerPhone'],

            // Summary totals section
            'nombreReleves' => $slipSummary['totalSlips'],
            'totalCase10' => $slipSummary['totalCase10'],
            'totalCase11' => RL24XmlSchema::formatAmount($slipSummary['totalCase11']),
            'totalCase12' => RL24XmlSchema::formatAmount($slipSummary['totalCase12']),
            'totalCase13' => RL24XmlSchema::formatAmount($slipSummary['totalCase13']),
            'totalCase14' => RL24XmlSchema::formatAmount($slipSummary['totalCase14']),

            // Preparer section
            'noPreparateur' => $preparerInfo['preparerNumber'],
            'nomPreparateur' => $preparerInfo['preparerName'],
            'adressePreparateur' => $preparerInfo['preparerAddress'],
            'villePreparateur' => $preparerInfo['preparerCity'],
            'provincePreparateur' => $preparerInfo['preparerProvince'],
            'codePostalPreparateur' => $preparerInfo['preparerPostalCode'],

            // Date field (to be filled at signing)
            'dateSignature' => date(RL24XmlSchema::DATE_FORMAT_XML),
        ];
    }

    /**
     * Format a slip for paper output.
     *
     * @param array $slip Slip record
     * @return array Formatted slip data
     */
    protected function formatSlipForPaper(array $slip): array
    {
        return [
            'slipNumber' => $slip['slipNumber'] ?? '',
            'slipType' => RL24XmlSchema::getSlipTypeDescription($slip['caseACode'] ?? RL24XmlSchema::CODE_ORIGINAL),
            'slipTypeCode' => $slip['caseACode'] ?? RL24XmlSchema::CODE_ORIGINAL,

            'recipient' => [
                'name' => trim(($slip['parentFirstName'] ?? '') . ' ' . ($slip['parentLastName'] ?? '')),
                'lastName' => $slip['parentLastName'] ?? '',
                'firstName' => $slip['parentFirstName'] ?? '',
                'sin' => $this->formatSINForDisplay($slip['parentSIN'] ?? ''),
                'address' => $slip['parentAddressLine1'] ?? '',
                'city' => $slip['parentCity'] ?? '',
                'province' => $slip['parentProvince'] ?? '',
                'postalCode' => $this->formatPostalCode($slip['parentPostalCode'] ?? ''),
            ],

            'child' => [
                'name' => trim(($slip['childFirstName'] ?? '') . ' ' . ($slip['childLastName'] ?? '')),
                'lastName' => $slip['childLastName'] ?? '',
                'firstName' => $slip['childFirstName'] ?? '',
                'dateOfBirth' => $this->formatDateForDisplay($slip['childDateOfBirth'] ?? null),
            ],

            'servicePeriod' => [
                'start' => $this->formatDateForDisplay($slip['servicePeriodStart'] ?? null),
                'end' => $this->formatDateForDisplay($slip['servicePeriodEnd'] ?? null),
            ],

            'amounts' => [
                'case10' => (int) ($slip['totalDays'] ?? 0),
                'case11' => $this->formatCurrency($slip['case11Amount'] ?? 0),
                'case12' => $this->formatCurrency($slip['case12Amount'] ?? 0),
                'case13' => $this->formatCurrency($slip['case13Amount'] ?? 0),
                'case14' => $this->formatCurrency($slip['case14Amount'] ?? 0),
            ],

            'status' => $slip['status'] ?? 'Draft',
        ];
    }

    /**
     * Format print title.
     *
     * @param array $summaryData Summary data
     * @return string Formatted title
     */
    protected function formatPrintTitle(array $summaryData): string
    {
        return sprintf(
            'RL-24 Sommaire - Année %d - Séquence %s',
            $summaryData['transmission']['taxYear'],
            str_pad($summaryData['transmission']['sequenceNumber'], 3, '0', STR_PAD_LEFT)
        );
    }

    /**
     * Format identification section for print.
     *
     * @param array $summaryData Summary data
     * @return array Identification section data
     */
    protected function formatIdentificationSection(array $summaryData): array
    {
        return [
            'label' => 'Identification',
            'fields' => [
                ['label' => 'Année d\'imposition', 'value' => $summaryData['transmission']['taxYear']],
                ['label' => 'Numéro de séquence', 'value' => str_pad($summaryData['transmission']['sequenceNumber'], 3, '0', STR_PAD_LEFT)],
                ['label' => 'Fichier XML', 'value' => $summaryData['transmission']['xmlFilename'] ?? 'Non généré'],
                ['label' => 'Statut', 'value' => $summaryData['transmission']['status']],
            ],
        ];
    }

    /**
     * Format provider section for print.
     *
     * @param array $summaryData Summary data
     * @return array Provider section data
     */
    protected function formatProviderSection(array $summaryData): array
    {
        $provider = $summaryData['provider'];
        return [
            'label' => 'Identification de l\'émetteur',
            'fields' => [
                ['label' => 'NEQ', 'value' => $provider['neq']],
                ['label' => 'Nom', 'value' => $provider['name']],
                ['label' => 'Adresse', 'value' => $provider['fullAddress']],
                ['label' => 'Téléphone', 'value' => $provider['phone']],
            ],
        ];
    }

    /**
     * Format summary section for print.
     *
     * @param array $summaryData Summary data
     * @return array Summary section data
     */
    protected function formatSummarySection(array $summaryData): array
    {
        $summary = $summaryData['summary'];
        return [
            'label' => 'Sommaire des relevés',
            'fields' => [
                ['label' => 'Nombre de relevés', 'value' => $summary['totalSlips']],
                ['label' => 'Nombre de participants', 'value' => $summary['participantCount']],
                ['label' => 'Case 10 - Total jours', 'value' => $summary['case10Formatted']],
                ['label' => 'Case 11 - Frais payés', 'value' => $summary['case11Formatted']],
                ['label' => 'Case 12 - Frais admissibles', 'value' => $summary['case12Formatted']],
                ['label' => 'Case 13 - Contributions gouvernementales', 'value' => $summary['case13Formatted']],
                ['label' => 'Case 14 - Frais admissibles nets', 'value' => $summary['case14Formatted']],
            ],
        ];
    }

    /**
     * Format preparer section for print.
     *
     * @param array $summaryData Summary data
     * @return array Preparer section data
     */
    protected function formatPreparerSection(array $summaryData): array
    {
        $preparer = $summaryData['preparer'];
        return [
            'label' => 'Identification du préparateur',
            'fields' => [
                ['label' => 'Numéro de préparateur', 'value' => $preparer['number']],
                ['label' => 'Nom', 'value' => $preparer['name']],
                ['label' => 'Adresse', 'value' => $preparer['address']],
                ['label' => 'Ville', 'value' => $preparer['city']],
                ['label' => 'Province', 'value' => $preparer['province']],
                ['label' => 'Code postal', 'value' => $preparer['postalCode']],
            ],
        ];
    }

    /**
     * Format certification section for print.
     *
     * @param array $summaryData Summary data
     * @return array Certification section data
     */
    protected function formatCertificationSection(array $summaryData): array
    {
        return [
            'label' => 'Attestation',
            'text' => 'J\'atteste que les renseignements fournis dans ce sommaire et dans les relevés ci-joints sont exacts et complets.',
            'fields' => [
                ['label' => 'Date', 'value' => '____________________', 'editable' => true],
                ['label' => 'Signature', 'value' => '____________________', 'editable' => true],
                ['label' => 'Nom en lettres moulées', 'value' => '____________________', 'editable' => true],
                ['label' => 'Titre', 'value' => '____________________', 'editable' => true],
            ],
        ];
    }

    /**
     * Format NEQ for display.
     *
     * @param string $neq NEQ number
     * @return string Formatted NEQ (XXXX XXX XXX)
     */
    protected function formatNEQ(string $neq): string
    {
        $cleaned = preg_replace('/[^0-9]/', '', $neq);
        if (strlen($cleaned) !== RL24XmlSchema::NEQ_LENGTH) {
            return $neq;
        }

        return substr($cleaned, 0, 4) . ' ' . substr($cleaned, 4, 3) . ' ' . substr($cleaned, 7, 3);
    }

    /**
     * Format preparer number for display.
     *
     * @param string $number Preparer number
     * @return string Formatted number
     */
    protected function formatPreparerNumber(string $number): string
    {
        $cleaned = preg_replace('/[^0-9]/', '', $number);
        return str_pad($cleaned, RL24XmlSchema::PREPARER_NUMBER_LENGTH, '0', STR_PAD_LEFT);
    }

    /**
     * Format SIN for display (masked).
     *
     * @param string $sin Social Insurance Number
     * @return string Masked SIN (XXX XXX XXX with first 6 digits masked)
     */
    protected function formatSINForDisplay(string $sin): string
    {
        $cleaned = preg_replace('/[^0-9]/', '', $sin);
        if (strlen($cleaned) !== RL24XmlSchema::SIN_LENGTH) {
            return '*** *** ***';
        }

        // Mask first 6 digits for privacy
        return '*** **' . substr($cleaned, 5, 1) . ' ' . substr($cleaned, 6, 3);
    }

    /**
     * Format postal code for display.
     *
     * @param string $postalCode Postal code
     * @return string Formatted postal code (X#X #X#)
     */
    protected function formatPostalCode(string $postalCode): string
    {
        $cleaned = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $postalCode));
        if (strlen($cleaned) !== 6) {
            return $postalCode;
        }

        return substr($cleaned, 0, 3) . ' ' . substr($cleaned, 3, 3);
    }

    /**
     * Format full address for display.
     *
     * @param array $addressInfo Address components
     * @return string Formatted full address
     */
    protected function formatFullAddress(array $addressInfo): string
    {
        $parts = [];

        if (!empty($addressInfo['providerAddress'])) {
            $parts[] = $addressInfo['providerAddress'];
        }

        $cityParts = [];
        if (!empty($addressInfo['providerCity'])) {
            $cityParts[] = $addressInfo['providerCity'];
        }
        if (!empty($addressInfo['providerProvince'])) {
            $cityParts[] = $addressInfo['providerProvince'];
        }
        if (!empty($addressInfo['providerPostalCode'])) {
            $cityParts[] = $this->formatPostalCode($addressInfo['providerPostalCode']);
        }

        if (!empty($cityParts)) {
            $parts[] = implode(', ', $cityParts);
        }

        return implode("\n", $parts);
    }

    /**
     * Format date for display.
     *
     * @param string|null $date Date in Y-m-d format
     * @return string Formatted date
     */
    protected function formatDateForDisplay(?string $date): string
    {
        if (empty($date)) {
            return '';
        }

        try {
            $dateObj = new \DateTime($date);
            return $dateObj->format(RL24XmlSchema::DATE_FORMAT_DISPLAY);
        } catch (\Exception $e) {
            return $date;
        }
    }

    /**
     * Format currency amount for display.
     *
     * @param float $amount Amount
     * @return string Formatted currency string
     */
    protected function formatCurrency(float $amount): string
    {
        return number_format($amount, RL24XmlSchema::AMOUNT_DECIMALS, ',', ' ') . ' $';
    }

    /**
     * Build empty result structure.
     *
     * @return array Empty result with error information
     */
    protected function buildEmptyResult(): array
    {
        return [
            'success' => false,
            'transmission' => null,
            'provider' => null,
            'summary' => null,
            'preparer' => null,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
        ];
    }

    /**
     * Reset processing state.
     */
    protected function resetState(): void
    {
        $this->errors = [];
        $this->warnings = [];
    }

    /**
     * Get processing errors.
     *
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get processing warnings.
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
