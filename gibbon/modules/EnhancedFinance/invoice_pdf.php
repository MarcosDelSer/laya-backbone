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

use Gibbon\Module\EnhancedFinance\Domain\InvoicePDFGenerator;

// Include core (this file is called directly, not through module framework)
include '../../gibbon.php';

// Access check - require user to be logged in
if (!$session->has('gibbonPersonID')) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['error' => 'Access denied. Please log in.']);
    exit;
}

// Check if user has access to this module action
if (isActionAccessible($guid, $connection2, '/modules/EnhancedFinance/invoice_pdf.php') == false) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['error' => 'You do not have access to this action.']);
    exit;
}

try {
    // Get invoice ID from request
    $invoiceID = $_GET['invoiceID'] ?? $_POST['invoiceID'] ?? '';

    if (empty($invoiceID)) {
        throw new \Exception('Invoice ID is required');
    }

    // Get the PDF generator service from container
    $pdfGenerator = $container->get(InvoicePDFGenerator::class);

    // TODO: In a real implementation, fetch invoice data from database
    // For now, this endpoint expects invoice data to be passed or demonstrates structure

    // Check if invoice data is provided in POST (for testing/demo)
    $invoiceDataJson = $_POST['invoiceData'] ?? null;

    if ($invoiceDataJson) {
        // Decode JSON invoice data
        $invoiceData = json_decode($invoiceDataJson, true);

        if (!$invoiceData) {
            throw new \Exception('Invalid invoice data format');
        }
    } else {
        // In production, this would fetch from database using the invoiceID
        // Example structure for demonstration:
        $invoiceData = [
            'invoiceNumber' => $invoiceID,
            'invoiceDate' => date('Y-m-d'),
            'dueDate' => date('Y-m-d', strtotime('+30 days')),
            'period' => date('F Y'),
            'customerName' => 'Sample Customer',
            'customerAddress' => "123 Main Street\nMontreal, QC\nH3A 1A1",
            'customerEmail' => 'customer@example.com',
            'customerPhone' => '(514) 123-4567',
            'items' => [
                [
                    'description' => 'Basic Daycare Services',
                    'quantity' => 20,
                    'unitPrice' => 45.00,
                ],
                [
                    'description' => 'Meal Service',
                    'quantity' => 20,
                    'unitPrice' => 12.50,
                ],
                [
                    'description' => 'Activity Supplements',
                    'quantity' => 1,
                    'unitPrice' => 50.00,
                ],
            ],
            'paymentTerms' => 'Payment is due within 30 days of invoice date. Late payments may incur a 2% monthly interest charge.',
            'paymentMethods' => "• Bank Transfer\n• Credit Card\n• Cheque\n• Cash",
            'notes' => 'Thank you for choosing our daycare services. Please contact us if you have any questions about this invoice.',
        ];
    }

    // Validate invoice data structure
    if (empty($invoiceData['invoiceNumber'])) {
        throw new \Exception('Invoice number is required');
    }

    if (empty($invoiceData['customerName'])) {
        throw new \Exception('Customer name is required');
    }

    if (empty($invoiceData['items']) || !is_array($invoiceData['items'])) {
        throw new \Exception('Invoice must contain at least one item');
    }

    // Generate and output PDF
    // Output mode 'D' = Download (force download dialog)
    // Output mode 'I' = Inline (display in browser)
    $outputMode = $_GET['mode'] ?? 'D';

    if (!in_array($outputMode, ['D', 'I'])) {
        $outputMode = 'D';
    }

    $filename = "invoice_{$invoiceData['invoiceNumber']}.pdf";

    // Generate PDF and output directly to browser
    // The generate() method will set appropriate headers and output the PDF
    $pdfGenerator->generate($invoiceData, $outputMode, $filename);

    // No further output needed - PDF has been sent
    exit;

} catch (\Exception $e) {
    // Log error
    error_log('Invoice PDF Generation Error: ' . $e->getMessage());

    // Send error response
    header('HTTP/1.1 500 Internal Server Error');
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Failed to generate invoice PDF',
        'message' => $e->getMessage(),
    ]);
    exit;
}
