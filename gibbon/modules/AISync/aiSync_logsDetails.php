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

use Gibbon\Services\Format;
use Gibbon\Module\AISync\Domain\AISyncGateway;

// Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/AISync/aiSync_logs.php') == false) {
    // Access denied
    echo "<div class='error'>";
    echo __('You do not have access to this action.');
    echo "</div>";
} else {
    // Proceed!
    $gibbonAISyncLogID = $_GET['gibbonAISyncLogID'] ?? '';

    if (empty($gibbonAISyncLogID)) {
        echo "<div class='error'>";
        echo __('Invalid log ID.');
        echo "</div>";
    } else {
        // Get gateway
        $aiSyncGateway = $container->get(AISyncGateway::class);

        // Get log details
        try {
            $log = $aiSyncGateway->getByID($gibbonAISyncLogID);

            if (empty($log)) {
                echo "<div class='error'>";
                echo __('Log entry not found.');
                echo "</div>";
            } else {
                // Display log details
                echo '<h2>' . __('Webhook Event Details') . '</h2>';

                // Status badge
                $statusColors = [
                    'pending' => 'bg-yellow-100 text-yellow-800 border-yellow-300',
                    'success' => 'bg-green-100 text-green-800 border-green-300',
                    'failed' => 'bg-red-100 text-red-800 border-red-300',
                ];
                $color = $statusColors[$log['status']] ?? 'bg-gray-100 text-gray-800 border-gray-300';

                echo '<div class="mb-4">';
                echo '<span class="inline-block px-3 py-1 rounded border text-sm font-semibold ' . $color . '">';
                echo strtoupper($log['status']);
                echo '</span>';
                echo '</div>';

                // Basic information
                echo '<div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-4">';
                echo '<h3 class="text-lg font-semibold mb-3">' . __('Basic Information') . '</h3>';

                echo '<div class="grid grid-cols-2 gap-3 text-sm">';

                echo '<div>';
                echo '<strong>' . __('Event Type:') . '</strong><br>';
                echo ucwords(str_replace('_', ' ', $log['eventType']));
                echo '</div>';

                echo '<div>';
                echo '<strong>' . __('Entity:') . '</strong><br>';
                echo ucfirst($log['entityType']) . ' #' . $log['entityID'];
                echo '</div>';

                echo '<div>';
                echo '<strong>' . __('Created:') . '</strong><br>';
                echo Format::dateTimeReadable($log['timestampCreated']);
                echo '</div>';

                echo '<div>';
                echo '<strong>' . __('Processed:') . '</strong><br>';
                if ($log['timestampProcessed']) {
                    echo Format::dateTimeReadable($log['timestampProcessed']);

                    // Calculate duration
                    $created = new DateTime($log['timestampCreated']);
                    $processed = new DateTime($log['timestampProcessed']);
                    $duration = $processed->diff($created);

                    $durationText = '';
                    if ($duration->days > 0) $durationText .= $duration->days . 'd ';
                    if ($duration->h > 0) $durationText .= $duration->h . 'h ';
                    if ($duration->i > 0) $durationText .= $duration->i . 'm ';
                    $durationText .= $duration->s . 's';

                    echo '<br><span class="text-xs text-gray-600">Duration: ' . trim($durationText) . '</span>';
                } else {
                    echo '<span class="text-gray-400">Not yet processed</span>';
                }
                echo '</div>';

                echo '<div>';
                echo '<strong>' . __('Retry Count:') . '</strong><br>';
                if ($log['retryCount'] > 0) {
                    echo '<span class="text-orange-600 font-semibold">' . $log['retryCount'] . '</span>';
                } else {
                    echo $log['retryCount'];
                }
                echo '</div>';

                echo '<div>';
                echo '<strong>' . __('Log ID:') . '</strong><br>';
                echo $log['gibbonAISyncLogID'];
                echo '</div>';

                echo '</div>';
                echo '</div>';

                // Error message (if any)
                if (!empty($log['errorMessage'])) {
                    echo '<div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">';
                    echo '<h3 class="text-lg font-semibold text-red-800 mb-2">' . __('Error Message') . '</h3>';
                    echo '<pre class="text-sm text-red-800 whitespace-pre-wrap overflow-x-auto">';
                    echo htmlspecialchars($log['errorMessage']);
                    echo '</pre>';
                    echo '</div>';
                }

                // Payload
                echo '<div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">';
                echo '<h3 class="text-lg font-semibold text-blue-800 mb-2">' . __('Request Payload') . '</h3>';

                if (!empty($log['payload'])) {
                    $payload = json_decode($log['payload'], true);

                    if (json_last_error() === JSON_ERROR_NONE) {
                        echo '<pre class="text-xs text-blue-900 whitespace-pre-wrap overflow-x-auto bg-white p-3 rounded border border-blue-300">';
                        echo htmlspecialchars(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                        echo '</pre>';
                    } else {
                        echo '<pre class="text-xs text-blue-900 whitespace-pre-wrap overflow-x-auto bg-white p-3 rounded border border-blue-300">';
                        echo htmlspecialchars($log['payload']);
                        echo '</pre>';
                    }
                } else {
                    echo '<p class="text-sm text-blue-800">' . __('No payload data') . '</p>';
                }
                echo '</div>';

                // Response
                echo '<div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">';
                echo '<h3 class="text-lg font-semibold text-green-800 mb-2">' . __('Response Data') . '</h3>';

                if (!empty($log['response'])) {
                    // Try to parse as JSON first
                    $response = json_decode($log['response'], true);

                    if (json_last_error() === JSON_ERROR_NONE) {
                        echo '<pre class="text-xs text-green-900 whitespace-pre-wrap overflow-x-auto bg-white p-3 rounded border border-green-300">';
                        echo htmlspecialchars(json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                        echo '</pre>';
                    } else {
                        echo '<pre class="text-xs text-green-900 whitespace-pre-wrap overflow-x-auto bg-white p-3 rounded border border-green-300">';
                        echo htmlspecialchars($log['response']);
                        echo '</pre>';
                    }
                } else {
                    echo '<p class="text-sm text-green-800">' . __('No response data yet') . '</p>';
                }
                echo '</div>';

                // Actions
                echo '<div class="mt-4 flex justify-end space-x-2">';

                // Retry button (if failed)
                if ($log['status'] === 'failed') {
                    echo '<a href="' . $session->get('absoluteURL') . '/modules/AISync/aiSync_retry.php?gibbonAISyncLogID=' . $log['gibbonAISyncLogID'] . '" class="button">';
                    echo __('Retry Sync');
                    echo '</a>';
                }

                // View related entity (if possible)
                $entityLinks = [
                    'activity' => '/modules/CareTracking/activities_view.php?id=',
                    'meal' => '/modules/CareTracking/meals_view.php?id=',
                    'nap' => '/modules/CareTracking/naps_view.php?id=',
                    'attendance' => '/modules/CareTracking/attendance_view.php?id=',
                    'photo' => '/modules/PhotoManagement/photos_view.php?id=',
                ];

                if (isset($entityLinks[$log['entityType']])) {
                    echo '<a href="' . $session->get('absoluteURL') . $entityLinks[$log['entityType']] . $log['entityID'] . '" class="button" target="_blank">';
                    echo __('View') . ' ' . ucfirst($log['entityType']);
                    echo '</a>';
                }

                echo '</div>';

                // Technical details
                echo '<div class="mt-6 bg-gray-100 border border-gray-300 rounded-lg p-4">';
                echo '<h3 class="text-sm font-semibold text-gray-700 mb-2">' . __('Technical Details') . '</h3>';
                echo '<div class="text-xs text-gray-600 space-y-1">';
                echo '<div><strong>Database ID:</strong> ' . $log['gibbonAISyncLogID'] . '</div>';
                echo '<div><strong>Timestamp Created (UTC):</strong> ' . $log['timestampCreated'] . '</div>';
                if ($log['timestampProcessed']) {
                    echo '<div><strong>Timestamp Processed (UTC):</strong> ' . $log['timestampProcessed'] . '</div>';
                }
                echo '<div><strong>JSON Payload Size:</strong> ' . (strlen($log['payload'] ?? '') ? number_format(strlen($log['payload'])) . ' bytes' : 'N/A') . '</div>';
                echo '<div><strong>JSON Response Size:</strong> ' . (strlen($log['response'] ?? '') ? number_format(strlen($log['response'])) . ' bytes' : 'N/A') . '</div>';
                echo '</div>';
                echo '</div>';
            }
        } catch (Exception $e) {
            echo "<div class='error'>";
            echo __('An error occurred while loading the log details.') . '<br>';
            echo htmlspecialchars($e->getMessage());
            echo "</div>";
        }
    }
}
