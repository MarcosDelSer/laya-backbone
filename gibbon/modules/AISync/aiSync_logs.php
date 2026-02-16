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
use Gibbon\Domain\QueryCriteria;
use Gibbon\Module\AISync\Domain\AISyncGateway;

if (isActionAccessible($guid, $connection2, '/modules/AISync/aiSync_logs.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs->add(__('View Sync Logs'));

    // Get gateway
    $aiSyncGateway = $container->get(AISyncGateway::class);

    // Filter form
    $form = Form::create('filters', $session->get('absoluteURL') . '/index.php', 'get');
    $form->setTitle(__('Filter Logs'));
    $form->setClass('noIntBorder fullWidth');

    $form->addHiddenValue('q', '/modules/AISync/aiSync_logs.php');

    $row = $form->addRow();
        $row->addLabel('status', __('Status'));
        $row->addSelect('status')
            ->fromArray([
                '' => __('All'),
                'pending' => __('Pending'),
                'success' => __('Success'),
                'failed' => __('Failed'),
            ])
            ->selected($_GET['status'] ?? '');

    $row = $form->addRow();
        $row->addLabel('eventType', __('Event Type'));
        $row->addSelect('eventType')
            ->fromArray([
                '' => __('All'),
                'care_activity_created' => __('Care Activity Created'),
                'care_activity_updated' => __('Care Activity Updated'),
                'care_activity_deleted' => __('Care Activity Deleted'),
                'meal_logged' => __('Meal Logged'),
                'meal_updated' => __('Meal Updated'),
                'meal_deleted' => __('Meal Deleted'),
                'nap_logged' => __('Nap Logged'),
                'nap_updated' => __('Nap Updated'),
                'nap_deleted' => __('Nap Deleted'),
                'child_checked_in' => __('Child Checked In'),
                'child_checked_out' => __('Child Checked Out'),
                'photo_uploaded' => __('Photo Uploaded'),
                'photo_tagged' => __('Photo Tagged'),
                'photo_deleted' => __('Photo Deleted'),
            ])
            ->selected($_GET['eventType'] ?? '');

    $row = $form->addRow();
        $row->addLabel('entityType', __('Entity Type'));
        $row->addSelect('entityType')
            ->fromArray([
                '' => __('All'),
                'activity' => __('Activity'),
                'meal' => __('Meal'),
                'nap' => __('Nap'),
                'attendance' => __('Attendance'),
                'photo' => __('Photo'),
            ])
            ->selected($_GET['entityType'] ?? '');

    $row = $form->addRow();
        $row->addLabel('dateFrom', __('Date From'));
        $row->addDate('dateFrom')
            ->setValue($_GET['dateFrom'] ?? date('Y-m-d', strtotime('-7 days')));

    $row = $form->addRow();
        $row->addLabel('dateTo', __('Date To'));
        $row->addDate('dateTo')
            ->setValue($_GET['dateTo'] ?? date('Y-m-d'));

    $row = $form->addRow();
        $row->addSearchSubmit($session);

    echo $form->getOutput();

    // Build query criteria
    $criteria = $criteria ?? QueryCriteria::fromPOST('logs');

    // Apply filters from GET parameters
    if (!empty($_GET['status'])) {
        $criteria->addFilter('status', $_GET['status']);
    }
    if (!empty($_GET['eventType'])) {
        $criteria->addFilter('eventType', $_GET['eventType']);
    }
    if (!empty($_GET['entityType'])) {
        $criteria->addFilter('entityType', $_GET['entityType']);
    }
    if (!empty($_GET['dateFrom'])) {
        $criteria->addFilter('dateFrom', $_GET['dateFrom']);
    }
    if (!empty($_GET['dateTo'])) {
        $criteria->addFilter('dateTo', $_GET['dateTo']);
    }

    // Query sync logs
    $logs = $aiSyncGateway->querySyncLogs($criteria);

    // Render table
    $table = DataTable::createPaginated('logs', $criteria);
    $table->setTitle(__('Webhook Event Logs'));
    $table->setDescription(__('Complete audit trail of all webhook synchronization events.'));

    $table->addHeaderAction('refresh', __('Refresh'))
        ->setURL('/modules/AISync/aiSync_logs.php')
        ->displayLabel();

    $table->addHeaderAction('health', __('View Health'))
        ->setURL('/modules/AISync/aiSync_health.php')
        ->displayLabel();

    // Define columns
    $table->addColumn('timestampCreated', __('Timestamp'))
        ->format(function ($row) {
            return Format::dateTimeReadable($row['timestampCreated']);
        });

    $table->addColumn('eventType', __('Event Type'))
        ->format(function ($row) {
            // Format event type to be more readable
            return ucwords(str_replace('_', ' ', $row['eventType']));
        });

    $table->addColumn('entityType', __('Entity'))
        ->format(function ($row) {
            return ucfirst($row['entityType']) . ' #' . $row['entityID'];
        });

    $table->addColumn('status', __('Status'))
        ->format(function ($row) {
            $statusColors = [
                'pending' => 'bg-yellow-100 text-yellow-800',
                'success' => 'bg-green-100 text-green-800',
                'failed' => 'bg-red-100 text-red-800',
            ];
            $color = $statusColors[$row['status']] ?? 'bg-gray-100 text-gray-800';
            return '<span class="inline-block px-2 py-1 rounded text-xs font-semibold ' . $color . '">'
                   . ucfirst($row['status'])
                   . '</span>';
        });

    $table->addColumn('retryCount', __('Retries'))
        ->format(function ($row) {
            if ($row['retryCount'] > 0) {
                return '<span class="text-orange-600 font-semibold">' . $row['retryCount'] . '</span>';
            }
            return $row['retryCount'];
        });

    $table->addColumn('timestampProcessed', __('Processed'))
        ->format(function ($row) {
            if ($row['timestampProcessed']) {
                $created = new DateTime($row['timestampCreated']);
                $processed = new DateTime($row['timestampProcessed']);
                $duration = $processed->diff($created);

                $durationText = '';
                if ($duration->days > 0) {
                    $durationText = $duration->days . 'd ';
                }
                if ($duration->h > 0) {
                    $durationText .= $duration->h . 'h ';
                }
                if ($duration->i > 0) {
                    $durationText .= $duration->i . 'm ';
                }
                $durationText .= $duration->s . 's';

                return Format::dateTimeReadable($row['timestampProcessed'])
                       . '<br><span class="text-xs text-gray-600">(' . trim($durationText) . ')</span>';
            }
            return '<span class="text-gray-400 text-xs">-</span>';
        });

    $table->addColumn('errorMessage', __('Error'))
        ->format(function ($row) {
            if (!empty($row['errorMessage'])) {
                $truncated = mb_substr($row['errorMessage'], 0, 50);
                if (mb_strlen($row['errorMessage']) > 50) {
                    $truncated .= '...';
                }
                return '<span class="text-red-600 text-xs" title="' . htmlspecialchars($row['errorMessage']) . '">'
                       . htmlspecialchars($truncated)
                       . '</span>';
            }
            return '<span class="text-gray-400 text-xs">-</span>';
        });

    // Add action column with details modal
    $table->addActionColumn()
        ->addParam('gibbonAISyncLogID')
        ->format(function ($row, $actions) use ($session) {
            $actions->addAction('view', __('View Details'))
                ->setIcon('page_right')
                ->directLink()
                ->modalWindow(1200, 800)
                ->setURL('/modules/AISync/aiSync_logsDetails.php?gibbonAISyncLogID=' . $row['gibbonAISyncLogID']);
        });

    echo $table->render($logs);

    // Summary statistics
    echo '<div class="mt-6 bg-gray-50 border border-gray-200 rounded-lg p-4">';
    echo '<h3 class="text-lg font-semibold mb-3">' . __('Quick Statistics') . '</h3>';

    $stats = $aiSyncGateway->getSyncStatistics(
        $_GET['dateFrom'] ?? null,
        $_GET['dateTo'] ?? null
    );

    echo '<div class="grid grid-cols-2 md:grid-cols-6 gap-3">';

    // Total
    echo '<div class="text-center">';
    echo '<div class="text-2xl font-bold text-gray-600">' . number_format($stats['totalSyncs']) . '</div>';
    echo '<div class="text-xs text-gray-600">' . __('Total') . '</div>';
    echo '</div>';

    // Pending
    echo '<div class="text-center">';
    echo '<div class="text-2xl font-bold text-yellow-600">' . number_format($stats['pendingSyncs']) . '</div>';
    echo '<div class="text-xs text-gray-600">' . __('Pending') . '</div>';
    echo '</div>';

    // Success
    echo '<div class="text-center">';
    echo '<div class="text-2xl font-bold text-green-600">' . number_format($stats['successfulSyncs']) . '</div>';
    echo '<div class="text-xs text-gray-600">' . __('Success') . '</div>';
    echo '</div>';

    // Failed
    echo '<div class="text-center">';
    echo '<div class="text-2xl font-bold text-red-600">' . number_format($stats['failedSyncs']) . '</div>';
    echo '<div class="text-xs text-gray-600">' . __('Failed') . '</div>';
    echo '</div>';

    // Avg Retries
    echo '<div class="text-center">';
    echo '<div class="text-2xl font-bold text-orange-600">' . number_format($stats['avgRetryCount'], 2) . '</div>';
    echo '<div class="text-xs text-gray-600">' . __('Avg Retries') . '</div>';
    echo '</div>';

    // Max Retries
    echo '<div class="text-center">';
    echo '<div class="text-2xl font-bold text-purple-600">' . number_format($stats['maxRetryCount']) . '</div>';
    echo '<div class="text-xs text-gray-600">' . __('Max Retries') . '</div>';
    echo '</div>';

    echo '</div>';
    echo '</div>';

    // Help section
    echo '<div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">';
    echo '<h3 class="text-lg font-semibold text-blue-800 mb-2">' . __('About Webhook Event Logs') . '</h3>';
    echo '<p class="text-sm text-blue-800 mb-3">' . __('This page shows a complete audit trail of all webhook synchronization events between Gibbon and the AI service. Each log entry represents an attempt to sync data and includes the full payload, response, and any errors encountered.') . '</p>';

    echo '<div class="text-sm text-blue-800">';
    echo '<strong>' . __('Status Meanings:') . '</strong>';
    echo '<ul class="list-disc list-inside ml-2 mt-1">';
    echo '<li><strong>Pending:</strong> ' . __('Sync is queued but not yet processed') . '</li>';
    echo '<li><strong>Success:</strong> ' . __('Sync completed successfully') . '</li>';
    echo '<li><strong>Failed:</strong> ' . __('Sync failed and may be retried') . '</li>';
    echo '</ul>';
    echo '</div>';

    echo '<div class="mt-3 text-sm text-blue-800">';
    echo '<strong>' . __('Troubleshooting Tips:') . '</strong>';
    echo '<ul class="list-disc list-inside ml-2 mt-1">';
    echo '<li>' . __('Click "View Details" to see full payload and response data') . '</li>';
    echo '<li>' . __('Use filters to narrow down specific issues or time periods') . '</li>';
    echo '<li>' . __('Check the "Webhook Health" page for overall system status') . '</li>';
    echo '<li>' . __('Failed syncs are automatically retried by the cron processor') . '</li>';
    echo '</ul>';
    echo '</div>';
    echo '</div>';
}
