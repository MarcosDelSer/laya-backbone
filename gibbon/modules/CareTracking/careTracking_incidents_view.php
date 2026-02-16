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
use Gibbon\Module\CareTracking\Domain\IncidentNotificationService;

// Module setup - breadcrumbs
$page->breadcrumbs->add(__('Care Tracking'), 'careTracking.php');
$page->breadcrumbs->add(__('Incidents'), 'careTracking_incidents.php');
$page->breadcrumbs->add(__('View Incident'));

// Access check
if (!isActionAccessible($guid, $connection2, '/modules/CareTracking/careTracking_incidents_view.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get incident ID from request
    $gibbonCareIncidentID = $_GET['gibbonCareIncidentID'] ?? null;

    if (empty($gibbonCareIncidentID)) {
        $page->addError(__('No incident specified.'));
    } else {
        // Get gateways via DI container
        $incidentGateway = $container->get(IncidentGateway::class);

        // Get the incident details
        $incident = $incidentGateway->getByID($gibbonCareIncidentID);

        if (empty($incident)) {
            $page->addError(__('The specified incident cannot be found.'));
        } else {
            $gibbonSchoolYearID = $session->get('gibbonSchoolYearID');
            $gibbonPersonID = $session->get('gibbonPersonID');

            // Get child details
            $childData = ['gibbonPersonID' => $incident['gibbonPersonID']];
            $childSql = "SELECT gibbonPersonID, preferredName, surname, image_240, dob, gender
                         FROM gibbonPerson
                         WHERE gibbonPersonID = :gibbonPersonID";
            $child = $pdo->selectOne($childSql, $childData);

            // Get recorded by staff details
            $recordedBy = null;
            if (!empty($incident['recordedByID'])) {
                $staffData = ['gibbonPersonID' => $incident['recordedByID']];
                $staffSql = "SELECT gibbonPersonID, preferredName, surname
                             FROM gibbonPerson
                             WHERE gibbonPersonID = :gibbonPersonID";
                $recordedBy = $pdo->selectOne($staffSql, $staffData);
            }

            // Handle actions
            $action = $_POST['action'] ?? '';

            // Handle parent notification action
            if ($action === 'notifyParent') {
                try {
                    $notificationService = $container->get(IncidentNotificationService::class);
                    $result = $notificationService->notifyParent($gibbonCareIncidentID);

                    if ($result['success']) {
                        $page->addSuccess(__('Parent has been notified successfully.'));
                        // Refresh incident data
                        $incident = $incidentGateway->getByID($gibbonCareIncidentID);
                    } else {
                        $page->addError(__('Failed to notify parent: ') . ($result['error'] ?? __('Unknown error')));
                    }
                } catch (\Exception $e) {
                    $page->addError(__('Failed to notify parent: ') . $e->getMessage());
                }
            }

            // Handle mark notified action
            if ($action === 'markNotified') {
                $result = $incidentGateway->markParentNotified($gibbonCareIncidentID);
                if ($result) {
                    $page->addSuccess(__('Parent has been marked as notified.'));
                    $incident = $incidentGateway->getByID($gibbonCareIncidentID);
                } else {
                    $page->addError(__('Failed to update notification status.'));
                }
            }

            // Handle director escalation action
            if ($action === 'escalateDirector') {
                try {
                    $notificationService = $container->get(IncidentNotificationService::class);
                    $reason = $_POST['escalationReason'] ?? 'Manual escalation';
                    $result = $notificationService->notifyDirector($gibbonCareIncidentID, $reason);

                    if ($result['success']) {
                        $page->addSuccess(__('Director has been notified successfully.'));
                        $incident = $incidentGateway->getByID($gibbonCareIncidentID);
                    } else {
                        $page->addError(__('Failed to notify director: ') . ($result['error'] ?? __('Unknown error')));
                    }
                } catch (\Exception $e) {
                    $page->addError(__('Failed to notify director: ') . $e->getMessage());
                }
            }

            // Page header with child info
            $childName = Format::name('', $child['preferredName'] ?? '', $child['surname'] ?? '', 'Student', false, true);
            echo '<h2>' . __('Incident Report') . ': ' . htmlspecialchars($childName) . '</h2>';

            // Severity badge color mapping
            $severityColors = [
                'Critical' => 'red',
                'High'     => 'orange',
                'Medium'   => 'yellow',
                'Low'      => 'green',
            ];

            // Type badge color mapping
            $typeColors = [
                'Minor Injury' => 'yellow',
                'Major Injury' => 'red',
                'Illness'      => 'blue',
                'Behavioral'   => 'orange',
                'Other'        => 'gray',
            ];

            $severityColor = $severityColors[$incident['severity']] ?? 'gray';
            $typeColor = $typeColors[$incident['type']] ?? 'gray';

            // Status banner based on severity
            if ($incident['severity'] === 'Critical' || $incident['severity'] === 'High') {
                echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">';
                echo '<p class="font-bold">' . __('High Severity Incident') . '</p>';
                echo '<p>' . __('This incident has been flagged as') . ' <strong>' . __($incident['severity']) . '</strong>. ';
                echo __('Ensure appropriate notifications and follow-up actions are completed.') . '</p>';
                echo '</div>';
            }

            // Main content grid
            echo '<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">';

            // Left column: Child info and basic details
            echo '<div class="lg:col-span-1">';

            // Child card
            echo '<div class="bg-white rounded-lg shadow p-4 mb-4">';
            echo '<h3 class="text-lg font-semibold mb-3 border-b pb-2">' . __('Child Information') . '</h3>';
            echo '<div class="flex items-center mb-3">';
            $image = !empty($child['image_240']) ? $child['image_240'] : 'themes/Default/img/anonymous_240.jpg';
            echo '<img src="' . $session->get('absoluteURL') . '/' . $image . '" class="w-16 h-16 rounded-full object-cover mr-4" alt="">';
            echo '<div>';
            echo '<p class="font-semibold text-lg">' . htmlspecialchars($childName) . '</p>';
            if (!empty($child['dob'])) {
                $age = date_diff(date_create($child['dob']), date_create('today'));
                echo '<p class="text-sm text-gray-600">' . __('Age') . ': ' . $age->y . ' ' . __('years') . ', ' . $age->m . ' ' . __('months') . '</p>';
            }
            echo '</div>';
            echo '</div>';
            echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/Students/student_view_details.php&gibbonPersonID=' . $incident['gibbonPersonID'] . '" class="text-blue-600 hover:underline text-sm">' . __('View Full Profile') . ' &rarr;</a>';
            echo '</div>';

            // Incident classification card
            echo '<div class="bg-white rounded-lg shadow p-4 mb-4">';
            echo '<h3 class="text-lg font-semibold mb-3 border-b pb-2">' . __('Incident Classification') . '</h3>';
            echo '<div class="space-y-2">';

            echo '<div class="flex justify-between items-center">';
            echo '<span class="text-gray-600">' . __('Type') . ':</span>';
            echo '<span class="bg-' . $typeColor . '-100 text-' . $typeColor . '-800 text-sm px-3 py-1 rounded-full">' . __($incident['type']) . '</span>';
            echo '</div>';

            echo '<div class="flex justify-between items-center">';
            echo '<span class="text-gray-600">' . __('Severity') . ':</span>';
            echo '<span class="bg-' . $severityColor . '-100 text-' . $severityColor . '-800 text-sm px-3 py-1 rounded-full font-semibold">' . __($incident['severity']) . '</span>';
            echo '</div>';

            if (!empty($incident['incidentCategory'])) {
                echo '<div class="flex justify-between items-center">';
                echo '<span class="text-gray-600">' . __('Category') . ':</span>';
                echo '<span class="text-gray-800">' . __($incident['incidentCategory']) . '</span>';
                echo '</div>';
            }

            if (!empty($incident['bodyPart'])) {
                echo '<div class="flex justify-between items-center">';
                echo '<span class="text-gray-600">' . __('Body Part') . ':</span>';
                echo '<span class="text-gray-800">' . __($incident['bodyPart']) . '</span>';
                echo '</div>';
            }

            echo '</div>';
            echo '</div>';

            // Medical & Follow-up card
            echo '<div class="bg-white rounded-lg shadow p-4 mb-4">';
            echo '<h3 class="text-lg font-semibold mb-3 border-b pb-2">' . __('Medical & Follow-up') . '</h3>';
            echo '<div class="space-y-2">';

            $medicalConsultedDisplay = ($incident['medicalConsulted'] ?? 'N') === 'Y' ? __('Yes') : __('No');
            $medicalBg = ($incident['medicalConsulted'] ?? 'N') === 'Y' ? 'bg-blue-50' : 'bg-gray-50';
            echo '<div class="flex justify-between items-center p-2 rounded ' . $medicalBg . '">';
            echo '<span class="text-gray-600">' . __('Medical Consulted') . ':</span>';
            echo '<span class="font-medium">' . $medicalConsultedDisplay . '</span>';
            echo '</div>';

            $followUpDisplay = ($incident['followUpRequired'] ?? 'N') === 'Y' ? __('Yes') : __('No');
            $followUpBg = ($incident['followUpRequired'] ?? 'N') === 'Y' ? 'bg-yellow-50' : 'bg-gray-50';
            echo '<div class="flex justify-between items-center p-2 rounded ' . $followUpBg . '">';
            echo '<span class="text-gray-600">' . __('Follow-up Required') . ':</span>';
            echo '<span class="font-medium">' . $followUpDisplay . '</span>';
            echo '</div>';

            echo '</div>';
            echo '</div>';

            echo '</div>'; // End left column

            // Middle column: Incident details
            echo '<div class="lg:col-span-2">';

            // Date/Time and Recording info
            echo '<div class="bg-white rounded-lg shadow p-4 mb-4">';
            echo '<h3 class="text-lg font-semibold mb-3 border-b pb-2">' . __('Incident Details') . '</h3>';

            echo '<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">';

            echo '<div class="bg-gray-50 rounded p-3">';
            echo '<p class="text-xs text-gray-500 uppercase tracking-wider">' . __('Date') . '</p>';
            echo '<p class="font-semibold">' . Format::date($incident['date']) . '</p>';
            echo '</div>';

            echo '<div class="bg-gray-50 rounded p-3">';
            echo '<p class="text-xs text-gray-500 uppercase tracking-wider">' . __('Time') . '</p>';
            echo '<p class="font-semibold">' . Format::time($incident['time']) . '</p>';
            echo '</div>';

            echo '<div class="bg-gray-50 rounded p-3">';
            echo '<p class="text-xs text-gray-500 uppercase tracking-wider">' . __('Recorded By') . '</p>';
            if ($recordedBy) {
                echo '<p class="font-semibold">' . Format::name('', $recordedBy['preferredName'], $recordedBy['surname'], 'Staff', false, true) . '</p>';
            } else {
                echo '<p class="text-gray-400">' . __('Unknown') . '</p>';
            }
            echo '</div>';

            echo '<div class="bg-gray-50 rounded p-3">';
            echo '<p class="text-xs text-gray-500 uppercase tracking-wider">' . __('Recorded At') . '</p>';
            echo '<p class="font-semibold">' . Format::dateTime($incident['timestampCreated']) . '</p>';
            echo '</div>';

            echo '</div>';

            // Description
            echo '<div class="mb-4">';
            echo '<h4 class="font-medium text-gray-700 mb-2">' . __('Description') . '</h4>';
            echo '<div class="bg-gray-50 rounded p-3 text-gray-800">';
            echo nl2br(htmlspecialchars($incident['description']));
            echo '</div>';
            echo '</div>';

            // Action Taken
            if (!empty($incident['actionTaken'])) {
                echo '<div class="mb-4">';
                echo '<h4 class="font-medium text-gray-700 mb-2">' . __('First Aid / Action Taken') . '</h4>';
                echo '<div class="bg-green-50 rounded p-3 text-gray-800">';
                echo nl2br(htmlspecialchars($incident['actionTaken']));
                echo '</div>';
                echo '</div>';
            }

            // Photo documentation
            if (!empty($incident['photoPath'])) {
                echo '<div class="mb-4">';
                echo '<h4 class="font-medium text-gray-700 mb-2">' . __('Photo Documentation') . '</h4>';
                echo '<div class="bg-gray-50 rounded p-3">';
                echo '<img src="' . $session->get('absoluteURL') . '/' . htmlspecialchars($incident['photoPath']) . '" class="max-w-full h-auto rounded shadow" alt="' . __('Incident Photo') . '">';
                echo '</div>';
                echo '</div>';
            }

            echo '</div>';

            // Notification Status Card
            echo '<div class="bg-white rounded-lg shadow p-4 mb-4">';
            echo '<h3 class="text-lg font-semibold mb-3 border-b pb-2">' . __('Notification Status') . '</h3>';

            echo '<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">';

            // Parent notification status
            $parentNotified = ($incident['parentNotified'] ?? 'N') === 'Y';
            $parentAcknowledged = ($incident['parentAcknowledged'] ?? 'N') === 'Y';

            echo '<div class="border rounded p-3 ' . ($parentNotified ? 'border-green-200 bg-green-50' : 'border-gray-200') . '">';
            echo '<div class="flex items-center justify-between">';
            echo '<div>';
            echo '<p class="font-medium">' . __('Parent Notification') . '</p>';
            if ($parentNotified) {
                echo '<p class="text-sm text-green-600">' . __('Notified') . '</p>';
                if (!empty($incident['parentNotifiedTime'])) {
                    echo '<p class="text-xs text-gray-500">' . Format::dateTime($incident['parentNotifiedTime']) . '</p>';
                }
            } else {
                echo '<p class="text-sm text-gray-500">' . __('Not yet notified') . '</p>';
            }
            echo '</div>';
            echo '<span class="text-2xl">' . ($parentNotified ? '&#10003;' : '&#10067;') . '</span>';
            echo '</div>';
            echo '</div>';

            // Parent acknowledgment status
            echo '<div class="border rounded p-3 ' . ($parentAcknowledged ? 'border-green-200 bg-green-50' : 'border-gray-200') . '">';
            echo '<div class="flex items-center justify-between">';
            echo '<div>';
            echo '<p class="font-medium">' . __('Parent Acknowledgment') . '</p>';
            if ($parentAcknowledged) {
                echo '<p class="text-sm text-green-600">' . __('Acknowledged') . '</p>';
                if (!empty($incident['parentAcknowledgedTime'])) {
                    echo '<p class="text-xs text-gray-500">' . Format::dateTime($incident['parentAcknowledgedTime']) . '</p>';
                }
            } else {
                echo '<p class="text-sm text-gray-500">' . __('Pending acknowledgment') . '</p>';
            }
            echo '</div>';
            echo '<span class="text-2xl">' . ($parentAcknowledged ? '&#10003;' : '&#8987;') . '</span>';
            echo '</div>';
            echo '</div>';

            echo '</div>';

            // Director notification status
            $directorNotified = ($incident['directorNotified'] ?? 'N') === 'Y';

            echo '<div class="border rounded p-3 ' . ($directorNotified ? 'border-blue-200 bg-blue-50' : 'border-gray-200') . '">';
            echo '<div class="flex items-center justify-between">';
            echo '<div>';
            echo '<p class="font-medium">' . __('Director Escalation') . '</p>';
            if ($directorNotified) {
                echo '<p class="text-sm text-blue-600">' . __('Director has been notified') . '</p>';
                if (!empty($incident['directorNotifiedTime'])) {
                    echo '<p class="text-xs text-gray-500">' . Format::dateTime($incident['directorNotifiedTime']) . '</p>';
                }
            } else {
                echo '<p class="text-sm text-gray-500">' . __('Not escalated to director') . '</p>';
            }
            echo '</div>';
            echo '<span class="text-2xl">' . ($directorNotified ? '&#128101;' : '-') . '</span>';
            echo '</div>';
            echo '</div>';

            // Action buttons
            if (!$parentNotified || !$directorNotified) {
                echo '<div class="mt-4 pt-3 border-t flex flex-wrap gap-2">';

                if (!$parentNotified) {
                    echo '<form method="post" class="inline">';
                    echo '<input type="hidden" name="action" value="notifyParent">';
                    echo '<button type="submit" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600 text-sm">';
                    echo __('Send Parent Notification') . '</button>';
                    echo '</form>';

                    echo '<form method="post" class="inline">';
                    echo '<input type="hidden" name="action" value="markNotified">';
                    echo '<button type="submit" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600 text-sm">';
                    echo __('Mark as Notified (Manual)') . '</button>';
                    echo '</form>';
                }

                if (!$directorNotified) {
                    echo '<form method="post" class="inline">';
                    echo '<input type="hidden" name="action" value="escalateDirector">';
                    echo '<input type="hidden" name="escalationReason" value="Manual escalation">';
                    echo '<button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 text-sm">';
                    echo __('Escalate to Director') . '</button>';
                    echo '</form>';
                }

                echo '</div>';
            }

            echo '</div>';

            // Linked Intervention Plan (if any)
            if (!empty($incident['linkedInterventionPlanID'])) {
                // Get intervention plan details
                $planData = ['planID' => $incident['linkedInterventionPlanID']];
                $planSql = "SELECT * FROM gibbonIndividualNeedsPlan WHERE gibbonIndividualNeedsPlanID = :planID";
                $plan = $pdo->selectOne($planSql, $planData);

                if ($plan) {
                    echo '<div class="bg-white rounded-lg shadow p-4 mb-4">';
                    echo '<h3 class="text-lg font-semibold mb-3 border-b pb-2">' . __('Linked Intervention Plan') . '</h3>';
                    echo '<div class="bg-purple-50 rounded p-3">';
                    echo '<p class="text-purple-800"><strong>' . __('Plan ID') . ':</strong> ' . $incident['linkedInterventionPlanID'] . '</p>';
                    echo '<p class="text-sm text-gray-600 mt-2">' . __('This incident is linked to an intervention plan. Review the plan for additional context and recommended actions.') . '</p>';
                    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/Individual Needs/individualNeeds_view_details.php&gibbonPersonID=' . $incident['gibbonPersonID'] . '" class="text-purple-600 hover:underline text-sm mt-2 inline-block">' . __('View Intervention Plan') . ' &rarr;</a>';
                    echo '</div>';
                    echo '</div>';
                }
            }

            echo '</div>'; // End middle column

            echo '</div>'; // End grid

            // Timeline section
            echo '<div class="bg-white rounded-lg shadow p-4 mb-4">';
            echo '<h3 class="text-lg font-semibold mb-3 border-b pb-2">' . __('Incident Timeline') . '</h3>';

            echo '<div class="relative">';
            echo '<div class="absolute left-4 top-0 bottom-0 w-0.5 bg-gray-200"></div>';

            // Timeline events
            $timelineEvents = [];

            // Incident occurred
            $timelineEvents[] = [
                'time' => $incident['date'] . ' ' . $incident['time'],
                'title' => __('Incident Occurred'),
                'description' => __($incident['type']) . ' - ' . __($incident['severity']) . ' severity',
                'icon' => '&#9888;',
                'color' => $severityColor,
            ];

            // Incident recorded
            $timelineEvents[] = [
                'time' => $incident['timestampCreated'],
                'title' => __('Incident Recorded'),
                'description' => $recordedBy ? __('Recorded by') . ' ' . Format::name('', $recordedBy['preferredName'], $recordedBy['surname'], 'Staff', false, true) : __('Recorded in system'),
                'icon' => '&#128221;',
                'color' => 'blue',
            ];

            // Parent notification
            if ($parentNotified && !empty($incident['parentNotifiedTime'])) {
                $timelineEvents[] = [
                    'time' => $incident['parentNotifiedTime'],
                    'title' => __('Parent Notified'),
                    'description' => __('Notification sent to parent(s)'),
                    'icon' => '&#128231;',
                    'color' => 'green',
                ];
            }

            // Director notification
            if ($directorNotified && !empty($incident['directorNotifiedTime'])) {
                $timelineEvents[] = [
                    'time' => $incident['directorNotifiedTime'],
                    'title' => __('Director Notified'),
                    'description' => __('Incident escalated to director'),
                    'icon' => '&#128101;',
                    'color' => 'blue',
                ];
            }

            // Parent acknowledgment
            if ($parentAcknowledged && !empty($incident['parentAcknowledgedTime'])) {
                $timelineEvents[] = [
                    'time' => $incident['parentAcknowledgedTime'],
                    'title' => __('Parent Acknowledged'),
                    'description' => __('Parent acknowledged the incident'),
                    'icon' => '&#10003;',
                    'color' => 'green',
                ];
            }

            // Sort by time
            usort($timelineEvents, function ($a, $b) {
                return strtotime($a['time']) - strtotime($b['time']);
            });

            foreach ($timelineEvents as $index => $event) {
                echo '<div class="relative pl-10 pb-6">';
                echo '<div class="absolute left-2 w-4 h-4 rounded-full bg-' . $event['color'] . '-500 border-2 border-white flex items-center justify-center text-xs text-white">&nbsp;</div>';
                echo '<div class="bg-' . $event['color'] . '-50 rounded p-3">';
                echo '<p class="text-xs text-gray-500">' . Format::dateTime($event['time']) . '</p>';
                echo '<p class="font-semibold">' . $event['title'] . '</p>';
                echo '<p class="text-sm text-gray-600">' . $event['description'] . '</p>';
                echo '</div>';
                echo '</div>';
            }

            echo '</div>';
            echo '</div>';

            // Child's Recent Incident History
            $criteria = $incidentGateway->newQueryCriteria()
                ->sortBy(['date', 'time'], 'DESC')
                ->pageSize(5)
                ->fromPOST();

            $recentIncidents = $incidentGateway->queryIncidentsByPerson($criteria, $incident['gibbonPersonID'], $gibbonSchoolYearID);

            if ($recentIncidents->count() > 1) { // More than just this incident
                echo '<div class="bg-white rounded-lg shadow p-4 mb-4">';
                echo '<h3 class="text-lg font-semibold mb-3 border-b pb-2">' . __('Recent Incident History for') . ' ' . htmlspecialchars($childName) . '</h3>';

                $table = DataTable::create('recentIncidents');

                $table->addColumn('date', __('Date'))
                    ->format(function ($row) {
                        return Format::date($row['date']);
                    });

                $table->addColumn('time', __('Time'))
                    ->format(function ($row) {
                        return Format::time($row['time']);
                    });

                $table->addColumn('type', __('Type'))
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
                        return '<span class="text-sm text-gray-600">' .
                               htmlspecialchars(substr($row['description'], 0, 50)) .
                               (strlen($row['description']) > 50 ? '...' : '') . '</span>';
                    });

                $table->addActionColumn()
                    ->addParam('gibbonCareIncidentID')
                    ->format(function ($row, $actions) use ($gibbonCareIncidentID) {
                        if ($row['gibbonCareIncidentID'] == $gibbonCareIncidentID) {
                            $actions->addAction('current', __('Current'))
                                ->setIcon('highlighter')
                                ->directLink()
                                ->setURL('#');
                        } else {
                            $actions->addAction('view', __('View'))
                                ->setURL('/modules/CareTracking/careTracking_incidents_view.php');
                        }
                    });

                echo $table->render($recentIncidents);
                echo '</div>';
            }

            // Action buttons at bottom
            echo '<div class="flex flex-wrap gap-2 mb-4">';

            // Edit button
            echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/CareTracking/careTracking_incidents_edit.php&gibbonCareIncidentID=' . $gibbonCareIncidentID . '" class="bg-yellow-500 text-white px-4 py-2 rounded hover:bg-yellow-600 inline-block">';
            echo __('Edit Incident') . '</a>';

            // PDF Export button
            echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/CareTracking/careTracking_incidents_pdf.php&gibbonCareIncidentID=' . $gibbonCareIncidentID . '" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600 inline-block" target="_blank">';
            echo __('Export PDF') . '</a>';

            // Print button
            echo '<button onclick="window.print()" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">';
            echo __('Print') . '</button>';

            echo '</div>';

            // Back link
            echo '<div class="mt-4">';
            echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/CareTracking/careTracking_incidents.php&date=' . $incident['date'] . '" class="text-blue-600 hover:underline">&larr; ' . __('Back to Incidents') . '</a>';
            echo '</div>';
        }
    }
}
