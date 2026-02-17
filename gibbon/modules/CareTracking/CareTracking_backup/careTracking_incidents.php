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
use Gibbon\Module\CareTracking\Domain\IncidentGateway;
use Gibbon\Module\CareTracking\Domain\AttendanceGateway;

// Module setup - breadcrumbs
$page->breadcrumbs->add(__('Care Tracking'), 'careTracking.php');
$page->breadcrumbs->add(__('Incidents'));

// Access check
if (!isActionAccessible($guid, $connection2, '/modules/CareTracking/careTracking_incidents.php')) {
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
    $incidentGateway = $container->get(IncidentGateway::class);
    $attendanceGateway = $container->get(AttendanceGateway::class);

    // Incident type options
    $incidentTypes = [
        'Minor Injury' => __('Minor Injury'),
        'Major Injury' => __('Major Injury'),
        'Illness'      => __('Illness'),
        'Behavioral'   => __('Behavioral'),
        'Other'        => __('Other'),
    ];

    // Severity options
    $severityOptions = [
        'Low'      => __('Low'),
        'Medium'   => __('Medium'),
        'High'     => __('High'),
        'Critical' => __('Critical'),
    ];

    // Handle incident logging action
    $action = $_POST['action'] ?? '';
    $childID = $_POST['gibbonPersonID'] ?? null;

    if ($action === 'logIncident' && !empty($childID)) {
        $incidentType = $_POST['incidentType'] ?? '';
        $severity = $_POST['severity'] ?? 'Low';
        $incidentTime = $_POST['incidentTime'] ?? date('H:i:s');
        $description = $_POST['description'] ?? '';
        $actionTaken = $_POST['actionTaken'] ?? null;

        if (!empty($incidentType) && !empty($description)) {
            $result = $incidentGateway->logIncident(
                $childID,
                $gibbonSchoolYearID,
                $date,
                $incidentTime,
                $incidentType,
                $severity,
                $description,
                $gibbonPersonID,
                $actionTaken
            );

            if ($result !== false) {
                $page->addSuccess(__('Incident has been logged successfully.'));
            } else {
                $page->addError(__('Failed to log incident.'));
            }
        } else {
            $page->addError(__('Please fill in all required fields.'));
        }
    }

    // Handle parent notification action
    if ($action === 'notifyParent') {
        $incidentID = $_POST['gibbonCareIncidentID'] ?? null;
        if (!empty($incidentID)) {
            $result = $incidentGateway->markParentNotified($incidentID);
            if ($result) {
                $page->addSuccess(__('Parent has been marked as notified.'));
            } else {
                $page->addError(__('Failed to update notification status.'));
            }
        }
    }

    // Page header
    echo '<h2>' . __('Incident Reporting') . '</h2>';

    // Date navigation form
    $form = Form::create('dateFilter', $session->get('absoluteURL') . '/index.php');
    $form->setMethod('get');
    $form->addHiddenValue('q', '/modules/CareTracking/careTracking_incidents.php');

    $row = $form->addRow();
    $row->addLabel('date', __('Date'));
    $row->addDate('date')->setValue(Format::date($date))->required();

    $row = $form->addRow();
    $row->addSubmit(__('Go'));

    echo $form->getOutput();

    // Display formatted date
    echo '<p class="text-lg mb-4">' . __('Showing incidents for') . ': <strong>' . Format::date($date) . '</strong></p>';

    // Get summary statistics
    $summary = $incidentGateway->getIncidentSummaryByDate($gibbonSchoolYearID, $date);

    // Display summary
    echo '<div class="bg-white rounded-lg shadow p-4 mb-4">';
    echo '<h3 class="text-lg font-semibold mb-3">' . __('Today\'s Incident Summary') . '</h3>';
    echo '<div class="grid grid-cols-2 md:grid-cols-6 gap-4 text-center">';

    echo '<div class="bg-gray-50 rounded p-2">';
    echo '<span class="block text-2xl font-bold">' . ($summary['totalIncidents'] ?? 0) . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('Total Incidents') . '</span>';
    echo '</div>';

    echo '<div class="bg-gray-50 rounded p-2">';
    echo '<span class="block text-2xl font-bold">' . ($summary['childrenInvolved'] ?? 0) . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('Children Involved') . '</span>';
    echo '</div>';

    echo '<div class="bg-yellow-50 rounded p-2">';
    echo '<span class="block text-2xl font-bold text-yellow-600">' . ($summary['minorInjuries'] ?? 0) . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('Minor Injuries') . '</span>';
    echo '</div>';

    echo '<div class="bg-red-50 rounded p-2">';
    echo '<span class="block text-2xl font-bold text-red-600">' . ($summary['majorInjuries'] ?? 0) . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('Major Injuries') . '</span>';
    echo '</div>';

    echo '<div class="bg-blue-50 rounded p-2">';
    echo '<span class="block text-2xl font-bold text-blue-600">' . ($summary['illnesses'] ?? 0) . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('Illnesses') . '</span>';
    echo '</div>';

    echo '<div class="bg-orange-50 rounded p-2">';
    echo '<span class="block text-2xl font-bold text-orange-600">' . ($summary['behavioral'] ?? 0) . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('Behavioral') . '</span>';
    echo '</div>';

    echo '</div>';

    // Parent notification status
    if (($summary['totalIncidents'] ?? 0) > 0) {
        $notified = $summary['parentsNotified'] ?? 0;
        $acknowledged = $summary['parentsAcknowledged'] ?? 0;
        $total = $summary['totalIncidents'];

        echo '<div class="mt-3 pt-3 border-t grid grid-cols-2 gap-4 text-center">';
        echo '<div>';
        echo '<span class="text-sm text-gray-600">' . __('Parents Notified') . ': </span>';
        echo '<span class="font-semibold">' . $notified . '/' . $total . '</span>';
        echo '</div>';
        echo '<div>';
        echo '<span class="text-sm text-gray-600">' . __('Parents Acknowledged') . ': </span>';
        echo '<span class="font-semibold">' . $acknowledged . '/' . $total . '</span>';
        echo '</div>';
        echo '</div>';
    }

    echo '</div>';

    // Section: Pending Parent Notifications (high severity incidents not yet notified)
    $pendingNotifications = $incidentGateway->selectIncidentsPendingNotification($gibbonSchoolYearID);

    if ($pendingNotifications->rowCount() > 0) {
        echo '<h3 class="text-lg font-semibold mt-6 mb-3">' . __('Pending Parent Notifications') . '</h3>';
        echo '<div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">';
        echo '<p class="text-sm text-red-600 mb-3">' . __('These incidents require parent notification.') . '</p>';

        echo '<div class="space-y-2">';
        foreach ($pendingNotifications as $incident) {
            $childName = Format::name('', $incident['preferredName'], $incident['surname'], 'Student', false, true);
            $severityColor = match($incident['severity']) {
                'Critical' => 'red',
                'High' => 'orange',
                'Medium' => 'yellow',
                default => 'gray',
            };

            echo '<div class="bg-white rounded p-3 flex items-center justify-between">';
            echo '<div>';
            echo '<span class="font-medium">' . htmlspecialchars($childName) . '</span>';
            echo ' - <span class="text-sm">' . __($incident['type']) . '</span>';
            echo ' <span class="bg-' . $severityColor . '-100 text-' . $severityColor . '-800 text-xs px-2 py-1 rounded">' . __($incident['severity']) . '</span>';
            echo '<p class="text-sm text-gray-600 mt-1">' . htmlspecialchars(substr($incident['description'], 0, 100)) . (strlen($incident['description']) > 100 ? '...' : '') . '</p>';
            echo '</div>';
            echo '<form method="post" action="' . $session->get('absoluteURL') . '/index.php?q=/modules/CareTracking/careTracking_incidents.php&date=' . $date . '" class="ml-4">';
            echo '<input type="hidden" name="action" value="notifyParent">';
            echo '<input type="hidden" name="gibbonCareIncidentID" value="' . $incident['gibbonCareIncidentID'] . '">';
            echo '<button type="submit" class="bg-green-500 text-white text-xs px-3 py-1 rounded hover:bg-green-600">' . __('Mark Notified') . '</button>';
            echo '</form>';
            echo '</div>';
        }
        echo '</div>';

        echo '</div>';
    }

    // Section: Report New Incident
    echo '<h3 class="text-lg font-semibold mt-6 mb-3">' . __('Report New Incident') . '</h3>';

    // Get children currently checked in
    $checkedInChildren = $attendanceGateway->selectChildrenCurrentlyCheckedIn($gibbonSchoolYearID, $date);

    if ($checkedInChildren->rowCount() > 0) {
        echo '<div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">';
        echo '<form method="post" action="' . $session->get('absoluteURL') . '/index.php?q=/modules/CareTracking/careTracking_incidents.php&date=' . $date . '">';
        echo '<input type="hidden" name="action" value="logIncident">';

        // Incident details
        echo '<div class="mb-4 grid grid-cols-1 md:grid-cols-4 gap-4">';

        echo '<div>';
        echo '<label class="block text-sm font-medium mb-1">' . __('Incident Type') . ' <span class="text-red-500">*</span></label>';
        echo '<select name="incidentType" class="w-full border rounded px-3 py-2" required>';
        echo '<option value="">' . __('Select Type') . '</option>';
        foreach ($incidentTypes as $value => $label) {
            echo '<option value="' . htmlspecialchars($value) . '">' . htmlspecialchars($label) . '</option>';
        }
        echo '</select>';
        echo '</div>';

        echo '<div>';
        echo '<label class="block text-sm font-medium mb-1">' . __('Severity') . '</label>';
        echo '<select name="severity" class="w-full border rounded px-3 py-2">';
        foreach ($severityOptions as $value => $label) {
            $selected = $value === 'Low' ? ' selected' : '';
            echo '<option value="' . htmlspecialchars($value) . '"' . $selected . '>' . htmlspecialchars($label) . '</option>';
        }
        echo '</select>';
        echo '</div>';

        echo '<div>';
        echo '<label class="block text-sm font-medium mb-1">' . __('Time') . '</label>';
        echo '<input type="time" name="incidentTime" value="' . date('H:i') . '" class="w-full border rounded px-3 py-2">';
        echo '</div>';

        echo '<div class="md:col-span-1">';
        echo '<label class="block text-sm font-medium mb-1">' . __('Child') . ' <span class="text-red-500">*</span></label>';
        echo '<select name="gibbonPersonID" class="w-full border rounded px-3 py-2" required>';
        echo '<option value="">' . __('Select Child') . '</option>';
        foreach ($checkedInChildren as $child) {
            $childName = Format::name('', $child['preferredName'], $child['surname'], 'Student', false, true);
            echo '<option value="' . $child['gibbonPersonID'] . '">' . htmlspecialchars($childName) . '</option>';
        }
        echo '</select>';
        echo '</div>';

        echo '</div>';

        echo '<div class="mb-4">';
        echo '<label class="block text-sm font-medium mb-1">' . __('Description') . ' <span class="text-red-500">*</span></label>';
        echo '<textarea name="description" rows="3" class="w-full border rounded px-3 py-2" placeholder="' . __('Describe what happened...') . '" required></textarea>';
        echo '</div>';

        echo '<div class="mb-4">';
        echo '<label class="block text-sm font-medium mb-1">' . __('Action Taken') . '</label>';
        echo '<textarea name="actionTaken" rows="2" class="w-full border rounded px-3 py-2" placeholder="' . __('What action was taken? (optional)') . '"></textarea>';
        echo '</div>';

        echo '<button type="submit" class="bg-yellow-500 text-white px-4 py-2 rounded hover:bg-yellow-600">' . __('Report Incident') . '</button>';

        echo '</form>';
        echo '</div>';
    } else {
        echo '<p class="text-gray-500 mb-4">' . __('No children are currently checked in.') . '</p>';
    }

    // Section: Today's Incident Records
    echo '<h3 class="text-lg font-semibold mt-6 mb-3">' . __('Incident Records') . '</h3>';

    // Build query criteria
    $criteria = $incidentGateway->newQueryCriteria()
        ->sortBy(['time', 'severity'])
        ->fromPOST();

    // Get incident data for the date
    $incidents = $incidentGateway->queryIncidentsByDate($criteria, $gibbonSchoolYearID, $date);

    // Build DataTable
    $table = DataTable::createPaginated('incidents', $criteria);
    $table->setTitle(__('Incident Records'));

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
                'Minor Injury' => 'yellow',
                'Major Injury' => 'red',
                'Illness'      => 'blue',
                'Behavioral'   => 'orange',
                'Other'        => 'gray',
            ];
            $color = $colors[$row['type']] ?? 'gray';
            return '<span class="bg-' . $color . '-100 text-' . $color . '-800 text-xs px-2 py-1 rounded">' . __($row['type']) . '</span>';
        });

    $table->addColumn('severity', __('Severity'))
        ->sortable()
        ->format(function ($row) {
            $colors = [
                'Critical' => 'red',
                'High'     => 'orange',
                'Medium'   => 'yellow',
                'Low'      => 'green',
            ];
            $color = $colors[$row['severity']] ?? 'gray';
            return '<span class="bg-' . $color . '-100 text-' . $color . '-800 text-xs px-2 py-1 rounded">' . __($row['severity']) . '</span>';
        });

    $table->addColumn('description', __('Description'))
        ->format(function ($row) {
            return '<span class="text-sm text-gray-600" title="' . htmlspecialchars($row['description']) . '">' .
                   htmlspecialchars(substr($row['description'], 0, 40)) .
                   (strlen($row['description']) > 40 ? '...' : '') . '</span>';
        });

    $table->addColumn('parentNotified', __('Notified'))
        ->notSortable()
        ->format(function ($row) {
            if ($row['parentNotified'] === 'Y') {
                return '<span class="text-green-600" title="' . __('Parent Notified') . '">&#10003;</span>';
            }
            return '<span class="text-red-600" title="' . __('Not Notified') . '">&#10007;</span>';
        });

    $table->addColumn('parentAcknowledged', __('Acknowledged'))
        ->notSortable()
        ->format(function ($row) {
            if ($row['parentAcknowledged'] === 'Y') {
                return '<span class="text-green-600" title="' . __('Parent Acknowledged') . '">&#10003;</span>';
            }
            return '<span class="text-gray-400">-</span>';
        });

    // Output table
    if ($incidents->count() > 0) {
        echo $table->render($incidents);
    } else {
        echo '<div class="bg-gray-50 rounded-lg p-4 text-center text-gray-500">';
        echo __('No incident records found for this date.');
        echo '</div>';
    }

    // Link back to dashboard
    echo '<div class="mt-4">';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/CareTracking/careTracking.php&date=' . $date . '" class="text-blue-600 hover:underline">&larr; ' . __('Back to Dashboard') . '</a>';
    echo '</div>';
}
