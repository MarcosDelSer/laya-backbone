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
use Gibbon\Module\StaffManagement\Domain\AuditLogGateway;

// Module setup - breadcrumbs
$page->breadcrumbs->add(__('Staff Management'), 'staffManagement.php');
$page->breadcrumbs->add(__('Audit Log'));

// Access check
if (!isActionAccessible($guid, $connection2, '/modules/StaffManagement/staffManagement_auditLog.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get date range from request or default to last 30 days
    $dateFrom = $_GET['dateFrom'] ?? date('Y-m-d', strtotime('-30 days'));
    $dateTo = $_GET['dateTo'] ?? date('Y-m-d');
    $tableFilter = $_GET['tableName'] ?? '';
    $actionFilter = $_GET['action'] ?? '';
    $personFilter = $_GET['person'] ?? '';
    $recordID = $_GET['recordID'] ?? '';

    // Validate date formats
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
        $dateFrom = date('Y-m-d', strtotime('-30 days'));
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
        $dateTo = date('Y-m-d');
    }

    // Ensure dateFrom is before dateTo
    if ($dateFrom > $dateTo) {
        $temp = $dateFrom;
        $dateFrom = $dateTo;
        $dateTo = $temp;
    }

    // Get gateway via DI container
    $auditLogGateway = $container->get(AuditLogGateway::class);

    // Page header
    echo '<h2>' . __('Audit Log') . '</h2>';
    echo '<p class="text-gray-600 mb-4">' . __('View all modifications made to staff management records with full audit trail.') . '</p>';

    // Filter Form
    $form = Form::create('auditLogFilter', $session->get('absoluteURL') . '/index.php');
    $form->setMethod('get');
    $form->setClass('noIntBorder fullWidth');
    $form->addHiddenValue('q', '/modules/StaffManagement/staffManagement_auditLog.php');

    $row = $form->addRow();
    $row->addLabel('dateFrom', __('Date From'));
    $row->addDate('dateFrom')->setValue(Format::date($dateFrom))->required();

    $row = $form->addRow();
    $row->addLabel('dateTo', __('Date To'));
    $row->addDate('dateTo')->setValue(Format::date($dateTo))->required();

    // Table filter options
    $tableOptions = [
        '' => __('-- All Tables --'),
        'gibbonStaffProfile' => __('Staff Profiles'),
        'gibbonStaffCertification' => __('Certifications'),
        'gibbonStaffEmergencyContact' => __('Emergency Contacts'),
        'gibbonStaffSchedule' => __('Schedules'),
        'gibbonStaffShiftTemplate' => __('Shift Templates'),
        'gibbonStaffTimeEntry' => __('Time Entries'),
        'gibbonStaffRatioSnapshot' => __('Ratio Snapshots'),
        'gibbonStaffDisciplinary' => __('Disciplinary Records'),
        'gibbonStaffAvailability' => __('Availability'),
        'gibbonStaffLeave' => __('Leave Requests'),
        'gibbonStaffRoomAssignment' => __('Room Assignments'),
    ];

    $row = $form->addRow();
    $row->addLabel('tableName', __('Table'));
    $row->addSelect('tableName')->fromArray($tableOptions)->selected($tableFilter);

    // Action filter options
    $actionOptions = [
        '' => __('-- All Actions --'),
        'INSERT' => __('Insert (Create)'),
        'UPDATE' => __('Update (Modify)'),
        'DELETE' => __('Delete'),
    ];

    $row = $form->addRow();
    $row->addLabel('action', __('Action'));
    $row->addSelect('action')->fromArray($actionOptions)->selected($actionFilter);

    // Record ID filter (optional)
    $row = $form->addRow();
    $row->addLabel('recordID', __('Record ID'))->description(__('Filter by specific record'));
    $row->addTextField('recordID')->setValue($recordID)->maxLength(20);

    $row = $form->addRow();
    $row->addSubmit(__('Filter'));

    echo $form->getOutput();

    // Quick date range links
    echo '<div class="mb-4 flex flex-wrap gap-2">';
    echo '<span class="text-gray-600 mr-2">' . __('Quick Select') . ':</span>';

    // Today
    $today = date('Y-m-d');
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_auditLog.php&dateFrom=' . $today . '&dateTo=' . $today . '" class="text-blue-600 hover:underline px-2">' . __('Today') . '</a>';

    // Last 7 days
    $last7Days = date('Y-m-d', strtotime('-7 days'));
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_auditLog.php&dateFrom=' . $last7Days . '&dateTo=' . $today . '" class="text-blue-600 hover:underline px-2">' . __('Last 7 Days') . '</a>';

    // Last 30 days
    $last30Days = date('Y-m-d', strtotime('-30 days'));
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_auditLog.php&dateFrom=' . $last30Days . '&dateTo=' . $today . '" class="text-blue-600 hover:underline px-2">' . __('Last 30 Days') . '</a>';

    // Last 90 days
    $last90Days = date('Y-m-d', strtotime('-90 days'));
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_auditLog.php&dateFrom=' . $last90Days . '&dateTo=' . $today . '" class="text-blue-600 hover:underline px-2">' . __('Last 90 Days') . '</a>';

    // This year
    $yearStart = date('Y-01-01');
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_auditLog.php&dateFrom=' . $yearStart . '&dateTo=' . $today . '" class="text-blue-600 hover:underline px-2">' . __('This Year') . '</a>';

    echo '</div>';

    // Display date range
    echo '<p class="text-lg mb-4">' . __('Showing audit records from') . ': <strong>' . Format::date($dateFrom) . '</strong> ' . __('to') . ' <strong>' . Format::date($dateTo) . '</strong></p>';

    // Get summary statistics
    $summary = $auditLogGateway->getAuditSummary($dateFrom, $dateTo);

    // Summary Statistics Cards
    echo '<div class="bg-white rounded-lg shadow p-4 mb-6">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">' . __('Period Summary') . '</h3>';
    echo '<div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-4 text-center">';

    echo '<div>';
    echo '<span class="block text-2xl font-bold text-blue-600">' . number_format($summary['totalChanges']) . '</span>';
    echo '<span class="text-sm text-gray-600">' . __('Total Changes') . '</span>';
    echo '</div>';

    echo '<div>';
    echo '<span class="block text-2xl font-bold text-green-600">' . number_format($summary['totalInserts']) . '</span>';
    echo '<span class="text-sm text-gray-600">' . __('Inserts') . '</span>';
    echo '</div>';

    echo '<div>';
    echo '<span class="block text-2xl font-bold text-yellow-600">' . number_format($summary['totalUpdates']) . '</span>';
    echo '<span class="text-sm text-gray-600">' . __('Updates') . '</span>';
    echo '</div>';

    echo '<div>';
    echo '<span class="block text-2xl font-bold text-red-600">' . number_format($summary['totalDeletes']) . '</span>';
    echo '<span class="text-sm text-gray-600">' . __('Deletes') . '</span>';
    echo '</div>';

    echo '<div>';
    echo '<span class="block text-2xl font-bold text-purple-600">' . number_format($summary['tablesAffected']) . '</span>';
    echo '<span class="text-sm text-gray-600">' . __('Tables Affected') . '</span>';
    echo '</div>';

    echo '<div>';
    echo '<span class="block text-2xl font-bold text-indigo-600">' . number_format($summary['recordsAffected']) . '</span>';
    echo '<span class="text-sm text-gray-600">' . __('Records Changed') . '</span>';
    echo '</div>';

    echo '<div>';
    echo '<span class="block text-2xl font-bold text-gray-600">' . number_format($summary['uniqueUsers']) . '</span>';
    echo '<span class="text-sm text-gray-600">' . __('Active Users') . '</span>';
    echo '</div>';

    echo '</div>';
    echo '</div>';

    // Changes by Table Summary
    $tablesSummary = $auditLogGateway->selectChangesSummaryByTable($dateFrom, $dateTo);
    if ($tablesSummary->rowCount() > 0) {
        echo '<div class="bg-white rounded-lg shadow p-4 mb-6">';
        echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">' . __('Changes by Table') . '</h3>';
        echo '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">';

        foreach ($tablesSummary as $tableStat) {
            $tableName = $tableStat['tableName'];
            // Format the table name for display
            $displayName = str_replace('gibbonStaff', '', $tableName);
            $displayName = preg_replace('/([a-z])([A-Z])/', '$1 $2', $displayName);

            echo '<div class="bg-gray-50 rounded p-3">';
            echo '<div class="flex justify-between items-center">';
            echo '<span class="font-medium">' . htmlspecialchars($displayName) . '</span>';
            echo '<span class="text-blue-600 font-bold">' . $tableStat['totalChanges'] . '</span>';
            echo '</div>';
            echo '<div class="text-xs text-gray-500 mt-1">';
            echo '<span class="text-green-600">' . $tableStat['inserts'] . ' ' . __('ins') . '</span> | ';
            echo '<span class="text-yellow-600">' . $tableStat['updates'] . ' ' . __('upd') . '</span> | ';
            echo '<span class="text-red-600">' . $tableStat['deletes'] . ' ' . __('del') . '</span>';
            echo '</div>';
            echo '</div>';
        }

        echo '</div>';
        echo '</div>';
    }

    // Changes by User Summary
    $usersSummary = $auditLogGateway->selectChangesSummaryByUser($dateFrom, $dateTo);
    if ($usersSummary->rowCount() > 0) {
        echo '<div class="bg-white rounded-lg shadow p-4 mb-6">';
        echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">' . __('Most Active Users') . '</h3>';
        echo '<div class="overflow-x-auto">';
        echo '<table class="w-full">';
        echo '<thead class="bg-gray-50">';
        echo '<tr>';
        echo '<th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">' . __('User') . '</th>';
        echo '<th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">' . __('Total') . '</th>';
        echo '<th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">' . __('Inserts') . '</th>';
        echo '<th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">' . __('Updates') . '</th>';
        echo '<th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">' . __('Deletes') . '</th>';
        echo '<th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">' . __('Last Change') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody class="divide-y divide-gray-200">';

        $count = 0;
        foreach ($usersSummary as $userStat) {
            if ($count >= 10) break; // Show top 10 users only
            $count++;

            $userName = !empty($userStat['preferredName'])
                ? Format::name('', $userStat['preferredName'], $userStat['surname'], 'Staff', true, true)
                : '<span class="text-gray-400">' . __('System') . '</span>';

            echo '<tr>';
            echo '<td class="px-3 py-2">' . $userName . '</td>';
            echo '<td class="px-3 py-2 text-center font-bold">' . $userStat['totalChanges'] . '</td>';
            echo '<td class="px-3 py-2 text-center text-green-600">' . $userStat['inserts'] . '</td>';
            echo '<td class="px-3 py-2 text-center text-yellow-600">' . $userStat['updates'] . '</td>';
            echo '<td class="px-3 py-2 text-center text-red-600">' . $userStat['deletes'] . '</td>';
            echo '<td class="px-3 py-2 text-gray-500">' . Format::dateTime($userStat['lastChange']) . '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
        echo '</div>';
        echo '</div>';
    }

    // Build query criteria for audit logs
    $criteria = $auditLogGateway->newQueryCriteria(true)
        ->sortBy(['timestampCreated'], 'DESC')
        ->filterBy('dateFrom', $dateFrom)
        ->filterBy('dateTo', $dateTo)
        ->fromPOST();

    if (!empty($tableFilter)) {
        $criteria->filterBy('tableName', $tableFilter);
    }
    if (!empty($actionFilter)) {
        $criteria->filterBy('action', $actionFilter);
    }
    if (!empty($personFilter)) {
        $criteria->filterBy('person', $personFilter);
    }
    if (!empty($recordID)) {
        $criteria->filterBy('recordID', $recordID);
    }

    // Query audit logs
    $auditLogs = $auditLogGateway->queryAuditLogs($criteria);

    // Build DataTable
    $table = DataTable::createPaginated('auditLogs', $criteria);
    $table->setTitle(__('Audit Log Entries'));

    // Add columns
    $table->addColumn('timestampCreated', __('Timestamp'))
        ->sortable()
        ->format(function ($row) {
            return Format::dateTime($row['timestampCreated']);
        });

    $table->addColumn('user', __('User'))
        ->notSortable()
        ->format(function ($row) {
            if (!empty($row['preferredName']) && !empty($row['surname'])) {
                return Format::name('', $row['preferredName'], $row['surname'], 'Staff', true, true)
                    . '<br><span class="text-xs text-gray-500">' . htmlspecialchars($row['username'] ?? '') . '</span>';
            }
            return '<span class="text-gray-400">' . __('System') . '</span>';
        });

    $table->addColumn('tableName', __('Table'))
        ->sortable()
        ->format(function ($row) {
            // Format the table name for display
            $displayName = str_replace('gibbonStaff', '', $row['tableName']);
            $displayName = preg_replace('/([a-z])([A-Z])/', '$1 $2', $displayName);
            return '<span class="font-medium">' . htmlspecialchars($displayName) . '</span>'
                . '<br><span class="text-xs text-gray-500">' . __('ID') . ': ' . $row['recordID'] . '</span>';
        });

    $table->addColumn('action', __('Action'))
        ->sortable()
        ->format(function ($row) {
            $actionClasses = [
                'INSERT' => 'bg-green-100 text-green-800',
                'UPDATE' => 'bg-yellow-100 text-yellow-800',
                'DELETE' => 'bg-red-100 text-red-800',
            ];
            $class = $actionClasses[$row['action']] ?? 'bg-gray-100 text-gray-800';
            return '<span class="' . $class . ' text-xs px-2 py-1 rounded font-medium">' . $row['action'] . '</span>';
        });

    $table->addColumn('fieldName', __('Field'))
        ->notSortable()
        ->format(function ($row) {
            if (empty($row['fieldName'])) {
                return '<span class="text-gray-400">-</span>';
            }
            // Format camelCase to readable format
            $displayName = preg_replace('/([a-z])([A-Z])/', '$1 $2', $row['fieldName']);
            $displayName = ucfirst($displayName);
            return htmlspecialchars($displayName);
        });

    $table->addColumn('oldValue', __('Old Value'))
        ->notSortable()
        ->format(function ($row) {
            if ($row['action'] === 'INSERT' || empty($row['oldValue'])) {
                return '<span class="text-gray-400">-</span>';
            }
            $value = $row['oldValue'];
            // Truncate long values
            if (strlen($value) > 50) {
                $value = substr($value, 0, 50) . '...';
            }
            // Mask sensitive fields
            if (in_array($row['fieldName'], ['sin', 'bankAccount', 'bankInstitution', 'bankTransit'])) {
                return '<span class="text-gray-400">[' . __('masked') . ']</span>';
            }
            return '<span class="text-red-600 line-through">' . htmlspecialchars($value) . '</span>';
        });

    $table->addColumn('newValue', __('New Value'))
        ->notSortable()
        ->format(function ($row) {
            if ($row['action'] === 'DELETE' || empty($row['newValue'])) {
                return '<span class="text-gray-400">-</span>';
            }
            $value = $row['newValue'];
            // Truncate long values
            if (strlen($value) > 50) {
                $value = substr($value, 0, 50) . '...';
            }
            // Mask sensitive fields
            if (in_array($row['fieldName'], ['sin', 'bankAccount', 'bankInstitution', 'bankTransit'])) {
                return '<span class="text-gray-400">[' . __('masked') . ']</span>';
            }
            return '<span class="text-green-600">' . htmlspecialchars($value) . '</span>';
        });

    $table->addColumn('ipAddress', __('IP Address'))
        ->notSortable()
        ->format(function ($row) {
            if (empty($row['ipAddress'])) {
                return '<span class="text-gray-400">-</span>';
            }
            return '<span class="text-xs text-gray-500">' . htmlspecialchars($row['ipAddress']) . '</span>';
        });

    // Output table
    echo $table->render($auditLogs);

    // Export notice
    echo '<div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mt-6">';
    echo '<h4 class="font-semibold text-blue-800 mb-2">' . __('Data Retention') . '</h4>';
    echo '<p class="text-sm text-blue-700">' . __('Audit log entries are retained for regulatory compliance purposes. Contact your system administrator for data export or retention policy information.') . '</p>';
    echo '</div>';

    // Link back to Dashboard
    echo '<div class="mt-6">';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement.php" class="text-blue-600 hover:underline">&larr; ' . __('Back to Staff Dashboard') . '</a>';
    echo '</div>';
}
