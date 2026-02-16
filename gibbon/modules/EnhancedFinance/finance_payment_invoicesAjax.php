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
 * Enhanced Finance Module - Payment Invoices AJAX
 *
 * AJAX endpoint to fetch outstanding invoices for a family.
 * Returns JSON array of invoices with balance remaining.
 *
 * @package    Gibbon\Module\EnhancedFinance
 * @author     LAYA
 * @license    GPL-3.0
 */

use Gibbon\Services\Format;
use Gibbon\Module\EnhancedFinance\Domain\InvoiceGateway;

// Set JSON content type
header('Content-Type: application/json');

// Gibbon framework check
if (!isset($gibbon) || !isset($container)) {
    echo json_encode(['error' => 'System error']);
    exit;
}

// Get parameters
$gibbonFamilyID = $_GET['gibbonFamilyID'] ?? '';
$gibbonSchoolYearID = $_GET['gibbonSchoolYearID'] ?? '';

// Validate parameters
if (empty($gibbonFamilyID)) {
    echo json_encode([]);
    exit;
}

// Get gateway
$invoiceGateway = $container->get(InvoiceGateway::class);

// Fetch outstanding invoices for this family
$invoices = $invoiceGateway->selectOutstandingByFamily($gibbonFamilyID);

$output = [];

foreach ($invoices as $invoice) {
    $balance = (float)$invoice['totalAmount'] - (float)$invoice['paidAmount'];

    // Only include invoices with remaining balance
    if ($balance > 0) {
        $output[] = [
            'gibbonEnhancedFinanceInvoiceID' => $invoice['gibbonEnhancedFinanceInvoiceID'],
            'invoiceNumber' => $invoice['invoiceNumber'],
            'childName' => Format::name('', $invoice['childPreferredName'], $invoice['childSurname'], 'Student', false),
            'totalAmount' => (float)$invoice['totalAmount'],
            'paidAmount' => (float)$invoice['paidAmount'],
            'balance' => $balance,
            'balanceFormatted' => Format::currency($balance),
            'dueDate' => Format::date($invoice['dueDate']),
        ];
    }
}

echo json_encode($output);
