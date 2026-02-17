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
 * Enhanced Finance Module - Invoice Add
 *
 * Form for creating new invoices. Allows selecting child, family,
 * setting amounts, dates, and applying tax rates.
 *
 * @package    Gibbon\Module\EnhancedFinance
 * @author     LAYA
 * @license    GPL-3.0
 */

use Gibbon\Forms\Form;
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Services\Format;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Domain\User\FamilyGateway;
use Gibbon\Domain\Students\StudentGateway;
use Gibbon\Module\EnhancedFinance\Domain\InvoiceGateway;
use Gibbon\Module\EnhancedFinance\Service\InvoiceService;
use Gibbon\Module\EnhancedFinance\Validator\InvoiceValidator;

// Access check
if (isActionAccessible($guid, $connection2, '/modules/EnhancedFinance/finance_invoice_add.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs
        ->add(__('Enhanced Finance'), 'finance.php')
        ->add(__('Manage Invoices'), 'finance_invoices.php')
        ->add(__('Add Invoice'));

    // Get request parameters
    $gibbonSchoolYearID = $_REQUEST['gibbonSchoolYearID'] ?? $session->get('gibbonSchoolYearID');
    $gibbonFamilyID = $_GET['gibbonFamilyID'] ?? '';
    $gibbonPersonID = $_GET['gibbonPersonID'] ?? '';

    if (empty($gibbonSchoolYearID)) {
        $page->addError(__('School year has not been specified.'));
        return;
    }

    // Get gateways and services
    $settingGateway = $container->get(SettingGateway::class);
    $familyGateway = $container->get(FamilyGateway::class);
    $studentGateway = $container->get(StudentGateway::class);
    $invoiceGateway = $container->get(InvoiceGateway::class);

    // Initialize Invoice Service
    $invoiceValidator = new InvoiceValidator();
    $invoiceService = new InvoiceService($settingGateway, $invoiceGateway, $invoiceValidator);

    // Get currency from settings
    $currency = $settingGateway->getSettingByScope('System', 'currency') ?: 'CAD';

    // Get tax rates from service
    $taxRates = $invoiceService->getTaxRates();
    $gstRate = $taxRates['gst'];
    $qstRate = $taxRates['qst'];
    $combinedTaxRate = $taxRates['combined'];

    // Generate next invoice number using service
    $invoiceNumber = $invoiceService->generateInvoiceNumber($gibbonSchoolYearID);

    // Calculate default dates using service
    $defaultInvoiceDate = date('Y-m-d');
    $defaultDueDate = $invoiceService->calculateDueDate($defaultInvoiceDate);

    // Description
    echo '<p>';
    echo __('Use this form to create a new invoice. Select the child and family, enter the amounts, and set the due date. Tax will be calculated automatically based on module settings.');
    echo '</p>';

    // Tax rate information
    echo '<div class="message">';
    echo '<strong>' . __('Tax Rates Applied:') . '</strong><br/>';
    echo __('GST') . ': ' . number_format((float)$gstRate * 100, 3) . '%<br/>';
    echo __('QST') . ': ' . number_format((float)$qstRate * 100, 3) . '%<br/>';
    echo __('Combined') . ': ' . number_format($combinedTaxRate * 100, 3) . '%';
    echo '</div>';

    // Create form
    $form = Form::create('invoiceAdd', $session->get('absoluteURL') . '/modules/EnhancedFinance/finance_invoice_addProcess.php');
    $form->setFactory(DatabaseFormFactory::create($pdo));

    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('gibbonSchoolYearID', $gibbonSchoolYearID);
    $form->addHiddenValue('gstRate', $gstRate);
    $form->addHiddenValue('qstRate', $qstRate);
    $form->addHiddenValue('csrf_token', $session->get('csrf_token'));

    // Invoice Details Section
    $form->addRow()->addHeading(__('Invoice Details'));

    // Invoice Number (auto-generated, read only)
    $row = $form->addRow();
        $row->addLabel('invoiceNumber', __('Invoice Number'));
        $row->addTextField('invoiceNumber')
            ->setValue($invoiceNumber)
            ->readonly()
            ->required();

    // Invoice Date
    $row = $form->addRow();
        $row->addLabel('invoiceDate', __('Invoice Date'));
        $row->addDate('invoiceDate')
            ->setValue(Format::date($defaultInvoiceDate))
            ->required();

    // Due Date
    $row = $form->addRow();
        $row->addLabel('dueDate', __('Due Date'))
            ->description(__('Payment is expected by this date.'));
        $row->addDate('dueDate')
            ->setValue(Format::date($defaultDueDate))
            ->required();

    // Status
    $statusOptions = [
        'Pending' => __('Pending'),
        'Issued' => __('Issued'),
    ];
    $row = $form->addRow();
        $row->addLabel('status', __('Status'))
            ->description(__('Set to Issued when ready to send to family.'));
        $row->addSelect('status')
            ->fromArray($statusOptions)
            ->selected('Pending')
            ->required();

    // Child and Family Section
    $form->addRow()->addHeading(__('Child & Family'));

    // Family selection
    $families = $familyGateway->selectFamiliesWithActiveStudents($gibbonSchoolYearID)->fetchAll();
    $familyOptions = [];
    foreach ($families as $family) {
        $familyOptions[$family['gibbonFamilyID']] = $family['name'];
    }

    $row = $form->addRow();
        $row->addLabel('gibbonFamilyID', __('Family'))
            ->description(__('Select the family to bill.'));
        $col = $row->addColumn()->addClass('flex-col');
        $col->addSelect('gibbonFamilyID')
            ->setID('gibbonFamilyID')
            ->fromArray($familyOptions)
            ->placeholder(__('Select a family...'))
            ->selected($gibbonFamilyID)
            ->required();

    // Child selection (will be populated based on family via JavaScript)
    $row = $form->addRow();
        $row->addLabel('gibbonPersonID', __('Child'))
            ->description(__('Select the child for this invoice.'));
        $row->addSelect('gibbonPersonID')
            ->setID('gibbonPersonID')
            ->placeholder(__('Select family first...'))
            ->selected($gibbonPersonID)
            ->required();

    // Amounts Section
    $form->addRow()->addHeading(__('Amounts'));

    // Subtotal (before tax)
    $row = $form->addRow();
        $row->addLabel('subtotal', __('Subtotal') . ' (' . $currency . ')')
            ->description(__('Amount before taxes.'));
        $row->addCurrency('subtotal')
            ->setID('subtotal')
            ->placeholder('0.00')
            ->required();

    // Apply tax checkbox
    $row = $form->addRow();
        $row->addLabel('applyTax', __('Apply Tax'))
            ->description(__('Check to apply GST/QST taxes.'));
        $row->addCheckbox('applyTax')
            ->setID('applyTax')
            ->checked(true);

    // Tax Amount (calculated)
    $row = $form->addRow();
        $row->addLabel('taxAmount', __('Tax Amount') . ' (' . $currency . ')')
            ->description(__('Automatically calculated.'));
        $row->addCurrency('taxAmount')
            ->setID('taxAmount')
            ->placeholder('0.00')
            ->readonly();

    // Total Amount (calculated)
    $row = $form->addRow();
        $row->addLabel('totalAmount', __('Total Amount') . ' (' . $currency . ')')
            ->description(__('Subtotal plus tax.'));
        $row->addCurrency('totalAmount')
            ->setID('totalAmount')
            ->placeholder('0.00')
            ->readonly();

    // Additional Information Section
    $form->addRow()->addHeading(__('Additional Information'));

    // Notes
    $row = $form->addRow();
        $row->addLabel('notes', __('Notes'))
            ->description(__('Internal notes or description of charges.'));
        $row->addTextArea('notes')
            ->setRows(4)
            ->setClass('w-full');

    // Submit buttons
    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();

    echo $form->getOutput();

    // JavaScript for dynamic child loading and tax calculation
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var familySelect = document.getElementById('gibbonFamilyID');
        var childSelect = document.getElementById('gibbonPersonID');
        var subtotalInput = document.getElementById('subtotal');
        var taxAmountInput = document.getElementById('taxAmount');
        var totalAmountInput = document.getElementById('totalAmount');
        var applyTaxCheckbox = document.getElementById('applyTax');

        var gstRate = <?php echo json_encode((float)$gstRate); ?>;
        var qstRate = <?php echo json_encode((float)$qstRate); ?>;
        var combinedTaxRate = gstRate + qstRate;
        var absoluteURL = '<?php echo $session->get('absoluteURL'); ?>';
        var gibbonSchoolYearID = '<?php echo $gibbonSchoolYearID; ?>';

        // Load children when family changes
        if (familySelect) {
            familySelect.addEventListener('change', function() {
                var gibbonFamilyID = this.value;

                // Clear child select
                childSelect.innerHTML = '<option value=""><?php echo __("Loading..."); ?></option>';

                if (!gibbonFamilyID) {
                    childSelect.innerHTML = '<option value=""><?php echo __("Select family first..."); ?></option>';
                    return;
                }

                // Fetch children for this family
                fetch(absoluteURL + '/modules/EnhancedFinance/finance_invoice_childrenAjax.php?gibbonFamilyID=' + gibbonFamilyID + '&gibbonSchoolYearID=' + gibbonSchoolYearID)
                    .then(response => response.json())
                    .then(data => {
                        childSelect.innerHTML = '<option value=""><?php echo __("Select a child..."); ?></option>';
                        if (data && data.length > 0) {
                            data.forEach(function(child) {
                                var option = document.createElement('option');
                                option.value = child.gibbonPersonID;
                                option.textContent = child.name;
                                childSelect.appendChild(option);
                            });
                        } else {
                            childSelect.innerHTML = '<option value=""><?php echo __("No children found"); ?></option>';
                        }
                    })
                    .catch(error => {
                        console.error('Error loading children:', error);
                        childSelect.innerHTML = '<option value=""><?php echo __("Error loading children"); ?></option>';
                    });
            });

            // Trigger initial load if family is pre-selected
            if (familySelect.value) {
                familySelect.dispatchEvent(new Event('change'));
            }
        }

        // Calculate tax and total when subtotal changes
        function calculateAmounts() {
            var subtotal = parseFloat(subtotalInput.value) || 0;
            var applyTax = applyTaxCheckbox.checked;

            var taxAmount = 0;
            if (applyTax) {
                taxAmount = subtotal * combinedTaxRate;
            }

            var totalAmount = subtotal + taxAmount;

            taxAmountInput.value = taxAmount.toFixed(2);
            totalAmountInput.value = totalAmount.toFixed(2);
        }

        if (subtotalInput) {
            subtotalInput.addEventListener('input', calculateAmounts);
            subtotalInput.addEventListener('change', calculateAmounts);
        }

        if (applyTaxCheckbox) {
            applyTaxCheckbox.addEventListener('change', calculateAmounts);
        }

        // Initial calculation
        calculateAmounts();
    });
    </script>
    <?php
}
