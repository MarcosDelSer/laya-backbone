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

use Gibbon\Module\RL24Submission\Domain\RL24TransmissionGateway;
use Gibbon\Module\RL24Submission\Services\RL24PaperSummaryGenerator;
use Gibbon\Module\RL24Submission\Services\RL24TransmissionFileNamer;

// Include core (this file is called directly for downloads)
include '../../gibbon.php';

// Access check
if (isActionAccessible($guid, $connection2, '/modules/RL24Submission/rl24_transmissions.php') == false) {
    // Silent fail for direct downloads - redirect to error page
    header('HTTP/1.0 403 Forbidden');
    die('Access Denied');
}

// Get required parameters
$gibbonRL24TransmissionID = $_GET['gibbonRL24TransmissionID'] ?? '';
$type = $_GET['type'] ?? 'xml';

// Validate transmission ID
if (empty($gibbonRL24TransmissionID)) {
    header('HTTP/1.0 400 Bad Request');
    die('Missing transmission ID');
}

// Get the transmission gateway
$transmissionGateway = $container->get(RL24TransmissionGateway::class);

// Get transmission details
$transmission = $transmissionGateway->getTransmissionByID($gibbonRL24TransmissionID);

if (empty($transmission)) {
    header('HTTP/1.0 404 Not Found');
    die('Transmission not found');
}

// Check transmission status - only allow downloads for appropriate statuses
$allowedStatuses = ['Generated', 'Validated', 'Submitted', 'Accepted'];
if (!in_array($transmission['status'], $allowedStatuses)) {
    header('HTTP/1.0 403 Forbidden');
    die('Transmission is not in a downloadable state');
}

// Handle different download types
switch ($type) {
    case 'xml':
        downloadXmlFile($transmission, $container);
        break;

    case 'summary':
        downloadPaperSummary($gibbonRL24TransmissionID, $transmission, $container);
        break;

    case 'slips':
        downloadSlipListing($gibbonRL24TransmissionID, $transmission, $container);
        break;

    default:
        header('HTTP/1.0 400 Bad Request');
        die('Invalid download type');
}

/**
 * Download the XML file for a transmission.
 *
 * @param array $transmission Transmission record
 * @param object $container DI container
 */
function downloadXmlFile(array $transmission, $container): void
{
    // Check if XML file exists
    if (empty($transmission['xmlFilePath'])) {
        header('HTTP/1.0 404 Not Found');
        die('XML file not generated');
    }

    // Get file namer service to resolve the path
    $fileNamer = $container->get(RL24TransmissionFileNamer::class);

    // Build the full path
    $xmlFilePath = $transmission['xmlFilePath'];

    // If path is relative, make it absolute
    if (!preg_match('/^\//', $xmlFilePath)) {
        // Assume relative to GIBBON_PATH
        $gibbonPath = defined('GIBBON_PATH') ? GIBBON_PATH : dirname(dirname(dirname(__FILE__)));
        $xmlFilePath = $gibbonPath . '/' . $xmlFilePath;
    }

    // Check if file exists
    if (!file_exists($xmlFilePath)) {
        // Try alternative path using the file namer storage directory
        $altPath = $fileNamer->getFullPath(
            $transmission['fileName'],
            $transmission['taxYear']
        );

        if (file_exists($altPath)) {
            $xmlFilePath = $altPath;
        } else {
            header('HTTP/1.0 404 Not Found');
            die('XML file not found on disk');
        }
    }

    // Check if file is readable
    if (!is_readable($xmlFilePath)) {
        header('HTTP/1.0 500 Internal Server Error');
        die('Cannot read XML file');
    }

    // Get file info
    $filename = $transmission['fileName'] ?? basename($xmlFilePath);
    $filesize = filesize($xmlFilePath);

    // Send headers for XML download
    header('Content-Type: application/xml; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . $filesize);
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Output file contents
    readfile($xmlFilePath);
    exit;
}

/**
 * Download paper summary as HTML or PDF.
 *
 * @param int $gibbonRL24TransmissionID Transmission ID
 * @param array $transmission Transmission record
 * @param object $container DI container
 */
function downloadPaperSummary(int $gibbonRL24TransmissionID, array $transmission, $container): void
{
    // Get paper summary generator service
    $summaryGenerator = $container->get(RL24PaperSummaryGenerator::class);

    // Generate print summary
    $printSummary = $summaryGenerator->generatePrintSummary($gibbonRL24TransmissionID);

    if (!$printSummary['success']) {
        header('HTTP/1.0 500 Internal Server Error');
        die('Failed to generate paper summary: ' . implode(', ', $printSummary['errors'] ?? ['Unknown error']));
    }

    // Generate filename for the summary
    $filename = sprintf(
        'RL24-Summary-%d-Seq%03d.html',
        $transmission['taxYear'],
        $transmission['sequenceNumber']
    );

    // Generate HTML output for print
    $html = generatePrintableHtml($printSummary, $transmission);

    // Send headers for HTML download
    header('Content-Type: text/html; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($html));
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo $html;
    exit;
}

/**
 * Download slip listing as HTML.
 *
 * @param int $gibbonRL24TransmissionID Transmission ID
 * @param array $transmission Transmission record
 * @param object $container DI container
 */
function downloadSlipListing(int $gibbonRL24TransmissionID, array $transmission, $container): void
{
    // Get paper summary generator service
    $summaryGenerator = $container->get(RL24PaperSummaryGenerator::class);

    // Generate slip listing
    $slipListing = $summaryGenerator->generateSlipListing($gibbonRL24TransmissionID);

    if (!$slipListing['success']) {
        header('HTTP/1.0 500 Internal Server Error');
        die('Failed to generate slip listing: ' . implode(', ', $slipListing['errors'] ?? ['Unknown error']));
    }

    // Generate filename for the listing
    $filename = sprintf(
        'RL24-Slips-%d-Seq%03d.html',
        $transmission['taxYear'],
        $transmission['sequenceNumber']
    );

    // Generate HTML output for print
    $html = generateSlipListingHtml($slipListing, $transmission);

    // Send headers for HTML download
    header('Content-Type: text/html; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($html));
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo $html;
    exit;
}

/**
 * Generate printable HTML for paper summary.
 *
 * @param array $printSummary Print summary data
 * @param array $transmission Transmission record
 * @return string HTML content
 */
function generatePrintableHtml(array $printSummary, array $transmission): string
{
    $html = '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($printSummary['title']) . '</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12pt;
            line-height: 1.4;
            margin: 20mm;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        .header h1 {
            font-size: 18pt;
            margin: 0 0 5px 0;
        }
        .header h2 {
            font-size: 14pt;
            font-weight: normal;
            margin: 0;
            color: #666;
        }
        .section {
            margin-bottom: 20px;
            page-break-inside: avoid;
        }
        .section-title {
            font-size: 14pt;
            font-weight: bold;
            border-bottom: 1px solid #999;
            padding-bottom: 5px;
            margin-bottom: 10px;
            color: #333;
        }
        .field-row {
            display: flex;
            margin-bottom: 8px;
        }
        .field-label {
            width: 200px;
            font-weight: bold;
            color: #555;
        }
        .field-value {
            flex: 1;
        }
        .amount-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .amount-table th,
        .amount-table td {
            border: 1px solid #999;
            padding: 8px;
            text-align: left;
        }
        .amount-table th {
            background-color: #f5f5f5;
            font-weight: bold;
        }
        .amount-table td.amount {
            text-align: right;
            font-family: monospace;
        }
        .certification {
            margin-top: 30px;
            padding: 15px;
            border: 1px solid #999;
            background-color: #f9f9f9;
        }
        .certification-text {
            font-style: italic;
            margin-bottom: 20px;
        }
        .signature-line {
            display: flex;
            margin-top: 30px;
        }
        .signature-field {
            flex: 1;
            margin-right: 20px;
        }
        .signature-field:last-child {
            margin-right: 0;
        }
        .signature-field label {
            display: block;
            font-size: 10pt;
            color: #666;
            margin-bottom: 5px;
        }
        .signature-field .line {
            border-bottom: 1px solid #333;
            height: 25px;
        }
        .footer {
            margin-top: 30px;
            font-size: 10pt;
            color: #666;
            text-align: center;
            border-top: 1px solid #ccc;
            padding-top: 10px;
        }
        @media print {
            body {
                margin: 15mm;
            }
            .header {
                page-break-after: avoid;
            }
            .section {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>RL-24 Sommaire</h1>
        <h2>' . htmlspecialchars($printSummary['title']) . '</h2>
    </div>';

    // Render each section
    foreach ($printSummary['sections'] as $sectionKey => $section) {
        $html .= '
    <div class="section">
        <div class="section-title">' . htmlspecialchars($section['label']) . '</div>';

        if ($sectionKey === 'summary') {
            // Special formatting for summary section with amounts table
            $html .= '
        <table class="amount-table">
            <thead>
                <tr>
                    <th>Description</th>
                    <th style="width: 150px;">Valeur</th>
                </tr>
            </thead>
            <tbody>';
            foreach ($section['fields'] as $field) {
                $isAmount = strpos($field['label'], 'Case') === 0 || strpos($field['label'], 'Frais') !== false;
                $html .= '
                <tr>
                    <td>' . htmlspecialchars($field['label']) . '</td>
                    <td class="' . ($isAmount ? 'amount' : '') . '">' . htmlspecialchars($field['value']) . '</td>
                </tr>';
            }
            $html .= '
            </tbody>
        </table>';
        } elseif ($sectionKey === 'certification') {
            // Special formatting for certification section
            $html .= '
        <div class="certification">
            <div class="certification-text">' . htmlspecialchars($section['text']) . '</div>
            <div class="signature-line">';
            foreach ($section['fields'] as $field) {
                $html .= '
                <div class="signature-field">
                    <label>' . htmlspecialchars($field['label']) . '</label>
                    <div class="line">' . (isset($field['editable']) && $field['editable'] ? '' : htmlspecialchars($field['value'])) . '</div>
                </div>';
            }
            $html .= '
            </div>
        </div>';
        } else {
            // Standard field rows
            foreach ($section['fields'] as $field) {
                $html .= '
        <div class="field-row">
            <div class="field-label">' . htmlspecialchars($field['label']) . ':</div>
            <div class="field-value">' . nl2br(htmlspecialchars($field['value'])) . '</div>
        </div>';
            }
        }

        $html .= '
    </div>';
    }

    // Footer with metadata
    $html .= '
    <div class="footer">
        <p>Document généré le ' . htmlspecialchars($printSummary['metadata']['generatedAt']) . '</p>
        <p>ID de transmission: ' . htmlspecialchars($printSummary['metadata']['transmissionID']) . '</p>
    </div>
</body>
</html>';

    return $html;
}

/**
 * Generate HTML for slip listing.
 *
 * @param array $slipListing Slip listing data
 * @param array $transmission Transmission record
 * @return string HTML content
 */
function generateSlipListingHtml(array $slipListing, array $transmission): string
{
    $html = '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RL-24 Liste des relevés - Année ' . htmlspecialchars($transmission['taxYear']) . '</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11pt;
            line-height: 1.3;
            margin: 15mm;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 15px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        .header h1 {
            font-size: 16pt;
            margin: 0 0 5px 0;
        }
        .header p {
            margin: 5px 0;
            color: #666;
        }
        .summary {
            background-color: #f5f5f5;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
        }
        .summary strong {
            margin-right: 20px;
        }
        .slip {
            page-break-inside: avoid;
            border: 1px solid #999;
            margin-bottom: 15px;
            padding: 10px;
        }
        .slip-header {
            display: flex;
            justify-content: space-between;
            border-bottom: 1px solid #ccc;
            padding-bottom: 5px;
            margin-bottom: 10px;
        }
        .slip-number {
            font-weight: bold;
            font-size: 14pt;
        }
        .slip-type {
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 10pt;
        }
        .slip-type.original {
            background-color: #d4edda;
            color: #155724;
        }
        .slip-type.amended {
            background-color: #fff3cd;
            color: #856404;
        }
        .slip-type.cancelled {
            background-color: #f8d7da;
            color: #721c24;
        }
        .slip-content {
            display: flex;
            gap: 20px;
        }
        .slip-section {
            flex: 1;
        }
        .slip-section h4 {
            font-size: 11pt;
            margin: 0 0 5px 0;
            color: #555;
        }
        .slip-section p {
            margin: 3px 0;
        }
        .amounts-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .amounts-table th,
        .amounts-table td {
            border: 1px solid #ccc;
            padding: 5px;
            text-align: center;
            font-size: 10pt;
        }
        .amounts-table th {
            background-color: #f0f0f0;
        }
        .amounts-table td {
            font-family: monospace;
        }
        .footer {
            margin-top: 20px;
            font-size: 9pt;
            color: #666;
            text-align: center;
            border-top: 1px solid #ccc;
            padding-top: 10px;
        }
        @media print {
            .slip {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>RL-24 Liste des relevés</h1>
        <p>Année d\'imposition: <strong>' . htmlspecialchars($transmission['taxYear']) . '</strong></p>
        <p>Numéro de séquence: <strong>' . str_pad($transmission['sequenceNumber'], 3, '0', STR_PAD_LEFT) . '</strong></p>
        <p>Fichier: <strong>' . htmlspecialchars($transmission['fileName'] ?? 'N/A') . '</strong></p>
    </div>

    <div class="summary">
        <strong>Total des relevés: ' . count($slipListing['slips']) . '</strong>
    </div>';

    // Render each slip
    foreach ($slipListing['slips'] as $index => $slip) {
        $typeClass = 'original';
        if ($slip['slipTypeCode'] === 'A') {
            $typeClass = 'amended';
        } elseif ($slip['slipTypeCode'] === 'D') {
            $typeClass = 'cancelled';
        }

        $html .= '
    <div class="slip">
        <div class="slip-header">
            <span class="slip-number">Relevé #' . str_pad($slip['slipNumber'], 3, '0', STR_PAD_LEFT) . '</span>
            <span class="slip-type ' . $typeClass . '">' . htmlspecialchars($slip['slipType']) . '</span>
        </div>
        <div class="slip-content">
            <div class="slip-section">
                <h4>Bénéficiaire (Parent)</h4>
                <p><strong>' . htmlspecialchars($slip['recipient']['name']) . '</strong></p>
                <p>NAS: ' . htmlspecialchars($slip['recipient']['sin']) . '</p>
                <p>' . htmlspecialchars($slip['recipient']['address']) . '</p>
                <p>' . htmlspecialchars($slip['recipient']['city']) . ', ' . htmlspecialchars($slip['recipient']['province']) . ' ' . htmlspecialchars($slip['recipient']['postalCode']) . '</p>
            </div>
            <div class="slip-section">
                <h4>Enfant</h4>
                <p><strong>' . htmlspecialchars($slip['child']['name']) . '</strong></p>
                <p>Date de naissance: ' . htmlspecialchars($slip['child']['dateOfBirth']) . '</p>
                <p>Période de service:</p>
                <p>' . htmlspecialchars($slip['servicePeriod']['start']) . ' au ' . htmlspecialchars($slip['servicePeriod']['end']) . '</p>
            </div>
        </div>
        <table class="amounts-table">
            <thead>
                <tr>
                    <th>Case 10<br>(Jours)</th>
                    <th>Case 11<br>(Frais payés)</th>
                    <th>Case 12<br>(Frais admissibles)</th>
                    <th>Case 13<br>(Contrib. gouv.)</th>
                    <th>Case 14<br>(Frais nets)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>' . $slip['amounts']['case10'] . '</td>
                    <td>' . htmlspecialchars($slip['amounts']['case11']) . '</td>
                    <td>' . htmlspecialchars($slip['amounts']['case12']) . '</td>
                    <td>' . htmlspecialchars($slip['amounts']['case13']) . '</td>
                    <td>' . htmlspecialchars($slip['amounts']['case14']) . '</td>
                </tr>
            </tbody>
        </table>
    </div>';
    }

    $html .= '
    <div class="footer">
        <p>Document généré le ' . date('Y-m-d H:i:s') . '</p>
        <p>Total: ' . count($slipListing['slips']) . ' relevés</p>
    </div>
</body>
</html>';

    return $html;
}
