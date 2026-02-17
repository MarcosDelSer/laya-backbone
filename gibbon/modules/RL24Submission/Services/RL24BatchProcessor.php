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
use Gibbon\Module\RL24Submission\Xml\RL24XmlGenerator;
use Gibbon\Module\RL24Submission\Xml\RL24XmlValidator;
use Gibbon\Module\RL24Submission\Xml\RL24XmlSchema;
use Gibbon\Domain\System\SettingGateway;

/**
 * RL-24 Batch Processor
 *
 * Service for batch generating RL-24 slips for all children with approved
 * eligibility forms in a tax year. Handles the full workflow:
 * 1. Create transmission record
 * 2. Generate individual slip records from eligibility data
 * 3. Calculate summary totals
 * 4. Generate and validate XML file
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class RL24BatchProcessor
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
     * @var RL24XmlGenerator XML generator instance
     */
    protected $xmlGenerator;

    /**
     * @var RL24XmlValidator XML validator instance
     */
    protected $xmlValidator;

    /**
     * @var array Processing errors encountered
     */
    protected $errors = [];

    /**
     * @var array Processing warnings (non-fatal)
     */
    protected $warnings = [];

    /**
     * @var array Processing statistics
     */
    protected $stats = [];

    /**
     * @var string|null Base path for storing XML files
     */
    protected $outputPath = null;

    /**
     * @var bool Dry run mode (no database writes)
     */
    protected $dryRun = false;

    /**
     * @var bool Verbose output mode
     */
    protected $verbose = false;

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

        $this->xmlGenerator = new RL24XmlGenerator();
        $this->xmlValidator = new RL24XmlValidator();
    }

    /**
     * Set dry run mode (no database writes).
     *
     * @param bool $dryRun
     * @return self
     */
    public function setDryRun(bool $dryRun): self
    {
        $this->dryRun = $dryRun;
        return $this;
    }

    /**
     * Set verbose output mode.
     *
     * @param bool $verbose
     * @return self
     */
    public function setVerbose(bool $verbose): self
    {
        $this->verbose = $verbose;
        return $this;
    }

    /**
     * Set output path for XML files.
     *
     * @param string $path
     * @return self
     */
    public function setOutputPath(string $path): self
    {
        $this->outputPath = rtrim($path, '/');
        return $this;
    }

    /**
     * Process batch generation for a tax year.
     *
     * @param int $gibbonSchoolYearID School year ID
     * @param int $taxYear Tax year (e.g., 2024)
     * @param int $generatedByID Person ID of user initiating the batch
     * @param array $options Processing options
     * @return array Result array with status, transmission ID, and stats
     */
    public function processBatch(int $gibbonSchoolYearID, int $taxYear, int $generatedByID, array $options = []): array
    {
        $this->resetState();

        $this->log("Starting batch processing for tax year $taxYear");

        // Get provider information from settings
        $providerInfo = $this->getProviderInfo();

        if (!$this->validateProviderInfo($providerInfo)) {
            return $this->buildResult(false, null, 'Provider configuration incomplete');
        }

        // Get approved eligibility forms for the tax year
        $eligibilityForms = $this->eligibilityGateway->selectApprovedEligibilityByFormYear($taxYear)->fetchAll();

        if (empty($eligibilityForms)) {
            $this->errors[] = 'No approved eligibility forms found for tax year ' . $taxYear;
            return $this->buildResult(false, null, 'No eligible children');
        }

        $this->log(sprintf('Found %d approved eligibility forms', count($eligibilityForms)));
        $this->stats['eligibleForms'] = count($eligibilityForms);

        // Check slip count limit
        if (count($eligibilityForms) > RL24XmlSchema::MAX_SLIPS_PER_FILE) {
            $this->warnings[] = sprintf(
                'Number of forms (%d) exceeds maximum per file (%d). Multiple files may be required.',
                count($eligibilityForms),
                RL24XmlSchema::MAX_SLIPS_PER_FILE
            );
        }

        // Create transmission record
        $transmissionID = null;
        if (!$this->dryRun) {
            $transmissionID = $this->transmissionGateway->createTransmission(
                $gibbonSchoolYearID,
                $taxYear,
                $generatedByID,
                $providerInfo
            );

            if (!$transmissionID) {
                $this->errors[] = 'Failed to create transmission record';
                return $this->buildResult(false, null, 'Database error');
            }

            $this->log("Created transmission ID: $transmissionID");
        } else {
            $this->log('[DRY RUN] Would create transmission record');
            $transmissionID = 0;
        }

        // Generate slips from eligibility forms
        $slipsGenerated = $this->generateSlipsFromEligibility(
            $transmissionID,
            $taxYear,
            $eligibilityForms,
            $providerInfo
        );

        if ($slipsGenerated === 0) {
            if (!$this->dryRun && $transmissionID) {
                // Clean up empty transmission
                $this->transmissionGateway->cancelTransmission($transmissionID, 'No slips generated');
            }
            return $this->buildResult(false, $transmissionID, 'No slips generated');
        }

        $this->log(sprintf('Generated %d slips', $slipsGenerated));
        $this->stats['slipsGenerated'] = $slipsGenerated;

        // Calculate and update summary totals
        $summaryTotals = $this->calculateSummaryTotals($transmissionID);

        if (!$this->dryRun && $transmissionID) {
            $this->transmissionGateway->updateSummaryTotals(
                $transmissionID,
                $summaryTotals['totalSlips'],
                $summaryTotals['totalCase11'],
                $summaryTotals['totalCase12'],
                $summaryTotals['totalDays']
            );
        }

        // Generate XML file
        $xmlResult = $this->generateXmlFile($transmissionID, $taxYear, $providerInfo, $eligibilityForms);

        if (!$xmlResult['success']) {
            if (!$this->dryRun && $transmissionID) {
                $this->transmissionGateway->updateTransmissionStatus(
                    $transmissionID,
                    'Failed',
                    ['notes' => 'XML generation failed: ' . implode('; ', $this->errors)]
                );
            }
            return $this->buildResult(false, $transmissionID, 'XML generation failed');
        }

        // Update transmission with XML file info
        if (!$this->dryRun && $transmissionID) {
            $this->transmissionGateway->updateXmlFile(
                $transmissionID,
                $xmlResult['filePath'],
                $xmlResult['validated'],
                $xmlResult['validationErrors'] ?? null
            );

            // Update status to Generated or Validated
            $newStatus = $xmlResult['validated'] ? 'Validated' : 'Generated';
            $this->transmissionGateway->updateTransmissionStatus($transmissionID, $newStatus);

            // Mark all draft slips as included
            $this->slipGateway->includeDraftSlips($transmissionID);
        }

        $this->log(sprintf(
            'Batch processing complete. Slips: %d, Total Days: %d, Total Amount: $%.2f',
            $summaryTotals['totalSlips'],
            $summaryTotals['totalDays'],
            $summaryTotals['totalCase12']
        ));

        return $this->buildResult(true, $transmissionID, 'Batch processing completed successfully');
    }

    /**
     * Generate slips from approved eligibility forms.
     *
     * @param int|null $transmissionID
     * @param int $taxYear
     * @param array $eligibilityForms
     * @param array $providerInfo
     * @return int Number of slips generated
     */
    protected function generateSlipsFromEligibility(?int $transmissionID, int $taxYear, array $eligibilityForms, array $providerInfo): int
    {
        $slipsGenerated = 0;
        $slipNumber = 0;

        foreach ($eligibilityForms as $form) {
            $slipNumber++;

            // Check if slip already exists for this child and year
            if ($this->slipGateway->slipExistsForChildAndYear($form['gibbonPersonIDChild'], $taxYear)) {
                $this->warnings[] = sprintf(
                    'Slip already exists for child ID %d in tax year %d - skipping',
                    $form['gibbonPersonIDChild'],
                    $taxYear
                );
                continue;
            }

            // Prepare slip data from eligibility form
            $slipData = $this->prepareSlipData($form, $taxYear, $slipNumber);

            // Validate slip data
            $validationErrors = $this->validateSlipData($slipData);
            if (!empty($validationErrors)) {
                $this->warnings[] = sprintf(
                    'Validation errors for child %s %s: %s',
                    $form['childFirstName'],
                    $form['childLastName'],
                    implode(', ', $validationErrors)
                );
                continue;
            }

            if (!$this->dryRun && $transmissionID) {
                $slipID = $this->slipGateway->createSlip(
                    $transmissionID,
                    $form['gibbonPersonIDChild'],
                    $form['gibbonPersonIDParent'],
                    $slipData
                );

                if ($slipID) {
                    $slipsGenerated++;
                    $this->log(sprintf(
                        'Created slip #%d for %s %s',
                        $slipNumber,
                        $form['childFirstName'],
                        $form['childLastName']
                    ), true);
                } else {
                    $this->warnings[] = sprintf(
                        'Failed to create slip for child %s %s',
                        $form['childFirstName'],
                        $form['childLastName']
                    );
                }
            } else {
                $slipsGenerated++;
                $this->log(sprintf(
                    '[DRY RUN] Would create slip #%d for %s %s',
                    $slipNumber,
                    $form['childFirstName'],
                    $form['childLastName']
                ), true);
            }
        }

        return $slipsGenerated;
    }

    /**
     * Prepare slip data from eligibility form.
     *
     * @param array $form Eligibility form data
     * @param int $taxYear Tax year
     * @param int $slipNumber Sequential slip number
     * @return array Prepared slip data
     */
    protected function prepareSlipData(array $form, int $taxYear, int $slipNumber): array
    {
        // Calculate days and amounts - these would typically come from
        // actual attendance/billing data. For now, use service period dates.
        $totalDays = $this->calculateServiceDays(
            $form['servicePeriodStart'] ?? ($taxYear . '-01-01'),
            $form['servicePeriodEnd'] ?? ($taxYear . '-12-31')
        );

        // Amount boxes - these should be calculated from actual billing data
        // For now, initialize to zero - to be populated by RL24SummaryCalculator
        $case11Amount = 0.00; // Childcare expenses paid
        $case12Amount = 0.00; // Eligible childcare expenses
        $case13Amount = 0.00; // Government contributions
        $case14Amount = 0.00; // Net eligible (case12 - case13)

        return [
            'slipNumber' => $slipNumber,
            'taxYear' => $taxYear,

            // Parent/recipient info
            'parentFirstName' => $form['parentFirstName'] ?? '',
            'parentLastName' => $form['parentLastName'] ?? '',
            'parentSIN' => $form['parentSIN'] ?? '',
            'parentAddressLine1' => $form['parentAddressLine1'] ?? '',
            'parentAddressLine2' => $form['parentAddressLine2'] ?? '',
            'parentCity' => $form['parentCity'] ?? '',
            'parentProvince' => $form['parentProvince'] ?? RL24XmlSchema::PROVINCE_QUEBEC,
            'parentPostalCode' => $form['parentPostalCode'] ?? '',

            // Child info
            'childFirstName' => $form['childFirstName'] ?? '',
            'childLastName' => $form['childLastName'] ?? '',
            'childDateOfBirth' => $form['childDateOfBirth'] ?? $form['childDOB'] ?? null,

            // Service period
            'servicePeriodStart' => $form['servicePeriodStart'] ?? ($taxYear . '-01-01'),
            'servicePeriodEnd' => $form['servicePeriodEnd'] ?? ($taxYear . '-12-31'),

            // Amounts
            'totalDays' => $totalDays,
            'case11Amount' => $case11Amount,
            'case12Amount' => $case12Amount,
            'case13Amount' => $case13Amount,
            'case14Amount' => $case14Amount,

            // Status and codes
            'status' => 'Draft',
            'caseACode' => RL24XmlSchema::CODE_ORIGINAL,
        ];
    }

    /**
     * Validate slip data before creation.
     *
     * @param array $slipData
     * @return array Array of validation error messages
     */
    protected function validateSlipData(array $slipData): array
    {
        $errors = [];

        // Required fields
        if (empty($slipData['parentLastName'])) {
            $errors[] = 'Parent last name is required';
        }

        if (empty($slipData['childLastName'])) {
            $errors[] = 'Child last name is required';
        }

        // SIN validation (if provided)
        if (!empty($slipData['parentSIN'])) {
            $cleanedSIN = preg_replace('/[^0-9]/', '', $slipData['parentSIN']);
            if (strlen($cleanedSIN) !== RL24XmlSchema::SIN_LENGTH) {
                $errors[] = 'Invalid SIN format';
            }
        }

        // Service period validation
        if (!empty($slipData['servicePeriodStart']) && !empty($slipData['servicePeriodEnd'])) {
            $start = strtotime($slipData['servicePeriodStart']);
            $end = strtotime($slipData['servicePeriodEnd']);

            if ($start > $end) {
                $errors[] = 'Service period start date must be before end date';
            }

            // Check if within tax year
            $taxYearStart = strtotime($slipData['taxYear'] . '-01-01');
            $taxYearEnd = strtotime($slipData['taxYear'] . '-12-31');

            if ($start < $taxYearStart || $end > $taxYearEnd) {
                $errors[] = 'Service period must be within the tax year';
            }
        }

        return $errors;
    }

    /**
     * Calculate number of service days between two dates.
     *
     * @param string $startDate
     * @param string $endDate
     * @return int Number of days
     */
    protected function calculateServiceDays(string $startDate, string $endDate): int
    {
        $start = new \DateTime($startDate);
        $end = new \DateTime($endDate);

        $interval = $start->diff($end);

        // Add 1 to include both start and end dates
        return $interval->days + 1;
    }

    /**
     * Calculate summary totals for a transmission.
     *
     * @param int|null $transmissionID
     * @return array Summary totals
     */
    protected function calculateSummaryTotals(?int $transmissionID): array
    {
        if ($this->dryRun || !$transmissionID) {
            // Return estimated totals in dry run mode
            return [
                'totalSlips' => $this->stats['slipsGenerated'] ?? 0,
                'totalDays' => 0,
                'totalCase11' => 0.00,
                'totalCase12' => 0.00,
                'totalCase13' => 0.00,
                'totalCase14' => 0.00,
            ];
        }

        return $this->slipGateway->getSlipSummaryByTransmission($transmissionID);
    }

    /**
     * Generate XML file for the transmission.
     *
     * @param int|null $transmissionID
     * @param int $taxYear
     * @param array $providerInfo
     * @param array $eligibilityForms
     * @return array Result with success status, file path, and validation status
     */
    protected function generateXmlFile(?int $transmissionID, int $taxYear, array $providerInfo, array $eligibilityForms): array
    {
        $this->log('Generating XML file...');

        // Reset the generator
        $this->xmlGenerator->reset();

        // Get transmission data if available
        $transmission = null;
        if ($transmissionID && !$this->dryRun) {
            $transmission = $this->transmissionGateway->getTransmissionByID($transmissionID);
        }

        // Set up transmission data for XML
        $transmissionData = [
            'taxYear' => $taxYear,
            'sequenceNumber' => $transmission['sequenceNumber'] ?? 1,
            'preparerNumber' => $providerInfo['preparerNumber'] ?? '',
            'transmissionType' => RL24XmlSchema::TRANSMISSION_ORIGINAL,
            'providerName' => $providerInfo['providerName'] ?? '',
            'providerNEQ' => $providerInfo['providerNEQ'] ?? '',
            'providerAddressLine1' => $providerInfo['providerAddress'] ?? '',
            'providerCity' => $providerInfo['providerCity'] ?? '',
            'providerProvince' => $providerInfo['providerProvince'] ?? RL24XmlSchema::PROVINCE_QUEBEC,
            'providerPostalCode' => $providerInfo['providerPostalCode'] ?? '',
        ];

        $this->xmlGenerator->setTransmissionData($transmissionData);

        // Add slips to the generator
        if ($transmissionID && !$this->dryRun) {
            // Get actual slips from database
            $slips = $this->slipGateway->selectSlipsByTransmission($transmissionID)->fetchAll();
            $this->xmlGenerator->addSlips($slips);
        } else {
            // In dry run mode, prepare slip data from eligibility forms
            $slipNumber = 0;
            foreach ($eligibilityForms as $form) {
                $slipNumber++;
                $slipData = $this->prepareSlipData($form, $taxYear, $slipNumber);
                $this->xmlGenerator->addSlip($slipData);
            }
        }

        // Generate the XML
        if (!$this->xmlGenerator->generate()) {
            foreach ($this->xmlGenerator->getErrors() as $error) {
                $this->errors[] = 'XML Generator: ' . $error;
            }
            return ['success' => false, 'filePath' => null, 'validated' => false];
        }

        // Determine output path
        $filename = $this->xmlGenerator->getFilename();
        $outputDir = $this->getOutputDirectory($taxYear);
        $filePath = $outputDir . '/' . $filename;

        // Save XML file
        if (!$this->dryRun) {
            if (!$this->xmlGenerator->saveToFile($filePath)) {
                foreach ($this->xmlGenerator->getErrors() as $error) {
                    $this->errors[] = 'XML Save: ' . $error;
                }
                return ['success' => false, 'filePath' => null, 'validated' => false];
            }

            $this->log("XML file saved to: $filePath");
        } else {
            $this->log("[DRY RUN] Would save XML file to: $filePath");
            $filePath = '[DRY RUN] ' . $filePath;
        }

        // Validate XML
        $validated = false;
        $validationErrors = null;

        if (!$this->dryRun) {
            $validated = $this->xmlValidator->validateXmlFile($filePath);

            if (!$validated) {
                $validationErrors = implode('; ', $this->xmlValidator->getErrors());
                $this->warnings[] = 'XML validation failed: ' . $validationErrors;
            } else {
                $this->log('XML validation passed');
            }
        }

        $this->stats['xmlFilePath'] = $filePath;
        $this->stats['xmlValidated'] = $validated;

        return [
            'success' => true,
            'filePath' => $filePath,
            'validated' => $validated,
            'validationErrors' => $validationErrors,
        ];
    }

    /**
     * Get output directory for XML files.
     *
     * @param int $taxYear
     * @return string Directory path
     */
    protected function getOutputDirectory(int $taxYear): string
    {
        if ($this->outputPath) {
            return $this->outputPath . '/rl24/' . $taxYear;
        }

        // Default to uploads directory
        $uploadsPath = defined('GIBBON_PATH') ? GIBBON_PATH . '/uploads' : getcwd() . '/uploads';
        return $uploadsPath . '/rl24/' . $taxYear;
    }

    /**
     * Get provider information from settings.
     *
     * @return array Provider info array
     */
    protected function getProviderInfo(): array
    {
        return [
            'preparerNumber' => $this->settingGateway->getSettingByScope('RL24 Submission', 'preparerNumber') ?? '',
            'providerName' => $this->settingGateway->getSettingByScope('RL24 Submission', 'providerName') ?? '',
            'providerNEQ' => $this->settingGateway->getSettingByScope('RL24 Submission', 'providerNEQ') ?? '',
            'providerAddress' => $this->settingGateway->getSettingByScope('RL24 Submission', 'providerAddress') ?? '',
            'providerCity' => $this->settingGateway->getSettingByScope('RL24 Submission', 'providerCity') ?? '',
            'providerProvince' => $this->settingGateway->getSettingByScope('RL24 Submission', 'providerProvince') ?? RL24XmlSchema::PROVINCE_QUEBEC,
            'providerPostalCode' => $this->settingGateway->getSettingByScope('RL24 Submission', 'providerPostalCode') ?? '',
        ];
    }

    /**
     * Validate provider information is complete.
     *
     * @param array $providerInfo
     * @return bool True if valid
     */
    protected function validateProviderInfo(array $providerInfo): bool
    {
        $required = ['preparerNumber', 'providerName', 'providerNEQ'];

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
     * Reset processing state.
     */
    protected function resetState(): void
    {
        $this->errors = [];
        $this->warnings = [];
        $this->stats = [];
    }

    /**
     * Build result array.
     *
     * @param bool $success
     * @param int|null $transmissionID
     * @param string $message
     * @return array
     */
    protected function buildResult(bool $success, ?int $transmissionID, string $message): array
    {
        return [
            'success' => $success,
            'transmissionID' => $transmissionID,
            'message' => $message,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'stats' => $this->stats,
        ];
    }

    /**
     * Log message if verbose mode is enabled.
     *
     * @param string $message
     * @param bool $onlyVerbose If true, only log in verbose mode
     */
    protected function log(string $message, bool $onlyVerbose = false): void
    {
        if ($onlyVerbose && !$this->verbose) {
            return;
        }

        if ($this->verbose || !$onlyVerbose) {
            echo '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n";
        }
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
     * Get processing statistics.
     *
     * @return array
     */
    public function getStats(): array
    {
        return $this->stats;
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

    /**
     * Preview batch generation without making changes.
     *
     * @param int $gibbonSchoolYearID
     * @param int $taxYear
     * @return array Preview data with eligible children and estimated totals
     */
    public function previewBatch(int $gibbonSchoolYearID, int $taxYear): array
    {
        $this->resetState();

        // Get provider info
        $providerInfo = $this->getProviderInfo();
        $providerValid = $this->validateProviderInfo($providerInfo);

        // Get eligibility summary
        $eligibilitySummary = $this->eligibilityGateway->getEligibilitySummaryByFormYear($taxYear);

        // Get approved eligibility forms
        $eligibilityForms = $this->eligibilityGateway->selectApprovedEligibilityByFormYear($taxYear)->fetchAll();

        // Check for existing slips
        $existingSlips = 0;
        foreach ($eligibilityForms as $form) {
            if ($this->slipGateway->slipExistsForChildAndYear($form['gibbonPersonIDChild'], $taxYear)) {
                $existingSlips++;
            }
        }

        $newSlips = count($eligibilityForms) - $existingSlips;

        return [
            'taxYear' => $taxYear,
            'providerInfo' => [
                'name' => $providerInfo['providerName'],
                'neq' => $providerInfo['providerNEQ'],
                'preparerNumber' => $providerInfo['preparerNumber'],
                'isValid' => $providerValid,
            ],
            'eligibility' => [
                'totalForms' => $eligibilitySummary['totalForms'] ?? 0,
                'approvedForms' => $eligibilitySummary['approvedCount'] ?? 0,
                'pendingForms' => $eligibilitySummary['pendingCount'] ?? 0,
            ],
            'slips' => [
                'existingSlips' => $existingSlips,
                'newSlipsToGenerate' => $newSlips,
                'maxPerFile' => RL24XmlSchema::MAX_SLIPS_PER_FILE,
                'requiresMultipleFiles' => $newSlips > RL24XmlSchema::MAX_SLIPS_PER_FILE,
            ],
            'canProcess' => $providerValid && $newSlips > 0,
            'warnings' => $this->warnings,
            'errors' => $this->errors,
        ];
    }

    /**
     * Regenerate XML file for an existing transmission.
     *
     * @param int $transmissionID
     * @return array Result with success status and file path
     */
    public function regenerateXml(int $transmissionID): array
    {
        $this->resetState();

        $transmission = $this->transmissionGateway->getTransmissionByID($transmissionID);

        if (empty($transmission)) {
            $this->errors[] = 'Transmission not found';
            return $this->buildResult(false, $transmissionID, 'Transmission not found');
        }

        if (in_array($transmission['status'], ['Submitted', 'Accepted'])) {
            $this->errors[] = 'Cannot regenerate XML for submitted/accepted transmission';
            return $this->buildResult(false, $transmissionID, 'Invalid transmission status');
        }

        $providerInfo = $this->getProviderInfo();
        $taxYear = (int) $transmission['taxYear'];

        // Get slips for this transmission
        $slips = $this->slipGateway->selectSlipsByTransmission($transmissionID)->fetchAll();

        if (empty($slips)) {
            $this->errors[] = 'No slips found for transmission';
            return $this->buildResult(false, $transmissionID, 'No slips found');
        }

        // Generate XML
        $xmlResult = $this->generateXmlForTransmission($transmission, $providerInfo, $slips);

        if (!$xmlResult['success']) {
            return $this->buildResult(false, $transmissionID, 'XML regeneration failed');
        }

        // Update transmission record
        $this->transmissionGateway->updateXmlFile(
            $transmissionID,
            $xmlResult['filePath'],
            $xmlResult['validated'],
            $xmlResult['validationErrors'] ?? null
        );

        $newStatus = $xmlResult['validated'] ? 'Validated' : 'Generated';
        $this->transmissionGateway->updateTransmissionStatus($transmissionID, $newStatus);

        return $this->buildResult(true, $transmissionID, 'XML regenerated successfully');
    }

    /**
     * Generate XML file for existing transmission.
     *
     * @param array $transmission
     * @param array $providerInfo
     * @param array $slips
     * @return array
     */
    protected function generateXmlForTransmission(array $transmission, array $providerInfo, array $slips): array
    {
        $this->xmlGenerator->reset();

        $transmissionData = [
            'taxYear' => $transmission['taxYear'],
            'sequenceNumber' => $transmission['sequenceNumber'],
            'preparerNumber' => $providerInfo['preparerNumber'],
            'transmissionType' => RL24XmlSchema::TRANSMISSION_ORIGINAL,
            'providerName' => $providerInfo['providerName'],
            'providerNEQ' => $providerInfo['providerNEQ'],
            'providerAddressLine1' => $providerInfo['providerAddress'],
            'providerCity' => $providerInfo['providerCity'],
            'providerProvince' => $providerInfo['providerProvince'],
            'providerPostalCode' => $providerInfo['providerPostalCode'],
        ];

        $this->xmlGenerator->setTransmissionData($transmissionData);
        $this->xmlGenerator->addSlips($slips);

        if (!$this->xmlGenerator->generate()) {
            foreach ($this->xmlGenerator->getErrors() as $error) {
                $this->errors[] = 'XML Generator: ' . $error;
            }
            return ['success' => false, 'filePath' => null, 'validated' => false];
        }

        $filename = $this->xmlGenerator->getFilename();
        $outputDir = $this->getOutputDirectory((int) $transmission['taxYear']);
        $filePath = $outputDir . '/' . $filename;

        if (!$this->xmlGenerator->saveToFile($filePath)) {
            foreach ($this->xmlGenerator->getErrors() as $error) {
                $this->errors[] = 'XML Save: ' . $error;
            }
            return ['success' => false, 'filePath' => null, 'validated' => false];
        }

        // Validate
        $validated = $this->xmlValidator->validateXmlFile($filePath);
        $validationErrors = $validated ? null : implode('; ', $this->xmlValidator->getErrors());

        return [
            'success' => true,
            'filePath' => $filePath,
            'validated' => $validated,
            'validationErrors' => $validationErrors,
        ];
    }
}
