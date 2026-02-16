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

namespace Gibbon\Module\EnhancedFinance\Domain;

use Mpdf\Mpdf;
use Gibbon\Contracts\Database\Connection;
use Gibbon\Contracts\Services\Session;
use InvalidArgumentException;
use RuntimeException;
use ZipArchive;

/**
 * Releve24PDFGenerator
 *
 * PDF generation service for Quebec RL-24 education expense tax receipts.
 * Generates official Quebec RL-24 form layout matching government specifications.
 * Supports single PDF generation and batch PDF generation with ZIP archive.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class Releve24PDFGenerator
{
    /**
     * Maximum number of documents allowed in a single batch operation.
     */
    public const MAX_BATCH_SIZE = 500;

    /**
     * Memory limit to use for large batch operations (in bytes).
     */
    public const LARGE_BATCH_MEMORY_LIMIT = '512M';

    /**
     * Threshold for considering a batch as "large" (affects memory/timeout settings).
     */
    public const LARGE_BATCH_THRESHOLD = 100;

    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @var Session
     */
    protected $session;

    /**
     * @var array Last error details
     */
    protected $lastError = [];

    /**
     * @var string Path to the template file
     */
    protected $templatePath;

    /**
     * Constructor.
     *
     * @param Connection $connection Database connection
     * @param Session $session Gibbon Session for organization info
     */
    public function __construct(Connection $connection, Session $session)
    {
        $this->connection = $connection;
        $this->session = $session;
        $this->templatePath = __DIR__ . '/../../templates/rl24_template.php';
    }

    /**
     * Generate a single RL-24 PDF document.
     *
     * @param string $releve24Id UUID of the RL-24 document
     * @return string Binary PDF content
     * @throws InvalidArgumentException If the RL-24 document ID is invalid or data validation fails
     * @throws RuntimeException If PDF generation fails
     */
    public function generatePDF(string $releve24Id): string
    {
        // Validate UUID format
        if (empty($releve24Id)) {
            throw new InvalidArgumentException('RL-24 document ID is required');
        }

        if (!$this->isValidUuid($releve24Id)) {
            $this->lastError = [
                'code' => 'INVALID_UUID_FORMAT',
                'message' => 'Invalid RL-24 document ID format',
                'document_id' => $releve24Id,
            ];
            throw new InvalidArgumentException('Invalid RL-24 document ID format: ' . substr($releve24Id, 0, 50));
        }

        // Get RL-24 document data
        $releve24 = $this->getReleve24Data($releve24Id);
        if (!$releve24) {
            $this->lastError = [
                'code' => 'DOCUMENT_NOT_FOUND',
                'message' => 'RL-24 document not found',
                'document_id' => $releve24Id,
            ];
            throw new InvalidArgumentException('RL-24 document not found: ' . $releve24Id);
        }

        // Validate required data fields
        $validationErrors = $this->validateReleve24Data($releve24);
        if (!empty($validationErrors)) {
            $this->lastError = [
                'code' => 'DATA_VALIDATION_FAILED',
                'message' => 'RL-24 document data validation failed',
                'document_id' => $releve24Id,
                'errors' => $validationErrors,
            ];
            throw new InvalidArgumentException(
                'RL-24 document data validation failed: ' . implode('; ', $validationErrors)
            );
        }

        // Get related data (with fallback to placeholders for missing data)
        $familyData = $this->getFamilyData($releve24['gibbonFamilyID'] ?? null);
        $childData = $this->getChildData($releve24['gibbonPersonID'] ?? null);
        $schoolData = $this->getSchoolData();

        // Render HTML template
        $html = $this->renderTemplate($releve24, $familyData, $childData, $schoolData);

        // Generate PDF
        try {
            $mpdf = $this->createMpdfInstance();
            $mpdf->WriteHTML($html);
            return $mpdf->Output('', 'S');
        } catch (\Exception $e) {
            $this->lastError = [
                'code' => 'PDF_GENERATION_FAILED',
                'message' => $e->getMessage(),
                'document_id' => $releve24Id,
            ];
            throw new RuntimeException('Failed to generate PDF for document ' . $releve24Id . ': ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Generate multiple RL-24 PDFs and return as ZIP archive.
     *
     * @param array $releve24Ids Array of RL-24 document UUIDs
     * @return string Binary ZIP content containing all PDFs
     * @throws InvalidArgumentException If no valid document IDs provided or batch size exceeded
     * @throws RuntimeException If ZIP creation or PDF generation fails
     */
    public function generateBatchPDF(array $releve24Ids): string
    {
        // Clear previous errors
        $this->lastError = [];

        if (empty($releve24Ids)) {
            $this->lastError = [
                'code' => 'EMPTY_BATCH',
                'message' => 'No RL-24 document IDs provided',
            ];
            throw new InvalidArgumentException('No RL-24 document IDs provided');
        }

        // Filter valid UUIDs and track invalid ones
        $validIds = [];
        $invalidIds = [];
        foreach ($releve24Ids as $id) {
            if ($this->isValidUuid($id)) {
                $validIds[] = $id;
            } else {
                $invalidIds[] = $id;
            }
        }

        // Log invalid UUIDs if any
        if (!empty($invalidIds)) {
            error_log(sprintf(
                'RL-24 Batch PDF: Skipping %d invalid document IDs: %s',
                count($invalidIds),
                implode(', ', array_slice($invalidIds, 0, 5)) . (count($invalidIds) > 5 ? '...' : '')
            ));
        }

        if (empty($validIds)) {
            $this->lastError = [
                'code' => 'NO_VALID_IDS',
                'message' => 'No valid RL-24 document IDs provided',
                'invalid_count' => count($invalidIds),
            ];
            throw new InvalidArgumentException('No valid RL-24 document IDs provided');
        }

        // Validate batch size
        $this->validateBatchSize(count($validIds));

        // Log batch operation start
        error_log(sprintf(
            'RL-24 Batch PDF: Starting generation for %d documents (memory: %s)',
            count($validIds),
            $this->formatBytes(memory_get_usage(true))
        ));

        // Create temporary ZIP file
        $zipFile = tempnam(sys_get_temp_dir(), 'rl24_batch_');
        if ($zipFile === false) {
            $this->lastError = [
                'code' => 'TEMP_FILE_FAILED',
                'message' => 'Failed to create temporary file for ZIP archive',
            ];
            throw new RuntimeException('Failed to create temporary file for ZIP archive');
        }

        $zip = new ZipArchive();
        $openResult = $zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($openResult !== true) {
            @unlink($zipFile);
            $this->lastError = [
                'code' => 'ZIP_CREATE_FAILED',
                'message' => 'Failed to create ZIP archive',
                'zip_error_code' => $openResult,
            ];
            throw new RuntimeException('Failed to create ZIP archive (error code: ' . $openResult . ')');
        }

        $errors = [];
        $successCount = 0;
        $processedCount = 0;

        foreach ($validIds as $releve24Id) {
            $processedCount++;

            try {
                $pdfContent = $this->generatePDF($releve24Id);
                $releve24Data = $this->getReleve24Data($releve24Id);

                // Generate unique filename
                $filename = $this->generatePdfFilename($releve24Data);
                $zip->addFromString($filename, $pdfContent);
                $successCount++;

                // Log progress for large batches
                if ($processedCount % 50 === 0) {
                    error_log(sprintf(
                        'RL-24 Batch PDF: Progress %d/%d (success: %d, errors: %d, memory: %s)',
                        $processedCount,
                        count($validIds),
                        $successCount,
                        count($errors),
                        $this->formatBytes(memory_get_usage(true))
                    ));
                }
            } catch (\Exception $e) {
                $errors[$releve24Id] = [
                    'message' => $e->getMessage(),
                    'type' => get_class($e),
                ];
            }
        }

        $zip->close();

        // If all documents failed, throw exception
        if ($successCount === 0) {
            @unlink($zipFile);
            $this->lastError = [
                'code' => 'ALL_FAILED',
                'message' => 'Failed to generate any PDFs',
                'total_requested' => count($validIds),
                'errors' => $errors,
            ];
            throw new RuntimeException(
                'Failed to generate any PDFs. ' . count($errors) . ' document(s) failed. ' .
                'First error: ' . ($errors[array_key_first($errors)]['message'] ?? 'Unknown error')
            );
        }

        // Read and clean up ZIP file
        $zipContent = file_get_contents($zipFile);
        @unlink($zipFile);

        if ($zipContent === false) {
            $this->lastError = [
                'code' => 'ZIP_READ_FAILED',
                'message' => 'Failed to read generated ZIP archive',
            ];
            throw new RuntimeException('Failed to read generated ZIP archive');
        }

        // Store results (including partial errors if any)
        $this->lastError = [
            'code' => !empty($errors) ? 'PARTIAL_FAILURE' : 'SUCCESS',
            'message' => !empty($errors) ? 'Some documents failed to generate' : 'All documents generated successfully',
            'details' => $errors,
            'success_count' => $successCount,
            'total_requested' => count($validIds),
            'invalid_ids_skipped' => count($invalidIds),
        ];

        // Log completion
        error_log(sprintf(
            'RL-24 Batch PDF: Completed. Success: %d, Failed: %d, Skipped invalid: %d, ZIP size: %s',
            $successCount,
            count($errors),
            count($invalidIds),
            $this->formatBytes(strlen($zipContent))
        ));

        return $zipContent;
    }

    /**
     * Format bytes to human-readable format.
     *
     * @param int $bytes
     * @return string
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Generate PDF for browser display/printing.
     *
     * @param string $releve24Id UUID of the RL-24 document
     * @return string Binary PDF content with inline disposition
     * @throws InvalidArgumentException If the RL-24 document ID is invalid
     * @throws RuntimeException If PDF generation fails
     */
    public function generatePDFForPrint(string $releve24Id): string
    {
        return $this->generatePDF($releve24Id);
    }

    /**
     * Get RL-24 document data from database.
     *
     * @param string $releve24Id
     * @return array|null
     */
    protected function getReleve24Data(string $releve24Id): ?array
    {
        $sql = "SELECT * FROM enhanced_finance_releve24 WHERE id = :id";
        $data = ['id' => $releve24Id];

        $result = $this->connection->selectOne($sql, $data);
        return $result ?: null;
    }

    /**
     * Get family data from database.
     *
     * @param int|null $familyId
     * @return array
     */
    protected function getFamilyData(?int $familyId): array
    {
        if ($familyId === null) {
            return $this->getPlaceholderFamilyData();
        }

        $sql = "SELECT
                    gf.gibbonFamilyID,
                    gf.name as familyName,
                    gf.nameAddress,
                    gf.homeAddress,
                    gf.homeAddressDistrict,
                    gf.homeAddressCountry,
                    gp.preferredName,
                    gp.surname,
                    gp.email,
                    gp.address1,
                    gp.address1District,
                    gp.address1Country
                FROM gibbonFamily gf
                LEFT JOIN gibbonFamilyAdult gfa ON gf.gibbonFamilyID = gfa.gibbonFamilyID AND gfa.contactPriority = 1
                LEFT JOIN gibbonPerson gp ON gfa.gibbonPersonID = gp.gibbonPersonID
                WHERE gf.gibbonFamilyID = :familyId";

        $data = ['familyId' => $familyId];
        $result = $this->connection->selectOne($sql, $data);

        if (!$result) {
            return $this->getPlaceholderFamilyData();
        }

        return [
            'name' => trim(($result['preferredName'] ?? '') . ' ' . ($result['surname'] ?? '')) ?: ($result['nameAddress'] ?? 'N/A'),
            'familyName' => $result['familyName'] ?? 'N/A',
            'address' => $this->formatAddress(
                $result['homeAddress'] ?? $result['address1'] ?? '',
                $result['homeAddressDistrict'] ?? $result['address1District'] ?? '',
                $result['homeAddressCountry'] ?? $result['address1Country'] ?? ''
            ),
            'email' => $result['email'] ?? '',
        ];
    }

    /**
     * Get child data from database.
     *
     * @param int|null $personId
     * @return array
     */
    protected function getChildData(?int $personId): array
    {
        if ($personId === null) {
            return $this->getPlaceholderChildData();
        }

        $sql = "SELECT
                    gibbonPersonID,
                    preferredName,
                    surname,
                    dob,
                    gender
                FROM gibbonPerson
                WHERE gibbonPersonID = :personId";

        $data = ['personId' => $personId];
        $result = $this->connection->selectOne($sql, $data);

        if (!$result) {
            return $this->getPlaceholderChildData();
        }

        return [
            'name' => trim(($result['preferredName'] ?? '') . ' ' . ($result['surname'] ?? '')) ?: 'N/A',
            'dob' => $result['dob'] ?? '',
            'gender' => $result['gender'] ?? '',
        ];
    }

    /**
     * Get school/institution data from session and database.
     *
     * @return array
     */
    protected function getSchoolData(): array
    {
        return [
            'name' => $this->session->get('organisationName') ?: 'N/A',
            'address' => $this->session->get('organisationAddress') ?: '',
            'neq' => $this->getOrganisationNEQ(),
            'phone' => $this->session->get('organisationPhone') ?: '',
        ];
    }

    /**
     * Get organisation NEQ (Quebec Enterprise Number) from settings.
     *
     * @return string
     */
    protected function getOrganisationNEQ(): string
    {
        $sql = "SELECT value FROM gibbonSetting
                WHERE scope = 'Enhanced Finance' AND name = 'organisationNEQ'";
        $result = $this->connection->selectOne($sql);
        return $result['value'] ?? '';
    }

    /**
     * Render the RL-24 HTML template.
     *
     * @param array $releve24 RL-24 document data
     * @param array $familyData Parent/guardian data
     * @param array $childData Child data
     * @param array $schoolData Institution data
     * @return string Rendered HTML
     */
    protected function renderTemplate(array $releve24, array $familyData, array $childData, array $schoolData): string
    {
        // Check if template file exists
        if (file_exists($this->templatePath)) {
            ob_start();
            include $this->templatePath;
            return ob_get_clean();
        }

        // Fallback to inline template
        return $this->renderInlineTemplate($releve24, $familyData, $childData, $schoolData);
    }

    /**
     * Render inline HTML template when template file is not available.
     *
     * @param array $releve24 RL-24 document data
     * @param array $familyData Parent/guardian data
     * @param array $childData Child data
     * @param array $schoolData Institution data
     * @return string Rendered HTML
     */
    protected function renderInlineTemplate(array $releve24, array $familyData, array $childData, array $schoolData): string
    {
        $documentYear = $releve24['document_year'] ?? date('Y');
        $totalEligible = $releve24['total_eligible'] ?? 0;
        $emissionDate = date('Y-m-d');

        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; margin: 20px; }
        .rl24-header { text-align: center; margin-bottom: 20px; }
        .rl24-header h1 { font-size: 16px; margin: 0 0 5px 0; }
        .rl24-header h2 { font-size: 14px; margin: 0 0 10px 0; font-weight: normal; }
        .rl24-header .box-code { font-size: 12px; color: #333; }
        .form-section { margin: 15px 0; }
        .form-section-title { font-weight: bold; margin-bottom: 5px; background: #f0f0f0; padding: 5px; }
        .form-row { display: flex; margin-bottom: 8px; }
        .form-label { width: 200px; font-weight: bold; }
        .form-value { flex: 1; border-bottom: 1px solid #333; padding: 2px 5px; }
        .amount-box { border: 2px solid #333; padding: 10px; margin: 15px 0; text-align: center; }
        .amount-box .amount { font-size: 18px; font-weight: bold; }
        .footer { margin-top: 30px; text-align: center; font-size: 10px; color: #666; }
        .official-notice { border-top: 1px solid #333; padding-top: 15px; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="rl24-header">
        <h1>GOUVERNEMENT DU QUÉBEC</h1>
        <h2>RELEVÉ 24 - FRAIS DE GARDE D\'ENFANTS</h2>
        <div class="box-code">REÇU AUX FINS DE L\'IMPÔT - BOX CODE 46</div>
    </div>

    <div class="form-section">
        <div class="form-section-title">ÉTABLISSEMENT / INSTITUTION</div>
        <div class="form-row">
            <span class="form-label">Nom:</span>
            <span class="form-value">' . htmlspecialchars($schoolData['name']) . '</span>
        </div>
        <div class="form-row">
            <span class="form-label">NEQ:</span>
            <span class="form-value">' . htmlspecialchars($schoolData['neq']) . '</span>
        </div>
        <div class="form-row">
            <span class="form-label">Adresse:</span>
            <span class="form-value">' . htmlspecialchars($schoolData['address']) . '</span>
        </div>
    </div>

    <div class="form-section">
        <div class="form-section-title">ENFANT</div>
        <div class="form-row">
            <span class="form-label">Nom:</span>
            <span class="form-value">' . htmlspecialchars($childData['name']) . '</span>
        </div>
        <div class="form-row">
            <span class="form-label">Date de naissance:</span>
            <span class="form-value">' . htmlspecialchars($childData['dob']) . '</span>
        </div>
    </div>

    <div class="form-section">
        <div class="form-section-title">PARENT / TUTEUR</div>
        <div class="form-row">
            <span class="form-label">Nom:</span>
            <span class="form-value">' . htmlspecialchars($familyData['name']) . '</span>
        </div>
        <div class="form-row">
            <span class="form-label">Adresse:</span>
            <span class="form-value">' . htmlspecialchars($familyData['address']) . '</span>
        </div>
    </div>

    <div class="amount-box">
        <div>MONTANT ADMISSIBLE POUR L\'ANNÉE D\'IMPOSITION ' . htmlspecialchars($documentYear) . '</div>
        <div class="amount">$' . number_format((float)$totalEligible, 2) . '</div>
    </div>

    <div class="official-notice">
        <p><strong>Ce relevé est émis conformément aux exigences fiscales du Québec.</strong></p>
        <p>Conservez ce document pour vos déclarations de revenus.</p>
    </div>

    <div class="footer">
        <p>Émis le: ' . htmlspecialchars($emissionDate) . '</p>
        <p>Document généré électroniquement - Signature non requise</p>
    </div>
</body>
</html>';

        return $html;
    }

    /**
     * Create and configure mPDF instance.
     *
     * @return Mpdf
     */
    protected function createMpdfInstance(): Mpdf
    {
        $config = [
            'mode' => 'utf-8',
            'format' => 'Letter',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 15,
            'margin_bottom' => 15,
            'margin_header' => 9,
            'margin_footer' => 9,
            'tempDir' => sys_get_temp_dir(),
        ];

        return new Mpdf($config);
    }

    /**
     * Generate PDF filename for a given RL-24 document.
     *
     * @param array|null $releve24Data
     * @return string
     */
    protected function generatePdfFilename(?array $releve24Data): string
    {
        $year = $releve24Data['document_year'] ?? date('Y');
        $familyId = $releve24Data['gibbonFamilyID'] ?? 'unknown';
        $childId = $releve24Data['gibbonPersonID'] ?? '';

        $suffix = $childId ? "_{$childId}" : '';
        return "RL24_{$year}_{$familyId}{$suffix}.pdf";
    }

    /**
     * Format address from components.
     *
     * @param string $address
     * @param string $district
     * @param string $country
     * @return string
     */
    protected function formatAddress(string $address, string $district, string $country): string
    {
        $parts = array_filter([$address, $district, $country]);
        return implode(', ', $parts) ?: 'N/A';
    }

    /**
     * Validate UUID format.
     *
     * @param string $uuid
     * @return bool
     */
    protected function isValidUuid(string $uuid): bool
    {
        $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';
        return (bool) preg_match($pattern, $uuid);
    }

    /**
     * Validate that required RL-24 data fields are present.
     *
     * @param array $releve24Data The RL-24 document data
     * @return array List of validation errors (empty if valid)
     */
    protected function validateReleve24Data(array $releve24Data): array
    {
        $errors = [];

        // Required fields for RL-24 document
        $requiredFields = [
            'id' => 'Document ID',
            'document_year' => 'Document Year',
        ];

        foreach ($requiredFields as $field => $label) {
            if (!isset($releve24Data[$field]) || $releve24Data[$field] === '' || $releve24Data[$field] === null) {
                $errors[] = sprintf('Missing required field: %s', $label);
            }
        }

        // Validate document_year format (must be a valid year)
        if (isset($releve24Data['document_year'])) {
            $year = (int) $releve24Data['document_year'];
            if ($year < 2000 || $year > 2100) {
                $errors[] = sprintf('Invalid document year: %s', $releve24Data['document_year']);
            }
        }

        // Validate total_eligible is non-negative if present
        if (isset($releve24Data['total_eligible']) && (float) $releve24Data['total_eligible'] < 0) {
            $errors[] = 'Total eligible amount cannot be negative';
        }

        // Warn if no family or person is associated (data will be placeholder)
        if (empty($releve24Data['gibbonFamilyID']) && empty($releve24Data['gibbonPersonID'])) {
            // This is a warning, not an error - we can still generate with placeholders
            error_log(sprintf(
                'RL-24 document %s has no associated family or person - using placeholder data',
                $releve24Data['id'] ?? 'unknown'
            ));
        }

        return $errors;
    }

    /**
     * Validate batch size and throw exception if exceeded.
     *
     * @param int $count Number of documents in batch
     * @throws InvalidArgumentException If batch size exceeds maximum
     */
    protected function validateBatchSize(int $count): void
    {
        if ($count > self::MAX_BATCH_SIZE) {
            throw new InvalidArgumentException(sprintf(
                'Batch size (%d) exceeds maximum allowed (%d). Please reduce the number of documents.',
                $count,
                self::MAX_BATCH_SIZE
            ));
        }

        if ($count === 0) {
            throw new InvalidArgumentException('No documents provided for batch generation');
        }
    }

    /**
     * Get placeholder family data for missing records.
     *
     * @return array
     */
    protected function getPlaceholderFamilyData(): array
    {
        return [
            'name' => 'N/A',
            'familyName' => 'N/A',
            'address' => 'N/A',
            'email' => '',
        ];
    }

    /**
     * Get placeholder child data for missing records.
     *
     * @return array
     */
    protected function getPlaceholderChildData(): array
    {
        return [
            'name' => 'N/A',
            'dob' => '',
            'gender' => '',
        ];
    }

    /**
     * Get the last error details.
     *
     * @return array
     */
    public function getLastError(): array
    {
        return $this->lastError;
    }

    /**
     * Set custom template path.
     *
     * @param string $path
     * @return void
     */
    public function setTemplatePath(string $path): void
    {
        $this->templatePath = $path;
    }

    /**
     * Get current template path.
     *
     * @return string
     */
    public function getTemplatePath(): string
    {
        return $this->templatePath;
    }
}
