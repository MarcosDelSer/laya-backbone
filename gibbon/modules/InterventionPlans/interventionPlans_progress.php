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
use Gibbon\Tables\DataTable;
use Gibbon\Module\InterventionPlans\Domain\InterventionPlanGateway;

// Module setup - breadcrumbs
$page->breadcrumbs
    ->add(__('Intervention Plans'), 'interventionPlans.php')
    ->add(__('Add Progress'));

// Access check
if (!isActionAccessible($guid, $connection2, '/modules/InterventionPlans/interventionPlans_progress.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get plan ID from URL
    $gibbonInterventionPlanID = $_GET['gibbonInterventionPlanID'] ?? '';

    if (empty($gibbonInterventionPlanID)) {
        $page->addError(__('You have not specified one or more required parameters.'));
        return;
    }

    // Get session values
    $gibbonSchoolYearID = $session->get('gibbonSchoolYearID');
    $gibbonPersonID = $session->get('gibbonPersonID');

    // Get gateway
    $interventionPlanGateway = $container->get(InterventionPlanGateway::class);

    // Get plan details
    $plan = $interventionPlanGateway->getPlanDetails($gibbonInterventionPlanID);

    if (empty($plan)) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }

    // Get goals for this plan
    $goals = $interventionPlanGateway->selectGoalsByPlan($gibbonInterventionPlanID)->fetchAll();

    if (empty($goals)) {
        $page->addWarning(__('This intervention plan has no SMART goals defined. Please add goals before recording progress.'));
        echo '<div class="mt-4">';
        echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/InterventionPlans/interventionPlans_edit.php&gibbonInterventionPlanID=' . $gibbonInterventionPlanID . '" class="text-blue-600 hover:underline">' . __('Edit Plan to Add Goals') . '</a>';
        echo '</div>';
        return;
    }

    // Progress level options
    $progressLevelOptions = [
        'Significant Progress' => __('Significant Progress'),
        'Some Progress' => __('Some Progress'),
        'No Change' => __('No Change'),
        'Regression' => __('Regression'),
    ];

    // Goal status options
    $goalStatusOptions = [
        'Not Started' => __('Not Started'),
        'In Progress' => __('In Progress'),
        'Achieved' => __('Achieved'),
        'Modified' => __('Modified'),
        'Discontinued' => __('Discontinued'),
    ];

    // Display child info header
    $childName = Format::name('', $plan['preferredName'], $plan['surname'], 'Student', true);

    echo '<div class="bg-white rounded-lg shadow p-4 mb-6 flex items-center">';
    if (!empty($plan['image_240'])) {
        echo '<img src="' . htmlspecialchars($plan['image_240']) . '" class="w-16 h-16 rounded-full mr-4" alt="">';
    }
    echo '<div>';
    echo '<h2 class="text-xl font-semibold">' . htmlspecialchars($childName) . '</h2>';
    echo '<p class="text-gray-600">' . __('Plan') . ': ' . htmlspecialchars($plan['title']) . ' (v' . $plan['version'] . ')</p>';
    echo '<p class="text-sm text-gray-500">' . __('Status') . ': <span class="font-medium">' . __($plan['status']) . '</span></p>';
    echo '</div>';
    echo '</div>';

    // Page header
    echo '<h2>' . __('Record Progress') . '</h2>';
    echo '<p class="text-gray-600 mb-4">' . __('Record progress for SMART goals in this intervention plan.') . '</p>';

    // Show goals summary with current progress
    echo '<div class="bg-white rounded-lg shadow p-4 mb-6">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">' . __('Current Goal Status') . '</h3>';

    echo '<div class="space-y-3">';
    foreach ($goals as $goal) {
        $statusColors = [
            'Not Started' => 'gray',
            'In Progress' => 'blue',
            'Achieved' => 'green',
            'Modified' => 'orange',
            'Discontinued' => 'red',
        ];
        $sColor = $statusColors[$goal['status']] ?? 'gray';
        $progress = (float)($goal['progressPercentage'] ?? 0);

        echo '<div class="bg-gray-50 rounded p-3">';
        echo '<div class="flex justify-between items-start mb-2">';
        echo '<h4 class="font-medium">' . htmlspecialchars($goal['title']) . '</h4>';
        echo '<span class="bg-' . $sColor . '-100 text-' . $sColor . '-800 text-xs px-2 py-1 rounded">' . __($goal['status']) . '</span>';
        echo '</div>';

        // Progress bar
        echo '<div class="flex items-center gap-3">';
        echo '<div class="flex-grow bg-gray-200 rounded-full h-2">';
        echo '<div class="bg-blue-600 h-2 rounded-full" style="width: ' . $progress . '%"></div>';
        echo '</div>';
        echo '<span class="text-sm font-medium">' . number_format($progress, 0) . '%</span>';
        echo '</div>';

        if (!empty($goal['targetDate'])) {
            echo '<p class="text-xs text-gray-500 mt-2">' . __('Target Date') . ': ' . Format::date($goal['targetDate']) . '</p>';
        }
        echo '</div>';
    }
    echo '</div>';
    echo '</div>';

    // Build goals dropdown
    $goalsDropdown = [];
    foreach ($goals as $goal) {
        $goalsDropdown[$goal['gibbonInterventionGoalID']] = $goal['title'] . ' (' . number_format($goal['progressPercentage'] ?? 0, 0) . '%)';
    }

    // Create progress form
    $form = Form::create('progressForm', $session->get('absoluteURL') . '/modules/' . $session->get('module') . '/interventionPlans_progressProcess.php');
    $form->setTitle(__('Add Progress Record'));
    $form->setClass('bg-white rounded-lg shadow p-4');

    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('gibbonInterventionPlanID', $gibbonInterventionPlanID);

    $row = $form->addRow();
        $row->addLabel('gibbonInterventionGoalID', __('Goal'))->description(__('Select the goal to record progress for.'));
        $row->addSelect('gibbonInterventionGoalID')
            ->fromArray($goalsDropdown)
            ->required()
            ->placeholder(__('Select a Goal'));

    $row = $form->addRow();
        $row->addLabel('recordDate', __('Date'))->description(__('Date of progress observation.'));
        $row->addDate('recordDate')
            ->required()
            ->setValue(Format::date(date('Y-m-d')));

    $row = $form->addRow();
        $row->addLabel('progressLevel', __('Progress Level'))->description(__('Overall assessment of progress since last update.'));
        $row->addSelect('progressLevel')
            ->fromArray($progressLevelOptions)
            ->required()
            ->placeholder(__('Select Progress Level'));

    $row = $form->addRow();
        $row->addLabel('progressNotes', __('Progress Notes'))->description(__('Describe observations and evidence of progress.'));
        $row->addTextArea('progressNotes')
            ->setRows(4)
            ->required();

    $row = $form->addRow();
        $row->addLabel('measurementValue', __('Measurement Value'))->description(__('Quantitative measurement if applicable (e.g., "3/5 trials", "80%").'));
        $row->addTextField('measurementValue')
            ->maxLength(100);

    $row = $form->addRow();
        $row->addLabel('progressPercentage', __('Progress Percentage'))->description(__('Update the overall goal progress (0-100%).'));
        $row->addNumber('progressPercentage')
            ->minimum(0)
            ->maximum(100)
            ->setValue(0);

    $row = $form->addRow();
        $row->addLabel('newStatus', __('Update Goal Status'))->description(__('Optionally update the goal status based on progress.'));
        $row->addSelect('newStatus')
            ->fromArray($goalStatusOptions)
            ->placeholder(__('Keep Current Status'));

    $row = $form->addRow();
        $row->addLabel('barriers', __('Barriers'))->description(__('Any obstacles or challenges encountered.'));
        $row->addTextArea('barriers')
            ->setRows(2);

    $row = $form->addRow();
        $row->addLabel('nextSteps', __('Next Steps'))->description(__('Recommended actions or adjustments for the next period.'));
        $row->addTextArea('nextSteps')
            ->setRows(2);

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit(__('Record Progress'));

    echo $form->getOutput();

    // Recent Progress History
    $progressRecords = $interventionPlanGateway->selectProgressByPlan($gibbonInterventionPlanID)->fetchAll();

    if (!empty($progressRecords)) {
        echo '<div class="bg-white rounded-lg shadow p-4 mt-6">';
        echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">' . __('Recent Progress Records') . '</h3>';

        echo '<div class="space-y-3">';
        $count = 0;
        foreach ($progressRecords as $progress) {
            if ($count >= 5) break; // Show only last 5 records

            $levelColors = [
                'Significant Progress' => 'green',
                'Some Progress' => 'blue',
                'No Change' => 'gray',
                'Regression' => 'red',
            ];
            $lColor = $levelColors[$progress['progressLevel']] ?? 'gray';

            echo '<div class="border-l-4 border-' . $lColor . '-500 pl-4 py-2">';
            echo '<div class="flex justify-between items-start">';
            echo '<div>';
            if (!empty($progress['goalTitle'])) {
                echo '<span class="font-medium text-blue-600">' . htmlspecialchars($progress['goalTitle']) . '</span> - ';
            }
            echo '<span class="bg-' . $lColor . '-100 text-' . $lColor . '-800 text-xs px-2 py-1 rounded">' . __($progress['progressLevel']) . '</span>';
            echo '</div>';
            echo '<span class="text-sm text-gray-500">' . Format::date($progress['recordDate']) . '</span>';
            echo '</div>';
            echo '<p class="text-sm mt-2">' . htmlspecialchars(substr($progress['progressNotes'], 0, 150)) . (strlen($progress['progressNotes']) > 150 ? '...' : '') . '</p>';
            if (!empty($progress['recordedByName'])) {
                echo '<p class="text-xs text-gray-400 mt-1">' . __('Recorded by') . ': ' . Format::name('', $progress['recordedByName'], $progress['recordedBySurname'], 'Staff', false) . '</p>';
            }
            echo '</div>';
            $count++;
        }
        echo '</div>';

        if (count($progressRecords) > 5) {
            echo '<p class="text-sm text-gray-500 mt-3">';
            echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/InterventionPlans/interventionPlans_view.php&gibbonInterventionPlanID=' . $gibbonInterventionPlanID . '" class="text-blue-600 hover:underline">' . __('View all progress records') . ' (' . count($progressRecords) . ')</a>';
            echo '</p>';
        }
        echo '</div>';
    }

    // Navigation links
    echo '<div class="mt-6 flex gap-4">';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/InterventionPlans/interventionPlans_view.php&gibbonInterventionPlanID=' . $gibbonInterventionPlanID . '" class="text-blue-600 hover:underline">' . __('View Full Plan') . '</a>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/InterventionPlans/interventionPlans_edit.php&gibbonInterventionPlanID=' . $gibbonInterventionPlanID . '" class="text-blue-600 hover:underline">' . __('Edit Plan') . '</a>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/InterventionPlans/interventionPlans.php" class="text-blue-600 hover:underline">&larr; ' . __('Back to List') . '</a>';
    echo '</div>';
}
