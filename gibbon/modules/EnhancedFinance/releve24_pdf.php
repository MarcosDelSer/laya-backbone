<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuiber and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Gibbon Core) and Gibbon LAYA are trademarks of Gibbon Education Ltd.

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

use Gibbon\Module\EnhancedFinance\Domain\Releve24PDFGenerator;

// Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/EnhancedFinance/releve24_pdf.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get parameters from request
    $releve24Id = $_GET['id'] ?? '';
    $displayMode = $_GET['display'] ?? 'download';

    // Validate RL-24 ID
    if (empty($releve24Id)) {
        $page->addError(__('RL-24 document ID is required.'));
        return;
    }

    // Validate UUID format
    $uuidPattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';
    if (!preg_match($uuidPattern, $releve24Id)) {
        $page->addError(__('Invalid RL-24 document ID format.'));
        return;
    }

    try {
        // Initialize PDF generator
        $pdfGenerator = $container->get(Releve24PDFGenerator::class);

        // Generate PDF
        $pdfContent = $pdfGenerator->generatePDF($releve24Id);

        // Get document year for filename
        // Query the database to get document details for filename
        $sql = "SELECT document_year, gibbonFamilyID, gibbonPersonID FROM enhanced_finance_releve24 WHERE id = :id";
        $result = $connection2->selectOne($sql, ['id' => $releve24Id]);

        $documentYear = $result['document_year'] ?? date('Y');
        $familyId = $result['gibbonFamilyID'] ?? 'unknown';
        $childId = $result['gibbonPersonID'] ?? '';

        $suffix = $childId ? "_{$childId}" : '';
        $filename = "RL24_{$documentYear}_{$familyId}{$suffix}.pdf";

        // Determine content disposition based on display mode
        if ($displayMode === 'print') {
            // Inline disposition for browser viewing/printing
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . $filename . '"');
            header('Content-Length: ' . strlen($pdfContent));
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');
        } else {
            // Attachment disposition for download
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . strlen($pdfContent));
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');
            header('Content-Transfer-Encoding: binary');
        }

        // Output PDF content
        echo $pdfContent;
        exit;

    } catch (\InvalidArgumentException $e) {
        // Handle invalid UUID or document not found
        $page->addError(__('RL-24 document not found or invalid ID provided.'));
        return;

    } catch (\RuntimeException $e) {
        // Handle PDF generation failure
        $page->addError(__('Error generating PDF. Please try again later.'));

        // Log error for administrators
        error_log('RL-24 PDF Generation Error: ' . $e->getMessage());
        return;

    } catch (\Exception $e) {
        // Handle unexpected errors
        $page->addError(__('An unexpected error occurred while generating the PDF.'));

        // Log error for administrators
        error_log('RL-24 PDF Generation Unexpected Error: ' . $e->getMessage());
        return;
    }
}
