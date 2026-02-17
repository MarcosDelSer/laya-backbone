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
$page->breadcrumbs
    ->add(__('Intervention Plans'), 'interventionPlans.php')
    ->add(__('Add Intervention Plan'));

// Access check
if (!isActionAccessible($guid, $connection2, '/modules/InterventionPlans/interventionPlans_add.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get session values
    $gibbonSchoolYearID = $session->get('gibbonSchoolYearID');
    $gibbonPersonID = $session->get('gibbonPersonID');

    // Get default settings from module settings
    $defaultReviewSchedule = getSettingByScope($connection2, 'Intervention Plans', 'defaultReviewSchedule') ?: 'Quarterly';
    $requireParentSignature = getSettingByScope($connection2, 'Intervention Plans', 'requireParentSignature') ?: 'Y';

    // Pre-fill child if provided via URL
    $preSelectedChild = $_GET['gibbonPersonID'] ?? '';

    // Status options
    $statusOptions = [
        'Draft' => __('Draft'),
        'Active' => __('Active'),
        'Under Review' => __('Under Review'),
    ];

    // Review schedule options
    $reviewScheduleOptions = [
        'Monthly' => __('Monthly'),
        'Quarterly' => __('Quarterly'),
        'Biannually' => __('Biannually'),
        'Annually' => __('Annually'),
    ];

    // Create form
    $form = Form::create('interventionPlanAdd', $session->get('absoluteURL') . '/modules/' . $session->get('module') . '/interventionPlans_addProcess.php');
    $form->setFactory(DatabaseFormFactory::create($pdo));

    $form->addHiddenValue('address', $session->get('address'));

    // Section: Plan Information
    $form->addRow()->addHeading(__('Plan Information'));

    $row = $form->addRow();
        $row->addLabel('gibbonPersonID', __('Child'))->description(__('Select the child this intervention plan is for.'));
        $row->addSelectStudent('gibbonPersonID', $gibbonSchoolYearID, ['allStudents' => false, 'byName' => true, 'byRoll' => false])
            ->required()
            ->placeholder(__('Please select...'))
            ->selected($preSelectedChild);

    $row = $form->addRow();
        $row->addLabel('title', __('Plan Title'))->description(__('A descriptive title for this intervention plan.'));
        $row->addTextField('title')
            ->maxLength(200)
            ->required();

    $row = $form->addRow();
        $row->addLabel('status', __('Status'));
        $row->addSelect('status')
            ->fromArray($statusOptions)
            ->required()
            ->selected('Draft');

    $row = $form->addRow();
        $row->addLabel('reviewSchedule', __('Review Schedule'))->description(__('How often should this plan be reviewed?'));
        $row->addSelect('reviewSchedule')
            ->fromArray($reviewScheduleOptions)
            ->required()
            ->selected($defaultReviewSchedule);

    // Section: Dates
    $form->addRow()->addHeading(__('Dates'));

    $row = $form->addRow();
        $row->addLabel('effectiveDate', __('Effective Date'))->description(__('When does this plan become effective?'));
        $row->addDate('effectiveDate')
            ->required()
            ->setValue(Format::date(date('Y-m-d')));

    $row = $form->addRow();
        $row->addLabel('endDate', __('End Date'))->description(__('Optional. When does this plan end?'));
        $row->addDate('endDate');

    $row = $form->addRow();
        $row->addLabel('nextReviewDate', __('Next Review Date'))->description(__('When should this plan be reviewed?'));
        $row->addDate('nextReviewDate');

    // Section: Initial Notes (Optional)
    $form->addRow()->addHeading(__('Initial Notes'));

    $form->addRow()->addContent('<div class="text-xs text-gray-600 mb-4">' . __('After creating the plan, you can add the 8-part structure including Strengths, Needs, SMART Goals, Strategies, Monitoring Methods, Parent Involvement, and Consultations from the edit page.') . '</div>');

    // Submit button
    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit(__('Create Plan'));

    echo $form->getOutput();

    // Information about the 8-part structure
    echo '<div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mt-6">';
    echo '<h3 class="text-lg font-semibold mb-3">' . __('8-Part Intervention Plan Structure') . '</h3>';
    echo '<p class="text-sm text-gray-700 mb-3">' . __('After creating the basic plan, you will be able to add the following sections:') . '</p>';
    echo '<div class="grid grid-cols-2 md:grid-cols-4 gap-2">';

    $parts = [
        '1' => __('Identification & History'),
        '2' => __('Strengths'),
        '3' => __('Needs'),
        '4' => __('SMART Goals'),
        '5' => __('Strategies'),
        '6' => __('Monitoring'),
        '7' => __('Parent Involvement'),
        '8' => __('Consultations'),
    ];

    foreach ($parts as $num => $label) {
        echo '<div class="bg-white rounded p-2 text-sm">';
        echo '<span class="font-bold text-blue-600">' . $num . '.</span> ' . $label;
        echo '</div>';
    }

    echo '</div>';
    echo '</div>';
}
