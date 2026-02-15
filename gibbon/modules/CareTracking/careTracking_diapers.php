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
use Gibbon\Module\CareTracking\Domain\DiaperGateway;
use Gibbon\Module\CareTracking\Domain\AttendanceGateway;

// Module setup - breadcrumbs
$page->breadcrumbs->add(__('Care Tracking'), 'careTracking.php');
$page->breadcrumbs->add(__('Diapers'));

// Access check
if (!isActionAccessible($guid, $connection2, '/modules/CareTracking/careTracking_diapers.php')) {
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
    $diaperGateway = $container->get(DiaperGateway::class);
    $attendanceGateway = $container->get(AttendanceGateway::class);

    // Diaper type options
    $diaperTypes = [
        'Wet'    => __('Wet'),
        'Soiled' => __('Soiled'),
        'Both'   => __('Both'),
        'Dry'    => __('Dry'),
    ];

    // Handle diaper logging action
    $action = $_POST['action'] ?? '';
    $childID = $_POST['gibbonPersonID'] ?? null;

    if ($action === 'logDiaper' && !empty($childID)) {
        $diaperType = $_POST['diaperType'] ?? '';
        $changeTime = $_POST['changeTime'] ?? date('H:i:s');
        $notes = $_POST['notes'] ?? null;

        if (!empty($diaperType)) {
            $result = $diaperGateway->logDiaperChange(
                $childID,
                $gibbonSchoolYearID,
                $date,
                $changeTime,
                $diaperType,
                $gibbonPersonID,
                $notes
            );

            if ($result !== false) {
                $page->addSuccess(__('Diaper change has been logged successfully.'));
            } else {
                $page->addError(__('Failed to log diaper change.'));
            }
        } else {
            $page->addError(__('Please select a diaper type.'));
        }
    }

    // Handle bulk diaper logging
    if ($action === 'logBulkDiaper') {
        $diaperType = $_POST['diaperType'] ?? '';
        $childIDs = $_POST['childIDs'] ?? [];
        $changeTime = $_POST['changeTime'] ?? date('H:i:s');
        $notes = $_POST['notes'] ?? null;

        if (!empty($diaperType) && !empty($childIDs)) {
            $successCount = 0;
            foreach ($childIDs as $childID) {
                $result = $diaperGateway->logDiaperChange(
                    $childID,
                    $gibbonSchoolYearID,
                    $date,
                    $changeTime,
                    $diaperType,
                    $gibbonPersonID,
                    $notes
                );
                if ($result !== false) {
                    $successCount++;
                }
            }
            if ($successCount > 0) {
                $page->addSuccess(__('Diaper changes logged for {count} children.', ['count' => $successCount]));
            }
        } else {
            $page->addError(__('Please select a diaper type and at least one child.'));
        }
    }

    // Page header
    echo '<h2>' . __('Diaper Tracking') . '</h2>';

    // Date navigation form
    $form = Form::create('dateFilter', $session->get('absoluteURL') . '/index.php');
    $form->setMethod('get');
    $form->addHiddenValue('q', '/modules/CareTracking/careTracking_diapers.php');

    $row = $form->addRow();
    $row->addLabel('date', __('Date'));
    $row->addDate('date')->setValue(Format::date($date))->required();

    $row = $form->addRow();
    $row->addSubmit(__('Go'));

    echo $form->getOutput();

    // Display formatted date
    echo '<p class="text-lg mb-4">' . __('Showing diaper changes for') . ': <strong>' . Format::date($date) . '</strong></p>';

    // Get summary statistics
    $summary = $diaperGateway->getDiaperSummaryByDate($gibbonSchoolYearID, $date);

    // Display summary
    echo '<div class="bg-white rounded-lg shadow p-4 mb-4">';
    echo '<h3 class="text-lg font-semibold mb-3">' . __('Today\'s Diaper Summary') . '</h3>';
    echo '<div class="grid grid-cols-2 md:grid-cols-6 gap-4 text-center">';

    echo '<div class="bg-gray-50 rounded p-2">';
    echo '<span class="block text-2xl font-bold">' . ($summary['totalChanges'] ?? 0) . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('Total Changes') . '</span>';
    echo '</div>';

    echo '<div class="bg-gray-50 rounded p-2">';
    echo '<span class="block text-2xl font-bold">' . ($summary['childrenChanged'] ?? 0) . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('Children Changed') . '</span>';
    echo '</div>';

    echo '<div class="bg-blue-50 rounded p-2">';
    echo '<span class="block text-2xl font-bold text-blue-600">' . ($summary['wetCount'] ?? 0) . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('Wet') . '</span>';
    echo '</div>';

    echo '<div class="bg-yellow-50 rounded p-2">';
    echo '<span class="block text-2xl font-bold text-yellow-600">' . ($summary['soiledCount'] ?? 0) . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('Soiled') . '</span>';
    echo '</div>';

    echo '<div class="bg-orange-50 rounded p-2">';
    echo '<span class="block text-2xl font-bold text-orange-600">' . ($summary['bothCount'] ?? 0) . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('Both') . '</span>';
    echo '</div>';

    echo '<div class="bg-green-50 rounded p-2">';
    echo '<span class="block text-2xl font-bold text-green-600">' . ($summary['dryCount'] ?? 0) . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('Dry') . '</span>';
    echo '</div>';

    echo '</div>';
    echo '</div>';

    // Section: Children Needing Change (haven't been changed in 2+ hours)
    echo '<h3 class="text-lg font-semibold mt-6 mb-3">' . __('Needs Diaper Change') . '</h3>';

    $childrenNeedingChange = $diaperGateway->selectChildrenNeedingChange($gibbonSchoolYearID, $date, 2);

    if ($childrenNeedingChange->rowCount() > 0) {
        echo '<div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">';
        echo '<p class="text-sm text-red-600 mb-3">' . __('These children haven\'t had a diaper change in over 2 hours or haven\'t been changed today.') . '</p>';
        echo '<form method="post" action="' . $session->get('absoluteURL') . '/index.php?q=/modules/CareTracking/careTracking_diapers.php&date=' . $date . '">';
        echo '<input type="hidden" name="action" value="logDiaper">';

        // Type selection
        echo '<div class="mb-4 flex flex-wrap gap-4">';
        echo '<div>';
        echo '<label class="block text-sm font-medium mb-1">' . __('Diaper Type') . '</label>';
        echo '<select name="diaperType" class="border rounded px-3 py-2" required>';
        echo '<option value="">' . __('Select Type') . '</option>';
        foreach ($diaperTypes as $value => $label) {
            echo '<option value="' . htmlspecialchars($value) . '">' . htmlspecialchars($label) . '</option>';
        }
        echo '</select>';
        echo '</div>';

        echo '<div>';
        echo '<label class="block text-sm font-medium mb-1">' . __('Time') . '</label>';
        echo '<input type="time" name="changeTime" value="' . date('H:i') . '" class="border rounded px-3 py-2">';
        echo '</div>';

        echo '<div class="flex-1">';
        echo '<label class="block text-sm font-medium mb-1">' . __('Notes') . '</label>';
        echo '<input type="text" name="notes" placeholder="' . __('Optional notes') . '" class="w-full border rounded px-3 py-2">';
        echo '</div>';
        echo '</div>';

        echo '<div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">';
        foreach ($childrenNeedingChange as $child) {
            $childName = Format::name('', $child['preferredName'], $child['surname'], 'Student', false, true);
            $image = !empty($child['image_240']) ? $child['image_240'] : 'themes/Default/img/anonymous_240.jpg';

            $minutesSince = $child['minutesSinceLastChange'] ?? null;
            $timeText = '';
            if ($minutesSince === null || $child['lastTime'] === null) {
                $timeText = '<span class="text-red-600 text-xs">' . __('No change today') . '</span>';
            } else {
                $hours = floor($minutesSince / 60);
                $mins = $minutesSince % 60;
                $timeText = '<span class="text-red-600 text-xs">' . $hours . 'h ' . $mins . 'm ' . __('ago') . '</span>';
            }

            echo '<div class="bg-white rounded-lg shadow p-3 text-center hover:shadow-lg transition-shadow border-2 border-red-200">';
            echo '<img src="' . $session->get('absoluteURL') . '/' . $image . '" class="w-16 h-16 rounded-full mx-auto mb-2 object-cover" alt="' . htmlspecialchars($childName) . '">';
            echo '<p class="text-sm font-medium truncate">' . htmlspecialchars($childName) . '</p>';
            echo '<div class="mt-1">' . $timeText . '</div>';
            echo '<button type="submit" name="gibbonPersonID" value="' . $child['gibbonPersonID'] . '" class="mt-2 bg-red-500 text-white text-xs px-3 py-1 rounded hover:bg-red-600">' . __('Log Change') . '</button>';
            echo '</div>';
        }
        echo '</div>';

        echo '</form>';
        echo '</div>';
    } else {
        echo '<p class="text-gray-500 mb-4">' . __('All checked-in children have been changed within the last 2 hours.') . '</p>';
    }

    // Section: Quick Log Diaper Change (for children currently checked in)
    echo '<h3 class="text-lg font-semibold mt-6 mb-3">' . __('Log Diaper Change') . '</h3>';

    // Get children currently checked in
    $checkedInChildren = $attendanceGateway->selectChildrenCurrentlyCheckedIn($gibbonSchoolYearID, $date);

    if ($checkedInChildren->rowCount() > 0) {
        echo '<div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">';
        echo '<form method="post" action="' . $session->get('absoluteURL') . '/index.php?q=/modules/CareTracking/careTracking_diapers.php&date=' . $date . '">';
        echo '<input type="hidden" name="action" value="logDiaper">';

        // Type and time selection
        echo '<div class="mb-4 flex flex-wrap gap-4">';
        echo '<div>';
        echo '<label class="block text-sm font-medium mb-1">' . __('Diaper Type') . '</label>';
        echo '<select name="diaperType" class="border rounded px-3 py-2" required>';
        echo '<option value="">' . __('Select Type') . '</option>';
        foreach ($diaperTypes as $value => $label) {
            echo '<option value="' . htmlspecialchars($value) . '">' . htmlspecialchars($label) . '</option>';
        }
        echo '</select>';
        echo '</div>';

        echo '<div>';
        echo '<label class="block text-sm font-medium mb-1">' . __('Time') . '</label>';
        echo '<input type="time" name="changeTime" value="' . date('H:i') . '" class="border rounded px-3 py-2">';
        echo '</div>';

        echo '<div class="flex-1">';
        echo '<label class="block text-sm font-medium mb-1">' . __('Notes') . '</label>';
        echo '<input type="text" name="notes" placeholder="' . __('Optional notes') . '" class="w-full border rounded px-3 py-2">';
        echo '</div>';
        echo '</div>';

        // Child selection grid
        echo '<div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">';
        foreach ($checkedInChildren as $child) {
            $childName = Format::name('', $child['preferredName'], $child['surname'], 'Student', false, true);
            $image = !empty($child['image_240']) ? $child['image_240'] : 'themes/Default/img/anonymous_240.jpg';

            // Check how many diaper changes this child has had today
            $changeCount = $diaperGateway->countDiaperChangesByPersonAndDate($child['gibbonPersonID'], $date);
            $changeBadge = '';
            if ($changeCount > 0) {
                $changeBadge = '<div class="flex flex-wrap gap-1 justify-center mt-1">';
                $changeBadge .= '<span class="bg-blue-200 text-blue-800 text-xs px-1 rounded">' . $changeCount . ' ' . __('change(s)') . '</span>';
                $changeBadge .= '</div>';
            }

            echo '<div class="bg-white rounded-lg shadow p-3 text-center hover:shadow-lg transition-shadow">';
            echo '<img src="' . $session->get('absoluteURL') . '/' . $image . '" class="w-16 h-16 rounded-full mx-auto mb-2 object-cover" alt="' . htmlspecialchars($childName) . '">';
            echo '<p class="text-sm font-medium truncate">' . htmlspecialchars($childName) . '</p>';
            echo $changeBadge;
            echo '<button type="submit" name="gibbonPersonID" value="' . $child['gibbonPersonID'] . '" class="mt-2 bg-green-500 text-white text-xs px-3 py-1 rounded hover:bg-green-600">' . __('Log Change') . '</button>';
            echo '</div>';
        }
        echo '</div>';

        echo '</form>';
        echo '</div>';
    } else {
        echo '<p class="text-gray-500 mb-4">' . __('No children are currently checked in.') . '</p>';
    }

    // Section: Bulk Diaper Logging
    echo '<h3 class="text-lg font-semibold mt-6 mb-3">' . __('Bulk Diaper Logging') . '</h3>';

    if ($checkedInChildren->rowCount() > 0) {
        // Reset the result pointer
        $checkedInChildren = $attendanceGateway->selectChildrenCurrentlyCheckedIn($gibbonSchoolYearID, $date);

        echo '<div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">';
        echo '<form method="post" action="' . $session->get('absoluteURL') . '/index.php?q=/modules/CareTracking/careTracking_diapers.php&date=' . $date . '">';
        echo '<input type="hidden" name="action" value="logBulkDiaper">';

        echo '<p class="text-sm text-gray-600 mb-3">' . __('Select multiple children to log the same diaper change for all at once.') . '</p>';

        // Type and time selection
        echo '<div class="mb-4 flex flex-wrap gap-4">';
        echo '<div>';
        echo '<label class="block text-sm font-medium mb-1">' . __('Diaper Type') . '</label>';
        echo '<select name="diaperType" class="border rounded px-3 py-2" required>';
        echo '<option value="">' . __('Select Type') . '</option>';
        foreach ($diaperTypes as $value => $label) {
            echo '<option value="' . htmlspecialchars($value) . '">' . htmlspecialchars($label) . '</option>';
        }
        echo '</select>';
        echo '</div>';

        echo '<div>';
        echo '<label class="block text-sm font-medium mb-1">' . __('Time') . '</label>';
        echo '<input type="time" name="changeTime" value="' . date('H:i') . '" class="border rounded px-3 py-2">';
        echo '</div>';

        echo '<div class="flex-1">';
        echo '<label class="block text-sm font-medium mb-1">' . __('Notes') . '</label>';
        echo '<input type="text" name="notes" placeholder="' . __('Optional notes') . '" class="w-full border rounded px-3 py-2">';
        echo '</div>';
        echo '</div>';

        // Child selection with checkboxes
        echo '<div class="mb-3">';
        echo '<label class="flex items-center text-sm font-medium mb-2">';
        echo '<input type="checkbox" id="selectAll" class="mr-2">';
        echo __('Select All');
        echo '</label>';
        echo '</div>';

        echo '<div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">';
        foreach ($checkedInChildren as $child) {
            $childName = Format::name('', $child['preferredName'], $child['surname'], 'Student', false, true);
            $image = !empty($child['image_240']) ? $child['image_240'] : 'themes/Default/img/anonymous_240.jpg';

            echo '<label class="bg-white rounded-lg shadow p-3 text-center hover:shadow-lg transition-shadow cursor-pointer">';
            echo '<input type="checkbox" name="childIDs[]" value="' . $child['gibbonPersonID'] . '" class="childCheckbox mb-2">';
            echo '<img src="' . $session->get('absoluteURL') . '/' . $image . '" class="w-12 h-12 rounded-full mx-auto mb-2 object-cover" alt="' . htmlspecialchars($childName) . '">';
            echo '<p class="text-sm font-medium truncate">' . htmlspecialchars($childName) . '</p>';
            echo '</label>';
        }
        echo '</div>';

        echo '<div class="mt-4">';
        echo '<button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">' . __('Log Change for Selected') . '</button>';
        echo '</div>';

        echo '</form>';

        // JavaScript for Select All functionality
        echo '<script>
        document.getElementById("selectAll").addEventListener("change", function() {
            var checkboxes = document.getElementsByClassName("childCheckbox");
            for (var i = 0; i < checkboxes.length; i++) {
                checkboxes[i].checked = this.checked;
            }
        });
        </script>';

        echo '</div>';
    }

    // Section: Today's Diaper Records
    echo '<h3 class="text-lg font-semibold mt-6 mb-3">' . __('Diaper Records') . '</h3>';

    // Build query criteria
    $criteria = $diaperGateway->newQueryCriteria()
        ->sortBy(['time', 'surname', 'preferredName'])
        ->fromPOST();

    // Get diaper data for the date
    $diapers = $diaperGateway->queryDiapersByDate($criteria, $gibbonSchoolYearID, $date);

    // Build DataTable
    $table = DataTable::createPaginated('diapers', $criteria);
    $table->setTitle(__('Diaper Records'));

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

    $table->addColumn('time', __('Time'))
        ->sortable()
        ->format(function ($row) {
            return Format::time($row['time']);
        });

    $table->addColumn('type', __('Type'))
        ->sortable()
        ->format(function ($row) {
            $colors = [
                'Wet'    => 'blue',
                'Soiled' => 'yellow',
                'Both'   => 'orange',
                'Dry'    => 'green',
            ];
            $color = $colors[$row['type']] ?? 'gray';
            return '<span class="bg-' . $color . '-100 text-' . $color . '-800 text-xs px-2 py-1 rounded">' . __($row['type']) . '</span>';
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

    $table->addColumn('logged', __('Logged'))
        ->notSortable()
        ->format(function ($row) {
            return Format::time($row['timestampCreated']);
        });

    // Output table
    if ($diapers->count() > 0) {
        echo $table->render($diapers);
    } else {
        echo '<div class="bg-gray-50 rounded-lg p-4 text-center text-gray-500">';
        echo __('No diaper records found for this date.');
        echo '</div>';
    }

    // Link back to dashboard
    echo '<div class="mt-4">';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/CareTracking/careTracking.php&date=' . $date . '" class="text-blue-600 hover:underline">&larr; ' . __('Back to Dashboard') . '</a>';
    echo '</div>';
}
