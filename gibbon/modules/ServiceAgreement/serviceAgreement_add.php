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
use Gibbon\Module\ServiceAgreement\Domain\ServiceAgreementGateway;

// Module setup - breadcrumbs
$page->breadcrumbs->add(__('Service Agreements'), 'serviceAgreement.php');
$page->breadcrumbs->add(__('Create New Agreement'));

// Access check
if (!isActionAccessible($guid, $connection2, '/modules/ServiceAgreement/serviceAgreement_add.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get school year ID and person ID from session
    $gibbonSchoolYearID = $session->get('gibbonSchoolYearID');
    $gibbonPersonID = $session->get('gibbonPersonID');

    // Get gateway via DI container
    $serviceAgreementGateway = $container->get(ServiceAgreementGateway::class);

    // Get default settings
    $defaultReducedContribution = getSettingByScope($connection2, 'Service Agreement', 'defaultReducedContribution') ?? '9.35';
    $defaultMaxHours = getSettingByScope($connection2, 'Service Agreement', 'defaultMaxHours') ?? '10';
    $latePickupFeePerMinute = getSettingByScope($connection2, 'Service Agreement', 'latePickupFeePerMinute') ?? '1.00';
    $latePickupGracePeriod = getSettingByScope($connection2, 'Service Agreement', 'latePickupGracePeriod') ?? '10';
    $defaultTerminationNotice = getSettingByScope($connection2, 'Service Agreement', 'defaultTerminationNotice') ?? '14';

    // Get children without agreements for dropdown
    $childrenWithoutAgreement = $serviceAgreementGateway->selectChildrenWithoutAgreement($gibbonSchoolYearID)->fetchAll();

    // Get all enrolled children for dropdown (fallback)
    $childrenOptions = [];
    foreach ($childrenWithoutAgreement as $child) {
        $childName = Format::name('', $child['preferredName'], $child['surname'], 'Student');
        $childrenOptions[$child['gibbonPersonID']] = $childName . ' (' . $child['formGroupName'] . ')';
    }

    // If no children without agreement, get all enrolled children
    if (empty($childrenOptions)) {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID];
        $sql = "SELECT gibbonPerson.gibbonPersonID, gibbonPerson.preferredName, gibbonPerson.surname, gibbonFormGroup.name as formGroupName
                FROM gibbonStudentEnrolment
                INNER JOIN gibbonPerson ON gibbonStudentEnrolment.gibbonPersonID=gibbonPerson.gibbonPersonID
                LEFT JOIN gibbonFormGroup ON gibbonStudentEnrolment.gibbonFormGroupID=gibbonFormGroup.gibbonFormGroupID
                WHERE gibbonStudentEnrolment.gibbonSchoolYearID=:gibbonSchoolYearID
                AND gibbonPerson.status='Full'
                ORDER BY gibbonPerson.surname, gibbonPerson.preferredName";
        $result = $pdo->executeQuery($data, $sql);
        while ($row = $result->fetch()) {
            $childName = Format::name('', $row['preferredName'], $row['surname'], 'Student');
            $childrenOptions[$row['gibbonPersonID']] = $childName . ' (' . ($row['formGroupName'] ?? '') . ')';
        }
    }

    // Get parents for dropdown
    $parentsOptions = [];
    $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID];
    $sql = "SELECT DISTINCT parent.gibbonPersonID, parent.preferredName, parent.surname, parent.email
            FROM gibbonFamilyAdult
            INNER JOIN gibbonPerson as parent ON gibbonFamilyAdult.gibbonPersonID=parent.gibbonPersonID
            INNER JOIN gibbonFamilyChild ON gibbonFamilyAdult.gibbonFamilyID=gibbonFamilyChild.gibbonFamilyID
            INNER JOIN gibbonStudentEnrolment ON gibbonFamilyChild.gibbonPersonID=gibbonStudentEnrolment.gibbonPersonID
            WHERE gibbonStudentEnrolment.gibbonSchoolYearID=:gibbonSchoolYearID
            AND parent.status='Full'
            AND gibbonFamilyAdult.contactPriority IN (1, 2)
            ORDER BY parent.surname, parent.preferredName";
    $result = $pdo->executeQuery($data, $sql);
    while ($row = $result->fetch()) {
        $parentName = Format::name('', $row['preferredName'], $row['surname'], 'Parent');
        $parentsOptions[$row['gibbonPersonID']] = $parentName . ' (' . ($row['email'] ?? 'no email') . ')';
    }

    // Page header
    echo '<h2>' . __('Create New Service Agreement') . '</h2>';
    echo '<p class="text-gray-600 mb-4">';
    echo __('Create a new Quebec FO-0659 Service Agreement (Entente de Services). All 13 articles are organized below. After creation, the agreement will be sent to the parent for electronic signature.');
    echo '</p>';

    // Create form
    $form = Form::create('serviceAgreementAdd', $session->get('absoluteURL') . '/modules/ServiceAgreement/serviceAgreement_addProcess.php');
    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('gibbonSchoolYearID', $gibbonSchoolYearID);

    // ========================================
    // ARTICLE 1: Identification of Parties
    // ========================================
    $form->addRow()->addHeading(__('Article 1: Identification of Parties'))->addClass('mt-6');

    // Provider Information
    $form->addRow()->addSubheading(__('Provider Information'));

    $row = $form->addRow();
    $row->addLabel('providerName', __('Provider Name'))->description(__('Childcare provider legal name'));
    $row->addTextField('providerName')->setValue(getSettingByScope($connection2, 'System', 'organisationName') ?? '')->required()->maxLength(100);

    $row = $form->addRow();
    $row->addLabel('providerPermitNumber', __('Permit Number'))->description(__('Quebec MFA permit number'));
    $row->addTextField('providerPermitNumber')->maxLength(50);

    $row = $form->addRow();
    $row->addLabel('providerAddress', __('Provider Address'));
    $row->addTextArea('providerAddress')->setRows(2)->maxLength(255);

    $row = $form->addRow();
    $row->addLabel('providerPhone', __('Provider Phone'));
    $row->addTextField('providerPhone')->maxLength(30);

    $row = $form->addRow();
    $row->addLabel('providerEmail', __('Provider Email'));
    $row->addEmail('providerEmail')->maxLength(100);

    // Child Information
    $form->addRow()->addSubheading(__('Child Information'));

    $row = $form->addRow();
    $row->addLabel('gibbonPersonIDChild', __('Child'))->description(__('Select the child for this agreement'));
    $row->addSelect('gibbonPersonIDChild')->fromArray($childrenOptions)->required()->placeholder(__('Select Child...'));

    $row = $form->addRow();
    $row->addLabel('childName', __('Child Legal Name'))->description(__('Full legal name as on official documents'));
    $row->addTextField('childName')->required()->maxLength(100);

    $row = $form->addRow();
    $row->addLabel('childDateOfBirth', __('Child Date of Birth'));
    $row->addDate('childDateOfBirth')->required();

    // Parent/Guardian Information
    $form->addRow()->addSubheading(__('Parent/Guardian Information'));

    $row = $form->addRow();
    $row->addLabel('gibbonPersonIDParent', __('Parent/Guardian'))->description(__('Select the parent who will sign this agreement'));
    $row->addSelect('gibbonPersonIDParent')->fromArray($parentsOptions)->required()->placeholder(__('Select Parent...'));

    $row = $form->addRow();
    $row->addLabel('parentName', __('Parent Legal Name'))->description(__('Full legal name for contract'));
    $row->addTextField('parentName')->required()->maxLength(100);

    $row = $form->addRow();
    $row->addLabel('parentAddress', __('Parent Address'));
    $row->addTextArea('parentAddress')->setRows(2)->maxLength(255);

    $row = $form->addRow();
    $row->addLabel('parentPhone', __('Parent Phone'));
    $row->addTextField('parentPhone')->maxLength(30);

    $row = $form->addRow();
    $row->addLabel('parentEmail', __('Parent Email'));
    $row->addEmail('parentEmail')->maxLength(100);

    // ========================================
    // ARTICLE 2: Description of Services
    // ========================================
    $form->addRow()->addHeading(__('Article 2: Description of Services'))->addClass('mt-6');

    $row = $form->addRow();
    $row->addLabel('maxHoursPerDay', __('Maximum Hours Per Day'))->description(__('Maximum daily childcare hours (usually 10)'));
    $row->addNumber('maxHoursPerDay')->setValue($defaultMaxHours)->minimum(1)->maximum(24)->decimalPlaces(2)->required();

    $row = $form->addRow();
    $row->addLabel('meals', __('Meals Included'));
    $col = $row->addColumn()->setClass('flex flex-col');
    $col->addCheckbox('includesBreakfast')->description(__('Breakfast'))->setValue('Y');
    $col->addCheckbox('includesLunch')->description(__('Lunch'))->setValue('Y')->checked(true);
    $col->addCheckbox('includesSnacks')->description(__('Snacks'))->setValue('Y')->checked(true);
    $col->addCheckbox('includesDinner')->description(__('Dinner'))->setValue('Y');

    $row = $form->addRow();
    $row->addLabel('serviceDescription', __('Additional Service Description'))->description(__('Describe any additional services provided'));
    $row->addTextArea('serviceDescription')->setRows(3);

    // ========================================
    // ARTICLE 3: Operating Hours
    // ========================================
    $form->addRow()->addHeading(__('Article 3: Operating Hours'))->addClass('mt-6');

    $row = $form->addRow();
    $row->addLabel('operatingHoursStart', __('Opening Time'));
    $row->addTime('operatingHoursStart')->setValue('07:00')->required();

    $row = $form->addRow();
    $row->addLabel('operatingHoursEnd', __('Closing Time'));
    $row->addTime('operatingHoursEnd')->setValue('18:00')->required();

    $daysOfWeek = [
        'Mon' => __('Monday'),
        'Tue' => __('Tuesday'),
        'Wed' => __('Wednesday'),
        'Thu' => __('Thursday'),
        'Fri' => __('Friday'),
        'Sat' => __('Saturday'),
        'Sun' => __('Sunday'),
    ];

    $row = $form->addRow();
    $row->addLabel('operatingDays', __('Operating Days'));
    $row->addCheckbox('operatingDays')->fromArray($daysOfWeek)->checked(['Mon', 'Tue', 'Wed', 'Thu', 'Fri']);

    // ========================================
    // ARTICLE 4: Attendance Pattern
    // ========================================
    $form->addRow()->addHeading(__('Article 4: Attendance Pattern'))->addClass('mt-6');

    $row = $form->addRow();
    $row->addLabel('attendancePattern', __('Attendance Schedule'))->description(__('Enter the child\'s regular attendance schedule (JSON format or description)'));
    $row->addTextArea('attendancePattern')->setRows(4)->required();

    $row = $form->addRow();
    $row->addLabel('hoursPerWeek', __('Total Hours Per Week'));
    $row->addNumber('hoursPerWeek')->minimum(0)->maximum(168)->decimalPlaces(2);

    // ========================================
    // ARTICLE 5: Payment Terms
    // ========================================
    $form->addRow()->addHeading(__('Article 5: Payment Terms (Contribution)'))->addClass('mt-6');

    $contributionTypes = [
        'Reduced' => __('Reduced Contribution (Quebec subsidized)'),
        'Full'    => __('Full Price'),
        'Mixed'   => __('Mixed (Reduced + Additional)'),
    ];

    $row = $form->addRow();
    $row->addLabel('contributionType', __('Contribution Type'));
    $row->addSelect('contributionType')->fromArray($contributionTypes)->required();

    $row = $form->addRow();
    $row->addLabel('dailyReducedContribution', __('Daily Reduced Contribution'))->description(__('Quebec reduced contribution rate (currently $9.35/day)'));
    $row->addCurrency('dailyReducedContribution')->setValue($defaultReducedContribution)->required();

    $row = $form->addRow();
    $row->addLabel('additionalDailyRate', __('Additional Daily Rate'))->description(__('Amount beyond reduced contribution (if applicable)'));
    $row->addCurrency('additionalDailyRate');

    $paymentFrequencies = [
        'Daily'    => __('Daily'),
        'Weekly'   => __('Weekly'),
        'Biweekly' => __('Bi-weekly'),
        'Monthly'  => __('Monthly'),
    ];

    $row = $form->addRow();
    $row->addLabel('paymentFrequency', __('Payment Frequency'));
    $row->addSelect('paymentFrequency')->fromArray($paymentFrequencies)->selected('Monthly')->required();

    $paymentMethods = [
        'DirectDebit'  => __('Direct Debit'),
        'BankTransfer' => __('Bank Transfer'),
        'Cheque'       => __('Cheque'),
        'Cash'         => __('Cash'),
        'Other'        => __('Other'),
    ];

    $row = $form->addRow();
    $row->addLabel('paymentMethod', __('Payment Method'));
    $row->addSelect('paymentMethod')->fromArray($paymentMethods)->selected('DirectDebit')->required();

    $row = $form->addRow();
    $row->addLabel('paymentDueDay', __('Payment Due Day'))->description(__('Day of month payment is due (1-31)'));
    $row->addNumber('paymentDueDay')->minimum(1)->maximum(31)->setValue(1);

    // ========================================
    // ARTICLE 6: Late Pickup Fees
    // ========================================
    $form->addRow()->addHeading(__('Article 6: Late Pickup Fees'))->addClass('mt-6');

    $row = $form->addRow();
    $row->addLabel('latePickupFeePerMinute', __('Fee Per Minute'))->description(__('Fee charged per minute for late pickup'));
    $row->addCurrency('latePickupFeePerMinute')->setValue($latePickupFeePerMinute);

    $row = $form->addRow();
    $row->addLabel('latePickupGracePeriod', __('Grace Period (minutes)'))->description(__('Minutes before late fees apply'));
    $row->addNumber('latePickupGracePeriod')->setValue($latePickupGracePeriod)->minimum(0)->maximum(60);

    $row = $form->addRow();
    $row->addLabel('latePickupMaxFee', __('Maximum Fee Per Instance'))->description(__('Optional cap on late fees'));
    $row->addCurrency('latePickupMaxFee');

    // ========================================
    // ARTICLE 7: Closure Days
    // ========================================
    $form->addRow()->addHeading(__('Article 7: Closure Days'))->addClass('mt-6');

    $row = $form->addRow();
    $row->addLabel('statutoryHolidaysClosed', __('Closed on Statutory Holidays'));
    $row->addYesNo('statutoryHolidaysClosed')->selected('Y')->required();

    $row = $form->addRow();
    $row->addLabel('summerClosureWeeks', __('Summer Closure Weeks'));
    $row->addNumber('summerClosureWeeks')->setValue(2)->minimum(0)->maximum(12);

    $row = $form->addRow();
    $row->addLabel('winterClosureWeeks', __('Winter Closure Weeks'));
    $row->addNumber('winterClosureWeeks')->setValue(1)->minimum(0)->maximum(8);

    $row = $form->addRow();
    $row->addLabel('closureDatesText', __('Specific Closure Dates'))->description(__('List any specific closure dates'));
    $row->addTextArea('closureDatesText')->setRows(3);

    // ========================================
    // ARTICLE 8: Absence Policy
    // ========================================
    $form->addRow()->addHeading(__('Article 8: Absence Policy'))->addClass('mt-6');

    $row = $form->addRow();
    $row->addLabel('maxAbsenceDaysPerYear', __('Maximum Absence Days Per Year'))->description(__('Leave blank for unlimited'));
    $row->addNumber('maxAbsenceDaysPerYear')->minimum(0)->maximum(365);

    $row = $form->addRow();
    $row->addLabel('absenceNoticeRequired', __('Notice Required (hours)'))->description(__('Hours of notice required for absence'));
    $row->addNumber('absenceNoticeRequired')->setValue(24)->minimum(0)->maximum(168);

    $absenceChargePolicies = [
        'ChargeAll'     => __('Charge for all absences'),
        'ChargePartial' => __('Charge partial for absences'),
        'NoCharge'      => __('No charge for absences'),
    ];

    $row = $form->addRow();
    $row->addLabel('absenceChargePolicy', __('Absence Charge Policy'));
    $row->addSelect('absenceChargePolicy')->fromArray($absenceChargePolicies)->selected('ChargeAll')->required();

    $row = $form->addRow();
    $row->addLabel('medicalAbsencePolicy', __('Medical Absence Policy'))->description(__('Policy for absences with medical certificate'));
    $row->addTextArea('medicalAbsencePolicy')->setRows(2);

    // ========================================
    // ARTICLE 9: Agreement Duration
    // ========================================
    $form->addRow()->addHeading(__('Article 9: Agreement Duration'))->addClass('mt-6');

    $row = $form->addRow();
    $row->addLabel('effectiveDate', __('Effective Date'))->description(__('Date when agreement takes effect'));
    $row->addDate('effectiveDate')->setValue(Format::date(date('Y-m-d')))->required();

    $row = $form->addRow();
    $row->addLabel('expirationDate', __('Expiration Date'))->description(__('Leave blank for indefinite agreement'));
    $row->addDate('expirationDate');

    $renewalTypes = [
        'AutoRenew'       => __('Automatic Annual Renewal'),
        'RequiresRenewal' => __('Requires Explicit Renewal'),
        'FixedTerm'       => __('Fixed Term (ends on expiration date)'),
    ];

    $row = $form->addRow();
    $row->addLabel('renewalType', __('Renewal Type'));
    $row->addSelect('renewalType')->fromArray($renewalTypes)->selected('AutoRenew')->required();

    $row = $form->addRow();
    $row->addLabel('renewalNoticeRequired', __('Renewal Notice (days)'))->description(__('Days notice required for renewal decisions'));
    $row->addNumber('renewalNoticeRequired')->setValue(30)->minimum(0)->maximum(180);

    // ========================================
    // ARTICLE 10: Termination Conditions
    // ========================================
    $form->addRow()->addHeading(__('Article 10: Termination Conditions'))->addClass('mt-6');

    $row = $form->addRow();
    $row->addLabel('parentTerminationNotice', __('Parent Termination Notice (days)'))->description(__('Days notice required from parent to terminate'));
    $row->addNumber('parentTerminationNotice')->setValue($defaultTerminationNotice)->minimum(0)->maximum(90)->required();

    $row = $form->addRow();
    $row->addLabel('providerTerminationNotice', __('Provider Termination Notice (days)'))->description(__('Days notice required from provider to terminate'));
    $row->addNumber('providerTerminationNotice')->setValue($defaultTerminationNotice)->minimum(0)->maximum(90)->required();

    $row = $form->addRow();
    $row->addLabel('immediateTerminationConditions', __('Immediate Termination Conditions'))->description(__('Conditions that allow immediate termination'));
    $row->addTextArea('immediateTerminationConditions')->setRows(3);

    $row = $form->addRow();
    $row->addLabel('terminationRefundPolicy', __('Termination Refund Policy'))->description(__('Policy for refunds upon termination'));
    $row->addTextArea('terminationRefundPolicy')->setRows(2);

    // ========================================
    // ARTICLE 11: Special Conditions
    // ========================================
    $form->addRow()->addHeading(__('Article 11: Special Conditions'))->addClass('mt-6');

    $row = $form->addRow();
    $row->addLabel('specialConditions', __('Special Conditions'))->description(__('Any special conditions or arrangements agreed upon'));
    $row->addTextArea('specialConditions')->setRows(4);

    // ========================================
    // ARTICLE 12: Consumer Protection Act
    // ========================================
    $form->addRow()->addHeading(__('Article 12: Quebec Consumer Protection Act'))->addClass('mt-6');

    // Display Consumer Protection Act notice
    echo '<div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">';
    echo '<h4 class="font-semibold text-yellow-800 mb-2">' . __('Consumer Protection Act Notice') . '</h4>';
    echo '<p class="text-sm text-yellow-700">';
    echo __('This service agreement is subject to the Quebec Consumer Protection Act (Loi sur la protection du consommateur). The parent/guardian will be required to acknowledge this notice before signing the agreement electronically.');
    echo '</p>';
    echo '<ul class="text-sm text-yellow-700 mt-2 list-disc list-inside">';
    echo '<li>' . __('The parent has 10 days to cancel the contract without penalty after receiving the signed copy') . '</li>';
    echo '<li>' . __('All fees and charges must be clearly stated in the contract') . '</li>';
    echo '<li>' . __('The provider must give written notice before any fee increases') . '</li>';
    echo '</ul>';
    echo '</div>';

    $form->addRow()->addContent(__('Consumer Protection Act acknowledgment will be collected when the parent signs the agreement.'));

    // ========================================
    // ARTICLE 13: Signatures
    // ========================================
    $form->addRow()->addHeading(__('Article 13: Signatures'))->addClass('mt-6');

    $form->addRow()->addContent('<div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
        <p class="text-sm text-blue-700">' . __('After creating this agreement, it will be marked as "Pending Signature" and sent to the parent for electronic signature. Both the parent and provider signatures will be collected digitally with timestamp and IP address verification for compliance.') . '</p>
    </div>');

    // Language preference for agreement
    $languages = [
        'fr' => __('French'),
        'en' => __('English'),
    ];

    $row = $form->addRow();
    $row->addLabel('languagePreference', __('Agreement Language'))->description(__('Language preference for agreement documents'));
    $row->addSelect('languagePreference')->fromArray($languages)->selected('fr')->required();

    // Submit buttons
    $row = $form->addRow();
    $row->addFooter();
    $row->addSubmit(__('Create Agreement'));

    echo $form->getOutput();

    // JavaScript to auto-populate child and parent names from selection
    echo '<script>
    document.addEventListener("DOMContentLoaded", function() {
        var childSelect = document.getElementById("gibbonPersonIDChild");
        var parentSelect = document.getElementById("gibbonPersonIDParent");

        if (childSelect) {
            childSelect.addEventListener("change", function() {
                var selectedOption = this.options[this.selectedIndex];
                if (selectedOption && selectedOption.text) {
                    // Extract just the name part (before the parentheses)
                    var name = selectedOption.text.split(" (")[0];
                    var childNameField = document.getElementById("childName");
                    if (childNameField && !childNameField.value) {
                        childNameField.value = name;
                    }
                }
            });
        }

        if (parentSelect) {
            parentSelect.addEventListener("change", function() {
                var selectedOption = this.options[this.selectedIndex];
                if (selectedOption && selectedOption.text) {
                    // Extract just the name part (before the parentheses)
                    var name = selectedOption.text.split(" (")[0];
                    var parentNameField = document.getElementById("parentName");
                    if (parentNameField && !parentNameField.value) {
                        parentNameField.value = name;
                    }
                    // Also try to extract email
                    var emailMatch = selectedOption.text.match(/\(([^)]+)\)/);
                    if (emailMatch && emailMatch[1] && emailMatch[1] !== "no email") {
                        var parentEmailField = document.getElementById("parentEmail");
                        if (parentEmailField && !parentEmailField.value) {
                            parentEmailField.value = emailMatch[1];
                        }
                    }
                }
            });
        }
    });
    </script>';
}
