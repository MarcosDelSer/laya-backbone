<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuiber and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Gibbon Core) and Gibbon LAYA are trademarks of Gibbon Education Ltd.

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

if (isActionAccessible($guid, $connection2, '/modules/StaffManagement/staffManagement_settings.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs->add(__('Staff Management Settings'));

    // Get settings gateway
    $settingGateway = $container->get(SettingGateway::class);
    $scope = 'Staff Management';

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $success = true;
        $errors = [];

        // Validate ratio values (must be positive integers)
        $ratioFields = ['ratioInfant', 'ratioToddler', 'ratioPreschool', 'ratioSchoolAge'];
        foreach ($ratioFields as $field) {
            $value = isset($_POST[$field]) ? intval($_POST[$field]) : 0;
            if ($value < 1 || $value > 50) {
                $errors[] = sprintf(__('Invalid value for %s. Must be between 1 and 50.'), $field);
                $success = false;
            }
        }

        // Validate certification expiry warning days
        $warningDays = isset($_POST['certificationExpiryWarningDays']) ? intval($_POST['certificationExpiryWarningDays']) : 0;
        if ($warningDays < 1 || $warningDays > 365) {
            $errors[] = __('Certification expiry warning days must be between 1 and 365.');
            $success = false;
        }

        // Validate required certifications (sanitize the list)
        $requiredCertifications = isset($_POST['requiredCertifications']) ? trim($_POST['requiredCertifications']) : '';

        // Validate enableAuditLog
        $enableAuditLog = isset($_POST['enableAuditLog']) && $_POST['enableAuditLog'] === 'Y' ? 'Y' : 'N';

        if ($success) {
            // Prepare settings to update
            $settings = [
                'certificationExpiryWarningDays' => strval($warningDays),
                'requiredCertifications' => $requiredCertifications,
                'ratioInfant' => strval(intval($_POST['ratioInfant'])),
                'ratioToddler' => strval(intval($_POST['ratioToddler'])),
                'ratioPreschool' => strval(intval($_POST['ratioPreschool'])),
                'ratioSchoolAge' => strval(intval($_POST['ratioSchoolAge'])),
                'enableAuditLog' => $enableAuditLog,
            ];

            // Update each setting in the database
            foreach ($settings as $name => $value) {
                try {
                    $data = ['value' => $value, 'scope' => $scope, 'name' => $name];
                    $sql = "UPDATE gibbonSetting SET value = :value WHERE scope = :scope AND name = :name";
                    $stmt = $connection2->prepare($sql);
                    $result = $stmt->execute($data);

                    if (!$result || $stmt->rowCount() === 0) {
                        // Try to check if the setting exists
                        $checkSql = "SELECT COUNT(*) FROM gibbonSetting WHERE scope = :scope AND name = :name";
                        $checkStmt = $connection2->prepare($checkSql);
                        $checkStmt->execute(['scope' => $scope, 'name' => $name]);
                        if ($checkStmt->fetchColumn() == 0) {
                            $errors[] = sprintf(__('Setting %s does not exist.'), $name);
                            $success = false;
                        }
                    }
                } catch (\PDOException $e) {
                    $errors[] = sprintf(__('Error updating %s: %s'), $name, $e->getMessage());
                    $success = false;
                }
            }

            if ($success) {
                $page->addMessage(__('Settings have been saved successfully.'));
            } else {
                foreach ($errors as $error) {
                    $page->addError($error);
                }
            }
        } else {
            foreach ($errors as $error) {
                $page->addError($error);
            }
        }
    }

    // Get current settings values
    $certificationExpiryWarningDays = $settingGateway->getSettingByScope($scope, 'certificationExpiryWarningDays') ?? '30';
    $requiredCertifications = $settingGateway->getSettingByScope($scope, 'requiredCertifications') ?? 'Criminal Background Check,Child Abuse Registry,First Aid,CPR';
    $ratioInfant = $settingGateway->getSettingByScope($scope, 'ratioInfant') ?? '5';
    $ratioToddler = $settingGateway->getSettingByScope($scope, 'ratioToddler') ?? '8';
    $ratioPreschool = $settingGateway->getSettingByScope($scope, 'ratioPreschool') ?? '10';
    $ratioSchoolAge = $settingGateway->getSettingByScope($scope, 'ratioSchoolAge') ?? '20';
    $enableAuditLog = $settingGateway->getSettingByScope($scope, 'enableAuditLog') ?? 'Y';

    // Create settings form
    $form = Form::create('staffManagementSettings', $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_settings.php');
    $form->setClass('w-full');

    // Quebec Ratio Settings Section
    $form->addRow()->addHeading(__('Quebec Staff-to-Child Ratio Thresholds'), __('Configure the required staff-to-child ratios for compliance monitoring'));

    $row = $form->addRow();
    $row->addContent('<div class="text-sm text-gray-600 mb-4">' .
        __('These ratios define the maximum number of children per staff member for each age group, as per Quebec childcare regulations.') .
        '</div>');

    $row = $form->addRow();
    $row->addLabel('ratioInfant', __('Infant Ratio (0-18 months)'))
        ->description(__('Maximum children per staff member for infants'));
    $row->addNumber('ratioInfant')
        ->setValue($ratioInfant)
        ->minimum(1)
        ->maximum(50)
        ->required();

    $row = $form->addRow();
    $row->addLabel('ratioToddler', __('Toddler Ratio (18-36 months)'))
        ->description(__('Maximum children per staff member for toddlers'));
    $row->addNumber('ratioToddler')
        ->setValue($ratioToddler)
        ->minimum(1)
        ->maximum(50)
        ->required();

    $row = $form->addRow();
    $row->addLabel('ratioPreschool', __('Preschool Ratio (36-60 months)'))
        ->description(__('Maximum children per staff member for preschoolers'));
    $row->addNumber('ratioPreschool')
        ->setValue($ratioPreschool)
        ->minimum(1)
        ->maximum(50)
        ->required();

    $row = $form->addRow();
    $row->addLabel('ratioSchoolAge', __('School Age Ratio (60+ months)'))
        ->description(__('Maximum children per staff member for school-age children'));
    $row->addNumber('ratioSchoolAge')
        ->setValue($ratioSchoolAge)
        ->minimum(1)
        ->maximum(50)
        ->required();

    // Display current Quebec regulation info
    $row = $form->addRow();
    $row->addContent('<div class="bg-blue-50 border border-blue-200 rounded p-4 mb-4">' .
        '<h4 class="font-semibold text-blue-800">' . __('Quebec Regulation Reference') . '</h4>' .
        '<ul class="list-disc list-inside text-sm text-blue-700">' .
        '<li>' . __('Infants (0-18 months): 1 staff per 5 children (1:5)') . '</li>' .
        '<li>' . __('Toddlers (18-36 months): 1 staff per 8 children (1:8)') . '</li>' .
        '<li>' . __('Preschoolers (36-60 months): 1 staff per 10 children (1:10)') . '</li>' .
        '<li>' . __('School Age (60+ months): 1 staff per 20 children (1:20)') . '</li>' .
        '</ul>' .
        '</div>');

    // Certification Settings Section
    $form->addRow()->addHeading(__('Certification Settings'), __('Configure certification tracking and notification preferences'));

    $row = $form->addRow();
    $row->addLabel('certificationExpiryWarningDays', __('Expiry Warning Days'))
        ->description(__('Number of days before certification expiry to send warning notifications'));
    $row->addNumber('certificationExpiryWarningDays')
        ->setValue($certificationExpiryWarningDays)
        ->minimum(1)
        ->maximum(365)
        ->required();

    $row = $form->addRow();
    $row->addLabel('requiredCertifications', __('Required Certifications'))
        ->description(__('Comma-separated list of certification types required for all staff members'));
    $row->addTextArea('requiredCertifications')
        ->setValue($requiredCertifications)
        ->setRows(3)
        ->required();

    // Available certification types info
    $row = $form->addRow();
    $row->addContent('<div class="bg-yellow-50 border border-yellow-200 rounded p-4 mb-4">' .
        '<h4 class="font-semibold text-yellow-800">' . __('Available Certification Types') . '</h4>' .
        '<p class="text-sm text-yellow-700">' .
        __('Use these certification type names in the required list:') .
        '</p>' .
        '<ul class="list-disc list-inside text-sm text-yellow-700">' .
        '<li>Criminal Background Check</li>' .
        '<li>Child Abuse Registry</li>' .
        '<li>First Aid</li>' .
        '<li>CPR</li>' .
        '<li>Early Childhood Education</li>' .
        '<li>Other</li>' .
        '</ul>' .
        '</div>');

    // Audit Settings Section
    $form->addRow()->addHeading(__('Audit Settings'), __('Configure audit trail for staff record modifications'));

    $row = $form->addRow();
    $row->addLabel('enableAuditLog', __('Enable Audit Log'))
        ->description(__('Track all modifications to staff records for compliance and security'));
    $row->addYesNo('enableAuditLog')
        ->selected($enableAuditLog)
        ->required();

    $row = $form->addRow();
    $row->addContent('<div class="text-sm text-gray-600 mb-4">' .
        __('When enabled, all changes to staff profiles, certifications, and sensitive data are logged with timestamp, user, and change details.') .
        '</div>');

    // Submit button
    $row = $form->addRow();
    $row->addSubmit(__('Save Settings'));

    echo $form->getOutput();

    // Help section
    echo '<h2>' . __('About Staff Management Settings') . '</h2>';
    echo '<div class="message">';

    echo '<h4>' . __('Ratio Compliance') . '</h4>';
    echo '<p>' . __('The ratio thresholds determine when the system will flag staffing levels as non-compliant. The system continuously monitors the staff-to-child ratio and alerts administrators when ratios exceed the configured maximums.') . '</p>';

    echo '<h4>' . __('Certification Tracking') . '</h4>';
    echo '<p>' . __('The certification settings control how the system tracks and notifies about staff certifications:') . '</p>';
    echo '<ul>';
    echo '<li>' . __('Expiry warnings are sent to administrators and the affected staff member') . '</li>';
    echo '<li>' . __('Required certifications are checked during compliance reports') . '</li>';
    echo '<li>' . __('Missing or expired certifications are highlighted on staff profiles') . '</li>';
    echo '</ul>';

    echo '<h4>' . __('Audit Trail') . '</h4>';
    echo '<p>' . __('The audit log records all changes to staff records including:') . '</p>';
    echo '<ul>';
    echo '<li>' . __('Profile updates (personal info, employment details, banking)') . '</li>';
    echo '<li>' . __('Certification additions, modifications, and deletions') . '</li>';
    echo '<li>' . __('Schedule changes and time tracking adjustments') . '</li>';
    echo '<li>' . __('Disciplinary record entries and modifications') . '</li>';
    echo '</ul>';
    echo '<p>' . __('Audit logs are stored for regulatory compliance and can be exported for audits.') . '</p>';

    echo '</div>';
}
