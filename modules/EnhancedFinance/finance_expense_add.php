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
 * Enhanced Finance Module - Expense Add
 *
 * Form for creating new expenses. Allows selecting category, vendor,
 * setting amounts, dates, payment method, and adding notes.
 *
 * @package    Gibbon\Module\EnhancedFinance
 * @author     LAYA
 * @license    GPL-3.0
 */

use Gibbon\Forms\Form;
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Services\Format;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Module\EnhancedFinance\Domain\ExpenseGateway;

// Access check
if (isActionAccessible($guid, $connection2, '/modules/EnhancedFinance/finance_expense_add.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs
        ->add(__('Enhanced Finance'), 'finance.php')
        ->add(__('Manage Expenses'), 'finance_expenses.php')
        ->add(__('Add Expense'));

    // Get request parameters
    $gibbonSchoolYearID = $_REQUEST['gibbonSchoolYearID'] ?? $session->get('gibbonSchoolYearID');

    if (empty($gibbonSchoolYearID)) {
        $page->addError(__('School year has not been specified.'));
        return;
    }

    // Get gateways and settings
    $settingGateway = $container->get(SettingGateway::class);
    $expenseGateway = $container->get(ExpenseGateway::class);

    // Get settings
    $gstRate = $settingGateway->getSettingByScope('Enhanced Finance', 'gstRate') ?: '0.05';
    $qstRate = $settingGateway->getSettingByScope('Enhanced Finance', 'qstRate') ?: '0.09975';
    $currency = $settingGateway->getSettingByScope('System', 'currency') ?: 'CAD';

    // Calculate combined tax rate
    $combinedTaxRate = (float)$gstRate + (float)$qstRate;

    // Default expense date is today
    $defaultExpenseDate = date('Y-m-d');

    // Description
    echo '<p>';
    echo __('Use this form to record a new expense. Select the category, enter the amount, vendor, and add any relevant notes. Tax can be applied automatically based on module settings.');
    echo '</p>';

    // Tax rate information
    echo '<div class="message">';
    echo '<strong>' . __('Tax Rates (if applicable):') . '</strong><br/>';
    echo __('GST') . ': ' . number_format((float)$gstRate * 100, 3) . '%<br/>';
    echo __('QST') . ': ' . number_format((float)$qstRate * 100, 3) . '%<br/>';
    echo __('Combined') . ': ' . number_format($combinedTaxRate * 100, 3) . '%';
    echo '</div>';

    // Get expense categories for dropdown
    $categorySQL = "SELECT gibbonEnhancedFinanceExpenseCategoryID, name, accountCode
                    FROM gibbonEnhancedFinanceExpenseCategory
                    WHERE isActive = 1
                    ORDER BY sortOrder ASC, name ASC";
    $categoryResult = $connection2->query($categorySQL);
    $categoryOptions = [];
    while ($row = $categoryResult->fetch()) {
        $label = $row['name'];
        if (!empty($row['accountCode'])) {
            $label .= ' (' . $row['accountCode'] . ')';
        }
        $categoryOptions[$row['gibbonEnhancedFinanceExpenseCategoryID']] = $label;
    }

    // Get distinct vendors for autocomplete
    $vendors = $expenseGateway->selectDistinctVendors()->fetchAll();
    $vendorList = array_column($vendors, 'vendor');

    // Create form
    $form = Form::create('expenseAdd', $session->get('absoluteURL') . '/modules/EnhancedFinance/finance_expense_addProcess.php');
    $form->setFactory(DatabaseFormFactory::create($pdo));

    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('gibbonSchoolYearID', $gibbonSchoolYearID);
    $form->addHiddenValue('gstRate', $gstRate);
    $form->addHiddenValue('qstRate', $qstRate);

    // Expense Details Section
    $form->addRow()->addHeading(__('Expense Details'));

    // Category
    $row = $form->addRow();
        $row->addLabel('gibbonEnhancedFinanceExpenseCategoryID', __('Category'))
            ->description(__('Select the expense category.'));
        $row->addSelect('gibbonEnhancedFinanceExpenseCategoryID')
            ->fromArray($categoryOptions)
            ->placeholder(__('Select a category...'))
            ->required();

    // Expense Date
    $row = $form->addRow();
        $row->addLabel('expenseDate', __('Expense Date'));
        $row->addDate('expenseDate')
            ->setValue(Format::date($defaultExpenseDate))
            ->required();

    // Status
    $statusOptions = [
        'Pending'  => __('Pending'),
        'Approved' => __('Approved'),
        'Paid'     => __('Paid'),
    ];
    $row = $form->addRow();
        $row->addLabel('status', __('Status'))
            ->description(__('Set the current status of this expense.'));
        $row->addSelect('status')
            ->fromArray($statusOptions)
            ->selected('Pending')
            ->required();

    // Vendor and Reference Section
    $form->addRow()->addHeading(__('Vendor Information'));

    // Vendor
    $row = $form->addRow();
        $row->addLabel('vendor', __('Vendor'))
            ->description(__('Name of the vendor or supplier.'));
        $row->addTextField('vendor')
            ->setID('vendor')
            ->maxLength(150)
            ->autocomplete('off');

    // Reference (invoice number, receipt number, etc.)
    $row = $form->addRow();
        $row->addLabel('reference', __('Reference'))
            ->description(__('Invoice number, receipt number, or other reference.'));
        $row->addTextField('reference')
            ->maxLength(100);

    // Payment Method
    $paymentMethodOptions = [
        'Cash'       => __('Cash'),
        'Cheque'     => __('Cheque'),
        'ETransfer'  => __('E-Transfer'),
        'CreditCard' => __('Credit Card'),
        'DebitCard'  => __('Debit Card'),
        'Other'      => __('Other'),
    ];
    $row = $form->addRow();
        $row->addLabel('paymentMethod', __('Payment Method'))
            ->description(__('How was/will this expense be paid?'));
        $row->addSelect('paymentMethod')
            ->fromArray($paymentMethodOptions)
            ->selected('Other')
            ->required();

    // Amounts Section
    $form->addRow()->addHeading(__('Amounts'));

    // Amount (before tax)
    $row = $form->addRow();
        $row->addLabel('amount', __('Amount') . ' (' . $currency . ')')
            ->description(__('Amount before taxes.'));
        $row->addCurrency('amount')
            ->setID('amount')
            ->placeholder('0.00')
            ->required();

    // Apply tax checkbox
    $row = $form->addRow();
        $row->addLabel('applyTax', __('Apply Tax'))
            ->description(__('Check to apply GST/QST taxes to this expense.'));
        $row->addCheckbox('applyTax')
            ->setID('applyTax')
            ->checked(false);

    // Tax Amount (calculated or manual)
    $row = $form->addRow();
        $row->addLabel('taxAmount', __('Tax Amount') . ' (' . $currency . ')')
            ->description(__('Calculated automatically if Apply Tax is checked, or enter manually.'));
        $row->addCurrency('taxAmount')
            ->setID('taxAmount')
            ->placeholder('0.00');

    // Total Amount (calculated)
    $row = $form->addRow();
        $row->addLabel('totalAmount', __('Total Amount') . ' (' . $currency . ')')
            ->description(__('Amount plus tax.'));
        $row->addCurrency('totalAmount')
            ->setID('totalAmount')
            ->placeholder('0.00')
            ->readonly();

    // Additional Information Section
    $form->addRow()->addHeading(__('Additional Information'));

    // Description/Notes
    $row = $form->addRow();
        $row->addLabel('description', __('Description'))
            ->description(__('Description of the expense or notes.'));
        $row->addTextArea('description')
            ->setRows(4)
            ->setClass('w-full');

    // Submit buttons
    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();

    echo $form->getOutput();

    // JavaScript for tax calculation and vendor autocomplete
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var amountInput = document.getElementById('amount');
        var taxAmountInput = document.getElementById('taxAmount');
        var totalAmountInput = document.getElementById('totalAmount');
        var applyTaxCheckbox = document.getElementById('applyTax');
        var vendorInput = document.getElementById('vendor');

        var gstRate = <?php echo json_encode((float)$gstRate); ?>;
        var qstRate = <?php echo json_encode((float)$qstRate); ?>;
        var combinedTaxRate = gstRate + qstRate;
        var vendorList = <?php echo json_encode($vendorList); ?>;

        // Calculate tax and total when amount changes or tax checkbox changes
        function calculateAmounts() {
            var amount = parseFloat(amountInput.value) || 0;
            var applyTax = applyTaxCheckbox.checked;
            var manualTax = parseFloat(taxAmountInput.value) || 0;

            var taxAmount = 0;
            if (applyTax) {
                taxAmount = amount * combinedTaxRate;
                taxAmountInput.value = taxAmount.toFixed(2);
                taxAmountInput.readOnly = true;
            } else {
                taxAmountInput.readOnly = false;
                taxAmount = manualTax;
            }

            var totalAmount = amount + taxAmount;
            totalAmountInput.value = totalAmount.toFixed(2);
        }

        // Update total when manual tax is entered
        function updateTotal() {
            var amount = parseFloat(amountInput.value) || 0;
            var taxAmount = parseFloat(taxAmountInput.value) || 0;
            var totalAmount = amount + taxAmount;
            totalAmountInput.value = totalAmount.toFixed(2);
        }

        if (amountInput) {
            amountInput.addEventListener('input', calculateAmounts);
            amountInput.addEventListener('change', calculateAmounts);
        }

        if (applyTaxCheckbox) {
            applyTaxCheckbox.addEventListener('change', calculateAmounts);
        }

        if (taxAmountInput) {
            taxAmountInput.addEventListener('input', function() {
                if (!applyTaxCheckbox.checked) {
                    updateTotal();
                }
            });
            taxAmountInput.addEventListener('change', function() {
                if (!applyTaxCheckbox.checked) {
                    updateTotal();
                }
            });
        }

        // Simple vendor autocomplete
        if (vendorInput && vendorList.length > 0) {
            var datalist = document.createElement('datalist');
            datalist.id = 'vendorSuggestions';
            vendorList.forEach(function(vendor) {
                var option = document.createElement('option');
                option.value = vendor;
                datalist.appendChild(option);
            });
            document.body.appendChild(datalist);
            vendorInput.setAttribute('list', 'vendorSuggestions');
        }

        // Initial calculation
        calculateAmounts();
    });
    </script>
    <?php
}
