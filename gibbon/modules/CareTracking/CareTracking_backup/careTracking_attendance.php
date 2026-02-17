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
use Gibbon\Module\CareTracking\Domain\AttendanceGateway;

// Module setup - breadcrumbs
$page->breadcrumbs->add(__('Care Tracking'), 'careTracking.php');
$page->breadcrumbs->add(__('Attendance'));

// Access check
if (!isActionAccessible($guid, $connection2, '/modules/CareTracking/careTracking_attendance.php')) {
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

    // Get gateway via DI container
    $attendanceGateway = $container->get(AttendanceGateway::class);

    // Handle check-in/check-out actions
    $action = $_POST['action'] ?? '';
    $childID = $_POST['gibbonPersonID'] ?? null;

    if (!empty($action) && !empty($childID)) {
        $time = date('H:i:s');
        $lateArrival = $_POST['lateArrival'] ?? 'N';
        $notes = $_POST['notes'] ?? null;

        if ($action === 'checkIn') {
            $result = $attendanceGateway->checkIn(
                $childID,
                $gibbonSchoolYearID,
                $date,
                $time,
                $gibbonPersonID,
                $lateArrival === 'Y',
                $notes
            );

            if ($result !== false) {
                $page->addSuccess(__('Child has been checked in successfully.'));
            } else {
                $page->addError(__('Failed to check in child.'));
            }
        } elseif ($action === 'checkOut') {
            $attendanceID = $_POST['gibbonCareAttendanceID'] ?? null;
            $earlyDeparture = $_POST['earlyDeparture'] ?? 'N';
            $pickupPersonName = $_POST['pickupPersonName'] ?? null;

            if (!empty($attendanceID)) {
                $result = $attendanceGateway->checkOut(
                    $attendanceID,
                    $time,
                    $gibbonPersonID,
                    $earlyDeparture === 'Y',
                    $pickupPersonName,
                    null,
                    !empty($pickupPersonName),
                    $notes
                );

                if ($result !== false) {
                    $page->addSuccess(__('Child has been checked out successfully.'));
                } else {
                    $page->addError(__('Failed to check out child.'));
                }
            }
        }
    }

    // Page header with date selector
    echo '<h2>' . __('Daily Attendance') . '</h2>';

    // Date navigation form
    $form = Form::create('dateFilter', $session->get('absoluteURL') . '/index.php');
    $form->setMethod('get');
    $form->addHiddenValue('q', '/modules/CareTracking/careTracking_attendance.php');

    $row = $form->addRow();
    $row->addLabel('date', __('Date'));
    $row->addDate('date')->setValue(Format::date($date))->required();

    $row = $form->addRow();
    $row->addSubmit(__('Go'));

    echo $form->getOutput();

    // Display formatted date
    echo '<p class="text-lg mb-4">' . __('Showing attendance for') . ': <strong>' . Format::date($date) . '</strong></p>';

    // Get summary statistics
    $summary = $attendanceGateway->getAttendanceSummaryByDate($gibbonSchoolYearID, $date);

    // Display summary
    echo '<div class="bg-white rounded-lg shadow p-4 mb-4">';
    echo '<div class="grid grid-cols-2 md:grid-cols-5 gap-4 text-center">';
    echo '<div><span class="block text-2xl font-bold text-green-600">' . ($summary['currentlyPresent'] ?? 0) . '</span><span class="text-sm text-gray-600">' . __('Currently Present') . '</span></div>';
    echo '<div><span class="block text-2xl font-bold">' . ($summary['totalCheckedIn'] ?? 0) . '</span><span class="text-sm text-gray-600">' . __('Checked In') . '</span></div>';
    echo '<div><span class="block text-2xl font-bold">' . ($summary['totalCheckedOut'] ?? 0) . '</span><span class="text-sm text-gray-600">' . __('Checked Out') . '</span></div>';
    echo '<div><span class="block text-2xl font-bold text-orange-500">' . ($summary['totalLateArrivals'] ?? 0) . '</span><span class="text-sm text-gray-600">' . __('Late Arrivals') . '</span></div>';
    echo '<div><span class="block text-2xl font-bold text-red-500">' . ($summary['totalAbsent'] ?? 0) . '</span><span class="text-sm text-gray-600">' . __('Absent') . '</span></div>';
    echo '</div>';
    echo '</div>';

    // Section: Children Not Yet Checked In
    echo '<h3 class="text-lg font-semibold mt-6 mb-3">' . __('Awaiting Check-In') . '</h3>';

    $notCheckedIn = $attendanceGateway->selectChildrenNotCheckedIn($gibbonSchoolYearID, $date);

    if ($notCheckedIn->rowCount() > 0) {
        echo '<div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">';
        echo '<form method="post" action="' . $session->get('absoluteURL') . '/index.php?q=/modules/CareTracking/careTracking_attendance.php&date=' . $date . '">';
        echo '<input type="hidden" name="action" value="checkIn">';

        echo '<div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">';
        foreach ($notCheckedIn as $child) {
            $childName = Format::name('', $child['preferredName'], $child['surname'], 'Student', false, true);
            $image = !empty($child['image_240']) ? $child['image_240'] : 'themes/Default/img/anonymous_240.jpg';

            echo '<div class="bg-white rounded-lg shadow p-3 text-center hover:shadow-lg transition-shadow">';
            echo '<img src="' . $session->get('absoluteURL') . '/' . $image . '" class="w-16 h-16 rounded-full mx-auto mb-2 object-cover" alt="' . htmlspecialchars($childName) . '">';
            echo '<p class="text-sm font-medium truncate">' . htmlspecialchars($childName) . '</p>';
            echo '<button type="submit" name="gibbonPersonID" value="' . $child['gibbonPersonID'] . '" class="mt-2 bg-green-500 text-white text-xs px-3 py-1 rounded hover:bg-green-600">' . __('Check In') . '</button>';
            echo '</div>';
        }
        echo '</div>';

        // Additional check-in options
        echo '<div class="mt-4 flex gap-4">';
        echo '<label class="flex items-center text-sm">';
        echo '<input type="checkbox" name="lateArrival" value="Y" class="mr-2">';
        echo __('Mark as Late Arrival');
        echo '</label>';
        echo '<input type="text" name="notes" placeholder="' . __('Notes (optional)') . '" class="text-sm border rounded px-2 py-1 flex-1">';
        echo '</div>';

        echo '</form>';
        echo '</div>';
    } else {
        echo '<p class="text-gray-500 mb-4">' . __('All children have been checked in.') . '</p>';
    }

    // Section: Children Currently Checked In (Ready for Check-Out)
    echo '<h3 class="text-lg font-semibold mt-6 mb-3">' . __('Currently Checked In') . '</h3>';

    $currentlyCheckedIn = $attendanceGateway->selectChildrenCurrentlyCheckedIn($gibbonSchoolYearID, $date);

    if ($currentlyCheckedIn->rowCount() > 0) {
        echo '<div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">';
        echo '<form method="post" action="' . $session->get('absoluteURL') . '/index.php?q=/modules/CareTracking/careTracking_attendance.php&date=' . $date . '">';
        echo '<input type="hidden" name="action" value="checkOut">';

        echo '<div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">';
        foreach ($currentlyCheckedIn as $child) {
            $childName = Format::name('', $child['preferredName'], $child['surname'], 'Student', false, true);
            $image = !empty($child['image_240']) ? $child['image_240'] : 'themes/Default/img/anonymous_240.jpg';
            $checkInTime = Format::time($child['checkInTime']);

            echo '<div class="bg-white rounded-lg shadow p-3 text-center hover:shadow-lg transition-shadow">';
            echo '<img src="' . $session->get('absoluteURL') . '/' . $image . '" class="w-16 h-16 rounded-full mx-auto mb-2 object-cover" alt="' . htmlspecialchars($childName) . '">';
            echo '<p class="text-sm font-medium truncate">' . htmlspecialchars($childName) . '</p>';
            echo '<p class="text-xs text-gray-500">' . __('In') . ': ' . $checkInTime . '</p>';
            echo '<input type="hidden" name="gibbonCareAttendanceID" value="' . $child['gibbonCareAttendanceID'] . '">';
            echo '<button type="submit" name="gibbonPersonID" value="' . $child['gibbonPersonID'] . '" class="mt-2 bg-blue-500 text-white text-xs px-3 py-1 rounded hover:bg-blue-600">' . __('Check Out') . '</button>';
            echo '</div>';
        }
        echo '</div>';

        // Additional check-out options
        echo '<div class="mt-4 flex gap-4 flex-wrap">';
        echo '<label class="flex items-center text-sm">';
        echo '<input type="checkbox" name="earlyDeparture" value="Y" class="mr-2">';
        echo __('Early Departure');
        echo '</label>';
        echo '<input type="text" name="pickupPersonName" placeholder="' . __('Pickup Person Name') . '" class="text-sm border rounded px-2 py-1">';
        echo '<input type="text" name="notes" placeholder="' . __('Notes (optional)') . '" class="text-sm border rounded px-2 py-1 flex-1">';
        echo '</div>';

        echo '</form>';
        echo '</div>';
    } else {
        echo '<p class="text-gray-500 mb-4">' . __('No children are currently checked in.') . '</p>';
    }

    // Section: Full Attendance List for the Day
    echo '<h3 class="text-lg font-semibold mt-6 mb-3">' . __('Attendance Records') . '</h3>';

    // Build query criteria
    $criteria = $attendanceGateway->newQueryCriteria()
        ->sortBy(['surname', 'preferredName'])
        ->fromPOST();

    // Get attendance data for the date
    $attendance = $attendanceGateway->queryAttendanceByDate($criteria, $gibbonSchoolYearID, $date);

    // Build DataTable
    $table = DataTable::createPaginated('attendance', $criteria);
    $table->setTitle(__('Attendance Records'));

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
            return Format::name('', $row['preferredName'], $row['surname'], 'Student', true, true);
        });

    $table->addColumn('checkInTime', __('Check In'))
        ->format(function ($row) {
            if (empty($row['checkInTime'])) {
                return '<span class="text-gray-400">-</span>';
            }
            $time = Format::time($row['checkInTime']);
            $late = $row['lateArrival'] === 'Y' ? ' <span class="text-orange-500 text-xs">(' . __('Late') . ')</span>' : '';
            return $time . $late;
        });

    $table->addColumn('checkOutTime', __('Check Out'))
        ->format(function ($row) {
            if (empty($row['checkOutTime'])) {
                return '<span class="text-green-600 text-sm">' . __('Still Present') . '</span>';
            }
            $time = Format::time($row['checkOutTime']);
            $early = $row['earlyDeparture'] === 'Y' ? ' <span class="text-orange-500 text-xs">(' . __('Early') . ')</span>' : '';
            return $time . $early;
        });

    $table->addColumn('duration', __('Duration'))
        ->notSortable()
        ->format(function ($row) {
            if (empty($row['checkInTime'])) {
                return '-';
            }
            $checkIn = strtotime($row['checkInTime']);
            $checkOut = !empty($row['checkOutTime']) ? strtotime($row['checkOutTime']) : time();
            $duration = $checkOut - $checkIn;
            $hours = floor($duration / 3600);
            $minutes = floor(($duration % 3600) / 60);
            return $hours . 'h ' . $minutes . 'm';
        });

    $table->addColumn('status', __('Status'))
        ->notSortable()
        ->format(function ($row) {
            if (!empty($row['absenceReason'])) {
                return '<span class="bg-red-100 text-red-800 text-xs px-2 py-1 rounded">' . __('Absent') . '</span>';
            }
            if (empty($row['checkInTime'])) {
                return '<span class="bg-yellow-100 text-yellow-800 text-xs px-2 py-1 rounded">' . __('Not Arrived') . '</span>';
            }
            if (empty($row['checkOutTime'])) {
                return '<span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded">' . __('Present') . '</span>';
            }
            return '<span class="bg-gray-100 text-gray-800 text-xs px-2 py-1 rounded">' . __('Left') . '</span>';
        });

    $table->addColumn('pickup', __('Pickup'))
        ->format(function ($row) {
            if (empty($row['pickupPersonName'])) {
                return '-';
            }
            $verified = $row['pickupVerified'] === 'Y' ? ' <span class="text-green-500">✓</span>' : '';
            return htmlspecialchars($row['pickupPersonName']) . $verified;
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
    if ($attendance->count() > 0) {
        echo $table->render($attendance);
    } else {
        echo '<div class="bg-gray-50 rounded-lg p-4 text-center text-gray-500">';
        echo __('No attendance records found for this date.');
        echo '</div>';
    }

    // Link back to dashboard
    echo '<div class="mt-4">';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/CareTracking/careTracking.php&date=' . $date . '" class="text-blue-600 hover:underline">&larr; ' . __('Back to Dashboard') . '</a>';
    echo '</div>';
}
