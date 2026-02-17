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
use Gibbon\Tables\DataTable;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Module\StaffManagement\Domain\RatioComplianceGateway;

// Module setup - breadcrumbs
$page->breadcrumbs
    ->add(__('Staff Management'), 'staffManagement.php')
    ->add(__('Ratio Monitor'), 'staffManagement_ratioMonitor.php')
    ->add(__('Ratio History'));

// Access check
if (!isActionAccessible($guid, $connection2, '/modules/StaffManagement/staffManagement_ratioHistory.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get school year ID from session
    $gibbonSchoolYearID = $session->get('gibbonSchoolYearID');

    // Get filter parameters
    $dateFrom = $_GET['dateFrom'] ?? date('Y-m-d', strtotime('-30 days'));
    $dateTo = $_GET['dateTo'] ?? date('Y-m-d');
    $ageGroup = $_GET['ageGroup'] ?? '';
    $roomName = $_GET['roomName'] ?? '';
    $complianceFilter = $_GET['compliance'] ?? '';

    // Validate date formats
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
        $dateFrom = date('Y-m-d', strtotime('-30 days'));
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
        $dateTo = date('Y-m-d');
    }

    // Ensure dateFrom is not after dateTo
    if ($dateFrom > $dateTo) {
        $temp = $dateFrom;
        $dateFrom = $dateTo;
        $dateTo = $temp;
    }

    // Get gateway via DI container
    $ratioComplianceGateway = $container->get(RatioComplianceGateway::class);

    // Get unique rooms for filter dropdown
    $rooms = $ratioComplianceGateway->selectUniqueRooms($gibbonSchoolYearID)->fetchAll();

    // Page header
    echo '<h2>' . __('Historical Ratio Compliance Report') . '</h2>';

    // Filter form
    echo '<div class="bg-white rounded-lg shadow p-4 mb-6">';
    echo '<form method="get" action="' . $session->get('absoluteURL') . '/index.php">';
    echo '<input type="hidden" name="q" value="/modules/StaffManagement/staffManagement_ratioHistory.php">';

    echo '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">';

    // Date From
    echo '<div>';
    echo '<label class="block text-sm font-medium text-gray-700 mb-1">' . __('Date From') . '</label>';
    echo '<input type="date" name="dateFrom" value="' . htmlspecialchars($dateFrom) . '" class="w-full standardWidth">';
    echo '</div>';

    // Date To
    echo '<div>';
    echo '<label class="block text-sm font-medium text-gray-700 mb-1">' . __('Date To') . '</label>';
    echo '<input type="date" name="dateTo" value="' . htmlspecialchars($dateTo) . '" class="w-full standardWidth">';
    echo '</div>';

    // Age Group Filter
    echo '<div>';
    echo '<label class="block text-sm font-medium text-gray-700 mb-1">' . __('Age Group') . '</label>';
    echo '<select name="ageGroup" class="w-full standardWidth">';
    echo '<option value="">' . __('All Age Groups') . '</option>';
    $ageGroups = ['Infant', 'Toddler', 'Preschool', 'School Age'];
    foreach ($ageGroups as $ag) {
        $selected = $ageGroup === $ag ? ' selected' : '';
        echo '<option value="' . $ag . '"' . $selected . '>' . __($ag) . '</option>';
    }
    echo '</select>';
    echo '</div>';

    // Room Filter
    echo '<div>';
    echo '<label class="block text-sm font-medium text-gray-700 mb-1">' . __('Room') . '</label>';
    echo '<select name="roomName" class="w-full standardWidth">';
    echo '<option value="">' . __('All Rooms') . '</option>';
    foreach ($rooms as $room) {
        if (!empty($room['roomName'])) {
            $selected = $roomName === $room['roomName'] ? ' selected' : '';
            echo '<option value="' . htmlspecialchars($room['roomName']) . '"' . $selected . '>' . htmlspecialchars($room['roomName']) . '</option>';
        }
    }
    echo '</select>';
    echo '</div>';

    // Compliance Filter
    echo '<div>';
    echo '<label class="block text-sm font-medium text-gray-700 mb-1">' . __('Compliance') . '</label>';
    echo '<select name="compliance" class="w-full standardWidth">';
    echo '<option value="">' . __('All') . '</option>';
    echo '<option value="Y"' . ($complianceFilter === 'Y' ? ' selected' : '') . '>' . __('Compliant Only') . '</option>';
    echo '<option value="N"' . ($complianceFilter === 'N' ? ' selected' : '') . '>' . __('Non-Compliant Only') . '</option>';
    echo '</select>';
    echo '</div>';

    echo '</div>'; // End grid

    // Submit and quick date buttons
    echo '<div class="mt-4 flex flex-wrap gap-2">';
    echo '<button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">' . __('Filter') . '</button>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_ratioHistory.php&dateFrom=' . date('Y-m-d', strtotime('-7 days')) . '&dateTo=' . date('Y-m-d') . '" class="bg-gray-200 text-gray-700 px-4 py-2 rounded hover:bg-gray-300">' . __('Last 7 Days') . '</a>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_ratioHistory.php&dateFrom=' . date('Y-m-d', strtotime('-30 days')) . '&dateTo=' . date('Y-m-d') . '" class="bg-gray-200 text-gray-700 px-4 py-2 rounded hover:bg-gray-300">' . __('Last 30 Days') . '</a>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_ratioHistory.php&dateFrom=' . date('Y-m-01') . '&dateTo=' . date('Y-m-d') . '" class="bg-gray-200 text-gray-700 px-4 py-2 rounded hover:bg-gray-300">' . __('This Month') . '</a>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_ratioHistory.php&dateFrom=' . date('Y-01-01') . '&dateTo=' . date('Y-m-d') . '" class="bg-gray-200 text-gray-700 px-4 py-2 rounded hover:bg-gray-300">' . __('This Year') . '</a>';
    echo '</div>';

    echo '</form>';
    echo '</div>';

    // Display date range
    echo '<p class="text-lg mb-4">' . __('Showing data from') . ': <strong>' . Format::date($dateFrom) . '</strong> ' . __('to') . ' <strong>' . Format::date($dateTo) . '</strong></p>';

    // Get compliance summary by age group
    $ageGroupSummary = $ratioComplianceGateway->selectComplianceSummaryByAgeGroup($gibbonSchoolYearID, $dateFrom, $dateTo)->fetchAll();

    // Get compliance trend data
    $complianceTrend = $ratioComplianceGateway->selectComplianceTrend($gibbonSchoolYearID, $dateFrom, $dateTo)->fetchAll();

    // Get peak non-compliance times
    $peakTimes = $ratioComplianceGateway->selectPeakNonComplianceTimes($gibbonSchoolYearID, $dateFrom, $dateTo)->fetchAll();

    // Calculate overall statistics
    $totalSnapshots = 0;
    $totalCompliant = 0;
    $totalNonCompliant = 0;
    foreach ($ageGroupSummary as $summary) {
        $totalSnapshots += $summary['totalSnapshots'];
        $totalCompliant += $summary['compliantSnapshots'];
        $totalNonCompliant += $summary['nonCompliantSnapshots'];
    }
    $overallComplianceRate = $totalSnapshots > 0 ? round(($totalCompliant / $totalSnapshots) * 100, 1) : 100;

    // Overall Statistics Cards
    echo '<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">';

    // Overall Compliance Rate
    $complianceClass = $overallComplianceRate >= 95 ? 'text-green-600' : ($overallComplianceRate >= 80 ? 'text-orange-500' : 'text-red-600');
    echo '<div class="bg-white rounded-lg shadow p-4 text-center">';
    echo '<div class="text-3xl font-bold ' . $complianceClass . '">' . $overallComplianceRate . '%</div>';
    echo '<div class="text-sm text-gray-500">' . __('Overall Compliance') . '</div>';
    echo '</div>';

    // Total Snapshots
    echo '<div class="bg-white rounded-lg shadow p-4 text-center">';
    echo '<div class="text-3xl font-bold">' . $totalSnapshots . '</div>';
    echo '<div class="text-sm text-gray-500">' . __('Total Snapshots') . '</div>';
    echo '</div>';

    // Compliant Count
    echo '<div class="bg-white rounded-lg shadow p-4 text-center">';
    echo '<div class="text-3xl font-bold text-green-600">' . $totalCompliant . '</div>';
    echo '<div class="text-sm text-gray-500">' . __('Compliant Snapshots') . '</div>';
    echo '</div>';

    // Non-Compliant Count
    echo '<div class="bg-white rounded-lg shadow p-4 text-center">';
    echo '<div class="text-3xl font-bold text-red-600">' . $totalNonCompliant . '</div>';
    echo '<div class="text-sm text-gray-500">' . __('Non-Compliant') . '</div>';
    echo '</div>';

    echo '</div>'; // End statistics grid

    // Age Group Summary Cards
    echo '<h3 class="text-lg font-semibold mb-3">' . __('Compliance by Age Group') . '</h3>';
    echo '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">';

    $ageGroupColors = [
        'Infant' => 'blue',
        'Toddler' => 'green',
        'Preschool' => 'purple',
        'School Age' => 'indigo',
    ];

    foreach ($ageGroupSummary as $summary) {
        $color = $ageGroupColors[$summary['ageGroup']] ?? 'gray';
        $rate = floatval($summary['complianceRate']);
        $rateClass = $rate >= 95 ? 'text-green-600' : ($rate >= 80 ? 'text-orange-500' : 'text-red-600');

        echo '<div class="bg-white rounded-lg shadow p-4">';
        echo '<div class="flex justify-between items-center mb-2">';
        echo '<h4 class="font-semibold text-' . $color . '-600">' . __($summary['ageGroup']) . '</h4>';
        echo '<span class="text-sm text-gray-500">' . __('Required') . ': 1:' . $summary['requiredRatio'] . '</span>';
        echo '</div>';

        echo '<div class="text-2xl font-bold ' . $rateClass . ' mb-2">' . $rate . '%</div>';

        echo '<div class="grid grid-cols-2 gap-2 text-sm">';
        echo '<div class="text-gray-500">' . __('Total') . ': <span class="font-semibold text-gray-800">' . $summary['totalSnapshots'] . '</span></div>';
        echo '<div class="text-gray-500">' . __('Compliant') . ': <span class="font-semibold text-green-600">' . $summary['compliantSnapshots'] . '</span></div>';
        echo '<div class="text-gray-500">' . __('Non-Compliant') . ': <span class="font-semibold text-red-600">' . $summary['nonCompliantSnapshots'] . '</span></div>';
        echo '<div class="text-gray-500">' . __('Avg Capacity') . ': <span class="font-semibold">' . round($summary['avgCompliancePercent'], 1) . '%</span></div>';
        echo '</div>';

        // Progress bar for compliance rate
        $barColor = $rate >= 95 ? 'bg-green-500' : ($rate >= 80 ? 'bg-yellow-500' : 'bg-red-500');
        echo '<div class="mt-3">';
        echo '<div class="w-full bg-gray-200 rounded-full h-2">';
        echo '<div class="' . $barColor . ' h-2 rounded-full" style="width: ' . $rate . '%"></div>';
        echo '</div>';
        echo '</div>';

        echo '</div>';
    }

    echo '</div>'; // End age group grid

    // Compliance Trend Table
    if (!empty($complianceTrend)) {
        echo '<h3 class="text-lg font-semibold mb-3">' . __('Daily Compliance Trend') . '</h3>';
        echo '<div class="bg-white rounded-lg shadow overflow-hidden mb-6">';
        echo '<div class="overflow-x-auto">';
        echo '<table class="min-w-full divide-y divide-gray-200">';
        echo '<thead class="bg-gray-50">';
        echo '<tr>';
        echo '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">' . __('Date') . '</th>';
        echo '<th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">' . __('Total Snapshots') . '</th>';
        echo '<th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">' . __('Compliant') . '</th>';
        echo '<th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">' . __('Compliance Rate') . '</th>';
        echo '<th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">' . __('Avg Capacity') . '</th>';
        echo '<th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">' . __('Total Staff') . '</th>';
        echo '<th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">' . __('Total Children') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody class="bg-white divide-y divide-gray-200">';

        foreach ($complianceTrend as $day) {
            $rate = floatval($day['complianceRate']);
            $rowClass = $rate < 80 ? 'bg-red-50' : ($rate < 95 ? 'bg-yellow-50' : '');
            $rateClass = $rate >= 95 ? 'text-green-600' : ($rate >= 80 ? 'text-orange-500' : 'text-red-600');

            echo '<tr class="' . $rowClass . '">';
            echo '<td class="px-6 py-4 whitespace-nowrap font-medium">' . Format::date($day['snapshotDate']) . '</td>';
            echo '<td class="px-6 py-4 whitespace-nowrap text-center">' . $day['totalSnapshots'] . '</td>';
            echo '<td class="px-6 py-4 whitespace-nowrap text-center text-green-600">' . $day['compliantSnapshots'] . '</td>';
            echo '<td class="px-6 py-4 whitespace-nowrap text-center font-semibold ' . $rateClass . '">' . $rate . '%</td>';
            echo '<td class="px-6 py-4 whitespace-nowrap text-center">' . round($day['avgCompliancePercent'], 1) . '%</td>';
            echo '<td class="px-6 py-4 whitespace-nowrap text-center">' . $day['totalStaff'] . '</td>';
            echo '<td class="px-6 py-4 whitespace-nowrap text-center">' . $day['totalChildren'] . '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
        echo '</div>';
        echo '</div>';
    }

    // Peak Non-Compliance Times
    if (!empty($peakTimes)) {
        // Filter to show only hours with non-compliance
        $hoursWithIssues = array_filter($peakTimes, function ($t) {
            return $t['nonCompliantCount'] > 0;
        });

        if (!empty($hoursWithIssues)) {
            echo '<h3 class="text-lg font-semibold mb-3">' . __('Peak Non-Compliance Times') . '</h3>';
            echo '<p class="text-sm text-gray-500 mb-3">' . __('Hours with the highest rate of non-compliance. Use this to identify staffing gaps.') . '</p>';
            echo '<div class="bg-white rounded-lg shadow overflow-hidden mb-6">';
            echo '<div class="overflow-x-auto">';
            echo '<table class="min-w-full divide-y divide-gray-200">';
            echo '<thead class="bg-gray-50">';
            echo '<tr>';
            echo '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">' . __('Hour') . '</th>';
            echo '<th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">' . __('Total Snapshots') . '</th>';
            echo '<th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">' . __('Non-Compliant') . '</th>';
            echo '<th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">' . __('Non-Compliance Rate') . '</th>';
            echo '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">' . __('Risk Level') . '</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody class="bg-white divide-y divide-gray-200">';

            $counter = 0;
            foreach ($hoursWithIssues as $timeSlot) {
                if ($counter >= 10) break; // Show top 10 problem hours

                $hour = intval($timeSlot['hour']);
                $hourDisplay = sprintf('%02d:00 - %02d:59', $hour, $hour);
                $rate = floatval($timeSlot['nonComplianceRate']);

                // Determine risk level
                if ($rate >= 20) {
                    $riskLevel = __('High Risk');
                    $riskClass = 'bg-red-100 text-red-800';
                } elseif ($rate >= 10) {
                    $riskLevel = __('Medium Risk');
                    $riskClass = 'bg-yellow-100 text-yellow-800';
                } else {
                    $riskLevel = __('Low Risk');
                    $riskClass = 'bg-blue-100 text-blue-800';
                }

                echo '<tr>';
                echo '<td class="px-6 py-4 whitespace-nowrap font-medium">' . $hourDisplay . '</td>';
                echo '<td class="px-6 py-4 whitespace-nowrap text-center">' . $timeSlot['totalSnapshots'] . '</td>';
                echo '<td class="px-6 py-4 whitespace-nowrap text-center text-red-600 font-semibold">' . $timeSlot['nonCompliantCount'] . '</td>';
                echo '<td class="px-6 py-4 whitespace-nowrap text-center font-semibold">' . $rate . '%</td>';
                echo '<td class="px-6 py-4 whitespace-nowrap"><span class="px-2 py-1 rounded text-xs font-semibold ' . $riskClass . '">' . $riskLevel . '</span></td>';
                echo '</tr>';

                $counter++;
            }

            echo '</tbody>';
            echo '</table>';
            echo '</div>';
            echo '</div>';
        }
    }

    // Detailed Snapshot History (DataTable)
    echo '<h3 class="text-lg font-semibold mb-3">' . __('Detailed Snapshot History') . '</h3>';

    // Build query criteria
    $criteria = $ratioComplianceGateway->newQueryCriteria()
        ->sortBy('snapshotDate', 'DESC')
        ->sortBy('snapshotTime', 'DESC')
        ->filterBy('dateFrom', $dateFrom)
        ->filterBy('dateTo', $dateTo)
        ->fromPOST();

    // Add additional filters
    if (!empty($ageGroup)) {
        $criteria->filterBy('ageGroup', $ageGroup);
    }
    if (!empty($roomName)) {
        $criteria->filterBy('roomName', $roomName);
    }
    if (!empty($complianceFilter)) {
        $criteria->filterBy('isCompliant', $complianceFilter);
    }

    // Query snapshots
    $snapshots = $ratioComplianceGateway->queryRatioSnapshots($criteria, $gibbonSchoolYearID);

    // Create DataTable
    $table = DataTable::createPaginated('ratioHistory', $criteria);
    $table->setTitle(__('Snapshot Records'));

    // Add columns
    $table->addColumn('snapshotDate', __('Date'))
        ->format(function ($row) {
            return Format::date($row['snapshotDate']);
        });

    $table->addColumn('snapshotTime', __('Time'))
        ->format(function ($row) {
            return Format::time($row['snapshotTime']);
        });

    $table->addColumn('ageGroup', __('Age Group'))
        ->format(function ($row) {
            $colors = [
                'Infant' => 'blue',
                'Toddler' => 'green',
                'Preschool' => 'purple',
                'School Age' => 'indigo',
            ];
            $color = $colors[$row['ageGroup']] ?? 'gray';
            return '<span class="px-2 py-1 rounded text-xs font-semibold bg-' . $color . '-100 text-' . $color . '-800">' . __($row['ageGroup']) . '</span>';
        });

    $table->addColumn('roomName', __('Room'))
        ->format(function ($row) {
            return $row['roomName'] ?: '-';
        });

    $table->addColumn('staffCount', __('Staff'))
        ->format(function ($row) {
            return '<span class="font-semibold">' . $row['staffCount'] . '</span>';
        });

    $table->addColumn('childCount', __('Children'))
        ->format(function ($row) {
            return '<span class="font-semibold">' . $row['childCount'] . '</span>';
        });

    $table->addColumn('ratio', __('Ratio'))
        ->format(function ($row) {
            if ($row['staffCount'] > 0) {
                $actual = '1:' . round($row['actualRatio'], 1);
                $required = '1:' . $row['requiredRatio'];
                return $actual . ' <span class="text-gray-400 text-xs">(' . $required . ')</span>';
            }
            return __('No Staff');
        });

    $table->addColumn('compliancePercent', __('Capacity'))
        ->format(function ($row) {
            $percent = round($row['compliancePercent'], 1);
            $colorClass = $percent >= 100 ? 'text-red-600' : ($percent >= 90 ? 'text-orange-500' : 'text-green-600');
            return '<span class="font-semibold ' . $colorClass . '">' . $percent . '%</span>';
        });

    $table->addColumn('isCompliant', __('Status'))
        ->format(function ($row) {
            if ($row['isCompliant'] === 'Y') {
                return '<span class="px-2 py-1 rounded text-xs font-semibold bg-green-100 text-green-800">&#10003; ' . __('Compliant') . '</span>';
            } else {
                return '<span class="px-2 py-1 rounded text-xs font-semibold bg-red-100 text-red-800">&#10007; ' . __('Non-Compliant') . '</span>';
            }
        });

    $table->addColumn('alertSent', __('Alert'))
        ->format(function ($row) {
            if ($row['alertSent'] === 'Y') {
                return '<span class="text-blue-600" title="' . __('Alert Sent') . '">&#128276;</span>';
            }
            return '-';
        });

    $table->addColumn('recordedBy', __('Recorded By'))
        ->format(function ($row) {
            if (!empty($row['recordedByName'])) {
                return Format::name('', $row['recordedByName'], $row['recordedBySurname'], 'Staff', false, true);
            }
            return $row['isAutomatic'] === 'Y' ? '<span class="text-gray-400">' . __('Automatic') . '</span>' : '-';
        });

    echo $table->render($snapshots);

    // Export Options
    echo '<div class="mt-6 mb-6">';
    echo '<h3 class="text-lg font-semibold mb-3">' . __('Export Options') . '</h3>';
    echo '<div class="flex flex-wrap gap-2">';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_ratioHistory.php&dateFrom=' . $dateFrom . '&dateTo=' . $dateTo . '&ageGroup=' . urlencode($ageGroup) . '&roomName=' . urlencode($roomName) . '&compliance=' . $complianceFilter . '&export=csv" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">' . __('Export to CSV') . '</a>';
    echo '<a href="javascript:window.print();" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">' . __('Print Report') . '</a>';
    echo '</div>';
    echo '</div>';

    // Handle CSV Export
    if (isset($_GET['export']) && $_GET['export'] === 'csv') {
        // This is a simple implementation - in production, use proper CSV library
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="ratio_compliance_' . $dateFrom . '_to_' . $dateTo . '.csv"');

        $output = fopen('php://output', 'w');

        // Header row
        fputcsv($output, [
            __('Date'),
            __('Time'),
            __('Age Group'),
            __('Room'),
            __('Staff Count'),
            __('Child Count'),
            __('Required Ratio'),
            __('Actual Ratio'),
            __('Capacity %'),
            __('Compliant'),
            __('Alert Sent'),
            __('Notes'),
        ]);

        // Get all data for export (without pagination)
        $exportCriteria = $ratioComplianceGateway->newQueryCriteria()
            ->sortBy('snapshotDate', 'DESC')
            ->sortBy('snapshotTime', 'DESC')
            ->filterBy('dateFrom', $dateFrom)
            ->filterBy('dateTo', $dateTo);

        if (!empty($ageGroup)) {
            $exportCriteria->filterBy('ageGroup', $ageGroup);
        }
        if (!empty($roomName)) {
            $exportCriteria->filterBy('roomName', $roomName);
        }
        if (!empty($complianceFilter)) {
            $exportCriteria->filterBy('isCompliant', $complianceFilter);
        }

        $exportSnapshots = $ratioComplianceGateway->queryRatioSnapshots($exportCriteria, $gibbonSchoolYearID);

        foreach ($exportSnapshots as $row) {
            fputcsv($output, [
                $row['snapshotDate'],
                $row['snapshotTime'],
                $row['ageGroup'],
                $row['roomName'] ?? '',
                $row['staffCount'],
                $row['childCount'],
                $row['requiredRatio'],
                $row['actualRatio'],
                $row['compliancePercent'],
                $row['isCompliant'] === 'Y' ? __('Yes') : __('No'),
                $row['alertSent'] === 'Y' ? __('Yes') : __('No'),
                $row['notes'] ?? '',
            ]);
        }

        fclose($output);
        exit;
    }

    // Quebec Compliance Reference
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

    // Link back to real-time monitor
    echo '<div class="mt-4">';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_ratioMonitor.php" class="text-blue-600 hover:underline">&larr; ' . __('Back to Real-Time Monitor') . '</a>';
    echo '</div>';
}
