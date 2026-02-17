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
 * Enhanced Finance Module - Payment Add
 *
 * Form for recording new payments against invoices. Allows selecting an invoice,
 * entering payment details including amount, date, method, and reference.
 *
 * @package    Gibbon\Module\EnhancedFinance
 * @author     LAYA
 * @license    GPL-3.0
 */

use Gibbon\Forms\Form;
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Services\Format;
use Gibbon\Tables\DataTable;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Domain\User\FamilyGateway;
use Gibbon\Module\EnhancedFinance\Domain\InvoiceGateway;
use Gibbon\Module\EnhancedFinance\Domain\PaymentGateway;

// Access check
if (isActionAccessible($guid, $connection2, '/modules/EnhancedFinance/finance_payment_add.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs
        ->add(__('Enhanced Finance'), 'finance.php')
        ->add(__('Record Payment'));

    // Return messages
    $page->return->addReturns([
        'success0' => __('Your request was completed successfully.'),
        'success1' => __('Payment recorded successfully.'),
        'error1' => __('There was an error recording the payment.'),
        'error2' => __('The selected invoice could not be found.'),
        'error3' => __('Required parameters were not provided.'),
        'error4' => __('Invalid payment amount.'),
        'error5' => __('Payment amount exceeds outstanding balance.'),
    ]);

    // Get request parameters
    $gibbonSchoolYearID = $_REQUEST['gibbonSchoolYearID'] ?? $session->get('gibbonSchoolYearID');
    $gibbonEnhancedFinanceInvoiceID = $_GET['gibbonEnhancedFinanceInvoiceID'] ?? '';
    $gibbonFamilyID = $_GET['gibbonFamilyID'] ?? '';

    if (empty($gibbonSchoolYearID)) {
        $page->addError(__('School year has not been specified.'));
        return;
    }

    // Get gateways and settings
    $settingGateway = $container->get(SettingGateway::class);
    $familyGateway = $container->get(FamilyGateway::class);
    $invoiceGateway = $container->get(InvoiceGateway::class);
    $paymentGateway = $container->get(PaymentGateway::class);

    // Get settings
    $currency = $settingGateway->getSettingByScope('System', 'currency') ?: 'CAD';

    // Payment method options (must match ENUM values in database schema)
    $paymentMethods = [
        'Cash'       => __('Cash'),
        'Cheque'     => __('Cheque'),
        'ETransfer'  => __('E-Transfer'),
        'CreditCard' => __('Credit Card'),
        'DebitCard'  => __('Debit Card'),
        'Other'      => __('Other'),
    ];

    // Description
    echo '<p>';
    echo __('Use this form to record a payment against an outstanding invoice. Select the invoice, enter the payment details, and submit to record the payment.');
    echo '</p>';

    // If an invoice ID is provided, pre-load that invoice
    $selectedInvoice = null;
    if (!empty($gibbonEnhancedFinanceInvoiceID)) {
        $selectedInvoice = $invoiceGateway->selectInvoiceByID($gibbonEnhancedFinanceInvoiceID);
        if (!empty($selectedInvoice)) {
            // Display invoice details
            echo '<div class="message">';
            echo '<strong>' . __('Recording payment for Invoice') . ': ' . htmlspecialchars($selectedInvoice['invoiceNumber']) . '</strong><br/>';
            echo __('Child') . ': ' . Format::name('', $selectedInvoice['childPreferredName'], $selectedInvoice['childSurname'], 'Student', true) . '<br/>';
            echo __('Family') . ': ' . htmlspecialchars($selectedInvoice['familyName']) . '<br/>';
            echo __('Total Amount') . ': ' . Format::currency($selectedInvoice['totalAmount']) . '<br/>';
            echo __('Paid Amount') . ': ' . Format::currency($selectedInvoice['paidAmount']) . '<br/>';
            echo '<strong>' . __('Balance Remaining') . ': ' . Format::currency($selectedInvoice['balanceRemaining']) . '</strong>';
            echo '</div>';
        }
    }

    // Create form
    $form = Form::create('paymentAdd', $session->get('absoluteURL') . '/modules/EnhancedFinance/finance_payment_addProcess.php');
    $form->setFactory(DatabaseFormFactory::create($pdo));

    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('gibbonSchoolYearID', $gibbonSchoolYearID);

    // Invoice Selection Section
    $form->addRow()->addHeading(__('Invoice Selection'));

    if (!empty($selectedInvoice)) {
        // Invoice pre-selected, show as read-only
        $form->addHiddenValue('gibbonEnhancedFinanceInvoiceID', $gibbonEnhancedFinanceInvoiceID);

        $row = $form->addRow();
            $row->addLabel('invoiceDisplay', __('Invoice'));
            $row->addTextField('invoiceDisplay')
                ->setValue($selectedInvoice['invoiceNumber'] . ' - ' . Format::name('', $selectedInvoice['childPreferredName'], $selectedInvoice['childSurname'], 'Student', false))
                ->readonly();

        $row = $form->addRow();
            $row->addLabel('balanceDisplay', __('Balance Remaining') . ' (' . $currency . ')');
            $row->addTextField('balanceDisplay')
                ->setValue(Format::currency($selectedInvoice['balanceRemaining']))
                ->readonly();
    } else {
        // Family selection first
        $families = $familyGateway->selectFamiliesWithActiveStudents($gibbonSchoolYearID)->fetchAll();
        $familyOptions = [];
        foreach ($families as $family) {
            $familyOptions[$family['gibbonFamilyID']] = $family['name'];
        }

        $row = $form->addRow();
            $row->addLabel('gibbonFamilyID', __('Family'))
                ->description(__('Select a family to see their outstanding invoices.'));
            $row->addSelect('gibbonFamilyID')
                ->setID('gibbonFamilyID')
                ->fromArray($familyOptions)
                ->placeholder(__('Select a family...'))
                ->selected($gibbonFamilyID);

        // Invoice selection (populated via JavaScript based on family)
        $row = $form->addRow();
            $row->addLabel('gibbonEnhancedFinanceInvoiceID', __('Invoice'))
                ->description(__('Select an outstanding invoice to apply payment.'));
            $row->addSelect('gibbonEnhancedFinanceInvoiceID')
                ->setID('gibbonEnhancedFinanceInvoiceID')
                ->placeholder(__('Select family first...'))
                ->required();

        // Balance display (updated via JavaScript)
        $row = $form->addRow();
            $row->addLabel('balanceDisplay', __('Balance Remaining') . ' (' . $currency . ')');
            $row->addTextField('balanceDisplay')
                ->setID('balanceDisplay')
                ->placeholder('0.00')
                ->readonly();
    }

    // Payment Details Section
    $form->addRow()->addHeading(__('Payment Details'));

    // Payment Date
    $row = $form->addRow();
        $row->addLabel('paymentDate', __('Payment Date'));
        $row->addDate('paymentDate')
            ->setValue(Format::date(date('Y-m-d')))
            ->required();

    // Payment Amount
    $row = $form->addRow();
        $row->addLabel('amount', __('Payment Amount') . ' (' . $currency . ')')
            ->description(__('Enter the payment amount received.'));
        $row->addCurrency('amount')
            ->setID('amount')
            ->placeholder('0.00')
            ->required();

    // Pay Full Balance button hint
    if (!empty($selectedInvoice) && $selectedInvoice['balanceRemaining'] > 0) {
        $row = $form->addRow();
            $row->addContent('<button type="button" id="payFullBalance" class="button">' . __('Pay Full Balance') . '</button>')
                ->addClass('text-right');
    }

    // Payment Method
    $row = $form->addRow();
        $row->addLabel('method', __('Payment Method'));
        $row->addSelect('method')
            ->fromArray($paymentMethods)
            ->selected('Cash')
            ->required();

    // Reference Number
    $row = $form->addRow();
        $row->addLabel('reference', __('Reference Number'))
            ->description(__('Cheque number, transaction ID, etc.'));
        $row->addTextField('reference')
            ->maxLength(255);

    // Notes
    $row = $form->addRow();
        $row->addLabel('notes', __('Notes'))
            ->description(__('Additional details about this payment.'));
        $row->addTextArea('notes')
            ->setRows(3)
            ->setClass('w-full');

    // Submit buttons
    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit(__('Record Payment'));

    echo $form->getOutput();

    // Recent Payments Section (for context)
    echo '<h3 class="mt-6">' . __('Recent Payments') . '</h3>';

    $recentPayments = $paymentGateway->selectRecentPayments($gibbonSchoolYearID, 10);

    if ($recentPayments->rowCount() > 0) {
        $table = DataTable::create('recentPayments');
        $table->setTitle(__('Recent Payments'));

        $table->addColumn('paymentDate', __('Date'))
            ->format(function ($row) {
                return Format::date($row['paymentDate']);
            });

        $table->addColumn('invoiceNumber', __('Invoice'));

        $table->addColumn('child', __('Child'))
            ->format(function ($row) {
                return Format::name('', $row['childPreferredName'], $row['childSurname'], 'Student', false);
            });

        $table->addColumn('familyName', __('Family'));

        $table->addColumn('amount', __('Amount'))
            ->format(function ($row) {
                return Format::currency($row['amount']);
            });

        $table->addColumn('method', __('Method'))
            ->format(function ($row) {
                return __($row['method']);
            });

        echo $table->render($recentPayments->toDataSet());
    } else {
        echo '<div class="message">' . __('No recent payments recorded.') . '</div>';
    }

    // JavaScript for dynamic invoice loading and amount validation
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var familySelect = document.getElementById('gibbonFamilyID');
        var invoiceSelect = document.getElementById('gibbonEnhancedFinanceInvoiceID');
        var balanceDisplay = document.getElementById('balanceDisplay');
        var amountInput = document.getElementById('amount');
        var payFullBalanceBtn = document.getElementById('payFullBalance');

        var absoluteURL = '<?php echo $session->get('absoluteURL'); ?>';
        var gibbonSchoolYearID = '<?php echo $gibbonSchoolYearID; ?>';
        var currentBalance = <?php echo !empty($selectedInvoice) ? (float)$selectedInvoice['balanceRemaining'] : 0; ?>;
        var invoiceBalances = {};

        // Load invoices when family changes
        if (familySelect) {
            familySelect.addEventListener('change', function() {
                var gibbonFamilyID = this.value;

                // Clear invoice select
                invoiceSelect.innerHTML = '<option value=""><?php echo __("Loading..."); ?></option>';
                balanceDisplay.value = '';
                invoiceBalances = {};

                if (!gibbonFamilyID) {
                    invoiceSelect.innerHTML = '<option value=""><?php echo __("Select family first..."); ?></option>';
                    return;
                }

                // Fetch outstanding invoices for this family
                fetch(absoluteURL + '/modules/EnhancedFinance/finance_payment_invoicesAjax.php?gibbonFamilyID=' + gibbonFamilyID + '&gibbonSchoolYearID=' + gibbonSchoolYearID)
                    .then(response => response.json())
                    .then(data => {
                        invoiceSelect.innerHTML = '<option value=""><?php echo __("Select an invoice..."); ?></option>';
                        if (data && data.length > 0) {
                            data.forEach(function(invoice) {
                                var option = document.createElement('option');
                                option.value = invoice.gibbonEnhancedFinanceInvoiceID;
                                option.textContent = invoice.invoiceNumber + ' - ' + invoice.childName + ' (' + invoice.balanceFormatted + ' <?php echo __("remaining"); ?>)';
                                invoiceSelect.appendChild(option);

                                // Store balance for validation
                                invoiceBalances[invoice.gibbonEnhancedFinanceInvoiceID] = parseFloat(invoice.balance);
                            });
                        } else {
                            invoiceSelect.innerHTML = '<option value=""><?php echo __("No outstanding invoices"); ?></option>';
                        }
                    })
                    .catch(error => {
                        console.error('Error loading invoices:', error);
                        invoiceSelect.innerHTML = '<option value=""><?php echo __("Error loading invoices"); ?></option>';
                    });
            });
        }

        // Update balance display when invoice changes
        if (invoiceSelect) {
            invoiceSelect.addEventListener('change', function() {
                var invoiceID = this.value;
                if (invoiceID && invoiceBalances[invoiceID] !== undefined) {
                    currentBalance = invoiceBalances[invoiceID];
                    balanceDisplay.value = currentBalance.toFixed(2);
                } else {
                    currentBalance = 0;
                    balanceDisplay.value = '';
                }
            });
        }

        // Pay full balance button
        if (payFullBalanceBtn) {
            payFullBalanceBtn.addEventListener('click', function() {
                if (currentBalance > 0) {
                    amountInput.value = currentBalance.toFixed(2);
                }
            });
        }

        // Validate payment amount doesn't exceed balance
        if (amountInput) {
            amountInput.addEventListener('change', function() {
                var amount = parseFloat(this.value) || 0;
                if (amount > currentBalance && currentBalance > 0) {
                    alert('<?php echo __("Warning: Payment amount exceeds the outstanding balance."); ?>');
                }
                if (amount < 0) {
                    alert('<?php echo __("Payment amount cannot be negative."); ?>');
                    this.value = '';
                }
            });
        }
    });
    </script>
    <?php

    // Information notice about payments
    echo '<div class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">';
    echo '<h4 class="font-semibold text-blue-800 mb-2">' . __('About Payments') . '</h4>';
    echo '<ul class="list-disc list-inside text-blue-700 space-y-1">';
    echo '<li>' . __('Payments are recorded against specific invoices.') . '</li>';
    echo '<li>' . __('When an invoice is fully paid, its status will automatically update to "Paid".') . '</li>';
    echo '<li>' . __('Partial payments will update the invoice status to "Partial".') . '</li>';
    echo '<li>' . __('Keep reference numbers for cheques and electronic transfers for record-keeping.') . '</li>';
    echo '</ul>';
    echo '</div>';
}
