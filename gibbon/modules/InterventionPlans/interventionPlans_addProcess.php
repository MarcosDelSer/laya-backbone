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

$URL = $session->get('absoluteURL') . '/index.php?q=/modules/' . $session->get('module') . '/interventionPlans_add.php';

// Proceed if valid session
if (!isActionAccessible($guid, $connection2, '/modules/InterventionPlans/interventionPlans_add.php')) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
}

// Get session values
$gibbonSchoolYearID = $session->get('gibbonSchoolYearID');
$gibbonPersonIDCreator = $session->get('gibbonPersonID');

// Validate required input
$gibbonPersonID = $_POST['gibbonPersonID'] ?? '';
$title = $_POST['title'] ?? '';
$status = $_POST['status'] ?? 'Draft';
$reviewSchedule = $_POST['reviewSchedule'] ?? 'Quarterly';
$effectiveDate = !empty($_POST['effectiveDate']) ? Format::dateConvert($_POST['effectiveDate']) : null;
$endDate = !empty($_POST['endDate']) ? Format::dateConvert($_POST['endDate']) : null;
$nextReviewDate = !empty($_POST['nextReviewDate']) ? Format::dateConvert($_POST['nextReviewDate']) : null;

// Check required fields
if (empty($gibbonPersonID) || empty($title) || empty($effectiveDate)) {
    $URL .= '&return=error1';
    header("Location: {$URL}");
    exit;
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

// Calculate next review date if not provided
if (empty($nextReviewDate) && !empty($effectiveDate)) {
    $effectiveDateObj = new DateTime($effectiveDate);
    switch ($reviewSchedule) {
        case 'Monthly':
            $effectiveDateObj->modify('+1 month');
            break;
        case 'Quarterly':
            $effectiveDateObj->modify('+3 months');
            break;
        case 'Biannually':
            $effectiveDateObj->modify('+6 months');
            break;
        case 'Annually':
            $effectiveDateObj->modify('+1 year');
            break;
    }
    $nextReviewDate = $effectiveDateObj->format('Y-m-d');
}

// Get gateway
$interventionPlanGateway = $container->get(InterventionPlanGateway::class);

// Prepare data
$data = [
    'gibbonPersonID' => $gibbonPersonID,
    'gibbonSchoolYearID' => $gibbonSchoolYearID,
    'title' => $title,
    'status' => $status,
    'version' => 1,
    'reviewSchedule' => $reviewSchedule,
    'effectiveDate' => $effectiveDate,
    'endDate' => $endDate,
    'nextReviewDate' => $nextReviewDate,
    'parentSigned' => 'N',
    'createdByID' => $gibbonPersonIDCreator,
    'lastModifiedByID' => $gibbonPersonIDCreator,
];

// Insert the intervention plan
try {
    $gibbonInterventionPlanID = $interventionPlanGateway->insert($data);

    if ($gibbonInterventionPlanID !== false) {
        // Create initial version record
        $interventionPlanGateway->insertVersion([
            'gibbonInterventionPlanID' => $gibbonInterventionPlanID,
            'versionNumber' => 1,
            'createdByID' => $gibbonPersonIDCreator,
            'changeSummary' => __('Initial plan created'),
            'snapshotData' => json_encode($data),
        ]);

        // Redirect to edit page to add sections
        $URLSuccess = $session->get('absoluteURL') . '/index.php?q=/modules/' . $session->get('module') . '/interventionPlans_edit.php&gibbonInterventionPlanID=' . $gibbonInterventionPlanID . '&return=success0';
        header("Location: {$URLSuccess}");
        exit;
    } else {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }
} catch (Exception $e) {
    $URL .= '&return=error2';
    header("Location: {$URL}");
    exit;
}
