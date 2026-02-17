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
 * Enhanced Finance Module - Invoice Add Process
 *
 * Process handler for creating new invoices.
 * Validates input, calculates tax amounts, and inserts invoice record.
 *
 * @package    Gibbon\Module\EnhancedFinance
 * @author     LAYA
 * @license    GPL-3.0
 */

use Gibbon\Services\Format;
use Gibbon\Module\EnhancedFinance\Domain\InvoiceGateway;

// Module setup
$_POST['address'] = '/modules/EnhancedFinance/finance_invoice_add.php';

// Get redirect URL
$URL = $session->get('absoluteURL') . '/index.php?q=/modules/EnhancedFinance/finance_invoices.php';

// Access check
if (isActionAccessible($guid, $connection2, '/modules/EnhancedFinance/finance_invoice_add.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
}

// CSRF check
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $session->get('csrf_token')) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
}

// Get posted data
$gibbonSchoolYearID = $_POST['gibbonSchoolYearID'] ?? '';
$gibbonFamilyID = $_POST['gibbonFamilyID'] ?? '';
$gibbonPersonID = $_POST['gibbonPersonID'] ?? '';
$invoiceNumber = $_POST['invoiceNumber'] ?? '';
$invoiceDate = $_POST['invoiceDate'] ?? '';
$dueDate = $_POST['dueDate'] ?? '';
$status = $_POST['status'] ?? 'Pending';
$subtotal = $_POST['subtotal'] ?? '';
$applyTax = isset($_POST['applyTax']) && $_POST['applyTax'] === 'on';
$gstRate = floatval($_POST['gstRate'] ?? 0.05);
$qstRate = floatval($_POST['qstRate'] ?? 0.09975);
$notes = $_POST['notes'] ?? '';

// Validate required fields
if (empty($gibbonSchoolYearID) || empty($gibbonFamilyID) || empty($gibbonPersonID) ||
    empty($invoiceNumber) || empty($invoiceDate) || empty($dueDate) || empty($subtotal)) {
    $URL .= '&return=error1';
    header("Location: {$URL}");
    exit;
}

// Convert date formats
$invoiceDate = Format::dateConvert($invoiceDate);
$dueDate = Format::dateConvert($dueDate);

// Validate dates
if ($invoiceDate === false || $dueDate === false) {
    $URL .= '&return=error1';
    header("Location: {$URL}");
    exit;
}

// Validate subtotal
$subtotal = floatval(str_replace(['$', ',', ' '], '', $subtotal));
if ($subtotal < 0) {
    $URL .= '&return=error1';
    header("Location: {$URL}");
    exit;
}

// Calculate tax amounts
$taxAmount = 0;
if ($applyTax && $subtotal > 0) {
    $combinedTaxRate = $gstRate + $qstRate;
    $taxAmount = round($subtotal * $combinedTaxRate, 2);
}

// Calculate total
$totalAmount = $subtotal + $taxAmount;

// Get current user ID
$createdByID = $session->get('gibbonPersonID');

// Get gateway
$invoiceGateway = $container->get(InvoiceGateway::class);

// Prepare invoice data
$invoiceData = [
    'gibbonPersonID' => $gibbonPersonID,
    'gibbonFamilyID' => $gibbonFamilyID,
    'gibbonSchoolYearID' => $gibbonSchoolYearID,
    'invoiceNumber' => $invoiceNumber,
    'invoiceDate' => $invoiceDate,
    'dueDate' => $dueDate,
    'subtotal' => $subtotal,
    'taxAmount' => $taxAmount,
    'totalAmount' => $totalAmount,
    'paidAmount' => 0.00,
    'status' => $status,
    'notes' => $notes,
    'createdByID' => $createdByID,
];

// Insert invoice
$invoiceID = $invoiceGateway->insert($invoiceData);

if ($invoiceID === false) {
    $URL .= '&return=error2';
    header("Location: {$URL}");
    exit;
}

// Redirect with success
$URL .= '&return=success0';
header("Location: {$URL}");
exit;
