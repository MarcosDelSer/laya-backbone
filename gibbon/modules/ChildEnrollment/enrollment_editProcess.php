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
use Gibbon\Module\ChildEnrollment\Domain\EnrollmentFormGateway;
use Gibbon\Module\ChildEnrollment\Domain\EnrollmentParentGateway;
use Gibbon\Module\ChildEnrollment\Domain\EnrollmentPickupGateway;
use Gibbon\Module\ChildEnrollment\Domain\EnrollmentEmergencyContactGateway;
use Gibbon\Module\ChildEnrollment\Domain\EnrollmentHealthGateway;
use Gibbon\Module\ChildEnrollment\Domain\EnrollmentNutritionGateway;
use Gibbon\Module\ChildEnrollment\Domain\EnrollmentAttendanceGateway;

// Module Bootstrap
require_once '../../gibbon.php';

// Get form ID from query string
$gibbonChildEnrollmentFormID = $_GET['gibbonChildEnrollmentFormID'] ?? $_POST['gibbonChildEnrollmentFormID'] ?? null;

// Setup redirect URL
$URL = $session->get('absoluteURL') . '/index.php?q=/modules/ChildEnrollment/enrollment_edit.php&gibbonChildEnrollmentFormID=' . $gibbonChildEnrollmentFormID;

// Access check
if (!isActionAccessible($guid, $connection2, '/modules/ChildEnrollment/enrollment_edit.php')) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
}

// Validate form ID
if (empty($gibbonChildEnrollmentFormID)) {
    $URL = $session->get('absoluteURL') . '/index.php?q=/modules/ChildEnrollment/enrollment_list.php&return=error1';
    header("Location: {$URL}");
    exit;
}

// Get gateways
$enrollmentFormGateway = $container->get(EnrollmentFormGateway::class);
$enrollmentParentGateway = $container->get(EnrollmentParentGateway::class);
$enrollmentPickupGateway = $container->get(EnrollmentPickupGateway::class);
$enrollmentEmergencyContactGateway = $container->get(EnrollmentEmergencyContactGateway::class);
$enrollmentHealthGateway = $container->get(EnrollmentHealthGateway::class);
$enrollmentNutritionGateway = $container->get(EnrollmentNutritionGateway::class);
$enrollmentAttendanceGateway = $container->get(EnrollmentAttendanceGateway::class);

// Get existing form
$existingForm = $enrollmentFormGateway->getByID($gibbonChildEnrollmentFormID);

if (empty($existingForm)) {
    $URL .= '&return=error3';
    header("Location: {$URL}");
    exit;
}

// Check if form can be edited (only Draft or Rejected forms can be edited)
if (!in_array($existingForm['status'], ['Draft', 'Rejected'])) {
    $URL .= '&return=error4';
    header("Location: {$URL}");
    exit;
}

// Get required values from session
$gibbonSchoolYearID = $session->get('gibbonSchoolYearID');
$gibbonPersonID = $session->get('gibbonPersonID');

// Collect and validate child information
$gibbonPersonIDChild = $_POST['gibbonPersonID'] ?? null;
$gibbonFamilyID = $_POST['gibbonFamilyID'] ?? null;
$childFirstName = trim($_POST['childFirstName'] ?? '');
$childLastName = trim($_POST['childLastName'] ?? '');
$childDateOfBirth = !empty($_POST['childDateOfBirth']) ? Format::dateConvert($_POST['childDateOfBirth']) : null;
$admissionDate = !empty($_POST['admissionDate']) ? Format::dateConvert($_POST['admissionDate']) : null;
$childAddress = trim($_POST['childAddress'] ?? '');
$childCity = trim($_POST['childCity'] ?? '');
$childPostalCode = trim($_POST['childPostalCode'] ?? '');
$languagesSpoken = trim($_POST['languagesSpoken'] ?? '');
$notes = trim($_POST['notes'] ?? '');

// Validate required fields
if (empty($gibbonFamilyID) || empty($childFirstName) || empty($childLastName) || empty($childDateOfBirth)) {
    $URL .= '&return=error1';
    header("Location: {$URL}");
    exit;
}

// Collect parent 1 information (required)
$parent1Name = trim($_POST['parent1Name'] ?? '');
$parent1Relationship = trim($_POST['parent1Relationship'] ?? '');
$parent1CellPhone = trim($_POST['parent1CellPhone'] ?? '');
$parent1HomePhone = trim($_POST['parent1HomePhone'] ?? '');
$parent1WorkPhone = trim($_POST['parent1WorkPhone'] ?? '');

// Validate parent 1 required fields
if (empty($parent1Name) || empty($parent1Relationship)) {
    $URL .= '&return=error1';
    header("Location: {$URL}");
    exit;
}

// At least one phone number required for parent 1
if (empty($parent1CellPhone) && empty($parent1HomePhone) && empty($parent1WorkPhone)) {
    $URL .= '&return=error1';
    header("Location: {$URL}");
    exit;
}

// Collect emergency contacts (required)
$emergency1Name = trim($_POST['emergency1Name'] ?? '');
$emergency1Relationship = trim($_POST['emergency1Relationship'] ?? '');
$emergency1Phone = trim($_POST['emergency1Phone'] ?? '');
$emergency2Name = trim($_POST['emergency2Name'] ?? '');
$emergency2Relationship = trim($_POST['emergency2Relationship'] ?? '');
$emergency2Phone = trim($_POST['emergency2Phone'] ?? '');

// Validate emergency contacts
if (empty($emergency1Name) || empty($emergency1Phone) || empty($emergency2Name) || empty($emergency2Phone)) {
    $URL .= '&return=error1';
    header("Location: {$URL}");
    exit;
}

// Collect pickup 1 (required)
$pickup1Name = trim($_POST['pickup1Name'] ?? '');
$pickup1Relationship = trim($_POST['pickup1Relationship'] ?? '');
$pickup1Phone = trim($_POST['pickup1Phone'] ?? '');

// Validate pickup 1
if (empty($pickup1Name) || empty($pickup1Phone)) {
    $URL .= '&return=error1';
    header("Location: {$URL}");
    exit;
}

// Get existing IDs for updating
$pickup1ID = $_POST['pickup1ID'] ?? null;
$pickup2ID = $_POST['pickup2ID'] ?? null;
$emergency1ID = $_POST['emergency1ID'] ?? null;
$emergency2ID = $_POST['emergency2ID'] ?? null;

// Begin transaction
$pdo->beginTransaction();

try {
    // ============================================
    // Update main enrollment form
    // ============================================
    $formData = [
        'gibbonPersonID' => $gibbonPersonIDChild ?: null,
        'gibbonFamilyID' => $gibbonFamilyID,
        'admissionDate' => $admissionDate,
        'childFirstName' => $childFirstName,
        'childLastName' => $childLastName,
        'childDateOfBirth' => $childDateOfBirth,
        'childAddress' => $childAddress,
        'childCity' => $childCity,
        'childPostalCode' => $childPostalCode,
        'languagesSpoken' => $languagesSpoken,
        'notes' => $notes,
    ];

    $updateSuccess = $enrollmentFormGateway->updateForm($gibbonChildEnrollmentFormID, $formData);

    if ($updateSuccess === false) {
        throw new Exception('Failed to update enrollment form');
    }

    // ============================================
    // Update Parent 1
    // ============================================
    $parent1Data = [
        'name' => $parent1Name,
        'relationship' => $parent1Relationship,
        'address' => trim($_POST['parent1Address'] ?? ''),
        'city' => trim($_POST['parent1City'] ?? ''),
        'postalCode' => trim($_POST['parent1PostalCode'] ?? ''),
        'homePhone' => $parent1HomePhone,
        'cellPhone' => $parent1CellPhone,
        'workPhone' => $parent1WorkPhone,
        'email' => trim($_POST['parent1Email'] ?? ''),
        'employer' => trim($_POST['parent1Employer'] ?? ''),
        'workAddress' => trim($_POST['parent1WorkAddress'] ?? ''),
        'workHours' => trim($_POST['parent1WorkHours'] ?? ''),
        'isPrimaryContact' => 'Y',
    ];

    $parent1Result = $enrollmentParentGateway->upsertParent($gibbonChildEnrollmentFormID, '1', $parent1Data);

    if ($parent1Result === false) {
        throw new Exception('Failed to update parent 1 record');
    }

    // ============================================
    // Update Parent 2 (if provided)
    // ============================================
    $parent2Name = trim($_POST['parent2Name'] ?? '');
    if (!empty($parent2Name)) {
        $parent2Data = [
            'name' => $parent2Name,
            'relationship' => trim($_POST['parent2Relationship'] ?? ''),
            'address' => trim($_POST['parent2Address'] ?? ''),
            'city' => trim($_POST['parent2City'] ?? ''),
            'postalCode' => trim($_POST['parent2PostalCode'] ?? ''),
            'homePhone' => trim($_POST['parent2HomePhone'] ?? ''),
            'cellPhone' => trim($_POST['parent2CellPhone'] ?? ''),
            'workPhone' => trim($_POST['parent2WorkPhone'] ?? ''),
            'email' => trim($_POST['parent2Email'] ?? ''),
            'employer' => trim($_POST['parent2Employer'] ?? ''),
            'workAddress' => trim($_POST['parent2WorkAddress'] ?? ''),
            'workHours' => trim($_POST['parent2WorkHours'] ?? ''),
            'isPrimaryContact' => 'N',
        ];

        $parent2Result = $enrollmentParentGateway->upsertParent($gibbonChildEnrollmentFormID, '2', $parent2Data);

        if ($parent2Result === false) {
            throw new Exception('Failed to update parent 2 record');
        }
    } else {
        // If parent 2 name is empty, delete the record if it exists
        $enrollmentParentGateway->deleteParentByFormAndNumber($gibbonChildEnrollmentFormID, '2');
    }

    // ============================================
    // Update Authorized Pickup 1
    // ============================================
    $pickup1Data = [
        'name' => $pickup1Name,
        'relationship' => $pickup1Relationship,
        'phone' => $pickup1Phone,
        'priority' => 1,
        'notes' => trim($_POST['pickup1Notes'] ?? ''),
    ];

    if (!empty($pickup1ID)) {
        $pickup1Result = $enrollmentPickupGateway->updatePickup($pickup1ID, $pickup1Data);
    } else {
        $pickup1Result = $enrollmentPickupGateway->addPickup($gibbonChildEnrollmentFormID, $pickup1Data);
    }

    if ($pickup1Result === false) {
        throw new Exception('Failed to update authorized pickup 1');
    }

    // ============================================
    // Update Authorized Pickup 2 (if provided)
    // ============================================
    $pickup2Name = trim($_POST['pickup2Name'] ?? '');
    if (!empty($pickup2Name)) {
        $pickup2Data = [
            'name' => $pickup2Name,
            'relationship' => trim($_POST['pickup2Relationship'] ?? ''),
            'phone' => trim($_POST['pickup2Phone'] ?? ''),
            'priority' => 2,
            'notes' => trim($_POST['pickup2Notes'] ?? ''),
        ];

        if (!empty($pickup2ID)) {
            $pickup2Result = $enrollmentPickupGateway->updatePickup($pickup2ID, $pickup2Data);
        } else {
            $pickup2Result = $enrollmentPickupGateway->addPickup($gibbonChildEnrollmentFormID, $pickup2Data);
        }

        if ($pickup2Result === false) {
            throw new Exception('Failed to update authorized pickup 2');
        }
    } else {
        // If pickup 2 name is empty, delete the record if it exists
        if (!empty($pickup2ID)) {
            $enrollmentPickupGateway->deletePickup($pickup2ID);
        }
    }

    // ============================================
    // Update Emergency Contact 1
    // ============================================
    $emergency1Data = [
        'name' => $emergency1Name,
        'relationship' => $emergency1Relationship,
        'phone' => $emergency1Phone,
        'alternatePhone' => trim($_POST['emergency1AlternatePhone'] ?? ''),
        'priority' => 1,
        'notes' => trim($_POST['emergency1Notes'] ?? ''),
    ];

    if (!empty($emergency1ID)) {
        $emergency1Result = $enrollmentEmergencyContactGateway->updateContact($emergency1ID, $emergency1Data);
    } else {
        $emergency1Result = $enrollmentEmergencyContactGateway->addContact($gibbonChildEnrollmentFormID, $emergency1Data);
    }

    if ($emergency1Result === false) {
        throw new Exception('Failed to update emergency contact 1');
    }

    // ============================================
    // Update Emergency Contact 2
    // ============================================
    $emergency2Data = [
        'name' => $emergency2Name,
        'relationship' => $emergency2Relationship,
        'phone' => $emergency2Phone,
        'alternatePhone' => trim($_POST['emergency2AlternatePhone'] ?? ''),
        'priority' => 2,
        'notes' => trim($_POST['emergency2Notes'] ?? ''),
    ];

    if (!empty($emergency2ID)) {
        $emergency2Result = $enrollmentEmergencyContactGateway->updateContact($emergency2ID, $emergency2Data);
    } else {
        $emergency2Result = $enrollmentEmergencyContactGateway->addContact($gibbonChildEnrollmentFormID, $emergency2Data);
    }

    if ($emergency2Result === false) {
        throw new Exception('Failed to update emergency contact 2');
    }

    // ============================================
    // Update Health Information
    // ============================================
    $allergiesText = trim($_POST['allergies'] ?? '');
    $medicationsText = trim($_POST['medications'] ?? '');

    // Convert allergies text to JSON array if present
    $allergiesJson = null;
    if (!empty($allergiesText)) {
        $allergiesList = array_filter(array_map('trim', preg_split('/[\n,]+/', $allergiesText)));
        if (!empty($allergiesList)) {
            $allergiesJson = json_encode($allergiesList);
        }
    }

    // Convert medications text to JSON array if present
    $medicationsJson = null;
    if (!empty($medicationsText)) {
        $medicationsList = array_filter(array_map('trim', preg_split('/[\n,]+/', $medicationsText)));
        if (!empty($medicationsList)) {
            $medicationsJson = json_encode($medicationsList);
        }
    }

    $healthData = [
        'allergies' => $allergiesJson,
        'medicalConditions' => trim($_POST['medicalConditions'] ?? ''),
        'hasEpiPen' => $_POST['hasEpiPen'] ?? 'N',
        'epiPenInstructions' => trim($_POST['epiPenInstructions'] ?? ''),
        'medications' => $medicationsJson,
        'specialNeeds' => trim($_POST['specialNeeds'] ?? ''),
        'doctorName' => trim($_POST['doctorName'] ?? ''),
        'doctorPhone' => trim($_POST['doctorPhone'] ?? ''),
        'doctorAddress' => trim($_POST['doctorAddress'] ?? ''),
        'healthInsuranceNumber' => trim($_POST['healthInsuranceNumber'] ?? ''),
        'healthInsuranceExpiry' => !empty($_POST['healthInsuranceExpiry']) ? Format::dateConvert($_POST['healthInsuranceExpiry']) : null,
    ];

    $healthResult = $enrollmentHealthGateway->saveHealth($gibbonChildEnrollmentFormID, $healthData);

    if ($healthResult === false) {
        throw new Exception('Failed to update health information');
    }

    // ============================================
    // Update Nutrition Information
    // ============================================
    $nutritionData = [
        'dietaryRestrictions' => trim($_POST['dietaryRestrictions'] ?? ''),
        'foodAllergies' => trim($_POST['foodAllergies'] ?? ''),
        'feedingInstructions' => trim($_POST['feedingInstructions'] ?? ''),
        'isBottleFeeding' => $_POST['isBottleFeeding'] ?? 'N',
        'bottleFeedingInfo' => trim($_POST['bottleFeedingInfo'] ?? ''),
        'foodPreferences' => trim($_POST['foodPreferences'] ?? ''),
        'foodDislikes' => trim($_POST['foodDislikes'] ?? ''),
    ];

    $nutritionResult = $enrollmentNutritionGateway->saveNutrition($gibbonChildEnrollmentFormID, $nutritionData);

    if ($nutritionResult === false) {
        throw new Exception('Failed to update nutrition information');
    }

    // ============================================
    // Update Attendance Pattern
    // ============================================
    $attendanceData = [
        'mondayAm' => isset($_POST['mondayAm']) ? 'Y' : 'N',
        'mondayPm' => isset($_POST['mondayPm']) ? 'Y' : 'N',
        'tuesdayAm' => isset($_POST['tuesdayAm']) ? 'Y' : 'N',
        'tuesdayPm' => isset($_POST['tuesdayPm']) ? 'Y' : 'N',
        'wednesdayAm' => isset($_POST['wednesdayAm']) ? 'Y' : 'N',
        'wednesdayPm' => isset($_POST['wednesdayPm']) ? 'Y' : 'N',
        'thursdayAm' => isset($_POST['thursdayAm']) ? 'Y' : 'N',
        'thursdayPm' => isset($_POST['thursdayPm']) ? 'Y' : 'N',
        'fridayAm' => isset($_POST['fridayAm']) ? 'Y' : 'N',
        'fridayPm' => isset($_POST['fridayPm']) ? 'Y' : 'N',
        'saturdayAm' => isset($_POST['saturdayAm']) ? 'Y' : 'N',
        'saturdayPm' => isset($_POST['saturdayPm']) ? 'Y' : 'N',
        'sundayAm' => isset($_POST['sundayAm']) ? 'Y' : 'N',
        'sundayPm' => isset($_POST['sundayPm']) ? 'Y' : 'N',
        'expectedArrivalTime' => !empty($_POST['expectedArrivalTime']) ? $_POST['expectedArrivalTime'] : null,
        'expectedDepartureTime' => !empty($_POST['expectedDepartureTime']) ? $_POST['expectedDepartureTime'] : null,
        'expectedHoursPerWeek' => !empty($_POST['expectedHoursPerWeek']) ? floatval($_POST['expectedHoursPerWeek']) : null,
        'notes' => trim($_POST['attendanceNotes'] ?? ''),
    ];

    $attendanceResult = $enrollmentAttendanceGateway->saveAttendance($gibbonChildEnrollmentFormID, $attendanceData);

    if ($attendanceResult === false) {
        throw new Exception('Failed to update attendance pattern');
    }

    // Commit the transaction
    $pdo->commit();

    // Success - redirect to view page
    $URL = $session->get('absoluteURL') . '/index.php?q=/modules/ChildEnrollment/enrollment_view.php&gibbonChildEnrollmentFormID=' . $gibbonChildEnrollmentFormID . '&return=success0';
    header("Location: {$URL}");
    exit;

} catch (Exception $e) {
    // Rollback the transaction on error
    $pdo->rollBack();

    // Log the error
    error_log('Enrollment form update failed: ' . $e->getMessage());

    $URL .= '&return=error2';
    header("Location: {$URL}");
    exit;
}
