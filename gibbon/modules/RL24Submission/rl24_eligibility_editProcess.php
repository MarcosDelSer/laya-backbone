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

use Gibbon\Module\RL24Submission\Domain\RL24EligibilityGateway;
use Gibbon\Services\Format;

// Include core (this file is called directly, not through module framework)
include '../../gibbon.php';

$gibbonRL24EligibilityID = $_POST['gibbonRL24EligibilityID'] ?? '';

$URL = $session->get('absoluteURL') . '/index.php?q=/modules/RL24Submission/rl24_eligibility_edit.php&gibbonRL24EligibilityID=' . $gibbonRL24EligibilityID;

// Access check
if (isActionAccessible($guid, $connection2, '/modules/RL24Submission/rl24_eligibility.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
}

// Validate eligibility ID
if (empty($gibbonRL24EligibilityID)) {
    $URL .= '&return=error1';
    header("Location: {$URL}");
    exit;
}

// Get the gateway
$eligibilityGateway = $container->get(RL24EligibilityGateway::class);

// Get existing eligibility record
$eligibility = $eligibilityGateway->getByID($gibbonRL24EligibilityID);

if (empty($eligibility)) {
    $URL .= '&return=error2';
    header("Location: {$URL}");
    exit;
}

// Get required POST data
$gibbonPersonIDChild = $_POST['gibbonPersonIDChild'] ?? '';
$formYear = $_POST['formYear'] ?? '';
$childFirstName = $_POST['childFirstName'] ?? '';
$childLastName = $_POST['childLastName'] ?? '';
$parentFirstName = $_POST['parentFirstName'] ?? '';
$parentLastName = $_POST['parentLastName'] ?? '';
$approvalStatus = $_POST['approvalStatus'] ?? 'Pending';

// Validate required fields
if (empty($gibbonPersonIDChild) || empty($formYear) ||
    empty($childFirstName) || empty($childLastName) || empty($parentFirstName) || empty($parentLastName)) {
    $URL .= '&return=error1';
    header("Location: {$URL}");
    exit;
}

// Check if changing form year would create a duplicate (different child+year combo)
if ($formYear != $eligibility['formYear'] || $gibbonPersonIDChild != $eligibility['gibbonPersonIDChild']) {
    if ($eligibilityGateway->eligibilityExistsForChildAndYear($gibbonPersonIDChild, $formYear, $gibbonRL24EligibilityID)) {
        $URL .= '&return=error3';
        header("Location: {$URL}");
        exit;
    }
}

// Get optional parent person ID
$gibbonPersonIDParent = !empty($_POST['gibbonPersonIDParent']) ? $_POST['gibbonPersonIDParent'] : null;

// Build form data array with all fields from the form
$formData = [
    'formYear' => $formYear,
    'gibbonPersonIDParent' => $gibbonPersonIDParent,
    'childFirstName' => trim($childFirstName),
    'childLastName' => trim($childLastName),
    'childDateOfBirth' => !empty($_POST['childDateOfBirth']) ? Format::dateConvert($_POST['childDateOfBirth']) : null,
    'childRelationship' => $_POST['childRelationship'] ?? null,
    'parentFirstName' => trim($parentFirstName),
    'parentLastName' => trim($parentLastName),
    'parentSIN' => !empty($_POST['parentSIN']) ? trim($_POST['parentSIN']) : null,
    'parentPhone' => $_POST['parentPhone'] ?? null,
    'parentEmail' => $_POST['parentEmail'] ?? null,
    'parentAddressLine1' => $_POST['parentAddressLine1'] ?? null,
    'parentAddressLine2' => $_POST['parentAddressLine2'] ?? null,
    'parentCity' => $_POST['parentCity'] ?? null,
    'parentProvince' => $_POST['parentProvince'] ?? 'QC',
    'parentPostalCode' => !empty($_POST['parentPostalCode']) ? strtoupper(trim($_POST['parentPostalCode'])) : null,
    'citizenshipStatus' => $_POST['citizenshipStatus'] ?? null,
    'citizenshipOther' => $_POST['citizenshipOther'] ?? null,
    'residencyStatus' => $_POST['residencyStatus'] ?? 'Quebec',
    'servicePeriodStart' => !empty($_POST['servicePeriodStart']) ? Format::dateConvert($_POST['servicePeriodStart']) : null,
    'servicePeriodEnd' => !empty($_POST['servicePeriodEnd']) ? Format::dateConvert($_POST['servicePeriodEnd']) : null,
    'divisionNumber' => $_POST['divisionNumber'] ?? null,
    'approvalNotes' => $_POST['approvalNotes'] ?? null,
    'documentsComplete' => $_POST['documentsComplete'] ?? 'N',
    'signatureConfirmed' => $_POST['signatureConfirmed'] ?? 'N',
    'notes' => $_POST['notes'] ?? null,
];

// Handle approval status change
$oldApprovalStatus = $eligibility['approvalStatus'];
$gibbonPersonID = $session->get('gibbonPersonID');

if ($approvalStatus !== $oldApprovalStatus) {
    $formData['approvalStatus'] = $approvalStatus;

    // Record approval/rejection details
    if ($approvalStatus === 'Approved' || $approvalStatus === 'Rejected') {
        $formData['approvalDate'] = date('Y-m-d');
        $formData['approvedByID'] = $gibbonPersonID;
    }
} else {
    // Keep the existing approval status
    $formData['approvalStatus'] = $approvalStatus;
}

// Handle signature date if newly confirmed
if ($_POST['signatureConfirmed'] === 'Y' && $eligibility['signatureConfirmed'] !== 'Y') {
    $formData['signatureDate'] = date('Y-m-d');
}

// Update the eligibility form
$updated = $eligibilityGateway->update($gibbonRL24EligibilityID, $formData);

if ($updated === false) {
    $URL .= '&return=error2';
    header("Location: {$URL}");
    exit;
}

// Success - redirect back to edit page
$URL .= '&return=success0';
header("Location: {$URL}");
exit;
