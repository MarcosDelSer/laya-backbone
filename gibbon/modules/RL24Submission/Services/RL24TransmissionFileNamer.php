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
use Gibbon\Module\RL24Submission\Xml\RL24XmlSchema;
use Gibbon\Domain\System\SettingGateway;

/**
 * RL-24 Transmission File Namer
 *
 * Service for generating and managing RL-24 transmission file names
 * according to Revenu Québec specifications.
 *
 * File naming format: AAPPPPPPSSS.xml
 * - AA = Last 2 digits of tax year
 * - PPPPPP = Preparer number (6 digits, zero-padded)
 * - SSS = Sequence number (3 digits, zero-padded, 001-999)
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class RL24TransmissionFileNamer
{
    /**
     * @var RL24TransmissionGateway Transmission gateway for sequence tracking
     */
    protected $transmissionGateway;

    /**
     * @var SettingGateway Setting gateway for preparer number
     */
    protected $settingGateway;

    /**
     * @var array Validation errors
     */
    protected $errors = [];

    /**
     * @var array Validation warnings
     */
    protected $warnings = [];

    /**
     * @var string|null Base path for storing files
     */
    protected $basePath = null;

    /**
     * Constructor
     *
     * @param RL24TransmissionGateway $transmissionGateway
     * @param SettingGateway $settingGateway
     */
    public function __construct(
        RL24TransmissionGateway $transmissionGateway,
        SettingGateway $settingGateway
    ) {
        $this->transmissionGateway = $transmissionGateway;
        $this->settingGateway = $settingGateway;
    }

    /**
     * Set base path for file storage.
     *
     * @param string $basePath
     * @return self
     */
    public function setBasePath(string $basePath): self
    {
        $this->basePath = rtrim($basePath, '/');
        return $this;
    }

    /**
     * Generate a filename for a new transmission.
     *
     * @param int $taxYear Tax year (4 digits, e.g., 2024)
     * @param string|null $preparerNumber Optional preparer number (uses setting if not provided)
     * @param int|null $sequenceNumber Optional sequence number (auto-increments if not provided)
     * @return string|null Generated filename or null on error
     */
    public function generateFilename(int $taxYear, ?string $preparerNumber = null, ?int $sequenceNumber = null): ?string
    {
        $this->resetState();

        // Get preparer number from settings if not provided
        if ($preparerNumber === null) {
            $preparerNumber = $this->getPreparerNumber();
        }

        // Validate preparer number
        if (!$this->validatePreparerNumber($preparerNumber)) {
            return null;
        }

        // Get next sequence number if not provided
        if ($sequenceNumber === null) {
            $sequenceNumber = $this->getNextSequenceNumber($taxYear, $preparerNumber);
        }

        // Validate sequence number
        if (!$this->validateSequenceNumber($sequenceNumber)) {
            return null;
        }

        // Validate tax year
        if (!$this->validateTaxYear($taxYear)) {
            return null;
        }

        return RL24XmlSchema::generateFilename($taxYear, $preparerNumber, $sequenceNumber);
    }

    /**
     * Generate a unique filename, checking for existing files.
     *
     * @param int $taxYear Tax year (4 digits)
     * @param string|null $preparerNumber Optional preparer number
     * @return array Result with filename and sequence number
     */
    public function generateUniqueFilename(int $taxYear, ?string $preparerNumber = null): array
    {
        $this->resetState();

        // Get preparer number from settings if not provided
        if ($preparerNumber === null) {
            $preparerNumber = $this->getPreparerNumber();
        }

        // Validate preparer number
        if (!$this->validatePreparerNumber($preparerNumber)) {
            return [
                'success' => false,
                'filename' => null,
                'sequenceNumber' => null,
                'errors' => $this->errors,
            ];
        }

        // Get next available sequence number
        $sequenceNumber = $this->getNextAvailableSequenceNumber($taxYear, $preparerNumber);

        if ($sequenceNumber === null) {
            return [
                'success' => false,
                'filename' => null,
                'sequenceNumber' => null,
                'errors' => $this->errors,
            ];
        }

        $filename = RL24XmlSchema::generateFilename($taxYear, $preparerNumber, $sequenceNumber);

        return [
            'success' => true,
            'filename' => $filename,
            'sequenceNumber' => $sequenceNumber,
            'taxYear' => $taxYear,
            'preparerNumber' => $preparerNumber,
            'fullPath' => $this->getFullPath($filename, $taxYear),
            'errors' => [],
        ];
    }

    /**
     * Parse a filename to extract its components.
     *
     * @param string $filename Filename to parse (AAPPPPPPSSS.xml)
     * @return array|null Parsed components or null if invalid
     */
    public function parseFilename(string $filename): ?array
    {
        $this->resetState();

        // Remove extension if present
        $baseName = pathinfo($filename, PATHINFO_FILENAME);
        $extension = pathinfo($filename, PATHINFO_EXTENSION);

        // Validate length (AA + PPPPPP + SSS = 11 characters)
        $expectedLength = RL24XmlSchema::YEAR_LENGTH + RL24XmlSchema::PREPARER_NUMBER_LENGTH + RL24XmlSchema::SEQUENCE_NUMBER_LENGTH;

        if (strlen($baseName) !== $expectedLength) {
            $this->errors[] = sprintf(
                'Invalid filename length. Expected %d characters, got %d.',
                $expectedLength,
                strlen($baseName)
            );
            return null;
        }

        // Validate all numeric
        if (!ctype_digit($baseName)) {
            $this->errors[] = 'Filename must contain only digits (excluding extension).';
            return null;
        }

        // Extract components
        $yearShort = substr($baseName, 0, RL24XmlSchema::YEAR_LENGTH);
        $preparerNumber = substr($baseName, RL24XmlSchema::YEAR_LENGTH, RL24XmlSchema::PREPARER_NUMBER_LENGTH);
        $sequenceNumber = substr($baseName, RL24XmlSchema::YEAR_LENGTH + RL24XmlSchema::PREPARER_NUMBER_LENGTH, RL24XmlSchema::SEQUENCE_NUMBER_LENGTH);

        // Determine full year (assume 20xx for now, or 19xx if > current year short)
        $currentYear = (int) date('Y');
        $currentYearShort = (int) substr((string) $currentYear, -2);
        $yearPrefix = ((int) $yearShort > $currentYearShort + 5) ? '19' : '20';
        $taxYear = (int) ($yearPrefix . $yearShort);

        return [
            'filename' => $filename,
            'baseName' => $baseName,
            'extension' => $extension ?: RL24XmlSchema::FILE_EXTENSION,
            'taxYear' => $taxYear,
            'yearShort' => $yearShort,
            'preparerNumber' => $preparerNumber,
            'sequenceNumber' => (int) $sequenceNumber,
            'isValid' => $this->validateParsedFilename($taxYear, $preparerNumber, (int) $sequenceNumber),
        ];
    }

    /**
     * Validate a filename format.
     *
     * @param string $filename Filename to validate
     * @return bool True if valid
     */
    public function validateFilename(string $filename): bool
    {
        $parsed = $this->parseFilename($filename);
        return $parsed !== null && $parsed['isValid'];
    }

    /**
     * Get the full file path for a filename.
     *
     * @param string $filename Filename
     * @param int $taxYear Tax year (for directory structure)
     * @return string Full file path
     */
    public function getFullPath(string $filename, int $taxYear): string
    {
        $directory = $this->getStorageDirectory($taxYear);
        return $directory . '/' . $filename;
    }

    /**
     * Get the storage directory for a tax year.
     *
     * @param int $taxYear Tax year
     * @return string Directory path
     */
    public function getStorageDirectory(int $taxYear): string
    {
        if ($this->basePath) {
            return $this->basePath . '/rl24/' . $taxYear;
        }

        // Default to uploads directory
        $uploadsPath = defined('GIBBON_PATH') ? GIBBON_PATH . '/uploads' : getcwd() . '/uploads';
        return $uploadsPath . '/rl24/' . $taxYear;
    }

    /**
     * Ensure storage directory exists and is writable.
     *
     * @param int $taxYear Tax year
     * @return bool True if directory is ready
     */
    public function ensureStorageDirectory(int $taxYear): bool
    {
        $directory = $this->getStorageDirectory($taxYear);

        if (!file_exists($directory)) {
            if (!mkdir($directory, 0755, true)) {
                $this->errors[] = 'Failed to create storage directory: ' . $directory;
                return false;
            }
        }

        if (!is_writable($directory)) {
            $this->errors[] = 'Storage directory is not writable: ' . $directory;
            return false;
        }

        return true;
    }

    /**
     * Get list of existing filenames for a tax year and preparer.
     *
     * @param int $taxYear Tax year
     * @param string $preparerNumber Preparer number
     * @return array List of existing filenames
     */
    public function getExistingFilenames(int $taxYear, string $preparerNumber): array
    {
        $directory = $this->getStorageDirectory($taxYear);

        if (!file_exists($directory)) {
            return [];
        }

        $pattern = $this->buildFilenamePattern($taxYear, $preparerNumber);
        $files = glob($directory . '/' . $pattern);

        return array_map('basename', $files ?: []);
    }

    /**
     * Check if a filename already exists.
     *
     * @param string $filename Filename to check
     * @param int $taxYear Tax year
     * @return bool True if file exists
     */
    public function filenameExists(string $filename, int $taxYear): bool
    {
        $fullPath = $this->getFullPath($filename, $taxYear);
        return file_exists($fullPath);
    }

    /**
     * Get the next sequence number for a tax year and preparer.
     *
     * @param int $taxYear Tax year
     * @param string $preparerNumber Preparer number
     * @return int Next sequence number
     */
    public function getNextSequenceNumber(int $taxYear, string $preparerNumber): int
    {
        // First check database for highest sequence number used
        $maxDbSequence = $this->transmissionGateway->getMaxSequenceNumber($taxYear);

        // Then check filesystem for any gaps or additional files
        $existingFilenames = $this->getExistingFilenames($taxYear, $preparerNumber);
        $maxFileSequence = 0;

        foreach ($existingFilenames as $filename) {
            $parsed = $this->parseFilename($filename);
            if ($parsed && $parsed['sequenceNumber'] > $maxFileSequence) {
                $maxFileSequence = $parsed['sequenceNumber'];
            }
        }

        // Use the higher of database or filesystem sequence
        $maxSequence = max($maxDbSequence, $maxFileSequence);

        return min($maxSequence + 1, RL24XmlSchema::MAX_TRANSMISSIONS_PER_YEAR);
    }

    /**
     * Get the next available sequence number, ensuring no conflicts.
     *
     * @param int $taxYear Tax year
     * @param string $preparerNumber Preparer number
     * @return int|null Next available sequence number, or null if exhausted
     */
    protected function getNextAvailableSequenceNumber(int $taxYear, string $preparerNumber): ?int
    {
        $nextSequence = $this->getNextSequenceNumber($taxYear, $preparerNumber);

        // Check if we've reached the limit
        if ($nextSequence > RL24XmlSchema::MAX_TRANSMISSIONS_PER_YEAR) {
            $this->errors[] = sprintf(
                'Maximum transmissions (%d) reached for tax year %d.',
                RL24XmlSchema::MAX_TRANSMISSIONS_PER_YEAR,
                $taxYear
            );
            return null;
        }

        // Double-check the file doesn't exist (race condition protection)
        $filename = RL24XmlSchema::generateFilename($taxYear, $preparerNumber, $nextSequence);
        $maxAttempts = 10;
        $attempts = 0;

        while ($this->filenameExists($filename, $taxYear) && $attempts < $maxAttempts) {
            $nextSequence++;
            if ($nextSequence > RL24XmlSchema::MAX_TRANSMISSIONS_PER_YEAR) {
                $this->errors[] = 'Maximum transmissions reached for tax year.';
                return null;
            }
            $filename = RL24XmlSchema::generateFilename($taxYear, $preparerNumber, $nextSequence);
            $attempts++;
        }

        if ($attempts >= $maxAttempts) {
            $this->errors[] = 'Unable to find available sequence number after multiple attempts.';
            return null;
        }

        return $nextSequence;
    }

    /**
     * Get preparer number from module settings.
     *
     * @return string Preparer number
     */
    public function getPreparerNumber(): string
    {
        return $this->settingGateway->getSettingByScope('RL24 Submission', 'preparerNumber') ?? '';
    }

    /**
     * Format preparer number for filename.
     *
     * @param string $preparerNumber Raw preparer number
     * @return string Formatted preparer number (6 digits, zero-padded)
     */
    public function formatPreparerNumber(string $preparerNumber): string
    {
        // Remove any non-digit characters
        $cleaned = preg_replace('/[^0-9]/', '', $preparerNumber);

        // Pad or truncate to exactly 6 digits
        return str_pad(
            substr($cleaned, 0, RL24XmlSchema::PREPARER_NUMBER_LENGTH),
            RL24XmlSchema::PREPARER_NUMBER_LENGTH,
            '0',
            STR_PAD_LEFT
        );
    }

    /**
     * Format sequence number for filename.
     *
     * @param int $sequenceNumber Raw sequence number
     * @return string Formatted sequence number (3 digits, zero-padded)
     */
    public function formatSequenceNumber(int $sequenceNumber): string
    {
        return str_pad(
            (string) min($sequenceNumber, RL24XmlSchema::MAX_TRANSMISSIONS_PER_YEAR),
            RL24XmlSchema::SEQUENCE_NUMBER_LENGTH,
            '0',
            STR_PAD_LEFT
        );
    }

    /**
     * Format tax year for filename.
     *
     * @param int $taxYear Full tax year (4 digits)
     * @return string Formatted year (2 digits)
     */
    public function formatTaxYear(int $taxYear): string
    {
        return substr((string) $taxYear, -RL24XmlSchema::YEAR_LENGTH);
    }

    /**
     * Build filename pattern for glob matching.
     *
     * @param int $taxYear Tax year
     * @param string $preparerNumber Preparer number
     * @return string Glob pattern
     */
    protected function buildFilenamePattern(int $taxYear, string $preparerNumber): string
    {
        $yearShort = $this->formatTaxYear($taxYear);
        $preparer = $this->formatPreparerNumber($preparerNumber);

        // Pattern: AA + PPPPPP + ??? (any 3 digits) + .xml
        return $yearShort . $preparer . '???' . RL24XmlSchema::FILE_EXTENSION;
    }

    /**
     * Validate preparer number.
     *
     * @param string|null $preparerNumber Preparer number to validate
     * @return bool True if valid
     */
    protected function validatePreparerNumber(?string $preparerNumber): bool
    {
        if (empty($preparerNumber)) {
            $this->errors[] = 'Preparer number is required.';
            return false;
        }

        $cleaned = preg_replace('/[^0-9]/', '', $preparerNumber);

        if (strlen($cleaned) === 0) {
            $this->errors[] = 'Preparer number must contain at least one digit.';
            return false;
        }

        if (strlen($cleaned) > RL24XmlSchema::PREPARER_NUMBER_LENGTH) {
            $this->warnings[] = sprintf(
                'Preparer number will be truncated to %d digits.',
                RL24XmlSchema::PREPARER_NUMBER_LENGTH
            );
        }

        return true;
    }

    /**
     * Validate sequence number.
     *
     * @param int|null $sequenceNumber Sequence number to validate
     * @return bool True if valid
     */
    protected function validateSequenceNumber(?int $sequenceNumber): bool
    {
        if ($sequenceNumber === null) {
            $this->errors[] = 'Sequence number is required.';
            return false;
        }

        if ($sequenceNumber < 1) {
            $this->errors[] = 'Sequence number must be at least 1.';
            return false;
        }

        if ($sequenceNumber > RL24XmlSchema::MAX_TRANSMISSIONS_PER_YEAR) {
            $this->errors[] = sprintf(
                'Sequence number exceeds maximum (%d).',
                RL24XmlSchema::MAX_TRANSMISSIONS_PER_YEAR
            );
            return false;
        }

        return true;
    }

    /**
     * Validate tax year.
     *
     * @param int $taxYear Tax year to validate
     * @return bool True if valid
     */
    protected function validateTaxYear(int $taxYear): bool
    {
        $currentYear = (int) date('Y');

        if ($taxYear < 1990) {
            $this->errors[] = 'Tax year must be 1990 or later.';
            return false;
        }

        if ($taxYear > $currentYear + 1) {
            $this->errors[] = sprintf(
                'Tax year cannot be more than one year in the future (%d).',
                $currentYear + 1
            );
            return false;
        }

        return true;
    }

    /**
     * Validate parsed filename components.
     *
     * @param int $taxYear Tax year
     * @param string $preparerNumber Preparer number
     * @param int $sequenceNumber Sequence number
     * @return bool True if all components valid
     */
    protected function validateParsedFilename(int $taxYear, string $preparerNumber, int $sequenceNumber): bool
    {
        // Don't add errors during parsing validation, just return status
        $currentYear = (int) date('Y');

        // Basic range checks
        if ($taxYear < 1990 || $taxYear > $currentYear + 1) {
            return false;
        }

        if (strlen($preparerNumber) !== RL24XmlSchema::PREPARER_NUMBER_LENGTH) {
            return false;
        }

        if ($sequenceNumber < 1 || $sequenceNumber > RL24XmlSchema::MAX_TRANSMISSIONS_PER_YEAR) {
            return false;
        }

        return true;
    }

    /**
     * Get filename info summary for display.
     *
     * @param string $filename Filename to describe
     * @return array Filename info array
     */
    public function getFilenameInfo(string $filename): array
    {
        $parsed = $this->parseFilename($filename);

        if ($parsed === null) {
            return [
                'filename' => $filename,
                'isValid' => false,
                'errors' => $this->errors,
            ];
        }

        return [
            'filename' => $filename,
            'isValid' => $parsed['isValid'],
            'taxYear' => $parsed['taxYear'],
            'preparerNumber' => $parsed['preparerNumber'],
            'sequenceNumber' => $parsed['sequenceNumber'],
            'description' => sprintf(
                'Tax Year: %d, Preparer: %s, Sequence: %d',
                $parsed['taxYear'],
                $parsed['preparerNumber'],
                $parsed['sequenceNumber']
            ),
        ];
    }

    /**
     * Generate a preview filename (for display purposes).
     *
     * @param int $taxYear Tax year
     * @param string|null $preparerNumber Preparer number
     * @return string Preview filename
     */
    public function previewFilename(int $taxYear, ?string $preparerNumber = null): string
    {
        if ($preparerNumber === null) {
            $preparerNumber = $this->getPreparerNumber();
        }

        $nextSequence = $this->getNextSequenceNumber($taxYear, $preparerNumber);

        return RL24XmlSchema::generateFilename($taxYear, $preparerNumber, $nextSequence);
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
