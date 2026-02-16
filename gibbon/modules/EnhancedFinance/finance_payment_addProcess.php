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
 * Enhanced Finance Module - Payment Add Process
 *
 * Process handler for recording new payments against invoices.
 * Validates input, inserts payment record, and updates invoice paid amount.
 *
 * @package    Gibbon\Module\EnhancedFinance
 * @author     LAYA
 * @license    GPL-3.0
 */

use Gibbon\Services\Format;
use Gibbon\Module\EnhancedFinance\Domain\InvoiceGateway;
use Gibbon\Module\EnhancedFinance\Domain\PaymentGateway;

// Module setup
$_POST['address'] = '/modules/EnhancedFinance/finance_payment_add.php';

// Get redirect URL
$URL = $session->get('absoluteURL') . '/index.php?q=/modules/EnhancedFinance/finance_payment_add.php';

// Access check
if (isActionAccessible($guid, $connection2, '/modules/EnhancedFinance/finance_payment_add.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
}

// Get posted data
$gibbonSchoolYearID = $_POST['gibbonSchoolYearID'] ?? '';
$gibbonEnhancedFinanceInvoiceID = $_POST['gibbonEnhancedFinanceInvoiceID'] ?? '';
$paymentDate = $_POST['paymentDate'] ?? '';
$amount = $_POST['amount'] ?? '';
$method = $_POST['method'] ?? 'Cash';
$reference = $_POST['reference'] ?? '';
$notes = $_POST['notes'] ?? '';

// Validate required fields
if (empty($gibbonEnhancedFinanceInvoiceID) || empty($paymentDate) || empty($amount)) {
    $URL .= '&return=error3';
    header("Location: {$URL}");
    exit;
}

// Convert date format
$paymentDate = Format::dateConvert($paymentDate);

// Validate amount
$amount = floatval(str_replace(['$', ',', ' '], '', $amount));
if ($amount <= 0) {
    $URL .= '&return=error4';
    header("Location: {$URL}");
    exit;
}

// Get gateways
$invoiceGateway = $container->get(InvoiceGateway::class);
$paymentGateway = $container->get(PaymentGateway::class);

// Verify invoice exists
$invoice = $invoiceGateway->selectInvoiceByID($gibbonEnhancedFinanceInvoiceID);
if (empty($invoice)) {
    $URL .= '&return=error2';
    header("Location: {$URL}");
    exit;
}

// Check if payment exceeds balance
$balanceRemaining = (float)$invoice['totalAmount'] - (float)$invoice['paidAmount'];
if ($amount > $balanceRemaining) {
    $URL .= '&return=error5';
    header("Location: {$URL}");
    exit;
}

// Get current user ID
$recordedByID = $session->get('gibbonPersonID');

// Prepare payment data
$paymentData = [
    'gibbonEnhancedFinanceInvoiceID' => $gibbonEnhancedFinanceInvoiceID,
    'paymentDate' => $paymentDate,
    'amount' => $amount,
    'method' => $method,
    'reference' => $reference,
    'notes' => $notes,
    'recordedByID' => $recordedByID,
];

// Insert payment
$paymentID = $paymentGateway->insertPayment($paymentData);

if ($paymentID === false) {
    $URL .= '&return=error1';
    header("Location: {$URL}");
    exit;
}

// Update invoice paid amount and status
$invoiceGateway->updatePaidAmount($gibbonEnhancedFinanceInvoiceID);

// Redirect with success
$URL .= '&return=success1';
header("Location: {$URL}");
exit;
