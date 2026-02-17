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

use Gibbon\Domain\System\SettingGateway;
use Gibbon\Module\RL24Submission\Domain\RL24TransmissionGateway;
use Gibbon\Module\RL24Submission\Domain\RL24EligibilityGateway;
use Gibbon\Module\RL24Submission\Domain\RL24SlipGateway;
use Gibbon\Module\RL24Submission\Services\RL24BatchProcessor;

// Include core (this file is called directly, not through module framework)
include '../../gibbon.php';

$URL = $session->get('absoluteURL') . '/index.php?q=/modules/RL24Submission/rl24_transmissions.php';

// Access check
if (isActionAccessible($guid, $connection2, '/modules/RL24Submission/rl24_transmissions.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
}

// Get required POST data
$taxYear = $_POST['taxYear'] ?? '';
$gibbonSchoolYearID = $_POST['gibbonSchoolYearID'] ?? '';
$confirm = $_POST['confirm'] ?? '';
$notes = $_POST['notes'] ?? '';
$gibbonPersonID = $session->get('gibbonPersonID');

// Validate required fields
if (empty($taxYear) || empty($gibbonSchoolYearID) || empty($confirm)) {
    $URL .= '&return=error1';
    header("Location: {$URL}");
    exit;
}

// Validate confirmation checkbox was checked
if ($confirm !== 'Y') {
    $URL .= '&return=error1&message=' . urlencode('You must confirm that all eligibility data has been reviewed.');
    header("Location: {$URL}");
    exit;
}

// Cast tax year to integer
$taxYear = (int) $taxYear;
$gibbonSchoolYearID = (int) $gibbonSchoolYearID;

// Get dependencies from container
$settingGateway = $container->get(SettingGateway::class);
$transmissionGateway = $container->get(RL24TransmissionGateway::class);
$eligibilityGateway = $container->get(RL24EligibilityGateway::class);
$slipGateway = $container->get(RL24SlipGateway::class);

// Verify provider configuration is complete
$providerName = $settingGateway->getSettingByScope('RL24 Submission', 'providerName');
$providerNEQ = $settingGateway->getSettingByScope('RL24 Submission', 'providerNEQ');
$preparerNumber = $settingGateway->getSettingByScope('RL24 Submission', 'preparerNumber');

if (empty($providerName) || empty($providerNEQ) || empty($preparerNumber)) {
    $URL .= '&return=error4&message=' . urlencode('Provider configuration is incomplete. Please configure all required settings.');
    header("Location: {$URL}");
    exit;
}

// Validate NEQ format (10 digits)
$cleanedNEQ = preg_replace('/[^0-9]/', '', $providerNEQ);
if (strlen($cleanedNEQ) !== 10) {
    $URL .= '&return=error4&message=' . urlencode('Provider NEQ must be 10 digits.');
    header("Location: {$URL}");
    exit;
}

// Check if there are approved eligibility forms for the tax year
$eligibilityForms = $eligibilityGateway->selectApprovedEligibilityByFormYear($taxYear)->fetchAll();

if (empty($eligibilityForms)) {
    $URL .= '&return=error5&message=' . urlencode('No approved eligibility forms found for tax year ' . $taxYear . '.');
    header("Location: {$URL}");
    exit;
}

// Check how many new slips would be generated (excluding existing ones)
$newSlipCount = 0;
foreach ($eligibilityForms as $form) {
    if (!$slipGateway->slipExistsForChildAndYear($form['gibbonPersonIDChild'], $taxYear)) {
        $newSlipCount++;
    }
}

if ($newSlipCount === 0) {
    $URL .= '&return=error5&message=' . urlencode('All approved eligibility forms already have RL-24 slips generated for tax year ' . $taxYear . '.');
    header("Location: {$URL}");
    exit;
}

// Create the batch processor service
$batchProcessor = new RL24BatchProcessor(
    $transmissionGateway,
    $slipGateway,
    $eligibilityGateway,
    $settingGateway
);

// Set output path for XML files
$uploadsPath = $session->get('absolutePath') . '/uploads';
$batchProcessor->setOutputPath($uploadsPath);

// Process the batch
try {
    $result = $batchProcessor->processBatch(
        $gibbonSchoolYearID,
        $taxYear,
        $gibbonPersonID,
        ['notes' => $notes]
    );
} catch (\Exception $e) {
    // Handle unexpected exceptions
    $URL .= '&return=error2&message=' . urlencode('An error occurred during batch processing: ' . $e->getMessage());
    header("Location: {$URL}");
    exit;
}

// Check processing result
if (!$result['success']) {
    $errorMessage = $result['message'] ?? 'Batch processing failed';

    // Include specific errors if available
    if (!empty($result['errors'])) {
        $errorMessage .= ': ' . implode('; ', $result['errors']);
    }

    $URL .= '&return=error2&message=' . urlencode($errorMessage);
    header("Location: {$URL}");
    exit;
}

// Get the transmission ID for the success redirect
$transmissionID = $result['transmissionID'] ?? null;

// Build success message with stats
$successMessage = sprintf(
    'Successfully generated %d RL-24 slips for tax year %d.',
    $result['stats']['slipsGenerated'] ?? 0,
    $taxYear
);

// Add warning count if any
if (!empty($result['warnings'])) {
    $successMessage .= sprintf(' (%d warnings)', count($result['warnings']));
}

// Redirect to transmissions list with success message
// If we have a transmission ID, redirect to the view page
if ($transmissionID) {
    $URL = $session->get('absoluteURL') . '/index.php?q=/modules/RL24Submission/rl24_transmissions_view.php';
    $URL .= '&gibbonRL24TransmissionID=' . $transmissionID;
    $URL .= '&return=success0';
    $URL .= '&message=' . urlencode($successMessage);
} else {
    $URL .= '&return=success0';
    $URL .= '&message=' . urlencode($successMessage);
}

header("Location: {$URL}");
exit;
