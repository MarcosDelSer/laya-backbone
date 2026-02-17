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
use Gibbon\Module\StaffManagement\Domain\DisciplinaryGateway;
use Gibbon\Module\StaffManagement\Domain\StaffProfileGateway;
use Gibbon\Module\StaffManagement\Domain\AuditLogGateway;

// Module setup - breadcrumbs
$page->breadcrumbs->add(__('Staff Management'), 'staffManagement.php');
$page->breadcrumbs->add(__('Disciplinary Records'));

// Access check - Director only
if (!isActionAccessible($guid, $connection2, '/modules/StaffManagement/staffManagement_disciplinary.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get school year ID from session
    $gibbonSchoolYearID = $session->get('gibbonSchoolYearID');
    $gibbonPersonID = $session->get('gibbonPersonID');

    // Get gateways via DI container
    $disciplinaryGateway = $container->get(DisciplinaryGateway::class);
    $staffProfileGateway = $container->get(StaffProfileGateway::class);
    $auditLogGateway = $container->get(AuditLogGateway::class);

    // Disciplinary type options
    $disciplinaryTypes = [
        'Verbal Warning'               => __('Verbal Warning'),
        'Written Warning'              => __('Written Warning'),
        'Final Warning'                => __('Final Warning'),
        'Suspension'                   => __('Suspension'),
        'Probation'                    => __('Probation'),
        'Performance Improvement Plan' => __('Performance Improvement Plan'),
        'Termination'                  => __('Termination'),
        'Documentation'                => __('Documentation'),
    ];

    // Severity options
    $severityOptions = [
        'Low'      => __('Low'),
        'Moderate' => __('Moderate'),
        'Serious'  => __('Serious'),
        'Critical' => __('Critical'),
    ];

    // Category options
    $categoryOptions = [
        'Attendance'           => __('Attendance'),
        'Conduct'              => __('Conduct'),
        'Performance'          => __('Performance'),
        'Policy Violation'     => __('Policy Violation'),
        'Safety'               => __('Safety'),
        'Insubordination'      => __('Insubordination'),
        'Harassment'           => __('Harassment'),
        'Theft/Fraud'          => __('Theft/Fraud'),
        'Substance Abuse'      => __('Substance Abuse'),
        'Other'                => __('Other'),
    ];

    // Status options
    $statusOptions = [
        'Open'         => __('Open'),
        'Under Review' => __('Under Review'),
        'Resolved'     => __('Resolved'),
        'Archived'     => __('Archived'),
    ];

    // Handle form actions
    $action = $_POST['action'] ?? '';
    $disciplinaryID = $_POST['gibbonStaffDisciplinaryID'] ?? null;

    if ($action === 'add') {
        $personID = $_POST['gibbonPersonID'] ?? null;
        $incidentDate = !empty($_POST['incidentDate']) ? Format::dateConvert($_POST['incidentDate']) : date('Y-m-d');
        $incidentTime = $_POST['incidentTime'] ?? null;
        $type = $_POST['type'] ?? '';
        $severity = $_POST['severity'] ?? 'Moderate';
        $category = $_POST['category'] ?? '';
        $description = $_POST['description'] ?? '';
        $actionTaken = $_POST['actionTaken'] ?? null;
        $witnessNames = $_POST['witnessNames'] ?? null;
        $followUpRequired = $_POST['followUpRequired'] ?? 'N';
        $followUpDate = ($followUpRequired === 'Y' && !empty($_POST['followUpDate'])) ? Format::dateConvert($_POST['followUpDate']) : null;
        $confidential = $_POST['confidential'] ?? 'N';

        if (!empty($personID) && !empty($type) && !empty($category) && !empty($description)) {
            $additionalData = [
                'actionTaken' => $actionTaken,
                'witnessNames' => $witnessNames,
                'followUpRequired' => $followUpRequired,
                'followUpDate' => $followUpDate,
                'followUpCompleted' => 'N',
                'confidential' => $confidential,
                'status' => 'Open',
            ];

            $newID = $disciplinaryGateway->logDisciplinaryIncident(
                $personID,
                $incidentDate,
                $incidentTime,
                $type,
                $severity,
                $category,
                $description,
                $gibbonPersonID,
                $additionalData
            );

            if ($newID !== false) {
                $page->addSuccess(__('Disciplinary record has been logged successfully.'));

                // Log the action
                $auditLogGateway->logInsert(
                    'gibbonStaffDisciplinary',
                    $newID,
                    $gibbonPersonID,
                    $session->get('session')
                );
            } else {
                $page->addError(__('Failed to log disciplinary record.'));
            }
        } else {
            $page->addError(__('Please fill in all required fields.'));
        }
    } elseif ($action === 'updateStatus' && !empty($disciplinaryID)) {
        $newStatus = $_POST['status'] ?? '';
        $resolutionNotes = $_POST['resolutionNotes'] ?? null;
        $resolutionDate = ($newStatus === 'Resolved' || $newStatus === 'Archived') ? date('Y-m-d') : null;

        if (!empty($newStatus)) {
            // Get old values for audit
            $oldRecord = $disciplinaryGateway->getDisciplinaryRecordByID($disciplinaryID);

            $result = $disciplinaryGateway->updateStatus($disciplinaryID, $newStatus, $resolutionDate, $resolutionNotes);

            if ($result !== false) {
                $page->addSuccess(__('Status has been updated successfully.'));

                // Log the action
                $auditLogGateway->logUpdate(
                    'gibbonStaffDisciplinary',
                    $disciplinaryID,
                    json_encode(['status' => $oldRecord['status']]),
                    json_encode(['status' => $newStatus, 'resolutionNotes' => $resolutionNotes]),
                    $gibbonPersonID,
                    $session->get('session')
                );
            } else {
                $page->addError(__('Failed to update status.'));
            }
        }
    } elseif ($action === 'completeFollowUp' && !empty($disciplinaryID)) {
        $followUpNotes = $_POST['followUpNotes'] ?? null;

        // Get old values for audit
        $oldRecord = $disciplinaryGateway->getDisciplinaryRecordByID($disciplinaryID);

        $result = $disciplinaryGateway->completeFollowUp($disciplinaryID, $followUpNotes);

        if ($result !== false) {
            $page->addSuccess(__('Follow-up has been marked as completed.'));

            // Log the action
            $auditLogGateway->logUpdate(
                'gibbonStaffDisciplinary',
                $disciplinaryID,
                json_encode(['followUpCompleted' => 'N']),
                json_encode(['followUpCompleted' => 'Y', 'followUpNotes' => $followUpNotes]),
                $gibbonPersonID,
                $session->get('session')
            );
        } else {
            $page->addError(__('Failed to complete follow-up.'));
        }
    } elseif ($action === 'addResponse' && !empty($disciplinaryID)) {
        $employeeResponse = $_POST['employeeResponse'] ?? '';

        if (!empty($employeeResponse)) {
            $result = $disciplinaryGateway->addEmployeeResponse($disciplinaryID, $employeeResponse);

            if ($result !== false) {
                $page->addSuccess(__('Employee response has been recorded.'));

                // Log the action
                $auditLogGateway->logUpdate(
                    'gibbonStaffDisciplinary',
                    $disciplinaryID,
                    json_encode(['employeeResponse' => null]),
                    json_encode(['employeeResponse' => $employeeResponse]),
                    $gibbonPersonID,
                    $session->get('session')
                );
            } else {
                $page->addError(__('Failed to record employee response.'));
            }
        }
    }

    // Get filter parameters
    $filterType = $_GET['type'] ?? '';
    $filterSeverity = $_GET['severity'] ?? '';
    $filterCategory = $_GET['category'] ?? '';
    $filterStatus = $_GET['status'] ?? '';
    $filterPerson = $_GET['gibbonPersonID'] ?? '';
    $filterDateFrom = $_GET['dateFrom'] ?? '';
    $filterDateTo = $_GET['dateTo'] ?? '';
    $filter = $_GET['filter'] ?? '';

    // Page header with confidentiality notice
    echo '<div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">';
    echo '<div class="flex items-center">';
    echo '<span class="text-red-600 text-2xl mr-3">&#128274;</span>';
    echo '<div>';
    echo '<h2 class="text-lg font-bold text-red-800 mb-1">' . __('Confidential - Director Access Only') . '</h2>';
    echo '<p class="text-sm text-red-600">' . __('This page contains sensitive employee disciplinary records. All actions are logged for audit purposes.') . '</p>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    // Get summary statistics
    $summary = $disciplinaryGateway->getDisciplinarySummary();

    // Display summary cards
    echo '<div class="bg-white rounded-lg shadow p-4 mb-6">';
    echo '<h3 class="text-lg font-semibold mb-3">' . __('Disciplinary Summary') . '</h3>';
    echo '<div class="grid grid-cols-2 md:grid-cols-7 gap-4 text-center">';

    echo '<div class="bg-gray-50 rounded p-3">';
    echo '<span class="block text-2xl font-bold text-gray-700">' . ($summary['totalRecords'] ?? 0) . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('Total Records') . '</span>';
    echo '</div>';

    echo '<div class="bg-blue-50 rounded p-3">';
    echo '<span class="block text-2xl font-bold text-blue-600">' . ($summary['staffInvolved'] ?? 0) . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('Staff Involved') . '</span>';
    echo '</div>';

    echo '<div class="bg-yellow-50 rounded p-3">';
    echo '<span class="block text-2xl font-bold text-yellow-600">' . ($summary['openRecords'] ?? 0) . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('Open') . '</span>';
    echo '</div>';

    echo '<div class="bg-orange-50 rounded p-3">';
    echo '<span class="block text-2xl font-bold text-orange-600">' . ($summary['underReview'] ?? 0) . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('Under Review') . '</span>';
    echo '</div>';

    echo '<div class="bg-green-50 rounded p-3">';
    echo '<span class="block text-2xl font-bold text-green-600">' . ($summary['resolved'] ?? 0) . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('Resolved') . '</span>';
    echo '</div>';

    echo '<div class="bg-purple-50 rounded p-3">';
    echo '<span class="block text-2xl font-bold text-purple-600">' . ($summary['pendingFollowUps'] ?? 0) . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('Pending Follow-ups') . '</span>';
    echo '</div>';

    $criticalSeverity = ($summary['criticalSeverity'] ?? 0) + ($summary['seriousSeverity'] ?? 0);
    $criticalClass = $criticalSeverity > 0 ? 'text-red-600 font-bold' : 'text-gray-600';
    echo '<div class="bg-red-50 rounded p-3">';
    echo '<span class="block text-2xl font-bold ' . $criticalClass . '">' . $criticalSeverity . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('Critical/Serious') . '</span>';
    echo '</div>';

    echo '</div>';

    // Type breakdown
    echo '<div class="mt-4 pt-4 border-t grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-2 text-center">';

    echo '<div class="text-xs">';
    echo '<span class="text-gray-600">' . __('Verbal') . ':</span> ';
    echo '<span class="font-semibold">' . ($summary['verbalWarnings'] ?? 0) . '</span>';
    echo '</div>';

    echo '<div class="text-xs">';
    echo '<span class="text-gray-600">' . __('Written') . ':</span> ';
    echo '<span class="font-semibold">' . ($summary['writtenWarnings'] ?? 0) . '</span>';
    echo '</div>';

    echo '<div class="text-xs">';
    echo '<span class="text-gray-600">' . __('Suspensions') . ':</span> ';
    echo '<span class="font-semibold">' . ($summary['suspensions'] ?? 0) . '</span>';
    echo '</div>';

    echo '<div class="text-xs">';
    echo '<span class="text-gray-600">' . __('Probations') . ':</span> ';
    echo '<span class="font-semibold">' . ($summary['probations'] ?? 0) . '</span>';
    echo '</div>';

    echo '<div class="text-xs">';
    echo '<span class="text-gray-600">' . __('PIPs') . ':</span> ';
    echo '<span class="font-semibold">' . ($summary['performancePlans'] ?? 0) . '</span>';
    echo '</div>';

    echo '<div class="text-xs">';
    echo '<span class="text-gray-600">' . __('Terminations') . ':</span> ';
    $terminationClass = ($summary['terminations'] ?? 0) > 0 ? 'text-red-600 font-bold' : 'font-semibold';
    echo '<span class="' . $terminationClass . '">' . ($summary['terminations'] ?? 0) . '</span>';
    echo '</div>';

    echo '</div>';
    echo '</div>';

    // Overdue Follow-ups Alert
    $overdueFollowUps = $disciplinaryGateway->selectOverdueFollowUps();

    if ($overdueFollowUps->rowCount() > 0) {
        echo '<div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">';
        echo '<h3 class="text-lg font-semibold text-red-800 mb-3">' . __('Overdue Follow-ups') . ' <span class="bg-red-200 text-red-800 text-sm px-2 py-1 rounded-full">' . $overdueFollowUps->rowCount() . '</span></h3>';
        echo '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">';

        foreach ($overdueFollowUps as $followUp) {
            $staffName = Format::name('', $followUp['preferredName'], $followUp['surname'], 'Staff', false, true);
            $daysOverdue = intval($followUp['daysOverdue']);
            $image = !empty($followUp['image_240']) ? $followUp['image_240'] : 'themes/Default/img/anonymous_240.jpg';

            echo '<div class="bg-white border border-red-300 rounded-lg p-3 flex items-center space-x-3">';
            echo '<img src="' . $session->get('absoluteURL') . '/' . $image . '" class="w-12 h-12 rounded-full object-cover" alt="">';
            echo '<div class="flex-1">';
            echo '<p class="font-semibold text-sm">' . htmlspecialchars($staffName) . '</p>';
            echo '<p class="text-xs text-gray-600">' . __($followUp['type']) . ' - ' . __($followUp['severity']) . '</p>';
            echo '<p class="text-xs text-red-600 font-semibold">';
            echo sprintf(__('Overdue by %d day(s)'), $daysOverdue);
            echo ' (' . Format::date($followUp['followUpDate']) . ')';
            echo '</p>';
            echo '</div>';
            echo '<form method="post" action="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_disciplinary.php">';
            echo '<input type="hidden" name="action" value="completeFollowUp">';
            echo '<input type="hidden" name="gibbonStaffDisciplinaryID" value="' . $followUp['gibbonStaffDisciplinaryID'] . '">';
            echo '<button type="submit" class="bg-green-500 text-white text-xs px-3 py-1 rounded hover:bg-green-600">' . __('Complete') . '</button>';
            echo '</form>';
            echo '</div>';
        }

        echo '</div>';
        echo '</div>';
    }

    // Pending Follow-ups Section
    $pendingCriteria = $disciplinaryGateway->newQueryCriteria()->sortBy(['followUpDate'], 'ASC');
    $pendingFollowUps = $disciplinaryGateway->queryPendingFollowUps($pendingCriteria);

    if ($pendingFollowUps->count() > 0) {
        echo '<div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">';
        echo '<h3 class="text-lg font-semibold text-yellow-800 mb-3">' . __('Upcoming Follow-ups') . ' <span class="bg-yellow-200 text-yellow-800 text-sm px-2 py-1 rounded-full">' . $pendingFollowUps->count() . '</span></h3>';
        echo '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">';

        foreach ($pendingFollowUps as $followUp) {
            $staffName = Format::name('', $followUp['preferredName'], $followUp['surname'], 'Staff', false, true);
            $followUpDate = $followUp['followUpDate'];
            $daysUntil = (strtotime($followUpDate) - strtotime(date('Y-m-d'))) / 86400;
            $image = !empty($followUp['image_240']) ? $followUp['image_240'] : 'themes/Default/img/anonymous_240.jpg';

            $urgencyClass = $daysUntil <= 3 ? 'border-orange-300' : 'border-yellow-300';
            $dateClass = $daysUntil <= 3 ? 'text-orange-600' : 'text-yellow-600';

            echo '<div class="bg-white border ' . $urgencyClass . ' rounded-lg p-3 flex items-center space-x-3">';
            echo '<img src="' . $session->get('absoluteURL') . '/' . $image . '" class="w-12 h-12 rounded-full object-cover" alt="">';
            echo '<div class="flex-1">';
            echo '<p class="font-semibold text-sm">' . htmlspecialchars($staffName) . '</p>';
            echo '<p class="text-xs text-gray-600">' . __($followUp['type']) . ' - ' . __($followUp['category']) . '</p>';
            echo '<p class="text-xs ' . $dateClass . ' font-semibold">';
            echo __('Due') . ': ' . Format::date($followUpDate);
            if ($daysUntil <= 7) {
                echo ' (' . sprintf(__('%d day(s)'), intval($daysUntil)) . ')';
            }
            echo '</p>';
            echo '</div>';
            echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_disciplinary_view.php&gibbonStaffDisciplinaryID=' . $followUp['gibbonStaffDisciplinaryID'] . '" class="bg-blue-500 text-white text-xs px-3 py-1 rounded hover:bg-blue-600">' . __('View') . '</a>';
            echo '</div>';
        }

        echo '</div>';
        echo '</div>';
    }

    // Filter form
    echo '<div class="bg-white rounded-lg shadow p-4 mb-6">';

    $form = Form::create('disciplinaryFilter', $session->get('absoluteURL') . '/index.php');
    $form->setMethod('get');
    $form->addHiddenValue('q', '/modules/StaffManagement/staffManagement_disciplinary.php');

    $row = $form->addRow();
    $row->addLabel('type', __('Type'));
    $row->addSelect('type')->fromArray(['' => __('All')] + $disciplinaryTypes)->selected($filterType);

    $row = $form->addRow();
    $row->addLabel('severity', __('Severity'));
    $row->addSelect('severity')->fromArray(['' => __('All')] + $severityOptions)->selected($filterSeverity);

    $row = $form->addRow();
    $row->addLabel('category', __('Category'));
    $row->addSelect('category')->fromArray(['' => __('All')] + $categoryOptions)->selected($filterCategory);

    $row = $form->addRow();
    $row->addLabel('status', __('Status'));
    $row->addSelect('status')->fromArray(['' => __('All')] + $statusOptions)->selected($filterStatus);

    // Get staff list for filter
    $staffCriteria = $staffProfileGateway->newQueryCriteria();
    $staffList = $staffProfileGateway->queryStaffProfiles($staffCriteria);
    $staffOptions = [];
    foreach ($staffList as $staff) {
        $staffOptions[$staff['gibbonPersonID']] = Format::name('', $staff['preferredName'], $staff['surname'], 'Staff', true, true);
    }

    $row = $form->addRow();
    $row->addLabel('gibbonPersonID', __('Staff Member'));
    $row->addSelect('gibbonPersonID')->fromArray(['' => __('All Staff')] + $staffOptions)->selected($filterPerson);

    $row = $form->addRow();
    $row->addLabel('dateFrom', __('Date From'));
    $row->addDate('dateFrom')->setValue($filterDateFrom);

    $row = $form->addRow();
    $row->addLabel('dateTo', __('Date To'));
    $row->addDate('dateTo')->setValue($filterDateTo);

    // Quick filter options
    $row = $form->addRow();
    $col = $row->addColumn()->addClass('flex gap-2 flex-wrap');
    $col->addContent('<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_disciplinary.php&filter=open" class="text-yellow-600 hover:underline text-sm">' . __('Show Open') . '</a>');
    $col->addContent('<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_disciplinary.php&filter=critical" class="text-red-600 hover:underline text-sm">' . __('Show Critical/Serious') . '</a>');
    $col->addContent('<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_disciplinary.php&filter=followup" class="text-purple-600 hover:underline text-sm">' . __('Show Pending Follow-ups') . '</a>');
    $col->addContent('<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_disciplinary.php" class="text-blue-600 hover:underline text-sm">' . __('Clear Filters') . '</a>');

    $row = $form->addRow();
    $row->addSubmit(__('Filter'));

    echo $form->getOutput();
    echo '</div>';

    // Add New Disciplinary Record Section
    echo '<div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-6">';
    echo '<h3 class="text-lg font-semibold text-gray-800 mb-3">' . __('Log New Disciplinary Record') . '</h3>';

    echo '<form method="post" action="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_disciplinary.php">';
    echo '<input type="hidden" name="action" value="add">';

    echo '<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">';

    // Staff member
    echo '<div>';
    echo '<label class="block text-sm font-medium mb-1">' . __('Staff Member') . ' <span class="text-red-500">*</span></label>';
    echo '<select name="gibbonPersonID" class="w-full border rounded px-3 py-2" required>';
    echo '<option value="">' . __('Select Staff') . '</option>';
    foreach ($staffOptions as $id => $name) {
        echo '<option value="' . $id . '">' . htmlspecialchars($name) . '</option>';
    }
    echo '</select>';
    echo '</div>';

    // Incident date
    echo '<div>';
    echo '<label class="block text-sm font-medium mb-1">' . __('Incident Date') . ' <span class="text-red-500">*</span></label>';
    echo '<input type="date" name="incidentDate" value="' . date('Y-m-d') . '" class="w-full border rounded px-3 py-2" required>';
    echo '</div>';

    // Incident time
    echo '<div>';
    echo '<label class="block text-sm font-medium mb-1">' . __('Incident Time') . '</label>';
    echo '<input type="time" name="incidentTime" class="w-full border rounded px-3 py-2">';
    echo '</div>';

    // Type
    echo '<div>';
    echo '<label class="block text-sm font-medium mb-1">' . __('Action Type') . ' <span class="text-red-500">*</span></label>';
    echo '<select name="type" class="w-full border rounded px-3 py-2" required>';
    echo '<option value="">' . __('Select Type') . '</option>';
    foreach ($disciplinaryTypes as $value => $label) {
        echo '<option value="' . htmlspecialchars($value) . '">' . htmlspecialchars($label) . '</option>';
    }
    echo '</select>';
    echo '</div>';

    // Severity
    echo '<div>';
    echo '<label class="block text-sm font-medium mb-1">' . __('Severity') . '</label>';
    echo '<select name="severity" class="w-full border rounded px-3 py-2">';
    foreach ($severityOptions as $value => $label) {
        $selected = $value === 'Moderate' ? ' selected' : '';
        echo '<option value="' . htmlspecialchars($value) . '"' . $selected . '>' . htmlspecialchars($label) . '</option>';
    }
    echo '</select>';
    echo '</div>';

    // Category
    echo '<div>';
    echo '<label class="block text-sm font-medium mb-1">' . __('Category') . ' <span class="text-red-500">*</span></label>';
    echo '<select name="category" class="w-full border rounded px-3 py-2" required>';
    echo '<option value="">' . __('Select Category') . '</option>';
    foreach ($categoryOptions as $value => $label) {
        echo '<option value="' . htmlspecialchars($value) . '">' . htmlspecialchars($label) . '</option>';
    }
    echo '</select>';
    echo '</div>';

    // Witnesses
    echo '<div>';
    echo '<label class="block text-sm font-medium mb-1">' . __('Witness Names') . '</label>';
    echo '<input type="text" name="witnessNames" class="w-full border rounded px-3 py-2" placeholder="' . __('Enter witness names, comma-separated') . '">';
    echo '</div>';

    // Confidential
    echo '<div>';
    echo '<label class="block text-sm font-medium mb-1">' . __('Mark as Confidential') . '</label>';
    echo '<select name="confidential" class="w-full border rounded px-3 py-2">';
    echo '<option value="N">' . __('No') . '</option>';
    echo '<option value="Y">' . __('Yes - Restricted Access') . '</option>';
    echo '</select>';
    echo '</div>';

    echo '</div>';

    // Description
    echo '<div class="mb-4">';
    echo '<label class="block text-sm font-medium mb-1">' . __('Description of Incident') . ' <span class="text-red-500">*</span></label>';
    echo '<textarea name="description" rows="3" class="w-full border rounded px-3 py-2" placeholder="' . __('Describe the incident in detail...') . '" required></textarea>';
    echo '</div>';

    // Action taken
    echo '<div class="mb-4">';
    echo '<label class="block text-sm font-medium mb-1">' . __('Action Taken') . '</label>';
    echo '<textarea name="actionTaken" rows="2" class="w-full border rounded px-3 py-2" placeholder="' . __('Describe the action taken in response to this incident...') . '"></textarea>';
    echo '</div>';

    // Follow-up section
    echo '<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">';

    echo '<div>';
    echo '<label class="block text-sm font-medium mb-1">' . __('Follow-up Required') . '</label>';
    echo '<select name="followUpRequired" id="followUpRequired" class="w-full border rounded px-3 py-2" onchange="toggleFollowUpDate()">';
    echo '<option value="N">' . __('No') . '</option>';
    echo '<option value="Y">' . __('Yes') . '</option>';
    echo '</select>';
    echo '</div>';

    echo '<div id="followUpDateContainer" style="display: none;">';
    echo '<label class="block text-sm font-medium mb-1">' . __('Follow-up Date') . '</label>';
    echo '<input type="date" name="followUpDate" class="w-full border rounded px-3 py-2">';
    echo '</div>';

    echo '</div>';

    echo '<button type="submit" class="bg-gray-700 text-white px-4 py-2 rounded hover:bg-gray-800">' . __('Log Disciplinary Record') . '</button>';

    echo '</form>';
    echo '</div>';

    // JavaScript for follow-up date toggle
    echo '<script>
    function toggleFollowUpDate() {
        var followUpRequired = document.getElementById("followUpRequired").value;
        var container = document.getElementById("followUpDateContainer");
        if (followUpRequired === "Y") {
            container.style.display = "block";
        } else {
            container.style.display = "none";
        }
    }
    </script>';

    // Build query criteria
    $criteria = $disciplinaryGateway->newQueryCriteria()
        ->sortBy(['incidentDate', 'incidentTime'], 'DESC')
        ->fromPOST();

    // Apply filters
    if (!empty($filterType)) {
        $criteria->filterBy('type', $filterType);
    }
    if (!empty($filterSeverity)) {
        $criteria->filterBy('severity', $filterSeverity);
    }
    if (!empty($filterCategory)) {
        $criteria->filterBy('category', $filterCategory);
    }
    if (!empty($filterStatus)) {
        $criteria->filterBy('status', $filterStatus);
    }
    if (!empty($filterPerson)) {
        $criteria->filterBy('staff', $filterPerson);
    }
    if (!empty($filterDateFrom)) {
        $criteria->filterBy('dateFrom', Format::dateConvert($filterDateFrom));
    }
    if (!empty($filterDateTo)) {
        $criteria->filterBy('dateTo', Format::dateConvert($filterDateTo));
    }

    // Apply quick filters
    if ($filter === 'open') {
        $criteria->filterBy('status', 'Open');
    } elseif ($filter === 'critical') {
        // Will show both Critical and Serious - handled by multiple calls or custom filter
        $criteria->filterBy('severity', 'Critical');
    } elseif ($filter === 'followup') {
        $criteria->filterBy('followUpRequired', 'Y');
        $criteria->filterBy('followUpCompleted', 'N');
    }

    // Get disciplinary records
    $records = $disciplinaryGateway->queryDisciplinaryRecords($criteria);

    // Build DataTable
    $table = DataTable::createPaginated('disciplinaryRecords', $criteria);
    $table->setTitle(__('Disciplinary Records'));

    // Add columns
    $table->addColumn('image', __('Photo'))
        ->notSortable()
        ->format(function ($row) use ($session) {
            $image = !empty($row['image_240']) ? $row['image_240'] : 'themes/Default/img/anonymous_240.jpg';
            return '<img src="' . $session->get('absoluteURL') . '/' . $image . '" class="w-10 h-10 rounded-full object-cover" alt="">';
        });

    $table->addColumn('name', __('Staff Member'))
        ->sortable(['surname', 'preferredName'])
        ->format(function ($row) {
            $output = Format::name('', $row['preferredName'], $row['surname'], 'Staff', true, true);
            if ($row['confidential'] === 'Y') {
                $output .= ' <span class="bg-gray-700 text-white text-xs px-2 py-0.5 rounded">&#128274;</span>';
            }
            return $output;
        });

    $table->addColumn('incidentDate', __('Date'))
        ->sortable()
        ->format(function ($row) {
            $output = Format::date($row['incidentDate']);
            if (!empty($row['incidentTime'])) {
                $output .= '<br><span class="text-xs text-gray-500">' . Format::time($row['incidentTime']) . '</span>';
            }
            return $output;
        });

    $table->addColumn('type', __('Type'))
        ->sortable()
        ->format(function ($row) {
            $colors = [
                'Verbal Warning'               => 'yellow',
                'Written Warning'              => 'orange',
                'Final Warning'                => 'red',
                'Suspension'                   => 'red',
                'Probation'                    => 'purple',
                'Performance Improvement Plan' => 'blue',
                'Termination'                  => 'gray',
                'Documentation'                => 'gray',
            ];
            $color = $colors[$row['type']] ?? 'gray';
            return '<span class="bg-' . $color . '-100 text-' . $color . '-800 text-xs px-2 py-1 rounded">' . __($row['type']) . '</span>';
        });

    $table->addColumn('severity', __('Severity'))
        ->sortable()
        ->format(function ($row) {
            $colors = [
                'Critical' => 'red',
                'Serious'  => 'orange',
                'Moderate' => 'yellow',
                'Low'      => 'green',
            ];
            $color = $colors[$row['severity']] ?? 'gray';
            return '<span class="bg-' . $color . '-100 text-' . $color . '-800 text-xs px-2 py-1 rounded">' . __($row['severity']) . '</span>';
        });

    $table->addColumn('category', __('Category'))
        ->sortable()
        ->format(function ($row) {
            return '<span class="text-sm">' . __($row['category']) . '</span>';
        });

    $table->addColumn('description', __('Description'))
        ->format(function ($row) {
            return '<span class="text-sm text-gray-600" title="' . htmlspecialchars($row['description']) . '">' .
                   htmlspecialchars(substr($row['description'], 0, 50)) .
                   (strlen($row['description']) > 50 ? '...' : '') . '</span>';
        });

    $table->addColumn('status', __('Status'))
        ->sortable()
        ->format(function ($row) {
            $statusClasses = [
                'Open'         => 'bg-yellow-100 text-yellow-800',
                'Under Review' => 'bg-orange-100 text-orange-800',
                'Resolved'     => 'bg-green-100 text-green-800',
                'Archived'     => 'bg-gray-100 text-gray-800',
            ];
            $class = $statusClasses[$row['status']] ?? 'bg-gray-100 text-gray-800';
            $output = '<span class="' . $class . ' text-xs px-2 py-1 rounded">' . __($row['status']) . '</span>';

            // Show follow-up indicator
            if ($row['followUpRequired'] === 'Y' && $row['followUpCompleted'] === 'N') {
                $output .= '<br><span class="text-xs text-purple-600">' . __('Follow-up pending') . '</span>';
            }

            return $output;
        });

    $table->addColumn('recordedBy', __('Recorded By'))
        ->notSortable()
        ->format(function ($row) {
            if (!empty($row['recordedByName']) && !empty($row['recordedBySurname'])) {
                return '<span class="text-xs text-gray-500">' . Format::name('', $row['recordedByName'], $row['recordedBySurname'], 'Staff', false, true) . '</span>';
            }
            return '<span class="text-gray-400">-</span>';
        });

    // Add action column
    $table->addActionColumn()
        ->addParam('gibbonStaffDisciplinaryID')
        ->format(function ($row, $actions) use ($session) {
            $actions->addAction('view', __('View Details'))
                ->setIcon('page_white')
                ->setURL('/modules/StaffManagement/staffManagement_disciplinary_view.php');

            $actions->addAction('edit', __('Edit'))
                ->setIcon('config')
                ->setURL('/modules/StaffManagement/staffManagement_disciplinary_edit.php');
        });

    // Output table
    echo '<h3 class="text-lg font-semibold mt-6 mb-3">' . __('All Disciplinary Records') . '</h3>';

    if ($records->count() > 0) {
        echo $table->render($records);
    } else {
        echo '<div class="bg-gray-50 rounded-lg p-4 text-center text-gray-500">';
        echo __('No disciplinary records found.');
        echo '</div>';
    }

    // Staff with Multiple Records Section
    $multipleRecordsStaff = $disciplinaryGateway->selectStaffWithMultipleRecords(2);

    if ($multipleRecordsStaff->rowCount() > 0) {
        echo '<h3 class="text-lg font-semibold mt-6 mb-3">' . __('Staff with Multiple Disciplinary Records') . '</h3>';
        echo '<div class="bg-white rounded-lg shadow p-4">';
        echo '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">';

        foreach ($multipleRecordsStaff as $staff) {
            $staffName = Format::name('', $staff['preferredName'], $staff['surname'], 'Staff', false, true);
            $image = !empty($staff['image_240']) ? $staff['image_240'] : 'themes/Default/img/anonymous_240.jpg';

            echo '<div class="bg-gray-50 border rounded-lg p-3 flex items-center space-x-3">';
            echo '<img src="' . $session->get('absoluteURL') . '/' . $image . '" class="w-12 h-12 rounded-full object-cover" alt="">';
            echo '<div class="flex-1">';
            echo '<p class="font-semibold">' . htmlspecialchars($staffName) . '</p>';
            echo '<div class="text-xs text-gray-600 space-x-2">';
            echo '<span>' . __('Total') . ': <strong>' . $staff['totalRecords'] . '</strong></span>';
            if ($staff['activeRecords'] > 0) {
                echo '<span class="text-orange-600">' . __('Active') . ': <strong>' . $staff['activeRecords'] . '</strong></span>';
            }
            echo '</div>';
            echo '<p class="text-xs text-gray-500">' . __('Last incident') . ': ' . Format::date($staff['lastIncident']) . '</p>';
            echo '</div>';
            echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_disciplinary.php&gibbonPersonID=' . $staff['gibbonPersonID'] . '" class="text-blue-600 hover:underline text-xs">' . __('View All') . '</a>';
            echo '</div>';
        }

        echo '</div>';
        echo '</div>';
    }

    // Link back to dashboard
    echo '<div class="mt-6">';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement.php" class="text-blue-600 hover:underline">&larr; ' . __('Back to Staff Management') . '</a>';
    echo '</div>';
}
