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
use Gibbon\Module\StaffManagement\Domain\RatioComplianceGateway;
use Gibbon\Module\StaffManagement\Domain\TimeTrackingGateway;
use Gibbon\Module\StaffManagement\Domain\AuditLogGateway;

// Module setup - breadcrumbs
$page->breadcrumbs
    ->add(__('Staff Management'), 'staffManagement.php')
    ->add(__('Ratio Monitor'));

// Access check
if (!isActionAccessible($guid, $connection2, '/modules/StaffManagement/staffManagement_ratioMonitor.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get school year ID from session
    $gibbonSchoolYearID = $session->get('gibbonSchoolYearID');
    $gibbonPersonID = $session->get('gibbonPersonID');

    // Get current date and time
    $date = $_GET['date'] ?? date('Y-m-d');
    $time = date('H:i:s');

    // Validate date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $date = date('Y-m-d');
    }

    // Get gateways via DI container
    $ratioComplianceGateway = $container->get(RatioComplianceGateway::class);
    $timeTrackingGateway = $container->get(TimeTrackingGateway::class);
    $auditLogGateway = $container->get(AuditLogGateway::class);

    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $mode = $_POST['mode'] ?? '';

        if ($mode === 'recordSnapshot') {
            // Record manual snapshot for all age groups
            $results = $ratioComplianceGateway->recordAllSnapshots(
                $gibbonSchoolYearID,
                $date,
                $time,
                $gibbonPersonID,
                false // Not automatic
            );

            // Log the action
            $auditLogGateway->logInsert(
                'gibbonStaffRatioSnapshot',
                null,
                $gibbonPersonID,
                ['action' => 'Manual snapshot recorded', 'date' => $date, 'time' => $time]
            );

            $page->addSuccess(__('Ratio snapshot recorded successfully.'));
        } elseif ($mode === 'recordSnapshotByRoom') {
            // Record snapshots by room
            $results = $ratioComplianceGateway->recordSnapshotsByRoom(
                $gibbonSchoolYearID,
                $date,
                $time,
                $gibbonPersonID,
                false // Not automatic
            );

            // Log the action
            $auditLogGateway->logInsert(
                'gibbonStaffRatioSnapshot',
                null,
                $gibbonPersonID,
                ['action' => 'Manual room snapshot recorded', 'date' => $date, 'time' => $time]
            );

            $page->addSuccess(__('Room-based ratio snapshot recorded successfully.'));
        }
    }

    // Calculate real-time ratios for all age groups
    $currentRatios = $ratioComplianceGateway->calculateAllCurrentRatios($gibbonSchoolYearID, $date, $time);

    // Calculate ratios by room
    $roomRatios = $ratioComplianceGateway->calculateRatiosByRoom($gibbonSchoolYearID, $date, $time);

    // Get daily compliance summary
    $complianceSummary = $ratioComplianceGateway->getDailyComplianceSummary($gibbonSchoolYearID, $date);

    // Get latest snapshots by age group
    $latestSnapshots = $ratioComplianceGateway->getLatestSnapshotsByAgeGroup($gibbonSchoolYearID, $date);

    // Get snapshots needing alerts
    $snapshotsNeedingAlerts = $ratioComplianceGateway->selectSnapshotsNeedingAlerts($gibbonSchoolYearID, $date)->fetchAll();

    // Get snapshots at warning level
    $snapshotsAtWarning = $ratioComplianceGateway->selectSnapshotsAtWarningLevel($gibbonSchoolYearID, $date, 90)->fetchAll();

    // Get staff needed for compliance
    $staffNeeded = $ratioComplianceGateway->getStaffNeededForCompliance($gibbonSchoolYearID, $date, $time);

    // Page header with date selector
    echo '<h2>' . __('Real-Time Ratio Compliance Monitor') . '</h2>';

    // Date navigation form
    echo '<div class="mb-4">';
    echo '<form method="get" action="' . $session->get('absoluteURL') . '/index.php" class="inline">';
    echo '<input type="hidden" name="q" value="/modules/StaffManagement/staffManagement_ratioMonitor.php">';
    echo '<label class="mr-2">' . __('Date') . ':</label>';
    echo '<input type="date" name="date" value="' . htmlspecialchars($date) . '" class="standardWidth" onchange="this.form.submit()">';
    echo '<button type="submit" class="ml-2">' . __('Go') . '</button>';
    echo '</form>';

    // Quick navigation to today
    if ($date != date('Y-m-d')) {
        echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_ratioMonitor.php&date=' . date('Y-m-d') . '" class="ml-4">' . __('Today') . '</a>';
    }
    echo '</div>';

    // Display formatted date and time
    echo '<p class="text-lg mb-4">' . __('Showing data for') . ': <strong>' . Format::date($date) . '</strong>';
    if ($date === date('Y-m-d')) {
        echo ' | ' . __('Current Time') . ': <strong>' . Format::time($time) . '</strong>';
        echo ' <span class="text-sm text-gray-500">(' . __('Auto-refreshes every 60 seconds') . ')</span>';
    }
    echo '</p>';

    // Alert banner for non-compliance
    $nonCompliantGroups = [];
    foreach ($currentRatios as $ageGroup => $ratio) {
        if ($ratio['isCompliant'] === 'N' && $ratio['childCount'] > 0) {
            $nonCompliantGroups[] = $ageGroup;
        }
    }

    if (!empty($nonCompliantGroups) && $date === date('Y-m-d')) {
        echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4">';
        echo '<div class="flex items-center">';
        echo '<span class="text-2xl mr-3">&#9888;</span>';
        echo '<div>';
        echo '<p class="font-bold">' . __('Ratio Compliance Alert') . '</p>';
        echo '<p>' . sprintf(__('%d age group(s) currently not meeting Quebec ratio requirements: %s'), count($nonCompliantGroups), implode(', ', $nonCompliantGroups)) . '</p>';
        if ($staffNeeded['totalStaffNeeded'] > 0) {
            echo '<p class="mt-1">' . sprintf(__('Additional staff needed: %d'), $staffNeeded['totalStaffNeeded']) . '</p>';
        }
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    // Warning banner for approaching capacity
    if (!empty($snapshotsAtWarning) && $date === date('Y-m-d')) {
        echo '<div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-4">';
        echo '<div class="flex items-center">';
        echo '<span class="text-2xl mr-3">&#9888;</span>';
        echo '<div>';
        echo '<p class="font-bold">' . __('Capacity Warning') . '</p>';
        echo '<p>' . sprintf(__('%d group(s) approaching maximum capacity (90%% or more)'), count($snapshotsAtWarning)) . '</p>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    // Quick Actions
    echo '<div class="mb-6 flex flex-wrap gap-2">';
    echo '<form method="post" action="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_ratioMonitor.php&date=' . $date . '" class="inline">';
    echo '<input type="hidden" name="mode" value="recordSnapshot">';
    echo '<button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">' . __('Record Snapshot Now') . '</button>';
    echo '</form>';
    echo '<form method="post" action="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_ratioMonitor.php&date=' . $date . '" class="inline">';
    echo '<input type="hidden" name="mode" value="recordSnapshotByRoom">';
    echo '<button type="submit" class="bg-purple-500 text-white px-4 py-2 rounded hover:bg-purple-600">' . __('Record Room Snapshots') . '</button>';
    echo '</form>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_ratioHistory.php&date=' . $date . '" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">' . __('View History') . '</a>';
    echo '</div>';

    // Daily Summary Card
    echo '<div class="bg-white rounded-lg shadow p-4 mb-6">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">' . __('Daily Compliance Summary') . '</h3>';
    echo '<div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">';

    // Overall compliance rate
    $complianceRate = $complianceSummary['complianceRate'] ?? 100;
    $complianceClass = $complianceRate >= 95 ? 'text-green-600' : ($complianceRate >= 80 ? 'text-orange-500' : 'text-red-600');
    echo '<div class="text-center">';
    echo '<div class="text-3xl font-bold ' . $complianceClass . '">' . round($complianceRate, 1) . '%</div>';
    echo '<div class="text-sm text-gray-500">' . __('Compliance Rate') . '</div>';
    echo '</div>';

    // Total snapshots
    echo '<div class="text-center">';
    echo '<div class="text-3xl font-bold">' . ($complianceSummary['totalSnapshots'] ?? 0) . '</div>';
    echo '<div class="text-sm text-gray-500">' . __('Total Snapshots') . '</div>';
    echo '</div>';

    // Compliant snapshots
    echo '<div class="text-center">';
    echo '<div class="text-3xl font-bold text-green-600">' . ($complianceSummary['compliantSnapshots'] ?? 0) . '</div>';
    echo '<div class="text-sm text-gray-500">' . __('Compliant') . '</div>';
    echo '</div>';

    // Non-compliant snapshots
    echo '<div class="text-center">';
    echo '<div class="text-3xl font-bold text-red-600">' . ($complianceSummary['nonCompliantSnapshots'] ?? 0) . '</div>';
    echo '<div class="text-sm text-gray-500">' . __('Non-Compliant') . '</div>';
    echo '</div>';

    // Average staff count
    echo '<div class="text-center">';
    echo '<div class="text-3xl font-bold">' . round($complianceSummary['avgStaffCount'] ?? 0, 1) . '</div>';
    echo '<div class="text-sm text-gray-500">' . __('Avg Staff') . '</div>';
    echo '</div>';

    // Average child count
    echo '<div class="text-center">';
    echo '<div class="text-3xl font-bold">' . round($complianceSummary['avgChildCount'] ?? 0, 1) . '</div>';
    echo '<div class="text-sm text-gray-500">' . __('Avg Children') . '</div>';
    echo '</div>';

    echo '</div>'; // End grid
    echo '</div>'; // End summary card

    // Age Group Ratio Cards
    echo '<h3 class="text-lg font-semibold mb-3">' . __('Current Ratios by Age Group') . '</h3>';
    echo '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">';

    $ageGroupInfo = [
        'Infant' => ['label' => __('Infant'), 'ageRange' => __('0-18 months'), 'required' => '1:5', 'color' => 'blue'],
        'Toddler' => ['label' => __('Toddler'), 'ageRange' => __('18-36 months'), 'required' => '1:8', 'color' => 'green'],
        'Preschool' => ['label' => __('Preschool'), 'ageRange' => __('36-60 months'), 'required' => '1:10', 'color' => 'purple'],
        'School Age' => ['label' => __('School Age'), 'ageRange' => __('60+ months'), 'required' => '1:20', 'color' => 'indigo'],
    ];

    foreach ($currentRatios as $ageGroup => $ratio) {
        $info = $ageGroupInfo[$ageGroup] ?? ['label' => $ageGroup, 'ageRange' => '', 'required' => '1:10', 'color' => 'gray'];

        // Determine card border color based on compliance
        if ($ratio['childCount'] === 0) {
            $borderColor = 'border-gray-300';
            $statusBg = 'bg-gray-100';
            $statusText = __('No Children');
            $statusIcon = '&#8212;'; // em dash
        } elseif ($ratio['isCompliant'] === 'Y') {
            if ($ratio['compliancePercent'] >= 90) {
                $borderColor = 'border-yellow-400';
                $statusBg = 'bg-yellow-100';
                $statusText = __('Near Capacity');
                $statusIcon = '&#9888;';
            } else {
                $borderColor = 'border-green-500';
                $statusBg = 'bg-green-100';
                $statusText = __('Compliant');
                $statusIcon = '&#10003;';
            }
        } else {
            $borderColor = 'border-red-500';
            $statusBg = 'bg-red-100';
            $statusText = __('Non-Compliant');
            $statusIcon = '&#10007;';
        }

        echo '<div class="bg-white rounded-lg shadow border-l-4 ' . $borderColor . ' p-4">';

        // Header with age group name and status
        echo '<div class="flex justify-between items-start mb-3">';
        echo '<div>';
        echo '<h4 class="font-semibold text-lg">' . $info['label'] . '</h4>';
        echo '<span class="text-sm text-gray-500">' . $info['ageRange'] . '</span>';
        echo '</div>';
        echo '<span class="' . $statusBg . ' px-2 py-1 rounded text-sm font-semibold">' . $statusIcon . ' ' . $statusText . '</span>';
        echo '</div>';

        // Ratio display
        echo '<div class="mb-3">';
        if ($ratio['staffCount'] > 0) {
            $actualRatioDisplay = '1:' . round($ratio['actualRatio'], 1);
        } else {
            $actualRatioDisplay = __('No Staff');
        }
        echo '<div class="text-2xl font-bold mb-1">' . $actualRatioDisplay . '</div>';
        echo '<div class="text-sm text-gray-500">' . __('Required') . ': ' . $info['required'] . '</div>';
        echo '</div>';

        // Staff and children counts
        echo '<div class="grid grid-cols-2 gap-2 mb-3">';
        echo '<div class="text-center bg-gray-50 rounded p-2">';
        echo '<div class="text-xl font-bold text-blue-600">' . $ratio['staffCount'] . '</div>';
        echo '<div class="text-xs text-gray-500">' . __('Staff') . '</div>';
        echo '</div>';
        echo '<div class="text-center bg-gray-50 rounded p-2">';
        echo '<div class="text-xl font-bold text-green-600">' . $ratio['childCount'] . '</div>';
        echo '<div class="text-xs text-gray-500">' . __('Children') . '</div>';
        echo '</div>';
        echo '</div>';

        // Capacity bar
        if ($ratio['childCount'] > 0 && $ratio['staffCount'] > 0) {
            $capacityPercent = min(100, $ratio['compliancePercent']);
            $capacityColor = $capacityPercent >= 100 ? 'bg-red-500' : ($capacityPercent >= 90 ? 'bg-yellow-500' : 'bg-green-500');
            echo '<div class="mb-2">';
            echo '<div class="flex justify-between text-xs mb-1">';
            echo '<span>' . __('Capacity') . '</span>';
            echo '<span>' . round($capacityPercent, 1) . '%</span>';
            echo '</div>';
            echo '<div class="w-full bg-gray-200 rounded-full h-2">';
            echo '<div class="' . $capacityColor . ' h-2 rounded-full" style="width: ' . $capacityPercent . '%"></div>';
            echo '</div>';
            echo '</div>';
        }

        // Additional capacity or staff needed
        if ($ratio['childCount'] > 0) {
            if ($ratio['isCompliant'] === 'Y' && $ratio['additionalCapacity'] > 0) {
                echo '<div class="text-sm text-green-600">';
                echo '<span class="font-semibold">+' . $ratio['additionalCapacity'] . '</span> ' . __('more children can be added');
                echo '</div>';
            } elseif ($ratio['staffNeeded'] > 0) {
                echo '<div class="text-sm text-red-600">';
                echo '<span class="font-semibold">+' . $ratio['staffNeeded'] . '</span> ' . __('staff needed');
                echo '</div>';
            }
        }

        echo '</div>'; // End card
    }

    echo '</div>'; // End grid

    // Room-Based Ratios (if any)
    if (!empty($roomRatios)) {
        echo '<h3 class="text-lg font-semibold mb-3">' . __('Current Ratios by Room') . '</h3>';
        echo '<div class="bg-white rounded-lg shadow overflow-hidden mb-6">';
        echo '<table class="min-w-full divide-y divide-gray-200">';
        echo '<thead class="bg-gray-50">';
        echo '<tr>';
        echo '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">' . __('Room') . '</th>';
        echo '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">' . __('Age Group') . '</th>';
        echo '<th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">' . __('Staff') . '</th>';
        echo '<th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">' . __('Children') . '</th>';
        echo '<th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">' . __('Ratio') . '</th>';
        echo '<th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">' . __('Status') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody class="bg-white divide-y divide-gray-200">';

        foreach ($roomRatios as $roomName => $ratio) {
            $rowClass = $ratio['isCompliant'] === 'Y' ? '' : 'bg-red-50';
            $statusClass = $ratio['isCompliant'] === 'Y' ? 'text-green-600' : 'text-red-600';
            $statusIcon = $ratio['isCompliant'] === 'Y' ? '&#10003;' : '&#10007;';
            $ratioDisplay = $ratio['staffCount'] > 0 ? '1:' . round($ratio['actualRatio'], 1) : __('No Staff');

            echo '<tr class="' . $rowClass . '">';
            echo '<td class="px-6 py-4 whitespace-nowrap font-medium">' . htmlspecialchars($roomName) . '</td>';
            echo '<td class="px-6 py-4 whitespace-nowrap">' . htmlspecialchars($ratio['ageGroup']) . '</td>';
            echo '<td class="px-6 py-4 whitespace-nowrap text-center">' . $ratio['staffCount'] . '</td>';
            echo '<td class="px-6 py-4 whitespace-nowrap text-center">' . $ratio['childCount'] . '</td>';
            echo '<td class="px-6 py-4 whitespace-nowrap text-center font-semibold">' . $ratioDisplay . '</td>';
            echo '<td class="px-6 py-4 whitespace-nowrap text-center ' . $statusClass . ' font-semibold">' . $statusIcon . '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    }

    // Latest Snapshots Table
    if (!empty($latestSnapshots)) {
        echo '<h3 class="text-lg font-semibold mb-3">' . __('Latest Recorded Snapshots') . '</h3>';
        echo '<div class="bg-white rounded-lg shadow overflow-hidden mb-6">';
        echo '<table class="min-w-full divide-y divide-gray-200">';
        echo '<thead class="bg-gray-50">';
        echo '<tr>';
        echo '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">' . __('Time') . '</th>';
        echo '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">' . __('Age Group') . '</th>';
        echo '<th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">' . __('Staff') . '</th>';
        echo '<th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">' . __('Children') . '</th>';
        echo '<th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">' . __('Ratio') . '</th>';
        echo '<th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">' . __('Capacity') . '</th>';
        echo '<th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">' . __('Status') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody class="bg-white divide-y divide-gray-200">';

        foreach ($latestSnapshots as $snapshot) {
            $rowClass = $snapshot['isCompliant'] === 'Y' ? '' : 'bg-red-50';
            $statusClass = $snapshot['isCompliant'] === 'Y' ? 'text-green-600' : 'text-red-600';
            $statusIcon = $snapshot['isCompliant'] === 'Y' ? '&#10003; ' . __('Compliant') : '&#10007; ' . __('Non-Compliant');
            $ratioDisplay = $snapshot['staffCount'] > 0 ? '1:' . round($snapshot['actualRatio'], 1) : __('No Staff');

            echo '<tr class="' . $rowClass . '">';
            echo '<td class="px-6 py-4 whitespace-nowrap">' . Format::time($snapshot['snapshotTime']) . '</td>';
            echo '<td class="px-6 py-4 whitespace-nowrap">' . htmlspecialchars($snapshot['ageGroup']) . '</td>';
            echo '<td class="px-6 py-4 whitespace-nowrap text-center">' . $snapshot['staffCount'] . '</td>';
            echo '<td class="px-6 py-4 whitespace-nowrap text-center">' . $snapshot['childCount'] . '</td>';
            echo '<td class="px-6 py-4 whitespace-nowrap text-center font-semibold">' . $ratioDisplay . '</td>';
            echo '<td class="px-6 py-4 whitespace-nowrap text-center">' . round($snapshot['compliancePercent'], 1) . '%</td>';
            echo '<td class="px-6 py-4 whitespace-nowrap text-center ' . $statusClass . ' font-semibold">' . $statusIcon . '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    }

    // Staff Currently Working Section
    $staffClockedIn = $timeTrackingGateway->selectStaffCurrentlyClockedIn($gibbonSchoolYearID, $date)->fetchAll();
    $staffOnBreak = $timeTrackingGateway->selectStaffOnBreak($gibbonSchoolYearID, $date)->fetchAll();

    echo '<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">';

    // Staff Currently Working
    echo '<div class="bg-white rounded-lg shadow p-4">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">' . __('Staff Currently Working') . ' (' . count($staffClockedIn) . ')</h3>';
    if (!empty($staffClockedIn)) {
        echo '<div class="space-y-2 max-h-64 overflow-y-auto">';
        foreach ($staffClockedIn as $staff) {
            $name = Format::name('', $staff['preferredName'] ?? '', $staff['surname'] ?? '', 'Staff', false, true);
            $clockedInTime = Format::time($staff['clockInTime'] ?? '');
            echo '<div class="flex justify-between items-center p-2 bg-gray-50 rounded">';
            echo '<span class="font-medium">' . $name . '</span>';
            echo '<span class="text-sm text-gray-500">' . __('In') . ': ' . $clockedInTime . '</span>';
            echo '</div>';
        }
        echo '</div>';
    } else {
        echo '<p class="text-gray-500">' . __('No staff currently clocked in.') . '</p>';
    }
    echo '</div>';

    // Staff On Break
    echo '<div class="bg-white rounded-lg shadow p-4">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">' . __('Staff On Break') . ' (' . count($staffOnBreak) . ')</h3>';
    if (!empty($staffOnBreak)) {
        echo '<div class="space-y-2 max-h-64 overflow-y-auto">';
        foreach ($staffOnBreak as $staff) {
            $name = Format::name('', $staff['preferredName'] ?? '', $staff['surname'] ?? '', 'Staff', false, true);
            $breakStartTime = Format::time($staff['breakStart'] ?? '');
            echo '<div class="flex justify-between items-center p-2 bg-purple-50 rounded">';
            echo '<span class="font-medium">' . $name . '</span>';
            echo '<span class="text-sm text-purple-600">' . __('Break since') . ': ' . $breakStartTime . '</span>';
            echo '</div>';
        }
        echo '</div>';
    } else {
        echo '<p class="text-gray-500">' . __('No staff currently on break.') . '</p>';
    }
    echo '</div>';

    echo '</div>'; // End grid

    // Quebec Compliance Information
    echo '<div class="bg-blue-50 rounded-lg p-4 mb-6">';
    echo '<h3 class="text-lg font-semibold mb-2">' . __('Quebec Staff-to-Child Ratio Requirements') . '</h3>';
    echo '<div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">';
    echo '<div class="bg-white rounded p-3 text-center">';
    echo '<div class="font-semibold text-blue-600">' . __('Infant') . '</div>';
    echo '<div class="text-gray-500">' . __('(0-18 months)') . '</div>';
    echo '<div class="text-2xl font-bold mt-1">1:5</div>';
    echo '</div>';
    echo '<div class="bg-white rounded p-3 text-center">';
    echo '<div class="font-semibold text-green-600">' . __('Toddler') . '</div>';
    echo '<div class="text-gray-500">' . __('(18-36 months)') . '</div>';
    echo '<div class="text-2xl font-bold mt-1">1:8</div>';
    echo '</div>';
    echo '<div class="bg-white rounded p-3 text-center">';
    echo '<div class="font-semibold text-purple-600">' . __('Preschool') . '</div>';
    echo '<div class="text-gray-500">' . __('(36-60 months)') . '</div>';
    echo '<div class="text-2xl font-bold mt-1">1:10</div>';
    echo '</div>';
    echo '<div class="bg-white rounded p-3 text-center">';
    echo '<div class="font-semibold text-indigo-600">' . __('School Age') . '</div>';
    echo '<div class="text-gray-500">' . __('(60+ months)') . '</div>';
    echo '<div class="text-2xl font-bold mt-1">1:20</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    // Auto-refresh script for today's date
    if ($date === date('Y-m-d')) {
        echo '<script>
            // Auto-refresh every 60 seconds
            setTimeout(function() {
                location.reload();
            }, 60000);

            // Update current time display
            function updateTime() {
                var now = new Date();
                var timeStr = now.toLocaleTimeString();
                var timeDisplay = document.getElementById("currentTimeDisplay");
                if (timeDisplay) {
                    timeDisplay.textContent = timeStr;
                }
            }
            setInterval(updateTime, 1000);
        </script>';
    }
}
