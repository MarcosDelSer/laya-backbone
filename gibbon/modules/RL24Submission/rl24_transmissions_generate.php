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

use Gibbon\Forms\Form;
use Gibbon\Services\Format;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Module\RL24Submission\Domain\RL24TransmissionGateway;
use Gibbon\Module\RL24Submission\Domain\RL24EligibilityGateway;
use Gibbon\Module\RL24Submission\Domain\RL24SlipGateway;

if (isActionAccessible($guid, $connection2, '/modules/RL24Submission/rl24_transmissions.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs->add(__('RL-24 Transmissions'), 'rl24_transmissions.php');
    $page->breadcrumbs->add(__('Generate New Batch'));

    // Get dependencies from container
    $gibbonSchoolYearID = $session->get('gibbonSchoolYearID');
    $gibbonPersonID = $session->get('gibbonPersonID');

    $settingGateway = $container->get(SettingGateway::class);
    $transmissionGateway = $container->get(RL24TransmissionGateway::class);
    $eligibilityGateway = $container->get(RL24EligibilityGateway::class);
    $slipGateway = $container->get(RL24SlipGateway::class);

    // Get selected tax year from request or default to current year
    $currentYear = (int) date('Y');
    $taxYear = isset($_GET['taxYear']) ? (int) $_GET['taxYear'] : $currentYear;

    // Get provider configuration
    $providerName = $settingGateway->getSettingByScope('RL24 Submission', 'providerName');
    $providerNEQ = $settingGateway->getSettingByScope('RL24 Submission', 'providerNEQ');
    $preparerNumber = $settingGateway->getSettingByScope('RL24 Submission', 'preparerNumber');
    $providerAddress = $settingGateway->getSettingByScope('RL24 Submission', 'providerAddress');
    $providerCity = $settingGateway->getSettingByScope('RL24 Submission', 'providerCity');
    $providerPostalCode = $settingGateway->getSettingByScope('RL24 Submission', 'providerPostalCode');

    // Check provider configuration
    $configComplete = !empty($providerName) && !empty($providerNEQ) && !empty($preparerNumber);
    $configWarnings = [];
    if (empty($providerName)) {
        $configWarnings[] = __('Provider name is not configured.');
    }
    if (empty($providerNEQ)) {
        $configWarnings[] = __('Provider NEQ is not configured.');
    } elseif (strlen(preg_replace('/[^0-9]/', '', $providerNEQ)) !== 10) {
        $configWarnings[] = __('Provider NEQ must be 10 digits.');
        $configComplete = false;
    }
    if (empty($preparerNumber)) {
        $configWarnings[] = __('Preparer number is not configured.');
    }

    // Year selection form
    $form = Form::create('selectYear', $session->get('absoluteURL') . '/index.php', 'get');
    $form->setTitle(__('Select Tax Year'));
    $form->setClass('noIntBorder fullWidth');

    $form->addHiddenValue('q', '/modules/RL24Submission/rl24_transmissions_generate.php');

    $yearOptions = [];
    for ($y = $currentYear; $y >= $currentYear - 3; $y--) {
        $yearOptions[$y] = $y;
    }

    $row = $form->addRow();
        $row->addLabel('taxYear', __('Tax Year'))->description(__('Select the tax year to generate RL-24 slips for.'));
        $row->addSelect('taxYear')
            ->fromArray($yearOptions)
            ->selected($taxYear)
            ->required();

    $row = $form->addRow();
        $row->addSubmit(__('Load Preview'));

    echo $form->getOutput();

    // Provider Configuration Section
    echo '<div class="bg-white border border-gray-200 rounded-lg p-4 mb-6">';
    echo '<h4 class="font-semibold text-gray-800 mb-3">' . __('Provider Configuration') . '</h4>';

    if ($configComplete) {
        echo '<div class="flex items-center gap-2 mb-3">';
        echo '<span class="tag success">' . __('Configuration Complete') . '</span>';
        echo '</div>';
    } else {
        echo '<div class="flex items-center gap-2 mb-3">';
        echo '<span class="tag error">' . __('Configuration Incomplete') . '</span>';
        echo '</div>';
        if (!empty($configWarnings)) {
            echo '<ul class="list-disc list-inside text-sm text-red-600 mb-3">';
            foreach ($configWarnings as $warning) {
                echo '<li>' . $warning . '</li>';
            }
            echo '</ul>';
        }
        echo '<p class="text-sm text-gray-600">';
        echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/RL24Submission/rl24_settings.php" class="underline">';
        echo __('Configure provider settings');
        echo '</a></p>';
    }

    echo '<div class="grid grid-cols-2 md:grid-cols-3 gap-4 text-sm mt-3">';
    echo '<div><span class="text-gray-500">' . __('Provider Name:') . '</span><br><span class="font-semibold">' . ($providerName ?: '-') . '</span></div>';
    echo '<div><span class="text-gray-500">' . __('NEQ:') . '</span><br><span class="font-semibold">';
    if (!empty($providerNEQ)) {
        $formattedNEQ = substr($providerNEQ, 0, 4) . ' ' . substr($providerNEQ, 4, 3) . ' ' . substr($providerNEQ, 7, 3);
        echo $formattedNEQ;
    } else {
        echo '-';
    }
    echo '</span></div>';
    echo '<div><span class="text-gray-500">' . __('Preparer Number:') . '</span><br><span class="font-semibold">' . ($preparerNumber ?: '-') . '</span></div>';
    echo '</div>';
    echo '</div>';

    // Get eligibility summary for the selected year
    $eligibilitySummary = $eligibilityGateway->getEligibilitySummaryByFormYear($taxYear);

    // Get next sequence number and calculate existing transmissions count
    $nextSequenceNumber = $transmissionGateway->getNextSequenceNumber($taxYear);
    $maxSequenceNumber = $nextSequenceNumber - 1;

    // Build query criteria for counting transmissions by tax year
    $transmissionCriteria = $transmissionGateway->newQueryCriteria()
        ->filterBy('taxYear', $taxYear);
    $transmissionsForYear = $transmissionGateway->queryTransmissions($transmissionCriteria, $gibbonSchoolYearID);
    $totalTransmissions = $transmissionsForYear->count();

    // Calculate slips that would be generated
    $eligibilityForms = $eligibilityGateway->selectApprovedEligibilityByFormYear($taxYear)->fetchAll();
    $existingSlipCount = 0;
    $newSlipCount = 0;

    foreach ($eligibilityForms as $form) {
        if ($slipGateway->slipExistsForChildAndYear($form['gibbonPersonIDChild'], $taxYear)) {
            $existingSlipCount++;
        } else {
            $newSlipCount++;
        }
    }

    // Eligibility Summary Section
    echo '<div class="bg-white border border-gray-200 rounded-lg p-4 mb-6">';
    echo '<h4 class="font-semibold text-gray-800 mb-3">' . __('Eligibility Summary for Tax Year') . ' ' . $taxYear . '</h4>';

    echo '<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">';

    // Total Forms
    echo '<div class="bg-gray-50 border border-gray-200 rounded-lg p-3 text-center">';
    echo '<div class="text-2xl font-bold text-gray-600">' . ($eligibilitySummary['totalForms'] ?? 0) . '</div>';
    echo '<div class="text-xs text-gray-700">' . __('Total Forms') . '</div>';
    echo '</div>';

    // Approved
    echo '<div class="bg-green-50 border border-green-200 rounded-lg p-3 text-center">';
    echo '<div class="text-2xl font-bold text-green-600">' . ($eligibilitySummary['approvedCount'] ?? 0) . '</div>';
    echo '<div class="text-xs text-green-700">' . __('Approved') . '</div>';
    echo '</div>';

    // Pending
    echo '<div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 text-center">';
    echo '<div class="text-2xl font-bold text-yellow-600">' . ($eligibilitySummary['pendingCount'] ?? 0) . '</div>';
    echo '<div class="text-xs text-yellow-700">' . __('Pending Review') . '</div>';
    echo '</div>';

    // Documents Complete
    echo '<div class="bg-blue-50 border border-blue-200 rounded-lg p-3 text-center">';
    echo '<div class="text-2xl font-bold text-blue-600">' . ($eligibilitySummary['documentsCompleteCount'] ?? 0) . '</div>';
    echo '<div class="text-xs text-blue-700">' . __('Documents Complete') . '</div>';
    echo '</div>';

    echo '</div>';

    // Warnings about pending forms
    if (($eligibilitySummary['pendingCount'] ?? 0) > 0) {
        echo '<div class="bg-yellow-50 border border-yellow-200 rounded p-3 text-sm text-yellow-800 mb-3">';
        echo '<strong>' . __('Note:') . '</strong> ';
        echo sprintf(__('There are %d eligibility forms pending review. Only approved forms will be included in the RL-24 batch.'), $eligibilitySummary['pendingCount']);
        echo '</div>';
    }

    echo '</div>';

    // Generation Preview Section
    echo '<div class="bg-white border border-gray-200 rounded-lg p-4 mb-6">';
    echo '<h4 class="font-semibold text-gray-800 mb-3">' . __('Generation Preview') . '</h4>';

    echo '<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">';

    // Existing Slips
    echo '<div class="bg-gray-50 border border-gray-200 rounded-lg p-3 text-center">';
    echo '<div class="text-2xl font-bold text-gray-600">' . $existingSlipCount . '</div>';
    echo '<div class="text-xs text-gray-700">' . __('Existing Slips') . '</div>';
    echo '</div>';

    // New Slips
    echo '<div class="bg-indigo-50 border border-indigo-200 rounded-lg p-3 text-center">';
    echo '<div class="text-2xl font-bold text-indigo-600">' . $newSlipCount . '</div>';
    echo '<div class="text-xs text-indigo-700">' . __('New Slips to Generate') . '</div>';
    echo '</div>';

    // Previous Transmissions
    echo '<div class="bg-purple-50 border border-purple-200 rounded-lg p-3 text-center">';
    echo '<div class="text-2xl font-bold text-purple-600">' . $totalTransmissions . '</div>';
    echo '<div class="text-xs text-purple-700">' . __('Previous Transmissions') . '</div>';
    echo '</div>';

    // Next Sequence Number
    $nextSequence = $nextSequenceNumber;
    echo '<div class="bg-teal-50 border border-teal-200 rounded-lg p-3 text-center">';
    echo '<div class="text-2xl font-bold text-teal-600">' . str_pad($nextSequence, 3, '0', STR_PAD_LEFT) . '</div>';
    echo '<div class="text-xs text-teal-700">' . __('Next Sequence') . '</div>';
    echo '</div>';

    echo '</div>';

    // Show expected filename
    if ($configComplete && !empty($preparerNumber)) {
        $yearCode = substr($taxYear, 2, 2);
        $expectedFilename = $yearCode . str_pad($preparerNumber, 6, '0', STR_PAD_LEFT) . str_pad($nextSequence, 3, '0', STR_PAD_LEFT) . '.xml';
        echo '<div class="text-sm text-gray-600 mb-3">';
        echo '<span class="text-gray-500">' . __('Expected filename:') . '</span> ';
        echo '<code class="bg-gray-100 px-2 py-1 rounded">' . $expectedFilename . '</code>';
        echo '</div>';
    }

    echo '</div>';

    // Can we generate?
    $canGenerate = $configComplete && $newSlipCount > 0;
    $generateErrors = [];

    if (!$configComplete) {
        $generateErrors[] = __('Provider configuration is incomplete. Please configure all required settings.');
    }

    if ($newSlipCount === 0) {
        if (($eligibilitySummary['approvedCount'] ?? 0) === 0) {
            $generateErrors[] = __('No approved eligibility forms found for this tax year.');
        } else {
            $generateErrors[] = __('All approved eligibility forms already have RL-24 slips generated.');
        }
    }

    // Generation Form
    echo '<div class="bg-white border border-gray-200 rounded-lg p-4 mb-6">';
    echo '<h4 class="font-semibold text-gray-800 mb-3">' . __('Generate RL-24 Batch') . '</h4>';

    if (!empty($generateErrors)) {
        echo '<div class="bg-red-50 border border-red-200 rounded p-3 mb-4">';
        echo '<ul class="list-disc list-inside text-sm text-red-700">';
        foreach ($generateErrors as $error) {
            echo '<li>' . $error . '</li>';
        }
        echo '</ul>';
        echo '</div>';
    }

    if ($canGenerate) {
        echo '<p class="text-sm text-gray-600 mb-4">';
        echo sprintf(
            __('Ready to generate %d new RL-24 slips for tax year %d. This will create individual slip records and generate an XML file for submission to Revenu Quebec.'),
            $newSlipCount,
            $taxYear
        );
        echo '</p>';

        // Generate form
        $generateForm = Form::create('generateBatch', $session->get('absoluteURL') . '/modules/RL24Submission/rl24_transmissions_generateProcess.php');
        $generateForm->setClass('noIntBorder fullWidth');

        $generateForm->addHiddenValue('address', $session->get('address'));
        $generateForm->addHiddenValue('taxYear', $taxYear);
        $generateForm->addHiddenValue('gibbonSchoolYearID', $gibbonSchoolYearID);

        $row = $generateForm->addRow();
            $row->addLabel('notes', __('Batch Notes'))->description(__('Optional notes for this transmission batch.'));
            $row->addTextArea('notes')
                ->setRows(2);

        $row = $generateForm->addRow();
            $row->addLabel('confirm', __('Confirm Generation'))
                ->description(__('I confirm that all eligibility data has been reviewed and is accurate.'));
            $row->addCheckbox('confirm')
                ->description(__('Yes, proceed with batch generation'))
                ->required();

        $row = $generateForm->addRow();
            $row->addFooter();
            $row->addSubmit(__('Generate Batch'));

        echo $generateForm->getOutput();
    } else {
        echo '<p class="text-sm text-gray-500">';
        echo __('Please resolve the issues above before generating a new batch.');
        echo '</p>';
    }

    echo '</div>';

    // Eligible Children Preview
    if ($newSlipCount > 0 && count($eligibilityForms) > 0) {
        echo '<div class="bg-white border border-gray-200 rounded-lg p-4 mb-6">';
        echo '<h4 class="font-semibold text-gray-800 mb-3">' . __('Eligible Children Preview') . ' (' . $newSlipCount . ')</h4>';

        echo '<div class="overflow-x-auto">';
        echo '<table class="w-full text-sm">';
        echo '<thead class="bg-gray-50">';
        echo '<tr>';
        echo '<th class="px-3 py-2 text-left font-medium text-gray-600">' . __('Child') . '</th>';
        echo '<th class="px-3 py-2 text-left font-medium text-gray-600">' . __('Parent/Guardian') . '</th>';
        echo '<th class="px-3 py-2 text-left font-medium text-gray-600">' . __('Service Period') . '</th>';
        echo '<th class="px-3 py-2 text-center font-medium text-gray-600">' . __('Status') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody class="divide-y divide-gray-100">';

        $displayCount = 0;
        $maxDisplay = 10;
        foreach ($eligibilityForms as $form) {
            // Skip existing slips
            if ($slipGateway->slipExistsForChildAndYear($form['gibbonPersonIDChild'], $taxYear)) {
                continue;
            }

            $displayCount++;
            if ($displayCount > $maxDisplay) {
                break;
            }

            echo '<tr class="hover:bg-gray-50">';

            // Child
            echo '<td class="px-3 py-2">';
            echo '<span class="font-medium">' . htmlspecialchars($form['childFirstName'] . ' ' . $form['childLastName']) . '</span>';
            if (!empty($form['childDateOfBirth'])) {
                echo '<br><span class="text-xs text-gray-500">DOB: ' . Format::date($form['childDateOfBirth']) . '</span>';
            }
            echo '</td>';

            // Parent
            echo '<td class="px-3 py-2">';
            echo htmlspecialchars($form['parentFirstName'] . ' ' . $form['parentLastName']);
            echo '</td>';

            // Service Period
            echo '<td class="px-3 py-2">';
            $start = !empty($form['servicePeriodStart']) ? Format::date($form['servicePeriodStart']) : '-';
            $end = !empty($form['servicePeriodEnd']) ? Format::date($form['servicePeriodEnd']) : '-';
            echo $start . ' - ' . $end;
            echo '</td>';

            // Status
            echo '<td class="px-3 py-2 text-center">';
            echo '<span class="tag success">' . __('Ready') . '</span>';
            echo '</td>';

            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
        echo '</div>';

        if ($newSlipCount > $maxDisplay) {
            echo '<p class="text-sm text-gray-500 mt-3 text-center">';
            echo sprintf(__('... and %d more children'), $newSlipCount - $maxDisplay);
            echo '</p>';
        }

        echo '</div>';
    }

    // Information box
    echo '<div class="message">';
    echo '<h4>' . __('About RL-24 Batch Generation') . '</h4>';
    echo '<ul class="list-disc list-inside space-y-1 text-sm">';
    echo '<li>' . __('Only approved FO-0601 eligibility forms will be included in the batch.') . '</li>';
    echo '<li>' . __('Children who already have an RL-24 slip for this tax year will be skipped.') . '</li>';
    echo '<li>' . __('The XML file will be named following Revenu Quebec format: AAPPPPPPSSS.xml') . '</li>';
    echo '<li>' . __('After generation, review the slips and download the XML for submission.') . '</li>';
    echo '<li>' . __('Maximum 1,000 slips per transmission file.') . '</li>';
    echo '</ul>';
    echo '</div>';
}
