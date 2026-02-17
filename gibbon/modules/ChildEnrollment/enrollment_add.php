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
use Gibbon\Domain\System\SettingGateway;

// Module setup - breadcrumbs
$page->breadcrumbs->add(__('Enrollment Forms'), 'enrollment_list.php');
$page->breadcrumbs->add(__('Add Enrollment Form'));

// Access check
if (!isActionAccessible($guid, $connection2, '/modules/ChildEnrollment/enrollment_add.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get settings
    $settingGateway = $container->get(SettingGateway::class);
    $minEmergencyContacts = $settingGateway->getSettingByScope('Child Enrollment', 'minEmergencyContacts') ?? 2;
    $minAuthorizedPickups = $settingGateway->getSettingByScope('Child Enrollment', 'minAuthorizedPickups') ?? 1;

    // Display return messages
    if (isset($_GET['return'])) {
        switch ($_GET['return']) {
            case 'success0':
                $page->addMessage(__('Enrollment form has been created successfully.'));
                break;
            case 'error0':
                $page->addError(__('Your request failed because you do not have access to this action.'));
                break;
            case 'error1':
                $page->addError(__('Your request failed because your inputs were invalid.'));
                break;
            case 'error2':
                $page->addError(__('Your request failed due to a database error.'));
                break;
            case 'error3':
                $page->addError(__('Your request failed because the selected child was not found.'));
                break;
        }
    }

    // Create form
    $form = Form::create('enrollmentAdd', $session->get('absoluteURL') . '/modules/ChildEnrollment/enrollment_addProcess.php');
    $form->setFactory(DatabaseFormFactory::create($pdo));
    $form->setTitle(__('Add Enrollment Form'));
    $form->setDescription(__('Complete all required fields to create a new child enrollment form (Fiche d\'Inscription).'));
    $form->addHiddenValue('address', $session->get('address'));

    // ============================================
    // SECTION: Child Information
    // ============================================
    $form->addRow()->addHeading(__('Child Information'))->append(__('Enter the child\'s personal information.'));

    // Child selection from existing students
    $row = $form->addRow();
        $row->addLabel('gibbonPersonID', __('Select Child'))
            ->description(__('Select an existing child from the system, or enter details manually below.'));
        $row->addSelectStudent('gibbonPersonID', $session->get('gibbonSchoolYearID'), ['allStudents' => true])
            ->placeholder(__('Select a child or leave empty for manual entry...'));

    // Family selection
    $row = $form->addRow();
        $row->addLabel('gibbonFamilyID', __('Family'));
        $row->addSelectFamily('gibbonFamilyID')
            ->placeholder(__('Select a family...'))
            ->required();

    // Child name
    $row = $form->addRow();
        $row->addLabel('childFirstName', __('Child First Name'))->description(__('Required'));
        $row->addTextField('childFirstName')
            ->required()
            ->maxLength(100);

    $row = $form->addRow();
        $row->addLabel('childLastName', __('Child Last Name'))->description(__('Required'));
        $row->addTextField('childLastName')
            ->required()
            ->maxLength(100);

    // Date of birth
    $row = $form->addRow();
        $row->addLabel('childDateOfBirth', __('Date of Birth'))->description(__('Required'));
        $row->addDate('childDateOfBirth')
            ->required();

    // Admission date
    $row = $form->addRow();
        $row->addLabel('admissionDate', __('Expected Admission Date'));
        $row->addDate('admissionDate');

    // Address
    $row = $form->addRow();
        $row->addLabel('childAddress', __('Address'));
        $row->addTextField('childAddress')
            ->maxLength(255);

    $row = $form->addRow();
        $row->addLabel('childCity', __('City'));
        $row->addTextField('childCity')
            ->maxLength(100);

    $row = $form->addRow();
        $row->addLabel('childPostalCode', __('Postal Code'));
        $row->addTextField('childPostalCode')
            ->maxLength(20);

    // Languages spoken
    $row = $form->addRow();
        $row->addLabel('languagesSpoken', __('Languages Spoken'))
            ->description(__('Separate multiple languages with commas'));
        $row->addTextField('languagesSpoken')
            ->maxLength(255);

    // Notes
    $row = $form->addRow();
        $row->addLabel('notes', __('Notes'));
        $row->addTextArea('notes')
            ->setRows(3);

    // ============================================
    // SECTION: Parent 1 Information
    // ============================================
    $form->addRow()->addHeading(__('Parent/Guardian 1'))->append(__('Primary parent or guardian information. At least one parent is required.'));

    $row = $form->addRow();
        $row->addLabel('parent1Name', __('Full Name'))->description(__('Required'));
        $row->addTextField('parent1Name')
            ->required()
            ->maxLength(150);

    $row = $form->addRow();
        $row->addLabel('parent1Relationship', __('Relationship'))->description(__('Required'));
        $row->addSelect('parent1Relationship')
            ->fromArray([
                'Mother' => __('Mother'),
                'Father' => __('Father'),
                'Guardian' => __('Guardian'),
                'Stepmother' => __('Stepmother'),
                'Stepfather' => __('Stepfather'),
                'Grandparent' => __('Grandparent'),
                'Other' => __('Other'),
            ])
            ->required()
            ->placeholder(__('Select relationship...'));

    $row = $form->addRow();
        $row->addLabel('parent1Address', __('Address'));
        $row->addTextField('parent1Address')
            ->maxLength(255);

    $row = $form->addRow();
        $row->addLabel('parent1City', __('City'));
        $row->addTextField('parent1City')
            ->maxLength(100);

    $row = $form->addRow();
        $row->addLabel('parent1PostalCode', __('Postal Code'));
        $row->addTextField('parent1PostalCode')
            ->maxLength(20);

    $row = $form->addRow();
        $row->addLabel('parent1CellPhone', __('Cell Phone'))->description(__('At least one phone number required'));
        $row->addTextField('parent1CellPhone')
            ->maxLength(30);

    $row = $form->addRow();
        $row->addLabel('parent1HomePhone', __('Home Phone'));
        $row->addTextField('parent1HomePhone')
            ->maxLength(30);

    $row = $form->addRow();
        $row->addLabel('parent1WorkPhone', __('Work Phone'));
        $row->addTextField('parent1WorkPhone')
            ->maxLength(30);

    $row = $form->addRow();
        $row->addLabel('parent1Email', __('Email'));
        $row->addEmail('parent1Email')
            ->maxLength(150);

    $row = $form->addRow();
        $row->addLabel('parent1Employer', __('Employer'));
        $row->addTextField('parent1Employer')
            ->maxLength(150);

    $row = $form->addRow();
        $row->addLabel('parent1WorkAddress', __('Work Address'));
        $row->addTextField('parent1WorkAddress')
            ->maxLength(255);

    $row = $form->addRow();
        $row->addLabel('parent1WorkHours', __('Work Hours'))
            ->description(__('e.g., 9AM-5PM'));
        $row->addTextField('parent1WorkHours')
            ->maxLength(100);

    // ============================================
    // SECTION: Parent 2 Information (Optional)
    // ============================================
    $form->addRow()->addHeading(__('Parent/Guardian 2'))->append(__('Second parent or guardian information. This section is optional.'));

    $row = $form->addRow();
        $row->addLabel('parent2Name', __('Full Name'));
        $row->addTextField('parent2Name')
            ->maxLength(150);

    $row = $form->addRow();
        $row->addLabel('parent2Relationship', __('Relationship'));
        $row->addSelect('parent2Relationship')
            ->fromArray([
                '' => __('Select relationship...'),
                'Mother' => __('Mother'),
                'Father' => __('Father'),
                'Guardian' => __('Guardian'),
                'Stepmother' => __('Stepmother'),
                'Stepfather' => __('Stepfather'),
                'Grandparent' => __('Grandparent'),
                'Other' => __('Other'),
            ]);

    $row = $form->addRow();
        $row->addLabel('parent2Address', __('Address'));
        $row->addTextField('parent2Address')
            ->maxLength(255);

    $row = $form->addRow();
        $row->addLabel('parent2City', __('City'));
        $row->addTextField('parent2City')
            ->maxLength(100);

    $row = $form->addRow();
        $row->addLabel('parent2PostalCode', __('Postal Code'));
        $row->addTextField('parent2PostalCode')
            ->maxLength(20);

    $row = $form->addRow();
        $row->addLabel('parent2CellPhone', __('Cell Phone'));
        $row->addTextField('parent2CellPhone')
            ->maxLength(30);

    $row = $form->addRow();
        $row->addLabel('parent2HomePhone', __('Home Phone'));
        $row->addTextField('parent2HomePhone')
            ->maxLength(30);

    $row = $form->addRow();
        $row->addLabel('parent2WorkPhone', __('Work Phone'));
        $row->addTextField('parent2WorkPhone')
            ->maxLength(30);

    $row = $form->addRow();
        $row->addLabel('parent2Email', __('Email'));
        $row->addEmail('parent2Email')
            ->maxLength(150);

    $row = $form->addRow();
        $row->addLabel('parent2Employer', __('Employer'));
        $row->addTextField('parent2Employer')
            ->maxLength(150);

    $row = $form->addRow();
        $row->addLabel('parent2WorkAddress', __('Work Address'));
        $row->addTextField('parent2WorkAddress')
            ->maxLength(255);

    $row = $form->addRow();
        $row->addLabel('parent2WorkHours', __('Work Hours'));
        $row->addTextField('parent2WorkHours')
            ->maxLength(100);

    // ============================================
    // SECTION: Authorized Pickup Person 1
    // ============================================
    $form->addRow()->addHeading(__('Authorized Pickup Person 1'))->append(sprintf(__('Persons authorized to pick up the child. Minimum %d required.'), $minAuthorizedPickups));

    $row = $form->addRow();
        $row->addLabel('pickup1Name', __('Full Name'))->description(__('Required'));
        $row->addTextField('pickup1Name')
            ->required()
            ->maxLength(150);

    $row = $form->addRow();
        $row->addLabel('pickup1Relationship', __('Relationship'))->description(__('Required'));
        $row->addTextField('pickup1Relationship')
            ->required()
            ->maxLength(50);

    $row = $form->addRow();
        $row->addLabel('pickup1Phone', __('Phone'))->description(__('Required'));
        $row->addTextField('pickup1Phone')
            ->required()
            ->maxLength(30);

    $row = $form->addRow();
        $row->addLabel('pickup1Notes', __('Notes'));
        $row->addTextField('pickup1Notes')
            ->maxLength(255);

    // ============================================
    // SECTION: Authorized Pickup Person 2 (Optional)
    // ============================================
    $form->addRow()->addHeading(__('Authorized Pickup Person 2'))->append(__('Additional authorized pickup person (optional).'));

    $row = $form->addRow();
        $row->addLabel('pickup2Name', __('Full Name'));
        $row->addTextField('pickup2Name')
            ->maxLength(150);

    $row = $form->addRow();
        $row->addLabel('pickup2Relationship', __('Relationship'));
        $row->addTextField('pickup2Relationship')
            ->maxLength(50);

    $row = $form->addRow();
        $row->addLabel('pickup2Phone', __('Phone'));
        $row->addTextField('pickup2Phone')
            ->maxLength(30);

    $row = $form->addRow();
        $row->addLabel('pickup2Notes', __('Notes'));
        $row->addTextField('pickup2Notes')
            ->maxLength(255);

    // ============================================
    // SECTION: Emergency Contact 1
    // ============================================
    $form->addRow()->addHeading(__('Emergency Contact 1'))->append(sprintf(__('Emergency contact persons. Minimum %d required.'), $minEmergencyContacts));

    $row = $form->addRow();
        $row->addLabel('emergency1Name', __('Full Name'))->description(__('Required'));
        $row->addTextField('emergency1Name')
            ->required()
            ->maxLength(150);

    $row = $form->addRow();
        $row->addLabel('emergency1Relationship', __('Relationship'))->description(__('Required'));
        $row->addTextField('emergency1Relationship')
            ->required()
            ->maxLength(50);

    $row = $form->addRow();
        $row->addLabel('emergency1Phone', __('Phone'))->description(__('Required'));
        $row->addTextField('emergency1Phone')
            ->required()
            ->maxLength(30);

    $row = $form->addRow();
        $row->addLabel('emergency1AlternatePhone', __('Alternate Phone'));
        $row->addTextField('emergency1AlternatePhone')
            ->maxLength(30);

    $row = $form->addRow();
        $row->addLabel('emergency1Notes', __('Notes'));
        $row->addTextField('emergency1Notes')
            ->maxLength(255);

    // ============================================
    // SECTION: Emergency Contact 2
    // ============================================
    $form->addRow()->addHeading(__('Emergency Contact 2'))->append(__('Second emergency contact.'));

    $row = $form->addRow();
        $row->addLabel('emergency2Name', __('Full Name'))->description(__('Required'));
        $row->addTextField('emergency2Name')
            ->required()
            ->maxLength(150);

    $row = $form->addRow();
        $row->addLabel('emergency2Relationship', __('Relationship'))->description(__('Required'));
        $row->addTextField('emergency2Relationship')
            ->required()
            ->maxLength(50);

    $row = $form->addRow();
        $row->addLabel('emergency2Phone', __('Phone'))->description(__('Required'));
        $row->addTextField('emergency2Phone')
            ->required()
            ->maxLength(30);

    $row = $form->addRow();
        $row->addLabel('emergency2AlternatePhone', __('Alternate Phone'));
        $row->addTextField('emergency2AlternatePhone')
            ->maxLength(30);

    $row = $form->addRow();
        $row->addLabel('emergency2Notes', __('Notes'));
        $row->addTextField('emergency2Notes')
            ->maxLength(255);

    // ============================================
    // SECTION: Health Information
    // ============================================
    $form->addRow()->addHeading(__('Health Information'))->append(__('Medical and health information for the child.'));

    $row = $form->addRow();
        $row->addLabel('allergies', __('Allergies'))
            ->description(__('List any allergies (food, environmental, medication, etc.)'));
        $row->addTextArea('allergies')
            ->setRows(3);

    $row = $form->addRow();
        $row->addLabel('medicalConditions', __('Medical Conditions'))
            ->description(__('List any medical conditions'));
        $row->addTextArea('medicalConditions')
            ->setRows(3);

    $row = $form->addRow();
        $row->addLabel('hasEpiPen', __('Has EpiPen'));
        $row->addYesNo('hasEpiPen')
            ->selected('N');

    $row = $form->addRow();
        $row->addLabel('epiPenInstructions', __('EpiPen Instructions'))
            ->description(__('If yes, provide detailed instructions for EpiPen use'));
        $row->addTextArea('epiPenInstructions')
            ->setRows(3);

    $row = $form->addRow();
        $row->addLabel('medications', __('Medications'))
            ->description(__('List any medications with dosage and schedule'));
        $row->addTextArea('medications')
            ->setRows(3);

    $row = $form->addRow();
        $row->addLabel('specialNeeds', __('Special Needs'))
            ->description(__('Describe any special needs or developmental considerations'));
        $row->addTextArea('specialNeeds')
            ->setRows(3);

    $row = $form->addRow();
        $row->addLabel('doctorName', __('Doctor Name'));
        $row->addTextField('doctorName')
            ->maxLength(150);

    $row = $form->addRow();
        $row->addLabel('doctorPhone', __('Doctor Phone'));
        $row->addTextField('doctorPhone')
            ->maxLength(30);

    $row = $form->addRow();
        $row->addLabel('doctorAddress', __('Doctor Address'));
        $row->addTextField('doctorAddress')
            ->maxLength(255);

    $row = $form->addRow();
        $row->addLabel('healthInsuranceNumber', __('Health Insurance Number'))
            ->description(__('Quebec RAMQ number'));
        $row->addTextField('healthInsuranceNumber')
            ->maxLength(50);

    $row = $form->addRow();
        $row->addLabel('healthInsuranceExpiry', __('Insurance Expiry Date'));
        $row->addDate('healthInsuranceExpiry');

    // ============================================
    // SECTION: Nutrition Information
    // ============================================
    $form->addRow()->addHeading(__('Nutrition Information'))->append(__('Dietary requirements and feeding information.'));

    $row = $form->addRow();
        $row->addLabel('dietaryRestrictions', __('Dietary Restrictions'))
            ->description(__('Religious, cultural, or other dietary restrictions'));
        $row->addTextArea('dietaryRestrictions')
            ->setRows(3);

    $row = $form->addRow();
        $row->addLabel('foodAllergies', __('Food Allergies'))
            ->description(__('Specific food allergies (separate from medical allergies)'));
        $row->addTextArea('foodAllergies')
            ->setRows(3);

    $row = $form->addRow();
        $row->addLabel('feedingInstructions', __('Special Feeding Instructions'));
        $row->addTextArea('feedingInstructions')
            ->setRows(3);

    $row = $form->addRow();
        $row->addLabel('isBottleFeeding', __('Is Bottle Feeding'));
        $row->addYesNo('isBottleFeeding')
            ->selected('N');

    $row = $form->addRow();
        $row->addLabel('bottleFeedingInfo', __('Bottle Feeding Details'))
            ->description(__('Formula type, schedule, etc.'));
        $row->addTextArea('bottleFeedingInfo')
            ->setRows(3);

    $row = $form->addRow();
        $row->addLabel('foodPreferences', __('Food Preferences'))
            ->description(__('Foods the child likes'));
        $row->addTextArea('foodPreferences')
            ->setRows(2);

    $row = $form->addRow();
        $row->addLabel('foodDislikes', __('Food Dislikes'))
            ->description(__('Foods the child dislikes'));
        $row->addTextArea('foodDislikes')
            ->setRows(2);

    // ============================================
    // SECTION: Weekly Attendance Schedule
    // ============================================
    $form->addRow()->addHeading(__('Weekly Attendance Schedule'))->append(__('Expected weekly attendance pattern.'));

    // Days of the week - AM/PM checkboxes
    $days = [
        'monday' => __('Monday'),
        'tuesday' => __('Tuesday'),
        'wednesday' => __('Wednesday'),
        'thursday' => __('Thursday'),
        'friday' => __('Friday'),
        'saturday' => __('Saturday'),
        'sunday' => __('Sunday'),
    ];

    foreach ($days as $key => $label) {
        $row = $form->addRow();
            $row->addLabel($key . 'Schedule', $label);
            $col = $row->addColumn()->addClass('flex gap-4');
            $col->addCheckbox($key . 'Am')->description(__('AM'))->setValue('Y');
            $col->addCheckbox($key . 'Pm')->description(__('PM'))->setValue('Y');
    }

    $row = $form->addRow();
        $row->addLabel('expectedArrivalTime', __('Expected Arrival Time'));
        $row->addTime('expectedArrivalTime');

    $row = $form->addRow();
        $row->addLabel('expectedDepartureTime', __('Expected Departure Time'));
        $row->addTime('expectedDepartureTime');

    $row = $form->addRow();
        $row->addLabel('expectedHoursPerWeek', __('Expected Hours Per Week'));
        $row->addNumber('expectedHoursPerWeek')
            ->minimum(0)
            ->maximum(80)
            ->decimalPlaces(1);

    $row = $form->addRow();
        $row->addLabel('attendanceNotes', __('Attendance Notes'));
        $row->addTextArea('attendanceNotes')
            ->setRows(2);

    // ============================================
    // Submit
    // ============================================
    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit(__('Create Enrollment Form'));

    echo $form->getOutput();

    // Help section
    echo '<div class="message">';
    echo '<h4>' . __('Enrollment Form Information') . '</h4>';
    echo '<p>' . __('This form creates a new child enrollment form (Fiche d\'Inscription) with all required Quebec compliance information. After creation:') . '</p>';
    echo '<ul class="list-disc ml-6 mt-2">';
    echo '<li>' . __('The form will be saved as a Draft.') . '</li>';
    echo '<li>' . __('You can edit the form to add additional information or collect signatures.') . '</li>';
    echo '<li>' . __('Parents can sign the form electronically.') . '</li>';
    echo '<li>' . __('Once signed, the form can be submitted for approval.') . '</li>';
    echo '<li>' . __('A PDF version can be generated and printed at any time.') . '</li>';
    echo '</ul>';
    echo '</div>';
}
