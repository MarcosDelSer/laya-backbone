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

use Gibbon\Forms\Form;
use Gibbon\Services\Format;
use Gibbon\Module\EnhancedFinance\Domain\Releve24PDFGenerator;

// Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/EnhancedFinance/releve24_pdf_batch.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Check if this is a POST request for batch PDF generation
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Get RL-24 IDs from POST data
        $releve24Ids = [];

        // Handle multiple input formats for IDs
        if (isset($_POST['ids']) && is_array($_POST['ids'])) {
            // Form submission with multiple checkboxes
            $releve24Ids = $_POST['ids'];
        } elseif (isset($_POST['ids']) && is_string($_POST['ids'])) {
            // Comma-separated string of IDs
            $releve24Ids = array_map('trim', explode(',', $_POST['ids']));
        }

        // Remove empty values
        $releve24Ids = array_filter($releve24Ids);

        // Validate that we have at least one ID
        if (empty($releve24Ids)) {
            $page->addError(__('At least one RL-24 document must be selected for batch generation.'));
            return;
        }

        // UUID validation pattern
        $uuidPattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

        // Validate all IDs are valid UUIDs
        $validIds = [];
        $invalidIds = [];
        foreach ($releve24Ids as $id) {
            if (preg_match($uuidPattern, $id)) {
                $validIds[] = $id;
            } else {
                $invalidIds[] = $id;
            }
        }

        // Check for invalid IDs
        if (!empty($invalidIds)) {
            error_log('RL-24 Batch PDF: Invalid document IDs provided: ' . implode(', ', $invalidIds));
        }

        // Ensure we have valid IDs to process
        if (empty($validIds)) {
            $page->addError(__('No valid RL-24 document IDs were provided.'));
            return;
        }

        // Limit batch size for performance (using constant from generator class)
        $maxBatchSize = Releve24PDFGenerator::MAX_BATCH_SIZE;
        if (count($validIds) > $maxBatchSize) {
            $page->addError(sprintf(__('Batch size exceeds maximum limit of %d documents. Please select fewer documents.'), $maxBatchSize));
            error_log(sprintf(
                'RL-24 Batch PDF: Rejected batch of %d documents (max: %d)',
                count($validIds),
                $maxBatchSize
            ));
            return;
        }

        try {
            // Set extended execution time for large batches
            $originalTimeout = ini_get('max_execution_time');
            $documentCount = count($validIds);
            $largeThreshold = Releve24PDFGenerator::LARGE_BATCH_THRESHOLD;

            if ($documentCount > 50) {
                // Allow 3 seconds per document plus 30 seconds overhead
                $newTimeout = 30 + ($documentCount * 3);
                set_time_limit($newTimeout);
                error_log(sprintf(
                    'RL-24 Batch PDF: Extended timeout to %d seconds for %d documents',
                    $newTimeout,
                    $documentCount
                ));
            }

            // Increase memory limit for large batches
            if ($documentCount > $largeThreshold) {
                ini_set('memory_limit', Releve24PDFGenerator::LARGE_BATCH_MEMORY_LIMIT);
                error_log(sprintf(
                    'RL-24 Batch PDF: Increased memory limit to %s for %d documents',
                    Releve24PDFGenerator::LARGE_BATCH_MEMORY_LIMIT,
                    $documentCount
                ));
            }

            // Initialize PDF generator
            $pdfGenerator = $container->get(Releve24PDFGenerator::class);

            // Generate batch ZIP file
            $zipContent = $pdfGenerator->generateBatchPDF($validIds);

            // Get any partial errors
            $lastError = $pdfGenerator->getLastError();
            if (!empty($lastError) && $lastError['code'] === 'PARTIAL_FAILURE') {
                // Log partial failures but continue with download
                error_log('RL-24 Batch PDF: Partial failure - ' . json_encode($lastError['details']));
            }

            // Generate filename with timestamp
            $timestamp = date('Ymd_His');
            $documentCount = count($validIds);
            $filename = "RL24_Batch_{$timestamp}_{$documentCount}documents.zip";

            // Send ZIP headers
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . strlen($zipContent));
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');
            header('Content-Transfer-Encoding: binary');

            // Output ZIP content
            echo $zipContent;
            exit;

        } catch (\InvalidArgumentException $e) {
            // Handle invalid input
            $page->addError(__('Invalid RL-24 documents selected. Please verify your selection.'));
            error_log(sprintf(
                'RL-24 Batch PDF Error (InvalidArgumentException): Count=%d, Error=%s',
                count($validIds),
                $e->getMessage()
            ));
            return;

        } catch (\RuntimeException $e) {
            // Handle ZIP creation or PDF generation failure
            $errorMessage = $e->getMessage();

            // Provide more specific error message based on the type of failure
            if (strpos($errorMessage, 'Failed to generate any PDFs') !== false) {
                $page->addError(__('Failed to generate any PDFs. The selected documents may be corrupted or have missing data.'));
            } elseif (strpos($errorMessage, 'memory') !== false || strpos($errorMessage, 'Memory') !== false) {
                $page->addError(__('Out of memory while generating batch PDF. Please try with fewer documents.'));
            } else {
                $page->addError(__('Error generating batch PDF. Please try again with fewer documents or contact support.'));
            }

            error_log(sprintf(
                'RL-24 Batch PDF Error (RuntimeException): Count=%d, Error=%s, Memory=%s',
                count($validIds),
                $e->getMessage(),
                ini_get('memory_limit')
            ));
            return;

        } catch (\Exception $e) {
            // Handle unexpected errors
            $page->addError(__('An unexpected error occurred while generating the batch PDF.'));
            error_log(sprintf(
                'RL-24 Batch PDF Error (Unexpected %s): Count=%d, Error=%s, Trace=%s',
                get_class($e),
                count($validIds),
                $e->getMessage(),
                $e->getTraceAsString()
            ));
            return;
        }
    }

    // Display batch selection form (GET request)
    $page->breadcrumbs->add(__('RL-24 Documents'), 'releve24.php');
    $page->breadcrumbs->add(__('Batch PDF Generation'));

    $gibbonSchoolYearID = $session->get('gibbonSchoolYearID');

    // Get available RL-24 documents
    $sql = "SELECT
                r.id,
                r.document_year,
                r.total_eligible,
                r.status,
                r.created_at,
                gf.name as familyName,
                gp.preferredName,
                gp.surname
            FROM enhanced_finance_releve24 r
            LEFT JOIN gibbonFamily gf ON r.gibbonFamilyID = gf.gibbonFamilyID
            LEFT JOIN gibbonPerson gp ON r.gibbonPersonID = gp.gibbonPersonID
            ORDER BY r.document_year DESC, gf.name ASC, gp.surname ASC";

    $result = $connection2->prepare($sql);
    $result->execute();
    $documents = $result->fetchAll(\PDO::FETCH_ASSOC);

    if (empty($documents)) {
        $page->addMessage(__('No RL-24 documents are available for PDF generation.'));
        return;
    }

    // Filter form
    $documentYear = $_GET['year'] ?? '';
    $statusFilter = $_GET['status'] ?? '';

    // Get unique years for filter
    $years = array_unique(array_column($documents, 'document_year'));
    rsort($years);

    $form = Form::create('filter', $session->get('absoluteURL') . '/index.php', 'get');
    $form->setTitle(__('Filter Documents'));
    $form->setClass('noIntBorder fullWidth');

    $form->addHiddenValue('q', '/modules/EnhancedFinance/releve24_pdf_batch.php');

    $row = $form->addRow();
        $row->addLabel('year', __('Document Year'));
        $yearOptions = ['' => __('All Years')];
        foreach ($years as $year) {
            $yearOptions[$year] = $year;
        }
        $row->addSelect('year')
            ->fromArray($yearOptions)
            ->selected($documentYear);

    $row = $form->addRow();
        $row->addLabel('status', __('Status'));
        $row->addSelect('status')
            ->fromArray([
                '' => __('All'),
                'draft' => __('Draft'),
                'finalized' => __('Finalized'),
                'sent' => __('Sent'),
            ])
            ->selected($statusFilter);

    $row = $form->addRow();
        $row->addSearchSubmit($session, __('Clear Filters'));

    echo $form->getOutput();

    // Apply filters
    $filteredDocuments = $documents;
    if (!empty($documentYear)) {
        $filteredDocuments = array_filter($filteredDocuments, function($doc) use ($documentYear) {
            return $doc['document_year'] == $documentYear;
        });
    }
    if (!empty($statusFilter)) {
        $filteredDocuments = array_filter($filteredDocuments, function($doc) use ($statusFilter) {
            return $doc['status'] == $statusFilter;
        });
    }

    if (empty($filteredDocuments)) {
        $page->addMessage(__('No RL-24 documents match your filter criteria.'));
        return;
    }

    // Batch selection form
    $batchForm = Form::create('batchPdf', $session->get('absoluteURL') . '/index.php?q=/modules/EnhancedFinance/releve24_pdf_batch.php', 'post');
    $batchForm->setTitle(sprintf(__('Select Documents (%d available)'), count($filteredDocuments)));

    // Quick selection buttons (JavaScript)
    echo '<div style="margin-bottom: 15px;">';
    echo '<button type="button" onclick="selectAll()" class="btn btn-sm">' . __('Select All') . '</button> ';
    echo '<button type="button" onclick="selectNone()" class="btn btn-sm">' . __('Select None') . '</button>';
    echo '</div>';

    // Document selection table
    echo '<form id="batchPdfForm" method="post" action="' . $session->get('absoluteURL') . '/index.php?q=/modules/EnhancedFinance/releve24_pdf_batch.php">';

    echo '<table class="fullWidth colorOddEven" cellspacing="0">';
    echo '<thead>';
    echo '<tr class="head">';
    echo '<th style="width: 30px;"><input type="checkbox" id="selectAllCheckbox" onclick="toggleAll(this)"></th>';
    echo '<th>' . __('Year') . '</th>';
    echo '<th>' . __('Family') . '</th>';
    echo '<th>' . __('Child') . '</th>';
    echo '<th>' . __('Amount') . '</th>';
    echo '<th>' . __('Status') . '</th>';
    echo '<th>' . __('Created') . '</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';

    foreach ($filteredDocuments as $doc) {
        $childName = trim(($doc['preferredName'] ?? '') . ' ' . ($doc['surname'] ?? '')) ?: __('N/A');
        $familyName = $doc['familyName'] ?? __('N/A');
        $amount = '$' . number_format((float)($doc['total_eligible'] ?? 0), 2);
        $status = ucfirst($doc['status'] ?? 'draft');
        $created = Format::date($doc['created_at'] ?? '');

        echo '<tr>';
        echo '<td><input type="checkbox" name="ids[]" value="' . htmlspecialchars($doc['id']) . '" class="doc-checkbox"></td>';
        echo '<td>' . htmlspecialchars($doc['document_year'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($familyName) . '</td>';
        echo '<td>' . htmlspecialchars($childName) . '</td>';
        echo '<td>' . $amount . '</td>';
        echo '<td>' . htmlspecialchars($status) . '</td>';
        echo '<td>' . $created . '</td>';
        echo '</tr>';
    }

    echo '</tbody>';
    echo '</table>';

    // Submit button
    echo '<div style="margin-top: 20px; text-align: right;">';
    echo '<span id="selectedCount" style="margin-right: 15px; color: #666;">' . __('0 documents selected') . '</span>';
    echo '<button type="submit" class="btn btn-primary" id="generateBtn" disabled>';
    echo '<i class="fas fa-file-archive"></i> ' . __('Generate Batch PDF (ZIP)');
    echo '</button>';
    echo '</div>';

    echo '</form>';

    // JavaScript for selection management
    echo '<script>
    function selectAll() {
        document.querySelectorAll(".doc-checkbox").forEach(function(cb) {
            cb.checked = true;
        });
        updateSelectedCount();
    }

    function selectNone() {
        document.querySelectorAll(".doc-checkbox").forEach(function(cb) {
            cb.checked = false;
        });
        updateSelectedCount();
    }

    function toggleAll(source) {
        document.querySelectorAll(".doc-checkbox").forEach(function(cb) {
            cb.checked = source.checked;
        });
        updateSelectedCount();
    }

    function updateSelectedCount() {
        var count = document.querySelectorAll(".doc-checkbox:checked").length;
        document.getElementById("selectedCount").textContent = count + " ' . __('documents selected') . '";
        document.getElementById("generateBtn").disabled = count === 0;

        // Update header checkbox state
        var total = document.querySelectorAll(".doc-checkbox").length;
        var headerCb = document.getElementById("selectAllCheckbox");
        headerCb.checked = count === total && total > 0;
        headerCb.indeterminate = count > 0 && count < total;
    }

    // Attach event listeners
    document.querySelectorAll(".doc-checkbox").forEach(function(cb) {
        cb.addEventListener("change", updateSelectedCount);
    });

    // Initial count update
    updateSelectedCount();

    // Form submission confirmation for large batches
    document.getElementById("batchPdfForm").addEventListener("submit", function(e) {
        var count = document.querySelectorAll(".doc-checkbox:checked").length;
        if (count > 100) {
            if (!confirm("' . __('You are about to generate PDFs for') . ' " + count + " ' . __('documents. This may take a few minutes. Continue?') . '")) {
                e.preventDefault();
            }
        }
    });
    </script>';
}
