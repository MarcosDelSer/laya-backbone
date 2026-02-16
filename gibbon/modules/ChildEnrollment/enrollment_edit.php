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
use Gibbon\Module\ChildEnrollment\Domain\EnrollmentFormGateway;

// Module setup - breadcrumbs
$page->breadcrumbs->add(__('Enrollment Forms'), 'enrollment_list.php');
$page->breadcrumbs->add(__('Edit Enrollment Form'));

// Access check
if (!isActionAccessible($guid, $connection2, '/modules/ChildEnrollment/enrollment_edit.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get form ID from request
    $gibbonChildEnrollmentFormID = $_GET['gibbonChildEnrollmentFormID'] ?? null;

    if (empty($gibbonChildEnrollmentFormID)) {
        $page->addError(__('You have not specified one or more required parameters.'));
        return;
    }

    // Get gateway via DI container
    $enrollmentFormGateway = $container->get(EnrollmentFormGateway::class);

    // Get form with all related data
    $formData = $enrollmentFormGateway->getFormWithRelations($gibbonChildEnrollmentFormID);

    if ($formData === false) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }

    // Check if form can be edited (only Draft or Rejected forms can be edited)
    if (!in_array($formData['status'], ['Draft', 'Rejected'])) {
        $page->addError(__('This enrollment form cannot be edited because it has been submitted or approved.'));
        return;
    }

    // Get settings
    $settingGateway = $container->get(SettingGateway::class);
    $minEmergencyContacts = $settingGateway->getSettingByScope('Child Enrollment', 'minEmergencyContacts') ?? 2;
    $minAuthorizedPickups = $settingGateway->getSettingByScope('Child Enrollment', 'minAuthorizedPickups') ?? 1;

    // Display return messages
    if (isset($_GET['return'])) {
        switch ($_GET['return']) {
            case 'success0':
                $page->addMessage(__('Enrollment form has been updated successfully.'));
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
                $page->addError(__('The specified enrollment form cannot be found.'));
                break;
            case 'error4':
                $page->addError(__('This enrollment form cannot be edited because it has been submitted or approved.'));
                break;
        }
    }

    // Extract parent data
    $parent1 = null;
    $parent2 = null;
    if (!empty($formData['parents'])) {
        foreach ($formData['parents'] as $parent) {
            if ($parent['parentNumber'] === '1') {
                $parent1 = $parent;
            } elseif ($parent['parentNumber'] === '2') {
                $parent2 = $parent;
            }
        }
    }

    // Extract pickup data
    $pickup1 = null;
    $pickup2 = null;
    if (!empty($formData['authorizedPickups'])) {
        foreach ($formData['authorizedPickups'] as $index => $pickup) {
            if ($index === 0) {
                $pickup1 = $pickup;
            } elseif ($index === 1) {
                $pickup2 = $pickup;
            }
        }
    }

    // Extract emergency contact data
    $emergency1 = null;
    $emergency2 = null;
    if (!empty($formData['emergencyContacts'])) {
        foreach ($formData['emergencyContacts'] as $index => $contact) {
            if ($index === 0) {
                $emergency1 = $contact;
            } elseif ($index === 1) {
                $emergency2 = $contact;
            }
        }
    }

    // Extract health data
    $health = $formData['health'] ?? [];

    // Extract nutrition data
    $nutrition = $formData['nutrition'] ?? [];

    // Extract attendance data
    $attendance = $formData['attendance'] ?? [];

    // Convert JSON allergies/medications back to text format for editing
    $allergiesText = '';
    if (!empty($health['allergies'])) {
        $allergies = json_decode($health['allergies'], true);
        if (is_array($allergies)) {
            $allergiesText = implode("\n", $allergies);
        } else {
            $allergiesText = $health['allergies'];
        }
    }

    $medicationsText = '';
    if (!empty($health['medications'])) {
        $medications = json_decode($health['medications'], true);
        if (is_array($medications)) {
            $medicationsText = implode("\n", $medications);
        } else {
            $medicationsText = $health['medications'];
        }
    }

    // Create form
    $form = Form::create('enrollmentEdit', $session->get('absoluteURL') . '/modules/ChildEnrollment/enrollment_editProcess.php?gibbonChildEnrollmentFormID=' . $gibbonChildEnrollmentFormID);
    $form->setFactory(DatabaseFormFactory::create($pdo));
    $form->setTitle(__('Edit Enrollment Form') . ': ' . $formData['formNumber']);
    $form->setDescription(__('Update the child enrollment form (Fiche d\'Inscription). Fields marked with an asterisk (*) are required.'));
    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('gibbonChildEnrollmentFormID', $gibbonChildEnrollmentFormID);

    // Store pickup and emergency contact IDs for updating
    if ($pickup1) {
        $form->addHiddenValue('pickup1ID', $pickup1['gibbonChildEnrollmentAuthorizedPickupID']);
    }
    if ($pickup2) {
        $form->addHiddenValue('pickup2ID', $pickup2['gibbonChildEnrollmentAuthorizedPickupID']);
    }
    if ($emergency1) {
        $form->addHiddenValue('emergency1ID', $emergency1['gibbonChildEnrollmentEmergencyContactID']);
    }
    if ($emergency2) {
        $form->addHiddenValue('emergency2ID', $emergency2['gibbonChildEnrollmentEmergencyContactID']);
    }

    // ============================================
    // SECTION: Child Information
    // ============================================
    $form->addRow()->addHeading(__('Child Information'))->append(__('Enter the child\'s personal information.'));

    // Child selection from existing students
    $row = $form->addRow();
        $row->addLabel('gibbonPersonID', __('Select Child'))
            ->description(__('Select an existing child from the system, or enter details manually below.'));
        $row->addSelectStudent('gibbonPersonID', $session->get('gibbonSchoolYearID'), ['allStudents' => true])
            ->placeholder(__('Select a child or leave empty for manual entry...'))
            ->selected($formData['gibbonPersonID'] ?? '');

    // Family selection
    $row = $form->addRow();
        $row->addLabel('gibbonFamilyID', __('Family'));
        $row->addSelectFamily('gibbonFamilyID')
            ->placeholder(__('Select a family...'))
            ->required()
            ->selected($formData['gibbonFamilyID'] ?? '');

    // Child name
    $row = $form->addRow();
        $row->addLabel('childFirstName', __('Child First Name'))->description(__('Required'));
        $row->addTextField('childFirstName')
            ->required()
            ->maxLength(100)
            ->setValue($formData['childFirstName'] ?? '');

    $row = $form->addRow();
        $row->addLabel('childLastName', __('Child Last Name'))->description(__('Required'));
        $row->addTextField('childLastName')
            ->required()
            ->maxLength(100)
            ->setValue($formData['childLastName'] ?? '');

    // Date of birth
    $row = $form->addRow();
        $row->addLabel('childDateOfBirth', __('Date of Birth'))->description(__('Required'));
        $row->addDate('childDateOfBirth')
            ->required()
            ->setValue(!empty($formData['childDateOfBirth']) ? Format::date($formData['childDateOfBirth']) : '');

    // Admission date
    $row = $form->addRow();
        $row->addLabel('admissionDate', __('Expected Admission Date'));
        $row->addDate('admissionDate')
            ->setValue(!empty($formData['admissionDate']) ? Format::date($formData['admissionDate']) : '');

    // Address
    $row = $form->addRow();
        $row->addLabel('childAddress', __('Address'));
        $row->addTextField('childAddress')
            ->maxLength(255)
            ->setValue($formData['childAddress'] ?? '');

    $row = $form->addRow();
        $row->addLabel('childCity', __('City'));
        $row->addTextField('childCity')
            ->maxLength(100)
            ->setValue($formData['childCity'] ?? '');

    $row = $form->addRow();
        $row->addLabel('childPostalCode', __('Postal Code'));
        $row->addTextField('childPostalCode')
            ->maxLength(20)
            ->setValue($formData['childPostalCode'] ?? '');

    // Languages spoken
    $row = $form->addRow();
        $row->addLabel('languagesSpoken', __('Languages Spoken'))
            ->description(__('Separate multiple languages with commas'));
        $row->addTextField('languagesSpoken')
            ->maxLength(255)
            ->setValue($formData['languagesSpoken'] ?? '');

    // Notes
    $row = $form->addRow();
        $row->addLabel('notes', __('Notes'));
        $row->addTextArea('notes')
            ->setRows(3)
            ->setValue($formData['notes'] ?? '');

    // ============================================
    // SECTION: Parent 1 Information
    // ============================================
    $form->addRow()->addHeading(__('Parent/Guardian 1'))->append(__('Primary parent or guardian information. At least one parent is required.'));

    $row = $form->addRow();
        $row->addLabel('parent1Name', __('Full Name'))->description(__('Required'));
        $row->addTextField('parent1Name')
            ->required()
            ->maxLength(150)
            ->setValue($parent1['name'] ?? '');

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
            ->placeholder(__('Select relationship...'))
            ->selected($parent1['relationship'] ?? '');

    $row = $form->addRow();
        $row->addLabel('parent1Address', __('Address'));
        $row->addTextField('parent1Address')
            ->maxLength(255)
            ->setValue($parent1['address'] ?? '');

    $row = $form->addRow();
        $row->addLabel('parent1City', __('City'));
        $row->addTextField('parent1City')
            ->maxLength(100)
            ->setValue($parent1['city'] ?? '');

    $row = $form->addRow();
        $row->addLabel('parent1PostalCode', __('Postal Code'));
        $row->addTextField('parent1PostalCode')
            ->maxLength(20)
            ->setValue($parent1['postalCode'] ?? '');

    $row = $form->addRow();
        $row->addLabel('parent1CellPhone', __('Cell Phone'))->description(__('At least one phone number required'));
        $row->addTextField('parent1CellPhone')
            ->maxLength(30)
            ->setValue($parent1['cellPhone'] ?? '');

    $row = $form->addRow();
        $row->addLabel('parent1HomePhone', __('Home Phone'));
        $row->addTextField('parent1HomePhone')
            ->maxLength(30)
            ->setValue($parent1['homePhone'] ?? '');

    $row = $form->addRow();
        $row->addLabel('parent1WorkPhone', __('Work Phone'));
        $row->addTextField('parent1WorkPhone')
            ->maxLength(30)
            ->setValue($parent1['workPhone'] ?? '');

    $row = $form->addRow();
        $row->addLabel('parent1Email', __('Email'));
        $row->addEmail('parent1Email')
            ->maxLength(150)
            ->setValue($parent1['email'] ?? '');

    $row = $form->addRow();
        $row->addLabel('parent1Employer', __('Employer'));
        $row->addTextField('parent1Employer')
            ->maxLength(150)
            ->setValue($parent1['employer'] ?? '');

    $row = $form->addRow();
        $row->addLabel('parent1WorkAddress', __('Work Address'));
        $row->addTextField('parent1WorkAddress')
            ->maxLength(255)
            ->setValue($parent1['workAddress'] ?? '');

    $row = $form->addRow();
        $row->addLabel('parent1WorkHours', __('Work Hours'))
            ->description(__('e.g., 9AM-5PM'));
        $row->addTextField('parent1WorkHours')
            ->maxLength(100)
            ->setValue($parent1['workHours'] ?? '');

    // ============================================
    // SECTION: Parent 2 Information (Optional)
    // ============================================
    $form->addRow()->addHeading(__('Parent/Guardian 2'))->append(__('Second parent or guardian information. This section is optional.'));

    $row = $form->addRow();
        $row->addLabel('parent2Name', __('Full Name'));
        $row->addTextField('parent2Name')
            ->maxLength(150)
            ->setValue($parent2['name'] ?? '');

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
            ])
            ->selected($parent2['relationship'] ?? '');

    $row = $form->addRow();
        $row->addLabel('parent2Address', __('Address'));
        $row->addTextField('parent2Address')
            ->maxLength(255)
            ->setValue($parent2['address'] ?? '');

    $row = $form->addRow();
        $row->addLabel('parent2City', __('City'));
        $row->addTextField('parent2City')
            ->maxLength(100)
            ->setValue($parent2['city'] ?? '');

    $row = $form->addRow();
        $row->addLabel('parent2PostalCode', __('Postal Code'));
        $row->addTextField('parent2PostalCode')
            ->maxLength(20)
            ->setValue($parent2['postalCode'] ?? '');

    $row = $form->addRow();
        $row->addLabel('parent2CellPhone', __('Cell Phone'));
        $row->addTextField('parent2CellPhone')
            ->maxLength(30)
            ->setValue($parent2['cellPhone'] ?? '');

    $row = $form->addRow();
        $row->addLabel('parent2HomePhone', __('Home Phone'));
        $row->addTextField('parent2HomePhone')
            ->maxLength(30)
            ->setValue($parent2['homePhone'] ?? '');

    $row = $form->addRow();
        $row->addLabel('parent2WorkPhone', __('Work Phone'));
        $row->addTextField('parent2WorkPhone')
            ->maxLength(30)
            ->setValue($parent2['workPhone'] ?? '');

    $row = $form->addRow();
        $row->addLabel('parent2Email', __('Email'));
        $row->addEmail('parent2Email')
            ->maxLength(150)
            ->setValue($parent2['email'] ?? '');

    $row = $form->addRow();
        $row->addLabel('parent2Employer', __('Employer'));
        $row->addTextField('parent2Employer')
            ->maxLength(150)
            ->setValue($parent2['employer'] ?? '');

    $row = $form->addRow();
        $row->addLabel('parent2WorkAddress', __('Work Address'));
        $row->addTextField('parent2WorkAddress')
            ->maxLength(255)
            ->setValue($parent2['workAddress'] ?? '');

    $row = $form->addRow();
        $row->addLabel('parent2WorkHours', __('Work Hours'));
        $row->addTextField('parent2WorkHours')
            ->maxLength(100)
            ->setValue($parent2['workHours'] ?? '');

    // ============================================
    // SECTION: Authorized Pickup Person 1
    // ============================================
    $form->addRow()->addHeading(__('Authorized Pickup Person 1'))->append(sprintf(__('Persons authorized to pick up the child. Minimum %d required.'), $minAuthorizedPickups));

    $row = $form->addRow();
        $row->addLabel('pickup1Name', __('Full Name'))->description(__('Required'));
        $row->addTextField('pickup1Name')
            ->required()
            ->maxLength(150)
            ->setValue($pickup1['name'] ?? '');

    $row = $form->addRow();
        $row->addLabel('pickup1Relationship', __('Relationship'))->description(__('Required'));
        $row->addTextField('pickup1Relationship')
            ->required()
            ->maxLength(50)
            ->setValue($pickup1['relationship'] ?? '');

    $row = $form->addRow();
        $row->addLabel('pickup1Phone', __('Phone'))->description(__('Required'));
        $row->addTextField('pickup1Phone')
            ->required()
            ->maxLength(30)
            ->setValue($pickup1['phone'] ?? '');

    $row = $form->addRow();
        $row->addLabel('pickup1Notes', __('Notes'));
        $row->addTextField('pickup1Notes')
            ->maxLength(255)
            ->setValue($pickup1['notes'] ?? '');

    // ============================================
    // SECTION: Authorized Pickup Person 2 (Optional)
    // ============================================
    $form->addRow()->addHeading(__('Authorized Pickup Person 2'))->append(__('Additional authorized pickup person (optional).'));

    $row = $form->addRow();
        $row->addLabel('pickup2Name', __('Full Name'));
        $row->addTextField('pickup2Name')
            ->maxLength(150)
            ->setValue($pickup2['name'] ?? '');

    $row = $form->addRow();
        $row->addLabel('pickup2Relationship', __('Relationship'));
        $row->addTextField('pickup2Relationship')
            ->maxLength(50)
            ->setValue($pickup2['relationship'] ?? '');

    $row = $form->addRow();
        $row->addLabel('pickup2Phone', __('Phone'));
        $row->addTextField('pickup2Phone')
            ->maxLength(30)
            ->setValue($pickup2['phone'] ?? '');

    $row = $form->addRow();
        $row->addLabel('pickup2Notes', __('Notes'));
        $row->addTextField('pickup2Notes')
            ->maxLength(255)
            ->setValue($pickup2['notes'] ?? '');

    // ============================================
    // SECTION: Emergency Contact 1
    // ============================================
    $form->addRow()->addHeading(__('Emergency Contact 1'))->append(sprintf(__('Emergency contact persons. Minimum %d required.'), $minEmergencyContacts));

    $row = $form->addRow();
        $row->addLabel('emergency1Name', __('Full Name'))->description(__('Required'));
        $row->addTextField('emergency1Name')
            ->required()
            ->maxLength(150)
            ->setValue($emergency1['name'] ?? '');

    $row = $form->addRow();
        $row->addLabel('emergency1Relationship', __('Relationship'))->description(__('Required'));
        $row->addTextField('emergency1Relationship')
            ->required()
            ->maxLength(50)
            ->setValue($emergency1['relationship'] ?? '');

    $row = $form->addRow();
        $row->addLabel('emergency1Phone', __('Phone'))->description(__('Required'));
        $row->addTextField('emergency1Phone')
            ->required()
            ->maxLength(30)
            ->setValue($emergency1['phone'] ?? '');

    $row = $form->addRow();
        $row->addLabel('emergency1AlternatePhone', __('Alternate Phone'));
        $row->addTextField('emergency1AlternatePhone')
            ->maxLength(30)
            ->setValue($emergency1['alternatePhone'] ?? '');

    $row = $form->addRow();
        $row->addLabel('emergency1Notes', __('Notes'));
        $row->addTextField('emergency1Notes')
            ->maxLength(255)
            ->setValue($emergency1['notes'] ?? '');

    // ============================================
    // SECTION: Emergency Contact 2
    // ============================================
    $form->addRow()->addHeading(__('Emergency Contact 2'))->append(__('Second emergency contact.'));

    $row = $form->addRow();
        $row->addLabel('emergency2Name', __('Full Name'))->description(__('Required'));
        $row->addTextField('emergency2Name')
            ->required()
            ->maxLength(150)
            ->setValue($emergency2['name'] ?? '');

    $row = $form->addRow();
        $row->addLabel('emergency2Relationship', __('Relationship'))->description(__('Required'));
        $row->addTextField('emergency2Relationship')
            ->required()
            ->maxLength(50)
            ->setValue($emergency2['relationship'] ?? '');

    $row = $form->addRow();
        $row->addLabel('emergency2Phone', __('Phone'))->description(__('Required'));
        $row->addTextField('emergency2Phone')
            ->required()
            ->maxLength(30)
            ->setValue($emergency2['phone'] ?? '');

    $row = $form->addRow();
        $row->addLabel('emergency2AlternatePhone', __('Alternate Phone'));
        $row->addTextField('emergency2AlternatePhone')
            ->maxLength(30)
            ->setValue($emergency2['alternatePhone'] ?? '');

    $row = $form->addRow();
        $row->addLabel('emergency2Notes', __('Notes'));
        $row->addTextField('emergency2Notes')
            ->maxLength(255)
            ->setValue($emergency2['notes'] ?? '');

    // ============================================
    // SECTION: Health Information
    // ============================================
    $form->addRow()->addHeading(__('Health Information'))->append(__('Medical and health information for the child.'));

    $row = $form->addRow();
        $row->addLabel('allergies', __('Allergies'))
            ->description(__('List any allergies (food, environmental, medication, etc.)'));
        $row->addTextArea('allergies')
            ->setRows(3)
            ->setValue($allergiesText);

    $row = $form->addRow();
        $row->addLabel('medicalConditions', __('Medical Conditions'))
            ->description(__('List any medical conditions'));
        $row->addTextArea('medicalConditions')
            ->setRows(3)
            ->setValue($health['medicalConditions'] ?? '');

    $row = $form->addRow();
        $row->addLabel('hasEpiPen', __('Has EpiPen'));
        $row->addYesNo('hasEpiPen')
            ->selected($health['hasEpiPen'] ?? 'N');

    $row = $form->addRow();
        $row->addLabel('epiPenInstructions', __('EpiPen Instructions'))
            ->description(__('If yes, provide detailed instructions for EpiPen use'));
        $row->addTextArea('epiPenInstructions')
            ->setRows(3)
            ->setValue($health['epiPenInstructions'] ?? '');

    $row = $form->addRow();
        $row->addLabel('medications', __('Medications'))
            ->description(__('List any medications with dosage and schedule'));
        $row->addTextArea('medications')
            ->setRows(3)
            ->setValue($medicationsText);

    $row = $form->addRow();
        $row->addLabel('specialNeeds', __('Special Needs'))
            ->description(__('Describe any special needs or developmental considerations'));
        $row->addTextArea('specialNeeds')
            ->setRows(3)
            ->setValue($health['specialNeeds'] ?? '');

    $row = $form->addRow();
        $row->addLabel('doctorName', __('Doctor Name'));
        $row->addTextField('doctorName')
            ->maxLength(150)
            ->setValue($health['doctorName'] ?? '');

    $row = $form->addRow();
        $row->addLabel('doctorPhone', __('Doctor Phone'));
        $row->addTextField('doctorPhone')
            ->maxLength(30)
            ->setValue($health['doctorPhone'] ?? '');

    $row = $form->addRow();
        $row->addLabel('doctorAddress', __('Doctor Address'));
        $row->addTextField('doctorAddress')
            ->maxLength(255)
            ->setValue($health['doctorAddress'] ?? '');

    $row = $form->addRow();
        $row->addLabel('healthInsuranceNumber', __('Health Insurance Number'))
            ->description(__('Quebec RAMQ number'));
        $row->addTextField('healthInsuranceNumber')
            ->maxLength(50)
            ->setValue($health['healthInsuranceNumber'] ?? '');

    $row = $form->addRow();
        $row->addLabel('healthInsuranceExpiry', __('Insurance Expiry Date'));
        $row->addDate('healthInsuranceExpiry')
            ->setValue(!empty($health['healthInsuranceExpiry']) ? Format::date($health['healthInsuranceExpiry']) : '');

    // ============================================
    // SECTION: Nutrition Information
    // ============================================
    $form->addRow()->addHeading(__('Nutrition Information'))->append(__('Dietary requirements and feeding information.'));

    $row = $form->addRow();
        $row->addLabel('dietaryRestrictions', __('Dietary Restrictions'))
            ->description(__('Religious, cultural, or other dietary restrictions'));
        $row->addTextArea('dietaryRestrictions')
            ->setRows(3)
            ->setValue($nutrition['dietaryRestrictions'] ?? '');

    $row = $form->addRow();
        $row->addLabel('foodAllergies', __('Food Allergies'))
            ->description(__('Specific food allergies (separate from medical allergies)'));
        $row->addTextArea('foodAllergies')
            ->setRows(3)
            ->setValue($nutrition['foodAllergies'] ?? '');

    $row = $form->addRow();
        $row->addLabel('feedingInstructions', __('Special Feeding Instructions'));
        $row->addTextArea('feedingInstructions')
            ->setRows(3)
            ->setValue($nutrition['feedingInstructions'] ?? '');

    $row = $form->addRow();
        $row->addLabel('isBottleFeeding', __('Is Bottle Feeding'));
        $row->addYesNo('isBottleFeeding')
            ->selected($nutrition['isBottleFeeding'] ?? 'N');

    $row = $form->addRow();
        $row->addLabel('bottleFeedingInfo', __('Bottle Feeding Details'))
            ->description(__('Formula type, schedule, etc.'));
        $row->addTextArea('bottleFeedingInfo')
            ->setRows(3)
            ->setValue($nutrition['bottleFeedingInfo'] ?? '');

    $row = $form->addRow();
        $row->addLabel('foodPreferences', __('Food Preferences'))
            ->description(__('Foods the child likes'));
        $row->addTextArea('foodPreferences')
            ->setRows(2)
            ->setValue($nutrition['foodPreferences'] ?? '');

    $row = $form->addRow();
        $row->addLabel('foodDislikes', __('Food Dislikes'))
            ->description(__('Foods the child dislikes'));
        $row->addTextArea('foodDislikes')
            ->setRows(2)
            ->setValue($nutrition['foodDislikes'] ?? '');

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
            $amCheckbox = $col->addCheckbox($key . 'Am')->description(__('AM'))->setValue('Y');
            $pmCheckbox = $col->addCheckbox($key . 'Pm')->description(__('PM'))->setValue('Y');

            // Set checked state based on existing data
            if (isset($attendance[$key . 'Am']) && $attendance[$key . 'Am'] === 'Y') {
                $amCheckbox->checked(true);
            }
            if (isset($attendance[$key . 'Pm']) && $attendance[$key . 'Pm'] === 'Y') {
                $pmCheckbox->checked(true);
            }
    }

    $row = $form->addRow();
        $row->addLabel('expectedArrivalTime', __('Expected Arrival Time'));
        $row->addTime('expectedArrivalTime')
            ->setValue($attendance['expectedArrivalTime'] ?? '');

    $row = $form->addRow();
        $row->addLabel('expectedDepartureTime', __('Expected Departure Time'));
        $row->addTime('expectedDepartureTime')
            ->setValue($attendance['expectedDepartureTime'] ?? '');

    $row = $form->addRow();
        $row->addLabel('expectedHoursPerWeek', __('Expected Hours Per Week'));
        $row->addNumber('expectedHoursPerWeek')
            ->minimum(0)
            ->maximum(80)
            ->decimalPlaces(1)
            ->setValue($attendance['expectedHoursPerWeek'] ?? '');

    $row = $form->addRow();
        $row->addLabel('attendanceNotes', __('Attendance Notes'));
        $row->addTextArea('attendanceNotes')
            ->setRows(2)
            ->setValue($attendance['notes'] ?? '');

    // ============================================
    // Submit
    // ============================================
    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit(__('Update Enrollment Form'));

    echo $form->getOutput();

    // Back link
    echo '<div class="mt-6">';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/ChildEnrollment/enrollment_view.php&gibbonChildEnrollmentFormID=' . $gibbonChildEnrollmentFormID . '" class="text-blue-600 hover:underline">&larr; ' . __('Back to View Form') . '</a>';
    echo '</div>';
}
