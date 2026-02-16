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
use Gibbon\Module\InterventionPlans\Domain\InterventionPlanGateway;

// Module includes
require_once '../../gibbon.php';

// Get URL and set return URL
$URL = $session->get('absoluteURL') . '/index.php?q=/modules/' . $session->get('module');
$gibbonInterventionPlanID = $_POST['gibbonInterventionPlanID'] ?? '';

if (empty($gibbonInterventionPlanID)) {
    $URL .= '/interventionPlans.php&return=error1';
    header("Location: {$URL}");
    exit;
}

$URL .= '/interventionPlans_progress.php&gibbonInterventionPlanID=' . $gibbonInterventionPlanID;

// Proceed if valid
if (!isActionAccessible($guid, $connection2, '/modules/InterventionPlans/interventionPlans_progress.php')) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
}

// Get session values
$gibbonPersonID = $session->get('gibbonPersonID');

// Get gateway
$interventionPlanGateway = $container->get(InterventionPlanGateway::class);

// Verify plan exists
$plan = $interventionPlanGateway->getPlanDetails($gibbonInterventionPlanID);

if (empty($plan)) {
    $URL .= '&return=error2';
    header("Location: {$URL}");
    exit;
}

// Get form data
$gibbonInterventionGoalID = $_POST['gibbonInterventionGoalID'] ?? '';
$recordDate = !empty($_POST['recordDate']) ? Format::dateConvert($_POST['recordDate']) : null;
$progressLevel = $_POST['progressLevel'] ?? '';
$progressNotes = $_POST['progressNotes'] ?? '';
$measurementValue = $_POST['measurementValue'] ?? null;
$progressPercentage = isset($_POST['progressPercentage']) && $_POST['progressPercentage'] !== '' ? (float)$_POST['progressPercentage'] : null;
$newStatus = $_POST['newStatus'] ?? '';
$barriers = $_POST['barriers'] ?? null;
$nextSteps = $_POST['nextSteps'] ?? null;

// Validate required fields
if (empty($gibbonInterventionGoalID) || empty($recordDate) || empty($progressLevel) || empty($progressNotes)) {
    $URL .= '&return=error1';
    header("Location: {$URL}");
    exit;
}

// Validate progress level
$validProgressLevels = ['Significant Progress', 'Some Progress', 'No Change', 'Regression'];
if (!in_array($progressLevel, $validProgressLevels)) {
    $URL .= '&return=error1';
    header("Location: {$URL}");
    exit;
}

// Validate goal status if provided
if (!empty($newStatus)) {
    $validStatuses = ['Not Started', 'In Progress', 'Achieved', 'Modified', 'Discontinued'];
    if (!in_array($newStatus, $validStatuses)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }
}

// Validate progress percentage
if ($progressPercentage !== null) {
    if ($progressPercentage < 0 || $progressPercentage > 100) {
        $progressPercentage = max(0, min(100, $progressPercentage));
    }
}

// Prepare progress data
$progressData = [
    'gibbonInterventionPlanID' => $gibbonInterventionPlanID,
    'gibbonInterventionGoalID' => $gibbonInterventionGoalID,
    'recordedByID' => $gibbonPersonID,
    'recordDate' => $recordDate,
    'progressNotes' => $progressNotes,
    'progressLevel' => $progressLevel,
    'measurementValue' => $measurementValue,
    'barriers' => $barriers,
    'nextSteps' => $nextSteps,
];

// Insert progress record
try {
    $inserted = $interventionPlanGateway->insertProgress($progressData);

    if ($inserted === false) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    // Update goal progress if percentage provided
    if ($progressPercentage !== null || !empty($newStatus)) {
        // Get current goal data
        $goals = $interventionPlanGateway->selectGoalsByPlan($gibbonInterventionPlanID)->fetchAll();
        $currentGoal = null;
        foreach ($goals as $goal) {
            if ($goal['gibbonInterventionGoalID'] == $gibbonInterventionGoalID) {
                $currentGoal = $goal;
                break;
            }
        }

        if ($currentGoal) {
            // Determine new status
            $statusToUpdate = !empty($newStatus) ? $newStatus : $currentGoal['status'];

            // Auto-update status based on progress if not explicitly set
            if (empty($newStatus)) {
                if ($progressPercentage >= 100) {
                    $statusToUpdate = 'Achieved';
                } elseif ($progressPercentage > 0 && $currentGoal['status'] === 'Not Started') {
                    $statusToUpdate = 'In Progress';
                }
            }

            // Determine progress percentage
            $percentageToUpdate = $progressPercentage !== null ? $progressPercentage : (float)($currentGoal['progressPercentage'] ?? 0);

            // Update goal
            $interventionPlanGateway->updateGoalProgress(
                $gibbonInterventionGoalID,
                $percentageToUpdate,
                $statusToUpdate
            );
        }
    }

    // Update plan's last modified timestamp
    $interventionPlanGateway->update($gibbonInterventionPlanID, [
        'lastModifiedByID' => $gibbonPersonID,
    ]);

    $URL .= '&return=success0';
    header("Location: {$URL}");
    exit;

} catch (Exception $e) {
    $URL .= '&return=error2';
    header("Location: {$URL}");
    exit;
}
