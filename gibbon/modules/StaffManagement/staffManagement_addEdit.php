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
use Gibbon\Module\StaffManagement\Domain\StaffProfileGateway;
use Gibbon\Module\StaffManagement\Domain\AuditLogGateway;

// Module setup - breadcrumbs
$page->breadcrumbs->add(__('Staff Management'), 'staffManagement.php');
$page->breadcrumbs->add(__('Staff Profiles'), 'staffManagement_profile.php');

// Determine if we're adding or editing
$gibbonStaffProfileID = $_GET['gibbonStaffProfileID'] ?? null;
$mode = !empty($gibbonStaffProfileID) ? 'edit' : 'add';

if ($mode === 'edit') {
    $page->breadcrumbs->add(__('Edit Staff Profile'));
} else {
    $page->breadcrumbs->add(__('Add Staff Profile'));
}

// Access check
if (!isActionAccessible($guid, $connection2, '/modules/StaffManagement/staffManagement_addEdit.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get gateways via DI container
    $staffProfileGateway = $container->get(StaffProfileGateway::class);
    $auditLogGateway = $container->get(AuditLogGateway::class);

    // Get current user info
    $gibbonPersonID = $session->get('gibbonPersonID');

    // Initialize staff profile data
    $staffProfile = [];

    // If editing, load existing profile
    if ($mode === 'edit') {
        $staffProfile = $staffProfileGateway->getStaffProfileByID($gibbonStaffProfileID);

        if (empty($staffProfile)) {
            $page->addError(__('The specified staff profile does not exist.'));
            return;
        }
    }

    // Province options for Canada
    $provinces = [
        '' => __('Select...'),
        'AB' => __('Alberta'),
        'BC' => __('British Columbia'),
        'MB' => __('Manitoba'),
        'NB' => __('New Brunswick'),
        'NL' => __('Newfoundland and Labrador'),
        'NS' => __('Nova Scotia'),
        'NT' => __('Northwest Territories'),
        'NU' => __('Nunavut'),
        'ON' => __('Ontario'),
        'PE' => __('Prince Edward Island'),
        'QC' => __('Quebec'),
        'SK' => __('Saskatchewan'),
        'YT' => __('Yukon'),
    ];

    // Employment type options
    $employmentTypes = [
        'Full-Time' => __('Full-Time'),
        'Part-Time' => __('Part-Time'),
        'Casual' => __('Casual'),
        'Contract' => __('Contract'),
        'Substitute' => __('Substitute'),
    ];

    // Status options
    $statusOptions = [
        'Active' => __('Active'),
        'Inactive' => __('Inactive'),
        'On Leave' => __('On Leave'),
        'Terminated' => __('Terminated'),
    ];

    // Qualification level options (Quebec compliance)
    $qualificationLevels = [
        '' => __('Select...'),
        'Unqualified' => __('Unqualified'),
        'Level 1' => __('Level 1'),
        'Level 2' => __('Level 2'),
        'Level 3' => __('Level 3'),
        'Director' => __('Director'),
    ];

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Collect form data
        $gibbonPersonIDInput = $_POST['gibbonPersonID'] ?? null;

        $formData = [
            'employeeNumber' => !empty($_POST['employeeNumber']) ? $_POST['employeeNumber'] : null,
            'sin' => !empty($_POST['sin']) ? $_POST['sin'] : null,
            'address' => !empty($_POST['address']) ? $_POST['address'] : null,
            'city' => !empty($_POST['city']) ? $_POST['city'] : null,
            'province' => !empty($_POST['province']) ? $_POST['province'] : null,
            'postalCode' => !empty($_POST['postalCode']) ? $_POST['postalCode'] : null,
            'position' => $_POST['position'] ?? '',
            'department' => !empty($_POST['department']) ? $_POST['department'] : null,
            'employmentType' => $_POST['employmentType'] ?? 'Full-Time',
            'hireDate' => !empty($_POST['hireDate']) ? Format::dateConvert($_POST['hireDate']) : null,
            'terminationDate' => !empty($_POST['terminationDate']) ? Format::dateConvert($_POST['terminationDate']) : null,
            'probationEndDate' => !empty($_POST['probationEndDate']) ? Format::dateConvert($_POST['probationEndDate']) : null,
            'status' => $_POST['status'] ?? 'Active',
            'qualificationLevel' => !empty($_POST['qualificationLevel']) ? $_POST['qualificationLevel'] : null,
            'insuranceProvider' => !empty($_POST['insuranceProvider']) ? $_POST['insuranceProvider'] : null,
            'insurancePolicyNumber' => !empty($_POST['insurancePolicyNumber']) ? $_POST['insurancePolicyNumber'] : null,
            'groupInsuranceEnrolled' => $_POST['groupInsuranceEnrolled'] ?? 'N',
            'bankInstitution' => !empty($_POST['bankInstitution']) ? $_POST['bankInstitution'] : null,
            'bankTransit' => !empty($_POST['bankTransit']) ? $_POST['bankTransit'] : null,
            'bankAccount' => !empty($_POST['bankAccount']) ? $_POST['bankAccount'] : null,
            'notes' => !empty($_POST['notes']) ? $_POST['notes'] : null,
        ];

        // Validate required fields
        $errors = [];

        if (empty($formData['position'])) {
            $errors[] = __('Position is a required field.');
        }

        if ($mode === 'add') {
            if (empty($gibbonPersonIDInput)) {
                $errors[] = __('Please select a person to create a staff profile for.');
            } else {
                // Check if person already has a staff profile
                if ($staffProfileGateway->hasStaffProfile($gibbonPersonIDInput)) {
                    $errors[] = __('This person already has a staff profile.');
                }
            }
        }

        if (!empty($errors)) {
            foreach ($errors as $error) {
                $page->addError($error);
            }
        } else {
            if ($mode === 'add') {
                // Add new staff profile
                $insertData = array_merge($formData, [
                    'gibbonPersonID' => $gibbonPersonIDInput,
                    'createdByID' => $gibbonPersonID,
                    'timestampCreated' => date('Y-m-d H:i:s'),
                    'timestampModified' => date('Y-m-d H:i:s'),
                ]);

                $newProfileID = $staffProfileGateway->insert($insertData);

                if ($newProfileID !== false) {
                    // Log the creation for audit trail
                    $auditLogGateway->logInsert(
                        'gibbonStaffProfile',
                        $newProfileID,
                        json_encode($insertData),
                        $gibbonPersonID,
                        $session->get('gibbonPersonID')
                    );

                    $page->addSuccess(__('Staff profile has been created successfully.'));

                    // Redirect to the profile view
                    header('Location: ' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_profile.php&gibbonStaffProfileID=' . $newProfileID . '&mode=view');
                    exit;
                } else {
                    $page->addError(__('Failed to create staff profile.'));
                }
            } else {
                // Update existing profile
                $formData['timestampModified'] = date('Y-m-d H:i:s');

                // Store old values for audit
                $oldValues = $staffProfile;

                $updated = $staffProfileGateway->update($gibbonStaffProfileID, $formData);

                if ($updated !== false) {
                    // Log the change for audit trail
                    $auditLogGateway->logUpdate(
                        'gibbonStaffProfile',
                        $gibbonStaffProfileID,
                        json_encode($oldValues),
                        json_encode(array_merge($oldValues, $formData)),
                        $gibbonPersonID,
                        $session->get('gibbonPersonID')
                    );

                    $page->addSuccess(__('Staff profile has been updated successfully.'));

                    // Redirect to the profile view
                    header('Location: ' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_profile.php&gibbonStaffProfileID=' . $gibbonStaffProfileID . '&mode=view');
                    exit;
                } else {
                    $page->addError(__('Failed to update staff profile.'));
                }
            }
        }
    }

    // Display page header
    if ($mode === 'edit') {
        $staffName = Format::name('', $staffProfile['preferredName'], $staffProfile['surname'], 'Staff', true, true);
        echo '<h2>' . __('Edit Staff Profile') . ': ' . $staffName . '</h2>';
    } else {
        echo '<h2>' . __('Add Staff Profile') . '</h2>';
    }

    // Create the form
    $formAction = $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_addEdit.php';
    if ($mode === 'edit') {
        $formAction .= '&gibbonStaffProfileID=' . $gibbonStaffProfileID;
    }

    $form = Form::create('staffProfileForm', $formAction);
    $form->setMethod('post');

    // Person Selection (only for add mode)
    if ($mode === 'add') {
        $form->addRow()->addHeading(__('Select Person'));

        // Get all staff members from gibbonPerson who don't already have a staff profile
        $sql = "SELECT gibbonPerson.gibbonPersonID as value,
                       CONCAT(gibbonPerson.surname, ', ', gibbonPerson.preferredName, ' (', gibbonPerson.username, ')') as name
                FROM gibbonPerson
                LEFT JOIN gibbonStaffProfile ON gibbonPerson.gibbonPersonID = gibbonStaffProfile.gibbonPersonID
                WHERE gibbonPerson.status = 'Full'
                AND gibbonStaffProfile.gibbonStaffProfileID IS NULL
                ORDER BY gibbonPerson.surname, gibbonPerson.preferredName";

        $availablePersons = $pdo->select($sql)->fetchAll(\PDO::FETCH_KEY_PAIR);

        $row = $form->addRow();
        $row->addLabel('gibbonPersonID', __('Person'))->description(__('Required'));
        $row->addSelect('gibbonPersonID')
            ->fromArray(['' => __('Select Person...')] + $availablePersons)
            ->required()
            ->selected($_POST['gibbonPersonID'] ?? '');

        $row = $form->addRow();
        $row->addContent('<p class="text-sm text-gray-600">' . __('Only persons without existing staff profiles are shown.') . '</p>');
    } else {
        // Display person info for edit mode
        $image = !empty($staffProfile['image_240']) ? $staffProfile['image_240'] : 'themes/Default/img/anonymous_240.jpg';

        echo '<div class="bg-white rounded-lg shadow p-4 mb-4">';
        echo '<div class="flex items-center gap-4">';
        echo '<img src="' . $session->get('absoluteURL') . '/' . $image . '" class="w-16 h-16 rounded-full object-cover" alt="">';
        echo '<div>';
        echo '<span class="font-semibold text-lg">' . Format::name('', $staffProfile['preferredName'], $staffProfile['surname'], 'Staff', true, true) . '</span>';
        echo '<br><span class="text-gray-500">' . htmlspecialchars($staffProfile['email'] ?? '') . '</span>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    // Personal Information Section
    $form->addRow()->addHeading(__('Personal Information'));

    $row = $form->addRow();
    $row->addLabel('employeeNumber', __('Employee Number'));
    $row->addTextField('employeeNumber')
        ->setValue($staffProfile['employeeNumber'] ?? $_POST['employeeNumber'] ?? '')
        ->maxLength(50);

    $row = $form->addRow();
    $row->addLabel('sin', __('Social Insurance Number'))
        ->description(__('This information is kept confidential.'));
    $row->addTextField('sin')
        ->setValue($staffProfile['sin'] ?? $_POST['sin'] ?? '')
        ->maxLength(20);

    // Address Section
    $form->addRow()->addHeading(__('Address'));

    $row = $form->addRow();
    $row->addLabel('address', __('Street Address'));
    $row->addTextArea('address')
        ->setValue($staffProfile['address'] ?? $_POST['address'] ?? '')
        ->setRows(2);

    $row = $form->addRow();
    $row->addLabel('city', __('City'));
    $row->addTextField('city')
        ->setValue($staffProfile['city'] ?? $_POST['city'] ?? '')
        ->maxLength(100);

    $row = $form->addRow();
    $row->addLabel('province', __('Province'));
    $row->addSelect('province')
        ->fromArray($provinces)
        ->selected($staffProfile['province'] ?? $_POST['province'] ?? '');

    $row = $form->addRow();
    $row->addLabel('postalCode', __('Postal Code'));
    $row->addTextField('postalCode')
        ->setValue($staffProfile['postalCode'] ?? $_POST['postalCode'] ?? '')
        ->maxLength(20);

    // Employment Information Section
    $form->addRow()->addHeading(__('Employment Information'));

    $row = $form->addRow();
    $row->addLabel('position', __('Position'))->description(__('Required'));
    $row->addTextField('position')
        ->setValue($staffProfile['position'] ?? $_POST['position'] ?? '')
        ->maxLength(100)
        ->required();

    $row = $form->addRow();
    $row->addLabel('department', __('Department'));
    $row->addTextField('department')
        ->setValue($staffProfile['department'] ?? $_POST['department'] ?? '')
        ->maxLength(100);

    $row = $form->addRow();
    $row->addLabel('employmentType', __('Employment Type'));
    $row->addSelect('employmentType')
        ->fromArray($employmentTypes)
        ->selected($staffProfile['employmentType'] ?? $_POST['employmentType'] ?? 'Full-Time');

    $row = $form->addRow();
    $row->addLabel('status', __('Status'));
    $row->addSelect('status')
        ->fromArray($statusOptions)
        ->selected($staffProfile['status'] ?? $_POST['status'] ?? 'Active');

    // Employment Dates Section
    $form->addRow()->addHeading(__('Employment Dates'));

    $row = $form->addRow();
    $row->addLabel('hireDate', __('Hire Date'));
    $row->addDate('hireDate')
        ->setValue(!empty($staffProfile['hireDate']) ? Format::date($staffProfile['hireDate']) : ($_POST['hireDate'] ?? ''));

    $row = $form->addRow();
    $row->addLabel('probationEndDate', __('Probation End Date'));
    $row->addDate('probationEndDate')
        ->setValue(!empty($staffProfile['probationEndDate']) ? Format::date($staffProfile['probationEndDate']) : ($_POST['probationEndDate'] ?? ''));

    $row = $form->addRow();
    $row->addLabel('terminationDate', __('Termination Date'));
    $row->addDate('terminationDate')
        ->setValue(!empty($staffProfile['terminationDate']) ? Format::date($staffProfile['terminationDate']) : ($_POST['terminationDate'] ?? ''));

    // Qualifications Section
    $form->addRow()->addHeading(__('Qualifications'))
        ->append('<p class="text-sm text-gray-600 mt-1">' . __('Quebec daycare qualification levels for ratio compliance.') . '</p>');

    $row = $form->addRow();
    $row->addLabel('qualificationLevel', __('Qualification Level'));
    $row->addSelect('qualificationLevel')
        ->fromArray($qualificationLevels)
        ->selected($staffProfile['qualificationLevel'] ?? $_POST['qualificationLevel'] ?? '');

    // Insurance Information Section
    $form->addRow()->addHeading(__('Insurance Information'));

    $row = $form->addRow();
    $row->addLabel('insuranceProvider', __('Insurance Provider'));
    $row->addTextField('insuranceProvider')
        ->setValue($staffProfile['insuranceProvider'] ?? $_POST['insuranceProvider'] ?? '')
        ->maxLength(100);

    $row = $form->addRow();
    $row->addLabel('insurancePolicyNumber', __('Policy Number'));
    $row->addTextField('insurancePolicyNumber')
        ->setValue($staffProfile['insurancePolicyNumber'] ?? $_POST['insurancePolicyNumber'] ?? '')
        ->maxLength(100);

    $row = $form->addRow();
    $row->addLabel('groupInsuranceEnrolled', __('Group Insurance Enrolled'));
    $row->addYesNo('groupInsuranceEnrolled')
        ->selected($staffProfile['groupInsuranceEnrolled'] ?? $_POST['groupInsuranceEnrolled'] ?? 'N');

    // Banking Information Section
    $form->addRow()->addHeading(__('Banking Information (Payroll)'))
        ->append('<p class="text-sm text-gray-600 mt-1">' . __('For direct deposit payroll. This information is kept confidential.') . '</p>');

    $row = $form->addRow();
    $row->addLabel('bankInstitution', __('Institution Number'))
        ->description(__('3-digit institution number'));
    $row->addTextField('bankInstitution')
        ->setValue($staffProfile['bankInstitution'] ?? $_POST['bankInstitution'] ?? '')
        ->maxLength(10);

    $row = $form->addRow();
    $row->addLabel('bankTransit', __('Transit Number'))
        ->description(__('5-digit transit/branch number'));
    $row->addTextField('bankTransit')
        ->setValue($staffProfile['bankTransit'] ?? $_POST['bankTransit'] ?? '')
        ->maxLength(10);

    $row = $form->addRow();
    $row->addLabel('bankAccount', __('Account Number'));
    $row->addTextField('bankAccount')
        ->setValue($staffProfile['bankAccount'] ?? $_POST['bankAccount'] ?? '')
        ->maxLength(20);

    // Notes Section
    $form->addRow()->addHeading(__('Internal HR Notes'));

    $row = $form->addRow();
    $row->addLabel('notes', __('Notes'))
        ->description(__('Internal HR notes. Not visible to the staff member.'));
    $row->addTextArea('notes')
        ->setValue($staffProfile['notes'] ?? $_POST['notes'] ?? '')
        ->setRows(4);

    // Submit buttons
    $row = $form->addRow();
    $row->addFooter();
    $row->addSubmit($mode === 'edit' ? __('Save Changes') : __('Create Staff Profile'));

    echo $form->getOutput();

    // Cancel link
    echo '<div class="mt-4">';
    if ($mode === 'edit') {
        echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_profile.php&gibbonStaffProfileID=' . $gibbonStaffProfileID . '&mode=view" class="text-gray-600 hover:underline">' . __('Cancel') . '</a>';
    } else {
        echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_profile.php" class="text-gray-600 hover:underline">' . __('Cancel') . '</a>';
    }
    echo '</div>';

    // Link back to dashboard
    echo '<div class="mt-4">';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement.php" class="text-blue-600 hover:underline">&larr; ' . __('Back to Dashboard') . '</a>';
    echo '</div>';
}
