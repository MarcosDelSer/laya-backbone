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
use Gibbon\Module\System\Domain\SetupWizardManager;
use Gibbon\Module\System\Domain\InstallationDetector;
use Gibbon\Domain\System\SettingGateway;

// Check user access permissions
if (isActionAccessible($guid, $connection2, '/modules/System/setup_wizard.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs->add(__('Setup Wizard'));

    // Initialize wizard dependencies
    $settingGateway = $container->get(SettingGateway::class);
    $installationDetector = new InstallationDetector($settingGateway, $pdo);
    $wizardManager = new SetupWizardManager($settingGateway, $pdo, $installationDetector);

    // Check if wizard is already completed
    if ($installationDetector->isWizardCompleted()) {
        $page->addMessage(__('The setup wizard has already been completed.'));
        echo '<div class="success">';
        echo '<p>' . __('Your daycare is already set up and ready to use.') . '</p>';
        echo '<p><a href="' . $session->get('absoluteURL') . '/index.php">' . __('Go to Dashboard') . '</a></p>';
        echo '</div>';
        return;
    }

    // Get current step
    $currentStep = $wizardManager->getCurrentStep();

    if (!$currentStep) {
        // No current step means wizard is complete
        $page->addMessage(__('Setup wizard completed successfully!'));
        echo '<div class="success">';
        echo '<p>' . __('Your daycare has been set up successfully.') . '</p>';
        echo '<p><a href="' . $session->get('absoluteURL') . '/index.php">' . __('Go to Dashboard') . '</a></p>';
        echo '</div>';
        return;
    }

    // Initialize variables
    $errors = [];
    $formData = $currentStep['data'] ?? [];
    $success = false;

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Get step class
        $stepClass = 'Gibbon\\Module\\System\\Domain\\' . $currentStep['class'];

        if (class_exists($stepClass)) {
            $step = new $stepClass($settingGateway, $pdo, $installationDetector);

            // Validate data
            $errors = $step->validate($_POST);

            if (empty($errors)) {
                try {
                    // Save data
                    $step->save($_POST);
                    $success = true;

                    // Check for navigation actions
                    if (isset($_POST['action'])) {
                        if ($_POST['action'] === 'next') {
                            // Move to next step
                            $nextStep = $wizardManager->getNextStep($currentStep['id']);
                            if ($nextStep) {
                                header("Location: " . $session->get('absoluteURL') . "/index.php?q=/modules/System/setup_wizard.php");
                                exit;
                            }
                        } elseif ($_POST['action'] === 'save_resume') {
                            // Save and resume later
                            $page->addMessage(__('Progress saved. You can resume the wizard later.'));
                        }
                    } else {
                        // Default: move to next step
                        $nextStep = $wizardManager->getNextStep($currentStep['id']);
                        if ($nextStep) {
                            header("Location: " . $session->get('absoluteURL') . "/index.php?q=/modules/System/setup_wizard.php");
                            exit;
                        }
                    }
                } catch (\Exception $e) {
                    $errors['_general'] = 'Failed to save data: ' . $e->getMessage();
                }
            }

            // Store form data for re-display
            $formData = $_POST;
        } else {
            $errors['_general'] = 'Step class not found: ' . $stepClass;
        }
    }

    // Display errors
    if (!empty($errors)) {
        echo '<div class="error">';
        echo '<h4>' . __('Please correct the following errors:') . '</h4>';
        echo '<ul>';
        foreach ($errors as $field => $error) {
            echo '<li>' . htmlspecialchars($error) . '</li>';
        }
        echo '</ul>';
        echo '</div>';
    }

    // Display success message
    if ($success && empty($errors)) {
        $page->addMessage(__('Step completed successfully.'));
    }

    // Calculate and display progress
    $completionPercentage = $wizardManager->getCompletionPercentage();

    echo '<div class="wizard-progress mb-6">';
    echo '<h3>' . __('Setup Progress') . ': ' . round($completionPercentage) . '%</h3>';
    echo '<div class="progress-bar" style="background-color: #e0e0e0; height: 30px; border-radius: 5px; overflow: hidden;">';
    echo '<div class="progress-bar-fill" style="background-color: #4CAF50; height: 100%; width: ' . round($completionPercentage) . '%; transition: width 0.3s;"></div>';
    echo '</div>';
    echo '</div>';

    // Display wizard steps navigation
    echo '<div class="wizard-steps mb-6">';
    echo '<div class="grid grid-cols-2 md:grid-cols-4 gap-2">';
    $allSteps = $wizardManager->getSteps();
    foreach ($allSteps as $stepId => $stepInfo) {
        $isCompleted = $wizardManager->isStepCompleted($stepId);
        $isCurrent = ($stepId === $currentStep['id']);
        $canAccess = $wizardManager->canAccessStep($stepId);

        $classes = 'step-indicator p-3 text-center rounded border-2 ';
        if ($isCurrent) {
            $classes .= 'border-blue-500 bg-blue-50';
        } elseif ($isCompleted) {
            $classes .= 'border-green-500 bg-green-50';
        } elseif ($canAccess) {
            $classes .= 'border-gray-300 bg-gray-50';
        } else {
            $classes .= 'border-gray-200 bg-gray-100 opacity-50';
        }

        echo '<div class="' . $classes . '">';
        echo '<div class="text-xs font-semibold">' . htmlspecialchars($stepInfo['name']) . '</div>';
        if ($isCompleted) {
            echo '<div class="text-green-600 text-lg">✓</div>';
        } elseif ($isCurrent) {
            echo '<div class="text-blue-600 text-lg">→</div>';
        }
        echo '</div>';
    }
    echo '</div>';
    echo '</div>';

    // Display current step title
    echo '<h2 class="text-2xl font-bold mb-4">' . htmlspecialchars($currentStep['name']) . '</h2>';

    // Create form based on current step
    $form = Form::create('wizardStep', $session->get('absoluteURL') . '/index.php?q=/modules/System/setup_wizard.php');
    $form->setClass('standardForm');

    // Render step-specific fields
    switch ($currentStep['id']) {
        case 'organization_info':
            $row = $form->addRow();
            $row->addLabel('name', __('Organization Name'))->description(__('Full legal name of your daycare'));
            $row->addTextField('name')->required()->maxLength(255)->setValue($formData['name'] ?? '');

            $row = $form->addRow();
            $row->addLabel('address', __('Address'))->description(__('Full street address'));
            $row->addTextArea('address')->required()->setRows(3)->setValue($formData['address'] ?? '');

            $row = $form->addRow();
            $row->addLabel('phone', __('Phone Number'))->description(__('Main contact phone'));
            $row->addTextField('phone')->required()->setValue($formData['phone'] ?? '');

            $row = $form->addRow();
            $row->addLabel('license_number', __('License Number'))->description(__('Government-issued daycare license number'));
            $row->addTextField('license_number')->required()->setValue($formData['license_number'] ?? '');

            $row = $form->addRow();
            $row->addLabel('email', __('Email'))->description(__('Primary contact email'));
            $row->addEmail('email')->setValue($formData['email'] ?? '');

            $row = $form->addRow();
            $row->addLabel('website', __('Website'))->description(__('Organization website (optional)'));
            $row->addURL('website')->setValue($formData['website'] ?? '');
            break;

        case 'admin_account':
            $row = $form->addRow();
            $row->addLabel('first_name', __('First Name'));
            $row->addTextField('first_name')->required()->setValue($formData['first_name'] ?? '');

            $row = $form->addRow();
            $row->addLabel('last_name', __('Last Name'));
            $row->addTextField('last_name')->required()->setValue($formData['last_name'] ?? '');

            $row = $form->addRow();
            $row->addLabel('email', __('Email'));
            $row->addEmail('email')->required()->setValue($formData['email'] ?? '');

            $row = $form->addRow();
            $row->addLabel('username', __('Username'))->description(__('Leave empty to auto-generate'));
            $row->addTextField('username')->setValue($formData['username'] ?? '');

            $row = $form->addRow();
            $row->addLabel('password', __('Password'))->description(__('Minimum 8 characters, must include uppercase, lowercase, number, and special character'));
            $row->addPassword('password')->required();

            $row = $form->addRow();
            $row->addLabel('password_confirm', __('Confirm Password'));
            $row->addPassword('password_confirm')->required();
            break;

        case 'operating_hours':
            $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
            foreach ($days as $day) {
                $row = $form->addRow();
                $row->addLabel($day . '_open', __(ucfirst($day) . ' Open'));
                $row->addTime($day . '_open')->setValue($formData[$day . '_open'] ?? '08:00');

                $row = $form->addRow();
                $row->addLabel($day . '_close', __(ucfirst($day) . ' Close'));
                $row->addTime($day . '_close')->setValue($formData[$day . '_close'] ?? '18:00');
            }

            $row = $form->addRow();
            $row->addLabel('timezone', __('Timezone'));
            $row->addSelectTimezone('timezone')->setValue($formData['timezone'] ?? 'America/Toronto');
            break;

        case 'groups_rooms':
            $row = $form->addRow();
            $row->addContent(__('<p>Add your daycare groups/rooms. You can add more later.</p>'));

            for ($i = 1; $i <= 3; $i++) {
                $row = $form->addRow();
                $row->addLabel("group_{$i}_name", __("Group {$i} Name"));
                $row->addTextField("group_{$i}_name")->setValue($formData["group_{$i}_name"] ?? '');

                $row = $form->addRow();
                $row->addLabel("group_{$i}_age_min", __("Group {$i} Min Age (years)"));
                $row->addNumber("group_{$i}_age_min")->setValue($formData["group_{$i}_age_min"] ?? 0);

                $row = $form->addRow();
                $row->addLabel("group_{$i}_age_max", __("Group {$i} Max Age (years)"));
                $row->addNumber("group_{$i}_age_max")->setValue($formData["group_{$i}_age_max"] ?? 5);

                $row = $form->addRow();
                $row->addLabel("group_{$i}_capacity", __("Group {$i} Capacity"));
                $row->addNumber("group_{$i}_capacity")->setValue($formData["group_{$i}_capacity"] ?? 20);
            }
            break;

        case 'finance_settings':
            $row = $form->addRow();
            $row->addLabel('currency', __('Currency'));
            $row->addSelect('currency')->fromArray(['USD' => 'USD', 'EUR' => 'EUR', 'GBP' => 'GBP', 'CAD' => 'CAD'])->setValue($formData['currency'] ?? 'USD');

            $row = $form->addRow();
            $row->addLabel('daily_rate', __('Default Daily Rate'));
            $row->addNumber('daily_rate')->setValue($formData['daily_rate'] ?? '50.00')->decimalPlaces(2);

            $row = $form->addRow();
            $row->addLabel('tax_number', __('Tax Number'));
            $row->addTextField('tax_number')->setValue($formData['tax_number'] ?? '');

            $row = $form->addRow();
            $row->addLabel('payment_terms', __('Payment Terms'));
            $row->addSelect('payment_terms')->fromArray([
                'immediate' => 'Immediate',
                'net7' => 'Net 7 days',
                'net15' => 'Net 15 days',
                'net30' => 'Net 30 days'
            ])->setValue($formData['payment_terms'] ?? 'net30');
            break;

        case 'service_connectivity':
            $row = $form->addRow();
            $row->addContent(__('<p>Checking service connectivity...</p>'));

            $row = $form->addRow();
            $row->addLabel('mysql_host', __('MySQL Host'));
            $row->addTextField('mysql_host')->setValue($formData['mysql_host'] ?? 'localhost');

            $row = $form->addRow();
            $row->addLabel('redis_enabled', __('Enable Redis'));
            $row->addCheckbox('redis_enabled')->setValue($formData['redis_enabled'] ?? '0');
            break;

        case 'sample_data':
            $row = $form->addRow();
            $row->addContent(__('<p>Optionally import sample data to help you get started.</p>'));

            $row = $form->addRow();
            $row->addLabel('import_students', __('Number of Sample Students'));
            $row->addNumber('import_students')->setValue($formData['import_students'] ?? 10);

            $row = $form->addRow();
            $row->addLabel('import_staff', __('Number of Sample Staff'));
            $row->addNumber('import_staff')->setValue($formData['import_staff'] ?? 5);
            break;

        case 'completion':
            $row = $form->addRow();
            $row->addContent(__('<h3>Congratulations!</h3><p>You have completed all required setup steps.</p>'));

            $row = $form->addRow();
            $row->addContent(__('<p>Click "Complete Wizard" to finalize your setup.</p>'));
            break;
    }

    // Add navigation buttons
    $row = $form->addRow();
    $col = $row->addColumn();
    $col->addContent('<div class="flex justify-between mt-6">');

    // Previous button
    $previousStep = $wizardManager->getPreviousStep($currentStep['id']);
    if ($previousStep) {
        $col->addButton(__('← Previous'))->onClick('history.back()')->setClass('btn-secondary');
    }

    // Save & Resume button
    $col->addSubmit(__('Save & Resume Later'))->setName('action')->setValue('save_resume')->setClass('btn-secondary');

    // Next button
    if ($currentStep['id'] === 'completion') {
        $col->addSubmit(__('Complete Wizard'))->setName('action')->setValue('next');
    } else {
        $col->addSubmit(__('Next →'))->setName('action')->setValue('next');
    }

    $col->addContent('</div>');

    echo $form->getOutput();
}
