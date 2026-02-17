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

/**
 * Enhanced Finance Module - Export File Download Handler
 *
 * Securely serves exported accounting files from the export log.
 * Validates user permissions, file existence, and integrity before serving.
 *
 * Security Features:
 * - Permission check via isActionAccessible()
 * - Export log ID validation
 * - File path traversal prevention
 * - Checksum verification
 * - Completed status check
 *
 * @package    Gibbon\Module\EnhancedFinance
 * @author     LAYA
 * @license    GPL-3.0
 */

use Gibbon\Module\EnhancedFinance\Domain\ExportGateway;

// Get absolute path for redirects
$absoluteURL = $session->get('absoluteURL');
$URL = $absoluteURL . '/index.php?q=/modules/EnhancedFinance/finance_export.php';

// Access check - use same permission as the main export page
if (isActionAccessible($guid, $connection2, '/modules/EnhancedFinance/finance_export.php') == false) {
    // Access denied - redirect with error
    header("Location: {$URL}&return=error0");
    exit;
}

// Get the export log ID from request
$gibbonEnhancedFinanceExportLogID = $_GET['gibbonEnhancedFinanceExportLogID'] ?? '';
$gibbonSchoolYearID = $_GET['gibbonSchoolYearID'] ?? '';

// Validate export log ID
if (empty($gibbonEnhancedFinanceExportLogID)) {
    header("Location: {$URL}&return=error3&gibbonSchoolYearID={$gibbonSchoolYearID}");
    exit;
}

// Get the export gateway
$exportGateway = $container->get(ExportGateway::class);

// Fetch the export record
$export = $exportGateway->selectExportByID($gibbonEnhancedFinanceExportLogID);

// Validate export exists
if (empty($export)) {
    header("Location: {$URL}&return=error3&gibbonSchoolYearID={$gibbonSchoolYearID}");
    exit;
}

// Validate export is completed
if ($export['status'] !== 'Completed') {
    header("Location: {$URL}&return=error1&gibbonSchoolYearID={$gibbonSchoolYearID}");
    exit;
}

// Validate file path is set
if (empty($export['filePath'])) {
    header("Location: {$URL}&return=error1&gibbonSchoolYearID={$gibbonSchoolYearID}");
    exit;
}

// Construct full file path
$absolutePath = $session->get('absolutePath');
$filePath = $absolutePath . '/' . ltrim($export['filePath'], '/');

// Security: Validate file path is within expected directory
// Prevent path traversal attacks
$expectedBaseDir = $absolutePath . '/uploads/EnhancedFinance/exports';
$realFilePath = realpath($filePath);
$realBaseDir = realpath($expectedBaseDir);

// Check that realpath succeeded (file exists) and path is within expected directory
if ($realFilePath === false || $realBaseDir === false) {
    // File or base directory doesn't exist
    header("Location: {$URL}&return=error1&gibbonSchoolYearID={$gibbonSchoolYearID}");
    exit;
}

// Ensure file is within the expected exports directory
if (strpos($realFilePath, $realBaseDir) !== 0) {
    // Security: Path traversal attempt detected
    error_log('EnhancedFinance Export Download: Path traversal attempt detected for export ID ' . $gibbonEnhancedFinanceExportLogID);
    header("Location: {$URL}&return=error0&gibbonSchoolYearID={$gibbonSchoolYearID}");
    exit;
}

// Verify file exists and is readable
if (!file_exists($realFilePath) || !is_readable($realFilePath)) {
    header("Location: {$URL}&return=error1&gibbonSchoolYearID={$gibbonSchoolYearID}");
    exit;
}

// Verify file integrity using stored checksum (if available)
if (!empty($export['checksum'])) {
    $fileChecksum = hash_file('sha256', $realFilePath);
    if ($fileChecksum !== $export['checksum']) {
        // Checksum mismatch - file may have been tampered with
        error_log('EnhancedFinance Export Download: Checksum mismatch for export ID ' . $gibbonEnhancedFinanceExportLogID);
        header("Location: {$URL}&return=error1&gibbonSchoolYearID={$gibbonSchoolYearID}");
        exit;
    }
}

// Determine content type based on file extension
$fileName = $export['fileName'] ?? basename($realFilePath);
$extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

$contentTypes = [
    'csv' => 'text/csv; charset=utf-8',
    'iif' => 'text/plain; charset=utf-8',
    'qbo' => 'application/x-qbooks',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'xls' => 'application/vnd.ms-excel',
    'txt' => 'text/plain; charset=utf-8',
];

$contentType = $contentTypes[$extension] ?? 'application/octet-stream';

// Get file size
$fileSize = filesize($realFilePath);

// Clear any previous output buffers
while (ob_get_level()) {
    ob_end_clean();
}

// Set headers for file download
header('Content-Type: ' . $contentType);
header('Content-Disposition: attachment; filename="' . addslashes($fileName) . '"');
header('Content-Length: ' . $fileSize);
header('Content-Transfer-Encoding: binary');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Prevent script timeout for large files
set_time_limit(300);

// Output the file
readfile($realFilePath);

exit;
