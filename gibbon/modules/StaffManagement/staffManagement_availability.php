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
use Gibbon\Module\StaffManagement\Domain\ScheduleGateway;
use Gibbon\Module\StaffManagement\Domain\StaffProfileGateway;
use Gibbon\Module\StaffManagement\Domain\AuditLogGateway;

// Module setup - breadcrumbs
$page->breadcrumbs
    ->add(__('Staff Management'), 'staffManagement.php')
    ->add(__('Staff Availability'));

// Access check
if (!isActionAccessible($guid, $connection2, '/modules/StaffManagement/staffManagement_availability.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get session info
    $gibbonSchoolYearID = $session->get('gibbonSchoolYearID');
    $gibbonPersonID = $session->get('gibbonPersonID');

    // Get gateways via DI container
    $scheduleGateway = $container->get(ScheduleGateway::class);
    $staffProfileGateway = $container->get(StaffProfileGateway::class);
    $auditLogGateway = $container->get(AuditLogGateway::class);

    // Days of the week for availability tracking
    $daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    // Get selected staff member from request
    $selectedStaffID = $_GET['gibbonPersonID'] ?? $_POST['gibbonPersonID'] ?? '';

    // Handle form submissions (add/edit/delete availability)
    $mode = $_POST['mode'] ?? '';
    $success = false;

    if ($mode === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($selectedStaffID)) {
        // Save weekly availability for all days
        $effectiveFrom = !empty($_POST['effectiveFrom']) ? Format::dateConvert($_POST['effectiveFrom']) : null;
        $effectiveTo = !empty($_POST['effectiveTo']) ? Format::dateConvert($_POST['effectiveTo']) : null;

        $savedCount = 0;
        $errorCount = 0;

        foreach ($daysOfWeek as $day) {
            $isAvailable = isset($_POST['available_' . $day]) ? 'Y' : 'N';
            $availableFrom = !empty($_POST['from_' . $day]) ? $_POST['from_' . $day] . ':00' : null;
            $availableTo = !empty($_POST['to_' . $day]) ? $_POST['to_' . $day] . ':00' : null;
            $preferredHours = !empty($_POST['preferred_' . $day]) ? floatval($_POST['preferred_' . $day]) : null;
            $maxHours = !empty($_POST['max_' . $day]) ? floatval($_POST['max_' . $day]) : null;
            $notes = !empty($_POST['notes_' . $day]) ? trim($_POST['notes_' . $day]) : null;

            $data = [
                'isAvailable' => $isAvailable,
                'availableFrom' => $availableFrom,
                'availableTo' => $availableTo,
                'preferredHours' => $preferredHours,
                'maxHours' => $maxHours,
                'notes' => $notes,
                'effectiveFrom' => $effectiveFrom,
                'effectiveTo' => $effectiveTo,
            ];

            $result = $scheduleGateway->upsertAvailability($selectedStaffID, $day, $data);
            if ($result !== false) {
                $savedCount++;
            } else {
                $errorCount++;
            }
        }

        // Log the change
        $auditLogGateway->logChange(
            'gibbonStaffAvailability',
            null,
            $gibbonPersonID,
            'Bulk Update',
            [],
            ['gibbonPersonID' => $selectedStaffID, 'effectiveFrom' => $effectiveFrom]
        );

        if ($errorCount === 0) {
            $page->addSuccess(__('Availability updated successfully for all days.'));
            $success = true;
        } else {
            $page->addWarning(__('Some availability entries could not be saved.') . ' (' . $errorCount . ' ' . __('errors') . ')');
        }
    } elseif ($mode === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        // Delete a specific availability record
        $gibbonStaffAvailabilityID = $_POST['gibbonStaffAvailabilityID'] ?? '';
        if (!empty($gibbonStaffAvailabilityID)) {
            $deleted = $scheduleGateway->deleteAvailability($gibbonStaffAvailabilityID);
            if ($deleted) {
                $auditLogGateway->logDelete('gibbonStaffAvailability', $gibbonStaffAvailabilityID, $gibbonPersonID, []);
                $page->addSuccess(__('Availability record deleted successfully.'));
                $success = true;
            } else {
                $page->addError(__('Failed to delete availability record.'));
            }
        }
    }

    // Page header
    echo '<h2>' . __('Staff Availability') . '</h2>';
    echo '<p class="text-gray-600 mb-4">' . __('Manage staff availability by day of the week. Set available hours, preferred working times, and notes for scheduling purposes.') . '</p>';

    // Staff selector form
    $staffList = $staffProfileGateway->selectActiveStaffList()->fetchKeyPair();

    $form = Form::create('staffSelector', $session->get('absoluteURL') . '/index.php');
    $form->setMethod('GET');
    $form->addHiddenValue('q', '/modules/StaffManagement/staffManagement_availability.php');

    $row = $form->addRow();
    $row->addLabel('gibbonPersonID', __('Select Staff Member'));
    $row->addSelect('gibbonPersonID')
        ->fromArray($staffList)
        ->placeholder(__('Please select...'))
        ->selected($selectedStaffID)
        ->required();

    $row = $form->addRow();
    $row->addSubmit(__('View Availability'));

    echo $form->getOutput();

    // Show availability editor if staff member selected
    if (!empty($selectedStaffID)) {
        // Get staff profile info
        $staffProfile = $staffProfileGateway->getStaffProfileByPersonID($selectedStaffID);
        $staffName = !empty($staffProfile) ? Format::name('', $staffProfile['preferredName'], $staffProfile['surname'], 'Staff', false, true) : __('Staff Member');

        echo '<hr class="my-6">';
        echo '<h3 class="text-lg font-semibold mb-2">' . __('Weekly Availability for') . ': ' . htmlspecialchars($staffName) . '</h3>';

        // Get current availability data
        $currentAvailability = $scheduleGateway->getCurrentAvailability($selectedStaffID);

        // Convert to associative array by day
        $availabilityByDay = [];
        foreach ($currentAvailability as $record) {
            $availabilityByDay[$record['dayOfWeek']] = $record;
        }

        // Check for scheduling conflicts this week
        $weekStart = date('Y-m-d', strtotime('monday this week'));
        $weekEnd = date('Y-m-d', strtotime('sunday this week'));

        // Get scheduled shifts for conflict detection
        $criteria = $scheduleGateway->newQueryCriteria();
        $scheduledShifts = $scheduleGateway->querySchedulesByDateRange($criteria, $gibbonSchoolYearID, $weekStart, $weekEnd);

        // Filter to this staff member's shifts
        $staffShifts = [];
        foreach ($scheduledShifts as $shift) {
            if ($shift['gibbonPersonID'] == $selectedStaffID) {
                $dayOfWeek = date('l', strtotime($shift['date']));
                if (!isset($staffShifts[$dayOfWeek])) {
                    $staffShifts[$dayOfWeek] = [];
                }
                $staffShifts[$dayOfWeek][] = $shift;
            }
        }

        // Get upcoming leave
        $leaveOnDate = [];
        foreach ($daysOfWeek as $day) {
            $date = date('Y-m-d', strtotime($day . ' this week'));
            $leave = $scheduleGateway->getLeaveByDate($selectedStaffID, $date);
            if ($leave) {
                $leaveOnDate[$day] = $leave;
            }
        }

        // Summary stats
        $availableDays = count(array_filter($availabilityByDay, function($a) { return $a['isAvailable'] === 'Y'; }));
        $totalPreferredHours = array_sum(array_map(function($a) { return floatval($a['preferredHours'] ?? 0); }, $availabilityByDay));
        $totalMaxHours = array_sum(array_map(function($a) { return floatval($a['maxHours'] ?? 0); }, $availabilityByDay));

        // Display summary
        echo '<div class="bg-white rounded-lg shadow p-4 mb-4">';
        echo '<div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">';
        echo '<div><span class="block text-2xl font-bold text-green-600">' . $availableDays . '</span><span class="text-sm text-gray-600">' . __('Available Days') . '</span></div>';
        echo '<div><span class="block text-2xl font-bold">' . (7 - $availableDays) . '</span><span class="text-sm text-gray-600">' . __('Unavailable Days') . '</span></div>';
        echo '<div><span class="block text-2xl font-bold text-blue-600">' . number_format($totalPreferredHours, 1) . 'h</span><span class="text-sm text-gray-600">' . __('Preferred Hours/Week') . '</span></div>';
        echo '<div><span class="block text-2xl font-bold text-purple-600">' . number_format($totalMaxHours, 1) . 'h</span><span class="text-sm text-gray-600">' . __('Max Hours/Week') . '</span></div>';
        echo '</div>';
        echo '</div>';

        // Availability Form
        echo '<form method="POST" action="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_availability.php&gibbonPersonID=' . $selectedStaffID . '">';
        echo '<input type="hidden" name="mode" value="save">';
        echo '<input type="hidden" name="gibbonPersonID" value="' . $selectedStaffID . '">';

        // Effective date range
        echo '<div class="bg-gray-50 rounded-lg p-4 mb-4">';
        echo '<h4 class="font-semibold mb-2">' . __('Effective Date Range') . ' <span class="text-sm font-normal text-gray-500">(' . __('optional') . ')</span></h4>';
        echo '<div class="grid grid-cols-1 md:grid-cols-2 gap-4">';
        echo '<div>';
        echo '<label class="block text-sm font-medium mb-1">' . __('Effective From') . '</label>';

        // Get effective dates from existing records
        $existingEffectiveFrom = null;
        $existingEffectiveTo = null;
        foreach ($availabilityByDay as $avail) {
            if (!empty($avail['effectiveFrom'])) {
                $existingEffectiveFrom = $avail['effectiveFrom'];
            }
            if (!empty($avail['effectiveTo'])) {
                $existingEffectiveTo = $avail['effectiveTo'];
            }
            break; // Only need to check one
        }

        echo '<input type="date" name="effectiveFrom" class="w-full border rounded px-3 py-2" value="' . ($existingEffectiveFrom ?? '') . '">';
        echo '<p class="text-xs text-gray-500 mt-1">' . __('Leave blank for immediate effect') . '</p>';
        echo '</div>';
        echo '<div>';
        echo '<label class="block text-sm font-medium mb-1">' . __('Effective Until') . '</label>';
        echo '<input type="date" name="effectiveTo" class="w-full border rounded px-3 py-2" value="' . ($existingEffectiveTo ?? '') . '">';
        echo '<p class="text-xs text-gray-500 mt-1">' . __('Leave blank for no end date') . '</p>';
        echo '</div>';
        echo '</div>';
        echo '</div>';

        // Weekly availability grid
        echo '<div class="overflow-x-auto">';
        echo '<table class="w-full border-collapse bg-white rounded-lg shadow">';
        echo '<thead>';
        echo '<tr class="bg-gray-100">';
        echo '<th class="border px-3 py-2 text-left">' . __('Day') . '</th>';
        echo '<th class="border px-3 py-2 text-center">' . __('Available') . '</th>';
        echo '<th class="border px-3 py-2 text-center">' . __('From') . '</th>';
        echo '<th class="border px-3 py-2 text-center">' . __('To') . '</th>';
        echo '<th class="border px-3 py-2 text-center">' . __('Preferred Hours') . '</th>';
        echo '<th class="border px-3 py-2 text-center">' . __('Max Hours') . '</th>';
        echo '<th class="border px-3 py-2">' . __('Notes') . '</th>';
        echo '<th class="border px-3 py-2 text-center">' . __('Status') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        foreach ($daysOfWeek as $day) {
            $dayData = $availabilityByDay[$day] ?? [];
            $isAvailable = ($dayData['isAvailable'] ?? 'Y') === 'Y';
            $availableFrom = $dayData['availableFrom'] ?? '';
            $availableTo = $dayData['availableTo'] ?? '';
            $preferredHours = $dayData['preferredHours'] ?? '';
            $maxHours = $dayData['maxHours'] ?? '';
            $notes = $dayData['notes'] ?? '';

            // Check for conflicts and leave
            $hasLeave = isset($leaveOnDate[$day]);
            $hasShifts = isset($staffShifts[$day]) && count($staffShifts[$day]) > 0;
            $conflicts = [];

            if ($hasShifts && !$isAvailable) {
                $conflicts[] = __('Scheduled but marked unavailable');
            }
            if ($hasLeave) {
                $conflicts[] = __('On leave') . ': ' . ucfirst($leaveOnDate[$day]['leaveType']);
            }

            // Row background color
            $rowClass = '';
            if ($hasLeave) {
                $rowClass = 'bg-orange-50';
            } elseif (!$isAvailable) {
                $rowClass = 'bg-red-50';
            } elseif (!empty($conflicts)) {
                $rowClass = 'bg-yellow-50';
            }

            echo '<tr class="' . $rowClass . '">';

            // Day name
            echo '<td class="border px-3 py-2 font-medium">';
            echo __($day);
            if ($hasShifts) {
                echo ' <span class="text-xs text-blue-600">(' . count($staffShifts[$day]) . ' ' . __('shifts') . ')</span>';
            }
            echo '</td>';

            // Available checkbox
            echo '<td class="border px-3 py-2 text-center">';
            echo '<input type="checkbox" name="available_' . $day . '" class="w-5 h-5 cursor-pointer availability-toggle" data-day="' . $day . '"' . ($isAvailable ? ' checked' : '') . '>';
            echo '</td>';

            // From time
            echo '<td class="border px-3 py-2">';
            echo '<input type="time" name="from_' . $day . '" class="w-full border rounded px-2 py-1 time-input-' . $day . '" value="' . substr($availableFrom, 0, 5) . '"' . (!$isAvailable ? ' disabled' : '') . '>';
            echo '</td>';

            // To time
            echo '<td class="border px-3 py-2">';
            echo '<input type="time" name="to_' . $day . '" class="w-full border rounded px-2 py-1 time-input-' . $day . '" value="' . substr($availableTo, 0, 5) . '"' . (!$isAvailable ? ' disabled' : '') . '>';
            echo '</td>';

            // Preferred hours
            echo '<td class="border px-3 py-2">';
            echo '<input type="number" name="preferred_' . $day . '" class="w-full border rounded px-2 py-1 hours-input-' . $day . '" value="' . $preferredHours . '" step="0.5" min="0" max="24"' . (!$isAvailable ? ' disabled' : '') . '>';
            echo '</td>';

            // Max hours
            echo '<td class="border px-3 py-2">';
            echo '<input type="number" name="max_' . $day . '" class="w-full border rounded px-2 py-1 hours-input-' . $day . '" value="' . $maxHours . '" step="0.5" min="0" max="24"' . (!$isAvailable ? ' disabled' : '') . '>';
            echo '</td>';

            // Notes
            echo '<td class="border px-3 py-2">';
            echo '<input type="text" name="notes_' . $day . '" class="w-full border rounded px-2 py-1" value="' . htmlspecialchars($notes) . '" placeholder="' . __('Optional notes...') . '">';
            echo '</td>';

            // Status indicators
            echo '<td class="border px-3 py-2 text-center">';
            if ($hasLeave) {
                echo '<span class="bg-orange-100 text-orange-800 text-xs px-2 py-1 rounded">' . __('On Leave') . '</span>';
            } elseif (!empty($conflicts)) {
                echo '<span class="bg-yellow-100 text-yellow-800 text-xs px-2 py-1 rounded" title="' . htmlspecialchars(implode(', ', $conflicts)) . '">' . __('Conflict') . '</span>';
            } elseif ($isAvailable) {
                echo '<span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded">' . __('Available') . '</span>';
            } else {
                echo '<span class="bg-red-100 text-red-800 text-xs px-2 py-1 rounded">' . __('Unavailable') . '</span>';
            }
            echo '</td>';

            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
        echo '</div>';

        // Quick action buttons
        echo '<div class="flex flex-wrap gap-2 my-4">';
        echo '<button type="button" onclick="setAllAvailable(true)" class="px-3 py-1 text-sm border rounded hover:bg-gray-100">' . __('Mark All Available') . '</button>';
        echo '<button type="button" onclick="setAllAvailable(false)" class="px-3 py-1 text-sm border rounded hover:bg-gray-100">' . __('Mark All Unavailable') . '</button>';
        echo '<button type="button" onclick="setWeekdaysOnly()" class="px-3 py-1 text-sm border rounded hover:bg-gray-100">' . __('Weekdays Only') . '</button>';
        echo '<button type="button" onclick="setStandardHours()" class="px-3 py-1 text-sm border rounded hover:bg-gray-100">' . __('Apply Standard Hours (8:00-17:00)') . '</button>';
        echo '</div>';

        // Submit button
        echo '<div class="flex justify-end gap-2 mt-4">';
        echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_schedule.php" class="px-4 py-2 border rounded hover:bg-gray-100">' . __('Cancel') . '</a>';
        echo '<button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">' . __('Save Availability') . '</button>';
        echo '</div>';

        echo '</form>';

        // Conflict Detection Section
        echo '<hr class="my-6">';
        echo '<h3 class="text-lg font-semibold mb-4">' . __('Schedule Conflict Detection') . '</h3>';

        // Get all scheduled shifts for this staff member
        $checkDate = $_GET['checkDate'] ?? date('Y-m-d');
        $checkDateFormatted = Format::date($checkDate);

        // Date selector for conflict check
        echo '<div class="bg-gray-50 rounded-lg p-4 mb-4">';
        echo '<form method="GET" action="' . $session->get('absoluteURL') . '/index.php" class="flex items-center gap-4">';
        echo '<input type="hidden" name="q" value="/modules/StaffManagement/staffManagement_availability.php">';
        echo '<input type="hidden" name="gibbonPersonID" value="' . $selectedStaffID . '">';
        echo '<label class="font-medium">' . __('Check Conflicts for Date') . ':</label>';
        echo '<input type="date" name="checkDate" class="border rounded px-3 py-2" value="' . $checkDate . '">';
        echo '<button type="submit" class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300">' . __('Check') . '</button>';
        echo '</form>';
        echo '</div>';

        // Find conflicts for the selected date
        $dayOfWeek = date('l', strtotime($checkDate));
        $availabilityForDay = $scheduleGateway->getAvailabilityByDay($selectedStaffID, $dayOfWeek, $checkDate);

        echo '<div class="bg-white rounded-lg shadow p-4 mb-4">';
        echo '<h4 class="font-semibold mb-2">' . __('Availability on') . ' ' . $checkDateFormatted . ' (' . __($dayOfWeek) . ')</h4>';

        if (empty($availabilityForDay) || $availabilityForDay['isAvailable'] === 'Y') {
            $availFrom = !empty($availabilityForDay['availableFrom']) ? Format::time($availabilityForDay['availableFrom']) : __('Not set');
            $availTo = !empty($availabilityForDay['availableTo']) ? Format::time($availabilityForDay['availableTo']) : __('Not set');

            echo '<p class="text-green-600 mb-2">';
            echo '<span class="font-semibold">' . __('Available') . '</span>';
            if (!empty($availabilityForDay['availableFrom'])) {
                echo ' (' . $availFrom . ' - ' . $availTo . ')';
            }
            echo '</p>';
        } else {
            echo '<p class="text-red-600 mb-2"><span class="font-semibold">' . __('Not Available') . '</span></p>';
        }

        // Check for leave on this date
        $leaveForDate = $scheduleGateway->getLeaveByDate($selectedStaffID, $checkDate);
        if ($leaveForDate) {
            echo '<div class="bg-orange-100 border border-orange-300 rounded p-3 mb-2">';
            echo '<span class="font-semibold text-orange-800">' . __('Leave Booked') . ':</span> ';
            echo htmlspecialchars(ucfirst($leaveForDate['leaveType']));
            if (!empty($leaveForDate['reason'])) {
                echo ' - ' . htmlspecialchars($leaveForDate['reason']);
            }
            echo '</div>';
        }

        // Get any scheduled shifts for this date
        $existingSchedule = $scheduleGateway->getScheduleByPersonAndDate($selectedStaffID, $checkDate);
        if (!empty($existingSchedule)) {
            echo '<div class="bg-blue-50 border border-blue-200 rounded p-3 mb-2">';
            echo '<span class="font-semibold text-blue-800">' . __('Scheduled Shift') . ':</span> ';
            echo Format::time($existingSchedule['startTime']) . ' - ' . Format::time($existingSchedule['endTime']);
            if (!empty($existingSchedule['roomAssignment'])) {
                echo ' (' . htmlspecialchars($existingSchedule['roomAssignment']) . ')';
            }

            // Check if this conflicts with availability
            if (!empty($availabilityForDay) && $availabilityForDay['isAvailable'] !== 'Y') {
                echo '<br><span class="text-red-600 font-semibold">' . __('CONFLICT: Staff member is marked as unavailable!') . '</span>';
            } elseif (!empty($availabilityForDay['availableFrom']) && !empty($availabilityForDay['availableTo'])) {
                if ($existingSchedule['startTime'] < $availabilityForDay['availableFrom'] ||
                    $existingSchedule['endTime'] > $availabilityForDay['availableTo']) {
                    echo '<br><span class="text-yellow-600 font-semibold">' . __('WARNING: Shift is outside available hours!') . '</span>';
                }
            }
            echo '</div>';
        }

        // Check for scheduling conflicts (overlapping shifts)
        if (!empty($existingSchedule)) {
            $conflicts = $scheduleGateway->findSchedulingConflicts(
                $selectedStaffID,
                $checkDate,
                $existingSchedule['startTime'],
                $existingSchedule['endTime'],
                $existingSchedule['gibbonStaffScheduleID']
            );

            if (!empty($conflicts)) {
                echo '<div class="bg-red-100 border border-red-300 rounded p-3">';
                echo '<span class="font-semibold text-red-800">' . __('Overlapping Shifts Detected') . ':</span>';
                echo '<ul class="list-disc list-inside mt-2">';
                foreach ($conflicts as $conflict) {
                    echo '<li>' . Format::time($conflict['startTime']) . ' - ' . Format::time($conflict['endTime']);
                    if (!empty($conflict['roomAssignment'])) {
                        echo ' (' . htmlspecialchars($conflict['roomAssignment']) . ')';
                    }
                    echo '</li>';
                }
                echo '</ul>';
                echo '</div>';
            }
        }

        echo '</div>';

        // Leave Summary
        echo '<h4 class="font-semibold mb-2">' . __('Upcoming Leave') . '</h4>';

        $leaveCriteria = $scheduleGateway->newQueryCriteria()
            ->filterBy('staff', $selectedStaffID)
            ->filterBy('status', 'Approved')
            ->filterBy('dateFrom', date('Y-m-d'))
            ->sortBy(['startDate'])
            ->pageSize(5);

        $upcomingLeave = $scheduleGateway->queryLeaveRequests($leaveCriteria, $gibbonSchoolYearID);

        if ($upcomingLeave->count() > 0) {
            echo '<div class="bg-white rounded-lg shadow overflow-hidden">';
            echo '<table class="w-full">';
            echo '<thead class="bg-gray-100">';
            echo '<tr>';
            echo '<th class="px-3 py-2 text-left">' . __('Type') . '</th>';
            echo '<th class="px-3 py-2 text-left">' . __('Dates') . '</th>';
            echo '<th class="px-3 py-2 text-left">' . __('Days') . '</th>';
            echo '<th class="px-3 py-2 text-left">' . __('Reason') . '</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';
            foreach ($upcomingLeave as $leave) {
                echo '<tr class="border-t">';
                echo '<td class="px-3 py-2">' . htmlspecialchars(ucfirst($leave['leaveType'])) . '</td>';
                echo '<td class="px-3 py-2">' . Format::date($leave['startDate']) . ' - ' . Format::date($leave['endDate']) . '</td>';
                echo '<td class="px-3 py-2">' . $leave['totalDays'] . '</td>';
                echo '<td class="px-3 py-2">' . htmlspecialchars($leave['reason'] ?? '-') . '</td>';
                echo '</tr>';
            }
            echo '</tbody>';
            echo '</table>';
            echo '</div>';
        } else {
            echo '<p class="text-gray-500">' . __('No upcoming approved leave.') . '</p>';
        }

        // Leave Balance Summary
        echo '<h4 class="font-semibold mt-4 mb-2">' . __('Leave Balance Summary') . '</h4>';
        $leaveBalance = $scheduleGateway->getLeaveBalanceSummary($selectedStaffID, $gibbonSchoolYearID);

        if (!empty($leaveBalance)) {
            echo '<div class="grid grid-cols-2 md:grid-cols-4 gap-4">';
            foreach ($leaveBalance as $balance) {
                echo '<div class="bg-white rounded-lg shadow p-3 text-center">';
                echo '<span class="block text-xl font-bold">' . $balance['usedDays'] . '</span>';
                echo '<span class="text-sm text-gray-600">' . htmlspecialchars(ucfirst($balance['leaveType'])) . ' ' . __('days used') . '</span>';
                echo '</div>';
            }
            echo '</div>';
        } else {
            echo '<p class="text-gray-500">' . __('No leave taken this year.') . '</p>';
        }
    }

    // Link back to schedule
    echo '<div class="mt-6">';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_schedule.php" class="text-blue-600 hover:underline">&larr; ' . __('Back to Schedule') . '</a>';
    echo '</div>';

    // JavaScript for interactivity
    ?>
    <script>
    // Toggle input fields based on availability checkbox
    document.querySelectorAll('.availability-toggle').forEach(function(checkbox) {
        checkbox.addEventListener('change', function() {
            var day = this.dataset.day;
            var inputs = document.querySelectorAll('.time-input-' + day + ', .hours-input-' + day);
            inputs.forEach(function(input) {
                input.disabled = !checkbox.checked;
            });
        });
    });

    function setAllAvailable(available) {
        document.querySelectorAll('.availability-toggle').forEach(function(checkbox) {
            checkbox.checked = available;
            checkbox.dispatchEvent(new Event('change'));
        });
    }

    function setWeekdaysOnly() {
        var weekdays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
        var weekends = ['Saturday', 'Sunday'];

        document.querySelectorAll('.availability-toggle').forEach(function(checkbox) {
            var day = checkbox.dataset.day;
            checkbox.checked = weekdays.includes(day);
            checkbox.dispatchEvent(new Event('change'));
        });
    }

    function setStandardHours() {
        var days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        days.forEach(function(day) {
            var fromInput = document.querySelector('input[name="from_' + day + '"]');
            var toInput = document.querySelector('input[name="to_' + day + '"]');
            var preferredInput = document.querySelector('input[name="preferred_' + day + '"]');
            var maxInput = document.querySelector('input[name="max_' + day + '"]');

            if (fromInput && !fromInput.disabled) {
                fromInput.value = '08:00';
            }
            if (toInput && !toInput.disabled) {
                toInput.value = '17:00';
            }
            if (preferredInput && !preferredInput.disabled) {
                preferredInput.value = '8';
            }
            if (maxInput && !maxInput.disabled) {
                maxInput.value = '10';
            }
        });
    }
    </script>
    <?php
}
