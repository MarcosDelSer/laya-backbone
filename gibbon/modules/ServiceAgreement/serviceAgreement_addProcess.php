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

use Gibbon\Module\ServiceAgreement\Domain\ServiceAgreementGateway;
use Gibbon\Module\ServiceAgreement\Domain\AnnexGateway;
use Gibbon\Services\Format;

// Include core (this file is called directly, not through module framework)
include '../../gibbon.php';

$URL = $session->get('absoluteURL') . '/index.php?q=/modules/ServiceAgreement/serviceAgreement_add.php';

// Access check
if (isActionAccessible($guid, $connection2, '/modules/ServiceAgreement/serviceAgreement_add.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
}

// Get gateways
$serviceAgreementGateway = $container->get(ServiceAgreementGateway::class);
$annexGateway = $container->get(AnnexGateway::class);

// Get current user and school year
$gibbonPersonID = $session->get('gibbonPersonID');
$gibbonSchoolYearID = $_POST['gibbonSchoolYearID'] ?? $session->get('gibbonSchoolYearID');

// Validate required fields
$gibbonPersonIDChild = $_POST['gibbonPersonIDChild'] ?? '';
$gibbonPersonIDParent = $_POST['gibbonPersonIDParent'] ?? '';
$childName = $_POST['childName'] ?? '';
$childDateOfBirth = $_POST['childDateOfBirth'] ?? '';
$parentName = $_POST['parentName'] ?? '';
$providerName = $_POST['providerName'] ?? '';
$effectiveDate = $_POST['effectiveDate'] ?? '';

// Check required fields
if (empty($gibbonPersonIDChild) || empty($gibbonPersonIDParent) || empty($childName) ||
    empty($childDateOfBirth) || empty($parentName) || empty($providerName) || empty($effectiveDate)) {
    $URL .= '&return=error1';
    header("Location: {$URL}");
    exit;
}

// Check if child already has an active or pending agreement
if ($serviceAgreementGateway->hasExistingAgreement($gibbonPersonIDChild, $gibbonSchoolYearID)) {
    $URL .= '&return=error3';
    header("Location: {$URL}");
    exit;
}

// Convert dates from display format to database format
$childDateOfBirthDB = Format::dateConvert($childDateOfBirth);
$effectiveDateDB = Format::dateConvert($effectiveDate);
$expirationDateDB = !empty($_POST['expirationDate']) ? Format::dateConvert($_POST['expirationDate']) : null;

// Generate agreement number
$agreementNumber = $serviceAgreementGateway->generateAgreementNumber($gibbonSchoolYearID);

// Process operating days (array to comma-separated string)
$operatingDays = isset($_POST['operatingDays']) && is_array($_POST['operatingDays'])
    ? implode(',', $_POST['operatingDays'])
    : 'Mon,Tue,Wed,Thu,Fri';

// Process meals included checkboxes
$includesBreakfast = isset($_POST['includesBreakfast']) ? 'Y' : 'N';
$includesLunch = isset($_POST['includesLunch']) ? 'Y' : 'N';
$includesSnacks = isset($_POST['includesSnacks']) ? 'Y' : 'N';
$includesDinner = isset($_POST['includesDinner']) ? 'Y' : 'N';

// Process attendance pattern
$attendancePattern = $_POST['attendancePattern'] ?? '';
// Try to JSON encode if it looks like structured data
if (!empty($attendancePattern) && $attendancePattern[0] !== '{' && $attendancePattern[0] !== '[') {
    // Plain text description, wrap in JSON
    $attendancePattern = json_encode(['description' => $attendancePattern]);
}

// Build data array for insert - all 13 articles
$data = [
    // System fields
    'gibbonSchoolYearID' => $gibbonSchoolYearID,
    'gibbonPersonIDChild' => $gibbonPersonIDChild,
    'gibbonPersonIDParent' => $gibbonPersonIDParent,
    'agreementNumber' => $agreementNumber,
    'status' => 'Draft', // Start as Draft, will be sent for signature

    // Article 1: Identification of Parties
    'providerName' => $providerName,
    'providerPermitNumber' => $_POST['providerPermitNumber'] ?? null,
    'providerAddress' => $_POST['providerAddress'] ?? null,
    'providerPhone' => $_POST['providerPhone'] ?? null,
    'providerEmail' => $_POST['providerEmail'] ?? null,
    'parentName' => $parentName,
    'parentAddress' => $_POST['parentAddress'] ?? null,
    'parentPhone' => $_POST['parentPhone'] ?? null,
    'parentEmail' => $_POST['parentEmail'] ?? null,
    'childName' => $childName,
    'childDateOfBirth' => $childDateOfBirthDB,

    // Article 2: Description of Services
    'maxHoursPerDay' => floatval($_POST['maxHoursPerDay'] ?? 10),
    'includesBreakfast' => $includesBreakfast,
    'includesLunch' => $includesLunch,
    'includesSnacks' => $includesSnacks,
    'includesDinner' => $includesDinner,
    'serviceDescription' => $_POST['serviceDescription'] ?? null,

    // Article 3: Operating Hours
    'operatingHoursStart' => $_POST['operatingHoursStart'] ?? '07:00:00',
    'operatingHoursEnd' => $_POST['operatingHoursEnd'] ?? '18:00:00',
    'operatingDays' => $operatingDays,

    // Article 4: Attendance Pattern
    'attendancePattern' => $attendancePattern,
    'hoursPerWeek' => !empty($_POST['hoursPerWeek']) ? floatval($_POST['hoursPerWeek']) : null,

    // Article 5: Payment Terms
    'contributionType' => $_POST['contributionType'] ?? 'Reduced',
    'dailyReducedContribution' => floatval($_POST['dailyReducedContribution'] ?? 9.35),
    'additionalDailyRate' => !empty($_POST['additionalDailyRate']) ? floatval($_POST['additionalDailyRate']) : null,
    'paymentFrequency' => $_POST['paymentFrequency'] ?? 'Monthly',
    'paymentMethod' => $_POST['paymentMethod'] ?? 'DirectDebit',
    'paymentDueDay' => !empty($_POST['paymentDueDay']) ? intval($_POST['paymentDueDay']) : null,

    // Article 6: Late Pickup Fees
    'latePickupFeePerMinute' => !empty($_POST['latePickupFeePerMinute']) ? floatval($_POST['latePickupFeePerMinute']) : 1.00,
    'latePickupGracePeriod' => !empty($_POST['latePickupGracePeriod']) ? intval($_POST['latePickupGracePeriod']) : 10,
    'latePickupMaxFee' => !empty($_POST['latePickupMaxFee']) ? floatval($_POST['latePickupMaxFee']) : null,

    // Article 7: Closure Days
    'statutoryHolidaysClosed' => $_POST['statutoryHolidaysClosed'] ?? 'Y',
    'summerClosureWeeks' => !empty($_POST['summerClosureWeeks']) ? intval($_POST['summerClosureWeeks']) : 2,
    'winterClosureWeeks' => !empty($_POST['winterClosureWeeks']) ? intval($_POST['winterClosureWeeks']) : 1,
    'closureDatesText' => $_POST['closureDatesText'] ?? null,

    // Article 8: Absence Policy
    'maxAbsenceDaysPerYear' => !empty($_POST['maxAbsenceDaysPerYear']) ? intval($_POST['maxAbsenceDaysPerYear']) : null,
    'absenceNoticeRequired' => !empty($_POST['absenceNoticeRequired']) ? intval($_POST['absenceNoticeRequired']) : 24,
    'absenceChargePolicy' => $_POST['absenceChargePolicy'] ?? 'ChargeAll',
    'medicalAbsencePolicy' => $_POST['medicalAbsencePolicy'] ?? null,

    // Article 9: Agreement Duration
    'effectiveDate' => $effectiveDateDB,
    'expirationDate' => $expirationDateDB,
    'renewalType' => $_POST['renewalType'] ?? 'AutoRenew',
    'renewalNoticeRequired' => !empty($_POST['renewalNoticeRequired']) ? intval($_POST['renewalNoticeRequired']) : 30,

    // Article 10: Termination Conditions
    'parentTerminationNotice' => intval($_POST['parentTerminationNotice'] ?? 14),
    'providerTerminationNotice' => intval($_POST['providerTerminationNotice'] ?? 14),
    'immediateTerminationConditions' => $_POST['immediateTerminationConditions'] ?? null,
    'terminationRefundPolicy' => $_POST['terminationRefundPolicy'] ?? null,

    // Article 11: Special Conditions
    'specialConditions' => $_POST['specialConditions'] ?? null,

    // Article 12: Consumer Protection Act (acknowledged at signing time)
    'consumerProtectionAcknowledged' => 'N',
    'consumerProtectionAcknowledgedDate' => null,

    // Article 13: Signatures (handled during signing process)
    'allSignaturesComplete' => 'N',
    'agreementCompletedDate' => null,

    // Language preference
    'languagePreference' => $_POST['languagePreference'] ?? 'fr',

    // Metadata
    'createdByID' => $gibbonPersonID,
];

// Insert the agreement
try {
    $gibbonServiceAgreementID = $serviceAgreementGateway->insert($data);

    if ($gibbonServiceAgreementID === false) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    // Create default annexes (A, B, C, D) for the agreement
    $annexTypes = ['A', 'B', 'C', 'D'];
    foreach ($annexTypes as $annexType) {
        $annexData = [
            'gibbonServiceAgreementID' => $gibbonServiceAgreementID,
            'annexType' => $annexType,
            'status' => 'NotApplicable', // Default to not applicable, can be updated later
        ];
        $annexGateway->insert($annexData);
    }

    // Update status to Pending Signature (ready to be sent to parent)
    $serviceAgreementGateway->updateStatus($gibbonServiceAgreementID, 'Pending Signature');

    // Success - redirect to view page
    $URL = $session->get('absoluteURL') . '/index.php?q=/modules/ServiceAgreement/serviceAgreement_view.php&gibbonServiceAgreementID=' . $gibbonServiceAgreementID . '&return=success0';
    header("Location: {$URL}");
    exit;

} catch (Exception $e) {
    // Log the error
    error_log('ServiceAgreement add error: ' . $e->getMessage());

    $URL .= '&return=error2';
    header("Location: {$URL}");
    exit;
}
