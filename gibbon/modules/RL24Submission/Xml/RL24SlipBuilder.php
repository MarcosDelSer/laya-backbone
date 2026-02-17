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

namespace Gibbon\Module\RL24Submission\Xml;

use DOMDocument;
use DOMElement;

/**
 * RL-24 Slip Builder
 *
 * Builds individual RL-24 slip XML elements with all required fields
 * for Revenu Québec submission. This builder handles the complete
 * structure of a single slip including identification, recipient (parent),
 * child information, service period, and amount boxes 10-14.
 *
 * Supports Original, Amended, and Cancelled slip types with appropriate
 * handling of references to prior slips.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class RL24SlipBuilder
{
    // =========================================================================
    // INSTANCE PROPERTIES
    // =========================================================================

    /**
     * @var DOMDocument The parent XML document
     */
    protected $dom;

    /**
     * @var array Slip data to build into XML
     */
    protected $slipData = [];

    /**
     * @var int Sequential slip number within the transmission
     */
    protected $slipNumber = 1;

    /**
     * @var array Validation errors encountered during build
     */
    protected $errors = [];

    /**
     * @var array Validation warnings encountered during build
     */
    protected $warnings = [];

    // =========================================================================
    // CONSTRUCTOR
    // =========================================================================

    /**
     * Constructor
     *
     * @param DOMDocument $dom The parent XML document
     */
    public function __construct(DOMDocument $dom)
    {
        $this->dom = $dom;
    }

    // =========================================================================
    // PUBLIC API
    // =========================================================================

    /**
     * Set the slip data to build
     *
     * @param array $data Slip data array (typically from gibbonRL24Slip table)
     * @return self
     */
    public function setSlipData(array $data): self
    {
        $this->slipData = $data;
        return $this;
    }

    /**
     * Set the sequential slip number
     *
     * @param int $number Slip number within the transmission
     * @return self
     */
    public function setSlipNumber(int $number): self
    {
        $this->slipNumber = $number;
        return $this;
    }

    /**
     * Build the complete RL-24 slip element
     *
     * @return DOMElement|null The slip element or null on failure
     */
    public function build(): ?DOMElement
    {
        $this->errors = [];
        $this->warnings = [];

        // Validate required data
        if (!$this->validateRequiredData()) {
            return null;
        }

        // Create the main slip element
        $slipElement = $this->createElement(RL24XmlSchema::ELEMENT_SLIP);

        // Build slip identification section
        $this->buildIdentification($slipElement);

        // Build recipient (parent) section
        $this->buildRecipient($slipElement);

        // Build child section
        $this->buildChild($slipElement);

        // Build service period section
        $this->buildServicePeriod($slipElement);

        // Build amount boxes (10-14)
        $this->buildAmounts($slipElement);

        return $slipElement;
    }

    /**
     * Build multiple slips from an array of slip data
     *
     * @param array $slipsData Array of slip data arrays
     * @return array Array of DOMElement objects
     */
    public function buildMultiple(array $slipsData): array
    {
        $elements = [];
        $slipNumber = 1;

        foreach ($slipsData as $slipData) {
            $this->setSlipData($slipData);
            $this->setSlipNumber($slipNumber);

            $element = $this->build();
            if ($element !== null) {
                $elements[] = $element;
                $slipNumber++;
            }
        }

        return $elements;
    }

    /**
     * Get validation errors
     *
     * @return array Array of error messages
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get validation warnings
     *
     * @return array Array of warning messages
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    /**
     * Check if the builder has errors
     *
     * @return bool True if errors exist
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Check if the builder has warnings
     *
     * @return bool True if warnings exist
     */
    public function hasWarnings(): bool
    {
        return !empty($this->warnings);
    }

    /**
     * Reset the builder state
     *
     * @return self
     */
    public function reset(): self
    {
        $this->slipData = [];
        $this->slipNumber = 1;
        $this->errors = [];
        $this->warnings = [];
        return $this;
    }

    // =========================================================================
    // PROTECTED BUILD METHODS
    // =========================================================================

    /**
     * Build the slip identification section
     *
     * @param DOMElement $parent Parent slip element
     */
    protected function buildIdentification(DOMElement $parent): void
    {
        $identification = $this->createElement(RL24XmlSchema::ELEMENT_SLIP_ID);

        // Slip number (NoReleve)
        $this->appendTextElement(
            $identification,
            RL24XmlSchema::ELEMENT_SLIP_NUMBER,
            (string) $this->slipNumber
        );

        // Case A - Slip type code (O=Original, A=Amended, D=Cancelled)
        $caseACode = $this->getSlipValue('caseACode', RL24XmlSchema::CODE_ORIGINAL);
        $this->appendTextElement(
            $identification,
            RL24XmlSchema::ELEMENT_BOX_A,
            $caseACode
        );

        // Case B - Additional code if applicable
        $caseBCode = $this->getSlipValue('caseBCode');
        if (!empty($caseBCode)) {
            $this->appendTextElement(
                $identification,
                RL24XmlSchema::ELEMENT_BOX_B,
                $caseBCode
            );
        }

        // Reference to original slip for amendments/cancellations
        if (in_array($caseACode, [RL24XmlSchema::CODE_AMENDED, RL24XmlSchema::CODE_CANCELLED])) {
            $originalReference = $this->getSlipValue('originalSlipNumber')
                ?? $this->getSlipValue('amendedSlipID');

            if (!empty($originalReference)) {
                $this->appendTextElement(
                    $identification,
                    RL24XmlSchema::ELEMENT_ORIGINAL_SLIP_NUMBER,
                    (string) $originalReference
                );
            } else {
                $this->warnings[] = sprintf(
                    'Slip #%d: %s slip should reference original slip number',
                    $this->slipNumber,
                    RL24XmlSchema::getSlipTypeDescription($caseACode)
                );
            }
        }

        $parent->appendChild($identification);
    }

    /**
     * Build the recipient (parent) section
     *
     * @param DOMElement $parent Parent slip element
     */
    protected function buildRecipient(DOMElement $parent): void
    {
        $recipient = $this->createElement(RL24XmlSchema::ELEMENT_RECIPIENT);

        // SIN (NAS) - optional but recommended
        $sin = $this->getSlipValue('parentSIN');
        if (!empty($sin)) {
            $formattedSIN = RL24XmlSchema::formatSIN($sin);
            if (!empty($formattedSIN)) {
                $this->appendTextElement(
                    $recipient,
                    RL24XmlSchema::ELEMENT_SIN,
                    $formattedSIN
                );
            } else {
                $this->warnings[] = sprintf(
                    'Slip #%d: Invalid SIN format, omitting from XML',
                    $this->slipNumber
                );
            }
        }

        // Recipient name
        $this->buildRecipientName($recipient);

        // Recipient address
        $this->buildRecipientAddress($recipient);

        $parent->appendChild($recipient);
    }

    /**
     * Build the recipient name section
     *
     * @param DOMElement $parent Parent recipient element
     */
    protected function buildRecipientName(DOMElement $parent): void
    {
        $recipientName = $this->createElement(RL24XmlSchema::ELEMENT_RECIPIENT_NAME);

        // Last name (required)
        $lastName = $this->truncateText($this->getSlipValue('parentLastName', ''), 30);
        $this->appendTextElement(
            $recipientName,
            RL24XmlSchema::ELEMENT_LAST_NAME,
            $lastName
        );

        // First name (required)
        $firstName = $this->truncateText($this->getSlipValue('parentFirstName', ''), 30);
        $this->appendTextElement(
            $recipientName,
            RL24XmlSchema::ELEMENT_FIRST_NAME,
            $firstName
        );

        $parent->appendChild($recipientName);
    }

    /**
     * Build the recipient address section
     *
     * @param DOMElement $parent Parent recipient element
     */
    protected function buildRecipientAddress(DOMElement $parent): void
    {
        $recipientAddress = $this->createElement(RL24XmlSchema::ELEMENT_RECIPIENT_ADDRESS);

        // Address line 1
        $addressLine1 = $this->getSlipValue('parentAddressLine1');
        if (!empty($addressLine1)) {
            $this->appendTextElement(
                $recipientAddress,
                RL24XmlSchema::ELEMENT_ADDRESS_LINE_1,
                $this->truncateText($addressLine1, 60)
            );
        }

        // Address line 2
        $addressLine2 = $this->getSlipValue('parentAddressLine2');
        if (!empty($addressLine2)) {
            $this->appendTextElement(
                $recipientAddress,
                RL24XmlSchema::ELEMENT_ADDRESS_LINE_2,
                $this->truncateText($addressLine2, 60)
            );
        }

        // City
        $city = $this->getSlipValue('parentCity');
        if (!empty($city)) {
            $this->appendTextElement(
                $recipientAddress,
                RL24XmlSchema::ELEMENT_CITY,
                $this->truncateText($city, 60)
            );
        }

        // Province
        $province = $this->getSlipValue('parentProvince', RL24XmlSchema::PROVINCE_QUEBEC);
        $this->appendTextElement(
            $recipientAddress,
            RL24XmlSchema::ELEMENT_PROVINCE,
            $province
        );

        // Postal code
        $postalCode = $this->getSlipValue('parentPostalCode');
        if (!empty($postalCode)) {
            $this->appendTextElement(
                $recipientAddress,
                RL24XmlSchema::ELEMENT_POSTAL_CODE,
                $this->formatPostalCode($postalCode)
            );
        }

        // Country
        $this->appendTextElement(
            $recipientAddress,
            RL24XmlSchema::ELEMENT_COUNTRY,
            RL24XmlSchema::COUNTRY_CANADA
        );

        $parent->appendChild($recipientAddress);
    }

    /**
     * Build the child section
     *
     * @param DOMElement $parent Parent slip element
     */
    protected function buildChild(DOMElement $parent): void
    {
        $child = $this->createElement(RL24XmlSchema::ELEMENT_CHILD);

        // Child last name (required)
        $lastName = $this->truncateText($this->getSlipValue('childLastName', ''), 30);
        $this->appendTextElement(
            $child,
            RL24XmlSchema::ELEMENT_CHILD_LAST_NAME,
            $lastName
        );

        // Child first name (required)
        $firstName = $this->truncateText($this->getSlipValue('childFirstName', ''), 30);
        $this->appendTextElement(
            $child,
            RL24XmlSchema::ELEMENT_CHILD_FIRST_NAME,
            $firstName
        );

        // Date of birth (optional but recommended)
        $dob = $this->getSlipValue('childDateOfBirth');
        if (!empty($dob)) {
            $this->appendTextElement(
                $child,
                RL24XmlSchema::ELEMENT_CHILD_DOB,
                RL24XmlSchema::formatDate($dob)
            );
        }

        $parent->appendChild($child);
    }

    /**
     * Build the service period section
     *
     * @param DOMElement $parent Parent slip element
     */
    protected function buildServicePeriod(DOMElement $parent): void
    {
        $period = $this->createElement(RL24XmlSchema::ELEMENT_SERVICE_PERIOD);

        // Period start date
        $startDate = $this->getSlipValue('servicePeriodStart');
        if (empty($startDate)) {
            // Default to January 1st of the tax year
            $taxYear = $this->getSlipValue('taxYear', date('Y'));
            $startDate = $taxYear . '-01-01';
        }
        $this->appendTextElement(
            $period,
            RL24XmlSchema::ELEMENT_PERIOD_START,
            RL24XmlSchema::formatDate($startDate)
        );

        // Period end date
        $endDate = $this->getSlipValue('servicePeriodEnd');
        if (empty($endDate)) {
            // Default to December 31st of the tax year
            $taxYear = $this->getSlipValue('taxYear', date('Y'));
            $endDate = $taxYear . '-12-31';
        }
        $this->appendTextElement(
            $period,
            RL24XmlSchema::ELEMENT_PERIOD_END,
            RL24XmlSchema::formatDate($endDate)
        );

        // Validate date order
        if (strtotime($startDate) > strtotime($endDate)) {
            $this->warnings[] = sprintf(
                'Slip #%d: Service period start date is after end date',
                $this->slipNumber
            );
        }

        $parent->appendChild($period);
    }

    /**
     * Build the amount boxes (10-14)
     *
     * @param DOMElement $parent Parent slip element
     */
    protected function buildAmounts(DOMElement $parent): void
    {
        // Box 10 - Number of days of childcare
        $totalDays = (int) $this->getSlipValue('totalDays', 0);
        $this->appendTextElement(
            $parent,
            RL24XmlSchema::ELEMENT_BOX_10,
            (string) $totalDays
        );

        // Validate days range
        if ($totalDays < 0 || $totalDays > 366) {
            $this->warnings[] = sprintf(
                'Slip #%d: Total days (%d) is outside expected range (0-366)',
                $this->slipNumber,
                $totalDays
            );
        }

        // Box 11 - Childcare expenses paid
        $case11 = (float) $this->getSlipValue('case11Amount', 0);
        $this->appendTextElement(
            $parent,
            RL24XmlSchema::ELEMENT_BOX_11,
            RL24XmlSchema::formatAmount($case11)
        );

        // Box 12 - Eligible childcare expenses
        $case12 = (float) $this->getSlipValue('case12Amount', 0);
        $this->appendTextElement(
            $parent,
            RL24XmlSchema::ELEMENT_BOX_12,
            RL24XmlSchema::formatAmount($case12)
        );

        // Box 13 - Government contributions received (only if > 0)
        $case13 = (float) $this->getSlipValue('case13Amount', 0);
        if ($case13 > 0) {
            $this->appendTextElement(
                $parent,
                RL24XmlSchema::ELEMENT_BOX_13,
                RL24XmlSchema::formatAmount($case13)
            );
        }

        // Box 14 - Net eligible expenses (Box 12 - Box 13, only if > 0)
        $case14 = (float) $this->getSlipValue('case14Amount', 0);
        if ($case14 > 0) {
            $this->appendTextElement(
                $parent,
                RL24XmlSchema::ELEMENT_BOX_14,
                RL24XmlSchema::formatAmount($case14)
            );
        }

        // Validate Box 14 calculation
        $expectedCase14 = $case12 - $case13;
        if (abs($case14 - $expectedCase14) > 0.01) {
            $this->warnings[] = sprintf(
                'Slip #%d: Box 14 (%.2f) does not equal Box 12 - Box 13 (%.2f - %.2f = %.2f)',
                $this->slipNumber,
                $case14,
                $case12,
                $case13,
                $expectedCase14
            );
        }
    }

    // =========================================================================
    // PROTECTED VALIDATION METHODS
    // =========================================================================

    /**
     * Validate required slip data is present
     *
     * @return bool True if valid
     */
    protected function validateRequiredData(): bool
    {
        $isValid = true;

        // Recipient last name is required
        if (empty($this->getSlipValue('parentLastName'))) {
            $this->errors[] = sprintf(
                'Slip #%d: Missing required field: parentLastName',
                $this->slipNumber
            );
            $isValid = false;
        }

        // Recipient first name is required
        if (empty($this->getSlipValue('parentFirstName'))) {
            $this->errors[] = sprintf(
                'Slip #%d: Missing required field: parentFirstName',
                $this->slipNumber
            );
            $isValid = false;
        }

        // Child last name is required
        if (empty($this->getSlipValue('childLastName'))) {
            $this->errors[] = sprintf(
                'Slip #%d: Missing required field: childLastName',
                $this->slipNumber
            );
            $isValid = false;
        }

        // Child first name is required
        if (empty($this->getSlipValue('childFirstName'))) {
            $this->errors[] = sprintf(
                'Slip #%d: Missing required field: childFirstName',
                $this->slipNumber
            );
            $isValid = false;
        }

        // Case A code must be valid
        $caseACode = $this->getSlipValue('caseACode', RL24XmlSchema::CODE_ORIGINAL);
        if (!in_array($caseACode, RL24XmlSchema::getValidSlipTypeCodes())) {
            $this->errors[] = sprintf(
                'Slip #%d: Invalid Case A code: %s',
                $this->slipNumber,
                $caseACode
            );
            $isValid = false;
        }

        return $isValid;
    }

    // =========================================================================
    // PROTECTED HELPER METHODS
    // =========================================================================

    /**
     * Get a value from slip data with optional default
     *
     * @param string $key Data key
     * @param mixed $default Default value if key not found
     * @return mixed Value or default
     */
    protected function getSlipValue(string $key, $default = null)
    {
        return $this->slipData[$key] ?? $default;
    }

    /**
     * Create a DOM element
     *
     * @param string $name Element name
     * @return DOMElement
     */
    protected function createElement(string $name): DOMElement
    {
        return $this->dom->createElement($name);
    }

    /**
     * Create and append a text element
     *
     * @param DOMElement $parent Parent element
     * @param string $name Element name
     * @param string $value Text value
     * @return DOMElement The created element
     */
    protected function appendTextElement(DOMElement $parent, string $name, string $value): DOMElement
    {
        $element = $this->createElement($name);
        $element->appendChild($this->dom->createTextNode($this->sanitizeXmlText($value)));
        $parent->appendChild($element);
        return $element;
    }

    /**
     * Truncate text to maximum length
     *
     * @param string $text Text to truncate
     * @param int $maxLength Maximum length
     * @return string Truncated text
     */
    protected function truncateText(string $text, int $maxLength): string
    {
        $text = trim($text);

        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        return mb_substr($text, 0, $maxLength);
    }

    /**
     * Format postal code (uppercase, with space for Canadian format)
     *
     * @param string $postalCode Raw postal code
     * @return string Formatted postal code
     */
    protected function formatPostalCode(string $postalCode): string
    {
        // Canadian postal codes should be uppercase with space
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $postalCode));

        if (strlen($code) === 6) {
            return substr($code, 0, 3) . ' ' . substr($code, 3, 3);
        }

        return $code;
    }

    /**
     * Sanitize text for XML content
     *
     * @param string $text Text to sanitize
     * @return string Sanitized text
     */
    protected function sanitizeXmlText(string $text): string
    {
        // Remove invalid XML characters
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);

        // Normalize whitespace
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }

    // =========================================================================
    // STATIC FACTORY METHODS
    // =========================================================================

    /**
     * Create a slip builder from slip data array
     *
     * @param DOMDocument $dom The parent XML document
     * @param array $slipData Slip data array
     * @param int $slipNumber Sequential slip number
     * @return self
     */
    public static function fromSlipData(DOMDocument $dom, array $slipData, int $slipNumber = 1): self
    {
        $builder = new self($dom);
        $builder->setSlipData($slipData);
        $builder->setSlipNumber($slipNumber);
        return $builder;
    }

    /**
     * Create a slip element directly from data (convenience method)
     *
     * @param DOMDocument $dom The parent XML document
     * @param array $slipData Slip data array
     * @param int $slipNumber Sequential slip number
     * @return DOMElement|null The slip element or null on failure
     */
    public static function createSlipElement(DOMDocument $dom, array $slipData, int $slipNumber = 1): ?DOMElement
    {
        $builder = self::fromSlipData($dom, $slipData, $slipNumber);
        return $builder->build();
    }

    /**
     * Create an original slip element
     *
     * @param DOMDocument $dom The parent XML document
     * @param array $slipData Slip data array
     * @param int $slipNumber Sequential slip number
     * @return DOMElement|null The slip element or null on failure
     */
    public static function createOriginalSlip(DOMDocument $dom, array $slipData, int $slipNumber = 1): ?DOMElement
    {
        $slipData['caseACode'] = RL24XmlSchema::CODE_ORIGINAL;
        return self::createSlipElement($dom, $slipData, $slipNumber);
    }

    /**
     * Create an amended slip element
     *
     * @param DOMDocument $dom The parent XML document
     * @param array $slipData Slip data array
     * @param int $slipNumber Sequential slip number
     * @param int|string $originalSlipNumber Reference to original slip
     * @return DOMElement|null The slip element or null on failure
     */
    public static function createAmendedSlip(
        DOMDocument $dom,
        array $slipData,
        int $slipNumber,
        $originalSlipNumber
    ): ?DOMElement {
        $slipData['caseACode'] = RL24XmlSchema::CODE_AMENDED;
        $slipData['originalSlipNumber'] = $originalSlipNumber;
        return self::createSlipElement($dom, $slipData, $slipNumber);
    }

    /**
     * Create a cancelled slip element
     *
     * @param DOMDocument $dom The parent XML document
     * @param array $slipData Slip data array
     * @param int $slipNumber Sequential slip number
     * @param int|string $originalSlipNumber Reference to original slip
     * @return DOMElement|null The slip element or null on failure
     */
    public static function createCancelledSlip(
        DOMDocument $dom,
        array $slipData,
        int $slipNumber,
        $originalSlipNumber
    ): ?DOMElement {
        $slipData['caseACode'] = RL24XmlSchema::CODE_CANCELLED;
        $slipData['originalSlipNumber'] = $originalSlipNumber;
        // Cancelled slips should have zero amounts
        $slipData['totalDays'] = 0;
        $slipData['case11Amount'] = 0;
        $slipData['case12Amount'] = 0;
        $slipData['case13Amount'] = 0;
        $slipData['case14Amount'] = 0;
        return self::createSlipElement($dom, $slipData, $slipNumber);
    }

    /**
     * Calculate summary totals from an array of slip data
     *
     * @param array $slipsData Array of slip data arrays
     * @return array Summary totals
     */
    public static function calculateSummaryTotals(array $slipsData): array
    {
        $totals = [
            'totalSlips' => 0,
            'totalDays' => 0,
            'totalCase11' => 0.00,
            'totalCase12' => 0.00,
            'totalCase13' => 0.00,
            'totalCase14' => 0.00,
        ];

        foreach ($slipsData as $slip) {
            $totals['totalSlips']++;
            $totals['totalDays'] += (int) ($slip['totalDays'] ?? 0);
            $totals['totalCase11'] += (float) ($slip['case11Amount'] ?? 0);
            $totals['totalCase12'] += (float) ($slip['case12Amount'] ?? 0);
            $totals['totalCase13'] += (float) ($slip['case13Amount'] ?? 0);
            $totals['totalCase14'] += (float) ($slip['case14Amount'] ?? 0);
        }

        return $totals;
    }
}
