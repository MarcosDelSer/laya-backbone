<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuiber and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Gibbon Core) and Gibbon LAYA are trademarks of Gibbon Education Ltd.

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
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Services\Format;
use Gibbon\Tables\DataTable;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Module\NotificationEngine\Domain\NotificationGateway;
use Gibbon\Domain\System\SettingGateway;

// Module includes
require_once __DIR__ . '/moduleFunctions.php';

$page->breadcrumbs->add(__('Retry Management'));

if (!isActionAccessible($guid, $connection2, '/modules/NotificationEngine/notifications_retry_management.php')) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get services
    $notificationGateway = $container->get(NotificationGateway::class);
    $settingGateway = $container->get(SettingGateway::class);

    // Get current settings
    $maxRetryAttempts = (int) ($settingGateway->getSettingByScope('Notification Engine', 'maxRetryAttempts') ?: 3);
    $retryDelayMinutes = (int) ($settingGateway->getSettingByScope('Notification Engine', 'retryDelayMinutes') ?: 5);

    // Get retry health metrics
    $healthMetrics = $notificationGateway->getRetryHealthMetrics();

    echo '<h2>' . __('Retry System Health') . '</h2>';

    // Display health metrics
    echo '<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">';

    // Total Retrying
    $statusClass = $healthMetrics['total_retrying'] > 50 ? 'bg-orange-100 border-orange-500' : 'bg-blue-100 border-blue-500';
    echo '<div class="border-l-4 p-4 ' . $statusClass . '">';
    echo '<div class="text-2xl font-bold">' . $healthMetrics['total_retrying'] . '</div>';
    echo '<div class="text-sm text-gray-600">' . __('Total Retrying') . '</div>';
    echo '</div>';

    // Pending Retries
    $statusClass = $healthMetrics['pending_retry_count'] > 30 ? 'bg-orange-100 border-orange-500' : 'bg-green-100 border-green-500';
    echo '<div class="border-l-4 p-4 ' . $statusClass . '">';
    echo '<div class="text-2xl font-bold">' . $healthMetrics['pending_retry_count'] . '</div>';
    echo '<div class="text-sm text-gray-600">' . __('Pending Retry') . '</div>';
    echo '</div>';

    // Permanently Failed
    $statusClass = $healthMetrics['permanently_failed_count'] > 10 ? 'bg-red-100 border-red-500' : 'bg-gray-100 border-gray-500';
    echo '<div class="border-l-4 p-4 ' . $statusClass . '">';
    echo '<div class="text-2xl font-bold">' . $healthMetrics['permanently_failed_count'] . '</div>';
    echo '<div class="text-sm text-gray-600">' . __('Permanently Failed') . '</div>';
    echo '</div>';

    // Retry Recovery Rate
    $statusClass = $healthMetrics['retry_recovery_rate'] > 10 ? 'bg-orange-100 border-orange-500' : 'bg-green-100 border-green-500';
    echo '<div class="border-l-4 p-4 ' . $statusClass . '">';
    echo '<div class="text-2xl font-bold">' . number_format($healthMetrics['retry_recovery_rate'], 1) . '%</div>';
    echo '<div class="text-sm text-gray-600">' . __('Retry Recovery Rate') . '</div>';
    echo '</div>';

    echo '</div>';

    // Detailed statistics
    echo '<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">';

    // Success Rate
    echo '<div class="bg-white border rounded p-4">';
    echo '<h4 class="font-bold mb-2">' . __('Overall Success Rate') . '</h4>';
    $successRateColor = $healthMetrics['success_rate'] > 95 ? 'text-green-600' : ($healthMetrics['success_rate'] > 85 ? 'text-orange-600' : 'text-red-600');
    echo '<div class="text-3xl ' . $successRateColor . ' font-bold">' . number_format($healthMetrics['success_rate'], 2) . '%</div>';
    echo '<div class="text-sm text-gray-600 mt-2">';
    echo __('Sent: {sent} / {total}', [
        'sent' => $healthMetrics['sent_count'],
        'total' => $healthMetrics['total_notifications']
    ]);
    echo '</div>';
    echo '</div>';

    // Failure Rate
    echo '<div class="bg-white border rounded p-4">';
    echo '<h4 class="font-bold mb-2">' . __('Permanent Failure Rate') . '</h4>';
    $failureRateColor = $healthMetrics['failure_rate'] < 2 ? 'text-green-600' : ($healthMetrics['failure_rate'] < 5 ? 'text-orange-600' : 'text-red-600');
    echo '<div class="text-3xl ' . $failureRateColor . ' font-bold">' . number_format($healthMetrics['failure_rate'], 2) . '%</div>';
    echo '<div class="text-sm text-gray-600 mt-2">';
    echo __('Failed: {failed} / {total}', [
        'failed' => $healthMetrics['failed_count'],
        'total' => $healthMetrics['total_notifications']
    ]);
    echo '</div>';
    echo '</div>';

    // Average Attempts
    echo '<div class="bg-white border rounded p-4">';
    echo '<h4 class="font-bold mb-2">' . __('Average Attempts') . '</h4>';
    echo '<div class="text-3xl text-blue-600 font-bold">' . number_format($healthMetrics['avg_attempts'], 2) . '</div>';
    echo '<div class="text-sm text-gray-600 mt-2">';
    echo __('Max attempts configured: {max}', ['max' => $maxRetryAttempts]);
    echo '</div>';
    echo '</div>';

    echo '</div>';

    // Current Settings
    echo '<h3 class="mb-2">' . __('Retry Configuration') . '</h3>';
    echo '<div class="bg-blue-50 border border-blue-200 rounded p-4 mb-6">';
    echo '<p class="mb-2"><strong>' . __('Maximum Retry Attempts:') . '</strong> ' . $maxRetryAttempts . '</p>';
    echo '<p class="mb-2"><strong>' . __('Base Retry Delay:') . '</strong> ' . $retryDelayMinutes . ' ' . __('minutes') . ' ' . __('(exponential backoff)') . '</p>';
    echo '<p class="mb-2"><strong>' . __('Retry Schedule:') . '</strong></p>';
    echo '<ul class="list-disc list-inside ml-4">';
    for ($i = 1; $i <= $maxRetryAttempts; $i++) {
        $delay = $notificationGateway->calculateRetryDelay($i, $retryDelayMinutes);
        echo '<li>' . __('Attempt {attempt}: {delay} minutes after last failure', [
            'attempt' => $i,
            'delay' => $delay
        ]) . '</li>';
    }
    echo '</ul>';
    echo '<p class="mt-4 text-sm text-gray-600">';
    echo __('Configure these settings in: Admin → System Admin → Notification Engine → Settings');
    echo '</p>';
    echo '</div>';

    // Retry Statistics by Attempt Number
    echo '<h3>' . __('Retry Statistics by Attempt Number') . '</h3>';

    $retryStats = $notificationGateway->getRetryStatistics();

    if (!empty($retryStats)) {
        echo '<table class="w-full mb-6 border-collapse">';
        echo '<thead>';
        echo '<tr class="bg-gray-100">';
        echo '<th class="border p-2 text-left">' . __('Attempt Number') . '</th>';
        echo '<th class="border p-2 text-right">' . __('Total') . '</th>';
        echo '<th class="border p-2 text-right">' . __('Pending') . '</th>';
        echo '<th class="border p-2 text-right">' . __('Failed') . '</th>';
        echo '<th class="border p-2 text-right">' . __('Delay (min)') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        foreach ($retryStats as $stat) {
            $delay = $notificationGateway->calculateRetryDelay((int)$stat['attempts'], $retryDelayMinutes);
            echo '<tr>';
            echo '<td class="border p-2">' . $stat['attempts'] . '</td>';
            echo '<td class="border p-2 text-right font-bold">' . $stat['count'] . '</td>';
            echo '<td class="border p-2 text-right text-blue-600">' . $stat['pending_count'] . '</td>';
            echo '<td class="border p-2 text-right text-red-600">' . $stat['failed_count'] . '</td>';
            echo '<td class="border p-2 text-right">' . $delay . '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
    } else {
        echo '<div class="bg-gray-50 border rounded p-4 mb-6 text-center text-gray-600">';
        echo __('No retry attempts recorded yet');
        echo '</div>';
    }

    // Notifications Waiting for Retry
    echo '<h3>' . __('Notifications Waiting for Retry') . '</h3>';

    $waitingNotifications = $notificationGateway->selectNotificationsPendingRetry($retryDelayMinutes);

    if (!empty($waitingNotifications)) {
        echo '<table class="w-full mb-6 border-collapse">';
        echo '<thead>';
        echo '<tr class="bg-gray-100">';
        echo '<th class="border p-2 text-left">' . __('ID') . '</th>';
        echo '<th class="border p-2 text-left">' . __('Type') . '</th>';
        echo '<th class="border p-2 text-left">' . __('Recipient') . '</th>';
        echo '<th class="border p-2 text-center">' . __('Attempts') . '</th>';
        echo '<th class="border p-2 text-left">' . __('Last Attempt') . '</th>';
        echo '<th class="border p-2 text-left">' . __('Next Retry') . '</th>';
        echo '<th class="border p-2 text-left">' . __('Error') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        foreach ($waitingNotifications as $notification) {
            echo '<tr>';
            echo '<td class="border p-2">' . $notification['gibbonNotificationQueueID'] . '</td>';
            echo '<td class="border p-2">' . $notification['type'] . '</td>';
            echo '<td class="border p-2">' . Format::name('', $notification['recipientPreferredName'], $notification['recipientSurname'], 'Student', true) . '</td>';
            echo '<td class="border p-2 text-center">';
            echo '<span class="inline-block bg-orange-100 text-orange-800 px-2 py-1 rounded text-sm">';
            echo $notification['attempts'] . ' / ' . $maxRetryAttempts;
            echo '</span>';
            echo '</td>';
            echo '<td class="border p-2">' . Format::dateTime($notification['lastAttemptAt']) . '</td>';
            echo '<td class="border p-2">' . Format::dateTime($notification['nextRetryAt']) . '</td>';
            echo '<td class="border p-2 text-sm text-gray-600">' . htmlspecialchars(substr($notification['errorMessage'] ?? '', 0, 100)) . '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
    } else {
        echo '<div class="bg-gray-50 border rounded p-4 mb-6 text-center text-gray-600">';
        echo __('No notifications currently waiting for retry');
        echo '</div>';
    }

    // Permanently Failed Notifications
    echo '<h3>' . __('Permanently Failed Notifications (Last 20)') . '</h3>';

    $criteria = QueryCriteria::create()
        ->sortBy('timestampCreated', 'DESC')
        ->pageSize(20);

    $failed = $notificationGateway->queryQueue($criteria, 'failed');

    if ($failed->count() > 0) {
        $table = DataTable::createPaginated('failedNotifications', $criteria);

        $table->addColumn('gibbonNotificationQueueID', __('ID'));
        $table->addColumn('type', __('Type'));
        $table->addColumn('recipient', __('Recipient'))
            ->format(function ($notification) {
                return Format::name('', $notification['recipientPreferredName'], $notification['recipientSurname'], 'Student', true);
            });
        $table->addColumn('attempts', __('Attempts'))
            ->format(function ($notification) use ($maxRetryAttempts) {
                return '<span class="inline-block bg-red-100 text-red-800 px-2 py-1 rounded text-sm">' .
                       $notification['attempts'] . ' / ' . $maxRetryAttempts .
                       '</span>';
            });
        $table->addColumn('timestampCreated', __('Created'))
            ->format(Format::using('dateTime', 'timestampCreated'));
        $table->addColumn('lastAttemptAt', __('Last Attempt'))
            ->format(Format::using('dateTime', 'lastAttemptAt'));
        $table->addColumn('errorMessage', __('Error'))
            ->format(function ($notification) {
                return '<span class="text-sm text-gray-600">' .
                       htmlspecialchars(substr($notification['errorMessage'] ?? '', 0, 150)) .
                       '</span>';
            });

        echo $table->render($failed);
    } else {
        echo '<div class="bg-gray-50 border rounded p-4 mb-6 text-center text-gray-600">';
        echo __('No permanently failed notifications');
        echo '</div>';
    }

    // Help Section
    echo '<h3 class="mt-8">' . __('How Retry Mechanism Works') . '</h3>';
    echo '<div class="bg-gray-50 border rounded p-4">';
    echo '<h4 class="font-bold mb-2">' . __('Exponential Backoff Strategy') . '</h4>';
    echo '<p class="mb-4">' . __('When a notification fails to deliver, the system automatically retries with progressively increasing delays to avoid overwhelming external services and allow temporary issues to resolve.') . '</p>';

    echo '<h4 class="font-bold mb-2">' . __('Retry Flow') . '</h4>';
    echo '<ol class="list-decimal list-inside mb-4">';
    echo '<li>' . __('Initial delivery attempt (no delay)') . '</li>';
    echo '<li>' . __('If failed: mark as pending retry, wait {delay} minutes', ['delay' => $retryDelayMinutes]) . '</li>';
    echo '<li>' . __('Retry with exponential backoff until max attempts reached') . '</li>';
    echo '<li>' . __('After max attempts: mark as permanently failed') . '</li>';
    echo '</ol>';

    echo '<p class="text-sm text-gray-600">';
    echo __('For detailed documentation, see: docs/RETRY_MECHANISM.md');
    echo '</p>';
    echo '</div>';
}
