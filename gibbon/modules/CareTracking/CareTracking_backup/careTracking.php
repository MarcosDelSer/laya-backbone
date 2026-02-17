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
use Gibbon\Module\CareTracking\Domain\AttendanceGateway;
use Gibbon\Module\CareTracking\Domain\MealGateway;
use Gibbon\Module\CareTracking\Domain\NapGateway;
use Gibbon\Module\CareTracking\Domain\DiaperGateway;
use Gibbon\Module\CareTracking\Domain\IncidentGateway;
use Gibbon\Module\CareTracking\Domain\ActivityGateway;

// Module setup - breadcrumbs
$page->breadcrumbs->add(__('Care Tracking'));

// Access check
if (!isActionAccessible($guid, $connection2, '/modules/CareTracking/careTracking.php')) {
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
    $attendanceGateway = $container->get(AttendanceGateway::class);
    $mealGateway = $container->get(MealGateway::class);
    $napGateway = $container->get(NapGateway::class);
    $diaperGateway = $container->get(DiaperGateway::class);
    $incidentGateway = $container->get(IncidentGateway::class);
    $activityGateway = $container->get(ActivityGateway::class);

    // Get summary statistics for the date
    $attendanceSummary = $attendanceGateway->getAttendanceSummaryByDate($gibbonSchoolYearID, $date);
    $mealSummary = $mealGateway->getMealSummaryByDate($gibbonSchoolYearID, $date);
    $napSummary = $napGateway->getNapSummaryByDate($gibbonSchoolYearID, $date);
    $diaperSummary = $diaperGateway->getDiaperSummaryByDate($gibbonSchoolYearID, $date);
    $incidentSummary = $incidentGateway->getIncidentSummaryByDate($gibbonSchoolYearID, $date);
    $activitySummary = $activityGateway->getActivitySummaryByDate($gibbonSchoolYearID, $date);

    // Page header with date selector
    echo '<h2>' . __('Daily Care Summary') . '</h2>';

    // Date navigation form
    echo '<div class="mb-4">';
    echo '<form method="get" action="' . $session->get('absoluteURL') . '/index.php" class="inline">';
    echo '<input type="hidden" name="q" value="/modules/CareTracking/careTracking.php">';
    echo '<label class="mr-2">' . __('Date') . ':</label>';
    echo '<input type="date" name="date" value="' . htmlspecialchars($date) . '" class="standardWidth" onchange="this.form.submit()">';
    echo '<button type="submit" class="ml-2">' . __('Go') . '</button>';
    echo '</form>';

    // Quick navigation to today
    if ($date != date('Y-m-d')) {
        echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/CareTracking/careTracking.php&date=' . date('Y-m-d') . '" class="ml-4">' . __('Today') . '</a>';
    }
    echo '</div>';

    // Display formatted date
    echo '<p class="text-lg mb-4">' . __('Showing data for') . ': <strong>' . Format::date($date) . '</strong></p>';

    // Dashboard grid layout
    echo '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">';

    // Attendance Card
    echo '<div class="bg-white rounded-lg shadow p-4">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">' . __('Attendance') . '</h3>';
    echo '<div class="space-y-2">';
    echo '<div class="flex justify-between"><span>' . __('Currently Present') . ':</span><span class="font-bold text-green-600">' . ($attendanceSummary['currentlyPresent'] ?? 0) . '</span></div>';
    echo '<div class="flex justify-between"><span>' . __('Checked In Today') . ':</span><span>' . ($attendanceSummary['totalCheckedIn'] ?? 0) . '</span></div>';
    echo '<div class="flex justify-between"><span>' . __('Checked Out') . ':</span><span>' . ($attendanceSummary['totalCheckedOut'] ?? 0) . '</span></div>';
    echo '<div class="flex justify-between"><span>' . __('Late Arrivals') . ':</span><span class="text-orange-500">' . ($attendanceSummary['totalLateArrivals'] ?? 0) . '</span></div>';
    echo '<div class="flex justify-between"><span>' . __('Absent') . ':</span><span class="text-red-500">' . ($attendanceSummary['totalAbsent'] ?? 0) . '</span></div>';
    echo '</div>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/CareTracking/careTracking_attendance.php&date=' . $date . '" class="block mt-3 text-blue-600 hover:underline">' . __('Manage Attendance') . ' &rarr;</a>';
    echo '</div>';

    // Meals Card
    echo '<div class="bg-white rounded-lg shadow p-4">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">' . __('Meals') . '</h3>';
    echo '<div class="space-y-2">';
    if (!empty($mealSummary) && is_array($mealSummary)) {
        foreach ($mealSummary as $meal) {
            $mealType = $meal['mealType'] ?? __('Unknown');
            $total = $meal['totalRecords'] ?? 0;
            echo '<div class="flex justify-between"><span>' . __($mealType) . ':</span><span>' . $total . ' ' . __('logged') . '</span></div>';
        }
    } else {
        echo '<p class="text-gray-500">' . __('No meals logged yet.') . '</p>';
    }
    echo '</div>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/CareTracking/careTracking_meals.php&date=' . $date . '" class="block mt-3 text-blue-600 hover:underline">' . __('Log Meals') . ' &rarr;</a>';
    echo '</div>';

    // Naps Card
    echo '<div class="bg-white rounded-lg shadow p-4">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">' . __('Naps') . '</h3>';
    echo '<div class="space-y-2">';
    echo '<div class="flex justify-between"><span>' . __('Currently Sleeping') . ':</span><span class="font-bold text-purple-600">' . ($napSummary['currentlySleeping'] ?? 0) . '</span></div>';
    echo '<div class="flex justify-between"><span>' . __('Total Naps') . ':</span><span>' . ($napSummary['totalNaps'] ?? 0) . '</span></div>';
    echo '<div class="flex justify-between"><span>' . __('Children Napped') . ':</span><span>' . ($napSummary['childrenNapped'] ?? 0) . '</span></div>';
    $avgDuration = $napSummary['avgDurationMinutes'] ?? 0;
    if ($avgDuration > 0) {
        echo '<div class="flex justify-between"><span>' . __('Avg Duration') . ':</span><span>' . round($avgDuration) . ' ' . __('min') . '</span></div>';
    }
    echo '</div>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/CareTracking/careTracking_naps.php&date=' . $date . '" class="block mt-3 text-blue-600 hover:underline">' . __('Track Naps') . ' &rarr;</a>';
    echo '</div>';

    // Diapers Card
    echo '<div class="bg-white rounded-lg shadow p-4">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">' . __('Diapers') . '</h3>';
    echo '<div class="space-y-2">';
    echo '<div class="flex justify-between"><span>' . __('Total Changes') . ':</span><span class="font-bold">' . ($diaperSummary['totalChanges'] ?? 0) . '</span></div>';
    echo '<div class="flex justify-between"><span>' . __('Children Changed') . ':</span><span>' . ($diaperSummary['childrenChanged'] ?? 0) . '</span></div>';
    echo '<div class="flex justify-between"><span>' . __('Wet') . ':</span><span>' . ($diaperSummary['wetCount'] ?? 0) . '</span></div>';
    echo '<div class="flex justify-between"><span>' . __('Soiled') . ':</span><span>' . ($diaperSummary['soiledCount'] ?? 0) . '</span></div>';
    echo '<div class="flex justify-between"><span>' . __('Both') . ':</span><span>' . ($diaperSummary['bothCount'] ?? 0) . '</span></div>';
    echo '</div>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/CareTracking/careTracking_diapers.php&date=' . $date . '" class="block mt-3 text-blue-600 hover:underline">' . __('Log Diapers') . ' &rarr;</a>';
    echo '</div>';

    // Incidents Card
    echo '<div class="bg-white rounded-lg shadow p-4">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">' . __('Incidents') . '</h3>';
    echo '<div class="space-y-2">';
    $totalIncidents = $incidentSummary['totalIncidents'] ?? 0;
    echo '<div class="flex justify-between"><span>' . __('Total Incidents') . ':</span><span class="font-bold">' . $totalIncidents . '</span></div>';
    if ($totalIncidents > 0) {
        $criticalCount = $incidentSummary['criticalSeverity'] ?? 0;
        $highCount = $incidentSummary['highSeverity'] ?? 0;
        if ($criticalCount > 0) {
            echo '<div class="flex justify-between"><span>' . __('Critical') . ':</span><span class="text-red-600 font-bold">' . $criticalCount . '</span></div>';
        }
        if ($highCount > 0) {
            echo '<div class="flex justify-between"><span>' . __('High Severity') . ':</span><span class="text-orange-500">' . $highCount . '</span></div>';
        }
        $minorInjuries = $incidentSummary['minorInjuries'] ?? 0;
        $majorInjuries = $incidentSummary['majorInjuries'] ?? 0;
        echo '<div class="flex justify-between"><span>' . __('Minor Injuries') . ':</span><span>' . $minorInjuries . '</span></div>';
        echo '<div class="flex justify-between"><span>' . __('Major Injuries') . ':</span><span class="text-red-500">' . $majorInjuries . '</span></div>';

        // Parent notification status
        $notified = $incidentSummary['parentsNotified'] ?? 0;
        $acknowledged = $incidentSummary['parentsAcknowledged'] ?? 0;
        echo '<div class="flex justify-between"><span>' . __('Parents Notified') . ':</span><span>' . $notified . '/' . $totalIncidents . '</span></div>';
    } else {
        echo '<p class="text-green-600">' . __('No incidents today.') . '</p>';
    }
    echo '</div>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/CareTracking/careTracking_incidents.php&date=' . $date . '" class="block mt-3 text-blue-600 hover:underline">' . __('View Incidents') . ' &rarr;</a>';
    echo '</div>';

    // Activities Card
    echo '<div class="bg-white rounded-lg shadow p-4">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">' . __('Activities') . '</h3>';
    echo '<div class="space-y-2">';
    $totalActivities = $activitySummary['totalActivities'] ?? 0;
    echo '<div class="flex justify-between"><span>' . __('Total Activities') . ':</span><span class="font-bold">' . $totalActivities . '</span></div>';
    echo '<div class="flex justify-between"><span>' . __('Children Participated') . ':</span><span>' . ($activitySummary['childrenParticipated'] ?? 0) . '</span></div>';
    echo '<div class="flex justify-between"><span>' . __('Unique Activities') . ':</span><span>' . ($activitySummary['uniqueActivities'] ?? 0) . '</span></div>';
    $totalDuration = $activitySummary['totalDurationMinutes'] ?? 0;
    if ($totalDuration > 0) {
        $hours = floor($totalDuration / 60);
        $mins = $totalDuration % 60;
        $durationStr = $hours > 0 ? $hours . 'h ' . $mins . 'm' : $mins . 'm';
        echo '<div class="flex justify-between"><span>' . __('Total Duration') . ':</span><span>' . $durationStr . '</span></div>';
    }
    // Activity type breakdown
    $physicalActivities = $activitySummary['physicalActivities'] ?? 0;
    $outdoorActivities = $activitySummary['outdoorActivities'] ?? 0;
    $artActivities = $activitySummary['artActivities'] ?? 0;
    if ($physicalActivities > 0 || $outdoorActivities > 0) {
        echo '<div class="flex justify-between"><span>' . __('Physical/Outdoor') . ':</span><span>' . ($physicalActivities + $outdoorActivities) . '</span></div>';
    }
    echo '</div>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/CareTracking/careTracking_activities.php&date=' . $date . '" class="block mt-3 text-blue-600 hover:underline">' . __('Log Activities') . ' &rarr;</a>';
    echo '</div>';

    echo '</div>'; // End grid

    // Quick Action Buttons
    echo '<div class="mt-6">';
    echo '<h3 class="text-lg font-semibold mb-3">' . __('Quick Actions') . '</h3>';
    echo '<div class="flex flex-wrap gap-2">';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/CareTracking/careTracking_attendance.php&date=' . $date . '" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">' . __('Check In/Out') . '</a>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/CareTracking/careTracking_meals.php&date=' . $date . '" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">' . __('Log Meal') . '</a>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/CareTracking/careTracking_naps.php&date=' . $date . '" class="bg-purple-500 text-white px-4 py-2 rounded hover:bg-purple-600">' . __('Start Nap') . '</a>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/CareTracking/careTracking_diapers.php&date=' . $date . '" class="bg-yellow-500 text-white px-4 py-2 rounded hover:bg-yellow-600">' . __('Diaper Change') . '</a>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/CareTracking/careTracking_incidents.php&date=' . $date . '" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">' . __('Report Incident') . '</a>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/CareTracking/careTracking_activities.php&date=' . $date . '" class="bg-indigo-500 text-white px-4 py-2 rounded hover:bg-indigo-600">' . __('Log Activity') . '</a>';
    echo '</div>';
    echo '</div>';
}
