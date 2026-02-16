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

// Get plan ID
$gibbonInterventionPlanID = $_POST['gibbonInterventionPlanID'] ?? $_GET['gibbonInterventionPlanID'] ?? '';
$action = $_GET['action'] ?? '';

$URL = $session->get('absoluteURL') . '/index.php?q=/modules/' . $session->get('module') . '/interventionPlans_edit.php&gibbonInterventionPlanID=' . $gibbonInterventionPlanID;

// Proceed if valid session
if (!isActionAccessible($guid, $connection2, '/modules/InterventionPlans/interventionPlans_edit.php')) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
}

// Check required parameters
if (empty($gibbonInterventionPlanID)) {
    $URL .= '&return=error1';
    header("Location: {$URL}");
    exit;
}

// Get session values
$gibbonSchoolYearID = $session->get('gibbonSchoolYearID');
$gibbonPersonIDModifier = $session->get('gibbonPersonID');

// Get gateway
$interventionPlanGateway = $container->get(InterventionPlanGateway::class);

// Verify plan exists
$plan = $interventionPlanGateway->getByID($gibbonInterventionPlanID);
if (empty($plan)) {
    $URL .= '&return=error2';
    header("Location: {$URL}");
    exit;
}

try {
    // Route to appropriate action handler
    switch ($action) {
        case 'addStrength':
            handleAddStrength($interventionPlanGateway, $gibbonInterventionPlanID);
            break;

        case 'addNeed':
            handleAddNeed($interventionPlanGateway, $gibbonInterventionPlanID);
            break;

        case 'addGoal':
            handleAddGoal($interventionPlanGateway, $gibbonInterventionPlanID);
            break;

        case 'addStrategy':
            handleAddStrategy($interventionPlanGateway, $gibbonInterventionPlanID);
            break;

        case 'addMonitoring':
            handleAddMonitoring($interventionPlanGateway, $gibbonInterventionPlanID);
            break;

        case 'addParentInvolvement':
            handleAddParentInvolvement($interventionPlanGateway, $gibbonInterventionPlanID);
            break;

        case 'addConsultation':
            handleAddConsultation($interventionPlanGateway, $gibbonInterventionPlanID);
            break;

        default:
            // Update main plan information
            handleUpdatePlan($interventionPlanGateway, $gibbonInterventionPlanID, $gibbonPersonIDModifier);
            break;
    }

    $URL .= '&return=success0';
    header("Location: {$URL}");
    exit;

} catch (Exception $e) {
    $URL .= '&return=error2';
    header("Location: {$URL}");
    exit;
}

/**
 * Handle updating main plan information
 */
function handleUpdatePlan($gateway, $planID, $modifierID)
{
    $gibbonPersonID = $_POST['gibbonPersonID'] ?? '';
    $title = $_POST['title'] ?? '';
    $status = $_POST['status'] ?? 'Draft';
    $reviewSchedule = $_POST['reviewSchedule'] ?? 'Quarterly';
    $effectiveDate = !empty($_POST['effectiveDate']) ? Format::dateConvert($_POST['effectiveDate']) : null;
    $endDate = !empty($_POST['endDate']) ? Format::dateConvert($_POST['endDate']) : null;
    $nextReviewDate = !empty($_POST['nextReviewDate']) ? Format::dateConvert($_POST['nextReviewDate']) : null;

    if (empty($gibbonPersonID) || empty($title) || empty($effectiveDate)) {
        throw new Exception('Missing required fields');
    }

    // Validate status
    $validStatuses = ['Draft', 'Active', 'Under Review', 'Completed', 'Archived'];
    if (!in_array($status, $validStatuses)) {
        $status = 'Draft';
    }

    // Validate review schedule
    $validSchedules = ['Monthly', 'Quarterly', 'Biannually', 'Annually'];
    if (!in_array($reviewSchedule, $validSchedules)) {
        $reviewSchedule = 'Quarterly';
    }

    $data = [
        'gibbonPersonID' => $gibbonPersonID,
        'title' => $title,
        'status' => $status,
        'reviewSchedule' => $reviewSchedule,
        'effectiveDate' => $effectiveDate,
        'endDate' => $endDate,
        'nextReviewDate' => $nextReviewDate,
        'lastModifiedByID' => $modifierID,
    ];

    $result = $gateway->update($planID, $data);

    if ($result === false) {
        throw new Exception('Failed to update plan');
    }
}

/**
 * Handle adding a strength (Part 2)
 */
function handleAddStrength($gateway, $planID)
{
    $category = $_POST['strengthCategory'] ?? '';
    $description = $_POST['strengthDescription'] ?? '';
    $examples = $_POST['strengthExamples'] ?? null;

    if (empty($category) || empty($description)) {
        throw new Exception('Missing required fields');
    }

    // Validate category
    $validCategories = ['Cognitive', 'Social', 'Physical', 'Emotional', 'Communication', 'Creative', 'Other'];
    if (!in_array($category, $validCategories)) {
        throw new Exception('Invalid category');
    }

    // Get next sort order
    $strengths = $gateway->selectStrengthsByPlan($planID)->fetchAll();
    $sortOrder = count($strengths);

    $data = [
        'gibbonInterventionPlanID' => $planID,
        'category' => $category,
        'description' => $description,
        'examples' => $examples,
        'sortOrder' => $sortOrder,
    ];

    $result = $gateway->insertStrength($data);

    if ($result === false) {
        throw new Exception('Failed to add strength');
    }
}

/**
 * Handle adding a need (Part 3)
 */
function handleAddNeed($gateway, $planID)
{
    $category = $_POST['needCategory'] ?? '';
    $priority = $_POST['needPriority'] ?? 'Medium';
    $description = $_POST['needDescription'] ?? '';
    $baseline = $_POST['needBaseline'] ?? null;

    if (empty($category) || empty($description)) {
        throw new Exception('Missing required fields');
    }

    // Validate category
    $validCategories = ['Communication', 'Behavior', 'Academic', 'Sensory', 'Motor', 'Social', 'Self-Care', 'Other'];
    if (!in_array($category, $validCategories)) {
        throw new Exception('Invalid category');
    }

    // Validate priority
    $validPriorities = ['Low', 'Medium', 'High', 'Critical'];
    if (!in_array($priority, $validPriorities)) {
        $priority = 'Medium';
    }

    // Get next sort order
    $needs = $gateway->selectNeedsByPlan($planID)->fetchAll();
    $sortOrder = count($needs);

    $data = [
        'gibbonInterventionPlanID' => $planID,
        'category' => $category,
        'description' => $description,
        'priority' => $priority,
        'baseline' => $baseline,
        'sortOrder' => $sortOrder,
    ];

    $result = $gateway->insertNeed($data);

    if ($result === false) {
        throw new Exception('Failed to add need');
    }
}

/**
 * Handle adding a SMART goal (Part 4)
 */
function handleAddGoal($gateway, $planID)
{
    $needID = !empty($_POST['goalNeedID']) ? $_POST['goalNeedID'] : null;
    $title = $_POST['goalTitle'] ?? '';
    $description = $_POST['goalDescription'] ?? '';
    $measurementCriteria = $_POST['goalMeasurement'] ?? '';
    $targetDate = !empty($_POST['goalTargetDate']) ? Format::dateConvert($_POST['goalTargetDate']) : null;

    if (empty($title) || empty($description) || empty($measurementCriteria)) {
        throw new Exception('Missing required fields');
    }

    // Get next sort order
    $goals = $gateway->selectGoalsByPlan($planID)->fetchAll();
    $sortOrder = count($goals);

    $data = [
        'gibbonInterventionPlanID' => $planID,
        'gibbonInterventionNeedID' => $needID,
        'title' => $title,
        'description' => $description,
        'measurementCriteria' => $measurementCriteria,
        'measurementBaseline' => null,
        'measurementTarget' => null,
        'achievabilityNotes' => null,
        'relevanceNotes' => null,
        'targetDate' => $targetDate,
        'status' => 'Not Started',
        'progressPercentage' => 0,
        'sortOrder' => $sortOrder,
    ];

    $result = $gateway->insertGoal($data);

    if ($result === false) {
        throw new Exception('Failed to add goal');
    }
}

/**
 * Handle adding a strategy (Part 5)
 */
function handleAddStrategy($gateway, $planID)
{
    $goalID = !empty($_POST['strategyGoalID']) ? $_POST['strategyGoalID'] : null;
    $title = $_POST['strategyTitle'] ?? '';
    $description = $_POST['strategyDescription'] ?? '';
    $responsibleParty = $_POST['strategyResponsible'] ?? 'Educator';
    $frequency = $_POST['strategyFrequency'] ?? null;

    if (empty($title) || empty($description)) {
        throw new Exception('Missing required fields');
    }

    // Validate responsible party
    $validParties = ['Educator', 'Parent', 'Therapist', 'Team', 'Other'];
    if (!in_array($responsibleParty, $validParties)) {
        $responsibleParty = 'Educator';
    }

    // Get next sort order
    $strategies = $gateway->selectStrategiesByPlan($planID)->fetchAll();
    $sortOrder = count($strategies);

    $data = [
        'gibbonInterventionPlanID' => $planID,
        'gibbonInterventionGoalID' => $goalID,
        'title' => $title,
        'description' => $description,
        'responsibleParty' => $responsibleParty,
        'frequency' => $frequency,
        'materialsNeeded' => null,
        'accommodations' => null,
        'sortOrder' => $sortOrder,
    ];

    $result = $gateway->insertStrategy($data);

    if ($result === false) {
        throw new Exception('Failed to add strategy');
    }
}

/**
 * Handle adding a monitoring method (Part 6)
 */
function handleAddMonitoring($gateway, $planID)
{
    $goalID = !empty($_POST['monitoringGoalID']) ? $_POST['monitoringGoalID'] : null;
    $method = $_POST['monitoringMethod'] ?? '';
    $description = $_POST['monitoringDescription'] ?? '';
    $frequency = $_POST['monitoringFrequency'] ?? 'Weekly';
    $responsibleParty = $_POST['monitoringResponsible'] ?? 'Educator';

    if (empty($method) || empty($description)) {
        throw new Exception('Missing required fields');
    }

    // Validate method
    $validMethods = ['Observation', 'Assessment', 'Data Collection', 'Checklist', 'Portfolio', 'Other'];
    if (!in_array($method, $validMethods)) {
        throw new Exception('Invalid method');
    }

    // Validate frequency
    $validFrequencies = ['Daily', 'Weekly', 'Biweekly', 'Monthly', 'Quarterly'];
    if (!in_array($frequency, $validFrequencies)) {
        $frequency = 'Weekly';
    }

    // Validate responsible party
    $validParties = ['Educator', 'Parent', 'Therapist', 'Team', 'Other'];
    if (!in_array($responsibleParty, $validParties)) {
        $responsibleParty = 'Educator';
    }

    // Get next sort order
    $monitoring = $gateway->selectMonitoringByPlan($planID)->fetchAll();
    $sortOrder = count($monitoring);

    $data = [
        'gibbonInterventionPlanID' => $planID,
        'gibbonInterventionGoalID' => $goalID,
        'method' => $method,
        'description' => $description,
        'frequency' => $frequency,
        'responsibleParty' => $responsibleParty,
        'dataCollectionTools' => null,
        'successIndicators' => null,
        'sortOrder' => $sortOrder,
    ];

    $result = $gateway->insertMonitoring($data);

    if ($result === false) {
        throw new Exception('Failed to add monitoring method');
    }
}

/**
 * Handle adding parent involvement (Part 7)
 */
function handleAddParentInvolvement($gateway, $planID)
{
    $activityType = $_POST['parentActivityType'] ?? '';
    $title = $_POST['parentTitle'] ?? '';
    $description = $_POST['parentDescription'] ?? '';
    $frequency = $_POST['parentFrequency'] ?? null;

    if (empty($activityType) || empty($title) || empty($description)) {
        throw new Exception('Missing required fields');
    }

    // Validate activity type
    $validTypes = ['Home Activity', 'Communication', 'Training', 'Meeting', 'Resources', 'Other'];
    if (!in_array($activityType, $validTypes)) {
        throw new Exception('Invalid activity type');
    }

    // Get next sort order
    $parentInvolvement = $gateway->selectParentInvolvementByPlan($planID)->fetchAll();
    $sortOrder = count($parentInvolvement);

    $data = [
        'gibbonInterventionPlanID' => $planID,
        'activityType' => $activityType,
        'title' => $title,
        'description' => $description,
        'frequency' => $frequency,
        'resourcesProvided' => null,
        'communicationMethod' => null,
        'sortOrder' => $sortOrder,
    ];

    $result = $gateway->insertParentInvolvement($data);

    if ($result === false) {
        throw new Exception('Failed to add parent involvement');
    }
}

/**
 * Handle adding a consultation (Part 8)
 */
function handleAddConsultation($gateway, $planID)
{
    $specialistType = $_POST['consultationSpecialistType'] ?? '';
    $specialistName = $_POST['consultationSpecialistName'] ?? null;
    $organization = $_POST['consultationOrganization'] ?? null;
    $purpose = $_POST['consultationPurpose'] ?? '';
    $consultationDate = !empty($_POST['consultationDate']) ? Format::dateConvert($_POST['consultationDate']) : null;
    $recommendations = $_POST['consultationRecommendations'] ?? null;

    if (empty($specialistType) || empty($purpose)) {
        throw new Exception('Missing required fields');
    }

    // Validate specialist type
    $validTypes = ['Speech Therapist', 'Occupational Therapist', 'Psychologist', 'Pediatrician', 'Behavioral Specialist', 'Special Education Consultant', 'Other'];
    if (!in_array($specialistType, $validTypes)) {
        throw new Exception('Invalid specialist type');
    }

    // Get next sort order
    $consultations = $gateway->selectConsultationsByPlan($planID)->fetchAll();
    $sortOrder = count($consultations);

    $data = [
        'gibbonInterventionPlanID' => $planID,
        'specialistType' => $specialistType,
        'specialistName' => $specialistName,
        'organization' => $organization,
        'purpose' => $purpose,
        'recommendations' => $recommendations,
        'consultationDate' => $consultationDate,
        'nextConsultationDate' => null,
        'notes' => null,
        'sortOrder' => $sortOrder,
    ];

    $result = $gateway->insertConsultation($data);

    if ($result === false) {
        throw new Exception('Failed to add consultation');
    }
}
