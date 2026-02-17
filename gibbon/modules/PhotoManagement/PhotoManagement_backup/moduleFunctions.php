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

/**
 * Photo Management Module Functions
 *
 * Helper functions for the Photo Management module.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */

/**
 * Format file size for display.
 *
 * @param int $bytes File size in bytes
 * @param int $precision Decimal precision
 * @return string Formatted file size
 */
function formatPhotoFileSize($bytes, $precision = 2)
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);

    return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * Validate image file type.
 *
 * @param string $mimeType MIME type of the file
 * @param string $allowedTypes Comma-separated list of allowed extensions
 * @return bool True if valid
 */
function isValidPhotoType($mimeType, $allowedTypes = 'jpg,jpeg,png,gif')
{
    $mimeMap = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/gif' => ['gif'],
        'image/webp' => ['webp'],
    ];

    $allowedArray = array_map('trim', explode(',', strtolower($allowedTypes)));

    if (!isset($mimeMap[$mimeType])) {
        return false;
    }

    foreach ($mimeMap[$mimeType] as $ext) {
        if (in_array($ext, $allowedArray)) {
            return true;
        }
    }

    return false;
}

/**
 * Generate a thumbnail path for a photo.
 *
 * @param string $filePath Original file path
 * @param string $suffix Thumbnail suffix (e.g., '_thumb', '_240')
 * @return string Thumbnail file path
 */
function getPhotoThumbnailPath($filePath, $suffix = '_thumb')
{
    $pathInfo = pathinfo($filePath);
    return $pathInfo['dirname'] . '/' . $pathInfo['filename'] . $suffix . '.' . $pathInfo['extension'];
}

/**
 * Get retention expiry date based on deleted date.
 *
 * @param string $deletedAt Deletion timestamp
 * @param int $retentionYears Retention period in years
 * @return string Expiry date
 */
function getPhotoRetentionExpiry($deletedAt, $retentionYears = 5)
{
    $deletedTime = strtotime($deletedAt);
    $expiryTime = strtotime("+{$retentionYears} years", $deletedTime);
    return date('Y-m-d H:i:s', $expiryTime);
}

/**
 * Check if a photo has expired past retention period.
 *
 * @param string $deletedAt Deletion timestamp
 * @param int $retentionYears Retention period in years
 * @return bool True if expired
 */
function isPhotoRetentionExpired($deletedAt, $retentionYears = 5)
{
    $expiryDate = getPhotoRetentionExpiry($deletedAt, $retentionYears);
    return strtotime($expiryDate) < time();
}

/**
 * Get photo status label.
 *
 * @param array $photo Photo data array
 * @return string HTML status label
 */
function getPhotoStatusLabel($photo)
{
    if (!empty($photo['deletedAt'])) {
        return '<span class="tag error">' . __('Deleted') . '</span>';
    }

    if ($photo['sharedWithParent'] === 'Y') {
        return '<span class="tag success">' . __('Shared') . '</span>';
    }

    return '<span class="tag dull">' . __('Private') . '</span>';
}

/**
 * Calculate remaining retention days for a deleted photo.
 *
 * @param string $deletedAt Deletion timestamp
 * @param int $retentionYears Retention period in years
 * @return int Remaining days (negative if expired)
 */
function getPhotoRetentionDaysRemaining($deletedAt, $retentionYears = 5)
{
    $expiryDate = getPhotoRetentionExpiry($deletedAt, $retentionYears);
    $expiryTime = strtotime($expiryDate);
    $now = time();

    return (int) floor(($expiryTime - $now) / (60 * 60 * 24));
}
