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
     * @throws InvalidArgumentException If the RL-24 document ID is invalid
     * @throws RuntimeException If PDF generation fails
     */
    public function generatePDF(string $releve24Id): string
    {
        // Validate UUID format
        if (!$this->isValidUuid($releve24Id)) {
            throw new InvalidArgumentException('Invalid RL-24 document ID format');
        }

        // Get RL-24 document data
        $releve24 = $this->getReleve24Data($releve24Id);
        if (!$releve24) {
            throw new InvalidArgumentException('RL-24 document not found: ' . $releve24Id);
        }

        // Get related data
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
            ];
            throw new RuntimeException('Failed to generate PDF: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Generate multiple RL-24 PDFs and return as ZIP archive.
     *
     * @param array $releve24Ids Array of RL-24 document UUIDs
     * @return string Binary ZIP content containing all PDFs
     * @throws InvalidArgumentException If no valid document IDs provided
     * @throws RuntimeException If ZIP creation or PDF generation fails
     */
    public function generateBatchPDF(array $releve24Ids): string
    {
        if (empty($releve24Ids)) {
            throw new InvalidArgumentException('No RL-24 document IDs provided');
        }

        // Filter valid UUIDs
        $validIds = array_filter($releve24Ids, [$this, 'isValidUuid']);
        if (empty($validIds)) {
            throw new InvalidArgumentException('No valid RL-24 document IDs provided');
        }

        // Create temporary ZIP file
        $zipFile = tempnam(sys_get_temp_dir(), 'rl24_batch_');
        if ($zipFile === false) {
            throw new RuntimeException('Failed to create temporary file for ZIP archive');
        }

        $zip = new ZipArchive();
        $openResult = $zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($openResult !== true) {
            unlink($zipFile);
            throw new RuntimeException('Failed to create ZIP archive');
        }

        $errors = [];
        $successCount = 0;

        foreach ($validIds as $releve24Id) {
            try {
                $pdfContent = $this->generatePDF($releve24Id);
                $releve24Data = $this->getReleve24Data($releve24Id);

                // Generate unique filename
                $filename = $this->generatePdfFilename($releve24Data);
                $zip->addFromString($filename, $pdfContent);
                $successCount++;
            } catch (\Exception $e) {
                $errors[$releve24Id] = $e->getMessage();
            }
        }

        $zip->close();

        // If all documents failed, throw exception
        if ($successCount === 0) {
            unlink($zipFile);
            throw new RuntimeException('Failed to generate any PDFs: ' . json_encode($errors));
        }

        // Read and clean up ZIP file
        $zipContent = file_get_contents($zipFile);
        unlink($zipFile);

        if ($zipContent === false) {
            throw new RuntimeException('Failed to read generated ZIP archive');
        }

        // Store partial errors if any
        if (!empty($errors)) {
            $this->lastError = [
                'code' => 'PARTIAL_FAILURE',
                'message' => 'Some documents failed to generate',
                'details' => $errors,
                'success_count' => $successCount,
            ];
        }

        return $zipContent;
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
