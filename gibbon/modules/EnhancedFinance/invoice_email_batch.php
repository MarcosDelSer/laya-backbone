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

use Gibbon\Module\EnhancedFinance\Domain\InvoiceEmailDelivery;
use Gibbon\Module\EnhancedFinance\Domain\InvoicePDFGenerator;

// Module includes
require_once __DIR__ . '/moduleFunctions.php';

// Check if user is logged in
if (!isset($_SESSION[$guid]['gibbonPersonID'])) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode([
        'error' => true,
        'message' => 'You must be logged in to access this page',
    ]);
    exit;
}

// Check action access
$URL = $_SESSION[$guid]['absoluteURL'] . '/index.php?q=/modules/EnhancedFinance/invoices.php';
if (!isActionAccessible($guid, $connection2, '/modules/EnhancedFinance/invoices.php')) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode([
        'error' => true,
        'message' => 'You do not have access to this action',
    ]);
    exit;
}

// Get parameters
$month = $_REQUEST['month'] ?? date('n');
$year = $_REQUEST['year'] ?? date('Y');
$sendMode = $_REQUEST['sendMode'] ?? 'individual'; // 'individual' or 'batch'
$batchRecipientEmail = $_REQUEST['batchRecipientEmail'] ?? null;
$batchRecipientName = $_REQUEST['batchRecipientName'] ?? '';

// Validate month and year
if (!is_numeric($month) || $month < 1 || $month > 12) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode([
        'error' => true,
        'message' => 'Invalid month. Must be between 1 and 12.',
    ]);
    exit;
}

if (!is_numeric($year) || $year < 2000 || $year > 2100) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode([
        'error' => true,
        'message' => 'Invalid year. Must be between 2000 and 2100.',
    ]);
    exit;
}

// Get custom invoices data if provided (JSON format)
$invoicesDataJSON = $_POST['invoicesData'] ?? null;

try {
    // Parse invoices data
    $invoices = null;
    if ($invoicesDataJSON) {
        $invoices = json_decode($invoicesDataJSON, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid invoices data JSON: ' . json_last_error_msg());
        }

        if (!is_array($invoices)) {
            throw new Exception('Invoices data must be an array');
        }
    } else {
        // In production, fetch invoices from database for the specified month/year
        // For now, use demo data
        $monthName = date('F', mktime(0, 0, 0, $month, 1));
        $period = "{$monthName} {$year}";

        $invoices = [
            [
                'invoiceData' => [
                    'invoiceNumber' => "INV-{$year}-" . sprintf('%02d', $month) . '-001',
                    'invoiceDate' => "{$year}-{$month}-01",
                    'dueDate' => date('Y-m-d', strtotime("{$year}-{$month}-01 +30 days")),
                    'customerName' => 'Smith Family',
                    'items' => [
                        [
                            'description' => "Full-time Daycare - {$period}",
                            'quantity' => 1,
                            'unitPrice' => 800.00,
                        ],
                        [
                            'description' => 'Daily Meals',
                            'quantity' => 20,
                            'unitPrice' => 8.50,
                        ],
                    ],
                    'notes' => 'Payment due within 30 days.',
                    'paymentTerms' => 'Net 30 days',
                    'paymentMethods' => 'E-transfer, Credit Card, Cheque',
                ],
                'recipientEmail' => 'smith.family@example.com',
                'recipientName' => 'Mr. & Mrs. Smith',
            ],
            [
                'invoiceData' => [
                    'invoiceNumber' => "INV-{$year}-" . sprintf('%02d', $month) . '-002',
                    'invoiceDate' => "{$year}-{$month}-01",
                    'dueDate' => date('Y-m-d', strtotime("{$year}-{$month}-01 +30 days")),
                    'customerName' => 'Johnson Family',
                    'items' => [
                        [
                            'description' => "Part-time Daycare - {$period}",
                            'quantity' => 1,
                            'unitPrice' => 450.00,
                        ],
                        [
                            'description' => 'Daily Meals',
                            'quantity' => 10,
                            'unitPrice' => 8.50,
                        ],
                        [
                            'description' => 'After-school Activities',
                            'quantity' => 8,
                            'unitPrice' => 15.00,
                        ],
                    ],
                    'notes' => 'Payment due within 30 days.',
                    'paymentTerms' => 'Net 30 days',
                    'paymentMethods' => 'E-transfer, Credit Card, Cheque',
                ],
                'recipientEmail' => 'johnson.family@example.com',
                'recipientName' => 'Mr. & Mrs. Johnson',
            ],
            [
                'invoiceData' => [
                    'invoiceNumber' => "INV-{$year}-" . sprintf('%02d', $month) . '-003',
                    'invoiceDate' => "{$year}-{$month}-01",
                    'dueDate' => date('Y-m-d', strtotime("{$year}-{$month}-01 +30 days")),
                    'customerName' => 'Brown Family',
                    'items' => [
                        [
                            'description' => "Full-time Daycare - {$period}",
                            'quantity' => 1,
                            'unitPrice' => 800.00,
                        ],
                        [
                            'description' => 'Daily Meals',
                            'quantity' => 20,
                            'unitPrice' => 8.50,
                        ],
                        [
                            'description' => 'Extended Hours',
                            'quantity' => 15,
                            'unitPrice' => 12.00,
                        ],
                    ],
                    'notes' => 'Payment due within 30 days.',
                    'paymentTerms' => 'Net 30 days',
                    'paymentMethods' => 'E-transfer, Credit Card, Cheque',
                ],
                'recipientEmail' => 'brown.family@example.com',
                'recipientName' => 'Mr. & Mrs. Brown',
            ],
        ];
    }

    // Get InvoiceEmailDelivery service from container
    $container = $gibbon->getContainer();

    // Get required dependencies
    $mailer = $container->get('Gibbon\Contracts\Comms\Mailer');
    $session = $container->get('Gibbon\Contracts\Services\Session');
    $settingGateway = $container->get('Gibbon\Domain\System\SettingGateway');

    // Create InvoicePDFGenerator
    $pdfGenerator = new InvoicePDFGenerator($session, $settingGateway);

    // Create InvoiceEmailDelivery service
    $emailDelivery = new InvoiceEmailDelivery($mailer, $session, $settingGateway, $pdfGenerator);

    // Process based on send mode
    if ($sendMode === 'batch') {
        // Send as single combined PDF to one recipient
        if (empty($batchRecipientEmail)) {
            throw new Exception('Batch recipient email is required for batch mode');
        }

        // Extract just the invoice data for batch PDF generation
        $invoicesData = array_map(function ($invoice) {
            return $invoice['invoiceData'];
        }, $invoices);

        // Validate each invoice data
        foreach ($invoicesData as $index => $invoiceData) {
            if (!isset($invoiceData['invoiceNumber']) || empty($invoiceData['invoiceNumber'])) {
                throw new Exception("Invoice at index {$index} must include invoiceNumber");
            }
            if (!isset($invoiceData['customerName']) || empty($invoiceData['customerName'])) {
                throw new Exception("Invoice at index {$index} must include customerName");
            }
            if (!isset($invoiceData['items']) || !is_array($invoiceData['items']) || empty($invoiceData['items'])) {
                throw new Exception("Invoice at index {$index} must include items array");
            }
        }

        // Prepare batch email options
        $monthName = date('F', mktime(0, 0, 0, $month, 1));
        $options = [
            'filename' => "invoices_batch_{$year}-" . sprintf('%02d', $month) . '.pdf',
        ];

        // Send batch PDF email
        $result = $emailDelivery->sendBatchPDF($invoicesData, $batchRecipientEmail, $batchRecipientName, $options);

        if ($result['success']) {
            // Log the batch email send
            $logMessage = sprintf(
                'Batch invoice email sent: %d invoices for %s %d to %s (User: %s)',
                $result['invoiceCount'],
                $monthName,
                $year,
                $batchRecipientEmail,
                $_SESSION[$guid]['gibbonPersonID']
            );
            error_log($logMessage);

            // Return success response
            echo json_encode([
                'success' => true,
                'message' => $result['message'],
                'invoiceCount' => $result['invoiceCount'],
                'recipient' => $batchRecipientEmail,
                'month' => $month,
                'year' => $year,
                'sendMode' => 'batch',
            ]);
        } else {
            // Return error response
            header('HTTP/1.1 500 Internal Server Error');
            echo json_encode([
                'error' => true,
                'message' => $result['error']['message'] ?? 'Failed to send batch invoice email',
                'code' => $result['error']['code'] ?? 'SEND_FAILED',
            ]);
        }
    } else {
        // Send individual emails to each recipient
        // Validate each invoice structure
        foreach ($invoices as $index => $invoice) {
            if (!isset($invoice['invoiceData']) || !isset($invoice['recipientEmail'])) {
                throw new Exception("Invoice at index {$index} must include invoiceData and recipientEmail");
            }

            $invoiceData = $invoice['invoiceData'];
            if (!isset($invoiceData['invoiceNumber']) || empty($invoiceData['invoiceNumber'])) {
                throw new Exception("Invoice at index {$index} must include invoiceNumber in invoiceData");
            }
            if (!isset($invoiceData['customerName']) || empty($invoiceData['customerName'])) {
                throw new Exception("Invoice at index {$index} must include customerName in invoiceData");
            }
            if (!isset($invoiceData['items']) || !is_array($invoiceData['items']) || empty($invoiceData['items'])) {
                throw new Exception("Invoice at index {$index} must include items array in invoiceData");
            }
        }

        // Send individual emails
        $results = $emailDelivery->sendBatch($invoices);

        // Count successes and failures
        $successCount = 0;
        $failureCount = 0;
        $failedInvoices = [];

        foreach ($results as $result) {
            if ($result['success']) {
                $successCount++;
            } else {
                $failureCount++;
                $failedInvoices[] = [
                    'index' => $result['index'],
                    'error' => $result['error'] ?? ['code' => 'UNKNOWN', 'message' => 'Unknown error'],
                ];
            }
        }

        // Log the batch send
        $monthName = date('F', mktime(0, 0, 0, $month, 1));
        $logMessage = sprintf(
            'Individual invoice emails sent: %d/%d successful for %s %d (User: %s)',
            $successCount,
            count($invoices),
            $monthName,
            $year,
            $_SESSION[$guid]['gibbonPersonID']
        );
        error_log($logMessage);

        // Return summary response
        echo json_encode([
            'success' => $failureCount === 0,
            'message' => "{$successCount} of " . count($invoices) . " invoice emails sent successfully",
            'totalInvoices' => count($invoices),
            'successCount' => $successCount,
            'failureCount' => $failureCount,
            'failedInvoices' => $failedInvoices,
            'month' => $month,
            'year' => $year,
            'sendMode' => 'individual',
            'results' => $results,
        ]);
    }
} catch (Exception $e) {
    // Handle exceptions
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage(),
    ]);
}
