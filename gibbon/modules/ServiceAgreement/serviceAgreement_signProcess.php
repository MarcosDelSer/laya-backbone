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
use Gibbon\Module\ServiceAgreement\Domain\SignatureGateway;

// Include core (this file is called directly, not through module framework)
include '../../gibbon.php';

$gibbonServiceAgreementID = $_POST['gibbonServiceAgreementID'] ?? '';
$URL = $session->get('absoluteURL') . '/index.php?q=/modules/ServiceAgreement/serviceAgreement_sign.php&gibbonServiceAgreementID=' . $gibbonServiceAgreementID;

// Access check
if (isActionAccessible($guid, $connection2, '/modules/ServiceAgreement/serviceAgreement_sign.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
}

// Check for required parameters
if (empty($gibbonServiceAgreementID)) {
    $URL = $session->get('absoluteURL') . '/index.php?q=/modules/ServiceAgreement/serviceAgreement.php&return=error1';
    header("Location: {$URL}");
    exit;
}

// Get gateways
$serviceAgreementGateway = $container->get(ServiceAgreementGateway::class);
$annexGateway = $container->get(AnnexGateway::class);
$signatureGateway = $container->get(SignatureGateway::class);

// Get the agreement
$agreement = $serviceAgreementGateway->getAgreementWithDetails($gibbonServiceAgreementID);

if (!$agreement) {
    $URL .= '&return=error2';
    header("Location: {$URL}");
    exit;
}

// Check if agreement is in a signable state
if (!in_array($agreement['status'], ['Pending Signature', 'Active'])) {
    $URL .= '&return=error3';
    header("Location: {$URL}");
    exit;
}

// Get form data
$signerType = $_POST['signerType'] ?? '';
$signerName = trim($_POST['signerName'] ?? '');
$signerEmail = trim($_POST['signerEmail'] ?? '');
$signatureType = $_POST['signatureType'] ?? 'Typed';
$typedSignature = trim($_POST['typedSignature'] ?? '');
$signatureData = $_POST['signatureData'] ?? '';
$consumerProtectionAcknowledged = isset($_POST['consumerProtectionAcknowledged']) && $_POST['consumerProtectionAcknowledged'] === 'on';
$termsAccepted = isset($_POST['termsAccepted']) && $_POST['termsAccepted'] === 'on';
$legalAcknowledgment = isset($_POST['legalAcknowledgment']) && $_POST['legalAcknowledgment'] === 'on';

// Validate required fields
if (empty($signerType) || empty($signerName)) {
    $URL .= '&return=error1';
    header("Location: {$URL}");
    exit;
}

// Validate signer type
$validSignerTypes = ['Parent', 'Provider', 'Witness'];
if (!in_array($signerType, $validSignerTypes)) {
    $URL .= '&return=error1';
    header("Location: {$URL}");
    exit;
}

// Validate signature data based on type
$finalSignatureData = '';
if ($signatureType === 'Typed') {
    if (empty($typedSignature)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }
    // For typed signatures, store the typed name as a JSON object
    $finalSignatureData = json_encode([
        'type' => 'typed',
        'signature' => $typedSignature,
        'timestamp' => date('Y-m-d H:i:s'),
    ]);
} elseif ($signatureType === 'Drawn') {
    if (empty($signatureData)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }
    // For drawn signatures, the data is already base64 encoded
    $finalSignatureData = $signatureData;
} else {
    $URL .= '&return=error1';
    header("Location: {$URL}");
    exit;
}

// Validate required acknowledgments
if (!$consumerProtectionAcknowledged || !$termsAccepted || !$legalAcknowledgment) {
    $URL .= '&return=error1';
    header("Location: {$URL}");
    exit;
}

// Check if this signer type has already signed
if ($signatureGateway->hasSignature($gibbonServiceAgreementID, $signerType)) {
    $URL .= '&return=error4';
    header("Location: {$URL}");
    exit;
}

// Collect audit trail information
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
// Handle proxied requests
if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $forwardedIps = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
    $ipAddress = trim($forwardedIps[0]);
}
if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
    $ipAddress = $_SERVER['HTTP_X_REAL_IP'];
}

$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$sessionID = session_id();

// Get current user if logged in
$gibbonPersonID = $session->get('gibbonPersonID');

// If signer is the parent from the agreement, link to their person ID
$signerPersonID = null;
if ($signerType === 'Parent' && !empty($agreement['gibbonPersonIDParent'])) {
    $signerPersonID = $agreement['gibbonPersonIDParent'];
} elseif ($gibbonPersonID) {
    // Use current logged-in user's ID for provider or other signers
    $signerPersonID = $gibbonPersonID;
}

// Generate verification hash of the agreement content at time of signing
$hashData = [
    'agreementID' => $gibbonServiceAgreementID,
    'agreementNumber' => $agreement['agreementNumber'],
    'childName' => $agreement['childName'],
    'parentName' => $agreement['parentName'],
    'providerName' => $agreement['providerName'],
    'effectiveDate' => $agreement['effectiveDate'],
    'expirationDate' => $agreement['expirationDate'],
    'dailyReducedContribution' => $agreement['dailyReducedContribution'],
    'signerType' => $signerType,
    'signerName' => $signerName,
    'signedAt' => date('Y-m-d H:i:s'),
];
$verificationHash = $signatureGateway->generateVerificationHash($hashData);

// Store the signature
try {
    $signatureID = $signatureGateway->storeSignature(
        $gibbonServiceAgreementID,
        $signerType,
        $signerName,
        $finalSignatureData,
        $signatureType,
        $ipAddress,
        $signerPersonID,
        $signerEmail,
        $userAgent,
        $sessionID,
        null, // geoLocation - could be added with JS geolocation API
        null, // deviceFingerprint - could be added with JS fingerprinting
        $legalAcknowledgment,
        $consumerProtectionAcknowledged,
        $termsAccepted,
        $verificationHash
    );

    if ($signatureID === false) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    // Update Consumer Protection acknowledgment on the agreement if this is a parent signature
    if ($signerType === 'Parent' && $consumerProtectionAcknowledged && $agreement['consumerProtectionAcknowledged'] !== 'Y') {
        $serviceAgreementGateway->markConsumerProtectionAcknowledged($gibbonServiceAgreementID);
    }

    // Check if all required signatures are now complete
    $allSignaturesComplete = $signatureGateway->areAllSignaturesComplete($gibbonServiceAgreementID, false);

    if ($allSignaturesComplete) {
        // Update agreement status to Active
        $serviceAgreementGateway->markSignaturesComplete($gibbonServiceAgreementID);

        // Sign all pending annexes
        $annexGateway->signAllPendingAnnexes($gibbonServiceAgreementID, $signerPersonID ?? $gibbonPersonID);
    }

    // Success
    $URL = $session->get('absoluteURL') . '/index.php?q=/modules/ServiceAgreement/serviceAgreement_sign.php&gibbonServiceAgreementID=' . $gibbonServiceAgreementID . '&return=success0';
    header("Location: {$URL}");
    exit;

} catch (Exception $e) {
    // Log the error
    error_log('ServiceAgreement sign error: ' . $e->getMessage());

    $URL .= '&return=error2';
    header("Location: {$URL}");
    exit;
}
