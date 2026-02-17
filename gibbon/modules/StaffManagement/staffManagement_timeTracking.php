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
use Gibbon\Module\StaffManagement\Domain\AuditLogGateway;

// Module setup - breadcrumbs
$page->breadcrumbs->add(__('Staff Management'), 'staffManagement.php');
$page->breadcrumbs->add(__('Time Tracking'));

// Access check
if (!isActionAccessible($guid, $connection2, '/modules/StaffManagement/staffManagement_timeTracking.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get school year ID from session
    $gibbonSchoolYearID = $session->get('gibbonSchoolYearID');
    $gibbonPersonID = $session->get('gibbonPersonID');

    // Get date from request or default to today
    $date = $_GET['date'] ?? date('Y-m-d');

    // Validate date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $date = date('Y-m-d');
    }

    // Get gateways via DI container
    $timeTrackingGateway = $container->get(TimeTrackingGateway::class);
    $auditLogGateway = $container->get(AuditLogGateway::class);

    // Handle clock-in/out and break actions
    $action = $_POST['action'] ?? '';

    if (!empty($action)) {
        $time = date('H:i:s');
        $notes = $_POST['notes'] ?? null;
        $staffID = $_POST['gibbonPersonID'] ?? $gibbonPersonID;
        $timeEntryID = $_POST['gibbonStaffTimeEntryID'] ?? null;

        if ($action === 'clockIn') {
            // Clock in the staff member
            $result = $timeTrackingGateway->clockIn(
                $staffID,
                $gibbonSchoolYearID,
                $date,
                $time,
                'Manual',
                null,
                null,
                $notes
            );

            if ($result !== false) {
                // Check if late
                $isLate = $timeTrackingGateway->checkIfLate($staffID, $date, $time);
                if ($isLate) {
                    $page->addWarning(__('You have been clocked in, but you arrived late according to your schedule.'));
                } else {
                    $page->addSuccess(__('You have been clocked in successfully.'));
                }

                // Log the action
                $auditLogGateway->logInsert(
                    'gibbonStaffTimeEntry',
                    $result,
                    $gibbonPersonID,
                    $session->get('session')
                );
            } else {
                $page->addError(__('Failed to clock in. You may already be clocked in.'));
            }
        } elseif ($action === 'clockOut' && !empty($timeEntryID)) {
            // Clock out the staff member
            $result = $timeTrackingGateway->clockOut(
                $timeEntryID,
                $time,
                'Manual',
                null,
                $notes
            );

            if ($result !== false) {
                $page->addSuccess(__('You have been clocked out successfully.'));

                // Calculate overtime if applicable
                $timeTrackingGateway->calculateOvertime($timeEntryID);

                // Log the action
                $auditLogGateway->logUpdate(
                    'gibbonStaffTimeEntry',
                    $timeEntryID,
                    '{}',
                    json_encode(['clockOutTime' => $time]),
                    $gibbonPersonID,
                    $session->get('session')
                );
            } else {
                $page->addError(__('Failed to clock out.'));
            }
        } elseif ($action === 'startBreak' && !empty($timeEntryID)) {
            // Start break
            $result = $timeTrackingGateway->startBreak($timeEntryID, $time);

            if ($result !== false) {
                $page->addSuccess(__('Break started.'));
            } else {
                $page->addError(__('Failed to start break.'));
            }
        } elseif ($action === 'endBreak' && !empty($timeEntryID)) {
            // End break
            $result = $timeTrackingGateway->endBreak($timeEntryID, $time);

            if ($result !== false) {
                $page->addSuccess(__('Break ended.'));
            } else {
                $page->addError(__('Failed to end break.'));
            }
        }
    }

    // Get current user's active time entry (for personal status display)
    $activeEntry = $timeTrackingGateway->getActiveTimeEntry($gibbonPersonID);
    $isOnBreak = !empty($activeEntry) && !empty($activeEntry['breakStart']) && empty($activeEntry['breakEnd']);

    // Page header - Current Status Section
    echo '<h2>' . __('My Time Tracking Status') . '</h2>';

    // Display current status card
    echo '<div class="bg-white rounded-lg shadow p-6 mb-6">';

    if (empty($activeEntry)) {
        // Not clocked in
        echo '<div class="flex items-center justify-between">';
        echo '<div>';
        echo '<p class="text-lg font-semibold text-gray-700">' . __('Status') . ': <span class="text-red-600">' . __('Not Clocked In') . '</span></p>';
        echo '<p class="text-sm text-gray-500">' . __('Click the button to start your work day.') . '</p>';
        echo '</div>';
        echo '<form method="post" action="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_timeTracking.php&date=' . $date . '">';
        echo '<input type="hidden" name="action" value="clockIn">';
        echo '<input type="hidden" name="gibbonPersonID" value="' . $gibbonPersonID . '">';
        echo '<button type="submit" class="bg-green-500 hover:bg-green-600 text-white font-bold py-3 px-6 rounded-lg text-lg transition-colors">';
        echo __('Clock In');
        echo '</button>';
        echo '</form>';
        echo '</div>';
    } else {
        // Clocked in
        $clockInTime = Format::time($activeEntry['clockInTime']);
        $duration = time() - strtotime($activeEntry['date'] . ' ' . $activeEntry['clockInTime']);
        $hours = floor($duration / 3600);
        $minutes = floor(($duration % 3600) / 60);

        echo '<div class="grid grid-cols-1 md:grid-cols-3 gap-4">';

        // Status info
        echo '<div class="md:col-span-2">';
        if ($isOnBreak) {
            echo '<p class="text-lg font-semibold text-gray-700">' . __('Status') . ': <span class="text-orange-500">' . __('On Break') . '</span></p>';
            echo '<p class="text-sm text-gray-500">' . __('Break started at') . ': ' . Format::time($activeEntry['breakStart']) . '</p>';
        } else {
            echo '<p class="text-lg font-semibold text-gray-700">' . __('Status') . ': <span class="text-green-600">' . __('Clocked In') . '</span></p>';
        }
        echo '<p class="text-sm text-gray-500">' . __('Clocked in at') . ': ' . $clockInTime . '</p>';
        echo '<p class="text-sm text-gray-500">' . __('Working for') . ': ' . $hours . 'h ' . $minutes . 'm</p>';
        if (!empty($activeEntry['totalBreakMinutes']) && $activeEntry['totalBreakMinutes'] > 0) {
            echo '<p class="text-sm text-gray-500">' . __('Total break time') . ': ' . $activeEntry['totalBreakMinutes'] . ' ' . __('minutes') . '</p>';
        }
        echo '</div>';

        // Action buttons
        echo '<div class="flex flex-col gap-2">';
        echo '<form method="post" action="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_timeTracking.php&date=' . $date . '" class="mb-2">';
        echo '<input type="hidden" name="gibbonStaffTimeEntryID" value="' . $activeEntry['gibbonStaffTimeEntryID'] . '">';

        if ($isOnBreak) {
            echo '<input type="hidden" name="action" value="endBreak">';
            echo '<button type="submit" class="w-full bg-orange-500 hover:bg-orange-600 text-white font-bold py-2 px-4 rounded transition-colors">';
            echo __('End Break');
            echo '</button>';
        } else {
            echo '<input type="hidden" name="action" value="startBreak">';
            echo '<button type="submit" class="w-full bg-orange-500 hover:bg-orange-600 text-white font-bold py-2 px-4 rounded transition-colors">';
            echo __('Start Break');
            echo '</button>';
        }
        echo '</form>';

        if (!$isOnBreak) {
            echo '<form method="post" action="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_timeTracking.php&date=' . $date . '">';
            echo '<input type="hidden" name="action" value="clockOut">';
            echo '<input type="hidden" name="gibbonStaffTimeEntryID" value="' . $activeEntry['gibbonStaffTimeEntryID'] . '">';
            echo '<input type="text" name="notes" placeholder="' . __('Notes (optional)') . '" class="w-full text-sm border rounded px-2 py-1 mb-2">';
            echo '<button type="submit" class="w-full bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded transition-colors">';
            echo __('Clock Out');
            echo '</button>';
            echo '</form>';
        }
        echo '</div>';

        echo '</div>';
    }

    echo '</div>';

    // Date navigation form
    echo '<h2>' . __('Daily Time Entries') . '</h2>';

    $form = Form::create('dateFilter', $session->get('absoluteURL') . '/index.php');
    $form->setMethod('get');
    $form->addHiddenValue('q', '/modules/StaffManagement/staffManagement_timeTracking.php');

    $row = $form->addRow();
    $row->addLabel('date', __('Date'));
    $row->addDate('date')->setValue(Format::date($date))->required();

    $row = $form->addRow();
    $row->addSubmit(__('Go'));

    echo $form->getOutput();

    // Display formatted date
    echo '<p class="text-lg mb-4">' . __('Showing time entries for') . ': <strong>' . Format::date($date) . '</strong></p>';

    // Get daily summary statistics
    $summary = $timeTrackingGateway->getDailySummary($gibbonSchoolYearID, $date);

    // Display summary
    echo '<div class="bg-white rounded-lg shadow p-4 mb-4">';
    echo '<div class="grid grid-cols-2 md:grid-cols-5 gap-4 text-center">';
    echo '<div><span class="block text-2xl font-bold text-green-600">' . ($summary['currentlyClockedIn'] ?? 0) . '</span><span class="text-sm text-gray-600">' . __('Currently Working') . '</span></div>';
    echo '<div><span class="block text-2xl font-bold">' . ($summary['totalClockedIn'] ?? 0) . '</span><span class="text-sm text-gray-600">' . __('Total Clocked In') . '</span></div>';
    echo '<div><span class="block text-2xl font-bold">' . ($summary['totalClockedOut'] ?? 0) . '</span><span class="text-sm text-gray-600">' . __('Clocked Out') . '</span></div>';
    echo '<div><span class="block text-2xl font-bold text-orange-500">' . ($summary['onBreak'] ?? 0) . '</span><span class="text-sm text-gray-600">' . __('On Break') . '</span></div>';
    echo '<div><span class="block text-2xl font-bold text-purple-500">' . number_format(($summary['totalHoursWorked'] ?? 0), 1) . '</span><span class="text-sm text-gray-600">' . __('Total Hours') . '</span></div>';
    echo '</div>';
    echo '</div>';

    // Section: Staff Currently Clocked In
    echo '<h3 class="text-lg font-semibold mt-6 mb-3">' . __('Staff Currently Working') . '</h3>';

    $currentlyWorking = $timeTrackingGateway->selectStaffCurrentlyClockedIn($gibbonSchoolYearID, $date);

    if ($currentlyWorking->rowCount() > 0) {
        echo '<div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">';
        echo '<div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">';

        foreach ($currentlyWorking as $staff) {
            $staffName = Format::name('', $staff['preferredName'], $staff['surname'], 'Staff', false, true);
            $image = !empty($staff['image_240']) ? $staff['image_240'] : 'themes/Default/img/anonymous_240.jpg';
            $clockInTime = Format::time($staff['clockInTime']);
            $onBreak = !empty($staff['breakStart']) && empty($staff['breakEnd']);

            echo '<div class="bg-white rounded-lg shadow p-3 text-center hover:shadow-lg transition-shadow">';
            echo '<img src="' . $session->get('absoluteURL') . '/' . $image . '" class="w-16 h-16 rounded-full mx-auto mb-2 object-cover" alt="' . htmlspecialchars($staffName) . '">';
            echo '<p class="text-sm font-medium truncate">' . htmlspecialchars($staffName) . '</p>';
            if (!empty($staff['position'])) {
                echo '<p class="text-xs text-gray-400 truncate">' . htmlspecialchars($staff['position']) . '</p>';
            }
            echo '<p class="text-xs text-gray-500">' . __('In') . ': ' . $clockInTime . '</p>';
            if ($onBreak) {
                echo '<span class="inline-block mt-1 bg-orange-100 text-orange-800 text-xs px-2 py-1 rounded">' . __('On Break') . '</span>';
            } else {
                echo '<span class="inline-block mt-1 bg-green-100 text-green-800 text-xs px-2 py-1 rounded">' . __('Working') . '</span>';
            }
            echo '</div>';
        }

        echo '</div>';
        echo '</div>';
    } else {
        echo '<p class="text-gray-500 mb-4">' . __('No staff currently clocked in.') . '</p>';
    }

    // Section: Staff on Break
    $staffOnBreak = $timeTrackingGateway->selectStaffOnBreak($gibbonSchoolYearID, $date);

    if ($staffOnBreak->rowCount() > 0) {
        echo '<h3 class="text-lg font-semibold mt-6 mb-3">' . __('Staff on Break') . '</h3>';
        echo '<div class="bg-orange-50 border border-orange-200 rounded-lg p-4 mb-4">';
        echo '<div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">';

        foreach ($staffOnBreak as $staff) {
            $staffName = Format::name('', $staff['preferredName'], $staff['surname'], 'Staff', false, true);
            $image = !empty($staff['image_240']) ? $staff['image_240'] : 'themes/Default/img/anonymous_240.jpg';
            $breakStart = Format::time($staff['breakStart']);

            echo '<div class="bg-white rounded-lg shadow p-3 text-center hover:shadow-lg transition-shadow">';
            echo '<img src="' . $session->get('absoluteURL') . '/' . $image . '" class="w-16 h-16 rounded-full mx-auto mb-2 object-cover" alt="' . htmlspecialchars($staffName) . '">';
            echo '<p class="text-sm font-medium truncate">' . htmlspecialchars($staffName) . '</p>';
            echo '<p class="text-xs text-gray-500">' . __('Break started') . ': ' . $breakStart . '</p>';
            echo '</div>';
        }

        echo '</div>';
        echo '</div>';
    }

    // Section: Full Time Entry List for the Day
    echo '<h3 class="text-lg font-semibold mt-6 mb-3">' . __('Time Entry Records') . '</h3>';

    // Build query criteria
    $criteria = $timeTrackingGateway->newQueryCriteria()
        ->sortBy(['surname', 'preferredName'])
        ->fromPOST();

    // Get time entries for the date
    $timeEntries = $timeTrackingGateway->queryTimeEntriesByDate($criteria, $gibbonSchoolYearID, $date);

    // Build DataTable
    $table = DataTable::createPaginated('timeEntries', $criteria);
    $table->setTitle(__('Time Entry Records'));

    // Add columns
    $table->addColumn('image', __('Photo'))
        ->notSortable()
        ->format(function ($row) use ($session) {
            $image = !empty($row['image_240']) ? $row['image_240'] : 'themes/Default/img/anonymous_240.jpg';
            return '<img src="' . $session->get('absoluteURL') . '/' . $image . '" class="w-10 h-10 rounded-full object-cover" alt="">';
        });

    $table->addColumn('name', __('Name'))
        ->sortable(['surname', 'preferredName'])
        ->format(function ($row) {
            return Format::name('', $row['preferredName'], $row['surname'], 'Staff', true, true);
        });

    $table->addColumn('clockInTime', __('Clock In'))
        ->format(function ($row) {
            if (empty($row['clockInTime'])) {
                return '<span class="text-gray-400">-</span>';
            }
            $time = Format::time($row['clockInTime']);
            $method = !empty($row['clockInMethod']) ? ' <span class="text-gray-400 text-xs">(' . $row['clockInMethod'] . ')</span>' : '';
            return $time . $method;
        });

    $table->addColumn('clockOutTime', __('Clock Out'))
        ->format(function ($row) {
            if (empty($row['clockOutTime'])) {
                return '<span class="text-green-600 text-sm">' . __('Still Working') . '</span>';
            }
            return Format::time($row['clockOutTime']);
        });

    $table->addColumn('totalWorkedMinutes', __('Hours Worked'))
        ->notSortable()
        ->format(function ($row) {
            if (empty($row['clockInTime'])) {
                return '-';
            }
            if (!empty($row['totalWorkedMinutes'])) {
                $hours = floor($row['totalWorkedMinutes'] / 60);
                $minutes = $row['totalWorkedMinutes'] % 60;
                return $hours . 'h ' . $minutes . 'm';
            }
            // Calculate live if still working
            $checkIn = strtotime($row['clockInTime']);
            $checkOut = !empty($row['clockOutTime']) ? strtotime($row['clockOutTime']) : time();
            $duration = $checkOut - $checkIn;
            $hours = floor($duration / 3600);
            $minutes = floor(($duration % 3600) / 60);
            return $hours . 'h ' . $minutes . 'm';
        });

    $table->addColumn('totalBreakMinutes', __('Break'))
        ->notSortable()
        ->format(function ($row) {
            if (empty($row['totalBreakMinutes']) || $row['totalBreakMinutes'] == 0) {
                return '-';
            }
            return $row['totalBreakMinutes'] . ' ' . __('min');
        });

    $table->addColumn('overtime', __('Overtime'))
        ->notSortable()
        ->format(function ($row) {
            if ($row['overtime'] !== 'Y') {
                return '-';
            }
            $minutes = $row['overtimeMinutes'] ?? 0;
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
            return $hours . 'h ' . $mins . 'm' . $status;
        });

    $table->addColumn('status', __('Status'))
        ->notSortable()
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

    $table->addColumn('notes', __('Notes'))
        ->format(function ($row) {
            if (empty($row['notes'])) {
                return '-';
            }
            return '<span class="text-sm text-gray-600" title="' . htmlspecialchars($row['notes']) . '">' .
                   htmlspecialchars(substr($row['notes'], 0, 30)) .
                   (strlen($row['notes']) > 30 ? '...' : '') . '</span>';
        });

    // Output table
    if ($timeEntries->count() > 0) {
        echo $table->render($timeEntries);
    } else {
        echo '<div class="bg-gray-50 rounded-lg p-4 text-center text-gray-500">';
        echo __('No time entries found for this date.');
        echo '</div>';
    }

    // Link back to dashboard
    echo '<div class="mt-4">';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement.php" class="text-blue-600 hover:underline">&larr; ' . __('Back to Staff Management') . '</a>';
    echo '</div>';
}
