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

/**
 * RL-24 XML Schema Definition
 *
 * Defines XSD schema constants, namespace URIs, element names, and
 * structure specifications for Revenu Québec RL-24 (Relevé 24) XML format.
 *
 * The RL-24 slip is filed by childcare service providers in Quebec
 * for parents claiming the tax credit for childcare expenses.
 *
 * @see https://www.revenuquebec.ca/en/businesses/rl-slips-and-summaries/rl-slips/
 * @version v1.0.00
 * @since   v1.0.00
 */
class RL24XmlSchema
{
    // =========================================================================
    // SCHEMA VERSION & METADATA
    // =========================================================================

    /**
     * Current schema version for RL-24 XML files.
     */
    const SCHEMA_VERSION = '2024.1';

    /**
     * RL slip type code for RL-24.
     */
    const SLIP_TYPE = 'RL-24';

    /**
     * Numeric slip type identifier.
     */
    const SLIP_TYPE_CODE = '24';

    /**
     * Schema year for which this definition applies.
     */
    const SCHEMA_YEAR = 2024;

    // =========================================================================
    // XML NAMESPACES
    // =========================================================================

    /**
     * Primary namespace URI for Revenu Québec RL transmissions.
     */
    const NS_RELEVE = 'http://www.mrq.gouv.qc.ca/T5/RL24';

    /**
     * Common namespace prefix for Revenu Québec schemas.
     */
    const NS_PREFIX_MRQ = 'http://www.mrq.gouv.qc.ca';

    /**
     * XML Schema Instance namespace.
     */
    const NS_XSI = 'http://www.w3.org/2001/XMLSchema-instance';

    /**
     * XML namespace for standard XML attributes.
     */
    const NS_XML = 'http://www.w3.org/XML/1998/namespace';

    /**
     * Revenu Québec transmission namespace.
     */
    const NS_TRANSMISSION = 'http://www.mrq.gouv.qc.ca/T5/transmission';

    // =========================================================================
    // ROOT ELEMENT DEFINITIONS
    // =========================================================================

    /**
     * Root element for RL-24 transmission file.
     */
    const ELEMENT_ROOT = 'Transmission';

    /**
     * Transmission header element.
     */
    const ELEMENT_HEADER = 'Entete';

    /**
     * Transmitter information element.
     */
    const ELEMENT_TRANSMITTER = 'Transmetteur';

    /**
     * Document group element (contains slips for one issuer).
     */
    const ELEMENT_GROUP = 'Groupe';

    /**
     * RL-24 slip container element.
     */
    const ELEMENT_SLIP = 'RL24';

    /**
     * Summary (totals) element.
     */
    const ELEMENT_SUMMARY = 'Sommaire';

    // =========================================================================
    // HEADER ELEMENTS
    // =========================================================================

    /**
     * Transmitter number (NP + 6 digits).
     */
    const ELEMENT_TRANSMITTER_NUMBER = 'NoTransmetteur';

    /**
     * Transmission type (O=Original, M=Modified, A=Annulation).
     */
    const ELEMENT_TRANSMISSION_TYPE = 'TypeTransmission';

    /**
     * Tax year for the transmission.
     */
    const ELEMENT_TAX_YEAR = 'Annee';

    /**
     * Certification number (RQ-99-99-999 format).
     */
    const ELEMENT_CERTIFICATION = 'NoCertification';

    /**
     * Sequential file number for the transmitter.
     */
    const ELEMENT_SEQUENCE_NUMBER = 'NoSequence';

    /**
     * Software name.
     */
    const ELEMENT_SOFTWARE_NAME = 'NomLogiciel';

    /**
     * Software version.
     */
    const ELEMENT_SOFTWARE_VERSION = 'VersionLogiciel';

    // =========================================================================
    // ISSUER/PROVIDER ELEMENTS
    // =========================================================================

    /**
     * Issuer (childcare provider) information container.
     */
    const ELEMENT_ISSUER = 'Emetteur';

    /**
     * Quebec Enterprise Number (NEQ) - 10 digits.
     */
    const ELEMENT_NEQ = 'NEQ';

    /**
     * Provider/issuer name.
     */
    const ELEMENT_ISSUER_NAME = 'NomEmetteur';

    /**
     * Issuer name line 1.
     */
    const ELEMENT_ISSUER_NAME_1 = 'Ligne1';

    /**
     * Issuer name line 2.
     */
    const ELEMENT_ISSUER_NAME_2 = 'Ligne2';

    /**
     * Issuer address container.
     */
    const ELEMENT_ISSUER_ADDRESS = 'AdresseEmetteur';

    /**
     * Address line 1.
     */
    const ELEMENT_ADDRESS_LINE_1 = 'Ligne1';

    /**
     * Address line 2.
     */
    const ELEMENT_ADDRESS_LINE_2 = 'Ligne2';

    /**
     * City/municipality.
     */
    const ELEMENT_CITY = 'Ville';

    /**
     * Province code (2 characters).
     */
    const ELEMENT_PROVINCE = 'Province';

    /**
     * Postal code.
     */
    const ELEMENT_POSTAL_CODE = 'CodePostal';

    /**
     * Country code.
     */
    const ELEMENT_COUNTRY = 'Pays';

    // =========================================================================
    // RECIPIENT (PARENT) ELEMENTS
    // =========================================================================

    /**
     * Recipient (parent/guardian) information container.
     */
    const ELEMENT_RECIPIENT = 'Destinataire';

    /**
     * Social Insurance Number (9 digits, no spaces).
     */
    const ELEMENT_SIN = 'NAS';

    /**
     * Recipient name container.
     */
    const ELEMENT_RECIPIENT_NAME = 'NomDestinataire';

    /**
     * Last name (family name).
     */
    const ELEMENT_LAST_NAME = 'Nom';

    /**
     * First name (given name).
     */
    const ELEMENT_FIRST_NAME = 'Prenom';

    /**
     * Recipient address container.
     */
    const ELEMENT_RECIPIENT_ADDRESS = 'AdresseDestinataire';

    // =========================================================================
    // CHILD ELEMENTS
    // =========================================================================

    /**
     * Child information container.
     */
    const ELEMENT_CHILD = 'Enfant';

    /**
     * Child's last name.
     */
    const ELEMENT_CHILD_LAST_NAME = 'NomEnfant';

    /**
     * Child's first name.
     */
    const ELEMENT_CHILD_FIRST_NAME = 'PrenomEnfant';

    /**
     * Child's date of birth (YYYY-MM-DD).
     */
    const ELEMENT_CHILD_DOB = 'DateNaissance';

    // =========================================================================
    // BOX/CASE ELEMENTS (Amounts and Codes)
    // =========================================================================

    /**
     * Box A - Slip type code (O=Original, A=Amended, D=Cancelled).
     */
    const ELEMENT_BOX_A = 'CaseA';

    /**
     * Box B - Additional code if applicable.
     */
    const ELEMENT_BOX_B = 'CaseB';

    /**
     * Box 10 - Number of days of childcare.
     */
    const ELEMENT_BOX_10 = 'Case10';

    /**
     * Box 11 - Childcare expenses paid.
     */
    const ELEMENT_BOX_11 = 'Case11';

    /**
     * Box 12 - Eligible childcare expenses.
     */
    const ELEMENT_BOX_12 = 'Case12';

    /**
     * Box 13 - Government contributions received.
     */
    const ELEMENT_BOX_13 = 'Case13';

    /**
     * Box 14 - Net eligible expenses (Box 12 - Box 13).
     */
    const ELEMENT_BOX_14 = 'Case14';

    // =========================================================================
    // SERVICE PERIOD ELEMENTS
    // =========================================================================

    /**
     * Service period container.
     */
    const ELEMENT_SERVICE_PERIOD = 'Periode';

    /**
     * Service period start date.
     */
    const ELEMENT_PERIOD_START = 'DateDebut';

    /**
     * Service period end date.
     */
    const ELEMENT_PERIOD_END = 'DateFin';

    // =========================================================================
    // SLIP IDENTIFICATION ELEMENTS
    // =========================================================================

    /**
     * Sequential slip number within the transmission.
     */
    const ELEMENT_SLIP_NUMBER = 'NoReleve';

    /**
     * Reference to original slip number (for amendments).
     */
    const ELEMENT_ORIGINAL_SLIP_NUMBER = 'NoDernierReleve';

    /**
     * Slip identification attributes container.
     */
    const ELEMENT_SLIP_ID = 'Identification';

    // =========================================================================
    // SUMMARY/TOTALS ELEMENTS
    // =========================================================================

    /**
     * Total number of slips in the group.
     */
    const ELEMENT_TOTAL_SLIPS = 'NombreReleves';

    /**
     * Total amount for Box 11 across all slips.
     */
    const ELEMENT_TOTAL_BOX_11 = 'TotalCase11';

    /**
     * Total amount for Box 12 across all slips.
     */
    const ELEMENT_TOTAL_BOX_12 = 'TotalCase12';

    /**
     * Total amount for Box 13 across all slips.
     */
    const ELEMENT_TOTAL_BOX_13 = 'TotalCase13';

    /**
     * Total amount for Box 14 across all slips.
     */
    const ELEMENT_TOTAL_BOX_14 = 'TotalCase14';

    /**
     * Total days across all slips.
     */
    const ELEMENT_TOTAL_DAYS = 'TotalJours';

    // =========================================================================
    // CODE VALUES
    // =========================================================================

    /**
     * Original slip code (Case A).
     */
    const CODE_ORIGINAL = 'O';

    /**
     * Amended slip code (Case A).
     */
    const CODE_AMENDED = 'A';

    /**
     * Cancelled/Deleted slip code (Case A).
     */
    const CODE_CANCELLED = 'D';

    /**
     * Original transmission type.
     */
    const TRANSMISSION_ORIGINAL = 'O';

    /**
     * Modified transmission type (amendments).
     */
    const TRANSMISSION_MODIFIED = 'M';

    /**
     * Cancellation transmission type.
     */
    const TRANSMISSION_CANCELLATION = 'A';

    // =========================================================================
    // FILE NAMING CONSTANTS
    // =========================================================================

    /**
     * XML file extension.
     */
    const FILE_EXTENSION = '.xml';

    /**
     * Maximum filename length (excluding extension).
     */
    const MAX_FILENAME_LENGTH = 30;

    /**
     * Preparer number length in filename (PPPPPP).
     */
    const PREPARER_NUMBER_LENGTH = 6;

    /**
     * Sequence number length in filename (SSS).
     */
    const SEQUENCE_NUMBER_LENGTH = 3;

    /**
     * Year length in filename (AA = last 2 digits).
     */
    const YEAR_LENGTH = 2;

    // =========================================================================
    // VALIDATION CONSTRAINTS
    // =========================================================================

    /**
     * Maximum slips per XML file.
     */
    const MAX_SLIPS_PER_FILE = 1000;

    /**
     * Maximum file size in bytes (300 MB).
     */
    const MAX_FILE_SIZE_BYTES = 314572800;

    /**
     * Recommended compression threshold (10 MB).
     */
    const COMPRESSION_THRESHOLD_BYTES = 10485760;

    /**
     * Maximum transmissions per transmitter per year.
     */
    const MAX_TRANSMISSIONS_PER_YEAR = 3599;

    /**
     * SIN length (without spaces).
     */
    const SIN_LENGTH = 9;

    /**
     * NEQ length (Quebec Enterprise Number).
     */
    const NEQ_LENGTH = 10;

    /**
     * Transmitter number prefix.
     */
    const TRANSMITTER_PREFIX = 'NP';

    /**
     * Transmitter number digit length.
     */
    const TRANSMITTER_DIGITS = 6;

    // =========================================================================
    // DATE FORMATS
    // =========================================================================

    /**
     * Date format for XML elements (ISO 8601).
     */
    const DATE_FORMAT_XML = 'Y-m-d';

    /**
     * Date format for display purposes.
     */
    const DATE_FORMAT_DISPLAY = 'd/m/Y';

    // =========================================================================
    // AMOUNT FORMATTING
    // =========================================================================

    /**
     * Decimal places for monetary amounts.
     */
    const AMOUNT_DECIMALS = 2;

    /**
     * Decimal separator for XML amounts.
     */
    const DECIMAL_SEPARATOR = '.';

    /**
     * Minimum amount value.
     */
    const MIN_AMOUNT = 0.00;

    /**
     * Maximum amount value per box.
     */
    const MAX_AMOUNT = 9999999.99;

    // =========================================================================
    // PROVINCE CODES
    // =========================================================================

    /**
     * Quebec province code.
     */
    const PROVINCE_QUEBEC = 'QC';

    /**
     * Canada country code.
     */
    const COUNTRY_CANADA = 'CAN';

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /**
     * Get the XML namespace declarations for the root element.
     *
     * @return array Associative array of namespace prefix => URI
     */
    public static function getNamespaceDeclarations(): array
    {
        return [
            'xmlns' => self::NS_RELEVE,
            'xmlns:xsi' => self::NS_XSI,
        ];
    }

    /**
     * Get all valid slip type codes for Box A.
     *
     * @return array Array of valid slip type codes
     */
    public static function getValidSlipTypeCodes(): array
    {
        return [
            self::CODE_ORIGINAL,
            self::CODE_AMENDED,
            self::CODE_CANCELLED,
        ];
    }

    /**
     * Get all valid transmission types.
     *
     * @return array Array of valid transmission types
     */
    public static function getValidTransmissionTypes(): array
    {
        return [
            self::TRANSMISSION_ORIGINAL,
            self::TRANSMISSION_MODIFIED,
            self::TRANSMISSION_CANCELLATION,
        ];
    }

    /**
     * Get slip type code description.
     *
     * @param string $code Slip type code
     * @return string Description of the code
     */
    public static function getSlipTypeDescription(string $code): string
    {
        $descriptions = [
            self::CODE_ORIGINAL => 'Original',
            self::CODE_AMENDED => 'Amended',
            self::CODE_CANCELLED => 'Cancelled',
        ];

        return $descriptions[$code] ?? 'Unknown';
    }

    /**
     * Generate XML filename in AAPPPPPPSSS.xml format.
     *
     * @param int $taxYear Tax year (4 digits)
     * @param string $preparerNumber Preparer number (6 digits)
     * @param int $sequenceNumber Sequence number (1-999)
     * @return string Generated filename
     */
    public static function generateFilename(int $taxYear, string $preparerNumber, int $sequenceNumber): string
    {
        $yearShort = substr((string) $taxYear, -self::YEAR_LENGTH);
        $preparer = str_pad(substr($preparerNumber, 0, self::PREPARER_NUMBER_LENGTH), self::PREPARER_NUMBER_LENGTH, '0', STR_PAD_LEFT);
        $sequence = str_pad((string) $sequenceNumber, self::SEQUENCE_NUMBER_LENGTH, '0', STR_PAD_LEFT);

        return $yearShort . $preparer . $sequence . self::FILE_EXTENSION;
    }

    /**
     * Format a SIN for XML output (9 digits, no spaces).
     *
     * @param string $sin Social Insurance Number (may contain spaces/dashes)
     * @return string Formatted SIN or empty string if invalid
     */
    public static function formatSIN(string $sin): string
    {
        $cleaned = preg_replace('/[^0-9]/', '', $sin);

        if (strlen($cleaned) !== self::SIN_LENGTH) {
            return '';
        }

        return $cleaned;
    }

    /**
     * Format a monetary amount for XML output.
     *
     * @param float $amount Amount to format
     * @return string Formatted amount with 2 decimal places
     */
    public static function formatAmount(float $amount): string
    {
        $amount = max(self::MIN_AMOUNT, min($amount, self::MAX_AMOUNT));
        return number_format($amount, self::AMOUNT_DECIMALS, self::DECIMAL_SEPARATOR, '');
    }

    /**
     * Format a date for XML output.
     *
     * @param string|\DateTimeInterface $date Date to format
     * @return string Formatted date in YYYY-MM-DD format
     */
    public static function formatDate($date): string
    {
        if ($date instanceof \DateTimeInterface) {
            return $date->format(self::DATE_FORMAT_XML);
        }

        $dateObj = \DateTime::createFromFormat('Y-m-d', $date);
        if ($dateObj === false) {
            $dateObj = new \DateTime($date);
        }

        return $dateObj->format(self::DATE_FORMAT_XML);
    }

    /**
     * Validate transmitter number format.
     *
     * @param string $transmitterNumber Transmitter number to validate
     * @return bool True if valid format (NP + 6 digits)
     */
    public static function isValidTransmitterNumber(string $transmitterNumber): bool
    {
        return preg_match('/^' . self::TRANSMITTER_PREFIX . '\d{' . self::TRANSMITTER_DIGITS . '}$/', $transmitterNumber) === 1;
    }

    /**
     * Validate NEQ format.
     *
     * @param string $neq Quebec Enterprise Number to validate
     * @return bool True if valid format (10 digits)
     */
    public static function isValidNEQ(string $neq): bool
    {
        $cleaned = preg_replace('/[^0-9]/', '', $neq);
        return strlen($cleaned) === self::NEQ_LENGTH;
    }

    /**
     * Get the XML structure template for an RL-24 slip.
     *
     * @return array Hierarchical array representing the XML structure
     */
    public static function getSlipStructure(): array
    {
        return [
            self::ELEMENT_SLIP => [
                self::ELEMENT_SLIP_ID => [
                    self::ELEMENT_SLIP_NUMBER,
                    self::ELEMENT_BOX_A,
                    self::ELEMENT_BOX_B,
                    self::ELEMENT_ORIGINAL_SLIP_NUMBER,
                ],
                self::ELEMENT_RECIPIENT => [
                    self::ELEMENT_SIN,
                    self::ELEMENT_RECIPIENT_NAME => [
                        self::ELEMENT_LAST_NAME,
                        self::ELEMENT_FIRST_NAME,
                    ],
                    self::ELEMENT_RECIPIENT_ADDRESS => [
                        self::ELEMENT_ADDRESS_LINE_1,
                        self::ELEMENT_ADDRESS_LINE_2,
                        self::ELEMENT_CITY,
                        self::ELEMENT_PROVINCE,
                        self::ELEMENT_POSTAL_CODE,
                        self::ELEMENT_COUNTRY,
                    ],
                ],
                self::ELEMENT_CHILD => [
                    self::ELEMENT_CHILD_LAST_NAME,
                    self::ELEMENT_CHILD_FIRST_NAME,
                    self::ELEMENT_CHILD_DOB,
                ],
                self::ELEMENT_SERVICE_PERIOD => [
                    self::ELEMENT_PERIOD_START,
                    self::ELEMENT_PERIOD_END,
                ],
                self::ELEMENT_BOX_10,
                self::ELEMENT_BOX_11,
                self::ELEMENT_BOX_12,
                self::ELEMENT_BOX_13,
                self::ELEMENT_BOX_14,
            ],
        ];
    }

    /**
     * Get the required elements for a valid RL-24 slip.
     *
     * @return array Array of required element names
     */
    public static function getRequiredElements(): array
    {
        return [
            self::ELEMENT_SLIP_NUMBER,
            self::ELEMENT_BOX_A,
            self::ELEMENT_LAST_NAME,
            self::ELEMENT_FIRST_NAME,
            self::ELEMENT_CHILD_LAST_NAME,
            self::ELEMENT_CHILD_FIRST_NAME,
            self::ELEMENT_PERIOD_START,
            self::ELEMENT_PERIOD_END,
        ];
    }
}
