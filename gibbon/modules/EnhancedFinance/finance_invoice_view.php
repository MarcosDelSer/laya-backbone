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
 * Enhanced Finance Module - Invoice View
 *
 * Displays invoice detail including header, child/family information,
 * amounts breakdown, payment history, and payment recording form.
 *
 * @package    Gibbon\Module\EnhancedFinance
 * @author     LAYA
 * @license    GPL-3.0
 */

use Gibbon\Forms\Form;
use Gibbon\Services\Format;
use Gibbon\Tables\DataTable;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Module\EnhancedFinance\Domain\InvoiceGateway;
use Gibbon\Module\EnhancedFinance\Domain\PaymentGateway;
use Gibbon\Module\EnhancedFinance\Service\InvoiceService;
use Gibbon\Module\EnhancedFinance\Service\PaymentService;
use Gibbon\Module\EnhancedFinance\Validator\InvoiceValidator;

// Access check
if (isActionAccessible($guid, $connection2, '/modules/EnhancedFinance/finance_invoice_view.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $gibbonEnhancedFinanceInvoiceID = $_GET['gibbonEnhancedFinanceInvoiceID'] ?? '';
    $gibbonSchoolYearID = $_GET['gibbonSchoolYearID'] ?? $session->get('gibbonSchoolYearID');

    // Validate required parameter
    if (empty($gibbonEnhancedFinanceInvoiceID)) {
        $page->addError(__('You have not specified one or more required parameters.'));
        return;
    }

    // Get gateways and services
    $invoiceGateway = $container->get(InvoiceGateway::class);
    $paymentGateway = $container->get(PaymentGateway::class);
    $settingGateway = $container->get(SettingGateway::class);

    // Initialize services
    $invoiceValidator = new InvoiceValidator();
    $invoiceService = new InvoiceService($settingGateway, $invoiceGateway, $invoiceValidator);
    $paymentService = new PaymentService($settingGateway, $paymentGateway, $invoiceGateway);

    // Get invoice details
    $invoice = $invoiceGateway->selectInvoiceByID($gibbonEnhancedFinanceInvoiceID);

    if (empty($invoice)) {
        $page->addError(__('The selected invoice could not be found.'));
        return;
    }

    // Breadcrumbs
    $page->breadcrumbs
        ->add(__('Enhanced Finance'), 'finance.php')
        ->add(__('Manage Invoices'), 'finance_invoices.php')
        ->add(__('View Invoice'));

    // Return messages for payment recording
    $page->return->addReturns([
        'success0' => __('Your request was completed successfully.'),
        'success1' => __('Payment recorded successfully.'),
        'error1' => __('There was an error recording the payment.'),
        'error2' => __('Required parameters were not provided.'),
        'error3' => __('Invalid payment amount.'),
    ]);

    // Get settings
    $currency = $settingGateway->getSettingByScope('System', 'currency') ?: 'CAD';

    // Get tax rates from service
    $taxRates = $invoiceService->getTaxRates();
    $gstRate = $taxRates['gst'];
    $qstRate = $taxRates['qst'];

    // Calculate amounts using service
    $balanceRemaining = (float)$invoice['balanceRemaining'];
    $isOverdue = $invoiceService->isOverdue($invoice['dueDate'], $invoice['status']);
    $canRecordPayment = in_array($invoice['status'], ['Issued', 'Partial']);

    // Determine status display
    $statusDisplay = $invoice['status'];
    $statusClass = '';
    if ($isOverdue) {
        $statusDisplay = __('Overdue');
        $statusClass = 'bg-red-100 text-red-800 border-red-200';
    } elseif ($invoice['status'] == 'Paid') {
        $statusClass = 'bg-green-100 text-green-800 border-green-200';
    } elseif ($invoice['status'] == 'Partial') {
        $statusClass = 'bg-orange-100 text-orange-800 border-orange-200';
    } elseif (in_array($invoice['status'], ['Cancelled', 'Refunded'])) {
        $statusClass = 'bg-gray-100 text-gray-600 border-gray-200';
    } else {
        $statusClass = 'bg-blue-100 text-blue-800 border-blue-200';
    }

    // Action buttons
    echo '<div class="linkTop flex justify-between items-center mb-4">';
    echo '<div class="flex gap-2">';

    // Back to list
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/EnhancedFinance/finance_invoices.php&gibbonSchoolYearID=' . $gibbonSchoolYearID . '" class="button">';
    echo '<span class="inline-flex items-center">&larr; ' . __('Back to Invoices') . '</span>';
    echo '</a>';

    // Edit button (if not cancelled/refunded)
    if (!in_array($invoice['status'], ['Cancelled', 'Refunded'])) {
        echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/EnhancedFinance/finance_invoice_edit.php&gibbonEnhancedFinanceInvoiceID=' . $gibbonEnhancedFinanceInvoiceID . '&gibbonSchoolYearID=' . $gibbonSchoolYearID . '" class="button">';
        echo '<span class="inline-flex items-center">' . __('Edit Invoice') . '</span>';
        echo '</a>';
    }

    // Print button (if not pending/cancelled)
    if (!in_array($invoice['status'], ['Pending', 'Cancelled'])) {
        echo '<a href="' . $session->get('absoluteURL') . '/modules/EnhancedFinance/finance_invoice_print.php?gibbonEnhancedFinanceInvoiceID=' . $gibbonEnhancedFinanceInvoiceID . '&type=invoice" target="_blank" class="button">';
        echo '<span class="inline-flex items-center">' . __('Print Invoice') . '</span>';
        echo '</a>';
    }

    echo '</div>';
    echo '</div>';

    // Invoice Header Section
    echo '<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">';

    // Invoice Information Card
    echo '<div class="bg-white border rounded-lg shadow-sm p-5">';
    echo '<h3 class="text-lg font-semibold mb-4 border-b pb-2">' . __('Invoice Information') . '</h3>';

    echo '<div class="grid grid-cols-2 gap-4">';

    // Invoice Number
    echo '<div>';
    echo '<span class="text-sm text-gray-500">' . __('Invoice Number') . '</span>';
    echo '<div class="font-semibold text-lg">' . htmlspecialchars($invoice['invoiceNumber']) . '</div>';
    echo '</div>';

    // Status
    echo '<div>';
    echo '<span class="text-sm text-gray-500">' . __('Status') . '</span>';
    echo '<div><span class="inline-block px-3 py-1 rounded-full text-sm font-medium border ' . $statusClass . '">' . __($statusDisplay) . '</span></div>';
    echo '</div>';

    // Invoice Date
    echo '<div>';
    echo '<span class="text-sm text-gray-500">' . __('Invoice Date') . '</span>';
    echo '<div class="font-medium">' . Format::date($invoice['invoiceDate']) . '</div>';
    echo '</div>';

    // Due Date
    echo '<div>';
    echo '<span class="text-sm text-gray-500">' . __('Due Date') . '</span>';
    $dueDateClass = $isOverdue ? 'text-red-600 font-semibold' : 'font-medium';
    echo '<div class="' . $dueDateClass . '">' . Format::date($invoice['dueDate']) . '</div>';
    if ($isOverdue) {
        // Calculate days overdue using service
        $daysOverdue = $invoiceService->getDaysOverdue($invoice['dueDate']);
        echo '<span class="text-xs text-red-500">' . sprintf(__('%d days overdue'), $daysOverdue) . '</span>';
    }
    echo '</div>';

    // School Year
    echo '<div>';
    echo '<span class="text-sm text-gray-500">' . __('School Year') . '</span>';
    echo '<div class="font-medium">' . htmlspecialchars($invoice['schoolYearName'] ?? '') . '</div>';
    echo '</div>';

    // Created By
    echo '<div>';
    echo '<span class="text-sm text-gray-500">' . __('Created By') . '</span>';
    echo '<div class="font-medium">' . Format::name('', $invoice['createdByPreferredName'], $invoice['createdBySurname'], 'Staff', false) . '</div>';
    echo '</div>';

    echo '</div>'; // End grid
    echo '</div>'; // End Invoice Information Card

    // Child & Family Information Card
    echo '<div class="bg-white border rounded-lg shadow-sm p-5">';
    echo '<h3 class="text-lg font-semibold mb-4 border-b pb-2">' . __('Child & Family') . '</h3>';

    echo '<div class="space-y-4">';

    // Child
    echo '<div>';
    echo '<span class="text-sm text-gray-500">' . __('Child') . '</span>';
    echo '<div class="font-semibold text-lg">' . Format::name('', $invoice['childPreferredName'], $invoice['childSurname'], 'Student', true) . '</div>';
    if (!empty($invoice['childDOB'])) {
        echo '<span class="text-sm text-gray-500">' . __('DOB') . ': ' . Format::date($invoice['childDOB']) . '</span>';
    }
    echo '</div>';

    // Family
    echo '<div>';
    echo '<span class="text-sm text-gray-500">' . __('Family') . '</span>';
    echo '<div class="font-medium">' . htmlspecialchars($invoice['familyName']) . '</div>';
    echo '</div>';

    // View family invoices link
    echo '<div class="pt-2">';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/EnhancedFinance/finance_invoices.php&gibbonFamilyID=' . $invoice['gibbonFamilyID'] . '&gibbonSchoolYearID=' . $gibbonSchoolYearID . '" class="text-sm text-blue-600 hover:underline">';
    echo __('View all invoices for this family') . ' &rarr;';
    echo '</a>';
    echo '</div>';

    echo '</div>'; // End space-y-4
    echo '</div>'; // End Child & Family Card

    echo '</div>'; // End grid

    // Amounts Section
    echo '<div class="bg-white border rounded-lg shadow-sm p-5 mb-6">';
    echo '<h3 class="text-lg font-semibold mb-4 border-b pb-2">' . __('Amount Details') . ' <small class="text-gray-500">(' . $currency . ')</small></h3>';

    echo '<div class="grid grid-cols-2 md:grid-cols-4 gap-4">';

    // Subtotal
    echo '<div class="text-center p-4 bg-gray-50 rounded-lg">';
    echo '<div class="text-sm text-gray-500 mb-1">' . __('Subtotal') . '</div>';
    echo '<div class="text-xl font-semibold">' . Format::currency($invoice['subtotal']) . '</div>';
    echo '</div>';

    // Tax Amount
    echo '<div class="text-center p-4 bg-gray-50 rounded-lg">';
    echo '<div class="text-sm text-gray-500 mb-1">' . __('Tax') . '</div>';
    echo '<div class="text-xl font-semibold">' . Format::currency($invoice['taxAmount']) . '</div>';
    // Get combined tax rate from service
    $taxRate = number_format($invoiceService->getCombinedTaxRate() * 100, 3);
    echo '<div class="text-xs text-gray-400">(' . $taxRate . '%)</div>';
    echo '</div>';

    // Total Amount
    echo '<div class="text-center p-4 bg-blue-50 rounded-lg">';
    echo '<div class="text-sm text-blue-600 mb-1">' . __('Total') . '</div>';
    echo '<div class="text-xl font-bold text-blue-700">' . Format::currency($invoice['totalAmount']) . '</div>';
    echo '</div>';

    // Paid Amount
    echo '<div class="text-center p-4 bg-green-50 rounded-lg">';
    echo '<div class="text-sm text-green-600 mb-1">' . __('Paid') . '</div>';
    echo '<div class="text-xl font-bold text-green-700">' . Format::currency($invoice['paidAmount']) . '</div>';
    echo '</div>';

    echo '</div>'; // End grid

    // Balance Remaining (prominent display if outstanding)
    if ($balanceRemaining > 0) {
        $balanceClass = $isOverdue ? 'bg-red-100 border-red-300' : 'bg-orange-100 border-orange-300';
        $textClass = $isOverdue ? 'text-red-700' : 'text-orange-700';
        echo '<div class="mt-6 p-4 rounded-lg border ' . $balanceClass . ' text-center">';
        echo '<div class="text-sm ' . $textClass . ' mb-1">' . __('Balance Remaining') . '</div>';
        echo '<div class="text-3xl font-bold ' . $textClass . '">' . Format::currency($balanceRemaining) . '</div>';
        echo '</div>';
    } else {
        echo '<div class="mt-6 p-4 rounded-lg border bg-green-100 border-green-300 text-center">';
        echo '<div class="text-sm text-green-700 mb-1">' . __('Status') . '</div>';
        echo '<div class="text-2xl font-bold text-green-700">' . __('Paid in Full') . '</div>';
        echo '</div>';
    }

    echo '</div>'; // End Amounts Section

    // Notes Section (if any)
    if (!empty($invoice['notes'])) {
        echo '<div class="bg-white border rounded-lg shadow-sm p-5 mb-6">';
        echo '<h3 class="text-lg font-semibold mb-4 border-b pb-2">' . __('Notes') . '</h3>';
        echo '<div class="text-gray-700 whitespace-pre-wrap">' . htmlspecialchars($invoice['notes']) . '</div>';
        echo '</div>';
    }

    // Payment History Section
    echo '<div class="bg-white border rounded-lg shadow-sm p-5 mb-6">';
    echo '<h3 class="text-lg font-semibold mb-4 border-b pb-2">' . __('Payment History') . '</h3>';

    // Get payments for this invoice
    $payments = $paymentGateway->selectPaymentsByInvoiceID($gibbonEnhancedFinanceInvoiceID)->fetchAll();

    if (count($payments) > 0) {
        // Create DataTable for payments
        $table = DataTable::create('payments');

        $table->addColumn('paymentDate', __('Date'))
            ->format(function ($payment) {
                return Format::date($payment['paymentDate']);
            });

        $table->addColumn('amount', __('Amount'))
            ->format(function ($payment) {
                return '<span class="font-semibold text-green-600">' . Format::currency($payment['amount']) . '</span>';
            });

        $table->addColumn('method', __('Method'))
            ->format(function ($payment) {
                $methodIcons = [
                    'Cash' => '💵',
                    'Cheque' => '📄',
                    'ETransfer' => '💸',
                    'CreditCard' => '💳',
                    'DebitCard' => '💳',
                    'Other' => '📋',
                ];
                $icon = $methodIcons[$payment['method']] ?? '📋';
                return $icon . ' ' . __($payment['method']);
            });

        $table->addColumn('reference', __('Reference'))
            ->format(function ($payment) {
                return !empty($payment['reference']) ? htmlspecialchars($payment['reference']) : '<span class="text-gray-400">-</span>';
            });

        $table->addColumn('recordedBy', __('Recorded By'))
            ->format(function ($payment) {
                return Format::name('', $payment['recordedByPreferredName'], $payment['recordedBySurname'], 'Staff', false);
            });

        $table->addColumn('notes', __('Notes'))
            ->format(function ($payment) {
                return !empty($payment['notes']) ? '<span class="text-sm">' . htmlspecialchars($payment['notes']) . '</span>' : '';
            });

        echo $table->render($payments);

        // Payment summary
        $totalPaid = array_sum(array_column($payments, 'amount'));
        echo '<div class="mt-4 text-right text-sm text-gray-600">';
        echo '<strong>' . __('Total Payments') . ':</strong> ' . count($payments) . ' | ';
        echo '<strong>' . __('Total Paid') . ':</strong> ' . Format::currency($totalPaid);
        echo '</div>';
    } else {
        echo '<div class="text-center py-8 text-gray-500">';
        echo '<p>' . __('No payments have been recorded for this invoice.') . '</p>';
        echo '</div>';
    }

    echo '</div>'; // End Payment History Section

    // Record Payment Form (if invoice can accept payments)
    if ($canRecordPayment) {
        echo '<div class="bg-white border rounded-lg shadow-sm p-5 mb-6">';
        echo '<h3 class="text-lg font-semibold mb-4 border-b pb-2">' . __('Record Payment') . '</h3>';

        // Payment form
        $form = Form::create('paymentAdd', $session->get('absoluteURL') . '/modules/EnhancedFinance/finance_payment_addProcess.php');

        $form->addHiddenValue('address', $session->get('address'));
        $form->addHiddenValue('gibbonEnhancedFinanceInvoiceID', $gibbonEnhancedFinanceInvoiceID);
        $form->addHiddenValue('gibbonSchoolYearID', $gibbonSchoolYearID);

        // Payment Date
        $row = $form->addRow();
            $row->addLabel('paymentDate', __('Payment Date'));
            $row->addDate('paymentDate')
                ->setValue(Format::date(date('Y-m-d')))
                ->required();

        // Amount
        $row = $form->addRow();
            $row->addLabel('amount', __('Amount') . ' (' . $currency . ')')
                ->description(sprintf(__('Balance remaining: %s'), Format::currency($balanceRemaining)));
            $row->addCurrency('amount')
                ->setID('amount')
                ->placeholder(number_format($balanceRemaining, 2))
                ->required();

        // Payment Method (using service)
        $methodOptions = $paymentService->getPaymentMethods();
        $row = $form->addRow();
            $row->addLabel('method', __('Payment Method'));
            $row->addSelect('method')
                ->fromArray($methodOptions)
                ->required();

        // Reference
        $row = $form->addRow();
            $row->addLabel('reference', __('Reference'))
                ->description(__('Transaction ID, cheque number, etc.'));
            $row->addTextField('reference')
                ->maxLength(100);

        // Notes
        $row = $form->addRow();
            $row->addLabel('notes', __('Notes'));
            $row->addTextArea('notes')
                ->setRows(2)
                ->setClass('w-full');

        // Submit
        $row = $form->addRow();
            $row->addFooter();
            $row->addSubmit(__('Record Payment'));

        echo $form->getOutput();

        // Quick payment buttons
        echo '<div class="mt-4 pt-4 border-t">';
        echo '<div class="text-sm text-gray-600 mb-2">' . __('Quick Payment:') . '</div>';
        echo '<div class="flex gap-2">';
        echo '<button type="button" onclick="document.getElementById(\'amount\').value = \'' . number_format($balanceRemaining, 2) . '\'" class="px-4 py-2 bg-green-100 text-green-700 rounded hover:bg-green-200 transition">';
        echo __('Pay Full Balance') . ' (' . Format::currency($balanceRemaining) . ')';
        echo '</button>';
        echo '</div>';
        echo '</div>';

        echo '</div>'; // End Record Payment Section
    }

    // Audit Information
    echo '<div class="bg-gray-50 border rounded-lg p-4 text-sm text-gray-600">';
    echo '<div class="flex justify-between">';
    echo '<div>';
    echo '<strong>' . __('Created') . ':</strong> ' . Format::dateTime($invoice['timestampCreated']);
    if (!empty($invoice['timestampModified']) && $invoice['timestampModified'] != $invoice['timestampCreated']) {
        echo ' | <strong>' . __('Last Modified') . ':</strong> ' . Format::dateTime($invoice['timestampModified']);
    }
    echo '</div>';
    echo '<div>';
    echo '<strong>' . __('Invoice ID') . ':</strong> ' . $gibbonEnhancedFinanceInvoiceID;
    echo '</div>';
    echo '</div>';
    echo '</div>';
}
