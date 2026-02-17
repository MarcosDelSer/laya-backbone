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
 * Enhanced Finance Module - Expense Edit Process
 *
 * Processes the expense edit form submission and updates
 * the expense record in the database.
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
$gibbonEnhancedFinanceExpenseID = $_POST['gibbonEnhancedFinanceExpenseID'] ?? '';
$gibbonSchoolYearID = $_POST['gibbonSchoolYearID'] ?? '';

$URL = $session->get('absoluteURL') . '/index.php?q=/modules/EnhancedFinance/finance_expense_edit.php&gibbonEnhancedFinanceExpenseID=' . $gibbonEnhancedFinanceExpenseID . '&gibbonSchoolYearID=' . $gibbonSchoolYearID;

// Access check
if (isActionAccessible($guid, $connection2, '/modules/EnhancedFinance/finance_expense_edit.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
}

// Validate required expense ID
if (empty($gibbonEnhancedFinanceExpenseID)) {
    $URL .= '&return=error3';
    header("Location: {$URL}");
    exit;
}

// Get the expense gateway
$expenseGateway = $container->get(ExpenseGateway::class);

// Check if expense exists
$existingExpense = $expenseGateway->selectExpenseByID($gibbonEnhancedFinanceExpenseID);

if (empty($existingExpense)) {
    $URL .= '&return=error2';
    header("Location: {$URL}");
    exit;
}

// Check if expense can be edited (not rejected)
if ($existingExpense['status'] == 'Rejected') {
    $URL .= '&return=error1';
    header("Location: {$URL}");
    exit;
}

// Proceed!
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
$removeReceipt = isset($_POST['removeReceipt']) && $_POST['removeReceipt'] == 'on';

// Validate required fields
if (empty($gibbonEnhancedFinanceExpenseCategoryID) || empty($expenseDate)) {
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
    $status = $existingExpense['status']; // Keep current status if invalid
}

// Validate payment method
$validPaymentMethods = ['Cash', 'Cheque', 'ETransfer', 'CreditCard', 'DebitCard', 'Other'];
if (!in_array($paymentMethod, $validPaymentMethods)) {
    $paymentMethod = 'Other';
}

// Handle receipt file upload
$receiptPath = $existingExpense['receiptPath']; // Keep existing by default

// Remove receipt if requested
if ($removeReceipt) {
    // Delete old file if it exists
    if (!empty($receiptPath)) {
        $oldFilePath = $session->get('absolutePath') . '/' . $receiptPath;
        if (file_exists($oldFilePath)) {
            unlink($oldFilePath);
        }
    }
    $receiptPath = null;
}

// Handle new receipt upload
if (!empty($_FILES['receiptFile']['name']) && $_FILES['receiptFile']['error'] === UPLOAD_ERR_OK) {
    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
    $fileType = mime_content_type($_FILES['receiptFile']['tmp_name']);

    if (!in_array($fileType, $allowedTypes)) {
        $URL .= '&return=error3';
        header("Location: {$URL}");
        exit;
    }

    // Validate file size (max 5MB)
    $maxFileSize = 5 * 1024 * 1024;
    if ($_FILES['receiptFile']['size'] > $maxFileSize) {
        $URL .= '&return=error3';
        header("Location: {$URL}");
        exit;
    }

    // Delete old file if it exists
    if (!empty($receiptPath) && !$removeReceipt) {
        $oldFilePath = $session->get('absolutePath') . '/' . $receiptPath;
        if (file_exists($oldFilePath)) {
            unlink($oldFilePath);
        }
    }

    // Create upload directory if it doesn't exist
    $uploadDir = $session->get('absolutePath') . '/uploads/EnhancedFinance/expenses';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Generate unique filename
    $fileExtension = pathinfo($_FILES['receiptFile']['name'], PATHINFO_EXTENSION);
    $newFilename = 'expense_' . $gibbonEnhancedFinanceExpenseID . '_' . time() . '.' . $fileExtension;
    $uploadPath = $uploadDir . '/' . $newFilename;

    // Move uploaded file
    if (move_uploaded_file($_FILES['receiptFile']['tmp_name'], $uploadPath)) {
        $receiptPath = 'uploads/EnhancedFinance/expenses/' . $newFilename;
    }
}

// Prepare data for update
$data = [
    'gibbonEnhancedFinanceExpenseCategoryID' => $gibbonEnhancedFinanceExpenseCategoryID,
    'expenseDate'                            => $expenseDate,
    'amount'                                 => $amount,
    'taxAmount'                              => $taxAmount,
    'totalAmount'                            => $totalAmount,
    'vendor'                                 => trim($vendor),
    'reference'                              => trim($reference),
    'paymentMethod'                          => $paymentMethod,
    'description'                            => trim($description),
    'status'                                 => $status,
    'receiptPath'                            => $receiptPath,
];

// If status changed to Approved and wasn't approved before, set approval info
if ($status == 'Approved' && $existingExpense['status'] != 'Approved') {
    $data['approvedByID'] = $session->get('gibbonPersonID');
    $data['approvedAt'] = date('Y-m-d H:i:s');
}

// Update the expense
try {
    $updated = $expenseGateway->update($gibbonEnhancedFinanceExpenseID, $data);

    if ($updated === false) {
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
$URL .= '&return=success1';
header("Location: {$URL}");
exit;
