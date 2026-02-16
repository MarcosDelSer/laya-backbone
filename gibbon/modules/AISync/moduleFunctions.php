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

/**
 * AI Sync Module Functions
 *
 * Helper functions for the AI Sync module.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */

/**
 * Format event type for display.
 *
 * @param string $eventType Event type code
 * @return string Formatted event type
 */
function formatAISyncEventType($eventType)
{
    return ucwords(str_replace('_', ' ', $eventType));
}

/**
 * Get status badge HTML.
 *
 * @param string $status Status: pending, success, or failed
 * @return string HTML badge
 */
function getAISyncStatusBadge($status)
{
    $statusColors = [
        'pending' => 'bg-yellow-100 text-yellow-800 border-yellow-300',
        'success' => 'bg-green-100 text-green-800 border-green-300',
        'failed' => 'bg-red-100 text-red-800 border-red-300',
    ];

    $color = $statusColors[$status] ?? 'bg-gray-100 text-gray-800 border-gray-300';

    return '<span class="inline-block px-2 py-1 rounded border text-xs font-semibold ' . $color . '">'
           . strtoupper($status)
           . '</span>';
}

/**
 * Calculate sync duration in human-readable format.
 *
 * @param string $timestampCreated Created timestamp
 * @param string|null $timestampProcessed Processed timestamp
 * @return string Duration string
 */
function getAISyncDuration($timestampCreated, $timestampProcessed = null)
{
    if (!$timestampProcessed) {
        return 'Not yet processed';
    }

    try {
        $created = new DateTime($timestampCreated);
        $processed = new DateTime($timestampProcessed);
        $duration = $processed->diff($created);

        $parts = [];
        if ($duration->days > 0) {
            $parts[] = $duration->days . 'd';
        }
        if ($duration->h > 0) {
            $parts[] = $duration->h . 'h';
        }
        if ($duration->i > 0) {
            $parts[] = $duration->i . 'm';
        }
        $parts[] = $duration->s . 's';

        return implode(' ', $parts);
    } catch (Exception $e) {
        return 'Unknown';
    }
}

/**
 * Format JSON payload for display.
 *
 * @param string|null $jsonString JSON string
 * @return string Formatted JSON or message
 */
function formatAISyncJSON($jsonString)
{
    if (empty($jsonString)) {
        return 'No data';
    }

    $decoded = json_decode($jsonString, true);

    if (json_last_error() === JSON_ERROR_NONE) {
        return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    return $jsonString;
}

/**
 * Get entity type icon.
 *
 * @param string $entityType Entity type
 * @return string Icon name
 */
function getAISyncEntityIcon($entityType)
{
    $icons = [
        'activity' => 'planner',
        'meal' => 'attendance',
        'nap' => 'sleep',
        'attendance' => 'attendance',
        'photo' => 'image',
    ];

    return $icons[$entityType] ?? 'page_right';
}
