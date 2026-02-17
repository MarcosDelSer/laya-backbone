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
use Gibbon\Module\EnhancedFinance\Domain\InvoiceGateway;

// Include core (this file is called directly, not through module framework)
include '../../gibbon.php';

// Module includes
require_once __DIR__ . '/moduleFunctions.php';

// Access check - require user to be logged in
if (!$session->has('gibbonPersonID')) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode([
        'error' => true,
        'message' => 'You must be logged in to access this page',
    ]);
    exit;
}

// Check if user has access to this module action
if (isActionAccessible($guid, $connection2, '/modules/EnhancedFinance/invoice_email.php') == false) {
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
        // Fetch canonical invoice data from database using $invoiceID
        $invoiceGateway = $container->get(InvoiceGateway::class);
        $invoice = $invoiceGateway->selectInvoiceByID($invoiceID);

        if (empty($invoice)) {
            throw new Exception('Invoice not found with ID: ' . $invoiceID);
        }

        // Get settings for payment terms
        $settingGateway = $container->get('Gibbon\Domain\System\SettingGateway');
        $paymentTermsDays = $settingGateway->getSettingByScope('Enhanced Finance', 'defaultPaymentTermsDays') ?: '30';

        // Format child name for invoice
        $childName = trim(($invoice['childPreferredName'] ?? '') . ' ' . ($invoice['childSurname'] ?? ''));
        $period = date('F Y', strtotime($invoice['invoiceDate']));

        // Build invoice data structure for PDF generation
        $invoiceData = [
            'invoiceNumber' => $invoice['invoiceNumber'],
            'invoiceDate' => $invoice['invoiceDate'],
            'dueDate' => $invoice['dueDate'],
            'period' => $period,
            'customerName' => $recipientName ?: ($invoice['familyName'] ?? 'Valued Customer'),
            'customerEmail' => $recipientEmail,
            'customerAddress' => '', // Address not stored in invoice table
            'items' => [
                [
                    'description' => "Invoice for {$childName} - {$period}",
                    'quantity' => 1,
                    'unitPrice' => (float)$invoice['subtotal'],
                ],
            ],
            'subtotal' => (float)$invoice['subtotal'],
            'taxAmount' => (float)$invoice['taxAmount'],
            'totalAmount' => (float)$invoice['totalAmount'],
            'paidAmount' => (float)$invoice['paidAmount'],
            'balanceRemaining' => (float)$invoice['balanceRemaining'],
            'notes' => $invoice['notes'] ?? '',
            'paymentTerms' => "Net {$paymentTermsDays} days",
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

    // Get required dependencies from container (container is available via bootstrap)
    $mailer = $container->get('Gibbon\Contracts\Comms\Mailer');
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
            $session->get('gibbonPersonID')
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
