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
 * Enhanced Finance Module - Batch RL-24 Generation
 *
 * Allows batch generation of Quebec RL-24 (Relevé 24) tax slips for multiple
 * children at once. Displays eligible children with payments in the tax year
 * and allows staff to select children for batch generation.
 *
 * @package    Gibbon\Module\EnhancedFinance
 * @author     LAYA
 * @license    GPL-3.0
 */

use Gibbon\Forms\Form;
use Gibbon\Services\Format;
use Gibbon\Tables\DataTable;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Module\EnhancedFinance\Releve24;
use Gibbon\Module\EnhancedFinance\Domain\Releve24Gateway;
use Gibbon\Module\EnhancedFinance\Domain\InvoiceGateway;
use Gibbon\Module\EnhancedFinance\Domain\PaymentGateway;

// Access check
if (isActionAccessible($guid, $connection2, '/modules/EnhancedFinance/finance_releve24_batch.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs
        ->add(__('Enhanced Finance'), 'finance.php')
        ->add(__('Manage RL-24'), 'finance_releve24.php')
        ->add(__('Batch Generate'));

    // Return messages
    $page->return->addReturns([
        'success0' => __('Your request was completed successfully.'),
        'success1' => __('RL-24 slips generated successfully.'),
        'error1' => __('There was an error generating the RL-24 slips.'),
        'error2' => __('No children were selected for generation.'),
        'error3' => __('Required provider configuration is missing.'),
        'error4' => __('Please select a valid tax year.'),
    ]);

    // Get gateways and services
    $releve24Gateway = $container->get(Releve24Gateway::class);
    $invoiceGateway = $container->get(InvoiceGateway::class);
    $paymentGateway = $container->get(PaymentGateway::class);
    $settingGateway = $container->get(SettingGateway::class);

    // Initialize Releve24 business logic class
    $releve24Service = new Releve24(
        $container->get('db'),
        $settingGateway,
        $invoiceGateway,
        $paymentGateway,
        $releve24Gateway
    );

    // Get current user ID for created by
    $gibbonPersonID = $session->get('gibbonPersonID');

    // Default tax year to previous year (RL-24 typically generated in new year for previous year)
    $currentYear = (int) date('Y');
    $defaultTaxYear = (date('n') <= 2) ? $currentYear - 1 : $currentYear;

    // Get tax year from request
    $taxYear = isset($_GET['taxYear']) ? (int) $_GET['taxYear'] : $defaultTaxYear;

    // Validate tax year range
    if ($taxYear < 2000 || $taxYear > $currentYear + 1) {
        $taxYear = $defaultTaxYear;
    }

    // Check provider configuration
    $missingConfig = $releve24Service->validateProviderConfiguration();

    if (!empty($missingConfig)) {
        echo '<div class="warning">';
        echo '<h3>' . __('Configuration Required') . '</h3>';
        echo '<p>' . __('Before generating RL-24 slips, the following provider information must be configured:') . '</p>';
        echo '<ul class="list-disc list-inside">';
        foreach ($missingConfig as $field) {
            echo '<li>' . htmlspecialchars($field) . '</li>';
        }
        echo '</ul>';
        echo '<p class="mt-2">';
        echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/EnhancedFinance/finance_settings.php" class="text-blue-600 hover:underline">';
        echo __('Go to Finance Settings') . ' &rarr;';
        echo '</a>';
        echo '</p>';
        echo '</div>';
    }

    // Description
    echo '<p>';
    echo __('Use this page to generate RL-24 tax slips for multiple children at once. Select the tax year, review the eligible children (those with payments in that year), and check the boxes next to the children you want to generate slips for.');
    echo '</p>';

    // Tax year options (last 5 years + available from database)
    $taxYearOptions = [];
    for ($year = $currentYear; $year >= $currentYear - 5; $year--) {
        $taxYearOptions[$year] = $year;
    }

    // Get additional years from database
    $availableYears = $releve24Gateway->selectAvailableTaxYears();
    foreach ($availableYears as $yearRow) {
        $taxYearOptions[$yearRow['taxYear']] = $yearRow['taxYear'];
    }
    krsort($taxYearOptions);

    // Build filter form for tax year selection
    $form = Form::create('batchFilters', $session->get('absoluteURL') . '/index.php', 'get');
    $form->setTitle(__('Select Tax Year'));
    $form->setClass('noIntBorder w-full');

    $form->addHiddenValue('q', '/modules/EnhancedFinance/finance_releve24_batch.php');

    $row = $form->addRow();
        $row->addLabel('taxYear', __('Tax Year'));
        $row->addSelect('taxYear')
            ->fromArray($taxYearOptions)
            ->selected($taxYear)
            ->required();

    $row = $form->addRow();
        $row->addSubmit(__('Load Eligible Children'));

    echo $form->getOutput();

    // Handle batch generation POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'batchGenerate') {
        $selectedChildren = $_POST['selectedChildren'] ?? [];
        $postTaxYear = (int) ($_POST['taxYear'] ?? $taxYear);

        if (empty($selectedChildren)) {
            $page->addError(__('No children were selected for generation.'));
        } elseif (!empty($missingConfig)) {
            $page->addError(__('Required provider configuration is missing. Please configure provider settings first.'));
        } else {
            // Process batch generation
            $successCount = 0;
            $errorCount = 0;
            $skippedCount = 0;

            foreach ($selectedChildren as $selection) {
                // Selection format: personID|familyID
                $parts = explode('|', $selection);
                if (count($parts) !== 2) {
                    $errorCount++;
                    continue;
                }

                $childPersonID = (int) $parts[0];
                $childFamilyID = (int) $parts[1];

                try {
                    // Check if RL-24 already exists for this child and year
                    $existing = $releve24Gateway->selectReleve24ByChildAndYear($childPersonID, $postTaxYear, 'R');

                    if (!empty($existing) && in_array($existing['status'], ['Generated', 'Sent', 'Filed'])) {
                        // Skip if already has a valid slip
                        $skippedCount++;
                        continue;
                    }

                    // Generate and save RL-24
                    $result = $releve24Service->generateAndSaveReleve24(
                        $childPersonID,
                        $childFamilyID,
                        $postTaxYear,
                        $gibbonPersonID
                    );

                    if ($result !== false) {
                        $successCount++;
                    } else {
                        $errorCount++;
                    }
                } catch (\Exception $e) {
                    $errorCount++;
                }
            }

            // Show results
            if ($successCount > 0) {
                $page->addSuccess(sprintf(__('%d RL-24 slip(s) generated successfully.'), $successCount));
            }
            if ($skippedCount > 0) {
                $page->addWarning(sprintf(__('%d child(ren) skipped (already have RL-24 for this year).'), $skippedCount));
            }
            if ($errorCount > 0) {
                $page->addError(sprintf(__('%d RL-24 slip(s) failed to generate.'), $errorCount));
            }
        }
    }

    // Get eligible children for the selected tax year
    $eligibleChildren = $releve24Service->getEligibleChildren($taxYear);

    // Get RL-24 summary statistics
    $summary = $releve24Service->getReleve24Summary($taxYear);

    // Summary display
    echo '<div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">';
    echo '<h3 class="text-lg font-semibold text-blue-800 mb-3">' . __('RL-24 Summary for Tax Year') . ' ' . $taxYear . '</h3>';
    echo '<div class="grid grid-cols-2 md:grid-cols-5 gap-4">';

    // Eligible Children
    echo '<div class="text-center bg-white rounded p-3">';
    echo '<div class="text-2xl font-bold text-blue-600">' . $summary['eligibleChildren'] . '</div>';
    echo '<div class="text-sm text-gray-500">' . __('Eligible Children') . '</div>';
    echo '</div>';

    // Generated Slips
    echo '<div class="text-center bg-white rounded p-3">';
    echo '<div class="text-2xl font-bold text-green-600">' . $summary['generatedSlips'] . '</div>';
    echo '<div class="text-sm text-gray-500">' . __('Generated Slips') . '</div>';
    echo '</div>';

    // Pending (Draft + Generated)
    $pendingCount = $summary['draftCount'] + $summary['generatedCount'];
    echo '<div class="text-center bg-white rounded p-3">';
    echo '<div class="text-2xl font-bold text-orange-600">' . $pendingCount . '</div>';
    echo '<div class="text-sm text-gray-500">' . __('Pending') . '</div>';
    echo '</div>';

    // Sent
    echo '<div class="text-center bg-white rounded p-3">';
    echo '<div class="text-2xl font-bold text-green-700">' . $summary['sentCount'] . '</div>';
    echo '<div class="text-sm text-gray-500">' . __('Sent') . '</div>';
    echo '</div>';

    // Total Amount Paid
    echo '<div class="text-center bg-white rounded p-3">';
    echo '<div class="text-xl font-bold text-purple-600">' . Format::currency($summary['totalAmountPaid']) . '</div>';
    echo '<div class="text-sm text-gray-500">' . __('Total Paid') . '</div>';
    echo '</div>';

    echo '</div>'; // End grid
    echo '</div>'; // End summary box

    // Eligible children section
    echo '<h3 class="text-lg font-semibold mt-6 mb-3">' . __('Eligible Children for Tax Year') . ' ' . $taxYear . '</h3>';

    if (count($eligibleChildren) === 0) {
        echo '<div class="bg-gray-50 rounded-lg p-4 text-center text-gray-500">';
        echo __('No children with payments found for this tax year.');
        echo '</div>';
    } else {
        // Batch generation form
        echo '<form method="post" action="' . $session->get('absoluteURL') . '/index.php?q=/modules/EnhancedFinance/finance_releve24_batch.php&taxYear=' . $taxYear . '">';
        echo '<input type="hidden" name="action" value="batchGenerate">';
        echo '<input type="hidden" name="taxYear" value="' . $taxYear . '">';

        // Select all controls
        echo '<div class="mb-4 flex items-center justify-between">';
        echo '<div>';
        echo '<label class="inline-flex items-center">';
        echo '<input type="checkbox" id="selectAll" class="mr-2">';
        echo '<span class="text-sm font-medium">' . __('Select All Pending') . '</span>';
        echo '</label>';
        echo '</div>';
        echo '<div>';
        echo '<button type="submit" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700" ' . (empty($missingConfig) ? '' : 'disabled') . '>';
        echo __('Generate Selected') . '</button>';
        echo '</div>';
        echo '</div>';

        // Children table
        echo '<div class="overflow-x-auto">';
        echo '<table class="w-full border-collapse">';
        echo '<thead>';
        echo '<tr class="bg-gray-100">';
        echo '<th class="p-2 text-left w-12">' . __('Select') . '</th>';
        echo '<th class="p-2 text-left">' . __('Child') . '</th>';
        echo '<th class="p-2 text-left">' . __('Family') . '</th>';
        echo '<th class="p-2 text-right">' . __('Total Paid') . '</th>';
        echo '<th class="p-2 text-center">' . __('Invoices') . '</th>';
        echo '<th class="p-2 text-center">' . __('Payments') . '</th>';
        echo '<th class="p-2 text-center">' . __('RL-24 Status') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        foreach ($eligibleChildren as $child) {
            $childName = Format::name('', $child['preferredName'] ?? '', $child['surname'] ?? '', 'Student', true);
            $selectionValue = $child['gibbonPersonID'] . '|' . $child['gibbonFamilyID'];
            $hasReleve24 = (int) ($child['hasReleve24'] ?? 0);

            // Determine row styling
            $rowClass = $hasReleve24 ? 'bg-green-50' : '';
            $isDisabled = $hasReleve24 ? 'disabled' : '';
            $checkboxClass = $hasReleve24 ? '' : 'batch-checkbox';

            echo '<tr class="border-b ' . $rowClass . '">';

            // Checkbox
            echo '<td class="p-2">';
            if (!$hasReleve24) {
                echo '<input type="checkbox" name="selectedChildren[]" value="' . htmlspecialchars($selectionValue) . '" class="' . $checkboxClass . '">';
            } else {
                echo '<span class="text-green-600" title="' . __('Already generated') . '">&#10003;</span>';
            }
            echo '</td>';

            // Child name
            echo '<td class="p-2">';
            echo '<div class="font-medium">' . htmlspecialchars($childName) . '</div>';
            if (!empty($child['dob'])) {
                echo '<div class="text-xs text-gray-500">' . __('DOB') . ': ' . Format::date($child['dob']) . '</div>';
            }
            echo '</td>';

            // Family
            echo '<td class="p-2">';
            echo htmlspecialchars($child['familyName'] ?? '');
            echo '</td>';

            // Total paid
            echo '<td class="p-2 text-right font-semibold text-green-700">';
            echo Format::currency($child['totalPaid'] ?? 0);
            echo '</td>';

            // Invoice count
            echo '<td class="p-2 text-center">';
            echo (int) ($child['invoiceCount'] ?? 0);
            echo '</td>';

            // Payment count
            echo '<td class="p-2 text-center">';
            echo (int) ($child['paymentCount'] ?? 0);
            echo '</td>';

            // RL-24 Status
            echo '<td class="p-2 text-center">';
            if ($hasReleve24) {
                echo '<span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded">' . __('Generated') . '</span>';
            } else {
                echo '<span class="bg-gray-100 text-gray-600 text-xs px-2 py-1 rounded">' . __('Pending') . '</span>';
            }
            echo '</td>';

            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
        echo '</div>';

        // Submit button at bottom
        echo '<div class="mt-4 flex justify-end">';
        echo '<button type="submit" class="bg-green-600 text-white px-6 py-2 rounded hover:bg-green-700" ' . (empty($missingConfig) ? '' : 'disabled') . '>';
        echo __('Generate RL-24 for Selected Children');
        echo '</button>';
        echo '</div>';

        echo '</form>';

        // JavaScript for select all functionality
        echo '<script>
            document.getElementById("selectAll").addEventListener("change", function() {
                var checkboxes = document.querySelectorAll(".batch-checkbox");
                checkboxes.forEach(function(checkbox) {
                    checkbox.checked = this.checked;
                }, this);
            });

            // Update select all when individual checkboxes change
            document.querySelectorAll(".batch-checkbox").forEach(function(checkbox) {
                checkbox.addEventListener("change", function() {
                    var allCheckboxes = document.querySelectorAll(".batch-checkbox");
                    var checkedCount = document.querySelectorAll(".batch-checkbox:checked").length;
                    document.getElementById("selectAll").checked = (checkedCount === allCheckboxes.length);
                    document.getElementById("selectAll").indeterminate = (checkedCount > 0 && checkedCount < allCheckboxes.length);
                });
            });
        </script>';
    }

    // Information notice about RL-24 batch generation
    echo '<div class="mt-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">';
    echo '<h4 class="font-semibold text-yellow-800 mb-2">' . __('Batch Generation Notes') . '</h4>';
    echo '<ul class="list-disc list-inside text-yellow-700 space-y-1">';
    echo '<li>' . __('Only children with payments in the selected tax year are shown.') . '</li>';
    echo '<li>' . __('Children who already have a generated RL-24 for this year are marked with a checkmark and cannot be selected.') . '</li>';
    echo '<li>' . __('RL-24 slips are generated in "Generated" status. You must send them separately.') . '</li>';
    echo '<li>' . __('If payments are received after generating, you may need to create an amended RL-24 (type A).') . '</li>';
    echo '<li>' . __('Ensure all provider information is configured in Finance Settings before generating.') . '</li>';
    echo '</ul>';
    echo '</div>';

    // Link back to RL-24 list
    echo '<div class="mt-4">';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/EnhancedFinance/finance_releve24.php&taxYear=' . $taxYear . '" class="text-blue-600 hover:underline">';
    echo '&larr; ' . __('Back to RL-24 List');
    echo '</a>';
    echo '</div>';
}
