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
 * Enhanced Finance Module - Report Excel Export Handler
 *
 * Handles Excel export for financial reports (aging, collection, revenue).
 * Uses PhpSpreadsheet via ExcelExporter base class for professional Excel output.
 *
 * Features:
 * - Formatted Excel exports with headers, styling, and auto-sizing
 * - Summary sections with totals
 * - Proper date and currency formatting
 * - Export logging for audit trail
 *
 * @package    Gibbon\Module\EnhancedFinance
 * @author     LAYA
 * @license    GPL-3.0
 */

use Gibbon\Services\Format;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Domain\School\SchoolYearGateway;
use Gibbon\Module\EnhancedFinance\Domain\InvoiceGateway;
use Gibbon\Module\EnhancedFinance\Domain\PaymentGateway;
use Gibbon\Module\EnhancedFinance\Domain\ExportGateway;
use Gibbon\Module\EnhancedFinance\Export\ReportExcelExporter;

// Access check - allow access if user can access any of the report pages
$reportPages = [
    '/modules/EnhancedFinance/finance_report_aging.php',
    '/modules/EnhancedFinance/finance_report_collection.php',
    '/modules/EnhancedFinance/finance_report_revenue.php',
];

$hasAccess = false;
foreach ($reportPages as $reportPage) {
    if (isActionAccessible($guid, $connection2, $reportPage)) {
        $hasAccess = true;
        break;
    }
}

if ($hasAccess == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get parameters
    $reportType = $_GET['type'] ?? '';
    $gibbonSchoolYearID = $_GET['gibbonSchoolYearID'] ?? $session->get('gibbonSchoolYearID');
    $filterStage = $_GET['stage'] ?? ''; // For collection report filtering

    // Validate report type
    $validTypes = ['aging', 'collection', 'revenue'];
    if (!in_array($reportType, $validTypes)) {
        $page->addError(__('Invalid report type specified.'));
        return;
    }

    // Check specific report access
    $reportPageMap = [
        'aging' => '/modules/EnhancedFinance/finance_report_aging.php',
        'collection' => '/modules/EnhancedFinance/finance_report_collection.php',
        'revenue' => '/modules/EnhancedFinance/finance_report_revenue.php',
    ];

    if (!isActionAccessible($guid, $connection2, $reportPageMap[$reportType])) {
        $page->addError(__('You do not have access to this action.'));
        return;
    }

    // Get gateways and settings
    $invoiceGateway = $container->get(InvoiceGateway::class);
    $settingGateway = $container->get(SettingGateway::class);
    $schoolYearGateway = $container->get(SchoolYearGateway::class);
    $exportGateway = $container->get(ExportGateway::class);

    // Get school year information
    $schoolYear = $schoolYearGateway->getByID($gibbonSchoolYearID);
    $schoolYearName = $schoolYear['name'] ?? __('Current Year');

    // Get currency setting
    $currency = $settingGateway->getSettingByScope('System', 'currency') ?: 'CAD';

    // Get user ID for export logging
    $exportedByID = $session->get('gibbonPersonID');

    try {
        // Create exporter
        $exporter = new ReportExcelExporter(
            $pdo,
            $settingGateway,
            $exportGateway
        );

        // Generate export based on report type
        switch ($reportType) {
            case 'aging':
                $result = $exporter->exportAgingReport(
                    $gibbonSchoolYearID,
                    $schoolYearName,
                    $currency,
                    $exportedByID
                );
                break;

            case 'collection':
                $result = $exporter->exportCollectionReport(
                    $gibbonSchoolYearID,
                    $schoolYearName,
                    $currency,
                    $exportedByID,
                    $filterStage
                );
                break;

            case 'revenue':
                $result = $exporter->exportRevenueReport(
                    $gibbonSchoolYearID,
                    $schoolYearName,
                    $currency,
                    $exportedByID
                );
                break;
        }

        if ($result && isset($result['filePath']) && file_exists($result['filePath'])) {
            // Set headers for Excel download
            $fileName = basename($result['filePath']);
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
            header('Content-Length: ' . filesize($result['filePath']));
            header('Cache-Control: max-age=0');
            header('Pragma: public');

            // Clear output buffer
            if (ob_get_level()) {
                ob_end_clean();
            }

            // Output file
            readfile($result['filePath']);
            exit;
        } else {
            $page->addError(__('Failed to generate Excel export.'));
        }

    } catch (\Exception $e) {
        $page->addError(__('An error occurred while generating the export: ') . $e->getMessage());
    }
}
