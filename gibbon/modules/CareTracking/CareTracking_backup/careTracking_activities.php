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
use Gibbon\Module\CareTracking\Domain\ActivityGateway;
use Gibbon\Module\CareTracking\Domain\AttendanceGateway;

// Module setup - breadcrumbs
$page->breadcrumbs->add(__('Care Tracking'), 'careTracking.php');
$page->breadcrumbs->add(__('Activities'));

// Access check
if (!isActionAccessible($guid, $connection2, '/modules/CareTracking/careTracking_activities.php')) {
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
    $activityGateway = $container->get(ActivityGateway::class);
    $attendanceGateway = $container->get(AttendanceGateway::class);

    // Activity type options
    $activityTypes = [
        'Art'       => __('Art'),
        'Music'     => __('Music'),
        'Physical'  => __('Physical'),
        'Language'  => __('Language'),
        'Math'      => __('Math'),
        'Science'   => __('Science'),
        'Social'    => __('Social'),
        'Free Play' => __('Free Play'),
        'Outdoor'   => __('Outdoor'),
        'Other'     => __('Other'),
    ];

    // Participation level options
    $participationLevels = [
        'Not Interested' => __('Not Interested'),
        'Observing'      => __('Observing'),
        'Participating'  => __('Participating'),
        'Leading'        => __('Leading'),
    ];

    // Handle activity logging action
    $action = $_POST['action'] ?? '';
    $childID = $_POST['gibbonPersonID'] ?? null;

    if ($action === 'logActivity' && !empty($childID)) {
        $activityName = $_POST['activityName'] ?? '';
        $activityType = $_POST['activityType'] ?? '';
        $duration = !empty($_POST['duration']) ? intval($_POST['duration']) : null;
        $participation = $_POST['participation'] ?? null;
        $notes = !empty($_POST['notes']) ? $_POST['notes'] : null;

        if (!empty($activityName) && !empty($activityType)) {
            $result = $activityGateway->logActivity(
                $childID,
                $gibbonSchoolYearID,
                $date,
                $activityName,
                $activityType,
                $gibbonPersonID,
                $duration,
                $participation,
                false, // aiSuggested
                null,  // aiActivityID
                $notes
            );

            if ($result !== false) {
                $page->addSuccess(__('Activity has been logged successfully.'));
            } else {
                $page->addError(__('Failed to log activity.'));
            }
        } else {
            $page->addError(__('Please fill in all required fields.'));
        }
    }

    // Handle bulk activity logging
    if ($action === 'bulkLogActivity') {
        $selectedChildren = $_POST['selectedChildren'] ?? [];
        $activityName = $_POST['activityName'] ?? '';
        $activityType = $_POST['activityType'] ?? '';
        $duration = !empty($_POST['duration']) ? intval($_POST['duration']) : null;
        $participation = $_POST['participation'] ?? null;
        $notes = !empty($_POST['notes']) ? $_POST['notes'] : null;

        if (!empty($selectedChildren) && !empty($activityName) && !empty($activityType)) {
            $successCount = 0;
            foreach ($selectedChildren as $childID) {
                $result = $activityGateway->logActivity(
                    $childID,
                    $gibbonSchoolYearID,
                    $date,
                    $activityName,
                    $activityType,
                    $gibbonPersonID,
                    $duration,
                    $participation,
                    false,
                    null,
                    $notes
                );
                if ($result !== false) {
                    $successCount++;
                }
            }
            if ($successCount > 0) {
                $page->addSuccess(sprintf(__('Activity logged for %d children.'), $successCount));
            }
        } else {
            $page->addError(__('Please select children and fill in required fields.'));
        }
    }

    // Page header
    echo '<h2>' . __('Activity Tracking') . '</h2>';

    // Date navigation form
    $form = Form::create('dateFilter', $session->get('absoluteURL') . '/index.php');
    $form->setMethod('get');
    $form->addHiddenValue('q', '/modules/CareTracking/careTracking_activities.php');

    $row = $form->addRow();
    $row->addLabel('date', __('Date'));
    $row->addDate('date')->setValue(Format::date($date))->required();

    $row = $form->addRow();
    $row->addSubmit(__('Go'));

    echo $form->getOutput();

    // Display formatted date
    echo '<p class="text-lg mb-4">' . __('Showing activities for') . ': <strong>' . Format::date($date) . '</strong></p>';

    // Get summary statistics
    $summary = $activityGateway->getActivitySummaryByDate($gibbonSchoolYearID, $date);

    // Display summary
    echo '<div class="bg-white rounded-lg shadow p-4 mb-4">';
    echo '<h3 class="text-lg font-semibold mb-3">' . __('Today\'s Activity Summary') . '</h3>';
    echo '<div class="grid grid-cols-2 md:grid-cols-5 gap-4 text-center">';

    echo '<div class="bg-gray-50 rounded p-2">';
    echo '<span class="block text-2xl font-bold">' . ($summary['totalActivities'] ?? 0) . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('Total Activities') . '</span>';
    echo '</div>';

    echo '<div class="bg-blue-50 rounded p-2">';
    echo '<span class="block text-2xl font-bold text-blue-600">' . ($summary['childrenParticipated'] ?? 0) . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('Children Participated') . '</span>';
    echo '</div>';

    echo '<div class="bg-green-50 rounded p-2">';
    echo '<span class="block text-2xl font-bold text-green-600">' . ($summary['uniqueActivities'] ?? 0) . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('Unique Activities') . '</span>';
    echo '</div>';

    $totalDuration = $summary['totalDurationMinutes'] ?? 0;
    $hours = floor($totalDuration / 60);
    $mins = $totalDuration % 60;
    $durationDisplay = $hours > 0 ? sprintf('%dh %dm', $hours, $mins) : sprintf('%dm', $mins);

    echo '<div class="bg-purple-50 rounded p-2">';
    echo '<span class="block text-2xl font-bold text-purple-600">' . $durationDisplay . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('Total Duration') . '</span>';
    echo '</div>';

    $avgDuration = round($summary['avgDurationMinutes'] ?? 0);
    echo '<div class="bg-orange-50 rounded p-2">';
    echo '<span class="block text-2xl font-bold text-orange-600">' . $avgDuration . 'm</span>';
    echo '<span class="text-xs text-gray-500">' . __('Avg Duration') . '</span>';
    echo '</div>';

    echo '</div>';

    // Activity type breakdown
    if (($summary['totalActivities'] ?? 0) > 0) {
        echo '<div class="mt-3 pt-3 border-t">';
        echo '<p class="text-sm text-gray-600 mb-2">' . __('Activity Types') . ':</p>';
        echo '<div class="flex flex-wrap gap-2">';

        $typeBreakdown = [
            'Art'       => ['count' => $summary['artActivities'] ?? 0, 'color' => 'pink'],
            'Music'     => ['count' => $summary['musicActivities'] ?? 0, 'color' => 'purple'],
            'Physical'  => ['count' => $summary['physicalActivities'] ?? 0, 'color' => 'red'],
            'Language'  => ['count' => $summary['languageActivities'] ?? 0, 'color' => 'blue'],
            'Math'      => ['count' => $summary['mathActivities'] ?? 0, 'color' => 'green'],
            'Science'   => ['count' => $summary['scienceActivities'] ?? 0, 'color' => 'teal'],
            'Social'    => ['count' => $summary['socialActivities'] ?? 0, 'color' => 'yellow'],
            'Free Play' => ['count' => $summary['freePlayActivities'] ?? 0, 'color' => 'orange'],
            'Outdoor'   => ['count' => $summary['outdoorActivities'] ?? 0, 'color' => 'lime'],
        ];

        foreach ($typeBreakdown as $type => $data) {
            if ($data['count'] > 0) {
                echo '<span class="bg-' . $data['color'] . '-100 text-' . $data['color'] . '-800 text-xs px-2 py-1 rounded">';
                echo __($type) . ': ' . $data['count'];
                echo '</span>';
            }
        }

        if (($summary['aiSuggestedActivities'] ?? 0) > 0) {
            echo '<span class="bg-indigo-100 text-indigo-800 text-xs px-2 py-1 rounded">';
            echo '🤖 ' . __('AI Suggested') . ': ' . $summary['aiSuggestedActivities'];
            echo '</span>';
        }

        echo '</div>';
        echo '</div>';
    }

    echo '</div>';

    // Section: Children Without Activities Today
    $childrenWithoutActivities = $activityGateway->selectChildrenWithoutActivities($gibbonSchoolYearID, $date);

    if ($childrenWithoutActivities->rowCount() > 0) {
        echo '<h3 class="text-lg font-semibold mt-6 mb-3">' . __('Children Without Activities Today') . '</h3>';
        echo '<div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">';
        echo '<p class="text-sm text-yellow-600 mb-3">' . __('These checked-in children have not participated in any activities today.') . '</p>';

        echo '<div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-2">';
        foreach ($childrenWithoutActivities as $child) {
            $childName = Format::name('', $child['preferredName'], $child['surname'], 'Student', false, true);
            $image = !empty($child['image_240']) ? $child['image_240'] : 'themes/Default/img/anonymous_240.jpg';

            echo '<div class="bg-white rounded p-2 flex items-center space-x-2">';
            echo '<img src="' . $session->get('absoluteURL') . '/' . $image . '" class="w-8 h-8 rounded-full object-cover" alt="">';
            echo '<span class="text-sm truncate">' . htmlspecialchars($childName) . '</span>';
            echo '</div>';
        }
        echo '</div>';

        echo '</div>';
    }

    // Section: Log New Activity
    echo '<h3 class="text-lg font-semibold mt-6 mb-3">' . __('Log New Activity') . '</h3>';

    // Get children currently checked in
    $checkedInChildren = $attendanceGateway->selectChildrenCurrentlyCheckedIn($gibbonSchoolYearID, $date);

    if ($checkedInChildren->rowCount() > 0) {
        // Quick individual logging section
        echo '<div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">';
        echo '<h4 class="font-medium mb-3">' . __('Quick Log') . '</h4>';

        echo '<form method="post" action="' . $session->get('absoluteURL') . '/index.php?q=/modules/CareTracking/careTracking_activities.php&date=' . $date . '">';
        echo '<input type="hidden" name="action" value="logActivity">';

        echo '<div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-4">';

        // Child selector
        echo '<div>';
        echo '<label class="block text-sm font-medium mb-1">' . __('Child') . ' <span class="text-red-500">*</span></label>';
        echo '<select name="gibbonPersonID" class="w-full border rounded px-3 py-2" required>';
        echo '<option value="">' . __('Select Child') . '</option>';
        foreach ($checkedInChildren as $child) {
            $childName = Format::name('', $child['preferredName'], $child['surname'], 'Student', false, true);
            echo '<option value="' . $child['gibbonPersonID'] . '">' . htmlspecialchars($childName) . '</option>';
        }
        echo '</select>';
        echo '</div>';

        // Activity name
        echo '<div>';
        echo '<label class="block text-sm font-medium mb-1">' . __('Activity Name') . ' <span class="text-red-500">*</span></label>';
        echo '<input type="text" name="activityName" class="w-full border rounded px-3 py-2" placeholder="' . __('e.g., Painting, Block Building') . '" required>';
        echo '</div>';

        // Activity type
        echo '<div>';
        echo '<label class="block text-sm font-medium mb-1">' . __('Type') . ' <span class="text-red-500">*</span></label>';
        echo '<select name="activityType" class="w-full border rounded px-3 py-2" required>';
        echo '<option value="">' . __('Select Type') . '</option>';
        foreach ($activityTypes as $value => $label) {
            echo '<option value="' . htmlspecialchars($value) . '">' . htmlspecialchars($label) . '</option>';
        }
        echo '</select>';
        echo '</div>';

        // Duration
        echo '<div>';
        echo '<label class="block text-sm font-medium mb-1">' . __('Duration (mins)') . '</label>';
        echo '<input type="number" name="duration" min="1" max="480" class="w-full border rounded px-3 py-2" placeholder="30">';
        echo '</div>';

        // Participation
        echo '<div>';
        echo '<label class="block text-sm font-medium mb-1">' . __('Participation') . '</label>';
        echo '<select name="participation" class="w-full border rounded px-3 py-2">';
        echo '<option value="">' . __('Select Level') . '</option>';
        foreach ($participationLevels as $value => $label) {
            echo '<option value="' . htmlspecialchars($value) . '">' . htmlspecialchars($label) . '</option>';
        }
        echo '</select>';
        echo '</div>';

        echo '</div>';

        // Notes
        echo '<div class="mb-4">';
        echo '<label class="block text-sm font-medium mb-1">' . __('Notes') . '</label>';
        echo '<textarea name="notes" rows="2" class="w-full border rounded px-3 py-2" placeholder="' . __('Optional notes about the activity...') . '"></textarea>';
        echo '</div>';

        echo '<button type="submit" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">' . __('Log Activity') . '</button>';

        echo '</form>';
        echo '</div>';

        // Bulk logging section
        echo '<div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">';
        echo '<h4 class="font-medium mb-3">' . __('Bulk Log Activity') . '</h4>';
        echo '<p class="text-sm text-gray-600 mb-3">' . __('Log the same activity for multiple children at once.') . '</p>';

        echo '<form method="post" action="' . $session->get('absoluteURL') . '/index.php?q=/modules/CareTracking/careTracking_activities.php&date=' . $date . '">';
        echo '<input type="hidden" name="action" value="bulkLogActivity">';

        // Activity details row
        echo '<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">';

        echo '<div>';
        echo '<label class="block text-sm font-medium mb-1">' . __('Activity Name') . ' <span class="text-red-500">*</span></label>';
        echo '<input type="text" name="activityName" class="w-full border rounded px-3 py-2" placeholder="' . __('e.g., Circle Time, Story Reading') . '" required>';
        echo '</div>';

        echo '<div>';
        echo '<label class="block text-sm font-medium mb-1">' . __('Type') . ' <span class="text-red-500">*</span></label>';
        echo '<select name="activityType" class="w-full border rounded px-3 py-2" required>';
        echo '<option value="">' . __('Select Type') . '</option>';
        foreach ($activityTypes as $value => $label) {
            echo '<option value="' . htmlspecialchars($value) . '">' . htmlspecialchars($label) . '</option>';
        }
        echo '</select>';
        echo '</div>';

        echo '<div>';
        echo '<label class="block text-sm font-medium mb-1">' . __('Duration (mins)') . '</label>';
        echo '<input type="number" name="duration" min="1" max="480" class="w-full border rounded px-3 py-2" placeholder="30">';
        echo '</div>';

        echo '<div>';
        echo '<label class="block text-sm font-medium mb-1">' . __('Participation') . '</label>';
        echo '<select name="participation" class="w-full border rounded px-3 py-2">';
        echo '<option value="">' . __('Select Level') . '</option>';
        foreach ($participationLevels as $value => $label) {
            echo '<option value="' . htmlspecialchars($value) . '">' . htmlspecialchars($label) . '</option>';
        }
        echo '</select>';
        echo '</div>';

        echo '</div>';

        // Notes for bulk
        echo '<div class="mb-4">';
        echo '<label class="block text-sm font-medium mb-1">' . __('Notes') . '</label>';
        echo '<textarea name="notes" rows="2" class="w-full border rounded px-3 py-2" placeholder="' . __('Optional notes about the activity...') . '"></textarea>';
        echo '</div>';

        // Select all checkbox
        echo '<div class="mb-2">';
        echo '<label class="inline-flex items-center">';
        echo '<input type="checkbox" id="selectAllActivities" class="mr-2">';
        echo '<span class="text-sm font-medium">' . __('Select All') . '</span>';
        echo '</label>';
        echo '</div>';

        // Children grid
        // Reset the result set pointer for checked-in children
        $checkedInChildren = $attendanceGateway->selectChildrenCurrentlyCheckedIn($gibbonSchoolYearID, $date);

        echo '<div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-2 mb-4">';
        foreach ($checkedInChildren as $child) {
            $childName = Format::name('', $child['preferredName'], $child['surname'], 'Student', false, true);
            $image = !empty($child['image_240']) ? $child['image_240'] : 'themes/Default/img/anonymous_240.jpg';

            // Get today's activity count for this child
            $childActivities = $activityGateway->selectActivitiesByPersonAndDate($child['gibbonPersonID'], $date);
            $activityCount = $childActivities->rowCount();

            echo '<label class="bg-white rounded p-2 flex items-center space-x-2 cursor-pointer hover:bg-gray-50 border">';
            echo '<input type="checkbox" name="selectedChildren[]" value="' . $child['gibbonPersonID'] . '" class="bulk-activity-checkbox">';
            echo '<img src="' . $session->get('absoluteURL') . '/' . $image . '" class="w-8 h-8 rounded-full object-cover" alt="">';
            echo '<span class="text-sm truncate flex-1">' . htmlspecialchars($childName) . '</span>';
            if ($activityCount > 0) {
                echo '<span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded">' . $activityCount . '</span>';
            }
            echo '</label>';
        }
        echo '</div>';

        echo '<button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">' . __('Log Activity for Selected') . '</button>';

        echo '</form>';
        echo '</div>';

        // JavaScript for select all
        echo '<script>
            document.getElementById("selectAllActivities").addEventListener("change", function() {
                var checkboxes = document.querySelectorAll(".bulk-activity-checkbox");
                checkboxes.forEach(function(checkbox) {
                    checkbox.checked = this.checked;
                }, this);
            });
        </script>';
    } else {
        echo '<p class="text-gray-500 mb-4">' . __('No children are currently checked in.') . '</p>';
    }

    // Section: Today's Activity Records
    echo '<h3 class="text-lg font-semibold mt-6 mb-3">' . __('Activity Records') . '</h3>';

    // Build query criteria
    $criteria = $activityGateway->newQueryCriteria()
        ->sortBy(['timestampCreated'], 'DESC')
        ->fromPOST();

    // Get activity data for the date
    $activities = $activityGateway->queryActivitiesByDate($criteria, $gibbonSchoolYearID, $date);

    // Build DataTable
    $table = DataTable::createPaginated('activities', $criteria);
    $table->setTitle(__('Activity Records'));

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

    $table->addColumn('activityName', __('Activity'))
        ->sortable()
        ->format(function ($row) {
            $aiIcon = $row['aiSuggested'] === 'Y' ? '🤖 ' : '';
            return $aiIcon . htmlspecialchars($row['activityName']);
        });

    $table->addColumn('activityType', __('Type'))
        ->sortable()
        ->format(function ($row) {
            $colors = [
                'Art'       => 'pink',
                'Music'     => 'purple',
                'Physical'  => 'red',
                'Language'  => 'blue',
                'Math'      => 'green',
                'Science'   => 'teal',
                'Social'    => 'yellow',
                'Free Play' => 'orange',
                'Outdoor'   => 'lime',
                'Other'     => 'gray',
            ];
            $color = $colors[$row['activityType']] ?? 'gray';
            return '<span class="bg-' . $color . '-100 text-' . $color . '-800 text-xs px-2 py-1 rounded">' . __($row['activityType']) . '</span>';
        });

    $table->addColumn('duration', __('Duration'))
        ->sortable()
        ->format(function ($row) {
            if (empty($row['duration'])) {
                return '-';
            }
            $duration = intval($row['duration']);
            if ($duration >= 60) {
                $hours = floor($duration / 60);
                $mins = $duration % 60;
                return sprintf('%dh %dm', $hours, $mins);
            }
            return $duration . 'm';
        });

    $table->addColumn('participation', __('Participation'))
        ->sortable()
        ->format(function ($row) {
            if (empty($row['participation'])) {
                return '-';
            }
            $colors = [
                'Leading'        => 'green',
                'Participating'  => 'blue',
                'Observing'      => 'yellow',
                'Not Interested' => 'gray',
            ];
            $color = $colors[$row['participation']] ?? 'gray';
            return '<span class="bg-' . $color . '-100 text-' . $color . '-800 text-xs px-2 py-1 rounded">' . __($row['participation']) . '</span>';
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

    $table->addColumn('time', __('Time'))
        ->notSortable()
        ->format(function ($row) {
            return Format::time($row['timestampCreated']);
        });

    // Output table
    if ($activities->count() > 0) {
        echo $table->render($activities);
    } else {
        echo '<div class="bg-gray-50 rounded-lg p-4 text-center text-gray-500">';
        echo __('No activity records found for this date.');
        echo '</div>';
    }

    // Link back to dashboard
    echo '<div class="mt-4">';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/CareTracking/careTracking.php&date=' . $date . '" class="text-blue-600 hover:underline">&larr; ' . __('Back to Dashboard') . '</a>';
    echo '</div>';
}
