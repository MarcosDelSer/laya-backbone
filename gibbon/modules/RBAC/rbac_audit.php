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
use Gibbon\Module\RBAC\Domain\AuditGateway;

if (isActionAccessible($guid, $connection2, '/modules/RBAC/rbac_audit.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs->add(__('View Audit Trail'));

    // Get gateway
    $auditGateway = $container->get(AuditGateway::class);

    // Filter options
    $actionOptions = [
        ''               => __('All Actions'),
        'login'          => __('Login'),
        'logout'         => __('Logout'),
        'access_granted' => __('Access Granted'),
        'access_denied'  => __('Access Denied'),
        'data_read'      => __('Data Read'),
        'data_create'    => __('Data Create'),
        'data_update'    => __('Data Update'),
        'data_delete'    => __('Data Delete'),
        'role_assigned'  => __('Role Assigned'),
        'role_revoked'   => __('Role Revoked'),
    ];

    $successOptions = [
        ''  => __('All Results'),
        'Y' => __('Successful'),
        'N' => __('Failed'),
    ];

    // Get filter values
    $action = $_GET['action'] ?? '';
    $success = $_GET['success'] ?? '';
    $dateFrom = $_GET['dateFrom'] ?? date('Y-m-d', strtotime('-7 days'));
    $dateTo = $_GET['dateTo'] ?? date('Y-m-d');
    $resourceType = $_GET['resourceType'] ?? '';

    // Filter form
    $form = Form::create('filter', $session->get('absoluteURL') . '/index.php', 'get');
    $form->setTitle(__('Filter'));
    $form->setClass('noIntBorder fullWidth');

    $form->addHiddenValue('q', '/modules/RBAC/rbac_audit.php');

    $row = $form->addRow();
        $row->addLabel('dateFrom', __('Date From'));
        $row->addDate('dateFrom')->setValue($dateFrom);

    $row = $form->addRow();
        $row->addLabel('dateTo', __('Date To'));
        $row->addDate('dateTo')->setValue($dateTo);

    $row = $form->addRow();
        $row->addLabel('action', __('Action Type'));
        $row->addSelect('action')
            ->fromArray($actionOptions)
            ->selected($action);

    $row = $form->addRow();
        $row->addLabel('success', __('Result'));
        $row->addSelect('success')
            ->fromArray($successOptions)
            ->selected($success);

    $row = $form->addRow();
        $row->addLabel('resourceType', __('Resource Type'));
        $row->addTextField('resourceType')
            ->setValue($resourceType)
            ->placeholder(__('e.g., children, staff, roles'));

    $row = $form->addRow();
        $row->addSearchSubmit($session, __('Clear Filters'));

    echo $form->getOutput();

    // Build query criteria
    $criteria = $auditGateway->newQueryCriteria(true)
        ->sortBy(['timestampCreated'], 'DESC')
        ->filterBy('action', $action)
        ->filterBy('success', $success)
        ->filterBy('dateFrom', $dateFrom)
        ->filterBy('dateTo', $dateTo)
        ->filterBy('resourceType', $resourceType)
        ->fromPOST();

    // Get audit logs
    $auditLogs = $auditGateway->queryAuditLogs($criteria);

    // Create data table
    $table = DataTable::createPaginated('auditLogs', $criteria);
    $table->setTitle(__('Audit Trail'));

    // Add columns
    $table->addColumn('timestampCreated', __('Timestamp'))
        ->sortable()
        ->format(function ($log) {
            return Format::dateTime($log['timestampCreated']);
        });

    $table->addColumn('user', __('User'))
        ->format(function ($log) {
            $name = Format::name('', $log['preferredName'], $log['surname'], 'Staff', false);
            return htmlspecialchars($name);
        });

    $table->addColumn('action', __('Action'))
        ->sortable()
        ->format(function ($log) {
            $actionColors = [
                'login'          => 'bg-blue-100 text-blue-800',
                'logout'         => 'bg-gray-100 text-gray-800',
                'access_granted' => 'bg-green-100 text-green-800',
                'access_denied'  => 'bg-red-100 text-red-800',
                'data_read'      => 'bg-purple-100 text-purple-800',
                'data_create'    => 'bg-teal-100 text-teal-800',
                'data_update'    => 'bg-yellow-100 text-yellow-800',
                'data_delete'    => 'bg-orange-100 text-orange-800',
                'role_assigned'  => 'bg-indigo-100 text-indigo-800',
                'role_revoked'   => 'bg-pink-100 text-pink-800',
            ];
            $color = $actionColors[$log['action']] ?? 'bg-gray-100 text-gray-800';
            $actionLabel = str_replace('_', ' ', ucfirst($log['action']));
            return '<span class="' . $color . ' text-xs px-2 py-1 rounded">' . $actionLabel . '</span>';
        });

    $table->addColumn('resourceType', __('Resource'))
        ->format(function ($log) {
            if (empty($log['resourceType'])) {
                return '<span class="text-gray-400">-</span>';
            }
            $resource = htmlspecialchars($log['resourceType']);
            if (!empty($log['resourceID'])) {
                $resource .= ' <code class="text-xs text-gray-500">#' . $log['resourceID'] . '</code>';
            }
            return $resource;
        });

    $table->addColumn('details', __('Details'))
        ->format(function ($log) {
            if (empty($log['details'])) {
                return '<span class="text-gray-400">-</span>';
            }
            $details = json_decode($log['details'], true);
            if (is_array($details) && isset($details['event'])) {
                return htmlspecialchars($details['event']);
            }
            $detailStr = htmlspecialchars(substr($log['details'], 0, 50));
            if (strlen($log['details']) > 50) {
                $detailStr .= '...';
            }
            return '<span title="' . htmlspecialchars($log['details']) . '">' . $detailStr . '</span>';
        });

    $table->addColumn('success', __('Result'))
        ->format(function ($log) {
            if ($log['success'] === 'Y') {
                return '<span class="tag success">' . __('Success') . '</span>';
            }
            return '<span class="tag error">' . __('Failed') . '</span>';
        });

    $table->addColumn('ipAddress', __('IP Address'))
        ->format(function ($log) {
            if (empty($log['ipAddress'])) {
                return '<span class="text-gray-400">-</span>';
            }
            return '<code class="text-xs">' . htmlspecialchars($log['ipAddress']) . '</code>';
        });

    echo $table->render($auditLogs);

    // Summary section
    $summary = $auditGateway->getAuditSummary($dateFrom, $dateTo);

    echo '<div class="mt-6 bg-white rounded-lg shadow p-4">';
    echo '<h3 class="text-lg font-semibold mb-3">' . __('Audit Summary') . ' (' . Format::date($dateFrom) . ' - ' . Format::date($dateTo) . ')</h3>';

    if (!empty($summary)) {
        echo '<div class="overflow-x-auto">';
        echo '<table class="w-full text-sm">';
        echo '<thead class="bg-gray-50">';
        echo '<tr>';
        echo '<th class="text-left p-2">' . __('Action') . '</th>';
        echo '<th class="text-right p-2">' . __('Total') . '</th>';
        echo '<th class="text-right p-2">' . __('Successful') . '</th>';
        echo '<th class="text-right p-2">' . __('Failed') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        foreach ($summary as $row) {
            $actionLabel = str_replace('_', ' ', ucfirst($row['action']));
            echo '<tr class="border-b">';
            echo '<td class="p-2">' . $actionLabel . '</td>';
            echo '<td class="text-right p-2 font-semibold">' . $row['count'] . '</td>';
            echo '<td class="text-right p-2 text-green-600">' . $row['successful'] . '</td>';
            echo '<td class="text-right p-2 text-red-600">' . ($row['failed'] > 0 ? $row['failed'] : '-') . '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    } else {
        echo '<p class="text-gray-500">' . __('No audit data available for the selected period.') . '</p>';
    }

    echo '</div>';

    // Unauthorized access attempts section
    $unauthorizedAttempts = $auditGateway->selectUnauthorizedAccessAttempts($dateFrom, $dateTo, 10);

    if ($unauthorizedAttempts->rowCount() > 0) {
        echo '<div class="mt-6 bg-red-50 border border-red-200 rounded-lg p-4">';
        echo '<h3 class="text-lg font-semibold text-red-800 mb-3">' . __('Recent Unauthorized Access Attempts') . '</h3>';

        echo '<div class="overflow-x-auto">';
        echo '<table class="w-full text-sm">';
        echo '<thead class="bg-red-100">';
        echo '<tr>';
        echo '<th class="text-left p-2">' . __('Time') . '</th>';
        echo '<th class="text-left p-2">' . __('User') . '</th>';
        echo '<th class="text-left p-2">' . __('Resource') . '</th>';
        echo '<th class="text-left p-2">' . __('IP Address') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        while ($attempt = $unauthorizedAttempts->fetch()) {
            $userName = Format::name('', $attempt['preferredName'], $attempt['surname'], 'Staff', false);
            echo '<tr class="border-b border-red-100">';
            echo '<td class="p-2">' . Format::dateTime($attempt['timestampCreated']) . '</td>';
            echo '<td class="p-2">' . htmlspecialchars($userName) . '</td>';
            echo '<td class="p-2">';
            echo htmlspecialchars($attempt['resourceType'] ?? '-');
            if (!empty($attempt['resourceID'])) {
                echo ' <code class="text-xs">#' . $attempt['resourceID'] . '</code>';
            }
            echo '</td>';
            echo '<td class="p-2"><code class="text-xs">' . htmlspecialchars($attempt['ipAddress'] ?? '-') . '</code></td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
        echo '</div>';
        echo '</div>';
    }

    // Information note
    echo '<div class="message mt-4">';
    echo __('The audit trail records all significant system events including logins, data access, and permission changes. Use the filters above to narrow down your search. Data is retained according to your organization\'s audit retention policy.');
    echo '</div>';
}
