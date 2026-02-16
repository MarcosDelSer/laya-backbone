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
use Gibbon\Services\Format;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Module\StaffManagement\Domain\ScheduleGateway;
use Gibbon\Module\StaffManagement\Domain\StaffProfileGateway;
use Gibbon\Module\StaffManagement\Domain\AuditLogGateway;

// Module setup - breadcrumbs
$page->breadcrumbs
    ->add(__('Staff Management'), 'staffManagement.php')
    ->add(__('Weekly Schedule'));

// Access check
if (!isActionAccessible($guid, $connection2, '/modules/StaffManagement/staffManagement_schedule.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get school year ID from session
    $gibbonSchoolYearID = $session->get('gibbonSchoolYearID');
    $gibbonPersonID = $session->get('gibbonPersonID');

    // Get gateways via DI container
    $scheduleGateway = $container->get(ScheduleGateway::class);
    $staffProfileGateway = $container->get(StaffProfileGateway::class);
    $auditLogGateway = $container->get(AuditLogGateway::class);

    // Get week start date from request or default to current week
    $weekStart = $_GET['weekStart'] ?? date('Y-m-d', strtotime('monday this week'));

    // Validate date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $weekStart)) {
        $weekStart = date('Y-m-d', strtotime('monday this week'));
    }

    // Ensure weekStart is a Monday
    $weekStart = date('Y-m-d', strtotime('monday this week', strtotime($weekStart)));
    $weekEnd = date('Y-m-d', strtotime($weekStart . ' +6 days'));

    // Calculate previous and next week
    $prevWeekStart = date('Y-m-d', strtotime($weekStart . ' -7 days'));
    $nextWeekStart = date('Y-m-d', strtotime($weekStart . ' +7 days'));
    $currentWeekStart = date('Y-m-d', strtotime('monday this week'));

    // Get staff filter
    $filterStaff = $_GET['staff'] ?? '';

    // Get room filter
    $filterRoom = $_GET['room'] ?? '';

    // Get age group filter
    $filterAgeGroup = $_GET['ageGroup'] ?? '';

    // Handle form submissions (add/edit/delete)
    $mode = $_POST['mode'] ?? '';
    $success = false;

    if ($mode === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        // Add new schedule entry
        $data = [
            'gibbonPersonID' => $_POST['gibbonPersonID'] ?? '',
            'gibbonSchoolYearID' => $gibbonSchoolYearID,
            'gibbonStaffShiftTemplateID' => !empty($_POST['gibbonStaffShiftTemplateID']) ? $_POST['gibbonStaffShiftTemplateID'] : null,
            'date' => Format::dateConvert($_POST['date'] ?? ''),
            'startTime' => $_POST['startTime'] ?? '08:00:00',
            'endTime' => $_POST['endTime'] ?? '17:00:00',
            'breakDuration' => intval($_POST['breakDuration'] ?? 30),
            'roomAssignment' => $_POST['roomAssignment'] ?? null,
            'ageGroup' => $_POST['ageGroup'] ?? null,
            'status' => 'Scheduled',
            'notes' => $_POST['notes'] ?? null,
            'createdByID' => $gibbonPersonID,
        ];

        // Check for conflicts
        $conflicts = $scheduleGateway->findSchedulingConflicts($data['gibbonPersonID'], $data['date'], $data['startTime'], $data['endTime']);
        if (!empty($conflicts)) {
            $page->addError(__('Schedule conflict detected. This staff member already has a shift scheduled during this time.'));
        } elseif (empty($data['gibbonPersonID']) || empty($data['date'])) {
            $page->addError(__('Please fill in all required fields.'));
        } else {
            $insertID = $scheduleGateway->insert($data);
            if ($insertID) {
                $auditLogGateway->logInsert('gibbonStaffSchedule', $insertID, $gibbonPersonID);
                $page->addSuccess(__('Schedule entry added successfully.'));
                $success = true;
            } else {
                $page->addError(__('Failed to add schedule entry.'));
            }
        }
    } elseif ($mode === 'edit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        // Edit existing schedule entry
        $gibbonStaffScheduleID = $_POST['gibbonStaffScheduleID'] ?? '';
        if (!empty($gibbonStaffScheduleID)) {
            $data = [
                'gibbonStaffShiftTemplateID' => !empty($_POST['gibbonStaffShiftTemplateID']) ? $_POST['gibbonStaffShiftTemplateID'] : null,
                'startTime' => $_POST['startTime'] ?? '08:00:00',
                'endTime' => $_POST['endTime'] ?? '17:00:00',
                'breakDuration' => intval($_POST['breakDuration'] ?? 30),
                'roomAssignment' => $_POST['roomAssignment'] ?? null,
                'ageGroup' => $_POST['ageGroup'] ?? null,
                'status' => $_POST['status'] ?? 'Scheduled',
                'notes' => $_POST['notes'] ?? null,
            ];

            $existing = $scheduleGateway->getScheduleByID($gibbonStaffScheduleID);

            // Check for conflicts (excluding current entry)
            $conflicts = $scheduleGateway->findSchedulingConflicts($existing['gibbonPersonID'], $existing['date'], $data['startTime'], $data['endTime'], $gibbonStaffScheduleID);
            if (!empty($conflicts)) {
                $page->addError(__('Schedule conflict detected. This staff member already has a shift scheduled during this time.'));
            } else {
                $updated = $scheduleGateway->update($gibbonStaffScheduleID, $data);
                if ($updated) {
                    $auditLogGateway->logUpdate('gibbonStaffSchedule', $gibbonStaffScheduleID, $gibbonPersonID, $existing, $data);
                    $page->addSuccess(__('Schedule entry updated successfully.'));
                    $success = true;
                } else {
                    $page->addError(__('Failed to update schedule entry.'));
                }
            }
        }
    } elseif ($mode === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        // Delete schedule entry
        $gibbonStaffScheduleID = $_POST['gibbonStaffScheduleID'] ?? '';
        if (!empty($gibbonStaffScheduleID)) {
            $existing = $scheduleGateway->getScheduleByID($gibbonStaffScheduleID);
            $deleted = $scheduleGateway->delete($gibbonStaffScheduleID);
            if ($deleted) {
                $auditLogGateway->logDelete('gibbonStaffSchedule', $gibbonStaffScheduleID, $gibbonPersonID, $existing);
                $page->addSuccess(__('Schedule entry deleted successfully.'));
                $success = true;
            } else {
                $page->addError(__('Failed to delete schedule entry.'));
            }
        }
    } elseif ($mode === 'copyWeek' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        // Copy week schedule
        $sourceWeek = $_POST['sourceWeek'] ?? '';
        $targetWeek = $_POST['targetWeek'] ?? '';
        if (!empty($sourceWeek) && !empty($targetWeek)) {
            $copiedCount = $scheduleGateway->copyWeekSchedule($gibbonSchoolYearID, $sourceWeek, $targetWeek, $gibbonPersonID);
            if ($copiedCount > 0) {
                $page->addSuccess(sprintf(__('%d schedule entries copied successfully.'), $copiedCount));
                $success = true;
            } else {
                $page->addWarning(__('No schedule entries found to copy.'));
            }
        }
    }

    // Page header with week navigation
    echo '<h2>' . __('Weekly Schedule') . '</h2>';

    // Week navigation
    echo '<div class="flex justify-between items-center mb-4">';
    echo '<div class="flex items-center gap-4">';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_schedule.php&weekStart=' . $prevWeekStart . '" class="text-blue-600 hover:underline">&laquo; ' . __('Previous Week') . '</a>';

    // Week display
    echo '<span class="text-lg font-semibold">';
    echo Format::date($weekStart) . ' - ' . Format::date($weekEnd);
    echo '</span>';

    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_schedule.php&weekStart=' . $nextWeekStart . '" class="text-blue-600 hover:underline">' . __('Next Week') . ' &raquo;</a>';
    echo '</div>';

    // Quick links
    if ($weekStart !== $currentWeekStart) {
        echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_schedule.php&weekStart=' . $currentWeekStart . '" class="text-blue-600 hover:underline">' . __('Current Week') . '</a>';
    }
    echo '</div>';

    // Filter Form
    $form = Form::create('scheduleFilter', $session->get('absoluteURL') . '/index.php');
    $form->setMethod('GET');
    $form->addHiddenValue('q', '/modules/StaffManagement/staffManagement_schedule.php');
    $form->addHiddenValue('weekStart', $weekStart);

    $row = $form->addRow();
    $row->addLabel('staff', __('Staff Member'));
    $staffList = $staffProfileGateway->selectActiveStaffList()->fetchKeyPair();
    $row->addSelect('staff')->fromArray($staffList)->selected($filterStaff)->placeholder(__('All Staff'));

    // Get room list from settings or database
    $rooms = explode(',', $session->get('Staff Management.rooms', 'Infant Room A,Infant Room B,Toddler Room A,Toddler Room B,Preschool Room,School Age Room'));
    $roomOptions = array_combine(array_map('trim', $rooms), array_map('trim', $rooms));
    $row = $form->addRow();
    $row->addLabel('room', __('Room'));
    $row->addSelect('room')->fromArray($roomOptions)->selected($filterRoom)->placeholder(__('All Rooms'));

    $ageGroupOptions = [
        'Infant' => __('Infant (0-18 months)'),
        'Toddler' => __('Toddler (18-36 months)'),
        'Preschool' => __('Preschool (36-60 months)'),
        'School Age' => __('School Age (60+ months)'),
        'Mixed' => __('Mixed'),
    ];
    $row = $form->addRow();
    $row->addLabel('ageGroup', __('Age Group'));
    $row->addSelect('ageGroup')->fromArray($ageGroupOptions)->selected($filterAgeGroup)->placeholder(__('All Age Groups'));

    $row = $form->addRow();
    $row->addSearchSubmit($session, __('Clear Filters'));

    echo $form->getOutput();

    // Get schedules for the week
    $criteria = $scheduleGateway->newQueryCriteria()
        ->sortBy(['date', 'startTime'])
        ->fromPOST();

    if (!empty($filterStaff)) {
        $criteria->filterBy('staff', $filterStaff);
    }
    if (!empty($filterRoom)) {
        $criteria->filterBy('roomAssignment', $filterRoom);
    }
    if (!empty($filterAgeGroup)) {
        $criteria->filterBy('ageGroup', $filterAgeGroup);
    }

    $schedules = $scheduleGateway->querySchedulesByDateRange($criteria, $gibbonSchoolYearID, $weekStart, $weekEnd);

    // Get shift templates for dropdown
    $shiftTemplates = $scheduleGateway->selectActiveShiftTemplates()->fetchAll();
    $templateOptions = [];
    foreach ($shiftTemplates as $template) {
        $templateOptions[$template['gibbonStaffShiftTemplateID']] = $template['name'] . ' (' . Format::time($template['startTime']) . ' - ' . Format::time($template['endTime']) . ')';
    }

    // Get daily summary
    $dailySummary = $scheduleGateway->getDailyScheduleSummary($gibbonSchoolYearID, date('Y-m-d'));

    // Summary Statistics
    echo '<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">';

    echo '<div class="bg-white rounded-lg shadow p-4 text-center">';
    echo '<div class="text-2xl font-bold text-blue-600">' . ($dailySummary['totalScheduled'] ?? 0) . '</div>';
    echo '<div class="text-sm text-gray-600">' . __('Scheduled Today') . '</div>';
    echo '</div>';

    echo '<div class="bg-white rounded-lg shadow p-4 text-center">';
    echo '<div class="text-2xl font-bold text-green-600">' . ($dailySummary['confirmed'] ?? 0) . '</div>';
    echo '<div class="text-sm text-gray-600">' . __('Confirmed') . '</div>';
    echo '</div>';

    echo '<div class="bg-white rounded-lg shadow p-4 text-center">';
    echo '<div class="text-2xl font-bold text-purple-600">' . ($dailySummary['uniqueStaff'] ?? 0) . '</div>';
    echo '<div class="text-sm text-gray-600">' . __('Staff Members') . '</div>';
    echo '</div>';

    echo '<div class="bg-white rounded-lg shadow p-4 text-center">';
    $earliestStart = $dailySummary['earliestStart'] ?? null;
    $latestEnd = $dailySummary['latestEnd'] ?? null;
    if ($earliestStart && $latestEnd) {
        echo '<div class="text-lg font-bold text-gray-700">' . Format::time($earliestStart) . ' - ' . Format::time($latestEnd) . '</div>';
    } else {
        echo '<div class="text-lg font-bold text-gray-700">--</div>';
    }
    echo '<div class="text-sm text-gray-600">' . __('Coverage') . '</div>';
    echo '</div>';

    echo '</div>';

    // Quick Action Buttons
    echo '<div class="flex flex-wrap gap-2 mb-4">';
    echo '<button onclick="openAddModal()" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">' . __('Add Schedule Entry') . '</button>';
    echo '<button onclick="openCopyWeekModal()" class="bg-purple-500 text-white px-4 py-2 rounded hover:bg-purple-600">' . __('Copy Week') . '</button>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_shiftTemplates.php" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">' . __('Manage Templates') . '</a>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_availability.php" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">' . __('Staff Availability') . '</a>';
    echo '</div>';

    // Weekly Schedule Grid
    $daysOfWeek = [];
    for ($i = 0; $i < 7; $i++) {
        $dayDate = date('Y-m-d', strtotime($weekStart . ' +' . $i . ' days'));
        $daysOfWeek[$dayDate] = [
            'date' => $dayDate,
            'name' => date('l', strtotime($dayDate)),
            'display' => Format::date($dayDate),
            'isToday' => $dayDate === date('Y-m-d'),
        ];
    }

    // Organize schedules by date
    $schedulesByDate = [];
    foreach ($daysOfWeek as $dayDate => $day) {
        $schedulesByDate[$dayDate] = [];
    }
    foreach ($schedules as $schedule) {
        $schedulesByDate[$schedule['date']][] = $schedule;
    }

    // Display weekly grid
    echo '<div class="overflow-x-auto">';
    echo '<table class="w-full border-collapse">';
    echo '<thead>';
    echo '<tr>';
    foreach ($daysOfWeek as $dayDate => $day) {
        $bgClass = $day['isToday'] ? 'bg-blue-100' : 'bg-gray-100';
        $textClass = $day['isToday'] ? 'text-blue-800' : 'text-gray-700';
        echo '<th class="' . $bgClass . ' ' . $textClass . ' border px-3 py-2 text-center" style="min-width: 150px;">';
        echo '<div class="font-semibold">' . __($day['name']) . '</div>';
        echo '<div class="text-sm font-normal">' . $day['display'] . '</div>';
        echo '</th>';
    }
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    echo '<tr>';
    foreach ($daysOfWeek as $dayDate => $day) {
        $bgClass = $day['isToday'] ? 'bg-blue-50' : 'bg-white';
        echo '<td class="' . $bgClass . ' border px-2 py-2 align-top" style="min-width: 150px; min-height: 200px;">';

        if (empty($schedulesByDate[$dayDate])) {
            echo '<div class="text-center text-gray-400 py-4">' . __('No schedules') . '</div>';
        } else {
            echo '<div class="space-y-2">';
            foreach ($schedulesByDate[$dayDate] as $schedule) {
                // Determine status color
                $statusColors = [
                    'Scheduled' => 'bg-yellow-100 border-yellow-300',
                    'Confirmed' => 'bg-green-100 border-green-300',
                    'Completed' => 'bg-gray-100 border-gray-300',
                    'Cancelled' => 'bg-red-100 border-red-300 line-through',
                    'No Show' => 'bg-red-200 border-red-400',
                ];
                $colorClass = $statusColors[$schedule['status']] ?? 'bg-gray-100 border-gray-300';

                // Use shift template color if available
                $templateColor = $schedule['shiftTemplateColor'] ?? null;
                $styleAttr = '';
                if ($templateColor) {
                    $styleAttr = 'style="border-left: 4px solid ' . htmlspecialchars($templateColor) . ';"';
                }

                echo '<div class="' . $colorClass . ' border rounded p-2 text-sm cursor-pointer hover:shadow" onclick="openEditModal(' . $schedule['gibbonStaffScheduleID'] . ')" ' . $styleAttr . '>';
                echo '<div class="font-semibold">' . htmlspecialchars($schedule['surname'] . ', ' . $schedule['preferredName']) . '</div>';
                echo '<div class="text-xs">' . Format::time($schedule['startTime']) . ' - ' . Format::time($schedule['endTime']) . '</div>';
                if (!empty($schedule['roomAssignment'])) {
                    echo '<div class="text-xs text-gray-600">' . htmlspecialchars($schedule['roomAssignment']) . '</div>';
                }
                if (!empty($schedule['ageGroup'])) {
                    echo '<div class="text-xs text-gray-500">' . htmlspecialchars($schedule['ageGroup']) . '</div>';
                }
                if (!empty($schedule['shiftTemplateName'])) {
                    echo '<div class="text-xs text-purple-600">' . htmlspecialchars($schedule['shiftTemplateName']) . '</div>';
                }
                echo '<div class="text-xs mt-1">';
                echo '<span class="inline-block px-1 py-0.5 rounded text-xs ';
                $statusTextColors = [
                    'Scheduled' => 'bg-yellow-200 text-yellow-800',
                    'Confirmed' => 'bg-green-200 text-green-800',
                    'Completed' => 'bg-gray-200 text-gray-800',
                    'Cancelled' => 'bg-red-200 text-red-800',
                    'No Show' => 'bg-red-300 text-red-900',
                ];
                echo ($statusTextColors[$schedule['status']] ?? 'bg-gray-200');
                echo '">' . __($schedule['status']) . '</span>';
                echo '</div>';
                echo '</div>';
            }
            echo '</div>';
        }

        // Add button for quick add
        echo '<button onclick="openAddModalForDate(\'' . $dayDate . '\')" class="w-full mt-2 text-xs text-blue-600 hover:text-blue-800 py-1">+ ' . __('Add') . '</button>';

        echo '</td>';
    }
    echo '</tr>';
    echo '</tbody>';
    echo '</table>';
    echo '</div>';

    // Legend
    echo '<div class="mt-4 flex flex-wrap gap-4 text-sm">';
    echo '<div class="flex items-center gap-1"><span class="w-4 h-4 bg-yellow-100 border border-yellow-300 rounded"></span> ' . __('Scheduled') . '</div>';
    echo '<div class="flex items-center gap-1"><span class="w-4 h-4 bg-green-100 border border-green-300 rounded"></span> ' . __('Confirmed') . '</div>';
    echo '<div class="flex items-center gap-1"><span class="w-4 h-4 bg-gray-100 border border-gray-300 rounded"></span> ' . __('Completed') . '</div>';
    echo '<div class="flex items-center gap-1"><span class="w-4 h-4 bg-red-100 border border-red-300 rounded"></span> ' . __('Cancelled') . '</div>';
    echo '</div>';

    // Modals and JavaScript
    echo '<div id="addModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">';
    echo '<div class="bg-white rounded-lg shadow-xl max-w-lg w-full mx-4 max-h-[90vh] overflow-y-auto">';
    echo '<div class="p-4 border-b flex justify-between items-center">';
    echo '<h3 class="text-lg font-semibold">' . __('Add Schedule Entry') . '</h3>';
    echo '<button onclick="closeAddModal()" class="text-gray-500 hover:text-gray-700">&times;</button>';
    echo '</div>';
    echo '<form method="POST" action="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_schedule.php&weekStart=' . $weekStart . '" class="p-4">';
    echo '<input type="hidden" name="mode" value="add">';

    // Staff selection
    echo '<div class="mb-4">';
    echo '<label class="block text-sm font-medium mb-1">' . __('Staff Member') . ' *</label>';
    echo '<select name="gibbonPersonID" class="w-full border rounded px-3 py-2" required>';
    echo '<option value="">' . __('Select Staff...') . '</option>';
    foreach ($staffList as $personID => $staffName) {
        echo '<option value="' . $personID . '">' . htmlspecialchars($staffName) . '</option>';
    }
    echo '</select>';
    echo '</div>';

    // Date
    echo '<div class="mb-4">';
    echo '<label class="block text-sm font-medium mb-1">' . __('Date') . ' *</label>';
    echo '<input type="date" name="date" id="addModalDate" class="w-full border rounded px-3 py-2" required>';
    echo '</div>';

    // Shift template
    echo '<div class="mb-4">';
    echo '<label class="block text-sm font-medium mb-1">' . __('Shift Template') . '</label>';
    echo '<select name="gibbonStaffShiftTemplateID" id="addTemplateSelect" class="w-full border rounded px-3 py-2" onchange="applyTemplate(this)">';
    echo '<option value="">' . __('Custom Time') . '</option>';
    foreach ($shiftTemplates as $template) {
        echo '<option value="' . $template['gibbonStaffShiftTemplateID'] . '" data-start="' . $template['startTime'] . '" data-end="' . $template['endTime'] . '" data-break="' . $template['breakDuration'] . '">' . htmlspecialchars($template['name']) . ' (' . Format::time($template['startTime']) . ' - ' . Format::time($template['endTime']) . ')</option>';
    }
    echo '</select>';
    echo '</div>';

    // Times
    echo '<div class="grid grid-cols-2 gap-4 mb-4">';
    echo '<div>';
    echo '<label class="block text-sm font-medium mb-1">' . __('Start Time') . ' *</label>';
    echo '<input type="time" name="startTime" id="addStartTime" class="w-full border rounded px-3 py-2" value="08:00" required>';
    echo '</div>';
    echo '<div>';
    echo '<label class="block text-sm font-medium mb-1">' . __('End Time') . ' *</label>';
    echo '<input type="time" name="endTime" id="addEndTime" class="w-full border rounded px-3 py-2" value="17:00" required>';
    echo '</div>';
    echo '</div>';

    // Break duration
    echo '<div class="mb-4">';
    echo '<label class="block text-sm font-medium mb-1">' . __('Break Duration (minutes)') . '</label>';
    echo '<input type="number" name="breakDuration" id="addBreakDuration" class="w-full border rounded px-3 py-2" value="30" min="0">';
    echo '</div>';

    // Room assignment
    echo '<div class="mb-4">';
    echo '<label class="block text-sm font-medium mb-1">' . __('Room Assignment') . '</label>';
    echo '<select name="roomAssignment" class="w-full border rounded px-3 py-2">';
    echo '<option value="">' . __('Not Assigned') . '</option>';
    foreach ($roomOptions as $room) {
        echo '<option value="' . htmlspecialchars($room) . '">' . htmlspecialchars($room) . '</option>';
    }
    echo '</select>';
    echo '</div>';

    // Age group
    echo '<div class="mb-4">';
    echo '<label class="block text-sm font-medium mb-1">' . __('Age Group') . '</label>';
    echo '<select name="ageGroup" class="w-full border rounded px-3 py-2">';
    echo '<option value="">' . __('Not Assigned') . '</option>';
    foreach ($ageGroupOptions as $value => $label) {
        echo '<option value="' . $value . '">' . $label . '</option>';
    }
    echo '</select>';
    echo '</div>';

    // Notes
    echo '<div class="mb-4">';
    echo '<label class="block text-sm font-medium mb-1">' . __('Notes') . '</label>';
    echo '<textarea name="notes" class="w-full border rounded px-3 py-2" rows="2"></textarea>';
    echo '</div>';

    echo '<div class="flex justify-end gap-2">';
    echo '<button type="button" onclick="closeAddModal()" class="px-4 py-2 border rounded hover:bg-gray-100">' . __('Cancel') . '</button>';
    echo '<button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">' . __('Add Schedule') . '</button>';
    echo '</div>';
    echo '</form>';
    echo '</div>';
    echo '</div>';

    // Edit Modal
    echo '<div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">';
    echo '<div class="bg-white rounded-lg shadow-xl max-w-lg w-full mx-4 max-h-[90vh] overflow-y-auto">';
    echo '<div class="p-4 border-b flex justify-between items-center">';
    echo '<h3 class="text-lg font-semibold">' . __('Edit Schedule Entry') . '</h3>';
    echo '<button onclick="closeEditModal()" class="text-gray-500 hover:text-gray-700">&times;</button>';
    echo '</div>';
    echo '<div id="editModalContent" class="p-4">';
    echo '<div class="text-center py-4">' . __('Loading...') . '</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    // Copy Week Modal
    echo '<div id="copyWeekModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">';
    echo '<div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">';
    echo '<div class="p-4 border-b flex justify-between items-center">';
    echo '<h3 class="text-lg font-semibold">' . __('Copy Week Schedule') . '</h3>';
    echo '<button onclick="closeCopyWeekModal()" class="text-gray-500 hover:text-gray-700">&times;</button>';
    echo '</div>';
    echo '<form method="POST" action="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_schedule.php&weekStart=' . $weekStart . '" class="p-4">';
    echo '<input type="hidden" name="mode" value="copyWeek">';

    echo '<div class="mb-4">';
    echo '<label class="block text-sm font-medium mb-1">' . __('Source Week (copy from)') . '</label>';
    echo '<input type="date" name="sourceWeek" class="w-full border rounded px-3 py-2" value="' . $weekStart . '" required>';
    echo '<p class="text-xs text-gray-500 mt-1">' . __('Select the Monday of the week to copy from') . '</p>';
    echo '</div>';

    echo '<div class="mb-4">';
    echo '<label class="block text-sm font-medium mb-1">' . __('Target Week (copy to)') . '</label>';
    echo '<input type="date" name="targetWeek" class="w-full border rounded px-3 py-2" value="' . $nextWeekStart . '" required>';
    echo '<p class="text-xs text-gray-500 mt-1">' . __('Select the Monday of the week to copy to') . '</p>';
    echo '</div>';

    echo '<div class="bg-yellow-50 border border-yellow-200 rounded p-3 mb-4">';
    echo '<p class="text-sm text-yellow-800">' . __('This will copy all schedules from the source week to the target week. Existing schedules in the target week will not be affected.') . '</p>';
    echo '</div>';

    echo '<div class="flex justify-end gap-2">';
    echo '<button type="button" onclick="closeCopyWeekModal()" class="px-4 py-2 border rounded hover:bg-gray-100">' . __('Cancel') . '</button>';
    echo '<button type="submit" class="px-4 py-2 bg-purple-500 text-white rounded hover:bg-purple-600">' . __('Copy Week') . '</button>';
    echo '</div>';
    echo '</form>';
    echo '</div>';
    echo '</div>';

    // JavaScript for modals
    ?>
    <script>
    function openAddModal() {
        document.getElementById('addModal').classList.remove('hidden');
    }

    function openAddModalForDate(date) {
        document.getElementById('addModalDate').value = date;
        openAddModal();
    }

    function closeAddModal() {
        document.getElementById('addModal').classList.add('hidden');
    }

    function openEditModal(scheduleID) {
        document.getElementById('editModal').classList.remove('hidden');
        // Load edit form via AJAX or populate with data
        loadEditForm(scheduleID);
    }

    function closeEditModal() {
        document.getElementById('editModal').classList.add('hidden');
    }

    function openCopyWeekModal() {
        document.getElementById('copyWeekModal').classList.remove('hidden');
    }

    function closeCopyWeekModal() {
        document.getElementById('copyWeekModal').classList.add('hidden');
    }

    function applyTemplate(select) {
        var option = select.options[select.selectedIndex];
        if (option.value) {
            document.getElementById('addStartTime').value = option.dataset.start.substring(0, 5);
            document.getElementById('addEndTime').value = option.dataset.end.substring(0, 5);
            document.getElementById('addBreakDuration').value = option.dataset.break;
        }
    }

    function loadEditForm(scheduleID) {
        var contentDiv = document.getElementById('editModalContent');
        contentDiv.innerHTML = '<div class="text-center py-4"><?php echo __('Loading...'); ?></div>';

        // Build form content for editing
        // In a real implementation, this would fetch the schedule data via AJAX
        // For now, we'll create a form that submits to the same page

        <?php
        // Prepare schedule data as JSON for JavaScript
        $schedulesJSON = [];
        foreach ($schedules as $schedule) {
            $schedulesJSON[$schedule['gibbonStaffScheduleID']] = $schedule;
        }
        echo 'var scheduleData = ' . json_encode($schedulesJSON) . ';';
        ?>

        var schedule = scheduleData[scheduleID];
        if (!schedule) {
            contentDiv.innerHTML = '<div class="text-center py-4 text-red-600"><?php echo __('Schedule not found'); ?></div>';
            return;
        }

        var formHTML = '<form method="POST" action="<?php echo $session->get('absoluteURL'); ?>/index.php?q=/modules/StaffManagement/staffManagement_schedule.php&weekStart=<?php echo $weekStart; ?>">';
        formHTML += '<input type="hidden" name="mode" value="edit">';
        formHTML += '<input type="hidden" name="gibbonStaffScheduleID" value="' + scheduleID + '">';

        // Staff name (display only)
        formHTML += '<div class="mb-4">';
        formHTML += '<label class="block text-sm font-medium mb-1"><?php echo __('Staff Member'); ?></label>';
        formHTML += '<div class="py-2 px-3 bg-gray-100 rounded">' + schedule.surname + ', ' + schedule.preferredName + '</div>';
        formHTML += '</div>';

        // Date (display only)
        formHTML += '<div class="mb-4">';
        formHTML += '<label class="block text-sm font-medium mb-1"><?php echo __('Date'); ?></label>';
        formHTML += '<div class="py-2 px-3 bg-gray-100 rounded">' + schedule.date + '</div>';
        formHTML += '</div>';

        // Shift template
        formHTML += '<div class="mb-4">';
        formHTML += '<label class="block text-sm font-medium mb-1"><?php echo __('Shift Template'); ?></label>';
        formHTML += '<select name="gibbonStaffShiftTemplateID" id="editTemplateSelect" class="w-full border rounded px-3 py-2" onchange="applyEditTemplate(this)">';
        formHTML += '<option value=""><?php echo __('Custom Time'); ?></option>';
        <?php foreach ($shiftTemplates as $template): ?>
        formHTML += '<option value="<?php echo $template['gibbonStaffShiftTemplateID']; ?>" data-start="<?php echo $template['startTime']; ?>" data-end="<?php echo $template['endTime']; ?>" data-break="<?php echo $template['breakDuration']; ?>"' + (schedule.gibbonStaffShiftTemplateID == '<?php echo $template['gibbonStaffShiftTemplateID']; ?>' ? ' selected' : '') + '><?php echo htmlspecialchars($template['name']) . ' (' . Format::time($template['startTime']) . ' - ' . Format::time($template['endTime']) . ')'; ?></option>';
        <?php endforeach; ?>
        formHTML += '</select>';
        formHTML += '</div>';

        // Times
        formHTML += '<div class="grid grid-cols-2 gap-4 mb-4">';
        formHTML += '<div>';
        formHTML += '<label class="block text-sm font-medium mb-1"><?php echo __('Start Time'); ?> *</label>';
        formHTML += '<input type="time" name="startTime" id="editStartTime" class="w-full border rounded px-3 py-2" value="' + schedule.startTime.substring(0, 5) + '" required>';
        formHTML += '</div>';
        formHTML += '<div>';
        formHTML += '<label class="block text-sm font-medium mb-1"><?php echo __('End Time'); ?> *</label>';
        formHTML += '<input type="time" name="endTime" id="editEndTime" class="w-full border rounded px-3 py-2" value="' + schedule.endTime.substring(0, 5) + '" required>';
        formHTML += '</div>';
        formHTML += '</div>';

        // Break duration
        formHTML += '<div class="mb-4">';
        formHTML += '<label class="block text-sm font-medium mb-1"><?php echo __('Break Duration (minutes)'); ?></label>';
        formHTML += '<input type="number" name="breakDuration" id="editBreakDuration" class="w-full border rounded px-3 py-2" value="' + schedule.breakDuration + '" min="0">';
        formHTML += '</div>';

        // Room assignment
        formHTML += '<div class="mb-4">';
        formHTML += '<label class="block text-sm font-medium mb-1"><?php echo __('Room Assignment'); ?></label>';
        formHTML += '<select name="roomAssignment" class="w-full border rounded px-3 py-2">';
        formHTML += '<option value=""><?php echo __('Not Assigned'); ?></option>';
        <?php foreach ($roomOptions as $room): ?>
        formHTML += '<option value="<?php echo htmlspecialchars($room); ?>"' + (schedule.roomAssignment == '<?php echo $room; ?>' ? ' selected' : '') + '><?php echo htmlspecialchars($room); ?></option>';
        <?php endforeach; ?>
        formHTML += '</select>';
        formHTML += '</div>';

        // Age group
        formHTML += '<div class="mb-4">';
        formHTML += '<label class="block text-sm font-medium mb-1"><?php echo __('Age Group'); ?></label>';
        formHTML += '<select name="ageGroup" class="w-full border rounded px-3 py-2">';
        formHTML += '<option value=""><?php echo __('Not Assigned'); ?></option>';
        <?php foreach ($ageGroupOptions as $value => $label): ?>
        formHTML += '<option value="<?php echo $value; ?>"' + (schedule.ageGroup == '<?php echo $value; ?>' ? ' selected' : '') + '><?php echo $label; ?></option>';
        <?php endforeach; ?>
        formHTML += '</select>';
        formHTML += '</div>';

        // Status
        formHTML += '<div class="mb-4">';
        formHTML += '<label class="block text-sm font-medium mb-1"><?php echo __('Status'); ?></label>';
        formHTML += '<select name="status" class="w-full border rounded px-3 py-2">';
        var statuses = ['Scheduled', 'Confirmed', 'Completed', 'Cancelled', 'No Show'];
        for (var i = 0; i < statuses.length; i++) {
            formHTML += '<option value="' + statuses[i] + '"' + (schedule.status == statuses[i] ? ' selected' : '') + '>' + statuses[i] + '</option>';
        }
        formHTML += '</select>';
        formHTML += '</div>';

        // Notes
        formHTML += '<div class="mb-4">';
        formHTML += '<label class="block text-sm font-medium mb-1"><?php echo __('Notes'); ?></label>';
        formHTML += '<textarea name="notes" class="w-full border rounded px-3 py-2" rows="2">' + (schedule.notes || '') + '</textarea>';
        formHTML += '</div>';

        formHTML += '<div class="flex justify-between">';
        formHTML += '<button type="button" onclick="deleteSchedule(' + scheduleID + ')" class="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600"><?php echo __('Delete'); ?></button>';
        formHTML += '<div class="flex gap-2">';
        formHTML += '<button type="button" onclick="closeEditModal()" class="px-4 py-2 border rounded hover:bg-gray-100"><?php echo __('Cancel'); ?></button>';
        formHTML += '<button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600"><?php echo __('Update'); ?></button>';
        formHTML += '</div>';
        formHTML += '</div>';
        formHTML += '</form>';

        contentDiv.innerHTML = formHTML;
    }

    function applyEditTemplate(select) {
        var option = select.options[select.selectedIndex];
        if (option.value) {
            document.getElementById('editStartTime').value = option.dataset.start.substring(0, 5);
            document.getElementById('editEndTime').value = option.dataset.end.substring(0, 5);
            document.getElementById('editBreakDuration').value = option.dataset.break;
        }
    }

    function deleteSchedule(scheduleID) {
        if (confirm('<?php echo __('Are you sure you want to delete this schedule entry?'); ?>')) {
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = '<?php echo $session->get('absoluteURL'); ?>/index.php?q=/modules/StaffManagement/staffManagement_schedule.php&weekStart=<?php echo $weekStart; ?>';

            var modeInput = document.createElement('input');
            modeInput.type = 'hidden';
            modeInput.name = 'mode';
            modeInput.value = 'delete';
            form.appendChild(modeInput);

            var idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'gibbonStaffScheduleID';
            idInput.value = scheduleID;
            form.appendChild(idInput);

            document.body.appendChild(form);
            form.submit();
        }
    }

    // Close modals on outside click
    document.getElementById('addModal').addEventListener('click', function(e) {
        if (e.target === this) closeAddModal();
    });
    document.getElementById('editModal').addEventListener('click', function(e) {
        if (e.target === this) closeEditModal();
    });
    document.getElementById('copyWeekModal').addEventListener('click', function(e) {
        if (e.target === this) closeCopyWeekModal();
    });

    // Close modals on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeAddModal();
            closeEditModal();
            closeCopyWeekModal();
        }
    });
    </script>
    <?php
}
