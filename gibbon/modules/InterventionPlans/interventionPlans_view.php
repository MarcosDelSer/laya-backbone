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

use Gibbon\Services\Format;
use Gibbon\Tables\DataTable;
use Gibbon\Module\InterventionPlans\Domain\InterventionPlanGateway;

// Module setup - breadcrumbs
$page->breadcrumbs
    ->add(__('Intervention Plans'), 'interventionPlans.php')
    ->add(__('View Intervention Plan'));

// Access check
if (!isActionAccessible($guid, $connection2, '/modules/InterventionPlans/interventionPlans_view.php')) {
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

    // Get gateway
    $interventionPlanGateway = $container->get(InterventionPlanGateway::class);

    // Get plan details
    $plan = $interventionPlanGateway->getPlanDetails($gibbonInterventionPlanID);

    if (empty($plan)) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }

    // Get all sections data
    $strengths = $interventionPlanGateway->selectStrengthsByPlan($gibbonInterventionPlanID)->fetchAll();
    $needs = $interventionPlanGateway->selectNeedsByPlan($gibbonInterventionPlanID)->fetchAll();
    $goals = $interventionPlanGateway->selectGoalsByPlan($gibbonInterventionPlanID)->fetchAll();
    $strategies = $interventionPlanGateway->selectStrategiesByPlan($gibbonInterventionPlanID)->fetchAll();
    $monitoring = $interventionPlanGateway->selectMonitoringByPlan($gibbonInterventionPlanID)->fetchAll();
    $parentInvolvement = $interventionPlanGateway->selectParentInvolvementByPlan($gibbonInterventionPlanID)->fetchAll();
    $consultations = $interventionPlanGateway->selectConsultationsByPlan($gibbonInterventionPlanID)->fetchAll();
    $progressRecords = $interventionPlanGateway->selectProgressByPlan($gibbonInterventionPlanID)->fetchAll();
    $versions = $interventionPlanGateway->selectVersionsByPlan($gibbonInterventionPlanID)->fetchAll();

    // Get goal statistics
    $goalStats = $interventionPlanGateway->getGoalStatsByPlan($gibbonInterventionPlanID);

    // Display child info header
    $childName = Format::name('', $plan['preferredName'], $plan['surname'], 'Student', true);

    // Status color mapping
    $statusColors = [
        'Draft' => 'gray',
        'Active' => 'green',
        'Under Review' => 'orange',
        'Completed' => 'blue',
        'Archived' => 'gray',
    ];
    $statusColor = $statusColors[$plan['status']] ?? 'gray';

    // Header card with child info and plan status
    echo '<div class="bg-white rounded-lg shadow p-4 mb-6">';
    echo '<div class="flex items-center justify-between">';
    echo '<div class="flex items-center">';
    if (!empty($plan['image_240'])) {
        echo '<img src="' . htmlspecialchars($plan['image_240']) . '" class="w-16 h-16 rounded-full mr-4" alt="">';
    }
    echo '<div>';
    echo '<h2 class="text-xl font-semibold">' . htmlspecialchars($childName) . '</h2>';
    echo '<p class="text-gray-600">' . htmlspecialchars($plan['title']) . ' <span class="text-gray-400">(v' . $plan['version'] . ')</span></p>';
    if (!empty($plan['dob'])) {
        echo '<p class="text-sm text-gray-500">' . __('DOB') . ': ' . Format::date($plan['dob']) . '</p>';
    }
    echo '</div>';
    echo '</div>';

    // Status and signature info
    echo '<div class="text-right">';
    echo '<span class="inline-block bg-' . $statusColor . '-100 text-' . $statusColor . '-800 px-3 py-1 rounded text-sm font-medium">' . __($plan['status']) . '</span>';
    if ($plan['parentSigned'] == 'Y') {
        echo '<p class="text-green-600 text-sm mt-2"><span class="font-medium">' . __('Parent Signed') . '</span>';
        if (!empty($plan['parentSignatureDate'])) {
            echo ' - ' . Format::date($plan['parentSignatureDate']);
        }
        echo '</p>';
    } else {
        echo '<p class="text-orange-500 text-sm mt-2">' . __('Awaiting Parent Signature') . '</p>';
    }
    echo '</div>';
    echo '</div>';

    // Plan dates row
    echo '<div class="mt-4 pt-4 border-t grid grid-cols-2 md:grid-cols-5 gap-4 text-sm">';
    echo '<div><span class="text-gray-500">' . __('Effective Date') . ':</span><br><span class="font-medium">' . (!empty($plan['effectiveDate']) ? Format::date($plan['effectiveDate']) : '-') . '</span></div>';
    echo '<div><span class="text-gray-500">' . __('End Date') . ':</span><br><span class="font-medium">' . (!empty($plan['endDate']) ? Format::date($plan['endDate']) : '-') . '</span></div>';
    echo '<div><span class="text-gray-500">' . __('Review Schedule') . ':</span><br><span class="font-medium">' . __($plan['reviewSchedule'] ?? '-') . '</span></div>';

    // Next review with overdue warning
    echo '<div><span class="text-gray-500">' . __('Next Review') . ':</span><br>';
    if (!empty($plan['nextReviewDate'])) {
        $reviewDate = new DateTime($plan['nextReviewDate']);
        $today = new DateTime();
        if ($reviewDate < $today) {
            echo '<span class="font-medium text-red-600">' . Format::date($plan['nextReviewDate']) . ' (' . __('Overdue') . ')</span>';
        } else {
            echo '<span class="font-medium">' . Format::date($plan['nextReviewDate']) . '</span>';
        }
    } else {
        echo '<span class="font-medium">-</span>';
    }
    echo '</div>';

    echo '<div><span class="text-gray-500">' . __('Created By') . ':</span><br>';
    if (!empty($plan['createdByName'])) {
        echo '<span class="font-medium">' . Format::name('', $plan['createdByName'], $plan['createdBySurname'], 'Staff', false) . '</span>';
    } else {
        echo '<span class="font-medium">-</span>';
    }
    echo '</div>';
    echo '</div>';
    echo '</div>';

    // Goal Progress Summary Card
    if (!empty($goals)) {
        echo '<div class="bg-white rounded-lg shadow p-4 mb-6">';
        echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">' . __('Goal Progress Overview') . '</h3>';
        echo '<div class="grid grid-cols-2 md:grid-cols-6 gap-4 text-center">';

        echo '<div class="bg-gray-50 rounded p-2">';
        echo '<span class="block text-2xl font-bold">' . ($goalStats['totalGoals'] ?? 0) . '</span>';
        echo '<span class="text-xs text-gray-500">' . __('Total Goals') . '</span>';
        echo '</div>';

        echo '<div class="bg-gray-50 rounded p-2">';
        echo '<span class="block text-2xl font-bold text-gray-500">' . ($goalStats['notStartedGoals'] ?? 0) . '</span>';
        echo '<span class="text-xs text-gray-500">' . __('Not Started') . '</span>';
        echo '</div>';

        echo '<div class="bg-blue-50 rounded p-2">';
        echo '<span class="block text-2xl font-bold text-blue-600">' . ($goalStats['inProgressGoals'] ?? 0) . '</span>';
        echo '<span class="text-xs text-gray-500">' . __('In Progress') . '</span>';
        echo '</div>';

        echo '<div class="bg-green-50 rounded p-2">';
        echo '<span class="block text-2xl font-bold text-green-600">' . ($goalStats['achievedGoals'] ?? 0) . '</span>';
        echo '<span class="text-xs text-gray-500">' . __('Achieved') . '</span>';
        echo '</div>';

        echo '<div class="bg-orange-50 rounded p-2">';
        echo '<span class="block text-2xl font-bold text-orange-600">' . ($goalStats['modifiedGoals'] ?? 0) . '</span>';
        echo '<span class="text-xs text-gray-500">' . __('Modified') . '</span>';
        echo '</div>';

        echo '<div class="bg-purple-50 rounded p-2">';
        echo '<span class="block text-2xl font-bold text-purple-600">' . number_format($goalStats['averageProgress'] ?? 0, 0) . '%</span>';
        echo '<span class="text-xs text-gray-500">' . __('Avg Progress') . '</span>';
        echo '</div>';

        echo '</div>';
        echo '</div>';
    }

    // Action Buttons
    echo '<div class="flex flex-wrap gap-2 mb-6">';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/InterventionPlans/interventionPlans_edit.php&gibbonInterventionPlanID=' . $gibbonInterventionPlanID . '" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">' . __('Edit Plan') . '</a>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/InterventionPlans/interventionPlans_progress.php&gibbonInterventionPlanID=' . $gibbonInterventionPlanID . '" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">' . __('Add Progress') . '</a>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/InterventionPlans/interventionPlans.php" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">' . __('Back to List') . '</a>';
    echo '</div>';

    // 8-Part Structure Sections
    echo '<div class="space-y-4">';

    // Part 1: Identification & History
    echo '<div class="bg-white rounded-lg shadow p-4">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">';
    echo '<span class="text-blue-600">1.</span> ' . __('Identification & History');
    echo '</h3>';
    echo '<p class="text-gray-600 text-sm">' . __('Child information is automatically populated from the student record.') . '</p>';
    echo '<div class="grid grid-cols-2 gap-4 mt-3">';
    echo '<div><span class="text-gray-500">' . __('Name') . ':</span><br><span class="font-medium">' . htmlspecialchars($childName) . '</span></div>';
    if (!empty($plan['dob'])) {
        echo '<div><span class="text-gray-500">' . __('Date of Birth') . ':</span><br><span class="font-medium">' . Format::date($plan['dob']) . '</span></div>';
    }
    echo '</div>';
    echo '</div>';

    // Part 2: Strengths
    echo '<div class="bg-white rounded-lg shadow p-4">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">';
    echo '<span class="text-blue-600">2.</span> ' . __('Strengths');
    echo ' <span class="text-sm text-gray-500 font-normal">(' . count($strengths) . ' ' . __('items') . ')</span>';
    echo '</h3>';

    if (!empty($strengths)) {
        echo '<div class="space-y-2">';
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
        echo '<p class="text-gray-500 text-sm">' . __('No strengths recorded yet.') . '</p>';
    }
    echo '</div>';

    // Part 3: Needs
    echo '<div class="bg-white rounded-lg shadow p-4">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">';
    echo '<span class="text-blue-600">3.</span> ' . __('Needs');
    echo ' <span class="text-sm text-gray-500 font-normal">(' . count($needs) . ' ' . __('items') . ')</span>';
    echo '</h3>';

    if (!empty($needs)) {
        echo '<div class="space-y-2">';
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
            echo ' <span class="bg-' . $pColor . '-100 text-' . $pColor . '-800 text-xs px-2 py-1 rounded">' . __($need['priority']) . ' ' . __('Priority') . '</span>';
            echo '<p class="mt-2 text-sm">' . htmlspecialchars($need['description']) . '</p>';
            if (!empty($need['baseline'])) {
                echo '<p class="mt-1 text-xs text-gray-500">' . __('Baseline') . ': ' . htmlspecialchars($need['baseline']) . '</p>';
            }
            echo '</div>';
        }
        echo '</div>';
    } else {
        echo '<p class="text-gray-500 text-sm">' . __('No needs recorded yet.') . '</p>';
    }
    echo '</div>';

    // Part 4: SMART Goals
    echo '<div class="bg-white rounded-lg shadow p-4">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">';
    echo '<span class="text-blue-600">4.</span> ' . __('SMART Goals');
    echo ' <span class="text-sm text-gray-500 font-normal">(' . count($goals) . ' ' . __('items') . ')</span>';
    echo '</h3>';

    if (!empty($goals)) {
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
            echo '<div class="grid grid-cols-2 gap-2 mt-3 text-xs">';
            if (!empty($goal['measurementCriteria'])) {
                echo '<div class="bg-white rounded p-2"><span class="font-medium text-blue-600">M - ' . __('Measurable') . ':</span><br>' . htmlspecialchars($goal['measurementCriteria']) . '</div>';
            }
            if (!empty($goal['achievabilityNotes'])) {
                echo '<div class="bg-white rounded p-2"><span class="font-medium text-blue-600">A - ' . __('Achievable') . ':</span><br>' . htmlspecialchars($goal['achievabilityNotes']) . '</div>';
            }
            if (!empty($goal['relevanceNotes'])) {
                echo '<div class="bg-white rounded p-2"><span class="font-medium text-blue-600">R - ' . __('Relevant') . ':</span><br>' . htmlspecialchars($goal['relevanceNotes']) . '</div>';
            }
            if (!empty($goal['targetDate'])) {
                echo '<div class="bg-white rounded p-2"><span class="font-medium text-blue-600">T - ' . __('Time-bound') . ':</span><br>' . Format::date($goal['targetDate']) . '</div>';
            }
            echo '</div>';

            // Linked need
            if (!empty($goal['needDescription'])) {
                echo '<p class="text-xs text-gray-500 mt-2 pt-2 border-t">' . __('Linked Need') . ': ' . htmlspecialchars(substr($goal['needDescription'], 0, 100)) . '</p>';
            }
            echo '</div>';
        }
        echo '</div>';
    } else {
        echo '<p class="text-gray-500 text-sm">' . __('No SMART goals defined yet.') . '</p>';
    }
    echo '</div>';

    // Part 5: Strategies
    echo '<div class="bg-white rounded-lg shadow p-4">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">';
    echo '<span class="text-blue-600">5.</span> ' . __('Strategies');
    echo ' <span class="text-sm text-gray-500 font-normal">(' . count($strategies) . ' ' . __('items') . ')</span>';
    echo '</h3>';

    if (!empty($strategies)) {
        echo '<div class="space-y-2">';
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
            if (!empty($strategy['materialsNeeded'])) {
                echo '<p class="text-xs text-gray-500">' . __('Materials') . ': ' . htmlspecialchars($strategy['materialsNeeded']) . '</p>';
            }
            if (!empty($strategy['accommodations'])) {
                echo '<p class="text-xs text-gray-500">' . __('Accommodations') . ': ' . htmlspecialchars($strategy['accommodations']) . '</p>';
            }
            if (!empty($strategy['goalTitle'])) {
                echo '<p class="text-xs text-gray-500 mt-1 pt-1 border-t">' . __('Goal') . ': ' . htmlspecialchars($strategy['goalTitle']) . '</p>';
            }
            echo '</div>';
        }
        echo '</div>';
    } else {
        echo '<p class="text-gray-500 text-sm">' . __('No strategies defined yet.') . '</p>';
    }
    echo '</div>';

    // Part 6: Monitoring
    echo '<div class="bg-white rounded-lg shadow p-4">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">';
    echo '<span class="text-blue-600">6.</span> ' . __('Monitoring');
    echo ' <span class="text-sm text-gray-500 font-normal">(' . count($monitoring) . ' ' . __('items') . ')</span>';
    echo '</h3>';

    if (!empty($monitoring)) {
        echo '<div class="space-y-2">';
        foreach ($monitoring as $monitor) {
            echo '<div class="bg-teal-50 rounded p-3">';
            echo '<div class="flex gap-2 mb-2">';
            echo '<span class="bg-teal-100 text-teal-800 text-xs px-2 py-1 rounded">' . __($monitor['method']) . '</span>';
            echo '<span class="bg-gray-100 text-gray-800 text-xs px-2 py-1 rounded">' . __($monitor['frequency']) . '</span>';
            if (!empty($monitor['responsibleParty'])) {
                echo '<span class="bg-gray-100 text-gray-800 text-xs px-2 py-1 rounded">' . __($monitor['responsibleParty']) . '</span>';
            }
            echo '</div>';
            echo '<p class="text-sm">' . htmlspecialchars($monitor['description']) . '</p>';
            if (!empty($monitor['dataCollectionTools'])) {
                echo '<p class="text-xs text-gray-500 mt-1">' . __('Tools') . ': ' . htmlspecialchars($monitor['dataCollectionTools']) . '</p>';
            }
            if (!empty($monitor['successIndicators'])) {
                echo '<p class="text-xs text-gray-500">' . __('Success Indicators') . ': ' . htmlspecialchars($monitor['successIndicators']) . '</p>';
            }
            if (!empty($monitor['goalTitle'])) {
                echo '<p class="text-xs text-gray-500 mt-1 pt-1 border-t">' . __('Goal') . ': ' . htmlspecialchars($monitor['goalTitle']) . '</p>';
            }
            echo '</div>';
        }
        echo '</div>';
    } else {
        echo '<p class="text-gray-500 text-sm">' . __('No monitoring methods defined yet.') . '</p>';
    }
    echo '</div>';

    // Part 7: Parent Involvement
    echo '<div class="bg-white rounded-lg shadow p-4">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">';
    echo '<span class="text-blue-600">7.</span> ' . __('Parent Involvement');
    echo ' <span class="text-sm text-gray-500 font-normal">(' . count($parentInvolvement) . ' ' . __('items') . ')</span>';
    echo '</h3>';

    if (!empty($parentInvolvement)) {
        echo '<div class="space-y-2">';
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
            if (!empty($involvement['resourcesProvided'])) {
                echo '<p class="text-xs text-gray-500">' . __('Resources') . ': ' . htmlspecialchars($involvement['resourcesProvided']) . '</p>';
            }
            if (!empty($involvement['communicationMethod'])) {
                echo '<p class="text-xs text-gray-500">' . __('Communication') . ': ' . htmlspecialchars($involvement['communicationMethod']) . '</p>';
            }
            echo '</div>';
        }
        echo '</div>';
    } else {
        echo '<p class="text-gray-500 text-sm">' . __('No parent involvement activities defined yet.') . '</p>';
    }
    echo '</div>';

    // Part 8: External Consultations
    echo '<div class="bg-white rounded-lg shadow p-4">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">';
    echo '<span class="text-blue-600">8.</span> ' . __('External Consultations');
    echo ' <span class="text-sm text-gray-500 font-normal">(' . count($consultations) . ' ' . __('items') . ')</span>';
    echo '</h3>';

    if (!empty($consultations)) {
        echo '<div class="space-y-2">';
        foreach ($consultations as $consultation) {
            echo '<div class="bg-indigo-50 rounded p-3">';
            echo '<div class="flex justify-between items-start">';
            echo '<div>';
            echo '<span class="bg-indigo-100 text-indigo-800 text-xs px-2 py-1 rounded">' . __($consultation['specialistType']) . '</span>';
            if (!empty($consultation['specialistName'])) {
                echo ' <span class="font-medium ml-2">' . htmlspecialchars($consultation['specialistName']) . '</span>';
            }
            if (!empty($consultation['organization'])) {
                echo '<span class="text-gray-500"> - ' . htmlspecialchars($consultation['organization']) . '</span>';
            }
            echo '</div>';
            if (!empty($consultation['consultationDate'])) {
                echo '<span class="text-xs text-gray-500">' . Format::date($consultation['consultationDate']) . '</span>';
            }
            echo '</div>';
            echo '<p class="text-sm mt-2"><span class="font-medium">' . __('Purpose') . ':</span> ' . htmlspecialchars($consultation['purpose']) . '</p>';
            if (!empty($consultation['recommendations'])) {
                echo '<p class="text-sm mt-1"><span class="font-medium">' . __('Recommendations') . ':</span> ' . htmlspecialchars($consultation['recommendations']) . '</p>';
            }
            if (!empty($consultation['notes'])) {
                echo '<p class="text-xs text-gray-500 mt-1">' . __('Notes') . ': ' . htmlspecialchars($consultation['notes']) . '</p>';
            }
            if (!empty($consultation['nextConsultationDate'])) {
                echo '<p class="text-xs text-gray-500">' . __('Next Consultation') . ': ' . Format::date($consultation['nextConsultationDate']) . '</p>';
            }
            echo '</div>';
        }
        echo '</div>';
    } else {
        echo '<p class="text-gray-500 text-sm">' . __('No external consultations recorded yet.') . '</p>';
    }
    echo '</div>';

    echo '</div>'; // End 8-part structure

    // Progress History Section
    if (!empty($progressRecords)) {
        echo '<div class="bg-white rounded-lg shadow p-4 mt-6">';
        echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">' . __('Progress History') . '</h3>';

        echo '<div class="space-y-3">';
        foreach ($progressRecords as $progress) {
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
            echo '<p class="text-sm mt-2">' . htmlspecialchars($progress['progressNotes']) . '</p>';
            if (!empty($progress['measurementValue'])) {
                echo '<p class="text-xs text-gray-500 mt-1">' . __('Measurement') . ': ' . htmlspecialchars($progress['measurementValue']) . '</p>';
            }
            if (!empty($progress['barriers'])) {
                echo '<p class="text-xs text-gray-500">' . __('Barriers') . ': ' . htmlspecialchars($progress['barriers']) . '</p>';
            }
            if (!empty($progress['nextSteps'])) {
                echo '<p class="text-xs text-gray-500">' . __('Next Steps') . ': ' . htmlspecialchars($progress['nextSteps']) . '</p>';
            }
            if (!empty($progress['recordedByName'])) {
                echo '<p class="text-xs text-gray-400 mt-1">' . __('Recorded by') . ': ' . Format::name('', $progress['recordedByName'], $progress['recordedBySurname'], 'Staff', false) . '</p>';
            }
            echo '</div>';
        }
        echo '</div>';
        echo '</div>';
    }

    // Version History Section
    if (!empty($versions)) {
        echo '<div class="bg-white rounded-lg shadow p-4 mt-6">';
        echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">' . __('Version History') . '</h3>';

        echo '<table class="w-full text-sm">';
        echo '<thead class="bg-gray-50">';
        echo '<tr>';
        echo '<th class="px-3 py-2 text-left">' . __('Version') . '</th>';
        echo '<th class="px-3 py-2 text-left">' . __('Date') . '</th>';
        echo '<th class="px-3 py-2 text-left">' . __('Created By') . '</th>';
        echo '<th class="px-3 py-2 text-left">' . __('Change Summary') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        foreach ($versions as $version) {
            echo '<tr class="border-t">';
            echo '<td class="px-3 py-2 font-medium">v' . $version['versionNumber'] . '</td>';
            echo '<td class="px-3 py-2">' . Format::dateTime($version['timestampCreated']) . '</td>';
            echo '<td class="px-3 py-2">';
            if (!empty($version['createdByName'])) {
                echo Format::name('', $version['createdByName'], $version['createdBySurname'], 'Staff', false);
            }
            echo '</td>';
            echo '<td class="px-3 py-2">' . htmlspecialchars($version['changeSummary'] ?? '-') . '</td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    }

    // Back button
    echo '<div class="mt-6">';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/InterventionPlans/interventionPlans.php" class="text-blue-600 hover:underline">&larr; ' . __('Back to Intervention Plans') . '</a>';
    echo '</div>';
}
