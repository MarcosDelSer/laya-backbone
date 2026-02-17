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
 * Enhanced Finance Module - Expense Add Process
 *
 * Processes the expense add form submission and creates
 * a new expense record in the database.
 *
 * @package    Gibbon\Module\EnhancedFinance
 * @author     LAYA
 * @license    GPL-3.0
 */

use Gibbon\Services\Format;
use Gibbon\Module\EnhancedFinance\Domain\ExpenseGateway;

// Gibbon setup
require_once '../../gibbon.php';

// Build URL for return
$URL = $session->get('absoluteURL') . '/index.php?q=/modules/EnhancedFinance/finance_expenses.php';

// Access check
if (isActionAccessible($guid, $connection2, '/modules/EnhancedFinance/finance_expense_add.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
}

// Proceed!
$gibbonSchoolYearID = $_POST['gibbonSchoolYearID'] ?? '';
$gibbonEnhancedFinanceExpenseCategoryID = $_POST['gibbonEnhancedFinanceExpenseCategoryID'] ?? '';
$expenseDate = $_POST['expenseDate'] ?? '';
$status = $_POST['status'] ?? 'Pending';
$vendor = $_POST['vendor'] ?? '';
$reference = $_POST['reference'] ?? '';
$paymentMethod = $_POST['paymentMethod'] ?? 'Other';
$amount = $_POST['amount'] ?? 0;
$applyTax = isset($_POST['applyTax']) && $_POST['applyTax'] == 'on';
$taxAmount = $_POST['taxAmount'] ?? 0;
$totalAmount = $_POST['totalAmount'] ?? 0;
$description = $_POST['description'] ?? '';

// Validate required fields
if (empty($gibbonSchoolYearID) || empty($gibbonEnhancedFinanceExpenseCategoryID) || empty($expenseDate)) {
    $URL .= '&return=error3';
    header("Location: {$URL}");
    exit;
}

// Validate amount
$amount = str_replace(['$', ',', ' '], '', $amount);
$amount = (float) $amount;

if ($amount <= 0) {
    $URL .= '&return=error3';
    header("Location: {$URL}");
    exit;
}

// Process tax amount
$taxAmount = str_replace(['$', ',', ' '], '', $taxAmount);
$taxAmount = (float) $taxAmount;

// If apply tax checkbox was checked, calculate tax from rates
if ($applyTax) {
    $gstRate = (float) ($_POST['gstRate'] ?? 0.05);
    $qstRate = (float) ($_POST['qstRate'] ?? 0.09975);
    $combinedTaxRate = $gstRate + $qstRate;
    $taxAmount = round($amount * $combinedTaxRate, 2);
}

// Calculate total
$totalAmount = $amount + $taxAmount;

// Format date for database (convert from display format to Y-m-d)
$expenseDate = Format::dateConvert($expenseDate);

// Validate date format
if (empty($expenseDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expenseDate)) {
    $URL .= '&return=error3';
    header("Location: {$URL}");
    exit;
}

// Validate status
$validStatuses = ['Pending', 'Approved', 'Paid', 'Rejected'];
if (!in_array($status, $validStatuses)) {
    $status = 'Pending';
}

// Validate payment method
$validPaymentMethods = ['Cash', 'Cheque', 'ETransfer', 'CreditCard', 'DebitCard', 'Other'];
if (!in_array($paymentMethod, $validPaymentMethods)) {
    $paymentMethod = 'Other';
}

// Get the expense gateway
$expenseGateway = $container->get(ExpenseGateway::class);

// Prepare data for insert
$data = [
    'gibbonEnhancedFinanceExpenseCategoryID' => $gibbonEnhancedFinanceExpenseCategoryID,
    'gibbonSchoolYearID'                     => $gibbonSchoolYearID,
    'expenseDate'                            => $expenseDate,
    'amount'                                 => $amount,
    'taxAmount'                              => $taxAmount,
    'totalAmount'                            => $totalAmount,
    'vendor'                                 => trim($vendor),
    'reference'                              => trim($reference),
    'paymentMethod'                          => $paymentMethod,
    'description'                            => trim($description),
    'status'                                 => $status,
    'createdByID'                            => $session->get('gibbonPersonID'),
];

// If status is Approved and user has permission, set approval info
if ($status == 'Approved') {
    $data['approvedByID'] = $session->get('gibbonPersonID');
    $data['approvedAt'] = date('Y-m-d H:i:s');
}

// Insert the expense
try {
    $gibbonEnhancedFinanceExpenseID = $expenseGateway->insertExpense($data);

    if ($gibbonEnhancedFinanceExpenseID === false) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }
} catch (Exception $e) {
    $URL .= '&return=error1';
    header("Location: {$URL}");
    exit;
}

// Success - redirect with success message
$URL .= '&return=success1&gibbonSchoolYearID=' . $gibbonSchoolYearID;
header("Location: {$URL}");
exit;
