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
use Gibbon\Module\AISync\Domain\AISyncGateway;
use Gibbon\Domain\System\SettingGateway;

if (isActionAccessible($guid, $connection2, '/modules/AISync/aiSync_health.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs->add(__('Webhook Health Monitoring'));

    // Get gateways
    $aiSyncGateway = $container->get(AISyncGateway::class);
    $settingGateway = $container->get(SettingGateway::class);

    // Get date filters
    $dateFrom = $_GET['dateFrom'] ?? date('Y-m-d', strtotime('-7 days'));
    $dateTo = $_GET['dateTo'] ?? date('Y-m-d');

    // Get health metrics
    $health = $aiSyncGateway->getWebhookHealth($dateFrom, $dateTo);

    // Display health status banner
    $statusColors = [
        'healthy' => ['bg-green-50', 'border-green-200', 'text-green-800'],
        'warning' => ['bg-yellow-50', 'border-yellow-200', 'text-yellow-800'],
        'critical' => ['bg-red-50', 'border-red-200', 'text-red-800'],
    ];

    $statusIcons = [
        'healthy' => '✓',
        'warning' => '⚠',
        'critical' => '✗',
    ];

    $status = $health['overall']['status'];
    $colors = $statusColors[$status] ?? $statusColors['healthy'];

    echo '<div class="' . $colors[0] . ' border ' . $colors[1] . ' rounded-lg p-4 mb-6">';
    echo '<div class="flex items-center justify-between">';
    echo '<div class="flex items-center">';
    echo '<span class="text-3xl mr-3">' . $statusIcons[$status] . '</span>';
    echo '<div>';
    echo '<h3 class="text-lg font-bold ' . $colors[2] . '">';
    echo __('Webhook Health Status') . ': ' . ucfirst($status);
    echo '</h3>';

    if (!empty($health['overall']['issues'])) {
        echo '<ul class="mt-2 text-sm ' . $colors[2] . '">';
        foreach ($health['overall']['issues'] as $issue) {
            echo '<li>• ' . htmlspecialchars($issue) . '</li>';
        }
        echo '</ul>';
    } else {
        echo '<p class="text-sm ' . $colors[2] . '">' . __('All webhook systems operating normally') . '</p>';
    }
    echo '</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    // Display key metrics
    echo '<div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">';

    // Total
    echo '<div class="bg-gray-50 border border-gray-200 rounded-lg p-4 text-center">';
    echo '<div class="text-3xl font-bold text-gray-600">' . number_format($health['overall']['total']) . '</div>';
    echo '<div class="text-sm text-gray-700">' . __('Total Syncs') . '</div>';
    echo '</div>';

    // Pending
    echo '<div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 text-center">';
    echo '<div class="text-3xl font-bold text-yellow-600">' . number_format($health['overall']['pending']) . '</div>';
    echo '<div class="text-sm text-yellow-700">' . __('Pending') . '</div>';
    echo '</div>';

    // Success
    echo '<div class="bg-green-50 border border-green-200 rounded-lg p-4 text-center">';
    echo '<div class="text-3xl font-bold text-green-600">' . number_format($health['overall']['success']) . '</div>';
    echo '<div class="text-sm text-green-700">' . __('Successful') . '</div>';
    echo '<div class="text-xs text-green-600 mt-1">' . $health['overall']['successRate'] . '%</div>';
    echo '</div>';

    // Failed
    echo '<div class="bg-red-50 border border-red-200 rounded-lg p-4 text-center">';
    echo '<div class="text-3xl font-bold text-red-600">' . number_format($health['overall']['failed']) . '</div>';
    echo '<div class="text-sm text-red-700">' . __('Failed') . '</div>';
    echo '<div class="text-xs text-red-600 mt-1">' . $health['overall']['failureRate'] . '%</div>';
    echo '</div>';

    // Permanently Failed
    echo '<div class="bg-orange-50 border border-orange-200 rounded-lg p-4 text-center">';
    echo '<div class="text-3xl font-bold text-orange-600">' . number_format($health['overall']['permanentlyFailed']) . '</div>';
    echo '<div class="text-sm text-orange-700">' . __('Permanently Failed') . '</div>';
    echo '</div>';

    echo '</div>';

    // Performance metrics
    echo '<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">';

    // Average retries until success
    echo '<div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-center">';
    echo '<div class="text-2xl font-bold text-blue-600">' . $health['performance']['avgRetriesUntilSuccess'] . '</div>';
    echo '<div class="text-sm text-blue-700">' . __('Avg Retries (Success)') . '</div>';
    echo '</div>';

    // Max retry count
    echo '<div class="bg-purple-50 border border-purple-200 rounded-lg p-4 text-center">';
    echo '<div class="text-2xl font-bold text-purple-600">' . $health['performance']['maxRetryCount'] . '</div>';
    echo '<div class="text-sm text-purple-700">' . __('Max Retry Count') . '</div>';
    echo '</div>';

    // Stale pending
    echo '<div class="bg-orange-50 border border-orange-200 rounded-lg p-4 text-center">';
    echo '<div class="text-2xl font-bold text-orange-600">' . $health['performance']['stalePending'] . '</div>';
    echo '<div class="text-sm text-orange-700">' . __('Stale Pending (>5min)') . '</div>';
    echo '</div>';

    // Recent failures
    echo '<div class="bg-red-50 border border-red-200 rounded-lg p-4 text-center">';
    echo '<div class="text-2xl font-bold text-red-600">' . $health['performance']['recentFailures'] . '</div>';
    echo '<div class="text-sm text-red-700">' . __('Recent Failures (1hr)') . '</div>';
    echo '</div>';

    echo '</div>';

    // Date range filter form
    $form = Form::create('filter', $session->get('absoluteURL') . '/index.php', 'get');
    $form->setTitle(__('Filter'));
    $form->setClass('noIntBorder fullWidth');

    $form->addHiddenValue('q', '/modules/AISync/aiSync_health.php');

    $row = $form->addRow();
        $row->addLabel('dateFrom', __('Date From'));
        $row->addDate('dateFrom')
            ->setValue($dateFrom)
            ->required();

    $row = $form->addRow();
        $row->addLabel('dateTo', __('Date To'));
        $row->addDate('dateTo')
            ->setValue($dateTo)
            ->required();

    $row = $form->addRow();
        $row->addFooter();
        $row->addSearchSubmit($session);

    echo $form->getOutput();

    // Get statistics by event type
    $statsByEvent = $aiSyncGateway->getSyncStatisticsByEventType($dateFrom, $dateTo);

    if (!empty($statsByEvent)) {
        echo '<h3 class="text-lg font-bold mb-4 mt-6">' . __('Sync Statistics by Event Type') . '</h3>';

        echo '<div class="overflow-x-auto">';
        echo '<table class="min-w-full bg-white border border-gray-300 rounded-lg">';
        echo '<thead class="bg-gray-100">';
        echo '<tr>';
        echo '<th class="px-4 py-2 text-left border-b">' . __('Event Type') . '</th>';
        echo '<th class="px-4 py-2 text-center border-b">' . __('Total') . '</th>';
        echo '<th class="px-4 py-2 text-center border-b">' . __('Success') . '</th>';
        echo '<th class="px-4 py-2 text-center border-b">' . __('Failed') . '</th>';
        echo '<th class="px-4 py-2 text-center border-b">' . __('Success Rate') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        foreach ($statsByEvent as $stat) {
            $total = (int)$stat['count'];
            $success = (int)$stat['successCount'];
            $failed = (int)$stat['failedCount'];
            $successRate = $total > 0 ? round(($success / $total) * 100, 1) : 0;

            echo '<tr class="hover:bg-gray-50">';
            echo '<td class="px-4 py-2 border-b font-medium">' . htmlspecialchars($stat['eventType']) . '</td>';
            echo '<td class="px-4 py-2 border-b text-center">' . number_format($total) . '</td>';
            echo '<td class="px-4 py-2 border-b text-center text-green-600">' . number_format($success) . '</td>';
            echo '<td class="px-4 py-2 border-b text-center text-red-600">' . number_format($failed) . '</td>';
            echo '<td class="px-4 py-2 border-b text-center">';

            if ($successRate >= 95) {
                echo '<span class="px-2 py-1 bg-green-100 text-green-800 rounded">' . $successRate . '%</span>';
            } elseif ($successRate >= 75) {
                echo '<span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded">' . $successRate . '%</span>';
            } else {
                echo '<span class="px-2 py-1 bg-red-100 text-red-800 rounded">' . $successRate . '%</span>';
            }

            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    }

    // Get statistics by entity type
    $statsByEntity = $aiSyncGateway->getSyncStatisticsByEntityType($dateFrom, $dateTo);

    if (!empty($statsByEntity)) {
        echo '<h3 class="text-lg font-bold mb-4 mt-6">' . __('Sync Statistics by Entity Type') . '</h3>';

        echo '<div class="overflow-x-auto">';
        echo '<table class="min-w-full bg-white border border-gray-300 rounded-lg">';
        echo '<thead class="bg-gray-100">';
        echo '<tr>';
        echo '<th class="px-4 py-2 text-left border-b">' . __('Entity Type') . '</th>';
        echo '<th class="px-4 py-2 text-center border-b">' . __('Total') . '</th>';
        echo '<th class="px-4 py-2 text-center border-b">' . __('Success') . '</th>';
        echo '<th class="px-4 py-2 text-center border-b">' . __('Failed') . '</th>';
        echo '<th class="px-4 py-2 text-center border-b">' . __('Success Rate') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        foreach ($statsByEntity as $stat) {
            $total = (int)$stat['count'];
            $success = (int)$stat['successCount'];
            $failed = (int)$stat['failedCount'];
            $successRate = $total > 0 ? round(($success / $total) * 100, 1) : 0;

            echo '<tr class="hover:bg-gray-50">';
            echo '<td class="px-4 py-2 border-b font-medium">' . htmlspecialchars($stat['entityType']) . '</td>';
            echo '<td class="px-4 py-2 border-b text-center">' . number_format($total) . '</td>';
            echo '<td class="px-4 py-2 border-b text-center text-green-600">' . number_format($success) . '</td>';
            echo '<td class="px-4 py-2 border-b text-center text-red-600">' . number_format($failed) . '</td>';
            echo '<td class="px-4 py-2 border-b text-center">';

            if ($successRate >= 95) {
                echo '<span class="px-2 py-1 bg-green-100 text-green-800 rounded">' . $successRate . '%</span>';
            } elseif ($successRate >= 75) {
                echo '<span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded">' . $successRate . '%</span>';
            } else {
                echo '<span class="px-2 py-1 bg-red-100 text-red-800 rounded">' . $successRate . '%</span>';
            }

            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    }

    // Get module settings for reference
    $syncEnabled = $settingGateway->getSettingByScope('AI Sync', 'syncEnabled');
    $maxRetries = $settingGateway->getSettingByScope('AI Sync', 'maxRetryAttempts');
    $retryDelay = $settingGateway->getSettingByScope('AI Sync', 'retryDelaySeconds');
    $webhookTimeout = $settingGateway->getSettingByScope('AI Sync', 'webhookTimeout');

    // Display configuration info
    echo '<h3 class="text-lg font-bold mb-4 mt-6">' . __('Current Configuration') . '</h3>';

    echo '<div class="bg-gray-50 border border-gray-200 rounded-lg p-4">';
    echo '<div class="grid grid-cols-1 md:grid-cols-2 gap-4">';

    echo '<div>';
    echo '<span class="font-semibold">' . __('Sync Enabled') . ':</span> ';
    if ($syncEnabled === 'Y') {
        echo '<span class="px-2 py-1 bg-green-100 text-green-800 rounded">Yes</span>';
    } else {
        echo '<span class="px-2 py-1 bg-red-100 text-red-800 rounded">No</span>';
    }
    echo '</div>';

    echo '<div>';
    echo '<span class="font-semibold">' . __('Max Retry Attempts') . ':</span> ';
    echo '<span class="px-2 py-1 bg-blue-100 text-blue-800 rounded">' . htmlspecialchars($maxRetries) . '</span>';
    echo '</div>';

    echo '<div>';
    echo '<span class="font-semibold">' . __('Retry Base Delay') . ':</span> ';
    echo '<span class="px-2 py-1 bg-blue-100 text-blue-800 rounded">' . htmlspecialchars($retryDelay) . 's</span>';
    echo '</div>';

    echo '<div>';
    echo '<span class="font-semibold">' . __('Webhook Timeout') . ':</span> ';
    echo '<span class="px-2 py-1 bg-blue-100 text-blue-800 rounded">' . htmlspecialchars($webhookTimeout) . 's</span>';
    echo '</div>';

    echo '</div>';
    echo '</div>';

    // Helpful links
    echo '<div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mt-6">';
    echo '<h4 class="font-bold text-blue-800 mb-2">' . __('Quick Links') . '</h4>';
    echo '<ul class="list-disc list-inside text-sm text-blue-700">';
    echo '<li><a href="index.php?q=/modules/AISync/aiSync_logs.php" class="underline">' . __('View Detailed Sync Logs') . '</a></li>';
    echo '<li><a href="index.php?q=/modules/AISync/aiSync_retry.php" class="underline">' . __('Retry Failed Syncs') . '</a></li>';
    echo '<li><a href="index.php?q=/modules/AISync/aiSync_settings.php" class="underline">' . __('Configure Settings') . '</a></li>';
    echo '</ul>';
    echo '</div>';
}
