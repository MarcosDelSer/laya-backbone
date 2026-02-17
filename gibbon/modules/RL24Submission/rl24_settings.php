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
use Gibbon\Domain\System\SettingGateway;

if (isActionAccessible($guid, $connection2, '/modules/RL24Submission/rl24_settings.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs->add(__('RL-24 Settings'));

    // Get the setting gateway
    $settingGateway = $container->get(SettingGateway::class);
    $scope = 'RL-24 Submission';

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $success = true;
        $settingsToUpdate = [
            'preparerNumber',
            'providerName',
            'providerNEQ',
            'providerAddress',
            'providerCity',
            'providerPostalCode',
            'xmlOutputPath',
            'autoCalculateDays',
            'requireSINValidation',
            'documentRetentionYears',
        ];

        foreach ($settingsToUpdate as $settingName) {
            $value = $_POST[$settingName] ?? '';

            // Clean up specific values
            if ($settingName === 'providerNEQ') {
                // Remove any non-numeric characters from NEQ
                $value = preg_replace('/[^0-9]/', '', $value);
            } elseif ($settingName === 'providerPostalCode') {
                // Uppercase and format postal code
                $value = strtoupper(trim($value));
            } elseif ($settingName === 'preparerNumber') {
                // Remove any non-numeric characters from preparer number
                $value = preg_replace('/[^0-9]/', '', $value);
            }

            // Update the setting in the database
            try {
                $data = ['value' => $value];
                $sql = "UPDATE gibbonSetting SET value = :value WHERE scope = :scope AND name = :name";
                $result = $connection2->prepare($sql);
                $result->execute([
                    'value' => $value,
                    'scope' => $scope,
                    'name' => $settingName,
                ]);

                // If the setting doesn't exist, insert it
                if ($result->rowCount() === 0) {
                    $sql = "INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value) VALUES (:scope, :name, :nameDisplay, :description, :value)";
                    $result = $connection2->prepare($sql);
                    $result->execute([
                        'scope' => $scope,
                        'name' => $settingName,
                        'nameDisplay' => ucwords(str_replace(['_', 'NEQ'], [' ', 'NEQ'], preg_replace('/([A-Z])/', ' $1', $settingName))),
                        'description' => '',
                        'value' => $value,
                    ]);
                }
            } catch (PDOException $e) {
                $success = false;
            }
        }

        if ($success) {
            $page->addMessage(__('Your settings have been saved successfully.'));
        } else {
            $page->addError(__('There was an error saving your settings. Please try again.'));
        }
    }

    // Get current settings
    $preparerNumber = $settingGateway->getSettingByScope($scope, 'preparerNumber');
    $providerName = $settingGateway->getSettingByScope($scope, 'providerName');
    $providerNEQ = $settingGateway->getSettingByScope($scope, 'providerNEQ');
    $providerAddress = $settingGateway->getSettingByScope($scope, 'providerAddress');
    $providerCity = $settingGateway->getSettingByScope($scope, 'providerCity');
    $providerPostalCode = $settingGateway->getSettingByScope($scope, 'providerPostalCode');
    $xmlOutputPath = $settingGateway->getSettingByScope($scope, 'xmlOutputPath');
    $autoCalculateDays = $settingGateway->getSettingByScope($scope, 'autoCalculateDays');
    $requireSINValidation = $settingGateway->getSettingByScope($scope, 'requireSINValidation');
    $documentRetentionYears = $settingGateway->getSettingByScope($scope, 'documentRetentionYears');

    // Set defaults if not set
    if (empty($xmlOutputPath)) {
        $xmlOutputPath = 'uploads/rl24/';
    }
    if (empty($autoCalculateDays)) {
        $autoCalculateDays = 'Y';
    }
    if (empty($requireSINValidation)) {
        $requireSINValidation = 'Y';
    }
    if (empty($documentRetentionYears)) {
        $documentRetentionYears = '7';
    }

    // Configuration status check
    $configComplete = !empty($providerName) && !empty($providerNEQ) && !empty($preparerNumber);
    $configWarnings = [];

    if (empty($providerName)) {
        $configWarnings[] = __('Provider name is required for RL-24 generation.');
    }
    if (empty($providerNEQ)) {
        $configWarnings[] = __('Provider NEQ is required for RL-24 generation.');
    } elseif (strlen($providerNEQ) !== 10) {
        $configWarnings[] = __('Provider NEQ must be exactly 10 digits.');
        $configComplete = false;
    }
    if (empty($preparerNumber)) {
        $configWarnings[] = __('Preparer number is required for RL-24 generation.');
    }

    // Display configuration status
    echo '<div class="bg-white border rounded-lg p-4 mb-6">';
    echo '<h4 class="font-semibold text-gray-800 mb-3">' . __('Configuration Status') . '</h4>';

    if ($configComplete) {
        echo '<div class="flex items-center gap-2 mb-3">';
        echo '<span class="tag success">' . __('Configuration Complete') . '</span>';
        echo '<span class="text-sm text-gray-600">' . __('All required settings are configured. You can now generate RL-24 batches.') . '</span>';
        echo '</div>';
    } else {
        echo '<div class="flex items-center gap-2 mb-3">';
        echo '<span class="tag error">' . __('Configuration Incomplete') . '</span>';
        echo '</div>';
        echo '<div class="bg-yellow-50 border border-yellow-200 rounded p-3 mb-3">';
        echo '<ul class="list-disc list-inside text-sm text-yellow-800">';
        foreach ($configWarnings as $warning) {
            echo '<li>' . $warning . '</li>';
        }
        echo '</ul>';
        echo '</div>';
    }
    echo '</div>';

    // Create the settings form
    $form = Form::create('rl24Settings', $session->get('absoluteURL') . '/index.php?q=/modules/RL24Submission/rl24_settings.php');
    $form->setTitle(__('RL-24 Module Settings'));
    $form->setDescription(__('Configure the settings for RL-24 tax slip generation and submission to Revenu Quebec.'));
    $form->setClass('w-full');

    // Provider Information Section
    $form->addRow()->addHeading(__('Provider Information'), __('Official provider details that appear on RL-24 slips.'));

    $row = $form->addRow();
        $row->addLabel('providerName', __('Provider Name'))
            ->description(__('Official name of the childcare provider as registered with Revenu Quebec.'));
        $row->addTextField('providerName')
            ->setValue($providerName)
            ->maxLength(100)
            ->required();

    $row = $form->addRow();
        $row->addLabel('providerNEQ', __('Provider NEQ'))
            ->description(__('Quebec Enterprise Number (10 digits). Format: 1234567890'));
        $row->addTextField('providerNEQ')
            ->setValue($providerNEQ)
            ->maxLength(10)
            ->required();

    $row = $form->addRow();
        $row->addLabel('providerAddress', __('Provider Address'))
            ->description(__('Street address for the provider (street number and name).'));
        $row->addTextField('providerAddress')
            ->setValue($providerAddress)
            ->maxLength(100);

    $row = $form->addRow();
        $row->addLabel('providerCity', __('Provider City'))
            ->description(__('City where the provider is located.'));
        $row->addTextField('providerCity')
            ->setValue($providerCity)
            ->maxLength(60);

    $row = $form->addRow();
        $row->addLabel('providerPostalCode', __('Provider Postal Code'))
            ->description(__('Postal code in Canadian format (e.g., H2X 1Y4).'));
        $row->addTextField('providerPostalCode')
            ->setValue($providerPostalCode)
            ->maxLength(7);

    // Preparer Information Section
    $form->addRow()->addHeading(__('Preparer Information'), __('Identification for the person/organization preparing the RL-24 slips.'));

    $row = $form->addRow();
        $row->addLabel('preparerNumber', __('Preparer Number'))
            ->description(__('Revenu Quebec preparer identification number (up to 6 digits). This number appears in the XML filename.'));
        $row->addTextField('preparerNumber')
            ->setValue($preparerNumber)
            ->maxLength(6)
            ->required();

    // Technical Settings Section
    $form->addRow()->addHeading(__('Technical Settings'), __('System configuration options.'));

    $row = $form->addRow();
        $row->addLabel('xmlOutputPath', __('XML Output Path'))
            ->description(__('Directory path where generated XML files will be stored (relative to Gibbon uploads folder).'));
        $row->addTextField('xmlOutputPath')
            ->setValue($xmlOutputPath)
            ->maxLength(255);

    // Processing Options Section
    $form->addRow()->addHeading(__('Processing Options'), __('Options that affect how RL-24 slips are processed.'));

    $row = $form->addRow();
        $row->addLabel('autoCalculateDays', __('Auto-calculate Days'))
            ->description(__('Automatically calculate attendance days from CareTracking data when generating slips.'));
        $row->addYesNo('autoCalculateDays')
            ->selected($autoCalculateDays);

    $row = $form->addRow();
        $row->addLabel('requireSINValidation', __('Require SIN Validation'))
            ->description(__('Validate Social Insurance Number format before generating slips. Invalid SINs will prevent slip generation.'));
        $row->addYesNo('requireSINValidation')
            ->selected($requireSINValidation);

    $row = $form->addRow();
        $row->addLabel('documentRetentionYears', __('Document Retention Years'))
            ->description(__('Number of years to retain FO-0601 eligibility documents before they can be purged.'));
        $row->addSelect('documentRetentionYears')
            ->fromArray(['5' => '5', '6' => '6', '7' => '7', '10' => '10', '15' => '15'])
            ->selected($documentRetentionYears);

    // Submit button
    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit(__('Save Settings'));

    echo $form->getOutput();

    // Information about RL-24 requirements
    echo '<div class="message mt-6">';
    echo '<h4>' . __('About RL-24 Requirements') . '</h4>';
    echo '<div class="space-y-3 text-sm">';

    echo '<div>';
    echo '<strong>' . __('NEQ (Quebec Enterprise Number)') . '</strong>';
    echo '<p class="text-gray-600">' . __('The NEQ is a unique 10-digit identifier assigned by the Registraire des entreprises du Quebec. It is required for all businesses and organizations operating in Quebec.') . '</p>';
    echo '</div>';

    echo '<div>';
    echo '<strong>' . __('Preparer Number') . '</strong>';
    echo '<p class="text-gray-600">' . __('The preparer number identifies the person or organization that prepares the RL-24 slips. This number is included in the XML filename format: AAPPPPPPSSS.xml (AA=year, PPPPPP=preparer number, SSS=sequence).') . '</p>';
    echo '</div>';

    echo '<div>';
    echo '<strong>' . __('XML File Format') . '</strong>';
    echo '<p class="text-gray-600">' . __('Generated XML files follow Revenu Quebec specifications. Each file can contain up to 1,000 slips. Files are validated against the government schema before submission.') . '</p>';
    echo '</div>';

    echo '<div>';
    echo '<strong>' . __('Document Retention') . '</strong>';
    echo '<p class="text-gray-600">' . __('Quebec tax regulations require that supporting documents be retained for a minimum of 6 years after the tax year. We recommend setting retention to 7 years or longer.') . '</p>';
    echo '</div>';

    echo '</div>';
    echo '</div>';

    // Links to related pages
    echo '<div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mt-6">';
    echo '<h4 class="font-semibold text-gray-800 mb-3">' . __('Related Pages') . '</h4>';
    echo '<ul class="space-y-2 text-sm">';
    echo '<li><a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/RL24Submission/rl24_transmissions.php" class="text-blue-600 hover:underline">' . __('RL-24 Transmissions') . '</a> - ' . __('View and generate RL-24 batch transmissions') . '</li>';
    echo '<li><a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/RL24Submission/rl24_eligibility.php" class="text-blue-600 hover:underline">' . __('FO-0601 Eligibility Forms') . '</a> - ' . __('Manage eligibility forms for childcare tax credits') . '</li>';
    echo '<li><a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/RL24Submission/rl24_slips.php" class="text-blue-600 hover:underline">' . __('RL-24 Slips') . '</a> - ' . __('View individual RL-24 slips') . '</li>';
    echo '</ul>';
    echo '</div>';
}
