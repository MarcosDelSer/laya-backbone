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
use Gibbon\Tables\DataTable;
use Gibbon\Services\Format;
use Gibbon\Module\StaffManagement\Domain\StaffProfileGateway;
use Gibbon\Module\StaffManagement\Domain\CertificationGateway;
use Gibbon\Module\StaffManagement\Domain\AuditLogGateway;

// Module setup - breadcrumbs
$page->breadcrumbs->add(__('Staff Management'), 'staffManagement.php');
$page->breadcrumbs->add(__('Staff Profiles'));

// Access check
if (!isActionAccessible($guid, $connection2, '/modules/StaffManagement/staffManagement_profile.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get gateways via DI container
    $staffProfileGateway = $container->get(StaffProfileGateway::class);
    $certificationGateway = $container->get(CertificationGateway::class);
    $auditLogGateway = $container->get(AuditLogGateway::class);

    // Get current user info
    $gibbonPersonID = $session->get('gibbonPersonID');

    // Check if viewing/editing a specific staff profile
    $gibbonStaffProfileID = $_GET['gibbonStaffProfileID'] ?? null;
    $mode = $_GET['mode'] ?? 'view';

    if (!empty($gibbonStaffProfileID)) {
        // Single staff profile view/edit mode
        $staffProfile = $staffProfileGateway->getStaffProfileByID($gibbonStaffProfileID);

        if (empty($staffProfile)) {
            $page->addError(__('The specified staff profile does not exist.'));
        } else {
            // Handle form submission for editing
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && $mode === 'edit') {
                // Collect and validate form data
                $updateData = [
                    'employeeNumber' => $_POST['employeeNumber'] ?? null,
                    'sin' => $_POST['sin'] ?? null,
                    'address' => $_POST['address'] ?? null,
                    'city' => $_POST['city'] ?? null,
                    'province' => $_POST['province'] ?? null,
                    'postalCode' => $_POST['postalCode'] ?? null,
                    'position' => $_POST['position'] ?? '',
                    'department' => $_POST['department'] ?? null,
                    'employmentType' => $_POST['employmentType'] ?? 'Full-Time',
                    'hireDate' => !empty($_POST['hireDate']) ? Format::dateConvert($_POST['hireDate']) : null,
                    'terminationDate' => !empty($_POST['terminationDate']) ? Format::dateConvert($_POST['terminationDate']) : null,
                    'probationEndDate' => !empty($_POST['probationEndDate']) ? Format::dateConvert($_POST['probationEndDate']) : null,
                    'status' => $_POST['status'] ?? 'Active',
                    'qualificationLevel' => $_POST['qualificationLevel'] ?? null,
                    'insuranceProvider' => $_POST['insuranceProvider'] ?? null,
                    'insurancePolicyNumber' => $_POST['insurancePolicyNumber'] ?? null,
                    'groupInsuranceEnrolled' => $_POST['groupInsuranceEnrolled'] ?? 'N',
                    'bankInstitution' => $_POST['bankInstitution'] ?? null,
                    'bankTransit' => $_POST['bankTransit'] ?? null,
                    'bankAccount' => $_POST['bankAccount'] ?? null,
                    'notes' => $_POST['notes'] ?? null,
                ];

                // Validate required fields
                if (empty($updateData['position'])) {
                    $page->addError(__('Position is a required field.'));
                } else {
                    // Store old values for audit
                    $oldValues = $staffProfile;

                    // Update the staff profile
                    $updated = $staffProfileGateway->update($gibbonStaffProfileID, $updateData);

                    if ($updated !== false) {
                        // Log the change for audit trail
                        $auditLogGateway->logUpdate(
                            'gibbonStaffProfile',
                            $gibbonStaffProfileID,
                            json_encode($oldValues),
                            json_encode(array_merge($oldValues, $updateData)),
                            $gibbonPersonID,
                            $session->get('gibbonPersonID')
                        );

                        $page->addSuccess(__('Staff profile has been updated successfully.'));

                        // Reload the profile data
                        $staffProfile = $staffProfileGateway->getStaffProfileByID($gibbonStaffProfileID);
                        $mode = 'view';
                    } else {
                        $page->addError(__('Failed to update staff profile.'));
                    }
                }
            }

            // Display page header
            $staffName = Format::name('', $staffProfile['preferredName'], $staffProfile['surname'], 'Staff', true, true);
            echo '<h2>' . $staffName . '</h2>';

            // Profile header with photo and quick info
            $image = !empty($staffProfile['image_240']) ? $staffProfile['image_240'] : 'themes/Default/img/anonymous_240.jpg';

            echo '<div class="bg-white rounded-lg shadow p-6 mb-4">';
            echo '<div class="flex flex-col md:flex-row gap-6">';

            // Photo
            echo '<div class="flex-shrink-0">';
            echo '<img src="' . $session->get('absoluteURL') . '/' . $image . '" class="w-32 h-32 rounded-full object-cover" alt="' . htmlspecialchars($staffName) . '">';
            echo '</div>';

            // Quick info
            echo '<div class="flex-grow">';
            echo '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">';
            echo '<div><span class="text-gray-500">' . __('Employee Number') . ':</span><br><span class="font-semibold">' . htmlspecialchars($staffProfile['employeeNumber'] ?? '-') . '</span></div>';
            echo '<div><span class="text-gray-500">' . __('Position') . ':</span><br><span class="font-semibold">' . htmlspecialchars($staffProfile['position'] ?? '-') . '</span></div>';
            echo '<div><span class="text-gray-500">' . __('Department') . ':</span><br><span class="font-semibold">' . htmlspecialchars($staffProfile['department'] ?? '-') . '</span></div>';
            echo '<div><span class="text-gray-500">' . __('Status') . ':</span><br>';
            $statusClass = [
                'Active' => 'bg-green-100 text-green-800',
                'Inactive' => 'bg-gray-100 text-gray-800',
                'On Leave' => 'bg-yellow-100 text-yellow-800',
                'Terminated' => 'bg-red-100 text-red-800',
            ];
            echo '<span class="px-2 py-1 rounded text-sm ' . ($statusClass[$staffProfile['status']] ?? 'bg-gray-100') . '">' . __($staffProfile['status']) . '</span>';
            echo '</div>';
            echo '<div><span class="text-gray-500">' . __('Employment Type') . ':</span><br><span class="font-semibold">' . __($staffProfile['employmentType'] ?? '-') . '</span></div>';
            echo '<div><span class="text-gray-500">' . __('Hire Date') . ':</span><br><span class="font-semibold">' . (!empty($staffProfile['hireDate']) ? Format::date($staffProfile['hireDate']) : '-') . '</span></div>';
            echo '</div>';
            echo '</div>';

            echo '</div>';
            echo '</div>';

            // Action buttons
            echo '<div class="flex gap-2 mb-4">';
            if ($mode === 'view') {
                echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_profile.php&gibbonStaffProfileID=' . $gibbonStaffProfileID . '&mode=edit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">' . __('Edit Profile') . '</a>';
            }
            echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_certifications.php&gibbonPersonID=' . $staffProfile['gibbonPersonID'] . '" class="bg-purple-500 text-white px-4 py-2 rounded hover:bg-purple-600">' . __('View Certifications') . '</a>';
            echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_schedule.php&gibbonPersonID=' . $staffProfile['gibbonPersonID'] . '" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">' . __('View Schedule') . '</a>';
            echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_profile.php" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">' . __('Back to List') . '</a>';
            echo '</div>';

            if ($mode === 'edit') {
                // Edit form
                $form = Form::create('editStaffProfile', $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_profile.php&gibbonStaffProfileID=' . $gibbonStaffProfileID . '&mode=edit');
                $form->setMethod('post');
                $form->setTitle(__('Edit Staff Profile'));

                // Personal Information Section
                $form->addRow()->addHeading(__('Personal Information'));

                $row = $form->addRow();
                $row->addLabel('employeeNumber', __('Employee Number'));
                $row->addTextField('employeeNumber')
                    ->setValue($staffProfile['employeeNumber'] ?? '')
                    ->maxLength(50);

                $row = $form->addRow();
                $row->addLabel('sin', __('Social Insurance Number'));
                $row->addTextField('sin')
                    ->setValue($staffProfile['sin'] ?? '')
                    ->maxLength(20);

                // Address Section
                $form->addRow()->addHeading(__('Address'));

                $row = $form->addRow();
                $row->addLabel('address', __('Street Address'));
                $row->addTextArea('address')
                    ->setValue($staffProfile['address'] ?? '')
                    ->setRows(2);

                $row = $form->addRow();
                $row->addLabel('city', __('City'));
                $row->addTextField('city')
                    ->setValue($staffProfile['city'] ?? '')
                    ->maxLength(100);

                $row = $form->addRow();
                $row->addLabel('province', __('Province'));
                $row->addSelect('province')
                    ->fromArray([
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
                    ])
                    ->selected($staffProfile['province'] ?? '');

                $row = $form->addRow();
                $row->addLabel('postalCode', __('Postal Code'));
                $row->addTextField('postalCode')
                    ->setValue($staffProfile['postalCode'] ?? '')
                    ->maxLength(20);

                // Employment Information Section
                $form->addRow()->addHeading(__('Employment Information'));

                $row = $form->addRow();
                $row->addLabel('position', __('Position'))->description(__('Required'));
                $row->addTextField('position')
                    ->setValue($staffProfile['position'] ?? '')
                    ->maxLength(100)
                    ->required();

                $row = $form->addRow();
                $row->addLabel('department', __('Department'));
                $row->addTextField('department')
                    ->setValue($staffProfile['department'] ?? '')
                    ->maxLength(100);

                $row = $form->addRow();
                $row->addLabel('employmentType', __('Employment Type'));
                $row->addSelect('employmentType')
                    ->fromArray([
                        'Full-Time' => __('Full-Time'),
                        'Part-Time' => __('Part-Time'),
                        'Casual' => __('Casual'),
                        'Contract' => __('Contract'),
                        'Substitute' => __('Substitute'),
                    ])
                    ->selected($staffProfile['employmentType'] ?? 'Full-Time');

                $row = $form->addRow();
                $row->addLabel('status', __('Status'));
                $row->addSelect('status')
                    ->fromArray([
                        'Active' => __('Active'),
                        'Inactive' => __('Inactive'),
                        'On Leave' => __('On Leave'),
                        'Terminated' => __('Terminated'),
                    ])
                    ->selected($staffProfile['status'] ?? 'Active');

                // Dates Section
                $form->addRow()->addHeading(__('Employment Dates'));

                $row = $form->addRow();
                $row->addLabel('hireDate', __('Hire Date'));
                $row->addDate('hireDate')
                    ->setValue(!empty($staffProfile['hireDate']) ? Format::date($staffProfile['hireDate']) : '');

                $row = $form->addRow();
                $row->addLabel('terminationDate', __('Termination Date'));
                $row->addDate('terminationDate')
                    ->setValue(!empty($staffProfile['terminationDate']) ? Format::date($staffProfile['terminationDate']) : '');

                $row = $form->addRow();
                $row->addLabel('probationEndDate', __('Probation End Date'));
                $row->addDate('probationEndDate')
                    ->setValue(!empty($staffProfile['probationEndDate']) ? Format::date($staffProfile['probationEndDate']) : '');

                // Qualifications Section
                $form->addRow()->addHeading(__('Qualifications'));

                $row = $form->addRow();
                $row->addLabel('qualificationLevel', __('Qualification Level'));
                $row->addSelect('qualificationLevel')
                    ->fromArray([
                        '' => __('Select...'),
                        'Unqualified' => __('Unqualified'),
                        'Level 1' => __('Level 1'),
                        'Level 2' => __('Level 2'),
                        'Level 3' => __('Level 3'),
                        'Director' => __('Director'),
                    ])
                    ->selected($staffProfile['qualificationLevel'] ?? '');

                // Insurance Information Section
                $form->addRow()->addHeading(__('Insurance Information'));

                $row = $form->addRow();
                $row->addLabel('insuranceProvider', __('Insurance Provider'));
                $row->addTextField('insuranceProvider')
                    ->setValue($staffProfile['insuranceProvider'] ?? '')
                    ->maxLength(100);

                $row = $form->addRow();
                $row->addLabel('insurancePolicyNumber', __('Policy Number'));
                $row->addTextField('insurancePolicyNumber')
                    ->setValue($staffProfile['insurancePolicyNumber'] ?? '')
                    ->maxLength(100);

                $row = $form->addRow();
                $row->addLabel('groupInsuranceEnrolled', __('Group Insurance Enrolled'));
                $row->addYesNo('groupInsuranceEnrolled')
                    ->selected($staffProfile['groupInsuranceEnrolled'] ?? 'N');

                // Banking Information Section
                $form->addRow()->addHeading(__('Banking Information (Payroll)'));

                $row = $form->addRow();
                $row->addLabel('bankInstitution', __('Institution Number'));
                $row->addTextField('bankInstitution')
                    ->setValue($staffProfile['bankInstitution'] ?? '')
                    ->maxLength(10);

                $row = $form->addRow();
                $row->addLabel('bankTransit', __('Transit Number'));
                $row->addTextField('bankTransit')
                    ->setValue($staffProfile['bankTransit'] ?? '')
                    ->maxLength(10);

                $row = $form->addRow();
                $row->addLabel('bankAccount', __('Account Number'));
                $row->addTextField('bankAccount')
                    ->setValue($staffProfile['bankAccount'] ?? '')
                    ->maxLength(20);

                // Notes Section
                $form->addRow()->addHeading(__('Notes'));

                $row = $form->addRow();
                $row->addLabel('notes', __('Internal HR Notes'));
                $row->addTextArea('notes')
                    ->setValue($staffProfile['notes'] ?? '')
                    ->setRows(4);

                // Submit
                $row = $form->addRow();
                $row->addFooter();
                $row->addSubmit(__('Save Changes'));

                echo $form->getOutput();

            } else {
                // View mode - display all sections

                // Personal Information
                echo '<div class="bg-white rounded-lg shadow p-4 mb-4">';
                echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">' . __('Personal Information') . '</h3>';
                echo '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">';
                echo '<div><span class="text-gray-500">' . __('Email') . ':</span><br>' . htmlspecialchars($staffProfile['email'] ?? '-') . '</div>';
                echo '<div><span class="text-gray-500">' . __('Phone') . ':</span><br>' . htmlspecialchars($staffProfile['phone1'] ?? '-') . '</div>';
                echo '<div><span class="text-gray-500">' . __('Secondary Phone') . ':</span><br>' . htmlspecialchars($staffProfile['phone2'] ?? '-') . '</div>';
                echo '<div><span class="text-gray-500">' . __('Date of Birth') . ':</span><br>' . (!empty($staffProfile['dob']) ? Format::date($staffProfile['dob']) : '-') . '</div>';
                echo '<div><span class="text-gray-500">' . __('Gender') . ':</span><br>' . htmlspecialchars($staffProfile['gender'] ?? '-') . '</div>';
                echo '<div><span class="text-gray-500">' . __('SIN') . ':</span><br><span class="text-gray-400">' . (!empty($staffProfile['sin']) ? '***-***-' . substr($staffProfile['sin'], -3) : '-') . '</span></div>';
                echo '</div>';
                echo '</div>';

                // Address
                echo '<div class="bg-white rounded-lg shadow p-4 mb-4">';
                echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">' . __('Address') . '</h3>';
                echo '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">';
                echo '<div class="lg:col-span-2"><span class="text-gray-500">' . __('Street Address') . ':</span><br>' . htmlspecialchars($staffProfile['address'] ?? '-') . '</div>';
                echo '<div><span class="text-gray-500">' . __('City') . ':</span><br>' . htmlspecialchars($staffProfile['city'] ?? '-') . '</div>';
                echo '<div><span class="text-gray-500">' . __('Province') . ':</span><br>' . htmlspecialchars($staffProfile['province'] ?? '-') . '</div>';
                echo '<div><span class="text-gray-500">' . __('Postal Code') . ':</span><br>' . htmlspecialchars($staffProfile['postalCode'] ?? '-') . '</div>';
                echo '</div>';
                echo '</div>';

                // Employment Information
                echo '<div class="bg-white rounded-lg shadow p-4 mb-4">';
                echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">' . __('Employment Information') . '</h3>';
                echo '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">';
                echo '<div><span class="text-gray-500">' . __('Position') . ':</span><br><span class="font-semibold">' . htmlspecialchars($staffProfile['position'] ?? '-') . '</span></div>';
                echo '<div><span class="text-gray-500">' . __('Department') . ':</span><br>' . htmlspecialchars($staffProfile['department'] ?? '-') . '</div>';
                echo '<div><span class="text-gray-500">' . __('Employment Type') . ':</span><br>' . __($staffProfile['employmentType'] ?? '-') . '</div>';
                echo '<div><span class="text-gray-500">' . __('Hire Date') . ':</span><br>' . (!empty($staffProfile['hireDate']) ? Format::date($staffProfile['hireDate']) : '-') . '</div>';
                echo '<div><span class="text-gray-500">' . __('Probation End Date') . ':</span><br>' . (!empty($staffProfile['probationEndDate']) ? Format::date($staffProfile['probationEndDate']) : '-') . '</div>';
                echo '<div><span class="text-gray-500">' . __('Termination Date') . ':</span><br>' . (!empty($staffProfile['terminationDate']) ? Format::date($staffProfile['terminationDate']) : '-') . '</div>';
                echo '</div>';
                echo '</div>';

                // Qualifications
                echo '<div class="bg-white rounded-lg shadow p-4 mb-4">';
                echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">' . __('Qualifications') . '</h3>';
                echo '<div class="grid grid-cols-1 md:grid-cols-2 gap-4">';
                echo '<div><span class="text-gray-500">' . __('Qualification Level') . ':</span><br>';
                $qualificationClass = [
                    'Director' => 'bg-purple-100 text-purple-800',
                    'Level 3' => 'bg-green-100 text-green-800',
                    'Level 2' => 'bg-blue-100 text-blue-800',
                    'Level 1' => 'bg-yellow-100 text-yellow-800',
                    'Unqualified' => 'bg-gray-100 text-gray-800',
                ];
                $qualLevel = $staffProfile['qualificationLevel'] ?? 'Unqualified';
                echo '<span class="px-2 py-1 rounded text-sm ' . ($qualificationClass[$qualLevel] ?? 'bg-gray-100') . '">' . __($qualLevel) . '</span>';
                echo '</div>';
                echo '</div>';

                // Display certifications summary
                $certCriteria = $certificationGateway->newQueryCriteria()
                    ->sortBy(['expiryDate'])
                    ->filterBy('status', 'Valid');
                $certifications = $certificationGateway->queryCertificationsByPerson($certCriteria, $staffProfile['gibbonPersonID']);

                if ($certifications->count() > 0) {
                    echo '<div class="mt-4">';
                    echo '<h4 class="font-semibold mb-2">' . __('Current Certifications') . '</h4>';
                    echo '<div class="space-y-1">';
                    foreach ($certifications as $cert) {
                        $expiryClass = 'text-gray-600';
                        if (!empty($cert['expiryDate'])) {
                            $daysUntilExpiry = (strtotime($cert['expiryDate']) - time()) / 86400;
                            if ($daysUntilExpiry <= 0) {
                                $expiryClass = 'text-red-600';
                            } elseif ($daysUntilExpiry <= 30) {
                                $expiryClass = 'text-orange-500';
                            }
                        }
                        echo '<div class="flex justify-between text-sm">';
                        echo '<span>' . htmlspecialchars($cert['certificationName']) . '</span>';
                        echo '<span class="' . $expiryClass . '">' . (!empty($cert['expiryDate']) ? Format::date($cert['expiryDate']) : __('No Expiry')) . '</span>';
                        echo '</div>';
                    }
                    echo '</div>';
                    echo '</div>';
                }
                echo '</div>';

                // Insurance Information
                echo '<div class="bg-white rounded-lg shadow p-4 mb-4">';
                echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">' . __('Insurance Information') . '</h3>';
                echo '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">';
                echo '<div><span class="text-gray-500">' . __('Insurance Provider') . ':</span><br>' . htmlspecialchars($staffProfile['insuranceProvider'] ?? '-') . '</div>';
                echo '<div><span class="text-gray-500">' . __('Policy Number') . ':</span><br>' . htmlspecialchars($staffProfile['insurancePolicyNumber'] ?? '-') . '</div>';
                echo '<div><span class="text-gray-500">' . __('Group Insurance Enrolled') . ':</span><br>' . ($staffProfile['groupInsuranceEnrolled'] === 'Y' ? __('Yes') : __('No')) . '</div>';
                echo '</div>';
                echo '</div>';

                // Banking Information (partially masked for security)
                echo '<div class="bg-white rounded-lg shadow p-4 mb-4">';
                echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">' . __('Banking Information') . '</h3>';
                echo '<div class="grid grid-cols-1 md:grid-cols-3 gap-4">';
                echo '<div><span class="text-gray-500">' . __('Institution') . ':</span><br><span class="text-gray-400">' . (!empty($staffProfile['bankInstitution']) ? '***' : '-') . '</span></div>';
                echo '<div><span class="text-gray-500">' . __('Transit') . ':</span><br><span class="text-gray-400">' . (!empty($staffProfile['bankTransit']) ? '***' : '-') . '</span></div>';
                echo '<div><span class="text-gray-500">' . __('Account') . ':</span><br><span class="text-gray-400">' . (!empty($staffProfile['bankAccount']) ? '***' . substr($staffProfile['bankAccount'], -4) : '-') . '</span></div>';
                echo '</div>';
                echo '</div>';

                // Notes
                if (!empty($staffProfile['notes'])) {
                    echo '<div class="bg-white rounded-lg shadow p-4 mb-4">';
                    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">' . __('Internal HR Notes') . '</h3>';
                    echo '<p class="text-gray-700 whitespace-pre-wrap">' . htmlspecialchars($staffProfile['notes']) . '</p>';
                    echo '</div>';
                }

                // Record information
                echo '<div class="text-sm text-gray-500 mt-4">';
                echo '<p>' . __('Created') . ': ' . Format::dateTime($staffProfile['timestampCreated']);
                if (!empty($staffProfile['createdByName'])) {
                    echo ' ' . __('by') . ' ' . Format::name('', $staffProfile['createdByName'], $staffProfile['createdBySurname'], 'Staff', false, true);
                }
                echo '</p>';
                echo '<p>' . __('Last Modified') . ': ' . Format::dateTime($staffProfile['timestampModified']) . '</p>';
                echo '</div>';
            }
        }

    } else {
        // Staff list view (default)
        echo '<h2>' . __('Staff Profiles') . '</h2>';

        // Filter form
        $statusFilter = $_GET['status'] ?? '';
        $employmentTypeFilter = $_GET['employmentType'] ?? '';
        $qualificationFilter = $_GET['qualificationLevel'] ?? '';
        $searchFilter = $_GET['search'] ?? '';

        $form = Form::create('filterStaff', $session->get('absoluteURL') . '/index.php');
        $form->setMethod('get');
        $form->addHiddenValue('q', '/modules/StaffManagement/staffManagement_profile.php');

        $row = $form->addRow();
        $row->addLabel('search', __('Search'));
        $row->addTextField('search')
            ->setValue($searchFilter)
            ->placeholder(__('Name, employee number, position...'));

        $row = $form->addRow();
        $row->addLabel('status', __('Status'));
        $row->addSelect('status')
            ->fromArray([
                '' => __('All'),
                'Active' => __('Active'),
                'Inactive' => __('Inactive'),
                'On Leave' => __('On Leave'),
                'Terminated' => __('Terminated'),
            ])
            ->selected($statusFilter);

        $row = $form->addRow();
        $row->addLabel('employmentType', __('Employment Type'));
        $row->addSelect('employmentType')
            ->fromArray([
                '' => __('All'),
                'Full-Time' => __('Full-Time'),
                'Part-Time' => __('Part-Time'),
                'Casual' => __('Casual'),
                'Contract' => __('Contract'),
                'Substitute' => __('Substitute'),
            ])
            ->selected($employmentTypeFilter);

        $row = $form->addRow();
        $row->addLabel('qualificationLevel', __('Qualification Level'));
        $row->addSelect('qualificationLevel')
            ->fromArray([
                '' => __('All'),
                'Director' => __('Director'),
                'Level 3' => __('Level 3'),
                'Level 2' => __('Level 2'),
                'Level 1' => __('Level 1'),
                'Unqualified' => __('Unqualified'),
            ])
            ->selected($qualificationFilter);

        $row = $form->addRow();
        $row->addSearchSubmit($container->get('gibbon')->session);

        echo $form->getOutput();

        // Build query criteria
        $criteria = $staffProfileGateway->newQueryCriteria(true)
            ->sortBy(['surname', 'preferredName'])
            ->filterBy('status', $statusFilter)
            ->filterBy('employmentType', $employmentTypeFilter)
            ->filterBy('qualificationLevel', $qualificationFilter)
            ->fromPOST();

        if (!empty($searchFilter)) {
            $criteria->searchBy($staffProfileGateway->getSearchableColumns(), $searchFilter);
        }

        // Get staff data
        $staffProfiles = $staffProfileGateway->queryStaffProfiles($criteria);

        // Build DataTable
        $table = DataTable::createPaginated('staffProfiles', $criteria);
        $table->setTitle(__('Staff Profiles'));

        // Add action button
        $table->addHeaderAction('add', __('Add'))
            ->setURL('/modules/StaffManagement/staffManagement_addEdit.php')
            ->displayLabel();

        // Add columns
        $table->addColumn('image', __('Photo'))
            ->notSortable()
            ->format(function ($row) use ($session) {
                $image = !empty($row['image_240']) ? $row['image_240'] : 'themes/Default/img/anonymous_240.jpg';
                return '<img src="' . $session->get('absoluteURL') . '/' . $image . '" class="w-10 h-10 rounded-full object-cover" alt="">';
            });

        $table->addColumn('name', __('Name'))
            ->sortable(['surname', 'preferredName'])
            ->format(function ($row) {
                return Format::name('', $row['preferredName'], $row['surname'], 'Staff', true, true);
            });

        $table->addColumn('employeeNumber', __('Employee #'))
            ->format(function ($row) {
                return htmlspecialchars($row['employeeNumber'] ?? '-');
            });

        $table->addColumn('position', __('Position'))
            ->sortable()
            ->format(function ($row) {
                $position = htmlspecialchars($row['position'] ?? '-');
                if (!empty($row['department'])) {
                    $position .= '<br><span class="text-xs text-gray-500">' . htmlspecialchars($row['department']) . '</span>';
                }
                return $position;
            });

        $table->addColumn('employmentType', __('Type'))
            ->format(function ($row) {
                return __($row['employmentType'] ?? '-');
            });

        $table->addColumn('status', __('Status'))
            ->format(function ($row) {
                $statusClass = [
                    'Active' => 'bg-green-100 text-green-800',
                    'Inactive' => 'bg-gray-100 text-gray-800',
                    'On Leave' => 'bg-yellow-100 text-yellow-800',
                    'Terminated' => 'bg-red-100 text-red-800',
                ];
                return '<span class="px-2 py-1 rounded text-xs ' . ($statusClass[$row['status']] ?? 'bg-gray-100') . '">' . __($row['status']) . '</span>';
            });

        $table->addColumn('qualificationLevel', __('Qualification'))
            ->format(function ($row) {
                $qualificationClass = [
                    'Director' => 'bg-purple-100 text-purple-800',
                    'Level 3' => 'bg-green-100 text-green-800',
                    'Level 2' => 'bg-blue-100 text-blue-800',
                    'Level 1' => 'bg-yellow-100 text-yellow-800',
                    'Unqualified' => 'bg-gray-100 text-gray-800',
                ];
                $level = $row['qualificationLevel'] ?? 'Unqualified';
                return '<span class="px-2 py-1 rounded text-xs ' . ($qualificationClass[$level] ?? 'bg-gray-100') . '">' . __($level) . '</span>';
            });

        $table->addColumn('hireDate', __('Hire Date'))
            ->sortable()
            ->format(function ($row) {
                return !empty($row['hireDate']) ? Format::date($row['hireDate']) : '-';
            });

        $table->addColumn('contact', __('Contact'))
            ->notSortable()
            ->format(function ($row) {
                $contact = '';
                if (!empty($row['email'])) {
                    $contact .= '<a href="mailto:' . htmlspecialchars($row['email']) . '" class="text-blue-600 hover:underline text-sm">' . htmlspecialchars($row['email']) . '</a>';
                }
                if (!empty($row['phone1'])) {
                    $contact .= '<br><span class="text-sm text-gray-600">' . htmlspecialchars($row['phone1']) . '</span>';
                }
                return $contact ?: '-';
            });

        // Add actions
        $table->addActionColumn()
            ->addParam('gibbonStaffProfileID')
            ->format(function ($row, $actions) {
                $actions->addAction('view', __('View'))
                    ->setURL('/modules/StaffManagement/staffManagement_profile.php')
                    ->addParam('mode', 'view');

                $actions->addAction('edit', __('Edit'))
                    ->setURL('/modules/StaffManagement/staffManagement_profile.php')
                    ->addParam('mode', 'edit');
            });

        // Output table
        echo $table->render($staffProfiles);

        // Quick stats
        $stats = $staffProfileGateway->getStaffSummaryStatistics();
        echo '<div class="bg-white rounded-lg shadow p-4 mt-4">';
        echo '<div class="grid grid-cols-2 md:grid-cols-5 gap-4 text-center">';
        echo '<div><span class="block text-2xl font-bold text-green-600">' . ($stats['totalActive'] ?? 0) . '</span><span class="text-sm text-gray-600">' . __('Active') . '</span></div>';
        echo '<div><span class="block text-2xl font-bold">' . ($stats['totalFullTime'] ?? 0) . '</span><span class="text-sm text-gray-600">' . __('Full-Time') . '</span></div>';
        echo '<div><span class="block text-2xl font-bold">' . ($stats['totalPartTime'] ?? 0) . '</span><span class="text-sm text-gray-600">' . __('Part-Time') . '</span></div>';
        echo '<div><span class="block text-2xl font-bold text-orange-500">' . ($stats['totalOnLeave'] ?? 0) . '</span><span class="text-sm text-gray-600">' . __('On Leave') . '</span></div>';
        echo '<div><span class="block text-2xl font-bold text-purple-600">' . ($stats['totalDirectors'] ?? 0) . '</span><span class="text-sm text-gray-600">' . __('Directors') . '</span></div>';
        echo '</div>';
        echo '</div>';
    }

    // Link back to dashboard
    echo '<div class="mt-4">';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement.php" class="text-blue-600 hover:underline">&larr; ' . __('Back to Dashboard') . '</a>';
    echo '</div>';
}
