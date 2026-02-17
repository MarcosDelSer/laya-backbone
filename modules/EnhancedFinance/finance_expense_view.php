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
 * Enhanced Finance Module - Expense View
 *
 * Displays expense detail including category, vendor information,
 * amounts breakdown, approval information, and receipt attachment.
 *
 * @package    Gibbon\Module\EnhancedFinance
 * @author     LAYA
 * @license    GPL-3.0
 */

use Gibbon\Services\Format;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Module\EnhancedFinance\Domain\ExpenseGateway;

// Access check
if (isActionAccessible($guid, $connection2, '/modules/EnhancedFinance/finance_expense_view.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $gibbonEnhancedFinanceExpenseID = $_GET['gibbonEnhancedFinanceExpenseID'] ?? '';
    $gibbonSchoolYearID = $_GET['gibbonSchoolYearID'] ?? $session->get('gibbonSchoolYearID');

    // Validate required parameter
    if (empty($gibbonEnhancedFinanceExpenseID)) {
        $page->addError(__('You have not specified one or more required parameters.'));
        return;
    }

    // Get gateways and settings
    $expenseGateway = $container->get(ExpenseGateway::class);
    $settingGateway = $container->get(SettingGateway::class);

    // Get expense details
    $expense = $expenseGateway->selectExpenseByID($gibbonEnhancedFinanceExpenseID);

    if (empty($expense)) {
        $page->addError(__('The selected expense could not be found.'));
        return;
    }

    // Breadcrumbs
    $page->breadcrumbs
        ->add(__('Enhanced Finance'), 'finance.php')
        ->add(__('Manage Expenses'), 'finance_expenses.php')
        ->add(__('View Expense'));

    // Get settings
    $currency = $settingGateway->getSettingByScope('System', 'currency') ?: 'CAD';
    $gstRate = $settingGateway->getSettingByScope('Enhanced Finance', 'gstRate') ?: '0.05';
    $qstRate = $settingGateway->getSettingByScope('Enhanced Finance', 'qstRate') ?: '0.09975';

    // Determine status display
    $statusDisplay = $expense['status'];
    $statusClass = '';
    switch ($expense['status']) {
        case 'Pending':
            $statusClass = 'bg-orange-100 text-orange-800 border-orange-200';
            break;
        case 'Approved':
            $statusClass = 'bg-blue-100 text-blue-800 border-blue-200';
            break;
        case 'Paid':
            $statusClass = 'bg-green-100 text-green-800 border-green-200';
            break;
        case 'Rejected':
            $statusClass = 'bg-red-100 text-red-800 border-red-200';
            break;
        default:
            $statusClass = 'bg-gray-100 text-gray-600 border-gray-200';
    }

    // Action buttons
    echo '<div class="linkTop flex justify-between items-center mb-4">';
    echo '<div class="flex gap-2">';

    // Back to list
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/EnhancedFinance/finance_expenses.php&gibbonSchoolYearID=' . $gibbonSchoolYearID . '" class="button">';
    echo '<span class="inline-flex items-center">&larr; ' . __('Back to Expenses') . '</span>';
    echo '</a>';

    // Edit button (if not rejected)
    if ($expense['status'] != 'Rejected') {
        echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/EnhancedFinance/finance_expense_edit.php&gibbonEnhancedFinanceExpenseID=' . $gibbonEnhancedFinanceExpenseID . '&gibbonSchoolYearID=' . $gibbonSchoolYearID . '" class="button">';
        echo '<span class="inline-flex items-center">' . __('Edit Expense') . '</span>';
        echo '</a>';
    }

    // Approve button (if pending)
    if ($expense['status'] == 'Pending') {
        echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/EnhancedFinance/finance_expense_approve.php&gibbonEnhancedFinanceExpenseID=' . $gibbonEnhancedFinanceExpenseID . '&gibbonSchoolYearID=' . $gibbonSchoolYearID . '" class="button">';
        echo '<span class="inline-flex items-center">' . __('Approve Expense') . '</span>';
        echo '</a>';
    }

    echo '</div>';
    echo '</div>';

    // Expense Header Section
    echo '<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">';

    // Expense Information Card
    echo '<div class="bg-white border rounded-lg shadow-sm p-5">';
    echo '<h3 class="text-lg font-semibold mb-4 border-b pb-2">' . __('Expense Information') . '</h3>';

    echo '<div class="grid grid-cols-2 gap-4">';

    // Expense ID
    echo '<div>';
    echo '<span class="text-sm text-gray-500">' . __('Expense ID') . '</span>';
    echo '<div class="font-semibold text-lg">#' . htmlspecialchars($gibbonEnhancedFinanceExpenseID) . '</div>';
    echo '</div>';

    // Status
    echo '<div>';
    echo '<span class="text-sm text-gray-500">' . __('Status') . '</span>';
    echo '<div><span class="inline-block px-3 py-1 rounded-full text-sm font-medium border ' . $statusClass . '">' . __($statusDisplay) . '</span></div>';
    echo '</div>';

    // Expense Date
    echo '<div>';
    echo '<span class="text-sm text-gray-500">' . __('Expense Date') . '</span>';
    echo '<div class="font-medium">' . Format::date($expense['expenseDate']) . '</div>';
    echo '</div>';

    // School Year
    echo '<div>';
    echo '<span class="text-sm text-gray-500">' . __('School Year') . '</span>';
    echo '<div class="font-medium">' . htmlspecialchars($expense['schoolYearName'] ?? '') . '</div>';
    echo '</div>';

    // Created By
    echo '<div>';
    echo '<span class="text-sm text-gray-500">' . __('Created By') . '</span>';
    echo '<div class="font-medium">' . Format::name('', $expense['createdByPreferredName'], $expense['createdBySurname'], 'Staff', false) . '</div>';
    echo '</div>';

    // Payment Method
    echo '<div>';
    echo '<span class="text-sm text-gray-500">' . __('Payment Method') . '</span>';
    $methodLabels = [
        'Cash'       => __('Cash'),
        'Cheque'     => __('Cheque'),
        'ETransfer'  => __('E-Transfer'),
        'CreditCard' => __('Credit Card'),
        'DebitCard'  => __('Debit Card'),
        'Other'      => __('Other'),
    ];
    echo '<div class="font-medium">' . ($methodLabels[$expense['paymentMethod']] ?? $expense['paymentMethod']) . '</div>';
    echo '</div>';

    echo '</div>'; // End grid
    echo '</div>'; // End Expense Information Card

    // Category & Vendor Information Card
    echo '<div class="bg-white border rounded-lg shadow-sm p-5">';
    echo '<h3 class="text-lg font-semibold mb-4 border-b pb-2">' . __('Category & Vendor') . '</h3>';

    echo '<div class="space-y-4">';

    // Category
    echo '<div>';
    echo '<span class="text-sm text-gray-500">' . __('Category') . '</span>';
    echo '<div class="font-semibold text-lg">' . htmlspecialchars($expense['categoryName']) . '</div>';
    if (!empty($expense['categoryAccountCode'])) {
        echo '<span class="text-sm text-gray-500">' . __('Account Code') . ': ' . htmlspecialchars($expense['categoryAccountCode']) . '</span>';
    }
    echo '</div>';

    // Vendor
    echo '<div>';
    echo '<span class="text-sm text-gray-500">' . __('Vendor') . '</span>';
    echo '<div class="font-medium">' . (!empty($expense['vendor']) ? htmlspecialchars($expense['vendor']) : '<span class="text-gray-400">' . __('Not specified') . '</span>') . '</div>';
    echo '</div>';

    // Reference
    echo '<div>';
    echo '<span class="text-sm text-gray-500">' . __('Reference') . '</span>';
    echo '<div class="font-medium">' . (!empty($expense['reference']) ? htmlspecialchars($expense['reference']) : '<span class="text-gray-400">' . __('Not specified') . '</span>') . '</div>';
    echo '</div>';

    // View category expenses link
    echo '<div class="pt-2">';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/EnhancedFinance/finance_expenses.php&gibbonEnhancedFinanceExpenseCategoryID=' . $expense['gibbonEnhancedFinanceExpenseCategoryID'] . '&gibbonSchoolYearID=' . $gibbonSchoolYearID . '" class="text-sm text-blue-600 hover:underline">';
    echo __('View all expenses in this category') . ' &rarr;';
    echo '</a>';
    echo '</div>';

    echo '</div>'; // End space-y-4
    echo '</div>'; // End Category & Vendor Card

    echo '</div>'; // End grid

    // Amounts Section
    echo '<div class="bg-white border rounded-lg shadow-sm p-5 mb-6">';
    echo '<h3 class="text-lg font-semibold mb-4 border-b pb-2">' . __('Amount Details') . ' <small class="text-gray-500">(' . $currency . ')</small></h3>';

    echo '<div class="grid grid-cols-2 md:grid-cols-3 gap-4">';

    // Amount (before tax)
    echo '<div class="text-center p-4 bg-gray-50 rounded-lg">';
    echo '<div class="text-sm text-gray-500 mb-1">' . __('Amount') . '</div>';
    echo '<div class="text-xl font-semibold">' . Format::currency($expense['amount']) . '</div>';
    echo '</div>';

    // Tax Amount
    echo '<div class="text-center p-4 bg-gray-50 rounded-lg">';
    echo '<div class="text-sm text-gray-500 mb-1">' . __('Tax') . '</div>';
    echo '<div class="text-xl font-semibold">' . Format::currency($expense['taxAmount']) . '</div>';
    if ((float)$expense['taxAmount'] > 0 && (float)$expense['amount'] > 0) {
        $taxPercent = ((float)$expense['taxAmount'] / (float)$expense['amount']) * 100;
        echo '<div class="text-xs text-gray-400">(' . number_format($taxPercent, 2) . '%)</div>';
    }
    echo '</div>';

    // Total Amount
    echo '<div class="text-center p-4 bg-blue-50 rounded-lg">';
    echo '<div class="text-sm text-blue-600 mb-1">' . __('Total') . '</div>';
    echo '<div class="text-xl font-bold text-blue-700">' . Format::currency($expense['totalAmount']) . '</div>';
    echo '</div>';

    echo '</div>'; // End grid
    echo '</div>'; // End Amounts Section

    // Description Section (if any)
    if (!empty($expense['description'])) {
        echo '<div class="bg-white border rounded-lg shadow-sm p-5 mb-6">';
        echo '<h3 class="text-lg font-semibold mb-4 border-b pb-2">' . __('Description') . '</h3>';
        echo '<div class="text-gray-700 whitespace-pre-wrap">' . htmlspecialchars($expense['description']) . '</div>';
        echo '</div>';
    }

    // Receipt Section
    echo '<div class="bg-white border rounded-lg shadow-sm p-5 mb-6">';
    echo '<h3 class="text-lg font-semibold mb-4 border-b pb-2">' . __('Receipt') . '</h3>';

    if (!empty($expense['receiptPath'])) {
        $receiptURL = $session->get('absoluteURL') . '/' . $expense['receiptPath'];
        $fileExtension = strtolower(pathinfo($expense['receiptPath'], PATHINFO_EXTENSION));
        $isImage = in_array($fileExtension, ['jpg', 'jpeg', 'png', 'gif', 'webp']);

        echo '<div class="space-y-4">';

        // Preview image if applicable
        if ($isImage) {
            echo '<div class="border rounded-lg p-2 bg-gray-50">';
            echo '<img src="' . htmlspecialchars($receiptURL) . '" alt="' . __('Receipt') . '" class="max-w-full max-h-96 mx-auto">';
            echo '</div>';
        }

        // Download link
        echo '<div class="flex gap-2">';
        echo '<a href="' . htmlspecialchars($receiptURL) . '" target="_blank" class="button">';
        echo '<span class="inline-flex items-center">' . __('View Receipt') . '</span>';
        echo '</a>';
        echo '<a href="' . htmlspecialchars($receiptURL) . '" download class="button">';
        echo '<span class="inline-flex items-center">' . __('Download Receipt') . '</span>';
        echo '</a>';
        echo '</div>';

        echo '</div>';
    } else {
        echo '<div class="text-center py-8 text-gray-500">';
        echo '<p>' . __('No receipt has been attached to this expense.') . '</p>';
        if ($expense['status'] != 'Rejected') {
            echo '<p class="mt-2"><a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/EnhancedFinance/finance_expense_edit.php&gibbonEnhancedFinanceExpenseID=' . $gibbonEnhancedFinanceExpenseID . '&gibbonSchoolYearID=' . $gibbonSchoolYearID . '" class="text-blue-600 hover:underline">' . __('Edit this expense to add a receipt') . '</a></p>';
        }
        echo '</div>';
    }

    echo '</div>'; // End Receipt Section

    // Approval Information (if approved or rejected)
    if (in_array($expense['status'], ['Approved', 'Rejected', 'Paid']) && !empty($expense['approvedByID'])) {
        echo '<div class="bg-white border rounded-lg shadow-sm p-5 mb-6">';
        echo '<h3 class="text-lg font-semibold mb-4 border-b pb-2">' . __('Approval Information') . '</h3>';

        echo '<div class="grid grid-cols-2 gap-4">';

        // Approved/Rejected By
        $actionLabel = $expense['status'] == 'Rejected' ? __('Rejected By') : __('Approved By');
        echo '<div>';
        echo '<span class="text-sm text-gray-500">' . $actionLabel . '</span>';
        echo '<div class="font-medium">' . Format::name('', $expense['approvedByPreferredName'], $expense['approvedBySurname'], 'Staff', false) . '</div>';
        echo '</div>';

        // Approval Date
        if (!empty($expense['approvedAt'])) {
            $dateLabel = $expense['status'] == 'Rejected' ? __('Rejected Date') : __('Approved Date');
            echo '<div>';
            echo '<span class="text-sm text-gray-500">' . $dateLabel . '</span>';
            echo '<div class="font-medium">' . Format::dateTime($expense['approvedAt']) . '</div>';
            echo '</div>';
        }

        echo '</div>'; // End grid
        echo '</div>'; // End Approval Information
    }

    // Audit Information
    echo '<div class="bg-gray-50 border rounded-lg p-4 text-sm text-gray-600">';
    echo '<div class="flex justify-between">';
    echo '<div>';
    echo '<strong>' . __('Created') . ':</strong> ' . Format::dateTime($expense['timestampCreated']);
    if (!empty($expense['timestampModified']) && $expense['timestampModified'] != $expense['timestampCreated']) {
        echo ' | <strong>' . __('Last Modified') . ':</strong> ' . Format::dateTime($expense['timestampModified']);
    }
    echo '</div>';
    echo '<div>';
    echo '<strong>' . __('Expense ID') . ':</strong> ' . $gibbonEnhancedFinanceExpenseID;
    echo '</div>';
    echo '</div>';
    echo '</div>';
}
