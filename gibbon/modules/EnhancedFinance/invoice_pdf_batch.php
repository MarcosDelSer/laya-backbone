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
if (isActionAccessible($guid, $connection2, '/modules/EnhancedFinance/invoice_pdf_batch.php') == false) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['error' => 'You do not have access to this action.']);
    exit;
}

try {
    // Get the PDF generator service from container
    $pdfGenerator = $container->get(InvoicePDFGenerator::class);

    // Get month and year from request (default to current month)
    $month = $_GET['month'] ?? $_POST['month'] ?? date('m');
    $year = $_GET['year'] ?? $_POST['year'] ?? date('Y');

    // Validate month and year
    if (!is_numeric($month) || $month < 1 || $month > 12) {
        throw new \Exception('Invalid month. Must be between 1 and 12.');
    }

    if (!is_numeric($year) || $year < 2000 || $year > 2100) {
        throw new \Exception('Invalid year. Must be between 2000 and 2100.');
    }

    $period = date('F Y', mktime(0, 0, 0, $month, 1, $year));

    // TODO: In a real implementation, fetch all family invoices from database
    // This would typically query a billing table for all families with invoices in the given month
    // For now, check if custom invoice data is provided in POST (for testing/demo)

    $invoicesDataJson = $_POST['invoicesData'] ?? null;

    if ($invoicesDataJson) {
        // Decode JSON invoices data
        $invoicesData = json_decode($invoicesDataJson, true);

        if (!$invoicesData || !is_array($invoicesData)) {
            throw new \Exception('Invalid invoices data format. Expected JSON array.');
        }
    } else {
        // In production, this would fetch from database using the month and year
        // Example structure for demonstration with multiple families:
        $invoicesData = [
            [
                'invoiceNumber' => 'INV-' . $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-001',
                'invoiceDate' => date('Y-m-d', mktime(0, 0, 0, $month, 1, $year)),
                'dueDate' => date('Y-m-d', mktime(0, 0, 0, $month, 15, $year)),
                'period' => $period,
                'customerName' => 'Smith Family',
                'customerAddress' => "123 Maple Street\nMontreal, QC\nH3A 1A1",
                'customerEmail' => 'smith@example.com',
                'customerPhone' => '(514) 123-4567',
                'items' => [
                    [
                        'description' => 'Full-time Daycare Services',
                        'quantity' => 20,
                        'unitPrice' => 50.00,
                    ],
                    [
                        'description' => 'Meal Service',
                        'quantity' => 20,
                        'unitPrice' => 15.00,
                    ],
                ],
                'paymentTerms' => 'Payment is due within 15 days of invoice date. Late payments may incur a 2% monthly interest charge.',
                'paymentMethods' => "• Bank Transfer\n• Credit Card\n• Cheque\n• Cash",
                'notes' => 'Thank you for choosing our daycare services.',
            ],
            [
                'invoiceNumber' => 'INV-' . $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-002',
                'invoiceDate' => date('Y-m-d', mktime(0, 0, 0, $month, 1, $year)),
                'dueDate' => date('Y-m-d', mktime(0, 0, 0, $month, 15, $year)),
                'period' => $period,
                'customerName' => 'Johnson Family',
                'customerAddress' => "456 Oak Avenue\nMontreal, QC\nH3B 2B2",
                'customerEmail' => 'johnson@example.com',
                'customerPhone' => '(514) 234-5678',
                'items' => [
                    [
                        'description' => 'Part-time Daycare Services',
                        'quantity' => 12,
                        'unitPrice' => 50.00,
                    ],
                    [
                        'description' => 'Meal Service',
                        'quantity' => 12,
                        'unitPrice' => 15.00,
                    ],
                    [
                        'description' => 'Activity Supplements',
                        'quantity' => 1,
                        'unitPrice' => 75.00,
                    ],
                ],
                'paymentTerms' => 'Payment is due within 15 days of invoice date. Late payments may incur a 2% monthly interest charge.',
                'paymentMethods' => "• Bank Transfer\n• Credit Card\n• Cheque\n• Cash",
                'notes' => 'Thank you for choosing our daycare services.',
            ],
            [
                'invoiceNumber' => 'INV-' . $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-003',
                'invoiceDate' => date('Y-m-d', mktime(0, 0, 0, $month, 1, $year)),
                'dueDate' => date('Y-m-d', mktime(0, 0, 0, $month, 15, $year)),
                'period' => $period,
                'customerName' => 'Brown Family',
                'customerAddress' => "789 Pine Road\nMontreal, QC\nH3C 3C3",
                'customerEmail' => 'brown@example.com',
                'customerPhone' => '(514) 345-6789',
                'items' => [
                    [
                        'description' => 'Full-time Daycare Services',
                        'quantity' => 22,
                        'unitPrice' => 50.00,
                    ],
                    [
                        'description' => 'Meal Service',
                        'quantity' => 22,
                        'unitPrice' => 15.00,
                    ],
                    [
                        'description' => 'Extended Hours Supplement',
                        'quantity' => 10,
                        'unitPrice' => 8.00,
                    ],
                ],
                'paymentTerms' => 'Payment is due within 15 days of invoice date. Late payments may incur a 2% monthly interest charge.',
                'paymentMethods' => "• Bank Transfer\n• Credit Card\n• Cheque\n• Cash",
                'notes' => 'Thank you for choosing our daycare services.',
            ],
        ];
    }

    // Validate that we have invoices to process
    if (empty($invoicesData)) {
        throw new \Exception('No invoices found for the specified period.');
    }

    // Validate each invoice data structure
    foreach ($invoicesData as $index => $invoiceData) {
        if (empty($invoiceData['invoiceNumber'])) {
            throw new \Exception("Invoice #{$index}: Invoice number is required");
        }

        if (empty($invoiceData['customerName'])) {
            throw new \Exception("Invoice #{$index}: Customer name is required");
        }

        if (empty($invoiceData['items']) || !is_array($invoiceData['items'])) {
            throw new \Exception("Invoice #{$index}: Invoice must contain at least one item");
        }
    }

    // Generate batch PDF
    // Output mode 'D' = Download (force download dialog)
    $outputMode = $_GET['mode'] ?? 'D';

    if (!in_array($outputMode, ['D', 'I'])) {
        $outputMode = 'D';
    }

    $filename = "invoices_batch_{$year}-" . str_pad($month, 2, '0', STR_PAD_LEFT) . ".pdf";

    // Log batch generation
    error_log(sprintf(
        'Batch Invoice Generation: %d invoices for %s (User: %s)',
        count($invoicesData),
        $period,
        $session->get('gibbonPersonID')
    ));

    // Generate batch PDF and output directly to browser
    // The generateBatch() method will set appropriate headers and output the PDF
    $pdfGenerator->generateBatch($invoicesData, $outputMode, $filename);

    // No further output needed - PDF has been sent
    exit;

} catch (\Exception $e) {
    // Log error
    error_log('Batch Invoice PDF Generation Error: ' . $e->getMessage());

    // Send error response
    header('HTTP/1.1 500 Internal Server Error');
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Failed to generate batch invoice PDF',
        'message' => $e->getMessage(),
    ]);
    exit;
}
