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
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Services\Format;
use Gibbon\Module\RL24Submission\Domain\RL24EligibilityGateway;

// Module setup - breadcrumbs
$page->breadcrumbs->add(__('FO-0601 Eligibility Forms'), 'rl24_eligibility.php');
$page->breadcrumbs->add(__('Edit Eligibility Form'));

// Access check
if (!isActionAccessible($guid, $connection2, '/modules/RL24Submission/rl24_eligibility.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get eligibility ID from URL
    $gibbonRL24EligibilityID = $_GET['gibbonRL24EligibilityID'] ?? '';

    if (empty($gibbonRL24EligibilityID)) {
        $page->addError(__('You have not specified one or more required parameters.'));
        return;
    }

    // Get eligibility gateway via DI container
    $eligibilityGateway = $container->get(RL24EligibilityGateway::class);

    // Get existing eligibility form data
    $eligibility = $eligibilityGateway->getEligibilityByID($gibbonRL24EligibilityID);

    if (empty($eligibility)) {
        $page->addError(__('The specified record does not exist.'));
        return;
    }

    // Get school year ID from session
    $gibbonSchoolYearID = $session->get('gibbonSchoolYearID');
    $gibbonPersonID = $session->get('gibbonPersonID');

    // Create form
    $form = Form::create('eligibilityEdit', $session->get('absoluteURL') . '/modules/RL24Submission/rl24_eligibility_editProcess.php');
    $form->setFactory(DatabaseFormFactory::create($pdo));
    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('gibbonRL24EligibilityID', $gibbonRL24EligibilityID);
    $form->addHiddenValue('gibbonSchoolYearID', $gibbonSchoolYearID);
    $form->addHiddenValue('modifiedByID', $gibbonPersonID);

    // Form Year Section
    $form->addRow()->addHeading(__('Form Details'));

    $row = $form->addRow();
        $row->addLabel('formYear', __('Tax Year'))->description(__('The tax year this eligibility form applies to.'));
        $currentYear = (int) date('Y');
        $yearOptions = [];
        for ($y = $currentYear; $y >= $currentYear - 5; $y--) {
            $yearOptions[$y] = $y;
        }
        $row->addSelect('formYear')
            ->fromArray($yearOptions)
            ->selected($eligibility['formYear'])
            ->required();

    // Child Information Section
    $form->addRow()->addHeading(__('Child Information'));

    // Display child name as read-only (linked child cannot be changed)
    $row = $form->addRow();
        $row->addLabel('childDisplay', __('Child'))->description(__('The enrolled child (cannot be changed).'));
        $childName = $eligibility['childPreferredName'] . ' ' . $eligibility['childSurname'];
        if (!empty($eligibility['childFirstName']) && !empty($eligibility['childLastName'])) {
            $childName = $eligibility['childFirstName'] . ' ' . $eligibility['childLastName'];
        }
        $row->addTextField('childDisplay')
            ->setValue($childName)
            ->readonly();

    $form->addHiddenValue('gibbonPersonIDChild', $eligibility['gibbonPersonIDChild']);

    $row = $form->addRow();
        $row->addLabel('childFirstName', __('Child First Name'))->description(__('Name as it appears on tax documents.'));
        $row->addTextField('childFirstName')
            ->maxLength(60)
            ->setValue($eligibility['childFirstName'])
            ->required();

    $row = $form->addRow();
        $row->addLabel('childLastName', __('Child Last Name'));
        $row->addTextField('childLastName')
            ->maxLength(60)
            ->setValue($eligibility['childLastName'])
            ->required();

    $row = $form->addRow();
        $row->addLabel('childDateOfBirth', __('Child Date of Birth'));
        $row->addDate('childDateOfBirth')
            ->setValue(!empty($eligibility['childDateOfBirth']) ? Format::date($eligibility['childDateOfBirth']) : '');

    $row = $form->addRow();
        $row->addLabel('childRelationship', __('Relationship to Parent'))->description(__('e.g., Son, Daughter, Ward'));
        $row->addTextField('childRelationship')
            ->maxLength(50)
            ->setValue($eligibility['childRelationship'] ?? '');

    // Parent/Guardian Information Section
    $form->addRow()->addHeading(__('Parent/Guardian Information'));

    $row = $form->addRow();
        $row->addLabel('gibbonPersonIDParent', __('Parent/Guardian'))->description(__('Select the parent or guardian claiming the credit.'));
        $row->addSelectUsers('gibbonPersonIDParent')
            ->placeholder(__('Please select...'))
            ->selected($eligibility['gibbonPersonIDParent'] ?? '');

    $row = $form->addRow();
        $row->addLabel('parentFirstName', __('Parent First Name'))->description(__('Name as it appears on tax documents.'));
        $row->addTextField('parentFirstName')
            ->maxLength(60)
            ->setValue($eligibility['parentFirstName'])
            ->required();

    $row = $form->addRow();
        $row->addLabel('parentLastName', __('Parent Last Name'));
        $row->addTextField('parentLastName')
            ->maxLength(60)
            ->setValue($eligibility['parentLastName'])
            ->required();

    $row = $form->addRow();
        $row->addLabel('parentSIN', __('Social Insurance Number (SIN)'))->description(__('Format: XXX XXX XXX'));
        $row->addTextField('parentSIN')
            ->maxLength(11)
            ->setValue($eligibility['parentSIN'] ?? '');

    $row = $form->addRow();
        $row->addLabel('parentPhone', __('Phone Number'));
        $row->addTextField('parentPhone')
            ->maxLength(20)
            ->setValue($eligibility['parentPhone'] ?? '');

    $row = $form->addRow();
        $row->addLabel('parentEmail', __('Email Address'));
        $row->addEmail('parentEmail')
            ->maxLength(100)
            ->setValue($eligibility['parentEmail'] ?? '');

    // Address Section
    $form->addRow()->addHeading(__('Address'));

    $row = $form->addRow();
        $row->addLabel('parentAddressLine1', __('Address Line 1'));
        $row->addTextField('parentAddressLine1')
            ->maxLength(100)
            ->setValue($eligibility['parentAddressLine1'] ?? '');

    $row = $form->addRow();
        $row->addLabel('parentAddressLine2', __('Address Line 2'));
        $row->addTextField('parentAddressLine2')
            ->maxLength(100)
            ->setValue($eligibility['parentAddressLine2'] ?? '');

    $row = $form->addRow();
        $row->addLabel('parentCity', __('City'));
        $row->addTextField('parentCity')
            ->maxLength(60)
            ->setValue($eligibility['parentCity'] ?? '');

    $row = $form->addRow();
        $row->addLabel('parentProvince', __('Province'));
        $provinceOptions = [
            'QC' => 'Quebec',
            'ON' => 'Ontario',
            'BC' => 'British Columbia',
            'AB' => 'Alberta',
            'MB' => 'Manitoba',
            'SK' => 'Saskatchewan',
            'NS' => 'Nova Scotia',
            'NB' => 'New Brunswick',
            'NL' => 'Newfoundland and Labrador',
            'PE' => 'Prince Edward Island',
            'NT' => 'Northwest Territories',
            'YT' => 'Yukon',
            'NU' => 'Nunavut',
        ];
        $row->addSelect('parentProvince')
            ->fromArray($provinceOptions)
            ->selected($eligibility['parentProvince'] ?? 'QC');

    $row = $form->addRow();
        $row->addLabel('parentPostalCode', __('Postal Code'));
        $row->addTextField('parentPostalCode')
            ->maxLength(10)
            ->setValue($eligibility['parentPostalCode'] ?? '');

    // Citizenship & Residency Section
    $form->addRow()->addHeading(__('Citizenship & Residency'));

    $row = $form->addRow();
        $row->addLabel('citizenshipStatus', __('Citizenship Status'));
        $row->addSelect('citizenshipStatus')
            ->fromArray([
                'Canadian' => __('Canadian Citizen'),
                'PermanentResident' => __('Permanent Resident'),
                'Refugee' => __('Refugee'),
                'Other' => __('Other'),
            ])
            ->placeholder(__('Please select...'))
            ->selected($eligibility['citizenshipStatus'] ?? '');

    $row = $form->addRow();
        $row->addLabel('citizenshipOther', __('Citizenship Other'))->description(__('If "Other" selected, please specify.'));
        $row->addTextField('citizenshipOther')
            ->maxLength(100)
            ->setValue($eligibility['citizenshipOther'] ?? '');

    $row = $form->addRow();
        $row->addLabel('residencyStatus', __('Residency Status'));
        $row->addSelect('residencyStatus')
            ->fromArray([
                'Quebec' => __('Quebec Resident'),
                'OutOfProvince' => __('Out of Province'),
            ])
            ->selected($eligibility['residencyStatus'] ?? 'Quebec');

    // Service Period Section
    $form->addRow()->addHeading(__('Service Period'));

    $row = $form->addRow();
        $row->addLabel('servicePeriodStart', __('Service Period Start'))->description(__('Start date of childcare services.'));
        $row->addDate('servicePeriodStart')
            ->setValue(!empty($eligibility['servicePeriodStart']) ? Format::date($eligibility['servicePeriodStart']) : '');

    $row = $form->addRow();
        $row->addLabel('servicePeriodEnd', __('Service Period End'))->description(__('End date of childcare services.'));
        $row->addDate('servicePeriodEnd')
            ->setValue(!empty($eligibility['servicePeriodEnd']) ? Format::date($eligibility['servicePeriodEnd']) : '');

    // Provider Administration Section (Edit-only section)
    $form->addRow()->addHeading(__('Provider Administration'));

    $row = $form->addRow();
        $row->addLabel('divisionNumber', __('Division/Permit Number'))->description(__('Provider division or permit number assigned by Revenu Quebec.'));
        $row->addTextField('divisionNumber')
            ->maxLength(20)
            ->setValue($eligibility['divisionNumber'] ?? '');

    $row = $form->addRow();
        $row->addLabel('approvalStatus', __('Approval Status'))->description(__('Current approval status of the eligibility form.'));
        $row->addSelect('approvalStatus')
            ->fromArray([
                'Pending' => __('Pending'),
                'Approved' => __('Approved'),
                'Rejected' => __('Rejected'),
                'Incomplete' => __('Incomplete'),
            ])
            ->selected($eligibility['approvalStatus'] ?? 'Pending')
            ->required();

    $row = $form->addRow();
        $row->addLabel('approvalNotes', __('Approval Notes'))->description(__('Internal notes about approval decision.'));
        $row->addTextArea('approvalNotes')
            ->setRows(3)
            ->setValue($eligibility['approvalNotes'] ?? '');

    $row = $form->addRow();
        $row->addLabel('documentsComplete', __('Documents Complete'))->description(__('Have all required supporting documents been submitted?'));
        $row->addYesNo('documentsComplete')
            ->selected($eligibility['documentsComplete'] ?? 'N')
            ->required();

    $row = $form->addRow();
        $row->addLabel('signatureConfirmed', __('Signature Confirmed'))->description(__('Has the parent signed the form?'));
        $row->addYesNo('signatureConfirmed')
            ->selected($eligibility['signatureConfirmed'] ?? 'N')
            ->required();

    // Display approval history if approved/rejected
    if (!empty($eligibility['approvalDate']) && !empty($eligibility['approvedByName'])) {
        $row = $form->addRow();
            $row->addLabel('approvalHistory', __('Approval History'));
            $approvalInfo = __('Status changed on') . ' ' . Format::date($eligibility['approvalDate']);
            $approvalInfo .= ' ' . __('by') . ' ' . $eligibility['approvedByName'] . ' ' . $eligibility['approvedBySurname'];
            $row->addContent('<span class="text-sm text-gray-600">' . htmlspecialchars($approvalInfo) . '</span>');
    }

    // Notes Section
    $form->addRow()->addHeading(__('Additional Information'));

    $row = $form->addRow();
        $row->addLabel('notes', __('Notes'));
        $row->addTextArea('notes')
            ->setRows(3)
            ->setValue($eligibility['notes'] ?? '');

    // Submit button
    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();

    echo $form->getOutput();

    // Display audit information
    echo '<div class="mt-6 p-4 bg-gray-50 rounded-lg">';
    echo '<h4 class="font-semibold mb-2">' . __('Record Information') . '</h4>';
    echo '<div class="text-sm text-gray-600">';
    echo '<p><strong>' . __('Created') . ':</strong> ' . Format::dateTime($eligibility['timestampCreated']);
    if (!empty($eligibility['createdByName'])) {
        echo ' ' . __('by') . ' ' . htmlspecialchars($eligibility['createdByName'] . ' ' . $eligibility['createdBySurname']);
    }
    echo '</p>';
    if (!empty($eligibility['timestampModified'])) {
        echo '<p><strong>' . __('Last Modified') . ':</strong> ' . Format::dateTime($eligibility['timestampModified']) . '</p>';
    }
    echo '</div>';
    echo '</div>';

    // Information box
    echo '<div class="mt-6 p-4 bg-blue-50 rounded-lg">';
    echo '<h4 class="font-semibold mb-2">' . __('Important Information') . '</h4>';
    echo '<ul class="text-sm text-gray-600 list-disc list-inside">';
    echo '<li>' . __('Changes to the child selection are not permitted once a form has been created.') . '</li>';
    echo '<li>' . __('Changing the approval status to "Approved" will record the current date and your user as the approver.') . '</li>';
    echo '<li>' . __('Approved forms are used for RL-24 slip generation. Ensure all information is accurate before approving.') . '</li>';
    echo '<li>' . __('SIN should be entered in the format XXX XXX XXX.') . '</li>';
    echo '</ul>';
    echo '</div>';
}
