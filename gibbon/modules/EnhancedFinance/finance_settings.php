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
 * Enhanced Finance Module - Finance Settings
 *
 * Configure module settings for the Enhanced Finance module including:
 * - Provider information (SIN, Name, Address, NEQ) for RL-24 documents
 * - Tax rates (GST, QST)
 * - Invoice settings (prefix, payment terms)
 * - RL-24 filing deadline
 *
 * @package    Gibbon\Module\EnhancedFinance
 * @author     LAYA
 * @license    GPL-3.0
 */

use Gibbon\Forms\Form;
use Gibbon\Services\Format;
use Gibbon\Domain\System\SettingGateway;

// Module setup - breadcrumbs
$page->breadcrumbs
    ->add(__('Enhanced Finance'), 'finance.php')
    ->add(__('Finance Settings'));

// Access check
if (!isActionAccessible($guid, $connection2, '/modules/EnhancedFinance/finance_settings.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $settingGateway = $container->get(SettingGateway::class);
    $scope = 'Enhanced Finance';

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $success = true;

        // Define settings to update
        $settings = [
            'providerSIN' => $_POST['providerSIN'] ?? '',
            'providerName' => $_POST['providerName'] ?? '',
            'providerAddress' => $_POST['providerAddress'] ?? '',
            'providerNEQ' => $_POST['providerNEQ'] ?? '',
            'invoicePrefix' => $_POST['invoicePrefix'] ?? 'INV-',
            'gstRate' => $_POST['gstRate'] ?? '0.05',
            'qstRate' => $_POST['qstRate'] ?? '0.09975',
            'defaultPaymentTermsDays' => $_POST['defaultPaymentTermsDays'] ?? '30',
            'rl24FilingDeadline' => $_POST['rl24FilingDeadline'] ?? '02-28',
        ];

        // Validate SIN format if provided (XXX-XXX-XXX)
        if (!empty($settings['providerSIN']) && !preg_match('/^\d{3}-\d{3}-\d{3}$/', $settings['providerSIN'])) {
            $page->addError(__('Provider SIN must be in format XXX-XXX-XXX (e.g., 123-456-789).'));
            $success = false;
        }

        // Validate tax rates
        $gstRate = floatval($settings['gstRate']);
        if ($gstRate < 0 || $gstRate > 1) {
            $page->addError(__('GST Rate must be a decimal between 0 and 1 (e.g., 0.05 for 5%).'));
            $success = false;
        }

        $qstRate = floatval($settings['qstRate']);
        if ($qstRate < 0 || $qstRate > 1) {
            $page->addError(__('QST Rate must be a decimal between 0 and 1 (e.g., 0.09975 for 9.975%).'));
            $success = false;
        }

        // Validate payment terms
        $paymentTerms = intval($settings['defaultPaymentTermsDays']);
        if ($paymentTerms < 0 || $paymentTerms > 365) {
            $page->addError(__('Default Payment Terms must be between 0 and 365 days.'));
            $success = false;
        }

        if ($success) {
            // Update settings in database
            foreach ($settings as $name => $value) {
                try {
                    $data = ['value' => $value];
                    $sql = "UPDATE gibbonSetting SET value = :value WHERE scope = :scope AND name = :name";
                    $result = $connection2->prepare($sql);
                    $result->execute([
                        'value' => $value,
                        'scope' => $scope,
                        'name' => $name,
                    ]);
                } catch (PDOException $e) {
                    $success = false;
                }
            }

            if ($success) {
                $page->addSuccess(__('Your settings have been saved successfully.'));
            } else {
                $page->addError(__('There was an error saving your settings. Please try again.'));
            }
        }
    }

    // Get current settings
    $providerSIN = $settingGateway->getSettingByScope($scope, 'providerSIN') ?? '';
    $providerName = $settingGateway->getSettingByScope($scope, 'providerName') ?? '';
    $providerAddress = $settingGateway->getSettingByScope($scope, 'providerAddress') ?? '';
    $providerNEQ = $settingGateway->getSettingByScope($scope, 'providerNEQ') ?? '';
    $invoicePrefix = $settingGateway->getSettingByScope($scope, 'invoicePrefix') ?? 'INV-';
    $gstRate = $settingGateway->getSettingByScope($scope, 'gstRate') ?? '0.05';
    $qstRate = $settingGateway->getSettingByScope($scope, 'qstRate') ?? '0.09975';
    $defaultPaymentTermsDays = $settingGateway->getSettingByScope($scope, 'defaultPaymentTermsDays') ?? '30';
    $rl24FilingDeadline = $settingGateway->getSettingByScope($scope, 'rl24FilingDeadline') ?? '02-28';

    // Build the settings form
    $form = Form::create('financeSettings', $session->get('absoluteURL') . '/index.php?q=/modules/EnhancedFinance/finance_settings.php');
    $form->setClass('w-full');

    // Provider Information Section
    $form->addRow()->addHeading(__('Provider Information'), __('Information used for Quebec RL-24 tax documents'));

    $row = $form->addRow();
    $row->addLabel('providerName', __('Provider Name'))
        ->description(__('Name of the childcare provider/organization for RL-24 documents'));
    $row->addTextField('providerName')
        ->setValue($providerName)
        ->maxLength(100);

    $row = $form->addRow();
    $row->addLabel('providerSIN', __('Provider SIN'))
        ->description(__('Social Insurance Number for RL-24 Box H (format: XXX-XXX-XXX)'));
    $row->addTextField('providerSIN')
        ->setValue($providerSIN)
        ->maxLength(11)
        ->placeholder('XXX-XXX-XXX');

    $row = $form->addRow();
    $row->addLabel('providerNEQ', __('Provider NEQ'))
        ->description(__('Quebec Enterprise Number (NEQ) of the childcare provider'));
    $row->addTextField('providerNEQ')
        ->setValue($providerNEQ)
        ->maxLength(20);

    $row = $form->addRow();
    $row->addLabel('providerAddress', __('Provider Address'))
        ->description(__('Full address of the childcare provider for RL-24 documents'));
    $row->addTextArea('providerAddress')
        ->setValue($providerAddress)
        ->setRows(3);

    // Tax Settings Section
    $form->addRow()->addHeading(__('Tax Settings'), __('Quebec GST and QST tax rates for invoicing'));

    $row = $form->addRow();
    $row->addLabel('gstRate', __('GST Rate'))
        ->description(__('Goods and Services Tax rate as decimal (e.g., 0.05 for 5%)'));
    $row->addNumber('gstRate')
        ->setValue($gstRate)
        ->decimalPlaces(5)
        ->minimum(0)
        ->maximum(1);

    $row = $form->addRow();
    $row->addLabel('qstRate', __('QST Rate'))
        ->description(__('Quebec Sales Tax rate as decimal (e.g., 0.09975 for 9.975%)'));
    $row->addNumber('qstRate')
        ->setValue($qstRate)
        ->decimalPlaces(5)
        ->minimum(0)
        ->maximum(1);

    // Invoice Settings Section
    $form->addRow()->addHeading(__('Invoice Settings'), __('Default settings for invoice generation'));

    $row = $form->addRow();
    $row->addLabel('invoicePrefix', __('Invoice Number Prefix'))
        ->description(__('Prefix for generated invoice numbers (e.g., INV-)'));
    $row->addTextField('invoicePrefix')
        ->setValue($invoicePrefix)
        ->maxLength(20);

    $row = $form->addRow();
    $row->addLabel('defaultPaymentTermsDays', __('Default Payment Terms (Days)'))
        ->description(__('Default number of days for payment due date from invoice date'));
    $row->addNumber('defaultPaymentTermsDays')
        ->setValue($defaultPaymentTermsDays)
        ->decimalPlaces(0)
        ->minimum(0)
        ->maximum(365);

    // RL-24 Settings Section
    $form->addRow()->addHeading(__('RL-24 Settings'), __('Quebec Relevé 24 tax document settings'));

    $row = $form->addRow();
    $row->addLabel('rl24FilingDeadline', __('RL-24 Filing Deadline'))
        ->description(__('Default filing deadline for RL-24 slips (MM-DD format, e.g., 02-28)'));
    $row->addTextField('rl24FilingDeadline')
        ->setValue($rl24FilingDeadline)
        ->maxLength(5)
        ->placeholder('MM-DD');

    // Submit button
    $row = $form->addRow();
    $row->addFooter();
    $row->addSubmit();

    echo $form->getOutput();

    // Information box about settings
    echo '<div class="mt-8 p-4 bg-blue-50 border border-blue-200 rounded-lg">';
    echo '<h4 class="font-semibold text-blue-800 mb-2">' . __('About Finance Settings') . '</h4>';
    echo '<ul class="list-disc list-inside text-blue-700 space-y-1">';
    echo '<li>' . __('Provider information is used when generating Quebec RL-24 tax documents.') . '</li>';
    echo '<li>' . __('Tax rates are applied to invoice calculations for GST and QST.') . '</li>';
    echo '<li>' . __('Invoice prefix is used when generating new invoice numbers.') . '</li>';
    echo '<li>' . __('RL-24 filing deadline is the last day of February following the tax year.') . '</li>';
    echo '</ul>';
    echo '</div>';

    // Quebec RL-24 Compliance Notice
    echo '<div class="mt-4 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">';
    echo '<h4 class="font-semibold text-yellow-800 mb-2">' . __('Quebec RL-24 Compliance') . '</h4>';
    echo '<p class="text-yellow-700">' . __('Important: Ensure provider SIN and NEQ are accurate before generating RL-24 documents. These fields are required for Quebec regulatory compliance. RL-24 slips must be issued to parents by the end of February following the tax year.') . '</p>';
    echo '</div>';

    // Link back to finance home
    echo '<div class="mt-4">';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/EnhancedFinance/finance.php" class="text-blue-600 hover:underline">&larr; ' . __('Back to Enhanced Finance') . '</a>';
    echo '</div>';
}
