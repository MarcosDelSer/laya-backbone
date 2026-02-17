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
use DOMException;

/**
 * RL-24 XML Generator
 *
 * Generates compliant RL-24 XML files for Revenu Québec submission
 * using DOMDocument for XML construction with proper namespace handling.
 *
 * Output file format: AAPPPPPPSSS.xml
 * - AA: Last 2 digits of tax year
 * - PPPPPP: 6-digit preparer number
 * - SSS: 3-digit sequence number
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class RL24XmlGenerator
{
    /**
     * @var DOMDocument The XML document being built
     */
    protected $dom;

    /**
     * @var DOMElement Root element of the transmission
     */
    protected $rootElement;

    /**
     * @var array Transmission data (provider info, tax year, etc.)
     */
    protected $transmissionData = [];

    /**
     * @var array Array of slip data to include in the XML
     */
    protected $slips = [];

    /**
     * @var array Summary totals calculated from slips
     */
    protected $summaryTotals = [];

    /**
     * @var array Errors encountered during generation
     */
    protected $errors = [];

    /**
     * Constructor - initializes the DOMDocument
     */
    public function __construct()
    {
        $this->dom = new DOMDocument('1.0', 'UTF-8');
        $this->dom->formatOutput = true;
        $this->dom->preserveWhiteSpace = false;
    }

    /**
     * Set transmission data (provider info, preparer number, tax year, etc.)
     *
     * @param array $data Transmission data
     * @return self
     */
    public function setTransmissionData(array $data): self
    {
        $this->transmissionData = array_merge([
            'taxYear' => date('Y'),
            'sequenceNumber' => 1,
            'preparerNumber' => '',
            'transmissionType' => RL24XmlSchema::TRANSMISSION_ORIGINAL,
            'certificationNumber' => '',
            'softwareName' => 'Gibbon RL24 Submission',
            'softwareVersion' => '1.0',
            'providerName' => '',
            'providerNameLine2' => '',
            'providerNEQ' => '',
            'providerAddressLine1' => '',
            'providerAddressLine2' => '',
            'providerCity' => '',
            'providerProvince' => RL24XmlSchema::PROVINCE_QUEBEC,
            'providerPostalCode' => '',
        ], $data);

        return $this;
    }

    /**
     * Add a slip to the XML generation queue
     *
     * @param array $slip Slip data matching gibbonRL24Slip table structure
     * @return self
     */
    public function addSlip(array $slip): self
    {
        $this->slips[] = $slip;
        return $this;
    }

    /**
     * Add multiple slips at once
     *
     * @param array $slips Array of slip data
     * @return self
     */
    public function addSlips(array $slips): self
    {
        foreach ($slips as $slip) {
            $this->addSlip($slip);
        }
        return $this;
    }

    /**
     * Clear all slips from the queue
     *
     * @return self
     */
    public function clearSlips(): self
    {
        $this->slips = [];
        return $this;
    }

    /**
     * Generate the complete RL-24 XML document
     *
     * @return bool True on success, false if errors occurred
     */
    public function generate(): bool
    {
        $this->errors = [];

        try {
            // Validate we have necessary data
            if (!$this->validateTransmissionData()) {
                return false;
            }

            if (empty($this->slips)) {
                $this->errors[] = 'No slips to include in the transmission';
                return false;
            }

            // Check maximum slips per file
            if (count($this->slips) > RL24XmlSchema::MAX_SLIPS_PER_FILE) {
                $this->errors[] = sprintf(
                    'Too many slips (%d). Maximum allowed is %d per file.',
                    count($this->slips),
                    RL24XmlSchema::MAX_SLIPS_PER_FILE
                );
                return false;
            }

            // Calculate summary totals
            $this->calculateSummaryTotals();

            // Build the XML structure
            $this->createRootElement();
            $this->createHeader();
            $this->createGroup();

            return true;
        } catch (DOMException $e) {
            $this->errors[] = 'XML generation error: ' . $e->getMessage();
            return false;
        }
    }

    /**
     * Get the generated XML as a string
     *
     * @return string The XML content
     */
    public function getXmlString(): string
    {
        return $this->dom->saveXML();
    }

    /**
     * Save the generated XML to a file
     *
     * @param string $filePath Full path to save the XML file
     * @return bool True on success
     */
    public function saveToFile(string $filePath): bool
    {
        $directory = dirname($filePath);

        if (!is_dir($directory)) {
            if (!mkdir($directory, 0755, true)) {
                $this->errors[] = 'Failed to create directory: ' . $directory;
                return false;
            }
        }

        $bytes = $this->dom->save($filePath);

        if ($bytes === false) {
            $this->errors[] = 'Failed to save XML file: ' . $filePath;
            return false;
        }

        // Check file size
        if ($bytes > RL24XmlSchema::MAX_FILE_SIZE_BYTES) {
            $this->errors[] = sprintf(
                'Generated file exceeds maximum size (%d bytes). Maximum allowed is %d bytes.',
                $bytes,
                RL24XmlSchema::MAX_FILE_SIZE_BYTES
            );
            return false;
        }

        return true;
    }

    /**
     * Generate the XML filename in AAPPPPPPSSS.xml format
     *
     * @return string Generated filename
     */
    public function getFilename(): string
    {
        return RL24XmlSchema::generateFilename(
            (int) $this->transmissionData['taxYear'],
            $this->transmissionData['preparerNumber'],
            (int) $this->transmissionData['sequenceNumber']
        );
    }

    /**
     * Get any errors that occurred during generation
     *
     * @return array Array of error messages
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Check if the generator has errors
     *
     * @return bool True if errors exist
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Get the calculated summary totals
     *
     * @return array Summary totals
     */
    public function getSummaryTotals(): array
    {
        return $this->summaryTotals;
    }

    /**
     * Validate required transmission data is present
     *
     * @return bool True if valid
     */
    protected function validateTransmissionData(): bool
    {
        $isValid = true;

        if (empty($this->transmissionData['taxYear'])) {
            $this->errors[] = 'Tax year is required';
            $isValid = false;
        }

        if (empty($this->transmissionData['preparerNumber'])) {
            $this->errors[] = 'Preparer number is required';
            $isValid = false;
        }

        if (empty($this->transmissionData['providerName'])) {
            $this->errors[] = 'Provider name is required';
            $isValid = false;
        }

        if (empty($this->transmissionData['providerNEQ'])) {
            $this->errors[] = 'Provider NEQ is required';
            $isValid = false;
        } elseif (!RL24XmlSchema::isValidNEQ($this->transmissionData['providerNEQ'])) {
            $this->errors[] = 'Invalid provider NEQ format';
            $isValid = false;
        }

        return $isValid;
    }

    /**
     * Calculate summary totals from all slips
     */
    protected function calculateSummaryTotals(): void
    {
        $this->summaryTotals = [
            'totalSlips' => 0,
            'totalDays' => 0,
            'totalCase11' => 0.00,
            'totalCase12' => 0.00,
            'totalCase13' => 0.00,
            'totalCase14' => 0.00,
        ];

        foreach ($this->slips as $slip) {
            $this->summaryTotals['totalSlips']++;
            $this->summaryTotals['totalDays'] += (int) ($slip['totalDays'] ?? 0);
            $this->summaryTotals['totalCase11'] += (float) ($slip['case11Amount'] ?? 0);
            $this->summaryTotals['totalCase12'] += (float) ($slip['case12Amount'] ?? 0);
            $this->summaryTotals['totalCase13'] += (float) ($slip['case13Amount'] ?? 0);
            $this->summaryTotals['totalCase14'] += (float) ($slip['case14Amount'] ?? 0);
        }
    }

    /**
     * Create the root Transmission element with namespaces
     */
    protected function createRootElement(): void
    {
        $this->rootElement = $this->dom->createElementNS(
            RL24XmlSchema::NS_RELEVE,
            RL24XmlSchema::ELEMENT_ROOT
        );

        $this->rootElement->setAttributeNS(
            'http://www.w3.org/2000/xmlns/',
            'xmlns:xsi',
            RL24XmlSchema::NS_XSI
        );

        $this->dom->appendChild($this->rootElement);
    }

    /**
     * Create the header (Entete) element with transmitter info
     */
    protected function createHeader(): void
    {
        $header = $this->createElement(RL24XmlSchema::ELEMENT_HEADER);

        // Transmitter info
        $transmitter = $this->createElement(RL24XmlSchema::ELEMENT_TRANSMITTER);

        $this->appendTextElement($transmitter, RL24XmlSchema::ELEMENT_TRANSMITTER_NUMBER,
            $this->formatTransmitterNumber($this->transmissionData['preparerNumber']));

        $this->appendTextElement($transmitter, RL24XmlSchema::ELEMENT_TRANSMISSION_TYPE,
            $this->transmissionData['transmissionType']);

        $this->appendTextElement($transmitter, RL24XmlSchema::ELEMENT_TAX_YEAR,
            (string) $this->transmissionData['taxYear']);

        $this->appendTextElement($transmitter, RL24XmlSchema::ELEMENT_SEQUENCE_NUMBER,
            str_pad((string) $this->transmissionData['sequenceNumber'], 3, '0', STR_PAD_LEFT));

        // Optional certification number
        if (!empty($this->transmissionData['certificationNumber'])) {
            $this->appendTextElement($transmitter, RL24XmlSchema::ELEMENT_CERTIFICATION,
                $this->transmissionData['certificationNumber']);
        }

        // Software info
        $this->appendTextElement($transmitter, RL24XmlSchema::ELEMENT_SOFTWARE_NAME,
            $this->transmissionData['softwareName']);

        $this->appendTextElement($transmitter, RL24XmlSchema::ELEMENT_SOFTWARE_VERSION,
            $this->transmissionData['softwareVersion']);

        $header->appendChild($transmitter);
        $this->rootElement->appendChild($header);
    }

    /**
     * Create the group (Groupe) element containing issuer, slips, and summary
     */
    protected function createGroup(): void
    {
        $group = $this->createElement(RL24XmlSchema::ELEMENT_GROUP);

        // Create issuer (Emetteur) element
        $this->createIssuer($group);

        // Create individual RL-24 slips
        foreach ($this->slips as $index => $slip) {
            $this->createSlip($group, $slip, $index + 1);
        }

        // Create summary (Sommaire) element
        $this->createSummary($group);

        $this->rootElement->appendChild($group);
    }

    /**
     * Create the issuer (Emetteur) element
     *
     * @param DOMElement $parent Parent element to append to
     */
    protected function createIssuer(DOMElement $parent): void
    {
        $issuer = $this->createElement(RL24XmlSchema::ELEMENT_ISSUER);

        // NEQ
        $neq = RL24XmlSchema::formatSIN($this->transmissionData['providerNEQ']);
        if (strlen($neq) !== RL24XmlSchema::NEQ_LENGTH) {
            // Use cleaned version even if not full length
            $neq = preg_replace('/[^0-9]/', '', $this->transmissionData['providerNEQ']);
        }
        $this->appendTextElement($issuer, RL24XmlSchema::ELEMENT_NEQ, $neq);

        // Issuer name
        $issuerName = $this->createElement(RL24XmlSchema::ELEMENT_ISSUER_NAME);
        $this->appendTextElement($issuerName, RL24XmlSchema::ELEMENT_ISSUER_NAME_1,
            $this->truncateText($this->transmissionData['providerName'], 60));

        if (!empty($this->transmissionData['providerNameLine2'])) {
            $this->appendTextElement($issuerName, RL24XmlSchema::ELEMENT_ISSUER_NAME_2,
                $this->truncateText($this->transmissionData['providerNameLine2'], 60));
        }
        $issuer->appendChild($issuerName);

        // Issuer address
        $issuerAddress = $this->createElement(RL24XmlSchema::ELEMENT_ISSUER_ADDRESS);

        if (!empty($this->transmissionData['providerAddressLine1'])) {
            $this->appendTextElement($issuerAddress, RL24XmlSchema::ELEMENT_ADDRESS_LINE_1,
                $this->truncateText($this->transmissionData['providerAddressLine1'], 60));
        }

        if (!empty($this->transmissionData['providerAddressLine2'])) {
            $this->appendTextElement($issuerAddress, RL24XmlSchema::ELEMENT_ADDRESS_LINE_2,
                $this->truncateText($this->transmissionData['providerAddressLine2'], 60));
        }

        if (!empty($this->transmissionData['providerCity'])) {
            $this->appendTextElement($issuerAddress, RL24XmlSchema::ELEMENT_CITY,
                $this->truncateText($this->transmissionData['providerCity'], 60));
        }

        $this->appendTextElement($issuerAddress, RL24XmlSchema::ELEMENT_PROVINCE,
            $this->transmissionData['providerProvince'] ?: RL24XmlSchema::PROVINCE_QUEBEC);

        if (!empty($this->transmissionData['providerPostalCode'])) {
            $this->appendTextElement($issuerAddress, RL24XmlSchema::ELEMENT_POSTAL_CODE,
                $this->formatPostalCode($this->transmissionData['providerPostalCode']));
        }

        $this->appendTextElement($issuerAddress, RL24XmlSchema::ELEMENT_COUNTRY,
            RL24XmlSchema::COUNTRY_CANADA);

        $issuer->appendChild($issuerAddress);
        $parent->appendChild($issuer);
    }

    /**
     * Create an individual RL-24 slip element
     *
     * @param DOMElement $parent Parent element to append to
     * @param array $slip Slip data
     * @param int $slipNumber Sequential slip number
     */
    protected function createSlip(DOMElement $parent, array $slip, int $slipNumber): void
    {
        $slipElement = $this->createElement(RL24XmlSchema::ELEMENT_SLIP);

        // Slip identification
        $slipId = $this->createElement(RL24XmlSchema::ELEMENT_SLIP_ID);

        $this->appendTextElement($slipId, RL24XmlSchema::ELEMENT_SLIP_NUMBER, (string) $slipNumber);

        $caseACode = $slip['caseACode'] ?? RL24XmlSchema::CODE_ORIGINAL;
        $this->appendTextElement($slipId, RL24XmlSchema::ELEMENT_BOX_A, $caseACode);

        if (!empty($slip['caseBCode'])) {
            $this->appendTextElement($slipId, RL24XmlSchema::ELEMENT_BOX_B, $slip['caseBCode']);
        }

        // Reference to original slip for amendments/cancellations
        if (in_array($caseACode, [RL24XmlSchema::CODE_AMENDED, RL24XmlSchema::CODE_CANCELLED])) {
            if (!empty($slip['amendedSlipID']) || !empty($slip['originalSlipNumber'])) {
                $originalNumber = $slip['originalSlipNumber'] ?? $slip['amendedSlipID'];
                $this->appendTextElement($slipId, RL24XmlSchema::ELEMENT_ORIGINAL_SLIP_NUMBER,
                    (string) $originalNumber);
            }
        }

        $slipElement->appendChild($slipId);

        // Recipient (parent) info
        $this->createRecipient($slipElement, $slip);

        // Child info
        $this->createChild($slipElement, $slip);

        // Service period
        $this->createServicePeriod($slipElement, $slip);

        // Amounts (Boxes 10-14)
        $this->appendTextElement($slipElement, RL24XmlSchema::ELEMENT_BOX_10,
            (string) ((int) ($slip['totalDays'] ?? 0)));

        $this->appendTextElement($slipElement, RL24XmlSchema::ELEMENT_BOX_11,
            RL24XmlSchema::formatAmount((float) ($slip['case11Amount'] ?? 0)));

        $this->appendTextElement($slipElement, RL24XmlSchema::ELEMENT_BOX_12,
            RL24XmlSchema::formatAmount((float) ($slip['case12Amount'] ?? 0)));

        if (isset($slip['case13Amount']) && (float) $slip['case13Amount'] > 0) {
            $this->appendTextElement($slipElement, RL24XmlSchema::ELEMENT_BOX_13,
                RL24XmlSchema::formatAmount((float) $slip['case13Amount']));
        }

        if (isset($slip['case14Amount']) && (float) $slip['case14Amount'] > 0) {
            $this->appendTextElement($slipElement, RL24XmlSchema::ELEMENT_BOX_14,
                RL24XmlSchema::formatAmount((float) $slip['case14Amount']));
        }

        $parent->appendChild($slipElement);
    }

    /**
     * Create recipient (parent) element within a slip
     *
     * @param DOMElement $parent Parent slip element
     * @param array $slip Slip data
     */
    protected function createRecipient(DOMElement $parent, array $slip): void
    {
        $recipient = $this->createElement(RL24XmlSchema::ELEMENT_RECIPIENT);

        // SIN
        if (!empty($slip['parentSIN'])) {
            $sin = RL24XmlSchema::formatSIN($slip['parentSIN']);
            if (!empty($sin)) {
                $this->appendTextElement($recipient, RL24XmlSchema::ELEMENT_SIN, $sin);
            }
        }

        // Recipient name
        $recipientName = $this->createElement(RL24XmlSchema::ELEMENT_RECIPIENT_NAME);
        $this->appendTextElement($recipientName, RL24XmlSchema::ELEMENT_LAST_NAME,
            $this->truncateText($slip['parentLastName'] ?? '', 30));
        $this->appendTextElement($recipientName, RL24XmlSchema::ELEMENT_FIRST_NAME,
            $this->truncateText($slip['parentFirstName'] ?? '', 30));
        $recipient->appendChild($recipientName);

        // Recipient address
        $recipientAddress = $this->createElement(RL24XmlSchema::ELEMENT_RECIPIENT_ADDRESS);

        if (!empty($slip['parentAddressLine1'])) {
            $this->appendTextElement($recipientAddress, RL24XmlSchema::ELEMENT_ADDRESS_LINE_1,
                $this->truncateText($slip['parentAddressLine1'], 60));
        }

        if (!empty($slip['parentAddressLine2'])) {
            $this->appendTextElement($recipientAddress, RL24XmlSchema::ELEMENT_ADDRESS_LINE_2,
                $this->truncateText($slip['parentAddressLine2'], 60));
        }

        if (!empty($slip['parentCity'])) {
            $this->appendTextElement($recipientAddress, RL24XmlSchema::ELEMENT_CITY,
                $this->truncateText($slip['parentCity'], 60));
        }

        $this->appendTextElement($recipientAddress, RL24XmlSchema::ELEMENT_PROVINCE,
            $slip['parentProvince'] ?? RL24XmlSchema::PROVINCE_QUEBEC);

        if (!empty($slip['parentPostalCode'])) {
            $this->appendTextElement($recipientAddress, RL24XmlSchema::ELEMENT_POSTAL_CODE,
                $this->formatPostalCode($slip['parentPostalCode']));
        }

        $this->appendTextElement($recipientAddress, RL24XmlSchema::ELEMENT_COUNTRY,
            RL24XmlSchema::COUNTRY_CANADA);

        $recipient->appendChild($recipientAddress);
        $parent->appendChild($recipient);
    }

    /**
     * Create child element within a slip
     *
     * @param DOMElement $parent Parent slip element
     * @param array $slip Slip data
     */
    protected function createChild(DOMElement $parent, array $slip): void
    {
        $child = $this->createElement(RL24XmlSchema::ELEMENT_CHILD);

        $this->appendTextElement($child, RL24XmlSchema::ELEMENT_CHILD_LAST_NAME,
            $this->truncateText($slip['childLastName'] ?? '', 30));

        $this->appendTextElement($child, RL24XmlSchema::ELEMENT_CHILD_FIRST_NAME,
            $this->truncateText($slip['childFirstName'] ?? '', 30));

        if (!empty($slip['childDateOfBirth'])) {
            $this->appendTextElement($child, RL24XmlSchema::ELEMENT_CHILD_DOB,
                RL24XmlSchema::formatDate($slip['childDateOfBirth']));
        }

        $parent->appendChild($child);
    }

    /**
     * Create service period element within a slip
     *
     * @param DOMElement $parent Parent slip element
     * @param array $slip Slip data
     */
    protected function createServicePeriod(DOMElement $parent, array $slip): void
    {
        $period = $this->createElement(RL24XmlSchema::ELEMENT_SERVICE_PERIOD);

        $this->appendTextElement($period, RL24XmlSchema::ELEMENT_PERIOD_START,
            RL24XmlSchema::formatDate($slip['servicePeriodStart'] ?? date('Y-01-01')));

        $this->appendTextElement($period, RL24XmlSchema::ELEMENT_PERIOD_END,
            RL24XmlSchema::formatDate($slip['servicePeriodEnd'] ?? date('Y-12-31')));

        $parent->appendChild($period);
    }

    /**
     * Create summary (Sommaire) element with totals
     *
     * @param DOMElement $parent Parent group element
     */
    protected function createSummary(DOMElement $parent): void
    {
        $summary = $this->createElement(RL24XmlSchema::ELEMENT_SUMMARY);

        $this->appendTextElement($summary, RL24XmlSchema::ELEMENT_TOTAL_SLIPS,
            (string) $this->summaryTotals['totalSlips']);

        $this->appendTextElement($summary, RL24XmlSchema::ELEMENT_TOTAL_DAYS,
            (string) $this->summaryTotals['totalDays']);

        $this->appendTextElement($summary, RL24XmlSchema::ELEMENT_TOTAL_BOX_11,
            RL24XmlSchema::formatAmount($this->summaryTotals['totalCase11']));

        $this->appendTextElement($summary, RL24XmlSchema::ELEMENT_TOTAL_BOX_12,
            RL24XmlSchema::formatAmount($this->summaryTotals['totalCase12']));

        if ($this->summaryTotals['totalCase13'] > 0) {
            $this->appendTextElement($summary, RL24XmlSchema::ELEMENT_TOTAL_BOX_13,
                RL24XmlSchema::formatAmount($this->summaryTotals['totalCase13']));
        }

        if ($this->summaryTotals['totalCase14'] > 0) {
            $this->appendTextElement($summary, RL24XmlSchema::ELEMENT_TOTAL_BOX_14,
                RL24XmlSchema::formatAmount($this->summaryTotals['totalCase14']));
        }

        $parent->appendChild($summary);
    }

    /**
     * Helper to create a DOM element
     *
     * @param string $name Element name
     * @return DOMElement
     */
    protected function createElement(string $name): DOMElement
    {
        return $this->dom->createElement($name);
    }

    /**
     * Helper to create and append a text element
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
     * Format transmitter number (NP + 6 digits)
     *
     * @param string $number Raw preparer number
     * @return string Formatted transmitter number
     */
    protected function formatTransmitterNumber(string $number): string
    {
        // Strip any existing prefix
        $number = preg_replace('/^NP/i', '', trim($number));

        // Extract only digits
        $digits = preg_replace('/[^0-9]/', '', $number);

        // Pad to 6 digits
        $digits = str_pad(substr($digits, 0, 6), 6, '0', STR_PAD_LEFT);

        return RL24XmlSchema::TRANSMITTER_PREFIX . $digits;
    }

    /**
     * Format postal code (remove spaces, uppercase)
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

    /**
     * Reset the generator for a new transmission
     *
     * @return self
     */
    public function reset(): self
    {
        $this->dom = new DOMDocument('1.0', 'UTF-8');
        $this->dom->formatOutput = true;
        $this->dom->preserveWhiteSpace = false;

        $this->rootElement = null;
        $this->transmissionData = [];
        $this->slips = [];
        $this->summaryTotals = [];
        $this->errors = [];

        return $this;
    }

    /**
     * Get the DOMDocument instance for advanced manipulation
     *
     * @return DOMDocument
     */
    public function getDomDocument(): DOMDocument
    {
        return $this->dom;
    }
}
