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
use Gibbon\Module\CareTracking\Domain\NapGateway;
use Gibbon\Module\CareTracking\Domain\AttendanceGateway;

// Module setup - breadcrumbs
$page->breadcrumbs->add(__('Care Tracking'), 'careTracking.php');
$page->breadcrumbs->add(__('Naps'));

// Access check
if (!isActionAccessible($guid, $connection2, '/modules/CareTracking/careTracking_naps.php')) {
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
    $napGateway = $container->get(NapGateway::class);
    $attendanceGateway = $container->get(AttendanceGateway::class);

    // Sleep quality options
    $qualityOptions = [
        'Sound'    => __('Sound'),
        'Light'    => __('Light'),
        'Restless' => __('Restless'),
    ];

    // Handle nap actions
    $action = $_POST['action'] ?? '';
    $childID = $_POST['gibbonPersonID'] ?? null;

    if ($action === 'startNap' && !empty($childID)) {
        $startTime = $_POST['startTime'] ?? date('H:i:s');
        $notes = $_POST['notes'] ?? null;

        // Check if child already has an active nap
        $activeNap = $napGateway->getActiveNap($childID, $date);
        if ($activeNap) {
            $page->addError(__('This child already has an active nap. Please end the current nap before starting a new one.'));
        } else {
            $result = $napGateway->startNap(
                $childID,
                $gibbonSchoolYearID,
                $date,
                $startTime,
                $gibbonPersonID,
                $notes
            );

            if ($result !== false) {
                $page->addSuccess(__('Nap has been started successfully.'));
            } else {
                $page->addError(__('Failed to start nap.'));
            }
        }
    }

    if ($action === 'endNap' && !empty($childID)) {
        $gibbonCareNapID = $_POST['gibbonCareNapID'] ?? null;
        $endTime = $_POST['endTime'] ?? date('H:i:s');
        $quality = $_POST['quality'] ?? null;
        $notes = $_POST['notes'] ?? null;

        if (!empty($gibbonCareNapID)) {
            $result = $napGateway->endNap(
                $gibbonCareNapID,
                $endTime,
                $quality,
                $notes
            );

            if ($result !== false) {
                $page->addSuccess(__('Nap has been ended successfully.'));
            } else {
                $page->addError(__('Failed to end nap.'));
            }
        } else {
            $page->addError(__('Invalid nap record.'));
        }
    }

    // Handle bulk nap start
    if ($action === 'startBulkNap') {
        $childIDs = $_POST['childIDs'] ?? [];
        $startTime = $_POST['startTime'] ?? date('H:i:s');
        $notes = $_POST['notes'] ?? null;

        if (!empty($childIDs)) {
            $successCount = 0;
            foreach ($childIDs as $childID) {
                $result = $napGateway->startNap(
                    $childID,
                    $gibbonSchoolYearID,
                    $date,
                    $startTime,
                    $gibbonPersonID,
                    $notes
                );
                if ($result !== false) {
                    $successCount++;
                }
            }
            if ($successCount > 0) {
                $page->addSuccess(__('Naps started for {count} children.', ['count' => $successCount]));
            }
        } else {
            $page->addError(__('Please select at least one child.'));
        }
    }

    // Page header
    echo '<h2>' . __('Nap Tracking') . '</h2>';

    // Date navigation form
    $form = Form::create('dateFilter', $session->get('absoluteURL') . '/index.php');
    $form->setMethod('get');
    $form->addHiddenValue('q', '/modules/CareTracking/careTracking_naps.php');

    $row = $form->addRow();
    $row->addLabel('date', __('Date'));
    $row->addDate('date')->setValue(Format::date($date))->required();

    $row = $form->addRow();
    $row->addSubmit(__('Go'));

    echo $form->getOutput();

    // Display formatted date
    echo '<p class="text-lg mb-4">' . __('Showing naps for') . ': <strong>' . Format::date($date) . '</strong></p>';

    // Get summary statistics
    $summary = $napGateway->getNapSummaryByDate($gibbonSchoolYearID, $date);

    // Display summary
    echo '<div class="bg-white rounded-lg shadow p-4 mb-4">';
    echo '<h3 class="text-lg font-semibold mb-3">' . __('Today\'s Nap Summary') . '</h3>';
    echo '<div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-4 text-center">';

    echo '<div class="bg-blue-50 rounded p-2">';
    echo '<span class="block text-2xl font-bold text-blue-600">' . ($summary['currentlySleeping'] ?? 0) . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('Currently Sleeping') . '</span>';
    echo '</div>';

    echo '<div class="bg-green-50 rounded p-2">';
    echo '<span class="block text-2xl font-bold text-green-600">' . ($summary['completedNaps'] ?? 0) . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('Completed') . '</span>';
    echo '</div>';

    echo '<div class="bg-gray-50 rounded p-2">';
    echo '<span class="block text-2xl font-bold">' . ($summary['totalNaps'] ?? 0) . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('Total Naps') . '</span>';
    echo '</div>';

    echo '<div class="bg-gray-50 rounded p-2">';
    echo '<span class="block text-2xl font-bold">' . ($summary['childrenNapped'] ?? 0) . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('Children Napped') . '</span>';
    echo '</div>';

    $avgDuration = round($summary['avgDurationMinutes'] ?? 0);
    echo '<div class="bg-purple-50 rounded p-2">';
    echo '<span class="block text-2xl font-bold text-purple-600">' . $avgDuration . 'm</span>';
    echo '<span class="text-xs text-gray-500">' . __('Avg Duration') . '</span>';
    echo '</div>';

    echo '<div class="bg-green-50 rounded p-2">';
    echo '<span class="block text-xl font-bold text-green-600">' . ($summary['soundSleeps'] ?? 0) . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('Sound') . '</span>';
    echo '</div>';

    echo '<div class="bg-yellow-50 rounded p-2">';
    echo '<span class="block text-xl font-bold text-yellow-600">' . ($summary['lightSleeps'] ?? 0) . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('Light') . '</span>';
    echo '</div>';

    echo '<div class="bg-orange-50 rounded p-2">';
    echo '<span class="block text-xl font-bold text-orange-600">' . ($summary['restlessSleeps'] ?? 0) . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('Restless') . '</span>';
    echo '</div>';

    echo '</div>';
    echo '</div>';

    // Section: Children Currently Napping (with end nap button)
    echo '<h3 class="text-lg font-semibold mt-6 mb-3">' . __('Currently Napping') . '</h3>';

    $currentlyNapping = $napGateway->selectChildrenCurrentlyNapping($gibbonSchoolYearID, $date);

    if ($currentlyNapping->rowCount() > 0) {
        echo '<div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">';
        echo '<form method="post" action="' . $session->get('absoluteURL') . '/index.php?q=/modules/CareTracking/careTracking_naps.php&date=' . $date . '">';
        echo '<input type="hidden" name="action" value="endNap">';

        // Quality selection
        echo '<div class="mb-4 flex flex-wrap gap-4">';
        echo '<div>';
        echo '<label class="block text-sm font-medium mb-1">' . __('Sleep Quality') . '</label>';
        echo '<select name="quality" class="border rounded px-3 py-2">';
        foreach ($qualityOptions as $value => $label) {
            $selected = $value === 'Sound' ? ' selected' : '';
            echo '<option value="' . htmlspecialchars($value) . '"' . $selected . '>' . htmlspecialchars($label) . '</option>';
        }
        echo '</select>';
        echo '</div>';

        echo '<div class="flex-1">';
        echo '<label class="block text-sm font-medium mb-1">' . __('Notes') . '</label>';
        echo '<input type="text" name="notes" placeholder="' . __('Optional notes') . '" class="w-full border rounded px-3 py-2">';
        echo '</div>';
        echo '</div>';

        echo '<div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">';
        foreach ($currentlyNapping as $child) {
            $childName = Format::name('', $child['preferredName'], $child['surname'], 'Student', false, true);
            $image = !empty($child['image_240']) ? $child['image_240'] : 'themes/Default/img/anonymous_240.jpg';
            $startTime = Format::time($child['startTime']);
            $durationMinutes = $child['currentDurationMinutes'] ?? 0;
            $durationHours = floor($durationMinutes / 60);
            $durationMins = $durationMinutes % 60;
            $durationText = $durationHours > 0 ? $durationHours . 'h ' . $durationMins . 'm' : $durationMins . 'm';

            echo '<div class="bg-white rounded-lg shadow p-3 text-center hover:shadow-lg transition-shadow">';
            echo '<img src="' . $session->get('absoluteURL') . '/' . $image . '" class="w-16 h-16 rounded-full mx-auto mb-2 object-cover" alt="' . htmlspecialchars($childName) . '">';
            echo '<p class="text-sm font-medium truncate">' . htmlspecialchars($childName) . '</p>';
            echo '<p class="text-xs text-gray-500">' . __('Started') . ': ' . $startTime . '</p>';
            echo '<p class="text-xs text-blue-600 font-semibold">' . __('Duration') . ': ' . $durationText . '</p>';
            echo '<input type="hidden" name="gibbonCareNapID" value="' . $child['gibbonCareNapID'] . '">';
            echo '<button type="submit" name="gibbonPersonID" value="' . $child['gibbonPersonID'] . '" class="mt-2 bg-blue-500 text-white text-xs px-3 py-1 rounded hover:bg-blue-600">' . __('End Nap') . '</button>';
            echo '</div>';
        }
        echo '</div>';

        echo '</form>';
        echo '</div>';
    } else {
        echo '<p class="text-gray-500 mb-4">' . __('No children are currently napping.') . '</p>';
    }

    // Section: Start Nap for Checked-In Children (who aren't napping)
    echo '<h3 class="text-lg font-semibold mt-6 mb-3">' . __('Start Nap') . '</h3>';

    // Get children who are checked in but don't have an active nap
    $checkedInChildren = $attendanceGateway->selectChildrenCurrentlyCheckedIn($gibbonSchoolYearID, $date);
    $availableChildren = [];

    // Filter out children who are currently napping
    $nappingIDs = [];
    $currentlyNappingForFilter = $napGateway->selectChildrenCurrentlyNapping($gibbonSchoolYearID, $date);
    foreach ($currentlyNappingForFilter as $napping) {
        $nappingIDs[] = $napping['gibbonPersonID'];
    }

    foreach ($checkedInChildren as $child) {
        if (!in_array($child['gibbonPersonID'], $nappingIDs)) {
            $availableChildren[] = $child;
        }
    }

    if (count($availableChildren) > 0) {
        echo '<div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">';
        echo '<form method="post" action="' . $session->get('absoluteURL') . '/index.php?q=/modules/CareTracking/careTracking_naps.php&date=' . $date . '">';
        echo '<input type="hidden" name="action" value="startNap">';

        echo '<div class="mb-4 flex flex-wrap gap-4">';
        echo '<div>';
        echo '<label class="block text-sm font-medium mb-1">' . __('Start Time') . '</label>';
        echo '<input type="time" name="startTime" value="' . date('H:i') . '" class="border rounded px-3 py-2">';
        echo '</div>';

        echo '<div class="flex-1">';
        echo '<label class="block text-sm font-medium mb-1">' . __('Notes') . '</label>';
        echo '<input type="text" name="notes" placeholder="' . __('Optional notes') . '" class="w-full border rounded px-3 py-2">';
        echo '</div>';
        echo '</div>';

        echo '<div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">';
        foreach ($availableChildren as $child) {
            $childName = Format::name('', $child['preferredName'], $child['surname'], 'Student', false, true);
            $image = !empty($child['image_240']) ? $child['image_240'] : 'themes/Default/img/anonymous_240.jpg';

            // Check for previous naps today
            $childNaps = $napGateway->selectNapsByPersonAndDate($child['gibbonPersonID'], $date);
            $napCount = $childNaps->rowCount();
            $napBadge = '';
            if ($napCount > 0) {
                $napBadge = '<span class="bg-blue-200 text-blue-800 text-xs px-1 rounded">' . $napCount . ' ' . __('nap(s)') . '</span>';
            }

            echo '<div class="bg-white rounded-lg shadow p-3 text-center hover:shadow-lg transition-shadow">';
            echo '<img src="' . $session->get('absoluteURL') . '/' . $image . '" class="w-16 h-16 rounded-full mx-auto mb-2 object-cover" alt="' . htmlspecialchars($childName) . '">';
            echo '<p class="text-sm font-medium truncate">' . htmlspecialchars($childName) . '</p>';
            if (!empty($napBadge)) {
                echo '<div class="mt-1">' . $napBadge . '</div>';
            }
            echo '<button type="submit" name="gibbonPersonID" value="' . $child['gibbonPersonID'] . '" class="mt-2 bg-green-500 text-white text-xs px-3 py-1 rounded hover:bg-green-600">' . __('Start Nap') . '</button>';
            echo '</div>';
        }
        echo '</div>';

        echo '</form>';
        echo '</div>';
    } else {
        echo '<p class="text-gray-500 mb-4">' . __('No children available to start a nap. All checked-in children are either napping or not checked in.') . '</p>';
    }

    // Section: Bulk Start Nap
    echo '<h3 class="text-lg font-semibold mt-6 mb-3">' . __('Bulk Start Nap') . '</h3>';

    if (count($availableChildren) > 0) {
        echo '<div class="bg-purple-50 border border-purple-200 rounded-lg p-4 mb-4">';
        echo '<form method="post" action="' . $session->get('absoluteURL') . '/index.php?q=/modules/CareTracking/careTracking_naps.php&date=' . $date . '">';
        echo '<input type="hidden" name="action" value="startBulkNap">';

        echo '<p class="text-sm text-gray-600 mb-3">' . __('Select multiple children to start nap time for all at once.') . '</p>';

        echo '<div class="mb-4 flex flex-wrap gap-4">';
        echo '<div>';
        echo '<label class="block text-sm font-medium mb-1">' . __('Start Time') . '</label>';
        echo '<input type="time" name="startTime" value="' . date('H:i') . '" class="border rounded px-3 py-2">';
        echo '</div>';

        echo '<div class="flex-1">';
        echo '<label class="block text-sm font-medium mb-1">' . __('Notes') . '</label>';
        echo '<input type="text" name="notes" placeholder="' . __('Optional notes') . '" class="w-full border rounded px-3 py-2">';
        echo '</div>';
        echo '</div>';

        // Child selection with checkboxes
        echo '<div class="mb-3">';
        echo '<label class="flex items-center text-sm font-medium mb-2">';
        echo '<input type="checkbox" id="selectAllNap" class="mr-2">';
        echo __('Select All');
        echo '</label>';
        echo '</div>';

        echo '<div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">';
        foreach ($availableChildren as $child) {
            $childName = Format::name('', $child['preferredName'], $child['surname'], 'Student', false, true);
            $image = !empty($child['image_240']) ? $child['image_240'] : 'themes/Default/img/anonymous_240.jpg';

            echo '<label class="bg-white rounded-lg shadow p-3 text-center hover:shadow-lg transition-shadow cursor-pointer">';
            echo '<input type="checkbox" name="childIDs[]" value="' . $child['gibbonPersonID'] . '" class="napChildCheckbox mb-2">';
            echo '<img src="' . $session->get('absoluteURL') . '/' . $image . '" class="w-12 h-12 rounded-full mx-auto mb-2 object-cover" alt="' . htmlspecialchars($childName) . '">';
            echo '<p class="text-sm font-medium truncate">' . htmlspecialchars($childName) . '</p>';
            echo '</label>';
        }
        echo '</div>';

        echo '<div class="mt-4">';
        echo '<button type="submit" class="bg-purple-500 text-white px-4 py-2 rounded hover:bg-purple-600">' . __('Start Nap for Selected') . '</button>';
        echo '</div>';

        echo '</form>';

        // JavaScript for Select All functionality
        echo '<script>
        document.getElementById("selectAllNap").addEventListener("change", function() {
            var checkboxes = document.getElementsByClassName("napChildCheckbox");
            for (var i = 0; i < checkboxes.length; i++) {
                checkboxes[i].checked = this.checked;
            }
        });
        </script>';

        echo '</div>';
    }

    // Section: Today's Nap Records
    echo '<h3 class="text-lg font-semibold mt-6 mb-3">' . __('Nap Records') . '</h3>';

    // Build query criteria
    $criteria = $napGateway->newQueryCriteria()
        ->sortBy(['startTime', 'surname', 'preferredName'])
        ->fromPOST();

    // Get nap data for the date
    $naps = $napGateway->queryNapsByDate($criteria, $gibbonSchoolYearID, $date);

    // Build DataTable
    $table = DataTable::createPaginated('naps', $criteria);
    $table->setTitle(__('Nap Records'));

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

    $table->addColumn('startTime', __('Start Time'))
        ->sortable()
        ->format(function ($row) {
            return Format::time($row['startTime']);
        });

    $table->addColumn('endTime', __('End Time'))
        ->format(function ($row) {
            if (empty($row['endTime'])) {
                return '<span class="text-blue-600 text-sm font-semibold">' . __('Sleeping...') . '</span>';
            }
            return Format::time($row['endTime']);
        });

    $table->addColumn('duration', __('Duration'))
        ->notSortable()
        ->format(function ($row) {
            if (empty($row['endTime'])) {
                // Calculate current duration
                $startTime = strtotime($row['date'] . ' ' . $row['startTime']);
                $now = time();
                $durationMinutes = floor(($now - $startTime) / 60);
            } else {
                $durationMinutes = $row['durationMinutes'] ?? 0;
            }

            $hours = floor($durationMinutes / 60);
            $minutes = $durationMinutes % 60;

            if ($hours > 0) {
                return $hours . 'h ' . $minutes . 'm';
            }
            return $minutes . 'm';
        });

    $table->addColumn('quality', __('Quality'))
        ->format(function ($row) {
            if (empty($row['quality'])) {
                return '-';
            }

            $colors = [
                'Sound'    => 'green',
                'Light'    => 'yellow',
                'Restless' => 'orange',
            ];
            $color = $colors[$row['quality']] ?? 'gray';
            return '<span class="bg-' . $color . '-100 text-' . $color . '-800 text-xs px-2 py-1 rounded">' . __($row['quality']) . '</span>';
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

    $table->addColumn('status', __('Status'))
        ->notSortable()
        ->format(function ($row) {
            if (empty($row['endTime'])) {
                return '<span class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded animate-pulse">' . __('In Progress') . '</span>';
            }
            return '<span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded">' . __('Completed') . '</span>';
        });

    // Output table
    if ($naps->count() > 0) {
        echo $table->render($naps);
    } else {
        echo '<div class="bg-gray-50 rounded-lg p-4 text-center text-gray-500">';
        echo __('No nap records found for this date.');
        echo '</div>';
    }

    // Link back to dashboard
    echo '<div class="mt-4">';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/CareTracking/careTracking.php&date=' . $date . '" class="text-blue-600 hover:underline">&larr; ' . __('Back to Dashboard') . '</a>';
    echo '</div>';
}
