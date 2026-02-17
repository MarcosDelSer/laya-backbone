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
use Gibbon\Module\MedicalProtocol\Domain\ProtocolGateway;
use Gibbon\Module\MedicalProtocol\Domain\AuthorizationGateway;
use Gibbon\Module\MedicalProtocol\Domain\AdministrationGateway;

// Module setup - breadcrumbs
$page->breadcrumbs->add(__('Medical Protocol'));

// Access check
if (!isActionAccessible($guid, $connection2, '/modules/MedicalProtocol/medicalProtocol.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get school year ID from session
    $gibbonSchoolYearID = $session->get('gibbonSchoolYearID');

    // Get date from request or default to today
    $date = $_GET['date'] ?? date('Y-m-d');

    // Validate date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $date = date('Y-m-d');
    }

    // Get gateways via DI container
    $protocolGateway = $container->get(ProtocolGateway::class);
    $authorizationGateway = $container->get(AuthorizationGateway::class);
    $administrationGateway = $container->get(AdministrationGateway::class);

    // Get summary statistics
    $protocolSummary = $protocolGateway->getProtocolSummary();
    $authorizationSummary = $authorizationGateway->getAuthorizationSummary($gibbonSchoolYearID);
    $authorizationByProtocol = $authorizationGateway->getAuthorizationSummaryByProtocol($gibbonSchoolYearID);
    $administrationSummary = $administrationGateway->getAdministrationSummaryByDate($gibbonSchoolYearID, $date);

    // Get pending items
    $pendingFollowUps = $administrationGateway->selectAdministrationsPendingFollowUp($gibbonSchoolYearID, $date)->fetchAll();
    $expiredWeightAuthorizations = $authorizationGateway->selectExpiredWeightAuthorizations($gibbonSchoolYearID)->fetchAll();
    $expiringAuthorizations = $authorizationGateway->selectExpiringAuthorizations($gibbonSchoolYearID, 14)->fetchAll();

    // Page header with date selector
    echo '<h2>' . __('Medical Protocol Dashboard') . '</h2>';

    // Date navigation form
    echo '<div class="mb-4">';
    echo '<form method="get" action="' . $session->get('absoluteURL') . '/index.php" class="inline">';
    echo '<input type="hidden" name="q" value="/modules/MedicalProtocol/medicalProtocol.php">';
    echo '<label class="mr-2">' . __('Date') . ':</label>';
    echo '<input type="date" name="date" value="' . htmlspecialchars($date) . '" class="standardWidth" onchange="this.form.submit()">';
    echo '<button type="submit" class="ml-2">' . __('Go') . '</button>';
    echo '</form>';

    // Quick navigation to today
    if ($date != date('Y-m-d')) {
        echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/MedicalProtocol/medicalProtocol.php&date=' . date('Y-m-d') . '" class="ml-4">' . __('Today') . '</a>';
    }
    echo '</div>';

    // Display formatted date
    echo '<p class="text-lg mb-4">' . __('Showing data for') . ': <strong>' . Format::date($date) . '</strong></p>';

    // Dashboard grid layout
    echo '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">';

    // Protocol Summary Card
    echo '<div class="bg-white rounded-lg shadow p-4">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">' . __('Protocol Summary') . '</h3>';
    echo '<div class="space-y-2">';
    echo '<div class="flex justify-between"><span>' . __('Active Protocols') . ':</span><span class="font-bold text-green-600">' . ($protocolSummary['activeProtocols'] ?? 0) . '</span></div>';
    echo '<div class="flex justify-between"><span>' . __('Medication Protocols') . ':</span><span>' . ($protocolSummary['medicationProtocols'] ?? 0) . '</span></div>';
    echo '<div class="flex justify-between"><span>' . __('Topical Protocols') . ':</span><span>' . ($protocolSummary['topicalProtocols'] ?? 0) . '</span></div>';

    // Protocol details
    if (!empty($authorizationByProtocol) && is_array($authorizationByProtocol)) {
        echo '<div class="border-t mt-3 pt-3">';
        foreach ($authorizationByProtocol as $protocolData) {
            $protocolName = $protocolData['name'] ?? __('Unknown');
            $formCode = $protocolData['formCode'] ?? '';
            $activeCount = $protocolData['activeAuthorizations'] ?? 0;
            echo '<div class="flex justify-between text-sm"><span>' . htmlspecialchars($protocolName) . ' (' . htmlspecialchars($formCode) . '):</span><span>' . $activeCount . ' ' . __('authorized') . '</span></div>';
        }
        echo '</div>';
    }
    echo '</div>';
    echo '</div>';

    // Authorization Status Card
    echo '<div class="bg-white rounded-lg shadow p-4">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">' . __('Authorization Status') . '</h3>';
    echo '<div class="space-y-2">';
    echo '<div class="flex justify-between"><span>' . __('Active Authorizations') . ':</span><span class="font-bold text-green-600">' . ($authorizationSummary['activeAuthorizations'] ?? 0) . '</span></div>';
    echo '<div class="flex justify-between"><span>' . __('Pending Authorizations') . ':</span><span class="text-yellow-500">' . ($authorizationSummary['pendingAuthorizations'] ?? 0) . '</span></div>';
    echo '<div class="flex justify-between"><span>' . __('Children Authorized') . ':</span><span>' . ($authorizationSummary['uniqueChildren'] ?? 0) . '</span></div>';

    // Weight status
    $expiredWeightCount = $authorizationSummary['expiredWeightAuthorizations'] ?? 0;
    $expiringWeightCount = $authorizationSummary['expiringWeightAuthorizations'] ?? 0;

    if ($expiredWeightCount > 0) {
        echo '<div class="flex justify-between"><span>' . __('Weight Update Required') . ':</span><span class="text-red-500 font-bold">' . $expiredWeightCount . '</span></div>';
    }
    if ($expiringWeightCount > 0) {
        echo '<div class="flex justify-between"><span>' . __('Weight Expiring Soon') . ':</span><span class="text-orange-500">' . $expiringWeightCount . '</span></div>';
    }
    echo '</div>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/MedicalProtocol/medicalProtocol_authorizations.php" class="block mt-3 text-blue-600 hover:underline">' . __('View Authorizations') . ' &rarr;</a>';
    echo '</div>';

    // Today's Administrations Card
    echo '<div class="bg-white rounded-lg shadow p-4">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">' . __("Today's Administrations") . '</h3>';
    echo '<div class="space-y-2">';
    $totalAdministrations = $administrationSummary['totalAdministrations'] ?? 0;
    echo '<div class="flex justify-between"><span>' . __('Total Administrations') . ':</span><span class="font-bold">' . $totalAdministrations . '</span></div>';
    echo '<div class="flex justify-between"><span>' . __('Children Treated') . ':</span><span>' . ($administrationSummary['childrenCount'] ?? 0) . '</span></div>';

    // Protocol breakdown
    $acetaminophenCount = $administrationSummary['acetaminophenCount'] ?? 0;
    $insectRepellentCount = $administrationSummary['insectRepellentCount'] ?? 0;
    if ($totalAdministrations > 0) {
        echo '<div class="border-t mt-3 pt-3">';
        echo '<div class="flex justify-between text-sm"><span>' . __('Acetaminophen (FO-0647)') . ':</span><span>' . $acetaminophenCount . '</span></div>';
        echo '<div class="flex justify-between text-sm"><span>' . __('Insect Repellent (FO-0646)') . ':</span><span>' . $insectRepellentCount . '</span></div>';
        echo '</div>';

        // Follow-up status
        $followUpsPending = $administrationSummary['followUpsPending'] ?? 0;
        $followUpsCompleted = $administrationSummary['followUpsCompleted'] ?? 0;
        if ($followUpsPending > 0 || $followUpsCompleted > 0) {
            echo '<div class="border-t mt-3 pt-3">';
            if ($followUpsPending > 0) {
                echo '<div class="flex justify-between"><span>' . __('Follow-ups Pending') . ':</span><span class="text-orange-500">' . $followUpsPending . '</span></div>';
            }
            if ($followUpsCompleted > 0) {
                echo '<div class="flex justify-between"><span>' . __('Follow-ups Completed') . ':</span><span class="text-green-600">' . $followUpsCompleted . '</span></div>';
            }
            echo '</div>';
        }

        // Parent notifications
        $parentsNotified = $administrationSummary['parentsNotified'] ?? 0;
        $parentsAcknowledged = $administrationSummary['parentsAcknowledged'] ?? 0;
        echo '<div class="flex justify-between"><span>' . __('Parents Notified') . ':</span><span>' . $parentsNotified . '/' . $totalAdministrations . '</span></div>';
    } else {
        echo '<p class="text-gray-500">' . __('No administrations today.') . '</p>';
    }
    echo '</div>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/MedicalProtocol/medicalProtocol_log.php&date=' . $date . '" class="block mt-3 text-blue-600 hover:underline">' . __('View Administration Log') . ' &rarr;</a>';
    echo '</div>';

    // Pending Follow-ups Card
    echo '<div class="bg-white rounded-lg shadow p-4">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">' . __('Pending Follow-ups') . '</h3>';
    echo '<div class="space-y-2">';
    $pendingCount = count($pendingFollowUps);
    if ($pendingCount > 0) {
        echo '<p class="text-orange-500 font-semibold mb-2">' . $pendingCount . ' ' . __('follow-up(s) require attention') . '</p>';
        // Show up to 5 pending follow-ups
        $displayCount = min($pendingCount, 5);
        for ($i = 0; $i < $displayCount; $i++) {
            $followUp = $pendingFollowUps[$i];
            $childName = htmlspecialchars($followUp['preferredName'] . ' ' . $followUp['surname']);
            $followUpTime = $followUp['followUpTime'] ?? '';
            $protocolName = htmlspecialchars($followUp['protocolName'] ?? '');
            $formCode = htmlspecialchars($followUp['formCode'] ?? '');

            // Check if follow-up is overdue
            $currentTime = date('H:i:s');
            $isOverdue = !empty($followUpTime) && $followUpTime < $currentTime;
            $statusClass = $isOverdue ? 'text-red-500' : 'text-yellow-600';
            $statusText = $isOverdue ? __('Overdue') : __('Due') . ' ' . Format::time($followUpTime);

            echo '<div class="bg-gray-50 p-2 rounded mb-2">';
            echo '<div class="font-medium">' . $childName . '</div>';
            echo '<div class="text-sm text-gray-600">' . $protocolName . ' (' . $formCode . ')</div>';
            echo '<div class="text-sm ' . $statusClass . '">' . $statusText . '</div>';
            echo '</div>';
        }
        if ($pendingCount > 5) {
            echo '<p class="text-sm text-gray-500">' . sprintf(__('...and %d more'), $pendingCount - 5) . '</p>';
        }
    } else {
        echo '<p class="text-green-600">' . __('No pending follow-ups.') . '</p>';
    }
    echo '</div>';
    echo '</div>';

    // Weight Updates Required Card
    echo '<div class="bg-white rounded-lg shadow p-4">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">' . __('Weight Updates Required') . '</h3>';
    echo '<div class="space-y-2">';
    $expiredCount = count($expiredWeightAuthorizations);
    $expiringCount = count($expiringAuthorizations);

    if ($expiredCount > 0) {
        echo '<p class="text-red-500 font-semibold mb-2">' . $expiredCount . ' ' . __('authorization(s) have expired weights') . '</p>';
        // Show up to 5 expired
        $displayCount = min($expiredCount, 5);
        for ($i = 0; $i < $displayCount; $i++) {
            $auth = $expiredWeightAuthorizations[$i];
            $childName = htmlspecialchars($auth['preferredName'] . ' ' . $auth['surname']);
            $weight = $auth['weightKg'] ?? 0;
            $expiredDate = Format::date($auth['weightExpiryDate']);
            $protocolName = htmlspecialchars($auth['protocolName'] ?? '');

            echo '<div class="bg-red-50 p-2 rounded mb-2">';
            echo '<div class="font-medium">' . $childName . '</div>';
            echo '<div class="text-sm text-gray-600">' . $protocolName . ' - ' . $weight . ' kg</div>';
            echo '<div class="text-sm text-red-500">' . __('Expired') . ': ' . $expiredDate . '</div>';
            echo '</div>';
        }
        if ($expiredCount > 5) {
            echo '<p class="text-sm text-gray-500">' . sprintf(__('...and %d more'), $expiredCount - 5) . '</p>';
        }
    }

    if ($expiringCount > 0 && $expiredCount < 5) {
        if ($expiredCount > 0) {
            echo '<div class="border-t mt-3 pt-3">';
        }
        echo '<p class="text-orange-500 font-semibold mb-2">' . $expiringCount . ' ' . __('weight(s) expiring within 14 days') . '</p>';
        // Show remaining slots up to 5 total
        $remainingSlots = 5 - $expiredCount;
        $displayCount = min($expiringCount, $remainingSlots);
        for ($i = 0; $i < $displayCount; $i++) {
            $auth = $expiringAuthorizations[$i];
            $childName = htmlspecialchars($auth['preferredName'] . ' ' . $auth['surname']);
            $weight = $auth['weightKg'] ?? 0;
            $expiryDate = Format::date($auth['weightExpiryDate']);
            $protocolName = htmlspecialchars($auth['protocolName'] ?? '');

            echo '<div class="bg-yellow-50 p-2 rounded mb-2">';
            echo '<div class="font-medium">' . $childName . '</div>';
            echo '<div class="text-sm text-gray-600">' . $protocolName . ' - ' . $weight . ' kg</div>';
            echo '<div class="text-sm text-yellow-600">' . __('Expires') . ': ' . $expiryDate . '</div>';
            echo '</div>';
        }
        if ($expiredCount > 0) {
            echo '</div>';
        }
    }

    if ($expiredCount == 0 && $expiringCount == 0) {
        echo '<p class="text-green-600">' . __('All weights are up to date.') . '</p>';
    }
    echo '</div>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/MedicalProtocol/medicalProtocol_authorizations.php&weightExpired=Y" class="block mt-3 text-blue-600 hover:underline">' . __('View All Weight Issues') . ' &rarr;</a>';
    echo '</div>';

    // Compliance Summary Card
    echo '<div class="bg-white rounded-lg shadow p-4">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">' . __('Compliance Summary') . '</h3>';
    echo '<div class="space-y-2">';

    // Active authorizations by protocol
    echo '<div class="text-sm font-medium text-gray-600 mb-2">' . __('Quebec Protocol Coverage') . '</div>';
    if (!empty($authorizationByProtocol)) {
        foreach ($authorizationByProtocol as $protocolData) {
            $formCode = $protocolData['formCode'] ?? '';
            $activeCount = $protocolData['activeAuthorizations'] ?? 0;
            $expiredWeight = $protocolData['expiredWeightAuthorizations'] ?? 0;

            $statusClass = $expiredWeight > 0 ? 'text-orange-500' : 'text-green-600';
            $statusIcon = $expiredWeight > 0 ? '⚠️' : '✓';

            echo '<div class="flex justify-between items-center">';
            echo '<span>' . htmlspecialchars($formCode) . ':</span>';
            echo '<span class="' . $statusClass . '">' . $statusIcon . ' ' . $activeCount . ' ' . __('active') . '</span>';
            echo '</div>';
        }
    } else {
        echo '<p class="text-gray-500">' . __('No protocol data available.') . '</p>';
    }

    // Overall compliance indicators
    $totalActive = $authorizationSummary['activeAuthorizations'] ?? 0;
    $totalExpiredWeight = $authorizationSummary['expiredWeightAuthorizations'] ?? 0;

    if ($totalActive > 0) {
        $complianceRate = round((($totalActive - $totalExpiredWeight) / $totalActive) * 100);
        echo '<div class="border-t mt-3 pt-3">';
        echo '<div class="flex justify-between"><span>' . __('Weight Compliance Rate') . ':</span>';
        $complianceClass = $complianceRate >= 90 ? 'text-green-600' : ($complianceRate >= 70 ? 'text-yellow-500' : 'text-red-500');
        echo '<span class="font-bold ' . $complianceClass . '">' . $complianceRate . '%</span></div>';
        echo '</div>';
    }
    echo '</div>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/MedicalProtocol/medicalProtocol_compliance.php" class="block mt-3 text-blue-600 hover:underline">' . __('View Compliance Report') . ' &rarr;</a>';
    echo '</div>';

    echo '</div>'; // End grid

    // Quick Action Buttons
    echo '<div class="mt-6">';
    echo '<h3 class="text-lg font-semibold mb-3">' . __('Quick Actions') . '</h3>';
    echo '<div class="flex flex-wrap gap-2">';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/MedicalProtocol/medicalProtocol_administer.php" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">' . __('Administer Protocol') . '</a>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/MedicalProtocol/medicalProtocol_log.php&date=' . $date . '" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">' . __('View Log') . '</a>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/MedicalProtocol/medicalProtocol_authorizations.php" class="bg-purple-500 text-white px-4 py-2 rounded hover:bg-purple-600">' . __('Manage Authorizations') . '</a>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/MedicalProtocol/medicalProtocol_compliance.php" class="bg-yellow-500 text-white px-4 py-2 rounded hover:bg-yellow-600">' . __('Compliance Report') . '</a>';
    echo '</div>';
    echo '</div>';
}
