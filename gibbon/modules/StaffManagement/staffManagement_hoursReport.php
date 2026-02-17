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

use Gibbon\Forms\Form;
use Gibbon\Tables\DataTable;
use Gibbon\Services\Format;
use Gibbon\Module\StaffManagement\Domain\TimeTrackingGateway;
use Gibbon\Module\StaffManagement\Domain\StaffProfileGateway;

// Module setup - breadcrumbs
$page->breadcrumbs->add(__('Staff Management'), 'staffManagement.php');
$page->breadcrumbs->add(__('Hours Report'));

// Access check
if (!isActionAccessible($guid, $connection2, '/modules/StaffManagement/staffManagement_hoursReport.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get school year ID from session
    $gibbonSchoolYearID = $session->get('gibbonSchoolYearID');
    $gibbonPersonID = $session->get('gibbonPersonID');

    // Get date range from request or default to current week
    $dateFrom = $_GET['dateFrom'] ?? date('Y-m-d', strtotime('monday this week'));
    $dateTo = $_GET['dateTo'] ?? date('Y-m-d', strtotime('sunday this week'));
    $staffFilter = $_GET['staff'] ?? '';
    $reportType = $_GET['reportType'] ?? 'weekly';

    // Validate date formats
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
        $dateFrom = date('Y-m-d', strtotime('monday this week'));
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
        $dateTo = date('Y-m-d', strtotime('sunday this week'));
    }

    // Ensure dateFrom is before dateTo
    if ($dateFrom > $dateTo) {
        $temp = $dateFrom;
        $dateFrom = $dateTo;
        $dateTo = $temp;
    }

    // Get gateways via DI container
    $timeTrackingGateway = $container->get(TimeTrackingGateway::class);
    $staffProfileGateway = $container->get(StaffProfileGateway::class);

    // Page header
    echo '<h2>' . __('Staff Hours Report') . '</h2>';

    // Filter Form
    $form = Form::create('hoursReportFilter', $session->get('absoluteURL') . '/index.php');
    $form->setMethod('get');
    $form->setClass('noIntBorder fullWidth');
    $form->addHiddenValue('q', '/modules/StaffManagement/staffManagement_hoursReport.php');

    $row = $form->addRow();
    $row->addLabel('reportType', __('Report Type'));
    $row->addSelect('reportType')
        ->fromArray([
            'weekly' => __('Weekly Summary'),
            'monthly' => __('Monthly (Payroll)'),
            'custom' => __('Custom Date Range'),
        ])
        ->selected($reportType);

    $row = $form->addRow();
    $row->addLabel('dateFrom', __('Date From'));
    $row->addDate('dateFrom')->setValue(Format::date($dateFrom))->required();

    $row = $form->addRow();
    $row->addLabel('dateTo', __('Date To'));
    $row->addDate('dateTo')->setValue(Format::date($dateTo))->required();

    // Get staff list for filter
    $criteria = $staffProfileGateway->newQueryCriteria()
        ->sortBy(['surname', 'preferredName']);
    $staffList = $staffProfileGateway->queryStaffProfiles($criteria, $gibbonSchoolYearID);
    $staffOptions = ['' => __('-- All Staff --')];
    foreach ($staffList as $staff) {
        $staffOptions[$staff['gibbonPersonID']] = Format::name('', $staff['preferredName'], $staff['surname'], 'Staff', true, true);
    }

    $row = $form->addRow();
    $row->addLabel('staff', __('Staff Member'));
    $row->addSelect('staff')->fromArray($staffOptions)->selected($staffFilter);

    $row = $form->addRow();
    $row->addSubmit(__('Filter'));

    echo $form->getOutput();

    // Quick date range links
    echo '<div class="mb-4 flex flex-wrap gap-2">';
    echo '<span class="text-gray-600 mr-2">' . __('Quick Select') . ':</span>';

    // This week
    $thisWeekStart = date('Y-m-d', strtotime('monday this week'));
    $thisWeekEnd = date('Y-m-d', strtotime('sunday this week'));
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_hoursReport.php&dateFrom=' . $thisWeekStart . '&dateTo=' . $thisWeekEnd . '&reportType=weekly" class="text-blue-600 hover:underline px-2">' . __('This Week') . '</a>';

    // Last week
    $lastWeekStart = date('Y-m-d', strtotime('monday last week'));
    $lastWeekEnd = date('Y-m-d', strtotime('sunday last week'));
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_hoursReport.php&dateFrom=' . $lastWeekStart . '&dateTo=' . $lastWeekEnd . '&reportType=weekly" class="text-blue-600 hover:underline px-2">' . __('Last Week') . '</a>';

    // This month
    $thisMonthStart = date('Y-m-01');
    $thisMonthEnd = date('Y-m-t');
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_hoursReport.php&dateFrom=' . $thisMonthStart . '&dateTo=' . $thisMonthEnd . '&reportType=monthly" class="text-blue-600 hover:underline px-2">' . __('This Month') . '</a>';

    // Last month
    $lastMonthStart = date('Y-m-01', strtotime('first day of last month'));
    $lastMonthEnd = date('Y-m-t', strtotime('last day of last month'));
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_hoursReport.php&dateFrom=' . $lastMonthStart . '&dateTo=' . $lastMonthEnd . '&reportType=monthly" class="text-blue-600 hover:underline px-2">' . __('Last Month') . '</a>';

    echo '</div>';

    // Display date range
    echo '<p class="text-lg mb-4">' . __('Report Period') . ': <strong>' . Format::date($dateFrom) . '</strong> ' . __('to') . ' <strong>' . Format::date($dateTo) . '</strong></p>';

    // Calculate overall summary for the period
    $overallSummary = [
        'totalStaff' => 0,
        'totalDays' => 0,
        'totalWorkedMinutes' => 0,
        'totalOvertimeMinutes' => 0,
        'approvedOvertimeMinutes' => 0,
        'pendingOvertimeMinutes' => 0,
    ];

    // Get hours summary data
    if (!empty($staffFilter)) {
        // Single staff member summary
        $staffSummary = $timeTrackingGateway->getHoursSummaryByPersonAndDateRange($staffFilter, $dateFrom, $dateTo);
        $overallSummary['totalStaff'] = 1;
        $overallSummary['totalDays'] = $staffSummary['totalDays'];
        $overallSummary['totalWorkedMinutes'] = $staffSummary['totalWorkedMinutes'];
        $overallSummary['totalOvertimeMinutes'] = $staffSummary['totalOvertimeMinutes'];
        $overallSummary['approvedOvertimeMinutes'] = $staffSummary['approvedOvertimeMinutes'];
    } else {
        // All staff summary
        $weeklyHours = $timeTrackingGateway->selectWeeklyHoursSummary($gibbonSchoolYearID, $dateFrom, $dateTo);
        foreach ($weeklyHours as $staff) {
            $overallSummary['totalStaff']++;
            $overallSummary['totalDays'] += $staff['daysWorked'];
            $overallSummary['totalWorkedMinutes'] += intval($staff['totalWorkedMinutes']);
            $overallSummary['totalOvertimeMinutes'] += intval($staff['totalOvertimeMinutes']);
        }
    }

    // Calculate hours from minutes
    $totalWorkedHours = round($overallSummary['totalWorkedMinutes'] / 60, 2);
    $totalOvertimeHours = round($overallSummary['totalOvertimeMinutes'] / 60, 2);

    // Summary Statistics Cards
    echo '<div class="bg-white rounded-lg shadow p-4 mb-6">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">' . __('Period Summary') . '</h3>';
    echo '<div class="grid grid-cols-2 md:grid-cols-5 gap-4 text-center">';

    echo '<div>';
    echo '<span class="block text-2xl font-bold text-blue-600">' . $overallSummary['totalStaff'] . '</span>';
    echo '<span class="text-sm text-gray-600">' . __('Staff Members') . '</span>';
    echo '</div>';

    echo '<div>';
    echo '<span class="block text-2xl font-bold text-green-600">' . number_format($totalWorkedHours, 1) . '</span>';
    echo '<span class="text-sm text-gray-600">' . __('Total Hours') . '</span>';
    echo '</div>';

    echo '<div>';
    echo '<span class="block text-2xl font-bold">' . $overallSummary['totalDays'] . '</span>';
    echo '<span class="text-sm text-gray-600">' . __('Days Worked') . '</span>';
    echo '</div>';

    echo '<div>';
    echo '<span class="block text-2xl font-bold text-orange-500">' . number_format($totalOvertimeHours, 1) . '</span>';
    echo '<span class="text-sm text-gray-600">' . __('Overtime Hours') . '</span>';
    echo '</div>';

    // Calculate average hours per day
    $avgHoursPerDay = $overallSummary['totalDays'] > 0 ? round($totalWorkedHours / $overallSummary['totalDays'], 1) : 0;
    echo '<div>';
    echo '<span class="block text-2xl font-bold text-purple-500">' . $avgHoursPerDay . '</span>';
    echo '<span class="text-sm text-gray-600">' . __('Avg Hours/Day') . '</span>';
    echo '</div>';

    echo '</div>';
    echo '</div>';

    // Staff Hours Table
    echo '<h3 class="text-lg font-semibold mt-6 mb-3">' . __('Staff Hours Detail') . '</h3>';

    // Build query criteria
    $criteria = $timeTrackingGateway->newQueryCriteria()
        ->sortBy(['surname', 'preferredName'])
        ->filterBy('dateFrom', $dateFrom)
        ->filterBy('dateTo', $dateTo)
        ->fromPOST();

    if (!empty($staffFilter)) {
        $criteria->filterBy('staff', $staffFilter);
    }

    // Get weekly hours summary for all staff
    $hoursData = $timeTrackingGateway->selectWeeklyHoursSummary($gibbonSchoolYearID, $dateFrom, $dateTo);

    if ($hoursData->rowCount() > 0) {
        // Build DataTable
        $table = DataTable::create('hoursReport');
        $table->setTitle(__('Hours by Staff Member'));

        // Add columns
        $table->addColumn('name', __('Staff Member'))
            ->sortable(['surname', 'preferredName'])
            ->format(function ($row) {
                return Format::name('', $row['preferredName'], $row['surname'], 'Staff', true, true);
            });

        $table->addColumn('position', __('Position'))
            ->format(function ($row) {
                return !empty($row['position']) ? htmlspecialchars($row['position']) : '<span class="text-gray-400">-</span>';
            });

        $table->addColumn('department', __('Department'))
            ->format(function ($row) {
                return !empty($row['department']) ? htmlspecialchars($row['department']) : '<span class="text-gray-400">-</span>';
            });

        $table->addColumn('daysWorked', __('Days Worked'))
            ->format(function ($row) {
                return $row['daysWorked'];
            });

        $table->addColumn('totalWorkedMinutes', __('Total Hours'))
            ->format(function ($row) {
                $minutes = intval($row['totalWorkedMinutes']);
                $hours = floor($minutes / 60);
                $mins = $minutes % 60;
                return $hours . 'h ' . $mins . 'm';
            });

        $table->addColumn('avgHours', __('Avg Hours/Day'))
            ->format(function ($row) {
                $minutes = intval($row['totalWorkedMinutes']);
                $days = intval($row['daysWorked']);
                if ($days > 0) {
                    $avgMinutes = $minutes / $days;
                    $hours = floor($avgMinutes / 60);
                    $mins = round($avgMinutes % 60);
                    return $hours . 'h ' . $mins . 'm';
                }
                return '-';
            });

        $table->addColumn('totalOvertimeMinutes', __('Overtime'))
            ->format(function ($row) {
                $minutes = intval($row['totalOvertimeMinutes']);
                if ($minutes <= 0) {
                    return '<span class="text-gray-400">-</span>';
                }
                $hours = floor($minutes / 60);
                $mins = $minutes % 60;
                return '<span class="text-orange-500 font-semibold">' . $hours . 'h ' . $mins . 'm</span>';
            });

        $table->addColumn('pendingOvertimeCount', __('Pending Approval'))
            ->format(function ($row) {
                $count = intval($row['pendingOvertimeCount']);
                if ($count <= 0) {
                    return '<span class="text-gray-400">-</span>';
                }
                return '<span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded text-xs">' . $count . ' ' . __('pending') . '</span>';
            });

        // Action to view individual details
        $table->addActionColumn()
            ->addParam('gibbonPersonID')
            ->format(function ($row, $actions) use ($session, $dateFrom, $dateTo) {
                $actions->addAction('view', __('View Details'))
                    ->setURL($session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_hoursReport.php')
                    ->addParam('staff', $row['gibbonPersonID'])
                    ->addParam('dateFrom', $dateFrom)
                    ->addParam('dateTo', $dateTo)
                    ->addParam('reportType', 'custom');
            });

        echo $table->render(new \Gibbon\Domain\DataSet($hoursData->fetchAll()));
    } else {
        echo '<div class="bg-gray-50 rounded-lg p-4 text-center text-gray-500">';
        echo __('No time entries found for the selected period.');
        echo '</div>';
    }

    // If a specific staff member is selected, show detailed breakdown
    if (!empty($staffFilter)) {
        echo '<h3 class="text-lg font-semibold mt-6 mb-3">' . __('Daily Breakdown') . '</h3>';

        // Get detailed time entries for this staff member
        $detailCriteria = $timeTrackingGateway->newQueryCriteria()
            ->sortBy(['date'])
            ->filterBy('dateFrom', $dateFrom)
            ->filterBy('dateTo', $dateTo)
            ->filterBy('staff', $staffFilter)
            ->fromPOST();

        $timeEntries = $timeTrackingGateway->queryTimeEntries($detailCriteria, $gibbonSchoolYearID);

        if ($timeEntries->count() > 0) {
            $detailTable = DataTable::createPaginated('timeEntriesDetail', $detailCriteria);
            $detailTable->setTitle(__('Daily Time Entries'));

            $detailTable->addColumn('date', __('Date'))
                ->format(function ($row) {
                    return Format::date($row['date']);
                });

            $detailTable->addColumn('clockInTime', __('Clock In'))
                ->format(function ($row) {
                    if (empty($row['clockInTime'])) {
                        return '<span class="text-gray-400">-</span>';
                    }
                    return Format::time($row['clockInTime']);
                });

            $detailTable->addColumn('clockOutTime', __('Clock Out'))
                ->format(function ($row) {
                    if (empty($row['clockOutTime'])) {
                        return '<span class="text-green-600 text-sm">' . __('Still Working') . '</span>';
                    }
                    return Format::time($row['clockOutTime']);
                });

            $detailTable->addColumn('totalBreakMinutes', __('Break'))
                ->format(function ($row) {
                    $mins = intval($row['totalBreakMinutes']);
                    return $mins > 0 ? $mins . ' ' . __('min') : '-';
                });

            $detailTable->addColumn('totalWorkedMinutes', __('Hours Worked'))
                ->format(function ($row) {
                    $minutes = intval($row['totalWorkedMinutes']);
                    if ($minutes <= 0) {
                        return '-';
                    }
                    $hours = floor($minutes / 60);
                    $mins = $minutes % 60;
                    return $hours . 'h ' . $mins . 'm';
                });

            $detailTable->addColumn('overtime', __('Overtime'))
                ->format(function ($row) {
                    if ($row['overtime'] !== 'Y') {
                        return '-';
                    }
                    $minutes = intval($row['overtimeMinutes']);
                    $hours = floor($minutes / 60);
                    $mins = $minutes % 60;
                    $status = '';
                    if ($row['overtimeApproved'] === 'Y') {
                        $status = ' <span class="text-green-500 text-xs">(' . __('Approved') . ')</span>';
                    } elseif ($row['overtimeApproved'] === 'N') {
                        $status = ' <span class="text-red-500 text-xs">(' . __('Denied') . ')</span>';
                    } else {
                        $status = ' <span class="text-yellow-500 text-xs">(' . __('Pending') . ')</span>';
                    }
                    return '<span class="text-orange-500">' . $hours . 'h ' . $mins . 'm</span>' . $status;
                });

            $detailTable->addColumn('status', __('Status'))
                ->format(function ($row) {
                    $status = $row['status'] ?? 'Active';
                    $statusClasses = [
                        'Active' => 'bg-green-100 text-green-800',
                        'Completed' => 'bg-gray-100 text-gray-800',
                        'Cancelled' => 'bg-red-100 text-red-800',
                        'Adjusted' => 'bg-yellow-100 text-yellow-800',
                    ];
                    $class = $statusClasses[$status] ?? 'bg-gray-100 text-gray-800';
                    return '<span class="' . $class . ' text-xs px-2 py-1 rounded">' . __($status) . '</span>';
                });

            echo $detailTable->render($timeEntries);
        } else {
            echo '<div class="bg-gray-50 rounded-lg p-4 text-center text-gray-500">';
            echo __('No time entries found for this staff member in the selected period.');
            echo '</div>';
        }
    }

    // Overtime Pending Approval Section
    $pendingOvertime = $timeTrackingGateway->selectPendingOvertimeApproval($gibbonSchoolYearID);

    if ($pendingOvertime->rowCount() > 0) {
        echo '<h3 class="text-lg font-semibold mt-6 mb-3">' . __('Pending Overtime Approvals') . '</h3>';
        echo '<div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">';
        echo '<table class="w-full">';
        echo '<thead><tr class="text-left border-b">';
        echo '<th class="pb-2">' . __('Staff Member') . '</th>';
        echo '<th class="pb-2">' . __('Date') . '</th>';
        echo '<th class="pb-2">' . __('Hours Worked') . '</th>';
        echo '<th class="pb-2">' . __('Overtime') . '</th>';
        echo '</tr></thead>';
        echo '<tbody>';

        foreach ($pendingOvertime as $entry) {
            $name = Format::name('', $entry['preferredName'], $entry['surname'], 'Staff', true, true);
            $totalMinutes = intval($entry['totalWorkedMinutes']);
            $overtimeMinutes = intval($entry['overtimeMinutes']);
            $totalHours = floor($totalMinutes / 60);
            $totalMins = $totalMinutes % 60;
            $otHours = floor($overtimeMinutes / 60);
            $otMins = $overtimeMinutes % 60;

            echo '<tr class="border-b border-yellow-100">';
            echo '<td class="py-2">' . htmlspecialchars($name) . '</td>';
            echo '<td class="py-2">' . Format::date($entry['date']) . '</td>';
            echo '<td class="py-2">' . $totalHours . 'h ' . $totalMins . 'm</td>';
            echo '<td class="py-2"><span class="text-orange-500 font-semibold">' . $otHours . 'h ' . $otMins . 'm</span></td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
    }

    // Late Arrivals Section (only show if date range is within last 30 days)
    $thirtyDaysAgo = date('Y-m-d', strtotime('-30 days'));
    if ($dateFrom >= $thirtyDaysAgo) {
        $lateArrivals = $timeTrackingGateway->selectLateArrivals($gibbonSchoolYearID, $dateFrom, $dateTo);

        if ($lateArrivals->rowCount() > 0) {
            echo '<h3 class="text-lg font-semibold mt-6 mb-3">' . __('Late Arrivals') . '</h3>';
            echo '<div class="bg-orange-50 border border-orange-200 rounded-lg p-4">';
            echo '<table class="w-full">';
            echo '<thead><tr class="text-left border-b">';
            echo '<th class="pb-2">' . __('Staff Member') . '</th>';
            echo '<th class="pb-2">' . __('Date') . '</th>';
            echo '<th class="pb-2">' . __('Scheduled') . '</th>';
            echo '<th class="pb-2">' . __('Actual') . '</th>';
            echo '<th class="pb-2">' . __('Minutes Late') . '</th>';
            echo '</tr></thead>';
            echo '<tbody>';

            foreach ($lateArrivals as $entry) {
                $name = Format::name('', $entry['preferredName'], $entry['surname'], 'Staff', true, true);
                $minutesLate = intval($entry['minutesLate']);
                $lateClass = $minutesLate > 15 ? 'text-red-600 font-bold' : 'text-orange-500';

                echo '<tr class="border-b border-orange-100">';
                echo '<td class="py-2">' . htmlspecialchars($name) . '</td>';
                echo '<td class="py-2">' . Format::date($entry['date']) . '</td>';
                echo '<td class="py-2">' . Format::time($entry['scheduledStartTime']) . '</td>';
                echo '<td class="py-2">' . Format::time($entry['clockInTime']) . '</td>';
                echo '<td class="py-2"><span class="' . $lateClass . '">' . $minutesLate . ' ' . __('min') . '</span></td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
            echo '</div>';
        }
    }

    // Link back to Time Tracking and Dashboard
    echo '<div class="mt-6 flex gap-4">';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_timeTracking.php" class="text-blue-600 hover:underline">&larr; ' . __('Time Tracking') . '</a>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement.php" class="text-blue-600 hover:underline">' . __('Staff Dashboard') . '</a>';
    echo '</div>';
}
