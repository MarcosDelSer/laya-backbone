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
 * Enhanced Finance Module - Accounting Export Process
 *
 * Handles export requests for Sage 50 and QuickBooks formats.
 * Validates input, generates exports using the appropriate exporter class,
 * and redirects with success/error messages.
 *
 * @package    Gibbon\Module\EnhancedFinance
 * @author     LAYA
 * @license    GPL-3.0
 */

use Gibbon\Module\EnhancedFinance\Export\Sage50Exporter;
use Gibbon\Module\EnhancedFinance\Export\QuickBooksExporter;
use Gibbon\Module\EnhancedFinance\Domain\InvoiceGateway;
use Gibbon\Module\EnhancedFinance\Domain\PaymentGateway;
use Gibbon\Module\EnhancedFinance\Domain\ExportGateway;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Services\Format;

// Set up return URL
$URL = $session->get('absoluteURL') . '/index.php?q=/modules/EnhancedFinance/finance_export.php';

// Access check
if (isActionAccessible($guid, $connection2, '/modules/EnhancedFinance/finance_export.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
}

// Get POST parameters
$gibbonSchoolYearID = $_POST['gibbonSchoolYearID'] ?? '';
$exportFormat = $_POST['exportFormat'] ?? '';
$exportType = $_POST['exportType'] ?? '';
$dateFrom = !empty($_POST['dateFrom']) ? Format::dateConvert($_POST['dateFrom']) : null;
$dateTo = !empty($_POST['dateTo']) ? Format::dateConvert($_POST['dateTo']) : null;
$preview = ($_POST['preview'] ?? '') === 'Y';

// Add school year ID to URL
$URL .= '&gibbonSchoolYearID=' . $gibbonSchoolYearID;

// Validate required fields
if (empty($gibbonSchoolYearID) || empty($exportFormat) || empty($exportType)) {
    $URL .= '&return=error3';
    header("Location: {$URL}");
    exit;
}

// Validate export format
$validFormats = ['Sage50', 'QuickBooks'];
if (!in_array($exportFormat, $validFormats)) {
    $URL .= '&return=error5';
    header("Location: {$URL}");
    exit;
}

// Validate export type
$validTypes = ['invoices', 'payments', 'combined'];
if (!in_array($exportType, $validTypes)) {
    $URL .= '&return=error3';
    header("Location: {$URL}");
    exit;
}

// Get the current user's ID
$exportedByID = $session->get('gibbonPersonID');

// Get required gateways
$settingGateway = $container->get(SettingGateway::class);
$invoiceGateway = $container->get(InvoiceGateway::class);
$paymentGateway = $container->get(PaymentGateway::class);
$exportGateway = $container->get(ExportGateway::class);

// Validate settings based on export format
if ($exportFormat === 'Sage50') {
    $arAccount = $settingGateway->getSettingByScope('Enhanced Finance', 'sage50AccountsReceivable');
    $revenueAccount = $settingGateway->getSettingByScope('Enhanced Finance', 'sage50RevenueAccount');

    if (empty($arAccount) || empty($revenueAccount)) {
        $URL .= '&return=error4';
        header("Location: {$URL}");
        exit;
    }
} else if ($exportFormat === 'QuickBooks') {
    $arAccount = $settingGateway->getSettingByScope('Enhanced Finance', 'quickbooksARAccount');
    $revenueAccount = $settingGateway->getSettingByScope('Enhanced Finance', 'quickbooksIncomeAccount');

    // QuickBooks has defaults, so only check if completely empty
    // Settings are optional as the exporter has default values
}

// Handle preview mode
if ($preview) {
    // Create exporter for preview
    if ($exportFormat === 'Sage50') {
        $exporter = new Sage50Exporter(
            $connection2,
            $settingGateway,
            $invoiceGateway,
            $paymentGateway,
            $exportGateway
        );
    } else {
        $exporter = new QuickBooksExporter(
            $connection2,
            $settingGateway,
            $invoiceGateway,
            $paymentGateway,
            $exportGateway
        );
    }

    // Determine which preview to get
    $previewType = $exportType === 'combined' ? 'invoices' : $exportType;
    $previewData = $exporter->getPreview($previewType, $gibbonSchoolYearID, $dateFrom, $dateTo, 10);

    // Store preview data in session for display
    $_SESSION['exportPreview'] = [
        'format' => $exportFormat,
        'type' => $exportType,
        'dateFrom' => $dateFrom,
        'dateTo' => $dateTo,
        'data' => $previewData
    ];

    $URL .= '&preview=1';
    header("Location: {$URL}");
    exit;
}

// Proceed with actual export
try {
    // Create the appropriate exporter
    if ($exportFormat === 'Sage50') {
        $exporter = new Sage50Exporter(
            $connection2,
            $settingGateway,
            $invoiceGateway,
            $paymentGateway,
            $exportGateway
        );
    } else {
        $exporter = new QuickBooksExporter(
            $connection2,
            $settingGateway,
            $invoiceGateway,
            $paymentGateway,
            $exportGateway
        );
    }

    // Execute export based on type
    switch ($exportType) {
        case 'invoices':
            $result = $exporter->exportInvoices($gibbonSchoolYearID, $dateFrom, $dateTo, $exportedByID);
            break;

        case 'payments':
            $result = $exporter->exportPayments($gibbonSchoolYearID, $dateFrom, $dateTo, $exportedByID);
            break;

        case 'combined':
            $result = $exporter->exportCombined($gibbonSchoolYearID, $dateFrom, $dateTo, $exportedByID);
            break;

        default:
            $URL .= '&return=error3';
            header("Location: {$URL}");
            exit;
    }

    // Check result
    if ($exportType === 'combined') {
        // Combined export has both invoices and payments results
        $invoiceSuccess = $result['invoices']['success'] ?? false;
        $paymentSuccess = $result['payments']['success'] ?? false;

        if ($invoiceSuccess && $paymentSuccess) {
            // Both successful
            $URL .= '&return=success1';
            $URL .= '&invoiceExportID=' . ($result['invoices']['exportLogID'] ?? '');
            $URL .= '&paymentExportID=' . ($result['payments']['exportLogID'] ?? '');
        } else if ($invoiceSuccess || $paymentSuccess) {
            // Partial success
            $URL .= '&return=success1';
            if ($invoiceSuccess) {
                $URL .= '&invoiceExportID=' . ($result['invoices']['exportLogID'] ?? '');
            }
            if ($paymentSuccess) {
                $URL .= '&paymentExportID=' . ($result['payments']['exportLogID'] ?? '');
            }
        } else {
            // Both failed
            $error = $result['invoices']['error'] ?? $result['payments']['error'] ?? 'Unknown error';
            if (strpos($error, 'No ') !== false) {
                $URL .= '&return=error2';
            } else {
                $URL .= '&return=error1';
            }
        }
    } else {
        // Single export type
        if ($result['success']) {
            $URL .= '&return=success1';
            $URL .= '&exportID=' . ($result['exportLogID'] ?? '');
        } else {
            $error = $result['error'] ?? 'Unknown error';
            if (strpos($error, 'No ') !== false) {
                $URL .= '&return=error2';
            } else {
                $URL .= '&return=error1';
            }
        }
    }

} catch (Exception $e) {
    // Log the error
    error_log('EnhancedFinance Export Error: ' . $e->getMessage());

    $URL .= '&return=error1';
}

header("Location: {$URL}");
exit;
