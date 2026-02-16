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
$invoiceID = $_REQUEST['invoiceID'] ?? null;
$recipientEmail = $_REQUEST['recipientEmail'] ?? null;
$recipientName = $_REQUEST['recipientName'] ?? '';

// Get optional parameters
$customSubject = $_REQUEST['subject'] ?? null;
$customBody = $_REQUEST['body'] ?? null;
$ccEmails = $_REQUEST['cc'] ?? null;
$bccEmails = $_REQUEST['bcc'] ?? null;

// Parse CC/BCC emails (comma-separated or array)
$ccArray = null;
if ($ccEmails) {
    $ccArray = is_array($ccEmails) ? $ccEmails : array_map('trim', explode(',', $ccEmails));
}

$bccArray = null;
if ($bccEmails) {
    $bccArray = is_array($bccEmails) ? $bccEmails : array_map('trim', explode(',', $bccEmails));
}

// Get custom invoice data if provided (JSON format)
$invoiceDataJSON = $_POST['invoiceData'] ?? null;

try {
    // Validate required parameters
    if (empty($invoiceID)) {
        throw new Exception('Invoice ID is required');
    }

    if (empty($recipientEmail)) {
        throw new Exception('Recipient email is required');
    }

    // Parse invoice data
    $invoiceData = null;
    if ($invoiceDataJSON) {
        $invoiceData = json_decode($invoiceDataJSON, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid invoice data JSON: ' . json_last_error_msg());
        }
    } else {
        // In production, fetch invoice data from database using $invoiceID
        // For now, use demo data
        $invoiceData = [
            'invoiceNumber' => 'INV-2026-02-001',
            'invoiceDate' => date('Y-m-d'),
            'dueDate' => date('Y-m-d', strtotime('+30 days')),
            'customerName' => $recipientName ?: 'Valued Customer',
            'customerEmail' => $recipientEmail,
            'customerAddress' => '123 Main Street, Montreal, QC H3A 1B1',
            'items' => [
                [
                    'description' => 'Full-time Daycare - ' . date('F Y'),
                    'quantity' => 1,
                    'unitPrice' => 800.00,
                ],
                [
                    'description' => 'Daily Meals',
                    'quantity' => 20,
                    'unitPrice' => 8.50,
                ],
            ],
            'notes' => 'Payment due within 30 days. Thank you for your business.',
            'paymentTerms' => 'Net 30 days',
            'paymentMethods' => 'E-transfer, Credit Card, Cheque',
        ];
    }

    // Validate invoice data structure
    if (!isset($invoiceData['invoiceNumber']) || empty($invoiceData['invoiceNumber'])) {
        throw new Exception('Invoice data must include invoiceNumber');
    }

    if (!isset($invoiceData['customerName']) || empty($invoiceData['customerName'])) {
        throw new Exception('Invoice data must include customerName');
    }

    if (!isset($invoiceData['items']) || !is_array($invoiceData['items']) || empty($invoiceData['items'])) {
        throw new Exception('Invoice data must include items array');
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

    // Prepare email options
    $options = [];
    if ($customSubject) {
        $options['subject'] = $customSubject;
    }
    if ($customBody) {
        $options['body'] = $customBody;
    }
    if ($ccArray) {
        $options['cc'] = $ccArray;
    }
    if ($bccArray) {
        $options['bcc'] = $bccArray;
    }

    // Send the invoice email
    $result = $emailDelivery->send($invoiceData, $recipientEmail, $recipientName, $options);

    if ($result['success']) {
        // Log the email send
        $logMessage = sprintf(
            'Invoice email sent: %s to %s (User: %s)',
            $invoiceData['invoiceNumber'],
            $recipientEmail,
            $_SESSION[$guid]['gibbonPersonID']
        );
        error_log($logMessage);

        // Return success response
        echo json_encode([
            'success' => true,
            'message' => $result['message'],
            'invoiceNumber' => $invoiceData['invoiceNumber'],
            'recipient' => $recipientEmail,
        ]);
    } else {
        // Return error response
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode([
            'error' => true,
            'message' => $result['error']['message'] ?? 'Failed to send invoice email',
            'code' => $result['error']['code'] ?? 'SEND_FAILED',
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
