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
use Gibbon\Module\NotificationEngine\Domain\DeliveryLogGateway;
use Gibbon\Domain\QueryCriteria;

// Module includes
$page->breadcrumbs->add(__('Delivery Logs'));

if (!isActionAccessible($guid, $connection2, '/modules/NotificationEngine/delivery_logs.php')) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get the delivery log gateway
    $deliveryLogGateway = $container->get(DeliveryLogGateway::class);

    // FILTER FORM
    $form = Form::create('filters', $session->get('absoluteURL') . '/index.php', 'get');
    $form->setTitle(__('Filter'));
    $form->setClass('noIntBorder fullWidth');

    $form->addHiddenValue('q', '/modules/' . $session->get('module') . '/delivery_logs.php');

    $row = $form->addRow();
        $row->addLabel('channel', __('Channel'));
        $row->addSelect('channel')
            ->fromArray([
                '' => __('All'),
                'email' => __('Email'),
                'push' => __('Push'),
            ])
            ->selected($_GET['channel'] ?? '');

    $row = $form->addRow();
        $row->addLabel('status', __('Status'));
        $row->addSelect('status')
            ->fromArray([
                '' => __('All'),
                'success' => __('Success'),
                'failed' => __('Failed'),
                'skipped' => __('Skipped'),
            ])
            ->selected($_GET['status'] ?? '');

    $row = $form->addRow();
        $row->addLabel('dateFrom', __('Date From'));
        $row->addDate('dateFrom')->setValue($_GET['dateFrom'] ?? '');

    $row = $form->addRow();
        $row->addLabel('dateTo', __('Date To'));
        $row->addDate('dateTo')->setValue($_GET['dateTo'] ?? '');

    $row = $form->addRow();
        $row->addSearchSubmit($session, __('Clear Filters'));

    echo $form->getOutput();

    // QUERY
    $criteria = $container->get(QueryCriteria::class)
        ->pageSize(50)
        ->fromPOST();

    $channel = $_GET['channel'] ?? null;
    $status = $_GET['status'] ?? null;
    $dateFrom = $_GET['dateFrom'] ?? null;
    $dateTo = $_GET['dateTo'] ?? null;

    // Build custom query to include date filtering
    $logs = $deliveryLogGateway->queryDeliveryLogs($criteria, null, $channel, $status);

    // TABLE
    $table = DataTable::createPaginated('deliveryLogs', $criteria);
    $table->setTitle(__('Delivery Logs'));

    $table->addColumn('timestampCreated', __('Date/Time'))
        ->format(function ($row) {
            return Format::dateTimeReadable($row['timestampCreated']);
        });

    $table->addColumn('channel', __('Channel'))
        ->format(function ($row) {
            $badges = [
                'email' => '<span class="badge" style="background-color: #4A90A4;">Email</span>',
                'push' => '<span class="badge" style="background-color: #5C6AC4;">Push</span>',
            ];
            return $badges[$row['channel']] ?? $row['channel'];
        });

    $table->addColumn('status', __('Status'))
        ->format(function ($row) {
            $badges = [
                'success' => '<span class="badge" style="background-color: #2ecc71;">Success</span>',
                'failed' => '<span class="badge" style="background-color: #e74c3c;">Failed</span>',
                'skipped' => '<span class="badge" style="background-color: #95a5a6;">Skipped</span>',
            ];
            return $badges[$row['status']] ?? $row['status'];
        });

    $table->addColumn('notification', __('Notification'))
        ->format(function ($row) {
            $output = '<strong>' . htmlspecialchars($row['notificationTitle']) . '</strong><br>';
            $output .= '<small>Type: ' . htmlspecialchars($row['notificationType']) . '</small>';
            return $output;
        });

    $table->addColumn('recipient', __('Recipient'))
        ->format(function ($row) {
            $output = htmlspecialchars($row['recipientPreferredName'] . ' ' . $row['recipientSurname']) . '<br>';
            if (!empty($row['recipientEmail'])) {
                $output .= '<small>' . htmlspecialchars($row['recipientEmail']) . '</small>';
            }
            if (!empty($row['recipientIdentifier'])) {
                $output .= '<br><small>ID: ' . htmlspecialchars($row['recipientIdentifier']) . '</small>';
            }
            return $output;
        });

    $table->addColumn('attemptNumber', __('Attempt'))
        ->width('5%');

    $table->addColumn('deliveryTimeMs', __('Time (ms)'))
        ->width('8%')
        ->format(function ($row) {
            if (empty($row['deliveryTimeMs'])) {
                return '-';
            }
            $ms = $row['deliveryTimeMs'];
            if ($ms < 100) {
                $color = '#2ecc71'; // Green - fast
            } elseif ($ms < 500) {
                $color = '#f39c12'; // Orange - moderate
            } else {
                $color = '#e74c3c'; // Red - slow
            }
            return '<span style="color: ' . $color . '; font-weight: bold;">' . $ms . ' ms</span>';
        });

    $table->addColumn('error', __('Error'))
        ->format(function ($row) {
            if ($row['status'] === 'success') {
                return '-';
            }
            $output = '';
            if (!empty($row['errorCode'])) {
                $output .= '<strong>' . htmlspecialchars($row['errorCode']) . '</strong><br>';
            }
            if (!empty($row['errorMessage'])) {
                $output .= '<small>' . htmlspecialchars($row['errorMessage']) . '</small>';
            }
            return $output ?: '-';
        });

    $table->addActionColumn()
        ->addParam('gibbonNotificationDeliveryLogID')
        ->format(function ($row, $actions) use ($session) {
            if (!empty($row['responseData'])) {
                $actions->addAction('view', __('View Details'))
                    ->setURL('/modules/NotificationEngine/delivery_log_view.php')
                    ->addParam('gibbonNotificationDeliveryLogID', $row['gibbonNotificationDeliveryLogID'])
                    ->setIcon('page_right')
                    ->modalWindow();
            }
        });

    echo $table->render($logs);

    // STATISTICS SECTION
    echo '<h2>' . __('Statistics') . '</h2>';

    // Get success rates
    $successRates = $deliveryLogGateway->getSuccessRates($dateFrom, $dateTo);

    if (!empty($successRates)) {
        echo '<div class="linkTop" style="margin-bottom: 20px;">';

        foreach ($successRates as $rate) {
            $channel = $rate['channel'];
            $total = $rate['total'];
            $successful = $rate['successful'];
            $failed = $rate['failed'];
            $skipped = $rate['skipped'];
            $successRate = $rate['successRate'];

            echo '<div style="padding: 15px; margin-bottom: 10px; border: 1px solid #ddd; border-radius: 5px;">';
            echo '<h3>' . ucfirst($channel) . ' Delivery Statistics</h3>';
            echo '<table cellspacing="0" style="width: 100%">';
            echo '<tr><td><strong>Total Deliveries:</strong></td><td>' . $total . '</td></tr>';
            echo '<tr><td><strong>Successful:</strong></td><td style="color: #2ecc71;">' . $successful . ' (' . round(($successful / $total) * 100, 1) . '%)</td></tr>';
            echo '<tr><td><strong>Failed:</strong></td><td style="color: #e74c3c;">' . $failed . ' (' . round(($failed / $total) * 100, 1) . '%)</td></tr>';
            echo '<tr><td><strong>Skipped:</strong></td><td style="color: #95a5a6;">' . $skipped . ' (' . round(($skipped / $total) * 100, 1) . '%)</td></tr>';
            echo '<tr><td><strong>Success Rate:</strong></td><td><strong>' . $successRate . '%</strong></td></tr>';
            echo '</table>';
            echo '</div>';
        }

        echo '</div>';
    }

    // Get average delivery times
    $avgTimes = $deliveryLogGateway->getAverageDeliveryTimes($dateFrom, $dateTo);

    if (!empty($avgTimes)) {
        echo '<div class="linkTop" style="margin-bottom: 20px;">';
        echo '<h3>' . __('Average Delivery Times') . '</h3>';
        echo '<table cellspacing="0" style="width: 100%; border-collapse: collapse;">';
        echo '<thead>';
        echo '<tr><th style="text-align: left;">Channel</th><th>Average</th><th>Min</th><th>Max</th><th>Samples</th></tr>';
        echo '</thead>';
        echo '<tbody>';

        foreach ($avgTimes as $time) {
            echo '<tr>';
            echo '<td><strong>' . ucfirst($time['channel']) . '</strong></td>';
            echo '<td>' . round($time['avgMs']) . ' ms</td>';
            echo '<td>' . round($time['minMs']) . ' ms</td>';
            echo '<td>' . round($time['maxMs']) . ' ms</td>';
            echo '<td>' . $time['sampleSize'] . '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    }

    // Get top errors
    $topErrors = $deliveryLogGateway->getTopErrors(5);

    if (!empty($topErrors)) {
        echo '<div class="linkTop" style="margin-bottom: 20px;">';
        echo '<h3>' . __('Most Common Errors') . '</h3>';
        echo '<table cellspacing="0" style="width: 100%; border-collapse: collapse;">';
        echo '<thead>';
        echo '<tr><th style="text-align: left;">Error Code</th><th>Channel</th><th>Count</th><th>Last Occurrence</th></tr>';
        echo '</thead>';
        echo '<tbody>';

        foreach ($topErrors as $error) {
            echo '<tr>';
            echo '<td><strong>' . htmlspecialchars($error['errorCode']) . '</strong></td>';
            echo '<td>' . ucfirst($error['channel']) . '</td>';
            echo '<td>' . $error['count'] . '</td>';
            echo '<td>' . Format::dateTimeReadable($error['lastOccurrence']) . '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    }
}
