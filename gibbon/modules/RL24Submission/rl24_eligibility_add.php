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

// Module setup - breadcrumbs
$page->breadcrumbs->add(__('FO-0601 Eligibility Forms'), 'rl24_eligibility.php');
$page->breadcrumbs->add(__('Add Eligibility Form'));

// Access check
if (!isActionAccessible($guid, $connection2, '/modules/RL24Submission/rl24_eligibility.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get school year ID from session
    $gibbonSchoolYearID = $session->get('gibbonSchoolYearID');
    $gibbonPersonID = $session->get('gibbonPersonID');

    // Default form year to current calendar year
    $currentYear = (int) date('Y');

    // Create form
    $form = Form::create('eligibilityAdd', $session->get('absoluteURL') . '/modules/RL24Submission/rl24_eligibility_addProcess.php');
    $form->setFactory(DatabaseFormFactory::create($pdo));
    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('gibbonSchoolYearID', $gibbonSchoolYearID);
    $form->addHiddenValue('createdByID', $gibbonPersonID);

    // Form Year Section
    $form->addRow()->addHeading(__('Form Details'));

    $row = $form->addRow();
        $row->addLabel('formYear', __('Tax Year'))->description(__('The tax year this eligibility form applies to.'));
        $yearOptions = [];
        for ($y = $currentYear; $y >= $currentYear - 2; $y--) {
            $yearOptions[$y] = $y;
        }
        $row->addSelect('formYear')
            ->fromArray($yearOptions)
            ->selected($currentYear)
            ->required();

    // Child Information Section
    $form->addRow()->addHeading(__('Child Information'));

    $row = $form->addRow();
        $row->addLabel('gibbonPersonIDChild', __('Child'))->description(__('Select the child enrolled in childcare.'));
        $row->addSelectStudent('gibbonPersonIDChild', $gibbonSchoolYearID)
            ->placeholder(__('Please select...'))
            ->required();

    $row = $form->addRow();
        $row->addLabel('childFirstName', __('Child First Name'));
        $row->addTextField('childFirstName')
            ->maxLength(60)
            ->required();

    $row = $form->addRow();
        $row->addLabel('childLastName', __('Child Last Name'));
        $row->addTextField('childLastName')
            ->maxLength(60)
            ->required();

    $row = $form->addRow();
        $row->addLabel('childDateOfBirth', __('Child Date of Birth'));
        $row->addDate('childDateOfBirth');

    $row = $form->addRow();
        $row->addLabel('childRelationship', __('Relationship to Parent'))->description(__('e.g., Son, Daughter, Ward'));
        $row->addTextField('childRelationship')
            ->maxLength(50);

    // Parent/Guardian Information Section
    $form->addRow()->addHeading(__('Parent/Guardian Information'));

    $row = $form->addRow();
        $row->addLabel('gibbonPersonIDParent', __('Parent/Guardian'))->description(__('Select the parent or guardian claiming the credit.'));
        $row->addSelectUsers('gibbonPersonIDParent')
            ->placeholder(__('Please select...'));

    $row = $form->addRow();
        $row->addLabel('parentFirstName', __('Parent First Name'));
        $row->addTextField('parentFirstName')
            ->maxLength(60)
            ->required();

    $row = $form->addRow();
        $row->addLabel('parentLastName', __('Parent Last Name'));
        $row->addTextField('parentLastName')
            ->maxLength(60)
            ->required();

    $row = $form->addRow();
        $row->addLabel('parentSIN', __('Social Insurance Number (SIN)'))->description(__('Format: XXX XXX XXX'));
        $row->addTextField('parentSIN')
            ->maxLength(11);

    $row = $form->addRow();
        $row->addLabel('parentPhone', __('Phone Number'));
        $row->addTextField('parentPhone')
            ->maxLength(20);

    $row = $form->addRow();
        $row->addLabel('parentEmail', __('Email Address'));
        $row->addEmail('parentEmail')
            ->maxLength(100);

    // Address Section
    $form->addRow()->addHeading(__('Address'));

    $row = $form->addRow();
        $row->addLabel('parentAddressLine1', __('Address Line 1'));
        $row->addTextField('parentAddressLine1')
            ->maxLength(100);

    $row = $form->addRow();
        $row->addLabel('parentAddressLine2', __('Address Line 2'));
        $row->addTextField('parentAddressLine2')
            ->maxLength(100);

    $row = $form->addRow();
        $row->addLabel('parentCity', __('City'));
        $row->addTextField('parentCity')
            ->maxLength(60);

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
            ->selected('QC');

    $row = $form->addRow();
        $row->addLabel('parentPostalCode', __('Postal Code'));
        $row->addTextField('parentPostalCode')
            ->maxLength(10);

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
            ->placeholder(__('Please select...'));

    $row = $form->addRow();
        $row->addLabel('citizenshipOther', __('Citizenship Other'))->description(__('If "Other" selected, please specify.'));
        $row->addTextField('citizenshipOther')
            ->maxLength(100);

    $row = $form->addRow();
        $row->addLabel('residencyStatus', __('Residency Status'));
        $row->addSelect('residencyStatus')
            ->fromArray([
                'Quebec' => __('Quebec Resident'),
                'OutOfProvince' => __('Out of Province'),
            ])
            ->selected('Quebec');

    // Service Period Section
    $form->addRow()->addHeading(__('Service Period'));

    $row = $form->addRow();
        $row->addLabel('servicePeriodStart', __('Service Period Start'))->description(__('Start date of childcare services.'));
        $row->addDate('servicePeriodStart');

    $row = $form->addRow();
        $row->addLabel('servicePeriodEnd', __('Service Period End'))->description(__('End date of childcare services.'));
        $row->addDate('servicePeriodEnd');

    $row = $form->addRow();
        $row->addLabel('divisionNumber', __('Division/Permit Number'))->description(__('Provider division or permit number.'));
        $row->addTextField('divisionNumber')
            ->maxLength(20);

    // Notes Section
    $form->addRow()->addHeading(__('Additional Information'));

    $row = $form->addRow();
        $row->addLabel('notes', __('Notes'));
        $row->addTextArea('notes')
            ->setRows(3);

    // Submit button
    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();

    echo $form->getOutput();

    // Information box
    echo '<div class="mt-6 p-4 bg-blue-50 rounded-lg">';
    echo '<h4 class="font-semibold mb-2">' . __('Important Information') . '</h4>';
    echo '<ul class="text-sm text-gray-600 list-disc list-inside">';
    echo '<li>' . __('All required fields must be completed before submitting.') . '</li>';
    echo '<li>' . __('The SIN should be entered in the format XXX XXX XXX.') . '</li>';
    echo '<li>' . __('After creating the form, you will be able to upload supporting documents.') . '</li>';
    echo '<li>' . __('The form will remain in "Pending" status until reviewed and approved.') . '</li>';
    echo '</ul>';
    echo '</div>';
}
