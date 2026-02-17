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
use DOMXPath;
use LibXMLError;

/**
 * RL-24 XML Validator
 *
 * Validates generated RL-24 XML files against Revenu Québec schema
 * specifications. Performs well-formedness checks, structural validation,
 * data format validation, and business rule validation.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class RL24XmlValidator
{
    // =========================================================================
    // ERROR SEVERITY LEVELS
    // =========================================================================

    /**
     * Error severity: Fatal error - transmission will be rejected.
     */
    const SEVERITY_ERROR = 'error';

    /**
     * Error severity: Warning - transmission may be accepted but with issues.
     */
    const SEVERITY_WARNING = 'warning';

    /**
     * Error severity: Notice - informational, no action required.
     */
    const SEVERITY_NOTICE = 'notice';

    // =========================================================================
    // VALIDATION ERROR CODES
    // =========================================================================

    /**
     * Error code: XML is not well-formed.
     */
    const ERR_XML_MALFORMED = 'XML_MALFORMED';

    /**
     * Error code: Required element is missing.
     */
    const ERR_MISSING_ELEMENT = 'MISSING_ELEMENT';

    /**
     * Error code: Invalid element value.
     */
    const ERR_INVALID_VALUE = 'INVALID_VALUE';

    /**
     * Error code: Invalid format (SIN, NEQ, date, etc.).
     */
    const ERR_INVALID_FORMAT = 'INVALID_FORMAT';

    /**
     * Error code: Business rule violation.
     */
    const ERR_BUSINESS_RULE = 'BUSINESS_RULE';

    /**
     * Error code: Schema validation failed.
     */
    const ERR_SCHEMA_INVALID = 'SCHEMA_INVALID';

    /**
     * Error code: File constraint violated (size, slip count).
     */
    const ERR_FILE_CONSTRAINT = 'FILE_CONSTRAINT';

    /**
     * Error code: Summary totals mismatch.
     */
    const ERR_SUMMARY_MISMATCH = 'SUMMARY_MISMATCH';

    // =========================================================================
    // INSTANCE PROPERTIES
    // =========================================================================

    /**
     * @var DOMDocument The XML document being validated
     */
    protected $dom;

    /**
     * @var DOMXPath XPath object for querying the document
     */
    protected $xpath;

    /**
     * @var array Validation errors collected during validation
     */
    protected $errors = [];

    /**
     * @var array Validation warnings collected during validation
     */
    protected $warnings = [];

    /**
     * @var bool Whether to use strict validation mode
     */
    protected $strictMode = true;

    /**
     * @var string|null Path to XSD schema file for validation
     */
    protected $schemaPath = null;

    // =========================================================================
    // CONSTRUCTOR
    // =========================================================================

    /**
     * Constructor
     *
     * @param bool $strictMode Whether to use strict validation mode
     */
    public function __construct(bool $strictMode = true)
    {
        $this->strictMode = $strictMode;
    }

    // =========================================================================
    // PUBLIC API
    // =========================================================================

    /**
     * Validate an XML string
     *
     * @param string $xmlString XML content to validate
     * @return bool True if validation passes without errors
     */
    public function validateString(string $xmlString): bool
    {
        $this->reset();

        // Check for empty string
        if (empty(trim($xmlString))) {
            $this->addError(
                self::ERR_XML_MALFORMED,
                'XML content is empty',
                self::SEVERITY_ERROR
            );
            return false;
        }

        // Load and validate XML well-formedness
        if (!$this->loadXml($xmlString)) {
            return false;
        }

        // Perform all validations
        return $this->performValidation();
    }

    /**
     * Validate an XML file
     *
     * @param string $filePath Path to XML file
     * @return bool True if validation passes without errors
     */
    public function validateFile(string $filePath): bool
    {
        $this->reset();

        // Check file exists
        if (!file_exists($filePath)) {
            $this->addError(
                self::ERR_FILE_CONSTRAINT,
                sprintf('File not found: %s', $filePath),
                self::SEVERITY_ERROR
            );
            return false;
        }

        // Check file is readable
        if (!is_readable($filePath)) {
            $this->addError(
                self::ERR_FILE_CONSTRAINT,
                sprintf('File is not readable: %s', $filePath),
                self::SEVERITY_ERROR
            );
            return false;
        }

        // Check file size constraint
        $fileSize = filesize($filePath);
        if ($fileSize > RL24XmlSchema::MAX_FILE_SIZE_BYTES) {
            $this->addError(
                self::ERR_FILE_CONSTRAINT,
                sprintf(
                    'File size (%d bytes) exceeds maximum allowed (%d bytes)',
                    $fileSize,
                    RL24XmlSchema::MAX_FILE_SIZE_BYTES
                ),
                self::SEVERITY_ERROR
            );
            return false;
        }

        // Read and validate
        $xmlString = file_get_contents($filePath);
        if ($xmlString === false) {
            $this->addError(
                self::ERR_XML_MALFORMED,
                'Failed to read XML file',
                self::SEVERITY_ERROR
            );
            return false;
        }

        return $this->validateString($xmlString);
    }

    /**
     * Validate a DOMDocument directly
     *
     * @param DOMDocument $dom DOM document to validate
     * @return bool True if validation passes without errors
     */
    public function validateDom(DOMDocument $dom): bool
    {
        $this->reset();
        $this->dom = $dom;
        $this->xpath = new DOMXPath($this->dom);

        // Register namespaces
        $this->registerNamespaces();

        return $this->performValidation();
    }

    /**
     * Set the path to the XSD schema file for validation
     *
     * @param string $schemaPath Path to XSD file
     * @return self
     */
    public function setSchemaPath(string $schemaPath): self
    {
        $this->schemaPath = $schemaPath;
        return $this;
    }

    /**
     * Set strict validation mode
     *
     * @param bool $strict Whether to use strict mode
     * @return self
     */
    public function setStrictMode(bool $strict): self
    {
        $this->strictMode = $strict;
        return $this;
    }

    /**
     * Get all validation errors
     *
     * @return array Array of error objects
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get all validation warnings
     *
     * @return array Array of warning objects
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    /**
     * Get all validation messages (errors and warnings)
     *
     * @return array Combined array of errors and warnings
     */
    public function getAllMessages(): array
    {
        return array_merge($this->errors, $this->warnings);
    }

    /**
     * Check if validation has errors
     *
     * @return bool True if errors exist
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Check if validation has warnings
     *
     * @return bool True if warnings exist
     */
    public function hasWarnings(): bool
    {
        return !empty($this->warnings);
    }

    /**
     * Check if validation is completely clean (no errors or warnings)
     *
     * @return bool True if no issues found
     */
    public function isClean(): bool
    {
        return empty($this->errors) && empty($this->warnings);
    }

    /**
     * Get validation summary as formatted string
     *
     * @return string Summary string
     */
    public function getSummary(): string
    {
        $errorCount = count($this->errors);
        $warningCount = count($this->warnings);

        if ($errorCount === 0 && $warningCount === 0) {
            return 'Validation passed - no issues found.';
        }

        $parts = [];
        if ($errorCount > 0) {
            $parts[] = sprintf('%d error(s)', $errorCount);
        }
        if ($warningCount > 0) {
            $parts[] = sprintf('%d warning(s)', $warningCount);
        }

        return 'Validation issues: ' . implode(', ', $parts);
    }

    // =========================================================================
    // PROTECTED METHODS
    // =========================================================================

    /**
     * Reset validation state
     */
    protected function reset(): void
    {
        $this->dom = null;
        $this->xpath = null;
        $this->errors = [];
        $this->warnings = [];
    }

    /**
     * Load XML string into DOMDocument
     *
     * @param string $xmlString XML content
     * @return bool True if loaded successfully
     */
    protected function loadXml(string $xmlString): bool
    {
        $this->dom = new DOMDocument();

        // Capture libxml errors
        $previousErrorState = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $loaded = $this->dom->loadXML($xmlString);

        // Process any libxml errors
        $xmlErrors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrorState);

        if (!$loaded) {
            $this->addError(
                self::ERR_XML_MALFORMED,
                'Failed to parse XML document',
                self::SEVERITY_ERROR
            );

            foreach ($xmlErrors as $error) {
                $this->addLibXmlError($error);
            }

            return false;
        }

        // Check for parse warnings
        foreach ($xmlErrors as $error) {
            if ($error->level === LIBXML_ERR_WARNING) {
                $this->addLibXmlError($error, self::SEVERITY_WARNING);
            } elseif ($error->level === LIBXML_ERR_ERROR || $error->level === LIBXML_ERR_FATAL) {
                $this->addLibXmlError($error, self::SEVERITY_ERROR);
            }
        }

        $this->xpath = new DOMXPath($this->dom);
        $this->registerNamespaces();

        return true;
    }

    /**
     * Register XML namespaces with XPath
     */
    protected function registerNamespaces(): void
    {
        if ($this->xpath) {
            $this->xpath->registerNamespace('rl24', RL24XmlSchema::NS_RELEVE);
            $this->xpath->registerNamespace('xsi', RL24XmlSchema::NS_XSI);
        }
    }

    /**
     * Perform all validation checks
     *
     * @return bool True if no errors
     */
    protected function performValidation(): bool
    {
        // Validate against XSD schema if available
        if ($this->schemaPath !== null && file_exists($this->schemaPath)) {
            $this->validateAgainstSchema();
        }

        // Structural validation
        $this->validateRootElement();
        $this->validateHeader();
        $this->validateGroup();

        // Business rule validation
        if ($this->strictMode) {
            $this->validateBusinessRules();
        }

        return !$this->hasErrors();
    }

    /**
     * Validate against XSD schema
     */
    protected function validateAgainstSchema(): void
    {
        if ($this->schemaPath === null || !file_exists($this->schemaPath)) {
            return;
        }

        $previousErrorState = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $valid = $this->dom->schemaValidate($this->schemaPath);

        $xmlErrors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrorState);

        if (!$valid) {
            foreach ($xmlErrors as $error) {
                $this->addError(
                    self::ERR_SCHEMA_INVALID,
                    sprintf('Schema validation: %s (line %d)', trim($error->message), $error->line),
                    self::SEVERITY_ERROR
                );
            }
        }
    }

    /**
     * Validate the root Transmission element
     */
    protected function validateRootElement(): void
    {
        $root = $this->dom->documentElement;

        if ($root === null) {
            $this->addError(
                self::ERR_MISSING_ELEMENT,
                'Missing root element',
                self::SEVERITY_ERROR
            );
            return;
        }

        // Check root element name
        if ($root->localName !== RL24XmlSchema::ELEMENT_ROOT) {
            $this->addError(
                self::ERR_INVALID_VALUE,
                sprintf(
                    'Invalid root element: expected "%s", found "%s"',
                    RL24XmlSchema::ELEMENT_ROOT,
                    $root->localName
                ),
                self::SEVERITY_ERROR
            );
        }

        // Check namespace
        if ($root->namespaceURI !== RL24XmlSchema::NS_RELEVE) {
            $this->addWarning(
                self::ERR_INVALID_VALUE,
                sprintf(
                    'Invalid namespace: expected "%s", found "%s"',
                    RL24XmlSchema::NS_RELEVE,
                    $root->namespaceURI ?? 'none'
                )
            );
        }
    }

    /**
     * Validate the header (Entete) element
     */
    protected function validateHeader(): void
    {
        $headers = $this->dom->getElementsByTagName(RL24XmlSchema::ELEMENT_HEADER);

        if ($headers->length === 0) {
            $this->addError(
                self::ERR_MISSING_ELEMENT,
                sprintf('Missing required element: %s', RL24XmlSchema::ELEMENT_HEADER),
                self::SEVERITY_ERROR
            );
            return;
        }

        $header = $headers->item(0);

        // Validate transmitter element
        $this->validateTransmitter($header);
    }

    /**
     * Validate the transmitter element
     *
     * @param \DOMElement $header Header element
     */
    protected function validateTransmitter($header): void
    {
        $transmitters = $header->getElementsByTagName(RL24XmlSchema::ELEMENT_TRANSMITTER);

        if ($transmitters->length === 0) {
            $this->addError(
                self::ERR_MISSING_ELEMENT,
                sprintf('Missing required element: %s', RL24XmlSchema::ELEMENT_TRANSMITTER),
                self::SEVERITY_ERROR
            );
            return;
        }

        $transmitter = $transmitters->item(0);

        // Validate transmitter number
        $transmitterNumber = $this->getElementValue($transmitter, RL24XmlSchema::ELEMENT_TRANSMITTER_NUMBER);
        if (empty($transmitterNumber)) {
            $this->addError(
                self::ERR_MISSING_ELEMENT,
                sprintf('Missing required element: %s', RL24XmlSchema::ELEMENT_TRANSMITTER_NUMBER),
                self::SEVERITY_ERROR
            );
        } elseif (!RL24XmlSchema::isValidTransmitterNumber($transmitterNumber)) {
            $this->addError(
                self::ERR_INVALID_FORMAT,
                sprintf(
                    'Invalid transmitter number format: "%s". Expected format: NP + 6 digits',
                    $transmitterNumber
                ),
                self::SEVERITY_ERROR
            );
        }

        // Validate transmission type
        $transmissionType = $this->getElementValue($transmitter, RL24XmlSchema::ELEMENT_TRANSMISSION_TYPE);
        if (!empty($transmissionType) && !in_array($transmissionType, RL24XmlSchema::getValidTransmissionTypes())) {
            $this->addError(
                self::ERR_INVALID_VALUE,
                sprintf(
                    'Invalid transmission type: "%s". Valid values: %s',
                    $transmissionType,
                    implode(', ', RL24XmlSchema::getValidTransmissionTypes())
                ),
                self::SEVERITY_ERROR
            );
        }

        // Validate tax year
        $taxYear = $this->getElementValue($transmitter, RL24XmlSchema::ELEMENT_TAX_YEAR);
        if (empty($taxYear)) {
            $this->addError(
                self::ERR_MISSING_ELEMENT,
                sprintf('Missing required element: %s', RL24XmlSchema::ELEMENT_TAX_YEAR),
                self::SEVERITY_ERROR
            );
        } elseif (!$this->isValidTaxYear($taxYear)) {
            $this->addError(
                self::ERR_INVALID_VALUE,
                sprintf('Invalid tax year: "%s". Must be a 4-digit year', $taxYear),
                self::SEVERITY_ERROR
            );
        }

        // Validate sequence number
        $sequenceNumber = $this->getElementValue($transmitter, RL24XmlSchema::ELEMENT_SEQUENCE_NUMBER);
        if (empty($sequenceNumber)) {
            $this->addError(
                self::ERR_MISSING_ELEMENT,
                sprintf('Missing required element: %s', RL24XmlSchema::ELEMENT_SEQUENCE_NUMBER),
                self::SEVERITY_ERROR
            );
        }
    }

    /**
     * Validate the group (Groupe) element and its contents
     */
    protected function validateGroup(): void
    {
        $groups = $this->dom->getElementsByTagName(RL24XmlSchema::ELEMENT_GROUP);

        if ($groups->length === 0) {
            $this->addError(
                self::ERR_MISSING_ELEMENT,
                sprintf('Missing required element: %s', RL24XmlSchema::ELEMENT_GROUP),
                self::SEVERITY_ERROR
            );
            return;
        }

        $group = $groups->item(0);

        // Validate issuer
        $this->validateIssuer($group);

        // Validate slips
        $slips = $group->getElementsByTagName(RL24XmlSchema::ELEMENT_SLIP);
        $slipCount = $slips->length;

        if ($slipCount === 0) {
            $this->addError(
                self::ERR_MISSING_ELEMENT,
                'No RL-24 slips found in transmission',
                self::SEVERITY_ERROR
            );
        } elseif ($slipCount > RL24XmlSchema::MAX_SLIPS_PER_FILE) {
            $this->addError(
                self::ERR_FILE_CONSTRAINT,
                sprintf(
                    'Too many slips (%d). Maximum allowed is %d per file',
                    $slipCount,
                    RL24XmlSchema::MAX_SLIPS_PER_FILE
                ),
                self::SEVERITY_ERROR
            );
        }

        // Validate each slip
        for ($i = 0; $i < $slips->length; $i++) {
            $this->validateSlip($slips->item($i), $i + 1);
        }

        // Validate summary
        $this->validateSummary($group, $slipCount);
    }

    /**
     * Validate the issuer (Emetteur) element
     *
     * @param \DOMElement $group Group element
     */
    protected function validateIssuer($group): void
    {
        $issuers = $group->getElementsByTagName(RL24XmlSchema::ELEMENT_ISSUER);

        if ($issuers->length === 0) {
            $this->addError(
                self::ERR_MISSING_ELEMENT,
                sprintf('Missing required element: %s', RL24XmlSchema::ELEMENT_ISSUER),
                self::SEVERITY_ERROR
            );
            return;
        }

        $issuer = $issuers->item(0);

        // Validate NEQ
        $neq = $this->getElementValue($issuer, RL24XmlSchema::ELEMENT_NEQ);
        if (empty($neq)) {
            $this->addError(
                self::ERR_MISSING_ELEMENT,
                sprintf('Missing required element: %s', RL24XmlSchema::ELEMENT_NEQ),
                self::SEVERITY_ERROR
            );
        } elseif (!RL24XmlSchema::isValidNEQ($neq)) {
            $this->addError(
                self::ERR_INVALID_FORMAT,
                sprintf('Invalid NEQ format: "%s". Must be 10 digits', $neq),
                self::SEVERITY_ERROR
            );
        }

        // Check for issuer name
        $issuerName = $issuer->getElementsByTagName(RL24XmlSchema::ELEMENT_ISSUER_NAME);
        if ($issuerName->length === 0) {
            $this->addWarning(
                self::ERR_MISSING_ELEMENT,
                'Missing issuer name element'
            );
        }
    }

    /**
     * Validate an individual RL-24 slip
     *
     * @param \DOMElement $slip Slip element
     * @param int $slipIndex 1-based slip index for error messages
     */
    protected function validateSlip($slip, int $slipIndex): void
    {
        $context = sprintf('Slip #%d', $slipIndex);

        // Validate identification
        $this->validateSlipIdentification($slip, $context);

        // Validate recipient (parent)
        $this->validateRecipient($slip, $context);

        // Validate child
        $this->validateChild($slip, $context);

        // Validate service period
        $this->validateServicePeriod($slip, $context);

        // Validate amounts
        $this->validateAmounts($slip, $context);
    }

    /**
     * Validate slip identification section
     *
     * @param \DOMElement $slip Slip element
     * @param string $context Context string for error messages
     */
    protected function validateSlipIdentification($slip, string $context): void
    {
        $identification = $slip->getElementsByTagName(RL24XmlSchema::ELEMENT_SLIP_ID);

        if ($identification->length === 0) {
            $this->addError(
                self::ERR_MISSING_ELEMENT,
                sprintf('%s: Missing slip identification element', $context),
                self::SEVERITY_ERROR
            );
            return;
        }

        $idElement = $identification->item(0);

        // Validate slip number
        $slipNumber = $this->getElementValue($idElement, RL24XmlSchema::ELEMENT_SLIP_NUMBER);
        if (empty($slipNumber)) {
            $this->addError(
                self::ERR_MISSING_ELEMENT,
                sprintf('%s: Missing slip number', $context),
                self::SEVERITY_ERROR
            );
        }

        // Validate Case A code
        $caseA = $this->getElementValue($idElement, RL24XmlSchema::ELEMENT_BOX_A);
        if (empty($caseA)) {
            $this->addError(
                self::ERR_MISSING_ELEMENT,
                sprintf('%s: Missing Case A (slip type code)', $context),
                self::SEVERITY_ERROR
            );
        } elseif (!in_array($caseA, RL24XmlSchema::getValidSlipTypeCodes())) {
            $this->addError(
                self::ERR_INVALID_VALUE,
                sprintf(
                    '%s: Invalid Case A code: "%s". Valid values: %s',
                    $context,
                    $caseA,
                    implode(', ', RL24XmlSchema::getValidSlipTypeCodes())
                ),
                self::SEVERITY_ERROR
            );
        }

        // For amended/cancelled slips, check for original slip reference
        if (in_array($caseA, [RL24XmlSchema::CODE_AMENDED, RL24XmlSchema::CODE_CANCELLED])) {
            $originalSlipNumber = $this->getElementValue($idElement, RL24XmlSchema::ELEMENT_ORIGINAL_SLIP_NUMBER);
            if (empty($originalSlipNumber) && $this->strictMode) {
                $this->addWarning(
                    self::ERR_MISSING_ELEMENT,
                    sprintf('%s: Amended/Cancelled slip should reference original slip number', $context)
                );
            }
        }
    }

    /**
     * Validate recipient (parent) section
     *
     * @param \DOMElement $slip Slip element
     * @param string $context Context string for error messages
     */
    protected function validateRecipient($slip, string $context): void
    {
        $recipients = $slip->getElementsByTagName(RL24XmlSchema::ELEMENT_RECIPIENT);

        if ($recipients->length === 0) {
            $this->addError(
                self::ERR_MISSING_ELEMENT,
                sprintf('%s: Missing recipient element', $context),
                self::SEVERITY_ERROR
            );
            return;
        }

        $recipient = $recipients->item(0);

        // Validate SIN if present
        $sin = $this->getElementValue($recipient, RL24XmlSchema::ELEMENT_SIN);
        if (!empty($sin) && !$this->isValidSIN($sin)) {
            $this->addError(
                self::ERR_INVALID_FORMAT,
                sprintf('%s: Invalid SIN format: "%s"', $context, $this->maskSIN($sin)),
                self::SEVERITY_ERROR
            );
        }

        // Check for recipient name
        $recipientName = $recipient->getElementsByTagName(RL24XmlSchema::ELEMENT_RECIPIENT_NAME);
        if ($recipientName->length > 0) {
            $lastName = $this->getElementValue($recipientName->item(0), RL24XmlSchema::ELEMENT_LAST_NAME);
            $firstName = $this->getElementValue($recipientName->item(0), RL24XmlSchema::ELEMENT_FIRST_NAME);

            if (empty($lastName)) {
                $this->addError(
                    self::ERR_MISSING_ELEMENT,
                    sprintf('%s: Missing recipient last name', $context),
                    self::SEVERITY_ERROR
                );
            }

            if (empty($firstName)) {
                $this->addError(
                    self::ERR_MISSING_ELEMENT,
                    sprintf('%s: Missing recipient first name', $context),
                    self::SEVERITY_ERROR
                );
            }
        } else {
            $this->addError(
                self::ERR_MISSING_ELEMENT,
                sprintf('%s: Missing recipient name element', $context),
                self::SEVERITY_ERROR
            );
        }
    }

    /**
     * Validate child section
     *
     * @param \DOMElement $slip Slip element
     * @param string $context Context string for error messages
     */
    protected function validateChild($slip, string $context): void
    {
        $children = $slip->getElementsByTagName(RL24XmlSchema::ELEMENT_CHILD);

        if ($children->length === 0) {
            $this->addError(
                self::ERR_MISSING_ELEMENT,
                sprintf('%s: Missing child element', $context),
                self::SEVERITY_ERROR
            );
            return;
        }

        $child = $children->item(0);

        // Validate child last name
        $lastName = $this->getElementValue($child, RL24XmlSchema::ELEMENT_CHILD_LAST_NAME);
        if (empty($lastName)) {
            $this->addError(
                self::ERR_MISSING_ELEMENT,
                sprintf('%s: Missing child last name', $context),
                self::SEVERITY_ERROR
            );
        }

        // Validate child first name
        $firstName = $this->getElementValue($child, RL24XmlSchema::ELEMENT_CHILD_FIRST_NAME);
        if (empty($firstName)) {
            $this->addError(
                self::ERR_MISSING_ELEMENT,
                sprintf('%s: Missing child first name', $context),
                self::SEVERITY_ERROR
            );
        }

        // Validate date of birth if present
        $dob = $this->getElementValue($child, RL24XmlSchema::ELEMENT_CHILD_DOB);
        if (!empty($dob) && !$this->isValidDate($dob)) {
            $this->addError(
                self::ERR_INVALID_FORMAT,
                sprintf('%s: Invalid child date of birth: "%s"', $context, $dob),
                self::SEVERITY_ERROR
            );
        }
    }

    /**
     * Validate service period section
     *
     * @param \DOMElement $slip Slip element
     * @param string $context Context string for error messages
     */
    protected function validateServicePeriod($slip, string $context): void
    {
        $periods = $slip->getElementsByTagName(RL24XmlSchema::ELEMENT_SERVICE_PERIOD);

        if ($periods->length === 0) {
            $this->addError(
                self::ERR_MISSING_ELEMENT,
                sprintf('%s: Missing service period element', $context),
                self::SEVERITY_ERROR
            );
            return;
        }

        $period = $periods->item(0);

        // Validate start date
        $startDate = $this->getElementValue($period, RL24XmlSchema::ELEMENT_PERIOD_START);
        if (empty($startDate)) {
            $this->addError(
                self::ERR_MISSING_ELEMENT,
                sprintf('%s: Missing service period start date', $context),
                self::SEVERITY_ERROR
            );
        } elseif (!$this->isValidDate($startDate)) {
            $this->addError(
                self::ERR_INVALID_FORMAT,
                sprintf('%s: Invalid service period start date: "%s"', $context, $startDate),
                self::SEVERITY_ERROR
            );
        }

        // Validate end date
        $endDate = $this->getElementValue($period, RL24XmlSchema::ELEMENT_PERIOD_END);
        if (empty($endDate)) {
            $this->addError(
                self::ERR_MISSING_ELEMENT,
                sprintf('%s: Missing service period end date', $context),
                self::SEVERITY_ERROR
            );
        } elseif (!$this->isValidDate($endDate)) {
            $this->addError(
                self::ERR_INVALID_FORMAT,
                sprintf('%s: Invalid service period end date: "%s"', $context, $endDate),
                self::SEVERITY_ERROR
            );
        }

        // Validate date order
        if (!empty($startDate) && !empty($endDate) && $this->isValidDate($startDate) && $this->isValidDate($endDate)) {
            if (strtotime($startDate) > strtotime($endDate)) {
                $this->addError(
                    self::ERR_BUSINESS_RULE,
                    sprintf('%s: Service period start date is after end date', $context),
                    self::SEVERITY_ERROR
                );
            }
        }
    }

    /**
     * Validate amount boxes (10-14)
     *
     * @param \DOMElement $slip Slip element
     * @param string $context Context string for error messages
     */
    protected function validateAmounts($slip, string $context): void
    {
        // Box 10 - Total days
        $box10 = $this->getElementValue($slip, RL24XmlSchema::ELEMENT_BOX_10);
        if ($box10 !== null && $box10 !== '') {
            if (!$this->isValidDays($box10)) {
                $this->addError(
                    self::ERR_INVALID_VALUE,
                    sprintf('%s: Invalid Box 10 (days) value: "%s"', $context, $box10),
                    self::SEVERITY_ERROR
                );
            }
        }

        // Box 11 - Childcare expenses paid
        $box11 = $this->getElementValue($slip, RL24XmlSchema::ELEMENT_BOX_11);
        if ($box11 !== null && $box11 !== '' && !$this->isValidAmount($box11)) {
            $this->addError(
                self::ERR_INVALID_VALUE,
                sprintf('%s: Invalid Box 11 (amount) value: "%s"', $context, $box11),
                self::SEVERITY_ERROR
            );
        }

        // Box 12 - Eligible childcare expenses
        $box12 = $this->getElementValue($slip, RL24XmlSchema::ELEMENT_BOX_12);
        if ($box12 !== null && $box12 !== '' && !$this->isValidAmount($box12)) {
            $this->addError(
                self::ERR_INVALID_VALUE,
                sprintf('%s: Invalid Box 12 (amount) value: "%s"', $context, $box12),
                self::SEVERITY_ERROR
            );
        }

        // Box 13 - Government contributions
        $box13 = $this->getElementValue($slip, RL24XmlSchema::ELEMENT_BOX_13);
        if ($box13 !== null && $box13 !== '' && !$this->isValidAmount($box13)) {
            $this->addError(
                self::ERR_INVALID_VALUE,
                sprintf('%s: Invalid Box 13 (amount) value: "%s"', $context, $box13),
                self::SEVERITY_ERROR
            );
        }

        // Box 14 - Net eligible expenses
        $box14 = $this->getElementValue($slip, RL24XmlSchema::ELEMENT_BOX_14);
        if ($box14 !== null && $box14 !== '' && !$this->isValidAmount($box14)) {
            $this->addError(
                self::ERR_INVALID_VALUE,
                sprintf('%s: Invalid Box 14 (amount) value: "%s"', $context, $box14),
                self::SEVERITY_ERROR
            );
        }

        // Business rule: Box 14 = Box 12 - Box 13 (if strict mode)
        if ($this->strictMode && $box12 !== null && $box13 !== null && $box14 !== null) {
            $expectedBox14 = (float) $box12 - (float) $box13;
            $actualBox14 = (float) $box14;

            // Allow for small rounding differences
            if (abs($expectedBox14 - $actualBox14) > 0.01) {
                $this->addWarning(
                    self::ERR_BUSINESS_RULE,
                    sprintf(
                        '%s: Box 14 (%.2f) does not equal Box 12 - Box 13 (%.2f - %.2f = %.2f)',
                        $context,
                        $actualBox14,
                        (float) $box12,
                        (float) $box13,
                        $expectedBox14
                    )
                );
            }
        }
    }

    /**
     * Validate summary (Sommaire) element
     *
     * @param \DOMElement $group Group element
     * @param int $expectedSlipCount Expected number of slips
     */
    protected function validateSummary($group, int $expectedSlipCount): void
    {
        $summaries = $group->getElementsByTagName(RL24XmlSchema::ELEMENT_SUMMARY);

        if ($summaries->length === 0) {
            $this->addError(
                self::ERR_MISSING_ELEMENT,
                sprintf('Missing required element: %s', RL24XmlSchema::ELEMENT_SUMMARY),
                self::SEVERITY_ERROR
            );
            return;
        }

        $summary = $summaries->item(0);

        // Validate slip count matches
        $totalSlips = $this->getElementValue($summary, RL24XmlSchema::ELEMENT_TOTAL_SLIPS);
        if ($totalSlips !== null && (int) $totalSlips !== $expectedSlipCount) {
            $this->addError(
                self::ERR_SUMMARY_MISMATCH,
                sprintf(
                    'Summary slip count (%s) does not match actual slip count (%d)',
                    $totalSlips,
                    $expectedSlipCount
                ),
                self::SEVERITY_ERROR
            );
        }

        // Validate summary totals are present
        if ($this->strictMode) {
            if ($this->getElementValue($summary, RL24XmlSchema::ELEMENT_TOTAL_BOX_11) === null) {
                $this->addWarning(
                    self::ERR_MISSING_ELEMENT,
                    'Missing summary total for Box 11'
                );
            }

            if ($this->getElementValue($summary, RL24XmlSchema::ELEMENT_TOTAL_BOX_12) === null) {
                $this->addWarning(
                    self::ERR_MISSING_ELEMENT,
                    'Missing summary total for Box 12'
                );
            }
        }
    }

    /**
     * Validate business rules
     */
    protected function validateBusinessRules(): void
    {
        // Calculate actual totals from slips
        $slips = $this->dom->getElementsByTagName(RL24XmlSchema::ELEMENT_SLIP);
        $calculatedTotals = [
            'totalDays' => 0,
            'totalCase11' => 0.0,
            'totalCase12' => 0.0,
            'totalCase13' => 0.0,
            'totalCase14' => 0.0,
        ];

        foreach ($slips as $slip) {
            $box10 = $this->getElementValue($slip, RL24XmlSchema::ELEMENT_BOX_10);
            $box11 = $this->getElementValue($slip, RL24XmlSchema::ELEMENT_BOX_11);
            $box12 = $this->getElementValue($slip, RL24XmlSchema::ELEMENT_BOX_12);
            $box13 = $this->getElementValue($slip, RL24XmlSchema::ELEMENT_BOX_13);
            $box14 = $this->getElementValue($slip, RL24XmlSchema::ELEMENT_BOX_14);

            $calculatedTotals['totalDays'] += (int) $box10;
            $calculatedTotals['totalCase11'] += (float) $box11;
            $calculatedTotals['totalCase12'] += (float) $box12;
            $calculatedTotals['totalCase13'] += (float) $box13;
            $calculatedTotals['totalCase14'] += (float) $box14;
        }

        // Verify summary totals match
        $groups = $this->dom->getElementsByTagName(RL24XmlSchema::ELEMENT_GROUP);
        if ($groups->length > 0) {
            $summaries = $groups->item(0)->getElementsByTagName(RL24XmlSchema::ELEMENT_SUMMARY);
            if ($summaries->length > 0) {
                $summary = $summaries->item(0);

                $this->verifySummaryTotal($summary, RL24XmlSchema::ELEMENT_TOTAL_DAYS, $calculatedTotals['totalDays'], 'days');
                $this->verifySummaryTotal($summary, RL24XmlSchema::ELEMENT_TOTAL_BOX_11, $calculatedTotals['totalCase11'], 'Box 11');
                $this->verifySummaryTotal($summary, RL24XmlSchema::ELEMENT_TOTAL_BOX_12, $calculatedTotals['totalCase12'], 'Box 12');
                $this->verifySummaryTotal($summary, RL24XmlSchema::ELEMENT_TOTAL_BOX_13, $calculatedTotals['totalCase13'], 'Box 13');
                $this->verifySummaryTotal($summary, RL24XmlSchema::ELEMENT_TOTAL_BOX_14, $calculatedTotals['totalCase14'], 'Box 14');
            }
        }
    }

    /**
     * Verify a summary total matches the calculated value
     *
     * @param \DOMElement $summary Summary element
     * @param string $elementName Element name
     * @param float|int $calculatedValue Calculated value
     * @param string $description Human-readable description
     */
    protected function verifySummaryTotal($summary, string $elementName, $calculatedValue, string $description): void
    {
        $reportedValue = $this->getElementValue($summary, $elementName);

        if ($reportedValue === null) {
            return; // Already reported as missing in validateSummary
        }

        $reported = is_float($calculatedValue) ? (float) $reportedValue : (int) $reportedValue;

        // Allow for small rounding differences in amounts
        $diff = is_float($calculatedValue) ? abs($calculatedValue - $reported) : abs($calculatedValue - $reported);
        $tolerance = is_float($calculatedValue) ? 0.01 : 0;

        if ($diff > $tolerance) {
            $this->addWarning(
                self::ERR_SUMMARY_MISMATCH,
                sprintf(
                    'Summary total for %s (%.2f) does not match calculated total (%.2f)',
                    $description,
                    $reported,
                    $calculatedValue
                )
            );
        }
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /**
     * Get the text value of an element
     *
     * @param \DOMElement $parent Parent element
     * @param string $elementName Element name to find
     * @return string|null Element value or null if not found
     */
    protected function getElementValue($parent, string $elementName): ?string
    {
        $elements = $parent->getElementsByTagName($elementName);

        if ($elements->length === 0) {
            return null;
        }

        return trim($elements->item(0)->textContent);
    }

    /**
     * Check if a tax year is valid
     *
     * @param string $year Tax year
     * @return bool True if valid
     */
    protected function isValidTaxYear(string $year): bool
    {
        if (!ctype_digit($year) || strlen($year) !== 4) {
            return false;
        }

        $yearInt = (int) $year;
        $currentYear = (int) date('Y');

        // Allow tax years from 2000 to current year + 1
        return $yearInt >= 2000 && $yearInt <= ($currentYear + 1);
    }

    /**
     * Check if a SIN is valid
     *
     * @param string $sin Social Insurance Number
     * @return bool True if valid format
     */
    protected function isValidSIN(string $sin): bool
    {
        // Remove any formatting
        $cleaned = preg_replace('/[^0-9]/', '', $sin);

        // SIN must be exactly 9 digits
        if (strlen($cleaned) !== RL24XmlSchema::SIN_LENGTH) {
            return false;
        }

        // Validate using Luhn algorithm
        return $this->validateLuhn($cleaned);
    }

    /**
     * Validate a number using the Luhn algorithm
     *
     * @param string $number Number string to validate
     * @return bool True if valid
     */
    protected function validateLuhn(string $number): bool
    {
        $sum = 0;
        $length = strlen($number);
        $parity = $length % 2;

        for ($i = $length - 1; $i >= 0; $i--) {
            $digit = (int) $number[$i];

            if (($i % 2) === $parity) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }

            $sum += $digit;
        }

        return ($sum % 10) === 0;
    }

    /**
     * Mask a SIN for error messages (show only last 3 digits)
     *
     * @param string $sin Social Insurance Number
     * @return string Masked SIN
     */
    protected function maskSIN(string $sin): string
    {
        $cleaned = preg_replace('/[^0-9]/', '', $sin);

        if (strlen($cleaned) < 4) {
            return str_repeat('*', strlen($cleaned));
        }

        return str_repeat('*', strlen($cleaned) - 3) . substr($cleaned, -3);
    }

    /**
     * Check if a date is valid (YYYY-MM-DD format)
     *
     * @param string $date Date string
     * @return bool True if valid
     */
    protected function isValidDate(string $date): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }

        $parts = explode('-', $date);
        return checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0]);
    }

    /**
     * Check if a days value is valid
     *
     * @param string $days Days value
     * @return bool True if valid
     */
    protected function isValidDays(string $days): bool
    {
        // Must be a non-negative integer
        if (!ctype_digit($days)) {
            return false;
        }

        $daysInt = (int) $days;

        // Reasonable range: 0 to 366 days
        return $daysInt >= 0 && $daysInt <= 366;
    }

    /**
     * Check if an amount is valid
     *
     * @param string $amount Amount value
     * @return bool True if valid
     */
    protected function isValidAmount(string $amount): bool
    {
        // Must be a valid number with up to 2 decimal places
        if (!preg_match('/^-?\d+(\.\d{1,2})?$/', $amount)) {
            return false;
        }

        $amountFloat = (float) $amount;

        // Must be within allowed range
        return $amountFloat >= RL24XmlSchema::MIN_AMOUNT && $amountFloat <= RL24XmlSchema::MAX_AMOUNT;
    }

    /**
     * Add an error to the collection
     *
     * @param string $code Error code
     * @param string $message Error message
     * @param string $severity Error severity
     */
    protected function addError(string $code, string $message, string $severity = self::SEVERITY_ERROR): void
    {
        $this->errors[] = [
            'code' => $code,
            'message' => $message,
            'severity' => $severity,
        ];
    }

    /**
     * Add a warning to the collection
     *
     * @param string $code Warning code
     * @param string $message Warning message
     */
    protected function addWarning(string $code, string $message): void
    {
        $this->warnings[] = [
            'code' => $code,
            'message' => $message,
            'severity' => self::SEVERITY_WARNING,
        ];
    }

    /**
     * Add a libxml error to the collection
     *
     * @param LibXMLError $error LibXML error object
     * @param string $severity Override severity
     */
    protected function addLibXmlError(LibXMLError $error, string $severity = self::SEVERITY_ERROR): void
    {
        $message = sprintf(
            'XML Parse %s: %s (Line %d, Column %d)',
            $error->level === LIBXML_ERR_WARNING ? 'Warning' : 'Error',
            trim($error->message),
            $error->line,
            $error->column
        );

        if ($severity === self::SEVERITY_WARNING) {
            $this->addWarning(self::ERR_XML_MALFORMED, $message);
        } else {
            $this->addError(self::ERR_XML_MALFORMED, $message, $severity);
        }
    }
}
