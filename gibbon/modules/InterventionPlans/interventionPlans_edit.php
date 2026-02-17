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
use Gibbon\Module\InterventionPlans\Domain\InterventionPlanGateway;

// Module setup - breadcrumbs
$page->breadcrumbs
    ->add(__('Intervention Plans'), 'interventionPlans.php')
    ->add(__('Edit Intervention Plan'));

// Access check
if (!isActionAccessible($guid, $connection2, '/modules/InterventionPlans/interventionPlans_edit.php')) {
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

    // Status options
    $statusOptions = [
        'Draft' => __('Draft'),
        'Active' => __('Active'),
        'Under Review' => __('Under Review'),
        'Completed' => __('Completed'),
        'Archived' => __('Archived'),
    ];

    // Review schedule options
    $reviewScheduleOptions = [
        'Monthly' => __('Monthly'),
        'Quarterly' => __('Quarterly'),
        'Biannually' => __('Biannually'),
        'Annually' => __('Annually'),
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
    echo '<p class="text-sm text-gray-500">';
    echo __('Status') . ': <span class="font-medium">' . __($plan['status']) . '</span>';
    if ($plan['parentSigned'] == 'Y') {
        echo ' | <span class="text-green-600">' . __('Parent Signed') . '</span>';
    } else {
        echo ' | <span class="text-orange-500">' . __('Awaiting Parent Signature') . '</span>';
    }
    echo '</p>';
    echo '</div>';
    echo '</div>';

    // Create form
    $form = Form::create('interventionPlanEdit', $session->get('absoluteURL') . '/modules/' . $session->get('module') . '/interventionPlans_editProcess.php');
    $form->setFactory(DatabaseFormFactory::create($pdo));

    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('gibbonInterventionPlanID', $gibbonInterventionPlanID);

    // Section: Plan Information
    $form->addRow()->addHeading(__('Plan Information'));

    $row = $form->addRow();
        $row->addLabel('gibbonPersonID', __('Child'));
        $row->addSelectStudent('gibbonPersonID', $gibbonSchoolYearID, ['allStudents' => false, 'byName' => true, 'byRoll' => false])
            ->required()
            ->selected($plan['gibbonPersonID']);

    $row = $form->addRow();
        $row->addLabel('title', __('Plan Title'));
        $row->addTextField('title')
            ->maxLength(200)
            ->required()
            ->setValue($plan['title']);

    $row = $form->addRow();
        $row->addLabel('status', __('Status'));
        $row->addSelect('status')
            ->fromArray($statusOptions)
            ->required()
            ->selected($plan['status']);

    $row = $form->addRow();
        $row->addLabel('reviewSchedule', __('Review Schedule'));
        $row->addSelect('reviewSchedule')
            ->fromArray($reviewScheduleOptions)
            ->required()
            ->selected($plan['reviewSchedule']);

    // Section: Dates
    $form->addRow()->addHeading(__('Dates'));

    $row = $form->addRow();
        $row->addLabel('effectiveDate', __('Effective Date'));
        $row->addDate('effectiveDate')
            ->required()
            ->setValue(!empty($plan['effectiveDate']) ? Format::date($plan['effectiveDate']) : '');

    $row = $form->addRow();
        $row->addLabel('endDate', __('End Date'));
        $row->addDate('endDate')
            ->setValue(!empty($plan['endDate']) ? Format::date($plan['endDate']) : '');

    $row = $form->addRow();
        $row->addLabel('nextReviewDate', __('Next Review Date'));
        $row->addDate('nextReviewDate')
            ->setValue(!empty($plan['nextReviewDate']) ? Format::date($plan['nextReviewDate']) : '');

    // Submit button
    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit(__('Save Changes'));

    echo $form->getOutput();

    // 8-Part Structure Sections
    echo '<div class="mt-8">';
    echo '<h2 class="text-2xl font-semibold mb-4">' . __('8-Part Plan Structure') . '</h2>';

    // Get existing sections data
    $strengths = $interventionPlanGateway->selectStrengthsByPlan($gibbonInterventionPlanID)->fetchAll();
    $needs = $interventionPlanGateway->selectNeedsByPlan($gibbonInterventionPlanID)->fetchAll();
    $goals = $interventionPlanGateway->selectGoalsByPlan($gibbonInterventionPlanID)->fetchAll();
    $strategies = $interventionPlanGateway->selectStrategiesByPlan($gibbonInterventionPlanID)->fetchAll();
    $monitoring = $interventionPlanGateway->selectMonitoringByPlan($gibbonInterventionPlanID)->fetchAll();
    $parentInvolvement = $interventionPlanGateway->selectParentInvolvementByPlan($gibbonInterventionPlanID)->fetchAll();
    $consultations = $interventionPlanGateway->selectConsultationsByPlan($gibbonInterventionPlanID)->fetchAll();

    // Category options for strengths
    $strengthCategories = [
        'Cognitive' => __('Cognitive'),
        'Social' => __('Social'),
        'Physical' => __('Physical'),
        'Emotional' => __('Emotional'),
        'Communication' => __('Communication'),
        'Creative' => __('Creative'),
        'Other' => __('Other'),
    ];

    // Category options for needs
    $needCategories = [
        'Communication' => __('Communication'),
        'Behavior' => __('Behavior'),
        'Academic' => __('Academic'),
        'Sensory' => __('Sensory'),
        'Motor' => __('Motor'),
        'Social' => __('Social'),
        'Self-Care' => __('Self-Care'),
        'Other' => __('Other'),
    ];

    // Priority options
    $priorityOptions = [
        'Low' => __('Low'),
        'Medium' => __('Medium'),
        'High' => __('High'),
        'Critical' => __('Critical'),
    ];

    // Responsible party options
    $responsiblePartyOptions = [
        'Educator' => __('Educator'),
        'Parent' => __('Parent'),
        'Therapist' => __('Therapist'),
        'Team' => __('Team'),
        'Other' => __('Other'),
    ];

    // Monitoring method options
    $monitoringMethodOptions = [
        'Observation' => __('Observation'),
        'Assessment' => __('Assessment'),
        'Data Collection' => __('Data Collection'),
        'Checklist' => __('Checklist'),
        'Portfolio' => __('Portfolio'),
        'Other' => __('Other'),
    ];

    // Monitoring frequency options
    $frequencyOptions = [
        'Daily' => __('Daily'),
        'Weekly' => __('Weekly'),
        'Biweekly' => __('Biweekly'),
        'Monthly' => __('Monthly'),
        'Quarterly' => __('Quarterly'),
    ];

    // Activity type options for parent involvement
    $activityTypeOptions = [
        'Home Activity' => __('Home Activity'),
        'Communication' => __('Communication'),
        'Training' => __('Training'),
        'Meeting' => __('Meeting'),
        'Resources' => __('Resources'),
        'Other' => __('Other'),
    ];

    // Specialist type options
    $specialistTypeOptions = [
        'Speech Therapist' => __('Speech Therapist'),
        'Occupational Therapist' => __('Occupational Therapist'),
        'Psychologist' => __('Psychologist'),
        'Pediatrician' => __('Pediatrician'),
        'Behavioral Specialist' => __('Behavioral Specialist'),
        'Special Education Consultant' => __('Special Education Consultant'),
        'Other' => __('Other'),
    ];

    // Part 1: Identification (Child info - display only)
    echo '<div class="bg-white rounded-lg shadow p-4 mb-4">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">';
    echo '<span class="text-blue-600">1.</span> ' . __('Identification & History');
    echo '</h3>';
    echo '<p class="text-gray-600">' . __('Child information is automatically populated from the student record.') . '</p>';
    echo '<div class="grid grid-cols-2 gap-4 mt-3">';
    echo '<div><span class="font-medium">' . __('Name') . ':</span> ' . htmlspecialchars($childName) . '</div>';
    if (!empty($plan['dob'])) {
        echo '<div><span class="font-medium">' . __('Date of Birth') . ':</span> ' . Format::date($plan['dob']) . '</div>';
    }
    echo '</div>';
    echo '</div>';

    // Part 2: Strengths
    echo '<div class="bg-white rounded-lg shadow p-4 mb-4">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">';
    echo '<span class="text-blue-600">2.</span> ' . __('Strengths');
    echo ' <span class="text-sm text-gray-500 font-normal">(' . count($strengths) . ' ' . __('items') . ')</span>';
    echo '</h3>';

    if (!empty($strengths)) {
        echo '<div class="space-y-2 mb-4">';
        foreach ($strengths as $strength) {
            echo '<div class="bg-green-50 rounded p-3">';
            echo '<span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded">' . __($strength['category']) . '</span>';
            echo '<p class="mt-2 text-sm">' . htmlspecialchars($strength['description']) . '</p>';
            if (!empty($strength['examples'])) {
                echo '<p class="mt-1 text-xs text-gray-500">' . __('Examples') . ': ' . htmlspecialchars($strength['examples']) . '</p>';
            }
            echo '</div>';
        }
        echo '</div>';
    } else {
        echo '<p class="text-gray-500 text-sm mb-4">' . __('No strengths recorded yet.') . '</p>';
    }

    // Add Strength Form
    $addStrengthForm = Form::create('addStrength', $session->get('absoluteURL') . '/modules/' . $session->get('module') . '/interventionPlans_editProcess.php?action=addStrength');
    $addStrengthForm->addHiddenValue('gibbonInterventionPlanID', $gibbonInterventionPlanID);
    $addStrengthForm->addHiddenValue('address', $session->get('address'));
    $addStrengthForm->setClass('bg-gray-50 rounded p-3');

    $row = $addStrengthForm->addRow();
        $row->addLabel('strengthCategory', __('Category'));
        $row->addSelect('strengthCategory')->fromArray($strengthCategories)->required();

    $row = $addStrengthForm->addRow();
        $row->addLabel('strengthDescription', __('Description'));
        $row->addTextArea('strengthDescription')->setRows(2)->required();

    $row = $addStrengthForm->addRow();
        $row->addLabel('strengthExamples', __('Examples'));
        $row->addTextField('strengthExamples');

    $row = $addStrengthForm->addRow();
        $row->addSubmit(__('Add Strength'));

    echo $addStrengthForm->getOutput();
    echo '</div>';

    // Part 3: Needs
    echo '<div class="bg-white rounded-lg shadow p-4 mb-4">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">';
    echo '<span class="text-blue-600">3.</span> ' . __('Needs');
    echo ' <span class="text-sm text-gray-500 font-normal">(' . count($needs) . ' ' . __('items') . ')</span>';
    echo '</h3>';

    if (!empty($needs)) {
        echo '<div class="space-y-2 mb-4">';
        foreach ($needs as $need) {
            $priorityColors = [
                'Critical' => 'red',
                'High' => 'orange',
                'Medium' => 'yellow',
                'Low' => 'green',
            ];
            $pColor = $priorityColors[$need['priority']] ?? 'gray';

            echo '<div class="bg-orange-50 rounded p-3">';
            echo '<span class="bg-orange-100 text-orange-800 text-xs px-2 py-1 rounded">' . __($need['category']) . '</span>';
            echo ' <span class="bg-' . $pColor . '-100 text-' . $pColor . '-800 text-xs px-2 py-1 rounded">' . __($need['priority']) . '</span>';
            echo '<p class="mt-2 text-sm">' . htmlspecialchars($need['description']) . '</p>';
            if (!empty($need['baseline'])) {
                echo '<p class="mt-1 text-xs text-gray-500">' . __('Baseline') . ': ' . htmlspecialchars($need['baseline']) . '</p>';
            }
            echo '</div>';
        }
        echo '</div>';
    } else {
        echo '<p class="text-gray-500 text-sm mb-4">' . __('No needs recorded yet.') . '</p>';
    }

    // Add Need Form
    $addNeedForm = Form::create('addNeed', $session->get('absoluteURL') . '/modules/' . $session->get('module') . '/interventionPlans_editProcess.php?action=addNeed');
    $addNeedForm->addHiddenValue('gibbonInterventionPlanID', $gibbonInterventionPlanID);
    $addNeedForm->addHiddenValue('address', $session->get('address'));
    $addNeedForm->setClass('bg-gray-50 rounded p-3');

    $row = $addNeedForm->addRow();
        $row->addLabel('needCategory', __('Category'));
        $row->addSelect('needCategory')->fromArray($needCategories)->required();

    $row = $addNeedForm->addRow();
        $row->addLabel('needPriority', __('Priority'));
        $row->addSelect('needPriority')->fromArray($priorityOptions)->required()->selected('Medium');

    $row = $addNeedForm->addRow();
        $row->addLabel('needDescription', __('Description'));
        $row->addTextArea('needDescription')->setRows(2)->required();

    $row = $addNeedForm->addRow();
        $row->addLabel('needBaseline', __('Baseline Assessment'));
        $row->addTextArea('needBaseline')->setRows(2);

    $row = $addNeedForm->addRow();
        $row->addSubmit(__('Add Need'));

    echo $addNeedForm->getOutput();
    echo '</div>';

    // Part 4: SMART Goals
    echo '<div class="bg-white rounded-lg shadow p-4 mb-4">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">';
    echo '<span class="text-blue-600">4.</span> ' . __('SMART Goals');
    echo ' <span class="text-sm text-gray-500 font-normal">(' . count($goals) . ' ' . __('items') . ')</span>';
    echo '</h3>';

    if (!empty($goals)) {
        echo '<div class="space-y-3 mb-4">';
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

            echo '<div class="bg-blue-50 rounded p-3">';
            echo '<div class="flex justify-between items-start">';
            echo '<h4 class="font-medium">' . htmlspecialchars($goal['title']) . '</h4>';
            echo '<span class="bg-' . $sColor . '-100 text-' . $sColor . '-800 text-xs px-2 py-1 rounded">' . __($goal['status']) . '</span>';
            echo '</div>';
            echo '<p class="text-sm mt-1">' . htmlspecialchars($goal['description']) . '</p>';

            // Progress bar
            echo '<div class="mt-2">';
            echo '<div class="flex justify-between text-xs text-gray-500 mb-1">';
            echo '<span>' . __('Progress') . '</span>';
            echo '<span>' . number_format($progress, 0) . '%</span>';
            echo '</div>';
            echo '<div class="w-full bg-gray-200 rounded-full h-2">';
            echo '<div class="bg-blue-600 h-2 rounded-full" style="width: ' . $progress . '%"></div>';
            echo '</div>';
            echo '</div>';

            // SMART details
            echo '<div class="grid grid-cols-2 gap-2 mt-2 text-xs text-gray-600">';
            if (!empty($goal['measurementCriteria'])) {
                echo '<div><span class="font-medium">M:</span> ' . htmlspecialchars(substr($goal['measurementCriteria'], 0, 50)) . '</div>';
            }
            if (!empty($goal['targetDate'])) {
                echo '<div><span class="font-medium">T:</span> ' . Format::date($goal['targetDate']) . '</div>';
            }
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';
    } else {
        echo '<p class="text-gray-500 text-sm mb-4">' . __('No SMART goals defined yet.') . '</p>';
    }

    // Build needs dropdown for goal form
    $needsDropdown = ['' => __('Select Need (Optional)')];
    foreach ($needs as $need) {
        $needsDropdown[$need['gibbonInterventionNeedID']] = substr($need['description'], 0, 50) . '...';
    }

    // Add Goal Form
    $addGoalForm = Form::create('addGoal', $session->get('absoluteURL') . '/modules/' . $session->get('module') . '/interventionPlans_editProcess.php?action=addGoal');
    $addGoalForm->addHiddenValue('gibbonInterventionPlanID', $gibbonInterventionPlanID);
    $addGoalForm->addHiddenValue('address', $session->get('address'));
    $addGoalForm->setClass('bg-gray-50 rounded p-3');

    $row = $addGoalForm->addRow();
        $row->addLabel('goalNeedID', __('Linked Need'));
        $row->addSelect('goalNeedID')->fromArray($needsDropdown);

    $row = $addGoalForm->addRow();
        $row->addLabel('goalTitle', __('Goal Title'));
        $row->addTextField('goalTitle')->required()->maxLength(200);

    $row = $addGoalForm->addRow();
        $row->addLabel('goalDescription', __('Specific - What is the goal?'));
        $row->addTextArea('goalDescription')->setRows(2)->required();

    $row = $addGoalForm->addRow();
        $row->addLabel('goalMeasurement', __('Measurable - How will progress be measured?'));
        $row->addTextArea('goalMeasurement')->setRows(2)->required();

    $row = $addGoalForm->addRow();
        $row->addLabel('goalTargetDate', __('Time-bound - Target completion date'));
        $row->addDate('goalTargetDate');

    $row = $addGoalForm->addRow();
        $row->addSubmit(__('Add SMART Goal'));

    echo $addGoalForm->getOutput();
    echo '</div>';

    // Part 5: Strategies
    echo '<div class="bg-white rounded-lg shadow p-4 mb-4">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">';
    echo '<span class="text-blue-600">5.</span> ' . __('Strategies');
    echo ' <span class="text-sm text-gray-500 font-normal">(' . count($strategies) . ' ' . __('items') . ')</span>';
    echo '</h3>';

    if (!empty($strategies)) {
        echo '<div class="space-y-2 mb-4">';
        foreach ($strategies as $strategy) {
            echo '<div class="bg-purple-50 rounded p-3">';
            echo '<div class="flex justify-between items-start">';
            echo '<h4 class="font-medium">' . htmlspecialchars($strategy['title']) . '</h4>';
            echo '<span class="bg-purple-100 text-purple-800 text-xs px-2 py-1 rounded">' . __($strategy['responsibleParty']) . '</span>';
            echo '</div>';
            echo '<p class="text-sm mt-1">' . htmlspecialchars($strategy['description']) . '</p>';
            if (!empty($strategy['frequency'])) {
                echo '<p class="text-xs text-gray-500 mt-1">' . __('Frequency') . ': ' . htmlspecialchars($strategy['frequency']) . '</p>';
            }
            if (!empty($strategy['goalTitle'])) {
                echo '<p class="text-xs text-gray-500">' . __('Goal') . ': ' . htmlspecialchars($strategy['goalTitle']) . '</p>';
            }
            echo '</div>';
        }
        echo '</div>';
    } else {
        echo '<p class="text-gray-500 text-sm mb-4">' . __('No strategies defined yet.') . '</p>';
    }

    // Build goals dropdown for strategy form
    $goalsDropdown = ['' => __('Select Goal (Optional)')];
    foreach ($goals as $goal) {
        $goalsDropdown[$goal['gibbonInterventionGoalID']] = substr($goal['title'], 0, 50);
    }

    // Add Strategy Form
    $addStrategyForm = Form::create('addStrategy', $session->get('absoluteURL') . '/modules/' . $session->get('module') . '/interventionPlans_editProcess.php?action=addStrategy');
    $addStrategyForm->addHiddenValue('gibbonInterventionPlanID', $gibbonInterventionPlanID);
    $addStrategyForm->addHiddenValue('address', $session->get('address'));
    $addStrategyForm->setClass('bg-gray-50 rounded p-3');

    $row = $addStrategyForm->addRow();
        $row->addLabel('strategyGoalID', __('Linked Goal'));
        $row->addSelect('strategyGoalID')->fromArray($goalsDropdown);

    $row = $addStrategyForm->addRow();
        $row->addLabel('strategyTitle', __('Strategy Title'));
        $row->addTextField('strategyTitle')->required()->maxLength(200);

    $row = $addStrategyForm->addRow();
        $row->addLabel('strategyDescription', __('Description'));
        $row->addTextArea('strategyDescription')->setRows(2)->required();

    $row = $addStrategyForm->addRow();
        $row->addLabel('strategyResponsible', __('Responsible Party'));
        $row->addSelect('strategyResponsible')->fromArray($responsiblePartyOptions)->required()->selected('Educator');

    $row = $addStrategyForm->addRow();
        $row->addLabel('strategyFrequency', __('Frequency'));
        $row->addTextField('strategyFrequency')->maxLength(100);

    $row = $addStrategyForm->addRow();
        $row->addSubmit(__('Add Strategy'));

    echo $addStrategyForm->getOutput();
    echo '</div>';

    // Part 6: Monitoring
    echo '<div class="bg-white rounded-lg shadow p-4 mb-4">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">';
    echo '<span class="text-blue-600">6.</span> ' . __('Monitoring');
    echo ' <span class="text-sm text-gray-500 font-normal">(' . count($monitoring) . ' ' . __('items') . ')</span>';
    echo '</h3>';

    if (!empty($monitoring)) {
        echo '<div class="space-y-2 mb-4">';
        foreach ($monitoring as $monitor) {
            echo '<div class="bg-teal-50 rounded p-3">';
            echo '<div class="flex gap-2 mb-2">';
            echo '<span class="bg-teal-100 text-teal-800 text-xs px-2 py-1 rounded">' . __($monitor['method']) . '</span>';
            echo '<span class="bg-gray-100 text-gray-800 text-xs px-2 py-1 rounded">' . __($monitor['frequency']) . '</span>';
            echo '</div>';
            echo '<p class="text-sm">' . htmlspecialchars($monitor['description']) . '</p>';
            if (!empty($monitor['goalTitle'])) {
                echo '<p class="text-xs text-gray-500 mt-1">' . __('Goal') . ': ' . htmlspecialchars($monitor['goalTitle']) . '</p>';
            }
            echo '</div>';
        }
        echo '</div>';
    } else {
        echo '<p class="text-gray-500 text-sm mb-4">' . __('No monitoring methods defined yet.') . '</p>';
    }

    // Add Monitoring Form
    $addMonitoringForm = Form::create('addMonitoring', $session->get('absoluteURL') . '/modules/' . $session->get('module') . '/interventionPlans_editProcess.php?action=addMonitoring');
    $addMonitoringForm->addHiddenValue('gibbonInterventionPlanID', $gibbonInterventionPlanID);
    $addMonitoringForm->addHiddenValue('address', $session->get('address'));
    $addMonitoringForm->setClass('bg-gray-50 rounded p-3');

    $row = $addMonitoringForm->addRow();
        $row->addLabel('monitoringGoalID', __('Linked Goal'));
        $row->addSelect('monitoringGoalID')->fromArray($goalsDropdown);

    $row = $addMonitoringForm->addRow();
        $row->addLabel('monitoringMethod', __('Method'));
        $row->addSelect('monitoringMethod')->fromArray($monitoringMethodOptions)->required();

    $row = $addMonitoringForm->addRow();
        $row->addLabel('monitoringDescription', __('Description'));
        $row->addTextArea('monitoringDescription')->setRows(2)->required();

    $row = $addMonitoringForm->addRow();
        $row->addLabel('monitoringFrequency', __('Frequency'));
        $row->addSelect('monitoringFrequency')->fromArray($frequencyOptions)->required()->selected('Weekly');

    $row = $addMonitoringForm->addRow();
        $row->addLabel('monitoringResponsible', __('Responsible Party'));
        $row->addSelect('monitoringResponsible')->fromArray($responsiblePartyOptions)->required()->selected('Educator');

    $row = $addMonitoringForm->addRow();
        $row->addSubmit(__('Add Monitoring Method'));

    echo $addMonitoringForm->getOutput();
    echo '</div>';

    // Part 7: Parent Involvement
    echo '<div class="bg-white rounded-lg shadow p-4 mb-4">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">';
    echo '<span class="text-blue-600">7.</span> ' . __('Parent Involvement');
    echo ' <span class="text-sm text-gray-500 font-normal">(' . count($parentInvolvement) . ' ' . __('items') . ')</span>';
    echo '</h3>';

    if (!empty($parentInvolvement)) {
        echo '<div class="space-y-2 mb-4">';
        foreach ($parentInvolvement as $involvement) {
            echo '<div class="bg-yellow-50 rounded p-3">';
            echo '<div class="flex justify-between items-start">';
            echo '<h4 class="font-medium">' . htmlspecialchars($involvement['title']) . '</h4>';
            echo '<span class="bg-yellow-100 text-yellow-800 text-xs px-2 py-1 rounded">' . __($involvement['activityType']) . '</span>';
            echo '</div>';
            echo '<p class="text-sm mt-1">' . htmlspecialchars($involvement['description']) . '</p>';
            if (!empty($involvement['frequency'])) {
                echo '<p class="text-xs text-gray-500 mt-1">' . __('Frequency') . ': ' . htmlspecialchars($involvement['frequency']) . '</p>';
            }
            echo '</div>';
        }
        echo '</div>';
    } else {
        echo '<p class="text-gray-500 text-sm mb-4">' . __('No parent involvement activities defined yet.') . '</p>';
    }

    // Add Parent Involvement Form
    $addParentForm = Form::create('addParentInvolvement', $session->get('absoluteURL') . '/modules/' . $session->get('module') . '/interventionPlans_editProcess.php?action=addParentInvolvement');
    $addParentForm->addHiddenValue('gibbonInterventionPlanID', $gibbonInterventionPlanID);
    $addParentForm->addHiddenValue('address', $session->get('address'));
    $addParentForm->setClass('bg-gray-50 rounded p-3');

    $row = $addParentForm->addRow();
        $row->addLabel('parentActivityType', __('Activity Type'));
        $row->addSelect('parentActivityType')->fromArray($activityTypeOptions)->required();

    $row = $addParentForm->addRow();
        $row->addLabel('parentTitle', __('Title'));
        $row->addTextField('parentTitle')->required()->maxLength(200);

    $row = $addParentForm->addRow();
        $row->addLabel('parentDescription', __('Description'));
        $row->addTextArea('parentDescription')->setRows(2)->required();

    $row = $addParentForm->addRow();
        $row->addLabel('parentFrequency', __('Frequency'));
        $row->addTextField('parentFrequency')->maxLength(50);

    $row = $addParentForm->addRow();
        $row->addSubmit(__('Add Parent Involvement'));

    echo $addParentForm->getOutput();
    echo '</div>';

    // Part 8: Consultations
    echo '<div class="bg-white rounded-lg shadow p-4 mb-4">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">';
    echo '<span class="text-blue-600">8.</span> ' . __('External Consultations');
    echo ' <span class="text-sm text-gray-500 font-normal">(' . count($consultations) . ' ' . __('items') . ')</span>';
    echo '</h3>';

    if (!empty($consultations)) {
        echo '<div class="space-y-2 mb-4">';
        foreach ($consultations as $consultation) {
            echo '<div class="bg-indigo-50 rounded p-3">';
            echo '<div class="flex justify-between items-start">';
            echo '<div>';
            echo '<span class="bg-indigo-100 text-indigo-800 text-xs px-2 py-1 rounded">' . __($consultation['specialistType']) . '</span>';
            if (!empty($consultation['specialistName'])) {
                echo ' <span class="font-medium ml-2">' . htmlspecialchars($consultation['specialistName']) . '</span>';
            }
            echo '</div>';
            if (!empty($consultation['consultationDate'])) {
                echo '<span class="text-xs text-gray-500">' . Format::date($consultation['consultationDate']) . '</span>';
            }
            echo '</div>';
            echo '<p class="text-sm mt-2">' . htmlspecialchars($consultation['purpose']) . '</p>';
            if (!empty($consultation['recommendations'])) {
                echo '<p class="text-xs text-gray-600 mt-1">' . __('Recommendations') . ': ' . htmlspecialchars(substr($consultation['recommendations'], 0, 100)) . '</p>';
            }
            echo '</div>';
        }
        echo '</div>';
    } else {
        echo '<p class="text-gray-500 text-sm mb-4">' . __('No external consultations recorded yet.') . '</p>';
    }

    // Add Consultation Form
    $addConsultationForm = Form::create('addConsultation', $session->get('absoluteURL') . '/modules/' . $session->get('module') . '/interventionPlans_editProcess.php?action=addConsultation');
    $addConsultationForm->addHiddenValue('gibbonInterventionPlanID', $gibbonInterventionPlanID);
    $addConsultationForm->addHiddenValue('address', $session->get('address'));
    $addConsultationForm->setClass('bg-gray-50 rounded p-3');

    $row = $addConsultationForm->addRow();
        $row->addLabel('consultationSpecialistType', __('Specialist Type'));
        $row->addSelect('consultationSpecialistType')->fromArray($specialistTypeOptions)->required();

    $row = $addConsultationForm->addRow();
        $row->addLabel('consultationSpecialistName', __('Specialist Name'));
        $row->addTextField('consultationSpecialistName')->maxLength(200);

    $row = $addConsultationForm->addRow();
        $row->addLabel('consultationOrganization', __('Organization'));
        $row->addTextField('consultationOrganization')->maxLength(200);

    $row = $addConsultationForm->addRow();
        $row->addLabel('consultationPurpose', __('Purpose'));
        $row->addTextArea('consultationPurpose')->setRows(2)->required();

    $row = $addConsultationForm->addRow();
        $row->addLabel('consultationDate', __('Consultation Date'));
        $row->addDate('consultationDate');

    $row = $addConsultationForm->addRow();
        $row->addLabel('consultationRecommendations', __('Recommendations'));
        $row->addTextArea('consultationRecommendations')->setRows(2);

    $row = $addConsultationForm->addRow();
        $row->addSubmit(__('Add Consultation'));

    echo $addConsultationForm->getOutput();
    echo '</div>';

    echo '</div>'; // End 8-part structure

    // Back button
    echo '<div class="mt-4">';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/InterventionPlans/interventionPlans.php" class="text-blue-600 hover:underline">&larr; ' . __('Back to Intervention Plans') . '</a>';
    echo '</div>';
}
