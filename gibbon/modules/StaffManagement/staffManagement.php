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
use Gibbon\Module\StaffManagement\Domain\StaffProfileGateway;
use Gibbon\Module\StaffManagement\Domain\CertificationGateway;
use Gibbon\Module\StaffManagement\Domain\RatioComplianceGateway;
use Gibbon\Module\StaffManagement\Domain\TimeTrackingGateway;

// Module setup - breadcrumbs
$page->breadcrumbs->add(__('Staff Management'));

// Access check
if (!isActionAccessible($guid, $connection2, '/modules/StaffManagement/staffManagement.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get school year ID from session
    $gibbonSchoolYearID = $session->get('gibbonSchoolYearID');

    // Get current date and time
    $date = date('Y-m-d');
    $time = date('H:i:s');

    // Get gateways via DI container
    $staffProfileGateway = $container->get(StaffProfileGateway::class);
    $certificationGateway = $container->get(CertificationGateway::class);
    $ratioComplianceGateway = $container->get(RatioComplianceGateway::class);
    $timeTrackingGateway = $container->get(TimeTrackingGateway::class);

    // Get summary statistics
    $staffSummary = $staffProfileGateway->getStaffSummaryStatistics();
    $certificationSummary = $certificationGateway->getCertificationSummaryStatistics();
    $complianceSummary = $ratioComplianceGateway->getDailyComplianceSummary($gibbonSchoolYearID, $date);

    // Calculate real-time ratios for all age groups
    $currentRatios = $ratioComplianceGateway->calculateAllCurrentRatios($gibbonSchoolYearID, $date, $time);

    // Page header
    echo '<h2>' . __('Staff Management Dashboard') . '</h2>';

    // Display current date
    echo '<p class="text-lg mb-4">' . __('Today') . ': <strong>' . Format::date($date) . '</strong></p>';

    // Alert Section - Show critical items that need attention
    $criticalAlerts = [];

    // Check for expired certifications
    $expiredRequired = intval($certificationSummary['totalRequiredExpired'] ?? 0);
    if ($expiredRequired > 0) {
        $criticalAlerts[] = [
            'type' => 'error',
            'message' => sprintf(__('%d required certification(s) have expired'), $expiredRequired),
            'link' => $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_certifications.php&filter=expired',
        ];
    }

    // Check for certifications expiring soon
    $expiringSoon = intval($certificationSummary['expiringSoon'] ?? 0);
    if ($expiringSoon > 0) {
        $criticalAlerts[] = [
            'type' => 'warning',
            'message' => sprintf(__('%d certification(s) expiring within 30 days'), $expiringSoon),
            'link' => $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_certifications.php&filter=expiring',
        ];
    }

    // Check for non-compliant ratios
    $nonCompliantCount = 0;
    foreach ($currentRatios as $ratio) {
        if ($ratio['isCompliant'] === 'N' && $ratio['childCount'] > 0) {
            $nonCompliantCount++;
        }
    }
    if ($nonCompliantCount > 0) {
        $criticalAlerts[] = [
            'type' => 'error',
            'message' => sprintf(__('%d age group(s) currently not meeting Quebec ratio requirements'), $nonCompliantCount),
            'link' => $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_ratioMonitor.php',
        ];
    }

    // Display alerts if any
    if (!empty($criticalAlerts)) {
        echo '<div class="mb-6">';
        foreach ($criticalAlerts as $alert) {
            $bgColor = $alert['type'] === 'error' ? 'bg-red-100 border-red-500 text-red-700' : 'bg-yellow-100 border-yellow-500 text-yellow-700';
            echo '<div class="' . $bgColor . ' border-l-4 p-4 mb-2">';
            echo '<a href="' . $alert['link'] . '" class="font-semibold hover:underline">' . $alert['message'] . ' &rarr;</a>';
            echo '</div>';
        }
        echo '</div>';
    }

    // Dashboard grid layout
    echo '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">';

    // Staff Overview Card
    echo '<div class="bg-white rounded-lg shadow p-4">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">' . __('Staff Overview') . '</h3>';
    echo '<div class="space-y-2">';
    echo '<div class="flex justify-between"><span>' . __('Active Staff') . ':</span><span class="font-bold text-green-600">' . ($staffSummary['totalActive'] ?? 0) . '</span></div>';
    echo '<div class="flex justify-between"><span>' . __('Full-Time') . ':</span><span>' . ($staffSummary['totalFullTime'] ?? 0) . '</span></div>';
    echo '<div class="flex justify-between"><span>' . __('Part-Time') . ':</span><span>' . ($staffSummary['totalPartTime'] ?? 0) . '</span></div>';
    echo '<div class="flex justify-between"><span>' . __('Casual') . ':</span><span>' . ($staffSummary['totalCasual'] ?? 0) . '</span></div>';
    echo '<div class="flex justify-between"><span>' . __('On Leave') . ':</span><span class="text-orange-500">' . ($staffSummary['totalOnLeave'] ?? 0) . '</span></div>';
    echo '</div>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_profile.php" class="block mt-3 text-blue-600 hover:underline">' . __('Manage Staff Profiles') . ' &rarr;</a>';
    echo '</div>';

    // Qualifications Card
    echo '<div class="bg-white rounded-lg shadow p-4">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">' . __('Qualifications') . '</h3>';
    echo '<div class="space-y-2">';
    echo '<div class="flex justify-between"><span>' . __('Directors') . ':</span><span class="font-bold">' . ($staffSummary['totalDirectors'] ?? 0) . '</span></div>';
    echo '<div class="flex justify-between"><span>' . __('Qualified Staff') . ':</span><span class="text-green-600">' . ($staffSummary['totalQualified'] ?? 0) . '</span></div>';
    echo '<div class="flex justify-between"><span>' . __('Unqualified') . ':</span><span class="text-orange-500">' . ($staffSummary['totalUnqualified'] ?? 0) . '</span></div>';
    $totalActive = intval($staffSummary['totalActive'] ?? 0);
    $totalQualified = intval($staffSummary['totalQualified'] ?? 0) + intval($staffSummary['totalDirectors'] ?? 0);
    $qualificationRate = $totalActive > 0 ? round(($totalQualified / $totalActive) * 100, 1) : 0;
    echo '<div class="flex justify-between"><span>' . __('Qualification Rate') . ':</span><span>' . $qualificationRate . '%</span></div>';
    echo '</div>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_profile.php" class="block mt-3 text-blue-600 hover:underline">' . __('View Staff Details') . ' &rarr;</a>';
    echo '</div>';

    // Certifications Card
    echo '<div class="bg-white rounded-lg shadow p-4">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">' . __('Certifications') . '</h3>';
    echo '<div class="space-y-2">';
    echo '<div class="flex justify-between"><span>' . __('Valid') . ':</span><span class="font-bold text-green-600">' . ($certificationSummary['totalValid'] ?? 0) . '</span></div>';
    echo '<div class="flex justify-between"><span>' . __('Pending') . ':</span><span class="text-blue-500">' . ($certificationSummary['totalPending'] ?? 0) . '</span></div>';
    echo '<div class="flex justify-between"><span>' . __('Expiring Soon') . ':</span><span class="text-orange-500">' . ($certificationSummary['expiringSoon'] ?? 0) . '</span></div>';
    echo '<div class="flex justify-between"><span>' . __('Expired') . ':</span><span class="text-red-500">' . ($certificationSummary['totalExpired'] ?? 0) . '</span></div>';
    $requiredValid = intval($certificationSummary['totalRequiredValid'] ?? 0);
    $requiredExpired = intval($certificationSummary['totalRequiredExpired'] ?? 0);
    if ($requiredExpired > 0) {
        echo '<div class="flex justify-between"><span>' . __('Required Expired') . ':</span><span class="text-red-600 font-bold">' . $requiredExpired . '</span></div>';
    }
    echo '</div>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_certifications.php" class="block mt-3 text-blue-600 hover:underline">' . __('Manage Certifications') . ' &rarr;</a>';
    echo '</div>';

    // Ratio Compliance Card
    echo '<div class="bg-white rounded-lg shadow p-4">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">' . __('Ratio Compliance') . '</h3>';
    echo '<div class="space-y-2">';

    // Display current ratios for each age group
    $ageGroupLabels = [
        'Infant' => __('Infant (0-18mo)'),
        'Toddler' => __('Toddler (18-36mo)'),
        'Preschool' => __('Preschool (36-60mo)'),
        'School Age' => __('School Age (60+mo)'),
    ];

    $hasChildren = false;
    foreach ($currentRatios as $ageGroup => $ratio) {
        if ($ratio['childCount'] > 0) {
            $hasChildren = true;
            $statusClass = $ratio['isCompliant'] === 'Y' ? 'text-green-600' : 'text-red-600';
            $statusIcon = $ratio['isCompliant'] === 'Y' ? '&#10003;' : '&#10007;';
            $ratioDisplay = $ratio['staffCount'] > 0 ? '1:' . round($ratio['actualRatio'], 1) : __('No Staff');
            $label = $ageGroupLabels[$ageGroup] ?? $ageGroup;
            echo '<div class="flex justify-between"><span>' . $label . ':</span><span class="' . $statusClass . '">' . $statusIcon . ' ' . $ratioDisplay . ' (' . $ratio['staffCount'] . '/' . $ratio['childCount'] . ')</span></div>';
        }
    }

    if (!$hasChildren) {
        echo '<p class="text-gray-500">' . __('No children currently checked in.') . '</p>';
    }

    // Daily compliance summary
    $complianceRate = $complianceSummary['complianceRate'] ?? 100;
    $complianceClass = $complianceRate >= 95 ? 'text-green-600' : ($complianceRate >= 80 ? 'text-orange-500' : 'text-red-600');
    echo '<div class="flex justify-between mt-2 pt-2 border-t"><span>' . __('Daily Compliance Rate') . ':</span><span class="font-bold ' . $complianceClass . '">' . $complianceRate . '%</span></div>';
    echo '</div>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_ratioMonitor.php" class="block mt-3 text-blue-600 hover:underline">' . __('View Ratio Monitor') . ' &rarr;</a>';
    echo '</div>';

    // Time Tracking Card
    echo '<div class="bg-white rounded-lg shadow p-4">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">' . __('Time Tracking') . '</h3>';
    echo '<div class="space-y-2">';

    // Get today's time tracking summary
    $todaySummary = $timeTrackingGateway->getDailySummary($gibbonSchoolYearID, $date);
    echo '<div class="flex justify-between"><span>' . __('Clocked In') . ':</span><span class="font-bold text-green-600">' . ($todaySummary['currentlyClockedIn'] ?? 0) . '</span></div>';
    echo '<div class="flex justify-between"><span>' . __('On Break') . ':</span><span class="text-purple-600">' . ($todaySummary['currentlyOnBreak'] ?? 0) . '</span></div>';
    echo '<div class="flex justify-between"><span>' . __('Clocked Out Today') . ':</span><span>' . ($todaySummary['totalClockedOut'] ?? 0) . '</span></div>';
    echo '<div class="flex justify-between"><span>' . __('Late Arrivals') . ':</span><span class="text-orange-500">' . ($todaySummary['totalLateArrivals'] ?? 0) . '</span></div>';

    // Calculate total hours worked today
    $totalMinutes = intval($todaySummary['totalWorkedMinutes'] ?? 0);
    if ($totalMinutes > 0) {
        $hours = floor($totalMinutes / 60);
        $mins = $totalMinutes % 60;
        $hoursStr = $hours > 0 ? $hours . 'h ' . $mins . 'm' : $mins . 'm';
        echo '<div class="flex justify-between"><span>' . __('Total Hours Today') . ':</span><span>' . $hoursStr . '</span></div>';
    }

    // Pending overtime approvals
    $pendingOvertime = intval($todaySummary['pendingOvertimeApprovals'] ?? 0);
    if ($pendingOvertime > 0) {
        echo '<div class="flex justify-between"><span>' . __('Pending Overtime') . ':</span><span class="text-orange-500 font-bold">' . $pendingOvertime . '</span></div>';
    }
    echo '</div>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_timeTracking.php" class="block mt-3 text-blue-600 hover:underline">' . __('Manage Time Tracking') . ' &rarr;</a>';
    echo '</div>';

    // Scheduling Card
    echo '<div class="bg-white rounded-lg shadow p-4">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">' . __('Scheduling') . '</h3>';
    echo '<div class="space-y-2">';
    echo '<p class="text-gray-600">' . __('Manage staff work schedules, shift templates, and availability tracking.') . '</p>';
    echo '<div class="flex justify-between mt-2"><span>' . __('Active Staff') . ':</span><span>' . ($staffSummary['totalActive'] ?? 0) . '</span></div>';
    echo '</div>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_schedule.php" class="block mt-3 text-blue-600 hover:underline">' . __('Manage Schedules') . ' &rarr;</a>';
    echo '</div>';

    echo '</div>'; // End grid

    // Quick Action Buttons
    echo '<div class="mt-6">';
    echo '<h3 class="text-lg font-semibold mb-3">' . __('Quick Actions') . '</h3>';
    echo '<div class="flex flex-wrap gap-2">';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_addEdit.php" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">' . __('Add New Staff') . '</a>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_timeTracking.php" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">' . __('Clock In/Out') . '</a>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_schedule.php" class="bg-purple-500 text-white px-4 py-2 rounded hover:bg-purple-600">' . __('View Schedule') . '</a>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_certifications.php" class="bg-yellow-500 text-white px-4 py-2 rounded hover:bg-yellow-600">' . __('Add Certification') . '</a>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_ratioMonitor.php" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">' . __('Ratio Monitor') . '</a>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_hoursReport.php" class="bg-indigo-500 text-white px-4 py-2 rounded hover:bg-indigo-600">' . __('Hours Report') . '</a>';
    echo '</div>';
    echo '</div>';

    // Quebec Compliance Information
    echo '<div class="mt-6 bg-blue-50 rounded-lg p-4">';
    echo '<h3 class="text-lg font-semibold mb-2">' . __('Quebec Ratio Requirements') . '</h3>';
    echo '<div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">';
    echo '<div><span class="font-semibold">' . __('Infant') . '</span><br>' . __('(0-18 months)') . '<br>1:5</div>';
    echo '<div><span class="font-semibold">' . __('Toddler') . '</span><br>' . __('(18-36 months)') . '<br>1:8</div>';
    echo '<div><span class="font-semibold">' . __('Preschool') . '</span><br>' . __('(36-60 months)') . '<br>1:10</div>';
    echo '<div><span class="font-semibold">' . __('School Age') . '</span><br>' . __('(60+ months)') . '<br>1:20</div>';
    echo '</div>';
    echo '</div>';
}
