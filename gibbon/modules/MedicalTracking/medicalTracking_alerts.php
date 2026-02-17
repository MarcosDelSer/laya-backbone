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
use Gibbon\Module\MedicalTracking\Domain\MedicalAlertGateway;

// Module setup - breadcrumbs
$page->breadcrumbs->add(__('Medical Tracking'), 'medicalTracking.php');
$page->breadcrumbs->add(__('Manage Alerts'));

// Access check
if (!isActionAccessible($guid, $connection2, '/modules/MedicalTracking/medicalTracking_alerts.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get school year ID and current user from session
    $gibbonSchoolYearID = $session->get('gibbonSchoolYearID');
    $gibbonPersonID = $session->get('gibbonPersonID');

    // Get gateway via DI container
    $alertGateway = $container->get(MedicalAlertGateway::class);

    // Alert type options
    $alertTypes = [
        'Allergy'       => __('Allergy'),
        'Medication'    => __('Medication'),
        'Accommodation' => __('Accommodation'),
        'General'       => __('General'),
    ];

    // Alert level options
    $alertLevels = [
        'Critical' => __('Critical'),
        'Warning'  => __('Warning'),
        'Info'     => __('Info'),
    ];

    // Get filter values from request
    $filterAlertType = $_GET['alertType'] ?? '';
    $filterAlertLevel = $_GET['alertLevel'] ?? '';
    $filterActive = $_GET['active'] ?? 'Y';
    $filterChild = $_GET['gibbonPersonID'] ?? '';
    $filterDashboard = $_GET['displayOnDashboard'] ?? '';

    // Handle actions
    $action = $_POST['action'] ?? '';

    // Handle acknowledge alert action
    if ($action === 'acknowledgeAlert') {
        $alertID = $_POST['gibbonMedicalAlertID'] ?? null;

        if (!empty($alertID)) {
            // For now, acknowledging just deactivates (can be expanded later)
            $result = $alertGateway->update($alertID, [
                'acknowledgedByID' => $gibbonPersonID,
                'acknowledgedDate' => date('Y-m-d H:i:s'),
            ]);

            if ($result) {
                $page->addSuccess(__('Alert has been acknowledged.'));
            } else {
                $page->addError(__('Failed to acknowledge alert.'));
            }
        }
    }

    // Handle deactivate alert action
    if ($action === 'deactivateAlert') {
        $alertID = $_POST['gibbonMedicalAlertID'] ?? null;

        if (!empty($alertID)) {
            $result = $alertGateway->deactivateAlert($alertID);

            if ($result) {
                $page->addSuccess(__('Alert has been deactivated.'));
            } else {
                $page->addError(__('Failed to deactivate alert.'));
            }
        }
    }

    // Handle create alert action
    if ($action === 'createAlert') {
        $childID = $_POST['gibbonPersonID'] ?? null;
        $alertType = $_POST['alertType'] ?? 'General';
        $alertLevel = $_POST['alertLevel'] ?? 'Warning';
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $actionRequired = trim($_POST['actionRequired'] ?? '');
        $displayOnDashboard = $_POST['displayOnDashboard'] ?? 'Y';
        $displayOnAttendance = $_POST['displayOnAttendance'] ?? 'N';
        $notifyOnCheckIn = $_POST['notifyOnCheckIn'] ?? 'N';
        $expirationDate = !empty($_POST['expirationDate']) ? Format::dateConvert($_POST['expirationDate']) : null;

        if (!empty($childID) && !empty($title)) {
            $additionalData = [
                'actionRequired' => $actionRequired ?: null,
                'displayOnDashboard' => $displayOnDashboard,
                'displayOnAttendance' => $displayOnAttendance,
                'notifyOnCheckIn' => $notifyOnCheckIn,
                'expirationDate' => $expirationDate,
            ];

            $result = $alertGateway->createAlert(
                $childID,
                $alertType,
                $alertLevel,
                $title,
                $description,
                $gibbonPersonID,
                $additionalData
            );

            if ($result !== false) {
                $page->addSuccess(__('Alert has been created successfully.'));
            } else {
                $page->addError(__('Failed to create alert.'));
            }
        } else {
            $page->addError(__('Please select a child and enter an alert title.'));
        }
    }

    // Page header
    echo '<h2>' . __('Medical Alert Management') . '</h2>';
    echo '<p class="text-gray-600 mb-4">' . __('View and manage medical alerts for children. Monitor allergen exposures, medication reminders, and other health-related notifications.') . '</p>';

    // Get alert statistics
    $alertStats = $alertGateway->getAlertStatistics();

    // Calculate totals from statistics
    $totalAlerts = 0;
    $criticalAlerts = 0;
    $warningAlerts = 0;
    $allergyAlerts = 0;

    if (!empty($alertStats) && is_array($alertStats)) {
        foreach ($alertStats as $stat) {
            $totalAlerts += $stat['totalCount'] ?? 0;
            if (($stat['alertLevel'] ?? '') === 'Critical') {
                $criticalAlerts += $stat['totalCount'] ?? 0;
            }
            if (($stat['alertLevel'] ?? '') === 'Warning') {
                $warningAlerts += $stat['totalCount'] ?? 0;
            }
            if (($stat['alertType'] ?? '') === 'Allergy') {
                $allergyAlerts += $stat['totalCount'] ?? 0;
            }
        }
    }

    // Display summary
    echo '<div class="bg-white rounded-lg shadow p-4 mb-4">';
    echo '<h3 class="text-lg font-semibold mb-3">' . __('Alert Summary') . '</h3>';
    echo '<div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">';

    echo '<div class="bg-gray-50 rounded p-3">';
    echo '<span class="block text-sm font-medium text-gray-600">' . __('Total Active Alerts') . '</span>';
    echo '<span class="block text-3xl font-bold text-gray-800">' . $totalAlerts . '</span>';
    echo '</div>';

    echo '<div class="bg-red-50 rounded p-3">';
    echo '<span class="block text-sm font-medium text-red-600">' . __('Critical Alerts') . '</span>';
    echo '<span class="block text-3xl font-bold text-red-700">' . $criticalAlerts . '</span>';
    echo '</div>';

    echo '<div class="bg-orange-50 rounded p-3">';
    echo '<span class="block text-sm font-medium text-orange-600">' . __('Warning Alerts') . '</span>';
    echo '<span class="block text-3xl font-bold text-orange-700">' . $warningAlerts . '</span>';
    echo '</div>';

    echo '<div class="bg-purple-50 rounded p-3">';
    echo '<span class="block text-sm font-medium text-purple-600">' . __('Allergy Alerts') . '</span>';
    echo '<span class="block text-3xl font-bold text-purple-700">' . $allergyAlerts . '</span>';
    echo '</div>';

    echo '</div>';
    echo '</div>';

    // Critical alerts banner
    $criticalAlertsList = $alertGateway->selectCriticalAlerts();

    if ($criticalAlertsList->rowCount() > 0) {
        echo '<div class="bg-red-50 border border-red-300 rounded-lg p-4 mb-4">';
        echo '<h3 class="text-lg font-semibold text-red-700 mb-3">&#9888; ' . __('Critical Alerts Requiring Attention') . '</h3>';
        echo '<div class="space-y-2">';

        foreach ($criticalAlertsList as $alert) {
            $childName = Format::name('', $alert['preferredName'], $alert['surname'], 'Student', false, true);
            $image = !empty($alert['image_240']) ? $alert['image_240'] : 'themes/Default/img/anonymous_240.jpg';

            echo '<div class="bg-white rounded p-3 flex items-center justify-between border-l-4 border-red-500">';
            echo '<div class="flex items-center">';
            echo '<img src="' . $session->get('absoluteURL') . '/' . $image . '" class="w-10 h-10 rounded-full object-cover mr-3" alt="">';
            echo '<div>';
            echo '<span class="font-semibold">' . htmlspecialchars($childName) . '</span>';
            echo ' - <span class="text-sm text-red-600 font-medium">' . htmlspecialchars($alert['title']) . '</span>';
            if (!empty($alert['actionRequired'])) {
                echo '<p class="text-sm text-gray-600 mt-1"><strong>' . __('Action') . ':</strong> ' . htmlspecialchars(substr($alert['actionRequired'], 0, 100)) . (strlen($alert['actionRequired']) > 100 ? '...' : '') . '</p>';
            }
            echo '</div>';
            echo '</div>';
            echo '<form method="post" action="' . $session->get('absoluteURL') . '/index.php?q=/modules/MedicalTracking/medicalTracking_alerts.php" class="ml-4">';
            echo '<input type="hidden" name="action" value="acknowledgeAlert">';
            echo '<input type="hidden" name="gibbonMedicalAlertID" value="' . $alert['gibbonMedicalAlertID'] . '">';
            echo '<button type="submit" class="bg-green-500 text-white text-xs px-3 py-1 rounded hover:bg-green-600">' . __('Acknowledge') . '</button>';
            echo '</form>';
            echo '</div>';
        }

        echo '</div>';
        echo '</div>';
    }

    // Filter form
    $filterForm = Form::create('alertFilter', $session->get('absoluteURL') . '/index.php');
    $filterForm->setMethod('get');
    $filterForm->setClass('noIntBorder fullWidth');
    $filterForm->addHiddenValue('q', '/modules/MedicalTracking/medicalTracking_alerts.php');

    $row = $filterForm->addRow();
    $row->addLabel('alertType', __('Alert Type'));
    $row->addSelect('alertType')
        ->fromArray(['' => __('All Types')] + $alertTypes)
        ->selected($filterAlertType);

    $row = $filterForm->addRow();
    $row->addLabel('alertLevel', __('Alert Level'));
    $row->addSelect('alertLevel')
        ->fromArray(['' => __('All Levels')] + $alertLevels)
        ->selected($filterAlertLevel);

    $row = $filterForm->addRow();
    $row->addLabel('displayOnDashboard', __('Dashboard Display'));
    $row->addSelect('displayOnDashboard')
        ->fromArray(['' => __('All'), 'Y' => __('Show on Dashboard'), 'N' => __('Hidden from Dashboard')])
        ->selected($filterDashboard);

    $row = $filterForm->addRow();
    $row->addLabel('active', __('Status'));
    $row->addSelect('active')
        ->fromArray(['Y' => __('Active'), 'N' => __('Inactive'), '' => __('All')])
        ->selected($filterActive);

    $row = $filterForm->addRow();
    $row->addSearchSubmit($session, __('Clear Filters'), ['alertType', 'alertLevel', 'displayOnDashboard', 'active']);

    echo $filterForm->getOutput();

    // Build query criteria
    $criteria = $alertGateway->newQueryCriteria()
        ->sortBy(['alertLevel', 'timestampCreated'])
        ->fromPOST();

    // Add filters to criteria
    if (!empty($filterAlertType)) {
        $criteria->filterBy('alertType', $filterAlertType);
    }
    if (!empty($filterAlertLevel)) {
        $criteria->filterBy('alertLevel', $filterAlertLevel);
    }
    if (!empty($filterDashboard)) {
        $criteria->filterBy('displayOnDashboard', $filterDashboard);
    }
    if ($filterActive !== '') {
        $criteria->filterBy('active', $filterActive);
    }
    if (!empty($filterChild)) {
        $criteria->filterBy('child', $filterChild);
    }

    // Get alert data
    $alerts = $alertGateway->queryAlerts($criteria);

    // Build DataTable
    $table = DataTable::createPaginated('alerts', $criteria);
    $table->setTitle(__('Medical Alerts'));

    // Add columns
    $table->addColumn('image', __('Photo'))
        ->notSortable()
        ->format(function ($row) use ($session) {
            $image = !empty($row['image_240']) ? $row['image_240'] : 'themes/Default/img/anonymous_240.jpg';
            return '<img src="' . $session->get('absoluteURL') . '/' . $image . '" class="w-10 h-10 rounded-full object-cover" alt="">';
        });

    $table->addColumn('name', __('Child Name'))
        ->sortable(['surname', 'preferredName'])
        ->format(function ($row) {
            return Format::name('', $row['preferredName'], $row['surname'], 'Student', true, true);
        });

    $table->addColumn('alertLevel', __('Level'))
        ->sortable()
        ->format(function ($row) {
            $colors = [
                'Critical' => 'bg-red-100 text-red-800',
                'Warning'  => 'bg-orange-100 text-orange-800',
                'Info'     => 'bg-blue-100 text-blue-800',
            ];
            $color = $colors[$row['alertLevel']] ?? 'bg-gray-100 text-gray-800';
            return '<span class="' . $color . ' text-xs px-2 py-1 rounded font-semibold">' . __($row['alertLevel']) . '</span>';
        });

    $table->addColumn('alertType', __('Type'))
        ->sortable()
        ->format(function ($row) {
            $icons = [
                'Allergy'       => '&#9888;',
                'Medication'    => '&#128138;',
                'Accommodation' => '&#128203;',
                'General'       => '&#9432;',
            ];
            $icon = $icons[$row['alertType']] ?? '&#9432;';
            return $icon . ' ' . __($row['alertType']);
        });

    $table->addColumn('title', __('Title'))
        ->sortable()
        ->format(function ($row) {
            $text = htmlspecialchars($row['title']);
            if (strlen($text) > 50) {
                return '<span title="' . $text . '">' . substr($text, 0, 50) . '...</span>';
            }
            return $text;
        });

    $table->addColumn('description', __('Description'))
        ->notSortable()
        ->format(function ($row) {
            if (empty($row['description'])) {
                return '<span class="text-gray-400">-</span>';
            }
            $text = htmlspecialchars($row['description']);
            if (strlen($text) > 60) {
                return '<span class="text-sm text-gray-600" title="' . $text . '">' . substr($text, 0, 60) . '...</span>';
            }
            return '<span class="text-sm text-gray-600">' . $text . '</span>';
        });

    $table->addColumn('displayOnDashboard', __('Dashboard'))
        ->notSortable()
        ->format(function ($row) {
            if ($row['displayOnDashboard'] === 'Y') {
                return '<span class="text-green-600" title="' . __('Visible on Dashboard') . '">&#10003;</span>';
            }
            return '<span class="text-gray-400">-</span>';
        });

    $table->addColumn('notifyOnCheckIn', __('Check-In'))
        ->notSortable()
        ->format(function ($row) {
            if ($row['notifyOnCheckIn'] === 'Y') {
                return '<span class="text-blue-600" title="' . __('Notify on Check-In') . '">&#128276;</span>';
            }
            return '<span class="text-gray-400">-</span>';
        });

    $table->addColumn('timestampCreated', __('Created'))
        ->sortable()
        ->format(function ($row) {
            return Format::dateTime($row['timestampCreated']);
        });

    $table->addColumn('active', __('Status'))
        ->notSortable()
        ->format(function ($row) {
            if ($row['active'] === 'Y') {
                return '<span class="text-green-600">' . __('Active') . '</span>';
            }
            return '<span class="text-gray-500">' . __('Inactive') . '</span>';
        });

    // Add action column
    $table->addActionColumn()
        ->format(function ($row, $actions) use ($session) {
            // Deactivate action (only for active alerts)
            if ($row['active'] === 'Y') {
                $actions->addAction('deactivate', __('Deactivate'))
                    ->setIcon('iconCross')
                    ->setURL($session->get('absoluteURL') . '/index.php?q=/modules/MedicalTracking/medicalTracking_alerts.php')
                    ->addParam('deactivate', $row['gibbonMedicalAlertID'])
                    ->directLink();
            }

            return $actions;
        });

    // Output table
    if ($alerts->count() > 0) {
        echo $table->render($alerts);
    } else {
        echo '<div class="bg-gray-50 rounded-lg p-4 text-center text-gray-500 mb-4">';
        echo __('No alert records found matching the selected criteria.');
        echo '</div>';
    }

    // Handle quick actions via GET parameters
    $deactivateID = $_GET['deactivate'] ?? null;

    if (!empty($deactivateID)) {
        $result = $alertGateway->deactivateAlert($deactivateID);
        if ($result) {
            echo '<script>window.location.href = "' . $session->get('absoluteURL') . '/index.php?q=/modules/MedicalTracking/medicalTracking_alerts.php";</script>';
        }
    }

    // Section: Create New Alert
    echo '<h3 class="text-lg font-semibold mt-6 mb-3">' . __('Create New Alert') . '</h3>';

    echo '<div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">';
    echo '<form method="post" action="' . $session->get('absoluteURL') . '/index.php?q=/modules/MedicalTracking/medicalTracking_alerts.php">';
    echo '<input type="hidden" name="action" value="createAlert">';

    // Get children (active students)
    $sql = "SELECT gibbonPersonID, preferredName, surname
            FROM gibbonPerson
            WHERE status='Full'
            ORDER BY surname, preferredName";
    $result = $connection2->query($sql);
    $children = [];
    while ($row = $result->fetch()) {
        $children[$row['gibbonPersonID']] = Format::name('', $row['preferredName'], $row['surname'], 'Student', true, true);
    }

    // Row 1: Child, Type, Level
    echo '<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">';

    echo '<div>';
    echo '<label class="block text-sm font-medium mb-1">' . __('Child') . ' <span class="text-red-500">*</span></label>';
    echo '<select name="gibbonPersonID" class="w-full border rounded px-3 py-2" required>';
    echo '<option value="">' . __('Select a child...') . '</option>';
    foreach ($children as $id => $name) {
        echo '<option value="' . $id . '">' . htmlspecialchars($name) . '</option>';
    }
    echo '</select>';
    echo '</div>';

    echo '<div>';
    echo '<label class="block text-sm font-medium mb-1">' . __('Alert Type') . '</label>';
    echo '<select name="alertType" class="w-full border rounded px-3 py-2">';
    foreach ($alertTypes as $value => $label) {
        $selected = $value === 'General' ? ' selected' : '';
        echo '<option value="' . htmlspecialchars($value) . '"' . $selected . '>' . htmlspecialchars($label) . '</option>';
    }
    echo '</select>';
    echo '</div>';

    echo '<div>';
    echo '<label class="block text-sm font-medium mb-1">' . __('Alert Level') . '</label>';
    echo '<select name="alertLevel" class="w-full border rounded px-3 py-2">';
    foreach ($alertLevels as $value => $label) {
        $selected = $value === 'Warning' ? ' selected' : '';
        echo '<option value="' . htmlspecialchars($value) . '"' . $selected . '>' . htmlspecialchars($label) . '</option>';
    }
    echo '</select>';
    echo '</div>';

    echo '</div>';

    // Row 2: Title
    echo '<div class="mb-4">';
    echo '<label class="block text-sm font-medium mb-1">' . __('Alert Title') . ' <span class="text-red-500">*</span></label>';
    echo '<input type="text" name="title" class="w-full border rounded px-3 py-2" placeholder="' . __('Brief alert title...') . '" required>';
    echo '</div>';

    // Row 3: Description and Action Required
    echo '<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">';

    echo '<div>';
    echo '<label class="block text-sm font-medium mb-1">' . __('Description') . '</label>';
    echo '<textarea name="description" class="w-full border rounded px-3 py-2" rows="3" placeholder="' . __('Detailed description of the alert...') . '"></textarea>';
    echo '</div>';

    echo '<div>';
    echo '<label class="block text-sm font-medium mb-1">' . __('Action Required') . '</label>';
    echo '<textarea name="actionRequired" class="w-full border rounded px-3 py-2" rows="3" placeholder="' . __('What action should staff take?') . '"></textarea>';
    echo '</div>';

    echo '</div>';

    // Row 4: Display options and expiration
    echo '<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">';

    echo '<div>';
    echo '<label class="block text-sm font-medium mb-1">' . __('Show on Dashboard') . '</label>';
    echo '<select name="displayOnDashboard" class="w-full border rounded px-3 py-2">';
    echo '<option value="Y" selected>' . __('Yes') . '</option>';
    echo '<option value="N">' . __('No') . '</option>';
    echo '</select>';
    echo '</div>';

    echo '<div>';
    echo '<label class="block text-sm font-medium mb-1">' . __('Show on Attendance') . '</label>';
    echo '<select name="displayOnAttendance" class="w-full border rounded px-3 py-2">';
    echo '<option value="N" selected>' . __('No') . '</option>';
    echo '<option value="Y">' . __('Yes') . '</option>';
    echo '</select>';
    echo '</div>';

    echo '<div>';
    echo '<label class="block text-sm font-medium mb-1">' . __('Notify on Check-In') . '</label>';
    echo '<select name="notifyOnCheckIn" class="w-full border rounded px-3 py-2">';
    echo '<option value="N" selected>' . __('No') . '</option>';
    echo '<option value="Y">' . __('Yes') . '</option>';
    echo '</select>';
    echo '</div>';

    echo '<div>';
    echo '<label class="block text-sm font-medium mb-1">' . __('Expiration Date') . '</label>';
    echo '<input type="date" name="expirationDate" class="w-full border rounded px-3 py-2">';
    echo '</div>';

    echo '</div>';

    // Submit button
    echo '<div class="mt-4">';
    echo '<button type="submit" class="bg-yellow-500 text-white px-4 py-2 rounded hover:bg-yellow-600">' . __('Create Alert') . '</button>';
    echo '</div>';

    echo '</form>';
    echo '</div>';

    // Quick links section
    echo '<div class="mt-6">';
    echo '<h3 class="text-lg font-semibold mb-3">' . __('Quick Links') . '</h3>';
    echo '<div class="flex flex-wrap gap-2">';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/MedicalTracking/medicalTracking_alerts.php&alertLevel=Critical" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">' . __('View Critical Alerts') . '</a>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/MedicalTracking/medicalTracking_alerts.php&alertType=Allergy" class="bg-purple-500 text-white px-4 py-2 rounded hover:bg-purple-600">' . __('Allergy Alerts') . '</a>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/MedicalTracking/medicalTracking_alerts.php&alertType=Medication" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">' . __('Medication Alerts') . '</a>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/MedicalTracking/medicalTracking_allergies.php" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">' . __('Manage Allergies') . '</a>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/MedicalTracking/medicalTracking.php" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">&larr; ' . __('Back to Dashboard') . '</a>';
    echo '</div>';
    echo '</div>';
}
