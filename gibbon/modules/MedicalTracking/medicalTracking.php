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
use Gibbon\Module\MedicalTracking\Domain\AllergyGateway;
use Gibbon\Module\MedicalTracking\Domain\MedicationGateway;
use Gibbon\Module\MedicalTracking\Domain\AccommodationPlanGateway;
use Gibbon\Module\MedicalTracking\Domain\MedicalAlertGateway;

// Module setup - breadcrumbs
$page->breadcrumbs->add(__('Medical Tracking'));

// Access check
if (!isActionAccessible($guid, $connection2, '/modules/MedicalTracking/medicalTracking.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get gateways via DI container
    $allergyGateway = $container->get(AllergyGateway::class);
    $medicationGateway = $container->get(MedicationGateway::class);
    $accommodationPlanGateway = $container->get(AccommodationPlanGateway::class);
    $medicalAlertGateway = $container->get(MedicalAlertGateway::class);

    // Get summary statistics
    $allergySummary = $allergyGateway->getAllergySummary();
    $medicationSummary = $medicationGateway->getMedicationSummary();
    $accommodationSummary = $accommodationPlanGateway->getAccommodationPlanSummary();
    $alertStatistics = $medicalAlertGateway->getAlertStatistics();
    $expirationSummary = $medicationGateway->getExpirationMonitoringSummary(30);

    // Get children with severe allergies and EpiPen requirements
    $severeAllergyChildren = $allergyGateway->selectChildrenWithSevereAllergies();
    $epiPenChildren = $allergyGateway->selectChildrenWithEpiPen();

    // Get children with staff-administered medications
    $staffMedicationChildren = $medicationGateway->selectChildrenWithStaffMedications();

    // Get unverified items needing attention
    $unverifiedAllergies = $allergyGateway->selectUnverifiedAllergies();
    $unverifiedMedications = $medicationGateway->selectUnverifiedMedications();

    // Get expired and expiring medications
    $expiredMedications = $medicationGateway->selectExpiredMedications();
    $expiringSoonMedications = $medicationGateway->selectMedicationsExpiringSoon(30);

    // Get critical alerts
    $criticalAlerts = $medicalAlertGateway->selectCriticalAlerts();

    // Calculate totals for display
    $totalAllergies = 0;
    $totalSevereAllergies = 0;
    $totalEpiPenRequired = 0;
    foreach ($allergySummary as $row) {
        $totalAllergies += $row['totalCount'];
        if (in_array($row['severity'], ['Severe', 'Life-Threatening'])) {
            $totalSevereAllergies += $row['totalCount'];
        }
        $totalEpiPenRequired += $row['epiPenCount'];
    }

    $totalMedications = 0;
    $totalStaffAdministered = 0;
    foreach ($medicationSummary as $row) {
        $totalMedications += $row['totalCount'];
        if (in_array($row['administeredBy'], ['Staff', 'Nurse'])) {
            $totalStaffAdministered += $row['totalCount'];
        }
    }

    $totalPlans = 0;
    $totalPendingApproval = 0;
    foreach ($accommodationSummary as $row) {
        $totalPlans += $row['totalPlans'];
        $totalPendingApproval += $row['pendingCount'];
    }

    $totalCriticalAlerts = 0;
    $totalWarningAlerts = 0;
    foreach ($alertStatistics as $row) {
        if ($row['alertLevel'] === 'Critical') {
            $totalCriticalAlerts += $row['totalCount'];
        } elseif ($row['alertLevel'] === 'Warning') {
            $totalWarningAlerts += $row['totalCount'];
        }
    }

    // Page header
    echo '<h2>' . __('Medical Tracking Dashboard') . '</h2>';
    echo '<p class="text-lg mb-4">' . __('Overview of children with medical needs, allergies, medications, and accommodation plans.') . '</p>';

    // Alert Banner for Critical Items
    $criticalCount = $criticalAlerts->rowCount();
    $expiredCount = $expiredMedications->rowCount();
    $unverifiedAllergyCount = $unverifiedAllergies->rowCount();
    $unverifiedMedicationCount = $unverifiedMedications->rowCount();

    if ($criticalCount > 0 || $expiredCount > 0 || $unverifiedAllergyCount > 0 || $unverifiedMedicationCount > 0) {
        echo '<div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6">';
        echo '<div class="flex">';
        echo '<div class="flex-shrink-0"><span class="text-red-500 text-2xl">&#9888;</span></div>';
        echo '<div class="ml-3">';
        echo '<h3 class="text-red-800 font-semibold">' . __('Attention Required') . '</h3>';
        echo '<ul class="list-disc list-inside text-red-700 mt-2">';
        if ($criticalCount > 0) {
            echo '<li>' . sprintf(__('%d critical medical alert(s) active'), $criticalCount) . '</li>';
        }
        if ($expiredCount > 0) {
            echo '<li>' . sprintf(__('%d expired medication(s) need replacement'), $expiredCount) . '</li>';
        }
        if ($unverifiedAllergyCount > 0) {
            echo '<li>' . sprintf(__('%d allergy record(s) awaiting verification'), $unverifiedAllergyCount) . '</li>';
        }
        if ($unverifiedMedicationCount > 0) {
            echo '<li>' . sprintf(__('%d medication record(s) awaiting verification'), $unverifiedMedicationCount) . '</li>';
        }
        echo '</ul>';
        echo '</div></div></div>';
    }

    // Dashboard grid layout
    echo '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">';

    // Allergies Card
    echo '<div class="bg-white rounded-lg shadow p-4">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">' . __('Allergies') . '</h3>';
    echo '<div class="space-y-2">';
    echo '<div class="flex justify-between"><span>' . __('Total Allergies') . ':</span><span class="font-bold">' . $totalAllergies . '</span></div>';
    echo '<div class="flex justify-between"><span>' . __('Severe/Life-Threatening') . ':</span><span class="font-bold text-red-600">' . $totalSevereAllergies . '</span></div>';
    echo '<div class="flex justify-between"><span>' . __('EpiPen Required') . ':</span><span class="font-bold text-orange-600">' . $totalEpiPenRequired . '</span></div>';
    echo '<div class="flex justify-between"><span>' . __('Children with Severe') . ':</span><span>' . $severeAllergyChildren->rowCount() . '</span></div>';
    echo '<div class="flex justify-between"><span>' . __('Unverified') . ':</span><span class="text-yellow-600">' . $unverifiedAllergyCount . '</span></div>';
    echo '</div>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/MedicalTracking/medicalTracking_allergies.php" class="block mt-3 text-blue-600 hover:underline">' . __('Manage Allergies') . ' &rarr;</a>';
    echo '</div>';

    // Medications Card
    echo '<div class="bg-white rounded-lg shadow p-4">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">' . __('Medications') . '</h3>';
    echo '<div class="space-y-2">';
    echo '<div class="flex justify-between"><span>' . __('Total Medications') . ':</span><span class="font-bold">' . $totalMedications . '</span></div>';
    echo '<div class="flex justify-between"><span>' . __('Staff Administered') . ':</span><span class="font-bold text-blue-600">' . $totalStaffAdministered . '</span></div>';
    echo '<div class="flex justify-between"><span>' . __('Expired') . ':</span><span class="font-bold text-red-600">' . ($expirationSummary['expiredCount'] ?? 0) . '</span></div>';
    echo '<div class="flex justify-between"><span>' . __('Expiring (30 days)') . ':</span><span class="text-orange-500">' . ($expirationSummary['expiringSoonCount'] ?? 0) . '</span></div>';
    echo '<div class="flex justify-between"><span>' . __('Unverified') . ':</span><span class="text-yellow-600">' . $unverifiedMedicationCount . '</span></div>';
    echo '</div>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/MedicalTracking/medicalTracking_medications.php" class="block mt-3 text-blue-600 hover:underline">' . __('Manage Medications') . ' &rarr;</a>';
    echo '</div>';

    // Accommodation Plans Card
    echo '<div class="bg-white rounded-lg shadow p-4">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">' . __('Accommodation Plans') . '</h3>';
    echo '<div class="space-y-2">';
    echo '<div class="flex justify-between"><span>' . __('Total Plans') . ':</span><span class="font-bold">' . $totalPlans . '</span></div>';
    echo '<div class="flex justify-between"><span>' . __('Pending Approval') . ':</span><span class="text-orange-500">' . $totalPendingApproval . '</span></div>';
    if (!empty($accommodationSummary)) {
        foreach ($accommodationSummary as $plan) {
            $planType = $plan['planType'] ?? __('Unknown');
            echo '<div class="flex justify-between"><span>' . __($planType) . ':</span><span>' . ($plan['totalPlans'] ?? 0) . '</span></div>';
        }
    }
    echo '</div>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/MedicalTracking/medicalTracking_accommodations.php" class="block mt-3 text-blue-600 hover:underline">' . __('Manage Plans') . ' &rarr;</a>';
    echo '</div>';

    // Medical Alerts Card
    echo '<div class="bg-white rounded-lg shadow p-4">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">' . __('Medical Alerts') . '</h3>';
    echo '<div class="space-y-2">';
    echo '<div class="flex justify-between"><span>' . __('Critical Alerts') . ':</span><span class="font-bold text-red-600">' . $totalCriticalAlerts . '</span></div>';
    echo '<div class="flex justify-between"><span>' . __('Warning Alerts') . ':</span><span class="font-bold text-orange-500">' . $totalWarningAlerts . '</span></div>';
    $totalInfoAlerts = 0;
    foreach ($alertStatistics as $row) {
        if ($row['alertLevel'] === 'Info') {
            $totalInfoAlerts += $row['totalCount'];
        }
    }
    echo '<div class="flex justify-between"><span>' . __('Info Alerts') . ':</span><span class="text-blue-500">' . $totalInfoAlerts . '</span></div>';
    // Break down by type
    $alertTypes = [];
    foreach ($alertStatistics as $row) {
        $type = $row['alertType'] ?? 'Unknown';
        if (!isset($alertTypes[$type])) {
            $alertTypes[$type] = 0;
        }
        $alertTypes[$type] += $row['totalCount'];
    }
    foreach ($alertTypes as $type => $count) {
        echo '<div class="flex justify-between"><span>' . __($type) . ':</span><span>' . $count . '</span></div>';
    }
    echo '</div>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/MedicalTracking/medicalTracking_alerts.php" class="block mt-3 text-blue-600 hover:underline">' . __('View Alerts') . ' &rarr;</a>';
    echo '</div>';

    // Children with Severe Allergies Card
    echo '<div class="bg-white rounded-lg shadow p-4">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">' . __('Severe Allergies') . '</h3>';
    echo '<div class="space-y-2">';
    $severeCount = $severeAllergyChildren->rowCount();
    if ($severeCount > 0) {
        $displayCount = 0;
        while ($child = $severeAllergyChildren->fetch()) {
            $displayCount++;
            if ($displayCount > 5) {
                echo '<p class="text-sm text-gray-500 mt-2">' . sprintf(__('...and %d more'), $severeCount - 5) . '</p>';
                break;
            }
            $name = Format::name('', $child['preferredName'], $child['surname'], 'Student', false, true);
            $hasEpiPen = $child['hasEpiPen'] ? '<span class="ml-2 text-red-500" title="EpiPen Required">&#128137;</span>' : '';
            echo '<div class="flex items-center justify-between">';
            echo '<span class="truncate">' . htmlspecialchars($name) . $hasEpiPen . '</span>';
            echo '</div>';
        }
    } else {
        echo '<p class="text-gray-500">' . __('No children with severe allergies.') . '</p>';
    }
    echo '</div>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/MedicalTracking/medicalTracking_allergies.php&filter=severe" class="block mt-3 text-blue-600 hover:underline">' . __('View All') . ' &rarr;</a>';
    echo '</div>';

    // Children with EpiPen Card
    echo '<div class="bg-white rounded-lg shadow p-4">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">' . __('EpiPen Required') . '</h3>';
    echo '<div class="space-y-2">';
    $epiPenCount = $epiPenChildren->rowCount();
    if ($epiPenCount > 0) {
        $displayCount = 0;
        while ($child = $epiPenChildren->fetch()) {
            $displayCount++;
            if ($displayCount > 5) {
                echo '<p class="text-sm text-gray-500 mt-2">' . sprintf(__('...and %d more'), $epiPenCount - 5) . '</p>';
                break;
            }
            $name = Format::name('', $child['preferredName'], $child['surname'], 'Student', false, true);
            echo '<div class="flex items-center justify-between">';
            echo '<span class="truncate text-sm">' . htmlspecialchars($name) . '</span>';
            echo '</div>';
            if (!empty($child['epiPenDetails'])) {
                echo '<p class="text-xs text-gray-500 truncate pl-2">' . htmlspecialchars($child['epiPenDetails']) . '</p>';
            }
        }
    } else {
        echo '<p class="text-gray-500">' . __('No children require EpiPen.') . '</p>';
    }
    echo '</div>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/MedicalTracking/medicalTracking_allergies.php&epiPen=Y" class="block mt-3 text-blue-600 hover:underline">' . __('View All') . ' &rarr;</a>';
    echo '</div>';

    echo '</div>'; // End grid

    // Critical Alerts Section (if any)
    if ($criticalCount > 0) {
        echo '<div class="mt-6">';
        echo '<h3 class="text-lg font-semibold mb-3">' . __('Critical Alerts') . '</h3>';
        echo '<div class="bg-red-50 rounded-lg p-4">';
        echo '<div class="space-y-3">';
        $criticalAlerts->reset();
        while ($alert = $criticalAlerts->fetch()) {
            $childName = Format::name('', $alert['preferredName'], $alert['surname'], 'Student', false, true);
            echo '<div class="bg-white rounded p-3 border-l-4 border-red-500">';
            echo '<div class="flex justify-between items-start">';
            echo '<div>';
            echo '<span class="font-semibold text-red-700">' . htmlspecialchars($alert['title']) . '</span>';
            echo '<span class="ml-2 text-sm text-gray-600">- ' . htmlspecialchars($childName) . '</span>';
            echo '</div>';
            echo '<span class="text-xs text-gray-500">' . Format::date($alert['timestampCreated']) . '</span>';
            echo '</div>';
            if (!empty($alert['description'])) {
                echo '<p class="text-sm text-gray-700 mt-1">' . htmlspecialchars($alert['description']) . '</p>';
            }
            if (!empty($alert['actionRequired'])) {
                echo '<p class="text-sm text-red-600 mt-1"><strong>' . __('Action') . ':</strong> ' . htmlspecialchars($alert['actionRequired']) . '</p>';
            }
            echo '</div>';
        }
        echo '</div></div></div>';
    }

    // Staff Administered Medications Section
    $staffMedCount = $staffMedicationChildren->rowCount();
    if ($staffMedCount > 0) {
        echo '<div class="mt-6">';
        echo '<h3 class="text-lg font-semibold mb-3">' . __('Children with Staff-Administered Medications') . '</h3>';
        echo '<div class="bg-blue-50 rounded-lg p-4">';
        echo '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">';
        while ($child = $staffMedicationChildren->fetch()) {
            $name = Format::name('', $child['preferredName'], $child['surname'], 'Student', false, true);
            echo '<div class="bg-white rounded p-3 border-l-4 border-blue-500">';
            echo '<div class="font-semibold text-blue-700">' . htmlspecialchars($name) . '</div>';
            echo '<p class="text-sm text-gray-600">' . $child['medicationCount'] . ' ' . __('medication(s)') . '</p>';
            if (!empty($child['medicationList'])) {
                echo '<p class="text-xs text-gray-500 truncate mt-1" title="' . htmlspecialchars($child['medicationList']) . '">' . htmlspecialchars($child['medicationList']) . '</p>';
            }
            echo '</div>';
        }
        echo '</div></div></div>';
    }

    // Expiring Medications Warning
    $expiringSoonCount = $expiringSoonMedications->rowCount();
    if ($expiringSoonCount > 0) {
        echo '<div class="mt-6">';
        echo '<h3 class="text-lg font-semibold mb-3">' . __('Medications Expiring Soon') . '</h3>';
        echo '<div class="bg-yellow-50 rounded-lg p-4">';
        echo '<div class="space-y-2">';
        while ($med = $expiringSoonMedications->fetch()) {
            $childName = Format::name('', $med['preferredName'], $med['surname'], 'Student', false, true);
            $daysLeft = $med['daysUntilExpiry'];
            $urgencyClass = $daysLeft <= 7 ? 'text-red-600' : ($daysLeft <= 14 ? 'text-orange-600' : 'text-yellow-600');
            echo '<div class="flex justify-between items-center bg-white p-2 rounded">';
            echo '<div>';
            echo '<span class="font-medium">' . htmlspecialchars($med['medicationName']) . '</span>';
            echo '<span class="text-sm text-gray-500"> - ' . htmlspecialchars($childName) . '</span>';
            echo '</div>';
            echo '<span class="' . $urgencyClass . ' font-semibold">' . sprintf(__('%d days'), $daysLeft) . '</span>';
            echo '</div>';
        }
        echo '</div></div></div>';
    }

    // Quick Action Buttons
    echo '<div class="mt-6">';
    echo '<h3 class="text-lg font-semibold mb-3">' . __('Quick Actions') . '</h3>';
    echo '<div class="flex flex-wrap gap-2">';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/MedicalTracking/medicalTracking_allergies.php" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">' . __('Manage Allergies') . '</a>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/MedicalTracking/medicalTracking_medications.php" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">' . __('Manage Medications') . '</a>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/MedicalTracking/medicalTracking_accommodations.php" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">' . __('Accommodation Plans') . '</a>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/MedicalTracking/medicalTracking_alerts.php" class="bg-orange-500 text-white px-4 py-2 rounded hover:bg-orange-600">' . __('Medical Alerts') . '</a>';
    echo '</div>';
    echo '</div>';
}
