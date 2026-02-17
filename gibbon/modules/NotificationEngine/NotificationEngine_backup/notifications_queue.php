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
use Gibbon\Tables\DataTable;
use Gibbon\Services\Format;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Module\NotificationEngine\Domain\NotificationGateway;

if (isActionAccessible($guid, $connection2, '/modules/NotificationEngine/notifications_queue.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs->add(__('Notification Queue'));

    // Get gateway
    $notificationGateway = $container->get(NotificationGateway::class);

    // Get queue statistics
    $stats = $notificationGateway->getQueueStatistics();

    // Display statistics
    echo '<div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">';

    // Pending
    echo '<div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 text-center">';
    echo '<div class="text-3xl font-bold text-yellow-600">' . $stats['pending'] . '</div>';
    echo '<div class="text-sm text-yellow-700">' . __('Pending') . '</div>';
    echo '</div>';

    // Processing
    echo '<div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-center">';
    echo '<div class="text-3xl font-bold text-blue-600">' . $stats['processing'] . '</div>';
    echo '<div class="text-sm text-blue-700">' . __('Processing') . '</div>';
    echo '</div>';

    // Sent
    echo '<div class="bg-green-50 border border-green-200 rounded-lg p-4 text-center">';
    echo '<div class="text-3xl font-bold text-green-600">' . $stats['sent'] . '</div>';
    echo '<div class="text-sm text-green-700">' . __('Sent') . '</div>';
    echo '</div>';

    // Failed
    echo '<div class="bg-red-50 border border-red-200 rounded-lg p-4 text-center">';
    echo '<div class="text-3xl font-bold text-red-600">' . $stats['failed'] . '</div>';
    echo '<div class="text-sm text-red-700">' . __('Failed') . '</div>';
    echo '</div>';

    // Total
    echo '<div class="bg-gray-50 border border-gray-200 rounded-lg p-4 text-center">';
    echo '<div class="text-3xl font-bold text-gray-600">' . $stats['total'] . '</div>';
    echo '<div class="text-sm text-gray-700">' . __('Total') . '</div>';
    echo '</div>';

    echo '</div>';

    // Filter form
    $form = Form::create('filter', $session->get('absoluteURL') . '/index.php', 'get');
    $form->setTitle(__('Filter'));
    $form->setClass('noIntBorder fullWidth');

    $form->addHiddenValue('q', '/modules/NotificationEngine/notifications_queue.php');

    $row = $form->addRow();
        $row->addLabel('status', __('Status'));
        $row->addSelect('status')
            ->fromArray([
                '' => __('All'),
                'pending' => __('Pending'),
                'processing' => __('Processing'),
                'sent' => __('Sent'),
                'failed' => __('Failed'),
            ])
            ->selected($_GET['status'] ?? '');

    $row = $form->addRow();
        $row->addLabel('type', __('Type'));
        $row->addTextField('type')
            ->setValue($_GET['type'] ?? '')
            ->placeholder(__('Notification type...'))
            ->maxLength(50);

    $row = $form->addRow();
        $row->addSearchSubmit($session, __('Clear Filters'));

    echo $form->getOutput();

    // Build query criteria
    $criteria = $notificationGateway->newQueryCriteria(true)
        ->sortBy(['timestampCreated'], 'DESC')
        ->fromPOST();

    // Get filtered status
    $statusFilter = $_GET['status'] ?? null;

    // Get queue
    $queue = $notificationGateway->queryQueue($criteria, $statusFilter);

    // Create data table
    $table = DataTable::createPaginated('notificationQueue', $criteria);
    $table->setTitle(__('Notification Queue'));

    // Status column
    $table->addColumn('status', __('Status'))
        ->width('10%')
        ->format(function ($row) {
            $statusClasses = [
                'pending' => 'tag warning',
                'processing' => 'tag message',
                'sent' => 'tag success',
                'failed' => 'tag error',
            ];
            $class = $statusClasses[$row['status']] ?? 'tag dull';
            return '<span class="' . $class . '">' . ucfirst($row['status']) . '</span>';
        });

    // Type column
    $table->addColumn('type', __('Type'))
        ->width('10%')
        ->format(function ($row) {
            return '<span class="tag dull">' . htmlspecialchars($row['type']) . '</span>';
        });

    // Recipient column
    $table->addColumn('recipient', __('Recipient'))
        ->width('15%')
        ->format(function ($row) {
            $name = Format::name('', $row['recipientPreferredName'] ?? '', $row['recipientSurname'] ?? '', 'Staff');
            $email = $row['recipientEmail'] ?? '';
            return $name . '<br><span class="text-xs text-gray-500">' . htmlspecialchars($email) . '</span>';
        });

    // Title column
    $table->addColumn('title', __('Title'))
        ->width('20%')
        ->format(function ($row) {
            return htmlspecialchars($row['title']);
        });

    // Channel column
    $table->addColumn('channel', __('Channel'))
        ->width('8%')
        ->format(function ($row) {
            $channelIcons = [
                'email' => 'envelope',
                'push' => 'mobile',
                'both' => 'bell',
            ];
            return '<span class="tag dull">' . ucfirst($row['channel']) . '</span>';
        });

    // Attempts column
    $table->addColumn('attempts', __('Attempts'))
        ->width('8%')
        ->format(function ($row) {
            $maxAttempts = 3;
            $attempts = (int) $row['attempts'];
            $class = $attempts >= $maxAttempts ? 'tag error' : 'tag dull';
            return '<span class="' . $class . '">' . $attempts . '/' . $maxAttempts . '</span>';
        });

    // Created column
    $table->addColumn('timestampCreated', __('Created'))
        ->width('12%')
        ->format(function ($row) {
            return Format::dateTime($row['timestampCreated']);
        });

    // Sent At column
    $table->addColumn('sentAt', __('Sent At'))
        ->width('12%')
        ->format(function ($row) {
            if (!empty($row['sentAt'])) {
                return Format::dateTime($row['sentAt']);
            }
            return '-';
        });

    // Error column
    $table->addColumn('errorMessage', __('Error'))
        ->width('15%')
        ->format(function ($row) {
            if (!empty($row['errorMessage'])) {
                $error = htmlspecialchars($row['errorMessage']);
                // Truncate long errors
                if (strlen($error) > 50) {
                    return '<span title="' . $error . '">' . substr($error, 0, 47) . '...</span>';
                }
                return '<span class="text-red-600">' . $error . '</span>';
            }
            return '-';
        });

    // Actions column
    $table->addActionColumn()
        ->addParam('gibbonNotificationQueueID')
        ->format(function ($row, $actions) {
            if ($row['status'] === 'failed' || $row['status'] === 'pending') {
                $actions->addAction('retry', __('Retry'))
                    ->setURL('/modules/NotificationEngine/notifications_queue_retryProcess.php')
                    ->setIcon('refresh')
                    ->directLink()
                    ->addConfirmation(__('Are you sure you want to retry this notification?'));
            }

            $actions->addAction('view', __('View Details'))
                ->setURL('/modules/NotificationEngine/notifications_queue_view.php')
                ->setIcon('search');

            $actions->addAction('delete', __('Delete'))
                ->setURL('/modules/NotificationEngine/notifications_queue_deleteProcess.php')
                ->setIcon('garbage')
                ->directLink()
                ->addConfirmation(__('Are you sure you want to delete this notification?'));
        });

    echo $table->render($queue);

    // CLI instructions
    echo '<div class="message">';
    echo '<h4>' . __('Queue Processing') . '</h4>';
    echo '<p>' . __('The notification queue is processed automatically via the CLI processor. To manually process the queue, run:') . '</p>';
    echo '<pre class="bg-gray-100 p-3 rounded text-sm">php modules/NotificationEngine/cli/processQueue.php</pre>';
    echo '<p>' . __('For production, set up a cron job to run this command periodically (recommended: every minute).') . '</p>';
    echo '<pre class="bg-gray-100 p-3 rounded text-sm">* * * * * php /path/to/gibbon/modules/NotificationEngine/cli/processQueue.php</pre>';
    echo '</div>';
}
